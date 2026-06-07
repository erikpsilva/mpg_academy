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

$id        = (int) ($_POST['id'] ?? 0);
$titulo    = trim($_POST['titulo'] ?? '');
$conteudo  = $_POST['conteudo'] ?? '';
$imagem    = trim($_POST['imagem'] ?? '');
$tag       = trim($_POST['tag'] ?? '');
$destaque  = isset($_POST['destaque']) ? 1 : 0;
$publicado = isset($_POST['publicado']) ? 1 : 0;

if ($titulo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Título obrigatório.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$eraPublicado = false;
if ($id > 0) {
    // Verifica se já estava publicado antes de editar
    $stCheck = $pdo->prepare("SELECT publicado FROM comunicados WHERE id = ?");
    $stCheck->execute([$id]);
    $anterior = $stCheck->fetch(PDO::FETCH_ASSOC);
    $eraPublicado = $anterior && (int)$anterior['publicado'] === 1;

    $st = $pdo->prepare("
        UPDATE comunicados
        SET titulo = ?, conteudo = ?, imagem = ?, tag = ?, destaque = ?, publicado = ?
        WHERE id = ?
    ");
    $st->execute([$titulo, $conteudo, $imagem ?: null, $tag ?: null, $destaque, $publicado, $id]);
} else {
    $st = $pdo->prepare("
        INSERT INTO comunicados (titulo, conteudo, imagem, tag, destaque, publicado)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $st->execute([$titulo, $conteudo, $imagem ?: null, $tag ?: null, $destaque, $publicado]);
    $id = (int) $pdo->lastInsertId();
}

// Dispara WhatsApp apenas quando comunicado é publicado pela primeira vez
if ($publicado && !$eraPublicado) {
    require_once dirname(__FILE__, 3) . '/config/app.php';
    require_once dirname(__FILE__, 3) . '/services/whatsapp/zapi.php';

    $stAlunos = $pdo->query("
        SELECT a.nome, a.celular
        FROM alunos a
        WHERE a.status = 'ativo' AND a.celular IS NOT NULL AND a.celular != ''
    ");
    $alunos = $stAlunos->fetchAll(PDO::FETCH_ASSOC);

    $areaUrl = BASE_URL . '/aluno';
    foreach ($alunos as $aluno) {
        $nomePrimeiro = explode(' ', trim($aluno['nome']))[0];
        $msg  = "Olá, *{$nomePrimeiro}*! 📢\n\n";
        $msg .= "A *MPG Academy* tem uma novidade para você!\n\n";
        $msg .= "Acesse sua área do aluno para conferir:\n🔗 {$areaUrl}";
        sendWhatsApp(formatPhoneZapi($aluno['celular']), $msg);
    }
}

echo json_encode(['success' => true, 'id' => $id]);
