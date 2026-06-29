(function(){
    'use strict';
    var payload = window.SIGEProfileDashboardV121900 || {};
    if (!payload || payload.enabled !== true || payload.view !== 'dashboard') return;

    function text(value){ return typeof value === 'string' ? value : ''; }
    function appendText(node, value){ node.appendChild(document.createTextNode(text(value))); }
    function whenReady(fn){
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, {once:true});
        else fn();
    }
    function make(tag, className){
        var node = document.createElement(tag);
        if (className) node.className = className;
        return node;
    }

    whenReady(function(){
        var shell = document.querySelector('.sg-dashboard-v2 .sg-dash-shell');
        if (!shell || shell.querySelector('[data-sg-profile-dashboard-v121900]')) return;
        try {
            if (payload.dismissKey && window.sessionStorage && sessionStorage.getItem(payload.dismissKey) === '1') return;
        } catch(e) {}

        var section = make('section', 'sg-profile-dashboard-v121900');
        section.setAttribute('data-sg-profile-dashboard-v121900', '1');
        section.setAttribute('data-tone', text((payload.profile && payload.profile.tone) || 'gestao'));
        section.setAttribute('aria-label', 'Painel executivo por perfil');

        var panel = make('div', 'sg-pd-panel');
        var kicker = make('span', 'sg-pd-kicker');
        appendText(kicker, 'Orientação do painel');
        panel.appendChild(kicker);

        var title = make('h2', 'sg-pd-title');
        appendText(title, (payload.profile && payload.profile.label ? payload.profile.label + ': ' : '') + (payload.headline || 'Veja primeiro o que precisa de atenção.'));
        panel.appendChild(title);

        var summary = make('p', 'sg-pd-summary');
        appendText(summary, payload.summary || 'Este painel usa as permissões reais do utilizador e não altera dados nem regras.');
        panel.appendChild(summary);

        var meta = make('div', 'sg-pd-meta');
        var metaItems = [];
        if (payload.profile && payload.profile.focus) metaItems.push(payload.profile.focus);
        if (payload.meta && payload.meta.actionsLabel) metaItems.push(payload.meta.actionsLabel);
        if (payload.meta && payload.meta.areasLabel) metaItems.push(payload.meta.areasLabel);
        metaItems.slice(0,3).forEach(function(item){
            var pill = make('span', 'sg-pd-pill');
            appendText(pill, item);
            meta.appendChild(pill);
        });
        panel.appendChild(meta);

        var main = make('div', 'sg-pd-main');
        var cards = make('div', 'sg-pd-cards');
        (Array.isArray(payload.cards) ? payload.cards.slice(0,3) : []).forEach(function(card){
            var el = make('article', 'sg-pd-card');
            var label = make('span');
            appendText(label, card.label || 'Indicador');
            var value = make('strong');
            appendText(value, card.value || 'Acompanhar');
            var hint = make('small');
            appendText(hint, card.hint || 'Validar antes de executar.');
            el.appendChild(label);
            el.appendChild(value);
            el.appendChild(hint);
            cards.appendChild(el);
        });
        main.appendChild(cards);

        var footer = make('div', 'sg-pd-footer');
        var actions = make('div', 'sg-pd-actions');
        (Array.isArray(payload.actions) ? payload.actions.slice(0,4) : []).forEach(function(action){
            if (!action || !action.href) return;
            var a = make('a', 'sg-pd-action');
            a.href = action.href;
            a.setAttribute('aria-label', text(action.label || 'Abrir') + (action.hint ? ' - ' + text(action.hint) : ''));
            appendText(a, action.label || 'Abrir');
            actions.appendChild(a);
        });
        footer.appendChild(actions);

        var groups = make('div', 'sg-pd-groups');
        (Array.isArray(payload.groups) ? payload.groups.slice(0,3) : []).forEach(function(group){
            var item = make('span', 'sg-pd-group' + (group.primary ? ' is-primary' : ''));
            item.title = text(group.focus || group.label || '');
            appendText(item, group.label || 'Área');
            groups.appendChild(item);
        });
        if (groups.childNodes.length) footer.appendChild(groups);

        var close = make('button', 'sg-pd-close');
        close.type = 'button';
        close.setAttribute('aria-label', 'Ocultar orientação deste painel nesta sessão');
        appendText(close, '×');
        close.addEventListener('click', function(){
            try { if (payload.dismissKey && window.sessionStorage) sessionStorage.setItem(payload.dismissKey, '1'); } catch(e) {}
            section.remove();
        });
        footer.appendChild(close);
        main.appendChild(footer);

        section.appendChild(panel);
        section.appendChild(main);

        var hero = shell.querySelector('.sg-dash-hero');
        if (hero && hero.parentNode === shell) shell.insertBefore(section, hero.nextSibling);
        else shell.insertBefore(section, shell.firstChild);
    });
})();
