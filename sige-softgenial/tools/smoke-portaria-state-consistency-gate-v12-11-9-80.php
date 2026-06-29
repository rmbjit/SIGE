<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke v12.11.9.80 - Portaria State Consistency Gate PRO. */
$root = dirname(__DIR__);
$checks = [];
$failed = [];
$check = function($cond, $label) use (&$checks, &$failed) {
    $checks[] = $label;
    if (!$cond) $failed[] = $label;
};
$has = static fn($s, $needle) => strpos((string)$s, (string)$needle) !== false;
$main = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$db = file_get_contents($root . '/includes/db-handler.php');
$port = file_get_contents($root . '/admin/system/portaria-view.php');
$safe = file_get_contents($root . '/includes/portaria-camera-safe-page.php');
$sec = file_get_contents($root . '/includes/security-baseline-pro.php');
$perm = file_get_contents($root . '/includes/permissions-layer.php');

$check($has($main, 'Version: 12.11.9.80'), 'Header do plugin em 12.11.9.80');
$check($has($main, "define('SIGE_VERSION', '12.11.9.80');"), 'SIGE_VERSION em 12.11.9.80');
$check(($build['version'] ?? '') === '12.11.9.80', 'BUILD.json em 12.11.9.80');
$check($has($build['build_id'] ?? '', 'portaria-state-consistency-gate-pro'), 'Build id identifica State Consistency Gate');
$check($has($main, 'Portaria State Consistency Gate PRO'), 'Changelog interno documenta State Consistency Gate');

$check($has($db, '$acesso_permitido = !empty($decisao_aluno[\'activo\']) && !empty($decisao_matricula[\'activo\']);'), 'Backend continua exigindo aluno e matrícula activos');
$check($has($db, "'permitido' => $" . "acesso_permitido"), 'Backend devolve permitido');
$check($has($db, "'acesso_permitido' => $" . "acesso_permitido"), 'Backend devolve acesso_permitido');
$check($has($db, "'bloqueado' => !$" . "acesso_permitido"), 'Backend devolve bloqueado');
$check($has($db, "'resultado' => $" . "acesso_permitido ? 'autorizado' : 'bloqueado'"), 'Backend devolve resultado explícito');
$check($has($db, "'ui_estado' => $" . "acesso_permitido ? 'autorizado' : 'bloqueado'"), 'Backend devolve ui_estado explícito');
$check($has($db, "'ui_selo' => $" . "acesso_permitido ? 'AUTORIZADO' : 'BLOQUEADO'"), 'Backend devolve selo explícito');
$check($has($db, "'ui_classe' => $" . "acesso_permitido ? 'success' : 'error'"), 'Backend devolve classe visual explícita');
$check($has($db, "'ui_som' => $" . "acesso_permitido ? 'success' : 'error'"), 'Backend devolve som explícito');
$check($has($db, "'desistente','desistiu'"), 'Desistente continua bloqueável');
$check($has($db, "'suspenso','suspensa'"), 'Suspenso continua bloqueável');
$check($has($db, "'transferido','transferida'"), 'Transferido continua bloqueável');
$check($has($db, 'Situação do aluno não definida. Não permitir entrada. Encaminhar à Secretaria.'), 'Situação indefinida bloqueia');

$check($has($safe, 'function accessDecision'), 'Leitor seguro centraliza decisão em accessDecision');
$check($has($safe, 'boolTrue(d.acesso_permitido)||boolTrue(d.permitido)'), 'Leitor seguro usa permissões explícitas como fonte de verdade');
$check($has($safe, 'explicitlyBlocked=boolTrue(d.bloqueado)'), 'Bloqueado explícito vence no leitor seguro');
$check($has($safe, 'var ok=explicitlyAllowed&&!explicitlyBlocked;'), 'Estado final impede mistura de permitido/bloqueado');
$check(!$has($safe, "String(d.som||'').toLowerCase()==='success'"), 'Leitor seguro não autoriza por som');
$check(!$has($safe, "String(d.cor||'').toLowerCase()==='#4caf50'"), 'Leitor seguro não autoriza por cor');
$check($has($safe, "chip: ok?'AUTORIZADO':'BLOQUEADO'"), 'Chip do leitor segue decisão final');
$check($has($safe, "msg: ok?'ENTRADA AUTORIZADA':'ACESSO BLOQUEADO'"), 'Título do leitor segue decisão final');
$check($has($safe, "play(dec.ok?'ok-audio':'err-audio')"), 'Som do leitor segue decisão final');
$check($has($safe, "buzz(dec.ok?'success':'error')"), 'Vibração do leitor segue decisão final');
$check($has($safe, "showResult(dec.cls,dec.msg"), 'Classe/cor do overlay seguem decisão final');
$check($has($safe, 'body.sg-portaria-result-active .tools{display:none}'), 'Mobile foca no resultado e oculta controlos inferiores');
$check($has($safe, 'Ler próximo crachá') && $has($safe, 'Processo manual'), 'Acções essenciais continuam no overlay');

$check($has($port, 'function sgPortariaBoolTrue'), 'Portaria admin normaliza booleano');
$check($has($port, 'var explicitlyAllowed = sgPortariaBoolTrue(permitido);'), 'Portaria admin usa permitido explícito');
$check($has($port, 'var isSuccess = explicitlyAllowed && !isBlocked;'), 'Portaria admin impede sucesso quando há bloqueio');
$check(!$has($port, "String(cor).toLowerCase() === '#4caf50'"), 'Portaria admin não autoriza por cor');
$check(!$has($port, "String(som).toLowerCase() === 'success'"), 'Portaria admin não autoriza por som');
$check($has($port, "sgPortariaSetText('r-chip', isSuccess ? 'AUTORIZADO' :"), 'Chip admin segue decisão final');
$check($has($port, "sgPortariaPlay(isSuccess ? 'audio-success' : 'audio-error')"), 'Som admin segue decisão final');
$check($has($port, 'suspenso|transferido|desistente'), 'Admin detecta termos de bloqueio operacional');

$check($has($safe, 'sige_validar_acesso'), 'Endpoint AJAX preservado');
$check($has($safe, "wp_create_nonce('sige_portaria_acesso')"), 'Nonce preservado no leitor seguro');
$check($has($sec, 'camera=(self), microphone=(), geolocation=(), payment=()'), 'Rota limpa preserva camera=(self)');
$check($has($sec, 'camera=(), microphone=(), geolocation=(), payment=()'), 'Sistema fora da Portaria continua restritivo');
$check($has($perm, "'guarda' => ['nome'=>'Guarda / Portaria'"), 'Perfil Guarda preservado');
$check($has($perm, "'permissions'=>['portaria.ver','portaria.validar_acesso','alunos.ver']"), 'Permissões Guarda continuam restritas');

if ($failed) {
    foreach ($failed as $f) echo "FAIL: {$f}\n";
    fwrite(STDERR, 'Smoke Portaria State Consistency v12.11.9.80 falhou: ' . count($failed) . ' falhas.' . PHP_EOL);
    exit(1);
}
echo 'Smoke Portaria State Consistency v12.11.9.80 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
