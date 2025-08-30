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
        Schema::table('organizations', function (Blueprint $table) {
            // Remove address-related fields - these should be managed through data entry system
            $table->dropColumn([
                'email',
                'phone', 
                'address',
                'city',
                'state',
                'country',
                'postal_code',
                'contact_person_name',
                'contact_person_title',
                'contact_person_email',
                'contact_person_phone',
                'business_hours',
                'services',
                'industry'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Restore the removed columns if rollback needed
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_title')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->text('business_hours')->nullable();
            $table->text('services')->nullable();
            $table->string('industry')->nullable();
        });
    }
};
