# Vast.ai Video Deployment Status

## Current status

As of March 7, 2026, the admin video builder is integrated into Laravel and FastAPI, but a dedicated video-generation stack has **not** been installed on the Vast.ai server yet.

What is already working now:

- Admin can create storyboard-based video jobs from the admin panel.
- FastAPI accepts video jobs and creates stitched videos.
- Scene voice-over uses the existing TTS pipeline.
- Reference images can be uploaded per scene.
- Prompts are stored so the pipeline can later be upgraded to true Vast.ai scene generation.

What is **not** installed yet for video generation:

- `ComfyUI`
- `AnimateDiff`
- `Stable Video Diffusion`
- `ModelScope text-to-video`
- `Open-Sora`
- any dedicated remote video-worker service on Vast.ai

---

## What is already installed on Vast.ai

Checked directly on the Vast.ai server over SSH.

### Server and storage

- Host: `123.21.129.10`
- SSH port: `51734`
- Workspace volume: `/workspace`
- `/workspace` size: **62 GB total**
- Used: **28 GB**
- Free: **35 GB**

### Current `/workspace` usage

- `/workspace/ollama` → about **20 GB**
- `/workspace/ollama` → about **15 GB** after removing `gemma2:9b`
- `/workspace/personal_assistant` → about **9.1 GB**
- `/workspace/conda` → about **1.7 GB**
- `/workspace/logs` → negligible

### Current Ollama models on Vast.ai

These are already present on the remote server:

- `nomic-embed-text:latest` → about **274 MB**
- `llama3.2:3b` → about **2.0 GB**
- `mistral-nemo:latest` → about **7.1 GB**
- `llama3:8b-instruct-q5_K_M` → about **5.7 GB**

Removed on March 7, 2026:

- `gemma2:9b` → freed about **5.4 GB**

### Existing remote AI services in use

The server already appears to be used for:

- remote Ollama models
- personal assistant services
- voice services exposed through local forwarded ports

---

## What I added in FastAPI

### New endpoints added

These endpoints were added for the new admin video flow:

- `POST /video/jobs`
- `GET /video/jobs/{job_id}`

Purpose:

- `POST /video/jobs` accepts a storyboard job from Laravel.
- `GET /video/jobs/{job_id}` returns the current status, output path, output URL, and any error.

### How the current video job works

Current implementation is **local FastAPI orchestration**, not remote Vast.ai video-model inference yet.

Current flow:

1. Laravel submits storyboard scenes to FastAPI.
2. FastAPI stores a local job manifest.
3. For each scene, FastAPI:
   - uses the uploaded reference image if present
   - or creates a prompt card scene if no image exists
   - uses TTS for voice-over if text is provided
4. `ffmpeg` stitches all clips together.
5. Result is saved under Laravel public storage.

So the shipped version is a practical MVP for stitched explainer videos.

---

## What already links FastAPI to Vast.ai

### Remote Ollama tunnel

FastAPI is already linked to Vast.ai through the local SSH tunnel:

- `OLLAMA_URL_VASTAI = http://127.0.0.1:11435`

That local port is forwarded to the Vast.ai Ollama service.

Tunnel script already present:

- [scripts/start-ollama-tunnel.sh](scripts/start-ollama-tunnel.sh)

### Existing FastAPI config that already uses Vast.ai

Current FastAPI pieces already linked to Vast.ai:

- `OLLAMA_URL_VASTAI` for remote Ollama
- `VASTAI_MODELS` routing for large models
- `CRAWL_LLM_URL` defaults to the Vast.ai tunnel
- `PERSONAL_ASSISTANT_WHISPER_URL` → `http://127.0.0.1:18081/transcribe`
- `PERSONAL_ASSISTANT_XTTS_URL` → `http://127.0.0.1:18082/tts`
- `PERSONAL_ASSISTANT_INDIC_TTS_URL` → `http://127.0.0.1:18083/tts`

Important:

The new video endpoints do **not yet call a Vast.ai video model**. They currently use:

- local FastAPI job logic
- existing voice endpoints
- local `ffmpeg`

---

## What I prepared for Vast.ai video installation

I added a bootstrap script here:

- [scripts/setup-vast-video-stack.sh](scripts/setup-vast-video-stack.sh)

That script is designed to prepare a base remote video environment with:

- `git`
- `ffmpeg`
- `python3`
- `python3-venv`
- `python3-pip`
- `curl`
- `ComfyUI`
- Python packages for a small FastAPI helper layer

Important:

This script was **created but not run**.
So `ComfyUI` is currently **not installed** on Vast.ai.

---

## What can be comfortably installed now

With about **35 GB free** on `/workspace`, the safest plan is still a **lean single-stack video deployment**.

### Comfortable install now

Recommended first install:

1. `ComfyUI`
2. one image-to-video workflow
3. one motion module / AnimateDiff setup
4. keep using existing TTS
5. keep using existing Ollama models

### Comfortable target stack

A practical stack that should fit:

- `ComfyUI` code + venv → **2 to 4 GB**
- one AnimateDiff workflow + motion modules → **3 to 6 GB**
- one image/video checkpoint family → **6 to 10 GB**
- temp render/output cache reserve → **5 to 8 GB**

Estimated comfortable total additional need:

- about **15 to 20 GB**

That is now the recommended target so we keep room for future models, cache, and output growth.

### Recommended best combination for this server

For the current storage target, the best combination is:

1. `ComfyUI`
2. `ComfyUI-AnimateDiff-Evolved`
3. one `Stable Diffusion 1.5` checkpoint
4. one AnimateDiff motion model
5. existing TTS and FFmpeg pipeline

Reason:

- best balance of quality, speed, and disk usage
- works well on the existing AI orchestration model
- keeps total new footprint in the **15 to 20 GB** range
- leaves safety margin for future additions

---

## What should be skipped for now

These should be skipped unless more storage is added:

### Not comfortable right now

- installing multiple large video model families together
- keeping many checkpoints and LoRAs at once
- full `Open-Sora` experiments
- separate local Whisper + XTTS + multiple video stacks all on the same 62 GB volume
- heavy caching of intermediate renders

### Specifically risky with current free space

- `Stable Video Diffusion` + `AnimateDiff` + `ModelScope` together
- `Open-Sora` plus ComfyUI assets
- multiple SDXL / Flux / video checkpoints together

---

## Estimated storage if we want to install all major options

These are practical estimates, not exact numbers.

### Minimal current working video stack

- no remote video model
- current ffmpeg + existing TTS usage only
- additional space needed: **0 to 2 GB**

### Lean recommended Vast.ai video stack

- ComfyUI
- AnimateDiff
- one image-to-video model/checkpoint
- temp output buffer
- estimated total: **15 to 20 GB**

### Medium stack

- ComfyUI
- AnimateDiff
- Stable Video Diffusion
- one extra text-to-video model
- some LoRAs / custom nodes / cache
- estimated total: **28 to 40 GB**

This is already beyond the comfortable margin of the current server.

### “Install everything useful” stack

If by “all” you mean:

- ComfyUI
- AnimateDiff
- Stable Video Diffusion
- ModelScope text-to-video
- extra checkpoints
- LoRAs
- custom nodes
- temp caches
- export cache
- multiple workflows ready in parallel

Estimated total additional need:

- about **45 to 70 GB**

### Experimental all-in stack including Open-Sora class experiments

Estimated total additional need:

- **70 GB to 120+ GB**

That is **not suitable** for the current 62 GB workspace volume.

---

## Recommended plan

### Best immediate plan

Use the current shipped admin video builder now, and install only a lean remote stack next:

1. keep current FastAPI stitched-video flow active
2. install `ComfyUI`
3. add one remote image-to-video or AnimateDiff workflow
4. add one FastAPI helper endpoint later for remote scene rendering
5. keep only one main video checkpoint family on disk

### If you want true AI video generation soon

Add more Vast.ai storage first.

Recommended target volume for comfort:

- **100 GB minimum** if you want one solid production stack
- **150 GB+** if you want multiple model families and room for caches/renders

---

## Commands used to inspect Vast.ai

### Storage and installed software

```bash
ssh -p 51734 root@123.21.129.10

df -h / /workspace
du -sh /workspace/*
ollama list
```

### Check whether ComfyUI exists

```bash
[ -d /workspace/video-stack/ComfyUI ] && echo installed || echo not-installed
```

---

## Bottom line

### Already done

- admin video UI added
- FastAPI video job endpoints added
- local stitched-video pipeline added
- Vast.ai state inspected

### Not yet done

- no ComfyUI install yet
- no remote video checkpoint install yet
- no dedicated FastAPI → Vast.ai video-model endpoint yet

### Current capacity answer

- yes, a **lean** remote video stack can still be added
- no, we should **not** install all major video frameworks/models on the current 62 GB volume
- for an “install everything useful” setup, assume **45 to 70 GB extra**, and more if experimental models are included
