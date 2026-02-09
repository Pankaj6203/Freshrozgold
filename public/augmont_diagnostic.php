<?php

/**
 * Augmont API Diagnostic Script (Public Folder Version)
 * Run this on production: https://rozgold.techotd.in/augmont_diagnostic.php
 */

// Load from parent directory (public folder pe hai, vendor parent me hai)
require __DIR__ . '/../vendor/autoload.php';

// Load .env from parent directory
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

echo "\n";
echo "==================================\n";
echo "AUGMONT API DIAGNOSTIC REPORT\n";
echo "==================================\n\n";

// Step 1: Check environment configuration
echo "1. ENVIRONMENT CONFIGURATION:\n";
echo "   - Email: " . $_ENV['AUGMONT_EMAIL'] . "\n";
echo "   - Password length: " . strlen($_ENV['AUGMONT_PASSWORD']) . " chars\n";
echo "   - Password (first 4 chars): " . substr($_ENV['AUGMONT_PASSWORD'], 0, 4) . "****\n";
echo "   - API URL: " . $_ENV['AUGMONT_API_URL'] . "\n\n";

// Step 2: Test authentication
echo "2. TESTING AUTHENTICATION:\n";

$loginUrl = $_ENV['AUGMONT_API_URL'] . '/auth/login';
echo "   - Login URL: $loginUrl\n";

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $_ENV['AUGMONT_EMAIL'],
    'password' => $_ENV['AUGMONT_PASSWORD']
]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$loginResponse = curl_exec($ch);
$loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   - HTTP Status: $loginHttpCode\n";

$loginData = json_decode($loginResponse, true);

if ($loginHttpCode == 200 && isset($loginData['result']['data']['accessToken'])) {
    $token = $loginData['result']['data']['accessToken'];
    echo "   - ✅ Authentication SUCCESSFUL\n";
    echo "   - Token length: " . strlen($token) . " chars\n";
    echo "   - Token (first 20): " . substr($token, 0, 20) . "...\n\n";
    
    // Step 3: Test rates API
    echo "3. TESTING RATES API:\n";
    $ratesUrl = $_ENV['AUGMONT_API_URL'] . '/rates';
    echo "   - Rates URL: $ratesUrl\n";
    
    $ch = curl_init($ratesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $ratesResponse = curl_exec($ch);
    $ratesHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   - HTTP Status: $ratesHttpCode\n";
    
    $ratesData = json_decode($ratesResponse, true);
    if ($ratesHttpCode == 200 && isset($ratesData['result']['data']['rates'])) {
        echo "   - ✅ Rates API WORKING\n";
        echo "   - Buy Rate: ₹" . number_format($ratesData['result']['data']['rates']['gBuy'], 2) . "\n";
        echo "   - Sell Rate: ₹" . number_format($ratesData['result']['data']['rates']['gSell'], 2) . "\n";
        echo "   - Block ID: " . $ratesData['result']['data']['blockId'] . "\n\n";
        
        $lockPrice = $ratesData['result']['data']['rates']['gBuy'];
        $blockId = $ratesData['result']['data']['blockId'];
        
        // Step 4: Test buy API
        echo "4. TESTING BUY API:\n";
        $buyUrl = $_ENV['AUGMONT_API_URL'] . '/user/buy';
        echo "   - Buy URL: $buyUrl\n";
        
        $buyData = [
            'lockPrice' => $lockPrice,
            'metalType' => 'gold',
            'merchantTransactionId' => 'DIAGNOSTIC_' . time(),
            'uniqueId' => 'DIAGNOSTIC_USER',
            'blockId' => $blockId,
            'modeOfPayment' => 'Online',
            'quantity' => 0.001 // Very small test amount
        ];
        
        $ch = curl_init($buyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($buyData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $buyResponse = curl_exec($ch);
        $buyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "   - HTTP Status: $buyHttpCode\n";
        
        $buyResponseData = json_decode($buyResponse, true);
        
        if ($buyHttpCode == 200) {
            echo "   - ✅ BUY API WORKING!\n";
            echo "   - Response: " . json_encode($buyResponseData, JSON_PRETTY_PRINT) . "\n\n";
        } elseif ($buyHttpCode == 405) {
            echo "   - ❌ BUY API RETURNS 405 (Method Not Allowed)\n";
            echo "   - Response: " . $buyResponse . "\n\n";
            echo "   🔍 ROOT CAUSE ANALYSIS:\n";
            echo "   - The /user/buy endpoint is NOT enabled for your Augmont account\n";
            echo "   - This is a merchant account configuration issue\n";
            echo "   - Your account type might be: API-only or Merchant Portal\n\n";
            echo "   📧 CONTACT AUGMONT SUPPORT:\n";
            echo "   - Email: support@augmont.com\n";
            echo "   - Request: Enable /user/buy and /user/sell API endpoints\n";
            echo "   - Your Account: " . $_ENV['AUGMONT_EMAIL'] . "\n";
            echo "   - Tell them: 'We need direct buy/sell API access, currently getting 405'\n\n";
        } else {
            echo "   - ❌ BUY API ERROR: $buyHttpCode\n";
            echo "   - Response: " . $buyResponse . "\n\n";
        }
        
        // Step 5: Test sell API
        echo "5. TESTING SELL API:\n";
        $sellUrl = $_ENV['AUGMONT_API_URL'] . '/user/sell';
        echo "   - Sell URL: $sellUrl\n";
        
        $sellData = [
            'lockPrice' => $ratesData['result']['data']['rates']['gSell'],
            'metalType' => 'gold',
            'merchantTransactionId' => 'DIAGNOSTIC_SELL_' . time(),
            'uniqueId' => 'DIAGNOSTIC_USER',
            'blockId' => $blockId,
            'quantity' => 0.001
        ];
        
        $ch = curl_init($sellUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($sellData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $sellResponse = curl_exec($ch);
        $sellHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "   - HTTP Status: $sellHttpCode\n";
        
        if ($sellHttpCode == 200) {
            echo "   - ✅ SELL API WORKING!\n\n";
        } elseif ($sellHttpCode == 405) {
            echo "   - ❌ SELL API RETURNS 405 (Method Not Allowed)\n";
            echo "   - Same issue as buy API - contact Augmont to enable\n\n";
        } else {
            echo "   - ❌ SELL API ERROR: $sellHttpCode\n";
            echo "   - Response: " . $sellResponse . "\n\n";
        }
        
    } else {
        echo "   - ❌ Rates API FAILED\n";
        echo "   - Response: " . $ratesResponse . "\n\n";
    }
    
} else {
    echo "   - ❌ Authentication FAILED\n";
    echo "   - HTTP Code: $loginHttpCode\n";
    echo "   - Response: " . $loginResponse . "\n\n";
    
    if (strpos($loginResponse, 'Unauthorized') !== false) {
        echo "   🔍 DIAGNOSIS: Wrong credentials\n";
        echo "   - Check AUGMONT_EMAIL and AUGMONT_PASSWORD in .env\n";
        echo "   - Password should be wrapped in single quotes\n";
        echo "   - Current: AUGMONT_PASSWORD='" . $_ENV['AUGMONT_PASSWORD'] . "'\n\n";
    }
}

echo "==================================\n";
echo "DIAGNOSTIC COMPLETE\n";
echo "==================================\n\n";

// Check if this is UAT or Production
if (strpos($_ENV['AUGMONT_API_URL'], 'uat-api') !== false) {
    echo "⚠️  NOTE: You're using UAT environment\n";
    echo "   - UAT has limited API access\n";
    echo "   - Production might have different endpoints available\n";
    echo "   - Contact Augmont to check production API access\n\n";
}

echo "📝 NEXT STEPS:\n";
echo "   1. If authentication works but buy/sell returns 405:\n";
echo "      → Contact Augmont support to enable API endpoints\n\n";
echo "   2. If authentication fails:\n";
echo "      → Check .env password format: AUGMONT_PASSWORD='password'\n";
echo "      → Verify credentials with Augmont\n\n";
echo "   3. If everything works:\n";
echo "      → Deploy updated GoldController.php to production\n";
echo "      → Clear config cache: php artisan config:clear\n\n";

?>
