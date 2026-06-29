/* ════════════════════════════════════════════════════════════════════════
   SIGE SoftGenial - UI Kit JS v1 (Sprint UX-1, v12.11.9.94)
   API global window.sigeUi:
     sigeUi.toast(mensagem, tipo, ms)        feedback não bloqueante
     sigeUi.confirm({...}) -> Promise<bool>  confirmação com nome do objecto
     sigeUi.aCarregar(botao, ligado)         estado de carregamento em botões
   Enhancer declarativo: [data-sige-confirm] em botões/links/submits.
   Vanilla JS, sem dependências. Cânone: docs/dev/UI-GUIA.md
   ════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';
    if (window.sigeUi) return;

    var ICONES = { ok: '✅', erro: '⚠️', aviso: '🔶', info: 'ℹ️' };

    function zonaToasts() {
        var z = document.querySelector('.sgk-toast-zona');
        if (!z) {
            z = document.createElement('div');
            z.className = 'sgk-toast-zona';
            z.setAttribute('aria-live', 'polite');
            document.body.appendChild(z);
        }
        return z;
    }

    function toast(mensagem, tipo, ms) {
        tipo = ICONES[tipo] ? tipo : 'info';
        var t = document.createElement('div');
        t.className = 'sgk-toast sgk-toast-' + tipo;
        t.setAttribute('role', tipo === 'erro' ? 'alert' : 'status');
        var ic = document.createElement('span');
        ic.textContent = ICONES[tipo];
        var tx = document.createElement('span');
        tx.textContent = String(mensagem);
        t.appendChild(ic);
        t.appendChild(tx);
        zonaToasts().appendChild(t);
        var vida = typeof ms === 'number' ? ms : (tipo === 'erro' ? 6000 : 3500);
        setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, vida);
        return t;
    }

    /**
     * Confirmação canónica. Opções:
     *   titulo     (ex.: 'Remover circular')
     *   texto      (SEMPRE com o nome do objecto: 'A circular "Reunião de pais" será removida.')
     *   confirmar  rótulo do botão de acção (defeito: 'Confirmar')
     *   cancelar   rótulo do botão de recuo (defeito: 'Cancelar')
     *   perigo     true para acções destrutivas (botão vermelho)
     * Devolve Promise<boolean>; ESC, overlay e Cancelar resolvem false.
     */
    function confirmar(op) {
        op = op || {};
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'sgk-modal-overlay';
            var modal = document.createElement('div');
            modal.className = 'sgk-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');

            var h = document.createElement('h3');
            h.className = 'sgk-modal-titulo';
            h.textContent = op.titulo || 'Confirmar acção';
            var p = document.createElement('p');
            p.className = 'sgk-modal-texto';
            p.textContent = op.texto || 'Tem a certeza?';
            var accoes = document.createElement('div');
            accoes.className = 'sgk-modal-accoes';

            var bCancelar = document.createElement('button');
            bCancelar.type = 'button';
            bCancelar.className = 'sgk-btn sgk-btn-sec';
            bCancelar.textContent = op.cancelar || 'Cancelar';
            var bOk = document.createElement('button');
            bOk.type = 'button';
            bOk.className = 'sgk-btn ' + (op.perigo ? 'sgk-btn-perigo' : 'sgk-btn-primario');
            bOk.textContent = op.confirmar || 'Confirmar';

            accoes.appendChild(bCancelar);
            accoes.appendChild(bOk);
            modal.appendChild(h);
            modal.appendChild(p);
            modal.appendChild(accoes);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            var antes = document.activeElement;
            function fechar(valor) {
                document.removeEventListener('keydown', aoTeclar, true);
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                if (antes && antes.focus) { try { antes.focus(); } catch (e) {} }
                resolve(valor);
            }
            function aoTeclar(ev) {
                if (ev.key === 'Escape') { ev.preventDefault(); fechar(false); }
                if (ev.key === 'Enter') { ev.preventDefault(); fechar(true); }
            }
            bCancelar.addEventListener('click', function () { fechar(false); });
            bOk.addEventListener('click', function () { fechar(true); });
            overlay.addEventListener('click', function (ev) { if (ev.target === overlay) fechar(false); });
            document.addEventListener('keydown', aoTeclar, true);
            bOk.focus();
        });
    }

    /**
     * Pedido de texto canónico (substitui o prompt() nativo). Opções:
     *   titulo, texto, placeholder, valor (inicial), confirmar, cancelar, perigo,
     *   obrigatorio (true: não fecha com o campo vazio).
     * Devolve Promise<string|null>; ESC, overlay e Cancelar dão null.
     */
    function pedirTexto(op) {
        op = op || {};
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'sgk-modal-overlay';
            var modal = document.createElement('div');
            modal.className = 'sgk-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');

            var h = document.createElement('h3');
            h.className = 'sgk-modal-titulo';
            h.textContent = op.titulo || 'Indique o valor';
            var p = document.createElement('p');
            p.className = 'sgk-modal-texto';
            p.textContent = op.texto || '';
            var campo = document.createElement('input');
            campo.type = 'text';
            campo.placeholder = op.placeholder || '';
            campo.value = op.valor || '';
            campo.style.cssText = 'width:100%;padding:9px 11px;border-radius:7px;border:1px solid var(--sgk-borda);font-size:13.5px;margin-bottom:16px;';
            var accoes = document.createElement('div');
            accoes.className = 'sgk-modal-accoes';
            var bCancelar = document.createElement('button');
            bCancelar.type = 'button';
            bCancelar.className = 'sgk-btn sgk-btn-sec';
            bCancelar.textContent = op.cancelar || 'Cancelar';
            var bOk = document.createElement('button');
            bOk.type = 'button';
            bOk.className = 'sgk-btn ' + (op.perigo ? 'sgk-btn-perigo' : 'sgk-btn-primario');
            bOk.textContent = op.confirmar || 'Confirmar';

            accoes.appendChild(bCancelar);
            accoes.appendChild(bOk);
            modal.appendChild(h);
            if (op.texto) modal.appendChild(p);
            modal.appendChild(campo);
            modal.appendChild(accoes);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            var antes = document.activeElement;
            function fechar(valor) {
                document.removeEventListener('keydown', aoTeclar, true);
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                if (antes && antes.focus) { try { antes.focus(); } catch (e) {} }
                resolve(valor);
            }
            function tentarConfirmar() {
                var v = campo.value.trim();
                if (op.obrigatorio && v === '') {
                    campo.style.borderColor = 'var(--sgk-erro)';
                    campo.focus();
                    return;
                }
                fechar(v);
            }
            function aoTeclar(ev) {
                if (ev.key === 'Escape') { ev.preventDefault(); fechar(null); }
                if (ev.key === 'Enter') { ev.preventDefault(); tentarConfirmar(); }
            }
            campo.addEventListener('input', function () { campo.style.borderColor = ''; });
            bCancelar.addEventListener('click', function () { fechar(null); });
            bOk.addEventListener('click', tentarConfirmar);
            overlay.addEventListener('click', function (ev) { if (ev.target === overlay) fechar(null); });
            document.addEventListener('keydown', aoTeclar, true);
            campo.focus();
        });
    }

    function aCarregar(botao, ligado) {
        if (!botao) return;
        if (ligado) {
            botao.dataset.sgRotulo = botao.innerHTML;
            botao.disabled = true;
            botao.innerHTML = '<span class="sgk-spinner" aria-hidden="true"></span> A processar...';
        } else {
            botao.disabled = false;
            if (botao.dataset.sgRotulo) { botao.innerHTML = botao.dataset.sgRotulo; delete botao.dataset.sgRotulo; }
        }
    }

    // Enhancer declarativo: <button data-sige-confirm="texto" data-sige-titulo="..."
    //                        data-sige-confirmar="Remover" data-sige-perigo="1">
    // Funciona em submits de formulário e em links; sem JS por ecrã.
    document.addEventListener('click', function (ev) {
        var alvo = ev.target && ev.target.closest ? ev.target.closest('[data-sige-confirm]') : null;
        if (!alvo || alvo.dataset.sgConfirmado === '1') return;
        ev.preventDefault();
        ev.stopPropagation();
        confirmar({
            titulo: alvo.dataset.sigeTitulo || 'Confirmar acção',
            texto: alvo.dataset.sigeConfirm,
            confirmar: alvo.dataset.sigeConfirmar || 'Confirmar',
            perigo: alvo.dataset.sigePerigo === '1'
        }).then(function (ok) {
            if (!ok) return;
            alvo.dataset.sgConfirmado = '1';
            if (alvo.tagName === 'A' && alvo.href) { window.location.href = alvo.href; }
            else if (alvo.form && (alvo.type === 'submit' || alvo.tagName === 'BUTTON')) { alvo.click(); }
            else { alvo.click(); }
            setTimeout(function () { delete alvo.dataset.sgConfirmado; }, 400);
        });
    }, true);

    // Enhancer declarativo: [data-sige-act="nomeFuncaoGlobal"] dispara a funcao
    // global correspondente, em substituicao de onclick inline. Regras de passagem
    // de argumento, fieis ao onclick que substituem:
    //   - [data-sige-args]  -> fn(...JSON)        (lista JSON; preserva tipos: numeros,
    //                          booleanos, varios argumentos; ex.: data-sige-args="[12,&quot;x&quot;]")
    //   - [data-sige-self]   (com data-sige-args) -> anexa o elemento como ultimo argumento
    //                          (substitui onclick="fn('x', this)")
    //   - [data-sige-arg]   -> fn(arg)            (substitui onclick="fn('texto')", um argumento)
    //   - [data-sige-noargs]-> fn()               (substitui onclick="fn()", sem passar nada,
    //                                              para funcoes cujo 1o parametro e significativo)
    //   - caso contrario    -> fn(elemento)       (substitui onclick="fn(this)")
    // Permite remover onclick das views por vagas, sem JS por ecra e sem alterar a
    // logica dos handlers. Em fase de bolha, para que o enhancer de confirmacao
    // (em captura) corra primeiro.
    document.addEventListener('click', function (ev) {
        var alvo = ev.target && ev.target.closest ? ev.target.closest('[data-sige-act]') : null;
        if (!alvo) return;
        var accao = alvo.getAttribute('data-sige-act');
        if (!accao) return;
        var fn = resolverAcao ? resolverAcao(accao) : window[accao];
        if (typeof fn !== 'function') return;
        // [data-sige-prevent]: impede a accao por omissao (substitui o "return false"
        // de onclick em <a>, por exemplo abrir o recibo numa janela sem navegar).
        if (alvo.hasAttribute('data-sige-prevent')) { ev.preventDefault(); }
        var argsJson = alvo.getAttribute('data-sige-args');
        if (argsJson !== null) {
            var args;
            try { args = JSON.parse(argsJson); } catch (e) { return; }
            if (!Array.isArray(args)) { args = [args]; }
            if (alvo.hasAttribute('data-sige-self')) { args = args.concat([alvo]); }
            fn.apply(null, args);
            return;
        }
        var arg = alvo.getAttribute('data-sige-arg');
        if (arg !== null) { fn(arg); }
        else if (alvo.hasAttribute('data-sige-noargs')) { fn(); }
        else { fn(alvo); }
    });

    // Submissao por TECLADO (Enter num campo) nao passa pelo clique: este
    // interceptor garante que formulários cujo botão de submissão usa
    // [data-sige-confirm] pedem SEMPRE confirmação, venha de onde vier.
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        if (!form || form.tagName !== 'FORM') return;
        // [v12.12.36] Respeitar o botao realmente premido. Se o submitter for conhecido,
        // so pedir confirmacao quando ELE a exige; um botao sem [data-sige-confirm] submete
        // normalmente e nunca herda o dialogo de outro botao do mesmo formulario. So quando
        // nao ha submitter (ex.: Enter num campo, em navegadores antigos) e que se recorre ao
        // primeiro botao de submissao com confirmacao.
        var botao;
        if (ev.submitter) {
            if (ev.submitter.dataset && ev.submitter.dataset.sigeConfirm !== undefined) {
                botao = ev.submitter;
            } else {
                return;
            }
        } else {
            botao = form.querySelector('button[type="submit"][data-sige-confirm], input[type="submit"][data-sige-confirm]');
        }
        if (!botao) return;
        if (botao.dataset.sgConfirmado === '1') return; // já confirmado pelo modal
        ev.preventDefault();
        ev.stopPropagation();
        confirmar({
            titulo: botao.dataset.sigeTitulo || 'Confirmar acção',
            texto: botao.dataset.sigeConfirm,
            confirmar: botao.dataset.sigeConfirmar || 'Confirmar',
            perigo: botao.dataset.sigePerigo === '1'
        }).then(function (ok) {
            if (!ok) return;
            botao.dataset.sgConfirmado = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit(botao);
            else botao.click();
            setTimeout(function () { delete botao.dataset.sgConfirmado; }, 400);
        });
    }, true);

    // ────────────────────────────────────────────────────────────────────
    // Funcoes nomeadas globais que substituem expressoes inline em onclick.
    // Sao expostas em window (e nao em sigeUi) para o despachante data-sige-act
    // as encontrar via window[accao]. Cada uma e fiel a expressao que substitui.
    // Recebem o elemento (equivalente ao "this") quando a expressao o usava.
    // ────────────────────────────────────────────────────────────────────
    window.sigeImprimirPagina = function () { window.print(); };

    window.sigeFecharModalJq = function (sel) {
        if (window.jQuery && sel) { window.jQuery(sel).fadeOut(200); }
    };

    // Alterna a visibilidade de um elemento por id (data-target), entre o valor
    // de exibicao indicado (data-display, por omissao block) e none.
    window.sigeAlternarDisplay = function (el) {
        if (!el) { return; }
        var t = document.getElementById(el.getAttribute('data-target'));
        if (!t) { return; }
        var show = el.getAttribute('data-display') || 'block';
        t.style.display = (t.style.display === 'none') ? show : 'none';
    };

    // Marca o proximo download como sem transicao, se a funcao existir.
    window.sigeMarcarDownloadSemTransicao = function () {
        if (window.sigeMarkNoTransitionDownload) { window.sigeMarkNoTransitionDownload(); }
    };

    window.sigeAlternarQuebraTexto = function (el) {
        if (!el || !el.style) return;
        el.style.whiteSpace = (el.style.whiteSpace === 'normal') ? 'nowrap' : 'normal';
    };

    window.sigeAlternarClasseProximo = function (el) {
        if (el && el.nextElementSibling) { el.nextElementSibling.classList.toggle('open'); }
    };

    window.sigeFecharPopupBackdrop = function (el) {
        var n = (el && el.closest) ? el.closest('.sige-pro-popup-backdrop') : null;
        if (n) { n.remove(); }
    };

    // Sidebar movel do shell (admin-shell.php)
    window.sigeAlternarSidebar = function () {
        var s = document.getElementById('sige-sidebar');
        var o = document.getElementById('sige-overlay');
        if (s) { s.classList.toggle('open'); }
        if (o) { o.classList.toggle('show'); }
        document.body.classList.toggle('sg-app-menu-open');
    };

    window.sigeFecharSidebar = function (el) {
        var s = document.getElementById('sige-sidebar');
        if (s) { s.classList.remove('open'); }
        if (el && el.classList) { el.classList.remove('show'); }
        document.body.classList.remove('sg-app-menu-open');
    };

    window.sigeAlternarSidebarMais = function (el) {
        var s = document.getElementById('sige-sidebar');
        var o = document.getElementById('sige-overlay');
        var aberto = false;
        if (s) { s.classList.toggle('open'); aberto = s.classList.contains('open'); }
        if (o) { o.classList.toggle('show', aberto); }
        document.body.classList.toggle('sg-app-menu-open', aberto);
        if (el) { el.setAttribute('aria-expanded', aberto ? 'true' : 'false'); }
    };

    // Abre o recibo (href do proprio link) numa janela, sem navegar a pagina
    // (o link usa data-sige-prevent). Substitui onclick="window.open(this.href,...);return false;".
    window.sigeAbrirReciboJanela = function (el) {
        if (el && el.href) {
            window.open(el.href, 'ReciboSIGE', 'width=980,height=850,scrollbars=yes,resizable=yes');
        }
    };

    // Navega para um endereco (ex.: mailto). Substitui onclick="window.location.href='...'".
    window.sigeIrPara = function (url) {
        if (url) { window.location.href = url; }
    };

    // v12.14.0 - CSP enforcement: substitui expressoes inline do tipo
    // window.open(this.href, ...); return false; por acao declarativa.
    window.sigeAbrirJanela = function (el) {
        if (!el || !el.href) { return; }
        var nome = el.getAttribute('data-sige-window-name') || 'SIGEWindow';
        var features = el.getAttribute('data-sige-window-features') || 'width=980,height=850,scrollbars=yes,resizable=yes';
        window.open(el.href, nome, features);
    };

    // v12.14.0 - executa uma funcao global passando o JSON guardado no proprio
    // elemento, para substituir onclick='fn({...})' com objectos grandes.
    window.sigeExecutarJsonData = function (el) {
        if (!el) { return; }
        var nome = el.getAttribute('data-sige-json-fn') || '';
        var fn = window[nome];
        if (typeof fn !== 'function') { return; }
        var raw = el.getAttribute('data-sige-json') || '{}';
        var data;
        try { data = JSON.parse(raw); } catch (e) { return; }
        fn(data);
    };

    // v12.14.0 - substitui onclick="this.parentElement.remove()" nos toasts.
    window.sigeRemoverPai = function (el) {
        if (el && el.parentElement) { el.parentElement.remove(); }
    };

    // v12.14.0 - ponte declarativa para formularios que ja tinham modal proprio
    // de confirmacao de caixa na view de extractos.
    window.sigeConfirmacaoCaixaSubmit = function (el) {
        if (!el || !el.form || typeof window.sigeAbrirConfirmacaoCaixa !== 'function') { return; }
        window.sigeAbrirConfirmacaoCaixa(
            el.form,
            el.getAttribute('data-sige-confirm-title') || 'Confirmar acção',
            el.getAttribute('data-sige-confirm-text') || 'Confirme a operação antes de avançar.'
        );
    };





    // v12.14.4 - acções documentais e comandos simples sem inline handlers.
    // Fica também disponível no shell admin para botões/documentos gerados por AJAX.
    document.addEventListener('click', function (ev) {
        var alvo = ev.target && ev.target.closest ? ev.target.closest('[data-sige-print],[data-sige-close],[data-sige-back],[data-sige-close-back]') : null;
        if (!alvo) return;
        if (alvo.hasAttribute('data-sige-print')) { ev.preventDefault(); window.print(); return; }
        if (alvo.hasAttribute('data-sige-close-back')) {
            ev.preventDefault();
            try { window.close(); } catch (e) {}
            if (history && history.length > 1) { try { history.back(); } catch (e2) {} }
            return;
        }
        if (alvo.hasAttribute('data-sige-close')) { ev.preventDefault(); try { window.close(); } catch (e3) {} return; }
        if (alvo.hasAttribute('data-sige-back')) { ev.preventDefault(); if (history) { history.back(); } }
    });


    // v12.14.1 - CSP Zero-Inline Hydrator
    // Consome data-sige-style/data-sige-on-* criados pelo sanitizador PHP e aplica
    // estilos/eventos por JS externo, sem permissao-inline, sem APIs dinamicas perigosas.
    function splitCssDecls(css) {
        var out = [], cur = '', depth = 0, quote = '';
        css = String(css || '');
        for (var i = 0; i < css.length; i++) {
            var ch = css.charAt(i);
            if (quote) { cur += ch; if (ch === quote && css.charAt(i - 1) !== '\\') quote = ''; continue; }
            if (ch === '"' || ch === "'") { quote = ch; cur += ch; continue; }
            if (ch === '(') depth++;
            if (ch === ')' && depth > 0) depth--;
            if (ch === ';' && depth === 0) { if (cur.trim()) out.push(cur.trim()); cur = ''; continue; }
            cur += ch;
        }
        if (cur.trim()) out.push(cur.trim());
        return out;
    }
    function aplicarDataStyle(el) {
        if (!el || !el.getAttribute || el.dataset.sgStyleApplied === '1') return;
        var raw = el.getAttribute('data-sige-style');
        if (!raw) return;
        splitCssDecls(raw).forEach(function (decl) {
            var idx = decl.indexOf(':');
            if (idx <= 0) return;
            var prop = decl.slice(0, idx).trim();
            var val = decl.slice(idx + 1).trim();
            var priority = '';
            if (/!important\s*$/i.test(val)) { val = val.replace(/!important\s*$/i, '').trim(); priority = 'important'; }
            try { el.style.setProperty(prop, val, priority); } catch (e) {}
        });
        el.dataset.sgStyleApplied = '1';
    }
    function hidratarDataStyles(root) {
        root = root || document;
        if (root.nodeType === 1 && root.hasAttribute && root.hasAttribute('data-sige-style')) aplicarDataStyle(root);
        var nodes = root.querySelectorAll ? root.querySelectorAll('[data-sige-style]') : [];
        Array.prototype.forEach.call(nodes, aplicarDataStyle);
    }

    function resolverAcao(nome) {
        if (!nome) return null;
        var ctx = window;
        String(nome).split('.').forEach(function (parte) { ctx = ctx && ctx[parte]; });
        return (typeof ctx === 'function') ? ctx : null;
    }
    function parseArg(raw, el, ev) {
        raw = String(raw || '').trim();
        if (raw === 'this') return el;
        if (raw === 'event') return ev;
        if (raw === 'this.value') return el ? el.value : undefined;
        if (raw === 'this.checked') return !!(el && el.checked);
        if (raw === 'true') return true;
        if (raw === 'false') return false;
        if (raw === 'null') return null;
        if (/^-?\d+(\.\d+)?$/.test(raw)) return Number(raw);
        var m = raw.match(/^['"]([\s\S]*)['"]$/);
        if (m) return m[1].replace(/\\'/g, "'").replace(/\\"/g, '"');
        return raw;
    }
    function splitArgs(raw) {
        var out = [], cur = '', quote = '', depth = 0;
        raw = String(raw || '');
        for (var i = 0; i < raw.length; i++) {
            var ch = raw.charAt(i);
            if (quote) { cur += ch; if (ch === quote && raw.charAt(i - 1) !== '\\') quote = ''; continue; }
            if (ch === '"' || ch === "'") { quote = ch; cur += ch; continue; }
            if (ch === '(') depth++;
            if (ch === ')' && depth > 0) depth--;
            if (ch === ',' && depth === 0) { out.push(cur.trim()); cur = ''; continue; }
            cur += ch;
        }
        if (cur.trim() !== '') out.push(cur.trim());
        return out;
    }
    function splitStatements(expr) {
        var out = [], cur = '', quote = '', depth = 0;
        expr = String(expr || '');
        for (var i = 0; i < expr.length; i++) {
            var ch = expr.charAt(i);
            if (quote) { cur += ch; if (ch === quote && expr.charAt(i - 1) !== '\\') quote = ''; continue; }
            if (ch === '"' || ch === "'") { quote = ch; cur += ch; continue; }
            if (ch === '(' || ch === '{') depth++;
            if ((ch === ')' || ch === '}') && depth > 0) depth--;
            if (ch === ';' && depth === 0) { if (cur.trim()) out.push(cur.trim()); cur = ''; continue; }
            cur += ch;
        }
        if (cur.trim()) out.push(cur.trim());
        return out;
    }
    function submitForm(form) {
        if (!form) return;
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }
    function executarExpressaoLegada(expr, el, ev) {
        expr = String(expr || '').trim();
        if (!expr) return true;
        if (expr.indexOf('return ') === 0) expr = expr.slice(7).trim().replace(/;$/, '').trim();
        if (expr === 'false') { if (ev) ev.preventDefault(); return false; }
        if (expr === 'this.form.submit()') { submitForm(el && el.form); return true; }
        if (expr === "this.closest('form').submit()" || expr === 'this.closest("form").submit()') { submitForm(el && el.closest ? el.closest('form') : null); return true; }
        var byIdSubmit = expr.match(/^document\.getElementById\(['"]([^'"]+)['"]\)\.submit\(\)$/);
        if (byIdSubmit) { submitForm(document.getElementById(byIdSubmit[1])); return true; }
        if (expr === 'window.print()') { window.print(); return true; }
        if (expr === 'window.close()') { window.close(); return true; }
        if (expr === 'history.back()') { history.back(); return true; }
        if (expr === 'window.close(); if(history.length>1){history.back();}' || expr === 'window.close(); if(history.length>1){history.back()}') { window.close(); if (history.length > 1) history.back(); return true; }

        var confirmMatch = expr.match(/^confirm\(['"]([\s\S]*)['"]\)$/);
        if (confirmMatch) {
            var ok = window.confirm(confirmMatch[1].replace(/\\'/g, "'").replace(/\\"/g, '"'));
            if (!ok && ev) ev.preventDefault();
            return ok;
        }
        var srcMatch = expr.match(/^this\.src\s*=\s*['"]([^'"]+)['"]$/);
        if (srcMatch && el) { el.src = srcMatch[1]; return true; }
        if (expr === "this.style.display='none'" || expr === 'this.style.display="none"') { if (el) el.style.setProperty('display', 'none'); return true; }
        var styleAssign = expr.match(/^this\.style\.([A-Za-z][A-Za-z0-9_]*)\s*=\s*['"]([\s\S]*)['"]$/);
        if (styleAssign && el && el.style) {
            var cssProp = styleAssign[1].replace(/[A-Z]/g, function (m) { return '-' + m.toLowerCase(); });
            try { el.style.setProperty(cssProp, styleAssign[2]); } catch (e) {}
            return true;
        }
        var idStyleTernary = expr.match(/^document\.getElementById\(['"]([^'"]+)['"]\)\.style\.([A-Za-z][A-Za-z0-9_]*)\s*=\s*this\.checked\s*\?\s*['"]([^'"]*)['"]\s*:\s*['"]([^'"]*)['"]$/);
        if (idStyleTernary) {
            var idEl = document.getElementById(idStyleTernary[1]);
            if (idEl && idEl.style && el) {
                var prop = idStyleTernary[2].replace(/[A-Z]/g, function (m) { return '-' + m.toLowerCase(); });
                try { idEl.style.setProperty(prop, el.checked ? idStyleTernary[3] : idStyleTernary[4]); } catch (e2) {}
            }
            return true;
        }
        var idValueAssign = expr.match(/^document\.getElementById\(['"]([^'"]+)['"]\)\.value\s*=\s*this\.value$/);
        if (idValueAssign) { var idVal = document.getElementById(idValueAssign[1]); if (idVal && el) idVal.value = el.value; return true; }
        if (expr === "this.style.display='none';this.parentNode.classList.add('is-fallback')") { if (el) { el.style.setProperty('display', 'none'); if (el.parentNode && el.parentNode.classList) el.parentNode.classList.add('is-fallback'); } return true; }
        if (expr === "this.nextElementSibling.value=this.options[this.selectedIndex].text") { if (el && el.nextElementSibling && el.options) el.nextElementSibling.value = el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : ''; return true; }
        var exempt = expr.match(/^if\(this\.value\)document\.getElementById\(['"]([^'"]+)['"]\)\.value=this\.value$/);
        if (exempt) { var target = document.getElementById(exempt[1]); if (target && el && el.value) target.value = el.value; return true; }
        if (expr.indexOf("document.getElementById('fam_motivo_wrap').style.display=this.checked") === 0) {
            var fam = document.getElementById('fam_motivo_wrap'); if (fam && el) fam.style.setProperty('display', el.checked ? 'flex' : 'none'); return true;
        }
        if (expr.indexOf("this.closest('label').style.background") === 0 && el && el.closest) {
            var lab = el.closest('label');
            if (lab) {
                lab.style.setProperty('background', el.checked ? 'var(--color-danger-50)' : 'var(--color-success-50)');
                lab.style.setProperty('border-color', el.checked ? 'var(--color-danger-200)' : 'var(--color-success-200)');
            }
            return true;
        }

        var call = expr.match(/^([A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*)\((.*)\)$/);
        if (call) {
            var fn = resolverAcao(call[1]);
            if (fn) {
                var args = splitArgs(call[2]).map(function (a) { return parseArg(a, el, ev); });
                fn.apply(null, args);
                return true;
            }
        }
        return true;
    }
    function hidratarEventos(root) {
        root = root || document;
        var selector = '[data-sige-on-click],[data-sige-on-change],[data-sige-on-input],[data-sige-on-keyup],[data-sige-on-submit],[data-sige-on-error],[data-sige-on-load],[data-sige-on-mouseout],[data-sige-on-mouseover],[data-sige-on-blur],[data-sige-on-focus]';
        var nodes = [];
        if (root.nodeType === 1 && root.matches && root.matches(selector)) nodes.push(root);
        if (root.querySelectorAll) nodes = nodes.concat(Array.prototype.slice.call(root.querySelectorAll(selector)));
        nodes.forEach(function (el) {
            ['click','change','input','keyup','submit','error','load','mouseout','mouseover','blur','focus'].forEach(function (evt) {
                var attr = 'data-sige-on-' + evt;
                if (!el.hasAttribute || !el.hasAttribute(attr) || el.getAttribute('data-sige-bound-' + evt) === '1') return;
                el.setAttribute('data-sige-bound-' + evt, '1');
                el.addEventListener(evt, function (ev) {
                    var expr = el.getAttribute(attr) || '';
                    var ok = true;
                    splitStatements(expr).forEach(function (stmt) {
                        if (ok !== false) ok = executarExpressaoLegada(stmt, el, ev);
                    });
                    if (ok === false) { ev.preventDefault(); ev.stopPropagation(); }
                });
            });
        });
    }
    function hidratarCspZeroInline(root) {
        hidratarDataStyles(root || document);
        hidratarEventos(root || document);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { hidratarCspZeroInline(document); });
    } else {
        hidratarCspZeroInline(document);
    }
    if (typeof MutationObserver !== 'undefined') {
        var mo = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes || [], function (n) { if (n.nodeType === 1) hidratarCspZeroInline(n); });
            });
        });
        try { mo.observe(document.documentElement || document.body, { childList: true, subtree: true }); } catch (e) {}
    }


    /* CSP Popup Guard v12.15.4
       Corrige janelas criadas por window.open()+document.write() que herdam a CSP
       do shell SIGE. Sem isto, <style> e <script> escritos no popup podem ser
       bloqueados e o documento aparece como HTML cru. Não usa eval/Function. */
    function cspNonceActual() {
        try {
            if (window.SIGE_CSP_NONCE) return String(window.SIGE_CSP_NONCE || '');
            if (document.currentScript && document.currentScript.nonce) return String(document.currentScript.nonce || '');
            var n = document.querySelector('script[nonce],style[nonce]');
            return n ? String(n.getAttribute('nonce') || n.nonce || '') : '';
        } catch (e) { return ''; }
    }
    function escAttrCsp(v) {
        return String(v || '').replace(/[&"<>]/g, function (c) { return {'&':'&amp;','"':'&quot;','<':'&lt;','>':'&gt;'}[c] || c; });
    }
    function cspNonceHtml(html) {
        var nonce = cspNonceActual();
        if (!nonce || typeof html !== 'string') return html;
        var n = escAttrCsp(nonce);
        html = html.replace(/<style\b(?![^>]*\bnonce=)/gi, '<style nonce="' + n + '"');
        html = html.replace(/<script\b(?![^>]*\bnonce=)(?![^>]*\bsrc=)/gi, '<script nonce="' + n + '"');
        return html;
    }
    function patchPopupDocumentWrite(win) {
        try {
            if (!win || !win.document || win.document.__sigeCspWritePatched) return win;
            var doc = win.document;
            var rawWrite = doc.write;
            var rawWriteln = doc.writeln;
            doc.__sigeCspWritePatched = true;
            if (typeof rawWrite === 'function') {
                doc.write = function () {
                    var args = Array.prototype.map.call(arguments, function (x) { return typeof x === 'string' ? cspNonceHtml(x) : x; });
                    return rawWrite.apply(doc, args);
                };
            }
            if (typeof rawWriteln === 'function') {
                doc.writeln = function () {
                    var args = Array.prototype.map.call(arguments, function (x) { return typeof x === 'string' ? cspNonceHtml(x) : x; });
                    return rawWriteln.apply(doc, args);
                };
            }
        } catch (e) {}
        return win;
    }
    function activarPopupCspGuard() {
        try {
            if (window.__sigeCspOpenPatched || typeof window.open !== 'function') return;
            var rawOpen = window.open;
            window.__sigeCspOpenPatched = true;
            window.open = function () {
                var win = rawOpen.apply(window, arguments);
                return patchPopupDocumentWrite(win);
            };
        } catch (e) {}
    }
    activarPopupCspGuard();

    window.sigeUi = { toast: toast, confirm: confirmar, prompt: pedirTexto, aCarregar: aCarregar };
})();
