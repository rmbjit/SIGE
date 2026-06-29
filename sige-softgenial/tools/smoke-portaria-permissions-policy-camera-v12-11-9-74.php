<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$main = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
if (($build['version'] ?? '') !== '12.11.9.74') {
    echo 'SKIP ' . basename(__FILE__) . ' - superseded by current build ' . ($build['version'] ?? 'unknown') . PHP_EOL;
    exit(0);
}
$sec = file_get_contents($root . '/includes/security-baseline-pro.php');
$port = file_get_contents($root . '/admin/system/portaria-view.php');
$db = file_get_contents($root . '/includes/db-handler.php');
$ok = 0; $fail = 0;
function check_v74($cond, $msg) { global $ok, $fail; if ($cond) { $ok++; echo "[OK] $msg\n"; } else { $fail++; echo "[FAIL] $msg\n"; } }
function has_v74($haystack, $needle) { return strpos($haystack, $needle) !== false; }

check_v74(has_v74($main, 'Version: 12.11.9.74'), 'Header do plugin em 12.11.9.74');
check_v74(has_v74($main, "define('SIGE_VERSION', '12.11.9.74');"), 'SIGE_VERSION em 12.11.9.74');
check_v74(($build['version'] ?? '') === '12.11.9.74', 'BUILD.json em 12.11.9.74');
check_v74(has_v74($build['build_id'] ?? '', 'camera-policy-edge-fallback-hotfix'), 'Build id identifica hotfix Permissions-Policy');

check_v74(has_v74($sec, 'function sige_sec_is_portaria_camera_request()'), 'Helper detecta request da Portaria');
check_v74(has_v74($sec, "\$page === 'sige-app' && \$view === 'portaria'"), 'Detecção restrita a admin.php?page=sige-app&view=portaria');
check_v74(has_v74($sec, 'function sige_sec_permissions_policy_header()'), 'Fonte única para Permissions-Policy');
check_v74(has_v74($sec, "return 'camera=(self), microphone=(), geolocation=(), payment=()';"), 'Portaria recebe camera=(self)');
check_v74(has_v74($sec, "return 'camera=(), microphone=(), geolocation=(), payment=()';"), 'Restante SIGE continua com camera=()');
check_v74(has_v74($sec, "header('Permissions-Policy: ' . sige_sec_permissions_policy_header(), true);"), 'Header Permissions-Policy é substituível e dinâmico');
check_v74(has_v74($sec, "add_action('send_headers', 'sige_sec_add_headers', 999);"), 'Reforço tardio send_headers preservado');
check_v74(has_v74($sec, "add_action('admin_init', 'sige_sec_add_headers', 999);"), 'Reforço tardio admin_init preservado');
check_v74(!has_v74($sec, "header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');"), 'Header estático camera=() removido');

check_v74(has_v74($port, 'v12.11.9.74'), 'Portaria marcada como v12.11.9.74');
check_v74(has_v74($port, 'function sgPortariaCameraPolicyState()'), 'JS detecta política do documento');
check_v74(has_v74($port, 'document.permissionsPolicy') && has_v74($port, 'document.featurePolicy'), 'Compatibilidade PermissionsPolicy/FeaturePolicy');
check_v74(has_v74($port, "allowsFeature('camera') ? 'permitida' : 'bloqueada'"), 'Diagnóstico distingue política permitida/bloqueada');
check_v74(has_v74($port, 'Política do documento'), 'Diagnóstico mostra política do documento');
check_v74(has_v74($port, 'PermissionsPolicyError'), 'Erro específico para Permissions-Policy');
check_v74(has_v74($port, "throw { name: 'PermissionsPolicyError', _sgPhase: 'permissions-policy'"), 'Start bloqueia com diagnóstico específico se policy ainda bloquear');
check_v74(has_v74($port, 'initial-policy-check'), 'Inicialização diagnostica policy antes do clique');
check_v74(has_v74($port, 'camera=()'), 'Mensagem técnica orienta procurar header camera=()');
check_v74(has_v74($port, "sgPortariaSetCameraStatus('waiting', 'Permissão anterior possivelmente bloqueada'"), 'Estado denied inicial já não é tratado como bloqueio final');
check_v74(!has_v74($port, "sgPortariaSetCameraStatus('denied', 'Câmara bloqueada para este site'"), 'False alert inicial removido');
check_v74(has_v74($port, "navigator.mediaDevices.getUserMedia({ video: true, audio: false })"), 'Pedido inicial continua genérico video:true');
check_v74(has_v74($port, 'Html5Qrcode.getCameras'), 'Enumeração de câmaras preservada');
check_v74(has_v74($port, 'sgPortariaChooseCamera'), 'Escolha de câmara preservada');
check_v74(has_v74($port, 'sgPortariaStopProbeStream'), 'Probe stream é encerrado após pedido');
check_v74(has_v74($port, 'sgPortariaRenderDiagnostic'), 'Diagnóstico visível preservado');
check_v74(has_v74($port, 'sgPortariaSetCameraStatus(info.mode, info.title, info.text)'), 'Status da UI usa erro classificado');
check_v74(has_v74($port, 'mostrarResultado(\'ERRO\', info.resultName'), 'Painel de resultado reflecte falhas de câmara');

check_v74(has_v74($port, "['portaria.ver','portaria.validar_acesso']"), 'Guard da página Portaria preservado');
check_v74(has_v74($db, 'portaria.validar_acesso'), 'Endpoint de validação continua protegido por portaria.validar_acesso');
check_v74(has_v74($main, 'Role Guarda') || has_v74($main, 'Guarda'), 'Histórico do role Guarda preservado no plugin principal');
check_v74(has_v74($port, 'sige_guarda'), 'Perfil Guarda continua autorizado na Portaria');

if ($fail) { echo "\nFAILURES: $fail\n"; exit(1); }
echo "\nSMOKE v12.11.9.74 OK - $ok checks\n";
