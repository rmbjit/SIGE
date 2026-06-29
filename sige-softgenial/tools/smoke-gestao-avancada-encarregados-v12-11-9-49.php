<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.49
 * Gestão Avançada de Encarregados PRO
 */
$root = dirname(__DIR__);
$fail = [];
$ok = [];
$check = function($cond, $label) use (&$fail, &$ok) {
    if ($cond) { $ok[] = $label; }
    else { $fail[] = $label; }
};
$read = function($rel) use ($root) {
    $p = $root . '/' . $rel;
    return is_file($p) ? file_get_contents($p) : '';
};

$main = $read('sige-softgenial.php');
$mig  = $read('includes/class-sige-migration.php');
$dbh  = $read('includes/db-handler.php');
$ui   = $read('admin/academic/alunos_lista.php');
$fetch= $read('includes/aluno-fetch-ajax.php');
$notif= $read('includes/notificacoes-encarregados.php');
$adv  = $read('includes/encarregados-advanced.php');
$build= $read('BUILD.json');

$check(strpos($main, 'Version: 12.11.9.49') !== false, 'Plugin header actualizado para 12.11.9.49');
$check(strpos($main, "define('SIGE_VERSION', '12.11.9.49')") !== false, 'Constante SIGE_VERSION actualizada');
$check(strpos($main, "includes/encarregados-advanced.php") !== false, 'Helper avançado incluído no bootstrap');
$check(strpos($build, 'gestao-avancada-encarregados-pro') !== false, 'BUILD.json actualizado');
$check(is_file($root . '/CHANGELOG-v12-11-9-49-gestao-avancada-encarregados-pro.txt'), 'Changelog da versão presente');

$check(strpos($adv, 'function sige_guardian_adv_payload') !== false, 'Payload minimizado de encarregados criado');
$check(strpos($adv, 'function sige_guardian_adv_bool') !== false, 'Helper de consentimento com fallback criado');
$check(strpos($adv, "'pai_mae' => 'Pai/Mãe'") !== false, 'Label Pai/Mãe correcto');

foreach ([
    'encarregado_principal_tipo',
    'encarregado_principal_nome',
    'encarregado_principal_telemovel',
    'canal_preferencial_comunicacao',
    'consent_whatsapp',
    'consent_email',
    'consent_sms',
    'consent_chamada',
    'contacto_alternativo_nome',
    'contacto_alternativo_telemovel',
    'autorizado_buscar_nome',
    'autorizado_buscar_documento',
    'encarregado_observacoes',
    'consentimento_comunicacao_em'
] as $col) {
    $check(strpos($mig, $col) !== false, "Migração contempla {$col}");
}
$check(strpos($mig, "const SCHEMA_VERSION = '20260606.1'") !== false, 'Schema version incrementado');
$check(strpos($mig, "add_index_if_missing(\"{\$p}sige_alunos\", 'idx_guardian_principal'") !== false, 'Índice idx_guardian_principal aplicado em sige_alunos');
$matStart = strpos($mig, '// 9. sige_matriculas');
$matEnd = strpos($mig, '// 9.1. sige_alunos_importacao_lotes');
$matBlock = ($matStart !== false && $matEnd !== false) ? substr($mig, $matStart, $matEnd - $matStart) : '';
$check(strpos($matBlock, 'idx_guardian_principal') === false, 'Sem índice de encarregado indevido em sige_matriculas');

foreach ([
    'name="encarregado_principal_tipo"',
    'name="canal_preferencial_comunicacao"',
    'name="encarregado_principal_nome"',
    'name="contacto_alternativo_nome"',
    'name="autorizado_buscar_nome"',
    'name="encarregado_observacoes"',
    'name="consent_whatsapp" value="0"',
    'id="consent_sms" value="1"'
] as $needle) {
    $check(strpos($ui, $needle) !== false, "UI inclui {$needle}");
}
$check(strpos($ui, 'function sigeToggleOutroEncarregado') !== false, 'JS controla estado do outro encarregado');
$check(strpos($ui, 'sigeMaybeFillWhatsappFromPrincipal') !== false, 'JS ajuda a preencher WhatsApp principal');
$check(strpos($ui, 'Tel. do outro encarregado') !== false, 'Validação client-side do outro encarregado');

$check(strpos($dbh, '$cb_default') !== false, 'Servidor usa checkbox com default defensivo');
$check(strpos($dbh, "Quando selecciona “Outro encarregado principal”") !== false, 'Servidor valida outro encarregado principal');
$check(strpos($dbh, "'encarregado_principal_tipo'       =>") !== false, 'Servidor grava tipo de encarregado principal');
$check(strpos($dbh, "'canal_preferencial_comunicacao'   =>") !== false, 'Servidor grava canal preferencial');
$check(strpos($dbh, "'consent_whatsapp'                 => \$cb_default('consent_whatsapp', 1)") !== false, 'Servidor mantém WhatsApp permitido por padrão legado');

$check(strpos($fetch, "'avancado' => function_exists('sige_guardian_adv_payload')") !== false, 'Ficha 360 inclui payload avançado minimizado');
$check(strpos($fetch, 'Encarregado principal definido') !== false, 'Índice de qualidade avalia encarregado principal');
$check(strpos($fetch, 'Pessoa autorizada a buscar identificada') !== false, 'Índice de qualidade avalia autorização de recolha');

$check(strpos($notif, 'consent_whatsapp') !== false, 'Notificações respeitam consentimento WhatsApp');
$check(strpos($notif, 'consent_email') !== false, 'Notificações respeitam consentimento e-mail');
$check(strpos($notif, 'contacto_alternativo_telemovel') !== false, 'Notificações consideram contacto alternativo');
$check(strpos($notif, 'pessoa autorizada a buscar não recebe notificações financeiras') !== false, 'Pessoa autorizada a buscar excluída de notificações financeiras por defeito');

if ($fail) {
    echo "FALHOU\n";
    foreach ($fail as $f) echo " - {$f}\n";
    exit(1);
}

echo "OK - " . count($ok) . " verificações passaram.\n";
