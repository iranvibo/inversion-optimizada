@extends('layouts.app')

@head
    <style>
        .login-card {
            background-color: var(--bg-secondary);
            border: var(--border-glow);
            box-shadow: var(--shadow-premium);
        }
    </style>
@endhead

@section('content')
<div class="flex items-center justify-center min-height-[60vh] py-12">
    <div class="w-full max-w-md bg-[hsl(223,47%,14%)] rounded-2xl p-8 border border-[rgba(255,255,255,0.06)] shadow-2xl animate-fade-in">
        
        <!-- Encabezado -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Bienvenido a ViBo Invest</h1>
            <p class="text-sm text-slate-400">La forma más sencilla y segura de automatizar tus ahorros en criptomonedas.</p>
        </div>

        @if(session('error'))
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <!-- Formulario Tradicional (Opcional) -->
        <form action="{{ route('login') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Correo Electrónico</label>
                <input type="email" name="email" id="email" required
                       class="w-full bg-[hsl(223,47%,10%)] border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-violet-500 transition duration-200"
                       placeholder="tu@correo.com" value="{{ old('email') }}">
                @error('email')
                    <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Contraseña</label>
                <input type="password" name="password" id="password" required
                       class="w-full bg-[hsl(223,47%,10%)] border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-violet-500 transition duration-200"
                       placeholder="••••••••">
                @error('password')
                    <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" 
                    class="w-full bg-violet-600 hover:bg-violet-500 text-white font-medium py-3 rounded-xl transition duration-200 text-sm focus:outline-none hover-lift">
                Iniciar Sesión
            </button>
        </form>

        <!-- Divisor -->
        <div class="relative my-8">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-slate-800"></div>
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-[hsl(223,47%,14%)] px-3 text-slate-500 font-semibold tracking-wider">o prueba el MVP al instante</span>
            </div>
        </div>

        <!-- Botón Auto Login Rápido -->
        <div class="text-center">
            <a href="{{ route('login.auto') }}" 
               class="w-full inline-flex items-center justify-center bg-gradient-to-r from-violet-500/10 to-indigo-500/10 hover:from-violet-500/20 hover:to-indigo-500/20 text-violet-300 border border-violet-500/30 font-medium py-3.5 px-4 rounded-xl transition duration-200 text-sm hover-lift">
                <svg class="w-4 h-4 mr-2 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                Iniciar Sesión de Prueba (1-Clic)
            </a>
            <p class="text-[11px] text-slate-500 mt-3">Inicia sesión inmediatamente con un usuario demo para testear la plataforma.</p>
        </div>

    </div>
</div>
@endsection
