<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Gate da hierarquia de perfis por nivel (Fase 9 incremento 2).
 *
 * Verifica que existe um mapa de niveis com os tres gestores no topo; que o
 * avaliador aplica a guarda de nivel (nivel_insuficiente) nos dois ramos, por
 * cima da regra anti-escalada do Incr 1; que a interface calcula o nivel do
 * actor, torna so de leitura as linhas de nivel igual ou superior e filtra o
 * selector aos perfis atribuiveis; e que o esquema nao mudou.
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

// 1. Funcoes de nivel presentes.
foreach ([
    'function sige_permissions_role_niveis',
    'function sige_permissions_role_nivel',
    'function sige_permissions_user_nivel',
] as $fn) {
    if (strpos($layer, $fn) === false) { $fails[] = "funcao em falta: {$fn}"; }
}

// 2. Mapa de niveis: tres gestores no topo, e perfis operacionais conhecidos.
foreach (["'direccao_geral'   => 90", "'admin_escola'     => 90", "'admin_ti'         => 80"] as $top) {
    if (strpos($layer, $top) === false) { $fails[] = "nivel de gestor em falta no mapa: {$top}"; }
}
foreach (['professor', 'secretaria', 'encarregado'] as $slug) {
    if (!preg_match("/'" . preg_quote($slug, '/') . "'\\s*=>\\s*\\d+/", $layer)) { $fails[] = "nivel em falta no mapa para: {$slug}"; }
}

// 3. Avaliador: guarda de nivel nos dois ramos, e regra anti-escalada mantida.
if (strpos($layer, "'nivel_insuficiente'") === false) { $fails[] = 'guarda nivel_insuficiente ausente'; }
// Fixa as tres guardas pelas mensagens: nivel do perfil (atribuir) e nivel do
// alvo (atribuir e remover, pelo menos duas vezes).
if (strpos($layer, 'Nao pode atribuir um perfil de nivel igual ou superior ao seu.') === false) {
    $fails[] = 'guarda de nivel do perfil (no atribuir) em falta';
}
if (substr_count($layer, 'Nao pode alterar um utilizador de nivel igual ou superior ao seu.') < 2) {
    $fails[] = 'guarda de nivel do alvo nao esta nos dois ramos (atribuir e remover)';
}
if (strpos($layer, 'sige_permissions_user_nivel($actor_id') === false) { $fails[] = 'avaliador nao calcula o nivel do actor'; }
if (strpos($layer, 'sige_permissions_role_nivel($role_id)') === false) { $fails[] = 'avaliador nao compara com o nivel do perfil a atribuir'; }
if (strpos($layer, "'escalada_gestao'") === false) { $fails[] = 'regra anti-escalada (Incr 1) foi perdida'; }

// 4. Interface: nivel do actor, linhas so de leitura por nivel, e filtro do selector.
if (strpos($ui, '$__actor_nivel') === false) { $fails[] = 'interface nao calcula o nivel do actor'; }
if (strpos($ui, '$__gerivel') === false) { $fails[] = 'interface nao calcula a gerivel por linha'; }
if (strpos($ui, "elseif (!\$__gerivel)") === false) { $fails[] = 'interface nao tem ramo so-leitura para nivel igual ou superior'; }
// O selector filtra por nivel e por gestao.
if (!preg_match('/foreach \(\$roles as \$role\):.*?\$__rnivel.*?\$__rgestao.*?continue;/s', $ui)) {
    $fails[] = 'selector de perfis nao filtra por nivel e gestao';
}

// 5. Sem migracao de esquema.
if (strpos($mig, "SCHEMA_VERSION = '20260621.1'") === false) { $fails[] = 'SCHEMA_VERSION mudou (este incremento nao migra o esquema)'; }

if (!empty($fails)) {
    echo "GATE PERMISSOES-HIERARQUIA FALHOU:\n";
    foreach ($fails as $f) echo " - {$f}\n";
    exit(1);
}
echo "GATE PERMISSOES-HIERARQUIA OK - mapa de niveis com os tres gestores no topo, guarda nivel_insuficiente nos dois ramos por cima da regra anti-escalada, interface a calcular o nivel do actor, a tornar so de leitura as linhas de nivel igual ou superior e a filtrar o selector aos perfis atribuiveis, sem migracao de esquema.\n";
exit(0);
