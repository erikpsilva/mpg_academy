<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$turma = null;

if ($id > 0) {
    require_once ROOT . '/config/database.php';
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT t.*,
               q.nome AS quadra_nome, q.cidade, q.estado,
               q.rua, q.numero, q.bairro, q.complemento,
               COUNT(DISTINCT ta.aluno_id) AS total_alunos
        FROM turmas t
        LEFT JOIN quadras q ON q.id = t.quadra_id
        LEFT JOIN turma_alunos ta ON ta.turma_id = t.id AND ta.status = 'ativo'
        WHERE t.id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$id]);
    $turma = $stmt->fetch();

    if (!$turma) {
        header('Location: ' . BASE_URL . '/admin/aulasehorarios');
        exit;
    }

    $horStmt = $pdo->prepare("
        SELECT qh.dia_semana, qh.hora_inicio, qh.hora_fim
        FROM turma_horarios th
        JOIN quadra_horarios qh ON qh.id = th.horario_id
        WHERE th.turma_id = ?
        ORDER BY qh.dia_semana, qh.hora_inicio
    ");
    $horStmt->execute([$id]);
    $horarios = $horStmt->fetchAll();

    $alunosStmt = $pdo->prepare("
        SELECT a.id, a.nome, a.email, a.celular, ta.data_entrada
        FROM turma_alunos ta
        JOIN alunos a ON a.id = ta.aluno_id
        WHERE ta.turma_id = ? AND ta.status = 'ativo'
        ORDER BY a.nome
    ");
    $alunosStmt->execute([$id]);
    $alunosTurma = $alunosStmt->fetchAll();
}

$dias     = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$diasCurto = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Aulas e Horários</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <?php if ($turma): ?>

        <!-- ── DETALHE DA TURMA ──────────────────────────────── -->
        <section class="aulas">

            <div class="row aulas__header">
                <div class="col-md-8">
                    <a href="<?= BASE_URL ?>/admin/aulasehorarios" class="aulas__back">&#8592; Voltar para Aulas e Horários</a>
                    <h2><?= htmlspecialchars($turma['nome']) ?></h2>
                    <p><?= htmlspecialchars($turma['quadra_nome'] ?? '—') ?> &mdash; <?= htmlspecialchars(($turma['cidade'] ?? '') . ' / ' . ($turma['estado'] ?? '')) ?></p>
                </div>
                <div class="col-md-4 aulas__headerActions">
                    <span class="statusBadge statusBadge--<?= $turma['status'] === 'ativa' ? 'ativo' : 'inativo' ?>"><?= strtoupper($turma['status']) ?></span>
                </div>
            </div>

            <div class="row">

                <!-- Quadra + horários -->
                <div class="col-md-5">
                    <div class="aulas__card">
                        <h4 class="aulas__cardTitle">Local de Treino</h4>
                        <ul class="aulas__infoList">
                            <li><span>Quadra</span><strong><?= htmlspecialchars($turma['quadra_nome'] ?? '—') ?></strong></li>
                            <li><span>Endereço</span><strong><?= htmlspecialchars(($turma['rua'] ?? '') . ', ' . ($turma['numero'] ?? '')) ?><?= $turma['complemento'] ? ' — ' . htmlspecialchars($turma['complemento']) : '' ?></strong></li>
                            <li><span>Bairro / Cidade</span><strong><?= htmlspecialchars(($turma['bairro'] ?? '') . ' — ' . ($turma['cidade'] ?? '') . '/' . ($turma['estado'] ?? '')) ?></strong></li>
                        </ul>
                        <?php if ($horarios): ?>
                        <div class="aulas__horariosList">
                            <?php foreach ($horarios as $h): ?>
                            <span class="aulas__horarioBadge">
                                <?= $diasCurto[$h['dia_semana']] ?> &nbsp; <?= substr($h['hora_inicio'],0,5) ?>–<?= substr($h['hora_fim'],0,5) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Configuração -->
                <div class="col-md-7">
                    <div class="aulas__card">
                        <h4 class="aulas__cardTitle">Configuração da Turma</h4>
                        <div class="row">
                            <div class="col-md-5">
                                <div class="formGroup__item">
                                    <label for="valorMensalidade">Mensalidade (R$)</label>
                                    <input class="input" type="number" id="valorMensalidade" min="0" step="0.01"
                                           placeholder="Ex: 250"
                                           value="<?= $turma['valor_mensalidade'] !== null ? htmlspecialchars($turma['valor_mensalidade']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="formGroup__item">
                                    <label for="dataInicio">Data de Início</label>
                                    <input class="input" type="date" id="dataInicio"
                                           value="<?= htmlspecialchars($turma['data_inicio'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="formGroup__item">
                                    <label for="statusTurma">Status</label>
                                    <select class="input" id="statusTurma">
                                        <option value="ativa"  <?= $turma['status'] === 'ativa'  ? 'selected' : '' ?>>Ativa</option>
                                        <option value="inativa"<?= $turma['status'] === 'inativa' ? 'selected' : '' ?>>Inativa</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn--primary" id="btnSalvarConfig">Salvar Configurações</button>
                    </div>
                </div>

            </div>

            <!-- Alunos -->
            <div class="row">
                <div class="col-md-12">
                    <div class="aulas__card">
                        <div class="aulas__cardHeader">
                            <h4 class="aulas__cardTitle">Alunos da Turma</h4>
                            <span class="aulas__alunoCount" id="alunoCount"><?= count($alunosTurma) ?> aluno(s)</span>
                        </div>

                        <!-- Busca para adicionar aluno -->
                        <div class="aulas__addAluno">
                            <div class="aulas__searchWrap">
                                <input class="input aulas__searchInput" type="text" id="buscaAluno"
                                       placeholder="Buscar aluno por nome ou e-mail para adicionar...">
                                <div class="aulas__suggestions" id="alunoSuggestions"></div>
                            </div>
                        </div>

                        <!-- Lista de alunos da turma -->
                        <div id="alunosLista">
                            <?php if ($alunosTurma): ?>
                            <?php foreach ($alunosTurma as $a): ?>
                            <div class="aulas__alunoItem" data-aluno-id="<?= $a['id'] ?>">
                                <div class="aulas__alunoInfo">
                                    <strong class="aulas__alunoNome"><?= htmlspecialchars($a['nome']) ?></strong>
                                    <span class="aulas__alunoEmail"><?= htmlspecialchars($a['email']) ?></span>
                                </div>
                                <span class="aulas__alunoData">desde <?= date('d/m/Y', strtotime($a['data_entrada'])) ?></span>
                                <button type="button" class="btn btn--gray aulas__btnRemoverAluno" data-aluno-id="<?= $a['id'] ?>">Remover</button>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <p class="aulas__empty" id="alunosEmpty">Nenhum aluno nesta turma ainda.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendário de treinos -->
            <div class="row">
                <div class="col-md-12">
                    <div class="aulas__card">
                        <div class="aulas__cardHeader">
                            <h4 class="aulas__cardTitle">Calendário de Treinos</h4>
                            <div class="aulas__calActions">
                                <input class="input aulas__dataFimInput" type="date" id="calDataFim"
                                       value="<?= date('Y-12-31') ?>"
                                       title="Gerar até esta data">
                                <button type="button" class="btn btn--primary" id="btnGerarCal">Gerar Calendário</button>
                            </div>
                        </div>
                        <p class="aulas__calHint">Treinos são gerados automaticamente excluindo feriados nacionais. Feriados municipais/estaduais devem ser cancelados manualmente.</p>
                        <div id="calendarBody">
                            <p class="aulas__empty">Carregando calendário...</p>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <?php else: ?>

        <!-- ── LISTA DE TURMAS ────────────────────────────────── -->
        <section class="aulas">

            <div class="row aulas__header">
                <div class="col-md-7">
                    <h2>Aulas e <span>Horários</span></h2>
                    <p>Configure as turmas, mensalidades e calendário de treinos.</p>
                </div>
                <div class="col-md-5 aulas__headerActions">
                    <div class="interessados__totalCard">
                        <span class="interessados__totalNum" id="totalGeral">—</span>
                        <span class="interessados__totalLabel">Total de turmas</span>
                    </div>
                </div>
            </div>

            <div class="aulas__tools">
                <label class="aulas__searchWrapLabel" for="buscaTurmas">
                    <span>Buscar turma</span>
                    <input class="input aulas__search" type="text" id="buscaTurmas" placeholder="Nome da turma ou quadra">
                </label>
                <div class="aulas__resultMeta">
                    <span id="resultCount"></span>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="interessados__tableWrap">
                        <table class="dashTable aulas__table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Turma</th>
                                    <th>Quadra</th>
                                    <th>Horários</th>
                                    <th>Mensalidade</th>
                                    <th>Início</th>
                                    <th>Alunos</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="turmasTableBody">
                                <tr><td colspan="9" class="interessados__loading">Carregando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="aulas__pagination" id="paginacaoWrap">
                <span class="aulas__paginationLabel">Paginação</span>
                <div class="aulas__paginationControls" id="paginacaoControles"></div>
            </div>

        </section>

        <?php endif; ?>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
    var AULAS_VIEW     = "<?= $turma ? 'detalhe' : 'lista' ?>";
    var TURMA_ID       = <?= $id ?>;
</script>

<?php
$version = time();
echo '<script src="' . ADMIN_BASE_URL . '/pages/aulasehorarios/aulasehorarios.js?v=' . $version . '"></script>';
?>

</body>
</html>
