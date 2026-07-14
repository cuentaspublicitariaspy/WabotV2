<?php

require_once __DIR__ . '/Database.php';

/** Perfil comercial local de una persona, independiente del canal de origen. */
class ProspectManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->db->exec("CREATE TABLE IF NOT EXISTS prospectos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            whatsapp VARCHAR(40) NOT NULL DEFAULT '',
            direccion VARCHAR(255) NOT NULL DEFAULT '',
            ciudad VARCHAR(100) NOT NULL DEFAULT '',
            pais VARCHAR(100) NOT NULL DEFAULT '',
            sitio_web VARCHAR(255) NOT NULL DEFAULT '',
            ocupacion VARCHAR(150) NOT NULL DEFAULT '',
            empresa VARCHAR(150) NOT NULL DEFAULT '',
            notas TEXT NULL,
            resumen TEXT NULL,
            intencion VARCHAR(255) NOT NULL DEFAULT '',
            nivel_interes ENUM('bajo','medio','alto') NOT NULL DEFAULT 'medio',
            temperatura ENUM('frio','tibio','caliente','muy_caliente') NOT NULL DEFAULT 'tibio',
            puntaje TINYINT UNSIGNED NOT NULL DEFAULT 0,
            analizado_en DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email), INDEX idx_whatsapp (whatsapp), INDEX idx_temperatura (temperatura)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS prospecto_referencias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prospecto_id INT NOT NULL,
            canal ENUM('whatsapp','chatbot') NOT NULL,
            referencia VARCHAR(140) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_referencia (canal, referencia),
            INDEX idx_prospecto (prospecto_id),
            FOREIGN KEY (prospecto_id) REFERENCES prospectos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function vincular(string $canal, string $referencia, array $datos = []): int
    {
        $stmt = $this->db->prepare('SELECT prospecto_id FROM prospecto_referencias WHERE canal = ? AND referencia = ?');
        $stmt->execute([$canal, $referencia]);
        $id = (int) $stmt->fetchColumn();

        $email = trim((string) ($datos['email'] ?? ''));
        $whatsapp = preg_replace('/\D+/', '', (string) ($datos['whatsapp'] ?? ''));
        if (!$id && ($email !== '' || $whatsapp !== '')) {
            $where = []; $params = [];
            if ($email !== '') { $where[] = 'email = ?'; $params[] = $email; }
            if ($whatsapp !== '') { $where[] = 'whatsapp = ?'; $params[] = $whatsapp; }
            $stmt = $this->db->prepare('SELECT id FROM prospectos WHERE ' . implode(' OR ', $where) . ' ORDER BY id LIMIT 1');
            $stmt->execute($params);
            $id = (int) $stmt->fetchColumn();
        }
        if (!$id) {
            $this->db->prepare('INSERT INTO prospectos (nombre, email, whatsapp) VALUES (?, ?, ?)')
                ->execute([trim((string) ($datos['nombre'] ?? '')), $email, $whatsapp]);
            $id = (int) $this->db->lastInsertId();
        }

        $fields = ['nombre','email','whatsapp','direccion','ciudad','pais','sitio_web','ocupacion','empresa','notas'];
        $sets = []; $values = [];
        foreach ($fields as $field) {
            $value = trim((string) ($datos[$field] ?? ''));
            if ($field === 'whatsapp') $value = preg_replace('/\D+/', '', $value);
            if ($value !== '') { $sets[] = "$field = ?"; $values[] = $value; }
        }
        if ($sets) { $values[] = $id; $this->db->prepare('UPDATE prospectos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values); }
        $this->db->prepare('INSERT IGNORE INTO prospecto_referencias (prospecto_id, canal, referencia) VALUES (?, ?, ?)')
            ->execute([$id, $canal, $referencia]);
        return $id;
    }

    public function obtenerPorReferencia(string $canal, string $referencia): ?array
    {
        $stmt = $this->db->prepare('SELECT p.* FROM prospectos p INNER JOIN prospecto_referencias r ON r.prospecto_id = p.id WHERE r.canal = ? AND r.referencia = ?');
        $stmt->execute([$canal, $referencia]);
        return $stmt->fetch() ?: null;
    }

    public function guardarAnalisis(int $id, array $analisis): void
    {
        $fields = ['nombre','email','whatsapp','direccion','ciudad','pais','sitio_web','ocupacion','empresa'];
        $sets = []; $values = [];
        foreach ($fields as $field) {
            $value = trim((string) ($analisis[$field] ?? ''));
            if ($field === 'whatsapp') $value = preg_replace('/\D+/', '', $value);
            if ($value !== '') { $sets[] = "$field = ?"; $values[] = $value; }
        }
        if ($sets) { $values[] = $id; $this->db->prepare('UPDATE prospectos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values); }
        $interes = in_array(($analisis['nivel_interes'] ?? ''), ['bajo','medio','alto'], true) ? $analisis['nivel_interes'] : 'medio';
        $temperatura = in_array(($analisis['temperatura'] ?? ''), ['frio','tibio','caliente','muy_caliente'], true) ? $analisis['temperatura'] : 'tibio';
        $puntaje = max(0, min(100, (int) ($analisis['puntaje'] ?? 0)));
        $stmt = $this->db->prepare('UPDATE prospectos SET resumen=?, intencion=?, nivel_interes=?, temperatura=?, puntaje=?, analizado_en=NOW() WHERE id=?');
        $stmt->execute([trim((string)($analisis['resumen'] ?? '')), trim((string)($analisis['intencion'] ?? '')), $interes, $temperatura, $puntaje, $id]);
    }
}
