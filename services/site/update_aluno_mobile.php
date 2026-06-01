<?php
require_once __DIR__ . '/mobile_auth.php';
$aluno = mobileAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$campos = ['celular','whatsapp','cep','rua','numero','complemento','bairro','cidade','estado'];
$set    = [];
$vals   = [];

foreach ($campos as $c) {
    if (array_key_exists($c, $body)) {
        $set[]  = "`{$c}` = ?";
        $vals[] = trim($body[$c]);
    }
}

// E-mail com validação
if (!empty($body['email'])) {
    $email = strtolower(trim($body['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
        exit;
    }
    $set[]  = '`email` = ?';
    $vals[] = $email;
}

if (empty($set)) {
    echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar.']);
    exit;
}

$vals[] = $aluno['id'];
$pdo    = getDbConnection();
$stmt   = $pdo->prepare("UPDATE alunos SET " . implode(', ', $set) . " WHERE id = ?");
$stmt->execute($vals);

// Retorna dados atualizados
$stUp = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
$stUp->execute([$aluno['id']]);
$updated = $stUp->fetch(PDO::FETCH_ASSOC);
unset($updated['senha']);

echo json_encode(['success' => true, 'aluno' => $updated]);
