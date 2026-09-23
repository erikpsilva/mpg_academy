<?php

/**
 * Lista de presença da aula experimental: marca quem veio e quem faltou.
 *
 *   presente → status 'realizada' (entra em "Aula realizada" nos agendamentos)
 *   faltou   → status 'cancelada' (entra em "Cancelada" nos agendamentos)
 *   limpar   → volta para 'agendada', para corrigir um clique errado
 *
 * A coluna `presenca` guarda a marcação à parte do status porque 'cancelada' sozinho não
 * diz se a pessoa faltou ou desmarcou antes. É ela que mantém a lista de uma data passada
 * igual ao que aconteceu: quem faltou continua aparecendo como "Faltou", e quem desmarcou
 * antes da aula (cancelada sem presença) não aparece.
 */

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

$id       = (int) ($_POST['id'] ?? 0);
$presenca = trim($_POST['presenca'] ?? '');

if ($id <= 0 || !in_array($presenca, ['presente', 'faltou', 'limpar'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$st = $pdo->prepare("SELECT id, turma_id, status, presenca, data_agendada FROM aulas_experimentais WHERE id = ?");
$st->execute([$id]);
$aula = $st->fetch(PDO::FETCH_ASSOC);

if (!$aula) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Aula experimental não encontrada.']);
    exit;
}

$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';

// Perfil Bate Bola não mexe em aula experimental.
if ($nivel === 'batebola') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

// Professor só marca presença nas turmas dele. A tela já mostra só essas, mas a regra
// precisa valer aqui também: sem isso, quem chamasse o endereço direto poderia marcar
// qualquer aula.
if ($nivel === 'professor') {
    $stVinculo = $pdo->prepare("SELECT 1 FROM professor_turmas WHERE professor_id = ? AND turma_id = ? LIMIT 1");
    $stVinculo->execute([(int) ($_SESSION['usuario']['professor_id'] ?? 0), (int) $aula['turma_id']]);
    if (!$stVinculo->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Essa aula é de uma turma que não está vinculada a você.']);
        exit;
    }
}

// Só mexe no que pertence à lista: agendada, já realizada, ou cancelada POR FALTA. Uma aula
// cancelada antes (a pessoa desmarcou) não volta por aqui — isso se resolve reagendando.
$naLista = $aula['status'] === 'agendada'
        || $aula['status'] === 'realizada'
        || ($aula['status'] === 'cancelada' && $aula['presenca'] === 'faltou');

if (!$naLista) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Esta aula foi cancelada antes da data e não faz parte da lista de presença.']);
    exit;
}

$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
if ($presenca !== 'limpar' && !empty($aula['data_agendada']) && $aula['data_agendada'] > $hoje) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Essa aula ainda não aconteceu — dá pra marcar presença a partir do dia da aula.']);
    exit;
}

if ($presenca === 'presente') {
    $pdo->prepare("UPDATE aulas_experimentais SET status = 'realizada', presenca = 'presente', presenca_em = NOW() WHERE id = ?")
        ->execute([$id]);
} elseif ($presenca === 'faltou') {
    $pdo->prepare("UPDATE aulas_experimentais SET status = 'cancelada', presenca = 'faltou', presenca_em = NOW() WHERE id = ?")
        ->execute([$id]);
} else {
    $pdo->prepare("UPDATE aulas_experimentais SET status = 'agendada', presenca = NULL, presenca_em = NULL WHERE id = ?")
        ->execute([$id]);
}

echo json_encode([
    'success'  => true,
    'presenca' => $presenca === 'limpar' ? null : $presenca,
    'status'   => $presenca === 'presente' ? 'realizada' : ($presenca === 'faltou' ? 'cancelada' : 'agendada'),
]);
