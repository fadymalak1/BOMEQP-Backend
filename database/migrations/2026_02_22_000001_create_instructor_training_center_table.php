<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot: instructors can be linked to multiple training centers.
     * When a training center "adds" an existing instructor (by email), we attach here.
     */
    public function up(): void
    {
        Schema::create('instructor_training_center', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('instructors')->cascadeOnDelete();
            $table->foreignId('training_center_id')->constrained('training_centers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['instructor_id', 'training_center_id'], 'instructor_tc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_training_center');
    }
};
