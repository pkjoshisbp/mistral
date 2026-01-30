<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Analytics;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Mail\ChatInteractionNotification;
use App\Mail\LeadCapturedNotification;
use App\Models\Lead;
use App\Services\IntentDetectionService;
use App\Services\AiAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WidgetController
{
    private $aiAgentService;

    public function __construct(AiAgentService $aiAgentService)
    {
        $this->aiAgentService = $aiAgentService;
    }

    /**
     * Generate widget script for embedding
     */
    public function getWidgetScript($orgId)
    {
        // Support both numeric ID and slug
        if (is_numeric($orgId)) {
            $organization = Organization::find($orgId);
        } else {
            $organization = Organization::where('slug', $orgId)->first();
        }
        
        if (!$organization || !$organization->is_active) {
            return response('Organization not found or inactive', 404);
        }

        // Use organization settings as single source of truth
        $settings = $organization->settings ?? [];
        
        // Check if organization has active Shopify integration
        $hasShopifyIntegration = $organization->integrations()
            ->where('provider', 'shopify')
            ->where('active', true)
            ->exists();

        $scriptVersion = now()->format('Ymd.His');
        $widgetConfig = [
            'orgId' => $orgId,
            'orgName' => $organization->name,
            'apiUrl' => config('app.url'),
            'theme' => $settings['widget_theme'] ?? 'default',
            'position' => $settings['widget_position'] ?? 'bottom-right',
            'offsetX' => (int)($settings['widget_offset_x'] ?? 20),
            'offsetY' => (int)($settings['widget_offset_y'] ?? 20),
            'primaryColor' => $settings['primary_color'] ?? '#007bff',
            'welcomeMessage' => $settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'chatHistoryTtlHours' => (int)($settings['chat_history_ttl_hours'] ?? 24),
            'requireContactForGuests' => (bool)($settings['require_contact_for_guests'] ?? false),
            // Branding/backlink controls (defaults: enabled + dofollow)
            'brandingEnabled' => array_key_exists('branding_enabled', $settings) ? (bool)$settings['branding_enabled'] : true,
            'brandingFollow' => array_key_exists('branding_follow', $settings) ? (bool)$settings['branding_follow'] : true,
            'brandingBadge' => (bool)($settings['branding_badge'] ?? false),
            // Shopify integration flag
            'isShopify' => $hasShopifyIntegration,
        ];

        $script = view('widget.script', compact('widgetConfig'))->render();

        Log::info('Serving widget script', [
            'org_id' => $orgId,
            'org_slug' => $organization->slug,
            'version' => $scriptVersion
        ]);

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Access-Control-Allow-Origin', '*')
            // Disable caching to ensure latest fixes are delivered to widgets
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-AI-Widget-Version', $scriptVersion)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Get widget CSS styles
     */
    public function getWidgetCSS($orgId)
    {
        // Support both numeric ID and slug
        if (is_numeric($orgId)) {
            $organization = Organization::find($orgId);
        } else {
            $organization = Organization::where('slug', $orgId)->first();
        }
        
        if (!$organization || !$organization->is_active) {
            return response('Organization not found or inactive', 404);
        }

        // Use organization settings as single source of truth
        $settings = $organization->settings ?? [];

        $theme = [
            'primaryColor' => $settings['primary_color'] ?? '#007bff',
            'secondaryColor' => $settings['secondary_color'] ?? '#f8f9fa',
            'textColor' => $settings['text_color'] ?? '#333333',
            'borderRadius' => $settings['border_radius'] ?? '10px'
        ];

        $css = view('widget.styles', compact('theme'))->render();

        return response($css)
            ->header('Content-Type', 'text/css')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Handle chat messages from widget
     */
    public function chat(Request $request, $orgId)
    {
        try {
            // Try to find organization by ID first, then by slug
            $organization = is_numeric($orgId) 
                ? Organization::find($orgId) 
                : Organization::where('slug', $orgId)->first();
            
            if (!$organization || !$organization->is_active) {
                return response()->json(['error' => 'Organization not found or inactive'], 404)
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Check token usage limits before processing chat
            $tokenLimitCheck = $this->checkTokenLimits($organization);
            if ($tokenLimitCheck !== true) {
                return response()->json($tokenLimitCheck, 429) // 429 Too Many Requests
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $message = $request->input('message');
            $sessionId = $request->input('session_id', uniqid());
            $userInfo = $request->input('user_info', []);
            $visitorInfo = $request->input('visitor_info', []); // For backward compatibility
            $isShopify = $request->input('is_shopify', false); // Widget flag for Shopify stores
            
            // Merge user_info and visitor_info
            $allUserInfo = array_merge($userInfo, $visitorInfo);

            // Extract location information
            $country = $request->input('country') ?? $allUserInfo['country'] ?? null;
            $region = $request->input('region') ?? $allUserInfo['region'] ?? null;
            $location = $request->input('location') ?? $allUserInfo['location'] ?? null;
            $sessionMetadata = $this->buildLeadSessionMetadata($request, $allUserInfo);
            $intentResult = null;

            try {
                $intentResult = app(IntentDetectionService::class)->detectIntent($message, $organization->id);
            } catch (\Throwable $t) {
                Log::warning('Intent detection failed', [
                    'org_id' => $organization->id,
                    'error' => $t->getMessage()
                ]);
            }

            if (!$message) {
                return response()->json(['error' => 'Message is required'], 400)
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Log and save lead capture if provided
            if (!empty($allUserInfo) && isset($allUserInfo['name'])) {
                Log::info('Lead captured via widget', [
                    'org_id' => $orgId,
                    'session_id' => $sessionId,
                    'user_info' => $allUserInfo,
                    'location' => compact('country', 'region', 'location')
                ]);
                
                $this->upsertWidgetLead(
                    $organization->id,
                    $sessionId,
                    $allUserInfo,
                    compact('country', 'region', 'location'),
                    $intentResult,
                    $message,
                    $sessionMetadata
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location'),
                    $sessionMetadata
                );
                Log::info('Lead upserted via widget', ['org_id' => $orgId, 'session_id' => $sessionId]);
            }

            // Load existing conversation to enable follow-up continuity
            $existingConversation = ChatConversation::where('conversation_id', $sessionId)
                ->where('organization_id', $organization->id)
                ->first();
            $previousContextPayloads = $existingConversation->metadata['last_context_payloads'] ?? [];

            $isAffirmativeFollowUp = $this->isAffirmativeFollowUp($message);

            // ---- SHOPIFY API INTEGRATION (SMART PATH) ----
            $shopifyContext = '';
            $shopifyData = null;
            $hasShopifyData = false;
            
            try {
                // First check if organization has active Shopify integration
                $integration = $organization->integrations()
                    ->where('provider', 'shopify')
                    ->where('active', true)
                    ->first();
                
                // Only check for Shopify patterns if integration exists
                $shouldCheckShopify = false;
                if ($integration) {
                    $shouldCheckShopify = $isShopify || $this->detectShopifyQuery($message);
                }
                
                if ($shouldCheckShopify) {
                    Log::info('Shopify query detected in widget', [
                        'org_id' => $organization->id,
                        'query' => $message,
                        'source' => $isShopify ? 'widget_flag' : 'pattern_detection'
                    ]);
                    
                    if ($integration && $integration->shop) {
                        try {
                            $response = \Illuminate\Support\Facades\Http::timeout(10)->post(config('app.url') . '/api/shopify/query', [
                                'shop_domain' => $integration->shop,
                                'query' => $message,
                            ]);
                            
                            if ($response->successful()) {
                                $data = $response->json();
                                if ($data['success'] ?? false) {
                                    $shopifyData = $data;
                                    $hasShopifyData = !empty($data['data']);
                                    
                                    // Build concise context for LLM
                                    if ($hasShopifyData && ($data['query_type'] ?? '') === 'products') {
                                        $products = $data['data'];
                                        $productCount = count($products);
                                        
                                        // Sort products by price (ascending) for accurate price queries
                                        usort($products, function($a, $b) {
                                            return floatval($a['price']) <=> floatval($b['price']);
                                        });
                                        
                                        Log::info('[SHOPIFY] Products sorted by price', [
                                            'first' => $products[0]['title'] ?? 'N/A',
                                            'first_price' => $products[0]['price'] ?? 'N/A',
                                            'last' => $products[count($products)-1]['title'] ?? 'N/A',
                                            'last_price' => $products[count($products)-1]['price'] ?? 'N/A'
                                        ]);
                                        
                                        // Extract product categories/types
                                        $categories = array_unique(array_map(function($p) {
                                            $title = $p['title'] ?? '';
                                            // Extract category from title (e.g., "Snowboard", "Ski Wax", etc.)
                                            if (stripos($title, 'snowboard') !== false) return 'Snowboards';
                                            if (stripos($title, 'ski') !== false) return 'Ski Equipment';
                                            if (stripos($title, 'gift') !== false) return 'Gift Cards';
                                            return 'Products';
                                        }, $products));
                                        
                                        // Build smart context with price range and examples
                                        $shopifyContext = "Available Products ({$productCount} total):\n";
                                        $shopifyContext .= "Categories: " . implode(', ', $categories) . "\n";
                                        
                                        // Add price range
                                        $availableProducts = array_filter($products, fn($p) => $p['available']);
                                        if (!empty($availableProducts)) {
                                            $minPrice = min(array_map(fn($p) => floatval($p['price']), $availableProducts));
                                            $maxPrice = max(array_map(fn($p) => floatval($p['price']), $availableProducts));
                                            $currency = $products[0]['currency'] ?? 'USD';
                                            $shopifyContext .= "Price Range: {$currency} {$minPrice} - {$currency} {$maxPrice}\n\n";
                                        } else {
                                            $shopifyContext .= "\n";
                                        }
                                        
                                        // Show examples: lowest price, highest price, and middle range
                                        $exampleCount = min(5, $productCount);
                                        $shopifyContext .= "Examples (sorted by price):\n";
                                        for ($i = 0; $i < $exampleCount; $i++) {
                                            $p = $products[$i];
                                            $shopifyContext .= "- {$p['title']}: {$p['currency']} {$p['price']}";
                                            if ($p['available']) {
                                                $shopifyContext .= " (In stock: {$p['inventory']})";
                                            } else {
                                                $shopifyContext .= " (Out of stock)";
                                            }
                                            $shopifyContext .= "\n";
                                        }
                                        
                                        if ($productCount > $exampleCount) {
                                            $shopifyContext .= "... and " . ($productCount - $exampleCount) . " more products\n";
                                        }
                                        
                                        $shopifyContext .= "\nWebsite: {$integration->shop}";
                                    } else {
                                        $shopifyContext = $data['formatted_text'] ?? '';
                                    }
                                    
                                    Log::info('Shopify data fetched for widget', [
                                        'query_type' => $data['query_type'] ?? 'unknown',
                                        'data_count' => count($data['data'] ?? []),
                                        'has_data' => $hasShopifyData
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error('Shopify API request failed in widget', [
                                'error' => $e->getMessage(),
                                'shop' => $integration->shop
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Shopify integration error in widget', ['error' => $e->getMessage()]);
            }

            // Search organization's Qdrant collection for context using enhanced search (or reuse last context on affirmatives)
            $collectionName = $organization->slug; // Use organization slug directly
            
            Log::info('Starting enhanced search', [
                'organization' => $organization->name,
                'collection' => $collectionName,
                'query' => $message
            ]);
            
            $searchResults = null;
            if (!$isAffirmativeFollowUp) {
                $searchResults = $this->aiAgentService->enhancedSearch(
                    $collectionName,
                    $message, // Use original message for rewriting
                    2 // Get top 2 relevant results for faster processing
                );
            }
            
            $context = '';
            $orderedResults = [];
            if ($isAffirmativeFollowUp && !empty($previousContextPayloads)) {
                // Reuse last context payloads for elaboration
                $orderedResults = array_map(function ($p) {
                    return ['payload' => $p];
                }, $previousContextPayloads);
            } elseif ($searchResults && isset($searchResults['results'])) {
                // Separate FAQ/info results from service results to prioritize FAQs for general questions
                $faqResults = [];
                $serviceResults = [];
                
                foreach ($searchResults['results'] as $result) {
                    $payload = $result['payload'] ?? [];
                    $dataType = $payload['data_type'] ?? '';
                    
                    // Treat both 'faq' and 'info' as FAQ content
                    if ($dataType === 'faq' || $dataType === 'info') {
                        $faqResults[] = $result;
                    } else {
                        $serviceResults[] = $result;
                    }
                }
                
                // For general questions (like pricing), prioritize FAQ content and exclude services
                $hasServiceKeywords = stripos($message, 'whatsapp') !== false ||
                                    stripos($message, 'integration') !== false;
                
                $isGeneralQuestion = (stripos($message, 'subscription') !== false || 
                                    stripos($message, 'pricing') !== false || 
                                    stripos($message, 'plan') !== false ||
                                    stripos($message, 'cost') !== false ||
                                    stripos($message, 'price') !== false) &&
                                   !$hasServiceKeywords;
                
                if ($isGeneralQuestion) {
                    // Only use FAQ results for general questions, exclude service-specific results
                    $orderedResults = $faqResults;
                } else {
                    // For specific questions, use both service and FAQ results
                    $orderedResults = array_merge($serviceResults, $faqResults);
                }
                
                $collectedLinks = [];
                foreach ($orderedResults as $result) {
                    $payload = $result['payload'] ?? [];
                    $dataType = $payload['data_type'] ?? '';
                    
                    // Format context differently based on data type
                    if ($dataType === 'service') {
                        // For services, include all relevant pricing and service info
                        if (isset($payload['title'])) $context .= "Service: " . $this->htmlToPlainWithLinks((string) $payload['title']) . "\n";
                        if (isset($payload['content'])) $context .= "Description: " . $this->htmlToPlainWithLinks((string) $payload['content']) . "\n";
                        if (isset($payload['price'])) $context .= "Price: " . $payload['price'] . " " . ($payload['currency'] ?? '') . "\n";
                        if (isset($payload['duration'])) $context .= "Duration: " . $payload['duration'] . "\n";
                        if (isset($payload['requirements'])) $context .= "Requirements: " . $payload['requirements'] . "\n";
                        $availability = $payload['availability'] ?? ($payload['metadata']['availability'] ?? null);
                        if (!empty($availability)) $context .= "Availability: " . $availability . "\n";
                    } else {
                        // For FAQs, keep it simple
                        $contextFields = ['title', 'content', 'category'];
                        foreach ($contextFields as $field) {
                            if (isset($payload[$field]) && is_string($payload[$field]) && !empty($payload[$field])) {
                                $context .= ucfirst($field) . ": " . $this->htmlToPlainWithLinks((string) $payload[$field]) . "\n";
                            }
                        }
                        // Collect any explicit links if present in metadata
                        if (isset($payload['links']) && is_array($payload['links'])) {
                            foreach ($payload['links'] as $lnk) {
                                if (is_string($lnk) && (stripos($lnk, 'http://') === 0 || stripos($lnk, 'https://') === 0)) {
                                    $collectedLinks[] = $lnk;
                                }
                            }
                        }
                    }
                    $context .= "\n";
                }
                // Append a Links section if any were collected
                $collectedLinks = array_values(array_unique($collectedLinks));
                if (!empty($collectedLinks)) {
                    $context .= "Links: " . implode(', ', $collectedLinks) . "\n\n";
                }
            }

            // Persist last context payloads for follow-up continuity (limit to top 5)
            try {
                if (!empty($orderedResults)) {
                    $payloads = [];
                    foreach (array_slice($orderedResults, 0, 5) as $res) {
                        $p = $res['payload'] ?? [];
                        if (!empty($p)) {
                            // Keep only relevant fields to reduce storage
                            $payloads[] = [
                                'data_type' => $p['data_type'] ?? null,
                                'title' => $p['title'] ?? null,
                                'content' => $p['content'] ?? null,
                                'price' => $p['price'] ?? null,
                                'currency' => $p['currency'] ?? null,
                                'duration' => $p['duration'] ?? null,
                                'requirements' => $p['requirements'] ?? null,
                                'availability' => $p['availability'] ?? ($p['metadata']['availability'] ?? null),
                                'category' => $p['category'] ?? null,
                                'links' => $p['links'] ?? null,
                            ];
                        }
                    }
                    if ($existingConversation) {
                        $meta = $existingConversation->metadata ?? [];
                        $meta['last_context_payloads'] = $payloads;
                        $existingConversation->metadata = $meta;
                        $existingConversation->save();
                    }
                }
            } catch (\Throwable $t) {
                Log::warning('Failed saving last context payloads', ['error' => $t->getMessage()]);
            }

            // Create concise system prompt with official org contact metadata
            $orgWebsite = $organization->website ?: config('app.url');
            $orgEmail = $organization->contact_email ?? null;
            $orgPhone = $organization->contact_phone ?? null;
            $orgDesc  = $organization->description ? trim($this->htmlToPlainWithLinks($organization->description)) : null;

            // Build context with Shopify data priority
            $finalContext = '';
            if (!empty($shopifyContext)) {
                $finalContext = "LIVE STORE DATA (use this as your primary source):\n\n" . $shopifyContext . "\n\n";
            }
            if ($context) {
                $finalContext .= "Additional information from knowledge base:\n\n" . $context;
            }

            // Assistant naming and channel-agnostic guidance
            $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
            $businessContext = $this->buildBusinessContext($organization);
            $promotionContext = $this->buildPromotionContext($organization);
            
            // Build smart system prompt
            if ($hasShopifyData) {
                // Shopify data available - guide LLM to be conversational
                $systemPrompt = "You are {$assistantName} for {$organization->name}. ";
                $systemPrompt .= "Use LIVE STORE DATA for product questions and the Knowledge Base for policies/FAQs.\n\n";
                $systemPrompt .= $finalContext . "\n";
                if ($businessContext) {
                    $systemPrompt .= $businessContext . "\n";
                }
                if ($promotionContext) {
                    $systemPrompt .= $promotionContext . "\n";
                }
                $systemPrompt .= "CRITICAL INSTRUCTIONS:\n";
                $systemPrompt .= "- Products are SORTED BY PRICE (lowest first)\n";
                $systemPrompt .= "- For 'lowest price' or 'cheapest' questions - use the FIRST available product in the list\n";
                $systemPrompt .= "- For 'highest price' or 'most expensive' - use the LAST product\n";
                $systemPrompt .= "- For 'what products do you sell?' - mention categories, give 2-3 examples with prices\n";
                $systemPrompt .= "- For 'do you have [item]?' - check the examples, say yes/no with price and stock\n";
                $systemPrompt .= "- For return policy, refund, warranty/guarantee, shipping, or store rules, use the Knowledge Base if available\n";
                $systemPrompt .= "- ALWAYS use EXACT prices from the product data above\n";
                $systemPrompt .= "- Keep responses brief (2-3 sentences, max 60 words), friendly, and helpful\n";
                $systemPrompt .= "Website: {$orgWebsite}";
            } else {
                // No Shopify data - standard prompt
                $systemPrompt = "You are {$assistantName} for {$organization->name}. ";
                if ($orgDesc) {
                    $systemPrompt .= "{$orgDesc}. ";
                }
                $systemPrompt .= "Website: {$orgWebsite}";
                if ($orgEmail) $systemPrompt .= " | Email: {$orgEmail}";
                if ($orgPhone) $systemPrompt .= " | Phone: {$orgPhone}";
                $systemPrompt .= ". ";

                if ($businessContext) {
                    $systemPrompt .= "\n" . $businessContext . "\n";
                }
                if ($promotionContext) {
                    $systemPrompt .= "\n" . $promotionContext . "\n";
                }
                
                if ($context) {
                    $systemPrompt .= "\nInfo:\n{$context}\n";
                }
                $systemPrompt .= "Be brief, friendly, and helpful. Answer in 2-3 sentences max (60 words).";
            }

            // Get AI response using llmChat for better token tracking
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message]
            ];
            
            // Use organization-specific AI provider and model
            $aiProvider = $this->aiAgentService->getAiProviderForOrganization($organization->id);
            if ($aiProvider === 'openai') {
                // Use OpenAI with organization-specific or global model
                $model = $this->aiAgentService->getOpenAiModelForOrganization($organization->id);
                $aiResponse = $this->aiAgentService->openAiChat($messages, $model, null, $organization->id);
            } else {
                // Use local LLM with organization-specific or global model
                $model = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                $aiResponse = $this->aiAgentService->llmChat($messages, $model, null, $organization->id);
            }

            $rawResponseText = null;
            if (!$aiResponse || !isset($aiResponse['message']['content'])) {
                // Fallback to old method
                $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt);
                $rawResponseText = $aiResponse['answer'] ?? null;
                $responseText = $rawResponseText ?? 'I apologize, but I\'m experiencing technical difficulties. Please try again later.';
            } else {
                $rawResponseText = $aiResponse['message']['content'];
                $responseText = $rawResponseText;
            }

            if (!$responseText) {
                throw new \Exception('Failed to get AI response');
            }

            // Normalize and sanitize AI response to plain text with clean URLs (no HTML)
            Log::info('Widget AI raw response', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'raw_ai_response_preview' => substr((string) $rawResponseText, 0, 300) . '...',
            ]);
            $responseText = $this->normalizeAiResponse($responseText);
            // Enforce official contacts only (no hallucinated emails/phones)
            $responseTextBefore = $responseText;
            $responseText = $this->enforceOfficialContacts(
                $responseText,
                $orgEmail,
                $orgPhone,
                $orgWebsite
            );
            if ($responseText !== $responseTextBefore) {
                Log::info('Widget response contacts sanitized', [
                    'org_id' => $orgId,
                    'session_id' => $sessionId,
                    'had_changes' => true
                ]);
            }

            // Detailed logging for debugging
            Log::info('Widget AI Response Debug', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'user_message' => $message,
                'context_length' => strlen($finalContext),
                'context_found' => !empty($finalContext),
                'has_shopify_data' => !empty($shopifyContext),
                'context_preview' => $finalContext ? substr($finalContext, 0, 300) . '...' : 'No context',
                'system_prompt_length' => strlen($systemPrompt),
                'ai_response_length' => strlen($responseText),
                'ai_response_preview' => substr($responseText, 0, 300) . '...',
                'full_ai_response' => $responseText
            ]);

            // Save conversation to database
            $this->saveConversationToDatabase($organization, $sessionId, $message, $responseText, $allUserInfo, compact('country', 'region', 'location'), $intentResult);

            // Log intent distribution analytics
            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                $intentResult,
                $request,
                compact('country', 'region', 'location'),
                $sessionMetadata
            );

            // Log the conversation for analytics
            Log::info('Widget chat', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => $responseText
            ]);

            return response()->json([
                'response' => $responseText,
                'session_id' => $sessionId,
                'timestamp' => now()->toISOString()
            ])->header('X-Robots-Tag', 'noindex, nofollow');

        } catch (\Exception $e) {
            Log::error('Widget chat error', [
                'org_id' => $orgId,
                'error' => $e->getMessage(),
                'message' => $request->input('message')
            ]);

            return response()->json([
                'response' => 'I apologize, but I\'m experiencing technical difficulties. Please try again later or contact support.',
                'error' => true
            ], 500)->header('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    /**
     * Stream chat - SSE endpoint for real-time streaming responses
     */
    public function streamChat(Request $request, $orgId)
    {
        $organization = is_numeric($orgId) 
            ? Organization::find($orgId) 
            : Organization::where('slug', $orgId)->first();
        
        if (!$organization || !$organization->is_active) {
            return response()->json(['error' => 'Organization not found or inactive'], 404);
        }

        // Check token limits
        $tokenLimitCheck = $this->checkTokenLimits($organization);
        if ($tokenLimitCheck !== true) {
            return response()->json($tokenLimitCheck, 429);
        }

        $message = $request->input('message');
        $sessionId = $request->input('session_id', uniqid());
        $userInfo = $request->input('user_info', []);
        $visitorInfo = $request->input('visitor_info', []);
        $allUserInfo = array_merge($userInfo, $visitorInfo);
        $country = $request->input('country') ?? ($allUserInfo['country'] ?? null);
        $region = $request->input('region') ?? ($allUserInfo['region'] ?? null);
        $location = $request->input('location') ?? ($allUserInfo['location'] ?? null);
        $sessionMetadata = $this->buildLeadSessionMetadata($request, $allUserInfo);
        
        if (!$message) {
            return response()->json(['error' => 'Message is required'], 400);
        }

        return response()->stream(function () use ($organization, $message, $sessionId, $request, $allUserInfo, $country, $region, $location, $sessionMetadata) {
            try {
                // Build context (simplified version - you can reuse logic from chat())
                $aiService = app(AiAgentService::class);
                $actionService = app(\App\Services\ActionService::class);
                
                // Check if any action should be executed
                $actionResult = $actionService->processQuery($message, $organization->id);
                $intentResult = $actionResult['intent'] ?? null;

                // Update lead with intent/priority if lead info is provided
                $this->upsertWidgetLead(
                    $organization->id,
                    $sessionId,
                    $allUserInfo,
                    compact('country', 'region', 'location'),
                    $intentResult,
                    $message,
                    $sessionMetadata
                );
                
                $context = '';
                $liveData = null;
                
                // If action was executed, include the live data
                if ($actionResult['type'] === 'action_executed' && isset($actionResult['result']['success']) && $actionResult['result']['success']) {
                    $liveData = $actionResult['result']['data'] ?? null;
                    $actionName = $actionResult['action']['action_name'] ?? 'database query';
                    
                    if ($liveData) {
                        $context .= "\n\n[LIVE DATA from {$actionName}]:\n";
                        $context .= json_encode($liveData, JSON_PRETTY_PRINT) . "\n";
                        $context .= "[END LIVE DATA]\n\n";
                        $context .= "IMPORTANT: Use ONLY the LIVE DATA above to answer the question. Format it in a user-friendly way.\n\n";
                        $context .= $this->buildLiveDataValidationRules($liveData);
                    }
                }
                
                // Search for relevant context only if no action was executed or as supplementary info
                if (!$liveData) {
                    $searchResults = $aiService->enhancedSearch($organization->slug, $message, 5);
                    
                    if ($searchResults && isset($searchResults['results'])) {
                        $context .= "\n\nAdditional information from knowledge base:\n\n";
                        foreach ($searchResults['results'] as $result) {
                            $payload = $result['payload'] ?? [];
                            if (isset($payload['title'])) $context .= "Title: " . $payload['title'] . "\n";
                            if (isset($payload['content'])) $context .= "Content: " . $payload['content'] . "\n";
                            $context .= "\n";
                        }
                    }
                }

                // Build messages
                $systemPrompt = "You are AI Assistant for {$organization->name}.";
                $businessContext = $this->buildBusinessContext($organization);
                $promotionContext = $this->buildPromotionContext($organization);
                if ($businessContext) {
                    $systemPrompt .= "\n" . $businessContext;
                }
                if ($promotionContext) {
                    $systemPrompt .= "\n" . $promotionContext;
                }
                if ($context) {
                    $systemPrompt .= $context;
                }

                // Stream from FastAPI
                $fastApiUrl = config('services.ai_agent.url');
                $model = \App\Models\AdminSetting::get('llama_default_model', 'llama3.2:3b');
                
                $ch = curl_init("{$fastApiUrl}/llm/chat/stream");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_HEADER => false,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $message]
                        ],
                        'model' => $model,
                        'backend_type' => 'ollama'
                    ]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_WRITEFUNCTION => function ($curl, $data) {
                        echo $data;
                        ob_flush();
                        flush();
                        return strlen($data);
                    }
                ]);
                
                curl_exec($ch);
                curl_close($ch);
                
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage(), 'done' => true]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Robots-Tag' => 'noindex, nofollow'
        ]);
    }

    private function upsertWidgetLead(int $organizationId, string $sessionId, array $userInfo, array $locationInfo, ?array $intentResult, ?string $message, ?array $sessionMetadata = null): void
    {
        if (empty($userInfo) || empty($userInfo['name']) || empty($userInfo['email'])) {
            return;
        }

        $intent = $intentResult['intent'] ?? null;
        $confidence = $intentResult['confidence'] ?? null;
        $priority = $this->mapLeadPriority($intent);
        $status = $this->mapLeadStatus($intent);

        $payload = [
            'name' => $userInfo['name'] ?? null,
            'email' => $userInfo['email'] ?? null,
            'phone' => $userInfo['phone'] ?? null,
            'source' => 'widget',
            'organization_id' => $organizationId,
            'session_id' => $sessionId,
            'location_data' => json_encode($locationInfo),
            'intent' => $intent,
            'intent_confidence' => $confidence,
            'priority' => $priority,
            'status' => $status,
            'last_message' => $message,
            'last_intent_at' => now(),
        ];

        if (!empty($sessionMetadata)) {
            $payload['session_metadata'] = json_encode($sessionMetadata);
        }

        try {
            $existingLead = Lead::where('organization_id', $organizationId)
                ->where('session_id', $sessionId)
                ->first();

            $lead = Lead::updateOrCreate(
                ['organization_id' => $organizationId, 'session_id' => $sessionId],
                $payload
            );

            $this->notifyLeadIfNeeded($lead, $existingLead, $intentResult, $message);
        } catch (\Exception $e) {
            Log::error('Failed to upsert widget lead', [
                'error' => $e->getMessage(),
                'org_id' => $organizationId,
                'session_id' => $sessionId
            ]);
        }
    }

    private function mapLeadPriority(?string $intent): string
    {
        $intent = strtolower(trim($intent ?? ''));

        if (in_array($intent, ['booking', 'appointment', 'purchase', 'pricing', 'quote', 'demo', 'contact'], true)) {
            return 'high';
        }

        if (in_array($intent, ['realtime_data', 'lookup'], true)) {
            return 'medium';
        }

        return 'normal';
    }

    private function mapLeadStatus(?string $intent): string
    {
        $intent = strtolower(trim($intent ?? ''));

        if (in_array($intent, ['booking', 'appointment', 'purchase', 'pricing', 'quote', 'demo', 'contact'], true)) {
            return 'qualified';
        }

        return 'new';
    }

    private function buildLeadSessionMetadata(Request $request, array $allUserInfo): array
    {
        $metadata = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->input('referrer') ?: $request->headers->get('referer'),
            'page_url' => $request->input('page_url'),
            'page_title' => $request->input('page_title'),
            'timezone' => $request->input('timezone') ?? ($allUserInfo['timezone'] ?? null),
            'language' => $request->input('language') ?? ($allUserInfo['language'] ?? null),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_term' => $request->input('utm_term'),
            'utm_content' => $request->input('utm_content'),
        ];

        return array_filter($metadata, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }

    private function logIntentAnalytics(int $organizationId, string $sessionId, ?array $intentResult, Request $request, array $locationInfo = [], ?array $sessionMetadata = null): void
    {
        if (empty($intentResult) || empty($intentResult['intent'])) {
            return;
        }

        try {
            $pageUrl = $sessionMetadata['page_url'] ?? $request->input('page_url');
            $pageTitle = $sessionMetadata['page_title'] ?? $request->input('page_title');
            $referrer = $sessionMetadata['referrer'] ?? $request->input('referrer') ?? $request->headers->get('referer');

            Analytics::create([
                'organization_id' => $organizationId,
                'visitor_id' => $sessionId,
                'session_id' => $sessionId,
                'event_type' => 'intent_detected',
                'page_url' => $pageUrl ?: config('app.url'),
                'page_title' => $pageTitle ?: '',
                'referrer' => $referrer ?: '',
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'country' => $locationInfo['country'] ?? null,
                'region' => $locationInfo['region'] ?? null,
                'city' => $locationInfo['location'] ?? null,
                'event_data' => [
                    'intent' => $intentResult['intent'] ?? null,
                    'confidence' => $intentResult['confidence'] ?? null,
                    'method' => $intentResult['method'] ?? null,
                ],
            ]);
        } catch (\Throwable $t) {
            Log::warning('Intent analytics log failed', [
                'org_id' => $organizationId,
                'error' => $t->getMessage()
            ]);
        }
    }

    private function buildBusinessContext(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $hours = trim((string) ($settings['business_hours'] ?? ''));
        $holidayEntries = $settings['holiday_dates'] ?? [];

        if (is_string($holidayEntries)) {
            $holidayEntries = preg_split('/[\n,]+/', $holidayEntries);
        }

        $holidayEntries = array_values(array_filter(array_map('trim', (array) $holidayEntries)));
        $holidays = $this->normalizeHolidayEntries($holidayEntries);

        if ($hours === '' && empty($holidays)) {
            return '';
        }

        $timezone = $organization->timezone ?: config('app.timezone', 'UTC');
        $now = now()->timezone($timezone);
        $today = $now->toDateString();

        $todayHoliday = null;
        foreach ($holidays as $holiday) {
            if ($holiday['date'] === $today) {
                $todayHoliday = $holiday;
                break;
            }
        }

        $lines = [];
        $lines[] = "Business hours & availability:";
        $lines[] = "- Timezone: {$timezone}";
        $lines[] = "- Current local time: " . $now->format('Y-m-d H:i');
        if ($hours !== '') {
            $lines[] = "- Business hours: {$hours}";
        }
        if (!empty($holidays)) {
            $holidayText = implode(', ', array_map(function ($holiday) {
                return $holiday['label'] ? ($holiday['date'] . ' (' . $holiday['label'] . ')') : $holiday['date'];
            }, $holidays));
            $lines[] = "- Holidays: {$holidayText}";
        }
        if ($todayHoliday) {
            $label = $todayHoliday['label'] ? " ({$todayHoliday['label']})" : '';
            $lines[] = "- Note: Today is listed as a holiday{$label}.";
        }

        return implode("\n", $lines);
    }

    private function normalizeHolidayEntries(array $holidayEntries): array
    {
        $holidays = [];

        foreach ($holidayEntries as $entry) {
            if ($entry === '') {
                continue;
            }
            $date = $entry;
            $label = null;

            if (str_contains($entry, '|')) {
                [$date, $label] = array_map('trim', explode('|', $entry, 2));
            } elseif (str_contains($entry, ':')) {
                [$date, $label] = array_map('trim', explode(':', $entry, 2));
            }

            $date = trim($date);
            if ($date === '') {
                continue;
            }

            $holidays[] = [
                'date' => $date,
                'label' => $label ?: null,
            ];
        }

        return $holidays;
    }

    private function buildPromotionContext(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $raw = trim((string) ($settings['seasonal_promotions'] ?? ''));

        if ($raw === '') {
            return '';
        }

        $timezone = $organization->timezone ?: config('app.timezone', 'UTC');
        $now = now()->timezone($timezone);
        $promotions = $this->parsePromotionLines($raw, $timezone);

        if (empty($promotions)) {
            return '';
        }

        $active = [];
        $upcoming = [];

        foreach ($promotions as $promo) {
            $start = $promo['start'];
            $end = $promo['end'];

            if ($start && $end && $now->between($start, $end)) {
                $active[] = $promo;
            } elseif ($start && $start->greaterThan($now)) {
                $upcoming[] = $promo;
            }
        }

        $lines = ["Promotions & offers:"];
        if (!empty($active)) {
            foreach ($active as $promo) {
                $lines[] = "- Active: {$promo['title']} ({$promo['start']->toDateString()} to {$promo['end']->toDateString()}) - {$promo['details']}";
            }
        }

        if (empty($active) && !empty($upcoming)) {
            foreach (array_slice($upcoming, 0, 3) as $promo) {
                $lines[] = "- Upcoming: {$promo['title']} ({$promo['start']->toDateString()} to {$promo['end']->toDateString()}) - {$promo['details']}";
            }
        }

        if (count($lines) === 1) {
            return '';
        }

        return implode("\n", $lines);
    }

    private function parsePromotionLines(string $raw, string $timezone): array
    {
        $lines = preg_split('/\r?\n/', $raw);
        $promotions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $start = null;
            $end = null;
            $title = '';
            $details = '';

            if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})\s*\|\s*([^|]+)\s*\|\s*(.+)$/i', $line, $m)) {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[1]), $timezone)->startOfDay();
                $end = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[2]), $timezone)->endOfDay();
                $title = trim($m[3]);
                $details = trim($m[4]);
            } elseif (preg_match('/^(\d{4}-\d{2}-\d{2})\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2})\s*\|\s*(.+)$/i', $line, $m)) {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[1]), $timezone)->startOfDay();
                $end = \Carbon\Carbon::createFromFormat('Y-m-d', trim($m[2]), $timezone)->endOfDay();
                $title = trim($m[3]);
                $details = '';
            } else {
                continue;
            }

            if ($title === '' && $details === '') {
                continue;
            }

            $promotions[] = [
                'start' => $start,
                'end' => $end,
                'title' => $title !== '' ? $title : 'Promotion',
                'details' => $details !== '' ? $details : 'Details available on request.',
            ];
        }

        return $promotions;
    }

    private function notifyLeadIfNeeded(Lead $lead, ?Lead $existingLead, ?array $intentResult, ?string $message): void
    {
        $organization = Organization::find($lead->organization_id);
        if (!$organization) {
            return;
        }

        $settings = $organization->settings ?? [];
        if (!(bool) ($settings['lead_notify_enabled'] ?? false)) {
            return;
        }

        $qualifiedOnly = (bool) ($settings['lead_notify_qualified_only'] ?? true);
        $newStatus = $lead->status ?? 'new';
        $previousStatus = $existingLead?->status ?? null;

        $shouldNotify = !$existingLead || ($previousStatus !== 'qualified' && $newStatus === 'qualified');
        if ($qualifiedOnly) {
            $shouldNotify = $shouldNotify && $newStatus === 'qualified';
        }

        if (!$shouldNotify) {
            return;
        }

        $emails = $settings['lead_notify_emails'] ?? [];
        if (is_string($emails)) {
            $emails = preg_split('/[\s,]+/', $emails);
        }
        $emails = array_values(array_filter(array_map('trim', (array) $emails)));

        if (!empty($emails)) {
            try {
                Mail::to($emails)->send(new LeadCapturedNotification($lead, $organization, $intentResult, $message));
            } catch (\Throwable $t) {
                Log::warning('Lead notification email failed', [
                    'error' => $t->getMessage(),
                    'org_id' => $lead->organization_id,
                ]);
            }
        }

        $webhookUrl = trim((string) ($settings['lead_notify_webhook_url'] ?? ''));
        if ($webhookUrl !== '') {
            try {
                Http::timeout(8)->post($webhookUrl, [
                    'event' => 'lead_captured',
                    'lead' => $lead->toArray(),
                    'organization' => [
                        'id' => $organization->id,
                        'name' => $organization->name,
                        'slug' => $organization->slug,
                    ],
                    'intent' => $intentResult,
                    'message' => $message,
                ]);
            } catch (\Throwable $t) {
                Log::warning('Lead webhook failed', [
                    'error' => $t->getMessage(),
                    'org_id' => $lead->organization_id,
                ]);
            }
        }
    }

    /**
     * Convert HTML to plain text while preserving links as "text (url)" or just the URL.
     */
    private function htmlToPlainWithLinks(string $html): string
    {
        if ($html === '') return '';

        // Replace anchors with readable text; if label is a URL, prefer the href URL to avoid duplication
        $html = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ($m) {
            $url = trim($m[1]);
            $text = trim(strip_tags($m[2]));
            $isTextUrl = (bool)preg_match('/^https?:\/\//i', $text);
            if ($text === '' || strcasecmp($text, $url) === 0 || $isTextUrl) {
                return $url;
            }
            return $text . ' (' . $url . ')';
        }, $html) ?? $html;

        // Convert common block separators to newlines
        $html = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\s*li\s*>/i', "* ", $html) ?? $html;

        // Strip remaining tags
        $text = strip_tags($html);
        // Decode entities and normalize whitespace
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n|\r|\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    private function buildLiveDataValidationRules($liveData): string
    {
        $prices = $this->extractAllowedPrices($liveData);

        $rules = "VALIDATION RULES:\n";
        $rules .= "- Do NOT guess, estimate, or infer numbers.\n";
        $rules .= "- If a value is not present in LIVE DATA, say it is not available.\n";

        if (!empty($prices)) {
            $rules .= "- Allowed prices (use exact values only): " . implode(', ', $prices) . "\n";
        } else {
            $rules .= "- If user asks about price and LIVE DATA has no price, say price is not available.\n";
        }

        return "\n" . $rules . "\n";
    }

    private function extractAllowedPrices($liveData): array
    {
        $prices = [];

        $walk = function ($value) use (&$walk, &$prices) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if (is_string($key) && preg_match('/price|cost|fee|amount|total/i', $key)) {
                        if (is_numeric($item)) {
                            $prices[] = (string) $item;
                        } elseif (is_string($item)) {
                            if (preg_match('/\d+(?:\.\d+)?/', $item)) {
                                $prices[] = trim($item);
                            }
                        }
                    }
                    $walk($item);
                }
            }
        };

        $walk($liveData);

        $prices = array_values(array_unique(array_filter($prices)));
        sort($prices);
        return $prices;
    }

    /**
     * Ensure model output is plain text without HTML tags; keep URLs intact for client linkify.
     */
    private function normalizeAiResponse(string $text): string
    {
        if ($text === '') return '';
        // First convert any anchor tags to "text (url)" or URL
        $text = $this->htmlToPlainWithLinks($text);
        // Convert Markdown links [label](url or noisy content) to "label (https://...)" or just URL
        $text = preg_replace_callback('/\[(.*?)\]\(([^)]+)\)/s', function ($m) {
            $label = trim($m[1]);
            $inner = trim($m[2]);
            $url = '';
            if (preg_match('/https?:\/\/[^\s)]+/i', $inner, $um)) {
                $url = $um[0];
            } elseif (preg_match('/(?:[a-z0-9-]+\.)+[a-z]{2,}(?:\/[^\s)]*)?/i', $inner, $dm)) {
                $url = 'https://' . $dm[0];
            }
            if ($url !== '') {
                // If label is empty or equals URL, or label itself looks like a URL (even if different), prefer the URL only
                if ($label === '' || strcasecmp($label, $url) === 0 || preg_match('/^https?:\/\//i', $label)) return $url;
                return $label . ' (' . $url . ')';
            }
            return $label !== '' ? $label : $inner; // fallback to readable text
        }, $text);
        // As an extra guard, remove any lingering tags
        $text = strip_tags($text);
        // Collapse excessive whitespace
        $text = preg_replace('/\s+/', ' ', str_replace(["\r", "\t"], [' ', ' '], $text));
        // Re-insert line breaks around bullets or list markers if any were present
        $text = preg_replace('/\*\s+/', "\n* ", $text);
        // Restore paragraph-like breaks after periods followed by asterisk bullets
        $text = preg_replace('/\.\s+\*/', ".\n*", $text);
        return trim($text);
    }

    /**
     * Detects short affirmative follow-ups like "yes", "yes tell me more", etc.
     */
    private function isAffirmativeFollowUp(string $text): bool
    {
        $t = trim(mb_strtolower($text));
        if ($t === '') return false;
        // Simple affirmatives
        $affirm = ['yes', 'yeah', 'yup', 'ya', 'sure', 'ok', 'okay', 'please', 'go ahead'];
        foreach ($affirm as $a) {
            if ($t === $a) return true;
        }
        // Phrases asking to elaborate
        $patterns = [
            '/^yes\b.*more/',
            '/\btell me more\b/',
            '/\bmore details\b/',
            '/\bhow it works\b/',
            '/\bexplain more\b/'
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $t)) return true;
        }
        // Also treat short confirmations under 16 chars as affirmative if contain yes/ok/sure
        if (mb_strlen($t) < 16 && preg_match('/\b(yes|ok|okay|sure|please)\b/', $t)) return true;
        return false;
    }

    /**
     * Post-process AI output to prevent fabricated contact details.
     * - If official email/phone are provided, replace any found with the official ones.
     * - If not provided, remove any email/phone-like strings and point to website.
     */
    private function enforceOfficialContacts(string $text, ?string $officialEmail, ?string $officialPhone, string $officialWebsite): string
    {
        $out = $text;

        try {
            // Email normalization (no lookbehind)
            $emailPattern = '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i';
            if (!empty($officialEmail)) {
                $out = preg_replace($emailPattern, $officialEmail, $out) ?? $out;
                $out = preg_replace('/(' . preg_quote($officialEmail, '/') . ')(\s*,\s*\1)+/i', '$1', $out) ?? $out;
            } else {
                $out = preg_replace($emailPattern, '', $out) ?? $out;
            }

            // Phone normalization: replace any plausible phone number with official when provided,
            // otherwise remove them. Avoid lookbehind: detect context by capturing groups.
            // Conservative phone pattern: starts with optional +, then digits with optional separators, min total digits >= 7
            $phonePattern = '/\+?[\d][\d\s\-\(\)]{6,}/';

            if (!empty($officialPhone)) {
                $out = preg_replace_callback($phonePattern, function($m) use ($officialPhone) {
                    // Keep if it's clearly not a phone (e.g., numbers with letters), else replace
                    $candidate = trim($m[0]);
                    // Count digits to avoid replacing long IDs with too few digits
                    $digits = preg_replace('/\D+/', '', $candidate);
                    return strlen($digits) >= 7 ? $officialPhone : $candidate;
                }, $out) ?? $out;
            } else {
                $out = preg_replace_callback($phonePattern, function($m) {
                    $candidate = trim($m[0]);
                    $digits = preg_replace('/\D+/', '', $candidate);
                    return strlen($digits) >= 7 ? '' : $candidate;
                }, $out) ?? $out;
            }

            // Clean up extra spaces/commas left by removals
            $out = preg_replace('/\s{2,}/', ' ', $out) ?? $out;
            $out = preg_replace('/\s+([,;.])/', '$1', $out) ?? $out;
            $out = trim($out);

            // If both email and phone are unavailable, encourage website contact
            if (empty($officialEmail) && empty($officialPhone)) {
                if ($out !== '' && !str_ends_with($out, '.')) {
                    $out .= '.';
                }
                if (stripos($out, $officialWebsite) === false) {
                    $out .= ' You can contact us via our official website: ' . $officialWebsite;
                }
            }
        } catch (\Throwable $t) {
            \Log::warning('Contact sanitization failed; returning original text', ['error' => $t->getMessage()]);
            return trim($text);
        }

        return trim($out);
    }

    /**
     * Get widget configuration
     */
    public function getConfig($orgId)
    {
        // Support both numeric ID and slug
        if (is_numeric($orgId)) {
            $organization = Organization::find($orgId);
        } else {
            $organization = Organization::where('slug', $orgId)->first();
        }
        
        if (!$organization || !$organization->is_active) {
            return response()->json(['error' => 'Organization not found or inactive'], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response()->json([
            'name' => $organization->name,
            'welcomeMessage' => $organization->settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'theme' => $organization->settings['widget_theme'] ?? 'default',
            'position' => $organization->settings['widget_position'] ?? 'bottom-right',
            'primaryColor' => $organization->settings['primary_color'] ?? '#007bff'
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Save conversation to database
     */
    private function saveConversationToDatabase($organization, $sessionId, $userMessage, $aiResponse, $userInfo = [], $locationInfo = [], $intentResult = null)
    {
        try {
            // Find or create conversation
            $conversation = ChatConversation::firstOrCreate(
                [
                    'conversation_id' => $sessionId,
                    'organization_id' => $organization->id
                ],
                [
                    'visitor_id' => $sessionId,
                    'visitor_name' => $userInfo['name'] ?? null,
                    'visitor_email' => $userInfo['email'] ?? null,
                    'visitor_phone' => $userInfo['phone'] ?? null,
                    'visitor_country' => $locationInfo['country'] ?? null,
                    'visitor_region' => $locationInfo['region'] ?? null,
                    'visitor_location' => $locationInfo['location'] ?? null,
                    'status' => 'active',
                    'agent_status' => 'ai',
                    'last_activity_at' => now()
                ]
            );

            // Save user message
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'user',
                'sender_name' => $userInfo['name'] ?? 'Visitor',
                'message' => $userMessage,
                'sent_at' => now(),
                'metadata' => [
                    'session_id' => $sessionId,
                    'user_info' => $userInfo,
                    'location_info' => $locationInfo,
                    'intent' => $intentResult['intent'] ?? null,
                    'intent_confidence' => $intentResult['confidence'] ?? null,
                    'intent_method' => $intentResult['method'] ?? null
                ]
            ]);

            // Save AI response
            $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'ai',
                'sender_name' => $assistantName,
                'message' => $aiResponse,
                'sent_at' => now(),
                'metadata' => [
                    'session_id' => $sessionId,
                    'organization_name' => $organization->name,
                    'intent' => $intentResult['intent'] ?? null,
                    'intent_confidence' => $intentResult['confidence'] ?? null,
                    'intent_method' => $intentResult['method'] ?? null
                ]
            ]);

            // Optional email notification for each interaction (user + AI)
            $this->sendChatInteractionNotification($organization, $conversation, $userMessage, $aiResponse, $userInfo, $locationInfo);

            // Update conversation activity
            $conversation->update([
                'last_activity_at' => now()
            ]);

            // Generate conversation title from first message if not set
            if (!$conversation->title) {
                $conversation->generateTitle();
            }

            Log::info('Conversation saved to database', [
                'conversation_id' => $conversation->id,
                'session_id' => $sessionId,
                'org_id' => $organization->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to save conversation to database', [
                'session_id' => $sessionId,
                'org_id' => $organization->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendChatInteractionNotification($organization, $conversation, $userMessage, $aiResponse, $userInfo = [], $locationInfo = []): void
    {
        try {
            $settings = $organization->settings ?? [];
            $enabled = (bool) ($settings['notify_chat_email_enabled'] ?? false);
            if (!$enabled) {
                return;
            }

            $emails = $settings['notify_chat_emails'] ?? [];
            if (is_string($emails)) {
                $emails = array_filter(array_map('trim', explode(',', $emails)));
            }

            if (!is_array($emails) || empty($emails)) {
                return;
            }

            $payload = [
                'organization' => $organization,
                'conversation' => $conversation,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
                'user_info' => $userInfo,
                'location_info' => $locationInfo
            ];

            Mail::to($emails)->send(new ChatInteractionNotification($payload));
        } catch (\Throwable $e) {
            Log::warning('Chat interaction email notification failed', [
                'org_id' => $organization->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if organization has exceeded token limits
     */
    private function checkTokenLimits($organization)
    {
        // Bypass limits for the platform host only (ai-chat.support)
        $bypassHosts = ['ai-chat.support', 'www.ai-chat.support'];
        $bypassOrgSlugs = ['platform', 'ai-chat-support'];
        $requestHost = request()->getHost();

        if (in_array($requestHost, $bypassHosts, true) || in_array($organization->slug, $bypassOrgSlugs, true)) {
            \Log::debug('Token limits bypassed for allowlisted host/org', [
                'host' => $requestHost,
                'org_id' => $organization->id,
                'org_slug' => $organization->slug
            ]);
            return true;
        }

        // Allow disabling token enforcement via config/services.ai_agent.enforce_limits or env AI_ENFORCE_LIMITS=false
        $enforce = (bool) config('services.ai_agent.enforce_limits', env('AI_ENFORCE_LIMITS', true));
        if (!$enforce) {
            \Log::debug('Token limits not enforced (config disabled)', [
                'org_id' => $organization->id,
                'org_name' => $organization->name
            ]);
            return true;
        }

        // Get the organization's owner (first user)
        $user = $organization->users()->first();
        if (!$user) {
            // No user associated, allow chat but log warning
            Log::warning('No user associated with organization for token limit check', [
                'org_id' => $organization->id,
                'org_name' => $organization->name
            ]);
            return true;
        }

        $subscription = $user->activeSubscription;

        // Estimate tokens needed for this request (rough estimate: 500-1000 tokens per chat)
        $estimatedTokensNeeded = 800;

        // Always consider user credits as a fallback funding source
        $creditBalance = 0;
        try {
            $creditBalance = optional(\App\Models\UserCredit::getOrCreateForUser($user->id))->balance ?? 0;
        } catch (\Throwable $e) {
            Log::error('Failed to load user credit balance', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        if (!$subscription || !$subscription->subscriptionPlan) {
            // No active subscription: allow if credits are sufficient; otherwise soft-allow but log
            if ($creditBalance >= $estimatedTokensNeeded) {
                Log::info('Allowing chat using credits (no subscription)', [
                    'org_id' => $organization->id,
                    'user_id' => $user->id,
                    'credit_balance' => $creditBalance
                ]);
                return true;
            }
            Log::info('No subscription and insufficient credits; allowing under soft policy', [
                'org_id' => $organization->id,
                'user_id' => $user->id,
                'credit_balance' => $creditBalance
            ]);
            return true;
        }

        $tokenLimit = $subscription->subscriptionPlan->token_cap_monthly;
        $tokensUsed = $subscription->tokens_used_this_period;
        $remainingTokens = $subscription->remaining_tokens;
        $usagePercentage = $subscription->usage_percentage;

        // If subscription remaining tokens are insufficient, allow if credits can cover
        if ($remainingTokens <= 0 || $remainingTokens < $estimatedTokensNeeded) {
            if ($creditBalance >= $estimatedTokensNeeded) {
                Log::info('Subscription low/exhausted, allowing chat using credits', [
                    'user_id' => $user->id,
                    'org_id' => $organization->id,
                    'remaining_sub_tokens' => $remainingTokens,
                    'credit_balance' => $creditBalance
                ]);
                return true;
            }

            // Hard deny if neither subscription tokens nor credits can cover
            if ($remainingTokens <= 0) {
                return [
                    'error' => 'Token limit exceeded',
                    'message' => 'You have used all ' . number_format($tokenLimit) . ' tokens in your ' . $subscription->subscriptionPlan->name . ' plan this month.',
                    'usage_info' => [
                        'used' => $tokensUsed,
                        'limit' => $tokenLimit,
                        'percentage' => round($usagePercentage, 1),
                        'credits' => $creditBalance
                    ],
                    'action_required' => 'upgrade_or_add_credits',
                    'upgrade_url' => config('app.url') . '/customer/subscription',
                    'credits_url' => config('app.url') . '/customer/credits',
                    'renewal_date' => $subscription->current_period_end ? $subscription->current_period_end->format('M j, Y') : null
                ];
            }

            return [
                'error' => 'Insufficient tokens',
                'message' => 'You have only ' . number_format($remainingTokens) . ' tokens remaining in your subscription, and not enough credits to cover this request.',
                'usage_info' => [
                    'used' => $tokensUsed,
                    'limit' => $tokenLimit,
                    'remaining' => $remainingTokens,
                    'percentage' => round($usagePercentage, 1),
                    'credits' => $creditBalance
                ],
                'action_required' => 'upgrade_or_add_credits',
                'upgrade_url' => config('app.url') . '/customer/subscription',
                'credits_url' => config('app.url') . '/customer/credits'
            ];
        }

        if ($usagePercentage >= 90) {
            // Warning: approaching limit, but still allow
            Log::info('User approaching token limit', [
                'user_id' => $user->id,
                'org_id' => $organization->id,
                'usage_percentage' => $usagePercentage,
                'remaining_tokens' => $remainingTokens,
                'credit_balance' => $creditBalance
            ]);
        }

        return true; // All checks passed
    }

    /**
     * Detect if the message is asking about Shopify-related data
     */
    private function detectShopifyQuery(string $message): bool
    {
        $lowerMessage = strtolower($message);
        
        // Generic Shopify keywords
        $shopifyKeywords = [
            'product', 'products', 'item', 'items', 'inventory', 'stock',
            'order', 'orders', 'tracking', 'shipment', 'shipping', 'delivery',
            'price', 'cost', 'how much', 'buy', 'purchase', 'sell',
            'available', 'in stock', 'out of stock', 'featured'
        ];
        
        foreach ($shopifyKeywords as $keyword) {
            if (stripos($lowerMessage, $keyword) !== false) {
                return true;
            }
        }
        
        // Check for "do you have [something]" pattern
        if (preg_match('/\b(do you have|got|carry|got any)\b/i', $lowerMessage)) {
            return true;
        }
        
        // Check for "looking for [something]" pattern
        if (preg_match('/\b(looking for|need|want|interested in)\b/i', $lowerMessage)) {
            return true;
        }
        
        return false;
    }
}
