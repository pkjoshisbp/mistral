# AI Chat Support — Complete Project Setup Reference

> **For Copilot / AI agent use.** Read this before making any assumptions about
> service locations, ports, GPU availability, or installed tools.
> Last updated: 2026-03-09

---

## 1. Server Overview

| Item | Value |
|------|-------|
| OS | Ubuntu 22.04 (remote server) |
| Web user | `web64` (limited — **cannot run `systemctl` directly**) |
| Web root | `/var/www/clients/client1/web64/web/laravel/public` |
| Domain | `https://ai-chat.support` |
| Workspace root | `/var/www/clients/client1/web64/web/` |
| Admin login | `admin@example.com` / `password123` |
| Customer login | `customer@ai-chat.support` / `4NAWhgQ5PskpQ2b` |

**No GPU is installed on this server.**  
All GPU work (ComfyUI image/video generation, large LLMs, voice cloning) runs on
**vast.ai** and is accessed via **SSH tunnels** from this server.

---

## 2. Component Map

```
Browser
  │
  └─▶ Nginx  ──▶  /laravel/public   (Laravel 10, PHP)
                        │
                        ▼
                  FastAPI :8111     (ai_backend/main.py)
                  │    │    │
                  │    │    └─▶ Qdrant :6333  (local vector DB)
                  │    │
                  │    └─▶ Ollama LOCAL :11434  (embeddings only – nomic-embed-text)
                  │
                  └─▶ SSH Tunnel :11435  ──▶  vast.ai Ollama :11434
                                                (large LLMs: llama3:8b, mistral-nemo)
                  │
                  └─▶ SSH Tunnel :18081  ──▶  vast.ai Whisper :18081
                                                (speech-to-text, personal assistant)
                  │
                  └─▶ edge-tts (Python module)  ──▶  Microsoft Edge TTS API
                        SOLE TTS — free, real neural voices, no local service
                        (ComfyUI / XTTS / Indic TTS removed 2026-03-11)
```

---

## 3. GPU — Vast.ai

**The GPU is NOT local.** It is rented from [vast.ai](https://vast.ai) and connected
by a persistent SSH autossh tunnel.

| Item | Value |
|------|-------|
| vast.ai SSH host | `123.21.129.10` |
| vast.ai SSH port | `51734` |
| SSH user | `root` |
| Tunnel script | `scripts/start-ollama-tunnel.sh` |
| Setup script | `setup-vast-tunnel.sh` |
| Tunnel log | `logs/ollama-tunnel.log` |

### What runs on vast.ai

| Service | vast.ai port | Local tunnel port | Purpose |
|---------|-------------|-------------------|---------|
| Ollama | 11434 | **11435** | Large LLMs (llama3:8b, mistral-nemo, llama3.2:3b) |
| Whisper | 18081 | **18081** | Speech-to-text (personal assistant) |

> **Removed 2026-03-11**: ComfyUI (:18084), XTTS v2 (:18082), Indic TTS (:18083),
> Lip-sync/Wav2Lip (:18085) — freed ~7 GB VRAM for LLM headroom.
> Video generation will move to a separate server when needed.

### VRAM budget (RTX 5060 Ti — 16,311 MiB)
| Process | VRAM | Notes |
|---------|------|-------|
| Whisper large-v3 | ~3,230 MiB | Always resident — STT |
| Ollama (one model at a time) | ~6,000–8,000 MiB | LRU eviction between models |
| **Free headroom** | **~4,500–7,000 MiB** | |

**Model priority**: qwen3.5:9b (default chat) → llama3.2:3b (fallback) → mistral-nemo (crawl/indexing)

### Ollama on vast.ai — persistent model storage
Ollama runs from **`/workspace/ollama/bin/ollama`** (NOT the system `/usr/local/bin/ollama`).
Models are stored in **`/workspace/ollama/models/`** which is on the persistent NVMe volume.
`/root/.ollama/models/` is empty — all models live on `/workspace`.
This means models survive instance restarts automatically. Always pull new models with:
```bash
ssh -p 51734 root@123.21.129.10 "ollama pull <model>"
# Ollama process is already running from /workspace so it uses /workspace/ollama/models by default
```

### Check / start tunnel
```bash
# Check if tunnel process is running
ps aux | grep autossh | grep 51734

# Start/restart the tunnel
bash /var/www/clients/client1/web64/web/scripts/start-ollama-tunnel.sh

# Test Ollama via tunnel
curl http://127.0.0.1:11435/api/tags

# Test Whisper via tunnel
curl http://127.0.0.1:18081/health
```

---

## 4. Service Ports Quick Reference

| Port | Service | Host | Notes |
|------|---------|------|-------|
| **8111** | FastAPI AI backend | local | `ai-fastapi.service`, primary API |
| **8112** | llama-server (llama.cpp) | local | spawned by FastAPI on demand |
| **11434** | Ollama LOCAL | local | embeddings only (nomic-embed-text, ~62ms) |
| **11435** | Ollama VAST.AI | local (tunnel) | large LLMs, routed by model name |
| **6333** | Qdrant vector DB | local | multi-org collections |
| **18081** | Whisper STT | local (tunnel) | personal assistant speech-to-text |

> Ports 18082–18085 (XTTS, Indic TTS, ComfyUI, Lip-sync) removed 2026-03-11.

---

## 5. FastAPI Service (`ai-fastapi.service`)

```
File:        /etc/systemd/system/ai-fastapi.service
Working dir: /var/www/clients/client1/web64/web/ai_backend/
Exec:        venv/bin/python main.py   (uvicorn on port 8111)
User:        web64
Venv:        /var/www/clients/client1/web64/web/ai_backend/venv/
Log:         /var/www/clients/client1/web64/web/ai_backend/logs/fastapi.log
```

### Restart commands
```bash
systemctl restart ai-fastapi.service
systemctl status  ai-fastapi.service
```

### Install Python packages (always use venv pip)
```bash
/var/www/clients/client1/web64/web/ai_backend/venv/bin/pip install <package>
# NEVER: pip install <package>  ← goes to system Python, service won't see it
```

---

## 6. Laravel

| Item | Value |
|------|-------|
| Path | `/var/www/clients/client1/web64/web/laravel/` |
| DB | MySQL, host `127.0.0.1:3306`, database `c1mistral` |
| DB user | `c1mistral` / `a4BTyLFt@hU5b` |
| FastAPI URL | `http://localhost:8111` (via `config/services.php` `ai_agent.url`) |
| AI provider | Ollama + llama (set in `admin_settings` table) |

### Key artisan commands
```bash
cd /var/www/clients/client1/web64/web/laravel

# Sync org data to Qdrant
php artisan sync:organization-data [org_id] [--type=faq|info|service|all]

# Test AI chat
php artisan test:ai-chat [org_slug]

# Run migrations
php artisan migrate
```

### Always use Livewire (never plain controller+view for UI)
- Components live in `app/Livewire/`
- Admin components: `app/Livewire/Admin/`
- Use Bootstrap classes — **no Tailwind CSS**

---

## 7. Ollama Model Routing

FastAPI automatically routes requests to local vs vast.ai based on model name:

```python
# local (fast, low-memory):
OLLAMA_URL_LOCAL  = "http://127.0.0.1:11434"   # embeddings, small models

# vast.ai (GPU, large models):
OLLAMA_URL_VASTAI = "http://127.0.0.1:11435"   # via SSH tunnel

VASTAI_MODELS = [
    "llama3:8b-instruct-q5_K_M",
    "llama3.1:8b",
    "mistral-nemo",
    "mistral-nemo:latest",
]
```

**nomic-embed-text must stay on local** — it runs in 62ms locally. The vast.ai
tunnel adds ~1500ms latency which would make it 27× slower.

---

## 8. Local Models

```
/var/www/clients/client1/web64/web/models/
    Llama-3.2-1B-Instruct-Q4_K_M.gguf
    Llama-3.2-3B-Instruct-f16.gguf
    Llama-3.2-3B-Instruct-Q4_K_M.gguf
    Llama-3.2-3B-Instruct-Q8_0-custom.gguf
```

llama.cpp binaries: `/var/www/clients/client1/web64/web/llama.cpp/build/bin/`
- `llama-cli` — interactive
- `llama-server` — HTTP server (port 8112, spawned on demand by FastAPI)

---

## 9. TTS Stack (Text-to-Speech)

Priority order in `_synthesize_audio_bytes()`:

1. **edge-tts** (ONLY provider) — Python venv module, calls Microsoft Edge TTS API.  
   Real distinct neural voices. 322 voices. Free. No local server required.  
   Installed in venv: `venv/bin/pip install edge-tts`

> **XTTS v2** and **Indic gTTS** removed 2026-03-11 to free GPU VRAM for LLMs.

### Default voice: `en-IN-NeerjaExpressiveNeural`
See `_EDGE_TTS_VOICE_MAP` in `main.py` for all 322 mapped voices.

### SSML markup supported in voiceover text:
`**bold**`, `[rate:slow]...[/rate]`, `[pause:500]`, `[pitch:high]...[/pitch]`,
`[volume:soft]...[/volume]`, `[prosody attr=...]...[/prosody]`

---

## 10. Video Generation Pipeline

### Storage paths
| Path | Purpose |
|------|---------|
| `laravel/storage/app/video-generation/jobs/` | JSON job state files |
| `laravel/storage/app/public/video-generation/output/` | Final MP4 files |
| `laravel/storage/app/video-generation/tmp/` | Scene temp clips |
| `https://ai-chat.support/storage/video-generation/output/{id}.mp4` | Public URL |

### Scene rendering flow
1. `_render_scene_clip()` — per scene: TTS audio + image/video source
2. ComfyUI (vast.ai GPU) — if available: img2vid / text2vid + RealESRGAN upscale
3. FFmpeg fallback — if ComfyUI unreachable: static image + Ken Burns effect
4. `_finalize_scene_clip()` — applies word-wrapped text overlays via `_apply_voiceover_overlay()`
5. `_process_video_job()` — concatenates all scene clips + adds background music

### Text overlay (fixed 2026-03-09)
- Uses `_wrap_text_lines()` — one `drawtext` filter per line with pixel Y offsets
- `\n` in FFmpeg subprocess args is NOT interpreted — fixed by multi-filter approach
- fontsize=36, ~55 chars/line on 1280px, zone positions: top/middle/bottom

### ComfyUI (vast.ai)
- URL: `http://127.0.0.1:18084` (SSH tunnel to vast.ai)
- Checkpoint: `v1-5-pruned-emaonly.safetensors`
- Motion model: `mm_sd_v15_v2.ckpt`
- Upscale: `RealESRGAN_x4plus.pth`
- **If ComfyUI tunnel is down → FFmpeg fallback is used automatically**

---

## 11. Qdrant Vector Database

- **URL**: `http://127.0.0.1:6333` (local, always available)
- **Collection naming**: `{organization_slug}` (e.g., `ai-chat-support`)
- **Vector dimensions**: 768 (nomic-embed-text)
- **Point ID format**: `{data_type}_{item_id}` (e.g., `faq_123`, `info_456`)

---

## 12. Avatar / Lip-Sync System

### Current status (2026-03-09)
- **Static avatar overlay** — fully working, GPU-free (Pillow + FFmpeg)
- **Lightweight local lip-sync** — fully working, CPU-only (no external service required)
- **Remote lip-sync API integration** — optional, provider is pluggable (Wav2Lip or other lightweight service)

### How it works now
1. User selects an avatar in the video generation admin UI (8 built-in portraits + custom URL)
2. User picks position (bottom-right, bottom-left, center-right, etc.), size (small/medium/large), shape (circle/rounded/rectangle)
3. FastAPI downloads the portrait, Pillow crops it to the chosen shape with a blue border ring
4. FFmpeg composites the avatar PNG onto each rendered scene clip
5. No GPU required for the static path

### Lip-sync modes
FastAPI now supports these modes via `LIPSYNC_MODE`:

- `local` (default): generate lightweight mouth animation locally using existing FastAPI runtime
- `remote`: call external API (`LIPSYNC_URL`) only
- `auto`: try remote first, fallback to local if remote is unavailable
- `off`: disable lip-sync and use static avatar only

### Optional remote lip-sync API on vast.ai
When you rent a new vast.ai instance:
```bash
# 1. On the vast.ai instance, run any lightweight lip-sync API on port 18085
#    The API contract: POST /generate with files: {image, audio} -> returns MP4
#    and return an MP4 binary response.

# 2. Add port 18085 to the autossh tunnel:
#    Edit scripts/start-ollama-tunnel.sh to add:
#    -L 18085:127.0.0.1:18085

# 3. Enable in systemd service:
#    Edit /etc/systemd/system/ai-fastapi.service, add:
#    Environment=LIPSYNC_ENABLED=true
#    Environment=LIPSYNC_URL=http://127.0.0.1:18085
#    Environment=LIPSYNC_MODE=auto

# 4. Reload and restart
#    systemctl daemon-reload && systemctl restart ai-fastapi.service
```

### Avatar catalog endpoint
`GET http://localhost:8111/video/avatar-catalog` — returns the full avatar list, lip-sync status, and all valid position/size/shape options.

### Key constants in main.py
| Constant | Default | Purpose |
|----------|---------|---------|
| `LIPSYNC_MODE` | `auto` | Lip-sync mode (`local`, `remote`, `auto`, `off`) |
| `LIPSYNC_URL` | `http://127.0.0.1:18085` | Lip-sync service (via SSH tunnel) |
| `LIPSYNC_ENABLED` | `false` | Enables remote lip-sync API calls |
| `AVATAR_CACHE_DIR` | `…/video-generation/avatar-cache/` | Cached processed avatar PNGs |
| `AVATAR_CATALOG` | 8 portraits (f1–f4, m1–m4) | Built-in avatar library |
| `AVATAR_SIZE_FRACTIONS` | small=0.22,medium=0.29,large=0.38 | Avatar height as fraction of frame |

---

## 13. Multi-Tenant Data Architecture

- Organizations are isolated: each gets its own Qdrant collection
- Laravel models: `Organization`, `OrganizationData`, `User` (many-to-many via pivot)
- Sync to Qdrant on every CRUD: `app/Services/UnifiedSyncService.php`
- AI abstraction: `app/Services/AiAgentService.php`
- Widget embed: `https://ai-chat.support/widget/{org_slug}/script.js`

---

## 14. Key File Locations

| File | Purpose |
|------|---------|
| `ai_backend/main.py` | FastAPI — all AI endpoints, video pipeline, TTS |
| `ai_backend/requirements.txt` | Python deps (install via venv pip) |
| `laravel/app/Livewire/Admin/VideoGenerationManager.php` | Video gen UI & submit logic |
| `laravel/resources/views/livewire/admin/video-generation-manager.blade.php` | Video gen blade |
| `laravel/app/Services/AiAgentService.php` | AI provider abstraction |
| `laravel/app/Services/UnifiedSyncService.php` | Qdrant sync |
| `laravel/app/Models/VideoGenerationJob.php` | Video job model |
| `scripts/start-ollama-tunnel.sh` | Start autossh tunnel to vast.ai |
| `docs/qdrant.md` | Qdrant usage guide |
| `docs/SERVICES_SETUP.md` | Service setup history |

---

## 15. Common Debugging Checklist

```bash
# Is FastAPI running?
systemctl status ai-fastapi.service

# Is vast.ai tunnel up?
ps aux | grep autossh | grep 51734
curl -s http://127.0.0.1:11435/api/tags    # Ollama via tunnel
curl -s http://127.0.0.1:18084/system_stats # ComfyUI via tunnel

# Is Qdrant running?
curl -s http://127.0.0.1:6333/collections

# Is edge-tts installed in venv?
/var/www/clients/client1/web64/web/ai_backend/venv/bin/python -c "import edge_tts; print('OK')"

# FastAPI logs
tail -f /var/www/clients/client1/web64/web/ai_backend/logs/fastapi.log

# Tunnel logs
tail -f /var/www/clients/client1/web64/web/logs/ollama-tunnel.log
```

---

## 16. Critical Warnings

1. **GPU is on vast.ai — never assume `nvidia-smi` works locally.**
2. **Always install Python packages via `venv/bin/pip`** — system pip won't be seen by the service.
3. **Never use `php artisan serve` for testing** — always use `https://ai-chat.support`.
4. **ComfyUI being unreachable is normal** (vast.ai instance may be off) — the FFmpeg fallback handles it.
5. **Do not add `nomic-embed-text` to `VASTAI_MODELS`** — tunnel latency makes it 27× slower.
6. **Bootstrap only for CSS** — the project does not use Tailwind.
7. **Livewire for all frontend interactions** — no plain controller+view for UI.
