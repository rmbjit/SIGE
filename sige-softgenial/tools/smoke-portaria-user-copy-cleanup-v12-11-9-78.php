<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$main = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$port = file_get_contents($root . '/admin/system/portaria-view.php');
$safe = file_get_contents($root . '/includes/portaria-camera-safe-page.php');
$fail = 0; $ok = 0;
$check = function($cond, $msg) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK  - {$msg}\n"; }
    else { $fail++; echo "FAIL- {$msg}\n"; }
};
$has = fn($s,$n) => strpos((string)$s,(string)$n) !== false;
$notHas = fn($s,$n) => strpos((string)$s,(string)$n) === false;

$check($has($main, 'Version: 12.11.9.79') || $has($main, 'Version: 12.11.9.79'), 'Header do plugin actualizado para 12.11.9.78+');
$check($has($main, "define('SIGE_VERSION', '12.11.9.79');") || $has($main, "define('SIGE_VERSION', '12.11.9.79');"), 'SIGE_VERSION actualizado para 12.11.9.78+');
$check(in_array(($build['version'] ?? ''), ['12.11.9.79','12.11.9.79'], true), 'BUILD.json actualizado para 12.11.9.78+');
$check($has($build['build_id'] ?? '', 'portaria-copy-essencial-ux-pro') || $has($build['build_id'] ?? '', 'portaria-active-only-access-gate-pro'), 'Build id identifica copy essencial/active-only da Portaria');

foreach (['Iniciar câmara','Foto do QR','Processo manual','Ler próximo crachá','Câmara activa','Câmara indisponível','Só alunos activos'] as $term) {
    $check($has($safe.$port, $term), "Copy essencial presente: {$term}");
}
$check(
    $has($safe.$port, 'resultado aparece aqui mesmo') || $has($safe.$port, 'veja aqui se a entrada') || $has($safe.$port, 'Só alunos activos recebem autorização'),
    'Copy essencial presente: resultado visível no próprio fluxo'
);

foreach ([
    'fora do <strong>wp-admin</strong>',
    'wp-admin com resultado',
    'Página limpa',
    'página limpa',
    'Diagnóstico rápido da câmara',
    'Diagnóstico limpo da câmara',
    'Permissão API',
    'Sinal policy API',
    'Erro técnico',
    'O browser/sistema recusou',
    'browser bloquear',
    'Se o browser bloquear',
    'streaming',
    'Streaming',
    'controlador preso',
    'Windows/Edge',
    'servidor/CDN/plugins',
    'Permissions-Policy: camera=(self). Se este erro persistir',
    'Não foi possível contactar o servidor.',
    'policy API pode aparecer'
] as $forbidden) {
    $check($notHas($safe, $forbidden) && $notHas($port, $forbidden), "Copy técnica removida: {$forbidden}");
}

$check($has($safe, "function diag(c){var d=$('diag'); if(!d)return; d.hidden=true; d.innerHTML=''}"), 'Diagnóstico técnico não é apresentado no leitor');
$check($has($port, "function sgPortariaRenderDiagnostic(context)"), 'Função de diagnóstico continua como stub seguro');
$check($has($port, "box.hidden = true;"), 'Diagnóstico da página principal fica oculto');

foreach (['sige_validar_acesso', 'sige_portaria_acesso', 'capture="environment"', 'scanFile(file,true)', 'pauseScanner:true'] as $token) {
    $check($has($safe.$port, $token), "Funcionalidade preservada: {$token}");
}

if ($fail) { echo "\nFAILURES: {$fail}; OK: {$ok}\n"; exit(1); }
echo "\nSMOKE PORTARIA USER COPY CLEANUP v12.11.9.78 OK ({$ok}/{$ok})\n";
