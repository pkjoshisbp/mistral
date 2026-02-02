# AI Agent Services Setup Instructions

## Prerequisites
Before setting up the services, ensure that:
1. Ollama is installed on the system
2. Mistral 7B model is pulled (`ollama pull mistral:7b`)
3. Qdrant is running on 127.0.0.1:6333
4. Python virtual environment is set up in `/var/www/clients/client1/web64/web/ai_backend/venv`

## Setup Instructions (Run as root or with sudo)

### 1. Copy service files to systemd directory
```bash
sudo cp /var/www/clients/client1/web64/web/scripts/ai-fastapi.service /etc/systemd/system/
sudo cp /var/www/clients/client1/web64/web/scripts/ai-ollama.service /etc/systemd/system/
```

### 2. Reload systemd daemon
```bash
sudo systemctl daemon-reload
```

### 3. Enable services to start on boot
```bash
sudo systemctl enable ai-ollama.service
sudo systemctl enable ai-fastapi.service
```

### 4. Start the services
```bash
# Start Ollama first (FastAPI depends on it)
sudo systemctl start ai-ollama.service

# Wait a few seconds, then start FastAPI
sleep 5
sudo systemctl start ai-fastapi.service
```

### 5. Check service status
```bash
sudo systemctl status ai-ollama.service
sudo systemctl status ai-fastapi.service
```

### 6. View service logs
```bash
# View Ollama logs
sudo journalctl -u ai-ollama.service -f

# View FastAPI logs
sudo journalctl -u ai-fastapi.service -f
```

## Service Management Commands

### Start services
```bash
sudo systemctl start ai-ollama.service
sudo systemctl start ai-fastapi.service
```

### Stop services
```bash
sudo systemctl stop ai-fastapi.service
sudo systemctl stop ai-ollama.service
```

### Restart services
```bash
sudo systemctl restart ai-ollama.service
sudo systemctl restart ai-fastapi.service
```

### Check if services are running
```bash
sudo systemctl is-active ai-ollama.service
sudo systemctl is-active ai-fastapi.service
```

## Troubleshooting

### If services fail to start:
1. Check logs: `sudo journalctl -u service-name -n 50`
2. Verify file permissions: `ls -la /var/www/clients/client1/web64/web/scripts/`
3. Check if ports are available: `netstat -tulpn | grep -E "8111|11434"`
4. Verify virtual environment: `ls -la /var/www/clients/client1/web64/web/ai_backend/venv/`

### Test endpoints manually:
```bash
# Test Ollama
curl http://localhost:11434/api/tags

# Test FastAPI
curl http://localhost:8111/docs
```

## Notes
- Services will automatically restart if they crash
- Services will start automatically on system boot
- Logs are written to syslog and can be viewed with journalctl
- Both services run as www-data user for security
