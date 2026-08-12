(function () {
    var tbody      = document.getElementById('uniformesTableBody');
    var totalGeral = document.getElementById('totalGeral');
    var statsBox   = document.getElementById('uniformesStats');
    var filtros    = document.querySelectorAll('.uniformes__filter');

    var modal        = document.getElementById('statusModal');
    var modalInfo    = document.getElementById('statusModalInfo');
    var modalOptions = document.getElementById('statusModalOptions');

    var pedidos       = [];
    var fluxo         = [];
    var labels        = {};
    // Grades de tamanho vêm do servidor (config/uniformes.php) — repetir as opções aqui
    // sairia de sincronia na primeira vez que uma grade mudasse.
    var tamanhos      = {};
    // Limite do nome e faixa do número também vêm do servidor, pelo mesmo motivo.
    var limites       = { nomeMax: 14, numeroMin: 1, numeroMax: 99 };
    var filtroAtivo   = 'todos';
    var colspan       = PODE_EDITAR ? 11 : 10;

    function escapar(txt) {
        var d = document.createElement('div');
        d.textContent = txt == null ? '' : txt;
        return d.innerHTML;
    }

    function moeda(v) {
        return 'R$ ' + Number(v).toFixed(2).replace('.', ',');
    }

    function carregar() {
        fetch(ADMIN_BASE_URL + '/services/get_pedidos_uniforme.php', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="interessados__loading">Erro ao carregar.</td></tr>';
                    return;
                }

                pedidos  = data.pedidos || [];
                fluxo    = data.fluxo || [];
                labels   = data.labels || {};
                tamanhos = data.tamanhos || {};
                limites  = {
                    nomeMax:   data.nome_max   || 14,
                    numeroMin: data.numero_min || 1,
                    numeroMax: data.numero_max || 99
                };

                totalGeral.textContent = data.total;
                renderStats(data.por_status);
                render();

                // Abrir a tela já dá o pedido por visto — zera o badge do sino.
                marcarVistos();
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="interessados__loading">Erro ao carregar.</td></tr>';
            });
    }

    function renderStats(porStatus) {
        if (!porStatus) return;
        var html = '';
        fluxo.forEach(function (s) {
            html += '<div class="uniformes__stat uniformes__stat--' + s + '">'
                  + '<strong>' + (porStatus[s] || 0) + '</strong>'
                  + '<span>' + escapar(labels[s] || s) + '</span>'
                  + '</div>';
        });
        statsBox.innerHTML = html;
    }

    function render() {
        var lista = filtroAtivo === 'todos'
            ? pedidos
            : pedidos.filter(function (p) { return p.status === filtroAtivo; });

        if (!lista.length) {
            tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="interessados__loading">Nenhum pedido nesse status.</td></tr>';
            return;
        }

        var html = '';
        lista.forEach(function (p) {
            html += '<tr' + (p.novo ? ' class="uniformes__row--novo"' : '') + '>'
                  + '<td>' + p.id + (p.novo ? ' <span class="uniformes__novoTag">NOVO</span>' : '') + '</td>'
                  + '<td>'
                  +   '<strong>' + escapar(p.aluno_nome) + '</strong>'
                  +   '<small class="uniformes__sub">' + escapar(p.aluno_email) + '</small>'
                  + '</td>'
                  + '<td>' + escapar(p.turma_nome) + '</td>'
                  + '<td>' + escapar(p.genero_label) + '<small class="uniformes__sub">' + escapar(p.modelo_label) + '</small></td>'
                  + '<td><strong>' + escapar(p.nome_camisa) + '</strong></td>'
                  + '<td><span class="uniformes__numero' + (p.conflito_numero ? ' is-conflito' : '') + '">'
                  +   p.numero + '</span>'
                  +   (p.conflito_numero ? '<small class="uniformes__sub uniformes__sub--alerta">número duplicado</small>' : '')
                  + '</td>'
                  + '<td>'
                  +   '<span class="uniformes__tam">' + escapar(p.tamanho_camisa) + '</span>'
                  +   '<small class="uniformes__sub">camisa</small>'
                  +   '<span class="uniformes__tam">' + escapar(p.tamanho_shorts) + '</span>'
                  +   '<small class="uniformes__sub">' + escapar((p.label_shorts || 'shorts').toLowerCase()) + '</small>'
                  + '</td>'
                  + '<td>' + moeda(p.valor) + '</td>'
                  + '<td>' + escapar(p.pago_em_label) + '</td>'
                  + '<td><span class="uniformes__badge uniformes__badge--' + p.status + '">'
                  +   escapar(p.status_label) + '</span></td>';

            if (PODE_EDITAR) {
                html += '<td>';
                if (p.proximo_status) {
                    html += '<button class="btn btn--primary btn--sm" data-avancar="' + p.id + '">'
                          + '&rarr; ' + escapar(labels[p.proximo_status] || p.proximo_status) + '</button> ';
                }
                html += '<button class="btn btn--gray btn--sm" data-status="' + p.id + '">Alterar</button> ';
                html += '<button class="btn btn--gray btn--sm" data-editar="' + p.id + '">Corrigir</button>';
                html += '</td>';
            }

            html += '</tr>';
        });

        tbody.innerHTML = html;

        tbody.querySelectorAll('[data-avancar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var p = acharPedido(btn.getAttribute('data-avancar'));
                if (p && p.proximo_status) salvarStatus(p.id, p.proximo_status, btn);
            });
        });

        tbody.querySelectorAll('[data-status]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirModal(acharPedido(btn.getAttribute('data-status')));
            });
        });

        tbody.querySelectorAll('[data-editar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirEdicao(acharPedido(btn.getAttribute('data-editar')));
            });
        });
    }

    function acharPedido(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < pedidos.length; i++) {
            if (pedidos[i].id === id) return pedidos[i];
        }
        return null;
    }

    // ── Modal de status ─────────────────────────────────────────────────────────
    function abrirModal(p) {
        if (!p) return;

        modalInfo.innerHTML = '<strong>' + escapar(p.aluno_nome) + '</strong> — '
                            + escapar(p.nome_camisa) + ' #' + p.numero
                            + ' (camisa ' + escapar(p.tamanho_camisa)
                            + ' / ' + escapar((p.label_shorts || 'shorts').toLowerCase()) + ' ' + escapar(p.tamanho_shorts) + ')';

        var html = '';
        fluxo.forEach(function (s) {
            html += '<button class="uniformes__statusOption' + (s === p.status ? ' is-active' : '') + '" '
                  + 'data-novo-status="' + s + '" data-pedido="' + p.id + '">'
                  + escapar(labels[s] || s) + '</button>';
        });
        modalOptions.innerHTML = html;

        modalOptions.querySelectorAll('[data-novo-status]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                salvarStatus(
                    parseInt(btn.getAttribute('data-pedido'), 10),
                    btn.getAttribute('data-novo-status'),
                    btn
                );
            });
        });

        modal.classList.add('confirmModal--open');
    }

    function fecharModal() {
        modal.classList.remove('confirmModal--open');
    }

    document.getElementById('statusModalFechar').addEventListener('click', fecharModal);
    modal.addEventListener('click', function (e) { if (e.target === this) fecharModal(); });

    // ── Salvar ──────────────────────────────────────────────────────────────────
    function salvarStatus(pedidoId, status, btn) {
        btn.disabled = true;

        var body = new URLSearchParams({ pedido_id: pedidoId, status: status });

        fetch(ADMIN_BASE_URL + '/services/update_status_pedido_uniforme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                fecharModal();
                carregar();
            } else {
                alert(data.message || 'Erro ao salvar.');
                btn.disabled = false;
            }
        })
        .catch(function () {
            alert('Erro ao comunicar com o servidor.');
            btn.disabled = false;
        });
    }

    // ── Modal de correção ───────────────────────────────────────────────────────
    //
    // Corrige só o que vai bordado/costurado: nome, número e tamanhos. Aluno, turma, gênero
    // e valor não entram — mudar isso não é corrigir um pedido, é outro pedido.
    var editarModal = document.getElementById('editarModal');
    var editarAtual = null;

    function opcoesTamanho(select, lista, atual) {
        select.innerHTML = lista.map(function (t) {
            return '<option value="' + escapar(t) + '"' + (t === atual ? ' selected' : '') + '>' + escapar(t) + '</option>';
        }).join('');
    }

    function abrirEdicao(p) {
        if (!p) return;
        editarAtual = p;

        document.getElementById('editarErro').style.display = 'none';
        document.getElementById('editarPedidoId').value = p.id;

        var campoNome = document.getElementById('editarNome');
        campoNome.value = p.nome_camisa;
        campoNome.maxLength = limites.nomeMax;
        document.getElementById('editarNomeHint').textContent =
            'Até ' + limites.nomeMax + ' caracteres. Só letras, ponto e hífen — vai bordado em caixa alta.';

        var campoNumero = document.getElementById('editarNumero');
        campoNumero.value = p.numero;
        campoNumero.min   = limites.numeroMin;
        campoNumero.max   = limites.numeroMax;

        document.getElementById('editarModalInfo').innerHTML =
            '<strong>' + escapar(p.aluno_nome) + '</strong> — ' + escapar(p.turma_nome)
          + ' &middot; ' + escapar(p.genero_label) + ' ' + escapar(p.modelo_label);

        var labelShorts = (p.label_shorts || 'Shorts');
        document.getElementById('editarShortsLabel').textContent = 'Tamanho ' +
            (p.genero === 'feminino' ? 'da ' : 'do ') + labelShorts.toLowerCase();

        var grade = (tamanhos[p.genero] || { camisa: [], shorts: [] });
        opcoesTamanho(document.getElementById('editarTamanhoCamisa'), grade.camisa, p.tamanho_camisa);
        opcoesTamanho(document.getElementById('editarTamanhoShorts'), grade.shorts, p.tamanho_shorts);

        // A confecção pode já ter começado — quem edita precisa saber disso antes de salvar.
        var aviso = document.getElementById('editarModalAviso');
        if (p.status !== 'pendente') {
            aviso.innerHTML = '&#9888; Este pedido já está em <strong>' + escapar(p.status_label)
                            + '</strong>. Se a confecção começou, avise a fábrica da correção.';
            aviso.style.display = '';
        } else {
            aviso.style.display = 'none';
        }

        carregarNumerosOcupados(p);
        editarModal.classList.add('confirmModal--open');
    }

    /**
     * Mostra quais números já estão tomados na turma+gênero do pedido. É só uma ajuda visual:
     * quem decide é o servidor, que refaz a checagem com trava antes de gravar.
     */
    function carregarNumerosOcupados(p) {
        var hint = document.getElementById('editarNumeroHint');
        hint.textContent = 'Verificando números disponíveis...';

        if (!p.turma_id) {
            hint.textContent = 'Pedido sem turma — o número será validado ao salvar.';
            return;
        }

        var url = ADMIN_BASE_URL + '/services/get_numeros_uniforme_admin.php'
                + '?aluno_id=' + p.aluno_id + '&turma_id=' + p.turma_id + '&genero=' + encodeURIComponent(p.genero);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.ocupados) {
                    hint.textContent = 'O número será validado ao salvar.';
                    return;
                }
                hint.textContent = data.ocupados.length
                    ? 'Já em uso nesta turma/gênero: ' + data.ocupados.join(', ')
                    : 'Nenhum número em uso nesta turma/gênero.';
            })
            .catch(function () {
                hint.textContent = 'O número será validado ao salvar.';
            });
    }

    function fecharEdicao() {
        editarModal.classList.remove('confirmModal--open');
        editarAtual = null;
    }

    function salvarEdicao() {
        if (!editarAtual) return;

        var btn  = document.getElementById('editarModalSalvar');
        var erro = document.getElementById('editarErro');
        erro.style.display = 'none';

        var body = new URLSearchParams({
            pedido_id:      editarAtual.id,
            nome_camisa:    document.getElementById('editarNome').value,
            numero:         document.getElementById('editarNumero').value,
            tamanho_camisa: document.getElementById('editarTamanhoCamisa').value,
            tamanho_shorts: document.getElementById('editarTamanhoShorts').value
        });

        btn.disabled = true;
        btn.textContent = 'Salvando...';

        function liberarBotao() {
            btn.disabled = false;
            btn.textContent = 'Salvar correção';
        }

        fetch(ADMIN_BASE_URL + '/services/update_pedido_uniforme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            liberarBotao();
            if (data.success) {
                fecharEdicao();
                carregar();
                return;
            }
            // Número tomado, tamanho inválido, nome vazio: o motivo fica no próprio modal,
            // com os dados ainda preenchidos, pra corrigir sem digitar tudo de novo.
            erro.textContent = data.message || 'Não foi possível salvar.';
            erro.style.display = '';
        })
        .catch(function () {
            liberarBotao();
            erro.textContent = 'Erro ao comunicar com o servidor.';
            erro.style.display = '';
        });
    }

    document.getElementById('editarModalFechar').addEventListener('click', fecharEdicao);
    document.getElementById('editarModalSalvar').addEventListener('click', salvarEdicao);
    editarModal.addEventListener('click', function (e) { if (e.target === this) fecharEdicao(); });

    function marcarVistos() {
        if (!pedidos.some(function (p) { return p.novo; })) return;

        fetch(ADMIN_BASE_URL + '/services/marcar_pedidos_uniforme_vistos.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).catch(function () {});
    }

    // ── Filtros ─────────────────────────────────────────────────────────────────
    filtros.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filtros.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            filtroAtivo = btn.getAttribute('data-filtro');
            render();
        });
    });

    carregar();
}());
