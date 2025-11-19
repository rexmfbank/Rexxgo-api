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
             $table->boolean('two_fa_enabled')->default(false)->after('fcm_token');
            $table->string('two_fa_code')->nullable()->after('two_fa_enabled');
            $table->timestamp('two_fa_expires_at')->nullable()->after('two_fa_code');
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
