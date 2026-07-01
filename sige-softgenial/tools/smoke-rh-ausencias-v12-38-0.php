<?php
/**
 * Smoke v12.38.0 - Férias & Ausências (Fase 1).
 *
 * (1) FUNCIONAL: contagem de dias (corridos/úteis), meio-dia e validação — puras.
 * (2) LIGAÇÃO: bootstrap carrega o include; AJAX registados; a view tem a aba,
 *     o painel, o modal, o CSS e as funções JS; versões sincronizadas.
 *
 * Uso: php tools/smoke-rh-ausencias-v12-38-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }

require_once $root . '/includes/rh-ausencias.php';

// ── (1) FUNCIONAL ──────────────────────────────────────────────────────────────
$c = sige_rh_ausencia_contar_dias('2026-07-01', '2026-07-07'); // Qua..Ter: 7 corridos, 5 úteis
_p($fails, $c['corridos'] === 7, 'Dias corridos = 7 -> ' . $c['corridos']);
_p($fails, $c['uteis'] === 5, 'Dias úteis = 5 (exclui Sáb/Dom) -> ' . $c['uteis']);

$fds = sige_rh_ausencia_contar_dias('2026-07-04', '2026-07-05'); // Sáb+Dom
_p($fails, $fds['corridos'] === 2 && $fds['uteis'] === 0, 'Fim de semana: 2 corridos, 0 úteis');

_p($fails, sige_rh_ausencia_dias_efectivos('2026-07-06', '2026-07-06', true) === 0.5, 'Meio dia (dia único) = 0.5');
_p($fails, sige_rh_ausencia_dias_efectivos('2026-07-06', '2026-07-08', true) === 3.0, 'Meio dia ignorado em intervalo (3 úteis)');

$vbad = sige_rh_ausencia_validar(['professor_id' => 3, 'tipo' => 'ferias', 'data_inicio' => '2026-07-10', 'data_fim' => '2026-07-01']);
_p($fails, $vbad['ok'] === false, 'Validação recusa fim < início');
$vtipo = sige_rh_ausencia_validar(['professor_id' => 3, 'tipo' => 'xpto', 'data_inicio' => '2026-07-01', 'data_fim' => '2026-07-02']);
_p($fails, $vtipo['ok'] === false, 'Validação recusa tipo inválido');
$vnoprof = sige_rh_ausencia_validar(['professor_id' => 0, 'tipo' => 'ferias', 'data_inicio' => '2026-07-01', 'data_fim' => '2026-07-02']);
_p($fails, $vnoprof['ok'] === false, 'Validação exige colaborador');
$vok = sige_rh_ausencia_validar(['professor_id' => 3, 'tipo' => 'doenca', 'data_inicio' => '2026-07-06', 'data_fim' => '2026-07-10']);
_p($fails, $vok['ok'] === true && (float) $vok['dados']['dias'] === 5.0 && $vok['dados']['tipo'] === 'doenca', 'Validação aceita e calcula dias (5)');

_p($fails, sige_rh_ausencia_tipo_label('ferias') === 'Férias', 'Rótulo de tipo (Férias)');
_p($fails, sige_rh_ausencia_estado_label('aprovada') === 'Aprovada', 'Rótulo de estado (Aprovada)');
_p($fails, count(sige_rh_ausencia_tipos()) === 5, '5 tipos disponíveis');

// ── (2) LIGAÇÃO ──────────────────────────────────────────────────────────────
$boot    = (string) @file_get_contents($root . '/sige-softgenial.php');
$incl    = (string) @file_get_contents($root . '/includes/rh-ausencias.php');
$equipe  = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$build   = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($boot, "require_once SIGE_PATH . 'includes/rh-ausencias.php'") !== false, 'Bootstrap carrega includes/rh-ausencias.php');
_p($fails, strpos($incl, "add_action('admin_init', 'sige_rh_ausencias_migrar'") !== false, 'Migração idempotente registada no admin_init');
_p($fails, strpos($incl, 'dbDelta(') !== false && strpos($incl, 'sige_rh_ausencias') !== false, 'Tabela criada por dbDelta');
foreach (['listar', 'guardar', 'estado', 'eliminar'] as $act) {
    _p($fails, strpos($incl, "wp_ajax_sige_rh_ausencia_{$act}") !== false, "AJAX registado: sige_rh_ausencia_{$act}");
}
_p($fails, strpos($incl, 'sige_ajax_equipe_can_manage') !== false, 'AJAX gated por gestão (sem permissões novas)');

_p($fails, strpos($equipe, 'id="sg-rh-tabbtn-ausencias"') !== false, 'Aba Ausências presente');
_p($fails, strpos($equipe, 'id="sg-rh-panel-ausencias"') !== false, 'Painel Ausências presente');
_p($fails, strpos($equipe, 'id="box-ausencia"') !== false, 'Modal de registo presente');
_p($fails, strpos($equipe, 'function sgAusCarregar()') !== false && strpos($equipe, 'function guardarAusencia()') !== false, 'Funções JS presentes');
_p($fails, strpos($equipe, "action: 'sige_rh_ausencia_listar'") !== false, 'JS chama o endpoint de listagem');
_p($fails, strpos($equipe, '.sg-aus-table{') !== false, 'CSS das ausências presente (design system)');
_p($fails, strpos($equipe, "addEventListener('change', sgAusCarregar)") !== false, 'Filtros ligados por change (não click)');
_p($fails, strpos($equipe, '#box-ausencia.sige-modal.active') !== false, 'Modal entra no failsafe (.active-driven)');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-AUSENCIAS FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-AUSENCIAS OK - Fase 1 completa e ligada.\n";
exit(0);
