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
        Schema::table('savings_products', function (Blueprint $table) {
            $table->decimal('developer_fee_percent', 5, 2)->default(0.00)->after('product_name');
            $table->string('bridge_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings_products', function (Blueprint $table) {
            //
        });
    }
};
