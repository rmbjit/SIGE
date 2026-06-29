<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$ok = 0; $fail = 0;
$pass = function($m) use (&$ok){ $ok++; echo "[OK] {$m}\n"; };
$bad = function($m) use (&$fail){ $fail++; echo "[FAIL] {$m}\n"; };
$get = function($file) use ($root) { return file_get_contents($root . '/' . $file); };
$contains = function($file, $needle, $msg) use ($get, $pass, $bad) {
    $s = $get($file);
    (strpos($s, $needle) !== false) ? $pass($msg) : $bad($msg);
};

$main = $get('sige-softgenial.php');
$build = json_decode($get('BUILD.json'), true);

(isset($build['version']) && in_array($build['version'], ['12.11.9.24','12.11.9.25','12.11.9.26'], true)) ? $pass('BUILD.json em versão Fase 1 compatível') : $bad('BUILD.json não está em versão Fase 1 compatível');
(strpos($main, "define('SIGE_VERSION', '12.11.9.24')") !== false || strpos($main, "define('SIGE_VERSION', '12.11.9.25')") !== false || strpos($main, "define('SIGE_VERSION', '12.11.9.26')") !== false) ? $pass('SIGE_VERSION actualizado') : $bad('SIGE_VERSION não actualizado');
$contains('sige-softgenial.php', "includes/security-scope-guard.php", 'Security Scope Guard carregado no bootstrap');
$contains('sige-softgenial.php', "includes/backup-safety.php", 'Backup Safety carregado no bootstrap');
$contains('includes/email-engine.php', "sige_recibo_lookup_context", 'Recibo público resolve contexto por BD financeira');
$contains('includes/email-engine.php', '\'e\'           => max(0, $escola_id)', 'URL pública do recibo inclui escola');
$contains('includes/email-engine.php', '\'a\'           => max(0, $aluno_id)', 'URL pública do recibo inclui aluno');
$contains('includes/documents-engine.php', 'function sige_gerar_html_recibo_agrupado($recibo_numero, int $aluno_id_filtro = 0, int $escola_id_contexto = 0)', 'Renderer de recibo aceita escola explícita');
$contains('includes/documents-engine.php', "wp_die('Recibo não encontrado para este aluno.'", 'Fallback público que mostrava todos os itens foi removido');
$contains('includes/security-scope-guard.php', "tenant_scope_guard_denied", 'Guarda central regista tentativas cross-tenant');
$contains('includes/backup-safety.php', "sige_backup_config_snapshot", 'Snapshot de configuração antes de restauro disponível');
$contains('includes/db-handler.php', "sige_backup_config_snapshot('before_config_restore')", 'Restauro cria snapshot antes de aplicar JSON');
$contains('admin/academic/alunos_lista.php', "sige_secure_document_url((int)\$a->id, 'doc_bi_url')", 'BI na lista de alunos passa por endpoint seguro');

$roles = $get('includes/security-roles.php');
if (preg_match("/add_role\('sige_director'.*?'manage_options'\s*=>\s*true/s", $roles)) {
    $bad('sige_director ainda recebe manage_options');
} else {
    $pass('sige_director não recebe manage_options');
}

if (strpos($roles, "remove_cap('manage_options')") !== false) {
    $pass('Migração defensiva remove manage_options de roles SIGE');
} else {
    $bad('Não encontrei remoção defensiva de manage_options');
}

echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
