<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke do direito de acesso e portabilidade (Fase 8 incremento 2).
 *
 * Executa o dossie contra um $wpdb simulado (so leitura), validando:
 *  - o dossie reune valores reais para um aluno valido da escola;
 *  - fail-closed: aluno fora da escola, escola <= 0 e aluno inexistente sao recusados;
 *  - isolamento por escola nas linhas (escola errada nao devolve nada);
 *  - a lista branca de colunas descarta identificadores invalidos antes do SQL;
 *  - sige_pii_dossier_json produz JSON valido com aluno e seccoes (portabilidade).
 */

define('SIGE_PRIVACY_TEST_MODE', 1);
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
$root = dirname(__DIR__);
require_once $root . '/includes/privacy/pii-catalog.php';
require_once $root . '/includes/privacy/pii-inventario.php';
require_once $root . '/includes/privacy/pii-dossier.php';

if (!function_exists('current_time')) { function current_time($t = 'mysql') { return gmdate('Y-m-d H:i:s'); } }

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) {
    if (!$cond) { $falhas[] = $msg; }
};

/** $wpdb simulado, so leitura, com um unico par valido aluno(7) - escola(3). */
class SigeAcessoMockWpdb {
    public $prefix = 'wp_';
    public $tables = [];
    public $aluno_id = 7;
    public $escola_id = 3;
    public $nome = 'Maria Joao Teste';
    public $processo = '2026-0007';

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
            if (preg_match("/LIKE '([^']+)'/", $sql, $m)) { return isset($this->tables[$m[1]]) ? $m[1] : null; }
            return null;
        }
        if (preg_match('/SELECT id FROM .*WHERE id = (\d+) AND escola_id = (\d+)/s', $sql, $m)) {
            return ((int) $m[1] === $this->aluno_id && (int) $m[2] === $this->escola_id) ? (string) $this->aluno_id : null;
        }
        return null;
    }

    public function get_row($sql, $output = null) {
        if (preg_match('/FROM `([^`]+)`.*WHERE id = (\d+) AND escola_id = (\d+)/s', $sql, $m)) {
            if ((int) $m[2] === $this->aluno_id && (int) $m[3] === $this->escola_id) {
                return ['nome_completo' => $this->nome, 'numero_processo' => $this->processo];
            }
        }
        return null;
    }

    public function get_results($sql, $output = null) {
        if (stripos($sql, 'SHOW COLUMNS FROM') !== false && preg_match('/FROM `([^`]+)`/', $sql, $m)) {
            if (isset($this->tables[$m[1]])) {
                return array_map(function ($c) { return (object) ['Field' => $c]; }, $this->tables[$m[1]]);
            }
            return [];
        }
        // SELECT do dossie: SELECT `c1`, `c2` FROM `wp_x` WHERE `wc` = N AND escola_id = M ORDER BY id DESC LIMIT L
        if (preg_match('/SELECT (.+?) FROM `([^`]+)` WHERE `?(\w+)`? = (\d+) AND escola_id = (\d+)/s', $sql, $m)) {
            $cols_raw = $m[1]; $wc = $m[3]; $n = (int) $m[4]; $esc = (int) $m[5];
            if ($n !== $this->aluno_id || $esc !== $this->escola_id) { return []; } // isolamento por escola
            $cols = array_map(static function ($c) { return trim(str_replace('`', '', $c)); }, explode(',', $cols_raw));
            $nrows = ($wc === 'id') ? 1 : 2;
            $rows = [];
            for ($r = 0; $r < $nrows; $r++) {
                $row = [];
                foreach ($cols as $c) {
                    if ($c === 'nome_completo') { $row[$c] = $this->nome; }
                    elseif ($c === 'numero_processo') { $row[$c] = $this->processo; }
                    else { $row[$c] = 'valor_' . $c . '_' . $r; }
                }
                $rows[] = $row;
            }
            return $rows;
        }
        return [];
    }
}

// Esquema simulado a partir do catalogo: todas as tabelas presentes, com as
// colunas de ligacao necessarias.
$por_tabela = sige_pii_catalogo_por_tabela();
$mock = new SigeAcessoMockWpdb();
foreach ($por_tabela as $tabela => $entradas) {
    $cols = array_map(static function ($e) { return $e['coluna']; }, $entradas);
    $base = ($tabela === 'sige_alunos') ? ['id', 'escola_id'] : ['id', 'escola_id', 'aluno_id'];
    $mock->tables['wp_' . $tabela] = array_values(array_unique(array_merge($base, $cols)));
}
$GLOBALS['wpdb'] = $mock;

// ── 1. Dossie valido reune valores reais ─────────────────────────────────────
$d = sige_pii_dossier(7, 3);
$ok(!empty($d['ok']), 'dossie de aluno valido deve ter ok=true');
$ok(($d['identificacao']['numero_processo'] ?? '') === '2026-0007', 'identificacao do dossie incorrecta');
$ok(($d['total_seccoes'] ?? 0) > 0, 'dossie deve ter seccoes');
$ok(($d['total_registos'] ?? 0) > 0, 'dossie deve ter registos');
// A seccao de sige_alunos deve conter o nome real.
$nome_presente = false;
foreach (($d['seccoes'] ?? []) as $sec) {
    if ($sec['tabela'] === 'sige_alunos') {
        foreach ($sec['linhas'] as $linha) {
            if (($linha['nome_completo'] ?? '') === 'Maria Joao Teste') { $nome_presente = true; }
        }
    }
}
$ok($nome_presente, 'dossie devia conter o nome real do aluno na seccao sige_alunos');

// ── 2. Fail-closed ───────────────────────────────────────────────────────────
$ok(empty(sige_pii_dossier(7, 99)['ok']), 'fail-closed: aluno fora da escola deve ser recusado');
$ok(empty(sige_pii_dossier(0, 3)['ok']), 'fail-closed: aluno_id <= 0 deve ser recusado');
$ok(empty(sige_pii_dossier(7, 0)['ok']), 'fail-closed: escola <= 0 deve ser recusada');
$ok(empty(sige_pii_dossier(999, 3)['ok']), 'fail-closed: aluno inexistente deve ser recusado');
$ok(sige_pii_dossier_aluno_pertence(7, 3) === true, 'aluno valido devia pertencer a escola');
$ok(sige_pii_dossier_aluno_pertence(7, 99) === false, 'aluno nao devia pertencer a escola errada');

// ── 3. Isolamento por escola nas linhas ──────────────────────────────────────
$linhas_ok = sige_pii_dossier_linhas('sige_alunos', ['nome_completo'], 'id', 7, 3);
$ok(!empty($linhas_ok), 'linhas de aluno valido nao deviam ser vazias');
$linhas_outra = sige_pii_dossier_linhas('sige_alunos', ['nome_completo'], 'id', 7, 99);
$ok($linhas_outra === [], 'isolamento: escola errada nao deve devolver linhas');

// ── 4. Lista branca de colunas ───────────────────────────────────────────────
$linhas_wl = sige_pii_dossier_linhas('sige_alunos', ['nome_completo', 'mau; DROP TABLE x'], 'id', 7, 3);
$ok(!empty($linhas_wl), 'lista branca: coluna valida devia continuar a devolver linhas');
$ok(!array_key_exists('mau; DROP TABLE x', $linhas_wl[0] ?? []), 'lista branca: coluna invalida devia ser descartada');
$ok(array_key_exists('nome_completo', $linhas_wl[0] ?? []), 'lista branca: coluna valida devia manter-se');

// ── 5. Portabilidade: JSON valido ────────────────────────────────────────────
$json = sige_pii_dossier_json($d);
$dec = json_decode($json, true);
$ok(is_array($dec), 'JSON do dossie deve descodificar');
$ok(($dec['aluno']['numero_processo'] ?? '') === '2026-0007', 'JSON deve conter o numero de processo');
$ok(!empty($dec['seccoes']), 'JSON deve conter seccoes');
$ok(isset($dec['aviso']) && $dec['aviso'] !== '', 'JSON deve conter aviso de confidencialidade');

if (!empty($falhas)) {
    echo "SMOKE ACESSO FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE ACESSO OK - dossie reune dados reais do aluno valido; fail-closed por escola/aluno; isolamento por escola; lista branca de colunas; JSON de portabilidade valido.\n";
exit(0);
