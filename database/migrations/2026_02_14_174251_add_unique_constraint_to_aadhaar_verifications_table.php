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
        Schema::table('aadhaar_verifications', function (Blueprint $table) {
            // Add unique constraint for verified Aadhaar numbers
            // This prevents the same Aadhaar from being verified multiple times
            $table->unique(['aadhaar_number', 'is_verified'], 'unique_verified_aadhaar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aadhaar_verifications', function (Blueprint $table) {
            $table->dropUnique('unique_verified_aadhaar');
        });
    }
};
