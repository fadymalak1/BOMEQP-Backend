<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('commission_amount', 10, 2)->nullable()->after('amount')->comment('Platform/Admin commission amount');
            $table->decimal('provider_amount', 10, 2)->nullable()->after('commission_amount')->comment('Provider (ACC) received amount');
            $table->string('payment_type', 50)->nullable()->after('payment_method')->comment('payment_type: destination_charge or standard');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['commission_amount', 'provider_amount', 'payment_type']);
        });
    }
};

