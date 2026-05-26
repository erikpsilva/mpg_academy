<?php

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';

validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$required = ['nome', 'email', 'cpf', 'nascimento', 'sexo', 'celular', 'whatsapp', 'cep', 'rua', 'numero', 'bairro', 'cidade', 'estado', 'senha'];

foreach ($required as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }
}

$nome      = trim($_POST['nome']);
$email     = trim($_POST['email']);
$cpf       = preg_replace('/[^\d]/', '', $_POST['cpf']);
$nascimento = $_POST['nascimento']; // DD/MM/AAAA
$sexo      = trim($_POST['sexo']);
$celular   = trim($_POST['celular']);
$whatsapp  = trim($_POST['whatsapp']);
$cep       = trim($_POST['cep']);
$rua       = trim($_POST['rua']);
$numero    = trim($_POST['numero']);
$bairro    = trim($_POST['bairro']);
$complemento = trim($_POST['complemento'] ?? '');
$cidade    = trim($_POST['cidade']);
$estado    = trim($_POST['estado']);
$senha     = $_POST['senha'];
$origem    = trim($_POST['origem'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

if (strlen($cpf) !== 11) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CPF inválido.']);
    exit;
}

if (strlen($senha) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha precisa ter pelo menos 8 caracteres.']);
    exit;
}

// Converter data DD/MM/AAAA → AAAA-MM-DD
$dateParts = explode('/', $nascimento);
if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[0], (int)$dateParts[2])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data de nascimento inválida.']);
    exit;
}
$nascimentoDb = sprintf('%04d-%02d-%02d', $dateParts[2], $dateParts[1], $dateParts[0]);

$pdo = getDbConnection();

$check = $pdo->prepare("SELECT id FROM alunos WHERE email = ? OR cpf = ? LIMIT 1");
$check->execute([$email, $cpf]);
if ($check->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Este e-mail ou CPF já está cadastrado.']);
    exit;
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
    $filename = 'aluno_' . uniqid() . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar a foto.']);
        exit;
    }

    $fotoPath = 'images/alunos/' . $filename;
}

$senhaHash = password_hash($senha, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("
    INSERT INTO alunos
        (nome, email, cpf, nascimento, sexo, celular, whatsapp, cep, rua, numero, bairro, complemento, cidade, estado, foto, senha, origem, status)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
");

$stmt->execute([
    $nome,
    $email,
    $cpf,
    $nascimentoDb,
    $sexo,
    $celular,
    $whatsapp,
    $cep,
    $rua,
    $numero,
    $bairro,
    $complemento ?: null,
    $cidade,
    $estado,
    $fotoPath,
    $senhaHash,
    $origem ?: null,
]);

http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => 'Cadastro realizado com sucesso! Faça login para acessar sua área.',
]);
