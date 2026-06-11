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

    <!-- MENSAJES DE ALERTA Y CONFIRMACIÓN -->

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

    <!-- SECCIÓN DE TARJETAS PRINCIPALES -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Tarjeta 1: Estado del Bot -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[160px]">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Estado de Automatización</span>
                <span class="relative flex h-3 w-3">
                    @if($user->bot_active)
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    @else
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    @endif
                </span>
            </div>
            
            <div class="my-4">
                <span class="text-3xl font-extrabold block text-white uppercase tracking-tight">
                    {{ $user->bot_active ? 'Activo' : 'Pausado' }}
                </span>
                <span class="text-xs text-slate-400">
                    {{ $user->bot_active ? 'Ejecutando señales del mercado.' : 'El bot no realizará operaciones.' }}
                </span>
            </div>

            <form action="{{ route('bot.toggle') }}" method="POST" class="m-0 p-0">
                @csrf
                <button type="submit" 
                        class="w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 {{ $user->bot_active ? 'bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/30' : 'bg-emerald-600 hover:bg-emerald-500 text-white' }}">
                    {{ $user->bot_active ? 'Pausar Bot' : 'Activar Bot' }}
                </button>
            </form>
        </div>

        <!-- Tarjeta 2: Modo de Operativa -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[160px]">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Modo de Operación</span>
                <div class="my-4">
                    <span class="text-3xl font-extrabold block text-white tracking-tight">
                        {{ $user->bot_mode === 'real' ? 'Dinero Real' : 'Simulación' }}
                    </span>
                    <span class="text-xs text-slate-400 block mt-1">
                        @if($user->bot_mode === 'real')
                            Operando directamente en tu cartera de Binance.
                        @else
                            Operaciones simuladas con capital de prueba.
                        @endif
                    </span>
                </div>
            </div>

            <form action="{{ route('bot.toggle-mode') }}" method="POST" class="m-0 p-0">
                @csrf
                <button type="submit" 
                        class="w-full text-xs font-bold py-2.5 px-4 rounded-xl transition duration-200 bg-violet-600/20 hover:bg-violet-600/30 text-violet-300 border border-violet-500/30">
                    Cambiar a Modo {{ $user->bot_mode === 'real' ? 'Simulación' : 'Real' }}
                </button>
            </form>
        </div>

        <!-- Tarjeta 3: Configuración de Binance -->
        <div class="bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-md hover-lift flex flex-col justify-between min-h-[160px]">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Conexión de Binance</span>
                <div class="my-4">
                    @if($user->isBinanceLinked())
                        <span class="text-sm font-bold text-white block">Credenciales Vinculadas</span>
                        <code class="text-[11px] text-slate-400 bg-slate-900 px-2 py-1 rounded block truncate mt-2">
                            API: {{ substr($user->binance_api_key, 0, 8) }}...{{ substr($user->binance_api_key, -8) }}
                        </code>
                    @else
                        <span class="text-xl font-bold text-rose-400 block mt-2">No Conectado</span>
                        <span class="text-xs text-slate-400 block">Conecta tu API para operar en real de forma segura.</span>
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

        </div>
    </div>

</div>
@endsection
