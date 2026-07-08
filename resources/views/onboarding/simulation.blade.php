@extends('layouts.app')

@section('content')
<div class="animate-fade-in max-w-3xl mx-auto">
    <!-- Encabezado -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-bold tracking-tight mb-2">Configura tu inversión</h1>
        <p class="text-slate-400 text-sm max-w-xl mx-auto">
            Elige tu perfil de riesgo y ajusta tu capital estimado para ver una simulación
            <span class="text-violet-400 font-medium">honesta</span> del rendimiento histórico del bot.
            Sin dinero real, sin sorpresas.
        </p>
    </div>

    <form id="onboarding-form" action="{{ route('onboarding.complete') }}" method="POST"
          class="bg-[hsl(223,47%,14%)] rounded-2xl p-8 shadow-premium border border-[rgba(255,255,255,0.08)]">
        @csrf

        <!-- Paso 1: Perfil de riesgo -->
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">1 · Tu perfil de riesgo</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8" id="risk-options">
            @foreach ($profiles as $profile)
                <label class="risk-card cursor-pointer rounded-xl border p-4 transition duration-200 hover-lift
                              {{ $profile === $defaultProfile ? 'border-violet-500 bg-violet-500/10' : 'border-[rgba(255,255,255,0.08)] bg-[hsl(223,47%,18%)]' }}">
                    <input type="radio" name="risk_profile" value="{{ $profile->value }}" class="hidden"
                           {{ $profile === $defaultProfile ? 'checked' : '' }}>
                    <span class="block font-semibold mb-1">{{ $profile->label() }}</span>
                    <span class="block text-xs text-slate-400 leading-relaxed">{{ $profile->description() }}</span>
                </label>
            @endforeach
        </div>

        <!-- Paso 2: Capital estimado -->
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">2 · Tu capital estimado</h2>
        <div class="mb-8">
            <div class="flex items-baseline justify-between mb-3">
                <span class="text-slate-400 text-sm">{{ number_format($minCapital, 0, ',', '.') }}$</span>
                <span id="capital-display" class="text-3xl font-bold text-violet-400">{{ number_format($defaultCapital, 0, ',', '.') }}$</span>
                <span class="text-slate-400 text-sm">{{ number_format($maxCapital, 0, ',', '.') }}$</span>
            </div>
            <input type="range" name="capital" id="capital-slider"
                   min="{{ $minCapital }}" max="{{ $maxCapital }}" step="100" value="{{ $defaultCapital }}"
                   class="w-full accent-violet-500 cursor-pointer">
        </div>

        <!-- Paso 3: Simulación interactiva -->
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">3 · Así se habría comportado tu inversión</h2>
        <div class="rounded-xl bg-[hsl(222,47%,10%)] border border-[rgba(255,255,255,0.06)] p-5 mb-6">
            <svg id="simulation-chart" viewBox="0 0 600 240" class="w-full h-auto" aria-label="Gráfico de simulación histórica"></svg>

            <!-- Transparencia: rendimiento acumulado y peor caída (Escenario 2) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                <div class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 p-4">
                    <span class="block text-xs text-emerald-400 uppercase tracking-wider mb-1">Rendimiento acumulado</span>
                    <span id="total-return" class="text-xl font-bold text-emerald-400">—</span>
                </div>
                <div class="rounded-lg bg-rose-500/10 border border-rose-500/20 p-4">
                    <span class="block text-xs text-rose-400 uppercase tracking-wider mb-1">Honestidad ante todo</span>
                    <span id="drawdown-message" class="text-sm font-medium text-rose-300 leading-snug">—</span>
                </div>
            </div>
        </div>

        <p id="simulation-error" class="hidden text-sm text-rose-400 mb-4"></p>

        <!-- Confirmación (Escenario 3) -->
        <button type="submit"
                class="w-full py-3.5 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 font-semibold text-white transition duration-200 hover-lift cursor-pointer border-0">
            Confirmar y empezar en modo simulación
        </button>
        <p class="text-center text-xs text-slate-500 mt-3">
            Tu perfil y capital quedarán guardados como configuración por defecto del bot. Podrás cambiarlos cuando quieras.
        </p>
    </form>
</div>

<script>
(function () {
    const csrfToken = document.querySelector('#onboarding-form input[name="_token"]').value;
    const slider = document.getElementById('capital-slider');
    const capitalDisplay = document.getElementById('capital-display');
    const chart = document.getElementById('simulation-chart');
    const totalReturnEl = document.getElementById('total-return');
    const drawdownEl = document.getElementById('drawdown-message');
    const errorEl = document.getElementById('simulation-error');
    const riskCards = document.querySelectorAll('.risk-card');

    const formatDollar = (value) => new Intl.NumberFormat('es-ES', { maximumFractionDigits: 0 }).format(value) + '$';

    function selectedProfile() {
        return document.querySelector('input[name="risk_profile"]:checked').value;
    }

    function highlightCards() {
        riskCards.forEach((card) => {
            const checked = card.querySelector('input').checked;
            card.classList.toggle('border-violet-500', checked);
            card.classList.toggle('bg-violet-500/10', checked);
            card.classList.toggle('border-[rgba(255,255,255,0.08)]', !checked);
            card.classList.toggle('bg-[hsl(223,47%,18%)]', !checked);
        });
    }

    const formatDollarAxis = (val) =>
        new Intl.NumberFormat('es-ES', { maximumFractionDigits: 0 }).format(val) + '$';

    function formatDateAxis(dateStr) {
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        // Misma fuente que la gráfica principal: "12 jun"
        return date.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
    }

    // Gráfica con ejes de precio (Y) y tiempo (X), igual que la del dashboard.
    function drawChart(series) {
        if (!series || series.length < 2) { chart.innerHTML = ''; return; }

        const W = 600, H = 240;
        const PAD_TOP = 15, PAD_BOTTOM = 25, PAD_LEFT = 15, PAD_RIGHT = 75;

        const values = series.map((p) => p.value);
        let min = Math.min(...values), max = Math.max(...values);

        // Atenúa el ruido forzando una amplitud mínima del 5% (como el dashboard)
        const avg = (min + max) / 2 || 1;
        const minRange = avg * 0.05;
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

        // Eje Y: líneas de cuadrícula y etiquetas de precio
        const gridValues = [max, (min + max) / 2, min];
        let yAxisHtml = '';
        gridValues.forEach((val) => {
            const yVal = y(val);
            yAxisHtml += `
                <line x1="${PAD_LEFT}" y1="${yVal}" x2="${W - PAD_RIGHT}" y2="${yVal}" stroke="rgba(255, 255, 255, 0.06)" stroke-width="1" stroke-dasharray="4 4" />
                <text x="${W - PAD_RIGHT + 8}" y="${yVal}" fill="#94a3b8" font-size="10" font-family="system-ui, -apple-system, sans-serif" alignment-baseline="middle" text-anchor="start">${formatDollarAxis(val)}</text>
            `;
        });

        // Eje X: etiquetas temporales (inicio, medio, fin)
        const midIndex = Math.floor((series.length - 1) / 2);
        const xAxisIndices = [0, midIndex, series.length - 1].filter((val, i, self) => self.indexOf(val) === i);
        let xAxisHtml = '';
        xAxisIndices.forEach((idx, i) => {
            const xVal = x(idx);
            let anchor = 'middle';
            if (xAxisIndices.length > 1) {
                if (i === 0) anchor = 'start';
                else if (i === xAxisIndices.length - 1) anchor = 'end';
            }
            xAxisHtml += `
                <text x="${xVal}" y="${H - 5}" fill="#94a3b8" font-size="10" font-family="system-ui, -apple-system, sans-serif" text-anchor="${anchor}">${formatDateAxis(series[idx].t)}</text>
            `;
        });

        chart.innerHTML = `
            <defs>
                <linearGradient id="area-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="rgba(139, 92, 246, 0.35)"/>
                    <stop offset="100%" stop-color="rgba(139, 92, 246, 0)"/>
                </linearGradient>
            </defs>
            ${yAxisHtml}
            <polygon points="${areaPoints}" fill="url(#area-fill)"/>
            <polyline points="${linePoints}" fill="none" stroke="hsl(263, 70%, 62%)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            ${xAxisHtml}
        `;
    }

    let debounceTimer = null;
    let activeRequest = 0;

    async function refreshSimulation() {
        errorEl.classList.add('hidden');
        const requestId = ++activeRequest;

        try {
            const response = await fetch('{{ route('onboarding.simulate') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ risk_profile: selectedProfile(), capital: Number(slider.value) }),
            });

            if (requestId !== activeRequest) return; // descarta respuestas obsoletas

            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                throw new Error(body.message || 'No se pudo calcular la simulación.');
            }

            const data = await response.json();
            drawChart(data.series);
            totalReturnEl.textContent = (data.total_return_percent >= 0 ? '+' : '') + data.total_return_percent.toFixed(1) + '%  ·  ' + formatDollar(data.final_value);
            drawdownEl.textContent = data.drawdown_message;
        } catch (err) {
            errorEl.textContent = err.message;
            errorEl.classList.remove('hidden');
        }
    }

    function scheduleRefresh() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshSimulation, 150);
    }

    slider.addEventListener('input', () => {
        capitalDisplay.textContent = formatDollar(Number(slider.value));
        scheduleRefresh();
    });

    riskCards.forEach((card) => {
        card.addEventListener('change', () => {
            highlightCards();
            scheduleRefresh();
        });
    });

    // Primera proyección al cargar la página
    refreshSimulation();
})();
</script>
@endsection
