<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Services\AiAgentService;

class AiChatSupportSeeder extends Seeder
{
    public function run()
    {
        // Use the AI Chat Support organization (main platform website)
        $organization = Organization::where('slug', 'ai-chat-support')->first();
        
        if (!$organization) {
            $this->command->error("AI Chat Support organization not found! Please create it first.");
            return;
        }

        // Update organization description with full details
        $organization->update([
            'description' => 'AI-powered customer support solution for businesses. We provide intelligent chat widgets, automated responses, and comprehensive analytics to help businesses deliver exceptional customer service. Contact: support@ai-chat.support, Phone: 9937253528, Location: Sambalpur, India',
            'contact_email' => 'support@ai-chat.support',
            'contact_phone' => '9937253528',
            'settings' => array_merge($organization->settings ?? [], [
                'address' => 'Sambalpur, India'
            ])
        ]);
        
        $this->command->info("Using organization: {$organization->name} (ID: {$organization->id}, Slug: {$organization->slug})");

        // AI Chat Support FAQ and knowledge base documents
        $documents = [
            [
                'title' => 'What is AI Chat Support?',
                'content' => 'AI Chat Support is a revolutionary customer service platform that uses artificial intelligence to provide instant, accurate responses to customer inquiries 24/7. Our AI-powered chat widgets integrate seamlessly with your website, helping you deliver exceptional customer experiences while reducing response times and support costs. The platform can handle multiple conversations simultaneously, learn from interactions, and escalate complex issues to human agents when needed.',
                'type' => 'general_info'
            ],
            [
                'title' => 'Getting Started with AI Chat Support',
                'content' => 'Setting up AI Chat Support is quick and easy. First, create your account and organization profile. Then, customize your chat widget appearance and behavior through our intuitive dashboard. Add your business information, FAQs, and knowledge base content to train the AI. Finally, copy the widget code and paste it into your website. Most businesses are up and running within 24 hours. Our support team is available to help you through the setup process.',
                'type' => 'setup_guide'
            ],
            [
                'title' => 'Features and Capabilities',
                'content' => 'AI Chat Support offers comprehensive features including: 24/7 automated customer support, intelligent response generation, conversation analytics and reporting, seamless human handoff, multi-language support, customizable chat widget design, integration with popular CRM systems, real-time conversation monitoring, lead capture and qualification, and detailed performance metrics. The platform continuously learns from interactions to improve response accuracy over time.',
                'type' => 'features'
            ],
            [
                'title' => 'Integration and Setup',
                'content' => 'AI Chat Support integrates with popular platforms including WordPress, Shopify, Magento, Wix, Squarespace, and custom websites. Integration is as simple as copying and pasting a small JavaScript code snippet. The widget is mobile-responsive and works across all devices and browsers. We also offer API integration for advanced users and custom implementations. Technical documentation and step-by-step guides are available in our help center.',
                'type' => 'integration'
            ],
            [
                'title' => 'Customer Support and Help',
                'content' => 'Our customer support team is available Monday through Friday, 9 AM to 6 PM EST. You can reach us via email at support@ai-chat.support, through our live chat on the website, or by phone at 9937253528. We also maintain a comprehensive help center with tutorials, guides, and FAQs. For urgent technical issues, we offer priority support to Pro and Enterprise customers. Our community forum allows users to share tips and best practices.',
                'type' => 'support'
            ],
            [
                'title' => 'Analytics and Reporting',
                'content' => 'Track your chat performance with detailed analytics including conversation volume, response times, customer satisfaction scores, common inquiry topics, and resolution rates. Monitor AI accuracy and identify areas for improvement. Export data for further analysis or integration with business intelligence tools. Real-time dashboards provide instant insights into customer interactions and support team performance. Custom reports can be scheduled and automatically delivered to stakeholders.',
                'type' => 'analytics'
            ],
            [
                'title' => 'Security and Privacy',
                'content' => 'We take security seriously with enterprise-grade encryption, secure data centers, regular security audits, and compliance with GDPR, CCPA, and SOC 2 Type II standards. All conversations are encrypted in transit and at rest. We never sell or share customer data with third parties. Customers have full control over their data and can request deletion at any time. Our privacy policy clearly outlines how we collect, use, and protect information.',
                'type' => 'security'
            ],
            [
                'title' => 'How accurate is AI Chat Support?',
                'content' => 'Accuracy improves over time as you add FAQs, business info, and real chat feedback. Many customers see high accuracy within the first week, and with continuous tuning it can reach 95–99% on common queries. We provide tools to review chat history, add missing answers, and improve responses quickly.',
                'type' => 'accuracy'
            ],
            [
                'title' => 'How can I improve AI responses?',
                'content' => 'You can improve responses by adding clear FAQs, service descriptions, and policies, and by reviewing chat history to fill gaps. Adding synonyms and common user phrases helps the AI match intent more accurately. We also support quick updates that sync to the knowledge base instantly.',
                'type' => 'improvement'
            ],
            [
                'title' => 'What data should I add first?',
                'content' => 'Start with FAQs, pricing policies, services or product summaries, and key policies like returns, warranty, and shipping. These cover most customer questions and immediately boost accuracy.',
                'type' => 'onboarding'
            ],
            [
                'title' => 'Does the AI learn from live chats?',
                'content' => 'The AI uses your knowledge base and policies as the primary source. You can review chat transcripts and add missing answers to improve future responses. This creates a continuous improvement loop with full control by your team.',
                'type' => 'learning'
            ],
            [
                'title' => 'How fast is the response time?',
                'content' => 'Most responses are delivered in a few seconds, and streaming replies make the response visible quickly. Speed depends on the model used and the complexity of the question. Faster models can be selected for latency-sensitive use cases.',
                'type' => 'performance'
            ],
            [
                'title' => 'Can I customize the AI behavior?',
                'content' => 'Yes. You can customize the assistant name, tone, welcome message, widget design, and the business data it uses. You can also configure intent keywords to improve routing for your industry.',
                'type' => 'customization'
            ],
            [
                'title' => 'What if the AI gives a wrong answer?',
                'content' => 'You can review chat logs, correct or add missing FAQs, and the AI will use the updated knowledge instantly. This prevents repeated mistakes and steadily improves answer quality.',
                'type' => 'quality'
            ],
            [
                'title' => 'Do you support multiple languages?',
                'content' => 'Yes, AI Chat Support can respond in multiple languages depending on the model used. You can add FAQs in multiple languages for best accuracy.',
                'type' => 'languages'
            ]
        ];

        // Sync to Qdrant
        try {
            $aiAgentService = app(AiAgentService::class);
            $collectionName = $organization->slug; // Use organization slug as collection name
            
            // Create collection if it doesn't exist
            $createResult = $aiAgentService->createCollection($collectionName, 768);

            if ($createResult) {
                $this->command->info("Created/verified Qdrant collection: {$collectionName}");
            } else {
                $this->command->warn("Qdrant collection create returned no response; continuing to upsert documents (collection may already exist).");
            }

            // Add each document to Qdrant
            $successCount = 0;
            foreach ($documents as $index => $document) {
                // Generate embedding for the document content
                    $embedResult = $aiAgentService->embed($document['content']);

                    if ($embedResult && is_array($embedResult)) {
                    // Add to Qdrant with the embedding
                    $addResult = $aiAgentService->addToQdrant(
                        $collectionName,
                            $embedResult,
                        [
                            'title' => $document['title'],
                            'content' => $document['content'],
                            'type' => $document['type'],
                            'organization_slug' => $organization->slug,
                            'data_type' => 'info'
                        ]
                    );

                    if ($addResult) {
                        $successCount++;
                    }
                }
            }

            $this->command->info("Successfully synced {$successCount}/" . count($documents) . " documents to Qdrant collection '{$collectionName}'.");
        } catch (\Exception $e) {
            $this->command->error("Failed to sync to Qdrant: " . $e->getMessage());
        }
    }
}
