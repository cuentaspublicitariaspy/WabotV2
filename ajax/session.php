<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/AgentRouter.php';
requireLogin();
header('Content-Type: application/json');
$router = new AgentRouter();
$router->ping((int)$_SESSION[ADMIN_USER_ID_KEY]);
echo json_encode(['status'=>'ok']);
