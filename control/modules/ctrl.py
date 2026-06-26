"""Tello 드론 제어 래퍼 + 멀티 드론(DroneFleet)."""

from __future__ import annotations

import os
import threading
import time
import zlib
from datetime import datetime
from pathlib import Path
from typing import Any, Callable
import logging
from logging.handlers import RotatingFileHandler

import cv2
import npty.db
import numpy as np
from djitellopy import Tello, TelloException
from npty.db import DB
from npty.util import get_section_config

from modules.yolo_detector import get_yolo_processor
from modules.vlm_processor import get_vlm_processor

API_AUTHORIZATION = "tello-api-secret-change-me"


def _get_logger() -> logging.Logger:
    log_dir = Path(__file__).resolve().parents[1] / "logs"
    log_dir.mkdir(parents=True, exist_ok=True)
    logger = logging.getLogger("drone.ctrl")
    if logger.handlers:
        return logger
    logger.setLevel(logging.INFO)
    fh = RotatingFileHandler(log_dir / "ctrl.log", maxBytes=1_000_000, backupCount=5, encoding="utf-8")
    fh.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(name)s - %(message)s"))
    logger.addHandler(fh)
    return logger


LOGGER = _get_logger()

_VIDEO_RESOLUTION_MAP = {
    "480p": Tello.RESOLUTION_480P,
    "720p": Tello.RESOLUTION_720P,
    "low": Tello.RESOLUTION_480P,
    "high": Tello.RESOLUTION_720P,
}
_VIDEO_FPS_MAP = {
    "5": Tello.FPS_5,
    "15": Tello.FPS_15,
    "30": Tello.FPS_30,
    "low": Tello.FPS_5,
    "middle": Tello.FPS_15,
    "high": Tello.FPS_30,
}
_VIDEO_BITRATE_MAP = {
    "auto": Tello.BITRATE_AUTO,
    "0": Tello.BITRATE_AUTO,
    "1": Tello.BITRATE_1MBPS,
    "2": Tello.BITRATE_2MBPS,
    "3": Tello.BITRATE_3MBPS,
    "4": Tello.BITRATE_4MBPS,
    "5": Tello.BITRATE_5MBPS,
}
_VIDEO_DIRECTION_MAP = {
    "forward": Tello.CAMERA_FORWARD,
    "downward": Tello.CAMERA_DOWNWARD,
    "0": Tello.CAMERA_FORWARD,
    "1": Tello.CAMERA_DOWNWARD,
}


def _read_video_config() -> dict[str, Any]:
    """npty.util로 [video]를 읽어 공통 비디오 설정을 반환."""
    out: dict[str, Any] = {"enabled": False}
    try:
        sec = get_section_config("video")
    except Exception:
        return out
    out["enabled"] = sec.getboolean("enabled", fallback=False)
    out["resolution"] = _VIDEO_RESOLUTION_MAP.get(sec.get("resolution", fallback="").strip().lower())
    out["fps"] = _VIDEO_FPS_MAP.get(sec.get("fps", fallback="").strip().lower())
    out["bitrate"] = _VIDEO_BITRATE_MAP.get(sec.get("bitrate", fallback="").strip().lower())
    out["direction"] = _VIDEO_DIRECTION_MAP.get(sec.get("direction", fallback="").strip().lower())
    out["stale_restart"] = sec.getboolean("stale_restart", fallback=True)
    out["stale_restart_sec"] = max(0.5, sec.getfloat("stale_restart_sec", fallback=2.0))
    return out


def _read_telemetry_config() -> dict[str, Any]:
    """npty.util로 [telemetry]를 읽어 주기 저장 설정을 반환."""
    out: dict[str, Any] = {"enabled": False, "interval_sec": 2.0}
    try:
        sec = get_section_config("telemetry")
    except Exception:
        return out
    out["enabled"] = sec.getboolean("enabled", fallback=False)
    out["interval_sec"] = max(0.5, sec.getfloat("interval_sec", fallback=2.0))
    return out


# mission_start: 액션 하나 성공 직후 다음 액션 전 대기(초). config `[mission]`에서 타입별 덮어쓰기 가능. 상한은 _MISSION_DELAY_CAP_SEC.
_MISSION_DELAY_CAP_SEC = 5.0


def _default_mission_delay_after_map() -> dict[str, float]:
    return {
        "takeoff": 1.0,
        "land": 0.35,
        "stream_on": 0.2,
        "stream_off": 0.2,
        "emergency": 0.2,
        "forward": 0.25,
        "back": 0.25,
        "left": 0.25,
        "right": 0.25,
        "up": 0.25,
        "down": 0.25,
        "rotate_cw": 0.25,
        "rotate_ccw": 0.25,
        "hover": 0.2,
        "deliver": 0.0,
        "rc": 0.1,
        "_default": 0.2,
    }


def _read_mission_delay_after_map(sec: Any) -> dict[str, float]:
    m = _default_mission_delay_after_map()
    cap = _MISSION_DELAY_CAP_SEC
    for k in list(m.keys()):
        if k == "_default":
            continue
        opt = f"delay_after_{k}_sec"
        try:
            m[k] = max(0.0, min(cap, float(sec.getfloat(opt, fallback=m[k]))))
        except Exception:
            m[k] = max(0.0, min(cap, m[k]))
    try:
        m["_default"] = max(0.0, min(cap, float(sec.getfloat("delay_after_default_sec", fallback=m["_default"]))))
    except Exception:
        m["_default"] = max(0.0, min(cap, m["_default"]))
    return m


def _read_mission_config() -> dict[str, Any]:
    out: dict[str, Any] = {
        "retry_on_lock": True,
        "lock_retry_interval_sec": 0.5,
        "lock_retry_max_attempts": 20,
        "delay_after_action": _default_mission_delay_after_map(),
    }
    try:
        sec = get_section_config("mission")
    except Exception:
        return out
    out["retry_on_lock"] = sec.getboolean("retry_on_lock", fallback=True)
    out["lock_retry_interval_sec"] = max(0.05, sec.getfloat("lock_retry_interval_sec", fallback=0.5))
    out["lock_retry_max_attempts"] = max(1, sec.getint("lock_retry_max_attempts", fallback=20))
    out["delay_after_action"] = _read_mission_delay_after_map(sec)
    return out


def is_api_authorized(authorization: str | None) -> bool:
    if authorization is None:
        return False
    return authorization.strip() == API_AUTHORIZATION


class DroneController:
    """스레드 안전 Tello 래퍼."""

    def __init__(self, drone_id: str | None = None) -> None:
        self._drone_id = drone_id
        self._cmd_lock = threading.RLock()
        # 기존 코드 호환용 별칭
        self._lock = self._cmd_lock
        self._frame_lock = threading.RLock()
        self._tello: Tello | None = None
        self._host: str | None = None
        self._frame_reader: Any | None = None
        self._latest_jpeg: bytes | None = None
        self._frame_pump_thread: threading.Thread | None = None
        self._frame_pump_stop = threading.Event()
        self._last_frame_signature: int | None = None
        self._last_frame_change_ts: float = 0.0
        self._same_frame_count: int = 0
        self._stale_restart_enabled: bool = True
        self._stale_restart_sec: float = 2.0
        self._yolo_busy: bool = False
        self._yolo_last_ts: float = 0.0

    @property
    def connected(self) -> bool:
        return self._tello is not None

    def _require(self) -> Tello:
        if self._tello is None:
            raise RuntimeError("not_connected")
        return self._tello

    def _acquire_or_raise_locked(self) -> None:
        if not self._cmd_lock.acquire(blocking=False):
            raise RuntimeError("locked")

    def connect(self, host: str, wait_for_state: bool = True) -> dict[str, Any]:
        with self._lock:
            if self._tello is not None:
                LOGGER.info("connect skipped already connected host=%s", self._host)
                return {"ok": True, "already": True, "host": self._host}
            # 전원 꺼짐/네트워크 단절 상황에서 startup 지연을 줄이기 위해
            # 내부 재시도 횟수를 최소화한다.
            t = Tello(host=host, retry_count=1)
            t.connect(wait_for_state=wait_for_state)
            self._tello = t
            self._host = host
            LOGGER.info("connected host=%s", host)
            return {"ok": True, "host": host}

    def disconnect(self) -> dict[str, Any]:
        with self._lock:
            if self._tello is None:
                return {"ok": True, "already": True}
            try:
                self._stop_frame_pump()
                with self._frame_lock:
                    if self._frame_reader is not None:
                        try:
                            self._frame_reader.stop()
                        except Exception:  # noqa: BLE001
                            pass
                        self._frame_reader = None
                self._tello.end()
            except TelloException:
                pass
            self._tello = None
            self._host = None
            LOGGER.info("disconnected")
            return {"ok": True}

    def stream_on(self) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            t = self._require()
            self._apply_watchdog_config(_read_video_config())
            try:
                self.apply_video_settings(_read_video_config())
            except Exception as e:  # noqa: BLE001
                LOGGER.warning("video settings before stream_on failed host=%s: %s", self._host, e)
            t.streamon()
            with self._frame_lock:
                
                self._restart_frame_reader_unlocked(t)
            self._start_frame_pump()
            try:
                get_yolo_processor()
            except Exception:  # noqa: BLE001
                pass
            try:
                get_vlm_processor()
            except Exception:  # noqa: BLE001
                pass
            return {"ok": True}
        finally:
            self._cmd_lock.release()

    def stream_off(self) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            t = self._require()
            self._stop_frame_pump()
            t.streamoff()
            with self._frame_lock:
                if self._frame_reader is not None:
                    try:
                        self._frame_reader.stop()
                    except Exception:  # noqa: BLE001
                        pass
                    self._frame_reader = None
                self._last_frame_signature = None
                self._last_frame_change_ts = 0.0
                self._same_frame_count = 0
            return {"ok": True}
        finally:
            self._cmd_lock.release()

    def takeoff(self, pause_stream_first: bool = False) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            t = self._require()
            had_stream = bool(getattr(t, "stream_on", False))
            if pause_stream_first and had_stream:
                with self._frame_lock:
                    if self._frame_reader is not None:
                        try:
                            self._frame_reader.stop()
                        except Exception:  # noqa: BLE001
                            pass
                        self._frame_reader = None
                t.streamoff()
            try:
                t.takeoff()
            finally:
                if pause_stream_first and had_stream:
                    try:
                        t.streamon()
                        with self._frame_lock:
                            self._restart_frame_reader_unlocked(t)
                        self._start_frame_pump()
                    except TelloException:
                        pass
            return {"ok": True}
        finally:
            self._cmd_lock.release()

    def land(self) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().land()
            return {"ok": True}
        finally:
            self._cmd_lock.release()

    def move_forward(self, cm: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().move_forward(int(cm))
            return {"ok": True, "cm": int(cm)}
        finally:
            self._cmd_lock.release()

    def move_back(self, cm: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().move_back(int(cm))
            return {"ok": True, "cm": int(cm)}
        finally:
            self._cmd_lock.release()

    def move_left(self, cm: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().move_left(int(cm))
            return {"ok": True, "cm": int(cm)}
        finally:
            self._cmd_lock.release()

    def move_right(self, cm: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().move_right(int(cm))
            return {"ok": True, "cm": int(cm)}
        finally:
            self._cmd_lock.release()

    def move_up(self, cm: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().move_up(int(cm))
            return {"ok": True, "cm": int(cm)}
        finally:
            self._cmd_lock.release()

    def move_down(self, cm: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().move_down(int(cm))
            return {"ok": True, "cm": int(cm)}
        finally:
            self._cmd_lock.release()

    def rotate_cw(self, degree: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().rotate_clockwise(int(degree))
            return {"ok": True, "degree": int(degree)}
        finally:
            self._cmd_lock.release()

    def rotate_ccw(self, degree: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().rotate_counter_clockwise(int(degree))
            return {"ok": True, "degree": int(degree)}
        finally:
            self._cmd_lock.release()

    def hover(self, sec: int | float) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            time.sleep(float(sec))
            return {"ok": True, "hover_sec": float(sec)}
        finally:
            self._cmd_lock.release()

    def deliver(self) -> dict[str, Any]:
        """배송 완료 확인 등 외부 비즈니스 훅 자리(현재는 no-op)."""
        return {"ok": True, "skipped": True, "reason": "deliver action has no drone command"}

    def rc(self, lr: int, fb: int, ud: int, yaw: int) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            t = self._require()
            before_ts = float(getattr(t, "last_rc_control_timestamp", 0.0))
            t.send_rc_control(lr, fb, ud, yaw)
            after_ts = float(getattr(t, "last_rc_control_timestamp", before_ts))
            sent = after_ts > before_ts
            return {
                "ok": True,
                "sent": sent,
                "skipped_by_sdk_interval": (not sent),
                "sdk_interval_sec": float(getattr(t, "TIME_BTW_RC_CONTROL_COMMANDS", 0.001)),
                "requested": {"lr": lr, "fb": fb, "ud": ud, "yaw": yaw},
                "last_rc_control_timestamp_before": before_ts,
                "last_rc_control_timestamp_after": after_ts,
            }
        finally:
            self._cmd_lock.release()

    def emergency(self) -> dict[str, Any]:
        self._acquire_or_raise_locked()
        try:
            self._require().emergency()
            return {"ok": True}
        finally:
            self._cmd_lock.release()

    def state(self) -> dict[str, Any]:
        with self._lock:
            return dict(self._require().get_current_state())

    def battery(self) -> dict[str, Any]:
        with self._lock:
            return {"battery": self._require().get_battery()}

    def diagnostics(self) -> dict[str, Any]:
        with self._lock:
            t = self._require()
            out: dict[str, Any] = {
                "stream_on": bool(getattr(t, "stream_on", False)),
                "is_flying": bool(getattr(t, "is_flying", False)),
            }
            try:
                out["state"] = dict(t.get_current_state())
            except TelloException:
                out["state"] = {}
            for key, getter in (
                ("battery", lambda: t.get_battery()),
                ("height_cm", lambda: t.get_height()),
                ("temperature_c", lambda: t.get_temperature()),
            ):
                try:
                    out[key] = getter()
                except (TelloException, RuntimeError, KeyError):
                    out[key] = None
            return out

    def ping(self) -> dict[str, Any]:
        return {"ok": True, "connected": self.connected, "host": self._host}

    def get_frame_jpeg(self, quality: int = 80) -> bytes | None:
        with self._frame_lock:
            if self._latest_jpeg is not None:
                return self._latest_jpeg
            t = self._tello
            if t is None or not getattr(t, "stream_on", False):
                return None
            if self._frame_reader is None:
                self._frame_reader = t.get_frame_read()
            raw = self._frame_reader.frame
            if raw is None or (isinstance(raw, np.ndarray) and raw.size == 0):
                return None
            frame = np.ascontiguousarray(raw)
        ok, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, int(quality)])
        if not ok:
            return None
        return buf.tobytes()

    def _submit_yolo_async(self, frame_bgr: np.ndarray) -> None:
        """YOLO 추론은 백그라운드 — 라이브 영상은 apply_overlay로 즉시 표시."""
        if self._yolo_busy:
            return
        yolo = get_yolo_processor()
        if yolo is None:
            return
        now = time.monotonic()
        interval = getattr(yolo, "detect_interval_sec", 0.4)
        if now - self._yolo_last_ts < interval:
            return
        self._yolo_last_ts = now
        self._yolo_busy = True
        source = self._host or ""
        frame_copy = frame_bgr.copy()

        def work() -> None:
            try:
                proc = get_yolo_processor()
                if proc is not None:
                    proc.detect_and_cache(frame_copy, source=source)
            except Exception as e:  # noqa: BLE001
                LOGGER.warning("YOLO detect failed host=%s: %s", self._host, e)
            finally:
                self._yolo_busy = False

        threading.Thread(target=work, daemon=True, name=f"yolo-{self._host or 'drone'}").start()

    def _start_frame_pump(self) -> None:
        """drontest.py 처럼 백그라운드에서 프레임을 계속 읽어 JPEG 캐시를 갱신한다."""
        self._stop_frame_pump()
        self._frame_pump_stop = threading.Event()
        self._latest_jpeg = None

        def pump() -> None:
            while not self._frame_pump_stop.is_set():
                try:
                    t = self._tello
                    if t is None or not getattr(t, "stream_on", False):
                        self._frame_pump_stop.wait(0.05)
                        continue

                    raw = None
                    with self._frame_lock:
                        if self._frame_reader is None:
                            self._restart_frame_reader_unlocked(t)
                        if self._frame_reader is not None:
                            raw = self._frame_reader.frame

                    if raw is None or not isinstance(raw, np.ndarray) or raw.size == 0:
                        self._frame_pump_stop.wait(0.03)
                        continue

                    frame = np.ascontiguousarray(raw)
                    now = time.monotonic()
                    signature = int(zlib.crc32(frame[::32, ::32].tobytes()))

                    with self._frame_lock:
                        if self._last_frame_signature != signature:
                            self._last_frame_signature = signature
                            self._last_frame_change_ts = now
                            self._same_frame_count = 0
                        else:
                            self._same_frame_count += 1
                            stale_for = now - self._last_frame_change_ts
                            if (
                                self._stale_restart_enabled
                                and self._last_frame_change_ts > 0
                                and stale_for >= self._stale_restart_sec
                                and self._same_frame_count >= 15
                            ):
                                if self._restart_frame_reader_unlocked(t):
                                    LOGGER.warning(
                                        "frame pump stale -> reader restarted host=%s stale_for=%.2fs",
                                        self._host,
                                        stale_for,
                                    )
                                    self._frame_pump_stop.wait(0.3)
                                    continue

                    frame_bgr = cv2.cvtColor(frame, cv2.COLOR_RGB2BGR)

                    # drontest.py처럼 프레임을 즉시 읽고, YOLO 박스는 캐시에서 오버레이
                    frame_out = frame_bgr
                    yolo = get_yolo_processor()
                    if yolo is not None:
                        frame_out = yolo.apply_overlay(frame_bgr)

                    ok, buf = cv2.imencode(".jpg", frame_out, [cv2.IMWRITE_JPEG_QUALITY, 75])
                    if ok:
                        with self._frame_lock:
                            self._latest_jpeg = buf.tobytes()

                    if self._drone_id:
                        vlm = get_vlm_processor()
                        if vlm is not None:
                            try:
                                vlm.submit_frame(self._drone_id, frame_bgr)
                            except Exception as e:  # noqa: BLE001
                                LOGGER.warning("VLM submit failed drone_id=%s: %s", self._drone_id, e)

                    self._submit_yolo_async(frame_bgr)
                except Exception as e:  # noqa: BLE001
                    LOGGER.debug("frame pump error host=%s: %s", self._host, e)

                self._frame_pump_stop.wait(1 / 30)

        self._frame_pump_thread = threading.Thread(
            target=pump,
            daemon=True,
            name=f"frame-pump-{self._host or 'drone'}",
        )
        self._frame_pump_thread.start()
        LOGGER.info("frame pump started host=%s", self._host)

    def _stop_frame_pump(self) -> None:
        self._frame_pump_stop.set()
        if self._frame_pump_thread is not None and self._frame_pump_thread.is_alive():
            self._frame_pump_thread.join(timeout=2.0)
        self._frame_pump_thread = None
        with self._frame_lock:
            self._latest_jpeg = None

    def _apply_watchdog_config(self, settings: dict[str, Any]) -> None:
        self._stale_restart_enabled = bool(settings.get("stale_restart", True))
        try:
            self._stale_restart_sec = max(0.5, float(settings.get("stale_restart_sec", 2.0)))
        except (TypeError, ValueError):
            self._stale_restart_sec = 2.0

    def _restart_frame_reader_unlocked(self, t: Tello) -> bool:
        try:
            if self._frame_reader is not None:
                try:
                    self._frame_reader.stop()
                except Exception:  # noqa: BLE001
                    pass
            self._frame_reader = t.get_frame_read()
            self._last_frame_signature = None
            self._last_frame_change_ts = time.monotonic()
            self._same_frame_count = 0
            return True
        except Exception as e:  # noqa: BLE001
            LOGGER.warning("frame reader restart failed host=%s err=%s", self._host, e)
            return False

    def apply_video_settings(self, settings: dict[str, Any]) -> dict[str, Any]:
        """config.ini [video] 공통 설정을 현재 드론에 적용."""
        with self._lock:
            t = self._require()
            self._apply_watchdog_config(settings)
            applied: dict[str, Any] = {"ok": True}
            resolution = settings.get("resolution")
            if resolution is not None:
                t.set_video_resolution(resolution)
                applied["resolution"] = resolution
            fps = settings.get("fps")
            if fps is not None:
                t.set_video_fps(fps)
                applied["fps"] = fps
            bitrate = settings.get("bitrate")
            if bitrate is not None:
                t.set_video_bitrate(int(bitrate))
                applied["bitrate"] = int(bitrate)
            direction = settings.get("direction")
            if direction is not None:
                t.set_video_direction(int(direction))
                applied["direction"] = int(direction)
            return applied


class DroneFleet:
    """드론 ID 기반 서비스. DB에서 목록을 읽어 자동 연결."""

    def __init__(self) -> None:
        self._lock = threading.RLock()
        self._registry: dict[str, dict[str, Any]] = {}
        self._controllers: dict[str, DroneController] = {}
        self.default_drone_id = "main"
        self._telemetry_thread: threading.Thread | None = None
        self._telemetry_stop = threading.Event()
        self._telemetry_cfg: dict[str, Any] = {"enabled": False, "interval_sec": 2.0}

    def _require_id(self, drone_id: str) -> None:
        if drone_id not in self._registry:
            raise RuntimeError(f"unknown_drone_id:{drone_id}")

    def _ctrl(self, drone_id: str) -> DroneController:
        self._require_id(drone_id)
        return self._controllers[drone_id]

    def load_from_db(self, config_path: str | None = None) -> dict[str, Any]:
        """drones 테이블을 읽어 레지스트리 갱신."""
        if config_path:
            os.environ["CONFIG_FILE"] = config_path
        elif "CONFIG_FILE" not in os.environ and "NPTY_CONFIG_FILE" not in os.environ:
            default_cfg = Path(__file__).resolve().parents[1] / "config.ini"
            os.environ["CONFIG_FILE"] = str(default_cfg)

        sql = "SELECT drone_id, ip, label, model, is_active FROM drones ORDER BY id"
        df = npty.db.read_sql(sql)
        LOGGER.info("loaded drones from db count=%s", len(df))

        registry: dict[str, dict[str, Any]] = {}
        for _, row in df.iterrows():
            did = str(row["drone_id"])
            host = str(row["ip"])
            is_active = int(row.get("is_active") or 0)
            registry[did] = {
                "id": did,
                "name": str(row.get("label") or did),
                "host": host,
                "model": str(row.get("model") or ""),
                "is_active": is_active,
            }

        with self._lock:
            self._registry = registry
            for drone_id in registry:
                ctrl = self._controllers.get(drone_id)
                if ctrl is None:
                    self._controllers[drone_id] = DroneController(drone_id=drone_id)
                else:
                    ctrl._drone_id = drone_id
            if registry and self.default_drone_id not in registry:
                self.default_drone_id = next(iter(registry.keys()))

        return {"ok": True, "count": len(registry), "default_drone_id": self.default_drone_id}

    def connect_all(self, wait_for_state: bool = True) -> dict[str, Any]:
        results: dict[str, Any] = {}
        video_cfg = _read_video_config()
        self._telemetry_cfg = _read_telemetry_config()
        for drone_id, cfg in self._registry.items():
            if int(cfg.get("is_active", 0)) != 1:
                results[drone_id] = {"ok": True, "skipped": True, "reason": "is_active=0"}
                LOGGER.info("auto connect skipped drone_id=%s reason=is_active=0", drone_id)
                continue
            try:
                results[drone_id] = self._controllers[drone_id].connect(
                    host=str(cfg["host"]), wait_for_state=wait_for_state
                )
                if video_cfg.get("enabled"):
                    try:
                        results[drone_id]["video"] = self._controllers[drone_id].apply_video_settings(video_cfg)
                    except Exception as e:  # noqa: BLE001
                        results[drone_id]["video"] = {"ok": False, "error": str(e)}
                        LOGGER.warning("video config apply failed drone_id=%s: %s", drone_id, e)
            except Exception as e:  # noqa: BLE001
                results[drone_id] = {"ok": False, "error": str(e)}
                LOGGER.exception("auto connect failed drone_id=%s host=%s", drone_id, cfg.get("host"))
        LOGGER.info("auto connect result=%s", results)
        self.start_telemetry_worker()
        return {"ok": True, "results": results}

    def _build_telemetry_row(self, drone_id: str, ctrl: DroneController) -> dict[str, Any] | None:
        if not ctrl.connected:
            return None
        try:
            state = ctrl.state()
        except Exception:  # noqa: BLE001
            state = {}
        try:
            bat = int(state.get("bat")) if state.get("bat") is not None else int(ctrl.battery().get("battery"))
        except Exception:  # noqa: BLE001
            bat = None
        try:
            altitude = int(state.get("h")) if state.get("h") is not None else int(state.get("tof"))
        except Exception:  # noqa: BLE001
            altitude = None
        try:
            vgx = int(state.get("vgx", 0))
            vgy = int(state.get("vgy", 0))
            vgz = int(state.get("vgz", 0))
            speed = int(round((vgx * vgx + vgy * vgy + vgz * vgz) ** 0.5))
        except Exception:  # noqa: BLE001
            speed = None
        try:
            if state.get("templ") is not None and state.get("temph") is not None:
                temperature = int(round((int(state["templ"]) + int(state["temph"])) / 2))
            else:
                temperature = int(round(float(ctrl.diagnostics().get("temperature_c"))))
        except Exception:  # noqa: BLE001
            temperature = None
        try:
            yaw = int(state.get("yaw")) if state.get("yaw") is not None else None
        except Exception:  # noqa: BLE001
            yaw = None
        return {
            "drone_id": drone_id,
            "battery": bat,
            "altitude": altitude,
            "speed": speed,
            "temperature": temperature,
            "yaw": yaw,
        }

    def _telemetry_loop(self) -> None:
        db = DB()
        LOGGER.info("telemetry worker started interval=%ss", self._telemetry_cfg.get("interval_sec"))
        while not self._telemetry_stop.is_set():
            try:
                with self._lock:
                    items = list(self._controllers.items())
                for drone_id, ctrl in items:
                    row = self._build_telemetry_row(drone_id, ctrl)
                    if row is None:
                        continue
                    try:
                        db.insert_data("drone_telemetry", row)
                    except Exception as e:  # noqa: BLE001
                        LOGGER.warning("telemetry insert failed drone_id=%s err=%s", drone_id, e)
            except Exception as e:  # noqa: BLE001
                LOGGER.exception("telemetry worker loop error: %s", e)
            self._telemetry_stop.wait(float(self._telemetry_cfg.get("interval_sec", 2.0)))
        LOGGER.info("telemetry worker stopped")

    def start_telemetry_worker(self) -> None:
        cfg = self._telemetry_cfg if self._telemetry_cfg else _read_telemetry_config()
        if not cfg.get("enabled"):
            LOGGER.info("telemetry worker disabled by config")
            return
        if self._telemetry_thread is not None and self._telemetry_thread.is_alive():
            return
        self._telemetry_stop.clear()
        self._telemetry_thread = threading.Thread(
            target=self._telemetry_loop, name="telemetry-writer", daemon=True
        )
        self._telemetry_thread.start()

    def stop_telemetry_worker(self) -> None:
        self._telemetry_stop.set()
        if self._telemetry_thread is not None and self._telemetry_thread.is_alive():
            self._telemetry_thread.join(timeout=2.0)

    def list_drones(self) -> list[dict[str, Any]]:
        out: list[dict[str, Any]] = []
        for drone_id, cfg in self._registry.items():
            out.append(
                {
                    "id": drone_id,
                    "name": cfg.get("name"),
                    "host": cfg.get("host"),
                    "model": cfg.get("model"),
                    "is_active": cfg.get("is_active", 1),
                    "connected": self._controllers[drone_id].connected,
                }
            )
        return out

    def get_drone(self, drone_id: str) -> dict[str, Any]:
        self._require_id(drone_id)
        cfg = self._registry[drone_id]
        return {
            "id": drone_id,
            "name": cfg.get("name"),
            "host": cfg.get("host"),
            "model": cfg.get("model"),
            "is_active": cfg.get("is_active", 1),
            "connected": self._controllers[drone_id].connected,
        }

    def stream_on(self, drone_id: str) -> dict[str, Any]:
        LOGGER.info("stream_on drone_id=%s", drone_id)
        return self._ctrl(drone_id).stream_on()

    def stream_off(self, drone_id: str) -> dict[str, Any]:
        LOGGER.info("stream_off drone_id=%s", drone_id)
        return self._ctrl(drone_id).stream_off()

    def takeoff(self, drone_id: str, pause_stream_first: bool = False) -> dict[str, Any]:
        LOGGER.info("takeoff drone_id=%s pause_stream_first=%s", drone_id, pause_stream_first)
        return self._ctrl(drone_id).takeoff(pause_stream_first=pause_stream_first)

    def land(self, drone_id: str) -> dict[str, Any]:
        LOGGER.info("land drone_id=%s", drone_id)
        return self._ctrl(drone_id).land()

    def forward(self, drone_id: str, cm: int) -> dict[str, Any]:
        return self._ctrl(drone_id).move_forward(cm)

    def back(self, drone_id: str, cm: int) -> dict[str, Any]:
        return self._ctrl(drone_id).move_back(cm)

    def left(self, drone_id: str, cm: int) -> dict[str, Any]:
        return self._ctrl(drone_id).move_left(cm)

    def right(self, drone_id: str, cm: int) -> dict[str, Any]:
        return self._ctrl(drone_id).move_right(cm)

    def up(self, drone_id: str, cm: int) -> dict[str, Any]:
        return self._ctrl(drone_id).move_up(cm)

    def down(self, drone_id: str, cm: int) -> dict[str, Any]:
        return self._ctrl(drone_id).move_down(cm)

    def rotate_cw(self, drone_id: str, degree: int) -> dict[str, Any]:
        return self._ctrl(drone_id).rotate_cw(degree)

    def rotate_ccw(self, drone_id: str, degree: int) -> dict[str, Any]:
        return self._ctrl(drone_id).rotate_ccw(degree)

    def hover(self, drone_id: str, sec: int | float) -> dict[str, Any]:
        return self._ctrl(drone_id).hover(sec)

    def deliver(self, drone_id: str) -> dict[str, Any]:
        return self._ctrl(drone_id).deliver()

    def emergency(self, drone_id: str) -> dict[str, Any]:
        LOGGER.warning("emergency drone_id=%s", drone_id)
        return self._ctrl(drone_id).emergency()

    def rc(self, drone_id: str, lr: int, fb: int, ud: int, yaw: int) -> dict[str, Any]:
        return self._ctrl(drone_id).rc(lr, fb, ud, yaw)

    def state(self, drone_id: str) -> dict[str, Any]:
        return self._ctrl(drone_id).state()

    def battery(self, drone_id: str) -> dict[str, Any]:
        return self._ctrl(drone_id).battery()

    def diagnostics(self, drone_id: str) -> dict[str, Any]:
        out = self._ctrl(drone_id).diagnostics()
        out["id"] = drone_id
        out["host"] = self._registry[drone_id].get("host")
        return out

    def ping(self, drone_id: str) -> dict[str, Any]:
        return self._ctrl(drone_id).ping()

    def get_frame_jpeg(self, drone_id: str, quality: int = 80) -> bytes | None:
        return self._ctrl(drone_id).get_frame_jpeg(quality=quality)

    def get_vlm_logs(self, drone_id: str, since_id: int = 0, limit: int = 100) -> dict[str, Any]:
        vlm = get_vlm_processor()
        if vlm is None:
            return {"enabled": False, "items": []}
        return {
            "enabled": True,
            "status": vlm.get_status(),
            "items": vlm.get_logs(since_id=since_id, drone_id=drone_id, limit=limit),
        }

    def mission_start(
        self,
        mission_id: int,
        drone_id: str,
        rpc_caller: Callable[[dict[str, Any]], dict[str, Any]] | None = None,
    ) -> dict[str, Any]:
        """미션 시작: action을 seq/id 오름차순으로 순차 실행."""
        did = str(drone_id).strip()
        if not did:
            raise ValueError("drone_id is required")
        self._require_id(did)
        db = DB()

        safe_did = did.replace("'", "''")
        msql = (
            "SELECT id, drone_id, state, created_at "
            f"FROM missions WHERE id={int(mission_id)} AND drone_id='{safe_did}' LIMIT 1"
        )
        mdf = npty.db.read_sql(msql)
        if len(mdf) == 0:
            raise RuntimeError(f"mission_not_found:{mission_id}:{did}")

        asql = (
            "SELECT id, mission_id, seq, type, value, result, error, executed_at "
            f"FROM mission_actions WHERE mission_id={int(mission_id)} "
            "ORDER BY seq ASC, id ASC"
        )
        adf = npty.db.read_sql(asql)
        actions: list[dict[str, Any]] = []
        for _, row in adf.iterrows():
            actions.append(
                {
                    "id": int(row["id"]),
                    "seq": int(row["seq"]),
                    "type": str(row.get("type") or ""),
                    "value": row.get("value"),
                    "result": row.get("result"),
                    "error": row.get("error"),
                    "executed_at": str(row.get("executed_at")) if row.get("executed_at") is not None else None,
                }
            )

        now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        db.update_data(
            "missions",
            {"state": "preparing", "started_at": now, "fail_reason": None, "ended_at": None},
            {"id": int(mission_id)},
        )

        mission_cfg = _read_mission_config()
        delay_after: dict[str, float] = mission_cfg["delay_after_action"]
        executed: list[dict[str, Any]] = []
        mission_trace: list[dict[str, Any]] = []
        for action_index, action in enumerate(actions):
            aid = int(action["id"])
            atype = str(action.get("type") or "").strip().lower()
            aval = action.get("value")
            action_now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            try:
                method, params = self._to_rpc_action(did, atype, aval)
                attempts = 0
                while True:
                    attempts += 1
                    req_payload = {
                        "jsonrpc": "2.0",
                        "method": method,
                        "params": params,
                        "id": f"mi-{mission_id}-{aid}-{attempts}",
                    }
                    request_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
                    if rpc_caller is None:
                        rpc_resp = {
                            "jsonrpc": "2.0",
                            "result": self._execute_mission_action(did, atype, aval),
                            "id": req_payload["id"],
                        }
                    else:
                        rpc_resp = rpc_caller(req_payload)
                    response_ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

                    mission_trace.append(
                        {
                            "action_id": aid,
                            "seq": int(action["seq"]),
                            "type": atype,
                            "attempt": attempts,
                            "request_ts": request_ts,
                            "response_ts": response_ts,
                            "request": req_payload,
                            "response": rpc_resp,
                        }
                    )

                    err = rpc_resp.get("error") if isinstance(rpc_resp, dict) else None
                    msg = str(err.get("message", "")).lower() if isinstance(err, dict) else ""
                    if (
                        "locked" in msg
                        and mission_cfg["retry_on_lock"]
                        and attempts < int(mission_cfg["lock_retry_max_attempts"])
                    ):
                        time.sleep(float(mission_cfg["lock_retry_interval_sec"]))
                        continue
                    if err:
                        raise RuntimeError(str(err.get("message") or err))
                    break

                db.update_data(
                    "mission_actions",
                    {"result": ("skip" if atype == "deliver" else "ok"), "error": None, "executed_at": action_now},
                    {"id": aid},
                )
                executed.append(
                    {
                        "id": aid,
                        "seq": int(action["seq"]),
                        "type": atype,
                        "result": ("skip" if atype == "deliver" else "ok"),
                        "response": rpc_resp.get("result") if isinstance(rpc_resp, dict) else rpc_resp,
                        "rpc": rpc_resp,
                        "attempts": attempts,
                    }
                )
                pause_sec = float(delay_after.get(atype, delay_after.get("_default", 0.2)))
                if pause_sec > 0.0 and action_index < len(actions) - 1:
                    LOGGER.info(
                        "mission_sleep_after mission_id=%s drone_id=%s after_action=%s sec=%s",
                        mission_id,
                        did,
                        atype,
                        pause_sec,
                    )
                    time.sleep(pause_sec)
            except Exception as e:  # noqa: BLE001
                err = str(e)
                db.update_data(
                    "mission_actions",
                    {"result": "fail", "error": err, "executed_at": action_now},
                    {"id": aid},
                )
                db.update_data(
                    "missions",
                    {"state": "failed", "ended_at": action_now, "fail_reason": err},
                    {"id": int(mission_id)},
                )
                return {
                    "ok": False,
                    "accepted": True,
                    "mission_id": int(mission_id),
                    "drone_id": did,
                    "mission_state": "failed",
                    "action_count": len(actions),
                    "executed_count": len(executed),
                    "failed_action_id": aid,
                    "error": err,
                    "actions": executed,
                    "trace": mission_trace,
                }

        end_now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        db.update_data("missions", {"state": "arrived", "ended_at": end_now, "fail_reason": None}, {"id": int(mission_id)})
        return {
            "ok": True,
            "accepted": True,
            "mission_id": int(mission_id),
            "drone_id": did,
            "mission_state": "arrived",
            "action_count": len(actions),
            "executed_count": len(executed),
            "actions": executed,
            "trace": mission_trace,
            "note": "mission actions executed in seq/id ascending order",
        }

    def _to_rpc_action(self, drone_id: str, action_type: str, value: Any) -> tuple[str, dict[str, Any]]:
        params: dict[str, Any] = {"drone_id": drone_id}
        if action_type == "takeoff":
            pause = False
            if isinstance(value, str):
                pause = value.strip().lower() in ("1", "true", "y", "yes")
            elif isinstance(value, (int, bool)):
                pause = bool(value)
            params["pause_stream_first"] = pause
            return "takeoff", params
        if action_type in ("land", "stream_on", "stream_off", "emergency"):
            return action_type, params
        if action_type in ("forward", "back", "left", "right", "up", "down"):
            if value is None:
                raise ValueError(f"{action_type} action requires value(cm)")
            params["value"] = int(value)
            return action_type, params
        if action_type in ("rotate_cw", "rotate_ccw"):
            if value is None:
                raise ValueError(f"{action_type} action requires value(degree)")
            params["value"] = int(value)
            return action_type, params
        if action_type == "hover":
            if value is None:
                raise ValueError("hover action requires value(sec)")
            params["value"] = int(value)
            return action_type, params
        if action_type == "deliver":
            return action_type, params
        if action_type == "rc":
            if value is None:
                raise ValueError("rc action requires value")
            if isinstance(value, str):
                parts = [p.strip() for p in value.split(",")]
                if len(parts) != 4:
                    raise ValueError("rc value must be 'lr,fb,ud,yaw'")
                params["lr"], params["fb"], params["ud"], params["yaw"] = (
                    int(parts[0]),
                    int(parts[1]),
                    int(parts[2]),
                    int(parts[3]),
                )
            elif isinstance(value, dict):
                params["lr"] = int(value["lr"])
                params["fb"] = int(value["fb"])
                params["ud"] = int(value["ud"])
                params["yaw"] = int(value["yaw"])
            elif isinstance(value, (int, float)):
                params["lr"] = 0
                params["fb"] = int(value)
                params["ud"] = 0
                params["yaw"] = 0
            else:
                raise ValueError("unsupported rc value format")
            return "rc", params
        raise ValueError(f"unsupported_action_type:{action_type}")

    def _execute_mission_action(self, drone_id: str, action_type: str, value: Any) -> dict[str, Any]:
        """mission_actions.type별 실행 매핑."""
        if action_type == "takeoff":
            pause = False
            if isinstance(value, str):
                pause = value.strip().lower() in ("1", "true", "y", "yes")
            elif isinstance(value, (int, bool)):
                pause = bool(value)
            return self.takeoff(drone_id, pause_stream_first=pause)
        if action_type == "land":
            return self.land(drone_id)
        if action_type == "forward":
            return self.forward(drone_id, int(value))
        if action_type == "back":
            return self.back(drone_id, int(value))
        if action_type == "left":
            return self.left(drone_id, int(value))
        if action_type == "right":
            return self.right(drone_id, int(value))
        if action_type == "up":
            return self.up(drone_id, int(value))
        if action_type == "down":
            return self.down(drone_id, int(value))
        if action_type == "rotate_cw":
            return self.rotate_cw(drone_id, int(value))
        if action_type == "rotate_ccw":
            return self.rotate_ccw(drone_id, int(value))
        if action_type == "hover":
            return self.hover(drone_id, int(value))
        if action_type == "deliver":
            return self.deliver(drone_id)
        if action_type == "stream_on":
            return self.stream_on(drone_id)
        if action_type == "stream_off":
            return self.stream_off(drone_id)
        if action_type == "emergency":
            return self.emergency(drone_id)
        if action_type == "rc":
            if value is None:
                raise ValueError("rc action requires value")
            if isinstance(value, str):
                parts = [p.strip() for p in value.split(",")]
                if len(parts) != 4:
                    raise ValueError("rc value must be 'lr,fb,ud,yaw'")
                lr, fb, ud, yaw = (int(parts[0]), int(parts[1]), int(parts[2]), int(parts[3]))
            elif isinstance(value, dict):
                lr = int(value["lr"])
                fb = int(value["fb"])
                ud = int(value["ud"])
                yaw = int(value["yaw"])
            else:
                raise ValueError("unsupported rc value format")
            return self.rc(drone_id, lr, fb, ud, yaw)
        raise ValueError(f"unsupported_action_type:{action_type}")


fleet = DroneFleet()

# 하위 호환
controller = DroneController()
