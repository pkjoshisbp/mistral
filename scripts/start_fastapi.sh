#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# FastAPI Backend Startup Script (hardened)
# - Activates venv
# - Optional waits for Ollama & Qdrant health before starting
# - Logs environment & versions

WORK_DIR="/var/www/clients/client1/web64/web/ai_backend"
LOG_DIR="$WORK_DIR/logs"
PORT="8111"
HOST="0.0.0.0"
UVICORN_WORKERS="1"  # Increase later if CPU allows
OLLAMA_URL="${OLLAMA_URL:-http://127.0.0.1:11434}"
QDRANT_HOST="${QDRANT_HOST:-127.0.0.1}"
QDRANT_PORT="${QDRANT_PORT:-6333}"

mkdir -p "$LOG_DIR"
cd "$WORK_DIR"

if [[ ! -d venv ]]; then
	echo "[start_fastapi] ERROR: venv not found at $WORK_DIR/venv" >&2
	exit 1
fi

source venv/bin/activate

echo "[start_fastapi] Python: $(python --version)" | tee -a "$LOG_DIR/startup.log"
echo "[start_fastapi] Using OLLAMA_URL=$OLLAMA_URL" | tee -a "$LOG_DIR/startup.log"
echo "[start_fastapi] Using QDRANT $QDRANT_HOST:$QDRANT_PORT" | tee -a "$LOG_DIR/startup.log"

# Optional: wait for Ollama
if command -v curl >/dev/null 2>&1; then
	for i in {1..15}; do
		if curl -s "${OLLAMA_URL}/api/tags" >/dev/null; then
			echo "[start_fastapi] Ollama reachable (attempt $i)" | tee -a "$LOG_DIR/startup.log"
			break
		fi
		echo "[start_fastapi] Waiting for Ollama... ($i)" | tee -a "$LOG_DIR/startup.log"
		sleep 2
	done
fi

# (Light) check Qdrant TCP port
if command -v nc >/dev/null 2>&1; then
	for i in {1..10}; do
		if nc -z "$QDRANT_HOST" "$QDRANT_PORT" 2>/dev/null; then
			echo "[start_fastapi] Qdrant reachable (attempt $i)" | tee -a "$LOG_DIR/startup.log"
			break
		fi
		echo "[start_fastapi] Waiting for Qdrant... ($i)" | tee -a "$LOG_DIR/startup.log"
		sleep 1
	done
fi

echo "[start_fastapi] Starting uvicorn on $HOST:$PORT" | tee -a "$LOG_DIR/startup.log"
exec uvicorn main:app --host "$HOST" --port "$PORT" --workers "$UVICORN_WORKERS" --no-access-log >>"$LOG_DIR/fastapi.stdout" 2>>"$LOG_DIR/fastapi.stderr"
