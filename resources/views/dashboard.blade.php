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
            @if($user->isBinanceLinked())
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Binance Conectado
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-500/10 text-slate-400 border border-slate-500/20">
                    <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                    Sin Conexión API
                </span>
            @endif
        </div>
    </div>

    <!-- Pestañas de Navegación (US05) -->
    <div class="border-b border-slate-800/60 flex space-x-6">
        <button type="button" id="tab-btn-panel" class="tab-btn border-b-2 border-violet-500 pb-3 px-1 text-sm font-semibold text-white focus:outline-none transition duration-200">
            Panel de Control
        </button>
        <button type="button" id="tab-btn-activity" class="tab-btn border-b-2 border-transparent pb-3 px-1 text-sm font-medium text-slate-400 hover:text-white focus:outline-none transition duration-200">
            Actividad
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

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-violet-500/10 border border-violet-500/20 text-violet-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
            {{ session('error') }}
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
                            Comprado (LONG)
                        @elseif($user->current_position === 'SHORT')
                            Vendido (SHORT)
                        @else
                            Fuera de mercado (CLOSE)
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
                            Operando directamente en tu cartera de Binance.
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

        <!-- Tarjeta 4: Configuración de Binance -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[170px]">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-lg bg-violet-500/10 text-violet-300 border border-violet-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Conexión Binance</span>
                </div>
                <div class="my-4">
                    @if($user->isBinanceLinked())
                        <span class="text-2xl font-extrabold block text-emerald-400 uppercase tracking-tight">Conectado</span>
                        <code class="text-[11px] text-slate-400 bg-slate-900 px-2 py-1 rounded block truncate mt-2">
                            API: {{ substr($user->binance_api_key, 0, 8) }}...{{ substr($user->binance_api_key, -8) }}
                        </code>
                    @else
                        <span class="text-2xl font-extrabold block text-rose-400 uppercase tracking-tight">Sin conexión</span>
                        <span class="text-xs text-slate-400 block mt-1">Conecta tu API para operar en real de forma segura.</span>
                    @endif
                </div>
            </div>

            @if($user->isBinanceLinked())
                <form action="{{ route('binance.disconnect') }}" method="POST" class="m-0 p-0">
                    @csrf
                    <button type="submit"
                            class="w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30">
                        Desvincular Cuenta
                    </button>
                </form>
            @else
                <a href="{{ route('binance.link') }}"
                   class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-violet-600 hover:bg-violet-500 text-white inline-block">
                    Conectar Binance
                </a>
            @endif
        </div>

    </div>

    @if(request()->has('simulador'))
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
        const badge = document.getElementById('bot-position-badge');
        if (!badge) return;

        badge.className = 'text-[11px] font-bold px-2 py-0.5 rounded-lg';
        if (position === 'LONG') {
            badge.classList.add('bg-emerald-500/10', 'text-emerald-400', 'border', 'border-emerald-500/20');
            badge.textContent = 'Comprado (LONG)';
        } else if (position === 'SHORT') {
            badge.classList.add('bg-rose-500/10', 'text-rose-400', 'border', 'border-rose-500/20');
            badge.textContent = 'Vendido (SHORT)';
        } else {
            badge.classList.add('bg-slate-500/10', 'text-slate-400', 'border', 'border-slate-500/20');
            badge.textContent = 'Fuera de mercado (CLOSE)';
        }
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

    if (botToggleForm) {
        botToggleForm.addEventListener('submit', async (e) => {
            e.preventDefault();

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

                showToast(data.message);
                refreshHistory(true);
                refreshActivities();

            } catch (err) {
                showToast(err.message, true);
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

@if($user->isBinanceLinked())
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
    const tabContentPanel = document.getElementById('tab-content-panel');
    const tabContentActivity = document.getElementById('tab-content-activity');

    function switchTab(target) {
        if (target === 'activity') {
            tabBtnPanel.classList.remove('border-violet-500', 'text-white');
            tabBtnPanel.classList.add('border-transparent', 'text-slate-400');
            tabBtnActivity.classList.remove('border-transparent', 'text-slate-400');
            tabBtnActivity.classList.add('border-violet-500', 'text-white');

            tabContentPanel.classList.add('hidden');
            tabContentActivity.classList.remove('hidden');

            refreshActivities();
        } else {
            tabBtnActivity.classList.remove('border-violet-500', 'text-white');
            tabBtnActivity.classList.add('border-transparent', 'text-slate-400');
            tabBtnPanel.classList.remove('border-transparent', 'text-slate-400');
            tabBtnPanel.classList.add('border-violet-500', 'text-white');

            tabContentActivity.classList.add('hidden');
            tabContentPanel.classList.remove('hidden');
        }
    }

    if (tabBtnPanel && tabBtnActivity) {
        tabBtnPanel.addEventListener('click', () => switchTab('panel'));
        tabBtnActivity.addEventListener('click', () => switchTab('activity'));
    }

    @if(session('active_tab') === 'activity')
        switchTab('activity');
    @endif

    // Primera carga del gráfico con el filtro por defecto (Mes)
    refreshHistory();
})();
</script>
@endsection
