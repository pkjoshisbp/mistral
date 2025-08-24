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
