<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate da blindagem do modulo de permissoes (Fase 9 incremento 1).
 *
 * Verifica que existe um avaliador unico de operacao com todas as guardas
 * (alvo protegido, anti-escalada, auto-proteccao, ultimo gestor); que o handler
 * da interface o chama e bloqueia nos dois ramos (atribuir e remover); que as
 * contas protegidas aparecem so de leitura, sem formulario de accao; que a porta
 * do menu passa a usar a permissao usuarios.gerir_permissoes; que a migracao de
 * reconciliacao existe e esta ligada; e que o esquema nao mudou.
 */

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};
$fails = [];

$layer = $read('includes/permissions-layer.php');
$ui    = $read('admin/system/permissions-ui.php');
$shell = $read('includes/admin-shell.php');
$mig   = $read('includes/class-sige-migration.php');

// 1. Funcoes de guarda e avaliador presentes.
foreach ([
    'function sige_permissions_perm_gestao',
    'function sige_permissions_principal_protegido',
    'function sige_permissions_role_concede_gestao',
    'function sige_permissions_user_tem_gestao_sige',
    'function sige_permissions_contar_gestores_sige',
    'function sige_permissions_avaliar_operacao',
] as $fn) {
    if (strpos($layer, $fn) === false) { $fails[] = "funcao em falta: {$fn}"; }
}

// 2. O avaliador cobre todos os codigos de guarda.
foreach (['alvo_protegido', 'escalada_gestao', 'auto_despromocao', 'auto_remocao', 'ultimo_gestor'] as $codigo) {
    if (strpos($layer, "'{$codigo}'") === false) { $fails[] = "codigo de guarda ausente no avaliador: {$codigo}"; }
}
// Conta protegida = administrador WP real.
if (strpos($layer, 'sige_is_real_wp_admin_user') === false) { $fails[] = 'principal protegido nao usa sige_is_real_wp_admin_user'; }

// 3. O handler chama o avaliador e bloqueia nos dois ramos.
if (substr_count($ui, 'sige_permissions_avaliar_operacao') < 2) { $fails[] = 'handler nao chama o avaliador nos dois ramos (atribuir e remover)'; }
if (strpos($ui, "'assign_user_role'") === false || strpos($ui, "'unassign_user_role'") === false) { $fails[] = 'accoes do handler em falta'; }
foreach (['ui_assign_blocked', 'ui_unassign_blocked'] as $audit_evt) {
    if (strpos($ui, $audit_evt) === false) { $fails[] = "auditoria de bloqueio em falta: {$audit_evt}"; }
}
// O bloqueio deve negar quando nao permitido.
if (strpos($ui, "['permitido']") === false) { $fails[] = 'handler nao verifica o resultado permitido do avaliador'; }

// 4. Interface mostra contas protegidas so de leitura, sem formulario de accao.
if (strpos($ui, 'sige_permissions_principal_protegido') === false) { $fails[] = 'interface nao deteta contas protegidas'; }
if (strpos($ui, '$__protegido') === false) { $fails[] = 'interface nao calcula o estado protegido por linha'; }
// O ramo protegido nao deve conter accao de formulario.
if (preg_match('/\$__protegido\s*\)\s*:\s*\?>(.*?)<\?php else/s', $ui, $mProt)) {
    if (strpos($mProt[1], 'sige_perm_action') !== false) { $fails[] = 'o ramo protegido nao deve conter formulario de accao (atribuir/remover)'; }
    if (strpos($mProt[1], 'protegido') === false) { $fails[] = 'o ramo protegido nao apresenta o selo Protegido'; }
} else {
    $fails[] = 'ramo de interface para conta protegida nao encontrado';
}

// 5. Porta do menu coerente: link de permissoes mostrado pela permissao de gestao.
if (strpos($shell, "\$sige_pode_permissoes = \$sige_menu_can_any(['usuarios.gerir_permissoes'])") === false) { $fails[] = 'menu nao calcula a permissao de gestao'; }
if (!preg_match('/if \(\$is_core_tech \|\| \$sige_pode_permissoes\):\s*\?>\s*<a href="\?page=sige-app&view=sige_permissoes"/s', $shell)) {
    $fails[] = 'link de Perfis e Permissoes nao esta condicionado por usuarios.gerir_permissoes';
}
// Saude do Sistema e Centro de Configuracao continuam restritos ao core admin.
if (!preg_match('/if \(\$is_core_tech\):\s*\?>\s*<a href="\?page=sige-app&view=sige_core_status"/s', $shell)) { $fails[] = 'Saude do Sistema deixou de ser restrito ao core admin'; }
if (!preg_match('/if \(\$is_core_tech\):\s*\?>\s*<a href="\?page=sige-app&view=config_center"/s', $shell)) { $fails[] = 'Centro de Configuracao deixou de ser restrito ao core admin'; }

// 6. Migracao de reconciliacao existe e esta ligada.
if (strpos($layer, 'function sige_permissions_migrate_121228_admin_ti_gestao') === false) { $fails[] = 'migracao de reconciliacao (121228) em falta'; }
if (strpos($layer, 'sige_permissions_migrate_121228_admin_ti_gestao()') === false || strpos($layer, "'12.12.28'") === false) { $fails[] = 'migracao 121228 nao ligada ao maybe_install'; }

// 7. Sem migracao de esquema.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

if (!empty($fails)) {
    echo "GATE PERMISSOES-BLINDAGEM FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE PERMISSOES-BLINDAGEM OK - avaliador unico com guardas (alvo protegido, anti-escalada, auto-proteccao, ultimo gestor), handler a bloquear nos dois ramos com auditoria, contas protegidas so de leitura sem formulario, porta do menu por usuarios.gerir_permissoes (com Saude e Centro restritos ao core admin), migracao de reconciliacao ligada, sem migracao de esquema.\n";
exit(0);
