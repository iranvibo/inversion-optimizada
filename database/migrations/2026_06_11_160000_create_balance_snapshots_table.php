<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshots del balance consolidado de la cuenta de Binance (US03):
     * cada sincronización del backend persiste un punto de la serie temporal
     * que alimenta el gráfico de evolución del dashboard.
     */
    public function up(): void
    {
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('balance', 16, 2);
            $table->timestamp('captured_at');
            $table->timestamps();

            // Índice compuesto: la consulta del dashboard siempre filtra
            // por usuario + ventana temporal y ordena por captured_at.
            $table->index(['user_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_snapshots');
    }
};
