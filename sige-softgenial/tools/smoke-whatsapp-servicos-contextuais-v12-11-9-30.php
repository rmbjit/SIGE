<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test v12.11.9.30 - WhatsApp PT-MZ / Serviços contextuais.
 * Executar a partir da raiz do plugin: php tools/smoke-whatsapp-servicos-contextuais-v12-11-9-30.php
 */
$root = dirname(__DIR__);
$files = [
    'tpl'    => $root . '/includes/whatsapp-templates-conversacional.php',
    'engine' => $root . '/includes/whatsapp-engine.php',
    'human'  => $root . '/includes/notification-humanization-pro.php',
    'cron'   => $root . '/includes/cron-tasks.php',
    'gerador'=> $root . '/admin/finance/financeiro-gerador.php',
];
$ok = 0; $fail = 0;
$check = function($cond, $label) use (&$ok, &$fail) {
    if ($cond) { echo "OK  - {$label}\n"; $ok++; }
    else { echo "FAIL - {$label}\n"; $fail++; }
};
foreach ($files as $k => $path) {
    $check(is_file($path), "ficheiro {$k} existe");
}
$tpl = file_get_contents($files['tpl']);
$engine = file_get_contents($files['engine']);
$human = file_get_contents($files['human']);
$cron = file_get_contents($files['cron']);
$gerador = file_get_contents($files['gerador']);

$check(strpos($tpl, '2026-06-01.v7-servicos-contextuais-pt-mz') !== false, 'versão do template WhatsApp v7 aplicada');
$check(strpos($tpl, 'sige_wpp_tpl_detectar_servicos_financeiros') !== false, 'detector de serviços financeiros existe');
$check(strpos($tpl, 'sige_wpp_tpl_contexto_pagamento') !== false, 'contexto de pagamento existe');
$check(strpos($tpl, 'sige_wpp_tpl_contexto_financeiro') !== false, 'contexto financeiro existe');
$check(strpos($tpl, 'sige_wpp_tpl_aluno_posse') !== false, 'referência possessiva do aluno existe');
$check(strpos($tpl, 'pagamento {contexto_pagamento}') !== false, 'aberturas de pagamento usam contexto real do serviço');
$check(strpos($tpl, 'informação financeira {contexto_financeiro}') !== false, 'mensagens de lançamento usam contexto financeiro');
$check(strpos($tpl, 'situação financeira {contexto_situacao}') !== false, 'cobranças usam contexto de situação');
$check(strpos($tpl, 'pagamento referente {aluno_ref}') === false, 'templates já não usam pagamento referente genérico');
$check(strpos($tpl, "('à matrícula de ' .") === false, 'fallback antigo à matrícula removido');
$check(strpos($tpl, "'da matrícula'") !== false, 'matrícula preservada apenas como tipo de serviço real');
$check(strpos($human, "pagamento dos serviços escolares de $1") !== false, 'humanizador não converte frase antiga para matrícula');
$check(strpos($engine, 'pagamento dos serviços escolares de {aluno}') !== false, 'fallback legacy de recibo usa serviços escolares');
$check(strpos($engine, 'referente à matrícula de {aluno}') === false, 'fallback legacy sem matrícula genérica');
$check(strpos($cron, 'serviços escolares de {$primeiro_nome}') !== false, 'cron fallback usa serviços escolares');
$check(strpos($gerador, 'serviços escolares de {$aluno_nome}') !== false, 'gerador fallback usa serviços escolares');
$check($fail === 0, 'resultado geral sem falhas');
echo "\nResumo: {$ok} OK / {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
