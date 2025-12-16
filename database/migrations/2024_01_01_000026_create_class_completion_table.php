<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_completion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_class_id')->unique()->constrained('training_classes')->cascadeOnDelete();
            $table->date('completed_date');
            $table->decimal('completion_rate_percentage', 5, 2);
            $table->integer('certificates_generated_count')->default(0);
            $table->foreignId('marked_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_completion');
    }
};

