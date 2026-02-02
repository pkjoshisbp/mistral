# Shopify App Final Configuration Steps

## 🎯 Objective
Complete the Partner Dashboard configuration to pass automated compliance checks and enable the Preferences page.

---

## ✅ What's Already Done
1. ✅ OAuth flow working perfectly
2. ✅ Webhook endpoint handling all 4 topics correctly
3. ✅ HMAC verification passing (verified in logs)
4. ✅ `app/uninstalled` webhook registered via API
5. ✅ Shopify Preferences page built and route added
6. ✅ Fast HTTP 200 responses with proper logging

---

## ⚠️ What Needs Manual Configuration

### Step 1: Configure GDPR Compliance Webhooks

**Why Manual?** GDPR webhooks CANNOT be registered via Shopify Admin REST API. They must be configured at the app level through Partner Dashboard.

**Where to Go:**
1. Navigate to: https://partners.shopify.com
2. Click **Apps** in left sidebar
3. Click **ai-chat-support** (your app)
4. Click **Configuration** tab (⚠️ NOT "Versions" - this is important!)

**What to Look For:**
Look for one of these sections (exact name varies):
- "Webhooks"
- "GDPR webhooks"  
- "Compliance webhooks"
- "Mandatory webhooks"
- "Protected customer data"

**What to Configure:**
Enter this URL in **ALL THREE** GDPR webhook fields:
```
https://ai-chat.support/shopify/webhooks
```

The three fields will be labeled:
1. **Customer data request endpoint** → `customers/data_request`
2. **Customer data erasure endpoint** → `customers/redact`
3. **Shop data erasure endpoint** → `shop/redact`

**After Entering:**
- Click **Save** button
- You should see a success message
- The webhooks are now registered at the **app configuration level** (not tied to a version)

---

### Step 2: Set Preferences URL

**Why?** This enables the "Preferences" link in merchant's Shopify admin when your app is installed.

**Where to Go:**
- Same **Configuration** tab from Step 1
- Scroll to find "App URL" or "URLs" section

**What to Configure:**
Find the field labeled **"Preferences URL"** and enter:
```
https://ai-chat.support/shopify/preferences
```

**What This Does:**
- When merchant clicks "Preferences" in their Shopify admin, they'll see your custom widget settings page
- URL will receive `?shop={merchant_shop_domain}` query parameter automatically
- Your Livewire component handles authentication and loads their settings

---

### Step 3: Review Embed Setting

**Why?** Your app is a **storefront widget**, NOT an admin-embedded app.

**Where to Go:**
- Same **Configuration** tab
- Look for "App embed" or "Embed app in Shopify admin" setting

**What to Configure:**
- **UNCHECK** "Embed app in Shopify admin" (set to `false`)
- This is correct because your app:
  - Injects a chat widget into customer storefronts via ScriptTag
  - Does NOT need to render UI inside Shopify admin
  - Only uses OAuth for authentication and webhook delivery

**If Already Unchecked:**
- Perfect! Leave it as is.

---

## 🧪 Step 4: Verify Configuration

### A. Check Webhook Registration (Optional but Recommended)

Run this command on your server:
```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan shopify:webhooks ai-chat-support.myshopify.com list
```

**Expected Output:**
You should now see **4 webhooks** (previously only 1):
```
Registered webhooks for ai-chat-support.myshopify.com:
1. app/uninstalled → https://ai-chat.support/shopify/webhooks
2. customers/data_request → https://ai-chat.support/shopify/webhooks
3. customers/redact → https://ai-chat.support/shopify/webhooks
4. shop/redact → https://ai-chat.support/shopify/webhooks
```

⚠️ **If Still Only 1 Webhook:**
- The GDPR webhooks are registered at **app-level** in Partner Dashboard
- They won't show up via Admin API list command
- This is NORMAL - Shopify's automated checker validates them differently
- Proceed to Step B anyway

### B. Re-Run Automated Checks

1. Go to Partner Dashboard → **Distribution** tab
2. Click **"Run automated checks"** button
3. Wait for checks to complete (30-60 seconds)

**Expected Result:**
Both checks should now **PASS** ✅:
- ✅ Provides mandatory compliance webhooks
- ✅ Verifies webhooks with HMAC signatures

### C. Monitor Live Webhook Traffic

Open another terminal and tail Laravel logs:
```bash
tail -f /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -i shopify
```

**During Automated Checks, You'll See:**
```
[SHOPIFY WEBHOOK] Received: customers/data_request from shop.myshopify.com
✅ HMAC VERIFIED SUCCESSFULLY
[SHOPIFY WEBHOOK] Returning HTTP 200 for customers/data_request

[SHOPIFY WEBHOOK] Received: customers/redact from shop.myshopify.com
✅ HMAC VERIFIED SUCCESSFULLY
[SHOPIFY WEBHOOK] Returning HTTP 200 for customers/redact

[SHOPIFY WEBHOOK] Received: shop/redact from shop.myshopify.com
✅ HMAC VERIFIED SUCCESSFULLY
[SHOPIFY WEBHOOK] Returning HTTP 200 for shop/redact
```

All from IP: `34.16.29.72` (Shopify-Captain-Hook - the automated checker)

---

## 🎨 Step 5: Test Preferences Page

### Access URL:
```
https://ai-chat.support/shopify/preferences?shop=ai-chat-support.myshopify.com
```

**What You Should See:**
1. Form with widget settings:
   - Enable/Disable toggle
   - Position dropdown (bottom-right, bottom-left, top-right, top-left)
   - Primary color picker with hex input
   - Welcome message textarea (200 char limit)
   - Horizontal and vertical offset inputs
2. Success alert after saving
3. Settings persist to `organizations.widget_settings` JSON column

**If Errors:**
- Check that Integration exists for the shop
- Check that Organization is linked to Integration
- Review Laravel logs for specific error messages

---

## 📊 Final Checklist

Before submitting app for review:

- [ ] Step 1: GDPR webhooks configured in Partner Dashboard Configuration tab
- [ ] Step 2: Preferences URL set to `https://ai-chat.support/shopify/preferences`
- [ ] Step 3: "Embed app" setting is unchecked (false)
- [ ] Step 4A: Verified webhook list (optional - may not show GDPR webhooks)
- [ ] Step 4B: Automated checks PASS ✅ (both compliance and HMAC)
- [ ] Step 4C: Confirmed webhooks hitting endpoint in logs with HTTP 200
- [ ] Step 5: Tested Preferences page loads and saves settings

---

## 🚨 Troubleshooting

### Automated Checks Still Failing?

**Check 1: Webhook URL Format**
- Must be exact: `https://ai-chat.support/shopify/webhooks`
- No trailing slash
- Must use HTTPS (not HTTP)

**Check 2: Configuration vs. Versions**
- GDPR webhooks MUST be in **Configuration** tab
- NOT in individual version configurations
- App-level settings, not version-level

**Check 3: Endpoint Accessibility**
- Test from external network: `curl -I https://ai-chat.support/shopify/webhooks`
- Should return `405 Method Not Allowed` (correct - POST only)
- Should NOT return 404, 500, or connection errors

**Check 4: HMAC Secret**
- Verify `SHOPIFY_API_SECRET` in `/var/www/clients/client1/web64/web/laravel/.env`
- Must match the API secret key shown in Partner Dashboard → App Setup

### Preferences Page Not Loading?

**Error: "Integration not found"**
- Shop must be installed first
- Run install flow: `https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com`
- Complete OAuth callback

**Error: "Organization not found"**
- Check `integrations` table has `organization_id` column populated
- Verify Organization exists in `organizations` table
- Check logs for specific relationship errors

**Error: 404 Not Found**
- Clear route cache: `php artisan route:clear`
- Verify route exists: `php artisan route:list | grep shopify/preferences`
- Check Livewire component namespace is correct

---

## 📞 Support References

**Documentation Created:**
- `SHOPIFY_MANUAL_WEBHOOK_CONFIG.md` - Detailed webhook explanation
- `SHOPIFY_SOLUTION.md` - Technical problem analysis
- `SHOPIFY_GDPR_WEBHOOKS.md` - Why GDPR webhooks need Partner Dashboard

**Key Files:**
- Webhook Handler: `laravel/app/Http/Controllers/ShopifyWebhookController.php`
- Preferences Component: `laravel/app/Livewire/Shopify/Preferences.php`
- Preferences View: `laravel/resources/views/livewire/shopify/preferences.blade.php`
- OAuth Controller: `laravel/app/Http/Controllers/IntegrationController.php`

**Artisan Commands:**
- List webhooks: `php artisan shopify:webhooks {shop} list`
- Register webhooks: `php artisan shopify:webhooks {shop} register` (only for app/uninstalled)

---

## ✨ Next Steps After Approval

Once automated checks pass:

1. **Submit for Review**: Partner Dashboard → Distribution → "Create submission"
2. **App Listing**: Fill out app description, screenshots, pricing
3. **Test Installation**: Install on a real Shopify store (not dev store)
4. **Monitor Usage**: Track via `token_usage_logs` table
5. **Iterate**: Collect merchant feedback via Preferences page

---

**You're almost there! The configuration is straightforward - just need to update those Partner Dashboard settings manually. Good luck! 🚀**
