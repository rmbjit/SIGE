/**
 * SIGE SoftGenial v12.11.9.63 - Jornada Mobile + Tablet UX PRO
 * v12.11.9.70 - Hotfix deep smoke: botão Mais do bottom nav próprio de Alunos abre sidebar com ARIA sincronizado.
 * Melhorias progressivas: device classes, sidebar acessível, tabelas scrolláveis e estados tácteis.
 */
(function () {
    'use strict';

    var MAX_MOBILE = 760;
    var MAX_TABLET = 1100;
    var scheduled = false;

    function body() {
        return document.body;
    }

    function isApp() {
        return !!(body() && body().classList && body().classList.contains('sige-admin-app'));
    }

    function width() {
        return window.innerWidth || document.documentElement.clientWidth || 1200;
    }

    function viewport() {
        var w = width();
        if (w <= MAX_MOBILE) return 'mobile';
        if (w <= MAX_TABLET) return 'tablet';
        return 'desktop';
    }

    function each(list, fn) {
        Array.prototype.forEach.call(list || [], fn);
    }

    function closest(el, selector) {
        return el && el.closest ? el.closest(selector) : null;
    }

    function setViewportClass() {
        if (!isApp()) return;
        var b = body();
        var v = viewport();
        b.classList.toggle('sige-ux-mobile', v === 'mobile');
        b.classList.toggle('sige-ux-tablet', v === 'tablet');
        b.classList.toggle('sige-ux-desktop', v === 'desktop');
        b.setAttribute('data-sige-ux-viewport', v);
    }

    function shouldSkipTable(table) {
        if (!table || table.dataset.sgNoUx === '1') return true;
        if (closest(table, '.sg-mobile-table-scroll,.sige-no-mobile-wrap,.sige-print-area,.sige-recibo,.sige-document-preview,.wp-editor-container,.tox,.mce-container')) return true;
        if (closest(table, 'template')) return true;
        return false;
    }

    function updateTableHint(wrapper) {
        if (!wrapper) return;
        window.requestAnimationFrame(function () {
            var overflow = wrapper.scrollWidth > wrapper.clientWidth + 8;
            wrapper.setAttribute('data-sg-hint', overflow ? '1' : '0');
            if (!wrapper.getAttribute('data-sg-scroll-hint')) {
                wrapper.setAttribute('data-sg-scroll-hint', 'Deslize para ver mais ↔');
            }
        });
    }

    function wrapTable(table) {
        if (shouldSkipTable(table)) return;
        var parent = table.parentNode;
        if (!parent || parent.nodeType !== 1) return;

        var wrapper = document.createElement('div');
        wrapper.className = 'sg-mobile-table-scroll';
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', table.getAttribute('aria-label') || 'Tabela com deslocamento horizontal');
        wrapper.setAttribute('tabindex', '0');
        wrapper.setAttribute('data-sg-scroll-hint', 'Deslize para ver mais ↔');
        parent.insertBefore(wrapper, table);
        wrapper.appendChild(table);
        table.dataset.sgMobileWrapped = '1';
        table.setAttribute('data-sg-mobile-wrapped', '1');
        updateTableHint(wrapper);
    }

    function normalizeExistingTableWrapper(wrapper) {
        if (!wrapper || wrapper.dataset.sgUxReady === '1') {
            if (wrapper) updateTableHint(wrapper);
            return;
        }
        wrapper.dataset.sgUxReady = '1';
        if (!wrapper.getAttribute('role')) wrapper.setAttribute('role', 'region');
        if (!wrapper.getAttribute('aria-label')) wrapper.setAttribute('aria-label', 'Tabela com deslocamento horizontal');
        if (!wrapper.getAttribute('tabindex')) wrapper.setAttribute('tabindex', '0');
        if (!wrapper.getAttribute('data-sg-scroll-hint')) wrapper.setAttribute('data-sg-scroll-hint', 'Deslize para ver mais ↔');
        updateTableHint(wrapper);
    }

    function enhanceTables() {
        if (!isApp() || width() > MAX_TABLET) return;
        var page = document.querySelector('.sg-app-page');
        if (!page) return;

        each(page.querySelectorAll('.sg-mobile-table-scroll'), normalizeExistingTableWrapper);
        each(page.querySelectorAll('table'), function (table) {
            if (table.dataset.sgMobileWrapped === '1') {
                normalizeExistingTableWrapper(closest(table, '.sg-mobile-table-scroll'));
                return;
            }
            wrapTable(table);
        });
    }

    function enhanceToolbarsAndForms() {
        if (!isApp() || width() > MAX_TABLET) return;
        var page = document.querySelector('.sg-app-page');
        if (!page) return;

        each(page.querySelectorAll('.sige-toolbar,.sg-toolbar,form.sige-toolbar,.sige-filters,.sg-filters,.tablenav'), function (el) {
            if (el) el.classList.add('sg-mobile-compliance-toolbar');
        });

        each(page.querySelectorAll('button,a.button,a.sige-btn,a.sg-btn,button.sige-btn,button.sg-btn'), function (el) {
            if (!el || el.dataset.sgUxTouchReady === '1') return;
            el.dataset.sgUxTouchReady = '1';
            var text = (el.textContent || '').replace(/\s+/g, ' ').trim();
            var title = el.getAttribute('title') || el.getAttribute('aria-label') || '';
            if (!text && title) {
                el.classList.add('sg-ux-icon-only');
                el.setAttribute('aria-label', title);
            }
        });
    }

    function sidebarState(open) {
        var side = document.getElementById('sige-sidebar');
        var overlay = document.getElementById('sige-overlay');
        if (!side) return;

        if (viewport() === 'desktop') {
            side.classList.remove('open');
            side.setAttribute('aria-hidden', 'false');
            if (!side.getAttribute('aria-label')) side.setAttribute('aria-label', 'Menu principal');
            if (overlay) overlay.classList.remove('show');
            if (body()) body().classList.remove('sg-app-menu-open');
            each(document.querySelectorAll('#sige-hamburger,.sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"],.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]'), function (btn) {
                btn.setAttribute('aria-expanded', 'false');
                btn.setAttribute('aria-controls', 'sige-sidebar');
            });
            return;
        }

        if (typeof open !== 'boolean') open = side.classList.contains('open');

        side.classList.toggle('open', open);
        side.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (!side.getAttribute('aria-label')) side.setAttribute('aria-label', 'Menu principal');
        if (overlay) overlay.classList.toggle('show', open);
        if (body()) body().classList.toggle('sg-app-menu-open', open);

        each(document.querySelectorAll('#sige-hamburger,.sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"],.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]'), function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('aria-controls', 'sige-sidebar');
        });

        if (open && viewport() !== 'desktop') {
            window.setTimeout(function () {
                var active = side.querySelector('.sige-menu-item.active') || side.querySelector('.sige-menu-item[href]');
                if (active && document.activeElement && !side.contains(document.activeElement)) {
                    try { active.focus({ preventScroll: true }); } catch (e) { active.focus(); }
                }
            }, 80);
        }
    }

    function syncSidebarA11y() {
        var side = document.getElementById('sige-sidebar');
        if (!side) return;
        sidebarState(side.classList.contains('open'));
    }

    function bindSidebar() {
        if (!isApp() || document.documentElement.dataset.sgUxSidebarBound === '1') return;
        document.documentElement.dataset.sgUxSidebarBound = '1';

        document.addEventListener('click', function (ev) {
            var target = ev.target;
            if (!target || !target.closest) return;

            if (target.closest('#sige-overlay')) {
                sidebarState(false);
                return;
            }

            var sidebarTrigger = target.closest('#sige-hamburger,.sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"],.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]');
            if (sidebarTrigger) {
                if (sidebarTrigger.matches('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]')) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    var side = document.getElementById('sige-sidebar');
                    sidebarState(!(side && side.classList.contains('open')));
                    return;
                }
                window.setTimeout(syncSidebarA11y, 0);
                return;
            }

            if (viewport() !== 'desktop' && target.closest('#sige-sidebar .sige-menu-item[href]')) {
                window.setTimeout(function () { sidebarState(false); }, 80);
            }
        }, true);

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape') return;
            var side = document.getElementById('sige-sidebar');
            if (side && side.classList.contains('open')) {
                sidebarState(false);
                var hamburger = document.getElementById('sige-hamburger');
                if (hamburger) hamburger.focus();
            }
        }, true);
    }

    function enhanceModals() {
        if (!isApp() || width() > MAX_TABLET) return;
        each(document.querySelectorAll('.sige-modal,.sg-modal,.sige-modal-content,.sg-modal-content,.modal-content,.sige-lanc-modal,.sg-paypro-modal,.sg-plans-modal-card,.sg-inspro-modal-box,.sg-generator-modal-card,.sg-expense-modal,.sg-extracts-modal-box,.wppc-modal'), function (modal) {
            if (!modal || modal.dataset.sgUxModalReady === '1') return;
            modal.dataset.sgUxModalReady = '1';
            if (!modal.getAttribute('role') && /modal|dialog/i.test(modal.className || '')) {
                modal.setAttribute('role', 'dialog');
            }
        });
    }

    function enhanceAll() {
        if (!isApp()) return;
        setViewportClass();
        bindSidebar();
        syncSidebarA11y();
        enhanceTables();
        enhanceToolbarsAndForms();
        enhanceModals();
    }

    function scheduleEnhance() {
        if (scheduled) return;
        scheduled = true;
        window.setTimeout(function () {
            scheduled = false;
            enhanceAll();
        }, 80);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceAll);
    } else {
        enhanceAll();
    }

    window.addEventListener('resize', scheduleEnhance);
    window.addEventListener('orientationchange', function () { window.setTimeout(enhanceAll, 180); });

    if ('MutationObserver' in window) {
        document.addEventListener('DOMContentLoaded', function () {
            var page = document.querySelector('.sg-app-page');
            if (!page) return;
            var observer = new MutationObserver(function (mutations) {
                var shouldRun = false;
                for (var i = 0; i < mutations.length; i++) {
                    if (mutations[i].addedNodes && mutations[i].addedNodes.length) {
                        shouldRun = true;
                        break;
                    }
                }
                if (shouldRun) scheduleEnhance();
            });
            observer.observe(page, { childList: true, subtree: true });
        });
    }
})();
