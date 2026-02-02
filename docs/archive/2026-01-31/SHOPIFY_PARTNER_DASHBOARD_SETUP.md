# Shopify Partner Dashboard - GDPR Webhook Configuration

## Quick Setup Guide (5 minutes)

### Access Your App Settings

1. Go to: https://partners.shopify.com/
2. Click **Apps** in the left sidebar
3. Select your app: **AI Chat Support**
4. Click **App setup** in the left navigation

### Configure Compliance Webhooks

Scroll down to the **"Compliance webhooks"** section. You'll see three required fields:

#### 1. Customer data request endpoint
**What to enter:**
```
https://ai-chat.support/shopify/webhooks
```

**What this does:**
- Shopify sends requests when customers ask for their data (GDPR Article 15)
- Header: `X-Shopify-Topic: customers/data_request`
- You have **30 days** to respond with customer data
- Our handler: `ShopifyWebhookController::handleCustomersDataRequest()`

---

#### 2. Customer data erasure endpoint
**What to enter:**
```
https://ai-chat.support/shopify/webhooks
```

**What this does:**
- Shopify sends requests to delete customer data (GDPR Article 17 "Right to be forgotten")
- Header: `X-Shopify-Topic: customers/redact`
- Triggered **48 hours** after customer requests deletion
- Our handler: `ShopifyWebhookController::handleCustomersRedact()`

---

#### 3. Shop data erasure endpoint
**What to enter:**
```
https://ai-chat.support/shopify/webhooks
```

**What this does:**
- Shopify sends requests to delete shop/merchant data after app uninstall
- Header: `X-Shopify-Topic: shop/redact`
- Triggered **48 hours** after shop uninstalls your app
- Our handler: `ShopifyWebhookController::handleShopRedact()`

---

### Save Changes

Click **"Save"** at the top or bottom of the page.

## What Happens Next

### Immediate Effects
- ✅ Automated checks will now find the configured webhook URLs
- ✅ "Provides mandatory compliance webhooks" check should PASS
- ✅ "Verifies webhooks with HMAC signatures" check should PASS (our endpoint is already ready)

### When Shopify Runs Automated Tests
You'll see in Laravel logs (`storage/logs/laravel.log`):

```
=== SHOPIFY WEBHOOK REQUEST RECEIVED ===
topic: "customers/data_request"
✅ HMAC VERIFIED SUCCESSFULLY
→ Routing to handleCustomersDataRequest
✅ customers/data_request webhook processed - Returning HTTP 200
```

## Verification Checklist

After saving in Partner Dashboard:

- [ ] All three compliance webhook URLs are set to: `https://ai-chat.support/shopify/webhooks`
- [ ] Clicked **Save** in Partner Dashboard
- [ ] Go to Partner Dashboard → Apps → Your App → **Overview**
- [ ] Click **"Run checks"** button
- [ ] Wait for automated tests to complete (~2 minutes)
- [ ] Verify both checks are green:
  - ✅ Provides mandatory compliance webhooks
  - ✅ Verifies webhooks with HMAC signatures

## Troubleshooting

### If checks still fail:

1. **Verify webhook URLs are saved**
   - Go back to App Setup → Compliance webhooks
   - Confirm all 3 fields show: `https://ai-chat.support/shopify/webhooks`
   - Make sure you clicked Save

2. **Check logs during test**
   ```bash
   cd /var/www/clients/client1/web64/web/laravel
   tail -f storage/logs/laravel.log | grep -i shopify
   ```
   - You should see incoming webhook requests from Shopify's test system
   - If you see `❌ HMAC VERIFICATION FAILED`, check that `SHOPIFY_API_SECRET` in `.env` matches the **Client secret** in Partner Dashboard → App Setup → App credentials

3. **Verify endpoint is publicly accessible**
   ```bash
   curl -I https://ai-chat.support/shopify/webhooks
   ```
   - Should return `405 Method Not Allowed` (GET not supported, POST required)
   - NOT 404 or 500

## Current Implementation Status

### ✅ Fully Implemented
- Single webhook endpoint for all topics
- HMAC signature verification (SHA-256, base64, timing-safe comparison)
- Request logging and debugging
- Fast 200 responses (< 5 seconds)
- Async processing for cleanup jobs
- CSRF exemption for webhook route

### ✅ Handler Methods Ready
- `handleAppUninstalled()` - Queues cleanup job, returns 200 immediately
- `handleCustomersDataRequest()` - Logs request, collects chat data, returns 200
- `handleCustomersRedact()` - Deletes customer chat conversations, returns 200
- `handleShopRedact()` - Deletes all org data including Qdrant, returns 200

### ⏳ Pending Action (Manual)
- Configure the 3 GDPR webhook URLs in Partner Dashboard (this document)
- Run automated checks after configuration

## Screenshots Guide

### Finding Compliance Webhooks Section

1. **Partner Dashboard Home**
   - URL: https://partners.shopify.com/
   - Click "Apps" in left sidebar

2. **Apps List**
   - Find "AI Chat Support"
   - Click on app name

3. **App Setup Page**
   - Look for "App setup" in left navigation
   - Scroll down to "Compliance webhooks" section
   - You'll see 3 input fields for webhook URLs

### What It Looks Like

```
Compliance webhooks
Configure your app to respond to compliance webhooks.

Customer data request endpoint *
[https://ai-chat.support/shopify/webhooks]

Customer data erasure endpoint *
[https://ai-chat.support/shopify/webhooks]

Shop data erasure endpoint *
[https://ai-chat.support/shopify/webhooks]

[Save]
```

## Timeline

1. **Before**: Automated checks fail because GDPR webhook URLs are not configured
2. **Configure**: Enter webhook URLs in Partner Dashboard (5 minutes)
3. **Save**: Click Save button
4. **Test**: Run automated checks in Partner Dashboard
5. **Verify**: Check Laravel logs for incoming test webhooks
6. **Success**: Both compliance checks pass ✅

## Support

If you encounter issues:
- Check Laravel logs: `storage/logs/laravel.log`
- Verify SHOPIFY_API_SECRET matches Partner Dashboard → App credentials → Client secret
- Confirm endpoint is accessible: `curl -X POST https://ai-chat.support/shopify/webhooks`
- Review Shopify documentation: https://shopify.dev/docs/apps/build/privacy-law-compliance

---

**Next Step**: Go to Partner Dashboard and configure the 3 compliance webhook URLs now!
