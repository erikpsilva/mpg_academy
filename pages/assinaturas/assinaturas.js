const signaturesPage = {
    init: function () {
        this.bindCopy();
    },

    bindCopy: function () {
        $('.signatureCard__copy').on('click', function () {
            const $button = $(this);
            const slug = $button.data('signature-target');
            const source = document.getElementById('source-' + slug);

            if (!source) {
                return;
            }

            const done = function () {
                const originalText = $button.text();

                $button.addClass('is-copied').text('Copiado');

                setTimeout(function () {
                    $button.removeClass('is-copied').text(originalText);
                }, 1800);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(source.value).then(done);
                return;
            }

            source.focus();
            source.select();
            document.execCommand('copy');
            done();
        });
    },
};

$(document).ready(function () {
    signaturesPage.init();
});
