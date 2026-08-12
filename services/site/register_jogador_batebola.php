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

$required = ['nome', 'celular', 'altura_cm', 'sexo', 'email', 'senha'];
foreach ($required as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }
}

if (empty($_FILES['foto']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Envie uma foto do rosto.']);
    exit;
}

$nome     = trim($_POST['nome']);
$celular  = trim($_POST['celular']);
$alturaCm = (int) $_POST['altura_cm'];
$sexo     = trim($_POST['sexo']);
$email    = trim($_POST['email']);
$senha    = $_POST['senha'];
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

if ($alturaCm < 100 || $alturaCm > 250) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Altura inválida.']);
    exit;
}

if (!in_array($sexo, ['masculino', 'feminino'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione o sexo.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
    exit;
}

if (strlen($senha) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A senha precisa ter pelo menos 6 caracteres.']);
    exit;
}

if ($senha !== $confirmarSenha) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}

$pdo = getDbConnection();

try {
    $check = $pdo->prepare("SELECT id FROM jogadores_batebola WHERE email = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
        exit;
    }

    // Upload da foto do rosto (obrigatória)
    $uploadDir = dirname(__FILE__, 3) . '/images/jogadores/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['foto']['tmp_name']);
        finfo_close($finfo);
    } else {
        $imageInfo = @getimagesize($_FILES['foto']['tmp_name']);
        $mimeType = $imageInfo['mime'] ?? null;
    }

    if (!in_array($mimeType, $allowedTypes, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato de foto inválido. Use JPG, PNG ou WebP.']);
        exit;
    }

    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeType];
    $filename = 'jogador_' . uniqid() . '.' . $ext;
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar a foto.']);
        exit;
    }

    $fotoPath = 'images/jogadores/' . $filename;

    $senhaHash = password_hash($senha, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        INSERT INTO jogadores_batebola (nome, email, celular, altura_cm, sexo, senha, foto)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$nome, $email, $celular, $alturaCm, $sexo, $senhaHash, $fotoPath]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Cadastro realizado com sucesso! Faça login para acessar sua área.',
    ]);
} catch (Throwable $e) {
    error_log('[batebola-cadastro] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao criar conta: ' . $e->getMessage()]);
}
