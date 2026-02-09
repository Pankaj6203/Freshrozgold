<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('aadhaar_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 15)->index();
            $table->string('aadhaar_number', 12);
            $table->string('request_id')->nullable(); // OTP request ID from API
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 6)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('masked_aadhaar_image')->nullable(); // Base64 or URL
            $table->text('profile_image')->nullable(); // Photo from Aadhaar
            $table->boolean('is_verified')->default(false);
            $table->string('verification_id')->nullable();
            $table->json('api_response')->nullable(); // Full API response
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->index(['aadhaar_number', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aadhaar_verifications');
    }
};
