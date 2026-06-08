<?php include ROOT . '/admin/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPG Academy — Meus Pagamentos</title>
    <?php include ROOT . '/admin/includes/assets.php'; ?>
</head>
<body>

<?php include ROOT . '/admin/includes/header/header.php'; ?>
<div class="adminLayout">
    <?php include ROOT . '/admin/includes/sidebar/sidebar.php'; ?>
    <main class="adminLayout__content">

        <div class="meusPgto">
            <div class="meusPgto__header">
                <h2 class="meusPgto__title">Meus <span>Pagamentos</span></h2>
                <p class="meusPgto__sub">Histórico completo de pagamentos recebidos.</p>
            </div>

            <div class="meusPgto__resumo" id="mpResumo" style="display:none">
                <div class="meusPgto__resumoCard">
                    <span class="meusPgto__resumoLabel">Total recebido</span>
                    <span class="meusPgto__resumoValor" id="mpTotalValor">—</span>
                </div>
                <div class="meusPgto__resumoCard">
                    <span class="meusPgto__resumoLabel">Pagamentos</span>
                    <span class="meusPgto__resumoValor" id="mpTotalCount">—</span>
                </div>
                <div class="meusPgto__resumoCard">
                    <span class="meusPgto__resumoLabel">Último pagamento</span>
                    <span class="meusPgto__resumoValor" id="mpUltimoPgto">—</span>
                </div>
            </div>

            <div class="meusPgto__list" id="mpList">
                <div class="meusPgto__loading">Carregando pagamentos...</div>
            </div>
        </div>

    </main>
</div>

<?php include ROOT . '/admin/includes/footer/footer.php'; ?>
<?php include ROOT . '/admin/includes/scripts.php'; ?>

<script>
var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
var BASE_URL       = "<?= BASE_URL ?>";

function fmtDate(d) {
    if (!d) return '—';
    return d.split('-').reverse().join('/');
}
function fmtValor(v) {
    return 'R$ ' + parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

fetch(ADMIN_BASE_URL + '/services/get_pagamentos_professor.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        var list = document.getElementById('mpList');

        if (!data.success || !data.pagamentos.length) {
            list.innerHTML = '<div class="meusPgto__empty">Nenhum pagamento registrado ainda.</div>';
            return;
        }

        var pagamentos = data.pagamentos;
        var total = pagamentos.reduce(function (acc, p) { return acc + parseFloat(p.valor); }, 0);

        document.getElementById('mpResumo').style.display = '';
        document.getElementById('mpTotalValor').textContent = fmtValor(total);
        document.getElementById('mpTotalCount').textContent = pagamentos.length + (pagamentos.length === 1 ? ' registro' : ' registros');
        document.getElementById('mpUltimoPgto').textContent = fmtDate(pagamentos[0].data_pagamento);

        list.innerHTML = pagamentos.map(function (p) {
            var comp = p.comprovante
                ? '<a href="' + BASE_URL + '/uploads/comprovantes/' + p.comprovante + '" target="_blank" class="meusPgto__comp">Ver comprovante ↗</a>'
                : '';
            return '<div class="meusPgto__item">' +
                '<div class="meusPgto__item-main">' +
                    '<span class="meusPgto__valor">' + fmtValor(p.valor) + '</span>' +
                    '<div class="meusPgto__meta">' +
                        '<span class="meusPgto__data">' + fmtDate(p.data_pagamento) + '</span>' +
                        (p.referencia ? '<span class="meusPgto__ref">' + p.referencia + '</span>' : '') +
                        (p.observacao ? '<span class="meusPgto__obs">' + p.observacao + '</span>' : '') +
                    '</div>' +
                '</div>' +
                comp +
            '</div>';
        }).join('');
    });
</script>
</body>
</html>
