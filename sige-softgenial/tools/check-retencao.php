<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate da retencao e expurgo (Fase 8 incremento 4) - so leitura.
 *
 * Verifica que o motor de retencao e so de leitura (so SELECT COUNT, nunca
 * escreve nem elimina), fail-closed por escola, com lista branca de
 * identificadores; que o calendario cobre as categorias com dados pessoais com
 * prazo, base legal e coluna de data validas; que o ecra nao processa POST nem
 * tem estilo inline e encaminha para o Apagamento; que a rota esta governada;
 * que a permissao de leitura esta registada, semeada e migrada; que NAO ha nova
 * superficie destrutiva (sem admin_post, sem regra do Kernel); e que o esquema
 * nao mudou.
 */

if (!defined('SIGE_PRIVACY_TEST_MODE')) define('SIGE_PRIVACY_TEST_MODE', 1);

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};
$fails = [];

$engine = $read('includes/privacy/pii-retencao.php');
$view   = $read('admin/system/privacidade-retencao-view.php');
$shell  = $read('includes/admin-shell.php');
$boot   = $read('sige-softgenial.php');
$perms  = $read('includes/permissions-layer.php');
$kernel = $read('includes/security-kernel-rules.php');
$mig    = $read('includes/class-sige-migration.php');

// 1. Modulo e funcoes nucleares.
if ($engine === '') {
    $fails[] = 'includes/privacy/pii-retencao.php em falta';
} else {
    foreach ([
        'function sige_pii_retencao_pode_ver', 'function sige_pii_retencao_modos',
        'function sige_pii_retencao_calendario', 'function sige_pii_retencao_cutoff',
        'function sige_pii_retencao_contar_excedido', 'function sige_pii_retencao_panorama',
        'function sige_pii_retencao_prazo_legivel',
    ] as $fn) {
        if (strpos($engine, $fn) === false) { $fails[] = "funcao em falta no motor: {$fn}"; }
    }
}

// 2. Motor e so de leitura: nunca escreve nem elimina.
foreach (['->insert(', '->update(', '->delete(', 'INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'TRUNCATE '] as $w) {
    if ($engine !== '' && strpos($engine, $w) !== false) { $fails[] = "motor de retencao tem escrita proibida ({$w}) - deve ser so leitura"; }
}
if ($engine !== '' && strpos($engine, 'SELECT COUNT(*)') === false) { $fails[] = 'motor nao conta registos (esperado SELECT COUNT)'; }
if ($engine !== '' && strpos($engine, '$escola_id <= 0') === false) { $fails[] = 'motor nao e fail-closed por escola'; }
if ($engine !== '' && strpos($engine, "preg_match('/^[a-z0-9_]+$/'") === false) { $fails[] = 'motor nao valida identificadores por lista branca'; }
if ($engine !== '' && strpos($engine, 'AND escola_id = %d') === false) { $fails[] = 'contagem sem isolamento por escola_id'; }

// 3. Ecra: so leitura, sem POST nem estilo inline, encaminha para o Apagamento.
if ($view === '') {
    $fails[] = 'admin/system/privacidade-retencao-view.php em falta';
} else {
    if (strpos($view, 'sige_pii_retencao_pode_ver') === false) { $fails[] = 'ecra sem guarda de acesso'; }
    if (strpos($view, '$_POST') !== false) { $fails[] = 'ecra nao deve processar POST (e so leitura)'; }
    foreach (['admin-post.php', 'sige_privacidade_apagar', 'wp_nonce_field'] as $proibido) {
        if (strpos($view, $proibido) !== false) { $fails[] = "ecra de retencao nao deve conter accao destrutiva ({$proibido})"; }
    }
    if (strpos($view, 'privacidade-apagamento') === false) { $fails[] = 'ecra nao encaminha para o Apagamento'; }
    if (preg_match('/\bstyle\s*=\s*["\']/', $view)) { $fails[] = 'ecra tem estilo inline (deve usar tokens)'; }
}

// 4. Rota governada.
if (strpos($shell, "'privacidade-retencao' => 'admin/system/privacidade-retencao-view.php'") === false) { $fails[] = 'rota privacidade-retencao em falta no mapa de despacho'; }
if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $mAl)) {
    if (strpos($mAl[1], "'privacidade-retencao'") === false) { $fails[] = 'privacidade-retencao ausente da allowlist'; }
} else { $fails[] = 'allowlist nao encontrada'; }
if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $mPm)) {
    if (strpos($mPm[1], '"privacidade-retencao"') === false) { $fails[] = 'privacidade-retencao ausente da matriz'; }
    if (strpos($mPm[1], 'privacidade.retencao_ver') === false) { $fails[] = 'matriz nao exige privacidade.retencao_ver'; }
} else { $fails[] = 'matriz de permissoes nao encontrada'; }
if (strpos($shell, 'view=privacidade-retencao') === false) { $fails[] = 'link de navegacao para retencao em falta'; }

// 5. Modulo carregado.
if (strpos($boot, 'privacy/pii-retencao.php') === false) { $fails[] = 'modulo de retencao nao carregado em sige-softgenial.php'; }

// 6. Permissao registada, semeada e migrada (so leitura).
if (strpos($perms, "'privacidade.retencao_ver'") === false) { $fails[] = 'permissao privacidade.retencao_ver nao registada'; }
if (strpos($perms, 'function sige_permissions_migrate_121227_privacidade_retencao') === false) { $fails[] = 'migracao da Incr 4 em falta'; }
if (strpos($perms, 'sige_permissions_migrate_121227_privacidade_retencao()') === false || strpos($perms, "'12.12.27'") === false) { $fails[] = 'migracao da Incr 4 nao ligada ao maybe_install'; }

// 7. Sem nova superficie destrutiva: nenhuma regra do Kernel nova para retencao.
if (strpos($kernel, 'retencao') !== false || strpos($kernel, 'sige_privacidade_reter') !== false) { $fails[] = 'a retencao nao deve ter regra no Kernel (e so leitura)'; }

// 8. Sem migracao de esquema.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

// 9. Validacao funcional do calendario e funcoes puras.
require_once $root . '/includes/privacy/pii-retencao.php';
if (function_exists('sige_pii_retencao_calendario')) {
    $cal = sige_pii_retencao_calendario();
    if (count($cal) < 5) { $fails[] = 'calendario de retencao demasiado pequeno'; }
    $modos_validos = array_keys(sige_pii_retencao_modos());
    $tabelas = [];
    foreach ($cal as $entry) {
        foreach (['tabela', 'categoria', 'prazo_meses', 'base_legal', 'coluna_data', 'modo'] as $campo) {
            if (!isset($entry[$campo])) { $fails[] = "entrada do calendario sem campo {$campo}"; }
        }
        if (isset($entry['modo']) && !in_array($entry['modo'], $modos_validos, true)) { $fails[] = "modo de expurgo invalido: {$entry['modo']}"; }
        if (isset($entry['prazo_meses']) && (int) $entry['prazo_meses'] <= 0) { $fails[] = "prazo invalido para {$entry['tabela']}"; }
        if (isset($entry['coluna_data']) && !preg_match('/^[a-z0-9_]+$/', (string) $entry['coluna_data'])) { $fails[] = "coluna de data invalida para {$entry['tabela']}"; }
        $tabelas[] = $entry['tabela'] ?? '';
    }
    // Categorias com dever de integridade tem de constar e estar retidas ou anonimizaveis.
    foreach (['sige_alunos', 'sige_acessos', 'sige_fin_pagamentos'] as $obrig) {
        if (!in_array($obrig, $tabelas, true)) { $fails[] = "calendario nao cobre tabela obrigatoria: {$obrig}"; }
    }
    // O registo de acessos tem de estar RETIDO (fonte das presencas, nao expurgavel).
    foreach ($cal as $entry) {
        if (($entry['tabela'] ?? '') === 'sige_acessos' && ($entry['modo'] ?? '') !== 'retido') {
            $fails[] = 'registo de acessos deve estar retido (fonte das presencas)';
        }
    }
} else {
    $fails[] = 'calendario de retencao ausente';
}
// cutoff produz uma data no passado; prazo_legivel apresenta anos/meses.
if (function_exists('sige_pii_retencao_cutoff')) {
    $cut = sige_pii_retencao_cutoff(12);
    if (strtotime($cut) >= time()) { $fails[] = 'cutoff de retencao nao recua no tempo'; }
}
if (function_exists('sige_pii_retencao_prazo_legivel')) {
    if (sige_pii_retencao_prazo_legivel(120) !== '10 anos') { $fails[] = 'prazo_legivel incorrecto para 120 meses'; }
    if (sige_pii_retencao_prazo_legivel(18) !== '1 ano e 6 meses') { $fails[] = 'prazo_legivel incorrecto para 18 meses'; }
}

if (!empty($fails)) {
    echo "GATE RETENCAO FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE RETENCAO OK - motor so de leitura (SELECT COUNT, fail-closed por escola, lista branca, isolamento), calendario integro (acessos retidos como fonte das presencas), ecra sem POST nem estilo inline e a encaminhar para o Apagamento, rota governada, permissao de leitura semeada e migrada, sem nova superficie destrutiva, sem migracao de esquema.\n";
exit(0);
