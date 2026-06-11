<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] === 'professor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once dirname(__FILE__, 3) . '/config/database.php';
$pdo = getDbConnection();

$profId = (int) ($_POST['professor_id'] ?? 0);
if (!$profId) {
    echo json_encode(['success' => false, 'message' => 'ID do professor inválido.']);
    exit;
}

$stProf = $pdo->prepare("SELECT * FROM professores WHERE id = ?");
$stProf->execute([$profId]);
$prof = $stProf->fetch(PDO::FETCH_ASSOC);
if (!$prof) {
    echo json_encode(['success' => false, 'message' => 'Professor não encontrado.']);
    exit;
}

$stHor = $pdo->prepare("
    SELECT qh.dia_semana, qh.hora_inicio, qh.hora_fim
    FROM professor_turmas pt
    JOIN turma_horarios th ON th.turma_id = pt.turma_id
    JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE pt.professor_id = ?
    ORDER BY qh.dia_semana, qh.hora_inicio
");
$stHor->execute([$profId]);
$horarios = $stHor->fetchAll(PDO::FETCH_ASSOC);

if (empty($horarios)) {
    echo json_encode(['success' => false, 'message' => 'Este professor não possui turmas/horários vinculados. Vincule as turmas antes de gerar o contrato.']);
    exit;
}

$token = bin2hex(random_bytes(32));
$html  = gerarHtmlContrato($prof, $horarios);

$pdo->prepare("DELETE FROM professor_contratos WHERE professor_id = ?")->execute([$profId]);
$pdo->prepare("
    INSERT INTO professor_contratos (professor_id, arquivo, conteudo_html, token)
    VALUES (?, NULL, ?, ?)
")->execute([$profId, $html, $token]);

echo json_encode(['success' => true, 'token' => $token]);

// ── Helpers ───────────────────────────────────────────────────────────────────

function numPt(int $n): string {
    if ($n < 0) return 'menos ' . numPt(-$n);
    $un = ['zero','um','dois','três','quatro','cinco','seis','sete','oito','nove',
           'dez','onze','doze','treze','quatorze','quinze','dezesseis','dezessete','dezoito','dezenove'];
    $dz = ['','','vinte','trinta','quarenta','cinquenta','sessenta','setenta','oitenta','noventa'];
    $ct = ['','cento','duzentos','trezentos','quatrocentos','quinhentos',
           'seiscentos','setecentos','oitocentos','novecentos'];
    if ($n === 100) return 'cem';
    if ($n < 20)   return $un[$n];
    if ($n < 100) {
        $r = $dz[(int)($n/10)]; $rem = $n%10;
        return $rem ? "$r e {$un[$rem]}" : $r;
    }
    if ($n < 1000) {
        $r = $ct[(int)($n/100)]; $rem = $n%100;
        return $rem ? "$r e " . numPt($rem) : $r;
    }
    $mil = (int)($n/1000); $rem = $n%1000;
    $r   = ($mil === 1 ? 'mil' : numPt($mil) . ' mil');
    return $rem ? "$r e " . numPt($rem) : $r;
}

function brlExtenso(float $v): string {
    $cents = (int) round($v * 100);
    $reais = (int)($cents / 100);
    $centavos = $cents % 100;
    $s = numPt($reais) . ($reais === 1 ? ' real' : ' reais');
    if ($centavos > 0) $s .= ' e ' . numPt($centavos) . ($centavos === 1 ? ' centavo' : ' centavos');
    return $s;
}

function fmtCpfStr(string $c): string {
    $d = preg_replace('/\D/', '', $c);
    return strlen($d) === 11
        ? substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2)
        : $c;
}

function gerarHtmlContrato(array $prof, array $horarios): string {
    $DIAS_FULL = ['Domingos','Segundas-feiras','Terças-feiras','Quartas-feiras','Quintas-feiras','Sextas-feiras','Sábados'];

    $nome     = trim($prof['nome'] . ' ' . ($prof['sobrenome'] ?? ''));
    $nomeCaps = mb_strtoupper($nome);
    $cpf      = fmtCpfStr($prof['cpf'] ?? '');
    $diaPgto  = (int)($prof['dia_pagamento'] ?? 5);

    $valor90      = (float)($prof['valor_aula_90min']  ?? 0);
    $valor120     = (float)($prof['valor_aula_120min'] ?? 0);
    $bonusValor   = (float)($prof['bonus_valor']  ?? 0);
    $bonusTitulo  = trim($prof['bonus_titulo'] ?? '');
    if ($bonusTitulo === '') $bonusTitulo = 'Adicional mensal';

    $has2h = false;
    foreach ($horarios as $h) {
        $hi = explode(':', $h['hora_inicio']);
        $hf = explode(':', $h['hora_fim']);
        $dur = ((int)$hf[0]*60+(int)($hf[1]??0)) - ((int)$hi[0]*60+(int)($hi[1]??0));
        if ($dur >= 110) { $has2h = true; break; }
    }

    $valorPorAula   = $has2h ? $valor120 : $valor90;
    $duracaoStr     = $has2h ? '2 (duas) horas' : '1h30 (uma hora e trinta minutos)';
    $sessCount      = count($horarios);
    $aulasMensais   = $sessCount * 4;
    $totalMensal    = $valorPorAula * $aulasMensais;

    $valorFmt = 'R$ ' . number_format($valorPorAula, 2, ',', '.');
    $totalFmt = 'R$ ' . number_format($totalMensal,  2, ',', '.');
    $valorExt = mb_strtoupper(brlExtenso($valorPorAula));
    $totalExt = mb_strtoupper(brlExtenso($totalMensal));
    $sessExt  = numPt($sessCount);
    $aulasExt = numPt($aulasMensais);

    // Adicional mensal (se houver)
    $bonusFmt       = $bonusValor > 0 ? 'R$ ' . number_format($bonusValor, 2, ',', '.') : '';
    $bonusExt       = $bonusValor > 0 ? mb_strtoupper(brlExtenso($bonusValor)) : '';
    $bonusTituloUp  = mb_strtoupper($bonusTitulo);
    $totalGeral     = $totalMensal + $bonusValor;
    $totalGeralFmt  = 'R$ ' . number_format($totalGeral, 2, ',', '.');
    $totalGeralExt  = mb_strtoupper(brlExtenso($totalGeral));

    $sessoesHtml = '';
    foreach ($horarios as $h) {
        $dow = (int)$h['dia_semana'];
        $hi  = substr($h['hora_inicio'], 0, 5);
        $hf  = substr($h['hora_fim'],    0, 5);
        $sessoesHtml .= "<p class=\"c-bullet\"><strong>{$DIAS_FULL[$dow]}:</strong> das {$hi}h às {$hf}h;</p>\n";
    }

    ob_start();
    ?>
<div class="c-doc">

<div class="c-aviso">
    <strong>Observação:</strong> este modelo foi elaborado com base nas condições informadas pelas partes
    e deve ser revisado por advogado antes da assinatura definitiva, especialmente para adequação fiscal, trabalhista e civil.
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">QUALIFICAÇÃO DAS PARTES</h2>
    <p>Pelo presente instrumento particular de Contrato de Prestação de Serviços Esportivos, de um lado,
    <strong>MPG ACADEMY</strong>, neste ato representada por <strong>ERIK PRIMÃO SILVA</strong>, inscrito no CPF
    sob nº <strong>358.572.068-47</strong>, doravante denominada simplesmente <strong>CONTRATANTE</strong>;</p>
    <p>e, de outro lado, <strong><?= $nomeCaps ?></strong>, inscrito no CPF sob nº <strong><?= $cpf ?></strong>,
    doravante denominado simplesmente <strong>CONTRATADO</strong>;</p>
    <p>têm entre si, justo e contratado, o presente instrumento que será regido pelas cláusulas e condições abaixo.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 1 &mdash; OBJETO</h2>
    <p>O presente contrato tem por objeto a prestação de serviços esportivos pelo CONTRATADO à CONTRATANTE, na qualidade de Professor de Voleibol, para ministrar treinamentos, aulas e atividades correlatas no âmbito da MPG Academy.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 2 &mdash; HORÁRIOS INICIAIS DE PRESTAÇÃO DOS SERVIÇOS</h2>
    <p>Na data de assinatura deste contrato, o CONTRATADO compromete-se a ministrar treinamentos nos seguintes dias e horários:</p>
    <?= $sessoesHtml ?>
    <p>A programação inicial corresponde a <?= $sessExt ?> (<?= $sessCount ?>) <?= $sessCount === 1 ? 'sessão semanal' : 'sessões semanais' ?>, com duração de <?= $duracaoStr ?> cada, totalizando aproximadamente <?= $aulasExt ?> (<?= $aulasMensais ?>) aulas mensais.</p>
    <p>Qualquer alteração nos horários deverá ser acordada por escrito entre as partes com antecedência mínima de 7 (sete) dias.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 3 &mdash; REMUNERAÇÃO</h2>
    <p>Pelo serviço prestado, a CONTRATANTE pagará ao CONTRATADO o valor de <strong><?= $valorFmt ?> (<?= $valorExt ?>)</strong>
    por aula de <?= $duracaoStr ?> ministrada, totalizando aproximadamente <strong><?= $totalFmt ?> (<?= $totalExt ?>)</strong> mensais,
    conforme a frequência estipulada na Cláusula 2.</p>
    <?php if ($bonusValor > 0): ?>
    <p>Adicionalmente, a CONTRATANTE pagará ao CONTRATADO o valor fixo de <strong><?= $bonusFmt ?> (<?= $bonusExt ?>)</strong>
    mensais a título de <strong><?= htmlspecialchars($bonusTituloUp) ?></strong>, independentemente do número de aulas ministradas no período,
    perfazendo uma remuneração total de <strong><?= $totalGeralFmt ?> (<?= $totalGeralExt ?>)</strong> mensais.</p>
    <?php endif; ?>
    <p>Aulas não ministradas por ausência do CONTRATADO não serão remuneradas, salvo nos casos previstos na Cláusula 6.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 4 &mdash; FORMA DE PAGAMENTO</h2>
    <p>O pagamento será realizado até o dia <strong><?= $diaPgto ?> (<?= numPt($diaPgto) ?>)</strong> de cada mês,
    preferencialmente via transferência bancária (PIX, TED ou DOC), referente às aulas ministradas no período anterior.</p>
    <p>Em caso de feriado ou final de semana no dia de vencimento, o pagamento será efetuado no primeiro dia útil seguinte.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 5 &mdash; OBRIGAÇÕES DO CONTRATADO</h2>
    <p>Ao CONTRATADO incumbe:</p>
    <p class="c-bullet">a) Estar presente nos horários e locais estipulados, preparado para ministrar os treinamentos;</p>
    <p class="c-bullet">b) Comunicar com pelo menos 48 (quarenta e oito) horas de antecedência qualquer impossibilidade de comparecimento, exceto em casos de força maior;</p>
    <p class="c-bullet">c) Conduzir as aulas com profissionalismo, comprometimento e qualidade técnica;</p>
    <p class="c-bullet">d) Respeitar alunos, responsáveis, funcionários e demais colaboradores da MPG Academy;</p>
    <p class="c-bullet">e) Zelar pelos materiais e instalações disponibilizados pela CONTRATANTE;</p>
    <p class="c-bullet">f) Manter-se atualizado quanto às técnicas de ensino do voleibol e às normas de segurança aplicáveis.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 6 &mdash; SAÚDE E SEGURANÇA</h2>
    <p>O CONTRATADO é responsável pela manutenção de sua própria saúde e integridade física para a realização das atividades. Deverá comunicar à CONTRATANTE qualquer condição que comprometa sua capacidade de ministrar as aulas, buscando, quando possível, profissional substituto qualificado.</p>
    <p>Em caso de afastamento por doença devidamente comprovada com atestado médico, as aulas serão remarcadas ou compensadas, conforme acordo entre as partes, sem penalidade ao CONTRATADO.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 7 &mdash; MATERIAIS E INFRAESTRUTURA</h2>
    <p>A CONTRATANTE disponibilizará ao CONTRATADO as instalações, quadras e materiais necessários para a realização dos treinamentos. Eventuais danos causados por negligência ou uso indevido poderão ser descontados do pagamento, mediante comunicação prévia e acordo entre as partes.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 8 &mdash; CONFIDENCIALIDADE</h2>
    <p>O CONTRATADO compromete-se a manter sigilo sobre informações estratégicas, operacionais e financeiras da MPG Academy às quais tenha acesso em razão do presente contrato, durante sua vigência e por 12 (doze) meses após o encerramento, sob pena de responsabilização civil e criminal.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 9 &mdash; IMAGEM E COMUNICAÇÃO</h2>
    <p>O CONTRATADO autoriza o uso de sua imagem e nome em materiais de divulgação da MPG Academy exclusivamente para fins institucionais, sem pagamento adicional. Qualquer publicação que mencione a MPG Academy nas redes sociais ou outros canais do CONTRATADO deverá ser previamente aprovada pela CONTRATANTE.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 10 &mdash; PRAZO DO CONTRATO</h2>
    <p>O presente contrato terá vigência por 12 (doze) meses a contar da data de sua assinatura, podendo ser renovado automaticamente por igual período, salvo manifestação em contrário de qualquer das partes com antecedência mínima de 30 (trinta) dias.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 11 &mdash; PRAZO DE AVISO PRÉVIO</h2>
    <p>Em caso de rescisão por qualquer das partes, fica estabelecido o prazo de aviso prévio de 30 (trinta) dias corridos, durante os quais o CONTRATADO continuará prestando os serviços normalmente e receberá sua remuneração integral.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 12 &mdash; RESCISÃO E MULTA</h2>
    <p>Em caso de rescisão antecipada sem justa causa por qualquer das partes — e sem observância do aviso prévio previsto na Cláusula 11 —, fica estabelecida multa equivalente ao valor de um mês de remuneração, correspondente a <strong><?= $bonusValor > 0 ? $totalGeralFmt : $totalFmt ?> (<?= $bonusValor > 0 ? $totalGeralExt : $totalExt ?>)</strong>, a ser pago à parte lesada no prazo de 10 (dez) dias após a notificação.</p>
    <p>Constituem justa causa para rescisão sem multa: descumprimento reiterado das obrigações contratuais, conduta antiética ou qualquer ato que cause dano à imagem da MPG Academy ou de seus alunos.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 13 &mdash; NÃO SOLICITAÇÃO</h2>
    <p>Durante a vigência deste contrato e por 12 (doze) meses após seu encerramento, o CONTRATADO compromete-se a não aliciar, contratar diretamente ou prestar serviços particulares aos alunos atendidos pela MPG Academy sem prévia autorização por escrito da CONTRATANTE.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 14 &mdash; REVISÃO ANUAL</h2>
    <p>Os valores acordados poderão ser revisados anualmente, a cada renovação do contrato, por acordo mútuo entre as partes, levando em consideração indicadores econômicos, desempenho do CONTRATADO e condições do mercado esportivo.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 15 &mdash; FORÇA MAIOR</h2>
    <p>Nenhuma das partes será responsabilizada por descumprimento contratual decorrente de causas além de seu controle razoável, incluindo casos fortuitos, pandemias, interdições governamentais ou desastres naturais. Nessas hipóteses, as obrigações ficarão suspensas pelo tempo necessário à superação do evento.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 16 &mdash; NATUREZA JURÍDICA</h2>
    <p>O presente contrato é firmado em caráter de prestação de serviços autônoma, não havendo vínculo empregatício, societário ou de qualquer outra natureza entre as partes. O CONTRATADO é o único responsável pelo recolhimento de seus encargos fiscais e previdenciários, conforme legislação vigente.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 17 &mdash; ANEXO CONTRATUAL</h2>
    <p>Este contrato poderá ser complementado por anexos específicos, devidamente assinados por ambas as partes, os quais passarão a integrar o presente instrumento para todos os efeitos legais.</p>
</div>

<div class="c-secao">
    <h2 class="c-secaoTitulo">CLÁUSULA 18 &mdash; FORO</h2>
    <p>As partes elegem o foro da comarca de São Paulo — SP para dirimir quaisquer controvérsias oriundas do presente contrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja.</p>
</div>

<div class="c-rodape">
    <p>São Paulo, ____ de __________________ de <?= date('Y') ?>.</p>
</div>

</div>
    <?php
    return ob_get_clean();
}
