<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_trainee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('trainee_id')->constrained('trainees')->cascadeOnDelete();
            $table->enum('status', ['enrolled', 'completed', 'dropped', 'failed'])->default('enrolled');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Ensure unique combination
            $table->unique(['class_id', 'trainee_id']);
            $table->index('class_id');
            $table->index('trainee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_trainee');
    }
};

