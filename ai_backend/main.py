from fastapi import FastAPI, Request, HTTPException
import httpx
from qdrant_client import QdrantClient
from qdrant_client.models import Distance, VectorParams, PointStruct
import uuid
import json

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

@app.post("/qdrant/create_collection")
async def create_collection(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    vector_size = data.get("vector_size", 4096)  # Default for Mistral 7B
    
    try:
        qdrant.create_collection(
            collection_name=collection_name,
            vectors_config=VectorParams(size=vector_size, distance=Distance.COSINE)
        )
        return {"status": "success", "message": f"Collection {collection_name} created"}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/qdrant/add")
async def add_to_qdrant(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    vector = data["vector"]
    payload = data["payload"]
    point_id = data.get("id", str(uuid.uuid4()))
    
    try:
        qdrant.upsert(
            collection_name=collection_name,
            points=[PointStruct(id=point_id, vector=vector, payload=payload)]
        )
        return {"status": "success", "id": point_id}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/qdrant/search")
async def search_qdrant(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    query_vector = data["query_vector"]
    limit = data.get("limit", 5)
    
    try:
        results = qdrant.search(
            collection_name=collection_name,
            query_vector=query_vector,
            limit=limit
        )
        return {"results": [{"id": r.id, "score": r.score, "payload": r.payload} for r in results]}
    except Exception as e:
        raise HTTPException(status_code=400, detail=str(e))

@app.post("/llm/answer")
async def llm_answer(request: Request):
    data = await request.json()
    prompt = data["prompt"]
    model = data.get("model", "mistral:7b")
    
    try:
        async with httpx.AsyncClient(timeout=60.0) as client:
            resp = await client.post(f"{OLLAMA_URL}/api/generate", json={
                "model": model,
                "prompt": prompt,
                "stream": False
            })
            result = resp.json()
            return {"answer": result.get("response", "")}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/llm/chat")
async def llm_chat(request: Request):
    data = await request.json()
    messages = data["messages"]
    model = data.get("model", "mistral:7b")
    
    try:
        async with httpx.AsyncClient(timeout=60.0) as client:
            resp = await client.post(f"{OLLAMA_URL}/api/chat", json={
                "model": model,
                "messages": messages,
                "stream": False
            })
            result = resp.json()
            return {"message": result.get("message", {})}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
