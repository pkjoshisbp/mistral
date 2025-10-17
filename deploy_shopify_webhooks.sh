#!/bin/bash

##############################################################################
# Shopify GDPR Webhooks Deployment Script
# 
# This script uses Shopify CLI to deploy GDPR compliance webhooks
# from your shopify.app.toml configuration file.
#
# Usage: ./deploy_shopify_webhooks.sh
##############################################################################

set -e  # Exit on any error

echo "========================================="
echo "Shopify GDPR Webhooks Deployment"
echo "========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Change to project root
cd "$(dirname "$0")"

echo "📁 Current directory: $(pwd)"
echo ""

# Check if shopify.app.toml exists
if [ ! -f "shopify.app.toml" ]; then
    echo -e "${RED}❌ Error: shopify.app.toml not found!${NC}"
    echo "Please ensure you're in the correct directory."
    exit 1
fi

echo -e "${GREEN}✓${NC} Found shopify.app.toml"
echo ""

# Show current webhook configuration
echo "📋 Current webhook configuration in shopify.app.toml:"
echo "─────────────────────────────────────────────────────"
grep -A 1 "topics = " shopify.app.toml | grep -v "^--$"
echo ""

# Check if Shopify CLI is installed
echo "🔍 Checking Shopify CLI availability..."
if command -v shopify &> /dev/null; then
    SHOPIFY_CMD="shopify"
    echo -e "${GREEN}✓${NC} Shopify CLI is installed globally"
else
    SHOPIFY_CMD="npx @shopify/cli"
    echo -e "${YELLOW}⚠${NC}  Shopify CLI not installed globally, using npx"
fi
echo ""

# Prompt for confirmation
echo "⚠️  This will deploy the following webhooks to your Shopify app:"
echo "   • app/uninstalled"
echo "   • customers/data_request (GDPR)"
echo "   • customers/redact (GDPR)"
echo "   • shop/redact (GDPR)"
echo ""
echo "   All pointing to: https://ai-chat.support/shopify/webhooks"
echo ""
read -p "Continue? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled."
    exit 0
fi

echo ""
echo "========================================="
echo "Step 1: Link to Shopify App"
echo "========================================="
echo ""

# Link to existing app using client_id from toml
CLIENT_ID=$(grep "client_id" shopify.app.toml | cut -d '"' -f 2)
echo "Using client_id: $CLIENT_ID"
echo ""

echo "This will open a browser window for authentication..."
echo "Please log in to your Shopify Partners account."
echo ""

# Try to link the app
if $SHOPIFY_CMD app config link --client-id="$CLIENT_ID" 2>&1 | tee /tmp/shopify_link.log; then
    echo -e "${GREEN}✓${NC} Successfully linked to Shopify app"
else
    echo -e "${RED}❌ Failed to link app${NC}"
    echo ""
    echo "Possible solutions:"
    echo "1. Run manually: $SHOPIFY_CMD app config link"
    echo "2. Check that you're logged into the correct Partners account"
    echo "3. Verify client_id in shopify.app.toml matches Partner Dashboard"
    exit 1
fi

echo ""
echo "========================================="
echo "Step 2: Deploy Webhook Configuration"
echo "========================================="
echo ""

# Push configuration to Shopify
echo "Deploying webhooks from shopify.app.toml..."
if $SHOPIFY_CMD app config push 2>&1 | tee /tmp/shopify_push.log; then
    echo ""
    echo -e "${GREEN}✓${NC} Webhooks deployed successfully!"
else
    echo ""
    echo -e "${RED}❌ Failed to deploy webhooks${NC}"
    echo ""
    echo "Check /tmp/shopify_push.log for details"
    exit 1
fi

echo ""
echo "========================================="
echo "Step 3: Verify Deployment"
echo "========================================="
echo ""

# List deployed webhooks
echo "Listing deployed webhooks..."
if $SHOPIFY_CMD app webhooks list 2>&1 | tee /tmp/shopify_webhooks.log; then
    echo ""
    echo -e "${GREEN}✓${NC} Webhook verification complete"
else
    echo -e "${YELLOW}⚠${NC}  Could not verify webhooks (may still be working)"
fi

echo ""
echo "========================================="
echo "✅ Deployment Complete!"
echo "========================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Go to Shopify Partner Dashboard:"
echo "   https://partners.shopify.com/"
echo ""
echo "2. Navigate to:"
echo "   Apps → AI Chat Support → Overview"
echo ""
echo "3. Click the 'Run checks' button"
echo ""
echo "4. Verify both checks pass:"
echo "   ✅ Provides mandatory compliance webhooks"
echo "   ✅ Verifies webhooks with HMAC signatures"
echo ""
echo "5. Monitor Laravel logs during testing:"
echo "   cd laravel"
echo "   tail -f storage/logs/laravel.log | grep -i shopify"
echo ""
echo "─────────────────────────────────────────────────────"
echo ""
echo "📝 Deployment logs saved to:"
echo "   /tmp/shopify_link.log"
echo "   /tmp/shopify_push.log"
echo "   /tmp/shopify_webhooks.log"
echo ""
echo "🎉 Your GDPR compliance webhooks are now configured!"
echo ""
