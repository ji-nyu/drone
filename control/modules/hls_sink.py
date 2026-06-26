"""드론 영상 UDP(H.264)를 HLS로 기록 (stream_on / stream_off와 연동).

같은 호스트에서 PyAV(djitellopy `get_frame_read`)와 ffmpeg가 동시에 11111을
쓰면, **바인드 순서** 때문에 `Address already in use`가 난다. 그래서
`DroneFleet.stream_on`에서는 **먼저** `warm_video_receiver()`로 PyAV를 연 뒤,
ffmpeg 입력 URL에 `reuse=1`을 붙여 **두 번째** 바인드로 붙인다.

AP에서 한 포트로만 들어오면 드론 구분은 없다. `reuse`로도 충돌이 나면
수신 경로를 분리하거나 HLS를 끄고 스냅샷만 쓴다.
"""

from __future__ import annotations

import atexit
import configparser
import logging
import os
import shutil
import signal
import subprocess
import threading
from pathlib import Path
from typing import Any

LOGGER = logging.getLogger("drone.hls")

_lock = threading.RLock()
_ref = 0
_proc: subprocess.Popen | None = None
_stderr_f: Any = None


def _config_path() -> Path:
    return Path(
        os.environ.get("CONFIG_FILE")
        or os.environ.get("NPTY_CONFIG_FILE")
        or (Path(__file__).resolve().parents[1] / "config.ini")
    )


def _read_hls_section() -> dict[str, Any]:
    p = _config_path()
    out: dict[str, Any] = {
        "enabled": True,
        "output_root": "/data/www/droneControl/public/stream",
        "segment_dir": "1",
        "udp_url": "udp://0.0.0.0:11111",
        "ffmpeg": "ffmpeg",
    }
    if not p.is_file():
        return out
    cfg = configparser.ConfigParser()
    cfg.read(p, encoding="utf-8")
    if not cfg.has_section("hls"):
        return out
    sec = cfg["hls"]
    out["enabled"] = sec.getboolean("enabled", fallback=True)
    out["output_root"] = sec.get("output_root", fallback=out["output_root"]).strip()
    out["segment_dir"] = sec.get("segment_dir", fallback=out["segment_dir"]).strip() or "1"
    out["udp_url"] = sec.get("udp_url", fallback=out["udp_url"]).strip()
    out["ffmpeg"] = sec.get("ffmpeg", fallback=out["ffmpeg"]).strip() or "ffmpeg"
    return out


def _hls_paths() -> tuple[Path, Path, Path]:
    """playlist, segment_dir, segment_filename_pattern (seg_%03d.ts dir + pattern name)."""
    c = _read_hls_section()
    root = Path(c["output_root"]).resolve()
    seg_dir = root / c["segment_dir"]
    playlist = root / "index.m3u8"
    seg_pattern = seg_dir / "seg_%03d.ts"
    return playlist, seg_dir, seg_pattern


def is_enabled() -> bool:
    return bool(_read_hls_section()["enabled"])


def _stop_ffmpeg_unlocked() -> None:
    global _proc, _stderr_f
    proc = _proc
    _proc = None
    if _stderr_f is not None:
        try:
            _stderr_f.close()
        except OSError:
            pass
        _stderr_f = None
    if proc is None or proc.poll() is not None:
        return
    try:
        if os.name != "nt":
            os.killpg(os.getpgid(proc.pid), signal.SIGTERM)
        else:
            proc.terminate()
    except (ProcessLookupError, OSError) as e:
        LOGGER.warning("hls ffmpeg terminate: %s", e)
    try:
        proc.wait(timeout=5)
    except subprocess.TimeoutExpired:
        try:
            if os.name != "nt":
                os.killpg(os.getpgid(proc.pid), signal.SIGKILL)
            else:
                proc.kill()
        except (ProcessLookupError, OSError):
            pass
        proc.wait(timeout=2)
    LOGGER.info("hls ffmpeg stopped")


def _start_ffmpeg_unlocked() -> dict[str, Any]:
    global _proc, _stderr_f
    c = _read_hls_section()
    if not c["enabled"]:
        return {"hls": "disabled"}
    ff = c["ffmpeg"]
    if not shutil.which(ff):
        LOGGER.error("hls enabled but ffmpeg not found in PATH: %s", ff)
        return {"hls": "error", "detail": "ffmpeg_not_found"}
    idx, seg_parent, seg = _hls_paths()
    seg_parent.mkdir(parents=True, exist_ok=True)
    idx.parent.mkdir(parents=True, exist_ok=True)
    log_dir = Path(__file__).resolve().parents[1] / "logs"
    log_dir.mkdir(parents=True, exist_ok=True)
    log_path = log_dir / "hls_ffmpeg.log"
    _stderr_f = open(log_path, "ab", buffering=0)
    # 사용자 제공 명령과 동일 구조: 세그는 output_root/<segment_dir>/, 플레이리스트는 output_root/index.m3u8
    cmd = [
        ff,
        "-nostdin",
        "-y",
        "-fflags",
        "nobuffer",
        "-flags",
        "low_delay",
        "-f",
        "h264",
        "-i",
        c["udp_url"],
        "-vf",
        "format=yuv420p",
        "-c:v",
        "libx264",
        "-preset",
        "veryfast",
        "-tune",
        "zerolatency",
        "-f",
        "hls",
        "-hls_time",
        "2",
        "-hls_list_size",
        "5",
        "-hls_flags",
        "delete_segments+append_list",
        "-hls_segment_filename",
        str(seg),
        str(idx),
    ]
    LOGGER.info("starting hls ffmpeg -> %s (segments under %s)", idx, seg_parent)
    try:
        _proc = subprocess.Popen(
            cmd,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=_stderr_f,
            preexec_fn=os.setsid if os.name != "nt" else None,
        )
    except OSError as e:
        LOGGER.exception("hls ffmpeg start failed: %s", e)
        if _stderr_f:
            _stderr_f.close()
            _stderr_f = None
        return {"hls": "error", "detail": str(e)}
    return {
        "hls": "recording",
        "playlist": str(idx),
        "segment_pattern": str(seg),
        "segment_dir": str(seg_parent),
    }


def after_stream_on(start_ffmpeg: bool = True) -> dict[str, Any]:
    """stream_on 성공 직후 호출. 첫 스트림이 켜질 때 ffmpeg를 띄운다.

    PyAV(djitellopy)가 먼저 UDP를 열어야 하므로, 호출부에서 warm_video_receiver 후
    start_ffmpeg=True 로 넘긴다. warm 실패 시 False로 두면 ffmpeg만 생략한다.
    """
    global _ref
    with _lock:
        c = _read_hls_section()
        if not c["enabled"]:
            return {"hls": "disabled"}
        _ref += 1
        if _ref == 1:
            if not start_ffmpeg:
                LOGGER.warning(
                    "hls: ffmpeg not started (PyAV receiver not warmed); snapshots still work"
                )
                return {"hls": "skipped_warm", "ref": 1}
            if _proc is not None and _proc.poll() is None:
                LOGGER.warning("hls ref=1 but process already running; resetting")
                _stop_ffmpeg_unlocked()
            started = _start_ffmpeg_unlocked()
            if started.get("hls") == "error":
                _ref = 0
            return started
        idx, _, _ = _hls_paths()
        return {
            "hls": "recording",
            "ref": _ref,
            "playlist": str(idx),
        }


def after_stream_off() -> dict[str, Any]:
    """stream_off 성공 직후 호출. 마지막 스트림이 꺼지면 ffmpeg를 종료한다."""
    global _ref
    with _lock:
        c = _read_hls_section()
        if not c["enabled"]:
            return {"hls": "disabled"}
        if _ref > 0:
            _ref -= 1
        if _ref == 0:
            _stop_ffmpeg_unlocked()
            return {"hls": "stopped"}
        return {"hls": "recording", "ref": _ref}


def shutdown() -> None:
    """프로세스 종료 시 ffmpeg 정리."""
    global _ref
    with _lock:
        _ref = 0
        _stop_ffmpeg_unlocked()


atexit.register(shutdown)
