<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_course_authorization', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('instructors')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->timestamp('authorized_at')->useCurrent();
            $table->foreignId('authorized_by')->constrained('users');
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_course_authorization');
    }
};

