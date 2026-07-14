<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ChatManager.php';
require_once __DIR__ . '/../includes/ProspectManager.php';
requireLogin();
header('Content-Type: application/json');

$canal = ($_REQUEST['canal'] ?? '') === 'chatbot' ? 'chatbot' : 'whatsapp';
$id = (int) ($_REQUEST['conversacion_id'] ?? 0);
if ($id < 1) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Conversación inválida']); exit; }

$chat = new ChatManager();
$prospectos = new ProspectManager();
$conversacion = $canal === 'chatbot' ? $chat->getWidgetConversacion($id) : $chat->getConversacion($id);
if (!$conversacion) { http_response_code(404); echo json_encode(['success'=>false,'error'=>'Conversación no encontrada']); exit; }
$referencia = $canal === 'chatbot' ? (string)$id : preg_replace('/\D+/', '', (string)$conversacion['wa_phone']);
$prospectoId = $prospectos->vincular($canal, $referencia, [
    'nombre' => $conversacion['wa_name'] ?? '',
    'whatsapp' => $canal === 'whatsapp' ? $conversacion['wa_phone'] : '',
]);

if (($_REQUEST['action'] ?? '') !== 'analizar') {
    echo json_encode(['success'=>true, 'prospecto'=>$prospectos->obtenerPorReferencia($canal, $referencia)]); exit;
}

$mensajes = $canal === 'chatbot' ? $chat->getWidgetMensajes($id) : $chat->getMensajes($id);
$transcripcion = [];
foreach ($mensajes as $mensaje) {
    $rol = ($mensaje['direccion'] ?? '') === 'in' ? 'Visitante' : 'Asistente';
    $transcripcion[] = $rol . ': ' . trim((string)($mensaje['contenido'] ?? ''));
}
if (!$transcripcion) { http_response_code(422); echo json_encode(['success'=>false,'error'=>'No hay mensajes para analizar']); exit; }

$instruccion = 'Analizá esta conversación comercial. Extraé solo datos que la persona haya dado explícitamente. Respondé ÚNICAMENTE JSON válido con estas claves: nombre, email, whatsapp, direccion, ciudad, pais, sitio_web, ocupacion, empresa, resumen, intencion, nivel_interes, temperatura, puntaje. resumen debe ser breve y decir qué busca. intencion debe describir el objetivo de compra o consulta. nivel_interes: bajo, medio o alto. temperatura: frio, tibio, caliente o muy_caliente. puntaje entero 0 a 100. Para datos desconocidos usá cadena vacía. No inventes datos.';
$payload = json_encode(['action'=>'chat', 'license_key'=>LICENSE_KEY, 'messages'=>[
    ['role'=>'system','content'=>$instruccion],
    ['role'=>'user','content'=>implode("\n", $transcripcion)],
]], JSON_UNESCAPED_UNICODE);
$ch = curl_init('https://wabot-cdn.vercel.app/api/proxy/openai');
curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30]);
$respuesta = curl_exec($ch); $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$contenido = json_decode((string)$respuesta, true)['content'] ?? '';
$contenido = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim((string)$contenido));
$analisis = json_decode($contenido, true);
if ($codigo !== 200 || !is_array($analisis)) { http_response_code(502); echo json_encode(['success'=>false,'error'=>'No se pudo generar un análisis válido']); exit; }
$prospectos->guardarAnalisis($prospectoId, $analisis);
echo json_encode(['success'=>true, 'prospecto'=>$prospectos->obtenerPorReferencia($canal, $referencia)]);
