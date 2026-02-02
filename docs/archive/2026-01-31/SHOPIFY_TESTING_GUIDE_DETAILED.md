# Shopify Installation Testing Guide

## ✅ Pre-Testing Checklist

Before testing the Shopify installation flow, ensure:

1. **Environment Variables Set**
   ```bash
   # In .env file
   SHOPIFY_API_KEY=5c39f2cc2b70c6e9d3ea5adb2a7f4a18
   SHOPIFY_API_SECRET=c94a8f4961e2ccccc4d8c4bb8c70b81c
   APP_URL=https://ai-chat.support
   
   # Mail settings configured
   MAIL_MAILER=smtp
   MAIL_HOST=your-smtp-host
   MAIL_PORT=587
   MAIL_USERNAME=your-username
   MAIL_PASSWORD=your-password
   MAIL_FROM_ADDRESS=noreply@ai-chat.support
   MAIL_FROM_NAME="AI Chat Support"
   ```

2. **Config Cached**
   ```bash
   cd /var/www/clients/client1/web64/web/laravel
   php artisan config:cache
   ```

3. **Routes Registered**
   ```bash
   php artisan route:list | grep shopify
   ```
   
   Should show:
   - `GET /shopify/install`
   - `GET /shopify/callback`
   - `GET /shopify/complete-setup`

4. **Shopify Partner Dashboard Configured**
   - App URL: `https://ai-chat.support`
   - Allowed redirection URLs: `https://ai-chat.support/shopify/callback`
   - Scopes: `read_script_tags`, `write_script_tags`

---

## 🧪 Test Scenario 1: New User Installation

### Steps:

1. **Start Log Monitoring**
   ```bash
   tail -f /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -i shopify
   ```

2. **Initiate Installation**
   - Visit: `https://ai-chat.support/shopify/install`
   - Enter your test Shopify store: `your-test-store.myshopify.com`
   - Click "Install App"

3. **Complete OAuth**
   - Authorize the app on Shopify
   - Should redirect to: `https://ai-chat.support/shopify/callback?code=xxx&shop=xxx`

4. **Verify Log Messages**
   Look for these in order:
   ```
   ✅ Shopify OAuth callback received
   ✅ Shopify access token obtained successfully
   ✅ Shopify shop details fetched {"has_email":true}
   ✅ Created new user for Shopify installation
   ✅ Associated user with organization
   ✅ Welcome email sent to new Shopify user
   ✅ User auto-logged in after Shopify installation
   ✅ Shopify ScriptTag created successfully
   ✅ Shopify integration completed {"user_created":true}
   ```

5. **Verify Database State**
   ```bash
   cd /var/www/clients/client1/web64/web/laravel
   php artisan tinker
   ```
   
   ```php
   // Check organization
   $org = \App\Models\Organization::where('website', 'like', '%your-test-store%')->first();
   $org->name; // Should be shop owner name
   $org->contact_email; // Should have email
   
   // Check user
   $user = \App\Models\User::where('email', $org->contact_email)->first();
   $user->name; // Should have name
   $user->email_verified_at; // Should be set
   
   // Check association
   $org->users; // Should include the user
   $user->organizations; // Should include the org
   
   // Check integration
   $org->integrations()->where('provider', 'shopify')->first();
   // Should have access_token, shop, script_tag_id
   ```

6. **Verify Auto-Login & Redirect**
   - After OAuth, should be automatically logged in
   - Should redirect to: `https://ai-chat.support/customer/dashboard`
   - Should see success message: "Shopify integration completed! Your AI chat widget is now active on your store."

7. **Check Email Delivery**
   - Check inbox for shop owner email
   - Subject: "Welcome to AI Chat Support!"
   - Should contain:
     - Login credentials
     - Temporary password
     - Dashboard link
     - Getting started guide

8. **Verify Widget on Storefront**
   - Visit your Shopify store: `https://your-test-store.myshopify.com`
   - Check for widget script: `https://ai-chat.support/widget/{org-slug}/script.js`
   - Widget should appear (usually bottom-right corner)
   - Test chat functionality

9. **Test Dashboard Access**
   - Click "Log In to Dashboard" button from email
   - Should login with email and temporary password
   - Should see organization in dashboard
   - Change password recommended

---

## 🧪 Test Scenario 2: Existing User, Second Store

### Setup:
- Use same email as first test
- Different Shopify store

### Expected Behavior:

1. **No New User Created**
   ```
   ✅ Found existing user for Shopify installation
   ✅ Associated user with organization
   ```

2. **No Welcome Email**
   - User already has account
   - No email sent (check logs)

3. **Multiple Organizations**
   ```php
   $user = \App\Models\User::where('email', 'owner@example.com')->first();
   $user->organizations->count(); // Should be 2
   ```

4. **Dashboard Shows Both**
   - Login to dashboard
   - Check organization dropdown
   - Should list both stores
   - Can switch between them

---

## 🧪 Test Scenario 3: No Email from Shopify (Edge Case)

### Simulate:
This is hard to test naturally. To simulate:

1. **Temporarily Comment Out Email Fetch**
   In `IntegrationController.php`, temporarily change:
   ```php
   $shopOwnerEmail = null; // Force fallback
   ```

2. **Expected Flow**
   - OAuth completes
   - Organization created
   - Widget installed
   - **Redirects to**: `https://ai-chat.support/shopify/complete-setup`

3. **Complete Setup Page**
   - Shows form: Name, Email, Password
   - Submit form
   - User created
   - Auto-associated with org
   - Auto-logged in
   - Redirected to dashboard

4. **Verify**
   ```php
   $org = \App\Models\Organization::latest()->first();
   $org->users; // Should have user from manual setup
   ```

5. **Restore Code**
   - Uncomment email fetch logic
   - Test normal flow again

---

## 🔍 Debugging Common Issues

### Issue: No Email Received

**Check:**
```bash
# Laravel log
tail -n 50 /var/www/clients/client1/web64/web/laravel/storage/logs/laravel.log | grep -i mail

# Test email sending manually
cd /var/www/clients/client1/web64/web/laravel
php artisan tinker
```

```php
Mail::raw('Test email', function($message) {
    $message->to('your-email@example.com')->subject('Test');
});
```

**Solutions:**
- Verify MAIL_* settings in `.env`
- Check spam folder
- Use mail testing service (Mailtrap, MailHog)
- Check email logs on SMTP server

---

### Issue: User Not Auto-Logged In

**Check:**
```bash
# Look for login error
tail -n 100 laravel/storage/logs/laravel.log | grep -i "auto-login"
```

**Debug:**
```php
// In IntegrationController after Auth::login()
\Log::info('Auth check after login', [
    'authenticated' => Auth::check(),
    'user_id' => Auth::id(),
]);
```

---

### Issue: Widget Not Appearing

**Check:**
1. **ScriptTag Created?**
   ```php
   $org = \App\Models\Organization::where('slug', 'your-slug')->first();
   $integration = $org->integrations()->where('provider', 'shopify')->first();
   $integration->script_tag_id; // Should have ID
   ```

2. **Script Accessible?**
   ```bash
   curl -I "https://ai-chat.support/widget/your-slug/script.js"
   # Should return 200 OK
   ```

3. **Shopify Theme**
   - Some themes block external scripts
   - Check browser console for errors
   - Check Shopify theme settings

---

### Issue: Organization Not Created

**Check Logs:**
```bash
tail -n 200 laravel/storage/logs/laravel.log | grep -i "error\|exception"
```

**Common Causes:**
- Slug collision (unlikely but check)
- Database connection issues
- Validation failures

**Debug:**
```php
// Add in IntegrationController
\Log::info('About to create organization', [
    'name' => $orgName,
    'slug' => $slug,
]);
```

---

## 📊 Success Criteria

After testing, verify:

- [x] User account created automatically
- [x] User receives welcome email with credentials
- [x] User auto-logged in after OAuth
- [x] Dashboard accessible immediately
- [x] Organization associated correctly
- [x] Widget installed on Shopify store
- [x] Widget appears on storefront
- [x] Chat functionality works
- [x] Existing user can add second store
- [x] Fallback setup page works
- [x] No errors in logs
- [x] Database integrity maintained

---

## 📝 Test Results Template

Copy and fill this out after testing:

```
# Shopify Installation Test Results
Date: _______________
Tester: _______________

## Test 1: New User Installation
- [ ] Installation initiated successfully
- [ ] OAuth completed
- [ ] User created: YES / NO
- [ ] Email received: YES / NO
- [ ] Auto-logged in: YES / NO
- [ ] Dashboard accessible: YES / NO
- [ ] Widget visible: YES / NO
- [ ] Issues found: _______________

## Test 2: Existing User, Second Store
- [ ] No duplicate user created: YES / NO
- [ ] Second org associated: YES / NO
- [ ] No welcome email sent: YES / NO
- [ ] Both stores in dashboard: YES / NO
- [ ] Issues found: _______________

## Test 3: Fallback Setup
- [ ] Redirected to setup page: YES / NO
- [ ] Form submission works: YES / NO
- [ ] User created manually: YES / NO
- [ ] Auto-associated: YES / NO
- [ ] Issues found: _______________

## Overall Assessment
Pass: YES / NO
Notes: _______________
```

---

## 🚀 Production Deployment Checklist

Before going live:

1. **Email Configuration**
   - [ ] Production SMTP configured
   - [ ] Test email delivery
   - [ ] Verify SPF/DKIM records
   - [ ] Set proper FROM address

2. **Shopify App Review**
   - [ ] App submitted for review
   - [ ] Privacy policy added
   - [ ] Terms of service added
   - [ ] Support contact provided

3. **Monitoring**
   - [ ] Log monitoring set up
   - [ ] Error alerting configured
   - [ ] Usage analytics tracking
   - [ ] Email delivery monitoring

4. **Documentation**
   - [ ] User documentation created
   - [ ] FAQ updated
   - [ ] Support guides ready
   - [ ] Video tutorials (optional)

5. **Backup**
   - [ ] Database backups automated
   - [ ] Code versioned in Git
   - [ ] Rollback plan prepared

---

**Good luck with testing! 🎉**

If you encounter any issues, check the logs first and refer to the debugging section above.
