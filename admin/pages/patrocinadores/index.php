<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$acao         = $_GET['acao'] ?? 'lista';
$editId       = (int)($_GET['id'] ?? 0);
$patrocinador = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM patrocinadores WHERE id = ?");
    $st->execute([$editId]);
    $patrocinador = $st->fetch();
    if (!$patrocinador) { header('Location: ' . ADMIN_BASE_URL . '/patrocinadores'); exit; }
    $acao = 'editar';
}

$patrocinadores = [];
if ($acao === 'lista') {
    $patrocinadores = $pdo->query("SELECT * FROM patrocinadores ORDER BY nome_fantasia")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Patrocinadores</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

    <?php if ($acao === 'lista'): ?>
    <section class="alunos patrocinadores">
        <div class="row alunos__header">
            <div class="col-md-7">
                <h2>Patroci<span>nadores</span></h2>
                <p>Empresas parceiras e patrocinadoras da MPG Academy.</p>
            </div>
            <div class="col-md-5 patrocinadores__headerActions">
                <a href="<?= ADMIN_BASE_URL ?>/patrocinadores?acao=novo" class="btn btn--primary">+ Novo patrocinador</a>
            </div>
        </div>

        <div class="patrocinadores__listCard">
            <div class="patGrid patGrid--head">
                <span>Nome Fantasia</span><span>CNPJ</span><span>Email</span>
                <span>Valor/mês</span><span>Status</span><span>Ações</span>
            </div>
            <?php if (empty($patrocinadores)): ?>
            <div class="patrocinadores__empty">
                Nenhum patrocinador cadastrado.
                <a href="<?= ADMIN_BASE_URL ?>/patrocinadores?acao=novo">Cadastrar agora &rarr;</a>
            </div>
            <?php endif; ?>
            <?php foreach ($patrocinadores as $p): ?>
            <div class="patGrid">
                <div>
                    <span class="patGrid__nome"><?= htmlspecialchars($p['nome_fantasia']) ?></span>
                    <?php if ($p['razao_social']): ?>
                    <small class="patGrid__razao"><?= htmlspecialchars($p['razao_social']) ?></small>
                    <?php endif; ?>
                </div>
                <span class="patGrid__cnpj"><?= htmlspecialchars($p['cnpj'] ?: '-') ?></span>
                <span class="patGrid__email"><?= htmlspecialchars($p['email'] ?: '-') ?></span>
                <span class="patGrid__valor">
                    <?= $p['valor_patrocinio'] ? 'R$ ' . number_format((float)$p['valor_patrocinio'],2,',','.') : '-' ?>
                </span>
                <span>
                    <?php if ($p['status'] === 'ativo'): ?>
                    <span class="statusBadge statusBadge--ativo">Ativo</span>
                    <?php else: ?>
                    <span class="statusBadge statusBadge--inativo">Inativo</span>
                    <?php endif; ?>
                </span>
                <div class="patGrid__actions">
                    <a href="<?= ADMIN_BASE_URL ?>/patrocinadores?id=<?= $p['id'] ?>" class="btn btn--sm btn--gray">Editar</a>
                    <button class="btn btn--sm btn--error btnExcluirPat"
                            data-id="<?= $p['id'] ?>"
                            data-nome="<?= htmlspecialchars($p['nome_fantasia']) ?>">Excluir</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php else: ?>
    <section class="alunos patrocinadores">
        <div class="row alunos__header">
            <div class="col-md-12">
                <a href="<?= ADMIN_BASE_URL ?>/patrocinadores" class="alunos__back">&#8592; Voltar</a>
                <h2><?= $acao === 'editar' ? 'Editar' : 'Novo' ?> <span>Patrocinador</span></h2>
            </div>
        </div>

        <form id="formPatrocinador" class="patrocinadores__form">
            <?php if ($patrocinador): ?>
            <input type="hidden" name="id" value="<?= $patrocinador['id'] ?>">
            <?php endif; ?>

            <div class="patForm__section">Dados da empresa</div>
            <div class="patForm__grid">
                <div class="patForm__field">
                    <label>Nome Fantasia <span>*</span></label>
                    <input type="text" name="nome_fantasia" class="input" placeholder="Nome comercial" required
                           value="<?= htmlspecialchars($patrocinador['nome_fantasia'] ?? '') ?>">
                </div>
                <div class="patForm__field">
                    <label>Razão Social</label>
                    <input type="text" name="razao_social" class="input" placeholder="Razão social"
                           value="<?= htmlspecialchars($patrocinador['razao_social'] ?? '') ?>">
                </div>
            </div>
            <div class="patForm__grid patForm__grid--3">
                <div class="patForm__field">
                    <label>CNPJ</label>
                    <input type="text" name="cnpj" id="inputCnpj" class="input" placeholder="00.000.000/0000-00" maxlength="18"
                           value="<?= htmlspecialchars($patrocinador['cnpj'] ?? '') ?>">
                </div>
                <div class="patForm__field">
                    <label>E-mail</label>
                    <input type="email" name="email" class="input" placeholder="contato@empresa.com"
                           value="<?= htmlspecialchars($patrocinador['email'] ?? '') ?>">
                </div>
                <div class="patForm__field">
                    <label>Celular</label>
                    <input type="text" name="celular" id="inputCelular" class="input" placeholder="(00) 00000-0000" maxlength="16"
                           value="<?= htmlspecialchars($patrocinador['celular'] ?? '') ?>">
                </div>
            </div>

            <div class="patForm__section">Endereço</div>
            <div class="patForm__grid patForm__grid--4">
                <div class="patForm__field">
                    <label>CEP</label>
                    <input type="text" name="cep" id="inputCep" class="input" placeholder="00000-000" maxlength="9"
                           value="<?= htmlspecialchars($patrocinador['cep'] ?? '') ?>">
                    <div id="cepMsg" style="font-size:11px;margin-top:4px;min-height:14px;"></div>
                </div>
                <div class="patForm__field patForm__field--span2">
                    <label>Rua / Logradouro</label>
                    <input type="text" name="rua" class="input" placeholder="Rua, Av., etc."
                           value="<?= htmlspecialchars($patrocinador['rua'] ?? '') ?>">
                </div>
                <div class="patForm__field">
                    <label>Número</label>
                    <input type="text" name="numero" class="input" placeholder="Nº"
                           value="<?= htmlspecialchars($patrocinador['numero'] ?? '') ?>">
                </div>
            </div>
            <div class="patForm__grid patForm__grid--4">
                <div class="patForm__field patForm__field--span2">
                    <label>Bairro</label>
                    <input type="text" name="bairro" class="input" placeholder="Bairro"
                           value="<?= htmlspecialchars($patrocinador['bairro'] ?? '') ?>">
                </div>
                <div class="patForm__field">
                    <label>Cidade</label>
                    <input type="text" name="cidade" class="input" placeholder="Cidade"
                           value="<?= htmlspecialchars($patrocinador['cidade'] ?? '') ?>">
                </div>
                <div class="patForm__field patForm__field--uf">
                    <label>Estado (UF)</label>
                    <input type="text" name="estado" class="input" placeholder="SP" maxlength="2"
                           value="<?= htmlspecialchars($patrocinador['estado'] ?? '') ?>">
                </div>
            </div>
            <?php if ($patrocinador && $patrocinador['complemento']): ?>
            <div class="patForm__grid patForm__grid--single">
                <div class="patForm__field">
                    <label>Complemento</label>
                    <input type="text" name="complemento" class="input" placeholder="Sala, andar, etc."
                           value="<?= htmlspecialchars($patrocinador['complemento'] ?? '') ?>">
                </div>
            </div>
            <?php endif; ?>

            <div class="patForm__section">Patrocínio</div>
            <div class="patForm__grid patForm__grid--3">
                <div class="patForm__field">
                    <label>Valor do Patrocínio (R$/mês)</label>
                    <input type="text" name="valor_patrocinio" id="inputValor" class="input" placeholder="0,00"
                           value="<?= $patrocinador && $patrocinador['valor_patrocinio'] ? number_format((float)$patrocinador['valor_patrocinio'],2,',','.') : '' ?>">
                </div>
                <div class="patForm__field">
                    <label>Status</label>
                    <select name="status" class="input">
                        <option value="ativo"   <?= ($patrocinador['status'] ?? 'ativo') === 'ativo'   ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($patrocinador['status'] ?? '')      === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="patForm__field">
                    <label>Observação</label>
                    <input type="text" name="observacao" class="input" placeholder="Opcional"
                           value="<?= htmlspecialchars($patrocinador['observacao'] ?? '') ?>">
                </div>
            </div>

            <div class="patrocinadores__actions">
                <button type="submit" class="btn btn--primary" id="btnSalvarPat">
                    <?= $acao === 'editar' ? 'Salvar alterações' : 'Cadastrar patrocinador' ?>
                </button>
                <a href="<?= ADMIN_BASE_URL ?>/patrocinadores" class="btn btn--gray">Cancelar</a>
            </div>
            <div id="formPatMsg" class="patrocinadores__message"></div>
        </form>
    </section>
    <?php endif; ?>

    </main>
</div>

<div class="confirmModal" id="confirmPatModal">
    <div class="confirmModal__box">
        <h3>Excluir Patrocinador</h3>
        <p>Excluir <strong id="confirmPatNome"></strong>? Esta ação não pode ser desfeita.</p>
        <div class="confirmModal__actions">
            <button class="btn btn--gray" id="confirmPatCancelar">Cancelar</button>
            <button class="btn btn--error" id="confirmPatOk">Sim, excluir</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

function maskCnpj(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g,'').slice(0,14);
        v = v.replace(/^(\d{2})(\d)/,'$1.$2');
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/,'.$1/$2');
        v = v.replace(/(\d{4})(\d)/,'$1-$2');
        this.value = v;
    });
}
function maskCelular(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g,'').slice(0,11);
        v = v.replace(/^(\d{2})(\d)/,'($1) $2');
        v = v.replace(/(\d{5})(\d{1,4})$/,'$1-$2');
        this.value = v;
    });
}
function maskCep(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g,'').slice(0,8);
        v = v.replace(/(\d{5})(\d)/,'$1-$2');
        this.value = v;
    });
    // ViaCEP: busca endereço ao sair do campo com CEP completo
    el.addEventListener('blur', function () {
        var cep = this.value.replace(/\D/g,'');
        if (cep.length !== 8) return;

        var msgEl = document.getElementById('cepMsg');
        msgEl.textContent = 'Buscando CEP...';
        msgEl.style.color = '#888';

        fetch('https://viacep.com.br/ws/' + cep + '/json/')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.erro) {
                msgEl.textContent = 'CEP não encontrado.';
                msgEl.style.color = '#cf7e7e';
                return;
            }
            var f = document.getElementById('formPatrocinador');
            if (!f) return;
            var set = function (name, val) {
                var el = f.querySelector('[name=' + name + ']');
                if (el && val) el.value = val;
            };
            set('rua',    data.logradouro);
            set('bairro', data.bairro);
            set('cidade', data.localidade);
            set('estado', data.uf);

            msgEl.textContent = '✓ Endereço preenchido automaticamente.';
            msgEl.style.color = '#7ecf7e';
            setTimeout(function () { msgEl.textContent = ''; }, 3000);

            // Foca no campo número para o admin completar
            var numEl = f.querySelector('[name=numero]');
            if (numEl) numEl.focus();
        })
        .catch(function () {
            msgEl.textContent = 'Erro ao consultar CEP.';
            msgEl.style.color = '#cf7e7e';
        });
    });
}
function maskValor(el) {
    el.addEventListener('input', function () {
        var v = this.value.replace(/\D/g,'');
        if (!v) { this.value=''; return; }
        v = (parseInt(v,10)/100).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
        this.value = v;
    });
}

var cnpjEl  = document.getElementById('inputCnpj');
var celEl   = document.getElementById('inputCelular');
var cepEl   = document.getElementById('inputCep');
var valEl   = document.getElementById('inputValor');
if (cnpjEl) maskCnpj(cnpjEl);
if (celEl)  maskCelular(celEl);
if (cepEl)  maskCep(cepEl);
if (valEl)  maskValor(valEl);

var form = document.getElementById('formPatrocinador');
if (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('btnSalvarPat');
        var msg = document.getElementById('formPatMsg');
        btn.disabled = true; btn.textContent = 'Salvando...';
        msg.style.display = 'none';
        fetch(ADMIN_BASE_URL + '/services/save_patrocinador.php', {
            method:'POST', credentials:'same-origin', body: new FormData(this),
        }).then(r => r.json()).then(d => {
            if (d.success) window.location.href = ADMIN_BASE_URL + '/patrocinadores';
            else { msg.textContent = d.message||'Erro.'; msg.style.color='#cf7e7e'; msg.style.display=''; btn.disabled=false; btn.textContent='Salvar'; }
        });
    });
}

var excluirId = 0;
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnExcluirPat');
    if (!btn) return;
    excluirId = btn.dataset.id;
    document.getElementById('confirmPatNome').textContent = btn.dataset.nome;
    document.getElementById('confirmPatModal').classList.add('confirmModal--open');
});
document.getElementById('confirmPatCancelar')?.addEventListener('click', function () {
    document.getElementById('confirmPatModal').classList.remove('confirmModal--open');
});
document.getElementById('confirmPatOk')?.addEventListener('click', function () {
    var btn = this; btn.disabled = true;
    var fd = new FormData(); fd.append('id', excluirId);
    fetch(ADMIN_BASE_URL + '/services/delete_patrocinador.php', {
        method:'POST', credentials:'same-origin', body: fd,
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else { alert(d.message||'Erro.'); btn.disabled=false; }
    });
});
</script>
</body>
</html>
