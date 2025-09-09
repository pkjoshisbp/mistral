<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
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

            // Log lead capture if provided
            if (!empty($allUserInfo) && isset($allUserInfo['name'])) {
                Log::info('Lead captured via widget', [
                    'org_id' => $orgId,
                    'session_id' => $sessionId,
                    'user_info' => $allUserInfo,
                    'location' => compact('country', 'region', 'location')
                ]);
            }

            // Generate embedding for the message
            $embedding = $this->aiAgentService->embed($message);

            if (!$embedding || !is_array($embedding)) {
                throw new \Exception('Failed to generate embedding');
            }

            // Search organization's Qdrant collection for context
            $collectionName = $organization->slug; // Use organization slug directly
            
            $searchResults = $this->aiAgentService->searchQdrant(
                $collectionName,
                $embedding, // embedding is already the array
                5 // Get top 5 relevant results
            );
            
            $context = '';
            if ($searchResults && isset($searchResults['results'])) {
                foreach ($searchResults['results'] as $result) {
                    $payload = $result['payload'] ?? [];
                    // Aggregate all available fields for context
                    foreach ($payload as $key => $value) {
                        if (is_string($value) && !empty($value) && $key !== 'org_id') {
                            $context .= ucfirst($key) . ": " . $value . "\n";
                        }
                    }
                    $context .= "\n";
                }
            }

            // Create system prompt with location awareness
            $systemPrompt = "You are a helpful customer service assistant for {$organization->name}. ";
            $systemPrompt .= "Answer questions based on the provided context. Be friendly, helpful, and concise. ";
            $systemPrompt .= "If you don't have specific information, politely say so and offer to help in other ways.\n\n";
            
            // Add location context if available
            if ($country || $region || $location) {
                $systemPrompt .= "Customer Location: ";
                if ($country) $systemPrompt .= "Country: {$country} ";
                if ($region) $systemPrompt .= "Region: {$region} ";
                if ($location) $systemPrompt .= "Location: {$location} ";
                $systemPrompt .= "\nPlease provide location-appropriate responses for pricing, availability, and services.\n\n";
            }
            
            if ($context) {
                $systemPrompt .= "Context:\n{$context}\n\n";
            }

            $systemPrompt .= "Customer Question: {$message}\n\nPlease provide a helpful response:";

            // Get AI response
            $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt);

            if (!$aiResponse || !isset($aiResponse['answer'])) {
                throw new \Exception('Failed to get AI response');
            }

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
