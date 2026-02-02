<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('code_batches', function (Blueprint $table) {
            // Add course_id to track which course the codes are for
            $table->foreignId('course_id')->nullable()->after('acc_id')->constrained('courses')->nullOnDelete();
            
            // Add payment status field (pending, approved, rejected, completed)
            $table->enum('payment_status', ['pending', 'approved', 'rejected', 'completed'])->default('completed')->after('payment_method');
            
            // Add manual payment fields
            $table->string('payment_receipt_url')->nullable()->after('payment_status');
            $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_receipt_url');
            
            // Add verification fields
            $table->foreignId('verified_by')->nullable()->after('payment_amount')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('rejection_reason')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('code_batches', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['course_id']);
            $table->dropColumn([
                'course_id',
                'payment_status',
                'payment_receipt_url',
                'payment_amount',
                'verified_by',
                'verified_at',
                'rejection_reason'
            ]);
        });
    }
};

