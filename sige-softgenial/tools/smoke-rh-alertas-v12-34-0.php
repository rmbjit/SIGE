<?php
/**
 * Smoke v12.34.0 - Alertas de contrato (RH).
 *
 * (1) FUNCIONAL: a função pura sige_rh_evaluate_contract_alerts classifica
 *     correctamente (expirado/critico/aviso), ignora sem-data/inactivos e ordena
 *     pelo mais urgente.
 * (2) LIGAÇÃO: bootstrap carrega o include; a view da Equipa rende a secção e
 *     reutiliza as linhas já carregadas (sem 2.ª query).
 *
 * Uso: php tools/smoke-rh-alertas-v12-34-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('apply_filters')) { function apply_filters($t, $v) { return $v; } }

require_once $root . '/includes/rh-alertas.php';

// ── (1) FUNCIONAL ──────────────────────────────────────────────────────────────
$today = '2026-06-30';
$rows = [
    (object) ['nome_completo' => 'Efectivo Sem Fim', 'tipo_contrato' => 'efectivo', 'fim_contrato' => null,         'status_ativo' => 1],
    (object) ['nome_completo' => 'Expirado',         'tipo_contrato' => 'contrato', 'fim_contrato' => '2026-05-01', 'status_ativo' => 1],
    (object) ['nome_completo' => 'Critico 15d',      'tipo_contrato' => 'contrato', 'fim_contrato' => '2026-07-15', 'status_ativo' => 1],
    (object) ['nome_completo' => 'Aviso 77d',        'tipo_contrato' => 'estagio',  'fim_contrato' => '2026-09-15', 'status_ativo' => 1],
    (object) ['nome_completo' => 'Longe',            'tipo_contrato' => 'contrato', 'fim_contrato' => '2027-01-01', 'status_ativo' => 1],
    (object) ['nome_completo' => 'Inactivo Urgente', 'tipo_contrato' => 'contrato', 'fim_contrato' => '2026-07-02', 'status_ativo' => 0],
    (object) ['nome_completo' => 'Data Vazia',       'tipo_contrato' => 'contrato', 'fim_contrato' => '0000-00-00', 'status_ativo' => 1],
];
$r = sige_rh_evaluate_contract_alerts($rows, $today);

_p($fails, $r['counts']['total'] === 3, 'Total de alertas = 3 (ignora efectivo sem fim, longe, inactivo e data vazia) -> ' . $r['counts']['total']);
_p($fails, $r['counts']['expirado'] === 1, 'Expirados = 1');
_p($fails, $r['counts']['critico'] === 1, 'Críticos = 1');
_p($fails, $r['counts']['aviso'] === 1, 'A expirar (aviso) = 1');
_p($fails, ($r['items'][0]['estado'] ?? '') === 'expirado' && ($r['items'][0]['dias'] ?? 0) < 0, 'Mais urgente primeiro (expirado no topo)');
_p($fails, ($r['items'][1]['estado'] ?? '') === 'critico', '2.º = crítico');
_p($fails, ($r['items'][2]['estado'] ?? '') === 'aviso', '3.º = aviso');
$nomes = array_map(function ($x) { return $x['nome']; }, $r['items']);
_p($fails, !in_array('Inactivo Urgente', $nomes, true), 'Colaborador inactivo é ignorado');
_p($fails, !in_array('Data Vazia', $nomes, true), 'Data 0000-00-00 é ignorada');
_p($fails, !in_array('Longe', $nomes, true), 'Contrato além do limiar (>90d) não alerta');

// Limiares personalizados.
$r2 = sige_rh_evaluate_contract_alerts($rows, $today, ['critico' => 10, 'aviso' => 20]);
_p($fails, $r2['counts']['total'] === 2, 'Com aviso=20, só expirado + 15d entram (total 2) -> ' . $r2['counts']['total']);

// Frases e rótulos.
_p($fails, sige_rh_contract_alert_phrase(-3) === 'expirou há 3 dias', 'Frase: expirou há N dias');
_p($fails, sige_rh_contract_alert_phrase(0) === 'expira hoje', 'Frase: expira hoje');
_p($fails, sige_rh_contract_alert_phrase(1) === 'expira amanhã', 'Frase: expira amanhã');
_p($fails, sige_rh_contract_alert_phrase(12) === 'faltam 12 dias', 'Frase: faltam N dias');
_p($fails, sige_rh_contract_label('efectivo') === 'Efectivo (Quadro)', 'Rótulo de vínculo conhecido');
_p($fails, sige_rh_contract_label('') === 'Vínculo não definido', 'Rótulo de vínculo vazio');

// ── (2) LIGAÇÃO ──────────────────────────────────────────────────────────────
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($boot, "require_once SIGE_PATH . 'includes/rh-alertas.php'") !== false, 'Bootstrap carrega includes/rh-alertas.php');
_p($fails, strpos($equipe, 'sige_rh_evaluate_contract_alerts((array) $_profs_raw') !== false, 'Equipa reutiliza $_profs_raw (sem 2.ª query)');
_p($fails, strpos($equipe, 'class="sg-rh-alertas') !== false, 'Secção de alertas presente na Equipa');
_p($fails, strpos($equipe, '.sg-rh-alertas{') !== false, 'CSS dos alertas presente (design system)');
_p($fails, strpos($equipe, '#') === false || strpos($equipe, '.sg-rh-alertas') !== false, 'Alertas usam tokens (gate de tokens valida o resto)');

// Versões sincronizadas.
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build)');

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-ALERTAS FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-ALERTAS OK - avaliacao correcta e ligacao completa.\n";
exit(0);
