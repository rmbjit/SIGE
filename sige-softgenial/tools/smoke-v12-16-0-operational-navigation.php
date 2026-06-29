<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = [];
$GLOBALS['SIGE_V121600_ALLOWED_CAPS'] = [];

function admin_url($path = '') { return 'admin.php'; }
function add_query_arg(array $args, $url) { return $url . '?' . http_build_query($args); }
function sige_page_guard_allows(array $permissions, array $legacy_caps = []): bool {
    foreach ($permissions as $permission) {
        if (in_array((string)$permission, $GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'], true)) { return true; }
    }
    foreach ($legacy_caps as $cap) {
        if (in_array((string)$cap, $GLOBALS['SIGE_V121600_ALLOWED_CAPS'], true)) { return true; }
    }
    return false;
}

require_once dirname(__DIR__) . '/includes/institutional-product-map.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) { $failures[] = $message; }
};
$group_by_slug = static function (array $groups, string $slug): ?array {
    foreach ($groups as $group) {
        if ((string)($group['slug'] ?? '') === $slug) { return $group; }
    }
    return null;
};
$views_in_group = static function (array $group): array {
    return array_values(array_map(static function (array $item): string {
        return (string)($item['view'] ?? '');
    }, (array)($group['actions'] ?? [])));
};

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = [];
$none = sige_institutional_navigation_groups_v121600(3, 3);
$check($none === [], 'Sem permissao nao deve haver navegacao operacional.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.pagar', 'financeiro.cobrancas_ver'];
$finance = sige_institutional_navigation_groups_v121600(2, 3);
$finance_group = $group_by_slug($finance, 'tesouraria');
$check($finance_group !== null, 'Tesouraria deve gerar grupo operacional.');
$check(($finance[0]['slug'] ?? '') === 'tesouraria', 'Tesouraria isolada deve ser grupo primario.');
$check(count((array)($finance_group['actions'] ?? [])) <= 2, 'Limit por grupo deve ser respeitado.');
$check(in_array('financeiro-pagamentos', $views_in_group($finance_group), true), 'Tesouraria deve apontar para registo de pagamento.');
$check(!in_array('alunos_lista', $views_in_group($finance_group), true), 'Tesouraria sem alunos.ver nao deve expor alunos.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['portaria.validar_acesso'];
$portaria = sige_institutional_navigation_groups_v121600(3, 3);
$check(count($portaria) === 1, 'Portaria deve manter navegacao limpa.');
$check(($portaria[0]['slug'] ?? '') === 'portaria', 'Portaria deve ser grupo primario.');
$check(in_array('portaria', $views_in_group($portaria[0]), true), 'Portaria deve apontar para a propria validacao.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['alunos.ver', 'academico.turmas_ver', 'financeiro.pagar'];
$misto = sige_institutional_navigation_groups_v121600(1, 2);
$check(count($misto) <= 2, 'Max groups deve limitar blocos exibidos no shell.');
foreach ($misto as $group) {
    $check(count((array)($group['actions'] ?? [])) <= 1, 'Limit por grupo deve cortar accoes em cada area.');
}
$all_views = [];
foreach ($misto as $group) { $all_views = array_merge($all_views, $views_in_group($group)); }
$check(count($all_views) === count(array_unique($all_views)), 'Navegacao operacional nao deve duplicar views.');
$check(in_array('financeiro-pagamentos', $all_views, true) || in_array('alunos_lista', $all_views, true) || in_array('turmas', $all_views, true), 'Perfil misto deve manter pelo menos uma accao operacional real.');

$meta = sige_institutional_area_meta_v121600();
$check(isset($meta['tesouraria'], $meta['secretaria'], $meta['academico'], $meta['portaria']), 'Metadados institucionais devem cobrir areas criticas.');

if ($failures) {
    foreach ($failures as $failure) { fwrite(STDERR, "ERRO: {$failure}\n"); }
    exit(1);
}

echo "v12.16.0 OPERATIONAL NAVIGATION SMOKE OK - grupos por area, limites e filtragem por permissao validados.\n";
