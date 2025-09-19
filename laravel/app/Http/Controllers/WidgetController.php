<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Lead;
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
        $organization = Organization::find($orgId);
        
        if (!$organization || !$organization->is_active) {
            return response('Organization not found or inactive', 404);
        }

        $widgetConfig = [
            'orgId' => $orgId,
            'orgName' => $organization->name,
            'apiUrl' => config('app.url'),
            'theme' => $organization->settings['widget_theme'] ?? 'default',
            'position' => $organization->settings['widget_position'] ?? 'bottom-right',
            'primaryColor' => $organization->settings['primary_color'] ?? '#007bff',
            'welcomeMessage' => $organization->settings['welcome_message'] ?? 'Hello! How can I help you today?'
        ];

        $script = view('widget.script', compact('widgetConfig'))->render();

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Get widget CSS styles
     */
    public function getWidgetCSS($orgId)
    {
        $organization = Organization::find($orgId);
        
        if (!$organization || !$organization->is_active) {
            return response('Organization not found or inactive', 404);
        }

        $theme = [
            'primaryColor' => $organization->settings['primary_color'] ?? '#007bff',
            'secondaryColor' => $organization->settings['secondary_color'] ?? '#f8f9fa',
            'textColor' => $organization->settings['text_color'] ?? '#333333',
            'borderRadius' => $organization->settings['border_radius'] ?? '10px'
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
            
            // Merge user_info and visitor_info
            $allUserInfo = array_merge($userInfo, $visitorInfo);

            // Extract location information
            $country = $request->input('country') ?? $allUserInfo['country'] ?? null;
            $region = $request->input('region') ?? $allUserInfo['region'] ?? null;
            $location = $request->input('location') ?? $allUserInfo['location'] ?? null;

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
                
                // Save lead to database
                try {
                    Lead::create([
                        'name' => $allUserInfo['name'] ?? null,
                        'email' => $allUserInfo['email'] ?? null,
                        'phone' => $allUserInfo['phone'] ?? null,
                        'source' => 'widget',
                        'organization_id' => $orgId,
                        'session_id' => $sessionId,
                        'location_data' => json_encode(compact('country', 'region', 'location'))
                    ]);
                    Log::info('Lead saved to database', ['org_id' => $orgId, 'session_id' => $sessionId]);
                } catch (\Exception $e) {
                    Log::error('Failed to save lead to database', ['error' => $e->getMessage(), 'org_id' => $orgId]);
                }
            }

            // Search organization's Qdrant collection for context using enhanced search
            $collectionName = $organization->slug; // Use organization slug directly
            
            Log::info('Starting enhanced search', [
                'organization' => $organization->name,
                'collection' => $collectionName,
                'query' => $message
            ]);
            
            $searchResults = $this->aiAgentService->enhancedSearch(
                $collectionName,
                $message, // Use original message for rewriting
                2 // Get top 2 relevant results for faster processing
            );
            
            $context = '';
            if ($searchResults && isset($searchResults['results'])) {
                // Separate FAQ results from service results to prioritize FAQs for general questions
                $faqResults = [];
                $serviceResults = [];
                
                foreach ($searchResults['results'] as $result) {
                    $payload = $result['payload'] ?? [];
                    $dataType = $payload['data_type'] ?? '';
                    
                    if ($dataType === 'faq') {
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

            // Create concise system prompt
            $systemPrompt = "You are a helpful customer service assistant for {$organization->name}. ";
            
            // Add location context briefly if available
            if ($country || $region || $location) {
                $systemPrompt .= "Customer is in ";
                if ($country) $systemPrompt .= $country;
                if ($region) $systemPrompt .= ", {$region}";
                if ($location) $systemPrompt .= ", {$location}";
                $systemPrompt .= ". ";
            }
            
            if ($context) {
                $systemPrompt .= "Use this info:\n{$context}\n";
                $systemPrompt .= "Give a brief, helpful answer using the above information. ";
            } else {
                $systemPrompt .= "I don't have specific info for this question. ";
            }
            
            $systemPrompt .= "Keep responses concise and direct. Always use plain text only - no HTML tags, no markdown, no special formatting. If you mention any website or resource, include the full https URL as plain text. If you suggest multiple resources, present them as a simple bulleted list with each item containing only the plain URL.";

            // Get AI response using llmChat for better token tracking
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $message]
            ];
            
            // Use configured AI provider for optimal performance
            if ($this->aiAgentService->isOpenAiProvider()) {
                // Use OpenAI with configured model
                $aiResponse = $this->aiAgentService->openAiChat($messages, $this->aiAgentService->getOpenAiModel(), null, $orgId);
            } else {
                // Use local LLM with configured model
                $aiResponse = $this->aiAgentService->llmChat($messages, $this->aiAgentService->getLlamaModel(), null, $orgId);
            }

            if (!$aiResponse || !isset($aiResponse['message']['content'])) {
                // Fallback to old method
                $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt);
                $responseText = $aiResponse['answer'] ?? 'I apologize, but I\'m experiencing technical difficulties. Please try again later.';
            } else {
                $responseText = $aiResponse['message']['content'];
            }

            if (!$responseText) {
                throw new \Exception('Failed to get AI response');
            }

            // Normalize and sanitize AI response to plain text with clean URLs (no HTML)
            $responseText = $this->normalizeAiResponse($responseText);

            // Detailed logging for debugging
            Log::info('Widget AI Response Debug', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'user_message' => $message,
                'context_length' => strlen($context),
                'context_found' => !empty($context),
                'context_preview' => $context ? substr($context, 0, 300) . '...' : 'No context',
                'system_prompt_length' => strlen($systemPrompt),
                'ai_response_length' => strlen($responseText),
                'ai_response_preview' => substr($responseText, 0, 300) . '...',
                'full_ai_response' => $responseText
            ]);

            // Save conversation to database
            $this->saveConversationToDatabase($organization, $sessionId, $message, $responseText, $allUserInfo, compact('country', 'region', 'location'));

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
     * Convert HTML to plain text while preserving links as "text (url)" or just the URL.
     */
    private function htmlToPlainWithLinks(string $html): string
    {
        if ($html === '') return '';

        // Replace anchors with "text (url)" preserving either label or URL
        $html = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ($m) {
            $url = trim($m[1]);
            $text = trim(strip_tags($m[2]));
            if ($text === '' || strcasecmp($text, $url) === 0) {
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
                if ($label === '' || strcasecmp($label, $url) === 0) return $url;
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
     * Get widget configuration
     */
    public function getConfig($orgId)
    {
        $organization = Organization::find($orgId);
        
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
    private function saveConversationToDatabase($organization, $sessionId, $userMessage, $aiResponse, $userInfo = [], $locationInfo = [])
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
                    'location_info' => $locationInfo
                ]
            ]);

            // Save AI response
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'ai',
                'sender_name' => 'AI Assistant',
                'message' => $aiResponse,
                'sent_at' => now(),
                'metadata' => [
                    'session_id' => $sessionId,
                    'organization_name' => $organization->name
                ]
            ]);

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

    /**
     * Check if organization has exceeded token limits
     */
    private function checkTokenLimits($organization)
    {
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
        if (!$subscription || !$subscription->subscriptionPlan) {
            // No active subscription - could implement pay-per-use or block
            return [
                'error' => 'No active subscription found',
                'message' => 'Please subscribe to a plan to continue using AI chat.',
                'action_required' => 'subscribe',
                'upgrade_url' => config('app.url') . '/customer/subscriptions'
            ];
        }

        $tokenLimit = $subscription->subscriptionPlan->token_cap_monthly;
        $tokensUsed = $subscription->tokens_used_this_period;
        $remainingTokens = $subscription->remaining_tokens;
        $usagePercentage = $subscription->usage_percentage;

        // Estimate tokens needed for this request (rough estimate: 500-1000 tokens per chat)
        $estimatedTokensNeeded = 800;

        if ($remainingTokens <= 0) {
            // Completely over limit
            return [
                'error' => 'Token limit exceeded',
                'message' => 'You have used all ' . number_format($tokenLimit) . ' tokens in your ' . $subscription->subscriptionPlan->name . ' plan this month.',
                'usage_info' => [
                    'used' => $tokensUsed,
                    'limit' => $tokenLimit,
                    'percentage' => round($usagePercentage, 1)
                ],
                'action_required' => 'upgrade_or_wait',
                'upgrade_url' => config('app.url') . '/customer/subscriptions',
                'renewal_date' => $subscription->current_period_end ? $subscription->current_period_end->format('M j, Y') : null
            ];
        }

        if ($remainingTokens < $estimatedTokensNeeded) {
            // Likely to exceed limit with this request
            return [
                'error' => 'Insufficient tokens',
                'message' => 'You have only ' . number_format($remainingTokens) . ' tokens remaining, but this request may need up to ' . number_format($estimatedTokensNeeded) . ' tokens.',
                'usage_info' => [
                    'used' => $tokensUsed,
                    'limit' => $tokenLimit,
                    'remaining' => $remainingTokens,
                    'percentage' => round($usagePercentage, 1)
                ],
                'action_required' => 'upgrade',
                'upgrade_url' => config('app.url') . '/customer/subscriptions'
            ];
        }

        if ($usagePercentage >= 90) {
            // Warning: approaching limit, but still allow
            Log::info('User approaching token limit', [
                'user_id' => $user->id,
                'org_id' => $organization->id,
                'usage_percentage' => $usagePercentage,
                'remaining_tokens' => $remainingTokens
            ]);
        }

        return true; // All checks passed
    }
}
