# Onboarding Screenshots Guide

## 📸 Required Screenshots for Shopify App Approval

To fully comply with Shopify's requirement **5.1.2.3 - Include detailed onboarding instructions**, you need to add 5 screenshots showing merchants how to use the app embed.

---

## Screenshot Locations

All screenshots should be placed in:
```
/laravel/public/images/onboarding/
```

The onboarding page will automatically display them when present. If images are missing, it shows descriptive text instead.

---

## Required Screenshots & How to Take Them

### 1. **step1-themes.png**
**What to capture:** Shopify Admin showing Themes page with Customize button

**How to take it:**
1. Log into your Shopify development store admin
2. Go to **Online Store** → **Themes**
3. Take a screenshot showing:
   - The left navigation menu with "Online Store" highlighted
   - The Themes page with your active theme
   - The **"Customize"** button clearly visible

**Recommended size:** 1200x800px  
**File format:** PNG or JPG

---

### 2. **step2-app-embeds.png**
**What to capture:** Theme editor showing App Embeds panel with AI Chat Support

**How to take it:**
1. Click **Customize** on your theme
2. In the theme editor, click the **App embeds** icon (puzzle piece icon) in left sidebar
3. Make sure **"AI Chat Support"** is visible in the list
4. Take a screenshot showing:
   - The App embeds panel open
   - "AI Chat Support" in the list
   - The toggle switch (either ON or OFF position)

**Recommended size:** 1200x800px  
**File format:** PNG or JPG

**Important:** This is the MOST critical screenshot for Shopify approval

---

### 3. **step3-save.png**
**What to capture:** Theme editor showing the Save button

**How to take it:**
1. In the theme editor (top bar)
2. Take a screenshot showing:
   - The **Save** button in the top right corner
   - Can show the full theme editor interface

**Recommended size:** 1200x800px  
**File format:** PNG or JPG

---

### 4. **toggle-widget.png**
**What to capture:** Close-up of the toggle switch for enabling/disabling

**How to take it:**
1. In the theme editor, open **App embeds**
2. Zoom in or crop to show:
   - "AI Chat Support" name
   - The toggle switch clearly
   - Maybe show both ON and OFF states (composite image)

**Recommended size:** 800x400px  
**File format:** PNG or JPG

---

### 5. **widget-settings.png**
**What to capture:** Settings panel for AI Chat Support

**How to take it:**
1. In the theme editor, with App embeds open
2. Click on **"AI Chat Support"** (not just the toggle, click the name)
3. The settings panel should appear on the right side
4. Take a screenshot showing:
   - The settings panel with all customization options
   - Color pickers, position settings, text fields, etc.

**Recommended size:** 1200x800px  
**File format:** PNG or JPG

---

### 6. **widget-preview.png** (Optional but recommended)
**What to capture:** The chat widget visible on your storefront

**How to take it:**
1. After enabling the widget, visit your store URL
2. Show the chat widget button in the corner
3. Optionally show it opened with a sample conversation
4. Take a screenshot showing:
   - Your store page with the widget visible
   - Preferably a product page or homepage

**Recommended size:** 1200x800px  
**File format:** PNG or JPG

---

## Screenshot Requirements

### Quality Standards:
- **Resolution:** Minimum 1200x800px (high DPI preferred)
- **Format:** PNG (for UI screenshots) or JPG
- **Size:** Keep under 500KB per image
- **Clarity:** Text must be readable, no blurry images
- **Annotations:** Add arrows or highlights if needed (optional)

### What to Show:
✅ Clear UI elements (buttons, toggles, menus)  
✅ Readable text  
✅ Your app name "AI Chat Support" visible  
✅ Professional appearance  

### What to Avoid:
❌ Blurry or low-resolution images  
❌ Personal/sensitive information visible  
❌ Test data that looks unprofessional  
❌ Browser dev tools open  

---

## How to Upload Screenshots

### Option 1: Direct Upload (Recommended)
```bash
# Using SCP or SFTP, upload to:
/var/www/clients/client1/web64/web/laravel/public/images/onboarding/

# Or via file manager if available
```

### Option 2: Base64 Embed (If images are small)
You can convert images to base64 and embed directly in the blade file.

---

## Verification Checklist

Before resubmitting to Shopify, verify:

- [ ] All 5 required screenshots are present
- [ ] Images display correctly on the onboarding page
- [ ] Screenshots show your app name "AI Chat Support"
- [ ] Images are high quality and readable
- [ ] File sizes are optimized (< 500KB each)
- [ ] Screenshots accurately represent the current app version

---

## Testing the Onboarding Page

Visit:
```
https://ai-chat.support/shopify/onboarding?shop=YOUR-STORE.myshopify.com
```

All images should display. If an image is missing, you'll see placeholder text instead.

---

## Alternative: Video Tutorial

Instead of (or in addition to) screenshots, you can create a short video:

**Video Requirements:**
- Length: 1-3 minutes
- Format: MP4, WebM
- Host on: YouTube, Vimeo, or Shopify's CDN
- Show: Complete installation and activation process

If you prefer video, embed it in the onboarding page at the top of Step 2.

---

## Quick Start

1. Install app in development store
2. Enable widget in theme editor
3. Take screenshots following the guide above
4. Upload to `/laravel/public/images/onboarding/`
5. Test the onboarding page
6. Deploy new version (if needed)
7. Resubmit to Shopify

---

## Status

**Current:** Placeholders added, waiting for screenshots  
**Next:** Take 5 screenshots and upload  
**Deploy:** Version 9 with screenshots (optional - images don't require new app version)

Images are served from the `public` directory, so no deployment is needed after uploading - they'll appear immediately.
