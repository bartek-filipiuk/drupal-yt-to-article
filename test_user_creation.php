<?php

use Drupal\yt_to_article_admin\Service\ApiUserService;

// Bootstrap Drupal
$autoloader = require_once 'web/autoload.php';
$kernel = \Drupal\Core\DrupalKernel::createFromRequest(\Symfony\Component\HttpFoundation\Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$kernel->preHandle(\Symfony\Component\HttpFoundation\Request::createFromGlobals());

// Get the API user service
$apiUserService = \Drupal::service('yt_to_article_admin.api_user_service');

// Test creating a user
echo "Testing user creation through Drupal service...\n";

$result = $apiUserService->createUser(
    'drupal.test@example.com',
    'drupaltest789',
    NULL, // Let the service auto-generate external_user_id
    100,  // Initial credits
    25.00 // Initial balance
);

if ($result) {
    echo "✓ User created successfully!\n";
    echo "  - ID: " . $result['id'] . "\n";
    echo "  - Username: " . $result['username'] . "\n";
    echo "  - Email: " . $result['email'] . "\n";
    echo "  - External ID: " . $result['external_user_id'] . "\n";
    
    // Now test updating credits
    echo "\nTesting credit update...\n";
    $billing = $apiUserService->updateUserCredits($result['id'], 200);
    if ($billing) {
        echo "✓ Credits updated to: " . $billing['credits'] . "\n";
    } else {
        echo "✗ Failed to update credits\n";
    }
    
    // Test updating balance
    echo "\nTesting balance update...\n";
    $billing = $apiUserService->updateUserBalance($result['id'], 50.00);
    if ($billing) {
        echo "✓ Balance updated to: $" . number_format($billing['balance'], 2) . "\n";
    } else {
        echo "✗ Failed to update balance\n";
    }
    
    // Test creating a token
    echo "\nTesting token creation...\n";
    $token = $apiUserService->createUserToken($result['id'], 'Test API Key', 'premium', false);
    if ($token) {
        echo "✓ Token created successfully!\n";
        echo "  - Token: " . $token['token'] . "\n";
        echo "  - Name: " . $token['name'] . "\n";
        echo "  - Tier: " . $token['tier'] . "\n";
    } else {
        echo "✗ Failed to create token\n";
    }
    
} else {
    echo "✗ Failed to create user\n";
}

echo "\nAll tests completed!\n";