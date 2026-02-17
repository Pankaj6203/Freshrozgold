<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$aadhaar = App\Models\AadhaarVerification::latest()->first();
$pan = App\Models\PanVerification::latest()->first();

echo "=== AADHAAR ===\n";
if ($aadhaar) {
    echo "Name: " . $aadhaar->name . "\n";
    echo "DOB (raw): " . var_export($aadhaar->date_of_birth, true) . "\n";
    echo "DOB (json): " . json_encode($aadhaar->date_of_birth) . "\n";
    echo "Mobile: " . $aadhaar->mobile . "\n";
} else {
    echo "No Aadhaar record found\n";
}

echo "\n=== PAN ===\n";
if ($pan) {
    echo "Name: " . $pan->name . "\n";
    echo "DOB (raw): " . var_export($pan->date_of_birth, true) . "\n";
    echo "DOB (json): " . json_encode($pan->date_of_birth) . "\n";
    echo "PAN: " . $pan->pan_number . "\n";
} else {
    echo "No PAN record found\n";
}

echo "\n=== KYC STATUS RESPONSE (simulated) ===\n";
$mobile = $aadhaar ? $aadhaar->mobile : ($pan ? $pan->mobile : null);
if ($mobile) {
    echo "Mobile: $mobile\n";
    $aadhaarData = $aadhaar ? [
        'name' => $aadhaar->name,
        'dob' => $aadhaar->date_of_birth,
    ] : null;
    echo "aadhaar_data.dob = " . json_encode($aadhaarData['dob'] ?? null) . "\n";
}
