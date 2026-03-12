#!/bin/bash
set -euo pipefail

VAST_HOST="${VAST_HOST:-123.21.129.10}"
VAST_PORT="${VAST_PORT:-51734}"
VAST_USER="${VAST_USER:-root}"
REMOTE_BASE="${REMOTE_BASE:-/workspace/video-stack}"

echo "[video-stack] preparing Vast.ai host ${VAST_USER}@${VAST_HOST}:${VAST_PORT}"

ssh -o StrictHostKeyChecking=no -p "${VAST_PORT}" "${VAST_USER}@${VAST_HOST}" <<REMOTE_SETUP
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

mkdir -p "${REMOTE_BASE}"
cd "${REMOTE_BASE}"

apt-get update
apt-get install -y git ffmpeg python3 python3-venv python3-pip curl

if [ ! -d ComfyUI ]; then
  git clone https://github.com/comfyanonymous/ComfyUI.git
fi

cd ComfyUI
python3 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip wheel setuptools
pip install -r requirements.txt
pip install fastapi uvicorn[standard] python-multipart httpx pillow pydantic

echo "[video-stack] base environment ready in ${REMOTE_BASE}/ComfyUI"
echo "[video-stack] next step: download only the video models you actually plan to use"
echo "[video-stack] suggested first stack: AnimateDiff + one image-to-video workflow + ffmpeg"
REMOTE_SETUP

echo "[video-stack] done"
