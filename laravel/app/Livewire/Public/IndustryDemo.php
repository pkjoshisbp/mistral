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
                'title' => 'Automotive Demo - AI Sales Assistant',
                'subtitle' => 'Experience how our AI helps car buyers',
                'organization' => 'Premier Auto Sales',
                'description' => 'See how our AI assistant helps customers with vehicle information, financing options, test drive scheduling, and service bookings.',
                'sample_questions' => [
                    'Show me SUVs under $30,000',
                    'I want to schedule a test drive',
                    'What financing options do you have?',
                    'Do you have any Honda Accords?',
                    'I need to book a service appointment',
                    'What is your trade-in value for my car?'
                ],
                'features' => [
                    'Vehicle Search',
                    'Test Drive Scheduling',
                    'Financing Options',
                    'Trade-in Valuation',
                    'Service Booking',
                    'Inventory Updates'
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

            // Simple approach - just use enhancedSearch like we did before, but with proper error handling
            $aiService = app(\App\Services\AiAgentService::class);
            $collectionName = 'demo_' . $this->industry;
            
            $searchResults = $aiService->enhancedSearch($collectionName, $userMessage, 3);
            
            $context = "Relevant information:\n\n";
            if (!empty($searchResults)) {
                foreach ($searchResults as $item) {
                    $payload = $item['payload'] ?? [];
                    if (isset($payload['content']) && !empty(trim($payload['content']))) {
                        $context .= trim($payload['content']) . "\n\n";
                    }
                }
            } else {
                $context = "No specific information found in the knowledge base.";
            }
            
            $orgName = $this->selectedDemo['organization'] ?? 'this organization';
            $systemPrompt = "You are a helpful customer service AI assistant for {$orgName}. Use the provided context to answer questions accurately and helpfully. Speak as {$orgName} using 'we' and 'our'. Keep responses concise and professional.";
            
            $chatMessages = [
                ['role' => 'system', 'content' => $systemPrompt . "\n\n" . $context],
                ['role' => 'user', 'content' => $userMessage]
            ];
            
            $response = $aiService->llmChat($chatMessages, 'llama3.2:3b');
            
            if ($response && isset($response['message']['content'])) {
                $aiResponse = $response['message']['content'];
            } else {
                $aiResponse = 'We ran into an issue generating a response. Please try again.';
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
        $this->currentMessage = $question;
        $this->sendMessage();
    }

    private function generateDemoResponse($message)
    {
        // Use the real AI backend with demo collection
        try {
            $aiService = app(\App\Services\AiAgentService::class);
            $collectionName = "demo_{$this->industry}";
            
            \Log::info('Demo AI query started', [
                'industry' => $this->industry,
                'collection' => $collectionName,
                'message' => $message
            ]);
            
            // First, query the demo collection for relevant context
            $searchResults = $aiService->enhancedSearch($collectionName, $message, 3);
            
            if ($searchResults && isset($searchResults['results']) && count($searchResults['results']) > 0) {
                \Log::info('Demo search results found', [
                    'collection' => $collectionName,
                    'results_count' => count($searchResults['results'])
                ]);
                
                // Prepare context for the LLM
                $context = "";
                foreach ($searchResults['results'] as $result) {
                    $context .= $result['payload']['question'] . "\n" . $result['payload']['answer'] . "\n\n";
                }
                
                // Create messages for the LLM
                $messages = [
                    [
                        'role' => 'system',
                        'content' => "You are an AI assistant for {$this->selectedDemo['organization']}. Use the following context to answer questions accurately and helpfully. If you can't find the answer in the context, provide a helpful response asking for more details.\n\nContext:\n{$context}"
                    ],
                    [
                        'role' => 'user',
                        'content' => $message
                    ]
                ];
                
                // Query the LLM
                $response = $aiService->llmChat($messages, 'llama3.2:3b');
                
                if ($response && isset($response['message']['content'])) {
                    \Log::info('Demo AI response generated successfully', [
                        'collection' => $collectionName,
                        'response_length' => strlen($response['message']['content'])
                    ]);
                    return $response['message']['content'];
                }
            } else {
                \Log::warning('No search results found for demo query', [
                    'collection' => $collectionName,
                    'message' => $message
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::error('Demo AI query failed', [
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
            ->layout('layouts.demo');
    }
}
