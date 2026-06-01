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

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/services/site/email_template.php';

$pdo  = getDbConnection();
$meses = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
          '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'];

// Busca mensalidades com 25+ dias de atraso ainda não notificadas
$st = $pdo->query("
    SELECT m.id AS mensalidade_id, m.aluno_id, m.referencia, m.vencimento, m.valor,
           a.nome, a.email,
           DATEDIFF(CURDATE(), m.vencimento) AS dias_atraso
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    LEFT JOIN notificacoes_log nl
           ON nl.aluno_id = m.aluno_id
          AND nl.mensalidade_id = m.id
          AND nl.tipo = 'atraso_25dias'
    WHERE m.status = 'atrasado'
      AND DATEDIFF(CURDATE(), m.vencimento) >= 25
      AND nl.id IS NULL
    ORDER BY dias_atraso DESC
");
$pendentes = $st->fetchAll();

$enviadas = 0;
$erros    = 0;

foreach ($pendentes as $r) {
    [$ano, $mes] = explode('-', $r['referencia']);
    $refLabel    = ($meses[$mes] ?? $mes) . '/' . $ano;
    $diasAtraso  = (int) $r['dias_atraso'];

    $aluno = ['id' => $r['aluno_id'], 'nome' => $r['nome'], 'email' => $r['email']];
    $mens  = ['id' => $r['mensalidade_id'], 'dias_atraso' => $diasAtraso, 'ref_label' => $refLabel];

    try {
        mpgEnviarNotificacaoAtraso($pdo, $aluno, $mens);
        $enviadas++;
    } catch (Throwable $e) {
        error_log('[mpg-notif] Erro ao enviar para aluno ' . $r['aluno_id'] . ': ' . $e->getMessage());
        $erros++;
    }
}

echo json_encode([
    'success'  => true,
    'enviadas' => $enviadas,
    'puladas'  => 0,
    'erros'    => $erros,
    'mensagem' => $enviadas === 0
        ? 'Nenhuma notificação pendente.'
        : $enviadas . ' notificação(ões) enviada(s).',
]);
