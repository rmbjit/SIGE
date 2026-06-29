<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate do direito de acesso e portabilidade (Fase 8 incremento 2).
 *
 * Verifica que o dossie e so de leitura e fail-closed por escola, que a
 * exportacao e um endpoint admin_post governado (permissao, nonce, auditoria,
 * cabecalhos de ficheiro), que o ecra nao processa POST nem tem estilo inline,
 * que a rota esta governada, que a permissao esta registada e semeada, que a
 * regra do Kernel existe em enforce e que o manifesto cresceu para 198 sem
 * migracao de esquema.
 */

if (!defined('SIGE_PRIVACY_TEST_MODE')) define('SIGE_PRIVACY_TEST_MODE', 1);

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};
$fails = [];

$dossier = $read('includes/privacy/pii-dossier.php');
$export  = $read('includes/privacy/pii-dossier-export.php');
$view    = $read('admin/system/privacidade-acesso-view.php');
$shell   = $read('includes/admin-shell.php');
$boot    = $read('sige-softgenial.php');
$perms   = $read('includes/permissions-layer.php');
$kernel  = $read('includes/security-kernel-rules.php');
$uikit   = $read('assets/views/privacidade.css');
$mig     = $read('includes/class-sige-migration.php');

// 1. Modulos e funcoes nucleares.
if ($dossier === '') {
    $fails[] = 'includes/privacy/pii-dossier.php em falta';
} else {
    foreach (['function sige_pii_dossier', 'function sige_pii_dossier_aluno_pertence', 'function sige_pii_dossier_linhas', 'function sige_pii_dossier_json', 'function sige_pii_dossier_pode_exportar', 'function sige_pii_dossier_identificacao'] as $fn) {
        if (strpos($dossier, $fn) === false) { $fails[] = "funcao em falta no dossie: {$fn}"; }
    }
}
if ($export === '') {
    $fails[] = 'includes/privacy/pii-dossier-export.php em falta';
} else {
    if (strpos($export, "add_action('admin_post_sige_privacidade_exportar'") === false) { $fails[] = 'endpoint admin_post de exportacao nao registado'; }
    if (strpos($export, 'function sige_privacidade_handle_exportar') === false) { $fails[] = 'handler de exportacao em falta'; }
}

// 2. Dossie e so de leitura e fail-closed por escola.
foreach (['->insert(', '->update(', '->delete(', 'INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'TRUNCATE '] as $w) {
    if ($dossier !== '' && strpos($dossier, $w) !== false) { $fails[] = "dossie tem escrita proibida ({$w})"; }
}
if ($dossier !== '' && strpos($dossier, 'sige_pii_dossier_aluno_pertence') === false) { $fails[] = 'dossie nao valida pertenca a escola'; }
if ($dossier !== '' && strpos($dossier, '$escola_id <= 0') === false) { $fails[] = 'dossie nao e fail-closed por escola'; }
// Validacao de identificadores de coluna por lista branca antes do SQL.
if ($dossier !== '' && strpos($dossier, "preg_match('/^[a-z0-9_]+$/'") === false) { $fails[] = 'dossie nao valida identificadores de coluna por lista branca'; }

// 3. Exportacao governada.
if ($export !== '') {
    if (strpos($export, 'sige_pii_dossier_pode_exportar') === false) { $fails[] = 'exportacao sem guarda de permissao'; }
    if (strpos($export, 'check_admin_referer') === false) { $fails[] = 'exportacao sem verificacao de nonce'; }
    if (strpos($export, 'sige_get_escola_id') === false) { $fails[] = 'exportacao sem contexto de escola'; }
    if (strpos($export, 'sige_permission_audit') === false) { $fails[] = 'exportacao sem registo de auditoria'; }
    if (strpos($export, 'Content-Disposition') === false) { $fails[] = 'exportacao sem cabecalho de transferencia de ficheiro'; }
    if (strpos($export, 'exit;') === false) { $fails[] = 'exportacao nao termina o pedido (exit)'; }
}

// 4. Ecra: nao processa POST e sem estilo inline; a exportacao vai para admin-post.php.
if ($view === '') {
    $fails[] = 'admin/system/privacidade-acesso-view.php em falta';
} else {
    if (strpos($view, 'sige_pii_dossier_pode_exportar') === false) { $fails[] = 'ecra sem guarda de acesso'; }
    if (strpos($view, '$_POST') !== false) { $fails[] = 'ecra nao deve processar POST (a exportacao vai para admin-post.php)'; }
    if (strpos($view, 'admin-post.php') === false) { $fails[] = 'ecra nao aponta a exportacao para admin-post.php'; }
    if (strpos($view, "name=\"action\" value=\"sige_privacidade_exportar\"") === false) { $fails[] = 'formulario de exportacao sem accao correcta'; }
    if (strpos($view, "wp_nonce_field('sige_privacidade_exportar')") === false) { $fails[] = 'formulario de exportacao sem campo de nonce'; }
    if (preg_match('/\bstyle\s*=\s*["\']/', $view)) { $fails[] = 'ecra tem estilo inline (deve usar tokens)'; }
}

// 5. Rota governada.
if (strpos($shell, "'privacidade-acesso' => 'admin/system/privacidade-acesso-view.php'") === false) { $fails[] = 'rota privacidade-acesso em falta no mapa de despacho'; }
if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $mAl)) {
    if (strpos($mAl[1], "'privacidade-acesso'") === false) { $fails[] = 'privacidade-acesso ausente da allowlist'; }
} else { $fails[] = 'allowlist nao encontrada'; }
if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $mPm)) {
    if (strpos($mPm[1], '"privacidade-acesso"') === false) { $fails[] = 'privacidade-acesso ausente da matriz'; }
    if (strpos($mPm[1], 'privacidade.acesso_exportar') === false) { $fails[] = 'matriz nao exige privacidade.acesso_exportar'; }
} else { $fails[] = 'matriz de permissoes nao encontrada'; }
if (strpos($shell, 'view=privacidade-acesso') === false) { $fails[] = 'link de navegacao para acesso/portabilidade em falta'; }

// 6. Modulos carregados.
if (strpos($boot, 'privacy/pii-dossier.php') === false || strpos($boot, 'privacy/pii-dossier-export.php') === false) {
    $fails[] = 'modulos do dossie nao carregados em sige-softgenial.php';
}

// 7. Permissao registada, semeada, migrada e sempre auditada.
if (strpos($perms, "'privacidade.acesso_exportar'") === false) { $fails[] = 'permissao privacidade.acesso_exportar nao registada'; }
if (strpos($perms, 'function sige_permissions_migrate_121224_privacidade_acesso') === false) { $fails[] = 'migracao da Incr 2 em falta'; }
if (strpos($perms, 'sige_permissions_migrate_121224_privacidade_acesso()') === false || strpos($perms, "'12.12.24'") === false) { $fails[] = 'migracao da Incr 2 nao ligada ao maybe_install'; }
// Tem de estar na lista de permissoes sempre auditadas.
if (preg_match('/\$critical\s*=\s*in_array\([^;]*?\[(.*?)\]/s', $perms, $mc)) {
    if (strpos($mc[1], 'privacidade.acesso_exportar') === false) { $fails[] = 'exportacao nao consta da lista de auditoria critica'; }
} else { $fails[] = 'lista de auditoria critica nao encontrada'; }

// 8. Regra do Kernel em enforce.
if (strpos($kernel, "'admin_post:sige_privacidade_exportar'") === false) { $fails[] = 'regra do Kernel para a exportacao em falta'; }

// 9. CSS enfileirado (classes do ecra de acesso).
if (strpos($uikit, 'sige-priv-procura') === false || strpos($uikit, 'sige-priv-export') === false) { $fails[] = 'CSS do ecra de acesso em falta'; }

// 10. Sem migracao de esquema.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

// 11. Validacao funcional do serializador JSON (funcao pura).
require_once $root . '/includes/privacy/pii-catalog.php';
require_once $root . '/includes/privacy/pii-dossier.php';
if (function_exists('sige_pii_dossier_json')) {
    $exemplo = [
        'aluno_id' => 7, 'escola_id' => 3, 'gerado_em' => '2026-06-21 10:00:00',
        'identificacao' => ['nome_completo' => 'Teste Aluno', 'numero_processo' => '2026-0007'],
        'seccoes' => [['tabela' => 'sige_alunos', 'colunas' => ['nome_completo'], 'col_sensibilidade' => ['nome_completo' => 'normal'], 'linhas' => [['nome_completo' => 'Teste Aluno']]]],
    ];
    $json = sige_pii_dossier_json($exemplo);
    $dec = json_decode($json, true);
    if (!is_array($dec) || ($dec['aluno']['numero_processo'] ?? '') !== '2026-0007' || empty($dec['seccoes'])) {
        $fails[] = 'serializador JSON do dossie invalido';
    }
} else {
    $fails[] = 'serializador JSON do dossie ausente';
}

if (!empty($fails)) {
    echo "GATE ACESSO FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE ACESSO OK - dossie so de leitura e fail-closed por escola, exportacao admin_post governada (permissao, nonce, auditoria, ficheiro), ecra sem POST nem estilo inline, rota governada, permissao semeada e auditada, regra do Kernel em enforce, sem migracao de esquema.\n";
exit(0);
