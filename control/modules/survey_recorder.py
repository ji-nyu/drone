"""드론 YOLO 탐지 결과를 test3.py 보고서 입력 JSON으로 기록한다.

스키마 (test3.py TEST_CASES 와 동일):
{
  "mission_id", "inspection_date", "inspection_area", "flight_id",
  "survey_method", "survey_duration_minutes", "total_detected_count",
  "detections": [{"trash_type", "count", "zone", "average_confidence"}],
  "highest_density_zone", "collection_priority",
  "recommended_actions", "limitations"
}
"""

from __future__ import annotations

import json
import logging
import math
import threading
import time
from collections import defaultdict
from datetime import datetime
from pathlib import Path
from typing import Any

from npty.util import get_section_config

LOGGER = logging.getLogger("drone.ctrl")

_manager: "SurveyManager | None" = None
_manager_lock = threading.Lock()


def _read_survey_config() -> dict[str, Any]:
    control_dir = Path(__file__).resolve().parents[1]
    out: dict[str, Any] = {
        "enabled": True,
        "auto_start_on_stream": True,
        "output_dir": control_dir / "logs" / "surveys",
        "inspection_area": "조사 구역",
        "survey_method": "드론 영상 기반 객체 탐지",
        "track_match_px": 80.0,
        "track_ttl_sec": 2.0,
    }
    try:
        sec = get_section_config("survey")
    except Exception:
        return out

    out["enabled"] = sec.getboolean("enabled", fallback=True)
    out["auto_start_on_stream"] = sec.getboolean("auto_start_on_stream", fallback=True)
    raw_dir = sec.get("output_dir", fallback="logs/surveys").strip()
    out_dir = Path(raw_dir)
    if not out_dir.is_absolute():
        out_dir = (control_dir / out_dir).resolve()
    out["output_dir"] = out_dir
    out["inspection_area"] = sec.get("inspection_area", fallback="조사 구역").strip()
    out["survey_method"] = sec.get(
        "survey_method", fallback="드론 영상 기반 객체 탐지"
    ).strip()
    out["track_match_px"] = max(10.0, sec.getfloat("track_match_px", fallback=80.0))
    out["track_ttl_sec"] = max(0.5, sec.getfloat("track_ttl_sec", fallback=2.0))
    return out


def zone_to_report_format(zone: str | None) -> str | None:
    """A1/B1/C1 → A-01/B-01/C-01 (보고서 표기)."""
    if not zone:
        return None
    z = zone.strip().upper().replace("-", "")
    if len(z) >= 2 and z[0].isalpha() and z[1:].isdigit():
        return f"{z[0]}-{int(z[1:]):02d}"
    return zone


class _Track:
    __slots__ = ("track_id", "cls_name", "zone", "cx", "cy", "conf_sum", "conf_n", "last_ts")

    def __init__(
        self,
        track_id: int,
        cls_name: str,
        zone: str | None,
        cx: float,
        cy: float,
        conf: float,
        ts: float,
    ) -> None:
        self.track_id = track_id
        self.cls_name = cls_name
        self.zone = zone
        self.cx = cx
        self.cy = cy
        self.conf_sum = conf
        self.conf_n = 1
        self.last_ts = ts

    @property
    def avg_conf(self) -> float:
        return self.conf_sum / max(1, self.conf_n)


class SurveySession:
    """한 번의 비행(스트림) 동안의 탐지 세션."""

    def __init__(
        self,
        drone_id: str,
        *,
        inspection_area: str,
        survey_method: str,
        output_dir: Path,
        track_match_px: float,
        track_ttl_sec: float,
        mission_id: str | None = None,
        flight_id: str | None = None,
    ) -> None:
        now = datetime.now()
        stamp = now.strftime("%Y%m%d-%H%M%S")
        safe_drone = "".join(c if c.isalnum() or c in "-_" else "_" for c in drone_id)
        self.drone_id = drone_id
        self.mission_id = mission_id or f"MISSION-{stamp}-{safe_drone}"
        self.flight_id = flight_id or f"{safe_drone}-{now.strftime('%m%d-%H%M')}"
        self.inspection_date = now.strftime("%Y-%m-%d")
        self.inspection_area = inspection_area
        self.survey_method = survey_method
        self.output_dir = output_dir
        self.track_match_px = track_match_px
        self.track_ttl_sec = track_ttl_sec
        self.started_at = time.monotonic()
        self.started_wall = now
        self._lock = threading.Lock()
        self._tracks: list[_Track] = []
        self._next_track_id = 1
        self._finished = False
        self.output_path: Path | None = None

    def record_detections(
        self,
        items: list[tuple[str, float, str | None, float, float]],
    ) -> None:
        """(cls_name, conf, zone, cx, cy) 목록을 추적기에 반영."""
        if not items:
            return
        now = time.monotonic()
        with self._lock:
            if self._finished:
                return
            self._expire_tracks(now)
            for cls_name, conf, zone, cx, cy in items:
                zone_fmt = zone_to_report_format(zone)
                track = self._match_track(cls_name, cx, cy, now)
                if track is None:
                    self._tracks.append(
                        _Track(self._next_track_id, cls_name, zone_fmt, cx, cy, conf, now)
                    )
                    self._next_track_id += 1
                else:
                    track.cx = cx
                    track.cy = cy
                    track.conf_sum += conf
                    track.conf_n += 1
                    track.last_ts = now
                    if zone_fmt and not track.zone:
                        track.zone = zone_fmt

    def _expire_tracks(self, now: float) -> None:
        alive: list[_Track] = []
        for t in self._tracks:
            if now - t.last_ts <= self.track_ttl_sec:
                alive.append(t)
            # expired tracks are kept in a closed list via snapshot at finish —
            # we move finished tracks to _closed below
        # Keep expired tracks for counting: stash on session
        if not hasattr(self, "_closed_tracks"):
            self._closed_tracks: list[_Track] = []
        for t in self._tracks:
            if now - t.last_ts > self.track_ttl_sec:
                self._closed_tracks.append(t)
        self._tracks = alive

    def _match_track(
        self, cls_name: str, cx: float, cy: float, now: float
    ) -> _Track | None:
        best: _Track | None = None
        best_dist = self.track_match_px
        for t in self._tracks:
            if t.cls_name != cls_name:
                continue
            dist = math.hypot(t.cx - cx, t.cy - cy)
            if dist <= best_dist:
                best_dist = dist
                best = t
        return best

    def _all_tracks(self) -> list[_Track]:
        closed = getattr(self, "_closed_tracks", [])
        return list(closed) + list(self._tracks)

    def build_report_json(self) -> dict[str, Any]:
        with self._lock:
            now = time.monotonic()
            self._expire_tracks(now)
            tracks = self._all_tracks()
            elapsed_sec = max(0.0, now - self.started_at)
            # 보고서용 분 단위 — 30초 미만도 최소 1분으로 표기
            duration_min = max(1, int(round(elapsed_sec / 60.0))) if elapsed_sec > 0 else 1

            # (trash_type, zone) 집계
            buckets: dict[tuple[str, str | None], list[_Track]] = defaultdict(list)
            for t in tracks:
                buckets[(t.cls_name, t.zone)].append(t)

            detections: list[dict[str, Any]] = []
            zone_totals: dict[str, int] = defaultdict(int)
            for (cls_name, zone), group in sorted(
                buckets.items(), key=lambda kv: (-len(kv[1]), kv[0][0], kv[0][1] or "")
            ):
                count = len(group)
                avg_conf = sum(g.avg_conf for g in group) / count
                detections.append(
                    {
                        "trash_type": cls_name,
                        "count": count,
                        "zone": zone,
                        "average_confidence": round(avg_conf, 3),
                    }
                )
                if zone:
                    zone_totals[zone] += count

            total = sum(int(d["count"]) for d in detections)
            highest = None
            if zone_totals:
                highest = max(zone_totals.items(), key=lambda kv: kv[1])[0]

            # 탐지 수치만 기록. 우선순위·권고·한계는 test3.py LLM이 작성한다.
            return {
                "mission_id": self.mission_id,
                "inspection_date": self.inspection_date,
                "inspection_area": self.inspection_area,
                "flight_id": self.flight_id,
                "survey_method": self.survey_method,
                "survey_duration_minutes": duration_min,
                "total_detected_count": total,
                "detections": detections,
                "highest_density_zone": highest,
                "collection_priority": None,
                "recommended_actions": [],
                "limitations": [],
                "drone_id": self.drone_id,
                "recorded_at": datetime.now().isoformat(timespec="seconds"),
            }

    def finish(self) -> Path:
        with self._lock:
            if self._finished and self.output_path is not None:
                return self.output_path
            self._finished = True

        data = self.build_report_json()
        self.output_dir.mkdir(parents=True, exist_ok=True)
        path = self.output_dir / f"{data['mission_id']}.json"
        path.write_text(
            json.dumps(data, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        # 최신 조사 파일 포인터 (test3.py --latest 용)
        latest = self.output_dir / "latest.json"
        latest.write_text(
            json.dumps(data, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
        with self._lock:
            self.output_path = path
        LOGGER.info(
            "survey saved path=%s total=%s zones=%s",
            path,
            data.get("total_detected_count"),
            data.get("highest_density_zone"),
        )
        return path


class SurveyManager:
    def __init__(self, cfg: dict[str, Any] | None = None) -> None:
        self.cfg = cfg or _read_survey_config()
        self._lock = threading.Lock()
        self._sessions: dict[str, SurveySession] = {}

    @property
    def enabled(self) -> bool:
        return bool(self.cfg.get("enabled", True))

    @property
    def auto_start_on_stream(self) -> bool:
        return bool(self.cfg.get("auto_start_on_stream", True))

    def start(
        self,
        drone_id: str,
        *,
        inspection_area: str | None = None,
        mission_id: str | None = None,
        flight_id: str | None = None,
    ) -> SurveySession | None:
        if not self.enabled:
            return None
        with self._lock:
            existing = self._sessions.get(drone_id)
            if existing is not None and not existing._finished:
                return existing
            session = SurveySession(
                drone_id,
                inspection_area=inspection_area or str(self.cfg["inspection_area"]),
                survey_method=str(self.cfg["survey_method"]),
                output_dir=Path(self.cfg["output_dir"]),
                track_match_px=float(self.cfg["track_match_px"]),
                track_ttl_sec=float(self.cfg["track_ttl_sec"]),
                mission_id=mission_id,
                flight_id=flight_id,
            )
            self._sessions[drone_id] = session
            LOGGER.info(
                "survey started drone_id=%s mission_id=%s",
                drone_id,
                session.mission_id,
            )
            return session

    def record(
        self,
        drone_id: str,
        items: list[tuple[str, float, str | None, float, float]],
    ) -> None:
        if not self.enabled or not items:
            return
        with self._lock:
            session = self._sessions.get(drone_id)
        if session is None:
            return
        session.record_detections(items)

    def finish(self, drone_id: str) -> Path | None:
        with self._lock:
            session = self._sessions.get(drone_id)
        if session is None:
            return None
        try:
            return session.finish()
        except Exception as e:  # noqa: BLE001
            LOGGER.warning("survey finish failed drone_id=%s: %s", drone_id, e)
            return None

    def get_session(self, drone_id: str) -> SurveySession | None:
        with self._lock:
            return self._sessions.get(drone_id)


def get_survey_manager() -> SurveyManager:
    global _manager
    with _manager_lock:
        if _manager is None:
            _manager = SurveyManager(_read_survey_config())
        return _manager
