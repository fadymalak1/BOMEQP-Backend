<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->decimal('base_price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('group_commission_percentage', 5, 2)->default(0);
            $table->decimal('training_center_commission_percentage', 5, 2)->default(0);
            $table->decimal('instructor_commission_percentage', 5, 2)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_pricing');
    }
};

