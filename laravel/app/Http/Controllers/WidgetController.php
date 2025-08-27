<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
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
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Get widget CSS
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
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Handle chat messages from widget
     */
    public function chat(Request $request, $orgId)
    {
        try {
            $organization = Organization::find($orgId);
            
            if (!$organization || !$organization->is_active) {
                return response()->json(['error' => 'Organization not found or inactive'], 404);
            }

            $message = $request->input('message');
            $sessionId = $request->input('session_id', uniqid());

            if (!$message) {
                return response()->json(['error' => 'Message is required'], 400);
            }

            // Generate embedding for the message
            $embedding = $this->aiAgentService->embed($message);

            if (!$embedding || !isset($embedding['embedding'])) {
                throw new \Exception('Failed to generate embedding');
            }

            // Search all relevant Qdrant collections for context
            $collections = [
                "org_{$orgId}_document",
                "org_{$orgId}_service",
                "org_{$orgId}_product",
                "org_{$orgId}_faq",
                "org_{$orgId}_webpage"
            ];
            $context = '';
            foreach ($collections as $collection) {
                $searchResults = $this->aiAgentService->searchQdrant(
                    $collection,
                    $embedding['embedding'],
                    3
                );
                \Log::debug('Qdrant search results for ' . $collection, ['searchResults' => $searchResults]);
                if ($searchResults && isset($searchResults['results'])) {
                    foreach ($searchResults['results'] as $result) {
                        $payload = $result['payload'] ?? [];
                        \Log::debug('Qdrant result payload', ['collection' => $collection, 'payload' => $payload]);
                        // Aggregate all available fields for context
                        foreach ($payload as $key => $value) {
                            \Log::debug('Payload field', ['key' => $key, 'value' => $value, 'type' => gettype($value)]);
                            if (is_string($value) && !empty($value)) {
                                $context .= ucfirst($key) . ": " . $value . "\n";
                            }
                        }
                        \Log::debug('Context after payload aggregation', ['context' => $context]);
                        $context .= "\n";
                    }
                }
                \Log::debug('Context after collection', ['collection' => $collection, 'context' => $context]);
            }

            // Create system prompt
            $systemPrompt = "You are a helpful customer service assistant for {$organization->name}. ";
            $systemPrompt .= "Answer questions based on the provided context. Be friendly, helpful, and concise. ";
            $systemPrompt .= "If you don't have specific information, politely say so and offer to help in other ways.\n\n";
            
            if ($context) {
                $systemPrompt .= "Context:\n{$context}\n\n";
            }

            $systemPrompt .= "Customer Question: {$message}\n\nPlease provide a helpful response:";

            // Get AI response
            $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt);

            if (!$aiResponse || !isset($aiResponse['answer'])) {
                throw new \Exception('Failed to get AI response');
            }

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
            ]);

        } catch (\Exception $e) {
            Log::error('Widget chat error', [
                'org_id' => $orgId,
                'error' => $e->getMessage(),
                'message' => $request->input('message')
            ]);

            return response()->json([
                'response' => 'I apologize, but I\'m experiencing technical difficulties. Please try again later or contact support.',
                'error' => true
            ], 500);
        }
    }

    /**
     * Get widget configuration
     */
    public function getConfig($orgId)
    {
        $organization = Organization::find($orgId);
        
        if (!$organization || !$organization->is_active) {
            return response()->json(['error' => 'Organization not found or inactive'], 404);
        }

        return response()->json([
            'name' => $organization->name,
            'welcomeMessage' => $organization->settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'theme' => $organization->settings['widget_theme'] ?? 'default',
            'position' => $organization->settings['widget_position'] ?? 'bottom-right',
            'primaryColor' => $organization->settings['primary_color'] ?? '#007bff'
        ]);
    }
}
