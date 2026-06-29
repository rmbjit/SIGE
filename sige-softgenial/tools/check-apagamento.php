<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate do apagamento por anonimizacao (Fase 8 incremento 3).
 *
 * Primeira operacao destrutiva. Verifica que o motor redige PII mas e
 * destrutivo-governado (so UPDATE, nunca DELETE/DROP/TRUNCATE/ALTER), fail-closed
 * por escola, com lista branca de identificadores, lista de preservacao
 * (numero_processo, aluno_id, data_hora) e escola_id no WHERE; que o endpoint
 * admin_post tem guarda de permissao, nonce, contexto de escola, confirmacao em
 * dois passos por numero de processo e auditoria antes e depois; que o ecra nao
 * processa POST nem tem estilo inline; que a rota esta governada; que a permissao
 * critica esta registada, semeada, migrada e sempre auditada; que a regra do
 * Kernel existe em enforce e risco critico; e que o esquema nao mudou.
 */

if (!defined('SIGE_PRIVACY_TEST_MODE')) define('SIGE_PRIVACY_TEST_MODE', 1);

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};
$fails = [];

$engine  = $read('includes/privacy/pii-apagamento.php');
$handler = $read('includes/privacy/pii-anonimizar-handler.php');
$view    = $read('admin/system/privacidade-apagamento-view.php');
$shell   = $read('includes/admin-shell.php');
$boot    = $read('sige-softgenial.php');
$perms   = $read('includes/permissions-layer.php');
$kernel  = $read('includes/security-kernel-rules.php');
$uikit   = $read('assets/views/privacidade.css');
$mig     = $read('includes/class-sige-migration.php');

// 1. Modulos e funcoes nucleares.
if ($engine === '') {
    $fails[] = 'includes/privacy/pii-apagamento.php em falta';
} else {
    foreach ([
        'function sige_pii_apagamento_marcador', 'function sige_pii_apagamento_preservar',
        'function sige_pii_apagamento_pode_executar', 'function sige_pii_apagamento_classificar',
        'function sige_pii_apagamento_maxlen', 'function sige_pii_apagamento_sentinela_data',
        'function sige_pii_apagamento_resolver', 'function sige_pii_apagamento_colunas_meta',
        'function sige_pii_apagamento_alvos_tabela', 'function sige_pii_apagamento_plano',
        'function sige_pii_apagamento_esta_anonimizado', 'function sige_pii_apagamento_executar',
    ] as $fn) {
        if (strpos($engine, $fn) === false) { $fails[] = "funcao em falta no motor: {$fn}"; }
    }
}
if ($handler === '') {
    $fails[] = 'includes/privacy/pii-anonimizar-handler.php em falta';
} else {
    if (strpos($handler, "add_action('admin_post_sige_privacidade_apagar'") === false) { $fails[] = 'endpoint admin_post de apagamento nao registado'; }
    if (strpos($handler, 'function sige_privacidade_handle_apagar') === false) { $fails[] = 'handler de apagamento em falta'; }
}

// 2. Motor destrutivo-governado: redige (UPDATE) mas nunca elimina linhas nem altera esquema.
foreach (['->delete(', 'DELETE ', 'DROP ', 'ALTER ', 'TRUNCATE '] as $w) {
    if ($engine !== '' && strpos($engine, $w) !== false) { $fails[] = "motor tem operacao proibida ({$w}) - o apagamento e por anonimizacao, nunca por eliminacao"; }
}
if ($engine !== '' && strpos($engine, 'UPDATE ') === false) { $fails[] = 'motor nao redige (esperado UPDATE de anonimizacao)'; }
// Fail-closed por escola e pertenca.
if ($engine !== '' && strpos($engine, 'sige_pii_dossier_aluno_pertence') === false) { $fails[] = 'motor nao valida pertenca do aluno a escola'; }
if ($engine !== '' && strpos($engine, '$escola_id <= 0') === false) { $fails[] = 'motor nao e fail-closed por escola'; }
// Lista branca de identificadores de coluna antes do SQL.
if ($engine !== '' && strpos($engine, "preg_match('/^[a-z0-9_]+$/'") === false) { $fails[] = 'motor nao valida identificadores de coluna por lista branca'; }
// Lista de preservacao presente (pseudonimos/ligacao/estrutura).
foreach (['numero_processo', 'aluno_id', 'data_hora'] as $keep) {
    if ($engine !== '' && strpos($engine, "'{$keep}'") === false) { $fails[] = "lista de preservacao nao inclui {$keep}"; }
}
// escola_id no WHERE do UPDATE (isolamento).
if ($engine !== '' && strpos($engine, 'AND escola_id = %d') === false) { $fails[] = 'UPDATE de anonimizacao sem escola_id no WHERE (isolamento)'; }
// O motor nao deve auditar (cabe ao handler, com o utilizador).
if ($engine !== '' && strpos($engine, 'sige_permission_audit') !== false) { $fails[] = 'motor nao deve registar auditoria (cabe ao handler)'; }

// 3. Endpoint governado com confirmacao em dois passos.
if ($handler !== '') {
    if (strpos($handler, 'sige_pii_apagamento_pode_executar') === false) { $fails[] = 'handler sem guarda de permissao'; }
    if (strpos($handler, 'wp_die') === false) { $fails[] = 'handler nao recusa o acesso sem permissao (wp_die)'; }
    if (strpos($handler, 'check_admin_referer') === false) { $fails[] = 'handler sem verificacao de nonce'; }
    if (strpos($handler, 'sige_get_escola_id') === false) { $fails[] = 'handler sem contexto de escola'; }
    if (strpos($handler, 'sige_pii_dossier_aluno_pertence') === false) { $fails[] = 'handler nao revalida pertenca do aluno'; }
    // Confirmacao em dois passos por numero de processo.
    if (strpos($handler, 'confirmar_processo') === false) { $fails[] = 'handler sem campo de confirmacao (confirmar_processo)'; }
    if (strpos($handler, 'strcasecmp') === false) { $fails[] = 'handler nao compara o numero de processo escrito'; }
    if (strpos($handler, 'numero_processo') === false) { $fails[] = 'handler nao confirma contra o numero de processo do aluno'; }
    // Auditoria antes e depois.
    if (strpos($handler, "'apagar_inicio'") === false) { $fails[] = 'handler sem auditoria antes (apagar_inicio)'; }
    if (strpos($handler, "'apagar_fim'") === false) { $fails[] = 'handler sem auditoria depois (apagar_fim)'; }
    if (substr_count($handler, 'sige_permission_audit') < 2) { $fails[] = 'handler deve auditar antes e depois'; }
    if (strpos($handler, 'wp_safe_redirect') === false) { $fails[] = 'handler nao redirecciona em seguranca'; }
    if (strpos($handler, 'exit;') === false) { $fails[] = 'handler nao termina o pedido (exit)'; }
}

// 4. Ecra: nao processa POST e sem estilo inline; o apagamento vai para admin-post.php.
if ($view === '') {
    $fails[] = 'admin/system/privacidade-apagamento-view.php em falta';
} else {
    if (strpos($view, 'sige_pii_apagamento_pode_executar') === false) { $fails[] = 'ecra sem guarda de acesso'; }
    if (strpos($view, '$_POST') !== false) { $fails[] = 'ecra nao deve processar POST (o apagamento vai para admin-post.php)'; }
    if (strpos($view, 'admin-post.php') === false) { $fails[] = 'ecra nao aponta o apagamento para admin-post.php'; }
    if (strpos($view, 'name="action" value="sige_privacidade_apagar"') === false) { $fails[] = 'formulario de apagamento sem accao correcta'; }
    if (strpos($view, "wp_nonce_field('sige_privacidade_apagar')") === false) { $fails[] = 'formulario de apagamento sem campo de nonce'; }
    if (strpos($view, 'name="confirmar_processo"') === false) { $fails[] = 'formulario sem campo de confirmacao por numero de processo'; }
    if (preg_match('/\bstyle\s*=\s*["\']/', $view)) { $fails[] = 'ecra tem estilo inline (deve usar tokens)'; }
}

// 5. Rota governada.
if (strpos($shell, "'privacidade-apagamento' => 'admin/system/privacidade-apagamento-view.php'") === false) { $fails[] = 'rota privacidade-apagamento em falta no mapa de despacho'; }
if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $mAl)) {
    if (strpos($mAl[1], "'privacidade-apagamento'") === false) { $fails[] = 'privacidade-apagamento ausente da allowlist'; }
} else { $fails[] = 'allowlist nao encontrada'; }
if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $mPm)) {
    if (strpos($mPm[1], '"privacidade-apagamento"') === false) { $fails[] = 'privacidade-apagamento ausente da matriz'; }
    if (strpos($mPm[1], 'privacidade.apagamento_executar') === false) { $fails[] = 'matriz nao exige privacidade.apagamento_executar'; }
} else { $fails[] = 'matriz de permissoes nao encontrada'; }
if (strpos($shell, 'view=privacidade-apagamento') === false) { $fails[] = 'link de navegacao para apagamento em falta'; }

// 6. Modulos carregados.
if (strpos($boot, 'privacy/pii-apagamento.php') === false || strpos($boot, 'privacy/pii-anonimizar-handler.php') === false) {
    $fails[] = 'modulos de apagamento nao carregados em sige-softgenial.php';
}

// 7. Permissao registada, semeada, migrada e sempre auditada.
if (strpos($perms, "'privacidade.apagamento_executar'") === false) { $fails[] = 'permissao privacidade.apagamento_executar nao registada'; }
if (strpos($perms, 'function sige_permissions_migrate_121225_privacidade_apagamento') === false) { $fails[] = 'migracao da Incr 3 em falta'; }
if (strpos($perms, 'sige_permissions_migrate_121225_privacidade_apagamento()') === false || strpos($perms, "'12.12.25'") === false) { $fails[] = 'migracao da Incr 3 nao ligada ao maybe_install'; }
if (preg_match('/\$critical\s*=\s*in_array\([^;]*?\[(.*?)\]/s', $perms, $mc)) {
    if (strpos($mc[1], 'privacidade.apagamento_executar') === false) { $fails[] = 'apagamento nao consta da lista de auditoria critica'; }
} else { $fails[] = 'lista de auditoria critica nao encontrada'; }

// 8. Regra do Kernel em enforce e risco critico.
if (strpos($kernel, "'admin_post:sige_privacidade_apagar'") === false) { $fails[] = 'regra do Kernel para o apagamento em falta'; }
if (preg_match("/'admin_post:sige_privacidade_apagar'.*?'delegated_to'/s", $kernel, $mk)) {
    if (strpos($mk[0], "'mode' => 'enforce'") === false) { $fails[] = 'regra do Kernel do apagamento nao esta em enforce'; }
    if (strpos($mk[0], "'risk' => 'critical'") === false) { $fails[] = 'regra do Kernel do apagamento nao e de risco critico'; }
    if (strpos($mk[0], "privacidade.apagamento_executar") === false) { $fails[] = 'regra do Kernel do apagamento sem a permissao correcta'; }
} else { $fails[] = 'bloco da regra do Kernel do apagamento nao encontrado'; }

// 9. CSS da zona de perigo (classes do ecra de apagamento).
if (strpos($uikit, 'sige-priv-perigo') === false) { $fails[] = 'CSS da zona de perigo do ecra de apagamento em falta'; }

// 10. Sem migracao de esquema.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

// 11. Validacao funcional do resolver de redaccao (funcao pura, seguranca de tipo).
require_once $root . '/includes/privacy/pii-apagamento.php';
if (function_exists('sige_pii_apagamento_resolver')) {
    $casos = [
        ['meta' => ['coluna' => 'nome_completo', 'type' => 'varchar(255)', 'nullable' => false, 'maxlen' => 255], 'accao' => 'marcador', 'valor' => '[apagado]'],
        ['meta' => ['coluna' => 'genero', 'type' => 'char(1)', 'nullable' => false, 'maxlen' => 1], 'accao' => 'marcador', 'valor' => '['],
        ['meta' => ['coluna' => 'observacoes', 'type' => 'text', 'nullable' => true, 'maxlen' => null], 'accao' => 'marcador', 'valor' => '[apagado]'],
        ['meta' => ['coluna' => 'data_nascimento', 'type' => 'date', 'nullable' => false, 'maxlen' => null], 'accao' => 'sentinela', 'valor' => '1900-01-01'],
        ['meta' => ['coluna' => 'consentimento_em', 'type' => 'datetime', 'nullable' => true, 'maxlen' => null], 'accao' => 'anular', 'valor' => null],
        ['meta' => ['coluna' => 'temperatura', 'type' => 'decimal(4,1)', 'nullable' => true, 'maxlen' => null], 'accao' => 'anular', 'valor' => null],
        ['meta' => ['coluna' => 'consent_whatsapp', 'type' => 'tinyint(1)', 'nullable' => false, 'maxlen' => null], 'accao' => 'zero', 'valor' => 0],
        ['meta' => ['coluna' => 'estado', 'type' => "enum('a','b')", 'nullable' => true, 'maxlen' => null], 'accao' => 'manter', 'valor' => null],
    ];
    foreach ($casos as $c) {
        $r = sige_pii_apagamento_resolver($c['meta']);
        if (($r['accao'] ?? '') !== $c['accao']) {
            $fails[] = "resolver: {$c['meta']['coluna']} ({$c['meta']['type']}) esperava accao {$c['accao']}, obteve " . ($r['accao'] ?? 'nada');
        }
        if (($r['valor'] ?? null) !== $c['valor']) {
            $fails[] = "resolver: {$c['meta']['coluna']} esperava valor diferente do obtido";
        }
    }
    // O marcador nunca pode exceder o comprimento da coluna.
    $r1 = sige_pii_apagamento_resolver(['coluna' => 'x', 'type' => 'varchar(3)', 'nullable' => true, 'maxlen' => 3]);
    if (strlen((string) $r1['valor']) > 3) { $fails[] = 'resolver: marcador excede o comprimento maximo da coluna'; }
    // A lista de preservacao tem de conter exactamente os pseudonimos estruturais.
    $keep = sige_pii_apagamento_preservar();
    foreach (['numero_processo', 'aluno_id', 'data_hora'] as $k) {
        if (!in_array($k, $keep, true)) { $fails[] = "lista de preservacao em runtime nao inclui {$k}"; }
    }
} else {
    $fails[] = 'resolver de redaccao ausente';
}

if (!empty($fails)) {
    echo "GATE APAGAMENTO FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE APAGAMENTO OK - motor destrutivo-governado (so UPDATE, fail-closed por escola, lista branca, lista de preservacao, escola_id no WHERE), endpoint admin_post com permissao, nonce, confirmacao em dois passos e auditoria antes/depois, ecra sem POST nem estilo inline, rota governada, permissao critica semeada e auditada, regra do Kernel em enforce/critico, sem migracao de esquema, redaccao segura quanto ao tipo.\n";
exit(0);
