<?php
// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Rozgold Live Server Diagnostic ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n\n";

// Check if files exist
$files_to_check = [
    'app/Http/Controllers/Api/PaymentController.php',
    'app/Http/Controllers/Api/KycController.php',
    'app/Http/Controllers/Api/OtpController.php',
    'config/services.php',
    'routes/api.php',
    'app/Models/PaymentOrder.php'
];

echo "1. FILE EXISTENCE CHECK:\n";
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file - EXISTS (" . filesize($file) . " bytes)\n";
    } else {
        echo "   ❌ $file - MISSING\n";
    }
}

echo "\n2. CONTROLLER CLASS CHECK:\n";
$controllers = [
    'App\Http\Controllers\Api\PaymentController',
    'App\Http\Controllers\Api\KycController',
    'App\Http\Controllers\Api\OtpController'
];

foreach ($controllers as $controller) {
    if (class_exists($controller)) {
        echo "   ✅ $controller - CLASS EXISTS\n";
    } else {
        echo "   ❌ $controller - CLASS NOT FOUND\n";
    }
}

echo "\n3. NAMESPACE CHECK:\n";
try {
    $paymentController = new App\Http\Controllers\Api\PaymentController();
    echo "   ✅ PaymentController instantiation - SUCCESS\n";
} catch (Exception $e) {
    echo "   ❌ PaymentController instantiation - FAILED: " . $e->getMessage() . "\n";
}

echo "\n4. CONFIG CHECK:\n";
try {
    $cashfree = config('services.cashfree');
    echo "   ✅ Cashfree config loaded: " . (isset($cashfree['app_id']) ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "   ❌ Config error: " . $e->getMessage() . "\n";
}

echo "\n5. ROUTE CHECK:\n";
try {
    $routes = app('router')->getRoutes();
    $apiRoutes = 0;
    foreach ($routes as $route) {
        if (strpos($route->uri(), 'api/') === 0) {
            $apiRoutes++;
        }
    }
    echo "   ✅ API Routes found: $apiRoutes\n";
} catch (Exception $e) {
    echo "   ❌ Route error: " . $e->getMessage() . "\n";
}

echo "\n=== DIAGNOSTIC COMPLETE ===\n";
?>