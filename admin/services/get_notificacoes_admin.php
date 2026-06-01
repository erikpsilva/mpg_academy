<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (empty($_SESSION['usuario'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$pdo  = getDbConnection();
$hoje = new DateTime('today');

$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

// Busca todas as mensalidades em atraso com dados do aluno
$st = $pdo->query("
    SELECT m.id AS mensalidade_id, m.aluno_id, m.referencia, m.vencimento, m.valor,
           a.nome, a.email,
           DATEDIFF(CURDATE(), m.vencimento) AS dias_atraso
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    WHERE m.status = 'atrasado'
    ORDER BY dias_atraso DESC
");
$rows = $st->fetchAll();

$alunos = [];
foreach ($rows as $r) {
    [$ano, $mes] = explode('-', $r['referencia']);
    $diasAtraso  = (int) $r['dias_atraso'];

    // Verifica se já foi enviada notificação de 25 dias para esta mensalidade
    $stLog = $pdo->prepare("
        SELECT id FROM notificacoes_log
        WHERE aluno_id = ? AND mensalidade_id = ? AND tipo = 'atraso_25dias'
    ");
    $stLog->execute([$r['aluno_id'], $r['mensalidade_id']]);
    $notificado = (bool) $stLog->fetchColumn();

    $alunos[] = [
        'mensalidade_id'  => (int) $r['mensalidade_id'],
        'aluno_id'        => (int) $r['aluno_id'],
        'nome'            => $r['nome'],
        'email'           => $r['email'],
        'referencia'      => $r['referencia'],
        'ref_label'       => ($meses[$mes] ?? $mes) . '/' . $ano,
        'dias_atraso'     => $diasAtraso,
        'alerta'          => $diasAtraso >= 25,   // approaching block
        'bloqueado'       => $diasAtraso >= 30,
        'notificado'      => $notificado,
    ];
}

echo json_encode([
    'success'         => true,
    'total'           => count($alunos),
    'total_alerta'    => count(array_filter($alunos, fn($a) => $a['alerta'] && !$a['notificado'])),
    'alunos'          => $alunos,
]);
