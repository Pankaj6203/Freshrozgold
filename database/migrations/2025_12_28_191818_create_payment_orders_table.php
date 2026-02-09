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
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique(); // Our internal order ID
            $table->string('cf_order_id')->nullable(); // Cashfree order ID
            $table->decimal('order_amount', 10, 2);
            $table->string('order_currency', 3)->default('INR');
            $table->enum('order_status', ['ACTIVE', 'PAID', 'EXPIRED', 'CANCELLED', 'FAILED'])->default('ACTIVE');
            $table->string('payment_session_id')->nullable();
            
            // Customer details
            $table->string('customer_id');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            
            // Additional data
            $table->text('order_note')->nullable();
            $table->json('payment_details')->nullable(); // Store payment response
            $table->json('api_response')->nullable(); // Full API response
            
            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['customer_id', 'order_status']);
            $table->index('order_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
    }
};
