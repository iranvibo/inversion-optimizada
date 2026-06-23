@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-12 animate-fade-in">
    <div class="mb-10">
        <a href="{{ url()->previous() }}" class="text-sm text-violet-400 hover:text-violet-300">&larr; Volver</a>
        <h1 class="text-3xl font-extrabold tracking-tight text-white mt-4 mb-2">Términos de Servicio y Aviso de Riesgo</h1>
        <p class="text-sm text-slate-400">Última actualización: {{ date('d/m/Y') }}</p>
    </div>

    <div class="space-y-8 text-sm leading-relaxed text-slate-300">
        <section class="p-5 rounded-xl bg-amber-500/10 border border-amber-500/20">
            <h2 class="text-lg font-bold text-amber-300 mb-2 flex items-center gap-2">
                ⚠️ ADVERTENCIA DE RIESGO IMPORTANTE
            </h2>
            <p class="text-amber-200">
                La negociación de criptomonedas y activos digitales conlleva un nivel de riesgo extremadamente alto y puede no ser adecuada para todos los inversores. El apalancamiento y la volatilidad del mercado pueden resultar en pérdidas significativas de capital. Antes de decidir utilizar los servicios de ViBo Invest, debes considerar cuidadosamente tus objetivos de inversión, nivel de experiencia y tolerancia al riesgo.
            </p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">1. Naturaleza del Servicio</h2>
            <p>
                ViBo Invest es una plataforma de software que proporciona una herramienta de automatización de trading. Al conectar tus credenciales de API de Binance, autorizas al software a monitorizar y ejecutar operaciones en tu nombre basadas en las señales recibidas. ViBo Invest no custodia tus fondos ni actúa como entidad financiera, gestora de carteras o asesor de inversiones.
            </p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">2. Exención y Descargo de Responsabilidad Financiera</h2>
            <p class="mb-3">
                Al utilizar ViBo Invest, aceptas y declaras comprender que:
            </p>
            <ul class="list-disc pl-5 space-y-2">
                <li>
                    <span class="text-white font-medium">Responsabilidad del usuario:</span> Eres el único responsable del uso del bot, de la configuración y activación de los perfiles de riesgo (Conservador, Balanceado, Agresivo) y del mantenimiento del saldo suficiente en tu cuenta de Binance.
                </li>
                <li>
                    <span class="text-white font-medium">Exclusión de responsabilidad por pérdidas:</span> ViBo Invest, sus desarrolladores y operadores no serán responsables bajo ninguna circunstancia de pérdidas financieras, daños directos, indirectos, incidentales o consecuentes incurridos en tu cuenta de Binance o en tus simulaciones como resultado del uso del software o la ejecución de señales de trading.
                </li>
                <li>
                    <span class="text-white font-medium">Sin garantías de rendimiento:</span> Los resultados pasados mostrados en simulaciones o históricos son meramente ilustrativos y no constituyen una garantía de rentabilidad futura. El mercado puede comportarse de manera imprevista anulando las estrategias de trading del sistema.
                </li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">3. Seguridad y Permisos de API</h2>
            <p>
                Para garantizar la seguridad de tus fondos, es una condición obligatoria e indispensable que configures tus claves de API de Binance con permisos estrictos de <span class="text-white font-medium">solo lectura y trading habilitado</span>, bloqueando explícitamente cualquier permiso de retirada de fondos. El sistema alertará y detendrá operaciones si detecta anomalías o vulnerabilidades de seguridad.
            </p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">4. Funcionamiento de Modos de Operación</h2>
            <p>
                La plataforma ofrece un <span class="text-white font-medium">Modo Simulación</span> y un <span class="text-white font-medium">Modo Real</span>. El Modo Simulación utiliza capital ficticio e históricos con fines educativos para que entiendas la operativa del bot. El Modo Real realiza órdenes de mercado reales en tu cuenta de Binance. Es responsabilidad tuya verificar en qué modo te encuentras operando en todo momento.
            </p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">5. Modificaciones de los Términos</h2>
            <p>
                Nos reservamos el derecho de modificar o reemplazar estos Términos de Servicio y Avisos de Riesgo en cualquier momento para reflejar cambios en la funcionalidad, regulaciones legales o condiciones de mercado. Te notificaremos de cambios significativos actualizando la fecha de revisión en la parte superior de esta página.
            </p>
        </section>

        <section>
            <h2 class="text-lg font-semibold text-white mb-2">6. Contacto y Consultas</h2>
            <p>
                Si tienes preguntas sobre estos términos o necesitas asistencia técnica con la plataforma, puedes contactarnos en <a href="mailto:soporte@viboinvest.test" class="text-violet-400 hover:text-violet-300 underline">soporte@viboinvest.test</a>.
            </p>
        </section>
    </div>

    <div class="mt-12 pt-6 border-t border-[rgba(255,255,255,0.06)] text-center">
        <a href="{{ route('register') }}" class="inline-block bg-violet-600 hover:bg-violet-500 text-white font-medium px-6 py-3 rounded-xl transition duration-200 text-sm hover-lift">
            Aceptar y volver al registro
        </a>
    </div>
</div>
@endsection
