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
<div class="flex items-center justify-center min-h-[60vh] py-12">
    <div class="w-full max-w-md login-card rounded-2xl p-8 animate-fade-in">

        <!-- Encabezado -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Crea tu cuenta</h1>
            <p class="text-sm text-slate-400">Empieza a automatizar tus ahorros en minutos, con tu capital siempre protegido.</p>
        </div>

        <!-- Código de invitación: obligatorio tanto para el registro con correo
             como para el registro con Google (se envía junto al token). -->
        <div class="mb-6">
            <label for="invitation_code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Código de Invitación</label>
            <input type="text" name="invitation_code" id="invitation_code" form="register-form" required data-invitation-code
                   autocomplete="off" autocapitalize="characters" spellcheck="false"
                   class="w-full bg-[hsl(223,47%,10%)] border border-slate-800 rounded-xl px-4 py-3 text-sm text-white tracking-widest focus:outline-none focus:border-violet-500 transition duration-200"
                   placeholder="XXXX-XXXX-XXXX-XXXX" value="{{ old('invitation_code', request()->query('invitation')) }}">
            <p class="text-[11px] text-slate-500 mt-1">El registro es solo con invitación. Introduce el código que te hemos enviado.</p>
            @error('invitation_code')
                <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span>
            @enderror
        </div>

        <!-- Registro con Google -->
        @include('auth.partials.social')
        <p class="text-center text-[11px] text-slate-500 -mt-4 mb-6">
            Al registrarte o iniciar sesión con Google, confirmas que has leído y aceptas la
            <a href="{{ route('privacy') }}" target="_blank" class="underline hover:text-slate-300">Política de Privacidad</a> y los
            <a href="{{ route('terms') }}" target="_blank" class="underline hover:text-slate-300">Términos de Servicio y Aviso de Riesgo</a>.
        </p>

        <!-- Formulario tradicional -->
        <form id="register-form" action="{{ route('register') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nombre</label>
                <input type="text" name="name" id="name" required autofocus
                       class="w-full bg-[hsl(223,47%,10%)] border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-violet-500 transition duration-200"
                       placeholder="Tu nombre" value="{{ old('name') }}">
                @error('name')
                    <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

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
                       placeholder="Mínimo 8 caracteres, con letras y números">
                @error('password')
                    <span class="text-rose-400 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Repetir Contraseña</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required
                       class="w-full bg-[hsl(223,47%,10%)] border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-violet-500 transition duration-200"
                       placeholder="••••••••">
            </div>

            <label class="flex items-start gap-2 text-xs text-slate-400 select-none cursor-pointer mb-2">
                <input type="checkbox" name="privacy" value="1" {{ old('privacy') ? 'checked' : '' }}
                       class="mt-0.5 rounded border-slate-700 bg-[hsl(223,47%,10%)] text-violet-600 focus:ring-violet-500">
                <span>
                    He leído y acepto la
                    <a href="{{ route('privacy') }}" target="_blank" class="text-violet-400 hover:text-violet-300 underline">Política de Privacidad</a>.
                </span>
            </label>
            @error('privacy')
                <span class="text-rose-400 text-xs -mt-1 mb-2 block">{{ $message }}</span>
            @enderror

            <label class="flex items-start gap-2 text-xs text-slate-400 select-none cursor-pointer">
                <input type="checkbox" name="terms" value="1" {{ old('terms') ? 'checked' : '' }}
                       class="mt-0.5 rounded border-slate-700 bg-[hsl(223,47%,10%)] text-violet-600 focus:ring-violet-500">
                <span>
                    He leído y acepto los
                    <a href="{{ route('terms') }}" target="_blank" class="text-violet-400 hover:text-violet-300 underline">Términos de Servicio y Aviso de Riesgo</a>.
                </span>
            </label>
            @error('terms')
                <span class="text-rose-400 text-xs -mt-1 block">{{ $message }}</span>
            @enderror

            <button type="submit"
                    class="w-full bg-violet-600 hover:bg-violet-500 text-white font-medium py-3 rounded-xl transition duration-200 text-sm focus:outline-none hover-lift">
                Crear Cuenta
            </button>
        </form>

        <!-- Pie -->
        <p class="text-center text-sm text-slate-400 mt-6">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="text-violet-400 hover:text-violet-300 font-medium">Inicia sesión</a>
        </p>

    </div>
</div>
@endsection
