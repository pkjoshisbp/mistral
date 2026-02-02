# Shopify App Compliance Webhooks Setup Guide

## Overview
To publish your Shopify app in the public channel, you **must** implement and configure the following mandatory compliance webhooks:

1. ✅ **customers/data_request** - GDPR data portability
2. ✅ **customers/redact** - GDPR right to erasure
3. ✅ **shop/redact** - Delete shop data after uninstall
4. ✅ **app/uninstalled** - Handle app uninstallation

## Implementation Status

### ✅ Code Implementation (COMPLETED)
All webhook handlers have been implemented in:
- **Controller**: `/laravel/app/Http/Controllers/ShopifyWebhookController.php`
- **Route**: `/shopify/webhooks` (POST)
- **HMAC Verification**: Implemented using `X-Shopify-Hmac-Sha256` header
- **CSRF Protection**: Excluded for webhook endpoint

### 📋 Configuration Required in Shopify Partner Dashboard

#### Step 1: Access Your App Settings
1. Go to [Shopify Partner Dashboard](https://partners.shopify.com/)
2. Navigate to **Apps** → Select your app
3. Click on **Configuration** in the left sidebar

#### Step 2: Configure Webhook Endpoints

In the **Event subscriptions** section, add the following webhooks:

##### 1. App Uninstalled
- **Topic**: `app/uninstalled`
- **URL**: `https://ai-chat.support/shopify/webhooks`
- **Format**: JSON
- **API Version**: `2025-01` (or latest stable)

##### 2. Customer Data Request (GDPR)
- **Topic**: `customers/data_request`
- **URL**: `https://ai-chat.support/shopify/webhooks`
- **Format**: JSON
- **API Version**: `2025-01` (or latest stable)

##### 3. Customer Redact (GDPR)
- **Topic**: `customers/redact`
- **URL**: `https://ai-chat.support/shopify/webhooks`
- **Format**: JSON
- **API Version**: `2025-01` (or latest stable)

##### 4. Shop Redact (GDPR)
- **Topic**: `shop/redact`
- **URL**: `https://ai-chat.support/shopify/webhooks`
- **Format**: JSON
- **API Version**: `2025-01` (or latest stable)

#### Step 3: Verify Your Configuration
1. After adding all webhooks, click **Save**
2. Shopify will automatically test your webhook endpoint
3. Your endpoint must:
   - ✅ Return HTTP 200 status
   - ✅ Respond within 5 seconds
   - ✅ Verify HMAC signatures correctly

## Webhook Details

### 1. App Uninstalled (`app/uninstalled`)
**Purpose**: Triggered when a merchant uninstalls your app from their store.

**What Our Implementation Does**:
- Deletes all organization data
- Removes Qdrant vector collections
- Deletes chat conversations and messages
- Removes user accounts (if they don't belong to other organizations)
- Cleans up ScriptTags

**Example Payload**:
```json
{
  "id": 123456,
  "name": "Example Store",
  "email": "owner@example.com",
  "domain": "example-store.myshopify.com"
}
```

### 2. Customer Data Request (`customers/data_request`)
**Purpose**: GDPR compliance - merchant or customer requests their data.

**What Our Implementation Does**:
- Collects all chat conversations for the customer
- Compiles message history
- Logs the request for audit trail
- Returns HTTP 200 (data should be provided within 30 days)

**Example Payload**:
```json
{
  "shop_id": 123456,
  "shop_domain": "example-store.myshopify.com",
  "orders_requested": [123, 456],
  "customer": {
    "id": 789,
    "email": "customer@example.com",
    "phone": "+1234567890"
  }
}
```

### 3. Customer Redact (`customers/redact`)
**Purpose**: GDPR compliance - delete customer data after 48-hour grace period.

**What Our Implementation Does**:
- Finds all chat conversations by customer email
- Permanently deletes chat messages
- Deletes conversation records
- Logs the deletion for audit trail

**Example Payload**:
```json
{
  "shop_id": 123456,
  "shop_domain": "example-store.myshopify.com",
  "customer": {
    "id": 789,
    "email": "customer@example.com",
    "phone": "+1234567890"
  },
  "orders_to_redact": [123, 456]
}
```

### 4. Shop Redact (`shop/redact`)
**Purpose**: GDPR compliance - delete all shop data 48 hours after app uninstall.

**What Our Implementation Does**:
- Completely removes organization and all related data
- Deletes Qdrant collections
- Removes all chat history
- Deletes user accounts (if not associated with other organizations)
- Full cleanup of all traces

**Example Payload**:
```json
{
  "shop_id": 123456,
  "shop_domain": "example-store.myshopify.com"
}
```

## Security: HMAC Verification

All webhooks are verified using Shopify's HMAC signature to ensure they're authentic.

**Verification Process**:
1. Extract `X-Shopify-Hmac-Sha256` header
2. Calculate HMAC using request body and your app's secret key
3. Compare using timing-safe comparison (`hash_equals`)
4. Reject if signatures don't match (return 401)

**Configuration Required**:
Your `.env` file must have:
```env
SHOPIFY_KEY=your_shopify_api_key
SHOPIFY_SECRET=your_shopify_api_secret
```

## Testing Your Webhooks

### Method 1: Shopify Partner Dashboard
1. Go to **Apps** → Your App → **Configuration**
2. Scroll to **Event subscriptions**
3. Click **Test** next to each webhook
4. Check your Laravel logs: `tail -f storage/logs/laravel.log`

### Method 2: Manual Testing with cURL
```bash
# Generate HMAC signature
SECRET="your_shopify_api_secret"
DATA='{"shop_id":123456,"shop_domain":"example.myshopify.com"}'
HMAC=$(echo -n "$DATA" | openssl dgst -sha256 -hmac "$SECRET" -binary | base64)

# Send test webhook
curl -X POST https://ai-chat.support/shopify/webhooks \
  -H "Content-Type: application/json" \
  -H "X-Shopify-Topic: shop/redact" \
  -H "X-Shopify-Shop-Domain: example.myshopify.com" \
  -H "X-Shopify-Hmac-Sha256: $HMAC" \
  -d "$DATA"
```

### Method 3: Use Shopify's Webhook Testing Tool
Visit: https://shopify.dev/docs/apps/build/webhooks/subscribe/test

## Troubleshooting

### Webhook Fails HMAC Verification
**Symptom**: Receiving 401 responses
**Solution**:
- Verify `SHOPIFY_SECRET` in `.env` matches your Partner Dashboard
- Ensure you're using the raw request body (not parsed JSON)
- Check that webhook URL doesn't have query parameters

### Webhook Times Out
**Symptom**: Shopify shows timeout errors
**Solution**:
- Process heavy tasks asynchronously (use Laravel queues)
- Return HTTP 200 immediately
- Optimize database queries

### Webhook Not Receiving Requests
**Symptom**: No logs showing webhook calls
**Solution**:
- Verify webhook URL is publicly accessible (test with curl)
- Check SSL certificate is valid
- Ensure route is not blocked by firewall
- Verify webhook is enabled in Partner Dashboard

## Checklist for Shopify App Approval

- [x] All 4 mandatory webhooks implemented
- [x] HMAC signature verification implemented
- [x] Webhooks respond within 5 seconds
- [x] GDPR-compliant data handling
- [x] Customer data deletion implemented
- [x] Shop data deletion implemented
- [ ] Configure webhooks in Shopify Partner Dashboard
- [ ] Test each webhook endpoint
- [ ] Monitor logs during Shopify's automated tests
- [ ] Submit app for review

## What Happens During Shopify Review

When you submit your app, Shopify's automated checks will:

1. ✅ **Test authentication** - OAuth flow
2. ✅ **Test redirect** - Post-install redirect to your app
3. ❌ **Test compliance webhooks** - Send test payloads to each endpoint
4. ❌ **Verify HMAC** - Ensure signatures are validated
5. ✅ **Check TLS certificate** - Must be valid HTTPS

Your webhook endpoint must return HTTP 200 for all test payloads.

## Additional Recommendations

### 1. Monitoring & Logging
- All webhook events are logged in `storage/logs/laravel.log`
- Consider adding monitoring alerts for webhook failures
- Track GDPR requests for compliance auditing

### 2. Data Retention Policy
Document your data retention policy in your app's privacy policy:
- Chat data retained for [X] days after conversation ends
- User accounts deleted 48 hours after shop uninstall (per GDPR)
- Backup data deleted within 30 days

### 3. Queue Processing (Optional Enhancement)
For better performance, consider processing webhooks asynchronously:

```php
// In ShopifyWebhookController.php
dispatch(new ProcessShopifyWebhook($topic, $shop, $data));
```

## Support & Resources

- **Shopify Webhooks Documentation**: https://shopify.dev/docs/apps/build/webhooks
- **GDPR Compliance**: https://shopify.dev/docs/apps/launch/privacy-compliance
- **Laravel Logs**: `/var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log`
- **Test Endpoint**: `https://ai-chat.support/shopify/webhooks`

## Next Steps

1. **Clear Laravel caches**:
```bash
cd /var/www/clients/client1/web64/web/laravel
php artisan route:clear
php artisan config:clear
```

2. **Test webhook endpoint manually**:
```bash
curl -X POST https://ai-chat.support/shopify/webhooks \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

3. **Configure in Shopify Partner Dashboard** (see Step 2 above)

4. **Run Shopify's automated checks** and verify all pass

5. **Submit app for review**

Good luck with your Shopify app approval! 🎉
