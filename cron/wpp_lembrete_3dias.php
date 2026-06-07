<?php
/**
 * CRON — Lembrete aula experimental 3 dias antes
 * Configurar no cPanel: diariamente às 08:00
 * Comando: php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_lembrete_3dias.php
 */

define('CRON_RUN', true);
require_once dirname(__FILE__, 2) . '/config/app.php';
require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/services/whatsapp/zapi.php';

$pdo = getDbConnection();

$hoje = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
$alvo = (clone $hoje)->modify('+3 days')->format('Y-m-d');

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
$stmt->execute([$alvo]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$diasSemana = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];

foreach ($rows as $r) {
    $dt      = new DateTime($r['data_agendada']);
    $dataFmt = $dt->format('d') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y')
             . ' (' . $diasSemana[(int)$dt->format('w')] . ')';

    $horarioFmt = $r['hora_inicio']
        ? 'das ' . substr($r['hora_inicio'], 0, 5) . 'h às ' . substr($r['hora_fim'], 0, 5) . 'h'
        : 'a confirmar';

    $endereco = '';
    if (!empty($r['rua'])) {
        $endereco = $r['rua'] . ', ' . ($r['numero'] ?? 's/n');
        if (!empty($r['complemento'])) $endereco .= ' - ' . $r['complemento'];
        $endereco .= ' - ' . $r['bairro'] . ', ' . $r['cidade'] . '/' . $r['estado'];
    }

    $nomePrimeiro = explode(' ', trim($r['nome']))[0];

    if (!empty($r['celular'])) {
        $msg = "Olá, *{$nomePrimeiro}*! 🎾\n\n";
        $msg .= "Lembrando que sua aula experimental na *MPG Academy* é em 3 dias!\n\n";
        $msg .= "📅 *Data:* {$dataFmt}\n";
        $msg .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msg .= "📍 *Local:* {$endereco}\n";
        $msg .= "\nQualquer dúvida é só chamar. Te esperamos!";

        sendWhatsApp(formatPhoneZapi($r['celular']), $msg);
    }

    // Notifica responsável do menor também
    if (!empty($r['is_menor']) && !empty($r['responsavel_celular'])) {
        $nomeAluno = trim($r['nome']);
        $nomeResp  = explode(' ', trim($r['responsavel_nome'] ?? 'Responsável'))[0];

        $msgResp = "Olá, *{$nomeResp}*! 🎾\n\n";
        $msgResp .= "Lembrando que a aula experimental de *{$nomeAluno}* na *MPG Academy* é em 3 dias!\n\n";
        $msgResp .= "📅 *Data:* {$dataFmt}\n";
        $msgResp .= "⏰ *Horário:* {$horarioFmt}\n";
        if ($endereco) $msgResp .= "📍 *Local:* {$endereco}";

        sendWhatsApp(formatPhoneZapi($r['responsavel_celular']), $msgResp);
    }
}

echo "Lembretes 3 dias: " . count($rows) . " enviados.\n";
