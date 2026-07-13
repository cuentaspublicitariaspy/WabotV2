<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/MetricsCollector.php';
requireLogin();
header('Content-Type: application/json');
$user = getUsuarioActual();
$usuarioId = in_array($user['rol'], ['super_admin', 'admin']) ? null : (int)$user['id'];
$metrics = new MetricsCollector();
echo json_encode($metrics->getMetricasGlobales($usuarioId));
