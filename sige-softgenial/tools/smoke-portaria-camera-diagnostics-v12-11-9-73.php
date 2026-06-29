<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE v12.11.9.73 Portaria Camera Permission Diagnostics Hotfix
 * Verifica diagnóstico de câmara, fallback desktop/tablet e preservação Guarda/Portaria.
 */
$root = dirname(__DIR__);
$files = [
    'main' => $root . '/sige-softgenial.php',
    'build' => $root . '/BUILD.json',
    'port' => $root . '/admin/system/portaria-view.php',
    'db' => $root . '/includes/db-handler.php',
    'ux' => $root . '/assets/mobile-tablet-ux.js',
];
foreach ($files as $label => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Ficheiro ausente: {$label} {$file}\n");
        exit(1);
    }
}
$main = file_get_contents($files['main']);
$build = file_get_contents($files['build']);
$port = file_get_contents($files['port']);
$db = file_get_contents($files['db']);
$ux = file_get_contents($files['ux']);

$build_meta = json_decode(file_get_contents($files['build']), true);
if (($build_meta['version'] ?? '') !== '12.11.9.73') {
    echo 'SKIP ' . basename(__FILE__) . ' - superseded by current build ' . ($build_meta['version'] ?? 'unknown') . PHP_EOL;
    exit(0);
}
$checks = [];
$add = function($name, $ok) use (&$checks) { $checks[$name] = (bool)$ok; };

// Versionamento
$add('version_header_12_11_9_73', strpos($main, 'Version: 12.11.9.73') !== false);
$add('sige_version_constant_12_11_9_73', strpos($main, "define('SIGE_VERSION', '12.11.9.73')") !== false);
$add('build_json_version_12_11_9_73', strpos($build, '12.11.9.73') !== false && strpos($build, 'portaria-camera-permission-diagnostics-hotfix') !== false);
$add('portaria_view_has_73_note', strpos($port, 'v12.11.9.73') !== false && strpos($port, 'Hotfix câmara/Edge') !== false);

// Diagnóstico de erros de câmara
$add('error_info_function_exists', strpos($port, 'function sgPortariaCameraErrorInfo') !== false);
$add('error_name_function_exists', strpos($port, 'function sgPortariaErrorName') !== false);
$add('constraint_error_function_exists', strpos($port, 'function sgPortariaIsConstraintError') !== false);
$add('permission_state_function_exists', strpos($port, 'async function sgPortariaCameraPermissionState') !== false);
$add('video_inputs_function_exists', strpos($port, 'async function sgPortariaVideoInputs') !== false);
$add('diagnostic_render_function_exists', strpos($port, 'function sgPortariaRenderDiagnostic') !== false);
$add('diagnostic_panel_exists', strpos($port, 'id="sg-camera-diagnostic"') !== false);
$add('not_allowed_mentions_global_browser_os', strpos($port, 'permissões globais do Edge/Chrome') !== false && strpos($port, 'sistema operativo') !== false);
$add('not_allowed_mentions_lock_absence', strpos($port, 'Quando “Câmara” não aparece no cadeado') !== false || strpos($port, 'Se não aparecer “Câmara” no cadeado') !== false);
$add('not_found_specific_copy', strpos($port, 'Nenhuma câmara detectada') !== false);
$add('not_readable_specific_copy', strpos($port, 'Câmara ocupada') !== false && strpos($port, 'Feche Teams/Zoom/WhatsApp/Camera') !== false);
$add('overconstrained_specific_copy', strpos($port, 'Câmara traseira não disponível') !== false);
$add('security_policy_copy', strpos($port, 'política do navegador, iframe ou permissões do servidor') !== false);
$add('technical_diagnostics_fields', strpos($port, 'Permissão API') !== false && strpos($port, 'Câmaras detectadas') !== false && strpos($port, 'Erro técnico') !== false);

// Fallback e selecção de câmara
$add('probe_permission_exists', strpos($port, 'async function sgPortariaProbePermission') !== false);
$add('probe_uses_generic_video_true_first', strpos($port, 'primeiro pedido sempre genérico') !== false && strpos($port, 'getUserMedia({ video: true, audio: false })') !== false);
$add('probe_records_phase', strpos($port, "phase: 'generic-getUserMedia'") !== false && strpos($port, "_sgPhase = 'generic-getUserMedia'") !== false);
$add('probe_stops_tracks', strpos($port, 'sgPortariaStopProbeStream') !== false && strpos($port, 'track.stop()') !== false);
$add('choose_camera_exists', strpos($port, 'function sgPortariaChooseCamera') !== false);
$add('choose_camera_prefers_back_labels', strpos($port, 'back|rear|traseira|environment|posterior') !== false);
$add('choose_camera_falls_back_first', strpos($port, "cameras[0].id") !== false);
$add('camera_config_uses_active_or_first', strpos($port, 'var firstAvailable = sgPortariaChooseCamera(sgPortariaCameras)') !== false);
$add('get_cameras_populates_selector', strpos($port, 'Html5Qrcode.getCameras') !== false && strpos($port, 'sgPortariaPopulateCameras(cameras)') !== false);
$add('probe_cameras_feed_getcameras_fallback', strpos($port, 'camerasFromProbe') !== false && strpos($port, 'device.deviceId') !== false);
$add('active_camera_uses_choose_camera', strpos($port, 'sgPortariaActiveCameraId = sgPortariaChooseCamera(cameras)') !== false || strpos($port, 'sgPortariaChooseCamera(camerasFromProbe)') !== false);
$add('start_fallback_tries_alternative_camera', strpos($port, 'html5qrcode-start-primary') !== false && strpos($port, 'html5qrcode-start-fallback') !== false);

// UX/UI Portaria preservada/melhorada
$add('camera_button_exists', strpos($port, 'id="sg-camera-start"') !== false && strpos($port, 'Permitir uso da câmara') !== false);
$add('camera_retry_stop_controls', strpos($port, 'id="sg-camera-retry"') !== false && strpos($port, 'id="sg-camera-stop"') !== false);
$add('camera_select_exists', strpos($port, 'id="sg-camera-select"') !== false);
$add('manual_fallback_exists', strpos($port, 'id="manual-proc"') !== false && strpos($port, 'sg-portaria-manual-btn') !== false);
$add('initial_copy_mentions_no_camera_in_lock', strpos($port, 'o cadeado não tiver “Câmara”') !== false || strpos($port, 'Se não aparecer “Câmara” no cadeado') !== false);
$add('tip_mentions_edge_windows_settings', strpos($port, 'No Edge/Windows') !== false && strpos($port, 'Definições > Câmara') !== false);
$add('result_panel_still_accessible', strpos($port, 'aria-live="polite"') !== false && strpos($port, 'role="status"') !== false);
$add('history_local_preserved', strpos($port, 'sgPortariaHistory') !== false && strpos($port, 'Últimas leituras') !== false);
$add('mobile_media_queries_preserved', strpos($port, '@media (max-width:760px)') !== false && strpos($port, '@media (max-width:380px)') !== false);

// Segurança / Guarda / backend
$add('page_guard_portaria_permissions', strpos($port, "['portaria.ver','portaria.validar_acesso']") !== false);
$add('guard_role_allowed_portaria', strpos($port, 'sige_guarda') !== false);
$add('ajax_nonce_preserved', strpos($port, 'sige_portaria_acesso') !== false && strpos($db, 'sige_portaria_acesso') !== false);
$add('ajax_action_preserved', strpos($port, "action: 'sige_validar_acesso'") !== false && strpos($db, 'sige_validar_acesso') !== false);
$add('ajax_permission_preserved', strpos($db, 'portaria.validar_acesso') !== false);
$add('guard_not_in_dashboard_regression_asset', strpos($ux, 'sg-app-menu-open') !== false);

$failed = array_keys(array_filter($checks, fn($ok) => !$ok));
if ($failed) {
    fwrite(STDERR, "Smoke Portaria Camera Diagnostics v12.11.9.73 FALHOU: " . count($failed) . "/" . count($checks) . " falhas\n");
    foreach ($failed as $name) fwrite(STDERR, " - {$name}\n");
    exit(1);
}
echo "Smoke Portaria Camera Diagnostics v12.11.9.73 OK: " . count($checks) . "/" . count($checks) . " checks.\n";
