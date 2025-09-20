#!/bin/bash

# Ollama vs llama.cpp Performance Comparison
# Tests similar 3B models to compare backend performance

echo "🔬 Ollama vs llama.cpp Performance Comparison"
echo "=============================================="
echo ""

TEST_PROMPT="What is AI chat support?"
TOKENS_TO_GENERATE=50

echo "Test Configuration:"
echo "• Prompt: '$TEST_PROMPT'"
echo "• Tokens to generate: $TOKENS_TO_GENERATE"
echo "• CPU Threads: 4"
echo ""

# Test 1: Ollama llama3.2:3b
echo "📊 Test 1: Ollama llama3.2:3b"
echo "────────────────────────────"

ollama_start_time=$(date +%s.%N)

ollama_response=$(curl -s -X POST http://localhost:11434/api/generate \
    -H "Content-Type: application/json" \
    -d "{
        \"model\": \"llama3.2:3b\",
        \"prompt\": \"$TEST_PROMPT\",
        \"stream\": false,
        \"options\": {
            \"num_predict\": $TOKENS_TO_GENERATE
        }
    }")

ollama_end_time=$(date +%s.%N)
ollama_response_time=$(echo "$ollama_end_time - $ollama_start_time" | bc -l)

ollama_text=$(echo "$ollama_response" | jq -r '.response // "Error: No response"')
ollama_word_count=$(echo "$ollama_text" | wc -w)

echo "⏱️  Response Time: $(printf "%.2f" $ollama_response_time)s"
echo "📝 Word Count: $ollama_word_count words"
echo "📄 Response: ${ollama_text:0:200}..."
echo ""

# Test 2: llama.cpp Qwen2.5-3B
echo "📊 Test 2: llama.cpp Qwen2.5-3B (Q4_0)"
echo "──────────────────────────────────"

llamacpp_start_time=$(date +%s.%N)

# Run llama.cpp and capture output
llamacpp_output=$(cd /var/www/clients/client1/web64/web && timeout 30s ./llama-cli \
    --hf-repo Qwen/Qwen2.5-3B-Instruct-GGUF \
    --hf-file qwen2.5-3b-instruct-q4_0.gguf \
    -p "$TEST_PROMPT" \
    -n $TOKENS_TO_GENERATE \
    --no-conversation \
    --threads 4 \
    --temp 0.8 2>&1)

llamacpp_end_time=$(date +%s.%N)
llamacpp_response_time=$(echo "$llamacpp_end_time - $llamacpp_start_time" | bc -l)

# Extract just the response text (everything after the prompt)
llamacpp_text=$(echo "$llamacpp_output" | sed -n '/^What is AI chat support\?/,/llama_perf_context_print/p' | \
    sed '1d;$d' | grep -v "llama_perf" | tr '\n' ' ')

llamacpp_word_count=$(echo "$llamacpp_text" | wc -w)

echo "⏱️  Response Time: $(printf "%.2f" $llamacpp_response_time)s"
echo "📝 Word Count: $llamacpp_word_count words"
echo "📄 Response: ${llamacpp_text:0:200}..."

# Extract performance metrics from llama.cpp output
eval_time=$(echo "$llamacpp_output" | grep "eval time" | sed -n 's/.*eval time = *\([0-9.]*\) ms.*/\1/p')
prompt_eval_time=$(echo "$llamacpp_output" | grep "prompt eval time" | sed -n 's/.*prompt eval time = *\([0-9.]*\) ms.*/\1/p')
tokens_per_second=$(echo "$llamacpp_output" | grep "eval time" | sed -n 's/.* (\([0-9.]*\) tokens per second).*/\1/p')

if [ ! -z "$eval_time" ]; then
    echo "📈 Token Generation: $(printf "%.2f" $tokens_per_second) tokens/sec"
    echo "⚡ Inference Time: $(printf "%.0f" $eval_time)ms"
fi

echo ""

# Performance Comparison
echo "🏆 Performance Comparison"
echo "────────────────────────"
echo "Backend          | Response Time | Tokens/Word | Speed Rating"
echo "───────────────────────────────────────────────────────────"
printf "Ollama (3.2:3b)  | %8.2fs    | %5d words | " $ollama_response_time $ollama_word_count

if (( $(echo "$ollama_response_time < 15" | bc -l) )); then
    echo "⭐⭐⭐⭐⭐"
elif (( $(echo "$ollama_response_time < 20" | bc -l) )); then
    echo "⭐⭐⭐⭐"
else
    echo "⭐⭐⭐"
fi

printf "llama.cpp (Qwen) | %8.2fs    | %5d words | " $llamacpp_response_time $llamacpp_word_count

if (( $(echo "$llamacpp_response_time < 10" | bc -l) )); then
    echo "⭐⭐⭐⭐⭐"
elif (( $(echo "$llamacpp_response_time < 15" | bc -l) )); then
    echo "⭐⭐⭐⭐"
else
    echo "⭐⭐⭐"
fi

echo ""

# Performance Analysis
speed_improvement=$(echo "scale=1; ($ollama_response_time - $llamacpp_response_time) / $ollama_response_time * 100" | bc -l)

echo "📋 Analysis"
echo "───────────"
if (( $(echo "$llamacpp_response_time < $ollama_response_time" | bc -l) )); then
    echo "✅ llama.cpp is $(printf "%.1f" $speed_improvement)% faster than Ollama"
    echo "✅ Better raw inference performance with llama.cpp"
else 
    speed_diff=$(echo "scale=1; ($llamacpp_response_time - $ollama_response_time) / $llamacpp_response_time * 100" | bc -l)
    echo "⚠️  Ollama is $(printf "%.1f" $speed_diff)% faster (includes download time for llama.cpp)"
fi

echo ""
echo "💡 Recommendations:"
echo "• Use Ollama for: Easy setup, model management, development"
echo "• Use llama.cpp for: Maximum performance, production optimization"
echo "• Both are suitable for real-time chat applications"