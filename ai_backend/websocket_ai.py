import asyncio
import json
import time
import logging
from typing import Dict, Any, Optional
from fastapi import WebSocket, WebSocketDisconnect
import httpx

logger = logging.getLogger(__name__)

class WebSocketAIService:
    """High-performance WebSocket AI service with streaming and connection pooling"""
    
    def __init__(self, fastapi_url: str = "http://127.0.0.1:8111"):
        self.fastapi_url = fastapi_url
        self.active_connections: Dict[str, WebSocket] = {}
        self.connection_stats: Dict[str, Dict] = {}
        self.http_client: Optional[httpx.AsyncClient] = None
    
    async def get_http_client(self) -> httpx.AsyncClient:
        """Persistent HTTP client with connection pooling"""
        if self.http_client is None:
            self.http_client = httpx.AsyncClient(
                timeout=30.0,
                limits=httpx.Limits(
                    max_connections=20,
                    max_keepalive_connections=10,
                    keepalive_expiry=30
                )
            )
        return self.http_client
    
    async def connect(self, websocket: WebSocket, client_id: str):
        """Accept WebSocket connection and track it"""
        await websocket.accept()
        self.active_connections[client_id] = websocket
        self.connection_stats[client_id] = {
            "connected_at": time.time(),
            "messages_sent": 0,
            "total_response_time": 0,
            "average_response_time": 0
        }
        logger.info(f"WebSocket client {client_id} connected")
    
    async def disconnect(self, client_id: str):
        """Remove WebSocket connection"""
        if client_id in self.active_connections:
            del self.active_connections[client_id]
        if client_id in self.connection_stats:
            stats = self.connection_stats[client_id]
            logger.info(f"Client {client_id} disconnected. Stats: {stats}")
            del self.connection_stats[client_id]
    
    async def send_status(self, client_id: str, status: str, data: Dict = None):
        """Send status update to client"""
        if client_id in self.active_connections:
            message = {"type": "status", "status": status, "data": data or {}}
            await self.active_connections[client_id].send_text(json.dumps(message))
    
    async def send_partial_response(self, client_id: str, partial_content: str, is_final: bool = False):
        """Send streaming response chunk"""
        if client_id in self.active_connections:
            message = {
                "type": "response_chunk",
                "content": partial_content,
                "is_final": is_final,
                "timestamp": time.time()
            }
            await self.active_connections[client_id].send_text(json.dumps(message))
    
    async def concurrent_rewrite_and_embed(self, query: str, client_id: str) -> Dict[str, Any]:
        """Concurrent rewrite + embed with WebSocket status updates"""
        await self.send_status(client_id, "processing", {"step": "rewrite_and_embed"})
        
        client = await self.get_http_client()
        start_time = time.time()
        
        # Concurrent tasks
        tasks = [
            client.post(f"{self.fastapi_url}/rewrite", json={"text": query}),
            client.post(f"{self.fastapi_url}/embed", json={"text": query})
        ]
        
        try:
            rewrite_response, embed_response = await asyncio.gather(*tasks)
            
            rewrite_data = rewrite_response.json()
            embed_data = embed_response.json()
            
            # Use rewritten query for better search
            processed_query = rewrite_data.get("rewrite", query)
            
            # Re-embed the rewritten query if different
            if processed_query != query:
                embed_response = await client.post(f"{self.fastapi_url}/embed", json={"text": processed_query})
                embed_data = embed_response.json()
            
            elapsed_ms = int((time.time() - start_time) * 1000)
            
            return {
                "query_original": query,
                "query_processed": processed_query,
                "embedding": embed_data.get("embedding"),
                "elapsed_ms": elapsed_ms
            }
            
        except Exception as e:
            await self.send_status(client_id, "error", {"step": "rewrite_and_embed", "error": str(e)})
            raise
    
    async def fast_search_with_updates(self, embedding: list, collection: str, client_id: str) -> Dict[str, Any]:
        """Fast Qdrant search with status updates"""
        await self.send_status(client_id, "processing", {"step": "search"})
        
        client = await self.get_http_client()
        start_time = time.time()
        
        try:
            response = await client.post(f"{self.fastapi_url}/qdrant/search", json={
                "collection_name": collection,
                "query_vector": embedding,
                "limit": 3
            })
            
            search_data = response.json()
            elapsed_ms = int((time.time() - start_time) * 1000)
            
            await self.send_status(client_id, "processing", {
                "step": "search_complete", 
                "results_found": len(search_data.get("results", [])),
                "elapsed_ms": elapsed_ms
            })
            
            return search_data
            
        except Exception as e:
            await self.send_status(client_id, "error", {"step": "search", "error": str(e)})
            raise
    
    async def streaming_generation(self, query: str, context: str, client_id: str) -> str:
        """Generate response with streaming chunks via WebSocket"""
        await self.send_status(client_id, "processing", {"step": "generating"})
        
        client = await self.get_http_client()
        
        # Optimized generation prompt
        messages = [
            {"role": "system", "content": f"Answer briefly for Gupta Diagnostics using: {context[:500]}"},
            {"role": "user", "content": query}
        ]
        
        try:
            # For now, use non-streaming API but simulate streaming by sending chunks
            response = await client.post(f"{self.fastapi_url}/llm/chat", json={
                "messages": messages,
                "options": {"num_predict": 150, "temperature": 0.3}
            })
            
            result = response.json()
            full_response = result.get("message", {}).get("content", "")
            
            # Simulate streaming by sending response in chunks
            words = full_response.split()
            chunk_size = 3  # 3 words per chunk
            
            accumulated = ""
            for i in range(0, len(words), chunk_size):
                chunk_words = words[i:i + chunk_size]
                chunk = " ".join(chunk_words) + " "
                accumulated += chunk
                
                # Send partial response
                await self.send_partial_response(client_id, chunk, is_final=False)
                await asyncio.sleep(0.1)  # Small delay for streaming effect
            
            # Send final complete response
            await self.send_partial_response(client_id, "", is_final=True)
            
            return full_response
            
        except Exception as e:
            await self.send_status(client_id, "error", {"step": "generation", "error": str(e)})
            raise
    
    async def process_query_pipeline(self, query: str, collection: str, client_id: str) -> Dict[str, Any]:
        """Complete optimized pipeline with WebSocket streaming"""
        pipeline_start = time.time()
        
        try:
            # Phase 1: Concurrent rewrite + embed
            phase1_result = await self.concurrent_rewrite_and_embed(query, client_id)
            
            if not phase1_result.get("embedding"):
                raise Exception("Embedding failed")
            
            # Phase 2: Search
            search_results = await self.fast_search_with_updates(
                phase1_result["embedding"], 
                collection, 
                client_id
            )
            
            # Build context
            context_parts = []
            for result in search_results.get("results", [])[:2]:
                payload = result.get("payload", {})
                context_parts.append(f"{payload.get('name', '')}: {payload.get('description', '')[:200]}")
            
            context = " | ".join(context_parts)
            
            # Phase 3: Streaming generation
            final_response = await self.streaming_generation(
                phase1_result["query_processed"],
                context,
                client_id
            )
            
            total_elapsed = int((time.time() - pipeline_start) * 1000)
            
            # Update connection stats
            if client_id in self.connection_stats:
                stats = self.connection_stats[client_id]
                stats["messages_sent"] += 1
                stats["total_response_time"] += total_elapsed
                stats["average_response_time"] = stats["total_response_time"] / stats["messages_sent"]
            
            return {
                "query_original": query,
                "query_processed": phase1_result["query_processed"],
                "answer": final_response,
                "context_used": context,
                "performance": {
                    "total_ms": total_elapsed,
                    "rewrite_embed_ms": phase1_result["elapsed_ms"]
                },
                "search_results_count": len(search_results.get("results", []))
            }
            
        except Exception as e:
            await self.send_status(client_id, "error", {"error": str(e)})
            raise
    
    async def cleanup(self):
        """Cleanup resources"""
        if self.http_client:
            await self.http_client.aclose()

# Global instance
websocket_ai_service = WebSocketAIService()
