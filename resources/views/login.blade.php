@extends('layouts.app')

@push('styles')
    <style>
        .login-card {
            background-color: var(--bg-secondary);
            border: var(--border-glow);
            box-shadow: var(--shadow-premium);
        }
    </style>
@endpush

@section('content')
<div class="flex items-center justify-center min-height-[60vh] py-12">
    <div class="w-full max-w-md login-card rounded-2xl p-8 animate-fade-in">
        
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

    </div>
</div>
@endsection
