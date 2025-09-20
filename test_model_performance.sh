#!/bin/bash

# AI Model Performance Test Script
# Tests response time and quality for different Ollama models

echo "🤖 AI Model Performance Comparison"
echo "=================================="
echo ""

# Test prompt for consistency
TEST_PROMPT="Explain what AI chat support is in one paragraph."

# Models to test
MODELS=("llama3.2:1b" "llama3.2:3b" "mistral:7b")

for model in "${MODELS[@]}"; do
    echo "Testing $model..."
    echo "─────────────────"
    
    # Measure response time
    start_time=$(date +%s.%N)
    
    # Make request to Ollama
    response=$(curl -s -X POST http://localhost:11434/api/generate \
        -H "Content-Type: application/json" \
        -d "{
            \"model\": \"$model\",
            \"prompt\": \"$TEST_PROMPT\",
            \"stream\": false
        }")
    
    end_time=$(date +%s.%N)
    
    # Calculate response time
    response_time=$(echo "$end_time - $start_time" | bc -l)
    
    # Extract response text
    response_text=$(echo "$response" | jq -r '.response // "Error: No response"')
    
    # Count words in response
    word_count=$(echo "$response_text" | wc -w)
    
    echo "⏱️  Response Time: ${response_time}s"
    echo "📝 Word Count: $word_count words"
    echo "📄 Response: ${response_text:0:200}..."
    echo ""
done

echo "✅ Performance test completed!"
echo ""
echo "💡 Recommendations:"
echo "• llama3.2:1b - Fastest for simple queries"
echo "• llama3.2:3b - Balanced speed/quality (recommended)" 
echo "• mistral:7b - Best quality for complex analysis"