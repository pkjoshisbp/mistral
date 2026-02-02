# Configure Shopify GDPR Webhooks Manually in Partner Dashboard

## Problem
- Shopify CLI 3.85.5 requires Node.js >= 20.10.0 (you have 18.17.0)
- CLI commands changed - `app config push` no longer exists
- Server user doesn't have sudo access to upgrade Node

## Solution: Manual Configuration in Partner Dashboard

### Step 1: Go to App Settings (Not Versions)

1. Go to: https://partners.shopify.com/
2. Click **Apps** in left sidebar
3. Click **ai-chat-support**
4. Click **Configuration** (NOT "Versions")

### Step 2: Find Webhooks Section

Scroll down to find **"Webhooks"** or **"GDPR webhooks"** or **"Compliance webhooks"** section.

You should see three required fields:
- **Customer data request endpoint**
- **Customer data erasure endpoint** 
- **Shop data erasure endpoint**

### Step 3: Enter Webhook URL for All Three

Enter this URL in ALL three fields:
```
https://ai-chat.support/shopify/webhooks
```

### Step 4: Save

Click **Save** at the top or bottom of the Configuration page.

### Step 5: Verify

After saving, check that webhooks are registered:

```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan shopify:webhooks ai-chat-support.myshopify.com list
```

You should see all 4 topics:
- app/uninstalled ✅ (already registered via API)
- customers/data_request (newly added via dashboard)
- customers/redact (newly added via dashboard)
- shop/redact (newly added via dashboard)

### Step 6: Run Automated Checks

1. Go back to Partner Dashboard → Apps → ai-chat-support → **Distribution**
2. Click **"Run checks"** under "Automated checks for common errors"
3. Wait ~2 minutes
4. Both checks should now pass:
   - ✅ Provides mandatory compliance webhooks
   - ✅ Verifies webhooks with HMAC signatures

## If You Don't See "Webhooks" Section in Configuration

Try these locations:

### Option A: App Setup
- Partner Dashboard → Apps → ai-chat-support → **App setup**
- Scroll to **"Compliance webhooks"** or **"GDPR"** section

### Option B: Distribution Settings
- Partner Dashboard → Apps → ai-chat-support → **Distribution**
- Look for **"Privacy & compliance"** or **"Webhooks"** section

### Option C: Extensions Tab
- Partner Dashboard → Apps → ai-chat-support → **Extensions**
- Look for **"Privacy webhooks"** or **"Compliance"**

## Important Notes

1. **These are APP-level settings**, not version-level
2. **You only need to configure them ONCE** per app (not per version)
3. **All versions automatically inherit** these webhook settings
4. **They persist** across all future versions and deployments

## What About the Version Page?

The version page (screenshot you showed) displays:
- App name
- Scopes
- Redirect URLs
- Webhooks API version

But **GDPR webhook URLs** are configured at the app level in Configuration/Setup, NOT in individual versions.

---

## Your Questions Answered

### Q: Preferences URL - Should I set it?

**YES**, set the Preferences URL to:
```
https://ai-chat.support/shopify/preferences
```

**Why?**
- Gives merchants a way to configure your widget settings
- Shows in Shopify admin under Apps → ai-chat-support → Settings
- Professional apps should always have this

**Create the Preferences Page:**

I can create a Livewire component for this if you want. It would show:
- Widget position (bottom-right, bottom-left, etc.)
- Primary color picker
- Welcome message customization
- Enable/disable widget
- etc.

### Q: Embed app in Shopify admin - Should I enable it?

**NO**, keep it as `false` (unselected) for your use case.

**Why?**
- Your app is a **widget that runs on the storefront**
- You don't need admin UI embedded in Shopify admin
- Setting `embedded = true` would require:
  - App Bridge SDK
  - Different authentication flow
  - Admin UI built with Polaris components
  - Runs inside an iframe in Shopify admin

**When to use embedded = true:**
- Apps that need admin dashboard UI
- Apps that manage products, orders, customers inside Shopify admin
- Apps that replace admin sections

**Your app:** Widget runs on customer-facing storefront → `embedded = false` ✅

---

## Current Configuration is Correct

From your screenshot (version 2.0.0):

✅ **App URL:** `https://ai-chat.support/shopify/install`  
✅ **Embed app in Shopify admin:** `true` (you can change to `false`)  
✅ **Preferences URL:** Currently empty - **SET THIS**  
✅ **Scopes:** `write_script_tags` (correct)  
✅ **Webhooks API version:** `2025-10`  

## Recommended Changes

1. **Add Preferences URL:**
   ```
   https://ai-chat.support/shopify/preferences
   ```

2. **Change "Embed app" to false** (unless you want admin UI)

3. **Configure GDPR webhooks** (in Configuration section, not Versions)

---

## Summary

**Don't worry about CLI anymore.** Just:

1. Go to **Configuration** (not Versions)
2. Find **GDPR/Compliance webhooks** section
3. Enter `https://ai-chat.support/shopify/webhooks` for all 3
4. Click **Save**
5. Run automated checks
6. ✅ Pass!

The manual dashboard configuration is actually the **most reliable** method for public apps, because:
- No CLI version dependencies
- No Node.js version issues
- Visual confirmation of settings
- One-time setup that persists
- Official Shopify recommended approach for production apps
