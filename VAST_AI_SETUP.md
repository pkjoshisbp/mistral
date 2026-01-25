# 🚀 Vast.ai Ollama Integration - Setup Guide

## 📋 Overview

**Primary LLM:** vast.ai GPU server with Ollama (llama3:8b-instruct-q5_K_M)
**Fallback LLM:** Local llama-server (Llama-3.2-3B-Instruct-Q8_0)
**Connection:** SSH tunnel from localhost:11434 → vast.ai Ollama

## ⚙️ Configuration Summary

```bash
# Your Configuration
MODEL = llama3:8b-instruct-q5_K_M
OLLAMA_URL = http://127.0.0.1:11434
VAST_SSH_HOST = <your-vast-ssh-host>    # Get from vast.ai dashboard
VAST_SSH_PORT = <your-vast-ssh-port>    # Get from vast.ai dashboard
```

## 🔹 STEP 1: Update Configuration Files

### 1.1 Edit setup-vast-tunnel.sh
```bash
nano /var/www/clients/client1/web64/web/setup-vast-tunnel.sh
```

Replace these lines with your actual vast.ai credentials:
```bash
VAST_SSH_HOST="<VAST_SSH_HOST>"  # e.g., ssh4.vast.ai
VAST_SSH_PORT="<PORT>"            # e.g., 12345
```

### 1.2 Verify FastAPI Backend
Already configured ✓
- Default model: `llama3:8b-instruct-q5_K_M`
- Ollama URL: `http://127.0.0.1:11434`
- Fallback: Local llama-server on port 8112

### 1.3 Verify Laravel Backend
Already configured ✓
- Backend type: `ollama`
- Model provider: `llama`

## 🔹 STEP 2: Start SSH Tunnel

### Manual Method (for testing):
```bash
cd /var/www/clients/client1/web64/web
./setup-vast-tunnel.sh
```

This will:
1. Create SSH tunnel to vast.ai
2. Test the connection
3. Show available models
4. Keep running in background

### Quick Manual Command:
```bash
ssh -N -L 11434:127.0.0.1:11434 root@<VAST_SSH_HOST> -p <PORT>
```

## 🔹 STEP 3: Test the Setup

### 3.1 Test Ollama Connection
```bash
# Check if Ollama is reachable
curl http://127.0.0.1:11434/api/tags

# Test chat endpoint
curl -X POST http://127.0.0.1:11434/api/chat \
  -H "Content-Type: application/json" \
  -d '{
    "model": "llama3:8b-instruct-q5_K_M",
    "messages": [{"role": "user", "content": "What is AI Chat Support?"}],
    "stream": false
  }' | python3 -m json.tool
```

### 3.2 Test via FastAPI
```bash
curl -X POST http://localhost:8111/llm/chat \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [
      {"role": "system", "content": "You are a helpful assistant"},
      {"role": "user", "content": "Hello, test message"}
    ],
    "model": "llama3:8b-instruct-q5_K_M",
    "backend_type": "ollama"
  }' | python3 -m json.tool
```

### 3.3 Test via Laravel Widget
Visit: https://ai-chat.support and test the chat widget

## 🔹 STEP 4: Make Tunnel Persistent (Optional but Recommended)

### 4.1 Install autossh
```bash
# Ask your system admin to run:
sudo apt install autossh
```

### 4.2 Create systemd service
```bash
sudo nano /etc/systemd/system/vast-tunnel.service
```

Paste this content:
```ini
[Unit]
Description=SSH Tunnel to vast.ai Ollama
After=network.target

[Service]
Type=simple
User=web64
ExecStart=/usr/bin/autossh -M 0 -N -o "ServerAliveInterval 30" -o "ServerAliveCountMax 3" -L 11434:127.0.0.1:11434 root@<VAST_SSH_HOST> -p <PORT>
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### 4.3 Enable and start service
```bash
sudo systemctl daemon-reload
sudo systemctl enable vast-tunnel.service
sudo systemctl start vast-tunnel.service
sudo systemctl status vast-tunnel.service
```

## 🔍 Monitoring & Troubleshooting

### Check Tunnel Status
```bash
# Check if tunnel is running
ps aux | grep "ssh.*11434"

# Check if Ollama is responding
curl http://127.0.0.1:11434/api/tags
```

### Check FastAPI Logs
```bash
tail -f /var/www/clients/client1/web64/web/ai_backend/fastapi.log | grep -E "Ollama|llama-server|fallback"
```

### Check Laravel Logs
```bash
tail -f /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -E "LLM|AI Agent"
```

### Common Issues

**Issue: "Connection refused" to localhost:11434**
- Solution: SSH tunnel is not running. Run `./setup-vast-tunnel.sh`

**Issue: "Ollama chat failed"**
- Solution: Check if vast.ai instance is running and Ollama service is active
- Fallback: System will automatically use local llama-server

**Issue: "Both Ollama and llama-server failed"**
- Solution 1: Restart FastAPI: Ask admin to run `systemctl restart ai-fastapi.service`
- Solution 2: Check llama-server: `ps aux | grep llama-server`

## 📊 Performance Expectations

### vast.ai Ollama (Primary)
- Model: llama3:8b-instruct-q5_K_M
- Response Time: 8-15 seconds (depends on GPU)
- Quality: Higher quality (8B model)
- Cost: Pay per hour for GPU

### Local llama-server (Fallback)
- Model: Llama-3.2-3B-Instruct-Q8_0
- Response Time: 35-45 seconds
- Quality: Good (3B model)
- Cost: Free (local CPU)

## 🎯 Architecture Flow

```
User → Laravel Widget
    ↓
Laravel → FastAPI (/llm/chat)
    ↓
FastAPI → Try Ollama (localhost:11434 via SSH tunnel)
    ↓         ↓
    ✓         ✗ (timeout/error)
    ↓         ↓
Return    Fallback → llama-server (localhost:8112)
             ↓
          Return
```

## 🔐 Security Notes

1. SSH tunnel is **private** - only accessible from localhost
2. No public exposure of Ollama endpoint
3. Credentials stored locally in script
4. Uses SSH key authentication (recommended over password)

## ✅ Checklist

- [ ] Updated `setup-vast-tunnel.sh` with vast.ai credentials
- [ ] Tested SSH tunnel manually
- [ ] Verified Ollama is reachable at localhost:11434
- [ ] Tested FastAPI `/llm/chat` endpoint
- [ ] Tested Laravel widget with real chat
- [ ] (Optional) Set up autossh systemd service
- [ ] Documented vast.ai credentials somewhere safe

## 📞 Support

If you encounter issues:
1. Check tunnel is running: `ps aux | grep ssh | grep 11434`
2. Test Ollama directly: `curl http://127.0.0.1:11434/api/tags`
3. Check FastAPI logs for fallback behavior
4. Verify vast.ai instance is active and running

---

**Status:** ✅ Configured and ready for testing
**Next Step:** Start the SSH tunnel and test the chat widget
