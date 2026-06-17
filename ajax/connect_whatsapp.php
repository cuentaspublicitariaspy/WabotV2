<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Database.php';
requireLogin();
header('Content-Type: application/json');

$user = getUsuarioActual();
$db = Database::getConnection();
$userId = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true);
$accessToken = $input['access_token'] ?? '';

if (!$accessToken) {
    echo json_encode(['success' => false, 'error' => 'Access token no recibido']);
    exit;
}

// Validate token and get phone number ID from Meta Graph API
$graphUrl = "https://graph.facebook.com/v18.0/me?fields=id,name&access_token=" . urlencode($accessToken);
$ch = curl_init($graphUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'Token inválido o expirado en Meta']);
    exit;
}

$me = json_decode($resp, true);
$fbUserId = $me['id'] ?? '';
$fbName = $me['name'] ?? '';

// Get WABA IDs associated with this user
$wabaUrl = "https://graph.facebook.com/v18.0/" . $fbUserId . "/whatsapp_business_accounts?access_token=" . urlencode($accessToken);
$ch = curl_init($wabaUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$wabaResp = curl_exec($ch);
$wabaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$phoneNumberId = '';
$wabaId = '';

if ($wabaHttpCode === 200) {
    $wabaData = json_decode($wabaResp, true);
    if (!empty($wabaData['data'][0])) {
        $wabaId = $wabaData['data'][0]['id'] ?? '';
        // Get phone numbers from WABA
        if ($wabaId) {
            $phoneUrl = "https://graph.facebook.com/v18.0/" . $wabaId . "/phone_numbers?access_token=" . urlencode($accessToken);
            $ch = curl_init($phoneUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $phoneResp = curl_exec($ch);
            curl_close($ch);
            $phoneData = json_decode($phoneResp, true);
            if (!empty($phoneData['data'][0])) {
                $phoneNumberId = $phoneData['data'][0]['id'] ?? '';
            }
        }
    }
}

// Store in clientes table
$verifyToken = 'wabotv2_verify_' . bin2hex(random_bytes(8));
$stmt = $db->prepare("SELECT id FROM clientes WHERE id = (SELECT cliente_id FROM usuarios WHERE id = ?)");
$stmt->execute([$userId]);
$clienteId = $stmt->fetchColumn();

if ($clienteId) {
    $stmt = $db->prepare("UPDATE clientes SET whatsapp_token = ?, whatsapp_phone_number_id = ?, whatsapp_business_account_id = ?, whatsapp_verify_token = ?, nombre = ? WHERE id = ?");
    $stmt->execute([$accessToken, $phoneNumberId, $wabaId, $verifyToken, $fbName, $clienteId]);
} else {
    $stmt = $db->prepare("INSERT INTO clientes (nombre, whatsapp_token, whatsapp_phone_number_id, whatsapp_business_account_id, whatsapp_verify_token) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$fbName, $accessToken, $phoneNumberId, $wabaId, $verifyToken]);
    $clienteId = (int)$db->lastInsertId();
    $db->prepare("UPDATE usuarios SET cliente_id = ? WHERE id = ?")->execute([$clienteId, $userId]);
}

echo json_encode([
    'success' => true,
    'phone_number_id' => $phoneNumberId,
    'waba_id' => $wabaId,
    'verify_token' => $verifyToken,
    'connected' => $phoneNumberId ? true : false,
]);
