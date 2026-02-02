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
        Schema::table('accs', function (Blueprint $table) {
            // Mailing Address fields
            $table->string('mailing_street')->nullable()->after('address');
            $table->string('mailing_city')->nullable()->after('mailing_street');
            $table->string('mailing_country')->nullable()->after('mailing_city');
            $table->string('mailing_postal_code')->nullable()->after('mailing_country');
            
            // Physical Address fields
            $table->string('physical_street')->nullable()->after('mailing_postal_code');
            $table->string('physical_city')->nullable()->after('physical_street');
            $table->string('physical_country')->nullable()->after('physical_city');
            $table->string('physical_postal_code')->nullable()->after('physical_country');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accs', function (Blueprint $table) {
            $table->dropColumn([
                'mailing_street',
                'mailing_city',
                'mailing_country',
                'mailing_postal_code',
                'physical_street',
                'physical_city',
                'physical_country',
                'physical_postal_code'
            ]);
        });
    }
};



