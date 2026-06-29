<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke v12.11.9.78 - Portaria Copy Essencial UX PRO. */
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
$safe = file_get_contents($root . '/includes/portaria-camera-safe-page.php');
$sec = file_get_contents($root . '/includes/security-baseline-pro.php');
$db = file_get_contents($root . '/includes/db-handler.php');

$check((bool)preg_match('/Version:\s*12\.11\.9\.(78|79|80)/', $main), 'Header do plugin em 12.11.9.78+');
$check((bool)preg_match("/define\('SIGE_VERSION', '12\.11\.9\.(78|79|80)'\);/", $main), 'SIGE_VERSION em 12.11.9.78+');
$check(in_array(($build['version'] ?? ''), ['12.11.9.78','12.11.9.79','12.11.9.80'], true), 'BUILD.json em 12.11.9.78+');
$check($has($build['build_id'] ?? '', 'portaria-copy-essencial-ux-pro') || $has($build['build_id'] ?? '', 'portaria-active-only-access-gate-pro') || $has($build['build_id'] ?? '', 'portaria-state-consistency-gate-pro'), 'Build id identifica Copy Essencial, Active-Only ou State Consistency');

$check($has($safe, '<h1>Leitor de crachás</h1>'), 'Leitor dedicado tem título simples');
$check($has($safe, 'Aponte para o QR Code'), 'Copy essencial orienta apontar QR');
$check($has($safe, 'Câmara pronta'), 'Estado inicial simples no leitor dedicado');
$check($has($safe, 'Iniciar câmara'), 'Botão principal simples no leitor dedicado');
$check($has($safe, 'Foto do QR'), 'Fallback Foto do QR preservado');
$check($has($safe, 'Processo manual'), 'Processo manual preservado');
$check($has($safe, 'Ler próximo crachá'), 'Botão de nova leitura preservado');
$check($has($safe, 'scan-result-overlay'), 'Resultado continua no espaço da câmara');
$check($has($safe, 'frame.has-result'), 'Estado has-result preservado');
$check($has($safe, 'role="dialog" aria-live="assertive"'), 'Acessibilidade do resultado preservada');
$check($has($safe, 'capture="environment"'), 'Foto do QR usa câmara traseira no mobile');
$check($has($safe, 'scanFile(file,true)'), 'Leitura de foto do QR preservada');
$check($has($safe, 'BarcodeDetector'), 'Fallback nativo de leitura QR preservado');
$check($has($safe, "window.addEventListener('pagehide'"), 'Câmara é libertada ao sair da página');
$check($has($safe, "function diag(c){var d=$('diag'); if(!d)return; d.hidden=true; d.innerHTML=''}"), 'Diagnóstico técnico oculto no leitor dedicado');

$check($has($port, '<h4 id="sg-camera-permission-title">Abrir câmara</h4>'), 'Portaria admin usa título simples');
$check($has($port, 'Toque em “Abrir câmara”. Depois da leitura'), 'Estado inicial simples no admin');
$check($has($port, 'Ecrã completo'), 'Abertura alternativa preservada com copy clara');
$check($has($port, 'Dica: no telemóvel, o resultado aparece no próprio leitor'), 'Dica mobile simples');
$check($has($port, 'sgPortariaOpenSafeCamera'), 'Botão continua a abrir leitor dedicado');
$check($has($port, "function sgPortariaRenderDiagnostic(context) {\n    var box = document.getElementById('sg-camera-diagnostic');\n    if (!box) return;\n    box.hidden = true;\n    box.innerHTML = '';\n}"), 'Diagnóstico técnico oculto no admin');

$forbidden = [
    'wp-admin',
    'página limpa',
    'Página limpa',
    'Diagnóstico rápido da câmara',
    'Diagnóstico limpo da câmara',
    'Permissão API',
    'Sinal policy API',
    'Erro técnico',
    'Fase:',
    'policy API',
    'browser',
    'streaming',
    'cache/servidor',
    'CDN',
    'plugin de segurança',
    'Edge/Windows',
    'controlador preso',
];
foreach ($forbidden as $needle) {
    $check(!$has($safe, $needle), 'Leitor dedicado sem copy técnica: ' . $needle);
    $check(!$has($port, $needle), 'Portaria admin sem copy técnica: ' . $needle);
}

$check($has($sec, 'sige_portaria_camera'), 'Security baseline reconhece o leitor dedicado');
$check($has($sec, 'camera=(self), microphone=(), geolocation=(), payment=()'), 'Permissão de câmara restrita ao escopo da Portaria preservada');
$check($has($sec, 'camera=(), microphone=(), geolocation=(), payment=()'), 'Bloqueio global da câmara fora da Portaria preservado');
$check($has($safe, "wp_create_nonce('sige_portaria_acesso')"), 'Nonce da Portaria preservado no leitor dedicado');
$check($has($safe, 'sige_validar_acesso'), 'Validação AJAX da Portaria preservada');
$check($has($db, "['portaria.validar_acesso']"), 'Endpoint continua protegido por portaria.validar_acesso');
$check($has($db, 'sige_portaria_acesso'), 'Nonce continua aceite pelo validador');
$check($has($db, 'sige_acessos'), 'Histórico de acessos preservado');
$check($has($db, 'numero_processo'), 'Validação por processo preservada');

if ($failed) {
    foreach ($failed as $f) echo "FAIL: {$f}\n";
    fwrite(STDERR, 'Smoke Portaria Copy Essencial v12.11.9.78 falhou: ' . count($failed) . ' falhas.' . PHP_EOL);
    exit(1);
}
echo 'Smoke Portaria Copy Essencial v12.11.9.78 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
