# Python Backend Integration - Exact Code Changes

## Overview
The widget chat flow is:
1. **Widget** → `AiChat.php` Livewire component
2. **AiChat.php** → Does Qdrant search + LLM chat
3. **LLM Chat** → Calls FastAPI `/llm/chat`

**Integration Point**: We need to add Shopify data retrieval in `AiChat.php sendMessage()` method, BEFORE Qdrant search.

---

## Step 1: Modify AiChat.php to Include Shopify Data

**File**: `laravel/app/Livewire/AiChat.php`

**Location**: Line ~220-240 (in `sendMessage()` method, right AFTER the "// ---- RETRIEVAL (Qdrant via nomic embeddings) ----" comment and BEFORE the actual Qdrant search)

### Add Shopify Integration Code

Find this section (around line 240):
```php
// ---- RETRIEVAL (Qdrant via nomic embeddings) ----
$allContext = [];
$topContext = [];
$context = '';
try {
    // Get organization slug for collection name
    $organization = Organization::find($this->selectedOrgId);
    $orgSlug = $organization ? str_replace('-', '_', $organization->slug) : "org_{$this->selectedOrgId}";
    $collectionName = $orgSlug;
```

**ADD THIS CODE RIGHT AFTER THE ABOVE** (before the Qdrant search):

```php
    // ---- SHOPIFY INTEGRATION ----
    $shopifyContext = '';
    try {
        // Check if organization has Shopify integration
        $integration = $organization->integrations()
            ->where('integration_type', 'shopify')
            ->whereNull('deleted_at')
            ->first();
        
        if ($integration && $integration->shop_domain) {
            // Detect if query needs Shopify data (products, orders, tracking)
            $needsShopify = $this->detectShopifyQuery($userMessage);
            
            if ($needsShopify) {
                \Log::info('Querying Shopify API', [
                    'org' => $orgSlug,
                    'shop' => $integration->shop_domain,
                    'query' => $userMessage
                ]);
                
                // Call Laravel API endpoint for Shopify data
                $shopifyResponse = \Http::timeout(10)->post(
                    config('app.url') . '/api/shopify/query',
                    [
                        'shop_domain' => $integration->shop_domain,
                        'query' => $userMessage,
                        'query_type' => 'auto'
                    ]
                );
                
                if ($shopifyResponse->successful()) {
                    $shopifyData = $shopifyResponse->json();
                    
                    if ($shopifyData['success'] && !empty($shopifyData['formatted_text'])) {
                        $shopifyContext = "\n\n=== LIVE STORE DATA ===\n";
                        $shopifyContext .= $shopifyData['formatted_text'];
                        $shopifyContext .= "\n=== END STORE DATA ===\n\n";
                        
                        \Log::info('Shopify data retrieved', [
                            'type' => $shopifyData['query_type'],
                            'has_data' => !empty($shopifyData['data']),
                            'context_length' => strlen($shopifyContext)
                        ]);
                    }
                } else {
                    \Log::warning('Shopify API request failed', [
                        'status' => $shopifyResponse->status(),
                        'body' => $shopifyResponse->body()
                    ]);
                }
            }
        }
    } catch (\Exception $e) {
        \Log::error('Shopify integration error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        // Continue without Shopify data - don't break the chat
    }
    // ---- END SHOPIFY INTEGRATION ----
```

### Add Helper Method to Detect Shopify Queries

**Location**: Add this method at the END of the `AiChat` class (before the final closing brace), around line 600:

```php
    /**
     * Detect if user query needs Shopify data
     */
    private function detectShopifyQuery(string $query): bool
    {
        $queryLower = mb_strtolower($query);
        
        // Keywords that indicate Shopify data is needed
        $shopifyKeywords = [
            // Products
            'product', 'products', 'item', 'items', 'sell', 'selling',
            'buy', 'buying', 'purchase', 'price', 'cost', 'catalog',
            'available', 'availability', 'stock', 'in stock', 'out of stock',
            'inventory',
            
            // Orders
            'order', 'orders', 'bought', 'purchased', 'my order',
            'order status', 'order number', '#',
            
            // Shipping
            'ship', 'shipping', 'delivery', 'track', 'tracking',
            'shipment', 'delivered', 'when will', 'where is',
        ];
        
        foreach ($shopifyKeywords as $keyword) {
            if (str_contains($queryLower, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
```

### Modify Context Building

**Location**: Find the section where $context is built from Qdrant results (around line 320)

Find this code:
```php
        // Build flattened text context for LLM and logs
        if (!empty($topContext)) {
            $context = "Relevant information:\n\n";
```

**MODIFY THE FIRST LINE** to prepend Shopify context:
```php
        // Build flattened text context for LLM and logs
        if (!empty($topContext)) {
            $context = $shopifyContext; // ADD SHOPIFY CONTEXT FIRST
            $context .= "Relevant information:\n\n";
```

This ensures Shopify data appears BEFORE FAQ/knowledge base data in the LLM context.

---

## Step 2: Update System Prompt to Use Shopify Data

**Location**: In `sendMessage()`, around line 490 where system prompt is built

Find this section:
```php
    if ($isOpenAI) {
        // Ultra-concise for GPT to minimize tokens
        $systemPrompt = "You're {$orgName}. Answer using context. Be brief, direct...
```

**ADD THIS INSTRUCTION** to both system prompts (OpenAI and Llama):

For OpenAI (line ~490):
```php
    if ($isOpenAI) {
        // Ultra-concise for GPT to minimize tokens
        $systemPrompt = "You're {$orgName}. Answer using context. **PRIORITIZE LIVE STORE DATA** if present. Be brief, direct. If referring to contact, ONLY use official: website={$orgMeta['website']}" .
```

For Llama (line ~500):
```php
    } else {
        // Standard prompt for Llama
        $systemPrompt = "Answer strictly for {$orgName} using ONLY provided context + user message. **If LIVE STORE DATA section exists, use it FIRST** for product/order/shipping questions. Speak as {$orgName} (we/our). Never say you are AI or use I/me/my. If address/price/timing/contact present: answer directly. If suggesting contact, ONLY use official details: website={$orgMeta['website']}" .
```

---

## Complete Modified sendMessage() Method Structure

Here's the logical flow after modifications:

```
1. User sends message
2. Check subscription
3. Add user message to chat
4. Run NLU (intent detection, slot extraction, query rewriting)
5. Check required slots
6. ✅ NEW: Query Shopify API if query contains product/order/shipping keywords
7. Search Qdrant for FAQ/knowledge base
8. Combine Shopify context + FAQ context
9. Build system prompt (instructing to prioritize Shopify data)
10. Call LLM with combined context
11. Display response to user
```

---

## Testing the Integration

### Test 1: Product Query
**Before**:
- User: "What products do you have?"
- Response: Generic message or "We are integrated with Shopify"

**After**:
- User: "What products do you have?"
- Response: "We have these products available: Test Widget ($29.99, In stock: 50), Sample Product ($49.99, In stock: 25), Demo Item ($19.99, Out of stock)"

### Test 2: Order Lookup
**Before**:
- User: "Where is my order #1001?"
- Response: "Please check your email for tracking info"

**After**:
- User: "Where is my order #1001?"
- Response: "Your order #1001 is fulfilled and shipped. Tracking number: TRACK123, shipped via FedEx. Items: Test Widget (1x $29.99)"

### Test 3: FAQ Query (No Shopify)
**Before**:
- User: "What are your office hours?"
- Response: "We are open Monday-Friday, 9 AM - 5 PM"

**After** (No change - uses FAQ data):
- User: "What are your office hours?"
- Response: "We are open Monday-Friday, 9 AM - 5 PM"

---

## Laravel Logs to Monitor

After making changes, monitor logs:

```bash
tail -f laravel/storage/logs/laravel.log | grep -i shopify
```

**Expected log entries**:
```
[2024-10-17] INFO Querying Shopify API {"org":"ai_chat_support","shop":"ai-chat-support.myshopify.com","query":"What products do you have?"}
[2024-10-17] INFO Shopify data retrieved {"type":"products","has_data":true,"context_length":450}
[2024-10-17] INFO [SHOPIFY API] Query received {"shop_domain":"ai-chat-support.myshopify.com","query":"What products do you have?","query_type":"auto"}
[2024-10-17] INFO [SHOPIFY API] Query successful {"shop_domain":"ai-chat-support.myshopify.com","query_type":"products","has_data":true}
```

---

## Error Handling

The integration includes comprehensive error handling:

1. **No Shopify Integration**: Silently skips Shopify query, continues with FAQ data
2. **Shopify API Timeout**: Logs warning, continues without Shopify data
3. **Shopify API Error**: Logs error, continues with FAQ data
4. **Invalid Response**: Logs warning, continues without Shopify data

**This ensures the chat never breaks** even if Shopify integration fails.

---

## Performance Considerations

### Current Flow Timing (Without Shopify):
- NLU: ~500ms
- Qdrant Search: ~300ms
- LLM Generation: ~2000ms
- **Total**: ~3000ms (3 seconds)

### New Flow Timing (With Shopify):
- NLU: ~500ms
- **Shopify Query: ~200ms** (NEW)
- Qdrant Search: ~300ms
- LLM Generation: ~2000ms
- **Total**: ~3200ms (3.2 seconds)

**Impact**: +200ms (6% slower, acceptable)

### Optimizations:
- Shopify API responses are cached (5-10 minutes)
- Only queries detected as Shopify-related make API call
- Timeout set to 10 seconds (prevents hanging)

---

## Deployment Steps

### 1. Deploy New Shopify App Version
```bash
cd /var/www/clients/client1/web64/web
npx @shopify/cli app deploy --force
```

### 2. Modify AiChat.php
- Add Shopify integration code (as shown above)
- Add detectShopifyQuery() helper method
- Modify context building
- Update system prompts

### 3. Test Laravel API Endpoints
```bash
# Test health
curl https://ai-chat.support/api/shopify/health

# Test org shop domain
curl https://ai-chat.support/api/organizations/ai-chat-support/shopify-domain

# Test product query
curl -X POST https://ai-chat.support/api/shopify/query \
  -H "Content-Type: application/json" \
  -d '{
    "shop_domain": "ai-chat-support.myshopify.com",
    "query": "What products do you have?",
    "query_type": "products"
  }'
```

### 4. Reinstall App on Dev Store
- Login to ai-chat-support.myshopify.com
- Apps → AI Chat Support → Uninstall
- Reinstall app (will request new permissions)
- Accept all scopes

### 5. Add Sample Products
- Products → Add product
- Create 3-5 sample products with prices and inventory

### 6. Test Widget Chat
- Open storefront: https://ai-chat-support.myshopify.com
- Click widget
- Test queries:
  - "What products do you sell?"
  - "Do you have test widget?"
  - "How much does sample product cost?"

### 7. Monitor Logs
```bash
# Laravel logs
tail -f laravel/storage/logs/laravel.log | grep -i shopify

# Check for successful queries
tail -f laravel/storage/logs/laravel.log | grep "Shopify data retrieved"
```

---

## Verification Checklist

- [ ] New app version deployed with expanded scopes
- [ ] App reinstalled on dev store with new permissions
- [ ] Sample products added to dev store
- [ ] AiChat.php modified with Shopify integration
- [ ] detectShopifyQuery() method added
- [ ] Context building modified to include Shopify data
- [ ] System prompts updated
- [ ] Laravel API endpoints tested with curl
- [ ] Widget displays on storefront
- [ ] Product query shows real products from store
- [ ] Order query works (if test order created)
- [ ] FAQ queries still work normally
- [ ] Laravel logs show Shopify API calls
- [ ] No errors in logs

---

## Next: Create Demo Screencast

Once the integration is working and verified:

1. Record screencast showing:
   - Installation process
   - Preferences configuration
   - **Widget chat answering product questions with REAL PRODUCTS**
   - Order tracking demo
   - Store info demo

2. Upload to YouTube (unlisted)

3. Resubmit app with screencast link

---

**Status**: Ready to implement  
**Estimated Time**: 30 minutes for code changes + 1 hour for testing  
**Risk**: Low (error handling prevents breaking chat)
