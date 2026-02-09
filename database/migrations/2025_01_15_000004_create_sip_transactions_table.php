<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sip_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sip_plan_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount_inr', 12, 2);
            $table->decimal('grams', 10, 4);
            $table->decimal('rate_per_gram', 12, 2);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('augmont_txn_id', 100)->nullable();
            $table->date('investment_date');
            $table->timestamps();

            $table->foreign('sip_plan_id')->references('id')->on('sip_plans')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['sip_plan_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sip_transactions');
    }
};
