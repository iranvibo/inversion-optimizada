PROMT1: 

Actúa como un senior product manager con experiencia en productos SaaS B2B.
Basándote en el siguiente contexto de alto nivel, genera un PRD que incluya: objetivos, stakeholders, user stories (formato "Como [usuario], quiero [acción] para [beneficio]"), requisitos técnicos, métricas de éxito, riesgos potenciales y criterios de aceptación.
Producto: [describe tu producto aquí]
Usuarios objetivo: [describe tu audiencia]
Problema principal: [describe el problema que resuelves]
Restricciones conocidas: [tecnología existente, presupuesto, timeline]

PROMT2: 

Actúa como un Product Owner senior con experiencia en metodologías ágiles.
A partir de la siguiente descripción de producto, genera 5 User Stories
que cumplan los criterios INVEST. Para cada una incluye:
- Título descriptivo
- Historia en formato "Como [rol], quiero [acción], para [beneficio]"
- 3 criterios de aceptación en formato BDD (Dado que/Cuando/Entonces)
- Estimación de complejidad (S/M/L)
- Evaluación breve contra INVEST
Descripción del producto:
idea-inicial.md
prd.md
Después de generar las historias, sugiere un orden de priorización
para el MVP y justifica tu decisión.
guardalo en formato markdown en docs/user-story.md

PROMT3:

Rol: Arquitecto de Software especializado en web apps Objetivo: Eres la persona experta en arquitectura de proyecto. A partir del proyecto descrito en 
idea-inicial.md
prd.md
  realiza Diagrama de arquitectura. Antes de empezar, preguntame que necesitas saber y que vas a considerar para diseñar este diagrama.
quiero usar las siguientes tecnologias:
Frontend & Vistas: Plantillas en Blade compiladas con Vite.
Estilos: 
Tailwind CSS 4
 y CSS personalizado (Vanilla CSS).
Base de datos: MySQL. El contenido dinámico es gestionado a través de Seeders de Laravel.
tambien docker para empaquetar todo

PROMT4:

Actúa como un Ingeniero DevOps y Arquitecto Cloud Senior. Tu objetivo es diseñar, instalar y configurar toda la infraestructura definida en el archivo `docs/architecture.md` de este proyecto.

PROMT5:

Actúa como un Desarrollador de Software Senior y Arquitecto de Software experto en Laravel. Tu objetivo es implementar una historia de usuario específica siguiendo las mejores prácticas de desarrollo y asegurando una cobertura robusta de pruebas unitarias y de integración.

### 1. Pautas de Código y Buenas Prácticas

Al escribir el código, debes seguir estrictamente los siguientes principios:
1. **Código Limpio y SOLID:** Aplica los principios SOLID y mantén las funciones pequeñas y con una única responsabilidad.
2. **Arquitectura:** Arquitectura Limpia (Clean Architecture) estructurada en tres capas (Entidades/Casos de Uso, Adaptadores de Interfaz, e Infraestructura) para desacoplar las APIs de los brokers del core de negocio.
3. **Manejo de Errores y Validaciones:** Implementa validaciones de entrada robustas en la capa más externa y un manejo de errores centralizado con códigos de estado HTTP semánticos y mensajes claros (sin exponer trazas internas).
4. **Seguridad y Rendimiento:** Asegúrate de sanitizar las entradas para prevenir inyecciones, aplicar principios de menor privilegio y optimizar las consultas a la base de datos (evitando problemas como el N+1).

---

### 2. Estrategia de Pruebas (Testing)

Debes escribir las pruebas antes o en paralelo con el código de producción (TDD-friendly). 

#### A. Pruebas Unitarias:
- Escribe pruebas unitarias para cada servicio, controlador, modelo o helper que contenga lógica.
- Utiliza mocks/stubs para aislar la unidad de código bajo prueba (ej: mockear la base de datos, APIs externas o servicios de correo).
- Incluye pruebas para **caminos felices** (happy paths) y **casos límite o de error** (edge cases & error handling).

#### B. Pruebas de Integración:
- Crea escenarios de integración que prueben el flujo completo de la funcionalidad (ej: desde la petición HTTP hasta la base de datos).
- Utiliza una base de datos de pruebas (o mocks a nivel de adaptador de persistencia si es necesario) para asegurar que las transacciones y la persistencia funcionan correctamente.
- Asegúrate de limpiar el estado o la base de datos antes/después de cada prueba para evitar efectos secundarios.

---

### 3. Formato de Salida Esperado

Presenta la solución en los siguientes pasos:
1. **Arquitectura del Directorio:** Muestra un árbol del directorio con los archivos nuevos y modificados.
2. **Código de Producción:** El código completo de la implementación, organizado por archivos con comentarios breves donde sea necesario explicar decisiones de diseño complejas.
3. **Código de Pruebas:** Los archivos de pruebas unitarias y de integración completos.
4. **Instrucciones de Ejecución:** Los comandos necesarios para ejecutar los tests y validar la cobertura.