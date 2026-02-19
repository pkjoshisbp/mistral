#!/bin/bash
set -euo pipefail

VAST_HOST="171.248.41.73"
VAST_PORT="29425"
VAST_USER="root"

ssh -o StrictHostKeyChecking=no -p ${VAST_PORT} ${VAST_USER}@${VAST_HOST} <<'REMOTE_SETUP'
set -euo pipefail

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ffmpeg git python3-venv python3-pip curl

mkdir -p /opt/personal_assistant
cd /opt/personal_assistant

if [[ ! -d .venv ]]; then
  python3 -m venv .venv
fi

source .venv/bin/activate
pip install --upgrade pip
pip install "fastapi" "uvicorn[standard]" "python-multipart" "faster-whisper" "TTS" "gTTS" "soundfile"

cat > /opt/personal_assistant/whisper_api.py <<'PY'
from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from faster_whisper import WhisperModel
import tempfile
import os

app = FastAPI()
model = WhisperModel("large-v3", device="cuda", compute_type="float16")

@app.get("/health")
async def health():
    return {"status": "ok", "service": "whisper", "model": "large-v3"}

@app.post("/transcribe")
async def transcribe(audio: UploadFile = File(...), language: str = Form("auto"), prompt: str = Form("")):
    data = await audio.read()
    if not data:
        raise HTTPException(status_code=400, detail="Empty audio")

    suffix = os.path.splitext(audio.filename or "speech.webm")[-1] or ".webm"
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            tmp.write(data)
            tmp_path = tmp.name

        kwargs = {"beam_size": 5, "vad_filter": True}
        if language and language.lower() != "auto":
            kwargs["language"] = language
        if prompt:
            kwargs["initial_prompt"] = prompt

        segments, info = model.transcribe(tmp_path, **kwargs)
        text = " ".join([(s.text or "").strip() for s in segments]).strip()

        return {
            "text": text,
            "language": getattr(info, "language", language),
            "meta": {
                "duration": getattr(info, "duration", None),
                "language_probability": getattr(info, "language_probability", None),
            },
        }
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.remove(tmp_path)
PY

cat > /opt/personal_assistant/xtts_api.py <<'PY'
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import os

# Needed for PyTorch >=2.6 compatibility with older XTTS checkpoint loading flow
os.environ.setdefault("TORCH_FORCE_NO_WEIGHTS_ONLY_LOAD", "1")

from TTS.api import TTS
from io import BytesIO
import base64
import tempfile
import os

app = FastAPI()

class SynthesizeRequest(BaseModel):
    text: str
    language: str = "en"
    speaker: str = ""

# XTTS v2 multilingual
xtts = TTS(model_name="tts_models/multilingual/multi-dataset/xtts_v2", gpu=True)

@app.get("/health")
async def health():
    return {"status": "ok", "service": "xtts", "model": "xtts_v2"}

@app.post("/tts")
async def tts(req: SynthesizeRequest):
    if not req.text.strip():
        raise HTTPException(status_code=400, detail="text is required")

    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=".wav") as tmp:
            tmp_path = tmp.name

        kwargs = {
            "text": req.text,
            "language": req.language,
            "file_path": tmp_path,
        }
        if req.speaker.strip():
            kwargs["speaker_wav"] = req.speaker.strip()

        xtts.tts_to_file(**kwargs)

        with open(tmp_path, "rb") as f:
            audio_bytes = f.read()

        return {
            "audio_base64": base64.b64encode(audio_bytes).decode("utf-8"),
            "mime_type": "audio/wav",
        }
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.remove(tmp_path)
PY

cat > /opt/personal_assistant/indic_tts_api.py <<'PY'
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from gtts import gTTS
from io import BytesIO
import base64

app = FastAPI()

class SynthesizeRequest(BaseModel):
    text: str
    language: str = "hi"

@app.get("/health")
async def health():
    return {"status": "ok", "service": "indic_tts", "engine": "gtts"}

@app.post("/tts")
async def tts(req: SynthesizeRequest):
    text = (req.text or "").strip()
    if not text:
        raise HTTPException(status_code=400, detail="text is required")

    lang = (req.language or "hi").split("-")[0]

    mp3_buffer = BytesIO()
    tts = gTTS(text=text, lang=lang)
    tts.write_to_fp(mp3_buffer)
    mp3_bytes = mp3_buffer.getvalue()

    return {
        "audio_base64": base64.b64encode(mp3_bytes).decode("utf-8"),
        "mime_type": "audio/mpeg",
    }
PY

pkill -f "uvicorn whisper_api:app" || true
pkill -f "uvicorn xtts_api:app" || true
pkill -f "uvicorn indic_tts_api:app" || true

nohup /opt/personal_assistant/.venv/bin/uvicorn whisper_api:app --host 0.0.0.0 --port 18081 > /opt/personal_assistant/whisper.log 2>&1 &
nohup env COQUI_TOS_AGREED=1 /opt/personal_assistant/.venv/bin/uvicorn xtts_api:app --host 0.0.0.0 --port 18082 > /opt/personal_assistant/xtts.log 2>&1 &
nohup /opt/personal_assistant/.venv/bin/uvicorn indic_tts_api:app --host 0.0.0.0 --port 18083 > /opt/personal_assistant/indic.log 2>&1 &

sleep 5
curl -s http://127.0.0.1:18081/health || true
curl -s http://127.0.0.1:18082/health || true
curl -s http://127.0.0.1:18083/health || true

REMOTE_SETUP

echo "Vast personal assistant services setup completed."
