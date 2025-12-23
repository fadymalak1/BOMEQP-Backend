<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainee_training_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainee_id')->constrained('trainees')->cascadeOnDelete();
            $table->foreignId('training_class_id')->constrained('training_classes')->cascadeOnDelete();
            $table->enum('status', ['enrolled', 'completed', 'dropped', 'failed'])->default('enrolled');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Ensure unique combination
            $table->unique(['trainee_id', 'training_class_id']);
            $table->index('trainee_id');
            $table->index('training_class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainee_training_class');
    }
};

