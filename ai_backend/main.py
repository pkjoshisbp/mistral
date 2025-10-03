from fastapi import FastAPI, Request, HTTPException, WebSocket, WebSocketDisconnect
import httpx
import logging
from qdrant_client import QdrantClient
from qdrant_client.models import Distance, VectorParams, PointStruct, Filter, FieldCondition, MatchValue
import uuid
import time
import asyncio
import os
import subprocess
import psutil
import signal
import json
import tempfile
import shutil
from pathlib import Path
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

# Backend type configuration
AI_BACKEND_TYPE = os.getenv("AI_BACKEND_TYPE", "ollama")  # ollama or llamacpp
LLAMACPP_BINARY = os.getenv("LLAMACPP_BINARY", "/var/www/clients/client1/web64/web/llama.cpp/build/bin/llama-cli")
LLAMACPP_SERVER_BINARY = os.getenv("LLAMACPP_SERVER_BINARY", "/var/www/clients/client1/web64/web/llama.cpp/build/bin/llama-server")
LLAMACPP_SERVER_PORT = int(os.getenv("LLAMACPP_SERVER_PORT", "8112"))
LLAMACPP_SERVER_URL = f"http://localhost:{LLAMACPP_SERVER_PORT}"
MODELS_DIR = os.getenv("MODELS_DIR", "/var/www/clients/client1/web64/web/models")

# Pre-configured GGUF models
GGUF_MODELS = {
    "bartowski/Llama-3.2-3B-Instruct-GGUF:Llama-3.2-3B-Instruct-Q4_K_M.gguf": {
        "url": "https://huggingface.co/bartowski/Llama-3.2-3B-Instruct-GGUF/resolve/main/Llama-3.2-3B-Instruct-Q4_K_M.gguf",
        "filename": "Llama-3.2-3B-Instruct-Q4_K_M.gguf"
    },
    "bartowski/Llama-3.2-1B-Instruct-GGUF:Llama-3.2-1B-Instruct-Q4_K_M.gguf": {
        "url": "https://huggingface.co/bartowski/Llama-3.2-1B-Instruct-GGUF/resolve/main/Llama-3.2-1B-Instruct-Q4_K_M.gguf",
        "filename": "Llama-3.2-1B-Instruct-Q4_K_M.gguf"
    },
    "bartowski/Llama-3.2-3B-Instruct-GGUF:Llama-3.2-3B-Instruct-Q8_0.gguf": {
        "url": "https://huggingface.co/bartowski/Llama-3.2-3B-Instruct-GGUF/resolve/main/Llama-3.2-3B-Instruct-Q8_0.gguf",
        "filename": "Llama-3.2-3B-Instruct-Q8_0.gguf"
    },
    "custom/Llama-3.2-3B-Instruct-Q8_0-Custom": {
        "url": "",  # Local custom model - no download needed
        "filename": "Llama-3.2-3B-Instruct-Q8_0-custom.gguf"
    }
}

# Ensure models directory exists
Path(MODELS_DIR).mkdir(exist_ok=True)

# Global variable to track llama-server process
llamacpp_server_process = None
current_llamacpp_model = None

# Process management config
MAX_OLLAMA_RUNNER_CPU = float(os.getenv("MAX_OLLAMA_RUNNER_CPU", "200.0"))  # Max CPU % for runner processes
MAX_OLLAMA_RUNNER_TIME = int(os.getenv("MAX_OLLAMA_RUNNER_TIME", "300"))    # Max runtime in seconds (5 min)
PROCESS_CHECK_INTERVAL = int(os.getenv("PROCESS_CHECK_INTERVAL", "30"))     # Check every 30 seconds

embed_semaphore = asyncio.Semaphore(EMBED_CONCURRENCY)

app = FastAPI()
qdrant = QdrantClient(host="127.0.0.1", port=6333)
OLLAMA_URL = os.getenv("OLLAMA_URL", "http://localhost:11434")

@app.on_event("shutdown")
async def shutdown_event():
    """Clean up resources when FastAPI shuts down"""
    await stop_llamacpp_server()

logging.basicConfig(level=logging.INFO, format="[%(asctime)s] %(levelname)s %(message)s")

# Helper functions for llama.cpp server management
async def start_llamacpp_server(model_path: str) -> bool:
    """Start llama-server with the specified model"""
    global llamacpp_server_process, current_llamacpp_model
    
    # If server is already running with the same model, return True
    if llamacpp_server_process and current_llamacpp_model == model_path:
        try:
            # Check if server is still responding
            async with httpx.AsyncClient(timeout=5) as client:
                response = await client.get(f"{LLAMACPP_SERVER_URL}/health")
                if response.status_code == 200:
                    logging.info(f"llama-server already running with model: {Path(model_path).name}")
                    return True
        except:
            pass  # Server not responding, will restart
    
    # Stop existing server if running
    await stop_llamacpp_server()
    
    try:
        # Set up environment with LD_LIBRARY_PATH for shared libraries
        env = os.environ.copy()
        lib_dir = Path(LLAMACPP_SERVER_BINARY).parent  # build/bin directory
        env["LD_LIBRARY_PATH"] = f"{lib_dir}:{env.get('LD_LIBRARY_PATH', '')}"
        
        # Start llama-server
        cmd = [
            LLAMACPP_SERVER_BINARY,
            "-m", model_path,
            "--port", str(LLAMACPP_SERVER_PORT),
            "--host", "127.0.0.1",
            "--ctx-size", "4096",
            "--n-predict", "-1",  # unlimited tokens
            "--threads", "4",
            "--no-warmup"
        ]
        
        logging.info(f"Starting llama-server on port {LLAMACPP_SERVER_PORT} with model: {Path(model_path).name}")
        
        llamacpp_server_process = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
            env=env
        )
        
        # Wait for server to start (check health endpoint)
        max_attempts = 30
        for attempt in range(max_attempts):
            try:
                await asyncio.sleep(1)
                async with httpx.AsyncClient(timeout=5) as client:
                    response = await client.get(f"{LLAMACPP_SERVER_URL}/health")
                    if response.status_code == 200:
                        current_llamacpp_model = model_path
                        logging.info(f"llama-server started successfully on port {LLAMACPP_SERVER_PORT}")
                        return True
            except:
                if attempt == max_attempts - 1:
                    logging.error(f"llama-server failed to start after {max_attempts} attempts")
                    await stop_llamacpp_server()
                    return False
                continue
        
        return False
        
    except Exception as e:
        logging.error(f"Failed to start llama-server: {str(e)}")
        await stop_llamacpp_server()
        return False

async def stop_llamacpp_server():
    """Stop the running llama-server"""
    global llamacpp_server_process, current_llamacpp_model
    
    if llamacpp_server_process:
        try:
            llamacpp_server_process.terminate()
            await asyncio.wait_for(llamacpp_server_process.wait(), timeout=10)
        except asyncio.TimeoutError:
            llamacpp_server_process.kill()
            await llamacpp_server_process.wait()
        except Exception as e:
            logging.error(f"Error stopping llama-server: {str(e)}")
        
        llamacpp_server_process = None
        current_llamacpp_model = None
        logging.info("llama-server stopped")

async def llamacpp_server_chat(messages: list) -> dict:
    """Send chat request to llama-server"""
    try:
        async with httpx.AsyncClient(timeout=60) as client:
            response = await client.post(f"{LLAMACPP_SERVER_URL}/v1/chat/completions", json={
                "model": "llama-model",  # llama-server ignores this, uses loaded model
                "messages": messages,
                "stream": False,
                "temperature": 0.7,
                "top_p": 0.9,
                "max_tokens": 512
            })
            
            if response.status_code != 200:
                raise HTTPException(status_code=500, detail=f"llama-server error: {response.text}")
            
            result = response.json()
            
            # Convert OpenAI-compatible response to our format
            if "choices" in result and len(result["choices"]) > 0:
                choice = result["choices"][0]
                message_content = choice.get("message", {}).get("content", "")
                
                usage = result.get("usage") or {}
                # If llama-server does not report usage, estimate tokens (prompt+completion)
                if not usage or not all(k in usage for k in ("prompt_tokens", "completion_tokens", "total_tokens")):
                    # Approximate: 1 token ~ 4 chars
                    input_text = " ".join([m.get("content", "") for m in messages])
                    output_text = message_content or ""
                    prompt_tokens = max(1, len(input_text) // 4)
                    completion_tokens = max(1, len(output_text) // 4)
                    total_tokens = prompt_tokens + completion_tokens
                    usage = {
                        "prompt_tokens": prompt_tokens,
                        "completion_tokens": completion_tokens,
                        "total_tokens": total_tokens
                    }
                return {
                    "message": {"content": message_content, "role": "assistant"},
                    "usage": usage
                }
            else:
                raise HTTPException(status_code=500, detail="Invalid response from llama-server")
                
    except Exception as e:
        logging.error(f"llama-server chat error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"llama-server chat failed: {str(e)}")

# Helper functions for llama.cpp support
async def download_gguf_model(model_repo_path: str) -> str:
    """Download GGUF model from Hugging Face if not already present"""
    if model_repo_path not in GGUF_MODELS:
        raise ValueError(f"Unknown model repository: {model_repo_path}")
    
    model_info = GGUF_MODELS[model_repo_path]
    local_path = Path(MODELS_DIR) / model_info["filename"]
    
    if local_path.exists():
        logging.info(f"GGUF model already exists: {local_path}")
        return str(local_path)
    
    logging.info(f"Downloading GGUF model: {model_repo_path}")
    
    try:
        async with httpx.AsyncClient(timeout=300) as client:  # 5 minute timeout for large downloads
            response = await client.get(model_info["url"], follow_redirects=True)
            response.raise_for_status()
            
            # Write to temporary file first, then move to final location
            with tempfile.NamedTemporaryFile(delete=False) as tmp_file:
                tmp_file.write(response.content)
                tmp_path = tmp_file.name
            
            shutil.move(tmp_path, local_path)
            logging.info(f"Downloaded GGUF model to: {local_path}")
            return str(local_path)
            
    except Exception as e:
        logging.error(f"Failed to download GGUF model {model_repo_path}: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to download model: {str(e)}")

async def run_llamacpp_chat(model_path: str, messages: list) -> dict:
    """Run llama.cpp chat inference"""
    if not Path(LLAMACPP_BINARY).exists():
        raise HTTPException(status_code=500, detail="llama.cpp binary not found")
    
    # Convert messages to prompt format
    prompt = ""
    for msg in messages:
        role = msg.get("role", "user")
        content = msg.get("content", "")
        if role == "system":
            prompt += f"System: {content}\n\n"
        elif role == "user":
            prompt += f"Human: {content}\n\n"
        elif role == "assistant":
            prompt += f"Assistant: {content}\n\n"
    
    prompt += "Assistant: "
    
    try:
        # Run llama.cpp with the model
        cmd = [
            LLAMACPP_BINARY,
            "-m", model_path,
            "-p", prompt,
            "--temp", "0.7",
            "--top-p", "0.9",
            "--repeat-penalty", "1.1",
            "-n", "512",  # max new tokens
            "--simple-io"
        ]
        
        logging.info(f"Running llama.cpp: {' '.join(cmd[:4])}...")  # Log first few args
        
        # Set up environment with LD_LIBRARY_PATH for shared libraries
        env = os.environ.copy()
        lib_dir = Path(LLAMACPP_BINARY).parent  # Same directory as the binary
        env["LD_LIBRARY_PATH"] = f"{lib_dir}:{env.get('LD_LIBRARY_PATH', '')}"
        
        process = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
            env=env
        )
        
        stdout, stderr = await process.communicate()
        
        if process.returncode != 0:
            error_msg = stderr.decode().strip()
            logging.error(f"llama.cpp error: {error_msg}")
            raise HTTPException(status_code=500, detail=f"llama.cpp inference failed: {error_msg}")
        
        output = stdout.decode().strip()
        
        # Extract just the generated response (after "Assistant: ")
        if "Assistant: " in output:
            response_text = output.split("Assistant: ")[-1].strip()
        else:
            response_text = output.strip()
        
        # Estimate usage tokens in cli mode as well
        input_text = " ".join([m.get("content", "") for m in messages])
        output_text = response_text or ""
        prompt_tokens = max(1, len(input_text) // 4)
        completion_tokens = max(1, len(output_text) // 4)
        total_tokens = prompt_tokens + completion_tokens

        return {
            "message": {"content": response_text, "role": "assistant"},
            "usage": {
                "prompt_tokens": prompt_tokens,
                "completion_tokens": completion_tokens,
                "total_tokens": total_tokens
            }
        }
        
    except Exception as e:
        logging.error(f"llama.cpp execution error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"llama.cpp execution failed: {str(e)}")

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

@app.get("/qdrant/collections")
async def list_collections():
    """List all collections in Qdrant"""
    try:
        collections = qdrant.get_collections()
        collection_list = []
        
        for collection in collections.collections:
            try:
                # Get collection info including point count
                info = qdrant.get_collection(collection.name)
                collection_list.append({
                    "name": collection.name,
                    "points_count": info.points_count,
                    "status": info.status,
                    "vector_size": info.config.params.vectors.size if hasattr(info.config.params, 'vectors') else None
                })
            except Exception as e:
                collection_list.append({
                    "name": collection.name,
                    "error": str(e)
                })
        
        return {
            "status": "success",
            "collections": collection_list,
            "total_collections": len(collection_list)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to list collections: {str(e)}")

@app.get("/qdrant/collections/{collection_name}")
async def get_collection(collection_name: str):
    """Get specific collection info"""
    try:
        info = qdrant.get_collection(collection_name)
        return {
            "status": "success",
            "name": collection_name,
            "points_count": info.points_count,
            "collection_status": info.status,
            "vector_size": info.config.params.vectors.size if hasattr(info.config.params, 'vectors') else None,
            "distance": info.config.params.vectors.distance if hasattr(info.config.params, 'vectors') else None
        }
    except Exception as e:
        raise HTTPException(status_code=404, detail=f"Collection not found: {str(e)}")

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
    backend_type = data.get("backend_type", AI_BACKEND_TYPE)  # Allow override from request
    
    # Log incoming chat request
    logging.info(f"llm_chat request: backend={backend_type} model={model} messages={len(messages)} msgs")

    # If system prompt contains context, log it for debugging
    for msg in messages:
        if msg.get("role") == "system":
            logging.info(f"System prompt/context: {msg.get('content')[:100]}...")
    start_time = time.time()
    
    # Handle llama.cpp backend
    if backend_type == "llamacpp":
        try:
            # Check if model is a GGUF repository path
            if model in GGUF_MODELS:
                model_path = await download_gguf_model(model)
            else:
                # Assume it's a file path
                model_path = model
                if not Path(model_path).exists():
                    raise HTTPException(status_code=404, detail=f"Model file not found: {model_path}")
            
            # Start llama-server with the model (or use existing if same model)
            server_started = await start_llamacpp_server(model_path)
            if not server_started:
                raise HTTPException(status_code=500, detail="Failed to start llama-server")
            
            # Send chat request to llama-server
            result = await llamacpp_server_chat(messages)
            elapsed_ms = int((time.time() - start_time) * 1000)
            logging.info(f"llama-server chat completed model={Path(model_path).name} elapsed_ms={elapsed_ms}")
            # Ensure usage keys present
            usage = result.get("usage") or {}
            if not usage:
                # As a fallback, estimate here too
                input_text = " ".join([m.get("content", "") for m in messages])
                output_text = result.get("message", {}).get("content", "")
                prompt_tokens = max(1, len(input_text) // 4)
                completion_tokens = max(1, len(output_text) // 4)
                total_tokens = prompt_tokens + completion_tokens
                result["usage"] = {
                    "prompt_tokens": prompt_tokens,
                    "completion_tokens": completion_tokens,
                    "total_tokens": total_tokens
                }
            return result
            
        except Exception as e:
            logging.error(f"llama.cpp chat error: {str(e)}")
            raise HTTPException(status_code=500, detail=str(e))
    
    # Handle Ollama backend (default/fallback)
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
            
            # Estimate token usage (simple approximation: ~4 chars per token)
            input_text = " ".join([msg.get("content", "") for msg in messages])
            output_text = result.get("message", {}).get("content", "")
            input_tokens = len(input_text) // 4
            output_tokens = len(output_text) // 4
            total_tokens = input_tokens + output_tokens
            
            return {
                "message": result.get("message", {}),
                "usage": {
                    "prompt_tokens": input_tokens,
                    "completion_tokens": output_tokens,
                    "total_tokens": total_tokens
                }
            }
            
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
                    
                    # Estimate token usage for fallback too
                    input_text = " ".join([msg.get("content", "") for msg in messages])
                    output_text = result.get("message", {}).get("content", "")
                    input_tokens = len(input_text) // 4
                    output_tokens = len(output_text) // 4
                    total_tokens = input_tokens + output_tokens
                    
                    return {
                        "message": result.get("message", {}),
                        "usage": {
                            "prompt_tokens": input_tokens,
                            "completion_tokens": output_tokens,
                            "total_tokens": total_tokens
                        }
                    }
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

@app.post("/store_data")
async def store_data(request: Request):
    """
    Unified endpoint to store any type of organization data to Qdrant
    Expected payload:
    {
        "organization_slug": "ai-chat-support",
        "data_type": "faq|info|service|document",
        "items": [
            {
                "id": "unique_id",
                "title": "title",
                "content": "main content",
                "category": "category",
                "metadata": {...}
            }
        ]
    }
    """
    try:
        data = await request.json()
        organization_slug = data["organization_slug"]
        data_type = data["data_type"]
        items = data["items"]
        
        logging.info(f"Store data request: org={organization_slug}, type={data_type}, count={len(items)}")
        
        # Create collection if it doesn't exist
        collection_name = organization_slug
        try:
            qdrant.create_collection(
                collection_name=collection_name,
                vectors_config=VectorParams(size=768, distance=Distance.COSINE)
            )
        except Exception as e:
            if "already exists" not in str(e):
                logging.warning(f"Collection creation issue: {str(e)}")
        
        successful_stores = 0
        failed_stores = []
        
        for item in items:
            try:
                # Prepare text for embedding
                text_parts = []
                if item.get('title'):
                    text_parts.append(f"Title: {item['title']}")
                if item.get('content'):
                    text_parts.append(f"Content: {item['content']}")
                if item.get('category'):
                    text_parts.append(f"Category: {item['category']}")
                
                full_text = " ".join(text_parts)
                
                if not full_text.strip():
                    failed_stores.append({"item_id": item.get('id'), "error": "No text content to embed"})
                    continue
                
                # Generate embedding
                start_time = time.time()
                embedding, elapsed_ms = await _generate_embedding(DEFAULT_EMBED_MODEL, full_text, start_time)
                
                # Prepare payload
                payload = {
                    "data_type": data_type,
                    "item_id": item.get('id'),
                    "title": item.get('title', ''),
                    "content": item.get('content', ''),
                    "category": item.get('category', ''),
                    "organization_slug": organization_slug
                }
                
                # Add any additional metadata
                if item.get('metadata'):
                    payload.update(item['metadata'])
                
                # Create consistent point ID based on data type and item ID
                # This ensures updates replace existing entries instead of creating duplicates
                # Use hash to convert string identifier to integer for Qdrant compatibility
                item_identifier = item.get('id', f"{data_type}_{successful_stores}")
                point_id_string = f"{organization_slug}_{data_type}_{item_identifier}"
                point_id = hash(point_id_string) & 0x7FFFFFFF  # Convert to positive 32-bit integer
                
                # First, try to delete existing points with the same item_id to avoid duplicates
                try:
                    existing_points = qdrant.scroll(
                        collection_name=collection_name,
                        scroll_filter=Filter(
                            must=[
                                FieldCondition(key="organization_slug", match=MatchValue(value=organization_slug)),
                                FieldCondition(key="data_type", match=MatchValue(value=data_type)),
                                FieldCondition(key="item_id", match=MatchValue(value=item.get('id')))
                            ]
                        ),
                        limit=100  # Should be enough for duplicates of same item
                    )
                    
                    if existing_points[0]:  # If any existing points found
                        existing_ids = [point.id for point in existing_points[0]]
                        if existing_ids:
                            qdrant.delete(
                                collection_name=collection_name,
                                points_selector=existing_ids
                            )
                            logging.info(f"Deleted {len(existing_ids)} existing points for item {item.get('id')}")
                except Exception as delete_error:
                    logging.warning(f"Could not delete existing points for {item.get('id')}: {str(delete_error)}")
                
                # Store in Qdrant with consistent point ID
                qdrant.upsert(
                    collection_name=collection_name,
                    points=[PointStruct(
                        id=point_id,
                        vector=embedding,
                        payload=payload
                    )]
                )
                
                successful_stores += 1
                logging.info(f"Stored item {item.get('id')} to {collection_name}")
                
            except Exception as e:
                failed_stores.append({
                    "item_id": item.get('id'), 
                    "error": str(e)
                })
                logging.error(f"Failed to store item {item.get('id')}: {str(e)}")
        
        response = {
            "success": True,
            "organization_slug": organization_slug,
            "data_type": data_type,
            "total_items": len(items),
            "successful_stores": successful_stores,
            "failed_stores": len(failed_stores),
            "failures": failed_stores
        }
        
        logging.info(f"Store data complete: {successful_stores}/{len(items)} successful")
        return response
        
    except Exception as e:
        logging.error(f"Store data error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Store data failed: {str(e)}")

@app.post("/update_data")
async def update_data(request: Request):
    """
    Update existing data in Qdrant (same as store_data but with explicit update logging)
    This is essentially an alias to store_data with clearer semantics for updates
    """
    try:
        data = await request.json()
        organization_slug = data["organization_slug"]
        data_type = data["data_type"]
        items = data["items"]
        
        logging.info(f"Update data request: org={organization_slug}, type={data_type}, count={len(items)}")
        
        # Use the same logic as store_data since it now handles updates properly
        return await store_data(request)
        
    except Exception as e:
        logging.error(f"Update data error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Update data failed: {str(e)}")

@app.post("/delete_data")
async def delete_data(request: Request):
    """
    Delete specific data points from Qdrant collection
    Expected payload:
    {
        "organization_slug": "ai-chat-support", 
        "item_ids": ["faq_123", "info_456"]
    }
    """
    try:
        data = await request.json()
        organization_slug = data["organization_slug"]
        item_ids = data["item_ids"]
        
        logging.info(f"Delete data request: org={organization_slug}, items={len(item_ids)}")
        
        collection_name = organization_slug
        deleted_count = 0
        failed_deletes = []
        
        # Get all points to find the ones to delete by payload matching
        scroll_result = qdrant.scroll(
            collection_name=collection_name,
            scroll_filter=None,
            limit=1000,  # Adjust if you have more items
            with_payload=True,
            with_vectors=False
        )
        
        points_to_delete = []
        for point in scroll_result[0]:  # scroll returns (points, next_page_offset)
            payload = point.payload
            if payload.get('item_id') in item_ids:
                points_to_delete.append(point.id)
        
        # Delete the points
        if points_to_delete:
            qdrant.delete(
                collection_name=collection_name,
                points_selector=points_to_delete
            )
            deleted_count = len(points_to_delete)
            logging.info(f"Deleted {deleted_count} items from {collection_name}")
        
        # Check which items weren't found
        found_items = set()
        for point in scroll_result[0]:
            payload = point.payload
            if payload.get('item_id') in item_ids and point.id in points_to_delete:
                found_items.add(payload.get('item_id'))
        
        failed_deletes = [item_id for item_id in item_ids if item_id not in found_items]
        
        response = {
            "success": True,
            "organization_slug": organization_slug,
            "total_requested": len(item_ids),
            "deleted_count": deleted_count,
            "failed_deletes": failed_deletes
        }
        
        return response
        
    except Exception as e:
        logging.error(f"Delete data error: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Delete data failed: {str(e)}")

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8111, reload=False)
