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
        Schema::table('savings', function (Blueprint $table) {
            $table->string("bank_address")->nullable();
            $table->string("bank_routing_number")->nullable();
            $table->string("bank_account_number")->nullable();
            $table->string("bank_beneficiary_name")->nullable();
            $table->string("bank_beneficiary_address")->nullable();
            $table->string("payment_rail")->nullable();
            $table->string("payment_rails")->nullable();
            $table->string("wallet_destination")->nullable();
            $table->string("destination_id")->nullable();
            $table->string("destination_address")->nullable();
            $table->string("destination_currency")->nullable();
            $table->string("destination_rail")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            //
        });
    }
};
