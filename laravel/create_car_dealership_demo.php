<?php

require_once 'vendor/autoload.php';

use App\Services\AiAgentService;

// Create car dealership demo data
$carDealershipData = [
    [
        'id' => 'demo_automotive_1',
        'question' => 'What car models do you have available?',
        'answer' => 'We have a wide selection of new and certified pre-owned vehicles including sedans, SUVs, trucks, and luxury cars from top brands like Toyota, Honda, Ford, BMW, and Mercedes-Benz.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ],
    [
        'id' => 'demo_automotive_2',
        'question' => 'Do you offer financing options?',
        'answer' => 'Yes! We offer competitive financing options with rates as low as 2.9% APR for qualified buyers. We work with multiple lenders to get you the best deal.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ],
    [
        'id' => 'demo_automotive_3',
        'question' => 'Can I trade in my current vehicle?',
        'answer' => 'Absolutely! We accept trade-ins and will provide you with a competitive market value assessment. Our trade-in process is quick and hassle-free.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ],
    [
        'id' => 'demo_automotive_4',
        'question' => 'Do you have certified pre-owned vehicles?',
        'answer' => 'Yes, we have an extensive selection of certified pre-owned vehicles that come with extended warranties and have passed rigorous multi-point inspections.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ],
    [
        'id' => 'demo_automotive_5',
        'question' => 'What is your warranty coverage?',
        'answer' => 'New vehicles come with full manufacturer warranty. Certified pre-owned vehicles include extended powertrain warranty up to 100,000 miles.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ],
    [
        'id' => 'demo_automotive_6',
        'question' => 'Can I schedule a test drive?',
        'answer' => 'Of course! You can schedule a test drive online or call us. We encourage test drives to help you find the perfect vehicle.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ],
    [
        'id' => 'demo_automotive_7',
        'question' => 'What are your current promotions?',
        'answer' => 'We have seasonal promotions including cash back offers, low APR financing, and lease specials. Check our website or visit our showroom for current deals.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ],
    [
        'id' => 'demo_automotive_8',
        'question' => 'Do you deliver vehicles?',
        'answer' => 'Yes, we offer home delivery service within 50 miles for your convenience. Contact us to arrange vehicle delivery.',
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties']
    ]
];

// Add feature-specific entries
$features = ['New Vehicle Sales', 'Certified Pre-Owned', 'Financing Options', 'Trade-In Services', 'Home Delivery', 'Extended Warranties'];
foreach ($features as $index => $feature) {
    $carDealershipData[] = [
        'id' => 'demo_automotive_feature_' . $index,
        'question' => "Tell me about $feature",
        'answer' => "Our $feature service at AutoMax Dealership is designed to meet your automotive needs with quality and reliability. Leading car dealership offering comprehensive vehicle sales including new cars, certified pre-owned vehicles, and exceptional customer service. Contact us to learn more about how this service can benefit you.",
        'category' => 'automotive',
        'organization' => 'AutoMax Dealership',
        'features' => $features
    ];
}

echo "Car dealership demo data created successfully!\n";
echo "Total entries: " . count($carDealershipData) . "\n";

// Initialize AI service
$aiService = new AiAgentService();
$collectionName = 'demo_automotive';

echo "Starting upload to Qdrant collection: $collectionName\n";

$successCount = 0;
$errorCount = 0;

foreach ($carDealershipData as $item) {
    try {
        // Create search content for embedding
        $searchContent = $item['question'] . ' ' . $item['answer'];
        
        // Generate embedding
        $embedding = $aiService->embed($searchContent);
        
        if ($embedding && is_array($embedding)) {
            // Upload to Qdrant
            $result = $aiService->addToQdrant($collectionName, $embedding, $item, $item['id']);
            
            if ($result) {
                echo "✓ Uploaded: {$item['id']}\n";
                $successCount++;
            } else {
                echo "✗ Failed to upload: {$item['id']}\n";
                $errorCount++;
            }
        } else {
            echo "✗ Failed to generate embedding for: {$item['id']}\n";
            $errorCount++;
        }
        
        // Small delay to avoid overwhelming the API
        usleep(100000); // 0.1 second
        
    } catch (Exception $e) {
        echo "✗ Exception for {$item['id']}: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n=== Upload Summary ===\n";
echo "Successful uploads: $successCount\n";
echo "Failed uploads: $errorCount\n";
echo "Total processed: " . count($carDealershipData) . "\n";

if ($successCount > 0) {
    echo "\n✅ Car dealership demo collection '$collectionName' created successfully!\n";
    echo "You can now test it at: https://ai-chat.support/demo/automotive\n";
} else {
    echo "\n❌ Failed to create car dealership demo collection.\n";
}