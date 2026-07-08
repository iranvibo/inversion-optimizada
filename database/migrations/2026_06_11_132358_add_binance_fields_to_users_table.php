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
        Schema::table('users', function (Blueprint $table) {
            $table->text('binance_api_key')->nullable();
            $table->text('binance_secret_key')->nullable();
            $table->boolean('binance_verified')->default(false);
            $table->boolean('bot_active')->default(false);
            $table->string('bot_mode')->default('simulation');
            $table->string('risk_level')->default('balanceado');
            $table->boolean('binance_withdrawal_alert')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'binance_api_key',
                'binance_secret_key',
                'binance_verified',
                'bot_active',
                'bot_mode',
                'risk_level',
                'binance_withdrawal_alert',
            ]);
        });
    }
};
