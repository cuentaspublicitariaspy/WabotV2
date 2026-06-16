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
        if ($usuarioId !== null) {
            $total = $this->db->prepare("SELECT COUNT(*) FROM metricas WHERE respondido_por = ? OR respondido_por_ia = 1");
            $total->execute([$usuarioId]);
            $promedio = $this->db->prepare("SELECT AVG(tiempo_respuesta_seg) FROM metricas WHERE tiempo_respuesta_seg IS NOT NULL AND respondido_por = ?");
            $promedio->execute([$usuarioId]);
            $maximo = $this->db->prepare("SELECT MAX(tiempo_respuesta_seg) FROM metricas WHERE tiempo_respuesta_seg IS NOT NULL AND respondido_por = ?");
            $maximo->execute([$usuarioId]);
            $minimo = $this->db->prepare("SELECT MIN(tiempo_respuesta_seg) FROM metricas WHERE tiempo_respuesta_seg IS NOT NULL AND respondido_por = ?");
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
                "SELECT HOUR(created_at) as hora, COUNT(*) as total
                 FROM metricas
                 WHERE respondido_por = ?
                 GROUP BY HOUR(created_at)
                 ORDER BY hora"
            );
            $porHora->execute([$usuarioId]);

            $iaTotal = $this->db->query("SELECT COUNT(*) FROM metricas WHERE respondido_por_ia = 1")->fetchColumn();
            $humanoCount = $this->db->prepare("SELECT COUNT(*) FROM metricas WHERE respondido_por = ? AND respondido_por_ia = 0");
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

            return [
                'total' => (int)$total->fetchColumn(),
                'promedio_seg' => ($p = $promedio->fetchColumn()) ? round((float)$p) : 0,
                'maximo_seg' => (int)($maximo->fetchColumn() ?? 0),
                'minimo_seg' => (int)($minimo->fetchColumn() ?? 0),
                'por_agente' => $porAgente->fetchAll(),
                'por_hora' => $porHora->fetchAll(),
                'ia_count' => (int)$iaTotal,
                'humano_count' => (int)($humanoCount->fetchColumn() ?? 0),
                'por_departamento' => $porDepartamento->fetchAll(),
            ];
        }

        $total = $this->db->query("SELECT COUNT(*) FROM metricas")->fetchColumn();
        $promedio = $this->db->query("SELECT AVG(tiempo_respuesta_seg) FROM metricas WHERE tiempo_respuesta_seg IS NOT NULL")->fetchColumn();
        $maximo = $this->db->query("SELECT MAX(tiempo_respuesta_seg) FROM metricas WHERE tiempo_respuesta_seg IS NOT NULL")->fetchColumn();
        $minimo = $this->db->query("SELECT MIN(tiempo_respuesta_seg) FROM metricas WHERE tiempo_respuesta_seg IS NOT NULL")->fetchColumn();

        $porAgente = $this->db->query(
            "SELECT u.nombre, COUNT(*) as total, AVG(m.tiempo_respuesta_seg) as promedio
             FROM metricas m
             JOIN usuarios u ON m.respondido_por = u.id
             WHERE m.respondido_por IS NOT NULL AND m.respondido_por_ia = 0
             GROUP BY u.id, u.nombre
             ORDER BY total DESC"
        )->fetchAll();

        $porHora = $this->db->query(
            "SELECT HOUR(created_at) as hora, COUNT(*) as total
             FROM metricas
             GROUP BY HOUR(created_at)
             ORDER BY hora"
        )->fetchAll();

        $iaCount = $this->db->query("SELECT COUNT(*) FROM metricas WHERE respondido_por_ia = 1")->fetchColumn();
        $humanoCount = $this->db->query("SELECT COUNT(*) FROM metricas WHERE respondido_por_ia = 0 AND respondido_por IS NOT NULL")->fetchColumn();

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
        ];
    }
}
