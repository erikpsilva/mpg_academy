<?php

/**
 * Ajusta a cobrança de teste usada para validar o fluxo de pagamento em produção.
 *
 * Existe para dar uma cobrança real, de valor baixo, que pode ser paga de verdade e
 * resetada quantas vezes for preciso — sem mexer na mensalidade de nenhum aluno de verdade.
 *
 * Só age sobre a cobrança do aluno de e-mail ALUNO_TESTE_EMAIL: qualquer id fora disso é
 * recusado, pra ninguém conseguir zerar o valor da mensalidade de um aluno pagante.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e) {
    error_log('[cobranca-teste] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
});

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/uniformes.php';

const ALUNO_TESTE_EMAIL = 'teste.pagamento@mpgacademy.com.br';

$pdo   = getDbConnection();
$acao  = trim($_POST['acao'] ?? 'valor');
$valor = (float) str_replace(',', '.', $_POST['valor'] ?? '1');

$st = $pdo->prepare("
    SELECT m.id, m.valor, m.status
    FROM mensalidades m
    JOIN alunos a ON a.id = m.aluno_id
    WHERE a.email = ? AND m.tipo = 'avulso'
    ORDER BY m.id DESC LIMIT 1
");
$st->execute([ALUNO_TESTE_EMAIL]);
$cobranca = $st->fetch();

if (!$cobranca) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Aluno de teste não encontrado. Rode o SQL de criação primeiro.',
    ]);
    exit;
}

if ($acao === 'resetar') {
    // Volta a cobrança pro estado inicial pra poder testar de novo. Também apaga o
    // lançamento de receita que a baixa gerou — senão cada teste inflaria o caixa.
    $pdo->prepare("
        UPDATE mensalidades
           SET status = 'pendente', data_pagamento = NULL, mp_payment_id = NULL,
               mp_taxa_valor = NULL, mp_valor_liquido = NULL, mp_payment_method = NULL,
               atualizado_em = NOW()
         WHERE id = ?
    ")->execute([(int) $cobranca['id']]);

    $pdo->prepare("
        DELETE FROM lancamentos_financeiros
         WHERE referencia_tipo IN ('mensalidade', 'mensalidade_taxa_mp') AND referencia_id = ?
    ")->execute([(int) $cobranca['id']]);

    echo json_encode(['success' => true, 'message' => 'Cobrança de teste voltou para pendente.']);
    exit;
}

// ── Mudança de valor ──────────────────────────────────────────────────────────
if ($valor < 1 || $valor > 50) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Use um valor entre R$ 1,00 e R$ 50,00 — é só pra testar.',
    ]);
    exit;
}

$pdo->prepare("UPDATE mensalidades SET valor = ?, atualizado_em = NOW() WHERE id = ?")
    ->execute([round($valor, 2), (int) $cobranca['id']]);

echo json_encode([
    'success' => true,
    'valor'   => round($valor, 2),
    'message' => 'Cobrança de teste agora é R$ ' . number_format($valor, 2, ',', '.') . '.',
]);
