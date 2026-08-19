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

// O pedido pode ser de aluno, professor ou equipe MPG — três tabelas diferentes. O JOIN
// interno com `alunos` que existia aqui fazia os pedidos de equipe sumirem da lista.
$st = $pdo->query("
    SELECT p.id, p.pessoa_tipo, p.pessoa_id, p.tipo_uniforme, p.equipe_cargo,
           p.aluno_id, p.turma_id,
           p.genero, p.modelo, p.nome_camisa, p.numero, p.tamanho_camisa, p.tamanho_shorts, p.valor,
           p.status_pedido, p.conflito_numero, p.pago_em, p.visto_admin,
           COALESCE(a.nome,
                    TRIM(CONCAT(pr.nome, ' ', COALESCE(pr.sobrenome, ''))),
                    au.nome_completo, '(removido)')            AS pessoa_nome,
           COALESCE(a.email, pr.email, au.email, '')           AS pessoa_email,
           COALESCE(a.celular, pr.celular, '')                 AS pessoa_celular,
           COALESCE(t.nome, '—') AS turma_nome
    FROM pedidos_uniforme p
    LEFT JOIN alunos         a  ON p.pessoa_tipo = 'aluno'     AND a.id  = p.pessoa_id
    LEFT JOIN professores    pr ON p.pessoa_tipo = 'professor' AND pr.id = p.pessoa_id
    LEFT JOIN admin_usuarios au ON p.pessoa_tipo = 'admin'     AND au.id = p.pessoa_id
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

    $ehEquipe = $r['tipo_uniforme'] === 'equipe_tecnica';

    $pedidos[] = [
        'id'              => (int) $r['id'],
        'pessoa_tipo'     => $r['pessoa_tipo'],
        'pessoa_id'       => (int) $r['pessoa_id'],
        'pessoa_label'    => UNIFORME_PESSOA_LABEL[$r['pessoa_tipo']] ?? $r['pessoa_tipo'],
        'tipo_uniforme'   => $r['tipo_uniforme'],
        'tipo_label'      => UNIFORME_TIPO_LABEL[$r['tipo_uniforme']] ?? $r['tipo_uniforme'],
        // Na camisa da equipe técnica o que vai estampado é o cargo + nome, não um número.
        'texto_camisa'    => $ehEquipe
                                ? uniformeTextoEquipe($r['equipe_cargo'], $r['nome_camisa'])
                                : $r['nome_camisa'],
        'aluno_id'        => $r['aluno_id'] !== null ? (int) $r['aluno_id'] : null,
        'turma_id'        => $r['turma_id'] !== null ? (int) $r['turma_id'] : null,
        'aluno_nome'      => $r['pessoa_nome'],
        'aluno_email'     => $r['pessoa_email'],
        'aluno_celular'   => $r['pessoa_celular'],
        'turma_nome'      => $r['pessoa_tipo'] === 'aluno' ? $r['turma_nome'] : (UNIFORME_PESSOA_LABEL[$r['pessoa_tipo']] ?? '—'),
        'genero'          => $r['genero'],
        'genero_label'    => $r['genero'] === 'feminino' ? 'Feminino' : 'Masculino',
        'modelo'          => $r['modelo'],
        'modelo_label'    => UNIFORME_MODELO_LABEL[$r['modelo']] ?? $r['modelo'],
        'cor_label'       => uniformeCor($r['modelo']),
        'nome_camisa'     => $r['nome_camisa'],
        // Equipe técnica é só camisa: número e calção não existem, e vêm nulos pra tela
        // saber que não deve mostrar campo vazio no lugar.
        'numero'          => $r['numero'] !== null ? (int) $r['numero'] : null,
        'tamanho_camisa'  => $r['tamanho_camisa'],
        'tamanho_shorts'  => $r['tamanho_shorts'],
        // Rótulo curto pra tela ('Bermuda') e completo pro PDF do fornecedor, que precisa
        // dizer sem ambiguidade o que é cada peça — camisa feminina é baby look, e o corte
        // da bermuda não é o mesmo do calção.
        'label_shorts'    => explode(' ', uniformeLabelPeca($r['genero'], 'shorts'))[0],
        'peca_camisa'     => uniformeLabelPeca($r['genero'], 'camisa'),
        'peca_shorts'     => uniformeLabelPeca($r['genero'], 'shorts'),
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
