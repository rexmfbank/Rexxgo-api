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
        Schema::table('borrowers', function (Blueprint $table) {
            $table->text('kyc_link')->nullable(); 
            $table->string('tos_link')->nullable();
            $table->string('tos_status')->default('not_started');
            $table->text('rejection_reasons')->nullable();
            $table->string('bridge_customer_id')->nullable()->unique(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            //
        });
    }
};
