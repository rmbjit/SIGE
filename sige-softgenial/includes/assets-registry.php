<?php
/**
 * SIGE SoftGenial - Registador central de assets locais (self-host).
 * Fase 4, incremento 1.
 *
 * Regista as bibliotecas self-hosted (assets/vendor/) como handles do WordPress,
 * para que a migracao dos blocos inline para wp_enqueue se possa fazer por vagas, e
 * injecta SRI (integrity) nas tags geradas pelo enqueue. E a base do front-end
 * seguro e do CSP em enforcement: as bibliotecas deixam de vir de CDN de terceiros.
 *
 * Nesta fase apenas REGISTA os handles (nao enfileira nada por si so); os ecrans
 * continuam a usar sige_cdn_script(), que ja aponta para os ficheiros locais. As
 * vagas seguintes vao trocar os blocos inline por sige_enqueue_lib().
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_assets_libs')) {
    /** handle => [ficheiro relativo a assets/vendor/, versao, chave em sri-hashes]. */
    function sige_assets_libs(): array {
        return [
            'sige-chartjs'     => ['chartjs/chart.umd.js',           '4.4.1',  'chartjs-4.4.1'],
            'sige-xlsx'        => ['xlsx/xlsx.full.min.js',          '0.18.5', 'xlsx-0.18.5'],
            'sige-exceljs'     => ['exceljs/exceljs.min.js',         '4.3.0',  'exceljs-4.3.0'],
            'sige-sortable'    => ['sortable/Sortable.min.js',       '1.15.2', 'sortable-1.15.2'],
            'sige-qrious'      => ['qrious/qrious.min.js',           '4.0.2',  'qrious-4.0.2'],
            'sige-filesaver'   => ['filesaver/FileSaver.min.js',     '2.0.5',  'filesaver-2.0.5'],
            'sige-html5qrcode' => ['html5qrcode/html5-qrcode.min.js', '2.3.8', 'html5qrcode-2.3.8'],
        ];
    }
}

if (!function_exists('sige_assets_base_url')) {
    function sige_assets_base_url(): string {
        return (defined('SIGE_URL') ? SIGE_URL : plugins_url('/', dirname(__DIR__) . '/sige-softgenial.php')) . 'assets/vendor/';
    }
}

if (!function_exists('sige_assets_registar')) {
    /** Regista os handles das bibliotecas locais (nao enfileira nada por si so). */
    function sige_assets_registar(): void {
        if (!function_exists('wp_register_script')) return;
        $base = sige_assets_base_url();
        foreach (sige_assets_libs() as $handle => $info) {
            wp_register_script($handle, $base . $info[0], [], $info[1], true);
        }
    }
}

if (!function_exists('sige_enqueue_lib')) {
    /** Enfileira uma biblioteca local pelo handle (ex.: sige-chartjs). */
    function sige_enqueue_lib(string $handle): void {
        if (function_exists('wp_enqueue_script')) wp_enqueue_script($handle);
    }
}

if (!function_exists('sige_assets_sri_para_handle')) {
    /** Devolve o SRI (integrity) de um handle a partir de sri-hashes.php. */
    function sige_assets_sri_para_handle(string $handle): string {
        $libs = sige_assets_libs();
        if (!isset($libs[$handle])) return '';
        $hashes = function_exists('sige_cdn_hashes') ? sige_cdn_hashes() : [];
        return (string) ($hashes[$libs[$handle][2]] ?? '');
    }
}

// Regista no carregamento de assets do admin (o SIGE corre em wp-admin). O hook ja
// e usado por outros modulos, pelo que nao acrescenta superficie de accao nova.
add_action('admin_enqueue_scripts', 'sige_assets_registar', 5);

if (!function_exists('sige_assets_tag_sri')) {
    /** Injecta integrity (SRI) e crossorigin nas tags dos nossos handles enfileirados. */
    function sige_assets_tag_sri(string $tag, string $handle): string {
        if (strpos($handle, 'sige-') !== 0) return $tag;
        $sri = sige_assets_sri_para_handle($handle);
        if ($sri === '' || strpos($tag, 'integrity=') !== false) return $tag;
        return str_replace(' src=', ' integrity="' . esc_attr($sri) . '" crossorigin="anonymous" src=', $tag);
    }
}
add_filter('script_loader_tag', 'sige_assets_tag_sri', 10, 2);

if (!function_exists('sige_utilities_inline_css')) {
    /**
     * Devolve as regras das classes utilitarias (as mesmas do assets/sige-utilities.css)
     * como uma cadeia CSS compacta, para embeber em janelas de impressao autonomas.
     *
     * As vistas com popup de impressao (window.open + document.write) constroem um
     * documento proprio, com o seu <style>, e nao carregam o sige-utilities.css
     * enfileirado na shell. Quando essas janelas clonam ou geram marcacao que usa as
     * classes sige-u- (migradas nas vagas de inline), as classes ficariam sem efeito
     * no impresso. Injectar este CSS no <style> do popup mantem a apresentacao fiel.
     *
     * Mantem !important para replicar a especificidade que o estilo inline tinha.
     */
    function sige_utilities_inline_css(): string {
        return '.sige-u-shrink-0{flex-shrink:0!important}'
             . '.sige-u-flex-1{flex:1!important}'
             . '.sige-u-tac{text-align:center!important}'
             . '.sige-u-tal{text-align:left!important}'
             . '.sige-u-tar{text-align:right!important}'
             . '.sige-u-fw7{font-weight:700!important}'
             . '.sige-u-fw6{font-weight:600!important}'
             . '.sige-u-fw5{font-weight:500!important}'
             . '.sige-u-m0{margin:0!important}'
             . '.sige-u-nowrap{white-space:nowrap!important}'
             . '.sige-u-oxa{overflow-x:auto!important}'
             . '.sige-u-wfull{width:100%!important}';
    }
}
