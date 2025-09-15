<?php
/**
 * Test script to measure AI widget performance
 */

// Test widget endpoint directly with organization ID
$orgId = 'ai-chat-support'; // Using the organization slug
$url = "https://ai-chat.support/widget/{$orgId}/chat";

// Test data
$testData = [
    'message' => 'What are your pricing plans?',
    'name' => 'Test User',
    'email' => 'test@example.com',
    'phone' => '+1234567890'
];

echo "Testing AI Widget Performance...\n";
echo "Message: " . $testData['message'] . "\n";
echo "Organization: " . $orgId . "\n";
echo "URL: " . $url . "\n";
echo "Starting test at: " . date('Y-m-d H:i:s') . "\n\n";

$startTime = microtime(true);

// Initialize cURL
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minute timeout
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'X-Requested-With: XMLHttpRequest'
]);

// Execute request
echo "Sending request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

$endTime = microtime(true);
$responseTime = round($endTime - $startTime, 2);

curl_close($ch);

echo "Response received at: " . date('Y-m-d H:i:s') . "\n";
echo "Response time: {$responseTime} seconds\n";
echo "HTTP Code: {$httpCode}\n";

if ($error) {
    echo "cURL Error: {$error}\n";
}

if ($response) {
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse) {
        echo "\nResponse Status: " . ($jsonResponse['success'] ?? 'unknown') . "\n";
        if (isset($jsonResponse['message'])) {
            echo "Response Message Length: " . strlen($jsonResponse['message']) . " characters\n";
            echo "Response Preview: " . substr($jsonResponse['message'], 0, 200) . "...\n";
        }
        if (isset($jsonResponse['error'])) {
            echo "Error: " . $jsonResponse['error'] . "\n";
        }
    } else {
        echo "Raw Response: " . substr($response, 0, 500) . "\n";
    }
} else {
    echo "No response received\n";
}

echo "\n" . str_repeat('=', 50) . "\n";

// Performance analysis
if ($responseTime < 5) {
    echo "✅ EXCELLENT: Response time under 5 seconds\n";
} elseif ($responseTime < 15) {
    echo "✅ GOOD: Response time under 15 seconds\n";
} elseif ($responseTime < 30) {
    echo "⚠️  ACCEPTABLE: Response time under 30 seconds\n";
} else {
    echo "❌ POOR: Response time over 30 seconds - needs optimization\n";
}