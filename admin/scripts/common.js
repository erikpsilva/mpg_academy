
// inicia após carregar a pagina
$( document ).ready(function() {
    initSidebar();
    initResponsiveTables();
});

// Mantem no celular as mesmas informacoes exibidas nas tabelas do desktop.
// Os titulos das colunas viram rotulos dentro de cada bloco, inclusive em
// tabelas montadas depois por AJAX.
const initResponsiveTables = () => {
    const prepareTable = (table) => {
        if (!(table instanceof HTMLTableElement) || table.classList.contains('no-mobile-cards')) return;

        const headers = Array.from(table.querySelectorAll(':scope > thead > tr:last-child > th'))
            .map((header) => header.textContent.trim());

        if (!headers.length) return;

        table.classList.add('responsiveTable');
        table.querySelectorAll(':scope > tbody > tr').forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                if (cell.tagName !== 'TD') return;
                const label = headers[index] || '';
                if (label) cell.dataset.label = label;
                if (cell.hasAttribute('colspan')) cell.classList.add('responsiveTable__full');
            });
        });
    };

    const prepareAll = (root) => {
        if (root.matches && root.matches('table')) prepareTable(root);
        if (root.querySelectorAll) root.querySelectorAll('table').forEach(prepareTable);
    };

    prepareAll(document);
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) prepareAll(node);
        }));
    }).observe(document.body, { childList: true, subtree: true });
};

const initSidebar = () => {
    // Transforma os títulos já existentes em controles de accordion sem exigir
    // duplicação da estrutura em cada perfil de usuário.
    $('.sidebar__section').each(function (index) {
        const $section = $(this);
        const $items = $section
            .nextUntil('.sidebar__section, .sidebar__divider')
            .filter('.sidebar__item');

        if (!$items.length) return;

        const groupId = `sidebarGroup${index}`;
        const isCurrentGroup = $items.find('.sidebar__link--active').length > 0;
        const label = $section.text().trim();

        $items
            .attr('data-sidebar-group', groupId)
            .toggleClass('sidebar__item--open', isCurrentGroup);

        $section
            .toggleClass('sidebar__section--active', isCurrentGroup)
            .html(`
                <button type="button" class="sidebar__sectionButton"
                        aria-expanded="${isCurrentGroup ? 'true' : 'false'}"
                        data-sidebar-target="${groupId}">
                    <span>${label}</span>
                    <span class="sidebar__sectionArrow" aria-hidden="true"></span>
                </button>
            `);
    });

    $('.sidebar').on('click', '.sidebar__sectionButton', function () {
        const $button = $(this);
        const groupId = $button.data('sidebar-target');
        const willOpen = $button.attr('aria-expanded') !== 'true';

        $button.attr('aria-expanded', String(willOpen));
        $button.closest('.sidebar__section').toggleClass('sidebar__section--active', willOpen);
        $(`.sidebar__item[data-sidebar-group="${groupId}"]`).toggleClass('sidebar__item--open', willOpen);
    });

    $('#toggleSidebar').click(function () {
        $('.sidebar').toggleClass('open');
        $('.sidebar__overlay').toggleClass('show');
    });

    $('#sidebarOverlay, #closeSidebar').click(function () {
        $('.sidebar').removeClass('open');
        $('.sidebar__overlay').removeClass('show');
    });
};
