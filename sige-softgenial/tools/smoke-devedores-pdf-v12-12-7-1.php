<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - Mapa de Cobranca (Lista de Devedores) em PDF - v12.12.7.1
 *
 * Prova, por inspeccao de codigo e do contrato do Kernel, que o novo
 * query handler sige_dev_print e definitivo, seguro e escalavel:
 *  - lê $_GET['sige_dev_print'] e valida o tipo;
 *  - exige login, nonce, permissao e tenant fail-closed;
 *  - reutiliza a saldo canonica e os mesmos estados do ecra;
 *  - e leitura pura (nunca escreve em tabelas financeiras);
 *  - tem regra no Security Kernel em enforce com runtime antecipado;
 *  - tem botao na Central de Cobrancas com URL nonce.
 */
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');

$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};

$handler = (string) @file_get_contents($root . '/includes/finance-devedores-pdf.php');
$view = (string) @file_get_contents($root . '/admin/finance/financeiro-devedores-view.php');
$boot = (string) @file_get_contents($root . '/sige-softgenial.php');

// ── 1. Ficheiro do handler e bootstrap ───────────────────────────────────────
$check($handler !== '', 'Handler includes/finance-devedores-pdf.php existe');
$check(strpos($boot, "includes/finance-devedores-pdf.php") !== false, 'Handler carregado no bootstrap (require_once)');

// ── 2. Query handler e validacao de entrada ──────────────────────────────────
$check(strpos($handler, "\$_GET['sige_dev_print']") !== false, 'Handler lê $_GET[sige_dev_print]');
$check(strpos($handler, "add_action('template_redirect'") !== false, 'Handler despacha em template_redirect');
$check(preg_match('/in_array\\(\\s*\\$tipo\\s*,\\s*\\[\\s*\'lista\'\\s*\\]/', $handler) === 1, 'Handler valida o tipo (whitelist lista)');

// ── 3. Seguranca: login, nonce, permissao, tenant fail-closed ────────────────
$check(strpos($handler, 'is_user_logged_in()') !== false, 'Handler exige utilizador autenticado');
$check(strpos($handler, "wp_verify_nonce(\$nonce, 'sige_dev_print')") !== false, 'Handler verifica o nonce sige_dev_print');
$check(strpos($handler, "sige_can('financeiro.cobrancas_ver'") !== false
    && strpos($handler, "'surface' => 'sige_dev_print'") !== false, 'Handler exige permissao SIGE com surface');
$check(strpos($handler, "sige_can('financeiro.ver'") === false, 'Handler NAO usa financeiro.ver (alinhado a pagina, sem alargar acesso)');
$check(strpos($handler, 'sige_get_escola_perfil()') !== false
    && strpos($handler, '->nome_escola') !== false
    && strpos($handler, '->logotipo') !== false, 'Identidade da escola pela fonte canonica (sige_get_escola_perfil: nome_escola/logotipo)');
$check(strpos($handler, "sige_get_config(") === false, 'Handler NAO depende de sige_get_config (funcao inexistente)');
$check(strpos($handler, 'current_user_can(\'sige_director\')') !== false, 'Handler tem fallback de papeis WP');
$check(preg_match('/\\$escola_id\\s*<=\\s*0/', $handler) === 1
    && strpos($handler, 'sige_get_escola_id()') !== false, 'Handler e tenant fail-closed (escola_id <= 0)');

// ── 4. Canonicidade financeira e leitura pura ────────────────────────────────
$check(strpos($handler, 'sige_fin_saldo_lancamento(') !== false, 'Dataset usa a fonte de verdade do ecra (sige_fin_saldo_lancamento, a mesma dos pagamentos)');
$check(strpos($handler, 'sige_aluno_activo_sql') !== false
    && strpos($handler, 'sige_matricula_activa_sql') !== false, 'Dataset replica a populacao do ecra (alunos activos com matricula activa)');
$check(strpos($handler, "status IN ('pendente', 'parcial')") !== false, 'Dataset usa os mesmos estados do ecra (pendente, parcial)');
$check(substr_count($handler, 'escola_id = %d') >= 4, 'Dataset filtra escola_id em todas as juncoes');
$has_write = preg_match('/\\b(INSERT\\s+INTO|UPDATE\\s+|DELETE\\s+FROM|->insert\\(|->update\\(|->delete\\(|->query\\()/i', $handler) === 1;
$check(!$has_write, 'Handler e leitura pura (sem INSERT/UPDATE/DELETE em tabelas financeiras)');
$check(strpos($handler, 'sige_audit_log(') !== false, 'Handler audita a emissao do documento');

// ── 5. Botao na Central de Cobrancas ─────────────────────────────────────────
$check(strpos($view, "'sige_dev_print' => 'lista'") !== false, 'View tem botao com sige_dev_print=lista');
$check(strpos($view, "wp_nonce_url(") !== false
    && strpos($view, "'sige_dev_print'") !== false, 'Botao usa URL com nonce sige_dev_print');
$check(strpos($view, 'target="_blank"') !== false, 'Botao abre em nova aba');
// O template de impressao JS antigo (Lista de Cobranca) permanece intacto.
$check(strpos($view, 'Lista de Cobrança') !== false, 'Template de impressao JS antigo (Lista de Cobranca) preservado');

// ── 6. Regra do Security Kernel ──────────────────────────────────────────────
require_once $root . '/includes/security-kernel-rules.php';
$rules = sige_security_kernel_rules();
$byId = [];
foreach ($rules as $r) { $byId[(string)($r['id'] ?? '')] = $r; }
$rule = $byId['query_handler:sige_dev_print'] ?? null;

$check(is_array($rule), 'Regra query_handler:sige_dev_print existe no Kernel');
if (is_array($rule)) {
    $check(($rule['mode'] ?? '') === 'enforce', 'Regra em enforce');
    $check(($rule['type'] ?? '') === 'query_handler', 'Regra do tipo query_handler');
    $check(!empty($rule['tenant_required']), 'Regra exige tenant');
    $hooks = isset($rule['runtime_hooks']) && is_array($rule['runtime_hooks']) ? $rule['runtime_hooks'] : [];
    $check(in_array('admin_init', $hooks, true) && in_array('parse_request', $hooks, true) && in_array('template_redirect', $hooks, true), 'Regra tem runtime_hooks completos (admin_init, parse_request, template_redirect)');
    $check((int)($rule['runtime_priority'] ?? 0) <= -1, 'Regra tem prioridade antecipada negativa');
    $intent = isset($rule['intent']) && is_array($rule['intent']) ? $rule['intent'] : [];
    $check(($intent['type'] ?? '') === 'nonce' && ($intent['action'] ?? '') === 'sige_dev_print', 'Regra tem intent nonce com action sige_dev_print');
    $check(!empty($rule['permissions']) && in_array('financeiro.cobrancas_ver', (array)$rule['permissions'], true), 'Regra declara permissoes de cobranca');
    $check(!in_array('financeiro.ver', (array)($rule['permissions'] ?? []), true), 'Regra NAO inclui financeiro.ver (acesso nao alargado)');
    $check(!empty($rule['rate_limit']) && !empty($rule['audit']), 'Regra tem rate limit e auditoria');
}

// ── Resultado ────────────────────────────────────────────────────────────────
echo str_repeat('-', 60) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE MAPA DE COBRANCA PDF FALHOU - ' . count($fails) . " verificacoes:\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'SMOKE MAPA DE COBRANCA PDF v12.12.7.1 OK - ' . $oks . " verificacoes passaram.\n";
exit(0);
