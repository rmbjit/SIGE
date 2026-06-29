/* SIGE SoftGenial v12.15.21 - Pesquisa Global (P3).
   Caixa de pesquisa unica na barra de topo. Sem dialogos nativos e sem
   atributos de evento inline: so addEventListener. Render seguro (textContent), navegacao por teclado.
   Usa jQuery.post para herdar o nonce global auto-injectado (_sige_nonce_g). */
(function () {
    'use strict';

    var input = document.getElementById('sgGSearchInput');
    var panel = document.getElementById('sgGSearchPanel');
    if (!input || !panel) return;

    // Portar o painel para o body: tira-o de qualquer contexto de empilhamento
    // da barra de topo (que em certas larguras fica abaixo do conteudo). O painel
    // passa a 'position: fixed' e e posicionado sob a caixa por JS.
    var box = input.closest('.sg-gsearch-box') || input.parentNode;
    if (panel.parentNode !== document.body) {
        document.body.appendChild(panel);
    }

    var $ = window.jQuery || null;
    var ajaxUrl = (typeof window.sigeAjax !== 'undefined' && window.sigeAjax.url)
        ? window.sigeAjax.url
        : (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '/wp-admin/admin-ajax.php');

    var timer = null;
    var ultimoTermo = '';
    var pedido = 0;            // contador para descartar respostas fora de ordem
    var itensActuais = [];     // elementos <a> dos resultados, para o teclado
    var indiceFoco = -1;

    function posicionar() {
        var r = box.getBoundingClientRect();
        panel.style.top = (r.bottom + 8) + 'px';
        panel.style.left = r.left + 'px';
        panel.style.width = r.width + 'px';
    }

    function fecharPainel() {
        panel.hidden = true;
        panel.innerHTML = '';
        itensActuais = [];
        indiceFoco = -1;
        input.setAttribute('aria-expanded', 'false');
    }

    function abrirPainel() {
        posicionar();
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    }

    function mensagem(texto) {
        panel.innerHTML = '';
        var div = document.createElement('div');
        div.className = 'sg-gsearch-empty';
        div.textContent = texto;
        panel.appendChild(div);
        abrirPainel();
    }

    function iconeSvg(nome) {
        // Conjunto minimo de icones inline (stroke currentColor).
        var paths = {
            users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
            layers: '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            receipt: '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/>',
            plan: '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6"/><path d="M9 11h6"/><path d="M9 15h6"/>',
            expense: '<path d="M2 12h6l3-9 3 18 3-9h5"/>'
        };
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        svg.innerHTML = paths[nome] || paths.users;
        return svg;
    }

    function render(grupos) {
        panel.innerHTML = '';
        itensActuais = [];
        indiceFoco = -1;

        if (!grupos || !grupos.length) {
            mensagem('Sem resultados para "' + ultimoTermo + '".');
            return;
        }

        grupos.forEach(function (grupo) {
            var cab = document.createElement('div');
            cab.className = 'sg-gsearch-group';
            cab.textContent = grupo.titulo;
            panel.appendChild(cab);

            (grupo.itens || []).forEach(function (item) {
                var a = document.createElement('a');
                a.className = 'sg-gsearch-item';
                a.href = item.url || '#';
                a.setAttribute('role', 'option');

                var ic = document.createElement('span');
                ic.className = 'sg-gsearch-item-ico';
                ic.appendChild(iconeSvg(grupo.icone));
                a.appendChild(ic);

                var txt = document.createElement('span');
                txt.className = 'sg-gsearch-item-txt';
                var t1 = document.createElement('span');
                t1.className = 'sg-gsearch-item-titulo';
                t1.textContent = item.titulo || '';
                txt.appendChild(t1);
                if (item.sub) {
                    var t2 = document.createElement('span');
                    t2.className = 'sg-gsearch-item-sub';
                    t2.textContent = item.sub;
                    txt.appendChild(t2);
                }
                a.appendChild(txt);

                a.addEventListener('mouseenter', function () { focar(itensActuais.indexOf(a)); });
                panel.appendChild(a);
                itensActuais.push(a);
            });
        });

        abrirPainel();
    }

    function focar(i) {
        if (!itensActuais.length) return;
        if (i < 0) i = itensActuais.length - 1;
        if (i >= itensActuais.length) i = 0;
        itensActuais.forEach(function (el) { el.classList.remove('is-foco'); });
        indiceFoco = i;
        itensActuais[i].classList.add('is-foco');
        itensActuais[i].scrollIntoView({ block: 'nearest' });
    }

    function pesquisar(termo) {
        var meu = ++pedido;
        if (!$) {
            mensagem('Pesquisa indisponivel.');
            return;
        }
        $.post(ajaxUrl, { action: 'sige_pesquisa_global', q: termo })
            .done(function (resp) {
                if (meu !== pedido) return; // resposta fora de ordem
                if (!resp || !resp.success) {
                    mensagem('Nao foi possivel pesquisar agora.');
                    return;
                }
                render(resp.data && resp.data.grupos ? resp.data.grupos : []);
            })
            .fail(function (xhr) {
                if (meu !== pedido) return;
                var cod = (xhr && xhr.status) ? ' (' + xhr.status + ')' : '';
                mensagem('Nao foi possivel pesquisar' + cod + '.');
            });
    }

    input.addEventListener('input', function () {
        var termo = input.value.trim();
        ultimoTermo = termo;
        clearTimeout(timer);
        if (termo.length < 2) {
            fecharPainel();
            return;
        }
        mensagem('A pesquisar...');
        timer = setTimeout(function () { pesquisar(termo); }, 220);
    });

    input.addEventListener('keydown', function (ev) {
        if (panel.hidden && (ev.key === 'ArrowDown' || ev.key === 'ArrowUp')) {
            if (input.value.trim().length >= 2) { abrirPainel(); }
        }
        if (ev.key === 'ArrowDown') { ev.preventDefault(); focar(indiceFoco + 1); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); focar(indiceFoco - 1); }
        else if (ev.key === 'Enter') {
            if (indiceFoco >= 0 && itensActuais[indiceFoco]) {
                ev.preventDefault();
                window.location.href = itensActuais[indiceFoco].href;
            }
        } else if (ev.key === 'Escape') {
            fecharPainel();
            input.blur();
        }
    });

    // Fechar ao clicar fora (o painel agora vive no body, fora de .sg-gsearch).
    document.addEventListener('click', function (ev) {
        if (ev.target.closest('.sg-gsearch')) return;
        if (ev.target.closest('#sgGSearchPanel')) return;
        fecharPainel();
    });

    // Manter o painel alinhado com a caixa ao deslocar ou redimensionar.
    function reposicionarSeAberto() {
        if (!panel.hidden) posicionar();
    }
    window.addEventListener('scroll', reposicionarSeAberto, true);
    window.addEventListener('resize', reposicionarSeAberto);

    // Atalho "/" para focar a pesquisa (quando o foco nao esta num campo de texto).
    document.addEventListener('keydown', function (ev) {
        if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey) return;
        var alvo = ev.target;
        var tag = alvo && alvo.tagName ? alvo.tagName.toUpperCase() : '';
        var editavel = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (alvo && alvo.isContentEditable);
        if (editavel) return;
        ev.preventDefault();
        input.focus();
    });
})();
