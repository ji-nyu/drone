"""드론 영상 VILA VLM 분석 — subprocess 워커 + UI 로그 버퍼."""

from __future__ import annotations

import json
import logging
import os
import subprocess
import tempfile
import threading
import time
from collections import deque
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Any

import cv2
import numpy as np
from npty.util import get_section_config

LOGGER = logging.getLogger("drone.ctrl")

_processor: "VlmProcessor | None" = None
_processor_lock = threading.Lock()


@dataclass
class VlmLogEntry:
    id: int
    ts: str
    level: str
    message: str
    drone_id: str | None = None


def _read_vlm_config() -> dict[str, Any]:
    control_dir = Path(__file__).resolve().parents[1]
    out: dict[str, Any] = {"enabled": False}
    try:
        sec = get_section_config("vlm")
    except Exception:
        return out

    out["enabled"] = sec.getboolean("enabled", fallback=False)
    out["python_path"] = sec.get(
        "python_path",
        fallback="/home/park/miniconda3/envs/vila/bin/python",
    ).strip()
    out["frame_interval_sec"] = max(0.5, sec.getfloat("frame_interval_sec", fallback=2.0))
    out["question"] = sec.get("question", fallback="이 영상에서 무엇을 볼 수 있나요?").strip()
    out["base_model"] = sec.get(
        "base_model",
        fallback="/home/park/Desktop/vlm/model/nvila_ko_chat_vector_1.5B",
    ).strip()
    out["checkpoint_dir"] = sec.get(
        "checkpoint_dir",
        fallback="/home/park/Desktop/vlm/model/nvila_ko_vlm_lora_50/checkpoint-epoch3",
    ).strip()
    out["vila_path"] = sec.get("vila_path", fallback="/home/park/Desktop/vlm/VILA").strip()
    out["max_new_tokens"] = max(1, sec.getint("max_new_tokens", fallback=25))
    out["load_lora"] = sec.getboolean("load_lora", fallback=True)
    out["max_log_entries"] = max(50, sec.getint("max_log_entries", fallback=500))
    out["worker_script"] = str(Path(__file__).resolve().parent / "vlm_worker.py")
    out["control_dir"] = str(control_dir)
    return out


class VlmProcessor:
    def __init__(self, cfg: dict[str, Any]) -> None:
        self.cfg = cfg
        self.frame_interval_sec = float(cfg["frame_interval_sec"])
        self._logs: deque[VlmLogEntry] = deque(maxlen=int(cfg["max_log_entries"]))
        self._log_id = 0
        self._log_lock = threading.Lock()
        self._last_submit: dict[str, float] = {}
        self._frame_counter: dict[str, int] = {}
        self._infer_id = 0
        self._pending: dict[int, tuple[str, int]] = {}
        self._worker: subprocess.Popen[str] | None = None
        self._reader_thread: threading.Thread | None = None
        self._stderr_thread: threading.Thread | None = None
        self._worker_lock = threading.Lock()
        self._ready = threading.Event()
        self._waiting_logged = False
        self._temp_dir = tempfile.mkdtemp(prefix="drone-vlm-")
        self._start_worker()

    def _append_log(self, level: str, message: str, drone_id: str | None = None) -> int:
        with self._log_lock:
            self._log_id += 1
            entry = VlmLogEntry(
                id=self._log_id,
                ts=datetime.now().strftime("%H:%M:%S"),
                level=level,
                message=message,
                drone_id=drone_id,
            )
            self._logs.append(entry)
            return entry.id

    def _worker_config(self) -> dict[str, Any]:
        return {
            "question": self.cfg["question"],
            "base_model": self.cfg["base_model"],
            "checkpoint_dir": self.cfg["checkpoint_dir"],
            "vila_path": self.cfg["vila_path"],
            "max_new_tokens": self.cfg["max_new_tokens"],
            "load_lora": self.cfg["load_lora"],
        }

    def _start_worker(self) -> None:
        with self._worker_lock:
            if self._worker is not None and self._worker.poll() is None:
                return

            self._ready.clear()
            env = os.environ.copy()
            env["VLM_WORKER_CONFIG"] = json.dumps(self._worker_config(), ensure_ascii=False)
            python_path = self.cfg["python_path"]
            worker_script = self.cfg["worker_script"]
            if not os.path.isfile(worker_script):
                self._append_log("error", f"VLM 워커 스크립트 없음: {worker_script}")
                return

            self._append_log("info", "VLM 워커 시작 중...")
            self._append_log("info", "=" * 70)
            self._append_log("info", "Real-time Webcam VLM - VILA Native")
            self._append_log("info", "=" * 70)
            try:
                self._worker = subprocess.Popen(
                    [python_path, worker_script],
                    cwd=self.cfg["control_dir"],
                    stdin=subprocess.PIPE,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    text=True,
                    bufsize=1,
                    env=env,
                )
            except Exception as e:  # noqa: BLE001
                self._append_log("error", f"VLM 워커 시작 실패: {e}")
                return

            self._stderr_thread = threading.Thread(
                target=self._read_worker_stderr,
                args=(self._worker,),
                daemon=True,
                name="vlm-worker-stderr",
            )
            self._stderr_thread.start()
            self._reader_thread = threading.Thread(
                target=self._read_worker_stdout,
                args=(self._worker,),
                daemon=True,
                name="vlm-worker-reader",
            )
            self._reader_thread.start()

    def _read_worker_stderr(self, proc: subprocess.Popen[str]) -> None:
        if proc.stderr is None:
            return
        for raw in proc.stderr:
            line = raw.strip()
            if not line:
                continue
            LOGGER.warning("vlm worker stderr: %s", line)
            if "Error" in line or "Traceback" in line or "Exception" in line:
                self._append_log("error", f"VLM: {line[:300]}")

    def _stop_worker_unlocked(self) -> None:
        proc = self._worker
        if proc is None:
            return
        try:
            if proc.stdin:
                proc.stdin.write(json.dumps({"op": "shutdown"}) + "\n")
                proc.stdin.flush()
        except Exception:  # noqa: BLE001
            pass
        try:
            proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
        self._worker = None

    def _read_worker_stdout(self, proc: subprocess.Popen[str]) -> None:
        if proc.stdout is None:
            return

        for raw in proc.stdout:
            line = raw.strip()
            if not line:
                continue
            try:
                msg = json.loads(line)
            except json.JSONDecodeError:
                LOGGER.debug("vlm worker non-json: %s", line)
                continue

            mtype = msg.get("type")
            if mtype == "log":
                level = msg.get("level", "info")
                if level == "warn":
                    level = "info"
                text = msg.get("message", "")
                if text.startswith("✅"):
                    level = "ok"
                elif text.startswith("⚠️"):
                    level = "info"
                self._append_log(level, text)
            elif mtype == "ready":
                self._waiting_logged = False
                self._append_log("info", "")
                self._append_log("info", "=" * 70)
                self._append_log("info", "실시간 분석 시작...")
                self._append_log("info", "=" * 70)
                self._ready.set()
            elif mtype == "result":
                req_id = msg.get("id")
                drone_id, frame_num = self._pending.pop(req_id, (None, msg.get("frame_num", 0)))
                inference_time = msg.get("inference_time", 0)
                answer = msg.get("answer", "")
                self._append_log(
                    "info",
                    f"[프레임 {frame_num}] {inference_time:.2f}초",
                    drone_id=drone_id,
                )
                self._append_log("ok", f"답변: {answer}", drone_id=drone_id)
                self._append_log("info", "-" * 70, drone_id=drone_id)
            elif mtype == "error":
                req_id = msg.get("id")
                drone_id, _ = self._pending.pop(req_id, (None, 0))
                prefix = f"[{drone_id}] " if drone_id else ""
                self._append_log("error", f"{prefix}추론 오류: {msg.get('message', '')}", drone_id=drone_id)
            elif mtype == "fatal":
                self._append_log("error", f"[VLM] {msg.get('message', 'fatal error')}")
                self._ready.set()

        rc = proc.poll()
        if rc is not None:
            self._ready.clear()
            self._append_log("error", f"VLM 워커 종료됨 (code={rc})")

    def submit_frame(self, drone_id: str, frame_bgr: np.ndarray) -> None:
        if not self._ready.is_set():
            if not self._waiting_logged:
                self._waiting_logged = True
                self._append_log(
                    "info",
                    "VLM 모델 로드 중입니다. 완료되면 분석이 시작됩니다 (약 1~3분).",
                )
            return

        now = time.monotonic()
        last = self._last_submit.get(drone_id, 0.0)
        if now - last < self.frame_interval_sec:
            return
        self._last_submit[drone_id] = now

        self._frame_counter[drone_id] = self._frame_counter.get(drone_id, 0) + 1
        frame_num = self._frame_counter[drone_id]

        ok, buf = cv2.imencode(".jpg", frame_bgr, [cv2.IMWRITE_JPEG_QUALITY, 85])
        if not ok:
            return

        path = os.path.join(self._temp_dir, f"{drone_id}_{frame_num}.jpg")
        try:
            with open(path, "wb") as f:
                f.write(buf.tobytes())
        except OSError as e:
            self._append_log("error", f"[{drone_id}] 프레임 저장 실패: {e}", drone_id=drone_id)
            return

        with self._worker_lock:
            proc = self._worker
            if proc is None or proc.poll() is not None:
                self._start_worker()
                proc = self._worker
            if proc is None or proc.stdin is None:
                return

            self._infer_id += 1
            req_id = self._infer_id
            self._pending[req_id] = (drone_id, frame_num)
            try:
                proc.stdin.write(
                    json.dumps(
                        {"op": "infer", "id": req_id, "frame_num": frame_num, "path": path},
                        ensure_ascii=False,
                    )
                    + "\n"
                )
                proc.stdin.flush()
            except Exception as e:  # noqa: BLE001
                self._pending.pop(req_id, None)
                self._append_log("error", f"[{drone_id}] VLM 요청 실패: {e}", drone_id=drone_id)

    def get_logs(self, since_id: int = 0, drone_id: str | None = None, limit: int = 100) -> list[dict[str, Any]]:
        with self._log_lock:
            items = [e for e in self._logs if e.id > since_id]
            if drone_id:
                items = [e for e in items if e.drone_id in (None, drone_id)]
            items = items[-limit:]
            return [
                {
                    "id": e.id,
                    "ts": e.ts,
                    "level": e.level,
                    "message": e.message,
                    "drone_id": e.drone_id,
                }
                for e in items
            ]

    def get_status(self) -> dict[str, Any]:
        proc = self._worker
        alive = proc is not None and proc.poll() is None
        return {
            "ready": self._ready.is_set(),
            "worker_alive": alive,
            "pending_inferences": len(self._pending),
        }

    def clear_logs(self, drone_id: str | None = None) -> None:
        with self._log_lock:
            if drone_id is None:
                self._logs.clear()
            else:
                kept = [e for e in self._logs if e.drone_id not in (None, drone_id)]
                self._logs = deque(kept, maxlen=self._logs.maxlen)


def get_vlm_processor() -> VlmProcessor | None:
    global _processor
    cfg = _read_vlm_config()
    if not cfg.get("enabled"):
        return None
    with _processor_lock:
        if _processor is None:
            try:
                _processor = VlmProcessor(cfg)
            except Exception as e:  # noqa: BLE001
                LOGGER.error("VLM init failed: %s", e)
                return None
        return _processor
