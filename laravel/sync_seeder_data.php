<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$organization = App\Models\Organization::where('slug', 'platform')->first();

if (!$organization) {
    echo "❌ Platform organization not found!\n";
    exit(1);
}

echo "✓ Using organization: {$organization->name} (ID: {$organization->id})\n\n";

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
        'content' => 'We offer flexible pricing plans to suit businesses of all sizes. Our plans start at $49/month for the Starter package with 2M tokens, and $199/month for the Pro plan with 10M tokens. For Indian customers, pricing is ₹4,900/month for Starter and ₹19,900/month for Pro. WhatsApp integration service is available for $50 (₹5,000 for Indian customers). All plans include comprehensive features, analytics, and support. Enterprise solutions are available with custom pricing.',
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

$successCount = 0;
$collection = $organization->slug;

echo "→ Adding documents to Qdrant collection '{$collection}'...\n\n";

foreach ($documents as $index => $document) {
    try {
        // Get embedding from FastAPI
        $embedResponse = Http::timeout(30)->post('http://localhost:8111/embed', [
            'text' => $document['content']
        ]);
        
        if (!$embedResponse->successful()) {
            echo "❌ [{$index}] Failed to get embedding for: {$document['title']}\n";
            continue;
        }
        
        $embedding = $embedResponse->json()['embedding'];
        
        // Add to Qdrant
        $qdrantResponse = Http::timeout(30)->put("http://localhost:6333/collections/{$collection}/points", [
            'points' => [
                [
                    'id' => 1000 + $index, // Use numeric ID
                    'vector' => $embedding,
                    'payload' => [
                        'title' => $document['title'],
                        'content' => $document['content'],
                        'type' => $document['type'],
                        'data_type' => 'info',
                        'item_id' => 'doc_' . $index,
                        'organization_slug' => $organization->slug,
                        'category' => ucfirst(str_replace('_', ' ', $document['type']))
                    ]
                ]
            ]
        ]);
        
        if ($qdrantResponse->successful()) {
            echo "✓ [{$index}] Added: {$document['title']}\n";
            $successCount++;
        } else {
            echo "❌ [{$index}] Qdrant failed for: {$document['title']}\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ [{$index}] Error: {$e->getMessage()}\n";
    }
}

echo "\n✓ Successfully added {$successCount}/" . count($documents) . " documents to Qdrant!\n";

// Verify
try {
    $collectionInfo = Http::get("http://localhost:6333/collections/{$collection}")->json();
    echo "\nCollection '{$collection}' now has {$collectionInfo['result']['points_count']} points\n";
} catch (\Exception $e) {
    echo "\n⚠ Could not verify collection: {$e->getMessage()}\n";
}

echo "\n✓ Platform organization is now ready with all seeder data!\n";
