/* global BASE_URL, ADMIN_BASE_URL */
(function ($) {
    'use strict';

    var ADMIN_URL = ADMIN_BASE_URL;

    function brl(v) {
        return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function carregarCaixa() {
        $.get(ADMIN_URL + '/services/get_caixa.php', function (d) {
            if (!d.success) return;

            $('#cardEntradas').text(brl(d.entradas));
            $('#cardSaidas').text(brl(d.saidas));
            $('#cardSaldo').text(brl(d.saldo));
            $('#cardSaldoBox')
                .removeClass('caixa__card--positivo caixa__card--negativo')
                .addClass(d.saldo >= 0 ? 'caixa__card--positivo' : 'caixa__card--negativo');

            $('#cardDivida').text(brl(d.divida_pendente));
            $('#cardProfessores').text(brl(d.professores_a_pagar));
        }, 'json');
    }

    $(function () {
        carregarCaixa();
    });

}(jQuery));
