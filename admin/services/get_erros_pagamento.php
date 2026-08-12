<?php

/**
 * Lista as falhas de pagamento registradas por mpRegistrarErroPagamento(), pra tela
 * admin/erros-pagamento. Traz o motivo já traduzido e a orientação do que falar pro aluno.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

$pdo = getDbConnection();

$mostrarResolvidos = ($_GET['resolvidos'] ?? '0') === '1';

$sql = "
    SELECT e.*, a.celular AS aluno_celular
    FROM pagamento_erros e
    LEFT JOIN alunos a ON a.id = e.aluno_id
    " . ($mostrarResolvidos ? "" : "WHERE e.resolvido = 0") . "
    ORDER BY e.criado_em DESC
    LIMIT 300
";

$erros = [];
foreach ($pdo->query($sql)->fetchAll() as $r) {
    $erros[] = [
        'id'               => (int) $r['id'],
        'aluno_id'         => $r['aluno_id'] ? (int) $r['aluno_id'] : null,
        'aluno_nome'       => $r['aluno_nome'] ?: '(não identificado)',
        'aluno_email'      => $r['aluno_email'],
        'aluno_celular'    => $r['aluno_celular'],
        'contexto'         => $r['contexto'],
        'referencia_label' => $r['referencia_label'],
        'valor'            => $r['valor'] !== null ? (float) $r['valor'] : null,
        'metodo'           => $r['metodo'],
        'parcelas'         => $r['parcelas'] ? (int) $r['parcelas'] : null,
        'origem'           => $r['origem'],
        'mp_payment_id'    => $r['mp_payment_id'],
        'mp_status'        => $r['mp_status'],
        'mp_status_detail' => $r['mp_status_detail'],
        'http_code'        => $r['http_code'] ? (int) $r['http_code'] : null,
        'motivo'           => $r['mensagem'],
        'acao'             => mpAcaoSugerida($r['mp_status_detail']),
        'detalhe_tecnico'  => $r['detalhe_tecnico'],
        'resolvido'        => (bool) $r['resolvido'],
        'novo'             => !$r['visto_admin'],
        'criado_em'        => (new DateTime($r['criado_em']))->format('d/m/Y H:i'),
    ];
}

$naoVistos  = (int) $pdo->query("SELECT COUNT(*) FROM pagamento_erros WHERE visto_admin = 0 AND resolvido = 0")->fetchColumn();
$naoResolv  = (int) $pdo->query("SELECT COUNT(*) FROM pagamento_erros WHERE resolvido = 0")->fetchColumn();

echo json_encode([
    'success'        => true,
    'erros'          => $erros,
    'total'          => count($erros),
    'nao_vistos'     => $naoVistos,
    'nao_resolvidos' => $naoResolv,
]);
