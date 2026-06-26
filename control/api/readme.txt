================================================================================
Tello HTTP API (control/api) — 개요
================================================================================

실행 디렉터리는 항상 `control` 이어야 모듈 경로(`api`, `modules`)가 올바르다.

    cd control
    uv run python -m api

또는

    uv run uvicorn api.main:app --host 0.0.0.0 --port 8000

기본 포트는 `api/__main__.py` 기준 8000이다.


================================================================================
인증 (Authorization)
================================================================================

보호되는 엔드포인트는 HTTP 헤더에 아래와 같이 넣어야 한다.

    Authorization: <modules/ctrl.py 의 API_AUTHORIZATION 과 동일한 문자열>

- 값은 **헤더 전체 문자열**이 `API_AUTHORIZATION` 상수와 **완전히 일치**해야 한다.
- `Bearer ` 접두어를 쓰지 않는다(원하면 `API_AUTHORIZATION` 자체를 `Bearer xxx` 형태로
  저장해 두면 그 전체가 헤더 값이 된다).
- 배포 전 `modules/ctrl.py` 의 `API_AUTHORIZATION` 을 반드시 변경할 것.

인증이 필요한 경로:
  POST /rpc
  GET  /stream.mjpeg
  GET  /snapshot.jpg

인증 없이 열리는 경로(예):
  GET  /health
  GET  /
  GET  /docs  (Swagger UI)


================================================================================
엔드포인트 종류
================================================================================

GET /
  API 안내 JSON.

GET /health
  서버 생존 확인 {"status":"ok"}.

GET /docs
  FastAPI Swagger (OpenAPI).

POST /rpc
  JSON-RPC 2.0 단일 요청. 본문은 JSON 객체 하나.
  반드시 헤더: Authorization, Content-Type: application/json

GET /stream.mjpeg
  multipart/x-mixed-replace MJPEG 스트림. 스트림이 켜져 있어야 프레임이 나온다.
  헤더: Authorization

GET /snapshot.jpg
  현재 프레임 JPEG 한 장(짧은 폴링·테스트용). 스트림 미가동 시 503 가능.
  헤더: Authorization

GET /test-web/
  `control/test/web/index.html` 정적 제공. 서버와 같은 호스트로 열면 CORS 없이 테스트하기 좋다.


================================================================================
JSON-RPC 요청 형식
================================================================================

요청(예):

    {
      "jsonrpc": "2.0",
      "method": "<메서드이름>",
      "params": { ... },
      "id": 1
    }

- `params` 는 생략 가능(내부에서 빈 객체로 처리).
- `method` 는 아래 표의 이름. 앞에 `drone.` 을 붙여도 동일하게 동작한다.

응답(성공):

    { "jsonrpc": "2.0", "result": { ... }, "id": 1 }

응답(실패):

    { "jsonrpc": "2.0", "error": { "code": <int>, "message": "<문자열>" }, "id": 1 }

대표 코드:
  -32600  잘못된 요청
  -32601  알 수 없는 method
  -32602  params 오류
  -32603  내부 오류
  -32000  드론/연결 관련 등 비즈니스 예외


================================================================================
JSON-RPC 메서드 목록
================================================================================

ping
  params 없음. 서버·연결 여부 확인.
  result 예: {"ok": true, "connected": false}

connect
  params (선택):
    host            문자열, 기본 "192.168.10.1"
    wait_for_state  bool, 기본 true

disconnect
  params 없음. 연결 종료·정리.

stream_on / stream_off
  params 없음. 카메라 스트림 on/off.

takeoff
  params (선택):
    pause_stream_first  bool, 기본 false. true 이면 스트림을 잠시 끄고 이륙 후 다시 켠다(UDP 부하 완화).

land / emergency
  params 없음. 착륙·비상 정지. land 는 공중(비행 중)일 때만 성공하는 경우가 많다.

diagnostics
  params 없음. battery, height_cm, temperature_c, state, stream_on, is_flying 등.

rc
  params 필수 (정수):
    lr   좌우 -100~100
    fb   전후 -100~100
    ud   상하 -100~100
    yaw  회전 -100~100

state
  params 없음. Tello 상태 딕셔너리(키는 기체 펌웨어에 따름).

battery
  params 없음. result 예: {"battery": 0~100}


================================================================================
curl 사용 예시
================================================================================

암호를 환경 변수에 넣어 쓰는 예(값은 ctrl.py 와 맞출 것):

    export AUTH='tello-api-secret-change-me'

인증 없이 호출(401 기대):

    curl -s -o /dev/null -w "%{http_code}\n" \
      -X POST http://127.0.0.1:8000/rpc \
      -H "Content-Type: application/json" \
      -d '{"jsonrpc":"2.0","method":"ping","id":1}'

ping:

    curl -s -X POST http://127.0.0.1:8000/rpc \
      -H "Content-Type: application/json" \
      -H "Authorization: $AUTH" \
      -d '{"jsonrpc":"2.0","method":"ping","id":1}'

연결 후 스트림:

    curl -s -X POST http://127.0.0.1:8000/rpc \
      -H "Content-Type: application/json" \
      -H "Authorization: $AUTH" \
      -d '{"jsonrpc":"2.0","method":"connect","params":{},"id":2}'

    curl -s -X POST http://127.0.0.1:8000/rpc \
      -H "Content-Type: application/json" \
      -H "Authorization: $AUTH" \
      -d '{"jsonrpc":"2.0","method":"stream_on","params":{},"id":3}'

MJPEG(출력은 바이너리이므로 파일로 저장하거나 플레이어로):

    curl -N -H "Authorization: $AUTH" \
      http://127.0.0.1:8000/stream.mjpeg -o /tmp/tello.mjpeg


================================================================================
브라우저에서 영상 보기
================================================================================

`<img src=".../stream.mjpeg">` 는 커스텀 `Authorization` 헤더를 붙일 수 없다.

- 권장: 제공 페이지 `http://127.0.0.1:8000/test-web/` 에서 snapshot 폴링으로 미리보기.
- 또는 별도 클라이언트(ffmpeg, curl, 데스크톱 앱)에서 헤더를 넣어 MJPEG 수신.


================================================================================
자동 테스트 스크립트
================================================================================

서버를 띄운 뒤:

    cd control
    uv run python test/test_api.py

`--base` 로 호스트·포트 변경 가능.


================================================================================
문서 끝
================================================================================
