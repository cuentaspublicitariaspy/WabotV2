# Correcciones y decisiones de Wabot

Este documento registra decisiones funcionales, de nomenclatura y de interfaz acordadas durante el desarrollo. Debe consultarse antes de modificar WC o WS.

## Nombres oficiales

- **WC**: Wabot Cliente. Aplicación PHP/MySQL instalada en el hosting de cada cliente.
- **WS**: Wabot Servidor. Servicio central en Vercel que administra clientes, licencias, API y Chatbot.
- **Base de Conocimiento**: identidad, productos, preguntas frecuentes, reglas, tono y condiciones de derivación. No llamarla “Chatbot”.
- **Chatbot**: elemento que se inserta en el sitio web del cliente. No llamarlo “Widget” en textos de interfaz, manuales ni comunicación; los nombres técnicos existentes pueden permanecer.

## Instalación y actualizaciones de WC

- Cada WC se instala como una copia independiente y queda desconectada de GitHub.
- Deben existir dos opciones de entrega: paquete de instalación mediante File Manager/SFTP, o despliegue Git de una sola vez seguido de desconexión y eliminación de `.git`.
- Nunca dejar el hosting de un cliente actualizado automáticamente desde el repositorio.
- **Excepción de pruebas:** el hosting de laboratorio puede conectarse temporalmente a la rama `master` para iterar y corregir rápido. Al aprobar la prueba A–Z, se desconecta Git; los WC reales se entregan como copias independientes.
- El instalador conecta a una base de datos que el equipo crea previamente. Al finalizar el alta inicial, `setup.php`, `setup_admin.php` e `init.sql` se eliminan automáticamente.
- Si WC encuentra un `.env` anterior cuya base ya fue eliminada, el instalador debe volver a mostrar el formulario de base de datos, sin bucles de redirección ni pasos manuales sobre archivos ocultos.
- El paquete de instalación WC debe incluir únicamente el código PHP necesario y la carpeta vacía `uploads/`; debe excluir WS (`wabot-cdn/`), Git, `.env`, datos de clientes, documentación interna y herramientas de construcción.
- La verificación GET del webhook de Meta debe responder el `hub.challenge` en texto plano antes de cargar base de datos o procesadores de mensajes.
- Un envío manual de WhatsApp se considera exitoso ante cualquier respuesta HTTP 2xx de Meta; no debe mostrarse un error si el mensaje fue aceptado y entregado.
- Para las respuestas automáticas de WhatsApp, WS debe entregar a WC un contrato estable con `content`; WC conserva compatibilidad con el formato original de OpenAI durante actualizaciones parciales.
- La IA automática solo responde cuando no existen agentes humanos activos. Mientras un administrador/agente figura **Online**, la conversación se asigna a esa persona y la IA no interviene.
- Un mismo webhook entrante de Meta debe procesarse una sola vez, incluso cuando Meta lo reintenta mientras WC genera una respuesta. Nunca se deben enviar respuestas duplicadas.
- Al subir o agregar una fuente, WC debe confirmar el resultado y permanecer en **Comunicación Inteligente → Conocimiento**.
- La Base de Conocimiento debe ser la fuente principal de las respuestas del Chatbot y de WhatsApp. Si falta información, la IA debe reconocerlo y ofrecer derivación; no inventar datos.
- El Chatbot debe aislarse de los estilos del sitio que lo contiene, respetar su color configurado y conservar un encuadre legible tanto en escritorio como en móvil.
- La Base de Conocimiento puede enviarse de forma transitoria desde WC a OpenAI para generar una respuesta, mediante la API Key central. WS no debe guardar ni poner en caché su contenido; al terminar la solicitud se descarta.
- Los mensajes salientes de WhatsApp deben guardarse en WC antes de registrar métricas. El identificador externo de Meta nunca se usa como ID interno de base de datos; el envío aceptado no puede terminar mostrando un falso error en el panel.
- La IA debe recibir el historial completo relevante, incluidas sus respuestas previas, y evitar volver a saludar o presentarse en una conversación ya iniciada.

## Seguridad y dominios en WS

- Cada cliente tiene **un único dominio autorizado** para su Chatbot.
- WS debe solicitar, normalizar y guardar ese dominio al crear o editar un cliente.
- Si el Chatbot se carga desde otro dominio, debe bloquearse y mostrar un mensaje visual cuidado, no un error técnico.
- La License Key y API Key son datos sensibles: se muestran completas solo al crear el cliente y luego se enmascaran.
- Al crear un cliente, WS debe mantener visible el modal de resultado hasta que el operador copie la API Key y la License Key.
- El modal de alta de WS entrega únicamente API Key y License Key, en cuadros separados y con copia mediante icono. El código de inserción del **Chatbot** se genera y muestra exclusivamente en WC → Configuración.
- La License Key y API Key se introducen desde WC → Configuración; no se debe indicar al operador que edite manualmente el archivo `.env`.
- WC no debe crear ni mostrar una API temporal `wgt_...`. Debe permanecer sin API Key hasta que el operador cargue la `wak_...` entregada por WS.
- Las credenciales guardadas en WC deben ocultarse en la interfaz. Cada guardado debe dar una confirmación visual clara; para reemplazar una clave se vuelve a pegar el valor completo.
- La API Key que figura en el código de inserción del Chatbot es un identificador público por diseño. La protección real se aplica en WS mediante el dominio único autorizado, el estado del cliente, límites y la License Key validada por WC.
- Al crear un cliente, WS debe permitir descargar un archivo de texto llamado `Datos de Instalación - [Nombre del cliente].txt` con fecha, cliente, URL de WC, API Key, License Key y la instrucción para cargar las claves desde WC → Configuración.
- WC debe fallar de forma segura si no cuenta con License Key válida o WS no está disponible.

## Interfaz

- Evitar alertas y confirmaciones nativas del navegador; utilizar diálogos propios coherentes con la interfaz.
- En tablas con poco espacio, usar acciones con iconos, `title` y etiqueta accesible en vez de texto largo.
- Priorizar una interfaz sobria, moderna, clara y consistente; no sacrificar acciones importantes por falta de espacio.
- La sección de acciones rápidas del dashboard de WC (Conversaciones y Conocimiento) no debe existir.
- **Regla de trabajo:** nunca afirmar que una función está operativa sin evidencia verificable. Si no se pudo comprobar, se declara como no comprobada; si algo no se puede hacer, se informa sin fingir resultados.

## Proceso de alta de cliente

1. Crear el cliente en WS, con URL de WC, dominio único autorizado y límites.
2. Instalar y configurar WC en el hosting del cliente.
3. Cargar la Base de Conocimiento en WC.
4. Configurar apariencia e insertar el **Chatbot** en el sitio autorizado.
5. Configurar Meta/WhatsApp y ejecutar pruebas completas antes de entregar.


## Conversación y agenda

- WC marca cada resultado de disponibilidad como consulta finalizada e indica a WS que debe responder usando esas opciones, sin volver a llamar la agenda en el mismo turno.
- Al presentar alternativas, la conversación debe priorizar como máximo las dos primeras horas válidas que WC devolvió; jamás inventa horarios.

- La IA debe conocer siempre el canal exacto de la conversación (**WhatsApp**, **Chatbot web** u otro) y adaptar su conducta: en WhatsApp no debe pedir un número que Meta ya entregó; en Chatbot solo solicita contacto cuando sea indispensable para completar la gestión.
- La confirmación de una cita se interpreta por intención conversacional, no por una palabra obligatoria. Afirmaciones, aceptación implícita o la repetición de la hora propuesta pueden confirmar una reserva; una negativa o pedido de cambio nunca lo hace.
- La IA nunca puede ofrecer ni confirmar un horario que el motor determinístico de agenda no haya devuelto como disponible en la misma interacción.

- La comprensión semántica de confirmación puede enviar transitoriamente a OpenAI la última propuesta de cita y la respuesta actual; WS no almacena ese contenido y WC conserva el historial bajo control del cliente.

- La IA no debe usar la muletilla “parece que” ni variantes: comunica acciones, requisitos o límites con claridad, sin proyectar duda ni falta de control.

- Agenda: al elegir un horario propuesto para reagendar, la IA debe completar el cambio sin pasos redundantes; la reserva manual debe mostrar horarios reales y abrir el formulario al hacer clic.
- Estilo: evitar Markdown visual (en especial `**`); tono siempre cálido, amable y humano, incluyendo un saludo cuando la persona abre con uno.


## Diseño móvil

- WC se diseña y prueba primero para móvil. En pantallas pequeñas el menú lateral nunca queda fijo ni empuja el contenido: se abre como cajón superpuesto mediante un botón visible, con fondo de cierre.
- El contenido no puede producir desborde horizontal de la página. Tablas y cronogramas extensos se desplazan dentro de su propio contenedor; formularios, modales, botones y acciones deben caber y ser táctiles.
- Las acciones frecuentes deben quedar accesibles sin depender de hover, puntero o un ancho de escritorio. Los modales ocupan el ancho útil y respetan el alto visible del teléfono.

- En móvil, Conversaciones se comporta como una lista. Al elegir un chat, el detalle se abre como una superficie modal a pantalla completa, con un control visible para volver a la lista. El estado de escritorio “Seleccioná una conversación…” no debe ocupar espacio en móvil.
- Navegación móvil: botón hamburguesa arriba a la izquierda y barra inferior fija con, como mínimo, Dashboard, Chats, Prospectos y Agenda.

- Móvil: no se permite scroll horizontal de la página, Dashboard ni Prospectos. En pantallas pequeñas se muestran solo las columnas útiles; las tablas no deben obligar a desplazar lateralmente.
- Móvil: la barra inferior no puede tapar las últimas filas ni acciones de Prospectos. Toda pantalla debe dejar margen de desplazamiento final equivalente a la barra fija.
- Móvil: la lista de Conversaciones debe conservar altura útil y renderizar sus chats; si no hay registros, debe verse explícitamente el estado vacío. Nunca puede quedar un panel blanco sin contenido.
- Agenda móvil: al tocar cualquier tarjeta de cita se abre directamente la Ficha de agendamiento como modal. Debe mostrar cliente, contacto, horario, servicio/agenda y contexto; las acciones son Reagendar y Cancelar (Confirmar solo si la cita aún está pendiente).

- Identidad transversal: todo WC relaciona prospectos, conversaciones y citas exclusivamente por teléfono o correo normalizados. El nombre es informativo y nunca se usa para fusionar personas. Al coincidir un teléfono/correo, Agenda enlaza el prospecto existente y Conversaciones muestra esa identidad automáticamente.


## Identidad transversal por contacto — 15/07/2026
- WC trata como la misma persona los teléfonos paraguayos `+595981966664`, `0981966664`, `981966664` y la forma abreviada `81966664`: la identidad interna se normaliza a `595981966664`.
- La unión entre Agenda, Prospectos y Chatbot se hace solamente por teléfono o correo electrónico; nunca por nombre.
- Conversaciones ya existentes con teléfono/correo se reconcilian al cargar la lista, sin analizar el texto de los mensajes.
- El Chatbot no accede a contactos ni a datos del navegador: un teléfono solo llega desde el texto enviado por la persona o por una integración explícita del sitio.


## Respaldo Agenda → Conversaciones — 15/07/2026
- Si un Chatbot tiene teléfono o correo y aún no posee una ficha vinculada, WC consulta las citas activas del cliente usando las variantes normalizadas del contacto.
- Al encontrar una cita, crea/reutiliza la ficha local, enlaza el chat y muestra el nombre de la reserva. No lee textos de mensajes para lograrlo.


## Corrección de ficha anónima ya existente — 16/07/2026
- Si el teléfono/correo ya estaba vinculado a un prospecto sin nombre, WC no se detiene allí: consulta la cita coincidente y completa el nombre en Prospectos y Conversaciones.


## Resolución directa y pruebas de identidad — 16/07/2026
- Conversaciones calcula el nombre visible directamente desde la cita activa por teléfono/correo canónico; no depende de que una actualización previa de la ficha haya funcionado.
- Se eliminó la dependencia de `str_starts_with` para mantener compatibilidad con versiones anteriores de PHP usadas en algunos hostings.
- Se probaron: sintaxis PHP de ambos archivos, cinco formatos del mismo teléfono, prospecto existente sin nombre, prevención de fusiones falsas, conservación de nombres verificados, coincidencia por correo y exclusión de citas canceladas.


## Ruta real Chatbot → Prospecto → Cita — 16/07/2026
- El diagnóstico de producción demostró que `widget_chats` no tenía teléfonos ni correos: el contacto visible provenía de la ficha enlazada mediante `prospecto_referencias`.
- Conversaciones resuelve ahora la identidad por la ruta real: chat de Chatbot → prospecto vinculado → teléfono/correo canónico → cita activa → nombre del cliente.
- Si el prospecto vinculado ya tiene un nombre válido, se conserva. Si está anónimo y existe una cita activa coincidente, se actualizan la ficha de Prospectos y el nombre visible del chat.
- El diagnóstico administrativo revisa todos los chats y muestra su prospecto enlazado, aunque `widget_chats` no contenga contacto.
- Verificación ejecutada: sintaxis PHP y escenario crítico chat sin contacto directo → prospecto anónimo vinculado → cita coincidente → nombre `Jorge`.
# Correcciones 16/07/2026 · Chatbot y agenda reservable

- El Chatbot no debe producir desplazamiento horizontal bajo ninguna resolución. Su host, panel, mensajes, burbujas y caja de escritura quedan contenidos al ancho visible.
- El Chatbot no muestra frases sugeridas ni botones de respuestas rápidas: la persona escribe libremente.
- La configuración del Chatbot permite definir el nombre visible del asistente y subir una foto JPG, PNG o WebP. La foto se utiliza en cabecera, mensajes y animación de escritura.
- La estructura canónica de reservas es Negocio → Sucursal → Agenda → Servicios.
- Una Agenda representa cualquier recurso reservable: persona, médico, peluquero, sala, box, cancha, vehículo, estudio, máquina o asesor.
- Cada Agenda administra nombre, sucursal, color, duración por defecto, buffer anterior, buffer posterior, horarios, excepciones, servicios y estado.
- Los servicios pertenecen a una Agenda, no directamente a la sucursal.
- Los horarios pertenecen a la Agenda y se aplican a todos sus servicios. Nunca deben duplicarse por servicio.
- La IA debe resolver agenda y servicio como una pareja coherente. Si el servicio identifica su agenda, no puede combinarlo con otra agenda.
- Prueba obligatoria de regresión: pedir una cita con David, elegir su servicio, pedir el lunes, recibir horarios reales y completar la reserva sin mensajes genéricos de error.
# Agenda: consistencia e idempotencia

- Una cita existente determina su agenda y su servicio. Para consultar alternativas o reagendar, WC no debe volver a pedir esos datos ni depender de que WS repita sus identificadores.
- Una reserva repetida con el mismo contacto, agenda, servicio, fecha y hora es idempotente: devuelve la cita ya registrada en vez de informar falsamente que el horario dejó de estar disponible.
- Los teléfonos paraguayos se localizan por sus últimos ocho dígitos para reconocer variantes como `+595981966664`, `0981966664` y `81966664`.
- En una conversación se ofrecen como máximo dos alternativas por fecha. La agenda manual conserva la lista completa.
- No usar respuestas prefabricadas repetitivas como “Lamentablemente, no tengo disponibilidad”. La respuesta debe describir naturalmente la búsqueda real y, ante una duda del visitante, volver a consultar antes de repetirla.
- Las fechas relativas deben concordar con `America/Asuncion`: no llamar “mañana” a la fecha actual ni inventar fechas.
