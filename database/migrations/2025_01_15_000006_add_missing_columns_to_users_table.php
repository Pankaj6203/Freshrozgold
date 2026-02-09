<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing columns to users table that exist in old system but not in Laravel.
     * Old system uses: unique_id, mobile, pan, aadhaar, kyc_status, augmont_user_id
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'unique_id')) {
                $table->string('unique_id', 30)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile', 15)->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'pan')) {
                $table->string('pan', 10)->nullable()->after('mobile');
            }
            if (!Schema::hasColumn('users', 'aadhaar')) {
                $table->string('aadhaar', 12)->nullable()->after('pan');
            }
            if (!Schema::hasColumn('users', 'kyc_status')) {
                $table->enum('kyc_status', ['pending', 'verified', 'rejected'])->default('pending')->after('aadhaar');
            }
            if (!Schema::hasColumn('users', 'kyc_verified_at')) {
                $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');
            }
            if (!Schema::hasColumn('users', 'augmont_user_id')) {
                $table->string('augmont_user_id', 100)->nullable()->after('kyc_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = ['unique_id', 'mobile', 'pan', 'aadhaar', 'kyc_status', 'kyc_verified_at', 'augmont_user_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
