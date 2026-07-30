from __future__ import annotations

import json
import logging
import threading
from datetime import datetime, timezone, timedelta
from pathlib import Path
from typing import Any

import cv2
import numpy as np
from npty.util import get_section_config

from modules.survey_recorder import get_survey_manager

LOGGER = logging.getLogger("drone.ctrl")

_processor: "YoloProcessor | None" = None
_processor_lock = threading.Lock()

_KST = timezone(timedelta(hours=9))
_MAX_EVENTS = 5000


def _read_yolo_config() -> dict[str, Any]:
    out: dict[str, Any] = {"enabled": False}
    try:
        sec = get_section_config("yolo")
    except Exception:
        return out

    control_dir = Path(__file__).resolve().parents[1]
    raw_path = sec.get("model_path", fallback="../test/test/best.pt").strip()
    model_path = Path(raw_path)
    if not model_path.is_absolute():
        model_path = (control_dir / model_path).resolve()

    out["enabled"] = sec.getboolean("enabled", fallback=False)
    out["model_path"] = model_path
    out["conf"] = sec.getfloat("conf", fallback=0.75)

    has_json_log = hasattr(sec, "has_option") and sec.has_option("json_log")
    if has_json_log:
        out["json_log"] = sec.getboolean("json_log", fallback=True)
    else:
        out["json_log"] = sec.getboolean("csv_log", fallback=True)

    json_raw = ""
    if hasattr(sec, "has_option") and sec.has_option("json_path"):
        json_raw = sec.get("json_path", fallback="").strip()
    if not json_raw and hasattr(sec, "has_option") and sec.has_option("csv_path"):
        csv_raw = sec.get("csv_path", fallback="").strip()
        if csv_raw:
            json_raw = str(Path(csv_raw).with_suffix(".json"))
    if not json_raw:
        json_raw = "../web/app/Data/inspection_detections_raw.json"

    json_path = Path(json_raw)
    if not json_path.is_absolute():
        json_path = (control_dir / json_path).resolve()
    out["json_path"] = json_path
    out["detect_interval_sec"] = max(0.1, sec.getfloat("detect_interval_sec", fallback=0.4))
    out["max_events"] = max(100, sec.getint("max_events", fallback=_MAX_EVENTS))
    return out


def _zone_for_center(center_x: int, frame_width: int) -> str:
    if center_x < frame_width / 3:
        return "A1"
    if center_x < frame_width * 2 / 3:
        return "B1"
    return "C1"


def _now_iso() -> str:
    return datetime.now(_KST).isoformat(timespec="seconds")


def _normalize_trash_type(cls_name: str) -> str:
    key = (cls_name or "").strip().lower()
    mapping = {
        "trash": "해양쓰레기",
        "bottle": "플라스틱병",
        "pet": "플라스틱병",
        "can": "캔",
        "bag": "비닐봉지",
        "styrofoam": "스티로폼",
    }
    return mapping.get(key, cls_name.strip() or "미분류")


class YoloProcessor:
    def __init__(self, cfg: dict[str, Any]) -> None:
        self.conf = float(cfg.get("conf", 0.75))
        self.json_log = bool(cfg.get("json_log", True))
        self.json_path: Path = cfg["json_path"]
        self.max_events = int(cfg.get("max_events", _MAX_EVENTS))
        self.detect_interval_sec = float(cfg.get("detect_interval_sec", 0.4))
        model_path: Path = cfg["model_path"]
        if not model_path.is_file():
            raise FileNotFoundError(f"YOLO model not found: {model_path}")

        from ultralytics import YOLO

        self.model = YOLO(str(model_path))
        self._infer_lock = threading.Lock()
        self._json_lock = threading.Lock()
        self._cache_lock = threading.Lock()
        self._cached_boxes: list[tuple[int, int, int, int, str, float]] = []
        self._det_seq = 0
        if self.json_log:
            self._init_json()
        LOGGER.info(
            "YOLO loaded model=%s conf=%s json_log=%s path=%s",
            model_path,
            self.conf,
            self.json_log,
            self.json_path,
        )

    def _empty_payload(self) -> dict[str, Any]:
        now = datetime.now(_KST)
        return {
            "schema_version": "1.0",
            "data_status": "live",
            "mission": {
                "mission_id": f"MISSION-{now.strftime('%Y%m%d-%H%M%S')}",
                "inspection_date": now.strftime("%Y-%m-%d"),
                "inspection_area": "현장 탐지",
                "flight_id": "LIVE",
                "survey_method": "드론 영상 기반 객체 탐지",
            },
            "detections": [],
        }

    def _init_json(self) -> None:
        self.json_path.parent.mkdir(parents=True, exist_ok=True)
        if self.json_path.exists() and self.json_path.stat().st_size > 0:
            return
        self.json_path.write_text(
            json.dumps(self._empty_payload(), ensure_ascii=False, indent=2),
            encoding="utf-8",
        )

    def _load_payload(self) -> dict[str, Any]:
        try:
            data = json.loads(self.json_path.read_text(encoding="utf-8"))
            if isinstance(data, dict) and isinstance(data.get("detections"), list):
                return data
            # 구버전 events 스키마 → detections 로 승격
            if isinstance(data, dict) and isinstance(data.get("events"), list):
                converted = self._empty_payload()
                for ev in data["events"]:
                    if not isinstance(ev, dict):
                        continue
                    converted["detections"].append(
                        {
                            "detection_id": f"DET-MIG-{len(converted['detections'])+1:04d}",
                            "mission_id": converted["mission"]["mission_id"],
                            "zone_id": ev.get("zone") or "B1",
                            "trash_type": _normalize_trash_type(str(ev.get("class") or "trash")),
                            "trash_count": 1,
                            "status": "uncollected",
                            "detected_at": ev.get("time") or _now_iso(),
                            "status_updated_at": ev.get("time") or _now_iso(),
                            "collected_at": None,
                            "average_confidence": float(ev.get("confidence") or 0),
                        }
                    )
                return converted
        except Exception as e:  # noqa: BLE001
            LOGGER.warning("detection json load failed: %s", e)
        return self._empty_payload()

    def _next_detection_id(self, mission_id: str) -> str:
        self._det_seq += 1
        stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
        return f"DET-{stamp}-{self._det_seq:04d}"

    def _upsert_detections(
        self,
        items: list[tuple[str, str, float]],
        *,
        mission_id: str,
    ) -> None:
        """(zone_id, trash_type, conf) 목록을 zone+type 기준으로 병합 기록."""
        if not items:
            return
        with self._json_lock:
            data = self._load_payload()
            if not isinstance(data.get("mission"), dict) or not data["mission"]:
                data["mission"] = self._empty_payload()["mission"]
            if not data["mission"].get("mission_id"):
                data["mission"]["mission_id"] = mission_id

            detections: list[dict[str, Any]] = list(data.get("detections") or [])
            index: dict[tuple[str, str], int] = {}
            for i, d in enumerate(detections):
                if not isinstance(d, dict):
                    continue
                key = (str(d.get("zone_id") or ""), str(d.get("trash_type") or ""))
                index[key] = i

            now = _now_iso()
            for zone_id, trash_type, conf in items:
                key = (zone_id, trash_type)
                if key in index:
                    d = detections[index[key]]
                    prev_n = max(1, int(d.get("trash_count") or 1))
                    prev_c = float(d.get("average_confidence") or conf)
                    new_n = prev_n + 1
                    d["trash_count"] = new_n
                    d["average_confidence"] = round((prev_c * prev_n + conf) / new_n, 3)
                    d["status_updated_at"] = now
                    if d.get("status") in (None, ""):
                        d["status"] = "uncollected"
                else:
                    detections.append(
                        {
                            "detection_id": self._next_detection_id(mission_id),
                            "mission_id": data["mission"].get("mission_id") or mission_id,
                            "zone_id": zone_id,
                            "trash_type": trash_type,
                            "trash_count": 1,
                            "status": "uncollected",
                            "detected_at": now,
                            "status_updated_at": now,
                            "collected_at": None,
                            "average_confidence": round(conf, 3),
                        }
                    )
                    index[key] = len(detections) - 1

            if len(detections) > self.max_events:
                detections = detections[-self.max_events :]

            data["schema_version"] = "1.0"
            data["data_status"] = "live"
            data["detections"] = detections

            tmp = self.json_path.with_suffix(".json.tmp")
            tmp.write_text(
                json.dumps(data, ensure_ascii=False, indent=2),
                encoding="utf-8",
            )
            tmp.replace(self.json_path)

    def _store_results(self, frame_bgr: np.ndarray, results: Any, source: str = "") -> None:
        boxes = results[0].boxes
        cached: list[tuple[int, int, int, int, str, float]] = []
        upsert_items: list[tuple[str, str, float]] = []
        survey_items: list[tuple[str, float, str | None, float, float]] = []
        if boxes is not None:
            frame_width = frame_bgr.shape[1]
            for box in boxes:
                cls_id = int(box.cls[0])
                cls_name = self.model.names[cls_id]
                conf = float(box.conf[0])
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                cached.append((x1, y1, x2, y2, cls_name, conf))
                center_x = (x1 + x2) / 2.0
                center_y = (y1 + y2) / 2.0
                zone = _zone_for_center(int(center_x), frame_width)
                survey_items.append((cls_name, conf, zone, center_x, center_y))
                if self.json_log:
                    upsert_items.append((zone, _normalize_trash_type(cls_name), conf))
        with self._cache_lock:
            self._cached_boxes = cached
        if upsert_items:
            mission_id = f"MISSION-LIVE-{(source or 'drone').replace(' ', '_')}"
            self._upsert_detections(upsert_items, mission_id=mission_id)
        if survey_items:
            try:
                drone_key = source or "default"
                get_survey_manager().record(drone_key, survey_items)
            except Exception as e:  # noqa: BLE001
                LOGGER.debug("survey record skipped: %s", e)

    def apply_overlay(self, frame_bgr: np.ndarray) -> np.ndarray:
        """마지막 탐지 결과를 현재 프레임에 빠르게 그린다 (추론 없음)."""
        with self._cache_lock:
            boxes = list(self._cached_boxes)
        if not boxes:
            return frame_bgr
        out = frame_bgr.copy()
        for x1, y1, x2, y2, cls_name, conf in boxes:
            cv2.rectangle(out, (x1, y1), (x2, y2), (0, 255, 0), 2)
            label = f"{cls_name} {conf:.2f}"
            (tw, th), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.5, 1)
            cv2.rectangle(out, (x1, max(0, y1 - th - 6)), (x1 + tw + 4, y1), (0, 255, 0), -1)
            cv2.putText(
                out,
                label,
                (x1 + 2, max(th + 2, y1 - 4)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.5,
                (0, 0, 0),
                1,
                cv2.LINE_AA,
            )
        return out

    def detect_and_cache(self, frame_bgr: np.ndarray, source: str = "") -> None:
        """백그라운드 추론 — 탐지 박스 캐시와 JSON 로그만 갱신."""
        with self._infer_lock:
            results = self.model(frame_bgr, conf=self.conf, verbose=False)
        self._store_results(frame_bgr, results, source=source)

    def annotate(self, frame_bgr: np.ndarray, source: str = "") -> np.ndarray:
        with self._infer_lock:
            results = self.model(frame_bgr, conf=self.conf, verbose=False)
        annotated = results[0].plot()
        self._store_results(frame_bgr, results, source=source)
        return annotated


def get_yolo_processor() -> YoloProcessor | None:

    global _processor
    cfg = _read_yolo_config()
    if not cfg.get("enabled"):
        return None
    with _processor_lock:
        if _processor is None:
            try:
                _processor = YoloProcessor(cfg)
            except Exception as e:  # noqa: BLE001
                LOGGER.error("YOLO init failed: %s", e)
                return None
        return _processor
