<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            // Ensure unique combination
            $table->unique(['acc_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_category');
    }
};

