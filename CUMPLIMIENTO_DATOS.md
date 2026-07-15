# Cumplimiento de protección de datos — Wabot

Estado: auditoría inicial. No sustituye asesoría jurídica ni certifica cumplimiento.

## Modelo de responsabilidades propuesto

- Cada negocio que usa Wabot es el **responsable del tratamiento** de los datos de sus visitantes, prospectos y pacientes/clientes.
- Rodas AI/Wabot opera como **encargado**, exclusivamente bajo instrucciones del negocio.
- Meta, OpenAI, Vercel y Hostinger pueden intervenir como subencargados/proveedores, según la configuración efectiva de cada instalación.

Este modelo debe quedar reflejado en el contrato de servicio y en el aviso de privacidad de cada negocio.

## Hallazgos de la auditoría inicial

### Ya existe

- Persistencia de conversaciones, prospectos y citas en el WC del cliente.
- Restricción de origen en los endpoints de almacenamiento del Chatbot.
- Validación de cliente activo mediante API Key o License Key.
- Deducción comercial con la regla de no tomar datos declarados por el asistente como datos del visitante.
- Eliminación manual de prospectos desde la administración.

### No suficiente todavía

1. Aviso de privacidad visible, específico por negocio y antes de recolectar datos.
2. Registro demostrable de la base legal y, cuando corresponda, del consentimiento: fecha, versión del aviso, canal y finalidad.
3. Mecanismo verificable, gratuito y simple para acceso, rectificación, oposición, supresión y portabilidad.
4. Supresión o anonimización integral: prospecto, mensajes de WhatsApp, Chatbot, citas y referencias vinculadas.
5. Plazos de conservación configurables y tarea periódica de borrado/anominización.
6. Explicación visible de la elaboración de perfiles: resumen comercial, intención, temperatura y puntaje.
7. Información de transferencias internacionales y subencargados, incluido el uso transitorio de OpenAI y la infraestructura configurada.
8. Acuerdo de encargo de tratamiento con cada negocio y autorización para subencargados.
9. Registro de actividades de tratamiento, controles de acceso, auditoría de acciones administrativas y procedimiento de incidentes.
10. Evaluación de impacto previa para las instancias que hagan perfilamiento sistemático, manejen datos sensibles o tengan alcance elevado.
11. Política especial para menores y bloqueo de uso de datos sensibles en los flujos no habilitados.

## Decisiones de producto obligatorias

- No usar conversaciones ni datos de clientes para entrenar modelos de Wabot.
- Enviar a WS/OpenAI solo lo indispensable para responder o extraer información; no persistir allí el contenido ni la License Key.
- La calificación comercial no debe tomar decisiones con efectos jurídicos ni negar servicios automáticamente. Debe poder revisarla un humano.
- La funcionalidad de agenda debe solicitar únicamente los datos necesarios para reservar.

## Implementación pendiente, en orden

1. Reemplazar `privacidad.html` por un aviso completo y parametrizable por cliente.
2. Añadir aceptación del aviso al Chatbot, con registro de versión y fecha.
3. Crear un módulo de solicitudes de derechos y exportación/supresión verificadas.
4. Añadir política de retención y proceso de purga/anominización.
5. Añadir registro de actividad e incidente de seguridad.
6. Documentar y firmar el acuerdo responsable–encargado y subencargados.
7. Realizar y conservar una evaluación de impacto cuando aplique.

## Nota de vigencia

El texto recibido como `Ley 7593/25` muestra una fecha de 27/11/2027, posterior a la fecha actual de este proyecto. Antes de presentar Wabot como "cumplidor de la ley", debe verificarse la versión oficial, su vigencia, reglamentación y autoridad competente con asesoría legal paraguaya.
