<?php

require_once 'laravel/bootstrap/app.php';

$app = require_once 'laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simulate a GET request to the customer action-manager route
$request = Illuminate\Http\Request::create('/customer/action-manager', 'GET');
$request->setUserResolver(function () {
    // Get the customer user
    return \App\Models\User::where('email', 'customer@ai-chat.support')->first();
});

try {
    $response = $kernel->handle($request);
    echo "Response Status: " . $response->getStatusCode() . PHP_EOL;
    echo "Response Headers: " . PHP_EOL;
    foreach ($response->headers->all() as $key => $values) {
        echo "  {$key}: " . implode(', ', $values) . PHP_EOL;
    }
    
    if ($response->getStatusCode() >= 400) {
        echo "Response Content: " . $response->getContent() . PHP_EOL;
    } else {
        echo "Success - Page loaded correctly" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}

$kernel->terminate($request, $response ?? new \Symfony\Component\HttpFoundation\Response());