#!/usr/bin/env python3
"""실시간 웹캠 VLM - VILA 네이티브 방식"""
import os
import sys

# ps3 import 우회 패치
class DummyPS3:
    def __getattr__(self, name):
        return lambda *args, **kwargs: None

sys.modules['ps3'] = DummyPS3()

import torch
from PIL import Image, ImageDraw, ImageFont
from transformers import AutoTokenizer
import time
import cv2
import numpy as np
import threading
from queue import Queue, Empty

# VILA path 추가
sys.path.insert(0, "/home/park/Desktop/vlm/VILA")

from llava.model.language_model.llava_llama import LlavaLlamaModel, LlavaConfig
from llava.mm_utils import process_images
from peft import PeftModel
import torch.nn as nn

class CustomMMProjector(nn.Module):
    def __init__(self):
        super().__init__()
        self.proj = nn.Sequential(
            nn.Linear(1152, 1344),
            nn.GELU(),
            nn.Linear(1344, 1536),
            nn.LayerNorm(1536)
        )
    def forward(self, x):
        return self.proj(x)

BASE_MODEL = "/home/park/Desktop/vlm/model/nvila_ko_chat_vector_1.5B"
CHECKPOINT_DIR = "/home/park/Desktop/vlm/model/nvila_ko_vlm_lora_50/checkpoint-epoch3"
MM_PROJECTOR_PT = os.path.join(CHECKPOINT_DIR, "mm_projector.pt")

# GPU 확인
if torch.cuda.is_available():
    device = torch.device("cuda:0")
    print("✅ GPU 사용 가능")
else:
    device = torch.device("cpu")
    print("⚠️  GPU 사용 불가, CPU 모드")

# 웹캠 설정
CAMERA_ID = 0
FRAME_INTERVAL = 2.0
USE_DISPLAY = True  # GUI 디스플레이 활성화
SAVE_FRAMES = False  # 프레임 저장 여부 (성능을 위해 비활성화)
OUTPUT_DIR = "/home/park/Desktop/vlm/captured_frames"  # 저장 디렉토리
FRAME_BUFFER_SIZE = 2  # 프레임 버퍼 크기 (작을수록 지연 감소)

# 출력 디렉토리 생성
if SAVE_FRAMES:
    os.makedirs(OUTPUT_DIR, exist_ok=True)

print("="*70)
print("Real-time Webcam VLM - VILA Native")
print("="*70)

# 1. 모델 로드
print("\n1. Loading VILA model...")
config = LlavaConfig.from_pretrained(BASE_MODEL)
config.resume_path = BASE_MODEL  # resume_path 설정으로 vision tower 타입 자동 감지
config.llm_cfg = os.path.join(BASE_MODEL, "llm")
config.vision_tower_cfg = os.path.join(BASE_MODEL, "vision_tower")
config.mm_projector_cfg = os.path.join(BASE_MODEL, "mm_projector")  # 파일 경로로 지정
config.image_aspect_ratio = "pad"
config.ps3 = False  # PS3 사용 안 함
config.s2 = False   # S2 사용 안 함
config.dynamic_s2 = False  # Dynamic S2 사용 안 함

# generation config에서 샘플링 파라미터 제거 (do_sample=False이므로)
if hasattr(config, 'temperature'):
    delattr(config, 'temperature')
if hasattr(config, 'top_p'):
    delattr(config, 'top_p')
if hasattr(config, 'top_k'):
    delattr(config, 'top_k')

model = LlavaLlamaModel(config=config)
print("✅ Model structure loaded")

# 2. MM Projector 로드
print("2. Loading MM Projector...")
state = torch.load(MM_PROJECTOR_PT, map_location='cpu')
custom_proj = CustomMMProjector()
custom_proj.load_state_dict(state)
model.mm_projector = custom_proj
print("✅ MM Projector loaded")

# 3. Tokenizer
print("3. Loading tokenizer...")
tokenizer = AutoTokenizer.from_pretrained(os.path.join(BASE_MODEL, "llm"), trust_remote_code=True)
if "<image>" not in tokenizer.get_vocab():
    tokenizer.add_tokens(["<image>"], special_tokens=True)
model.resize_token_embeddings(len(tokenizer))

image_token_id = tokenizer.convert_tokens_to_ids("<image>")
model.media_token_ids = {"image": image_token_id}
tokenizer.media_token_ids = {"image": image_token_id}
config.media_token_ids = {"image": image_token_id}
model.tokenizer = tokenizer
print(f"✅ Tokenizer loaded (image token: {image_token_id})")

# 4. LoRA 로드
print("4. Loading LoRA adapter...")
LOAD_LORA = True  # LoRA 로드 활성화
if LOAD_LORA and os.path.exists(CHECKPOINT_DIR):
    try:
        model.llm = PeftModel.from_pretrained(model.llm, CHECKPOINT_DIR)
        if hasattr(model.llm, 'base_model') and hasattr(model.llm.base_model, 'model'):
            if hasattr(model.llm.base_model.model, 'model'):
                model.llm.model = model.llm.base_model.model.model
        print("✅ LoRA loaded")
    except Exception as e:
        print(f"⚠️  LoRA 로드 실패: {e}")
        print("   베이스 모델만 사용합니다")
else:
    print("⚠️  LoRA 로드 건너뜀 - 베이스 모델만 사용")

# 5. GPU 이동
print("5. Moving to device...")
dtype = torch.float32 if device.type == "cpu" else torch.bfloat16
model = model.to(device, dtype=dtype)
print(f"✅ Model on {device}")

# 6. end_tokens 비활성화
if hasattr(model, 'encoders'):
    for enc_name in model.encoders:
        model.encoders[enc_name].end_tokens = None

# 7. eval 모드
model.eval()
print("✅ Model ready")

# 8. 웹캠 초기화
print("\n6. Opening webcam...")
cap = None
for camera_id in [CAMERA_ID, 0, 1, 2]:  # 여러 카메라 ID 시도
    print(f"   시도 중: /dev/video{camera_id}")
    test_cap = cv2.VideoCapture(camera_id, cv2.CAP_V4L2)
    if test_cap.isOpened():
        ret, frame = test_cap.read()
        if ret and frame is not None:
            cap = test_cap
            CAMERA_ID = camera_id
            print(f"✅ 카메라 {camera_id} 연결 성공")
            break
        else:
            test_cap.release()
    else:
        if test_cap is not None:
            test_cap.release()

if cap is None or not cap.isOpened():
    print("❌ 사용 가능한 웹캠을 찾을 수 없습니다!")
    print("   다른 프로그램이 카메라를 사용 중인지 확인하세요.")
    print("   또는 다음 명령어로 권한을 확인하세요:")
    print("   sudo usermod -a -G video $USER")
    exit(1)

cap.set(cv2.CAP_PROP_FRAME_WIDTH, 1280)
cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 720)
cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)  # 버퍼 크기 최소화로 최신 프레임 유지
print(f"✅ 웹캠 열림 (해상도: {int(cap.get(3))}x{int(cap.get(4))})")

# 9. 질문 설정
#question = "이 영상에서 보이는 사물들을 설명해줘."

question = "이 영상에서 무엇을 볼 수 있나요?"
#question = "이 영상에 어떤 객체가 보이나요?"

#question = "한국어를 읽어주세요."
#question = "글을 읽어주세요."
#question = "read the article"
#question = "read the korean"

print(f"\n7. 질문: {question}")
print("   (Ctrl+C로 종료)")

# 한글 폰트 로드 함수
def get_korean_font(size=20):
    """Linux에서 한글 폰트를 찾아 로드"""
    font_paths = [
        "/usr/share/fonts/truetype/nanum/NanumGothic.ttf",
        "/usr/share/fonts/truetype/nanum/NanumBarunGothic.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
    ]
    for font_path in font_paths:
        if os.path.exists(font_path):
            try:
                return ImageFont.truetype(font_path, size)
            except:
                continue
    # 폰트를 찾지 못하면 기본 폰트 사용
    return ImageFont.load_default()

# 폰트 미리 로드
font_large = get_korean_font(32)
font_small = get_korean_font(24)

# 글로벌 변수
frame_queue = Queue(maxsize=FRAME_BUFFER_SIZE)
result_queue = Queue(maxsize=1)
stop_event = threading.Event()
last_answer = "대기 중..."
last_inference_time = 0
frame_count = 0
inference_count = 0

# VLM 추론 스레드
def inference_thread():
    global last_answer, last_inference_time, inference_count
    import re
    
    while not stop_event.is_set():
        try:
            # 큐에서 프레임 가져오기 (타임아웃 0.1초)
            frame_data = frame_queue.get(timeout=0.1)
            if frame_data is None:
                break
            
            frame, frame_num = frame_data
            inference_count += 1
            
            # OpenCV BGR → RGB 변환
            rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            pil_image = Image.fromarray(rgb_frame)
            
            # 이미지 전처리
            image_tensor = process_images([pil_image], model.get_vision_tower().image_processor, config)
            image_tensor = image_tensor.to(device, dtype=dtype)
            
            # 프롬프트 구성
            prompt = f"<image>\n질문: {question}\n답변:"
            input_ids = tokenizer(prompt, return_tensors="pt").input_ids.to(device)
            
            # Media 구성
            media = {"image": [image_tensor[0]]}
            media_config = {"image": {}}
            
            # 추론 시작
            inference_start = time.time()
            
            with torch.no_grad():
                outputs = model.generate(
                    input_ids,
                    media=media,
                    media_config=media_config,
                    max_new_tokens=25,
                    do_sample=False,
                    pad_token_id=tokenizer.eos_token_id,
                    eos_token_id=tokenizer.eos_token_id,
                )
            
            inference_time = time.time() - inference_start
            
            # 결과 디코딩
            result = tokenizer.decode(outputs[0], skip_special_tokens=True)
            
            if "답변:" in result:
                result = result.split("답변:")[-1].strip()
            
            # 첫 문장만 추출
            sentences = re.split(r'([.!?]\s+)', result)
            if len(sentences) >= 2:
                answer = sentences[0] + sentences[1].strip()
            else:
                answer = result
            
            last_answer = answer
            last_inference_time = time.time()
            
            # 결과 출력
            print(f"\n[프레임 {frame_num}] {inference_time:.2f}초")
            print(f"답변: {answer}")
            print("-" * 70)
            
        except Empty:
            continue
        except Exception as e:
            print(f"⚠️  추론 오류: {e}")
            continue

print("\n" + "="*70)
print("실시간 분석 시작...")
print("="*70)

# 추론 스레드 시작
inference_worker = threading.Thread(target=inference_thread, daemon=True)
inference_worker.start()

try:
    while True:
        ret, frame = cap.read()
        if not ret:
            print("❌ 프레임을 읽을 수 없습니다")
            break
        
        frame_count += 1
        current_time = time.time()
        
        # 일정 간격마다 추론 큐에 프레임 추가
        if current_time - last_inference_time >= FRAME_INTERVAL:
            # 큐가 가득 차면 오래된 프레임 제거
            if frame_queue.full():
                try:
                    frame_queue.get_nowait()
                except Empty:
                    pass
            
            # 새 프레임 추가
            try:
                frame_queue.put_nowait((frame.copy(), frame_count))
            except:
                pass  # 큐가 가득 차면 스킵
        
        # 화면에 프레임 표시 (GUI 사용 시에만)
        if USE_DISPLAY:
            display_frame = frame.copy()
            
            # OpenCV BGR → RGB 변환
            display_rgb = cv2.cvtColor(display_frame, cv2.COLOR_BGR2RGB)
            pil_img = Image.fromarray(display_rgb)
            draw = ImageDraw.Draw(pil_img)
            
            # 한글 텍스트 그리기
            #text1 = f"프레임: {frame_count} | 추론: {inference_count}"
            text2 = f"답변: {last_answer[:60]}"
            
            # 텍스트 배경 (가독성 향상)
            draw.rectangle([(5, 5), (1270, 120)], fill=(0, 0, 0, 180))
            
            # 텍스트 그리기
            #draw.text((15, 15), text1, font=font_large, fill=(0, 255, 0))
            draw.text((15, 65), text2, font=font_small, fill=(0, 255, 255))
            
            # RGB → BGR 변환 후 표시
            display_frame = cv2.cvtColor(np.array(pil_img), cv2.COLOR_RGB2BGR)
            cv2.namedWindow('Webcam VLM', cv2.WINDOW_NORMAL)
            cv2.setWindowProperty('Webcam VLM', cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)
            cv2.imshow('Webcam VLM', display_frame)
            
            # ESC 키로 종료
            if cv2.waitKey(1) & 0xFF == 27:
                print("\n종료합니다...")
                break
        else:
            # GUI 없이 실행 시 Ctrl+C로만 종료 가능
            time.sleep(0.001)

except KeyboardInterrupt:
    print("\n\n사용자에 의해 중단되었습니다")

finally:
    # 스레드 종료
    stop_event.set()
    frame_queue.put(None)  # 종료 신호
    inference_worker.join(timeout=2)
    
    cap.release()
    if USE_DISPLAY:
        cv2.destroyAllWindows()
    print("\n✅ 웹캠 종료")
    print("="*70)
