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
        Schema::table('gold_transactions', function (Blueprint $table) {
            $table->string('unique_id')->nullable()->after('user_id')->comment('Augmont user unique ID');
            $table->string('metal_type', 10)->nullable()->after('transaction_type')->comment('gold or silver');
            $table->string('block_id')->nullable()->after('total_amount')->comment('Augmont rate block ID');
            $table->string('source', 20)->default('augmont')->after('status')->comment('augmont, manual, etc.');
            $table->string('mode_of_payment', 50)->nullable()->after('payment_method')->comment('Online, Bank Transfer, etc.');
            $table->text('augmont_response')->nullable()->after('invoice_data')->comment('Raw Augmont API response JSON');
            $table->string('reference_type', 50)->nullable()->after('augmont_response');
            $table->string('reference_id', 100)->nullable()->after('reference_type');
            $table->string('payment_reference', 100)->nullable()->after('payment_status')->comment('Payment gateway reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gold_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'unique_id',
                'metal_type',
                'block_id',
                'source',
                'mode_of_payment',
                'augmont_response',
                'reference_type',
                'reference_id',
                'payment_reference',
            ]);
        });
    }
};
