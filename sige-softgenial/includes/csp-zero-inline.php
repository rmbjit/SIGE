<?php
/**
 * SIGE SoftGenial v12.14.1 - CSP Zero-Inline Guard
 *
 * Camada central de hardening para o shell administrativo autenticado:
 * - remove 'permissao-inline' da politica CSP;
 * - converte style= em data-sige-style antes da resposta chegar ao navegador;
 * - converte handlers on*= em data-sige-on-* antes da resposta chegar ao navegador;
 * - aplica nonce defensivo a tags script/style que ainda sejam emitidos por views legadas.
 *
 * A hidratacao de data-sige-style/data-sige-on-* e feita por assets/sige-ui.js,
 * ficheiro externo carregado via wp_enqueue_script.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_csp_zero_inline_policy')) {
    function sige_csp_zero_inline_policy(): string {
        $nonce = function_exists('sige_csp_nonce') ? sige_csp_nonce() : '';
        $nonce_part = $nonce !== '' ? " 'nonce-" . $nonce . "'" : '';
        return "default-src 'self'; "
            . "script-src 'self'" . $nonce_part . "; "
            . "script-src-elem 'self'" . $nonce_part . "; "
            . "script-src-attr 'none'; "
            . "style-src 'self'" . $nonce_part . "; "
            . "style-src-elem 'self'" . $nonce_part . "; "
            . "style-src-attr 'none'; "
            . "img-src 'self' data: blob: https:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "media-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self';";
    }
}


if (!function_exists('sige_csp_zero_inline_is_standalone_request')) {
    /**
     * Pedidos SIGE que renderizam uma pagina HTML autonoma fora do shell admin.
     * Estas paginas herdam a CSP zero-inline, mas nao passam pelo wp_enqueue
     * normal do admin. Por isso precisam de buffer + hidratador externo para
     * nao ficarem em HTML cru quando a CSP bloqueia CSS/handlers inline.
     */
    function sige_csp_zero_inline_is_standalone_request(): bool {
        foreach (['sige_portaria_camera', 'sige_print', 'sige_dev_print', 'sige_desp_print'] as $key) {
            if (isset($_GET[$key]) || isset($_POST[$key]) || isset($_REQUEST[$key])) {
                return true;
            }
        }
        $action = isset($_REQUEST['action']) ? sanitize_key((string) wp_unslash($_REQUEST['action'])) : '';
        if ($action !== '' && strpos($action, 'sige_') === 0) {
            return true;
        }
        return false;
    }
}

if (!function_exists('sige_csp_zero_inline_should_apply')) {
    function sige_csp_zero_inline_should_apply(): bool {
        if (function_exists('sige_csp_is_admin_shell_request') && sige_csp_is_admin_shell_request()) {
            return true;
        }
        if (function_exists('sige_csp_zero_inline_is_standalone_request') && sige_csp_zero_inline_is_standalone_request()) {
            return true;
        }
        $action = '';
        if (isset($_REQUEST['action'])) {
            $action = sanitize_key((string) wp_unslash($_REQUEST['action']));
        }
        if ($action !== '' && strpos($action, 'sige_') === 0) {
            return true;
        }
        return false;
    }
}


if (!function_exists('sige_csp_zero_inline_asset_url')) {
    function sige_csp_zero_inline_asset_url(string $rel): string {
        $base = defined('SIGE_URL') ? SIGE_URL : (function_exists('plugins_url') ? plugins_url('../', __FILE__) : '');
        $ver  = defined('SIGE_VERSION') ? SIGE_VERSION : (string) time();
        return rtrim($base, '/') . '/' . ltrim($rel, '/') . '?ver=' . rawurlencode($ver);
    }
}

if (!function_exists('sige_csp_zero_inline_inject_standalone_hydrator')) {
    /**
     * Injecta o hidratador externo apenas em paginas autonomas. Ele aplica
     * data-sige-style/data-sige-on-* por JS seguro, sem permissao-inline/eval.
     */
    function sige_csp_zero_inline_inject_standalone_hydrator(string $html): string {
        if ($html === '' || !function_exists('sige_csp_zero_inline_is_standalone_request') || !sige_csp_zero_inline_is_standalone_request()) {
            return $html;
        }
        if (stripos($html, 'data-sige-style') === false && stripos($html, 'data-sige-on-') === false) {
            return $html;
        }
        if (stripos($html, 'assets/sige-ui.js') !== false) {
            return $html;
        }
        $tag = '<script defer src="' . esc_url(sige_csp_zero_inline_asset_url('assets/sige-ui.js')) . '"></script>' . "\n";
        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $tag . '</head>', $html, 1);
        }
        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $tag . '</body>', $html, 1);
        }
        return $html . $tag;
    }
}

if (!function_exists('sige_csp_zero_inline_sanitise_html')) {
    /**
     * Sanitiza apenas HTML do shell SIGE. Nao interpreta nem executa expressoes;
     * so move atributos inline para atributos declarativos consumidos por JS externo.
     */
    function sige_csp_zero_inline_sanitise_html(string $html): string {
        if ($html === '') return $html;
        $nonce = function_exists('sige_csp_nonce') ? esc_attr(sige_csp_nonce()) : '';

        if ($nonce !== '') {
            // Nonce defensivo para blocos legados que ainda sao emitidos pela view.
            $html = preg_replace('/<script\b(?![^>]*\bnonce=)/i', '<script nonce="' . $nonce . '"', $html);
            $html = preg_replace('/<style\b(?![^>]*\bnonce=)/i', '<style nonce="' . $nonce . '"', $html);
        }

        // style= deixa de chegar ao browser como inline style. O valor passa a ser
        // aplicado por CSSOM no JS externo, sem permissao-inline no CSP.
        $html = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', ' data-sige-style=$1', $html);

        // Handlers inline on*= deixam de chegar ao browser como handlers executaveis.
        // Ex.: onchange="this.form.submit()" => data-sige-on-change="this.form.submit()".
        $html = preg_replace_callback('/\son([a-z][a-z0-9_-]*)\s*=\s*("[^"]*"|\'[^\']*\')/i', function ($m) {
            $event = strtolower($m[1]);
            return ' data-sige-on-' . $event . '=' . $m[2];
        }, $html);

        if (function_exists('sige_csp_zero_inline_inject_standalone_hydrator')) {
            $html = sige_csp_zero_inline_inject_standalone_hydrator($html);
        }
        return $html;
    }
}


if (!function_exists('sige_csp_zero_inline_buffer_active')) {
    function sige_csp_zero_inline_buffer_active(): bool {
        if (!function_exists('ob_get_status')) return false;
        $levels = ob_get_status(true);
        if (!is_array($levels)) return false;
        foreach ($levels as $level) {
            $cb = $level['name'] ?? ($level['callback'] ?? '');
            if (is_string($cb) && strpos($cb, 'sige_csp_zero_inline_sanitise_html') !== false) {
                return true;
            }
            if (is_array($cb) && in_array('sige_csp_zero_inline_sanitise_html', $cb, true)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sige_csp_zero_inline_boot')) {
    function sige_csp_zero_inline_boot(): void {
        if (function_exists('sige_csp_zero_inline_should_apply') && sige_csp_zero_inline_should_apply()) {
            if (!headers_sent()) {
                header('Content-Security-Policy: ' . sige_csp_zero_inline_policy(), true);
                header('X-SIGE-CSP-Mode: zero-inline-v12.15.4', true);
            }
            if (!function_exists('sige_csp_zero_inline_buffer_active') || !sige_csp_zero_inline_buffer_active()) {
                ob_start('sige_csp_zero_inline_sanitise_html');
            }
        }
    }
}
add_action('admin_init', 'sige_csp_zero_inline_boot', 0);
add_action('template_redirect', 'sige_csp_zero_inline_boot', -100);
