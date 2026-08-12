<?php

/**
 * Busca de aluno pro formulário de pedido manual de uniforme (admin/pedirfuniforme).
 * Diferente de search_alunos_disponiveis.php (que busca quem NÃO está numa turma), aqui
 * é o contrário: só interessa aluno ativo COM turma, porque é a turma que define o balde
 * de numeração do uniforme.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$busca = trim($_GET['busca'] ?? '');

if (strlen($busca) < 2) {
    echo json_encode(['success' => true, 'alunos' => []]);
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT DISTINCT a.id, a.nome, a.email, a.sexo
    FROM alunos a
    JOIN turma_alunos ta ON ta.aluno_id = a.id AND ta.status = 'ativo'
    JOIN turmas t ON t.id = ta.turma_id AND t.status = 'ativa'
    WHERE a.status = 'ativo'
      AND (a.nome LIKE ? OR a.email LIKE ?)
    ORDER BY a.nome
    LIMIT 8
");
$stmt->execute(["%$busca%", "%$busca%"]);

echo json_encode(['success' => true, 'alunos' => $stmt->fetchAll()]);
