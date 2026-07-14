<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/MetricsCollector.php';
require_once __DIR__ . '/../includes/ProspectManager.php';
requireLogin();
header('Content-Type: application/json');
$user = getUsuarioActual();
$usuarioId = in_array($user['rol'], ['super_admin', 'admin']) ? null : (int)$user['id'];
$metrics = new MetricsCollector();
$data = $metrics->getMetricasGlobales($usuarioId);
$data['prospectos'] = (new ProspectManager())->metricas();
echo json_encode($data);
