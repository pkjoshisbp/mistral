<?php

require_once 'laravel/vendor/autoload.php';

// Load Laravel app
$app = require_once 'laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Organization;

// Function to send data to FastAPI backend
function sendToFastAPI($endpoint, $data) {
    $url = 'http://localhost:8111' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => $response, 'code' => $httpCode];
}

// Get AI Chat Support organization
$org = Organization::where('name', 'AI Chat Support')->first();
if (!$org) {
    echo "AI Chat Support organization not found!\n";
    exit(1);
}

$collectionName = 'ai-chat-support';

echo "Syncing FAQ data for AI Chat Support...\n";

// Read the CSV file
$csvFile = __DIR__ . '/sample_files/ai_chat_support_faq.csv';
if (!file_exists($csvFile)) {
    echo "FAQ file not found: $csvFile\n";
    exit(1);
}

$faqs = [];
if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle);
    while (($data = fgetcsv($handle)) !== false) {
        $faqs[] = array_combine($header, $data);
    }
    fclose($handle);
}

echo "Found " . count($faqs) . " FAQ entries\n";

// Process each FAQ
$successCount = 0;
foreach ($faqs as $index => $faq) {
    $text = "Question: " . $faq['question'] . "\nAnswer: " . $faq['answer'];
    
    // Get embedding
    $embeddingResult = sendToFastAPI('/embed', [
        'text' => $text
    ]);
    
    if ($embeddingResult['code'] !== 200) {
        echo "Failed to get embedding for FAQ $index: " . $embeddingResult['response'] . "\n";
        continue;
    }
    
    $embeddingData = json_decode($embeddingResult['response'], true);
    if (!isset($embeddingData['embedding'])) {
        echo "Invalid embedding response for FAQ $index\n";
        continue;
    }
    
    $vector = $embeddingData['embedding'];
    
    // Add to Qdrant
    $addResult = sendToFastAPI('/qdrant/add', [
        'collection_name' => $collectionName,
        'vector' => $vector,
        'payload' => [
            'org_id' => $org->slug,
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'category' => $faq['category'],
            'type' => 'faq',
            'text' => $text
        ]
    ]);
    
    if ($addResult['code'] === 200) {
        $successCount++;
        echo "✓ Added FAQ $index: " . substr($faq['question'], 0, 50) . "...\n";
    } else {
        echo "✗ Failed to add FAQ $index: " . $addResult['response'] . "\n";
    }
    
    // Small delay to avoid overwhelming the API
    usleep(100000); // 0.1 seconds
}

echo "\nSync completed! Successfully added $successCount out of " . count($faqs) . " FAQs\n";

// Verify the collection
$checkResult = sendToFastAPI('/qdrant/collections', []);
if ($checkResult['code'] === 200) {
    $collections = json_decode($checkResult['response'], true);
    foreach ($collections['collections'] as $collection) {
        if ($collection['name'] === $collectionName) {
            echo "Collection '$collectionName' now has {$collection['points_count']} points\n";
            break;
        }
    }
}
