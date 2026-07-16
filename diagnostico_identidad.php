<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/ProspectManager.php';

requireLogin();
$user = getUsuarioActual();
if (!in_array((string)($user['rol'] ?? ''), ['super_admin', 'admin'], true)) {
    http_response_code(403);
    exit('Acceso restringido.');
}

header('Content-Type: text/html; charset=utf-8');
$db = Database::getConnection();
$prospectManager = new ProspectManager();
$errors = [];

function diagRows(PDO $db, string $sql, array &$errors, string $label): array
{
    try {
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = $label . ': ' . $e->getMessage();
        return [];
    }
}
function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function genericName(string $name): bool
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    return $name === '' || in_array($name, ['visitante web', 'sin nombre', 'unknown'], true);
}

$chats = diagRows($db, "SELECT id, visitor_name, visitor_email, visitor_phone, updated_at
    FROM widget_chats
    WHERE visitor_phone <> '' OR visitor_email <> ''
    ORDER BY updated_at DESC, id DESC LIMIT 100", $errors, 'widget_chats');

$appointments = diagRows($db, "SELECT id, prospecto_id, nombre_cliente, telefono, email, estado, inicio, updated_at
    FROM citas
    ORDER BY updated_at DESC, id DESC LIMIT 500", $errors, 'citas');

$prospects = diagRows($db, "SELECT id, nombre, whatsapp, email, updated_at
    FROM prospectos
    ORDER BY updated_at DESC, id DESC LIMIT 500", $errors, 'prospectos');

$appointmentByPhone = [];
$appointmentByEmail = [];
foreach ($appointments as $appointment) {
    if (in_array((string)$appointment['estado'], ['cancelada_cliente', 'cancelada_negocio'], true)) continue;
    $phone = $prospectManager->normalizarTelefono((string)$appointment['telefono']);
    $email = strtolower(trim((string)$appointment['email']));
    if ($phone !== '' && !isset($appointmentByPhone[$phone])) $appointmentByPhone[$phone] = $appointment;
    if ($email !== '' && !isset($appointmentByEmail[$email])) $appointmentByEmail[$email] = $appointment;
}

$prospectByPhone = [];
$prospectByEmail = [];
foreach ($prospects as $prospect) {
    $phone = $prospectManager->normalizarTelefono((string)$prospect['whatsapp']);
    $email = strtolower(trim((string)$prospect['email']));
    if ($phone !== '' && !isset($prospectByPhone[$phone])) $prospectByPhone[$phone] = $prospect;
    if ($email !== '' && !isset($prospectByEmail[$email])) $prospectByEmail[$email] = $prospect;
}

$report = [];
foreach ($chats as $chat) {
    $phone = $prospectManager->normalizarTelefono((string)$chat['visitor_phone']);
    $email = strtolower(trim((string)$chat['visitor_email']));
    $appointment = $phone !== '' ? ($appointmentByPhone[$phone] ?? null) : null;
    if (!$appointment && $email !== '') $appointment = $appointmentByEmail[$email] ?? null;
    $prospect = $phone !== '' ? ($prospectByPhone[$phone] ?? null) : null;
    if (!$prospect && $email !== '') $prospect = $prospectByEmail[$email] ?? null;

    $expected = '';
    $source = 'sin coincidencia';
    if ($prospect && !genericName((string)$prospect['nombre'])) {
        $expected = (string)$prospect['nombre'];
        $source = 'prospecto #' . $prospect['id'];
    } elseif ($appointment && !genericName((string)$appointment['nombre_cliente'])) {
        $expected = (string)$appointment['nombre_cliente'];
        $source = 'cita #' . $appointment['id'];
    }

    $report[] = [
        'chat_id' => $chat['id'],
        'actual' => $chat['visitor_name'],
        'telefono_original' => $chat['visitor_phone'],
        'telefono_canonico' => $phone,
        'email' => $chat['visitor_email'],
        'prospecto' => $prospect ? ('#' . $prospect['id'] . ' · ' . $prospect['nombre'] . ' · ' . $prospect['whatsapp']) : '—',
        'cita' => $appointment ? ('#' . $appointment['id'] . ' · ' . $appointment['nombre_cliente'] . ' · ' . $appointment['telefono'] . ' · ' . $appointment['estado']) : '—',
        'esperado' => $expected,
        'fuente' => $source,
        'resultado' => $expected === '' ? 'Sin identidad encontrada' : (trim((string)$chat['visitor_name']) === $expected ? 'Correcto' : 'Debe actualizarse'),
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnóstico de identidad - Wabot</title>
<style>
body{font-family:Inter,system-ui,sans-serif;margin:0;background:#f8fafc;color:#0f172a}main{max-width:1500px;margin:auto;padding:24px}h1{font-size:24px}.card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;margin:16px 0}.error{background:#fff1f2;border-color:#fecdd3;color:#9f1239}.ok{color:#047857;font-weight:700}.bad{color:#b45309;font-weight:700}.table{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1200px}th,td{text-align:left;padding:10px;border-bottom:1px solid #e2e8f0;font-size:13px;vertical-align:top}th{background:#f1f5f9}code{font-size:12px}a{color:#047857}
</style>
</head>
<body><main>
<h1>Diagnóstico de identidad</h1>
<p>Lectura administrativa. No modifica datos ni inspecciona mensajes.</p>
<p><a href="conversaciones.php">← Volver a Conversaciones</a></p>
<?php if ($errors): ?><div class="card error"><strong>Errores reales detectados</strong><ul><?php foreach ($errors as $error): ?><li><?=h($error)?></li><?php endforeach ?></ul></div><?php endif ?>
<div class="card"><strong>Resumen:</strong> <?=count($chats)?> chats con contacto · <?=count($prospects)?> prospectos revisados · <?=count($appointments)?> citas revisadas.</div>
<div class="card table"><table>
<thead><tr><th>Chat</th><th>Nombre actual</th><th>Teléfono recibido</th><th>Canónico</th><th>Prospecto coincidente</th><th>Cita coincidente</th><th>Nombre esperado</th><th>Fuente</th><th>Resultado</th></tr></thead>
<tbody>
<?php foreach ($report as $row): ?>
<tr>
<td>#<?=h($row['chat_id'])?></td>
<td><?=h($row['actual'])?></td>
<td><code><?=h($row['telefono_original'])?></code></td>
<td><code><?=h($row['telefono_canonico'])?></code></td>
<td><?=h($row['prospecto'])?></td>
<td><?=h($row['cita'])?></td>
<td><?=h($row['esperado'])?></td>
<td><?=h($row['fuente'])?></td>
<td class="<?=$row['resultado']==='Correcto'?'ok':'bad'?>"><?=h($row['resultado'])?></td>
</tr>
<?php endforeach ?>
<?php if (!$report): ?><tr><td colspan="9">No hay chats con teléfono o correo para diagnosticar.</td></tr><?php endif ?>
</tbody></table></div>
</main></body></html>
