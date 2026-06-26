# Tello HTTP API 문서

`control` 디렉터리에서 서버를 실행한 뒤 아래 URL을 사용한다.

- 기본 베이스 URL: `http://127.0.0.1:8000` (포트는 실행 시 바뀔 수 있음)
- 인증: HTTP 헤더 `Authorization` 값이 `modules/ctrl.py`의 **`API_AUTHORIZATION`** 상수와 **완전히 동일**해야 한다. (아래 예시에서는 기본값 `tello-api-secret-change-me` 사용)

쉘에서 반복 사용할 때(아래 curl 예시와 동일한 패턴):

```bash
export BASE='http://127.0.0.1:8000'
export AUTH='tello-api-secret-change-me'
export DRONE_ID='TT-01'
```

`DRONE_ID`는 **실제로 `GET /drones` 응답에 나오는 `drone_id`**로 바꾼다. 예시에서는 `TT-01`을 사용한다.

### 이 문서에서 쓰는 말

- **요청 본문(본문)**  
  HTTP에서 **POST·PUT 등으로 서버에 실어 보내는 데이터**를 말한다. 이 프로젝트는 대부분 **JSON**이라, 예전에 표 제목을 `본문(JSON):`처럼 적기도 했다.  
  **curl에서는 `-d '{ ... }'` 안에 들어가는 부분이 곧 본문**이다. `Content-Type: application/json`과 함께 쓴다.

- **표(`지원 method` 등)**  
  `POST /rpc` 처럼 한 URL에 여러 `method`가 있을 때만 **참고용 표**를 둔다. 각 `method`의 **실제 호출은 바로 아래 `curl` 한 블록**이 기준이다.

- **curl 개수**  
  가능한 한 **엔드포인트(또는 JSON-RPC `method`)당 `curl` 블록은 하나**만 둔다. 같은 API의 변형 예시(예: `pause_stream_first` true/false)는 넣지 않고, 필요하면 표나 한 줄 설명으로만 적는다.

- **기능**  
  맨 위 표를 매번 보지 않아도 되도록, 각 절(또는 `POST /rpc`의 각 `method`)에 **`기능:` 한 줄**로 무엇을 하는 API인지 적어 두었다.

---

## 전체 엔드포인트 목록 (`control/api/main.py` 기준)

| 메서드 | 경로 | 인증 | 설명 |
|--------|------|:----:|------|
| GET | `/` | 아니오 | API 안내 JSON |
| GET | `/health` | 아니오 | 헬스체크 |
| GET | `/docs` | 아니오 | Swagger UI (OpenAPI) |
| GET | `/openapi.json` | 아니오 | OpenAPI 스키마 JSON |
| GET | `/redoc` | 아니오 | ReDoc UI |
| GET | `/test-web/` | 아니오 | 테스트용 정적 페이지 |
| GET | `/drones` | 예 | 등록 드론 목록·기본 ID |
| GET | `/drones/{drone_id}` | 예 | 드론 메타 조회 |
| POST | `/missions/start` | 예 | 미션 시작(DB 액션 순차 실행) |
| POST | `/rpc` | 예 | JSON-RPC 2.0 단일 요청 |
| POST | `/test-web/log` | 예 | test-web 로그를 서버 파일로 저장(OpenAPI 비표시) |
| POST | `/drones/{drone_id}/stream-on` | 예 | 영상 스트림 켜기 |
| POST | `/drones/{drone_id}/stream-off` | 예 | 영상 스트림 끄기 |
| POST | `/drones/{drone_id}/takeoff` | 예 | 이륙(JSON body) |
| POST | `/drones/{drone_id}/land` | 예 | 착륙 |
| POST | `/drones/{drone_id}/emergency` | 예 | 비상 정지 |
| POST | `/drones/{drone_id}/rc` | 예 | RC 조종(JSON body, 각 축 -100~100) |
| GET | `/drones/{drone_id}/state` | 예 | 상태 dict |
| GET | `/drones/{drone_id}/battery` | 예 | 배터리 |
| GET | `/drones/{drone_id}/diagnostics` | 예 | 진단 정보 |
| GET | `/drones/{drone_id}/snapshot.jpg` | 예 | JPEG 1장(권장 경로) |
| GET | `/drones/{drone_id}/stream.mjpeg` | 예 | MJPEG 스트림(권장 경로) |

아래는 **예전 단일 드론용 URL**이다. 경로에 `drone_id`가 없고, **서버가 정한 기본 드론(`default_drone_id`)** 만 대상으로 동작한다. 새로 작성하는 클라이언트는 위 **`/drones/{drone_id}/...`** 를 쓰는 것이 좋다(FastAPI에서 `deprecated` 표시).

| 메서드 | 경로 | 인증 | 설명 |
|--------|------|:----:|------|
| POST | `/drone/stream-on` | 예 | 기본 드론만: 영상 스트림 켜기 |
| POST | `/drone/stream-off` | 예 | 기본 드론만: 스트림 끄기 |
| POST | `/drone/takeoff` | 예 | 기본 드론만: 이륙(JSON body `pause_stream_first`) |
| POST | `/drone/land` | 예 | 기본 드론만: 착륙 |
| POST | `/drone/emergency` | 예 | 기본 드론만: 비상 정지 |
| POST | `/drone/rc` | 예 | 기본 드론만: RC(JSON body) |
| GET | `/drone/state` | 예 | 기본 드론만: 상태 |
| GET | `/drone/battery` | 예 | 기본 드론만: 배터리 |
| GET | `/drone/diagnostics` | 예 | 기본 드론만: 진단 |
| GET | `/snapshot.jpg` | 예 | 스냅샷 JPEG. `?drone_id=`로 대상 지정(생략 시 기본 드론) |
| GET | `/stream.mjpeg` | 예 | MJPEG. `?drone_id=`로 대상 지정(생략 시 기본 드론) |

이동·회전·호버·배송 등은 **REST 경로가 없고** `POST /rpc` 또는 미션(`POST /missions/start`)으로만 호출된다.

---

## 공통 제약

- **응답 포맷팅**: JSON이 길 때 `| python3 -m json.tool` 로 들여쓰기 가능. `jq`가 있으면 `| jq .` 도 동일 목적.
- **`Authorization`**: 값은 서버의 `API_AUTHORIZATION`과 **바이트 단위로 동일**(앞뒤 공백 제거 후 비교). 틀리면 **401**.
- **`drone_id`**: 서버 시작 시 DB에서 읽어 등록된 ID여야 한다. 없으면 `unknown_drone_id:*` 등 런타임 오류 가능.
- **경로 변수 `drone_id`**: URL에 그대로 넣는다. 특수문자가 있으면 URL 인코딩이 필요할 수 있다.
- **POST 요청 데이터**: JSON이면 `Content-Type: application/json` + `curl -d '...'` (위「이 문서에서 쓰는 말」참고).

---

## GET `/`

**기능:** 루트에서 제공하는 주요 경로 안내 JSON을 반환한다.

**curl**

```bash
curl -s "${BASE:-http://127.0.0.1:8000}/"
```

---

## GET `/health`

**기능:** 서버 프로세스가 살아 있는지 확인하는 헬스체크.

**응답 예:** `{"status":"ok"}`

**curl**

```bash
curl -s "${BASE:-http://127.0.0.1:8000}/health"
```

---

## GET `/docs`

**기능:** Swagger UI(OpenAPI) HTML 페이지. 브라우저에서 API를 시험할 때 사용.

**curl (HTTP 코드만)**

```bash
curl -s -o /dev/null -w "%{http_code}\n" "${BASE:-http://127.0.0.1:8000}/docs"
```

---

## GET `/openapi.json`

**기능:** OpenAPI 3 스키마를 JSON으로 내려준다(코드 생성·클라이언트 스텁용).

**curl**

```bash
curl -s "${BASE:-http://127.0.0.1:8000}/openapi.json"
```

---

## GET `/redoc`

**기능:** ReDoc UI HTML. 읽기 좋은 API 문서 페이지.

**curl (HTTP 코드만)**

```bash
curl -s -o /dev/null -w "%{http_code}\n" "${BASE:-http://127.0.0.1:8000}/redoc"
```

---

## GET `/test-web/`

**기능:** `control/test/web/` 테스트용 정적 페이지(HTML/JS). 인증 없음.

**curl**

```bash
curl -s -o /dev/null -w "%{http_code}\n" "${BASE:-http://127.0.0.1:8000}/test-web/"
```

브라우저: `http://127.0.0.1:8000/test-web/`

---

## GET `/drones`

**기능:** 서버에 등록된 드론 목록과 `default_drone_id`를 조회한다.

**필수 헤더:** `Authorization`

**curl**

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drones"
```

(응답 들여쓰기: 끝에 `| python3 -m json.tool` 를 붙이면 된다.)

---

## GET `/drones/{drone_id}`

**기능:** 해당 `drone_id`의 레지스트리 메타(DB 연동 정보 등)를 조회한다.

**필수 헤더:** `Authorization`

**curl**

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}"
```

---

## POST `/test-web/log`

**기능:** test-web에서 보낸 요청/응답 로그를 서버 파일(`control/logs/test-web-api.log`)에 남긴다.

test-web 페이지가 보내는 형식과 동일하게 `-d`는 JSON 객체(필드는 서버가 `.get`으로 읽음).

**필수 헤더:** `Content-Type: application/json`, `Authorization`

**`-d` JSON 필드(예시, 모두 선택):** `label`, `request_ts`, `response_ts`, `curl`, `status`, `response`

**curl**

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/test-web/log" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"label":"manual","request_ts":"2026-04-09T12:00:00","response_ts":"2026-04-09T12:00:01","curl":"curl -s ...","status":200,"response":"{}"}'
```

---

## 멀티 드론 REST (`/drones/{drone_id}/...`) — 권장

**기능:** 경로에 드론 ID를 넣어 해당 드론만 제어·조회한다(권장 방식).

아래 모두 **`Authorization` 필요**. `{drone_id}`는 실제 등록 ID로 치환.

### POST `/drones/{drone_id}/stream-on`

**기능:** 해당 드론 영상 스트림(Tello `streamon`)을 켠다.

JSON 요청 바디 없음 (`-d` 생략).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/stream-on" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drones/{drone_id}/stream-off`

**기능:** 해당 드론 영상 스트림(`streamoff`)을 끈다.

JSON 요청 바디 없음 (`-d` 생략).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/stream-off" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drones/{drone_id}/takeoff`

**기능:** 해당 드론 이륙(`takeoff`).

`-d` JSON: `pause_stream_first` (bool, 생략 시 false). `true`이면 스트림을 잠시 끄고 이륙 후 다시 켠다.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/takeoff" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"pause_stream_first":false}'
```

### POST `/drones/{drone_id}/land`

**기능:** 해당 드론 착륙(`land`).

JSON 요청 바디 없음 (`-d` 생략).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/land" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drones/{drone_id}/emergency`

**기능:** 해당 드론 비상 정지(`emergency`).

JSON 요청 바디 없음 (`-d` 생략).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/emergency" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drones/{drone_id}/rc`

**기능:** 해당 드론에 RC 조종값(`send_rc_control`)을 보낸다.

`-d` JSON: `lr`, `fb`, `ud`, `yaw` 정수, 각각 **-100~100** (스키마 검증).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/rc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"lr":0,"fb":0,"ud":0,"yaw":0}'
```

### GET `/drones/{drone_id}/state`

**기능:** 해당 드론의 Tello 상태 dict를 조회한다.

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/state"
```

### GET `/drones/{drone_id}/battery`

**기능:** 해당 드론 배터리(%)를 조회한다.

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/battery"
```

### GET `/drones/{drone_id}/diagnostics`

**기능:** 해당 드론 진단 정보(스트림 여부·배터리 등)를 조회한다.

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/diagnostics"
```

### GET `/drones/{drone_id}/snapshot.jpg`

**기능:** 현재 영상 프레임 1장을 JPEG로 받는다(스냅샷).

프레임 없으면 **503**, 응답 텍스트 `no frame`.

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/snapshot.jpg" \
  -o /tmp/tello_snapshot.jpg
```

### GET `/drones/{drone_id}/stream.mjpeg`

**기능:** MJPEG(`multipart/x-mixed-replace`)로 연속 프레임을 받는다.

스트림이 꺼져 있으면 프레임이 안 나올 수 있음. 중단: Ctrl+C.

```bash
curl -N -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drones/${DRONE_ID:-TT-01}/stream.mjpeg" \
  -o /tmp/tello_stream.mjpeg
```

---

## 호환 경로 (`/drone/*`, `/snapshot.jpg`, `/stream.mjpeg`) — deprecated

**기능:** 예전 단일 드론 URL 호환. **기본 드론**(`default_drone_id`)만 대상이거나, 쿼리로 `drone_id`를 준다.

**기본 드론** `fleet.default_drone_id`만 대상(경로에 `drone_id` 없음).  
`snapshot`/`stream` 호환 경로는 쿼리 **`drone_id`** 로 대상 지정 가능(기본값은 default 드론).

### POST `/drone/stream-on`

**기능:** 기본 드론에 대해 스트림 켜기(멀티 드론 경로와 동일 동작, 대상만 고정).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drone/stream-on" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drone/stream-off`

**기능:** 기본 드론 스트림 끄기.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drone/stream-off" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drone/takeoff`

**기능:** 기본 드론 이륙.

`-d` JSON: `pause_stream_first` (bool, 기본 false)

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drone/takeoff" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"pause_stream_first":false}'
```

### POST `/drone/land`

**기능:** 기본 드론 착륙.

JSON 요청 바디 없음 (`-d` 생략).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drone/land" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drone/emergency`

**기능:** 기본 드론 비상 정지.

JSON 요청 바디 없음 (`-d` 생략).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drone/emergency" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}"
```

### POST `/drone/rc`

**기능:** 기본 드론 RC 조종.

`-d` JSON: `lr`, `fb`, `ud`, `yaw` 정수, 각 -100~100.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/drone/rc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"lr":0,"fb":0,"ud":0,"yaw":0}'
```

### GET `/drone/state`

**기능:** 기본 드론 상태 조회.

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drone/state"
```

### GET `/drone/battery`

**기능:** 기본 드론 배터리 조회.

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drone/battery"
```

### GET `/drone/diagnostics`

**기능:** 기본 드론 진단 조회.

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/drone/diagnostics"
```

### GET `/snapshot.jpg`

**기능:** 호환용 스냅샷 JPEG. `?drone_id=`로 대상 지정.

**쿼리:** `drone_id` (선택, 기본 `default_drone_id`)

```bash
curl -s -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/snapshot.jpg?drone_id=${DRONE_ID:-TT-01}" \
  -o /tmp/tello_snapshot.jpg
```

### GET `/stream.mjpeg`

**기능:** 호환용 MJPEG 스트림. `?drone_id=`로 대상 지정.

**쿼리:** `drone_id` (선택, 기본 `default_drone_id`)

```bash
curl -N -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  "${BASE:-http://127.0.0.1:8000}/stream.mjpeg?drone_id=${DRONE_ID:-TT-01}" \
  -o /tmp/tello_stream.mjpeg
```

---

## POST `/missions/start`

**기능:** DB에 정의된 미션(`missions`)과 액션(`mission_actions`)을 순서대로 실행한다.

DB `missions` / `mission_actions` 기반. **`mission_id`는 `missions.id`**, **`drone_id`는 해당 미션 행의 `drone_id`와 일치**해야 한다.

**필수 헤더:** `Content-Type: application/json`, `Authorization`

`-d` JSON: `mission_id`(int, DB의 미션 PK), `drone_id`(str, 해당 미션 행과 동일).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/missions/start" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"mission_id":1,"drone_id":"'${DRONE_ID:-TT-01}'"}'
```

lock 재시도: `config.ini` `[mission]`.

---

## POST `/rpc`

**기능:** JSON-RPC 2.0으로 드론 명령·조회를 한 URL(`POST /rpc`)에 보낸다.

JSON-RPC **2.0 단일 객체**만 지원. **`jsonrpc`는 반드시 `"2.0"`**.

**필수 헤더:** `Content-Type: application/json`, `Authorization`

`-d`로 보낼 JSON-RPC **한 객체**: `jsonrpc`는 `"2.0"`, `method`(문자열, 필수), `params`(객체, 생략 시 `{}`, 배열 불가), `id`(선택).  
`params` 안에 `drone_id`를 넣을 수 있고, 생략 시 서버 기본 드론 ID가 쓰인다.

### 지원 `method` 및 추가 파라미터 (`control/api/rpc.py`)

| method | 추가 params | 비고 |
|--------|-------------|------|
| `ping` | — | |
| `list_drones` | — | 드론 목록 |
| `connect` | — | **항상 거부** (서버 기동 시 연결) |
| `disconnect` | — | **항상 거부** |
| `stream_on` | — | |
| `stream_off` | — | |
| `takeoff` | `pause_stream_first` (bool, 선택) | 기본 `false` |
| `land` | — | |
| `forward` | `value` (int, **필수**) | cm |
| `back` | `value` (int) | cm |
| `left` | `value` (int) | cm |
| `right` | `value` (int) | cm |
| `up` | `value` (int) | cm |
| `down` | `value` (int) | cm |
| `rotate_cw` | `value` (int) | 도 |
| `rotate_ccw` | `value` (int) | 도 |
| `hover` | `value` (int) | 초(서버 sleep) |
| `deliver` | — | 현재 no-op |
| `rc` | `lr`,`fb`,`ud`,`yaw` 정수 **필수** | Tello SDK 관례상 -100~100 |
| `emergency` | — | |
| `state` | — | |
| `battery` | — | |
| `diagnostics` | — | |

`method`에 `drone.` 접두어를 붙여도 동일하게 처리된다.

### curl (`POST /rpc`) — **`method` 하나당 아래에서 `curl` 블록은 하나**

공통: `BASE`, `AUTH`, `DRONE_ID`. `-d` 안의 `"id"` 는 임의. 응답 정리: `| python3 -m json.tool`

**`ping`**

**기능:** 해당 드론 연결 여부 등 가벼운 확인(`fleet.ping`).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"ping","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":1}'
```

**`list_drones`**

**기능:** 등록된 드론 목록 조회.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"list_drones","params":{},"id":2}'
```

**`stream_on`**

**기능:** 영상 스트림 켜기.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"stream_on","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":3}'
```

**`stream_off`**

**기능:** 영상 스트림 끄기.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"stream_off","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":4}'
```

**`takeoff`** — `params`에 `"pause_stream_first":true` 를 넣는 형태만 다르고 나머지 동일.

**기능:** 이륙.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"takeoff","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":5}'
```

**`land`**

**기능:** 착륙.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"land","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":6}'
```

**`emergency`**

**기능:** 비상 정지.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"emergency","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":7}'
```

**`forward`** — 같은 형식으로 `method`만 `back` / `left` / `right` / `up` / `down` 으로 바꾸고 `value`(cm)를 조절.

**기능:** 지정 cm 만큼 직선 이동(전진 예시).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"forward","params":{"drone_id":"'${DRONE_ID:-TT-01}'","value":20},"id":8}'
```

**`rotate_cw`** — `rotate_ccw` 도 동일, `value`는 도(°).

**기능:** 시계 방향 회전(반시계는 `rotate_ccw`).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"rotate_cw","params":{"drone_id":"'${DRONE_ID:-TT-01}'","value":90},"id":9}'
```

**`hover`** (`value` = 초)

**기능:** 서버에서 지정 초만큼 대기(호버링 시간 확보).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"hover","params":{"drone_id":"'${DRONE_ID:-TT-01}'","value":2},"id":10}'
```

**`deliver`**

**기능:** 배송 비즈니스 훅(현재 드론 명령 없음).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"deliver","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":11}'
```

**`rc`**

**기능:** RC 조종값 전송.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"rc","params":{"drone_id":"'${DRONE_ID:-TT-01}'","lr":0,"fb":0,"ud":0,"yaw":0},"id":12}'
```

**`state`**

**기능:** Tello 상태 dict.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"state","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":13}'
```

**`battery`**

**기능:** 배터리 %.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"battery","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":14}'
```

**`diagnostics`**

**기능:** 진단 정보.

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"diagnostics","params":{"drone_id":"'${DRONE_ID:-TT-01}'"},"id":15}'
```

`method`에 `drone.` 접두어(예: `drone.battery`)를 붙여도 동일하다. 위 `battery` 예시에서 문자열만 바꾸면 된다.

**`connect`** · **`disconnect`** — 항상 JSON-RPC `error` (HTTP 200). 확인용으로 `connect` 하나만 예시; `disconnect`는 `method`만 바꾸면 된다.

**기능:** 서버에서 사용 안 함(거부 응답 확인용).

```bash
curl -s -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -H "Authorization: ${AUTH:-tello-api-secret-change-me}" \
  -d '{"jsonrpc":"2.0","method":"connect","params":{},"id":16}'
```

**인증 헤더 없음 → HTTP 401** (`POST /rpc` 동일 URL)

**기능:** 인증 실패 시 동작 확인(보안 점검용).

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST "${BASE:-http://127.0.0.1:8000}/rpc" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"ping","id":99}'
```

> 서버 시작 시 DB에서 드론을 자동 connect 하므로 클라이언트는 `connect`/`disconnect`를 쓰지 않는다.  
> `config.ini` `[video] enabled=true`이면 연결 직후 공통 비디오 설정 적용.  
> `[telemetry] enabled=true`이면 `drone_telemetry` 주기 insert.

---

## JSON-RPC 오류 코드 (참고)

**기능:** `POST /rpc` 응답 JSON의 `error.code` 해석 참고(문서용, 별도 HTTP 엔드포인트 아님).

| code | 의미 |
|------|------|
| -32600 | 잘못된 요청 (`jsonrpc`≠2.0, method 없음 등) |
| -32601 | 알 수 없는 method, 비활성 method(`connect` 등) |
| -32602 | params 오류(타입, 필수 필드 누락 등) |
| -32603 | 기타 예외 |
| -32000 | 드론/연결 등 (`not_connected`, `locked`, Tello 예외 등) |

---

## mission_actions type 매핑 (DB 기준)

**기능:** DB에 저장된 미션 액션 `type` 문자열이 서버에서 어떤 RPC로 바뀌는지 매핑 표(참고).

`POST /missions/start` 실행 시 `mission_actions.type`은 아래처럼 내부 RPC로 매핑된다.  
(`seq`, `id` 오름차순 실행)

| mission_actions.type | 행동 설명(한글) | value 의미 | 내부 호출 |
|---|---|---|---|
| `takeoff` | 드론 이륙 | 선택 (bool: `pause_stream_first`) | `takeoff` |
| `land` | 드론 착륙 | 없음 | `land` |
| `forward` | 전진 이동 | cm (정수) | `forward` + `value` |
| `back` | 후진 이동 | cm (정수) | `back` + `value` |
| `left` | 좌측 이동 | cm (정수) | `left` + `value` |
| `right` | 우측 이동 | cm (정수) | `right` + `value` |
| `up` | 상승 | cm (정수) | `up` + `value` |
| `down` | 하강 | cm (정수) | `down` + `value` |
| `rotate_cw` | 시계 방향 회전 | degree (정수) | `rotate_cw` + `value` |
| `rotate_ccw` | 반시계 방향 회전 | degree (정수) | `rotate_ccw` + `value` |
| `hover` | 제자리 대기(호버링) | 초 (정수) | `hover` + `value` (서버 sleep) |
| `deliver` | 배송 완료 처리(비행 명령 없음) | 없음 | `deliver` (드론 명령 없는 business hook, 현재 no-op) |
| 그 외 type | 지원하지 않는 액션 타입 | - | `unsupported_action_type:*` 오류로 action `fail` 처리 |

---

## 서버 실행

**기능:** API 서버를 띄우는 방법(`control` 디렉터리에서 실행).

```bash
cd control
uv run python -m api
```

또는

```bash
cd control
uv run uvicorn api.main:app --host 0.0.0.0 --port 8000
```

추가 안내: `control/readme.txt`, `control/api/readme.txt`
