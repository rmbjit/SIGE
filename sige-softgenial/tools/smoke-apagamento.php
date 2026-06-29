<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke do apagamento por anonimizacao (Fase 8 incremento 3).
 *
 * Executa o motor contra um $wpdb simulado e stateful, validando:
 *  - o plano reune as seccoes e exclui a lista de preservacao (numero_processo, aluno_id);
 *  - a execucao redige o nome (marcador) e NAO toca numero_processo nem colunas financeiras;
 *  - o UPDATE inclui sempre escola_id no WHERE (isolamento);
 *  - fail-closed: aluno fora da escola, escola <= 0 e aluno = 0 sao recusados;
 *  - idempotencia: apos a anonimizacao, o aluno e detectado como anonimizado;
 *  - a redaccao e segura quanto ao tipo (texto -> marcador; data/numero anulavel -> nulo;
 *    data nao anulavel -> sentinela; numero nao anulavel -> zero; enum -> mantido).
 */

define('SIGE_PRIVACY_TEST_MODE', 1);
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
$root = dirname(__DIR__);
require_once $root . '/includes/privacy/pii-catalog.php';
require_once $root . '/includes/privacy/pii-inventario.php';
require_once $root . '/includes/privacy/pii-dossier.php';
require_once $root . '/includes/privacy/pii-apagamento.php';

if (!function_exists('current_time')) { function current_time($t = 'mysql') { return gmdate('Y-m-d H:i:s'); } }

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) {
    if (!$cond) { $falhas[] = $msg; }
};

/**
 * $wpdb simulado e stateful. Aluno(7) na escola(3). Duas tabelas existem:
 * wp_sige_alunos e wp_sige_fin_pagamentos. Cada uma inclui colunas PII
 * catalogadas, a coluna de preservacao e uma coluna financeira NAO catalogada,
 * para provar que esta ultima nunca e tocada. Os UPDATE sao capturados.
 */
class SigeApagamentoMockWpdb {
    public $prefix = 'wp_';
    public $aluno_id = 7;
    public $escola_id = 3;
    public $nome = 'Maria Joao Teste';
    public $processo = '2026-0007';
    public $updates = [];

    // Colunas por tabela: [Field, Type, Null]. Inclui PII catalogada, preservacao e financeira.
    public $schema = [
        'wp_sige_alunos' => [
            ['id', 'bigint(20)', 'NO'],
            ['escola_id', 'bigint(20)', 'NO'],
            ['numero_processo', 'varchar(50)', 'YES'],   // catalogada mas PRESERVADA
            ['nome_completo', 'varchar(255)', 'NO'],     // texto NOT NULL -> marcador
            ['data_nascimento', 'date', 'NO'],           // data NOT NULL -> sentinela
            ['genero', 'char(1)', 'NO'],                 // texto curto -> marcador truncado
            ['observacoes', 'text', 'YES'],              // texto -> marcador
            ['consent_whatsapp', 'tinyint(1)', 'YES'],   // numero anulavel -> nulo
            ['foto', 'varchar(255)', 'YES'],             // Incr 3.2: identificavel -> marcador
            ['doc_bi_url', 'varchar(255)', 'YES'],       // Incr 3.2: BI digitalizado -> marcador
            ['contacto_emergencia_1', 'varchar(255)', 'YES'], // Incr 3.2: contacto -> marcador
            ['autorizado_buscar_telemovel', 'varchar(50)', 'YES'], // Incr 3.2: contacto -> marcador
            ['autorizado_buscar_documento', 'varchar(80)', 'YES'], // Incr 3.2: documento -> marcador
            ['encarregado_observacoes', 'text', 'YES'],  // Incr 3.2: notas -> marcador
            ['mensalidade_base', 'decimal(10,2)', 'YES'], // FINANCEIRA, nao catalogada -> intacta
        ],
        'wp_sige_fin_pagamentos' => [
            ['id', 'bigint(20)', 'NO'],
            ['escola_id', 'bigint(20)', 'NO'],
            ['aluno_id', 'bigint(20)', 'NO'],            // catalogada mas PRESERVADA (ligacao)
            ['referencia_externa', 'varchar(120)', 'YES'], // texto -> marcador
            ['observacoes', 'text', 'YES'],              // texto -> marcador
            ['valor', 'decimal(10,2)', 'NO'],            // FINANCEIRA, nao catalogada -> intacta
        ],
        'wp_sige_fin_contactos_cobranca' => [
            ['id', 'bigint(20)', 'NO'],
            ['escola_id', 'bigint(20)', 'NO'],
            ['aluno_id', 'bigint(20)', 'NO'],            // catalogada mas PRESERVADA (ligacao)
            ['canal', 'varchar(30)', 'NO'],              // texto -> marcador
            ['notas', 'text', 'YES'],                    // texto -> marcador
            ['data_contacto', 'datetime', 'NO'],         // Incr 3.2: catalogada mas PRESERVADA (operacional)
            ['proximo_contacto', 'date', 'YES'],         // Incr 3.2: catalogada mas PRESERVADA (operacional)
            ['valor_prometido', 'decimal(10,2)', 'YES'], // FINANCEIRA, nao catalogada -> intacta
        ],
    ];

    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) { $args = $args[0]; }
        $i = 0;
        return preg_replace_callback('/%[dsf]/', function ($m) use (&$i, $args) {
            $v = $args[$i] ?? ''; $i++;
            if ($m[0] === '%d') { return (string) (int) $v; }
            if ($m[0] === '%f') { return (string) (float) $v; }
            return "'" . str_replace("'", "''", (string) $v) . "'";
        }, $sql);
    }

    public function get_var($sql) {
        if (stripos($sql, 'SHOW TABLES LIKE') !== false) {
            if (preg_match("/LIKE '([^']+)'/", $sql, $m)) { return isset($this->schema[$m[1]]) ? $m[1] : null; }
            return null;
        }
        // esta_anonimizado: SELECT nome_completo FROM ... WHERE id=N AND escola_id=M
        if (preg_match('/SELECT nome_completo FROM .*WHERE id = (\d+) AND escola_id = (\d+)/s', $sql, $m)) {
            return ((int) $m[1] === $this->aluno_id && (int) $m[2] === $this->escola_id) ? $this->nome : null;
        }
        // aluno_pertence: SELECT id FROM ... WHERE id=N AND escola_id=M
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
            if (isset($this->schema[$m[1]])) {
                return array_map(function ($c) {
                    return (object) ['Field' => $c[0], 'Type' => $c[1], 'Null' => $c[2]];
                }, $this->schema[$m[1]]);
            }
            return [];
        }
        return [];
    }

    public function query($sql) {
        if (stripos($sql, 'UPDATE ') === 0 && preg_match('/UPDATE `([^`]+)`/', $sql, $m)) {
            $table = $m[1];
            $this->updates[] = ['table' => $table, 'sql' => $sql];
            // Estado: anonimizar o nome torna o aluno detectavel como anonimizado.
            if ($table === 'wp_sige_alunos' && strpos($sql, "`nome_completo` = '[apagado]'") !== false) {
                $this->nome = sige_pii_apagamento_marcador();
            }
            return ($table === 'wp_sige_fin_pagamentos') ? 2 : 1;
        }
        return 0;
    }
}

global $wpdb;
$wpdb = new SigeApagamentoMockWpdb();

// 1. Plano (pre-visualizacao). So leitura.
$plano = sige_pii_apagamento_plano(7, 3);
$ok(!empty($plano['ok']), 'plano deve ter sucesso para aluno valido da escola');
$ok(!empty($plano['seccoes']), 'plano deve conter seccoes');
$ok(($plano['total_colunas'] ?? 0) > 0, 'plano deve listar colunas a redigir');

// A lista de preservacao nunca aparece entre os alvos.
$alvos_cols = [];
foreach (($plano['seccoes'] ?? []) as $sec) {
    foreach ($sec['alvos'] as $a) { $alvos_cols[] = $a['coluna']; }
}
$ok(!in_array('numero_processo', $alvos_cols, true), 'numero_processo nunca deve ser um alvo (preservado)');
$ok(!in_array('aluno_id', $alvos_cols, true), 'aluno_id nunca deve ser um alvo (preservado)');
$ok(!in_array('mensalidade_base', $alvos_cols, true), 'coluna financeira nao catalogada nunca deve ser um alvo');
$ok(!in_array('valor', $alvos_cols, true), 'coluna financeira valor nunca deve ser um alvo');
$ok(in_array('nome_completo', $alvos_cols, true), 'nome_completo deve ser um alvo de redaccao');

// 2. Execucao da anonimizacao.
$res = sige_pii_apagamento_executar(7, 3);
$ok(!empty($res['ok']), 'execucao deve ter sucesso para aluno valido');
$ok(($res['total_colunas'] ?? 0) > 0, 'execucao deve redigir colunas');
$ok(!empty($wpdb->updates), 'execucao deve emitir UPDATEs');

// Inspeccao dos UPDATE capturados.
$upd_alunos = '';
$upd_pag = '';
foreach ($wpdb->updates as $u) {
    if ($u['table'] === 'wp_sige_alunos') { $upd_alunos = $u['sql']; }
    if ($u['table'] === 'wp_sige_fin_pagamentos') { $upd_pag = $u['sql']; }
}
$ok($upd_alunos !== '', 'deve existir UPDATE a sige_alunos');
$ok(strpos($upd_alunos, "`nome_completo` = '[apagado]'") !== false, 'nome_completo deve ser redigido com o marcador');
$ok(strpos($upd_alunos, "`data_nascimento` = '1900-01-01'") !== false, 'data_nascimento NOT NULL deve receber sentinela');
$ok(strpos($upd_alunos, '`consent_whatsapp` = NULL') !== false, 'consent_whatsapp anulavel deve ser anulado');
$ok(strpos($upd_alunos, '`numero_processo`') === false, 'numero_processo NUNCA deve ser tocado no UPDATE');
$ok(strpos($upd_alunos, '`mensalidade_base`') === false, 'coluna financeira NUNCA deve ser tocada no UPDATE');
$ok(strpos($upd_alunos, 'AND escola_id = 3') !== false, 'UPDATE de alunos deve isolar por escola_id');
$ok(strpos($upd_alunos, 'WHERE `id` = 7') !== false, 'UPDATE de alunos deve filtrar pelo aluno');

$ok($upd_pag !== '', 'deve existir UPDATE a sige_fin_pagamentos');
$ok(strpos($upd_pag, '`referencia_externa` = ') !== false, 'referencia_externa deve ser redigida');
$ok(strpos($upd_pag, '`valor`') === false, 'coluna financeira valor NUNCA deve ser tocada');
$ok(strpos($upd_pag, '`aluno_id` = ') === false || strpos($upd_pag, 'SET `aluno_id`') === false, 'aluno_id nao deve ser redigido (apenas usado no WHERE)');
$ok(strpos($upd_pag, 'AND escola_id = 3') !== false, 'UPDATE de pagamentos deve isolar por escola_id');

// 2b. Incr 3.2: novos campos identificaveis redigidos; datas de cobranca preservadas.
$ok(strpos($upd_alunos, "`foto` = '[apagado]'") !== false, 'Incr 3.2: foto deve ser redigida');
$ok(strpos($upd_alunos, "`doc_bi_url` = '[apagado]'") !== false, 'Incr 3.2: documento de identidade digitalizado deve ser redigido');
$ok(strpos($upd_alunos, "`contacto_emergencia_1` = '[apagado]'") !== false, 'Incr 3.2: contacto de emergencia deve ser redigido');
$ok(strpos($upd_alunos, "`autorizado_buscar_telemovel` = '[apagado]'") !== false, 'Incr 3.2: contacto da pessoa autorizada a buscar deve ser redigido');
$ok(strpos($upd_alunos, "`autorizado_buscar_documento` = '[apagado]'") !== false, 'Incr 3.2: documento da pessoa autorizada a buscar deve ser redigido');
$ok(strpos($upd_alunos, "`encarregado_observacoes` = '[apagado]'") !== false, 'Incr 3.2: notas do encarregado devem ser redigidas');

$upd_cobranca = '';
foreach ($wpdb->updates as $u) {
    if ($u['table'] === 'wp_sige_fin_contactos_cobranca') { $upd_cobranca = $u['sql']; }
}
$ok($upd_cobranca !== '', 'deve existir UPDATE a sige_fin_contactos_cobranca (tem notas a redigir)');
$ok(strpos($upd_cobranca, '`notas` = ') !== false, 'notas de cobranca devem ser redigidas');
$ok(strpos($upd_cobranca, '`data_contacto`') === false, 'Incr 3.2: data_contacto deve ser PRESERVADA (registo operacional), nunca redigida');
$ok(strpos($upd_cobranca, '`proximo_contacto`') === false, 'Incr 3.2: proximo_contacto deve ser PRESERVADA (registo operacional), nunca redigida');
$ok(strpos($upd_cobranca, '`valor_prometido`') === false, 'coluna financeira valor_prometido NUNCA deve ser tocada');
$ok(strpos($upd_cobranca, 'AND escola_id = 3') !== false, 'UPDATE de cobranca deve isolar por escola_id');

// 3. Fail-closed.
$ok(empty(sige_pii_apagamento_plano(7, 99)['ok']), 'plano deve recusar aluno fora da escola');
$ok(empty(sige_pii_apagamento_executar(7, 99)['ok']), 'execucao deve recusar aluno fora da escola');
$ok(empty(sige_pii_apagamento_plano(0, 3)['ok']), 'plano deve recusar aluno = 0');
$ok(empty(sige_pii_apagamento_executar(7, 0)['ok']), 'execucao deve recusar escola = 0');

// 4. Idempotencia: apos a anonimizacao, o aluno e detectado como anonimizado.
$ok(sige_pii_apagamento_esta_anonimizado(7, 3) === true, 'aluno deve ser detectado como anonimizado apos a execucao');
$res2 = sige_pii_apagamento_executar(7, 3);
$ok(!empty($res2['ok']), 'reexecucao deve continuar valida (idempotente)');
$ok(sige_pii_apagamento_esta_anonimizado(7, 3) === true, 'aluno mantem-se anonimizado apos reexecucao');

// 5. Seguranca de tipo do resolver (reforco do gate estatico).
$ok((sige_pii_apagamento_resolver(['coluna' => 'x', 'type' => 'varchar(40)', 'nullable' => true, 'maxlen' => 40])['accao']) === 'marcador', 'texto deve receber marcador');
$ok((sige_pii_apagamento_resolver(['coluna' => 'x', 'type' => 'date', 'nullable' => true, 'maxlen' => null])['accao']) === 'anular', 'data anulavel deve ser anulada');
$ok((sige_pii_apagamento_resolver(['coluna' => 'x', 'type' => 'date', 'nullable' => false, 'maxlen' => null])['accao']) === 'sentinela', 'data nao anulavel deve receber sentinela');
$ok((sige_pii_apagamento_resolver(['coluna' => 'x', 'type' => 'int(11)', 'nullable' => false, 'maxlen' => null])['accao']) === 'zero', 'numero nao anulavel deve receber zero');
$ok((sige_pii_apagamento_resolver(['coluna' => 'x', 'type' => "enum('a','b')", 'nullable' => false, 'maxlen' => null])['accao']) === 'manter', 'enum deve ser mantido');

if (!empty($falhas)) {
    echo "SMOKE APAGAMENTO FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE APAGAMENTO OK - plano exclui preservacao, execucao redige PII (nome=marcador, data=sentinela, consentimento=nulo) e preserva numero_processo e colunas financeiras, isola por escola_id, fail-closed nos quatro casos, idempotente, redaccao segura quanto ao tipo.\n";
exit(0);
