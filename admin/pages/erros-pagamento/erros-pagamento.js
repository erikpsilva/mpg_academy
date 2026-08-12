(function () {
    var lista        = document.getElementById('errosLista');
    var totalEl      = document.getElementById('totalPendentes');
    var filtros      = document.querySelectorAll('.errosPagamento__filter');
    var mostrarTodos = false;

    var CONTEXTO_LABEL = {
        mensalidade: 'Mensalidade',
        uniforme:    'Uniforme',
        batebola:    'Bate Bola',
        outro:       'Outro'
    };

    var ORIGEM_LABEL = {
        site:   'Site',
        mobile: 'App',
        cron:   'Cobrança automática',
        admin:  'Admin'
    };

    function escapar(txt) {
        var d = document.createElement('div');
        d.textContent = txt == null ? '' : txt;
        return d.innerHTML;
    }

    function moeda(v) {
        if (v === null || v === undefined) return '—';
        return 'R$ ' + Number(v).toFixed(2).replace('.', ',');
    }

    function carregar() {
        lista.innerHTML = '<p class="errosPagamento__vazio">Carregando...</p>';

        fetch(ADMIN_BASE_URL + '/services/get_erros_pagamento.php?resolvidos=' + (mostrarTodos ? '1' : '0'), {
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                lista.innerHTML = '<p class="errosPagamento__vazio">Erro ao carregar.</p>';
                return;
            }

            totalEl.textContent = data.nao_resolvidos;
            render(data.erros || []);

            // Abrir a tela zera o badge do sino.
            if (data.nao_vistos > 0) marcarVistos();
        })
        .catch(function () {
            lista.innerHTML = '<p class="errosPagamento__vazio">Erro ao carregar.</p>';
        });
    }

    function render(erros) {
        if (!erros.length) {
            lista.innerHTML = '<p class="errosPagamento__vazio">Nenhum erro de pagamento por aqui. 🎉</p>';
            return;
        }

        var html = '';
        erros.forEach(function (e) {
            var whats = e.aluno_celular
                ? '<a class="errosPagamento__whats" target="_blank" rel="noopener" href="https://wa.me/55' +
                  encodeURIComponent(String(e.aluno_celular).replace(/\D/g, '')) + '">Chamar no WhatsApp</a>'
                : '';

            html += '<article class="errosPagamentoCard' + (e.resolvido ? ' is-resolvido' : '') + (e.novo ? ' is-novo' : '') + '">'
                  + '<div class="errosPagamentoCard__top">'
                  +   '<div class="errosPagamentoCard__who">'
                  +     '<strong>' + escapar(e.aluno_nome) + (e.novo ? ' <span class="errosPagamentoCard__novoTag">NOVO</span>' : '') + '</strong>'
                  +     '<small>' + escapar(e.aluno_email || '') + (e.aluno_celular ? ' · ' + escapar(e.aluno_celular) : '') + '</small>'
                  +   '</div>'
                  +   '<div class="errosPagamentoCard__meta">'
                  +     '<span class="errosPagamentoCard__ctx errosPagamentoCard__ctx--' + e.contexto + '">'
                  +       escapar(CONTEXTO_LABEL[e.contexto] || e.contexto) + '</span>'
                  +     '<span class="errosPagamentoCard__data">' + escapar(e.criado_em) + '</span>'
                  +   '</div>'
                  + '</div>'

                  + '<div class="errosPagamentoCard__motivo">'
                  +   '<span>Motivo</span>'
                  +   '<strong>' + escapar(e.motivo) + '</strong>'
                  + '</div>';

            if (e.acao) {
                html += '<div class="errosPagamentoCard__acao"><span>O que fazer</span>' + escapar(e.acao) + '</div>';
            }

            html += '<dl class="errosPagamentoCard__dados">'
                  +   '<div><dt>Cobrança</dt><dd>' + escapar(e.referencia_label || '—') + '</dd></div>'
                  +   '<div><dt>Valor</dt><dd>' + moeda(e.valor) + '</dd></div>'
                  +   '<div><dt>Método</dt><dd>' + escapar(e.metodo || '—')
                  +     (e.parcelas && e.parcelas > 1 ? ' (' + e.parcelas + 'x)' : '') + '</dd></div>'
                  +   '<div><dt>Origem</dt><dd>' + escapar(ORIGEM_LABEL[e.origem] || e.origem) + '</dd></div>'
                  + '</dl>'

                  + '<div class="errosPagamentoCard__acoes">'
                  +   whats
                  +   '<button type="button" class="errosPagamentoCard__detalhe" data-detalhe="' + e.id + '">Ver detalhe técnico</button>'
                  +   '<button type="button" class="btn btn--sm ' + (e.resolvido ? 'btn--gray' : 'btn--primary') + '" '
                  +     'data-resolver="' + e.id + '" data-novo-valor="' + (e.resolvido ? '0' : '1') + '">'
                  +     (e.resolvido ? 'Reabrir' : 'Marcar como resolvido') + '</button>'
                  + '</div>'

                  + '<pre class="errosPagamentoCard__tecnico" id="tec-' + e.id + '" hidden>'
                  +   'status: ' + escapar(e.mp_status || '—')
                  +   (e.mp_status_detail ? '\nstatus_detail: ' + escapar(e.mp_status_detail) : '')
                  +   (e.http_code ? '\nHTTP: ' + e.http_code : '')
                  +   (e.mp_payment_id ? '\npayment_id: ' + escapar(e.mp_payment_id) : '')
                  +   (e.detalhe_tecnico ? '\n\n' + escapar(e.detalhe_tecnico) : '')
                  + '</pre>'
                  + '</article>';
        });

        lista.innerHTML = html;

        lista.querySelectorAll('[data-detalhe]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pre = document.getElementById('tec-' + btn.getAttribute('data-detalhe'));
                pre.hidden = !pre.hidden;
                btn.textContent = pre.hidden ? 'Ver detalhe técnico' : 'Esconder detalhe técnico';
            });
        });

        lista.querySelectorAll('[data-resolver]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                resolver(btn.getAttribute('data-resolver'), btn.getAttribute('data-novo-valor'), btn);
            });
        });
    }

    function resolver(erroId, novoValor, btn) {
        btn.disabled = true;

        var body = new URLSearchParams({ erro_id: erroId, resolvido: novoValor });

        fetch(ADMIN_BASE_URL + '/services/resolver_erro_pagamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) carregar();
            else { alert(data.message || 'Erro ao salvar.'); btn.disabled = false; }
        })
        .catch(function () { alert('Erro ao comunicar com o servidor.'); btn.disabled = false; });
    }

    function marcarVistos() {
        var body = new URLSearchParams({ acao: 'marcar_vistos' });
        fetch(ADMIN_BASE_URL + '/services/resolver_erro_pagamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        }).catch(function () {});
    }

    filtros.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filtros.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            mostrarTodos = btn.getAttribute('data-filtro') === 'todos';
            carregar();
        });
    });

    carregar();
}());
