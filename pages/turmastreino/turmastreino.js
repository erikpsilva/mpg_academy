
const DIAS = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
const NIVEL_LABEL = { iniciante: 'Iniciante', intermediario: 'Intermediário', avancado: 'Avançado' };
const IMGS = ['imgTumra01.png', 'imgTumra02.png', 'imgTumra03.png'];
const WA = 'https://wa.me/5511972330097';

const fmt = (n) => parseFloat(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const esc = (s) => $('<span>').text(s || '').html();

let todasTurmas = [];
let filtroAtivo = 'todos';

const cardHtml = (t, idx) => {
    const featured  = t.promo_valor !== null && t.promo_meses !== null;
    const img       = IMGS[idx % IMGS.length];
    const nivel     = NIVEL_LABEL[t.nivel] || t.nivel;
    const generoMap = { masculino: 'Masculino', feminino: 'Feminino', misto: 'Misto' };
    const generoLabel = generoMap[t.genero] || t.genero;

    const schedule = (t.horarios || []).map(h =>
        '<div>' +
            '<span>' + DIAS[h.dia_semana] + '</span>' +
            '<strong>' + h.hora_inicio.slice(0, 5) + ' – ' + h.hora_fim.slice(0, 5) + '</strong>' +
        '</div>'
    ).join('');

    const price = featured
        ? '<del>R$ ' + fmt(t.valor_mensalidade) + '</del>' +
          '<strong>R$ ' + fmt(t.promo_valor) + '</strong>' +
          '<span>por mês nos ' + t.promo_meses + ' primeiros meses</span>' +
          '<b>Promoção válida por ' + t.promo_meses + ' ' + (t.promo_meses === 1 ? 'mês' : 'meses') + '</b>'
        : (t.valor_mensalidade !== null
            ? '<strong>R$ ' + fmt(t.valor_mensalidade) + '</strong><span>por mês</span>'
            : '<span>Consulte valores</span>');

    return '<article class="turmaCard' + (featured ? ' turmaCard--featured' : '') + '" data-nivel="' + t.nivel + '">' +
        '<img src="' + BASE_URL + '/images/turmasvalores/' + img + '" alt="' + esc(t.nome) + '">' +
        '<div class="turmaCard__content">' +
            '<div class="turmaCard__top">' +
                '<div class="turmaCard__tags">' +
                    '<span class="turmaCard__level">' + nivel + '</span>' +
                    '<span class="turmaCard__gender turmaCard__gender--' + t.genero + '">' + generoLabel + '</span>' +
                '</div>' +
                (featured ? '<span class="turmaCard__promo">Promoção <i class="icon-thumbtacks" aria-hidden="true"></i></span>' : '') +
            '</div>' +
            '<h2>' + esc(t.nome) + '</h2>' +
            '<p>' + esc(t.quadra_nome) + '</p>' +
            (t.quadra_endereco ? '<div class="turmaCard__address"><i class="icon-rua" aria-hidden="true"></i><span>' + esc(t.quadra_endereco) + '</span></div>' : '') +
            (schedule ? '<div class="turmaCard__schedule"><i class="icon-calendar" aria-hidden="true"></i>' + schedule + '</div>' : '') +
            '<div class="turmaCard__price">' + price + '</div>' +
            '<div class="turmaCard__benefits">' +
                '<span><i class="icon-user" aria-hidden="true"></i> Turma para ' + nivel.toLowerCase() + 's</span>' +
                '<span><i class="icon-treinosfocados" aria-hidden="true"></i> Treinos dinâmicos</span>' +
                '<span><i class="icon-seguro" aria-hidden="true"></i> Ambiente seguro</span>' +
            '</div>' +
            '<a class="turmaCard__cta" href="' + WA + '?text=' + encodeURIComponent('Oi, eu quero saber sobre fazer minha aula experimental para a turma ' + t.nome) + '" target="_blank" rel="noopener">' +
                '<i class="icon-whatsapp" aria-hidden="true"></i> Agendar aula experimental' +
            '</a>' +
        '</div>' +
    '</article>';
};

const render = (nivel) => {
    const wrap   = $('#turmasValoresCards');
    const turmas = nivel === 'todos' ? todasTurmas : todasTurmas.filter(t => t.nivel === nivel);

    if (turmas.length === 0) {
        wrap.html('<p class="turmasValoresEmpty">Nenhuma turma disponível no momento.</p>');
        return;
    }

    wrap.html(turmas.map((t, i) => cardHtml(t, i)).join(''));
};

$(document).ready(() => {
    const wrap = $('#turmasValoresCards');
    wrap.html('<p class="turmasValoresLoading">Carregando turmas...</p>');

    $.get(BASE_URL + '/services/site/get_turmas.php', (res) => {
        if (!res.success || !res.turmas.length) {
            wrap.html('<p class="turmasValoresEmpty">Nenhuma turma disponível no momento.</p>');
            return;
        }
        todasTurmas = res.turmas;
        render(filtroAtivo);
    }, 'json').fail(() => {
        wrap.html('<p class="turmasValoresEmpty">Erro ao carregar turmas.</p>');
    });

    $(document).on('click', '.turmasValoresFilters button', function () {
        $('.turmasValoresFilters button').removeClass('is-active');
        $(this).addClass('is-active');
        filtroAtivo = $(this).data('nivel') || 'todos';
        render(filtroAtivo);
    });
});
