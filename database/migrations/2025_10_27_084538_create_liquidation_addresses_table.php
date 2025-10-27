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
        Schema::create('liquidation_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_liquidation_id'); 
            $table->string('chain');
            $table->int('savings_id');
            $table->string('address');
            $table->string('currency');
            $table->string('customer_id');
            $table->string('destination_payment_rail');
            $table->string('destination_currency');
            $table->string('destination_address');
            $table->string('state'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquidation_addresses');
    }
};
