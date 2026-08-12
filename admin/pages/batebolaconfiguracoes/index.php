<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<?php
require_once ROOT . '/config/database.php';
$pdo = getDbConnection();

$cfgValorBatebola = $pdo->query("SELECT valor FROM configuracoes WHERE chave = 'valor_batebola'")->fetch();
$valorBatebola = $cfgValorBatebola ? (float) $cfgValorBatebola['valor'] : 17.00;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<title>MPG Academy - Admin - Bate Bola - Configurações</title>
<?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>

<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <section class="jogadores">
            <div class="row jogadores__header">
                <div class="col-md-8">
                    <h2>Bate Bola — <span>Configurações</span></h2>
                    <p>Configurações gerais do Bate Bola.</p>
                </div>
            </div>

            <div class="configSection configSection--first">
                <h3>Valor</h3>
                <div class="configCard">
                    <div class="configRow configRow--stack">
                        <div class="configRow__info">
                            <strong>Valor do Bate Bola</strong>
                            <p>Valor cobrado por participante em cada encontro. É essa informação que será exibida pro usuário.</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                            <span style="color:#aaa;font-size:14px;">R$</span>
                            <input type="number" id="inputValorBatebola" min="0" step="0.01"
                                   value="<?= number_format($valorBatebola, 2, '.', '') ?>"
                                   style="background:#1a1a1a;border:1px solid #333;border-radius:6px;color:#ddd;font-size:14px;padding:9px 12px;width:130px;">
                            <button class="btn btn--primary btn--sm" id="btnSalvarValorBatebola">Salvar</button>
                        </div>
                        <div id="valorBatebolaMsg" class="configMsg" style="margin-top:8px;"></div>
                    </div>
                </div>
            </div>

        </section>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";

(function () {
    var btn   = document.getElementById('btnSalvarValorBatebola');
    var input = document.getElementById('inputValorBatebola');
    var msg   = document.getElementById('valorBatebolaMsg');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var valor = parseFloat(input.value);
        if (isNaN(valor) || valor < 0) {
            msg.textContent = 'Informe um valor válido.';
            msg.className   = 'configMsg is-error';
            return;
        }
        btn.disabled  = true;
        msg.className = 'configMsg';

        var body = new URLSearchParams({ chave: 'valor_batebola', valor: valor.toFixed(2) });
        fetch(ADMIN_BASE_URL + '/services/save_configuracao.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                msg.textContent = 'Valor de R$ ' + valor.toFixed(2).replace('.', ',') + ' salvo.';
                msg.className   = 'configMsg is-success';
            } else {
                msg.textContent = 'Erro: ' + (data.message || '');
                msg.className   = 'configMsg is-error';
            }
            btn.disabled = false;
        })
        .catch(function () {
            msg.textContent = 'Erro ao comunicar com o servidor.';
            msg.className   = 'configMsg is-error';
            btn.disabled    = false;
        });
    });
}());
</script>

</body>
</html>
