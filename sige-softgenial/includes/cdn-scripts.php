<?php
/**
 * SIGE SoftGenial - CDN Scripts com SRI
 * Ficheiro: includes/cdn-scripts.php
 * 
 * Centraliza todas as referências a scripts CDN com:
 * - Versões fixas (pinned)
 * - Subresource Integrity (SRI) hashes
 * - crossorigin="anonymous"
 * 
 * Para gerar os hashes: php sige-generate-sri.php
 */
if (!defined('ABSPATH')) exit;

/**
 * Catalogo de scripts: agora SELF-HOST (ficheiros locais em assets/vendor/),
 * servidos pela propria instalacao em vez de CDN. As URLs sao construidas a partir
 * de SIGE_URL (sem literais https:// de CDN no codigo), o que retira cdnjs e unpkg
 * das dependencias externas e permite um CSP estrito sem fontes de terceiros.
 */
function sige_cdn_catalog(): array {
    $base = (defined('SIGE_URL') ? SIGE_URL : plugins_url('/', dirname(__DIR__) . '/sige-softgenial.php')) . 'assets/vendor/';
    return [
        'chartjs'     => $base . 'chartjs/chart.umd.js',
        'exceljs'     => $base . 'exceljs/exceljs.min.js',
        'filesaver'   => $base . 'filesaver/FileSaver.min.js',
        'xlsx'        => $base . 'xlsx/xlsx.full.min.js',
        'qrious'      => $base . 'qrious/qrious.min.js',
        'sortable'    => $base . 'sortable/Sortable.min.js',
        'html5qrcode' => $base . 'html5qrcode/html5-qrcode.min.js',
    ];
}

/**
 * Retorna SRI hashes (gerados por sige-generate-sri.php)
 */
function sige_cdn_hashes(): array {
    static $hashes = null;
    if ($hashes === null) {
        $file = SIGE_PATH . 'includes/sri-hashes.php';
        if (file_exists($file)) {
            require_once $file;
            $hashes = function_exists('sige_sri_hashes') ? sige_sri_hashes() : [];
        } else {
            $hashes = [];
        }
    }
    return $hashes;
}

/**
 * Gera tag <script> com SRI para um script CDN
 * 
 * @param string $key Chave do catálogo (ex: 'chartjs', 'exceljs')
 * @return string Tag HTML completa
 */
function sige_cdn_script(string $key): string {
    $catalog = sige_cdn_catalog();
    if (!isset($catalog[$key])) return "<!-- CDN script '{$key}' não encontrado no catálogo -->";
    
    $url = $catalog[$key];
    $hashes = sige_cdn_hashes();
    
    // Mapear chaves do catálogo para chaves dos hashes
    $hash_map = [
        'chartjs' => 'chartjs-4.4.1',
        'exceljs' => 'exceljs-4.3.0',
        'filesaver' => 'filesaver-2.0.5',
        'xlsx' => 'xlsx-0.18.5',
        'qrious' => 'qrious-4.0.2',
        'sortable' => 'sortable-1.15.2',
        'html5qrcode' => 'html5qrcode-2.3.8',
    ];
    
    $hash_key = $hash_map[$key] ?? $key;
    $integrity = $hashes[$hash_key] ?? '';
    
    $attrs = $integrity 
        ? ' integrity="' . esc_attr($integrity) . '" crossorigin="anonymous"'
        : ' crossorigin="anonymous"';
    
    return '<script src="' . esc_url($url) . '"' . $attrs . '></script>';
}
