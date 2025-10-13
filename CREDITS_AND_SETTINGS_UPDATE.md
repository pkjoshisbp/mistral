# Credits & Integration Settings Update

## Changes Made

### 1. Increased Trial Credits from 1,000 to 20,000 Tokens ✅

**File**: `laravel/app/Http/Controllers/IntegrationController.php`

#### Why the Change?
- 1,000 tokens = only 1-2 conversations
- 20,000 tokens = 20-40 conversations (much better trial experience)
- Aligns with organization token_balance already set at 20K

#### Updated Code:
```php
// Give initial credits to new Shopify users (20,000 tokens for trial)
$userCredit = \App\Models\UserCredit::getOrCreateForUser($user->id);
$userCredit->addCredits(20000.00, 'Initial trial credits for Shopify app installation', [
    'source' => 'shopify_install',
    'shop' => $shop
]);
```

### 2. Integration Settings Access for Shopify Users ✅

**Location**: Customer Dashboard → Integration Settings

**URL**: `https://ai-chat.support/customer/integration-settings`

**Menu Item**: Already added to customer sidebar under "Data Management" section

## How Shopify Users Access Settings

### Step-by-Step:
1. **Install Shopify App** → Auto-redirected to dashboard
2. **Navigate to Sidebar Menu** → Find "Integration Settings"
3. **Click "Integration Settings"** → Opens settings page

### What They Can Edit:

#### **Organization Information:**
- ✅ Organization Name
- ✅ Description (helps AI understand your business)
- ✅ Website URL
- ✅ Contact Email
- ✅ Contact Phone

#### **Widget Customization:**
- ✅ Welcome Message
- ✅ Widget Position (4 corners)
- ✅ Primary Color (with color picker)
- ✅ Horizontal Offset (pixels)
- ✅ Vertical Offset (pixels)
- ✅ Live Widget Preview

### Menu Location:
```
Customer Dashboard
└── Data Management
    ├── Organization
    ├── Content Hub
    ├── Documents
    ├── Website Crawler
    ├── API Integration
    └── Integration Settings ← HERE
```

## Credits Distribution Summary

### New User Credits by Source:

| Source | Credits | Duration |
|--------|---------|----------|
| Shopify Install (New User) | 20,000 | 20-40 chats |
| WordPress Install | 0* | Manual signup needed |
| Manual Registration | 0 | Purchase or subscribe |

*WordPress creates organization with 20K token_balance, but users register separately

### Organization Token Balance:

| Source | Token Balance | Purpose |
|--------|---------------|---------|
| Shopify Install | 20,000 | Organization-level tracking |
| WordPress Install | 20,000 | Organization-level tracking |
| Manual Creation | 0 (default) | Admin sets manually |

## Integration Settings Features

### Shopify-Specific Display:
```php
@if($integration)
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        <strong>Integration Type:</strong> 
        <span class="badge badge-primary ml-2">SHOPIFY</span>
        @if($integration->shop)
            <br><small class="text-muted">Shop: {{ $integration->shop }}</small>
        @endif
    </div>
@endif
```

### WordPress-Specific Display:
```php
@if($integration)
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        <strong>Integration Type:</strong> 
        <span class="badge badge-primary ml-2">WORDPRESS</span>
        @if($integration->shop)
            <br><small class="text-muted">Site: {{ $integration->shop }}</small>
        @endif
    </div>
@endif
```

## Testing Guide

### For Shopify Users:

#### Test 1: New Installation
1. Install Shopify app
2. Verify auto-redirect to dashboard
3. Check credits = 20,000 (in Credits page)
4. Navigate to "Integration Settings"
5. Verify all fields are populated from Shopify data
6. Edit organization details
7. Save and verify changes persist

#### Test 2: Settings Update
1. Change organization name
2. Update description
3. Add contact phone
4. Change widget color
5. Update welcome message
6. Click "Save All Settings"
7. Verify success message
8. Refresh page and verify changes saved

#### Test 3: Widget Preview
1. Navigate to Integration Settings
2. Change primary color
3. Observe live preview update
4. Change widget position
5. Observe position text update

### For WordPress Users:

#### Test 1: Plugin Installation
1. Install WordPress plugin
2. Complete setup form with contact details
3. Verify organization created with 20K token_balance
4. User registers separately
5. Navigate to Integration Settings
6. Verify WordPress integration shown
7. Update organization details

## Credit Usage Estimates

### Token/Credit Consumption:

| Action | Tokens | Credits |
|--------|--------|---------|
| Simple question | 200-500 | 200-500 |
| Complex query with context | 800-1500 | 800-1500 |
| Document search + response | 500-1000 | 500-1000 |
| Average conversation (5 msgs) | 1000-2000 | 1000-2000 |

### 20,000 Credits Allows:
- **10-20 complex conversations**
- **30-40 simple Q&A sessions**
- **15-20 days of moderate usage**
- **Enough to fully evaluate the service**

## Files Modified

1. ✅ `laravel/app/Http/Controllers/IntegrationController.php`
   - Updated Shopify user credits: 1,000 → 20,000
   - Updated log messages to reflect new amount

## Already Implemented (No Changes Needed)

1. ✅ `laravel/app/Livewire/Customer/IntegrationSettingsManager.php` - Already exists
2. ✅ `laravel/resources/views/livewire/customer/integration-settings-manager.blade.php` - Already exists
3. ✅ `laravel/routes/web.php` - Route already added
4. ✅ `laravel/resources/views/layouts/customer.blade.php` - Menu item already added

## User Communication

### Welcome Email Should Include:
```
Welcome to AI Chat Support!

Your account has been created with:
- 20,000 free trial credits
- Enough for 20-40 chat conversations
- Perfect for evaluating our AI assistant

To customize your chat widget:
1. Login to your dashboard
2. Navigate to "Integration Settings"
3. Update your organization details
4. Customize widget appearance

Questions? Contact us at support@ai-chat.support
```

## Monitoring & Analytics

### Track:
- [ ] Credits granted per installation
- [ ] Credits consumption rate
- [ ] Conversion from trial to paid
- [ ] Average trial duration
- [ ] Settings page usage

### Metrics to Watch:
- Trial credits depleted in < 7 days → Engaged user
- Trial credits depleted in < 24 hours → Potential abuse
- No credits used after 30 days → Inactive trial
- Settings page never visited → Poor onboarding

## Future Enhancements

### Option 1: Tiered Trials
```php
// Based on Shopify plan
switch($shopifyPlan) {
    case 'basic': $credits = 10000; break;
    case 'shopify': $credits = 20000; break;
    case 'advanced': $credits = 30000; break;
    case 'plus': $credits = 50000; break;
}
```

### Option 2: Credit Expiration
```php
// Credits expire after 30 days
$userCredit->addCredits(20000.00, 'Trial credits', [
    'expires_at' => now()->addDays(30)
]);
```

### Option 3: Smart Notifications
```php
// Email when credits reach 20% (4000 remaining)
// Email when credits reach 5% (1000 remaining)
// Email when credits depleted
```

## Documentation Updates Needed

- [ ] Update SHOPIFY_SETUP.md with 20K credits info
- [ ] Update user guide with Integration Settings location
- [ ] Create video tutorial for settings page
- [ ] Add FAQ about credit usage
- [ ] Update marketing materials (20K credits = better selling point)

---

**Date**: October 7, 2025  
**Status**: ✅ Complete  
**Trial Credits**: 20,000 tokens (up from 1,000)  
**Settings Access**: Already available at /customer/integration-settings  
**Breaking Changes**: None (increase only)
