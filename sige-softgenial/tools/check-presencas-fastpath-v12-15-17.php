<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.17 - Gate do caminho rapido de presencas (P2 ALTO).
 *
 * Protege os invariantes do caminho rapido (popover inline + teclado + lote)
 * atras da flag sige_presencas_fast_path_v121517_enabled (default OFF,
 * reversivel: pôr a option a '0' repoe o modal classico):
 *  (a) presencas.js: zero dialogos nativos (alert/confirm/prompt em chamada
 *      nua, permitindo as formas com ponto tipo sigeUi.confirm) e zero onclick;
 *  (b) engine: regista o handler wp_ajax_sige_presencas_marcar_lote;
 *  (c) o handler de lote tem guarda completa (permissao, nonce, escola_id,
 *      validacao partilhada por item, tecto defensivo, escrita factorizada);
 *  (d) view: flag lida com default OFF e comparacao estrita, com o markup do
 *      popover, da barra de lote e da regiao aria-live;
 *  (e) manifesto: a accao de lote esta declarada na superficie;
 *  (f) presencas.css: as ancoras do popover e da barra existem.
 */
$root = dirname(__DIR__);
$errors = [];

$jsPath       = $root . '/assets/views/presencas.js';
$enginePath   = $root . '/includes/presencas-engine.php';
$viewPath     = $root . '/admin/academic/presencas-view.php';
$cssPath      = $root . '/assets/views/presencas.css';
$manifestPath = $root . '/docs/security/ACTION_SURFACE_MANIFEST-v12.12.63.json';

foreach ([
    $jsPath => 'assets/views/presencas.js',
    $enginePath => 'includes/presencas-engine.php',
    $viewPath => 'admin/academic/presencas-view.php',
    $cssPath => 'assets/views/presencas.css',
    $manifestPath => 'docs/security/ACTION_SURFACE_MANIFEST-v12.12.63.json',
] as $path => $label) {
    if (!is_file($path)) {
        fwrite(STDERR, "check-presencas-fastpath-v12-15-17: FALHOU\n- Ficheiro em falta: {$label}\n");
        exit(1);
    }
}

$js       = (string) file_get_contents($jsPath);
$engine   = (string) file_get_contents($enginePath);
$view     = (string) file_get_contents($viewPath);
$css      = (string) file_get_contents($cssPath);
$manifest = (string) file_get_contents($manifestPath);

// (a) Zero dialogos nativos e zero onclick no JS.
// Chamada nua = nao precedida por caractere de palavra nem ponto (logo
// sigeUi.confirm/prompt e quaisquer metodos com ponto sao permitidos).
if (preg_match('/(?<![\w.])(?:alert|confirm|prompt)\s*\(/', $js)) {
    $errors[] = 'presencas.js usa dialogo nativo (alert/confirm/prompt em chamada nua). Usar sigeUi.toast/confirm/prompt.';
}
if (strpos($js, 'onclick') !== false) {
    $errors[] = 'presencas.js contem onclick (proibido: delegacao de eventos apenas).';
}

// (b) Engine regista o handler de lote.
if (strpos($engine, "add_action('wp_ajax_sige_presencas_marcar_lote'") === false) {
    $errors[] = 'engine nao regista wp_ajax_sige_presencas_marcar_lote.';
}

// (c) Guarda completa do handler de lote. O handler de lote e o ultimo do
// ficheiro, por isso a fatia desde a sua assinatura ate ao fim cobre-o todo.
$posLote = strpos($engine, "add_action('wp_ajax_sige_presencas_marcar_lote'");
if ($posLote !== false) {
    $slice = substr($engine, $posLote);
    $exigido = [
        'sige_presencas_pode_editar'        => 'verificacao de permissao',
        "check_ajax_referer('sige_presencas" => 'nonce de presencas',
        'sige_get_escola_id'                => 'isolamento multi-tenant (escola_id)',
        'sige_presencas_validar_item'       => 'validacao partilhada por item',
        'sige_presencas_aplicar_excecao'    => 'escrita factorizada (fonte unica)',
        '$LIMITE'                           => 'tecto defensivo do lote',
        '1000'                              => 'valor do tecto defensivo',
    ];
    foreach ($exigido as $needle => $desc) {
        if (strpos($slice, $needle) === false) {
            $errors[] = "handler de lote sem {$desc} ({$needle}).";
        }
    }
}

// (d) View: flag com default OFF, comparacao estrita e markup do caminho rapido.
if (strpos($view, "get_option('sige_presencas_fast_path_v121517_enabled', '0')") === false) {
    $errors[] = 'view nao le a flag com default OFF (esperado get_option(..., \'0\')).';
}
if (strpos($view, "=== '1'") === false) {
    $errors[] = 'view nao usa comparacao estrita da flag (=== \'1\').';
}
foreach ([
    'fastPath:'         => 'sinalizacao fastPath na config do JS',
    'id="sgPresPop"'    => 'markup do popover inline',
    'id="sgPresBar"'    => 'markup da barra de lote',
    'sgPresStatus'      => 'regiao aria-live de estado',
    '$fast_path'        => 'condicao do ramo do caminho rapido',
] as $needle => $desc) {
    if (strpos($view, $needle) === false) {
        $errors[] = "view sem {$desc} ({$needle}).";
    }
}

// (e) Manifesto: accao de lote declarada na superficie.
if (strpos($manifest, 'sige_presencas_marcar_lote') === false) {
    $errors[] = 'manifesto sem a accao sige_presencas_marcar_lote.';
}

// (f) CSS: ancoras do popover e da barra.
foreach (['.sg-pres-pop', '.sg-pres-bar'] as $sel) {
    if (strpos($css, $sel) === false) {
        $errors[] = "presencas.css sem a ancora {$sel}.";
    }
}

if ($errors) {
    fwrite(STDERR, "check-presencas-fastpath-v12-15-17: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
printf("check-presencas-fastpath-v12-15-17: OK - caminho rapido atras de flag (default OFF, reversivel), handler de lote com guarda completa, sem dialogos nativos nem onclick, manifesto e CSS alinhados.\n");
