<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_month', 7); // YYYY-MM format
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->decimal('total_revenue', 10, 2);
            $table->decimal('group_commission_amount', 10, 2);
            $table->enum('status', ['pending', 'requested', 'paid'])->default('pending');
            $table->timestamp('request_date')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->enum('payment_method', ['credit_card', 'bank_transfer', 'wallet'])->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_settlements');
    }
};

