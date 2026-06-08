/* global BASE_URL, ADMIN_BASE_URL */
(function ($) {
    'use strict';

    function brl(v) {
        return 'R$ ' + parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(d) {
        if (!d) return '—';
        return d.split('-').reverse().join('/');
    }

    var DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    function carregarDados() {
        $.get(ADMIN_BASE_URL + '/services/get_area_professor.php', function (d) {
            if (!d.success) return;

            var p = d.professor;

            // Boas-vindas
            $('#apNome').text(p.nome);

            // Cards
            $('#apGanhosHoje').text(brl(d.total_ganhos));
            $('#apProjecao').text(brl(d.total_projecao));
            $('#apTotal').text(brl(d.total_esperado));
            if (p.pgto_date) {
                $('#apDiaPgto').text('Pagamento previsto: dia ' + p.dia_pagamento);
                $('#apProjecaoSub').text('até ' + fmtDate(p.pgto_date));
            }

            // Dados pessoais
            $('#apEmail').text(p.email || '—');
            $('#apCelular').text(p.celular || '—');
            $('#apValor90').text(p.valor_90min > 0 ? brl(p.valor_90min) + ' / aula' : '—');
            $('#apValor120').text(p.valor_120min > 0 ? brl(p.valor_120min) + ' / aula' : '—');
            $('#apDiaPgtoInfo').text(p.dia_pagamento ? 'Dia ' + p.dia_pagamento : '—');

            // Turmas
            var $turmas = $('#apTurmas').empty();
            if (!d.turmas || d.turmas.length === 0) {
                $turmas.html('<p class="areaProfessor__empty">Nenhuma turma vinculada.</p>');
                return;
            }

            $.each(d.turmas, function (_, t) {
                var horariosHtml = '';
                $.each(t.horarios, function (_, h) {
                    horariosHtml +=
                        '<div class="apHorario">' +
                            '<span class="apHorario__dia">' + h.dia_nome + '</span>' +
                            '<span class="apHorario__hora">' + h.hora_inicio + ' – ' + h.hora_fim + '</span>' +
                            '<span class="apHorario__dur">' + (h.duracao_min >= 110 ? '2h00' : '1h30') + '</span>' +
                            '<span class="apHorario__val">' + brl(h.valor_aula) + '/aula</span>' +
                            '<span class="apHorario__qtd">' + h.aulas_feitas + ' aulas realizadas</span>' +
                        '</div>';
                });

                var statusClass = t.status === 'ativa' ? 'apTurma--ativa' : 'apTurma--inativa';
                $turmas.append(
                    '<div class="apTurma ' + statusClass + '">' +
                        '<div class="apTurma__header">' +
                            '<span class="apTurma__nome">' + t.nome + '</span>' +
                            '<div class="apTurma__ganhos">' +
                                '<span>Ganhos: <strong>' + brl(t.ganhos_hoje) + '</strong></span>' +
                                '<span>Projeção: <strong>' + brl(t.projecao) + '</strong></span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="apTurma__horarios">' + horariosHtml + '</div>' +
                        '<div class="apTurma__inicio">Início nesta turma: ' + fmtDate(t.data_inicio) + '</div>' +
                    '</div>'
                );
            });
        }, 'json');
    }

    // Alterar senha
    $(function () {
        carregarDados();

        $('#formSenha').on('submit', function (e) {
            e.preventDefault();
            var btn = $('#btnSenha');
            var msg = $('#senhaMsg');
            btn.prop('disabled', true).text('Salvando...');
            msg.text('').removeClass('areaProfessor__senhaMsg--ok areaProfessor__senhaMsg--err');

            $.post(
                ADMIN_BASE_URL + '/services/update_senha_professor.php',
                new FormData(this),
                function (d) {
                    if (d.success) {
                        msg.text(d.message).addClass('areaProfessor__senhaMsg--ok');
                        document.getElementById('formSenha').reset();
                    } else {
                        msg.text(d.message).addClass('areaProfessor__senhaMsg--err');
                    }
                    btn.prop('disabled', false).text('Alterar senha');
                },
                'json'
            );
        });
    });

}(jQuery));
