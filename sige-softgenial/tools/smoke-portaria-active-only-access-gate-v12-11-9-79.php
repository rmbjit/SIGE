<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke v12.11.9.79 - Portaria Active-Only Access Gate PRO. */
$root = dirname(__DIR__);
$checks = [];
$failed = [];
$check = function($cond, $label) use (&$checks, &$failed) { $checks[] = $label; if (!$cond) $failed[] = $label; };
$has = static fn($s, $needle) => strpos((string)$s, (string)$needle) !== false;
$main = file_get_contents($root . '/sige-softgenial.php');
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$db = file_get_contents($root . '/includes/db-handler.php');
$port = file_get_contents($root . '/admin/system/portaria-view.php');
$safe = file_get_contents($root . '/includes/portaria-camera-safe-page.php');
$perm = file_get_contents($root . '/includes/permissions-layer.php');
$sec = file_get_contents($root . '/includes/security-baseline-pro.php');

$check((bool)preg_match('/Version:\s*12\.11\.9\.(79|80)/', $main), 'Header do plugin em 12.11.9.79+');
$check((bool)preg_match("/define\('SIGE_VERSION', '12\.11\.9\.(79|80)'\);/", $main), 'SIGE_VERSION em 12.11.9.79+');
$check(in_array(($build['version'] ?? ''), ['12.11.9.79','12.11.9.80'], true), 'BUILD.json em 12.11.9.79+');
$check($has($build['build_id'] ?? '', 'portaria-active-only-access-gate-pro') || $has($build['build_id'] ?? '', 'portaria-state-consistency-gate-pro'), 'Build id identifica active-only/state consistency');
$check($has($main, 'Portaria Active-Only Access Gate PRO') && $has($main, 'Portaria State Consistency Gate PRO'), 'Changelog interno documenta a mudança e o hardening de estado');

$check($has($db, 'function sige_portaria_decisao_situacao'), 'Função de decisão operacional existe');
$check($has($db, "['activo','ativo','activa','ativa']"), 'Activo/ativo são aceites');
$check($has($db, "['suspenso','suspensa']"), 'Suspenso é bloqueável');
$check($has($db, "['transferido','transferida'"), 'Transferido é bloqueável');
$check($has($db, "['desistente','desistiu'"), 'Desistente é bloqueável');
$check($has($db, "['inactivo','inactiva','inativo','inativa']"), 'Inactivo é bloqueável');
$check($has($db, "Situação do aluno não definida. Não permitir entrada. Encaminhar à Secretaria."), 'Aluno sem situação definida bloqueia');
$check($has($db, "if ($" . "fonte === 'matricula')") && $has($db, "'label' => 'Activo'"), 'Matrícula sem status mantém compatibilidade');
$check($has($db, "$" . "acesso_permitido = !empty($" . "decisao_aluno['activo']) && !empty($" . "decisao_matricula['activo']);"), 'Gate exige aluno e matrícula activos');
$check($has($db, "'permitido' => $" . "acesso_permitido"), 'Resposta inclui permitido');
$check($has($db, "'bloqueado' => !$" . "acesso_permitido"), 'Resposta inclui bloqueado');
$check($has($db, 'ENTRADA AUTORIZADA') && $has($db, 'ACESSO BLOQUEADO'), 'Mensagens finais claras');
$check($has($db, "$" . "som = 'success'") && $has($db, "$" . "som = 'error'"), 'Som acompanha decisão');
$check($has($db, "$" . "acao = 'Permitir entrada'") && $has($db, "$" . "acao = 'Bloquear entrada'"), 'Acção acompanha decisão');
$check($has($db, 'Não permitir entrada. Encaminhar à Secretaria.'), 'Nota de bloqueio clara');
$check($has($db, 'm.status_matricula'), 'Consulta inclui situação da matrícula');
$check($has($db, 'ORDER BY CASE WHEN m.ano_lectivo = %d THEN 0 ELSE 1 END'), 'Ano lectivo corrente tem prioridade na matrícula');
$check($has($db, "'status_no_momento' => $" . "decisao_final['codigo']"), 'Histórico grava decisão');
$check($has($db, "['portaria.validar_acesso']"), 'Permissão AJAX preservada');
$check($has($db, 'sige_check_nonce_global()'), 'Nonce preservado');

$check($has($safe, 'function accessDecision') && $has($safe, 'boolTrue(d.acesso_permitido)||boolTrue(d.permitido)'), 'Leitor seguro usa decisão booleana explícita');
$check($has($safe, "status: ok?'Entrada autorizada':'Acesso bloqueado'"), 'Leitor seguro separa autorizado/bloqueado');
$check($has($safe, "play(dec.ok?'ok-audio':'err-audio')") || $has($safe, "play(ok?'ok-audio':'err-audio')"), 'Som no leitor seguro respeita decisão');
$check($has($safe, "buzz(dec.ok?'success':'error')") || $has($safe, "buzz(ok?'success':'error')"), 'Vibração no leitor seguro respeita decisão');
$check($has($safe, "showResult(dec.cls,dec.msg"), 'Overlay usa classe conforme decisão');
$check($has($safe, 'Não permitir entrada. Encaminhar à Secretaria.'), 'Overlay instrui bloqueio');
$check($has($safe, 'Só alunos activos recebem autorização'), 'Copy do leitor explica regra');
$check($has($safe, "'BLOQUEADO'"), 'Chip Bloqueado existe no leitor');

$check($has($port, 'permita a entrada apenas para alunos activos'), 'Copy admin explica regra');
$check($has($port, 'Suspensos, transferidos ou desistentes'), 'Copy admin cita estados bloqueados');
$check($has($port, 'Só alunos activos'), 'Chip admin reforça regra');
$check($has($port, 'd.permitido === true || d.acesso_permitido === true') || $has($port, 'sgPortariaBoolTrue(permitido)'), 'Admin usa booleano permitido');
$check($has($port, 'd.acao ||'), 'Admin usa acção do backend');
$check($has($port, "isBlocked ? 'BLOQUEADO' : 'ATENÇÃO'") || $has($port, "isBlocked ? 'Bloqueado' : 'Atenção'"), 'Admin distingue bloqueio');
$check($has($port, "sgPortariaSetText('r-action-help', isSuccess ?") || $has($port, "sgPortariaSetText('r-action-help', acao ||"), 'Admin mostra acção operacional');

$check($has($safe, 'sige_validar_acesso'), 'Endpoint oficial preservado');
$check($has($safe, "wp_create_nonce('sige_portaria_acesso')"), 'Nonce do leitor seguro preservado');
$check($has($safe, 'scan-result-overlay'), 'Resultado no próprio scanner preservado');
$check($has($safe, 'Ler próximo crachá'), 'Retake preservado');
$check($has($safe, 'Foto do QR'), 'Foto do QR preservada');
$check($has($safe, 'Processo manual'), 'Processo manual preservado');
$check($has($sec, 'camera=(self), microphone=(), geolocation=(), payment=()'), 'Câmara permitida na rota limpa');
$check($has($sec, 'camera=(), microphone=(), geolocation=(), payment=()'), 'Bloqueio global preservado');
$check($has($perm, "'guarda' => ['nome'=>'Guarda / Portaria'"), 'Perfil Guarda preservado');
$check($has($perm, "'permissions'=>['portaria.ver','portaria.validar_acesso','alunos.ver']"), 'Permissões Guarda restritas preservadas');

if ($failed) { foreach ($failed as $f) echo "FAIL: $f\n"; fwrite(STDERR, 'Smoke Portaria Active-Only v12.11.9.79 falhou: ' . count($failed) . ' falhas.' . PHP_EOL); exit(1); }
echo 'Smoke Portaria Active-Only v12.11.9.79 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
