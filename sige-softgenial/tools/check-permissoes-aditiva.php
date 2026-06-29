<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate da sobreposicao puramente aditiva por capacidades (Fase 9 incremento 5).
 *
 * Garante que o modulo deixou de substituir o papel WordPress e passou a conceder
 * as capacidades por um filtro user_has_cap, e prova o invariante que torna isto
 * seguro sem WordPress vivo: nenhum codigo depende de um papel sige_* de staff
 * estar no array de papeis do utilizador (zero verificacoes por slug de staff).
 */

$root = dirname(__DIR__);
$read = function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return file_exists($p) ? (string) file_get_contents($p) : '';
};
$fails = [];

$layer = $read('includes/permissions-layer.php');
$ajax  = $read('includes/ajax-handlers.php');
$equipe = $read('admin/hr/equipe-view.php');
$mig   = $read('includes/class-sige-migration.php');

// 1. Funcoes da sobreposicao aditiva presentes.
foreach ([
    'function sige_permissions_staff_wp_roles',
    'function sige_permissions_is_staff_wp_role',
    'function sige_permissions_get_latest_active_role',
    'function sige_permissions_caps_for_user',
    'function sige_permissions_grant_caps_filter',
] as $fn) {
    if (strpos($layer, $fn) === false) { $fails[] = "funcao em falta: {$fn}"; }
}

// 2. Filtro user_has_cap registado.
if (strpos($layer, "add_filter('user_has_cap', 'sige_permissions_grant_caps_filter'") === false) {
    $fails[] = 'filtro user_has_cap nao registado';
}
// Guarda anti-recursao no filtro.
if (strpos($layer, 'static $in = false;') === false) { $fails[] = 'filtro sem guarda anti-recursao'; }

// 3. A atribuicao NAO substitui o papel: sync_user_role sem set_role.
if (preg_match('/function sige_permissions_sync_user_role\([^)]*\).*?\n    \}\n\}/s', $layer, $m)) {
    if (strpos($m[0], 'set_role') !== false) { $fails[] = 'sync_user_role ainda chama set_role'; }
} else {
    $fails[] = 'nao foi possivel isolar sync_user_role para verificar';
}

// 4. A sincronizacao no init e so limpeza: repoe papel sige_* de staff, sem voltar a por set_role para sige_*.
if (preg_match('/function sige_permissions_sync_current_user_legacy_role\(\).*?\n    \}\n\}/s', $layer, $m)) {
    if (strpos($m[0], 'set_role') !== false) { $fails[] = 'a sincronizacao no init ainda chama set_role (deve so limpar)'; }
    if (strpos($m[0], 'sige_permissions_restore_wp_roles') === false || strpos($m[0], 'sige_permissions_is_staff_wp_role') === false) {
        $fails[] = 'a sincronizacao no init nao faz a limpeza por restauro de papel sige_* de staff';
    }
} else {
    $fails[] = 'nao foi possivel isolar a sincronizacao no init para verificar';
}

// 5. Verificacoes por slug convertidas para capacidade.
if (strpos($ajax, "user_can(\$wp_user_old->ID, 'sige_admin_ti')") === false || strpos($ajax, "user_can(\$user->ID, 'sige_admin_ti')") === false) {
    $fails[] = 'proteccao do Admin TI no ajax-handlers nao usa verificacao por capacidade';
}
if (strpos($equipe, "user_can(\$s->ID, 'sige_professor')") === false || strpos($equipe, "user_can(\$s->ID, 'sige_educador')") === false) {
    $fails[] = 'rotulo do cracha no equipe-view nao usa verificacao por capacidade';
}

// 6. INVARIANTE: zero in_array com slug de papel sige_* de STAFF em includes/ e admin/.
// Esta e a salvaguarda que substitui o WordPress vivo: se nada depende do papel
// sige_* de staff estar guardado, o filtro e suficiente por construcao.
$staff = ['sige_admin_ti','sige_director','sige_pedagogico','sige_gestor_rh','sige_professor','sige_educador','sige_secretaria_geral','sige_secretario','sige_financeiro','sige_assistente','sige_recepcao','sige_guarda','sige_motorista','sige_limpeza'];
$alt = implode('|', array_map('preg_quote', $staff));
$ofensas = [];
foreach (['includes', 'admin'] as $dir) {
    $base = $root . '/' . $dir;
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $linhas = file($f->getPathname(), FILE_IGNORE_NEW_LINES);
        foreach ($linhas as $n => $linha) {
            if (preg_match('/in_array\s*\([^;]*[\'"](' . $alt . ')[\'"]/', $linha)) {
                $rel = str_replace($root . '/', '', $f->getPathname());
                $ofensas[] = "{$rel}:" . ($n + 1);
            }
        }
    }
}
if (!empty($ofensas)) {
    $fails[] = 'INVARIANTE QUEBRADO: verificacao por slug de papel sige_* de staff (o filtro nao cobre slug): ' . implode(', ', $ofensas);
}

// 7. Sem migracao de esquema.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

if (!empty($fails)) {
    echo "GATE PERMISSOES-ADITIVA FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE PERMISSOES-ADITIVA OK - capacidades concedidas por filtro user_has_cap (com guarda anti-recursao), atribuicao e init ja nao substituem o papel WordPress (init so limpa papel sige_* de staff legado), proteccoes por slug convertidas para capacidade, e zero verificacoes por slug de papel sige_* de staff (invariante), sem migracao de esquema.\n";
exit(0);
