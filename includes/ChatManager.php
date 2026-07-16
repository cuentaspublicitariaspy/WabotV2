<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ProspectManager.php';

class ChatManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getOrCreateConversacion(string $waPhone, string $waName = ''): int
    {
        $stmt = $this->db->prepare("SELECT id FROM conversaciones WHERE wa_phone = ?");
        $stmt->execute([$waPhone]);
        $row = $stmt->fetch();

        if ($row) {
            return (int)$row['id'];
        }

        $stmt = $this->db->prepare("INSERT INTO conversaciones (wa_phone, wa_name) VALUES (?, ?)");
        $stmt->execute([$waPhone, $waName]);
        return (int)$this->db->lastInsertId();
    }

    public function guardarMensaje(int $conversacionId, string $contenido, string $direccion, ?string $waMessageId = null, ?int $respondidoPor = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO mensajes (conversacion_id, wa_message_id, contenido, direccion, respondido_por) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$conversacionId, $waMessageId, $contenido, $direccion, $respondidoPor]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizarConversacion(int $conversacionId, string $ultimoMensaje, string $estado = 'pendiente'): void
    {
        $stmt = $this->db->prepare("UPDATE conversaciones SET ultimo_mensaje = ?, ultimo_tiempo = NOW(), estado = ? WHERE id = ?");
        $stmt->execute([$ultimoMensaje, $estado, $conversacionId]);
    }

    public function marcarLeido(int $conversacionId, int $usuarioId): void
    {
        $stmt = $this->db->prepare("UPDATE conversaciones SET leido_por = ?, leido_en = NOW(), estado = 'respondido' WHERE id = ?");
        $stmt->execute([$usuarioId, $conversacionId]);
    }

    public function getConversaciones(string $search = '', string $estadoFiltro = '', ?int $usuarioId = null, bool $esAdmin = false): array
    {
        $sql = "SELECT c.*, u.nombre as leido_por_nombre, a.nombre as asignado_a_nombre
                FROM conversaciones c
                LEFT JOIN usuarios u ON c.leido_por = u.id
                LEFT JOIN usuarios a ON c.asignado_a = a.id
                WHERE 1=1";
        $params = [];

        if (!$esAdmin && $usuarioId !== null) {
            $sql .= " AND (c.asignado_a = ? OR c.asignado_a IS NULL)";
            $params[] = $usuarioId;
        }

        if ($search !== '') {
            $sql .= " AND (c.wa_name LIKE ? OR c.wa_phone LIKE ? OR c.ultimo_mensaje LIKE ?)";
            $param = "%$search%";
            $params[] = $param;
            $params[] = $param;
            $params[] = $param;
        }

        if ($estadoFiltro !== '') {
            $sql .= " AND c.estado = ?";
            $params[] = $estadoFiltro;
        }

        $sql .= " ORDER BY c.ultimo_tiempo DESC, c.created_at DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $conversaciones = $stmt->fetchAll();
        foreach ($conversaciones as &$conversacion) {
            $conversacion['canal'] = 'whatsapp';
        }
        unset($conversacion);
        return $conversaciones;
    }

    /** Conversaciones iniciadas desde el Chatbot instalado en el sitio. */
    public function getWidgetConversaciones(string $search = '', string $estadoFiltro = ''): array
    {
        $this->reconciliarIdentidadesChatbot();
        $sql = "SELECT wc.*,\n"
            . " COALESCE((SELECT wm.content FROM widget_messages wm WHERE wm.chat_id = wc.id ORDER BY wm.id DESC LIMIT 1), '') AS ultimo_mensaje,\n"
            . " CASE WHEN wc.unread = 1 THEN 'pendiente' ELSE 'respondido' END AS estado\n"
            . " FROM widget_chats wc WHERE 1=1";
        $params = [];
        if ($search !== '') {
            $sql .= " AND (wc.visitor_name LIKE ? OR wc.visitor_email LIKE ? OR wc.visitor_phone LIKE ? OR EXISTS (SELECT 1 FROM widget_messages wm WHERE wm.chat_id = wc.id AND wm.content LIKE ?))";
            $term = '%' . $search . '%';
            $params = [$term, $term, $term, $term];
        }
        if ($estadoFiltro !== '') {
            $sql .= $estadoFiltro === 'pendiente' ? ' AND wc.unread = 1' : ' AND wc.unread = 0';
        }
        $sql .= ' ORDER BY wc.updated_at DESC LIMIT 100';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $chats = $stmt->fetchAll();
        foreach ($chats as &$chat) {
            $chat['canal'] = 'chatbot';
            $chat['wa_name'] = $chat['visitor_name'] ?: 'Visitante web';
            $chat['wa_phone'] = $chat['visitor_phone'] ?: ($chat['visitor_email'] ?: 'Chatbot');
            $chat['ultimo_tiempo'] = $chat['updated_at'];
            $chat['departamento'] = null;
            $chat['asignado_a'] = null;
            $chat['asignado_a_nombre'] = null;
        }
        unset($chat);
        return $chats;
    }

    public function getWidgetConversacion(int $chatId): ?array
    {
        $this->reconciliarIdentidadesChatbot($chatId);
        $stmt = $this->db->prepare('SELECT * FROM widget_chats WHERE id = ?');
        $stmt->execute([$chatId]);
        $chat = $stmt->fetch();
        if (!$chat) return null;
        $chat['canal'] = 'chatbot';
        $chat['wa_name'] = $chat['visitor_name'] ?: 'Visitante web';
        $chat['wa_phone'] = $chat['visitor_phone'] ?: ($chat['visitor_email'] ?: 'Chatbot');
        return $chat;
    }

    public function getWidgetMensajes(int $chatId): array
    {
        $stmt = $this->db->prepare('SELECT id, role, content AS contenido, created_at FROM widget_messages WHERE chat_id = ? ORDER BY id ASC');
        $stmt->execute([$chatId]);
        $messages = $stmt->fetchAll();
        foreach ($messages as &$message) {
            $message['direccion'] = $message['role'] === 'visitor' ? 'in' : 'out';
            $message['respondido_por_nombre'] = $message['role'] === 'assistant' ? 'IA' : null;
        }
        unset($message);
        $this->db->prepare('UPDATE widget_chats SET unread = 0 WHERE id = ?')->execute([$chatId]);
        return $messages;
    }

    /**
     * Repara datos de instalaciones anteriores: recorre únicamente mensajes
     * escritos por el visitante y aplica nombres explícitos a la conversación
     * y a su ficha. Nunca lee respuestas de IA como fuente de identidad.
     */
    /**
     * Vincula chats ya existentes por teléfono/correo con su ficha local.
     * No examina texto de mensajes ni usa el nombre como criterio de unión.
     */
    /**
     * Vincula chats ya existentes por teléfono/correo con Prospectos y Citas.
     * No examina texto de mensajes ni usa el nombre como criterio de unión.
     */
    private function reconciliarIdentidadesChatbot(?int $soloChatId = null): void
    {
        try {
            $sql = "SELECT id, visitor_name, visitor_email, visitor_phone FROM widget_chats WHERE (visitor_phone <> '' OR visitor_email <> '')";
            $params = [];
            if ($soloChatId !== null) { $sql .= ' AND id = ?'; $params[] = $soloChatId; }
            $sql .= ' ORDER BY id DESC LIMIT 200';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $chats = $stmt->fetchAll();
            if (!$chats) return;

            $prospectos = new ProspectManager();
            $actualizarChat = $this->db->prepare("UPDATE widget_chats SET visitor_name = ? WHERE id = ?");
            foreach ($chats as $chat) {
                $datos = [
                    'email' => (string)$chat['visitor_email'],
                    'whatsapp' => (string)$chat['visitor_phone'],
                ];
                $prospectoId = $prospectos->resolverIdentidad($datos, false);
                $prospecto = $prospectoId ? $prospectos->obtener($prospectoId) : null;

                // Una cita existente es fuente local y determinística de
                // identidad. Sirve de respaldo para instalaciones previas que
                // aún no tenían prospecto_id enlazado.
                if (!$prospecto && ($datos['whatsapp'] !== '' || $datos['email'] !== '')) {
                    $where = []; $citaParams = [];
                    $variantes = $prospectos->variantesTelefono($datos['whatsapp']);
                    if ($variantes) {
                        $where[] = 'telefono IN (' . implode(',', array_fill(0, count($variantes), '?')) . ')';
                        array_push($citaParams, ...$variantes);
                    }
                    if ($datos['email'] !== '') {
                        $where[] = 'LOWER(email) = ?';
                        $citaParams[] = strtolower($datos['email']);
                    }
                    if ($where) {
                        $cita = $this->db->prepare('SELECT nombre_cliente, telefono, email FROM citas WHERE (' . implode(' OR ', $where) . ") AND estado NOT IN ('cancelada_cliente','cancelada_negocio') ORDER BY updated_at DESC, id DESC LIMIT 1");
                        $cita->execute($citaParams);
                        $encontrada = $cita->fetch();
                        if ($encontrada) {
                            $prospectoId = $prospectos->resolverIdentidad([
                                'nombre' => (string)$encontrada['nombre_cliente'],
                                'email' => (string)($datos['email'] ?: $encontrada['email']),
                                'whatsapp' => (string)($datos['whatsapp'] ?: $encontrada['telefono']),
                            ]);
                            $prospecto = $prospectos->obtener($prospectoId);
                        }
                    }
                }

                if (!$prospecto) continue;
                $prospectos->vincular('chatbot', (string)$chat['id'], $datos);

                $nombreActual = mb_strtolower(trim((string)$chat['visitor_name']), 'UTF-8');
                $sinNombre = $nombreActual === '' || in_array($nombreActual, ['visitante web', 'sin nombre', 'unknown'], true);
                $nombre = trim((string)($prospecto['nombre'] ?? ''));
                if ($sinNombre && $nombre !== '') {
                    $actualizarChat->execute([$nombre, (int)$chat['id']]);
                }
            }
        } catch (Throwable $e) {
            error_log('Wabot chatbot identity reconciliation failed: ' . $e->getMessage());
        }
    }

    public function getMensajes(int $conversacionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.*,
                    CASE
                        WHEN m.respondido_por IS NOT NULL THEN u.nombre
                        WHEN m.direccion = 'out' THEN 'IA'
                        ELSE NULL
                    END as respondido_por_nombre
             FROM mensajes m
             LEFT JOIN usuarios u ON m.respondido_por = u.id
             WHERE m.conversacion_id = ?
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$conversacionId]);
        return $stmt->fetchAll();
    }

    public function getConversacion(int $conversacionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, u.nombre as leido_por_nombre, a.nombre as asignado_a_nombre
             FROM conversaciones c
             LEFT JOIN usuarios u ON c.leido_por = u.id
             LEFT JOIN usuarios a ON c.asignado_a = a.id
             WHERE c.id = ?"
        );
        $stmt->execute([$conversacionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isProcessed(string $waMessageId): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM processed_ids WHERE wa_message_id = ?");
        $stmt->execute([$waMessageId]);
        return $stmt->fetch() !== false;
    }

    /**
     * Reserva un mensaje entrante una sola vez. INSERT IGNORE usa el índice
     * UNIQUE de processed_ids, por lo que protege también ante webhooks
     * simultáneos o reintentos de Meta.
     */
    public function claimIncomingMessage(string $waMessageId): bool
    {
        $stmt = $this->db->prepare("INSERT IGNORE INTO processed_ids (wa_message_id) VALUES (?)");
        $stmt->execute([$waMessageId]);
        return $stmt->rowCount() === 1;
    }

    public function getHistorial(int $conversacionId, int $limit = 60): array
    {
        $stmt = $this->db->prepare(
            // created_at tiene precisión de segundos: varios mensajes de una
            // conversación pueden compartirlo. El id conserva el orden real.
            "SELECT contenido, direccion FROM mensajes WHERE conversacion_id = ? ORDER BY id DESC LIMIT ?"
        );
        $stmt->execute([$conversacionId, $limit]);
        $rows = $stmt->fetchAll();
        $rows = array_reverse($rows);

        $historial = [];
        foreach ($rows as $row) {
            $historial[] = [
                'role' => $row['direccion'] === 'in' ? 'user' : 'assistant',
                'content' => $row['contenido'],
            ];
        }
        return $historial;
    }

    public function resetConversacion(int $conversacionId): void
    {
        $stmt = $this->db->prepare("DELETE FROM mensajes WHERE conversacion_id = ?");
        $stmt->execute([$conversacionId]);

        $stmt = $this->db->prepare("UPDATE conversaciones SET ultimo_mensaje = '', ultimo_tiempo = NOW(), estado = 'respondido' WHERE id = ?");
        $stmt->execute([$conversacionId]);
    }

    public function getUltimoMensajeInId(int $conversacionId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM mensajes WHERE conversacion_id = ? AND direccion = 'in' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$conversacionId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    public function getUltimoMensajeInTime(int $conversacionId): ?string
    {
        $stmt = $this->db->prepare("SELECT created_at FROM mensajes WHERE conversacion_id = ? AND direccion = 'in' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$conversacionId]);
        $row = $stmt->fetch();
        return $row ? $row['created_at'] : null;
    }
}
