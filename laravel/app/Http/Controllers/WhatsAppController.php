<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppController extends Controller
{
    private $aiAgentService;

    public function __construct(AiAgentService $aiAgentService)
    {
        $this->aiAgentService = $aiAgentService;
    }

    /**
     * Verify WhatsApp webhook
     */
    public function verifyWebhook(Request $request)
    {
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        
        // Check if the verify token matches any organization's expected token
        if ($verifyToken && str_starts_with($verifyToken, 'ai_chat_support_')) {
            Log::info('WhatsApp webhook verification successful', ['token' => $verifyToken]);
            return response($challenge, 200);
        }
        
        Log::warning('WhatsApp webhook verification failed', ['token' => $verifyToken]);
        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp messages
     */
    public function handleWebhook(Request $request)
    {
        try {
            $data = $request->all();
            Log::info('WhatsApp webhook received', $data);

            // Parse WhatsApp webhook data
            if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
                $change = $data['entry'][0]['changes'][0]['value'];
                $message = $change['messages'][0];
                $contact = $change['contacts'][0] ?? [];
                
                $phoneNumberId = $change['metadata']['phone_number_id'];
                $fromNumber = $message['from'];
                $messageText = $message['text']['body'] ?? '';
                $messageId = $message['id'];
                
                // Find organization by phone number ID
                $organization = $this->findOrganizationByPhoneId($phoneNumberId);
                
                if (!$organization) {
                    Log::warning('Organization not found for phone number ID', ['phone_id' => $phoneNumberId]);
                    return response('OK', 200);
                }

                // Process the message with AI
                $aiResponse = $this->processMessageWithAI($messageText, $organization);
                
                // Send response back to WhatsApp
                $this->sendWhatsAppMessage($phoneNumberId, $fromNumber, $aiResponse, $organization);
                
                // Log the conversation
                Log::info('WhatsApp conversation processed', [
                    'org_id' => $organization->id,
                    'from' => $fromNumber,
                    'message' => $messageText,
                    'response' => $aiResponse
                ]);
            }

            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Find organization by WhatsApp phone number ID
     */
    private function findOrganizationByPhoneId($phoneNumberId)
    {
        // For now, we'll use a simple mapping
        // In production, this would be stored in organization settings
        $phoneMapping = [
            // Add your phone number ID mappings here
            // 'phone_number_id' => organization_id
        ];
        
        if (isset($phoneMapping[$phoneNumberId])) {
            return Organization::find($phoneMapping[$phoneNumberId]);
        }
        
        // Fallback to default organization (ID 3 - AI Chat Support)
        return Organization::find(3);
    }

    /**
     * Process message with AI
     */
    private function processMessageWithAI($message, $organization)
    {
        try {
            // Generate embedding for the message
            $embedding = $this->aiAgentService->embed($message);

            if (!$embedding || !is_array($embedding)) {
                throw new \Exception('Failed to generate embedding');
            }

            // Search organization's Qdrant collection for context
            $collectionName = $organization->slug;
            
            $searchResults = $this->aiAgentService->searchQdrant(
                $collectionName,
                $embedding,
                5
            );
            
            $context = '';
            if ($searchResults && isset($searchResults['results'])) {
                foreach ($searchResults['results'] as $result) {
                    $payload = $result['payload'] ?? [];
                    foreach ($payload as $key => $value) {
                        if (is_string($value) && !empty($value) && $key !== 'org_id') {
                            $context .= ucfirst($key) . ": " . $value . "\n";
                        }
                    }
                    $context .= "\n";
                }
            }

            // Create system prompt for WhatsApp
            $systemPrompt = "You are a helpful WhatsApp assistant for {$organization->name}. ";
            $systemPrompt .= "Keep responses concise and friendly for WhatsApp format. ";
            $systemPrompt .= "Use emojis appropriately. Answer based on the provided context.\n\n";
            
            if ($context) {
                $systemPrompt .= "Context:\n{$context}\n\n";
            }

            $systemPrompt .= "WhatsApp Message: {$message}\n\nPlease provide a helpful response:";

            // Get AI response
            $aiResponse = $this->aiAgentService->llmAnswer($systemPrompt);

            if (!$aiResponse || !isset($aiResponse['answer'])) {
                throw new \Exception('Failed to get AI response');
            }

            return $aiResponse['answer'];

        } catch (\Exception $e) {
            Log::error('WhatsApp AI processing error', [
                'error' => $e->getMessage(),
                'organization' => $organization->id
            ]);
            
            return "I apologize, but I'm experiencing technical difficulties right now. Please try again later or contact our support team. 🤖";
        }
    }

    /**
     * Send message to WhatsApp
     */
    private function sendWhatsAppMessage($phoneNumberId, $toNumber, $message, $organization)
    {
        try {
            // Get access token from organization settings
            $accessToken = $organization->settings['whatsapp_access_token'] ?? null;
            
            if (!$accessToken) {
                Log::warning('WhatsApp access token not configured', ['org_id' => $organization->id]);
                return false;
            }

            $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
            
            $response = Http::withToken($accessToken)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $toNumber,
                    'text' => [
                        'body' => $message
                    ]
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'to' => $toNumber,
                    'message_id' => $response->json('messages.0.id')
                ]);
                return true;
            } else {
                Log::error('WhatsApp message send failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('WhatsApp send message error', [
                'error' => $e->getMessage(),
                'to' => $toNumber
            ]);
            return false;
        }
    }
}
