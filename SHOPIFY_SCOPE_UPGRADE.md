# Shopify App Scope Upgrade Instructions

## Problem
Your current installation only has `write_script_tags` scope. We've updated the code to request additional scopes:
- ✅ `write_script_tags` (existing)
- ✅ `read_products` (NEW)
- ✅ `read_orders` (NEW - requires Shopify approval)
- ✅ `read_themes` (NEW)

**However**, Shopify doesn't auto-upgrade scopes for existing installations. You must upgrade.

## EASIEST METHOD: One-Click Upgrade

**Just visit this URL (replace with your store domain):**

```
https://ai-chat.support/api/integrations/shopify/upgrade?shop=ai-chat-support.myshopify.com
```

This will:
1. ✅ Request new permissions from Shopify
2. ✅ Keep your existing installation
3. ✅ Preserve all settings and data
4. ✅ Take ~30 seconds

**Steps:**
1. Click the upgrade link above (or paste in browser)
2. Review the new permissions Shopify shows
3. Click "Update app"
4. Done! 🎉

## Alternative: Full Reinstall

If the upgrade link doesn't work, do a full reinstall:

### 1. Uninstall Current App
1. Go to your Shopify admin: https://ai-chat-support.myshopify.com/admin
2. Navigate to **Settings** → **Apps and sales channels**
3. Find **AI Chat Support**
4. Click **Uninstall**

### 2. Reinstall with New Scopes
1. Go to: https://ai-chat.support/api/integrations/shopify/install?shop=ai-chat-support.myshopify.com
2. Click **Install**
3. Review the NEW permissions:
   - ✅ Write script tags (inject widget)
   - ✅ Read products (product queries)
   - ✅ Read orders (order tracking - will work after Shopify approval)
   - ✅ Read themes (detect theme colors)
4. Click **Install app**

## Quick Upgrade Link for Your Store

**Your personalized upgrade URL:**
```
https://ai-chat.support/api/integrations/shopify/upgrade?shop=ai-chat-support.myshopify.com
```

Copy and paste this in your browser → Done in 30 seconds! ⚡

## What Happens After Reinstall?

### Immediately Working:
✅ Product catalog queries  
✅ Theme color detection  
✅ Store information  
✅ Widget with matched colors  

### Pending Shopify Approval:
⏳ Order tracking (will show helpful message until approved)  
⏳ Order status queries  

### Your Data:
✅ Organization settings preserved  
✅ Chat history preserved  
✅ Widget customization preserved  
✅ Only the integration token gets updated  

## Timeline

1. **Reinstall**: 2 minutes
2. **Test non-order features**: Works immediately ✅
3. **Submit for Shopify review**: See SHOPIFY_APPROVAL_GUIDE.md
4. **Shopify approval**: 3-5 business days
5. **Order tracking**: Works after approval ✅

## Need Help?

If you encounter issues during reinstall, the system logs everything to:
- Laravel logs: `laravel/storage/logs/laravel.log`
- Look for: `[SHOPIFY]` tagged entries

---
**Updated**: October 27, 2025  
**Required Action**: Reinstall app to get new scopes
