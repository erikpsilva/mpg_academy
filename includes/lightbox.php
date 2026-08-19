<?php
/**
 * Lightbox de foto — usado nas telas do Bate Bola, onde as fotos aparecem como miniaturas
 * pequenas e não dava pra reconhecer quem é quem.
 *
 * Compartilhado entre admin e site, por isso é autocontido: não depende de jQuery (o site
 * não carrega em todas as páginas) nem de biblioteca externa, e o CSS vive em
 * includes/lightbox.less, importado pelos dois entrypoints.
 *
 * Uso: inclua este arquivo uma vez na página e marque as imagens com data-lightbox.
 *   <img src="..." alt="..." data-lightbox>
 *
 * O clique é capturado por delegação no document, então funciona também nas listas que o
 * JavaScript monta depois (ex.: a tabela de jogadores do admin).
 */
?>
<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Foto ampliada" hidden>
    <button type="button" class="lightbox__close" id="lightboxClose" aria-label="Fechar">&times;</button>
    <figure class="lightbox__figure">
        <img class="lightbox__img" id="lightboxImg" src="" alt="">
        <figcaption class="lightbox__legenda" id="lightboxLegenda"></figcaption>
    </figure>
</div>

<script>
(function () {
    var caixa    = document.getElementById('lightbox');
    var imagem   = document.getElementById('lightboxImg');
    var legenda  = document.getElementById('lightboxLegenda');
    var fechar   = document.getElementById('lightboxClose');
    if (!caixa) return;

    // Guarda quem tinha o foco pra devolver no fechamento — sem isso, quem navega por
    // teclado volta pro topo da página toda vez que fecha uma foto.
    var focoAnterior = null;

    function abrir(img) {
        focoAnterior = document.activeElement;

        imagem.src = img.getAttribute('src');
        imagem.alt = img.getAttribute('alt') || '';

        // A legenda sai do alt (que já traz o nome do jogador nas telas do Bate Bola).
        legenda.textContent = img.getAttribute('data-legenda') || img.getAttribute('alt') || '';
        legenda.style.display = legenda.textContent ? '' : 'none';

        caixa.hidden = false;
        document.body.classList.add('lightbox-aberto');
        fechar.focus();
    }

    function fecharLightbox() {
        caixa.hidden = true;
        imagem.src = '';
        document.body.classList.remove('lightbox-aberto');
        if (focoAnterior && focoAnterior.focus) focoAnterior.focus();
    }

    // Delegação: pega inclusive as imagens que o JS insere depois de a página carregar.
    document.addEventListener('click', function (e) {
        var alvo = e.target.closest ? e.target.closest('[data-lightbox]') : null;
        if (!alvo) return;

        var img = alvo.tagName === 'IMG' ? alvo : alvo.querySelector('img');
        if (!img || !img.getAttribute('src')) return;

        e.preventDefault();
        abrir(img);
    });

    fechar.addEventListener('click', fecharLightbox);

    // Clicar no fundo fecha; clicar na própria foto, não — senão fecha sem querer ao
    // tentar olhar de perto.
    caixa.addEventListener('click', function (e) {
        if (e.target === caixa || e.target.classList.contains('lightbox__figure')) fecharLightbox();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !caixa.hidden) fecharLightbox();
    });
}());
</script>
