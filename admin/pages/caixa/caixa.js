/* global BASE_URL, ADMIN_BASE_URL, AirDatepicker */
(function ($) {
    'use strict';

    var ADMIN_URL = ADMIN_BASE_URL;

    var MESES_PT = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    var MESES_SHORT = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    var localePtBR = {
        days:        ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'],
        daysShort:   ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'],
        daysMin:     ['Do','Se','Te','Qa','Qi','Se','Sa'],
        months:      MESES_PT,
        monthsShort: MESES_SHORT,
        today:       'Hoje',
        clear:       'Limpar',
        dateFormat:  'MM/yyyy',
        timeFormat:  'HH:mm',
        firstDay:    0
    };

    function brl(v) {
        return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function hojeYM() {
        var now = new Date();
        var m = now.getMonth() + 1;
        return now.getFullYear() + '-' + (m < 10 ? '0' + m : m);
    }

    function toDate(ym) {
        var p = ym.split('-');
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, 1);
    }

    function toYM(date) {
        var m = date.getMonth() + 1;
        return date.getFullYear() + '-' + (m < 10 ? '0' + m : m);
    }

    function carregarCaixa(mes) {
        $.get(ADMIN_URL + '/services/get_caixa.php', { mes: mes }, function (d) {
            if (!d.success) return;

            var e = d.entradas;
            var s = d.saidas;
            var dt = toDate(d.mes);

            // Subtítulo e badge
            $('#caixaSubtitle').text(MESES_PT[dt.getMonth()] + ' de ' + dt.getFullYear());

            var $badge = $('#caixaBadge');
            if (d.aberto) {
                $badge.text('ABERTO').removeClass('caixa__badge--fechado').addClass('caixa__badge--aberto');
            } else {
                $badge.text('FECHADO').removeClass('caixa__badge--aberto').addClass('caixa__badge--fechado');
            }

            // Cards topo
            $('#cardEntradas').text(brl(e.total));
            $('#cardEntradasSub').text(e.mensalidades_qtd + ' mensalidades');
            $('#cardSaidas').text(brl(s.total));

            var saldo = d.saldo;
            $('#cardSaldo').text(brl(saldo));
            $('#cardSaldoSub').text(saldo >= 0 ? 'Saldo positivo' : 'Saldo negativo');
            $('#cardSaldoBox')
                .removeClass('caixa__card--positivo caixa__card--negativo')
                .addClass(saldo >= 0 ? 'caixa__card--positivo' : 'caixa__card--negativo');

            // Entradas
            $('#mensPagas').text(brl(e.mensalidades));
            $('#mensQtd').text(e.mensalidades_qtd + ' alunos');
            $('#receitasLanc').text(brl(e.lancamentos));
            $('#totalEntradas').text(brl(e.total));

            // Saídas
            $('#parcelasTotal').text(brl(s.parcelas));
            $('#despesasLanc').text(brl(s.lancamentos));
            $('#totalSaidas').text(brl(s.total));

            // Parcelas detalhe
            var $det = $('#parcelasDetalhe').empty();
            if (s.parcelas_detalhe && s.parcelas_detalhe.length > 0) {
                $.each(s.parcelas_detalhe, function (_, p) {
                    var venc = p.vencimento ? p.vencimento.split('-').reverse().join('/') : '';
                    $det.append(
                        '<div class="caixa__parcelaItem">' +
                            '<span>' + p.nome + ' <small>' + venc + '</small></span>' +
                            '<span>' + brl(p.valor) + '</span>' +
                        '</div>'
                    );
                });
            }
        }, 'json');
    }

    $(function () {
        var hoje = hojeYM();
        var mesSelecionado = hoje;

        var dp = new AirDatepicker('#mesCaixa', {
            locale: localePtBR,
            view: 'months',
            minView: 'months',
            autoClose: true,
            maxDate: new Date(),
            dateFormat: function (date) {
                return MESES_SHORT[date.getMonth()] + ' ' + date.getFullYear();
            },
            onSelect: function (opts) {
                if (opts.date) {
                    mesSelecionado = toYM(opts.date);
                    carregarCaixa(mesSelecionado);
                }
            }
        });

        // Seleciona mês atual no picker
        dp.selectDate(new Date());

        carregarCaixa(hoje);
    });

}(jQuery));
