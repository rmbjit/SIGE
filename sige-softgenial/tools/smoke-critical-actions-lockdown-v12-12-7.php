<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
if (!defined('SIGE_SECURITY_KERNEL_TEST_MODE')) define('SIGE_SECURITY_KERNEL_TEST_MODE', true);
if (!defined('SIGE_SECURITY_KERNEL_OBSERVE_LOG')) define('SIGE_SECURITY_KERNEL_OBSERVE_LOG', true);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');

$GLOBALS['sige_sk_test_actions'] = [];
$GLOBALS['sige_sk_test_filters'] = [];
$GLOBALS['sige_sk_test_logs'] = [];
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['sige_sk_test_actions'][] = [$hook, (int)$priority, (int)$accepted_args, is_callable($callback) ? $callback : null];
        return true;
    }
}
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['sige_sk_test_filters'][] = [$hook, (int)$priority, (int)$accepted_args, is_callable($callback) ? $callback : null];
        return true;
    }
}
if (!function_exists('is_user_logged_in')) { function is_user_logged_in() { return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 202; } }
if (!function_exists('sige_get_escola_id')) { function sige_get_escola_id() { return 7; } }
if (!function_exists('sige_user_can_any_secure')) { function sige_user_can_any_secure($permissions, $legacy = []) { return true; } }
if (!function_exists('sige_rate_limit_action')) { function sige_rate_limit_action($key, $max, $window, $die = false) { return true; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($nonce, $action) { return $nonce === ('ok-' . $action); } }
if (!function_exists('sanitize_key')) { function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string)$key)); } }
if (!function_exists('wp_unslash')) { function wp_unslash($value) { return $value; } }
if (!function_exists('current_filter')) { function current_filter() { return 'unit_test'; } }
if (!function_exists('sige_security_log')) { function sige_security_log($event, $line = '') { $GLOBALS['sige_sk_test_logs'][] = [$event, $line]; } }
if (!function_exists('wp_die')) { function wp_die($message = '', $title = '', $args = []) { throw new RuntimeException('wp_die:' . (string)$message); } }

require_once $root . '/includes/security-kernel.php';

$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

$rules = sige_security_kernel_all_rules();
$manifest = json_decode((string)file_get_contents($root . '/docs/security/ACTION_SURFACE_MANIFEST-v12.12.7.json'), true);
$manifestCount = is_array($manifest['items'] ?? null) ? count($manifest['items']) : (is_array($manifest['surfaces'] ?? null) ? count($manifest['surfaces']) : (is_array($manifest) ? count($manifest) : 0));
$check(count($rules) >= $manifestCount && $manifestCount >= 192, 'Security Kernel cobre o manifesto v12.12.7');

$modes = [];
$typeCounts = [];
$criticalObserve = [];
foreach ($rules as $id => $rule) {
    $mode = (string)($rule['mode'] ?? '');
    $modes[$mode] = ($modes[$mode] ?? 0) + 1;
    $type = (string)($rule['type'] ?? '');
    $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
    if (($rule['risk'] ?? '') === 'critical' && $mode === 'observe') $criticalObserve[] = $id;
}
$check(($typeCounts['view_action'] ?? 0) >= 18, 'view_action inventariado no contrato do Kernel');
$check(($modes['enforce'] ?? 0) >= 29, 'lockdown enforce activo nas acções críticas aprovadas');
$check(($modes['delegated'] ?? 0) >= 1, 'modo delegated suportado para políticas de domínio existentes');
$check(count($criticalObserve) === 0, 'zero acções críticas permanecem em observe');

$required = [
    'view_action:financeiro-pagamentos:sige_fin_pagar_submit' => ['permission' => 'financeiro.pagar', 'intent' => 'nonce', 'view' => 'financeiro-pagamentos'],
    'view_action:financeiro-pagamentos:sige_fin_pagar_familia_submit' => ['permission' => 'financeiro.pagar', 'intent' => 'nonce', 'view' => 'financeiro-pagamentos'],
    'view_action:financeiro-despesas:nova_despesa' => ['permission' => 'financeiro.despesas_gerir', 'intent' => 'nonce', 'view' => 'financeiro-despesas'],
    'view_action:financeiro-despesas:aprovar_despesa' => ['permission' => 'financeiro.despesas_gerir', 'intent' => 'nonce', 'view' => 'financeiro-despesas'],
    'view_action:sige_permissoes:save_role_permissions' => ['permission' => 'usuarios.gerir_permissoes', 'intent' => 'nonce', 'view' => 'sige_permissoes'],
    'wp_ajax:sige_mpesa_conciliar_manual' => ['permission' => 'financeiro.mobile_payments_gerir', 'intent' => 'nonce'],
    'wp_ajax:sige_mpesa_rejeitar' => ['permission' => 'financeiro.mobile_payments_gerir', 'intent' => 'nonce'],
    'rest_route:sige/v1:/mpesa/callback' => ['intent' => 'token'],
    'rest_route:sige/v1:/emola/callback' => ['intent' => 'token'],
    'wp_ajax:sige_remover_aluno' => ['permission' => 'alunos.apagar', 'intent' => 'nonce'],
];
foreach ($required as $id => $expect) {
    $rule = $rules[$id] ?? null;
    $check(is_array($rule), "{$id} existe no Kernel");
    if (!is_array($rule)) continue;
    $check(($rule['mode'] ?? '') === 'enforce', "{$id} em enforce");
    if (isset($expect['permission'])) $check(in_array($expect['permission'], $rule['permissions'] ?? [], true), "{$id} tem permissao {$expect['permission']}");
    if (isset($expect['intent'])) $check(($rule['intent']['type'] ?? '') === $expect['intent'], "{$id} tem intent {$expect['intent']}");
    if (isset($expect['view'])) $check(($rule['view'] ?? '') === $expect['view'], "{$id} tem view correcta");
    $check(!empty($rule['audit']), "{$id} tem auditoria");
    $check(!empty($rule['rate_limit']) || ($rule['type'] ?? '') === 'rest_route', "{$id} tem rate limit ou controlo REST");
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = ['page' => 'sige-app', 'view' => 'financeiro-pagamentos'];
$_POST = ['page' => 'sige-app', 'view' => 'financeiro-pagamentos', 'sige_fin_pagar_submit' => '1', '_wpnonce' => 'ok-sige_fin_pagar'];
$_REQUEST = array_merge($_GET, $_POST);
$matches = sige_security_kernel_matching_view_actions();
$check(isset($matches['view_action:financeiro-pagamentos:sige_fin_pagar_submit']), 'dispatch identifica mutacao directa de pagamento');
try {
    sige_security_kernel_dispatch_view_actions('admin_init');
    $allowed = false;
    foreach ($GLOBALS['sige_sk_test_logs'] as $row) {
        if ($row[0] === 'security_kernel.allowed' && strpos($row[1], 'surface_id=view_action:financeiro-pagamentos:sige_fin_pagar_submit') !== false) $allowed = true;
    }
    $check($allowed, 'view_action de pagamento valido passa e audita allowed');
} catch (Throwable $e) {
    $check(false, 'view_action de pagamento valido nao deve bloquear');
}

$mpesa = file_get_contents($root . '/includes/payments/mpesa-conciliacao.php');
$check(strpos($mpesa, 'WHERE id = %d AND escola_id = %d') !== false, 'M-Pesa manual consulta transaccao com escola_id');
$check(strpos($mpesa, "['id' => \$tx_id, 'escola_id' => \$escola_id]") !== false, 'M-Pesa manual actualiza transaccao com escola_id');
$check(strpos($mpesa, 'sige_fin_lancamentos') !== false && (strpos($mpesa, 'AND escola_id = %d LIMIT 1') !== false || strpos($mpesa, 'AND escola_id=%d') !== false), 'M-Pesa valida lancamento contra escola');

$desp = file_get_contents($root . '/admin/finance/financeiro-despesas-view.php');
$check(strpos($desp, 'SELECT escola_id FROM') === false, 'Despesas nao escolhem primeira escola da base');
$check(strpos($desp, 'sige_get_escola_id') !== false, 'Despesas usam escola do contexto');
$check(strpos($desp, 'Despesa não pertence a esta escola') !== false || strpos($desp, 'Despesa nao pertence a esta escola') !== false, 'Despesas validam ownership antes de aprovar/anular');

$lancView = file_get_contents($root . '/admin/finance/financeiro-lancamentos-view.php');
$relMensal = file_get_contents($root . '/admin/finance/financeiro-relatorio-mensal-view.php');
$check(strpos($lancView, 'SELECT escola_id FROM') === false && strpos($lancView, 'sige_get_escola_id') !== false, 'Lançamentos usam escola do contexto, sem primeira escola da base');
$check(strpos($relMensal, 'SELECT escola_id FROM') === false && strpos($relMensal, 'sige_get_escola_id') !== false, 'Relatório mensal usa escola do contexto, sem primeira escola da base');

$pag = file_get_contents($root . '/admin/finance/financeiro-pagamentos.php');
$check(strpos($pag, "sige_fin_user_can_write_12127('financeiro.pagar')") !== false, 'Pagamento exige permissao financeiro.pagar na acção');
$check(strpos($pag, "sige_fin_user_can_write_12127('financeiro.lancamentos_gerir')") !== false, 'Cancelamento exige permissao de lancamentos');
$check(strpos($pag, "sige_fin_user_can_write_12127('financeiro.isentar_multas')") !== false, 'Isencao exige permissao propria');
$check(strpos($pag, "sige_fin_user_can_write_12127('financeiro.bloquear_mes')") !== false, 'Bloqueio mensal exige permissao propria');

$perms = file_get_contents($root . '/includes/permissions-layer.php');
$check(strpos($perms, 'sige_school_role_permissions') !== false, 'Permissoes por perfil possuem tabela escolar');
$check(strpos($perms, 'sige_school_user_permission_overrides') !== false, 'Overrides individuais possuem tabela escolar');
$check(strpos($perms, 'school_role_permissions') !== false && strpos($perms, 'school_user_overrides') !== false, 'sige_can resolve permissões tenant-scoped');

$permUi = file_get_contents($root . '/admin/system/permissions-ui.php');
$check(strpos($permUi, 'afectam apenas esta escola') !== false, 'UI declara que matriz escolar afecta apenas a escola corrente');
$check(strpos($permUi, '$escola_id_contexto') !== false, 'UI de permissões usa contexto de escola');

$ajax = file_get_contents($root . '/includes/ajax-handlers.php');
$check(strpos($ajax, "status' => 'arquivado'") !== false || strpos($ajax, 'status = %s') !== false, 'Remover aluno foi convertido em arquivamento');
$check(strpos($ajax, 'DELETE FROM $tMat') === false && strpos($ajax, 'DELETE FROM $tAlu') === false, 'Arquivamento de aluno nao apaga historico');

if ($fails) {
    fwrite(STDERR, 'SMOKE CRITICAL ACTIONS LOCKDOWN 12.12.7 FALHOU: ' . implode('; ', $fails) . "\n");
    exit(1);
}
echo "SMOKE CRITICAL ACTIONS LOCKDOWN 12.12.7 OK - {$oks} verificacoes passaram.\n";
