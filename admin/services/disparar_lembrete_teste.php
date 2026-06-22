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

$turmaId = (int) ($_POST['turma_id'] ?? 0);
if (!$turmaId) {
    echo json_encode(['success' => false, 'message' => 'Turma inválida.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
require_once dirname(__FILE__, 3) . '/config/app.php';
require_once dirname(__FILE__, 3) . '/services/whatsapp/wpp_aula_teste_lembrete.php';

$pdo = getDbConnection();

// Mesma janela que os crons cobririam hoje: agendados pra hoje (lembrete do dia)
// ou pra daqui a 3 dias (lembrete antecipado) — só nessas datas existe mensagem.
$hoje  = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
$alvo3 = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->modify('+3 days')->format('Y-m-d');

$stmt = $pdo->prepare("
    SELECT ae.id, ae.data_agendada,
           at.nome, at.celular, at.is_menor, at.responsavel_nome, at.responsavel_celular,
           t.nome AS turma_nome,
           q.rua, q.numero, q.bairro, q.complemento, q.cidade, q.estado,
           qh.hora_inicio, qh.hora_fim
    FROM aulas_experimentais ae
    JOIN alunos_teste at  ON at.id = ae.aluno_teste_id
    JOIN turmas t         ON t.id  = ae.turma_id
    JOIN quadras q        ON q.id  = t.quadra_id
    LEFT JOIN turma_horarios th ON th.turma_id = t.id
    LEFT JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE ae.status = 'agendada'
      AND ae.turma_id = ?
      AND DATE(ae.data_agendada) IN (?, ?)
    GROUP BY ae.id
");
$stmt->execute([$turmaId, $hoje, $alvo3]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo json_encode([
        'success'  => true,
        'enviados' => 0,
        'message'  => 'Nenhum aluno agendado pra hoje ou em 3 dias nessa turma — não há lembrete pra disparar agora.',
    ]);
    exit;
}

$jaEnviado = $pdo->prepare("SELECT id FROM lembrete_teste_log WHERE aula_experimental_id = ? AND tipo = ? AND DATE(enviado_em) = CURDATE() LIMIT 1");
$logInsert = $pdo->prepare("INSERT INTO lembrete_teste_log (aula_experimental_id, tipo) VALUES (?, ?)");

$enviados = 0;
$pulados  = 0;

foreach ($rows as $r) {
    $dataSomente = substr($r['data_agendada'], 0, 10);
    $tipo        = ($dataSomente === $hoje) ? 'dia_aula' : '3dias';

    $jaEnviado->execute([$r['id'], $tipo]);
    if ($jaEnviado->fetch()) {
        $pulados++;
        continue; // já tinha sido enviado hoje (cron ou disparo manual anterior)
    }

    wppAulaTesteLembrete($r, $tipo);
    $logInsert->execute([$r['id'], $tipo]);
    $enviados++;
}

$msg = $enviados > 0
    ? "{$enviados} lembrete(s) enviado(s)."
    : 'Nenhum lembrete novo — todos os elegíveis já tinham recebido hoje.';
if ($pulados > 0) {
    $msg .= " ({$pulados} já tinha(m) recebido hoje e foi(ram) pulado(s).)";
}

echo json_encode(['success' => true, 'enviados' => $enviados, 'pulados' => $pulados, 'message' => $msg]);
