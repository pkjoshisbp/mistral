# ✅ NEW Shopify App Configuration - COMPLETED

**Date**: October 14, 2025  
**Status**: Successfully deployed with all webhooks configured  
**Distribution Method**: Public/Custom (NO sales channel) ✅

---

## 🎉 NEW App Details

### Partner Dashboard Information
- **App Name**: ai-chat-support
- **Client ID**: `d9d8ed2dd9a7d99e67ca61fd135da57c` ✅ NEW
- **API Secret**: `d3f3c4c2acc03c2a56b9c45928c7fae2` ✅ NEW
- **Organization**: AI Chat Support
- **Current Active Version**: web-3 ★ ACTIVE
- **Distribution Method**: Public/Custom (NOT Sales Channel) ✅

---

## ✅ Configuration Updated

### Files Updated Successfully

1. **shopify.app.ai-chat-support.toml** (New config file)
   ```toml
   client_id = "d9d8ed2dd9a7d99e67ca61fd135da57c"
   name = "ai-chat-support"
   application_url = "https://ai-chat.support/shopify/install"
   embedded = true
   
   [webhooks]
   api_version = "2025-01"
   
     [webhooks.privacy_compliance]
     customer_deletion_url = "https://ai-chat.support/shopify/webhooks"
     customer_data_request_url = "https://ai-chat.support/shopify/webhooks"
     shop_deletion_url = "https://ai-chat.support/shopify/webhooks"
   
     [[webhooks.subscriptions]]
     topics = ["app/uninstalled"]
     uri = "https://ai-chat.support/shopify/webhooks"
   
   [access_scopes]
   scopes = "read_script_tags,write_script_tags"
   
   [auth]
   redirect_urls = [
     "https://ai-chat.support/api/integrations/shopify/oauth/callback"
   ]
   
   [app_preferences]
   url = "https://ai-chat.support/shopify/preferences"
   ```

2. **laravel/.env**
   ```bash
   SHOPIFY_API_KEY=d9d8ed2dd9a7d99e67ca61fd135da57c
   SHOPIFY_API_SECRET=d3f3c4c2acc03c2a56b9c45928c7fae2
   ```

3. **Laravel Cache**
   - Config cache cleared ✅
   - Application cache cleared ✅

---

## 🚀 Deployment Status

### CLI Commands Executed
```bash
✅ App config linked to new app
✅ Version web-3 deployed and released
✅ All webhooks configured (privacy compliance + app/uninstalled)
```

### Version History
| Version | Status | Created |
|---------|--------|---------|
| web-3 | ★ active | 2025-10-14 16:54:47 |
| ver-1.0 | inactive | 2025-10-14 16:46:33 |
| ai-chat-support-1 | inactive | 2025-10-14 16:41:57 |

---

## 📋 Webhook Configuration (DEPLOYED)

### Privacy Compliance Webhooks ✅
| Topic | URL | Status |
|-------|-----|--------|
| `customers/data_request` | https://ai-chat.support/shopify/webhooks | ✅ Deployed in web-3 |
| `customers/redact` | https://ai-chat.support/shopify/webhooks | ✅ Deployed in web-3 |
| `shop/redact` | https://ai-chat.support/shopify/webhooks | ✅ Deployed in web-3 |

### Regular Webhooks ✅
| Topic | URL | Status |
|-------|-----|--------|
| `app/uninstalled` | https://ai-chat.support/shopify/webhooks | ✅ Deployed in web-3 |

---

## 🎯 NEXT STEPS - CRITICAL

### Step 1: Install App on Dev Store

**URL**: 
```
https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com
```

**What This Does**:
- Tests OAuth flow with new credentials
- Creates new Integration record in database
- Registers webhooks via Admin API
- Injects ScriptTag for widget

**Expected Result**:
- Redirects to Shopify OAuth screen
- After approval, redirects back to complete-setup page
- No errors in Laravel logs

---

### Step 2: Verify Installation

**Check Database**:
```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan tinker
```

```php
// Check latest integration
\App\Models\Integration::where('integration_type', 'shopify')->latest()->first();
// Should show: new access_token, shop_domain, organization_id

// Check webhooks registered
exit
```

**Check Webhooks**:
```bash
php artisan shopify:webhooks ai-chat-support.myshopify.com list
```

**Expected Output**:
```
Registered webhooks for ai-chat-support.myshopify.com:
- id:xxxxxxxxx topic:app/uninstalled address:https://ai-chat.support/shopify/webhooks
Total: 1
```

**Note**: Privacy compliance webhooks won't show here (configured at app level in TOML)

---

### Step 3: Run Automated Checks (MOST IMPORTANT)

1. **Go to**: https://partners.shopify.com
2. **Navigate**: Apps → ai-chat-support → **Distribution** tab
3. **Click**: **"Run"** button under "Automated checks for common errors"
4. **Wait**: 30-60 seconds for checks to complete

**Expected Results - ALL should be ✅ GREEN:**
- ✅ Immediately authenticates after install
- ✅ Immediately redirects to app UI after authentication
- ✅ Provides mandatory compliance webhooks
- ✅ Verifies webhooks with HMAC signatures
- ✅ Uses a valid TLS certificate

---

### Step 4: Monitor Webhook Test Traffic

**Open Terminal**:
```bash
tail -f /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -i shopify
```

**During Automated Checks, You Should See**:
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

**All from IP**: 34.16.29.72 (Shopify-Captain-Hook)

---

### Step 5: Test Preferences Page

**URL**:
```
https://ai-chat.support/shopify/preferences?shop=ai-chat-support.myshopify.com
```

**Expected**:
- Widget settings form loads
- Can change position, color, message
- Save works without errors
- Success message appears

---

## ✅ Verification Checklist

Before proceeding with app listing:

- [ ] New app created in Partner Dashboard (NOT sales channel)
- [x] shopify.app.ai-chat-support.toml linked and configured
- [x] Laravel .env updated with new credentials
- [x] Laravel cache cleared
- [x] App version web-3 deployed and ★ active
- [ ] **Install app on dev store** (NEXT STEP)
- [ ] **OAuth flow completes successfully**
- [ ] Integration record created in database
- [ ] Webhooks registered (visible in logs)
- [ ] **All 5 automated checks PASS ✅** (CRITICAL)
- [ ] Preferences page loads and works
- [ ] No errors in Laravel logs

---

## 🎊 Key Differences from Old App

| Aspect | Old App | New App |
|--------|---------|---------|
| **Distribution** | Sales Channel ❌ | Public/Custom ✅ |
| **Client ID** | e209ea490d1c4a8981ba790ecaf75ad8 | d9d8ed2dd9a7d99e67ca61fd135da57c |
| **API Secret** | e373027d7961ce9576b8e5ed48efb8ac | d3f3c4c2acc03c2a56b9c45928c7fae2 |
| **Config File** | shopify.app.toml | shopify.app.ai-chat-support.toml |
| **Active Version** | web-6 | web-3 |
| **Webhooks** | Configured | Same configuration |
| **Compliance Requirements** | More (sales channel) | Fewer (standard app) |

---

## 🚨 Important Notes

### What Changed
- ✅ New Client ID and Secret (credentials updated everywhere)
- ✅ New config file created (shopify.app.ai-chat-support.toml)
- ✅ Distribution method changed (no sales channel)
- ✅ Fresh start with new app in Partner Dashboard

### What Stayed the Same
- ✅ All URLs (install, callback, webhooks, preferences)
- ✅ All Laravel code (controllers, components, views, routes)
- ✅ Database schema
- ✅ Webhook configuration structure
- ✅ Access scopes (write_script_tags)

### Old App Status
- The old app (e209ea490d1c4a8981ba790ecaf75ad8) should be deleted/archived in Partner Dashboard
- Old credentials in shopify.app.toml file (can be kept as backup)
- Old database records (Integration ID 7, Org ID 16) will remain but won't be used

---

## 📞 Troubleshooting

### If OAuth Fails During Install

**Check**:
```bash
grep SHOPIFY /var/www/clients/client1/web64/web/laravel/.env
```

**Verify**:
- SHOPIFY_API_KEY matches new Client ID
- SHOPIFY_API_SECRET matches new API Secret

**Clear cache again**:
```bash
php artisan config:clear
php artisan cache:clear
```

---

### If Automated Checks Fail

**Verify deployment**:
```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app versions list
```

**Confirm**: web-3 shows ★ active

**Check webhook config**:
```bash
cat shopify.app.ai-chat-support.toml | grep -A 10 privacy_compliance
```

**Redeploy if needed**:
```bash
npx @shopify/cli app deploy --force
```

---

### If Preferences Page Shows Error

**Ensure app is installed first**:
1. Install via: `https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com`
2. Complete OAuth flow
3. Then access preferences page

---

## 🎯 SUCCESS CRITERIA

You'll know everything is working when:

1. ✅ App deployed (web-3 active)
2. ✅ OAuth flow completes without errors
3. ✅ Integration record created in database
4. ✅ **All 5 automated checks PASS** ← MAIN GOAL
5. ✅ Preferences page loads
6. ✅ Webhook test traffic appears in logs
7. ✅ No errors in Laravel logs
8. ✅ Distribution method is NOT "Sales Channel"

---

## 🚀 Ready to Test!

**Your immediate next action**:

1. **Install the app**: Open browser and go to:
   ```
   https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com
   ```

2. **Complete OAuth flow**

3. **Run automated checks** in Partner Dashboard

4. **Verify all checks PASS ✅**

---

**Deployment complete! The new app is configured and ready for testing.** 🎉

**Document**: `/var/www/clients/client1/web64/web/SHOPIFY_NEW_APP_DEPLOYED.md`  
**Created**: October 14, 2025  
**Status**: Ready for installation and automated checks
