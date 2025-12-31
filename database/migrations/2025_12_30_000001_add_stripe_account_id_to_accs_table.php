<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('commission_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->dropColumn('stripe_account_id');
        });
    }
};

