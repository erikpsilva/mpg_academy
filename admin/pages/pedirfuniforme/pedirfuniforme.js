(function () {
    var form = document.getElementById('pedirUniformeForm');
    if (!form) return;

    var buscaInput   = document.getElementById('buscaAluno');
    var buscaResults = document.getElementById('buscaAlunoResults');
    var alunoBox      = document.getElementById('alunoSelecionadoBox');
    var alunoNomeEl    = document.getElementById('alunoSelecionadoNome');
    var alunoEmailEl   = document.getElementById('alunoSelecionadoEmail');
    var alunoTrocarBtn = document.getElementById('alunoTrocarBtn');
    var alunoIdInput   = document.getElementById('alunoId');

    var turmaField  = document.getElementById('turmaField');
    var turmaSelect = document.getElementById('turmaSelect');
    var modeloBlock   = document.getElementById('modeloBlock');
    var detalhesBlock = document.getElementById('detalhesBlock');
    var resumoBlock   = document.getElementById('resumoBlock');

    var nomeCamisa  = document.getElementById('nomeCamisa');
    var numeroInput = document.getElementById('numeroInput');
    var numberPick  = document.getElementById('numberPick');
    var numberLabel = document.getElementById('numberLabel');
    var pecas = {
        camisa: {
            box:   document.getElementById('sizesBoxCamisa'),
            input: document.getElementById('tamanhoCamisaInput'),
            label: document.getElementById('labelTamCamisa')
        },
        shorts: {
            box:   document.getElementById('sizesBoxShorts'),
            input: document.getElementById('tamanhoShortsInput'),
            label: document.getElementById('labelTamShorts')
        }
    };

    var produtoRadios = document.querySelectorAll('input[name="produto"]');
    var produtoHint   = document.getElementById('produtoHint');
    var fieldShorts   = document.getElementById('fieldTamShorts');
    var valorProduto  = document.getElementById('resumoValorProduto');

    var modal     = document.getElementById('uniformNumbersModal');
    var modalGrid = document.getElementById('uniformNumbersGrid');
    var modalSub  = document.getElementById('uniformNumbersSub');
    var measuresModal = document.getElementById('adminMeasuresModal');
    var measuresOpen  = document.getElementById('adminMeasuresOpen');

    var submitBtn = document.getElementById('pedirUniformeSubmit');
    var errorBox  = document.getElementById('pedirUniformeError');

    var alunoAtual = null; // { id, nome, email, sexo }
    var buscaTimeout = null;

    // ── Busca de aluno ──────────────────────────────────────────────────────────
    buscaInput.addEventListener('input', function () {
        var termo = buscaInput.value.trim();
        clearTimeout(buscaTimeout);

        if (termo.length < 2) {
            buscaResults.innerHTML = '';
            buscaResults.classList.remove('is-open');
            return;
        }

        buscaTimeout = setTimeout(function () {
            fetch(ADMIN_BASE_URL + '/services/search_alunos_uniforme.php?busca=' + encodeURIComponent(termo), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderResultados((data.alunos || []));
                })
                .catch(function () { buscaResults.innerHTML = ''; });
        }, 250);
    });

    function renderResultados(alunos) {
        if (!alunos.length) {
            buscaResults.innerHTML = '<div class="pedirUniforme__searchEmpty">Nenhum aluno encontrado.</div>';
            buscaResults.classList.add('is-open');
            return;
        }

        var html = '';
        alunos.forEach(function (a) {
            html += '<button type="button" class="pedirUniforme__searchItem" data-id="' + a.id + '">'
                  + '<strong>' + escapar(a.nome) + '</strong><small>' + escapar(a.email) + '</small>'
                  + '</button>';
        });
        buscaResults.innerHTML = html;
        buscaResults.classList.add('is-open');

        buscaResults.querySelectorAll('[data-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selecionarAluno(parseInt(btn.getAttribute('data-id'), 10));
            });
        });
    }

    function escapar(txt) {
        var d = document.createElement('div');
        d.textContent = txt == null ? '' : txt;
        return d.innerHTML;
    }

    function selecionarAluno(id) {
        fetch(ADMIN_BASE_URL + '/services/get_turmas_aluno_uniforme.php?aluno_id=' + id, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || 'Erro ao carregar o aluno.');
                    return;
                }
                if (!data.turmas.length) {
                    alert('Esse aluno não está matriculado em nenhuma turma ativa — a numeração do uniforme depende de turma.');
                    return;
                }

                alunoAtual = data.aluno;
                alunoIdInput.value = data.aluno.id;

                alunoNomeEl.textContent  = data.aluno.nome;
                alunoEmailEl.textContent = data.aluno.email;
                alunoBox.style.display   = 'flex';
                buscaInput.value = '';
                buscaResults.innerHTML = '';
                buscaResults.classList.remove('is-open');
                buscaInput.parentElement.style.display = 'none';

                turmaSelect.innerHTML = '';
                data.turmas.forEach(function (t) {
                    var opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.nome;
                    turmaSelect.appendChild(opt);
                });
                turmaField.style.display = '';

                // Pré-seleciona o modelo pelo sexo cadastrado do aluno, se aplicável.
                var generoPadrao = (data.aluno.sexo === 'feminino') ? 'feminino' : 'masculino';
                var radioPadrao = form.querySelector('input[value="' + generoPadrao + '|padrao"]');
                if (radioPadrao) radioPadrao.checked = true;

                modeloBlock.style.display   = '';
                detalhesBlock.style.display = '';
                resumoBlock.style.display   = '';

                // aplicarProduto() já refaz os tamanhos e zera o número.
                aplicarProduto();
            })
            .catch(function () { alert('Erro ao comunicar com o servidor.'); });
    }

    alunoTrocarBtn.addEventListener('click', function () {
        alunoAtual = null;
        alunoIdInput.value = '';
        alunoBox.style.display = 'none';
        buscaInput.parentElement.style.display = '';
        turmaField.style.display   = 'none';
        // Os passos de modelo, personalização e confirmação ficam VISÍVEIS. Esconder o botão
        // de finalizar até tudo estar preenchido deixava a impressão de que ele não existia —
        // melhor deixar sempre à mão: o submit valida e diz exatamente o que falta.
        buscaInput.focus();
    });

    document.addEventListener('click', function (e) {
        if (!buscaResults.contains(e.target) && e.target !== buscaInput) {
            buscaResults.classList.remove('is-open');
        }
    });

    // ── Modelo / gênero ─────────────────────────────────────────────────────────
    function modeloSelecionado() {
        return form.querySelector('input[name="modelo_completo"]:checked');
    }

    function generoAtual() {
        if (corteUnico()) return generoDoCadastro();

        var m = modeloSelecionado();
        return m ? m.getAttribute('data-genero') : 'masculino';
    }

    form.querySelectorAll('input[name="modelo_completo"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            renderTamanhos();
            resetNumero();
        });
    });

    // ── Produto ─────────────────────────────────────────────────────────────────
    //
    // O produto (uniforme completo, só a camisa, regata) decide o preço, quais tamanhos
    // aparecem e em que cortes ele existe — a regata tem grade única de adulto.
    function produtoAtual() {
        var escolhido = document.querySelector('input[name="produto"]:checked');
        return escolhido ? escolhido.value : 'completo';
    }

    function fichaProduto() {
        return UNIFORME_PRODUTOS[produtoAtual()] || UNIFORME_PRODUTOS.completo;
    }

    function pecaDeCima() {
        return produtoAtual() === 'regata' ? 'regata' : 'camisa';
    }

    /** Peça única (regata): sem masculino/feminino/infantil pra escolher. */
    function corteUnico() {
        return !!fichaProduto().corte_unico;
    }

    /**
     * Corte gravado num produto de peça única: o do cadastro do aluno. Não muda a peça —
     * é só o balde da numeração (turma + corte), que precisa ser o mesmo do uniforme dele
     * pra o número continuar sendo dele.
     */
    function generoDoCadastro() {
        return (alunoAtual && alunoAtual.sexo === 'feminino') ? 'feminino' : 'masculino';
    }

    function temShorts() {
        return fichaProduto().pecas.indexOf('shorts') !== -1;
    }

    function aplicarProduto() {
        var ficha  = fichaProduto();
        var cortes = ficha.cortes;

        // Pedido de professor/equipe MPG escolhe o produto no próprio bloco de equipe
        // ("Tipo de uniforme"); mostrar os dois seletores confundiria qual manda.
        var campoProduto = document.getElementById('produtoField');
        if (campoProduto) campoProduto.style.display = destinoEhAluno() ? '' : 'none';

        // Peça única troca os cartões de corte por um aviso do que é a peça.
        var boxModelos = document.getElementById('modelosBox');
        var boxUnico   = document.getElementById('produtoUnicoAviso');
        var unico      = corteUnico() && destinoEhAluno();

        if (boxModelos) boxModelos.hidden = unico;
        if (boxUnico)   boxUnico.hidden   = !unico;

        form.querySelectorAll('[data-genero-card]').forEach(function (card) {
            var vale = cortes.indexOf(card.getAttribute('data-genero-card')) !== -1;
            card.style.display = vale ? '' : 'none';
            if (!vale) card.querySelector('input').checked = false;
        });

        if (!modeloSelecionado() && cortes.length) {
            var primeiro = form.querySelector('[data-genero-card="' + cortes[0] + '"] input');
            if (primeiro) primeiro.checked = true;
        }

        // O destino "equipe" tem regra própria de visibilidade (data-so-completo) — aqui só
        // mexemos no calção quando o pedido é de aluno.
        if (fieldShorts && destinoEhAluno()) {
            fieldShorts.style.display = temShorts() ? '' : 'none';
        }

        if (valorProduto) valorProduto.textContent = moeda(UNIFORME_VALORES[produtoAtual()]);
        if (produtoHint)  produtoHint.textContent  = ficha.descricao;

        renderTamanhos();
        resetNumero();
    }

    function destinoEhAluno() {
        var ativo = document.querySelector('[data-destino].is-active');
        return !ativo || ativo.getAttribute('data-destino') === 'aluno';
    }

    function moeda(v) {
        return 'R$ ' + Number(v).toFixed(2).replace('.', ',');
    }

    produtoRadios.forEach(function (radio) {
        radio.addEventListener('change', aplicarProduto);
    });

    // O bloco de equipe (script na própria página) mexe nos mesmos campos ao trocar o
    // destino; reaplicar depois devolve o formulário ao estado do produto escolhido.
    document.querySelectorAll('[data-destino]').forEach(function (btn) {
        btn.addEventListener('click', function () { setTimeout(aplicarProduto, 0); });
    });

    turmaSelect.addEventListener('change', resetNumero);

    function resetNumero() {
        numeroInput.value = '';
        numberLabel.textContent = 'Escolher número';
        numberPick.classList.remove('is-filled');
    }

    // ── Tamanhos ────────────────────────────────────────────────────────────────
    // Camisa e shorts têm grades próprias, e elas mudam com o gênero. Ver config/uniformes.php.
    function renderTamanhos() {
        var genero = generoAtual();

        Object.keys(pecas).forEach(function (slot) {
            var ref = pecas[slot];
            // O campo de cima mostra a grade da peça do produto: camisa no uniforme e na
            // camisa avulsa, regata quando o pedido é de regata.
            var peca = slot === 'camisa' ? pecaDeCima() : slot;
            var t   = (slot === 'shorts' && !temShorts()) ? null : (UNIFORME_MEDIDAS[genero] || {})[peca];
            var anterior = ref.input.value;

            ref.box.innerHTML = '';
            ref.input.value   = '';
            if (!t) return;

            ref.label.textContent = 'Tamanho — ' + t.label;

            t.linhas.forEach(function (linha) {
                var tam = linha[0];
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pedirUniforme__size';
                btn.textContent = tam;
                btn.title = t.colunas.slice(1).map(function (c, i) { return c + ': ' + linha[i + 1]; }).join(' · ');

                btn.addEventListener('click', function () {
                    ref.box.querySelectorAll('.pedirUniforme__size').forEach(function (b) { b.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                    ref.input.value = tam;
                });

                if (tam === anterior) {
                    btn.classList.add('is-active');
                    ref.input.value = tam;
                }

                ref.box.appendChild(btn);
            });
        });
    }

    // ── Modal de números ────────────────────────────────────────────────────────
    numberPick.addEventListener('click', function () {
        if (!alunoAtual) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        carregarNumeros();
    });

    function fecharModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-numbers-close').forEach(function (btn) {
        btn.addEventListener('click', fecharModal);
    });

    function abrirMedidas() {
        measuresModal.classList.add('is-open');
        measuresModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function fecharMedidas() {
        measuresModal.classList.remove('is-open');
        measuresModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    measuresOpen.addEventListener('click', abrirMedidas);
    document.querySelectorAll('.js-admin-measures-close').forEach(function (btn) {
        btn.addEventListener('click', fecharMedidas);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) fecharModal();
        if (e.key === 'Escape' && measuresModal.classList.contains('is-open')) fecharMedidas();
    });

    function carregarNumeros() {
        var genero = generoAtual();
        modalGrid.innerHTML = '<p class="uniformNumbers__loading">Carregando números...</p>';
        modalSub.textContent = 'Uniforme ' + (UNIFORME_GENERO_LABEL[genero] || genero).toLowerCase() + ' — ' +
            turmaSelect.options[turmaSelect.selectedIndex].textContent;

        var url = ADMIN_BASE_URL + '/services/get_numeros_uniforme_admin.php'
                + '?aluno_id=' + encodeURIComponent(alunoIdInput.value)
                + '&turma_id=' + encodeURIComponent(turmaSelect.value)
                + '&genero='   + encodeURIComponent(genero);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    modalGrid.innerHTML = '<p class="uniformNumbers__loading">' + (data.message || 'Erro ao carregar.') + '</p>';
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
                btn.title = 'Este número já é desse aluno';
            }

            if (String(n) === numeroInput.value) btn.classList.add('is-selected');

            if (!btn.disabled) {
                btn.addEventListener('click', (function (numero) {
                    return function () {
                        numeroInput.value = numero;
                        numberLabel.textContent = 'Número ' + numero;
                        numberPick.classList.add('is-filled');
                        fecharModal();
                    };
                }(n)));
            }

            modalGrid.appendChild(btn);
        }
    }

    // ── Envio ───────────────────────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorBox.textContent = '';
        errorBox.classList.remove('is-visible');

        var m = modeloSelecionado();

        if (!alunoIdInput.value)     return erro('Selecione o aluno.');
        if (!m && !corteUnico())     return erro('Escolha o modelo do uniforme.');
        if (!nomeCamisa.value.trim()) return erro('Informe o nome que vai na camiseta.');
        if (!numeroInput.value)      return erro('Escolha o número da camiseta.');
        if (!pecas.camisa.input.value) {
            return erro('Escolha o tamanho ' + (pecaDeCima() === 'regata' ? 'da regata' : 'da camisa') + '.');
        }
        if (temShorts() && !pecas.shorts.input.value) return erro('Escolha o tamanho do shorts.');

        if (!window.confirm('Confirma o pedido já como PAGO? Use só se o pagamento já foi coletado por fora do sistema.')) return;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Registrando...';

        var body = new URLSearchParams({
            aluno_id:    alunoIdInput.value,
            produto:     produtoAtual(),
            turma_id:    turmaSelect.value,
            // Peça única não tem corte nem cor escolhidos na tela: o corte sai do cadastro
            // (só pro balde da numeração) e o modelo é o fixo do produto.
            genero:      corteUnico() ? generoDoCadastro() : m.getAttribute('data-genero'),
            modelo:      corteUnico() ? 'padrao'           : m.getAttribute('data-modelo'),
            nome_camisa: nomeCamisa.value.trim(),
            numero:      numeroInput.value,
            // A regata vai no campo da camisa — ver uniformeValidarTamanhos().
            tamanho_camisa: pecas.camisa.input.value,
            tamanho_shorts: temShorts() ? pecas.shorts.input.value : ''
        });

        fetch(ADMIN_BASE_URL + '/services/criar_pedido_uniforme_manual.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                document.getElementById('sucessoModalInfo').textContent =
                    'Pedido #' + data.pedido_id + ' registrado como pago para ' + alunoAtual.nome + '.';
                document.getElementById('sucessoModal').classList.add('confirmModal--open');
            } else {
                erro(data.message || 'Não foi possível criar o pedido.');
                restaurarBotao();
            }
        })
        .catch(function () {
            erro('Erro de conexão. Tente novamente.');
            restaurarBotao();
        });
    });

    function erro(msg) {
        errorBox.textContent = msg;
        errorBox.classList.add('is-visible');
    }

    function restaurarBotao() {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Registrar pedido como pago';
    }

    // Estado inicial: produto padrão (uniforme completo) manda nos cortes e nos tamanhos.
    aplicarProduto();
}());
