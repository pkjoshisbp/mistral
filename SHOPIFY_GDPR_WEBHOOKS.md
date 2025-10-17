# Shopify GDPR Webhook Configuration Guide

## Problem Identified (Oct 14, 2025)

When attempting to register GDPR compliance webhooks via the Admin API, we receive **404 errors**:

```
Failed to register Shopify webhook: customers/data_request
Status: 404
Response: "Could not find the webhook topic customers/data_request"
```

## Root Cause

**GDPR compliance webhooks CANNOT be registered via the Admin API.** They must be configured in the Shopify Partner Dashboard under **App Setup → Compliance webhooks**.

### Why This Happens

1. GDPR topics (`customers/data_request`, `customers/redact`, `shop/redact`) are **special compliance webhooks**
2. Shopify requires developers to manually configure these in the Partner Dashboard
3. The Admin API `/webhooks.json` endpoint only supports standard webhook topics
4. The automated checker verifies that these URLs are configured in the Partner Dashboard, NOT via API

## Solution: Configure in Partner Dashboard

### Step 1: Access Partner Dashboard

1. Go to https://partners.shopify.com/
2. Navigate to **Apps** → Select your app (`AI Chat Support`)
3. Click **App setup** in the left sidebar

### Step 2: Configure Compliance Webhooks

Scroll down to the **Compliance webhooks** section and configure:

#### Customer data request endpoint
- **URL**: `https://ai-chat.support/shopify/webhooks`
- **Topic**: Will be sent via `X-Shopify-Topic: customers/data_request` header
- Shopify requires you to respond within **30 days** with customer data

#### Customer data erasure endpoint  
- **URL**: `https://ai-chat.support/shopify/webhooks`
- **Topic**: Will be sent via `X-Shopify-Topic: customers/redact` header
- Triggered 48 hours after customer requests deletion

#### Shop data erasure endpoint
- **URL**: `https://ai-chat.support/shopify/webhooks`
- **Topic**: Will be sent via `X-Shopify-Topic: shop/redact` header
- Triggered 48 hours after shop uninstalls your app

### Step 3: Save Changes

Click **Save** in the Partner Dashboard. Shopify will now send these webhooks to your endpoint.

## What About app/uninstalled?

The `app/uninstalled` topic **CAN** be registered via the Admin API (and we do this automatically). This is why it succeeded:

```
✅ Shopify webhook registered successfully
topic: "app/uninstalled"  
webhook_id: 1855786680619
```

## Code Changes Needed

### Update Dynamic Registration (IntegrationController.php)

We should:
1. Only attempt to register `app/uninstalled` via API
2. Log a reminder to configure GDPR webhooks in Partner Dashboard
3. Remove the 404 warnings since they're expected behavior

### Updated registerShopifyWebhooks() Method

```php
private function registerShopifyWebhooks($shop, $accessToken)
{
    // Only register non-GDPR webhooks via API
    // GDPR webhooks MUST be configured in Partner Dashboard
    $apiWebhooks = [
        [
            'topic' => 'app/uninstalled',
            'address' => config('app.url') . '/shopify/webhooks',
            'format' => 'json'
        ]
    ];

    // Log reminder about GDPR webhooks
    Log::info('GDPR webhooks must be configured in Partner Dashboard', [
        'shop' => $shop,
        'required_topics' => ['customers/data_request', 'customers/redact', 'shop/redact'],
        'dashboard_url' => 'https://partners.shopify.com/',
        'endpoint' => config('app.url') . '/shopify/webhooks'
    ]);

    // Register API-supported webhooks...
}
```

## Verification Steps

### After Configuring in Partner Dashboard:

1. **Check Automated Tests**
   - Partner Dashboard → Apps → Your App → Overview
   - Click "Run checks" 
   - Both compliance checks should now pass:
     - ✅ Provides mandatory compliance webhooks
     - ✅ Verifies webhooks with HMAC signatures

2. **Monitor Logs During Tests**
   ```bash
   tail -f storage/logs/laravel.log | grep -i shopify
   ```

3. **Verify Webhook Delivery**
   - Shopify will send test webhooks during automated checks
   - You should see in logs:
     - `SHOPIFY WEBHOOK REQUEST RECEIVED`
     - `HMAC VERIFIED SUCCESSFULLY`
     - `customers/data_request webhook processed - Returning HTTP 200`

## Current Status

### ✅ Working
- Webhook endpoint: `https://ai-chat.support/shopify/webhooks`
- HMAC verification (tested with manual signed requests)
- CSRF exemption for webhook route
- Handler methods for all 4 topics
- `app/uninstalled` registered successfully via API

### ⚠️ Requires Manual Action
- Configure GDPR webhook URLs in Partner Dashboard
- All 3 topics must point to: `https://ai-chat.support/shopify/webhooks`

### ✅ Ready for Testing
- Once GDPR webhooks are configured in dashboard, re-run automated checks
- Our endpoint will handle all 4 topics correctly
- HMAC verification will pass

## References

- [Shopify GDPR Webhooks Documentation](https://shopify.dev/docs/apps/build/privacy-law-compliance)
- [Mandatory Webhooks for Public Apps](https://shopify.dev/docs/apps/build/privacy-law-compliance#mandatory-webhooks)
- [Partner Dashboard App Setup](https://partners.shopify.com/)

## Next Steps

1. ✅ App installed on `ai-chat-support.myshopify.com`
2. ✅ `app/uninstalled` webhook registered via API
3. ⏳ **Configure GDPR webhooks in Partner Dashboard** (you must do this manually)
4. ⏳ Run automated checks in Partner Dashboard
5. ⏳ Monitor logs to confirm webhook delivery and HMAC verification

---

**Bottom Line**: The 404 errors are expected. GDPR webhooks are intentionally excluded from the Admin API and must be configured in the Partner Dashboard's **App Setup → Compliance webhooks** section.
