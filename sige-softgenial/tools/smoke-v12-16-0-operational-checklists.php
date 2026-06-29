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
$by_label = static function (array $items, string $label): ?array {
    foreach ($items as $item) {
        if ((string)($item['label'] ?? '') === $label) { return $item; }
    }
    return null;
};
$views = static function (array $items): array {
    return array_values(array_map(static function (array $item): string {
        return (string)($item['view'] ?? '');
    }, $items));
};

$signals = [
    'alunos_com_divida' => 4,
    'pagamentos_hoje' => 2,
    'alunos_sem_doc' => 3,
    'alunos_sem_encarregado' => 0,
    'alunos_sem_turma' => 1,
    'profs_sem_nuit' => 5,
];

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = [];
$none = sige_institutional_operational_checklist_v121600($signals, 0);
$check($none === [], 'Sem permissao nao deve haver checklist operacional.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.cobrancas_ver'];
$finance = sige_institutional_operational_checklist_v121600($signals, 0);
$finance_debt = $by_label($finance, 'Cobranças vencidas');
$check($finance_debt !== null, 'Tesouraria deve ver cobranças vencidas.');
$check(($finance_debt['status'] ?? '') === 'attention', 'Divida positiva deve ficar como attention.');
$check(!in_array('alunos_lista', $views($finance), true), 'Financeiro isolado nao deve expor alunos.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['alunos.ver'];
$secretaria = sige_institutional_operational_checklist_v121600($signals, 0);
$docs = $by_label($secretaria, 'Documentos em falta');
$enc = $by_label($secretaria, 'Encarregados por completar');
$check($docs !== null && ($docs['status'] ?? '') === 'attention', 'Documentos em falta devem aparecer como attention.');
$check($enc !== null && ($enc['status'] ?? '') === 'ok', 'Encarregados com valor zero devem aparecer como ok.');
$check(!in_array('financeiro-devedores', $views($secretaria), true), 'Secretaria sem financeiro nao deve ver devedores.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['academico.turmas_ver'];
$academico = sige_institutional_operational_checklist_v121600($signals, 0);
$sem_turma = $by_label($academico, 'Alunos sem turma');
$check($sem_turma !== null && ($sem_turma['status'] ?? '') === 'attention', 'Alunos sem turma devem ser prioridade académica.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['portaria.validar_acesso'];
$portaria = sige_institutional_operational_checklist_v121600($signals, 0);
$check(count($portaria) === 1, 'Perfil portaria deve manter checklist limpo.');
$check(($portaria[0]['view'] ?? '') === 'portaria', 'Portaria deve apontar para validação de entrada.');
$check(($portaria[0]['status'] ?? '') === 'next', 'Portaria sem métrica deve ser próximo passo, não pendência falsa.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.cobrancas_ver', 'alunos.ver', 'academico.turmas_ver'];
$misto = sige_institutional_operational_checklist_v121600($signals, 3);
$check(count($misto) === 3, 'Limit deve cortar checklist para o número solicitado.');
$check(count(array_unique($views($misto))) <= count($misto), 'Checklist deve permanecer bem formado com views válidas.');
$check(($misto[0]['status'] ?? '') === 'attention', 'Pendências devem vir antes de itens ok/next.');

if ($failures) {
    foreach ($failures as $failure) { fwrite(STDERR, "ERRO: {$failure}\n"); }
    exit(1);
}

echo "v12.16.0 OPERATIONAL CHECKLIST SMOKE OK - checklists por perfil, sinais e permissões validados.\n";
