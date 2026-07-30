# 의존성 / 모듈 설치 가이드

다른 PC에서 환경을 맞출 때 이 문서를 따르면 됩니다.  
역할별로 **설치 환경이 다릅니다.**

| 구분 | 용도 | 설치 방법 |
|------|------|-----------|
| A. 드론 제어 API | Tello 제어, YOLO, 스트림 | `control/` + `uv` |
| B. 보고서 LLM | Word 보고서 생성 (`test3.py`) | 별도 conda/venv + pip |
| C. 웹 서버 | CodeIgniter 대시보드 | PHP 8.1+ + Composer |
| D. (선택) VLM | 영상 VLM 워커 | torch / transformers 등 |

---

## A. 드론 제어 API (`drone/control`)

### 필요 모듈 (요약)

| pip 패키지명 | import / 용도 |
|---|---|
| `djitellopy` | Tello 드론 SDK |
| `opencv-python` | `cv2` 영상 처리 |
| `numpy` | (opencv·ultralytics 의존) |
| `fastapi` | REST / JSON-RPC API |
| `uvicorn[standard]` | API 서버 실행 |
| `ultralytics` | YOLO 탐지 |
| `m3u8` | HLS 관련 |
| `npty-util` | 설정(`config.ini`) 유틸 (사내 git) |
| `npty-db` | DB 유틸 (사내 git) |

> 위 목록은 `control/pyproject.toml`에 이미 정의되어 있습니다.  
> **직접 pip로 하나씩 깔 필요 없이** 아래 한 줄로 설치하는 것을 권장합니다.

### 설치 (권장: uv)

```bash
cd drone/control

# uv 설치 (없는 경우) — https://docs.astral.sh/uv/
# Windows PowerShell 예:
# irm https://astral.sh/uv/install.ps1 | iex

uv sync
```

실행:

```bash
cd drone/control
uv run python -m api
```

### pip만 쓸 때

`npty-*`는 사내 git이 필요합니다.

```bash
cd drone/control
python -m venv .venv
# Windows
.venv\Scripts\activate
# Linux/macOS
# source .venv/bin/activate

pip install -U pip
pip install "djitellopy>=2.5.0" "opencv-python>=4.13.0.92" "fastapi>=0.115.0" "uvicorn[standard]>=0.32.0" "m3u8>=6.0.0" "ultralytics>=8.0.0"
pip install "git+https://public:public@git.npty.xyz/packages/npty/util.git"
pip install "git+https://public:public@git.npty.xyz/packages/npty/db.git"
```

### 시스템 패키지 (Linux)

```bash
sudo apt install -y libgl1 libglib2.0-0
```

### YOLO 가중치

- 설정: `control/config.ini` → `[yolo] model_path`
- 기본 예: `../test/test/best.pt`  
- 파일이 없으면 YOLO만 비활성/실패하고 API 자체는 뜰 수 있습니다.

---

## B. 보고서 생성 (`test3.py` / 웹 보고서 버튼)

웹의 「보고서 생성」은 **제어 API 환경이 아니라**  
`.env`의 `report.python` 이 가리키는 Python으로 `control/test3.py`를 실행합니다.

### 필요 모듈

| pip 패키지명 | 용도 |
|---|---|
| `python-docx` | Word(`.docx`) 생성 |
| `llama-cpp-python` | GGUF LLM 추론 |

### Windows / GPU별 설치 (llama-cpp-python)

`python-docx`는 공통입니다.

```powershell
python -m pip install -U pip
pip install python-docx
```

그다음 GPU에 맞게 **하나만** 선택:

| GPU | 설치 명령 |
|-----|-----------|
| **AMD (Windows, 권장)** | `pip install llama-cpp-python --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/vulkan` |
| AMD HIP 대안 | `pip install llama-cpp-python --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/hip-radeon` |
| NVIDIA CUDA 12.1 | `pip install llama-cpp-python --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/cu121` |
| NVIDIA CUDA 12.2 | `pip install llama-cpp-python --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/cu122` |
| GPU 없음 / 실패 시 | `pip install llama-cpp-python --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/cpu` |

#### AMD PC에서 할 일 (순서)

1. 보고서용 Python 환경 만들기 (예: conda)
   ```powershell
   conda create -n report-llm python=3.11 -y
   conda activate report-llm
   ```
2. Word 모듈 설치
   ```powershell
   pip install python-docx
   ```
3. AMD용 llama-cpp 설치 (Vulkan 권장)
   ```powershell
   pip uninstall llama-cpp-python -y
   pip install llama-cpp-python --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/vulkan
   ```
4. (선택) Vulkan SDK 설치: https://vulkan.lunarg.com/sdk/home#windows  
5. 확인
   ```powershell
   python -c "import docx, llama_cpp; print('OK')"
   ```
6. 웹에 Python 연결 — `drone/web/.env`
   ```ini
   report.python = C:\...\report-llm\python.exe
   ```
   (실제 `where python` 경로로 넣을 것)
7. 모델 경로 — `drone/control/test3.py`의 `MODEL_PATH` 또는 환경변수 `MODEL_PATH`에 GGUF 폴더/파일 지정

Vulkan이 실패하면 `hip-radeon` → 그래도 안 되면 `cpu` 순으로 시도.

#### Windows 경로 길이 오류가 날 때

일반 `pip install llama-cpp-python`(소스 빌드)은 피하고, 위 **wheel URL**만 사용하세요.  
그래도 실패하면 TEMP를 짧게:

```powershell
mkdir C:\tmp -Force
$env:TMP = "C:\tmp"
$env:TEMP = "C:\tmp"
pip install llama-cpp-python --extra-index-url https://abetlen.github.io/llama-cpp-python/whl/vulkan
```

### 웹에 Python 경로 연결

`drone/web/.env`:

```ini
report.python = C:\경로\python.exe
```

예: `D:\conda_envs\ex\python.exe` 또는 `...\report-llm\python.exe`

### LLM 모델 파일 (pip가 아님)

- `test3.py`의 `MODEL_PATH` 또는 환경변수 `MODEL_PATH`
- **`.gguf` 파일** 또는 GGUF가 들어 있는 폴더를 별도 다운로드해야 합니다.
- 예 기본값: `D:\model\EXAONE-3.5-7.8B-Q4`

---

## C. 웹 서버 (`drone/web`)

### 필요 사항

| 항목 | 버전 / 비고 |
|---|---|
| PHP | `^8.1` |
| Composer 패키지 | `codeigniter4/framework ^4.6` |

### 설치

```bash
cd drone/web
composer install
```

개발 서버 예:

```bash
php spark serve
# 또는 웹 서버 DocumentRoot = public/
```

MySQL 등 DB 설정은 `web/.env`의 `database.*` 값을 맞춥니다.

---

## D. (선택) VLM 워커

`control/modules/vlm_worker.py`를 쓸 때만 필요합니다.  
제어 API 기본 동작에는 필수가 아닙니다.

| 패키지 | 용도 |
|---|---|
| `torch` | 추론 |
| `transformers` | 토크나이저 등 |
| `peft` | LoRA 등 |
| `Pillow` | 이미지 |
| `llava` | LLaVA 모델 패키지 (별도 설치/클론) |

CUDA 환경에 맞는 `torch` 설치는 [PyTorch 공식](https://pytorch.org/get-started/locally/)을 따르세요.

---

## 빠른 체크리스트

다른 PC에서 최소로 돌릴 때:

1. [ ] `cd drone/control && uv sync` → API 기동 `uv run python -m api`
2. [ ] `cd drone/web && composer install` → PHP 서버 기동
3. [ ] 보고서 쓸 경우: `pip install -r control/requirements-report.txt` 후 `.env`의 `report.python` 지정
4. [ ] GGUF 모델 경로를 `test3.py` / `MODEL_PATH`에 맞춤
5. [ ] (선택) YOLO `best.pt` 경로 확인

### 설치 확인 명령

```bash
# A. 제어 API
cd drone/control
uv run python -c "import cv2, fastapi, ultralytics, djitellopy; print('control OK')"

# B. 보고서
python -c "import docx, llama_cpp; print('report OK')"

# C. 웹
cd drone/web
php -v
composer show codeigniter4/framework
```

---

## 파일 위치 요약

| 파일 | 내용 |
|---|---|
| `control/pyproject.toml` | 제어 API 공식 의존성 |
| `control/uv.lock` | uv 잠금 파일 (재현 설치) |
| `control/requirements-report.txt` | 보고서용 pip 목록 |
| `web/composer.json` | PHP / CodeIgniter |
| `web/.env` → `report.python` | 보고서용 Python 경로 |
| `control/test3.py` → `MODEL_PATH` | LLM GGUF 경로 |
