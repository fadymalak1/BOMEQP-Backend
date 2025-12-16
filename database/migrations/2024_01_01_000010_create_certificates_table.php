<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number')->unique();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('training_center_id')->constrained('training_centers');
            $table->foreignId('instructor_id')->nullable()->constrained('instructors')->nullOnDelete();
            $table->string('trainee_name');
            $table->string('trainee_id_number')->nullable();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->foreignId('template_id')->constrained('certificate_templates');
            $table->string('certificate_pdf_url');
            $table->string('verification_code')->unique();
            $table->enum('status', ['valid', 'revoked', 'expired'])->default('valid');
            $table->foreignId('code_used_id')->nullable()->constrained('certificate_codes')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};

