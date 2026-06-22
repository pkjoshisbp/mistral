# Vast.ai Rollback Note

Last updated: 2026-06-12

This project currently avoids Vast.ai for normal chat routing and embeddings:

- Default answer model/provider is OpenAI, currently `gpt-5.1-mini`.
- Query understanding uses OpenAI mini in one call for intent, rewrite, entities, and search targets.
- Qdrant embeddings use local Ollama on `http://127.0.0.1:11434`.
- The separate context relevance judge call is disabled; the final answer prompt performs the private context relevance check.
- Local Llama fallback is `llama3.2:3b`.

Keep this note so Vast.ai can be reintroduced later if OpenAI token costs become too high.

## When To Consider Rolling Back

Use Vast.ai again only if one of these is true:

- Monthly OpenAI cost is materially higher than the fixed Vast.ai cost.
- GPT mini quality is not good enough for a specific customer segment.
- We need a predictable fixed compute budget more than we need lower latency.
- A local/Vast model becomes strong enough to replace OpenAI query understanding reliably.

Before changing architecture, compare real token usage from the admin Token Usage dashboard against the expected Vast.ai monthly server cost.

## Current Safe Rollback Shape

Preferred rollback is hybrid, not full reversal:

1. Keep local embeddings on `11434`.
2. Put Vast.ai Ollama behind a separate tunnel on `11435`.
3. Use Vast.ai only for answer generation or selected orgs.
4. Keep query understanding on OpenAI until the local/Vast model matches it reliably.
5. Keep context relevance inside the final answer prompt; do not restore a separate judge call unless there is strong evidence it improves answers.

This avoids breaking Qdrant embedding generation while giving us a GPU LLM option.

## Service And Environment Knobs

FastAPI service templates live in:

- `scripts/ai-fastapi.service`
- `ai_backend.service`

Current local-first values are expected to look like:

```ini
Environment=OLLAMA_URL=http://127.0.0.1:11434
Environment=OLLAMA_URL_LOCAL=http://127.0.0.1:11434
Environment=EMBED_MODEL=nomic-embed-text
Environment=FALLBACK_EMBED_MODEL=llama3.2:1b
Environment=VASTAI_ENABLED=false
Environment=VASTAI_HEALTHCHECK_ENABLED=false
Environment=CHAT_MODEL=llama3.2:3b
```

To re-enable Vast.ai for chat while keeping embeddings local:

```ini
Environment=OLLAMA_URL=http://127.0.0.1:11435
Environment=OLLAMA_URL_LOCAL=http://127.0.0.1:11434
Environment=VASTAI_ENABLED=true
Environment=VASTAI_HEALTHCHECK_ENABLED=true
Environment=CHAT_MODEL=llama3.1:8b
```

Important: do not point embeddings to Vast.ai unless intentionally changing vector dimensions and reindexing Qdrant. The local `nomic-embed-text` model produces 768-dimensional embeddings.

## Tunnel Checklist

1. Start a Vast.ai instance with Ollama installed and the desired chat model pulled.
2. Create an SSH tunnel from local port `11435` to the remote Ollama port:

```bash
ssh -N -L 11435:127.0.0.1:11434 root@<VAST_HOST> -p <VAST_PORT>
```

3. Test the tunnel:

```bash
curl http://127.0.0.1:11435/api/tags
```

4. Update the FastAPI systemd environment values above.
5. Reload and restart FastAPI:

```bash
sudo systemctl daemon-reload
sudo systemctl restart ai-fastapi.service
```

6. Confirm the backend still uses local embeddings:

```bash
curl -s http://127.0.0.1:8111/health
curl -s -X POST http://127.0.0.1:8111/embed \
  -H "Content-Type: application/json" \
  -d '{"text":"embedding smoke test"}'
```

The embed response should still report `model` as `nomic-embed-text` and `dims` as `768`.

## Laravel/Admin Checklist

If we want Vast.ai as the global Llama fallback:

```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan tinker --execute="\\App\\Models\\AdminSetting::set('ai_model_provider', 'llama', 'select', 'ai', 'AI Model Provider'); \\App\\Models\\AdminSetting::set('llama_default_model', 'llama3.1:8b', 'select', 'ai', 'Llama Default Model');"
```

For a safer rollout, change only one organization in the organization AI settings instead of changing the global provider.

If the admin UI no longer lists the desired Vast model, add it back to:

- `laravel/app/Livewire/Admin/SettingsManager.php`
- `laravel/app/Livewire/Admin/OrganizationAiManager.php`

## Code Paths To Review

- `ai_backend/main.py`
  - `get_ollama_url()` controls normal chat routing.
  - `get_embedding_ollama_url()` should stay local unless reindexing embeddings.
- `laravel/app/Services/AiAgentService.php`
  - OpenAI default model and query understanding live here.
  - `assessContextRelevance()` is intentionally a no-call deferred result.
- `laravel/app/Http/Controllers/WidgetController.php`
  - Widget final prompt contains the context relevance guard.
- `laravel/app/Http/Controllers/Api/WhatsappWebhookController.php`
  - WhatsApp final prompt contains the same context relevance guard.

## Validation

Run these before calling the rollback successful:

```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan test tests/Unit/WidgetControllerContextReuseTest.php tests/Unit/WidgetSpamGuardTest.php
```

Also test a real widget chat for:

- Product search
- Policy/support question
- Irrelevant-context query
- Contact-info query
- Hinglish or spelling-mistake query

## Roll Forward Again

To return to the current OpenAI/local setup:

```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan tinker --execute="\\App\\Models\\AdminSetting::set('ai_model_provider', 'openai', 'select', 'ai', 'AI Model Provider'); \\App\\Models\\AdminSetting::set('openai_default_model', 'gpt-5.1-mini', 'text', 'ai', 'OpenAI Default Model'); \\App\\Models\\AdminSetting::set('llama_default_model', 'llama3.2:3b', 'select', 'ai', 'Llama Default Model');"
```

Then set FastAPI service values back to:

```ini
Environment=OLLAMA_URL=http://127.0.0.1:11434
Environment=OLLAMA_URL_LOCAL=http://127.0.0.1:11434
Environment=VASTAI_ENABLED=false
Environment=VASTAI_HEALTHCHECK_ENABLED=false
Environment=CHAT_MODEL=llama3.2:3b
```

