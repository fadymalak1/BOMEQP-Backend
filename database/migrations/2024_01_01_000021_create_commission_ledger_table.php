<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('acc_id')->nullable()->constrained('accs')->nullOnDelete();
            $table->foreignId('training_center_id')->nullable()->constrained('training_centers')->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->decimal('group_commission_amount', 10, 2)->default(0);
            $table->decimal('group_commission_percentage', 5, 2)->default(0);
            $table->decimal('acc_commission_amount', 10, 2)->nullable();
            $table->decimal('acc_commission_percentage', 5, 2)->nullable();
            $table->enum('settlement_status', ['pending', 'paid'])->default('pending');
            $table->date('settlement_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_ledger');
    }
};

