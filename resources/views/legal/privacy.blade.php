@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-12 animate-fade-in">
    <div class="mb-10">
        <a href="{{ url()->previous() }}" class="text-sm text-violet-400 hover:text-violet-300">&larr; Volver</a>
        <h1 class="text-3xl font-extrabold tracking-tight text-white mt-4 mb-2">Política de Privacidad</h1>
        <p class="text-sm text-slate-400">Última actualización: {{ date('d/m/Y') }}</p>
    </div>

    <div class="space-y-8 text-sm leading-relaxed text-slate-300">
        <section>
            <h2 class="text-lg font-semibold text-white mb-2">1. Quiénes somos</h2>
            <p>ViBo Invest es una plataforma de automatización de trading orientada a usuarios particulares. Tratamos tus datos con el único fin de prestarte el servicio de forma segura y transparente.</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">2. Qué datos recogemos</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li><span class="text-white">Datos de cuenta:</span> nombre y correo electrónico que indicas al registrarte o que nos facilita Google al iniciar sesión con tu cuenta.</li>
                <li><span class="text-white">Credenciales de Binance:</span> tus claves de API se almacenan cifradas y se utilizan exclusivamente para operar en modo de solo lectura y trading, nunca para retiradas de fondos.</li>
                <li><span class="text-white">Datos de uso:</span> nivel de riesgo, estado del bot e histórico de operaciones simuladas o reales para mostrarte la evolución de tu capital.</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">3. Inicio de sesión con Google</h2>
            <p>Si accedes con Google, utilizamos Firebase Authentication para verificar tu identidad. Solo recibimos tu nombre, correo electrónico y foto de perfil públicos. En ningún momento accedemos a tu contraseña de Google.</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">4. Para qué usamos tus datos</h2>
            <p>Usamos tus datos para autenticarte, ejecutar las funciones de la plataforma, proteger tu capital mediante nuestros límites de seguridad (stop loss diario y capital protegido) y mejorar el servicio. No vendemos ni cedemos tus datos a terceros con fines comerciales.</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">5. Seguridad</h2>
            <p>Tus credenciales sensibles se cifran en reposo y se mantienen bajo custodia local aislada. El sistema bloquea por diseño cualquier permiso de retirada de fondos sobre tu cuenta de Binance.</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">6. Tus derechos</h2>
            <p>Puedes acceder, rectificar o eliminar tus datos en cualquier momento, así como desvincular tu cuenta de Binance o cerrar tu cuenta. Para ejercer estos derechos, contacta con nuestro equipo de soporte.</p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">7. Contacto</h2>
            <p>Si tienes cualquier duda sobre esta política, escríbenos a <a href="mailto:soporte@viboinvest.test" class="text-violet-400 hover:text-violet-300 underline">soporte@viboinvest.test</a>.</p>
        </section>
    </div>

    <div class="mt-12 pt-6 border-t border-[rgba(255,255,255,0.06)] text-center">
        <a href="{{ route('register') }}" class="inline-block bg-violet-600 hover:bg-violet-500 text-white font-medium px-6 py-3 rounded-xl transition duration-200 text-sm hover-lift">
            Entendido, crear mi cuenta
        </a>
    </div>
</div>
@endsection
