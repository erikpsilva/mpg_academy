<?php

/**
 * Upload da imagem de capa do comunicado.
 *
 * Este arquivo já respondeu 500 em produção sem dizer o motivo: o front faz r.json() na
 * resposta, então um fatal do PHP (que sai como HTML) virava só "Erro de comunicação" na
 * tela, sem pista nenhuma. Por isso aqui tudo é blindado pra SEMPRE sair JSON com a causa
 * real — mesmo em erro fatal — e o motivo técnico vai pro error_log do servidor.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$respondido = false;

/** Resposta única — evita mandar dois JSONs quando o shutdown roda depois de uma resposta. */
function responder(int $http, array $dados): void
{
    global $respondido;
    if ($respondido) return;
    $respondido = true;

    http_response_code($http);
    echo json_encode($dados);
}

// Erro fatal (função inexistente, extensão faltando, limite de memória) não pode chegar ao
// navegador como HTML — senão o front perde a mensagem e mostra um erro genérico.
register_shutdown_function(function () {
    $erro = error_get_last();
    if ($erro && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[upload-comunicado] FATAL: ' . $erro['message'] . ' em ' . $erro['file'] . ':' . $erro['line']);
        responder(500, [
            'success' => false,
            'message' => 'Erro interno ao processar a imagem. O administrador do sistema foi avisado nos logs.',
        ]);
    }
});

set_exception_handler(function (Throwable $e) {
    error_log('[upload-comunicado] ' . $e->getMessage());
    responder(500, ['success' => false, 'message' => 'Erro interno ao enviar a imagem.']);
});

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (empty($_SESSION['usuario'])) {
    responder(403, ['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/app.php';

// Arquivo maior que post_max_size faz o PHP descartar $_POST e $_FILES inteiros e seguir
// como se nada tivesse sido enviado. Sem tratar isso, o aluno do suporte fica procurando
// bug de permissão quando o problema é só o limite do servidor.
$tamanhoEnviado = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if (empty($_FILES) && $tamanhoEnviado > 0) {
    $limite = ini_get('post_max_size');
    responder(413, [
        'success' => false,
        'message' => 'A imagem é maior que o limite do servidor (' . $limite . '). Reduza o tamanho e tente de novo.',
    ]);
    exit;
}

if (empty($_FILES['imagem'])) {
    responder(400, ['success' => false, 'message' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['imagem'];

// Cada código de erro tem uma causa e uma saída diferente — devolver "nenhum arquivo" pra
// todos escondia justamente os dois que são problema de servidor (NO_TMP_DIR e CANT_WRITE).
if ($file['error'] !== UPLOAD_ERR_OK) {
    $motivos = [
        UPLOAD_ERR_INI_SIZE   => 'A imagem passa do limite do servidor (' . ini_get('upload_max_filesize') . '). Reduza o tamanho.',
        UPLOAD_ERR_FORM_SIZE  => 'A imagem passa do limite do formulário.',
        UPLOAD_ERR_PARTIAL    => 'O envio foi interrompido no meio. Tente de novo.',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
        UPLOAD_ERR_NO_TMP_DIR => 'O servidor está sem pasta temporária de upload. Precisa de ajuste na hospedagem.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo temporário. Precisa de ajuste na hospedagem.',
        UPLOAD_ERR_EXTENSION  => 'Uma extensão do PHP bloqueou o envio.',
    ];

    error_log('[upload-comunicado] UPLOAD_ERR ' . $file['error']);
    responder(400, [
        'success' => false,
        'message' => $motivos[$file['error']] ?? 'Falha no envio do arquivo (código ' . $file['error'] . ').',
    ]);
    exit;
}

$maxBytes = 5 * 1024 * 1024; // 5 MB

if ($file['size'] > $maxBytes) {
    responder(400, ['success' => false, 'message' => 'Imagem muito grande (máx 5 MB).']);
    exit;
}

// ── Tipo do arquivo ───────────────────────────────────────────────────────────
//
// Este era o único arquivo do projeto usando mime_content_type(); todo o resto usa
// finfo_open(). Em hospedagem compartilhada mime_content_type() às vezes cai no
// disable_functions, e aí a chamada vira erro fatal — 500 sem mensagem nenhuma.
// Agora tenta finfo, depois mime_content_type, e por último getimagesize().
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime    = null;

if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $file['tmp_name']) ?: null;
        finfo_close($finfo);
    }
}

if ($mime === null && function_exists('mime_content_type')) {
    $mime = mime_content_type($file['tmp_name']) ?: null;
}

if ($mime === null) {
    $info = @getimagesize($file['tmp_name']);
    $mime = $info['mime'] ?? null;
}

if ($mime === null) {
    error_log('[upload-comunicado] não foi possível detectar o mime do arquivo enviado');
    responder(500, [
        'success' => false,
        'message' => 'Não foi possível identificar o tipo da imagem neste servidor.',
    ]);
    exit;
}

if (!isset($allowed[$mime])) {
    responder(400, ['success' => false, 'message' => 'Formato inválido (' . $mime . '). Use JPG, PNG ou WebP.']);
    exit;
}

// ── Gravação ──────────────────────────────────────────────────────────────────
$ext     = $allowed[$mime];
$dir     = dirname(__FILE__, 3) . '/uploads/comunicados/';
$name    = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destAbs = $dir . $name;
$relPath = 'uploads/comunicados/' . $name;

// mkdir() falhando calado era outro caminho pro 500 mudo: sem a pasta, move_uploaded_file()
// falha depois e ninguém sabe que a causa foi permissão no diretório pai.
if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
    error_log('[upload-comunicado] não foi possível criar ' . $dir);
    responder(500, [
        'success' => false,
        'message' => 'A pasta de imagens não existe e não pôde ser criada no servidor (uploads/comunicados).',
    ]);
    exit;
}

if (!is_writable($dir)) {
    error_log('[upload-comunicado] sem permissão de escrita em ' . $dir);
    responder(500, [
        'success' => false,
        'message' => 'Sem permissão de escrita na pasta uploads/comunicados no servidor.',
    ]);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    error_log('[upload-comunicado] move_uploaded_file falhou para ' . $destAbs);
    responder(500, ['success' => false, 'message' => 'Erro ao salvar o arquivo no servidor.']);
    exit;
}

responder(200, [
    'success' => true,
    'path'    => $relPath,
    'url'     => appBaseUrl() . '/' . $relPath,
]);
