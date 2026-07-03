<?php

namespace App\Http\Controllers;

use App\Services\MoonPayService;
use Illuminate\Support\Facades\Auth;

/**
 * Rampa de entrada y salida de fondos (fiat ↔ USDC) con el widget de MoonPay.
 *
 * Las dos pantallas solo incrustan el widget alojado por MoonPay en un iframe:
 * el pago, el KYC y los datos de tarjeta/IBAN ocurren íntegramente dentro de
 * MoonPay. Requiere tener la wallet de Hyperliquid vinculada, porque esa es la
 * dirección a la que llegan (o desde la que salen) los USDC.
 */
class MoonPayController extends Controller
{
    public function __construct(
        protected readonly MoonPayService $moonPay,
    ) {}

    /**
     * On-ramp: comprar USDC con EUR (tarjeta/banco) hacia la wallet del usuario.
     */
    public function buy()
    {
        return $this->rampView('buy');
    }

    /**
     * Off-ramp: vender USDC y recibir EUR en la cuenta bancaria (IBAN).
     */
    public function sell()
    {
        return $this->rampView('sell');
    }

    private function rampView(string $direction)
    {
        $user = Auth::user();

        // Sin wallet vinculada no hay dirección de destino/reembolso posible.
        if (! $user->isHyperliquidLinked()) {
            return redirect()->route('hyperliquid.link')->with(
                'error',
                'Vincula primero tu wallet de Hyperliquid para poder añadir o retirar fondos.',
            );
        }

        $widgetUrl = null;
        if ($this->moonPay->isConfigured()) {
            $widgetUrl = $direction === 'buy'
                ? $this->moonPay->buyUrlFor($user)
                : $this->moonPay->sellUrlFor($user);
        }

        return view('moonpay.ramp', [
            'direction' => $direction,
            'widgetUrl' => $widgetUrl,
        ]);
    }
}
