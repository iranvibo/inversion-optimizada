@extends('layouts.app')

@php
    $isBuy = $direction === 'buy';
@endphp

@section('content')
<div class="space-y-8 animate-fade-in">

    <!-- Encabezado de la página -->
    <div class="flex items-center gap-3">
        <a href="{{ route('dashboard') }}#fondos" class="text-slate-400 hover:text-white transition duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">
                {{ $isBuy ? 'Añadir fondos' : 'Retirar fondos' }}
            </h1>
            <p class="text-sm text-slate-400">
                @if($isBuy)
                    Compra USDC con tu tarjeta o cuenta bancaria y recíbelos directamente en tu propia wallet.
                @else
                    Convierte tus USDC a euros y recíbelos en tu cuenta bancaria (IBAN).
                @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- Widget de MoonPay (7 columnas) -->
        <div class="lg:col-span-7 bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-4 shadow-xl">
            @if($widgetUrl)
                <iframe
                    src="{{ $widgetUrl }}"
                    title="MoonPay"
                    allow="accelerometer; autoplay; camera; gyroscope; payment"
                    class="w-full rounded-xl border-0 bg-[hsl(223,47%,10%)]"
                    style="height: 640px;"
                ></iframe>
            @else
                <div class="h-full min-h-[320px] flex flex-col items-center justify-center text-center gap-3 p-8">
                    <span class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-300 border border-amber-500/20 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                    <h2 class="text-lg font-bold text-white">Servicio no disponible temporalmente</h2>
                    <p class="text-xs text-slate-400 max-w-sm">
                        La {{ $isBuy ? 'compra' : 'retirada' }} de fondos no está activa en este momento. Inténtalo de nuevo más tarde.
                    </p>
                    <a href="{{ route('dashboard') }}#fondos" class="mt-2 text-xs font-bold py-2.5 px-6 rounded-xl bg-violet-600 hover:bg-violet-500 text-white transition duration-200">
                        Volver al panel
                    </a>
                </div>
            @endif
        </div>

        <!-- Cómo funciona (5 columnas) -->
        <div class="lg:col-span-5 bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-xl space-y-6">
            <div>
                <h2 class="text-xl font-bold text-white mb-2">¿Cómo funciona?</h2>
                <p class="text-sm text-slate-400">
                    @if($isBuy)
                        En tres pasos tendrás tus fondos listos para que el bot opere con ellos.
                    @else
                        En tres pasos tendrás tus euros de vuelta en el banco.
                    @endif
                </p>
            </div>

            <div class="space-y-4">
                @if($isBuy)
                    <div class="flex gap-4 p-3 rounded-xl hover:bg-[hsl(223,47%,18%)] transition duration-200">
                        <div class="w-8 h-8 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center font-bold text-violet-400 text-sm shrink-0">1</div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Elige el importe y paga</h4>
                            <p class="text-xs text-slate-400 mt-0.5">Introduce cuántos euros quieres invertir (por ejemplo, 50 €) y paga con tarjeta o transferencia dentro del propio widget. La primera vez, MoonPay te pedirá verificar tu identidad.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 p-3 rounded-xl hover:bg-[hsl(223,47%,18%)] transition duration-200">
                        <div class="w-8 h-8 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center font-bold text-violet-400 text-sm shrink-0">2</div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Recibe USDC en tu wallet</h4>
                            <p class="text-xs text-slate-400 mt-0.5">MoonPay convierte tus euros a USDC (un dólar digital estable) y los envía directamente a tu wallet vinculada. Nadie más puede tocarlos.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 p-3 rounded-xl bg-violet-500/5 border border-violet-500/10">
                        <div class="w-8 h-8 rounded-full bg-violet-600/40 border border-violet-500/50 flex items-center justify-center font-bold text-violet-300 text-sm shrink-0">3</div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Deposítalos en Hyperliquid</h4>
                            <p class="text-xs text-slate-400 mt-0.5">Entra en <a href="https://app.hyperliquid.xyz" target="_blank" rel="noopener noreferrer" class="text-violet-400 hover:text-violet-300 underline underline-offset-2">app.hyperliquid.xyz</a> con tu wallet y pulsa "Deposit" para ingresar los USDC. Desde ese momento el bot podrá operar con ellos.</p>
                        </div>
                    </div>
                @else
                    <div class="flex gap-4 p-3 rounded-xl hover:bg-[hsl(223,47%,18%)] transition duration-200">
                        <div class="w-8 h-8 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center font-bold text-violet-400 text-sm shrink-0">1</div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Saca tus USDC a tu wallet</h4>
                            <p class="text-xs text-slate-400 mt-0.5">Entra en <a href="https://app.hyperliquid.xyz" target="_blank" rel="noopener noreferrer" class="text-violet-400 hover:text-violet-300 underline underline-offset-2">app.hyperliquid.xyz</a> con tu wallet y pulsa "Withdraw" para pasar los USDC a tu propia wallet. Suele tardar unos minutos.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 p-3 rounded-xl hover:bg-[hsl(223,47%,18%)] transition duration-200">
                        <div class="w-8 h-8 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center font-bold text-violet-400 text-sm shrink-0">2</div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Indica el importe y tu cuenta</h4>
                            <p class="text-xs text-slate-400 mt-0.5">En el widget, elige cuántos USDC quieres convertir a euros e introduce el IBAN donde quieres recibirlos.</p>
                        </div>
                    </div>
                    <div class="flex gap-4 p-3 rounded-xl bg-violet-500/5 border border-violet-500/10">
                        <div class="w-8 h-8 rounded-full bg-violet-600/40 border border-violet-500/50 flex items-center justify-center font-bold text-violet-300 text-sm shrink-0">3</div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Envía los USDC y recibe euros</h4>
                            <p class="text-xs text-slate-400 mt-0.5">MoonPay te indicará la dirección a la que enviar los USDC desde tu wallet. En cuanto lleguen, los convierte a euros y los transfiere a tu banco.</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="p-3 bg-[hsl(223,47%,10%)] rounded-xl border border-slate-800 text-[11px] text-slate-400 flex items-start gap-2">
                <svg class="w-4 h-4 text-violet-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <span>
                    El pago se procesa íntegramente en MoonPay: ViBo Invest <strong>nunca ve ni almacena</strong> los datos de tu tarjeta ni de tu cuenta bancaria.
                </span>
            </div>
        </div>

    </div>

</div>
@endsection
