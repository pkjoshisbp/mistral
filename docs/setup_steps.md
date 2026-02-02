# Project Setup Steps and Shell Commands

## 1. Create Python FastAPI Backend

```bash
# Create backend directory
mkdir -p /var/www/clients/client1/web64/web/ai_backend
cd /var/www/clients/client1/web64/web/ai_backend

# Initialize git repo if not already
if [ ! -d ".git" ]; then git init; fi

git remote add origin https://github.com/pkjoshisbp/mistral.git

# Create Python virtual environment
python3 -m venv venv
source venv/bin/activate

# Create requirements.txt
cat <<EOT > requirements.txt
fastapi
uvicorn[standard]
httpx
qdrant-client
EOT

# Install dependencies
pip install -r requirements.txt

git add requirements.txt

git commit -m "Add FastAPI backend requirements"
git push -u origin main
```

## 2. Install Ollama and Mistral 7B

```bash
# Download and install Ollama (see https://ollama.com/download for details)
# After install, pull Mistral 7B model
ollama pull mistral:7b
```

## 3. Create FastAPI App

```bash
# Create main.py with basic endpoint
cat <<EOT > main.py
from fastapi import FastAPI, Request
import httpx
from qdrant_client import QdrantClient

app = FastAPI()
qdrant = QdrantClient(host="127.0.0.1", port=6333)
OLLAMA_URL = "http://localhost:11434"

@app.post("/embed")
async def embed(request: Request):
    data = await request.json()
    text = data["text"]
    async with httpx.AsyncClient() as client:
        resp = await client.post(f"{OLLAMA_URL}/api/embeddings", json={"model": "mistral:7b", "prompt": text})
        embedding = resp.json()["embedding"]
    return {"embedding": embedding}
EOT

git add main.py

git commit -m "Add FastAPI main.py with /embed endpoint"
git push
```

## 4. Run FastAPI Server

```bash
source venv/bin/activate
uvicorn main:app --host 0.0.0.0 --port 8111 &
```

## 5. Create Laravel Project with Livewire

```bash
# Go to web root
cd /var/www/clients/client1/web64/web

# Install Laravel (latest)
composer create-project laravel/laravel laravel
cd laravel

# Install Livewire
composer require livewire/livewire

# Install AdminLTE (optional, via npm or CDN)
npm install admin-lte

# Initialize git and push
if [ ! -d ".git" ]; then git init; fi

git add .
git commit -m "Add Laravel project with Livewire and AdminLTE"
git push
```

## 6. MySQL Database Planning
- No separate MySQL DB needed for backend unless you want to store logs/metadata.
- Each client has their own DB; Laravel can connect to all as needed.

## 7. Regularly Push Changes
- After each major step, run:
```bash
git add .
git commit -m "<describe change>"
git push
```

---

Follow these steps for setup and version control. Update commit messages as needed for each change.
