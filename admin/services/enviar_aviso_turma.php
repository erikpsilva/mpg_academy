<?php

/**
 * Envia o aviso de WhatsApp para UM destinatário.
 *
 * Um por requisição de propósito: a tela chama este serviço em sequência e vai marcando
 * quem recebeu. Mandar os trinta numa requisição só significaria uma tela parada por mais de
 * um minuto, sem saber onde parou — e, quando o servidor cortasse por timeout, ninguém
 * saberia quem já tinha recebido e quem não.
 *
 * O texto e a imagem vêm a cada chamada (a tela repete), então não existe estado no servidor
 * entre um envio e outro.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (empty($_SESSION['usuario']) || !in_array($nivel, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/services/whatsapp/zapi.php';

$telefone = preg_replace('/\D/', '', $_POST['telefone'] ?? '');
$texto    = trim($_POST['texto'] ?? '');
$imagem   = trim($_POST['imagem'] ?? '');

if ($telefone === '' || strlen($telefone) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número inválido.']);
    exit;
}

if ($texto === '' && $imagem === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Escreva a mensagem ou escolha uma imagem.']);
    exit;
}

// A imagem precisa ser um arquivo nosso: a Z-API busca a URL que mandarmos, então aceitar
// endereço livre aqui transformaria o sistema em mensageiro de qualquer link.
if ($imagem !== '') {
    $base = rtrim(appBaseUrl(), '/') . '/uploads/avisos/';
    if (strpos($imagem, $base) !== 0 || !preg_match('/^[\w.\-]+\.(jpg|jpeg|png|webp)$/i', basename($imagem))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Imagem inválida — envie o arquivo pelo formulário.']);
        exit;
    }
}

// Com imagem, o texto vai como legenda — chega tudo numa mensagem só, do jeito que a pessoa
// lê melhor. Sem imagem, é mensagem de texto comum.
$resultado = $imagem !== ''
    ? sendWhatsAppImage($telefone, $imagem, $texto)
    : ['ok' => sendWhatsApp($telefone, $texto)];

if (empty($resultado['ok'])) {
    echo json_encode([
        'success' => false,
        'message' => $resultado['erro'] ?? 'A Z-API recusou o envio.',
    ]);
    exit;
}

echo json_encode(['success' => true]);
