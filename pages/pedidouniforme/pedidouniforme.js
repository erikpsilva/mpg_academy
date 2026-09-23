/**
 * Formulário de pedido de uniforme.
 *
 * O modal de números busca a disponibilidade no servidor a cada abertura (e sempre que
 * muda turma ou gênero), porque o balde de numeração é por TURMA + GÊNERO.
 *
 * O produto escolhido (uniforme completo, só a camisa, regata) manda no resto da tela:
 * decide quais tamanhos aparecem, quais cortes são possíveis e qual é o preço. Tudo isso
 * vem do PHP em UNIFORME_PRODUTOS/UNIFORME_VALORES, pra nunca divergir de
 * config/uniformes.php.
 */
(function () {
    var form = document.getElementById('uniformOrderForm');
    if (!form) return;

    var elTurma      = document.getElementById('uniformTurma');
    var elNome       = document.getElementById('uniformNome');
    var elNumero     = document.getElementById('uniformNumero');
    var elNumberBtn  = document.getElementById('uniformNumberPick');
    var elNumberLbl  = document.getElementById('uniformNumberLabel');
    var elSubmit     = document.getElementById('uniformSubmit');
    var elError      = document.getElementById('uniformError');

    // Camisa e shorts têm grades diferentes entre si e entre os gêneros — cada peça tem
    // seu próprio seletor e sua própria tabela de medidas.
    var pecas = {
        camisa: {
            input: document.getElementById('uniformTamanhoCamisa'),
            box:   document.getElementById('uniformSizesCamisa'),
            label: document.getElementById('labelTamCamisa'),
            resumo: document.getElementById('resumoTamCamisa')
        },
        shorts: {
            input: document.getElementById('uniformTamanhoShorts'),
            box:   document.getElementById('uniformSizesShorts'),
            label: document.getElementById('labelTamShorts'),
            resumo: document.getElementById('resumoTamShorts')
        }
    };

    var modal     = document.getElementById('uniformNumbersModal');
    var modalGrid = document.getElementById('uniformNumbersGrid');
    var modalSub  = document.getElementById('uniformNumbersSub');
    var measuresModal = document.getElementById('uniformMeasuresModal');
    var measuresBody  = document.getElementById('uniformMeasuresBody');
    var measuresSub   = document.getElementById('uniformMeasuresSub');

    var resumo = {
        produto: document.getElementById('resumoProduto'),
        modelo: document.getElementById('resumoModelo'),
        nome:   document.getElementById('resumoNome'),
        numero: document.getElementById('resumoNumero'),
        labelCamisa: document.getElementById('resumoLabelCamisa'),
        labelShorts: document.getElementById('resumoLabelShorts'),
        linhaShorts: document.getElementById('resumoLinhaShorts'),
        total:  document.getElementById('resumoTotal')
    };

    var fieldShorts   = document.getElementById('fieldTamShorts');
    var notaProduto   = document.getElementById('uniformProductNote');
    var submitValor   = document.getElementById('uniformSubmitValor');

    function moeda(v) {
        return 'R$ ' + Number(v).toFixed(2).replace('.', ',');
    }

    // ── Produto ─────────────────────────────────────────────────────────────────
    function produtoAtual() {
        var r = form.querySelector('input[name="produto"]:checked');
        return r ? r.value : 'completo';
    }

    function fichaProduto() {
        return UNIFORME_PRODUTOS[produtoAtual()] || UNIFORME_PRODUTOS.completo;
    }

    /** Peça de cima do produto: a regata tem grade própria, os demais usam a da camisa. */
    function pecaDeCima() {
        return produtoAtual() === 'regata' ? 'regata' : 'camisa';
    }

    function temShorts() {
        return fichaProduto().pecas.indexOf('shorts') !== -1;
    }

    /**
     * Um produto não existe em todo corte — a regata tem grade única de adulto. Os cartões
     * fora do catálogo somem, e se o corte escolhido era um deles, cai pro primeiro válido.
     */
    function aplicarProduto() {
        var cortes = fichaProduto().cortes;

        form.querySelectorAll('[data-genero-card]').forEach(function (card) {
            var vale = cortes.indexOf(card.getAttribute('data-genero-card')) !== -1;
            card.style.display = vale ? '' : 'none';
            if (!vale) card.querySelector('input').checked = false;
        });

        if (!modeloSelecionado() && cortes.length) {
            var primeiro = form.querySelector('[data-genero-card="' + cortes[0] + '"] input');
            if (primeiro) primeiro.checked = true;
        }

        if (fieldShorts) fieldShorts.style.display = temShorts() ? '' : 'none';
        if (resumo.linhaShorts) resumo.linhaShorts.style.display = temShorts() ? '' : 'none';

        var valor = UNIFORME_VALORES[produtoAtual()];
        if (submitValor) submitValor.textContent = moeda(valor);
        if (resumo.total) resumo.total.textContent = moeda(valor);

        if (notaProduto) {
            notaProduto.textContent = produtoAtual() === 'regata'
                ? 'A regata tem corte unissex e grade única (PP ao XG3) — o modelo escolhido aqui define a cor e a numeração da sua turma.'
                : 'O corte define a modelagem e a grade de tamanhos da sua peça.';
        }

        renderTamanhos();
        atualizarResumo();
    }

    function escapar(txt) {
        var d = document.createElement('div');
        d.textContent = txt == null ? '' : txt;
        return d.innerHTML;
    }

    function tabela(genero, peca) {
        return (UNIFORME_MEDIDAS[genero] && UNIFORME_MEDIDAS[genero][peca]) || null;
    }

    function modeloSelecionado() {
        return form.querySelector('input[name="modelo_completo"]:checked');
    }

    function generoAtual() {
        var m = modeloSelecionado();
        return m ? m.getAttribute('data-genero') : 'masculino';
    }

    // ── Tamanhos ────────────────────────────────────────────────────────────────
    // Os botões saem da própria tabela de medidas do gênero escolhido, então trocar de
    // masculino pra feminino troca a grade inteira (a camisa masculina vai até XG3, a
    // feminina só até XG; o calção e a bermuda também não batem).
    function renderTamanhos() {
        var genero = generoAtual();

        Object.keys(pecas).forEach(function (slot) {
            var ref = pecas[slot];
            // O campo de cima mostra a grade da peça do produto: camisa no uniforme e na
            // camisa avulsa, regata quando o pedido é de regata.
            var peca = slot === 'camisa' ? pecaDeCima() : slot;
            var t   = (slot === 'shorts' && !temShorts()) ? null : tabela(genero, peca);
            var anterior = ref.input.value;

            ref.box.innerHTML = '';
            ref.input.value   = '';
            if (!t) return;

            ref.label.textContent = 'Tamanho — ' + t.label;
            if (slot === 'camisa' && resumo.labelCamisa) {
                resumo.labelCamisa.textContent = 'Tam. ' + t.label.split(' ')[0].toLowerCase();
            }
            if (slot === 'shorts' && resumo.labelShorts) {
                resumo.labelShorts.textContent = 'Tam. ' + t.label.split(' ')[0].toLowerCase();
            }

            t.linhas.forEach(function (linha) {
                var tam = linha[0];
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'uniformOrder__size';
                btn.textContent = tam;
                btn.title = t.colunas.slice(1).map(function (c, i) {
                    return c + ': ' + linha[i + 1];
                }).join(' · ');

                btn.addEventListener('click', function () {
                    ref.box.querySelectorAll('.uniformOrder__size').forEach(function (b) {
                        b.classList.remove('is-active');
                    });
                    btn.classList.add('is-active');
                    ref.input.value = tam;
                    atualizarResumo();
                });

                // Mantém a escolha se o tamanho também existir na nova grade.
                if (tam === anterior) {
                    btn.classList.add('is-active');
                    ref.input.value = tam;
                }

                ref.box.appendChild(btn);
            });
        });
    }

    // ── Tabela de medidas (modal) ───────────────────────────────────────────────
    function renderMedidas(peca) {
        var genero = generoAtual();
        if (peca === 'camisa') peca = pecaDeCima();
        var t = tabela(genero, peca);
        if (!t || !measuresBody) return;

        if (measuresSub) {
            measuresSub.textContent = 'Compare com uma peça que você já usa para escolher o tamanho.';
        }

        var html = '<div class="uniMedidas"><section class="uniMedidas__bloco uniMedidas__bloco--' + genero + '">'
                 + '<h4>' + escapar(t.label) + '</h4><div class="uniMedidas__scroll"><table><thead><tr>';
        t.colunas.forEach(function (c) { html += '<th>' + escapar(c) + '</th>'; });
        html += '</tr></thead><tbody>';
        t.linhas.forEach(function (linha) {
            html += '<tr>';
            linha.forEach(function (v, i) {
                html += (i === 0 ? '<th>' + escapar(v) + '</th>' : '<td>' + escapar(v) + '</td>');
            });
            html += '</tr>';
        });
        html += '</tbody></table></div></section></div>'
              + '<p class="uniMedidas__aviso">' + escapar(UNIFORME_AVISO_MEDIDAS) + '</p>';

        measuresBody.innerHTML = html;
    }

    // ── Resumo ──────────────────────────────────────────────────────────────────
    function atualizarResumo() {
        var ficha = fichaProduto();
        if (resumo.produto) resumo.produto.textContent = ficha.nome;

        var m = modeloSelecionado();
        if (m) {
            var genero = m.getAttribute('data-genero');
            var modelo = m.getAttribute('data-modelo');
            resumo.modelo.textContent =
                (UNIFORME_GENERO_LABEL[genero] || genero) + ' — ' +
                (UNIFORME_MODELOS_LABEL[modelo] || modelo);
        } else {
            resumo.modelo.textContent = '—';
        }

        resumo.nome.textContent   = elNome.value.trim() ? elNome.value.trim().toUpperCase() : '—';
        resumo.numero.textContent = elNumero.value ? elNumero.value : '—';

        Object.keys(pecas).forEach(function (peca) {
            var ref = pecas[peca];
            if (ref.resumo) ref.resumo.textContent = ref.input.value ? ref.input.value : '—';
        });
    }

    // ── Modal de números ────────────────────────────────────────────────────────
    function abrirModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        carregarNumeros();
    }

    function fecharModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function carregarNumeros() {
        var genero = generoAtual();
        modalGrid.innerHTML = '<p class="uniformNumbers__loading">Carregando números...</p>';
        modalSub.textContent = 'Disponibilidade do uniforme ' +
            (UNIFORME_GENERO_LABEL[genero] || genero).toLowerCase() + ' na sua turma.';

        var url = BASE_URL + '/services/site/get_numeros_uniforme.php'
                + '?turma_id=' + encodeURIComponent(elTurma.value)
                + '&genero='   + encodeURIComponent(genero);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    modalGrid.innerHTML = '<p class="uniformNumbers__loading">' +
                        (data.message || 'Não foi possível carregar os números.') + '</p>';
                    return;
                }
                renderNumeros(data);
            })
            .catch(function () {
                modalGrid.innerHTML = '<p class="uniformNumbers__loading">Erro ao carregar. Tente novamente.</p>';
            });
    }

    function renderNumeros(data) {
        var ocupados = data.ocupados || [];
        var meus     = data.meus || [];

        modalGrid.innerHTML = '';

        for (var n = data.min; n <= data.max; n++) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = n;
            btn.className = 'uniformNumbers__item';

            if (ocupados.indexOf(n) !== -1) {
                btn.classList.add('is-taken');
                btn.disabled = true;
                btn.title = 'Número já usado por outro aluno da turma';
            } else if (meus.indexOf(n) !== -1) {
                btn.classList.add('is-mine');
                btn.title = 'Este número já é seu';
            }

            if (String(n) === elNumero.value) btn.classList.add('is-selected');

            if (!btn.disabled) {
                btn.addEventListener('click', (function (numero) {
                    return function () {
                        elNumero.value        = numero;
                        elNumberLbl.textContent = 'Número ' + numero;
                        elNumberBtn.classList.add('is-filled');
                        atualizarResumo();
                        fecharModal();
                    };
                }(n)));
            }

            modalGrid.appendChild(btn);
        }
    }

    // ── Eventos ─────────────────────────────────────────────────────────────────
    form.querySelectorAll('input[name="produto"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            // Trocar de produto pode trocar o corte (a regata não tem infantil), e o corte
            // é o balde da numeração — por isso o número volta a zero junto.
            elNumero.value = '';
            elNumberLbl.textContent = 'Escolher número';
            elNumberBtn.classList.remove('is-filled');
            aplicarProduto();
        });
    });

    form.querySelectorAll('input[name="modelo_completo"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            // Trocar de gênero muda o balde de numeração: o número escolhido pode não
            // valer mais, então zera pra forçar nova escolha.
            renderTamanhos();
            elNumero.value = '';
            elNumberLbl.textContent = 'Escolher número';
            elNumberBtn.classList.remove('is-filled');
            atualizarResumo();
        });
    });

    if (elTurma && elTurma.tagName === 'SELECT') {
        elTurma.addEventListener('change', function () {
            elNumero.value = '';
            elNumberLbl.textContent = 'Escolher número';
            elNumberBtn.classList.remove('is-filled');
            atualizarResumo();
        });
    }

    elNome.addEventListener('input', atualizarResumo);
    elNumberBtn.addEventListener('click', abrirModal);

    document.querySelectorAll('.js-numbers-close').forEach(function (btn) {
        btn.addEventListener('click', fecharModal);
    });

    function abrirMedidas(peca) {
        renderMedidas(peca || 'camisa');
        measuresModal.classList.add('is-open');
        measuresModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function fecharMedidas() {
        measuresModal.classList.remove('is-open');
        measuresModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    // Cada peça abre a SUA tabela — evita o aluno comparar a camisa pela medida do shorts.
    document.querySelectorAll('[data-medidas]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            abrirMedidas(btn.getAttribute('data-medidas'));
        });
    });

    document.querySelectorAll('.js-measures-close').forEach(function (btn) {
        btn.addEventListener('click', fecharMedidas);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) fecharModal();
        if (e.key === 'Escape' && measuresModal.classList.contains('is-open')) fecharMedidas();
    });

    // ── Envio ───────────────────────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        elError.textContent = '';

        var m = modeloSelecionado();

        if (!m)                    return erro('Escolha o modelo do uniforme.');
        if (!elNome.value.trim())  return erro('Informe o nome que vai na camiseta.');
        if (!elNumero.value)       return erro('Escolha o número da camiseta.');
        if (!pecas.camisa.input.value) {
            return erro('Escolha o tamanho ' + (pecaDeCima() === 'regata' ? 'da regata' : 'da camisa') + '.');
        }
        if (temShorts() && !pecas.shorts.input.value) {
            var t = tabela(generoAtual(), 'shorts');
            return erro('Escolha o tamanho ' + (t ? 'd' + (t.label.indexOf('Bermuda') === 0 ? 'a bermuda' : 'o calção') : 'do shorts') + '.');
        }

        elSubmit.disabled    = true;
        elSubmit.textContent = 'Criando seu pedido...';

        var body = new URLSearchParams({
            produto:        produtoAtual(),
            turma_id:       elTurma.value,
            genero:         m.getAttribute('data-genero'),
            modelo:         m.getAttribute('data-modelo'),
            nome_camisa:    elNome.value.trim(),
            numero:         elNumero.value,
            // A regata é gravada no campo da camisa — ver uniformeValidarTamanhos().
            tamanho_camisa: pecas.camisa.input.value,
            tamanho_shorts: temShorts() ? pecas.shorts.input.value : ''
        });

        fetch(BASE_URL + '/services/site/criar_pedido_uniforme.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success && data.redirect) {
                window.location.href = data.redirect;
                return;
            }

            // Alguém pegou o número entre a escolha e o envio — reabre o modal atualizado.
            if (data.numero_usado) {
                elNumero.value = '';
                elNumberLbl.textContent = 'Escolher número';
                elNumberBtn.classList.remove('is-filled');
                atualizarResumo();
                abrirModal();
            }

            erro(data.message || 'Não foi possível criar o pedido.');
            restaurarBotao();
        })
        .catch(function () {
            erro('Erro de conexão. Tente novamente.');
            restaurarBotao();
        });
    });

    function erro(msg) {
        elError.textContent = msg;
        elError.classList.add('is-visible');
    }

    // Monta o botão de novo em vez de só devolver o texto: o valor vive num <span> dentro
    // dele, e escrever textContent no botão apagaria esse span pra sempre.
    function restaurarBotao() {
        elSubmit.disabled  = false;
        elSubmit.innerHTML = 'Ir para o pagamento — <span id="uniformSubmitValor"></span>';
        submitValor = document.getElementById('uniformSubmitValor');
        submitValor.textContent = moeda(UNIFORME_VALORES[produtoAtual()]);
    }

    // aplicarProduto() já chama renderTamanhos() e atualizarResumo().
    aplicarProduto();
}());
