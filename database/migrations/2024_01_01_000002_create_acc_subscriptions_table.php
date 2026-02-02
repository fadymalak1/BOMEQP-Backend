<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->date('subscription_start_date');
            $table->date('subscription_end_date');
            $table->date('renewal_date');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            $table->enum('payment_method', ['credit_card', 'bank_transfer', 'wallet'])->nullable();
            $table->string('transaction_id')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_subscriptions');
    }
};

