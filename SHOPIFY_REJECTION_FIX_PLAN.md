# Shopify App Store Rejection - Fix Action Plan
**Reference: 93967 | App: AI CHAT SUPPORT | Date: January 8, 2026**

## Executive Summary
Your Shopify app was rejected for 9 critical issues. This document provides a complete step-by-step plan to fix each issue and get your app approved.

---

## 🔴 ISSUE 1: Billing API (1.2.1) - CRITICAL
**Problem**: App uses off-platform billing instead of Shopify Billing API or Managed Pricing.

### Current State
- No Shopify Billing API integration detected in codebase
- Checkbox "I have approval to charge merchants outside of Shopify Billing API" is checked without exemption documentation

### Fix Steps

#### Option A: Use Shopify Managed Pricing (Recommended - Easier)
1. **In Shopify Partner Dashboard:**
   - Go to Apps → AI CHAT SUPPORT → Pricing
   - Set up pricing tiers using Managed Pricing interface
   - Define free trial period (e.g., 7 or 14 days)
   - Set monthly/annual pricing tiers
   - **UNCHECK** the "I have approval to charge outside Billing API" checkbox

2. **No code changes needed** - Shopify handles billing automatically

#### Option B: Implement Shopify Billing API (Advanced)
1. **Add billing API scopes** to your app:
   ```php
   // In ShopifyInstall.php, update scopes
   $scopes = 'read_script_tags,write_script_tags,write_products,read_products';
   ```

2. **Create Billing Controller:**
   ```bash
   php artisan make:controller ShopifyBillingController
   ```

3. **Implement charging logic:**
   - Create subscription when merchant completes installation
   - Check subscription status before allowing access
   - Handle upgrade/downgrade flows
   - Webhook for billing updates

4. **Code Implementation:**
   ```php
   // app/Http/Controllers/ShopifyBillingController.php
   public function createCharge(Request $request)
   {
       $shop = $request->shop;
       $accessToken = Integration::where('shop', $shop)->first()->access_token;
       
       $charge = [
           'recurring_application_charge' => [
               'name' => 'AI Chat Support - Basic',
               'price' => 29.99,
               'return_url' => route('shopify.billing.callback'),
               'trial_days' => 7,
               'test' => env('APP_ENV') !== 'production'
           ]
       ];
       
       $response = Http::withHeaders([
           'X-Shopify-Access-Token' => $accessToken
       ])->post("https://{$shop}/admin/api/2024-01/recurring_application_charges.json", $charge);
       
       return redirect($response['recurring_application_charge']['confirmation_url']);
   }
   ```

5. **Add middleware to check subscription status**

**Recommendation**: Use Managed Pricing (Option A) for faster approval.

---

## 🔴 ISSUE 2: Pricing Information (4.2.1)
**Problem**: Pricing added to "See all pricing options" when it could fit in the listing form.

### Fix Steps
1. **In Shopify Partner Dashboard:**
   - Go to Apps → AI CHAT SUPPORT → Listing → Pricing
   - Remove any content from "See all pricing options" field
   - Use the structured pricing fields instead:
     - **Free trial**: 7 days (or your actual trial period)
     - **Basic Plan**: $29.99/month (or your actual pricing)
     - **Pro Plan**: $79.99/month (if applicable)
   - Ensure pricing matches what's shown in your app UI

2. **Match pricing in your Laravel app:**
   - Update any pricing pages to match Partner Dashboard
   - Ensure consistency across app and listing

---

## 🔴 ISSUE 3: Theme App Extensions (5.1.1) - CRITICAL
**Problem**: App automatically adds embed without merchant control. Must use theme app extensions.

### Current State
- Widget auto-injects via JavaScript
- No theme app extension structure

### Fix Steps

1. **Install Shopify CLI** (if not already installed):
   ```bash
   npm install -g @shopify/cli @shopify/theme
   ```

2. **Create Theme App Extension:**
   ```bash
   cd /var/www/clients/client1/web64/web
   mkdir -p shopify-app-extension
   cd shopify-app-extension
   shopify app generate extension --template theme
   ```

3. **Create extension structure:**
   ```
   shopify-app-extension/
   ├── extensions/
   │   └── ai-chat-widget/
   │       ├── blocks/
   │       │   └── chat-widget.liquid
   │       ├── snippets/
   │       │   └── chat-script.liquid
   │       └── assets/
   │           └── chat-widget.js
   └── shopify.app.toml
   ```

4. **Create `blocks/chat-widget.liquid`:**
   ```liquid
   {% schema %}
   {
     "name": "AI Chat Widget",
     "target": "section",
     "settings": [
       {
         "type": "checkbox",
         "id": "enable_widget",
         "label": "Enable Chat Widget",
         "default": true
       },
       {
         "type": "color",
         "id": "widget_color",
         "label": "Widget Color",
         "default": "#007bff"
       },
       {
         "type": "text",
         "id": "welcome_message",
         "label": "Welcome Message",
         "default": "Hi! How can I help you today?"
       }
     ]
   }
   {% endschema %}

   {% if block.settings.enable_widget %}
     <script>
       (function() {
         var shop = '{{ shop.permanent_domain }}';
         var config = {
           color: '{{ block.settings.widget_color }}',
           welcomeMessage: '{{ block.settings.welcome_message }}'
         };
         // Load widget script
         var script = document.createElement('script');
         script.src = 'https://ai-chat.support/widget/{{ shop.permanent_domain }}/script.js';
         document.body.appendChild(script);
       })();
     </script>
   {% endif %}
   ```

5. **Deploy extension:**
   ```bash
   shopify app deploy
   ```

6. **Remove auto-injection code:**
   - Update `ShopifyWebhookController.php` to NOT automatically inject script tags
   - Let merchants enable via theme editor instead

---

## 🔴 ISSUE 4: Onboarding Instructions (5.1.3)
**Problem**: No detailed setup instructions for theme app extensions.

### Fix Steps

1. **Create onboarding component:**
   ```bash
   php artisan make:livewire Public/ShopifyOnboarding
   ```

2. **Add to `ShopifyOnboarding.php`:**
   ```php
   <?php
   namespace App\Livewire\Public;
   use Livewire\Component;

   class ShopifyOnboarding extends Component
   {
       public $currentStep = 1;
       public $shop;
       
       public function mount($shop)
       {
           $this->shop = $shop;
       }
       
       public function render()
       {
           return view('livewire.public.shopify-onboarding')
               ->layout('layouts.public')
               ->title('Setup Instructions - AI Chat Support');
       }
   }
   ```

3. **Create onboarding view** with step-by-step instructions:
   - **Step 1**: Installation complete
   - **Step 2**: How to enable widget in theme editor (with screenshots)
   - **Step 3**: Customize widget settings
   - **Step 4**: Add training data
   - **Step 5**: Test the widget
   - Include deep link: `https://{shop}/admin/themes/current/editor?context=apps`

4. **Add route:**
   ```php
   Route::get('/shopify/onboarding/{shop}', ShopifyOnboarding::class)->name('shopify.onboarding');
   ```

5. **Redirect after OAuth:**
   - Update `ShopifyWebhookController.php` OAuth callback
   - Redirect to onboarding page instead of directly to dashboard

---

## 🔴 ISSUE 5A: Store Integration Loop
**Problem**: App asks to integrate store every time app index is opened.

### Current State Analysis
- `ShopifyInstall.php` requires manual shop domain entry
- No automatic detection of already-installed app

### Fix Steps

1. **Update OAuth flow to save session:**
   ```php
   // In ShopifyWebhookController@oauthCallback
   public function oauthCallback(Request $request)
   {
       $shop = $request->shop;
       $code = $request->code;
       
       // Exchange code for access token
       // ... existing code ...
       
       // Save session data
       session(['shopify_shop' => $shop]);
       session(['shopify_authenticated' => true]);
       
       // Check if organization already exists
       $integration = Integration::where('shop', $shop)->first();
       if ($integration && $integration->organization_id) {
           // Already set up - go to dashboard
           return redirect()->route('customer.dashboard');
       }
       
       // First time - go to onboarding
       return redirect()->route('shopify.onboarding', ['shop' => $shop]);
   }
   ```

2. **Create middleware to check Shopify session:**
   ```bash
   php artisan make:middleware CheckShopifySession
   ```

   ```php
   public function handle($request, Closure $next)
   {
       if (!session('shopify_authenticated')) {
           return redirect()->route('shopify.auth');
       }
       return $next($request);
   }
   ```

3. **Remove manual shop domain entry** for returning merchants:
   - Detect shop from session or URL parameters
   - Only show manual entry for first-time installs

---

## 🔴 ISSUE 5B: Chatbot Reset on Navigation
**Problem**: Chatbot resets and clears messages when clicking elements on online store.

### Current State Analysis
- Widget uses session storage or local state
- No persistence across page navigations

### Fix Steps

1. **Update `widget/script.blade.php` to persist messages:**
   ```javascript
   // In AiChatWidget class
   loadMessages() {
       const key = `ai_chat_messages_${this.sessionId}`;
       const stored = localStorage.getItem(key);
       if (stored) {
           this.messages = JSON.parse(stored);
           this.renderMessages();
       }
   }

   saveMessages() {
       const key = `ai_chat_messages_${this.sessionId}`;
       localStorage.setItem(key, JSON.stringify(this.messages));
   }

   addMessage(text, sender) {
       this.messages.push({ text, sender, timestamp: Date.now() });
       this.saveMessages(); // Persist immediately
       this.renderMessage(text, sender);
   }
   ```

2. **Restore session on page load:**
   ```javascript
   constructor(config) {
       this.config = config;
       this.sessionId = this.getOrCreateSessionId(); // Get existing session
       this.messages = [];
       this.init();
       this.loadMessages(); // Restore previous messages
   }

   getOrCreateSessionId() {
       const key = `ai_session_id_${this.config.orgId}`;
       let sessionId = sessionStorage.getItem(key);
       if (!sessionId) {
           sessionId = 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
           sessionStorage.setItem(key, sessionId);
       }
       return sessionId;
   }
   ```

3. **Add session timeout** (e.g., 30 minutes):
   ```javascript
   checkSessionExpiry() {
       const key = `ai_session_timestamp_${this.config.orgId}`;
       const lastActivity = sessionStorage.getItem(key);
       const now = Date.now();
       
       if (lastActivity && (now - parseInt(lastActivity)) > 30 * 60 * 1000) {
           // Session expired - clear messages
           this.clearSession();
       } else {
           sessionStorage.setItem(key, now.toString());
       }
   }
   ```

---

## 🔴 ISSUE 6: Language Support (4.3.2)
**Problem**: App listing claims many languages but only supports English.

### Fix Steps

1. **In Shopify Partner Dashboard:**
   - Go to Apps → AI CHAT SUPPORT → Listing → Languages
   - Remove all languages except English
   - Keep only: **English**

2. **If you want multi-language support** (future):
   - Implement Laravel localization
   - Add translation files
   - Update widget to support multiple languages
   - Then add languages to listing

---

## 🔴 ISSUE 7: Installation Flow (2.3.1)
**Problem**: App asks for manual myshopify.com URL entry during installation.

### Current State
- `ShopifyInstall.php` has manual shop domain input field

### Fix Steps

1. **Shopify handles installation automatically:**
   - When merchant clicks "Add app" in Shopify App Store, Shopify redirects with shop parameter
   - Your OAuth callback receives the shop domain automatically

2. **Update `ShopifyInstall.php` to auto-detect:**
   ```php
   public function mount()
   {
       // Get shop from URL parameter (provided by Shopify)
       $shop = request('shop');
       
       if ($shop) {
           // Auto-redirect to OAuth
           $this->shopDomain = str_replace('.myshopify.com', '', $shop);
           $this->startInstallation();
       }
   }
   ```

3. **Remove manual input form** or only show as fallback:
   - Hide input field when shop parameter is present
   - Only show for edge cases (re-authentication, etc.)

4. **Update route:**
   ```php
   // In web.php
   Route::get('/shopify/install', ShopifyInstall::class)->name('shopify.install');
   // Shopify will redirect to: https://ai-chat.support/shopify/install?shop=store.myshopify.com
   ```

---

## 🔴 ISSUE 8: Widget Branding (5.1.4) - CRITICAL
**Problem**: Widget must use standard attribution pattern (logo only), not "Powered by" text.

### Current State
- Widget shows "Powered by AI Chat Support" text
- Does not follow standard attribution guidelines

### Fix Steps

1. **Update `widget/script.blade.php` branding section:**
   ```javascript
   // BEFORE (line ~152):
   ${this.config.brandingEnabled && this.config.brandingBadge ? `
       <div class="ai-chat-branding">
           <a href="https://ai-chat.support">Powered by AI Chat Support</a>
       </div>
   ` : ''}

   // AFTER:
   ${this.config.brandingEnabled ? `
       <div class="ai-chat-attribution">
           <a href="https://ai-chat.support" target="_blank" rel="nofollow noopener noreferrer" aria-label="Powered by AI Chat Support">
               <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                   <!-- Your logo SVG path -->
                   <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
               </svg>
           </a>
       </div>
   ` : ''}
   ```

2. **Update CSS for standard attribution:**
   ```css
   /* In widget/styles.blade.php */
   .ai-chat-attribution {
       position: absolute;
       bottom: 8px;
       right: 8px;
       opacity: 0.6;
       z-index: 1000;
   }

   .ai-chat-attribution a {
       display: flex;
       align-items: center;
       color: {{ $theme['primaryColor'] }};
       text-decoration: none;
       font-size: 12px;
   }

   .ai-chat-attribution svg {
       width: 16px;
       height: 16px;
   }
   ```

3. **Remove text-based branding options:**
   - Update `WidgetManager.php` Livewire component
   - Remove "Show Powered by text" option
   - Keep only logo/icon attribution

4. **Update database settings:**
   ```sql
   -- For all organizations, update branding to logo-only
   UPDATE organization_settings 
   SET settings = JSON_SET(settings, '$.branding_text_enabled', false)
   WHERE JSON_EXTRACT(settings, '$.branding_text_enabled') IS NOT NULL;
   ```

---

## 📋 IMPLEMENTATION CHECKLIST

### Phase 1: Critical Blocking Issues (Do First)
- [ ] **Issue 1**: Set up Managed Pricing in Partner Dashboard
- [ ] **Issue 1**: Uncheck "off-platform billing" checkbox
- [ ] **Issue 3**: Create theme app extension
- [ ] **Issue 3**: Remove auto-injection of script tags
- [ ] **Issue 7**: Remove manual shop domain entry
- [ ] **Issue 8**: Change widget branding to logo-only
- [ ] **Issue 8**: Update all widget instances

### Phase 2: User Experience Issues
- [ ] **Issue 4**: Create onboarding instructions page
- [ ] **Issue 4**: Add deep link to theme editor
- [ ] **Issue 5A**: Fix store integration loop
- [ ] **Issue 5A**: Detect existing installations
- [ ] **Issue 5B**: Implement message persistence
- [ ] **Issue 5B**: Add session management

### Phase 3: Listing Updates
- [ ] **Issue 2**: Update pricing in Partner Dashboard
- [ ] **Issue 2**: Remove "See all pricing" content
- [ ] **Issue 6**: Remove unsupported languages
- [ ] **Issue 6**: Keep English only

### Phase 4: Testing
- [ ] Install app in test store
- [ ] Verify OAuth flow works without manual input
- [ ] Test theme extension in theme editor
- [ ] Verify chatbot persists across pages
- [ ] Check widget branding (logo only)
- [ ] Test full onboarding flow

### Phase 5: Response to Shopify
- [ ] Reply to rejection email
- [ ] Confirm all issues are fixed
- [ ] Request review resumption

---

## 🚀 QUICK START COMMANDS

```bash
# 1. Create theme app extension
cd /var/www/clients/client1/web64/web
mkdir -p shopify-app-extension
cd shopify-app-extension
shopify app generate extension --template theme

# 2. Create onboarding component
cd ../laravel
php artisan make:livewire Public/ShopifyOnboarding

# 3. Create billing controller (if using Billing API)
php artisan make:controller ShopifyBillingController

# 4. Test changes
php artisan config:clear
php artisan cache:clear

# 5. Deploy
systemctl restart ai-fastapi.service
```

---

## 📧 EMAIL RESPONSE TEMPLATE

```
Subject: Re: Action required: Issues with your app submission (Reference: 93967)

Hello Shopify App Store Team,

Thank you for the detailed feedback on AI CHAT SUPPORT (Reference: 93967).

I have addressed all 9 issues identified in your review:

1. ✅ Billing API: Implemented Shopify Managed Pricing, removed off-platform billing checkbox
2. ✅ Pricing Information: Updated pricing in structured fields, removed "See all pricing" content
3. ✅ Theme App Extensions: Created theme app extension, removed auto-injection
4. ✅ Onboarding Instructions: Added detailed setup instructions with deep links
5. ✅ Store Integration Loop: Fixed authentication flow, removed redundant integration requests
6. ✅ Chatbot Reset: Implemented session persistence across page navigation
7. ✅ Language Support: Updated listing to show English only
8. ✅ Installation Flow: Removed manual shop domain entry, auto-detect from Shopify
9. ✅ Widget Branding: Changed to standard attribution pattern (logo only)

The app is now ready for review. Please let me know if you need any additional information.

Thank you,
[Your Name]
```

---

## ⏱️ ESTIMATED TIMELINE

| Phase | Duration | Priority |
|-------|----------|----------|
| Phase 1 (Critical) | 2-3 days | HIGH |
| Phase 2 (UX) | 1-2 days | MEDIUM |
| Phase 3 (Listing) | 1 hour | LOW |
| Phase 4 (Testing) | 1 day | HIGH |
| **Total** | **4-6 days** | - |

---

## 🆘 NEED HELP?

If you need assistance with any specific issue, I can:
1. Write the complete code for any component
2. Create the theme app extension structure
3. Update specific files
4. Test the implementation

Just let me know which issue you want to tackle first, and I'll provide detailed code implementation.

---

## 📚 REFERENCES

- [Shopify App Requirements](https://shopify.dev/docs/apps/launch/requirements)
- [Theme App Extensions](https://shopify.dev/docs/apps/online-store/theme-app-extensions)
- [Shopify Billing API](https://shopify.dev/docs/apps/billing)
- [App Store Listing Guidelines](https://shopify.dev/docs/apps/launch/app-store-guidelines)
