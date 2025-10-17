# Shopify App Listing Requirements Checklist

**App**: ai-chat-support  
**Type**: AI Chat Widget (Storefront)  
**Billing**: External (Credit-based via your website)

---

## ✅ Technical Requirements (Already Done)

- [x] Must authenticate immediately after install ✅
- [x] Must redirect to app UI after install (complete-setup page) ✅
- [x] Provides mandatory compliance webhooks ✅
- [x] Verifies webhooks with HMAC signatures ✅
- [x] Uses a valid TLS certificate ✅
- [x] Must have UI merchants can interact with (Preferences page) ✅
- [x] Must submit as a regular app (public distribution, not sales channel) ✅
- [x] App must be free from bugs and errors ✅
- [x] Must not bypass Shopify checkout ✅
- [x] Must not bypass the Shopify theme store ✅
- [x] Must not be a capital lending app ✅
- [x] Must not be a desktop app ✅
- [x] Must not be a marketplace ✅
- [x] Must not be an app that provides refunds ✅
- [x] Must not be an unauthorized payment gateway ✅
- [x] Must not connect merchants to external agencies ✅
- [x] Must not falsify data ✅
- [x] Must not require browser extension ✅
- [x] Must re-install properly ✅
- [x] Must use Shopify APIs after install ✅
- [x] Embedded = false (no embedded app requirements) ✅

---

## 📝 Listing Content Requirements (TO DO)

### 1. App Icon ⚠️ REQUIRED
**Status**: ❌ Not uploaded yet

**What to do**:
1. Create app icon: 1200x1200px PNG or JPG
2. Design: Your AI Chat Support logo/branding
3. Upload: Partner Dashboard → Apps → ai-chat-support → **App listing** → **App icon**

**Requirements**:
- Must be square (1:1 aspect ratio)
- Minimum 1200x1200px
- Clear, professional design
- No generic placeholder icons

---

### 2. Pricing Information ⚠️ REQUIRED
**Status**: ❌ Not added yet

**What to do**:
1. Go to: Partner Dashboard → Apps → ai-chat-support → **Pricing**
2. Click: **Add pricing plan**

**Your Options**:

**Option A: Free App with In-App Purchases** (RECOMMENDED for your case)
```
Pricing plan name: Free Plan
Price: $0.00 USD/month
Description: Free to install. Credits can be purchased on our website.
```

**Then in app description, explain**:
- "Free to install"
- "Pay-as-you-go credit system"
- "Credits start at $5 for 5,000 messages"
- "Purchase credits at https://ai-chat.support"

**Option B: External Billing Only**
```
Pricing plan name: Credit-Based Pricing
Price: Free
Description: External billing via our website. See pricing details at https://ai-chat.support/pricing
```

**Option C: List All Credit Packages** (Most transparent)
```
Free Plan: $0 - Includes trial credits
Starter: $5 - 5,000 AI responses
Business: $25 - 30,000 AI responses  
Enterprise: $100 - 150,000 AI responses
```

---

### 3. App Name ✅ Already Done
**Status**: ✅ "ai-chat-support" is descriptive and not generic

---

### 4. App Description & Details ⚠️ REQUIRED
**Status**: Need to complete

**What to include**:
1. **Short description** (50-120 chars):
   ```
   AI-powered customer support chat widget with 24/7 automated responses
   ```

2. **Long description** (detailed):
   ```
   Transform your customer support with our AI-powered chat widget.
   
   KEY FEATURES:
   • 24/7 automated customer support
   • AI trained on your business data (FAQs, services, products)
   • Customizable widget design and position
   • Multi-language support
   • Real-time responses
   • No coding required
   
   HOW IT WORKS:
   1. Install the app
   2. Configure widget appearance in Settings
   3. AI automatically answers customer questions
   4. Widget appears on your storefront
   
   PRICING:
   Free to install. Pay-as-you-go credits:
   • $5 = 5,000 AI responses
   • $25 = 30,000 AI responses
   • $100 = 150,000 AI responses
   
   Credits purchased on our website: https://ai-chat.support
   
   SUPPORT:
   Email: info@ai-chat.support
   Documentation: https://ai-chat.support/docs
   ```

---

### 5. Screenshots/Demo ⚠️ REQUIRED
**Status**: ❌ Need to add

**What to provide**:
1. **At least 3-5 screenshots**:
   - Widget on a storefront (customer view)
   - Widget in action (chat conversation)
   - Preferences/Settings page (merchant view)
   - AI responding to customer query
   - Widget customization options

2. **Optional but recommended**:
   - Demo video/screencast (30-60 seconds)
   - Show installation process
   - Show widget configuration
   - Show customer interaction

**Screenshot requirements**:
- High quality (1280x800px or larger)
- Show actual app functionality
- No competitor branding
- Clear, well-lit, professional

---

### 6. Test Credentials ⚠️ REQUIRED for Review
**Status**: Will need to provide during submission

**What Shopify needs**:
```
Development store URL: ai-chat-support.myshopify.com
Admin email: [provide test account]
Admin password: [provide test password]

Instructions for reviewers:
1. App is installed on the dev store
2. Navigate to Apps → ai-chat-support
3. Click "Settings" to see Preferences page
4. Widget is visible on storefront at [store URL]
5. Test chat by asking: "What services do you offer?"
```

---

### 7. App Tags/Categories ⚠️ REQUIRED
**Status**: Need to select

**Recommended categories**:
- Customer Support
- Live Chat
- AI & Automation
- Customer Service
- Communication

**Tags**:
- AI chat
- customer support
- live chat
- chatbot
- automated support
- customer service
- AI assistant

---

### 8. Support Information ⚠️ REQUIRED
**Status**: Need to add

**What to provide**:
```
Support email: info@ai-chat.support
Support URL: https://ai-chat.support/support
Documentation: https://ai-chat.support/docs
Privacy policy: https://ai-chat.support/privacy
Terms of service: https://ai-chat.support/terms
```

---

### 9. Additional Listing Content

**Must state if it requires Online Store sales channel**:
- ✅ YES - Your widget appears on storefront, so it requires Online Store channel
- Add note: "Requires Online Store sales channel to display chat widget"

**Must not have misleading or inaccurate tags**:
- ✅ Use accurate tags: AI, chat, support, customer service
- ❌ Don't use: free (if charging), SEO, marketing (unless true)

**Must not misuse App card subtitle**:
- Keep subtitle clear and honest
- Example: "AI-powered customer support chat"

**Must not have reviews or testimonials in listing**:
- ❌ Don't add fake reviews
- ✅ Real reviews come from merchants after using app

**Must not have stats or data in listing**:
- ❌ Don't claim "Used by 10,000 merchants" unless true
- ✅ Be honest about being new/beta if applicable

---

## 🚫 Requirements That DON'T Apply

### Billing API Requirements (You Use External Billing)
- ❌ "Must implement Billing API correctly" - **NOT NEEDED** (external billing)
- ❌ "Must use Shopify Billing" - **NOT NEEDED** (you have own credit system)
- ❌ "Must allow changing between pricing plans" - **NOT NEEDED** (external)

### Scope-Specific Requirements (Not Using These Scopes)
- ❌ "Chat in Checkout access scope" - You're not in checkout
- ❌ "Payment Mandate API" - Not using payment mandates
- ❌ "Post Purchase access scope" - Not post-purchase app
- ❌ "Subscription API" - Not using Shopify subscriptions
- ❌ "Read all orders access scope" - Not requesting this scope

### Embedded App Requirements (embedded = false)
- ❌ "Must use session tokens for embedded apps" - Not embedded
- ❌ "Admin blocks must be feature-complete" - Not using admin blocks

### Sales Channel Requirements (You Removed This!)
- ❌ All sales channel specific requirements - Doesn't apply ✅

---

## 📋 Summary: What You Need to Do

### Immediate Actions (Required for Submission)

1. **Create & Upload App Icon**
   - Design: 1200x1200px professional logo
   - Upload: Partner Dashboard → App listing → App icon

2. **Add Pricing Information**
   - Option: "Free to install"
   - Add note: "Credit-based pricing at https://ai-chat.support"

3. **Complete App Description**
   - Short description (1-2 sentences)
   - Long description (features, how it works, pricing)
   - Support contact info

4. **Take Screenshots**
   - Minimum 3 screenshots:
     - Widget on storefront
     - Chat conversation
     - Preferences/Settings page
   - Recommended: 30-60 second demo video

5. **Prepare Test Credentials**
   - Dev store URL with app installed
   - Test account credentials
   - Testing instructions for reviewers

6. **Add Required Links**
   - Privacy policy URL
   - Terms of service URL
   - Support/contact URL

### Optional But Recommended

7. **Demo Video/Screencast**
   - Shows installation
   - Shows configuration
   - Shows widget in action

8. **Help Documentation**
   - Installation guide
   - Configuration guide
   - FAQ for merchants

---

## ✅ What's Already Done

- ✅ All technical requirements met (OAuth, webhooks, HTTPS)
- ✅ Preferences page built and working
- ✅ Distribution method correct (not sales channel)
- ✅ App complies with Shopify policies
- ✅ Embedded = false (simpler requirements)
- ✅ Widget functionality working

---

## 🎯 Billing Clarification

**You DO NOT need Shopify Billing API because**:
1. You use external credit system (PayPal/Razorpay)
2. Merchants buy credits on your website
3. You manage billing independently
4. This is allowed and common for many apps

**You MUST disclose**:
- In app listing: "External billing"
- In pricing section: "Credits purchased at https://ai-chat.support"
- In description: Clear pricing structure

**Shopify Billing API is only needed if**:
- You want Shopify to collect payments
- You want recurring subscriptions through Shopify
- You want app charges to appear on merchant's Shopify invoice

**You don't need this** - your current credit system is fine!

---

## 📞 Next Steps

1. **Start with app icon** (quickest task)
2. **Add pricing details** (clarify external billing)
3. **Write app description** (use template above)
4. **Take screenshots** (install on dev store, capture screens)
5. **Prepare test credentials** (for Shopify review team)
6. **Submit for review** (after all content complete)

---

**The good news**: All the hard technical work is done! Now you just need to complete the **marketing/listing content** to submit for review. The Billing API requirement doesn't apply to your external credit system.

**Document**: `/var/www/clients/client1/web64/web/SHOPIFY_LISTING_REQUIREMENTS.md`  
**Created**: October 14, 2025  
**Status**: Ready to complete listing content
