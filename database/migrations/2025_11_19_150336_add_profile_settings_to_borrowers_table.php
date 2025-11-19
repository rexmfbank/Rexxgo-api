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
            $table->boolean('show_account_balance')->default(true);
            $table->boolean('allow_notification')->default(true);
            $table->boolean('updates_notification')->default(true);
            $table->boolean('balance_changes_notification')->default(true);
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
