#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# Ollama Server Startup Script (hardened)
# Expects ollama installed system-wide.

export OLLAMA_HOST="${OLLAMA_HOST:-127.0.0.1}"
export OLLAMA_PORT="${OLLAMA_PORT:-11434}"
# Use a dedicated writable data directory instead of default hidden dir in parent (permission issues)
export OLLAMA_HOME="${OLLAMA_HOME:-/var/www/clients/client1/web64/web/ollama_home}" 

mkdir -p "$OLLAMA_HOME" || {
	echo "[start_ollama] ERROR: Cannot create OLLAMA_HOME at $OLLAMA_HOME" >&2
	exit 1
}
chown "${USER:-web64}:client1" "$OLLAMA_HOME" 2>/dev/null || true

LOG_DIR="/var/www/clients/client1/web64/web/ai_backend/logs"
mkdir -p "$LOG_DIR"

echo "[start_ollama] Starting Ollama at $OLLAMA_HOST:$OLLAMA_PORT (OLLAMA_HOME=$OLLAMA_HOME)" | tee -a "$LOG_DIR/ollama.startup.log"

# Optional: ensure model present (commented to avoid long pull during service start)
# if ! ollama list | grep -q 'mistral:7b'; then
#   echo "[start_ollama] Pulling model mistral:7b" | tee -a "$LOG_DIR/ollama.startup.log"
#   ollama pull mistral:7b || echo "[start_ollama] WARNING: model pull failed" | tee -a "$LOG_DIR/ollama.startup.log"
# fi

exec ollama serve >>"$LOG_DIR/ollama.stdout" 2>>"$LOG_DIR/ollama.stderr"
