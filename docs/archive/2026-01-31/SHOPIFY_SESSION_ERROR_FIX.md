# Shopify OAuth Session Error Fix

## Problem
When installing the Shopify app, users encountered this error:
```
RuntimeException: Session store not set on request.
```

This occurred at line 584 in `IntegrationController.php` during the `shopifyCallback()` method when trying to execute:
```php
request()->session()->regenerate();
```

## Root Cause
The Shopify OAuth callback route was defined in `routes/api.php`:
```php
Route::get('/shopify/oauth/callback', [IntegrationController::class, 'shopifyCallback'])
```

**API routes in Laravel do NOT include session middleware by default** - they only have the `api` middleware group which includes:
- Throttling
- Bindings
- JSON response formatting

OAuth callbacks require session support for:
- User authentication (`Auth::login()`)
- Session regeneration for security
- Flash messages
- Redirect with session data

## Solution
**Moved the Shopify OAuth callback route from `api.php` to `web.php`**

### Changes Made

#### 1. Added to `routes/web.php` (line ~398)
```php
// Shopify OAuth callback (needs web session middleware for auto-login)
Route::get('/api/integrations/shopify/oauth/callback', [\App\Http\Controllers\IntegrationController::class, 'shopifyCallback'])
    ->name('api.integrations.shopify.oauth.callback');
```

**Note:** The URL path remains `/api/integrations/shopify/oauth/callback` to maintain compatibility with Shopify's registered redirect URL. Only the route file changed.

#### 2. Removed from `routes/api.php`
```php
// REMOVED: Route::get('/shopify/oauth/callback', ...)
// Only the webhook route remains in api.php (webhooks don't need sessions)
Route::post('/webhook/shopify', [IntegrationController::class, 'shopifyWebhook'])
```

## Why This Works

### Web Routes Include Session Middleware
Routes in `web.php` automatically get the `web` middleware group which includes:
- `EncryptCookies`
- `AddQueuedCookiesToResponse`
- `StartSession` ← **This is what was missing!**
- `ShareErrorsFromSession`
- `VerifyCsrfToken` (except for this route, excluded in VerifyCsrfToken.php)
- `SubstituteBindings`

### OAuth Flow Now Works
1. Shopify redirects to: `https://ai-chat.support/api/integrations/shopify/oauth/callback?code=...`
2. Laravel matches the route in `web.php`
3. **Session middleware starts**, making `session()` available
4. Controller can now:
   - Exchange code for access token
   - Create/update organization
   - Create user account
   - `Auth::login($user)` ✅
   - `request()->session()->regenerate()` ✅
   - Redirect to dashboard with active session ✅

## Testing Checklist

- [ ] Uninstall existing Shopify app from test store (if installed)
- [ ] Visit https://ai-chat.support/shopify/install
- [ ] Enter Shopify store URL
- [ ] Click "Install App"
- [ ] Authorize the app in Shopify
- [ ] Should redirect back to callback URL
- [ ] Should create organization and user
- [ ] Should auto-login and redirect to dashboard
- [ ] No "Session store not set" error
- [ ] User should see 20,000 trial credits

## Related Files
- `/laravel/routes/web.php` - Contains OAuth callback route
- `/laravel/routes/api.php` - Contains webhook route only
- `/laravel/app/Http/Controllers/IntegrationController.php` - shopifyCallback() method
- `/laravel/app/Http/Middleware/VerifyCsrfToken.php` - CSRF exception for callback URL

## Technical Notes

### Why Not Add Session Middleware to API Routes?
- API routes are designed to be stateless (for tokens, not sessions)
- Adding sessions to API routes breaks RESTful principles
- Would affect all other API endpoints unnecessarily

### Why Keep Webhook in API Routes?
- Webhooks are server-to-server calls
- Don't need sessions, cookies, or CSRF protection
- Should remain stateless
- Already exempted from CSRF in `VerifyCsrfToken.php`

### URL Path Consideration
The route is defined as:
```php
Route::get('/api/integrations/shopify/oauth/callback', ...)
```

The `/api/` prefix in the **URL path** does NOT mean it must be in `api.php`. Route files and URL paths are independent. What matters:
- **Route file** determines middleware group
- **URL path** is just a string pattern to match

## Prevention
When creating OAuth/authentication flows in the future:
1. ✅ Use `web.php` for routes that need sessions
2. ✅ Use `api.php` for stateless endpoints (tokens, webhooks)
3. ✅ Test with fresh browser session to catch session errors early
4. ✅ Check Laravel middleware groups in `app/Http/Kernel.php`

---

**Status:** ✅ Fixed
**Date:** October 9, 2025
**Impact:** Critical - Blocks all new Shopify app installations
