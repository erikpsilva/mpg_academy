<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
$nivel = $_SESSION['usuario']['nivel_acesso'] ?? '';
if (!in_array($nivel, ['admin', 'editor'], true)) {
    header('Location: ' . BASE_URL . '/admin/inicio');
    exit;
}

require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

// Só turmas ativas: aviso é sobre treino que está acontecendo.
$turmas = $pdo->query("
    SELECT t.id, t.nome, q.nome AS quadra_nome,
           (SELECT COUNT(*) FROM turma_alunos ta
             JOIN alunos a ON a.id = ta.aluno_id
            WHERE ta.turma_id = t.id AND ta.status = 'ativo' AND a.status = 'ativo') AS alunos
    FROM turmas t
    LEFT JOIN quadras q ON q.id = t.quadra_id
    WHERE t.status = 'ativa'
    ORDER BY t.nome
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Avisos por WhatsApp</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="avisoWpp">

            <div class="row avisoWpp__header">
                <div class="col-md-12">
                    <h2>Avisos por <span>WhatsApp</span></h2>
                    <p>Escolha a turma, escreva a mensagem e, se quiser, anexe uma imagem. O envio é individual — cada pessoa recebe no privado, como se você tivesse mandado uma a uma.</p>
                </div>
            </div>

            <div class="avisoWpp__grid">

                <!-- ── Mensagem ──────────────────────────────────────────────── -->
                <div class="avisoWpp__col">

                    <div class="avisoWpp__card">
                        <h3><span>1</span> Turma</h3>

                        <label class="avisoWpp__field">
                            <span>Para quem vai o aviso</span>
                            <select id="avisoTurma">
                                <option value="">Escolha a turma…</option>
                                <?php foreach ($turmas as $t): ?>
                                <option value="<?= (int) $t['id'] ?>">
                                    <?= htmlspecialchars($t['nome']) ?><?= $t['quadra_nome'] ? ' · ' . htmlspecialchars($t['quadra_nome']) : '' ?>
                                    (<?= (int) $t['alunos'] ?> aluno<?= (int) $t['alunos'] === 1 ? '' : 's' ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small>Vai para o responsável cadastrado; quem não tem responsável recebe no próprio número.</small>
                        </label>
                    </div>

                    <div class="avisoWpp__card">
                        <h3><span>2</span> Mensagem</h3>

                        <label class="avisoWpp__field">
                            <span>Texto</span>
                            <textarea id="avisoTexto" rows="9" placeholder="Oi! Passando pra avisar que..."></textarea>
                            <small>
                                Formatação do WhatsApp funciona: <strong>*negrito*</strong>, <em>_itálico_</em> e emojis.
                                <span id="avisoContador">0 caracteres</span>
                            </small>
                        </label>

                        <div class="avisoWpp__field">
                            <span>Imagem (opcional)</span>
                            <input type="file" id="avisoImagem" accept="image/jpeg,image/png,image/webp">
                            <small>JPG, PNG ou WebP, até 5 MB. O texto vai como legenda da imagem.</small>

                            <div class="avisoWpp__preview" id="avisoPreview" hidden>
                                <img id="avisoPreviewImg" alt="Imagem do aviso">
                                <button type="button" class="btn btn--gray btn--sm" id="avisoRemoverImg">Remover imagem</button>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ── Destinatários e disparo ───────────────────────────────── -->
                <div class="avisoWpp__col">

                    <div class="avisoWpp__card">
                        <h3><span>3</span> Quem vai receber</h3>

                        <div class="avisoWpp__destinatarios" id="avisoDestinatarios">
                            <p class="avisoWpp__vazio">Escolha uma turma para ver a lista.</p>
                        </div>
                    </div>

                    <div class="avisoWpp__card avisoWpp__card--envio">
                        <h3><span>4</span> Disparar</h3>

                        <p class="avisoWpp__aviso">
                            Cada envio sai com alguns segundos de intervalo — é o que evita o WhatsApp
                            tratar a sequência como disparo em massa. Não feche a página durante o envio.
                        </p>

                        <p class="avisoWpp__erro" id="avisoErro" style="display:none;"></p>

                        <div class="avisoWpp__progresso" id="avisoProgresso" hidden>
                            <div class="avisoWpp__barra"><span id="avisoBarra"></span></div>
                            <small id="avisoProgressoTexto">Enviando…</small>
                        </div>

                        <button class="btn btn--primary avisoWpp__disparar" id="avisoDisparar" type="button" disabled>
                            Disparar aviso
                        </button>
                    </div>

                </div>
            </div>

        </section>

        <!-- Confirmação antes de disparar -->
        <div class="confirmModal" id="avisoModal">
            <div class="confirmModal__box">
                <h3>Confirmar disparo</h3>
                <p id="avisoModalInfo"></p>
                <div class="avisoWpp__previewMsg" id="avisoModalPreview"></div>
                <div class="confirmModal__actions">
                    <button class="btn btn--gray" id="avisoCancelar" type="button">Cancelar</button>
                    <button class="btn btn--primary" id="avisoConfirmar" type="button">Sim, disparar</button>
                </div>
            </div>
        </div>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var BASE_URL       = "<?= BASE_URL ?>";
</script>

<?php echo '<script src="' . ADMIN_BASE_URL . '/pages/avisoswhatsapp/avisoswhatsapp.js?v=' . time() . '"></script>'; ?>

</body>
</html>
