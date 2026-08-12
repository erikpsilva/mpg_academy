<style>
.header__impersonating {
    display: flex; align-items: center; gap: 10px; margin-right: 16px;
    background: rgba(229, 194, 0, .1); border: 1px solid rgba(229, 194, 0, .35);
    border-radius: 8px; padding: 8px 14px; font-size: 12px; color: #e5c200;
}
.header__impersonating strong { color: #fff; }
.header__impersonating a {
    color: #e5c200; font-weight: 700; text-decoration: underline; white-space: nowrap;
}
.header__impersonating a:hover { color: #fff; }
.header__notif { position: relative; margin-right: 16px; }
.header__notifBtn {
    position: relative; display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; background: transparent; border: 1px solid #2a2a2a;
    border-radius: 8px; cursor: pointer; color: #ccc; font-size: 18px; transition: .2s;
}
.header__notifBtn:hover { border-color: #e5c200; color: #e5c200; }
.header__notifBadge {
    position: absolute; top: -6px; right: -6px; min-width: 18px; height: 18px;
    padding: 0 4px; background: #e53535; border-radius: 10px;
    font-size: 10px; font-weight: 900; color: #fff; display: flex;
    align-items: center; justify-content: center; line-height: 1;
}
.header__notifDropdown {
    display: none; position: absolute; top: calc(100% + 10px); right: 0;
    width: 340px; background: #141414; border: 1px solid #2a2a2a;
    border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.6); z-index: 9999; overflow: hidden;
}
.header__notifDropdown.is-open { display: block; }
.header__notifHead {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; border-bottom: 1px solid #222;
}
.header__notifHead strong { font-size: 13px; color: #eee; font-weight: 700; }
.header__notifEnviar {
    font-size: 11px; font-weight: 700; color: #e5c200; background: none;
    border: 1px solid #e5c200; border-radius: 4px; padding: 3px 8px; cursor: pointer;
    text-transform: uppercase; transition: .2s;
}
.header__notifEnviar:hover { background: #e5c200; color: #050505; }
.header__notifList { max-height: 320px; overflow-y: auto; }
.header__notifItem {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px; border-bottom: 1px solid #1e1e1e; transition: .15s;
}
.header__notifItem:hover { background: rgba(255,255,255,.03); }
.header__notifItem:last-child { border-bottom: 0; }
.header__notifDot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px;
}
.header__notifDot--ok       { background: #ff8c00; }
.header__notifDot--alerta   { background: #ff4444; }
.header__notifDot--uniforme { background: #e5c200; }
.header__notifSection { border-bottom: 1px solid #222; }
.header__notifSection .header__notifList { max-height: 240px; }
.header__notifItem--link { text-decoration: none; }
.header__notifItem--link:hover .header__notifNome { color: #e5c200; }
.header__notifDias--uniforme { color: #888; font-weight: 600; }
a.header__notifEnviar { text-decoration: none; display: inline-block; }
.header__notifInfo { min-width: 0; flex: 1; }
.header__notifNome { display: block; font-size: 13px; font-weight: 700; color: #eee; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.header__notifMeta { display: block; font-size: 11px; color: #888; margin-top: 2px; }
.header__notifDias { font-size: 11px; font-weight: 700; flex-shrink: 0; margin-top: 4px; }
.header__notifDias--ok     { color: #ff8c00; }
.header__notifDias--alerta { color: #ff4444; }
.header__notifEmpty { padding: 24px 16px; text-align: center; color: #666; font-size: 13px; }
.header__notifMsg { padding: 10px 16px; font-size: 12px; color: #7ecf7e; border-top: 1px solid #222; }
</style>

<header class="header">
    <div class="container-fluid">
        <div class="row header__row">

            <div class="col-6 header__logo">
                <button class="header__hamburger" id="toggleSidebar">
                    <span></span><span></span><span></span>
                </button>
                <img src="<?= ADMIN_BASE_URL ?>/images/logo.png" alt="logo" />
            </div>

            <div class="col-6 header__user">

                <?php if (!empty($_SESSION['_impersonator'])): ?>
                <div class="header__impersonating">
                    Visualizando como <strong><?= htmlspecialchars($_SESSION['usuario']['nome_completo']) ?></strong>
                    <a href="<?= ADMIN_BASE_URL ?>/services/impersonate_stop.php">Voltar para admin</a>
                </div>
                <?php endif; ?>

                <!-- Sino de notificações -->
                <div class="header__notif" id="headerNotif">
                    <button class="header__notifBtn" id="notifBtn" title="Notificações">
                        🔔
                        <span class="header__notifBadge" id="notifBadge" style="display:none;">0</span>
                    </button>
                    <div class="header__notifDropdown" id="notifDropdown">
                        <div class="header__notifSection" id="notifErrosSection" style="display:none;">
                            <div class="header__notifHead">
                                <strong>⚠️ Erros de pagamento</strong>
                                <a class="header__notifEnviar" href="<?= BASE_URL ?>/admin/erros-pagamento">Ver todos</a>
                            </div>
                            <div class="header__notifList" id="notifErrosList"></div>
                        </div>

                        <div class="header__notifSection" id="notifUniformesSection" style="display:none;">
                            <div class="header__notifHead">
                                <strong>Novos pedidos de uniforme</strong>
                                <a class="header__notifEnviar" href="<?= BASE_URL ?>/admin/uniformes">Ver todos</a>
                            </div>
                            <div class="header__notifList" id="notifUniformesList"></div>
                        </div>

                        <div class="header__notifHead">
                            <strong>Alunos com atraso</strong>
                            <button class="header__notifEnviar" id="notifEnviarBtn">Enviar notificações</button>
                        </div>
                        <div class="header__notifList" id="notifList">
                            <div class="header__notifEmpty">Carregando...</div>
                        </div>
                        <div class="header__notifMsg" id="notifEnviarMsg" style="display:none;"></div>
                    </div>
                </div>

                <span class="header__user__name">
                    <?= htmlspecialchars($_SESSION['usuario']['nome_completo']) ?>
                </span>
                <a href="<?= BASE_URL ?>/admin/logout" class="btn btn--gray header__user__logout">
                    Sair
                </a>
            </div>

        </div>
    </div>
</header>

<script>
(function () {
    var ADMIN_BASE_URL = "<?= ADMIN_BASE_URL ?>";
    var btn     = document.getElementById('notifBtn');
    var dropdown = document.getElementById('notifDropdown');
    var badge   = document.getElementById('notifBadge');
    var list    = document.getElementById('notifList');
    var enviarBtn = document.getElementById('notifEnviarBtn');
    var enviarMsg = document.getElementById('notifEnviarMsg');

    // Toggle dropdown
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('is-open');
    });
    document.addEventListener('click', function (e) {
        if (!document.getElementById('headerNotif').contains(e.target)) {
            dropdown.classList.remove('is-open');
        }
    });

    // Carrega notificações
    function carregarNotificacoes() {
        fetch(ADMIN_BASE_URL + '/services/get_notificacoes_admin.php', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;

            renderUniformes(data.uniformes || []);
            renderErros(data.erros_pagamento || []);

            // Badge: atrasos 25+ dias não notificados + pedidos de uniforme + erros de pagamento
            var alertas = data.total_alerta + (data.total_uniformes || 0) + (data.total_erros || 0);
            if (alertas > 0) {
                badge.textContent   = alertas > 9 ? '9+' : alertas;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }

            // Renderiza lista
            if (data.alunos.length === 0) {
                list.innerHTML = '<div class="header__notifEmpty">Nenhum aluno em atraso.</div>';
                return;
            }

            var html = '';
            data.alunos.forEach(function (a) {
                var isAlerta = a.alerta;
                var dotClass  = isAlerta ? 'header__notifDot--alerta' : 'header__notifDot--ok';
                var diasClass = isAlerta ? 'header__notifDias--alerta' : 'header__notifDias--ok';
                var diasText  = a.dias_atraso + 'd atraso';
                var aviso     = isAlerta ? ' ⚠ ' + (30 - a.dias_atraso) + 'd p/ bloquear' : '';
                var notifIcon = a.notificado ? ' ✓' : '';
                html += '<div class="header__notifItem">'
                      + '<span class="header__notifDot ' + dotClass + '"></span>'
                      + '<div class="header__notifInfo">'
                      + '<span class="header__notifNome">' + a.nome + notifIcon + '</span>'
                      + '<span class="header__notifMeta">' + a.email + ' — ' + a.ref_label + '</span>'
                      + '</div>'
                      + '<span class="header__notifDias ' + diasClass + '">' + diasText + (aviso ? '<br>' + aviso : '') + '</span>'
                      + '</div>';
            });
            list.innerHTML = html;
        })
        .catch(function () {
            list.innerHTML = '<div class="header__notifEmpty">Erro ao carregar.</div>';
        });
    }

    // Falhas de pagamento que o admin ainda não viu — vão no topo do dropdown porque
    // é o item mais acionável: tem um aluno travado esperando ajuda do outro lado.
    function renderErros(lista) {
        var secao = document.getElementById('notifErrosSection');
        var box   = document.getElementById('notifErrosList');
        if (!secao || !box) return;

        if (!lista.length) {
            secao.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        var ctxLabel = { mensalidade: 'Mensalidade', uniforme: 'Uniforme', batebola: 'Bate Bola', outro: 'Cobrança' };

        var html = '';
        lista.forEach(function (e) {
            var valor = (e.valor !== null && e.valor !== undefined)
                ? ' · R$ ' + Number(e.valor).toFixed(2).replace('.', ',') : '';

            html += '<a class="header__notifItem header__notifItem--link" href="'
                  + ADMIN_BASE_URL + '/erros-pagamento">'
                  + '<span class="header__notifDot header__notifDot--alerta"></span>'
                  + '<div class="header__notifInfo">'
                  + '<span class="header__notifNome">' + e.aluno_nome + '</span>'
                  + '<span class="header__notifMeta">' + (ctxLabel[e.contexto] || e.contexto) + valor + '</span>'
                  + '<span class="header__notifMeta">' + e.motivo + '</span>'
                  + '</div>'
                  + '<span class="header__notifDias header__notifDias--alerta">' + e.criado_em + '</span>'
                  + '</a>';
        });

        box.innerHTML = html;
        secao.style.display = '';
    }

    // Pedidos de uniforme pagos que ainda não foram vistos
    function renderUniformes(lista) {
        var secao = document.getElementById('notifUniformesSection');
        var box   = document.getElementById('notifUniformesList');
        if (!secao || !box) return;

        if (!lista.length) {
            secao.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        var html = '';
        lista.forEach(function (u) {
            html += '<a class="header__notifItem header__notifItem--link" href="'
                  + ADMIN_BASE_URL + '/uniformes">'
                  + '<span class="header__notifDot header__notifDot--uniforme"></span>'
                  + '<div class="header__notifInfo">'
                  + '<span class="header__notifNome">' + u.aluno_nome + '</span>'
                  + '<span class="header__notifMeta">'
                  +   u.genero_label + ' · ' + u.modelo_label
                  +   ' · camisa ' + u.tamanho_camisa + ' / shorts ' + u.tamanho_shorts
                  +   ' — ' + u.nome_camisa + ' #' + u.numero
                  + '</span>'
                  + '<span class="header__notifMeta">' + u.turma_nome + '</span>'
                  + '</div>'
                  + '<span class="header__notifDias header__notifDias--uniforme">' + u.pago_em + '</span>'
                  + '</a>';
        });

        box.innerHTML = html;
        secao.style.display = '';
    }

    carregarNotificacoes();

    // Enviar notificações
    enviarBtn.addEventListener('click', function () {
        enviarBtn.disabled  = true;
        enviarBtn.textContent = '...';
        enviarMsg.style.display = 'none';

        fetch(ADMIN_BASE_URL + '/services/enviar_notificacoes_atraso.php', {
            method: 'POST',
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            enviarMsg.textContent   = data.mensagem || (data.enviadas + ' enviada(s).');
            enviarMsg.style.color   = data.erros > 0 ? '#cf7e7e' : '#7ecf7e';
            enviarMsg.style.display = '';
            carregarNotificacoes();
        })
        .catch(function () {
            enviarMsg.textContent   = 'Erro ao enviar.';
            enviarMsg.style.color   = '#cf7e7e';
            enviarMsg.style.display = '';
        })
        .finally(function () {
            enviarBtn.disabled    = false;
            enviarBtn.textContent = 'Enviar notificações';
        });
    });
}());
</script>
