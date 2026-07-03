<?php

namespace App\Services;

use App\Models\User;

/**
 * Rampa de entrada/salida de fondos fiat ↔ USDC mediante el widget alojado
 * de MoonPay (on-ramp = comprar, off-ramp = vender).
 *
 * El backend NO toca dinero ni datos bancarios: su única responsabilidad es
 * construir la URL del widget con la wallet del usuario como destino y
 * firmarla con la secret key (HMAC-SHA256 sobre la query string, como exige
 * MoonPay cuando se pasa walletAddress). Sin la firma, un tercero podría
 * alterar la dirección de destino de los fondos.
 */
class MoonPayService
{
    /**
     * La rampa solo se ofrece si ambas claves del panel de MoonPay están
     * configuradas; si no, las pantallas muestran un aviso de no disponible.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('services.moonpay.api_key'))
            && ! empty(config('services.moonpay.secret_key'));
    }

    /**
     * URL firmada del widget de compra (on-ramp): el usuario paga en EUR y
     * MoonPay envía USDC directamente a su wallet (la vinculada a Hyperliquid).
     */
    public function buyUrlFor(User $user): string
    {
        return $this->signedUrl(config('services.moonpay.buy_url'), [
            'apiKey' => config('services.moonpay.api_key'),
            // currencyCode fija el activo/red: el usuario no puede elegir otro
            // por error (los depósitos a Hyperliquid llegan por esta red).
            'currencyCode' => config('services.moonpay.currency_code'),
            'walletAddress' => $user->hyperliquid_wallet_address,
            'baseCurrencyCode' => config('services.moonpay.base_currency_code'),
            'language' => 'es',
            // Permite reconciliar transacciones en el panel de MoonPay sin
            // exponer datos personales del usuario.
            'externalCustomerId' => (string) $user->id,
            'theme' => 'dark',
        ]);
    }

    /**
     * URL firmada del widget de venta (off-ramp): MoonPay convierte los USDC
     * del usuario a EUR y los envía a su cuenta bancaria (IBAN). La wallet
     * vinculada actúa como dirección de reembolso si la operación falla.
     */
    public function sellUrlFor(User $user): string
    {
        return $this->signedUrl(config('services.moonpay.sell_url'), [
            'apiKey' => config('services.moonpay.api_key'),
            'baseCurrencyCode' => config('services.moonpay.currency_code'),
            'quoteCurrencyCode' => config('services.moonpay.base_currency_code'),
            'refundWalletAddress' => $user->hyperliquid_wallet_address,
            'language' => 'es',
            'externalCustomerId' => (string) $user->id,
            'theme' => 'dark',
        ]);
    }

    /**
     * Construye la URL del widget y añade la firma que MoonPay verifica en el
     * servidor: base64(HMAC-SHA256(secret, query string incluida la '?')).
     *
     * @param  array<string, string|null>  $params
     */
    private function signedUrl(string $baseUrl, array $params): string
    {
        $query = '?'.http_build_query(array_filter($params), '', '&', PHP_QUERY_RFC3986);

        $signature = base64_encode(hash_hmac(
            'sha256',
            $query,
            (string) config('services.moonpay.secret_key'),
            true,
        ));

        return rtrim($baseUrl, '/').$query.'&signature='.rawurlencode($signature);
    }
}
