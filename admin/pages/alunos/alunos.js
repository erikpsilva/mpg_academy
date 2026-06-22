
// ─── LISTA ───────────────────────────────────────────────────────────────────

const formatData = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return isNaN(d.getTime()) ? val : d.toLocaleDateString('pt-BR');
};

const fmt = (n) => n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const badgeSimples = (efetivo, base) => {
    const temDesc = base - efetivo > 0.001;
    if (temDesc) {
        return '<span class="alunos__mensalidadeBadge alunos__mensalidadeBadge--desconto">' +
                   '<span class="alunos__mensalidadeOriginal">R$&nbsp;' + fmt(base) + '</span>' +
                   '<span class="alunos__mensalidadeValor">R$&nbsp;<strong>' + fmt(efetivo) + '</strong><em>/m&ecirc;s</em></span>' +
                   '<span class="alunos__descontoBadge">DESC</span>' +
               '</span>';
    }
    return '<span class="alunos__mensalidadeBadge">' +
               '<span class="alunos__mensalidadeValor">R$&nbsp;<strong>' + fmt(efetivo) + '</strong><em>/m&ecirc;s</em></span>' +
           '</span>';
};

const renderMensalidade = (detalhes) => {
    if (!detalhes) return '<span class="alunos__semTurmaLabel">—</span>';

    const turmas = detalhes.split('|').map(item => {
        const p = item.split('~');
        return { nome: p[0], efetivo: parseFloat(p[1]), base: parseFloat(p[2]) };
    }).filter(t => t.base > 0);

    if (turmas.length === 0) return '<span class="alunos__semTurmaLabel">—</span>';

    if (turmas.length === 1) {
        return badgeSimples(turmas[0].efetivo, turmas[0].base);
    }

    const totalEfetivo = turmas.reduce((s, t) => s + t.efetivo, 0);
    const totalBase    = turmas.reduce((s, t) => s + t.base, 0);
    const temDescTotal = totalBase - totalEfetivo > 0.001;

    let html = '<div class="alunos__mensalidadeDetalhes">';
    turmas.forEach(t => {
        const temDesc = t.base - t.efetivo > 0.001;
        html += '<div class="alunos__mensalidadeItem">' +
                    '<span class="alunos__mensalidadeItemNome">' + $('<span>').text(t.nome).html() + '</span>' +
                    '<span class="alunos__mensalidadeItemValor">';
        if (temDesc) {
            html += '<s>R$&nbsp;' + fmt(t.base) + '</s>&nbsp;<strong>' + fmt(t.efetivo) + '</strong>';
        } else {
            html += '<strong>R$&nbsp;' + fmt(t.efetivo) + '</strong>';
        }
        html += '</span></div>';
    });
    html += '<div class="alunos__mensalidadeTotal">' +
                (temDescTotal ? '<s>R$&nbsp;' + fmt(totalBase) + '</s>&nbsp;' : '') +
                'Total: <strong>R$&nbsp;' + fmt(totalEfetivo) + '</strong>/m&ecirc;s' +
            '</div>';
    html += '</div>';
    return html;
};

const renderTabela = (registros) => {
    const tbody = $('#alunosTableBody');
    if (!registros || registros.length === 0) {
        tbody.html('<tr><td colspan="7" class="interessados__empty">Nenhum aluno encontrado.</td></tr>');
        return;
    }
    const rows = registros.map(r => {
        const turmaCell = r.turmas_nomes
            ? '<span class="alunos__turmaBadge">' + $('<span>').text(r.turmas_nomes).html() + '</span>'
            : '<span class="alunos__semTurmaLabel">—</span>';

        const dias = parseInt(r.max_dias_atraso) || 0;
        const atrasoBadge = dias > 0
            ? '<span style="display:inline-flex;align-items:center;gap:3px;margin-top:5px;padding:2px 8px;'
              + 'background:' + (dias >= 25 ? 'rgba(255,68,68,.15)' : 'rgba(255,140,0,.13)') + ';'
              + 'border:1px solid ' + (dias >= 25 ? 'rgba(255,68,68,.55)' : 'rgba(255,140,0,.45)') + ';'
              + 'border-radius:4px;font-size:10px;font-weight:900;text-transform:uppercase;'
              + 'color:' + (dias >= 25 ? '#ff5a5a' : '#ff9a1e') + ';white-space:nowrap;">'
              + '⚠ ' + dias + 'd em atraso'
              + (dias >= 25 ? ' — bloquear em ' + Math.max(0, 30 - dias) + 'd' : '')
              + '</span>'
            : '';

        return '<tr>' +
            '<td class="interessados__id">' + r.id + '</td>' +
            '<td class="alunos__cellNome"><strong>' + $('<span>').text(r.nome).html() + '</strong>' +
                (atrasoBadge ? '<br>' + atrasoBadge : '') + '</td>' +
            '<td class="alunos__cellEmail">' + $('<span>').text(r.email).html() + '</td>' +
            '<td class="alunos__cellTurma">' + turmaCell + '</td>' +
            '<td class="alunos__cellMensalidade">' + renderMensalidade(r.mensalidade_detalhes) + '</td>' +
            '<td class="alunos__cellStatus"><span class="statusBadge statusBadge--' + r.status + '">' + r.status.toUpperCase() + '</span></td>' +
            '<td class="alunos__cellAcoes"><div class="alunos__actions"><a href="' + BASE_URL + '/admin/alunos?id=' + r.id + '" class="btn btn--gray alunos__btnDetalhe">Ver detalhes</a></div></td>' +
        '</tr>';
    }).join('');
    tbody.html(rows);
};

const renderPaginacao = (pagina, totalPaginas) => {
    const wrap = $('#paginacaoControles');
    let html = '';
    html += '<button class="btn btn--pag" ' + (pagina > 1 ? 'data-pag="' + (pagina - 1) + '"' : 'disabled') + '>&#8592; Anterior</button>';

    const inicio = Math.max(1, pagina - 2);
    const fim    = Math.min(totalPaginas, pagina + 2);
    for (let p = inicio; p <= fim; p++) {
        const ativo = p === pagina ? ' btn--pag--ativo' : '';
        html += '<button class="btn btn--pag' + ativo + '" data-pag="' + p + '">' + p + '</button>';
    }
    html += '<span class="alunos__pageInfo">Página ' + pagina + ' de ' + totalPaginas + '</span>';
    html += '<button class="btn btn--pag" ' + (pagina < totalPaginas ? 'data-pag="' + (pagina + 1) + '"' : 'disabled') + '>Próxima &#8594;</button>';
    wrap.html(html);
};

let paginaAtual = 1;

const carregarAlunos = (pagina, busca, turmaId) => {
    paginaAtual = pagina;
    const tbody = $('#alunosTableBody');
    tbody.html('<tr><td colspan="7" class="interessados__loading">Carregando...</td></tr>');

    $.get(ADMIN_BASE_URL + '/services/get_alunos.php', { pagina, busca, turma_id: turmaId || '' }, (res) => {
        if (!res.success) {
            tbody.html('<tr><td colspan="7" class="interessados__empty">Erro ao carregar dados.</td></tr>');
            return;
        }
        $('#totalGeral').text(res.totalGeral);

        const turmaSel  = $('#filtraTurma option:selected').text();
        const filtrando = turmaId > 0;
        $('#resultCount').html(
            busca || filtrando
                ? res.total + ' aluno(s)' + (filtrando ? ' na turma <strong>' + $('<span>').text(turmaSel).html() + '</strong>' : '') + (busca ? ' — busca: "' + $('<span>').text(busca).html() + '"' : '')
                : res.total + ' aluno(s)'
        );
        renderTabela(res.registros);
        renderPaginacao(res.pagina, res.totalPaginas);
    }, 'json').fail(() => {
        tbody.html('<tr><td colspan="7" class="interessados__empty">Erro ao comunicar com o servidor.</td></tr>');
    });
};

const carregarFiltroTurmas = () => {
    $.get(ADMIN_BASE_URL + '/services/get_turmas_select.php', (res) => {
        if (!res.success || !res.turmas.length) return;
        const select = $('#filtraTurma');
        res.turmas.forEach(t => {
            const label = t.quadra_nome ? t.nome + ' — ' + t.quadra_nome : t.nome;
            select.append('<option value="' + t.id + '">' + $('<span>').text(label).html() + '</option>');
        });
    }, 'json');
};

// ─── DETALHE ─────────────────────────────────────────────────────────────────

const turmaRowHtml = (t) =>
    '<div class="alunos__turmaRow" data-turma-id="' + t.id + '">' +
        '<div class="alunos__turmaInfo">' +
            '<strong class="alunos__turmaNome">' + $('<span>').text(t.nome).html() + '</strong>' +
            '<span class="alunos__turmaQuadra">' + $('<span>').text(t.quadra_nome || '').html() + '</span>' +
        '</div>' +
        (t.valor_mensalidade != null ? (
            t.valor_efetivo != null && t.valor_efetivo < t.valor_mensalidade
                ? '<div class="alunos__turmaValorWrap"><span class="alunos__turmaValorOriginal">R$ ' + parseFloat(t.valor_mensalidade).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</span><span class="alunos__turmaValor">R$ ' + parseFloat(t.valor_efetivo).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '/mês</span></div>'
                : '<span class="alunos__turmaValor">R$ ' + parseFloat(t.valor_mensalidade).toLocaleString('pt-BR', {minimumFractionDigits:2}) + '/mês</span>'
        ) : '') +
        '<span class="alunos__turmaDesde">desde ' + (t.data_entrada ? t.data_entrada.split('-').reverse().join('/') : '—') + '</span>' +
        '<button type="button" class="btn btn--gray alunos__btnRemoverTurma" data-turma-id="' + t.id + '">Remover</button>' +
    '</div>';

const carregarSelectTurmas = () => {
    $.get(ADMIN_BASE_URL + '/services/get_turmas_select.php', (res) => {
        if (!res.success) return;
        const select = $('#selectAddTurma');
        select.find('option:not(:first)').remove();
        res.turmas.forEach(t => {
            const label = t.quadra_nome ? t.nome + ' — ' + t.quadra_nome : t.nome;
            select.append('<option value="' + t.id + '">' + $('<span>').text(label).html() + '</option>');
        });
    }, 'json');
};

const addTurmaAluno = () => {
    const turmaId = parseInt($('#selectAddTurma').val());
    if (!turmaId) { alert('Selecione uma turma.'); return; }
    const dataInicio = $('#dataInicioTurma').val() || new Date().toISOString().slice(0, 10);

    $.ajax({
        url: ADMIN_BASE_URL + '/services/add_aluno_turma.php',
        method: 'POST',
        data: { turma_id: turmaId, aluno_id: ALUNO_ID, data_inicio: dataInicio },
        dataType: 'json',
    }).done((res) => {
        if (res.success) {
            $('#semTurmaMsg').remove();
            $('#turmasDoAluno').append(turmaRowHtml(res.aluno_turma || {
                id: turmaId,
                nome: $('#selectAddTurma option:selected').text().split(' — ')[0],
                quadra_nome: $('#selectAddTurma option:selected').text().split(' — ')[1] || '',
                valor_mensalidade: null,
                data_entrada: new Date().toISOString().slice(0, 10),
            }));
            $('#selectAddTurma').val('');
        } else {
            alert(res.message || 'Erro ao adicionar turma.');
        }
    }).fail((xhr) => {
        try {
            const res = JSON.parse(xhr.responseText);
            alert(res.message || 'Erro ao adicionar turma.');
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        }
    });
};

const removerTurmaAluno = (turmaId, el) => {
    if (!confirm('Remover aluno desta turma?')) return;
    $.post(ADMIN_BASE_URL + '/services/remove_aluno_turma.php', { turma_id: turmaId, aluno_id: ALUNO_ID }, (res) => {
        if (res.success) {
            el.remove();
            if ($('#turmasDoAluno .alunos__turmaRow').length === 0) {
                $('#turmasDoAluno').prepend('<p class="alunos__semTurma" id="semTurmaMsg">Este aluno não está em nenhuma turma.</p>');
            }
        } else {
            alert(res.message || 'Erro ao remover turma.');
        }
    }, 'json').fail(() => alert('Erro ao comunicar com o servidor.'));
};

const initDetalhe = () => {
    carregarSelectTurmas();
    initFaturasDrawer();

    $('#btnAddTurma').on('click', addTurmaAluno);

    $(document).on('click', '.alunos__btnRemoverTurma', function () {
        removerTurmaAluno(parseInt($(this).data('turma-id')), $(this).closest('.alunos__turmaRow'));
    });

    // ── Desconto ──────────────────────────────────────────────────────────────

    const atualizarPreviewDesconto = () => {
        const valor    = parseFloat($('#descontoValor').val());
        const tipo     = $('#descontoTipo').val();
        const baseAttr = $('#descontoModal').data('valor-base');
        const base     = parseFloat(baseAttr);
        const preview  = $('#descontoPreview');
        if (isNaN(valor) || valor <= 0 || isNaN(base) || base <= 0) { preview.html(''); return; }
        let efetivo;
        if (tipo === 'percentual') {
            efetivo = base * (1 - valor / 100);
        } else {
            efetivo = Math.max(0, base - valor);
        }
        preview.html(
            'Mensalidade: <s>R$ ' + base.toLocaleString('pt-BR', {minimumFractionDigits:2}) + '</s> → ' +
            '<strong>R$ ' + efetivo.toLocaleString('pt-BR', {minimumFractionDigits:2}) + '/mês</strong>'
        );
    };

    $(document).on('change', '#chkIsentoMatricula', function () {
        const chk    = $(this);
        const isento = chk.is(':checked') ? '1' : '0';
        chk.prop('disabled', true);

        $.post(ADMIN_BASE_URL + '/services/save_isencao_matricula.php', {
            aluno_id: ALUNO_ID,
            isento:   isento,
        }, (res) => {
            chk.prop('disabled', false);
            if (!res.success) {
                chk.prop('checked', !chk.is(':checked'));
                alert(res.message || 'Erro ao salvar isenção.');
            }
        }, 'json').fail(() => {
            chk.prop('disabled', false);
            chk.prop('checked', !chk.is(':checked'));
            alert('Erro ao comunicar com o servidor.');
        });
    });

    $(document).on('click', '.alunos__btnDesconto', function () {
        const btn = $(this);
        $('#descontoModal').data('valor-base', btn.data('valor'));
        $('#descontoTurmaId').val(btn.data('turma-id'));
        $('#descontoTurmaNome').text(btn.data('turma-nome'));
        $('#descontoValor').val(btn.data('desconto') || '');
        $('#descontoTipo').val(btn.data('tipo') || 'fixo');
        const vitalicio = btn.data('vitalicio') === 1 || btn.data('vitalicio') === '1';
        $('#descontoVitalicio').prop('checked', vitalicio);
        $('#descontoDatas').toggle(!vitalicio);
        $('#descontoInicio').val(btn.data('inicio') || '');
        $('#descontoFim').val(btn.data('fim') || '');
        atualizarPreviewDesconto();
        $('#descontoRemover').toggle(!!(btn.data('desconto') > 0));
        $('#descontoModal').addClass('confirmModal--open');
    });

    $('#descontoVitalicio').on('change', function () {
        $('#descontoDatas').toggle(!$(this).is(':checked'));
    });

    $('#descontoValor, #descontoTipo').on('input change', atualizarPreviewDesconto);

    $('#descontoCancelar').on('click', () => $('#descontoModal').removeClass('confirmModal--open'));

    $('#descontoModal').on('click', function (e) {
        if ($(e.target).is('#descontoModal')) $(this).removeClass('confirmModal--open');
    });

    $('#descontoRemover').on('click', function () {
        const btn = $(this).prop('disabled', true).text('Removendo...');
        $.post(ADMIN_BASE_URL + '/services/save_desconto_aluno.php', {
            aluno_id: ALUNO_ID,
            turma_id: $('#descontoTurmaId').val(),
            remover: '1',
        }, (res) => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.message || 'Erro ao remover desconto.');
                btn.prop('disabled', false).text('Remover desconto');
            }
        }, 'json').fail(() => {
            alert('Erro ao comunicar com o servidor.');
            btn.prop('disabled', false).text('Remover desconto');
        });
    });

    $('#descontoSalvar').on('click', function () {
        const btn = $(this).prop('disabled', true).text('Salvando...');
        const vitalicio = $('#descontoVitalicio').is(':checked') ? '1' : '0';
        $.post(ADMIN_BASE_URL + '/services/save_desconto_aluno.php', {
            aluno_id : ALUNO_ID,
            turma_id : $('#descontoTurmaId').val(),
            desconto : $('#descontoValor').val(),
            desconto_tipo     : $('#descontoTipo').val(),
            desconto_vitalicio: vitalicio,
            desconto_inicio   : vitalicio === '1' ? '' : $('#descontoInicio').val(),
            desconto_fim      : vitalicio === '1' ? '' : $('#descontoFim').val(),
        }, (res) => {
            if (res.success) {
                location.reload();
            } else {
                alert(res.message || 'Erro ao salvar desconto.');
                btn.prop('disabled', false).text('Salvar');
            }
        }, 'json').fail(() => {
            alert('Erro ao comunicar com o servidor.');
            btn.prop('disabled', false).text('Salvar');
        });
    });

    // ── Cobrança extra ───────────────────────────────────────────────────────
    const cobrancaModal = document.getElementById('cobrancaModal');
    const btnNova       = document.getElementById('btnNovaCobranca');

    if (btnNova && cobrancaModal) {
        btnNova.addEventListener('click', () => {
            document.getElementById('cobrancaDescricao').value  = '';
            document.getElementById('cobrancaValor').value      = '';
            document.getElementById('cobrancaMsg').style.display = 'none';
            cobrancaModal.classList.add('confirmModal--open');
        });

        document.getElementById('cobrancaCancelar').addEventListener('click', () => {
            cobrancaModal.classList.remove('confirmModal--open');
        });

        cobrancaModal.addEventListener('click', e => {
            if (e.target === cobrancaModal) cobrancaModal.classList.remove('confirmModal--open');
        });

        document.getElementById('cobrancaSalvar').addEventListener('click', function () {
            const btn      = this;
            const desc     = document.getElementById('cobrancaDescricao').value.trim();
            const valor    = parseFloat(document.getElementById('cobrancaValor').value);
            const venc     = document.getElementById('cobrancaVencimento').value;
            const msg      = document.getElementById('cobrancaMsg');

            if (!desc || isNaN(valor) || valor <= 0 || !venc) {
                msg.textContent   = 'Preencha todos os campos.';
                msg.style.color   = '#cf7e7e';
                msg.style.display = '';
                return;
            }

            btn.disabled    = true;
            btn.textContent = 'Criando...';
            msg.style.display = 'none';

            const fd = new FormData();
            fd.append('aluno_id',   ALUNO_ID);
            fd.append('descricao',  desc);
            fd.append('valor',      valor.toFixed(2));
            fd.append('vencimento', venc);

            fetch(ADMIN_BASE_URL + '/services/add_cobranca_avulsa.php', {
                method: 'POST', credentials: 'same-origin', body: fd,
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    cobrancaModal.classList.remove('confirmModal--open');
                    // Força recarregar faturas se o painel já foi aberto
                    const panel = document.getElementById('faturasPanel');
                    if (panel) {
                        const body = document.getElementById('faturasBody');
                        body.innerHTML = '<div class="faturasPanel__loading">Carregando...</div>';
                        fetch(ADMIN_BASE_URL + '/services/get_mensalidades_aluno.php?aluno_id=' + ALUNO_ID, {
                            credentials: 'same-origin',
                        })
                        .then(r => r.json())
                        .then(d => { if (d.success) renderFaturas(d.mensalidades); });
                        panel.classList.add('is-open');
                        document.body.style.overflow = 'hidden';
                    }
                } else {
                    msg.textContent   = data.message || 'Erro ao criar cobrança.';
                    msg.style.color   = '#cf7e7e';
                    msg.style.display = '';
                    btn.disabled      = false;
                    btn.textContent   = 'Criar cobrança';
                }
            })
            .catch(() => {
                msg.textContent   = 'Erro de comunicação.';
                msg.style.color   = '#cf7e7e';
                msg.style.display = '';
                btn.disabled      = false;
                btn.textContent   = 'Criar cobrança';
            });
        });
    }

    // Exclusão do aluno
    let alunoIdParaExcluir = 0;

    $('#btnExcluir').on('click', function () {
        alunoIdParaExcluir = $(this).data('id');
        $('#confirmNome').text($(this).data('nome'));
        $('#confirmModal').addClass('confirmModal--open');
    });

    $('#confirmCancelar').on('click', () => {
        $('#confirmModal').removeClass('confirmModal--open');
        alunoIdParaExcluir = 0;
    });

    $('#confirmModal').on('click', function (e) {
        if ($(e.target).is('#confirmModal')) {
            $(this).removeClass('confirmModal--open');
            alunoIdParaExcluir = 0;
        }
    });

    $('#confirmExcluir').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text('Excluindo...');
        $.post(ADMIN_BASE_URL + '/services/delete_aluno.php', { id: alunoIdParaExcluir }, (res) => {
            if (res.success) {
                window.location.href = BASE_URL + '/admin/alunos';
            } else {
                alert(res.message || 'Erro ao excluir.');
                btn.prop('disabled', false).text('Sim, excluir');
            }
        }, 'json').fail(() => {
            alert('Erro ao comunicar com o servidor.');
            btn.prop('disabled', false).text('Sim, excluir');
        });
    });
};

// ─── PAINEL DE FATURAS ───────────────────────────────────────────────────────

const statusLabel = { pago: 'Pago', pendente: 'Pendente', atrasado: 'Atrasado' };
const fmtData = (v) => v ? v.split('-').reverse().join('/') : '—';
const fmtVal  = (v) => 'R$ ' + parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 });

const renderFaturas = (lista) => {
    const body = document.getElementById('faturasBody');

    if (!lista || lista.length === 0) {
        body.innerHTML = '<div class="faturasPanel__empty">Nenhuma fatura encontrada.</div>';
        return;
    }

    // Resumo
    let totPago = 0, totPend = 0, totAtr = 0, totVal = 0;
    lista.forEach(m => {
        const v = parseFloat(m.valor) || 0;
        totVal += v;
        if (m.status === 'pago')      totPago++;
        else if (m.status === 'atrasado') totAtr++;
        else                          totPend++;
    });

    let statsHtml = '<div class="faturasPanel__stats">'
        + '<div class="fpStat fpStat--total"><div class="fpStat__label">Total faturas</div><div class="fpStat__value">' + lista.length + '</div></div>'
        + '<div class="fpStat fpStat--pago"><div class="fpStat__label">Pagas</div><div class="fpStat__value">' + totPago + '</div></div>'
        + '<div class="fpStat fpStat--pend"><div class="fpStat__label">Pendentes</div><div class="fpStat__value">' + totPend + '</div></div>'
        + (totAtr > 0 ? '<div class="fpStat fpStat--atr"><div class="fpStat__label">Atrasadas</div><div class="fpStat__value">' + totAtr + '</div></div>' : '')
        + '</div>';

    let rows = lista.map(m => {
        const matriculaTag = m.matricula_valor && parseFloat(m.matricula_valor) > 0
            ? '<br><span class="fpMatricula">+ matrícula ' + fmtVal(m.matricula_valor) + '</span>'
            : '';
        const proporcionalTag = m.proporcional_valor && parseFloat(m.proporcional_valor) > 0
            ? '<br><span class="fpMatricula">+ proporcional ' + fmtVal(m.proporcional_valor) + '</span>'
            : '';

        const colorClass = m.status === 'pago' ? 'is-pago' : (m.status === 'atrasado' ? 'is-atrasado' : 'is-pendente');

        const isAvulso = m.tipo === 'avulso';
        const refCell  = isAvulso
            ? '<span class="fpRefStack fpRefStack--extra">'
                + '<span class="fpExtraTag">EXTRA</span>'
                + '<strong>' + $('<span>').text(m.descricao || '—').html() + '</strong>'
              + '</span>'
            : '<strong>' + $('<span>').text(m.referencia).html() + '</strong>';
        const turmaCell = isAvulso
            ? '<span style="color:#555">—</span>'
            : $('<span>').text(m.turma_nome).html();

        return '<tr data-id="' + m.id + '">'
            + '<td>' + refCell + '</td>'
            + '<td>' + turmaCell + '</td>'
            + '<td>' + fmtVal(m.valor) + proporcionalTag + matriculaTag + '</td>'
            + '<td>' + fmtData(m.vencimento) + '</td>'
            + '<td class="fpCellPago">' + (m.data_pagamento ? fmtData(m.data_pagamento) : '<span style="color:#444">—</span>') + '</td>'
            + '<td>'
                + '<div class="fpStatusWrap">'
                    + '<select class="fpStatusSelect ' + colorClass + '" data-id="' + m.id + '" data-status="' + m.status + '">'
                        + '<option value="pendente"' + (m.status === 'pendente' ? ' selected' : '') + '>Pendente</option>'
                        + '<option value="pago"'     + (m.status === 'pago'     ? ' selected' : '') + '>Pago</option>'
                        + '<option value="atrasado"' + (m.status === 'atrasado' ? ' selected' : '') + '>Atrasado</option>'
                    + '</select>'
                    + '<input type="date" class="fpDateInput" value="' + (m.data_pagamento || new Date().toISOString().slice(0,10)) + '">'
                    + '<button class="fpStatusSave">Salvar</button>'
                    + '<span class="fpFlash fpFlash--ok">&#10003;</span>'
                    + '<span class="fpFlash fpFlash--err">&#10007;</span>'
                + '</div>'
            + '</td>'
        + '</tr>';
    }).join('');

    body.innerHTML = statsHtml
        + '<table class="fpTable">'
        + '<thead><tr><th>Referência</th><th>Turma</th><th>Valor</th><th>Vencimento</th><th>Data pag.</th><th>Status</th></tr></thead>'
        + '<tbody>' + rows + '</tbody>'
        + '</table>';

    // Bind events
    body.querySelectorAll('.fpStatusSelect').forEach(sel => {
        sel.addEventListener('change', function () {
            const wrap    = this.closest('.fpStatusWrap');
            const dateInp = wrap.querySelector('.fpDateInput');
            const saveBtn = wrap.querySelector('.fpStatusSave');

            // Limpa classes de cor
            sel.className = 'fpStatusSelect is-' + this.value;

            if (this.value === 'pago') {
                dateInp.style.display = '';
                saveBtn.style.display = '';
            } else {
                dateInp.style.display = 'none';
                saveBtn.style.display = 'none';
                salvarStatusFatura(this.closest('tr').dataset.id, this.value, '', wrap);
            }
        });
    });

    body.querySelectorAll('.fpStatusSave').forEach(btn => {
        btn.addEventListener('click', function () {
            const wrap    = this.closest('.fpStatusWrap');
            const dateInp = wrap.querySelector('.fpDateInput');
            const tr      = this.closest('tr');
            salvarStatusFatura(tr.dataset.id, 'pago', dateInp.value, wrap);
        });
    });
};

const salvarStatusFatura = (id, status, dataPag, wrap) => {
    const flashOk  = wrap.querySelector('.fpFlash--ok');
    const flashErr = wrap.querySelector('.fpFlash--err');
    const saveBtn  = wrap.querySelector('.fpStatusSave');
    flashOk.style.display = flashErr.style.display = 'none';
    if (saveBtn) saveBtn.disabled = true;

    const fd = new FormData();
    fd.append('id', id);
    fd.append('status', status);
    if (dataPag) fd.append('data_pagamento', dataPag);

    fetch(ADMIN_BASE_URL + '/services/update_mensalidade_status.php', {
        method: 'POST', credentials: 'same-origin', body: fd,
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            flashOk.style.display = '';
            setTimeout(() => { flashOk.style.display = 'none'; }, 2500);

            // Atualiza célula de data paga
            const tr = wrap.closest('tr');
            const cellPago = tr.querySelector('.fpCellPago');
            if (status === 'pago' && dataPag) {
                cellPago.textContent = dataPag.split('-').reverse().join('/');
            } else if (status !== 'pago') {
                cellPago.innerHTML = '<span style="color:#444">—</span>';
            }

            // Oculta date+save se foi salvo com sucesso como pago
            if (status === 'pago') {
                wrap.querySelector('.fpDateInput').style.display = 'none';
                if (saveBtn) { saveBtn.style.display = 'none'; saveBtn.disabled = false; }
            }
        } else {
            flashErr.style.display = '';
            if (saveBtn) saveBtn.disabled = false;
        }
    })
    .catch(() => {
        flashErr.style.display = '';
        if (saveBtn) saveBtn.disabled = false;
    });
};

const initFaturasDrawer = () => {
    const panel     = document.getElementById('faturasPanel');
    const body      = document.getElementById('faturasBody');
    const nomeSpan  = document.getElementById('faturasNomeAluno');
    const btnAbrir  = document.getElementById('btnFaturas');
    const btnFechar = document.getElementById('fechaFaturas');

    if (!panel || !btnAbrir) return;

    let carregado = false;

    const abrirPanel = () => {
        nomeSpan.textContent = btnAbrir.dataset.nome || '';
        panel.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        if (!carregado) {
            carregado = true;
            fetch(ADMIN_BASE_URL + '/services/get_mensalidades_aluno.php?aluno_id=' + ALUNO_ID, {
                credentials: 'same-origin',
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) renderFaturas(data.mensalidades);
                else body.innerHTML = '<div class="faturasPanel__empty">Erro ao carregar faturas.</div>';
            })
            .catch(() => {
                body.innerHTML = '<div class="faturasPanel__empty">Erro de comunicação.</div>';
            });
        }
    };

    const fecharPanel = () => {
        panel.classList.remove('is-open');
        document.body.style.overflow = '';
    };

    btnAbrir.addEventListener('click', abrirPanel);
    btnFechar.addEventListener('click', fecharPanel);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) fecharPanel();
    });
};

// ─── INIT ────────────────────────────────────────────────────────────────────

$(document).ready(() => {
    if (ALUNO_VIEW === 'lista') {
        carregarFiltroTurmas();
        carregarAlunos(1, '', 0);

        let debounceTimer;

        $('#buscaAlunos').on('input', function () {
            clearTimeout(debounceTimer);
            const busca   = $(this).val().trim();
            const turmaId = parseInt($('#filtraTurma').val()) || 0;
            debounceTimer = setTimeout(() => carregarAlunos(1, busca, turmaId), 400);
        });

        $('#filtraTurma').on('change', function () {
            const turmaId = parseInt($(this).val()) || 0;
            const busca   = $('#buscaAlunos').val().trim();
            carregarAlunos(1, busca, turmaId);
        });

        $(document).on('click', '.btn--pag', function () {
            const busca   = $('#buscaAlunos').val().trim();
            const turmaId = parseInt($('#filtraTurma').val()) || 0;
            carregarAlunos(parseInt($(this).data('pag')), busca, turmaId);
        });
    }

    if (ALUNO_VIEW === 'detalhe') {
        initDetalhe();
    }
});
