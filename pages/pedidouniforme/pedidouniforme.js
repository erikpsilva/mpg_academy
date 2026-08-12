/**
 * Formulário de pedido de uniforme.
 *
 * O modal de números busca a disponibilidade no servidor a cada abertura (e sempre que
 * muda turma ou gênero), porque o balde de numeração é por TURMA + GÊNERO.
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
        modelo: document.getElementById('resumoModelo'),
        nome:   document.getElementById('resumoNome'),
        numero: document.getElementById('resumoNumero'),
        labelShorts: document.getElementById('resumoLabelShorts')
    };

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

        Object.keys(pecas).forEach(function (peca) {
            var ref = pecas[peca];
            var t   = tabela(genero, peca);
            var anterior = ref.input.value;

            ref.box.innerHTML = '';
            ref.input.value   = '';
            if (!t) return;

            ref.label.textContent = 'Tamanho — ' + t.label;
            if (peca === 'shorts' && resumo.labelShorts) {
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
        var m = modeloSelecionado();
        if (m) {
            var genero = m.getAttribute('data-genero');
            var modelo = m.getAttribute('data-modelo');
            resumo.modelo.textContent =
                (genero === 'feminino' ? 'Feminino' : 'Masculino') + ' — ' +
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
            (genero === 'feminino' ? 'feminino' : 'masculino') + ' na sua turma.';

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
        if (!pecas.camisa.input.value) return erro('Escolha o tamanho da camisa.');
        if (!pecas.shorts.input.value) {
            var t = tabela(generoAtual(), 'shorts');
            return erro('Escolha o tamanho ' + (t ? 'd' + (t.label.indexOf('Bermuda') === 0 ? 'a bermuda' : 'o calção') : 'do shorts') + '.');
        }

        elSubmit.disabled    = true;
        elSubmit.textContent = 'Criando seu pedido...';

        var body = new URLSearchParams({
            turma_id:       elTurma.value,
            genero:         m.getAttribute('data-genero'),
            modelo:         m.getAttribute('data-modelo'),
            nome_camisa:    elNome.value.trim(),
            numero:         elNumero.value,
            tamanho_camisa: pecas.camisa.input.value,
            tamanho_shorts: pecas.shorts.input.value
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

    function restaurarBotao() {
        elSubmit.disabled    = false;
        elSubmit.textContent = elSubmit.getAttribute('data-label') || 'Ir para o pagamento';
    }

    elSubmit.setAttribute('data-label', elSubmit.textContent.trim());

    renderTamanhos();
    atualizarResumo();
}());
