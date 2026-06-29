/* SIGE SoftGenial v12.14.4 - Document Actions
 * External, CSP-safe handlers for print/close/back controls used by document windows.
 */
(function () {
  'use strict';
  function closest(el, sel) { return el && el.closest ? el.closest(sel) : null; }
  function closeOrBack() {
    try { window.close(); } catch (e) {}
    if (window.history && window.history.length > 1) {
      try { window.history.back(); } catch (e2) {}
    }
  }
  document.addEventListener('click', function (ev) {
    var print = closest(ev.target, '[data-sige-print]');
    if (print) { ev.preventDefault(); window.print(); return; }
    var closeBack = closest(ev.target, '[data-sige-close-back]');
    if (closeBack) { ev.preventDefault(); closeOrBack(); return; }
    var close = closest(ev.target, '[data-sige-close]');
    if (close) { ev.preventDefault(); try { window.close(); } catch (e) {} return; }
    var back = closest(ev.target, '[data-sige-back]');
    if (back) { ev.preventDefault(); if (window.history) window.history.back(); }
  }, false);
})();
