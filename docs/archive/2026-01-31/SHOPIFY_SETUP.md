# Shopify App Configuration Guide

## ✅ Completed Steps

### 1. Environment Variables Added
The following Shopify credentials have been added to `.env`:

```env
SHOPIFY_API_KEY=e209ea490d1c4a8981ba790ecaf75ad8
SHOPIFY_API_SECRET=e373027d7961ce9576b8e5ed48efb8ac
```

### 2. Service Configuration
Already configured in `config/services.php`:
```php
'shopify' => [
    'key' => env('SHOPIFY_API_KEY'),
    'secret' => env('SHOPIFY_API_SECRET'),
],
```

### 3. Installation Page
Available at: **https://ai-chat.support/shopify/install**

### 4. API Routes
The following routes are active:
- `POST /api/integrations/register` - Initiate Shopify OAuth
- `GET /api/integrations/shopify/oauth/callback` - OAuth callback handler
- `POST /api/integrations/webhook/shopify` - Shopify webhooks
- `GET /api/integrations/widget-script/{org_id}` - Widget script delivery

---

## 🔧 Shopify Partner Dashboard Configuration

You need to configure the following in your Shopify App settings:

### App URLs
1. **App URL**: `https://ai-chat.support/shopify/install`
2. **Allowed redirection URL(s)**:
   ```
   https://ai-chat.support/api/integrations/shopify/oauth/callback
   ```

### Webhook URLs (if needed)
- **App uninstalled webhook**: `https://ai-chat.support/api/integrations/webhook/shopify`

### API Access Scopes
The app requests the following scopes (already configured in code):
- `read_products`
- `read_orders`
- `read_customers`
- `write_script_tags`

### App Distribution
- Set to **Public** or **Custom** depending on your needs
- Add app listing details, screenshots, and pricing if making it public

---

## 🚀 Installation Flow

### For Merchants:
1. Merchant visits: `https://ai-chat.support/shopify/install`
2. Enter their Shopify store URL (e.g., `mystore.myshopify.com`)
3. Click "Install App"
4. Redirected to Shopify OAuth consent page
5. After approval, redirected back to: `https://ai-chat.support/api/integrations/shopify/oauth/callback`
6. System automatically:
   - Creates/updates organization
   - Saves access token
   - Installs widget script via ScriptTag API
   - Widget appears on their storefront

### What Happens Behind the Scenes:
1. **Organization Creation**: Each Shopify store gets its own organization with 20,000 initial tokens
2. **Widget Injection**: A script tag is automatically created in Shopify pointing to:
   ```
   https://ai-chat.support/api/integrations/widget-script/{org_id}
   ```
3. **Widget Configuration**: Default settings applied (customizable later):
   - Position: bottom-right
   - Color: #007bff
   - Welcome message: "Hello! How can I help you today?"

---

## 🧪 Testing the Installation

### Test URL:
```
https://ai-chat.support/shopify/install
```

### Test with a Development Store:
1. Create a development store in your Shopify Partner account
2. Use that store's URL (e.g., `dev-store-123.myshopify.com`) on the install page
3. Verify the OAuth flow completes successfully
4. Check that the widget appears on the development store

### Verify in Database:
```sql
-- Check organization created
SELECT * FROM organizations WHERE website LIKE '%myshopify.com%';

-- Check integration saved
SELECT * FROM integrations WHERE provider = 'shopify';
```

---

## 🔍 Troubleshooting

### If OAuth fails:
1. Verify API credentials in Shopify Partner Dashboard match `.env`
2. Check redirect URL is exactly: `https://ai-chat.support/api/integrations/shopify/oauth/callback`
3. Review Laravel logs: `laravel/storage/logs/laravel.log`

### If widget doesn't appear:
1. Check ScriptTag creation logs
2. Verify the script URL is accessible: `https://ai-chat.support/api/integrations/widget-script/{org_id}`
3. Check browser console for JavaScript errors

### Check Logs:
```bash
tail -f /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -i shopify
```

---

## 📝 Next Steps

1. **Test the installation flow** with a development store
2. **Configure app listing** in Shopify Partner Dashboard (if making public)
3. **Set up app pricing** (if charging merchants)
4. **Add app review/listing details** for Shopify App Store submission
5. **Test uninstall webhook** to ensure proper cleanup

---

## 🔐 Security Notes

- Access tokens are securely stored in the `integrations` table
- Webhook verification should be implemented for production
- API credentials are environment-specific (never commit to git)
- Each merchant's data is isolated by organization

---

## 📞 Support

For issues or questions:
- Email: info@ai-chat.support
- Documentation: https://ai-chat.support/docs
