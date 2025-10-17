#!/bin/bash

# Shopify Webhook Testing Script
# This script tests your Shopify webhooks with proper HMAC signatures

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=================================================="
echo "Shopify Webhook Testing Tool"
echo "=================================================="
echo ""

# Configuration
WEBHOOK_URL="https://ai-chat.support/shopify/webhooks"
SHOPIFY_SECRET="${SHOPIFY_SECRET:-your_shopify_api_secret_here}"

# Check if secret is configured
if [ "$SHOPIFY_SECRET" = "your_shopify_api_secret_here" ]; then
    echo -e "${YELLOW}WARNING: Using placeholder secret. Set SHOPIFY_SECRET environment variable for real testing.${NC}"
    echo "Example: SHOPIFY_SECRET='your_real_secret' ./test_shopify_webhooks.sh"
    echo ""
fi

# Function to test webhook with HMAC
test_webhook() {
    local TOPIC=$1
    local PAYLOAD=$2
    local SHOP="test-store.myshopify.com"
    
    echo -e "${YELLOW}Testing: $TOPIC${NC}"
    echo "Payload: $PAYLOAD"
    
    # Generate HMAC signature
    HMAC=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SHOPIFY_SECRET" -binary | base64)
    
    # Send request
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$WEBHOOK_URL" \
        -H "Content-Type: application/json" \
        -H "X-Shopify-Topic: $TOPIC" \
        -H "X-Shopify-Shop-Domain: $SHOP" \
        -H "X-Shopify-Hmac-Sha256: $HMAC" \
        -d "$PAYLOAD")
    
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | head -n-1)
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo -e "${GREEN}✓ PASSED${NC} - HTTP $HTTP_CODE"
        echo "Response: $BODY"
    else
        echo -e "${RED}✗ FAILED${NC} - HTTP $HTTP_CODE"
        echo "Response: $BODY"
    fi
    echo ""
}

# Test 1: App Uninstalled
echo "Test 1: app/uninstalled"
echo "========================"
PAYLOAD='{"id":123456,"name":"Test Store","email":"owner@example.com","domain":"test-store.myshopify.com"}'
test_webhook "app/uninstalled" "$PAYLOAD"

# Test 2: Customer Data Request (GDPR)
echo "Test 2: customers/data_request"
echo "==============================="
PAYLOAD='{"shop_id":123456,"shop_domain":"test-store.myshopify.com","customer":{"id":789,"email":"customer@example.com"},"orders_requested":[123,456]}'
test_webhook "customers/data_request" "$PAYLOAD"

# Test 3: Customer Redact (GDPR)
echo "Test 3: customers/redact"
echo "========================"
PAYLOAD='{"shop_id":123456,"shop_domain":"test-store.myshopify.com","customer":{"id":789,"email":"customer@example.com"},"orders_to_redact":[123,456]}'
test_webhook "customers/redact" "$PAYLOAD"

# Test 4: Shop Redact (GDPR)
echo "Test 4: shop/redact"
echo "==================="
PAYLOAD='{"shop_id":123456,"shop_domain":"test-store.myshopify.com"}'
test_webhook "shop/redact" "$PAYLOAD"

# Test 5: Invalid HMAC (should fail)
echo "Test 5: Invalid HMAC (should return 401)"
echo "========================================="
PAYLOAD='{"test":true}'
INVALID_HMAC="invalid_hmac_signature"

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$WEBHOOK_URL" \
    -H "Content-Type: application/json" \
    -H "X-Shopify-Topic: app/uninstalled" \
    -H "X-Shopify-Shop-Domain: test-store.myshopify.com" \
    -H "X-Shopify-Hmac-Sha256: $INVALID_HMAC" \
    -d "$PAYLOAD")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "401" ]; then
    echo -e "${GREEN}✓ PASSED${NC} - Correctly rejected invalid HMAC (HTTP $HTTP_CODE)"
else
    echo -e "${RED}✗ FAILED${NC} - Should return 401 for invalid HMAC (got HTTP $HTTP_CODE)"
fi
echo "Response: $BODY"
echo ""

# Summary
echo "=================================================="
echo "Testing Complete!"
echo "=================================================="
echo ""
echo "Next Steps:"
echo "1. Check Laravel logs: tail -f laravel/storage/logs/laravel.log"
echo "2. Configure these webhooks in Shopify Partner Dashboard"
echo "3. Use the URL: $WEBHOOK_URL"
echo ""
echo "All 4 mandatory topics:"
echo "  - app/uninstalled"
echo "  - customers/data_request"
echo "  - customers/redact"
echo "  - shop/redact"
echo ""
