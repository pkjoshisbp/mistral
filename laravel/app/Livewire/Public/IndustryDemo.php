<?php

namespace App\Livewire\Public;

use App\Models\Organization;
use Livewire\Component;
use Livewire\Attributes\On;

class IndustryDemo extends Component
{
    public $industry;
    public $messages = [];
    public $query = '';
    public $isLoading = false;
    public $demoData = [];
    public $selectedDemo = null;

    public function mount($industry = 'healthcare')
    {
        $this->industry = $industry;
        $this->loadDemoData();
        
        // Ensure selectedDemo is set before initializing chat
        if (!isset($this->selectedDemo) || empty($this->selectedDemo)) {
            $this->selectedDemo = [
                'organization' => 'AI Assistant',
                'title' => 'AI Demo',
                'subtitle' => 'Experience our AI in action'
            ];
        }
        
        $this->initializeChat();
    }

    public function loadDemoData()
    {
        // Try to load from database first
        try {
            $demoOrg = \App\Models\DemoOrganization::where('industry', $this->industry)->where('is_active', true)->first();
            
            if ($demoOrg) {
                $this->selectedDemo = [
                    'title' => $demoOrg->name . ' - AI Demo',
                    'subtitle' => 'Experience our AI in action',
                    'organization' => $demoOrg->name,
                    'description' => $demoOrg->description,
                    'features' => $demoOrg->features ?: [],
                    'sample_questions' => $demoOrg->sample_questions ?: []
                ];
                return;
            }
        } catch (\Exception $e) {
            \Log::error('IndustryDemo loadDemoData error: ' . $e->getMessage());
        }
        
        // Fallback to hardcoded data
        $this->demoData = [
            'healthcare' => [
                'title' => 'Healthcare Demo - AI Patient Support',
                'subtitle' => 'Experience how our AI helps patients 24/7',
                'organization' => 'City General Hospital',
                'description' => 'See how our AI assistant helps patients with appointment scheduling, symptom triage, insurance questions, and general medical inquiries.',
                'sample_questions' => [
                    'I need to schedule an appointment with Dr. Smith',
                    'What are your visiting hours?',
                    'Do you accept Blue Cross insurance?',
                    'I have chest pain, what should I do?',
                    'How do I get my test results?',
                    'What departments do you have?'
                ],
                'features' => [
                    'Appointment Scheduling',
                    'Symptom Triage',
                    'Insurance Verification',
                    'Department Information',
                    'Emergency Guidance',
                    'Test Results Access'
                ]
            ],
            'education' => [
                'title' => 'Education Demo - AI Student Support',
                'subtitle' => 'Experience how our AI helps students and parents',
                'organization' => 'Metro University',
                'description' => 'See how our AI assistant helps students with admissions, course information, campus services, and academic support.',
                'sample_questions' => [
                    'What are the admission requirements?',
                    'How do I apply for financial aid?',
                    'What programs do you offer?',
                    'Where is the library located?',
                    'How do I register for classes?',
                    'What are the tuition fees?'
                ],
                'features' => [
                    'Admission Guidance',
                    'Financial Aid Support',
                    'Course Information',
                    'Campus Navigation',
                    'Registration Help',
                    'Academic Resources'
                ]
            ],
            'automotive' => [
                'title' => 'Car Dealership Demo - AI Sales Assistant',
                'subtitle' => 'Experience how our AI helps car buyers find their perfect vehicle',
                'organization' => 'AutoMax Dealership',
                'description' => 'Full-service automotive repair and maintenance center with certified technicians and state-of-the-art equipment.',
                'sample_questions' => [
                    'What car models do you have available?',
                    'Do you offer financing options?',
                    'Can I trade in my current vehicle?',
                    'Do you have certified pre-owned vehicles?',
                    'Can I schedule a test drive?',
                    'What are your current promotions?'
                ],
                'features' => [
                    'New Vehicle Sales',
                    'Certified Pre-Owned',
                    'Financing Options',
                    'Trade-In Services',
                    'Home Delivery',
                    'Extended Warranties'
                ]
            ],
            'ecommerce' => [
                'title' => 'E-commerce Demo - AI Shopping Assistant',
                'subtitle' => 'Experience how our AI helps online shoppers',
                'organization' => 'Online Store Pro',
                'description' => 'See how our AI assistant helps customers find products, track orders, handle returns, and provide personalized recommendations.',
                'sample_questions' => [
                    'I\'m looking for a laptop under $800',
                    'Where is my order #12345?',
                    'How do I return this item?',
                    'Do you have any discounts available?',
                    'What\'s your shipping policy?',
                    'Can you recommend a phone case?'
                ],
                'features' => [
                    'Product Search',
                    'Order Tracking',
                    'Return Processing',
                    'Discount Information',
                    'Shipping Details',
                    'Product Recommendations'
                ]
            ],
            'hospitality' => [
                'title' => 'Hotel Demo - AI Guest Services',
                'subtitle' => 'Experience how our AI helps hotel guests',
                'organization' => 'Grand Plaza Hotel',
                'description' => 'See how our AI assistant helps guests with reservations, amenities information, local recommendations, and service requests.',
                'sample_questions' => [
                    'I want to book a room for this weekend',
                    'What amenities do you have?',
                    'Can you recommend local restaurants?',
                    'How do I get to the airport?',
                    'I need extra towels in room 205',
                    'What time is checkout?'
                ],
                'features' => [
                    'Room Reservations',
                    'Amenities Information',
                    'Local Recommendations',
                    'Transportation Help',
                    'Room Service Requests',
                    'Hotel Policies'
                ]
            ],
            'realestate' => [
                'title' => 'Real Estate Demo - AI Property Assistant',
                'subtitle' => 'Experience how our AI helps property seekers',
                'organization' => 'Prime Realty Group',
                'description' => 'See how our AI assistant helps clients with property searches, market information, viewing appointments, and mortgage guidance.',
                'sample_questions' => [
                    'Show me 3-bedroom houses under $400,000',
                    'I want to schedule a property viewing',
                    'What\'s the market value of my home?',
                    'Can you help me with mortgage information?',
                    'Are there good schools in this area?',
                    'What properties do you have in downtown?'
                ],
                'features' => [
                    'Property Search',
                    'Viewing Appointments',
                    'Market Analysis',
                    'Mortgage Guidance',
                    'Neighborhood Information',
                    'Investment Advice'
                ]
            ]
        ];

        if (!isset($this->selectedDemo)) {
            $this->selectedDemo = $this->demoData[$this->industry] ?? $this->demoData['healthcare'];
        }
    }

    public function initializeChat()
    {
        $orgName = $this->selectedDemo['organization'] ?? 'AI Assistant';
        $this->messages = [
            [
                'role' => 'assistant',
                'content' => "Hello! I'm the AI assistant for {$orgName}. How can I help you today?",
                'timestamp' => now()
            ]
        ];
    }

    public function sendMessage()
    {
        if (empty($this->query)) {
            return;
        }

        try {
            $this->isLoading = true;
            $userMessage = $this->query;
            $this->query = '';

            // Add user message to chat immediately
            $this->messages[] = [
                'role' => 'user',
                'content' => $userMessage,
                'timestamp' => now(),
            ];

            // Use enhanced search with actions for live data integration
            $aiService = app(\App\Services\AiAgentService::class);
            $collectionName = 'demo_' . $this->industry;
            
            // Try to find organization for this demo to get live data actions
            $organizationId = null;
            try {
                $demoOrg = \App\Models\DemoOrganization::where('industry', $this->industry)->where('is_active', true)->first();
                if ($demoOrg && $demoOrg->organization_id) {
                    $organizationId = $demoOrg->organization_id;
                }
            } catch (\Exception $e) {
                // Fallback to regular search if no organization found
            }
            
            if ($organizationId) {
                // Use enhanced search with actions for live data
                $searchResults = $aiService->enhancedSearchWithActions($collectionName, $userMessage, $organizationId, 3);
            } else {
                // Fallback to regular enhanced search
                $searchResults = $aiService->enhancedSearch($collectionName, $userMessage, 3);
            }
            
            $context = "";
            if (!empty($searchResults) && isset($searchResults['results'])) {
                foreach ($searchResults['results'] as $item) {
                    $payload = $item['payload'] ?? [];
                    
                    // Extract question and answer from the search results
                    if (isset($payload['question']) && isset($payload['answer'])) {
                        $context .= "Q: {$payload['question']}\nA: {$payload['answer']}\n\n";
                    } elseif (isset($payload['content']) && !empty(trim($payload['content']))) {
                        $context .= trim($payload['content']) . "\n\n";
                    }
                }
            }
            
            if (empty($context)) {
                $context = "No specific information found in the knowledge base.";
            }
            
            $orgName = $this->selectedDemo['organization'] ?? 'this organization';
            
            // Check if using OpenAI for more concise prompts
            $aiService = app(\App\Services\AiAgentService::class);
            $isOpenAI = $aiService->isOpenAiProvider();
            
            if ($isOpenAI) {
                // Concise prompt for GPT-5-mini
                $systemPrompt = "You are {$orgName}'s AI assistant. Use only the Context below. Answer as {$orgName} with 'we/our'. Be brief and direct.";
            } else {
                // Standard prompt for Llama
                $systemPrompt = "You are {$orgName}'s AI assistant. Use provided context. Answer as {$orgName} with 'we/our'. Keep responses brief.";
            }
            
            $chatMessages = [
                ['role' => 'system', 'content' => $systemPrompt . "\n\nContext:\n" . $context],
                ['role' => 'user', 'content' => $userMessage]
            ];
            
            // Debug: Log what context is actually being sent
            \Log::info('Demo context being sent to AI', [
                'context_length' => strlen($context),
                'context_content' => $context,
                'system_message_length' => strlen($systemPrompt . "\n\nContext:\n" . $context),
                'full_system_message' => $systemPrompt . "\n\nContext:\n" . $context,
                'search_results_count' => isset($searchResults['results']) ? count($searchResults['results']) : 0,
                'user_message' => $userMessage
            ]);
            
            $response = $aiService->smartLlmChat($chatMessages);
            
            if ($response && isset($response['message']['content']) && !empty($response['message']['content'])) {
                $aiResponse = $response['message']['content'];
            } elseif (isset($searchResults) && !empty($searchResults)) {
                // Use the best search result as fallback when AI response is empty/null
                $bestResult = $searchResults[0];
                $payload = $bestResult['payload'] ?? $bestResult;
                $aiResponse = $payload['answer'] ?? $payload['content'] ?? 'I found some relevant information but need more details to provide a complete answer.';
            } else {
                // If no context, provide a helpful generic response
                $aiResponse = "I'd be happy to help you! Could you please provide more specific details about what you're looking for?";
            }
            
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $aiResponse,
                'timestamp' => now()
            ];
            
        } catch (\Exception $e) {
            \Log::error('Demo chat error: ' . $e->getMessage());
            
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'Sorry, I encountered an error. Please try again.',
                'timestamp' => now()
            ];
        } finally {
            $this->isLoading = false;
        }
    }



    public function sendSampleQuestion($question)
    {
        $this->query = $question;
        $this->sendMessage();
    }

    private function generateDemoResponse($message)
    {
        // Use the action-enhanced AI system
        try {
            $aiService = app(\App\Services\AiAgentService::class);
            $collectionName = "demo_{$this->industry}";
            
            \Log::info('Demo AI query started with action system', [
                'industry' => $this->industry,
                'collection' => $collectionName,
                'message' => $message
            ]);
            
            // Get organization ID for demo (use AI Chat Support as default)
            $organization = Organization::where('slug', 'ai-chat-support')->first();
            $organizationId = $organization ? $organization->id : 3;
            
            // Use enhanced search with actions if we have actions configured
            // Check if we have actions for this organization
            $hasActions = \App\Models\OrganizationAction::forOrganization($organizationId)
                ->active()
                ->exists();
            
            if ($hasActions) {
                \Log::info('Using action-enhanced search', [
                    'organization_id' => $organizationId,
                    'has_actions' => $hasActions
                ]);
                
                // Use action-enhanced search
                $enhancedResult = $aiService->enhancedSearchWithActions($collectionName, $message, $organizationId, 3);
                
                if ($enhancedResult) {
                    \Log::info('Enhanced result type', [
                        'type' => $enhancedResult['type'],
                        'primary_source' => $enhancedResult['primary_source'] ?? 'unknown'
                    ]);
                    
                    // Handle different result types
                    if ($enhancedResult['type'] === 'hybrid' && isset($enhancedResult['live_data'])) {
                        // Action executed successfully - combine live data with KB context
                        $liveData = $enhancedResult['live_data'];
                        $kbResults = $enhancedResult['kb_results'];
                        
                        // Prepare context from KB
                        $kbContext = "";
                        if ($kbResults && isset($kbResults['results'])) {
                            foreach ($kbResults['results'] as $result) {
                                $payload = $result['payload'] ?? [];
                                if (isset($payload['question']) && isset($payload['answer'])) {
                                    $kbContext .= "Q: {$payload['question']}\nA: {$payload['answer']}\n\n";
                                }
                            }
                        }
                        
                        $orgName = $this->selectedDemo['organization'];
                        $systemPrompt = "You are {$orgName}'s AI assistant. You have access to both live data and knowledge base information. 
                        
                        LIVE DATA (prioritize this for real-time queries):
                        {$liveData}
                        
                        KNOWLEDGE BASE (use for general information):
                        {$kbContext}
                        
                        Provide helpful answers using both sources as appropriate. Always mention when you're providing live/current data vs general information.";
                        
                        $messages = [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $message]
                        ];
                        
                        $response = $aiService->smartLlmChat($messages);
                        
                        if ($response && isset($response['message']['content'])) {
                            \Log::info('Hybrid response generated successfully', [
                                'live_data_length' => strlen($liveData),
                                'kb_context_length' => strlen($kbContext)
                            ]);
                            return $response['message']['content'];
                        }
                        
                    } elseif (in_array($enhancedResult['type'], ['fallback_to_kb', 'knowledge_base_only'])) {
                        // Use regular KB approach
                        $kbResults = $enhancedResult['kb_results'];
                        
                        if ($kbResults && isset($kbResults['results']) && count($kbResults['results']) > 0) {
                            $context = "";
                            foreach ($kbResults['results'] as $result) {
                                $payload = $result['payload'] ?? [];
                                if (isset($payload['question']) && isset($payload['answer'])) {
                                    $context .= "Q: {$payload['question']}\nA: {$payload['answer']}\n\n";
                                }
                            }
                            
                            if (!empty($context)) {
                                $messages = [
                                    [
                                        'role' => 'system',
                                        'content' => "You are {$this->selectedDemo['organization']}'s AI assistant. Use the following context to answer questions accurately.\n\nContext:\n{$context}"
                                    ],
                                    ['role' => 'user', 'content' => $message]
                                ];
                                
                                $response = $aiService->smartLlmChat($messages);
                                
                                if ($response && isset($response['message']['content'])) {
                                    return $response['message']['content'];
                                }
                            }
                        }
                    }
                }
            } else {
                // Fallback to regular enhanced search (no actions configured)
                \Log::info('No actions configured, using regular enhanced search');
                
                $searchResults = $aiService->enhancedSearch($collectionName, $message, 3);
                
                if ($searchResults && isset($searchResults['results']) && count($searchResults['results']) > 0) {
                    $context = "";
                    foreach ($searchResults['results'] as $result) {
                        $context .= $result['payload']['question'] . "\n" . $result['payload']['answer'] . "\n\n";
                    }
                    
                    $messages = [
                        [
                            'role' => 'system',
                            'content' => "You are an AI assistant for {$this->selectedDemo['organization']}. Use the following context to answer questions accurately.\n\nContext:\n{$context}"
                        ],
                        ['role' => 'user', 'content' => $message]
                    ];
                    
                    $response = $aiService->smartLlmChat($messages);
                    
                    if ($response && isset($response['message']['content'])) {
                        return $response['message']['content'];
                    }
                }
            }
            
        } catch (\Exception $e) {
            \Log::error('Demo AI query with actions failed', [
                'industry' => $this->industry,
                'message' => $message,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to hardcoded responses
        $message = strtolower($message);
        $industry = $this->industry;

        if ($industry === 'healthcare') {
            if (str_contains($message, 'appointment') || str_contains($message, 'schedule')) {
                return "I'd be happy to help you schedule an appointment! We have availability with Dr. Smith on Tuesday at 2:00 PM or Thursday at 10:30 AM. Would either of these times work for you? Please note that you'll need to provide your insurance information when you arrive.";
            } elseif (str_contains($message, 'insurance')) {
                return "We accept most major insurance plans including Blue Cross Blue Shield, Aetna, Cigna, and UnitedHealth. To verify your specific coverage, please provide your insurance card information and I'll check your benefits and copay requirements.";
            } elseif (str_contains($message, 'chest pain') || str_contains($message, 'emergency')) {
                return "⚠️ **This is a medical emergency.** Please call 911 immediately or go to the nearest emergency room. If you're experiencing chest pain, difficulty breathing, or other serious symptoms, don't wait - seek immediate medical attention.";
            } elseif (str_contains($message, 'visiting hours') || str_contains($message, 'hours')) {
                return "Our visiting hours are:\n• Monday-Friday: 10:00 AM - 8:00 PM\n• Saturday-Sunday: 12:00 PM - 6:00 PM\n• ICU: 24/7 for immediate family\n• Pediatric Ward: 9:00 AM - 9:00 PM\n\nPlease check in at the front desk and bring a valid ID.";
            }
        } elseif ($industry === 'automotive') {
            if (str_contains($message, 'suv') || str_contains($message, '30000') || str_contains($message, 'under')) {
                return "Great choice! We have several SUVs under $30,000:\n\n🚗 **2022 Honda CR-V** - $28,500\n• 28 MPG, AWD, Honda Sensing Suite\n\n🚗 **2021 Toyota RAV4** - $29,800\n• 27 MPG, All-Wheel Drive, Toyota Safety 2.0\n\n🚗 **2020 Mazda CX-5** - $26,900\n• 31 MPG, Premium Interior, i-ACTIVSENSE\n\nWould you like to schedule a test drive for any of these?";
            } elseif (str_contains($message, 'test drive') || str_contains($message, 'schedule')) {
                return "I'd love to help you schedule a test drive! 🚗\n\nAvailable times this week:\n• Tomorrow: 10:00 AM, 2:00 PM, 4:00 PM\n• Friday: 9:00 AM, 1:00 PM, 3:30 PM\n• Saturday: 10:00 AM, 12:00 PM, 2:00 PM, 4:00 PM\n\nWhich vehicle interests you most? Please bring a valid driver's license and we'll have it ready for you!";
            } elseif (str_contains($message, 'financing') || str_contains($message, 'finance')) {
                return "We offer excellent financing options! 💰\n\n**Current Rates:**\n• New Cars: Starting at 3.9% APR\n• Used Cars: Starting at 4.9% APR\n• First-time buyers: Special programs available\n\n**We work with:**\n• Banks and credit unions\n• Manufacturer financing\n• Bad credit specialists\n\nPre-approval takes just 5 minutes! What's your target monthly payment?";
            }
        } elseif ($industry === 'ecommerce') {
            if (str_contains($message, 'laptop') || str_contains($message, '800')) {
                return "Perfect! Here are our top laptops under $800:\n\n💻 **ASUS VivoBook 15** - $679\n• Intel i5, 8GB RAM, 256GB SSD\n• 15.6\" Full HD, Windows 11\n• ⭐ 4.3/5 stars (2,847 reviews)\n\n💻 **Acer Aspire 5** - $749\n• AMD Ryzen 5, 12GB RAM, 512GB SSD\n• 15.6\" IPS Display\n• ⭐ 4.1/5 stars (1,923 reviews)\n\n💻 **HP Pavilion 14** - $799\n• Intel i7, 16GB RAM, 512GB SSD\n• 14\" FHD, Ultra-portable\n• ⭐ 4.4/5 stars (3,156 reviews)\n\nFree shipping on all! Need help choosing?";
            } elseif (str_contains($message, 'order') && str_contains($message, '12345')) {
                return "Let me check order #12345 for you! 📦\n\n**Order Status: Out for Delivery**\n• Tracking: UPS1Z9876543210\n• Expected delivery: Today by 8:00 PM\n• Items: MacBook Air (Space Gray), Apple Magic Mouse\n• Delivery address: 123 Main St, Anytown\n\nYou'll receive a text when it's delivered. Need to change delivery instructions?";
            }
        }

        // Generic responses
        $genericResponses = [
            "Thank you for your question! I'm here to help with information about {$this->selectedDemo['organization']}. Could you please provide more details so I can assist you better?",
            "I understand you're looking for information. As the AI assistant for {$this->selectedDemo['organization']}, I'm here to help! What specific service or information do you need?",
            "Great question! Let me help you with that. At {$this->selectedDemo['organization']}, we're committed to providing excellent service. What would you like to know more about?",
        ];

        return $genericResponses[array_rand($genericResponses)];
    }

    public function render()
    {
        return view('livewire.public.industry-demo')
            ->layout('layouts.public');
    }
}
