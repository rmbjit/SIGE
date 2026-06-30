<?php
/**
 * Smoke v12.30.2 - Consolidacao: fonte de verdade UNICA do staff com perfil SIGE.
 *
 * Cobre tres niveis:
 *  PARTE 1 - FUNCIONAL (cenario das capturas): prova o conjunto correcto e a causa
 *            do bug (criterio antigo com r.ativo=1 excluia um colaborador).
 *  PARTE 2 - FUNCAO REAL: carrega includes/sige-staff-roster.php com um $wpdb
 *            simulado e verifica o pos-processamento real (dedup/intval, mapa do
 *            perfil actual, "a atribuicao mais recente vence", traducao para papel WP).
 *  PARTE 3 - ARQUITECTURA: confirma que o criterio vive num so sitio e que AMBAS
 *            as paginas (Equipa e Permissoes) o consomem - e que ninguem mantem
 *            uma copia local com `AND r.ativo`.
 *
 * Uso: php tools/smoke-rh-equipe-roster-consolidado-v12-30-2.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$arr, $cond, $msg) { $arr[] = [(bool)$cond, $msg]; }

// ===========================================================================
// PARTE 1 - FUNCIONAL (replica do criterio canonico; cenario real).
// ===========================================================================
$ESCOLA = 7;
$users = [
    1 => ['wp_roles' => ['sige_admin_ti'],  'meta_escola' => 7, 'removed' => false, 'real_admin' => false],
    2 => ['wp_roles' => ['sige_guarda'],    'meta_escola' => 0, 'removed' => false, 'real_admin' => false],
    3 => ['wp_roles' => ['administrator'],   'meta_escola' => 7, 'removed' => false, 'real_admin' => true],
    4 => ['wp_roles' => [],                  'meta_escola' => 0, 'removed' => false, 'real_admin' => false],
    5 => ['wp_roles' => ['sige_professor'], 'meta_escola' => 99,'removed' => false, 'real_admin' => false],
    6 => ['wp_roles' => ['sige_professor'], 'meta_escola' => 7, 'removed' => true,  'real_admin' => false],
];
$user_roles = [
    ['id' => 10, 'user_id' => 1, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'admin_ti',  'r_ativo' => 1],
    ['id' => 11, 'user_id' => 2, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'professor', 'r_ativo' => 0], // o caso do bug
    ['id' => 12, 'user_id' => 3, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'admin_ti',  'r_ativo' => 1],
    ['id' => 13, 'user_id' => 4, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'aluno',     'r_ativo' => 1],
    ['id' => 14, 'user_id' => 5, 'escola_id' => 99, 'ur_ativo' => 1, 'slug' => 'professor', 'r_ativo' => 1],
];
$staff_role_slugs = ['sige_admin_ti','sige_director','sige_secretaria_geral','sige_assistente','sige_financeiro','sige_professor','sige_educador','sige_motorista','sige_limpeza','sige_secretario','sige_gestor_rh','sige_pedagogico','sige_recepcao','sige_guarda'];
$portal = ['aluno','encarregado'];

$ids_meta = [];
foreach ($users as $id => $u) {
    if ($u['meta_escola'] === $ESCOLA && array_intersect($u['wp_roles'], $staff_role_slugs)) $ids_meta[] = $id;
}
$ids_sige = [];
foreach ($user_roles as $r) {
    if ($r['escola_id'] === $ESCOLA && $r['ur_ativo'] === 1 && !in_array($r['slug'], $portal, true)) $ids_sige[] = $r['user_id'];
}
$staff_ids = array_values(array_unique(array_merge($ids_meta, $ids_sige)));
$staff_ids = array_values(array_filter($staff_ids, fn($id) => !$users[$id]['real_admin']));
$staff_ids = array_values(array_filter($staff_ids, fn($id) => !$users[$id]['removed']));
sort($staff_ids);

_p($fails, $staff_ids === [1, 2], 'Equipa = {1,2} (toda a equipa com perfil SIGE) -> [' . implode(',', $staff_ids) . ']');
_p($fails, !in_array(3, $staff_ids, true), 'SUPER admin (3) fora');
_p($fails, !in_array(4, $staff_ids, true), 'Aluno/portal (4) fora');
_p($fails, !in_array(5, $staff_ids, true), 'Outra escola (5) fora');
_p($fails, !in_array(6, $staff_ids, true), 'Removido (6) fora');

$ids_sige_buggy = [];
foreach ($user_roles as $r) {
    if ($r['escola_id'] === $ESCOLA && $r['ur_ativo'] === 1 && $r['r_ativo'] === 1 && !in_array($r['slug'], $portal, true)) $ids_sige_buggy[] = $r['user_id'];
}
_p($fails, !in_array(2, $ids_sige_buggy, true), 'Regressao guard: criterio antigo (r.ativo=1) excluia o utilizador 2');

// ===========================================================================
// PARTE 2 - FUNCAO REAL (includes/sige-staff-roster.php com $wpdb simulado).
// ===========================================================================
if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('sige_permissions_tables')) {
    function sige_permissions_tables(): array { return ['user_roles' => 'ur_tbl', 'roles' => 'r_tbl', 'permissions' => 'p', 'role_permissions' => 'rp']; }
}
if (!function_exists('sige_permissions_role_to_wp_role')) {
    function sige_permissions_role_to_wp_role(string $slug): string {
        $m = ['professor' => 'sige_professor', 'admin_ti' => 'sige_admin_ti', 'guarda' => 'sige_guarda', 'limpeza' => 'sige_limpeza'];
        return $m[$slug] ?? '';
    }
}
// $wpdb simulado: prepare e identidade; get_col/get_results devolvem fixtures.
// (O criterio SQL e travado na PARTE 3 por leitura da fonte; aqui validamos o
//  pos-processamento PHP real das funcoes partilhadas.)
class _SmokeWpdb {
    public $col = [];
    public $results = [];
    public function prepare($sql, $args = []) { return $sql; }
    public function get_col($sql) { return $this->col; }
    public function get_results($sql) { return $this->results; }
}
$GLOBALS['wpdb'] = new _SmokeWpdb();
require_once $root . '/includes/sige-staff-roster.php';

_p($fails, function_exists('sige_staff_active_profile_user_ids'), 'Funcao sige_staff_active_profile_user_ids existe');
_p($fails, function_exists('sige_staff_active_profile_map'), 'Funcao sige_staff_active_profile_map existe');
_p($fails, sige_staff_portal_role_slugs() === ['aluno', 'encarregado'], 'Papeis de portal centralizados (aluno, encarregado)');

// Escola invalida -> vazio (tenant safety), sem tocar no $wpdb.
_p($fails, sige_staff_active_profile_user_ids(0) === [], 'escola_id<=0 devolve vazio (tenant safety)');

// IDs: dedup + intval do que o get_col devolve.
$GLOBALS['wpdb']->col = [3, 3, '5', 7, '7'];
$got_ids = sige_staff_active_profile_user_ids(7);
sort($got_ids);
_p($fails, $got_ids === [3, 5, 7], 'IDs dedup+intval -> [3,5,7] (obtido: [' . implode(',', $got_ids) . '])');

// Mapa: "a atribuicao mais recente vence" (ASC -> ultima sobrescreve) + traducao WP.
$GLOBALS['wpdb']->results = [
    (object) ['user_id' => 2, 'slug' => 'guarda',    'nome' => 'Guarda / Portaria'],
    (object) ['user_id' => 2, 'slug' => 'professor', 'nome' => 'Professor'], // mais recente
    (object) ['user_id' => 1, 'slug' => 'admin_ti',  'nome' => 'Admin TI'],
];
$map = sige_staff_active_profile_map(7);
_p($fails, ($map[2]['wp'] ?? '') === 'sige_professor', 'Mapa: utilizador 2 = Professor (atribuicao mais recente vence; nao Guarda)');
_p($fails, ($map[2]['slug'] ?? '') === 'professor', 'Mapa: slug do perfil actual preservado');
_p($fails, ($map[1]['wp'] ?? '') === 'sige_admin_ti', 'Mapa: utilizador 1 = Admin TI');

// ===========================================================================
// PARTE 3 - ARQUITECTURA (um so criterio; ambas as paginas consomem-no).
// ===========================================================================
$get = fn($rel) => (string) @file_get_contents($root . '/' . $rel);
$roster = $get('includes/sige-staff-roster.php');
$equipe = $get('admin/hr/equipe-view.php');
$perm   = $get('admin/system/permissions-ui.php');
$boot   = $get('sige-softgenial.php');
$main   = $get('sige-softgenial.php');
$build  = json_decode($get('BUILD.json'), true);

// Criterio canonico vive no roster e esta correcto.
_p($fails, strpos($roster, 'ur.ativo = 1') !== false, 'Roster usa ur.ativo = 1');
_p($fails, strpos($roster, 'AND r.ativo') === false, 'Roster NAO filtra por r.ativo (a fuga foi removida na fonte unica)');
_p($fails, strpos($roster, 'sige_staff_portal_role_slugs()') !== false || strpos($roster, "['aluno', 'encarregado']") !== false, 'Roster exclui papeis de portal');
_p($fails, strpos($roster, 'sige_permissions_role_to_wp_role') !== false, 'Roster traduz perfil actual -> papel WP');

// Bootstrap carrega o roster.
_p($fails, strpos($boot, "require_once SIGE_PATH . 'includes/sige-staff-roster.php'") !== false, 'Bootstrap carrega o roster');

// Equipa consome a fonte unica (e ja nao tem copia local do criterio).
_p($fails, strpos($equipe, 'sige_staff_active_profile_user_ids((int) $escola_id)') !== false, 'Equipa consome sige_staff_active_profile_user_ids');
_p($fails, strpos($equipe, 'sige_staff_active_profile_map((int) $escola_id)') !== false, 'Equipa consome sige_staff_active_profile_map');
_p($fails, strpos($equipe, "r.slug NOT IN ('aluno', 'encarregado')") === false, 'Equipa ja nao tem a query (B) inline (sem copia do criterio)');
_p($fails, strpos($equipe, 'sige_is_real_wp_admin_user') !== false, 'Equipa mantem exclusao do super admin');
_p($fails, strpos($equipe, '$sige_rh_eff_role($s)') !== false, 'Equipa mantem cargo/KPI por perfil actual');
_p($fails, strpos($equipe, "user_can(\$s->ID, 'sige_professor')") !== false && strpos($equipe, "user_can(\$s->ID, 'sige_educador')") !== false, 'Cracha mantem verificacao por capacidade (paridade com gate aditiva)');

// Permissoes consome a MESMA fonte unica (e ja nao tem copia com r.ativo).
_p($fails, strpos($perm, 'sige_staff_active_profile_user_ids((int) $escola_id)') !== false, 'Permissoes consome sige_staff_active_profile_user_ids');
_p($fails, strpos($perm, 'AND r.ativo = 1') === false, 'Permissoes ja nao tem a copia local com AND r.ativo = 1');

// Versao alinhada.
_p($fails, strpos($main, 'Version: 12.30.2') !== false, 'Header do plugin em 12.30.2');
_p($fails, strpos($main, "define('SIGE_VERSION', '12.30.2')") !== false, 'SIGE_VERSION em 12.30.2');
_p($fails, is_array($build) && ($build['version'] ?? '') === '12.30.2', 'BUILD.json em 12.30.2');

// ===========================================================================
$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) {
    echo 'SMOKE RH-EQUIPA-ROSTER-CONSOLIDADO FALHOU: ' . count($erros) . " verificacao(oes).\n";
    exit(1);
}
echo "SMOKE RH-EQUIPA-ROSTER-CONSOLIDADO OK - fonte de verdade unica, consumida pela Equipa e por Permissoes.\n";
exit(0);
