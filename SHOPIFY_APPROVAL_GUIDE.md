# Shopify App Approval Guide - Order Access

## Current Status

✅ **Working Features:**
- Product catalog queries
- Price comparisons  
- Store information
- Theme color detection
- Widget embedding

⚠️ **Requires Approval:**
- Order tracking
- Order status queries
- Customer order history

## Why Order Access Requires Approval

Shopify classifies order data as **Protected Customer Data**. Apps that access this data must go through an additional approval process to ensure they meet Shopify's privacy and security standards.

**Error Message:**
```
[API] This app is not approved to access REST endpoints with protected customer data
```

## Required API Scopes

The app now requests these scopes during installation:

1. ✅ `write_script_tags` - Inject chat widget into store
2. ✅ `read_products` - Access product catalog  
3. ⚠️ `read_orders` - Access order information (REQUIRES APPROVAL)
4. ✅ `read_themes` - Detect theme colors

## Steps to Get Shopify Approval

### 1. **Complete App Listing**
- App name: AI Chat Support
- App URL: https://ai-chat.support
- Privacy policy URL: https://ai-chat.support/privacy
- Support email: [your support email]

### 2. **Data Protection Requirements**

Document how you handle protected customer data:

- **Data Storage**: Orders are NOT stored permanently, only queried in real-time
- **Data Access**: Limited to answering customer support queries
- **Data Sharing**: No third-party sharing
- **Data Retention**: Not retained after chat session
- **Encryption**: All API calls over HTTPS

### 3. **Submit for Review**

1. Go to Shopify Partners Dashboard
2. Select your app
3. Navigate to **App Review** section
4. Submit app for review with protected customer data access request

### 4. **Review Process**

**Timeline**: 3-5 business days (typically)

**What Shopify Reviews:**
- Privacy policy compliance
- Data handling practices
- Security measures
- Use case justification
- GDPR compliance

### 5. **Testing Before Approval**

**Development Store Workaround:**
- Development stores may have different restrictions
- Use test orders created within the dev store
- Some protected data endpoints may work in development mode

## Current Behavior (Without Approval)

When customers ask about orders:

**Query**: "What is the status of my order #d10?"

**Response**: 
> "Order tracking is not available yet. Our app is pending Shopify approval to access order information. You can check your order status directly in your Shopify account or contact our support team."

This is handled gracefully with a helpful error message instead of technical errors.

## After Approval is Granted

Once approved, the system will automatically:

1. ✅ Search orders by order number (e.g., #1001, #d10)
2. ✅ Search orders by customer email
3. ✅ Show order status (pending, fulfilled, shipped)
4. ✅ Show payment status (paid, pending, refunded)
5. ✅ Show tracking information (tracking number, carrier, URL)
6. ✅ List order items and totals

**Example Working Response:**
```
Order #1001:
Status: Fulfilled
Payment: Paid
Total: USD 785.95
Tracking: 1234567890
Track at: https://tracking.example.com/1234567890
Items:
- The Compare at Price Snowboard (Qty: 1) - USD 785.95
```

## Technical Implementation

### Updated Files:
1. **IntegrationController.php** - Updated OAuth scopes to include `read_orders`
2. **ShopifyApiService.php** - Added 403 error detection and helpful error messages
3. **Widget** - Already handles order queries, just waiting for API approval

### Error Handling:
- Detects 403 "protected customer data" errors
- Returns user-friendly message
- Logs warning for monitoring
- Doesn't break chat experience

## Next Steps

1. ✅ Update app scopes (DONE)
2. ✅ Add error handling (DONE)  
3. ⏳ Submit app for Shopify review
4. ⏳ Wait for approval (3-5 days)
5. ⏳ Test with real orders after approval
6. ✅ Deploy to production

## Support URL for Shopify Team

**Test Store**: https://ai-chat-support.myshopify.com  
**Widget Demo**: Chat widget visible on store  
**Test Queries**:
- "Do you have snowboards?" ✅ Works
- "What is the cheapest snowboard?" ✅ Works  
- "What is the status of my order #d10?" ⚠️ Pending approval

---

**Documentation Date**: October 27, 2025  
**App Version**: 1.0  
**Contact**: [Your support email]
