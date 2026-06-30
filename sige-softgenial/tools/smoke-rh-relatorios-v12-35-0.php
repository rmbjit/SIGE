<?php
/**
 * Smoke v12.35.0 - Relatórios de RH.
 *
 * (1) FUNCIONAL: a função pura sige_rh_build_reports agrega correctamente
 *     (categoria/vínculo/regime/carreira, admissões, antiguidade, salário e
 *     qualidade), considerando só activos nas distribuições.
 * (2) LIGAÇÃO: bootstrap carrega o include; a view monta $sige_rh_people e
 *     rende a aba de Relatórios (sem 2.ª query).
 *
 * Uso: php tools/smoke-rh-relatorios-v12-35-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('apply_filters')) { function apply_filters($t, $v) { return $v; } }

require_once $root . '/includes/rh-relatorios.php';

// ── (1) FUNCIONAL ──────────────────────────────────────────────────────────────
$today  = '2026-06-30';
$people = [
    ['categoria' => 'docente',        'tem_ficha' => true,  'ativo' => true,  'tipo_contrato' => 'efectivo', 'nivel_carreira' => 'tecnico_n1', 'regime_trabalho' => 'tempo_integral', 'data_admissao' => '2020-06-30', 'salario' => 30000, 'nuit' => '123', 'telemovel' => '84', 'email' => 'a@x', 'foto' => true],
    ['categoria' => 'docente',        'tem_ficha' => true,  'ativo' => true,  'tipo_contrato' => 'contrato', 'nivel_carreira' => 'tecnico_n1', 'regime_trabalho' => 'tempo_integral', 'data_admissao' => '2024-06-30', 'salario' => 20000, 'nuit' => '',    'telemovel' => '',   'email' => 'b@x', 'foto' => false],
    ['categoria' => 'administrativo', 'tem_ficha' => true,  'ativo' => true,  'tipo_contrato' => 'estagio',  'nivel_carreira' => 'auxiliar',   'regime_trabalho' => 'tempo_parcial',  'data_admissao' => '2026-01-01', 'salario' => 0,     'nuit' => '999', 'telemovel' => '85', 'email' => 'c@x', 'foto' => true],
    ['categoria' => 'apoio',          'tem_ficha' => false, 'ativo' => true,  'tipo_contrato' => '',         'nivel_carreira' => '',           'regime_trabalho' => '',               'data_admissao' => '',           'salario' => 0,     'nuit' => '',    'telemovel' => '',   'email' => 'd@x', 'foto' => false],
    ['categoria' => 'administrativo', 'tem_ficha' => true,  'ativo' => false, 'tipo_contrato' => 'efectivo', 'nivel_carreira' => 'tecnico_n1', 'regime_trabalho' => 'tempo_integral', 'data_admissao' => '2019-01-01', 'salario' => 50000, 'nuit' => '777', 'telemovel' => '86', 'email' => 'e@x', 'foto' => true],
];
$R = sige_rh_build_reports($people, ['today' => $today]);

_p($fails, $R['total'] === 5, 'Total de registos = 5 -> ' . $R['total']);
_p($fails, $R['ativos'] === 4, 'Activos = 4 -> ' . $R['ativos']);
_p($fails, $R['inativos'] === 1, 'Inactivos = 1 -> ' . $R['inativos']);
_p($fails, $R['sem_ficha'] === 1, 'Sem ficha = 1 -> ' . $R['sem_ficha']);

// Categoria (só activos).
$cat = [];
foreach ($R['categoria'] as $c) { $cat[$c['slug']] = $c['count']; }
_p($fails, $cat['docente'] === 2 && $cat['administrativo'] === 1 && $cat['apoio'] === 1, 'Categoria: docente=2, admin=1, apoio=1');

// Vínculo (ordem fixa: efectivo, contrato, estagio, '').
_p($fails, (int) $R['vinculo'][0]['count'] === 1, 'Vínculo efectivo = 1 (inactivo excluído)');
_p($fails, (int) $R['vinculo'][1]['count'] === 1, 'Vínculo contrato = 1');
_p($fails, (int) $R['vinculo'][2]['count'] === 1, 'Vínculo estágio = 1');
_p($fails, (int) $R['vinculo'][3]['count'] === 1, 'Vínculo não definido = 1 (pessoa sem ficha)');

// Carreira: tecnico_n1=2 no topo.
_p($fails, $R['carreira'][0]['slug'] === 'tecnico_n1' && (int) $R['carreira'][0]['count'] === 2, 'Carreira mais comum = tecnico_n1 (2)');
// Regime: tempo_integral=2 no topo.
_p($fails, (int) $R['regime'][0]['count'] === 2, 'Regime mais comum tem 2');

// Admissões por ano (cronológico; inactivo de 2019 excluído).
_p($fails, ($R['admissoes']['2020'] ?? 0) === 1 && ($R['admissoes']['2024'] ?? 0) === 1 && ($R['admissoes']['2026'] ?? 0) === 1, 'Admissões: 2020/2024/2026 = 1 cada');
_p($fails, !isset($R['admissoes']['2019']), 'Admissão de inactivo (2019) não entra');

// Antiguidade média (~2.8 anos): robusto entre 2.5 e 3.0.
_p($fails, $R['antiguidade_media'] >= 2.5 && $R['antiguidade_media'] <= 3.0, 'Antiguidade média ~2.8 anos -> ' . $R['antiguidade_media']);

// Salário (só activos com valor > 0).
_p($fails, $R['salario']['com_valor'] === 2, 'Salário: 2 com valor (estágio 0 e sem-ficha 0 fora)');
_p($fails, abs($R['salario']['massa'] - 50000) < 0.01, 'Massa salarial = 50000');
_p($fails, abs($R['salario']['media'] - 25000) < 0.01, 'Média salarial = 25000');

// Qualidade (sobre 4 activos).
_p($fails, $R['qualidade']['ficha']['ok'] === 3 && $R['qualidade']['ficha']['falta'] === 1, 'Qualidade ficha: 3 ok / 1 falta');
_p($fails, $R['qualidade']['email']['ok'] === 4, 'Qualidade email: 4 ok');
_p($fails, $R['qualidade']['nuit']['ok'] === 2 && $R['qualidade']['nuit']['falta'] === 2, 'Qualidade NUIT: 2 ok / 2 falta');
_p($fails, $R['qualidade']['foto']['ok'] === 2, 'Qualidade foto: 2 ok');

// Helpers.
_p($fails, sige_rh_pct(2, 4) === 50, 'pct(2,4) = 50');
_p($fails, sige_rh_pct(0, 0) === 0, 'pct(0,0) = 0 (sem divisão por zero)');
_p($fails, sige_rh_categoria_label('docente') === 'Docentes', 'Rótulo categoria docente');
_p($fails, sige_rh_pretty_label('') === 'Não definido', 'Rótulo livre vazio = Não definido');

// Conjunto vazio não rebenta.
$E = sige_rh_build_reports([], ['today' => $today]);
_p($fails, $E['ativos'] === 0 && $E['antiguidade_media'] === 0.0 && $E['salario']['media'] === 0.0, 'Conjunto vazio: tudo a zero, sem erro');

// ── (2) LIGAÇÃO ──────────────────────────────────────────────────────────────
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($boot, "require_once SIGE_PATH . 'includes/rh-relatorios.php'") !== false, 'Bootstrap carrega includes/rh-relatorios.php');
_p($fails, strpos($equipe, '$sige_rh_people[] = [') !== false, 'Equipa monta $sige_rh_people no loop existente');
_p($fails, strpos($equipe, 'sige_rh_build_reports($sige_rh_people') !== false, 'Equipa chama sige_rh_build_reports (sem 2.ª query)');
_p($fails, strpos($equipe, 'class="sg-rh-tabs"') !== false, 'Barra de abas presente');
_p($fails, strpos($equipe, 'id="sg-rh-panel-relatorios"') !== false, 'Painel de Relatórios presente');
_p($fails, strpos($equipe, 'function sgRhSwitchTab(') !== false, 'Função sgRhSwitchTab presente');
_p($fails, strpos($equipe, 'data-sige-act=\'sgRhSwitchTab\'') !== false || strpos($equipe, 'data-sige-act="sgRhSwitchTab"') !== false, 'Abas despacham sgRhSwitchTab (CSP-safe)');
_p($fails, strpos($equipe, '.sg-rh-rep-card{') !== false, 'CSS dos relatórios presente (design system)');

// Versões sincronizadas.
preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb), 'Versões sincronizadas (header=const=build) -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-RELATORIOS FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-RELATORIOS OK - agregacao correcta e ligacao completa.\n";
exit(0);
