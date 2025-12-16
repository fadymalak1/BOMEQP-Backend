<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acc_id')->constrained('accs')->cascadeOnDelete();
            $table->enum('document_type', ['license', 'registration', 'certificate', 'other']);
            $table->string('document_url');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acc_documents');
    }
};

