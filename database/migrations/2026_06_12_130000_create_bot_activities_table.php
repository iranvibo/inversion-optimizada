<?php
 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
 
return new class extends Migration
{
    /**
     * Historial de actividad y transacciones del bot (US05):
     * Permite ver un feed con explicaciones claras sobre las compras,
     * ventas, rendimientos netos y protección de riesgo.
     */
    public function up(): void
    {
        Schema::create('bot_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'buy', 'sell', 'risk_protection', etc.
            $table->string('action'); // 'buy_opportunity', 'close_profit', 'close_loss', 'stop_loss_trigger', 'withdrawal_security_trigger'
            $table->text('description')->nullable(); // descripción por defecto
            $table->decimal('profit_percentage', 5, 2)->nullable();
            $table->decimal('profit_value', 12, 2)->nullable();
            $table->boolean('risk_alert')->default(false); // destaca el evento arriba en la UI
            $table->json('raw_details')->nullable();
            $table->timestamps();
 
            // Índice compuesto para acelerar la carga del historial filtrado por usuario
            $table->index(['user_id', 'created_at']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('bot_activities');
    }
};
