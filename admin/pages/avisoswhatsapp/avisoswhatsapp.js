/**
 * Disparo de aviso por WhatsApp para uma turma.
 *
 * O envio é feito aqui, um destinatário por requisição, com intervalo entre eles. Poderia ser
 * um laço no servidor, mas aí a tela ficaria parada um minuto sem dizer nada e um timeout no
 * meio deixaria ninguém sabendo quem já recebeu. Assim cada pessoa vira uma linha que muda de
 * estado na hora, e o que falhou fica visível pra reenviar.
 */
(function () {
    var selTurma     = document.getElementById('avisoTurma');
    if (!selTurma) return;

    var txtMensagem  = document.getElementById('avisoTexto');
    var contador     = document.getElementById('avisoContador');
    var inputImagem  = document.getElementById('avisoImagem');
    var preview      = document.getElementById('avisoPreview');
    var previewImg   = document.getElementById('avisoPreviewImg');
    var removerImg   = document.getElementById('avisoRemoverImg');
    var caixaDest    = document.getElementById('avisoDestinatarios');
    var btnDisparar  = document.getElementById('avisoDisparar');
    var caixaErro    = document.getElementById('avisoErro');
    var progresso    = document.getElementById('avisoProgresso');
    var barra        = document.getElementById('avisoBarra');
    var progressoTxt = document.getElementById('avisoProgressoTexto');

    var modal        = document.getElementById('avisoModal');
    var modalInfo    = document.getElementById('avisoModalInfo');
    var modalPreview = document.getElementById('avisoModalPreview');

    var contatos    = [];
    var imagemUrl   = '';
    var enviando    = false;

    // Intervalo entre um envio e outro. Rajada de mensagens idênticas é o que faz o WhatsApp
    // marcar o número da academia como spam — e aí nenhum aviso chega mais.
    var INTERVALO_MS = 4000;

    function escapar(txt) {
        var d = document.createElement('div');
        d.textContent = txt == null ? '' : txt;
        return d.innerHTML;
    }

    function erro(msg) {
        caixaErro.textContent = msg || '';
        caixaErro.style.display = msg ? '' : 'none';
    }

    function atualizarBotao() {
        var temTexto = txtMensagem.value.trim() !== '' || imagemUrl !== '';
        btnDisparar.disabled = enviando || !contatos.length || !temTexto;
    }

    // ── Turma e destinatários ───────────────────────────────────────────────────
    selTurma.addEventListener('change', function () {
        contatos = [];
        atualizarBotao();

        if (!selTurma.value) {
            caixaDest.innerHTML = '<p class="avisoWpp__vazio">Escolha uma turma para ver a lista.</p>';
            return;
        }

        caixaDest.innerHTML = '<p class="avisoWpp__vazio">Carregando…</p>';

        fetch(ADMIN_BASE_URL + '/services/get_contatos_turma.php?turma_id=' + encodeURIComponent(selTurma.value),
              { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    caixaDest.innerHTML = '<p class="avisoWpp__vazio">' + escapar(d.message || 'Erro ao carregar.') + '</p>';
                    return;
                }

                contatos = d.contatos || [];
                renderDestinatarios(d);
                atualizarBotao();
            })
            .catch(function () {
                caixaDest.innerHTML = '<p class="avisoWpp__vazio">Erro ao carregar a lista.</p>';
            });
    });

    function renderDestinatarios(d) {
        if (!contatos.length) {
            caixaDest.innerHTML = '<p class="avisoWpp__vazio">Nenhum aluno ativo com número cadastrado nessa turma.</p>';
            return;
        }

        var html = '<p class="avisoWpp__resumo"><strong>' + contatos.length + '</strong> número(s) nesta turma</p>'
                 + '<ul class="avisoWpp__lista" id="avisoLista">';

        contatos.forEach(function (c, i) {
            html += '<li class="avisoWpp__item" data-i="' + i + '">'
                  + '  <span class="avisoWpp__status" data-status>•</span>'
                  + '  <span class="avisoWpp__pessoa">'
                  + '    <strong>' + escapar(c.para) + '</strong>'
                  + '    <small>' + escapar(c.telefone)
                  +        (c.eh_responsavel ? ' · responsável de ' : ' · ') + escapar(c.alunos.join(', '))
                  + '    </small>'
                  + '  </span>'
                  + '</li>';
        });

        html += '</ul>';

        // Quem ficou de fora precisa aparecer: é cadastro pra arrumar, não gente a ignorar.
        if (d.sem_numero && d.sem_numero.length) {
            html += '<p class="avisoWpp__semNumero"><strong>' + d.sem_numero.length + '</strong> sem número cadastrado: '
                  + d.sem_numero.map(function (s) { return escapar(s.aluno); }).join(', ') + '</p>';
        }

        caixaDest.innerHTML = html;
    }

    // ── Texto e imagem ──────────────────────────────────────────────────────────
    txtMensagem.addEventListener('input', function () {
        contador.textContent = txtMensagem.value.length + ' caracteres';
        atualizarBotao();
    });

    inputImagem.addEventListener('change', function () {
        var arquivo = inputImagem.files && inputImagem.files[0];
        if (!arquivo) return;

        erro('');
        btnDisparar.disabled = true;

        var form = new FormData();
        form.append('imagem', arquivo);
        form.append('destino', 'avisos');

        fetch(ADMIN_BASE_URL + '/services/upload_comunicado_img.php', {
            method: 'POST',
            credentials: 'same-origin',
            body: form
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.success) {
                erro(d.message || 'Não foi possível enviar a imagem.');
                inputImagem.value = '';
                atualizarBotao();
                return;
            }

            imagemUrl = d.url;
            previewImg.src = d.url;
            preview.hidden = false;
            atualizarBotao();
        })
        .catch(function () {
            erro('Erro ao enviar a imagem.');
            atualizarBotao();
        });
    });

    removerImg.addEventListener('click', function () {
        imagemUrl = '';
        inputImagem.value = '';
        preview.hidden = true;
        atualizarBotao();
    });

    // ── Confirmação ─────────────────────────────────────────────────────────────
    btnDisparar.addEventListener('click', function () {
        erro('');

        modalInfo.innerHTML = 'Enviar para <strong>' + contatos.length + ' número(s)</strong> da turma '
                            + '<strong>' + escapar(selTurma.options[selTurma.selectedIndex].textContent.trim()) + '</strong>?';

        modalPreview.innerHTML = (imagemUrl ? '<img src="' + imagemUrl + '" alt="">' : '')
                               + '<p>' + escapar(txtMensagem.value).replace(/\n/g, '<br>') + '</p>';

        modal.classList.add('confirmModal--open');
    });

    document.getElementById('avisoCancelar').addEventListener('click', function () {
        modal.classList.remove('confirmModal--open');
    });

    document.getElementById('avisoConfirmar').addEventListener('click', function () {
        modal.classList.remove('confirmModal--open');
        dispararTodos();
    });

    // ── Disparo ─────────────────────────────────────────────────────────────────
    function marcarItem(indice, estado, titulo) {
        var item = document.querySelector('.avisoWpp__item[data-i="' + indice + '"]');
        if (!item) return;

        item.classList.remove('is-enviando', 'is-ok', 'is-erro');
        item.classList.add(estado);
        item.querySelector('[data-status]').textContent =
            estado === 'is-ok' ? '✓' : (estado === 'is-erro' ? '✕' : '…');
        if (titulo) item.title = titulo;
    }

    function dispararTodos() {
        enviando = true;
        atualizarBotao();

        progresso.hidden = false;
        var enviados = 0;
        var falhas   = 0;

        function proximo(i) {
            if (i >= contatos.length) {
                enviando = false;
                atualizarBotao();
                progressoTxt.textContent = falhas
                    ? enviados + ' enviado(s), ' + falhas + ' com erro. Passe o mouse nos ✕ para ver o motivo.'
                    : 'Pronto! ' + enviados + ' aviso(s) enviado(s).';
                return;
            }

            var c = contatos[i];
            marcarItem(i, 'is-enviando');
            progressoTxt.textContent = 'Enviando ' + (i + 1) + ' de ' + contatos.length + '…';
            barra.style.width = Math.round(((i) / contatos.length) * 100) + '%';

            var body = new URLSearchParams({
                telefone: c.telefone,
                texto:    txtMensagem.value,
                imagem:   imagemUrl
            });

            fetch(ADMIN_BASE_URL + '/services/enviar_aviso_turma.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) { enviados++; marcarItem(i, 'is-ok'); }
                else { falhas++; marcarItem(i, 'is-erro', d.message || 'falhou'); }
            })
            .catch(function () {
                falhas++;
                marcarItem(i, 'is-erro', 'erro de conexão');
            })
            .finally(function () {
                barra.style.width = Math.round(((i + 1) / contatos.length) * 100) + '%';
                setTimeout(function () { proximo(i + 1); }, INTERVALO_MS);
            });
        }

        proximo(0);
    }

    // Fechar a aba no meio do disparo deixa metade da turma avisada e metade não.
    window.addEventListener('beforeunload', function (e) {
        if (!enviando) return;
        e.preventDefault();
        e.returnValue = '';
    });
}());
