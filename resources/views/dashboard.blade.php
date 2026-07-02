@extends('layouts.app')

@section('content')
<div class="space-y-8 animate-fade-in">
    
    <!-- Encabezado del Dashboard -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Tu Panel de Control</h1>
            <p class="text-sm text-slate-400">Controla tu estrategia y supervisa tu inversión en tiempo real.</p>
        </div>
        <div class="flex items-center gap-3">
            @if($user->isBrokerLinked())
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    {{ $user->tradingChannel() === \App\Models\User::CHANNEL_HYPERLIQUID ? 'Hyperliquid Conectado' : 'Binance Conectado' }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-500/10 text-slate-400 border border-slate-500/20">
                    <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                    Sin Conexión API
                </span>
            @endif

            <span id="top-bot-position-badge" class="text-[11px] font-bold px-2 py-0.5 rounded-lg {{ $user->current_position === 'LONG' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($user->current_position === 'SHORT' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-slate-500/10 text-slate-400 border border-slate-500/20') }}">
                @if($user->current_position === 'LONG')
                    Comprando-BTC
                @elseif($user->current_position === 'SHORT')
                    Vendiendo-BTC
                @else
                    Fuera de mercado
                @endif
            </span>
        </div>
    </div>

    <!-- Pestañas de Navegación (US05) -->
    <div class="border-b border-slate-800/60 flex space-x-6 overflow-x-auto whitespace-nowrap scrollbar-none pb-0.5">
        <button type="button" id="tab-btn-panel" class="tab-btn shrink-0 border-b-2 border-violet-500 pb-3 px-1 text-sm font-semibold text-white focus:outline-none transition duration-200">
            Panel de Control
        </button>
        <button type="button" id="tab-btn-activity" class="tab-btn shrink-0 border-b-2 border-transparent pb-3 px-1 text-sm font-medium text-slate-400 hover:text-white focus:outline-none transition duration-200">
            Actividad
        </button>
        <button type="button" id="tab-btn-info" class="tab-btn shrink-0 border-b-2 border-transparent pb-3 px-1 text-sm font-medium text-slate-400 hover:text-white focus:outline-none transition duration-200">
            Cómo Funciona
        </button>
        <button type="button" id="tab-btn-help" class="tab-btn shrink-0 border-b-2 border-transparent pb-3 px-1 text-sm font-medium text-slate-400 hover:text-white focus:outline-none transition duration-200">
            Ayuda
        </button>
    </div>

    <div id="tab-content-panel" class="tab-content space-y-8 animate-fade-in">

    <!-- MENSAJES DE ALERTA Y CONFIRMACIÓN -->

    <!-- Alerta de Inestabilidad del Proveedor de Señales (US06) -->
    @if($signalProviderUnstable)
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm flex items-center gap-3 animate-fade-in mb-6">
            <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
                Conexión temporalmente inestable con el proveedor de señales, tus fondos están seguros.
            </div>
        </div>
    @endif

    <!-- Alerta de Retiros en Segundo Plano (Escenario 3) -->
    @if($user->binance_withdrawal_alert)
        <div class="p-6 rounded-2xl bg-rose-500/10 border-2 border-rose-500/30 text-rose-200 shadow-xl space-y-4 relative overflow-hidden">
            <!-- Glow detrás del error para estética premium -->
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-rose-500/20 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-rose-500/20 flex items-center justify-center text-rose-400 shrink-0 border border-rose-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-white uppercase tracking-wide">¡ALERTA DE SEGURIDAD MÁXIMA!</h3>
                    <p class="text-sm text-slate-300">
                        La verificación automática de seguridad en segundo plano ha detectado que se han habilitado **permisos de retiro** en tu cuenta de Binance.
                    </p>
                    <p class="text-sm font-semibold text-rose-300">
                        Por seguridad de tu capital, el bot ha sido PAUSADO de forma inmediata e ineludible.
                    </p>
                </div>
            </div>
            
            <div class="bg-[hsl(223,47%,10%)] p-4 rounded-xl border border-rose-500/20 text-xs space-y-2 text-slate-300">
                <span class="font-bold text-white block">Instrucciones de Solución Obligatorias:</span>
                <ol class="list-decimal pl-4 space-y-1">
                    <li>Accede a tu cuenta de Binance en el menú de "Gestión de API".</li>
                    <li>Edita las restricciones de la API Key vinculada.</li>
                    <li><strong>Desmarca</strong> la casilla "Permitir Retiros" (Enable Withdrawals).</li>
                    <li>Guarda los cambios e introduce tu código 2FA.</li>
                    <li>Vuelve aquí y pulsa "Reconfigurar API Key" para volver a validar la seguridad.</li>
                </ol>
            </div>
            
            <div class="flex gap-3">
                <a href="{{ route('binance.link') }}" class="bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold py-2.5 px-5 rounded-xl transition hover-lift">
                    Reconfigurar API Key de Binance
                </a>
            </div>
        </div>
    @endif

    <!-- Confirmación de Seguridad Exitosa (Escenario 2) -->
    @if(session('security_verified'))
        <div class="p-5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 flex items-center gap-4 animate-fade-in">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0 border border-emerald-500/30">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <div class="text-sm">
                <span class="font-bold block text-white">Seguridad Verificada</span>
                {{ session('security_verified') }}
            </div>
        </div>
    @endif

    <!-- BALANCE TOTAL Y EVOLUCIÓN (US03) -->
    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md relative overflow-hidden">
        <div class="absolute -left-20 -top-20 w-60 h-60 bg-violet-500/5 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6 relative">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Balance Total</span>
                <!-- Balance grande y legible, sin jerga técnica (Escenario 1) -->
                <div id="balance-amount"
                     class="text-5xl md:text-6xl font-extrabold tracking-tight text-white my-2 transition-all duration-500"
                     data-balance="{{ $latestSnapshot?->balance ?? '' }}">
                    @if($latestSnapshot)
                        {{ number_format($latestSnapshot->balance, 2, ',', '.') }}<span class="text-3xl text-slate-400 font-semibold">$</span>
                    @else
                        <span class="text-3xl text-slate-500 font-semibold">Sin datos todavía</span>
                    @endif
                </div>
                <p id="balance-change" class="text-sm text-slate-400">
                    @if(!$latestSnapshot)
                        Cuando el sistema sincronice tu cuenta verás aquí la evolución de tu dinero.
                    @endif
                </p>
            </div>

            <!-- Filtros temporales: Día / Semana / Mes (Escenario 2) -->
            <div id="range-filters" class="flex items-center gap-2 bg-[hsl(223,47%,10%)] rounded-xl p-1 border border-[rgba(255,255,255,0.06)] self-start">
                <button type="button" data-range="day" class="range-btn text-xs font-bold py-2 px-4 rounded-lg transition duration-200 text-slate-400 hover:text-white">Día</button>
                <button type="button" data-range="week" class="range-btn text-xs font-bold py-2 px-4 rounded-lg transition duration-200 text-slate-400 hover:text-white">Semana</button>
                <button type="button" data-range="month" class="range-btn text-xs font-bold py-2 px-4 rounded-lg transition duration-200 bg-violet-600 text-white">Mes</button>
            </div>
        </div>

        <!-- Gráfico lineal simple: sin velas, RSI, MACD ni libros de órdenes (Escenario 1) -->
        <div class="mt-6 rounded-xl bg-[hsl(222,47%,10%)] border border-[rgba(255,255,255,0.06)] p-5 relative">
            <svg id="balance-chart" viewBox="0 0 600 200" class="w-full h-auto" aria-label="Gráfico de evolución del balance"></svg>
            <p id="balance-chart-empty" class="hidden text-center text-xs text-slate-500 py-8">
                Aún no hay historial suficiente en este rango. Prueba la herramienta de sincronización del simulador.
            </p>
            
            <!-- Tooltip Flotante Premium -->
            <div id="chart-tooltip" class="absolute pointer-events-none bg-slate-950/95 border border-slate-800 rounded-xl p-3 text-xs shadow-2xl hidden z-10 transition-all duration-75 select-none" style="min-width: 140px;">
                <div id="tooltip-date" class="text-slate-400 font-medium mb-1"></div>
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                    <div id="tooltip-value" class="font-extrabold text-white text-sm"></div>
                </div>
            </div>
        </div>
        <p id="balance-error" class="hidden text-sm text-rose-400 mt-3"></p>
    </div>

    <!-- SECCIÓN DE TARJETAS PRINCIPALES (US07) -->
    @php
        $rl = strtolower($user->risk_level);
        $riskFill = $rl === 'agresivo' ? 3 : ($rl === 'balanceado' ? 2 : 1);
        $riskColor = $rl === 'agresivo' ? 'bg-rose-500' : ($rl === 'balanceado' ? 'bg-amber-500' : 'bg-emerald-500');
        $riskWord = $rl === 'agresivo' ? 'Riesgo alto' : ($rl === 'balanceado' ? 'Riesgo medio' : 'Riesgo bajo');
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <!-- Tarjeta 1: Estado del Bot -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[170px]">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-lg bg-violet-500/10 text-violet-300 border border-violet-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Estado del Bot</span>
                </div>
                <span id="bot-indicator" class="relative flex h-3 w-3 shrink-0">
                    @if($user->bot_active)
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    @else
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    @endif
                </span>
            </div>

            <div class="my-4">
                <div class="mb-2">
                    <span id="bot-status-text" class="text-2xl font-extrabold block text-white uppercase tracking-tight">
                        {{ $user->bot_active ? 'Activo' : 'Pausado' }}
                    </span>
                    <span id="bot-status-desc" class="text-xs text-slate-400">
                        {{ $user->bot_active ? 'Ejecutando señales del mercado.' : 'El bot no realizará operaciones.' }}
                    </span>
                </div>
                <div class="pt-2 border-t border-slate-800/40 flex items-center justify-between">
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Posición Actual</span>
                    <span id="bot-position-badge" class="text-[11px] font-bold px-2 py-0.5 rounded-lg {{ $user->current_position === 'LONG' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($user->current_position === 'SHORT' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-slate-500/10 text-slate-400 border border-slate-500/20') }}">
                        @if($user->current_position === 'LONG')
                            Comprando-BTC
                        @elseif($user->current_position === 'SHORT')
                            Vendiendo-BTC
                        @else
                            Fuera de mercado
                        @endif
                    </span>
                </div>
            </div>

            <form id="bot-toggle-form" action="{{ route('bot.toggle') }}" method="POST" class="m-0 p-0">
                @csrf
                <button type="submit" id="bot-toggle-btn"
                        class="w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 {{ $user->bot_active ? 'bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30' : 'bg-emerald-600 hover:bg-emerald-500 text-white' }}">
                    {{ $user->bot_active ? 'Pausar Bot' : 'Activar Bot' }}
                </button>
            </form>
        </div>

        <!-- Tarjeta 2: Modo de Operativa -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[170px]">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-lg bg-violet-500/10 text-violet-300 border border-violet-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m4 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Modo de Operación</span>
                </div>
                <div class="my-4">
                    <span id="bot-mode-text" class="text-2xl font-extrabold block text-white uppercase tracking-tight">
                        {{ $user->bot_mode === 'real' ? 'Dinero Real' : 'Simulación' }}
                    </span>
                    <span id="bot-mode-desc" class="text-xs text-slate-400 block mt-1">
                        @if($user->bot_mode === 'real')
                            Operando directamente en tu cuenta de {{ $user->tradingChannel() === \App\Models\User::CHANNEL_HYPERLIQUID ? 'Hyperliquid' : 'Binance' }}.
                        @else
                            Operaciones simuladas con capital de prueba.
                        @endif
                    </span>
                </div>
            </div>

            <form id="bot-toggle-mode-form" action="{{ route('bot.toggle-mode') }}" method="POST" class="m-0 p-0">
                @csrf
                <button type="submit" id="bot-toggle-mode-btn"
                        class="w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-violet-600/20 hover:bg-violet-600/30 text-violet-300 border border-violet-500/30">
                    Cambiar a Modo {{ $user->bot_mode === 'real' ? 'Simulación' : 'Real' }}
                </button>
            </form>
        </div>

        <!-- Tarjeta 3: Nivel de Riesgo (US07) -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[170px]">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-lg bg-violet-500/10 text-violet-300 border border-violet-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Nivel de Riesgo</span>
                </div>
                <div class="my-4">
                    <span id="risk-level-badge" class="text-2xl font-extrabold block text-white tracking-tight uppercase">
                        {{ $user->risk_level }}
                    </span>

                    <!-- Medidor visual de riesgo: traduce el nivel a bajo / medio / alto sin jerga -->
                    <div class="flex items-center gap-2 mt-3">
                        <div id="risk-gauge" class="flex items-center gap-1 flex-1" role="img" aria-label="Nivel de riesgo: {{ $riskWord }}">
                            @for($i = 1; $i <= 3; $i++)
                                <span class="risk-seg h-1.5 flex-1 rounded-full {{ $i <= $riskFill ? $riskColor : 'bg-slate-700/70' }}"></span>
                            @endfor
                        </div>
                        <span id="risk-gauge-label" class="text-[10px] font-bold uppercase tracking-wider text-slate-400 shrink-0">{{ $riskWord }}</span>
                    </div>

                    <span id="risk-level-desc" class="text-xs text-slate-400 block mt-2">
                        @if($rl === 'conservador')
                            Prioriza preservar tu capital. Crecimiento moderado con caídas temporales pequeñas de hasta un 15%-20%.
                        @elseif($rl === 'balanceado')
                            Equilibrio entre crecimiento y estabilidad. Asume caídas temporales moderadas de hasta un 30%-50%.
                        @else
                            Busca el máximo crecimiento. Debes tolerar caídas temporales pronunciadas de hasta un 50%-90%.
                        @endif
                    </span>
                </div>
            </div>

            <form id="risk-level-form" action="{{ route('bot.update-risk') }}" method="POST" class="m-0 p-0">
                @csrf
                <div class="flex gap-2">
                    <select id="risk-level-select" name="risk_level" class="flex-1 bg-slate-900 border border-slate-800 rounded-xl px-2 py-2 text-xs font-semibold text-white focus:outline-none focus:ring-1 focus:ring-violet-500">
                        <option value="conservador" {{ $rl === 'conservador' ? 'selected' : '' }}>Conservador</option>
                        <option value="balanceado" {{ $rl === 'balanceado' ? 'selected' : '' }}>Balanceado</option>
                        <option value="agresivo" {{ $rl === 'agresivo' ? 'selected' : '' }}>Agresivo</option>
                    </select>
                    <button type="submit" id="risk-level-btn"
                            class="text-xs font-bold py-2.5 px-3 rounded-xl transition duration-200 bg-violet-600 hover:bg-violet-500 text-white shadow-md shrink-0">
                        Cambiar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tarjeta 4: Canal de Ejecución (Binance / Hyperliquid) -->
        @php
            $activeChannel = $user->tradingChannel();
            $isHyperliquidChannel = $activeChannel === \App\Models\User::CHANNEL_HYPERLIQUID;
        @endphp
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[170px]">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-lg bg-violet-500/10 text-violet-300 border border-violet-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Canal de Ejecución</span>
                </div>

                <!-- Selector de canal: el activo se resalta; cambiar al otro canal
                     exige tenerlo vinculado (si no, lleva a su pantalla de conexión) -->
                <div class="flex gap-2 mt-4">
                    @if($isHyperliquidChannel)
                        @if($user->isBinanceLinked())
                            <form action="{{ route('bot.switch-channel') }}" method="POST" class="m-0 p-0 flex-1">
                                @csrf
                                <input type="hidden" name="trading_channel" value="binance">
                                <button type="submit" class="w-full text-[11px] font-bold py-1.5 px-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:border-violet-500/50 transition duration-200">Binance</button>
                            </form>
                        @else
                            <a href="{{ route('binance.link') }}" class="flex-1 text-center text-[11px] font-bold py-1.5 px-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:border-violet-500/50 transition duration-200">Binance</a>
                        @endif
                        <span class="flex-1 text-center text-[11px] font-bold py-1.5 px-2 rounded-lg bg-violet-600/20 border border-violet-500/50 text-violet-300">Hyperliquid</span>
                    @else
                        <span class="flex-1 text-center text-[11px] font-bold py-1.5 px-2 rounded-lg bg-violet-600/20 border border-violet-500/50 text-violet-300">Binance</span>
                        @if($user->isHyperliquidLinked())
                            <form action="{{ route('bot.switch-channel') }}" method="POST" class="m-0 p-0 flex-1">
                                @csrf
                                <input type="hidden" name="trading_channel" value="hyperliquid">
                                <button type="submit" class="w-full text-[11px] font-bold py-1.5 px-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:border-violet-500/50 transition duration-200">Hyperliquid</button>
                            </form>
                        @else
                            <a href="{{ route('hyperliquid.link') }}" class="flex-1 text-center text-[11px] font-bold py-1.5 px-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:border-violet-500/50 transition duration-200">Hyperliquid</a>
                        @endif
                    @endif
                </div>

                <div class="my-3">
                    @if($user->isBrokerLinked())
                        <span class="text-2xl font-extrabold block text-emerald-400 uppercase tracking-tight">Conectado</span>
                        <code class="text-[11px] text-slate-400 bg-slate-900 px-2 py-1 rounded block truncate mt-2">
                            @if($isHyperliquidChannel)
                                Wallet: {{ substr($user->hyperliquid_wallet_address, 0, 8) }}...{{ substr($user->hyperliquid_wallet_address, -6) }}
                            @else
                                API: {{ substr($user->binance_api_key, 0, 8) }}...{{ substr($user->binance_api_key, -8) }}
                            @endif
                        </code>
                    @else
                        <span class="text-2xl font-extrabold block text-rose-400 uppercase tracking-tight">Sin conexión</span>
                        <span class="text-xs text-slate-400 block mt-1">Conecta tu cuenta para operar en real de forma segura.</span>
                    @endif
                </div>
            </div>

            @if($user->isBrokerLinked())
                <form action="{{ $isHyperliquidChannel ? route('hyperliquid.disconnect') : route('binance.disconnect') }}" method="POST" class="m-0 p-0">
                    @csrf
                    <button type="submit"
                            class="w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30">
                        Desvincular Cuenta
                    </button>
                </form>
            @else
                <a href="{{ $isHyperliquidChannel ? route('hyperliquid.link') : route('binance.link') }}"
                   class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-violet-600 hover:bg-violet-500 text-white inline-block">
                    {{ $isHyperliquidChannel ? 'Conectar Hyperliquid' : 'Conectar Binance' }}
                </a>
            @endif
        </div>

    </div>

    @if(config('app.demo_tools_enabled') && request()->has('simulador'))
    <!-- SECCIÓN DE SIMULACIÓN Y PRUEBAS PARA EL USUARIO -->
    <div class="bg-[hsl(223,47%,14%)] border border-violet-500/20 rounded-2xl p-6 shadow-lg relative overflow-hidden">
        <div class="absolute -right-20 -bottom-20 w-60 h-60 bg-violet-500/5 rounded-full blur-3xl pointer-events-none"></div>
        
        <h2 class="text-lg font-bold text-white mb-2 flex items-center gap-2">
            <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 9.172V5L8 4z" />
            </svg>
            Herramientas del Simulador de Integración (US01)
        </h2>
        <p class="text-xs text-slate-400 mb-6">
            Usa estas herramientas para probar los diferentes escenarios definidos en los criterios de aceptación (BDD) de la historia de usuario.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Escenario 1: Probar Vincular Llave con Retiros Habilitados -->
            <div class="bg-[hsl(223,47%,10%)] p-4 rounded-xl border border-slate-800 space-y-3">
                <span class="text-xs font-bold text-violet-400 block uppercase">1. Detectar Retiros Activos</span>
                <p class="text-xs text-slate-300">
                    Ve a la pantalla de vinculación y utiliza la clave <code>withdrawals_enabled_key</code>. El sistema bloqueará la vinculación y mostrará la guía roja técnica.
                </p>
                <a href="{{ route('binance.link') }}?api_key=withdrawals_enabled_key" 
                   class="inline-block text-[11px] font-bold text-violet-300 hover:text-white transition duration-200">
                    Probar en Formulario &rarr;
                </a>
            </div>

            <!-- Escenario 2: Probar Vinculación Exitosa -->
            <div class="bg-[hsl(223,47%,10%)] p-4 rounded-xl border border-slate-800 space-y-3">
                <span class="text-xs font-bold text-emerald-400 block uppercase">2. Vinculación Exitosa</span>
                <p class="text-xs text-slate-300">
                    Ve a la pantalla de vinculación y utiliza cualquier clave válida, ej: <code>my_secure_api_key</code>. Debe redireccionar con el banner verde de confirmación.
                </p>
                <a href="{{ route('binance.link') }}?api_key=my_secure_api_key" 
                   class="inline-block text-[11px] font-bold text-emerald-300 hover:text-white transition duration-200">
                    Probar en Formulario &rarr;
                </a>
            </div>

            <!-- Escenario 3: Simulación de Auditoría en Segundo Plano -->
            <div class="bg-[hsl(223,47%,10%)] p-4 rounded-xl border border-slate-800 space-y-3 flex flex-col justify-between">
                <div>
                    <span class="text-xs font-bold text-rose-400 block uppercase">3. Auditoría Periódica</span>
                    <p class="text-xs text-slate-300">
                        Simula que tu clave vinculada adquiere permisos de retiro en Binance posteriormente. Se ejecuta el comando de fondo, pausa el bot y lanza la alerta.
                    </p>
                </div>
                
                @if($user->isBinanceLinked())
                    <form action="{{ route('binance.simulate-alert') }}" method="POST" class="m-0 p-0 mt-3">
                        @csrf
                        <button type="submit" 
                                class="w-full text-center text-[10px] font-bold py-1.5 px-3 rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition hover-lift">
                            Simular Detección de Retiros
                        </button>
                    </form>
                @else
                    <span class="text-[10px] text-slate-500 italic block mt-3">Requiere Binance conectado primero.</span>
                @endif
            </div>

            <!-- US03: Simular sincronización de balance -->
            <div class="bg-[hsl(223,47%,10%)] p-4 rounded-xl border border-slate-800 space-y-3 flex flex-col justify-between">
                <div>
                    <span class="text-xs font-bold text-sky-400 block uppercase">4. Sincronizar Balance (US03)</span>
                    <p class="text-xs text-slate-300">
                        Simula que el backend sincroniza tu saldo con Binance: genera historial demo si no existe, registra un nuevo snapshot y actualiza el balance en vivo vía WebSockets.
                    </p>
                </div>

                @if($user->isBinanceLinked())
                    <form action="{{ route('binance.simulate-balance-sync') }}" method="POST" class="m-0 p-0 mt-3">
                        @csrf
                        <button type="submit"
                                class="w-full text-center text-[10px] font-bold py-1.5 px-3 rounded-lg bg-sky-600 hover:bg-sky-500 text-white transition hover-lift">
                            Simular Sincronización de Balance
                        </button>
                    </form>
                @else
                    <span class="text-[10px] text-slate-500 italic block mt-3">Requiere Binance conectado primero.</span>
                @endif
            </div>

            <!-- US05: Simular Actividad del Bot -->
            <div class="bg-[hsl(223,47%,10%)] p-4 rounded-xl border border-slate-800 space-y-3 flex flex-col justify-between">
                <div>
                    <span class="text-xs font-bold text-indigo-400 block uppercase">5. Simular Actividad (US05)</span>
                    <p class="text-xs text-slate-300">
                        Siembra datos reales traducidos a lenguaje humano: compras de oportunidad, cierres individuales de rendimiento y protección stop-loss diaria.
                    </p>
                </div>

                <form action="{{ route('bot.simulate-activity') }}" method="POST" class="m-0 p-0 mt-3">
                    @csrf
                    <button type="submit"
                            class="w-full text-center text-[10px] font-bold py-1.5 px-3 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition hover-lift">
                        Simular Actividad del Bot
                    </button>
                </form>
            </div>

        </div>
    </div>
    @endif

    <!-- Zona de Peligro (GDPR) -->
    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md mt-8">
        <h3 class="text-sm font-bold text-rose-400 flex items-center gap-2 mb-2">
            <svg class="w-4 h-4 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Zona de Peligro: Privacidad y Datos (GDPR)
        </h3>
        <p class="text-xs text-slate-400 mb-4 leading-relaxed">
            De acuerdo con la legislación GDPR, puedes eliminar de forma permanente tu cuenta, tus claves API cifradas de Binance y todo tu historial de operaciones. Esta acción es irreversible.
        </p>
        <button type="button" 
                onclick="confirmAccountDeletion()" 
                class="inline-block text-xs font-bold py-2.5 px-5 rounded-xl transition duration-200 bg-rose-500/10 hover:bg-rose-500 hover:text-white text-rose-400 border border-rose-500/30 cursor-pointer hover-lift">
            Eliminar mi Cuenta
        </button>
    </div>

    </div>

    <!-- Contenido de la pestaña de Actividad (US05) -->
    <div id="tab-content-activity" class="tab-content space-y-8 hidden animate-fade-in">
        
        <!-- Alertas de Riesgo Destacadas arriba (Escenario 3) -->
        <div id="activity-risk-alerts" class="space-y-4">
            @foreach($riskAlerts as $alert)
                <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-200 shadow-lg relative overflow-hidden">
                    <!-- Glow detrás del error para estética premium -->
                    <div class="absolute -right-10 -top-10 w-24 h-24 bg-rose-500/10 rounded-full blur-xl pointer-events-none"></div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-rose-500/20 flex items-center justify-center text-rose-400 shrink-0 border border-rose-500/30">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-rose-400 uppercase tracking-wider block">ALERTA DE PROTECCIÓN DE RIESGO</span>
                            <p class="text-sm font-semibold text-white mt-1">{{ $alert->human_description }}</p>
                            <span class="text-[10px] text-slate-400 block mt-2">{{ $alert->created_at->diffForHumans() }} ({{ $alert->created_at->format('d/m/Y H:i') }})</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Feed Principal de Actividad Reciente -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md relative overflow-hidden">
            <div class="absolute -left-20 -top-20 w-60 h-60 bg-violet-500/5 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative space-y-6">
                <div>
                    <h2 class="text-xl font-bold text-white">Historial de Actividad Reciente</h2>
                    <p class="text-xs text-slate-400">Explicaciones claras en lenguaje sencillo sobre el comportamiento y las acciones del bot.</p>
                </div>

                <div id="activity-feed-list" class="divide-y divide-slate-800/40">
                    @forelse($activities as $activity)
                        <div class="py-4 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-4">
                                <!-- Icono según tipo -->
                                @if($activity->type === 'long' || $activity->type === 'buy')
                                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20 shrink-0" title="Inversión al Alza (LONG)">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8L6 21" />
                                        </svg>
                                    </div>
                                @elseif($activity->type === 'short')
                                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center border border-rose-500/20 shrink-0" title="Inversión a la Baja (SHORT)">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8L6 3" />
                                        </svg>
                                    </div>
                                @elseif($activity->type === 'close' || $activity->type === 'sell')
                                    <div class="w-10 h-10 rounded-xl bg-slate-500/10 text-slate-400 flex items-center justify-center border border-slate-500/20 shrink-0" title="Posición Cerrada (CLOSE)">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                @elseif($activity->type === 'risk_protection')
                                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center border border-rose-500/20 shrink-0" title="Protección de Riesgo">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                @else
                                    <div class="w-10 h-10 rounded-xl bg-slate-500/10 text-slate-400 flex items-center justify-center border border-slate-500/20 shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                @endif
                                
                                <div>
                                    <p class="text-sm font-medium text-slate-200">{{ $activity->human_description }}</p>
                                    <span class="text-[10px] text-slate-500 block mt-1">{{ $activity->created_at->diffForHumans() }} ({{ $activity->created_at->format('d/m/Y H:i') }})</span>
                                </div>
                            </div>

                            <!-- Visualización amigable de rendimientos individuales (Escenario 2) -->
                            @if($activity->profit_percentage !== null)
                                <div class="text-right shrink-0">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold {{ $activity->profit_percentage >= 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' }}">
                                        {{ $activity->profit_percentage >= 0 ? '+' : '' }}{{ number_format($activity->profit_percentage, 1, ',', '.') }}%
                                    </span>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="py-12 text-center text-slate-500 text-sm">
                            <p>No se han registrado acciones del bot recientemente en esta cuenta.</p>
                            <p class="text-xs text-slate-600 mt-1">Usa la herramienta del simulador en la pestaña "Panel de Control" para generar actividad de prueba.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    <!-- Contenido de la pestaña de Cómo Funciona -->
    <div id="tab-content-info" class="tab-content hidden animate-fade-in space-y-8">
        <!-- Tarjeta de Bienvenida / Hero simple -->
        <div class="relative bg-gradient-to-r from-violet-600/10 via-slate-900 to-indigo-600/10 border border-violet-500/20 rounded-3xl p-6 md:p-8 overflow-hidden shadow-xl">
            <!-- Glow estético -->
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-violet-600/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 max-w-3xl space-y-4">
                <span class="text-xs font-bold text-violet-400 uppercase tracking-widest bg-violet-500/10 px-3 py-1 rounded-full border border-violet-500/20">Tu guía de inicio</span>
                <h2 class="text-2xl md:text-3xl font-bold text-white tracking-tight leading-tight">Cómo funciona ViBo Invest</h2>
                <p class="text-sm md:text-base text-slate-300 leading-relaxed">
                    Nuestra misión es hacer accesible el trading automatizado para cualquier persona. Diseñado bajo el concepto de "Netflix del trading", ViBo se enfoca en la máxima simplicidad, transparencia y seguridad, eliminando gráficos complejos y tecnicismos.
                </p>
            </div>
        </div>

        <!-- Cuadrícula de Fundamentos -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- El Objetivo -->
            <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 hover:border-violet-500/30 transition duration-300 group shadow-md">
                <div class="w-12 h-12 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 border border-violet-500/20 mb-4 group-hover:scale-105 transition-transform duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">¿Cuál es el Objetivo?</h3>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Democratizar las inversiones automatizadas. En lugar de lidiar con complicadas terminales de trading, ViBo te permite activar estrategias preconfiguradas con un solo clic y adaptadas a tu perfil de riesgo personal.
                </p>
            </div>

            <!-- ¿Qué es el Trading? -->
            <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 hover:border-violet-500/30 transition duration-300 group shadow-md">
                <div class="w-12 h-12 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 border border-violet-500/20 mb-4 group-hover:scale-105 transition-transform duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">¿Qué es el Trading?</h3>
                <p class="text-sm text-slate-300 leading-relaxed">
                    El trading es la compra y venta de activos financieros para obtener beneficios de sus variaciones de precio. En ViBo, nuestro sistema opera de forma 100% automatizada en segundo plano: no tienes que analizar gráficos ni ejecutar órdenes manualmente.
                </p>
            </div>

            <!-- ¿Por qué solo Bitcoin? -->
            <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 hover:border-violet-500/30 transition duration-300 group shadow-md">
                <div class="w-12 h-12 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 border border-violet-500/20 mb-4 group-hover:scale-105 transition-transform duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">¿Por qué solo con Bitcoin (BTC)?</h3>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Operamos exclusivamente el par BTC/USDC. Bitcoin es el activo digital más consolidado, seguro y líquido del mercado. Al enfocarnos solo en BTC contra USDC (dólar digital), evitamos la volatilidad desmedida de monedas desconocidas y mantenemos tu cartera clara y simple.
                </p>
            </div>

            <!-- ¿Qué es Binance y su prestigio? -->
            <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 hover:border-violet-500/30 transition duration-300 group shadow-md">
                <div class="w-12 h-12 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 border border-violet-500/20 mb-4 group-hover:scale-105 transition-transform duration-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Prestigio de Binance</h3>
                <p class="text-sm text-slate-300 leading-relaxed">
                    Binance es el exchange (casa de cambio) de criptomonedas más grande, seguro y de mayor prestigio en el mundo. Tus fondos nunca se transfieren a ViBo Invest; siempre se quedan bajo tu custodia directa dentro de tu cuenta personal de Binance.
                </p>
            </div>
        </div>



        <!-- Mecanismo y herramientas -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Mecanismo y Herramientas (Paso a Paso) -->
            <div class="lg:col-span-7 bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-3xl p-6 md:p-8 shadow-md space-y-6">
                <div>
                    <h3 class="text-xl font-bold text-white mb-2">El Mecanismo de Funcionamiento</h3>
                    <p class="text-sm text-slate-400">Así es como ViBo procesa y ejecuta cada movimiento en milisegundos:</p>
                </div>

                <div class="space-y-6 relative before:absolute before:left-6 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-800">
                    <!-- Paso 1 -->
                    <div class="flex items-start gap-4 relative">
                        <span class="w-12 h-12 rounded-xl bg-violet-600/10 border border-violet-500/20 text-violet-400 flex items-center justify-center font-bold text-sm shrink-0 shadow-md">01</span>
                        <div>
                            <h4 class="text-sm font-bold text-white mb-1">Sondeo de Señales (Polling)</h4>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Cada 5 segundos, nuestro servidor consulta al proveedor de señales si existe un cambio de estrategia de acuerdo con tu nivel de riesgo (Conservador, Balanceado o Agresivo).
                            </p>
                        </div>
                    </div>
                    <!-- Paso 2 -->
                    <div class="flex items-start gap-4 relative">
                        <span class="w-12 h-12 rounded-xl bg-violet-600/10 border border-violet-500/20 text-violet-400 flex items-center justify-center font-bold text-sm shrink-0 shadow-md">02</span>
                        <div>
                            <h4 class="text-sm font-bold text-white mb-1">Validación y Filtro de Seguridad</h4>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                El sistema actúa como un guardián de seguridad (Gatekeeper). Antes de emitir cualquier orden, confirma que el bot esté encendido, verifica tus credenciales y comprueba que **no** existan permisos de retiro activos.
                            </p>
                        </div>
                    </div>
                    <!-- Paso 3 -->
                    <div class="flex items-start gap-4 relative">
                        <span class="w-12 h-12 rounded-xl bg-violet-600/10 border border-violet-500/20 text-violet-400 flex items-center justify-center font-bold text-sm shrink-0 shadow-md">03</span>
                        <div>
                            <h4 class="text-sm font-bold text-white mb-1">Ejecución en Tiempo Real</h4>
                            <p class="text-xs text-slate-300 leading-relaxed">
                                Si la señal cambia y la cuenta es segura, ViBo procesa y envía la orden a Binance (comprar BTC, vender BTC o cerrar posición a USDC). Esto se ejecuta localmente mediante claves API cifradas en nuestro servidor.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Protecciones y Seguridad -->
            <div class="lg:col-span-5 bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-3xl p-6 md:p-8 shadow-md relative overflow-hidden space-y-6">
                <!-- Glow verde sutil para seguridad -->
                <div class="absolute -right-20 -top-20 w-48 h-48 bg-emerald-500/10 rounded-full blur-2xl pointer-events-none"></div>
                
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Protecciones y Seguridad
                </h3>

                <div class="space-y-4 text-xs text-slate-300 leading-relaxed">
                    <div class="bg-emerald-500/5 border border-emerald-500/20 rounded-xl p-3">
                        <strong class="text-white block mb-1">Sin permisos de retiro</strong>
                        Para conectar ViBo, es obligatorio desactivar la casilla "Enable Withdrawals" (Permitir Retiros) al crear tu API Key en Binance. ViBo **nunca** podrá retirar tus fondos.
                    </div>
                    <div class="bg-rose-500/5 border border-rose-500/20 rounded-xl p-3">
                        <strong class="text-white block mb-1">Pausa Automática Ineludible</strong>
                        Si en cualquier momento nuestro sistema detecta que has activado los permisos de retiro en Binance, el bot se **detendrá de inmediato** por seguridad absoluta.
                    </div>
                    <div class="bg-slate-900/40 border border-slate-800 rounded-xl p-3">
                        <strong class="text-white block mb-1">Cifrado de Llaves (AES-256)</strong>
                        Tus API Keys y Secret Keys se guardan completamente cifradas en el servidor y nunca se comparten con la API de señales ni terceros.
                    </div>
                </div>
            </div>
        </div>

        <!-- Gestión y Control de Riesgos (Horizontal) -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-3xl p-6 md:p-8 shadow-md space-y-6">
            <div class="max-w-3xl">
                <h3 class="text-xl font-bold text-white flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Gestión y Control de Riesgos
                </h3>
                <p class="text-sm text-slate-300">
                    El trading es una actividad con riesgo inherente y se pueden experimentar **caídas temporales** ("drawdowns"). En ViBo te damos herramientas claras y sencillas para gestionarlo:
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Punto 1 -->
                <div class="bg-violet-500/5 border border-violet-500/20 rounded-2xl p-5">
                    <strong class="text-white block text-sm mb-2">Perfiles de Riesgo Ajustables</strong>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        Elige en cualquier momento entre un nivel **Conservador**, **Balanceado** o **Agresivo**. Esto cambiará el apalancamiento y el comportamiento de las señales replicadas del proveedor externo.
                    </p>
                </div>
                <!-- Punto 2 -->
                <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-5">
                    <strong class="text-white block text-sm mb-2">Botón de Pánico (Pausa al instante)</strong>
                    <p class="text-xs text-slate-300 leading-relaxed">
                        Si deseas detener la operativa por cualquier motivo, puedes pausar el bot con un solo clic. Esto cerrará todas tus posiciones en Bitcoin de inmediato y convertirá el saldo a USDC.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido de la pestaña de Ayuda -->
    <div id="tab-content-help" class="tab-content hidden animate-fade-in space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Columna Izquierda: Lista de Pasos -->
            <div id="help-steps-list" class="lg:col-span-5 space-y-4 lg:block transition-all duration-300">
                <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md sticky top-24">
                    <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-4 px-2">Guía de Configuración</h3>
                    <div class="space-y-2">
                        <!-- Botón Paso 1 -->
                        <button type="button" data-step="1" class="step-nav-btn w-full flex items-center gap-4 p-4 rounded-xl border text-left transition duration-200 bg-violet-600/10 border-violet-500/30 text-white font-semibold">
                            <span class="step-num w-8 h-8 rounded-lg bg-violet-600 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-md transition-colors duration-200">01</span>
                            <span class="text-sm font-semibold truncate">Crear cuenta en Binance</span>
                        </button>
                        <!-- Botón Paso 2 -->
                        <button type="button" data-step="2" class="step-nav-btn w-full flex items-center gap-4 p-4 rounded-xl border border-transparent text-left hover:bg-slate-800/40 text-slate-400 hover:text-slate-200 transition duration-200 font-medium">
                            <span class="step-num w-8 h-8 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold text-sm shrink-0 transition-colors duration-200">02</span>
                            <span class="text-sm truncate">Cargar fondos (USDC)</span>
                        </button>
                        <!-- Botón Paso 3 -->
                        <button type="button" data-step="3" class="step-nav-btn w-full flex items-center gap-4 p-4 rounded-xl border border-transparent text-left hover:bg-slate-800/40 text-slate-400 hover:text-slate-200 transition duration-200 font-medium">
                            <span class="step-num w-8 h-8 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold text-sm shrink-0 transition-colors duration-200">03</span>
                            <span class="text-sm truncate">Crear API en Binance</span>
                        </button>
                        <!-- Botón Paso 4 -->
                        <button type="button" data-step="4" class="step-nav-btn w-full flex items-center gap-4 p-4 rounded-xl border border-transparent text-left hover:bg-slate-800/40 text-slate-400 hover:text-slate-200 transition duration-200 font-medium">
                            <span class="step-num w-8 h-8 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold text-sm shrink-0 transition-colors duration-200">04</span>
                            <span class="text-sm truncate">Vincular cuenta</span>
                        </button>
                        <!-- Botón Paso 5 -->
                        <button type="button" data-step="5" class="step-nav-btn w-full flex items-center gap-4 p-4 rounded-xl border border-transparent text-left hover:bg-slate-800/40 text-slate-400 hover:text-slate-200 transition duration-200 font-medium">
                            <span class="step-num w-8 h-8 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold text-sm shrink-0 transition-colors duration-200">05</span>
                            <span class="text-sm truncate">Activar Bot de trading</span>
                        </button>
                        <!-- Botón Paso 6 -->
                        <button type="button" data-step="6" class="step-nav-btn w-full flex items-center gap-4 p-4 rounded-xl border border-transparent text-left hover:bg-slate-800/40 text-slate-400 hover:text-slate-200 transition duration-200 font-medium">
                            <span class="step-num w-8 h-8 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold text-sm shrink-0 transition-colors duration-200">06</span>
                            <span class="text-sm truncate">Monitorización</span>
                        </button>
                        <!-- Botón Paso 7 -->
                        <button type="button" data-step="7" class="step-nav-btn w-full flex items-center gap-4 p-4 rounded-xl border border-transparent text-left hover:bg-slate-800/40 text-slate-400 hover:text-slate-200 transition duration-200 font-medium">
                            <span class="step-num w-8 h-8 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold text-sm shrink-0 transition-colors duration-200">07</span>
                            <span class="text-sm truncate">Realizar un Retiro</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Detalle del Paso y Video -->
            <div id="help-step-details" class="lg:col-span-7 hidden lg:block transition-all duration-300">
                <!-- Botón Volver (solo visible en móvil) -->
                <div class="lg:hidden mb-6">
                    <button type="button" id="back-to-steps-btn" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] text-xs font-bold text-slate-300 hover:text-white transition duration-200 shadow-md">
                        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Volver a la lista de temas
                    </button>
                </div>
                <!-- Contenido Paso 1 -->
                <div id="help-step-content-1" class="help-step-content space-y-6">
                    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md">
                        <div class="flex items-center gap-3.5 mb-6">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 font-bold text-sm shrink-0">1</span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">Crear cuenta en Binance</h2>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed mb-6">
                            Para poder utilizar el bot en modo Real, necesitas tener una cuenta en la plataforma Binance. Sigue estos sencillos pasos para registrarte de forma gratuita:
                        </p>
                        <ul class="space-y-4 mb-8 text-sm text-slate-300">
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <div>
                                    Haz clic en el enlace de invitación para registrarte directamente:
                                    <a href="https://www.binance.com/register?ref=142891053" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-violet-400 hover:text-violet-300 font-semibold underline underline-offset-2 transition-all duration-200 ml-1">
                                        abrir Binance
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    Completa el proceso de registro introduciendo tu correo electrónico o teléfono y verifica tu identidad (KYC) para habilitar todas las operaciones de la cuenta.
                                </span>
                            </li>
                        </ul>

                        <!-- Video Player Card -->
                        <div class="pt-6 border-t border-slate-800/60 space-y-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Guía Visual en Video</span>
                            <div class="video-container relative rounded-2xl overflow-hidden border border-white/5 bg-slate-950 aspect-video shadow-2xl flex items-center justify-center group">
                                <video id="help-video-1" controls class="w-full h-full hidden absolute inset-0 z-10" src=""></video>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 to-violet-950/20 flex flex-col items-center justify-center p-6 text-center space-y-4 z-0 transition-opacity duration-300">
                                    <div class="w-16 h-16 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition duration-300 shadow-lg">
                                        <svg class="w-8 h-8 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white mb-1">Videotutorial — Crear cuenta en Binance</h4>
                                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                            Este video explicativo estará disponible próximamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enlace al Siguiente Tema -->
                        <div class="pt-6 border-t border-slate-800/60 flex justify-end">
                            <button type="button" onclick="selectHelpStep(2)" class="inline-flex items-center gap-1.5 text-xs font-bold text-violet-400 hover:text-violet-300 transition duration-200 cursor-pointer">
                                Siguiente: Cargar fondos (USDC)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenido Paso 2 -->
                <div id="help-step-content-2" class="help-step-content space-y-6 hidden">
                    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md">
                        <div class="flex items-center gap-3.5 mb-6">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 font-bold text-sm shrink-0">2</span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">Cargar fondos para operar</h2>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed mb-6">
                            Para que el bot pueda abrir inversiones, es necesario tener saldo disponible en tu cuenta de **Margen Cruzado** (Cross Margin) en la moneda estable **USDC**:
                        </p>
                        <ul class="space-y-4 mb-8 text-sm text-slate-300">
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Depositar fondos:</strong> Dirígete a la sección **Deposit** en Binance, haz clic en **Buy with EUR** (o tu moneda local), realiza el pago con tarjeta o transferencia y adquiere la moneda estable **USDC**.
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <div class="flex-grow">
                                    <span><strong>Transferir a Margen Cruzado:</strong> Haz clic en el botón **Transfer** (Transferir) y realiza la transferencia interna configurando los siguientes datos:</span>
                                    <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 text-xs mt-3 text-slate-400 space-y-1.5 max-w-md">
                                        <div class="flex justify-between border-b border-slate-800/40 pb-1.5">
                                            <span class="font-semibold text-slate-300">De (From):</span>
                                            <span>Fiat and Spot</span>
                                        </div>
                                        <div class="flex justify-between border-b border-slate-800/40 pb-1.5">
                                            <span class="font-semibold text-slate-300">Para (To):</span>
                                            <span>Cross Margin (Margen Cruzado)</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="font-semibold text-slate-300">Moneda (Coin):</span>
                                            <span class="text-violet-400 font-bold">USDC</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>

                        <!-- Video Player Card -->
                        <div class="pt-6 border-t border-slate-800/60 space-y-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Guía Visual en Video</span>
                            <div class="video-container relative rounded-2xl overflow-hidden border border-white/5 bg-slate-950 aspect-video shadow-2xl flex items-center justify-center group">
                                <video id="help-video-2" controls class="w-full h-full hidden absolute inset-0 z-10" src=""></video>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 to-violet-950/20 flex flex-col items-center justify-center p-6 text-center space-y-4 z-0 transition-opacity duration-300">
                                    <div class="w-16 h-16 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition duration-300 shadow-lg">
                                        <svg class="w-8 h-8 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white mb-1">Videotutorial — Cargar fondos</h4>
                                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                            Este video explicativo estará disponible próximamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enlace al Siguiente Tema -->
                        <div class="pt-6 border-t border-slate-800/60 flex justify-end">
                            <button type="button" onclick="selectHelpStep(3)" class="inline-flex items-center gap-1.5 text-xs font-bold text-violet-400 hover:text-violet-300 transition duration-200 cursor-pointer">
                                Siguiente: Crear API en Binance
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenido Paso 3 -->
                <div id="help-step-content-3" class="help-step-content space-y-6 hidden">
                    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md">
                        <div class="flex items-center gap-3.5 mb-6">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 font-bold text-sm shrink-0">3</span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">Crear API en Binance</h2>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed mb-6">
                            Para que el bot pueda leer tu balance y lanzar órdenes en modo Real, es necesario configurar una API Key en Binance aplicando estrictas medidas de seguridad:
                        </p>
                        <ul class="space-y-4 mb-8 text-sm text-slate-300">
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Generar API:</strong> En Binance, ve a tu perfil &rarr; **API Management** (Gestión de API) y haz clic en **Create API**. Elige la opción **System Generated** (Generada por el sistema) y ponle un nombre (por ejemplo: "ViBo Bot").
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Guardar claves:</strong> Copia la **API Key** y la **Secret Key** que se muestran. <em>(Atención: la Secret Key no se volverá a mostrar nunca más después de recargar la pantalla).</em>
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <div class="flex-grow">
                                    <span><strong>Restringir acceso IP (Seguridad Obligatoria):</strong> Haz clic en **Edit Restrictions** (Editar restricciones), marca **Restrict access to trusted IPs only** (Restringir acceso solo a IPs de confianza) e ingresa la dirección IP de nuestro servidor:</span>
                                    <div class="flex items-center gap-2 mt-2">
                                        <code class="bg-slate-900 border border-slate-800 text-violet-400 px-3 py-1.5 rounded-xl font-mono font-bold text-xs select-all">82.223.44.83</code>
                                        <button type="button" onclick="copyIpToClipboard()" class="bg-violet-600 hover:bg-violet-500 text-white text-xs font-bold py-1.5 px-3 rounded-xl transition duration-200 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                            Copiar IP
                                        </button>
                                    </div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <div class="flex-grow">
                                    <span><strong>Habilitar permisos necesarios:</strong> En la sección de restricciones, marca únicamente las casillas de operaciones requeridas:</span>
                                    <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3 text-xs mt-3 text-slate-400 space-y-1.5">
                                        <div class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span> Enable Spot & Margin & Stock Trading</div>
                                        <div class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span> Enable Margin Loan, Repay & Transfer</div>
                                    </div>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Guardar cambios:</strong> Haz clic en **Save** e introduce tu código de verificación 2FA.
                                </span>
                            </li>
                        </ul>

                        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs">
                            <strong>REGLA DE SEGURIDAD ABSOLUTA:</strong> Bajo ninguna circunstancia habilites la casilla "Permitir Retiros" (Enable Withdrawals). Para garantizar la seguridad del capital de nuestros usuarios, el bot rechazará y pausará la cuenta en el acto si detecta que la API tiene permisos de retiro.
                        </div>

                        <!-- Video Player Card -->
                        <div class="pt-6 border-t border-slate-800/60 space-y-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Guía Visual en Video</span>
                            <div class="video-container relative rounded-2xl overflow-hidden border border-white/5 bg-slate-950 aspect-video shadow-2xl flex items-center justify-center group">
                                <video id="help-video-3" controls class="w-full h-full hidden absolute inset-0 z-10" src=""></video>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 to-violet-950/20 flex flex-col items-center justify-center p-6 text-center space-y-4 z-0 transition-opacity duration-300">
                                    <div class="w-16 h-16 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition duration-300 shadow-lg">
                                        <svg class="w-8 h-8 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white mb-1">Videotutorial — Crear API</h4>
                                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                            Este video explicativo estará disponible próximamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enlace al Siguiente Tema -->
                        <div class="pt-6 border-t border-slate-800/60 flex justify-end">
                            <button type="button" onclick="selectHelpStep(4)" class="inline-flex items-center gap-1.5 text-xs font-bold text-violet-400 hover:text-violet-300 transition duration-200 cursor-pointer">
                                Siguiente: Vincular cuenta
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenido Paso 4 -->
                <div id="help-step-content-4" class="help-step-content space-y-6 hidden">
                    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md">
                        <div class="flex items-center gap-3.5 mb-6">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 font-bold text-sm shrink-0">4</span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">Vincular cuenta de Binance</h2>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed mb-6">
                            Establece el enlace entre Binance y tu panel de trading en ViBo Invest de manera encriptada y 100% segura:
                        </p>
                        <ul class="space-y-4 mb-8 text-sm text-slate-300">
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    Haz clic en el botón **Conectar Binance** en la tarjeta de Conexión del Panel de Control.
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    Pega la **API KEY** y la **SECRET KEY** de Binance generadas en el paso anterior en los campos del formulario.
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    Presiona en **Verificar y Vincular Cuenta**. El sistema realizará una validación automática inmediata y, si todo está correcto, tu cuenta quedará vinculada de forma segura.
                                </span>
                            </li>
                        </ul>

                        <!-- Video Player Card -->
                        <div class="pt-6 border-t border-slate-800/60 space-y-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Guía Visual en Video</span>
                            <div class="video-container relative rounded-2xl overflow-hidden border border-white/5 bg-slate-950 aspect-video shadow-2xl flex items-center justify-center group">
                                <video id="help-video-4" controls class="w-full h-full hidden absolute inset-0 z-10" src=""></video>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 to-violet-950/20 flex flex-col items-center justify-center p-6 text-center space-y-4 z-0 transition-opacity duration-300">
                                    <div class="w-16 h-16 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition duration-300 shadow-lg">
                                        <svg class="w-8 h-8 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white mb-1">Videotutorial — Vincular cuenta</h4>
                                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                            Este video explicativo estará disponible próximamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enlace al Siguiente Tema -->
                        <div class="pt-6 border-t border-slate-800/60 flex justify-end">
                            <button type="button" onclick="selectHelpStep(5)" class="inline-flex items-center gap-1.5 text-xs font-bold text-violet-400 hover:text-violet-300 transition duration-200 cursor-pointer">
                                Siguiente: Activar Bot de trading
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenido Paso 5 -->
                <div id="help-step-content-5" class="help-step-content space-y-6 hidden">
                    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md">
                        <div class="flex items-center gap-3.5 mb-6">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 font-bold text-sm shrink-0">5</span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">Activar Bot de trading</h2>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed mb-6">
                            Una vez completada la vinculación de tu API de Binance, pon a operar la estrategia en mercado real:
                        </p>
                        <ul class="space-y-4 mb-8 text-sm text-slate-300">
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Seleccionar Riesgo:</strong> En la tarjeta "Nivel de Riesgo" de tu Panel de Control, selecciona el perfil (Conservador, Balanceado o Agresivo) en el menú y haz clic en el botón **Cambiar**.
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Cambiar a Modo Real:</strong> En la tarjeta de Modo de Operación, haz clic en **Cambiar a modo Real** para conmutar del entorno de simulación al dinero real.
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Activar Bot:</strong> En la tarjeta "Estado del Bot", presiona en **Activar Bot**. Confirma tu decisión en el popup interactivo de seguridad. El bot empezará a seguir automáticamente las señales vigentes del proveedor.
                                </span>
                            </li>
                        </ul>

                        <!-- Video Player Card -->
                        <div class="pt-6 border-t border-slate-800/60 space-y-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Guía Visual en Video</span>
                            <div class="video-container relative rounded-2xl overflow-hidden border border-white/5 bg-slate-950 aspect-video shadow-2xl flex items-center justify-center group">
                                <video id="help-video-5" controls class="w-full h-full hidden absolute inset-0 z-10" src=""></video>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 to-violet-950/20 flex flex-col items-center justify-center p-6 text-center space-y-4 z-0 transition-opacity duration-300">
                                    <div class="w-16 h-16 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition duration-300 shadow-lg">
                                        <svg class="w-8 h-8 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white mb-1">Videotutorial — Activar Bot</h4>
                                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                            Este video explicativo estará disponible próximamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enlace al Siguiente Tema -->
                        <div class="pt-6 border-t border-slate-800/60 flex justify-end">
                            <button type="button" onclick="selectHelpStep(6)" class="inline-flex items-center gap-1.5 text-xs font-bold text-violet-400 hover:text-violet-300 transition duration-200 cursor-pointer">
                                Siguiente: Monitorización
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenido Paso 6 -->
                <div id="help-step-content-6" class="help-step-content space-y-6 hidden">
                    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md">
                        <div class="flex items-center gap-3.5 mb-6">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 font-bold text-sm shrink-0">6</span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">Monitorización</h2>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed mb-6">
                            Supervisa el comportamiento del bot sin tecnicismos complejos:
                        </p>
                        <ul class="space-y-4 mb-8 text-sm text-slate-300">
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Confirmación de conexión:</strong> En la cabecera del panel de control verás la insignia verde **Binance Conectado**.
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Identificar inversiones activas:</strong> Revisa el distintivo superior para conocer el estado actual de tu balance: **Comprando-BTC** (cuando hay una operación al alza abierta), **Vendiendo-BTC** (cuando hay una posición a la baja abierta) o **Fuera del Mercado** (cuando no hay posiciones abiertas y tu dinero está refugiado en USDC sin riesgo de precio).
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Evolución y saldos:</strong> El valor en la sección **Balance Total** fluctúa en tiempo real reflejando el patrimonio de tu billetera de Margen, reflejado también de forma simplificada en el gráfico de evolución.
                                </span>
                            </li>
                        </ul>

                        <!-- Video Player Card -->
                        <div class="pt-6 border-t border-slate-800/60 space-y-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Guía Visual en Video</span>
                            <div class="video-container relative rounded-2xl overflow-hidden border border-white/5 bg-slate-950 aspect-video shadow-2xl flex items-center justify-center group">
                                <video id="help-video-6" controls class="w-full h-full hidden absolute inset-0 z-10" src=""></video>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 to-violet-950/20 flex flex-col items-center justify-center p-6 text-center space-y-4 z-0 transition-opacity duration-300">
                                    <div class="w-16 h-16 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition duration-300 shadow-lg">
                                        <svg class="w-8 h-8 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white mb-1">Videotutorial — Monitorización</h4>
                                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                            Este video explicativo estará disponible próximamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Enlace al Siguiente Tema -->
                        <div class="pt-6 border-t border-slate-800/60 flex justify-end">
                            <button type="button" onclick="selectHelpStep(7)" class="inline-flex items-center gap-1.5 text-xs font-bold text-violet-400 hover:text-violet-300 transition duration-200 cursor-pointer">
                                Siguiente: Realizar un Retiro
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Contenido Paso 7 -->
                <div id="help-step-content-7" class="help-step-content space-y-6 hidden">
                    <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 md:p-8 shadow-md">
                        <div class="flex items-center gap-3.5 mb-6">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/10 text-violet-400 border border-violet-500/20 font-bold text-sm shrink-0">7</span>
                            <h2 class="text-2xl font-bold text-white tracking-tight">Para hacer un retiro</h2>
                        </div>
                        <p class="text-sm text-slate-300 leading-relaxed mb-6">
                            El bot de ViBo Invest no tiene autorización para realizar retiros sobre tu cuenta de Binance. Cuando decidas retirar fondos a tu banco, sigue estos pasos ordenados de seguridad:
                        </p>
                        <ul class="space-y-4 mb-8 text-sm text-slate-300">
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Pausar Bot:</strong> Ve al Panel de Control de ViBo Invest y haz clic en **Pausar Bot**. Esto asegura el cierre de cualquier operación activa y consolida tu patrimonio en USDC, previniendo que el bot abra transacciones durante el retiro.
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Acceder a Binance:</strong> Inicia sesión en tu cuenta de Binance y dirígete al menú superior &rarr; billetera **Funding** (Financiación o Spot).
                                </span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-5 h-5 rounded-full bg-violet-500/10 text-violet-400 flex items-center justify-center text-xs shrink-0 mt-0.5">&rarr;</span>
                                <span>
                                    <strong>Retirar fondos:</strong> Haz clic en **Withdraw** (Retirar), selecciona la moneda estable USDC o moneda fiat (como EUR), introduce tu cuenta o destino y confirma el retiro.
                                </span>
                            </li>
                        </ul>

                        <!-- Video Player Card -->
                        <div class="pt-6 border-t border-slate-800/60 space-y-3">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Guía Visual en Video</span>
                            <div class="video-container relative rounded-2xl overflow-hidden border border-white/5 bg-slate-950 aspect-video shadow-2xl flex items-center justify-center group">
                                <video id="help-video-7" controls class="w-full h-full hidden absolute inset-0 z-10" src=""></video>
                                <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 to-violet-950/20 flex flex-col items-center justify-center p-6 text-center space-y-4 z-0 transition-opacity duration-300">
                                    <div class="w-16 h-16 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center text-violet-400 group-hover:scale-110 transition duration-300 shadow-lg">
                                        <svg class="w-8 h-8 fill-current translate-x-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white mb-1">Videotutorial — Realizar retiros</h4>
                                        <p class="text-xs text-slate-400 max-w-sm mx-auto">
                                            Este video explicativo estará disponible próximamente.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div> <!-- Closes #tab-content-help -->
    </div> <!-- Closes the main wrapper (line 4) -->

    <!-- Modal de Confirmación Premium para Activar/Pausar Bot -->
    <div id="bot-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-description" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300">
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.08)] rounded-2xl p-6 shadow-2xl max-w-sm w-full relative transform scale-95 transition-transform duration-300 ease-out">
            <!-- Glow decorativo superior -->
            <div class="absolute -top-10 left-1/2 -translate-x-1/2 w-32 h-32 bg-violet-500/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="flex flex-col items-center text-center">
                <!-- Icono Dinámico -->
                <div id="modal-icon-container" class="w-12 h-12 rounded-full flex items-center justify-center mb-4 border">
                    <svg id="modal-icon" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <!-- Se llena dinámicamente -->
                    </svg>
                </div>
                
                <h3 id="modal-title" class="text-lg font-bold text-white mb-2"></h3>
                <p id="modal-description" class="text-xs text-slate-400 leading-relaxed mb-6"></p>
                
                <div class="flex gap-3 w-full">
                    <button type="button" id="modal-cancel-btn" class="flex-1 text-xs font-bold py-2.5 px-4 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 transition duration-200">
                        Cancelar
                    </button>
                    <button type="button" id="modal-confirm-btn" class="flex-1 text-xs font-bold py-2.5 px-4 rounded-xl text-white transition duration-200">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación de Eliminación de Cuenta -->
    <div id="delete-account-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeDeleteAccountModal()"></div>
        
        <div class="bg-[hsl(223,47%,14%)] border border-rose-500/20 rounded-2xl p-6 shadow-2xl relative max-w-md w-full mx-4 z-10 animate-fade-in space-y-6">
            <div class="flex items-center gap-3 text-rose-400">
                <span class="w-10 h-10 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </span>
                <h3 class="text-lg font-bold text-white">¿Eliminar tu cuenta definitivamente?</h3>
            </div>
            
            <div class="space-y-3 text-xs text-slate-300">
                <p>
                    Estás a punto de eliminar de forma permanente tu cuenta de **ViBo Invest** y todos los datos asociados de acuerdo a la regulación de privacidad (GDPR).
                </p>
                <div class="bg-rose-500/5 border border-rose-500/10 rounded-xl p-3 text-[11px] text-rose-300 space-y-2">
                    <p class="font-bold flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Aviso Financiero Importante:
                    </p>
                    <p>
                        Si tienes el bot activo, el sistema intentará **pausar el bot y cerrar de forma preventiva las posiciones abiertas** en tu cuenta de Binance antes de borrar tus credenciales.
                    </p>
                    <p class="font-semibold text-rose-200">
                        Esta acción es irreversible y perderás tu historial de inmediato.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" 
                        onclick="closeDeleteAccountModal()" 
                        class="text-xs font-semibold py-2 px-4 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800 transition duration-200 cursor-pointer">
                    Cancelar
                </button>
                <form action="{{ route('account.delete') }}" method="POST" class="m-0 p-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="text-xs font-bold py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-500 text-white shadow-md transition duration-200 cursor-pointer">
                        Sí, eliminar cuenta
                    </button>
                </form>
            </div>
        </div>
    </div>

<script>
(function () {
    const amountEl = document.getElementById('balance-amount');
    const changeEl = document.getElementById('balance-change');
    const chart = document.getElementById('balance-chart');
    const emptyEl = document.getElementById('balance-chart-empty');
    const errorEl = document.getElementById('balance-error');
    const rangeButtons = document.querySelectorAll('.range-btn');

    const botIndicator = document.getElementById('bot-indicator');
    const botStatusText = document.getElementById('bot-status-text');
    const botStatusDesc = document.getElementById('bot-status-desc');
    const botToggleForm = document.getElementById('bot-toggle-form');
    const botToggleBtn = document.getElementById('bot-toggle-btn');

    let currentRange = 'month';
    let originalBalance = null;
    let originalChangeMessage = null;
    let chartHovering = false;
    let liveBalance = null;
    let currentSeries = [];
    // Modo actual del bot en el cliente: solo el modo real tiene patrimonio "en
    // vivo". Sirve para descartar respuestas del sondeo en vivo que lleguen tarde,
    // después de cambiar a simulación, y que de otro modo añadirían el valor real
    // como último punto de la curva simulada.
    let currentBotMode = @json($user->bot_mode);

    const formatDollar = (value) =>
        new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);

    const formatDollarShort = (val) =>
        new Intl.NumberFormat('es-ES', { maximumFractionDigits: 0 }).format(val) + '$';

    function formatDateXAxis(dateStr, range) {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        if (range === 'day') {
            return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
        } else if (range === 'week') {
            // Eje X en semana: "lun 12"
            return date.toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric' });
        } else {
            // Eje X en mes: "12 jun"
            return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
        }
    }

    function formatDateTooltip(dateStr, range) {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        if (range === 'day') {
            const timeStr = date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            return `Hoy, ${timeStr}`;
        } else if (range === 'week') {
            const weekday = date.toLocaleDateString('es-ES', { weekday: 'short' });
            const dayMonth = date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
            const timeStr = date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            return `${weekday} ${dayMonth}, ${timeStr}`;
        } else {
            return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'long', year: 'numeric' });
        }
    }

    function formatDateShort(dateStr, range) {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        if (range === 'day') {
            return date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) + 'h';
        } else {
            return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) + 'h';
        }
    }

    function renderBalance(value) {
        amountEl.innerHTML = formatDollar(value) + '<span class="text-3xl text-slate-400 font-semibold">$</span>';
    }

    // Transición sutil para denotar "cuenta viva" (Escenario 3)
    function pulseBalance() {
        amountEl.classList.add('scale-105', 'text-violet-300');
        setTimeout(() => amountEl.classList.remove('scale-105', 'text-violet-300'), 600);
    }

    function highlightRange() {
        rangeButtons.forEach((btn) => {
            const active = btn.dataset.range === currentRange;
            btn.classList.toggle('bg-violet-600', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('text-slate-400', !active);
        });
    }

    // Gráfico lineal en SVG con ejes interactivos y tooltip (US03)
    function drawChart(series) {
        const hasData = series.length >= 2;
        chart.classList.toggle('hidden', !hasData);
        emptyEl.classList.toggle('hidden', hasData);
        if (!hasData) { chart.innerHTML = ''; return; }

        const W = 600, H = 200;
        const PAD_TOP = 15;
        const PAD_BOTTOM = 25;
        const PAD_LEFT = 15;
        const PAD_RIGHT = 75;

        const values = series.map((p) => p.value);
        let min = Math.min(...values);
        let max = Math.max(...values);

        // Evitar auto-escalado agresivo en micro-fluctuaciones (US03)
        // Forzamos un rango mínimo del 5% del valor promedio para atenuar ruido
        const avg = (min + max) / 2 || 1;
        const minRange = avg * 0.05; // 5% de amplitud mínima
        if ((max - min) < minRange) {
            const padding = (minRange - (max - min)) / 2;
            min -= padding;
            max += padding;
        }

        const range = max - min || 1;
        const x = (i) => PAD_LEFT + (i / (series.length - 1)) * (W - PAD_LEFT - PAD_RIGHT);
        const y = (v) => H - PAD_BOTTOM - ((v - min) / range) * (H - PAD_TOP - PAD_BOTTOM);

        const linePoints = series.map((p, i) => `${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' ');
        const areaPoints = `${PAD_LEFT},${H - PAD_BOTTOM} ${linePoints} ${(W - PAD_RIGHT)},${H - PAD_BOTTOM}`;

        // Generar líneas de cuadrícula y etiquetas del eje Y (valores)
        const gridValues = [max, (min + max) / 2, min];
        let yAxisHtml = '';
        gridValues.forEach((val) => {
            const yVal = y(val);
            yAxisHtml += `
                <line x1="${PAD_LEFT}" y1="${yVal}" x2="${W - PAD_RIGHT}" y2="${yVal}" stroke="rgba(255, 255, 255, 0.06)" stroke-width="1" stroke-dasharray="4 4" />
                <text x="${W - PAD_RIGHT + 8}" y="${yVal}" fill="#94a3b8" font-size="10" font-family="system-ui, -apple-system, sans-serif" alignment-baseline="middle" text-anchor="start">${formatDollarShort(val)}</text>
            `;
        });

        // Generar etiquetas del eje X (temporal)
        const midIndex = Math.floor((series.length - 1) / 2);
        const xAxisIndices = [0, midIndex, series.length - 1].filter((val, index, self) => self.indexOf(val) === index);
        let xAxisHtml = '';
        xAxisIndices.forEach((idx, i) => {
            const p = series[idx];
            const xVal = x(idx);
            let anchor = 'middle';
            if (xAxisIndices.length > 1) {
                if (i === 0) anchor = 'start';
                else if (i === xAxisIndices.length - 1) anchor = 'end';
            }
            xAxisHtml += `
                <text x="${xVal}" y="${H - 5}" fill="#94a3b8" font-size="10" font-family="system-ui, -apple-system, sans-serif" text-anchor="${anchor}">${formatDateXAxis(p.t, currentRange)}</text>
            `;
        });

        chart.innerHTML = `
            <defs>
                <linearGradient id="balance-area-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="rgba(139, 92, 246, 0.35)"/>
                    <stop offset="100%" stop-color="rgba(139, 92, 246, 0)"/>
                </linearGradient>
            </defs>
            
            <!-- Cuadrícula y eje Y -->
            ${yAxisHtml}
            
            <!-- Relleno de área y línea de la gráfica -->
            <polygon points="${areaPoints}" fill="url(#balance-area-fill)"/>
            <polyline points="${linePoints}" fill="none" stroke="hsl(263, 70%, 62%)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            
            <!-- Eje X -->
            ${xAxisHtml}
            
            <!-- Guía vertical de interacción (oculta por defecto) -->
            <line id="chart-guide-line" x1="0" y1="${PAD_TOP}" x2="0" y2="${H - PAD_BOTTOM}" stroke="rgba(139, 92, 246, 0.4)" stroke-width="1.5" stroke-dasharray="3 3" style="display: none;" />
            
            <!-- Punto indicador (oculto por defecto) -->
            <circle id="chart-indicator-dot" r="6" fill="hsl(263, 70%, 62%)" stroke="#ffffff" stroke-width="2" style="display: none; filter: drop-shadow(0px 2px 4px rgba(0,0,0,0.5));" />
            
            <!-- Overlay invisible para capturar eventos táctiles y de ratón -->
            <rect id="chart-overlay-rect" x="${PAD_LEFT}" y="${PAD_TOP}" width="${W - PAD_LEFT - PAD_RIGHT}" height="${H - PAD_TOP - PAD_BOTTOM}" fill="#000" opacity="0" style="cursor: crosshair;" />
        `;

        // Referencias del DOM para la interacción
        const overlay = document.getElementById('chart-overlay-rect');
        const guideLine = document.getElementById('chart-guide-line');
        const indicatorDot = document.getElementById('chart-indicator-dot');
        const tooltip = document.getElementById('chart-tooltip');
        const tooltipDate = document.getElementById('tooltip-date');
        const tooltipValue = document.getElementById('tooltip-value');

        function handleHover(e) {
            if (!series || series.length < 2) return;
            const rect = chart.getBoundingClientRect();
            
            let clientX;
            if (e.touches && e.touches.length > 0) {
                clientX = e.touches[0].clientX;
            } else {
                clientX = e.clientX;
            }

            const mouseX = clientX - rect.left;
            const svgX = (mouseX / rect.width) * W;

            const totalXRange = W - PAD_LEFT - PAD_RIGHT;
            const relativeX = svgX - PAD_LEFT;
            const percentX = relativeX / totalXRange;
            const index = Math.round(percentX * (series.length - 1));
            const safeIndex = Math.max(0, Math.min(series.length - 1, index));

            const point = series[safeIndex];
            const ptX = x(safeIndex);
            const ptY = y(point.value);

            // Posicionar guía y dot
            guideLine.setAttribute('x1', ptX);
            guideLine.setAttribute('x2', ptX);
            guideLine.style.display = 'block';

            indicatorDot.setAttribute('cx', ptX);
            indicatorDot.setAttribute('cy', ptY);
            indicatorDot.style.display = 'block';

            // Actualizar Tooltip
            tooltipDate.textContent = formatDateTooltip(point.t, currentRange);
            tooltipValue.textContent = formatDollar(point.value) + ' $';
            tooltip.style.display = 'block';

            const tooltipWidth = tooltip.offsetWidth || 140;
            const tooltipHeight = tooltip.offsetHeight || 60;

            let tooltipX = (ptX / W) * rect.width - (tooltipWidth / 2);
            let tooltipY = (ptY / H) * rect.height - tooltipHeight - 15;

            // Evitar desbordes del tooltip
            if (tooltipX < 0) {
                tooltipX = 5;
            } else if (tooltipX + tooltipWidth > rect.width) {
                tooltipX = rect.width - tooltipWidth - 5;
            }
            if (tooltipY < 0) {
                tooltipY = (ptY / H) * rect.height + 15;
            }

            tooltip.style.left = `${tooltipX}px`;
            tooltip.style.top = `${tooltipY}px`;

            // Actualizar Balance en Cabecera
            chartHovering = true;
            renderBalance(point.value);
            changeEl.textContent = `Balance el ${formatDateShort(point.t, currentRange)}`;
            changeEl.classList.add('text-violet-400', 'font-semibold');
        }

        function handleLeave() {
            chartHovering = false;
            guideLine.style.display = 'none';
            indicatorDot.style.display = 'none';
            tooltip.style.display = 'none';

            if (originalBalance !== null) {
                renderBalance(originalBalance);
            }
            if (originalChangeMessage !== null) {
                changeEl.textContent = originalChangeMessage;
                changeEl.classList.remove('text-violet-400', 'font-semibold');
            }
        }

        overlay.addEventListener('mousemove', handleHover);
        overlay.addEventListener('touchmove', handleHover, { passive: true });
        overlay.addEventListener('mouseleave', handleLeave);
        overlay.addEventListener('touchend', handleLeave);
    }

    function updateChartWithLive(balance) {
        // En simulación no hay valor en vivo: nunca se añade un punto "live" a la
        // curva. Esto bloquea también las respuestas del sondeo en vivo que lleguen
        // tarde tras cambiar de real a simulación (condición de carrera).
        if (currentBotMode !== 'real') return;
        if (!currentSeries || currentSeries.length === 0) return;

        const lastIndex = currentSeries.length - 1;
        if (currentSeries[lastIndex].isLive) {
            currentSeries[lastIndex].value = balance;
            currentSeries[lastIndex].t = new Date().toISOString();
        } else {
            currentSeries.push({
                t: new Date().toISOString(),
                value: balance,
                isLive: true
            });
        }
        drawChart(currentSeries);
    }

    async function refreshHistory(pulse = false) {
        errorEl.classList.add('hidden');

        try {
            const response = await fetch(`{{ route('dashboard.balance') }}?range=${currentRange}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                throw new Error(body.message || 'No se pudo cargar la evolución del balance.');
            }

            const data = await response.json();
            
            // Guardar valores originales para restaurar tras el hover interactivo (US03)
            originalBalance = data.current_balance;
            originalChangeMessage = data.change_message;

            currentSeries = data.series || [];

            if (currentBotMode === 'real' && liveBalance !== null && currentSeries.length > 0) {
                updateChartWithLive(liveBalance);
            } else {
                drawChart(currentSeries);
            }
            changeEl.textContent = data.change_message;

            if (data.current_balance !== null) {
                renderBalance(data.current_balance);
                if (pulse) pulseBalance();
            }
        } catch (err) {
            errorEl.textContent = err.message;
            errorEl.classList.remove('hidden');
        }
    }

    function updateBotPositionUi(position) {
        const badges = [
            document.getElementById('bot-position-badge'),
            document.getElementById('top-bot-position-badge')
        ].filter(el => el !== null);

        badges.forEach(badge => {
            badge.className = 'text-[11px] font-bold px-2 py-0.5 rounded-lg';
            if (position === 'LONG') {
                badge.classList.add('bg-emerald-500/10', 'text-emerald-400', 'border', 'border-emerald-500/20');
                badge.textContent = 'Comprando-BTC';
            } else if (position === 'SHORT') {
                badge.classList.add('bg-rose-500/10', 'text-rose-400', 'border', 'border-rose-500/20');
                badge.textContent = 'Vendiendo-BTC';
            } else {
                badge.classList.add('bg-slate-500/10', 'text-slate-400', 'border', 'border-slate-500/20');
                badge.textContent = 'Fuera de mercado';
            }
        });
    }

    function updateBotUi(isActive) {
        if (isActive) {
            botIndicator.innerHTML = `
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            `;
            botStatusText.textContent = 'Activo';
            botStatusDesc.textContent = 'Ejecutando señales del mercado.';
            botToggleBtn.textContent = 'Pausar Bot';
            botToggleBtn.className = 'w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30';
        } else {
            botIndicator.innerHTML = `
                <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
            `;
            botStatusText.textContent = 'Pausado';
            botStatusDesc.textContent = 'El bot no realizará operaciones.';
            botToggleBtn.textContent = 'Activar Bot';
            botToggleBtn.className = 'w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-emerald-600 hover:bg-emerald-500 text-white';
            updateBotPositionUi('CLOSE');
        }
    }

    function showToast(message, isError = false) {
        const alertContainer = document.createElement('div');
        alertContainer.className = `fixed bottom-5 right-5 p-4 rounded-2xl shadow-lg border text-xs font-bold bg-slate-900 text-white animate-fade-in z-50 ${isError ? 'border-rose-500/25 text-rose-300' : 'border-violet-500/20 text-white'}`;
        alertContainer.textContent = message;
        document.body.appendChild(alertContainer);
        setTimeout(() => alertContainer.remove(), 4000);
    }

    function showModal(title, description, isPauseAction, onConfirm) {
        const modal = document.getElementById('bot-confirm-modal');
        const modalBox = modal.querySelector('div');
        const modalTitle = document.getElementById('modal-title');
        const modalDesc = document.getElementById('modal-description');
        const confirmBtn = document.getElementById('modal-confirm-btn');
        const cancelBtn = document.getElementById('modal-cancel-btn');
        const iconContainer = document.getElementById('modal-icon-container');
        const iconSvg = document.getElementById('modal-icon');

        modalTitle.textContent = title;
        modalDesc.textContent = description;

        if (isPauseAction) {
            iconContainer.className = 'w-12 h-12 rounded-full flex items-center justify-center mb-4 bg-amber-500/10 text-amber-400 border border-amber-500/20';
            confirmBtn.className = 'flex-1 text-xs font-bold py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white transition duration-200';
            confirmBtn.textContent = 'Pausar Bot';
            iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />';
        } else {
            iconContainer.className = 'w-12 h-12 rounded-full flex items-center justify-center mb-4 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
            confirmBtn.className = 'flex-1 text-xs font-bold py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white transition duration-200';
            confirmBtn.textContent = 'Activar Bot';
            iconSvg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />';
        }

        modal.classList.remove('hidden');
        modal.offsetHeight; // force reflow
        modal.classList.remove('opacity-0');
        modalBox.classList.remove('scale-95');
        modalBox.classList.add('scale-100');

        const closeModal = () => {
            modal.classList.add('opacity-0');
            modalBox.classList.remove('scale-100');
            modalBox.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
            confirmBtn.onclick = null;
            cancelBtn.onclick = null;
            modal.onclick = null;
            document.removeEventListener('keydown', handleKeyDown);
        };

        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        };

        confirmBtn.onclick = () => {
            closeModal();
            onConfirm();
        };

        cancelBtn.onclick = closeModal;
        
        modal.onclick = (e) => {
            if (e.target === modal) {
                closeModal();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
    }

    if (botToggleForm) {
        botToggleForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const isCurrentlyActive = botStatusText.textContent.trim().toLowerCase() === 'activo';
            const isReal = currentBotMode === 'real';

            const title = isCurrentlyActive ? '¿Pausar la automatización?' : '¿Activar la automatización?';
            const description = isCurrentlyActive 
                ? (isReal 
                    ? 'El bot dejará de seguir las señales del mercado de forma inmediata. Tus posiciones abiertas en Binance se cerrarán por seguridad.' 
                    : 'El bot dejará de seguir las señales del mercado en modo simulación y se cerrará tu posición de prueba.') 
                : (isReal
                    ? 'El bot comenzará a ejecutar operaciones en tu cuenta real de Binance siguiendo la señal vigente del mercado.'
                    : 'El bot comenzará a ejecutar operaciones simuladas siguiendo la señal vigente en el mercado.');

            showModal(title, description, isCurrentlyActive, async () => {
                botToggleBtn.disabled = true;
                botToggleBtn.classList.add('opacity-50');

                try {
                    const formData = new FormData(botToggleForm);
                    const response = await fetch(botToggleForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': formData.get('_token')
                        },
                        body: formData
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(data.message || 'Error al cambiar el estado del bot.');
                    }

                    updateBotUi(data.bot_active);
                    if (data.current_position) {
                        updateBotPositionUi(data.current_position);
                    }
                    showToast(data.message);
                    refreshActivities();

                } catch (err) {
                    showToast(err.message, true);
                } finally {
                    botToggleBtn.disabled = false;
                    botToggleBtn.classList.remove('opacity-50');
                }
            });
        });
    }

    const botToggleModeForm = document.getElementById('bot-toggle-mode-form');
    const botToggleModeBtn = document.getElementById('bot-toggle-mode-btn');
    const botModeText = document.getElementById('bot-mode-text');
    const botModeDesc = document.getElementById('bot-mode-desc');

    if (botToggleModeForm) {
        botToggleModeForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            botToggleModeBtn.disabled = true;
            botToggleModeBtn.classList.add('opacity-50');

            try {
                const formData = new FormData(botToggleModeForm);
                const response = await fetch(botToggleModeForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': formData.get('_token')
                    },
                    body: formData
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'Error al cambiar el modo del bot.');
                }

                const isReal = data.bot_mode === 'real';
                currentBotMode = data.bot_mode;
                botModeText.textContent = isReal ? 'Dinero Real' : 'Simulación';
                botModeDesc.textContent = isReal 
                    ? 'Operando directamente en tu cartera de Binance.' 
                    : 'Operaciones simuladas con capital de prueba.';
                
                botToggleModeBtn.textContent = `Cambiar a Modo ${isReal ? 'Simulación' : 'Real'}`;

                if (data.current_position) {
                    updateBotPositionUi(data.current_position);
                }

                // En simulación no existe patrimonio "en vivo": el último punto del
                // gráfico es el último snapshot histórico, no un equity en tiempo real.
                // Sin esto, el liveBalance heredado del modo real (p. ej. 58$) se
                // añadiría como último punto de la curva simulada.
                if (!isReal) {
                    liveBalance = null;
                }

                // Sync navbar select value
                const navSelect = document.getElementById('nav-mode-select');
                if (navSelect) {
                    navSelect.value = data.bot_mode;
                }

                showToast(data.message);
                refreshHistory(true);
                refreshActivities();

            } catch (err) {
                showToast(err.message, true);
                // Revert navbar select to actual mode
                const navSelect = document.getElementById('nav-mode-select');
                if (navSelect) {
                    navSelect.value = currentBotMode;
                }
            } finally {
                botToggleModeBtn.disabled = false;
                botToggleModeBtn.classList.remove('opacity-50');
            }
        });
    }

    const riskLevelForm = document.getElementById('risk-level-form');
    const riskLevelBtn = document.getElementById('risk-level-btn');
    const riskLevelBadge = document.getElementById('risk-level-badge');
    const riskLevelDesc = document.getElementById('risk-level-desc');
    const riskGauge = document.getElementById('risk-gauge');
    const riskGaugeLabel = document.getElementById('risk-gauge-label');

    // Actualiza el medidor visual de riesgo (bajo / medio / alto) sin recargar la página
    function updateRiskGauge(level) {
        if (!riskGauge) return;
        const fill = level === 'agresivo' ? 3 : (level === 'balanceado' ? 2 : 1);
        const color = level === 'agresivo' ? 'bg-rose-500' : (level === 'balanceado' ? 'bg-amber-500' : 'bg-emerald-500');
        const word = level === 'agresivo' ? 'Riesgo alto' : (level === 'balanceado' ? 'Riesgo medio' : 'Riesgo bajo');

        riskGauge.querySelectorAll('.risk-seg').forEach((seg, i) => {
            seg.className = 'risk-seg h-1.5 flex-1 rounded-full ' + (i < fill ? color : 'bg-slate-700/70');
        });
        riskGauge.setAttribute('aria-label', `Nivel de riesgo: ${word}`);
        if (riskGaugeLabel) riskGaugeLabel.textContent = word;
    }

    if (riskLevelForm) {
        riskLevelForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            riskLevelBtn.disabled = true;
            riskLevelBtn.classList.add('opacity-50');

            try {
                const formData = new FormData(riskLevelForm);
                const response = await fetch(riskLevelForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': formData.get('_token')
                    },
                    body: formData
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(data.message || 'Error al actualizar el nivel de riesgo.');
                }

                const rLevel = data.risk_level;
                riskLevelBadge.textContent = rLevel.toUpperCase();
                
                let desc = '';
                if (rLevel === 'conservador') {
                    desc = 'Prioriza preservar tu capital. Crecimiento moderado con caídas temporales pequeñas de hasta un 15%-20%.';
                } else if (rLevel === 'balanceado') {
                    desc = 'Equilibrio entre crecimiento y estabilidad. Asume caídas temporales moderadas de hasta un 30%-50%.';
                } else {
                    desc = 'Busca el máximo crecimiento. Debes tolerar caídas temporales pronunciadas de hasta un 50%-90%.';
                }
                riskLevelDesc.textContent = desc;
                updateRiskGauge(rLevel);

                showToast(data.message);
                if (data.current_position) {
                    updateBotPositionUi(data.current_position);
                }
                refreshHistory(true);
                refreshActivities();

            } catch (err) {
                showToast(err.message, true);
            } finally {
                riskLevelBtn.disabled = false;
                riskLevelBtn.classList.remove('opacity-50');
            }
        });
    }

    rangeButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            currentRange = btn.dataset.range;
            highlightRange();
            refreshHistory();
        });
    });

    async function refreshActivities() {
        const feedList = document.getElementById('activity-feed-list');
        const riskAlertsContainer = document.getElementById('activity-risk-alerts');
        if (!feedList) return;

        try {
            const response = await fetch("{{ route('dashboard.activities') }}", {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) {
                throw new Error('No se pudo cargar la actividad.');
            }

            const data = await response.json();
            const activities = data.activities || [];

            // 1. Renderizar Risk Alerts
            if (riskAlertsContainer) {
                const alerts = activities.filter(act => act.risk_alert);
                if (alerts.length > 0) {
                    riskAlertsContainer.innerHTML = alerts.map(alert => `
                        <div class="p-5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-200 shadow-lg relative overflow-hidden animate-fade-in">
                            <div class="absolute -right-10 -top-10 w-24 h-24 bg-rose-500/10 rounded-full blur-xl pointer-events-none"></div>
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl bg-rose-500/20 flex items-center justify-center text-rose-400 shrink-0 border border-rose-500/30">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-xs font-bold text-rose-400 uppercase tracking-wider block">ALERTA DE PROTECCIÓN DE RIESGO</span>
                                    <p class="text-sm font-semibold text-white mt-1">${alert.human_description}</p>
                                    <span class="text-[10px] text-slate-400 block mt-2">${alert.created_at_human} (${alert.created_at_formatted})</span>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    riskAlertsContainer.innerHTML = '';
                }
            }

            // 2. Renderizar feed principal
            if (activities.length > 0) {
                feedList.innerHTML = activities.map(activity => {
                    let iconHtml = '';
                    if (activity.type === 'long' || activity.type === 'buy') {
                        iconHtml = `
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20 shrink-0" title="Inversión al Alza (LONG)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8L6 21" />
                                </svg>
                            </div>
                        `;
                    } else if (activity.type === 'short') {
                        iconHtml = `
                            <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center border border-rose-500/20 shrink-0" title="Inversión a la Baja (SHORT)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8L6 3" />
                                </svg>
                            </div>
                        `;
                    } else if (activity.type === 'close' || activity.type === 'sell') {
                        iconHtml = `
                            <div class="w-10 h-10 rounded-xl bg-slate-500/10 text-slate-400 flex items-center justify-center border border-slate-500/20 shrink-0" title="Posición Cerrada (CLOSE)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        `;
                    } else if (activity.type === 'risk_protection') {
                        iconHtml = `
                            <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center border border-rose-500/20 shrink-0" title="Protección de Riesgo">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                        `;
                    } else {
                        iconHtml = `
                            <div class="w-10 h-10 rounded-xl bg-slate-500/10 text-slate-400 flex items-center justify-center border border-slate-500/20 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        `;
                    }

                    let profitHtml = '';
                    if (activity.profit_percentage !== null) {
                        const profitVal = parseFloat(activity.profit_percentage);
                        const sign = profitVal >= 0 ? '+' : '';
                        const formattedVal = new Intl.NumberFormat('es-ES', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(profitVal);
                        const badgeClass = profitVal >= 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                        profitHtml = `
                            <div class="text-right shrink-0">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold ${badgeClass}">
                                    ${sign}${formattedVal}%
                                </span>
                            </div>
                        `;
                    }

                    return `
                        <div class="py-4 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-4">
                                ${iconHtml}
                                <div>
                                    <p class="text-sm font-medium text-slate-200">${activity.human_description}</p>
                                    <span class="text-[10px] text-slate-500 block mt-1">${activity.created_at_human} (${activity.created_at_formatted})</span>
                                </div>
                            </div>
                            ${profitHtml}
                        </div>
                    `;
                }).join('');
            } else {
                feedList.innerHTML = `
                    <div class="py-12 text-center text-slate-500 text-sm">
                        <p>No se han registrado acciones del bot recientemente en esta cuenta.</p>
                        <p class="text-xs text-slate-600 mt-1">Usa la herramienta del simulador en la pestaña "Panel de Control" para generar actividad de prueba.</p>
                    </div>
                `;
            }
        } catch (err) {
            console.error('Error refreshing activities:', err);
        }
    }

@if($user->isBrokerLinked())
    // Sondeo del patrimonio neto en vivo: la cabecera se mueve con el P/L de la
    // posición abierta sin esperar al snapshot programado (cada 15 min). No toca
    // el histórico del gráfico; solo refresca el número grande.
    async function pollLiveBalance() {
        // No malgastar peticiones si la pestaña está oculta.
        if (document.hidden) return;

        try {
            const response = await fetch(`{{ route('dashboard.balance.live') }}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data.live || data.balance === null || data.balance === undefined) return;

            const changed = liveBalance !== null && data.balance !== liveBalance;
            liveBalance = data.balance;
            // Que un "mouse leave" del gráfico restaure el último valor en vivo,
            // no el snapshot antiguo.
            originalBalance = data.balance;

            if (!chartHovering) {
                renderBalance(data.balance);
                if (changed) pulseBalance();
            }

            updateChartWithLive(data.balance);

            if (data.current_position) {
                updateBotPositionUi(data.current_position);
            }
        } catch (err) {
            // Silencioso: la cabecera conserva el último valor mostrado.
        }
    }

    setInterval(pollLiveBalance, 5000);
    pollLiveBalance();
@endif

    // Actualización reactiva vía WebSockets (Escenario 3)
    if (window.Echo) {
        window.Echo.private('App.Models.User.{{ $user->id }}')
            .listen('.balance.updated', (event) => {
                renderBalance(event.balance);
                pulseBalance();
                
                liveBalance = event.balance;
                originalBalance = event.balance;
                
                refreshHistory();
                if (event.current_position) {
                    updateBotPositionUi(event.current_position);
                }
                refreshActivities();
            })
            .listen('.bot.status.updated', (event) => {
                updateBotUi(event.bot_active);
                if (event.current_position) {
                    updateBotPositionUi(event.current_position);
                }
                refreshActivities();
            });
    }

    // Gestión de pestañas (US05)
    const tabBtnPanel = document.getElementById('tab-btn-panel');
    const tabBtnActivity = document.getElementById('tab-btn-activity');
    const tabBtnInfo = document.getElementById('tab-btn-info');
    const tabBtnHelp = document.getElementById('tab-btn-help');
    const tabContentPanel = document.getElementById('tab-content-panel');
    const tabContentActivity = document.getElementById('tab-content-activity');
    const tabContentInfo = document.getElementById('tab-content-info');
    const tabContentHelp = document.getElementById('tab-content-help');

    function switchTab(target) {
        // Remover clases activas de todos los botones
        [tabBtnPanel, tabBtnActivity, tabBtnInfo, tabBtnHelp].forEach(btn => {
            if (btn) {
                btn.classList.remove('border-violet-500', 'text-white');
                btn.classList.add('border-transparent', 'text-slate-400');
                btn.classList.remove('font-semibold');
                btn.classList.add('font-medium');
            }
        });
        
        // Ocultar todos los contenidos
        [tabContentPanel, tabContentActivity, tabContentInfo, tabContentHelp].forEach(content => {
            if (content) content.classList.add('hidden');
        });

        // Activar el objetivo
        if (target === 'activity' && tabBtnActivity && tabContentActivity) {
            tabBtnActivity.classList.remove('border-transparent', 'text-slate-400', 'font-medium');
            tabBtnActivity.classList.add('border-violet-500', 'text-white', 'font-semibold');
            tabContentActivity.classList.remove('hidden');
            refreshActivities();
        } else if (target === 'info' && tabBtnInfo && tabContentInfo) {
            tabBtnInfo.classList.remove('border-transparent', 'text-slate-400', 'font-medium');
            tabBtnInfo.classList.add('border-violet-500', 'text-white', 'font-semibold');
            tabContentInfo.classList.remove('hidden');
        } else if (target === 'help' && tabBtnHelp && tabContentHelp) {
            tabBtnHelp.classList.remove('border-transparent', 'text-slate-400', 'font-medium');
            tabBtnHelp.classList.add('border-violet-500', 'text-white', 'font-semibold');
            tabContentHelp.classList.remove('hidden');
        } else if (tabBtnPanel && tabContentPanel) {
            tabBtnPanel.classList.remove('border-transparent', 'text-slate-400', 'font-medium');
            tabBtnPanel.classList.add('border-violet-500', 'text-white', 'font-semibold');
            tabContentPanel.classList.remove('hidden');
        }
    }

    if (tabBtnPanel) tabBtnPanel.addEventListener('click', () => switchTab('panel'));
    if (tabBtnActivity) tabBtnActivity.addEventListener('click', () => switchTab('activity'));
    if (tabBtnInfo) tabBtnInfo.addEventListener('click', () => switchTab('info'));
    if (tabBtnHelp) tabBtnHelp.addEventListener('click', () => switchTab('help'));

    // Configuración de videos explicativos para cada paso (vacio por defecto)
    const HELP_VIDEOS = {
        1: "", // url del video para paso 1, ej: "/videos/paso-1.mp4"
        2: "",
        3: "",
        4: "",
        5: "",
        6: "",
        7: ""
    };

    // Gestión de Pasos en la pestaña de Ayuda
    const stepNavButtons = document.querySelectorAll('.step-nav-btn');
    const stepContents = document.querySelectorAll('.help-step-content');
    const stepsList = document.getElementById('help-steps-list');
    const stepDetails = document.getElementById('help-step-details');
    const backToStepsBtn = document.getElementById('back-to-steps-btn');

    stepNavButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const selectedStep = parseInt(btn.dataset.step);

            // Actualizar botones de navegación lateral
            stepNavButtons.forEach(b => {
                const stepNum = b.querySelector('.step-num');
                if (parseInt(b.dataset.step) === selectedStep) {
                    b.classList.remove('border-transparent', 'text-slate-400', 'hover:text-slate-200', 'font-medium');
                    b.classList.add('bg-violet-600/10', 'border-violet-500/30', 'text-white', 'font-semibold');
                    if (stepNum) {
                        stepNum.classList.remove('bg-slate-800', 'text-slate-400');
                        stepNum.classList.add('bg-violet-600', 'text-white');
                    }
                } else {
                    b.classList.remove('bg-violet-600/10', 'border-violet-500/30', 'text-white', 'font-semibold');
                    b.classList.add('border-transparent', 'text-slate-400', 'hover:text-slate-200', 'font-medium');
                    if (stepNum) {
                        stepNum.classList.remove('bg-violet-600', 'text-white');
                        stepNum.classList.add('bg-slate-800', 'text-slate-400');
                    }
                }
            });

            // Mostrar el contenido correspondiente y configurar el video
            stepContents.forEach(content => {
                const contentId = `help-step-content-${selectedStep}`;
                if (content.id === contentId) {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            });

            // Cargar y mostrar video si existe
            const videoEl = document.getElementById(`help-video-${selectedStep}`);
            if (videoEl) {
                const videoContainer = videoEl.parentElement;
                const placeholderEl = videoContainer ? videoContainer.querySelector('div') : null;
                const videoUrl = HELP_VIDEOS[selectedStep];

                if (videoUrl) {
                    videoEl.src = videoUrl;
                    videoEl.classList.remove('hidden');
                    if (placeholderEl) {
                        placeholderEl.classList.add('hidden', 'opacity-0');
                    }
                } else {
                    videoEl.src = "";
                    videoEl.classList.add('hidden');
                    if (placeholderEl) {
                        placeholderEl.classList.remove('hidden', 'opacity-0');
                    }
                }
            }

            // Control de vistas en móvil (Ayuda Responsiva)
            if (window.innerWidth < 1024) {
                if (stepsList && stepDetails) {
                    stepsList.classList.add('hidden');
                    stepDetails.classList.remove('hidden');
                }
            }

            // Dirigir el scroll arriba del todo en la página
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // Botón Volver en móvil para la pestaña de Ayuda
    if (backToStepsBtn) {
        backToStepsBtn.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                if (stepsList && stepDetails) {
                    stepsList.classList.remove('hidden');
                    stepDetails.classList.add('hidden');
                }
            }
        });
    }

    // Función de copia al portapapeles global
    window.copyIpToClipboard = function() {
        const ip = "82.223.44.83";
        navigator.clipboard.writeText(ip).then(() => {
            showToast("IP copiada al portapapeles: " + ip);
        }).catch(err => {
            console.error("No se pudo copiar la IP: ", err);
        });
    };

    // Función global para seleccionar un paso de ayuda desde enlaces internos
    window.selectHelpStep = function(step) {
        const btn = document.querySelector(`.step-nav-btn[data-step="${step}"]`);
        if (btn) {
            btn.click();
        }
    };

    @if(session('active_tab') === 'activity')
        switchTab('activity');
    @elseif(session('active_tab') === 'help')
        switchTab('help');
    @endif

    @if(session('success'))
        showToast("{{ session('success') }}");
    @endif
    @if(session('error'))
        showToast("{{ session('error') }}", true);
    @endif

    // Controladores para el Modal de Eliminación de Cuenta
    window.confirmAccountDeletion = function() {
        const modal = document.getElementById('delete-account-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    };

    window.closeDeleteAccountModal = function() {
        const modal = document.getElementById('delete-account-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    // Primera carga del gráfico con el filtro por defecto (Mes)
    refreshHistory();
})();
</script>
@endsection
