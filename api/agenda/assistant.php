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

/**
 * Cuando la instalación tiene una sola agenda o un solo servicio activos,
 * no obligamos a la IA a repetir IDs que ya son inequívocos. Si hay varias
 * opciones, no se elige ninguna a ciegas: el resultado devuelve el catálogo.
 */
function resolveUnambiguousAgendaSelection(AppointmentManager $agenda, array $input): array {
    $services=array_values(array_filter($agenda->list('servicios'),static fn($item)=>!empty($item['activo'])));
    $agendas=array_values(array_filter($agenda->list('agendas'),static fn($item)=>!empty($item['activo'])));
    // Una cita existente ya determina sin ambigüedad su agenda y servicio.
    // Es esencial para consultar alternativas o reagendar sin pedir de nuevo
    // información que WC ya conoce.
    if(!empty($input['cita_id'])){
        $context=$agenda->appointmentContext((int)$input['cita_id']);
        if($context){
            if(empty($input['agenda_id']))$input['agenda_id']=(int)$context['agenda_id'];
            if(empty($input['servicio_id']))$input['servicio_id']=(int)$context['servicio_id'];
        }
    }
    // Un servicio ya identifica de forma determinística su agenda.
    if(!empty($input['servicio_id']) && empty($input['agenda_id'])){
        foreach($services as $service){
            if((int)$service['id']===(int)$input['servicio_id']){$input['agenda_id']=(int)$service['agenda_id'];break;}
        }
    }
    // Si primero se eligió una agenda (por ejemplo "David"), solo se
    // consideran los servicios que realmente pertenecen a ese recurso.
    if(!empty($input['agenda_id']) && empty($input['servicio_id'])){
        $own=array_values(array_filter($services,static fn($service)=>(int)$service['agenda_id']===(int)$input['agenda_id']));
        if(count($own)===1)$input['servicio_id']=(int)$own[0]['id'];
    }
    if(empty($input['servicio_id']) && count($services)===1){
        $input['servicio_id']=(int)$services[0]['id'];
        $input['agenda_id']=(int)$services[0]['agenda_id'];
    }
    if(empty($input['agenda_id']) && count($agendas)===1)$input['agenda_id']=(int)$agendas[0]['id'];
    // Nunca se consulta una pareja agenda/servicio incoherente.
    if(!empty($input['servicio_id']) && !empty($input['agenda_id'])){
        $valid=false;
        foreach($services as $service){
            if((int)$service['id']===(int)$input['servicio_id'] && (int)$service['agenda_id']===(int)$input['agenda_id']){$valid=true;break;}
        }
        if(!$valid)unset($input['servicio_id']);
    }
    $input['_selection']=['servicios'=>$services,'agendas'=>$agendas];
    return $input;
}
try {
    if($action==='catalogo'){
        echo json_encode([
            'success'=>true,
            'servicios'=>$agenda->list('servicios'),
            'agendas'=>$agenda->list('agendas'),
            'sucursales'=>$agenda->list('sucursales'),
            'reglas'=>$agenda->settings(),
            'instruccion'=>'Relacioná el nombre pedido por la persona con una agenda y elegí solamente servicios asociados a esa agenda. Si tiene un solo servicio activo, continuá sin pedir que lo confirme.'
        ]);exit;
    }
    if($action==='disponibilidad'){
        $input=resolveUnambiguousAgendaSelection($agenda,$input);
        echo json_encode([
            'success'=>true,
            'slots'=>$agenda->availability($input),
            'consulta_finalizada'=>true,
            'instruccion'=>'La consulta ya terminó. Respondé ahora con estos horarios reales y no vuelvas a llamar la herramienta de agenda en este turno. Mencioná la fecha exacta; usá “mañana” solamente si coincide realmente con el día siguiente en America/Asuncion.'
        ]);exit;
    }
    if($action==='proximos_horarios'){
        $input=resolveUnambiguousAgendaSelection($agenda,$input);
        if(empty($input['servicio_id']) || empty($input['agenda_id'])){
            echo json_encode(['success'=>false,'error'=>'Hay más de una opción para reservar. Pedí que elija servicio o agenda.','servicios'=>$input['_selection']['servicios'],'agendas'=>$input['_selection']['agendas']]);exit;
        }
        if(empty($input['fecha_desde']))$input['fecha_desde']=date('Y-m-d');
        if(empty($input['dias']))$input['dias']=14;
        echo json_encode([
            'success'=>true,
            'opciones'=>$agenda->nextAvailability($input),
            'agenda_id'=>(int)$input['agenda_id'],
            'servicio_id'=>(int)$input['servicio_id'],
            'consulta_finalizada'=>true,
            'instruccion'=>'La consulta ya terminó. Respondé ahora usando exclusivamente estas opciones y como máximo dos alternativas. No vuelvas a llamar la herramienta de agenda en este turno. Mencioná la fecha exacta; usá “mañana” solamente si coincide realmente con el día siguiente en America/Asuncion. No repitas frases prefabricadas como “Lamentablemente, no tengo disponibilidad”. Si no hay opciones, explicá con naturalidad qué franja se consultó y ofrecé buscar otra fecha. Si la persona cuestiona el resultado, volvé a consultar antes de repetir la respuesta.'
        ]);exit;
    }
    if($action==='citas_cliente'){
        echo json_encode(['success'=>true,'citas'=>$agenda->findUpcoming($input)]);exit;
    }
    if($action==='crear'){
        // WS llama esta acción cuando la intención conversacional autoriza la
        // reserva. WC vuelve
        // a validar disponibilidad y evita doble reserva dentro de la transacción.
        if(empty($input['confirmada']))throw new RuntimeException('La reserva todavía no fue autorizada por la intención del cliente.');
        $input=resolveUnambiguousAgendaSelection($agenda,$input);
        if(empty($input['servicio_id']) || empty($input['agenda_id']))throw new RuntimeException('Hace falta identificar la agenda y su servicio antes de reservar.');
        $data=$input; $data['canal']=in_array(($input['canal']??''),['whatsapp','chatbot'],true)?$input['canal']:'chatbot';
        echo json_encode(['success'=>true,'cita_id'=>$agenda->create($data,'IA:'.$data['canal']),'consulta_finalizada'=>true,'instruccion'=>'La reserva quedó registrada. Confirmala una sola vez con un mensaje humano, amable y sin frases prefabricadas.']);exit;
    }
    if($action==='estado'){
        $agenda->changeStatus((int)($input['cita_id']??0),(string)($input['estado']??''),'IA:'.($input['canal']??'chatbot'));
        echo json_encode(['success'=>true]);exit;
    }
    if($action==='reprogramar'){
        if(empty($input['confirmada']))throw new RuntimeException('La reprogramación todavía no fue autorizada por la intención del cliente.');
        $input=resolveUnambiguousAgendaSelection($agenda,$input);
        $agenda->reschedule((int)($input['cita_id']??0),$input,'IA:'.($input['canal']??'chatbot'));
        echo json_encode(['success'=>true,'consulta_finalizada'=>true,'instruccion'=>'La cita fue reprogramada. Informá la nueva fecha y hora con naturalidad y no vuelvas a pedir confirmación.']);exit;
    }
    if($action==='cancelar'){
        if(empty($input['confirmada']))throw new RuntimeException('La cancelación todavía no fue autorizada por la intención del cliente.');
        $agenda->changeStatus((int)($input['cita_id']??0),'cancelada_cliente','IA:'.($input['canal']??'chatbot'));
        echo json_encode(['success'=>true]);exit;
    }
    throw new InvalidArgumentException('Acción de agenda inválida.');
}catch(Throwable $e){http_response_code(422);echo json_encode(['success'=>false,'error'=>$e->getMessage()]);}
