<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            // Stripe Connect Status and Details
            $table->enum('stripe_connect_status', ['pending', 'connected', 'failed', 'inactive', 'updating'])->nullable()->after('stripe_account_id');
            $table->string('stripe_onboarding_url')->nullable()->after('stripe_connect_status');
            $table->boolean('stripe_onboarding_completed')->default(false)->after('stripe_onboarding_url');
            $table->timestamp('stripe_onboarding_completed_at')->nullable()->after('stripe_onboarding_completed');
            $table->json('stripe_requirements')->nullable()->after('stripe_onboarding_completed_at'); // Requirements from Stripe
            
            // Admin tracking
            $table->foreignId('stripe_connected_by_admin')->nullable()->after('stripe_requirements')->constrained('users')->nullOnDelete();
            $table->timestamp('stripe_connected_at')->nullable()->after('stripe_connected_by_admin');
            $table->timestamp('stripe_last_status_check_at')->nullable()->after('stripe_connected_at');
            $table->text('stripe_last_error_message')->nullable()->after('stripe_last_status_check_at');
        });
    }

    public function down(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->dropForeign(['stripe_connected_by_admin']);
            $table->dropColumn([
                'stripe_connect_status',
                'stripe_onboarding_url',
                'stripe_onboarding_completed',
                'stripe_onboarding_completed_at',
                'stripe_requirements',
                'stripe_connected_by_admin',
                'stripe_connected_at',
                'stripe_last_status_check_at',
                'stripe_last_error_message',
            ]);
        });
    }
};

