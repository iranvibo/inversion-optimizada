{{-- Acceso con Google (Firebase) + separador. Reutilizado en login y registro. --}}

<!-- Mensaje de error del flujo de Google -->
<div data-google-error class="hidden mb-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm text-center"></div>

<button type="button" data-google-signin
        class="w-full flex items-center justify-center gap-3 bg-white hover:bg-slate-100 text-slate-800 font-medium py-3 rounded-xl transition duration-200 text-sm focus:outline-none hover-lift">
    <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.76h3.56c2.08-1.92 3.28-4.74 3.28-8.09Z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.56-2.76c-.98.66-2.23 1.06-3.72 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"/>
        <path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84Z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38Z"/>
    </svg>
    Continuar con Google
</button>

<!-- Separador -->
<div class="flex items-center gap-4 my-6">
    <div class="h-px flex-1 bg-slate-800"></div>
    <span class="text-xs text-slate-500 uppercase tracking-wider">o</span>
    <div class="h-px flex-1 bg-slate-800"></div>
</div>

@push('scripts')
    @vite('resources/js/firebase-auth.js')
@endpush
