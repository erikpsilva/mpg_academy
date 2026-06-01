<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$acao      = $_GET['acao'] ?? 'lista';
$editId    = (int) ($_GET['id'] ?? 0);
$professor = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM professores WHERE id = ?");
    $st->execute([$editId]);
    $professor = $st->fetch();
    if (!$professor) { header('Location: ' . ADMIN_BASE_URL . '/professores'); exit; }
    $acao = 'editar';
}

$professores = [];
if ($acao === 'lista') {
    $professores = $pdo->query("SELECT * FROM professores ORDER BY nome, sobrenome")->fetchAll();
}

function fmtSalario(?float $v): string {
    if ($v === null) return '—';
    return 'R$ ' . number_format($v, 2, ',', '.');
}
function fmtNasc(?string $d): string {
    if (!$d) return '—';
    return date('d/m/Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Professores</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

    <?php if ($acao === 'lista'): ?>
    <!-- ── LISTA ──────────────────────────────────────────────────────────── -->
    <section class="alunos professores">
        <div class="row alunos__header">
            <div class="col-md-7">
                <h2>Profes<span>sores</span></h2>
                <p>Professores cadastrados na MPG Academy.</p>
            </div>
            <div class="col-md-5" style="display:flex;justify-content:flex-end;align-items:center;">
                <a href="<?= ADMIN_BASE_URL ?>/professores?acao=novo" class="btn btn--primary">+ Novo professor</a>
            </div>
        </div>

        <div class="professores__panel">
            <div class="profGrid profGrid--head">
                <span>Nome</span>
                <span>E-mail</span>
                <span>CPF</span>
                <span>Celular</span>
                <span>Salário</span>
                <span>Dia pgto</span>
                <span>Ações</span>
            </div>

            <?php if (empty($professores)): ?>
            <div class="professores__empty">
                Nenhum professor cadastrado.
                <a href="<?= ADMIN_BASE_URL ?>/professores?acao=novo">Cadastrar agora →</a>
            </div>
            <?php endif; ?>

            <?php foreach ($professores as $p): ?>
            <div class="profGrid">
                <div>
                    <span class="profGrid__nome"><?= htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']) ?></span>
                    <small class="profGrid__date"><?= date('d/m/Y', strtotime($p['criado_em'])) ?></small>
                </div>
                <span class="profGrid__email"><?= htmlspecialchars($p['email']) ?></span>
                <span><?= htmlspecialchars($p['cpf'] ?: '—') ?></span>
                <span><?= htmlspecialchars($p['celular'] ?: '—') ?></span>
                <span><?= fmtSalario($p['salario'] !== null ? (float)$p['salario'] : null) ?></span>
                <span><?= $p['dia_pagamento'] ? 'Dia ' . (int)$p['dia_pagamento'] : '—' ?></span>
                <div class="profGrid__actions">
                    <a href="<?= ADMIN_BASE_URL ?>/professores?id=<?= $p['id'] ?>" class="btn btn--sm btn--gray">Editar</a>
                    <button class="btn btn--sm btn--error btnExcluirProf"
                            data-id="<?= $p['id'] ?>"
                            data-nome="<?= htmlspecialchars($p['nome'] . ' ' . $p['sobrenome']) ?>">
                        Excluir
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php else: ?>
    <!-- ── FORMULÁRIO ─────────────────────────────────────────────────────── -->
    <section class="alunos professores">
        <div class="row alunos__header">
            <div class="col-md-12">
                <a href="<?= ADMIN_BASE_URL ?>/professores" class="alunos__back">&#8592; Voltar para Professores</a>
                <h2><?= $acao === 'editar' ? 'Editar' : 'Novo' ?> <span>Professor</span></h2>
            </div>
        </div>

        <form id="formProfessor" class="profForm">
            <?php if ($professor): ?>
            <input type="hidden" name="id" value="<?= $professor['id'] ?>">
            <?php endif; ?>

            <!-- Nome + Sobrenome -->
            <div class="profForm__grid">
                <div class="profForm__field">
                    <label>Nome <span>*</span></label>
                    <input type="text" name="nome" class="input" placeholder="Nome" required
                           value="<?= htmlspecialchars($professor['nome'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Sobrenome <span>*</span></label>
                    <input type="text" name="sobrenome" class="input" placeholder="Sobrenome" required
                           value="<?= htmlspecialchars($professor['sobrenome'] ?? '') ?>">
                </div>
            </div>

            <!-- Email + Senha -->
            <div class="profForm__grid">
                <div class="profForm__field">
                    <label>E-mail <span>*</span></label>
                    <input type="email" name="email" class="input" placeholder="email@exemplo.com" required
                           value="<?= htmlspecialchars($professor['email'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Senha <?= $acao === 'editar' ? '' : '<span>*</span>' ?></label>
                    <input type="password" name="senha" class="input" placeholder="<?= $acao === 'editar' ? 'Deixe em branco para não alterar' : 'Senha de acesso' ?>"
                           <?= $acao !== 'editar' ? 'required' : '' ?>>
                </div>
            </div>

            <!-- CPF + Celular + Nascimento -->
            <div class="profForm__grid profForm__grid--3">
                <div class="profForm__field">
                    <label>CPF</label>
                    <input type="text" name="cpf" id="inputCpf" class="input" placeholder="000.000.000-00" maxlength="14"
                           value="<?= htmlspecialchars($professor['cpf'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Celular</label>
                    <input type="text" name="celular" id="inputCelular" class="input" placeholder="(00) 00000-0000" maxlength="16"
                           value="<?= htmlspecialchars($professor['celular'] ?? '') ?>">
                </div>
                <div class="profForm__field">
                    <label>Data de Nascimento</label>
                    <input type="text" name="data_nascimento" id="inputNasc" class="input" placeholder="DD/MM/AAAA" maxlength="10"
                           value="<?= $professor && $professor['data_nascimento'] ? date('d/m/Y', strtotime($professor['data_nascimento'])) : '' ?>">
                </div>
            </div>

            <!-- Salário + Dia de pagamento + Status -->
            <div class="profForm__grid profForm__grid--3">
                <div class="profForm__field">
                    <label>Salário (R$)</label>
                    <input type="text" name="salario" id="inputSalario" class="input" placeholder="0,00"
                           value="<?= $professor && $professor['salario'] !== null ? number_format((float)$professor['salario'], 2, ',', '.') : '' ?>">
                </div>
                <div class="profForm__field">
                    <label>Dia de pagamento</label>
                    <select name="dia_pagamento" class="input">
                        <option value="">Selecionar...</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>" <?= ($professor['dia_pagamento'] ?? '') == $d ? 'selected' : '' ?>>
                            Dia <?= $d ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="profForm__field">
                    <label>Status</label>
                    <select name="status" class="input">
                        <option value="ativo"   <?= ($professor['status'] ?? 'ativo') === 'ativo'   ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($professor['status'] ?? '')      === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
            </div>

            <div class="profForm__actions">
                <button type="submit" class="btn btn--primary" id="btnSalvarProf">
                    <?= $acao === 'editar' ? 'Salvar alterações' : 'Cadastrar professor' ?>
                </button>
                <a href="<?= ADMIN_BASE_URL ?>/professores" class="btn btn--gray">Cancelar</a>
            </div>
            <div id="formProfMsg" class="profForm__msg"></div>
        </form>
    </section>
    <?php endif; ?>

    </main>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="confirmModal" id="confirmExcluirProfModal">
    <div class="confirmModal__box">
        <h3>Excluir Professor</h3>
        <p>Tem certeza que deseja excluir <strong id="confirmProfNome"></strong>?<br>Esta ação não pode ser desfeita.</p>
        <div class="confirmModal__actions">
            <button class="btn btn--gray" id="confirmProfCancelar">Cancelar</button>
            <button class="btn btn--error" id="confirmProfExcluir">Sim, excluir</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

// ── Máscaras ──────────────────────────────────────────────────────────────────
function maskCpf(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        this.value = v;
    });
}
function maskCelular(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 11);
        v = v.replace(/^(\d{2})(\d)/, '($1) $2');
        v = v.replace(/(\d{5})(\d{1,4})$/, '$1-$2');
        this.value = v;
    });
}
function maskData(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 8);
        v = v.replace(/(\d{2})(\d)/, '$1/$2');
        v = v.replace(/(\d{2})(\d)/, '$1/$2');
        this.value = v;
    });
}
function maskSalario(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '');
        if (!v) { this.value = ''; return; }
        v = (parseInt(v, 10) / 100).toFixed(2)
              .replace('.', ',')
              .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        this.value = v;
    });
}

var cpfEl     = document.getElementById('inputCpf');
var celEl     = document.getElementById('inputCelular');
var nascEl    = document.getElementById('inputNasc');
var salEl     = document.getElementById('inputSalario');
if (cpfEl)  maskCpf(cpfEl);
if (celEl)  maskCelular(celEl);
if (nascEl) maskData(nascEl);
if (salEl)  maskSalario(salEl);

// ── Salvar professor ──────────────────────────────────────────────────────────
var form = document.getElementById('formProfessor');
if (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btnSalvarProf');
        var msg = document.getElementById('formProfMsg');
        btn.disabled    = true;
        btn.textContent = 'Salvando...';
        msg.style.display = 'none';

        fetch(ADMIN_BASE_URL + '/services/save_professor.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: new FormData(this),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.href = ADMIN_BASE_URL + '/professores';
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
}

// ── Excluir professor ─────────────────────────────────────────────────────────
var excluirId = 0;
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnExcluirProf');
    if (!btn) return;
    excluirId = btn.dataset.id;
    document.getElementById('confirmProfNome').textContent = btn.dataset.nome;
    document.getElementById('confirmExcluirProfModal').classList.add('confirmModal--open');
});

document.getElementById('confirmProfCancelar')?.addEventListener('click', function () {
    document.getElementById('confirmExcluirProfModal').classList.remove('confirmModal--open');
    excluirId = 0;
});

document.getElementById('confirmProfExcluir')?.addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Excluindo...';
    var fd = new FormData();
    fd.append('id', excluirId);
    fetch(ADMIN_BASE_URL + '/services/delete_professor.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Erro ao excluir.');
            btn.disabled    = false;
            btn.textContent = 'Sim, excluir';
        }
    });
});
</script>

</body>
</html>
