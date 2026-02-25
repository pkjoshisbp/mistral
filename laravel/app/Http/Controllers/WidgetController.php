<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Integration;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Analytics;
use App\Models\PricingPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\ChatInteractionNotification;
use App\Mail\LeadCapturedNotification;
use App\Models\Lead;
use App\Models\OrganizationFaq;
use App\Services\IntentDetectionService;
use App\Services\AiAgentService;
use App\Services\FaqFollowUpService;
use App\Services\FollowUpStateService;
use App\Services\LocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Mail\ChatEscalationNotification;
use App\Mail\ChatInteractionDigestNotification;
use Illuminate\Support\Str;
use App\Models\OrganizationData;

class WidgetController
{
    private $aiAgentService;
    private $faqFollowUpService;
    private $followUpStateService;

    public function __construct(AiAgentService $aiAgentService, FaqFollowUpService $faqFollowUpService, FollowUpStateService $followUpStateService)
    {
        $this->aiAgentService = $aiAgentService;
        $this->faqFollowUpService = $faqFollowUpService;
        $this->followUpStateService = $followUpStateService;
    }

    /**
     * Resolve Shopify shop domain to organization widget config.
     */
    public function resolveShopifyOrganization(Request $request)
    {
        $shop = strtolower(trim((string) $request->query('shop')));

        if ($shop === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing shop domain',
            ], 422);
        }

        if (!str_ends_with($shop, '.myshopify.com')) {
            $shop .= '.myshopify.com';
        }

        $integration = Integration::with('organization')
            ->where('provider', 'shopify')
            ->where('shop', $shop)
            ->where('active', true)
            ->first();

        if (!$integration || !$integration->organization || !$integration->organization->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'No active organization found for this Shopify shop',
            ], 404);
        }

        $organization = $integration->organization;

        return response()->json([
            'success' => true,
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug,
            'script_url' => url('/widget/' . $organization->slug . '/script.js'),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
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
        $starterPrompts = $this->getWidgetStarterPrompts($organization);
        $enableWebsocket = (bool) env('WIDGET_WEBSOCKET_ENABLED', false);
        $configuredWsUrl = trim((string) env('WIDGET_WS_URL', ''));
        $websocketHost = (string) env('WIDGET_WEBSOCKET_PUBLIC_HOST', parse_url(config('app.url'), PHP_URL_HOST));
        $websocketPort = (int) env('WIDGET_WEBSOCKET_PORT', 8090);
        $websocketScheme = (bool) env('WIDGET_WEBSOCKET_TLS', true) ? 'wss' : 'ws';
        $derivedWsUrl = $websocketHost ? sprintf('%s://%s:%d', $websocketScheme, $websocketHost, $websocketPort) : null;
        $wsUrl = $configuredWsUrl !== '' ? $configuredWsUrl : $derivedWsUrl;

        $widgetConfig = [
            'orgId' => $orgId,
            'orgName' => $organization->name,
            'orgWebsite' => $organization->website ?: config('app.url'),
            'contactEmail' => $organization->contact_email ?? null,
            'contactPhone' => $organization->contact_phone ?? null,
            'apiUrl' => config('app.url'),
            'headerLogoUrl' => $settings['widget_header_logo_url'] ?? null,
            'showHeaderLogo' => (bool)($settings['show_header_logo'] ?? false),
            'brandingLogoUrl' => $settings['branding_logo_url'] ?? (rtrim(config('app.url'), '/') . '/images/ai-chat-logo.svg'),
            'enableWebsocket' => $enableWebsocket,
            'wsUrl' => $wsUrl,
            'scriptVersion' => $scriptVersion,
            'theme' => $settings['widget_theme'] ?? 'default',
            'position' => $settings['widget_position'] ?? 'bottom-right',
            'offsetX' => (int)($settings['widget_offset_x'] ?? 20),
            'offsetY' => (int)($settings['widget_offset_y'] ?? 20),
            'primaryColor' => $settings['primary_color'] ?? '#007bff',
            'welcomeMessage' => $settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'chatHistoryTtlHours' => (int)($settings['chat_history_ttl_hours'] ?? 24),
            'requireContactForGuests' => (bool)($settings['require_contact_for_guests'] ?? false),
            'contactFields' => is_array($settings['widget_contact_fields'] ?? null) ? $settings['widget_contact_fields'] : [],
            'starterPrompts' => $starterPrompts,
            // Branding/backlink controls (defaults: enabled + dofollow)
            'brandingEnabled' => array_key_exists('branding_enabled', $settings) ? (bool)$settings['branding_enabled'] : true,
            'brandingFollow' => array_key_exists('branding_follow', $settings) ? (bool)$settings['branding_follow'] : true,
            'brandingBadge' => (bool)($settings['branding_badge'] ?? false),
            'brandingTextEnabled' => array_key_exists('branding_text_enabled', $settings) ? (bool)$settings['branding_text_enabled'] : true,
            'brandingText' => trim((string)($settings['branding_text'] ?? 'AI Chat Support')) ?: 'AI Chat Support',
            // Shopify integration flag
            'isShopify' => $hasShopifyIntegration,
            'customJs' => $this->sanitizeWidgetCustomCode($settings['widget_custom_js'] ?? null),
        ];

        $script = view('widget.script', compact('widgetConfig'))->render();

        if (env('WIDGET_LOG_REQUESTS', false)) {
            Log::info('Serving widget script', [
                'org_id' => $orgId,
                'org_slug' => $organization->slug,
                'version' => $scriptVersion
            ]);
        }

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
        $customCss = $this->sanitizeWidgetCustomCode($settings['widget_custom_css'] ?? null);

        $css = view('widget.styles', compact('theme', 'customCss'))->render();

        return response($css)
            ->header('Content-Type', 'text/css')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, proxy-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function sanitizeWidgetCustomCode($code): string
    {
        if (!is_string($code)) {
            return '';
        }

        $clean = str_replace(["\0", "\r\0", "\x1A"], '', $code);
        $clean = trim($clean);

        return $clean;
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

            if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
                return response()->json(['error' => 'Widget request origin is not allowed for this organization'], 403)
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
            $city = $request->input('city') ?? $allUserInfo['city'] ?? null;
            $sessionMetadata = $this->buildLeadSessionMetadata($request, $allUserInfo);
            $intentResult = null;

            $settings = $organization->settings ?? [];
            $verifiedOnly = (bool) ($settings['verified_only_mode'] ?? false);
            $guardrailCategories = $settings['guardrail_categories'] ?? [];
            $approvedSensitive = $settings['approved_sensitive_categories'] ?? [];
            $responseTone = $settings['response_tone'] ?? 'friendly';
            $responseLanguage = $settings['response_language'] ?? 'auto';
            $rulePolicy = $this->getWidgetRulePolicy($organization);

            if (!$message) {
                return response()->json(['error' => 'Message is required'], 400)
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Load existing conversation early for handoff and follow-up continuity
            $existingConversation = ChatConversation::where('conversation_id', $sessionId)
                ->where('organization_id', $organization->id)
                ->first();
            $previousContextPayloads = $existingConversation->metadata['last_context_payloads'] ?? [];
            $pendingFollowUpState = $this->followUpStateService->getPendingState($existingConversation);
            $hasPendingFollowUpState = is_array($pendingFollowUpState) && !empty($pendingFollowUpState);

            $lastAssistantForIntent = $this->getLastAssistantMessage($organization, $sessionId);
            $skipIntentOnAffirmative = (bool) ($rulePolicy['skip_intent_on_affirmative_follow_up'] ?? true);
            $isAffirmativeContinuationForIntent = $this->isAffirmativeFollowUp((string) $message)
                && $existingConversation
                && ((is_string($lastAssistantForIntent) && $this->responseHasQuestion($lastAssistantForIntent)) || $hasPendingFollowUpState)
                && $skipIntentOnAffirmative;

            if (!$isAffirmativeContinuationForIntent) {
                try {
                    $intentResult = app(IntentDetectionService::class)->detectIntent($message, $organization->id);
                } catch (\Throwable $t) {
                    Log::warning('Intent detection failed', [
                        'org_id' => $organization->id,
                        'error' => $t->getMessage()
                    ]);
                }
            } else {
                $intentResult = [
                    'intent' => 'follow_up',
                    'confidence' => 0.95,
                    'method' => 'rule_follow_up',
                ];
            }


            if ($existingConversation && in_array($existingConversation->agent_status, ['agent_assigned', 'agent_active'], true)) {
                $handoffText = 'A human agent is reviewing your message and will reply shortly.';

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $handoffText,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                if ($conversation) {
                    $conversation->update([
                        'agent_last_active_at' => now(),
                        'last_activity_at' => now(),
                    ]);
                }

                return response()->json(['response' => $handoffText])
                    ->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Log and save lead capture if provided
            if (!empty($allUserInfo) && isset($allUserInfo['name'])) {
                Log::info('Lead captured via widget', [
                    'org_id' => $orgId,
                    'session_id' => $sessionId,
                    'user_info' => $allUserInfo,
                    'location' => compact('country', 'region', 'location', 'city')
                ]);
                
                $this->upsertWidgetLead(
                    $organization->id,
                    $sessionId,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult,
                    $message,
                    $sessionMetadata
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );
                Log::info('Lead upserted via widget', ['org_id' => $orgId, 'session_id' => $sessionId]);
            }

            if ($this->isNumericOnlyMessage($message) && !$this->shouldBypassNumericGuard($existingConversation)) {
                $clarifyResponse = $this->buildClarifyNumberResponse();

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $clarifyResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($clarifyResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $clarifyResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $clarifyResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $clarifyResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $isAffirmativeFollowUp = $this->isAffirmativeFollowUp($message);
            $isShortFollowUp = $this->isShortFollowUp($message);
            $lastAssistantMessage = $this->getLastAssistantMessage($organization, $sessionId);
            $lastAssistantAskedQuestion = is_string($lastAssistantMessage)
                && trim($lastAssistantMessage) !== ''
                && $this->responseHasQuestion($lastAssistantMessage);
            $isContextualShortFollowUp = $isShortFollowUp
                || ($this->isOneOrTwoWordReply($message) && $lastAssistantAskedQuestion);
            $isAffirmativeContinuation = $isAffirmativeFollowUp && $lastAssistantAskedQuestion;
            $skipExactMatchOnAffirmative = (bool) ($rulePolicy['skip_exact_match_on_affirmative_follow_up'] ?? true);

            $canReusePreviousContext = !empty($previousContextPayloads)
                && ($isAffirmativeFollowUp || $isContextualShortFollowUp);

            $searchQuery = $message;
            if ($isContextualShortFollowUp && !$canReusePreviousContext) {
                if ($isAffirmativeFollowUp && $hasPendingFollowUpState) {
                    $searchQuery = $this->followUpStateService->buildPinnedFollowUpQuery($pendingFollowUpState, (string) $message);
                } else {
                $previousUserMessage = $this->getLastUserMessageForSession($organization->id, $sessionId);
                $queryParts = [];

                if (is_string($previousUserMessage) && trim($previousUserMessage) !== '') {
                    $queryParts[] = trim($previousUserMessage);
                }

                if (is_string($lastAssistantMessage) && trim($lastAssistantMessage) !== '' && $this->responseHasQuestion($lastAssistantMessage)) {
                    $queryParts[] = trim($lastAssistantMessage);
                }

                $queryParts[] = $message;
                $searchQuery = trim(implode(' ', array_filter($queryParts)));
                }
            }

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
                'query' => $searchQuery,
                'original_message' => $message,
                'query_was_enriched' => trim((string) $searchQuery) !== trim((string) $message),
                'is_short_follow_up' => $isShortFollowUp,
                'is_contextual_short_follow_up' => $isContextualShortFollowUp
            ]);
            
            $searchResults = null;
            if (!$canReusePreviousContext) {
                $searchResults = $this->aiAgentService->enhancedSearch(
                    $collectionName,
                    $searchQuery,
                    6 // Use broader retrieval window to reduce false negatives on specific product queries
                );
            }
            
            $context = '';
            $orderedResults = [];
            if ($canReusePreviousContext) {
                // Reuse last context payloads for follow-up continuity
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

                        $supplementaryInfo = $this->extractSupplementaryInfoFromPayload($payload);
                        if ($supplementaryInfo !== '') {
                            $context .= "Supplementary: " . $supplementaryInfo . "\n";
                        }

                        $modelPricing = $this->extractModelPricingFromPayload($payload);
                        if ($modelPricing['ex_showroom_price_inr'] !== '') {
                            $context .= "Ex-showroom Price (INR): " . $modelPricing['ex_showroom_price_inr'] . "\n";
                        }
                        if ($modelPricing['approx_on_road_price_inr'] !== '') {
                            $context .= "On-road Price (INR): " . $modelPricing['approx_on_road_price_inr'] . "\n";
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

            $directCatalogContext = $this->buildDirectCatalogMatchContext($organization, (string) $searchQuery);
            if ($directCatalogContext !== '') {
                $context .= ($context !== '' ? "\n" : '') . $directCatalogContext . "\n";
            }

            // Persist last context payloads for follow-up continuity (limit to top 5)
            $payloads = $this->buildContextPayloadCache($orderedResults);
            $this->persistLastContextPayloads(
                $organization,
                $sessionId,
                $payloads,
                $allUserInfo,
                compact('country', 'region', 'location', 'city')
            );

            // Create concise system prompt with official org contact metadata
            $orgWebsite = $organization->website ?: config('app.url');
            $orgEmail = $organization->contact_email ?? null;
            $orgPhone = $organization->contact_phone ?? null;
            $orgDesc  = $organization->description ? trim($this->htmlToPlainWithLinks($organization->description)) : null;

            if (($intentResult['intent'] ?? null) === 'pricing') {
                $pricingContext = $this->buildPricingContext($organization);
                if ($pricingContext !== '') {
                    $context .= ($context !== '' ? "\n\n" : '') . $pricingContext;
                } elseif ($this->shouldUsePricingFallback($context, $shopifyContext, $message)) {
                    Log::info('Pricing context missing - returning pricing fallback response', [
                        'org_id' => $organization->id,
                        'org_slug' => $organization->slug,
                        'session_id' => $sessionId
                    ]);

                    $safeResponse = $this->buildPricingUnavailableResponse($organization);

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return response()->json([
                        'response' => $safeResponse,
                        'session_id' => $sessionId,
                        'timestamp' => now()->toISOString()
                    ])->header('X-Robots-Tag', 'noindex, nofollow');
                }
            }

            // Build context with Shopify data priority
            $finalContext = '';
            if (!empty($shopifyContext)) {
                $finalContext = "LIVE STORE DATA (use this as your primary source):\n\n" . $shopifyContext . "\n\n";
            }
            if ($context) {
                $finalContext .= "Additional information from knowledge base:\n\n" . $context;
            }

            $agentContext = $this->buildAgentContext($organization->id, $sessionId);
            if ($agentContext) {
                $finalContext .= "\nAgent notes:\n" . $agentContext . "\n";
            }

            $guardrailCategory = $this->detectGuardrailCategory($message, $guardrailCategories);
            if ($guardrailCategory && !$this->isSensitiveCategoryApproved($guardrailCategory, $approvedSensitive)) {
                $safeResponse = $this->buildSensitiveGuardrailResponse($guardrailCategory, $organization);
                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $hasVerifiedContext = !empty($finalContext) || !empty($shopifyContext);
            if ($this->shouldUseAffirmativeNoContextFallback($isAffirmativeContinuation, $context, $shopifyContext, null)) {
                $safeResponse = $this->buildAffirmativeNoContextResponse($organization);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $safeResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($safeResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $safeResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($verifiedOnly && !$hasVerifiedContext) {
                $safeResponse = $this->buildVerifiedOnlyResponse($organization);
                return response()->json([
                    'response' => $safeResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $isContactQuery = $this->isContactQuery($message);

            if ($this->isCallbackRequest($message)) {
                $userPhone = $allUserInfo['phone'] ?? $allUserInfo['contact_phone'] ?? null;
                if (!$userPhone) {
                    $userPhone = $this->extractPhoneFromMessage($message);
                }

                $callbackResponse = $this->buildCallbackResponse($userPhone, $orgEmail, $orgPhone, $orgWebsite);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $callbackResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                return response()->json([
                    'response' => $callbackResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($isContactQuery) {
                $contactResponse = $this->buildContactResponse($orgEmail, $orgPhone, $orgWebsite);

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $contactResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                return response()->json([
                    'response' => $contactResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $branchFaqMatch = $this->getFunnelBranchFaqMatchResponse($organization, $pendingFollowUpState, (string) $message);
            if ($branchFaqMatch && !$hasShopifyData) {
                Log::info('Widget funnel branch FAQ match response', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'faq_id' => $branchFaqMatch['payload']['item_id'] ?? null,
                    'title' => $branchFaqMatch['payload']['title'] ?? null,
                ]);

                $branchResponse = $branchFaqMatch['response'];
                $branchFollowUp = $this->faqFollowUpService->getFollowUpText(
                    $organization,
                    $branchResponse,
                    $branchFaqMatch['payload']['follow_up'] ?? null,
                    $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                );
                if ($branchFollowUp !== '' && !$this->responseHasQuestion($branchResponse)) {
                    $branchResponse = trim($branchResponse) . "\n\n" . $branchFollowUp;
                }

                $tokenMessages = [
                    ['role' => 'user', 'content' => $message],
                ];
                $this->aiAgentService->logWidgetTokenUsage(
                    $organization->id,
                    $tokenMessages,
                    $branchResponse,
                    'faq_direct'
                );

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $branchResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($branchResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $branchResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $branchResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $branchResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $exactFaqMatch = $this->getExactFaqMatchResponse($searchResults, $organization, (string) $message);
            if ($exactFaqMatch && !$hasShopifyData && !($isAffirmativeContinuation && $skipExactMatchOnAffirmative)) {
                Log::info('Widget exact FAQ match response', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'score' => $exactFaqMatch['score'] ?? null,
                    'title' => $exactFaqMatch['payload']['title'] ?? null,
                    'item_id' => $exactFaqMatch['payload']['item_id'] ?? null,
                    'source' => $exactFaqMatch['match_source'] ?? 'semantic',
                ]);

                $directResponse = $exactFaqMatch['response'];
                $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
                $paraphrasedResponse = null;
                $skipParaphrase = (($exactFaqMatch['match_source'] ?? '') === 'keyword_fallback');

                if (!$skipParaphrase) {
                    try {
                        $paraphrasePrompt = "You are {$assistantName} for {$organization->name}. "
                            . "Tone: {$responseTone}. Language: {$responseLanguage}. "
                            . "Rewrite the following FAQ answer in 1-2 concise sentences. "
                            . "Use first-person plural (we/our). Do not add new information. "
                            . "If the answer includes contact details, keep them. "
                            . "Answer only with the rewritten response.\n\n"
                            . "FAQ Answer: \"{$directResponse}\"";

                        $paraphraseMessages = [
                            ['role' => 'system', 'content' => $paraphrasePrompt],
                            ['role' => 'user', 'content' => $message]
                        ];

                        $paraphraseOptions = ['num_predict' => 120, 'temperature' => 0.4];
                        $paraphraseModel = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                        $paraphraseResponse = $this->aiAgentService->llmChat(
                            $paraphraseMessages,
                            $paraphraseModel,
                            null,
                            $organization->id,
                            $paraphraseOptions
                        );

                        if ($paraphraseResponse && isset($paraphraseResponse['message']['content'])) {
                            $paraphrasedResponse = trim($paraphraseResponse['message']['content']);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Widget FAQ paraphrase failed', [
                            'org_id' => $organization->id,
                            'session_id' => $sessionId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $finalFaqResponse = $paraphrasedResponse ?: $directResponse;
                $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                    $organization,
                    $finalFaqResponse,
                    $exactFaqMatch['payload']['follow_up'] ?? null,
                    $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                );
                if ($faqFollowUp !== '' && !$this->responseHasQuestion($finalFaqResponse)) {
                    $finalFaqResponse = trim($finalFaqResponse) . "\n\n" . $faqFollowUp;
                }
                if (!$paraphrasedResponse) {
                    $tokenMessages = [
                        ['role' => 'user', 'content' => $message],
                    ];
                    $this->aiAgentService->logWidgetTokenUsage(
                        $organization->id,
                        $tokenMessages,
                        $finalFaqResponse,
                        'faq_direct'
                    );
                }

                Log::info('Widget direct FAQ response sent', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'paraphrased' => (bool) $paraphrasedResponse,
                    'response_preview' => substr((string) $finalFaqResponse, 0, 300) . '...',
                ]);
                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $finalFaqResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($finalFaqResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $finalFaqResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $finalFaqResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $finalFaqResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($this->shouldClarifyAffirmative($message, $organization, $sessionId)) {
                $shortResponse = $this->buildAffirmativeClarifyResponse();

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $shortResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($shortResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $shortResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $shortResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            $lowConfidenceResponse = $this->buildLowConfidenceClarificationResponse($message, $searchResults);
            if ($lowConfidenceResponse !== null && empty($shopifyContext) && !$this->isContactQuery($message)) {
                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $lowConfidenceResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($lowConfidenceResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $lowConfidenceResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $lowConfidenceResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $lowConfidenceResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            if ($this->isVeryShortQuery($message)
                && trim((string) $context) === ''
                && empty($shopifyContext)
                && !$this->isContactQuery($message)) {
                $shortResponse = $this->isPromoQuery($message)
                    ? $this->buildPromoUnavailableResponse()
                    : $this->buildClarifyResponse();

                $conversation = $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $shortResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult
                );

                $this->logIntentAnalytics(
                    $organization->id,
                    $sessionId,
                    $intentResult,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );

                if ($this->isUnansweredResponse($shortResponse)) {
                    $this->logUnansweredQuestion(
                        $organization->id,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );
                }

                if ($conversation) {
                    $this->handleEscalationIfNeeded(
                        $conversation,
                        $message,
                        $shortResponse,
                        $intentResult,
                        $request,
                        $sessionMetadata
                    );
                }

                return response()->json([
                    'response' => $shortResponse,
                    'session_id' => $sessionId,
                    'timestamp' => now()->toISOString()
                ])->header('X-Robots-Tag', 'noindex, nofollow');
            }

            // Assistant naming and channel-agnostic guidance
            $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
            $businessContext = $this->buildBusinessContext($organization);
            $promotionContext = $this->buildPromotionContext($organization);
            $faqFollowUpInstruction = $this->faqFollowUpService->getFollowUpInstruction($organization);
            $supplementaryInstruction = $this->buildSupplementaryInstruction($organization);

            // Build smart system prompt
            if ($hasShopifyData) {
                // Shopify data available - guide LLM to be conversational
                $systemPrompt = "You are {$assistantName} for {$organization->name}. ";
                $systemPrompt .= "Tone: {$responseTone}. Language: {$responseLanguage}. ";
                $systemPrompt .= "Use LIVE STORE DATA for product questions and the Knowledge Base for policies/FAQs.\n";
                $systemPrompt .= "Always ground factual answers in CURRENT CONTEXT. Use PRIOR HISTORY only to resolve references or maintain continuity.\n";
                $systemPrompt .= "Write in first-person plural as the business (use \"we/our\"), not \"they\".\n";
                $systemPrompt .= "Be concise and precise. Prefer 1-2 short sentences unless the user asks for more detail. Avoid long lists; if a list is necessary, keep it very short. If the answer is not in the provided context, say so and ask one clarifying question.\n";
                $systemPrompt .= "If the user asks how to contact, you MUST include official contact details (Email/Phone/Website if available) and nothing else.\n";
                $systemPrompt .= $supplementaryInstruction . "\n";
                $systemPrompt .= "If the user clearly ends the conversation (for example: 'goodbye', 'that's all', 'nothing else'), respond with a brief, friendly closing message (1 sentence max). If the user says 'no' as an answer to your clarifying question, continue helping with their original request instead of ending the chat.\n\n";
                $systemPrompt .= "\nCURRENT CONTEXT:\n" . $finalContext . "\n";
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
                $systemPrompt .= "Tone: {$responseTone}. Language: {$responseLanguage}. ";
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
                    $systemPrompt .= "\nCURRENT CONTEXT:\n{$context}\n";
                }
                $systemPrompt .= "Write in first-person plural as the business (use \"we/our\"), not \"they\". ";
                $systemPrompt .= "Be concise and precise. Prefer 1-2 short sentences unless the user asks for more detail. Avoid long lists; if a list is necessary, keep it very short. If the answer is not in the provided context, say so and ask one clarifying question. ";
                $systemPrompt .= "Always ground factual answers in CURRENT CONTEXT. Use PRIOR HISTORY only to resolve references or maintain continuity. ";
                $systemPrompt .= "If the user clearly ends the conversation (for example: 'goodbye', 'that's all', 'nothing else'), respond with a brief, friendly closing message (1 sentence max). If the user says 'no' as an answer to your clarifying question, continue helping with their original request instead of ending the chat. ";
                $systemPrompt .= "If the user asks how to contact, you MUST include official contact details (Email/Phone/Website if available) and nothing else.";
                $systemPrompt .= " " . $supplementaryInstruction;
            }

            $systemPrompt .= "\n\nOUTPUT ENVELOPE: Return JSON ONLY with keys: response (string), entity (string), topics_covered (string array), follow_up (object|null with type and topic array).";
            $systemPrompt .= " Keep response natural and concise for users. Set follow_up=null if not asking a follow-up question.";

            // Get AI response using llmChat for better token tracking
            $messages = $this->buildChatMessages($organization, $sessionId, $systemPrompt, $message, (string) $finalContext);
            Log::info('Widget LLM context prepared', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'system_prompt_preview' => substr((string) $systemPrompt, 0, 600) . '...',
                'context_length' => strlen((string) $finalContext),
            ]);
            
            // Use organization-specific AI provider and model
            $llmStartedAt = microtime(true);
            $oneCallEnvelopeUsed = false;
            $oneCallEnvelopeParseOk = false;
            $maxTokens = 220;
            $affirmativeMaxTokens = (int) ($rulePolicy['affirmative_follow_up_max_tokens'] ?? 140);
            if ($isAffirmativeContinuation) {
                $maxTokens = max(80, min(300, $affirmativeMaxTokens));
            } elseif ($isContactQuery) {
                $maxTokens = 60;
            } elseif (preg_match('/\b(detail|explain|list|steps|guide|compare|pricing|plans|features|benefits|requirements|policy|refund|return|shipping|warranty|guarantee)\b/i', $message)
                || strlen($message) > 120
                || strlen($finalContext) > 2000) {
                $maxTokens = 420;
            }
            $localOptions = ['num_predict' => $maxTokens, 'temperature' => 0.3];
            $aiProvider = $this->aiAgentService->getAiProviderForOrganization($organization->id);
            if ($this->shouldUseOpenAiFallback($message, $organization, $responseLanguage)) {
                $model = $this->aiAgentService->getOpenAiModelForOrganization($organization->id);
                $aiResponse = $this->aiAgentService->openAiChat($messages, $model, null, $organization->id);
            } elseif ($aiProvider === 'openai') {
                // Use OpenAI with organization-specific or global model
                $model = $this->aiAgentService->getOpenAiModelForOrganization($organization->id);
                $aiResponse = $this->aiAgentService->openAiChat($messages, $model, null, $organization->id);
            } else {
                // Use local LLM with organization-specific or global model
                $model = $this->aiAgentService->getLlamaModelForOrganization($organization->id);
                $aiResponse = $this->aiAgentService->llmChat($messages, $model, null, $organization->id, $localOptions);
            }

            $rawResponseText = null;
            $structuredFollowUpState = null;
            $oneCallEnvelopeRetryAttempted = false;
            $oneCallEnvelopeRetrySucceeded = false;
            if (!$aiResponse || !isset($aiResponse['message']['content'])) {
                // Fallback to old method
                $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt);
                $rawResponseText = $aiResponse['answer'] ?? null;
                $responseText = $rawResponseText ?? 'I apologize, but I\'m experiencing technical difficulties. Please try again later.';
            } else {
                $rawResponseText = $aiResponse['message']['content'];
                $responseText = $rawResponseText;

                $envelope = $this->extractOneCallEnvelope((string) $rawResponseText);
                if (is_array($envelope)) {
                    $oneCallEnvelopeParseOk = true;
                    $responseText = (string) ($envelope['response'] ?? $responseText);
                    $structuredFollowUpState = $envelope['structured_state'] ?? null;
                    $oneCallEnvelopeUsed = is_array($structuredFollowUpState) && !empty($structuredFollowUpState);
                } else {
                    $oneCallEnvelopeRetryAttempted = true;
                    $retryEnvelope = $this->retryStrictEnvelopeExtraction(
                        (string) $rawResponseText,
                        (string) $message,
                        $organization,
                        (int) $organization->id,
                        $aiProvider
                    );
                    if (is_array($retryEnvelope)) {
                        $oneCallEnvelopeRetrySucceeded = true;
                        $oneCallEnvelopeParseOk = true;
                        $responseText = (string) ($retryEnvelope['response'] ?? $responseText);
                        $structuredFollowUpState = $retryEnvelope['structured_state'] ?? null;
                        $oneCallEnvelopeUsed = is_array($structuredFollowUpState) && !empty($structuredFollowUpState);
                    }
                }
            }
            $llmElapsedMs = round((microtime(true) - $llmStartedAt) * 1000, 2);

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

            $hallucinationBlocked = false;
            if ($this->shouldBlockRoleQueryWithoutContext($message, $finalContext ?? '')) {
                $responseText = $this->getRoleInfoUnavailableResponse();
                $hallucinationBlocked = true;
            } else {
                [$responseText, $hallucinationBlocked] = $this->enforceContextOnlyAnswer(
                    $message,
                    $finalContext ?? '',
                    $responseText,
                    $organization
                );
            }

            // Detailed logging for debugging
            Log::info('Widget AI Response Debug', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'user_message' => $message,
                'llm_elapsed_ms' => $llmElapsedMs,
                'one_call_envelope_parse_ok' => $oneCallEnvelopeParseOk,
                'one_call_envelope_used' => $oneCallEnvelopeUsed,
                'one_call_envelope_retry_attempted' => $oneCallEnvelopeRetryAttempted,
                'one_call_envelope_retry_succeeded' => $oneCallEnvelopeRetrySucceeded,
                'extraction_call_skipped' => $oneCallEnvelopeUsed,
                'estimated_saved_ms' => $oneCallEnvelopeUsed ? 1800 : 0,
                'context_length' => strlen($finalContext),
                'context_found' => !empty($finalContext),
                'has_shopify_data' => !empty($shopifyContext),
                'context_preview' => $finalContext ? substr($finalContext, 0, 300) . '...' : 'No context',
                'system_prompt_length' => strlen($systemPrompt),
                'ai_response_length' => strlen($responseText),
                'ai_response_preview' => substr($responseText, 0, 300) . '...',
                'full_ai_response' => $responseText
            ]);

            $escalationReason = $this->getEscalationReason($message, $responseText, $intentResult);
            $isWithinHours = $this->isWithinBusinessHours($organization);
            if ($escalationReason === 'user_requested_human' && $isWithinHours === false) {
                $handoffMessage = $this->buildHandoffMessage($organization);
                if ($handoffMessage !== '') {
                    $responseText = trim($responseText) . "\n\n" . $handoffMessage;
                }
            }

            $responseHasQuestion = $this->responseHasQuestion($responseText);
            $isConversationEnding = $this->shouldTreatAsConversationEnding(
                $message,
                $lastAssistantAskedQuestion,
                $hasPendingFollowUpState
            );

            if (!$hallucinationBlocked && !$responseHasQuestion && !$isConversationEnding) {
                $intentFollowUp = $this->buildFollowUpPrompt($intentResult);
                if ($intentFollowUp !== '') {
                    $responseText = trim($responseText) . "\n\n" . $intentFollowUp;
                } else {
                    $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                        $organization,
                        $responseText,
                        null,
                        $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                    );
                    if ($faqFollowUp !== '') {
                        $responseText = trim($responseText) . "\n\n" . $faqFollowUp;
                    } else {
                        $suggestion = $this->buildProactiveSuggestion($intentResult);
                        if ($suggestion !== '') {
                            $responseText = trim($responseText) . "\n\n" . $suggestion;
                        }
                    }
                }
            }

            // Save conversation to database
            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $responseText,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                $intentResult,
                $structuredFollowUpState
            );

            // Log intent distribution analytics
            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                $intentResult,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($this->isUnansweredResponse($responseText)) {
                $this->logUnansweredQuestion(
                    $organization->id,
                    $sessionId,
                    $message,
                    $responseText,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );
            }

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $responseText,
                    $intentResult,
                    $request,
                    $sessionMetadata,
                    $escalationReason
                );
            }

            // Log the conversation for analytics
            Log::info('Widget chat', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => $responseText,
                'one_call_envelope_used' => $oneCallEnvelopeUsed,
                'llm_elapsed_ms' => $llmElapsedMs,
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

        if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
            return response()->json(['error' => 'Widget request origin is not allowed for this organization'], 403);
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
        $city = $request->input('city') ?? ($allUserInfo['city'] ?? null);
        $sessionMetadata = $this->buildLeadSessionMetadata($request, $allUserInfo);

        $settings = $organization->settings ?? [];
        $verifiedOnly = (bool) ($settings['verified_only_mode'] ?? false);
        $guardrailCategories = $settings['guardrail_categories'] ?? [];
        $approvedSensitive = $settings['approved_sensitive_categories'] ?? [];
        $responseTone = $settings['response_tone'] ?? 'friendly';
        $responseLanguage = $settings['response_language'] ?? 'auto';
        
        if (!$message) {
            return response()->json(['error' => 'Message is required'], 400);
        }

        $existingConversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if ($this->isNumericOnlyMessage($message) && !$this->shouldBypassNumericGuard($existingConversation)) {
            $clarifyResponse = $this->buildClarifyNumberResponse();

            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $clarifyResponse,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                null,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($this->isUnansweredResponse($clarifyResponse)) {
                $this->logUnansweredQuestion(
                    $organization->id,
                    $sessionId,
                    $message,
                    $clarifyResponse,
                    $request,
                    compact('country', 'region', 'location', 'city'),
                    $sessionMetadata
                );
            }

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $clarifyResponse,
                    null,
                    $request,
                    $sessionMetadata
                );
            }

            return response()->stream(function () use ($clarifyResponse) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $clarifyResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $existingConversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if ($existingConversation && in_array($existingConversation->agent_status, ['agent_assigned', 'agent_active'], true)) {
            $handoffText = 'A human agent is reviewing your message and will reply shortly.';
            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $handoffText,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            if ($conversation) {
                $conversation->update([
                    'agent_last_active_at' => now(),
                    'last_activity_at' => now(),
                ]);
            }

            return response()->stream(function () use ($handoffText) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $handoffText, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $humanRequestReason = $this->getEscalationReason($message, '', null);
        $isWithinHours = $this->isWithinBusinessHours($organization);
        if ($humanRequestReason === 'user_requested_human' && $isWithinHours === false) {
            $handoffText = $this->buildHandoffMessage($organization);
            if ($handoffText === '') {
                $handoffText = 'A human agent will review your message and reply as soon as possible.';
            }

            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $handoffText,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                null,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $handoffText,
                    null,
                    $request,
                    $sessionMetadata,
                    $humanRequestReason
                );
            }

            return response()->stream(function () use ($handoffText) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $handoffText, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $guardrailCategory = $this->detectGuardrailCategory($message, $guardrailCategories);
        if ($guardrailCategory && !$this->isSensitiveCategoryApproved($guardrailCategory, $approvedSensitive)) {
            $safeResponse = $this->buildSensitiveGuardrailResponse($guardrailCategory, $organization);
            $conversation = $this->saveConversationToDatabase(
                $organization,
                $sessionId,
                $message,
                $safeResponse,
                $allUserInfo,
                compact('country', 'region', 'location', 'city'),
                null
            );

            $this->logIntentAnalytics(
                $organization->id,
                $sessionId,
                null,
                $request,
                compact('country', 'region', 'location', 'city'),
                $sessionMetadata
            );

            if ($conversation) {
                $this->handleEscalationIfNeeded(
                    $conversation,
                    $message,
                    $safeResponse,
                    null,
                    $request,
                    $sessionMetadata
                );
            }

            return response()->stream(function () use ($safeResponse) {
                $this->initStreamOutput();
                echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'X-Robots-Tag' => 'noindex, nofollow'
            ]);
        }

        $rulePolicy = $this->getWidgetRulePolicy($organization);

        $pendingFollowUpState = $this->followUpStateService->getPendingState($existingConversation);

        return response()->stream(function () use ($organization, $message, $sessionId, $request, $allUserInfo, $country, $region, $location, $city, $sessionMetadata, $verifiedOnly, $responseTone, $responseLanguage, $rulePolicy, $pendingFollowUpState) {
            $this->initStreamOutput();
            
            $lastAssistantMessageForEnding = $this->getLastAssistantMessage($organization, $sessionId);
            $lastAssistantAskedQuestionForEnding = is_string($lastAssistantMessageForEnding)
                && trim($lastAssistantMessageForEnding) !== ''
                && $this->responseHasQuestion($lastAssistantMessageForEnding);
            $hasPendingFollowUpStateForEnding = is_array($pendingFollowUpState) && !empty($pendingFollowUpState);

            if ($this->shouldTreatAsConversationEnding($message, $lastAssistantAskedQuestionForEnding, $hasPendingFollowUpStateForEnding)) {
                $closingResponses = [
                    "No problem! Feel free to reach out if you need anything.",
                    "Understood! Let me know if you have any questions later.",
                    "All good! We're here if you need us.",
                    "Thank you! Don't hesitate to contact us if you need help."
                ];
                $closingResponse = $closingResponses[array_rand($closingResponses)];
                
                echo "data: " . json_encode(['content' => $closingResponse, 'done' => true]) . "\n\n";
                $this->streamFlush();
                
                $this->saveConversationToDatabase(
                    $organization,
                    $sessionId,
                    $message,
                    $closingResponse,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    null
                );
                
                return;
            }
            
            try {
                // Build context (simplified version - you can reuse logic from chat())
                $aiService = app(AiAgentService::class);
                $actionService = app(\App\Services\ActionService::class);

                $lastAssistantMessage = $this->getLastAssistantMessage($organization, $sessionId);
                $lastAssistantAskedQuestion = is_string($lastAssistantMessage)
                    && trim($lastAssistantMessage) !== ''
                    && $this->responseHasQuestion($lastAssistantMessage);
                $isAffirmativeFollowUp = $this->isAffirmativeFollowUp((string) $message);
                $isAffirmativeContinuation = $isAffirmativeFollowUp && ($lastAssistantAskedQuestion || (is_array($pendingFollowUpState) && !empty($pendingFollowUpState)));
                $skipIntentOnAffirmative = (bool) ($rulePolicy['skip_intent_on_affirmative_follow_up'] ?? true);
                $skipExactMatchOnAffirmative = (bool) ($rulePolicy['skip_exact_match_on_affirmative_follow_up'] ?? true);

                $searchQuery = $message;
                if ($isAffirmativeContinuation) {
                    if ($isAffirmativeFollowUp && is_array($pendingFollowUpState) && !empty($pendingFollowUpState)) {
                        $searchQuery = $this->followUpStateService->buildPinnedFollowUpQuery($pendingFollowUpState, (string) $message);
                    } else {
                        $previousUserMessage = $this->getLastUserMessageForSession($organization->id, $sessionId);
                        $queryParts = [];

                        if (is_string($previousUserMessage) && trim($previousUserMessage) !== '') {
                            $queryParts[] = trim($previousUserMessage);
                        }

                        if (is_string($lastAssistantMessage) && trim($lastAssistantMessage) !== '') {
                            $queryParts[] = trim($lastAssistantMessage);
                        }

                        $queryParts[] = $message;
                        $searchQuery = trim(implode(' ', array_filter($queryParts)));
                    }
                }

                $actionQuery = $message;
                if ($this->isPricingFollowUp($message)) {
                    $previousMessage = $this->getLastUserMessageForSession($organization->id, $sessionId);
                    if ($previousMessage) {
                        $actionQuery = trim($previousMessage . ' ' . $message);
                    }
                }
                
                // Check if any action should be executed
                if ($isAffirmativeContinuation && $skipIntentOnAffirmative) {
                    $actionResult = [
                        'type' => 'no_action',
                        'intent' => [
                            'intent' => 'follow_up',
                            'confidence' => 0.95,
                            'method' => 'rule_follow_up',
                        ],
                    ];
                } else {
                    $actionResult = $actionService->processQuery($actionQuery, $organization->id);
                }
                $intentResult = $actionResult['intent'] ?? null;

                // Update lead with intent/priority if lead info is provided
                $this->upsertWidgetLead(
                    $organization->id,
                    $sessionId,
                    $allUserInfo,
                    compact('country', 'region', 'location', 'city'),
                    $intentResult,
                    $message,
                    $sessionMetadata
                );
                
                $context = '';
                $liveData = null;
                $isPricingIntent = ($intentResult['intent'] ?? null) === 'pricing';
                $searchResults = null;
                $resultsForPayloadCache = [];
                
                // If action was executed, include the live data
                if ($actionResult['type'] === 'action_executed' && isset($actionResult['result']['success']) && $actionResult['result']['success']) {
                    $liveData = $actionResult['result']['data'] ?? null;
                    $actionName = $actionResult['action']['action_name'] ?? 'database query';
                    
                    if ($liveData) {
                        $context .= "\n\n[LIVE DATA from {$actionName}]:\n";
                        $context .= json_encode($liveData, JSON_PRETTY_PRINT) . "\n";
                        $context .= "[END LIVE DATA]\n\n";
                        if ($isPricingIntent) {
                            $pricingHints = $this->buildPricingLiveDataHints($liveData);
                            if ($pricingHints !== '') {
                                $context .= $pricingHints . "\n\n";
                            }
                            $pricingContext = $this->buildPricingContext($organization);
                            $context .= "IMPORTANT: Use the LIVE DATA above as primary. Also include PRICING CONTEXT below (credit packages + conversation estimates) if relevant. Format it in a user-friendly way.\n\n";
                            $context .= $this->buildLiveDataValidationRules($liveData);
                            if ($pricingContext !== '') {
                                $context .= "\nPRICING CONTEXT:\n{$pricingContext}\n";
                            }
                        } else {
                            $context .= "IMPORTANT: Use ONLY the LIVE DATA above to answer the question. Format it in a user-friendly way.\n\n";
                            $context .= $this->buildLiveDataValidationRules($liveData);
                        }
                    }
                }
                
                // Search for relevant context only if no action was executed or as supplementary info
                if (!$liveData) {
                    $searchResults = $aiService->enhancedSearch($organization->slug, $searchQuery, 5);
                    
                    if ($searchResults && isset($searchResults['results'])) {
                        $resultsForPayloadCache = $searchResults['results'];
                        $context .= "\n\nAdditional information from knowledge base:\n\n";
                        foreach ($searchResults['results'] as $result) {
                            $payload = $result['payload'] ?? [];
                            if (isset($payload['title'])) $context .= "Title: " . $payload['title'] . "\n";
                            if (isset($payload['content'])) $context .= "Content: " . $payload['content'] . "\n";
                            
                            // Extract follow-up question if present
                            if (isset($payload['follow_up']) && !empty($payload['follow_up'])) {
                                $context .= "Follow-up: " . $payload['follow_up'] . "\n";
                            }

                            $supplementaryInfo = $this->extractSupplementaryInfoFromPayload($payload);
                            if ($supplementaryInfo !== '') {
                                $context .= "Supplementary: " . $supplementaryInfo . "\n";
                            }

                            $modelPricing = $this->extractModelPricingFromPayload($payload);
                            if ($modelPricing['ex_showroom_price_inr'] !== '') {
                                $context .= "Ex-showroom Price (INR): " . $modelPricing['ex_showroom_price_inr'] . "\n";
                            }
                            if ($modelPricing['approx_on_road_price_inr'] !== '') {
                                $context .= "On-road Price (INR): " . $modelPricing['approx_on_road_price_inr'] . "\n";
                            }
                            
                            $context .= "\n";
                        }
                    }

                    $directCatalogContext = $this->buildDirectCatalogMatchContext($organization, (string) $searchQuery);
                    if ($directCatalogContext !== '') {
                        $context .= "\n" . $directCatalogContext . "\n";
                    }
                }

                if (!empty($resultsForPayloadCache)) {
                    $payloads = $this->buildContextPayloadCache($resultsForPayloadCache);
                    $this->persistLastContextPayloads(
                        $organization,
                        $sessionId,
                        $payloads,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city')
                    );
                }

                if ($isPricingIntent && !$liveData) {
                    $pricingContext = $this->buildPricingContext($organization);
                    if ($pricingContext !== '') {
                        $context .= "\n\n" . $pricingContext;
                    } elseif ($this->shouldUsePricingFallback($context, null, $message)) {
                        Log::info('Pricing context missing - returning pricing fallback response (stream)', [
                            'org_id' => $organization->id,
                            'org_slug' => $organization->slug,
                            'session_id' => $sessionId
                        ]);

                        $safeResponse = $this->buildPricingUnavailableResponse($organization);
                        echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                        $this->streamFlush();

                        $conversation = $this->saveConversationToDatabase(
                            $organization,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $allUserInfo,
                            compact('country', 'region', 'location', 'city'),
                            $intentResult
                        );

                        $this->logIntentAnalytics(
                            $organization->id,
                            $sessionId,
                            $intentResult,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );

                        if ($this->isUnansweredResponse($safeResponse)) {
                            $this->logUnansweredQuestion(
                                $organization->id,
                                $sessionId,
                                $message,
                                $safeResponse,
                                $request,
                                compact('country', 'region', 'location', 'city'),
                                $sessionMetadata
                            );
                        }

                        if ($conversation) {
                            $this->handleEscalationIfNeeded(
                                $conversation,
                                $message,
                                $safeResponse,
                                $intentResult,
                                $request,
                                $sessionMetadata
                            );
                        }

                        return;
                    }
                }

                $agentContext = $this->buildAgentContext($organization->id, $sessionId);
                if ($agentContext) {
                    $context .= "\nAgent notes:\n" . $agentContext . "\n";
                }

                if ($this->shouldUseAffirmativeNoContextFallback($isAffirmativeContinuation, $context, null, $liveData)) {
                    $safeResponse = $this->buildAffirmativeNoContextResponse($organization);
                    echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($safeResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($verifiedOnly && !$liveData && trim($context) === '') {
                    $safeResponse = $this->buildVerifiedOnlyResponse($organization);
                    echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($safeResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $branchFaqMatch = $this->getFunnelBranchFaqMatchResponse($organization, $pendingFollowUpState, (string) $message);
                if ($branchFaqMatch && !$liveData) {
                    $directResponse = $branchFaqMatch['response'];
                    $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                        $organization,
                        $directResponse,
                        $branchFaqMatch['payload']['follow_up'] ?? null,
                        $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                    );
                    if ($faqFollowUp !== '') {
                        $directResponse = trim($directResponse) . "\n\n" . $faqFollowUp;
                    }

                    Log::info('Widget stream funnel branch FAQ response', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'title' => $branchFaqMatch['payload']['title'] ?? null,
                        'item_id' => $branchFaqMatch['payload']['item_id'] ?? null,
                    ]);

                    $tokenMessages = [
                        ['role' => 'user', 'content' => $message],
                    ];
                    $this->aiAgentService->logWidgetTokenUsage(
                        $organization->id,
                        $tokenMessages,
                        $directResponse,
                        'faq_direct'
                    );

                    echo "data: " . json_encode(['content' => $directResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $directResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($directResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $directResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $directResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $exactFaqMatch = $this->getExactFaqMatchResponse($searchResults, $organization, (string) $message);
                if ($exactFaqMatch && !$liveData && !($isAffirmativeContinuation && $skipExactMatchOnAffirmative)) {
                    $directResponse = $exactFaqMatch['response'];
                    $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                        $organization,
                        $directResponse,
                        $exactFaqMatch['payload']['follow_up'] ?? null,
                        $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                    );
                    if ($faqFollowUp !== '') {
                        $directResponse = trim($directResponse) . "\n\n" . $faqFollowUp;
                    }
                    Log::info('Widget stream exact FAQ match response', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'score' => $exactFaqMatch['score'] ?? null,
                        'title' => $exactFaqMatch['payload']['title'] ?? null,
                        'source' => $exactFaqMatch['match_source'] ?? 'semantic',
                    ]);

                    $tokenMessages = [
                        ['role' => 'user', 'content' => $message],
                    ];
                    $this->aiAgentService->logWidgetTokenUsage(
                        $organization->id,
                        $tokenMessages,
                        $directResponse,
                        'llm_chat_stream'
                    );

                    echo "data: " . json_encode(['content' => $directResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $directResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($directResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $directResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $directResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($this->shouldBlockRoleQueryWithoutContext($message, $context)) {
                    $safeResponse = $this->getRoleInfoUnavailableResponse();
                    echo "data: " . json_encode(['content' => $safeResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $safeResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($safeResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $safeResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $safeResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($this->shouldClarifyAffirmative($message, $organization, $sessionId)) {
                    $shortResponse = $this->buildAffirmativeClarifyResponse();

                    echo "data: " . json_encode(['content' => $shortResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($shortResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $shortResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $shortResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $lowConfidenceResponse = $this->buildLowConfidenceClarificationResponse($message, $searchResults);
                if ($lowConfidenceResponse !== null && !$liveData && !$this->isContactQuery($message)) {
                    echo "data: " . json_encode(['content' => $lowConfidenceResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $lowConfidenceResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($lowConfidenceResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $lowConfidenceResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $lowConfidenceResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                if ($this->isVeryShortQuery($message)
                    && trim((string) $context) === ''
                    && !$liveData
                    && !$this->isContactQuery($message)) {
                    $shortResponse = $this->isPromoQuery($message)
                        ? $this->buildPromoUnavailableResponse()
                        : $this->buildClarifyResponse();

                    echo "data: " . json_encode(['content' => $shortResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $shortResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($shortResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $shortResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $shortResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $orgWebsite = $organization->website ?: config('app.url');
                $orgEmail = $organization->contact_email ?? null;
                $orgPhone = $organization->contact_phone ?? null;

                if ($this->isCallbackRequest($message)) {
                    $userPhone = $allUserInfo['phone'] ?? $allUserInfo['contact_phone'] ?? null;
                    if (!$userPhone) {
                        $userPhone = $this->extractPhoneFromMessage($message);
                    }

                    $callbackResponse = $this->buildCallbackResponse($userPhone, $orgEmail, $orgPhone, $orgWebsite);
                    echo "data: " . json_encode(['content' => $callbackResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $callbackResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $callbackResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata
                        );
                    }

                    return;
                }

                $contactQuickResponse = $this->isContactQuery($message)
                    ? $this->buildContactResponse($orgEmail, $orgPhone, $orgWebsite)
                    : null;

                // Build messages
                $assistantName = $organization->settings['assistant_display_name'] ?? 'AI Assistant';
                $systemPrompt = "You are {$assistantName} for {$organization->name}.";
                $businessContext = $this->buildBusinessContext($organization);
                $promotionContext = $this->buildPromotionContext($organization);
                $supplementaryInstruction = $this->buildSupplementaryInstruction($organization);
                $systemPrompt .= " Tone: {$responseTone}. Language: {$responseLanguage}.";
                $systemPrompt .= " Write in first-person plural as the business (use \"we/our\"), not \"they\".";
                $systemPrompt .= " Be concise and precise. Prefer 1-2 short sentences unless the user asks for more detail. Avoid long lists; if a list is necessary, keep it very short.";
                $systemPrompt .= " If the user asks how to contact, you MUST include official contact details (Email/Phone/Website if available) and nothing else.";
                $systemPrompt .= " " . $supplementaryInstruction;
                $systemPrompt .= " Website: {$orgWebsite}";
                if ($orgEmail) $systemPrompt .= " | Email: {$orgEmail}";
                if ($orgPhone) $systemPrompt .= " | Phone: {$orgPhone}";
                $systemPrompt .= ".";
                if ($businessContext) {
                    $systemPrompt .= "\n" . $businessContext;
                }
                if ($promotionContext) {
                    $systemPrompt .= "\n" . $promotionContext;
                }
                $aiProvider = $this->aiAgentService->getAiProviderForOrganization($organization->id);
                $contextForPrompt = $aiProvider === 'openai'
                    ? $this->compactContextForOpenAi((string) $context)
                    : (string) $context;

                if ($contextForPrompt) {
                    $systemPrompt .= "\nCURRENT CONTEXT:\n" . $contextForPrompt . "\n";
                }
                if ($intentResult && isset($intentResult['intent'])) {
                    $systemPrompt .= "\nIntent: " . $intentResult['intent'] . ". Add a short follow-up question and one proactive suggestion if appropriate.";
                }

                $lowThinkingMode = $this->shouldUseLowThinkingMode(
                    (string) $message,
                    $intentResult,
                    $isAffirmativeContinuation,
                    (string) $contextForPrompt
                );

                if ($aiProvider === 'openai' && $lowThinkingMode) {
                    $systemPrompt .= "\nFAST MODE: Low thinking for support queries. Use context-grounded, direct answering only. Do not over-reason. Prefer short factual output (1-3 concise lines) unless the user explicitly asks for detail.";
                    Log::info('OpenAI low-thinking mode enabled', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'intent' => $intentResult['intent'] ?? null,
                        'message_words' => str_word_count((string) $message),
                    ]);
                }

                $chatMessages = $this->buildChatMessages($organization, $sessionId, $systemPrompt, $message, (string) $context);
                $useOpenAiFallback = $this->shouldUseOpenAiFallback($message, $organization, $responseLanguage) || $aiProvider === 'openai';
                $maxTokens = $contactQuickResponse ? 60 : 220;
                $affirmativeMaxTokens = (int) ($rulePolicy['affirmative_follow_up_max_tokens'] ?? 140);
                if ($isAffirmativeContinuation && !$contactQuickResponse) {
                    $maxTokens = max(80, min(300, $affirmativeMaxTokens));
                }
                if (!$contactQuickResponse && (preg_match('/\b(detail|explain|list|steps|guide|compare|pricing|plans|features|benefits|requirements|policy|refund|return|shipping|warranty|guarantee)\b/i', $message)
                    || strlen($message) > 120
                    || strlen($context) > 2000)) {
                    $maxTokens = 420;
                }

                if ($contactQuickResponse) {
                    $streamBackendUsed = 'contact_quick_response';
                    $streamBackendAttempts = [];
                    $fullResponse = $contactQuickResponse;
                    echo "data: " . json_encode(['content' => $fullResponse, 'done' => true]) . "\n\n";
                    $this->streamFlush();
                } else {
                $streamBackendUsed = null;
                $streamBackendAttempts = [];

                if ($useOpenAiFallback) {
                    $model = $this->aiAgentService->getOpenAiModelForOrganization($organization->id);
                    $aiResponse = $this->aiAgentService->openAiChat($chatMessages, $model, null, $organization->id);
                    $fullResponse = (string) ($aiResponse['message']['content'] ?? '');

                    if (trim($fullResponse) !== '') {
                        $streamBackendUsed = 'openai';
                        $streamBackendAttempts[] = [
                            'attempt' => 'openai-primary',
                            'model' => $model,
                            'successful' => true,
                        ];
                        echo "data: " . json_encode(['content' => $fullResponse, 'done' => true]) . "\n\n";
                        $this->streamFlush();
                    } else {
                        $streamBackendAttempts[] = [
                            'attempt' => 'openai-primary',
                            'model' => $model,
                            'successful' => false,
                            'reason' => 'empty_response',
                        ];
                        $useOpenAiFallback = false;
                    }
                }

                if (!$useOpenAiFallback) {
                    // Stream from FastAPI with Vast.ai GPU
                    $responseStartTime = microtime(true);
                    Log::info('Starting LLM response generation', ['model' => 'llama3:8b-instruct-q5_K_M', 'use_vastai' => true]);
                    
                    $fastApiUrl = config('services.ai_agent.url');
                    $model = $this->aiAgentService->getLlamaModelForOrganization($organization->id);

                    $fullResponse = '';
                    $streamAttempt = function (bool $useVast, string $attemptModel, string $attemptLabel) use ($fastApiUrl, $chatMessages, $maxTokens, &$fullResponse) {
                        $sseBuffer = '';
                        $attemptResponse = '';
                        $hadOutput = false;
                        $hadDone = false;
                        $hadError = false;

                        $ch = curl_init("{$fastApiUrl}/llm/chat/stream");
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => false,
                            CURLOPT_HEADER => false,
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode([
                                'messages' => $chatMessages,
                                'model' => $attemptModel,
                                'backend_type' => 'ollama',
                                'options' => [
                                    'num_predict' => $maxTokens,
                                    'temperature' => 0.3,
                                    'use_vastai' => $useVast,
                                ],
                            ]),
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_TIMEOUT => 70,
                            CURLOPT_WRITEFUNCTION => function ($curl, $data) use (&$sseBuffer, &$attemptResponse, &$hadOutput, &$hadDone, &$hadError, &$fullResponse) {
                                $sseBuffer .= $data;

                                $parts = explode("\n\n", $sseBuffer);
                                $sseBuffer = array_pop($parts);

                                foreach ($parts as $part) {
                                    $part = trim($part);
                                    if ($part === '') {
                                        continue;
                                    }

                                    $lines = preg_split('/\r?\n/', $part);
                                    foreach ($lines as $line) {
                                        $line = trim($line);
                                        if (!str_starts_with($line, 'data: ')) {
                                            continue;
                                        }

                                        $payload = json_decode(substr($line, 6), true);
                                        if (!is_array($payload)) {
                                            continue;
                                        }

                                        if (!empty($payload['error'])) {
                                            $hadError = true;
                                            continue;
                                        }

                                        $hasContent = isset($payload['content']) && $payload['content'] !== '';
                                        if ($hasContent) {
                                            $attemptResponse .= $payload['content'];
                                            $fullResponse .= $payload['content'];
                                            $hadOutput = true;
                                        }

                                        if (!empty($payload['done'])) {
                                            $hadDone = true;
                                        }

                                        if ($hasContent || !empty($payload['done'])) {
                                            echo "data: " . json_encode($payload) . "\n\n";
                                            $this->streamFlush();
                                        }
                                    }
                                }

                                return strlen($data);
                            },
                        ]);

                        curl_exec($ch);
                        $curlErrNo = curl_errno($ch);
                        $curlErr = $curlErrNo ? curl_error($ch) : null;
                        curl_close($ch);

                        if (trim($sseBuffer) !== '') {
                            $lines = preg_split('/\r?\n/', trim($sseBuffer));
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (!str_starts_with($line, 'data: ')) {
                                    continue;
                                }
                                $payload = json_decode(substr($line, 6), true);
                                if (!is_array($payload)) {
                                    continue;
                                }
                                if (!empty($payload['error'])) {
                                    $hadError = true;
                                    continue;
                                }
                                if (isset($payload['content']) && $payload['content'] !== '') {
                                    $attemptResponse .= $payload['content'];
                                    $fullResponse .= $payload['content'];
                                    $hadOutput = true;
                                    echo "data: " . json_encode(['content' => $payload['content'], 'done' => false]) . "\n\n";
                                    $this->streamFlush();
                                }
                                if (!empty($payload['done'])) {
                                    $hadDone = true;
                                    echo "data: " . json_encode(['content' => '', 'done' => true]) . "\n\n";
                                    $this->streamFlush();
                                }
                            }
                        }

                        Log::info('Widget stream backend attempt', [
                            'attempt' => $attemptLabel,
                            'model' => $attemptModel,
                            'use_vastai' => $useVast,
                            'curl_errno' => $curlErrNo,
                            'curl_error' => $curlErr,
                            'had_output' => $hadOutput,
                            'had_done' => $hadDone,
                            'had_error' => $hadError,
                            'response_length' => strlen($attemptResponse),
                        ]);

                        return [
                            'curl_errno' => $curlErrNo,
                            'curl_error' => $curlErr,
                            'had_output' => $hadOutput,
                            'had_done' => $hadDone,
                            'had_error' => $hadError,
                            'response_length' => strlen($attemptResponse),
                        ];
                    };

                    $fallbackReason = null;
                    if ($aiProvider === 'openai') {
                        $fallbackReason = [
                            'openai_failed_or_empty' => true,
                            'skip_vast_for_openai_provider' => true,
                        ];
                    } else {
                        $firstAttempt = $streamAttempt(true, $model, 'vast-primary');
                        $streamBackendAttempts[] = [
                            'attempt' => 'vast-primary',
                            'model' => $model,
                            'successful' => ($firstAttempt['had_output'] && $firstAttempt['had_done'] && !$firstAttempt['had_error'] && $firstAttempt['curl_errno'] === 0),
                            'had_output' => $firstAttempt['had_output'],
                            'had_done' => $firstAttempt['had_done'],
                            'had_error' => $firstAttempt['had_error'],
                            'curl_errno' => $firstAttempt['curl_errno'],
                        ];

                        $shouldRetryLocal = (
                            !$firstAttempt['had_output']
                            || !$firstAttempt['had_done']
                            || $firstAttempt['had_error']
                            || $firstAttempt['curl_errno'] !== 0
                        );

                        if (!$shouldRetryLocal) {
                            $streamBackendUsed = 'vast_primary';
                        } else {
                            $fallbackReason = [
                                'had_output' => $firstAttempt['had_output'],
                                'had_done' => $firstAttempt['had_done'],
                                'had_error' => $firstAttempt['had_error'],
                                'curl_errno' => $firstAttempt['curl_errno'],
                            ];
                        }
                    }

                    if (!$streamBackendUsed) {
                        $fullResponse = '';
                        Log::warning('Widget stream retrying on local fallback model', [
                            'reason' => $fallbackReason,
                            'fallback_model' => 'llama3.2:3b',
                            'session_id' => $sessionId,
                            'org_id' => $organization->id,
                        ]);

                        $secondAttempt = $streamAttempt(false, 'llama3.2:3b', 'local-fallback-3b');
                        $streamBackendAttempts[] = [
                            'attempt' => 'local-fallback-3b',
                            'model' => 'llama3.2:3b',
                            'successful' => ($secondAttempt['had_output'] && $secondAttempt['had_done'] && !$secondAttempt['had_error'] && $secondAttempt['curl_errno'] === 0),
                            'had_output' => $secondAttempt['had_output'],
                            'had_done' => $secondAttempt['had_done'],
                            'had_error' => $secondAttempt['had_error'],
                            'curl_errno' => $secondAttempt['curl_errno'],
                        ];

                        if (!$secondAttempt['had_output'] || !$secondAttempt['had_done']) {
                            $safeErrorMessage = 'Sorry, I encountered an error. Please try again.';
                            echo "data: " . json_encode(['content' => $safeErrorMessage, 'done' => true]) . "\n\n";
                            $this->streamFlush();
                            $fullResponse = $safeErrorMessage;
                            $streamBackendUsed = 'error_fallback_message';
                        } else {
                            $streamBackendUsed = 'local_fallback_llama3.2:3b';
                        }
                    }

                    $responseElapsedMs = round((microtime(true) - $responseStartTime) * 1000, 2);
                    Log::info('LLM response generation completed', ['elapsed_ms' => $responseElapsedMs, 'response_length' => strlen($fullResponse)]);
                }
                }

                if (!$streamBackendUsed) {
                    $streamBackendUsed = $useOpenAiFallback ? 'openai' : 'unknown';
                }

                Log::info('Widget stream backend diagnostics', [
                    'org_id' => $organization->id,
                    'session_id' => $sessionId,
                    'backend_used' => $streamBackendUsed,
                    'fallback_used' => collect($streamBackendAttempts)->contains(function ($attempt) {
                        return ($attempt['attempt'] ?? '') === 'local-fallback-3b';
                    }),
                    'attempts' => $streamBackendAttempts,
                ]);

                $finalResponse = trim($fullResponse);
                $hallucinationBlocked = false;
                [$finalResponse, $hallucinationBlocked] = $this->enforceContextOnlyAnswer(
                    $message,
                    $context ?? '',
                    $finalResponse,
                    $organization
                );
                $escalationReason = $this->getEscalationReason($message, $finalResponse, $intentResult);
                $postfixParts = [];

                $isWithinHours = $this->isWithinBusinessHours($organization);
                if ($hallucinationBlocked) {
                    $postfixParts = [];
                } elseif ($escalationReason === 'user_requested_human' && $isWithinHours === false) {
                    $handoffMessage = $this->buildHandoffMessage($organization);
                    if ($handoffMessage !== '') {
                        $postfixParts[] = $handoffMessage;
                    }
                }

                $responseHasQuestion = $this->responseHasQuestion($finalResponse);
                $isConversationEnding = $this->shouldTreatAsConversationEnding(
                    $message,
                    $lastAssistantAskedQuestion,
                    is_array($pendingFollowUpState) && !empty($pendingFollowUpState)
                );

                if (!$hallucinationBlocked && !$responseHasQuestion && !$isConversationEnding) {
                    $intentFollowUp = $this->buildFollowUpPrompt($intentResult);
                    if ($intentFollowUp !== '') {
                        $postfixParts[] = $intentFollowUp;
                    } else {
                        $faqFollowUp = $this->faqFollowUpService->getFollowUpText(
                            $organization,
                            $finalResponse,
                            null,
                            $this->buildFaqFollowUpContext($allUserInfo, compact('country', 'region', 'location', 'city'))
                        );
                        if ($faqFollowUp !== '') {
                            $postfixParts[] = $faqFollowUp;
                        } else {
                            $suggestion = $this->buildProactiveSuggestion($intentResult);
                            if ($suggestion !== '') {
                                $postfixParts[] = $suggestion;
                            }
                        }
                    }
                }

                if (!empty($postfixParts)) {
                    $suffix = "\n\n" . implode("\n\n", $postfixParts);
                    echo "data: " . json_encode(['content' => $suffix, 'done' => true]) . "\n\n";
                    $this->streamFlush();
                    $finalResponse = trim($finalResponse) . $suffix;
                }

                if ($finalResponse !== '') {
                    Log::info('Widget AI stream response', [
                        'org_id' => $organization->id,
                        'session_id' => $sessionId,
                        'response_length' => strlen($finalResponse),
                        'context_length' => strlen($context),
                        'used_live_data' => $liveData ? true : false,
                        'response_preview' => mb_substr($finalResponse, 0, 800),
                        'context_preview' => mb_substr($context, 0, 800),
                        'app_timezone' => config('app.timezone', 'UTC'),
                        'logged_at_local' => now()->toIso8601String(),
                        'logged_at_utc' => now('UTC')->toIso8601String(),
                    ]);

                    $this->aiAgentService->logWidgetTokenUsage(
                        $organization->id,
                        $chatMessages,
                        $finalResponse,
                        'llm_chat_stream'
                    );

                    $conversation = $this->saveConversationToDatabase(
                        $organization,
                        $sessionId,
                        $message,
                        $finalResponse,
                        $allUserInfo,
                        compact('country', 'region', 'location', 'city'),
                        $intentResult
                    );

                    $this->logIntentAnalytics(
                        $organization->id,
                        $sessionId,
                        $intentResult,
                        $request,
                        compact('country', 'region', 'location', 'city'),
                        $sessionMetadata
                    );

                    if ($this->isUnansweredResponse($finalResponse)) {
                        $this->logUnansweredQuestion(
                            $organization->id,
                            $sessionId,
                            $message,
                            $finalResponse,
                            $request,
                            compact('country', 'region', 'location', 'city'),
                            $sessionMetadata
                        );
                    }

                    if ($conversation) {
                        $this->handleEscalationIfNeeded(
                            $conversation,
                            $message,
                            $finalResponse,
                            $intentResult,
                            $request,
                            $sessionMetadata,
                            $escalationReason
                        );
                    }
                }
                
            } catch (\Exception $e) {
                echo "data: " . json_encode(['error' => $e->getMessage(), 'done' => true]) . "\n\n";
                $this->streamFlush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'X-Robots-Tag' => 'noindex, nofollow'
        ]);
    }

    private function initStreamOutput(): void
    {
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @ob_implicit_flush(true);
    }

    private function streamFlush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
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

    private function buildFaqFollowUpContext(array $allUserInfo, array $locationInfo = []): array
    {
        $context = [];

        $locationValue = $locationInfo['location']
            ?? ($allUserInfo['location'] ?? null)
            ?? ($allUserInfo['custom_fields']['location'] ?? null)
            ?? null;

        if (is_string($locationValue) && trim($locationValue) !== '') {
            $context['location'] = trim($locationValue);
        }

        $regionValue = $locationInfo['region'] ?? ($allUserInfo['region'] ?? null);
        if (is_string($regionValue) && trim($regionValue) !== '') {
            $context['region'] = trim($regionValue);
        }

        $countryValue = $locationInfo['country'] ?? ($allUserInfo['country'] ?? null);
        if (is_string($countryValue) && trim($countryValue) !== '') {
            $context['country'] = trim($countryValue);
        }

        if (!empty($allUserInfo['custom_fields']) && is_array($allUserInfo['custom_fields'])) {
            $context['custom_fields'] = $allUserInfo['custom_fields'];
        }

        return $context;
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

        if (!empty($allUserInfo['custom_fields']) && is_array($allUserInfo['custom_fields'])) {
            $metadata['custom_contact_fields'] = $allUserInfo['custom_fields'];
        }

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

            $userAgent = Str::limit((string) $request->userAgent(), 255, '');

            Analytics::create([
                'organization_id' => $organizationId,
                'visitor_id' => $sessionId,
                'session_id' => $sessionId,
                'event_type' => 'intent_detected',
                'page_url' => $pageUrl ?: config('app.url'),
                'page_title' => $pageTitle ?: '',
                'referrer' => $referrer ?: '',
                'user_agent' => $userAgent,
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

    private function isUnansweredResponse(string $text): bool
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return false;
        }

        $patterns = [
            "i don't know",
            "i do not know",
            "not sure",
            "sorry, i don't",
            "sorry, i do not",
            "don't have that information",
            "do not have that information",
            "not available",
            "unable to",
            "can't find",
            "cannot find"
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($t, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function logUnansweredQuestion(int $organizationId, string $sessionId, string $question, string $response, Request $request, array $locationInfo = [], ?array $sessionMetadata = null): void
    {
        try {
            $pageUrl = $sessionMetadata['page_url'] ?? $request->input('page_url');
            $pageTitle = $sessionMetadata['page_title'] ?? $request->input('page_title');
            $referrer = $sessionMetadata['referrer'] ?? $request->input('referrer') ?? $request->headers->get('referer');

            $userAgent = Str::limit((string) $request->userAgent(), 255, '');

            Analytics::create([
                'organization_id' => $organizationId,
                'visitor_id' => $sessionId,
                'session_id' => $sessionId,
                'event_type' => 'unanswered_question',
                'page_url' => $pageUrl ?: config('app.url'),
                'page_title' => $pageTitle ?: '',
                'referrer' => $referrer ?: '',
                'user_agent' => $userAgent,
                'ip_address' => $request->ip(),
                'country' => $locationInfo['country'] ?? null,
                'region' => $locationInfo['region'] ?? null,
                'city' => $locationInfo['location'] ?? null,
                'event_data' => [
                    'message' => $question,
                    'response' => $response,
                ],
            ]);
        } catch (\Throwable $t) {
            Log::warning('Unanswered question log failed', [
                'org_id' => $organizationId,
                'error' => $t->getMessage()
            ]);
        }
    }

    private function handleEscalationIfNeeded(ChatConversation $conversation, string $userMessage, string $responseText, ?array $intentResult, Request $request, ?array $sessionMetadata = null, ?string $precomputedReason = null): void
    {
        $alreadyEscalated = ($conversation->status === 'needs_handoff' || $conversation->agent_status === 'escalation_requested');
        $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
        $existingEscalation = $meta['escalation'] ?? [];
        $reason = $precomputedReason
            ?: ($existingEscalation['reason'] ?? $this->getEscalationReason($userMessage, $responseText, $intentResult));
        if (!$reason) {
            return;
        }

        $meta['escalation'] = [
            'reason' => $reason,
            'intent' => $intentResult['intent'] ?? ($existingEscalation['intent'] ?? null),
            'confidence' => $intentResult['confidence'] ?? ($existingEscalation['confidence'] ?? null),
            'triggered_at' => $existingEscalation['triggered_at'] ?? now()->toISOString(),
        ];

        if (!$alreadyEscalated) {
            $conversation->update([
                'status' => 'needs_handoff',
                'agent_status' => 'escalation_requested',
                'escalated_at' => now(),
                'metadata' => $meta,
                'last_activity_at' => now()
            ]);
        } else {
            $conversation->update([
                'metadata' => $meta,
                'last_activity_at' => now()
            ]);
        }

        $emailSentAt = $meta['escalation_email_last_sent_at'] ?? null;
        if (!$emailSentAt) {
            Log::info('Escalation email sending', [
                'conversation_id' => $conversation->conversation_id,
                'org_id' => $conversation->organization_id,
                'reason' => $reason,
            ]);
            $sent = $this->sendEscalationNotification($conversation, $reason);
            if ($sent) {
                $conversation->refresh();
                $latestMeta = is_array($conversation->metadata) ? $conversation->metadata : [];
                $meta = array_merge($latestMeta, $meta);
                $meta['escalation_email_last_sent_at'] = now()->toIso8601String();
                $conversation->update([
                    'metadata' => $meta,
                    'last_activity_at' => now()
                ]);
            } else {
                Log::warning('Escalation email not sent', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $conversation->organization_id,
                ]);
            }
        } else {
            Log::info('Escalation email skipped (already sent)', [
                'conversation_id' => $conversation->conversation_id,
                'org_id' => $conversation->organization_id,
                'last_sent_at' => $emailSentAt,
            ]);
        }

        if (!$alreadyEscalated && empty($conversation->summary)) {
            $summary = $this->buildConversationSummary($conversation);
            if ($summary !== '') {
                $conversation->update(['summary' => $summary]);
            }
        }

        if (!$alreadyEscalated) {
            try {
                $userAgent = Str::limit((string) $request->userAgent(), 255, '');

                Analytics::create([
                    'organization_id' => $conversation->organization_id,
                    'visitor_id' => $conversation->visitor_id ?? $conversation->conversation_id,
                    'session_id' => $conversation->conversation_id,
                    'event_type' => 'human_escalation',
                    'page_url' => $sessionMetadata['page_url'] ?? config('app.url'),
                    'page_title' => $sessionMetadata['page_title'] ?? '',
                    'referrer' => $sessionMetadata['referrer'] ?? '',
                    'user_agent' => $userAgent,
                    'ip_address' => $request->ip(),
                    'country' => $conversation->visitor_country,
                    'region' => $conversation->visitor_region,
                    'city' => $conversation->visitor_location,
                    'event_data' => [
                        'reason' => $reason,
                        'intent' => $intentResult['intent'] ?? null,
                        'confidence' => $intentResult['confidence'] ?? null,
                    ],
                ]);
            } catch (\Throwable $t) {
                Log::warning('Escalation analytics log failed', [
                    'org_id' => $conversation->organization_id,
                    'error' => $t->getMessage()
                ]);
            }
        }
    }

    private function sendEscalationNotification(ChatConversation $conversation, string $reason): bool
    {
        try {
            $organization = $conversation->organization ?? Organization::find($conversation->organization_id);
            if (!$organization) {
                return false;
            }

            $settings = $organization->settings ?? [];
            $enabled = (bool) ($settings['escalation_notify_enabled'] ?? false);
            if (!$enabled) {
                return false;
            }

            $emails = $settings['escalation_notify_emails'] ?? [];
            if (is_string($emails)) {
                $emails = array_filter(array_map('trim', preg_split('/[\s,]+/', $emails)));
            }
            $emails = array_values(array_filter(array_map('trim', (array) $emails)));
            if (empty($emails)) {
                return false;
            }

            $summary = $conversation->summary ?: $this->buildConversationSummary($conversation);
            $consoleUrl = rtrim(config('app.url'), '/') . '/customer/live-chats';
            $mailgunDomain = config('services.mailgun.domain');
            if (!$mailgunDomain) {
                $fromAddress = config('mail.from.address');
                if (is_string($fromAddress) && str_contains($fromAddress, '@')) {
                    $mailgunDomain = substr(strrchr($fromAddress, '@'), 1) ?: null;
                }
            }
            $replyTo = $mailgunDomain ? ('ai-chat-support+' . $conversation->conversation_id . '@' . $mailgunDomain) : null;

            if (!$replyTo) {
                Log::warning('Escalation email reply-to missing mailgun domain', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $organization->id,
                ]);
            }

            $magicLinkData = $this->buildEscalationMagicLink($conversation);
            $magicLinkUrl = $magicLinkData['url'] ?? null;
            $magicLinkTtl = $magicLinkData['ttl_minutes'] ?? null;

            $payload = [
                'organization' => $organization,
                'conversation' => $conversation,
                'reason' => $reason,
                'summary' => $summary,
                'console_url' => $consoleUrl,
                'reply_to' => $replyTo,
                'magic_link' => $magicLinkUrl,
                'magic_link_ttl_minutes' => $magicLinkTtl,
            ];

            Mail::to($emails)->send(new ChatEscalationNotification($payload));
            return true;
        } catch (\Throwable $e) {
            Log::warning('Escalation email notification failed', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    private function buildEscalationMagicLink(ChatConversation $conversation): ?array
    {
        try {
            $ttlMinutes = 30;
            $token = Str::random(40);
            $expiresAt = now()->addMinutes($ttlMinutes);

            $meta = $conversation->metadata ?? [];
            $meta['escalation_magic'] = [
                'token_hash' => hash('sha256', $token),
                'created_at' => now()->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'last_used_at' => $meta['escalation_magic']['last_used_at'] ?? null,
            ];
            $conversation->metadata = $meta;
            $conversation->save();

            $url = URL::temporarySignedRoute('escalations.magic', $expiresAt, [
                'conversation' => $conversation->id,
                'token' => $token,
            ]);

            return [
                'url' => $url,
                'ttl_minutes' => $ttlMinutes,
            ];
        } catch (\Throwable $e) {
            Log::warning('Escalation magic link generation failed', [
                'conversation_id' => $conversation->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getEscalationReason(string $userMessage, string $responseText, ?array $intentResult): ?string
    {
        $message = mb_strtolower($userMessage);

        if (str_contains($message, 'human') || str_contains($message, 'agent') || str_contains($message, 'representative')) {
            return 'user_requested_human';
        }

        $complaintKeywords = [
            'complaint', 'refund', 'cancel', 'angry', 'frustrated', 'upset', 'bad service', 'unhappy',
            'scam', 'fraud', 'chargeback', 'lawsuit', 'legal', 'terrible', 'worst', 'disappointed'
        ];

        foreach ($complaintKeywords as $kw) {
            if (str_contains($message, $kw)) {
                return 'complaint_detected';
            }
        }

        if ($this->isUnansweredResponse($responseText)) {
            return 'unanswered';
        }

        $confidence = (float) ($intentResult['confidence'] ?? 1);
        if ($confidence > 0 && $confidence < 0.4) {
            return 'low_intent_confidence';
        }

        return null;
    }

    private function buildHandoffMessage(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $availability = $settings['agent_availability'] ?? 'auto';
        $offlineMessage = trim((string) ($settings['handoff_offline_message'] ?? ''));

        $email = trim((string) ($organization->contact_email ?? ''));
        $phone = trim((string) ($organization->contact_phone ?? ''));
        $website = $organization->website ?: config('app.url');

        $channels = [];
        if ($email !== '') {
            $channels[] = "Email: {$email}";
        }
        if ($phone !== '') {
            $channels[] = "Call/WhatsApp: {$phone}";
        }
        if ($website !== '') {
            $channels[] = "Website: {$website}";
        }

        $isWithinHours = $this->isWithinBusinessHours($organization);
        $hasOnlineAgent = $this->hasOnlineAgent($organization->id);
        $agentsOnline = true;

        if ($availability === 'offline') {
            $agentsOnline = false;
        } elseif ($availability === 'auto') {
            $agentsOnline = $isWithinHours;
        }

        if ($agentsOnline) {
            $base = $hasOnlineAgent
                ? 'A human agent is online and will join shortly.'
                : 'We are connecting you to a human agent now. Please stay online.';
            if (empty($channels)) {
                return $base;
            }
            return $base . ' You can also reach us via ' . implode(' | ', $channels) . '.';
        }

        $base = $offlineMessage !== ''
            ? rtrim($offlineMessage, '.') . '.'
            : 'Our agents are currently offline. Please leave your contact details or reach us via the options below.';

        if (empty($channels)) {
            return $base;
        }

        return $base . ' ' . implode(' | ', $channels) . '.';
    }

    private function hasOnlineAgent(int $organizationId): bool
    {
        $windowMinutes = 5;

        return ChatConversation::where('organization_id', $organizationId)
            ->whereIn('agent_status', ['agent_active', 'agent_assigned'])
            ->whereNotNull('agent_last_active_at')
            ->where('agent_last_active_at', '>=', now()->subMinutes($windowMinutes))
            ->exists();
    }

    private function buildConversationSummary(ChatConversation $conversation): string
    {
        try {
            $messages = $conversation->messages()
                ->orderBy('sent_at', 'desc')
                ->limit(6)
                ->get()
                ->reverse();

            if ($messages->isEmpty()) {
                return '';
            }

            $lines = [];
            foreach ($messages as $msg) {
                $sender = $msg->isFromUser() ? 'User' : ($msg->isFromAgent() ? 'Agent' : 'AI');
                $text = trim(strip_tags((string) $msg->message));
                if ($text === '') {
                    continue;
                }
                $lines[] = "{$sender}: {$text}";
            }

            $contact = $conversation->getContactInfo();
            $visitor = $conversation->getDisplayName();

            $header = "Conversation summary for {$visitor} ({$contact})";
            return $header . "\n" . implode("\n", $lines);
        } catch (\Throwable $t) {
            Log::warning('Conversation summary generation failed', [
                'conversation_id' => $conversation->id,
                'error' => $t->getMessage()
            ]);
        }

        return '';
    }

    private function isContactQuery(string $message): bool
    {
        $message = mb_strtolower($message);

        // Strong contact intent keywords
        if (preg_match('/\b(contact|reach|email|phone|call|whatsapp|address|location|customer care|helpline)\b/i', $message)) {
            return true;
        }

        // Avoid false positives like "AI Chat Support" by requiring context around support/help
        return (bool) preg_match('/\b(support|help)\b\s*(team|desk|email|phone|number|contact|line|center|centre)\b/i', $message)
            || (bool) preg_match('/\b(contact|reach|email|phone|call)\b\s*(support|help)\b/i', $message);
    }

    private function buildContactResponse(?string $email, ?string $phone, string $website): string
    {
        $parts = [];
        if ($email) {
            $parts[] = "Email: {$email}";
        }
        if ($phone) {
            $parts[] = "Phone: {$phone}";
        }
        $parts[] = "Website: {$website}";

        return 'You can reach us at ' . implode(' | ', $parts) . '.';
    }

    private function getExactFaqMatchResponse(?array $searchResults, Organization $organization, string $message = ''): ?array
    {
        if (!$searchResults || empty($searchResults['results']) || !is_array($searchResults['results'])) {
            return $this->getKeywordFaqMatchResponse($organization, $message);
        }

        $threshold = 0.82;
        $best = null;

        foreach ($searchResults['results'] as $result) {
            $payload = $result['payload'] ?? [];
            $dataType = strtolower((string) ($payload['data_type'] ?? ''));
            $itemId = strtolower((string) ($payload['item_id'] ?? ''));
            $type = strtolower((string) ($payload['type'] ?? ''));

            $isFaqPayload = $dataType === 'faq'
                || $type === 'faq'
                || str_starts_with($itemId, 'faq_');

            if (!$isFaqPayload) {
                continue;
            }

            $score = (float) ($result['score'] ?? 0);
            if ($score < $threshold) {
                continue;
            }

            if (!$best || $score > ($best['score'] ?? 0)) {
                $best = [
                    'score' => $score,
                    'payload' => $payload,
                ];
            }
        }

        if (!$best) {
            return $this->getKeywordFaqMatchResponse($organization, $message);
        }

        $payload = $best['payload'] ?? [];
        $content = $payload['content'] ?? ($payload['title'] ?? '');
        $response = $this->htmlToPlainWithLinks((string) $content);
        $response = trim($response);

        if ($response === '') {
            return null;
        }

        return [
            'response' => $response,
            'score' => $best['score'],
            'payload' => $payload,
            'match_source' => 'semantic',
        ];
    }

    private function getKeywordFaqMatchResponse(Organization $organization, string $message): ?array
    {
        $translationMap = $this->getOrganizationQueryTranslationMap($organization);
        $query = $this->normalizeKeywordMatchText($message, $translationMap);
        if ($query === '') {
            return null;
        }

        $queryTerms = $this->extractKeywordTerms($query);
        if (empty($queryTerms)) {
            return null;
        }

        $careerTerms = [
            'career', 'careers', 'job', 'jobs', 'vacancy', 'vacancies', 'opening', 'openings',
            'post', 'posts', 'recruitment', 'hiring', 'naukri', 'chakri', 'niyukti',
            'appointment', 'staff', 'teacher', 'teachers', 'hr', 'resume', 'cv', 'khali'
        ];
        $hasCareerIntent = $this->containsAnyKeywordTerm($queryTerms, $careerTerms);

        $faqs = OrganizationFaq::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id', 'question', 'answer', 'follow_up', 'keywords', 'category', 'updated_at']);

        $best = null;
        foreach ($faqs as $faq) {
            $questionNorm = $this->normalizeKeywordMatchText((string) $faq->question);
            $categoryNorm = $this->normalizeKeywordMatchText((string) ($faq->category ?? ''));
            $keywordsNorm = $this->normalizeKeywordMatchText((string) ($faq->keywords ?? ''));
            $faqTerms = $this->extractKeywordTerms(trim($questionNorm . ' ' . $keywordsNorm . ' ' . $categoryNorm));

            $score = 0.0;
            $strongKeywordHit = false;

            $keywordParts = preg_split('/[,|]/', (string) ($faq->keywords ?? '')) ?: [];
            foreach ($keywordParts as $keywordPart) {
                $keywordNorm = $this->normalizeKeywordMatchText((string) $keywordPart);
                if ($keywordNorm === '' || mb_strlen($keywordNorm) < 2) {
                    continue;
                }

                if (str_contains($query, $keywordNorm)) {
                    $score += 2.25;
                    $strongKeywordHit = true;
                    continue;
                }

                $keywordTerms = $this->extractKeywordTerms($keywordNorm);
                if (!empty($keywordTerms) && empty(array_diff($keywordTerms, $queryTerms))) {
                    $score += 1.5;
                }
            }

            $overlapCount = count(array_intersect($queryTerms, $faqTerms));
            if ($overlapCount > 0) {
                $score += min(1.8, $overlapCount * 0.42);
            }

            if ($questionNorm !== '' && str_contains($query, $questionNorm)) {
                $score += 0.9;
            }

            if ($hasCareerIntent && $this->containsAnyKeywordTerm($faqTerms, $careerTerms)) {
                $score += 1.05;
            }

            if ($hasCareerIntent && str_contains($categoryNorm, 'career')) {
                $score += 0.9;
            }

            if (!$strongKeywordHit && $score < 2.3) {
                continue;
            }

            if (
                $best === null
                || $score > $best['score']
                || ($score === $best['score'] && (string) $faq->updated_at > (string) ($best['updated_at'] ?? ''))
            ) {
                $answer = trim($this->htmlToPlainWithLinks((string) $faq->answer));
                if ($answer === '') {
                    continue;
                }

                $best = [
                    'score' => $score,
                    'updated_at' => (string) $faq->updated_at,
                    'response' => $answer,
                    'payload' => [
                        'item_id' => 'faq_' . $faq->id,
                        'data_type' => 'faq',
                        'type' => 'faq',
                        'title' => $faq->question,
                        'content' => $answer,
                        'follow_up' => $faq->follow_up,
                        'category' => $faq->category,
                        'keywords' => $faq->keywords,
                    ],
                ];
            }
        }

        if (!$best || ($best['score'] ?? 0) < 2.6) {
            return null;
        }

        unset($best['updated_at']);
        $best['match_source'] = 'keyword_fallback';
        return $best;
    }

    private function normalizeKeywordMatchText(string $value, array $extraReplacements = []): string
    {
        $normalized = strtolower(trim(strip_tags($value)));
        if ($normalized === '') {
            return '';
        }

        $phraseReplacements = [
            'post khali' => 'vacancy',
            'khali post' => 'vacancy',
            'job khali' => 'vacancy',
            'kensi' => 'which',
            'kon si' => 'which',
            'konsi' => 'which',
            'kaunsi' => 'which',
            'keun' => 'which',
            'keunsi' => 'which',
            'yaa' => 'or',
            'ya' => 'or',
            'kii' => 'is',
            'kii' => 'is',
            'achhe' => 'available',
            'achi' => 'available',
            'naukri' => 'job',
            'chakri' => 'job',
            'niyukti' => 'recruitment',
        ];

        if (!empty($extraReplacements)) {
            $phraseReplacements = array_merge($phraseReplacements, $extraReplacements);
        }

        foreach ($phraseReplacements as $from => $to) {
            $normalized = str_replace($from, $to, $normalized);
        }

        $normalized = preg_replace('/[^a-z0-9\s]+/i', ' ', $normalized) ?? $normalized;
        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function extractKeywordTerms(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        $stopWords = [
            'a', 'an', 'the', 'is', 'are', 'am', 'to', 'for', 'of', 'in', 'on', 'at',
            'this', 'that', 'it', 'we', 'you', 'i', 'me', 'my', 'our', 'your', 'or', 'and',
            'which', 'what', 'please', 'can', 'could', 'would', 'should', 'with', 'from'
        ];

        $terms = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }
            if (in_array($part, $stopWords, true)) {
                continue;
            }
            $terms[] = $part;
        }

        return array_values(array_unique($terms));
    }

    private function containsAnyKeywordTerm(array $sourceTerms, array $expectedTerms): bool
    {
        if (empty($sourceTerms) || empty($expectedTerms)) {
            return false;
        }

        return !empty(array_intersect($sourceTerms, $expectedTerms));
    }

    private function getOrganizationQueryTranslationMap(Organization $organization): array
    {
        $settings = $organization->settings ?? [];
        $configured = $settings['query_translation_map'] ?? [];

        $map = [];

        if (is_string($configured)) {
            $rows = preg_split('/\r\n|\r|\n/', $configured) ?: [];
            foreach ($rows as $row) {
                $line = trim((string) $row);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $parts = preg_split('/=>|=|\|/', $line, 2) ?: [];
                if (count($parts) < 2) {
                    continue;
                }

                $from = trim((string) ($parts[0] ?? ''));
                $to = trim((string) ($parts[1] ?? ''));
                if ($from === '' || $to === '') {
                    continue;
                }

                $from = strtolower(trim(preg_replace('/\s+/', ' ', $from) ?? $from));
                $to = strtolower(trim(preg_replace('/\s+/', ' ', $to) ?? $to));

                if ($from !== '' && $to !== '') {
                    $map[$from] = $to;
                }
            }

            return $map;
        }

        if (is_array($configured)) {
            foreach ($configured as $from => $to) {
                if (is_int($from) && is_string($to)) {
                    $parts = preg_split('/=>|=|\|/', $to, 2) ?: [];
                    if (count($parts) >= 2) {
                        $from = (string) ($parts[0] ?? '');
                        $to = (string) ($parts[1] ?? '');
                    } else {
                        continue;
                    }
                }

                $fromNorm = strtolower(trim((string) $from));
                $toNorm = strtolower(trim((string) $to));

                if ($fromNorm !== '' && $toNorm !== '') {
                    $map[$fromNorm] = $toNorm;
                }
            }
        }

        return $map;
    }

    private function getFunnelBranchFaqMatchResponse(Organization $organization, ?array $pendingFollowUpState, string $message): ?array
    {
        if (!is_array($pendingFollowUpState) || empty($pendingFollowUpState)) {
            return null;
        }

        $cleanMessage = trim(mb_strtolower(strip_tags($message)));
        if ($cleanMessage === '') {
            return null;
        }

        if (!$this->isShortFollowUp($cleanMessage) && !$this->isOneOrTwoWordReply($cleanMessage)) {
            return null;
        }

        $branchType = null;
        if ($this->isAffirmativeFollowUp($cleanMessage)) {
            $branchType = 'affirmative';
        } elseif ($this->isNegativeFollowUp($cleanMessage)) {
            $branchType = 'negative';
        }

        $affirmativeTriggers = ['yes', 'yeah', 'yup', 'yep', 'sure', 'okay', 'ok', 'definitely', 'go ahead', 'continue'];
        $negativeTriggers = ['no', 'nope', 'nah', 'not now', 'dont', "don't", 'do not', 'not really', 'no thanks', 'no thank you'];
        $triggers = $branchType === 'affirmative'
            ? $affirmativeTriggers
            : ($branchType === 'negative' ? $negativeTriggers : []);

        $neutralTriggerTerms = [];
        if ($branchType === null) {
            if (str_word_count($cleanMessage) > 4) {
                return null;
            }

            $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $cleanMessage);
            $neutralTriggerTerms = array_values(array_filter(array_unique(array_map(
                static fn ($part) => trim((string) $part),
                preg_split('/\s+/', (string) $normalized) ?: []
            )), static fn ($part) => $part !== '' && mb_strlen($part) >= 2));

            if (empty($neutralTriggerTerms)) {
                return null;
            }
        }

        $candidateFaqs = OrganizationFaq::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id', 'question', 'answer', 'follow_up', 'keywords', 'category', 'updated_at']);

        $best = null;
        foreach ($candidateFaqs as $faq) {
            $questionNorm = trim(mb_strtolower((string) $faq->question));
            $keywordsNorm = trim(mb_strtolower((string) ($faq->keywords ?? '')));
            $categoryNorm = trim(mb_strtolower((string) ($faq->category ?? '')));

            $isFunnelCategory = str_starts_with($categoryNorm, 'funnel');
            $score = 0.0;

            if ($branchType !== null) {
                if (in_array($questionNorm, $triggers, true)) {
                    $score += 1.35;
                }

                foreach ($triggers as $trigger) {
                    if ($keywordsNorm !== '' && str_contains($keywordsNorm, $trigger)) {
                        $score += 0.9;
                        break;
                    }
                }

                if ($branchType === 'affirmative' && preg_match('/\b(yes|yeah|yup|yep|sure|okay|ok|definitely)\b/i', $questionNorm)) {
                    $score += 0.8;
                }

                if ($branchType === 'negative' && preg_match('/\b(no|nope|nah|not now|not really|no thanks|no thank you)\b/i', $questionNorm)) {
                    $score += 0.8;
                }
            } else {
                if (!$isFunnelCategory) {
                    continue;
                }

                foreach ($neutralTriggerTerms as $term) {
                    if ($questionNorm === $term) {
                        $score += 1.15;
                    }

                    if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $questionNorm)) {
                        $score += 0.55;
                    }

                    if ($keywordsNorm !== '' && preg_match('/\b' . preg_quote($term, '/') . '\b/i', $keywordsNorm)) {
                        $score += 0.95;
                    }
                }
            }

            if ($isFunnelCategory) {
                $score += 0.35;
            }

            if (trim((string) ($faq->follow_up ?? '')) !== '') {
                $score += 0.1;
            }

            if ($score <= 0) {
                continue;
            }

            if (
                $best === null
                || $score > $best['score']
                || ($score === $best['score'] && (string) $faq->updated_at > (string) ($best['updated_at'] ?? ''))
            ) {
                $answer = trim($this->htmlToPlainWithLinks((string) $faq->answer));
                if ($answer === '') {
                    continue;
                }

                $best = [
                    'score' => $score,
                    'updated_at' => (string) $faq->updated_at,
                    'response' => $answer,
                    'payload' => [
                        'item_id' => 'faq_' . $faq->id,
                        'data_type' => 'faq',
                        'type' => 'faq',
                        'title' => $faq->question,
                        'content' => $answer,
                        'follow_up' => $faq->follow_up,
                        'category' => $faq->category,
                        'keywords' => $faq->keywords,
                    ],
                ];
            }
        }

        $minimumScore = $branchType === null ? 0.8 : 0.9;
        if (!$best || ($best['score'] ?? 0) < $minimumScore) {
            return null;
        }

        unset($best['updated_at']);
        return $best;
    }

    private function buildChatMessages(Organization $organization, string $sessionId, string $systemPrompt, string $message, string $context = ''): array
    {
        $hasContext = trim($context) !== '';
        $includeHistory = false;
        $historyLimit = 0;
        $isShortFollowUp = $this->isShortFollowUp($message);
        $messageHasUrl = $this->containsUrl($message);
        $referencesSharedLink = $this->referencesPreviouslySharedLink($message);

        $lastAssistant = $this->getLastAssistantMessage($organization, $sessionId);
        $lastAssistantAskedQuestion = $lastAssistant !== null && $this->responseHasQuestion($lastAssistant);
        $isLikelyShortReply = $isShortFollowUp || ($this->isOneOrTwoWordReply($message) && $lastAssistantAskedQuestion);

        if ($isLikelyShortReply && $lastAssistantAskedQuestion) {
            $includeHistory = true;
            $historyLimit = 4;
        }

        if (!$hasContext) {
            $includeHistory = true;
            $historyLimit = max($historyLimit, 4);
        }

        if ($this->isAffirmativeFollowUp($message) && $this->isPreviousUserAffirmative($organization, $sessionId)) {
            $includeHistory = true;
            $historyLimit = max($historyLimit, 10);
        }

        if ($messageHasUrl || $referencesSharedLink) {
            $includeHistory = true;
            $historyLimit = max($historyLimit, 8);
        }

        if ($includeHistory) {
            $recentMessages = $this->getRecentConversationMessages($organization, $sessionId, $message, $historyLimit);
            if (!empty($recentMessages)) {
                $systemPrompt .= "\n\nPRIOR HISTORY (use only if the user explicitly refers to it):\n";
                foreach ($recentMessages as $rm) {
                    $label = $rm['role'] === 'user' ? 'User' : 'Assistant';
                    $systemPrompt .= $label . ": " . $rm['content'] . "\n";
                }

                $sharedLinks = $this->extractRecentSharedLinks($recentMessages);
                if (!empty($sharedLinks)) {
                    $systemPrompt .= "\nRECENT USER-SHARED LINKS:\n";
                    foreach ($sharedLinks as $link) {
                        $systemPrompt .= "- {$link}\n";
                    }
                    if ($referencesSharedLink) {
                        $systemPrompt .= "If the user refers to 'the link' or 'shared link', use the RECENT USER-SHARED LINKS above and do not claim that no link was provided.\n";
                    }
                }
            }
        }

        if ($isLikelyShortReply && $lastAssistantAskedQuestion) {
            $systemPrompt .= "\nFOLLOW-UP MODE: The user's latest message is likely a short answer to the assistant's previous question. Interpret it using the immediately preceding question and context.\n";
        }

        if ($this->isAffirmativeFollowUp($message) && $lastAssistantAskedQuestion) {
            $systemPrompt .= "\nAFFIRMATIVE CONTINUATION: The user accepted your previous follow-up question. Continue with one relevant detail from the option(s) you just offered, grounded in CURRENT CONTEXT. Do not reset the conversation or ask 'what would you like help with?'.\n";
        }

        $systemPrompt .= "\nCURRENT QUERY:\n" . $message . "\n";

        return [
            ['role' => 'system', 'content' => trim($systemPrompt)],
            ['role' => 'user', 'content' => $message],
        ];
    }

    private function getRecentConversationMessages(Organization $organization, string $sessionId, string $message, int $limit = 4): array
    {
        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return [];
        }

        $recent = $conversation->messages()
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $messages = [];
        foreach ($recent as $msg) {
            $text = trim(strip_tags((string) $msg->message));
            if ($text === '') {
                continue;
            }

            $role = $msg->sender_type === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $text];
        }

        return $messages;
    }

    private function containsUrl(string $text): bool
    {
        return (bool) preg_match('/https?:\/\/[^\s]+/i', $text);
    }

    private function referencesPreviouslySharedLink(string $text): bool
    {
        $normalized = strtolower(trim($text));
        if ($normalized === '') {
            return false;
        }

        if ($this->containsUrl($normalized)) {
            return false;
        }

        $hasLinkWord = (bool) preg_match('/\b(link|url)\b/i', $normalized);
        if (!$hasLinkWord) {
            return false;
        }

        $hasReferenceCue = (bool) preg_match('/\b(shared|sent|provided|given|posted|above|earlier|before|previous|same|that)\b/i', $normalized)
            || (bool) preg_match('/\b(i have|i\'ve|we have|we\'ve)\s+(shared|sent|provided|given)\b/i', $normalized);

        return $hasReferenceCue;
    }

    private function extractUrlsFromText(string $text): array
    {
        if (!preg_match_all('/https?:\/\/[^\s]+/i', $text, $matches)) {
            return [];
        }

        $links = [];
        foreach (($matches[0] ?? []) as $url) {
            $cleanUrl = rtrim((string) $url, ".,;:!?)]}");
            if ($cleanUrl !== '') {
                $links[] = $cleanUrl;
            }
        }

        return array_values(array_unique($links));
    }

    private function extractRecentSharedLinks(array $recentMessages): array
    {
        $links = [];
        foreach ($recentMessages as $message) {
            if (!is_array($message) || ($message['role'] ?? null) !== 'user') {
                continue;
            }

            $content = (string) ($message['content'] ?? '');
            if ($content === '') {
                continue;
            }

            $links = array_merge($links, $this->extractUrlsFromText($content));
        }

        return array_values(array_unique($links));
    }

    private function isPreviousUserAffirmative(Organization $organization, string $sessionId): bool
    {
        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return false;
        }

        $lastUserMessage = $conversation->messages()
            ->where('sender_type', 'user')
            ->orderBy('sent_at', 'desc')
            ->first();

        if (!$lastUserMessage) {
            return false;
        }

        $text = trim(strip_tags((string) $lastUserMessage->message));
        if ($text === '') {
            return false;
        }

        return $this->isAffirmativeFollowUp($text);
    }

    private function isShortFollowUp(string $message): bool
    {
        $clean = trim(mb_strtolower($message));
        if ($clean === '') {
            return false;
        }

        if ($this->isAffirmativeFollowUp($message)) {
            return true;
        }

        $negatives = [
            'no', 'nope', 'nah', 'not now', 'dont', 'don\'t', 'do not', 'not really', 'no thanks', 'no thank you',
        ];

        if (in_array($clean, $negatives, true)) {
            return true;
        }

        if (str_word_count($clean) <= 3) {
            if (preg_match('/\b(it|that|those|these|this|they|them|there|here|above|previous|earlier|more|details|explain|expand|continue)\b/', $clean)) {
                return true;
            }
            if (preg_match('/^(and|also|what about|how about)\b/', $clean)) {
                return true;
            }
            return false;
        }

        return false;
    }

    private function isOneOrTwoWordReply(string $message): bool
    {
        $clean = trim(mb_strtolower(strip_tags($message)));
        if ($clean === '') {
            return false;
        }

        $wordCount = str_word_count($clean);
        if ($wordCount === 0) {
            return false;
        }

        return $wordCount <= 2;
    }

    private function shouldUseOpenAiFallback(string $message, Organization $organization, string $responseLanguage): bool
    {
        return false;
    }

    private function shouldUseLowThinkingMode(string $message, ?array $intentResult, bool $isAffirmativeContinuation, string $context): bool
    {
        $text = trim(strtolower($message));
        $wordCount = str_word_count($text);
        $hasContext = trim($context) !== '';

        if ($text === '') {
            return false;
        }

        if ($isAffirmativeContinuation) {
            return true;
        }

        if (preg_match('/^(yes|no|ok|okay|sure|haan|ha|hmm|continue|go ahead|proceed)\b/i', $text)) {
            return true;
        }

        $complexSignals = preg_match('/\b(why|how|analyze|analysis|compare|detailed|detail|strategy|architect|design|step by step|explain)\b/i', $text);
        if ($complexSignals) {
            return false;
        }

        $intent = strtolower((string) ($intentResult['intent'] ?? ''));
        $simpleIntents = ['pricing', 'booking', 'lookup', 'realtime_data', 'static_info', 'follow_up'];
        $contextGrounded = str_contains($context, '[LIVE DATA') || str_contains($context, 'CURRENT CONTEXT');

        if ($hasContext && $contextGrounded && $wordCount <= 24 && in_array($intent, $simpleIntents, true)) {
            return true;
        }

        return $hasContext && $wordCount <= 8;
    }

    private function buildPricingContext(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $pricingEnabled = (bool) ($settings['pricing_context_enabled'] ?? false);
        $allowedSlugs = ['ai-chat-support', 'platform'];
        $allowPlatformPricing = $pricingEnabled || in_array($organization->slug, $allowedSlugs, true);

        $servicePricing = $this->buildServicePricingContext($organization);
        if ($servicePricing !== '') {
            return $servicePricing;
        }

        if (!$allowPlatformPricing) {
            return '';
        }

        $subscriptionPlans = PricingPlan::active()
            ->subscriptions()
            ->orderBy('sort_order')
            ->orderBy('billing_period')
            ->get();
        $creditPackages = PricingPlan::active()
            ->credits()
            ->orderBy('sort_order')
            ->get();

        if ($subscriptionPlans->isEmpty() && $creditPackages->isEmpty()) {
            return '';
        }

        $locationService = app(LocationService::class);
        $currency = $locationService->getUserCurrency();
        $tokensPerConversation = (int) ($organization->settings['pricing_tokens_per_conversation'] ?? 500);
        if ($tokensPerConversation <= 0) {
            $tokensPerConversation = 500;
        }

        $lines = [];
        $lines[] = "Pricing overview (conversation estimates assume ~{$tokensPerConversation} tokens per conversation):";

        if ($subscriptionPlans->isNotEmpty()) {
            $lines[] = 'Subscription plans:';
            $grouped = [];
            foreach ($subscriptionPlans as $plan) {
                $key = $plan->metadata['original_slug'] ?? $plan->slug ?? $plan->name;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'name' => $plan->name,
                        'token_cap' => $plan->token_cap,
                        'overage' => $plan->overage_price_per_100k,
                        'periods' => [],
                    ];
                }
                $grouped[$key]['periods'][$plan->billing_period] = $plan;
            }

            foreach ($grouped as $group) {
                $tokens = number_format((int) ($group['token_cap'] ?? 0));
                $estimate = $this->formatConversationEstimate((int) ($group['token_cap'] ?? 0), $tokensPerConversation);

                $monthlyPlan = $group['periods']['monthly'] ?? null;
                $yearlyPlan = $group['periods']['yearly'] ?? null;

                $line = "- {$group['name']}:";
                if ($monthlyPlan) {
                    $amount = (float) $monthlyPlan->price;
                    if ($currency === 'INR') {
                        $amount = $locationService->convertToINR($amount);
                    }
                    $line .= ' ' . $locationService->formatPrice($amount, $currency) . '/mo';
                }
                if ($yearlyPlan) {
                    $amount = (float) $yearlyPlan->price;
                    if ($currency === 'INR') {
                        $amount = $locationService->convertToINR($amount);
                    }
                    $line .= ' (' . $locationService->formatPrice($amount, $currency) . '/yr)';
                }
                if ((int) ($group['token_cap'] ?? 0) > 0) {
                    $line .= ", {$tokens} tokens/mo (~{$estimate} conversations)";
                }
                if ((float) ($group['overage'] ?? 0) > 0) {
                    $overageAmount = (float) $group['overage'];
                    if ($currency === 'INR') {
                        $overageAmount = $locationService->convertToINR($overageAmount);
                    }
                    $overage = $locationService->formatPrice($overageAmount, $currency) . ' per 100k tokens';
                    $line .= ", overage {$overage}";
                }
                $lines[] = $line . '.';
            }
        }

        if ($creditPackages->isNotEmpty()) {
            $lines[] = 'Credit packages (one-time):';
            foreach ($creditPackages as $package) {
                $priceAmount = $package->getPriceForCurrency($currency);
                if ($priceAmount === null) {
                    $priceAmount = (float) $package->price;
                    if ($currency === 'INR') {
                        $priceAmount = $locationService->convertToINR($priceAmount);
                    }
                }
                $price = $locationService->formatPrice($priceAmount, $currency);
                $tokens = number_format((int) $package->credits);
                $estimate = $this->formatConversationEstimate((int) $package->credits, $tokensPerConversation);
                $lines[] = "- {$package->name}: {$price}, {$tokens} tokens (~{$estimate} conversations).";
            }
        }

        $lines[] = 'Note: Conversation estimates are rough; actual usage varies by message length.';

        return implode("\n", $lines);
    }

    private function buildServicePricingContext(Organization $organization): string
    {
        $services = OrganizationData::where('organization_id', $organization->id)
            ->where('type', 'service')
            ->orderByDesc('id')
            ->get();

        if ($services->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($services as $service) {
            $meta = is_array($service->metadata) ? $service->metadata : [];
            $price = $meta['price'] ?? null;
            $currency = $meta['currency'] ?? null;
            if ($price === null || $price === '') {
                continue;
            }

            $priceText = trim((string) $price);
            if ($currency) {
                $priceText = trim((string) $currency) . ' ' . $priceText;
            }

            $name = $service->name ?: 'Service';
            $lines[] = "- {$name}: {$priceText}";
        }

        if (empty($lines)) {
            return '';
        }

        array_unshift($lines, 'Service pricing:');
        return implode("\n", $lines);
    }

    private function shouldUsePricingFallback(string $context, ?string $shopifyContext, string $query): bool
    {
        $query = strtolower(trim($query));
        if (!preg_match('/\b(price|pricing|cost|fee|fees|charge|rate|how much|tuition|payment|bill|invoice|expense|\$|₹|€|£)\b/i', $query)) {
            return false;
        }

        $combined = trim($context . "\n" . ($shopifyContext ?? ''));
        if ($combined === '') {
            return true;
        }

        return !preg_match('/\b(price|pricing|cost|plan|package|\$|₹|€|£)\b/i', $combined);
    }

    private function buildPricingUnavailableResponse(Organization $organization): string
    {
        $orgWebsite = $organization->website ?: config('app.url');
        $orgEmail = $organization->contact_email ?? null;
        $orgPhone = $organization->contact_phone ?? null;
        $contact = $this->buildContactResponse($orgEmail, $orgPhone, $orgWebsite);

        return "We don’t have pricing details available in our knowledge base yet. " . $contact;
    }

    private function formatConversationEstimate(int $tokens, int $tokensPerConversation): string
    {
        if ($tokens <= 0) {
            return '0';
        }
        if ($tokensPerConversation <= 0) {
            return 'N/A';
        }

        $estimate = (int) floor($tokens / $tokensPerConversation);

        return number_format(max(1, $estimate));
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

        [$hoursDisplay, $timezoneOverride] = $this->extractTimezoneFromBusinessHours($hours);
        $timezone = $timezoneOverride ?: ($organization->timezone ?: config('app.timezone', 'UTC'));
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
        if ($hoursDisplay !== '') {
            $lines[] = "- Business hours: {$hoursDisplay}";
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

    private function isWithinBusinessHours(Organization $organization): ?bool
    {
        $settings = $organization->settings ?? [];
        $rawHours = trim((string) ($settings['business_hours'] ?? ''));
        if ($rawHours === '') {
            return null;
        }

        [$hoursDisplay, $timezoneOverride] = $this->extractTimezoneFromBusinessHours($rawHours);
        $hoursDisplay = trim($hoursDisplay);
        if ($hoursDisplay === '') {
            return null;
        }

        $windows = $this->parseBusinessHoursWindows($hoursDisplay);
        if (empty($windows)) {
            return null;
        }

        $timezone = $timezoneOverride ?: ($organization->timezone ?: config('app.timezone', 'UTC'));
        $now = now()->timezone($timezone);
        $day = $now->dayOfWeek;
        $minutes = ($now->hour * 60) + $now->minute;

        foreach ($windows as $window) {
            if (!in_array($day, $window['days'], true)) {
                continue;
            }

            $start = $window['start'];
            $end = $window['end'];

            if ($start <= $end) {
                if ($minutes >= $start && $minutes <= $end) {
                    return true;
                }
            } else {
                if ($minutes >= $start || $minutes <= $end) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseBusinessHoursWindows(string $hours): array
    {
        $lines = preg_split('/\r?\n|;/', $hours);
        $windows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (!preg_match('/(\d{1,2}(?:[:\.]\d{2})?\s*(?:am|pm)?)[\s\-to]+(\d{1,2}(?:[:\.]\d{2})?\s*(?:am|pm)?)/i', $line, $match)) {
                continue;
            }

            $start = $this->parseTimeToMinutes($match[1]);
            $end = $this->parseTimeToMinutes($match[2]);
            if ($start === null || $end === null) {
                continue;
            }

            $days = $this->extractDaysFromLine($line);
            if (empty($days)) {
                $days = [0, 1, 2, 3, 4, 5, 6];
            }

            $windows[] = [
                'days' => $days,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $windows;
    }

    private function extractDaysFromLine(string $line): array
    {
        $line = strtolower($line);
        $days = [];

        $rangePattern = '/\b(sun(?:day)?|mon(?:day)?|tue(?:s|sday)?|wed(?:nesday)?|thu(?:rs|rsday|r|day)?|fri(?:day)?|sat(?:urday)?)\b\s*(?:-|to)\s*\b(sun(?:day)?|mon(?:day)?|tue(?:s|sday)?|wed(?:nesday)?|thu(?:rs|rsday|r|day)?|fri(?:day)?|sat(?:urday)?)\b/i';
        if (preg_match_all($rangePattern, $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $start = $this->mapDayToken($match[1]);
                $end = $this->mapDayToken($match[2]);
                if ($start === null || $end === null) {
                    continue;
                }

                if ($start <= $end) {
                    for ($i = $start; $i <= $end; $i++) {
                        $days[$i] = true;
                    }
                } else {
                    for ($i = $start; $i <= 6; $i++) {
                        $days[$i] = true;
                    }
                    for ($i = 0; $i <= $end; $i++) {
                        $days[$i] = true;
                    }
                }
            }
        }

        $tokenPattern = '/\b(sun(?:day)?|mon(?:day)?|tue(?:s|sday)?|wed(?:nesday)?|thu(?:rs|rsday|r|day)?|fri(?:day)?|sat(?:urday)?)\b/i';
        if (preg_match_all($tokenPattern, $line, $matches)) {
            foreach ($matches[1] as $token) {
                $day = $this->mapDayToken($token);
                if ($day !== null) {
                    $days[$day] = true;
                }
            }
        }

        return array_keys($days);
    }

    private function mapDayToken(string $token): ?int
    {
        $token = strtolower($token);
        if (str_starts_with($token, 'sun')) {
            return 0;
        }
        if (str_starts_with($token, 'mon')) {
            return 1;
        }
        if (str_starts_with($token, 'tue')) {
            return 2;
        }
        if (str_starts_with($token, 'wed')) {
            return 3;
        }
        if (str_starts_with($token, 'thu')) {
            return 4;
        }
        if (str_starts_with($token, 'fri')) {
            return 5;
        }
        if (str_starts_with($token, 'sat')) {
            return 6;
        }

        return null;
    }

    private function parseTimeToMinutes(string $time): ?int
    {
        $clean = strtolower(trim($time));
        $ampm = null;

        if (preg_match('/\d{1,2}\.\d{2}/', $clean)) {
            $clean = str_replace('.', ':', $clean);
        }

        if (preg_match('/(am|pm)$/', $clean, $match)) {
            $ampm = $match[1];
            $clean = trim(preg_replace('/(am|pm)$/', '', $clean));
        }

        if (!preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $clean, $match)) {
            return null;
        }

        $hour = (int) $match[1];
        $minute = isset($match[2]) ? (int) $match[2] : 0;

        if ($hour > 23) {
            return null;
        }
        if ($minute > 59) {
            $minute = 59;
        }

        if ($ampm) {
            if ($hour === 12) {
                $hour = $ampm === 'am' ? 0 : 12;
            } elseif ($ampm === 'pm') {
                $hour += 12;
            }
        }

        return ($hour * 60) + $minute;
    }

    private function buildAgentContext(int $organizationId, string $sessionId): string
    {
        if ($sessionId === '') {
            return '';
        }

        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$conversation) {
            return '';
        }

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'agent')
            ->orderByDesc('sent_at')
            ->limit(3)
            ->get();

        if ($messages->isEmpty()) {
            return '';
        }

        $lines = [];
        foreach ($messages as $msg) {
            $name = $msg->getSenderDisplayName();
            $text = trim(strip_tags((string) $msg->message));
            if ($text === '') {
                continue;
            }
            $lines[] = "- {$name}: {$text}";
        }

        return implode("\n", array_reverse($lines));
    }

    private function extractTimezoneFromBusinessHours(string $hours): array
    {
        $clean = trim($hours);
        if ($clean === '') {
            return ['', null];
        }

        $timezoneMap = [
            'IST' => 'Asia/Kolkata',
            'UTC' => 'UTC',
            'GMT' => 'UTC',
            'EST' => 'America/New_York',
            'EDT' => 'America/New_York',
            'CST' => 'America/Chicago',
            'CDT' => 'America/Chicago',
            'MST' => 'America/Denver',
            'MDT' => 'America/Denver',
            'PST' => 'America/Los_Angeles',
            'PDT' => 'America/Los_Angeles'
        ];

        if (preg_match('/\b(UTC|GMT)([+-]\d{1,2})(?::?(\d{2}))?\b/i', $clean, $m)) {
            $offsetHours = (int) $m[2];
            $offsetMinutes = isset($m[3]) ? (int) $m[3] : 0;
            $sign = $offsetHours < 0 ? '-' : '+';
            $offsetHours = abs($offsetHours);
            $tz = sprintf('UTC%s%02d:%02d', $sign, $offsetHours, $offsetMinutes);
            $display = trim(preg_replace('/\b(UTC|GMT)([+-]\d{1,2})(?::?(\d{2}))?\b/i', '', $clean));
            return [$display, $tz];
        }

        if (preg_match_all('/\b([A-Z]{2,4})\b/', $clean, $matches)) {
            $abbrs = $matches[1] ?? [];
            $abbrs = array_values(array_filter(array_map('strtoupper', $abbrs)));
            $abbrs = array_filter($abbrs, fn ($abbr) => !in_array($abbr, ['AM', 'PM'], true));
            foreach ($abbrs as $abbr) {
                if (isset($timezoneMap[$abbr])) {
                    $display = trim(preg_replace('/\b' . preg_quote($abbr, '/') . '\b/', '', $clean));
                    return [$display, $timezoneMap[$abbr]];
                }
            }
        }

        return [$clean, null];
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

    private function detectGuardrailCategory(string $message, $guardrailCategories): ?string
    {
        $enabled = is_array($guardrailCategories) ? $guardrailCategories : [];
        if (empty($enabled)) {
            return null;
        }

        $text = mb_strtolower($message);

        $patterns = [
            'legal' => ['legal', 'lawsuit', 'contract', 'attorney', 'lawyer', 'compliance', 'terms', 'privacy', 'policy'],
            'medical' => ['medical', 'doctor', 'diagnosis', 'treatment', 'symptom', 'prescription', 'health', 'clinic'],
            'finance' => ['finance', 'loan', 'interest', 'investment', 'tax', 'insurance', 'mortgage', 'credit']
        ];

        foreach ($enabled as $category) {
            $category = mb_strtolower(trim((string) $category));
            if (!isset($patterns[$category])) {
                continue;
            }
            foreach ($patterns[$category] as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function isSensitiveCategoryApproved(string $category, $approvedCategories): bool
    {
        $approved = is_array($approvedCategories) ? $approvedCategories : [];
        return in_array($category, $approved, true);
    }

    private function buildSensitiveGuardrailResponse(string $category, Organization $organization): string
    {
        $base = "I can't provide {$category} advice. For help, please contact a qualified professional.";
        $handoff = $this->buildHandoffMessage($organization);
        return $handoff ? ($base . ' ' . $handoff) : $base;
    }

    private function buildVerifiedOnlyResponse(Organization $organization): string
    {
        $base = "I don't have verified information for that yet.";
        $handoff = $this->buildHandoffMessage($organization);
        return $handoff ? ($base . ' ' . $handoff) : $base;
    }

    private function buildFollowUpPrompt(?array $intentResult): string
    {
        $intent = $intentResult['intent'] ?? '';
        $intent = strtolower(trim($intent));

        if ($intent === 'booking') {
            return 'Would you like me to help you book an appointment?';
        }
        if ($intent === 'pricing') {
            return 'Do you want a detailed quote or a specific item price?';
        }
        if ($intent === 'lookup' || $intent === 'realtime_data') {
            return 'Do you want me to look up anything else for you?';
        }

        return '';
    }

    private function buildProactiveSuggestion(?array $intentResult): string
    {
        $intent = $intentResult['intent'] ?? '';
        $intent = strtolower(trim($intent));

        if ($intent === 'booking') {
            return 'Suggestion: I can share available slots or book a time for you.';
        }
        if ($intent === 'pricing') {
            return 'Suggestion: I can send a detailed price breakdown or compare options.';
        }
        if ($intent === 'realtime_data') {
            return 'Suggestion: I can check live availability or status updates.';
        }
        if ($intent === 'lookup') {
            return 'Suggestion: I can help find related items or services.';
        }

        return '';
    }
    
    private function enforceContextOnlyAnswer(string $message, string $context, string $response, $organization): array
    {
        $roleTerms = '(chairman|chairperson|founder|principal|director|ceo|cfo|coo|md|president|owner|head|manager|trustee|secretary)';
        $needsGrounding = (bool) preg_match('/\bwho\s+is\b/i', $message)
            || (bool) preg_match('/\b' . $roleTerms . '\b/i', $message);

        if (!$needsGrounding) {
            return [$response, false];
        }

        $hasContext = trim($context) !== '';
        $contextHasRole = $hasContext && (bool) preg_match('/\b' . $roleTerms . '\b/i', $context);

        if (!$hasContext || !$contextHasRole) {
            if (preg_match('/\b(don\'t have|do not have|not available|not in our|no information)\b/i', $response)) {
                return [$response, false];
            }

            Log::warning('Widget response blocked by context guard', [
                'org_id' => $organization->id ?? null,
                'org_slug' => $organization->slug ?? null,
                'message' => $message,
                'context_length' => strlen($context),
            ]);

            $fallback = 'We don\'t have that information in our knowledge base yet. Could you share the detail you need?';
            return [$fallback, true];
        }

        return [$response, false];
    }

    private function shouldBlockRoleQueryWithoutContext(string $message, string $context): bool
    {
        $roleTerms = '(chairman|chairperson|founder|principal|director|ceo|cfo|coo|md|president|owner|head|manager|trustee|secretary)';
        $needsGrounding = (bool) preg_match('/\bwho\s+is\b/i', $message)
            || (bool) preg_match('/\b' . $roleTerms . '\b/i', $message)
            || (bool) preg_match('/\b(founder|owner|director|ceo|chairman)\s+(name|named|person)\b/i', $message);

        if (!$needsGrounding) {
            return false;
        }

        $contextText = trim((string) $context);
        if ($contextText === '') {
            return true;
        }

        $hasRole = (bool) preg_match('/\b' . $roleTerms . '\b/i', $contextText);
        $hasName = (bool) preg_match('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,3}\b/', $contextText);

        return !($hasRole && $hasName);
    }

    private function getRoleInfoUnavailableResponse(): string
    {
        return "We don't have that information in our knowledge base yet. Could you share the detail you need?";
    }

    private function isNumericOnlyMessage(?string $message): bool
    {
        if (!is_string($message)) {
            return false;
        }

        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match('/^\d{5,}$/', str_replace(' ', '', $trimmed));
    }

    private function buildClarifyResponse(): string
    {
        return "I didn't understand that. Could you please share a bit more detail?";
    }

    private function buildLowConfidenceClarificationResponse(string $message, ?array $searchResults): ?string
    {
        if (!$this->isEntityInfoQuery($message)) {
            return null;
        }

        $subject = $this->extractEntityInfoSubject($message);
        if ($subject === '') {
            return null;
        }

        $results = $searchResults['results'] ?? null;
        if (!is_array($results) || empty($results)) {
            return null;
        }

        $maxScore = 0.0;
        $titles = [];
        $subjectNormalized = $this->normalizeEntityText($subject);
        $hasDirectMatch = false;

        foreach ($results as $result) {
            $score = (float) ($result['score'] ?? 0.0);
            if ($score > $maxScore) {
                $maxScore = $score;
            }

            $payload = $result['payload'] ?? [];
            $title = trim((string) ($payload['title'] ?? ''));
            $content = trim((string) ($payload['content'] ?? ''));

            if ($title !== '') {
                $titles[] = $title;
            }

            $titleNormalized = $this->normalizeEntityText($title);
            $contentNormalized = $this->normalizeEntityText($content);

            if ($subjectNormalized !== '' && (
                ($titleNormalized !== '' && str_contains($titleNormalized, $subjectNormalized)) ||
                ($contentNormalized !== '' && str_contains($contentNormalized, $subjectNormalized))
            )) {
                $hasDirectMatch = true;
            }
        }

        if ($maxScore >= 0.58 || $hasDirectMatch) {
            return null;
        }

        $suggestions = $this->pickClosestEntitySuggestions($subject, $titles, 3);
        if (empty($suggestions)) {
            return "I couldn't find an exact match for '{$subject}' in our records yet. Could you share the model name again or check the spelling?";
        }

        $options = $this->formatSuggestionList($suggestions);
        return "I couldn't find an exact match for '{$subject}'. Did you mean {$options}? If yes, I can share full details right away.";
    }

    private function isEntityInfoQuery(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        return (bool) preg_match('/\b(tell me more about|tell me about|more about|details about|details of|information about|info about)\b/i', $trimmed);
    }

    private function extractEntityInfoSubject(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return '';
        }

        $patterns = [
            '/(?:tell me more about|tell me about|more about|details about|details of|information about|info about)\s+(.+)$/i',
            '/\babout\s+(.+)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $trimmed, $matches)) {
                $subject = trim((string) ($matches[1] ?? ''));
                $subject = trim($subject, " \t\n\r\0\x0B.?!,;:'\"");
                if ($subject !== '') {
                    return $subject;
                }
            }
        }

        return '';
    }

    private function normalizeEntityText(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? $normalized;
        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function pickClosestEntitySuggestions(string $subject, array $titles, int $limit = 3): array
    {
        $subjectNormalized = $this->normalizeEntityText($subject);
        if ($subjectNormalized === '') {
            return [];
        }

        $scored = [];
        foreach ($titles as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }

            $titleNormalized = $this->normalizeEntityText($title);
            if ($titleNormalized === '') {
                continue;
            }

            similar_text($subjectNormalized, $titleNormalized, $percent);
            $containsBoost = str_contains($titleNormalized, $subjectNormalized) ? 8.0 : 0.0;
            $score = (float) $percent + $containsBoost;

            $scored[] = [
                'title' => $title,
                'score' => $score,
            ];
        }

        usort($scored, function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        $results = [];
        foreach ($scored as $item) {
            if (count($results) >= $limit) {
                break;
            }

            $title = $item['title'];
            if (!in_array($title, $results, true)) {
                $results[] = $title;
            }
        }

        return $results;
    }

    private function formatSuggestionList(array $items): string
    {
        $values = array_values(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $items)));

        $count = count($values);
        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $values[0];
        }

        $last = array_pop($values);
        return implode(', ', $values) . ' or ' . $last;
    }

    private function buildAffirmativeClarifyResponse(): string
    {
        return "Sure. What would you like help with?";
    }

    private function buildClarifyNumberResponse(): string
    {
        return "I didn't understand that. Could you please rephrase or share what that number is about?";
    }

    private function buildDirectCatalogMatchContext(Organization $organization, string $query): string
    {
        $terms = $this->extractExplicitCatalogTerms($query);
        if (empty($terms)) {
            return '';
        }

        $rows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->whereIn('type', ['product', 'info', 'service'])
            ->where(function ($where) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%' . $term . '%';
                    $where->orWhere('name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('content', 'like', $like);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return '';
        }

        $normalizedTerms = array_map(fn ($t) => $this->normalizeEntityText((string) $t), $terms);

        $scored = $rows->map(function (OrganizationData $row) use ($normalizedTerms) {
            $name = trim((string) ($row->name ?? ''));
            $description = trim((string) ($row->description ?? ''));
            $content = trim((string) ($row->content ?? ''));

            $nameNormalized = $this->normalizeEntityText($name);
            $descriptionNormalized = $this->normalizeEntityText($description);
            $contentNormalized = $this->normalizeEntityText($content);

            $score = 0.0;
            foreach ($normalizedTerms as $term) {
                if ($term === '') {
                    continue;
                }

                if ($nameNormalized !== '' && $nameNormalized === $term) {
                    $score += 5.0;
                } elseif ($nameNormalized !== '' && str_contains($nameNormalized, $term)) {
                    $score += 3.0;
                } elseif ($descriptionNormalized !== '' && str_contains($descriptionNormalized, $term)) {
                    $score += 1.5;
                } elseif ($contentNormalized !== '' && str_contains($contentNormalized, $term)) {
                    $score += 1.0;
                }
            }

            return ['row' => $row, 'score' => $score];
        })->filter(fn ($item) => ($item['score'] ?? 0) > 0)
          ->sortByDesc('score')
          ->values();

        if ($scored->isEmpty()) {
            return '';
        }

        $lines = [
            '[DIRECT CATALOG MATCHES from synced database]:',
        ];

        foreach ($scored->take(3) as $item) {
            /** @var OrganizationData $row */
            $row = $item['row'];
            $name = trim((string) ($row->name ?? ''));
            $description = trim((string) ($row->description ?? ''));
            $content = trim((string) ($row->content ?? ''));
            $summary = Str::limit($description !== '' ? $description : $content, 220, '...');
            $url = $this->extractCatalogUrlFromRow($row);

            $line = '- Title: ' . ($name !== '' ? $name : 'Catalog Item');
            if ($summary !== '') {
                $line .= ' | Details: ' . $summary;
            }
            if ($url !== '') {
                $line .= ' | Link: ' . $url;
            }

            $lines[] = $line;
        }

        $lines[] = 'Use DIRECT CATALOG MATCHES as authoritative item presence when answering availability/customization questions.';

        Log::info('Direct catalog match context added', [
            'org_id' => $organization->id,
            'terms' => $terms,
            'matches' => $scored->take(3)->map(function ($item) {
                return $item['row']->name ?? null;
            })->filter()->values()->all(),
        ]);

        return implode("\n", $lines);
    }

    private function extractExplicitCatalogTerms(string $query): array
    {
        $terms = [];
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        if (preg_match_all('/"([^"\n]{2,100})"/', $trimmed, $matches)) {
            foreach (($matches[1] ?? []) as $value) {
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    $terms[] = $candidate;
                }
            }
        }

        if (preg_match_all('/https?:\/\/[^\s]+/i', $trimmed, $matches)) {
            foreach (($matches[0] ?? []) as $rawUrl) {
                $url = rtrim((string) $rawUrl, ".,;:!?)]}");
                $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
                $slug = trim((string) basename($path), '/');
                if ($slug !== '') {
                    $terms[] = trim((string) (preg_replace('/[-_]+/', ' ', $slug) ?? $slug));
                }
            }
        }

        if (preg_match('/\b(?:painting|artwork|product)\s+(?:titled|called|named)?\s*([a-z0-9][a-z0-9\s\-]{2,120})/i', $trimmed, $match)) {
            $candidate = trim((string) ($match[1] ?? ''), " \t\n\r\0\x0B.,;:!?\"'");
            if ($candidate !== '') {
                $terms[] = $candidate;
            }
        }

        $deduped = [];
        foreach ($terms as $term) {
            $normalized = $this->normalizeEntityText((string) $term);
            if ($normalized === '' || mb_strlen($normalized) < 3) {
                continue;
            }
            $deduped[$normalized] = trim((string) $term);
        }

        return array_values($deduped);
    }

    private function extractCatalogUrlFromRow(OrganizationData $row): string
    {
        $metadata = is_array($row->metadata ?? null) ? $row->metadata : [];
        foreach (['url', 'link', 'product_url', 'website_url'] as $key) {
            $value = trim((string) ($metadata[$key] ?? ''));
            if ($value !== '' && preg_match('/^https?:\/\//i', $value)) {
                return $value;
            }
        }

        $combined = trim((string) ($row->description ?? '')) . "\n" . trim((string) ($row->content ?? ''));
        $urls = $this->extractUrlsFromText($combined);

        return $urls[0] ?? '';
    }

    private function isVeryShortQuery(?string $message): bool
    {
        if (!is_string($message)) {
            return false;
        }

        $trimmed = trim($message);
        if ($trimmed === '') {
            return false;
        }

        $wordCount = str_word_count($trimmed);
        if ($wordCount <= 1) {
            return true;
        }

        return $wordCount <= 2 && mb_strlen($trimmed) <= 12;
    }

    private function responseHasQuestion(string $text): bool
    {
        $clean = trim($text);
        if ($clean === '') {
            return false;
        }

        // Consider it a question if any line contains a question mark,
        // even when additional helper/supplementary lines follow.
        return str_contains($clean, '?');
    }

    private function getLastAssistantMessage(Organization $organization, string $sessionId): ?string
    {
        if ($sessionId === '') {
            return null;
        }

        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return null;
        }

        $lastAssistant = $conversation->messages()
            ->whereIn('sender_type', ['ai', 'assistant'])
            ->orderBy('sent_at', 'desc')
            ->first();

        if (!$lastAssistant) {
            return null;
        }

        $text = trim(strip_tags((string) $lastAssistant->message));
        return $text !== '' ? $text : null;
    }

    private function shouldClarifyAffirmative(string $message, Organization $organization, string $sessionId): bool
    {
        if (!$this->isAffirmativeFollowUp($message)) {
            return false;
        }

        $lastAssistant = $this->getLastAssistantMessage($organization, $sessionId);
        if ($lastAssistant === null) {
            return true;
        }

        return !$this->responseHasQuestion($lastAssistant);
    }

    private function isConversationEndingPhrase(?string $message): bool
    {
        if (!is_string($message) || trim($message) === '') {
            return false;
        }

        $lowerMessage = strtolower(trim($message));
        
        // Exact matches for conversation ending phrases
        $endPhrases = [
            'no', 'nope', 'nah', 'no thanks', 'no thank you', 'not needed',
            'not interested', 'no problem', 'all good', 'that\'s all',
            'goodbye', 'bye', 'see you', 'later', 'thanks bye',
            'i\'m good', 'im good', 'all set', 'nothing else'
        ];

        foreach ($endPhrases as $phrase) {
            if ($lowerMessage === $phrase || str_ends_with($lowerMessage, $phrase)) {
                return true;
            }
        }

        // Check if message contains negative response patterns
        return preg_match('/^(no|nope|nah|not)\s*(thank|thanks|needed|interested|required)/i', $lowerMessage) === 1;
    }

    private function shouldTreatAsConversationEnding(?string $message, bool $lastAssistantAskedQuestion = false, bool $hasPendingFollowUpState = false): bool
    {
        if (!$this->isConversationEndingPhrase($message)) {
            return false;
        }

        if (!is_string($message)) {
            return false;
        }

        $clean = strtolower(trim($message));
        $ambiguousNegativeReplies = [
            'no', 'nope', 'nah', 'not really', 'not now', 'no thanks', 'no thank you',
        ];

        if (($lastAssistantAskedQuestion || $hasPendingFollowUpState) && in_array($clean, $ambiguousNegativeReplies, true)) {
            return false;
        }

        return true;
    }

    private function isPromoQuery(string $message): bool
    {
        return (bool) preg_match('/\b(promo|promotion|discount|offer|coupon|deal|sale|special)\b/i', $message);
    }

    private function buildPromoUnavailableResponse(): string
    {
        return "We don't have any promotions or discount details listed right now. Could you share what you're looking for?";
    }

    private function isCallbackRequest(string $message): bool
    {
        return (bool) preg_match('/\b(call\s*back|callback|call\s*me|ring\s*me|please\s*call|phone\s*me|contact\s*me)\b/i', $message);
    }

    private function extractPhoneFromMessage(string $message): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', ' ', $message);
        if (!$normalized) {
            return null;
        }

        if (preg_match('/\+?\d[\d\s-]{7,}/', $normalized, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[0]));
        }

        return null;
    }

    private function buildCallbackResponse(?string $userPhone, ?string $orgEmail, ?string $orgPhone, string $orgWebsite): string
    {
        $response = 'Thanks! I will pass your request to our team to arrange a call.';
        if ($userPhone) {
            $response .= " I have your number as {$userPhone}.";
        }

        $response .= " If it’s urgent, you can reach us at Email: {$orgEmail}";
        if ($orgPhone) {
            $response .= " | Phone: {$orgPhone}";
        }
        $response .= " | Website: {$orgWebsite}.";

        return $response;
    }

    private function shouldBypassNumericGuard(?ChatConversation $conversation): bool
    {
        if (!$conversation) {
            return false;
        }

        $lastAssistant = $conversation->messages()
            ->whereIn('sender_type', ['ai', 'agent'])
            ->latest('sent_at')
            ->first();

        if (!$lastAssistant || !is_string($lastAssistant->message)) {
            return false;
        }

        $text = strtolower($lastAssistant->message);

        return (bool) preg_match(
            '/\b(phone|mobile|contact\s*number|number|otp|one\s*time\s*password|code|pin|zip|postal|pincode|id\s*number|student\s*id|admission\s*number|order\s*number|reference\s*number|ticket\s*number)\b/i',
            $text
        );
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
        $affirm = ['yes', 'yeah', 'yup', 'yep', 'ya', 'yah', 'sure', 'certainly', 'ok', 'okay', 'please', 'go ahead', 'go on', 'continue', 'proceed', 'carry on'];
        foreach ($affirm as $a) {
            if ($t === $a) return true;
        }
        // Phrases asking to elaborate (generic follow-up only)
        // Important: avoid matching specific intent queries like "tell me more about Victoris"
        $patterns = [
            '/^yes\b.*more/',
            '/^(tell me more|more details|explain more|how it works)\s*$/',
            '/^(yes|yeah|yup|yep|ya|yah|sure|certainly|ok|okay|please)\b.*\b(tell me more|more details|explain more|how it works)\b/'
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $t)) return true;
        }
        // Also treat short confirmations under 16 chars as affirmative if contain yes/ok/sure
        if (mb_strlen($t) < 16 && preg_match('/\b(yes|yeah|yup|yep|ya|yah|ok|okay|sure|please)\b/', $t)) return true;
        return false;
    }

    private function isNegativeFollowUp(string $text): bool
    {
        $t = trim(mb_strtolower($text));
        if ($t === '') {
            return false;
        }

        $negatives = [
            'no', 'nope', 'nah', 'not now', 'dont', "don't", 'do not', 'not really', 'no thanks', 'no thank you', 'maybe later'
        ];

        if (in_array($t, $negatives, true)) {
            return true;
        }

        if (mb_strlen($t) < 20 && preg_match('/\b(no|nope|nah|not now|no thanks|not really)\b/', $t)) {
            return true;
        }

        return false;
    }

    private function shouldUseAffirmativeNoContextFallback(bool $isAffirmativeContinuation, string $context = '', ?string $shopifyContext = null, $liveData = null): bool
    {
        if (!$isAffirmativeContinuation) {
            return false;
        }

        if (!empty($liveData)) {
            return false;
        }

        $combined = trim($context . "\n" . (string) ($shopifyContext ?? ''));
        return $combined === '';
    }

    private function buildAffirmativeNoContextResponse(Organization $organization): string
    {
        $orgWebsite = $organization->website ?: config('app.url');
        $orgEmail = $organization->contact_email ?? null;
        $orgPhone = $organization->contact_phone ?? null;
        $contactResponse = $this->buildContactResponse($orgEmail, $orgPhone, $orgWebsite);

        return "Sorry, we don't have the required details in our verified knowledge base right now. {$contactResponse}";
    }

    private function buildContextPayloadCache(array $results): array
    {
        $payloads = [];
        foreach (array_slice($results, 0, 5) as $res) {
            $p = is_array($res) ? ($res['payload'] ?? $res) : [];
            if (!is_array($p) || empty($p)) {
                continue;
            }

            $modelPricing = $this->extractModelPricingFromPayload($p);
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
                'follow_up' => $p['follow_up'] ?? null,
                'supplementary_info' => $this->extractSupplementaryInfoFromPayload($p),
                'ex_showroom_price_inr' => $modelPricing['ex_showroom_price_inr'] ?? null,
                'approx_on_road_price_inr' => $modelPricing['approx_on_road_price_inr'] ?? null,
            ];
        }

        return $payloads;
    }

    private function persistLastContextPayloads(Organization $organization, string $sessionId, array $payloads, array $userInfo = [], array $locationInfo = []): void
    {
        if (empty($payloads) || trim($sessionId) === '') {
            return;
        }

        try {
            $conversation = ChatConversation::firstOrCreate(
                [
                    'conversation_id' => $sessionId,
                    'organization_id' => $organization->id,
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
                    'agent_status' => 'ai_active',
                    'last_activity_at' => now(),
                ]
            );

            $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
            $meta['last_context_payloads'] = $payloads;
            $conversation->update([
                'metadata' => $meta,
                'last_activity_at' => now(),
            ]);
        } catch (\Throwable $t) {
            Log::warning('Failed saving last context payloads', ['error' => $t->getMessage()]);
        }
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
            'starterPrompts' => $this->getWidgetStarterPrompts($organization),
            'theme' => $organization->settings['widget_theme'] ?? 'default',
            'position' => $organization->settings['widget_position'] ?? 'bottom-right',
            'primaryColor' => $organization->settings['primary_color'] ?? '#007bff'
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function getWidgetStarterPrompts(Organization $organization): array
    {
        try {
            $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
            $customPrompts = [];
            foreach ((array) ($settings['widget_custom_starter_prompts'] ?? []) as $prompt) {
                $value = trim((string) $prompt);
                if ($value !== '' && !in_array($value, $customPrompts, true)) {
                    $customPrompts[] = $value;
                }
            }

            $faqs = OrganizationFaq::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->where('is_starter_prompt', true)
                ->orderBy('starter_sort_order')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(6)
                ->pluck('question')
                ->map(function ($question) {
                    return trim((string) $question);
                })
                ->filter()
                ->values()
                ->all();

            $merged = array_values(array_unique(array_filter(array_merge($customPrompts, $faqs))));
            return array_slice($merged, 0, 6);
        } catch (\Throwable $e) {
            Log::warning('Failed loading widget starter prompts', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Fetch agent messages for a widget session
     */
    public function getAgentMessages(Request $request, $orgId)
    {
        $organization = is_numeric($orgId)
            ? Organization::find($orgId)
            : Organization::where('slug', $orgId)->first();

        if (!$organization || !$organization->is_active) {
            return response()->json(['messages' => []], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
            return response()->json(['messages' => []], 403)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $sessionId = (string) $request->query('session_id');
        $lastId = (int) $request->query('last_id', 0);

        if ($sessionId === '') {
            return response()->json(['messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$conversation) {
            return response()->json(['messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'agent')
            ->when($lastId > 0, function ($q) use ($lastId) {
                $q->where('id', '>', $lastId);
            })
            ->orderBy('id', 'asc')
            ->get();

        $payload = $messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'message' => $msg->message,
                'sender_name' => $msg->getSenderDisplayName(),
                'sent_at' => optional($msg->sent_at)->toISOString() ?? now()->toISOString(),
            ];
        })->values();

        return response()->json(['messages' => $payload], 200)
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function getConversationHistory(Request $request, $orgId)
    {
        $organization = is_numeric($orgId)
            ? Organization::find($orgId)
            : Organization::where('slug', $orgId)->first();

        if (!$organization || !$organization->is_active) {
            return response()->json(['session_id' => null, 'messages' => []], 404)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        if (!$this->isWidgetRequestAllowedForOrganization($organization, $request)) {
            return response()->json(['session_id' => null, 'messages' => []], 403)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $email = strtolower(trim((string) $request->query('email', '')));
        $phone = trim((string) $request->query('phone', ''));
        $sessionId = trim((string) $request->query('session_id', ''));
        $limit = max(1, min(50, (int) $request->query('limit', 30)));

        if ($sessionId === '' && $email === '') {
            return response()->json(['session_id' => null, 'messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $conversation = null;

        if ($sessionId !== '') {
            $conversation = ChatConversation::where('organization_id', $organization->id)
                ->where('conversation_id', $sessionId)
                ->first();
        }

        if (!$conversation && $email !== '') {
            $query = ChatConversation::where('organization_id', $organization->id)
                ->whereRaw('LOWER(visitor_email) = ?', [$email]);

            if ($phone !== '') {
                $query->where('visitor_phone', $phone);
            }

            $conversation = $query
                ->orderByDesc('last_activity_at')
                ->orderByDesc('id')
                ->first();
        }

        if (!$conversation) {
            return response()->json(['session_id' => null, 'messages' => []], 200)
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $messages = ChatMessage::where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(function (ChatMessage $msg) {
                $sender = 'bot';
                if ($msg->sender_type === 'user') {
                    $sender = 'user';
                } elseif ($msg->sender_type === 'agent') {
                    $sender = 'agent';
                }

                return [
                    'id' => $msg->id,
                    'sender' => $sender,
                    'sender_name' => $msg->getSenderDisplayName(),
                    'message' => $msg->message,
                    'sent_at' => optional($msg->sent_at)->toISOString() ?? now()->toISOString(),
                ];
            });

        return response()->json([
            'session_id' => $conversation->conversation_id,
            'messages' => $messages,
        ], 200)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function isWidgetRequestAllowedForOrganization(Organization $organization, Request $request): bool
    {
        $settings = $organization->settings ?? [];
        $configured = $settings['widget_allowed_domains'] ?? [];

        if (is_string($configured)) {
            $configured = preg_split('/[\r\n,]+/', $configured) ?: [];
        }

        if (!is_array($configured)) {
            $configured = [];
        }

        $allowedHosts = array_values(array_filter(array_map(function ($item) {
            $host = strtolower(trim((string) $item));
            if ($host === '') {
                return null;
            }
            if (str_starts_with($host, 'http://') || str_starts_with($host, 'https://')) {
                $parsed = parse_url($host, PHP_URL_HOST);
                $host = strtolower(trim((string) $parsed));
            }
            return trim($host, '.');
        }, $configured)));

        if (empty($allowedHosts)) {
            $websiteHost = strtolower((string) parse_url((string) ($organization->website ?? ''), PHP_URL_HOST));
            $websiteHost = trim($websiteHost, '.');

            if ($websiteHost !== '') {
                $allowedHosts[] = $websiteHost;
            }

            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $appHost = trim($appHost, '.');
            if ($appHost !== '') {
                $allowedHosts[] = $appHost;
            }

            $allowedHosts = array_values(array_unique(array_filter($allowedHosts)));
        }

        $origin = trim((string) $request->header('origin', ''));
        $referer = trim((string) $request->header('referer', ''));
        $currentHost = '';

        if ($origin !== '') {
            $currentHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        }
        if ($currentHost === '' && $referer !== '') {
            $currentHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
        }

        $currentHost = trim($currentHost, '.');
        if ($currentHost === '') {
            Log::warning('Widget domain guard could not resolve request host', [
                'org_id' => $organization->id,
                'org_slug' => $organization->slug,
                'origin' => $origin,
                'referer' => $referer,
                'allowed_hosts' => $allowedHosts,
            ]);
            return false;
        }

        if (empty($allowedHosts)) {
            Log::warning('Widget domain guard has no allowed hosts configured or derivable', [
                'org_id' => $organization->id,
                'org_slug' => $organization->slug,
                'origin' => $origin,
                'referer' => $referer,
            ]);
            return false;
        }

        foreach ($allowedHosts as $allowed) {
            if ($currentHost === $allowed || str_ends_with($currentHost, '.' . $allowed)) {
                return true;
            }
        }

        Log::warning('Widget domain guard blocked request', [
            'org_id' => $organization->id,
            'org_slug' => $organization->slug,
            'current_host' => $currentHost,
            'origin' => $origin,
            'referer' => $referer,
            'allowed_hosts' => $allowedHosts,
        ]);

        return false;
    }

    /**
     * Save conversation to database
     */
    private function saveConversationToDatabase($organization, $sessionId, $userMessage, $aiResponse, $userInfo = [], $locationInfo = [], $intentResult = null, ?array $structuredFollowUpState = null)
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
                    'agent_status' => 'ai_active',
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
                    'intent_method' => $intentResult['method'] ?? null,
                    'one_call_envelope_state' => $structuredFollowUpState,
                ]
            ]);

            // Optional email notification for each interaction (user + AI)
            $this->sendChatInteractionNotification($organization, $conversation, $userMessage, $aiResponse, $userInfo, $locationInfo);

            // Update conversation activity
            $conversation->update([
                'last_activity_at' => now()
            ]);

            $conversation->refresh();
            $conversationMeta = is_array($conversation->metadata) ? $conversation->metadata : [];
            $contextPayloads = $conversationMeta['last_context_payloads'] ?? [];
            $this->followUpStateService->updatePendingState(
                $conversation,
                (string) $aiResponse,
                is_array($contextPayloads) ? $contextPayloads : [],
                is_array($structuredFollowUpState) ? $structuredFollowUpState : null
            );

            // Generate conversation title from first message if not set
            if (!$conversation->title) {
                $conversation->generateTitle();
            }

            Log::info('Conversation saved to database', [
                'conversation_id' => $conversation->id,
                'session_id' => $sessionId,
                'org_id' => $organization->id
            ]);

            return $conversation;

        } catch (\Exception $e) {
            Log::error('Failed to save conversation to database', [
                'session_id' => $sessionId,
                'org_id' => $organization->id,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function extractOneCallEnvelope(string $rawResponseText): ?array
    {
        $raw = trim($rawResponseText);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $raw, $m)) {
            $raw = trim((string) ($m[1] ?? ''));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $raw, $matches)) {
                $decoded = json_decode((string) $matches[0], true);
            }
        }

        if (!is_array($decoded)) {
            $pseudo = $this->parsePseudoEnvelope($raw);
            if (is_array($pseudo)) {
                return $pseudo;
            }
            return null;
        }

        $response = trim((string) ($decoded['response'] ?? $decoded['answer'] ?? ''));
        if ($response === '') {
            return null;
        }

        $state = [
            'entity' => trim((string) ($decoded['entity'] ?? '')),
            'topics_covered' => is_array($decoded['topics_covered'] ?? null) ? $decoded['topics_covered'] : [],
            'follow_up' => is_array($decoded['follow_up'] ?? null) ? $decoded['follow_up'] : null,
        ];

        return [
            'response' => $response,
            'structured_state' => $state,
        ];
    }

    private function retryStrictEnvelopeExtraction(string $rawResponseText, string $userMessage, Organization $organization, int $organizationId, string $aiProvider): ?array
    {
        try {
            $system = 'Convert assistant output into strict JSON only. Return exactly one JSON object with keys: response (string), entity (string), topics_covered (array of strings), follow_up (object|null with keys type and topic array). No markdown, no extra text.';
            $user = "User message:\n{$userMessage}\n\nAssistant output:\n{$rawResponseText}";

            if ($aiProvider === 'openai') {
                $model = $this->aiAgentService->getOpenAiModelForOrganization($organizationId);
                $retry = $this->aiAgentService->openAiChat(
                    [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    $model,
                    null,
                    $organizationId
                );
            } else {
                $model = $this->aiAgentService->getLlamaModelForOrganization($organizationId);
                $retry = $this->aiAgentService->smartLlmChat(
                    [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    $model,
                    null,
                    $organizationId,
                    [
                        'num_predict' => 140,
                        'temperature' => 0.0,
                        'use_vastai' => true,
                    ]
                );
            }

            $retryText = trim((string) ($retry['message']['content'] ?? ''));
            if ($retryText === '') {
                return null;
            }

            return $this->extractOneCallEnvelope($retryText);
        } catch (\Throwable $e) {
            Log::warning('One-call envelope strict retry failed', [
                'org_id' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parsePseudoEnvelope(string $raw): ?array
    {
        $text = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if ($text === '') {
            return null;
        }

        if (!preg_match('/\bresponse\s*:/i', $text)) {
            return null;
        }

        $response = '';
        $entity = '';
        $topicsCovered = [];
        $followUp = null;

        if (preg_match('/response\s*:\s*(.*?)(?=\bentity\s*:|\btopics_covered\s*:|\bfollow_up\s*:|$)/i', $text, $m)) {
            $response = trim((string) ($m[1] ?? ''));
            $response = trim($response, "\"' ");
        }

        if (preg_match('/entity\s*:\s*(.*?)(?=\btopics_covered\s*:|\bfollow_up\s*:|$)/i', $text, $m)) {
            $entity = trim((string) ($m[1] ?? ''));
            $entity = trim($entity, "\"' ");
        }

        if (preg_match('/topics_covered\s*:\s*(\[[^\]]*\])/i', $text, $m)) {
            $rawTopics = (string) ($m[1] ?? '[]');
            $decodedTopics = json_decode($rawTopics, true);
            if (!is_array($decodedTopics)) {
                $fallback = trim($rawTopics, '[] ');
                $decodedTopics = array_filter(array_map('trim', explode(',', $fallback)));
                $decodedTopics = array_map(function ($item) {
                    return trim((string) $item, "\"' ");
                }, $decodedTopics);
            }
            $topicsCovered = is_array($decodedTopics) ? array_values($decodedTopics) : [];
        }

        if (preg_match('/follow_up\s*:\s*(\{.*\}|null)/i', $text, $m)) {
            $rawFollowUp = trim((string) ($m[1] ?? ''));
            if (strtolower($rawFollowUp) !== 'null') {
                $decodedFollowUp = json_decode($rawFollowUp, true);
                if (is_array($decodedFollowUp)) {
                    $followUp = $decodedFollowUp;
                }
            }
        }

        if ($response === '') {
            return null;
        }

        return [
            'response' => $response,
            'structured_state' => [
                'entity' => $entity,
                'topics_covered' => is_array($topicsCovered) ? $topicsCovered : [],
                'follow_up' => is_array($followUp) ? $followUp : null,
            ],
        ];
    }

    private function isPricingFollowUp(string $message): bool
    {
        $msg = strtolower($message);
        return (bool) preg_match('/\b(price|pricing|quote|estimate|breakdown|cost|range)\b/', $msg)
            && str_word_count($msg) <= 8;
    }

    private function buildPricingLiveDataHints($liveData): string
    {
        if (!is_array($liveData) || empty($liveData)) {
            return '';
        }

        $rows = $this->isListArray($liveData) ? $liveData : [$liveData];
        $creditRows = [];
        $subscriptionRows = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $planType = strtolower((string) ($row['plan_type'] ?? ''));
            if ($planType === 'credit') {
                $creditRows[] = $row;
            } elseif ($planType === 'subscription' || $planType === '') {
                $subscriptionRows[] = $row;
            }
        }

        $lines = [];
        $lines[] = 'PRICING INTERPRETATION HINTS:';
        $lines[] = '- Treat plan_type="credit" rows as credit-based one-time packs.';
        $lines[] = '- Treat plan_type="subscription" rows as recurring plans.';

        if (!empty($creditRows)) {
            $lines[] = '- CREDIT-BASED PLANS ARE AVAILABLE in LIVE DATA. Do not say unavailable.';
            foreach (array_slice($creditRows, 0, 5) as $row) {
                $name = (string) ($row['name'] ?? 'Credit Plan');
                $price = (string) ($row['price'] ?? 'N/A');
                $currency = (string) ($row['currency'] ?? 'USD');
                $credits = isset($row['credits']) && $row['credits'] !== null ? number_format((int) $row['credits']) : 'N/A';
                $lines[] = "  - {$name}: {$currency} {$price}, {$credits} tokens";
            }
        } else {
            $lines[] = '- CREDIT-BASED PLANS are not present in this LIVE DATA set.';
        }

        if (!empty($subscriptionRows)) {
            $lines[] = '- Subscription rows are also present; separate them clearly from credit packs.';
        }

        return implode("\n", $lines);
    }

    private function getLastUserMessageForSession(int $organizationId, string $sessionId): ?string
    {
        $conversation = ChatConversation::where('conversation_id', $sessionId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$conversation) {
            return null;
        }

        $lastUser = ChatMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'user')
            ->latest('id')
            ->first();

        return $lastUser?->message;
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

            $mode = $settings['notify_chat_email_mode'] ?? 'immediate';
            $intervalMinutes = (int) ($settings['notify_chat_email_interval_minutes'] ?? 10);
            $intervalMinutes = max(1, $intervalMinutes);

            $mailgunDomain = config('services.mailgun.domain');
            if (!$mailgunDomain) {
                $fromAddress = config('mail.from.address');
                if (is_string($fromAddress) && str_contains($fromAddress, '@')) {
                    $mailgunDomain = substr(strrchr($fromAddress, '@'), 1) ?: null;
                }
            }
            $replyTo = $mailgunDomain
                ? ('ai-chat-support+' . $conversation->conversation_id . '@' . $mailgunDomain)
                : null;

            if (!$replyTo) {
                Log::warning('Chat interaction reply-to missing mailgun domain', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $organization->id ?? null,
                ]);
            }

            if ($mode === 'digest') {
                $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                $lastSentAt = $metadata['email_last_sent_at'] ?? null;
                $lastSentMessageId = $metadata['email_last_message_id'] ?? null;
                if ($lastSentAt) {
                    try {
                        $lastSentAtTime = \Carbon\Carbon::parse($lastSentAt);
                        if ($lastSentAtTime->diffInMinutes(now()) < $intervalMinutes) {
                            Log::info('Chat digest suppressed (interval)', [
                                'conversation_id' => $conversation->conversation_id,
                                'org_id' => $organization->id ?? null,
                                'last_sent_at' => $lastSentAt,
                                'interval_minutes' => $intervalMinutes,
                            ]);
                            return;
                        }
                    } catch (\Throwable $e) {
                        // If parsing fails, fall through and attempt to send.
                    }
                }

                $messagesQuery = ChatMessage::where('conversation_id', $conversation->id)
                    ->orderBy('sent_at', 'asc')
                    ->orderBy('id', 'asc');

                if (!empty($lastSentMessageId)) {
                    $messagesQuery->where('id', '>', $lastSentMessageId);
                }

                $messages = $messagesQuery->get();
                if ($messages->isEmpty()) {
                    Log::info('Chat digest suppressed (no new messages)', [
                        'conversation_id' => $conversation->conversation_id,
                        'org_id' => $organization->id ?? null,
                    ]);
                    return;
                }

                $payload = [
                    'organization' => $organization,
                    'conversation' => $conversation,
                    'messages' => $messages,
                    'user_info' => $userInfo,
                    'location_info' => $locationInfo,
                    'range_start' => $messages->first()->sent_at,
                    'range_end' => $messages->last()->sent_at,
                    'message_count' => $messages->count(),
                    'interval_minutes' => $intervalMinutes,
                    'reply_to' => $replyTo,
                ];

                Mail::to($emails)->send(new ChatInteractionDigestNotification($payload));
                Log::info('Chat digest sent', [
                    'conversation_id' => $conversation->conversation_id,
                    'org_id' => $organization->id ?? null,
                    'email_count' => count($emails),
                ]);

                $metadata['email_last_sent_at'] = now()->toIso8601String();
                $metadata['email_last_message_id'] = $messages->last()->id;
                $conversation->metadata = $metadata;
                $conversation->save();

                return;
            }

            $payload = [
                'organization' => $organization,
                'conversation' => $conversation,
                'user_message' => $userMessage,
                'ai_response' => $aiResponse,
                'user_info' => $userInfo,
                'location_info' => $locationInfo,
                'reply_to' => $replyTo,
            ];

            Mail::to($emails)->send(new ChatInteractionNotification($payload));
            Log::info('Chat interaction email sent', [
                'conversation_id' => $conversation->conversation_id,
                'org_id' => $organization->id ?? null,
                'email_count' => count($emails),
            ]);
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
        // Allow disabling token enforcement via config/services.ai_agent.enforce_limits or env AI_ENFORCE_LIMITS=false
        $enforce = (bool) config('services.ai_agent.enforce_limits', env('AI_ENFORCE_LIMITS', true));
        if (!$enforce) {
            \Log::debug('Token limits not enforced (config disabled)', [
                'org_id' => $organization->id,
                'org_name' => $organization->name
            ]);
            return true;
        }

        // Get the organization's billing user (prefer admin, then legacy org users, then first)
        $user = $organization->users()->where('role', 'admin')->first();
        if (!$user) {
            $user = $organization->legacyUsers()->where('role', 'admin')->first();
        }
        if (!$user) {
            $user = $organization->legacyUsers()->first();
        }
        if (!$user) {
            $user = $organization->users()->first();
        }
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
            $creditBalance = optional(\App\Models\UserCredit::getOrCreateForUser($user->id))->getUsableCreditBalance() ?? 0;
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

        $tokenLimit = $subscription->subscriptionPlan->token_cap;
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
    private function extractSupplementaryInfoFromPayload(array $payload): string
    {
        $candidates = [
            $payload['supplementary_info'] ?? null,
            $payload['supplementary'] ?? null,
            data_get($payload, 'metadata.supplementary_info'),
            data_get($payload, 'metadata.supplementary'),
            data_get($payload, 'metadata.csv.supplementary_info'),
            data_get($payload, 'metadata.csv.supplementary'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value)) {
                $text = trim($this->htmlToPlainWithLinks($value));
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function extractModelPricingFromPayload(array $payload): array
    {
        $exShowroom = $this->firstNonEmptyString([
            $payload['ex_showroom_price_inr'] ?? null,
            $payload['ex_showroom_price'] ?? null,
            data_get($payload, 'metadata.ex_showroom_price_inr'),
            data_get($payload, 'metadata.ex_showroom_price'),
            data_get($payload, 'metadata.csv.ex_showroom_price_inr'),
            data_get($payload, 'metadata.csv.ex_showroom_price'),
        ]);

        $onRoad = $this->firstNonEmptyString([
            $payload['approx_on_road_price_inr'] ?? null,
            $payload['on_road_price_inr'] ?? null,
            $payload['on_road_price'] ?? null,
            data_get($payload, 'metadata.approx_on_road_price_inr'),
            data_get($payload, 'metadata.on_road_price_inr'),
            data_get($payload, 'metadata.on_road_price'),
            data_get($payload, 'metadata.csv.approx_on_road_price_inr'),
            data_get($payload, 'metadata.csv.on_road_price_inr'),
            data_get($payload, 'metadata.csv.on_road_price'),
        ]);

        $content = (string) ($payload['content'] ?? '');
        if ($content !== '') {
            if ($exShowroom === '' && preg_match('/ex\s*showroom\s*price(?:\s*inr)?\s*:\s*([^\n]+)/i', $content, $matches)) {
                $exShowroom = trim((string) ($matches[1] ?? ''));
            }

            if ($onRoad === '' && preg_match('/(?:approx\s*)?on\s*road\s*price(?:\s*inr)?\s*:\s*([^\n]+)/i', $content, $matches)) {
                $onRoad = trim((string) ($matches[1] ?? ''));
            }
        }

        return [
            'ex_showroom_price_inr' => $this->normalizePriceText($exShowroom),
            'approx_on_road_price_inr' => $this->normalizePriceText($onRoad),
        ];
    }

    private function firstNonEmptyString(array $values): string
    {
        foreach ($values as $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return '';
    }

    private function normalizePriceText(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function buildSupplementaryInstruction(Organization $organization): string
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $customInstruction = trim((string) ($settings['supplementary_instruction'] ?? ''));
        if ($customInstruction !== '') {
            return $customInstruction;
        }

        return "When CURRENT CONTEXT includes a line starting with 'Supplementary:', include one short, relevant final sentence using that data. Treat supplementary data as optional context, never invent missing values, and only include it when it helps answer the user's query.";
    }

    private function compactContextForOpenAi(string $context): string
    {
        $normalized = trim($context);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/\[LIVE DATA from[^\]]*\]:\s*(.*?)\s*\[END LIVE DATA\]/s', $normalized, $matches)) {
            $rawLiveData = trim((string) ($matches[1] ?? ''));
            $decoded = json_decode($rawLiveData, true);

            if (is_array($decoded)) {
                $summaryPayload = $this->summarizeLiveDataForPrompt($decoded);
                $summaryJson = json_encode($summaryPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $replacement = "[LIVE DATA SUMMARY]:\n" . ($summaryJson ?: '{}') . "\n[END LIVE DATA SUMMARY]";
                $normalized = str_replace($matches[0], $replacement, $normalized);
            } else {
                $snippet = mb_substr($rawLiveData, 0, 1800);
                $replacement = "[LIVE DATA SNIPPET]:\n{$snippet}\n[END LIVE DATA SNIPPET]";
                $normalized = str_replace($matches[0], $replacement, $normalized);
            }
        }

        if (mb_strlen($normalized) > 7000) {
            $normalized = mb_substr($normalized, 0, 7000) . "\n[Context truncated for token control.]";
        }

        return $normalized;
    }

    private function summarizeLiveDataForPrompt(array $liveData): array
    {
        if (!$this->isListArray($liveData)) {
            return $this->sanitizePromptArray($liveData);
        }

        $items = $this->selectLiveDataItemsForSummary($liveData);
        $summary = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $summary[] = $this->summarizeLiveDataRow($item);
            } else {
                $summary[] = $item;
            }
        }

        return [
            'items_count' => count($liveData),
            'items_shown' => count($summary),
            'items' => $summary,
        ];
    }

    private function selectLiveDataItemsForSummary(array $liveData): array
    {
        if (empty($liveData)) {
            return [];
        }

        $isPricingRows = collect($liveData)->every(function ($item) {
            if (!is_array($item)) {
                return false;
            }

            return array_key_exists('plan_type', $item)
                || array_key_exists('billing_period', $item)
                || array_key_exists('credits', $item)
                || array_key_exists('token_cap', $item);
        });

        if (!$isPricingRows) {
            return array_slice($liveData, 0, 8);
        }

        $creditRows = [];
        $subscriptionRows = [];
        $otherRows = [];

        foreach ($liveData as $row) {
            if (!is_array($row)) {
                $otherRows[] = $row;
                continue;
            }

            $planType = strtolower((string) ($row['plan_type'] ?? ''));
            if ($planType === 'credit') {
                $creditRows[] = $row;
            } elseif ($planType === 'subscription' || $planType === '') {
                $subscriptionRows[] = $row;
            } else {
                $otherRows[] = $row;
            }
        }

        $sortRows = function (array $rows): array {
            usort($rows, function ($a, $b) {
                $aSort = (int) ($a['sort_order'] ?? 9999);
                $bSort = (int) ($b['sort_order'] ?? 9999);
                if ($aSort !== $bSort) {
                    return $aSort <=> $bSort;
                }

                $aName = strtolower((string) ($a['name'] ?? $a['title'] ?? ''));
                $bName = strtolower((string) ($b['name'] ?? $b['title'] ?? ''));
                if ($aName !== $bName) {
                    return $aName <=> $bName;
                }

                $aPeriod = strtolower((string) ($a['billing_period'] ?? ''));
                $bPeriod = strtolower((string) ($b['billing_period'] ?? ''));
                return $aPeriod <=> $bPeriod;
            });

            return $rows;
        };

        $creditRows = $sortRows($creditRows);
        $subscriptionRows = $sortRows($subscriptionRows);

        $selected = [];
        if (!empty($creditRows)) {
            $selected = array_merge($selected, array_slice($creditRows, 0, 8));
        }
        if (!empty($subscriptionRows)) {
            $selected = array_merge($selected, array_slice($subscriptionRows, 0, 8));
        }
        if (!empty($otherRows)) {
            $selected = array_merge($selected, array_slice($otherRows, 0, 4));
        }

        return array_slice($selected, 0, 16);
    }

    private function summarizeLiveDataRow(array $row): array
    {
        $preferredKeys = [
            'id', 'name', 'title', 'slug', 'description',
            'price', 'currency', 'plan_type', 'billing_period', 'token_cap', 'credits', 'tokens',
            'is_active', 'sort_order'
        ];

        $picked = [];
        foreach ($preferredKeys as $key) {
            if (array_key_exists($key, $row) && $this->isScalarOrNull($row[$key])) {
                $picked[$key] = $this->sanitizeScalarForPrompt($row[$key]);
            }
        }

        if (count($picked) < 8) {
            foreach ($row as $key => $value) {
                if (isset($picked[$key])) {
                    continue;
                }
                if (!$this->isScalarOrNull($value)) {
                    continue;
                }
                $picked[$key] = $this->sanitizeScalarForPrompt($value);
                if (count($picked) >= 8) {
                    break;
                }
            }
        }

        return $picked;
    }

    private function sanitizePromptArray(array $data): array
    {
        $result = [];
        $count = 0;
        foreach ($data as $key => $value) {
            if ($count >= 25) {
                break;
            }

            if (is_array($value)) {
                $result[$key] = $this->isListArray($value)
                    ? array_slice(array_map(fn ($item) => is_array($item) ? $this->summarizeLiveDataRow($item) : $this->sanitizeScalarForPrompt($item), $value), 0, 5)
                    : $this->sanitizePromptArray($value);
            } else {
                $result[$key] = $this->sanitizeScalarForPrompt($value);
            }

            $count++;
        }

        return $result;
    }

    private function sanitizeScalarForPrompt($value)
    {
        if (is_string($value)) {
            $clean = trim((string) preg_replace('/\s+/', ' ', $value));
            if (mb_strlen($clean) > 220) {
                return mb_substr($clean, 0, 220) . '…';
            }
            return $clean;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }

    private function isScalarOrNull($value): bool
    {
        return is_scalar($value) || $value === null;
    }

    private function isListArray(array $array): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    private function getWidgetRulePolicy(Organization $organization): array
    {
        $defaults = [
            'skip_intent_on_affirmative_follow_up' => true,
            'skip_exact_match_on_affirmative_follow_up' => true,
            'affirmative_follow_up_max_tokens' => 140,
        ];

        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];
        $overrides = $settings['widget_rule_policy'] ?? [];
        if (!is_array($overrides)) {
            return $defaults;
        }

        $policy = $defaults;

        if (array_key_exists('skip_intent_on_affirmative_follow_up', $overrides)) {
            $policy['skip_intent_on_affirmative_follow_up'] = (bool) $overrides['skip_intent_on_affirmative_follow_up'];
        }

        if (array_key_exists('skip_exact_match_on_affirmative_follow_up', $overrides)) {
            $policy['skip_exact_match_on_affirmative_follow_up'] = (bool) $overrides['skip_exact_match_on_affirmative_follow_up'];
        }

        if (array_key_exists('affirmative_follow_up_max_tokens', $overrides) && is_numeric($overrides['affirmative_follow_up_max_tokens'])) {
            $policy['affirmative_follow_up_max_tokens'] = max(80, min(300, (int) $overrides['affirmative_follow_up_max_tokens']));
        }

        return $policy;
    }

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

    /**
     * Server-side IP geolocation proxy — avoids CSP issues when called from widget JS.
     * Calls ip-api.com from the server using the visitor's real IP.
     */
    public function geoip(Request $request): \Illuminate\Http\JsonResponse
    {
        $clientIp = $request->ip();
        // Skip lookup for private/loopback IPs (local dev)
        if (!$clientIp || filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return response()->json(['city' => '', 'region' => '', 'country' => '']);
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(3)
                ->get("http://ip-api.com/json/{$clientIp}", [
                    'fields' => 'status,country,regionName,city',
                ]);

            if ($resp->successful()) {
                $data = $resp->json();
                if (($data['status'] ?? '') === 'success') {
                    return response()->json([
                        'city'    => $data['city'] ?? '',
                        'region'  => $data['regionName'] ?? '',
                        'country' => $data['country'] ?? '',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Silently fail — widget will use timezone fallback
        }

        return response()->json(['city' => '', 'region' => '', 'country' => '']);
    }
}
