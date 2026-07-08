<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifica los ID tokens de Firebase/Google directamente contra las claves
 * públicas de Google, sin necesidad de una cuenta de servicio.
 *
 * Un ID token de Firebase es un JWT firmado por Google (RS256). Validamos la
 * firma, el emisor y la audiencia frente al Project ID del proyecto.
 */
class FirebaseTokenVerifier
{
    /**
     * URL de los certificados públicos x509 con los que Google firma los ID tokens.
     */
    private const CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    /**
     * Verifica un ID token y devuelve sus claims (sub, email, name, picture, ...).
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException si el token es inválido o el proyecto no está configurado.
     */
    public function verify(string $idToken): array
    {
        $projectId = config('services.firebase.project_id');

        if (empty($projectId)) {
            throw new RuntimeException('Firebase no está configurado (falta FIREBASE_PROJECT_ID).');
        }

        try {
            $decoded = JWT::decode($idToken, $this->publicKeys());
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo verificar el token de Google: '.$e->getMessage(), 0, $e);
        }

        $claims = (array) $decoded;

        if (($claims['aud'] ?? null) !== $projectId) {
            throw new RuntimeException('El token no pertenece a esta aplicación.');
        }

        if (($claims['iss'] ?? null) !== 'https://securetoken.google.com/'.$projectId) {
            throw new RuntimeException('El emisor del token no es válido.');
        }

        if (empty($claims['sub'])) {
            throw new RuntimeException('El token no contiene un identificador de usuario.');
        }

        return $claims;
    }

    /**
     * Descarga (y cachea) las claves públicas de Google necesarias para validar la firma.
     *
     * @return array<string, Key>
     */
    private function publicKeys(): array
    {
        $certs = Cache::remember('firebase:google_public_keys', now()->addHour(), function () {
            $response = Http::timeout(10)->get(self::CERTS_URL);

            if ($response->failed()) {
                throw new RuntimeException('No se pudieron obtener las claves públicas de Google.');
            }

            return $response->json();
        });

        $keys = [];
        foreach ($certs as $kid => $certificate) {
            $keys[$kid] = new Key($certificate, 'RS256');
        }

        return $keys;
    }
}
