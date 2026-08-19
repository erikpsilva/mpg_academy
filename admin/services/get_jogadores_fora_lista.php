<?php

/**
 * Jogadores que ainda NÃO estão na lista de um domingo — alimenta o seletor de inclusão
 * manual em admin/batebolapresenca.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e) {
    error_log('[batebola-fora-lista] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar: ' . $e->getMessage()]);
});

require_once dirname(__FILE__, 3) . '/config/api_security.php';
validateApiAccess($ALLOWED_ORIGINS);

if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';

$pdo        = getDbConnection();
$dataEvento = trim($_GET['data_evento'] ?? '');

$dt = DateTime::createFromFormat('Y-m-d', $dataEvento);
if (!$dt || $dt->format('Y-m-d') !== $dataEvento) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

// Fora da lista = sem inscrição paga nesse domingo. Quem tem inscrição pendente ou cancelada
// continua aparecendo: incluir manualmente é justamente o caminho pra quem pagou por fora
// depois de desistir do PIX.
$st = $pdo->prepare("
    SELECT j.id, j.nome, j.celular, j.altura_cm, j.nivel, j.foto
    FROM jogadores_batebola j
    WHERE NOT EXISTS (
        SELECT 1 FROM batebola_inscricoes bi
        WHERE bi.jogador_id = j.id AND bi.data_evento = ? AND bi.status = 'pago'
    )
    ORDER BY j.nome ASC
");
$st->execute([$dataEvento]);

$jogadores = [];
foreach ($st->fetchAll() as $j) {
    $jogadores[] = [
        'id'        => (int) $j['id'],
        'nome'      => $j['nome'],
        'celular'   => $j['celular'],
        'altura_cm' => $j['altura_cm'] ? (int) $j['altura_cm'] : null,
        'nivel'     => (int) ($j['nivel'] ?? 3),
        'foto'      => $j['foto'],
    ];
}

echo json_encode(['success' => true, 'jogadores' => $jogadores, 'total' => count($jogadores)]);
