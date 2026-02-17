<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\AadhaarVerification;

class KycAadhaarOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_aadhaar_generate_unauthorized_maps_to_502()
    {
        $user = User::factory()->create();

        // Partial mock controller and bind into container
        $mock = \Mockery::mock(\App\Http\Controllers\Api\KycController::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('callAadhaarOtpAPI')
             ->once()
             ->andReturn([
                 'success' => false,
                 'http_code' => 401,
                 'error' => 'Unauthorized Access'
             ]);

        $this->app->instance(\App\Http\Controllers\Api\KycController::class, $mock);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/kyc/aadhaar/generate-otp', ['aadhaar_number' => '123412341234']);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'message' => 'Aadhaar service unauthorized. Check KYC provider API key/configuration.',
                'errors'  => null
            ]);

        $this->assertDatabaseCount('aadhaar_verifications', 0);
    }

    public function test_aadhaar_generate_provider_5xx_maps_to_503()
    {
        $user = User::factory()->create();

        $mock = \Mockery::mock(\App\Http\Controllers\Api\KycController::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('callAadhaarOtpAPI')
             ->once()
             ->andReturn([
                 'success' => false,
                 'http_code' => 500,
                 'error' => 'Internal Server Error'
             ]);

        $this->app->instance(\App\Http\Controllers\Api\KycController::class, $mock);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/kyc/aadhaar/generate-otp', ['aadhaar_number' => '123412341234']);

        $response->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Aadhaar service temporarily unavailable. Please try again later.',
                'errors'  => null
            ]);

        $this->assertDatabaseCount('aadhaar_verifications', 0);
    }

    public function test_aadhaar_generate_unauthorized_in_raw_response_maps_to_502()
    {
        $user = User::factory()->create();

        $mock = \Mockery::mock(\App\Http\Controllers\Api\KycController::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('callAadhaarOtpAPI')
             ->once()
             ->andReturn([
                 'success' => false,
                 'raw_response' => ['status_code' => 401, 'message' => 'Unauthorized Access'],
                 'error' => 'Unauthorized Access'
             ]);

        $this->app->instance(\App\Http\Controllers\Api\KycController::class, $mock);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/kyc/aadhaar/generate-otp', ['aadhaar_number' => '123412341234']);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'message' => 'Aadhaar service unauthorized. Check KYC provider API key/configuration.',
                'errors'  => null
            ]);

        $this->assertDatabaseCount('aadhaar_verifications', 0);
    }
    public function test_aadhaar_generate_success_creates_verification_and_returns_200()
    {
        $user = User::factory()->create();

        $mock = \Mockery::mock(\App\Http\Controllers\Api\KycController::class)->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('callAadhaarOtpAPI')
             ->once()
             ->andReturn([
                 'success' => true,
                 'data' => ['request_id' => 'REQ_TEST_123']
             ]);

        $this->app->instance(\App\Http\Controllers\Api\KycController::class, $mock);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/kyc/aadhaar/generate-otp', ['aadhaar_number' => '123412341234']);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['request_id', 'aadhaar_number']])
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('aadhaar_verifications', [
            'request_id' => 'REQ_TEST_123',
            'aadhaar_number' => '123412341234'
        ]);
    }
}
