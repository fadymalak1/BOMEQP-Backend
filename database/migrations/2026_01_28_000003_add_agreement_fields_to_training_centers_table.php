<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_centers', function (Blueprint $table) {
            // Agreement Checkboxes
            $table->boolean('agreed_to_receive_communications')->default(false)->after('how_did_you_hear_about_us');
            $table->boolean('agreed_to_terms_and_conditions')->default(false)->after('agreed_to_receive_communications');
        });
    }

    public function down(): void
    {
        Schema::table('training_centers', function (Blueprint $table) {
            $table->dropColumn([
                'agreed_to_receive_communications',
                'agreed_to_terms_and_conditions',
            ]);
        });
    }
};

