<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/MetricsCollector.php';
requireLogin();
header('Content-Type: application/json');
$user = getUsuarioActual();
$rol = $user['rol'];
$usuarioId = in_array($rol, ['super_admin', 'admin']) ? null : (int)$user['id'];
$clienteId = $rol === 'super_admin' ? null : ($user['cliente_id'] ?? null);
$metrics = new MetricsCollector();
echo json_encode($metrics->getMetricasGlobales($usuarioId, $clienteId));
