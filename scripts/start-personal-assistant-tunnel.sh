#!/bin/bash
set -euo pipefail

# Dedicated autossh tunnel for Personal Assistant model services on vast.ai
VAST_HOST="171.248.41.73"
VAST_PORT="29425"
VAST_USER="root"

# Local ports (FastAPI will call these)
LOCAL_WHISPER_PORT="18081"
LOCAL_XTTS_PORT="18082"
LOCAL_INDIC_PORT="18083"

# Remote ports (services running on vast.ai)
REMOTE_WHISPER_PORT="18081"
REMOTE_XTTS_PORT="18082"
REMOTE_INDIC_PORT="18083"

AUTOSSH_MONITOR_PORT="0"
LOG_FILE="/var/www/clients/client1/web64/web/logs/personal-assistant-tunnel.log"

echo "[personal-assistant-tunnel] starting tunnel to ${VAST_USER}@${VAST_HOST}:${VAST_PORT}"

is_tunnel_running() {
  pgrep -fa autossh | grep -F "root@${VAST_HOST}" | grep -F "${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT}" >/dev/null 2>&1
}

if is_tunnel_running; then
  echo "[personal-assistant-tunnel] existing tunnel found, restarting"
  pkill -f "autossh.*${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT}.*root@${VAST_HOST}" || true
  pkill -f "autossh.*root@${VAST_HOST}.*${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT}" || true
  sleep 2
fi

export AUTOSSH_GATETIME=0
export AUTOSSH_POLL=30
export AUTOSSH_LOGFILE="$LOG_FILE"
export AUTOSSH_DEBUG=1

autossh -M ${AUTOSSH_MONITOR_PORT} -f -N \
  -L ${LOCAL_WHISPER_PORT}:127.0.0.1:${REMOTE_WHISPER_PORT} \
  -L ${LOCAL_XTTS_PORT}:127.0.0.1:${REMOTE_XTTS_PORT} \
  -L ${LOCAL_INDIC_PORT}:127.0.0.1:${REMOTE_INDIC_PORT} \
  ${VAST_USER}@${VAST_HOST} \
  -p ${VAST_PORT} \
  -o ServerAliveInterval=30 \
  -o ServerAliveCountMax=3 \
  -o ExitOnForwardFailure=yes \
  -o StrictHostKeyChecking=no

sleep 3

if is_tunnel_running; then
  echo "[personal-assistant-tunnel] tunnel established"
  echo "  whisper: http://127.0.0.1:${LOCAL_WHISPER_PORT}"
  echo "  xtts:    http://127.0.0.1:${LOCAL_XTTS_PORT}"
  echo "  indic:   http://127.0.0.1:${LOCAL_INDIC_PORT}"
  echo "  log:     $LOG_FILE"
else
  echo "[personal-assistant-tunnel] failed to establish tunnel"
  exit 1
fi
