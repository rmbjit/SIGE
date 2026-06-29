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
$by_title = static function (array $items, string $title): ?array {
    foreach ($items as $item) {
        if ((string)($item['title'] ?? '') === $title) { return $item; }
    }
    return null;
};
$views = static function (array $items): array {
    return array_values(array_map(static function (array $item): string {
        return (string)($item['view'] ?? '');
    }, $items));
};

$signals = [
    'alunos_com_divida' => 8,
    'pagamentos_hoje' => 3,
    'alunos_sem_doc' => 2,
    'alunos_sem_encarregado' => 1,
    'alunos_sem_turma' => 0,
];

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = [];
$none = sige_institutional_flow_guidance_v121600($signals, 0);
$check($none === [], 'Sem permissao nao deve haver fluxos guiados.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.pagar'];
$payments = sige_institutional_flow_guidance_v121600($signals, 0);
$payment = $by_title($payments, 'Registar pagamento');
$check($payment !== null, 'Tesouraria deve ver fluxo de registo de pagamento.');
$check(($payment['status'] ?? '') === 'attention', 'Dividas positivas devem priorizar pagamento como attention.');
$check(count((array)($payment['steps'] ?? [])) === 3, 'Pagamento deve ter tres passos operacionais.');
$check(!in_array('alunos_lista', $views($payments), true), 'Financeiro isolado nao deve expor fluxo de novo aluno.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.lancar_mensalidades'];
$billing = sige_institutional_flow_guidance_v121600($signals, 0);
$billing_flow = $by_title($billing, 'Lançar mensalidades');
$check($billing_flow !== null, 'Permissao de mensalidades deve expor lancador guiado.');
$check(($billing_flow['view'] ?? '') === 'financeiro-gerador', 'Mensalidades devem apontar para financeiro-gerador.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['alunos.criar', 'matriculas.criar'];
$secretaria = sige_institutional_flow_guidance_v121600($signals, 0);
$new_student = $by_title($secretaria, 'Novo aluno');
$check($new_student !== null, 'Secretaria deve ver fluxo de novo aluno.');
$check(($new_student['status'] ?? '') === 'attention', 'Documentos pendentes devem marcar novo aluno como attention.');
$check(strpos((string)($new_student['href'] ?? ''), 'view=alunos_lista') !== false, 'Novo aluno deve apontar para alunos_lista.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['academico.lancar_notas'];
$academic = sige_institutional_flow_guidance_v121600($signals, 0);
$grades = $by_title($academic, 'Lançar notas');
$check($grades !== null, 'Professor deve ver fluxo de lançamento de notas.');
$check(strpos((string)($grades['guardrail'] ?? ''), 'não altera cálculo') !== false, 'Microcopy académico deve explicitar que não altera cálculo.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['portaria.validar_acesso'];
$gate = sige_institutional_flow_guidance_v121600($signals, 0);
$check(count($gate) === 1, 'Portaria deve manter apenas um fluxo principal.');
$check(($gate[0]['view'] ?? '') === 'portaria', 'Portaria deve apontar para validacao.');
$check(($gate[0]['status'] ?? '') === 'guided', 'Portaria sem métrica nao deve criar falsa pendencia.');

$GLOBALS['SIGE_V121600_ALLOWED_PERMISSIONS'] = ['financeiro.pagar', 'alunos.criar', 'matriculas.criar', 'academico.lancar_notas'];
$mixed = sige_institutional_flow_guidance_v121600($signals, 2);
$check(count($mixed) === 2, 'Limit deve cortar fluxos guiados para o numero pedido.');
$check(($mixed[0]['status'] ?? '') === 'attention', 'Fluxos com sinal operacional devem vir primeiro.');
$all_views = $views($mixed);
$check(count($all_views) === count(array_unique($all_views)), 'Fluxos exibidos nao devem duplicar views no mesmo bloco.');

if ($failures) {
    foreach ($failures as $failure) { fwrite(STDERR, "ERRO: {$failure}\n"); }
    exit(1);
}

echo "v12.16.0 FLOW GUIDANCE SMOKE OK - fluxos por perfil, microcopy, limites e permissões validados.\n";
