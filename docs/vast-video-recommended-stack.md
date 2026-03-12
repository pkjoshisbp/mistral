# Recommended Vast.ai Video Stack

## Goal

Keep the new video-generation stack inside **15 to 20 GB** of additional disk usage so the server still has room for:

- future AI models
- cache and render files
- platform growth

## Best combination

This is the best fit for the current server.

### Install

1. `ComfyUI`
2. `ComfyUI-AnimateDiff-Evolved`
3. one `Stable Diffusion 1.5` checkpoint
4. one AnimateDiff motion model
5. use existing:
   - Ollama
   - TTS endpoints
   - FFmpeg stitching

## Why this is the best option

Compared with heavier setups, this combination gives the best tradeoff:

- good enough visual quality for SaaS/demo/marketing videos
- works on moderate GPU memory
- fits inside the chosen storage budget
- integrates cleanly with the current FastAPI scene pipeline
- easy to expand later

## Exact recommended assets

### Base software

- `ComfyUI`
- `ComfyUI-AnimateDiff-Evolved`

### Models

- SD 1.5 checkpoint:
  - `v1-5-pruned-emaonly.safetensors`
- AnimateDiff motion model:
  - `mm_sd_v15_v2.ckpt`

### Optional later

Only after confirming good free space:

- one extra SD 1.5 style checkpoint
- one upscaler
- one extra motion module

Do **not** add these yet:

- Stable Video Diffusion
- ModelScope text-to-video
- Open-Sora
- multiple SDXL/Flux families

## Estimated disk usage

### Core stack

- ComfyUI + venv: **2 to 4 GB**
- AnimateDiff nodes/models: **3 to 5 GB**
- one SD 1.5 checkpoint: **4 to 5 GB**
- temp/cache/output reserve: **4 to 6 GB**

### Total

- expected practical usage: **13 to 20 GB**

## Recommended architecture

Use this flow:

1. Laravel admin submits storyboard job
2. FastAPI breaks job into scenes
3. Ollama writes/adjusts prompts if needed
4. ComfyUI renders scene clips
5. Existing TTS generates voice-over
6. FFmpeg stitches scenes and audio
7. Final video is saved back to Laravel storage

## Best use of current remote models

Keep these on Vast.ai:

- `llama3:8b-instruct-q5_K_M` for better script generation
- `mistral-nemo:latest` as optional alternate writer/rewrite model
- `llama3.2:3b` for lighter tasks
- `nomic-embed-text` for embeddings

Removed:

- `gemma2:9b`

## What we are intentionally skipping

To stay inside the storage budget, skip:

- multi-family video models
- experimental long-video systems
- multiple checkpoint packs
- high-cache workflows
- heavy research models

## Future upgrade path

If storage is increased later:

### Phase 2

Add one of these:

- Stable Video Diffusion
- one additional image-to-video workflow

### Phase 3

Only with larger storage:

- ModelScope text-to-video
- extra LoRAs and custom nodes
- advanced continuity workflows

## Suggested install order

1. Install ComfyUI
2. Install AnimateDiff node pack
3. Add SD 1.5 checkpoint
4. Add one motion model
5. Test 5-second scene render
6. Connect scene render call from FastAPI
7. Add automatic stitching and transitions

## Bottom line

For the current server, the best practical combination is:

- `ComfyUI`
- `AnimateDiff`
- `Stable Diffusion 1.5`
- existing `Ollama + TTS + FFmpeg`

This is the strongest choice while respecting the requested **15 to 20 GB** budget.
