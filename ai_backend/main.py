from fastapi import FastAPI, Request, HTTPException, WebSocket, WebSocketDisconnect
import httpx
import logging
from qdrant_client import QdrantClient
from qdrant_client.models import Distance, VectorParams, PointStruct
import uuid
import time
import asyncio
import os
import subprocess
import psutil
import signal
try:
    from rewrite import rewrite_prompt  # type: ignore
except Exception as e:
    rewrite_import_error = e
    rewrite_prompt = None  # type: ignore

SERVICE_START_TIME = time.time()
MODEL_WARMED = False

# Config
DEFAULT_EMBED_MODEL = os.getenv("EMBED_MODEL", "nomic-embed-text")  # Fast dedicated embedding model
FALLBACK_EMBED_MODEL = os.getenv("FALLBACK_EMBED_MODEL", "llama3.2:1b")  # Fallback if nomic fails
DEFAULT_CHAT_MODEL = os.getenv("CHAT_MODEL", "llama3.2:3b")  # Use Llama 3 2B as default chat model
FALLBACK_CHAT_MODEL = os.getenv("FALLBACK_CHAT_MODEL", "llama3.2:1b")  # Fast fallback
EMBED_TIMEOUT_SEC = float(os.getenv("EMBED_TIMEOUT", "15"))
MAX_EMBED_CHARS = int(os.getenv("MAX_EMBED_CHARS", "1800"))
EMBED_CONCURRENCY = int(os.getenv("EMBED_CONCURRENCY", "2"))

# Process management config
MAX_OLLAMA_RUNNER_CPU = float(os.getenv("MAX_OLLAMA_RUNNER_CPU", "200.0"))  # Max CPU % for runner processes
MAX_OLLAMA_RUNNER_TIME = int(os.getenv("MAX_OLLAMA_RUNNER_TIME", "300"))    # Max runtime in seconds (5 min)
PROCESS_CHECK_INTERVAL = int(os.getenv("PROCESS_CHECK_INTERVAL", "30"))     # Check every 30 seconds

embed_semaphore = asyncio.Semaphore(EMBED_CONCURRENCY)

app = FastAPI()
qdrant = QdrantClient(host="127.0.0.1", port=6333)
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://localhost:11434")

logging.basicConfig(level=logging.INFO, format="[%(asctime)s] %(levelname)s %(message)s")

def cleanup_stuck_ollama_processes():
    """Clean up stuck Ollama runner processes that consume too much CPU or run too long"""
    try:
        killed_count = 0
        for proc in psutil.process_iter(['pid', 'name', 'cmdline', 'create_time']):
            try:
                if 'ollama' in proc.info['name'] and 'runner' in ' '.join(proc.info['cmdline'] or []):
                    # Check CPU usage with a 1-second interval for accuracy
                    cpu_percent = proc.cpu_percent(interval=1.0)
                    runtime = time.time() - proc.info['create_time']
                    
                    should_kill = False
                    reason = ""
                    
                    if cpu_percent > MAX_OLLAMA_RUNNER_CPU:
                        should_kill = True
                        reason = f"high CPU usage ({cpu_percent:.1f}%)"
                    elif runtime > MAX_OLLAMA_RUNNER_TIME:
                        should_kill = True
                        reason = f"long runtime ({runtime:.0f}s)"
                    
                    if should_kill:
                        logging.warning(f"Killing stuck ollama runner PID {proc.info['pid']}: {reason}")
                        proc.kill()
                        killed_count += 1
                        
                    # Log all runner processes for debugging
                    logging.info(f"Ollama runner PID {proc.info['pid']}: CPU {cpu_percent:.1f}%, runtime {runtime:.0f}s")
                        
            except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
                continue
                
        if killed_count > 0:
            logging.info(f"Cleaned up {killed_count} stuck ollama runner processes")
            time.sleep(2)  # Give processes time to clean up
            
    except Exception as e:
        logging.error(f"Error during process cleanup: {str(e)}")

async def periodic_process_cleanup():
    """Background task to periodically clean up stuck processes"""
    while True:
        try:
            await asyncio.sleep(PROCESS_CHECK_INTERVAL)
            cleanup_stuck_ollama_processes()
        except Exception as e:
            logging.error(f"Error in periodic cleanup: {str(e)}")
            await asyncio.sleep(PROCESS_CHECK_INTERVAL)

@app.get("/health")
async def health():
    uptime_sec = int(time.time() - SERVICE_START_TIME)
    
    # Count ollama runner processes
    runner_count = 0
    high_cpu_runners = 0
    try:
        for proc in psutil.process_iter(['name', 'cmdline']):
            try:
                if 'ollama' in proc.info['name'] and 'runner' in ' '.join(proc.info['cmdline'] or []):
                    runner_count += 1
                    # Get CPU usage with interval for accuracy
                    cpu_percent = proc.cpu_percent(interval=0.1)
                    if cpu_percent > MAX_OLLAMA_RUNNER_CPU:
                        high_cpu_runners += 1
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                continue
    except Exception:
        pass
    
    return {
        "status": "ok",
        "uptime_sec": uptime_sec,
        "model_warmed": MODEL_WARMED,
        "default_embed_model": DEFAULT_EMBED_MODEL,
        "fallback_embed_model": FALLBACK_EMBED_MODEL,
        "concurrency": EMBED_CONCURRENCY,
        "ollama_runners": runner_count,
        "high_cpu_runners": high_cpu_runners,
        "max_cpu_limit": MAX_OLLAMA_RUNNER_CPU,
        "max_runtime_limit": MAX_OLLAMA_RUNNER_TIME
    }

@app.post("/cleanup")
async def manual_cleanup():
    """Manually trigger cleanup of stuck Ollama processes"""
    try:
        cleanup_stuck_ollama_processes()
        return {"status": "cleanup_completed", "message": "Process cleanup triggered"}
    except Exception as e:
        logging.error(f"Manual cleanup failed: {str(e)}")
        return {"status": "error", "message": str(e)}

async def _generate_embedding(model: str, text: str, start_time: float):
    async with embed_semaphore:
        async with httpx.AsyncClient(timeout=EMBED_TIMEOUT_SEC) as client:
            resp = await client.post(
                f"{OLLAMA_URL}/api/embeddings",
                json={"model": model, "prompt": text}
            )
            if resp.status_code != 200:
                raise HTTPException(status_code=500, detail=f"Ollama API error ({model}): {resp.text}")
            result = resp.json()
            if "embedding" not in result:
                raise HTTPException(status_code=500, detail=f"No embedding field in response ({model})")
            elapsed_ms = int((time.time() - start_time) * 1000)
            return result["embedding"], elapsed_ms

@app.post("/embed")
async def embed(request: Request):
    try:
        data = await request.json()
        text = data["text"]
        requested_model = data.get("model")
        model = requested_model or DEFAULT_EMBED_MODEL
        start_time = time.time()

    # Truncation disabled: embed full text for better context
    # If embedding timeouts or model errors occur, re-enable below:
    # if len(text) > MAX_EMBED_CHARS:
    #     text = text[:MAX_EMBED_CHARS]

        # Quick health check
        async with httpx.AsyncClient(timeout=5.0) as client:
            try:
                health_resp = await client.get(f"{OLLAMA_URL}/api/tags")
                if health_resp.status_code != 200:
                    raise HTTPException(status_code=503, detail="Ollama service not available")
            except Exception:
                raise HTTPException(status_code=503, detail="Ollama service not responding")

        used_model = model
        try:
            embedding, elapsed_ms = await _generate_embedding(model, text, start_time)
        except HTTPException as he:
            if model != FALLBACK_EMBED_MODEL:
                try:
                    embedding, elapsed_ms = await _generate_embedding(FALLBACK_EMBED_MODEL, text, start_time)
                    used_model = FALLBACK_EMBED_MODEL
                except Exception:
                    raise he
            else:
                raise

        logging.info(f"embed chars={len(text)} model={used_model} ms={elapsed_ms}")
        return {"embedding": embedding, "model": used_model, "elapsed_ms": elapsed_ms, "chars": len(text)}
    except httpx.TimeoutException:
        raise HTTPException(status_code=408, detail="Ollama embedding timeout")
    except httpx.RequestError as e:
        raise HTTPException(status_code=503, detail=f"Ollama connection error: {str(e)}")
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Embedding generation error: {str(e)}")

@app.post("/rewrite")
async def rewrite_endpoint(request: Request):
    """Rewrite a user query into a concise unambiguous form using local quantized llama.cpp model."""
    try:
        if rewrite_prompt is None:
            raise HTTPException(status_code=503, detail=f"Rewrite model unavailable: {rewrite_import_error}")
        data = await request.json()
        text = data.get("text")
        if not text or not isinstance(text, str):
            raise HTTPException(status_code=400, detail="'text' must be a non-empty string")
        rewritten = await asyncio.get_event_loop().run_in_executor(None, rewrite_prompt, text)
        return {"rewrite": rewritten}
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Rewrite failed: {e}")

@app.websocket("/ws/rewrite")
async def ws_rewrite(ws: WebSocket):
    await ws.accept()
    try:
        if rewrite_prompt is None:
            await ws.send_text(f"ERROR: rewrite model unavailable: {rewrite_import_error}")
            await ws.close()
            return
        while True:
            try:
                text = await ws.receive_text()
            except WebSocketDisconnect:
                break
            if not text.strip():
                await ws.send_text("")
                continue
            # Offload blocking llama.cpp call to thread pool
            rewritten = await asyncio.get_event_loop().run_in_executor(None, rewrite_prompt, text)
            await ws.send_text(rewritten)
    except Exception:
        try:
            await ws.close()
        except Exception:
            pass

@app.post("/qdrant/create_collection")
async def create_collection(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    vector_size = data.get("vector_size", 768)  # Default for nomic-embed-text
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

@app.post("/qdrant/search_text")
async def search_qdrant_text(request: Request):
    data = await request.json()
    collection_name = data["collection_name"]
    query_text = data["query_text"]
    limit = data.get("limit", 5)
    model = data.get("model", DEFAULT_EMBED_MODEL)
    
    try:
        # First generate embedding for the query text
        start_time = time.time()
        query_vector = await _generate_embedding(model, query_text, start_time)
        
        # Then search using the vector
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
    model = data.get("model", FALLBACK_EMBED_MODEL)
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

@app.post("/embed_batch")
async def embed_batch(request: Request):
    """Batch embedding to reduce per-request overhead.
    Request JSON: {"texts": ["...", "..."], "model": optional}
    Returns: {"model": used_model, "results": [{"embedding": [...], "chars": n, "elapsed_ms": t}, ...]}
    """
    data = await request.json()
    texts = data.get("texts")
    if not isinstance(texts, list) or not texts:
        raise HTTPException(status_code=400, detail="'texts' must be a non-empty list")
    requested_model = data.get("model")
    model = requested_model or DEFAULT_EMBED_MODEL
    results = []
    overall_start = time.time()

    # Health check once
    async with httpx.AsyncClient(timeout=5.0) as client:
        try:
            health_resp = await client.get(f"{OLLAMA_URL}/api/tags")
            if health_resp.status_code != 200:
                raise HTTPException(status_code=503, detail="Ollama service not available")
        except Exception:
            raise HTTPException(status_code=503, detail="Ollama service not responding")

    used_model = model
    for t in texts:
        start_time = time.time()
        if not isinstance(t, str):
            results.append({"error": "not a string"})
            continue
    # Truncation disabled: embed full text for better context
    # If embedding timeouts or model errors occur, re-enable below:
    # if len(t) > MAX_EMBED_CHARS:
    #     t = t[:MAX_EMBED_CHARS]
        try:
            embedding, elapsed_ms = await _generate_embedding(used_model, t, start_time)
        except HTTPException as he:
            if used_model != FALLBACK_EMBED_MODEL:
                try:
                    embedding, elapsed_ms = await _generate_embedding(FALLBACK_EMBED_MODEL, t, start_time)
                    used_model = FALLBACK_EMBED_MODEL
                except Exception:
                    results.append({"error": str(he.detail)})
                    continue
            else:
                results.append({"error": str(he.detail)})
                continue
        results.append({"embedding": embedding, "chars": len(t), "elapsed_ms": elapsed_ms})
    total_ms = int((time.time() - overall_start) * 1000)
    logging.info(f"embed_batch count={len(texts)} model={used_model} total_ms={total_ms}")
    return {"model": used_model, "count": len(results), "total_ms": total_ms, "results": results}

@app.post("/llm/chat")
async def llm_chat(request: Request):
    data = await request.json()
    messages = data["messages"]
    model = data.get("model", DEFAULT_CHAT_MODEL)  # Use high quality model by default
    # Log incoming chat request
    logging.info(f"llm_chat request: model={model} messages={messages}")

    # If system prompt contains context, log it for debugging
    for msg in messages:
        if msg.get("role") == "system":
            logging.info(f"System prompt/context sent to Ollama: {msg.get('content')}")
    start_time = time.time()
    
    # Quick process check before making request
    try:
        high_cpu_count = 0
        for proc in psutil.process_iter(['name', 'cmdline', 'cpu_percent']):
            try:
                if 'ollama' in proc.info['name'] and 'runner' in ' '.join(proc.info['cmdline'] or []):
                    if proc.cpu_percent() > MAX_OLLAMA_RUNNER_CPU:
                        high_cpu_count += 1
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                continue
        
        if high_cpu_count > 0:
            logging.warning(f"Found {high_cpu_count} high-CPU ollama runners before chat request")
            cleanup_stuck_ollama_processes()
    except Exception:
        pass  # Don't fail the request if process check fails
    
    try:
        async with httpx.AsyncClient(timeout=60.0) as client:  # Reduced timeout since models are warmed
            resp = await client.post(f"{OLLAMA_URL}/api/chat", json={
                "model": model,
                "messages": messages,
                "stream": False
            })
            result = resp.json()
            elapsed_ms = int((time.time() - start_time) * 1000)
            logging.info(f"LLM chat completed model={model} elapsed_ms={elapsed_ms}")
            return {"message": result.get("message", {})}
            
    except Exception as e:
        # Try fallback model if primary fails
        if model != FALLBACK_CHAT_MODEL:
            try:
                logging.warning(f"Primary model {model} failed, trying fallback {FALLBACK_CHAT_MODEL}")
                async with httpx.AsyncClient(timeout=30.0) as client:
                    resp = await client.post(f"{OLLAMA_URL}/api/chat", json={
                        "model": FALLBACK_CHAT_MODEL,
                        "messages": messages,
                        "stream": False
                    })
                    result = resp.json()
                    elapsed_ms = int((time.time() - start_time) * 1000)
                    logging.info(f"LLM chat completed with fallback model={FALLBACK_CHAT_MODEL} elapsed_ms={elapsed_ms}")
                    return {"message": result.get("message", {})}
            except Exception as fallback_error:
                elapsed_ms = int((time.time() - start_time) * 1000)
                logging.error(f"Both models failed. Primary: {str(e)}, Fallback: {str(fallback_error)} elapsed_ms={elapsed_ms}")
                raise HTTPException(status_code=500, detail=f"Chat failed: {str(e)}")
        else:
            elapsed_ms = int((time.time() - start_time) * 1000)
            logging.error(f"LLM chat failed model={model} elapsed_ms={elapsed_ms} error={str(e)}")
            raise HTTPException(status_code=500, detail=str(e))

@app.on_event("startup")
async def warm_model():
    global MODEL_WARMED
    try:
        # Clean up any existing stuck processes before starting
        logging.info("Cleaning up any stuck ollama processes...")
        cleanup_stuck_ollama_processes()
        
        # Start background process monitoring task
        asyncio.create_task(periodic_process_cleanup())
        logging.info("Started background process monitoring")
        
        logging.info("Warming up models...")
        async with httpx.AsyncClient(timeout=60.0) as client:
            # Check available models
            await client.get(f"{OLLAMA_URL}/api/tags")
            
            # Warm up embedding model
            embed_resp = await client.post(
                f"{OLLAMA_URL}/api/embeddings",
                json={"model": DEFAULT_EMBED_MODEL, "prompt": "warmup"}
            )
            
            # Warm up chat model to keep it in memory
            chat_resp = await client.post(
                f"{OLLAMA_URL}/api/chat",
                json={
                    "model": DEFAULT_CHAT_MODEL,
                    "messages": [{"role": "user", "content": "warmup"}],
                    "stream": False
                }
            )

            # Optionally warm fallback embedding model (if different)
            fallback_embed_resp = None
            if FALLBACK_EMBED_MODEL != DEFAULT_EMBED_MODEL:
                try:
                    fallback_embed_resp = await client.post(
                        f"{OLLAMA_URL}/api/embeddings",
                        json={"model": FALLBACK_EMBED_MODEL, "prompt": "warmup"}
                    )
                    logging.info(f"Fallback embed model warmed: {FALLBACK_EMBED_MODEL} status={fallback_embed_resp.status_code}")
                except Exception as fe:
                    logging.warning(f"Fallback embed warm failed: {FALLBACK_EMBED_MODEL} error={fe}")

            # Optionally warm fallback chat model (if different)
            fallback_chat_resp = None
            if FALLBACK_CHAT_MODEL != DEFAULT_CHAT_MODEL:
                try:
                    fallback_chat_resp = await client.post(
                        f"{OLLAMA_URL}/api/chat",
                        json={
                            "model": FALLBACK_CHAT_MODEL,
                            "messages": [{"role": "user", "content": "warmup"}],
                            "stream": False
                        }
                    )
                    logging.info(f"Fallback chat model warmed: {FALLBACK_CHAT_MODEL} status={fallback_chat_resp.status_code}")
                except Exception as fc:
                    logging.warning(f"Fallback chat warm failed: {FALLBACK_CHAT_MODEL} error={fc}")
            
            if embed_resp.status_code == 200 and chat_resp.status_code == 200:
                MODEL_WARMED = True
                logging.info(
                    "Models warmed up successfully: default_embed=%s default_chat=%s fallback_embed=%s fallback_chat=%s",
                    DEFAULT_EMBED_MODEL,
                    DEFAULT_CHAT_MODEL,
                    FALLBACK_EMBED_MODEL if FALLBACK_EMBED_MODEL != DEFAULT_EMBED_MODEL else "(same)",
                    FALLBACK_CHAT_MODEL if FALLBACK_CHAT_MODEL != DEFAULT_CHAT_MODEL else "(same)"
                )
            else:
                logging.warning("Model warmup partially failed")
                
    except Exception as e:
        logging.error(f"Model warmup failed: {str(e)}")
        pass

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8111, reload=False)
