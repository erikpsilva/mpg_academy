<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'professor') {
    header('Location: ' . ADMIN_BASE_URL . '/inicio'); exit;
}

require_once ROOT . '/config/database.php';
$pdo    = getDbConnection();
$profId = (int) $_SESSION['usuario']['professor_id'];
$hoje   = date('Y-m-d');
$ano    = (int) date('Y');
$rIni   = "{$ano}-01-01";
$rFim   = "{$ano}-12-31";

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
        'dow'  => (int)$r['dia_semana'],
        'hi'   => substr($r['hora_inicio'],0,5),
        'hf'   => substr($r['hora_fim'],0,5),
        'dur'  => $durMin,
    ];
}

// Concluídas e faltas no ano
$conc = []; $faltasMap = [];
$sc = $pdo->prepare("SELECT turma_id,data FROM professor_aulas_concluidas WHERE professor_id=? AND data BETWEEN ? AND ?");
$sc->execute([$profId,$rIni,$rFim]);
foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $r) $conc[$r['turma_id'].'_'.$r['data']] = true;

$sf = $pdo->prepare("SELECT turma_id,data,tipo FROM professor_faltas WHERE professor_id=? AND data BETWEEN ? AND ?");
$sf->execute([$profId,$rIni,$rFim]);
foreach ($sf->fetchAll(PDO::FETCH_ASSOC) as $r) $faltasMap[$r['turma_id'].'_'.$r['data']] = $r['tipo'];

// Gera calendário
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
            if ($d <= $hoje) {
                if (isset($faltasMap[$key]))   $st = 'falta';
                elseif (isset($conc[$key]))    $st = 'concluida';
                else                           $st = 'pendente';
            } else { $st = 'programada'; }
            $aulas[] = ['data'=>$d,'mes'=>substr($d,0,7),'turma_id'=>$t['id'],'turma_nome'=>$t['nome'],
                        'hi'=>$h['hi'],'hf'=>$h['hf'],'dur'=>$h['dur'],'dow'=>$h['dow'],
                        'status'=>$st,'falta_tipo'=>$faltasMap[$key]??null];
            $cur = strtotime('+7 days',$cur);
        }
    }
}
usort($aulas, fn($a,$b)=>strcmp($a['data'].$a['hi'],$b['data'].$b['hi']));
$porMes = [];
foreach ($aulas as $a) $porMes[$a['mes']][] = $a;

$qtdC = count(array_filter($aulas,fn($a)=>$a['status']==='concluida'));
$qtdF = count(array_filter($aulas,fn($a)=>$a['status']==='falta'));
$qtdP = count(array_filter($aulas,fn($a)=>$a['status']==='pendente'));

$MESES = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$DIAS  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy — Minhas Aulas</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
<?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
<main class="adminLayout__content">

<div class="areaProfessor__welcome">
    <div>
        <h1 class="areaProfessor__title">Minhas <span>Aulas</span></h1>
        <p class="areaProfessor__sub"><?= $ano ?> &mdash; Marque as aulas que você concluiu</p>
    </div>
    <span class="areaProfessor__badge">Professor</span>
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
<div class="minhasAulas__vazio"><span>📅</span><p>Nenhuma aula programada para <?= $ano ?>.</p></div>
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
         data-turma="<?= $a['turma_id'] ?>" data-data="<?= $a['data'] ?>">
        <div class="minhasAulas__dataBox">
            <span class="minhasAulas__diaN"><?= $diaN ?></span>
            <span class="minhasAulas__diaNome"><?= $diaNm ?></span>
        </div>
        <div class="minhasAulas__info">
            <span class="minhasAulas__turmaNome"><?= htmlspecialchars($a['turma_nome']) ?></span>
            <span class="minhasAulas__horario"><?= $a['hi'] ?> – <?= $a['hf'] ?> <em><?= $dur ?></em></span>
        </div>
        <?php if ($a['status'] === 'falta'): ?>
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
document.querySelectorAll('.js-toggle').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var item = this.closest('.minhasAulas__item');
        btn.disabled = true;
        var fd = new FormData();
        fd.append('turma_id', item.dataset.turma);
        fd.append('data',     item.dataset.data);
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
