<?php
/**
 * Shared helpers for professor frequency pages.
 * Used by: minha-frequencia (professor view) and frequencia-professor (admin view).
 */

function buildFrequencia(PDO $pdo, int $profId): array {
    $hoje = date('Y-m-d');
    $ano  = (int) date('Y');
    $rIni = "{$ano}-01-01";
    $rFim = "{$ano}-12-31";

    // Turmas + horários
    $rows = $pdo->prepare("
        SELECT pt.turma_id AS id, pt.data_inicio, t.nome,
               qh.dia_semana, qh.hora_inicio, qh.hora_fim
        FROM professor_turmas pt
        JOIN turmas t ON t.id = pt.turma_id
        JOIN turma_horarios th ON th.turma_id = pt.turma_id
        JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE pt.professor_id = ?
        ORDER BY t.nome, qh.dia_semana, qh.hora_inicio
    ");
    $rows->execute([$profId]);
    $turmas = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tid = $r['id'];
        if (!isset($turmas[$tid])) {
            $turmas[$tid] = ['id'=>$tid,'nome'=>$r['nome'],'data_inicio'=>$r['data_inicio']??$hoje,'horarios'=>[]];
        }
        $durMin = ((int)substr($r['hora_fim'],0,2)*60+(int)substr($r['hora_fim'],3,2))
                - ((int)substr($r['hora_inicio'],0,2)*60+(int)substr($r['hora_inicio'],3,2));
        $turmas[$tid]['horarios'][] = [
            'dow'=>(int)$r['dia_semana'],
            'hi' =>substr($r['hora_inicio'],0,5),
            'hf' =>substr($r['hora_fim'],0,5),
            'dur'=>$durMin,
        ];
    }

    // Concluídas
    $conc = [];
    $sc = $pdo->prepare("SELECT turma_id,data FROM professor_aulas_concluidas WHERE professor_id=? AND data BETWEEN ? AND ?");
    $sc->execute([$profId,$rIni,$rFim]);
    foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) $conc[$r['turma_id'].'_'.$r['data']] = true;

    // Faltas
    $faltasMap = [];
    $sf = $pdo->prepare("SELECT turma_id,data,tipo FROM professor_faltas WHERE professor_id=? AND data BETWEEN ? AND ?");
    $sf->execute([$profId,$rIni,$rFim]);
    foreach ($sf->fetchAll(PDO::FETCH_ASSOC) as $r) $faltasMap[$r['turma_id'].'_'.$r['data']] = $r['tipo'];

    // Gera aulas passadas
    $aulas = [];
    foreach ($turmas as $t) {
        $ini = max($t['data_inicio'], $rIni);
        foreach ($t['horarios'] as $h) {
            $cur = strtotime($ini);
            $fim = strtotime($rFim);
            while ($cur <= $fim && (int)date('w',$cur) !== $h['dow']) $cur = strtotime('+1 day',$cur);
            while ($cur <= $fim) {
                $d   = date('Y-m-d',$cur);
                $key = $t['id'].'_'.$d;
                if ($d > $hoje) {
                    // Datas futuras: inclui apenas se tiver falta registrada (planejada)
                    if (!isset($faltasMap[$key])) { $cur = strtotime('+7 days',$cur); continue; }
                    $st = 'falta';
                } else {
                    if (isset($faltasMap[$key]))   $st = 'falta';
                    elseif (isset($conc[$key]))    $st = 'concluida';
                    else                           $st = 'pendente';
                }
                $aulas[] = ['data'=>$d,'mes'=>substr($d,0,7),'turma_id'=>$t['id'],'turma_nome'=>$t['nome'],
                            'hi'=>$h['hi'],'hf'=>$h['hf'],'dur'=>$h['dur'],'dow'=>$h['dow'],
                            'status'=>$st,'falta_tipo'=>$faltasMap[$key]??null];
                $cur = strtotime('+7 days',$cur);
            }
        }
    }
    usort($aulas, fn($a,$b)=>strcmp($b['data'].$b['hi'],$a['data'].$a['hi'])); // desc

    $porMes = [];
    foreach ($aulas as $a) $porMes[$a['mes']][] = $a;

    $total = count($aulas);
    $qtdC  = count(array_filter($aulas,fn($a)=>$a['status']==='concluida'));
    $qtdF  = count(array_filter($aulas,fn($a)=>$a['status']==='falta'));
    $qtdP  = count(array_filter($aulas,fn($a)=>$a['status']==='pendente'));
    $taxa  = $total > 0 ? round($qtdC / max($total - $qtdP, 1) * 100) : 0;

    $stats = ['total'=>$total,'concluidas'=>$qtdC,'faltas'=>$qtdF,'pendentes'=>$qtdP,'taxa'=>$taxa];
    return [$aulas, $porMes, $stats];
}

function renderFrequenciaView(array $porMes, array $stats): void {
    $MESES = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    $DIAS  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    ?>
    <div class="freqProf__stats">
        <div class="freqProf__statCard">
            <span class="freqProf__statNum freqProf__statNum--verde"><?= $stats['concluidas'] ?></span>
            <span class="freqProf__statLabel">Aulas concluídas</span>
        </div>
        <div class="freqProf__statCard">
            <span class="freqProf__statNum freqProf__statNum--vermelho"><?= $stats['faltas'] ?></span>
            <span class="freqProf__statLabel">Faltas</span>
        </div>
        <div class="freqProf__statCard">
            <span class="freqProf__statNum freqProf__statNum--amarelo"><?= $stats['pendentes'] ?></span>
            <span class="freqProf__statLabel">Não registradas</span>
        </div>
        <div class="freqProf__statCard">
            <span class="freqProf__statNum freqProf__statNum--azul"><?= $stats['taxa'] ?>%</span>
            <span class="freqProf__statLabel">Taxa de presença</span>
        </div>
    </div>

    <?php if (empty($porMes)): ?>
    <div class="freqProf__vazio"><span>📋</span><p>Nenhuma aula registrada ainda.</p></div>
    <?php else: ?>
    <div class="freqProf__lista">
    <?php foreach ($porMes as $ym => $mes):
        [$y,$m] = explode('-',$ym);
    ?>
    <div class="freqProf__mesGrupo">
        <div class="freqProf__mesHeader"><?= $MESES[(int)$m] . ' ' . $y ?></div>
        <?php foreach ($mes as $a):
            $diaN  = (int)date('d',strtotime($a['data']));
            $diaNm = $DIAS[(int)date('w',strtotime($a['data']))];
            $dur   = $a['dur'] >= 110 ? '2h00' : '1h30';
        ?>
        <div class="freqProf__item freqProf__item--<?= $a['status'] ?>">
            <div class="freqProf__dataBox">
                <span class="freqProf__diaN"><?= $diaN ?></span>
                <span class="freqProf__diaNome"><?= $diaNm ?></span>
            </div>
            <div class="freqProf__info">
                <span class="freqProf__turmaNome"><?= htmlspecialchars($a['turma_nome']) ?></span>
                <span class="freqProf__horario"><?= $a['hi'] ?> – <?= $a['hf'] ?> <em><?= $dur ?></em></span>
            </div>
            <?php if ($a['status'] === 'falta'): ?>
                <span class="freqProf__tag freqProf__tag--falta">✕ <?= $a['falta_tipo'] === 'planejada' ? 'Planejada' : 'Sem aviso' ?></span>
            <?php elseif ($a['status'] === 'concluida'): ?>
                <span class="freqProf__tag freqProf__tag--concluida">✓ Concluída</span>
            <?php else: ?>
                <span class="freqProf__tag freqProf__tag--pendente">? Não registrada</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
}
