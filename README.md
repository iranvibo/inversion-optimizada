0. Ficha del proyecto

0.1. Tu nombre completo:

Iran Vicente Boligan

0.2. Nombre del proyecto:

ViBo Invest (Inversión Optimizada)

0.3. Descripción breve del proyecto:

Plataforma web B2C de automatización de trading de criptomonedas inspirada en el concepto del "Netflix del trading automatizado". Diseñada con una UX/UI radicalmente simple y enfocada en inversores minoristas no técnicos (entre 35 y 55 años). La plataforma integra de forma segura la API de Binance, cifra localmente las credenciales de los usuarios, consume de forma segura señales de trading desde un proveedor externo y proporciona un control de riesgo ineludible (Stop Loss diario, Capital Protegido) y un historial de actividad redactado enteramente en lenguaje humano.

0.4. URL del proyecto:

*   **Entorno de Desarrollo**: Local (Docker Compose)
*   **Producción**: [Pendiente de Despliegue]

0.5. URL o archivo comprimido del repositorio

*   **Repositorio**: `https://github.com/iranvibo/inversion-optimizada`

---

## 1. Descripción general del producto

### 1.1. Objetivo
El propósito de **ViBo Invest** es eliminar la fricción técnica y la barrera psicológica que enfrentan los inversores minoristas al usar bots de trading automatizados.

*   **Simplicidad radical**: Oculta por completo la complejidad de los gráficos técnicos tradicionales (velas japonesas, libros de órdenes, RSI, MACD, etc.).
*   **Confianza y Control**: Garantiza que el usuario mantenga el control sobre su capital en Binance, impidiendo de forma activa que la plataforma adquiera permisos de retiro.
*   **Paz Mental**: Ofrece mecanismos ineludibles de protección de pérdidas (Stop Loss diario y límite de capital protegido) visibles y configurables.
*   **Educación sin riesgo**: Incorpora un onboarding con simulación en tiempo real (Shadow Mode) utilizando datos históricos del mercado para probar el bot de forma interactiva.

### 1.2. Características y funcionalidades principales
*   **Onboarding Interactivo con Simulación (Shadow Mode)**:
    *   Formulario rápido de perfil de riesgo (Conservador, Balanceado, Agresivo).
    *   Gráfico interactivo de simulación histórica que muestra de forma honesta las ganancias estimadas y las caídas temporales ("drawdowns") del capital en base al perfil y capital seleccionado.
*   **Vinculación Segura de API Keys de Binance**:
    *   Tutorial visual paso a paso para vincular las API Keys de Binance del usuario.
    *   **Validación de Permisos de Retiro**: El sistema deniega la vinculación si detecta que la API Key tiene activados los permisos de retiro (*withdrawals*).
    *   **Auditoría de Seguridad Periódica**: Tarea cron automatizada que verifica cada día que las llaves sigan sin permisos de retiro. Si detecta cambios, detiene el bot instantáneamente.
*   **Dashboard y Panel de Control Simplificado**:
    *   Visualización clara del balance total en euros/dólares.
    *   Gráfico lineal limpio de la evolución histórica de la cuenta (filtrable por Día, Semana y Mes).
    *   Interruptor único (On/Off) de encendido y pausa instantánea del bot.
    *   Indicadores en vivo del estado del bot (Activo, Pausado, Simulación).
*   **Historial de Actividad en Lenguaje Humano**:
    *   Muestra el registro de eventos y operaciones en lenguaje natural (ej. *"Se realizó una compra para aprovechar una caída temporal de precio"* o *"Protección diaria activada: El bot se pausó automáticamente para proteger tu capital"*).

### 1.3. Diseño y experiencia de usuario
*   **Visual Premium**: Paletas de colores armoniosas HSL, modo oscuro elegante por defecto, transiciones suaves y microanimaciones interactivas.
*   **Lenguaje Humano**: Evita términos de la jerga de criptomonedas o del trading técnico. Reemplaza palabras complejas como "volatilidad" por "caídas temporales".

---

## 2. Arquitectura del Sistema y Tecnologías

La plataforma utiliza una arquitectura moderna desacoplada en contenedores y optimizada para procesar datos en tiempo real de manera asíncrona.

*   **Backend / Framework principal**: [Laravel 11](https://laravel.com/) (PHP 8.3+)
    *   Gestión de colas robusta y estructura minimalista.
    *   Cifrado simétrico robusto **AES-256-GCM** para el almacenamiento seguro de las API Keys de los usuarios en la base de datos MySQL.
*   **Frontend**: Plantillas Blade compiladas con [Vite](https://vitejs.dev/)
    *   Reactividad del lado del cliente mediante JavaScript Vanilla y Alpine.js.
    *   Estilos integrados con **Tailwind CSS 4** y **Vanilla CSS** para el diseño premium personalizado.
*   **WebSockets & Tiempo Real**: [Laravel Reverb](https://laravel.com/docs/11.x/reverb)
    *   Servidor de WebSockets de alto rendimiento integrado nativamente en Laravel 11.
    *   Permite empujar actualizaciones en vivo del saldo, estado del bot y alertas de seguridad directamente al navegador del usuario sin necesidad de recurrir a servicios de pago externos (ej. Pusher).
*   **Mensajería, Colas y Caché**: [Redis 7](https://redis.io/)
    *   Gestiona los workers de tareas en segundo plano (auditoría de llaves, procesamiento inmediato de órdenes) y actúa como el broker Pub/Sub de Laravel Reverb.
*   **Base de Datos**: [MySQL 8.0](https://www.mysql.com/)
    *   Persistencia de perfiles de usuario, configuraciones de riesgo e historial de actividad.
    *   Inicialización de configuraciones de riesgo y datos históricos de la simulación mediante **Laravel Seeders**.

---

## 3. Seguridad e Integración de Señales

### 3.1. Aislamiento Estricto de API Keys
Las credenciales de Binance (API Key y Secret Key) de los usuarios están almacenadas en MySQL de forma cifrada simétricamente. **Bajo ninguna circunstancia** estas llaves son compartidas, procesadas o enviadas al proveedor de señales de trading externo.

### 3.2. Integración de Señales vía Webhook con Firma HMAC-SHA256
*   El bot de trading obtiene las señales que debe ejecutar en Binance desde una **API externa al proyecto**.
*   El backend de ViBo Invest actúa como el único **Gatekeeper**:
    1.  Recibe las señales mediante una petición webhook HTTP POST.
    2.  Verifica la procedencia de la señal validando la cabecera `X-Sign` con una firma **HMAC-SHA256** calculada con el cuerpo de la petición y una clave secreta compartida.
    3.  Si la firma coincide, acepta la señal y encola un trabajo asíncrono en Redis.
    4.  El worker extrae la tarea, descifra las llaves API locales del usuario, realiza las validaciones de riesgo locales (Stop Loss diario, Capital Protegido, Bot activo) y ejecuta la orden directamente en Binance.

---

## 4. Topología de Contenedores Docker

El proyecto se ejecuta y distribuye en contenedores aislados que se comunican mediante una red interna puente (`vibo-network`):

1.  **`web` (Nginx)**: Proxy inverso que atiende las solicitudes públicas en los puertos HTTP 80 / HTTPS 443 y redirige peticiones PHP al contenedor del backend.
2.  **`app` (Laravel Backend)**: Contenedor con PHP-FPM donde se aloja y ejecuta la lógica principal del proyecto.
3.  **`websocket` (Laravel Reverb)**: Proceso dedicado al servidor WebSocket autohospedado (Laravel Reverb) expuesto en el puerto 8080.
4.  **`redis`**: Broker de mensajería rápido en memoria para la caché, colas de ejecución y broker de mensajería de Reverb.
5.  **`db` (MySQL)**: Contenedor de base de datos de persistencia relacional.
6.  **`queue-worker`**: Proceso PHP dedicado en segundo plano que corre indefinidamente (`php artisan queue:work`) para consumir trabajos pendientes en Redis.
7.  **`scheduler-worker`**: Cron de Laravel encargado de programar tareas periódicas en segundo plano como la auditoría diaria de API Keys.

---

## 5. Instalación y Despliegue Local (Desarrollo)

### Requisitos Previos
*   [Docker](https://www.docker.com/) instalado en el sistema.
*   [Docker Compose](https://docs.docker.com/compose/) instalado.

### Pasos para Configurar e Iniciar

1.  **Clonar el repositorio**:
    ```bash
    git clone https://github.com/iranvibo/inversion-optimizada.git
    cd inversion-optimizada
    ```

2.  **Configurar variables de entorno**:
    Copia el archivo de configuración `.env.example` como `.env` y define las variables de entorno principales (especialmente las claves secretas de encriptación de la app, Redis, MySQL y la firma del Webhook):
    ```bash
    cp .env.example .env
    ```

3.  **Levantar el entorno Docker**:
    Construye e inicia todos los servicios definidos en el archivo `docker-compose.yml`:
    ```bash
    docker compose up -d --build
    ```

4.  **Instalar dependencias de PHP y generar clave de Laravel**:
    ```bash
    docker compose exec app composer install
    docker compose exec app php artisan key:generate
    ```

5.  **Ejecutar migraciones y poblar la base de datos (Seeders)**:
    Ejecuta las migraciones de base de datos para configurar la base de datos de MySQL e inserta las configuraciones iniciales y datos históricos para la simulación mediante los seeders:
    ```bash
    docker compose exec app php artisan migrate --seed
    ```

6.  **Instalar y compilar dependencias de Frontend (Vite)**:
    ```bash
    docker compose exec app npm install
    docker compose exec app npm run build
    ```

7.  **Verificar ejecución**:
    *   **Frontend y Dashboard**: Accede a través de tu navegador a `http://localhost`.
    *   **Laravel Reverb Websockets**: Conexión activa y escuchando en el puerto `8080`.

---

## 6. Documentación Adicional

Para profundizar en el diseño conceptual, las historias de usuario y los detalles técnicos de la plataforma, consulta los siguientes archivos ubicados en la carpeta `docs/`:

*   [docs/idea-inicial.md](file:///Users/bdado/VSCode/inversion-optimizada/docs/idea-inicial.md): Visión de negocio original y lineamientos de diseño minimalista de ViBo Invest.
*   [docs/prd.md](file:///Users/bdado/VSCode/inversion-optimizada/docs/prd.md): Documento de Requerimientos de Producto (PRD) detallando el alcance del MVP, métricas de éxito y seguridad.
*   [docs/architecture.md](file:///Users/bdado/VSCode/inversion-optimizada/docs/architecture.md): Documento detallado de la arquitectura de software, diagramas Mermaid (topología de red, secuencia de autenticación, firma HMAC) y decisiones técnicas.
*   [docs/user-story.md](file:///Users/bdado/VSCode/inversion-optimizada/docs/user-story.md): User Stories priorizadas y redactadas con criterios INVEST y pruebas en formato BDD.