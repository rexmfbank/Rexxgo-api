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
            $table->string('currency')->nullable()->after('bank_name');
            $table->string('routing_number')->nullable()->after('currency');
            $table->string('sort_code')->nullable()->after('routing_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->dropColumn(['currency', 'routing_number', 'sort_code']);
        });
    }
};
