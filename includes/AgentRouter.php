<?php

require_once __DIR__ . '/Database.php';

class AgentRouter
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function marcarActivo(int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO agentes_sesion (usuario_id, activo, ultimo_ping)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE activo = 1, ultimo_ping = NOW()"
        );
        $stmt->execute([$usuarioId]);
    }

    public function marcarInactivo(int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO agentes_sesion (usuario_id, activo, ultimo_ping)
             VALUES (?, 0, NOW())
             ON DUPLICATE KEY UPDATE activo = 0, ultimo_ping = NOW()"
        );
        $stmt->execute([$usuarioId]);

        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_logout = NOW() WHERE id = ?");
        $stmt->execute([$usuarioId]);

        $stmt = $this->db->prepare(
            "UPDATE conversaciones SET asignado_a = NULL WHERE asignado_a = ?"
        );
        $stmt->execute([$usuarioId]);
    }

    public function marcarAusente(int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE conversaciones SET asignado_a = NULL WHERE asignado_a = ?"
        );
        $stmt->execute([$usuarioId]);
    }

    public function estaActivo(int $usuarioId): bool
    {
        $this->limpiarSesionesExpiradas();
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM agentes_sesion WHERE usuario_id = ? AND activo = 1");
        $stmt->execute([$usuarioId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function ping(int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO agentes_sesion (usuario_id, activo, ultimo_ping)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE ultimo_ping = NOW(), activo = 1"
        );
        $stmt->execute([$usuarioId]);
    }

    public function limpiarSesionesExpiradas(int $timeoutSegundos = 120): void
    {
        $stmt = $this->db->prepare(
            "UPDATE agentes_sesion SET activo = 0
             WHERE activo = 1 AND ultimo_ping < DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$timeoutSegundos]);
    }

    public function hayAgentesDisponibles(): bool
    {
        $this->limpiarSesionesExpiradas();
        $stmt = $this->db->query("SELECT COUNT(*) FROM agentes_sesion WHERE activo = 1");
        return (int)$stmt->fetchColumn() > 0;
    }

    public function getAgentesActivos(): array
    {
        $this->limpiarSesionesExpiradas();
        $stmt = $this->db->query(
            "SELECT u.id, u.nombre, u.rol, a.ultimo_ping
             FROM agentes_sesion a
             JOIN usuarios u ON a.usuario_id = u.id
             WHERE a.activo = 1
             ORDER BY u.nombre"
        );
        return $stmt->fetchAll();
    }

    public function asignarConversacion(int $conversacionId, int $usuarioId): void
    {
        $stmt = $this->db->prepare("UPDATE conversaciones SET asignado_a = ? WHERE id = ?");
        $stmt->execute([$usuarioId, $conversacionId]);
    }

    public function getAgenteParaAsignar(): ?int
    {
        $this->limpiarSesionesExpiradas();
        $stmt = $this->db->query(
            "SELECT a.usuario_id,
                   (SELECT COUNT(*) FROM conversaciones c WHERE c.asignado_a = a.usuario_id AND c.estado = 'pendiente') as carga
             FROM agentes_sesion a
             WHERE a.activo = 1
             ORDER BY carga ASC
             LIMIT 1"
        );
        $row = $stmt->fetch();
        return $row ? (int)$row['usuario_id'] : null;
    }
}
