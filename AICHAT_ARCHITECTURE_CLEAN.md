# AiChat.php Integration Architecture - Revised Clean Design

## Overview
This document explains the clean architecture for handling different data sources in the AI chat.

---

## Architecture Principles

### 1. Laravel is the Orchestrator
Laravel's AiChat.php component acts as the **orchestrator** that:
- Receives user query
- Detects what data sources are needed
- Fetches data from appropriate sources
- Combines contexts
- Sends to FastAPI for LLM response

### 2. FastAPI is the AI Engine
FastAPI is **only** responsible for:
- Embedding generation (nomic-embed-text)
- Vector search (Qdrant)
- LLM inference (Llama 3.2)

### 3. Laravel Handles All External APIs
Laravel directly calls:
- Shopify Admin API
- Google Sheets API
- Database queries
- Any other external services

---

## Query Routing Logic

### Step 1: Detect Integration Types Available

```php
private function getAvailableIntegrations($organization): array
{
    $integrations = [];
    
    // Check Shopify
    $shopifyIntegration = $organization->integrations()
        ->where('integration_type', 'shopify')
        ->whereNull('deleted_at')
        ->first();
    
    if ($shopifyIntegration && $shopifyIntegration->shop_domain) {
        $integrations['shopify'] = $shopifyIntegration;
    }
    
    // Check Google Sheets (future)
    $sheetsIntegration = $organization->integrations()
        ->where('integration_type', 'google_sheets')
        ->whereNull('deleted_at')
        ->first();
    
    if ($sheetsIntegration) {
        $integrations['google_sheets'] = $sheetsIntegration;
    }
    
    // Always have knowledge base (Qdrant)
    $integrations['knowledge_base'] = true;
    
    return $integrations;
}
```

### Step 2: Detect Query Intent

```php
private function detectQueryIntent(string $query): array
{
    $queryLower = mb_strtolower($query);
    $intents = [];
    
    // Shopify intent keywords
    $shopifyKeywords = [
        'products' => ['product', 'item', 'sell', 'buy', 'catalog', 'price', 'cost'],
        'orders' => ['order', '#', 'bought', 'purchased', 'order status'],
        'shipping' => ['track', 'tracking', 'shipment', 'delivery', 'ship'],
        'inventory' => ['stock', 'available', 'availability', 'in stock'],
    ];
    
    foreach ($shopifyKeywords as $intent => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($queryLower, $keyword)) {
                $intents[] = 'shopify_' . $intent;
                break 2; // Found Shopify intent, exit
            }
        }
    }
    
    // Google Sheets intent keywords (future)
    $sheetsKeywords = ['spreadsheet', 'sheet', 'row', 'column', 'data entry', 'table'];
    foreach ($sheetsKeywords as $keyword) {
        if (str_contains($queryLower, $keyword)) {
            $intents[] = 'google_sheets';
            break;
        }
    }
    
    // Database query intent (account, subscription, etc.)
    $dbKeywords = ['my account', 'subscription', 'payment', 'invoice', 'billing'];
    foreach ($dbKeywords as $keyword) {
        if (str_contains($queryLower, $keyword)) {
            $intents[] = 'database';
            break;
        }
    }
    
    // Always include knowledge base search
    $intents[] = 'knowledge_base';
    
    return array_unique($intents);
}
```

### Step 3: Fetch Data from Appropriate Sources

```php
private function fetchContextData(array $intents, array $integrations, string $query): array
{
    $contexts = [];
    
    // 1. Shopify Data (if needed and available)
    if (in_array('shopify_products', $intents) || 
        in_array('shopify_orders', $intents) || 
        in_array('shopify_shipping', $intents) ||
        in_array('shopify_inventory', $intents)) {
        
        if (isset($integrations['shopify'])) {
            $contexts['shopify'] = $this->fetchShopifyData($integrations['shopify'], $query);
        }
    }
    
    // 2. Google Sheets Data (if needed and available)
    if (in_array('google_sheets', $intents)) {
        if (isset($integrations['google_sheets'])) {
            $contexts['google_sheets'] = $this->fetchGoogleSheetsData($integrations['google_sheets'], $query);
        }
    }
    
    // 3. Database Data (if needed)
    if (in_array('database', $intents)) {
        $contexts['database'] = $this->fetchDatabaseData($query);
    }
    
    // 4. Knowledge Base (always)
    if (in_array('knowledge_base', $intents)) {
        $contexts['knowledge_base'] = $this->fetchKnowledgeBase($query);
    }
    
    return $contexts;
}
```

### Step 4: Individual Data Fetchers

```php
/**
 * Fetch Shopify data via Laravel API
 */
private function fetchShopifyData($integration, string $query): string
{
    try {
        \Log::info('Fetching Shopify data', [
            'shop' => $integration->shop_domain,
            'query' => $query
        ]);
        
        // Call Laravel's own API endpoint (internal call)
        $response = \Http::timeout(10)->post(
            config('app.url') . '/api/shopify/query',
            [
                'shop_domain' => $integration->shop_domain,
                'query' => $query,
                'query_type' => 'auto'
            ]
        );
        
        if ($response->successful()) {
            $data = $response->json();
            
            if ($data['success'] && !empty($data['formatted_text'])) {
                \Log::info('Shopify data retrieved', [
                    'type' => $data['query_type'],
                    'length' => strlen($data['formatted_text'])
                ]);
                
                return "\n\n=== LIVE SHOPIFY DATA ===\n" . 
                       $data['formatted_text'] . 
                       "\n=== END SHOPIFY DATA ===\n\n";
            }
        }
        
        return '';
        
    } catch (\Exception $e) {
        \Log::error('Shopify data fetch failed', ['error' => $e->getMessage()]);
        return '';
    }
}

/**
 * Fetch Google Sheets data (future implementation)
 */
private function fetchGoogleSheetsData($integration, string $query): string
{
    try {
        // TODO: Implement Google Sheets API integration
        // Similar pattern to Shopify
        
        return '';
        
    } catch (\Exception $e) {
        \Log::error('Google Sheets fetch failed', ['error' => $e->getMessage()]);
        return '';
    }
}

/**
 * Fetch database data (user account, subscriptions, etc.)
 */
private function fetchDatabaseData(string $query): string
{
    try {
        $user = \Auth::user();
        if (!$user) return '';
        
        $context = "\n\n=== USER ACCOUNT DATA ===\n";
        
        // Example: Subscription info
        if (str_contains(strtolower($query), 'subscription')) {
            $subscription = $user->activeSubscription;
            if ($subscription) {
                $context .= "Subscription: {$subscription->plan->name}\n";
                $context .= "Status: Active\n";
                $context .= "Expires: {$subscription->ends_at->format('Y-m-d')}\n";
            }
        }
        
        // Example: Payment history
        if (str_contains(strtolower($query), 'payment') || 
            str_contains(strtolower($query), 'invoice')) {
            $payments = $user->payments()->latest()->take(3)->get();
            foreach ($payments as $payment) {
                $context .= "Payment: {$payment->amount} on {$payment->created_at->format('Y-m-d')}\n";
            }
        }
        
        $context .= "=== END ACCOUNT DATA ===\n\n";
        
        return $context;
        
    } catch (\Exception $e) {
        \Log::error('Database fetch failed', ['error' => $e->getMessage()]);
        return '';
    }
}

/**
 * Fetch knowledge base via Qdrant (existing implementation)
 */
private function fetchKnowledgeBase(string $query): string
{
    try {
        // Existing Qdrant search logic
        // ... (your current implementation)
        
        return $context;
        
    } catch (\Exception $e) {
        \Log::error('Knowledge base fetch failed', ['error' => $e->getMessage()]);
        return "No specific information found in the knowledge base.";
    }
}
```

### Step 5: Combine and Prioritize Contexts

```php
private function combineContexts(array $contexts): string
{
    $combined = '';
    
    // Priority order: Live data first, then knowledge base
    
    // 1. Shopify data (highest priority for commerce queries)
    if (isset($contexts['shopify']) && !empty($contexts['shopify'])) {
        $combined .= $contexts['shopify'];
    }
    
    // 2. Database data (user-specific info)
    if (isset($contexts['database']) && !empty($contexts['database'])) {
        $combined .= $contexts['database'];
    }
    
    // 3. Google Sheets data (custom data)
    if (isset($contexts['google_sheets']) && !empty($contexts['google_sheets'])) {
        $combined .= $contexts['google_sheets'];
    }
    
    // 4. Knowledge base (general FAQs)
    if (isset($contexts['knowledge_base']) && !empty($contexts['knowledge_base'])) {
        $combined .= "\n\n=== KNOWLEDGE BASE ===\n";
        $combined .= $contexts['knowledge_base'];
        $combined .= "\n=== END KNOWLEDGE BASE ===\n\n";
    }
    
    return $combined ?: "No relevant information found.";
}
```

---

## Revised sendMessage() Implementation

Here's the clean integration in the main `sendMessage()` method:

```php
public function sendMessage()
{
    // ... (existing validation and setup code)
    
    // Get organization
    $organization = Organization::find($this->selectedOrgId);
    
    // ============================================
    // STEP 1: Detect what integrations are available
    // ============================================
    $availableIntegrations = $this->getAvailableIntegrations($organization);
    
    \Log::info('Available integrations', [
        'org' => $organization->slug,
        'integrations' => array_keys($availableIntegrations)
    ]);
    
    // ============================================
    // STEP 2: Detect what type of query this is
    // ============================================
    $queryIntents = $this->detectQueryIntent($userMessage);
    
    \Log::info('Query intents detected', [
        'query' => $userMessage,
        'intents' => $queryIntents
    ]);
    
    // ============================================
    // STEP 3: Fetch data from appropriate sources
    // ============================================
    $contexts = $this->fetchContextData($queryIntents, $availableIntegrations, $userMessage);
    
    // ============================================
    // STEP 4: Combine all contexts
    // ============================================
    $fullContext = $this->combineContexts($contexts);
    
    \Log::info('Combined context', [
        'sources' => array_keys($contexts),
        'total_length' => strlen($fullContext)
    ]);
    
    // ============================================
    // STEP 5: Build system prompt
    // ============================================
    $systemPrompt = $this->buildSystemPrompt($organization, $fullContext);
    
    // ============================================
    // STEP 6: Call LLM (FastAPI) with combined context
    // ============================================
    $chatMessages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userMessage]
    ];
    
    $response = $aiService->smartLlmChat($chatMessages, null, $user->id, $this->selectedOrgId);
    
    // ... (rest of existing code)
}
```

---

## System Prompt Updates

```php
private function buildSystemPrompt($organization, $context): string
{
    $orgName = $organization->name ?? 'this organization';
    
    $prompt = "You are the AI assistant for {$orgName}.\n\n";
    $prompt .= "IMPORTANT INSTRUCTIONS:\n";
    $prompt .= "1. If LIVE SHOPIFY DATA is present, use it FIRST for product/order/shipping questions\n";
    $prompt .= "2. If USER ACCOUNT DATA is present, use it for subscription/payment questions\n";
    $prompt .= "3. If KNOWLEDGE BASE data is present, use it for general FAQs\n";
    $prompt .= "4. Always prioritize live data over general knowledge\n";
    $prompt .= "5. Be concise and direct (2-3 sentences max)\n";
    $prompt .= "6. Never invent information not in the context\n\n";
    $prompt .= "CONTEXT:\n{$context}\n\n";
    
    return $prompt;
}
```

---

## Benefits of This Architecture

### ✅ Clear Separation of Concerns
- **Laravel**: Business logic, external APIs, database
- **FastAPI**: AI/ML tasks only (embeddings, vector search, LLM)

### ✅ Easy to Extend
Adding new integrations (Google Sheets, CRM, etc.) is straightforward:
1. Add detection keywords
2. Add fetch method
3. Add to combination logic

### ✅ Debuggable
Each step is logged:
```
[INFO] Available integrations: {"shopify": true, "knowledge_base": true}
[INFO] Query intents detected: ["shopify_products", "knowledge_base"]
[INFO] Fetching Shopify data: {"shop": "store.myshopify.com"}
[INFO] Shopify data retrieved: {"type": "products", "length": 450}
[INFO] Combined context: {"sources": ["shopify", "knowledge_base"], "total_length": 1250}
```

### ✅ Performant
- Parallel fetching possible (if needed)
- Caching at each layer
- Early exit if no relevant integration

### ✅ Resilient
- Each fetch wrapped in try-catch
- Failure in one source doesn't break others
- Graceful degradation

---

## Migration Path

### Current Code → New Architecture

1. **Keep existing code** for knowledge base search
2. **Add** integration detection before search
3. **Add** Shopify/other fetchers
4. **Modify** context building to combine sources
5. **Update** system prompt

No breaking changes - just additions!

---

## Summary

**Your instinct was 100% correct**: Laravel should handle Shopify API calls, not FastAPI.

**My implementation already does this correctly**:
- ShopifyApiService.php (Laravel) → Shopify Admin API ✅
- ShopifyDataController.php (Laravel) → Internal endpoint ✅
- AiChat.php (Laravel) → Calls Laravel API, not FastAPI ✅
- FastAPI → Only used for LLM chat ✅

**This architecture**:
- ✅ Keeps FastAPI focused on AI/ML
- ✅ Leverages Laravel's strengths (auth, DB, APIs)
- ✅ Easy to add Google Sheets, CRM, etc.
- ✅ Clear intent detection and routing
- ✅ Debuggable with comprehensive logging

Ready to proceed with deployment?
