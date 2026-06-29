<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke v12.11.9.75 - Portaria Camera Safe Bootstrap PRO. */
$root = dirname(__DIR__);
$main = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
if (($build['version'] ?? '') !== '12.11.9.75') {
    echo 'SKIP ' . basename(__FILE__) . ' - superseded by current build ' . ($build['version'] ?? 'unknown') . PHP_EOL;
    exit(0);
}
$port = file_get_contents($root . '/admin/system/portaria-view.php');
$sec = file_get_contents($root . '/includes/security-baseline-pro.php');
$checks = [];
$check = function(bool $ok, string $label) use (&$checks) { $checks[] = [$ok, $label]; };
$has = fn($src, $needle) => strpos($src, $needle) !== false;

$check($has($main, 'Version: 12.11.9.75'), 'Header do plugin em 12.11.9.75');
$check($has($main, "define('SIGE_VERSION', '12.11.9.75');"), 'SIGE_VERSION em 12.11.9.75');
$check(($build['version'] ?? '') === '12.11.9.75', 'BUILD.json em 12.11.9.75');
$check($has($build['build_id'] ?? '', 'portaria-camera-safe-bootstrap-pro'), 'Build id identifica safe bootstrap');

$check($has($sec, 'function sige_sec_filter_wp_headers'), 'Filtro wp_headers reforça Permissions-Policy');
$check($has($sec, "camera=(self), microphone=(), geolocation=(), payment=()"), 'Portaria permite camera=(self) mantendo microfone/geolocalização/payment bloqueados');
$check($has($sec, "return 'camera=(), microphone=(), geolocation=(), payment=()';"), 'Restante SIGE continua com camera=()');
$check($has($sec, "REQUEST_URI"), 'Detecção de Portaria tem fallback por REQUEST_URI');

$check($has($port, 'v12.11.9.75'), 'Portaria marcada como v12.11.9.75');
$check($has($port, 'sgPortariaNativeStream'), 'Estado de stream nativo presente');
$check($has($port, 'sgPortariaNativeConstraintCandidates'), 'Constraints nativas seguras presentes');
$check($has($port, 'stream = await sgPortariaGetNativeStream(false);'), 'Bootstrap nativo antes do leitor QR');
$check($has($port, 'await sgPortariaSleep(750);'), 'Delay de libertação do controlador Edge/Windows');
$check($has($port, 'sgPortariaStartNativeQrFallback'), 'Fallback nativo com BarcodeDetector presente');
$check($has($port, "'BarcodeDetector' in window"), 'Detecção de BarcodeDetector presente');
$check($has($port, "formats: ['qr_code']"), 'Fallback restringido a QR Code');
$check($has($port, 'PolicyApiSignalOnly'), 'Policy API tratada como sinal/aviso');
$check($has($port, 'A testar câmara apesar do aviso de política'), 'Mobile não é bloqueado preventivamente por policy API');
$check(!$has($port, "if (sgPortariaCameraPolicyStatus() === 'bloqueada')"), 'Sem bloqueio directo por sgPortariaCameraPolicyStatus');
$check(!$has($port, "throw { name: 'PermissionsPolicyError'"), 'Sem throw preventivo de PermissionsPolicyError');
$check(!$has($port, 'O cadeado pode mostrar Câmara permitida, mas a política do documento bloqueou getUserMedia'), 'Mensagem antiga de falso alerta removida');
$check($has($port, 'Não encontrei uma aplicação aberta a usar a câmara'), 'NotReadableError explica controlador/browser sem culpar apenas apps abertas');
$check($has($port, 'sgPortariaStopNativePreview'), 'Stop limpa stream nativo');
$check($has($port, 'sgPortariaClearQrInstance(750)'), 'Fallback nativo limpa Html5Qrcode e aguarda libertação do controlador');
$check($has($port, 'pagehide'), 'Câmara libertada ao sair da página');

$failed = array_filter($checks, fn($c) => !$c[0]);
if ($failed) {
    foreach ($failed as $f) fwrite(STDERR, "FAIL: {$f[1]}\n");
    fwrite(STDERR, 'Smoke Portaria Camera Safe Bootstrap v12.11.9.75 falhou: ' . count($failed) . ' falhas.' . PHP_EOL);
    exit(1);
}
echo 'Smoke Portaria Camera Safe Bootstrap v12.11.9.75 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
