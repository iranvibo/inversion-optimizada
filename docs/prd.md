# Product Requirement Document (PRD): ViBo Invest

## 1. Introducción y Resumen Ejecutivo

### Producto
**ViBo Invest** — Una plataforma web B2C de automatización de trading de criptomonedas diseñada bajo el concepto del "Netflix del trading automatizado". Se enfoca en la extrema simplicidad y en el control estricto de riesgos.

### Usuarios Objetivo
Inversores minoristas y usuarios no técnicos (principalmente de 35 a 55 años). Este perfil posee capital para invertir, pero carece de tiempo o de los conocimientos técnicos avanzados de trading. Tienen un perfil de riesgo moderado a conservador y sienten desconfianza o miedo hacia las plataformas de trading tradicionales que son excesivamente complejas.

### Problema Principal
Las plataformas de trading automatizado actuales parecen "cabinas de avión": están saturadas de gráficos complejos (velas, order books), terminología técnica incomprensible (RSI, MACD, volatilidad anualizada, apalancamiento) y configuraciones abrumadoras. Esto genera una barrera psicológica de entrada, desconfianza sobre el control del dinero y temor a perder todos los fondos por falta de límites de protección claros.

### Restricciones Conocidas
*   **Integración única**: Conexión obligatoria con el exchange **Binance** a través de API Keys.
*   **Seguridad estricta**: Las API Keys no deben tener permisos de retiro bajo ninguna circunstancia.
*   **Simplicidad radical**: Prohibido el uso de indicadores técnicos o interfaces sobrecargadas en el front-end. Toda la comunicación y métricas deben estar en lenguaje humano y comprensible.
*   **Alcance MVP**: El alcance inicial debe limitarse exclusivamente a: login, onboarding con simulación, conexión con Binance, activación/pausado del bot y visualización del balance histórico.

---

## 2. Objetivos del Producto

### Objetivos de Negocio
1.  **Fácil Onboarding**: Lograr que un usuario no técnico configure su cuenta y comience en modo simulación en menos de 10 minutos.
2.  **Generación de Confianza**: Posicionar a ViBo Invest como la herramienta más segura y transparente del mercado minorista, reduciendo la tasa de abandono en la fase de conexión de API.
3.  **Conversión Simulación a Real**: Conseguir que al menos el 25% de los usuarios que prueban la simulación gratuita conecten su cuenta real en los primeros 14 días.

### Objetivos de Experiencia de Usuario (UX)
1.  **Simplicidad**: Ocultar la complejidad algorítmica. El usuario no configura estrategias, solo define límites de riesgo y capital.
2.  **Tranquilidad Mental (Paz Mental)**: Visualizar constantemente el estado de las protecciones (Stop Loss diario, capital protegido) para reforzar la sensación de control.

---

## 3. Stakeholders

| Stakeholder | Rol / Interés en el Proyecto |
| :--- | :--- |
| **Usuarios Finales (Retail)** | Buscan maximizar sus ahorros con el mínimo esfuerzo, de forma segura y entendiendo qué ocurre con su dinero. |
| **Equipo de Desarrollo** | Responsables de la integración segura con Binance, procesamiento de datos de trading en segundo plano y visualización web interactiva y rápida. |
| **Product Manager (PM)** | Vela por el cumplimiento de la simplicidad de la interfaz, el control del alcance del MVP y el alineamiento con las necesidades del cliente B2C. |
| **Responsable de Seguridad (Security & Compliance)** | Garantiza la custodia segura de las API keys y la validación estricta de que no se permiten retiros en las cuentas integradas. |
| **Soporte y Atención al Cliente** | Requieren herramientas sencillas para diagnosticar problemas de conexión API o dudas del usuario sobre el funcionamiento del bot. |

---

## 4. User Stories

### A. Landing Page & Registro
1.  **Como** usuario interesado,  
    **quiero** acceder a una landing page clara y sin jerga técnica,  
    **para** entender en pocos pasos cómo la plataforma puede ayudarme a automatizar mis ahorros de forma segura.
2.  **Como** usuario nuevo,  
    **quiero** registrarme de forma instantánea usando mi cuenta de Google o Apple,  
    **para** evitar rellenar formularios largos y complejos.
3.  **Como** usuario potencial,  
    **quiero** ver respuestas claras a preguntas frecuentes sobre la seguridad de mi dinero (retiros, pérdidas),  
    **para** mitigar mis miedos iniciales antes de crear una cuenta.

### B. Onboarding & Simulación
4.  **Como** usuario recién registrado,  
    **quiero** realizar un cuestionario rápido no técnico de perfil de riesgo (Conservador, Balanceado, Agresivo) y definir un capital estimado,  
    **para** que la plataforma personalice mi experiencia de simulación.
5.  **Como** usuario temeroso de perder dinero,  
    **quiero** ver una simulación interactiva que muestre de forma honesta tanto las ganancias potenciales como las caídas temporales históricas,  
    **para** ajustar mis expectativas reales y construir confianza en la transparencia del bot.

### C. Conexión y Seguridad (Binance)
6.  **Como** usuario listo para operar en real,  
    **quiero** ver un tutorial visual paso a paso (con capturas de pantalla) de cómo crear y vincular mi API Key de Binance sin permisos de retiro,  
    **para** realizar la conexión de manera segura y sin cometer errores técnicos.
7.  **Como** usuario preocupado por la seguridad,  
    **quiero** que el sistema valide mi API Key al ingresarla y me bloquee si detecta que tiene permisos de retiro activados,  
    **para** asegurarme de que mi dinero nunca pueda ser retirado por la plataforma.

### D. Dashboard & Control del Bot
8.  **Como** usuario activo,  
    **quiero** ver el balance total de mi cuenta de manera destacada y un gráfico lineal simple de su evolución (día, semana, mes),  
    **para** evaluar el rendimiento general de mi dinero de un solo vistazo.
9.  **Como** usuario activo,  
    **quiero** ver tarjetas claras que indiquen el estado del bot (Activo, Pausado, Simulación) y el nivel de riesgo actual,  
    **para** sentir control inmediato sobre la automatización.
10. **Como** usuario en control de su riesgo,  
    **quiero** poder activar y pausar el bot con un solo botón simple en cualquier momento,  
    **para** detener la actividad del bot si el mercado me genera intranquilidad.
11. **Como** usuario preocupado por pérdidas catastróficas,  
    **quiero** ver el valor exacto de mi stop-loss diario y de mi capital protegido,  
    **para** tener la seguridad de que el bot nunca perderá más de lo que he autorizado.

### E. Historial de Actividad
12. **Como** usuario curioso,  
    **quiero** ver un registro de actividad reciente redactado en lenguaje humano (ej. "Protección activada" o "Posición cerrada con +1.2%"),  
    **para** entender qué decisiones ha tomado el bot sin necesidad de descifrar logs técnicos o códigos de transacción.

---

## 5. Requisitos Técnicos

### Frontend (Capa de Presentación)
*   **Tecnologías**: HTML5, Vanilla CSS (diseño personalizado premium, paletas de colores HSL armoniosas, modo oscuro elegante, transiciones suaves y microanimaciones). Javascript moderno para reactividad simple.
*   **Enfoque UX/UI**: Ocultar por completo componentes técnicos como gráficos de velas (Candlesticks), indicadores matemáticos (RSI, MACD) o tablas de órdenes pendientes (Order books).
*   **Interactividad**: El panel debe actualizarse de forma dinámica y fluida al cambiar el filtro del bot o activar/pausar operaciones.

### Backend & Integraciones
*   **Autenticación**: Integración con servicios de login social (Google OAuth / Apple Sign-In).
*   **Integración con Exchanges**: Consumo del API REST y WebSockets de **Binance** para:
    *   Verificación de credenciales de API Key y API Secret.
    *   Validación estricta en tiempo de conexión de que la API Key **NO** tiene permisos de retiro (IP/Withdrawal restrictions checking).
    *   Consulta de balance de cuenta en tiempo real.
    *   Ejecución de órdenes de compra/venta y configuración de Stop Loss/Take Profit.
*   **Motor de Simulación (Shadow Mode)**:
    *   Mapeo de datos históricos reales para proyectar curvas de ganancias y drawdowns ("caídas temporales").
    *   Cálculo en tiempo real del peor drawdown semanal/mensual simulado para ajustar los parámetros dinámicamente.

### Seguridad y Almacenamiento
*   **Cifrado de API Keys**: Las claves de API de los usuarios deben almacenarse cifradas en reposo usando algoritmos robustos (ej. AES-256) con llaves de cifrado gestionadas de forma segura.
*   **Auditoría de Permisos**: Sistema automático que verifique periódicamente (ej. una vez al día) que las API vinculadas siguen sin permisos de retiro. Si se detecta un cambio de permisos, el bot debe pausarse de inmediato y notificar al usuario.

---

## 6. Métricas de Éxito

| Categoría | Métrica | Objetivo MVP |
| :--- | :--- | :--- |
| **Adopción** | Tiempo medio de Onboarding (TMO) | < 8 minutos |
| **Conversión** | Tasa de conexión de API Binance | > 40% de los usuarios registrados |
| **Activación** | Activación del Bot (Simulación o Real) | > 80% de los usuarios que completan onboarding |
| **Retención** | Uso recurrente del Dashboard (W1 Retention) | > 60% de los usuarios activos semanales |
| **Calidad** | Incidencias de soporte por error en carga de API | < 5% de las conexiones |

---

## 7. Riesgos Potenciales y Mitigaciones

### R1: Pérdidas en cuentas reales debido a caídas abruptas del mercado (Drawdowns)
*   *Severidad*: Alta | *Probabilidad*: Alta
*   *Mitigación*: Implementar de forma nativa e ineludible un **Stop Loss diario** y un **Capital Protegido** configurable. Detener la ejecución del bot de inmediato si el drawdown diario del usuario supera el límite establecido.

### R2: Desconfianza del usuario al ingresar sus API Keys de Binance
*   *Severidad*: Alta | *Probabilidad*: Media-Alta
*   *Mitigación*: Educar visualmente durante el onboarding con un paso interactivo que muestre que la casilla "Permitir Retiros" debe estar desmarcada en Binance. Validar programáticamente en nuestro backend y mostrar un mensaje de confirmación verde gigante: *"Seguridad Verificada: ViBo Invest no puede retirar tus fondos."*

### R3: Problemas de latencia o caídas de la API de Binance
*   *Severidad*: Media | *Probabilidad*: Media
*   *Mitigación*: Diseñar el backend con políticas de reintento (retry policies) y alertas automáticas. Si la API de Binance no responde, pausar temporalmente la ejecución local del bot y notificar al usuario mediante un banner amigable en el Dashboard (ej. "Conexión temporalmente inestable con Binance, tus fondos están seguros").

---

## 8. Criterios de Aceptación (Criterios de Listoque)

### 1. Registro e Inicio de Sesión
*   **Dado que** un usuario no registrado está en la landing page,  
    **cuando** hace clic en "Probar simulación gratis",  
    **entonces** debe poder registrarse en menos de 3 clics usando Google o Apple Login.

### 2. Onboarding y Configuración de Riesgo
*   **Dado que** un usuario inicia sesión por primera vez,  
    **cuando** completa las 3 pantallas de onboarding (bienvenida, selección de perfil de riesgo simple y slider de capital estimado),  
    **entonces** el sistema debe generar y pintar un gráfico de simulación interactivo que ilustre ganancias proyectadas y caídas temporales reales en base a datos históricos.

### 3. Conexión de Binance sin Permisos de Retiro
*   **Dado que** el usuario ingresa sus API Keys en la pantalla de conexión,  
    **cuando** el sistema comprueba las credenciales contra Binance,  
    **entonces**:
    *   Si los permisos de retiro (*withdrawals*) están activados, debe mostrar un error claro indicando cómo deshabilitarlos y bloquear el proceso.
    *   Si los permisos de retiro están desactivados, debe permitir el acceso al Dashboard real y mostrar un mensaje de éxito sobre la seguridad de la cuenta.

### 4. Control del Bot y Visualización del Balance
*   **Dado que** el usuario está en el Dashboard principal,  
    **cuando** interactúa con el botón de "Pausar",  
    **entonces** el estado del bot debe cambiar a "Pausado" en tiempo real, deteniendo cualquier orden pendiente en la API de Binance.
*   **Dado que** el bot realiza transacciones en segundo plano,  
    **cuando** el usuario visita el historial de actividad,  
    **entonces** debe ver explicaciones claras de las operaciones en lenguaje humano en vez de códigos crudos de transacción de criptomonedas.
