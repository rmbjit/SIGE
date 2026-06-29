<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.13 - Smoke do Portal enxuto (motor de decisao).
 *
 * Exercita a POLITICA REAL (includes/portal-lean-assets.php), nao uma copia,
 * sob stubs minimos de WordPress, varrendo a tabela de verdade completa da
 * decisao sige_portal_lean_is_active(). Corre isolado em subprocesso, por isso
 * os stubs nao colidem com nada.
 */
$root = dirname(__DIR__);

// Stubs minimos de WordPress (todos com guarda, para nunca redeclarar).
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
$GLOBALS['__sige_caps'] = [];
$GLOBALS['__sige_opts'] = [];
if (!function_exists('current_user_can')) {
    function current_user_can($cap) { return !empty($GLOBALS['__sige_caps'][$cap]); }
}
if (!function_exists('get_option')) {
    function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['__sige_opts']) ? $GLOBALS['__sige_opts'][$k] : $d; }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($k) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $k)); }
}

require $root . '/includes/portal-lean-assets.php';

if (!function_exists('sige_portal_lean_is_active')) {
    fwrite(STDERR, "smoke-portal-lean-assets-v12-15-13: FALHOU\n- A politica nao definiu sige_portal_lean_is_active.\n");
    exit(1);
}

$fail = [];
$caso = function (string $label, array $opts, string $view, array $caps, bool $expected) use (&$fail) {
    $GLOBALS['__sige_opts'] = $opts;
    $GLOBALS['__sige_caps'] = $caps;
    $_GET['view'] = $view;
    $got = sige_portal_lean_is_active();
    if ($got !== $expected) {
        $fail[] = sprintf('%s: esperado %s, obtido %s', $label, $expected ? 'true' : 'false', $got ? 'true' : 'false');
    }
};

$FLAG = 'sige_portal_lean_assets_v121513_enabled';

// Flag por defeito (option ausente => '1' => ligada): utilizador exclusivo do portal => enxuto.
$caso('encarregado no portal (defeito)', [], 'aluno_portal', ['sige_encarregado' => 1], true);
$caso('aluno no portal (defeito)',       [], 'aluno_portal', ['sige_aluno' => 1], true);
$caso('flag explicita 1',                [$FLAG => '1'], 'aluno_portal', ['sige_encarregado' => 1], true);

// Flag desligada => sempre shell completa.
$caso('flag 0 desliga',                  [$FLAG => '0'], 'aluno_portal', ['sige_encarregado' => 1], false);

// View errada => nunca enxuga.
$caso('view dashboard',                  [], 'dashboard', ['sige_encarregado' => 1], false);
$caso('view financeiro',                 [], 'financeiro-pagamentos', ['sige_encarregado' => 1], false);
$caso('view vazia',                      [], '', ['sige_encarregado' => 1], false);

// Staff no portal => mantem shell completa (pode navegar para modulos pesados).
$caso('director no portal',              [], 'aluno_portal', ['sige_director' => 1], false);
$caso('manage_options no portal',        [], 'aluno_portal', ['manage_options' => 1], false);
$caso('professor no portal',             [], 'aluno_portal', ['sige_professor' => 1], false);
$caso('financeiro no portal',            [], 'aluno_portal', ['sige_financeiro' => 1], false);

// Staff vence mesmo com capacidade de portal acumulada.
$caso('encarregado + director',          [], 'aluno_portal', ['sige_encarregado' => 1, 'sige_director' => 1], false);

// Sem capacidades => nao e utilizador de portal => nao enxuga.
$caso('sem capacidades',                 [], 'aluno_portal', [], false);

if ($fail) {
    fwrite(STDERR, "smoke-portal-lean-assets-v12-15-13: FALHOU\n- " . implode("\n- ", $fail) . "\n");
    exit(1);
}
echo "smoke-portal-lean-assets-v12-15-13: OK - 13 casos da tabela de verdade do portal enxuto verdes.\n";
