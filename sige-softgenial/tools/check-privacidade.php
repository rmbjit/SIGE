<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate do inventario de dados pessoais (Fase 8 incremento 1).
 *
 * Verifica que o catalogo de PII e integro, que o inventario e so de leitura,
 * que o ecra nao tem escrita nem estilo inline, que esta governado (rota,
 * allowlist, matriz, navegacao), que os modulos sao carregados, que a permissao
 * esta registada e semeada, e que nao houve migracao de esquema.
 */

if (!defined('SIGE_PRIVACY_TEST_MODE')) define('SIGE_PRIVACY_TEST_MODE', 1);

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};

$fails = [];

$catalog = $read('includes/privacy/pii-catalog.php');
$invent  = $read('includes/privacy/pii-inventario.php');
$view    = $read('admin/system/privacidade-view.php');
$shell   = $read('includes/admin-shell.php');
$boot    = $read('sige-softgenial.php');
$perms   = $read('includes/permissions-layer.php');
$uikit   = $read('includes/ui-kit.php');
$css     = $read('assets/views/privacidade.css');
$mig     = $read('includes/class-sige-migration.php');

// 1. Modulos existem e definem as funcoes nucleares.
if ($catalog === '') {
    $fails[] = 'includes/privacy/pii-catalog.php em falta';
} else {
    foreach (['function sige_pii_catalogo', 'function sige_pii_categorias', 'function sige_pii_bases_legais', 'function sige_pii_catalogo_por_tabela'] as $fn) {
        if (strpos($catalog, $fn) === false) { $fails[] = "funcao em falta no catalogo: {$fn}"; }
    }
}
if ($invent === '') {
    $fails[] = 'includes/privacy/pii-inventario.php em falta';
} else {
    foreach (['function sige_privacidade_pode_aceder', 'function sige_pii_inventario', 'function sige_pii_cobertura', 'function sige_pii_agregados', 'function sige_pii_coluna_parece_pii', 'function sige_pii_contar'] as $fn) {
        if (strpos($invent, $fn) === false) { $fails[] = "funcao em falta no inventario: {$fn}"; }
    }
}

// 2. Integridade do catalogo (carregado em modo de teste).
if ($catalog !== '' && $invent !== '') {
    require_once $root . '/includes/privacy/pii-catalog.php';
    if (function_exists('sige_pii_catalogo') && function_exists('sige_pii_categorias') && function_exists('sige_pii_bases_legais')) {
        $cats = array_keys(sige_pii_categorias());
        $bases = array_keys(sige_pii_bases_legais());
        $entradas = sige_pii_catalogo();
        if (count($entradas) < 30) { $fails[] = 'catalogo demasiado pequeno (esperado pelo menos 30 campos)'; }
        $tem_sensivel = false;
        foreach ($entradas as $i => $e) {
            foreach (['tabela','coluna','categoria','sensibilidade','finalidade','base_legal'] as $k) {
                if (!array_key_exists($k, $e) || $e[$k] === '') { $fails[] = "entrada {$i} sem campo {$k}"; }
            }
            if (isset($e['categoria']) && !in_array($e['categoria'], $cats, true)) { $fails[] = "entrada {$i} com categoria invalida: {$e['categoria']}"; }
            if (isset($e['sensibilidade']) && !in_array($e['sensibilidade'], ['normal','sensivel'], true)) { $fails[] = "entrada {$i} com sensibilidade invalida"; }
            if (isset($e['base_legal']) && !in_array($e['base_legal'], $bases, true)) { $fails[] = "entrada {$i} com base legal invalida: {$e['base_legal']}"; }
            if (isset($e['sensibilidade']) && $e['sensibilidade'] === 'sensivel') { $tem_sensivel = true; }
        }
        if (!$tem_sensivel) { $fails[] = 'catalogo sem qualquer campo sensivel (saude/financeiro esperados)'; }
        // Tem de cobrir saude e a tabela de alunos.
        $tabelas = array_unique(array_map(static function ($e) { return $e['tabela']; }, $entradas));
        foreach (['sige_alunos', 'sige_jardim_saude', 'sige_professores'] as $obrig) {
            if (!in_array($obrig, $tabelas, true)) { $fails[] = "catalogo nao cobre tabela obrigatoria: {$obrig}"; }
        }
    } else {
        $fails[] = 'catalogo nao carregou em modo de teste';
    }
}

// 3. Inventario e so de leitura (sem escrita).
foreach (['->insert(', '->update(', '->delete(', 'INSERT ', 'UPDATE ', 'DELETE ', 'DROP ', 'ALTER ', 'TRUNCATE '] as $w) {
    if ($invent !== '' && strpos($invent, $w) !== false) { $fails[] = "inventario tem escrita proibida ({$w})"; }
}
// Fail-closed por escola presente.
if ($invent !== '' && strpos($invent, '$escola_id <= 0') === false) {
    $fails[] = 'inventario nao e fail-closed por escola';
}

// 4. View existe, tem guarda de acesso e e so de leitura, sem estilo inline.
if ($view === '') {
    $fails[] = 'admin/system/privacidade-view.php em falta';
} else {
    if (strpos($view, 'sige_privacidade_pode_aceder') === false) { $fails[] = 'view sem guarda de acesso'; }
    foreach (['$_POST', '<form', 'admin-post', 'wp_ajax', '->insert(', '->update(', '->delete('] as $w) {
        if (strpos($view, $w) !== false) { $fails[] = "view nao deve ter escrita/POST ({$w})"; }
    }
    if (strpos($view, 'sige_pii_inventario') === false) { $fails[] = 'view nao usa o inventario de dominio'; }
    if (preg_match('/\bstyle\s*=\s*["\']/', $view)) { $fails[] = 'view tem estilo inline (deve usar tokens)'; }
}

// 5. Governanca da rota: mapa + allowlist + matriz + navegacao.
if (strpos($shell, "'privacidade-dados' => 'admin/system/privacidade-view.php'") === false) {
    $fails[] = 'rota privacidade-dados em falta no mapa de despacho';
}
if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $mAl)) {
    if (strpos($mAl[1], "'privacidade-dados'") === false) { $fails[] = 'privacidade-dados ausente da allowlist (rota cai no painel inicial)'; }
} else {
    $fails[] = 'allowlist nao encontrada no admin-shell';
}
if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $mPm)) {
    if (strpos($mPm[1], '"privacidade-dados"') === false) { $fails[] = 'privacidade-dados ausente da matriz de permissoes'; }
    if (strpos($mPm[1], 'privacidade.inventario_ver') === false) { $fails[] = 'matriz nao exige privacidade.inventario_ver'; }
} else {
    $fails[] = 'matriz de permissoes nao encontrada no admin-shell';
}
if (strpos($shell, 'view=privacidade-dados') === false) {
    $fails[] = 'link de navegacao para o inventario em falta';
}

// 6. Modulos carregados no arranque.
if (strpos($boot, 'privacy/pii-catalog.php') === false || strpos($boot, 'privacy/pii-inventario.php') === false) {
    $fails[] = 'modulos de privacidade nao carregados em sige-softgenial.php';
}

// 7. Permissao registada, semeada e migrada.
if (strpos($perms, "'privacidade.inventario_ver'") === false) {
    $fails[] = 'permissao privacidade.inventario_ver nao registada no catalogo de permissoes';
}
if (strpos($perms, 'function sige_permissions_migrate_121223_privacidade') === false) {
    $fails[] = 'migracao de permissao da Fase 8 em falta';
}
if (strpos($perms, 'sige_permissions_migrate_121223_privacidade()') === false || strpos($perms, "'12.12.23'") === false) {
    $fails[] = 'migracao da Fase 8 nao esta ligada ao maybe_install';
}

// 8. CSS tokenizado existe e e enfileirado.
if ($css === '') { $fails[] = 'assets/views/privacidade.css em falta'; }
if (strpos($uikit, 'privacidade.css') === false) { $fails[] = 'privacidade.css nao e enfileirado no ui-kit'; }

// 9. Sem migracao de esquema: SCHEMA_VERSION inalterada.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) {
    $fails[] = 'SCHEMA_VERSION mudou (este incremento nao deve migrar o esquema)';
}

if (!empty($fails)) {
    echo "GATE PRIVACIDADE FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE PRIVACIDADE OK - inventario de PII so de leitura, catalogo integro (saude e financeiro sensiveis), ecra governado (rota, allowlist, matriz, navegacao), permissao semeada, sem migracao de esquema.\n";
exit(0);
