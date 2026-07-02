<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canal de ejecución alternativo a Binance: Hyperliquid (DEX de perpetuos).
     *
     * - trading_channel: canal por el que se ejecutan las operaciones en real
     *   ('binance' por defecto, 'hyperliquid' opcional). El default rellena
     *   también las filas existentes.
     * - hyperliquid_wallet_address: dirección pública de la wallet principal
     *   (consultas de estado). Cifrada en reposo igual que las claves.
     * - hyperliquid_agent_key: clave privada de la API wallet (agente) delegada,
     *   que firma órdenes pero no puede retirar fondos. Cifrada (AES-256).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('trading_channel')->default('binance');
            $table->text('hyperliquid_wallet_address')->nullable();
            $table->text('hyperliquid_agent_key')->nullable();
            $table->boolean('hyperliquid_verified')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'trading_channel',
                'hyperliquid_wallet_address',
                'hyperliquid_agent_key',
                'hyperliquid_verified',
            ]);
        });
    }
};
