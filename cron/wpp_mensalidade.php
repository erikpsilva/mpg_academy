<?php
/**
 * CRON — Notificações WhatsApp de mensalidade
 * Configurar no cPanel: diariamente às 08:00
 * Comando: php /home/SEU_USUARIO/public_html/mpg_academy/cron/wpp_mensalidade.php
 *
 * Gatilhos:
 *   - 5 dias antes do vencimento (tipo: wpp_5dias)
 *   - Dia do vencimento           (tipo: wpp_vencimento)
 *   - Atrasada, a cada 2 dias     (tipo: wpp_atraso — verifica última envio)
 *
 * Cada gatilho tem o seu toggle em Configurações > Avisos e notificações.
 */

define('CRON_RUN', true);

// Recusa quem abrir a URL sem o header da KingHost. Ver cron/_auth.php.
require_once dirname(__FILE__) . '/_auth.php';
require_once dirname(__FILE__, 2) . '/config/app.php';
require_once dirname(__FILE__, 2) . '/config/database.php';
require_once dirname(__FILE__, 2) . '/config/avisos.php';
require_once dirname(__FILE__, 2) . '/services/whatsapp/zapi.php';

$pdo  = getDbConnection();
$hoje = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
$em5  = (new DateTime($hoje))->modify('+5 days')->format('Y-m-d');

// Os três avisos vivem no mesmo cron, então cada um é checado na sua seção — desligar o de
// atraso não pode calar o de vencimento.
$liga5dias      = avisoAtivo($pdo, 'aviso_mensalidade_5dias');
$ligaVencimento = avisoAtivo($pdo, 'aviso_mensalidade_vencimento');
$ligaAtraso     = avisoAtivo($pdo, 'aviso_mensalidade_atraso');

if (!$liga5dias && !$ligaVencimento && !$ligaAtraso) {
    echo "Notificações de mensalidade: desligadas em Configurações > Avisos e notificações.\n";
    exit;
}

$total = 0;

// ─── 1. Vencendo em 5 dias ────────────────────────────────────────────────────
if ($liga5dias) {
    $st5 = $pdo->prepare("
        SELECT m.id AS mensalidade_id, m.aluno_id, m.vencimento, m.valor, m.referencia,
               a.nome, a.celular
        FROM mensalidades m
        JOIN alunos a ON a.id = m.aluno_id
        LEFT JOIN notificacoes_log nl
               ON nl.mensalidade_id = m.id AND nl.tipo = 'wpp_5dias'
        WHERE m.status IN ('pendente','gerado')
          AND a.status = 'ativo'
          AND DATE(m.vencimento) = ?
          AND nl.id IS NULL
    ");
    $st5->execute([$em5]);
    foreach ($st5->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (empty($r['celular'])) continue;

        $nomePrimeiro = explode(' ', trim($r['nome']))[0];
        $vencFmt      = _fmtDataBR($r['vencimento']);
        $valorFmt     = 'R$ ' . number_format((float)$r['valor'], 2, ',', '.');

        $msg  = "Olá, *{$nomePrimeiro}*! 👋\n\n";
        $msg .= "Sua mensalidade da *MPG Academy* no valor de *{$valorFmt}* vence em *5 dias* ({$vencFmt}).\n\n";
        $msg .= "Caso precise de ajuda para pagamento, entre em contato conosco.";

        if (sendWhatsApp(formatPhoneZapi($r['celular']), $msg)) {
            _logNotificacao($pdo, $r['aluno_id'], $r['mensalidade_id'], 'wpp_5dias');
            $total++;
        }
    }
} else {
    echo "Vencendo em 5 dias: desligado.\n";
}

// ─── 2. Dia do vencimento ─────────────────────────────────────────────────────
if ($ligaVencimento) {
    $stVenc = $pdo->prepare("
        SELECT m.id AS mensalidade_id, m.aluno_id, m.vencimento, m.valor, m.referencia,
               a.nome, a.celular
        FROM mensalidades m
        JOIN alunos a ON a.id = m.aluno_id
        LEFT JOIN notificacoes_log nl
               ON nl.mensalidade_id = m.id AND nl.tipo = 'wpp_vencimento'
        WHERE m.status IN ('pendente','gerado')
          AND a.status = 'ativo'
          AND DATE(m.vencimento) = ?
          AND nl.id IS NULL
    ");
    $stVenc->execute([$hoje]);
    foreach ($stVenc->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (empty($r['celular'])) continue;

        $nomePrimeiro = explode(' ', trim($r['nome']))[0];
        $vencFmt      = _fmtDataBR($r['vencimento']);
        $valorFmt     = 'R$ ' . number_format((float)$r['valor'], 2, ',', '.');

        $msg  = "Olá, *{$nomePrimeiro}*! 🔔\n\n";
        $msg .= "Sua mensalidade da *MPG Academy* no valor de *{$valorFmt}* vence *hoje* ({$vencFmt}).\n\n";
        $msg .= "Qualquer dúvida estamos à disposição!";

        if (sendWhatsApp(formatPhoneZapi($r['celular']), $msg)) {
            _logNotificacao($pdo, $r['aluno_id'], $r['mensalidade_id'], 'wpp_vencimento');
            $total++;
        }
    }
} else {
    echo "Vencimento hoje: desligado.\n";
}

// ─── 3. Atrasada (a cada 2 dias) ─────────────────────────────────────────────
if ($ligaAtraso) {
    $stAtr = $pdo->prepare("
        SELECT m.id AS mensalidade_id, m.aluno_id, m.vencimento, m.valor,
               a.nome, a.celular,
               DATEDIFF(CURDATE(), m.vencimento) AS dias_atraso,
               MAX(nl2.enviado_em) AS ultimo_envio
        FROM mensalidades m
        JOIN alunos a ON a.id = m.aluno_id
        LEFT JOIN notificacoes_log nl2
               ON nl2.mensalidade_id = m.id AND nl2.tipo = 'wpp_atraso'
        WHERE m.status = 'atrasado'
          AND a.status = 'ativo'
        GROUP BY m.id, a.nome, a.celular, m.vencimento, m.valor
        HAVING ultimo_envio IS NULL
            OR DATEDIFF(CURDATE(), DATE(ultimo_envio)) >= 2
    ");
    $stAtr->execute();
    foreach ($stAtr->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (empty($r['celular'])) continue;

        $nomePrimeiro = explode(' ', trim($r['nome']))[0];
        $vencFmt      = _fmtDataBR($r['vencimento']);
        $diasAtraso   = (int)$r['dias_atraso'];
        $valorFmt     = 'R$ ' . number_format((float)$r['valor'], 2, ',', '.');

        $msg  = "Olá, *{$nomePrimeiro}*! ⚠️\n\n";
        $msg .= "Sua mensalidade da *MPG Academy* no valor de *{$valorFmt}* está em atraso há *{$diasAtraso} dia(s)* (venceu em {$vencFmt}).\n\n";
        $msg .= "Entre em contato para regularizar. Estamos à disposição! 😊";

        if (sendWhatsApp(formatPhoneZapi($r['celular']), $msg)) {
            _logNotificacao($pdo, $r['aluno_id'], $r['mensalidade_id'], 'wpp_atraso');
            $total++;
        }
    }
} else {
    echo "Em atraso: desligado.\n";
}

echo "Notificações de mensalidade enviadas: {$total}\n";

// ─── Helpers ──────────────────────────────────────────────────────────────────

function _fmtDataBR(string $data): string {
    [$y, $m, $d] = explode('-', substr($data, 0, 10));
    $meses = ['01'=>'jan','02'=>'fev','03'=>'mar','04'=>'abr','05'=>'mai','06'=>'jun',
              '07'=>'jul','08'=>'ago','09'=>'set','10'=>'out','11'=>'nov','12'=>'dez'];
    return $d . '/' . $m . '/' . $y;
}

function _logNotificacao(PDO $pdo, int $alunoId, int $mensalidadeId, string $tipo): void {
    $pdo->prepare("
        INSERT IGNORE INTO notificacoes_log (aluno_id, mensalidade_id, tipo)
        VALUES (?, ?, ?)
    ")->execute([$alunoId, $mensalidadeId, $tipo]);
}
