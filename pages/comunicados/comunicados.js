$(document).ready(() => {
    const modal = $('.studentAnnouncementModal');

    function closeAnnouncementModal() {
        modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').removeClass('is-menu-open');
    }

    $('.studentAnnouncementOpen').on('click', function () {
        const button = $(this);
        const image  = button.data('image');
        const title  = button.data('title');
        const date   = button.data('date');
        const tag    = button.data('tag');

        // Suporta conteúdo HTML rico (data-html) ou texto simples (data-text)
        const htmlContent = button.attr('data-html');
        const textContent = button.data('text');

        modal.find('.studentAnnouncementModal__image').attr({ src: image, alt: title });
        modal.find('.studentAnnouncementModal__tag').text(tag);
        modal.find('h2').text(title);
        modal.find('time').text(date);

        const contentEl = modal.find('.studentAnnouncementModal__content');
        if (htmlContent !== undefined && htmlContent !== '') {
            contentEl.html(htmlContent);
            // Garante que links abram em nova aba com segurança
            contentEl.find('a').attr({ target: '_blank', rel: 'noopener noreferrer' });
        } else {
            contentEl.text(textContent || '');
        }

        modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').addClass('is-menu-open');
    });

    $('[data-announcement-close]').on('click', closeAnnouncementModal);

    $(document).on('keydown', (event) => {
        if (event.key === 'Escape') closeAnnouncementModal();
    });
});
