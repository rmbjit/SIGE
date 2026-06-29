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
$views = static function (array $actions): array {
    return array_values(array_map(static function (array $item): string {
        return (string)($item['view'] ?? '');
    }, $actions));
};

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = [];
$GLOBALS['SIGE_V121600_ALLOWED_CAPS'] = [];
$none = sige_institutional_actions_v121600(0);
$check($none === [], 'Sem permissao nao deve mostrar atalhos operacionais.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.pagar'];
$finance = sige_institutional_profile_context_v121600(8);
$finance_views = $views($finance['actions']);
$check(in_array('financeiro-pagamentos', $finance_views, true), 'Tesouraria deve expor Registar pagamento.');
$check(!in_array('alunos_lista', $finance_views, true), 'Tesouraria isolada nao deve expor Alunos sem permissao.');
$check(($finance['label'] ?? '') === 'Tesouraria', 'Perfil financeiro deve ser Tesouraria.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['alunos.ver', 'academico.turmas_ver'];
$secretaria = sige_institutional_profile_context_v121600(8);
$secretaria_views = $views($secretaria['actions']);
$check(in_array('alunos_lista', $secretaria_views, true), 'Secretaria deve expor Alunos.');
$check(in_array('turmas', $secretaria_views, true), 'Secretaria deve expor Turmas.');
$check(!in_array('financeiro-pagamentos', $secretaria_views, true), 'Secretaria sem financeiro nao deve expor Pagamento.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['portaria.validar_acesso'];
$portaria = sige_institutional_profile_context_v121600(8);
$portaria_views = $views($portaria['actions']);
$check(in_array('portaria', $portaria_views, true), 'Portaria deve expor validacao de entrada.');
$check(($portaria['label'] ?? '') === 'Portaria', 'Perfil de portaria deve permanecer limpo.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['sistema.estado_ver'];
$tecnico = sige_institutional_profile_context_v121600(8);
$tecnico_views = $views($tecnico['actions']);
$check(in_array('sige_core_status', $tecnico_views, true), 'Admin tecnico deve ver Saude do sistema.');
$check(in_array('config_center', $tecnico_views, true), 'Admin tecnico deve ver Configuracoes.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.pagar', 'alunos.ver', 'academico.turmas_ver'];
$gestao = sige_institutional_profile_context_v121600(8);
$gestao_views = $views($gestao['actions']);
$check(($gestao['label'] ?? '') === 'Gestao', 'Perfil transversal deve virar Gestao.');
$check(count(array_unique($gestao_views)) === count($gestao_views), 'Views operacionais nao podem duplicar no contexto.');

if ($failures) {
    foreach ($failures as $failure) { fwrite(STDERR, "ERRO: {$failure}\n"); }
    exit(1);
}

echo "v12.16.0 OPERATIONAL MAP SMOKE OK - perfis filtrados por permissao e sem atalhos indevidos.\n";
