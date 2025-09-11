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
            $organization = Organization::find($orgId);
            
            if (!$organization || !$organization->is_active) {
                return response()->json(['error' => 'Organization not found or inactive'], 404)
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
                5 // Get top 5 relevant results
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
                
                foreach ($orderedResults as $result) {
                    $payload = $result['payload'] ?? [];
                    $dataType = $payload['data_type'] ?? '';
                    
                    // Format context differently based on data type
                    if ($dataType === 'service') {
                        // For services, include all relevant pricing and service info
                        if (isset($payload['title'])) $context .= "Service: " . $payload['title'] . "\n";
                        if (isset($payload['content'])) $context .= "Description: " . $payload['content'] . "\n";
                        if (isset($payload['price'])) $context .= "Price: " . $payload['price'] . " " . ($payload['currency'] ?? '') . "\n";
                        if (isset($payload['duration'])) $context .= "Duration: " . $payload['duration'] . "\n";
                        if (isset($payload['requirements'])) $context .= "Requirements: " . $payload['requirements'] . "\n";
                    } else {
                        // For FAQs, keep it simple
                        $contextFields = ['title', 'content', 'category'];
                        foreach ($contextFields as $field) {
                            if (isset($payload[$field]) && is_string($payload[$field]) && !empty($payload[$field])) {
                                $context .= ucfirst($field) . ": " . $payload[$field] . "\n";
                            }
                        }
                    }
                    $context .= "\n";
                }
            }

            // Create system prompt with location awareness
            $systemPrompt = "You are a helpful customer service assistant for {$organization->name}. ";
            $systemPrompt .= "IMPORTANT: You must answer questions based ONLY on the provided context below. ";
            $systemPrompt .= "Do not make up information or provide generic responses. ";
            $systemPrompt .= "Do not assume the customer is asking about a specific service unless they explicitly mention it. ";
            $systemPrompt .= "If you don't have specific information in the context, say 'I don't have that specific information available' and offer to help in other ways.\n\n";
            
            // Add the customer's question FIRST to provide focus
            $systemPrompt .= "CUSTOMER QUESTION: \"{$message}\"\n\n";
            
            // Add location context if available
            if ($country || $region || $location) {
                $systemPrompt .= "Customer Location: ";
                if ($country) $systemPrompt .= "Country: {$country} ";
                if ($region) $systemPrompt .= "Region: {$region} ";
                if ($location) $systemPrompt .= "Location: {$location} ";
                $systemPrompt .= "\nPlease provide location-appropriate responses for pricing, availability, and services.\n\n";
            }
            
            if ($context) {
                $systemPrompt .= "RELEVANT CONTEXT (Use this information to answer the customer's question above):\n{$context}\n\n";
            }

            $systemPrompt .= "Based on the context provided, please give a direct, helpful answer to the customer's question. ";
            $systemPrompt .= "Answer only what they specifically asked about. ";
            $systemPrompt .= "Do not mention specific services unless the customer's question relates to them:";

            // Get AI response
            $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt);

            if (!$aiResponse || !isset($aiResponse['answer'])) {
                throw new \Exception('Failed to get AI response');
            }

            // Detailed logging for debugging
            Log::info('Widget AI Response Debug', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'user_message' => $message,
                'context_length' => strlen($context),
                'context_found' => !empty($context),
                'context_preview' => $context ? substr($context, 0, 300) . '...' : 'No context',
                'system_prompt_length' => strlen($systemPrompt),
                'system_prompt_preview' => substr($systemPrompt, 0, 400) . '...',
                'ai_response_length' => strlen($aiResponse['answer']),
                'ai_response_preview' => substr($aiResponse['answer'], 0, 300) . '...',
                'full_ai_response' => $aiResponse['answer']
            ]);

            // Save conversation to database
            $this->saveConversationToDatabase($organization, $sessionId, $message, $aiResponse['answer'], $allUserInfo, compact('country', 'region', 'location'));

            // Log the conversation for analytics
            Log::info('Widget chat', [
                'org_id' => $orgId,
                'session_id' => $sessionId,
                'message' => $message,
                'response' => $aiResponse['answer']
            ]);

            return response()->json([
                'response' => $aiResponse['answer'],
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
}
