(function(){
    'use strict';
    var payload = window.SIGEOperationalWorkflowV121800 || {};
    if (!payload || payload.enabled !== true) return;

    function text(value){ return typeof value === 'string' ? value : ''; }
    function appendText(node, value){ node.appendChild(document.createTextNode(text(value))); }
    function whenReady(fn){
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, {once:true});
        else fn();
    }

    whenReady(function(){
        var page = document.querySelector('.sg-app-page');
        if (!page || page.querySelector('[data-sg-operational-workflow-v121800]')) return;
        try {
            if (payload.dismissKey && window.sessionStorage && sessionStorage.getItem(payload.dismissKey) === '1') return;
        } catch(e) {}

        var card = document.createElement('section');
        card.className = 'sg-operational-workflow-v121800';
        card.setAttribute('data-sg-operational-workflow-v121800', '1');
        card.setAttribute('data-view', text(payload.view));
        card.setAttribute('data-tone', text(payload.tone || 'operacional'));
        card.setAttribute('aria-label', 'Orientação de fluxo operacional');

        var copy = document.createElement('div');
        copy.className = 'sg-ow-copy';

        var kicker = document.createElement('span');
        kicker.className = 'sg-ow-kicker';
        appendText(kicker, 'Fluxo seguro');
        copy.appendChild(kicker);

        var title = document.createElement('h2');
        title.className = 'sg-ow-title';
        appendText(title, payload.title || 'Fluxo seguro');
        copy.appendChild(title);

        var lead = document.createElement('p');
        lead.className = 'sg-ow-lead';
        appendText(lead, payload.lead || 'Orientação operacional para reduzir erro humano.');
        copy.appendChild(lead);

        var steps = Array.isArray(payload.steps) ? payload.steps.slice(0,3) : [];
        if (steps.length) {
            var list = document.createElement('ol');
            list.className = 'sg-ow-steps';
            steps.forEach(function(step, idx){
                var li = document.createElement('li');
                li.className = 'sg-ow-step';
                var num = document.createElement('span');
                num.className = 'sg-ow-step-num';
                appendText(num, String(idx + 1));
                var label = document.createElement('span');
                appendText(label, step);
                li.appendChild(num);
                li.appendChild(label);
                list.appendChild(li);
            });
            copy.appendChild(list);
        }

        if (payload.guardrail) {
            var guard = document.createElement('div');
            guard.className = 'sg-ow-guardrail';
            var guardText = document.createElement('span');
            appendText(guardText, payload.guardrail);
            guard.appendChild(guardText);
            copy.appendChild(guard);
        }

        var actions = document.createElement('div');
        actions.className = 'sg-ow-actions';
        if (payload.actionHref) {
            var action = document.createElement('a');
            action.className = 'sg-ow-action';
            action.href = payload.actionHref;
            appendText(action, payload.actionLabel || 'Continuar');
            actions.appendChild(action);
        }
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'sg-ow-close';
        close.setAttribute('aria-label', 'Ocultar orientação nesta sessão');
        appendText(close, '×');
        close.addEventListener('click', function(){
            try { if (payload.dismissKey && window.sessionStorage) sessionStorage.setItem(payload.dismissKey, '1'); } catch(e) {}
            card.remove();
        });
        actions.appendChild(close);

        card.appendChild(copy);
        card.appendChild(actions);
        page.insertBefore(card, page.firstChild);
    });
})();
