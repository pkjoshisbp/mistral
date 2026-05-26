#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/.vastai-tunnel.env"

if [ -f "$CONFIG_FILE" ]; then
  # shellcheck disable=SC1090
  . "$CONFIG_FILE"
fi

# Dedicated autossh tunnel for Personal Assistant model services on vast.ai
VAST_HOST="${VAST_HOST:-123.21.80.170}"
VAST_PORT="${VAST_PORT:-51734}"
VAST_USER="${VAST_USER:-root}"

# Local ports (FastAPI will call these)
LOCAL_WHISPER_PORT="18081"
LOCAL_XTTS_PORT="18082"
LOCAL_INDIC_PORT="18083"
LOCAL_COMFYUI_PORT="18084"

# Remote ports (services running on vast.ai)
REMOTE_WHISPER_PORT="18081"
REMOTE_XTTS_PORT="18082"
REMOTE_INDIC_PORT="18083"
REMOTE_COMFYUI_PORT="8188"

AUTOSSH_MONITOR_PORT="0"
LOG_FILE="/var/www/clients/client1/web64/web/logs/personal-assistant-tunnel.log"

echo "[personal-assistant-tunnel] starting tunnel to ${VAST_USER}@${VAST_HOST}:${VAST_PORT}"

is_tunnel_running() {
  ss -ltn | grep -q ":${LOCAL_WHISPER_PORT}" && \
  ss -ltn | grep -q ":${LOCAL_XTTS_PORT}" && \
  ss -ltn | grep -q ":${LOCAL_INDIC_PORT}" && \
  ss -ltn | grep -q ":${LOCAL_COMFYUI_PORT}"
}

is_autossh_running() {
  pgrep -f "autossh.*${VAST_PORT}.*${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT}" >/dev/null
}

echo "[personal-assistant-tunnel] cleaning up previous tunnel processes"
pkill -f "autossh.*${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT}.*${VAST_USER}@${VAST_HOST}" || true
pkill -f "autossh.*${VAST_USER}@${VAST_HOST}.*${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT}" || true
pkill -f "autossh.*${VAST_PORT}.*${LOCAL_WHISPER_PORT}" || true
pkill -f "autossh.*${VAST_PORT}.*${LOCAL_XTTS_PORT}" || true
pkill -f "autossh.*${VAST_PORT}.*${LOCAL_INDIC_PORT}" || true
sleep 2

export AUTOSSH_GATETIME=0
export AUTOSSH_POLL=30
export AUTOSSH_LOGFILE="$LOG_FILE"
export AUTOSSH_DEBUG=1

autossh -M ${AUTOSSH_MONITOR_PORT} -f -N \
  -L ${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT} \
  -L ${LOCAL_XTTS_PORT}:127.0.0.1:${REMOTE_XTTS_PORT} \
  -L ${LOCAL_INDIC_PORT}:127.0.0.1:${REMOTE_INDIC_PORT} \
  -L ${LOCAL_COMFYUI_PORT}:127.0.0.1:${REMOTE_COMFYUI_PORT} \
  ${VAST_USER}@${VAST_HOST} \
  -p ${VAST_PORT} \
  -o ServerAliveInterval=30 \
  -o ServerAliveCountMax=3 \
  -o ExitOnForwardFailure=yes \
  -o StrictHostKeyChecking=no \
  -o ConnectTimeout=10 \
  -o LogLevel=ERROR

for _ in $(seq 1 30); do
  if is_tunnel_running; then
    break
  fi
  sleep 2
done

if is_tunnel_running; then
  echo "[personal-assistant-tunnel] tunnel established"
  echo "  whisper:  http://127.0.0.1:${LOCAL_WHISPER_PORT}"
  echo "  xtts:     http://127.0.0.1:${LOCAL_XTTS_PORT}"
  echo "  indic:    http://127.0.0.1:${LOCAL_INDIC_PORT}"
  echo "  comfyui:  http://127.0.0.1:${LOCAL_COMFYUI_PORT}"
  echo "  log:      $LOG_FILE"
elif is_autossh_running; then
  echo "[personal-assistant-tunnel] autossh process started; ports are still warming up"
  echo "  whisper:  http://127.0.0.1:${LOCAL_WHISPER_PORT}"
  echo "  xtts:     http://127.0.0.1:${LOCAL_XTTS_PORT}"
  echo "  indic:    http://127.0.0.1:${LOCAL_INDIC_PORT}"
  echo "  comfyui:  http://127.0.0.1:${LOCAL_COMFYUI_PORT}"
  echo "  log:      $LOG_FILE"
else
  echo "[personal-assistant-tunnel] failed to establish tunnel (check $LOG_FILE for auth/port errors)"
  exit 1
fi
