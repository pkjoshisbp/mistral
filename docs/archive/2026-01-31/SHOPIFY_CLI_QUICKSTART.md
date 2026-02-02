# 🚀 Quick Start: Deploy Shopify GDPR Webhooks

## ✅ You're Ready!

Everything is configured. You just need to run **one command** to deploy GDPR webhooks.

## Prerequisites (Already Have)

- ✅ Node.js v18.17.0 installed
- ✅ npm v9.6.7 installed  
- ✅ npx available
- ✅ `shopify.app.toml` configured with all 4 webhooks
- ✅ Deployment script created: `deploy_shopify_webhooks.sh`

## Option 1: Automated Script (Recommended)

### Single Command Deployment:

```bash
cd /var/www/clients/client1/web64/web
./deploy_shopify_webhooks.sh
```

The script will:
1. ✅ Verify `shopify.app.toml` exists
2. ✅ Show you what will be deployed
3. ✅ Link to your Shopify app (will open browser for auth)
4. ✅ Deploy all 4 webhooks (including GDPR)
5. ✅ Verify deployment
6. ✅ Show you next steps

### What to Expect:

```
📋 Current webhook configuration:
   • app/uninstalled → https://ai-chat.support/shopify/webhooks
   • customers/data_request → https://ai-chat.support/shopify/webhooks
   • customers/redact → https://ai-chat.support/shopify/webhooks
   • shop/redact → https://ai-chat.support/shopify/webhooks

Continue? (y/N): y

Step 1: Link to Shopify App
[Browser opens for authentication]
✓ Successfully linked to Shopify app

Step 2: Deploy Webhook Configuration  
✓ Webhooks deployed successfully!

Step 3: Verify Deployment
✓ Webhook verification complete

✅ Deployment Complete!
```

## Option 2: Manual Commands

If you prefer to run commands manually:

```bash
cd /var/www/clients/client1/web64/web

# 1. Link your app (one-time)
npx @shopify/cli app config link --client-id=e209ea490d1c4a8981ba790ecaf75ad8

# 2. Deploy webhooks from shopify.app.toml
npx @shopify/cli app config push

# 3. Verify deployment
npx @shopify/cli app webhooks list
```

## After Deployment

### 1. Run Automated Checks in Partner Dashboard

1. Go to: https://partners.shopify.com/
2. Navigate to: **Apps → AI Chat Support → Overview**
3. Click: **"Run checks"** button
4. Wait ~2 minutes

### 2. Expected Results:

✅ **Provides mandatory compliance webhooks** - PASS  
✅ **Verifies webhooks with HMAC signatures** - PASS

### 3. Monitor Laravel Logs

Open a second terminal and watch for incoming test webhooks:

```bash
cd /var/www/clients/client1/web64/web/laravel
tail -f storage/logs/laravel.log | grep -i shopify
```

You should see:
```
=== SHOPIFY WEBHOOK REQUEST RECEIVED ===
topic: "customers/data_request"
✅ HMAC VERIFIED SUCCESSFULLY
→ Routing to handleCustomersDataRequest
✅ customers/data_request webhook processed - Returning HTTP 200
```

## Troubleshooting

### Browser doesn't open for authentication?

Copy the URL from terminal and paste in your browser manually.

### "App not found" error?

Run with explicit client ID:
```bash
npx @shopify/cli app config link --client-id=e209ea490d1c4a8981ba790ecaf75ad8
```

### Need to see what's in shopify.app.toml?

```bash
cat /var/www/clients/client1/web64/web/shopify.app.toml
```

### Want to test a webhook manually?

```bash
npx @shopify/cli app webhooks trigger \
  --topic=customers/data_request \
  --address=https://ai-chat.support/shopify/webhooks
```

## Why This Works (vs. Manual Dashboard)

### Partner Dashboard UI Issue:
- GDPR webhook fields may not be visible in older UI
- Location varies (Configuration vs. App setup vs. Extensions)
- Inconsistent across different app types

### Shopify CLI Advantages:
- ✅ Always works (uses Partner API)
- ✅ Version controlled (`shopify.app.toml`)
- ✅ No manual clicking
- ✅ Repeatable and scriptable
- ✅ Sees all webhook types (including GDPR)

## Timeline

- **Authentication**: 1 minute (browser login)
- **Deployment**: 30 seconds
- **Verification**: 30 seconds
- **Run automated checks**: 2 minutes
- **Total**: ~4 minutes ⚡

## Files Created

- ✅ `shopify.app.toml` - Webhook configuration (already existed)
- ✅ `deploy_shopify_webhooks.sh` - Automated deployment script
- ✅ `SHOPIFY_CLI_DEPLOYMENT.md` - Full CLI documentation
- ✅ `SHOPIFY_CLI_QUICKSTART.md` - This quick start guide

## Ready? Run This Now:

```bash
cd /var/www/clients/client1/web64/web
./deploy_shopify_webhooks.sh
```

Then go to Partner Dashboard and click "Run checks"! 🎉

---

**Questions?** Check the full documentation:
- `SHOPIFY_CLI_DEPLOYMENT.md` - Detailed CLI usage
- `SHOPIFY_SOLUTION.md` - Problem analysis and solution
- `SHOPIFY_GDPR_WEBHOOKS.md` - Technical deep dive
