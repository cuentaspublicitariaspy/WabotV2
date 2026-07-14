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

    public function listar(string $busqueda = '', string $temperatura = ''): array
    {
        $sql = 'SELECT p.*, GROUP_CONCAT(DISTINCT r.canal ORDER BY r.canal SEPARATOR ", ") AS canales FROM prospectos p LEFT JOIN prospecto_referencias r ON r.prospecto_id=p.id WHERE 1=1';
        $params = [];
        if ($busqueda !== '') {
            $sql .= ' AND (p.nombre LIKE ? OR p.email LIKE ? OR p.whatsapp LIKE ? OR p.empresa LIKE ? OR p.resumen LIKE ?)';
            $like = '%' . $busqueda . '%'; $params = [$like,$like,$like,$like,$like];
        }
        if (in_array($temperatura, ['frio','tibio','caliente','muy_caliente'], true)) { $sql .= ' AND p.temperatura = ?'; $params[] = $temperatura; }
        $sql .= ' GROUP BY p.id ORDER BY p.puntaje DESC, p.updated_at DESC LIMIT 500';
        $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM prospectos WHERE id = ?'); $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }

    public function actualizar(int $id, array $datos): void
    {
        $campos = ['nombre','email','whatsapp','direccion','ciudad','pais','sitio_web','ocupacion','empresa','notas','resumen','intencion','nivel_interes','temperatura','puntaje'];
        $sets=[]; $values=[];
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $datos)) continue;
            $valor = trim((string)$datos[$campo]);
            if ($campo === 'whatsapp') $valor = preg_replace('/\D+/', '', $valor);
            if ($campo === 'nivel_interes' && !in_array($valor, ['bajo','medio','alto'], true)) $valor='medio';
            if ($campo === 'temperatura' && !in_array($valor, ['frio','tibio','caliente','muy_caliente'], true)) $valor='tibio';
            if ($campo === 'puntaje') $valor=max(0,min(100,(int)$valor));
            $sets[] = "$campo = ?"; $values[] = $valor;
        }
        if ($sets) { $values[]=$id; $this->db->prepare('UPDATE prospectos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values); }
    }

    /**
     * Guarda únicamente valores realmente declarados. El modelo devuelve
     * campos vacíos para lo que desconoce: esos vacíos nunca deben borrar una
     * ficha que ya tenía información.
     */
    private function guardarDatosDeclarados(int $id, array $datos): void
    {
        $campos = ['nombre','email','whatsapp','direccion','ciudad','pais','sitio_web','ocupacion','empresa'];
        $limpios = [];
        foreach ($campos as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));
            if ($valor !== '') $limpios[$campo] = $valor;
        }
        if (!$limpios) return;

        // Si un visitante web y uno de WhatsApp declararon el mismo correo o
        // teléfono, pasan a ser un único prospecto local.
        $email = $limpios['email'] ?? '';
        $whatsapp = isset($limpios['whatsapp']) ? preg_replace('/\D+/', '', $limpios['whatsapp']) : '';
        if ($email !== '' || $whatsapp !== '') {
            $where = []; $params = [$id];
            if ($email !== '') { $where[] = 'email = ?'; $params[] = $email; }
            if ($whatsapp !== '') { $where[] = 'whatsapp = ?'; $params[] = $whatsapp; }
            $stmt = $this->db->prepare('SELECT id FROM prospectos WHERE id <> ? AND (' . implode(' OR ', $where) . ') ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute($params);
            $destino = (int) $stmt->fetchColumn();
            if ($destino > 0) {
                $this->db->prepare('UPDATE prospecto_referencias SET prospecto_id = ? WHERE prospecto_id = ?')->execute([$destino, $id]);
                $this->db->prepare('DELETE FROM prospectos WHERE id = ?')->execute([$id]);
                $id = $destino;
            }
        }
        $this->actualizar($id, $limpios);
    }

    /** Extrae datos personales declarados en un mensaje y actualiza WC. WS no conserva el contenido. */
    public function registrarDatosDeclarados(int $id, string $mensaje): array
    {
        $mensaje = trim($mensaje);
        if ($mensaje === '' || !preg_match('/(@|https?:|www\.|\+?\d[\d\s().-]{6,}|\b(mi nombre|me llamo|soy |correo|email|mail|direcci[oó]n|vivo|trabajo|me dedico|empresa|negocio|web|sitio)\b)/iu', $mensaje)) return [];
        if (!defined('LICENSE_KEY') || LICENSE_KEY === '') return [];
        $prompt = 'Extraé exclusivamente datos personales o comerciales que la persona declaró en este mensaje. Respondé SOLO JSON válido con: nombre,email,whatsapp,direccion,ciudad,pais,sitio_web,ocupacion,empresa. Para lo que no esté explícito devolvé cadena vacía. No inventes ni infieras.';
        $payload = json_encode(['action'=>'chat','license_key'=>LICENSE_KEY,'messages'=>[
            ['role'=>'system','content'=>$prompt], ['role'=>'user','content'=>$mensaje]
        ]], JSON_UNESCAPED_UNICODE);
        $ch = curl_init('https://wabot-cdn.vercel.app/api/proxy/openai');
        curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12]);
        $response = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $content = json_decode((string)$response, true)['content'] ?? '';
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim((string)$content));
        $data = json_decode($content, true);
        if ($status === 200 && is_array($data)) {
            $this->guardarDatosDeclarados($id, $data);
            return $data;
        }
        return [];
    }

    public function metricas(): array
    {
        $row = $this->db->query("SELECT COUNT(*) total, SUM(temperatura IN ('caliente','muy_caliente')) calientes, SUM(temperatura='muy_caliente') muy_calientes, ROUND(AVG(puntaje)) puntaje_promedio FROM prospectos")->fetch() ?: [];
        return ['total'=>(int)($row['total']??0), 'calientes'=>(int)($row['calientes']??0), 'muy_calientes'=>(int)($row['muy_calientes']??0), 'puntaje_promedio'=>(int)($row['puntaje_promedio']??0)];
    }
}
