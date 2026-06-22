from contextlib import asynccontextmanager
from fastapi import FastAPI, Request, HTTPException, WebSocket, WebSocketDisconnect, UploadFile, File, Form, BackgroundTasks
import httpx
import logging
from logging.handlers import RotatingFileHandler
from qdrant_client import QdrantClient
from qdrant_client.models import Distance, VectorParams, PointStruct, Filter, FieldCondition, MatchValue, MatchText
import uuid
import time
import asyncio
import os
import subprocess
import psutil
import signal
import json
import tempfile
import shutil
import base64
import re
import hashlib
import wave
import audioop
from pathlib import Path
try:
    from ftlangdetect import detect as ft_detect  # type: ignore
except Exception:
    ft_detect = None  # type: ignore
try:
    from langdetect import detect as ld_detect, DetectorFactory  # type: ignore
    DetectorFactory.seed = 0
except Exception:
    ld_detect = None  # type: ignore
try:
    from rewrite import rewrite_prompt  # type: ignore
except Exception as e:
    rewrite_import_error = e
    rewrite_prompt = None  # type: ignore

SERVICE_START_TIME = time.time()
MODEL_WARMED = False

# Config
DEFAULT_EMBED_MODEL = os.getenv("EMBED_MODEL", "nomic-embed-text")  # Fast dedicated embedding model
FALLBACK_EMBED_MODEL = os.getenv("FALLBACK_EMBED_MODEL", "llama3.2:1b")  # Fallback if nomic fails
VASTAI_ENABLED = os.getenv("VASTAI_ENABLED", "false").lower() == "true"
DEFAULT_CHAT_MODEL = os.getenv("CHAT_MODEL", "llama3.1:8b" if VASTAI_ENABLED else "llama3.2:3b")
FALLBACK_CHAT_MODEL = os.getenv("FALLBACK_CHAT_MODEL", "llama3.2:3b")  # Fast local fallback
QUERY_NORMALIZATION_MODEL = os.getenv("QUERY_NORMALIZATION_MODEL", DEFAULT_CHAT_MODEL)
EMBED_TIMEOUT_SEC = float(os.getenv("EMBED_TIMEOUT", "25"))  # 25s handles nomic-embed-text cold-start (model load ~20s)
MAX_EMBED_CHARS = int(os.getenv("MAX_EMBED_CHARS", "1800"))
EMBED_CONCURRENCY = int(os.getenv("EMBED_CONCURRENCY", "8"))  # Max concurrent embed endpoint calls
EMBED_BATCH_SIZE = int(os.getenv("EMBED_BATCH_SIZE", "100"))  # Items per /api/embed batch call

# Backend type configuration
# IMPORTANT PORT MAPPING (do not swap during debugging):
# - 127.0.0.1:11434 => local Ollama on this server
# - 127.0.0.1:11435 => SSH tunnel endpoint that forwards to Vast.ai Ollama (remote 11434)
AI_BACKEND_TYPE = os.getenv("AI_BACKEND_TYPE", "ollama")  # ollama or llamacpp
OLLAMA_URL_LOCAL = os.getenv("OLLAMA_URL", "http://127.0.0.1:11434")  # Local Ollama
OLLAMA_URL_VASTAI = "http://127.0.0.1:11435"  # vast.ai via SSH tunnel
OLLAMA_URL = OLLAMA_URL_LOCAL  # Default to local for embeddings, health checks, etc.
LARAVEL_WIDGET_BASE_URL = os.getenv("LARAVEL_WIDGET_BASE_URL", "https://ai-chat.support")
LLAMACPP_BINARY = os.getenv("LLAMACPP_BINARY", "/var/www/clients/client1/web64/web/llama.cpp/build/bin/llama-cli")
LLAMACPP_SERVER_BINARY = os.getenv("LLAMACPP_SERVER_BINARY", "/var/www/clients/client1/web64/web/llama.cpp/build/bin/llama-server")
LLAMACPP_SERVER_PORT = int(os.getenv("LLAMACPP_SERVER_PORT", "8112"))
LLAMACPP_SERVER_URL = f"http://localhost:{LLAMACPP_SERVER_PORT}"
MODELS_DIR = os.getenv("MODELS_DIR", "/var/www/clients/client1/web64/web/models")

# Models hosted on vast.ai (use tunnel)
# NOTE: Do NOT add nomic-embed-text here. It runs in 62ms on local CPU.
# Tunnel latency (~1500ms) would make it 27x slower via Vast.ai.
# GPU routing only helps for large LLMs where compute >> tunnel overhead.
#
# VRAM budget (RTX 5060 Ti — 16,311 MiB):
#   Whisper large-v3:            ~3,230 MiB  (always resident — personal assistant STT)
#   Ollama model cache:         ~6,000–8,000 MiB  depending on loaded model
#   Free headroom:              ~4,500–7,000 MiB
#
# Priority order (Ollama only loads one large model at a time via LRU eviction):
#   1. llama3.1:8b  — primary chat model
#   2. llama3.2:3b                                  — fast fallback
#   3. mistral-nemo                                 — crawl/indexing (lower priority)
#
# Running llama3.1:8b + Whisper simultaneously ≈ 8.2 GB → fits comfortably.
# Running mistral-nemo + Whisper simultaneously ≈ 10.2 GB → fits with ~6 GB free.
# Do NOT run all three large models simultaneously — Ollama handles LRU eviction.
VASTAI_MODELS = [
    "deepseek-r1:8b",
    "llama3.1:8b",
    "mistral-nemo",
    "mistral-nemo:latest",
]

DEVANAGARI_RE = re.compile(r"[\u0900-\u097F]")
ORIYA_RE = re.compile(r"[\u0B00-\u0B7F]")
HINGLISH_HINTS = {
    "hai", "haan", "nahi", "kya", "mera", "meri", "mere", "mujhe", "karna",
    "karni", "karna hai", "kitna", "kab", "kaise", "kyu", "kyon", "chahiye",
    "hoga", "hogi", "honge", "kripya", "please bata", "sampark", "madad",
}
ORIYA_LATIN_HINTS = {
    "kana", "kemiti", "achhi", "achi", "mu", "mate", "mo", "mora", "aapankar", "apankara", "darkar", "kariba",
    "kahinki", "odia", "oriya", "bhala", "seba", "samparka",
}

AVAILABILITY_QUESTION_HINTS = {
    "kon achhi", "kana achhi", "kya hai", "kaun sa hai", "available", "offer", "offer karte",
}


def _strip_reasoning_blocks(text: str) -> str:
    cleaned = re.sub(r"<think>.*?</think>\s*", "", text or "", flags=re.IGNORECASE | re.DOTALL)
    cleaned = re.sub(r"</?think>", "", cleaned, flags=re.IGNORECASE)
    return cleaned.strip()


def _sanitize_chat_result(result: dict) -> dict:
    message = result.get("message")
    if isinstance(message, dict):
        content = str(message.get("content", "") or "")
        cleaned = _strip_reasoning_blocks(content)
        if cleaned != content:
            message["content"] = cleaned
            result.setdefault("meta", {})["reasoning_stripped"] = True
    return result


def _should_disable_thinking(model: str) -> bool:
    normalized = (model or "").strip().lower()
    return normalized.startswith("deepseek-r1")

# Dedicated model/URL for crawl suggestion + structured extraction.
# These crawl tasks must stay on vast.ai for better accuracy and consistency.
# Allowed models are intentionally restricted to the larger remote models.
CRAWL_ALLOWED_MODELS = [
    "llama3.1:8b",
    "mistral-nemo",
    "mistral-nemo:latest",
]
CRAWL_LLM_MODEL = os.getenv("CRAWL_LLM_MODEL", "mistral-nemo")
CRAWL_LLM_URL   = os.getenv("CRAWL_LLM_URL",   OLLAMA_URL_VASTAI)  # vast.ai tunnel only

# Personal Assistant voice service configuration (typically tunneled from vast.ai)
PERSONAL_ASSISTANT_WHISPER_URL = os.getenv("PERSONAL_ASSISTANT_WHISPER_URL", "http://127.0.0.1:18081/transcribe")
# XTTS and Indic TTS removed — edge-tts is the sole TTS provider (no GPU server needed).
PERSONAL_ASSISTANT_TIMEOUT_SEC = float(os.getenv("PERSONAL_ASSISTANT_TIMEOUT_SEC", "60"))
PERSONAL_ASSISTANT_MAX_AUDIO_MB = int(os.getenv("PERSONAL_ASSISTANT_MAX_AUDIO_MB", "20"))
LOCAL_WHISPER_MODEL = os.getenv("LOCAL_WHISPER_MODEL", "large-v3")
LOCAL_WHISPER_CACHE_DIR = os.getenv("LOCAL_WHISPER_CACHE_DIR", "/tmp/ai_backend_whisper_cache")
PERSONAL_ASSISTANT_ENABLE_LOCAL_FALLBACK = os.getenv("PERSONAL_ASSISTANT_ENABLE_LOCAL_FALLBACK", "true").lower() == "true"
VIDEO_JOB_DIR = Path(os.getenv("VIDEO_JOB_DIR", "/var/www/clients/client1/web64/web/laravel/storage/app/video-generation/jobs"))
VIDEO_OUTPUT_DIR = Path(os.getenv("VIDEO_OUTPUT_DIR", "/var/www/clients/client1/web64/web/laravel/storage/app/public/video-generation/output"))
VIDEO_TEMP_DIR = Path(os.getenv("VIDEO_TEMP_DIR", "/var/www/clients/client1/web64/web/laravel/storage/app/video-generation/tmp"))
VIDEO_PUBLIC_BASE_URL = os.getenv(
    "VIDEO_PUBLIC_BASE_URL",
    f"{LARAVEL_WIDGET_BASE_URL.rstrip('/')}/storage/video-generation/output",
)
VIDEO_MAX_DURATION_SEC = int(os.getenv("VIDEO_MAX_DURATION_SEC", "180"))
VIDEO_FPS = int(os.getenv("VIDEO_FPS", "24"))
VIDEO_FONT_FILE = os.getenv("VIDEO_FONT_FILE", "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")

# ComfyUI (AnimateDiff) — tunneled from Vast.ai at 127.0.0.1:8188 via local port 18084
COMFYUI_URL = os.getenv("COMFYUI_URL", "http://127.0.0.1:18084")
COMFYUI_CHECKPOINT = os.getenv("COMFYUI_CHECKPOINT", "v1-5-pruned-emaonly.safetensors")
COMFYUI_MOTION_MODEL = os.getenv("COMFYUI_MOTION_MODEL", "mm_sd_v15_v2.ckpt")
COMFYUI_FRAMES = int(os.getenv("COMFYUI_FRAMES", "24"))      # frames per clip (24 @ 8fps = 3s)
COMFYUI_RENDER_FPS = int(os.getenv("COMFYUI_RENDER_FPS", "8"))
COMFYUI_STEPS = int(os.getenv("COMFYUI_STEPS", "20"))
COMFYUI_DENOISE_T2V = float(os.getenv("COMFYUI_DENOISE_T2V", "1.0"))
COMFYUI_DENOISE_I2V = float(os.getenv("COMFYUI_DENOISE_I2V", "0.65"))   # raise slightly from 0.60 → more motion freedom
COMFYUI_MOTION_LORA = os.getenv("COMFYUI_MOTION_LORA", "")                # e.g. "v2_lora_ZoomIn.ckpt"  (empty = disabled)
COMFYUI_MOTION_LORA_STRENGTH = float(os.getenv("COMFYUI_MOTION_LORA_STRENGTH", "0.7"))
COMFYUI_WIDTH = int(os.getenv("COMFYUI_WIDTH", "512"))
COMFYUI_HEIGHT = int(os.getenv("COMFYUI_HEIGHT", "512"))
COMFYUI_POLL_INTERVAL = float(os.getenv("COMFYUI_POLL_INTERVAL", "3.0"))
COMFYUI_TIMEOUT = int(os.getenv("COMFYUI_TIMEOUT", "300"))
COMFYUI_CFG = float(os.getenv("COMFYUI_CFG", "8.5"))     # raised from 7.5 — stronger prompt adherence
COMFYUI_NEG_PROMPT = os.getenv(
    "COMFYUI_NEG_PROMPT",
    "blurry, low quality, nsfw, deformed, watermark, text overlay, ugly, duplicate, extra limbs, "
    "color artifacts, psychedelic, rainbow glitch, neon artifacts, oversaturated, color bleeding, "
    "chromatic aberration, flickering colors, temporal inconsistency, noisy, grainy, pixelated, "
    "color noise, color shift, color smear, color fringe, color burst, color halos",
)
# Global style text prepended to every scene prompt to enforce cinematic look
COMFYUI_GLOBAL_STYLE_PROMPT = os.getenv(
    "COMFYUI_GLOBAL_STYLE_PROMPT",
    "cinematic photography, photorealistic, sharp focus, professional studio lighting, "
    "smooth natural motion, film quality, 8k resolution, color graded, "
    "no color artifacts, temporally coherent",
)
COMFYUI_UPSCALE_MODEL = os.getenv("COMFYUI_UPSCALE_MODEL", "RealESRGAN_x4plus.pth")
# GPU throttle: seconds to sleep between ComfyUI scenes (frees GPU for Ollama chat)
COMFYUI_INTER_SCENE_DELAY = float(os.getenv("COMFYUI_INTER_SCENE_DELAY", "3.0"))
# Maximum GPU utilisation (%) to wait for before starting next ComfyUI render
COMFYUI_GPU_UTIL_THRESHOLD = int(os.getenv("COMFYUI_GPU_UTIL_THRESHOLD", "75"))

# ── Avatar / Lip-sync ────────────────────────────────────────────────────────
# Generic lip-sync HTTP service on vast.ai, port 18085 via SSH tunnel.
# Keep disabled by default until a lightweight provider (e.g. Wav2Lip service)
# is actually deployed and healthy.
LIPSYNC_URL     = os.getenv("LIPSYNC_URL",     "http://127.0.0.1:18085")
LIPSYNC_ENABLED = os.getenv("LIPSYNC_ENABLED", "false").lower() == "true"
LIPSYNC_MODE    = os.getenv("LIPSYNC_MODE",    "auto").strip().lower()
LIPSYNC_LOCAL_FPS = int(os.getenv("LIPSYNC_LOCAL_FPS", "12"))
AVATAR_CACHE_DIR  = VIDEO_TEMP_DIR.parent / "avatar-cache"

AVATAR_CATALOG: list[dict] = [
    # ── Female ────────────────────────────────────────────────────────────────
    {"id": "f1", "name": "Priya",  "gender": "female", "style": "professional",
     "image_url": "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&h=400&fit=crop&crop=top"},
    {"id": "f2", "name": "Neha",   "gender": "female", "style": "casual",
     "image_url": "https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&h=400&fit=crop&crop=top"},
    {"id": "f3", "name": "Emma",   "gender": "female", "style": "corporate",
     "image_url": "https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&h=400&fit=crop&crop=top"},
    {"id": "f4", "name": "Sara",   "gender": "female", "style": "modern",
     "image_url": "https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400&h=400&fit=crop&crop=top"},
    # ── Male ──────────────────────────────────────────────────────────────────
    {"id": "m1", "name": "Arjun",  "gender": "male",   "style": "professional",
     "image_url": "https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=400&h=400&fit=crop&crop=top"},
    {"id": "m2", "name": "Raj",    "gender": "male",   "style": "casual",
     "image_url": "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=400&fit=crop&crop=top"},
    {"id": "m3", "name": "James",  "gender": "male",   "style": "corporate",
     "image_url": "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&h=400&fit=crop&crop=top"},
    {"id": "m4", "name": "Marcus", "gender": "male",   "style": "modern",
     "image_url": "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&h=400&fit=crop&crop=top"},
]

AVATAR_SIZE_FRACTIONS: dict[str, float] = {"small": 0.22, "medium": 0.29, "large": 0.38}

def get_ollama_url(model: str) -> str:
    """Get the appropriate Ollama URL based on the model"""
    if not VASTAI_ENABLED:
        return OLLAMA_URL_LOCAL

    return OLLAMA_URL_VASTAI if model in VASTAI_MODELS else OLLAMA_URL_LOCAL


def get_embedding_ollama_url(model: str) -> str:
    """Embeddings must stay local so Qdrant retrieval does not depend on Vast.ai."""
    return OLLAMA_URL_LOCAL


def resolve_crawl_model(requested_model: str | None = None) -> str:
    model = (requested_model or CRAWL_LLM_MODEL).strip()
    if model not in CRAWL_ALLOWED_MODELS:
        logging.warning(f"crawl model '{model}' is not allowed; forcing {CRAWL_LLM_MODEL}")
        return CRAWL_LLM_MODEL
    return model


def _detect_query_language(query: str) -> dict:
    text = (query or "").strip()
    if text == "":
        return {
            "language": "en",
            "confidence": 1.0,
            "source": "empty",
            "script": "latin",
        }

    if ORIYA_RE.search(text):
        return {"language": "or", "confidence": 0.99, "source": "heuristic_script", "script": "oriya"}

    if DEVANAGARI_RE.search(text):
        return {"language": "hi", "confidence": 0.98, "source": "heuristic_script", "script": "devanagari"}

    lowered = text.lower()
    hinglish_hits = sum(1 for hint in HINGLISH_HINTS if hint in lowered)
    oriya_hits = sum(1 for hint in ORIYA_LATIN_HINTS if hint in lowered)
    ascii_only = all(ord(ch) < 128 for ch in lowered)

    if oriya_hits >= 2:
        return {"language": "or", "confidence": 0.72, "source": "heuristic_latin", "script": "latin"}

    if hinglish_hits >= 2:
        return {"language": "hi", "confidence": 0.7, "source": "heuristic_latin", "script": "latin"}

    if ft_detect is not None:
        try:
            detected = ft_detect(lowered)
            lang = str(detected.get("lang") or detected.get("language") or "").strip().lower()
            score = float(detected.get("score") or detected.get("confidence") or 0.0)
            if lang:
                return {
                    "language": lang,
                    "confidence": score,
                    "source": "ftlangdetect",
                    "script": "latin",
                }
        except Exception as exc:
            logging.warning("ftlangdetect failed for query detection: %s", exc)

    if ld_detect is not None:
        try:
            lang = str(ld_detect(lowered) or "").strip().lower()
            if lang:
                return {
                    "language": lang,
                    "confidence": 0.65,
                    "source": "langdetect",
                    "script": "latin",
                }
        except Exception as exc:
            logging.warning("langdetect failed for query detection: %s", exc)

    if ascii_only:
        return {"language": "en", "confidence": 0.55, "source": "heuristic_ascii", "script": "latin"}

    return {"language": "unknown", "confidence": 0.2, "source": "heuristic_fallback", "script": "mixed"}


def _should_normalize_query(query: str, detection: dict) -> bool:
    text = (query or "").strip()
    if text == "":
        return False

    language = str(detection.get("language") or "").lower()
    confidence = float(detection.get("confidence") or 0.0)
    script = str(detection.get("script") or "latin").lower()

    if script in {"devanagari", "oriya", "mixed"}:
        return True

    if language in {"hi", "or"} and confidence >= 0.35:
        return True

    lowered = text.lower()
    hint_hits = sum(1 for hint in HINGLISH_HINTS if hint in lowered) + sum(1 for hint in ORIYA_LATIN_HINTS if hint in lowered)
    return hint_hits >= 2


def _canonicalize_multilingual_support_query(query: str, detection: dict) -> str:
    original = (query or "").strip()
    if original == "":
        return original

    lowered = re.sub(r"\s+", " ", re.sub(r"[^\w\s:-]", " ", original.lower())).strip()
    language = str(detection.get("language") or "").lower()

    has_availability_intent = any(hint in lowered for hint in AVAILABILITY_QUESTION_HINTS)
    has_subscription_intent = "subscription" in lowered or "plan" in lowered or "pricing" in lowered
    has_trial_intent = "trial" in lowered or "demo" in lowered
    has_price_intent = any(term in lowered for term in ["price", "cost", "fee", "charge", "rate", "pricing"])

    if language in {"or", "hi"}:
        if has_subscription_intent and has_availability_intent:
            return "what subscription plans do you offer"
        if has_trial_intent and has_availability_intent:
            return "do you offer a free trial"
        if has_subscription_intent and has_price_intent:
            return "what is the pricing for your subscription plans"

    return original


async def _normalize_query_to_english(query: str, model: str, use_vastai: bool = True) -> str:
    ollama_url = OLLAMA_URL_VASTAI if (use_vastai and VASTAI_ENABLED) else get_ollama_url(model)
    messages = [
        {
            "role": "system",
            "content": (
                "You normalize multilingual customer-support queries for semantic retrieval.\n"
                "Rules:\n"
                "- Convert the user query into concise canonical English for search.\n"
                "- Questions asking what is available or what exists must become offering/availability queries, not recommendation queries.\n"
                "- Example: 'aapankar subscription plan kon achhi?' -> 'what subscription plans do you offer'.\n"
                "- Example: 'free trial achhi ki?' -> 'do you offer a free trial'.\n"
                "- Example: 'pricing kete?' -> 'what is the pricing'.\n"
                "- Preserve product names, service names, person names, test names, IDs, order numbers, dates, phone numbers, and quantities exactly.\n"
                "- Do not answer the question.\n"
                "- Do not explain your reasoning.\n"
                "- If the query is already clear English, return it unchanged.\n"
                "- Return ONLY the normalized English query text."
            ),
        },
        {"role": "user", "content": query},
    ]

    async with httpx.AsyncClient(timeout=45.0) as client:
        payload = {
            "model": model,
            "messages": messages,
            "stream": False,
            "options": {
                "temperature": 0.0,
                "num_predict": 96,
            },
        }
        if _should_disable_thinking(model):
            payload["think"] = False
        resp = await client.post(f"{ollama_url}/api/chat", json=payload)
        if resp.status_code != 200:
            raise RuntimeError(f"Query normalization HTTP {resp.status_code}: {resp.text}")
        result = _sanitize_chat_result(resp.json())
        if isinstance(result, dict) and result.get("error"):
            raise RuntimeError(f"Query normalization error: {result.get('error')}")
        content = ((result.get("message") or {}).get("content") or "").strip()
        content = re.sub(r"\s+", " ", content)
        return content or query.strip()

# Pre-configured GGUF models
GGUF_MODELS = {
    "bartowski/Llama-3.2-3B-Instruct-GGUF:Llama-3.2-3B-Instruct-Q4_K_M.gguf": {
        "url": "https://huggingface.co/bartowski/Llama-3.2-3B-Instruct-GGUF/resolve/main/Llama-3.2-3B-Instruct-Q4_K_M.gguf",
        "filename": "Llama-3.2-3B-Instruct-Q4_K_M.gguf"
    },
    "bartowski/Llama-3.2-1B-Instruct-GGUF:Llama-3.2-1B-Instruct-Q4_K_M.gguf": {
        "url": "https://huggingface.co/bartowski/Llama-3.2-1B-Instruct-GGUF/resolve/main/Llama-3.2-1B-Instruct-Q4_K_M.gguf",
        "filename": "Llama-3.2-1B-Instruct-Q4_K_M.gguf"
    },
    "bartowski/Llama-3.2-3B-Instruct-GGUF:Llama-3.2-3B-Instruct-Q8_0.gguf": {
        "url": "https://huggingface.co/bartowski/Llama-3.2-3B-Instruct-GGUF/resolve/main/Llama-3.2-3B-Instruct-Q8_0.gguf",
        "filename": "Llama-3.2-3B-Instruct-Q8_0.gguf"
    },
    "custom/Llama-3.2-3B-Instruct-Q8_0-Custom": {
        "url": "",  # Local custom model - no download needed
        "filename": "Llama-3.2-3B-Instruct-Q8_0-custom.gguf"
    }
}

# Ensure models directory exists
Path(MODELS_DIR).mkdir(exist_ok=True)
VIDEO_JOB_DIR.mkdir(parents=True, exist_ok=True)
VIDEO_OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
VIDEO_TEMP_DIR.mkdir(parents=True, exist_ok=True)

# Logging setup
LOG_DIR = Path(__file__).parent / "logs"
LOG_DIR.mkdir(exist_ok=True)
LOG_FILE = LOG_DIR / "fastapi.log"

logging.basicConfig(
    level=logging.INFO,
    format="[%(asctime)s] %(levelname)s %(message)s",
    handlers=[
        logging.StreamHandler(),
        RotatingFileHandler(LOG_FILE, maxBytes=5 * 1024 * 1024, backupCount=5, encoding="utf-8"),
    ],
)

# Global variable to track llama-server process
llamacpp_server_process = None
current_llamacpp_model = None

# Process management config
MAX_OLLAMA_RUNNER_CPU = float(os.getenv("MAX_OLLAMA_RUNNER_CPU", "200.0"))  # Observability only (not used for killing)
MAX_OLLAMA_RUNNER_TIME = int(os.getenv("MAX_OLLAMA_RUNNER_TIME", "300"))    # Max runtime in seconds (5 min)
PROCESS_CHECK_INTERVAL = int(os.getenv("PROCESS_CHECK_INTERVAL", "30"))     # Check every 30 seconds
VASTAI_HEALTHCHECK_ENABLED = VASTAI_ENABLED and os.getenv("VASTAI_HEALTHCHECK_ENABLED", "false").lower() == "true"
VASTAI_HEALTHCHECK_INTERVAL = int(os.getenv("VASTAI_HEALTHCHECK_INTERVAL", "30"))
VASTAI_TUNNEL_SCRIPT = os.getenv(
    "VASTAI_TUNNEL_SCRIPT",
    "/var/www/clients/client1/web64/web/scripts/start-ollama-tunnel.sh",
)
VASTAI_RESTART_COOLDOWN = int(os.getenv("VASTAI_RESTART_COOLDOWN", "120"))

_vastai_restart_lock = asyncio.Lock()
_vastai_last_restart = 0.0

embed_semaphore = asyncio.Semaphore(EMBED_CONCURRENCY)
# Only 1 ComfyUI render at a time — prevents GPU memory contention and
# keeps the GPU partly free for Ollama chat inference between scenes.
_comfyui_semaphore = asyncio.Semaphore(1)

@asynccontextmanager
async def lifespan(app: FastAPI):
    # ── Startup ──
    global MODEL_WARMED
    try:
        logging.info("Cleaning up any stuck ollama processes...")
        cleanup_stuck_ollama_processes()

        asyncio.create_task(periodic_process_cleanup())
        logging.info("Started background process monitoring")

        asyncio.create_task(periodic_vastai_healthcheck())
        logging.info("Started Vast.ai healthcheck monitor")

        asyncio.create_task(periodic_embed_keepalive())
        logging.info("Started embed keepalive task")

        logging.info("Warming up models...")
        async with httpx.AsyncClient(timeout=60.0) as client:
            await client.get(f"{OLLAMA_URL}/api/tags")

            embed_resp = await client.post(
                f"{OLLAMA_URL}/api/embeddings",
                json={"model": DEFAULT_EMBED_MODEL, "prompt": "warmup", "keep_alive": "24h"}
            )

            chat_url = get_ollama_url(DEFAULT_CHAT_MODEL)
            try:
                chat_resp = await client.post(
                    f"{chat_url}/api/chat",
                    json={
                        "model": DEFAULT_CHAT_MODEL,
                        "messages": [{"role": "user", "content": "warmup"}],
                        "stream": False
                    }
                )
                logging.info(f"Default chat model warmed: {DEFAULT_CHAT_MODEL} url={chat_url} status={chat_resp.status_code}")
            except Exception as ce:
                logging.warning(f"Default chat warm failed: {DEFAULT_CHAT_MODEL} url={chat_url} error={ce}")

            if FALLBACK_EMBED_MODEL != DEFAULT_EMBED_MODEL:
                try:
                    fallback_embed_resp = await client.post(
                        f"{OLLAMA_URL}/api/embeddings",
                        json={"model": FALLBACK_EMBED_MODEL, "prompt": "warmup"}
                    )
                    logging.info(f"Fallback embed model warmed: {FALLBACK_EMBED_MODEL} status={fallback_embed_resp.status_code}")
                except Exception as fe:
                    logging.warning(f"Fallback embed warm failed: {FALLBACK_EMBED_MODEL} error={fe}")

            if FALLBACK_CHAT_MODEL != DEFAULT_CHAT_MODEL:
                try:
                    fallback_chat_url = get_ollama_url(FALLBACK_CHAT_MODEL)
                    fallback_chat_resp = await client.post(
                        f"{fallback_chat_url}/api/chat",
                        json={
                            "model": FALLBACK_CHAT_MODEL,
                            "messages": [{"role": "user", "content": "warmup"}],
                            "stream": False,
                            "keep_alive": "0"  # Don't hold in RAM — nomic-embed-text has priority
                        }
                    )
                    logging.info(f"Fallback chat model verified (not held in RAM): {FALLBACK_CHAT_MODEL} url={fallback_chat_url} status={fallback_chat_resp.status_code}")
                except Exception as fc:
                    logging.warning(f"Fallback chat warm failed: {FALLBACK_CHAT_MODEL} error={fc}")

            MODEL_WARMED = True
            logging.info(
                "Models warmed up successfully: default_embed=%s default_chat=%s fallback_embed=%s fallback_chat=%s",
                DEFAULT_EMBED_MODEL,
                DEFAULT_CHAT_MODEL,
                FALLBACK_EMBED_MODEL if FALLBACK_EMBED_MODEL != DEFAULT_EMBED_MODEL else "(same)",
                FALLBACK_CHAT_MODEL if FALLBACK_CHAT_MODEL != DEFAULT_CHAT_MODEL else "(same)"
            )
    except Exception as e:
        logging.error(f"Model warmup failed: {str(e)}")

    yield  # Application runs here

    # ── Shutdown ──
    await stop_llamacpp_server()


app = FastAPI(lifespan=lifespan)
qdrant = QdrantClient(host="127.0.0.1", port=6333)
# OLLAMA_URL already defined at line 37 - don't override it

def _video_job_path(job_id: str) -> Path:
    return VIDEO_JOB_DIR / f"{job_id}.json"

def _utc_timestamp() -> str:
    return time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())

def _load_video_job(job_id: str):
    path = _video_job_path(job_id)
    if not path.exists():
        raise HTTPException(status_code=404, detail="Video job not found")
    return json.loads(path.read_text(encoding="utf-8"))

def _save_video_job(job: dict) -> dict:
    path = _video_job_path(str(job["job_id"]))
    path.write_text(json.dumps(job, ensure_ascii=False, indent=2), encoding="utf-8")
    return job

def _update_video_job(job_id: str, **fields):
    job = _load_video_job(job_id)
    job.update(fields)
    job["updated_at"] = _utc_timestamp()
    return _save_video_job(job)

def _video_dimensions(aspect_ratio: str):
    if aspect_ratio == "9:16":
        return (720, 1280)
    if aspect_ratio == "1:1":
        return (1080, 1080)
    return (1280, 720)


# Resolution presets: base ComfyUI generation is always 512×512 (SD1.5 optimal).
# For hd/fullhd the render pipeline adds RealESRGAN ×4 upscale automatically.
_QUALITY_PRESETS: dict[str, dict[str, tuple[int, int]]] = {
    "standard": {"16:9": (640, 360),  "9:16": (360, 640),  "1:1": (512, 512)},
    "hd":       {"16:9": (1280, 720), "9:16": (720, 1280), "1:1": (1080, 1080)},
    "fullhd":   {"16:9": (1920, 1080),"9:16": (1080, 1920),"1:1": (1440, 1440)},
}

def _output_dimensions(aspect_ratio: str, quality: str) -> tuple[int, int]:
    """Return target pixel dimensions from aspect ratio + quality preset.
    HD and Full HD automatically trigger RealESRGAN upscaling in the pipeline."""
    ratio = aspect_ratio if aspect_ratio in ("9:16", "1:1") else "16:9"
    return _QUALITY_PRESETS.get(quality, _QUALITY_PRESETS["hd"]).get(ratio, (1280, 720))

def _escape_drawtext(text: str) -> str:
    cleaned = (text or "").replace("\n", " ").replace("\r", " ").strip()
    cleaned = re.sub(r"\s+", " ", cleaned)
    cleaned = cleaned[:160] if len(cleaned) > 160 else cleaned
    return (
        cleaned.replace("\\", r"\\")
        .replace(":", r"\:")
        .replace("'", r"\'")
        .replace("%", r"\%")
    )


def _wrap_text_lines(text: str, fontsize: int = 36, video_width: int = 1280) -> list[str]:
    """Word-wrap text and return a list of FFmpeg drawtext-escaped lines.

    Each entry is a single line ready to embed in ``text='...'``.  Use one
    ``drawtext`` filter per line with incrementing Y offsets — FFmpeg's
    drawtext does NOT interpret ``\\n`` escape sequences in the text
    parameter when values are passed via subprocess args.
    """
    import textwrap as _textwrap

    # Average proportional-font character width ≈ 0.55× fontsize.
    # Use 82 % of video width so there is visible padding on both sides.
    max_chars = max(20, int(video_width * 0.82 / (fontsize * 0.55)))

    cleaned = re.sub(r"\s+", " ", (text or "").replace("\n", " ").replace("\r", " ").strip())
    cleaned = cleaned[:300]  # hard cap before wrapping

    lines = _textwrap.wrap(cleaned, width=max_chars) or [cleaned]

    escaped: list[str] = []
    for line in lines:
        escaped.append(
            line.replace("\\", r"\\")
            .replace(":", r"\:")
            .replace("'", r"\'")  
            .replace("%", r"\%")
        )
    return escaped


# Keep old name as alias so any other callers don't break
def _wrap_and_escape_drawtext(text: str, fontsize: int = 36, video_width: int = 1280) -> str:
    return r"\n".join(_wrap_text_lines(text, fontsize, video_width))


# ── Avatar helpers ──────────────────────────────────────────────────────────

async def _download_avatar_image(url: str, avatar_id: str) -> "Path | None":
    """Download avatar image from URL and cache it locally."""
    AVATAR_CACHE_DIR.mkdir(parents=True, exist_ok=True)
    url_hash = hashlib.md5(url.encode()).hexdigest()[:8]
    cached = AVATAR_CACHE_DIR / f"avatar_{avatar_id}_{url_hash}.jpg"
    if cached.exists():
        return cached
    try:
        async with httpx.AsyncClient(timeout=20, follow_redirects=True) as client:
            resp = await client.get(url)
            resp.raise_for_status()
            cached.write_bytes(resp.content)
            return cached
    except Exception as exc:
        logging.warning("Avatar download failed (%s): %s", url, exc)
        return None


def _create_circular_avatar_png(
    src_path: Path, dst_path: Path, size: int,
    border_color: str = "#4F8EF7", border_px: int = 5,
) -> bool:
    """Crop image to a circle with a coloured border ring and save as RGBA PNG."""
    try:
        from PIL import Image, ImageDraw  # type: ignore
        img = Image.open(src_path).convert("RGBA")
        img = img.resize((size, size), Image.LANCZOS)
        # Alpha circle mask
        mask = Image.new("L", (size, size), 0)
        ImageDraw.Draw(mask).ellipse((0, 0, size - 1, size - 1), fill=255)
        img.putalpha(mask)
        if border_px > 0:
            total = size + border_px * 2
            bg = Image.new("RGBA", (total, total), (0, 0, 0, 0))
            r = int(border_color[1:3], 16)
            g = int(border_color[3:5], 16)
            b = int(border_color[5:7], 16)
            ImageDraw.Draw(bg).ellipse((0, 0, total - 1, total - 1), fill=(r, g, b, 230))
            bg.paste(img, (border_px, border_px), img)
            bg.save(dst_path, "PNG")
        else:
            img.save(dst_path, "PNG")
        return True
    except Exception as exc:
        logging.warning("Avatar circle crop failed: %s", exc)
        return False


def _create_rounded_avatar_png(
    src_path: Path, dst_path: Path, w: int, h: int, radius: int = 20
) -> bool:
    """Crop image to a rounded rectangle and save as RGBA PNG."""
    try:
        from PIL import Image, ImageDraw  # type: ignore
        img = Image.open(src_path).convert("RGBA")
        img = img.resize((w, h), Image.LANCZOS)
        mask = Image.new("L", (w, h), 0)
        ImageDraw.Draw(mask).rounded_rectangle((0, 0, w - 1, h - 1), radius=radius, fill=255)
        img.putalpha(mask)
        img.save(dst_path, "PNG")
        return True
    except Exception as exc:
        logging.warning("Avatar rounded-rect crop failed: %s", exc)
        return False


async def _call_lipsync_service(avatar_image_path: Path, audio_path: Path) -> "Path | None":
    """Call external lip-sync service on vast.ai (port 18085 via SSH tunnel).
    Only active when LIPSYNC_ENABLED=true in the systemd Environment."""
    try:
        image_mime = "image/png" if avatar_image_path.suffix.lower() == ".png" else "image/jpeg"
        async with httpx.AsyncClient(timeout=180) as client:
            with open(avatar_image_path, "rb") as img_f, open(audio_path, "rb") as aud_f:
                resp = await client.post(
                    f"{LIPSYNC_URL}/generate",
                    files={
                        "image": (avatar_image_path.name, img_f, image_mime),
                        "audio": (audio_path.name, aud_f, "audio/mpeg"),
                    },
                )
            if resp.status_code == 200:
                result = avatar_image_path.with_suffix(".lipsync.mp4")
                result.write_bytes(resp.content)
                logging.info("Lip-sync service OK → %s", result.name)
                return result
            logging.warning(
                "Lip-sync service returned HTTP %s body=%s",
                resp.status_code,
                (resp.text or "")[:500],
            )
    except Exception as exc:
        logging.warning("Lip-sync service unavailable: %s", exc)
    return None


async def _extract_audio_energy_curve(audio_path: Path, points: int) -> list[float]:
    """Return a normalized RMS energy curve (0..1) sampled across an audio file."""
    points = max(1, points)
    tmp_wav = VIDEO_TEMP_DIR / f"lipsync_{uuid.uuid4().hex}.wav"
    try:
        await _run_command([
            "ffmpeg", "-y",
            "-i", str(audio_path),
            "-ac", "1",
            "-ar", "16000",
            str(tmp_wav),
        ])

        with wave.open(str(tmp_wav), "rb") as wav_file:
            sample_width = max(1, wav_file.getsampwidth())
            total_frames = max(1, wav_file.getnframes())
            chunk = max(1, total_frames // points)
            values: list[float] = []
            for _ in range(points):
                data = wav_file.readframes(chunk)
                if not data:
                    values.append(0.0)
                    continue
                values.append(float(audioop.rms(data, sample_width)))

        peak = max(values) if values else 0.0
        if peak <= 0.0:
            return [0.0 for _ in range(points)]

        normalized = [min(1.0, (v / peak) ** 0.65) for v in values]

        smoothed: list[float] = []
        for idx in range(len(normalized)):
            left = max(0, idx - 1)
            right = min(len(normalized), idx + 2)
            window = normalized[left:right]
            smoothed.append(sum(window) / max(1, len(window)))

        return smoothed
    except Exception as exc:
        logging.warning("Local lip-sync audio analysis failed: %s", exc)
        return [0.0 for _ in range(points)]
    finally:
        if tmp_wav.exists():
            tmp_wav.unlink(missing_ok=True)


async def _create_local_lipsync_video(
    avatar_png_path: Path,
    audio_path: Path,
    overlay_w: int,
    overlay_h: int,
) -> "Path | None":
    """Generate a lightweight talking-avatar WebM (with alpha) from one image + audio."""
    try:
        from PIL import Image, ImageDraw  # type: ignore
    except Exception as exc:
        logging.warning("Local lip-sync requires Pillow: %s", exc)
        return None

    duration = await _probe_duration_seconds(audio_path)
    if duration <= 0:
        return None

    fps = max(8, min(24, LIPSYNC_LOCAL_FPS))
    frame_count = max(1, int(duration * fps))
    energy_curve = await _extract_audio_energy_curve(audio_path, frame_count)

    tmp_dir = VIDEO_TEMP_DIR / f"lipsync_frames_{uuid.uuid4().hex}"
    tmp_dir.mkdir(parents=True, exist_ok=True)

    try:
        base = Image.open(avatar_png_path).convert("RGBA").resize((overlay_w, overlay_h), Image.LANCZOS)

        mouth_w = max(10, int(overlay_w * 0.14))
        mouth_min_h = max(2, int(overlay_h * 0.012))
        mouth_max_h = max(mouth_min_h + 2, int(overlay_h * 0.065))
        mouth_cx = overlay_w // 2
        mouth_cy = int(overlay_h * 0.56)

        for idx in range(frame_count):
            amp = energy_curve[idx] if idx < len(energy_curve) else 0.0
            frame = base.copy()
            draw = ImageDraw.Draw(frame, "RGBA")

            mouth_h = mouth_min_h + int((mouth_max_h - mouth_min_h) * amp)
            outer = (
                mouth_cx - mouth_w // 2,
                mouth_cy - mouth_h // 2,
                mouth_cx + mouth_w // 2,
                mouth_cy + mouth_h // 2,
            )
            # Subtle lip shadow only — avoid heavy dark blobs on portraits.
            draw.ellipse(outer, fill=(45, 14, 14, 28 + int(34 * amp)))

            inner_w = max(4, int(mouth_w * 0.58))
            inner_h = max(1, int(mouth_h * 0.52))
            inner = (
                mouth_cx - inner_w // 2,
                mouth_cy - inner_h // 2,
                mouth_cx + inner_w // 2,
                mouth_cy + inner_h // 2,
            )
            draw.ellipse(inner, fill=(62, 18, 18, 20 + int(26 * amp)))

            frame_path = tmp_dir / f"frame_{idx:05d}.png"
            frame.save(frame_path, "PNG")

        out_mov = AVATAR_CACHE_DIR / f"{avatar_png_path.stem}_{audio_path.stem}_local_lipsync_v2.mov"
        await _run_command([
            "ffmpeg", "-y",
            "-framerate", str(fps),
            "-i", str(tmp_dir / "frame_%05d.png"),
            "-c:v", "qtrle",
            "-pix_fmt", "argb",
            str(out_mov),
        ])
        return out_mov if out_mov.exists() else None
    except Exception as exc:
        logging.warning("Local lip-sync render failed: %s", exc)
        return None
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)


async def _build_lipsync_overlay(
    avatar_image_path: Path,
    audio_path: Path,
    overlay_w: int,
    overlay_h: int,
) -> "Path | None":
    """Resolve lip-sync overlay according to mode: off | remote | local | auto."""
    mode = LIPSYNC_MODE if LIPSYNC_MODE in {"off", "remote", "local", "auto"} else "local"
    if mode == "off" or not audio_path.exists():
        logging.info("Lip-sync skipped: mode=%s audio_exists=%s", mode, audio_path.exists())
        return None

    if mode in {"remote", "auto"}:
        remote = await _call_lipsync_service(avatar_image_path, audio_path)
        if remote and remote.exists():
            logging.info("Lip-sync overlay resolved via remote service")
            return remote
        logging.info("Lip-sync remote unavailable; mode=%s", mode)
        if mode == "remote":
            return None

    if mode in {"local", "auto"}:
        local = await _create_local_lipsync_video(avatar_image_path, audio_path, overlay_w, overlay_h)
        if local and local.exists():
            logging.info("Lip-sync overlay resolved via local fallback")
            return local

    return None


async def _composite_avatar_on_clip(
    clip_path: Path,
    scene: dict,
    width: int,
    height: int,
) -> Path:
    """Overlay a talking-head avatar onto a rendered scene clip.

    Avatar config comes from scene['avatar'] dict:
      id         - id from AVATAR_CATALOG, or 'custom'
      position   - bottom-right | bottom-left | bottom-center |
                   top-right | top-left | center-right | center-left
      size       - small | medium | large
      shape      - circle | rounded | rectangle
      custom_url - URL of custom avatar image (when id == 'custom')

    Lip-sync API is used automatically when LIPSYNC_ENABLED=true
    and the audio path is recorded in scene['_runtime']['tts_audio_path'].
    """
    avatar_cfg = scene.get("avatar") or {}
    avatar_id  = (avatar_cfg.get("id") or "").strip()
    if not avatar_id or avatar_id == "none":
        return clip_path

    position = avatar_cfg.get("position",  "bottom-right")
    size_key  = avatar_cfg.get("size",     "medium")
    shape     = avatar_cfg.get("shape",    "circle")

    # Resolve image URL
    if avatar_id == "custom":
        image_url = (avatar_cfg.get("custom_url") or "").strip()
        if not image_url:
            return clip_path
    else:
        entry = next((a for a in AVATAR_CATALOG if a["id"] == avatar_id), None)
        if not entry:
            return clip_path
        image_url = entry["image_url"]

    # Download / use cached image
    src_image = await _download_avatar_image(image_url, avatar_id)
    if not src_image or not src_image.exists():
        logging.warning("Avatar image unavailable for id=%s", avatar_id)
        return clip_path

    # Compute target pixel dimensions
    size_frac = AVATAR_SIZE_FRACTIONS.get(size_key, 0.29)
    avatar_h  = int(height * size_frac)
    avatar_w  = avatar_h  # square base; border adds a few px for circle

    AVATAR_CACHE_DIR.mkdir(parents=True, exist_ok=True)
    processed_png = AVATAR_CACHE_DIR / f"avatar_{avatar_id}_{shape}_{size_key}_{width}x{height}.png"

    if shape == "circle":
        ok = _create_circular_avatar_png(src_image, processed_png, avatar_h,
                                         border_color="#4F8EF7", border_px=5)
        overlay_w = overlay_h = avatar_h + 10  # border adds 5px each side
    elif shape == "rounded":
        ok = _create_rounded_avatar_png(src_image, processed_png, avatar_w, avatar_h,
                                        radius=int(avatar_h * 0.12))
        overlay_w, overlay_h = avatar_w, avatar_h
    else:  # rectangle
        ok = _create_rounded_avatar_png(src_image, processed_png, avatar_w, avatar_h, radius=0)
        overlay_w, overlay_h = avatar_w, avatar_h

    if not ok or not processed_png.exists():
        return clip_path

    # Pixel position
    margin = 18
    pos_map: dict[str, tuple[int, int]] = {
        "bottom-right":  (width  - overlay_w - margin, height - overlay_h - margin),
        "bottom-left":   (margin,                       height - overlay_h - margin),
        "bottom-center": ((width  - overlay_w) // 2,   height - overlay_h - margin),
        "top-right":     (width  - overlay_w - margin, margin),
        "top-left":      (margin,                       margin),
        "center-right":  (width  - overlay_w - margin, (height - overlay_h) // 2),
        "center-left":   (margin,                       (height - overlay_h) // 2),
    }
    x_px, y_px = pos_map.get(position, pos_map["bottom-right"])

    # Try lip-sync overlay (remote/local based on LIPSYNC_MODE)
    audio_path_str  = (scene.get("_runtime") or {}).get("tts_audio_path")
    overlay_source  = processed_png
    if audio_path_str:
        audio_obj = Path(audio_path_str)
        if audio_obj.exists():
            lipsync = await _build_lipsync_overlay(processed_png, audio_obj, overlay_w, overlay_h)
            if lipsync and lipsync.exists():
                overlay_source = lipsync

    # FFmpeg composite
    out_path = clip_path.with_name(clip_path.stem + "_avatar.mp4")
    is_video = overlay_source.suffix.lower() in {".mp4", ".mov", ".mkv", ".webm"}

    if is_video:
        cmd = [
            "ffmpeg", "-y",
            "-i", str(clip_path),
            "-stream_loop", "-1",
            "-i", str(overlay_source),
            "-filter_complex",
            f"[1:v]format=rgba,scale={overlay_w}:{overlay_h}[av];"
            f"[0:v][av]overlay={x_px}:{y_px}:shortest=1[v]",
            "-map", "[v]", "-map", "0:a?",
            "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p", "-c:a", "copy",
            str(out_path),
        ]
    else:
        cmd = [
            "ffmpeg", "-y",
            "-i", str(clip_path),
            "-i", str(processed_png),
            "-filter_complex",
            f"[1:v]scale={overlay_w}:{overlay_h}[av];"
            f"[0:v][av]overlay={x_px}:{y_px}:format=auto[v]",
            "-map", "[v]", "-map", "0:a?",
            "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p", "-c:a", "copy",
            str(out_path),
        ]

    await _run_command(cmd)
    out_path.replace(clip_path)
    logging.info(
        "Avatar composited: id=%s shape=%s pos=%s size=%s onto %s",
        avatar_id, shape, position, size_key, clip_path.name,
    )
    return clip_path


def _split_overlay_segments(text: str, max_segments: int = 3) -> list[str]:
    text = re.sub(r"\s+", " ", (text or "").strip())
    if not text:
        return []

    parts = [p.strip(" ,.-") for p in re.split(r"(?<=[.!?…])\s+|\s*[—;:]\s*", text) if p.strip()]
    if not parts:
        return [text]

    if len(parts) <= max_segments:
        return parts

    chunk_size = max(1, int(len(parts) / max_segments + 0.999))
    merged = []
    for idx in range(0, len(parts), chunk_size):
        merged.append(" ".join(parts[idx:idx + chunk_size]))
    return merged[:max_segments]


# ---------------------------------------------------------------------------
# Edge TTS voice map — full Indian + English catalog
# Value can be any valid Edge TTS ShortName; if the incoming speaker is already
# a valid ShortName (contains 'Neural') it is passed through directly.
# ---------------------------------------------------------------------------
_EDGE_TTS_VOICE_MAP: dict[str, str] = {
    # ── Indian English (en-IN) ──────────────────────────────────────────────
    "female_1":                    "en-IN-NeerjaExpressiveNeural",  # Expressive Indian English female
    "en-in-female-expressive":     "en-IN-NeerjaExpressiveNeural",
    "en-in-female":                "en-IN-NeerjaNeural",
    "en-in-female-2":              "en-IN-NeerjaNeural",
    "en-in-male":                  "en-IN-PrabhatNeural",
    "male_1":                      "en-IN-PrabhatNeural",
    # ── Hindi (hi-IN) ───────────────────────────────────────────────────────
    "female_2":                    "hi-IN-SwaraNeural",            # Hindi female
    "hi-in-female":                "hi-IN-SwaraNeural",
    "hi-in-male":                  "hi-IN-MadhurNeural",
    "hindi female":                "hi-IN-SwaraNeural",
    "hindi male":                  "hi-IN-MadhurNeural",
    # ── Tamil (ta-IN) ───────────────────────────────────────────────────────
    "ta-in-female":                "ta-IN-PallaviNeural",
    "ta-in-male":                  "ta-IN-ValluvarNeural",
    # ── Telugu (te-IN) ──────────────────────────────────────────────────────
    "te-in-female":                "te-IN-ShrutiNeural",
    "te-in-male":                  "te-IN-MohanNeural",
    # ── Kannada (kn-IN) ─────────────────────────────────────────────────────
    "kn-in-female":                "kn-IN-SapnaNeural",
    "kn-in-male":                  "kn-IN-GaganNeural",
    # ── Malayalam (ml-IN) ───────────────────────────────────────────────────
    "ml-in-female":                "ml-IN-SobhanaNeural",
    "ml-in-male":                  "ml-IN-MidhunNeural",
    # ── Marathi (mr-IN) ─────────────────────────────────────────────────────
    "mr-in-female":                "mr-IN-AarohiNeural",
    "mr-in-male":                  "mr-IN-ManoharNeural",
    # ── Bengali (bn-IN) ─────────────────────────────────────────────────────
    "bn-in-female":                "bn-IN-TanishaaNeural",
    "bn-in-male":                  "bn-IN-BashkarNeural",
    # ── Gujarati (gu-IN) ────────────────────────────────────────────────────
    "gu-in-female":                "gu-IN-DhwaniNeural",
    "gu-in-male":                  "gu-IN-NiranjanNeural",
    # ── English US ──────────────────────────────────────────────────────────
    "en-us-female":                "en-US-AvaNeural",
    "en-us-female-2":              "en-US-EmmaNeural",
    "en-us-female-3":              "en-US-JennyNeural",
    "en-us-female-4":              "en-US-AriaNeural",
    "en-us-male":                  "en-US-AndrewNeural",
    "en-us-male-2":                "en-US-BrianNeural",
    "en-us-male-3":                "en-US-GuyNeural",
    "male_2":                      "en-US-BrianNeural",
    # ── English UK ──────────────────────────────────────────────────────────
    "en-gb-female":                "en-GB-SoniaNeural",
    "en-gb-female-2":              "en-GB-LibbyNeural",
    "en-gb-male":                  "en-GB-RyanNeural",
    "en-gb-male-2":                "en-GB-ThomasNeural",
    # ── English AU ──────────────────────────────────────────────────────────
    "en-au-female":                "en-AU-NatashaNeural",
    "en-au-male":                  "en-AU-WilliamMultilingualNeural",
    # ── Legacy aliases (kept for backwards compatibility) ───────────────────
    "suad qasim":                  "en-IN-NeerjaExpressiveNeural",
    "chanda madan":                "hi-IN-SwaraNeural",
    "kumar dahl":                  "en-IN-PrabhatNeural",
    "damien black":                "en-US-BrianNeural",
    "indian female":               "en-IN-NeerjaExpressiveNeural",
    "indian male":                 "en-IN-PrabhatNeural",
    "english female":              "en-US-AvaNeural",
    "english male":                "en-GB-RyanNeural",
}


def _normalize_tts_speaker(speaker: str) -> str:
    """Return a human-readable label for a speaker ID (for diagnostics)."""
    labels = {
        "female_1": "Neerja (Indian English F)",
        "female_2": "Swara (Hindi F)",
        "male_1":   "Prabhat (Indian English M)",
        "male_2":   "Brian (US English M)",
    }
    return labels.get((speaker or "").strip(), (speaker or "").strip())


def _resolve_edge_tts_voice(speaker: str) -> str:
    """Return the Edge TTS ShortName for a given speaker key.
    If the speaker is already a valid ShortName (contains 'Neural') it is returned as-is.
    Falls back to en-IN-NeerjaExpressiveNeural if unknown."""
    s = (speaker or "").strip()
    # Already a raw voice ID — pass through directly
    if "Neural" in s:
        return s
    key = s.lower()
    if key in _EDGE_TTS_VOICE_MAP:
        return _EDGE_TTS_VOICE_MAP[key]
    # Fuzzy substring match
    for k, v in _EDGE_TTS_VOICE_MAP.items():
        if k and (k in key or key in k):
            return v
    return "en-IN-NeerjaExpressiveNeural"  # safe default


def _parse_markup_to_ssml(text: str, voice: str) -> str:
    """Convert custom voice markup into SSML for Edge TTS.

    Supported markup (same syntax as Azure SSML shortcuts):
      **text**                               → <emphasis level="strong">text</emphasis>
      *text*                                 → <emphasis level="moderate">text</emphasis>
      [pause:500]                            → <break time="500ms"/>
      [silence:1000]                         → <break time="1000ms"/>
      [rate:slow]text[/rate]                 → <prosody rate="slow">text</prosody>
      [pitch:high]text[/pitch]               → <prosody pitch="high">text</prosody>
      [volume:soft]text[/volume]             → <prosody volume="soft">text</prosody>
      [prosody rate="-10%" pitch="+5%"]text[/prosody]  → <prosody ...>text</prosody>
      [personality:Cheerful]text[/personality]  → text  (stripped, not in standard SSML)
    """
    import re
    # Already raw SSML — leave as-is
    if text.strip().startswith("<speak"):
        return text

    # Bold: **text** → strong emphasis
    text = re.sub(r'\*\*(.+?)\*\*', r'<emphasis level="strong">\1</emphasis>', text, flags=re.DOTALL)
    # Italic: *text* → moderate emphasis
    text = re.sub(r'\*([^*]+?)\*', r'<emphasis level="moderate">\1</emphasis>', text, flags=re.DOTALL)
    # Pauses
    text = re.sub(r'\[pause:(\d+)\]',   r'<break time="\1ms"/>', text)
    text = re.sub(r'\[silence:(\d+)\]', r'<break time="\1ms"/>', text)
    # Rate
    text = re.sub(r'\[rate:([^\]]+)\](.+?)\[/rate\]',     r'<prosody rate="\1">\2</prosody>',   text, flags=re.DOTALL)
    # Pitch
    text = re.sub(r'\[pitch:([^\]]+)\](.+?)\[/pitch\]',   r'<prosody pitch="\1">\2</prosody>',  text, flags=re.DOTALL)
    # Volume
    text = re.sub(r'\[volume:([^\]]+)\](.+?)\[/volume\]', r'<prosody volume="\1">\2</prosody>', text, flags=re.DOTALL)
    # Combined prosody: [prosody attr="val" ...]text[/prosody]
    text = re.sub(r'\[prosody ([^\]]+)\](.+?)\[/prosody\]', r'<prosody \1>\2</prosody>', text, flags=re.DOTALL)
    # Personality / style tags: strip markers, keep content (not in standard SSML)
    text = re.sub(r'\[personality:[^\]]+\](.+?)\[/personality\]', r'\1', text, flags=re.DOTALL)
    text = re.sub(r'\[style:[^\]]+\](.+?)\[/style\]', r'\1', text, flags=re.DOTALL)

    return (
        f'<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="en-IN">'
        f'<voice name="{voice}">{text}</voice></speak>'
    )


_MARKUP_MARKERS = ("**", "*", "[pause:", "[silence:", "[rate:", "[pitch:", "[volume:", "[prosody", "[personality:", "[style:")


async def _synthesize_with_edge_tts(text: str, speaker: str) -> bytes:
    """Synthesize speech with Microsoft Edge TTS.
    Automatically converts custom markup to SSML when markup tags are present."""
    import edge_tts
    voice = _resolve_edge_tts_voice(speaker)

    # Detect if text contains custom markup that needs SSML conversion
    has_markup = text.strip().startswith("<speak") or any(m in text for m in _MARKUP_MARKERS)
    if has_markup:
        ssml = _parse_markup_to_ssml(text, voice)
        communicate = edge_tts.Communicate(ssml, voice=voice)
    else:
        communicate = edge_tts.Communicate(text, voice=voice)

    audio_chunks: list[bytes] = []
    async for chunk in communicate.stream():
        if chunk["type"] == "audio":
            audio_chunks.append(chunk["data"])
    if not audio_chunks:
        raise RuntimeError(f"edge-tts returned no audio for voice {voice!r}")
    return b"".join(audio_chunks)


async def _apply_voiceover_overlay(clip_path: Path, overlay_text: str, duration: float, scene_index: int) -> Path:
    """Overlay large timed phrases derived from voiceover text onto the scene clip."""
    segments = _split_overlay_segments(overlay_text, max_segments=3)
    if not segments or duration <= 0:
        return clip_path

    fontsize = 36
    line_spacing = 8
    line_step = fontsize + line_spacing          # pixels between line tops
    video_h = 720                                # target height for 16:9 HD

    # Anchor Y for the TOP of a wrapped text block in each zone (pixels).
    # bottom/middle anchors are adjusted downward from these bases.
    _zone_base_y = [
        int(video_h * 0.08),   # zone 0 — top band
        int(video_h * 0.68),   # zone 1 — lower band
        int(video_h * 0.38),   # zone 2 — middle band
    ]

    total_words = sum(max(1, len(seg.split())) for seg in segments)
    current = 0.35
    filters: list[str] = []

    for idx, seg in enumerate(segments):
        words = max(1, len(seg.split()))
        seg_len = max(1.1, (duration - 0.7) * (words / total_words))
        start = current
        end = min(duration - 0.15, start + seg_len)
        zone = (scene_index + idx) % len(_zone_base_y)
        base_y = _zone_base_y[zone]

        # Word-wrap returns one escaped string per visual line
        lines = _wrap_text_lines(seg, fontsize=fontsize, video_width=1280)

        # For bottom zone, anchor so the last line stays within the frame
        if zone == 1:
            base_y = min(base_y, video_h - len(lines) * line_step - 20)

        font_prefix = f"drawtext=fontfile={VIDEO_FONT_FILE}:" if Path(VIDEO_FONT_FILE).exists() else "drawtext="
        for li, escaped_line in enumerate(lines):
            y_px = base_y + li * line_step
            draw = (
                font_prefix
                + f"text='{escaped_line}':fontcolor=white:fontsize={fontsize}:"
                + f"x=(w-text_w)/2:y={y_px}:fix_bounds=1:"
                + f"box=1:boxcolor=0x0B1220AA:boxborderw=14:shadowcolor=0x000000:shadowx=0:shadowy=3:"
                + f"enable='between(t,{start:.2f},{end:.2f})'"
            )
            filters.append(draw)
        current = end + 0.18

    overlay_path = clip_path.with_name(f"{clip_path.stem}_overlay.mp4")
    await _run_command([
        "ffmpeg", "-y",
        "-i", str(clip_path),
        "-vf", ",".join(filters + ["format=yuv420p"]),
        "-map", "0:v", "-map", "0:a?",
        "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
        "-c:a", "copy",
        str(overlay_path),
    ])
    overlay_path.replace(clip_path)
    return clip_path


async def _finalize_scene_clip(clip_path: Path, scene: dict, scene_index: int, duration: float, width: int = 1280, height: int = 720) -> Path:
    # Store dimensions in runtime so avatar helper can read them
    scene.setdefault("_runtime", {})["video_width"]  = width
    scene.setdefault("_runtime", {})["video_height"] = height

    # 1 ─ Text overlay (word-wrapped subtitles)
    overlay_text = (scene.get("voiceover_text") or "").strip()
    if overlay_text and scene.get("text_overlay", True) is not False:
        try:
            await _apply_voiceover_overlay(clip_path, overlay_text, duration, scene_index)
            scene["_runtime"]["text_overlay_applied"] = True
        except Exception as exc:
            logging.warning("Scene %s overlay failed: %s", scene_index + 1, exc)
            scene["_runtime"]["text_overlay_applied"] = False
            scene["_runtime"]["text_overlay_error"]   = str(exc)

    # 2 ─ Avatar / talking-head composite
    avatar_id = ((scene.get("avatar") or {}).get("id") or "").strip()
    if avatar_id and avatar_id != "none":
        try:
            await _composite_avatar_on_clip(clip_path, scene, width, height)
            scene["_runtime"]["avatar_applied"] = True
        except Exception as exc:
            logging.warning("Scene %s avatar failed: %s", scene_index + 1, exc)
            scene["_runtime"]["avatar_applied"] = False
            scene["_runtime"]["avatar_error"]   = str(exc)

    return clip_path

async def _run_command(command: list[str]):
    proc = await asyncio.create_subprocess_exec(
        *command,
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
    )
    stdout, stderr = await proc.communicate()
    if proc.returncode != 0:
        raise RuntimeError((stderr or stdout or b"command failed").decode("utf-8", errors="ignore"))
    return stdout.decode("utf-8", errors="ignore")

async def _probe_duration_seconds(file_path: Path) -> float:
    output = await _run_command([
        "ffprobe",
        "-v",
        "error",
        "-show_entries",
        "format=duration",
        "-of",
        "default=noprint_wrappers=1:nokey=1",
        str(file_path),
    ])
    try:
        return max(0.0, float((output or "0").strip()))
    except Exception:
        return 0.0

def _contain_blur_filter(width: int, height: int, sharpen_amount: float = 0.8) -> str:
    """Return an ffmpeg filter that keeps the full image visible.
    The source is centered over a blurred full-frame background, which avoids
    both hard black bars and center-cropping."""
    return (
        f"[0:v]split=2[bg][fg];"
        f"[bg]scale={width}:{height}:force_original_aspect_ratio=increase:flags=lanczos,"
        f"boxblur=20:10,crop={width}:{height}[bg2];"
        f"[fg]scale={width}:{height}:force_original_aspect_ratio=decrease:flags=lanczos,"
        f"unsharp=5:5:{sharpen_amount}:3:3:0.0[fg2];"
        f"[bg2][fg2]overlay=(W-w)/2:(H-h)/2,format=yuv420p"
    )


async def _prepare_image_canvas(src_path: Path, dest_path: Path, width: int, height: int, sharpen_amount: float = 0.8) -> Path:
    """Render a single image onto an exact output canvas without cropping.
    This is used for preserve-mode screenshots and for ComfyUI img2vid inputs so
    website/UI images keep all text visible."""
    dest_path.parent.mkdir(parents=True, exist_ok=True)
    await _run_command([
        "ffmpeg", "-y",
        "-i", str(src_path),
        "-filter_complex", _contain_blur_filter(width, height, sharpen_amount=sharpen_amount),
        "-frames:v", "1",
        str(dest_path),
    ])
    return dest_path


def _resolve_video_mode(scene: dict) -> str:
    """Auto-preserve scenes that are likely screenshots, dashboards, widgets, or text-heavy UI.
    AnimateDiff is poor at reproducing exact text, so these scenes should stay in ffmpeg preserve mode."""
    explicit = (scene.get("video_mode") or "").strip().lower()
    prompt = (scene.get("prompt") or "").lower()
    refs = []
    refs.extend(scene.get("reference_image_urls") or [])
    refs.extend(scene.get("reference_image_paths") or [])
    if scene.get("reference_image_url"):
        refs.append(scene.get("reference_image_url"))
    if scene.get("reference_image_path"):
        refs.append(scene.get("reference_image_path"))
    ref_text = " ".join(str(r).lower() for r in refs)

    screenshot_cues = (
        "screenshot", "widget", "dashboard", "settings", "admin", "backend", "panel",
        "website", "webpage", "landing page", "search bar", "interface", " ui ",
        "shopify", "wordpress", "logo", "pricing", "faq", "knowledge base", "text",
    )
    screenshot_url_cues = (
        "cdn.shopify.com", "website-files.com", "/images/onboarding/", "widget-settings",
        "screenshot-", ".png", ".webp",
    )
    looks_like_screenshot = any(cue in prompt for cue in screenshot_cues) or any(cue in ref_text for cue in screenshot_url_cues)

    if explicit == "preserve":
        return "preserve"
    if looks_like_screenshot:
        return "preserve"
    return "animate"

def _motion_enhance_prompt(prompt: str) -> str:
    """Prepend subtle camera-motion keywords when the user's prompt doesn't already
    contain them.  Drives AnimateDiff to generate actual movement rather than a
    near-static image."""
    _motion_kws = ("camera", "dolly", "pan ", "pans", "zoom", "motion", "tracking",
                   "cinematic", "drone", "fly", "handheld", "steadicam", "tilt", "orbital")
    if any(kw in prompt.lower() for kw in _motion_kws):
        return prompt
    return f"slow cinematic camera pan, smooth natural motion, fluid cinematography, {prompt}"


async def _synthesize_audio_bytes(text: str, language: str = "en", speaker: str = ""):
    """Synthesize speech using Microsoft Edge TTS (sole provider — XTTS/Indic removed)."""
    speaker_label = _normalize_tts_speaker(speaker)
    audio_bytes = await _synthesize_with_edge_tts(text, speaker or speaker_label)
    return audio_bytes, "audio/mpeg", "edge_tts"

# ---------------------------------------------------------------------------
# ComfyUI (AnimateDiff) helpers
# ---------------------------------------------------------------------------

def _get_gpu_utilization() -> int:
    """Return current GPU utilisation 0-100.  Returns 0 if nvidia-smi is unavailable."""
    try:
        result = subprocess.run(
            ["nvidia-smi", "--query-gpu=utilization.gpu", "--format=csv,noheader,nounits"],
            capture_output=True, text=True, timeout=5,
        )
        return int(result.stdout.strip().split("\n")[0])
    except Exception:
        return 0

async def _comfyui_available() -> bool:
    """Quick health check — returns True if ComfyUI is reachable."""
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(f"{COMFYUI_URL}/system_stats")
            return resp.status_code == 200
    except Exception:
        return False


def _build_t2v_workflow(prompt: str, width: int, height: int, frames: int, seed: int, add_upscale: bool = False) -> dict:
    """Return a ComfyUI API workflow dict for text-to-video (AnimateDiff).
    If add_upscale=True, appends RealESRGAN ×4 nodes after VAEDecode.
    If COMFYUI_MOTION_LORA is set, injects a LoraLoader node for enhanced camera motion."""
    motion_prompt = _motion_enhance_prompt(prompt or "")
    full_prompt = f"{COMFYUI_GLOBAL_STYLE_PROMPT}, {motion_prompt}".strip(", ")
    # If Motion LoRA is configured, route the model through LoraLoader first
    _sd_model_ref = ["1", 0]
    _clip_ref = ["1", 1]
    wf: dict = {
        "1":  {"class_type": "CheckpointLoaderSimple",
               "inputs": {"ckpt_name": COMFYUI_CHECKPOINT}},
    }
    if COMFYUI_MOTION_LORA:
        wf["lora"] = {
            "class_type": "LoraLoader",
            "inputs": {
                "model": ["1", 0], "clip": ["1", 1],
                "lora_name": COMFYUI_MOTION_LORA,
                "strength_model": COMFYUI_MOTION_LORA_STRENGTH,
                "strength_clip": 0.0,
            },
        }
        _sd_model_ref = ["lora", 0]
        _clip_ref     = ["lora", 1]
    wf.update({
        "2":  {"class_type": "CLIPTextEncode",
               "inputs": {"text": full_prompt, "clip": _clip_ref}},
        "3":  {"class_type": "CLIPTextEncode",
               "inputs": {"text": COMFYUI_NEG_PROMPT, "clip": _clip_ref}},
        "4":  {"class_type": "ADE_LoadAnimateDiffModel",
               "inputs": {"model_name": COMFYUI_MOTION_MODEL}},
        "5":  {"class_type": "ADE_ApplyAnimateDiffModelSimple",
               "inputs": {"motion_model": ["4", 0]}},
        "6":  {"class_type": "ADE_UseEvolvedSampling",
               "inputs": {"model": _sd_model_ref,
                          "beta_schedule": "sqrt_linear (AnimateDiff)",
                          "m_models": ["5", 0]}},
        "7":  {"class_type": "EmptyLatentImage",
               "inputs": {"width": width, "height": height, "batch_size": frames}},
        "8":  {"class_type": "KSampler",
               "inputs": {"seed": seed, "steps": COMFYUI_STEPS, "cfg": COMFYUI_CFG,
                          "sampler_name": "euler", "scheduler": "karras",
                          "denoise": COMFYUI_DENOISE_T2V,
                          "model": ["6", 0], "positive": ["2", 0],
                          "negative": ["3", 0], "latent_image": ["7", 0]}},
        "9":  {"class_type": "VAEDecode",
               "inputs": {"samples": ["8", 0], "vae": ["1", 2]}},
    })
    if add_upscale:
        wf["10"] = {"class_type": "UpscaleModelLoader",
                    "inputs": {"model_name": COMFYUI_UPSCALE_MODEL}}
        wf["11"] = {"class_type": "ImageUpscaleWithModel",
                    "inputs": {"upscale_model": ["10", 0], "image": ["9", 0]}}
        wf["12"] = {"class_type": "SaveImage",
                    "inputs": {"images": ["11", 0], "filename_prefix": "aichat_t2v_up"}}
    else:
        wf["10"] = {"class_type": "SaveImage",
                    "inputs": {"images": ["9", 0], "filename_prefix": "aichat_t2v"}}
    return wf


def _build_i2v_workflow(prompt: str, image_name: str, width: int, height: int, frames: int, seed: int, add_upscale: bool = False) -> dict:
    """Return a ComfyUI API workflow dict for image-to-video (AnimateDiff img2img).
    If add_upscale=True, appends RealESRGAN ×4 nodes after VAEDecode.
    Node 9 = VAEEncode, Node 10 = RepeatLatentBatch (expands single-frame latent
    to batch_size=frames so AnimateDiff receives the correct shape).
    Uses euler (non-ancestral) + karras for temporally-stable, artifact-free output.
    If COMFYUI_MOTION_LORA is set, injects a LoraLoader for enhanced camera motion."""
    motion_prompt = _motion_enhance_prompt(prompt or "")
    full_prompt = f"{COMFYUI_GLOBAL_STYLE_PROMPT}, {motion_prompt}".strip(", ")
    # Route model through LoraLoader if a Motion LoRA is configured
    _sd_model_ref = ["1", 0]
    _clip_ref = ["1", 1]
    wf: dict = {
        "1":  {"class_type": "CheckpointLoaderSimple",
               "inputs": {"ckpt_name": COMFYUI_CHECKPOINT}},
    }
    if COMFYUI_MOTION_LORA:
        wf["lora"] = {
            "class_type": "LoraLoader",
            "inputs": {
                "model": ["1", 0], "clip": ["1", 1],
                "lora_name": COMFYUI_MOTION_LORA,
                "strength_model": COMFYUI_MOTION_LORA_STRENGTH,
                "strength_clip": 0.0,
            },
        }
        _sd_model_ref = ["lora", 0]
        _clip_ref     = ["lora", 1]
    wf.update({
        "2":  {"class_type": "CLIPTextEncode",
               "inputs": {"text": full_prompt,
                          "clip": _clip_ref}},
        "3":  {"class_type": "CLIPTextEncode",
               "inputs": {"text": COMFYUI_NEG_PROMPT, "clip": _clip_ref}},
        "4":  {"class_type": "ADE_LoadAnimateDiffModel",
               "inputs": {"model_name": COMFYUI_MOTION_MODEL}},
        "5":  {"class_type": "ADE_ApplyAnimateDiffModelSimple",
               "inputs": {"motion_model": ["4", 0]}},
        "6":  {"class_type": "ADE_UseEvolvedSampling",
               "inputs": {"model": _sd_model_ref,
                          "beta_schedule": "sqrt_linear (AnimateDiff)",
                          "m_models": ["5", 0]}},
        "7":  {"class_type": "LoadImage",
               "inputs": {"image": image_name}},
        "8":  {"class_type": "ImageScale",
               "inputs": {"image": ["7", 0], "width": width, "height": height,
                          "upscale_method": "lanczos", "crop": "center"}},
        "9":  {"class_type": "VAEEncode",
               "inputs": {"pixels": ["8", 0], "vae": ["1", 2]}},
        # Expand single-frame latent to batch_size=frames (required by AnimateDiff)
        "10": {"class_type": "RepeatLatentBatch",
               "inputs": {"samples": ["9", 0], "amount": frames}},
        "11": {"class_type": "KSampler",
               "inputs": {"seed": seed, "steps": COMFYUI_STEPS, "cfg": COMFYUI_CFG,
                          # euler (NOT ancestral) + karras = deterministic per-frame denoising
                          # → temporal attention has consistent gradients → no rainbow flicker
                          "sampler_name": "euler", "scheduler": "karras",
                          "denoise": COMFYUI_DENOISE_I2V,
                          "model": ["6", 0], "positive": ["2", 0],
                          "negative": ["3", 0], "latent_image": ["10", 0]}},
        "12": {"class_type": "VAEDecode",
               "inputs": {"samples": ["11", 0], "vae": ["1", 2]}},
    })
    if add_upscale:
        wf["13"] = {"class_type": "UpscaleModelLoader",
                    "inputs": {"model_name": COMFYUI_UPSCALE_MODEL}}
        wf["14"] = {"class_type": "ImageUpscaleWithModel",
                    "inputs": {"upscale_model": ["13", 0], "image": ["12", 0]}}
        wf["15"] = {"class_type": "SaveImage",
                    "inputs": {"images": ["14", 0], "filename_prefix": "aichat_i2v_up"}}
    else:
        wf["13"] = {"class_type": "SaveImage",
                    "inputs": {"images": ["12", 0], "filename_prefix": "aichat_i2v"}}
    return wf


async def _comfyui_upload_image(image_path: Path) -> str:
    """Upload an image to ComfyUI /upload/image and return the stored filename."""
    async with httpx.AsyncClient(timeout=30.0) as client:
        with open(image_path, "rb") as fh:
            resp = await client.post(
                f"{COMFYUI_URL}/upload/image",
                files={"image": (image_path.name, fh, "image/png")},
                data={"overwrite": "true"},
            )
        resp.raise_for_status()
        return resp.json()["name"]


async def _comfyui_submit(workflow: dict) -> str:
    """Submit a workflow to ComfyUI /prompt and return the prompt_id."""
    async with httpx.AsyncClient(timeout=30.0) as client:
        resp = await client.post(f"{COMFYUI_URL}/prompt", json={"prompt": workflow})
        resp.raise_for_status()
        return resp.json()["prompt_id"]


async def _comfyui_wait(prompt_id: str) -> dict:
    """Poll /history/{prompt_id} until the job is complete; return the history entry."""
    deadline = asyncio.get_event_loop().time() + COMFYUI_TIMEOUT
    async with httpx.AsyncClient(timeout=15.0) as client:
        while asyncio.get_event_loop().time() < deadline:
            try:
                resp = await client.get(f"{COMFYUI_URL}/history/{prompt_id}")
                if resp.status_code == 200:
                    data = resp.json()
                    if prompt_id in data:
                        entry = data[prompt_id]
                        status = entry.get("status", {})
                        if status.get("completed"):
                            return entry
                        # Check for errors in the queue
                        if status.get("status_str") == "error":
                            messages = status.get("messages", [])
                            raise RuntimeError(f"ComfyUI job failed: {messages}")
            except httpx.HTTPError:
                pass
            await asyncio.sleep(COMFYUI_POLL_INTERVAL)
    raise TimeoutError(f"ComfyUI job {prompt_id} did not complete within {COMFYUI_TIMEOUT}s")


async def _comfyui_download_frames(history_entry: dict, dest_dir: Path) -> list[Path]:
    """Download all output image frames produced by the workflow."""
    frame_paths: list[Path] = []
    outputs = history_entry.get("outputs", {})
    async with httpx.AsyncClient(timeout=60.0) as client:
        for _node_id, node_output in outputs.items():
            images = node_output.get("images", [])
            for idx, img_info in enumerate(images):
                filename = img_info["filename"]
                subfolder = img_info.get("subfolder", "")
                img_type = img_info.get("type", "output")
                params = f"filename={filename}&type={img_type}"
                if subfolder:
                    params += f"&subfolder={subfolder}"
                resp = await client.get(f"{COMFYUI_URL}/view?{params}")
                resp.raise_for_status()
                frame_path = dest_dir / f"frame_{idx:05d}.png"
                frame_path.write_bytes(resp.content)
                frame_paths.append(frame_path)
    frame_paths.sort(key=lambda p: p.name)
    return frame_paths


async def _frames_to_clip(frame_paths: list[Path], clip_path: Path, render_fps: int, target_duration: int, audio_path=None, out_w: int = 512, out_h: int = 512) -> Path:
    """Stitch PNG frames into a video clip; loop to reach target_duration; optionally mix audio.
    out_w/out_h define the final pixel dimensions — FFmpeg scales/pads frames to fit."""
    if not frame_paths:
        raise RuntimeError("No frames to stitch")

    frame_dir = frame_paths[0].parent
    # Write an ffconcat to loop the frames to reach target_duration
    frames_available = len(frame_paths)
    clip_duration_secs = frames_available / render_fps
    loop_count = max(1, int(target_duration / clip_duration_secs) + 1)

    concat_content = "ffconcat version 1.0\n"
    for _ in range(loop_count):
        for fp in frame_paths:
            concat_content += f"file '{fp.resolve()}'\nduration {1.0 / render_fps:.6f}\n"
    concat_file = frame_dir / "frames_concat.txt"
    concat_file.write_text(concat_content, encoding="utf-8")

    # Produce a raw clip trimmed to target_duration at the requested output resolution.
    # minterpolate=fps=VIDEO_FPS:mi_mode=blend upsamples 8fps AnimateDiff frames to 24fps
    # using linear blending between adjacent frames — much smoother than raw 8fps playback.
    raw_clip = frame_dir / "raw_visual.mp4"
    cmd = [
        "ffmpeg", "-y",
        "-f", "concat", "-safe", "0", "-i", str(concat_file),
        "-t", str(target_duration),
        "-filter_complex", (
            f"[0:v]minterpolate=fps={VIDEO_FPS}:mi_mode=blend[tmp];"
            f"[tmp]split=2[bg][fg];"
            f"[bg]scale={out_w}:{out_h}:force_original_aspect_ratio=increase:flags=lanczos,"
            f"boxblur=20:10,crop={out_w}:{out_h}[bg2];"
            f"[fg]scale={out_w}:{out_h}:force_original_aspect_ratio=decrease:flags=lanczos[fg2];"
            f"[bg2][fg2]overlay=(W-w)/2:(H-h)/2,format=yuv420p"
        ),
        "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
        str(raw_clip),
    ]
    await _run_command(cmd)

    if audio_path:
        # Use apad to extend short audio with silence so the video always reaches
        # target_duration even when voiceover finishes early.
        cmd2 = [
            "ffmpeg", "-y",
            "-i", str(raw_clip),
            "-i", str(audio_path),
            "-filter_complex", "[1:a]apad[aout]",
            "-map", "0:v", "-map", "[aout]",
            "-c:v", "copy", "-c:a", "aac",
            "-t", str(target_duration),
            str(clip_path),
        ]
        await _run_command(cmd2)
    else:
        raw_clip.rename(clip_path)

    return clip_path


async def _comfyui_render_scene(
    job_id: str,
    prompt: str,
    image_path: Path | None,
    clip_path: Path,
    target_duration: int,
    audio_path=None,
    seed: int = 42,
    out_w: int = 512,
    out_h: int = 512,
) -> Path:
    """
    Renders a scene clip via ComfyUI AnimateDiff.
    - Always generates at COMFYUI_WIDTH × COMFYUI_HEIGHT (512×512, SD1.5 native).
    - If out_w/out_h exceed 512, RealESRGAN ×4 upscale nodes are added to the
      workflow automatically, delivering ~2048px frames that FFmpeg then crops
      and rescales to the exact target dimensions.
    - Chooses text2vid or img2vid workflow based on whether image_path is supplied.
    """
    frames_dir = clip_path.parent / f"frames_{clip_path.stem}"
    frames_dir.mkdir(parents=True, exist_ok=True)

    # Base generation is always at SD1.5 native resolution
    w, h = COMFYUI_WIDTH, COMFYUI_HEIGHT
    # Upscale whenever the desired output is larger than the base render size
    add_upscale = out_w > w or out_h > h
    if add_upscale:
        logging.info(
            "Video job %s: RealESRGAN upscale enabled (%dx%d → %dx%d)",
            job_id, w, h, out_w, out_h,
        )

    if image_path and image_path.exists():
        logging.info("Video job %s: ComfyUI img2vid (upscale=%s)", job_id, add_upscale)
        comfy_input = clip_path.parent / f"{clip_path.stem}_comfy_input.png"
        try:
            await _prepare_image_canvas(image_path, comfy_input, w, h, sharpen_amount=0.9)
            image_path = comfy_input
        except Exception as exc:
            logging.warning("Video job %s: could not prefit ComfyUI image (%s), using original", job_id, exc)
        img_name = await _comfyui_upload_image(image_path)
        workflow = _build_i2v_workflow(prompt or "", img_name, w, h, COMFYUI_FRAMES, seed, add_upscale=add_upscale)
    else:
        logging.info("Video job %s: ComfyUI t2v (upscale=%s)", job_id, add_upscale)
        workflow = _build_t2v_workflow(prompt or "cinematic scene", w, h, COMFYUI_FRAMES, seed, add_upscale=add_upscale)

    # ── GPU throttle: wait until utilisation drops before queuing next render ────
    for _wait in range(20):
        util = _get_gpu_utilization()
        if util <= COMFYUI_GPU_UTIL_THRESHOLD:
            break
        logging.info("Video job %s: GPU at %d%% > threshold %d%%, waiting 5s",
                     job_id, util, COMFYUI_GPU_UTIL_THRESHOLD)
        await asyncio.sleep(5)

    # ── Semaphore: only 1 ComfyUI render at a time to share GPU with Ollama ──
    async with _comfyui_semaphore:
        prompt_id = await _comfyui_submit(workflow)
        logging.info("Video job %s: ComfyUI prompt_id=%s", job_id, prompt_id)
        history = await _comfyui_wait(prompt_id)
        frame_paths = await _comfyui_download_frames(history, frames_dir)
    logging.info("Video job %s: downloaded %d frames (upscale=%s)", job_id, len(frame_paths), add_upscale)
    return await _frames_to_clip(frame_paths, clip_path, COMFYUI_RENDER_FPS, target_duration, audio_path, out_w=out_w, out_h=out_h)


async def _prepare_reference_images(scene: dict, scene_dir: Path, index: int) -> list:
    """Download/copy all reference images for a scene.  Supports both legacy single
    fields (reference_image_url / reference_image_path) and new list fields
    (reference_image_urls / reference_image_paths)."""
    paths: list = []

    # ── local files ──────────────────────────────────────────────────────────
    local_list = scene.get("reference_image_paths") or []
    if not local_list and scene.get("reference_image_path"):
        local_list = [scene["reference_image_path"]]
    for i, sp in enumerate(local_list):
        sp = (sp or "").strip()
        if sp and Path(sp).exists():
            suffix = Path(sp).suffix or ".png"
            target = scene_dir / f"scene_{index:02d}_local{i:02d}{suffix}"
            shutil.copy2(sp, target)
            paths.append(target)

    # ── remote URLs ───────────────────────────────────────────────────────────
    url_list = scene.get("reference_image_urls") or []
    if not url_list and scene.get("reference_image_url"):
        url_list = [scene["reference_image_url"]]
    async with httpx.AsyncClient(timeout=40.0, follow_redirects=True) as client:
        for i, url in enumerate(url_list):
            url = (url or "").strip()
            if not url:
                continue
            try:
                raw_ext = Path(url.split("?")[0]).suffix.lower()
                suffix = raw_ext if raw_ext in (".jpg", ".jpeg", ".png", ".webp", ".bmp") else ".jpg"
                target = scene_dir / f"scene_{index:02d}_url{i:02d}{suffix}"
                resp = await client.get(url)
                resp.raise_for_status()
                target.write_bytes(resp.content)
                paths.append(target)
            except Exception as exc:
                logging.warning("Scene %s: could not download image %s: %s", index + 1, url, exc)

    return paths

async def _render_scene_clip(job_id: str, scene: dict, index: int, scene_dir: Path, width: int, height: int, language: str, speaker: str):
    scene_title = (scene.get("title") or f"Scene {index + 1}").strip() or f"Scene {index + 1}"
    scene_prompt = (scene.get("prompt") or "").strip()
    voiceover_text = (scene.get("voiceover_text") or "").strip()
    requested_duration = max(4, int(scene.get("duration_seconds") or 10))
    input_mode = (scene.get("input_mode") or "static").strip()   # text | image | both | static
    image_paths = await _prepare_reference_images(scene, scene_dir, index)
    image_path = image_paths[0] if image_paths else None  # first image for single-image path
    audio_path = None
    actual_duration = requested_duration
    runtime = scene.setdefault("_runtime", {})
    runtime["speaker_requested"] = speaker
    runtime["speaker_normalized"] = _normalize_tts_speaker(speaker)
    runtime["video_mode_resolved"] = _resolve_video_mode(scene)

    # ── Synthesize voice-over ────────────────────────────────────────────────
    if voiceover_text:
        try:
            audio_bytes, mime_type, provider_used = await _synthesize_audio_bytes(
                voiceover_text, language=language, speaker=speaker
            )
            audio_ext = ".mp3" if "mpeg" in mime_type else ".wav"
            audio_path = scene_dir / f"scene_{index:02d}{audio_ext}"
            audio_path.write_bytes(audio_bytes)
            runtime["tts_provider_used"] = provider_used
            runtime["tts_mime_type"] = mime_type
            runtime["tts_audio_path"] = str(audio_path)
            audio_duration = await _probe_duration_seconds(audio_path)
            if audio_duration > 0:
                actual_duration = max(requested_duration, int(audio_duration + 1))
            runtime["tts_audio_duration_seconds"] = audio_duration
            runtime["scene_duration_seconds"] = actual_duration
            # edge-tts has real distinct voices — voice selection IS effective
            runtime["tts_voice_selection_effective"] = provider_used == "edge_tts"
            if provider_used == "edge_tts":
                runtime["tts_edge_voice"] = _resolve_edge_tts_voice(speaker or "")
            logging.info(
                "Video job %s scene %s synthesized provider=%s speaker=%s duration=%ss",
                job_id, index + 1, provider_used, speaker or "<default>", actual_duration,
            )
        except Exception as exc:
            logging.warning("Video job %s scene %s TTS failed (continuing without audio): %s", job_id, index + 1, exc)
            audio_path = None
            runtime["tts_error"] = str(exc)

    clip_path = scene_dir / f"scene_{index:02d}.mp4"

    # ── Multi-image: render one sub-clip per image then concatenate ───────────
    if len(image_paths) > 1:
        sub_duration = max(2.0, actual_duration / len(image_paths))
        sub_clips: list = []
        video_mode = _resolve_video_mode(scene)
        comfyui_ok = video_mode == "animate" and input_mode in ("text", "image", "both") and await _comfyui_available()
        logging.info("Video job %s scene %s: multi-image mode (%d images, %.1fs each, video_mode=%s)", job_id, index + 1, len(image_paths), sub_duration, video_mode)

        for img_idx, img_path in enumerate(image_paths):
            sub_clip_path = scene_dir / f"scene_{index:02d}_sub{img_idx:02d}.mp4"
            rendered = False

            if comfyui_ok and (scene_prompt or img_path):
                try:
                    effective_image = None if input_mode == "text" else img_path
                    await _comfyui_render_scene(
                        job_id=f"{job_id}_s{index}i{img_idx}",
                        prompt=scene_prompt,
                        image_path=effective_image,
                        clip_path=sub_clip_path,
                        target_duration=sub_duration,
                        audio_path=None,
                        seed=hash(f"{job_id}_{index}_{img_idx}") % (2 ** 31),
                        out_w=width,
                        out_h=height,
                    )
                    rendered = sub_clip_path.exists()
                except Exception as exc:
                    logging.warning("Video job %s scene %s sub %s ComfyUI failed: %s", job_id, index + 1, img_idx, exc)

            if not rendered:
                # Ken Burns effect for sub-clip fallback (cycle style per image index)
                # Use VIDEO_FPS so preserve-mode scenes feel like real video rather than 8fps slides.
                # Zoom capped at 1.15x (not 1.3x) so text in screenshots stays legible.
                # 2x prefit canvas preserves fine detail and avoids cropping.
                if video_mode == "preserve":
                    _kb_sub_effects = [
                        "zoompan=z='min(zoom+0.00008,1.03)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
                        "zoompan=z='1.02':x='if(lte(on,1),iw*0.01,min(iw*0.03,x+0.12))':y='ih/2-(ih/zoom/2)'",
                        "zoompan=z='1.02':x='if(lte(on,1),iw*0.03,max(iw*0.01,x-0.12))':y='ih/2-(ih/zoom/2)'",
                        "zoompan=z='1.01':x='iw/2-(iw/zoom/2)':y='if(lte(on,1),ih*0.01,min(ih*0.03,y+0.10))'",
                    ]
                else:
                    _kb_sub_effects = [
                        "zoompan=z='min(zoom+0.001,1.15)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
                        "zoompan=z='if(lte(zoom,1.0),1.15,max(1.001,zoom-0.001))':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
                        "zoompan=z='1.08':x='if(lte(on,1),0,x+0.8)':y='ih/2-(ih/zoom/2)'",
                        "zoompan=z='1.08':x='if(lte(on,1),iw-(iw/zoom),max(0,x-0.8))':y='ih/2-(ih/zoom/2)'",
                    ]
                _sub_fps = VIDEO_FPS
                _sub_frames = max(1, int(sub_duration * _sub_fps))
                _kb = _kb_sub_effects[img_idx % len(_kb_sub_effects)]
                prepared_sub = scene_dir / f"scene_{index:02d}_sub{img_idx:02d}_canvas.png"
                kb_source = img_path
                try:
                    kb_source = await _prepare_image_canvas(img_path, prepared_sub, width * 2, height * 2, sharpen_amount=1.0)
                except Exception as exc:
                    logging.warning("Video job %s scene %s sub %s prefit failed: %s", job_id, index + 1, img_idx, exc)
                _sub_vf = (
                    f"{_kb}:d={_sub_frames}:s={width}x{height}:fps={_sub_fps},"
                    f"format=yuv420p"
                )
                ffcmd = [
                    "ffmpeg", "-y", "-loop", "1", "-framerate", str(_sub_fps), "-i", str(kb_source),
                    "-vf", _sub_vf,
                    "-t", str(sub_duration), "-r", str(_sub_fps),
                    "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                    str(sub_clip_path),
                ]
                proc = await asyncio.create_subprocess_exec(*ffcmd, stderr=asyncio.subprocess.PIPE)
                _, ffstderr = await proc.communicate()
                if proc.returncode != 0:
                    logging.error("ffmpeg sub-clip %s failed: %s", img_idx, ffstderr.decode())
                    continue

            if sub_clip_path.exists():
                sub_clips.append(sub_clip_path)

        if sub_clips:
            # Use a longer transition so image changes feel cinematic instead of slide-like.
            # For short sub-clips (~2s) this lands around 0.7s; for longer clips it caps at 1.0s.
            _XFADE_DUR = max(0.6, min(1.0, sub_duration * 0.35))
            if len(sub_clips) == 1:
                # Only one sub-clip — just mux audio directly
                single = sub_clips[0]
                if audio_path:
                    cc = [
                        "ffmpeg", "-y", "-i", str(single), "-i", str(audio_path),
                        "-filter_complex", "[1:a]apad[aout]",
                        "-map", "0:v", "-map", "[aout]",
                        "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                        "-c:a", "aac", "-t", str(actual_duration), str(clip_path),
                    ]
                else:
                    cc = [
                        "ffmpeg", "-y", "-i", str(single),
                        "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                        "-t", str(actual_duration), str(clip_path),
                    ]
            elif len(sub_clips) <= 12:
                # Longer fade transitions between each sub-clip for a more natural flow.
                inputs_flat = [item for p in sub_clips for item in ("-i", str(p))]
                fg_parts = []
                last_label = "[0:v]"
                for _xi in range(1, len(sub_clips)):
                    offset = _xi * sub_duration - _xi * _XFADE_DUR
                    out_label = f"[xv{_xi}]" if _xi < len(sub_clips) - 1 else "[vout]"
                    fg_parts.append(
                        f"{last_label}[{_xi}:v]xfade=transition=fade:duration={_XFADE_DUR}:offset={offset:.3f}{out_label}"
                    )
                    last_label = out_label
                n = len(sub_clips)
                if audio_path:
                    fg = ";".join(fg_parts) + f";[{n}:a]apad[aout]"
                    cc = (
                        ["ffmpeg", "-y"] + inputs_flat + ["-i", str(audio_path)]
                        + ["-filter_complex", fg]
                        + ["-map", "[vout]", "-map", "[aout]"]
                        + ["-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                           "-c:a", "aac", "-t", str(actual_duration), str(clip_path)]
                    )
                else:
                    fg = ";".join(fg_parts)
                    cc = (
                        ["ffmpeg", "-y"] + inputs_flat
                        + ["-filter_complex", fg]
                        + ["-map", "[vout]"]
                        + ["-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                           "-t", str(actual_duration), str(clip_path)]
                    )
            else:
                # More than 12 sub-clips: simple concat (xfade filter chain would be too large)
                concat_file = scene_dir / f"scene_{index:02d}_concat.txt"
                concat_file.write_text("\n".join(f"file '{p}'" for p in sub_clips))
                if audio_path:
                    cc = [
                        "ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", str(concat_file),
                        "-i", str(audio_path),
                        "-filter_complex", "[1:a]apad[aout]",
                        "-map", "0:v", "-map", "[aout]",
                        "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                        "-c:a", "aac", "-t", str(actual_duration), str(clip_path),
                    ]
                else:
                    cc = [
                        "ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", str(concat_file),
                        "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                        "-t", str(actual_duration), str(clip_path),
                    ]
            proc = await asyncio.create_subprocess_exec(*cc, stderr=asyncio.subprocess.PIPE)
            _, ccerr = await proc.communicate()
            if proc.returncode == 0 and clip_path.exists():
                logging.info("Video job %s scene %s: %d sub-clips concatenated OK (dissolve)", job_id, index + 1, len(sub_clips))
                return await _finalize_scene_clip(clip_path, scene, index, actual_duration, width=width, height=height)
            logging.error("Video job %s scene %s concat failed: %s", job_id, index + 1, ccerr.decode())
        # fall through to single-image path if something went wrong

    # ── Single-image / text-only path ─────────────────────────────────────────
    video_mode = _resolve_video_mode(scene)
    use_comfyui = video_mode == "animate" and input_mode in ("text", "image", "both") and (scene_prompt or image_path)
    if use_comfyui:
        try:
            if await _comfyui_available():
                logging.info("Video job %s scene %s: using ComfyUI (mode=%s)", job_id, index + 1, input_mode)
                # For 'image' mode with no text prompt, use an empty prompt (img2vid)
                effective_prompt = scene_prompt
                # For text-only mode, don't pass image (even if one was uploaded)
                effective_image = None if input_mode == "text" else image_path
                rendered_clip = await _comfyui_render_scene(
                    job_id=job_id,
                    prompt=effective_prompt,
                    image_path=effective_image,
                    clip_path=clip_path,
                    target_duration=actual_duration,
                    audio_path=audio_path,
                    seed=hash(f"{job_id}_{index}") % (2 ** 31),
                    out_w=width,
                    out_h=height,
                )
                return await _finalize_scene_clip(rendered_clip, scene, index, actual_duration, width=width, height=height)
            else:
                logging.warning(
                    "Video job %s scene %s: ComfyUI not available, falling back to ffmpeg",
                    job_id, index + 1,
                )
        except Exception as exc:
            logging.warning(
                "Video job %s scene %s: ComfyUI render failed (%s), falling back to ffmpeg",
                job_id, index + 1, exc,
            )

    # ── Fallback: ffmpeg Ken Burns zoom/pan or text-card ─────────────────────
    # NOTE: never use -shortest — it clips the video to audio length.
    # Instead use -t {actual_duration} + apad to pad silence when VO is shorter.
    logging.info("Video job %s scene %s: using ffmpeg fallback (mode=%s)", job_id, index + 1, input_mode)
    if image_path:
        # Ken Burns effect — zoom capped at 1.15x to keep text in screenshots legible.
        # 2x prefit canvas gives enough resolution for the pan/zoom without cropping.
        if video_mode == "preserve":
            _kb_effects = [
                "zoompan=z='min(zoom+0.00008,1.03)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
                "zoompan=z='1.02':x='if(lte(on,1),iw*0.01,min(iw*0.03,x+0.12))':y='ih/2-(ih/zoom/2)'",
                "zoompan=z='1.02':x='if(lte(on,1),iw*0.03,max(iw*0.01,x-0.12))':y='ih/2-(ih/zoom/2)'",
                "zoompan=z='1.01':x='iw/2-(iw/zoom/2)':y='if(lte(on,1),ih*0.01,min(ih*0.03,y+0.10))'",
            ]
        else:
            _kb_effects = [
                "zoompan=z='min(zoom+0.001,1.15)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
                "zoompan=z='if(lte(zoom,1.0),1.15,max(1.001,zoom-0.001))':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'",
                "zoompan=z='1.08':x='if(lte(on,1),0,x+0.8)':y='ih/2-(ih/zoom/2)'",
                "zoompan=z='1.08':x='if(lte(on,1),iw-(iw/zoom),max(0,x-0.8))':y='ih/2-(ih/zoom/2)'",
            ]
        fps = VIDEO_FPS
        total_frames = int(actual_duration * fps)
        kb = _kb_effects[index % len(_kb_effects)]
        prepared_single = scene_dir / f"scene_{index:02d}_canvas.png"
        kb_source = image_path
        try:
            kb_source = await _prepare_image_canvas(image_path, prepared_single, width * 2, height * 2, sharpen_amount=1.0)
        except Exception as exc:
            logging.warning("Video job %s scene %s prefit failed: %s", job_id, index + 1, exc)
        vf = (
            f"{kb}:d={total_frames}:s={width}x{height}:fps={fps},"
            f"format=yuv420p"
        )
        if audio_path:
            command = [
                "ffmpeg", "-y", "-loop", "1", "-framerate", str(fps), "-i", str(kb_source),
                "-i", str(audio_path),
                "-filter_complex", "[1:a]apad[aout]",
                "-map", "0:v", "-map", "[aout]",
                "-vf", vf, "-t", str(actual_duration),
                "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p", "-c:a", "aac",
                str(clip_path),
            ]
        else:
            command = [
                "ffmpeg", "-y", "-loop", "1", "-framerate", str(fps), "-i", str(kb_source),
                "-vf", vf, "-t", str(actual_duration),
                "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                str(clip_path),
            ]
    else:
        # Wrap into multiple drawtext filters (one per line) — FFmpeg drawtext
        # does not interpret \n escape sequences in the text= parameter.
        _fallback_lines = _wrap_text_lines(
            scene_prompt or voiceover_text or scene_title, fontsize=36, video_width=1280
        )
        _fallback_fs = 36
        _fallback_step = _fallback_fs + 8
        _fallback_block_h = len(_fallback_lines) * _fallback_step
        _fallback_font_prefix = f"drawtext=fontfile={VIDEO_FONT_FILE}:" if Path(VIDEO_FONT_FILE).exists() else "drawtext="
        _fallback_block_top = max(20, (height - _fallback_block_h) // 2)
        _fallback_drawtext_parts = [
            _fallback_font_prefix
            + f"text='{_line}':fontcolor=white:fontsize={_fallback_fs}:"
            + f"x=(w-text_w)/2:y={_fallback_block_top + _li * _fallback_step}:"
            + f"fix_bounds=1:box=1:boxcolor=0x00000088:boxborderw=24"
            for _li, _line in enumerate(_fallback_lines)
        ]
        drawtext = ",".join(_fallback_drawtext_parts)
        if audio_path:
            command = [
                "ffmpeg", "-y", "-f", "lavfi",
                "-i", f"color=c=0x111827:s={width}x{height}:r={VIDEO_FPS}:d={actual_duration}",
                "-i", str(audio_path),
                "-filter_complex", "[1:a]apad[aout]",
                "-map", "0:v", "-map", "[aout]",
                "-vf", f"{drawtext},format=yuv420p", "-t", str(actual_duration),
                "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p", "-c:a", "aac",
                str(clip_path),
            ]
        else:
            command = [
                "ffmpeg", "-y", "-f", "lavfi",
                "-i", f"color=c=0x111827:s={width}x{height}:r={VIDEO_FPS}:d={actual_duration}",
                "-vf", f"{drawtext},format=yuv420p", "-t", str(actual_duration),
                "-c:v", "libx264", "-preset", "veryfast", "-pix_fmt", "yuv420p",
                str(clip_path),
            ]

    await _run_command(command)
    return await _finalize_scene_clip(clip_path, scene, index, actual_duration, width=width, height=height)

async def _process_video_job(job_id: str):
    job = _load_video_job(job_id)
    aspect_ratio = job.get("aspect_ratio", "16:9")
    output_quality = (job.get("settings") or {}).get("output_quality") or job.get("output_quality") or "hd"
    width, height = _output_dimensions(aspect_ratio, output_quality)
    logging.info("Video job %s: quality=%s output=%dx%d", job_id, output_quality, width, height)
    scenes = job.get("scenes") or []
    scene_dir = VIDEO_TEMP_DIR / job_id
    scene_dir.mkdir(parents=True, exist_ok=True)

    # Inject global avatar config into any scene that doesn't override it
    global_avatar = (job.get("settings") or {}).get("avatar") or {}
    if global_avatar.get("id") and global_avatar["id"] != "none":
        for sc in scenes:
            if not (sc.get("avatar") or {}).get("id"):
                sc["avatar"] = global_avatar

    try:
        _update_video_job(job_id, status="processing", progress=5, started_at=_utc_timestamp(),
                          scenes_total=len(scenes), current_scene=0, current_scene_title="Starting…")
        clip_paths = []
        for index, scene in enumerate(scenes):
            scene_title = (scene.get("title") or f"Scene {index + 1}").strip()
            _update_video_job(
                job_id,
                current_scene=index + 1,
                current_scene_title=scene_title,
                scenes_total=len(scenes),
                progress=min(10 + int((index / max(1, len(scenes))) * 78), 88),
            )
            clip_path = await _render_scene_clip(
                job_id,
                scene,
                index,
                scene_dir,
                width,
                height,
                job.get("language", "en"),
                job.get("speaker", ""),
            )
            clip_paths.append(clip_path)
            _update_video_job(
                job_id,
                progress=min(90, int(((index + 1) / max(1, len(scenes))) * 80) + 10),
                current_scene=index + 1,
                current_scene_title=f"{scene_title} ✓",
                scenes_total=len(scenes),
                scenes=scenes,
            )
            # Brief GPU rest between scenes so Ollama chat inference can respond.
            if index < len(scenes) - 1 and COMFYUI_INTER_SCENE_DELAY > 0:
                logging.info("Video job %s: inter-scene GPU rest %.1fs", job_id, COMFYUI_INTER_SCENE_DELAY)
                await asyncio.sleep(COMFYUI_INTER_SCENE_DELAY)

        if not clip_paths:
            raise RuntimeError("No renderable scenes were supplied")

        concat_file = scene_dir / "concat.txt"
        concat_file.write_text(
            "\n".join([f"file '{path}'" for path in clip_paths]),
            encoding="utf-8",
        )

        output_path = VIDEO_OUTPUT_DIR / f"{job_id}.mp4"
        await _run_command([
            "ffmpeg",
            "-y",
            "-f",
            "concat",
            "-safe",
            "0",
            "-i",
            str(concat_file),
            "-c:v",
            "libx264",
            "-preset",
            "veryfast",
            "-pix_fmt",
            "yuv420p",
            "-c:a",
            "aac",
            "-movflags",
            "+faststart",
            str(output_path),
        ])

        _update_video_job(
            job_id,
            status="completed",
            progress=100,
            output_video_path=str(output_path),
            output_video_url=f"{VIDEO_PUBLIC_BASE_URL.rstrip('/')}/{output_path.name}",
            completed_at=_utc_timestamp(),
            render_mode="storyboard-composer",
        )
    except Exception as exc:
        logging.exception("Video job failed: %s", job_id)
        _update_video_job(
            job_id,
            status="failed",
            error_message=str(exc),
            completed_at=_utc_timestamp(),
        )
    finally:
        shutil.rmtree(scene_dir, ignore_errors=True)

# Helper functions for llama.cpp server management
async def start_llamacpp_server(model_path: str) -> bool:
    """Start llama-server with the specified model"""
    global llamacpp_server_process, current_llamacpp_model
    
    # If server is already running with the same model, return True
    if llamacpp_server_process and current_llamacpp_model == model_path:
        try:
            # Check if server is still responding
            async with httpx.AsyncClient(timeout=5) as client:
                response = await client.get(f"{LLAMACPP_SERVER_URL}/health")
                if response.status_code == 200:
                    logging.info(f"llama-server already running with model: {Path(model_path).name}")
                    return True
        except:
            pass  # Server not responding, will restart
    
    # Stop existing server if running
    await stop_llamacpp_server()
    
    try:
        # Set up environment with LD_LIBRARY_PATH for shared libraries
        env = os.environ.copy()
        lib_dir = Path(LLAMACPP_SERVER_BINARY).parent  # build/bin directory
        env["LD_LIBRARY_PATH"] = f"{lib_dir}:{env.get('LD_LIBRARY_PATH', '')}"
        
        # Start llama-server
        cmd = [
            LLAMACPP_SERVER_BINARY,
            "-m", model_path,
            "--port", str(LLAMACPP_SERVER_PORT),
            "--host", "127.0.0.1",
            "--ctx-size", "4096",
            "--n-predict", "-1",  # unlimited tokens
            "--threads", "4",
            "--no-warmup"
        ]
        
        logging.info(f"Starting llama-server on port {LLAMACPP_SERVER_PORT} with model: {Path(model_path).name}")
        
        llamacpp_server_process = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
            env=env
        )
        
        # Wait for server to start (check health endpoint)
        max_attempts = 30
        for attempt in range(max_attempts):
            try:
                await asyncio.sleep(1)
                async with httpx.AsyncClient(timeout=5) as client:
                    response = await client.get(f"{LLAMACPP_SERVER_URL}/health")
                    if response.status_code == 200:
                        current_llamacpp_model = model_path
                        logging.info(f"llama-server started successfully on port {LLAMACPP_SERVER_PORT}")
                        return True
            except:
                if attempt == max_attempts - 1:
                    logging.error(f"llama-server failed to start after {max_attempts} attempts")
                    await stop_llamacpp_server()
                    return False
                continue
        
        return False
        
    except Exception as e:
        logging.error(f"Failed to start llama-server: {str(e)}")
        await stop_llamacpp_server()
        return False

async def stop_llamacpp_server():
    """Stop the running llama-server"""
    global llamacpp_server_process, current_llamacpp_model
    
    if llamacpp_server_process:
        try:
            llamacpp_server_process.terminate()
            await asyncio.wait_for(llamacpp_server_process.wait(), timeout=10)
        except asyncio.TimeoutError:
            llamacpp_server_process.kill()
            await llamacpp_server_process.wait()
        except Exception as e:
            logging.error(f"Error stopping llama-server: {str(e)}")
        
        llamacpp_server_process = None
        current_llamacpp_model = None
        logging.info("llama-server stopped")

async def check_vastai_health() -> bool:
    """Check if the vast.ai tunnel is responding on OLLAMA_URL_VASTAI."""
    try:
        async with httpx.AsyncClient(timeout=3.0) as client:
            resp = await client.get(f"{OLLAMA_URL_VASTAI}/api/tags")
            return resp.status_code == 200
    except Exception:
        return False

async def restart_vastai_tunnel(reason: str) -> None:
    """Attempt to restart the SSH tunnel using the local autossh script."""
    global _vastai_last_restart
    now = time.time()
    if now - _vastai_last_restart < VASTAI_RESTART_COOLDOWN:
        logging.info(
            "Vast.ai tunnel restart skipped (cooldown %ss). reason=%s",
            VASTAI_RESTART_COOLDOWN,
            reason,
        )
        return

    async with _vastai_restart_lock:
        now = time.time()
        if now - _vastai_last_restart < VASTAI_RESTART_COOLDOWN:
            return
        _vastai_last_restart = now

        if not Path(VASTAI_TUNNEL_SCRIPT).exists():
            logging.error("Vast.ai tunnel script not found: %s", VASTAI_TUNNEL_SCRIPT)
            return

        logging.warning("Restarting Vast.ai tunnel. reason=%s", reason)
        try:
            proc = await asyncio.create_subprocess_exec(
                "bash",
                VASTAI_TUNNEL_SCRIPT,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
            )
            stdout, stderr = await proc.communicate()
            if proc.returncode != 0:
                logging.error(
                    "Vast.ai tunnel restart failed code=%s stderr=%s",
                    proc.returncode,
                    (stderr or b"").decode("utf-8", errors="ignore"),
                )
            else:
                logging.info("Vast.ai tunnel restart completed.")
        except Exception as e:
            logging.error("Vast.ai tunnel restart exception: %s", str(e))

async def periodic_embed_keepalive() -> None:
    """Ping nomic-embed-text every 4 minutes to keep it loaded in local Ollama RAM.
    Prevents the 15-second cold-start that occurs when the model is evicted."""
    await asyncio.sleep(30)  # Wait for initial warmup to complete first
    while True:
        try:
            async with httpx.AsyncClient(timeout=30.0) as client:
                resp = await client.post(
                    f"{OLLAMA_URL}/api/embeddings",
                    json={"model": DEFAULT_EMBED_MODEL, "prompt": "keepalive", "keep_alive": "24h"}
                )
                if resp.status_code == 200:
                    logging.debug(f"embed keepalive ping ok model={DEFAULT_EMBED_MODEL}")
                else:
                    logging.warning(f"embed keepalive ping failed status={resp.status_code}")
        except Exception as e:
            logging.warning(f"embed keepalive ping error: {e}")
        await asyncio.sleep(240)  # ping every 4 minutes


async def periodic_vastai_healthcheck() -> None:
    """Periodically verify the vast.ai tunnel and restart if needed."""
    if not VASTAI_HEALTHCHECK_ENABLED:
        logging.info("Vast.ai healthcheck disabled")
        return

    logging.info("Starting Vast.ai healthcheck interval=%ss", VASTAI_HEALTHCHECK_INTERVAL)
    consecutive_failures = 0
    while True:
        ok = await check_vastai_health()
        if ok:
            if consecutive_failures > 0:
                logging.info("Vast.ai tunnel healthy again")
            consecutive_failures = 0
        else:
            consecutive_failures += 1
            logging.warning("Vast.ai healthcheck failed count=%s", consecutive_failures)
            if consecutive_failures >= 2:
                await restart_vastai_tunnel("healthcheck_failed")
        await asyncio.sleep(VASTAI_HEALTHCHECK_INTERVAL)

async def llamacpp_server_chat(messages: list) -> dict:
    """Send chat request to llama-server"""
    try:
        async with httpx.AsyncClient(timeout=60) as client:
            response = await client.post(f"{LLAMACPP_SERVER_URL}/v1/chat/completions", json={
                "model": "llama-model",  # llama-server ignores this, uses loaded model
                "messages": messages,
                "stream": False,
                "temperature": 0.7,
                "top_p": 0.9,
                "max_tokens": 512
            })
            
            if response.status_code != 200:
                raise HTTPException(status_code=500, detail=f"llama-server error: {response.text}")
            
            result = response.json()
            
            # Convert OpenAI-compatible response to our format
            if "choices" in result and len(result["choices"]) > 0:
                choice = result["choices"][0]
                message_content = choice.get("message", {}).get("content", "")
                
                usage = result.get("usage") or {}
                # If llama-server does not report usage, estimate tokens (prompt+completion)
                if not usage or not all(k in usage for k in ("prompt_tokens", "completion_tokens", "total_tokens")):
                    # Approximate: 1 token ~ 4 chars
                    input_text = " ".join([m.get("content", "") for m in messages])
                    output_text = message_content or ""
                    prompt_tokens = max(1, len(input_text) // 4)
                    completion_tokens = max(1, len(output_text) // 4)
                    total_tokens = prompt_tokens + completion_tokens
                    usage = {
                        "prompt_tokens": prompt_tokens,
                        "completion_tokens": completion_tokens,
                        "total_tokens": total_tokens
                    }
                return {
                    "message": {"content": message_content, "role": "assistant"},
                    "usage": usage
                }
            else:
                raise HTTPException(status_code=500, detail="Invalid response from llama-server")
                
    except Exception as e:
        logging.error(f"llama-server chat error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"llama-server chat failed: {str(e)}")

# Helper functions for llama.cpp support
async def download_gguf_model(model_repo_path: str) -> str:
    """Download GGUF model from Hugging Face if not already present"""
    if model_repo_path not in GGUF_MODELS:
        raise ValueError(f"Unknown model repository: {model_repo_path}")
    
    model_info = GGUF_MODELS[model_repo_path]
    local_path = Path(MODELS_DIR) / model_info["filename"]
    
    if local_path.exists():
        logging.info(f"GGUF model already exists: {local_path}")
        return str(local_path)
    
    logging.info(f"Downloading GGUF model: {model_repo_path}")
    
    try:
        async with httpx.AsyncClient(timeout=300) as client:  # 5 minute timeout for large downloads
            response = await client.get(model_info["url"], follow_redirects=True)
            response.raise_for_status()
            
            # Write to temporary file first, then move to final location
            with tempfile.NamedTemporaryFile(delete=False) as tmp_file:
                tmp_file.write(response.content)
                tmp_path = tmp_file.name
            
            shutil.move(tmp_path, local_path)
            logging.info(f"Downloaded GGUF model to: {local_path}")
            return str(local_path)
            
    except Exception as e:
        logging.error(f"Failed to download GGUF model {model_repo_path}: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to download model: {str(e)}")

async def run_llamacpp_chat(model_path: str, messages: list) -> dict:
    """Run llama.cpp chat inference"""
    if not Path(LLAMACPP_BINARY).exists():
        raise HTTPException(status_code=500, detail="llama.cpp binary not found")
    
    # Convert messages to prompt format
    prompt = ""
    for msg in messages:
        role = msg.get("role", "user")
        content = msg.get("content", "")
        if role == "system":
            prompt += f"System: {content}\n\n"
        elif role == "user":
            prompt += f"Human: {content}\n\n"
        elif role == "assistant":
            prompt += f"Assistant: {content}\n\n"
    
    prompt += "Assistant: "
    
    try:
        # Run llama.cpp with the model
        cmd = [
            LLAMACPP_BINARY,
            "-m", model_path,
            "-p", prompt,
            "--temp", "0.7",
            "--top-p", "0.9",
            "--repeat-penalty", "1.1",
            "-n", "512",  # max new tokens
            "--simple-io"
        ]
        
        logging.info(f"Running llama.cpp: {' '.join(cmd[:4])}...")  # Log first few args
        
        # Set up environment with LD_LIBRARY_PATH for shared libraries
        env = os.environ.copy()
        lib_dir = Path(LLAMACPP_BINARY).parent  # Same directory as the binary
        env["LD_LIBRARY_PATH"] = f"{lib_dir}:{env.get('LD_LIBRARY_PATH', '')}"
        
        process = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
            env=env
        )
        
        stdout, stderr = await process.communicate()
        
        if process.returncode != 0:
            error_msg = stderr.decode().strip()
            logging.error(f"llama.cpp error: {error_msg}")
            raise HTTPException(status_code=500, detail=f"llama.cpp inference failed: {error_msg}")
        
        output = stdout.decode().strip()
        
        # Extract just the generated response (after "Assistant: ")
        if "Assistant: " in output:
            response_text = output.split("Assistant: ")[-1].strip()
        else:
            response_text = output.strip()
        
        # Estimate usage tokens in cli mode as well
        input_text = " ".join([m.get("content", "") for m in messages])
        output_text = response_text or ""
        prompt_tokens = max(1, len(input_text) // 4)
        completion_tokens = max(1, len(output_text) // 4)
        total_tokens = prompt_tokens + completion_tokens

        return {
            "message": {"content": response_text, "role": "assistant"},
            "usage": {
                "prompt_tokens": prompt_tokens,
                "completion_tokens": completion_tokens,
                "total_tokens": total_tokens
            }
        }
        
    except Exception as e:
        logging.error(f"llama.cpp execution error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"llama.cpp execution failed: {str(e)}")

def cleanup_stuck_ollama_processes():
    """Observe Ollama runner processes without killing them.

    Inference runners can legitimately use high CPU for short bursts, and killing
    them can break active chat/stream responses.
    """
    try:
        for proc in psutil.process_iter(['pid', 'name', 'cmdline', 'create_time']):
            try:
                if 'ollama' in proc.info['name'] and 'runner' in ' '.join(proc.info['cmdline'] or []):
                    # Check CPU usage with a 1-second interval for accuracy
                    cpu_percent = proc.cpu_percent(interval=1.0)
                    runtime = time.time() - proc.info['create_time']

                    # Log all runner processes for debugging
                    logging.info(f"Ollama runner PID {proc.info['pid']}: CPU {cpu_percent:.1f}%, runtime {runtime:.0f}s")
                        
            except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
                continue
            
    except Exception as e:
        logging.error(f"Error during process cleanup: {str(e)}")

async def periodic_process_cleanup():
    """Background task to periodically clean up stuck processes"""
    while True:
        try:
            await asyncio.sleep(PROCESS_CHECK_INTERVAL)
            cleanup_stuck_ollama_processes()
        except Exception as e:
            logging.error(f"Error in periodic cleanup: {str(e)}")
            await asyncio.sleep(PROCESS_CHECK_INTERVAL)

@app.get("/health")
async def health():
    uptime_sec = int(time.time() - SERVICE_START_TIME)
    
    # Count ollama runner processes
    runner_count = 0
    high_cpu_runners = 0
    try:
        for proc in psutil.process_iter(['name', 'cmdline']):
            try:
                if 'ollama' in proc.info['name'] and 'runner' in ' '.join(proc.info['cmdline'] or []):
                    runner_count += 1
                    # Get CPU usage with interval for accuracy
                    cpu_percent = proc.cpu_percent(interval=0.1)
                    if cpu_percent > MAX_OLLAMA_RUNNER_CPU:
                        high_cpu_runners += 1
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                continue
    except Exception:
        pass
    
    return {
        "status": "ok",
        "uptime_sec": uptime_sec,
        "model_warmed": MODEL_WARMED,
        "default_embed_model": DEFAULT_EMBED_MODEL,
        "fallback_embed_model": FALLBACK_EMBED_MODEL,
        "concurrency": EMBED_CONCURRENCY,
        "ollama_runners": runner_count,
        "high_cpu_runners": high_cpu_runners,
        "max_cpu_limit": MAX_OLLAMA_RUNNER_CPU,
        "max_runtime_limit": MAX_OLLAMA_RUNNER_TIME
    }

@app.post("/cleanup")
async def manual_cleanup():
    """Manually trigger cleanup of stuck Ollama processes"""
    try:
        cleanup_stuck_ollama_processes()
        return {"status": "cleanup_completed", "message": "Process cleanup triggered"}
    except Exception as e:
        logging.error(f"Manual cleanup failed: {str(e)}")
        return {"status": "error", "message": str(e)}

async def _generate_embedding(model: str, text: str, start_time: float):
    """Single embedding via /api/embeddings — used for real-time chat queries."""
    async with embed_semaphore:
        embed_url = get_embedding_ollama_url(model)
        async with httpx.AsyncClient(timeout=EMBED_TIMEOUT_SEC) as client:
            resp = await client.post(
                f"{embed_url}/api/embeddings",
                json={"model": model, "prompt": text, "keep_alive": "1h"}
            )
            if resp.status_code != 200:
                raise HTTPException(status_code=500, detail=f"Ollama API error ({model}): {resp.text}")
            result = resp.json()
            if "embedding" not in result:
                raise HTTPException(status_code=500, detail=f"No embedding field in response ({model})")
            elapsed_ms = int((time.time() - start_time) * 1000)
            return result["embedding"], elapsed_ms


async def _generate_embeddings_batch(texts: list, model: str, start_time: float):
    """Batch embedding via /api/embed — accepts array input, returns list of vectors.
    
    ~3x faster than sequential single calls for bulk sync operations because:
    - One HTTP round-trip per batch instead of one per item
    - nomic-embed-text processes ~50ms/item regardless of batch size on CPU
    - For 3000 items: 30 calls × 5s = 2.5min vs 3000 × 140ms = 7min sequential
    """
    async with embed_semaphore:
        embed_url = get_embedding_ollama_url(model)
        async with httpx.AsyncClient(timeout=EMBED_TIMEOUT_SEC * len(texts)) as client:
            resp = await client.post(
                f"{embed_url}/api/embed",
                json={"model": model, "input": texts}
            )
            if resp.status_code != 200:
                raise HTTPException(status_code=500, detail=f"Ollama batch embed error ({model}): {resp.text}")
            result = resp.json()
            if "embeddings" not in result:
                raise HTTPException(status_code=500, detail=f"No embeddings field in batch response ({model})")
            elapsed_ms = int((time.time() - start_time) * 1000)
            return result["embeddings"], elapsed_ms


@app.post("/embed")
async def embed(request: Request):
    try:
        data = await request.json()
        text = data["text"]
        requested_model = data.get("model")
        model = requested_model or DEFAULT_EMBED_MODEL
        start_time = time.time()

    # Truncation disabled: embed full text for better context
    # If embedding timeouts or model errors occur, re-enable below:
    # if len(text) > MAX_EMBED_CHARS:
    #     text = text[:MAX_EMBED_CHARS]

        # Quick health check against local Ollama only; embeddings should not depend on Vast.ai.
        embed_health_url = get_embedding_ollama_url(model)
        async with httpx.AsyncClient(timeout=5.0) as client:
            try:
                health_resp = await client.get(f"{embed_health_url}/api/tags")
                if health_resp.status_code != 200:
                    raise HTTPException(status_code=503, detail="Ollama service not available")
            except Exception:
                raise HTTPException(status_code=503, detail="Ollama service not responding")

        used_model = model
        try:
            embedding, elapsed_ms = await _generate_embedding(model, text, start_time)
        except HTTPException as he:
            if model != FALLBACK_EMBED_MODEL:
                try:
                    embedding, elapsed_ms = await _generate_embedding(FALLBACK_EMBED_MODEL, text, start_time)
                    used_model = FALLBACK_EMBED_MODEL
                except Exception:
                    raise he
            else:
                raise

        logging.info(f"embed chars={len(text)} model={used_model} ms={elapsed_ms}")
        return {"embedding": embedding, "model": used_model, "elapsed_ms": elapsed_ms, "chars": len(text)}
    except httpx.TimeoutException:
        raise HTTPException(status_code=408, detail="Ollama embedding timeout")
    except httpx.RequestError as e:
        raise HTTPException(status_code=503, detail=f"Ollama connection error: {str(e)}")
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Embedding generation error: {str(e)}")

@app.post("/rewrite")
async def rewrite_endpoint(request: Request):
    """Rewrite a user query into a concise unambiguous form using local quantized llama.cpp model."""
    try:
        if rewrite_prompt is None:
            raise HTTPException(status_code=503, detail=f"Rewrite model unavailable: {rewrite_import_error}")
        data = await request.json()
        text = data.get("text")
        if not text or not isinstance(text, str):
            raise HTTPException(status_code=400, detail="'text' must be a non-empty string")
        rewritten = await asyncio.get_event_loop().run_in_executor(None, rewrite_prompt, text)
        return {"rewrite": rewritten}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Rewrite failed: {e}")

@app.websocket("/ws/rewrite")
async def ws_rewrite(ws: WebSocket):
    await ws.accept()
    try:
        if rewrite_prompt is None:
            await ws.send_text(f"ERROR: rewrite model unavailable: {rewrite_import_error}")
            await ws.close()
            return
        while True:
            try:
                text = await ws.receive_text()
            except WebSocketDisconnect:
                break
            if not text.strip():
                await ws.send_text("")
                continue
            # Offload blocking llama.cpp call to thread pool
            rewritten = await asyncio.get_event_loop().run_in_executor(None, rewrite_prompt, text)
            await ws.send_text(rewritten)
    except Exception:
        try:
            await ws.close()
        except Exception:
            pass

@app.websocket("/ws/widget/chat")
async def ws_widget_chat(ws: WebSocket):
    await ws.accept()
    client_host = getattr(ws.client, "host", "unknown") if ws.client else "unknown"
    client_port = getattr(ws.client, "port", "unknown") if ws.client else "unknown"
    logging.info("Widget WS connected", extra={"client": f"{client_host}:{client_port}"})
    try:
        while True:
            try:
                payload = await ws.receive_json()
            except WebSocketDisconnect:
                logging.info("Widget WS disconnected", extra={"client": f"{client_host}:{client_port}"})
                break

            if payload.get("type") == "ping":
                await ws.send_text(json.dumps({"type": "pong"}))
                continue

            org_id = payload.get("org_id")
            session_id = payload.get("session_id")
            if not org_id:
                await ws.send_text(json.dumps({"error": True, "message": "org_id is required"}))
                continue

            logging.info(
                "Widget WS request received",
                extra={
                    "client": f"{client_host}:{client_port}",
                    "org_id": org_id,
                    "session_id": session_id,
                },
            )

            url = f"{LARAVEL_WIDGET_BASE_URL.rstrip('/')}/widget/{org_id}/chat/stream"
            headers = {"Accept": "text/event-stream"}

            try:
                async with httpx.AsyncClient(timeout=None) as client:
                    async with client.stream("POST", url, json=payload, headers=headers) as resp:
                        logging.info(
                            "Widget WS upstream response",
                            extra={
                                "org_id": org_id,
                                "session_id": session_id,
                                "status": resp.status_code,
                            },
                        )
                        if resp.status_code == 429:
                            body = await resp.aread()
                            await ws.send_text(json.dumps({"error": True, "status": 429, "body": body.decode(errors="ignore")}))
                            continue
                        if resp.status_code != 200:
                            body = await resp.aread()
                            await ws.send_text(json.dumps({"error": True, "status": resp.status_code, "body": body.decode(errors="ignore")}))
                            continue

                        sent_chunks = 0
                        first_chunk_sent = False
                        async for line in resp.aiter_lines():
                            if not line:
                                continue
                            if line.startswith("data: "):
                                data = line[6:].strip()
                                if data:
                                    await ws.send_text(data)
                                    sent_chunks += 1
                                    if not first_chunk_sent:
                                        first_chunk_sent = True
                                        logging.info(
                                            "Widget WS first chunk forwarded",
                                            extra={
                                                "org_id": org_id,
                                                "session_id": session_id,
                                            },
                                        )
                        logging.info(
                            "Widget WS stream complete",
                            extra={
                                "org_id": org_id,
                                "session_id": session_id,
                                "chunks": sent_chunks,
                            },
                        )
            except Exception as e:
                logging.exception("Widget WS proxy error", extra={"org_id": org_id, "session_id": session_id})
                await ws.send_text(json.dumps({"error": True, "message": f"Proxy error: {e}"}))
    except Exception:
        try:
            logging.exception("Widget WS handler error", extra={"client": f"{client_host}:{client_port}"})
            await ws.close()
        except Exception:
            pass

@app.post("/qdrant/create_collection")
async def create_collection(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    vector_size = data.get("vector_size", 768)  # Default for nomic-embed-text
    try:
        qdrant.create_collection(
            collection_name=collection_name,
            vectors_config=VectorParams(size=vector_size, distance=Distance.COSINE)
        )
        return {"status": "success", "message": f"Collection {collection_name} created"}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.delete("/qdrant/delete_collection")
async def delete_collection(request: Request):
    """Delete a collection from Qdrant"""
    data = await request.json()
    collection_name = data["collection_name"]
    try:
        # Check if collection exists before trying to delete
        collections = qdrant.get_collections()
        collection_names = [c.name for c in collections.collections]
        
        if collection_name not in collection_names:
            logging.warning(f"Collection {collection_name} not found in Qdrant")
            return {"status": "success", "message": f"Collection {collection_name} does not exist (already deleted)"}
        
        # Delete the collection
        qdrant.delete_collection(collection_name=collection_name)
        logging.info(f"Collection {collection_name} deleted successfully")
        return {"status": "success", "message": f"Collection {collection_name} deleted"}
    except Exception as e:
        logging.error(f"Error deleting collection {collection_name}: {str(e)}")
        raise HTTPException(status_code=400, detail=str(e))

@app.get("/qdrant/collections")
async def list_collections():
    """List all collections in Qdrant"""
    try:
        collections = qdrant.get_collections()
        collection_list = []
        
        for collection in collections.collections:
            try:
                # Get collection info including point count
                info = qdrant.get_collection(collection.name)
                collection_list.append({
                    "name": collection.name,
                    "points_count": info.points_count,
                    "status": info.status,
                    "vector_size": info.config.params.vectors.size if hasattr(info.config.params, 'vectors') else None
                })
            except Exception as e:
                collection_list.append({
                    "name": collection.name,
                    "error": str(e)
                })
        
        return {
            "status": "success",
            "collections": collection_list,
            "total_collections": len(collection_list)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to list collections: {str(e)}")

@app.get("/qdrant/collections/{collection_name}")
async def get_collection(collection_name: str):
    """Get specific collection info"""
    try:
        info = qdrant.get_collection(collection_name)
        return {
            "status": "success",
            "name": collection_name,
            "points_count": info.points_count,
            "collection_status": info.status,
            "vector_size": info.config.params.vectors.size if hasattr(info.config.params, 'vectors') else None,
            "distance": info.config.params.vectors.distance if hasattr(info.config.params, 'vectors') else None
        }
    except Exception as e:
        raise HTTPException(status_code=404, detail=f"Collection not found: {str(e)}")

@app.post("/qdrant/add")
async def add_to_qdrant(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    vector = data["vector"]
    payload = data["payload"]
    point_id = data.get("id", str(uuid.uuid4()))
    try:
        qdrant.upsert(
            collection_name=collection_name,
            points=[PointStruct(id=point_id, vector=vector, payload=payload)]
        )
        return {"status": "success", "id": point_id}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/qdrant/search")
async def search_qdrant(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    query_vector = data["query_vector"]
    limit = data.get("limit", 5)
    try:
        start_time = time.time()
        results = qdrant.search(
            collection_name=collection_name,
            query_vector=query_vector,
            limit=limit
        )
        elapsed_ms = int((time.time() - start_time) * 1000)
        logging.info(f"qdrant_search collection={collection_name} limit={limit} elapsed_ms={elapsed_ms}")
        return {
            "results": [{"id": r.id, "score": r.score, "payload": r.payload} for r in results],
            "elapsed_ms": elapsed_ms,
        }
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/qdrant/search_text")
async def search_qdrant_text(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    query_text = data["query_text"]
    limit = data.get("limit", 5)
    model = data.get("model", DEFAULT_EMBED_MODEL)
    
    try:
        # First generate embedding for the query text
        embed_start = time.time()
        query_vector, embed_ms = await _generate_embedding(model, query_text, embed_start)
        logging.info(
            f"qdrant_search_text embedding collection={collection_name} model={model} chars={len(query_text)} elapsed_ms={embed_ms}"
        )
        
        # Then search using the vector
        search_start = time.time()
        results = qdrant.search(
            collection_name=collection_name,
            query_vector=query_vector,
            limit=limit
        )
        search_ms = int((time.time() - search_start) * 1000)
        logging.info(f"qdrant_search_text collection={collection_name} limit={limit} elapsed_ms={search_ms}")
        return {
            "results": [{"id": r.id, "score": r.score, "payload": r.payload} for r in results],
            "embedding_elapsed_ms": embed_ms,
            "search_elapsed_ms": search_ms,
        }
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/qdrant/search_by_terms")
async def search_qdrant_by_terms(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    terms = data.get("terms", [])
    limit = int(data.get("limit", 10))

    if not isinstance(terms, list):
        raise HTTPException(status_code=422, detail="terms must be an array")

    normalized_terms = []
    for term in terms:
        t = str(term).strip()
        if len(t) >= 2:
            normalized_terms.append(t)

    if not normalized_terms:
        return {"results": []}

    try:
        start_time = time.time()
        by_id = {}

        for term in normalized_terms[:8]:
            term_variants = list(dict.fromkeys([
                term,
                term.lower(),
                term.title(),
            ]))

            for variant in term_variants:
                try:
                    exact_points, _ = qdrant.scroll(
                        collection_name=collection_name,
                        scroll_filter=Filter(must=[
                            FieldCondition(key="title", match=MatchValue(value=variant))
                        ]),
                        limit=max(limit, 20),
                        with_payload=True,
                        with_vectors=False,
                    )
                    for point in exact_points:
                        point_id = str(point.id)
                        payload = point.payload or {}
                        existing = by_id.get(point_id)
                        score = 1.0
                        if existing is None or score > existing["score"]:
                            by_id[point_id] = {
                                "id": point.id,
                                "score": score,
                                "payload": payload,
                            }
                except Exception:
                    continue

            try:
                text_points, _ = qdrant.scroll(
                    collection_name=collection_name,
                    scroll_filter=Filter(should=[
                        FieldCondition(key="entity", match=MatchText(text=term)),
                        FieldCondition(key="primary_entity", match=MatchText(text=term)),
                        FieldCondition(key="semantic_text", match=MatchText(text=term)),
                        FieldCondition(key="metadata.entity", match=MatchText(text=term)),
                        FieldCondition(key="metadata.primary_entity", match=MatchText(text=term)),
                        FieldCondition(key="metadata.semantic_text", match=MatchText(text=term)),
                        FieldCondition(key="metadata.csv.entity", match=MatchText(text=term)),
                        FieldCondition(key="metadata.csv.primary_entity", match=MatchText(text=term)),
                        FieldCondition(key="metadata.csv.semantic_text", match=MatchText(text=term)),
                        FieldCondition(key="title", match=MatchText(text=term)),
                        FieldCondition(key="content", match=MatchText(text=term)),
                        FieldCondition(key="keywords", match=MatchText(text=term)),
                        FieldCondition(key="search_keywords", match=MatchText(text=term)),
                        FieldCondition(key="metadata.keywords", match=MatchText(text=term)),
                        FieldCondition(key="metadata.search_keywords", match=MatchText(text=term)),
                        FieldCondition(key="metadata.csv.keywords", match=MatchText(text=term)),
                        FieldCondition(key="metadata.csv.search_keywords", match=MatchText(text=term)),
                        FieldCondition(key="question", match=MatchText(text=term)),
                        FieldCondition(key="answer", match=MatchText(text=term)),
                    ]),
                    limit=max(limit * 3, 30),
                    with_payload=True,
                    with_vectors=False,
                )
                for point in text_points:
                    point_id = str(point.id)
                    payload = point.payload or {}
                    existing = by_id.get(point_id)
                    score = 0.72
                    if existing is None or score > existing["score"]:
                        by_id[point_id] = {
                            "id": point.id,
                            "score": score,
                            "payload": payload,
                        }
            except Exception:
                # MatchText may not be available on older environments; exact-title path still works.
                continue

        results = list(by_id.values())
        results.sort(key=lambda item: item.get("score", 0), reverse=True)
        results = results[:limit]

        elapsed_ms = int((time.time() - start_time) * 1000)
        logging.info(
            f"qdrant_search_by_terms collection={collection_name} terms={len(normalized_terms)} results={len(results)} elapsed_ms={elapsed_ms}"
        )

        return {"results": results, "elapsed_ms": elapsed_ms}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/llm/answer")
async def llm_answer(request: Request):
    data = await request.json()
    prompt = data["prompt"]
    model = data.get("model", FALLBACK_EMBED_MODEL)
    timeout_seconds = 120.0 if str(model).strip().lower() == "llama3.2:3b" else 60.0
    try:
        async with httpx.AsyncClient(timeout=timeout_seconds) as client:
            ollama_url = get_ollama_url(model)
            resp = await client.post(f"{ollama_url}/api/generate", json={
                "model": model,
                "prompt": prompt,
                "stream": False
            })
            result = resp.json()
            return {"answer": result.get("response", "")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/extract")
async def extract_keyword(request: Request):
    """Extract specific information using LLM (e.g., product keywords)"""
    data = await request.json()
    prompt = data["prompt"]
    max_tokens = data.get("max_tokens", 20)
    model = data.get("model", FALLBACK_EMBED_MODEL)
    
    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            resp = await client.post(f"{OLLAMA_URL}/api/generate", json={
                "model": model,
                "prompt": prompt,
                "stream": False,
                "options": {
                    "num_predict": max_tokens,
                    "temperature": 0.1,  # Low temperature for consistent extraction
                    "top_k": 10,
                    "top_p": 0.5
                }
            })
            result = resp.json()
            extracted = result.get("response", "").strip()
            
            # Remove any explanation text after the keyword
            if '\n' in extracted:
                extracted = extracted.split('\n')[0].strip()
            
            return {"result": extracted}
    except Exception as e:
        logging.error(f"Extract endpoint error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/crawl/extract-attributes")
async def crawl_extract_attributes(request: Request):
    """
    LLM-powered structured attribute extractor for web crawler.

    Strips noisy HTML, then asks the LLM to extract a set of user-defined
    attributes as JSON.  Works for any page type (product, service, doctor,
    menu-item, property, article, faq, etc.).

    Request body:
      {
        "text":        "<cleaned page text>",
        "attributes":  ["name","price","artist","medium","size","color"],
        "page_type":   "product",                  // optional label
        "page_url":    "https://...",              // for logging only
        "model":       "llama3.2:3b"               // optional
      }

    Response:
      {
        "extracted": { "name": "...", "price": "...", ... },
        "flat_content": "name: ...\nprice: ...\n...",
        "model": "llama3.2:3b"
      }
    """
    data = await request.json()
    text = data.get("text", "").strip()
    attributes = data.get("attributes", [])
    page_type = data.get("page_type", "product")
    page_url = data.get("page_url", "")
    prompt_override = (data.get("prompt_override") or "").strip()
    # Model comes from caller, but we always prefer the dedicated crawl URL (vast.ai GPU).
    # The caller can still override by passing "crawl_llm_url" explicitly.
    model = resolve_crawl_model(data.get("model"))
    crawl_url = data.get("crawl_llm_url", CRAWL_LLM_URL)

    if not text:
        raise HTTPException(status_code=400, detail="text is required")
    if not attributes:
        raise HTTPException(status_code=400, detail="attributes list is required")

    # Truncate to avoid token overflow (~3500 chars is safe for 3b models)
    max_chars = 3500
    if len(text) > max_chars:
        text = text[:max_chars] + "..."

    attr_list_str = ", ".join(attributes)

    prompt = f"""You are a structured data extraction assistant. Your task is to extract specific attributes from a webpage.

Page type: {page_type}
URL: {page_url}

Extract ONLY these attributes: {attr_list_str}

RULES:
- Return ONLY a valid JSON object, no explanation, no markdown.
- If an attribute is not found, use null.
- Do not invent values. Use only what is in the text.
- Keep values concise.

Page text:
\"\"\"
{text}
\"\"\"

JSON output (keys must exactly match the attribute names given above):"""

    if prompt_override:
        prompt += f"\n\nAdditional extraction instructions:\n{prompt_override}"

    async def _do_extract(ollama_url: str, timeout: float = 45.0):
        async with httpx.AsyncClient(timeout=timeout) as client:
            return await client.post(f"{ollama_url}/api/generate", json={
                "model": model,
                "prompt": prompt,
                "stream": False,
                "options": {
                    "num_predict": 512,
                    "temperature": 0.05,
                    "top_k": 10,
                    "top_p": 0.5,
                    "stop": ["```", "\n\n\n"]
                }
            })

    try:
        resp = await _do_extract(crawl_url)
        logging.info(f"crawl/extract-attributes used crawl_url={crawl_url} model={model} url={page_url}")

        result = resp.json()
        raw = result.get("response", "").strip()

        # Parse JSON from response - be lenient about surrounding text
        extracted = {}
        json_match = re.search(r'\{.*\}', raw, re.DOTALL)
        if json_match:
            try:
                extracted = json.loads(json_match.group())
            except json.JSONDecodeError:
                # Try to fix common LLM JSON mistakes
                cleaned = re.sub(r',\s*}', '}', json_match.group())
                cleaned = re.sub(r',\s*]', ']', cleaned)
                try:
                    extracted = json.loads(cleaned)
                except Exception:
                    logging.warning(f"Could not parse LLM JSON for {page_url}: {raw[:200]}")

        # Remove null values and empty strings
        extracted = {k: v for k, v in extracted.items() if v not in (None, "", "null", "N/A", "n/a")}

        # Build a clean flat text representation for Qdrant embedding
        flat_lines = []
        for attr in attributes:
            val = extracted.get(attr)
            if val:
                flat_lines.append(f"{attr}: {val}")
        flat_content = "\n".join(flat_lines)

        return {
            "extracted": extracted,
            "flat_content": flat_content,
            "model": model
        }

    except Exception as e:
        logging.error(f"crawl/extract-attributes error for {page_url}: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/crawl/suggest-template")
async def crawl_suggest_template(request: Request):
    data = await request.json()
    title = (data.get("title") or "").strip()
    meta_description = (data.get("meta_description") or "").strip()
    headings = data.get("headings") or []
    text = (data.get("text") or "").strip()
    page_url = (data.get("page_url") or "").strip()
    current_page_type = (data.get("current_page_type") or "").strip()
    current_attributes = data.get("current_attributes") or []
    prompt_override = (data.get("prompt_override") or "").strip()
    model = resolve_crawl_model(data.get("model"))
    crawl_url = data.get("crawl_llm_url", CRAWL_LLM_URL)

    if not text:
        raise HTTPException(status_code=400, detail="text is required")

    if len(text) > 3500:
        text = text[:3500] + "..."

    headings_block = "\n".join(f"- {heading}" for heading in headings[:25])
    current_attributes_block = ", ".join(str(item) for item in current_attributes[:20])

    prompt = f"""You are helping an admin build a reusable website crawl template from one sample page.

Return ONLY valid JSON with this exact shape:
{{
  "page_type": "product|service|doctor|property|menu-item|medical-test|faq|article|event|course|general",
  "qdrant_data_type": "product|service|faq|info|webpage",
  "attribute_schema": ["field 1", "field 2"],
  "url_filter_pattern": "string or null",
  "summary": "short explanation"
}}

Rules:
- No markdown, no commentary, JSON only.
- Keep attribute_schema to 4-12 fields.
- Prefer fields that are likely to repeat on similar pages of the same site.
- Use concise lower-case field names.
- If current attributes are already good, refine them instead of replacing them wildly.
- url_filter_pattern should usually be a reusable path fragment like /products/ or /tests/ and not the full page URL.

Current hint for page type: {current_page_type or 'none'}
Current attributes: {current_attributes_block or 'none'}
URL: {page_url or 'unknown'}
Title: {title or 'unknown'}
Meta description: {meta_description or 'none'}
Headings:
{headings_block or '- none'}

Sample page text:
---
{text}
---
"""

    if prompt_override:
        prompt += f"\nAdditional admin instructions:\n{prompt_override}\n"

    async def _do_suggest(ollama_url: str, timeout: float = 45.0):
        async with httpx.AsyncClient(timeout=timeout) as client:
            return await client.post(f"{ollama_url}/api/generate", json={
                "model": model,
                "prompt": prompt,
                "stream": False,
                "options": {
                    "num_predict": 384,
                    "temperature": 0.05,
                    "top_k": 10,
                    "top_p": 0.5,
                    "stop": ["```", "\n\n\n"]
                }
            })

    try:
        resp = await _do_suggest(crawl_url)
        logging.info(f"crawl/suggest-template used crawl_url={crawl_url} model={model} url={page_url}")

        result = resp.json()
        raw = (result.get("response") or "").strip()
        json_match = re.search(r'\{.*\}', raw, re.DOTALL)
        if not json_match:
            raise ValueError("No JSON object found in suggestion response")

        parsed = json.loads(json_match.group())
        attribute_schema = parsed.get("attribute_schema") or []
        if not isinstance(attribute_schema, list):
            attribute_schema = []

        return {
            "page_type": parsed.get("page_type") or current_page_type or "general",
            "qdrant_data_type": parsed.get("qdrant_data_type") or "webpage",
            "attribute_schema": [str(item).strip() for item in attribute_schema if str(item).strip()][:12],
            "url_filter_pattern": parsed.get("url_filter_pattern"),
            "summary": (parsed.get("summary") or "").strip(),
            "model": model,
        }
    except Exception as e:
        logging.error(f"crawl/suggest-template error: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/embed_batch")
async def embed_batch(request: Request):
    """Batch embedding to reduce per-request overhead.
    Request JSON: {"texts": ["...", "..."], "model": optional}
    Returns: {"model": used_model, "results": [{"embedding": [...], "chars": n, "elapsed_ms": t}, ...]}
    """
    data = await request.json()
    texts = data.get("texts")
    if not isinstance(texts, list) or not texts:
        raise HTTPException(status_code=400, detail="'texts' must be a non-empty list")
    requested_model = data.get("model")
    model = requested_model or DEFAULT_EMBED_MODEL
    results = []
    overall_start = time.time()

    # Health check once
    async with httpx.AsyncClient(timeout=5.0) as client:
        try:
            health_resp = await client.get(f"{OLLAMA_URL}/api/tags")
            if health_resp.status_code != 200:
                raise HTTPException(status_code=503, detail="Ollama service not available")
        except Exception:
            raise HTTPException(status_code=503, detail="Ollama service not responding")

    used_model = model
    for t in texts:
        start_time = time.time()
        if not isinstance(t, str):
            results.append({"error": "not a string"})
            continue
    # Truncation disabled: embed full text for better context
    # If embedding timeouts or model errors occur, re-enable below:
    # if len(t) > MAX_EMBED_CHARS:
    #     t = t[:MAX_EMBED_CHARS]
        try:
            embedding, elapsed_ms = await _generate_embedding(used_model, t, start_time)
        except HTTPException as he:
            if used_model != FALLBACK_EMBED_MODEL:
                try:
                    embedding, elapsed_ms = await _generate_embedding(FALLBACK_EMBED_MODEL, t, start_time)
                    used_model = FALLBACK_EMBED_MODEL
                except Exception:
                    results.append({"error": str(he.detail)})
                    continue
            else:
                results.append({"error": str(he.detail)})
                continue
        results.append({"embedding": embedding, "chars": len(t), "elapsed_ms": elapsed_ms})
    total_ms = int((time.time() - overall_start) * 1000)
    logging.info(f"embed_batch count={len(texts)} model={used_model} total_ms={total_ms}")
    return {"model": used_model, "count": len(results), "total_ms": total_ms, "results": results}

_local_whisper_model = None


def _extract_json_object(raw_text: str) -> dict:
    cleaned = (raw_text or "").strip()
    if cleaned == "":
        return {}

    code_match = re.search(r"```(?:json)?\s*(\{.*?\})\s*```", cleaned, re.DOTALL)
    if code_match:
        cleaned = code_match.group(1).strip()

    try:
        return json.loads(cleaned)
    except Exception:
        pass

    first = cleaned.find("{")
    last = cleaned.rfind("}")
    if first != -1 and last != -1 and last > first:
        candidate = cleaned[first:last + 1]
        try:
            return json.loads(candidate)
        except Exception:
            return {}

    return {}


def _get_local_whisper_model():
    global _local_whisper_model
    if _local_whisper_model is not None:
        return _local_whisper_model

    try:
        from faster_whisper import WhisperModel  # type: ignore
    except Exception as e:
        raise RuntimeError(f"faster-whisper is not installed: {str(e)}")

    compute_type = os.getenv("LOCAL_WHISPER_COMPUTE_TYPE", "int8")
    device = os.getenv("LOCAL_WHISPER_DEVICE", "auto")

    cache_dir = LOCAL_WHISPER_CACHE_DIR
    os.makedirs(cache_dir, exist_ok=True)
    os.environ.setdefault("HF_HOME", cache_dir)
    os.environ.setdefault("HUGGINGFACE_HUB_CACHE", cache_dir)
    os.environ.setdefault("XDG_CACHE_HOME", cache_dir)

    logging.info(
        f"Loading local Whisper model={LOCAL_WHISPER_MODEL} device={device} compute_type={compute_type} cache_dir={cache_dir}"
    )
    _local_whisper_model = WhisperModel(
        LOCAL_WHISPER_MODEL,
        device=device,
        compute_type=compute_type,
        download_root=cache_dir,
    )
    return _local_whisper_model


async def _chat_completion(messages: list, model: str) -> dict:
    ollama_url = get_ollama_url(model)
    async with httpx.AsyncClient(timeout=PERSONAL_ASSISTANT_TIMEOUT_SEC) as client:
        payload = {
            "model": model,
            "messages": messages,
            "stream": False,
            "options": {
                "temperature": 0.2,
                "num_predict": 300
            }
        }
        if _should_disable_thinking(model):
            payload["think"] = False
        resp = await client.post(f"{ollama_url}/api/chat", json=payload)
        if resp.status_code != 200:
            raise RuntimeError(f"LLM HTTP {resp.status_code}: {resp.text}")
        result = _sanitize_chat_result(resp.json())
        if isinstance(result, dict) and result.get("error"):
            raise RuntimeError(f"LLM error: {result.get('error')}")
        return result


@app.post("/language/normalize-query")
async def normalize_query(request: Request):
    data = await request.json()
    query = (data.get("query") or "").strip()
    model = (data.get("model") or QUERY_NORMALIZATION_MODEL).strip() or QUERY_NORMALIZATION_MODEL
    use_vastai = bool(data.get("use_vastai", True)) and VASTAI_ENABLED

    if query == "":
        raise HTTPException(status_code=400, detail="Query is required")

    detection = _detect_query_language(query)
    should_normalize = bool(data.get("force_translate", False)) or _should_normalize_query(query, detection)
    normalized_query = _canonicalize_multilingual_support_query(query, detection)
    translation_error = None

    if should_normalize and normalized_query.strip() == query.strip():
        try:
            normalized_query = await _normalize_query_to_english(query, model, use_vastai=use_vastai)
        except Exception as exc:
            translation_error = str(exc)
            logging.warning("Query normalization failed, using original query: %s", exc)
            normalized_query = query

    return {
        "original_query": query,
        "normalized_query": normalized_query,
        "language": detection.get("language", "en"),
        "confidence": detection.get("confidence", 0.0),
        "detection_source": detection.get("source", "unknown"),
        "script": detection.get("script", "latin"),
        "used_translation": should_normalize and normalized_query.strip() != query.strip(),
        "translation_model": model,
        "translation_error": translation_error,
    }


@app.post("/voice/transcribe")
async def voice_transcribe(
    audio: UploadFile = File(...),
    language: str = Form("auto"),
    prompt: str = Form(""),
    provider: str = Form("auto")
):
    provider = (provider or "auto").strip().lower()
    language = (language or "auto").strip()
    prompt = (prompt or "").strip()

    audio_bytes = await audio.read()
    max_bytes = PERSONAL_ASSISTANT_MAX_AUDIO_MB * 1024 * 1024
    if not audio_bytes:
        raise HTTPException(status_code=400, detail="Audio payload is empty")
    if len(audio_bytes) > max_bytes:
        raise HTTPException(status_code=413, detail=f"Audio exceeds {PERSONAL_ASSISTANT_MAX_AUDIO_MB}MB limit")

    vast_whisper_error = None

    # Prefer tunneled Vast.ai whisper service
    if provider in ["auto", "vast", "whisper"]:
        try:
            async with httpx.AsyncClient(timeout=PERSONAL_ASSISTANT_TIMEOUT_SEC) as client:
                files = {
                    "audio": (
                        audio.filename or "speech.webm",
                        audio_bytes,
                        audio.content_type or "application/octet-stream",
                    )
                }
                form_data = {
                    "language": language,
                    "prompt": prompt,
                }
                resp = await client.post(PERSONAL_ASSISTANT_WHISPER_URL, data=form_data, files=files)
                if resp.status_code == 200:
                    payload = resp.json()
                    text = (payload.get("text") or "").strip()
                    if text != "":
                        return {
                            "text": text,
                            "language": payload.get("language", language),
                            "provider_used": "vast_whisper",
                            "meta": payload.get("meta", {}),
                        }
                vast_whisper_error = f"HTTP {resp.status_code}: {resp.text}"
        except Exception as e:
            vast_whisper_error = str(e)

        logging.warning(f"Vast Whisper transcription failed, attempting local fallback: {vast_whisper_error}")

    if provider not in ["auto", "local"]:
        raise HTTPException(status_code=502, detail="Requested speech provider unavailable")

    if not PERSONAL_ASSISTANT_ENABLE_LOCAL_FALLBACK and provider == "auto":
        raise HTTPException(
            status_code=503,
            detail="Voice transcription service is temporarily unavailable (Vast Whisper unreachable).",
        )

    # Local fallback: faster-whisper
    tmp_path = None
    try:
        model = _get_local_whisper_model()
        suffix = Path(audio.filename or "speech.webm").suffix or ".webm"
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp_file:
            tmp_file.write(audio_bytes)
            tmp_path = tmp_file.name

        transcribe_kwargs = {
            "beam_size": 5,
            "vad_filter": True,
        }
        if language.lower() != "auto":
            transcribe_kwargs["language"] = language
        if prompt:
            transcribe_kwargs["initial_prompt"] = prompt

        segments, info = model.transcribe(tmp_path, **transcribe_kwargs)
        text = " ".join([(segment.text or "").strip() for segment in segments]).strip()

        return {
            "text": text,
            "language": getattr(info, "language", language),
            "provider_used": "local_faster_whisper",
            "meta": {
                "duration": getattr(info, "duration", None),
                "language_probability": getattr(info, "language_probability", None),
            },
        }
    except Exception as e:
        logging.error(f"Local Whisper transcription failed: {str(e)}")
        if provider == "auto":
            raise HTTPException(
                status_code=503,
                detail="Voice transcription service is temporarily unavailable.",
            )
        raise HTTPException(status_code=500, detail=f"Transcription failed: {str(e)}")
    finally:
        if tmp_path and os.path.exists(tmp_path):
            try:
                os.remove(tmp_path)
            except Exception:
                pass


@app.post("/voice/synthesize")
async def voice_synthesize(request: Request):
    data = await request.json()
    text = (data.get("text") or "").strip()
    language = (data.get("language") or "en").strip().lower()
    speaker_raw = (data.get("speaker") or "").strip()
    speaker = _normalize_tts_speaker(speaker_raw)

    if text == "":
        raise HTTPException(status_code=400, detail="Text is required")

    try:
        audio_bytes = await _synthesize_with_edge_tts(text, speaker_raw or speaker)
        return {
            "audio_base64": base64.b64encode(audio_bytes).decode("utf-8"),
            "mime_type": "audio/mpeg",
            "provider_used": "edge_tts",
            "edge_voice": _resolve_edge_tts_voice(speaker_raw or speaker),
        }
    except Exception as edge_exc:
        logging.error("voice_synthesize: edge-tts failed: %s", edge_exc)
        raise HTTPException(status_code=502, detail=f"Edge TTS failed: {edge_exc}")


@app.post("/video/jobs")
async def create_video_job(request: Request, background_tasks: BackgroundTasks):
    data = await request.json()
    scenes = data.get("scenes") or []
    title = (data.get("title") or "Generated Video").strip()
    aspect_ratio = (data.get("aspect_ratio") or "16:9").strip()
    language = (data.get("language") or "en").strip()
    speaker = (data.get("speaker") or "").strip()
    target_duration = int(data.get("target_duration_seconds") or 0)

    if not scenes:
        raise HTTPException(status_code=400, detail="At least one scene is required")

    computed_duration = sum(max(1, int(scene.get("duration_seconds") or 0)) for scene in scenes)
    if target_duration <= 0:
        target_duration = computed_duration

    if target_duration > VIDEO_MAX_DURATION_SEC:
        raise HTTPException(
            status_code=400,
            detail=f"Video duration exceeds limit of {VIDEO_MAX_DURATION_SEC} seconds",
        )

    job_id = str(data.get("job_id") or uuid.uuid4())
    output_quality = (data.get("output_quality") or "hd").strip()
    if output_quality not in _QUALITY_PRESETS:
        output_quality = "hd"
    out_w, out_h = _output_dimensions(aspect_ratio, output_quality)

    incoming_settings = data.get("settings") if isinstance(data.get("settings"), dict) else {}
    merged_settings = dict(incoming_settings or {})
    merged_settings["output_quality"] = output_quality

    job = {
        "job_id": job_id,
        "organization_id": data.get("organization_id"),
        "organization_slug": data.get("organization_slug"),
        "title": title,
        "status": "queued",
        "progress": 0,
        "language": language,
        "speaker": speaker,
        "aspect_ratio": aspect_ratio,
        "output_quality": output_quality,
        "output_width": out_w,
        "output_height": out_h,
        "target_duration_seconds": target_duration,
        "global_prompt": (data.get("global_prompt") or "").strip(),
        "scenes": scenes,
        "settings": merged_settings,
        "source": data.get("source") or "unknown",
        "created_at": _utc_timestamp(),
        "updated_at": _utc_timestamp(),
    }
    _save_video_job(job)
    background_tasks.add_task(_process_video_job, job_id)
    return job


@app.get("/video/jobs/{job_id}")
async def get_video_job(job_id: str):
    return _load_video_job(job_id)


@app.get("/video/comfyui-status")
async def get_comfyui_status():
    """Return ComfyUI connectivity status and available checkpoints/motion models."""
    available = await _comfyui_available()
    result: dict = {
        "available": available,
        "url": COMFYUI_URL,
        "checkpoint": COMFYUI_CHECKPOINT,
        "motion_model": COMFYUI_MOTION_MODEL,
        "upscale_model": COMFYUI_UPSCALE_MODEL,
        "frames": COMFYUI_FRAMES,
        "render_fps": COMFYUI_RENDER_FPS,
        "base_width": COMFYUI_WIDTH,
        "base_height": COMFYUI_HEIGHT,
        "steps": COMFYUI_STEPS,
        "quality_presets": {k: {"16:9": list(v["16:9"]), "9:16": list(v["9:16"]), "1:1": list(v["1:1"])} for k, v in _QUALITY_PRESETS.items()},
    }
    if available:
        try:
            async with httpx.AsyncClient(timeout=10.0) as client:
                resp = await client.get(f"{COMFYUI_URL}/object_info/CheckpointLoaderSimple")
                if resp.status_code == 200:
                    opts = resp.json().get("CheckpointLoaderSimple", {})
                    inp = opts.get("input", {}).get("required", {}).get("ckpt_name", [])
                    checkpoints = inp[0] if inp else []
                    result["available_checkpoints"] = checkpoints
        except Exception:
            result["available_checkpoints"] = []
    return result


@app.get("/video/avatar-catalog")
async def get_avatar_catalog():
    """Return the built-in avatar catalog and current lip-sync service status."""
    return {
        "avatars": AVATAR_CATALOG,
        "lipsync_enabled": LIPSYNC_ENABLED,
        "lipsync_url": LIPSYNC_URL,
        "lipsync_mode": LIPSYNC_MODE,
        "positions": ["bottom-right", "bottom-left", "bottom-center",
                      "top-right", "top-left", "center-right", "center-left"],
        "sizes": list(AVATAR_SIZE_FRACTIONS.keys()),
        "shapes": ["circle", "rounded", "rectangle"],
    }


async def parse_assistant_command(request: Request):
    data = await request.json()
    query = (data.get("query") or "").strip()
    language = (data.get("language") or "en").strip()
    model = data.get("model") or DEFAULT_CHAT_MODEL
    context_items = data.get("context") or []

    if query == "":
        raise HTTPException(status_code=400, detail="query is required")

    context_text = ""
    if isinstance(context_items, list) and context_items:
        normalized = [str(item).strip() for item in context_items if str(item).strip() != ""]
        context_text = "\n".join(normalized[:8])

    system_prompt = (
        "You are a voice personal assistant command parser. "
        "Classify the user command and output STRICT JSON only with keys: "
        "intent, action, entities, needs_confirmation, reply. "
        "Allowed intents: dictation, send_email, calendar, appointment, reminder, notes, task, daily_brief, quick_search, unknown. "
        "Keep reply concise and natural."
    )

    user_prompt = f"Language: {language}\n"
    if context_text:
        user_prompt += f"Recent context:\n{context_text}\n"
    user_prompt += f"User command: {query}\n"

    messages = [
        {"role": "system", "content": system_prompt},
        {"role": "user", "content": user_prompt},
    ]

    try:
        llm_result = await _chat_completion(messages, model)
        content = (llm_result.get("message", {}) or {}).get("content", "")
        parsed = _extract_json_object(content)

        if not parsed:
            parsed = {
                "intent": "unknown",
                "action": "clarify",
                "entities": {},
                "needs_confirmation": True,
                "reply": "I understood your command partially. Could you please clarify what you want me to do?",
            }

        parsed.setdefault("intent", "unknown")
        parsed.setdefault("action", "clarify")
        parsed.setdefault("entities", {})
        parsed.setdefault("needs_confirmation", True)
        parsed.setdefault("reply", "Please confirm what you want me to do.")

        return {
            "result": parsed,
            "provider": "llm",
            "model": model,
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Command parsing failed: {str(e)}")

@app.post("/llm/chat")
async def llm_chat(request: Request):
    data = await request.json()
    messages = data["messages"]
    model = data.get("model", DEFAULT_CHAT_MODEL)  # Use high quality model by default
    backend_type = data.get("backend_type", AI_BACKEND_TYPE)  # Allow override from request
    options = data.get("options") or {}
    keep_alive = options.get("keep_alive") if isinstance(options, dict) else None
    ollama_options = {k: v for k, v in options.items() if k != "keep_alive"} if isinstance(options, dict) else {}
    request_id = uuid.uuid4().hex[:8]
    
    # Log incoming chat request
    logging.info(
        f"llm_chat request_id={request_id} backend={backend_type} model={model} messages={len(messages)} msgs options_keys={list(options.keys()) if options else []}"
    )

    # If system prompt contains context, log it for debugging
    for msg in messages:
        if msg.get("role") == "system":
            logging.info(f"System prompt/context: {msg.get('content')[:100]}...")
    start_time = time.time()
    
    # Handle llama.cpp backend
    if backend_type == "llamacpp":
        try:
            # Check if model is a GGUF repository path
            if model in GGUF_MODELS:
                model_path = await download_gguf_model(model)
            else:
                # Assume it's a file path
                model_path = model
                if not Path(model_path).exists():
                    raise HTTPException(status_code=404, detail=f"Model file not found: {model_path}")
            
            # Start llama-server with the model (or use existing if same model)
            server_started = await start_llamacpp_server(model_path)
            if not server_started:
                raise HTTPException(status_code=500, detail="Failed to start llama-server")
            
            # Send chat request to llama-server
            result = await llamacpp_server_chat(messages)
            elapsed_ms = int((time.time() - start_time) * 1000)
            logging.info(f"llama-server chat completed model={Path(model_path).name} elapsed_ms={elapsed_ms}")
            # Ensure usage keys present
            usage = result.get("usage") or {}
            if not usage:
                # As a fallback, estimate here too
                input_text = " ".join([m.get("content", "") for m in messages])
                output_text = result.get("message", {}).get("content", "")
                prompt_tokens = max(1, len(input_text) // 4)
                completion_tokens = max(1, len(output_text) // 4)
                total_tokens = prompt_tokens + completion_tokens
                result["usage"] = {
                    "prompt_tokens": prompt_tokens,
                    "completion_tokens": completion_tokens,
                    "total_tokens": total_tokens
                }
            return result
            
        except Exception as e:
            logging.error(f"llama.cpp chat error: {str(e)}")
            raise HTTPException(status_code=500, detail=str(e))
    
    # Handle Ollama backend (default/fallback)
    
    # Check if use_vastai is explicitly requested in options
    use_vastai = (options.get("use_vastai", False) if options else False) and VASTAI_ENABLED
    
    # Get the appropriate Ollama URL for this model
    if use_vastai:
        ollama_url = OLLAMA_URL_VASTAI
        logging.info(f"🚀 Forcing Vast.ai GPU for non-streaming: {ollama_url} model={model}")
    else:
        ollama_url = get_ollama_url(model)
    logging.info(f"Using Ollama URL: {ollama_url} for model: {model}")
    
    # Try primary URL first, then fallback models/hosts
    configs_to_try = [(ollama_url, model)]
    if ollama_url == OLLAMA_URL_VASTAI:
        # Fallback to local Ollama - try same model first, then fallback model
        configs_to_try.append((OLLAMA_URL_LOCAL, model))
        if model != FALLBACK_CHAT_MODEL:
            configs_to_try.append((OLLAMA_URL_LOCAL, FALLBACK_CHAT_MODEL))
        logging.info(f"Will fallback to local Ollama ({OLLAMA_URL_LOCAL}) with models: {model}, {FALLBACK_CHAT_MODEL} if vast.ai fails")
    elif model != FALLBACK_CHAT_MODEL:
        configs_to_try.append((ollama_url, FALLBACK_CHAT_MODEL))
        logging.info(f"Will fallback to model {FALLBACK_CHAT_MODEL} on {ollama_url} if {model} fails")
    
    last_error = None
    attempt_debug = []
    for url_to_try, model_to_use in configs_to_try:
        try:
            # 45s for Vast.ai (large models need 6-12s load + inference on cold start)
            # 60s for local fallback
            timeout = 45.0 if url_to_try == OLLAMA_URL_VASTAI else 60.0
            attempt_start = time.time()
            async with httpx.AsyncClient(timeout=timeout) as client:
                payload = {
                    "model": model_to_use,
                    "messages": messages,
                    "stream": False,
                }
                if ollama_options:
                    payload["options"] = ollama_options
                if keep_alive:
                    payload["keep_alive"] = keep_alive
                if _should_disable_thinking(model_to_use):
                    payload["think"] = False

                resp = await client.post(f"{url_to_try}/api/chat", json=payload)
                if resp.status_code != 200:
                    raise RuntimeError(f"Ollama HTTP {resp.status_code}: {resp.text}")
                result = _sanitize_chat_result(resp.json())
                if isinstance(result, dict) and result.get("error"):
                    raise RuntimeError(f"Ollama error: {result.get('error')}")
                attempt_ms = int((time.time() - attempt_start) * 1000)
                elapsed_ms = int((time.time() - start_time) * 1000)
                logging.info(
                    f"LLM chat completed request_id={request_id} model={model_to_use} url={url_to_try} attempt_ms={attempt_ms} elapsed_ms={elapsed_ms}"
                )
            
                # Debug logging - full response
                print(f"DEBUG: Full Ollama response: {result}", flush=True)
                print(f"DEBUG: Message content: {result.get('message', {})}", flush=True)
                logging.info(f"Full Ollama response: {result}")
                logging.info(f"Ollama response: message={result.get('message', {})}, done={result.get('done')}")
            
                # Estimate token usage (simple approximation: ~4 chars per token)
                input_text = " ".join([msg.get("content", "") for msg in messages])
                message_obj = result.get("message", {})
                output_text = message_obj.get("content", "") or ""
                input_tokens = len(input_text) // 4
                output_tokens = len(output_text) // 4
                total_tokens = input_tokens + output_tokens
            
                response_payload = {
                    "message": message_obj,
                    "usage": {
                        "prompt_tokens": input_tokens,
                        "completion_tokens": output_tokens,
                        "total_tokens": total_tokens
                    },
                    "debug": {
                        "requested_model": model,
                        "actual_model": model_to_use,
                        "requested_backend": backend_type,
                        "actual_backend": "ollama",
                        "requested_url": ollama_url,
                        "actual_url": url_to_try,
                        "fallback_used": (url_to_try != ollama_url) or (model_to_use != model),
                        "attempts": attempt_debug + [{
                            "url": url_to_try,
                            "model": model_to_use,
                            "backend": "ollama",
                            "successful": True,
                            "attempt_ms": attempt_ms,
                        }],
                    }
                }

                return response_payload
                
        except Exception as e:
            last_error = e
            is_vastai = url_to_try == OLLAMA_URL_VASTAI
            attempt_ms = int((time.time() - attempt_start) * 1000)
            attempt_debug.append({
                "url": url_to_try,
                "model": model_to_use,
                "backend": "ollama",
                "successful": False,
                "attempt_ms": attempt_ms,
                "error": str(e),
                "is_vastai": is_vastai,
            })
            logging.warning(
                f"{'🚨 Vast.ai' if is_vastai else 'Local Ollama'} URL {url_to_try} failed request_id={request_id} attempt_ms={attempt_ms}: {str(e)}"
            )
            if is_vastai:
                logging.info("⚡ Falling back to local Ollama...")
            continue  # Try next URL
    
    # If all Ollama URLs failed, try llama-server fallback
    # If all Ollama URLs failed, try llama-server fallback
    if last_error:
        logging.error(f"All Ollama URLs failed. Last error: {str(last_error)}. Attempting llama-server fallback...")
        try:
            # Fallback to local llama-server
            result = await llamacpp_server_chat(messages)
            result = _sanitize_chat_result(result)
            elapsed_ms = int((time.time() - start_time) * 1000)
            logging.info(f"✅ llama-server fallback successful request_id={request_id} elapsed_ms={elapsed_ms}")
            
            # Ensure usage keys present
            usage = result.get("usage") or {}
            if not usage:
                input_text = " ".join([m.get("content", "") for m in messages])
                output_text = result.get("message", {}).get("content", "")
                prompt_tokens = max(1, len(input_text) // 4)
                completion_tokens = max(1, len(output_text) // 4)
                total_tokens = prompt_tokens + completion_tokens
                result["usage"] = {
                    "prompt_tokens": prompt_tokens,
                    "completion_tokens": completion_tokens,
                    "total_tokens": total_tokens
                }
            result["debug"] = {
                "requested_model": model,
                "actual_model": model,
                "requested_backend": backend_type,
                "actual_backend": "llamacpp",
                "requested_url": ollama_url,
                "actual_url": "llamacpp://local",
                "fallback_used": True,
                "attempts": attempt_debug + [{
                    "url": "llamacpp://local",
                    "model": model,
                    "backend": "llamacpp",
                    "successful": True,
                    "attempt_ms": elapsed_ms,
                }],
            }
            return result
            
        except Exception as llamacpp_error:
            elapsed_ms = int((time.time() - start_time) * 1000)
            logging.error(
                f"❌ Both Ollama and llama-server failed request_id={request_id}. Ollama: {str(last_error)}, llama-server: {str(llamacpp_error)} elapsed_ms={elapsed_ms}"
            )
            raise HTTPException(status_code=500, detail=f"All LLM backends failed. Ollama: {str(last_error)}, llama-server: {str(llamacpp_error)}")

@app.post("/llm/chat/stream")
async def stream_chat(request: Request):
    """
    Stream chat endpoint - returns SSE for real-time token streaming
    """
    from fastapi.responses import StreamingResponse
    import json
    
    data = await request.json()
    start_time = time.time()
    messages = data["messages"]
    model = data.get("model", DEFAULT_CHAT_MODEL)
    backend_type = data.get("backend_type", AI_BACKEND_TYPE)
    options = data.get("options") or {}
    keep_alive = options.get("keep_alive") if isinstance(options, dict) else None
    ollama_options = {k: v for k, v in options.items() if k != "keep_alive"} if isinstance(options, dict) else {}
    request_id = uuid.uuid4().hex[:8]
    
    logging.info(
        f"Stream chat request_id={request_id} model={model} backend={backend_type} messages={len(messages)} options_keys={list(options.keys()) if options else []}"
    )
    
    # Check if use_vastai is explicitly requested in options
    use_vastai = (options.get("use_vastai", False) if options else False) and VASTAI_ENABLED
    
    # Get the appropriate Ollama URL for this model
    if use_vastai:
        ollama_url = OLLAMA_URL_VASTAI
        logging.info(f"🚀 Forcing Vast.ai GPU for streaming: {ollama_url} model={model}")
    else:
        ollama_url = get_ollama_url(model)
        logging.info(f"Using Ollama URL: {ollama_url} for streaming model: {model}")
    
    # Try primary URL first, then fallback models/hosts
    configs_to_try = [(ollama_url, model)]
    if ollama_url == OLLAMA_URL_VASTAI:
        # Fallback to local Ollama with fallback model
        if model != FALLBACK_CHAT_MODEL:
            configs_to_try.append((OLLAMA_URL_LOCAL, FALLBACK_CHAT_MODEL))
        logging.info(f"Will fallback to local Ollama ({OLLAMA_URL_LOCAL}) with model: {FALLBACK_CHAT_MODEL} if vast.ai fails for streaming")
    elif model != FALLBACK_CHAT_MODEL:
        configs_to_try.append((ollama_url, FALLBACK_CHAT_MODEL))
        logging.info(f"Will fallback to model {FALLBACK_CHAT_MODEL} on {ollama_url} if {model} fails for streaming")
    
    async def generate():
        last_error = None
        for url_to_try, model_to_use in configs_to_try:
            try:
                suppress_reasoning_stream = _should_disable_thinking(model_to_use)
                # Vast can take longer to first token on cold starts; avoid premature fallback.
                timeout = 45.0 if url_to_try == OLLAMA_URL_VASTAI else 120.0
                attempt_start = time.time()
                async with httpx.AsyncClient(timeout=timeout) as client:
                    # Make streaming request to Ollama
                    stream_payload = {
                        "model": model_to_use,
                        "messages": messages,
                        "stream": True,
                    }
                    if ollama_options:
                        stream_payload["options"] = ollama_options
                    if keep_alive:
                        stream_payload["keep_alive"] = keep_alive
                    if _should_disable_thinking(model_to_use):
                        stream_payload["think"] = False

                    async with client.stream(
                        'POST',
                        f"{url_to_try}/api/chat",
                        json=stream_payload
                    ) as response:
                        if response.status_code != 200:
                            body = (await response.aread()).decode("utf-8", errors="ignore")
                            raise RuntimeError(f"Ollama HTTP {response.status_code}: {body}")
                        # Track tokens for usage counting
                        full_content = ""
                        first_token_ms = None
                    
                        async for line in response.aiter_lines():
                            if not line:
                                continue
                            line_data = line.strip()
                            if line_data.startswith("data:"):
                                line_data = line_data[5:].strip()
                            if line_data == "[DONE]":
                                continue
                        
                            try:
                                chunk = json.loads(line_data)
                            
                                # Extract content from message
                                if 'message' in chunk and 'content' in chunk['message']:
                                    content = chunk['message']['content']
                                    full_content += content
                                    if first_token_ms is None:
                                        first_token_ms = int((time.time() - attempt_start) * 1000)
                                        logging.info(
                                            f"stream first_token request_id={request_id} model={model_to_use} url={url_to_try} first_token_ms={first_token_ms}"
                                        )

                                    if not suppress_reasoning_stream:
                                        # Send SSE format
                                        yield f"data: {json.dumps({'content': content, 'done': False})}\n\n"
                            
                                # Check if done
                                if chunk.get('done', False):
                                    if not full_content.strip():
                                        raise RuntimeError(
                                            f"Ollama stream completed with empty content for model={model_to_use} url={url_to_try}"
                                        )
                                    visible_content = _strip_reasoning_blocks(full_content) if suppress_reasoning_stream else full_content
                                    if suppress_reasoning_stream and not visible_content.strip():
                                        raise RuntimeError(
                                            f"Ollama stream completed with only reasoning content for model={model_to_use} url={url_to_try}"
                                        )

                                    if suppress_reasoning_stream:
                                        yield f"data: {json.dumps({'content': visible_content, 'done': False})}\n\n"

                                    # Calculate token usage
                                    input_text = " ".join([msg.get("content", "") for msg in messages])
                                    input_tokens = len(input_text) // 4
                                    output_tokens = len(visible_content) // 4
                                    total_tokens = input_tokens + output_tokens
                                
                                    # Send final message with usage
                                    final_data = {
                                        'content': '',
                                        'done': True,
                                        'usage': {
                                            'prompt_tokens': input_tokens,
                                            'completion_tokens': output_tokens,
                                            'total_tokens': total_tokens
                                        }
                                    }
                                    yield f"data: {json.dumps(final_data)}\n\n"
                                
                                    elapsed_ms = int((time.time() - start_time) * 1000)
                                    attempt_ms = int((time.time() - attempt_start) * 1000)
                                    logging.info(
                                        f"Stream completed request_id={request_id} model={model_to_use} url={url_to_try} tokens={total_tokens} attempt_ms={attempt_ms} elapsed_ms={elapsed_ms}"
                                    )
                                    return  # Success - exit generator
                                
                            except json.JSONDecodeError:
                                continue
                            
            except Exception as e:
                last_error = e
                is_vastai = url_to_try == OLLAMA_URL_VASTAI
                logging.warning(
                    f"{'🚨 Vast.ai stream' if is_vastai else 'Local Ollama stream'} failed for {url_to_try}: {str(e)} | repr={repr(e)}",
                    exc_info=True,
                )
                if is_vastai:
                    logging.info(f"⚡ Falling back to local Ollama for streaming...")
                continue  # Try next URL
        
        # If all Ollama URLs failed, fallback to llama.cpp and emit a single SSE message
        if last_error:
            logging.error(f"All Ollama stream URLs failed: {str(last_error)}")
            try:
                result = await llamacpp_server_chat(messages)
                message_content = result.get("message", {}).get("content", "")
                usage = result.get("usage") or {}
                if not message_content.strip():
                    raise RuntimeError("llama.cpp fallback returned empty content")

                yield f"data: {json.dumps({'content': message_content, 'done': False})}\n\n"
                yield f"data: {json.dumps({'content': '', 'done': True, 'usage': usage})}\n\n"
                logging.info(f"Stream llama.cpp fallback succeeded request_id={request_id}")
                return
            except Exception as llamacpp_error:
                logging.error(f"Stream llama.cpp fallback failed request_id={request_id}: {llamacpp_error}")
                error_data = {'error': f'All connection attempts failed: {str(last_error)}', 'done': True}
                yield f"data: {json.dumps(error_data)}\n\n"
    
    return StreamingResponse(generate(), media_type="text/event-stream")

@app.post("/store_data")
async def store_data(request: Request):
    """
    Unified endpoint to store any type of organization data to Qdrant
    Expected payload:
    {
        "organization_slug": "ai-chat-support",
        "data_type": "faq|info|service|document",
        "items": [
            {
                "id": "unique_id",
                "title": "title",
                "content": "main content",
                "category": "category",
                "metadata": {...}
            }
        ]
    }
    """
    try:
        data = await request.json()
        organization_slug = data["organization_slug"]
        data_type = data["data_type"]
        items = data["items"]
        
        logging.info(f"Store data request: org={organization_slug}, type={data_type}, count={len(items)}")
        t_request_start = time.time()
        # Create collection if it doesn't exist
        collection_name = organization_slug
        try:
            qdrant.create_collection(
                collection_name=collection_name,
                vectors_config=VectorParams(size=768, distance=Distance.COSINE)
            )
        except Exception as e:
            if "already exists" not in str(e):
                logging.warning(f"Collection creation issue: {str(e)}")
        
        successful_stores = 0
        failed_stores = []

        # ── Pass 1: Prepare all texts and payloads (no embedding yet) ──────────
        t_pass1_start = time.time()
        prepared = []  # list of {text, payload, point_id, item_id}
        for item in items:
            try:
                metadata = item.get('metadata') or {}
                if not isinstance(metadata, dict):
                    metadata = {}

                raw_keywords = (
                    item.get('keywords')
                    or item.get('search_keywords')
                    or metadata.get('keywords')
                    or metadata.get('search_keywords')
                    or ((metadata.get('csv') or {}).get('keywords') if isinstance(metadata.get('csv'), dict) else None)
                    or ((metadata.get('csv') or {}).get('search_keywords') if isinstance(metadata.get('csv'), dict) else None)
                    or ''
                )
                keywords = str(raw_keywords).strip()

                raw_entity = (
                    item.get('entity')
                    or item.get('primary_entity')
                    or metadata.get('entity')
                    or metadata.get('primary_entity')
                    or ((metadata.get('csv') or {}).get('entity') if isinstance(metadata.get('csv'), dict) else None)
                    or ((metadata.get('csv') or {}).get('primary_entity') if isinstance(metadata.get('csv'), dict) else None)
                    or keywords
                    or ''
                )
                entity = str(raw_entity).strip()

                raw_semantic_text = (
                    item.get('semantic_text')
                    or metadata.get('semantic_text')
                    or ((metadata.get('csv') or {}).get('semantic_text') if isinstance(metadata.get('csv'), dict) else None)
                    or ''
                )
                semantic_text = str(raw_semantic_text).strip()

                raw_semantic_terms = (
                    item.get('semantic_terms')
                    or metadata.get('semantic_terms')
                    or ((metadata.get('csv') or {}).get('semantic_terms') if isinstance(metadata.get('csv'), dict) else None)
                    or []
                )
                if isinstance(raw_semantic_terms, list):
                    semantic_terms = [str(term).strip() for term in raw_semantic_terms if str(term).strip()]
                else:
                    semantic_terms = [part.strip() for part in str(raw_semantic_terms).split(',') if part.strip()]
                if not semantic_text and semantic_terms:
                    semantic_text = ' '.join(semantic_terms)

                # Build compact embedding text — shorter text means faster CPU embedding.
                # Rule: title + content (stripped of URLs, capped at 400 chars).
                # We intentionally skip entity/keywords/semantic_text because:
                #   - entity is usually the same as title (redundant)
                #   - keywords is a tokenised re-hash of the title (redundant)
                #   - semantic_text is yet another copy of the same words
                # Repeating the same info just inflates token count without
                # improving retrieval quality.
                # NOTE: payload['content'] still holds the FULL original content
                # so the LLM always gets complete context when answering.
                raw_content = item.get('content', '')
                # Strip URL lines (product_url: ...) from embedding text
                content_for_embed = re.sub(r'\s*product_url:.*', '', raw_content, flags=re.IGNORECASE).strip()
                # Truncate long content (e.g. category pages) to ~400 chars for embedding
                if len(content_for_embed) > 400:
                    content_for_embed = content_for_embed[:400]

                embed_parts = []
                if item.get('title'):
                    embed_parts.append(item['title'])
                if content_for_embed:
                    embed_parts.append(content_for_embed)
                elif item.get('category'):
                    embed_parts.append(item['category'])

                full_text = " ".join(embed_parts).strip()
                if not full_text.strip():
                    failed_stores.append({"item_id": item.get('id'), "error": "No text content to embed"})
                    continue

                # Build payload
                payload = {
                    "data_type": data_type,
                    "item_id": item.get('id'),
                    "title": item.get('title', ''),
                    "content": item.get('content', ''),
                    "category": item.get('category', ''),
                    "entity": entity,
                    "primary_entity": entity,
                    "keywords": keywords,
                    "search_keywords": keywords,
                    "semantic_terms": semantic_terms,
                    "semantic_text": semantic_text,
                    "follow_up": item.get('follow_up'),
                    "organization_slug": organization_slug
                }
                if item.get('metadata'):
                    payload.update(item['metadata'])
                # Re-normalise keyword fields after metadata merge
                payload["keywords"] = keywords or str(payload.get("keywords") or payload.get("search_keywords") or '').strip()
                payload["search_keywords"] = payload["keywords"]
                payload["entity"] = entity or str(payload.get("entity") or payload.get("primary_entity") or payload.get("keywords") or '').strip()
                payload["primary_entity"] = payload["entity"]
                payload["semantic_terms"] = semantic_terms or payload.get("semantic_terms") or []
                payload["semantic_text"] = semantic_text or str(payload.get("semantic_text") or '').strip()

                item_identifier = str(item.get('id', f"{data_type}_{len(prepared)}"))
                stable_seed = f"{organization_slug}:{data_type}:{item_identifier}".encode("utf-8")
                point_id = int.from_bytes(hashlib.sha256(stable_seed).digest()[:8], byteorder="big", signed=False) & 0x7FFFFFFFFFFFFFFF

                prepared.append({
                    "text": full_text,
                    "payload": payload,
                    "point_id": point_id,
                    "item_id": item.get('id'),
                })
            except Exception as e:
                failed_stores.append({"item_id": item.get('id'), "error": f"prep: {str(e)}"})
                logging.error(f"Failed to prepare item {item.get('id')}: {str(e)}")

        # ── Pass 2: Batch embed + batch upsert in groups of EMBED_BATCH_SIZE ───
        # upsert() with consistent hash IDs is idempotent — no scroll+delete needed.
        # qdrant.upsert is synchronous — run in thread pool to avoid blocking event loop.
        t_pass1_end = time.time()
        logging.info(f"Pass 1 (prepare {len(prepared)} items): {t_pass1_end - t_pass1_start:.2f}s")
        t_pass2_start = time.time()
        for batch_start in range(0, len(prepared), EMBED_BATCH_SIZE):
            batch = prepared[batch_start: batch_start + EMBED_BATCH_SIZE]
            texts = [p["text"] for p in batch]
            batch_num = batch_start // EMBED_BATCH_SIZE + 1
            total_batches = (len(prepared) + EMBED_BATCH_SIZE - 1) // EMBED_BATCH_SIZE
            try:
                t0 = time.time()
                embeddings, embed_ms = await _generate_embeddings_batch(texts, DEFAULT_EMBED_MODEL, t0)
                t_after_embed = time.time()
                logging.info(
                    f"Batch {batch_num}/{total_batches}: embedded {len(texts)} items in {t_after_embed - t0:.2f}s ({embed_ms}ms reported)"
                )

                points = [
                    PointStruct(id=p["point_id"], vector=emb, payload=p["payload"])
                    for p, emb in zip(batch, embeddings)
                ]
                t_upsert_start = time.time()
                await asyncio.to_thread(qdrant.upsert, collection_name=collection_name, points=points)
                t_upsert_end = time.time()
                successful_stores += len(batch)
                logging.info(
                    f"Batch {batch_num}/{total_batches}: upserted {len(points)} points in {t_upsert_end - t_upsert_start:.3f}s"
                )

            except Exception as e:
                logging.error(f"Batch {batch_num} failed: {str(e)} — falling back to single-item mode")
                # Fallback: embed+upsert one at a time for this batch
                for p in batch:
                    try:
                        t0 = time.time()
                        emb, _ = await _generate_embedding(DEFAULT_EMBED_MODEL, p["text"], t0)
                        await asyncio.to_thread(
                            qdrant.upsert,
                            collection_name=collection_name,
                            points=[PointStruct(id=p["point_id"], vector=emb, payload=p["payload"])]
                        )
                        successful_stores += 1
                    except Exception as item_err:
                        failed_stores.append({"item_id": p["item_id"], "error": str(item_err)})
                        logging.error(f"Fallback failed for item {p['item_id']}: {str(item_err)}")
        
        response = {
            "success": True,
            "organization_slug": organization_slug,
            "data_type": data_type,
            "total_items": len(items),
            "successful_stores": successful_stores,
            "failed_stores": len(failed_stores),
            "failures": failed_stores
        }
        
        t_total = time.time() - t_request_start
        logging.info(
            f"Store data complete: {successful_stores}/{len(items)} successful | "
            f"total={t_total:.2f}s pass1={t_pass1_end - t_pass1_start:.2f}s "
            f"pass2={time.time() - t_pass2_start:.2f}s ({t_total/max(len(items),1)*1000:.0f}ms/item)"
        )
        return response
        
    except Exception as e:
        logging.error(f"Store data error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Store data failed: {str(e)}")

@app.post("/update_data")
async def update_data(request: Request):
    """
    Update existing data in Qdrant (same as store_data but with explicit update logging)
    This is essentially an alias to store_data with clearer semantics for updates
    """
    try:
        data = await request.json()
        organization_slug = data["organization_slug"]
        data_type = data["data_type"]
        items = data["items"]
        
        logging.info(f"Update data request: org={organization_slug}, type={data_type}, count={len(items)}")
        
        # Use the same logic as store_data since it now handles updates properly
        return await store_data(request)
        
    except Exception as e:
        logging.error(f"Update data error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Update data failed: {str(e)}")

@app.post("/delete_data")
async def delete_data(request: Request):
    """
    Delete specific data points from Qdrant collection
    Expected payload:
    {
        "organization_slug": "ai-chat-support", 
        "item_ids": ["faq_123", "info_456"]
    }
    """
    try:
        data = await request.json()
        organization_slug = data["organization_slug"]
        item_ids = data["item_ids"]
        
        logging.info(f"Delete data request: org={organization_slug}, items={len(item_ids)}")
        
        collection_name = organization_slug
        deleted_count = 0
        failed_deletes = []
        
        # Get all points to find the ones to delete by payload matching
        scroll_result = qdrant.scroll(
            collection_name=collection_name,
            scroll_filter=None,
            limit=1000,  # Adjust if you have more items
            with_payload=True,
            with_vectors=False
        )
        
        points_to_delete = []
        for point in scroll_result[0]:  # scroll returns (points, next_page_offset)
            payload = point.payload
            if payload.get('item_id') in item_ids:
                points_to_delete.append(point.id)
        
        # Delete the points
        if points_to_delete:
            qdrant.delete(
                collection_name=collection_name,
                points_selector=points_to_delete
            )
            deleted_count = len(points_to_delete)
            logging.info(f"Deleted {deleted_count} items from {collection_name}")
        
        # Check which items weren't found
        found_items = set()
        for point in scroll_result[0]:
            payload = point.payload
            if payload.get('item_id') in item_ids and point.id in points_to_delete:
                found_items.add(payload.get('item_id'))
        
        failed_deletes = [item_id for item_id in item_ids if item_id not in found_items]
        
        response = {
            "success": True,
            "organization_slug": organization_slug,
            "total_requested": len(item_ids),
            "deleted_count": deleted_count,
            "failed_deletes": failed_deletes
        }
        
        return response
        
    except Exception as e:
        logging.error(f"Delete data error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Delete data failed: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8111, reload=False)
