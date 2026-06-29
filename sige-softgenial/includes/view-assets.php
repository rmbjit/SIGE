<?php
/**
 * SIGE SoftGenial - Carregador de assets por view (regra do escuteiro)
 *
 * Par da convenção assets/views/ (ver README dessa pasta): quando o CSS/JS
 * de uma view é extraído do monólito, a view passa a chamar
 * sige_view_assets('nome_da_view') e este helper imprime as tags com
 * versionamento por filemtime (cache-busting automático em cada deploy).
 *
 * Imprime inline no ponto de chamada (as views do SIGE renderizam dentro
 * do shell, fora do ciclo normal de wp_enqueue), por isso funciona em
 * qualquer view sem alterar o admin-shell.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_view_assets')) {
    function sige_view_assets(string $view): void {
        $view = sanitize_key($view);
        if ($view === '') return;
        $base_dir = defined('SIGE_PATH') ? SIGE_PATH . 'assets/views/' : '';
        $base_url = defined('SIGE_URL') ? SIGE_URL . 'assets/views/' : '';
        if ($base_dir === '' || $base_url === '') return;

        $css = $base_dir . $view . '.css';
        if (is_file($css)) {
            printf(
                '<link rel="stylesheet" href="%s">' . "\n",
                esc_url($base_url . $view . '.css?v=' . (string) filemtime($css))
            );
        }
        $js = $base_dir . $view . '.js';
        if (is_file($js)) {
            printf(
                '<script src="%s" defer></script>' . "\n",
                esc_url($base_url . $view . '.js?v=' . (string) filemtime($js))
            );
        }
    }
}
