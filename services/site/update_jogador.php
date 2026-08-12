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

if (empty($_SESSION['jogador']['id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo       = getDbConnection();
$jogadorId = (int) $_SESSION['jogador']['id'];

$nome     = trim($_POST['nome'] ?? '');
$celular  = trim($_POST['celular'] ?? '');
$email    = trim($_POST['email'] ?? '');
$alturaCm = (int) ($_POST['altura_cm'] ?? 0);
$sexo     = trim($_POST['sexo'] ?? '');
$senha    = $_POST['senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

if ($nome === '' || $celular === '' || $email === '' || $alturaCm <= 0 || $sexo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
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

if ($alturaCm < 100 || $alturaCm > 250) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Altura inválida.']);
    exit;
}

if ($senha !== '' && strlen($senha) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A nova senha precisa ter pelo menos 6 caracteres.']);
    exit;
}

if ($senha !== '' && $senha !== $confirmarSenha) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
    exit;
}

try {
    $check = $pdo->prepare("SELECT id FROM jogadores_batebola WHERE email = ? AND id != ? LIMIT 1");
    $check->execute([$email, $jogadorId]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Este e-mail já está em uso por outro jogador.']);
        exit;
    }

    // Upload de nova foto (opcional)
    $fotoPath = null;
    if (!empty($_FILES['foto']['tmp_name'])) {
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
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $filename)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar a foto.']);
            exit;
        }
        $fotoPath = 'images/jogadores/' . $filename;
    }

    if ($senha !== '') {
        $senhaHash = password_hash($senha, PASSWORD_BCRYPT);
        $sql = "UPDATE jogadores_batebola SET nome = ?, celular = ?, email = ?, altura_cm = ?, sexo = ?, senha = ?"
             . ($fotoPath ? ", foto = ?" : "") . ", atualizado_em = NOW() WHERE id = ?";
        $params = [$nome, $celular, $email, $alturaCm, $sexo, $senhaHash];
    } else {
        $sql = "UPDATE jogadores_batebola SET nome = ?, celular = ?, email = ?, altura_cm = ?, sexo = ?"
             . ($fotoPath ? ", foto = ?" : "") . ", atualizado_em = NOW() WHERE id = ?";
        $params = [$nome, $celular, $email, $alturaCm, $sexo];
    }
    if ($fotoPath) $params[] = $fotoPath;
    $params[] = $jogadorId;

    $pdo->prepare($sql)->execute($params);

    $_SESSION['jogador']['nome']    = $nome;
    $_SESSION['jogador']['celular'] = $celular;
    $_SESSION['jogador']['email']   = $email;
    $_SESSION['jogador']['altura_cm'] = $alturaCm;
    $_SESSION['jogador']['sexo']    = $sexo;
    if ($fotoPath) $_SESSION['jogador']['foto'] = $fotoPath;

    echo json_encode(['success' => true, 'message' => 'Dados atualizados com sucesso!']);
} catch (Throwable $e) {
    error_log('[batebola-update-jogador] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
}
