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
- El instalador conecta a una base de datos que el equipo crea previamente. Al finalizar el alta inicial, `setup.php`, `setup_admin.php` e `init.sql` se eliminan automáticamente.

## Seguridad y dominios en WS

- Cada cliente tiene **un único dominio autorizado** para su Chatbot.
- WS debe solicitar, normalizar y guardar ese dominio al crear o editar un cliente.
- Si el Chatbot se carga desde otro dominio, debe bloquearse y mostrar un mensaje visual cuidado, no un error técnico.
- La License Key y API Key son datos sensibles: se muestran completas solo al crear el cliente y luego se enmascaran.
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
