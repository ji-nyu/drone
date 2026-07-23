"""JSON-RPC + REST 드론 제어/스트림 API."""

from __future__ import annotations

import asyncio
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import logging
from logging.handlers import RotatingFileHandler
from typing import Any

from fastapi import Depends, FastAPI, Query
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse, Response, StreamingResponse
from fastapi.staticfiles import StaticFiles

from api.deps import verify_authorization, verify_authorization_flexible
from api.rpc import handle_request
from api.schemas import (
    ApiOkResponse,
    JsonRpcRequest,
    JsonRpcResponse,
    MissionStartRequest,
    MissionStartResponse,
    RcRequest,
    RcResponse,
    TakeoffRequest,
)
from modules.ctrl import fleet

app = FastAPI(title="Tello Multi-Drone API", version="0.3.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["X-Frame-Seq"],
)

_executor = ThreadPoolExecutor(max_workers=6, thread_name_prefix="tello")


def _setup_api_logger() -> logging.Logger:
    log_dir = Path(__file__).resolve().parent.parent / "logs"
    log_dir.mkdir(parents=True, exist_ok=True)
    logger = logging.getLogger("drone.api")
    if logger.handlers:
        return logger
    logger.setLevel(logging.INFO)
    fh = RotatingFileHandler(log_dir / "api.log", maxBytes=1_000_000, backupCount=5, encoding="utf-8")
    fh.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(name)s - %(message)s"))
    logger.addHandler(fh)
    return logger


API_LOGGER = _setup_api_logger()


def _setup_test_web_logger() -> logging.Logger:
    log_dir = Path(__file__).resolve().parent.parent / "logs"
    log_dir.mkdir(parents=True, exist_ok=True)
    logger = logging.getLogger("drone.test_web")
    if logger.handlers:
        return logger
    logger.setLevel(logging.INFO)
    fh = RotatingFileHandler(log_dir / "test-web-api.log", maxBytes=1_000_000, backupCount=5, encoding="utf-8")
    fh.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(name)s - %(message)s"))
    logger.addHandler(fh)
    return logger


TEST_WEB_LOGGER = _setup_test_web_logger()


def _safe_jpeg(drone_id: str) -> bytes | None:
    try:
        jpeg, _ = fleet.get_frame_snapshot(drone_id, quality=80)
        return jpeg
    except RuntimeError:
        return None


def _safe_jpeg_with_seq(drone_id: str) -> tuple[bytes | None, int]:
    try:
        return fleet.get_frame_snapshot(drone_id, quality=80)
    except RuntimeError:
        return None, 0


async def _run_blocking(func):
    return await asyncio.get_running_loop().run_in_executor(_executor, func)


@app.on_event("startup")
async def startup_load_and_connect() -> None:
    """서버 시작 시 DB에서 드론 목록을 읽고 자동 연결."""
    try:
        load = fleet.load_from_db()
        conn = fleet.connect_all(wait_for_state=True)
        API_LOGGER.info("startup load=%s connect=%s", load, conn)
    except Exception as e:  # noqa: BLE001
        # config.ini 미설정/DB 접속 실패 시에도 서버는 기동되게 유지한다.
        API_LOGGER.exception("startup auto-connect failed: %s", e)
        print(f"[startup] DB auto-connect skipped: {e}")


@app.on_event("shutdown")
async def shutdown_cleanup() -> None:
    """서버 종료 시 백그라운드 저장 스레드 정리."""
    try:
        fleet.stop_telemetry_worker()
    except Exception as e:  # noqa: BLE001
        API_LOGGER.warning("shutdown telemetry stop failed: %s", e)


@app.get("/", summary="API 엔드포인트 목록")
async def root() -> dict:
    return {
        "rpc": "POST /rpc",
        "drones": "GET /drones",
        "mission_start": "POST /missions/start",
        "drone_rest": "POST/GET /drones/{drone_id}/*",
        "compat": "POST/GET /drone/*, /snapshot.jpg, /stream.mjpeg",
        "docs": "GET /docs",
    }


@app.get("/health", summary="서버 헬스체크")
async def health() -> dict:
    return {"status": "ok"}


@app.post("/test-web/log", dependencies=[Depends(verify_authorization)], include_in_schema=False)
async def test_web_log(body: dict[str, Any]) -> dict:
    """test-web 페이지의 요청/응답 로그를 파일로 저장."""
    TEST_WEB_LOGGER.info(
        "label=%s request_ts=%s response_ts=%s\ncurl=%s\nresponse_status=%s\nresponse_text=%s",
        body.get("label"),
        body.get("request_ts"),
        body.get("response_ts"),
        body.get("curl"),
        body.get("status"),
        body.get("response"),
    )
    return {"ok": True}


@app.get("/drones", dependencies=[Depends(verify_authorization)], summary="등록 드론 목록")
async def list_drones() -> dict:
    out = {"items": fleet.list_drones(), "default_drone_id": fleet.default_drone_id}
    API_LOGGER.info("list_drones count=%s default=%s", len(out["items"]), out["default_drone_id"])
    return out


@app.get("/drones/{drone_id}", dependencies=[Depends(verify_authorization)], summary="드론 메타 조회")
async def get_drone(drone_id: str) -> dict:
    return fleet.get_drone(drone_id)


@app.post(
    "/missions/start",
    dependencies=[Depends(verify_authorization)],
    response_model=MissionStartResponse,
    summary="미션 시작 요청 접수(1단계)",
    description=(
        "mission_id + drone_id를 받아 미션/액션을 검증하고 접수한다. "
        "실제 action 실행/lock 재시도 엔진은 다음 단계에서 추가 예정."
    ),
)
async def mission_start(body: MissionStartRequest) -> dict:
    return await _run_blocking(lambda: fleet.mission_start(body.mission_id, body.drone_id, rpc_caller=handle_request))


@app.post(
    "/rpc",
    dependencies=[Depends(verify_authorization)],
    response_model=JsonRpcResponse,
    summary="드론 제어 JSON-RPC",
    description=(
        "JSON-RPC 2.0 단일 엔드포인트.\n\n"
        "- `params.drone_id`로 대상 드론 지정\n"
        "- 생략 시 기본 드론(default_drone_id)\n"
        "- 서버 시작 시 자동 connect 완료 상태를 전제로 동작"
    ),
)
async def jsonrpc(body: JsonRpcRequest) -> JSONResponse:
    payload = body.model_dump()
    API_LOGGER.info("rpc method=%s params=%s", payload.get("method"), payload.get("params"))
    result = await _run_blocking(lambda: handle_request(payload))
    return JSONResponse(result)


# ===== 멀티 드론 REST (권장) =====
@app.post("/drones/{drone_id}/stream-on", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, summary="영상 스트림 켜기")
async def drone_stream_on_by_id(drone_id: str) -> dict:
    return await _run_blocking(lambda: fleet.stream_on(drone_id))


@app.post("/drones/{drone_id}/stream-off", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, summary="영상 스트림 끄기")
async def drone_stream_off_by_id(drone_id: str) -> dict:
    return await _run_blocking(lambda: fleet.stream_off(drone_id))


@app.post("/drones/{drone_id}/takeoff", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, summary="이륙")
async def drone_takeoff_by_id(drone_id: str, body: TakeoffRequest) -> dict:
    return await _run_blocking(lambda: fleet.takeoff(drone_id, pause_stream_first=body.pause_stream_first))


@app.post("/drones/{drone_id}/land", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, summary="착륙")
async def drone_land_by_id(drone_id: str) -> dict:
    return await _run_blocking(lambda: fleet.land(drone_id))


@app.post("/drones/{drone_id}/emergency", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, summary="비상 정지")
async def drone_emergency_by_id(drone_id: str) -> dict:
    return await _run_blocking(lambda: fleet.emergency(drone_id))


@app.post("/drones/{drone_id}/rc", dependencies=[Depends(verify_authorization)], response_model=RcResponse, summary="실시간 조종값 전송")
async def drone_rc_by_id(drone_id: str, body: RcRequest) -> dict:
    return await _run_blocking(lambda: fleet.rc(drone_id, body.lr, body.fb, body.ud, body.yaw))


@app.get("/drones/{drone_id}/state", dependencies=[Depends(verify_authorization_flexible)], summary="드론 상태 조회")
async def drone_state_by_id(drone_id: str) -> dict:
    return await _run_blocking(lambda: fleet.state(drone_id))


@app.get("/drones/{drone_id}/battery", dependencies=[Depends(verify_authorization_flexible)], summary="배터리 조회")
async def drone_battery_by_id(drone_id: str) -> dict:
    return await _run_blocking(lambda: fleet.battery(drone_id))


@app.get("/drones/{drone_id}/diagnostics", dependencies=[Depends(verify_authorization)], summary="진단 정보 조회")
async def drone_diagnostics_by_id(drone_id: str) -> dict:
    return await _run_blocking(lambda: fleet.diagnostics(drone_id))


@app.get("/drones/{drone_id}/vlm/logs", dependencies=[Depends(verify_authorization_flexible)], summary="VLM 분석 로그")
async def vlm_logs_by_id(
    drone_id: str,
    since: int = Query(default=0, ge=0),
    limit: int = Query(default=100, ge=1, le=500),
) -> dict:
    return await _run_blocking(lambda: fleet.get_vlm_logs(drone_id, since_id=since, limit=limit))


@app.get("/drones/{drone_id}/snapshot.jpg", dependencies=[Depends(verify_authorization_flexible)], summary="현재 프레임 JPEG 1장")
async def snapshot_jpg_by_id(drone_id: str) -> Response:
    jpeg, seq = await asyncio.get_running_loop().run_in_executor(
        _executor, lambda: _safe_jpeg_with_seq(drone_id)
    )
    if not jpeg:
        return Response(status_code=503, content=b"no frame", media_type="text/plain")
    return Response(
        content=jpeg,
        media_type="image/jpeg",
        headers={
            "Cache-Control": "no-store, no-cache, must-revalidate, max-age=0",
            "Pragma": "no-cache",
            "Expires": "0",
            "X-Frame-Seq": str(seq),
        },
    )


@app.get("/drones/{drone_id}/stream.mjpeg", dependencies=[Depends(verify_authorization_flexible)], summary="MJPEG 실시간 스트림")
async def stream_mjpeg_by_id(drone_id: str) -> StreamingResponse:
    async def gen():
        while True:
            jpeg = await asyncio.get_running_loop().run_in_executor(_executor, lambda: _safe_jpeg(drone_id))
            if jpeg:
                yield b"--frame\r\n" b"Content-Type: image/jpeg\r\n\r\n" + jpeg + b"\r\n"
                # ~30fps 상한: 동일 프레임 재전송으로 CPU가 100% 점유되는 것을 막는다.
                await asyncio.sleep(1 / 30)
            else:
                await asyncio.sleep(0.05)

    return StreamingResponse(gen(), media_type="multipart/x-mixed-replace; boundary=frame")


# ===== 기존 단일 드론 경로 호환 (connect/disconnect 없음) =====
@app.post("/drone/stream-on", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, deprecated=True)
async def compat_stream_on() -> dict:
    return fleet.stream_on(fleet.default_drone_id)


@app.post("/drone/stream-off", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, deprecated=True)
async def compat_stream_off() -> dict:
    return fleet.stream_off(fleet.default_drone_id)


@app.post("/drone/takeoff", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, deprecated=True)
async def compat_takeoff(body: TakeoffRequest) -> dict:
    return fleet.takeoff(fleet.default_drone_id, pause_stream_first=body.pause_stream_first)


@app.post("/drone/land", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, deprecated=True)
async def compat_land() -> dict:
    return fleet.land(fleet.default_drone_id)


@app.post("/drone/emergency", dependencies=[Depends(verify_authorization)], response_model=ApiOkResponse, deprecated=True)
async def compat_emergency() -> dict:
    return fleet.emergency(fleet.default_drone_id)


@app.post("/drone/rc", dependencies=[Depends(verify_authorization)], response_model=RcResponse, deprecated=True)
async def compat_rc(body: RcRequest) -> dict:
    return fleet.rc(fleet.default_drone_id, body.lr, body.fb, body.ud, body.yaw)


@app.get("/drone/state", dependencies=[Depends(verify_authorization)], deprecated=True)
async def compat_state() -> dict:
    return fleet.state(fleet.default_drone_id)


@app.get("/drone/battery", dependencies=[Depends(verify_authorization)], deprecated=True)
async def compat_battery() -> dict:
    return fleet.battery(fleet.default_drone_id)


@app.get("/drone/diagnostics", dependencies=[Depends(verify_authorization)], deprecated=True)
async def compat_diagnostics() -> dict:
    return fleet.diagnostics(fleet.default_drone_id)


@app.get("/snapshot.jpg", dependencies=[Depends(verify_authorization_flexible)], deprecated=True)
async def compat_snapshot_jpg(drone_id: str = Query(default=fleet.default_drone_id)) -> Response:
    return await snapshot_jpg_by_id(drone_id)


@app.get("/stream.mjpeg", dependencies=[Depends(verify_authorization_flexible)], deprecated=True)
async def compat_stream_mjpeg(drone_id: str = Query(default=fleet.default_drone_id)) -> StreamingResponse:
    return await stream_mjpeg_by_id(drone_id)


_test_web_dir = Path(__file__).resolve().parent.parent / "test" / "web"
if _test_web_dir.is_dir():
    app.mount("/test-web", StaticFiles(directory=str(_test_web_dir), html=True), name="test_web")
