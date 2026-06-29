<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';
$fails = [];

$sample = "<?php\nfinal class X { const ACTION_SAVE = 'sige_settings_save'; public static function init(){ add_action('wp_ajax_' . self::ACTION_SAVE, [__CLASS__, 'save']); }}";
$const = sige_gov_collect_string_constants($sample);
$hook = sige_gov_resolve_hook_expr("'wp_ajax_' . self::ACTION_SAVE", $const);
if ($hook !== 'wp_ajax_sige_settings_save') $fails[] = 'resolver de hook dinamico nao detectou wp_ajax_sige_settings_save';

$items = [];
sige_gov_extract_query_surface_from_source($items, "<?php add_action('template_redirect', function(){ if (!isset(\$_GET['sige_desp_print'])) return; });", 'virtual.php');
if (!isset($items['query_handler:sige_desp_print'])) $fails[] = 'extractor de query handler nao detectou sige_desp_print';

$unscoped = "<?php add_action('template_redirect', function(){ \$d = \$wpdb->get_row(\$wpdb->prepare(\"SELECT * FROM \$tD WHERE id=%d\", \$id)); \$where = [\"1=1\"]; });";
$tmp = 'tools/.negative-tenant-sample.php';
file_put_contents(dirname(__DIR__) . '/' . $tmp, $unscoped);
$txt = sige_gov_read($tmp);
@unlink(dirname(__DIR__) . '/' . $tmp);
if (strpos($txt, 'SELECT * FROM $tD WHERE id=%d", $id') === false || strpos($txt, '$where = ["1=1"];') === false) {
    $fails[] = 'teste negativo de tenant nao preservou amostra vulneravel';
}

$current = sige_gov_extract_action_surface_independent();
$ids = array_map(static function ($it) { return $it['id']; }, $current);
foreach (['wp_ajax:sige_settings_save','query_handler:sige_desp_print','query_handler:sige_recibo','wp_hook:template_redirect'] as $id) {
    if (!in_array($id, $ids, true)) $fails[] = "extractor independente nao detectou {$id}";
}

if ($fails) { fwrite(STDERR, "NEGATIVE TESTS FALHARAM\n - " . implode("\n - ", $fails) . "\n"); exit(1); }
echo 'GOVERNANCE NEGATIVE TESTS OK - detectores adversariais exercitados.' . "\n";
