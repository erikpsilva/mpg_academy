<?php

/**
 * Lista pra quem vai o aviso de uma turma: um número por pessoa, já no formato da Z-API.
 *
 * Quem recebe é o responsável, quando existe — é ele quem acompanha o aluno menor de idade.
 * Sem responsável cadastrado, vai no número do próprio aluno.
 *
 * Números repetidos entram uma vez só: irmãos na mesma turma costumam ter o mesmo
 * responsável, e ninguém quer receber o mesmo aviso duas vezes.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (empty($_SESSION['usuario']) || !in_array($nivel, ['admin', 'editor'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/services/whatsapp/zapi.php';

$pdo     = getDbConnection();
$turmaId = (int) ($_GET['turma_id'] ?? 0);

if ($turmaId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Escolha a turma.']);
    exit;
}

$st = $pdo->prepare("
    SELECT a.id, a.nome, a.celular, a.responsavel_nome, a.responsavel_celular, t.nome AS turma_nome
    FROM turma_alunos ta
    JOIN alunos a ON a.id = ta.aluno_id
    JOIN turmas t ON t.id = ta.turma_id
    WHERE ta.turma_id = ? AND ta.status = 'ativo' AND a.status = 'ativo'
    ORDER BY a.nome
");
$st->execute([$turmaId]);

$contatos = [];
$semNumero = [];
$vistos    = [];
$turmaNome = '';

foreach ($st->fetchAll() as $a) {
    $turmaNome = $a['turma_nome'];

    $bruto = trim($a['responsavel_celular'] ?? '') !== ''
        ? $a['responsavel_celular']
        : (string) $a['celular'];

    $telefone = preg_replace('/\D/', '', $bruto);

    // Celular brasileiro válido: DDD + 8 ou 9 dígitos (com ou sem o 55 na frente).
    $valido = $telefone !== '' && strlen($telefone) >= 10 && strlen($telefone) <= 13;

    if (!$valido) {
        $semNumero[] = ['aluno' => $a['nome'], 'motivo' => 'sem celular cadastrado'];
        continue;
    }

    if (isset($vistos[$telefone])) {
        // Mesmo número já na lista (irmãos, por exemplo) — anota junto, não duplica o envio.
        $contatos[$vistos[$telefone]]['alunos'][] = $a['nome'];
        continue;
    }

    $vistos[$telefone] = count($contatos);

    $contatos[] = [
        'aluno_id'  => (int) $a['id'],
        'alunos'    => [$a['nome']],
        'para'      => trim($a['responsavel_nome'] ?? '') !== '' ? $a['responsavel_nome'] : $a['nome'],
        'telefone'  => $telefone,
        'eh_responsavel' => trim($a['responsavel_celular'] ?? '') !== '',
    ];
}

echo json_encode([
    'success'    => true,
    'turma'      => $turmaNome,
    'contatos'   => $contatos,
    'sem_numero' => $semNumero,
    'total'      => count($contatos),
]);
