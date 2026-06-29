<?php
/**
 * SIGE SoftGenial - WhatsApp Helper
 * Ficheiro: includes/whatsapp-helper.php
 *
 * Corrige o envio de nonce no formulário de teste WhatsApp.
 * Injeta JS que sobrescreve qualquer handler de botão de teste existente
 * para usar o nonce correcto do sigeAjax global.
 */
if (!defined('ABSPATH')) exit;

// ── Injectar JS correctivo em todas as páginas SIGE ──────────────────────────
add_action('admin_footer', function () {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'sige-app') === false) return;
    ?>
    <script <?php echo sige_csp_script_attr(); ?>>
    (function () {
        'use strict';

        // sigeAjax é injectado pelo wp_localize_script no admin_enqueue_scripts
        // Garante que o nonce correcto é usado em todos os pedidos de teste WPP
        var ajaxUrl = (typeof sigeAjax !== 'undefined') ? sigeAjax.url : ajaxurl;
        var nonce   = (typeof sigeAjax !== 'undefined') ? sigeAjax.nonce : '';

        // ── Corrigir botão de teste WhatsApp ─────────────────────────────────
        // Aguardar DOM completo e depois interceptar qualquer botão de teste WPP
        document.addEventListener('DOMContentLoaded', function () {
            interceptarBotoesTeste();
        });
        // Segundo fallback para conteúdo carregado dinamicamente
        setTimeout(interceptarBotoesTeste, 800);
        setTimeout(interceptarBotoesTeste, 2000);

        function interceptarBotoesTeste() {
            // Procurar botões de teste por ID, classe ou texto
            var selectors = [
                '#btn-testar-wpp',
                '#btnTestarWpp',
                '.btn-testar-wpp',
                '[data-action="testar_wpp"]',
            ];

            selectors.forEach(function (sel) {
                var btn = document.querySelector(sel);
                if (btn && !btn.dataset.sigeFixed) {
                    btn.dataset.sigeFixed = '1';
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        fazerTesteDirecto(btn);
                    }, true); // capture=true para correr antes do handler original
                }
            });

            // Também procurar por texto do botão
            document.querySelectorAll('button, input[type="button"]').forEach(function (el) {
                if (el.dataset.sigeFixed) return;
                var txt = (el.textContent || el.value || '').toLowerCase();
                if (txt.includes('testar') && (txt.includes('wpp') || txt.includes('whatsapp') || txt.includes('mensagem'))) {
                    el.dataset.sigeFixed = '1';
                    el.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        fazerTesteDirecto(el);
                    }, true);
                }
            });
        }

        function fazerTesteDirecto(btn) {
            // Recolher campos do formulário mais próximo
            var form   = btn.closest('form') || document.body;
            var numero = (form.querySelector('[name="numero_teste"], [name="numero"], #numero_teste, #wpp_numero') || {}).value || '';
            var url    = (form.querySelector('[name="whatsapp_url"], [name="url"], #whatsapp_url') || {}).value || '';
            var token  = (form.querySelector('[name="whatsapp_token"], [name="token"], #whatsapp_token') || {}).value || '';

            // Fallback: inputs com labels relacionados
            if (!url) {
                document.querySelectorAll('input[type="text"], input[type="url"]').forEach(function (i) {
                    var label = (document.querySelector('label[for="' + i.id + '"]') || {}).textContent || '';
                    if (!url && (label.toLowerCase().includes('url') || label.toLowerCase().includes('endpoint'))) url = i.value;
                    if (!token && (label.toLowerCase().includes('token') || label.toLowerCase().includes('api key'))) token = i.value;
                    if (!numero && (label.toLowerCase().includes('número') || label.toLowerCase().includes('numero') || label.toLowerCase().includes('teste'))) numero = i.value;
                });
            }

            if (!numero || !url || !token) {
                alert('Preencha o Endpoint, Token e Número de teste antes de testar.');
                return;
            }

            var originalText = btn.textContent || btn.value;
            if (btn.tagName === 'INPUT') btn.value = '⏳ A testar...';
            else btn.textContent = '⏳ A testar...';
            btn.disabled = true;

            var fd = new FormData();
            fd.append('action', 'sige_testar_wpp_config');
            fd.append('nonce',  nonce);                    // nonce correcto do sigeAjax
            fd.append('numero', numero);
            fd.append('url',    url);
            fd.append('token',  token);

            fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        mostrarResultado(btn, '✅ ' + (data.data || 'Mensagem Entregue!'), 'green');
                    } else {
                        mostrarResultado(btn, '❌ ' + (data.data || 'Erro desconhecido'), 'red');
                    }
                })
                .catch(function (err) {
                    mostrarResultado(btn, '❌ Erro de ligação: ' + err.message, 'red');
                })
                .finally(function () {
                    if (btn.tagName === 'INPUT') btn.value = originalText;
                    else btn.textContent = originalText;
                    btn.disabled = false;
                });
        }

        function mostrarResultado(btn, msg, cor) {
            var existing = document.getElementById('sige-wpp-resultado');
            if (existing) existing.remove();
            var div = document.createElement('div');
            div.id = 'sige-wpp-resultado';
            div.style.cssText = 'margin-top:10px;padding:10px 14px;border-radius:8px;font-weight:600;font-size:13px;background:' +
                (cor === 'green' ? '#e8f5e9;color:#2e7d32;border:1px solid #c8e6c9' : '#ffebee;color:#c62828;border:1px solid #ffcdd2') + ';';
            div.textContent = msg;
            btn.parentNode.insertBefore(div, btn.nextSibling);
            setTimeout(function () { if (div.parentNode) div.remove(); }, 8000);
        }

    })();
    </script>
    <?php
});