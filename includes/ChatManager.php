<?php

require_once __DIR__ . '/Database.php';

class ChatManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getOrCreateConversacion(string $waPhone, string $waName = '', ?int $clienteId = null): int
    {
        $stmt = $this->db->prepare("SELECT id FROM conversaciones WHERE wa_phone = ?");
        $stmt->execute([$waPhone]);
        $row = $stmt->fetch();

        if ($row) {
            if ($clienteId !== null) {
                $stmt = $this->db->prepare("UPDATE conversaciones SET cliente_id = COALESCE(cliente_id, ?) WHERE id = ?");
                $stmt->execute([$clienteId, (int)$row['id']]);
            }
            return (int)$row['id'];
        }

        $stmt = $this->db->prepare("INSERT INTO conversaciones (wa_phone, wa_name, cliente_id) VALUES (?, ?, ?)");
        $stmt->execute([$waPhone, $waName, $clienteId]);
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

    public function getConversaciones(string $search = '', string $estadoFiltro = '', ?int $usuarioId = null, bool $esAdmin = false, ?int $clienteId = null): array
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

        if ($clienteId !== null) {
            $sql .= " AND c.cliente_id = ?";
            $params[] = $clienteId;
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
        return $stmt->fetchAll();
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

    public function markProcessed(string $waMessageId): void
    {
        $stmt = $this->db->prepare("INSERT IGNORE INTO processed_ids (wa_message_id) VALUES (?)");
        $stmt->execute([$waMessageId]);
    }

    public function getHistorial(int $conversacionId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT contenido, direccion FROM mensajes WHERE conversacion_id = ? ORDER BY created_at DESC LIMIT ?"
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
