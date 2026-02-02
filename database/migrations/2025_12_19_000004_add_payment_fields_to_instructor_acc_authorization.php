<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_acc_authorization', function (Blueprint $table) {
            $table->decimal('authorization_price', 10, 2)->nullable()->after('commission_percentage');
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->after('authorization_price');
            $table->timestamp('payment_date')->nullable()->after('payment_status');
            $table->string('payment_transaction_id')->nullable()->after('payment_date');
            $table->enum('group_admin_status', ['pending', 'commission_set', 'completed'])->default('pending')->after('status');
            $table->foreignId('group_commission_set_by')->nullable()->constrained('users')->nullOnDelete()->after('group_admin_status');
            $table->timestamp('group_commission_set_at')->nullable()->after('group_commission_set_by');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_acc_authorization', function (Blueprint $table) {
            $table->dropForeign(['group_commission_set_by']);
            $table->dropColumn([
                'authorization_price',
                'payment_status',
                'payment_date',
                'payment_transaction_id',
                'group_admin_status',
                'group_commission_set_by',
                'group_commission_set_at',
            ]);
        });
    }
};

