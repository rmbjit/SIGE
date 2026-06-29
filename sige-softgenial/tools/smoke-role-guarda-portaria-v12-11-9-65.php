<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test estático - v12.11.9.65 Role Guarda / Portaria PRO.
 */
$root = dirname(__DIR__);
$checks = [];
$failures = [];

$read = static function (string $rel) use ($root): string {
    $path = $root . '/' . ltrim($rel, '/');
    if (!is_file($path)) throw new RuntimeException("Ficheiro não encontrado: {$rel}");
    return file_get_contents($path);
};
$must = static function (string $label, bool $condition) use (&$checks, &$failures): void {
    $checks[] = $label;
    if (!$condition) $failures[] = $label;
};

$main = $read('sige-softgenial.php');
$build = json_decode($read('BUILD.json'), true);
$permissions = $read('includes/permissions-layer.php');
$roles = $read('includes/security-roles.php');
$shell = $read('includes/admin-shell.php');
$portaria = $read('admin/system/portaria-view.php');
$alunos = $read('admin/academic/alunos_lista.php');
$alunoAjax = $read('includes/aluno-fetch-ajax.php');
$db = $read('includes/db-handler.php');
$ajax = $read('includes/ajax-handlers.php');
$permsUi = $read('admin/system/permissions-ui.php');
$rh = $read('admin/hr/equipe-view.php');

$must('Header Version = 12.11.9.65', strpos($main, '* Version: 12.11.9.65') !== false);
$must('SIGE_VERSION = 12.11.9.65', strpos($main, "define('SIGE_VERSION', '12.11.9.65')") !== false);
$must('BUILD.json version = 12.11.9.65', is_array($build) && ($build['version'] ?? '') === '12.11.9.65');
$must('BUILD.json build_id identifica Guarda/Portaria', is_array($build) && strpos((string)($build['build_id'] ?? ''), 'guarda-portaria') !== false);

$must('Permissão portaria.ver registada', strpos($permissions, "'portaria.ver'") !== false);
$must('Permissão portaria.validar_acesso registada', strpos($permissions, "'portaria.validar_acesso'") !== false);
$must('Role matriz guarda registada', strpos($permissions, "'guarda' => ['nome'=>'Guarda / Portaria'") !== false);
$must('Role matriz guarda tem exactamente Portaria + Alunos ver', strpos($permissions, "'permissions'=>['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false);
$must('Role matriz guarda não recebe alunos.criar', !preg_match("/'guarda'\s*=>\s*\[[^\]]*'alunos\.criar'/s", $permissions));
$must('Role matriz guarda não recebe alunos.editar', !preg_match("/'guarda'\s*=>\s*\[[^\]]*'alunos\.editar'/s", $permissions));
$must('Role matriz guarda não recebe alunos.apagar', !preg_match("/'guarda'\s*=>\s*\[[^\]]*'alunos\.apagar'/s", $permissions));
$must('Migração v12.11.9.65 recria perfil guarda fechado', strpos($permissions, 'sige_permissions_migrate_1211965_guarda_portaria') !== false && strpos($permissions, '$wpdb->delete($t[\'role_permissions\'], [\'role_id\' => $role_id]') !== false);
$must('sige_can aplica allowlist ao perfil guarda', strpos($permissions, 'guarda_scope_denied') !== false && strpos($permissions, "['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false);
$must('Mapeamento role guarda -> sige_guarda', strpos($permissions, "'guarda'            => 'sige_guarda'") !== false);
$must('Mapeamento sige_guarda -> guarda', strpos($permissions, "'sige_guarda'            => 'guarda'") !== false);

$must('WP role sige_guarda criada', strpos($roles, "add_role('sige_guarda'") !== false);
$must('WP role sige_guarda sem manage_options explícito', !preg_match("/add_role\('sige_guarda'.*'manage_options'\s*=>\s*true/s", $roles));
$must('manage_options removido de sige_guarda', strpos($roles, "'sige_assistente','sige_recepcao','sige_guarda'") !== false);

$must('Portaria usa permissão própria no guard', strpos($portaria, "['portaria.ver','portaria.validar_acesso']") !== false && strpos($portaria, "'sige_guarda'") !== false);
$must('Portaria não depende mais de configuracoes.ver no guard', strpos($portaria, "['configuracoes.ver']") === false);
$must('AJAX validar acesso exige portaria.validar_acesso', strpos($db, "'portaria.validar_acesso'") !== false && strpos($db, "'sige_guarda'") !== false);
$must('Nonce global aceita Portaria', strpos($db, "'sige_portaria_acesso'") !== false);

$must('Shell mapeia view portaria para portaria.ver', strpos($shell, '"portaria" => ["portaria.ver","portaria.validar_acesso"]') !== false);
$must('Shell redirecciona Guarda para Portaria', strpos($shell, '$is_guarda') !== false && strpos($shell, "\$view = 'portaria';") !== false);
$must('Menu tem bloco Portaria dedicado', strpos($shell, 'PORTARIA') !== false && strpos($shell, 'Portaria Digital') !== false);
$must('Menu tem bloco Consulta para alunos read-only', strpos($shell, 'CONSULTA') !== false && strpos($shell, 'view=alunos_lista') !== false);
$must('Bottom nav inclui Portaria', strpos($shell, "'label' => 'Portaria'") !== false);

$must('Alunos permite consulta ao Guarda', strpos($alunos, "['alunos.ver']") !== false && strpos($alunos, "'sige_guarda'") !== false);
$must('Alunos tem camada read-only', strpos($alunos, '$sige_alunos_read_only') !== false && strpos($alunos, 'Modo consulta') !== false);
$must('Alunos esconde criar/importar por permissão', strpos($alunos, 'if ($sige_alunos_can_create)') !== false);
$must('Alunos esconde editar por permissão', strpos($alunos, 'if ($sige_alunos_can_edit)') !== false);
$must('Alunos esconde remover por permissão', strpos($alunos, 'if ($sige_alunos_can_delete)') !== false);
$must('Alunos bloqueia JS novoAluno sem criar', strpos($alunos, 'window.sigeAlunosCanCreate') !== false && strpos($alunos, 'Não pode registar novos alunos') !== false);
$must('Alunos bloqueia JS apagarAluno sem apagar', strpos($alunos, 'window.sigeAlunosCanDelete') !== false && strpos($alunos, 'permissão para remover alunos') !== false);
$must('Alunos bloqueia export/cartões para Guarda', strpos($alunos, '$sige_alunos_can_export = !$sige_alunos_is_guarda') !== false && strpos($alunos, 'não pode imprimir cartões') !== false);
$must('Endpoint export nega Guarda', strpos($alunoAjax, 'sige_alunos_user_is_guarda') !== false && strpos($alunoAjax, 'Sem permissão para exportar listas ou imprimir cartões em lote') !== false);
$must('Endpoint import não inclui sige_guarda no manage helper', !preg_match('/function\s+sige_alunos_user_can_manage\s*\([^)]*\)\s*\{.*sige_guarda/sU', $alunoAjax));
$must('AJAX salvar aluno exige criar/editar, sem Guarda fallback', strpos($db, '$sige_required_aluno_permission') !== false && strpos($db, 'Sem permissão para criar alunos') !== false);
$must('AJAX remover aluno exige alunos.apagar', strpos($db, "['alunos.apagar']") !== false && strpos($db, 'Sem permissão para remover alunos') !== false);
$removerStart = strpos($ajax, "add_action('wp_ajax_sige_remover_aluno'");
$removerEnd = $removerStart === false ? false : strpos($ajax, "wp_send_json_success('Aluno removido com sucesso.');", $removerStart);
$removerSegment = ($removerStart !== false && $removerEnd !== false) ? substr($ajax, $removerStart, $removerEnd - $removerStart) : '';
$must('Handler alternativo remover aluno não permite Guarda', strpos($removerSegment, "sige_can('alunos.apagar')") !== false && strpos($removerSegment, 'sige_guarda') === false);

$must('UI permissões conhece módulo Portaria', strpos($permsUi, "'portaria' => 'Portaria'") !== false);
$must('UI permissões conhece role Guarda', strpos($permsUi, "'guarda'") !== false);
$must('Equipa/RH pode atribuir role Guarda', strpos($rh, 'Guarda / Portaria') !== false && strpos($rh, 'value="sige_guarda"') !== false);

$total = count($checks);
if ($failures) {
    echo "SMOKE v12.11.9.65 Role Guarda / Portaria: FAIL\n";
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
echo "SMOKE v12.11.9.65 Role Guarda / Portaria: OK ({$total}/{$total} checks)\n";
