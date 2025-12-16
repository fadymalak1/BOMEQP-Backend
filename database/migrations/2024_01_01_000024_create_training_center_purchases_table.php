<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_center_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_center_id')->constrained('training_centers')->cascadeOnDelete();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->enum('purchase_type', ['material', 'course', 'package']);
            $table->unsignedBigInteger('item_id');
            $table->decimal('amount', 10, 2);
            $table->decimal('group_commission_percentage', 5, 2)->default(0);
            $table->decimal('group_commission_amount', 10, 2)->default(0);
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->timestamp('purchased_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_center_purchases');
    }
};

