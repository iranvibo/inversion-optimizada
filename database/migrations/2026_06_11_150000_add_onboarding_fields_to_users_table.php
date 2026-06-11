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
            // Capital estimado por el usuario en el onboarding (US02 - Escenario 3)
            $table->decimal('estimated_capital', 12, 2)->nullable();
            // Marca de finalización del onboarding interactivo
            $table->timestamp('onboarding_completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['estimated_capital', 'onboarding_completed_at']);
        });
    }
};
