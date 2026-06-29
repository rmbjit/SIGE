(function () {
    'use strict';

    var mqDesktop = window.matchMedia ? window.matchMedia('(min-width: 1101px)') : null;

    function shell() {
        return document.getElementById('sige-layout');
    }

    function sidebar() {
        return document.getElementById('sige-sidebar');
    }

    function overlay() {
        return document.getElementById('sige-overlay');
    }

    function menuButtons() {
        return document.querySelectorAll('#sige-hamburger, .sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"], .sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]');
    }

    function setExpanded(open) {
        menuButtons().forEach(function (btn) {
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function sync() {
        var s = sidebar();
        var o = overlay();
        var open = !!(s && s.classList.contains('open'));
        document.body.classList.toggle('sg-app-menu-open', open);
        if (o) {
            o.classList.toggle('show', open);
        }
        setExpanded(open);
    }

    function close() {
        var s = sidebar();
        var o = overlay();
        if (s) {
            s.classList.remove('open');
        }
        if (o) {
            o.classList.remove('show');
        }
        document.body.classList.remove('sg-app-menu-open');
        setExpanded(false);
    }

    function closeOnDesktop() {
        if (mqDesktop && mqDesktop.matches) {
            close();
        }
    }

    function init() {
        var layout = shell();
        if (!layout || !layout.classList.contains('sg-product-pro-shell')) {
            return;
        }

        document.body.setAttribute('data-sige-shell-stability', '12.15.6');
        sync();
        closeOnDesktop();

        document.addEventListener('click', function (ev) {
            var target = ev.target;
            if (!target || !target.closest) {
                return;
            }
            if (target.closest('#sige-overlay')) {
                close();
                return;
            }
            if (target.closest('#sige-sidebar .sige-menu-item')) {
                close();
                return;
            }
            if (target.closest('#sige-hamburger, .sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"], .sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]')) {
                window.setTimeout(sync, 0);
            }
        }, true);

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                close();
            }
        }, true);

        window.addEventListener('resize', function () {
            window.setTimeout(function () {
                closeOnDesktop();
                sync();
            }, 80);
        }, { passive: true });

        if (mqDesktop && mqDesktop.addEventListener) {
            mqDesktop.addEventListener('change', closeOnDesktop);
        } else if (mqDesktop && mqDesktop.addListener) {
            mqDesktop.addListener(closeOnDesktop);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
