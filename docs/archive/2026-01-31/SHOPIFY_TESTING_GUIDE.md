# Shopify Installation Testing Guide

## ✅ Ready to Test!

Your Shopify app integration is now properly configured with comprehensive debug logging.

---

## 🔧 Shopify Partner Dashboard Setup (IMPORTANT)

Before testing, configure these in your Shopify Partner Dashboard:

### App URLs
1. **App URL**: 
   ```
   https://ai-chat.support/shopify/install
   ```

2. **Allowed redirection URL(s)**:
   ```
   https://ai-chat.support/api/integrations/shopify/oauth/callback
   ```

### API Scopes
Ensure these scopes are enabled:
- `read_products`
- `read_orders`
- `read_customers`
- `write_script_tags`

---

## 🚀 Testing Steps

### 1. Access the Installation Page
Navigate to:
```
https://ai-chat.support/shopify/install
```

You should see the Shopify installation page with:
- AI Chat Support branding
- Store domain input field
- "Install on Shopify" button

### 2. Enter Your Store Domain
In the input field, enter just your store name (without `.myshopify.com`):
```
your-test-store
```
Or the full domain:
```
your-test-store.myshopify.com
```

### 3. Click "Install on Shopify"
This will:
- Validate your input
- Call `/api/integrations/register` with provider=shopify
- Redirect to Shopify OAuth authorization page

### 4. Authorize the App
On Shopify's page, you'll see:
- Requested permissions
- "Install app" button

Click "Install app" to proceed.

### 5. OAuth Callback
After authorization, Shopify redirects to:
```
https://ai-chat.support/api/integrations/shopify/oauth/callback
```

The system will:
- Exchange code for access token
- Create/update organization
- Save integration record
- Create ScriptTag for widget injection
- Redirect to dashboard or return URL

---

## 📊 Debug Logs to Monitor

Watch the logs in real-time during installation:

```bash
# In terminal, run:
tail -f /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -i shopify
```

### Expected Log Flow

1. **Registration Attempt**:
```
Integration registration attempt {"provider":"shopify","shop":"your-store.myshopify.com",...}
```

2. **OAuth Initiation**:
```
Initiating Shopify OAuth {"shop":"your-store.myshopify.com","has_api_key":true,...}
Shopify OAuth URL generated {"shop":"...","state":"...","redirect_uri":"..."}
```

3. **OAuth Callback**:
```
Shopify OAuth callback received {"shop":"...","has_code":true,...}
Shopify OAuth state verified, exchanging code for token
Shopify access token obtained successfully
```

4. **Organization Creation**:
```
Created new organization for Shopify integration {"org_id":X,"org_slug":"...","shop":"..."}
```
OR
```
Using existing organization for Shopify integration {"existing_org_id":X,...}
```

5. **Integration Saved**:
```
Shopify integration record saved {"integration_id":X,"org_id":X,...}
Organization widget settings saved {"org_id":X,"settings":{...}}
```

6. **ScriptTag Creation**:
```
Creating Shopify ScriptTag {"shop":"...","org_id":X,"script_src":"..."}
Shopify ScriptTag created successfully {"shop":"...","script_tag_id":X,...}
```

7. **Completion**:
```
Shopify integration completed {"shop":"...","org_id":X,"script_tag_created":true}
```

---

## ❌ Common Errors & Solutions

### Error: "Invalid state parameter"
**Logs show**: `Shopify OAuth state mismatch`

**Solution**: 
- Clear browser cookies/session
- Start installation flow again from `/shopify/install`

### Error: "Failed to get access token"
**Logs show**: `Shopify token exchange failed`

**Solution**:
- Verify API credentials in `.env` match Shopify Partner Dashboard
- Check API secret is correct
- Ensure redirect URL in Shopify matches exactly

### Error: "Shopify API not configured"
**Logs show**: `Shopify OAuth failed - API key not configured`

**Solution**:
- Verify `.env` has:
  ```
  SHOPIFY_API_KEY=e209ea490d1c4a8981ba790ecaf75ad8
  SHOPIFY_API_SECRET=e373027d7961ce9576b8e5ed48efb8ac
  ```
- Restart PHP/Laravel if you just added them

### ScriptTag Creation Fails
**Logs show**: `Failed to create Shopify ScriptTag`

**Solution**:
- Check if `write_script_tags` permission is granted
- Verify the store is not on a trial/development plan that restricts ScriptTags
- Check Shopify API version (currently using `2025-01`)

---

## 🧪 Verification Checklist

After successful installation:

### 1. Check Database
```sql
-- Organization created
SELECT * FROM organizations WHERE website LIKE '%myshopify.com%' ORDER BY created_at DESC LIMIT 1;

-- Integration saved
SELECT * FROM integrations WHERE provider = 'shopify' ORDER BY created_at DESC LIMIT 1;
```

### 2. Check Widget Appears on Store
1. Visit your Shopify store frontend
2. Look for the AI Chat Support widget (usually bottom-right)
3. Click to open and test chat functionality

### 3. Verify ScriptTag in Shopify Admin
1. Go to Shopify Admin > Settings > Apps and sales channels
2. Click your app
3. Check if ScriptTag is listed (or use Shopify API to query)

---

## 🔍 Advanced Debugging

### Full Request/Response Logging
If you need more details, temporarily add to `IntegrationController.php`:

```php
// In shopifyCallback() after token exchange:
Log::debug('Shopify token response full', [
    'status' => $tokenResponse->status(),
    'headers' => $tokenResponse->headers(),
    'body' => $tokenResponse->body()
]);
```

### Check Session Data
```php
// In register() method:
Log::debug('Session check', [
    'session_id' => session()->getId(),
    'all_session_data' => session()->all()
]);
```

---

## 📞 Support

If you encounter issues:

1. **Check logs first** - Most issues are logged with details
2. **Verify Shopify Partner settings** - URLs must match exactly
3. **Test with development store** - Don't test on live stores initially
4. **Review API credentials** - Ensure they're correct in `.env`

---

## ✨ Next Steps After Successful Test

1. ✅ Verify widget appears and functions on test store
2. ✅ Test uninstall flow (if webhook configured)
3. ✅ Customize widget appearance per organization
4. ✅ Upload product data to organization's knowledge base
5. ✅ Submit app for Shopify App Store review (if going public)

---

## 🎉 Success Indicators

You'll know it worked when:
- ✅ No errors in logs
- ✅ Organization created in database
- ✅ Integration record saved with access token
- ✅ ScriptTag created successfully
- ✅ Widget visible on your Shopify store
- ✅ Chat widget responds to messages

Ready to test! 🚀
