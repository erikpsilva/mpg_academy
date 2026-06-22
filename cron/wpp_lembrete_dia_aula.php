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
require_once dirname(__FILE__, 2) . '/services/whatsapp/wpp_aula_teste_lembrete.php';

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

$jaEnviado = $pdo->prepare("SELECT id FROM lembrete_teste_log WHERE aula_experimental_id = ? AND tipo = 'dia_aula' AND DATE(enviado_em) = CURDATE() LIMIT 1");
$logInsert = $pdo->prepare("INSERT INTO lembrete_teste_log (aula_experimental_id, tipo) VALUES (?, 'dia_aula')");

$enviados = 0;
foreach ($rows as $r) {
    $jaEnviado->execute([$r['id']]);
    if ($jaEnviado->fetch()) continue; // já enviado hoje (ex: disparo manual) — evita duplicado

    wppAulaTesteLembrete($r, 'dia_aula');
    $logInsert->execute([$r['id']]);
    $enviados++;
}

echo "Lembretes dia da aula: {$enviados} enviados.\n";
