#!/bin/bash

# SSH Tunnel Setup for vast.ai Ollama Instance
# This creates a secure tunnel from localhost:11434 to the vast.ai GPU server

# CONFIGURATION
VAST_SSH_HOST="<VAST_SSH_HOST>"  # e.g., ssh://ssh4.vast.ai
VAST_SSH_PORT="<PORT>"            # e.g., 12345
LOCAL_PORT=11434
REMOTE_PORT=11434

echo "🚀 Setting up SSH tunnel to vast.ai Ollama instance..."
echo ""
echo "Configuration:"
echo "  Local:  http://127.0.0.1:${LOCAL_PORT}"
echo "  Remote: ${VAST_SSH_HOST}:${VAST_SSH_PORT}"
echo "  Model:  llama3:8b-instruct-q5_K_M"
echo ""

# Check if tunnel is already running
if pgrep -f "ssh.*-L.*${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}" > /dev/null; then
    echo "⚠️  SSH tunnel already running!"
    echo ""
    echo "Process info:"
    ps aux | grep -E "ssh.*-L.*${LOCAL_PORT}" | grep -v grep
    echo ""
    read -p "Kill existing tunnel and restart? (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        pkill -f "ssh.*-L.*${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}"
        sleep 2
        echo "✓ Killed existing tunnel"
    else
        echo "Keeping existing tunnel. Exiting."
        exit 0
    fi
fi

# Start SSH tunnel in background
echo "Starting SSH tunnel..."
ssh -N -L ${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT} root@${VAST_SSH_HOST} -p ${VAST_SSH_PORT} &

# Wait a moment for connection
sleep 3

# Test connection
echo ""
echo "Testing connection..."
if curl -s http://127.0.0.1:${LOCAL_PORT}/api/tags > /dev/null 2>&1; then
    echo "✅ SUCCESS! Tunnel is active and Ollama is reachable"
    echo ""
    echo "Available models:"
    curl -s http://127.0.0.1:${LOCAL_PORT}/api/tags | python3 -m json.tool | grep '"name"' | head -5
    echo ""
    echo "📝 To make this persistent:"
    echo "   1. Install autossh: sudo apt install autossh"
    echo "   2. Create systemd service (see setup-autossh-service.sh)"
    echo ""
    echo "🔧 To test the chat endpoint:"
    echo "   curl -X POST http://127.0.0.1:11434/api/chat -d '{\"model\":\"llama3:8b-instruct-q5_K_M\",\"messages\":[{\"role\":\"user\",\"content\":\"Hello\"}],\"stream\":false}'"
else
    echo "❌ FAILED! Could not connect to Ollama"
    echo ""
    echo "Troubleshooting:"
    echo "  1. Check SSH credentials are correct"
    echo "  2. Verify vast.ai instance is running"
    echo "  3. Confirm Ollama is running on the remote server"
    echo "  4. Check firewall/security group settings"
    pkill -f "ssh.*-L.*${LOCAL_PORT}:127.0.0.1:${REMOTE_PORT}"
    exit 1
fi

echo ""
echo "Press Ctrl+C to stop the tunnel, or let it run in background."
