/* global BASE_URL, AirDatepicker */
(function ($) {
    'use strict';

    var ADMIN_URL = BASE_URL + '/admin';
    var dp = null;
    var isProgrammatic = false;

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

    function toYM(date) {
        var m = date.getMonth() + 1;
        return date.getFullYear() + '-' + (m < 10 ? '0' + m : m);
    }

    function toDate(ym) {
        var p = ym.split('-');
        return new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, 1);
    }

    function addMonthsToYM(ym, n) {
        var d = toDate(ym);
        d.setMonth(d.getMonth() + n);
        return toYM(d);
    }

    function hojeYM() {
        var now = new Date();
        var m = now.getMonth() + 1;
        return now.getFullYear() + '-' + (m < 10 ? '0' + m : m);
    }

    function setRange(de, ate) {
        isProgrammatic = true;
        dp.clear();
        dp.selectDate([toDate(de), toDate(ate)]);
        isProgrammatic = false;
        carregarPrevisao(de, ate);
    }

    function carregarPrevisao(de, ate) {
        $.get(ADMIN_URL + '/services/get_previsao.php', { mes_de: de, mes_ate: ate }, function (d) {
            if (!d.success) return;

            var e = d.entradas;
            var s = d.saidas;
            var n = d.num_meses;

            var sub = n === 1
                ? MESES_PT[toDate(d.mes_de).getMonth()] + ' de ' + toDate(d.mes_de).getFullYear()
                : MESES_SHORT[toDate(d.mes_de).getMonth()] + '/' + toDate(d.mes_de).getFullYear() +
                  ' → ' +
                  MESES_SHORT[toDate(d.mes_ate).getMonth()] + '/' + toDate(d.mes_ate).getFullYear() +
                  ' (' + n + ' meses)';
            $('#previsaoSubtitle').text(sub);

            $('#cardEntradas').text(brl(e.total));
            $('#cardEntradasSub').text(brl(e.total_confirmado) + ' confirmados');
            $('#cardSaidas').text(brl(s.total));

            var saldo = d.saldo;
            $('#cardSaldo').text(brl(saldo));
            $('#cardSaldoSub').text(saldo >= 0 ? 'Saldo positivo' : 'Atenção: déficit previsto');
            $('#cardSaldoBox')
                .removeClass('previsao__card--positivo previsao__card--negativo')
                .addClass(saldo >= 0 ? 'previsao__card--positivo' : 'previsao__card--negativo');

            $('#mensPagas').text(brl(e.mensalidades_pagas));
            $('#mensPendentes').text(brl(e.mensalidades_pendentes));
            $('#mensAtrasadas').text(brl(e.mensalidades_atrasadas));
            $('#patrocinios').text(brl(e.patrocinios));
            $('#receitasLanc').text(brl(e.lancamentos));
            $('#totalEntradas').text(brl(e.total));

            $('#aluguelQuadras').text(brl(s.aluguel_quadras));
            $('#salarios').text(brl(s.salarios));
            $('#parcelasPend').text(brl(s.parcelas_pendentes));
            $('#parcelasPagas').text(brl(s.parcelas_pagas));
            $('#despesasLanc').text(brl(s.lancamentos));
            $('#totalSaidas').text(brl(s.total));

            var $det = $('#parcelasDetalhe').empty();
            if (s.parcelas_detalhe && s.parcelas_detalhe.length > 0) {
                $.each(s.parcelas_detalhe, function (_, p) {
                    var pago = p.status === 'pago' || p.status === 'adiantado';
                    var venc = p.vencimento ? p.vencimento.split('-').reverse().join('/') : '';
                    $det.append(
                        '<div class="previsao__parcelaItem' + (pago ? ' previsao__parcelaItem--paga' : '') + '">' +
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

        dp = new AirDatepicker('#mesPeriodo', {
            locale: localePtBR,
            view: 'months',
            minView: 'months',
            range: true,
            autoClose: true,
            dateFormat: function (date) {
                return MESES_SHORT[date.getMonth()] + ' ' + date.getFullYear();
            },
            multipleDatesSeparator: ' → ',
            onSelect: function (opts) {
                if (isProgrammatic) return;
                if (opts.date && opts.date.length === 2) {
                    $('.previsao__btnPeriodo').removeClass('previsao__btnPeriodo--active');
                    carregarPrevisao(toYM(opts.date[0]), toYM(opts.date[1]));
                }
            }
        });

        // Carga inicial: mês atual
        setRange(hoje, hoje);

        // Botões rápidos
        $('.previsao__btnPeriodo').on('click', function () {
            $('.previsao__btnPeriodo').removeClass('previsao__btnPeriodo--active');
            $(this).addClass('previsao__btnPeriodo--active');
            var n   = parseInt($(this).data('meses'), 10);
            var ate = addMonthsToYM(hoje, n - 1);
            setRange(hoje, ate);
        });
    });

}(jQuery));
