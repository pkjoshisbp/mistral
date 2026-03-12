# Video Generation Integration

## What is included

- Admin-only video builder in Laravel at `/admin/video-generation`
- FastAPI storyboard endpoint at `/video/jobs`
- Automatic stitching with `ffmpeg`
- Scene-level voice-over using the existing TTS pipeline
- Reference image support for each scene
- Prompt preservation for the future Vast.ai render worker

## Current behavior

The first shipped version is a practical MVP:

1. Admin defines scenes, prompts, durations, and voice-over text.
2. Optional reference images are uploaded from the admin panel.
3. FastAPI renders a stitched preview/final explainer video from those scene assets.
4. The same manifest is ready to be handed off to a dedicated Vast.ai scene generator later.

## Vast.ai recommendation

Use a lean remote stack first because storage is limited:

- ComfyUI
- AnimateDiff
- Stable Video Diffusion / image-to-video workflow
- Piper or XTTS for voice if you want remote-only rendering
- `ffmpeg` for stitching

## Storage check summary

### Web server
- Workspace usage: about 15 GB
- Free space on `/`: about 56 GB

### Vast.ai
- `/workspace` volume: 62 GB total
- Used: about 33 GB
- Free: about 30 GB

This means the 62 GB volume is present, but only ~30 GB is currently free. A lean video stack fits. A larger multi-model setup will become tight quickly.

## Recommendation

Start with one remote video model family only. Add more storage before installing multiple video checkpoints, LoRAs, and extra image models together.
