# User Stories: ViBo Invest MVP

Este documento define las 5 User Stories principales para el MVP de **ViBo Invest**, estructuradas bajo el criterio **INVEST** y utilizando el formato de desarrollo guiado por comportamiento (**BDD**) para sus criterios de aceptación.

> [!NOTE]
> **Origen de las Señales del Bot**: El bot de trading obtiene la información y datos de las señales a ejecutar en Binance desde una API externa al proyecto.

---

## 1. User Stories del MVP

### US01: Vinculación Segura de Cuenta de Binance con Validación de Permisos de Retiro
* **Título descriptivo:** Vinculación segura de cuenta de Binance sin permisos de retiro.
* **Fórmula de Historia:**
  * **Como** usuario listo para operar en real,
  * **quiero** que la plataforma valide que mi API Key de Binance no tenga activados los permisos de retiro,
  * **para** tener la absoluta seguridad de que mi dinero nunca podrá ser retirado por la plataforma.
* **Criterios de Aceptación (BDD):**
  * **Escenario 1: Detección de permisos de retiro activos durante la vinculación**
    * **Dado que** estoy en la pantalla de vinculación de Binance y he ingresado mi API Key y Secret Key,
    * **cuando** el sistema comprueba los permisos con la API de Binance y detecta que la opción de retiros (*withdrawals*) está habilitada,
    * **entonces** el sistema debe mostrar un mensaje de alerta destacado en rojo que explique de forma sencilla cómo desactivar dicha opción y debe bloquear la vinculación de la API.
  * **Escenario 2: Vinculación exitosa con permisos de retiro desactivados**
    * **Dado que** estoy en la pantalla de vinculación de Binance y he ingresado mi API Key y Secret Key,
    * **cuando** el sistema comprueba los permisos con la API de Binance y detecta que la opción de retiros está deshabilitada,
    * **entonces** el sistema debe permitir la vinculación, redirigirme al Dashboard real y mostrar un mensaje verde destacado de confirmación de seguridad ("Seguridad Verificada: ViBo Invest no puede retirar tus fondos").
  * **Escenario 3: Verificación periódica en segundo plano**
    * **Dado que** tengo mi API Key de Binance vinculada y el bot está activo,
    * **cuando** el sistema realiza la verificación periódica automatizada de permisos y detecta que se han habilitado los permisos de retiro en Binance,
    * **entonces** el sistema debe pausar de forma inmediata la actividad del bot por seguridad y notificar al usuario de forma destacada en la interfaz web.
* **Estimación de complejidad:** M (Medium)
* **Evaluación contra INVEST:**
  * **I (Independiente):** Es independiente de la lógica de trading o de la visualización de balances históricos; se centra solo en la pasarela de autenticación y verificación de permisos.
  * **N (Negociable):** La frecuencia de la validación periódica (ej. cada 24 horas o cada hora) y el diseño visual de la guía técnica son ajustables.
  * **V (Valioso):** Crucial para mitigar el miedo del usuario minorista y generar confianza al garantizar que el capital permanece seguro en su exchange.
  * **E (Estimable):** La documentación de la API de Binance define claramente los endpoints para consultar los permisos de una API Key vinculada.
  * **S (Pequeño):** Se acota estrictamente al flujo de ingreso, validación técnica y persistencia segura de credenciales API.
  * **T (Testeable):** Se puede testear mediante mocks de la API de Binance simulando respuestas con y sin permisos de retiro.

---

### US02: Configuración de Perfil de Riesgo y Simulación Histórica Interactiva en Onboarding
* **Título descriptivo:** Simulación personalizada basada en perfil de riesgo y capital estimado.
* **Fórmula de Historia:**
  * **Como** usuario recién registrado,
  * **quiero** realizar un cuestionario rápido de perfil de riesgo y ajustar mi capital con un deslizador,
  * **para** visualizar una simulación interactiva y honesta del rendimiento histórico de mi inversión sin poner en riesgo dinero real.
* **Criterios de Aceptación (BDD):**
  * **Escenario 1: Generación de proyección dinámica según parámetros del usuario**
    * **Dado que** he completado mi registro e iniciado el onboarding,
    * **cuando** selecciono un perfil de riesgo (Conservador, Balanceado, Agresivo) y ajusto el slider de capital estimado a un valor específico (ej. 1000€),
    * **entonces** el sistema debe proyectar dinámicamente un gráfico histórico con la evolución de la inversión basado en esos parámetros.
  * **Escenario 2: Transparencia en la visualización de caídas temporales (Drawdowns)**
    * **Dado que** estoy interactuando con el gráfico de simulación en el onboarding,
    * **cuando** el gráfico dibuja la evolución temporal del bot,
    * **entonces** debe destacar explícitamente tanto el rendimiento positivo acumulado como la peor caída temporal sufrida históricamente en lenguaje natural (ej. "La cuenta ha tenido caídas temporales de hasta un 12%").
  * **Escenario 3: Persistencia de parámetros iniciales**
    * **Dado que** he terminado de revisar la simulación del onboarding,
    * **cuando** hago clic en el botón de confirmación para finalizar el onboarding,
    * **entonces** el sistema debe guardar mi perfil de riesgo y capital estimado para establecerlos como la configuración por defecto de mi bot en modo simulación.
* **Estimación de complejidad:** M (Medium)
* **Evaluación contra INVEST:**
  * **I (Independiente):** Funciona completamente del lado del cliente y del servidor con datos históricos y modelos matemáticos internos; no requiere conexión con Binance en vivo para funcionar.
  * **N (Negociable):** Los textos descriptivos de cada nivel de riesgo y los límites mínimos/máximos del deslizador son flexibles y abiertos a cambios de diseño de producto.
  * **V (Valioso):** Es el primer punto de contacto real con el valor del producto, demostrando transparencia (al mostrar pérdidas históricas) y disminuyendo la fricción inicial.
  * **E (Estimable):** El equipo puede estimar con exactitud el esfuerzo de construir la interfaz interactiva y el set de datos simulados fijos para las proyecciones.
  * **S (Pequeño):** Se limita al flujo guiado inicial y su salida visual interactiva, sin interferir con la integración de trading real.
  * **T (Testeable):** Se puede comprobar validando que al cambiar de perfil de riesgo o mover el slider de capital, el gráfico y los textos de pérdidas asociadas se recalculan dinámicamente.

---

### US03: Visualización de Balance Total y Gráfico de Rendimiento Lineal Simple en Dashboard
* **Título descriptivo:** Dashboard principal con visualización de balance y gráfico de evolución simple.
* **Fórmula de Historia:**
  * **Como** usuario activo,
  * **quiero** ver el balance total de mi cuenta de Binance de forma clara y un gráfico lineal simple de su evolución,
  * **para** conocer de un vistazo el rendimiento general de mi dinero sin distraerme con gráficos técnicos de trading.
* **Criterios de Aceptación (BDD):**
  * **Escenario 1: Carga del dashboard limpio de complejidad técnica**
    * **Dado que** he accedido al Dashboard de mi cuenta vinculada,
    * **cuando** carga la página principal,
    * **entonces** el sistema debe mostrar mi balance consolidado en euros/dólares en letra grande y legible, y un gráfico de líneas sin indicadores técnicos (sin velas, RSI, MACD o libros de órdenes).
  * **Escenario 2: Cambio interactivo del filtro temporal**
    * **Dado que** estoy visualizando el gráfico de evolución en el dashboard,
    * **cuando** selecciono uno de los filtros temporales (Día, Semana, Mes),
    * **entonces** el gráfico lineal debe actualizarse dinámicamente mostrando la serie temporal correspondiente y el porcentaje de cambio del balance en lenguaje humano.
  * **Escenario 3: Actualización reactiva del balance**
    * **Dado que** el saldo en mi cuenta real de Binance cambia debido a operaciones,
    * **cuando** el backend sincroniza los datos del exchange,
    * **entonces** el balance en pantalla debe actualizarse de forma reactiva con una transición visual sutil para denotar el estado vivo de la cuenta.
* **Estimación de complejidad:** M (Medium)
* **Evaluación contra INVEST:**
  * **I (Independiente):** Es independiente de las pantallas de onboarding y de las acciones de encendido/apagado manual del bot; solo consulta y visualiza balances.
  * **N (Negociable):** La paleta de colores del gráfico, el tipo de gráfico lineal y el intervalo exacto de actualización de datos se pueden ajustar tras pruebas de UX.
  * **V (Valioso):** Responde a la necesidad básica del usuario de monitorear y entender el estado de sus ahorros de forma rápida y sin estrés cognitivo.
  * **E (Estimable):** Las librerías de gráficos web estándar y las APIs de consulta de Binance simplifican y hacen predecible la estimación de esta tarea.
  * **S (Pequeño):** Se centra de forma exclusiva en la visualización agregada y el comportamiento de filtros de tiempo, abstrayéndose de la lógica interna de compra/venta.
  * **T (Testeable):** Se puede verificar mediante pruebas unitarias y de integración inyectando conjuntos de datos de balances con diferentes rangos y validando su renderizado correcto.

---

### US04: Control de Encendido/Apagado Manual e Instantáneo del Bot
* **Título descriptivo:** Control manual de activación y pausa del bot.
* **Fórmula de Historia:**
  * **Como** usuario en control de su riesgo,
  * **quiero** poder activar y pausar el bot de trading mediante un único botón simple en el dashboard,
  * **para** detener o reanudar las operaciones de inmediato si el mercado o mi situación personal me generan intranquilidad.
* **Criterios de Aceptación (BDD):**
  * **Escenario 1: Activación instantánea del bot**
    * **Dado que** el bot de trading se encuentra en estado "Pausado",
    * **cuando** hago clic en el botón "Activar",
    * **entonces** el sistema debe cambiar el estado del bot a "Activo" en la base de datos, enviar una señal al motor de ejecución de órdenes y actualizar el indicador visual en pantalla a verde en tiempo real.
  * **Escenario 2: Pausado instantáneo del bot**
    * **Dado que** el bot de trading se encuentra en estado "Activo",
    * **cuando** hago clic en el botón "Pausar",
    * **entonces** el sistema debe cambiar el estado del bot a "Pausado" en la base de datos, detener de forma inmediata la colocación de nuevas órdenes en Binance y cambiar el indicador visual a un color gris/ámbar.
  * **Escenario 3: Cierre preventivo al pausar**
    * **Dado que** el bot se encuentra en estado "Activo" y tiene posiciones de trading abiertas,
    * **cuando** el usuario hace clic en "Pausar",
    * **entonces** el sistema debe proceder a gestionar o cerrar preventivamente las posiciones activas en Binance de forma segura según la regla de mitigación definida, antes de establecer el estado "Pausado" definitivo.
* **Estimación de complejidad:** S (Small)
* **Evaluación contra INVEST:**
  * **I (Independiente):** Si bien activa o desactiva la ejecución del motor de trading, el flujo de cambio de estado y control del botón en el UI es modular.
  * **N (Negociable):** La lógica de mitigación en Binance al presionar "Pausar" (ej. cancelar órdenes límite abiertas, cerrar a mercado o mantener posiciones actuales) es negociable según el comportamiento de seguridad ideal.
  * **V (Valioso):** Otorga al usuario el control absoluto de su capital en tiempo real, lo que genera un gran alivio psicológico ante fluctuaciones del mercado.
  * **E (Estimable):** El equipo puede estimar fácilmente la creación del endpoint del backend para cambiar el estado y la reactividad del botón en la interfaz.
  * **S (Pequeño):** Consiste en un interruptor digital lógico (On/Off) y su respectiva propagación a los hilos de ejecución del backend.
  * **T (Testeable):** Se puede verificar mediante pruebas automáticas que comprueban que el backend rechaza o detiene nuevas órdenes si el flag de estado se encuentra en "Pausado".

---

### US05: Historial de Transacciones y Acciones del Bot Redactado en Lenguaje Humano
* **Título descriptivo:** Historial de actividad del bot redactado en lenguaje sencillo.
* **Fórmula de Historia:**
  * **Como** usuario no técnico,
  * **quiero** ver un registro de actividad reciente con explicaciones claras y naturales sobre las acciones tomadas por el bot,
  * **para** entender por qué se realizaron transacciones sin necesidad de analizar códigos o logs complejos.
* **Criterios de Aceptación (BDD):**
  * **Escenario 1: Conversión de transacciones a lenguaje humano**
    * **Dado que** el bot ha operado recientemente en mi cuenta,
    * **cuando** navego a la pestaña de "Actividad",
    * **entonces** el sistema debe mostrar un feed de eventos donde cada orden de Binance esté traducida a lenguaje natural (ej. "Se realizó una compra para aprovechar una caída temporal de precio" en lugar de un código de transacción crudo).
  * **Escenario 2: Visualización amigable de rendimientos individuales**
    * **Dado que** una posición se ha cerrado con beneficio o pérdida,
    * **cuando** se lista en el historial de actividad,
    * **entonces** debe mostrarse de forma explícita el resultado neto (ej. "Posición cerrada con un +1.5% de beneficio (+15€)" o "Protección de pérdida activada para asegurar tu capital").
  * **Escenario 3: Resaltado visual de eventos de protección de riesgo**
    * **Dado que** el bot activa una protección automática de riesgo (como el stop-loss diario),
    * **cuando** este evento se registra en el feed de actividad,
    * **entonces** el sistema debe destacarlo visualmente con un diseño de alerta en la parte superior del historial (ej. "Protección diaria activada: El bot se pausó automáticamente para proteger tu capital").
* **Estimación de complejidad:** S (Small)
* **Evaluación contra INVEST:**
  * **I (Independiente):** Es independiente de la ejecución en vivo del trading o del onboarding; actúa de manera retrospectiva leyendo registros de base de datos.
  * **N (Negociable):** El catálogo exacto de mensajes de traducción y los criterios de colores para el feed son refinables mediante pruebas con usuarios.
  * **V (Valioso):** Convierte la incertidumbre de las transacciones crudas en transparencia y comprensión total del bot, aumentando la confianza.
  * **E (Estimable):** Crear plantillas de traducción mapeadas a los tipos de transacción y estados del bot es una tarea de desarrollo predecible.
  * **S (Pequeño):** Consiste en un componente de visualización que lee y da formato legible a un registro de eventos ya existente.
  * **T (Testeable):** Se puede comprobar insertando eventos simulados en base de datos y verificando que el frontend despliega las descripciones textuales correctas según el mapa de traducción.

---

## 2. Priorización del MVP y Justificación

Como Product Owner, recomiendo estructurar el desarrollo del MVP de **ViBo Invest** bajo un enfoque de embudo de conversión y seguridad progresiva, priorizando las historias en el siguiente orden:

```mermaid
graph TD
    US02[1. US02: Simulación interactiva en Onboarding] --> US01[2. US01: Vinculación segura de Binance]
    US01 --> US03[3. US03: Dashboard de balance simple]
    US03 --> US04[4. US04: Control manual Activar/Pausar]
    US04 --> US05[5. US05: Historial en lenguaje humano]
```

### Tabla de Priorización

| Orden | ID / Story | Complejidad | Impacto en Confianza / Retención | Justificación |
| :---: | :--- | :---: | :---: | :--- |
| **1** | **US02**: Simulación Interactiva en Onboarding | **M** | **Muy Alto** (Gancho Inicial) | **Conversión y Educación:** Permite al usuario experimentar inmediatamente la propuesta de valor sin fricción, reduciendo el miedo inicial antes de pedirle que vincule sus activos reales o configure exchanges. |
| **2** | **US01**: Vinculación Segura de Binance (No-Retiro) | **M** | **Extremo** (Seguridad) | **Crucial de Seguridad:** Es la puerta de entrada a la operativa real. Si el usuario no confía en este paso o si no se validan los permisos de retiro de forma infalible, el producto entero falla. |
| **3** | **US03**: Dashboard de Balance y Evolución Simple | **M** | **Alto** (Visualización) | **Visibilidad Financiera:** Una vez conectada la API, el usuario necesita ver su balance consolidado actual. Es la pantalla principal de monitoreo diario que fomenta la retención de los usuarios. |
| **4** | **US04**: Control Manual de Activación y Pausa | **S** | **Muy Alto** (Control) | **Paz Mental Activa:** Es el botón de control operativo que permite al usuario decidir cuándo trabaja el bot o apagarlo instantáneamente ante la intranquilidad del mercado. |
| **5** | **US05**: Historial de Actividad en Lenguaje Humano | **S** | **Medio-Alto** (Transparencia) | **Comprensión Operativa:** Proporciona un registro claro de qué está haciendo la automatización. Se sitúa al final del MVP porque requiere que la plataforma ya sea capaz de simular u operar para generar logs de eventos. |

---

### Justificación Detallada del Orden Propuesto

1. **La Simulación como Primer Paso (US02)**: En productos de inversión para usuarios no técnicos, el mayor obstáculo es el miedo a perder dinero. Si exigimos la conexión de Binance (US01) al inicio del flujo, la tasa de abandono se disparará. Mostrando una simulación interactiva, transparente y honesta (que muestre tanto ganancias como caídas históricas reales), ganamos la confianza inicial del usuario en menos de 10 minutos.
2. **Seguridad y Validación Fuerte (US01)**: Una vez convencido con la simulación, el usuario da el paso de vincular su capital real. Aquí, el sistema debe ser implacable en la verificación técnica: impedir la vinculación si hay permisos de retiro es nuestra promesa de seguridad fundamental y no es negociable en el MVP.
3. **El Corazón del Dashboard (US03 e US04)**: Con la cuenta conectada de forma segura, se construye el centro de control del usuario. La visualización simple de su saldo y rendimiento (US03) le permite comprobar que todo está en orden, mientras que el botón de Activar/Pausar (US04) le otorga una "válvula de escape" manual que le da total soberanía y tranquilidad mental.
4. **Feed de Actividad Comprensible (US05)**: Por último, el historial en lenguaje humano sirve para responder a la pregunta de *"¿qué está haciendo el bot ahora mismo?"*. Al evitar la jerga técnica y explicar los movimientos como lo haría una persona, cerramos el círculo de simplicidad, transparencia y control sin saturar al usuario en el día a día.
