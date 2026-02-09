<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OTP lookups: mobile + verification status + expiry
        if (Schema::hasTable('otps')) {
            Schema::table('otps', function (Blueprint $table) {
                if (!Schema::hasIndex('otps', 'otps_mobile_verified_expires')) {
                    $table->index(['mobiles', 'is_verified', 'expires_at'], 'otps_mobile_verified_expires');
                }
            });
        }

        // Gold transaction queries: user's buy/sell history
        if (Schema::hasTable('gold_transactions')) {
            Schema::table('gold_transactions', function (Blueprint $table) {
                if (!Schema::hasIndex('gold_transactions', 'gold_txn_user_type_date')) {
                    $table->index(['user_id', 'transaction_type', 'created_at'], 'gold_txn_user_type_date');
                }
            });
        }

        // PAN verification lookups
        if (Schema::hasTable('pan_verifications')) {
            Schema::table('pan_verifications', function (Blueprint $table) {
                if (!Schema::hasIndex('pan_verifications', 'pan_number_verified')) {
                    $table->index(['pan_number', 'is_verified'], 'pan_number_verified');
                }
            });
        }

        // Aadhaar verification lookups
        if (Schema::hasTable('aadhaar_verifications')) {
            Schema::table('aadhaar_verifications', function (Blueprint $table) {
                if (!Schema::hasIndex('aadhaar_verifications', 'aadhaar_mobile_verified')) {
                    $table->index(['mobile', 'is_verified'], 'aadhaar_mobile_verified');
                }
            });
        }

        // Payment order lookups by order_id and status
        if (Schema::hasTable('payment_orders')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                if (!Schema::hasIndex('payment_orders', 'payment_order_id_status')) {
                    $table->index(['order_id', 'order_status'], 'payment_order_id_status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('otps')) {
            Schema::table('otps', function (Blueprint $table) {
                $table->dropIndex('otps_mobile_verified_expires');
            });
        }

        if (Schema::hasTable('gold_transactions')) {
            Schema::table('gold_transactions', function (Blueprint $table) {
                $table->dropIndex('gold_txn_user_type_date');
            });
        }

        if (Schema::hasTable('pan_verifications')) {
            Schema::table('pan_verifications', function (Blueprint $table) {
                $table->dropIndex('pan_number_verified');
            });
        }

        if (Schema::hasTable('aadhaar_verifications')) {
            Schema::table('aadhaar_verifications', function (Blueprint $table) {
                $table->dropIndex('aadhaar_mobile_verified');
            });
        }

        if (Schema::hasTable('payment_orders')) {
            Schema::table('payment_orders', function (Blueprint $table) {
                $table->dropIndex('payment_order_id_status');
            });
        }
    }
};
