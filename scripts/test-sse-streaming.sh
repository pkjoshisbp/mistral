#!/bin/bash
# Test SSE streaming endpoint

echo "Testing SSE Streaming Endpoint..."
echo "=================================="
echo ""

curl -N -X POST "http://localhost:8111/llm/chat/stream" \
  -H "Content-Type: application/json" \
  -d '{
    "messages": [
      {"role": "system", "content": "You are a helpful assistant."},
      {"role": "user", "content": "Count from 1 to 5"}
    ],
    "model": "llama3:8b-instruct-q5_K_M",
    "backend_type": "ollama"
  }'

echo ""
echo ""
echo "If you see text appearing gradually, streaming is working!"
