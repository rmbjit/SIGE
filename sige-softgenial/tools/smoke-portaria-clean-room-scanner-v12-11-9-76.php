<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke v12.11.9.76 - Portaria Camera Clean Room Scanner PRO. */
$root = dirname(__DIR__);
$checks = [];
$failed = [];
$check = function($cond, $label) use (&$checks, &$failed) {
    $checks[] = $label;
    if (!$cond) $failed[] = $label;
};
$has = fn($s, $needle) => strpos($s, $needle) !== false;
$main = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$port = file_get_contents($root . '/admin/system/portaria-view.php');
$sec = file_get_contents($root . '/includes/security-baseline-pro.php');
$safe = file_get_contents($root . '/includes/portaria-camera-safe-page.php');
$db = file_get_contents($root . '/includes/db-handler.php');

$check((bool)preg_match('/Version:\s*12\.11\.9\.(76|77|78|79|80)/', $main), 'Header do plugin em 12.11.9.76+');
$check((bool)preg_match("/define\('SIGE_VERSION', '12\.11\.9\.(76|77|78|79|80)'\);/", $main), 'SIGE_VERSION em 12.11.9.76+');
$check(in_array(($build['version'] ?? ''), ['12.11.9.76','12.11.9.77','12.11.9.78','12.11.9.79','12.11.9.80'], true), 'BUILD.json em 12.11.9.76+');
$check($has($build['build_id'] ?? '', 'portaria-camera-clean-room-scanner-pro') || $has($build['build_id'] ?? '', 'portaria-onscreen-result-retake-ux-pro') || $has($build['build_id'] ?? '', 'portaria-clean-copy-ux-pro') || $has($build['build_id'] ?? '', 'portaria-copy-essencial-ux-pro') || $has($build['build_id'] ?? '', 'portaria-active-only-access-gate-pro') || $has($build['build_id'] ?? '', 'portaria-state-consistency-gate-pro'), 'Build id identifica clean room scanner/on-screen result');
$check($has($main, "includes/portaria-camera-safe-page.php"), 'Main inclui rota limpa da Portaria');
$check(is_file($root . '/includes/portaria-camera-safe-page.php'), 'Arquivo portaria-camera-safe-page.php existe');

$check($has($sec, "sige_portaria_camera"), 'Security baseline reconhece ?sige_portaria_camera=1');
$check($has($sec, "camera=(self), microphone=(), geolocation=(), payment=()"), 'Permissions-Policy permite camera=(self) só no escopo de Portaria');
$check($has($sec, "camera=(), microphone=(), geolocation=(), payment=()"), 'Política global continua bloqueando câmara fora da Portaria');
$check($has($sec, "if (!function_exists('is_admin') || !is_admin()) return false;"), 'Fora do scanner limpo, front-end não ganha câmara por engano');

$check($has($safe, 'template_redirect'), 'Scanner limpo renderiza via template_redirect');
$check($has($safe, "sige_portaria_camera_safe_url"), 'Helper de URL do scanner seguro existe');
$check($has($safe, "sige_portaria_camera_safe_allowed"), 'Scanner seguro tem guard de acesso');
$check($has($safe, "portaria.ver','portaria.validar_acesso") || $has($safe, "'portaria.ver', 'portaria.validar_acesso'"), 'Scanner seguro exige permissões da Portaria');
$check($has($safe, "wp_create_nonce('sige_portaria_acesso')"), 'Scanner seguro cria nonce correcto');
$check($has($safe, "sige_validar_acesso"), 'Scanner seguro valida pelo AJAX existente');
$check($has($safe, "capture=\"environment\""), 'Fallback Foto do QR usa capture=environment');
$check($has($safe, "scanFile(file,true)"), 'Fallback Foto do QR usa Html5Qrcode.scanFile');
$check($has($safe, "BarcodeDetector"), 'Scanner seguro tem fallback/primary nativo BarcodeDetector');
$check($has($safe, "getUserMedia"), 'Scanner seguro usa WebRTC apenas no gesto de iniciar');
$check($has($safe, "window.addEventListener('pagehide'"), 'Scanner seguro liberta câmara em pagehide');
$check($has($safe, "Feature-Policy: camera 'self'"), 'Scanner seguro inclui Feature-Policy legado');

$check($has($port, '$sg_portaria_safe_camera_url'), 'Portaria admin constrói URL do leitor seguro');
$check($has($port, 'portariaSafeCameraUrl'), 'JS da Portaria recebe URL do leitor seguro');
$check($has($port, 'sgPortariaOpenSafeCamera'), 'Botão abre leitor seguro');
$check($has($port, "sgPortariaOpenSafeCamera();"), 'Start/retry chamam leitor seguro');
$check(!$has($port, "sgPortariaCameraPermissionState().then"), 'Admin não faz diagnóstico preventivo falso por Permission API');
$check($has($port, 'Leitor ainda não iniciado') || $has($port, 'Abrir câmara'), 'Status inicial orienta o utilizador para abrir a câmara');
$check($has($port, 'Ecrã completo') || $has($port, 'Abrir em nova aba'), 'UI permite abrir leitor em ecrã separado');
$check($has($port, 'Foto do QR'), 'UI menciona fallback de foto no fluxo');

$check($has($db, "['portaria.validar_acesso']"), 'AJAX da Portaria continua protegido por portaria.validar_acesso');
$check($has($db, "sige_portaria_acesso"), 'Nonce da Portaria continua aceite pelo validador global');
$check($has($db, "sige_acessos"), 'Histórico de acessos continua preservado');
$check($has($db, "numero_processo"), 'Validação continua baseada no processo do aluno');

if ($failed) {
    foreach ($failed as $f) echo "FAIL: {$f}\n";
    fwrite(STDERR, 'Smoke Portaria Clean Room Scanner v12.11.9.76+ falhou: ' . count($failed) . ' falhas.' . PHP_EOL);
    exit(1);
}
echo 'Smoke Portaria Clean Room Scanner v12.11.9.76+ OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
