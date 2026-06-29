<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
if (!defined('ABSPATH')) define('ABSPATH', dirname(__DIR__) . '/');
require_once __DIR__ . '/governance-lib.php';
require_once dirname(__DIR__) . '/includes/security-kernel-rules.php';

function sige_sk_validate_rules_array(array $rules, array $manifestIds): array {
    $fails = [];
    $ids = [];
    foreach ($rules as $idx => $rule) {
        if (!is_array($rule)) { $fails[] = "regra {$idx} invalida"; continue; }
        foreach (['id','type','name','mode','risk','public','tenant_required','intent','audit','rate_limit','permissions'] as $field) {
            if (!array_key_exists($field, $rule)) $fails[] = ($rule['id'] ?? "regra {$idx}") . " sem {$field}";
        }
        $id = (string)($rule['id'] ?? '');
        if ($id === '') { $fails[] = "regra {$idx} sem id"; continue; }
        if (isset($ids[$id])) $fails[] = "regra duplicada {$id}";
        $ids[$id] = true;
        $mode = (string)($rule['mode'] ?? '');
        if (!in_array($mode, ['observe','enforce','delegated','retired'], true)) $fails[] = "{$id} com mode invalido";
        if (($rule['type'] ?? '') === 'rest_route') {
            if (empty($rule['route']) || strpos((string)$rule['route'], '/') !== 0) $fails[] = "{$id} rest_route sem route explicita";
            if (($rule['runtime_match'] ?? '') !== 'namespace_route') $fails[] = "{$id} rest_route sem runtime_match namespace_route";
        }
        if (($rule['type'] ?? '') === 'query_handler') {
            $hooks = isset($rule['runtime_hooks']) && is_array($rule['runtime_hooks']) ? $rule['runtime_hooks'] : [];
            foreach (['admin_init','parse_request','template_redirect'] as $qh) {
                if (!in_array($qh, $hooks, true)) $fails[] = "{$id} query_handler sem runtime_hook {$qh}";
            }
            if ((int)($rule['runtime_priority'] ?? 0) > -1) $fails[] = "{$id} query_handler sem prioridade antecipada negativa";
        }
        if (($rule['type'] ?? '') === 'wp_hook' && (int)($rule['runtime_priority'] ?? 0) > -1) {
            $fails[] = "{$id} wp_hook sem prioridade antecipada negativa";
        }
        if (($rule['type'] ?? '') === 'view_action') {
            foreach (['page','view','method','discriminator'] as $vf) if (!array_key_exists($vf, $rule)) $fails[] = "{$id} view_action sem {$vf}";
        }
        if ($mode === 'enforce') {
            $risk = (string)($rule['risk'] ?? '');
            $intent = isset($rule['intent']) && is_array($rule['intent']) ? $rule['intent'] : [];
            $intentType = (string)($intent['type'] ?? 'none');
            if (!in_array($intentType, ['nonce','token','hmac','hmac_or_token'], true)) $fails[] = "{$id} enforce sem intent nonce/token";
            if ($intentType === 'nonce' && (empty($intent['field']) || empty($intent['action']))) $fails[] = "{$id} nonce incompleto";
            if (in_array($intentType, ['token','hmac','hmac_or_token'], true) && empty($intent['validator']) && empty($intent['expected_option'])) $fails[] = "{$id} token/HMAC sem validador";
            if (in_array($risk, ['high','critical'], true) && empty($rule['audit'])) $fails[] = "{$id} high/critical sem auditoria";
            if (in_array($risk, ['high','critical'], true) && empty($rule['rate_limit'])) $fails[] = "{$id} high/critical sem rate_limit";
            $authMode = (string)($rule['authorization_mode'] ?? 'permissions');
            if (empty($rule['public']) && !in_array($authMode, ['delegated','public_token','public_hmac'], true) && empty($rule['permissions'])) $fails[] = "{$id} privado enforce sem permissoes";
            if ($authMode === 'delegated' && empty($rule['delegated_to'])) $fails[] = "{$id} delegado sem delegated_to";
        }
        if (($rule['risk'] ?? '') === 'critical' && $mode === 'observe') $fails[] = "{$id} critico ainda em observe";
    }
    $ruleIds = array_keys($ids);
    sort($ruleIds); sort($manifestIds);
    $missing = array_values(array_diff($manifestIds, $ruleIds));
    $stale = array_values(array_diff($ruleIds, $manifestIds));
    if ($missing) $fails[] = 'manifesto fora do kernel: ' . implode(', ', array_slice($missing, 0, 20));
    if ($stale) $fails[] = 'regras obsoletas fora do manifesto: ' . implode(', ', array_slice($stale, 0, 20));
    return $fails;
}

$v = sige_gov_version();
$manifest = json_decode(sige_gov_read("docs/security/ACTION_SURFACE_MANIFEST-v{$v}.json"), true);
$rulesDoc = json_decode(sige_gov_read("docs/security/SECURITY_KERNEL_RULES-v{$v}.json"), true);
if (!is_array($manifest) || empty($manifest['items'])) { fwrite(STDERR, "manifesto ausente\n"); exit(1); }
if (!is_array($rulesDoc) || empty($rulesDoc['items'])) { fwrite(STDERR, "rules JSON ausente\n"); exit(1); }
$manifestIds = array_map(static function ($it) { return (string)$it['id']; }, $manifest['items']);
$rules = sige_security_kernel_rules();
$fails = sige_sk_validate_rules_array($rules, $manifestIds);
$phpIds = array_map(static function ($it) { return (string)$it['id']; }, $rules);
$jsonIds = array_map(static function ($it) { return (string)$it['id']; }, $rulesDoc['items']);
sort($phpIds); sort($jsonIds);
if ($phpIds !== $jsonIds) $fails[] = 'rules PHP e JSON desalinhados';
$byId = [];
foreach ($rules as $rule) $byId[(string)$rule['id']] = $rule;
$pilot = [
    'admin_post:sige_mpesa_guardar_config',
    'admin_post:sige_emola_guardar_config',
    'query_handler:sige_desp_print',
    'wp_ajax:sige_settings_save',
];
foreach ($pilot as $id) {
    if (empty($byId[$id])) { $fails[] = "piloto ausente {$id}"; continue; }
    if (($byId[$id]['mode'] ?? '') !== 'enforce') $fails[] = "piloto nao esta em enforce {$id}";
}
$enforce = array_values(array_filter($rules, static function ($r) { return ($r['mode'] ?? '') === 'enforce'; }));
if (count($enforce) < 4) $fails[] = 'Security Kernel sem enforcement minimo';
$criticalObserve = array_values(array_filter($rules, static function ($r) { return ($r['risk'] ?? '') === 'critical' && ($r['mode'] ?? '') === 'observe'; }));
if ($criticalObserve) $fails[] = 'Acções críticas ainda em observe: ' . count($criticalObserve);
$settings = $byId['wp_ajax:sige_settings_save'] ?? [];
if (($settings['authorization_mode'] ?? '') !== 'delegated') $fails[] = 'settings_save deve manter autorizacao delegada';
if (empty($settings['tenant_required'])) $fails[] = 'settings_save deve exigir tenant no kernel';
if (($settings['tenant_scope'] ?? '') !== 'required_for_sige_config_writes') $fails[] = 'settings_save sem tenant_scope de escrita sige_config';
if (($settings['delegated_to'] ?? '') !== 'SIGE_Settings_Policy::can_edit') $fails[] = 'settings_save deve delegar para SIGE_Settings_Policy::can_edit';
foreach (['rest_route:sige/v1:/mpesa/callback','rest_route:sige/v1:/emola/callback','rest_route:sige/v1:/process-queue'] as $rid) {
    if (empty($byId[$rid]['route'])) $fails[] = "REST sem route explicita {$rid}";
}
foreach (['admin_post:sige_mpesa_guardar_config','admin_post:sige_emola_guardar_config'] as $mid) {
    if (($byId[$mid]['tenant_storage'] ?? '') !== 'tenant_scoped_wp_option') $fails[] = "mobile payment sem tenant_storage scoped {$mid}";
}

if ($fails) {
    fwrite(STDERR, "SECURITY KERNEL RULES FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'SECURITY KERNEL RULES OK - ' . count($rules) . ' regras cobrem o manifesto; enforce=' . count($enforce) . '; delegated=' . count(array_filter($rules, static function ($r) { return ($r['mode'] ?? '') === 'delegated'; })) . '; observe=' . count(array_filter($rules, static function ($r) { return ($r['mode'] ?? '') === 'observe'; })) . ".\n";
exit(0);
