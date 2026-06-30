/* SIGE SoftGenial - Financeiro Core UI
 * v12.15.12: marcador visual/UX (sem observador de mutacoes, sem portal/move-DOM,
 *   sem interceptar submissao/clique financeiro, sem execucao dinamica).
 * v12.25.0: Pagamentos - seccoes colapsaveis + modal acima da barra lateral.
 * v12.26.0: Config (Precos e Servicos) - seccoes colapsaveis + modal.
 * v12.29.0: lembra o estado (aberto/fechado) das seccoes na sessao, para nao
 *   reabrir tudo apos gravar/recarregar. So visual; nao toca em valores/formulas,
 *   nao intercepta submissao/cliques financeiros nem move nos do formulario.
 */
(function () {
  'use strict';
  if (!document || !document.body) return;
  document.body.setAttribute('data-sige-financeiro-core-design', '12.29.0');

  var body = document.body;

  // Persistencia segura do estado das seccoes (sessionStorage; pode falhar em
  // modo privado, por isso try/catch). null = sem registo.
  function lerEstado(key) {
    if (!key) return null;
    try { var v = window.sessionStorage.getItem(key); return v === null ? null : (v === '1'); }
    catch (e) { return null; }
  }
  function guardarEstado(key, aberto) {
    if (!key) return;
    try { window.sessionStorage.setItem(key, aberto ? '1' : '0'); } catch (e) {}
  }
  function chaveSeccao(prefixo, header) {
    var t = (header.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60);
    return 'sigeCol:' + prefixo + ':' + t;
  }

  // Eleva o conteudo acima da barra lateral enquanto ha modal visivel (so leitura
  // do estado; nunca intercepta o fluxo financeiro). Usado em pagamentos.
  function sincronizarModalLift() {
    var aberto = document.querySelector('.sige-modal[style*="display: flex"], .sige-modal[style*="display:flex"]');
    body.classList.toggle('sige-paypro-modal-open', !!aberto);
  }

  // Torna um cartao colapsavel: cabecalho clicavel + seta; nao colapsa quando se
  // clica num controlo dentro do cabecalho. Acessivel por teclado. Lembra o estado.
  function tornarColapsavel(card, headerSel, bodySel, headClass, cardCollapsedClass, chevClass, aberto, prefixo) {
    var header = card.querySelector(headerSel);
    var content = card.querySelector(bodySel);
    if (!header || !content || header.getAttribute('data-sige-collapsible') === '1') return;
    header.setAttribute('data-sige-collapsible', '1');
    header.classList.add(headClass);
    header.setAttribute('role', 'button');
    header.setAttribute('tabindex', '0');

    var chev = document.createElement('span');
    chev.className = chevClass;
    chev.setAttribute('aria-hidden', 'true');
    header.appendChild(chev);

    var key = prefixo ? chaveSeccao(prefixo, header) : '';
    function definir(open, persistir) {
      card.classList.toggle(cardCollapsedClass, !open);
      content.style.display = open ? '' : 'none';
      header.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (persistir) guardarEstado(key, open);
    }
    var lembrado = lerEstado(key);
    definir(lembrado === null ? aberto : lembrado, false);
    header.addEventListener('click', function (e) {
      if (e.target.closest('a,button,input,select,label,textarea')) return;
      definir(card.classList.contains(cardCollapsedClass), true);
    });
    header.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); definir(card.classList.contains(cardCollapsedClass), true); }
    });
  }

  function run() {
    // ── Pagamentos ──────────────────────────────────────────────────────────
    if (body.classList.contains('sige-view-financeiro-pagamentos')) {
      sincronizarModalLift();
      document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sige-act="sigeCloseModal"], [data-sige-act="sigeOpenModal"], .sige-modal-close')) {
          setTimeout(sincronizarModalLift, 30);
        }
      }, true);
      document.querySelectorAll('.sg-paypro-operation-card').forEach(function (card, idx) {
        // Dividas Actuais aberta; restantes fechadas; abre se ja houver seleccao.
        var aberto = idx === 0 || !!card.querySelector('input:checked');
        tornarColapsavel(card, '.sige-card-header', '.sige-card-body', 'sg-paypro-collapse-head', 'sg-paypro-collapsed', 'sg-paypro-collapse-chev', aberto, 'pag');
      });
    }

    // ── Config (Precos e Servicos) ─────────────────────────────────────────
    if (body.classList.contains('sige-view-financeiro-config')) {
      // Pagina de definicoes: as seccoes de regras comecam FECHADAS (pagina mais
      // curta); a "Lista de Servicos" (conteudo principal) fica ABERTA. Exclui os
      // .fc-card dentro de modais (ex.: formulario de servico).
      document.querySelectorAll('.sg-fincfg-wrap .fc-card').forEach(function (card) {
        if (card.closest('.sg-fincfg-service-modal, .sg-fincfg-modal')) return;
        var aberto = card.classList.contains('sg-fincfg-services-list');
        tornarColapsavel(card, '.fc-card-header', '.fc-card-body', 'sg-fincfg-collapse-head', 'sg-fincfg-collapsed', 'sg-fincfg-collapse-chev', aberto, 'cfg');
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
