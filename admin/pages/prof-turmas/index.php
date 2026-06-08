<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
if (($_SESSION['usuario']['nivel_acesso'] ?? '') !== 'professor') {
    header('Location: ' . ADMIN_BASE_URL . '/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo    = getDbConnection();
$profId = (int) $_SESSION['usuario']['professor_id'];

$DIAS_PT = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

// ── Turmas vinculadas ao professor ──────────────────────────────────────────
$stTurmas = $pdo->prepare("
    SELECT t.id, t.nome, t.status, t.max_alunos, t.nivel, t.genero,
           q.nome AS quadra_nome, q.bairro, q.cidade,
           GROUP_CONCAT(
               CONCAT(qh.dia_semana, '|', qh.hora_inicio, '|', qh.hora_fim)
               ORDER BY qh.dia_semana, qh.hora_inicio
               SEPARATOR ';'
           ) AS horarios_raw
    FROM professor_turmas pt
    JOIN turmas t ON t.id = pt.turma_id
    JOIN quadras q ON q.id = t.quadra_id
    LEFT JOIN turma_horarios th ON th.turma_id = t.id
    LEFT JOIN quadra_horarios qh ON qh.id = th.horario_id
    WHERE pt.professor_id = ?
    GROUP BY t.id
    ORDER BY (t.status = 'ativa') DESC, t.nome
");
$stTurmas->execute([$profId]);
$turmas = $stTurmas->fetchAll(PDO::FETCH_ASSOC);

// ── Sub-queries preparadas ──────────────────────────────────────────────────
$stAlunos = $pdo->prepare("
    SELECT a.nome, a.celular
    FROM turma_alunos ta
    JOIN alunos a ON a.id = ta.aluno_id
    WHERE ta.turma_id = ? AND ta.status = 'ativo'
    ORDER BY a.nome
");

$stExp = $pdo->prepare("
    SELECT ae.data_agendada, ae.status,
           at.nome
    FROM aulas_experimentais ae
    JOIN alunos_teste at ON at.id = ae.aluno_teste_id
    WHERE ae.turma_id = ? AND ae.status = 'agendada'
    ORDER BY ae.data_agendada ASC
    LIMIT 50
");

// ── Processa cada turma ──────────────────────────────────────────────────────
foreach ($turmas as &$t) {
    $horarios = [];
    if ($t['horarios_raw']) {
        foreach (explode(';', $t['horarios_raw']) as $slot) {
            [$dia, $hi, $hf] = explode('|', $slot);
            $durMin = ((int)substr($hf,0,2)*60 + (int)substr($hf,3,2))
                    - ((int)substr($hi,0,2)*60 + (int)substr($hi,3,2));
            $horarios[] = [
                'dia'    => $DIAS_PT[(int)$dia] ?? '',
                'inicio' => substr($hi, 0, 5),
                'fim'    => substr($hf, 0, 5),
                'dur'    => $durMin >= 110 ? '2h00' : '1h30',
            ];
        }
    }
    $t['horarios'] = $horarios;

    $stAlunos->execute([$t['id']]);
    $t['alunos'] = $stAlunos->fetchAll(PDO::FETCH_ASSOC);

    $stExp->execute([$t['id']]);
    $t['experimentais'] = $stExp->fetchAll(PDO::FETCH_ASSOC);
}
unset($t);

$totalTurmas  = count($turmas);
$totalAlunos  = array_sum(array_map(fn($t) => count($t['alunos']), $turmas));

function iniciais(string $nome): string {
    $partes = explode(' ', trim($nome));
    $a = mb_substr($partes[0] ?? '', 0, 1);
    $b = mb_substr($partes[1] ?? $partes[0] ?? '', 0, 1);
    return mb_strtoupper($a . $b);
}

function fmtDataCurta(?string $d): string {
    if (!$d) return '—';
    return date('d/m', strtotime($d));
}

$statusExpLabels = [
    'agendada'        => 'Agendada',
    'realizada'       => 'Realizada',
    'cancelada'       => 'Cancelada',
    'nao_compareceu'  => 'Não compareceu',
];
$statusExpClass = [
    'agendada'        => 'profTurma__expStatus--agendada',
    'realizada'       => 'profTurma__expStatus--realizada',
    'cancelada'       => 'profTurma__expStatus--cancelada',
    'nao_compareceu'  => 'profTurma__expStatus--falta',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy — Minhas Turmas</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <!-- Cabeçalho -->
        <div class="areaProfessor__welcome">
            <div>
                <h1 class="areaProfessor__title">Minhas <span>Turmas</span></h1>
                <p class="areaProfessor__sub">
                    <?= $totalTurmas ?> turma<?= $totalTurmas !== 1 ? 's' : '' ?> &mdash;
                    <?= $totalAlunos ?> aluno<?= $totalAlunos !== 1 ? 's' : '' ?> matriculado<?= $totalAlunos !== 1 ? 's' : '' ?>
                </p>
            </div>
            <span class="areaProfessor__badge">Professor</span>
        </div>

        <?php if (empty($turmas)): ?>
        <div class="profTurmas__vazia">
            <span class="profTurmas__vaziaIcon">🏐</span>
            <p>Você ainda não está vinculado a nenhuma turma.</p>
        </div>

        <?php else: ?>
        <div class="profTurmas__grid">

            <?php foreach ($turmas as $t): ?>
            <div class="profTurma <?= $t['status'] !== 'ativa' ? 'profTurma--inativa' : '' ?>">

                <!-- Header da turma -->
                <div class="profTurma__header">
                    <div>
                        <div class="profTurma__headerTop">
                            <h2 class="profTurma__nome"><?= htmlspecialchars($t['nome']) ?></h2>
                            <span class="profTurma__badge profTurma__badge--<?= $t['status'] ?>">
                                <?= $t['status'] === 'ativa' ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </div>
                        <div class="profTurma__loc">
                            📍 <?= htmlspecialchars($t['quadra_nome']) ?> &mdash;
                            <?= htmlspecialchars($t['bairro']) ?>, <?= htmlspecialchars($t['cidade']) ?>
                        </div>
                    </div>
                    <?php if ($t['max_alunos']): ?>
                    <div class="profTurma__capacidade">
                        <span class="profTurma__capNum"><?= count($t['alunos']) ?></span>
                        <span class="profTurma__capMax">/ <?= (int)$t['max_alunos'] ?></span>
                        <span class="profTurma__capLabel">alunos</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Horários -->
                <?php if ($t['horarios']): ?>
                <div class="profTurma__horarios">
                    <?php foreach ($t['horarios'] as $h): ?>
                    <div class="profTurma__horario">
                        <strong><?= $h['dia'] ?></strong>
                        <span><?= $h['inicio'] ?> – <?= $h['fim'] ?></span>
                        <em><?= $h['dur'] ?></em>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Alunos + Experimentais -->
                <div class="profTurma__body">

                    <!-- Alunos matriculados -->
                    <div class="profTurma__section">
                        <div class="profTurma__sectionTitle">
                            <span>Alunos matriculados</span>
                            <span class="profTurma__count"><?= count($t['alunos']) ?></span>
                        </div>

                        <?php if (empty($t['alunos'])): ?>
                        <div class="profTurma__vazio">Nenhum aluno matriculado.</div>
                        <?php else: ?>
                        <ul class="profTurma__alunoList">
                            <?php foreach ($t['alunos'] as $a): ?>
                            <li class="profTurma__alunoItem">
                                <div class="profTurma__avatar">
                                    <?= iniciais($a['nome']) ?>
                                </div>
                                <span class="profTurma__alunoNome">
                                    <?= htmlspecialchars($a['nome']) ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Aulas experimentais -->
                    <div class="profTurma__section">
                        <div class="profTurma__sectionTitle">
                            <span>Aulas experimentais</span>
                            <span class="profTurma__count"><?= count($t['experimentais']) ?></span>
                        </div>

                        <?php if (empty($t['experimentais'])): ?>
                        <div class="profTurma__vazio">Nenhuma aula experimental registrada.</div>
                        <?php else: ?>
                        <ul class="profTurma__expList">
                            <?php foreach ($t['experimentais'] as $exp):
                                $stLabel = $statusExpLabels[$exp['status']] ?? ucfirst($exp['status']);
                                $stClass = $statusExpClass[$exp['status']] ?? '';
                            ?>
                            <li class="profTurma__expItem">
                                <span class="profTurma__expData">
                                    <?= fmtDataCurta($exp['data_agendada']) ?>
                                </span>
                                <span class="profTurma__expNome">
                                    <?= htmlspecialchars($exp['nome']) ?>
                                </span>
                                <span class="profTurma__expStatus <?= $stClass ?>">
                                    <?= $stLabel ?>
                                </span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                </div><!-- /.profTurma__body -->
            </div><!-- /.profTurma -->
            <?php endforeach; ?>

        </div><!-- /.profTurmas__grid -->
        <?php endif; ?>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";</script>
</body>
</html>
