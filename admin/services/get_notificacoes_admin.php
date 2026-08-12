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

// ── Pedidos de uniforme pagos que o admin ainda não viu ──────────────────────────
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$uniformes = [];
try {
    $stUni = $pdo->query("
        SELECT p.id, p.genero, p.modelo, p.nome_camisa, p.numero, p.tamanho_camisa, p.tamanho_shorts, p.pago_em,
               a.nome AS aluno_nome, COALESCE(t.nome, '—') AS turma_nome
        FROM pedidos_uniforme p
        JOIN alunos a ON a.id = p.aluno_id
        LEFT JOIN turmas t ON t.id = p.turma_id
        WHERE p.status_pagamento = 'pago' AND p.visto_admin = 0
        ORDER BY p.pago_em DESC
        LIMIT 20
    ");

    foreach ($stUni->fetchAll() as $u) {
        $uniformes[] = [
            'id'           => (int) $u['id'],
            'aluno_nome'   => $u['aluno_nome'],
            'turma_nome'   => $u['turma_nome'],
            'genero_label' => $u['genero'] === 'feminino' ? 'Feminino' : 'Masculino',
            'modelo_label' => UNIFORME_MODELO_LABEL[$u['modelo']] ?? $u['modelo'],
            'nome_camisa'  => $u['nome_camisa'],
            'numero'       => (int) $u['numero'],
            'tamanho_camisa' => $u['tamanho_camisa'],
            'tamanho_shorts' => $u['tamanho_shorts'],
            'pago_em'      => $u['pago_em'] ? (new DateTime($u['pago_em']))->format('d/m H:i') : '',
        ];
    }
} catch (PDOException $e) {
    // Tabela ainda não migrada — o sino segue funcionando só com as mensalidades.
}

// ── Erros de pagamento ainda não vistos ──────────────────────────────────────────
require_once dirname(__FILE__, 3) . '/config/mercadopago.php';

$errosPagamento = [];
try {
    $stErr = $pdo->query("
        SELECT id, aluno_nome, contexto, valor, metodo, mensagem, mp_status_detail, criado_em
        FROM pagamento_erros
        WHERE visto_admin = 0 AND resolvido = 0
        ORDER BY criado_em DESC
        LIMIT 20
    ");

    foreach ($stErr->fetchAll() as $e) {
        $errosPagamento[] = [
            'id'         => (int) $e['id'],
            'aluno_nome' => $e['aluno_nome'] ?: '(não identificado)',
            'contexto'   => $e['contexto'],
            'valor'      => $e['valor'] !== null ? (float) $e['valor'] : null,
            'metodo'     => $e['metodo'],
            'motivo'     => $e['mensagem'],
            'criado_em'  => (new DateTime($e['criado_em']))->format('d/m H:i'),
        ];
    }
} catch (PDOException $e) {
    // Tabela ainda não migrada — o sino segue funcionando sem essa seção.
}

echo json_encode([
    'success'         => true,
    'total'           => count($alunos),
    'total_alerta'    => count(array_filter($alunos, fn($a) => $a['alerta'] && !$a['notificado'])),
    'alunos'          => $alunos,
    'uniformes'       => $uniformes,
    'total_uniformes' => count($uniformes),
    'erros_pagamento' => $errosPagamento,
    'total_erros'     => count($errosPagamento),
]);
