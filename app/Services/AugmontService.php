<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AugmontService
{
    private string $apiUrl;
    private string $email;
    private string $password;
    private ?string $authToken = null;

    // DNS resolution cache (in-memory for this request lifecycle)
    private static ?string $resolvedIp = null;

    public function __construct()
    {
        $config = config('services.augmont');
        $this->apiUrl  = $config['api_url'];
        $this->email   = $config['email'];
        $this->password = $config['password'];
    }

    // ─── DNS Helper ─────────────────────────────────────────────

    /**
     * Resolve Augmont hostname to IP and cache it.
     * Shared hosting DNS resolvers are often slow/unreliable.
     * We cache the resolved IP for 30 minutes so cURL can skip DNS lookup.
     */
    private function getResolvedHost(): ?string
    {
        $host = parse_url($this->apiUrl, PHP_URL_HOST);
        if (!$host) return null;

        // Check Laravel cache first (survives across requests)
        $cacheKey = 'dns_resolve_' . $host;
        $cachedIp = Cache::get($cacheKey);
        if ($cachedIp) {
            return "{$host}:443:{$cachedIp}";
        }

        // Try to resolve via gethostbyname
        $ip = @gethostbyname($host);
        if ($ip && $ip !== $host) {
            Cache::put($cacheKey, $ip, now()->addMinutes(30));
            Log::info("DNS resolved: {$host} → {$ip} (cached 30 min)");
            return "{$host}:443:{$ip}";
        }

        // Fallback: try Google DNS via dns_get_record
        $records = @dns_get_record($host, DNS_A);
        if (!empty($records[0]['ip'])) {
            $ip = $records[0]['ip'];
            Cache::put($cacheKey, $ip, now()->addMinutes(30));
            Log::info("DNS resolved (dns_get_record): {$host} → {$ip} (cached 30 min)");
            return "{$host}:443:{$ip}";
        }

        Log::warning("DNS resolution failed for {$host}");
        return null;
    }

    /**
     * Build cURL options for DNS resolution workaround.
     */
    private function getCurlOptions(): array
    {
        $resolve = $this->getResolvedHost();
        $options = ['verify' => false];
        if ($resolve) {
            $options['curl'] = [
                CURLOPT_RESOLVE => [$resolve],
            ];
        }
        return $options;
    }

    // ─── Authentication ─────────────────────────────────────────

    public function login(): bool
    {
        if (Cache::has('augmont_auth_token')) {
            $this->authToken = Cache::get('augmont_auth_token');
            return true;
        }

        // Retry login up to 2 times on DNS/timeout failure
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $loginUrl = "{$this->apiUrl}/auth/login";
                Log::info("Augmont login attempt {$attempt}", ['url' => $loginUrl]);

                $response = Http::timeout(20)
                    ->connectTimeout(10)
                    ->withOptions($this->getCurlOptions())
                    ->post($loginUrl, [
                        'email'    => $this->email,
                        'password' => $this->password,
                    ]);

                if (!$response->successful()) {
                    Log::error('Augmont login failed', ['status' => $response->status(), 'body' => $response->body()]);
                    return false;
                }

                $data = $response->json();
                $token     = $data['result']['data']['accessToken']
                          ?? $data['data']['accessToken']
                          ?? $data['accessToken']
                          ?? null;
                $expiresIn = $data['result']['data']['expiresIn']
                          ?? $data['data']['expiresIn']
                          ?? $data['expiresIn']
                          ?? 7200;

                if (!$token) {
                    Log::error('Token not found in Augmont login response', ['response' => $data]);
                    return false;
                }

                $this->authToken = $token;
                Cache::put('augmont_auth_token', $token, now()->addSeconds((int) $expiresIn - 60));
                return true;

            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                $isDnsOrTimeout = str_contains($errorMsg, 'Could not resolve')
                    || str_contains($errorMsg, 'Resolving timed out')
                    || str_contains($errorMsg, 'cURL error 28')
                    || str_contains($errorMsg, 'cURL error 6');

                Log::error("Augmont login exception (attempt {$attempt}/2)", [
                    'error' => $errorMsg,
                    'is_dns_timeout' => $isDnsOrTimeout,
                ]);

                if ($isDnsOrTimeout && $attempt < 2) {
                    // Clear DNS cache and retry after a short delay
                    $host = parse_url($this->apiUrl, PHP_URL_HOST);
                    Cache::forget('dns_resolve_' . $host);
                    usleep(500000); // 500ms delay before retry
                    continue;
                }
                return false;
            }
        }
        return false;
    }

    // ─── Generic API Caller ─────────────────────────────────────

    public function call(string $endpoint, string $method = 'GET', array $data = [], string $bodyType = 'json'): array
    {
        if (!$this->authToken && !$this->login()) {
            return ['success' => false, 'message' => 'Augmont authentication failed', 'status_code' => 401];
        }

        // Retry on DNS/timeout (up to 2 attempts)
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $url = "{$this->apiUrl}/{$endpoint}";

            try {
                $headers = [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $this->authToken,
                ];
                if ($bodyType !== 'formdata') {
                    $headers['Content-Type'] = 'application/json';
                }

                $http = Http::withHeaders($headers)
                    ->timeout(20)
                    ->connectTimeout(10)
                    ->withOptions($this->getCurlOptions());

                $response = match (strtoupper($method)) {
                    'POST'   => $bodyType === 'formdata'
                                ? $http->asMultipart()->post($url, $data)
                                : $http->post($url, $data),
                    'PUT'    => $http->put($url, $data),
                    'DELETE' => $http->delete($url, $data),
                    default  => $http->get($url, $data),
                };

                if ($response->status() === 401) {
                    // Token expired → re-login once
                    $this->authToken = null;
                    Cache::forget('augmont_auth_token');
                    if ($this->login()) {
                        return $this->call($endpoint, $method, $data, $bodyType);
                    }
                    return ['success' => false, 'message' => 'Augmont re-authentication failed', 'status_code' => 401];
                }

                $body = $response->json();

                if ($response->successful()) {
                    return ['success' => true, 'data' => $body];
                }

                return [
                    'success'     => false,
                    'message'     => $body['message'] ?? 'Augmont API error: ' . $response->status(),
                    'data'        => $body,
                    'status_code' => $response->status(),
                ];

            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                $isDnsOrTimeout = str_contains($errorMsg, 'Could not resolve')
                    || str_contains($errorMsg, 'Resolving timed out')
                    || str_contains($errorMsg, 'cURL error 28')
                    || str_contains($errorMsg, 'cURL error 6');

                Log::error("Augmont API exception (attempt {$attempt}/2)", [
                    'endpoint' => $endpoint,
                    'error' => $errorMsg,
                    'is_dns_timeout' => $isDnsOrTimeout,
                ]);

                if ($isDnsOrTimeout && $attempt < 2) {
                    $host = parse_url($this->apiUrl, PHP_URL_HOST);
                    Cache::forget('dns_resolve_' . $host);
                    usleep(500000); // 500ms before retry
                    continue;
                }

                if (str_contains($errorMsg, 'Could not resolve') || str_contains($errorMsg, 'cURL error 6')) {
                    return ['success' => false, 'message' => 'Cannot reach Augmont server (DNS resolution failed). Check server network/DNS settings.', 'status_code' => 503];
                }
                if (str_contains($errorMsg, 'timed out') || str_contains($errorMsg, 'cURL error 28')) {
                    return ['success' => false, 'message' => 'Augmont server connection timed out. Please try again.', 'status_code' => 504];
                }
                return ['success' => false, 'message' => 'API connection failed: ' . $errorMsg, 'status_code' => 503];
            }
        }
        return ['success' => false, 'message' => 'Augmont API failed after retries', 'status_code' => 503];
    }

    // ─── Rates ──────────────────────────────────────────────────

    public function getRates(): array
    {
        return $this->call('rates');
    }

    public function getHistoricalRates(array $params = []): array
    {
        return $this->call('rolling-data', 'GET', $params);
    }

    // ─── Gold Buy / Sell ────────────────────────────────────────

    public function buyGold(array $data): array
    {
        return $this->call('buy', 'POST', $data);
    }

    public function sellGold(array $data): array
    {
        return $this->call('sell', 'POST', $data);
    }

    // ─── Users ──────────────────────────────────────────────────

    public function createUser(array $data): array
    {
        return $this->call('users', 'POST', $data);
    }

    public function updateUser(string $uniqueId, array $data): array
    {
        return $this->call("users/{$uniqueId}", 'PUT', $data);
    }

    public function getUserPassbook(string $uniqueId): array
    {
        return $this->call("users/{$uniqueId}/passbook");
    }

    // ─── User Banks ─────────────────────────────────────────────

    public function createUserBank(string $uniqueId, array $data): array
    {
        return $this->call("users/{$uniqueId}/banks", 'POST', $data);
    }

    public function getUserBanks(string $uniqueId): array
    {
        return $this->call("users/{$uniqueId}/banks");
    }

    // ─── User Addresses ─────────────────────────────────────────

    public function createUserAddress(string $uniqueId, array $data): array
    {
        return $this->call("users/{$uniqueId}/address", 'POST', $data);
    }

    public function getUserAddresses(string $uniqueId): array
    {
        return $this->call("users/{$uniqueId}/address");
    }

    public function deleteUserAddress(string $uniqueId, string $addressId): array
    {
        return $this->call("users/{$uniqueId}/address/{$addressId}", 'DELETE');
    }

    // ─── User KYC ───────────────────────────────────────────────

    public function getUserKyc(string $uniqueId): array
    {
        return $this->call("users/{$uniqueId}/kyc");
    }

    public function updateUserKyc(string $uniqueId, array $data): array
    {
        return $this->call("users/{$uniqueId}/kyc", 'POST', $data);
    }

    // ─── Fixed Deposits ─────────────────────────────────────────

    public function createFd(array $data): array
    {
        return $this->call('fd/create', 'POST', $data);
    }

    public function listFd(string $uniqueId, array $params = []): array
    {
        return $this->call("fd/{$uniqueId}/order", 'GET', $params);
    }

    public function getFdDetail(string $merchantTxnId): array
    {
        return $this->call("fd/{$merchantTxnId}/detail");
    }

    public function getFdTerms(string $schemeId, string $uniqueId): array
    {
        return $this->call("fd/terms/{$schemeId}/{$uniqueId}");
    }

    public function getFdSchemes(array $params = []): array
    {
        return $this->call('fd/schemes', 'GET', $params);
    }

    public function closeFd(string $transactionId): array
    {
        return $this->call("fd/{$transactionId}/close", 'PUT');
    }

    public function getFdPassbook(string $uniqueId): array
    {
        return $this->call("fd/{$uniqueId}/passbook");
    }

    public function fdPreOrder(array $data): array
    {
        return $this->call('fd/pre-order', 'POST', $data);
    }

    public function fdPreCloseCalc(string $transactionId): array
    {
        return $this->call("fd/{$transactionId}/pre-close", 'PUT');
    }

    public function getFdTransactions(string $transactionId): array
    {
        return $this->call("fd/{$transactionId}/transactions");
    }

    // ─── SIP ────────────────────────────────────────────────────

    public function getSipRates(): array
    {
        return $this->call('sip/rates');
    }

    // ─── Transfers ──────────────────────────────────────────────

    public function createTransfer(array $data): array
    {
        return $this->call('transfer', 'POST', $data);
    }

    public function listTransfers(string $uniqueId, array $params = []): array
    {
        return $this->call("{$uniqueId}/transfer", 'GET', $params);
    }

    public function getTransferInfo(string $txnId, string $uniqueId): array
    {
        return $this->call("transfer/{$txnId}/{$uniqueId}");
    }

    // ─── Products ───────────────────────────────────────────────

    public function listProducts(array $params = []): array
    {
        return $this->call('products', 'GET', $params);
    }

    public function getProduct(string $productId): array
    {
        return $this->call("products/{$productId}");
    }

    // ─── Orders (Redeem) ────────────────────────────────────────

    public function createOrder(array $data): array
    {
        return $this->call('order', 'POST', $data, 'formdata');
    }

    public function listOrders(string $uniqueId, array $params = []): array
    {
        return $this->call("{$uniqueId}/order", 'GET', $params);
    }

    public function getOrderInfo(string $txnId, string $uniqueId): array
    {
        return $this->call("order/{$txnId}/{$uniqueId}");
    }

    // ─── Invoices ───────────────────────────────────────────────

    public function getBuyInvoice(string $transactionId): array
    {
        return $this->call("invoice/{$transactionId}");
    }

    public function getSellInvoice(string $transactionId): array
    {
        return $this->call("invoice/sell/{$transactionId}");
    }

    public function getRedeemInvoice(string $orderId): array
    {
        return $this->call("invoice/order/{$orderId}");
    }

    // ─── Master Data ────────────────────────────────────────────

    public function getStates(array $params = []): array
    {
        return $this->call('master/states', 'GET', $params);
    }

    public function getCities(array $params = []): array
    {
        return $this->call('master/cities', 'GET', $params);
    }

    public function getBanks(array $params = []): array
    {
        return $this->call('master/banks', 'GET', $params);
    }

    // ─── Withdraw ───────────────────────────────────────────────

    public function getWithdrawInfo(string $sellTxnId, string $uniqueId): array
    {
        return $this->call("withdraw/{$sellTxnId}/{$uniqueId}");
    }

    public function updateWithdraw(string $sellTxnId, string $uniqueId, array $data): array
    {
        return $this->call("withdraw/{$sellTxnId}/{$uniqueId}", 'PUT', $data);
    }

    // ─── Balance ────────────────────────────────────────────────

    public function getBalance(string $uniqueId): array
    {
        return $this->call("users/{$uniqueId}/balance");
    }

    // ─── Buy / Sell Info ────────────────────────────────────────

    public function getBuyInfo(string $txnId, string $uniqueId): array
    {
        return $this->call("buy/{$txnId}/{$uniqueId}");
    }

    public function getSellInfo(string $txnId, string $uniqueId): array
    {
        return $this->call("sell/{$txnId}/{$uniqueId}");
    }

    public function getBuyList(string $uniqueId, array $params = []): array
    {
        return $this->call("{$uniqueId}/buy", 'GET', $params);
    }

    public function getSellList(string $uniqueId, array $params = []): array
    {
        return $this->call("{$uniqueId}/sell", 'GET', $params);
    }
}
