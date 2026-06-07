<?php
/**
 * CRON — Lembrete aula experimental no dia (3h antes)
 * Configurar no cPanel: diariamente às 06:00 (horário do servidor)
 * Ajuste o horário de execução para 3h antes do início das aulas.
 * Ex: se as aulas são às 09h, executar às 06h.
 * Comando: php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_lembrete_dia_aula.php
 */

define('CRON_RUN', true);
require_once dirname(__FILE__, 2) . '/config/app.php';
require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/services/whatsapp/zapi.php';

$pdo = getDbConnection();

$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');

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
      AND DATE(ae.data_agendada) = ?
    GROUP BY ae.id
");
$stmt->execute([$hoje]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $horarioFmt = $r['hora_inicio']
        ? 'às ' . substr($r['hora_inicio'], 0, 5) . 'h'
        : 'hoje';

    $endereco = '';
    if (!empty($r['rua'])) {
        $endereco = $r['rua'] . ', ' . ($r['numero'] ?? 's/n');
        if (!empty($r['complemento'])) $endereco .= ' - ' . $r['complemento'];
        $endereco .= ' - ' . $r['bairro'] . ', ' . $r['cidade'] . '/' . $r['estado'];
    }

    $nomePrimeiro = explode(' ', trim($r['nome']))[0];

    if (!empty($r['celular'])) {
        $msg = "Olá, *{$nomePrimeiro}*! 🎾\n\n";
        $msg .= "Hoje é o dia da sua aula experimental na *MPG Academy*!\n\n";
        $msg .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msg .= "📍 *Local:* {$endereco}\n";
        $msg .= "\nTe esperamos! 😊";

        sendWhatsApp(formatPhoneZapi($r['celular']), $msg);
    }

    if (!empty($r['is_menor']) && !empty($r['responsavel_celular'])) {
        $nomeAluno = trim($r['nome']);
        $nomeResp  = explode(' ', trim($r['responsavel_nome'] ?? 'Responsável'))[0];

        $msgResp = "Olá, *{$nomeResp}*! 🎾\n\n";
        $msgResp .= "Hoje é o dia da aula experimental de *{$nomeAluno}* na *MPG Academy*!\n\n";
        $msgResp .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msgResp .= "📍 *Local:* {$endereco}";

        sendWhatsApp(formatPhoneZapi($r['responsavel_celular']), $msgResp);
    }
}

echo "Lembretes dia da aula: " . count($rows) . " enviados.\n";
