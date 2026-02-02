# Shopify CLI Deployment - GDPR Webhooks Configuration

## ✅ Good News!

You already have a `shopify.app.toml` file configured with all GDPR webhooks! This is the **modern recommended approach** for managing Shopify apps.

## Current `shopify.app.toml` Configuration

Your file already includes all mandatory webhooks:

```toml
[webhooks]
api_version = "2025-01"

[[webhooks.subscriptions]]
topics = ["app/uninstalled"]
uri = "https://ai-chat.support/shopify/webhooks"

[[webhooks.subscriptions]]
topics = ["customers/data_request"]
uri = "https://ai-chat.support/shopify/webhooks"

[[webhooks.subscriptions]]
topics = ["customers/redact"]
uri = "https://ai-chat.support/shopify/webhooks"

[[webhooks.subscriptions]]
topics = ["shop/redact"]
uri = "https://ai-chat.support/shopify/webhooks"
```

## Solution: Deploy Using Shopify CLI

### Step 1: Install Shopify CLI (If Not Already Installed)

```bash
cd /var/www/clients/client1/web64/web

# Install Shopify CLI globally
npm install -g @shopify/cli@latest

# Or use npx (no installation needed)
npx @shopify/cli@latest --version
```

### Step 2: Authenticate with Shopify Partners

```bash
cd /var/www/clients/client1/web64/web

# Login to your Shopify Partners account
npx @shopify/cli app config link
```

This will:
1. Open a browser to authenticate
2. Link your local `shopify.app.toml` to your Partner Dashboard app
3. Use your existing `client_id` to identify the app

### Step 3: Deploy Webhook Configuration

```bash
# Push webhook configuration to Shopify
npx @shopify/cli app config push
```

This command will:
- ✅ Read your `shopify.app.toml` file
- ✅ Register all 4 GDPR webhooks with Shopify
- ✅ Update Partner Dashboard automatically
- ✅ Configure compliance webhooks correctly

### Step 4: Verify Deployment

```bash
# Check webhook status
npx @shopify/cli app webhooks list
```

You should see:
```
Topic                    | Address
-------------------------|----------------------------------------
app/uninstalled          | https://ai-chat.support/shopify/webhooks
customers/data_request   | https://ai-chat.support/shopify/webhooks
customers/redact         | https://ai-chat.support/shopify/webhooks
shop/redact              | https://ai-chat.support/shopify/webhooks
```

### Step 5: Run Automated Checks

After deployment:
1. Go to Partner Dashboard → Apps → AI Chat Support → Overview
2. Click **"Run checks"**
3. Both compliance checks should now **PASS** ✅

## Why CLI Works vs. Admin API

### Admin API (What We Tried):
- ❌ GDPR webhooks return 404
- ✅ Only `app/uninstalled` works
- **Reason**: Security restriction

### Shopify CLI (`app config push`):
- ✅ All webhooks work (including GDPR)
- ✅ Uses Partner API (not Admin API)
- ✅ Automatically updates Partner Dashboard
- **Reason**: Authenticated as app developer, not merchant

## Alternative: Manual Partner Dashboard Configuration

If you don't see GDPR webhook fields in Partner Dashboard, it might be because:

1. **You're looking in the wrong section**
   - Go to: Apps → AI Chat Support → **Configuration** (not "App setup")
   - OR: Apps → AI Chat Support → **Extensions** → **Privacy webhooks**

2. **Your app is not in "Distribution" mode**
   - Go to: Apps → AI Chat Support → **Distribution**
   - Ensure distribution is set to **"Public"** or **"Custom"**
   - GDPR fields only appear for apps intended for public distribution

3. **UI has changed** (Shopify updates frequently)
   - Use CLI method instead (more reliable)

## Recommended: Use Shopify CLI

Since you already have `shopify.app.toml`, using CLI is the **best solution**:

### Advantages:
- ✅ Version-controlled configuration
- ✅ Automatic webhook deployment
- ✅ Works for all webhook types (including GDPR)
- ✅ CI/CD friendly
- ✅ No manual Partner Dashboard clicking
- ✅ Consistent across environments

### Commands Reference:

```bash
# Link to existing app
npx @shopify/cli app config link

# Push all configuration (webhooks, scopes, etc.)
npx @shopify/cli app config push

# List current webhooks
npx @shopify/cli app webhooks list

# Trigger a test webhook
npx @shopify/cli app webhooks trigger --topic=customers/data_request

# View app info
npx @shopify/cli app info
```

## Full Deployment Workflow

```bash
# 1. Navigate to project root
cd /var/www/clients/client1/web64/web

# 2. Install Shopify CLI (one-time)
npm install -g @shopify/cli

# 3. Authenticate and link to your app
shopify app config link
# (Will prompt for login and app selection)

# 4. Deploy webhooks from shopify.app.toml
shopify app config push

# 5. Verify webhooks are registered
shopify app webhooks list

# 6. Test a webhook (optional)
shopify app webhooks trigger \
  --topic=customers/data_request \
  --address=https://ai-chat.support/shopify/webhooks

# 7. Monitor Laravel logs
cd laravel
tail -f storage/logs/laravel.log | grep -i shopify
```

## If CLI Installation Fails (Restricted Server)

If you can't install npm packages on this server:

### Option A: Use Local Machine
```bash
# On your local machine:
git clone <your-repo>
cd <repo>
npm install -g @shopify/cli
shopify app config link
shopify app config push
```

### Option B: Use Shopify Partners Dashboard

Look for these sections (UI varies):
- **Apps → AI Chat Support → Extensions → Privacy compliance**
- **Apps → AI Chat Support → Configuration → GDPR webhooks**
- **Apps → AI Chat Support → App setup → Compliance webhooks**

Enter `https://ai-chat.support/shopify/webhooks` in all GDPR webhook fields.

## Monitoring Webhook Deployment

### During `app config push`:

You'll see output like:
```
✓ Pushing configuration to Shopify...
✓ Updated webhooks subscriptions
  • app/uninstalled → https://ai-chat.support/shopify/webhooks
  • customers/data_request → https://ai-chat.support/shopify/webhooks
  • customers/redact → https://ai-chat.support/shopify/webhooks
  • shop/redact → https://ai-chat.support/shopify/webhooks
✓ Configuration pushed successfully!
```

### In Laravel Logs:

After running automated checks, you'll see:
```
=== SHOPIFY WEBHOOK REQUEST RECEIVED ===
topic: "customers/data_request"
✅ HMAC VERIFIED SUCCESSFULLY
→ Routing to handleCustomersDataRequest
✅ customers/data_request webhook processed - Returning HTTP 200
```

## Troubleshooting

### "App not found" when running `config link`

**Solution**: Manually specify client_id:
```bash
shopify app config link --client-id=e209ea490d1c4a8981ba790ecaf75ad8
```

### "Permission denied" errors

**Solution**: Run with sudo or install in user directory:
```bash
npm install -g @shopify/cli --prefix ~/.local
export PATH="$HOME/.local/bin:$PATH"
```

### Webhooks still not appearing in automated checks

**Solution**: Wait 5-10 minutes after deployment, then re-run checks. Shopify caches configuration.

### Want to verify manually

**Solution**: Check Partner Dashboard after CLI push:
```
Apps → AI Chat Support → Overview
Should show: ✅ Provides mandatory compliance webhooks
```

## Current vs. New Approach

### Before (What We Tried):
```php
// IntegrationController.php - registerShopifyWebhooks()
Http::post("/admin/api/2025-01/webhooks.json", [...])
// ❌ Returns 404 for GDPR topics
```

### Now (Using CLI):
```toml
# shopify.app.toml
[[webhooks.subscriptions]]
topics = ["customers/data_request"]
uri = "https://ai-chat.support/shopify/webhooks"
```

```bash
shopify app config push
# ✅ Works for all topics, including GDPR
```

## Next Steps

1. **Install Shopify CLI**:
   ```bash
   npm install -g @shopify/cli
   ```

2. **Link your app**:
   ```bash
   cd /var/www/clients/client1/web64/web
   shopify app config link
   ```

3. **Deploy webhooks**:
   ```bash
   shopify app config push
   ```

4. **Run automated checks**:
   - Partner Dashboard → Apps → AI Chat Support → Overview → "Run checks"

5. **Verify in logs**:
   ```bash
   cd laravel
   tail -f storage/logs/laravel.log | grep -i shopify
   ```

## Expected Timeline

- CLI installation: 2 minutes
- Authentication: 1 minute
- Config push: 30 seconds
- Webhook propagation: 5 minutes
- Run automated checks: 2 minutes
- **Total: ~10 minutes** ✅

---

## Bottom Line

You already have `shopify.app.toml` configured correctly! Just run:

```bash
npm install -g @shopify/cli
cd /var/www/clients/client1/web64/web
shopify app config link
shopify app config push
```

This will deploy all GDPR webhooks and fix the automated checks! 🚀
