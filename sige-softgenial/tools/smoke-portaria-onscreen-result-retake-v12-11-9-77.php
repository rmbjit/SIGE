<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke v12.11.9.77 - Portaria On-Screen Result + Retake UX PRO. */
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

$check((bool)preg_match('/Version:\s*12\.11\.9\.(77|78|79|80)/', $main), 'Header do plugin em 12.11.9.77+');
$check((bool)preg_match("/define\('SIGE_VERSION', '12\.11\.9\.(77|78|79|80)'\);/", $main), 'SIGE_VERSION em 12.11.9.77+');
$check(in_array(($build['version'] ?? ''), ['12.11.9.77','12.11.9.78','12.11.9.79','12.11.9.80'], true), 'BUILD.json em 12.11.9.77+');
$check($has($build['build_id'] ?? '', 'portaria-onscreen-result-retake-ux-pro') || $has($build['build_id'] ?? '', 'portaria-clean-copy-ux-pro') || $has($build['build_id'] ?? '', 'portaria-copy-essencial-ux-pro') || $has($build['build_id'] ?? '', 'portaria-active-only-access-gate-pro') || $has($build['build_id'] ?? '', 'portaria-state-consistency-gate-pro'), 'Build id identifica on-screen result/clean copy');
$check($has($main, 'includes/portaria-camera-safe-page.php'), 'Main inclui rota limpa da Portaria');
$check(is_file($root . '/includes/portaria-camera-safe-page.php'), 'Arquivo portaria-camera-safe-page.php existe');

$check($has($sec, 'sige_portaria_camera'), 'Security baseline reconhece ?sige_portaria_camera=1');
$check($has($sec, 'camera=(self), microphone=(), geolocation=(), payment=()'), 'Permissions-Policy permite camera=(self) só no escopo da Portaria');
$check($has($sec, 'camera=(), microphone=(), geolocation=(), payment=()'), 'Política global continua bloqueando câmara fora da Portaria');
$check($has($safe, "Permissions-Policy: camera=(self)"), 'Página limpa emite Permissions-Policy própria');
$check($has($safe, "Feature-Policy: camera 'self'"), 'Página limpa mantém Feature-Policy legado');

$check($has($safe, 'template_redirect'), 'Scanner limpo renderiza via template_redirect');
$check($has($safe, 'sige_portaria_camera_safe_url'), 'Helper de URL do scanner seguro existe');
$check($has($safe, 'sige_portaria_camera_safe_allowed'), 'Scanner seguro tem guard de acesso');
$check($has($safe, "'portaria.ver', 'portaria.validar_acesso'"), 'Scanner seguro exige permissões da Portaria');
$check($has($safe, "wp_create_nonce('sige_portaria_acesso')"), 'Scanner seguro cria nonce correcto');
$check($has($safe, 'sige_validar_acesso'), 'Scanner seguro valida pelo AJAX existente');
$check($has($safe, 'credentials:\'same-origin\''), 'Fetch preserva sessão WordPress');

$check($has($safe, 'scan-result-overlay'), 'Existe overlay de resultado no espaço da câmara');
$check($has($safe, 'frame.has-result'), 'Frame tem estado has-result para trocar scanner/resultado');
$check($has($safe, 'role="dialog" aria-live="assertive"'), 'Overlay de resultado é anunciado de forma acessível');
$check($has($safe, 'Ler próximo crachá'), 'Existe botão de retake/nova leitura');
$check($has($safe, 'overlay-manual-toggle'), 'Overlay permite abrir processo manual sem scroll');
$check($has($safe, 'overlay-manual-wrap'), 'Campo manual inline no overlay existe');
$check($has($safe, 'function showResult'), 'JS centraliza actualização do resultado');
$check($has($safe, 'function resetResult'), 'JS tem reset antes de nova leitura');
$check($has($safe, 'function overlayVisible'), 'JS controla visibilidade do overlay');
$check($has($safe, "validar(code,'camera-safe',{pauseScanner:true})"), 'QR lido pausa scanner antes de validar');
$check($has($safe, 'if(opts&&opts.pauseScanner)stopCam();'), 'Validação pode parar a câmara para evitar duplicados');
$check($has($safe, "next.addEventListener('click'"), 'Botão nova leitura reinicia scanner em um toque');
$check($has($safe, 'desktop-result{display:none}'), 'Mobile/tablet esconde resultado duplicado lateral');
$check($has($safe, 'min-height:min(64vh,430px)'), 'Scanner mobile cabe melhor no ecrã');
$check($has($safe, "buzz(dec.ok?'success':'error')") || $has($safe, "buzz(ok?'success':'error')") || ($has($safe, 'buzz(\'success\')') && $has($safe, 'buzz(\'error\')')), 'Haptic feedback para sucesso e erro quando suportado');
$check($has($safe, "play(dec.ok?'ok-audio':'err-audio')") || $has($safe, "play(ok?'ok-audio':'err-audio')") || ($has($safe, "play('ok-audio')") && $has($safe, "play('err-audio')")), 'Feedback sonoro preservado');
$check($has($safe, 'capture="environment"'), 'Fallback Foto do QR usa capture=environment');
$check($has($safe, 'scanFile(file,true)'), 'Fallback Foto do QR usa Html5Qrcode.scanFile');
$check($has($safe, 'BarcodeDetector'), 'Scanner seguro mantém fallback/primary nativo BarcodeDetector');
$check($has($safe, 'window.addEventListener(\'pagehide\''), 'Scanner liberta câmara em pagehide');
$check($has($safe, 'resultado aparece neste ecrã') || $has($safe, 'resultado aparece no próprio espaço da câmara') || $has($safe, 'Só alunos activos recebem autorização'), 'Texto de UX reflecte aprendizagem do mobile');

$check($has($port, '$sg_portaria_safe_camera_url'), 'Portaria admin constrói URL do leitor seguro');
$check($has($port, 'sgPortariaOpenSafeCamera'), 'Botão admin abre leitor seguro');
$check($has($port, 'Foto do QR'), 'Admin menciona fallback de foto no fluxo');

$check($has($db, "['portaria.validar_acesso']"), 'AJAX da Portaria continua protegido por portaria.validar_acesso');
$check($has($db, 'sige_portaria_acesso'), 'Nonce da Portaria continua aceite pelo validador global');
$check($has($db, 'sige_acessos'), 'Histórico de acessos continua preservado');
$check($has($db, 'numero_processo'), 'Validação continua baseada no processo do aluno');

if ($failed) {
    foreach ($failed as $f) echo "FAIL: {$f}\n";
    fwrite(STDERR, 'Smoke Portaria On-Screen Result v12.11.9.77+ falhou: ' . count($failed) . ' falhas.' . PHP_EOL);
    exit(1);
}
echo 'Smoke Portaria On-Screen Result v12.11.9.77+ OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
