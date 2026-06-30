<?php
/**
 * Smoke v12.30.1 - Equipa: lista completa, KPIs por perfil SIGE actual, sem SUPER admin.
 *
 * Reproduz o cenario reportado (capturas): a pagina de Permissoes mostrava dois
 * colaboradores (Admin TI e um com perfil actual "Professor"), mas a Equipa so
 * mostrava um. Causa: a Equipa filtrava por r.ativo=1 na uniao com sige_user_roles,
 * deixando de fora quem tinha uma ATRIBUICAO activa (ur.ativo=1) a um papel cujo
 * flag global r.ativo nao estava a 1.
 *
 * Este teste e (1) FUNCIONAL: replica o criterio exacto de construcao de $staff e
 * prova o conjunto correcto; e (2) de SALVAGUARDA de codigo-fonte: fixa os
 * invariantes da correccao no proprio equipe-view.php.
 *
 * Uso: php tools/smoke-rh-equipe-lista-completa-v12-30-1.php
 */

$root  = dirname(__DIR__);
$fails = [];
$ok    = [];
function _p(&$arr, $cond, $msg) { $arr[] = [$cond, $msg]; }

// ---------------------------------------------------------------------------
// PARTE 1 - Teste FUNCIONAL do criterio (replica fiel da logica do view).
// ---------------------------------------------------------------------------
$ESCOLA = 7;

// Fixtures de utilizadores (espelho do que get_users()/wp_users devolveria).
$users = [
    1 => ['nome' => 'Edilson Talhado', 'wp_roles' => ['sige_admin_ti'], 'meta_escola' => 7, 'removed' => false, 'real_admin' => false],
    2 => ['nome' => 'Guarda - SoftGenial', 'wp_roles' => ['sige_guarda'],  'meta_escola' => 0, 'removed' => false, 'real_admin' => false],
    3 => ['nome' => 'Super Admin',         'wp_roles' => ['administrator'], 'meta_escola' => 7, 'removed' => false, 'real_admin' => true],
    4 => ['nome' => 'Aluno Portal',        'wp_roles' => [],                'meta_escola' => 0, 'removed' => false, 'real_admin' => false],
    5 => ['nome' => 'Prof Outra Escola',   'wp_roles' => ['sige_professor'],'meta_escola' => 99,'removed' => false, 'real_admin' => false],
    6 => ['nome' => 'Removido',            'wp_roles' => ['sige_professor'],'meta_escola' => 7, 'removed' => true,  'real_admin' => false],
];

// Fixtures de atribuicoes (tabela sige_user_roles JOIN sige_roles).
// Repara: utilizador 2 tem perfil actual "professor" com r_ativo=0 (o caso do bug).
$user_roles = [
    ['id' => 10, 'user_id' => 1, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'admin_ti',  'r_ativo' => 1],
    ['id' => 11, 'user_id' => 2, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'professor', 'r_ativo' => 0],
    ['id' => 12, 'user_id' => 3, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'admin_ti',  'r_ativo' => 1],
    ['id' => 13, 'user_id' => 4, 'escola_id' => 7,  'ur_ativo' => 1, 'slug' => 'aluno',     'r_ativo' => 1],
    ['id' => 14, 'user_id' => 5, 'escola_id' => 99, 'ur_ativo' => 1, 'slug' => 'professor', 'r_ativo' => 1],
];

$staff_role_slugs = ['sige_admin_ti','sige_director','sige_secretaria_geral','sige_assistente','sige_financeiro','sige_professor','sige_educador','sige_motorista','sige_limpeza','sige_secretario','sige_gestor_rh','sige_pedagogico','sige_recepcao','sige_guarda'];
$portal = ['aluno','encarregado'];
$role_to_wp = ['admin_ti'=>'sige_admin_ti','direccao_geral'=>'sige_director','director'=>'sige_director','dir_pedagogico'=>'sige_pedagogico','gestor_rh'=>'sige_gestor_rh','professor'=>'sige_professor','educador'=>'sige_educador','secretaria_geral'=>'sige_secretaria_geral','secretario'=>'sige_secretario','tesoureiro'=>'sige_financeiro','assistente'=>'sige_assistente','recepcao'=>'sige_recepcao','guarda'=>'sige_guarda','motorista'=>'sige_motorista','limpeza'=>'sige_limpeza'];

// (A) staff WP scoped por escola (meta).
$ids_meta = [];
foreach ($users as $id => $u) {
    if ($u['meta_escola'] === $ESCOLA && array_intersect($u['wp_roles'], $staff_role_slugs)) $ids_meta[] = $id;
}
// (B) perfil SIGE actual nesta escola: ur.ativo=1, NAO-portal, SEM filtrar r.ativo.
$ids_sige = [];
foreach ($user_roles as $r) {
    if ($r['escola_id'] === $ESCOLA && $r['ur_ativo'] === 1 && !in_array($r['slug'], $portal, true)) $ids_sige[] = $r['user_id'];
}
$staff_ids = array_values(array_unique(array_merge($ids_meta, $ids_sige)));
// Excluir SUPER admin / admin WP real.
$staff_ids = array_values(array_filter($staff_ids, fn($id) => !$users[$id]['real_admin']));
// Excluir removidos (soft delete).
$staff_ids = array_values(array_filter($staff_ids, fn($id) => !$users[$id]['removed']));
sort($staff_ids);

_p($fails, $staff_ids === [1, 2], 'Equipa = {Edilson(1), Guarda/Professor(2)} (toda a equipa com perfil SIGE aparece) -> obtido: [' . implode(',', $staff_ids) . ']');
_p($fails, !in_array(3, $staff_ids, true), 'SUPER admin (3) NUNCA aparece na equipa');
_p($fails, !in_array(4, $staff_ids, true), 'Aluno/portal (4) nao aparece na equipa');
_p($fails, !in_array(5, $staff_ids, true), 'Colaborador de outra escola (5) nao entra (tenant-scoped)');
_p($fails, !in_array(6, $staff_ids, true), 'Removido (6) continua oculto (soft delete)');

// Prova do bug antigo: COM o filtro r.ativo=1, o utilizador 2 cairia fora.
$ids_sige_buggy = [];
foreach ($user_roles as $r) {
    if ($r['escola_id'] === $ESCOLA && $r['ur_ativo'] === 1 && $r['r_ativo'] === 1 && !in_array($r['slug'], $portal, true)) $ids_sige_buggy[] = $r['user_id'];
}
_p($fails, !in_array(2, $ids_sige_buggy, true), 'Regressao guard: o criterio antigo (r.ativo=1) excluia o utilizador 2 (confirma a causa)');

// KPIs por PERFIL SIGE ACTUAL (nao pelo papel WP legado).
$eff = function ($id) use ($users, $user_roles, $ESCOLA, $portal, $role_to_wp) {
    foreach ($user_roles as $r) { // perfil actual (ASC -> ultimo vence; aqui um por user)
        if ($r['user_id'] === $id && $r['escola_id'] === $ESCOLA && $r['ur_ativo'] === 1 && !in_array($r['slug'], $portal, true)) {
            $wp = $role_to_wp[$r['slug']] ?? '';
            if ($wp !== '') return $wp;
        }
    }
    return reset($users[$id]['wp_roles']) ?: '';
};
$docentes = ['sige_professor','sige_educador','sige_pedagogico'];
$apoio    = ['sige_motorista','sige_limpeza','sige_recepcao','sige_guarda'];
$kpi = ['doc' => 0, 'apoio' => 0, 'admin' => 0];
foreach ($staff_ids as $id) {
    $e = $eff($id);
    if (in_array($e, $docentes, true)) $kpi['doc']++; elseif (in_array($e, $apoio, true)) $kpi['apoio']++; else $kpi['admin']++;
}
_p($fails, count($staff_ids) === 2, 'KPI Total Colaboradores = 2');
_p($fails, $kpi['doc'] === 1, 'KPI Docentes = 1 (utilizador 2 conta como Professor pelo perfil actual, nao como Guarda)');
_p($fails, $kpi['admin'] === 1, 'KPI Admin = 1 (Edilson / Admin TI)');
_p($fails, $kpi['apoio'] === 0, 'KPI Apoio = 0 (o papel WP guarda NAO domina o perfil actual professor)');

// ---------------------------------------------------------------------------
// PARTE 2 - Salvaguarda de codigo-fonte (fixa os invariantes da correccao).
// ---------------------------------------------------------------------------
$view = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$main = (string) @file_get_contents($root . '/sige-softgenial.php');
$build = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

// Isolar a regiao da fonte (B) para garantir que NAO tem r.ativo=1.
$ini = strpos($view, '(B) Utilizadores com PERFIL SIGE actual');
$fim = strpos($view, '$__staff_ids = array_values', $ini === false ? 0 : $ini);
$regiaoB = ($ini !== false && $fim !== false) ? substr($view, $ini, $fim - $ini) : '';
// Isolar so a query SQL (entre SELECT DISTINCT e o fecho do prepare) para nao
// apanhar a mencao a r.ativo no comentario explicativo logo acima.
$qi = strpos($view, 'SELECT DISTINCT ur.user_id', $ini === false ? 0 : $ini);
$qf = strpos($view, '$escola_id', $qi === false ? 0 : $qi);
$sqlB = ($qi !== false && $qf !== false) ? substr($view, $qi, $qf - $qi) : '';
_p($fails, $sqlB !== '' && strpos($sqlB, 'ur.ativo = 1') !== false, 'Query (B) usa ur.ativo = 1');
// 'AND r.ativo' evita falso-positivo com a substring 'r.ativo' dentro de 'ur.ativo'.
_p($fails, $sqlB !== '' && strpos($sqlB, 'AND r.ativo') === false, 'Query (B) NAO filtra por r.ativo (a fuga foi removida)');
_p($fails, strpos($view, "r.slug NOT IN ('aluno', 'encarregado')") !== false, 'Papeis de portal excluidos da equipa');
_p($fails, strpos($view, 'array_merge($__staff_ids_meta, $__staff_ids_sige)') !== false, 'Uniao das duas fontes (meta + sige_user_roles)');
_p($fails, strpos($view, 'sige_is_real_wp_admin_user') !== false, 'SUPER admin filtrado (sige_is_real_wp_admin_user)');
_p($fails, strpos($view, 'sige_permissions_role_to_wp_role') !== false, 'Cargo/KPI derivam do perfil SIGE actual (mapa role->wp)');
_p($fails, strpos($view, '$sige_rh_eff_role($s)') !== false, 'Papel efectivo aplicado (helper $sige_rh_eff_role)');
_p($fails, strpos($view, "in_array(\$__eff_role, \$roles_docentes, true)") !== false, 'KPI categoriza pelo papel efectivo');
// Paridade com gate permissoes-aditiva: cracha mantem verificacao por capacidade.
_p($fails, strpos($view, "user_can(\$s->ID, 'sige_professor')") !== false && strpos($view, "user_can(\$s->ID, 'sige_educador')") !== false, 'Cracha mantem verificacao por capacidade (paridade com gate aditiva)');
// Versao alinhada.
_p($fails, strpos($main, 'Version: 12.30.1') !== false, 'Header do plugin em 12.30.1');
_p($fails, strpos($main, "define('SIGE_VERSION', '12.30.1')") !== false, 'SIGE_VERSION em 12.30.1');
_p($fails, is_array($build) && ($build['version'] ?? '') === '12.30.1', 'BUILD.json em 12.30.1');

// ---------------------------------------------------------------------------
$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) {
    echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n";
}
echo "\n";
if ($erros) {
    echo 'SMOKE RH-EQUIPA-LISTA-COMPLETA FALHOU: ' . count($erros) . " verificacao(oes).\n";
    exit(1);
}
echo "SMOKE RH-EQUIPA-LISTA-COMPLETA OK - lista completa, KPIs por perfil SIGE actual, sem SUPER admin.\n";
exit(0);
