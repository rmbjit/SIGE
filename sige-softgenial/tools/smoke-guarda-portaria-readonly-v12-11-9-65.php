<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.11.9.65 - Smoke estático da camada Guarda / Portaria Read-Only.
 * Executar na raiz do plugin: php tools/smoke-guarda-portaria-readonly-v12-11-9-65.php
 */
$root = dirname(__DIR__);
$checks = [];
$failures = [];
function sg_file(string $rel): string {
    global $root;
    $path = $root . '/' . $rel;
    return is_file($path) ? (string)file_get_contents($path) : '';
}
function sg_check(string $label, bool $ok): void {
    global $checks, $failures;
    $checks[] = [$label, $ok];
    if (!$ok) $failures[] = $label;
}

$main = sg_file('sige-softgenial.php');
$build = sg_file('BUILD.json');
$perm = sg_file('includes/permissions-layer.php');
$roles = sg_file('includes/security-roles.php');
$shell = sg_file('includes/admin-shell.php');
$portaria = sg_file('admin/system/portaria-view.php');
$db = sg_file('includes/db-handler.php');
$alunoAjax = sg_file('includes/aluno-fetch-ajax.php');
$alunos = sg_file('admin/academic/alunos_lista.php');
$equipe = sg_file('admin/hr/equipe-view.php');
$permUi = sg_file('admin/system/permissions-ui.php');

sg_check('Versão principal 12.11.9.65', strpos($main, 'Version: 12.11.9.65') !== false && strpos($main, "SIGE_VERSION', '12.11.9.65'") !== false);
sg_check('BUILD identificado como guarda-portaria-readonly-pro', strpos($build, 'guarda-portaria-readonly-pro') !== false && strpos($build, '12.11.9.65') !== false);
sg_check('Permissões granulares de Portaria existem', strpos($perm, "'portaria.ver'") !== false && strpos($perm, "'portaria.validar_acesso'") !== false);
sg_check('Perfil SIGE guarda existe com allowlist base', strpos($perm, "'guarda' =>") !== false && strpos($perm, "'permissions'=>['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false);
sg_check('Migração fecha exactamente permissões do guarda', strpos($perm, 'O perfil Guarda / Portaria é intencionalmente fechado') !== false && strpos($perm, "delete(\$t['role_permissions'], ['role_id' => \$role_id]") !== false);
sg_check('sige_can bloqueia overrides fora do escopo do guarda', strpos($perm, 'guarda_scope_denied') !== false && strpos($perm, "['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false);
sg_check('WP role sige_guarda criada com capacidades mínimas', strpos($roles, "add_role('sige_guarda'") !== false && strpos($roles, "add_cap('alunos.ver')") !== false && strpos($roles, "add_cap('sige_guarda')") !== false);
sg_check('Admin shell reconhece e redirecciona guarda para Portaria', strpos($shell, '$is_guarda') !== false && strpos($shell, "'GUARDA / PORTARIA'") !== false && strpos($shell, 'view=portaria') !== false);
sg_check('Rota Portaria protegida por portaria.ver e portaria.validar_acesso', strpos($portaria, "['portaria.ver','portaria.validar_acesso']") !== false && strpos($portaria, "'sige_guarda'") !== false);
sg_check('AJAX Portaria aceita nonce próprio e role guarda', strpos($db, 'sige_portaria_acesso') !== false && strpos($db, 'sige_ajax_validar_acesso') !== false && strpos($db, "'portaria.validar_acesso'") !== false && strpos($db, "'sige_guarda'") !== false);
sg_check('AJAX salvar/remover aluno exige criar/editar/apagar e exclui guarda', strpos($db, '$sige_required_aluno_permission') !== false && strpos($db, "['alunos.apagar']") !== false && strpos($db, 'Sem permissão para remover alunos') !== false);
sg_check('Cadastro staff valida allowlist e inclui sige_guarda', strpos($db, '$allowed_staff_roles') !== false && strpos($db, "'sige_guarda'") !== false && strpos($db, '$user->set_role($role_staff)') !== false && strpos($db, '$u->set_role($role_staff)') !== false);
sg_check('Alunos permite consulta para guarda e read-only no front-end', strpos($alunos, "['alunos.ver']") !== false && strpos($alunos, "'sige_guarda'") !== false && strpos($alunos, '$sige_alunos_read_only') !== false && strpos($alunos, 'window.sigeAlunosIsGuarda') !== false);
sg_check('Alunos oculta exportação/documentos/portal para guarda', strpos($alunos, '$sige_alunos_can_documents') !== false && strpos($alunos, '$sige_alunos_can_export') !== false && strpos($alunos, 'não pode exportar listas de alunos') !== false && strpos($alunos, 'não pode emitir documentos') !== false);
sg_check('AJAX aluno completo/exportação bloqueiam guarda; Ficha 360 redacta dados sensíveis', strpos($alunoAjax, 'sige_alunos_user_is_guarda') !== false && strpos($alunoAjax, 'Sem permissão para carregar dados de edição do aluno') !== false && strpos($alunoAjax, 'Sem permissão para exportar listas') !== false && strpos($alunoAjax, 'Dados financeiros, académicos detalhados, documentos digitais') !== false);
sg_check('RH/Equipa permite criar colaborador Guarda / Portaria', strpos($equipe, "'sige_guarda'") !== false && strpos($equipe, 'Guarda / Portaria') !== false && strpos($equipe, '<option value="sige_guarda">Guarda / Portaria</option>') !== false);
sg_check('UI de permissões fixa o perfil guarda no escopo correcto', strpos($permUi, "(string)(\$role->slug ?? '') === 'guarda'") !== false && strpos($permUi, "['portaria.ver','portaria.validar_acesso','alunos.ver']") !== false);

foreach ($checks as [$label, $ok]) {
    echo ($ok ? "OK   " : "FAIL ") . $label . PHP_EOL;
}
if ($failures) {
    echo PHP_EOL . 'Falhas: ' . count($failures) . PHP_EOL;
    exit(1);
}
echo PHP_EOL . 'Smoke Guarda / Portaria v12.11.9.65 OK: ' . count($checks) . '/' . count($checks) . PHP_EOL;
