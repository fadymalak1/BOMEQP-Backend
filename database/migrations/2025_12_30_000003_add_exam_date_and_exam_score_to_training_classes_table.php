<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('training_classes', function (Blueprint $table) {
            $table->date('exam_date')->nullable()->after('end_date');
            $table->decimal('exam_score', 5, 2)->nullable()->after('exam_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('training_classes', function (Blueprint $table) {
            $table->dropColumn(['exam_date', 'exam_score']);
        });
    }
};

