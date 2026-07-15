<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/AppointmentManager.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false]); exit; }
$input=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$agenda=new AppointmentManager(); $user=getUsuarioActual();
try {
    switch($input['action']??'') {
        case 'availability': $out=['slots'=>$agenda->availability($input)]; break;
        case 'save_entity': $out=['id'=>$agenda->saveEntity((string)($input['entity']??''),$input)]; break;
        case 'save_hours': $agenda->saveHours($input); $out=[]; break;
        case 'save_settings': $agenda->saveSettings($input); $out=[]; break;
        case 'create': $input['canal']=$input['canal']??'manual'; $out=['id'=>$agenda->create($input,'humano:'.($user['nombre']??'usuario'))]; break;
        case 'block': $out=['id'=>$agenda->createBlock($input,'humano:'.($user['nombre']??'usuario'))]; break;
        case 'status': $agenda->changeStatus((int)($input['id']??0),(string)($input['status']??''),'humano:'.($user['nombre']??'usuario')); $out=[]; break;
        default: throw new InvalidArgumentException('Acción no válida.');
    }
    echo json_encode(['success'=>true]+$out);
} catch(Throwable $e) { http_response_code(422); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
