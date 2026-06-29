<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate da reposicao do papel WordPress (Fase 9 incremento 3, actualizado pelo
 * incremento 5).
 *
 * No incremento 5 a substituicao do papel foi eliminada (sobreposicao puramente
 * aditiva por filtro; ver check-permissoes-aditiva). As utilidades de preservacao
 * e reposicao mantem-se para a limpeza do estado legado: este gate garante que a
 * reposicao continua a existir, e segura para o fluxo aditivo (so repoe o papel
 * por omissao quando o utilizador esta num papel sige_* de staff, nunca mexendo
 * num utilizador ja num papel real), que limpa a copia, que e usada na remocao de
 * perfil (com mensagem), e que nao ha alteracao de esquema.
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

// 1. Funcoes de preservacao e reposicao presentes.
foreach ([
    'function sige_permissions_backup_wp_roles',
    'function sige_permissions_restore_wp_roles',
] as $fn) {
    if (strpos($layer, $fn) === false) { $fails[] = "funcao em falta: {$fn}"; }
}

// 2. A reposicao le e limpa a copia.
if (strpos($layer, "get_user_meta(\$user_id, '_sige_wp_roles_backup', true)") === false) { $fails[] = 'reposicao nao le a copia'; }
if (strpos($layer, "delete_user_meta(\$user_id, '_sige_wp_roles_backup')") === false) { $fails[] = 'reposicao nao limpa a copia'; }

// 3. A reposicao e segura para o fluxo aditivo: sem copia, so repoe o papel por
// omissao quando o utilizador esta num papel sige_* de staff; caso contrario nao toca.
if (preg_match('/function sige_permissions_restore_wp_roles\(int \$user_id\): bool.*?\n    \}\n\}/s', $layer, $m)) {
    if (strpos($m[0], 'sige_permissions_is_staff_wp_role') === false || strpos($m[0], "get_option('default_role'") === false) {
        $fails[] = 'reposicao sem copia nao esta limitada ao caso de papel sige_* de staff (risco de repor utilizador ja num papel real)';
    }
    if (!preg_match('/if \(!\$on_staff_sige\) return false;/', $m[0])) {
        $fails[] = 'reposicao nao protege o utilizador ja num papel real (deve nao tocar)';
    }
} else {
    $fails[] = 'nao foi possivel isolar restore_wp_roles para verificar';
}

// 4. A remocao de perfil repoe o papel (no handler), com mensagem.
if (strpos($ui, 'sige_permissions_restore_wp_roles($user_id)') === false) { $fails[] = 'a remocao nao repoe o papel'; }
if (strpos($ui, 'O papel WordPress original do utilizador foi reposto.') === false) { $fails[] = 'mensagem de remocao nao reflecte a reposicao'; }

// 5. Sem migracao de esquema (usa user meta, nao DDL).
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

if (!empty($fails)) {
    echo "GATE PERMISSOES-SOBREPOSICAO FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE PERMISSOES-SOBREPOSICAO OK - reposicao presente, segura para o fluxo aditivo (so repoe o papel por omissao quando ha papel sige_* de staff, nunca mexendo num papel real), limpa a copia, usada na remocao com mensagem, sem migracao de esquema.\n";
exit(0);
