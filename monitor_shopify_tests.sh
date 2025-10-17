#!/bin/bash

# Shopify Automated Test Monitor
# Run this BEFORE clicking "Run checks" in Shopify Partner Dashboard

echo "======================================================================"
echo " 🔍 MONITORING SHOPIFY AUTOMATED TESTS"
echo "======================================================================"
echo ""
echo "Ready to monitor webhook requests from Shopify's automated checker."
echo ""
echo "Next steps:"
echo "1. Keep this terminal open"
echo "2. Go to Shopify Partner Dashboard"
echo "3. Click 'Run automated checks' or 'Test app'"
echo "4. Watch this screen for detailed logs"
echo ""
echo "Press Ctrl+C to stop monitoring"
echo ""
echo "======================================================================"
echo ""

cd /var/www/clients/client1/web64/web/laravel

# Watch logs with color highlighting
tail -f storage/logs/laravel.log | grep --line-buffered -E "(SHOPIFY|webhook|HMAC|✅|❌|→)" | while read line; do
    if echo "$line" | grep -q "✅"; then
        echo -e "\033[0;32m$line\033[0m"  # Green for success
    elif echo "$line" | grep -q "❌"; then
        echo -e "\033[0;31m$line\033[0m"  # Red for errors
    elif echo "$line" | grep -q "→"; then
        echo -e "\033[0;33m$line\033[0m"  # Yellow for routing
    else
        echo "$line"
    fi
done
