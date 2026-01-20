<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_connect_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('account_type', ['acc', 'training_center', 'instructor']);
            $table->unsignedBigInteger('account_id');
            $table->string('account_name'); // For easy reference
            $table->enum('action', ['initiated', 'completed', 'failed', 'updated', 'requirements_added', 'disconnected', 'retry']);
            $table->enum('status', ['success', 'failed', 'pending']);
            $table->string('stripe_connected_account_id')->nullable();
            $table->text('error_message')->nullable();
            $table->json('details')->nullable(); // Previous status, new status, changed fields, etc.
            $table->foreignId('performed_by_admin')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['account_type', 'account_id']);
            $table->index('action');
            $table->index('status');
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_connect_logs');
    }
};

