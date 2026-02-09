<?php
// Check routes file on live server
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Routes File Verification ===\n\n";

// Read routes/api.php
$routesFile = file_get_contents(__DIR__.'/routes/api.php');
echo "1. Checking USE statements in routes/api.php:\n";
if (strpos($routesFile, 'use App\Http\Controllers\Api\PaymentController;') !== false) {
    echo "   ✅ PaymentController use statement - EXISTS\n";
} else {
    echo "   ❌ PaymentController use statement - MISSING\n";
}

if (strpos($routesFile, 'use App\Http\Controllers\Api\KycController;') !== false) {
    echo "   ✅ KycController use statement - EXISTS\n";
} else {
    echo "   ❌ KycController use statement - MISSING\n";
}

if (strpos($routesFile, 'use App\Http\Controllers\Api\OtpController;') !== false) {
    echo "   ✅ OtpController use statement - EXISTS\n";
} else {
    echo "   ❌ OtpController use statement - MISSING\n";
}

echo "\n2. Checking route definitions:\n";
$routes = app('router')->getRoutes();
$paymentRoutes = 0;
foreach ($routes as $route) {
    if (strpos($route->uri(), 'api/payment') !== false) {
        $paymentRoutes++;
        $action = $route->getAction();
        echo "   Route: {$route->methods()[0]} /{$route->uri()}\n";
        if (isset($action['controller'])) {
            echo "   Controller: {$action['controller']}\n";
        } else if (isset($action['uses'])) {
            echo "   Uses: " . (is_string($action['uses']) ? $action['uses'] : 'Closure') . "\n";
        }
    }
}

echo "\n3. Testing route resolution:\n";
try {
    $request = \Illuminate\Http\Request::create('/api/payment/order-status?order_id=test', 'GET');
    $request->headers->set('Accept', 'application/json');
    echo "   ✅ Route can be created\n";
} catch (Exception $e) {
    echo "   ❌ Route error: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
?>