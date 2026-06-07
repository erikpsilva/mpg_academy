<?php
/**
 * CRON — Lembrete de treino 4h antes para alunos cadastrados
 * Configurar no cPanel: diariamente às 05:00 (notifica quem treina às 09h+)
 * Ajuste o horário conforme o início mais cedo das turmas.
 * Comando: php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_lembrete_treino.php
 *
 * Lógica: verifica qual é o dia da semana hoje → busca todos os alunos ativos
 * em turmas que treinam hoje → envia notificação (uma vez por dia por aluno/turma).
 */

define('CRON_RUN', true);
require_once dirname(__FILE__, 2) . '/config/app.php';
require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/services/whatsapp/zapi.php';

$pdo  = getDbConnection();
$now  = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
$hoje = $now->format('Y-m-d');

// dia_semana no banco: 0=Dom, 1=Seg, 2=Ter, 3=Qua, 4=Qui, 5=Sex, 6=Sáb
$diaSemanaHoje = (int)$now->format('w');

$stmt = $pdo->prepare("
    SELECT a.id AS aluno_id, a.nome, a.celular,
           t.id AS turma_id, t.nome AS turma_nome,
           q.rua, q.numero, q.bairro, q.complemento, q.cidade, q.estado,
           qh.hora_inicio, qh.hora_fim
    FROM turma_alunos ta
    JOIN alunos a           ON a.id  = ta.aluno_id
    JOIN turmas t           ON t.id  = ta.turma_id
    JOIN quadras q          ON q.id  = t.quadra_id
    JOIN turma_horarios th  ON th.turma_id = t.id
    JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE ta.status = 'ativo'
      AND t.status  = 'ativa'
      AND qh.dia_semana = ?
      AND a.celular IS NOT NULL AND a.celular != ''
    ORDER BY qh.hora_inicio ASC
");
$stmt->execute([$diaSemanaHoje]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Controle: envia só 1 vez por aluno_id + turma_id por dia
$enviados = [];
$total    = 0;

foreach ($rows as $r) {
    $chave = $r['aluno_id'] . '_' . $r['turma_id'];
    if (isset($enviados[$chave])) continue;
    $enviados[$chave] = true;

    $nomePrimeiro = explode(' ', trim($r['nome']))[0];
    $horarioFmt   = 'às ' . substr($r['hora_inicio'], 0, 5) . 'h';

    $endereco = '';
    if (!empty($r['rua'])) {
        $endereco = $r['rua'] . ', ' . ($r['numero'] ?? 's/n');
        if (!empty($r['complemento'])) $endereco .= ' - ' . $r['complemento'];
        $endereco .= ' - ' . $r['bairro'] . ', ' . $r['cidade'] . '/' . $r['estado'];
    }

    $msg  = "Olá, *{$nomePrimeiro}*! 🎾\n\n";
    $msg .= "Hoje é dia de treino na *MPG Academy*!\n\n";
    $msg .= "⏰ *Horário:* {$horarioFmt}\n";
    if ($endereco) $msg .= "📍 *Local:* {$endereco}\n";
    $msg .= "\nTe esperamos!";

    if (sendWhatsApp(formatPhoneZapi($r['celular']), $msg)) {
        $total++;
    }
}

echo "Lembretes de treino enviados: {$total}\n";
