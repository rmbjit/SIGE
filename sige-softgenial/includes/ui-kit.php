<?php
/**
 * SIGE SoftGenial - UI Kit (camada PHP, Sprint UX-1)
 *
 * Carrega assets/sige-ui.css e assets/sige-ui.js em todos os ecrãs do
 * SIGE, DEPOIS do design system (dependência declarada). Como tudo no
 * kit é namespaced (.sg-*), views não tocadas não mudam um pixel.
 *
 * Helpers de renderização (cânone em docs/dev/UI-GUIA.md):
 *   sige_ui_banner('ok'|'erro'|'aviso'|'info', $html)
 *   sige_ui_empty($icone, $titulo, $texto, $accao_html = '')
 */
if (!defined('ABSPATH')) exit;

// ── Carregamento (auto-suficiente: zero alterações no admin-shell) ──────────
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos((string)$hook, 'sige-app') === false) return;

    // v12.15.13 - Portal enxuto: na Pagina do Aluno, o encarregado/aluno nao
    // renderiza views financeiras de staff, por isso esses CSS sao peso morto.
    // So decide enfileiramento; nao toca em PHP financeiro nem em formulas.
    $sige_portal_lean = function_exists('sige_portal_lean_is_active') && sige_portal_lean_is_active();

    // FONTE DA VERDADE: tokens de design carregam ANTES de tudo, para que
    // todas as folhas de estilo e views herdem a mesma paleta/escala.
    $tokens = SIGE_PATH . 'assets/sige-tokens.css';
    if (is_file($tokens)) {
        wp_enqueue_style('sige-tokens', SIGE_URL . 'assets/sige-tokens.css', [], SIGE_VERSION . '.' . filemtime($tokens));
    }

    $deps = ['sige-tokens', 'sige-design-system'];
    if (wp_style_is('sige-mobile-tablet-ux', 'enqueued') || wp_style_is('sige-mobile-tablet-ux', 'registered')) {
        $deps[] = 'sige-mobile-tablet-ux';
    }
    $css = SIGE_PATH . 'assets/sige-ui.css';
    $js  = SIGE_PATH . 'assets/sige-ui.js';
    if (file_exists($css)) {
        wp_enqueue_style('sige-ui-kit', SIGE_URL . 'assets/sige-ui.css', $deps, SIGE_VERSION . '.' . filemtime($css));
    // Calendário de devedores: engine + CSS próprio (o gate de colisões cobre assets/views/)
    require_once SIGE_PATH . 'includes/fin-devedores-calendario.php';
        // v12.15.13 - Os CSS de views financeiras de staff (devedores, reconciliacao,
        // aprovacoes) sao globais por historia, mas o portal do encarregado/aluno
        // nunca renderiza essas views. No portal enxuto, nao os enfileiramos. O
        // motor de devedores acima (require_once) permanece sempre carregado.
        if (!$sige_portal_lean) {
            $sgdc = SIGE_PATH . 'assets/views/devedores.css';
            if (is_file($sgdc)) {
                wp_enqueue_style('sige-devedores-css', SIGE_URL . 'assets/views/devedores.css', ['sige-ui-kit'], SIGE_VERSION . '.' . filemtime($sgdc));
            }
            $sgrc = SIGE_PATH . 'assets/views/reconciliacao.css';
            if (is_file($sgrc)) {
                wp_enqueue_style('sige-reconciliacao-css', SIGE_URL . 'assets/views/reconciliacao.css', ['sige-ui-kit'], SIGE_VERSION . '.' . filemtime($sgrc));
            }
            $sgap = SIGE_PATH . 'assets/views/aprovacoes.css';
            if (is_file($sgap)) {
                wp_enqueue_style('sige-aprovacoes-css', SIGE_URL . 'assets/views/aprovacoes.css', ['sige-ui-kit'], SIGE_VERSION . '.' . filemtime($sgap));
            }
        }
        $sgpv = SIGE_PATH . 'assets/views/privacidade.css';
        if (is_file($sgpv)) {
            wp_enqueue_style('sige-privacidade-css', SIGE_URL . 'assets/views/privacidade.css', ['sige-ui-kit'], SIGE_VERSION . '.' . filemtime($sgpv));
        }
    }
    $shell_css = SIGE_PATH . 'assets/sige-shell-stability.css';
    if (file_exists($shell_css)) {
        wp_enqueue_style('sige-shell-stability', SIGE_URL . 'assets/sige-shell-stability.css', ['sige-ui-kit'], SIGE_VERSION . '.' . filemtime($shell_css));
    }

    // Fase 11, v12.15.8: Design System PRO por view, com opt-out e correcao de layering dos menus de acao.
    // A camada de Alunos nunca deve ser global; só entra no ecrã alunos_lista
    // e pode ser desligada via option `sige_design_alunos_v12157_enabled = 0`.
    $sige_view = isset($_GET['view']) ? sanitize_key((string) $_GET['view']) : '';
    $alunos_design_enabled = get_option('sige_design_alunos_v12157_enabled', '1') !== '0';
    if ($sige_view === 'alunos_lista' && $alunos_design_enabled) {
        $alunos_css = SIGE_PATH . 'assets/views/alunos-design-pro.css';
        if (is_file($alunos_css)) {
            wp_enqueue_style(
                'sige-alunos-design-pro-v12158',
                SIGE_URL . 'assets/views/alunos-design-pro.css',
                ['sige-shell-stability'],
                SIGE_VERSION . '.' . filemtime($alunos_css)
            );
        }
    }

    if (file_exists($js)) {
        wp_enqueue_script('sige-ui-kit', SIGE_URL . 'assets/sige-ui.js', [], SIGE_VERSION . '.' . filemtime($js), true);
    }
    $shell_js = SIGE_PATH . 'assets/sige-shell-stability.js';
    if (file_exists($shell_js)) {
        wp_enqueue_script('sige-shell-stability', SIGE_URL . 'assets/sige-shell-stability.js', ['sige-ui-kit'], SIGE_VERSION . '.' . filemtime($shell_js), true);
    }

    // Fase 11, v12.15.12: Financeiro Core por view, sem tocar em PHP financeiro.
    // Escopo: pagamentos, devedores, extratos, dashboard e views financeiras adjacentes.
    // Opt-out: option `sige_design_financeiro_core_v121512_enabled = 0`.
    $financeiro_core_views = [
        'financeiro-dashboard',
        'financeiro-pagamentos',
        'financeiro-devedores',
        'financeiro-extratos',
        'financeiro-lancamentos',
        'financeiro-relatorio-mensal',
        'financeiro-centros',
        'financeiro-config',
        'financeiro-planos',
        'financeiro-despesas',
        'financeiro-auditoria',
        'financeiro-inscricoes',
        'financeiro-gerador',
        'pagamentos-turma',
        'mpesa',
        'reconciliacao',
        'aprovacoes',
    ];
    $financeiro_design_enabled = get_option('sige_design_financeiro_core_v121512_enabled', '1') !== '0';
    $financeiro_core_active = $financeiro_design_enabled && in_array($sige_view, $financeiro_core_views, true);
    if ($financeiro_core_active) {
        $financeiro_css = SIGE_PATH . 'assets/views/financeiro-core-design-pro.css';
        if (is_file($financeiro_css)) {
            wp_enqueue_style(
                'sige-financeiro-core-design-pro-v121512',
                SIGE_URL . 'assets/views/financeiro-core-design-pro.css',
                ['sige-shell-stability'],
                SIGE_VERSION . '.' . filemtime($financeiro_css)
            );
        }
    }

    if ($sige_view === 'alunos_lista' && $alunos_design_enabled) {
        $alunos_js = SIGE_PATH . 'assets/views/alunos-design-pro.js';
        if (is_file($alunos_js)) {
            wp_enqueue_script(
                'sige-alunos-design-pro-v12158',
                SIGE_URL . 'assets/views/alunos-design-pro.js',
                ['sige-ui-kit'],
                SIGE_VERSION . '.' . filemtime($alunos_js),
                true
            );
        }
    }

    // Nota: o registo de modelos de crachá é entregue INLINE pela própria view
    // (admin/academic/alunos_lista.php, lido do filesystem com o nonce de CSP),
    // por ser à prova de falhas de HTTP/CDN/cache. Não se enfileira aqui.

    if ($financeiro_core_active) {
        $financeiro_js = SIGE_PATH . 'assets/views/financeiro-core-design-pro.js';
        if (is_file($financeiro_js)) {
            wp_enqueue_script(
                'sige-financeiro-core-design-pro-v121512',
                SIGE_URL . 'assets/views/financeiro-core-design-pro.js',
                ['sige-ui-kit'],
                SIGE_VERSION . '.' . filemtime($financeiro_js),
                true
            );
        }
    }
}, 20);

// ── Helpers de renderização ──────────────────────────────────────────────────
if (!function_exists('sige_ui_banner')) {
    /** Banner de feedback. $html já vem escapado/seguro pelo chamador. */
    function sige_ui_banner(string $tipo, string $html): void {
        $tipos = ['ok' => '✅', 'erro' => '⚠️', 'aviso' => '🔶', 'info' => 'ℹ️'];
        if (!isset($tipos[$tipo])) $tipo = 'info';
        echo '<div class="sgk-banner sgk-banner-' . esc_attr($tipo) . '" role="' . ($tipo === 'erro' ? 'alert' : 'status') . '">'
           . '<span aria-hidden="true">' . $tipos[$tipo] . '</span><div>' . $html . '</div></div>';
    }
}

if (!function_exists('sige_ui_empty')) {
    /**
     * Estado vazio com próxima acção. Usar dentro de um cartão ou no lugar
     * da tabela vazia; $accao_html é tipicamente um <a class="sgk-btn ...">.
     */
    function sige_ui_empty(string $icone, string $titulo, string $texto, string $accao_html = ''): void {
        echo '<div class="sgk-empty">'
           . '<div class="sgk-empty-icone" aria-hidden="true">' . esc_html($icone) . '</div>'
           . '<p class="sgk-empty-titulo">' . esc_html($titulo) . '</p>'
           . '<p class="sgk-empty-texto">' . esc_html($texto) . '</p>'
           . $accao_html
           . '</div>';
    }
}
