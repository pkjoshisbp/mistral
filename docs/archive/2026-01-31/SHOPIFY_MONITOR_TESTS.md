# 🔍 MONITORING SHOPIFY AUTOMATED TESTS

## ✅ Debug Logging Added!

We've added comprehensive debug logging to help you see exactly what Shopify's automated checker is doing.

---

## 📊 What Shopify Tests

### Shopify's Automated Checker Will:

1. **Check webhook registration** (looks for webhooks in the shop, not Partner Dashboard)
2. **Send test payloads** to each webhook endpoint
3. **Verify HMAC signatures** are validated correctly
4. **Check response codes** (must be HTTP 200)
5. **Measure response time** (must be < 5 seconds)

### Endpoints Shopify Tests:

**Primary Webhook Endpoint:**
```
POST https://ai-chat.support/shopify/webhooks
```

This ONE endpoint handles ALL 4 webhook topics:
- `app/uninstalled`
- `customers/data_request`
- `customers/redact`
- `shop/redact`

Shopify identifies which webhook by the `X-Shopify-Topic` header.

---

## 🔍 How to Monitor the Test

### Step 1: Open Log Monitoring

**In one terminal**, run this to watch logs in real-time:

```bash
cd /var/www/clients/client1/web64/web/laravel
tail -f storage/logs/laravel.log | grep -E "(SHOPIFY|webhook|HMAC|✅|❌)"
```

### Step 2: Run Shopify's Automated Checks

1. Go to: https://partners.shopify.com/
2. Navigate to: **Apps** → **Your App**
3. Look for: **"Run checks"** or **"Test app"** button
4. Click it!

### Step 3: Watch the Logs

You'll see detailed output like:

```
[INFO] === SHOPIFY WEBHOOK REQUEST RECEIVED ===
[INFO] X-Shopify-Topic: app/uninstalled
[INFO] X-Shopify-Shop-Domain: test-shop.myshopify.com
[INFO] X-Shopify-Hmac-Sha256: present
[INFO] ✅ HMAC VERIFIED SUCCESSFULLY
[INFO] ✅ SHOPIFY WEBHOOK HMAC VERIFIED - Processing...
[INFO] → Routing to handleAppUninstalled
[INFO] ✅ app/uninstalled webhook processed successfully - Returning HTTP 200
```

---

## ✅ What SUCCESS Looks Like

### Successful Test Run:

```bash
# For each of the 4 webhooks, you'll see:

=== SHOPIFY WEBHOOK REQUEST RECEIVED ===
  topic: app/uninstalled
  hmac: present
  
✅ HMAC VERIFIED SUCCESSFULLY

✅ SHOPIFY WEBHOOK HMAC VERIFIED - Processing...

→ Routing to handleAppUninstalled

✅ app/uninstalled webhook processed successfully - Returning HTTP 200
```

### All 4 Topics Should Show:
```
✅ app/uninstalled webhook processed successfully - Returning HTTP 200
✅ customers/data_request webhook processed - Returning HTTP 200
✅ customers/redact webhook processed - Returning HTTP 200
✅ shop/redact webhook processed - Returning HTTP 200
```

---

## ❌ What FAILURE Looks Like

### Issue 1: HMAC Verification Failed

```
❌ SHOPIFY WEBHOOK HMAC VERIFICATION FAILED
  hmac_header: MISSING
  
OR

❌ HMAC MISMATCH - Signature verification failed
  expected_hmac: abc123...
  received_hmac: xyz789...
  help: Check that SHOPIFY_SECRET matches...
```

**Fix:**
```bash
# Check .env has correct secret
cd /var/www/clients/client1/web64/web/laravel
grep SHOPIFY_SECRET .env

# Should match Partner Dashboard → Your App → API credentials → Client secret
```

### Issue 2: No Webhooks Found

If Shopify checker says "no webhooks configured":

```bash
# This means webhooks weren't registered during install
# Solution: Reinstall app to trigger registration

# 1. Uninstall from dev store
# 2. Reinstall using OAuth URL
# 3. Check logs for webhook registration
```

### Issue 3: Endpoint Not Accessible

```
# No logs appear when you run the test
```

**Check:**
```bash
# Test endpoint manually
curl -X POST https://ai-chat.support/shopify/webhooks \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: app/uninstalled" \
  -d '{"test": true}'

# Should return: HTTP 401 (because HMAC is missing - that's correct!)
# Should NOT return: HTTP 404 (would mean route not found)
```

---

## 📋 Step-by-Step Testing Process

### Before Running Automated Checks:

1. **Ensure app is installed on a dev store:**
   ```bash
   cd /var/www/clients/client1/web64/web/laravel
   php artisan tinker
   
   # In tinker:
   \App\Models\Integration::where('provider', 'shopify')->count()
   # Should be > 0
   ```

2. **Verify webhooks were registered:**
   ```bash
   # Check logs for webhook registration
   grep "Shopify webhook registered successfully" storage/logs/laravel.log
   
   # Should see 4 lines (one for each webhook)
   ```

3. **Start log monitoring:**
   ```bash
   tail -f storage/logs/laravel.log | grep -E "(SHOPIFY|webhook)"
   ```

### Running the Tests:

4. **Go to Partner Dashboard**
   - https://partners.shopify.com/
   - Apps → Your App

5. **Find the test button** (might be labeled):
   - "Run automated checks"
   - "Test app"
   - "Run compliance checks"
   - Or in the app submission flow

6. **Click "Run checks"**

7. **Watch logs** - You should see 4 webhook requests come in

8. **Check results** in Partner Dashboard:
   ```
   ✅ Provides mandatory compliance webhooks
   ✅ Verifies webhooks with HMAC signatures
   ```

---

## 🐛 Troubleshooting Guide

### Problem: "No logs appearing"

**Possible causes:**
1. Webhooks not registered in Shopify
2. Endpoint not publicly accessible
3. Log watching wrong file

**Solutions:**
```bash
# 1. Check if webhooks registered
cd /var/www/clients/client1/web64/web/laravel
php artisan tinker

$integration = \App\Models\Integration::where('provider', 'shopify')->first();
if ($integration) {
    echo "Shop: " . $integration->shop . "\n";
    echo "Token: " . substr($integration->access_token, 0, 10) . "...\n";
}

# 2. Test endpoint accessibility
curl -I https://ai-chat.support/shopify/webhooks

# 3. Check correct log file
ls -lah storage/logs/

# 4. Check log permissions
ls -l storage/logs/laravel.log
```

### Problem: "HMAC verification failing"

**Check secret matches:**
```bash
# Get secret from .env
cd /var/www/clients/client1/web64/web/laravel
grep SHOPIFY_SECRET .env

# Compare with Partner Dashboard:
# Apps → Your App → API credentials → Client secret
# These MUST match exactly!
```

### Problem: "Webhooks not found by Shopify"

**Solution: Reinstall app**

```bash
# 1. Build install URL
TEST_SHOP="your-dev-store.myshopify.com"
INSTALL_URL="https://${TEST_SHOP}/admin/oauth/authorize?client_id=e209ea490d1c4a8981ba790ecaf75ad8&scope=write_script_tags&redirect_uri=https://ai-chat.support/api/integrations/shopify/oauth/callback"

echo "$INSTALL_URL"

# 2. Visit URL in browser
# 3. Click "Install"
# 4. Check logs for webhook registration:
tail -f storage/logs/laravel.log | grep "webhook registered"
```

---

## 📊 Log Analysis

### Good Log Sequence:

```
[timestamp] === SHOPIFY WEBHOOK REQUEST RECEIVED ===
[timestamp] ✅ HMAC VERIFIED SUCCESSFULLY
[timestamp] ✅ SHOPIFY WEBHOOK HMAC VERIFIED - Processing...
[timestamp] → Routing to handleAppUninstalled
[timestamp] ✅ app/uninstalled webhook processed successfully - Returning HTTP 200
```

**This means:** ✅ Everything working correctly!

### Bad Log Sequence:

```
[timestamp] === SHOPIFY WEBHOOK REQUEST RECEIVED ===
[timestamp] ❌ HMAC VERIFICATION FAILED: Missing X-Shopify-Hmac-Sha256 header
```

**This means:** ❌ Request not from Shopify or HMAC header missing

### No Logs At All:

**This means:**
- Shopify can't reach your endpoint (check URL)
- Webhooks not registered (reinstall app)
- Watching wrong log file

---

## 🎯 Quick Test Commands

### Test 1: Endpoint Accessibility
```bash
curl -X POST https://ai-chat.support/shopify/webhooks \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: app/uninstalled" \
  -d '{"test": true}'

# Expected: HTTP 401 (HMAC missing - correct!)
# Bad: HTTP 404 (route not found)
```

### Test 2: With Valid HMAC
```bash
cd /var/www/clients/client1/web64/web
SHOPIFY_SECRET="e373027d7961ce9576b8e5ed48efb8ac" bash test_shopify_webhooks.sh

# Expected: All tests pass with HTTP 200
```

### Test 3: Check Webhook Registration
```bash
cd /var/www/clients/client1/web64/web/laravel
grep -A 2 "Shopify webhook registered successfully" storage/logs/laravel.log | tail -20

# Should show 4 webhooks registered
```

---

## ✅ Checklist Before Running Tests

- [ ] App installed on dev store
- [ ] Webhooks registered (check logs)
- [ ] SHOPIFY_SECRET in .env matches Partner Dashboard
- [ ] Endpoint accessible (test with curl)
- [ ] Log monitoring running
- [ ] Ready to click "Run checks" in Partner Dashboard

---

## 🎉 Expected Outcome

### After clicking "Run checks":

**In logs, you'll see:**
```
4-8 webhook requests (Shopify may test each topic 1-2 times)
All showing ✅ HMAC VERIFIED
All returning HTTP 200
Response times < 100ms
```

**In Partner Dashboard:**
```
Automated checks for common errors
  ✅ Immediately authenticates after install
  ✅ Immediately redirects to app UI after authentication
  ✅ Provides mandatory compliance webhooks ← SHOULD PASS NOW!
  ✅ Verifies webhooks with HMAC signatures ← SHOULD PASS NOW!
  ✅ Uses a valid TLS certificate
```

---

## 📞 If Tests Still Fail

**Provide these details:**

1. **Logs from test run:**
   ```bash
   cd /var/www/clients/client1/web64/web/laravel
   grep -A 10 "SHOPIFY WEBHOOK REQUEST RECEIVED" storage/logs/laravel.log | tail -50
   ```

2. **HMAC configuration:**
   ```bash
   grep SHOPIFY_SECRET .env
   # (mask the actual secret when sharing)
   ```

3. **Webhook registration status:**
   ```bash
   grep "Shopify webhook registered" storage/logs/laravel.log
   ```

4. **Error messages from Partner Dashboard**

---

**Start monitoring logs NOW and run the Shopify automated checks!** 🚀

The detailed logging will tell us exactly what's happening!
