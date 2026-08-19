<?php

/**
 * Troca dois jogadores de time, à mão, depois do sorteio.
 *
 * A regra é só uma: os dois precisam ter o MESMO nível de estrelas. O sorteio existe pra
 * equilibrar os times por nível — deixar trocar um 5 estrelas por um 2 desmontaria justamente
 * o que ele calculou. Com níveis iguais, a soma de cada time não muda e o equilíbrio se
 * mantém, então a troca é segura.
 *
 * A troca não reescreve os times (eles são recalculados do seed a cada carregamento): fica
 * guardada como um par de jogadores e é reaplicada por cima do sorteio. Ver
 * batebolaAplicarTrocas() em config/batebola.php.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e) {
    error_log('[batebola-troca] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao trocar: ' . $e->getMessage()]);
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
require_once dirname(__FILE__, 3) . '/config/batebola.php';

$pdo        = getDbConnection();
$dataEvento = trim($_POST['data_evento'] ?? '');
$idA        = (int) ($_POST['jogador_a'] ?? 0);
$idB        = (int) ($_POST['jogador_b'] ?? 0);

$dt = DateTime::createFromFormat('Y-m-d', $dataEvento);
if (!$dt || $dt->format('Y-m-d') !== $dataEvento || (int) $dt->format('w') !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data inválida.']);
    exit;
}

if ($idA <= 0 || $idB <= 0 || $idA === $idB) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Selecione dois jogadores diferentes.']);
    exit;
}

// ── Os dois precisam estar nos times montados ────────────────────────────────
$times = batebolaTimesDoEvento($pdo, $dataEvento);

if (empty($times)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Os times ainda não foram sorteados.']);
    exit;
}

$posA = batebolaPosicaoJogador($times, $idA);
$posB = batebolaPosicaoJogador($times, $idB);

if ($posA === null || $posB === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Jogador não está nos times desse domingo.']);
    exit;
}

if ($posA[0] === $posB[0]) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Os dois já estão no mesmo time.']);
    exit;
}

$jogA = $times[$posA[0]]['jogadores'][$posA[1]];
$jogB = $times[$posB[0]]['jogadores'][$posB[1]];

// ── A regra ───────────────────────────────────────────────────────────────────
$nivelA = (int) ($jogA['nivel'] ?? 3);
$nivelB = (int) ($jogB['nivel'] ?? 3);

if ($nivelA !== $nivelB) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Só dá pra trocar jogadores do mesmo nível. '
                   . $jogA['nome'] . ' tem ' . $nivelA . ' estrela' . ($nivelA === 1 ? '' : 's')
                   . ' e ' . $jogB['nome'] . ' tem ' . $nivelB . '.',
    ]);
    exit;
}

// ── Grava o par ───────────────────────────────────────────────────────────────
$trocas = batebolaTrocasSalvas($pdo, $dataEvento);

// Trocar o mesmo par de novo desfaz a troca anterior, em vez de empilhar um par que
// simplesmente anularia o outro na hora de aplicar.
$par      = [$idA, $idB];
$parInv   = [$idB, $idA];
$desfez   = false;

foreach ($trocas as $i => $t) {
    if ($t == $par || $t == $parInv) {
        unset($trocas[$i]);
        $desfez = true;
        break;
    }
}

if (!$desfez) $trocas[] = $par;

batebolaSalvarTrocas($pdo, $dataEvento, $trocas);

$timesAtualizados = batebolaTimesDoEvento($pdo, $dataEvento);
$novaPosA = batebolaPosicaoJogador($timesAtualizados, $idA);

echo json_encode([
    'success'  => true,
    'desfeita' => $desfez,
    'message'  => $desfez
        ? 'Troca desfeita — ' . $jogA['nome'] . ' e ' . $jogB['nome'] . ' voltaram pros times do sorteio.'
        : $jogA['nome'] . ' e ' . $jogB['nome'] . ' trocaram de time.',
    'time_a'   => $novaPosA !== null ? $timesAtualizados[$novaPosA[0]]['cor'] : null,
]);
