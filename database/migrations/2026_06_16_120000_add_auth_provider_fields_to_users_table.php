<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Añade el soporte para inicio de sesión con Google (Firebase) y el
     * registro del consentimiento de la política de privacidad.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identificador único del usuario en Firebase/Google (null = cuenta solo con contraseña).
            $table->string('firebase_uid')->nullable()->unique()->after('email');
            // Foto de perfil obtenida desde Google.
            $table->string('avatar')->nullable()->after('firebase_uid');
            // Momento en el que el usuario aceptó la política de privacidad.
            $table->timestamp('accepted_privacy_at')->nullable()->after('avatar');
        });

        // Los usuarios que acceden con Google no tienen contraseña local.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firebase_uid', 'avatar', 'accepted_privacy_at']);
        });
    }
};
