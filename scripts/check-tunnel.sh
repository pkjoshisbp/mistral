#!/bin/bash
# Monitor autossh tunnel status

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=== Autossh Tunnel Status ==="
echo ""

VAST_PORT="${VAST_PORT:-51734}"

# Check if autossh is running
if pgrep -f "autossh.*${VAST_PORT}" > /dev/null; then
    PID=$(pgrep -f "autossh.*${VAST_PORT}")
    echo -e "${GREEN}✅ Autossh running (PID: $PID)${NC}"
    
    # Show process details
    echo ""
    echo "Process details:"
    ps aux | grep autossh | grep ${VAST_PORT} | grep -v grep
    
    # Test connection
    echo ""
    echo "Testing Ollama connection..."
    if curl -s --max-time 3 http://127.0.0.1:11435/api/tags > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Ollama responding on port 11435${NC}"
        curl -s http://127.0.0.1:11435/api/tags | python3 -c "import sys, json; d=json.load(sys.stdin); print('Models:', ', '.join([m['name'] for m in d.get('models', [])]))" 2>/dev/null
    else
        echo -e "${RED}❌ Ollama not responding${NC}"
    fi
    
    # Show recent log entries
    echo ""
    echo "Recent log entries (last 5 lines):"
    tail -5 /var/www/clients/client1/web64/web/logs/ollama-tunnel.log
else
    echo -e "${RED}❌ Autossh not running${NC}"
    echo ""
    echo "To start: bash /var/www/clients/client1/web64/web/scripts/start-ollama-tunnel.sh"
fi

echo ""
