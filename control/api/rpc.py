"""JSON-RPC 2.0 요청 처리 (단일 객체 요청)."""

from __future__ import annotations

from typing import Any

from djitellopy import TelloException

from modules.ctrl import fleet


def _dispatch(method: str, params: dict[str, Any]) -> Any:
    drone_id = str(params.get("drone_id", fleet.default_drone_id))

    if method.startswith("drone."):
        method = method[len("drone.") :]

    if method == "ping":
        return fleet.ping(drone_id)

    if method == "list_drones":
        return {"items": fleet.list_drones()}

    if method == "connect":
        raise LookupError("connect is managed by server startup")

    if method == "disconnect":
        raise LookupError("disconnect endpoint is disabled")

    if method == "stream_on":
        return fleet.stream_on(drone_id)

    if method == "stream_off":
        return fleet.stream_off(drone_id)

    if method == "takeoff":
        return fleet.takeoff(
            drone_id,
            pause_stream_first=bool(params.get("pause_stream_first", False)),
        )

    if method == "land":
        return fleet.land(drone_id)

    if method in {"forward", "back", "left", "right", "up", "down", "rotate_cw", "rotate_ccw", "hover"}:
        try:
            v = int(params["value"])
        except (KeyError, TypeError, ValueError) as e:
            raise ValueError(f"{method} requires integer value") from e
        if method == "forward":
            return fleet.forward(drone_id, v)
        if method == "back":
            return fleet.back(drone_id, v)
        if method == "left":
            return fleet.left(drone_id, v)
        if method == "right":
            return fleet.right(drone_id, v)
        if method == "up":
            return fleet.up(drone_id, v)
        if method == "down":
            return fleet.down(drone_id, v)
        if method == "rotate_cw":
            return fleet.rotate_cw(drone_id, v)
        if method == "rotate_ccw":
            return fleet.rotate_ccw(drone_id, v)
        return fleet.hover(drone_id, v)

    if method == "deliver":
        return fleet.deliver(drone_id)

    if method == "diagnostics":
        return fleet.diagnostics(drone_id)

    if method == "rc":
        try:
            lr = int(params["lr"])
            fb = int(params["fb"])
            ud = int(params["ud"])
            yaw = int(params["yaw"])
        except (KeyError, TypeError, ValueError) as e:
            raise ValueError("rc requires integer lr, fb, ud, yaw") from e
        return fleet.rc(drone_id, lr, fb, ud, yaw)

    if method == "emergency":
        return fleet.emergency(drone_id)

    if method == "state":
        return fleet.state(drone_id)

    if method == "battery":
        return fleet.battery(drone_id)

    raise LookupError(f"unknown method: {method}")


def handle_request(body: dict[str, Any]) -> dict[str, Any]:
    """JSON-RPC 단일 요청 dict -> 응답 dict."""
    req_id = body.get("id")

    if body.get("jsonrpc") != "2.0":
        return {
            "jsonrpc": "2.0",
            "error": {"code": -32600, "message": "Invalid Request: jsonrpc must be 2.0"},
            "id": req_id,
        }

    method = body.get("method")
    if not method or not isinstance(method, str):
        return {
            "jsonrpc": "2.0",
            "error": {"code": -32600, "message": "Invalid Request: method"},
            "id": req_id,
        }

    params = body.get("params")
    if params is None:
        params = {}
    if not isinstance(params, dict):
        return {
            "jsonrpc": "2.0",
            "error": {"code": -32602, "message": "Invalid params: expected object"},
            "id": req_id,
        }

    try:
        result = _dispatch(method, params)
        return {"jsonrpc": "2.0", "result": result, "id": req_id}
    except LookupError as e:
        return {"jsonrpc": "2.0", "error": {"code": -32601, "message": str(e)}, "id": req_id}
    except ValueError as e:
        return {"jsonrpc": "2.0", "error": {"code": -32602, "message": str(e)}, "id": req_id}
    except (RuntimeError, TelloException) as e:
        return {"jsonrpc": "2.0", "error": {"code": -32000, "message": str(e)}, "id": req_id}
    except Exception as e:
        return {"jsonrpc": "2.0", "error": {"code": -32603, "message": str(e)}, "id": req_id}
