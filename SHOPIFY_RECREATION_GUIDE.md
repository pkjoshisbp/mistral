# 🔄 Shopify App Deletion & Recreation Guide

**Date**: October 14, 2025  
**Purpose**: Remove sales channel distribution and recreate as public/custom app  
**Backup Reference**: See `SHOPIFY_APP_BACKUP.md` for all settings

---

## ⚠️ PRE-DELETION CHECKLIST

Before you start, ensure:

- [x] Full backup created (`SHOPIFY_APP_BACKUP.md`)
- [x] Current credentials documented
- [x] All code is committed to git
- [ ] Ready to spend 20-30 minutes on this process
- [ ] Understand that existing installations will break

---

## 📋 PART 1: DELETE CURRENT APP

### Step 1.1: Uninstall from Dev Store

1. Go to: `https://ai-chat-support.myshopify.com/admin/apps`
2. Find: **ai-chat-support**
3. Click: **Delete** or **Uninstall**
4. Confirm: Click **Uninstall app** in the modal

**Why**: Must uninstall before deleting app from Partner Dashboard

---

### Step 1.2: Delete App in Partner Dashboard

1. Go to: https://partners.shopify.com
2. Click: **Apps** (left sidebar)
3. Find: **ai-chat-support**
4. Click: The app name to open app details
5. Look for: **Three-dot menu (⋮)** or **Settings** button (top right)
6. Click: **Delete app** or **Archive app**
7. Confirm: Type `ai-chat-support` to confirm deletion
8. Click: **Delete** button

**Expected**: App is deleted/archived and removed from your app list

**Note**: Some apps can only be archived (not fully deleted) if they have been distributed. If you only see "Archive", that's fine - proceed with archiving.

---

### Step 1.3: Verify Deletion

1. Go to: https://partners.shopify.com/4539700/apps
2. Check: **ai-chat-support** should NOT appear in list
3. If archived: It might appear in "Archived" tab - this is fine

---

## 🆕 PART 2: CREATE NEW APP

### Option A: Using Partner Dashboard (RECOMMENDED - More Control)

#### Step 2A.1: Create App in Partner Dashboard

1. Go to: https://partners.shopify.com
2. Click: **Apps** (left sidebar)
3. Click: **Create app** button (top right)
4. Select: **Create app manually** (NOT "Use a template")

#### Step 2A.2: Configure App Type

You'll see distribution options:

**IMPORTANT - Choose ONE of these:**

**Option 1: Public App** (For App Store distribution)
- Click: **Public distribution**
- Best for: Apps you want to list in Shopify App Store
- ✅ Choose this if you plan to distribute publicly

**Option 2: Custom App** (Simpler, no App Store)
- Click: **Custom app**
- Best for: Private apps or specific clients
- ✅ Choose this for faster setup (no compliance reviews)

**⚠️ DO NOT SELECT:**
- ❌ Sales channel
- ❌ Build a sales channel

#### Step 2A.3: Enter App Details

Fill in the form:
- **App name**: `ai-chat-support` (or `ai-chat-support-v2` if original name is locked)
- **App URL**: `https://ai-chat.support/shopify/install`
- **Allowed redirection URL(s)**: `https://ai-chat.support/api/integrations/shopify/oauth/callback`

Click: **Create app**

#### Step 2A.4: Note Down New Credentials

After creation, you'll see:
- **Client ID**: Copy this! (e.g., `abc123def456...`)
- **Client secret**: Click "Show" and copy this!

**SAVE THESE IMMEDIATELY** - you'll need them in next steps.

---

### Option B: Using Shopify CLI (Alternative)

#### Step 2B.1: Initialize New App

```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app init
```

#### Step 2B.2: Answer Interactive Prompts

```
? App name: ai-chat-support
→ Type: ai-chat-support (or ai-chat-support-v2)

? Which template do you want to use?
→ Select: None (we have existing code)

? Distribution method?
→ Select: Public distribution (or Custom app)
   ⚠️ DO NOT SELECT: Sales channel

? Install dependencies?
→ Select: No (we already have the project)
```

**Expected**: CLI creates basic structure and shows Client ID

#### Step 2B.3: Get Credentials from CLI Output

Look for output like:
```
✔ App created in Partner Dashboard
Client ID: abc123def456...
```

Copy the Client ID. To get the secret:
1. Go to Partner Dashboard → Apps → ai-chat-support
2. Go to App Setup tab
3. Copy Client secret

---

## 🔧 PART 3: UPDATE CONFIGURATION FILES

### Step 3.1: Update shopify.app.toml

```bash
cd /var/www/clients/client1/web64/web
nano shopify.app.toml
```

**Update the `client_id` line only:**

```toml
client_id = "YOUR_NEW_CLIENT_ID_HERE"
name = "ai-chat-support"
application_url = "https://ai-chat.support/shopify/install"
embedded = true

[webhooks]
api_version = "2025-01"

  [[webhooks.subscriptions]]
  topics = ["app/uninstalled"]
  uri = "https://ai-chat.support/shopify/webhooks"

[webhooks.privacy_compliance]
customer_deletion_url = "https://ai-chat.support/shopify/webhooks"
customer_data_request_url = "https://ai-chat.support/shopify/webhooks"
shop_deletion_url = "https://ai-chat.support/shopify/webhooks"

[access_scopes]
scopes = "write_script_tags"
optional_scopes = []
use_legacy_install_flow = false

[auth]
redirect_urls = [
  "https://ai-chat.support/api/integrations/shopify/oauth/callback"
]

[app_preferences]
url = "https://ai-chat.support/shopify/preferences"
```

**Save**: Ctrl+O, Enter, Ctrl+X

---

### Step 3.2: Update Laravel .env

```bash
cd /var/www/clients/client1/web64/web/laravel
nano .env
```

**Find these lines and update:**

```bash
# Shopify App Credentials
SHOPIFY_API_KEY=YOUR_NEW_CLIENT_ID_HERE
SHOPIFY_API_SECRET=YOUR_NEW_CLIENT_SECRET_HERE
```

**Save**: Ctrl+O, Enter, Ctrl+X

---

### Step 3.3: Clear Laravel Cache

```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan config:clear
php artisan cache:clear
```

---

## 🚀 PART 4: DEPLOY & CONFIGURE

### Step 4.1: Link App Configuration

```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app config link --client-id=YOUR_NEW_CLIENT_ID
```

**Expected output:**
```
✔ shopify.app.toml is now linked to "ai-chat-support" on Shopify
```

---

### Step 4.2: Deploy App Version

```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app deploy --force
```

**Expected output:**
```
✔ New version released to users.
web-1 (or similar version name)
```

---

### Step 4.3: Verify Active Version

```bash
npx @shopify/cli app versions list
```

**Expected output:**
```
VERSION  STATUS    MESSAGE  DATE CREATED         CREATED BY
web-1    ★ active           2025-10-14 16:xx:xx  gid://shopify/...
```

**Confirm**: Version shows ★ active

---

## ✅ PART 5: TESTING & VERIFICATION

### Step 5.1: Install on Dev Store

1. Open browser: `https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com`
2. Click: **Install app**
3. Shopify OAuth screen appears
4. Click: **Install app** on Shopify screen
5. Redirected back to: `https://ai-chat.support/shopify/complete-setup`

**Expected**: OAuth flow completes successfully

---

### Step 5.2: Verify Database Records

```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan tinker
```

```php
// Check integration created
\App\Models\Integration::where('integration_type', 'shopify')->latest()->first();
// Should show: shop_domain, access_token, organization_id

// Check organization created
\App\Models\Organization::latest()->first();
// Should show: name, slug

exit
```

---

### Step 5.3: Verify Webhooks Registered

```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan shopify:webhooks ai-chat-support.myshopify.com list
```

**Expected output:**
```
Registered webhooks for ai-chat-support.myshopify.com:
- id:xxxxxxxxx topic:app/uninstalled address:https://ai-chat.support/shopify/webhooks
Total: 1
```

**Note**: Privacy compliance webhooks won't show here (configured in TOML, not via API)

---

### Step 5.4: Test Preferences Page

1. Open: `https://ai-chat.support/shopify/preferences?shop=ai-chat-support.myshopify.com`
2. **Expected**: Widget settings form appears
3. Try: Change position to "bottom-left"
4. Click: **Save Preferences**
5. **Expected**: Success message appears

---

### Step 5.5: Monitor Logs for Errors

```bash
tail -30 /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log
```

**Look for**: Any errors related to Shopify integration  
**Expected**: No errors, just successful OAuth and webhook logs

---

## 🧪 PART 6: RUN AUTOMATED CHECKS

### Step 6.1: Access Partner Dashboard Distribution

1. Go to: https://partners.shopify.com
2. Click: **Apps** → **ai-chat-support** (or new name)
3. Click: **Distribution** tab
4. Scroll to: **Automated checks for common errors**

---

### Step 6.2: Run Checks

1. Click: **Run** button
2. Wait: 30-60 seconds for checks to complete
3. Watch: Progress indicators

---

### Step 6.3: Verify All Checks Pass

**Expected results - ALL should be ✅ GREEN:**

- ✅ Immediately authenticates after install
- ✅ Immediately redirects to app UI after authentication
- ✅ Provides mandatory compliance webhooks
- ✅ Verifies webhooks with HMAC signatures
- ✅ Uses a valid TLS certificate

**If any fail**: See troubleshooting section below

---

### Step 6.4: Monitor Webhook Test Traffic

In a separate terminal:

```bash
tail -f /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -i shopify
```

**During automated checks, you should see:**

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

## 🎯 FINAL VERIFICATION CHECKLIST

Before proceeding with app listing:

- [ ] New app created (Partner Dashboard shows it)
- [ ] Distribution method is **Public** or **Custom** (NOT Sales Channel)
- [ ] shopify.app.toml updated with new client_id
- [ ] .env updated with new SHOPIFY_API_KEY and SHOPIFY_API_SECRET
- [ ] App config linked (`npx app config link`)
- [ ] App version deployed and active
- [ ] OAuth flow works (successful installation)
- [ ] Integration record created in database
- [ ] Organization record created
- [ ] ScriptTag injected (visible in Shopify admin)
- [ ] Webhooks registered (visible in logs)
- [ ] Preferences page loads and saves settings
- [ ] **All 5 automated checks PASS ✅**
- [ ] No errors in Laravel logs
- [ ] Widget appears on storefront (if ScriptTag active)

---

## 🚨 TROUBLESHOOTING

### Issue: "App name already taken"

**Solution**: Use a different name like `ai-chat-support-v2`

Update in:
- Partner Dashboard app name
- `shopify.app.toml` → `name = "ai-chat-support-v2"`

---

### Issue: OAuth callback fails (404 or 500)

**Check**:
```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan route:list | grep shopify
```

**Verify**: Route exists for `/api/integrations/shopify/oauth/callback`

**Fix if missing**:
```bash
php artisan route:clear
php artisan config:clear
```

---

### Issue: "Client ID not found" during config link

**Solution**: Verify you copied the correct Client ID from Partner Dashboard

Check:
1. Partner Dashboard → Apps → ai-chat-support → App Setup
2. Copy the exact Client ID shown
3. Update `shopify.app.toml` with exact value

---

### Issue: Automated checks fail for webhooks

**Check deployment**:
```bash
npx @shopify/cli app versions list
```

Verify:
- Latest version is ★ active
- If inactive, run: `npx @shopify/cli app deploy --force`

**Check TOML configuration**:
```bash
cat /var/www/clients/client1/web64/web/shopify.app.toml
```

Verify `[webhooks.privacy_compliance]` section exists with all 3 URLs

---

### Issue: Preferences page shows "Integration not found"

**Cause**: App not installed on the shop

**Solution**:
1. Install app first: `https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com`
2. Complete OAuth flow
3. Then try preferences page

---

### Issue: ScriptTag not injecting

**Check logs**:
```bash
grep -i scripttag /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | tail -20
```

**Verify scope**:
- `shopify.app.toml` has `scopes = "write_script_tags"`
- App was deployed after adding this scope

**Manual check in Shopify**:
1. Go to: `https://ai-chat-support.myshopify.com/admin/settings/apps/development`
2. Click: ai-chat-support
3. Check: Should show "Script tags" permission

---

## 📊 OLD vs NEW Comparison

| Aspect | Old App | New App |
|--------|---------|---------|
| **Distribution** | Sales Channel ❌ | Public/Custom ✅ |
| **Client ID** | e209ea490d1c4a8981ba790ecaf75ad8 | NEW_CLIENT_ID |
| **API Secret** | e373027d7961ce9576b8e5ed48efb8ac | NEW_SECRET |
| **Webhooks** | All configured ✅ | Same configuration |
| **OAuth URLs** | Same | Same |
| **Code** | No changes | No changes |
| **Automated Checks** | Passed ✅ | Need to re-run |
| **Compliance** | More requirements | Fewer requirements |

---

## ✨ SUCCESS CRITERIA

You'll know the recreation was successful when:

1. ✅ Partner Dashboard shows new app
2. ✅ Distribution method is NOT "Sales Channel"
3. ✅ OAuth flow completes without errors
4. ✅ All 5 automated checks PASS
5. ✅ Preferences page loads and works
6. ✅ No errors in Laravel logs
7. ✅ Ready to proceed with app listing

---

## 📞 If You Get Stuck

### Reference Documents
- **Complete backup**: `SHOPIFY_APP_BACKUP.md`
- **Sales channel explanation**: `SHOPIFY_SALES_CHANNEL_SOLUTION.md`
- **Success summary**: `SHOPIFY_SUCCESS.md`

### Quick Recovery
If something breaks, you can reference `SHOPIFY_APP_BACKUP.md` which has:
- Complete TOML configuration
- All URLs and routes
- Database schema
- Full Laravel integration code

### Shopify Support
If creation fails:
- https://partners.shopify.com/support
- Click "Get help" in Partner Dashboard
- Mention: "Need help creating public app instead of sales channel"

---

**Ready?** Follow the steps in order, and you'll have a new app without the sales channel in 20-30 minutes! 🚀

**Document**: `/var/www/clients/client1/web64/web/SHOPIFY_RECREATION_GUIDE.md`  
**Created**: October 14, 2025  
**Status**: Ready to execute
