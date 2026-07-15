<?php
require_once '../includes/auth.php';
require_once '../includes/ferias.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false]);
    exit;
}

requireLogin();

marcarTodasNotificacoesLidas($pdo, getCurrentUserId());

echo json_encode(['sucesso' => true]);
