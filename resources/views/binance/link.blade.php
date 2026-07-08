@extends('layouts.app')

@section('content')
<div class="space-y-8 animate-fade-in">
    
    <!-- Encabezado de la página -->
    <div class="flex items-center gap-3">
        <a href="{{ route('dashboard') }}" class="text-slate-400 hover:text-white transition duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-white">Vincular cuenta de Binance</h1>
            <p class="text-sm text-slate-400">Paso obligatorio de seguridad para permitir al bot operar con tu capital.</p>
        </div>
    </div>

    <!-- CUADRO DE ALERTA DE PERMISOS DE RETIRO ACTIVO (Escenario 1) -->
    @if(session('withdrawal_error'))
        <div class="p-6 rounded-2xl bg-rose-500/10 border-2 border-rose-500/30 text-rose-200 shadow-xl space-y-4 relative overflow-hidden animate-fade-in">
            <!-- Brillo de fondo para la alerta -->
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-rose-500/20 rounded-full blur-2xl pointer-events-none"></div>
            
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-rose-500/20 flex items-center justify-center text-rose-400 shrink-0 border border-rose-500/30">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-white uppercase tracking-wide">Vinculación Rechazada: Permisos de Retiro Activos</h3>
                    <p class="text-sm text-slate-300">
                        {{ session('withdrawal_error') }}
                    </p>
                </div>
            </div>

            <!-- Guía técnica rápida para desactivar retiros -->
            <div class="bg-[hsl(223,47%,10%)] p-5 rounded-xl border border-rose-500/20 space-y-3">
                <span class="font-bold text-white text-xs uppercase tracking-wider block text-rose-300">Guía rápida de desactivación:</span>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-300">
                    <div class="p-3 bg-[hsl(223,47%,14%)] rounded-lg border border-slate-800">
                        <span class="font-bold text-white block mb-1">1. Editar en Binance</span>
                        Ve a "Gestión de API" en Binance, busca tu clave y pulsa en "Editar restricciones".
                    </div>
                    <div class="p-3 bg-[hsl(223,47%,14%)] rounded-lg border border-slate-800">
                        <span class="font-bold text-white block mb-1">2. Desmarcar Retiros</span>
                        Asegúrate de que la opción <strong class="text-rose-400">"Habilitar retiros" (Enable Withdrawals)</strong> esté desmarcada.
                    </div>
                    <div class="p-3 bg-[hsl(223,47%,14%)] rounded-lg border border-slate-800">
                        <span class="font-bold text-white block mb-1">3. Guardar & Reintentar</span>
                        Guarda los cambios, introduce tu código de verificación (2FA) y vuelve a ingresar la clave abajo.
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Formulario de Vinculación (5 Columnas) -->
        <div class="lg:col-span-5 bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-xl flex flex-col justify-between">
            <div>
                <h2 class="text-xl font-bold text-white mb-6">Ingresar Credenciales</h2>
                
                <form action="{{ route('binance.store') }}" method="POST" class="space-y-5">
                    @csrf
                    
                    <div>
                        <label for="binance_api_key" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Binance API Key</label>
                        <input type="text" name="binance_api_key" id="binance_api_key" required
                               class="w-full bg-[hsl(223,47%,10%)] border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-violet-500 transition duration-200"
                               placeholder="Introduce tu API Key" 
                               value="{{ old('binance_api_key', request('api_key')) }}">
                        @error('binance_api_key')
                            <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="binance_secret_key" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Binance Secret Key</label>
                        <input type="password" name="binance_secret_key" id="binance_secret_key" required
                               class="w-full bg-[hsl(223,47%,10%)] border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-violet-500 transition duration-200"
                               placeholder="Introduce tu Secret Key" 
                               value="{{ old('binance_secret_key', request('api_key') === 'withdrawals_enabled_key' ? 'withdrawals_enabled_secret' : (request('api_key') === 'invalid_key' ? 'invalid_secret' : 'my_secure_secret_key')) }}">
                        @error('binance_secret_key')
                            <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="p-3 bg-[hsl(223,47%,10%)] rounded-xl border border-slate-800 text-[11px] text-slate-400 flex items-start gap-2">
                        <svg class="w-4 h-4 text-violet-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>
                            Tus claves se cifran localmente mediante AES-256-GCM y permanecen aisladas en nuestro servidor. <strong>Nunca</strong> son enviadas a proveedores de señales externos.
                        </span>
                    </div>

                    <button type="submit" 
                            class="w-full bg-violet-600 hover:bg-violet-500 text-white font-medium py-3 rounded-xl transition duration-200 text-sm hover-lift mt-2">
                        Verificar y Vincular Cuenta
                    </button>
                </form>
            </div>
            
            <div class="mt-6 pt-6 border-t border-slate-800 text-center">
                <a href="{{ route('dashboard') }}" class="text-xs text-slate-400 hover:text-white transition duration-200">
                    Cancelar y volver
                </a>
            </div>
        </div>

        <!-- Tutorial Paso a Paso (7 Columnas) -->
        <div class="lg:col-span-7 bg-[hsl(223,47%,14%)] border border-[rgba(255,255,255,0.06)] rounded-2xl p-6 shadow-xl space-y-6">
            <div>
                <h2 class="text-xl font-bold text-white mb-2">¿Cómo crear tu clave API en Binance?</h2>
                <p class="text-sm text-slate-400">Sigue este sencillo tutorial visual para garantizar una conexión 100% segura sin permisos de retiro.</p>
            </div>

            <!-- Pasos Interactivos -->
            <div class="space-y-4">
                
                <!-- Paso 1 -->
                <div class="flex gap-4 p-3 rounded-xl hover:bg-[hsl(223,47%,18%)] transition duration-200">
                    <div class="w-8 h-8 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center font-bold text-violet-400 text-sm shrink-0">
                        1
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Ve a Gestión de API</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Inicia sesión en Binance, ve a tu Perfil y selecciona "Gestión de API". Haz clic en "Crear API".</p>
                    </div>
                </div>

                <!-- Paso 2 -->
                <div class="flex gap-4 p-3 rounded-xl hover:bg-[hsl(223,47%,18%)] transition duration-200">
                    <div class="w-8 h-8 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center font-bold text-violet-400 text-sm shrink-0">
                        2
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Elige Clave Generada por el Sistema</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Selecciona el tipo de clave por defecto. Asígnale un nombre descriptivo (ej. "ViBo Invest Bot") para identificarla fácilmente.</p>
                    </div>
                </div>

                <!-- Paso 3 -->
                <div class="flex gap-4 p-3 rounded-xl bg-violet-500/5 border border-violet-500/10 rounded-xl">
                    <div class="w-8 h-8 rounded-full bg-violet-600/40 border border-violet-500/50 flex items-center justify-center font-bold text-violet-300 text-sm shrink-0">
                        3
                    </div>
                    <div class="space-y-2">
                        <h4 class="text-sm font-semibold text-white flex items-center gap-1.5">
                            Configura los Permisos de Seguridad
                            <span class="text-[9px] font-bold bg-emerald-500/10 text-emerald-400 px-1.5 py-0.5 rounded border border-emerald-500/20 uppercase">Muy Importante</span>
                        </h4>
                        <p class="text-xs text-slate-300">
                            En las restricciones de la API, asegúrate de activar solo **"Habilitar lectura"** y **"Habilitar trading de Spot y Margen"** si es necesario.
                        </p>
                        
                        <!-- Ilustración visual interactiva simulando la casilla de Binance -->
                        <div class="p-3 bg-[hsl(223,47%,10%)] rounded-lg border border-slate-800 space-y-2 mt-1">
                            <span class="text-[10px] text-slate-500 font-bold uppercase block tracking-wider">Simulación de Panel de Binance:</span>
                            <div class="flex flex-wrap gap-4 text-[11px]">
                                <div class="flex items-center gap-1.5 text-slate-400">
                                    <div class="w-3.5 h-3.5 rounded border border-violet-500 bg-violet-500 flex items-center justify-center text-white text-[9px] font-bold">✓</div>
                                    Habilitar Lectura
                                </div>
                                <div class="flex items-center gap-1.5 text-slate-400">
                                    <div class="w-3.5 h-3.5 rounded border border-violet-500 bg-violet-500 flex items-center justify-center text-white text-[9px] font-bold">✓</div>
                                    Habilitar Spot/Margen
                                </div>
                                <div class="flex items-center gap-1.5 text-rose-400 font-semibold">
                                    <div class="w-3.5 h-3.5 rounded border border-rose-500/50 bg-rose-500/10 flex items-center justify-center"></div>
                                    Habilitar Retiros (Desactivado)
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 4 -->
                <div class="flex gap-4 p-3 rounded-xl hover:bg-[hsl(223,47%,18%)] transition duration-200">
                    <div class="w-8 h-8 rounded-full bg-violet-600/20 border border-violet-500/30 flex items-center justify-center font-bold text-violet-400 text-sm shrink-0">
                        4
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Copia y pega las llaves</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Copia tu API Key y Secret Key y pégalas en el formulario de la izquierda. Una vez completado, pulsa en Verificar.</p>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>
@endsection
