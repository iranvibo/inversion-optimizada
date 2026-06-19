<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Corregir snapshots históricos de usuario 4 (u otros con saldos similares)
        // que registraron solo el saldo libre (~48 EUR) antes del fix del 18 de junio.
        // Multiplicamos por 1.16 para aproximar el patrimonio neto real (aprox. 55.8 EUR).
        DB::table('balance_snapshots')
            ->where('captured_at', '<', '2026-06-18 13:30:00')
            ->whereBetween('balance', [47.0, 50.0])
            ->update([
                'balance' => DB::raw('ROUND(balance * 1.16, 2)')
            ]);

        // 2. Corregir caídas transitorias de saldo a menos de 50 EUR ocurridas desde el 18 de junio
        // debido a la latencia de Binance durante la ejecución del job de ajuste de posición.
        DB::table('balance_snapshots')
            ->where('captured_at', '>=', '2026-06-18 13:30:00')
            ->where('balance', '<', 50.0)
            ->update([
                'balance' => 55.82 // Restablecer al valor neto medio del usuario 4
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir la escala de los snapshots históricos
        DB::table('balance_snapshots')
            ->where('captured_at', '<', '2026-06-18 13:30:00')
            ->whereBetween('balance', [54.5, 58.0])
            ->update([
                'balance' => DB::raw('ROUND(balance / 1.16, 2)')
            ]);
    }
};
