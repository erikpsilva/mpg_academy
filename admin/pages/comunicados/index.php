<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$acao   = $_GET['acao'] ?? 'lista';
$editId = (int) ($_GET['id'] ?? 0);
$comunicado = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM comunicados WHERE id = ?");
    $st->execute([$editId]);
    $comunicado = $st->fetch();
    if (!$comunicado) { header('Location: ' . ADMIN_BASE_URL . '/comunicados'); exit; }
    $acao = 'editar';
}

$comunicados = [];
if ($acao === 'lista') {
    $comunicados = $pdo->query("SELECT * FROM comunicados ORDER BY criado_em DESC")->fetchAll();
}

$cores = ['is-blue', 'is-green', 'is-purple', 'is-orange'];
$corTag = fn(string $tag) => $tag ? $cores[abs(crc32($tag)) % count($cores)] : 'is-blue';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Comunicados</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
</head>
<body>
<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

    <?php if ($acao === 'lista'): ?>
    <!-- ── LISTA ──────────────────────────────────────────────────────────── -->
    <section class="alunos comunicados">
        <div class="row alunos__header">
            <div class="col-md-7">
                <h2>Comuni<span>cados</span></h2>
                <p>Gerencie os comunicados exibidos na área do aluno.</p>
            </div>
            <div class="col-md-5" style="display:flex;justify-content:flex-end;align-items:center;">
                <a href="<?= ADMIN_BASE_URL ?>/comunicados?acao=novo" class="btn btn--primary">+ Novo comunicado</a>
            </div>
        </div>

        <div class="comunicados__panel">
            <div class="comGrid comGrid--head">
                <span>Imagem</span><span>Título</span><span>Tag</span>
                <span>Destaque</span><span>Status</span><span>Ações</span>
            </div>
            <?php if (empty($comunicados)): ?>
            <div class="comunicados__empty">Nenhum comunicado cadastrado.</div>
            <?php endif; ?>
            <?php foreach ($comunicados as $c): ?>
            <div class="comGrid">
                <?php if ($c['imagem']): ?>
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($c['imagem']) ?>" alt="">
                <?php else: ?>
                <div class="comGrid__placeholder"></div>
                <?php endif; ?>
                <div>
                    <strong class="comGrid__title"><?= htmlspecialchars($c['titulo']) ?></strong>
                    <small class="comGrid__date"><?= date('d/m/Y', strtotime($c['criado_em'])) ?></small>
                </div>
                <span>
                    <?php if ($c['tag']): ?>
                    <span class="comTag <?= $corTag($c['tag']) ?>"><?= htmlspecialchars($c['tag']) ?></span>
                    <?php else: ?><span style="color:#444">—</span><?php endif; ?>
                </span>
                <span><?= $c['destaque'] ? '<span class="comDestaque">★ Dest.</span>' : '<span style="color:#444">—</span>' ?></span>
                <span class="<?= $c['publicado'] ? 'comStatus--pub' : 'comStatus--ras' ?>">
                    <?= $c['publicado'] ? 'Publicado' : 'Rascunho' ?>
                </span>
                <span class="comGrid__actions">
                    <a href="<?= ADMIN_BASE_URL ?>/comunicados?id=<?= $c['id'] ?>" class="btn btn--sm btn--gray">Editar</a>
                    <button class="btn btn--sm btn--error btnExcluirCom" data-id="<?= $c['id'] ?>" data-titulo="<?= htmlspecialchars($c['titulo']) ?>">Excluir</button>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php else: ?>
    <!-- ── FORMULÁRIO (NOVO / EDITAR) ─────────────────────────────────────── -->
    <section class="alunos comunicados">
        <div class="row alunos__header">
            <div class="col-md-12">
                <a href="<?= ADMIN_BASE_URL ?>/comunicados" class="alunos__back">&#8592; Voltar para Comunicados</a>
                <h2><?= $acao === 'editar' ? 'Editar' : 'Novo' ?> <span>Comunicado</span></h2>
            </div>
        </div>

        <div class="comunicados__formWrap">
            <form id="formComunicado" class="comunicados__form">
                <?php if ($comunicado): ?>
                <input type="hidden" name="id" value="<?= $comunicado['id'] ?>">
                <?php endif; ?>

                <!-- Título -->
                <div class="comunicados__field">
                    <label>Título <span class="comunicados__required">*</span></label>
                    <input type="text" name="titulo" class="input" placeholder="Título do comunicado" required
                           value="<?= htmlspecialchars($comunicado['titulo'] ?? '') ?>">
                </div>

                <!-- Imagem -->
                <div class="comunicados__field">
                    <label>Imagem de capa</label>
                    <div class="imgUploadArea" id="imgUploadArea">
                        <img id="imgPreview" src="<?= $comunicado['imagem'] ? BASE_URL . '/' . htmlspecialchars($comunicado['imagem']) : '' ?>"
                             style="<?= $comunicado['imagem'] ? 'display:block;' : '' ?>">
                        <p id="imgPlaceholder" style="<?= $comunicado['imagem'] ? 'display:none;' : '' ?>">
                            Clique ou arraste uma imagem aqui
                        </p>
                        <label class="imgLabel" for="inputImagem">Selecionar arquivo</label>
                        <input type="file" id="inputImagem" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        <input type="hidden" name="imagem" id="imagemPath" value="<?= htmlspecialchars($comunicado['imagem'] ?? '') ?>">
                    </div>
                    <div id="imgUploadMsg" style="font-size:12px;margin-top:6px;"></div>
                </div>

                <!-- Tag + Destaque + Publicado -->
                <div class="comunicados__options">
                    <div class="comunicados__field">
                        <label>Tag / Categoria</label>
                        <input type="text" name="tag" class="input" placeholder="Ex: Avisos, Uniformes"
                               value="<?= htmlspecialchars($comunicado['tag'] ?? '') ?>">
                    </div>
                    <div class="comunicados__field">
                        <label class="comunicados__check">
                            <input type="checkbox" name="destaque" value="1" <?= ($comunicado['destaque'] ?? 0) ? 'checked' : '' ?>>
                            ★ Destaque
                        </label>
                    </div>
                    <div class="comunicados__field">
                        <label class="comunicados__check">
                            <input type="checkbox" name="publicado" value="1" <?= ($comunicado['publicado'] ?? 1) ? 'checked' : '' ?>>
                            Publicado
                        </label>
                    </div>
                </div>

                <!-- Conteúdo (Quill) -->
                <div class="comunicados__field comunicados__editor">
                    <label>Conteúdo</label>
                    <div id="quillEditor"></div>
                    <input type="hidden" name="conteudo" id="conteudoHidden">
                </div>

                <div class="comunicados__actions">
                    <button type="submit" class="btn btn--primary" id="btnSalvar">Salvar comunicado</button>
                    <a href="<?= ADMIN_BASE_URL ?>/comunicados" class="btn btn--gray">Cancelar</a>
                </div>
                <div id="formMsg" class="comunicados__msg"></div>
            </form>
        </div>
    </section>
    <?php endif; ?>

    </main>
</div>

<!-- Confirm delete -->
<div class="confirmModal" id="confirmExcluirModal">
    <div class="confirmModal__box">
        <h3>Excluir Comunicado</h3>
        <p>Excluir <strong id="confirmComTitulo"></strong>? Esta ação não pode ser desfeita.</p>
        <div class="confirmModal__actions">
            <button class="btn btn--gray" id="confirmExcluirCancelar">Cancelar</button>
            <button class="btn btn--error" id="confirmExcluirOk">Excluir</button>
        </div>
    </div>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var BASE_URL       = "<?= BASE_URL ?>";

// ── Editor Quill ──────────────────────────────────────────────────────────────
<?php if ($acao !== 'lista'): ?>
var quill = new Quill('#quillEditor', {
    theme: 'snow',
    placeholder: 'Escreva o conteúdo do comunicado...',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline'],
            ['link'],
            [{ header: [2, 3, false] }],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['clean']
        ]
    }
});

<?php if (!empty($comunicado['conteudo'])): ?>
quill.clipboard.dangerouslyPasteHTML(<?= json_encode($comunicado['conteudo']) ?>);
<?php endif; ?>

// ── Upload de imagem ──────────────────────────────────────────────────────────
var inputImg   = document.getElementById('inputImagem');
var preview    = document.getElementById('imgPreview');
var placeholder = document.getElementById('imgPlaceholder');
var pathField  = document.getElementById('imagemPath');
var uploadMsg  = document.getElementById('imgUploadMsg');

inputImg.addEventListener('change', function () {
    if (!this.files[0]) return;
    var fd = new FormData();
    fd.append('imagem', this.files[0]);
    uploadMsg.textContent = 'Enviando...';
    uploadMsg.style.color = '#aaa';

    fetch(ADMIN_BASE_URL + '/services/upload_comunicado_img.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            preview.src = data.url;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
            pathField.value = data.path;
            uploadMsg.textContent = 'Imagem enviada.';
            uploadMsg.style.color = '#7ecf7e';
        } else {
            uploadMsg.textContent = data.message || 'Erro ao enviar.';
            uploadMsg.style.color = '#cf7e7e';
        }
    })
    .catch(function () {
        uploadMsg.textContent = 'Erro de comunicação.';
        uploadMsg.style.color = '#cf7e7e';
    });
});

// ── Salvar comunicado ─────────────────────────────────────────────────────────
document.getElementById('formComunicado').addEventListener('submit', function (e) {
    e.preventDefault();
    document.getElementById('conteudoHidden').value = quill.root.innerHTML;

    var btn = document.getElementById('btnSalvar');
    var msg = document.getElementById('formMsg');
    btn.disabled    = true;
    btn.textContent = 'Salvando...';
    msg.style.display = 'none';

    var fd = new FormData(this);
    fetch(ADMIN_BASE_URL + '/services/save_comunicado.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            window.location.href = ADMIN_BASE_URL + '/comunicados';
        } else {
            msg.textContent   = data.message || 'Erro ao salvar.';
            msg.style.color   = '#cf7e7e';
            msg.style.display = '';
            btn.disabled      = false;
            btn.textContent   = 'Salvar comunicado';
        }
    })
    .catch(function () {
        msg.textContent   = 'Erro de comunicação.';
        msg.style.color   = '#cf7e7e';
        msg.style.display = '';
        btn.disabled      = false;
        btn.textContent   = 'Salvar comunicado';
    });
});
<?php endif; ?>

// ── Excluir comunicado ────────────────────────────────────────────────────────
var excluirId = 0;
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btnExcluirCom');
    if (!btn) return;
    excluirId = btn.dataset.id;
    document.getElementById('confirmComTitulo').textContent = btn.dataset.titulo;
    document.getElementById('confirmExcluirModal').classList.add('confirmModal--open');
});
document.getElementById('confirmExcluirCancelar')?.addEventListener('click', function () {
    document.getElementById('confirmExcluirModal').classList.remove('confirmModal--open');
});
document.getElementById('confirmExcluirOk')?.addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    var fd = new FormData();
    fd.append('id', excluirId);
    fetch(ADMIN_BASE_URL + '/services/delete_comunicado.php', {
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
