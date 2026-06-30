/**
 * SIGE SoftGenial - Registo de modelos de cracha do estudante.
 *
 * FONTE DE VERDADE UNICA do desenho dos crachas: usado tanto pela pre-visualizacao
 * ao vivo (na pagina Alunos) como pela impressao real. Garante que o que a escola
 * ve no seletor e exactamente o que sai impresso.
 *
 * Cada modelo expoe:
 *   meta : { id, nome, descricao }
 *   css(ctx)  -> string com um bloco <style> (com nonce quando ctx.nonce existe)
 *   card(a, ctx) -> string HTML de UM cartao
 *
 * ctx = {
 *   escolaNome, logoUrl, anoLectivo,
 *   accent (hex), social {instagram, facebook, website}, showSocial (bool),
 *   template (id), batch (bool), nonce (string)
 * }
 *
 * Contexto de impressao: documento autonomo (janela/iframe), por isso usa cores
 * literais (nao ha acesso a assets/sige-tokens.css). A cor de destaque vem da
 * escolha da escola (ctx.accent).
 */
(function () {
    'use strict';

    function esc(v) {
        return String(v === undefined || v === null ? '' : v).replace(/[&<>'"]/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[c];
        });
    }

    // Clareia (p>0) ou escurece (p<0) uma cor hex; tolerante a entradas invalidas.
    function shade(hex, p) {
        hex = String(hex || '').replace('#', '');
        if (hex.length === 3) { hex = hex.split('').map(function (c) { return c + c; }).join(''); }
        var n = parseInt(hex, 16);
        if (isNaN(n) || hex.length !== 6) { return '#7c3aed'; }
        var r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
        function adj(x) { return Math.round(Math.min(255, Math.max(0, x + (p / 100) * 255))); }
        return '#' + ((1 << 24) + (adj(r) << 16) + (adj(g) << 8) + adj(b)).toString(16).slice(1);
    }

    function accentOf(ctx) {
        var a = String((ctx && ctx.accent) || '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(a) ? a : '#7c3aed';
    }

    function photoTag(a, fallbackColor) {
        var foto = (a && a.foto) ? String(a.foto) : '';
        return foto
            ? '<img src="' + esc(foto) + '" alt="">'
            : '<span class="ph">FOTO</span>';
    }

    function processoOf(a) { return (a && a.numero_processo) ? String(a.numero_processo) : ''; }
    function nomeOf(a) { return (a && a.nome_completo) ? String(a.nome_completo) : ''; }
    function turmaOf(a) {
        return (a && a.classe && a.turma_nome) ? (esc(a.classe) + ' - ' + esc(a.turma_nome)) : 'S/ Turma';
    }

    // QR (data-uri) quando disponivel; caso contrario, o numero de processo legivel
    // para o porteiro validar pela entrada manual. Nunca deixa o espaco vazio.
    function qrCell(a) {
        var proc = processoOf(a);
        var qr = (typeof window.sigeQrDataUri === 'function') ? window.sigeQrDataUri('Aluno:' + proc) : '';
        return qr
            ? '<img src="' + qr + '" class="qr" alt="">'
            : '<span class="qr-fallback">' + esc(proc) + '</span>';
    }

    // Ícones sociais (SVG inline). Devolve '' quando nada para mostrar.
    function socialRow(ctx) {
        if (!ctx || !ctx.showSocial || !ctx.social) { return ''; }
        var s = ctx.social, items = [];
        function ico(path) {
            return '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + path + '</svg>';
        }
        if (s.instagram) {
            items.push('<span class="soc"><span class="soc-i">' + ico('<rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>') + '</span>' + esc(s.instagram) + '</span>');
        }
        if (s.facebook) {
            items.push('<span class="soc"><span class="soc-i">' + ico('<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>') + '</span>' + esc(s.facebook) + '</span>');
        }
        if (s.website) {
            items.push('<span class="soc"><span class="soc-i">' + ico('<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20a15.3 15.3 0 0 1 0-20"/>') + '</span>' + esc(s.website) + '</span>');
        }
        if (!items.length) { return ''; }
        return '<div class="social">' + items.join('') + '</div>';
    }

    function styleOpen(ctx) {
        var n = (ctx && ctx.nonce) ? ' nonce="' + esc(ctx.nonce) + '"' : '';
        return '<style' + n + '>';
    }

    // CSS base partilhado (estrutura da folha A4 e do contentor do cartao).
    function sheetCss(ctx) {
        return 'body{font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact;margin:0;padding:0;background:' + (ctx.batch ? '#ffffff' : '#eef1f6') + ';' + (ctx.batch ? '' : 'display:flex;justify-content:center;align-items:center;min-height:100vh;') + '}'
            + (ctx.batch ? '@media print{@page{size:A4;margin:10mm}}' : '')
            + '.sheet{display:flex;flex-wrap:wrap;gap:15px;justify-content:' + (ctx.batch ? 'flex-start' : 'center') + ';padding:' + (ctx.batch ? '10px' : '0') + '}'
            + '.card-container{break-inside:avoid;page-break-inside:avoid}'
            + '.ph{font-size:10px;color:#94a3b8;font-weight:700}'
            + '.qr{width:42px;height:42px}'
            + '.qr-fallback{font-size:9px;font-weight:700;font-family:\'Courier New\',monospace;letter-spacing:.5px}';
    }

    var T = {};

    // ───────────────────────────── AURORA ─────────────────────────────
    // Cabecalho em degrade da cor de destaque, foto circular sobreposta, redes em chips.
    T.aurora = {
        meta: { id: 'aurora', nome: 'Aurora', descricao: 'Degradê moderno com foto circular e redes sociais.' },
        css: function (ctx) {
            var a = accentOf(ctx), a2 = shade(a, -22), soft = shade(a, 42);
            return styleOpen(ctx) + sheetCss(ctx)
                + '.card{width:220px;height:350px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.16);display:flex;flex-direction:column;align-items:center;box-sizing:border-box;position:relative}'
                /* O cabecalho reserva padding inferior (46px) MAIOR que a sobreposicao
                   da foto (40px), por isso a foto fica sempre ABAIXO do titulo, mesmo
                   com o nome da escola em 2-3 linhas (corrige a foto a tapar o texto). */
                + '.head{width:100%;min-height:84px;background:linear-gradient(135deg,' + a + ',' + a2 + ');display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:12px 8px 46px;box-sizing:border-box}'
                + '.head .logo{height:30px;width:30px;border-radius:50%;background:#fff;padding:2px;object-fit:contain}'
                + '.head .title{color:#fff;font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;text-align:center;margin-top:5px;line-height:1.2;padding:0 10px}'
                + '.photo{width:84px;height:84px;border-radius:50%;border:4px solid #fff;background:' + soft + ';overflow:hidden;display:flex;align-items:center;justify-content:center;margin-top:-40px;position:relative;z-index:2;box-shadow:0 4px 12px rgba(15,23,42,.18)}'
                + '.photo img{width:100%;height:100%;object-fit:cover}'
                + '.body{flex:1;width:100%;text-align:center;padding:10px 12px 8px;box-sizing:border-box}'
                + '.name{font-size:13px;font-weight:800;color:#0f172a;line-height:1.15;max-height:31px;overflow:hidden}'
                + '.role{display:inline-block;margin-top:6px;background:' + soft + ';color:' + a2 + ';font-size:8px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;padding:3px 12px;border-radius:999px}'
                + '.meta{margin-top:8px;font-size:10px;color:#475569;line-height:1.5}'
                + '.meta strong{color:#1e293b}'
                + '.social{display:flex;flex-wrap:wrap;gap:4px 8px;justify-content:center;margin-top:8px;padding:0 6px}'
                + '.social .soc{display:inline-flex;align-items:center;gap:3px;font-size:7.5px;font-weight:600;color:' + a2 + ';max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
                + '.social .soc-i{color:' + a + ';display:inline-flex}'
                + '.foot{width:100%;background:#f8fafc;border-top:1px solid #eef2f7;padding:8px 14px;box-sizing:border-box;display:flex;justify-content:space-between;align-items:center}'
                + '.foot .val{font-size:8px;color:#64748b}.foot .val strong{display:block;color:#0f172a;font-size:9px;margin-top:2px}'
                + '.foot .qr-fallback{color:' + a2 + '}'
                + '</style>';
        },
        card: function (a, ctx) {
            return '<div class="card-container"><div class="card">'
                + '<div class="head"><img src="' + esc(ctx.logoUrl) + '" class="logo" alt=""><div class="title">REPÚBLICA DE MOÇAMBIQUE<br>' + esc(ctx.escolaNome) + '</div></div>'
                + '<div class="photo">' + photoTag(a) + '</div>'
                + '<div class="body"><div class="name">' + esc(nomeOf(a)) + '</div><div class="role">Estudante</div>'
                + '<div class="meta"><div><strong>Proc:</strong> ' + esc(processoOf(a)) + '</div><div><strong>Turma:</strong> ' + turmaOf(a) + '</div></div>'
                + socialRow(ctx) + '</div>'
                + '<div class="foot"><div class="val">Válido até:<strong>Dezembro ' + esc(ctx.anoLectivo) + '</strong></div>' + qrCell(a) + '</div>'
                + '</div></div>';
        }
    };

    // ───────────────────────────── CLASSIC ─────────────────────────────
    // Institucional sobrio (azul-marinho), foto rectangular. Oficial e conservador.
    T.classic = {
        meta: { id: 'classic', nome: 'Clássico', descricao: 'Institucional sóbrio, estilo documento oficial.' },
        css: function (ctx) {
            var a = accentOf(ctx);
            return styleOpen(ctx) + sheetCss(ctx)
                + '.card{width:220px;height:350px;background:#fff;border:1px solid #cbd5e1;border-radius:12px;overflow:hidden;box-shadow:0 4px ' + (ctx.batch ? '6px rgba(0,0,0,.05)' : '15px rgba(0,0,0,.1)') + ';display:flex;flex-direction:column;align-items:center;box-sizing:border-box}'
                + '.head{width:100%;height:85px;background:#0f172a;color:#fff;text-align:center;padding-top:12px;box-sizing:border-box}'
                + '.head img.logo{height:36px;width:36px;border-radius:50%;background:#fff;padding:2px;margin-bottom:4px;object-fit:contain}'
                + '.head .title{font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;line-height:1.2;padding:0 8px}'
                + '.photo{width:90px;height:110px;margin-top:15px;border-radius:8px;border:3px solid #fff;background:#f1f5f9;box-shadow:0 2px 4px rgba(0,0,0,.1);overflow:hidden;display:flex;align-items:center;justify-content:center}'
                + '.photo img{width:100%;height:100%;object-fit:cover}'
                + '.body{padding:12px 10px 8px;text-align:center;width:100%;box-sizing:border-box;flex:1}'
                + '.name{font-size:13px;font-weight:800;color:#0f172a;margin-bottom:8px;line-height:1.2;max-height:31px;overflow:hidden}'
                + '.role{display:inline-block;background:' + a + ';color:#fff;font-size:8px;font-weight:700;padding:4px 12px;border-radius:20px;letter-spacing:.5px;margin-bottom:10px;text-transform:uppercase}'
                + '.meta{font-size:10px;color:#475569;line-height:1.5}.meta strong{color:#1e293b}'
                + '.social{display:flex;flex-wrap:wrap;gap:3px 8px;justify-content:center;margin-top:8px}'
                + '.social .soc{display:inline-flex;align-items:center;gap:3px;font-size:7.5px;font-weight:600;color:#334155;white-space:nowrap}'
                + '.social .soc-i{color:' + a + '}'
                + '.foot{width:100%;background:#f8fafc;border-top:1px solid #e2e8f0;padding:10px 15px;box-sizing:border-box;display:flex;justify-content:space-between;align-items:center}'
                + '.foot .val{font-size:8px;color:#64748b}.foot .val strong{display:block;color:#0f172a;font-size:9px;margin-top:2px}'
                + '.foot .qr-fallback{color:#0f172a}'
                + '</style>';
        },
        card: function (a, ctx) {
            return '<div class="card-container"><div class="card">'
                + '<div class="head"><img src="' + esc(ctx.logoUrl) + '" class="logo" alt=""><div class="title">REPÚBLICA DE MOÇAMBIQUE<br>' + esc(ctx.escolaNome) + '</div></div>'
                + '<div class="photo">' + photoTag(a) + '</div>'
                + '<div class="body"><div class="name">' + esc(nomeOf(a)) + '</div><div class="role">Estudante</div>'
                + '<div class="meta"><div><strong>Proc:</strong> ' + esc(processoOf(a)) + '</div><div><strong>Turma:</strong> ' + turmaOf(a) + '</div></div>'
                + socialRow(ctx) + '</div>'
                + '<div class="foot"><div class="val">Válido até:<strong>Dezembro ' + esc(ctx.anoLectivo) + '</strong></div>' + qrCell(a) + '</div>'
                + '</div></div>';
        }
    };

    // ───────────────────────────── VIVID ─────────────────────────────
    // Faixa lateral de destaque a toda a altura, tipografia forte, foto grande.
    T.vivid = {
        meta: { id: 'vivid', nome: 'Vivid', descricao: 'Faixa lateral vibrante e visual ousado.' },
        css: function (ctx) {
            var a = accentOf(ctx), a2 = shade(a, -25), soft = shade(a, 46);
            return styleOpen(ctx) + sheetCss(ctx)
                + '.card{width:220px;height:350px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 10px 26px rgba(15,23,42,.18);display:flex;box-sizing:border-box;position:relative}'
                + '.band{width:14px;background:linear-gradient(180deg,' + a + ',' + a2 + ');flex:0 0 14px}'
                + '.inner{flex:1;display:flex;flex-direction:column;align-items:center;min-width:0}'
                + '.head{width:100%;padding:12px 12px 6px;display:flex;align-items:center;gap:8px;box-sizing:border-box;border-bottom:2px solid ' + soft + '}'
                + '.head img.logo{height:28px;width:28px;border-radius:7px;object-fit:contain;background:' + soft + ';padding:2px}'
                + '.head .title{font-size:7px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:' + a2 + ';line-height:1.2}'
                + '.photo{width:96px;height:96px;border-radius:14px;border:3px solid ' + soft + ';overflow:hidden;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin-top:12px}'
                + '.photo img{width:100%;height:100%;object-fit:cover}'
                + '.body{flex:1;width:100%;text-align:center;padding:8px 12px;box-sizing:border-box}'
                + '.name{font-size:14px;font-weight:800;color:#0f172a;line-height:1.1;max-height:32px;overflow:hidden}'
                + '.role{display:inline-block;margin-top:6px;background:' + a + ';color:#fff;font-size:8px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;padding:3px 12px;border-radius:6px}'
                + '.meta{margin-top:8px;font-size:10px;color:#475569;line-height:1.5;text-align:left;padding:0 6px}.meta strong{color:' + a2 + '}'
                + '.social{display:flex;flex-direction:column;gap:3px;margin-top:8px;padding:0 6px;text-align:left}'
                + '.social .soc{display:inline-flex;align-items:center;gap:4px;font-size:8px;font-weight:600;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
                + '.social .soc-i{color:' + a + '}'
                + '.foot{width:100%;padding:8px 12px;box-sizing:border-box;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #eef2f7}'
                + '.foot .val{font-size:8px;color:#64748b}.foot .val strong{display:block;color:' + a2 + ';font-size:9px;margin-top:2px}'
                + '.foot .qr-fallback{color:' + a2 + '}'
                + '</style>';
        },
        card: function (a, ctx) {
            return '<div class="card-container"><div class="card"><div class="band"></div><div class="inner">'
                + '<div class="head"><img src="' + esc(ctx.logoUrl) + '" class="logo" alt=""><div class="title">' + esc(ctx.escolaNome) + '</div></div>'
                + '<div class="photo">' + photoTag(a) + '</div>'
                + '<div class="body"><div class="name">' + esc(nomeOf(a)) + '</div><div class="role">Estudante</div>'
                + '<div class="meta"><div><strong>Proc:</strong> ' + esc(processoOf(a)) + '</div><div><strong>Turma:</strong> ' + turmaOf(a) + '</div></div>'
                + socialRow(ctx) + '</div>'
                + '<div class="foot"><div class="val">Válido até:<strong>Dez. ' + esc(ctx.anoLectivo) + '</strong></div>' + qrCell(a) + '</div>'
                + '</div></div></div>';
        }
    };

    // ───────────────────────────── MINIMAL ─────────────────────────────
    // Limpo, muito espaco em branco, linha de destaque fina. Discreto e elegante.
    T.minimal = {
        meta: { id: 'minimal', nome: 'Minimal', descricao: 'Limpo e elegante, com linha de destaque fina.' },
        css: function (ctx) {
            var a = accentOf(ctx);
            return styleOpen(ctx) + sheetCss(ctx)
                + '.card{width:220px;height:350px;background:#fff;border:1px solid #e7ebf0;border-radius:14px;overflow:hidden;box-shadow:0 6px 18px rgba(15,23,42,.08);display:flex;flex-direction:column;align-items:center;box-sizing:border-box}'
                + '.bar{width:100%;height:4px;background:' + a + ';flex:0 0 4px}'
                + '.head{width:100%;display:flex;align-items:center;gap:8px;padding:12px 14px 4px;box-sizing:border-box}'
                + '.head img.logo{height:26px;width:26px;border-radius:6px;object-fit:contain}'
                + '.head .title{font-size:7.5px;font-weight:700;letter-spacing:.3px;color:#64748b;text-transform:uppercase;line-height:1.2}'
                + '.photo{width:88px;height:88px;border-radius:12px;overflow:hidden;background:#f3f5f8;display:flex;align-items:center;justify-content:center;margin-top:14px}'
                + '.photo img{width:100%;height:100%;object-fit:cover}'
                + '.body{flex:1;width:100%;text-align:center;padding:10px 14px;box-sizing:border-box}'
                + '.name{font-size:13px;font-weight:700;color:#0f172a;line-height:1.2;max-height:31px;overflow:hidden}'
                + '.role{font-size:8px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:' + a + ';margin-top:5px}'
                + '.meta{margin-top:10px;font-size:10px;color:#64748b;line-height:1.6}.meta strong{color:#334155;font-weight:600}'
                + '.social{display:flex;flex-wrap:wrap;gap:3px 10px;justify-content:center;margin-top:8px}'
                + '.social .soc{display:inline-flex;align-items:center;gap:3px;font-size:7.5px;font-weight:500;color:#64748b;white-space:nowrap}'
                + '.social .soc-i{color:' + a + '}'
                + '.foot{width:100%;padding:9px 14px;box-sizing:border-box;display:flex;justify-content:space-between;align-items:center;border-top:1px solid #f1f4f8}'
                + '.foot .val{font-size:7.5px;color:#94a3b8}.foot .val strong{display:block;color:#475569;font-size:8.5px;margin-top:2px;font-weight:600}'
                + '.foot .qr{width:38px;height:38px}.foot .qr-fallback{color:#475569}'
                + '</style>';
        },
        card: function (a, ctx) {
            return '<div class="card-container"><div class="card"><div class="bar"></div>'
                + '<div class="head"><img src="' + esc(ctx.logoUrl) + '" class="logo" alt=""><div class="title">' + esc(ctx.escolaNome) + '</div></div>'
                + '<div class="photo">' + photoTag(a) + '</div>'
                + '<div class="body"><div class="name">' + esc(nomeOf(a)) + '</div><div class="role">Estudante</div>'
                + '<div class="meta"><div><strong>Proc.</strong> ' + esc(processoOf(a)) + '</div><div>' + turmaOf(a) + '</div></div>'
                + socialRow(ctx) + '</div>'
                + '<div class="foot"><div class="val">Válido até<strong>Dezembro ' + esc(ctx.anoLectivo) + '</strong></div>' + qrCell(a) + '</div>'
                + '</div></div>';
        }
    };

    function resolve(id) { return T[id] || T.aurora; }

    function buildDocument(students, ctx) {
        ctx = ctx || {};
        var tpl = resolve(ctx.template);
        var list = Array.isArray(students) ? students : [students];
        var body = '';
        for (var i = 0; i < list.length; i++) { body += tpl.card(list[i] || {}, ctx); }
        var title = ctx.batch ? 'Imprimir Cartões' : 'Imprimir Cartão';
        return '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(title) + '</title>'
            + tpl.css(ctx) + '</head><body><div class="sheet">' + body + '</div></body></html>';
    }

    function list() {
        return Object.keys(T).map(function (id) { return T[id].meta; });
    }

    window.SigeCrachaTemplates = { resolve: resolve, buildDocument: buildDocument, list: list, ids: function () { return Object.keys(T); } };
})();
