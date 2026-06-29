<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE v12.11.9.72 Portaria Camera Permission UX PRO
 * Static deep checks for camera permission hotfix, Guarda isolation and mobile/tablet UX.
 */
$root = dirname(__DIR__);
$files = [
    'main' => $root . '/sige-softgenial.php',
    'build' => $root . '/BUILD.json',
    'portaria' => $root . '/admin/system/portaria-view.php',
    'db' => $root . '/includes/db-handler.php',
    'permissions' => $root . '/includes/permissions-layer.php',
    'mobile_js' => $root . '/assets/mobile-tablet-ux.js',
];
foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Ficheiro em falta: {$name} {$path}\n");
        exit(1);
    }
}
$main = file_get_contents($files['main']);
$build = file_get_contents($files['build']);
$port = file_get_contents($files['portaria']);
$db = file_get_contents($files['db']);
$perm = file_get_contents($files['permissions']);
$mobile = file_get_contents($files['mobile_js']);

$build_meta = json_decode(file_get_contents($files['build']), true);
if (($build_meta['version'] ?? '') !== '12.11.9.72') {
    echo 'SKIP ' . basename(__FILE__) . ' - superseded by current build ' . ($build_meta['version'] ?? 'unknown') . PHP_EOL;
    exit(0);
}

$checks = [];
$add = function(string $name, bool $ok) use (&$checks) { $checks[$name] = $ok; };

$add('version_header_12_11_9_72', strpos($main, 'Version: 12.11.9.72') !== false);
$add('sige_version_constant_12_11_9_72', strpos($main, "define('SIGE_VERSION', '12.11.9.72')") !== false);
$add('build_json_version_12_11_9_72', strpos($build, '12.11.9.72') !== false && strpos($build, 'portaria-camera-permission-ux-pro') !== false);
$add('portaria_view_has_72_note', strpos($port, 'v12.11.9.72') !== false && strpos($port, 'getUserMedia') !== false);
$add('keeps_portaria_guard_permissions', strpos($port, "['portaria.ver','portaria.validar_acesso']") !== false && strpos($port, 'sige_page_guard') !== false);
$add('keeps_guarda_role_allowed', strpos($port, 'sige_guarda') !== false);
$add('uses_html5qrcode_cdn', strpos($port, 'sige_cdn_script("html5qrcode")') !== false);
$add('removes_default_scanner_dashboard_constructor', strpos($port, 'new Html5QrcodeScanner') === false);
$add('uses_direct_html5qrcode_constructor', strpos($port, "new Html5Qrcode('reader'") !== false || strpos($port, 'new Html5Qrcode("reader"') !== false);
$add('own_camera_permission_overlay', strpos($port, 'sg-camera-permission') !== false && strpos($port, 'Activar leitura por câmara') !== false);
$add('own_start_camera_button', strpos($port, 'id="sg-camera-start"') !== false && strpos($port, 'Permitir uso da câmara') !== false);
$add('start_button_type_button', strpos($port, '<button type="button" class="sg-camera-button" id="sg-camera-start"') !== false);
$add('button_bound_to_start_camera', strpos($port, "start.addEventListener('click'") !== false && strpos($port, 'sgPortariaStartCamera();') !== false);
$add('start_uses_get_user_media', strpos($port, 'navigator.mediaDevices.getUserMedia') !== false);
$add('secure_context_check', strpos($port, 'window.isSecureContext') !== false && strpos($port, 'SecurityError') !== false);
$add('friendly_permission_denied_message', strpos($port, 'Permissão negada') !== false && strpos($port, 'permita a câmara') !== false);
$add('friendly_https_message', strpos($port, 'HTTPS necessário') !== false || strpos($port, 'exige HTTPS') !== false);
$add('friendly_camera_busy_message', strpos($port, 'A câmara está ocupada') !== false);
$add('camera_state_classes', strpos($port, 'is-waiting') !== false && strpos($port, 'is-starting') !== false && strpos($port, 'is-running') !== false && strpos($port, 'is-error') !== false && strpos($port, 'is-denied') !== false);
$add('camera_controls_present', strpos($port, 'id="sg-camera-retry"') !== false && strpos($port, 'id="sg-camera-stop"') !== false && strpos($port, 'id="sg-camera-select"') !== false);
$add('camera_controls_bound', strpos($port, 'sgPortariaStopCamera(true)') !== false && strpos($port, 'sgPortariaSwitchCamera') !== false);
$add('camera_select_populated', strpos($port, 'sgPortariaPopulateCameras') !== false && strpos($port, 'Html5Qrcode.getCameras') !== false);
$add('camera_pick_back_camera', strpos($port, 'back|rear|traseira|environment') !== false);
$add('responsive_qrbox_function', strpos($port, 'qrbox: function(viewfinderWidth, viewfinderHeight)') !== false);
$add('scan_line_only_when_running', strpos($port, '.sg-camera-frame.is-running:after') !== false);
$add('manual_fallback_always_available', strpos($port, 'sg-portaria-manual') !== false && strpos($port, 'id="sg-portaria-manual-btn"') !== false);
$add('manual_input_mobile_numeric_text', strpos($port, 'type="text" id="manual-proc"') !== false && strpos($port, 'inputmode="numeric"') !== false && strpos($port, 'maxlength="20"') !== false);
$add('manual_input_client_sanitizes_digits', strpos($port, "manual.value = String(manual.value || '').replace(/[^0-9]/g, '')") !== false);
$add('result_panel_aria_busy_validation', strpos($port, "panel.setAttribute('aria-busy'") !== false);
$add('manual_enter_key_works', strpos($port, "e.key === 'Enter'") !== false && strpos($port, 'validarManual();') !== false);
$add('manual_no_inline_onclick', strpos($port, 'onclick="validarManual()"') === false);
$add('scan_deduplicates_same_code', strpos($port, 'sgPortariaLastScan') !== false && strpos($port, '< 3500') !== false);
$add('scan_requires_is_scanning_and_validating', strpos($port, 'if(!isScanning || isValidating || !codigo) return;') !== false);
$add('ajax_posts_nonce_and_origin', strpos($port, "action: 'sige_validar_acesso'") !== false && strpos($port, '_sige_nonce: portariaNonce') !== false && strpos($port, 'origem: origem') !== false);
$add('ajax_error_accepts_string_or_object', strpos($port, "typeof response.data === 'string'") !== false);
$add('result_panel_aria_live', strpos($port, 'id="result-panel" class="result-box waiting" aria-live="polite"') !== false && strpos($port, 'aria-atomic="true"') !== false && strpos($port, 'role="status"') !== false);
$add('camera_status_aria_live', strpos($port, 'id="sg-camera-status" aria-live="polite" role="status"') !== false);
$add('history_present', strpos($port, 'sg-portaria-history') !== false && strpos($port, 'sgPortariaAddHistory') !== false && strpos($port, 'slice(0, 5)') !== false);
$add('mobile_fit_min_width_zero', strpos($port, '.sg-portaria-v2 *{box-sizing:border-box;min-width:0;}') !== false);
$add('mobile_controls_single_column', strpos($port, '@media (max-width:760px)') !== false && strpos($port, '.sg-camera-actions{grid-template-columns:1fr;}') !== false);
$add('very_small_screen_guard', strpos($port, '@media (max-width:380px)') !== false);
$add('pagehide_stops_camera', strpos($port, "window.addEventListener('pagehide'") !== false && strpos($port, 'sgPortariaStopCamera(false)') !== false);
$add('visibilitychange_stops_camera', strpos($port, "document.addEventListener('visibilitychange'") !== false);
$add('server_unslash_qr_code', strpos($db, 'wp_unslash($_POST[\'qr_code\'])') !== false);
$add('server_limits_qr_numeric_length', strpos($db, 'substr($numero_processo, 0, 20)') !== false);
$add('server_selects_minimal_student_fields', strpos($db, 'SELECT id, nome_completo, foto, status, numero_processo') !== false);
$add('server_returns_processo', strpos($db, "'processo' => $" . 'aluno->numero_processo') !== false);
$add('server_keeps_ajax_permission', strpos($db, "['portaria.validar_acesso']") !== false && strpos($db, 'sige_ajax_user_can_permissions_or_caps') !== false);
$add('server_join_scoped_by_escola', strpos($db, 'm.turma_id = t.id AND m.escola_id = t.escola_id') !== false && strpos($db, 'AND t.escola_id = %d') !== false);
$add('permissions_guarda_unchanged', strpos($perm, "'guarda' =>") !== false && strpos($perm, "'portaria.ver','portaria.validar_acesso','alunos.ver'") !== false);
$add('global_bottom_nav_still_supports_sidebar_more', strpos($mobile, 'button[aria-controls="sige-sidebar"]') !== false && strpos($mobile, 'sige-mobile-bottom-nav') !== false);
$add('no_finance_terms_changed_in_portaria', strpos($port, 'mensalidade') === false && strpos($port, 'recibo') === false);

$failed = array_keys(array_filter($checks, fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "Smoke Portaria v12.11.9.72 FALHOU: " . count($failed) . "/" . count($checks) . " falhas\n");
    foreach ($failed as $name) fwrite(STDERR, " - {$name}\n");
    exit(1);
}

echo "Smoke Portaria Camera Permission UX v12.11.9.72 OK: " . count($checks) . "/" . count($checks) . " checks.\n";
