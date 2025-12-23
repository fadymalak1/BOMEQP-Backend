<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_center_id')->constrained('training_centers')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('id_number')->unique();
            $table->string('id_image_url')->nullable();
            $table->string('card_image_url')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();

            // Index for faster lookups
            $table->index('training_center_id');
            $table->index('email');
            $table->index('id_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainees');
    }
};

