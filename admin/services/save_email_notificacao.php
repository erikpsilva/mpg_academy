<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$acao  = trim($_POST['acao']  ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$nome  = trim($_POST['nome']  ?? '');
$id    = (int) ($_POST['id']  ?? 0);

$pdo = getDbConnection();

if ($acao === 'add') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
        exit;
    }
    try {
        $st = $pdo->prepare("INSERT INTO emails_notificacao (email, nome) VALUES (?, ?)");
        $st->execute([$email, $nome ?: null]);
        $newId = (int) $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $newId, 'email' => $email, 'nome' => $nome]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'E-mail já cadastrado.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar.']);
        }
    }
    exit;
}

if ($acao === 'remove') {
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }
    $st = $pdo->prepare("DELETE FROM emails_notificacao WHERE id = ?");
    $st->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
