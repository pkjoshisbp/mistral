#!/bin/bash
# Monitor and auto-restart SSH tunnel to vast.ai
# Run this in a screen/tmux session: screen -S ollama-tunnel ./monitor-ollama-tunnel.sh

# Direct connection to vast.ai instance
VAST_HOST="171.248.41.73"
VAST_PORT="29425"
VAST_USER="root"
LOCAL_PORT="11435"
REMOTE_PORT="11434"

echo "🔄 Starting Ollama tunnel monitor (auto-restart enabled)"
echo "Press Ctrl+A then D to detach from screen"
echo "To reattach: screen -r ollama-tunnel"
echo ""

while true; do
    # Check if tunnel is running
    if ! pgrep -f "ssh.*${VAST_PORT}.*${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}" > /dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  Tunnel not running. Starting..."
        
        ssh -N -L ${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT} \
            ${VAST_USER}@${VAST_HOST} \
            -p ${VAST_PORT} \
            -o ServerAliveInterval=60 \
            -o ServerAliveCountMax=3 \
            -o ExitOnForwardFailure=yes \
            -o StrictHostKeyChecking=no \
            >> /var/www/clients/client1/web64/web/logs/ollama-tunnel.log 2>&1 &
        
        sleep 5
        
        # Verify it started
        if pgrep -f "ssh.*${VAST_PORT}.*${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}" > /dev/null; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✅ Tunnel restored"
        else
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ❌ Failed to start tunnel. Retrying in 30s..."
        fi
    else
        # Test if Ollama is actually responding
        if ! curl -s --max-time 5 http://127.0.0.1:${LOCAL_PORT}/api/tags > /dev/null 2>&1; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ⚠️  Tunnel exists but Ollama not responding. Restarting..."
            pkill -f "ssh.*${VAST_PORT}.*${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}"
            sleep 3
        fi
    fi
    
    # Check every 30 seconds
    sleep 30
done
