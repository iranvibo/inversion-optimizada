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