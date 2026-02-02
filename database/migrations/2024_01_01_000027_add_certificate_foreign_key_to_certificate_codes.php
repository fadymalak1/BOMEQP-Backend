<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_codes', function (Blueprint $table) {
            $table->foreign('used_for_certificate_id')->references('id')->on('certificates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_codes', function (Blueprint $table) {
            $table->dropForeign(['used_for_certificate_id']);
        });
    }
};

