/* SIGE SoftGenial v12.15.11 - Alunos stability rollback + safe modal layering. */
(function () {
    'use strict';

    function isAlunosView() {
        return document.body && document.body.classList.contains('sige-view-alunos_lista');
    }

    if (!isAlunosView()) return;

    document.body.setAttribute('data-sige-alunos-design-pro', '12.15.11');

    function getMenus() {
        return Array.prototype.slice.call(document.querySelectorAll('.sige-alunos-page details.sige-card-actions'));
    }

    function getCard(menu) {
        return menu && menu.closest ? menu.closest('.sige-aluno-card') : null;
    }

    function syncMenu(menu) {
        if (!menu) return;
        var summary = menu.querySelector('summary');
        var card = getCard(menu);
        if (summary) summary.setAttribute('aria-expanded', menu.open ? 'true' : 'false');
        if (card) card.classList.toggle('sige-card-actions-open', !!menu.open);
        var anyOpen = getMenus().some(function (candidate) { return candidate.open; });
        document.body.classList.toggle('sige-alunos-actions-open', anyOpen);
    }

    function closeOtherMenus(current) {
        getMenus().forEach(function (menu) {
            if (menu !== current && menu.open) {
                menu.open = false;
                syncMenu(menu);
            }
        });
    }

    document.addEventListener('toggle', function (ev) {
        var menu = ev.target && ev.target.closest ? ev.target.closest('details.sige-card-actions') : null;
        if (!menu) return;
        if (menu.open) closeOtherMenus(menu);
        syncMenu(menu);
    }, true);

    document.addEventListener('click', function (ev) {
        if (!ev.target || (ev.target.closest && ev.target.closest('details.sige-card-actions'))) return;
        getMenus().forEach(function (menu) {
            if (menu.open) {
                menu.open = false;
                syncMenu(menu);
            }
        });
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Escape') return;
        var closed = false;
        getMenus().forEach(function (menu) {
            if (menu.open) {
                menu.open = false;
                syncMenu(menu);
                closed = true;
            }
        });
        if (closed) ev.stopPropagation();
    });

    function syncFilters() {
        var toolbar = document.querySelector('.sige-alunos-page .sige-toolbar');
        var toggle = document.querySelector('.sige-mobile-filter-toggle');
        if (!toolbar || !toggle) return;
        toggle.setAttribute('aria-expanded', toolbar.classList.contains('sige-mobile-filters-open') ? 'true' : 'false');
        toggle.setAttribute('aria-controls', 'sige-alunos-filtros');
        if (!toolbar.id) toolbar.id = 'sige-alunos-filtros';
    }

    document.addEventListener('click', function (ev) {
        if (ev.target && ev.target.closest && ev.target.closest('.sige-mobile-filter-toggle')) {
            window.setTimeout(syncFilters, 0);
        }
    });

    getMenus().forEach(syncMenu);
    syncFilters();
})();
