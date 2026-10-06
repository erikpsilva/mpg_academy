(function () {
    var tbody      = document.getElementById('uniformesTableBody');
    var totalGeral = document.getElementById('totalGeral');
    var statsBox   = document.getElementById('uniformesStats');
    var valoresBox = document.getElementById('uniformesValores');
    var filtros    = document.querySelectorAll('.uniformes__filter');

    var modal        = document.getElementById('statusModal');
    var modalInfo    = document.getElementById('statusModalInfo');
    var modalOptions = document.getElementById('statusModalOptions');

    var pedidos       = [];
    var fluxo         = [];
    var labels        = {};
    var contagemStatus = {};
    // Grades de tamanho vêm do servidor (config/uniformes.php) — repetir as opções aqui
    // sairia de sincronia na primeira vez que uma grade mudasse.
    var tamanhos      = {};
    // Limite do nome e faixa do número também vêm do servidor, pelo mesmo motivo.
    var limites       = { nomeMax: 14, numeroMin: 1, numeroMax: 99 };
    var filtroAtivo   = 'todos';
    var colspan       = PODE_EDITAR ? 12 : 11;

    // ── Abas ────────────────────────────────────────────────────────────────────
    //
    // Pedido em produção e pedido já entregue são duas leituras diferentes: uma é trabalho
    // a fazer, a outra é histórico. Os grupos vêm do HTML (que os recebe de
    // config/uniformes.php), então a tela nunca discorda da regra.
    var abas          = document.querySelectorAll('.uniformes__tab');
    var statusDoGrupo = {};
    var vazioDoGrupo  = {};

    abas.forEach(function (aba) {
        var grupo = aba.getAttribute('data-grupo');
        statusDoGrupo[grupo] = (aba.getAttribute('data-status') || '').split(',');
        vazioDoGrupo[grupo]  = aba.getAttribute('data-vazio') || 'Nenhum pedido aqui.';
    });

    // Qual aba abre é decisão do PHP (a primeira de UNIFORME_STATUS_GRUPOS), não daqui.
    var abaInicial = document.querySelector('.uniformes__tab.is-active');
    var grupoAtivo = abaInicial ? abaInicial.getAttribute('data-grupo') : 'todos';

    function noGrupo(p, grupo) {
        return (statusDoGrupo[grupo] || []).indexOf(p.status) !== -1;
    }

    /** Os pedidos da aba aberta, já com o filtro de status aplicado. */
    function listaVisivel() {
        return pedidos.filter(function (p) {
            if (!noGrupo(p, grupoAtivo)) return false;
            return filtroAtivo === 'todos' || p.status === filtroAtivo;
        });
    }

    function escapar(txt) {
        var d = document.createElement('div');
        d.textContent = txt == null ? '' : txt;
        return d.innerHTML;
    }

    function moeda(v) {
        return 'R$ ' + Number(v).toFixed(2).replace('.', ',');
    }

    /** Quantos pedidos de cada produto, na ordem em que aparecem na lista. */
    function contarPorProduto(lista) {
        var ordem = [];
        var mapa  = {};

        lista.forEach(function (p) {
            var tipo = p.tipo_uniforme || 'completo';
            if (!mapa[tipo]) {
                mapa[tipo] = { nome: p.produto_nome || tipo, qtd: 0 };
                ordem.push(tipo);
            }
            mapa[tipo].qtd += 1;
        });

        return ordem.map(function (t) { return mapa[t]; });
    }

    // No PDF o fornecedor recebe somente o texto que sera estampado. Prefixos de
    // equipe/professor ficam numa linha e o nome em outra, ambos em caixa alta.
    function nomeParaImpressao(texto) {
        var partes = String(texto || '').split(/\s+[—–-]\s+/);
        var prefixo = partes.length > 1 ? partes.shift() : '';
        var nome = partes.length ? partes.join(' — ') : String(texto || '');

        return '<span class="uniformes__printName">'
             + (prefixo ? '<small>' + escapar(prefixo.toUpperCase()) + '</small>' : '')
             + '<strong>' + escapar(nome.toUpperCase()) + '</strong>'
             + '</span>';
    }

    /**
     * Tamanhos como o fornecedor precisa ler.
     *
     * O nome da peça vem completo e com o gênero — CAMISA FEMININA BABY LOOK, BERMUDA
     * FEMININA, CALÇÃO MASCULINO. Antes saía só CAMISA e BERMUDA, e a coluna que mostra o
     * gênero fica de fora da impressão — então no papel não dava pra saber se a peça era
     * masculina ou feminina, que são modelagens diferentes.
     */
    function tamanhosParaImpressao(p) {
        var html = '<span class="uniformes__printSize"><small>'
                 + escapar((p.peca_camisa || 'Camisa').toUpperCase())
                 + '</small><strong>' + escapar(p.tamanho_camisa) + '</strong></span>';

        if (p.tamanho_shorts) {
            html += '<span class="uniformes__printSize"><small>'
                  + escapar((p.peca_shorts || p.label_shorts || 'Shorts').toUpperCase())
                  + '</small><strong>' + escapar(p.tamanho_shorts) + '</strong></span>';
        }
        return html;
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
                // Guardado: trocar de aba precisa recontar sem ir ao servidor de novo.
                contagemStatus = data.por_status || {};
                renderContadoresAbas(contagemStatus);

                // aplicarAba() deixa visíveis só os chips da aba aberta e já desenha os
                // contadores e a lista — inclusive na primeira carga.
                aplicarAba();

                // Abrir a tela já dá o pedido por visto — zera o badge do sino.
                marcarVistos();
            })
            .catch(function () {
                tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="interessados__loading">Erro ao carregar.</td></tr>';
            });
    }

    /** Contadores de status — só os da aba aberta, pra não misturar as duas leituras. */
    function renderStats(porStatus) {
        if (!porStatus) return;

        var html = '';
        fluxo.forEach(function (s) {
            if ((statusDoGrupo[grupoAtivo] || []).indexOf(s) === -1) return;

            html += '<div class="uniformes__stat uniformes__stat--' + s + '">'
                  + '<strong>' + (porStatus[s] || 0) + '</strong>'
                  + '<span>' + escapar(labels[s] || s) + '</span>'
                  + '</div>';
        });
        statsBox.innerHTML = html;
    }

    /** Número ao lado do nome de cada aba. */
    function renderContadoresAbas(porStatus) {
        if (!porStatus) return;

        document.querySelectorAll('[data-contador]').forEach(function (el) {
            var grupo = el.getAttribute('data-contador');
            var total = (statusDoGrupo[grupo] || []).reduce(function (soma, s) {
                return soma + (porStatus[s] || 0);
            }, 0);

            el.textContent = total;
        });
    }

    /**
     * Quanto custou, separado por produto.
     *
     * Acompanha o filtro de status de propósito: filtrando "Pendente" o admin vê quanto
     * ainda vai sair pra confecção; em "Todos", o gasto acumulado. Os contadores de status
     * acima continuam sendo do total geral, então o rótulo diz qual recorte está em uso.
     */
    function renderValores(lista) {
        if (!valoresBox) return;

        // Um card por produto vendido (uniforme completo, só a camisa, regata, comissão),
        // montado a partir dos próprios pedidos — produto novo no catálogo aparece sozinho.
        var ordem = [];
        var porProduto = {};
        var total = 0;

        lista.forEach(function (p) {
            var tipo = p.tipo_uniforme || 'completo';
            if (!porProduto[tipo]) {
                porProduto[tipo] = { nome: p.produto_nome || tipo, valor: 0, qtd: 0 };
                ordem.push(tipo);
            }
            var v = Number(p.valor) || 0;
            porProduto[tipo].valor += v;
            porProduto[tipo].qtd   += 1;
            total += v;
        });

        var recorte = filtroAtivo === 'todos'
            ? 'todos os pedidos'
            : (labels[filtroAtivo] || filtroAtivo).toLowerCase();

        var html = '';
        ordem.forEach(function (tipo) {
            var d = porProduto[tipo];
            html += '<div class="uniformes__valor uniformes__valor--' + escapar(tipo) + '">'
                  +   '<strong>' + moeda(d.valor) + '</strong>'
                  +   '<span>' + escapar(d.nome) + ' &middot; ' + d.qtd + '</span>'
                  + '</div>';
        });

        valoresBox.innerHTML = html
          + '<div class="uniformes__valor uniformes__valor--total">'
          +   '<strong>' + moeda(total) + '</strong>'
          +   '<span>Total &middot; ' + escapar(recorte) + '</span>'
          + '</div>';
    }

    function render() {
        var lista = listaVisivel();

        renderValores(lista);

        if (!lista.length) {
            var vazio = filtroAtivo !== 'todos'
                ? 'Nenhum pedido nesse status.'
                : (vazioDoGrupo[grupoAtivo] || 'Nenhum pedido aqui.');

            tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="interessados__loading">' + vazio + '</td></tr>';
            return;
        }

        var html = '';
        lista.forEach(function (p, i) {
            // Sequência da lista, não o id do pedido: o id tem buracos (pedido cancelado,
            // reserva que expirou) e não diz quantos pedidos existem. O id real fica no
            // title, que é o número usado pra conversar sobre um pedido específico.
            // A camisa da comissão técnica é outro produto (só camisa, sem número) e vai
            // separada pro fornecedor — por isso a linha tem fundo próprio.
            var classes = [];
            if (p.novo) classes.push('uniformes__row--novo');
            if (p.tipo_uniforme === 'equipe_tecnica') classes.push('uniformes__row--equipe');
            if (!p.pago) classes.push('uniformes__row--naoPago');

            html += '<tr' + (classes.length ? ' class="' + classes.join(' ') + '"' : '') + '>'
                  + '<td title="Pedido #' + p.id + '">' + (i + 1)
                  + (p.novo ? ' <span class="uniformes__novoTag">NOVO</span>' : '') + '</td>'
                  + '<td class="uniformes__printExclude">'
                  +   '<strong>' + escapar(p.aluno_nome) + '</strong>'
                  +   '<small class="uniformes__sub">' + escapar(p.aluno_email) + '</small>'
                  + '</td>'
                  + '<td class="uniformes__printExclude">' + escapar(p.turma_nome) + '</td>'
                  + '<td class="uniformes__printExclude">' + escapar(p.genero_label) + '<small class="uniformes__sub">' + escapar(p.modelo_label) + '</small></td>'
                  // Coluna de produto: na tela o nome curto; no papel a descrição completa,
                  // que nomeia cada peça (gola V, baby look, bermuda infantil, regata).
                  + '<td>'
                  +   '<span class="uniformes__screenValue">' + escapar(p.produto_curto || p.produto_nome) + '</span>'
                  +   '<span class="uniformes__printProduct">' + escapar((p.produto_completo || '').toUpperCase()) + '</span>'
                  + '</td>'
                  + '<td><strong class="uniformes__screenValue">' + escapar(p.texto_camisa || p.nome_camisa) + '</strong>'
                  +   nomeParaImpressao(p.texto_camisa || p.nome_camisa) + '</td>'
                  + '<td>'
                  +   (p.numero === null
                        ? '<span class="uniformes__sub">&mdash;</span>'   // equipe técnica não tem número
                        : '<span class="uniformes__numero' + (p.conflito_numero ? ' is-conflito' : '') + '">' + p.numero + '</span>'
                          + (p.conflito_numero ? '<small class="uniformes__sub uniformes__sub--alerta">número duplicado</small>' : ''))
                  + '</td>'
                  + '<td><span class="uniformes__cor uniformes__cor--' + (p.cor_label || '').toLowerCase() + '">'
                  +   escapar(p.cor_label || '—') + '</span></td>'
                  + '<td>'
                  +   '<span class="uniformes__screenValue">'
                  +     '<span class="uniformes__tam">' + escapar(p.tamanho_camisa) + '</span>'
                  +     '<small class="uniformes__sub">' + escapar(p.peca_de_cima === 'regata' ? 'regata' : 'camisa') + '</small>'
                  +     (p.tamanho_shorts
                        ? '<span class="uniformes__tam">' + escapar(p.tamanho_shorts) + '</span>'
                          + '<small class="uniformes__sub">' + escapar((p.label_shorts || 'shorts').toLowerCase()) + '</small>'
                        : '<small class="uniformes__sub">peça única</small>')
                  +   '</span>'
                  +   tamanhosParaImpressao(p)
                  + '</td>'
                  + '<td class="uniformes__printExclude">' + moeda(p.valor) + '</td>'
                  + '<td class="uniformes__printExclude">'
                  +   (p.pago
                        ? escapar(p.pago_em_label)
                        : '<span class="uniformes__naoPago">não pago</span>'
                          + '<small class="uniformes__sub">pedido em ' + escapar(p.criado_em_label) + '</small>')
                  + '</td>'
                  + '<td class="uniformes__printExclude"><span class="uniformes__badge uniformes__badge--' + p.status + '">'
                  +   escapar(p.status_label) + '</span></td>';

            if (PODE_EDITAR) {
                html += '<td class="uniformes__printExclude uniformes__actionCell"><div class="uniformes__actions">';
                if (p.proximo_status) {
                    html += '<button class="btn btn--primary btn--sm uniformes__nextAction" data-avancar="' + p.id + '">'
                          + '&rarr; ' + escapar(labels[p.proximo_status] || p.proximo_status) + '</button> ';
                }

                html += '<details class="uniformesActionMenu">'
                      +   '<summary>Mais ações <span aria-hidden="true">&#8942;</span></summary>'
                      +   '<div class="uniformesActionMenu__panel">';

                // O pagamento é um estado à parte da produção: quem lançou o pedido sem o
                // dinheiro marca aqui quando ele entra (e desfaz, se marcou errado).
                if (!p.pago) {
                    html += '<button class="uniformesActionMenu__item uniformesActionMenu__item--success" data-pagar="' + p.id + '">✓ Marcar como pago</button> ';
                } else if (p.pode_desmarcar) {
                    html += '<button class="uniformesActionMenu__item" data-despagar="' + p.id + '">Desmarcar pagamento</button> ';
                }

                html += '<button class="uniformesActionMenu__item" data-status="' + p.id + '">Alterar etapa</button> ';
                html += '<button class="uniformesActionMenu__item" data-editar="' + p.id + '">Corrigir dados</button> ';
                html += '<button class="uniformesActionMenu__item uniformesActionMenu__item--danger" data-excluir="' + p.id + '">Excluir pedido</button>';
                html +=   '</div></details>';
                html += '</div></td>';
            }

            html += '</tr>';
        });

        tbody.innerHTML = html;

        // Um menu por vez deixa a tabela limpa mesmo ao trabalhar em várias linhas.
        tbody.querySelectorAll('.uniformesActionMenu').forEach(function (menu) {
            menu.addEventListener('toggle', function () {
                if (!menu.open) return;
                tbody.querySelectorAll('.uniformesActionMenu[open]').forEach(function (outro) {
                    if (outro !== menu) outro.removeAttribute('open');
                });
            });
        });

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

        tbody.querySelectorAll('[data-excluir]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                abrirExclusao(acharPedido(btn.getAttribute('data-excluir')));
            });
        });

        tbody.querySelectorAll('[data-pagar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                marcarPagamento(parseInt(btn.getAttribute('data-pagar'), 10), true, btn);
            });
        });

        tbody.querySelectorAll('[data-despagar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!window.confirm('Voltar este pedido para NÃO pago? Ele sai do relatório de pagamentos.')) return;
                marcarPagamento(parseInt(btn.getAttribute('data-despagar'), 10), false, btn);
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
                            + escapar(p.produto_curto || p.produto_nome) + ' · '
                            + escapar(p.nome_camisa) + ' #' + p.numero
                            + ' (' + escapar(p.peca_de_cima === 'regata' ? 'regata ' : 'camisa ') + escapar(p.tamanho_camisa)
                            + (p.tamanho_shorts
                                ? ' / ' + escapar((p.label_shorts || 'shorts').toLowerCase()) + ' ' + escapar(p.tamanho_shorts)
                                : '') + ')';

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
          + ' &middot; ' + escapar(p.produto_completo || p.produto_nome)
          + ' &middot; ' + escapar(p.modelo_label);

        // O produto manda nos campos: só quem tem calção mostra o segundo tamanho, e a
        // grade de cima é a da peça daquele pedido (camisa, baby look, infantil ou regata).
        var temShorts   = (p.pecas || []).indexOf('shorts') !== -1;
        var campoShorts = document.getElementById('editarTamanhoShorts');
        var labelShorts = (p.label_shorts || 'Shorts');

        campoShorts.closest('.uniformes__editField').style.display = temShorts ? '' : 'none';
        campoShorts.required = temShorts;

        document.getElementById('editarShortsLabel').textContent = 'Tamanho ' +
            (p.genero === 'masculino' ? 'do ' : 'da ') + labelShorts.toLowerCase();

        document.getElementById('editarCamisaLabel').textContent = 'Tamanho ' +
            (p.peca_de_cima === 'regata' ? 'da regata' : 'da ' + (p.peca_camisa || 'camisa').toLowerCase());

        var grade = (tamanhos[p.genero] || { camisa: [], shorts: [], regata: [] });
        opcoesTamanho(document.getElementById('editarTamanhoCamisa'), grade[p.peca_de_cima] || grade.camisa, p.tamanho_camisa);
        opcoesTamanho(campoShorts, grade.shorts || [], p.tamanho_shorts);

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

        // Produto sem calção manda o campo vazio — mandar um tamanho aqui criaria um
        // pedido de "só camisa" com bermuda, que a confecção não saberia interpretar.
        var temShorts = (editarAtual.pecas || []).indexOf('shorts') !== -1;

        var body = new URLSearchParams({
            pedido_id:      editarAtual.id,
            nome_camisa:    document.getElementById('editarNome').value,
            numero:         document.getElementById('editarNumero').value,
            tamanho_camisa: document.getElementById('editarTamanhoCamisa').value,
            tamanho_shorts: temShorts ? document.getElementById('editarTamanhoShorts').value : ''
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

    /** Marca (ou desmarca) o pagamento de um pedido lançado pelo admin. */
    function marcarPagamento(pedidoId, pago, btn) {
        btn.disabled = true;
        btn.textContent = pago ? 'Marcando...' : 'Desfazendo...';

        fetch(ADMIN_BASE_URL + '/services/marcar_pagamento_uniforme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ pedido_id: pedidoId, pago: pago ? '1' : '0' }).toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) { carregar(); return; }
            alert(d.message || 'Não foi possível mudar o pagamento.');
            btn.disabled = false;
        })
        .catch(function () {
            alert('Erro ao comunicar com o servidor.');
            btn.disabled = false;
        });
    }

    // ── Exclusão ────────────────────────────────────────────────────────────────
    //
    // Excluir não apaga a linha do banco: marca o pedido como cancelado. Ele some das telas
    // e o número volta pro pool da turma, mas o registro fica — pedido que teve dinheiro
    // envolvido não pode sumir sem rastro. A confirmação mostra o pedido inteiro, porque é
    // fácil clicar na linha errada numa lista de dezenas.
    var excluirModal = document.getElementById('excluirModal');
    var excluirAtual = null;

    function abrirExclusao(p) {
        if (!p || !excluirModal) return;
        excluirAtual = p;

        var erro = document.getElementById('excluirErro');
        erro.style.display = 'none';

        document.getElementById('excluirModalInfo').innerHTML =
            '<strong>' + escapar(p.aluno_nome) + '</strong> — ' + escapar(p.turma_nome)
          + '<br>' + escapar(p.produto_completo || p.produto_nome)
          + '<br>' + escapar(p.texto_camisa || p.nome_camisa)
          + (p.numero !== null ? ' &middot; nº ' + p.numero : '')
          + ' &middot; ' + escapar(p.tamanho_camisa)
          + (p.tamanho_shorts ? ' / ' + escapar(p.tamanho_shorts) : '')
          + '<br>' + moeda(p.valor) + ' &middot; pago em ' + escapar(p.pago_em_label);

        // Pedido que já foi pra confecção pode já estar sendo costurado — quem exclui
        // precisa saber disso antes, não depois.
        var aviso = document.getElementById('excluirAviso');
        if (p.status !== 'pendente') {
            aviso.innerHTML = '&#9888; Este pedido já está em <strong>' + escapar(p.status_label)
                            + '</strong>. Se a confecção começou, avise a fábrica.';
            aviso.style.display = '';
        } else {
            aviso.style.display = 'none';
        }

        excluirModal.classList.add('confirmModal--open');
    }

    function fecharExclusao() {
        if (excluirModal) excluirModal.classList.remove('confirmModal--open');
        excluirAtual = null;
    }

    function confirmarExclusao() {
        if (!excluirAtual) return;

        var btn  = document.getElementById('excluirConfirmar');
        var erro = document.getElementById('excluirErro');

        btn.disabled = true;
        btn.textContent = 'Excluindo...';

        fetch(ADMIN_BASE_URL + '/services/excluir_pedido_uniforme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams({ pedido_id: excluirAtual.id }).toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            btn.textContent = 'Sim, excluir';

            if (d.success) { fecharExclusao(); carregar(); return; }

            erro.textContent = d.message || 'Não foi possível excluir.';
            erro.style.display = '';
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = 'Sim, excluir';
            erro.textContent = 'Erro de conexão.';
            erro.style.display = '';
        });
    }

    if (excluirModal) {
        document.getElementById('excluirCancelar').addEventListener('click', fecharExclusao);
        document.getElementById('excluirConfirmar').addEventListener('click', confirmarExclusao);
        excluirModal.addEventListener('click', function (e) { if (e.target === this) fecharExclusao(); });
    }

    function marcarVistos() {
        if (!pedidos.some(function (p) { return p.novo; })) return;

        fetch(ADMIN_BASE_URL + '/services/marcar_pedidos_uniforme_vistos.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).catch(function () {});
    }

    // ── Enviar tudo para confecção ──────────────────────────────────────────────
    //
    // Acompanha a lista impressa: gera o PDF, manda pro fornecedor e marca tudo como
    // enviado de uma vez. Fazer linha a linha com dezenas de pedidos é onde alguém pula
    // um e aquele uniforme nunca sai.
    var btnEnviarTodos = document.getElementById('btnEnviarTodos');
    var modalEnviar    = document.getElementById('enviarTodosModal');

    function pendentes() {
        return pedidos.filter(function (p) { return p.status === 'pendente'; });
    }

    function fecharEnviarTodos() {
        if (modalEnviar) modalEnviar.classList.remove('confirmModal--open');
    }

    if (btnEnviarTodos && modalEnviar) {
        btnEnviarTodos.addEventListener('click', function () {
            var lista = pendentes();
            var info  = document.getElementById('enviarTodosInfo');
            var erro  = document.getElementById('enviarTodosErro');
            var ok    = document.getElementById('enviarTodosConfirmar');

            erro.style.display = 'none';

            if (!lista.length) {
                info.textContent = 'Não há pedidos pendentes no momento — tudo já foi enviado.';
                ok.style.display = 'none';
            } else {
                // Mostra a composição: é o que ele vai conferir contra a lista impressa.
                var detalhe = contarPorProduto(lista).map(function (c) {
                    return c.qtd + ' × ' + c.nome;
                });

                info.innerHTML = 'Marcar <strong>' + lista.length + ' pedido' + (lista.length === 1 ? '' : 's')
                               + ' pendente' + (lista.length === 1 ? '' : 's') + '</strong> como enviados para confecção?'
                               + (detalhe.length ? '<br><small>' + detalhe.join(' · ') + '</small>' : '')
                               + '<br><br>Pedidos que já avançaram não são afetados.';
                ok.style.display = '';
            }

            modalEnviar.classList.add('confirmModal--open');
        });

        document.getElementById('enviarTodosCancelar').addEventListener('click', fecharEnviarTodos);
        modalEnviar.addEventListener('click', function (e) { if (e.target === this) fecharEnviarTodos(); });

        document.getElementById('enviarTodosConfirmar').addEventListener('click', function () {
            var btn  = this;
            var erro = document.getElementById('enviarTodosErro');

            btn.disabled = true;
            btn.textContent = 'Enviando...';

            fetch(ADMIN_BASE_URL + '/services/enviar_todos_confeccao.php', {
                method: 'POST',
                credentials: 'same-origin'
            })
            .then(function (r) {
                return r.text().then(function (t) {
                    try { return JSON.parse(t); }
                    catch (e) {
                        console.error('Resposta não-JSON ao enviar todos:', t.slice(0, 500));
                        return { success: false, message: 'O servidor respondeu com erro ' + r.status + '.' };
                    }
                });
            })
            .then(function (d) {
                btn.disabled = false;
                btn.textContent = 'Sim, enviar';

                if (d.success) { fecharEnviarTodos(); carregar(); return; }

                erro.textContent = d.message || 'Não foi possível enviar.';
                erro.style.display = '';
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = 'Sim, enviar';
                erro.textContent = 'Erro de conexão.';
                erro.style.display = '';
            });
        });
    }

    // ── Impressão / PDF ─────────────────────────────────────────────────────────
    //
    // Usa a impressão do próprio navegador (Ctrl+P → Salvar como PDF). O layout de papel
    // vem do @media print no LESS: fundo branco, grade fechada e sem os elementos de tela
    // (menu, filtros, botões), pra sair parecido com uma planilha que o fornecedor lê.
    var btnImprimir = document.getElementById('btnImprimir');
    if (btnImprimir) {
        btnImprimir.addEventListener('click', function () {
            atualizarCabecalhoImpressao();
            window.print();
        });
    }

    /** Preenche o cabeçalho que só existe no papel: filtro aplicado e totais. */
    function atualizarCabecalhoImpressao() {
        var elFiltro = document.getElementById('printFiltro');
        var elTotal  = document.getElementById('printTotal');
        if (!elFiltro || !elTotal) return;

        var lista = listaVisivel();

        // No papel o fornecedor precisa saber que recorte é aquele — a aba não aparece
        // na impressão.
        var abaAtiva = document.querySelector('.uniformes__tab.is-active');
        var nomeAba  = abaAtiva ? abaAtiva.childNodes[0].textContent.trim() : 'Todos';

        elFiltro.textContent = nomeAba
            + (filtroAtivo === 'todos' ? '' : ' · ' + (labels[filtroAtivo] || filtroAtivo));

        // O fornecedor precisa saber quantas peças de cada produto, não só o total de linhas.
        var contagem = contarPorProduto(lista);
        var partes   = [lista.length + ' pedido' + (lista.length === 1 ? '' : 's')];

        contagem.forEach(function (c) { partes.push(c.qtd + ' × ' + c.nome); });

        elTotal.textContent = partes.join(' · ');
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

    // ── Abas ────────────────────────────────────────────────────────────────────
    //
    // Trocar de aba zera o filtro de status: os chips da aba anterior não valem aqui, e
    // manter "Entregue" selecionado ao voltar pra produção deixaria a lista vazia sem
    // explicação nenhuma na tela.
    function aplicarAba() {
        filtros.forEach(function (chip) {
            var grupoDoChip = chip.getAttribute('data-grupo');
            var vale = !grupoDoChip || grupoAtivo === 'todos' || grupoDoChip === grupoAtivo;

            chip.style.display = vale ? '' : 'none';
            chip.classList.toggle('is-active', chip.getAttribute('data-filtro') === 'todos');
        });

        filtroAtivo = 'todos';

        // O envio em massa age sobre pedidos PENDENTES: numa aba que não os mostra, o botão
        // só convidaria ao clique errado.
        if (btnEnviarTodos) {
            var temPendentes = (statusDoGrupo[grupoAtivo] || []).indexOf('pendente') !== -1;
            btnEnviarTodos.style.display = temPendentes ? '' : 'none';
        }

        renderStats(contagemStatus);
        render();
    }

    abas.forEach(function (aba) {
        aba.addEventListener('click', function () {
            abas.forEach(function (a) { a.classList.remove('is-active'); });
            aba.classList.add('is-active');
            grupoAtivo = aba.getAttribute('data-grupo');
            aplicarAba();
        });
    });

    carregar();
}());
