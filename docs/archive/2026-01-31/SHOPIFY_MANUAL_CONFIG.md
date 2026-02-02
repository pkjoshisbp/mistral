# 🎯 FIX: Shopify Automated Checks Failing - Manual Webhook Configuration

## The Problem

Your automated checks are failing because:
- ❌ Shopify's bot can't find registered webhooks
- ❌ Dynamic registration happens AFTER OAuth (so bot doesn't see them)
- ❌ Bot expects webhooks to be pre-configured in Partner Dashboard

## The Solution

**You need BOTH approaches:**
1. ✅ **Manual configuration in Partner Dashboard** (for automated checks to pass)
2. ✅ **Dynamic registration in code** (for actual merchant installations)

---

## 📋 Step-by-Step: Configure in Partner Dashboard

### Step 1: Go to Your App Settings

1. Visit: https://partners.shopify.com/
2. Click: **Apps** in the left sidebar
3. Find your app and click on it
4. Click: **Configuration** tab

### Step 2: Scroll to "Webhooks" Section

Look for a section called:
- **"Webhooks"** or
- **"Event subscriptions"** or  
- **"Compliance webhooks"** or
- **"GDPR webhooks"**

### Step 3: Add Each Webhook Manually

For **EACH** of the 4 webhooks below, click **"Add webhook"** or **"Subscribe to webhook"**:

#### Webhook 1: App Uninstalled
```
Event/Topic:     app/uninstalled
Webhook URL:     https://ai-chat.support/shopify/webhooks
Format:          JSON
API Version:     2025-01
```

#### Webhook 2: Customer Data Request
```
Event/Topic:     customers/data_request
Webhook URL:     https://ai-chat.support/shopify/webhooks
Format:          JSON
API Version:     2025-01
```

#### Webhook 3: Customer Redact
```
Event/Topic:     customers/redact
Webhook URL:     https://ai-chat.support/shopify/webhooks
Format:          JSON
API Version:     2025-01
```

#### Webhook 4: Shop Redact
```
Event/Topic:     shop/redact
Webhook URL:     https://ai-chat.support/shopify/webhooks
Format:          JSON
API Version:     2025-01
```

### Step 4: Save Configuration

1. Click **"Save"** button at the bottom of the page
2. Shopify will automatically test each webhook
3. You should see ✅ green checkmarks next to each webhook

### Step 5: Verify Webhooks Work

After saving, Shopify will send test payloads. Check your logs:

```bash
cd /var/www/clients/client1/web64/web/laravel
tail -f storage/logs/laravel.log | grep -i shopify
```

You should see:
```
[INFO] Shopify webhook received - topic: app/uninstalled
[INFO] Shopify webhook received - topic: customers/data_request
[INFO] Shopify webhook received - topic: customers/redact
[INFO] Shopify webhook received - topic: shop/redact
```

---

## 🔍 Where to Find Webhook Configuration

The location varies depending on your Partner Dashboard version:

### Option A: Configuration Tab → Webhooks Section
```
Partner Dashboard
  └─ Apps
      └─ [Your App]
          └─ Configuration (tab)
              └─ Webhooks (section - scroll down)
                  └─ [Add webhook button]
```

### Option B: API Access → Webhooks
```
Partner Dashboard  
  └─ Apps
      └─ [Your App]
          └─ API access (tab)
              └─ Event subscriptions (section)
                  └─ [Add subscription button]
```

### Option C: App Setup → Webhooks
```
Partner Dashboard
  └─ Apps
      └─ [Your App]
          └─ App setup (tab)
              └─ Webhooks (section)
                  └─ [Configure webhooks button]
```

**Can't find it?**
- Try searching for "webhook" or "GDPR" on the page (Ctrl+F)
- Look for sections about "Compliance" or "Event subscriptions"
- Check if there's a **"Set up"** or **"Get started"** button you need to click first

---

## 🎯 Alternative: Use Shopify CLI (If You Have Access)

If you have Shopify CLI installed, you can push the TOML config:

```bash
cd /var/www/clients/client1/web64/web

# Install Shopify CLI (if not installed)
npm install -g @shopify/cli @shopify/app

# Log in to Shopify
shopify auth login

# Push webhooks configuration
shopify app config push
```

This will read the `shopify.app.toml` file we created and configure webhooks automatically.

---

## 🐛 Troubleshooting

### Issue 1: "Can't find webhooks section"

**Possible reasons:**
- Your app might be using an older Partner Dashboard UI
- You might need to enable "App distribution" first
- Webhooks might be under a different section name

**Try this:**
1. Go to: **Apps** → **Your App** → **Distribution**
2. Make sure **"Make this app available on the Shopify App Store"** is enabled
3. Go back to **Configuration** tab
4. Webhooks section should now be visible

### Issue 2: "Webhook URL validation fails"

**Check:**
- ✅ URL starts with `https://` (not `http://`)
- ✅ URL is publicly accessible (not localhost)
- ✅ SSL certificate is valid
- ✅ Route is registered: `/shopify/webhooks`
- ✅ CSRF exception is configured

**Test manually:**
```bash
curl -X POST https://ai-chat.support/shopify/webhooks \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: app/uninstalled" \
  -d '{"shop_domain":"test.myshopify.com"}'
```

Should return: HTTP 200 or 401 (not 404!)

### Issue 3: "Automated checks still failing"

**After configuring webhooks, you need to:**
1. Go back to app submission page
2. Click **"Run checks"** or **"Re-run automated tests"**
3. Wait for checks to complete (can take 1-2 minutes)
4. All compliance checks should now pass ✅

---

## 📊 What the Automated Checker Does

When you click "Run checks" in Partner Dashboard:

```
Step 1: Shopify Bot Checks App Configuration
  ├─ Looks for registered webhooks in Partner Dashboard ✅
  ├─ Verifies all 4 compliance webhooks are present
  └─ Checks webhook URLs are valid HTTPS

Step 2: Shopify Bot Tests OAuth Flow
  ├─ Initiates OAuth on test shop
  ├─ Completes authorization
  └─ Verifies redirect works

Step 3: Shopify Bot Tests Webhooks
  ├─ Sends test payload to app/uninstalled webhook
  ├─ Sends test payload to customers/data_request webhook
  ├─ Sends test payload to customers/redact webhook
  └─ Sends test payload to shop/redact webhook

Step 4: Verifies Responses
  ├─ Checks each webhook returns HTTP 200
  ├─ Verifies response time < 5 seconds
  └─ Tests HMAC verification (sends invalid HMAC, expects 401)

✅ All checks pass → App ready for review!
```

---

## 💡 Why You Need Both (Manual + Dynamic)

### Manual Configuration in Partner Dashboard:
- ✅ **For automated checks** - Shopify's bot can see them
- ✅ **For app review** - Human reviewers verify configuration
- ✅ **For initial approval** - Required to pass submission checks

### Dynamic Registration in Code:
- ✅ **For real merchants** - Webhooks work when they install
- ✅ **For reliability** - No manual errors per merchant
- ✅ **For scale** - Works for unlimited installs

**Both together = Perfect setup!** ✅

---

## ✅ Checklist

Before running automated checks again:

- [ ] All 4 webhooks configured in Partner Dashboard
- [ ] Each webhook shows ✅ green checkmark
- [ ] Test webhook endpoint manually (should return 200 or 401)
- [ ] Dynamic registration code is in place (already done)
- [ ] Laravel logs show webhook receptions
- [ ] Click "Run checks" in Partner Dashboard
- [ ] Wait for all automated checks to pass
- [ ] Submit app for review!

---

## 🎯 Quick Copy-Paste Reference

When adding webhooks in Partner Dashboard, copy these values:

```
Webhook URL (same for all 4):
https://ai-chat.support/shopify/webhooks

Topics (add each separately):
1. app/uninstalled
2. customers/data_request
3. customers/redact
4. shop/redact

Format: JSON
API Version: 2025-01
```

---

## 📞 Still Having Issues?

### Check Partner Dashboard Status

1. Go to: **Apps** → **Your App**
2. Look for status indicators:
   - ❌ Red = Issues to fix
   - ⚠️ Yellow = Warnings
   - ✅ Green = All good

3. Click on any ❌ or ⚠️ to see details

### Contact Shopify Support

If you absolutely can't find webhook configuration:
- Go to: https://partners.shopify.com/organizations
- Click: **Get support**
- Ask: "Where do I configure compliance webhooks for my app?"

---

## 🎉 Next Steps

1. **Configure webhooks in Partner Dashboard** (do this NOW!)
2. **Run automated checks** (should pass after webhook config)
3. **Test installation** on development store
4. **Verify dynamic registration works** (check logs)
5. **Submit for review** (all checks should be green)

---

**The key insight:** Shopify's automated checker looks at **Partner Dashboard configuration**, not at what happens during actual installations. You need manual configuration for the bot to see them! 🤖✅

