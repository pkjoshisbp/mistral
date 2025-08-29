#!/bin/bash
# Download smaller Q4_K_S model (faster, slight quality loss)
cd /var/www/clients/client1/web64/web/ai_backend/models/llama-3.2-1b-instruct

# Backup current model
if [ -f "Llama-3.2-1B-Instruct-Q4_K_M.gguf" ]; then
    mv Llama-3.2-1B-Instruct-Q4_K_M.gguf Llama-3.2-1B-Instruct-Q4_K_M.gguf.backup
fi

# Download Q4_K_S (smaller/faster)
echo "Downloading Q4_K_S model (faster)..."
wget -q --show-progress \
  "https://huggingface.co/bartowski/Llama-3.2-1B-Instruct-GGUF/resolve/main/Llama-3.2-1B-Instruct-Q4_K_S.gguf" \
  -O Llama-3.2-1B-Instruct-Q4_K_S.gguf

# Update service to use Q4_K_S
echo "Updating service configuration..."
sed -i 's/Q4_K_M\.gguf/Q4_K_S.gguf/' /var/www/clients/client1/web64/web/scripts/ai-fastapi.service

echo "Done! Copy service file and restart:"
echo "cp /var/www/clients/client1/web64/web/scripts/ai-fastapi.service /etc/systemd/system/"
echo "systemctl daemon-reload && systemctl restart ai-fastapi.service"
