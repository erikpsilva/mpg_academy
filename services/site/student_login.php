<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';

validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$email = trim($_POST['email'] ?? '');
$senha = trim($_POST['senha'] ?? '');

if (empty($email) || empty($senha)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail e senha são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

$pdo = getDbConnection();

// Um mesmo e-mail pode ter MAIS DE UM aluno: menor de idade usa o e-mail do responsável, e
// o responsável às vezes também é aluno (pai que joga e matricula o filho). Por isso não dá
// pra usar LIMIT 1 — é a senha que diz quem está entrando.
$stmt = $pdo->prepare("SELECT id, nome, email, foto, status, senha, is_menor FROM alunos WHERE email = ?");
$stmt->execute([$email]);
$candidatos = $stmt->fetchAll();

// Confere a senha contra cada cadastro daquele e-mail.
$compativeis = array_values(array_filter(
    $candidatos,
    fn($a) => password_verify($senha, $a['senha'])
));

if (empty($compativeis)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos.']);
    exit;
}

// Pai e filho com a MESMA senha — aí só quem está na frente da tela sabe quem é.
if (count($compativeis) > 1 && empty($_POST['aluno_id'])) {
    http_response_code(200);
    echo json_encode([
        'success'         => false,
        'escolher_perfil' => true,
        'message'         => 'Existe mais de um cadastro com esse e-mail. Escolha quem está entrando.',
        'perfis'          => array_map(fn($a) => [
            'id'       => (int) $a['id'],
            'nome'     => $a['nome'],
            'foto'     => $a['foto'],
            'is_menor' => (bool) $a['is_menor'],
        ], $compativeis),
    ]);
    exit;
}

$aluno = $compativeis[0];

// Perfil escolhido na tela — só aceita um dos que a senha já validou, pra ninguém entrar
// em outro cadastro só mandando um id qualquer.
if (!empty($_POST['aluno_id'])) {
    $escolhido = (int) $_POST['aluno_id'];
    $achou = null;
    foreach ($compativeis as $c) {
        if ((int) $c['id'] === $escolhido) { $achou = $c; break; }
    }
    if (!$achou) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'E-mail ou senha incorretos.']);
        exit;
    }
    $aluno = $achou;
}

if ($aluno['status'] !== 'ativo') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sua conta está inativa. Entre em contato com a MPG Academy.']);
    exit;
}

unset($aluno['senha'], $aluno['is_menor']);

$_SESSION['aluno'] = $aluno;

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Login realizado com sucesso.',
]);
