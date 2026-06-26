#!/usr/bin/env python3
"""VILA VLM 추론 워커 — vila conda 환경에서 subprocess로 실행."""

from __future__ import annotations

import json
import os
import re
import sys
import time
from typing import Any

# ps3 import 우회 패치
class DummyPS3:
    def __getattr__(self, name):
        return lambda *args, **kwargs: None


sys.modules.setdefault("ps3", DummyPS3())


def _emit(payload: dict[str, Any]) -> None:
    sys.stdout.write(json.dumps(payload, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def _log(level: str, message: str) -> None:
    _emit({"type": "log", "level": level, "message": message})


def _load_model(cfg: dict[str, Any]) -> tuple[Any, Any, Any, Any]:
    import torch
    import torch.nn as nn
    from PIL import Image
    from peft import PeftModel
    from transformers import AutoTokenizer

    vila_path = cfg["vila_path"]
    if vila_path not in sys.path:
        sys.path.insert(0, vila_path)

    from llava.mm_utils import process_images
    from llava.model.language_model.llava_llama import LlavaConfig, LlavaLlamaModel

    class CustomMMProjector(nn.Module):
        def __init__(self) -> None:
            super().__init__()
            self.proj = nn.Sequential(
                nn.Linear(1152, 1344),
                nn.GELU(),
                nn.Linear(1344, 1536),
                nn.LayerNorm(1536),
            )

        def forward(self, x):
            return self.proj(x)

    base_model = cfg["base_model"]
    checkpoint_dir = cfg["checkpoint_dir"]
    mm_projector_pt = os.path.join(checkpoint_dir, "mm_projector.pt")

    if torch.cuda.is_available():
        device = torch.device("cuda:0")
        _log("ok", "GPU 사용 가능")
    else:
        device = torch.device("cpu")
        _log("warn", "GPU 사용 불가, CPU 모드")

    _log("info", "VILA 모델 로드 중...")
    config = LlavaConfig.from_pretrained(base_model)
    config.resume_path = base_model
    config.llm_cfg = os.path.join(base_model, "llm")
    config.vision_tower_cfg = os.path.join(base_model, "vision_tower")
    config.mm_projector_cfg = os.path.join(base_model, "mm_projector")
    config.image_aspect_ratio = "pad"
    config.ps3 = False
    config.s2 = False
    config.dynamic_s2 = False

    for attr in ("temperature", "top_p", "top_k"):
        if hasattr(config, attr):
            delattr(config, attr)

    model = LlavaLlamaModel(config=config)
    _log("ok", "Model structure loaded")

    state = torch.load(mm_projector_pt, map_location="cpu")
    custom_proj = CustomMMProjector()
    custom_proj.load_state_dict(state)
    model.mm_projector = custom_proj
    _log("ok", "MM Projector loaded")

    tokenizer = AutoTokenizer.from_pretrained(os.path.join(base_model, "llm"), trust_remote_code=True)
    if "<image>" not in tokenizer.get_vocab():
        tokenizer.add_tokens(["<image>"], special_tokens=True)
    model.resize_token_embeddings(len(tokenizer))

    image_token_id = tokenizer.convert_tokens_to_ids("<image>")
    model.media_token_ids = {"image": image_token_id}
    tokenizer.media_token_ids = {"image": image_token_id}
    config.media_token_ids = {"image": image_token_id}
    model.tokenizer = tokenizer
    _log("ok", f"Tokenizer loaded (image token: {image_token_id})")

    if cfg.get("load_lora", True) and os.path.exists(checkpoint_dir):
        try:
            model.llm = PeftModel.from_pretrained(model.llm, checkpoint_dir)
            if hasattr(model.llm, "base_model") and hasattr(model.llm.base_model, "model"):
                if hasattr(model.llm.base_model.model, "model"):
                    model.llm.model = model.llm.base_model.model.model
            _log("ok", "LoRA loaded")
        except Exception as e:  # noqa: BLE001
            _log("warn", f"LoRA 로드 실패: {e}")
    else:
        _log("warn", "LoRA 로드 건너뜀")

    dtype = torch.float32 if device.type == "cpu" else torch.bfloat16
    model = model.to(device, dtype=dtype)

    if hasattr(model, "encoders"):
        for enc_name in model.encoders:
            model.encoders[enc_name].end_tokens = None

    model.eval()
    _log("ok", f"Model ready on {device}")

    runtime = {
        "device": device,
        "dtype": dtype,
        "config": config,
        "process_images": process_images,
        "Image": Image,
    }
    return model, tokenizer, config, runtime


def _infer(
    model: Any,
    tokenizer: Any,
    config: Any,
    runtime: dict[str, Any],
    image_path: str,
    question: str,
    max_new_tokens: int,
) -> tuple[str, float]:
    import torch

    device = runtime["device"]
    dtype = runtime["dtype"]
    pil_image = runtime["Image"].open(image_path).convert("RGB")
    image_tensor = runtime["process_images"]([pil_image], model.get_vision_tower().image_processor, config)
    image_tensor = image_tensor.to(device, dtype=dtype)

    prompt = f"<image>\n질문: {question}\n답변:"
    input_ids = tokenizer(prompt, return_tensors="pt").input_ids.to(device)
    media = {"image": [image_tensor[0]]}
    media_config = {"image": {}}

    started = time.time()
    with torch.no_grad():
        outputs = model.generate(
            input_ids,
            media=media,
            media_config=media_config,
            max_new_tokens=max_new_tokens,
            do_sample=False,
            pad_token_id=tokenizer.eos_token_id,
            eos_token_id=tokenizer.eos_token_id,
        )
    inference_time = time.time() - started

    result = tokenizer.decode(outputs[0], skip_special_tokens=True)
    if "답변:" in result:
        result = result.split("답변:")[-1].strip()

    sentences = re.split(r"([.!?]\s+)", result)
    if len(sentences) >= 2:
        answer = sentences[0] + sentences[1].strip()
    else:
        answer = result

    return answer, inference_time


def main() -> None:
    try:
        cfg = json.loads(os.environ.get("VLM_WORKER_CONFIG", "{}"))
    except json.JSONDecodeError:
        _emit({"type": "fatal", "message": "invalid VLM_WORKER_CONFIG"})
        sys.exit(1)

    question = cfg.get("question", "이 영상에서 무엇을 볼 수 있나요?")
    max_new_tokens = int(cfg.get("max_new_tokens", 25))
    _log("info", f"질문: {question}")

    try:
        model, tokenizer, config, runtime = _load_model(cfg)
    except Exception as e:  # noqa: BLE001
        _emit({"type": "fatal", "message": f"model load failed: {e}"})
        sys.exit(1)

    _emit({"type": "ready"})

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        try:
            req = json.loads(line)
        except json.JSONDecodeError:
            _emit({"type": "error", "message": "invalid json request"})
            continue

        op = req.get("op")
        if op == "shutdown":
            break
        if op == "ping":
            _emit({"type": "pong"})
            continue
        if op != "infer":
            _emit({"type": "error", "message": f"unknown op: {op}"})
            continue

        req_id = req.get("id")
        frame_num = req.get("frame_num", 0)
        image_path = req.get("path", "")
        if not image_path or not os.path.isfile(image_path):
            _emit({"type": "error", "id": req_id, "message": f"image not found: {image_path}"})
            continue

        try:
            answer, inference_time = _infer(
                model,
                tokenizer,
                config,
                runtime,
                image_path,
                question,
                max_new_tokens,
            )
            _emit(
                {
                    "type": "result",
                    "id": req_id,
                    "frame_num": frame_num,
                    "answer": answer,
                    "inference_time": round(inference_time, 2),
                }
            )
        except Exception as e:  # noqa: BLE001
            _emit({"type": "error", "id": req_id, "message": str(e)})


if __name__ == "__main__":
    main()
