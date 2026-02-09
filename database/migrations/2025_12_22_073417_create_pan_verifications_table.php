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
        Schema::create('pan_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 15)->index();
            $table->string('pan_number', 10)->unique();
            $table->string('name')->nullable();
            $table->string('name_on_card')->nullable();
            $table->string('father_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('status', ['valid', 'invalid', 'pending'])->default('pending');
            $table->enum('category', ['individual', 'company', 'firm', 'trust', 'other'])->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('verification_id')->nullable();
            $table->text('masked_image_url')->nullable(); // PAN card masked image
            $table->json('api_response')->nullable(); // Full API response
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pan_verifications');
    }
};
