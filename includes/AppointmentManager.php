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
        // Una agenda es el recurso que se reserva: puede ser una persona, una
        // sala, un consultorio o cualquier capacidad única del negocio.
        $this->db->exec("CREATE TABLE IF NOT EXISTS agenda_agendas (
            id INT AUTO_INCREMENT PRIMARY KEY, sucursal_id INT NOT NULL, nombre VARCHAR(150) NOT NULL,
            descripcion VARCHAR(255) NOT NULL DEFAULT '', buffer_minutes SMALLINT NOT NULL DEFAULT 0,
            activo TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agenda_sucursal (sucursal_id, activo)
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
        $this->ensureColumn('agenda_horarios', 'agenda_id', 'INT NULL AFTER id');
        $this->ensureColumn('agenda_bloqueos', 'agenda_id', 'INT NULL AFTER id');
        $this->ensureColumn('citas', 'agenda_id', 'INT NULL AFTER id');
        // Migración de instalaciones previas: cada profesional configurado se
        // transforma en una agenda de su sucursal para no perder datos.
        $this->migrateLegacyProfessionals();
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        // MariaDB no permite placeholders en SHOW COLUMNS ... LIKE. Consultar
        // information_schema sí admite parámetros y funciona en Hostinger.
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        if (!(int)$stmt->fetchColumn()) $this->db->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
    }

    private function migrateLegacyProfessionals(): void
    {
        $rows = $this->db->query("SELECT p.id,p.nombre,p.especialidad,p.sucursal_id FROM agenda_profesionales p
            LEFT JOIN agenda_agendas a ON a.nombre=p.nombre AND a.sucursal_id=COALESCE(p.sucursal_id,0)
            WHERE p.sucursal_id IS NOT NULL AND a.id IS NULL")->fetchAll();
        foreach ($rows as $row) {
            $this->db->prepare('INSERT INTO agenda_agendas(sucursal_id,nombre,descripcion) VALUES(?,?,?)')
                ->execute([(int)$row['sucursal_id'], $row['nombre'], $row['especialidad']]);
            $agendaId = (int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE agenda_horarios SET agenda_id=? WHERE profesional_id=? AND agenda_id IS NULL')->execute([$agendaId,(int)$row['id']]);
            $this->db->prepare('UPDATE citas SET agenda_id=? WHERE profesional_id=? AND agenda_id IS NULL')->execute([$agendaId,(int)$row['id']]);
            $this->db->prepare('UPDATE agenda_bloqueos SET agenda_id=? WHERE profesional_id=? AND agenda_id IS NULL')->execute([$agendaId,(int)$row['id']]);
        }
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
        $tables = ['servicios'=>'agenda_servicios','profesionales'=>'agenda_profesionales','sucursales'=>'agenda_sucursales','agendas'=>'agenda_agendas'];
        if (!isset($tables[$entity])) return [];
        if ($entity === 'agendas') return $this->db->query('SELECT a.*,s.nombre sucursal FROM agenda_agendas a LEFT JOIN agenda_sucursales s ON s.id=a.sucursal_id ORDER BY a.activo DESC,s.nombre,a.nombre')->fetchAll();
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
        } elseif ($entity === 'agendas') {
            $values=[(int)($data['sucursal_id']??0),trim((string)($data['nombre']??'')),trim((string)($data['descripcion']??'')),max(0,min(240,(int)($data['buffer_minutes']??0))),!empty($data['activo'])?1:0];
            if (!$values[0]) throw new InvalidArgumentException('Elegí la sucursal de esta agenda.');
            if ($values[1]==='') throw new InvalidArgumentException('La agenda necesita un nombre.');
            if ($id) { $values[]=$id; $this->db->prepare('UPDATE agenda_agendas SET sucursal_id=?,nombre=?,descripcion=?,buffer_minutes=?,activo=? WHERE id=?')->execute($values); return $id; }
            $this->db->prepare('INSERT INTO agenda_agendas(sucursal_id,nombre,descripcion,buffer_minutes,activo) VALUES(?,?,?,?,?)')->execute($values);
        } else throw new InvalidArgumentException('Entidad no válida.');
        return (int)$this->db->lastInsertId();
    }

    public function saveHours(array $data): void
    {
        $agendaId=(int)($data['agenda_id']??0);
        if (!$agendaId) throw new InvalidArgumentException('Elegí la agenda a la que pertenece este horario.');
        $day=(int)($data['dia_semana']??0); $from=(string)($data['hora_inicio']??''); $to=(string)($data['hora_fin']??'');
        if ($day<0 || $day>6 || !preg_match('/^\d\d:\d\d$/',$from) || !preg_match('/^\d\d:\d\d$/',$to) || $from >= $to) throw new InvalidArgumentException('Horario inválido.');
        $this->db->prepare('INSERT INTO agenda_horarios(agenda_id,dia_semana,hora_inicio,hora_fin) VALUES(?,?,?,?)')->execute([$agendaId,$day,$from,$to]);
    }

    public function hours(): array
    {
        return $this->db->query("SELECT h.*, a.nombre agenda, s.nombre sucursal FROM agenda_horarios h LEFT JOIN agenda_agendas a ON a.id=h.agenda_id LEFT JOIN agenda_sucursales s ON s.id=a.sucursal_id WHERE h.activo=1 ORDER BY h.dia_semana,h.hora_inicio")->fetchAll();
    }

    public function availability(array $filter): array
    {
        $serviceId=(int)($filter['servicio_id']??0); $agendaId=(int)($filter['agenda_id']??0);
        $date=(string)($filter['fecha']??'');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new InvalidArgumentException('Indicá una fecha válida.');
        $service=$this->db->prepare('SELECT duracion_minutos FROM agenda_servicios WHERE id=? AND activo=1'); $service->execute([$serviceId]); $duration=(int)$service->fetchColumn();
        if (!$duration) throw new InvalidArgumentException('Elegí un servicio activo.');
        if (!$agendaId) throw new InvalidArgumentException('Indicá la agenda que se desea consultar.');
        $agendaStmt=$this->db->prepare('SELECT * FROM agenda_agendas WHERE id=? AND activo=1');$agendaStmt->execute([$agendaId]);$agenda=$agendaStmt->fetch();
        if (!$agenda) throw new InvalidArgumentException('La agenda indicada no está disponible.');
        $settings=$this->settings(); $tz=new DateTimeZone($settings['timezone'] ?: 'America/Asuncion');
        $target=new DateTimeImmutable($date, $tz); $today=new DateTimeImmutable('today',$tz);
        if ($target<$today || $target>$today->modify('+'.(int)$settings['max_advance_days'].' days')) return [];
        $weekday=(int)$target->format('w');
        $q='SELECT hora_inicio,hora_fin FROM agenda_horarios WHERE activo=1 AND dia_semana=? AND agenda_id=? ORDER BY hora_inicio';
        $params=[$weekday,$agendaId];
        $stmt=$this->db->prepare($q); $stmt->execute($params); $ranges=$stmt->fetchAll();
        $slots=[]; $step=max(5,(int)$settings['slot_minutes']); $buffer=(int)$agenda['buffer_minutes'];
        foreach($ranges as $range) {
            $cursor=new DateTimeImmutable($date.' '.$range['hora_inicio'],$tz); $end=new DateTimeImmutable($date.' '.$range['hora_fin'],$tz);
            while($cursor->modify('+'.($duration+$buffer).' minutes') <= $end) {
                if ($cursor >= new DateTimeImmutable('+'.(int)$settings['min_notice_hours'].' hours',$tz) && $this->isFree($cursor,$cursor->modify('+'.$duration.' minutes'),$agendaId)) $slots[]=$cursor->format('H:i');
                $cursor=$cursor->modify('+'.$step.' minutes');
            }
        }
        return array_values(array_unique($slots));
    }

    public function nextAvailability(array $filter): array
    {
        $from=(string)($filter['fecha_desde']??date('Y-m-d'));
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from))throw new InvalidArgumentException('Fecha inicial inválida.');
        $days=max(1,min(30,(int)($filter['dias']??7)));
        $period=(string)($filter['franja']??'');
        $options=[];
        for($i=0;$i<$days&&count($options)<5;$i++){
            $date=date('Y-m-d',strtotime($from.' +'.$i.' day'));
            $slots=$this->availability(['servicio_id'=>$filter['servicio_id']??0,'agenda_id'=>$filter['agenda_id']??0,'fecha'=>$date]);
            if($period==='manana')$slots=array_values(array_filter($slots,static fn($slot)=>$slot<'12:00'));
            if($period==='tarde')$slots=array_values(array_filter($slots,static fn($slot)=>$slot>='12:00'));
            if($slots)$options[]=['fecha'=>$date,'horarios'=>array_slice($slots,0,4)];
        }
        return $options;
    }

    private function isFree(DateTimeImmutable $start, DateTimeImmutable $end, int $agendaId, ?int $ignoreId = null): bool
    {
        $bufferStmt=$this->db->prepare('SELECT buffer_minutes FROM agenda_agendas WHERE id=?');$bufferStmt->execute([$agendaId]);
        $buffer=max(0,(int)$bufferStmt->fetchColumn());
        $checkStart=$start->modify('-'.$buffer.' minutes');
        $checkEnd=$end->modify('+'.$buffer.' minutes');
        $sql="SELECT COUNT(*) FROM citas WHERE estado NOT IN ('cancelada_cliente','cancelada_negocio','no_asistio') AND inicio < ? AND fin > ?";
        $params=[$checkEnd->format('Y-m-d H:i:s'),$checkStart->format('Y-m-d H:i:s')];
        $sql.=' AND agenda_id=?';$params[]=$agendaId;
        if($ignoreId){$sql.=' AND id<>?';$params[]=$ignoreId;}
        $stmt=$this->db->prepare($sql);$stmt->execute($params); if((int)$stmt->fetchColumn()>0)return false;
        $sql='SELECT COUNT(*) FROM agenda_bloqueos WHERE inicio < ? AND fin > ?';$params=[$end->format('Y-m-d H:i:s'),$start->format('Y-m-d H:i:s')];
        $sql.=' AND (agenda_id=? OR agenda_id IS NULL)';$params[]=$agendaId;
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return (int)$stmt->fetchColumn()===0;
    }

    public function create(array $data, string $actor='manual'): int
    {
        $serviceId=(int)($data['servicio_id']??0); $service=$this->db->prepare('SELECT duracion_minutos,requiere_aprobacion FROM agenda_servicios WHERE id=? AND activo=1');$service->execute([$serviceId]);$service=$service->fetch();
        if(!$service)throw new InvalidArgumentException('Servicio inválido.');
        $start=(string)($data['inicio']??'');$tz=new DateTimeZone(($this->settings()['timezone']??'America/Asuncion'));
        try{$inicio=new DateTimeImmutable($start,$tz);}catch(Throwable $e){throw new InvalidArgumentException('Fecha y hora inválidas.');}
        $fin=$inicio->modify('+'.(int)$service['duracion_minutos'].' minutes');$agendaId=(int)($data['agenda_id']??0);
        if (!$agendaId) throw new InvalidArgumentException('Elegí una agenda para reservar.');
        $agendaStmt=$this->db->prepare('SELECT sucursal_id FROM agenda_agendas WHERE id=? AND activo=1');$agendaStmt->execute([$agendaId]);$branch=$agendaStmt->fetchColumn();
        if (!$branch) throw new InvalidArgumentException('La agenda seleccionada no está disponible.');
        // Ni un humano ni la IA pueden reservar fuera de los horarios y reglas
        // configurados. La disponibilidad es la fuente determinística única.
        $horas = $this->availability(['servicio_id'=>$serviceId,'agenda_id'=>$agendaId,'fecha'=>$inicio->format('Y-m-d')]);
        if (!in_array($inicio->format('H:i'), $horas, true)) throw new RuntimeException('Ese horario no está disponible según las reglas de agenda.');
        $this->db->beginTransaction();
        try {
            if(!$this->isFree($inicio,$fin,$agendaId)) throw new RuntimeException('Ese horario ya no está disponible.');
            $status=!empty($service['requiere_aprobacion'])?'pendiente_confirmacion':'confirmada';
            $history=json_encode([['at'=>date('c'),'action'=>'creada','by'=>$actor]],JSON_UNESCAPED_UNICODE);
            $stmt=$this->db->prepare('INSERT INTO citas(agenda_id,prospecto_id,nombre_cliente,telefono,email,servicio_id,sucursal_id,inicio,fin,estado,motivo,observaciones,canal,creado_por,historial) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$agendaId,(int)($data['prospecto_id']??0)?:null,trim((string)($data['nombre_cliente']??'')),preg_replace('/\D+/','',(string)($data['telefono']??'')),trim((string)($data['email']??'')),$serviceId,$branch,$inicio->format('Y-m-d H:i:s'),$fin->format('Y-m-d H:i:s'),$status,trim((string)($data['motivo']??'')),trim((string)($data['observaciones']??'')),$data['canal']??'manual',$actor,$history]);
            $id=(int)$this->db->lastInsertId();$this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function appointments(string $from, string $to): array
    {
        $stmt=$this->db->prepare("SELECT c.*, s.nombre servicio,a.nombre agenda,b.nombre sucursal FROM citas c LEFT JOIN agenda_servicios s ON s.id=c.servicio_id LEFT JOIN agenda_agendas a ON a.id=c.agenda_id LEFT JOIN agenda_sucursales b ON b.id=c.sucursal_id WHERE c.inicio >= ? AND c.inicio < ? ORDER BY c.inicio ASC");
        $stmt->execute([$from,$to]);return $stmt->fetchAll();
    }

    /** Resumen operativo para el día seleccionado. No contiene datos simulados. */
    public function dayOverview(string $date): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new InvalidArgumentException('Fecha inválida.');
        $from = $date.' 00:00:00';
        $to = date('Y-m-d 00:00:00', strtotime($date.' +1 day'));
        $appointments = $this->appointments($from, $to);
        $blocks = $this->blocks($from, $to);
        $occupied = 0;
        $pending = 0;
        foreach ($appointments as $appointment) {
            if (!in_array($appointment['estado'], ['cancelada_cliente','cancelada_negocio','no_asistio'], true)) {
                $occupied += max(0, strtotime($appointment['fin']) - strtotime($appointment['inicio']));
            }
            if ($appointment['estado'] === 'pendiente_confirmacion') $pending++;
        }
        return [
            'appointments' => $appointments,
            'blocks' => $blocks,
            'total' => count($appointments),
            'pending' => $pending,
            'occupied_minutes' => (int)round($occupied / 60),
            'ready_profiles' => count(array_filter($appointments, static fn($a) => !empty($a['prospecto_id']) || !empty($a['motivo'])))
        ];
    }

    public function blocks(string $from, string $to): array
    {
        $stmt = $this->db->prepare("SELECT b.*, a.nombre agenda, s.nombre sucursal
            FROM agenda_bloqueos b
            LEFT JOIN agenda_agendas a ON a.id=b.agenda_id
            LEFT JOIN agenda_sucursales s ON s.id=COALESCE(a.sucursal_id,b.sucursal_id)
            WHERE b.inicio < ? AND b.fin > ? ORDER BY b.inicio ASC");
        $stmt->execute([$to, $from]);
        return $stmt->fetchAll();
    }

    public function createBlock(array $data, string $actor = 'manual'): int
    {
        $tz = new DateTimeZone($this->settings()['timezone'] ?? 'America/Asuncion');
        try {
            $start = new DateTimeImmutable((string)($data['inicio'] ?? ''), $tz);
            $end = new DateTimeImmutable((string)($data['fin'] ?? ''), $tz);
        } catch (Throwable $e) { throw new InvalidArgumentException('Indicá un inicio y fin válidos para el bloqueo.'); }
        if ($end <= $start) throw new InvalidArgumentException('El bloqueo debe terminar después de comenzar.');
        $reason = trim((string)($data['motivo'] ?? 'Bloqueo de agenda'));
        $stmt = $this->db->prepare('INSERT INTO agenda_bloqueos(agenda_id,sucursal_id,inicio,fin,motivo) VALUES(?,?,?,?,?)');
        $stmt->execute([
            (int)($data['agenda_id'] ?? 0) ?: null,
            null,
            $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'),
            $reason.' · '.$actor
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function changeStatus(int $id,string $status,string $actor='manual'): void
    {
        $allowed=['confirmada','pendiente_confirmacion','reprogramada','cancelada_cliente','cancelada_negocio','no_asistio','completada'];
        if(!in_array($status,$allowed,true))throw new InvalidArgumentException('Estado no válido.');
        $current=$this->db->prepare('SELECT historial FROM citas WHERE id=?');$current->execute([$id]);$history=$current->fetchColumn();
        if($history===false)throw new InvalidArgumentException('La cita no existe.');
        $entries=json_decode($history?:'[]',true)?:[];$entries[]=['at'=>date('c'),'action'=>'estado:'.$status,'by'=>$actor];
        $this->db->prepare('UPDATE citas SET estado=?,historial=? WHERE id=?')->execute([$status,json_encode($entries,JSON_UNESCAPED_UNICODE),$id]);
    }

    public function findUpcoming(array $filter): array
    {
        $phone=preg_replace('/\D+/','',(string)($filter['telefono']??''));
        $email=trim((string)($filter['email']??''));
        $name=trim((string)($filter['nombre_cliente']??''));
        if(!$phone&&!$email&&!$name)throw new InvalidArgumentException('Hace falta teléfono, correo o nombre para localizar la cita.');
        $where=['c.inicio>=NOW()','c.estado NOT IN ("cancelada_cliente","cancelada_negocio","no_asistio","completada")'];$params=[];
        if($phone){$where[]='c.telefono=?';$params[]=$phone;}elseif($email){$where[]='c.email=?';$params[]=$email;}else{$where[]='c.nombre_cliente LIKE ?';$params[]='%'.$name.'%';}
        $stmt=$this->db->prepare('SELECT c.id,c.nombre_cliente,c.telefono,c.inicio,c.fin,c.estado,s.nombre servicio,a.nombre agenda FROM citas c LEFT JOIN agenda_servicios s ON s.id=c.servicio_id LEFT JOIN agenda_agendas a ON a.id=c.agenda_id WHERE '.implode(' AND ',$where).' ORDER BY c.inicio ASC LIMIT 5');
        $stmt->execute($params);return $stmt->fetchAll();
    }

    public function reschedule(int $id, array $data, string $actor='manual'): void
    {
        $existing=$this->db->prepare('SELECT * FROM citas WHERE id=?');$existing->execute([$id]);$existing=$existing->fetch();
        if (!$existing) throw new InvalidArgumentException('La cita no existe.');
        if (in_array($existing['estado'], ['cancelada_cliente','cancelada_negocio','no_asistio','completada'], true)) throw new RuntimeException('Esa cita ya no puede reprogramarse.');
        $agendaId=(int)($data['agenda_id'] ?? $existing['agenda_id']);
        $serviceId=(int)($data['servicio_id'] ?? $existing['servicio_id']);
        $service=$this->db->prepare('SELECT duracion_minutos FROM agenda_servicios WHERE id=? AND activo=1');$service->execute([$serviceId]);$duration=(int)$service->fetchColumn();
        if (!$duration || !$agendaId) throw new InvalidArgumentException('Indicá servicio y agenda válidos.');
        $tz=new DateTimeZone(($this->settings()['timezone']??'America/Asuncion'));
        try {$start=new DateTimeImmutable((string)($data['inicio']??''),$tz);} catch(Throwable $e) { throw new InvalidArgumentException('Nueva fecha y hora inválidas.'); }
        $end=$start->modify('+'.$duration.' minutes');
        $day=(int)$start->format('w');
        $hours=$this->db->prepare('SELECT hora_inicio,hora_fin FROM agenda_horarios WHERE agenda_id=? AND dia_semana=? AND activo=1');$hours->execute([$agendaId,$day]);
        $fits=false;foreach($hours->fetchAll() as $range){$from=new DateTimeImmutable($start->format('Y-m-d').' '.$range['hora_inicio'],$tz);$to=new DateTimeImmutable($start->format('Y-m-d').' '.$range['hora_fin'],$tz);if($start>=$from&&$end<=$to){$fits=true;break;}}
        if(!$fits) throw new RuntimeException('El nuevo horario está fuera de la disponibilidad de esa agenda.');
        if(!$this->isFree($start,$end,$agendaId,$id)) throw new RuntimeException('El nuevo horario se superpone con otra cita o bloqueo.');
        $branch=$this->db->prepare('SELECT sucursal_id FROM agenda_agendas WHERE id=? AND activo=1');$branch->execute([$agendaId]);$branch=$branch->fetchColumn();if(!$branch)throw new InvalidArgumentException('La agenda seleccionada no está disponible.');
        $history=json_decode($existing['historial']??'[]',true)?:[];$history[]=['at'=>date('c'),'action'=>'reprogramada','by'=>$actor,'from'=>$existing['inicio'],'to'=>$start->format('Y-m-d H:i:s')];
        $this->db->prepare('UPDATE citas SET agenda_id=?,sucursal_id=?,servicio_id=?,inicio=?,fin=?,estado="reprogramada",historial=? WHERE id=?')->execute([$agendaId,$branch,$serviceId,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),json_encode($history,JSON_UNESCAPED_UNICODE),$id]);
    }
}
