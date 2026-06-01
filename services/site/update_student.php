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

if (empty($_SESSION['aluno']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$id       = (int) $_SESSION['aluno']['id'];
$email    = trim($_POST['email'] ?? '');
$sexo     = trim($_POST['sexo'] ?? '');
$celular  = trim($_POST['celular'] ?? '');
$cep      = trim($_POST['cep'] ?? '');
$rua      = trim($_POST['rua'] ?? '');
$numero   = trim($_POST['numero'] ?? '');
$bairro   = trim($_POST['bairro'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');
$cidade   = trim($_POST['cidade'] ?? '');
$estado   = trim($_POST['estado'] ?? '');
$senha    = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmar_senha'] ?? '';

// Validações obrigatórias
$required = compact('email', 'sexo', 'celular', 'cep', 'rua', 'numero', 'bairro', 'cidade', 'estado');
foreach ($required as $field => $value) {
    if (empty($value)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

$pdo = getDbConnection();

// Verificar se o email já está em uso por outro aluno
$check = $pdo->prepare("SELECT id FROM alunos WHERE email = ? AND id != ? LIMIT 1");
$check->execute([$email, $id]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este e-mail já está sendo usado por outra conta.']);
    exit;
}

// Validação de senha (opcional — só valida se preenchida)
$senhaHash = null;
if (!empty($senha) || !empty($confirmar)) {
    if (strlen($senha) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A nova senha precisa ter pelo menos 8 caracteres.']);
        exit;
    }
    if ($senha !== $confirmar) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
        exit;
    }
    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
}

// Upload de foto (opcional)
$fotoPath = null;
if (!empty($_FILES['foto']['tmp_name'])) {
    $uploadDir = dirname(__FILE__, 3) . '/images/alunos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['foto']['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de foto inválido. Use JPG, PNG ou WebP.']);
        exit;
    }

    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
    $filename = 'aluno_' . $id . '_' . uniqid() . '.' . $ext;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $filename)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar a foto.']);
        exit;
    }

    $fotoPath = 'images/alunos/' . $filename;
}

// Montar query dinamicamente
$fields = [
    'email'       => $email,
    'sexo'        => $sexo,
    'celular'     => $celular,
    'cep'         => $cep,
    'rua'         => $rua,
    'numero'      => $numero,
    'bairro'      => $bairro,
    'complemento' => $complemento ?: null,
    'cidade'      => $cidade,
    'estado'      => $estado,
];

if ($senhaHash) {
    $fields['senha'] = $senhaHash;
}

if ($fotoPath) {
    $fields['foto'] = $fotoPath;
}

$sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
$values = array_values($fields);
$values[] = $id;

$stmt = $pdo->prepare("UPDATE alunos SET $sets WHERE id = ?");
$stmt->execute($values);

// Buscar dados atualizados e renovar sessão
$refresh = $pdo->prepare("SELECT id, nome, email, foto, status FROM alunos WHERE id = ? LIMIT 1");
$refresh->execute([$id]);
$_SESSION['aluno'] = $refresh->fetch();

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Perfil atualizado com sucesso!',
    'foto'    => $fotoPath ? (defined('BASE_URL') ? '' : '') : null,
]);
