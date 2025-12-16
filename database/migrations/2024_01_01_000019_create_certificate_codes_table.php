<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('batch_id')->constrained('code_batches')->cascadeOnDelete();
            $table->foreignId('training_center_id')->constrained('training_centers')->cascadeOnDelete();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->decimal('purchased_price', 10, 2);
            $table->boolean('discount_applied')->default(false);
            $table->foreignId('discount_code_id')->nullable()->constrained('discount_codes')->nullOnDelete();
            $table->enum('status', ['available', 'used', 'expired', 'revoked'])->default('available');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_for_certificate_id')->nullable();
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_codes');
    }
};

