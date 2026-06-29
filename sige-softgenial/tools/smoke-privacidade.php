<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke do inventario de dados pessoais (Fase 8 incremento 1).
 *
 * Executa o catalogo e o inventario contra um $wpdb simulado, validando:
 *  - integridade do catalogo;
 *  - deteccao de DESVIOS (campo catalogado ausente do esquema);
 *  - deteccao de LACUNAS (coluna PII no esquema fora do catalogo);
 *  - agregados por escola (contagens) para escola valida;
 *  - fail-closed: escola invalida zera os agregados e marca escola_valida=false.
 */

define('SIGE_PRIVACY_TEST_MODE', 1);
$root = dirname(__DIR__);
require_once $root . '/includes/privacy/pii-catalog.php';
require_once $root . '/includes/privacy/pii-inventario.php';

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) {
    if (!$cond) { $falhas[] = $msg; }
};

/** $wpdb simulado, so leitura. */
class SigePrivMockWpdb {
    public $prefix = 'wp_';
    public $tables = [];   // 'wp_sige_x' => [colunas...]
    public $contagens = []; // 'wp_sige_x' => inteiro devolvido por COUNT/SUM

    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) { $args = $args[0]; }
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $args) {
            $v = $args[$i] ?? ''; $i++;
            if ($m[0] === '%d') { return (string) (int) $v; }
            return "'" . str_replace("'", "''", (string) $v) . "'";
        }, $sql);
    }

    public function get_var($sql) {
        if (stripos($sql, 'SHOW TABLES LIKE') !== false) {
            if (preg_match("/LIKE '([^']+)'/", $sql, $m)) {
                return isset($this->tables[$m[1]]) ? $m[1] : null;
            }
            return null;
        }
        // COUNT/SUM: devolve valor deterministico por tabela.
        if (preg_match('/FROM `([^`]+)`/', $sql, $m)) {
            return isset($this->contagens[$m[1]]) ? (string) $this->contagens[$m[1]] : '0';
        }
        return '0';
    }

    public function get_results($sql) {
        if (stripos($sql, 'SHOW COLUMNS FROM') !== false && preg_match('/FROM `([^`]+)`/', $sql, $m)) {
            if (isset($this->tables[$m[1]])) {
                return array_map(function ($c) { return (object) ['Field' => $c]; }, $this->tables[$m[1]]);
            }
        }
        return [];
    }
}

// Construir um esquema simulado a partir do proprio catalogo, e depois mutar:
//  - remover 'temperatura' de sige_jardim_saude  => deve aparecer em DESVIOS;
//  - adicionar 'segundo_telemovel' a sige_alunos  => deve aparecer em LACUNAS;
//  - omitir sige_acessos por completo             => tabela_existe=false.
$por_tabela = sige_pii_catalogo_por_tabela();
$mock = new SigePrivMockWpdb();
foreach ($por_tabela as $tabela => $entradas) {
    if ($tabela === 'sige_acessos') { continue; } // tabela ausente
    $cols = array_map(static function ($e) { return $e['coluna']; }, $entradas);
    $cols = array_merge(['id', 'escola_id'], $cols);
    if ($tabela === 'sige_jardim_saude') {
        $cols = array_values(array_filter($cols, static function ($c) { return $c !== 'temperatura'; }));
    }
    if ($tabela === 'sige_alunos') {
        $cols[] = 'segundo_telemovel'; // PII por classificar
    }
    $mock->tables['wp_' . $tabela] = $cols;
    $mock->contagens['wp_' . $tabela] = 7; // qualquer contagem positiva
}
$GLOBALS['wpdb'] = $mock;

// ── 1. Catalogo integro ──────────────────────────────────────────────────────
$cat = sige_pii_catalogo();
$ok(count($cat) >= 30, 'catalogo deve ter pelo menos 30 campos, tem ' . count($cat));
$ok(count(array_filter($cat, static function ($e) { return $e['sensibilidade'] === 'sensivel'; })) > 0, 'catalogo deve ter campos sensiveis');

// ── 2. Cobertura: desvios e lacunas ──────────────────────────────────────────
$cob = sige_pii_cobertura();
$desvios = array_map(static function ($d) { return $d['tabela'] . '.' . $d['coluna']; }, $cob['desvios']);
$lacunas = array_map(static function ($l) { return $l['tabela'] . '.' . $l['coluna']; }, $cob['lacunas']);
$ok(in_array('sige_jardim_saude.temperatura', $desvios, true), 'DESVIO esperado (sige_jardim_saude.temperatura) nao detectado');
$ok(in_array('sige_alunos.segundo_telemovel', $lacunas, true), 'LACUNA esperada (sige_alunos.segundo_telemovel) nao detectada');

// sige_acessos ausente => tabela_existe=false e os seus campos nao contam como desvio.
$acessos_existe = null;
foreach ($cob['cobertura'] as $t) { if ($t['tabela'] === 'sige_acessos') { $acessos_existe = $t['tabela_existe']; } }
$ok($acessos_existe === false, 'sige_acessos devia constar como tabela ausente');
$ok(!in_array('sige_acessos.data_hora', $desvios, true), 'tabela ausente nao deve gerar desvios');

// ── 3. Agregados por escola valida ───────────────────────────────────────────
$agg = sige_pii_agregados(5);
$ok(!empty($agg), 'agregados de escola valida nao devem ser vazios');
$ok(isset($agg['titulares_alunos']) && is_int($agg['titulares_alunos']), 'agregado titulares_alunos deve ser inteiro');
$ok(($agg['titulares_alunos'] ?? 0) > 0, 'agregado titulares_alunos deve ser positivo no esquema simulado');

// ── 4. Fail-closed por escola ────────────────────────────────────────────────
$agg0 = sige_pii_agregados(0);
$ok($agg0 === [], 'fail-closed: escola <= 0 deve devolver agregados vazios');
$aggNeg = sige_pii_agregados(-3);
$ok($aggNeg === [], 'fail-closed: escola negativa deve devolver agregados vazios');
$ok(sige_pii_contar('sige_alunos', 'COUNT(*)', 0) === 0, 'fail-closed: contar com escola 0 deve ser 0');

// ── 5. Inventario completo ───────────────────────────────────────────────────
$inv0 = sige_pii_inventario(0);
$ok($inv0['escola_valida'] === false, 'inventario(0) deve marcar escola_valida=false');
$ok($inv0['agregados'] === [], 'inventario(0) deve ter agregados vazios');

$inv = sige_pii_inventario(5);
$ok($inv['escola_valida'] === true, 'inventario(5) deve marcar escola_valida=true');
$ok($inv['resumo']['total_campos'] === count($cat), 'resumo total_campos deve igualar o catalogo');
$ok($inv['resumo']['desvios'] >= 1, 'resumo deve reflectir pelo menos um desvio');
$ok($inv['resumo']['lacunas'] >= 1, 'resumo deve reflectir pelo menos uma lacuna');
$ok($inv['resumo']['campos_sensiveis'] > 0, 'resumo deve contar campos sensiveis');
$ok(!empty($inv['agregados']), 'inventario(5) deve ter agregados');

if (!empty($falhas)) {
    echo "SMOKE PRIVACIDADE FALHOU:\n";
    foreach ($falhas as $f) { echo " - {$f}\n"; }
    exit(1);
}
echo "SMOKE PRIVACIDADE OK - catalogo integro, desvios e lacunas detectados, agregados por escola, fail-closed confirmado.\n";
exit(0);
