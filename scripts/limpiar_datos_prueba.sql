-- Wabot: limpieza manual de datos de prueba.
--
-- Este archivo SOLO borra filas de interacción comercial. No altera tablas,
-- columnas, índices, usuarios, configuración, Base de Conocimiento,
-- sucursales, agendas, servicios, horarios, bloqueos ni citas.
--
-- No se ejecuta automáticamente con Git ni con un redeploy.
-- Ejecutar únicamente cuando el operador decida reiniciar las pruebas.
--
-- No contiene DROP, TRUNCATE, CREATE ni ALTER.

START TRANSACTION;

-- Métricas e historial de WhatsApp.
DELETE FROM metricas;
DELETE FROM mensajes;
DELETE FROM conversaciones;

-- Historial del Chatbot web.
DELETE FROM widget_messages;
DELETE FROM widget_chats;

-- Perfiles comerciales y sus referencias a los canales.
DELETE FROM prospecto_referencias;
DELETE FROM prospectos;

-- Evita que IDs de webhooks anteriores afecten nuevas pruebas.
DELETE FROM processed_ids;

COMMIT;
