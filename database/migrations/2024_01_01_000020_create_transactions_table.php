<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('transaction_type', ['subscription', 'code_purchase', 'material_purchase', 'course_purchase', 'commission', 'settlement']);
            $table->enum('payer_type', ['acc', 'training_center', 'group']);
            $table->unsignedBigInteger('payer_id');
            $table->enum('payee_type', ['group', 'acc', 'instructor']);
            $table->unsignedBigInteger('payee_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('payment_method', ['wallet', 'credit_card', 'bank_transfer']);
            $table->string('payment_gateway_transaction_id')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

