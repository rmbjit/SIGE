(function(){
    var cfg = window.SIGE_PRESENCAS_CFG || {};
    var ajaxurl = cfg.ajaxurl || '';
    var nonce = cfg.nonce || '';
    var podeEditar = !!cfg.podeEditar;
    var fastPath = !!cfg.fastPath;
    var meses = cfg.meses || [];
    var ano = parseInt(cfg.ano, 10) || new Date().getFullYear();
    var mes = parseInt(cfg.mes, 10) || (new Date().getMonth() + 1);
    var abrirModal = null;
    var turmaSel = document.getElementById('sgPresTurma');
    var tabela = document.getElementById('sgPresTabela');
    var vazio = document.getElementById('sgPresVazio');
    var label = document.getElementById('sgPresMesLabel');
    var btnCsv = document.getElementById('sgPresCsv');
    var btnPrint = document.getElementById('sgPresPrint');
    var dadosActuais = null;
    var celulaActiva = null;

    // Pagina do relatorio oficial: so existe o botao Imprimir; o resto e do mapa.
    var relPrint = document.getElementById('sgPresRelPrint');
    if (relPrint) relPrint.addEventListener('click', function(){ window.print(); });
    if (!turmaSel) { return; }

    // ── Feedback declarativo (nunca dialogos nativos) ─────────────────────────
    function toast(msg, tipo){
        if (window.sigeUi && typeof window.sigeUi.toast === 'function'){ window.sigeUi.toast(msg, tipo || 'info'); }
        anunciar(msg);
    }
    function anunciar(msg){
        var s = document.getElementById('sgPresStatus');
        if (!s) return;
        s.textContent = '';
        void s.offsetWidth; // forca o leitor de ecra a reanunciar
        s.textContent = msg;
    }

    function syncLabel(){ label.textContent = meses[mes] + ' de ' + ano; }
    function mudaMes(delta){ mes += delta; if (mes < 1){ mes = 12; ano--; } if (mes > 12){ mes = 1; ano++; } syncLabel(); carregar(); }
    document.getElementById('sgPresAnt').addEventListener('click', function(){ mudaMes(-1); });
    document.getElementById('sgPresSeg').addEventListener('click', function(){ mudaMes(1); });
    turmaSel.addEventListener('change', carregar);

    function syncRelLink(){
        var rel = document.getElementById('sgPresRel');
        if (!rel) return;
        var turma = parseInt(turmaSel.value || '0', 10);
        if (!turma){ rel.setAttribute('aria-disabled','true'); rel.style.pointerEvents='none'; rel.style.opacity='.5'; rel.href='#'; return; }
        rel.removeAttribute('aria-disabled'); rel.style.pointerEvents=''; rel.style.opacity='';
        rel.href = '?page=sige-app&view=presencas&relatorio=1&turma_id=' + turma + '&ano=' + ano + '&mes=' + mes;
    }

    function carregar(){
        syncRelLink();
        if (fastPath) limparSeleccao();
        var turma = parseInt(turmaSel.value || '0', 10);
        if (!turma){ tabela.style.display='none'; vazio.style.display=''; vazio.textContent='Escolha a turma para carregar o mapa do mês.'; btnCsv.disabled = btnPrint.disabled = true; return; }
        vazio.style.display=''; vazio.textContent='A carregar o mapa...'; tabela.style.display='none';
        var fd = new FormData();
        fd.append('action','sige_presencas_grid'); fd.append('_wpnonce',nonce);
        fd.append('turma_id',turma); fd.append('ano',ano); fd.append('mes',mes);
        fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
          .then(function(r){return r.json();})
          .then(function(j){
              if(!j || !j.success){ vazio.textContent = (j && j.data) ? j.data : 'Falha ao carregar.'; return; }
              dadosActuais = j.data; render(j.data);
          })
          .catch(function(){ vazio.textContent='Falha de ligação. Tente novamente.'; });
    }

    function render(d){
        if (!d.alunos || !d.alunos.length){ vazio.textContent='Sem alunos com matrícula activa nesta turma.'; btnCsv.disabled=btnPrint.disabled=true; return; }
        var html = '<thead><tr><th style="text-align:left;">Aluno</th>';
        d.dias.forEach(function(dd, ci){ html += '<th class="sg-cel-h" data-dia="'+dd.dia+'" data-c="'+ci+'" title="'+dd.semana+(fastPath?' (clique para a coluna)':'')+'">'+dd.dia+'<br><span style="font-weight:400;font-size:10px;opacity:.8;">'+dd.semana+'</span></th>'; });
        html += '<th class="sg-fim">P</th><th class="sg-fim">AT</th><th class="sg-fim">F</th><th class="sg-fim">J</th><th class="sg-fim">%</th></tr></thead><tbody>';
        d.alunos.forEach(function(a, ri){
            html += '<tr data-aluno="'+a.id+'"><td class="sg-nome'+(fastPath?' sg-nome-sel':'')+'" data-aluno="'+a.id+'" data-r="'+ri+'" title="Processo '+(a.processo||'')+(fastPath?' (clique para a linha)':'')+'">'+escapeHtml(a.nome)+'</td>';
            d.dias.forEach(function(dd, ci){
                var e = a.estados[dd.dia] || '';
                var hora = a.horas && a.horas[dd.dia] ? ' title="1ª entrada '+a.horas[dd.dia]+(a.motivos&&a.motivos[dd.dia]?' | '+escapeHtml(a.motivos[dd.dia]):'')+'"' : (a.motivos&&a.motivos[dd.dia]?' title="'+escapeHtml(a.motivos[dd.dia])+'"':'');
                var cls = e ? 'sgp sgp-'+e : 'sgp sgp-vazio';
                var ed = e ? ' sg-cel-ed' : '';
                var tab = (fastPath && e) ? ' tabindex="-1"' : '';
                html += '<td class="sg-cel'+ed+'" data-aluno="'+a.id+'" data-nome="'+escapeHtml(a.nome)+'" data-data="'+dd.data+'" data-dia="'+dd.dia+'" data-r="'+ri+'" data-c="'+ci+'"'+hora+tab+'><b class="'+cls+'">'+(e||'·')+'</b></td>';
            });
            var c = a.totais.contagens;
            html += '<td class="sg-tot">'+(c.P+c.PM)+'</td><td class="sg-tot">'+c.AT+'</td><td class="sg-tot">'+c.F+'</td><td class="sg-tot">'+c.J+'</td><td class="sg-tot">'+a.totais.pct_presenca+'%</td></tr>';
        });
        html += '</tbody>';
        tabela.innerHTML = html; tabela.style.display=''; vazio.style.display='none';
        btnCsv.disabled = btnPrint.disabled = false;
        if (podeEditar && !fastPath && abrirModal){
            tabela.querySelectorAll('td.sg-cel').forEach(function(td){
                td.addEventListener('click', function(){ if (td.querySelector('b').textContent !== '·') abrirModal(td); });
            });
        } else if (fastPath){
            initRoving();
        }
    }

    function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

    // ── Remendo de totais (vindos sempre do servidor; o cliente nunca recalcula) ─
    function patchCelula(td, estado){
        var b = td.querySelector('b');
        if (!b) return;
        b.textContent = estado || '·';
        b.className = estado ? 'sgp sgp-'+estado : 'sgp sgp-vazio';
        if (fastPath){
            if (estado){ td.classList.add('sg-cel-ed'); if (!td.hasAttribute('tabindex')) td.setAttribute('tabindex','-1'); }
        }
    }
    function patchTotais(tr, totais){
        var tots = tr.querySelectorAll('td.sg-tot');
        if (tots.length < 5 || !totais) return;
        var c = totais.contagens;
        tots[0].textContent = (c.P + c.PM);
        tots[1].textContent = c.AT;
        tots[2].textContent = c.F;
        tots[3].textContent = c.J;
        tots[4].textContent = totais.pct_presenca + '%';
    }
    function patchLinha(alunoId, linha){
        if (!linha) return;
        var tr = tabela.querySelector('tr[data-aluno="'+alunoId+'"]');
        if (!tr) return;
        if (linha.estados){
            tr.querySelectorAll('td.sg-cel').forEach(function(td){
                var dia = td.dataset.dia;
                if (linha.estados[dia] !== undefined) patchCelula(td, linha.estados[dia] || '');
            });
        }
        patchTotais(tr, linha.totais);
    }

    // ── Escrita de uma celula (caminho rapido e atalho de teclado) ────────────
    function aplicarUma(td, estado, motivo){
        var fd = new FormData();
        fd.append('action','sige_presencas_marcar'); fd.append('_wpnonce',nonce);
        fd.append('aluno_id', td.dataset.aluno); fd.append('data', td.dataset.data);
        fd.append('estado', estado); fd.append('motivo', motivo || '');
        fd.append('ano', ano); fd.append('mes', mes);
        fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
          .then(function(r){return r.json();})
          .then(function(j){
              if(!j || !j.success){ toast((j&&j.data)||'Não foi possível guardar.', 'erro'); return; }
              if (j.data.linha) patchLinha(td.dataset.aluno, j.data.linha);
              else patchCelula(td, j.data.estado || '');
              toast('Presença actualizada.', 'ok');
          })
          .catch(function(){ toast('Falha de ligação.', 'erro'); });
    }

    // ── Escrita em lote (uma ida ao servidor para N celulas) ──────────────────
    function aplicarLote(estado){
        var itens = [];
        seleccao.forEach(function(td){ itens.push({aluno_id: parseInt(td.dataset.aluno,10), data: td.dataset.data, estado: estado}); });
        if (!itens.length){ toast('Nenhuma célula seleccionada.', 'aviso'); return; }
        var motivoEl = document.getElementById('sgPresBarMotivo');
        var motivo = motivoEl ? motivoEl.value : '';
        var fd = new FormData();
        fd.append('action','sige_presencas_marcar_lote'); fd.append('_wpnonce',nonce);
        fd.append('itens', JSON.stringify(itens)); fd.append('motivo', motivo);
        fd.append('ano', ano); fd.append('mes', mes);
        fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
          .then(function(r){return r.json();})
          .then(function(j){
              if(!j || !j.success){ toast((j&&j.data)||'Não foi possível aplicar.', 'erro'); return; }
              var linhas = j.data.linhas || {};
              Object.keys(linhas).forEach(function(aid){ patchLinha(aid, linhas[aid]); });
              var n = j.data.aplicados || itens.length;
              limparSeleccao();
              toast('Aplicado a ' + n + (n===1 ? ' célula.' : ' células.'), 'ok');
          })
          .catch(function(){ toast('Falha de ligação.', 'erro'); });
    }

    // ── Selecção (lote): conjunto de celulas realcadas ────────────────────────
    var seleccao = new Set();
    var ancora = null; // {r,c}
    function marcarSel(td, on){ if (on){ td.classList.add('sg-cel-sel'); seleccao.add(td); } else { td.classList.remove('sg-cel-sel'); seleccao.delete(td); } }
    function limparVisualSel(){ seleccao.forEach(function(td){ td.classList.remove('sg-cel-sel'); }); seleccao.clear(); }
    function limparSeleccao(){ limparVisualSel(); ancora = null; syncBar(); }
    function syncBar(){
        var bar = document.getElementById('sgPresBar');
        if (!bar) return;
        var n = seleccao.size;
        if (n >= 2){ bar.hidden = false; var cEl = document.getElementById('sgPresBarCount'); if (cEl) cEl.textContent = n + ' células'; }
        else { bar.hidden = true; }
        anunciar(n + (n===1 ? ' célula seleccionada' : ' células seleccionadas'));
    }
    function selRange(r2, c2){
        if (!ancora) return;
        var rmin=Math.min(ancora.r,r2), rmax=Math.max(ancora.r,r2), cmin=Math.min(ancora.c,c2), cmax=Math.max(ancora.c,c2);
        limparVisualSel();
        for (var r=rmin;r<=rmax;r++){ for (var c=cmin;c<=cmax;c++){ var cell=tabela.querySelector('td.sg-cel-ed[data-r="'+r+'"][data-c="'+c+'"]'); if (cell) marcarSel(cell, true); } }
        syncBar();
    }
    function selRow(tr){ limparVisualSel(); tr.querySelectorAll('td.sg-cel-ed').forEach(function(td){ marcarSel(td, true); }); ancora=null; syncBar(); }
    function selCol(dia){ limparVisualSel(); tabela.querySelectorAll('td.sg-cel-ed[data-dia="'+dia+'"]').forEach(function(td){ marcarSel(td, true); }); ancora=null; syncBar(); }

    // ── Foco navegavel (roving tabindex) ──────────────────────────────────────
    var celFocada = null;
    function focarCelula(td){ if (celFocada && celFocada !== td) celFocada.setAttribute('tabindex','-1'); td.setAttribute('tabindex','0'); celFocada = td; td.focus(); }
    function initRoving(){ celFocada = null; var first = tabela.querySelector('td.sg-cel-ed'); if (first){ first.setAttribute('tabindex','0'); celFocada = first; } }
    function vizinhoEditavel(nr, nc, k){
        for (var i=0;i<80;i++){
            if (nr < 0 || nc < 0) return null;
            var cell = tabela.querySelector('td[data-r="'+nr+'"][data-c="'+nc+'"]');
            if (!cell) return null;
            if (cell.classList.contains('sg-cel-ed')) return cell;
            if (k==='ArrowRight') nc++; else if (k==='ArrowLeft') nc--; else if (k==='ArrowUp') nr--; else nr++;
        }
        return null;
    }

    // ── Popover inline (substitui o modal por celula no caminho rapido) ───────
    var pop = document.getElementById('sgPresPop');
    function posicionarPop(td){
        var rect = td.getBoundingClientRect();
        pop.style.visibility='hidden'; pop.hidden=false;
        var pw = pop.offsetWidth, ph = pop.offsetHeight;
        var vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight;
        var left = window.scrollX + rect.left;
        var maxLeft = window.scrollX + vw - pw - 8;
        if (left > maxLeft) left = maxLeft;
        if (left < window.scrollX + 8) left = window.scrollX + 8;
        var top = window.scrollY + rect.bottom + 6;
        if (rect.bottom + ph + 8 > vh) top = window.scrollY + rect.top - ph - 6;
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
        pop.style.visibility='';
    }
    function abrirPopover(td){
        if (!pop) return;
        celulaActiva = td;
        var sub = document.getElementById('sgPresPopSub');
        if (sub) sub.textContent = td.dataset.nome + ' · ' + td.dataset.data;
        var mot = document.getElementById('sgPresPopMotivo'); if (mot) mot.value='';
        posicionarPop(td);
        var first = pop.querySelector('.sg-pres-pop-op'); if (first) first.focus();
    }
    function fecharPopover(){ if (pop){ pop.hidden = true; } celulaActiva = null; }

    function initFastPath(){
        // Clique: celula (simples/Shift/Ctrl), nome (linha), cabecalho do dia (coluna).
        tabela.addEventListener('click', function(ev){
            var cel = ev.target.closest ? ev.target.closest('td.sg-cel-ed') : null;
            if (cel){
                var r = parseInt(cel.dataset.r,10), c = parseInt(cel.dataset.c,10);
                if (ev.shiftKey && ancora){ ev.preventDefault(); selRange(r,c); return; }
                if (ev.ctrlKey || ev.metaKey){ ev.preventDefault(); marcarSel(cel, !seleccao.has(cel)); ancora={r:r,c:c}; syncBar(); return; }
                limparSeleccao(); ancora={r:r,c:c}; focarCelula(cel); abrirPopover(cel); return;
            }
            var nome = ev.target.closest ? ev.target.closest('td.sg-nome') : null;
            if (nome){ var tr = nome.closest('tr'); if (tr) selRow(tr); return; }
            var hd = ev.target.closest ? ev.target.closest('th.sg-cel-h') : null;
            if (hd && hd.dataset.dia){ selCol(hd.dataset.dia); return; }
        });

        // Teclado: setas navegam, Enter/Espaco abre, j/p/d/a aplicam, Esc fecha.
        tabela.addEventListener('keydown', function(ev){
            var cel = ev.target.closest ? ev.target.closest('td.sg-cel-ed') : null;
            if (!cel) return;
            var r = parseInt(cel.dataset.r,10), c = parseInt(cel.dataset.c,10);
            var k = ev.key;
            if (k==='ArrowRight'||k==='ArrowLeft'||k==='ArrowUp'||k==='ArrowDown'){
                ev.preventDefault();
                var nr=r, nc=c;
                if (k==='ArrowRight') nc++; else if (k==='ArrowLeft') nc--; else if (k==='ArrowUp') nr--; else nr++;
                var alvo = vizinhoEditavel(nr, nc, k);
                if (alvo) focarCelula(alvo);
                return;
            }
            if (k==='Enter'||k===' '||k==='Spacebar'){ ev.preventDefault(); limparSeleccao(); ancora={r:r,c:c}; abrirPopover(cel); return; }
            if (k==='Escape'){ fecharPopover(); limparSeleccao(); return; }
            var atalho = {j:'falta_justificada', p:'presente_manual', d:'dispensado', a:'auto'}[k.toLowerCase()];
            if (atalho){ ev.preventDefault(); aplicarUma(cel, atalho, ''); return; }
        });

        // Opcoes do popover.
        if (pop){
            pop.querySelectorAll('.sg-pres-pop-op').forEach(function(btn){
                btn.addEventListener('click', function(){ if (!celulaActiva) return; var mot=document.getElementById('sgPresPopMotivo'); aplicarUma(celulaActiva, btn.dataset.estado, mot?mot.value:''); fecharPopover(); });
            });
            pop.addEventListener('keydown', function(ev){ if (ev.key==='Escape'){ fecharPopover(); if (celFocada) celFocada.focus(); } });
        }

        // Barra de lote.
        var bar = document.getElementById('sgPresBar');
        if (bar){
            bar.querySelectorAll('.sg-pres-bar-op').forEach(function(btn){ btn.addEventListener('click', function(){ aplicarLote(btn.dataset.estado); }); });
            var limpar = document.getElementById('sgPresBarLimpar');
            if (limpar) limpar.addEventListener('click', function(){ limparSeleccao(); });
        }

        // Fechar popover ao clicar fora.
        document.addEventListener('mousedown', function(ev){ if (!pop || pop.hidden) return; if (pop.contains(ev.target)) return; fecharPopover(); });
        // Reposicionar/fechar em scroll e redimensionamento.
        window.addEventListener('resize', function(){ if (pop && !pop.hidden) fecharPopover(); });
    }

    // ── Modal classico (caminho OFF e rollback), sem dialogos nativos ─────────
    var modal = document.getElementById('sgPresModal');
    function initModal(){
        abrirModal = function(td){
            celulaActiva = td;
            document.getElementById('sgPresModalSub').textContent = td.dataset.nome + ' · ' + td.dataset.data;
            modal.querySelectorAll('input[name="sgPresEstado"]').forEach(function(r){ r.checked = false; });
            document.getElementById('sgPresMotivo').value = '';
            modal.style.display = 'flex';
        };
        document.getElementById('sgPresCancelar').addEventListener('click', function(){ modal.style.display='none'; });
        modal.addEventListener('click', function(ev){ if (ev.target === modal) modal.style.display='none'; });
        document.getElementById('sgPresGuardar').addEventListener('click', function(){
            var op = modal.querySelector('input[name="sgPresEstado"]:checked');
            if (!op){ toast('Escolha uma opção.', 'aviso'); return; }
            var fd = new FormData();
            fd.append('action','sige_presencas_marcar'); fd.append('_wpnonce',nonce);
            fd.append('aluno_id',celulaActiva.dataset.aluno); fd.append('data',celulaActiva.dataset.data);
            fd.append('estado',op.value); fd.append('motivo',document.getElementById('sgPresMotivo').value);
            fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
              .then(function(r){return r.json();})
              .then(function(j){
                  if(!j || !j.success){ toast((j&&j.data)||'Não foi possível guardar.', 'erro'); return; }
                  modal.style.display='none';
                  carregar(); // recarrega para refazer totais
              })
              .catch(function(){ toast('Falha de ligação.', 'erro'); });
        });
    }

    if (podeEditar && fastPath){ initFastPath(); }
    else if (podeEditar && modal){ initModal(); }

    btnPrint.addEventListener('click', function(){ window.print(); });
    btnCsv.addEventListener('click', function(){
        if (!dadosActuais) return;
        var linhas = [['Aluno'].concat(dadosActuais.dias.map(function(d){return d.dia;})).concat(['P','AT','F','J','% Presença']).join(';')];
        dadosActuais.alunos.forEach(function(a){
            var c = a.totais.contagens;
            var row = [a.nome.replace(/;/g,',')];
            dadosActuais.dias.forEach(function(d){ row.push(a.estados[d.dia]||''); });
            row.push(c.P+c.PM, c.AT, c.F, c.J, a.totais.pct_presenca+'%');
            linhas.push(row.join(';'));
        });
        var blob = new Blob(['\ufeff'+linhas.join('\n')], {type:'text/csv;charset=utf-8;'});
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'presencas-' + (turmaSel.options[turmaSel.selectedIndex].text||'turma').replace(/\s+/g,'_') + '-' + ano + '-' + String(mes).padStart(2,'0') + '.csv';
        document.body.appendChild(a); a.click(); a.remove();
    });

    syncLabel();
    syncRelLink();
})();
