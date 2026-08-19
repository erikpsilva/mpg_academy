/**
 * admin/pagamentos-uniformes — dinheiro de uniforme, separado do Controle Financeiro.
 */

(function () {
    'use strict';

    var lista     = document.getElementById('pagUniformesLista');
    var seletor   = document.getElementById('filtroMes');
    var mesesFeitos = false;

    function moeda(v) {
        if (v === null || v === undefined) return '—';
        return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapar(txt) {
        var d = document.createElement('div');
        d.textContent = txt === null || txt === undefined ? '' : String(txt);
        return d.innerHTML;
    }

    /** '2026-08' → 'Ago/2026' */
    function rotuloMes(ym) {
        var meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
        var p = ym.split('-');
        return (meses[parseInt(p[1], 10) - 1] || p[1]) + '/' + p[0];
    }

    var STATUS_PEDIDO = {
        pendente:   'Pendente',
        enviado:    'Enviado',
        pronto:     'Pronto',
        finalizado: 'Finalizado',
        entregue:   'Entregue'
    };

    function linha(p) {
        var tamanhos = [];
        if (p.tamanho_camisa) tamanhos.push('camisa ' + escapar(p.tamanho_camisa));
        if (p.tamanho_shorts) tamanhos.push((p.genero === 'feminino' ? 'bermuda ' : 'calção ') + escapar(p.tamanho_shorts));

        var badgeManual = p.manual
            ? '<span class="pagUniformes__tag pagUniformes__tag--manual" title="Registrado pelo admin, pago por fora">Externo</span>'
            : '';

        return ''
          + '<article class="pagUniformes__item">'
          +   '<div class="pagUniformes__itemMain">'
          +     '<h3>' + escapar(p.aluno_nome) + ' ' + badgeManual + '</h3>'
          +     '<p class="pagUniformes__meta">'
          +       (p.turma ? escapar(p.turma) + ' &middot; ' : '')
          +       (p.numero !== null ? 'nº ' + p.numero + ' &middot; ' : '')
          +       (p.nome_camisa ? '“' + escapar(p.nome_camisa) + '” &middot; ' : '')
          +       (tamanhos.length ? tamanhos.join(', ') : '')
          +     '</p>'
          +     '<p class="pagUniformes__meta pagUniformes__meta--dim">'
          +       escapar(p.forma)
          +       (p.pago_em ? ' &middot; ' + escapar(p.pago_em) : '')
          +       (p.registrado_por ? ' &middot; por ' + escapar(p.registrado_por) : '')
          +       (p.mp_payment_id ? ' &middot; MP ' + escapar(p.mp_payment_id) : '')
          +     '</p>'
          +   '</div>'
          +   '<div class="pagUniformes__itemValores">'
          +     '<span class="pagUniformes__valor">' + moeda(p.valor) + '</span>'
          +     (p.taxa !== null && p.taxa > 0
                    ? '<span class="pagUniformes__taxa">taxa ' + moeda(p.taxa) + '</span>'
                      + '<span class="pagUniformes__liquido">líquido ' + moeda(p.liquido) + '</span>'
                    : '')
          +     '<span class="pagUniformes__status">' + (STATUS_PEDIDO[p.status_pedido] || escapar(p.status_pedido)) + '</span>'
          +   '</div>'
          + '</article>';
    }

    function carregar() {
        lista.innerHTML = '<p class="pagUniformes__vazio">Carregando...</p>';

        var url = ADMIN_BASE_URL + '/services/get_pagamentos_uniforme.php';
        if (seletor.value) url += '?mes=' + encodeURIComponent(seletor.value);

        fetch(url, { credentials: 'same-origin' })
            // Parse defensivo: se o servidor responder HTML (fatal do PHP), r.json() estoura
            // e o motivo real some. Melhor mostrar o status e jogar o corpo no console.
            .then(function (r) {
                return r.text().then(function (texto) {
                    try {
                        return JSON.parse(texto);
                    } catch (e) {
                        console.error('Resposta não-JSON de get_pagamentos_uniforme:', texto.slice(0, 600));
                        return {
                            success: false,
                            message: 'O servidor respondeu com erro ' + r.status + '. Veja o console para o detalhe.'
                        };
                    }
                });
            })
            .then(function (data) {
                if (!data.success) {
                    lista.innerHTML = '<p class="pagUniformes__vazio">' + escapar(data.message || 'Não foi possível carregar.') + '</p>';
                    return;
                }

                document.getElementById('totalQtd').textContent     = data.total;
                document.getElementById('totalBruto').textContent   = moeda(data.total_bruto);
                document.getElementById('totalTaxa').textContent    = moeda(data.total_taxa);
                document.getElementById('totalLiquido').textContent = moeda(data.total_liquido);

                // Só na primeira carga: recarregar as opções a cada filtro apagaria a escolha.
                if (!mesesFeitos) {
                    (data.meses || []).forEach(function (m) {
                        var o = document.createElement('option');
                        o.value = m;
                        o.textContent = rotuloMes(m);
                        seletor.appendChild(o);
                    });
                    mesesFeitos = true;
                }

                if (!data.pagamentos.length) {
                    lista.innerHTML = '<p class="pagUniformes__vazio">Nenhum uniforme pago nesse período.</p>';
                    return;
                }

                lista.innerHTML = data.pagamentos.map(linha).join('');
            })
            .catch(function () {
                lista.innerHTML = '<p class="pagUniformes__vazio">Erro ao carregar os pagamentos.</p>';
            });
    }

    seletor.addEventListener('change', carregar);
    carregar();
}());
