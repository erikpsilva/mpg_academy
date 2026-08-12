<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo  = getDbConnection();
$hoje = date('Y-m-d');

$MESES = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$DIAS  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

$mesFiltro = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mesFiltro)) $mesFiltro = date('Y-m');
$rIni     = $mesFiltro . '-01';
$rFim     = date('Y-m-t', strtotime($rIni));
$dtMes    = new DateTime($rIni);
$prevMes  = (clone $dtMes)->modify('-1 month')->format('Y-m');
$nextMes  = (clone $dtMes)->modify('+1 month')->format('Y-m');
$mesLabel = $MESES[(int) $dtMes->format('n')] . ' de ' . $dtMes->format('Y');
$professorFiltro = $_GET['professor'] ?? 'todos';
if ($professorFiltro !== 'todos' && !ctype_digit((string)$professorFiltro)) $professorFiltro = 'todos';

$professoresAtivos = $pdo->query("
    SELECT id, nome, sobrenome
    FROM professores
    WHERE status = 'ativo'
    ORDER BY nome, sobrenome
")->fetchAll(PDO::FETCH_ASSOC);

function historicoAulasUrl($mes, $professorFiltro) {
    $params = ['mes' => $mes];
    if ($professorFiltro !== 'todos') $params['professor'] = $professorFiltro;
    return '?' . http_build_query($params);
}

// Turmas + horários de todos os professores ativos
$rows = $pdo->query("
    SELECT pt.professor_id, p.nome AS prof_nome, p.sobrenome AS prof_sobrenome,
           pt.turma_id AS id, pt.data_inicio, t.nome,
           qh.dia_semana, qh.hora_inicio, qh.hora_fim
    FROM professor_turmas pt
    JOIN professores p ON p.id = pt.professor_id
    JOIN turmas t ON t.id = pt.turma_id
    JOIN turma_horarios th ON th.turma_id = pt.turma_id
    JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE p.status = 'ativo'
    ORDER BY p.nome, t.nome, qh.dia_semana, qh.hora_inicio
");
$turmas = [];
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $key = $r['professor_id'] . '_' . $r['id'];
    if (!isset($turmas[$key])) {
        $turmas[$key] = [
            'professor_id' => $r['professor_id'],
            'id'           => $r['id'],
            'nome'         => trim($r['prof_nome'] . ' ' . $r['prof_sobrenome']) . ' — ' . $r['nome'],
            'data_inicio'  => $r['data_inicio'] ?? $hoje,
            'horarios'     => [],
        ];
    }
    $durMin = ((int)substr($r['hora_fim'],0,2)*60+(int)substr($r['hora_fim'],3,2))
            - ((int)substr($r['hora_inicio'],0,2)*60+(int)substr($r['hora_inicio'],3,2));
    $turmas[$key]['horarios'][] = [
        'dow' => (int)$r['dia_semana'],
        'hi'  => substr($r['hora_inicio'],0,5),
        'hf'  => substr($r['hora_fim'],0,5),
        'dur' => $durMin,
    ];
}

// Concluídas e faltas no ano, de todos os professores
$conc = []; $faltasMap = [];
$sc = $pdo->query("SELECT professor_id,turma_id,data FROM professor_aulas_concluidas WHERE data BETWEEN '$rIni' AND '$rFim'");
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) $conc[$r['professor_id'].'_'.$r['turma_id'].'_'.$r['data']] = true;

$sf = $pdo->query("SELECT professor_id,turma_id,data,tipo FROM professor_faltas WHERE data BETWEEN '$rIni' AND '$rFim'");
foreach ($sf->fetchAll(PDO::FETCH_ASSOC) as $r) $faltasMap[$r['professor_id'].'_'.$r['turma_id'].'_'.$r['data']] = $r['tipo'];

// Aulas canceladas (turma específica ou turma_id NULL = todas as turmas) — vale pra qualquer professor
$canceladasMap = []; $canceladasGlobal = [];
$sca = $pdo->query("SELECT turma_id,data,motivo FROM aulas_canceladas WHERE data BETWEEN '$rIni' AND '$rFim'");
foreach ($sca->fetchAll(PDO::FETCH_ASSOC) as $r) {
    if ($r['turma_id'] === null) $canceladasGlobal[$r['data']] = $r['motivo'];
    else                          $canceladasMap[$r['turma_id'].'_'.$r['data']] = $r['motivo'];
}

// Gera calendário
$aulas = [];
foreach ($turmas as $t) {
    if ($professorFiltro !== 'todos' && (string)$t['professor_id'] !== (string)$professorFiltro) continue;
    $ini = max($t['data_inicio'], $rIni);
    foreach ($t['horarios'] as $h) {
        $cur = strtotime($ini);
        $fim = strtotime($rFim);
        while ($cur <= $fim && (int)date('w',$cur) !== $h['dow']) $cur = strtotime('+1 day',$cur);
        while ($cur <= $fim) {
            $d      = date('Y-m-d',$cur);
            $key    = $t['professor_id'].'_'.$t['id'].'_'.$d;
            $motivo = $canceladasMap[$t['id'].'_'.$d] ?? $canceladasGlobal[$d] ?? null;
            if ($d <= $hoje) {
                if ($motivo !== null)           $st = 'cancelada';
                elseif (isset($faltasMap[$key])) $st = 'falta';
                elseif (isset($conc[$key]))      $st = 'concluida';
                else                              $st = 'pendente';
            } else {
                // Data futura: só sai do "programada" se já tiver falta ou cancelamento registrado
                if ($motivo !== null)            $st = 'cancelada';
                elseif (isset($faltasMap[$key])) $st = 'falta';
                else                              $st = 'programada';
            }
            $aulas[] = ['data'=>$d,'mes'=>substr($d,0,7),
                        'professor_id'=>$t['professor_id'],'turma_id'=>$t['id'],'turma_nome'=>$t['nome'],
                        'hi'=>$h['hi'],'hf'=>$h['hf'],'dur'=>$h['dur'],'dow'=>$h['dow'],
                        'status'=>$st,'falta_tipo'=>$faltasMap[$key]??null,'cancelada_motivo'=>$motivo];
            $cur = strtotime('+7 days',$cur);
        }
    }
}
usort($aulas, fn($a,$b)=>strcmp($a['data'].$a['hi'],$b['data'].$b['hi']));
$porMes = [];
foreach ($aulas as $a) $porMes[$a['mes']][] = $a;
ksort($porMes);

$qtdC = count(array_filter($aulas,fn($a)=>$a['status']==='concluida'));
$qtdF = count(array_filter($aulas,fn($a)=>$a['status']==='falta'));
$qtdP = count(array_filter($aulas,fn($a)=>$a['status']==='pendente'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy — Histórico de Aulas</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
<?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
<main class="adminLayout__content">

<div class="areaProfessor__welcome">
    <div>
        <h1 class="areaProfessor__title">Histórico de <span>Aulas</span></h1>
        <p class="areaProfessor__sub">Marque as aulas que já aconteceram, de todos os professores</p>
    </div>
</div>

<div class="historicoAulas__mesFiltro">
    <a href="<?= historicoAulasUrl($prevMes, $professorFiltro) ?>" class="historicoAulas__mesNav">&#8592;</a>
    <label class="historicoAulas__mesLabel">
        <?= $mesLabel ?>
        <input type="month" id="filtroMes" value="<?= $mesFiltro ?>">
    </label>
    <a href="<?= historicoAulasUrl($nextMes, $professorFiltro) ?>" class="historicoAulas__mesNav">&#8594;</a>

    <label class="historicoAulas__professorFiltro">
        <span>Professor</span>
        <select id="filtroProfessor">
            <option value="todos" <?= $professorFiltro === 'todos' ? 'selected' : '' ?>>Todos os professores</option>
            <?php foreach ($professoresAtivos as $prof): ?>
                <?php $profNome = trim($prof['nome'] . ' ' . $prof['sobrenome']); ?>
                <option value="<?= (int)$prof['id'] ?>" <?= (string)$professorFiltro === (string)$prof['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($profNome) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
</div>

<div class="minhasAulas__stats">
    <div class="minhasAulas__stat">
        <span class="minhasAulas__statNum minhasAulas__statNum--verde"><?= $qtdC ?></span>
        <span class="minhasAulas__statLabel">Concluídas</span>
    </div>
    <div class="minhasAulas__stat">
        <span class="minhasAulas__statNum minhasAulas__statNum--vermelho"><?= $qtdF ?></span>
        <span class="minhasAulas__statLabel">Faltas</span>
    </div>
    <div class="minhasAulas__stat">
        <span class="minhasAulas__statNum minhasAulas__statNum--amarelo"><?= $qtdP ?></span>
        <span class="minhasAulas__statLabel">Não registradas</span>
    </div>
</div>

<?php if (empty($aulas)): ?>
<div class="minhasAulas__vazio"><span>📅</span><p>Nenhuma aula programada para <?= $mesLabel ?>.</p></div>
<?php else: ?>
<div class="minhasAulas__lista">
<?php foreach ($porMes as $ym => $mes):
    [$y,$m] = explode('-',$ym);
?>
<div class="minhasAulas__mesGrupo">
    <div class="minhasAulas__mesHeader"><?= $MESES[(int)$m] . ' ' . $y ?></div>
    <?php foreach ($mes as $a):
        $diaN  = (int)date('d',strtotime($a['data']));
        $diaNm = $DIAS[(int)date('w',strtotime($a['data']))];
        $dur   = $a['dur'] >= 110 ? '2h00' : '1h30';
    ?>
    <div class="minhasAulas__item minhasAulas__item--<?= $a['status'] ?>"
         data-professor="<?= $a['professor_id'] ?>" data-turma="<?= $a['turma_id'] ?>" data-data="<?= $a['data'] ?>">
        <div class="minhasAulas__dataBox">
            <span class="minhasAulas__diaN"><?= $diaN ?></span>
            <span class="minhasAulas__diaNome"><?= $diaNm ?></span>
        </div>
        <div class="minhasAulas__info">
            <span class="minhasAulas__turmaNome"><?= htmlspecialchars($a['turma_nome']) ?></span>
            <span class="minhasAulas__horario"><?= $a['hi'] ?> – <?= $a['hf'] ?> <em><?= $dur ?></em></span>
        </div>
        <?php if ($a['status'] === 'cancelada'): ?>
            <div class="minhasAulas__statusTag minhasAulas__statusTag--cancelada" title="<?= htmlspecialchars($a['cancelada_motivo'] ?? '') ?>">
                🚫 Cancelada
            </div>
        <?php elseif ($a['status'] === 'falta'): ?>
            <div class="minhasAulas__statusTag minhasAulas__statusTag--falta">
                ✕ <?= $a['falta_tipo'] === 'planejada' ? 'Falta planejada' : 'Falta sem aviso' ?>
            </div>
        <?php elseif ($a['status'] === 'concluida'): ?>
            <button class="minhasAulas__btn minhasAulas__btn--concluida js-toggle">✓ Concluída</button>
        <?php elseif ($a['status'] === 'pendente'): ?>
            <button class="minhasAulas__btn minhasAulas__btn--pendente js-toggle">Marcar concluída</button>
        <?php else: ?>
            <div class="minhasAulas__statusTag minhasAulas__statusTag--programada">Programada</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>
</div>
<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var BASE_URL = "<?= BASE_URL ?>";
document.getElementById('filtroMes').addEventListener('change', function () {
    if (!this.value) return;
    var params = new URLSearchParams(window.location.search);
    params.set('mes', this.value);
    window.location.href = '?' + params.toString();
});
document.getElementById('filtroProfessor').addEventListener('change', function () {
    var params = new URLSearchParams(window.location.search);
    params.set('mes', document.getElementById('filtroMes').value || '<?= $mesFiltro ?>');
    if (this.value === 'todos') params.delete('professor');
    else params.set('professor', this.value);
    window.location.href = '?' + params.toString();
});
document.querySelectorAll('.js-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var item = this.closest('.minhasAulas__item');
        btn.disabled = true;
        var fd = new FormData();
        fd.append('professor_id', item.dataset.professor);
        fd.append('turma_id',     item.dataset.turma);
        fd.append('data',         item.dataset.data);
        fetch(BASE_URL + '/admin/services/marcar_aula_concluida.php', { method:'POST', body:fd })
            .then(r => r.json())
            .then(function(res) {
                if (res.success) { window.location.reload(); }
                else { alert(res.message || 'Erro.'); btn.disabled = false; }
            });
    });
});
</script>
</body>
</html>
