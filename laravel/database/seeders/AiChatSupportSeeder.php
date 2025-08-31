<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Services\AiAgentService;

class AiChatSupportSeeder extends Seeder
{
    public function run()
    {
        // Create AI Chat Support organization
        $organization = Organization::firstOrCreate(
            ['slug' => 'ai-chat-support'],
            [
                'name' => 'AI Chat Support',
                'description' => 'AI-powered customer support solution for businesses. We provide intelligent chat widgets, automated responses, and comprehensive analytics to help businesses deliver exceptional customer service. Contact: support@ai-chat.support, Phone: 9937253528, Location: Sambalpur, India',
                'website_url' => 'https://ai-chat.support',
                'settings' => [
                    'contact_email' => 'support@ai-chat.support',
                    'contact_phone' => '9937253528',
                    'address' => 'Sambalpur, India',
                    'collection_name' => 'ai_chat_support_docs'
                ],
                'is_active' => true,
            ]
        );

        // Update organization if it already exists
        $organization->update([
            'description' => 'AI-powered customer support solution for businesses. We provide intelligent chat widgets, automated responses, and comprehensive analytics to help businesses deliver exceptional customer service. Contact: support@ai-chat.support, Phone: 9937253528, Location: Sambalpur, India',
            'website_url' => 'https://ai-chat.support',
            'settings' => [
                'contact_email' => 'support@ai-chat.support',
                'contact_phone' => '9937253528',
                'address' => 'Sambalpur, India',
                'collection_name' => 'ai_chat_support_docs'
            ]
        ]);

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
                'title' => 'Pricing and Plans',
                'content' => 'We offer flexible pricing plans to suit businesses of all sizes. Our Starter plan begins at $29/month for up to 1,000 conversations, perfect for small businesses. The Professional plan at $99/month includes up to 5,000 conversations and advanced analytics. The Enterprise plan offers unlimited conversations, priority support, and custom integrations. All plans include a 14-day free trial with no credit card required. Volume discounts are available for high-traffic websites.',
                'type' => 'pricing'
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
            ]
        ];

        // Sync to Qdrant
        try {
            $aiAgentService = app(AiAgentService::class);
            $collectionName = 'ai-chat-support';
            
            // Create collection
            $createResult = $aiAgentService->createCollection($collectionName, 768);
            
            if ($createResult) {
                $this->command->info("Created Qdrant collection: {$collectionName}");
                
                // Add each document to Qdrant
                $successCount = 0;
                foreach ($documents as $index => $document) {
                    // Generate embedding for the document content
                    $embedResult = $aiAgentService->embed($document['content']);
                    
                    if ($embedResult && isset($embedResult['embedding'])) {
                        // Add to Qdrant with the embedding
                        $addResult = $aiAgentService->addToQdrant(
                            $collectionName,
                            $embedResult['embedding'],
                            [
                                'title' => $document['title'],
                                'content' => $document['content'],
                                'type' => $document['type']
                            ],
                            'doc_' . $index
                        );
                        
                        if ($addResult) {
                            $successCount++;
                        }
                    }
                }
                
                $this->command->info("Successfully updated AI Chat Support organization and synced {$successCount}/" . count($documents) . " documents to Qdrant.");
            } else {
                $this->command->error("Organization updated but failed to create Qdrant collection");
            }
        } catch (\Exception $e) {
            $this->command->error("Organization updated but failed to sync to Qdrant: " . $e->getMessage());
        }
    }
}
