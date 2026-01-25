# Critical Fixes - January 23, 2026

## Issues Fixed

### 1. Main Website Widget Disappeared (404 Error)
**Problem:** The ai-chat.support website widget stopped working because it was trying to load organization ID 3, which no longer exists in the database.

**Root Cause:** Organization ID 3 was deleted at some point, but the public layout template was still hardcoded to use it.

**Solution:** Changed [layouts/public.blade.php](laravel/resources/views/layouts/public.blade.php#L167-L176) to use the organization slug `ai-chat-support` instead of numeric ID 3:

```php
// Before (broken):
const orgId = 3;
script.src = `{{ config('app.url') }}/widget/${orgId}/script.js`;

// After (fixed):
const orgSlug = 'ai-chat-support';
script.src = `{{ config('app.url') }}/widget/${orgSlug}/script.js`;
```

**Status:** ✅ FIXED - Widget now loads correctly for the main website

---

### 2. Shopify Reinstall Not Creating New Organization
**Problem:** When uninstalling and reinstalling the Shopify app, it was supposed to create a new organization but didn't.

**Root Cause:** The OAuth callback logic in `IntegrationController::shopifyCallback()` was checking if the user already had ANY organization, and if so, it would reuse it. This meant:
- User installs app → Creates org A for shop-a.myshopify.com
- User uninstalls app from shop-a
- User installs app on shop-b.myshopify.com → Incorrectly reused org A instead of creating org B

**Solution:** Changed the organization lookup strategy in [IntegrationController.php](laravel/app/Http/Controllers/IntegrationController.php#L423-L492):

**Old Logic:**
1. Check if user has any organization → reuse it
2. Check if organization exists by website URL → reuse it  
3. Create new organization only if neither exists

**New Logic:**
1. ✅ Check if THIS specific shop already has an integration → reuse that org (for reinstalls)
2. ✅ Check if organization exists by website URL → reuse it
3. ✅ Create new organization if neither exists

This ensures:
- **Reinstalling the same shop** → Reuses the correct organization for that shop
- **Installing on a new shop** → Creates a new organization even if user already has other orgs
- **Multiple shops per user** → Properly supported (user can have different orgs for different shops)

**Key Code Change:**
```php
// First, check if this exact shop already has an integration
$existingIntegration = Integration::where('provider', 'shopify')
    ->where('shop', $shop)
    ->first();

if ($existingIntegration) {
    // Shop already integrated - reuse the organization
    $organization = $existingIntegration->organization;
    // ... update with fresh data
}
```

**Status:** ✅ FIXED - Now correctly handles both reinstalls and multi-shop scenarios

---

## Testing Recommendations

1. **Main Website Widget:**
   - Visit https://ai-chat.support
   - Verify chat widget appears in bottom-right corner
   - Test sending a message to confirm AI responses work

2. **Shopify Reinstall:**
   - Uninstall the app from ai-chat-support.myshopify.com
   - Reinstall the app
   - Verify it finds and reuses the existing organization (ID 27)
   - Check logs show: "Found existing organization for this Shopify shop"

3. **New Shopify Store:**
   - Install app on a different test store (e.g., test-shop.myshopify.com)
   - Verify it creates a NEW organization
   - Verify user can access both organizations from dashboard

---

## Database State

**Current Organizations:**
- ID 27: `ai-chat-support` (Active) - Main site + Shopify integration

**Note:** Organization ID 3 was previously deleted. All references updated to use slug `ai-chat-support` instead.

---

## Related Files Modified

1. `/laravel/resources/views/layouts/public.blade.php` - Widget initialization
2. `/laravel/app/Http/Controllers/IntegrationController.php` - OAuth callback logic

---

## Outstanding Issues

1. **llama-server Still Stuck:** The llama-server process (PID 3646596) is using 91.3% CPU and is unresponsive. This causes all AI chat responses to timeout after 30 seconds.
   - **Workaround:** User needs to restart llama-server with sudo privileges
   - **Alternative:** Switch to Ollama backend temporarily via AdminSettings

2. **Multiple ai-chat-support Organizations:** Only ID 27 exists now, but this should be monitored to ensure no duplicates are created in the future.

---

## Prevention Measures

1. **Widget Configuration:** Consider moving widget organization ID/slug to a configuration file or database setting instead of hardcoding in templates.

2. **Organization Management:** The improved Shopify integration logic should prevent accidental organization reuse, but consider adding:
   - Admin dashboard to view all Shopify integrations
   - Warning when deleting organizations that have active integrations
   - Automated tests for OAuth flow

3. **Health Monitoring:** Consider adding automated health checks for:
   - Widget endpoint availability
   - llama-server responsiveness
   - Organization data integrity
