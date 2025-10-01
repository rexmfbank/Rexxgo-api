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
        // 1. Create treasury table
        Schema::create('treasury', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('currency', 10);
            $table->decimal('rates_to_dollar', 15, 6)->default(0);
            $table->enum('fee_type', ['percentage', 'fixed']);
            $table->decimal('fees_value', 15, 6)->default(0);
            $table->decimal('fees_capped_at', 20, 6)->nullable();
            $table->decimal('balance', 20, 6)->default(0);
            $table->decimal('low_threshold_limit', 20, 6)->default(0);
            $table->json('configuration')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

    

        // Update savings_transactions table
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->decimal('rates', 15, 6)->nullable()->after('receipt');
            $table->string('currency', 10)->nullable()->after('rates');
            $table->string('external_tx_id')->nullable()->after('currency');
            $table->json('external_response')->nullable()->after('external_tx_id');
            $table->text('comment')->nullable()->after('external_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop treasury table
        Schema::dropIfExists('treasury');

        // Rollback savings table
        Schema::table('savings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        // Rollback savings_transactions table
        Schema::table('savings_transactions', function (Blueprint $table) {
            $table->dropColumn(['rates', 'currency', 'external_tx_id', 'external_response', 'comment']);
        });
    }
};
