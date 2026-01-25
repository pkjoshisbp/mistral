# Manual Deployment Guide for AI Chat Widget Extension

## 🚀 Quick Deployment Steps

Since Shopify CLI authentication is challenging on headless servers, deploy the extension manually through the Partner Dashboard.

---

## Method 1: Via Shopify Partner Dashboard (Recommended)

### Step 1: Access Extension Management
1. Go to [Shopify Partner Dashboard](https://partners.shopify.com/)
2. Navigate to **Apps** → **AI CHAT SUPPORT**
3. Click on **Extensions** in the left sidebar

### Step 2: Create Theme App Extension
1. Click **Create extension**
2. Select **Theme app extension**
3. Name it: `AI Chat Widget`
4. Click **Create**

### Step 3: Upload Extension Files

You'll need to upload these files from your server:

**Block Files:**
- `/var/www/clients/client1/web64/web/extensions/ai-chat-widget/blocks/chat-widget.liquid`
- `/var/www/clients/client1/web64/web/extensions/ai-chat-widget/blocks/app-embed.liquid`

**Asset Files:**
- `/var/www/clients/client1/web64/web/extensions/ai-chat-widget/assets/chat-widget.css`
- `/var/www/clients/client1/web64/web/extensions/ai-chat-widget/assets/chat-widget.js`

**Schema/Locales (if prompted):**
- Create `locales/en.default.json` with empty object: `{}`

### Step 4: Create New App Version
1. After uploading files, click **Create version**
2. Add version notes: "Theme app extension with merchant-controlled widget installation and customization"
3. Click **Submit for review** or **Create version** (depending on UI)

### Step 5: Test in Development Store
1. In Partner Dashboard, go to **Test on development store**
2. Select a test store
3. Install the app
4. Go to store admin → **Themes** → **Customize**
5. Click **Add section** or **App embeds**
6. Enable **AI Chat Widget**

---

## Method 2: Via Shopify CLI (Alternative - Requires Local Machine)

If you have access to a local machine with browser:

```bash
# 1. Clone the extension files to your local machine
# Download the /extensions directory

# 2. Install Shopify CLI
npm install -g @shopify/cli

# 3. Navigate to project directory
cd /path/to/project

# 4. Authenticate
shopify auth login

# 5. Deploy
shopify app deploy
```

---

## Method 3: GitHub Integration (Advanced)

If your app is connected to GitHub:

1. Push the extension files to your GitHub repository
2. In Partner Dashboard → **Apps** → **AI CHAT SUPPORT**
3. Go to **Versions** tab
4. Click **Create version from GitHub**
5. Select the branch and commit
6. Deploy

---

## File Contents Reference

### Directory Structure
```
/extensions/ai-chat-widget/
├── blocks/
│   ├── chat-widget.liquid      # Main widget block with settings
│   └── app-embed.liquid         # Simple app embed
├── assets/
│   ├── chat-widget.css          # Widget styling
│   └── chat-widget.js           # Widget JavaScript
└── shopify.extension.toml       # Extension configuration
```

### Extension Configuration (shopify.extension.toml)
```toml
api_version = "2024-01"

[[extensions]]
type = "theme"
name = "AI Chat Widget"
handle = "ai-chat-widget"

[extensions.settings]
```

---

## Verification Checklist

After deployment, verify:

✅ Extension appears in Partner Dashboard → Extensions
✅ New app version created
✅ Extension can be enabled in test store Theme Editor
✅ Widget customization settings are visible
✅ Widget loads correctly on storefront
✅ Chat functionality works as expected

---

## Troubleshooting

### Issue: Extension not appearing in Theme Editor
**Solution:** Ensure the extension is included in the app version. Go to **Versions** tab and check if the extension is listed.

### Issue: Files upload failing
**Solution:** Check file syntax with Liquid validator:
```bash
# Validate Liquid syntax
shopify theme check
```

### Issue: Widget not loading on storefront
**Solution:** 
1. Verify organization slug is correct in settings
2. Check browser console for JavaScript errors
3. Ensure FastAPI backend is running (port 8111)
4. Test widget script directly: `https://ai-chat.support/widget/{org_slug}/script.js`

---

## Next Steps After Deployment

1. **Test Installation Flow**
   - Install app in development store
   - Verify OAuth redirect to onboarding page
   - Complete 5-step onboarding

2. **Test Theme Extension**
   - Enable widget in Theme Editor
   - Test customization settings (colors, position, etc.)
   - Verify widget appears on storefront

3. **Test Chat Functionality**
   - Open chat widget
   - Send test messages
   - Navigate between pages (verify persistence)
   - Close and reopen chat (verify messages saved)

4. **Prepare for Resubmission**
   - Document all fixes in reply to Shopify
   - Provide test store credentials
   - Reference this deployment as proof of Issue 3 fix

---

## Status: Ready for Deployment

All code changes are complete. Extension files are ready at:
- `/var/www/clients/client1/web64/web/extensions/ai-chat-widget/`

**Choose your deployment method above and proceed!**
