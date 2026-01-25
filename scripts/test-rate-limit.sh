#!/bin/bash
# Test widget chat rate limiting (5 requests/minute per session)

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

ORG_SLUG="platform"
SESSION_ID="test_session_$(date +%s)"
URL="https://ai-chat.support/widget/${ORG_SLUG}/chat"

echo "=== Testing Widget Rate Limit ==="
echo "Session ID: $SESSION_ID"
echo "Rate limit: 5 requests/minute per session"
echo ""

# Send 7 requests rapidly
for i in {1..7}; do
    echo -n "Request $i: "
    
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$URL" \
        -H "Content-Type: application/json" \
        -d "{\"message\":\"Test $i\",\"session_id\":\"$SESSION_ID\"}")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✅ Success (200)${NC}"
    elif [ "$HTTP_CODE" = "429" ]; then
        echo -e "${RED}🚫 Rate limited (429)${NC}"
        echo "   Response: $BODY" | head -c 100
        echo ""
    else
        echo -e "${YELLOW}⚠️  Code: $HTTP_CODE${NC}"
    fi
    
    sleep 0.5
done

echo ""
echo "Expected: First 5 requests succeed, requests 6-7 get 429"
