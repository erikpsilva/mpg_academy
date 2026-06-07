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

$aulaId    = (int)($_POST['aula_id']    ?? 0);
$adminId   = (int)($_POST['admin_id']   ?? 0);
$adminNome = trim($_POST['admin_nome']  ?? '');

if (!$aulaId || !$adminId || !$adminNome) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Verifica se já existe termo para esta aula
$stmt = $pdo->prepare("SELECT id, assinado_escola_em FROM termo_assinaturas WHERE aula_experimental_id = ? LIMIT 1");
$stmt->execute([$aulaId]);
$termo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$termo) {
    // Cria o registro do termo com token único
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("
        INSERT INTO termo_assinaturas (aula_experimental_id, token, assinante_escola_id, assinante_escola_nome, assinado_escola_em, assinado_escola_ip)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ")->execute([$aulaId, $token, $adminId, $adminNome, $ip]);
} else {
    if ($termo['assinado_escola_em']) {
        echo json_encode(['success' => false, 'message' => 'Esta aula já foi assinada pela escola.']);
        exit;
    }
    $pdo->prepare("
        UPDATE termo_assinaturas
        SET assinante_escola_id = ?, assinante_escola_nome = ?, assinado_escola_em = NOW(), assinado_escola_ip = ?
        WHERE aula_experimental_id = ?
    ")->execute([$adminId, $adminNome, $ip, $aulaId]);
}

echo json_encode(['success' => true]);
