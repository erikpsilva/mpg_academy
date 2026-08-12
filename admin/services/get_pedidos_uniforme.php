<?php

/**
 * Lista os pedidos de uniforme pro painel admin. Só entram pedidos PAGOS — pedido sem
 * pagamento confirmado é apenas uma reserva de número, não um pedido de verdade.
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
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

$pdo = getDbConnection();

$st = $pdo->query("
    SELECT p.id, p.aluno_id, p.turma_id,
           p.genero, p.modelo, p.nome_camisa, p.numero, p.tamanho_camisa, p.tamanho_shorts, p.valor,
           p.status_pedido, p.conflito_numero, p.pago_em, p.visto_admin,
           a.nome AS aluno_nome, a.email AS aluno_email, a.celular AS aluno_celular,
           COALESCE(t.nome, '—') AS turma_nome
    FROM pedidos_uniforme p
    JOIN alunos a ON a.id = p.aluno_id
    LEFT JOIN turmas t ON t.id = p.turma_id
    WHERE p.status_pagamento = 'pago'
    ORDER BY p.pago_em DESC, p.id DESC
");

$pedidos = [];
$porStatus = array_fill_keys(UNIFORME_STATUS_FLUXO, 0);

foreach ($st->fetchAll() as $r) {
    $status = $r['status_pedido'];
    if (isset($porStatus[$status])) $porStatus[$status]++;

    $indice = array_search($status, UNIFORME_STATUS_FLUXO, true);

    $pedidos[] = [
        'id'              => (int) $r['id'],
        'aluno_id'        => (int) $r['aluno_id'],
        'turma_id'        => $r['turma_id'] !== null ? (int) $r['turma_id'] : null,
        'aluno_nome'      => $r['aluno_nome'],
        'aluno_email'     => $r['aluno_email'],
        'aluno_celular'   => $r['aluno_celular'],
        'turma_nome'      => $r['turma_nome'],
        'genero'          => $r['genero'],
        'genero_label'    => $r['genero'] === 'feminino' ? 'Feminino' : 'Masculino',
        'modelo'          => $r['modelo'],
        'modelo_label'    => UNIFORME_MODELO_LABEL[$r['modelo']] ?? $r['modelo'],
        'nome_camisa'     => $r['nome_camisa'],
        'numero'          => (int) $r['numero'],
        'tamanho_camisa'  => $r['tamanho_camisa'],
        'tamanho_shorts'  => $r['tamanho_shorts'],
        'label_shorts'    => explode(' ', uniformeLabelPeca($r['genero'], 'shorts'))[0],
        'valor'           => (float) $r['valor'],
        'status'          => $status,
        'status_label'    => UNIFORME_STATUS_LABEL[$status] ?? $status,
        'status_indice'   => $indice === false ? 0 : $indice,
        'proximo_status'  => ($indice !== false && isset(UNIFORME_STATUS_FLUXO[$indice + 1]))
                                ? UNIFORME_STATUS_FLUXO[$indice + 1] : null,
        'conflito_numero' => (bool) $r['conflito_numero'],
        'novo'            => !$r['visto_admin'],
        'pago_em'         => $r['pago_em'],
        'pago_em_label'   => $r['pago_em'] ? (new DateTime($r['pago_em']))->format('d/m/Y H:i') : '—',
    ];
}

echo json_encode([
    'success'    => true,
    'total'      => count($pedidos),
    'por_status' => $porStatus,
    'fluxo'      => UNIFORME_STATUS_FLUXO,
    'labels'     => UNIFORME_STATUS_LABEL,
    'pedidos'    => $pedidos,

    // Grades de tamanho por gênero e limites do número — o formulário de correção monta os
    // selects a partir daqui, em vez de repetir as opções no JS e sair de sincronia com
    // config/uniformes.php na primeira vez que uma grade mudar.
    'tamanhos'   => [
        'masculino' => [
            'camisa' => uniformeTamanhos('masculino', 'camisa'),
            'shorts' => uniformeTamanhos('masculino', 'shorts'),
        ],
        'feminino' => [
            'camisa' => uniformeTamanhos('feminino', 'camisa'),
            'shorts' => uniformeTamanhos('feminino', 'shorts'),
        ],
    ],
    'numero_min' => UNIFORME_NUMERO_MIN,
    'numero_max' => UNIFORME_NUMERO_MAX,
    'nome_max'   => UNIFORME_NOME_MAX,
]);
