# Shopify API Integration Implementation Plan

## Current Status: App Rejected - Need Shopify API Integration

**Rejection Reference**: #93967  
**Rejection Date**: October 17, 2024  
**Rejection Reason**: "Your app doesn't use or need Shopify's API"

### Key Issues Identified:
1. ✅ **Scopes Added** - shopify.app.ai-chat-support.toml updated with read_products, read_orders, read_fulfillments, read_shipping, read_customers, read_inventory
2. ⚠️ **Deployment Pending** - New version needs to be deployed with expanded scopes
3. ❌ **Backend Integration Missing** - AI doesn't call Shopify API for live data
4. ❌ **Demo Screencast Missing** - Required for resubmission

---

## Implementation Steps

### Step 1: Deploy New Shopify App Version ✅ READY
**Status**: Configuration complete, ready to deploy  
**Files Modified**: shopify.app.ai-chat-support.toml

**Command to Run**:
```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app deploy --force
```

**Expected Result**:
- Creates web-5 (or next version number)
- New version becomes active with expanded scopes
- Merchants see permission request for new scopes on next login

**Verification**:
```bash
npx @shopify/cli app versions list
```

**New Scopes Being Deployed**:
- `read_script_tags` - Widget injection (existing)
- `write_script_tags` - Widget management (existing)
- `read_products` - Product catalog access ✅ NEW
- `read_orders` - Order status lookup ✅ NEW
- `read_fulfillments` - Shipping tracking ✅ NEW
- `read_shipping` - Shipping rates/methods ✅ NEW
- `read_customers` - Customer data access ✅ NEW
- `read_inventory` - Stock availability ✅ NEW

---

### Step 2: Laravel API Layer ✅ COMPLETED

**Created Files**:
1. `app/Services/ShopifyApiService.php` - Shopify Admin API wrapper
2. `app/Http/Controllers/Api/ShopifyDataController.php` - API endpoint for Python backend
3. Updated `routes/api.php` - Added /api/shopify/* routes

**Available API Endpoints**:

#### POST /api/shopify/query
Main endpoint for AI backend to query Shopify data.

**Request**:
```json
{
  "shop_domain": "ai-chat-support.myshopify.com",
  "query": "What products do you have?",
  "query_type": "auto"
}
```

**Query Types**:
- `auto` - Auto-detect based on query content (default)
- `products` - Search products
- `order` - Lookup order by number/email
- `shop_info` - Get store information

**Response**:
```json
{
  "success": true,
  "query_type": "products",
  "data": [
    {
      "id": 123456,
      "title": "Product Name",
      "description": "Product description...",
      "price": "29.99",
      "currency": "USD",
      "available": true,
      "inventory": 50,
      "url": "https://store.myshopify.com/products/handle"
    }
  ],
  "formatted_text": "Products:\n- Product Name: USD 29.99 (In stock: 50)\n  Description: Product description...\n  URL: https://..."
}
```

#### GET /api/shopify/shop/{shop_domain}
Get shop information directly.

#### GET /api/shopify/health
Health check endpoint.

**Service Methods Available**:
- `searchProducts(query, limit)` - Search products by title
- `getProduct(id)` - Get single product details
- `getAllProducts(limit)` - Get all active products
- `searchOrder(orderIdentifier)` - Find order by number or ID
- `getCustomerOrders(email, limit)` - Get orders by customer email
- `getOrderTracking(orderId)` - Get fulfillment/tracking info
- `getShopInfo()` - Get store details
- `formatForAI(data, type)` - Format data for LLM context

**Caching Strategy**:
- Products: 10 minutes (600s)
- Shop info: 1 hour (3600s)
- Search results: 5 minutes (300s)
- Rate limit compliance: 2 requests/second max

---

### Step 3: Python Backend Integration ⚠️ IN PROGRESS

**File to Modify**: `ai_backend/main.py`

**Implementation Required**:

#### 3.1 Add HTTP client for Laravel API
```python
import httpx
from typing import Optional, Dict, Any

LARAVEL_API_URL = "https://ai-chat.support/api"

async def query_shopify_data(shop_domain: str, query: str, query_type: str = "auto") -> Optional[Dict[str, Any]]:
    """Query Shopify data via Laravel API"""
    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{LARAVEL_API_URL}/shopify/query",
                json={
                    "shop_domain": shop_domain,
                    "query": query,
                    "query_type": query_type
                },
                timeout=10.0
            )
            
            if response.status_code == 200:
                return response.json()
            else:
                logger.error(f"Shopify API error: {response.status_code} - {response.text}")
                return None
    except Exception as e:
        logger.error(f"Failed to query Shopify: {str(e)}")
        return None
```

#### 3.2 Update chat endpoint to detect Shopify queries
```python
@app.post("/chat")
async def chat(request: ChatRequest):
    """Enhanced chat with Shopify integration"""
    
    # Get organization and check if it's a Shopify store
    shop_domain = await get_shop_domain_for_org(request.organization_slug)
    
    # Detect if query needs Shopify data
    query_lower = request.message.lower()
    needs_shopify = any(keyword in query_lower for keyword in [
        'product', 'order', 'tracking', 'price', 'buy', 'sell',
        'available', 'stock', 'inventory', 'shipping', 'delivery'
    ])
    
    shopify_context = ""
    
    if needs_shopify and shop_domain:
        # Query Shopify API
        shopify_data = await query_shopify_data(shop_domain, request.message)
        
        if shopify_data and shopify_data.get('success'):
            shopify_context = shopify_data.get('formatted_text', '')
            logger.info(f"Added Shopify context: {len(shopify_context)} chars")
    
    # Existing FAQ search
    faq_context = await search_qdrant(request.organization_slug, request.message)
    
    # Combine contexts
    full_context = f"{shopify_context}\n\n{faq_context}"
    
    # Generate response with both contexts
    response = await generate_llm_response(request.message, full_context)
    
    return {"response": response}
```

#### 3.3 Add shop domain lookup
```python
async def get_shop_domain_for_org(org_slug: str) -> Optional[str]:
    """Get Shopify shop domain for organization"""
    # Query Laravel database or add endpoint
    # For now, can be added to organization metadata
    # Or create new endpoint: GET /api/shopify/org/{org_slug}/shop-domain
    pass
```

**Key Integration Points**:
1. Detect Shopify-related queries (products, orders, tracking)
2. Call Laravel API endpoint with shop_domain + query
3. Append formatted_text to LLM context
4. Generate response using both FAQ + Shopify data
5. Log Shopify API usage for debugging

---

### Step 4: Database Schema Updates (Optional)

**Add shop_domain to organizations table**:
```sql
ALTER TABLE organizations 
ADD COLUMN shopify_domain VARCHAR(255) NULL 
AFTER slug;

-- Update existing Shopify organizations
UPDATE organizations o
INNER JOIN integrations i ON o.id = i.organization_id
SET o.shopify_domain = i.shop_domain
WHERE i.integration_type = 'shopify';
```

**Or use existing integrations table** (Recommended):
```php
// In Python backend, query Laravel API:
// GET /api/shopify/org/{org_slug}/shop-domain
// Returns: {"shop_domain": "store.myshopify.com"}
```

---

### Step 5: Testing Workflow

#### 5.1 Test Laravel API Layer
```bash
# Test health endpoint
curl https://ai-chat.support/api/shopify/health

# Test product query
curl -X POST https://ai-chat.support/api/shopify/query \
  -H "Content-Type: application/json" \
  -d '{
    "shop_domain": "ai-chat-support.myshopify.com",
    "query": "What products do you have?",
    "query_type": "products"
  }'

# Test order lookup
curl -X POST https://ai-chat.support/api/shopify/query \
  -H "Content-Type: application/json" \
  -d '{
    "shop_domain": "ai-chat-support.myshopify.com",
    "query": "Where is order #1001?",
    "query_type": "order"
  }'
```

#### 5.2 Test Python Backend Integration
```bash
# Test chat with Shopify integration
curl -X POST http://localhost:8111/chat \
  -H "Content-Type: application/json" \
  -d '{
    "organization_slug": "ai-chat-support",
    "message": "What products do you sell?",
    "session_id": "test-123"
  }'
```

#### 5.3 Test End-to-End via Widget
1. Install app on ai-chat-support.myshopify.com with new scopes
2. Add sample products to dev store
3. Create test order with fulfillment
4. Open storefront, click widget
5. Test queries:
   - "What products do you have?"
   - "Do you sell [product name]?"
   - "Where is my order #1001?"
   - "Track my shipment"
   - "What is your store address?"

**Expected Results**:
- ✅ Chat shows actual product names from store
- ✅ Chat shows real prices and availability
- ✅ Order lookup returns status and tracking
- ✅ Store info shows real contact details

---

### Step 6: Demo Screencast Creation

**Requirements** (per Shopify):
- Demonstrate full onboarding and feature usage
- English language or English subtitles
- Show merchant perspective (installation → configuration → usage)
- Length: 2-5 minutes recommended

**Screencast Outline**:

1. **Introduction** (15 seconds)
   - "AI Chat Support for Shopify"
   - "Automated customer support powered by AI"

2. **Installation** (30 seconds)
   - Navigate to Shopify App Store (or install link)
   - Click "Add app"
   - Show OAuth permission screen with scopes
   - Click "Install app"
   - Redirect to success page

3. **Configuration** (45 seconds)
   - Navigate to Apps → AI Chat Support
   - Show Preferences page
   - Enable widget toggle
   - Select position (bottom-right)
   - Pick primary color
   - Enter welcome message
   - Click "Save Preferences"
   - Show success message

4. **Storefront Integration** (30 seconds)
   - Open storefront in new tab
   - Show widget appears in bottom-right
   - Click widget to open chat
   - Show welcome message

5. **Product Query Demo** (45 seconds)
   - Type: "What products do you sell?"
   - Show AI response with actual product names and prices
   - Type: "Do you have [specific product]?"
   - Show AI response with product details and stock status

6. **Order Tracking Demo** (45 seconds)
   - Type: "Where is my order #1001?"
   - Show AI response with order status
   - Show tracking number and link
   - Show order items list

7. **Store Info Demo** (30 seconds)
   - Type: "What is your store address?"
   - Show AI response with contact details
   - Show store hours/phone/email

8. **Conclusion** (15 seconds)
   - "AI Chat Support - Automated customer service"
   - "Uses Shopify API for live product and order data"
   - "Available in the Shopify App Store"

**Recording Tools**:
- OBS Studio (free, open-source)
- Loom (freemium, easy to use)
- ScreenFlow (macOS, paid)
- Camtasia (Windows/macOS, paid)

**Upload**:
- YouTube (unlisted)
- Copy link for app listing submission

---

### Step 7: App Listing Updates

**Update App Description**:
```
AI Chat Support provides automated customer service for your Shopify store 
using advanced AI technology. The widget integrates seamlessly with your 
storefront and answers customer questions about:

✅ Product catalog - Search and recommend products
✅ Order status - Track orders and shipments
✅ Store information - Contact details and policies
✅ Custom FAQs - Your specific business information

**Shopify Integration**:
Uses Shopify API to access live product data, order status, inventory 
levels, and shipping tracking information. All responses are personalized 
to your actual store data.

**Features**:
- Customizable widget (position, color, welcome message)
- Multi-language support (8 languages)
- Real-time product search
- Order tracking and status updates
- 24/7 automated support
- Easy setup - no coding required
```

**Update Features List**:
- ✅ Real-time product catalog search
- ✅ Order status lookup and tracking
- ✅ Shipping tracking information
- ✅ Inventory availability checks
- ✅ Store information and contact details
- ✅ Custom FAQ management
- ✅ Customizable widget design
- ✅ Multi-language support (8 languages)

**Technical Details**:
```
API Integrations:
- Shopify Admin API 2025-01
- Products API (read_products)
- Orders API (read_orders)
- Fulfillments API (read_fulfillments)
- Inventory API (read_inventory)
- ScriptTag API (widget injection)

Permissions Required:
- read_products - Display product information
- read_orders - Order status lookup
- read_fulfillments - Shipping tracking
- read_shipping - Shipping methods
- read_customers - Customer order history
- read_inventory - Stock availability
- read_script_tags, write_script_tags - Widget management
```

---

### Step 8: Resubmission Checklist

Before resubmitting to Shopify App Store:

#### Technical Requirements
- [x] All scopes added to shopify.app.ai-chat-support.toml
- [ ] New version deployed and active
- [ ] Laravel API endpoints functional and tested
- [ ] Python backend calls Laravel API successfully
- [ ] Chat shows real products from test store
- [ ] Order lookup returns real order data
- [ ] Tracking info displays correctly
- [ ] Widget appears on storefront
- [ ] Preferences page functional

#### Documentation Requirements
- [ ] Demo screencast created (2-5 minutes)
- [ ] Screencast uploaded to YouTube (unlisted)
- [ ] App description updated with API usage details
- [ ] Features list updated with Shopify integration
- [ ] Technical documentation complete
- [ ] Test credentials provided (if needed)

#### Testing Evidence
- [ ] Screenshots of chat showing products
- [ ] Screenshots of order lookup working
- [ ] Screenshots of Preferences page
- [ ] Log excerpts showing API calls
- [ ] Video demonstration complete

#### Submission
- [ ] Partner Dashboard → Distribution → Create new submission
- [ ] Reference previous rejection (#93967)
- [ ] Explain changes made
- [ ] Include screencast link
- [ ] Provide test store credentials
- [ ] Submit for review

---

## Next Immediate Actions

### Priority 1: Deploy New Version (5 minutes)
```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app deploy --force
npx @shopify/cli app versions list
```

### Priority 2: Test Laravel API (15 minutes)
```bash
# Test endpoints manually with curl
curl https://ai-chat.support/api/shopify/health
curl -X POST https://ai-chat.support/api/shopify/query -H "Content-Type: application/json" -d '...'

# Check Laravel logs
tail -f laravel/storage/logs/laravel.log
```

### Priority 3: Update Python Backend (30 minutes)
- Add httpx dependency to requirements.txt
- Implement query_shopify_data() function
- Update /chat endpoint with Shopify detection
- Add logging for Shopify API calls
- Test locally

### Priority 4: End-to-End Testing (20 minutes)
- Reinstall app on dev store
- Add sample products
- Test widget chat with product queries
- Create test order and test tracking
- Verify responses show real data

### Priority 5: Create Screencast (60 minutes)
- Set up recording software
- Prepare script/outline
- Record demo following outline above
- Upload to YouTube (unlisted)
- Add link to notes

### Priority 6: Resubmit App (15 minutes)
- Update app listing
- Fill resubmission form
- Reference rejection #93967
- Include screencast link
- Submit

---

## Files Created/Modified

### New Files
1. `/var/www/clients/client1/web64/web/laravel/app/Services/ShopifyApiService.php`
2. `/var/www/clients/client1/web64/web/laravel/app/Http/Controllers/Api/ShopifyDataController.php`

### Modified Files
1. `/var/www/clients/client1/web64/web/shopify.app.ai-chat-support.toml` - Added scopes
2. `/var/www/clients/client1/web64/web/laravel/routes/api.php` - Added routes

### Files to Modify
1. `/var/www/clients/client1/web64/web/ai_backend/main.py` - Add Shopify integration
2. `/var/www/clients/client1/web64/web/ai_backend/requirements.txt` - Add httpx

---

## Expected Timeline

- **Deploy + Test Laravel**: 1 hour
- **Python Backend Integration**: 2 hours
- **End-to-End Testing**: 1 hour
- **Create Screencast**: 1-2 hours
- **Update Listing + Resubmit**: 1 hour

**Total Estimated Time**: 6-7 hours of focused work

---

## Success Criteria

✅ **Technical Success**:
- Chat widget shows real products from Shopify store
- Order lookup returns actual order data
- Tracking info displays correctly
- API calls logged and working

✅ **Submission Success**:
- Comprehensive screencast demonstrates all features
- App listing clearly explains Shopify API usage
- All automated checks pass
- App approved by Shopify team

✅ **User Experience**:
- Merchants can customize widget via Preferences
- Customers get accurate product/order information
- Widget works on storefront without issues
- Performance is acceptable (< 3s response time)

---

## Contact & Support

**Current App Details**:
- App Name: ai-chat-support
- Client ID: d9d8ed2dd9a7d99e67ca61fd135da57c
- Active Version: web-4 (will become web-5 after deployment)
- Rejection Reference: #93967

**Test Environment**:
- Store: ai-chat-support.myshopify.com
- Admin: info@ai.chat.support / password123
- Customer: customer@ai-chat.support / pragati123..

---

**Document Version**: 1.0  
**Last Updated**: October 17, 2024  
**Status**: Ready for implementation
