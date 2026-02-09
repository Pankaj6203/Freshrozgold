<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_banks')) {
            Schema::table('user_banks', function (Blueprint $table) {
                if (!Schema::hasColumn('user_banks', 'is_verified')) {
                    $table->boolean('is_verified')->default(false)->after('status');
                }
                if (!Schema::hasColumn('user_banks', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('is_verified');
                }
                if (!Schema::hasColumn('user_banks', 'name_at_bank')) {
                    $table->string('name_at_bank', 150)->nullable()->after('verified_at')
                        ->comment('Account holder name returned by bank');
                }
                if (!Schema::hasColumn('user_banks', 'verification_status')) {
                    $table->string('verification_status', 20)->nullable()->after('name_at_bank')
                        ->comment('VALID, INVALID, ERROR, PENDING');
                }
                if (!Schema::hasColumn('user_banks', 'verification_ref')) {
                    $table->string('verification_ref', 100)->nullable()->after('verification_status')
                        ->comment('Cashfree reference ID');
                }
                if (!Schema::hasColumn('user_banks', 'verification_utr')) {
                    $table->string('verification_utr', 100)->nullable()->after('verification_ref')
                        ->comment('Penny drop UTR number');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_banks')) {
            Schema::table('user_banks', function (Blueprint $table) {
                $columns = ['is_verified', 'verified_at', 'name_at_bank', 'verification_status', 'verification_ref', 'verification_utr'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('user_banks', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
