<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$turmas = $pdo->query("SELECT id, nome FROM turmas WHERE status = 'ativa' ORDER BY nome")->fetchAll();

$filtroTurma = (int) ($_GET['turma_id'] ?? 0);
$filtroMes   = trim($_GET['mes'] ?? '');

$where  = [];
$params = [];

if ($filtroTurma > 0) {
    $where[]  = '(ac.turma_id = ? OR ac.turma_id IS NULL)';
    $params[] = $filtroTurma;
}
if (preg_match('/^\d{4}-\d{2}$/', $filtroMes)) {
    $where[]  = 'DATE_FORMAT(ac.data, "%Y-%m") = ?';
    $params[] = $filtroMes;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$canceladas = $pdo->prepare("
    SELECT ac.id, ac.data, ac.motivo, ac.criado_em,
           t.nome AS turma_nome
    FROM aulas_canceladas ac
    LEFT JOIN turmas t ON t.id = ac.turma_id
    $whereClause
    ORDER BY ac.data DESC
");
$canceladas->execute($params);
$canceladas = $canceladas->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Aulas Canceladas</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<style>
.acTable { width: 100%; border-collapse: collapse; margin-top: 8px; }
.acTable th { background: #1a1a1a; color: #aaa; font-size: 12px; font-weight: 600;
              text-align: left; padding: 10px 12px; border-bottom: 1px solid #2a2a2a; }
.acTable td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid #1e1e1e; vertical-align: middle; }
.acTable tr:hover td { background: #161616; }
.acTag { display: inline-block; background: #1f1f2e; color: #9b9bcf; border: 1px solid #2a2a4a;
          border-radius: 4px; font-size: 11px; padding: 2px 8px; }
.acTag--all { background: #1f2a1f; color: #7ecf7e; border-color: #2a4a2a; }
.acFilters { display: flex; gap: 10px; margin-bottom: 16px; align-items: flex-end; flex-wrap: wrap; }
.acFilters label { font-size: 12px; color: #888; display: block; margin-bottom: 4px; }
.acFilters select, .acFilters input[type="month"] { background: #1a1a1a; border: 1px solid #333;
    border-radius: 6px; color: #ddd; font-size: 13px; padding: 8px 10px; }
.acForm { background: #111; border: 1px solid #222; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; }
.acForm h3 { font-size: 15px; font-weight: 700; margin: 0 0 16px; }
.acFields { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.acFields .field { display: flex; flex-direction: column; gap: 4px; }
.acFields label { font-size: 12px; color: #888; }
.acFields input, .acFields select, .acFields textarea {
    background: #1a1a1a; border: 1px solid #333; border-radius: 6px;
    color: #ddd; font-size: 13px; padding: 9px 12px; }
.acFields textarea { resize: vertical; min-height: 38px; }
.acEmpty { color: #555; font-size: 13px; padding: 24px 0; text-align: center; }
</style>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

    <section class="alunos">
        <div class="row alunos__header">
            <div class="col-md-12">
                <h2>Aulas <span>Canceladas</span></h2>
                <p>Registre datas sem aula (feriados ou outros motivos). Essas datas são descontadas do cálculo proporcional.</p>
            </div>
        </div>

        <!-- Formulário de registro -->
        <div class="acForm">
            <h3>Registrar data sem aula</h3>
            <form id="formCancelar">
                <div class="acFields">
                    <div class="field">
                        <label>Data <span style="color:#e55">*</span></label>
                        <input type="date" name="data" id="inputData" required
                               style="min-width:150px;"
                               value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="field">
                        <label>Turma</label>
                        <select name="turma_id" id="inputTurma" style="min-width:200px;">
                            <option value="">Todas as turmas</option>
                            <?php foreach ($turmas as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Motivo</label>
                        <input type="text" name="motivo" placeholder="Ex: Feriado, Evento..." style="min-width:240px;">
                    </div>
                    <div class="field">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn--primary" id="btnSalvar">Salvar</button>
                    </div>
                </div>
                <div id="formMsg" style="font-size:13px;margin-top:10px;display:none;"></div>
            </form>
        </div>

        <!-- Filtros -->
        <form method="GET" action="" class="acFilters">
            <div>
                <label>Filtrar por turma</label>
                <select name="turma_id" onchange="this.form.submit()">
                    <option value="">Todas</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filtroTurma === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Filtrar por mês</label>
                <input type="month" name="mes" value="<?= htmlspecialchars($filtroMes) ?>" onchange="this.form.submit()">
            </div>
            <?php if ($filtroTurma || $filtroMes): ?>
            <a href="<?= ADMIN_BASE_URL ?>/aulas-canceladas" class="btn btn--gray btn--sm" style="margin-bottom:0;">Limpar filtros</a>
            <?php endif; ?>
        </form>

        <!-- Tabela -->
        <?php if (empty($canceladas)): ?>
        <div class="acEmpty">Nenhuma aula cancelada registrada<?= ($filtroTurma || $filtroMes) ? ' para este filtro' : '' ?>.</div>
        <?php else: ?>
        <table class="acTable">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Dia da semana</th>
                    <th>Turma</th>
                    <th>Motivo</th>
                    <th>Registrado em</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $diaSemanaLabel = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
            foreach ($canceladas as $ac):
                $dt  = new DateTime($ac['data']);
                $dow = (int) $dt->format('w');
            ?>
            <tr>
                <td><strong><?= $dt->format('d/m/Y') ?></strong></td>
                <td><?= $diaSemanaLabel[$dow] ?></td>
                <td>
                    <?php if ($ac['turma_nome']): ?>
                    <span class="acTag"><?= htmlspecialchars($ac['turma_nome']) ?></span>
                    <?php else: ?>
                    <span class="acTag acTag--all">Todas</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($ac['motivo'] ?? '—') ?></td>
                <td style="color:#666;font-size:12px;"><?= date('d/m/Y', strtotime($ac['criado_em'])) ?></td>
                <td>
                    <button class="btn btn--sm btn--error btnExcluir"
                            data-id="<?= $ac['id'] ?>"
                            data-data="<?= $dt->format('d/m/Y') ?>">
                        Excluir
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    </main>
</div>

<!-- Modal confirmação excluir -->
<div class="confirmModal" id="modalExcluir">
    <div class="confirmModal__box">
        <h3>Excluir aula cancelada</h3>
        <p>Remover o cancelamento do dia <strong id="confirmData"></strong>?</p>
        <div class="confirmModal__actions">
            <button class="btn btn--gray" id="btnCancelarModal">Cancelar</button>
            <button class="btn btn--error" id="btnConfirmarExcluir">Excluir</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var excluirId = 0;

// ── Salvar ────────────────────────────────────────────────────────────────────
document.getElementById('formCancelar').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('btnSalvar');
    var msg = document.getElementById('formMsg');
    btn.disabled    = true;
    btn.textContent = 'Salvando...';
    msg.style.display = 'none';

    var fd = new FormData(this);
    fetch(ADMIN_BASE_URL + '/services/save_aula_cancelada.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            window.location.reload();
        } else {
            msg.textContent   = data.message || 'Erro ao salvar.';
            msg.style.color   = '#cf7e7e';
            msg.style.display = '';
            btn.disabled      = false;
            btn.textContent   = 'Salvar';
        }
    })
    .catch(function () {
        msg.textContent   = 'Erro de comunicação.';
        msg.style.color   = '#cf7e7e';
        msg.style.display = '';
        btn.disabled      = false;
        btn.textContent   = 'Salvar';
    });
});

// ── Excluir ───────────────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnExcluir');
    if (!btn) return;
    excluirId = btn.dataset.id;
    document.getElementById('confirmData').textContent = btn.dataset.data;
    document.getElementById('modalExcluir').classList.add('confirmModal--open');
});

document.getElementById('btnCancelarModal').addEventListener('click', function () {
    document.getElementById('modalExcluir').classList.remove('confirmModal--open');
});

document.getElementById('btnConfirmarExcluir').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('id', excluirId);
    fetch(ADMIN_BASE_URL + '/services/delete_aula_cancelada.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) window.location.reload();
        else { alert(data.message || 'Erro ao excluir.'); btn.disabled = false; }
    });
});
</script>
</body>
</html>
