<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['view', 'initiate', 'update', 'retry', 'disconnect', 'resend_link', 'view_details']);
            $table->enum('account_type', ['acc', 'training_center', 'instructor'])->nullable();
            $table->unsignedBigInteger('target_account_id')->nullable();
            $table->string('target_account_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index('admin_id');
            $table->index('action');
            $table->index('timestamp');
            $table->index(['account_type', 'target_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};

