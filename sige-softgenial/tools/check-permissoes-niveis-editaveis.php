<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate dos niveis de perfil editaveis (Fase 9 incremento 4).
 *
 * Verifica que os niveis passam a fundir um mapa base em codigo com desvios
 * guardados na base de dados (opcao), com gravacao validada (0 a 100) que so
 * guarda desvios; que a edicao e exclusiva do administrador WordPress real (no
 * handler e no painel); e que nao ha alteracao de esquema.
 */

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};
$fails = [];

$layer = $read('includes/permissions-layer.php');
$ui    = $read('admin/system/permissions-ui.php');
$mig   = $read('includes/class-sige-migration.php');

// 1. Funcoes de base, desvios e gravacao presentes.
foreach ([
    'function sige_permissions_niveis_base',
    'function sige_permissions_niveis_overrides',
    'function sige_permissions_guardar_niveis',
] as $fn) {
    if (strpos($layer, $fn) === false) { $fails[] = "funcao em falta: {$fn}"; }
}

// 2. role_niveis funde base com desvios.
if (strpos($layer, 'sige_permissions_niveis_base()') === false || strpos($layer, 'sige_permissions_niveis_overrides()') === false) {
    $fails[] = 'role_niveis nao funde o mapa base com os desvios';
}
if (strpos($layer, 'foreach (sige_permissions_niveis_overrides() as $slug => $nivel)') === false) {
    $fails[] = 'role_niveis nao itera os desvios para os fundir';
}
// Os desvios vivem numa opcao.
if (strpos($layer, "get_option('sige_permissions_niveis_overrides'") === false) { $fails[] = 'desvios nao sao lidos da opcao'; }
if (strpos($layer, "update_option('sige_permissions_niveis_overrides'") === false) { $fails[] = 'gravacao nao persiste a opcao'; }

// 3. Gravacao valida (0 a 100) e guarda so desvios.
if (strpos($layer, 'max(0, min(100,') === false) { $fails[] = 'gravacao/leitura nao limita o nivel a 0..100'; }
if (!preg_match('/if \(\$n === \$base_n\) \{\s*unset\(\$overrides\[\$slug\]\);/', $layer)) {
    $fails[] = 'gravacao nao remove o desvio quando o valor volta ao base';
}

// 4. Edicao exclusiva do administrador WordPress real (handler).
if (strpos($ui, "\$action === 'save_niveis'") === false) { $fails[] = 'accao save_niveis em falta no handler'; }
if (strpos($ui, 'sige_permissions_principal_protegido(get_current_user_id())') === false) { $fails[] = 'save_niveis nao verifica administrador WordPress real'; }
foreach (['ui_save_niveis', 'ui_niveis_blocked'] as $evt) {
    if (strpos($ui, $evt) === false) { $fails[] = "auditoria em falta: {$evt}"; }
}
if (strpos($ui, 'sige_permissions_guardar_niveis(') === false) { $fails[] = 'handler nao chama a gravacao'; }

// 5. Painel visivel e editavel so ao administrador WordPress real.
if (!preg_match('/if \(\$__actor_protegido\):\s*\?>\s*<section class="sige-perm-card">\s*<div class="sige-perm-card-h">\s*<h2>.*?Hierarquia de n.*?vel/s', $ui)) {
    $fails[] = 'painel de niveis nao esta reservado ao administrador WordPress real';
}
if (strpos($ui, 'name="niveis[') === false || strpos($ui, 'value="save_niveis"') === false) { $fails[] = 'formulario do painel de niveis incompleto'; }
// Sem estilo inline novo no painel (input de nivel sem atributo style).
if (preg_match('/name="niveis\[[^"]*"[^>]*style=/', $ui)) { $fails[] = 'painel de niveis usa estilo inline novo'; }

// 6. Sem migracao de esquema.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

if (!empty($fails)) {
    echo "GATE PERMISSOES-NIVEIS-EDITAVEIS FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE PERMISSOES-NIVEIS-EDITAVEIS OK - niveis fundem mapa base com desvios na opcao, gravacao validada (0..100) que so guarda desvios, edicao exclusiva do administrador WordPress real (handler e painel) com auditoria, sem estilo inline novo, sem migracao de esquema.\n";
exit(0);
