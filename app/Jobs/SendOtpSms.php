<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOtpSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying.
     */
    public int $backoff = 5;

    public function __construct(
        private string $mobile,
        private string $otp
    ) {}

    public function handle(): void
    {
        $config = config('services.smsmenow');
        $jsessionId = config('services.smsmenow.jsessionid', '039173125348E90D79475DE345B52C29');

        // Format mobile number
        $formatted = $this->mobile;
        if (!str_starts_with($this->mobile, '+')) {
            if (strlen($this->mobile) === 10 && preg_match('/^[6-9]\d{9}$/', $this->mobile)) {
                $formatted = '+91' . $this->mobile;
            }
        }

        // Build SMS message
        $message = str_replace(
            ['{otp}', '{validity}'],
            [$this->otp, config('otp.validity_minutes')],
            config('otp.sms_template')
        );

        // Build URL
        $url = $config['url'] . '?' . http_build_query([
            'user'     => $config['user'],
            'password' => $config['password'],
            'senderid' => $config['senderid'],
            'mobiles'  => $formatted,
            'sms'      => $message,
        ]);

        // cURL call
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_SSL_VERIFYPEER => config('app.env') === 'production',
            CURLOPT_SSL_VERIFYHOST => config('app.env') === 'production' ? 2 : false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            Log::error("SendOtpSms cURL error #{$curlErrno}: {$curlError}", ['mobile' => $this->mobile]);
            throw new \RuntimeException("cURL Error #{$curlErrno}: {$curlError}");
        }

        if ($httpCode == 200 && $response && strpos($response, '<status>success</status>') !== false) {
            Log::info("OTP SMS sent successfully", ['mobile' => $this->mobile]);
        } else {
            Log::error("OTP SMS failed", ['mobile' => $this->mobile, 'http_code' => $httpCode, 'response' => substr($response ?? '', 0, 200)]);
            throw new \RuntimeException("SMS gateway returned HTTP {$httpCode}");
        }
    }

    /**
     * Handle a job failure (all retries exhausted).
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("OTP SMS delivery permanently failed", [
            'mobile' => $this->mobile,
            'error'  => $exception->getMessage(),
        ]);
    }
}
