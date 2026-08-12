
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
    $('#toggleSidebar').click(function () {
        $('.sidebar').toggleClass('open');
        $('.sidebar__overlay').toggleClass('show');
    });

    $('#sidebarOverlay, #closeSidebar').click(function () {
        $('.sidebar').removeClass('open');
        $('.sidebar__overlay').removeClass('show');
    });
};
