<?php

require_once __DIR__ . '/Database.php';

class MetricsCollector
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function registrarRespuesta(int $conversacionId, int $mensajeInId, int $mensajeOutId, ?int $respondidoPor = null, bool $respondidoPorIa = false): void
    {
        $stmt = $this->db->prepare(
            "SELECT TIMESTAMPDIFF(SECOND,
                (SELECT created_at FROM mensajes WHERE id = ?),
                (SELECT created_at FROM mensajes WHERE id = ?)
            ) as diff"
        );
        $stmt->execute([$mensajeInId, $mensajeOutId]);
        $diff = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare(
            "INSERT INTO metricas (conversacion_id, mensaje_in_id, mensaje_out_id, tiempo_respuesta_seg, respondido_por, respondido_por_ia)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$conversacionId, $mensajeInId, $mensajeOutId, $diff, $respondidoPor, $respondidoPorIa ? 1 : 0]);
    }

    public function getMetricasGlobales(?int $usuarioId = null): array
    {
        $clienteJoin = "";
        $clienteJoinFull = "";

        // Tasa de respuesta: mensajes entrantes que tienen una respuesta
        // registrada, sobre el total de mensajes recibidos por WhatsApp.
        $entrantes = (int)$this->db->query("SELECT COUNT(*) FROM mensajes WHERE direccion = 'in'")->fetchColumn();

        if ($usuarioId !== null) {
            $total = $this->db->prepare("SELECT COUNT(*) FROM metricas m$clienteJoin WHERE (m.respondido_por = ? OR m.respondido_por_ia = 1)");
            $total->execute([$usuarioId]);
            $promedio = $this->db->prepare("SELECT AVG(m.tiempo_respuesta_seg) FROM metricas m$clienteJoin WHERE m.tiempo_respuesta_seg IS NOT NULL AND m.respondido_por = ?");
            $promedio->execute([$usuarioId]);
            $maximo = $this->db->prepare("SELECT MAX(m.tiempo_respuesta_seg) FROM metricas m$clienteJoin WHERE m.tiempo_respuesta_seg IS NOT NULL AND m.respondido_por = ?");
            $maximo->execute([$usuarioId]);
            $minimo = $this->db->prepare("SELECT MIN(m.tiempo_respuesta_seg) FROM metricas m$clienteJoin WHERE m.tiempo_respuesta_seg IS NOT NULL AND m.respondido_por = ?");
            $minimo->execute([$usuarioId]);

            $porAgente = $this->db->prepare(
                "SELECT u.nombre, COUNT(*) as total, AVG(m.tiempo_respuesta_seg) as promedio
                 FROM metricas m
                 JOIN usuarios u ON m.respondido_por = u.id
                 WHERE m.respondido_por = ? AND m.respondido_por_ia = 0
                 GROUP BY u.id, u.nombre"
            );
            $porAgente->execute([$usuarioId]);

            $porHora = $this->db->prepare(
                "SELECT HOUR(m.created_at) as hora, COUNT(*) as total
                 FROM metricas m$clienteJoin
                 WHERE m.respondido_por = ?
                 GROUP BY HOUR(m.created_at)
                 ORDER BY hora"
            );
            $porHora->execute([$usuarioId]);

            $iaTotal = $this->db->query("SELECT COUNT(*) FROM metricas WHERE respondido_por_ia = 1")->fetchColumn();
            $humanoCount = $this->db->prepare("SELECT COUNT(*) FROM metricas m$clienteJoin WHERE m.respondido_por = ? AND m.respondido_por_ia = 0");
            $humanoCount->execute([$usuarioId]);

            $porDepartamento = $this->db->prepare(
                "SELECT COALESCE(c.departamento, 'general') as departamento, COUNT(*) as total
                 FROM metricas m
                 JOIN conversaciones c ON m.conversacion_id = c.id
                 WHERE m.respondido_por = ?
                 GROUP BY c.departamento
                 ORDER BY total DESC"
            );
            $porDepartamento->execute([$usuarioId]);

            $totalValue = (int)$total->fetchColumn();

            return [
                'total' => $totalValue,
                'promedio_seg' => ($p = $promedio->fetchColumn()) ? round((float)$p) : 0,
                'maximo_seg' => (int)($maximo->fetchColumn() ?? 0),
                'minimo_seg' => (int)($minimo->fetchColumn() ?? 0),
                'por_agente' => $porAgente->fetchAll(),
                'por_hora' => $porHora->fetchAll(),
                'ia_count' => (int)$iaTotal,
                'humano_count' => (int)($humanoCount->fetchColumn() ?? 0),
                'por_departamento' => $porDepartamento->fetchAll(),
                'tasa_respuesta' => $entrantes > 0 ? min(100, round(($totalValue / $entrantes) * 100)) : 0,
            ];
        }

        $total = $this->db->query("SELECT COUNT(*) FROM metricas m$clienteJoinFull")->fetchColumn();
        $promedio = $this->db->query("SELECT AVG(m.tiempo_respuesta_seg) FROM metricas m$clienteJoinFull WHERE m.tiempo_respuesta_seg IS NOT NULL")->fetchColumn();
        $maximo = $this->db->query("SELECT MAX(m.tiempo_respuesta_seg) FROM metricas m$clienteJoinFull WHERE m.tiempo_respuesta_seg IS NOT NULL")->fetchColumn();
        $minimo = $this->db->query("SELECT MIN(m.tiempo_respuesta_seg) FROM metricas m$clienteJoinFull WHERE m.tiempo_respuesta_seg IS NOT NULL")->fetchColumn();

        $porAgente = $this->db->query(
            "SELECT u.nombre, COUNT(*) as total, AVG(m.tiempo_respuesta_seg) as promedio
             FROM metricas m$clienteJoinFull
             JOIN usuarios u ON m.respondido_por = u.id
             WHERE m.respondido_por IS NOT NULL AND m.respondido_por_ia = 0
             GROUP BY u.id, u.nombre
             ORDER BY total DESC"
        )->fetchAll();

        $porHora = $this->db->query(
            "SELECT HOUR(m.created_at) as hora, COUNT(*) as total
             FROM metricas m$clienteJoinFull
             GROUP BY HOUR(m.created_at)
             ORDER BY hora"
        )->fetchAll();

        $iaCount = $this->db->query("SELECT COUNT(*) FROM metricas m$clienteJoinFull WHERE m.respondido_por_ia = 1")->fetchColumn();
        $humanoCount = $this->db->query("SELECT COUNT(*) FROM metricas m$clienteJoinFull WHERE m.respondido_por_ia = 0 AND m.respondido_por IS NOT NULL")->fetchColumn();

        $porDepartamento = $this->db->query(
            "SELECT COALESCE(c.departamento, 'general') as departamento, COUNT(*) as total
             FROM metricas m
             JOIN conversaciones c ON m.conversacion_id = c.id
             GROUP BY c.departamento
             ORDER BY total DESC"
        )->fetchAll();

        return [
            'total' => (int)$total,
            'promedio_seg' => $promedio ? round((float)$promedio) : 0,
            'maximo_seg' => (int)($maximo ?? 0),
            'minimo_seg' => (int)($minimo ?? 0),
            'por_agente' => $porAgente,
            'por_hora' => $porHora,
            'ia_count' => (int)$iaCount,
            'humano_count' => (int)$humanoCount,
            'por_departamento' => $porDepartamento,
            'tasa_respuesta' => $entrantes > 0 ? min(100, round(((int)$total / $entrantes) * 100)) : 0,
        ];
    }
}
