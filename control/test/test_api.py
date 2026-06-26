#!/usr/bin/env python3
"""Tello API 인증·멀티드론 스모크 테스트.

사전에 서버를 띄운 뒤 실행하세요::

    cd control
    uv run python -m api

    # 다른 터미널
    uv run python test/test_api.py

옵션::

    uv run python test/test_api.py --base http://127.0.0.1:8000
"""

from __future__ import annotations

import argparse
import json
import sys
import urllib.error
import urllib.request

from modules.ctrl import API_AUTHORIZATION


def _post(url: str, headers: dict[str, str], body: dict) -> tuple[int, bytes]:
    data = json.dumps(body).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=data,
        headers=headers,
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=15) as resp:
        return resp.status, resp.read()


def main() -> int:
    parser = argparse.ArgumentParser(description="Tello API multi-drone smoke test")
    parser.add_argument(
        "--base",
        default="http://127.0.0.1:8000",
        help="API base URL (no trailing slash)",
    )
    args = parser.parse_args()
    base = args.base.rstrip("/")
    rpc_url = f"{base}/rpc"
    drones_url = f"{base}/drones"

    # 1) Authorization 없음 → 401
    try:
        _post(
            rpc_url,
            {"Content-Type": "application/json"},
            {"jsonrpc": "2.0", "method": "ping", "id": 1},
        )
    except urllib.error.HTTPError as e:
        if e.code != 401:
            print(f"FAIL: expected HTTP 401 without Authorization, got {e.code}")
            return 1
        print("OK: POST /rpc without Authorization → 401")
    except urllib.error.URLError as e:
        print(f"SKIP: 서버에 연결할 수 없습니다 ({e}). uv run python -m api 를 먼저 실행하세요.")
        return 2

    # 2) 드론 목록 조회
    try:
        req = urllib.request.Request(
            drones_url,
            headers={"Authorization": API_AUTHORIZATION},
            method="GET",
        )
        with urllib.request.urlopen(req, timeout=15) as resp:
            status = resp.status
            raw = resp.read()
    except urllib.error.HTTPError as e:
        print(f"FAIL: GET /drones with auth → HTTP {e.code} {e.read().decode(errors='replace')}")
        return 1
    except urllib.error.URLError as e:
        print(f"SKIP: {e}")
        return 2

    if status != 200:
        print(f"FAIL: expected 200, got {status}")
        return 1

    payload = json.loads(raw.decode())
    items = payload.get("items", [])
    if not isinstance(items, list) or len(items) == 0:
        print("FAIL: /drones result invalid:", payload)
        return 1
    print(f"OK: GET /drones with Authorization → {len(items)} drones")

    stream_only_ids = {str(x.get("id")) for x in items if str(x.get("host")) == "10.10.0.8"}
    controllable = next((str(x.get("id")) for x in items if str(x.get("id")) not in stream_only_ids), None)
    if not controllable:
        print("WARN: controllable drone 없음, JSON-RPC 제어 테스트는 생략")
        return 0

    # 3) ping/drone_id
    try:
        status, raw = _post(
            rpc_url,
            {
                "Content-Type": "application/json",
                "Authorization": API_AUTHORIZATION,
            },
            {"jsonrpc": "2.0", "method": "ping", "params": {"drone_id": controllable}, "id": 2},
        )
    except urllib.error.HTTPError as e:
        print(f"FAIL: ping with auth → HTTP {e.code} {e.read().decode(errors='replace')}")
        return 1

    payload = json.loads(raw.decode())
    if payload.get("error"):
        print("FAIL:", payload)
        return 1
    if not payload.get("result", {}).get("ok"):
        print("FAIL: ping result:", payload)
        return 1

    print(f"OK: POST /rpc ping(drone_id={controllable}) →", payload["result"])

    # 4) client 측 정책: 10.10.0.8 드론은 조종 명령을 보내지 않음
    if stream_only_ids:
        print(f"OK: stream-only IDs(client policy): {sorted(stream_only_ids)}")
    else:
        print("INFO: stream-only drone(10.10.0.8) 없음")

    # 안전한 제어 테스트: rc=0 (중립)
    status, raw = _post(
        rpc_url,
        {
            "Content-Type": "application/json",
            "Authorization": API_AUTHORIZATION,
        },
        {
            "jsonrpc": "2.0",
            "method": "rc",
            "params": {"drone_id": controllable, "lr": 0, "fb": 0, "ud": 0, "yaw": 0},
            "id": 3,
        },
    )
    payload = json.loads(raw.decode())
    if payload.get("error"):
        print("FAIL: rc(0,0,0,0):", payload)
        return 1
    print(f"OK: rc neutral sent to controllable drone_id={controllable}")

    # 5) 스냅샷(드론·스트림 없으면 503 가능)
    target_for_snapshot = next(iter(stream_only_ids), controllable)
    snap_url = f"{base}/snapshot.jpg?drone_id={target_for_snapshot}"
    req = urllib.request.Request(
        snap_url,
        headers={"Authorization": API_AUTHORIZATION},
        method="GET",
    )
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            code = resp.status
            _ = resp.read(16)
    except urllib.error.HTTPError as e:
        code = e.code
    except urllib.error.URLError as e:
        print(f"SKIP snapshot: {e}")
        return 0

    if code in (200, 503):
        print(f"OK: GET /snapshot.jpg?drone_id={target_for_snapshot} → {code} (503은 스트림 미가동 시 정상)")
    else:
        print(f"WARN: GET /snapshot.jpg → {code}")

    print("all checks passed")
    return 0


if __name__ == "__main__":
    sys.exit(main())
