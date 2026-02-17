<?php
/**
 * Server Diagnostic Script
 * Run from browser: https://rozgold.techotd.in/server_diagnostic.php
 * Tests: DNS resolution, API connectivity, .env config
 * DELETE THIS FILE after debugging!
 */

// ── Password Protection ── (access: server_diagnostic.php?key=rozgold2026)
$accessKey = $_GET['key'] ?? '';
if ($accessKey !== 'rozgold2026') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Add ?key=YOUR_KEY to URL']);
    exit;
}

header('Content-Type: application/json');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
];

// ── 1. DNS Resolution Tests ──
$dns_tests = [
    'uat-api.augmontgold.com' => null,
    'api.quickekyc.com' => null,
    'sandbox.quickekyc.com' => null,
];
foreach ($dns_tests as $host => &$val) {
    $start = microtime(true);
    $ip = @gethostbyname($host);
    $elapsed = round((microtime(true) - $start) * 1000);
    $val = [
        'resolved' => ($ip !== $host),
        'ip' => ($ip !== $host) ? $ip : null,
        'time_ms' => $elapsed,
    ];
}
unset($val);
$results['dns_resolution'] = $dns_tests;

// ── 2. cURL connectivity tests ──
function test_url($url, $timeout = 10) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_NOBODY => true, // HEAD request only
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $start = microtime(true);
    curl_exec($ch);
    $elapsed = round((microtime(true) - $start) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    curl_close($ch);
    return [
        'reachable' => ($errno === 0),
        'http_code' => $httpCode,
        'time_ms' => $elapsed,
        'error' => $error ?: null,
        'curl_errno' => $errno,
    ];
}

$results['connectivity'] = [
    'augmont_uat' => test_url('https://uat-api.augmontgold.com/api/merchant/v1/auth/login'),
    'quickekyc_production' => test_url('https://api.quickekyc.com'),
    'quickekyc_sandbox' => test_url('https://sandbox.quickekyc.com'),
    'google_dns_check' => test_url('https://www.google.com', 5),
];

// ── 3. .env config check (no secrets exposed!) ──
// Bootstrap Laravel .env parsing
$envFile = __DIR__ . '/../.env';
$envConfig = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\"'");
        $envConfig[$key] = $value;
    }
}

$results['env_config'] = [
    'QUICKEKYC_ENVIRONMENT' => $envConfig['QUICKEKYC_ENVIRONMENT'] ?? 'NOT SET',
    'QUICKEKYC_API_URL' => $envConfig['QUICKEKYC_API_URL'] ?? 'NOT SET',
    'QUICKEKYC_SANDBOX_URL' => $envConfig['QUICKEKYC_SANDBOX_URL'] ?? 'NOT SET',
    'QUICKEKYC_API_KEY_set' => !empty($envConfig['QUICKEKYC_API_KEY']),
    'QUICKEKYC_SANDBOX_API_KEY_set' => !empty($envConfig['QUICKEKYC_SANDBOX_API_KEY']),
    'AUGMONT_API_URL' => $envConfig['AUGMONT_API_URL'] ?? 'NOT SET',
    'AUGMONT_EMAIL_set' => !empty($envConfig['AUGMONT_EMAIL']),
    'AUGMONT_PASSWORD_set' => !empty($envConfig['AUGMONT_PASSWORD']),
    'APP_ENV' => $envConfig['APP_ENV'] ?? 'NOT SET',
    'APP_DEBUG' => $envConfig['APP_DEBUG'] ?? 'NOT SET',
];

// ── 4. Check for duplicate .env keys ──
$keyCount = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $lineNum => $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        $key = trim(explode('=', $line, 2)[0]);
        if (!isset($keyCount[$key])) $keyCount[$key] = [];
        $keyCount[$key][] = $lineNum + 1;
    }
}
$duplicates = array_filter($keyCount, fn($lines) => count($lines) > 1);
$results['duplicate_env_keys'] = $duplicates;

// ── 5. Augmont login test ──
if (!empty($envConfig['AUGMONT_EMAIL']) && !empty($envConfig['AUGMONT_PASSWORD'])) {
    $augmontUrl = ($envConfig['AUGMONT_API_URL'] ?? 'https://uat-api.augmontgold.com/api/merchant/v1') . '/auth/login';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $augmontUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $envConfig['AUGMONT_EMAIL'],
            'password' => $envConfig['AUGMONT_PASSWORD'],
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $start = microtime(true);
    $resp = curl_exec($ch);
    $elapsed = round((microtime(true) - $start) * 1000);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $results['augmont_login_test'] = [
        'url' => $augmontUrl,
        'http_code' => $httpCode,
        'time_ms' => $elapsed,
        'error' => $error ?: null,
        'has_token' => str_contains($resp ?: '', 'accessToken'),
        'response_preview' => substr(preg_replace('/"accessToken"\s*:\s*"[^"]+"/', '"accessToken":"***MASKED***"', $resp ?: ''), 0, 300),
    ];
} else {
    $results['augmont_login_test'] = ['error' => 'Augmont credentials not configured in .env'];
}

// ── 6. QuickeKYC connectivity test ──
$kycEnv = $envConfig['QUICKEKYC_ENVIRONMENT'] ?? 'sandbox';
$kycUrl = ($kycEnv === 'sandbox')
    ? ($envConfig['QUICKEKYC_SANDBOX_URL'] ?? 'https://sandbox.quickekyc.com')
    : ($envConfig['QUICKEKYC_API_URL'] ?? 'https://api.quickekyc.com');

$results['quickekyc_test'] = [
    'environment_used' => $kycEnv,
    'url_used' => $kycUrl,
    'connectivity' => test_url($kycUrl),
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
