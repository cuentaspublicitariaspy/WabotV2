<?php
/** Puente privado para WS: devuelve solo resultados determinísticos de agenda. */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/Database.php';
require_once __DIR__ . '/../../includes/AppointmentManager.php';
header('Content-Type: application/json; charset=utf-8');

$input=json_decode(file_get_contents('php://input'),true)?:[];
$key=trim((string)($input['api_key']??''));
if($key===''){http_response_code(401);echo json_encode(['success'=>false,'error'=>'Credencial requerida']);exit;}
$stmt=Database::getConnection()->prepare('SELECT id FROM widget_config WHERE api_key=? AND enabled=1');$stmt->execute([$key]);
if(!$stmt->fetch()){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Cliente no autorizado']);exit;}
$agenda=new AppointmentManager();$action=$input['action']??'';
try {
    if($action==='catalogo'){
        echo json_encode(['success'=>true,'servicios'=>$agenda->list('servicios'),'profesionales'=>$agenda->list('profesionales'),'sucursales'=>$agenda->list('sucursales'),'reglas'=>$agenda->settings()]);exit;
    }
    if($action==='disponibilidad'){
        echo json_encode(['success'=>true,'slots'=>$agenda->availability($input)]);exit;
    }
    if($action==='crear'){
        // WS solo llama esta acción tras una confirmación explícita. WC vuelve
        // a validar disponibilidad y evita doble reserva dentro de la transacción.
        if(empty($input['confirmada']))throw new RuntimeException('La cita aún requiere confirmación del cliente.');
        $data=$input; $data['canal']=in_array(($input['canal']??''),['whatsapp','chatbot'],true)?$input['canal']:'chatbot';
        echo json_encode(['success'=>true,'cita_id'=>$agenda->create($data,'IA:'.$data['canal'])]);exit;
    }
    if($action==='estado'){
        $agenda->changeStatus((int)($input['cita_id']??0),(string)($input['estado']??''),'IA:'.($input['canal']??'chatbot'));
        echo json_encode(['success'=>true]);exit;
    }
    throw new InvalidArgumentException('Acción de agenda inválida.');
}catch(Throwable $e){http_response_code(422);echo json_encode(['success'=>false,'error'=>$e->getMessage()]);}
