<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'ViBo Invest') }} — Automatización Premium</title>
    
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Vite CSS & JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Estilos en línea para asegurar colores base y animaciones premium sin esperar a Vite -->
    <style>
        :root {
            --bg-primary: hsl(222, 47%, 10%);
            --bg-secondary: hsl(223, 47%, 14%);
            --bg-tertiary: hsl(223, 47%, 18%);
            --color-text: hsl(210, 40%, 98%);
            --color-text-muted: hsl(215, 20%, 65%);
            --color-primary: hsl(263, 70%, 55%);
            --color-primary-hover: hsl(263, 70%, 62%);
            --color-success: hsl(142, 70%, 45%);
            --color-danger: hsl(0, 84%, 60%);
            --shadow-premium: 0 10px 30px -10px rgba(0, 0, 0, 0.7);
            --border-glow: 1px solid rgba(255, 255, 255, 0.08);
            --font-outfit: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--color-text);
            font-family: var(--font-outfit);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            overflow-x: hidden;
        }

        .gradient-glow {
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.15) 0%, rgba(0, 0, 0, 0) 70%);
            z-index: -1;
            filter: blur(40px);
            pointer-events: none;
        }
        
        .glow-top-right {
            top: -100px;
            right: -100px;
        }
        
        .glow-bottom-left {
            bottom: -150px;
            left: -150px;
        }

        /* Micro-animación para hovers */
        .hover-lift {
            transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1), box-shadow 0.25s ease;
        }
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.8);
        }

        /* Animación para cargar la página */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
    @stack('styles')
</head>
<body class="relative">
    <!-- Fondos de brillo degradados de fondo para sensación premium -->
    <div class="gradient-glow glow-top-right"></div>
    <div class="gradient-glow glow-bottom-left"></div>

    <!-- Barra de Navegación -->
    <nav class="border-b border-[rgba(255,255,255,0.06)] bg-[hsl(223,47%,12%)] backdrop-blur-md sticky top-0 z-50 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <span class="text-2xl font-bold bg-gradient-to-r from-violet-400 to-indigo-400 bg-clip-text text-transparent tracking-tight">
                ViBo <span class="font-light">Invest</span>
            </span>
            <span class="text-[10px] font-semibold bg-violet-500/10 text-violet-400 px-2 py-0.5 rounded-full border border-violet-500/20 uppercase tracking-wider">
                MVP v1.0
            </span>
        </div>
        
        @auth
            <div class="flex items-center space-x-6">
                <a href="{{ route('dashboard') }}" class="text-sm font-medium transition duration-200 text-slate-300 hover:text-white">
                    Dashboard
                </a>
                <div class="h-4 w-px bg-slate-800"></div>
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center font-bold text-sm text-white">
                        {{ substr(Auth::user()->name, 0, 1) }}
                    </div>
                    <span class="text-sm text-slate-300 hidden md:inline">{{ Auth::user()->name }}</span>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="inline m-0 p-0">
                    @csrf
                    <button type="submit" class="text-xs text-slate-400 hover:text-rose-400 transition duration-200 bg-transparent border-0 cursor-pointer">
                        Cerrar Sesión
                    </button>
                </form>
            </div>
        @endauth
    </nav>

    <!-- Contenido Principal -->
    <main class="flex-grow container mx-auto px-6 py-8 max-w-5xl relative z-10">
        @yield('content')
    </main>

    <!-- Pie de página -->
    <footer class="border-t border-[rgba(255,255,255,0.06)] py-6 text-center text-xs text-slate-500 bg-[hsl(223,47%,8%)]">
        <p>&copy; {{ date('Y') }} ViBo Invest. Diseñado para ofrecer total seguridad y simplicidad. Custodia local 100% aislada.</p>
    </footer>
</body>
</html>
