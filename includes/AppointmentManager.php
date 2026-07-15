<?php

require_once __DIR__ . '/Database.php';

/** Motor determinístico de agenda. La IA interpreta; esta clase decide. */
class AppointmentManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_settings (
            id TINYINT PRIMARY KEY DEFAULT 1, timezone VARCHAR(64) NOT NULL DEFAULT 'America/Asuncion',
            slot_minutes SMALLINT NOT NULL DEFAULT 30, buffer_minutes SMALLINT NOT NULL DEFAULT 0,
            min_notice_hours SMALLINT NOT NULL DEFAULT 2, max_advance_days SMALLINT NOT NULL DEFAULT 90,
            cancel_notice_hours SMALLINT NOT NULL DEFAULT 12, reminder_hours SMALLINT NOT NULL DEFAULT 24,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("INSERT IGNORE INTO agenda_settings (id) VALUES (1)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_sucursales (
            id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(120) NOT NULL, direccion VARCHAR(255) NOT NULL DEFAULT '', activo TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_profesionales (
            id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(150) NOT NULL, especialidad VARCHAR(150) NOT NULL DEFAULT '', sucursal_id INT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1, vacaciones_desde DATE NULL, vacaciones_hasta DATE NULL,
            INDEX idx_sucursal (sucursal_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_servicios (
            id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(150) NOT NULL, duracion_minutos SMALLINT NOT NULL DEFAULT 30,
            requiere_aprobacion TINYINT(1) NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_horarios (
            id INT AUTO_INCREMENT PRIMARY KEY, profesional_id INT NULL, sucursal_id INT NULL, dia_semana TINYINT NOT NULL,
            hora_inicio TIME NOT NULL, hora_fin TIME NOT NULL, activo TINYINT(1) NOT NULL DEFAULT 1,
            INDEX idx_prof_dia (profesional_id, dia_semana), INDEX idx_suc_dia (sucursal_id, dia_semana)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_bloqueos (
            id INT AUTO_INCREMENT PRIMARY KEY, profesional_id INT NULL, sucursal_id INT NULL, inicio DATETIME NOT NULL, fin DATETIME NOT NULL,
            motivo VARCHAR(255) NOT NULL DEFAULT '', INDEX idx_bloqueo (inicio, fin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS citas (
            id INT AUTO_INCREMENT PRIMARY KEY, prospecto_id INT NULL, nombre_cliente VARCHAR(150) NOT NULL DEFAULT '', telefono VARCHAR(40) NOT NULL DEFAULT '', email VARCHAR(255) NOT NULL DEFAULT '',
            servicio_id INT NULL, profesional_id INT NULL, sucursal_id INT NULL, inicio DATETIME NOT NULL, fin DATETIME NOT NULL,
            estado ENUM('solicitud','falta_informacion','propuesta','confirmada','pendiente_confirmacion','reprogramada','cancelada_cliente','cancelada_negocio','no_asistio','completada','lista_espera') NOT NULL DEFAULT 'solicitud',
            motivo TEXT NULL, observaciones TEXT NULL, canal ENUM('whatsapp','chatbot','manual') NOT NULL DEFAULT 'manual', creado_por VARCHAR(80) NOT NULL DEFAULT 'sistema',
            historial JSON NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_agenda (profesional_id, inicio, fin), INDEX idx_cliente (telefono, inicio), INDEX idx_estado (estado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_lista_espera (
            id INT AUTO_INCREMENT PRIMARY KEY, prospecto_id INT NULL, nombre_cliente VARCHAR(150) NOT NULL DEFAULT '', telefono VARCHAR(40) NOT NULL DEFAULT '',
            servicio_id INT NULL, profesional_id INT NULL, sucursal_id INT NULL, desde DATE NULL, hasta DATE NULL, preferencia TEXT NULL,
            estado ENUM('activa','ofrecida','convertida','cancelada') NOT NULL DEFAULT 'activa', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function settings(): array
    {
        return $this->db->query('SELECT * FROM agenda_settings WHERE id=1')->fetch() ?: [];
    }

    public function saveSettings(array $data): void
    {
        $allowed = ['timezone','slot_minutes','buffer_minutes','min_notice_hours','max_advance_days','cancel_notice_hours','reminder_hours'];
        $sets=[]; $values=[];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) continue;
            $value = trim((string)$data[$field]);
            if ($field !== 'timezone') $value = max(0, min(365, (int)$value));
            if ($field === 'timezone' && !in_array($value, timezone_identifiers_list(), true)) continue;
            $sets[]="$field=?"; $values[]=$value;
        }
        if ($sets) { $values[] = 1; $this->db->prepare('UPDATE agenda_settings SET '.implode(',', $sets).' WHERE id=?')->execute($values); }
    }

    public function list(string $entity): array
    {
        $tables = ['servicios'=>'agenda_servicios','profesionales'=>'agenda_profesionales','sucursales'=>'agenda_sucursales'];
        if (!isset($tables[$entity])) return [];
        return $this->db->query('SELECT * FROM '.$tables[$entity].' ORDER BY activo DESC, nombre ASC')->fetchAll();
    }

    public function saveEntity(string $entity, array $data): int
    {
        $id=(int)($data['id']??0);
        if ($entity === 'servicios') {
            $values=[trim((string)($data['nombre']??'')),max(5,(int)($data['duracion_minutos']??30)),!empty($data['requiere_aprobacion'])?1:0,!empty($data['activo'])?1:0];
            if ($values[0]==='') throw new InvalidArgumentException('El servicio necesita un nombre.');
            if ($id) { $values[]=$id; $this->db->prepare('UPDATE agenda_servicios SET nombre=?,duracion_minutos=?,requiere_aprobacion=?,activo=? WHERE id=?')->execute($values); return $id; }
            $this->db->prepare('INSERT INTO agenda_servicios(nombre,duracion_minutos,requiere_aprobacion,activo) VALUES(?,?,?,?)')->execute($values);
        } elseif ($entity === 'profesionales') {
            $values=[trim((string)($data['nombre']??'')),trim((string)($data['especialidad']??'')),(int)($data['sucursal_id']??0)?:null,!empty($data['activo'])?1:0];
            if ($values[0]==='') throw new InvalidArgumentException('El profesional necesita un nombre.');
            if ($id) { $values[]=$id; $this->db->prepare('UPDATE agenda_profesionales SET nombre=?,especialidad=?,sucursal_id=?,activo=? WHERE id=?')->execute($values); return $id; }
            $this->db->prepare('INSERT INTO agenda_profesionales(nombre,especialidad,sucursal_id,activo) VALUES(?,?,?,?)')->execute($values);
        } elseif ($entity === 'sucursales') {
            $values=[trim((string)($data['nombre']??'')),trim((string)($data['direccion']??'')),!empty($data['activo'])?1:0];
            if ($values[0]==='') throw new InvalidArgumentException('La sucursal necesita un nombre.');
            if ($id) { $values[]=$id; $this->db->prepare('UPDATE agenda_sucursales SET nombre=?,direccion=?,activo=? WHERE id=?')->execute($values); return $id; }
            $this->db->prepare('INSERT INTO agenda_sucursales(nombre,direccion,activo) VALUES(?,?,?)')->execute($values);
        } else throw new InvalidArgumentException('Entidad no válida.');
        return (int)$this->db->lastInsertId();
    }

    public function saveHours(array $data): void
    {
        $professional=(int)($data['profesional_id']??0)?:null; $branch=(int)($data['sucursal_id']??0)?:null;
        if (!$professional && !$branch) throw new InvalidArgumentException('Elegí profesional o sucursal.');
        $day=(int)($data['dia_semana']??0); $from=(string)($data['hora_inicio']??''); $to=(string)($data['hora_fin']??'');
        if ($day<0 || $day>6 || !preg_match('/^\d\d:\d\d$/',$from) || !preg_match('/^\d\d:\d\d$/',$to) || $from >= $to) throw new InvalidArgumentException('Horario inválido.');
        $this->db->prepare('INSERT INTO agenda_horarios(profesional_id,sucursal_id,dia_semana,hora_inicio,hora_fin) VALUES(?,?,?,?,?)')->execute([$professional,$branch,$day,$from,$to]);
    }

    public function hours(): array
    {
        return $this->db->query("SELECT h.*, p.nombre profesional, s.nombre sucursal FROM agenda_horarios h LEFT JOIN agenda_profesionales p ON p.id=h.profesional_id LEFT JOIN agenda_sucursales s ON s.id=h.sucursal_id WHERE h.activo=1 ORDER BY h.dia_semana,h.hora_inicio")->fetchAll();
    }

    public function availability(array $filter): array
    {
        $serviceId=(int)($filter['servicio_id']??0); $professional=(int)($filter['profesional_id']??0)?:null; $branch=(int)($filter['sucursal_id']??0)?:null;
        $date=(string)($filter['fecha']??'');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new InvalidArgumentException('Indicá una fecha válida.');
        $service=$this->db->prepare('SELECT duracion_minutos FROM agenda_servicios WHERE id=? AND activo=1'); $service->execute([$serviceId]); $duration=(int)$service->fetchColumn();
        if (!$duration) throw new InvalidArgumentException('Elegí un servicio activo.');
        $settings=$this->settings(); $tz=new DateTimeZone($settings['timezone'] ?: 'America/Asuncion');
        $target=new DateTimeImmutable($date, $tz); $today=new DateTimeImmutable('today',$tz);
        if ($target<$today || $target>$today->modify('+'.(int)$settings['max_advance_days'].' days')) return [];
        $weekday=(int)$target->format('w');
        if ($professional) {
            $p=$this->db->prepare('SELECT vacaciones_desde,vacaciones_hasta FROM agenda_profesionales WHERE id=? AND activo=1'); $p->execute([$professional]); $vac=$p->fetch();
            if (!$vac || ($vac['vacaciones_desde'] && $date >= $vac['vacaciones_desde'] && $date <= $vac['vacaciones_hasta'])) return [];
        }
        $q='SELECT hora_inicio,hora_fin FROM agenda_horarios WHERE activo=1 AND dia_semana=? AND ((profesional_id '.($professional?'=?':'IS NULL').') OR (sucursal_id '.($branch?'=?':'IS NULL').')) ORDER BY hora_inicio';
        $params=[$weekday]; if($professional)$params[]=$professional; if($branch)$params[]=$branch;
        $stmt=$this->db->prepare($q); $stmt->execute($params); $ranges=$stmt->fetchAll();
        $slots=[]; $step=max(5,(int)$settings['slot_minutes']); $buffer=(int)$settings['buffer_minutes'];
        foreach($ranges as $range) {
            $cursor=new DateTimeImmutable($date.' '.$range['hora_inicio'],$tz); $end=new DateTimeImmutable($date.' '.$range['hora_fin'],$tz);
            while($cursor->modify('+'.($duration+$buffer).' minutes') <= $end) {
                if ($cursor >= new DateTimeImmutable('+'.(int)$settings['min_notice_hours'].' hours',$tz) && $this->isFree($cursor,$cursor->modify('+'.$duration.' minutes'),$professional,$branch)) $slots[]=$cursor->format('H:i');
                $cursor=$cursor->modify('+'.$step.' minutes');
            }
        }
        return array_values(array_unique($slots));
    }

    private function isFree(DateTimeImmutable $start, DateTimeImmutable $end, ?int $professional, ?int $branch, ?int $ignoreId = null): bool
    {
        $sql="SELECT COUNT(*) FROM citas WHERE estado NOT IN ('cancelada_cliente','cancelada_negocio','no_asistio') AND inicio < ? AND fin > ?";
        $params=[$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')];
        if($professional){$sql.=' AND profesional_id=?';$params[]=$professional;} elseif($branch){$sql.=' AND sucursal_id=?';$params[]=$branch;}
        if($ignoreId){$sql.=' AND id<>?';$params[]=$ignoreId;}
        $stmt=$this->db->prepare($sql);$stmt->execute($params); if((int)$stmt->fetchColumn()>0)return false;
        $sql='SELECT COUNT(*) FROM agenda_bloqueos WHERE inicio < ? AND fin > ?';$params=[$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')];
        if($professional){$sql.=' AND (profesional_id=? OR profesional_id IS NULL)';$params[]=$professional;} elseif($branch){$sql.=' AND (sucursal_id=? OR sucursal_id IS NULL)';$params[]=$branch;}
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn()===0;
    }

    public function create(array $data, string $actor='manual'): int
    {
        $serviceId=(int)($data['servicio_id']??0); $service=$this->db->prepare('SELECT duracion_minutos,requiere_aprobacion FROM agenda_servicios WHERE id=? AND activo=1');$service->execute([$serviceId]);$service=$service->fetch();
        if(!$service)throw new InvalidArgumentException('Servicio inválido.');
        $start=(string)($data['inicio']??'');$tz=new DateTimeZone(($this->settings()['timezone']??'America/Asuncion'));
        try{$inicio=new DateTimeImmutable($start,$tz);}catch(Throwable $e){throw new InvalidArgumentException('Fecha y hora inválidas.');}
        $fin=$inicio->modify('+'.(int)$service['duracion_minutos'].' minutes');$prof=(int)($data['profesional_id']??0)?:null;$branch=(int)($data['sucursal_id']??0)?:null;
        // Ni un humano ni la IA pueden reservar fuera de los horarios y reglas
        // configurados. La disponibilidad es la fuente determinística única.
        $horas = $this->availability(['servicio_id'=>$serviceId,'profesional_id'=>$prof,'sucursal_id'=>$branch,'fecha'=>$inicio->format('Y-m-d')]);
        if (!in_array($inicio->format('H:i'), $horas, true)) throw new RuntimeException('Ese horario no está disponible según las reglas de agenda.');
        $this->db->beginTransaction();
        try {
            if(!$this->isFree($inicio,$fin,$prof,$branch)) throw new RuntimeException('Ese horario ya no está disponible.');
            $status=!empty($service['requiere_aprobacion'])?'pendiente_confirmacion':'confirmada';
            $history=json_encode([['at'=>date('c'),'action'=>'creada','by'=>$actor]],JSON_UNESCAPED_UNICODE);
            $stmt=$this->db->prepare('INSERT INTO citas(prospecto_id,nombre_cliente,telefono,email,servicio_id,profesional_id,sucursal_id,inicio,fin,estado,motivo,observaciones,canal,creado_por,historial) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([(int)($data['prospecto_id']??0)?:null,trim((string)($data['nombre_cliente']??'')),preg_replace('/\D+/','',(string)($data['telefono']??'')),trim((string)($data['email']??'')),$serviceId,$prof,$branch,$inicio->format('Y-m-d H:i:s'),$fin->format('Y-m-d H:i:s'),$status,trim((string)($data['motivo']??'')),trim((string)($data['observaciones']??'')),$data['canal']??'manual',$actor,$history]);
            $id=(int)$this->db->lastInsertId();$this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function appointments(string $from, string $to): array
    {
        $stmt=$this->db->prepare("SELECT c.*, s.nombre servicio,p.nombre profesional,b.nombre sucursal FROM citas c LEFT JOIN agenda_servicios s ON s.id=c.servicio_id LEFT JOIN agenda_profesionales p ON p.id=c.profesional_id LEFT JOIN agenda_sucursales b ON b.id=c.sucursal_id WHERE c.inicio >= ? AND c.inicio < ? ORDER BY c.inicio ASC");
        $stmt->execute([$from,$to]);return $stmt->fetchAll();
    }

    public function changeStatus(int $id,string $status,string $actor='manual'): void
    {
        $allowed=['confirmada','pendiente_confirmacion','reprogramada','cancelada_cliente','cancelada_negocio','no_asistio','completada'];
        if(!in_array($status,$allowed,true))throw new InvalidArgumentException('Estado no válido.');
        $this->db->prepare('UPDATE citas SET estado=? WHERE id=?')->execute([$status,$id]);
    }
}
