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
        // Eliminar snapshots incorrectos con valores mínimos erróneos del 19 de junio de 2026
        \Illuminate\Support\Facades\DB::table('balance_snapshots')
            ->where('balance', '<', 50.0)
            ->where('captured_at', 'like', '2026-06-19%')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
