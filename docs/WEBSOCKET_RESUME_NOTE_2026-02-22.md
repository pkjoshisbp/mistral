# WebSocket Resume Note (2026-02-22)

## Current temporary state
- Widget is intentionally running HTTP fallback only.
- `WIDGET_WEBSOCKET_ENABLED=false` in `laravel/.env`.
- `initWebSocket()` call is removed from widget init in `laravel/resources/views/widget/script.blade.php`.

## Why paused
- Browser showed repeated `wss://ai-chat.support/ws` connection errors.
- Direct upgrade test to `/ws` returned `404` first, then `502` after proxy changes.
- This indicates Apache `/ws` proxy path is not fully correct/healthy yet.

## Verified facts
- Apache modules are loaded: `proxy`, `proxy_http`, `proxy_wstunnel`, `rewrite`, `ssl`.
- Local backend port is listening: `0.0.0.0:8090`.

## Resume checklist
1. Confirm websocket backend mode (local plain ws preferred):
   - Keep `WIDGET_WEBSOCKET_TLS=false` for backend on `127.0.0.1:8090`.
2. Fix SSL vhost directives for `ai-chat.support` only:
   - `ProxyPass /ws ws://127.0.0.1:8090/ retry=0 timeout=60`
   - `ProxyPassReverse /ws ws://127.0.0.1:8090/`
3. Reload services:
   - `systemctl restart widget-websocket.service`
   - `apachectl -t && systemctl reload apache2`
4. Validate websocket handshake:
   - `curl -i --http1.1 -N -H 'Connection: Upgrade' -H 'Upgrade: websocket' -H 'Sec-WebSocket-Version: 13' -H 'Sec-WebSocket-Key: SGVsbG8sIHdvcmxkIQ==' https://ai-chat.support/ws | head -n 20`
   - Expected: `HTTP/1.1 101 Switching Protocols`
5. Re-enable widget websocket after successful 101:
   - Set `WIDGET_WEBSOCKET_ENABLED=true`
   - Re-add `this.initWebSocket();` in widget init
   - Clear Laravel cache (`php artisan optimize:clear`)

## Useful diagnostics if still failing
- `apachectl -S`
- `grep -RIn 'ServerName\s\+ai-chat.support\|ProxyPass\s\+/ws' /etc/apache2/sites-enabled /etc/apache2/sites-available`
- `journalctl -u apache2 -n 200 --no-pager`
- `journalctl -u widget-websocket.service -n 200 --no-pager`
