# 🔐 Shopify App Complete Configuration Backup
**Date**: October 14, 2025  
**Purpose**: Full backup before deleting and recreating app to remove sales channel  
**Current App**: ai-chat-support (automated checks ✅ PASSED)

---

## 📋 Current App Details

### Partner Dashboard Information
- **App Name**: ai-chat-support
- **Client ID**: `e209ea490d1c4a8981ba790ecaf75ad8`
- **API Secret**: `e373027d7961ce9576b8e5ed48efb8ac`
- **Organization**: AI Chat Support
- **Current Active Version**: web-6 (RELEASED)
- **Total Versions Created**: 6 (web-6, web-5, web-4, 2.0.0, 1.0.0, ai-chat-support-1)

### Automated Checks Status
- ✅ Immediately authenticates after install
- ✅ Immediately redirects to app UI after authentication
- ✅ Provides mandatory compliance webhooks
- ✅ Verifies webhooks with HMAC signatures
- ✅ Uses a valid TLS certificate

### Distribution Settings (CURRENT - TO BE CHANGED)
- **Distribution Method**: Sales Channel ❌ (This is what we're removing)
- **App Listing Status**: Draft (not published)
- **Access to Protected Customer Data**: No

---

## 📄 shopify.app.toml (COMPLETE BACKUP)

```toml
# Learn more about configuring your app at https://shopify.dev/docs/apps/tools/cli/configuration

client_id = "e209ea490d1c4a8981ba790ecaf75ad8"
name = "ai-chat-support"
application_url = "https://ai-chat.support/shopify/install"
embedded = true

[webhooks]
api_version = "2025-01"

  [[webhooks.subscriptions]]
  topics = ["app/uninstalled"]
  uri = "https://ai-chat.support/shopify/webhooks"

[webhooks.privacy_compliance]
customer_deletion_url = "https://ai-chat.support/shopify/webhooks"
customer_data_request_url = "https://ai-chat.support/shopify/webhooks"
shop_deletion_url = "https://ai-chat.support/shopify/webhooks"

[access_scopes]
# Learn more at https://shopify.dev/docs/apps/tools/cli/configuration#access_scopes
scopes = "write_script_tags"
optional_scopes = [ ]
use_legacy_install_flow = false

[auth]
redirect_urls = [
  "https://ai-chat.support/api/integrations/shopify/oauth/callback"
]

[app_preferences]
url = "https://ai-chat.support/shopify/preferences"
```

---

## 🔧 Laravel Configuration

### .env Settings (Shopify Section)
```bash
# Shopify App Credentials
SHOPIFY_API_KEY=e209ea490d1c4a8981ba790ecaf75ad8
SHOPIFY_API_SECRET=e373027d7961ce9576b8e5ed48efb8ac
```

### config/services.php
```php
'shopify' => [
    'key' => env('SHOPIFY_API_KEY'),
    'secret' => env('SHOPIFY_API_SECRET'),
],
```

### Routes Configuration
```php
// Public OAuth routes
Route::get('/shopify/install', \App\Livewire\Public\ShopifyInstall::class)->name('shopify.install');
Route::get('/shopify/complete-setup', \App\Livewire\Public\ShopifyCompleteSetup::class)->name('shopify.complete-setup');
Route::get('/shopify/preferences', \App\Livewire\Shopify\Preferences::class)->name('shopify.preferences');

// API routes
Route::get('/api/integrations/shopify/oauth/callback', [IntegrationController::class, 'shopifyCallback'])->name('api.integrations.shopify.oauth.callback');
Route::post('/api/integrations/webhook/shopify', [IntegrationController::class, 'shopifyWebhook'])->name('api.integrations.shopify.webhook');
Route::post('/shopify/webhooks', [ShopifyWebhookController::class, 'handle'])->name('shopify.webhooks');
```

---

## 🌐 URL Configuration

### Base URLs
- **App URL**: `https://ai-chat.support`
- **Application URL (Install)**: `https://ai-chat.support/shopify/install`
- **OAuth Callback**: `https://ai-chat.support/api/integrations/shopify/oauth/callback`
- **Webhook Endpoint**: `https://ai-chat.support/shopify/webhooks`
- **Preferences URL**: `https://ai-chat.support/shopify/preferences`

### Test Shop
- **Domain**: ai-chat-support.myshopify.com
- **Currently Installed**: Yes (Integration ID: 7, Org ID: 16)

---

## 🔌 Webhook Configuration

### Regular Webhooks
| Topic | URI | Registration Method |
|-------|-----|---------------------|
| `app/uninstalled` | https://ai-chat.support/shopify/webhooks | Admin API + TOML |

### Privacy Compliance Webhooks
| Topic | URI | Configuration |
|-------|-----|---------------|
| `customers/data_request` | https://ai-chat.support/shopify/webhooks | TOML privacy_compliance |
| `customers/redact` | https://ai-chat.support/shopify/webhooks | TOML privacy_compliance |
| `shop/redact` | https://ai-chat.support/shopify/webhooks | TOML privacy_compliance |

### Webhook Handling
- **Controller**: `app/Http/Controllers/ShopifyWebhookController.php`
- **Verification**: HMAC SHA-256 with timing-safe comparison
- **Processing**: Async via jobs (CleanupShopifyUninstall, etc.)
- **Response Time**: < 100ms (immediate HTTP 200)
- **Logging**: Comprehensive logs in `storage/logs/laravel.log`

---

## 🔐 Access Scopes

### Current Scopes
```
write_script_tags
```

### Why We Need This
- **ScriptTag injection**: To inject chat widget JavaScript into merchant storefronts
- **Widget delivery**: Automatic widget installation on customer-facing pages

### Optional Scopes
```
(none)
```

---

## 🎨 App Settings

### Application Settings
- **Embedded**: `true`
- **Legacy Install Flow**: `false`
- **API Version**: `2025-01`

### App Preferences
- **URL**: `https://ai-chat.support/shopify/preferences`
- **Purpose**: Merchant widget customization (position, color, message, offsets)
- **Component**: `app/Livewire/Shopify/Preferences.php`

---

## 📦 Key Laravel Files

### Controllers
```
app/Http/Controllers/IntegrationController.php
├── shopifyCallback() - OAuth completion
├── registerShopifyWebhooks() - Auto-register app/uninstalled
└── shopifyWebhook() - Alternative webhook endpoint

app/Http/Controllers/ShopifyWebhookController.php
├── handle() - Main webhook router
├── verifyHmac() - HMAC signature validation
├── handleAppUninstalled() - Cleanup job dispatcher
├── handleCustomersDataRequest() - GDPR data collection
├── handleCustomersRedact() - GDPR customer deletion
└── handleShopRedact() - GDPR shop deletion
```

### Livewire Components
```
app/Livewire/Public/ShopifyInstall.php
├── Handles OAuth initiation
└── Redirects to Shopify OAuth screen

app/Livewire/Public/ShopifyCompleteSetup.php
├── Post-installation setup page
└── Widget configuration guide

app/Livewire/Shopify/Preferences.php
├── mount() - Load shop settings
└── savePreferences() - Persist widget config
```

### Views
```
resources/views/livewire/public/shopify-install.blade.php
resources/views/livewire/public/shopify-complete-setup.blade.php
resources/views/livewire/shopify/preferences.blade.php
resources/views/layouts/shopify-app.blade.php
```

### Models
```
app/Models/Integration.php
├── shop_domain (ai-chat-support.myshopify.com)
├── access_token (encrypted)
├── organization_id (16)
└── integration_type ('shopify')

app/Models/Organization.php
└── widget_settings (JSON - stores preferences)
```

### Jobs
```
app/Jobs/CleanupShopifyUninstall.php
├── Soft-delete integration
├── Revoke access token
└── Clean up ScriptTags
```

### Artisan Commands
```
app/Console/Commands/ShopifyWebhooksCommand.php
├── shopify:webhooks {shop} list - List registered webhooks
└── shopify:webhooks {shop} register - Register app/uninstalled
```

---

## 🧪 Testing & Verification

### Installation Test URL
```
https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com
```

### Verification Commands
```bash
# List webhooks
cd /var/www/clients/client1/web64/web/laravel
php artisan shopify:webhooks ai-chat-support.myshopify.com list

# Check routes
php artisan route:list | grep shopify

# Monitor webhook traffic
tail -f storage/logs/laravel.log | grep -i shopify
```

### Expected OAuth Flow
1. User clicks install → `/shopify/install`
2. Redirect to Shopify OAuth → `admin.shopify.com/oauth/authorize`
3. User approves → Callback to `/api/integrations/shopify/oauth/callback`
4. Store access token → Create Integration, Organization, User
5. Register webhooks → POST to Shopify Admin API
6. Inject ScriptTag → Widget appears on storefront
7. Redirect to → `/shopify/complete-setup`

---

## 📊 Database State

### Current Integrations Table
```sql
SELECT * FROM integrations WHERE integration_type = 'shopify';
-- Latest: ID 7, shop: ai-chat-support.myshopify.com, org_id: 16
```

### Organizations Table
```sql
SELECT * FROM organizations WHERE id = 16;
-- slug: ai-chat-support
-- widget_settings: JSON with preferences
```

### Users Table
```sql
SELECT * FROM users WHERE id = 21;
-- email: pkjoshi.sbp@gmail.com
-- auto-created during OAuth
```

---

## 🚀 Recreation Steps (NEW APP)

### Step 1: Create New App via CLI
```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app init
```

**Interactive Prompts - ANSWER THESE:**
- **App name**: `ai-chat-support` (or `ai-chat-support-v2` if name taken)
- **Template**: Choose "None" or "Blank" (we have existing code)
- **Distribution**: ⚠️ **PUBLIC DISTRIBUTION** (NOT Sales Channel!)
- **Use TypeScript**: No
- **Use React**: No
- **Install dependencies**: No (we have existing project)

### Step 2: Update shopify.app.toml
```bash
cd /var/www/clients/client1/web64/web
nano shopify.app.toml
```

**Replace `client_id` with NEW value**, keep everything else:
```toml
client_id = "NEW_CLIENT_ID_FROM_PARTNER_DASHBOARD"
name = "ai-chat-support"  # or new name if required
application_url = "https://ai-chat.support/shopify/install"
embedded = true

[webhooks]
api_version = "2025-01"

  [[webhooks.subscriptions]]
  topics = ["app/uninstalled"]
  uri = "https://ai-chat.support/shopify/webhooks"

[webhooks.privacy_compliance]
customer_deletion_url = "https://ai-chat.support/shopify/webhooks"
customer_data_request_url = "https://ai-chat.support/shopify/webhooks"
shop_deletion_url = "https://ai-chat.support/shopify/webhooks"

[access_scopes]
scopes = "write_script_tags"
optional_scopes = []
use_legacy_install_flow = false

[auth]
redirect_urls = [
  "https://ai-chat.support/api/integrations/shopify/oauth/callback"
]

[app_preferences]
url = "https://ai-chat.support/shopify/preferences"
```

### Step 3: Update Laravel .env
```bash
cd /var/www/clients/client1/web64/web/laravel
nano .env
```

**Update these lines:**
```bash
SHOPIFY_API_KEY=NEW_CLIENT_ID_HERE
SHOPIFY_API_SECRET=NEW_API_SECRET_HERE
```

### Step 4: Link and Deploy
```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app config link --client-id=NEW_CLIENT_ID
npx @shopify/cli app deploy --force
```

### Step 5: Verify Version Created
```bash
npx @shopify/cli app versions list
# Should show new version as ★ active
```

### Step 6: Install on Dev Store
```
https://ai-chat.support/shopify/install?shop=ai-chat-support.myshopify.com
```

### Step 7: Run Automated Checks
```
Partner Dashboard → Distribution → Run automated checks
Expected: All ✅ PASS
```

---

## ✅ Post-Recreation Checklist

After creating the new app, verify:

- [ ] shopify.app.toml has new client_id
- [ ] .env has new SHOPIFY_API_KEY and SHOPIFY_API_SECRET
- [ ] App config linked: `npx @shopify/cli app config link`
- [ ] Version deployed and active
- [ ] OAuth flow works (install test)
- [ ] Integration created in database
- [ ] Organization created
- [ ] ScriptTag injected
- [ ] Webhooks registered (check logs)
- [ ] Automated checks PASS (all 5)
- [ ] Preferences page loads
- [ ] Distribution method: **Public Distribution** (NOT Sales Channel)

---

## ⚠️ Important Notes

### What Changes
- ✅ Client ID (update in TOML and .env)
- ✅ API Secret (update in .env)
- ✅ Integration ID (new row in database)
- ✅ Organization might get new ID (if creating fresh)

### What Stays the Same
- ✅ All URLs (install, callback, webhooks, preferences)
- ✅ All code (controllers, components, views)
- ✅ All routes
- ✅ Database schema
- ✅ Access scopes
- ✅ Webhook configuration structure

### Critical: Distribution Method Selection
When creating new app, you'll see options like:
- **Public distribution** ✅ ← SELECT THIS
- **Custom app** ✅ ← OR THIS (simpler)
- **Sales channel** ❌ ← DO NOT SELECT

---

## 📞 Emergency Rollback

If new app creation fails:
1. You still have old credentials above
2. Restore client_id in shopify.app.toml
3. Restore .env values
4. Request old app restoration from Shopify Support

---

## 📚 Reference Documentation

- **Shopify App TOML**: https://shopify.dev/docs/apps/tools/cli/configuration
- **Webhook Subscriptions**: https://shopify.dev/docs/apps/build/webhooks
- **OAuth Flow**: https://shopify.dev/docs/apps/auth/oauth
- **Privacy Compliance**: https://shopify.dev/docs/apps/store/data-protection/protected-customer-data

---

**✨ This backup contains everything needed to recreate the app exactly as it was, minus the sales channel distribution method.**

**Backup created**: October 14, 2025  
**Backup location**: `/var/www/clients/client1/web64/web/SHOPIFY_APP_BACKUP.md`  
**Current status**: Ready for deletion and recreation
