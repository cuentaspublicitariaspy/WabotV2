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
            estado ENUM('nuevo','contactado','seguimiento','cerrado') NOT NULL DEFAULT 'nuevo',
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
        try { $this->db->exec("ALTER TABLE prospectos ADD COLUMN estado ENUM('nuevo','contactado','seguimiento','cerrado') NOT NULL DEFAULT 'nuevo'"); } catch (Throwable $e) {}
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
        $campos = ['nombre','email','whatsapp','direccion','ciudad','pais','sitio_web','ocupacion','empresa','estado','notas','resumen','intencion','nivel_interes','temperatura','puntaje'];
        $sets=[]; $values=[];
        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $datos)) continue;
            $valor = trim((string)$datos[$campo]);
            if ($campo === 'whatsapp') $valor = preg_replace('/\D+/', '', $valor);
            if ($campo === 'nivel_interes' && !in_array($valor, ['bajo','medio','alto'], true)) $valor='medio';
            if ($campo === 'temperatura' && !in_array($valor, ['frio','tibio','caliente','muy_caliente'], true)) $valor='tibio';
            if ($campo === 'estado' && !in_array($valor, ['nuevo','contactado','seguimiento','cerrado'], true)) $valor='nuevo';
            if ($campo === 'puntaje') $valor=max(0,min(100,(int)$valor));
            $sets[] = "$campo = ?"; $values[] = $valor;
        }
        if ($sets) { $values[]=$id; $this->db->prepare('UPDATE prospectos SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values); }
    }

    public function crear(array $datos): int
    {
        $this->db->prepare("INSERT INTO prospectos (nombre, estado) VALUES (?, 'nuevo')")
            ->execute([trim((string) ($datos['nombre'] ?? ''))]);
        $id = (int) $this->db->lastInsertId();
        $this->actualizar($id, $datos);
        return $id;
    }

    public function eliminar(int $id): void
    {
        $this->db->prepare('DELETE FROM prospectos WHERE id = ?')->execute([$id]);
    }

    /** Datos inequívocos que pueden actualizarse localmente, sin esperar IA. */
    public function detectarDatosBasicos(string $mensaje): array
    {
        $datos = [];
        if (preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $mensaje, $m)) $datos['email'] = $m[0];
        if (preg_match('/\b(?:https?:\/\/|www\.)[^\s<>()]+/i', $mensaje, $m)) $datos['sitio_web'] = $m[0];
        if (preg_match('/(?<!\w)(?:\+?\d[\d\s().-]{6,}\d)(?!\w)/', $mensaje, $m)) {
            $telefono = preg_replace('/\D+/', '', $m[0]);
            if (strlen($telefono) >= 7) $datos['whatsapp'] = $telefono;
        }
        // El nombre se resuelve localmente, sin depender de una llamada a IA.
        // Acepta los formatos reales que suele usar una persona en un chat.
        $nombrePatrones = [
            '/\b(?:me\s+llamo|mi\s+nombre\s+(?:es\s*)?|nombre|soy|me\s+dicen|pod[eé]s\s+llamarme)\s*(?:es\s*)?[:=,-]?\s*([\p{L}][\p{L}\'’.-]*(?:\s+[\p{L}][\p{L}\'’.-]*){0,3})/iu',
        ];
        foreach ($nombrePatrones as $patron) {
            if (!preg_match($patron, $mensaje, $m)) continue;
            $nombre = rtrim(trim(preg_replace('/\s+/', ' ', $m[1])), ".,;:!?");
            // Evita tomar frases como “soy de Asunción”, “soy un cliente” o
            // conectores que no son un nombre.
            $nombre = preg_split('/\s+\b(?:y|pero|para|porque|de|desde|con|que|tengo|quiero)\b/iu', $nombre)[0];
            if ($nombre !== '' && !preg_match('/^(?:de|un|una|el|la|cliente|parte|interesado|interesada)\b/iu', $nombre)) {
                $datos['nombre'] = $nombre;
                break;
            }
        }
        // No se acepta una palabra aislada solo porque comienza en mayúscula.
        // "Sabes?" es un ejemplo real de por qué esa heurística es peligrosa:
        // puede cambiar el nombre de una persona por una palabra común. Las
        // respuestas breves se validan abajo, usando el contexto conversacional.
        return $datos;
    }

    /**
     * Un nombre inferido por IA solo puede escribirse si está realmente
     * declarado por el visitante. La IA ayuda a comprender el contexto, pero
     * nunca tiene permiso de inventar una identidad ni de reemplazarla.
     */
    private function nombreDeclaradoEnContexto(string $nombre, string $mensaje, array $contexto): bool
    {
        $nombre = trim($nombre);
        $mensaje = trim($mensaje);
        if ($nombre === '' || $mensaje === '') return false;

        $nombreEscapado = preg_quote($nombre, '/');
        // Declaración espontánea: "soy Ana", "me llamo Ana", etc.
        if (preg_match('/\b(?:me\s+llamo|mi\s+nombre\s+(?:es\s*)?|soy|me\s+dicen|pod[eé]s\s+llamarme)\s*(?:es\s*)?[:=,-]?\s*' . $nombreEscapado . '\b/iu', $mensaje)) {
            return true;
        }

        // Respuesta corta ("Ana Pérez") solo es válida si el turno anterior
        // del asistente preguntó expresamente por el nombre.
        $limpio = trim(preg_replace('/[.,;:!?]+$/u', '', $mensaje));
        if (strcasecmp($limpio, $nombre) !== 0) return false;
        if (!preg_match('/^[\p{L}][\p{L}\'’.-]*(?:\s+[\p{L}][\p{L}\'’.-]*){0,3}$/u', $limpio)) return false;

        for ($i = count($contexto) - 2; $i >= 0; $i--) {
            if (($contexto[$i]['role'] ?? '') !== 'assistant') continue;
            $anterior = (string) ($contexto[$i]['content'] ?? '');
            return (bool) preg_match('/(?:c[oó]mo\s+te\s+llam[aá]s|cu[aá]l\s+es\s+tu\s+nombre|decime\s+tu\s+nombre|tu\s+nombre\s*(?:completo)?)/iu', $anterior);
        }
        return false;
    }

    /**
     * La IA puede comprender un mensaje, pero no aportar datos que fueron
     * dichos por el asistente. Para todo campo distinto del nombre, el valor
     * debe estar materialmente presente en el turno actual del visitante.
     */
    private function valorDeclaradoPorVisitante(string $valor, string $mensaje): bool
    {
        $valor = trim($valor);
        $mensaje = trim($mensaje);
        if ($valor === '' || $mensaje === '') return false;

        $normalizar = static function (string $texto): string {
            $texto = preg_replace('/\s+/u', ' ', trim($texto));
            return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
        };
        return str_contains($normalizar($mensaje), $normalizar($valor));
    }

    private function filtrarDatosVerificables(array $datos, string $mensaje, array $contexto): array
    {
        foreach ($datos as $campo => $valor) {
            $valor = trim((string) $valor);
            if ($valor === '') { unset($datos[$campo]); continue; }
            if ($campo === 'nombre') {
                if (!$this->nombreDeclaradoEnContexto($valor, $mensaje, $contexto)) unset($datos[$campo]);
                continue;
            }
            if (!$this->valorDeclaradoPorVisitante($valor, $mensaje)) unset($datos[$campo]);
        }
        return $datos;
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

    /**
     * Extrae datos declarados usando el contexto inmediato de la conversación.
     * WS procesa ese contexto en tránsito y no lo almacena. Así una respuesta
     * breve como “Roberto” se interpreta correctamente si antes se preguntó
     * “¿Cómo te llamás?”.
     */
    public function registrarDatosDeclarados(int $id, string $mensaje, array $contexto = [], string $apiKey = ''): array
    {
        $mensaje = trim($mensaje);
        $basicos = $this->detectarDatosBasicos($mensaje);
        $this->guardarDatosDeclarados($id, $basicos);
        if ($mensaje === '') return $basicos;
        $prompt = 'Extraé exclusivamente datos personales o comerciales que el visitante comunique durante esta conversación. Respondé SOLO JSON válido con: nombre,email,whatsapp,direccion,ciudad,pais,sitio_web,ocupacion,empresa. Para lo que no esté explícito devolvé cadena vacía. No inventes ni infieras. La extracción NO depende de que el asistente haya hecho una pregunta: si el visitante expresa espontáneamente su nombre, correo, teléfono u otro dato, guardalo en su campo. Usá el contexto para comprender respuestas breves, pero no tomes datos del asistente como datos del visitante.';
        $mensajes = [['role'=>'system','content'=>$prompt]];
        foreach ($contexto as $turno) {
            $rol = ($turno['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $texto = trim((string) ($turno['content'] ?? ''));
            if ($texto !== '') $mensajes[] = ['role'=>$rol, 'content'=>$texto];
        }
        // Compatibilidad con llamadas antiguas que todavía no entregan historial.
        if (!$contexto) $mensajes[] = ['role'=>'user','content'=>$mensaje];
        // El Chatbot ya está vinculado a un cliente de WS por su API Key. La
        // License Key sigue siendo la credencial preferida para WC/WhatsApp,
        // pero su ausencia no puede anular silenciosamente la extracción.
        $payload = json_encode([
            'action'=>'chat',
            'license_key'=>defined('LICENSE_KEY') ? LICENSE_KEY : '',
            'api_key'=>$apiKey,
            'messages'=>$mensajes
        ], JSON_UNESCAPED_UNICODE);
        $ch = curl_init('https://wabot-cdn.vercel.app/api/proxy/openai');
        curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12]);
        $response = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $content = json_decode((string)$response, true)['content'] ?? '';
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim((string)$content));
        $data = json_decode($content, true);
        if ($status === 200 && is_array($data)) {
            // WS interpreta; WC solo persiste datos verificables en el turno
            // del visitante. Nunca los extraídos de un mensaje del asistente.
            $data = $this->filtrarDatosVerificables($data, $mensaje, $contexto);
            $this->guardarDatosDeclarados($id, $data);
            return array_merge($basicos, array_filter($data, static fn($value) => trim((string) $value) !== ''));
        }
        return $basicos;
    }

    public function metricas(): array
    {
        $row = $this->db->query("SELECT COUNT(*) total, SUM(temperatura IN ('caliente','muy_caliente')) calientes, SUM(temperatura='muy_caliente') muy_calientes, ROUND(AVG(puntaje)) puntaje_promedio FROM prospectos")->fetch() ?: [];
        return ['total'=>(int)($row['total']??0), 'calientes'=>(int)($row['calientes']??0), 'muy_calientes'=>(int)($row['muy_calientes']??0), 'puntaje_promedio'=>(int)($row['puntaje_promedio']??0)];
    }
}
