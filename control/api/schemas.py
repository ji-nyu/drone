"""OpenAPI 문서용 JSON-RPC 스키마."""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, Field


class JsonRpcRequest(BaseModel):
    """JSON-RPC 2.0 단일 요청."""

    jsonrpc: str = Field(
        default="2.0",
        description="JSON-RPC 버전. 반드시 `2.0`",
        examples=["2.0"],
    )
    method: str = Field(
        description=(
            "호출할 메서드 이름. 예: `connect`, `stream_on`, `takeoff`, `battery`, "
            "`diagnostics`, `rc`"
        ),
        examples=["battery"],
    )
    params: dict[str, Any] = Field(
        default_factory=dict,
        description=(
            "메서드별 파라미터 객체. "
            "`connect`: `host`(str), `wait_for_state`(bool), "
            "`takeoff`: `pause_stream_first`(bool), "
            "`rc`: `lr`, `fb`, `ud`, `yaw` (int, -100~100)"
        ),
        examples=[{"pause_stream_first": True}],
    )
    id: int | str | None = Field(
        default=None,
        description="요청 식별자. 응답의 `id`로 그대로 반환",
        examples=[1],
    )

    model_config = {
        "json_schema_extra": {
            "examples": [
                {
                    "jsonrpc": "2.0",
                    "method": "connect",
                    "params": {"host": "192.168.10.1", "wait_for_state": True},
                    "id": 1,
                },
                {"jsonrpc": "2.0", "method": "stream_on", "params": {}, "id": 2},
                {
                    "jsonrpc": "2.0",
                    "method": "takeoff",
                    "params": {"pause_stream_first": True},
                    "id": 3,
                },
            ]
        }
    }


class JsonRpcError(BaseModel):
    code: int = Field(description="JSON-RPC 오류 코드")
    message: str = Field(description="오류 메시지")


class JsonRpcResponse(BaseModel):
    jsonrpc: str = Field(default="2.0")
    result: dict[str, Any] | None = Field(
        default=None,
        description="성공 시 결과 객체",
    )
    error: JsonRpcError | None = Field(
        default=None,
        description="실패 시 오류 객체",
    )
    id: int | str | None = Field(default=None)


class ApiOkResponse(BaseModel):
    ok: bool = Field(default=True, description="성공 여부")
    already: bool | None = Field(default=None, description="이미 같은 상태였는지 여부")


class ConnectRequest(BaseModel):
    host: str = Field(
        default="192.168.10.1",
        description="드론 IP 주소",
        examples=["192.168.10.1"],
    )
    wait_for_state: bool = Field(
        default=True,
        description="초기 상태 패킷 수신을 기다릴지 여부",
    )


class TakeoffRequest(BaseModel):
    pause_stream_first: bool = Field(
        default=False,
        description="true이면 스트림을 잠시 끄고 이륙 후 다시 켬",
    )


class RcRequest(BaseModel):
    lr: int = Field(description="좌/우 속도 (-100~100)", ge=-100, le=100)
    fb: int = Field(description="앞/뒤 속도 (-100~100)", ge=-100, le=100)
    ud: int = Field(description="상/하 속도 (-100~100)", ge=-100, le=100)
    yaw: int = Field(description="회전 속도 (-100~100)", ge=-100, le=100)


class RcResponse(ApiOkResponse):
    sent: bool | None = Field(default=None, description="SDK가 이번 RC를 실제 전송했는지")
    skipped_by_sdk_interval: bool | None = Field(
        default=None,
        description="SDK 최소 송신 간격 제한으로 이번 RC 전송이 생략되었는지",
    )
    sdk_interval_sec: float | None = Field(default=None, description="SDK 최소 RC 송신 간격(초)")
    requested: dict[str, int] | None = Field(default=None, description="요청으로 보낸 RC 값")
    last_rc_control_timestamp_before: float | None = Field(default=None, description="호출 전 SDK 타임스탬프")
    last_rc_control_timestamp_after: float | None = Field(default=None, description="호출 후 SDK 타임스탬프")


class MissionStartRequest(BaseModel):
    mission_id: int = Field(description="missions.id")
    drone_id: str = Field(description="대상 드론 ID (missions.drone_id와 일치)")


class MissionStartResponse(ApiOkResponse):
    accepted: bool | None = Field(default=None, description="미션 시작 요청 접수 여부")
    mission_id: int | None = Field(default=None)
    drone_id: str | None = Field(default=None)
    mission_state: str | None = Field(default=None, description="missions.state 현재값")
    action_count: int | None = Field(default=None, description="mission_actions 개수")
    actions: list[dict[str, Any]] | None = Field(default=None, description="seq/id 오름차순 action 목록")
    note: str | None = Field(default=None)

