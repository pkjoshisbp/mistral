# FastAPI AI Backend Setup Instructions

## 1. Directory Structure

Create a new folder for your FastAPI backend:

```
/var/www/clients/client1/web64/web/ai_backend
```

---

## 2. Python Virtual Environment Setup

```
cd /var/www/clients/client1/web64/web/ai_backend
python3 -m venv venv
source venv/bin/activate
```

---

## 3. requirements.txt

Create a `requirements.txt` file with:

```
fastapi
uvicorn[standard]
httpx
qdrant-client
```

---

## 4. Install Dependencies

```
source venv/bin/activate
pip install -r requirements.txt
```

---

## 5. Install Ollama and Mistral 7B

- Follow [Ollama installation instructions](https://ollama.com/download) for your OS.
- After installing Ollama, run:
  ```bash
  ollama pull mistral:7b
  ```
- Start Ollama server (usually runs as a system service).

---

## 6. FastAPI App Example (`main.py`)

Create `main.py` in `ai_backend`:

```python
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

# ...add endpoints for /qdrant/add, /qdrant/search, /llm/answer as needed...
```

---

## 7. Run FastAPI Server

```
source venv/bin/activate
uvicorn main:app --host 0.0.0.0 --port 8000
```

---

## 8. Systemd Service Template

Create a file `ai_backend.service` with:

```
[Unit]
Description=FastAPI AI Backend Service
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/clients/client1/web64/web/ai_backend
Environment="PATH=/var/www/clients/client1/web64/web/ai_backend/venv/bin"
ExecStart=/var/www/clients/client1/web64/web/ai_backend/venv/bin/uvicorn main:app --host 0.0.0.0 --port 8000
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## 9. Enable and Start Service

```
sudo cp ai_backend.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable ai_backend
sudo systemctl start ai_backend
```

---

## 10. Next Steps

- Add more endpoints to `main.py` for Qdrant and LLM as needed.
- Test API from Laravel via HTTP requests.
