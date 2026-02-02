<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('user_type', ['acc', 'training_center', 'instructor'])->nullable();
            $table->unsignedBigInteger('user_type_id')->nullable(); // ID of acc, training_center, or instructor
            
            // Amounts
            $table->decimal('gross_amount', 10, 2); // المبلغ الإجمالي
            $table->decimal('commission_amount', 10, 2); // مبلغ العمولة
            $table->decimal('net_amount', 10, 2); // المبلغ الصافي المراد تحويله
            
            // Stripe details
            $table->string('stripe_transfer_id')->nullable()->unique(); // معرف التحويل في Stripe
            $table->string('stripe_account_id')->nullable(); // Stripe Connect account ID للمستخدم
            
            // Status tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'retrying'])->default('pending');
            $table->integer('retry_count')->default(0); // عدد محاولات إعادة التحويل
            $table->text('error_message')->nullable(); // رسالة الخطأ في حالة الفشل
            
            // Timestamps
            $table->timestamp('processed_at')->nullable(); // وقت المعالجة
            $table->timestamp('completed_at')->nullable(); // وقت اكتمال التحويل
            $table->timestamp('failed_at')->nullable(); // وقت فشل التحويل
            $table->timestamps();
            
            // Indexes
            $table->index('transaction_id');
            $table->index('status');
            $table->index('stripe_transfer_id');
            $table->index(['user_type', 'user_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};

