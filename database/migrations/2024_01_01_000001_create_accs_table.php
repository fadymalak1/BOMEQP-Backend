<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name');
            $table->string('registration_number')->unique();
            $table->string('country');
            $table->text('address');
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('website')->nullable();
            $table->string('logo_url')->nullable();
            $table->enum('status', ['pending', 'active', 'suspended', 'expired'])->default('pending');
            $table->boolean('registration_fee_paid')->default(false);
            $table->decimal('registration_fee_amount', 10, 2)->nullable();
            $table->timestamp('registration_paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accs');
    }
};

