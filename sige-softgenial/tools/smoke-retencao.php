<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke da retencao e expurgo (Fase 8 incremento 4) - so leitura.
 *
 * Executa o motor contra um $wpdb simulado, validando:
 *  - o panorama conta os registos alem do prazo por categoria, dentro da escola;
 *  - a contagem de sige_alunos restringe a alunos nao activos (status <> activo);
 *  - cada contagem isola por escola_id;
 *  - uma tabela sem a coluna de data de aferir nao e mensuravel (devolve null);
 *  - fail-closed: escola <= 0 nao conta nada;
 *  - o registo de acessos esta retido (fonte das presencas, nao expurgavel);
 *  - cutoff recua no tempo e prazo_legivel apresenta anos e meses.
 */

define('SIGE_PRIVACY_TEST_MODE', 1);
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
$root = dirname(__DIR__);
require_once $root . '/includes/privacy/pii-inventario.php';
require_once $root . '/includes/privacy/pii-retencao.php';

if (!function_exists('current_time')) {
    function current_time($t = 'mysql') { return $t === 'timestamp' ? time() : gmdate('Y-m-d H:i:s'); }
}

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) {
    if (!$cond) { $falhas[] = $msg; }
};

/**
 * $wpdb simulado. Algumas tabelas existem com a sua coluna de data de aferir;
 * sige_mpesa_transacoes existe mas SEM a coluna criado_em (para testar o caso
 * nao mensuravel). Captura as queries de contagem.
 */
class SigeRetencaoMockWpdb {
    public $prefix = 'wp_';
    public $escola_id = 3;
    public $contagens = [];

    public $schema = [
        'wp_sige_alunos'                => ['id', 'escola_id', 'data_registo', 'status', 'nome_completo'],
        'wp_sige_acessos'               => ['id', 'escola_id', 'aluno_id', 'data_hora'],
        'wp_sige_fin_pagamentos'        => ['id', 'escola_id', 'aluno_id', 'data_pagamento', 'valor'],
        'wp_sige_fin_contactos_cobranca'=> ['id', 'escola_id', 'aluno_id', 'data_contacto', 'notas'],
        'wp_sige_mpesa_transacoes'      => ['id', 'escola_id', 'msisdn'], // SEM criado_em -> nao mensuravel
    ];

    // Contagem deterministica por tabela (registos alem do prazo).
    public $por_tabela = [
        'wp_sige_alunos'                 => 4,
        'wp_sige_acessos'                => 100,
        'wp_sige_fin_pagamentos'         => 7,
        'wp_sige_fin_contactos_cobranca' => 5,
    ];

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
            if (preg_match("/LIKE '([^']+)'/", $sql, $m)) { return isset($this->schema[$m[1]]) ? $m[1] : null; }
            return null;
        }
        if (stripos($sql, 'SELECT COUNT(*)') !== false && preg_match('/FROM `([^`]+)`.*escola_id = (\d+)/s', $sql, $m)) {
            $tab = $m[1]; $esc = (int) $m[2];
            $this->contagens[] = ['tabela' => $tab, 'sql' => $sql];
            if ($esc !== $this->escola_id) { return 0; } // isolamento por escola
            return $this->por_tabela[$tab] ?? 0;
        }
        return null;
    }

    public function get_results($sql, $output = null) {
        if (stripos($sql, 'SHOW COLUMNS FROM') !== false && preg_match('/FROM `([^`]+)`/', $sql, $m)) {
            if (isset($this->schema[$m[1]])) {
                return array_map(function ($c) { return (object) ['Field' => $c]; }, $this->schema[$m[1]]);
            }
            return [];
        }
        return [];
    }
}

global $wpdb;
$wpdb = new SigeRetencaoMockWpdb();

// 1. Panorama dentro da escola.
$pan = sige_pii_retencao_panorama(3);
$ok(!empty($pan['ok']), 'panorama deve ter sucesso para escola valida');
$ok(!empty($pan['linhas']), 'panorama deve listar categorias');
$ok(count($pan['linhas']) === count(sige_pii_retencao_calendario()), 'panorama deve cobrir todo o calendario');

// Indexar por tabela.
$porTab = [];
foreach ($pan['linhas'] as $l) { $porTab[$l['tabela']] = $l; }

$ok(($porTab['sige_alunos']['excedido'] ?? -1) === 4, 'sige_alunos deve contar 4 alem do prazo');
$ok(($porTab['sige_acessos']['excedido'] ?? -1) === 100, 'sige_acessos deve contar 100 alem do prazo');
$ok(($porTab['sige_alunos']['mensuravel'] ?? false) === true, 'sige_alunos deve ser mensuravel');
$ok(($porTab['sige_mpesa_transacoes']['mensuravel'] ?? true) === false, 'sige_mpesa_transacoes sem coluna de data nao deve ser mensuravel');
$ok(($porTab['sige_mpesa_transacoes']['excedido'] ?? -1) === 0, 'tabela nao mensuravel conta 0');

// Total e total anonimizavel (acessos e auditoria sao retidos, nao contam para anonimizavel).
$ok(($pan['total_excedido'] ?? 0) === (4 + 100 + 7 + 5), 'total alem do prazo deve somar todas as categorias mensuraveis');
$ok(($pan['total_anonimizavel'] ?? -1) === (4 + 7 + 5), 'total anonimizavel deve excluir os retidos (acessos)');

// 2. A contagem de sige_alunos restringe a nao activos e isola por escola.
$q_alunos = '';
foreach ($wpdb->contagens as $c) { if ($c['tabela'] === 'wp_sige_alunos') { $q_alunos = $c['sql']; } }
$ok($q_alunos !== '', 'deve existir contagem para sige_alunos');
$ok(strpos($q_alunos, "status <> 'activo'") !== false, 'contagem de sige_alunos deve restringir a alunos nao activos');
$ok(strpos($q_alunos, 'escola_id = 3') !== false, 'contagem de sige_alunos deve isolar por escola_id');

// As tabelas sem so_inactivos NAO devem ter o filtro de estado.
$q_acessos = '';
foreach ($wpdb->contagens as $c) { if ($c['tabela'] === 'wp_sige_acessos') { $q_acessos = $c['sql']; } }
$ok(strpos($q_acessos, "status <>") === false, 'contagem de acessos nao deve filtrar por estado');

// 3. O registo de acessos esta retido.
$ok(($porTab['sige_acessos']['modo'] ?? '') === 'retido', 'sige_acessos deve estar retido (fonte das presencas)');

// 4. Fail-closed.
$pan0 = sige_pii_retencao_panorama(0);
$ok(empty($pan0['ok']), 'panorama deve recusar escola = 0');
$ok(sige_pii_retencao_contar_excedido(['tabela' => 'sige_alunos', 'coluna_data' => 'data_registo', 'prazo_meses' => 12], 0) === null, 'contagem deve recusar escola = 0');

// 5. Isolamento: escola diferente conta 0.
$wpdb2 = new SigeRetencaoMockWpdb();
$wpdb2->escola_id = 3;
$GLOBALS['wpdb'] = $wpdb2;
$n_outra = sige_pii_retencao_contar_excedido(['tabela' => 'sige_alunos', 'coluna_data' => 'data_registo', 'prazo_meses' => 12, 'so_inactivos' => true], 99);
$ok($n_outra === 0, 'contagem de outra escola deve ser 0 (isolamento)');
$GLOBALS['wpdb'] = $wpdb;

// 6. Funcoes puras.
$ok(strtotime(sige_pii_retencao_cutoff(12)) < time(), 'cutoff deve recuar no tempo');
$ok(sige_pii_retencao_prazo_legivel(120) === '10 anos', 'prazo_legivel 120 meses deve ser 10 anos');
$ok(sige_pii_retencao_prazo_legivel(18) === '1 ano e 6 meses', 'prazo_legivel 18 meses deve ser 1 ano e 6 meses');
$ok(sige_pii_retencao_prazo_legivel(1) === '1 mes', 'prazo_legivel 1 mes');

if (!empty($falhas)) {
    echo "SMOKE RETENCAO FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE RETENCAO OK - panorama conta alem do prazo por categoria e por escola, sige_alunos restrito a nao activos, isolamento por escola_id, tabela sem data nao mensuravel, fail-closed, acessos retidos, funcoes puras correctas.\n";
exit(0);
