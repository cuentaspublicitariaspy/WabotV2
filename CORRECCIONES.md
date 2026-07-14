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

## Proceso de alta de cliente

1. Crear el cliente en WS, con URL de WC, dominio único autorizado y límites.
2. Instalar y configurar WC en el hosting del cliente.
3. Cargar la Base de Conocimiento en WC.
4. Configurar apariencia e insertar el **Chatbot** en el sitio autorizado.
5. Configurar Meta/WhatsApp y ejecutar pruebas completas antes de entregar.
