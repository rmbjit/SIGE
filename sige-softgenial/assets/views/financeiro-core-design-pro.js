/* SIGE SoftGenial - Financeiro Core UI
 * v12.15.12: marcador visual/UX (sem observador de mutacoes, sem portal/move-DOM,
 *   sem interceptar submissao/clique financeiro, sem execucao dinamica).
 * v12.25.0: enriquecimento SO VISUAL do ecra de Pagamentos:
 *   - seccoes colapsaveis (encolhe a pagina longa) - nao toca em valores/formulas;
 *   - eleva o conteudo acima da barra lateral enquanto ha modal aberto.
 *   Nao intercepta cliques/submissao financeira nem move nos do formulario.
 */
(function () {
  'use strict';
  if (!document || !document.body) return;
  document.body.setAttribute('data-sige-financeiro-core-design', '12.25.0');

  var isPagamentos = document.body.classList.contains('sige-view-financeiro-pagamentos');
  if (!isPagamentos) return;

  // ── Modal acima da barra lateral ──────────────────────────────────────────
  // .sg-app-content e contexto de empilhamento abaixo da sidebar; quando ha um
  // .sige-modal visivel (incl. os modais de sucesso renderizados pelo servidor),
  // marcamos o body para o CSS elevar o conteudo. So LEITURA do estado; nunca
  // intercepta o fluxo de pagamento.
  function sincronizarModalLift() {
    var aberto = document.querySelector('.sige-modal[style*="display: flex"], .sige-modal[style*="display:flex"]');
    document.body.classList.toggle('sige-paypro-modal-open', !!aberto);
  }
  sincronizarModalLift();
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-sige-act="sigeCloseModal"], [data-sige-act="sigeOpenModal"], .sige-modal-close')) {
      setTimeout(sincronizarModalLift, 30);
    }
  }, true);

  // ── Seccoes colapsaveis ───────────────────────────────────────────────────
  function aplicarColapso() {
    var cards = document.querySelectorAll('.sg-paypro-operation-card');
    if (!cards.length) return;
    cards.forEach(function (card, idx) {
      var header = card.querySelector('.sige-card-header');
      var body = card.querySelector('.sige-card-body');
      if (!header || !body || header.getAttribute('data-sige-collapsible') === '1') return;
      header.setAttribute('data-sige-collapsible', '1');
      header.classList.add('sg-paypro-collapse-head');
      header.setAttribute('role', 'button');
      header.setAttribute('tabindex', '0');

      var chev = document.createElement('span');
      chev.className = 'sg-paypro-collapse-chev';
      chev.setAttribute('aria-hidden', 'true');
      header.appendChild(chev);

      function definir(aberto) {
        card.classList.toggle('sg-paypro-collapsed', !aberto);
        body.style.display = aberto ? '' : 'none';
        header.setAttribute('aria-expanded', aberto ? 'true' : 'false');
      }
      // 1.a seccao (Dividas Actuais) aberta; restantes fechadas. Defensivo: se
      // alguma seccao ja tem algo seleccionado, abre-a para nao esconder escolhas.
      var temSeleccao = !!card.querySelector('input:checked');
      definir(idx === 0 || temSeleccao);

      header.addEventListener('click', function (e) {
        // nao colapsar quando se clica num controlo dentro do cabecalho
        if (e.target.closest('a,button,input,select,label,textarea')) return;
        definir(card.classList.contains('sg-paypro-collapsed'));
      });
      header.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); definir(card.classList.contains('sg-paypro-collapsed')); }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', aplicarColapso);
  } else {
    aplicarColapso();
  }
})();
