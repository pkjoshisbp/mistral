#!/bin/bash
# SSH Tunnel to vast.ai Ollama Instance with Autossh Auto-Reconnect
# This creates a tunnel so Laravel can access Ollama at http://127.0.0.1:11435
# Video generation (ComfyUI, Wav2Lip), XTTS, and Indic TTS have been removed.
# Only Ollama (11435) and Whisper STT (18081) remain.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/.vastai-tunnel.env"

if [ -f "$CONFIG_FILE" ]; then
    # shellcheck disable=SC1090
    . "$CONFIG_FILE"
fi

# Direct connection to vast.ai instance
VAST_HOST="${VAST_HOST:-123.21.80.170}"
VAST_PORT="${VAST_PORT:-51734}"
VAST_USER="${VAST_USER:-root}"
LOCAL_PORT="11435"
REMOTE_PORT="11434"
WHISPER_LOCAL_PORT="18081"
WHISPER_REMOTE_PORT="18081"

# Autossh monitoring port (must be different from LOCAL_PORT)
# Autossh uses this port+1 for bidirectional tunnel health checks
AUTOSSH_MONITOR_PORT="0"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting autossh tunnel to vast.ai Ollama...${NC}"

# Check if tunnel already exists and kill it
if pgrep -f "autossh.*${VAST_PORT}.*${LOCAL_PORT}" > /dev/null; then
    echo -e "${YELLOW}Tunnel already running. Killing existing tunnel...${NC}"
    pkill -f "autossh.*${VAST_PORT}.*${LOCAL_PORT}"
    sleep 2
fi

# Export autossh environment variables
export AUTOSSH_GATETIME=0        # 0 = wait indefinitely for first connection
export AUTOSSH_POLL=30           # Check connection every 30 seconds
export AUTOSSH_LOGFILE="/var/www/clients/client1/web64/web/logs/ollama-tunnel.log"
export AUTOSSH_DEBUG=1           # Enable debug logging

autossh -M ${AUTOSSH_MONITOR_PORT} -f -N \
    -L ${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT} \
    -L ${WHISPER_LOCAL_PORT}:127.0.0.1:${WHISPER_REMOTE_PORT} \
    ${VAST_USER}@${VAST_HOST} \
    -p ${VAST_PORT} \
    -o ServerAliveInterval=30 \
    -o ServerAliveCountMax=3 \
    -o ExitOnForwardFailure=yes \
    -o StrictHostKeyChecking=no

AUTOSSH_EXIT=$?

# Wait a moment for tunnel to establish (autossh runs in background)
sleep 5

# Check if autossh is running
if pgrep -f "autossh.*${VAST_PORT}" > /dev/null; then
    AUTOSSH_PID=$(pgrep -f "autossh.*${VAST_PORT}")
    echo -e "${GREEN}Autossh tunnel established successfully!${NC}"
    echo -e "${GREEN}   Autossh PID: $AUTOSSH_PID${NC}"
    echo -e "${GREEN}   Ollama (LLM) at: http://127.0.0.1:${LOCAL_PORT}${NC}"
    echo -e "${GREEN}   Whisper STT at: http://127.0.0.1:${WHISPER_LOCAL_PORT}${NC}"
    echo ""
    echo -e "To check tunnel status: ${YELLOW}ps aux | grep autossh | grep ${VAST_PORT}${NC}"
    echo -e "To stop tunnel: ${YELLOW}pkill -f 'autossh.*${VAST_PORT}'${NC}"
    echo -e "To view logs: ${YELLOW}tail -f /var/www/clients/client1/web64/web/logs/ollama-tunnel.log${NC}"

    # Test Ollama connection
    echo ""
    echo -e "${GREEN}Testing Ollama connection...${NC}"
    sleep 2
    if curl -s http://127.0.0.1:${LOCAL_PORT}/api/tags > /dev/null 2>&1; then
        echo -e "${GREEN}Ollama is responding!${NC}"
        curl -s http://127.0.0.1:${LOCAL_PORT}/api/tags | python3 -c "import sys, json; d=json.load(sys.stdin); print('Available models:', ', '.join([m['name'] for m in d.get('models', [])]))" 2>/dev/null || echo "Models list available"
    else
        echo -e "${YELLOW}Ollama not responding yet (may still be loading). Check logs.${NC}"
    fi
else
    echo -e "${RED}Failed to establish autossh tunnel (exit code: $AUTOSSH_EXIT)${NC}"
    echo -e "Check logs: tail -f /var/www/clients/client1/web64/web/logs/ollama-tunnel.log"
    exit 1
fi
