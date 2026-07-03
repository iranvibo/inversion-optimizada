<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Códigos de invitación: el registro (contraseña o Google) sólo es válido
     * si el usuario presenta un código emitido específicamente para su email
     * a través de la API de invitaciones.
     *
     * - code_hash: SHA-256 del código normalizado. El código en claro sólo se
     *   devuelve una vez en la respuesta de la API y nunca se persiste.
     * - email: destinatario del código; el registro debe usar ese mismo email.
     * - used_by: usuario que canjeó el código. cascadeOnDelete para que el
     *   borrado de cuenta (GDPR) arrastre también el registro del canje.
     */
    public function up(): void
    {
        Schema::create('invitation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitation_codes');
    }
};
