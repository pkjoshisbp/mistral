# Shopify Theme App Extension - Deployment Guide

## ✅ ISSUE 3 FIXED: Theme App Extensions (5.1.1)

We have successfully removed automatic script tag injection and implemented proper theme app extensions.

---

## 📁 What Was Created

### 1. Theme App Extension Files
```
/extensions/ai-chat-widget/
├── blocks/
│   ├── chat-widget.liquid      # Main widget block with customization settings
│   └── app-embed.liquid         # App embed for automatic inclusion
└── assets/
    ├── chat-widget.css          # Extension styles
    └── chat-widget.js           # Extension JavaScript
```

### 2. Onboarding System
- ✅ `ShopifyOnboarding` Livewire component
- ✅ Step-by-step setup instructions
- ✅ Deep link to theme editor
- ✅ Route: `/shopify/onboarding`

### 3. Code Changes
- ✅ Removed `createShopifyScriptTag()` auto-injection
- ✅ Removed `write_script_tags` scope from OAuth
- ✅ Updated shopify.app.toml configuration
- ✅ Redirects to onboarding after installation

---

## 🚀 DEPLOYMENT STEPS

### Step 1: Update Shopify App Configuration

```bash
cd /var/www/clients/client1/web64/web
```

The `shopify.app.ai-chat-support.toml` has been updated with theme extension configuration.

### Step 2: Deploy Theme Extension to Shopify

```bash
# Install Shopify CLI if not already installed
npm install -g @shopify/cli @shopify/theme

# Deploy the extension
cd /var/www/clients/client1/web64/web
shopify app deploy
```

**What this does:**
- Packages the extension files
- Uploads to Shopify Partner Dashboard
- Makes it available in merchants' theme editors

### Step 3: Update Shopify Partner Dashboard

1. **Go to Shopify Partner Dashboard**
2. **Apps → AI CHAT SUPPORT → Configuration**
3. **Verify extension is listed:**
   - Extension name: `ai-chat-widget`
   - Type: Theme Extension
   - Status: Active

### Step 4: Update App Scopes (if needed)

In Partner Dashboard → Configuration → Compliance:
- Verify `write_script_tags` is **removed**
- Verify `read_script_tags` is **removed**
- Keep: `read_products`, `read_orders`, `read_themes`

### Step 5: Test in Development Store

1. **Install app in test store:**
   ```
   https://ai-chat.support/shopify/install?shop=YOUR-TEST-STORE.myshopify.com
   ```

2. **Follow onboarding instructions:**
   - Should redirect to onboarding page
   - No automatic script injection
   - Deep link to theme editor provided

3. **Enable in Theme Editor:**
   - Go to theme editor
   - Find "App embeds" section
   - Toggle "AI Chat Support" ON
   - Save theme

4. **Verify widget appears on storefront**

---

## 🔧 How It Works Now

### Installation Flow

```
1. Merchant clicks "Add app" in Shopify App Store
   ↓
2. Shopify redirects to: /shopify/install?shop=store.myshopify.com
   ↓
3. OAuth flow completes
   ↓
4. NO SCRIPT TAG INJECTION (removed!)
   ↓
5. Redirects to: /shopify/onboarding?shop=store.myshopify.com
   ↓
6. Merchant sees step-by-step instructions
   ↓
7. Deep link opens theme editor
   ↓
8. Merchant enables app embed
   ↓
9. Widget appears on store
```

### Merchant Control

**Before (WRONG):**
- ❌ App automatically adds script tag
- ❌ Merchant has no control
- ❌ Violates Shopify requirements

**After (CORRECT):**
- ✅ Merchant enables via theme editor
- ✅ Full control over appearance
- ✅ Can disable anytime
- ✅ Follows Shopify requirements

---

## 📝 Theme Extension Features

### App Embed Block (`app-embed.liquid`)
- **Purpose:** Simple one-click enable in theme settings
- **Target:** `body` (appears on all pages)
- **Settings:**
  - Widget color override
- **Best for:** Merchants who want it everywhere with minimal setup

### Chat Widget Block (`chat-widget.liquid`)
- **Purpose:** Advanced customization options
- **Target:** Individual sections/pages
- **Settings:**
  - Enable/disable toggle
  - Primary color picker
  - Widget position (bottom-right/left)
  - Widget size slider
  - Welcome message
  - Input placeholder
  - Auto-open options
  - Auto-open delay
- **Best for:** Merchants who want fine-grained control

---

## 🧪 TESTING CHECKLIST

### Pre-Deployment Tests

- [x] Extension files created
- [x] shopify.app.toml updated
- [x] Auto-injection removed
- [x] Scopes updated
- [x] Onboarding component created
- [x] Route added

### Post-Deployment Tests

- [ ] Extension appears in Partner Dashboard
- [ ] Test store installation works
- [ ] Onboarding page loads correctly
- [ ] Deep link opens theme editor
- [ ] App embed toggles on/off
- [ ] Widget appears on storefront
- [ ] Widget loads correctly
- [ ] No console errors
- [ ] Widget persists after page navigation
- [ ] Can customize colors in theme editor
- [ ] Can disable app embed

---

## 🐛 TROUBLESHOOTING

### Extension doesn't appear in theme editor

**Problem:** After deployment, merchants don't see the app in theme editor.

**Solutions:**
1. Verify extension was deployed:
   ```bash
   shopify app extensions list
   ```

2. Check Partner Dashboard → Extensions tab

3. Re-deploy extension:
   ```bash
   shopify app deploy --force
   ```

### Deep link doesn't work

**Problem:** "Open Theme Editor" button doesn't go to right place.

**Solutions:**
1. Verify `SHOPIFY_APP_ID` in `.env`
2. Update `ShopifyOnboarding.php` mount method:
   ```php
   $this->deepLink = "https://{$this->shop}/admin/themes/current/editor?context=apps";
   ```

### Widget doesn't load after enabling

**Problem:** App embed is ON but widget doesn't appear.

**Solutions:**
1. Check browser console for errors
2. Verify widget script URL is correct:
   ```
   https://ai-chat.support/widget/{org_slug}/script.js
   ```
3. Check organization exists and has correct slug
4. Verify FastAPI is running

---

## 📊 BEFORE vs AFTER

### Before (Auto-Injection)

```php
// OLD CODE - REMOVED
private function createShopifyScriptTag($shop, $accessToken, $orgId)
{
    $scriptSrc = config('app.url') . "/api/integrations/widget-script/{$orgId}";
    
    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $accessToken
    ])->post("https://{$shop}/admin/api/2025-01/script_tags.json", [
        'script_tag' => [
            'event' => 'onload',
            'src' => $scriptSrc
        ]
    ]);
    // ...
}
```

### After (Theme Extension)

```liquid
<!-- NEW CODE - extensions/ai-chat-widget/blocks/app-embed.liquid -->
{% schema %}
{
  "name": "AI Chat Support",
  "target": "body",
  "settings": [...]
}
{% endschema %}

<script>
  // Load widget script
  var script = document.createElement('script');
  script.src = 'https://ai-chat.support/widget/' + orgSlug + '/script.js';
  document.body.appendChild(script);
</script>
```

**Key Difference:**
- ❌ Server-side forced injection → ✅ Client-side merchant-controlled loading
- ❌ No merchant control → ✅ Toggle on/off in theme editor
- ❌ Violates Shopify rules → ✅ Follows Shopify requirements

---

## 🎯 NEXT STEPS

After deployment:

1. ✅ **Test in development store**
2. ⏳ **Submit for review** (with other fixes)
3. ⏳ **Monitor for approval**

### Related Fixes Needed

This completes **Issue 3**, but still need:

- **Issue 1:** Billing API/Managed Pricing
- **Issue 2:** Pricing information
- **Issue 4:** Onboarding instructions (✅ DONE)
- **Issue 5A:** Store integration loop
- **Issue 5B:** Chatbot reset on navigation
- **Issue 6:** Language support
- **Issue 7:** Installation flow
- **Issue 8:** Widget branding

---

## 📞 SUPPORT

If deployment fails or you encounter issues:

1. Check Shopify CLI logs
2. Verify Partner Dashboard shows extension
3. Test in development store first
4. Contact Shopify Partner Support if extension won't deploy

---

## ✅ COMPLETION CHECKLIST

- [x] Theme extension files created
- [x] Auto-injection removed
- [x] Scopes updated
- [x] Onboarding page created
- [x] Routes added
- [x] Documentation created
- [ ] Extension deployed to Shopify
- [ ] Tested in development store
- [ ] Verified with Shopify reviewer requirements

**Status:** Ready for deployment ✅
