

from __future__ import annotations

import csv
import logging
import threading
from datetime import datetime
from pathlib import Path
from typing import Any

import cv2
import numpy as np
from npty.util import get_section_config

from modules.survey_recorder import get_survey_manager

LOGGER = logging.getLogger("drone.ctrl")

_processor: "YoloProcessor | None" = None
_processor_lock = threading.Lock()


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
    out["csv_log"] = sec.getboolean("csv_log", fallback=True)
    csv_raw = sec.get("csv_path", fallback="logs/detection_zone_log.csv").strip()
    csv_path = Path(csv_raw)
    if not csv_path.is_absolute():
        csv_path = (control_dir / csv_path).resolve()
    out["csv_path"] = csv_path
    out["detect_interval_sec"] = max(0.1, sec.getfloat("detect_interval_sec", fallback=0.4))
    return out


def _zone_for_center(center_x: int, frame_width: int) -> str:
    if center_x < frame_width / 3:
        return "A1"
    if center_x < frame_width * 2 / 3:
        return "B1"
    return "C1"


class YoloProcessor:
    def __init__(self, cfg: dict[str, Any]) -> None:
        self.conf = float(cfg.get("conf", 0.75))
        self.csv_log = bool(cfg.get("csv_log", True))
        self.csv_path: Path = cfg["csv_path"]
        self.detect_interval_sec = float(cfg.get("detect_interval_sec", 0.4))
        model_path: Path = cfg["model_path"]
        if not model_path.is_file():
            raise FileNotFoundError(f"YOLO model not found: {model_path}")

        from ultralytics import YOLO

        self.model = YOLO(str(model_path))
        self._infer_lock = threading.Lock()
        self._csv_lock = threading.Lock()
        self._cache_lock = threading.Lock()
        self._cached_boxes: list[tuple[int, int, int, int, str, float]] = []
        if self.csv_log:
            self._init_csv()
        LOGGER.info("YOLO loaded model=%s conf=%s", model_path, self.conf)

    def _init_csv(self) -> None:
        self.csv_path.parent.mkdir(parents=True, exist_ok=True)
        if self.csv_path.exists() and self.csv_path.stat().st_size > 0:
            return
        with self.csv_path.open("w", newline="", encoding="utf-8-sig") as f:
            csv.writer(f).writerow(["time", "zone", "class", "confidence", "x1", "y1", "x2", "y2"])

    def _append_csv(self, rows: list[list[Any]]) -> None:
        if not rows:
            return
        with self._csv_lock:
            with self.csv_path.open("a", newline="", encoding="utf-8-sig") as f:
                csv.writer(f).writerows(rows)

    def _store_results(self, frame_bgr: np.ndarray, results: Any, source: str = "") -> None:
        boxes = results[0].boxes
        cached: list[tuple[int, int, int, int, str, float]] = []
        rows: list[list[Any]] = []
        survey_items: list[tuple[str, float, str | None, float, float]] = []
        if boxes is not None:
            frame_width = frame_bgr.shape[1]
            now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
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
                if self.csv_log:
                    rows.append([now, zone, cls_name, round(conf, 3), x1, y1, x2, y2])
        with self._cache_lock:
            self._cached_boxes = cached
        self._append_csv(rows)
        if survey_items:
            try:
                # source 는 보통 host IP. drone_id 가 있으면 그걸 쓰고, 없으면 source 키로 기록.
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
        """백그라운드 추론 — 탐지 박스 캐시와 CSV만 갱신."""
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
