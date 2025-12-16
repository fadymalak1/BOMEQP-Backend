<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->enum('discount_type', ['time_limited', 'quantity_based']);
            $table->decimal('discount_percentage', 5, 2);
            $table->json('applicable_course_ids')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('total_quantity')->nullable();
            $table->integer('used_quantity')->default(0);
            $table->enum('status', ['active', 'expired', 'depleted', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};

