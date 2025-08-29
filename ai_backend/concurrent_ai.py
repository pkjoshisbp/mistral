import asyncio
import httpx
import time
from typing import List, Dict, Any
import logging

logger = logging.getLogger(__name__)

class ConcurrentAIService:
    """Optimized concurrent AI service for faster responses"""
    
    def __init__(self, fastapi_url: str = "http://127.0.0.1:8111"):
        self.fastapi_url = fastapi_url
        self.session_pool = {}  # Keep persistent connections
    
    async def get_client(self) -> httpx.AsyncClient:
        """Reuse HTTP client for connection pooling"""
        if 'client' not in self.session_pool:
            self.session_pool['client'] = httpx.AsyncClient(
                timeout=30.0,
                limits=httpx.Limits(max_connections=10, max_keepalive_connections=5)
            )
        return self.session_pool['client']
    
    async def concurrent_nlu_and_embed(self, query: str, org_context: str = "") -> Dict[str, Any]:
        """Process NLU and initial embedding concurrently (instead of sequentially)"""
        start_time = time.time()
        client = await self.get_client()
        
        # Simplified NLU prompt (much shorter)
        simple_nlu_messages = [
            {"role": "system", "content": "Extract intent in 1-2 words: contact|pricing|booking|test_info|general. Be concise."},
            {"role": "user", "content": query[:100]}  # Truncate for speed
        ]
        
        # Concurrent tasks
        tasks = []
        
        # Task 1: Simple NLU (fast)
        nlu_task = client.post(f"{self.fastapi_url}/llm/chat", json={
            "messages": simple_nlu_messages,
            "options": {"num_predict": 10, "temperature": 0.1}  # Very short response
        })
        tasks.append(("nlu", nlu_task))
        
        # Task 2: Rewrite query (concurrent)
        rewrite_task = client.post(f"{self.fastapi_url}/rewrite", json={
            "text": query
        })
        tasks.append(("rewrite", rewrite_task))
        
        # Task 3: Direct embedding of original query (fallback)
        embed_task = client.post(f"{self.fastapi_url}/embed", json={
            "text": query
        })
        tasks.append(("embed_original", embed_task))
        
        # Execute all concurrently
        results = {}
        try:
            responses = await asyncio.gather(*[task[1] for task in tasks], return_exceptions=True)
            
            for i, (task_name, _) in enumerate(tasks):
                if isinstance(responses[i], Exception):
                    logger.warning(f"Task {task_name} failed: {responses[i]}")
                    results[task_name] = None
                else:
                    results[task_name] = responses[i].json()
                    
        except Exception as e:
            logger.error(f"Concurrent processing failed: {e}")
            return {"error": str(e)}
        
        elapsed_ms = int((time.time() - start_time) * 1000)
        logger.info(f"Concurrent NLU+Rewrite+Embed completed in {elapsed_ms}ms")
        
        return {
            "nlu": results.get("nlu"),
            "rewrite": results.get("rewrite"),
            "embed_original": results.get("embed_original"),
            "elapsed_ms": elapsed_ms
        }
    
    async def fast_search_and_generate(self, query: str, embedding: List[float], org_collection: str) -> Dict[str, Any]:
        """Fast search + optimized generation"""
        start_time = time.time()
        client = await self.get_client()
        
        # Task 1: Qdrant search
        search_task = client.post(f"{self.fastapi_url}/qdrant/search", json={
            "collection_name": org_collection,
            "query_vector": embedding,
            "limit": 3  # Reduced from 5 for speed
        })
        
        search_result = await search_task
        search_data = search_result.json()
        
        # Build minimal context (not huge text blocks)
        context_parts = []
        for result in search_data.get("results", [])[:2]:  # Max 2 results
            payload = result.get("payload", {})
            context_parts.append(f"{payload.get('name', '')}: {payload.get('description', '')[:200]}")  # Truncate
        
        context = " | ".join(context_parts)
        
        # Optimized generation prompt (much shorter)
        optimized_messages = [
            {"role": "system", "content": f"Answer briefly for Gupta Diagnostics using: {context}"},
            {"role": "user", "content": query}
        ]
        
        # Fast generation
        generate_task = client.post(f"{self.fastapi_url}/llm/chat", json={
            "messages": optimized_messages,
            "options": {"num_predict": 100, "temperature": 0.3}  # Shorter, focused response
        })
        
        generate_result = await generate_task
        generation_data = generate_result.json()
        
        elapsed_ms = int((time.time() - start_time) * 1000)
        logger.info(f"Search+Generate completed in {elapsed_ms}ms")
        
        return {
            "search_results": search_data,
            "generation": generation_data,
            "context_used": context,
            "elapsed_ms": elapsed_ms
        }
    
    async def optimized_pipeline(self, query: str, org_collection: str = "org_1_data") -> Dict[str, Any]:
        """Complete optimized pipeline: concurrent processing + fast generation"""
        pipeline_start = time.time()
        
        # Phase 1: Concurrent NLU, Rewrite, Embed (instead of sequential)
        phase1_result = await self.concurrent_nlu_and_embed(query)
        
        if "error" in phase1_result:
            return phase1_result
        
        # Use rewritten query if available, fallback to original
        rewrite_data = phase1_result.get("rewrite")
        if rewrite_data and "rewrite" in rewrite_data:
            processed_query = rewrite_data["rewrite"]
            
            # Get embedding for rewritten query
            client = await self.get_client()
            embed_task = client.post(f"{self.fastapi_url}/embed", json={
                "text": processed_query
            })
            embed_result = await embed_task
            embed_data = embed_result.json()
        else:
            # Fallback to original
            processed_query = query
            embed_data = phase1_result.get("embed_original")
        
        if not embed_data or "embedding" not in embed_data:
            return {"error": "Embedding failed"}
        
        # Phase 2: Fast search + generation
        phase2_result = await self.fast_search_and_generate(
            processed_query, 
            embed_data["embedding"], 
            org_collection
        )
        
        total_elapsed = int((time.time() - pipeline_start) * 1000)
        
        return {
            "query_original": query,
            "query_processed": processed_query,
            "answer": phase2_result.get("generation", {}).get("message", {}).get("content", ""),
            "performance": {
                "phase1_ms": phase1_result.get("elapsed_ms", 0),
                "phase2_ms": phase2_result.get("elapsed_ms", 0),
                "total_ms": total_elapsed
            },
            "debug": {
                "nlu": phase1_result.get("nlu"),
                "context_used": phase2_result.get("context_used"),
                "search_results_count": len(phase2_result.get("search_results", {}).get("results", []))
            }
        }
    
    async def cleanup(self):
        """Clean up connections"""
        if 'client' in self.session_pool:
            await self.session_pool['client'].aclose()
