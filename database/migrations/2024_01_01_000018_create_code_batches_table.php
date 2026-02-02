<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_center_id')->constrained('training_centers')->cascadeOnDelete();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['wallet', 'credit_card']);
            $table->string('transaction_id')->nullable();
            $table->timestamp('purchase_date')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_batches');
    }
};

