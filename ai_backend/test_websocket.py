#!/usr/bin/env python3
"""
WebSocket AI Client Test
Tests WebSocket vs HTTP performance
"""

import asyncio
import websockets
import json
import time
import aiohttp

class AIClientBenchmark:
    def __init__(self):
        self.ws_url = "ws://127.0.0.1:8111/ws/ai"
        self.http_url = "http://127.0.0.1:8111/ai/optimized"
    
    async def test_websocket(self, query: str):
        """Test WebSocket performance"""
        print(f"🔗 Testing WebSocket: {query}")
        start_time = time.time()
        
        try:
            async with websockets.connect(self.ws_url) as websocket:
                # Send query
                message = {"query": query, "collection": "org_1_data"}
                await websocket.send(json.dumps(message))
                
                response_chunks = []
                final_result = None
                
                # Receive streaming responses
                while True:
                    response = await websocket.recv()
                    data = json.loads(response)
                    
                    if data["type"] == "status":
                        print(f"  📊 Status: {data['status']} - {data.get('data', {})}")
                    
                    elif data["type"] == "response_chunk":
                        if not data["is_final"]:
                            print(f"  📝 Chunk: {data['content']}", end="", flush=True)
                            response_chunks.append(data["content"])
                        else:
                            print()  # New line after streaming
                    
                    elif data["type"] == "final_result":
                        final_result = data["data"]
                        break
                    
                    elif data["type"] == "error":
                        print(f"  ❌ Error: {data['error']}")
                        break
                
                total_time = time.time() - start_time
                
                return {
                    "method": "WebSocket",
                    "total_time": total_time,
                    "result": final_result,
                    "streaming_chunks": len(response_chunks)
                }
                
        except Exception as e:
            print(f"  ❌ WebSocket error: {e}")
            return {"method": "WebSocket", "error": str(e)}
    
    async def test_http(self, query: str):
        """Test HTTP performance"""
        print(f"🌐 Testing HTTP: {query}")
        start_time = time.time()
        
        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(self.http_url, json={
                    "query": query,
                    "collection": "org_1_data"
                }) as response:
                    if response.status == 200:
                        result = await response.json()
                        total_time = time.time() - start_time
                        
                        return {
                            "method": "HTTP",
                            "total_time": total_time,
                            "result": result
                        }
                    else:
                        error_text = await response.text()
                        return {"method": "HTTP", "error": f"HTTP {response.status}: {error_text}"}
                        
        except Exception as e:
            print(f"  ❌ HTTP error: {e}")
            return {"method": "HTTP", "error": str(e)}
    
    async def benchmark_comparison(self, queries: list):
        """Run benchmark comparison"""
        print("🚀 AI Client Performance Benchmark\n")
        
        results = []
        
        for i, query in enumerate(queries, 1):
            print(f"📋 Test {i}/{len(queries)}: {query[:50]}...")
            
            # Test both methods
            ws_result = await self.test_websocket(query)
            await asyncio.sleep(1)  # Brief pause
            http_result = await self.test_http(query)
            
            # Compare results
            if "error" not in ws_result and "error" not in http_result:
                speedup = http_result["total_time"] / ws_result["total_time"]
                print(f"  ⚡ WebSocket: {ws_result['total_time']:.2f}s")
                print(f"  🐌 HTTP: {http_result['total_time']:.2f}s")
                print(f"  📈 Speedup: {speedup:.1f}x faster")
                
                results.append({
                    "query": query,
                    "websocket_time": ws_result["total_time"],
                    "http_time": http_result["total_time"],
                    "speedup": speedup
                })
            
            print("-" * 60)
        
        # Summary
        if results:
            avg_speedup = sum(r["speedup"] for r in results) / len(results)
            print(f"📊 Average speedup: {avg_speedup:.1f}x faster with WebSocket")
            
            total_ws_time = sum(r["websocket_time"] for r in results)
            total_http_time = sum(r["http_time"] for r in results)
            print(f"📊 Total WebSocket time: {total_ws_time:.1f}s")
            print(f"📊 Total HTTP time: {total_http_time:.1f}s")

async def main():
    benchmark = AIClientBenchmark()
    
    test_queries = [
        "What is the address of Gupta Diagnostics?",
        "What is the phone number?", 
        "When can I get a blood sugar test?",
        "Show me thyroid test pricing"
    ]
    
    await benchmark.benchmark_comparison(test_queries)

if __name__ == "__main__":
    asyncio.run(main())
