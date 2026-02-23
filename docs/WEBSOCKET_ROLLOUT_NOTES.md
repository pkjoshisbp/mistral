# WebSocket Rollout & Rollback Notes (Widget)

## Goal
Safely re-introduce widget socket streaming with immediate rollback path.

## Current state
- Widget WebSocket client path is present in `laravel/resources/views/widget/script.blade.php`.
- It is now controlled by env flag:
  - `WIDGET_WEBSOCKET_ENABLED` (default false)
   - `WIDGET_WS_URL` (optional override; if empty it auto-derives from host/port)

## Ratchet server (Laravel)
- Command: `php artisan widget:websocket`
- Optional flags:
   - `--host=0.0.0.0`
   - `--port=8090`
   - `--tls=true`
   - `--tls-cert=/var/www/clients/client1/web64/ssl/ai-chat.support-le.crt`
   - `--tls-key=/var/www/clients/client1/web64/ssl/ai-chat.support-le.key`
- Env defaults used by command:
   - `WIDGET_WEBSOCKET_HOST=0.0.0.0`
   - `WIDGET_WEBSOCKET_PUBLIC_HOST=ai-chat.support`
   - `WIDGET_WEBSOCKET_PORT=8090`
   - `WIDGET_WEBSOCKET_TLS=true`
   - `WIDGET_WEBSOCKET_TLS_CERT=/var/www/clients/client1/web64/ssl/ai-chat.support-le.crt`
   - `WIDGET_WEBSOCKET_TLS_KEY=/var/www/clients/client1/web64/ssl/ai-chat.support-le.key`
   - `WIDGET_WEBSOCKET_TLS_PASSPHRASE=`
   - `WIDGET_WEBSOCKET_FORWARD_TIMEOUT=65`
- Component:
   - `laravel/app/WebSockets/WidgetChatSocketServer.php`

## Systemd (root-run)
- Service file available at:
   - `scripts/widget-websocket.service`
- Install commands (run as root):
   - `cp /var/www/clients/client1/web64/web/scripts/widget-websocket.service /etc/systemd/system/`
   - `systemctl daemon-reload`
   - `systemctl enable widget-websocket.service`
   - `systemctl restart widget-websocket.service`
   - `systemctl status widget-websocket.service`

## Enable (safe rollout)
1. Set env:
   - `WIDGET_WEBSOCKET_ENABLED=true`
   - `WIDGET_WEBSOCKET_PUBLIC_HOST=ai-chat.support`
   - Optional explicit URL: `WIDGET_WS_URL=wss://ai-chat.support:8090`
2. Clear config cache:
   - `php artisan config:clear`
3. Start Ratchet server:
   - `php artisan widget:websocket --host=0.0.0.0 --port=8090 --tls=true --tls-cert=/var/www/clients/client1/web64/ssl/ai-chat.support-le.crt --tls-key=/var/www/clients/client1/web64/ssl/ai-chat.support-le.key`
3. Verify one test org widget stream in browser console for
   - "[AI Chat] WebSocket connected"

## Immediate rollback (no deploy needed)
1. Set env:
   - `WIDGET_WEBSOCKET_ENABLED=false`
2. Clear config cache:
   - `php artisan config:clear`
3. Widget auto-falls back to existing HTTP chat flow.
4. Stop Ratchet process/service.

## Ratchet server plan (recommended phased)
### Phase 1: Parallel server
- Build Ratchet server in separate process/service (no replacement yet).
- Keep HTTP chat endpoints unchanged.

### Phase 2: Controlled org rollout
- Keep global `WIDGET_WEBSOCKET_ENABLED=false`.
- Add org-level allowlist toggle later, then pilot with 1 org.

### Phase 3: Full rollout
- Enable globally after stability metrics pass.

## Suggested runtime notes to capture
- connect success rate
- avg first token latency
- stream completion rate
- fallback rate to HTTP
- per-org error count
