<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/MetricsCollector.php';
requireLogin();
header('Content-Type: application/json');
$user = getUsuarioActual();
$usuarioId = $user['rol'] === 'admin' ? null : (int)$user['id'];
$metrics = new MetricsCollector();
echo json_encode($metrics->getMetricasGlobales($usuarioId));
