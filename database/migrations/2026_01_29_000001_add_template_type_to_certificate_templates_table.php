<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->enum('template_type', ['course', 'training_center', 'instructor'])->default('course')->after('acc_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_templates', function (Blueprint $table) {
            $table->dropColumn('template_type');
        });
    }
};

