<?php
/**
 * SIGE SoftGenial - Abertura de Ano Lectivo
 *
 * v12.10.54 - Abertura Académica Segura
 * - Abertura oficial passa a usar o snapshot final criado no Encerramento Académico Seguro.
 * - Bloqueia abertura sem ano de origem encerrado, sem snapshots activos ou com destino já iniciado.
 * - Mantém reprovados na classe de origem e promove PROGRIDE/TRANSITA para a classe seguinte.
 * - Aplica matriz curricular da classe destino, em vez de copiar cegamente disciplinas da turma antiga.
 * - Executa a operação em transacção, com auditoria e registo estruturado de abertura.
 * - Intervenção de produto: solução completa, não remendo. Visual Produto PRO preservado.
 */

if (!defined('ABSPATH')) exit;

// Guard de acesso - Abertura (Director)
// [12.9.6] Guarda centralizada via matriz SIGE: a permissão atribuída ao perfil
// é a fonte de verdade. Mantém WP roles legadas como fallback enquanto o
// utilizador ainda não tem perfil SIGE atribuído.
if (!sige_page_guard(['academico.reabrir_ano'], ['sige_director'])) return;
$__sige_can_abrir_ano = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || !function_exists('sige_can') || sige_can('academico.reabrir_ano', ['view' => 'abertura']);

$_escola_perfil = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$_escola_nome = $_escola_perfil->nome_escola ?? get_bloginfo('name');
$_eid = sige_require_escola_id('abertura_ano');

global $wpdb;

/* ─── TABELAS ─────────────────────────────────────────────────────── */
$tTurmas      = $wpdb->prefix . 'sige_turmas';
$tAlunos      = $wpdb->prefix . 'sige_alunos';
$tMatriculas  = $wpdb->prefix . 'sige_matriculas';
$tNotas       = $wpdb->prefix . 'sige_notas';
$tDisciplinas = $wpdb->prefix . 'sige_disciplinas';
$tTurmaDisc   = $wpdb->prefix . 'sige_turma_disciplinas';
$tMatriz      = $wpdb->prefix . 'sige_matriz_curricular';
$tRegras      = $wpdb->prefix . 'sige_regras_academicas';

/* ─── HELPERS DE BASE ─────────────────────────────────────────────── */
if (!function_exists('sige_ab_ano_atual')) {
    function sige_ab_ano_atual() {
        if ( function_exists('sige_get_ano_lectivo_atual') )   return sige_get_ano_lectivo_atual();
        if ( function_exists('sige_fin_get_ano_letivo_master')) return sige_fin_get_ano_letivo_master();
        return (string) wp_date('Y');
    }
}
if (!function_exists('sige_ab_tbl')) {
    function sige_ab_tbl($nome) {
        global $wpdb;
        return $wpdb->prefix . $nome;
    }
}
if (!function_exists('sige_ab_table_exists')) {
    function sige_ab_table_exists($table) {
        global $wpdb;
        static $cache = [];
        $table = (string)$table;
        if (isset($cache[$table])) return (bool)$cache[$table];
        $cache[$table] = (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return (bool)$cache[$table];
    }
}
if (!function_exists('sige_ab_col_exists')) {
    function sige_ab_col_exists($table, $column) {
        global $wpdb;
        static $cache = [];
        $table = (string)$table;
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        $key = $table . '::' . $column;
        if (isset($cache[$key])) return (bool)$cache[$key];
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($safe_table === '' || $column === '') return false;
        $cache[$key] = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$safe_table}` LIKE %s", $column));
        return (bool)$cache[$key];
    }
}
if (!function_exists('sige_ab_icon')) {
    function sige_ab_icon($name) {
        return function_exists('sige_ui_icon') ? sige_ui_icon((string)$name) : '';
    }
}
if (!function_exists('sige_ab_audit')) {
    function sige_ab_audit($action, $data = []) {
        $payload = array_merge(['action' => $action, 'time' => current_time('mysql')], (array)$data);
        if (function_exists('sige_audit_log')) {
            sige_audit_log($action, $payload, 'academico');
        } elseif (function_exists('sige_obs_log')) {
            sige_obs_log($action, $payload);
        }
    }
}
if (!function_exists('sige_ab_wpdb_assert')) {
    function sige_ab_wpdb_assert($contexto) {
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            throw new Exception($contexto . ': ' . $wpdb->last_error);
        }
    }
}

/* ─── MIGRAÇÕES ADITIVAS E NÃO DESTRUTIVAS ───────────────────────── */
if (!function_exists('sige_ab_ensure_abertura_table')) {
    function sige_ab_ensure_abertura_table() {
        global $wpdb;
        $table = sige_ab_tbl('sige_ano_lectivo_aberturas');
        if (sige_ab_table_exists($table)) return $table;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL,
            ano_origem INT(4) NOT NULL,
            ano_destino INT(4) NOT NULL,
            status_operacao VARCHAR(24) NOT NULL DEFAULT 'concluida',
            total_turmas INT(11) NOT NULL DEFAULT 0,
            total_matriculas INT(11) NOT NULL DEFAULT 0,
            total_progride INT(11) NOT NULL DEFAULT 0,
            total_transita INT(11) NOT NULL DEFAULT 0,
            total_reprova INT(11) NOT NULL DEFAULT 0,
            total_concluintes INT(11) NOT NULL DEFAULT 0,
            total_bloqueados INT(11) NOT NULL DEFAULT 0,
            relatorio LONGTEXT DEFAULT NULL,
            hash_integridade VARCHAR(64) DEFAULT NULL,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            criado_em DATETIME DEFAULT NULL,
            finalizado_em DATETIME DEFAULT NULL,
            erro LONGTEXT DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sige_abertura (escola_id, ano_origem, ano_destino),
            KEY idx_sige_abertura_status (escola_id, ano_destino, status_operacao)
        ) {$cc};";
        dbDelta($sql);
        return $table;
    }
}
if (!function_exists('sige_ab_ensure_matricula_cols')) {
    function sige_ab_ensure_matricula_cols() {
        global $wpdb;
        $tM = sige_ab_tbl('sige_matriculas');
        $cols = [
            'abertura_operacao_id' => "ALTER TABLE {$tM} ADD COLUMN abertura_operacao_id BIGINT(20) UNSIGNED DEFAULT NULL",
            'abertura_snapshot_id' => "ALTER TABLE {$tM} ADD COLUMN abertura_snapshot_id BIGINT(20) UNSIGNED DEFAULT NULL",
            'turma_origem_id' => "ALTER TABLE {$tM} ADD COLUMN turma_origem_id BIGINT(20) UNSIGNED DEFAULT NULL",
            'classe_origem' => "ALTER TABLE {$tM} ADD COLUMN classe_origem VARCHAR(50) DEFAULT NULL",
            'classe_destino' => "ALTER TABLE {$tM} ADD COLUMN classe_destino VARCHAR(50) DEFAULT NULL",
            'situacao_origem' => "ALTER TABLE {$tM} ADD COLUMN situacao_origem VARCHAR(30) DEFAULT NULL",
        ];
        foreach ($cols as $col => $sql) {
            if (!sige_ab_col_exists($tM, $col)) {
                $wpdb->query($sql);
            }
        }
    }
}

/* ─── MAPA DE TRANSIÇÃO ACADÉMICA ──────────────────────────────────
   Mantém o SNE como referência e cobre também nomenclaturas usadas por
   escolas com pré-escolar. Se uma classe não estiver reconhecida, a abertura
   oficial bloqueia para evitar empurrar alunos para uma turma errada. */
if (!function_exists('sige_ab_classe_key')) {
    function sige_ab_classe_key($classe) {
        $c = trim((string)$classe);
        $c = str_replace(['º','ª'], ['', ''], $c);
        $c = remove_accents($c);
        $c = strtolower($c);
        $c = preg_replace('/\s+/', ' ', $c);
        return $c;
    }
}
if (!function_exists('sige_ab_next_classe_info')) {
    function sige_ab_next_classe_info($classe) {
        $original = trim((string)$classe);
        $key = sige_ab_classe_key($original);
        $map = [
            'bercario' => 'Creche',
            'creche' => '2º/3º Ano',
            '2/3 ano' => '4º Ano',
            '2o/3o ano' => '4º Ano',
            '2/3 anos' => '4º Ano',
            '4 ano' => 'Pré-primário',
            '4o ano' => 'Pré-primário',
            'pre primario' => '1ª',
            'pre-primario' => '1ª',
            'pre escolar' => '1ª',
            'pre-escolar' => '1ª',
            '1' => '2ª', '1a' => '2ª', '1 classe' => '2ª',
            '2' => '3ª', '2a' => '3ª', '2 classe' => '3ª',
            '3' => '4ª', '3a' => '4ª', '3 classe' => '4ª',
            '4' => '5ª', '4a' => '5ª', '4 classe' => '5ª',
            '5' => '6ª', '5a' => '6ª', '5 classe' => '6ª',
            '6' => '7ª', '6a' => '7ª', '6 classe' => '7ª',
            '7' => '8ª', '7a' => '8ª', '7 classe' => '8ª',
            '8' => '9ª', '8a' => '9ª', '8 classe' => '9ª',
            '9' => '10ª', '9a' => '10ª', '9 classe' => '10ª',
            '10' => '11ª', '10a' => '11ª', '10 classe' => '11ª',
            '11' => '12ª', '11a' => '12ª', '11 classe' => '12ª',
            '12' => null, '12a' => null, '12 classe' => null,
        ];
        if (array_key_exists($key, $map)) {
            return ['known' => true, 'terminal' => $map[$key] === null, 'next' => $map[$key]];
        }
        return ['known' => false, 'terminal' => false, 'next' => null];
    }
}
if (!function_exists('sige_ab_proxima_classe')) {
    function sige_ab_proxima_classe($classe) {
        $info = sige_ab_next_classe_info($classe);
        if (!empty($info['known']) && !empty($info['next'])) return $info['next'];
        if (!empty($info['terminal'])) return 'Concluído';
        return (string)$classe;
    }
}
if (!function_exists('sige_ab_bucket_situacao')) {
    function sige_ab_bucket_situacao($situacao) {
        $s = strtoupper(trim((string)$situacao));
        if (in_array($s, ['PROGRIDE','APROVADO','APROVADA'], true)) return 'progride';
        if (in_array($s, ['TRANSITA'], true)) return 'transita';
        if (in_array($s, ['REPROVA','REPROVADO','REPROVADA','NAO_TRANSITA','NÃO TRANSITA'], true)) return 'reprova';
        return 'sem_notas';
    }
}

/* ─── FONTE DA VERDADE: SNAPSHOT DO ENCERRAMENTO ─────────────────── */
if (!function_exists('sige_ab_get_turmas_do_ano')) {
    function sige_ab_get_turmas_do_ano($ano, $escola_id) {
        global $wpdb;
        $tT = sige_ab_tbl('sige_turmas');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tT} WHERE ano_lectivo = %d AND escola_id = %d ORDER BY classe, nome_turma, nome",
            (int)$ano, (int)$escola_id
        ));
    }
}
if (!function_exists('sige_ab_get_active_snapshots')) {
    function sige_ab_get_active_snapshots($ano, $escola_id) {
        global $wpdb;
        $table = sige_ab_tbl('sige_ano_lectivo_snapshots');
        if (!sige_ab_table_exists($table)) return [];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE escola_id = %d AND ano_lectivo = %d AND status_snapshot = 'activo'
             ORDER BY turma_nome, aluno_nome",
            (int)$escola_id, (int)$ano
        ));
    }
}
if (!function_exists('sige_ab_count_active_matriculas')) {
    function sige_ab_count_active_matriculas($ano, $escola_id) {
        global $wpdb;
        $tM = sige_ab_tbl('sige_matriculas');
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tM}
             WHERE ano_lectivo = %d AND escola_id = %d
               AND (status_matricula IS NULL OR status_matricula != 'cancelada')",
            (int)$ano, (int)$escola_id
        ));
    }
}
if (!function_exists('sige_ab_get_matriz_disciplinas')) {
    function sige_ab_get_matriz_disciplinas($classe, $escola_id) {
        global $wpdb;
        $tMatriz = sige_ab_tbl('sige_matriz_curricular');
        $tDis = sige_ab_tbl('sige_disciplinas');
        if (!sige_ab_table_exists($tMatriz)) return [];
        $classe = (string)$classe;
        $ids = [];

        // 1) Tentativa exacta - mantém performance para escolas bem configuradas.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT mc.disciplina_id
             FROM {$tMatriz} mc
             LEFT JOIN {$tDis} d ON d.id = mc.disciplina_id
             WHERE mc.escola_id = %d AND mc.classe = %s
               AND (d.id IS NULL OR d.activo = 1)
             ORDER BY COALESCE(mc.ordem_pauta, 999), mc.id",
            (int)$escola_id, $classe
        ));
        foreach ((array)$rows as $r) {
            $did = (int)($r->disciplina_id ?? 0);
            if ($did > 0 && !in_array($did, $ids, true)) $ids[] = $did;
        }
        if (!empty($ids)) return $ids;

        // 2) Fallback seguro por normalização - evita bloquear por variações como "2ª" vs "2ª Classe".
        $target_key = sige_ab_classe_key($classe);
        $target_key_alt = trim(str_replace(' classe', '', $target_key));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT mc.classe, mc.disciplina_id
             FROM {$tMatriz} mc
             LEFT JOIN {$tDis} d ON d.id = mc.disciplina_id
             WHERE mc.escola_id = %d
               AND (d.id IS NULL OR d.activo = 1)
             ORDER BY COALESCE(mc.ordem_pauta, 999), mc.id",
            (int)$escola_id
        ));
        foreach ((array)$rows as $r) {
            $row_key = sige_ab_classe_key($r->classe ?? '');
            $row_key_alt = trim(str_replace(' classe', '', $row_key));
            if ($row_key === $target_key || $row_key_alt === $target_key_alt) {
                $did = (int)($r->disciplina_id ?? 0);
                if ($did > 0 && !in_array($did, $ids, true)) $ids[] = $did;
            }
        }
        return $ids;
    }
}
if (!function_exists('sige_ab_existing_operation')) {
    function sige_ab_existing_operation($ano_origem, $ano_destino, $escola_id) {
        global $wpdb;
        $table = sige_ab_ensure_abertura_table();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE escola_id=%d AND ano_origem=%d AND ano_destino=%d LIMIT 1",
            (int)$escola_id, (int)$ano_origem, (int)$ano_destino
        ));
    }
}

if (!function_exists('sige_ab_build_opening_plan')) {
    function sige_ab_build_opening_plan($ano_origem, $ano_destino, $escola_id) {
        global $wpdb;
        $ano_origem = (int)$ano_origem;
        $ano_destino = (int)$ano_destino;
        $eid = (int)$escola_id;
        $tT = sige_ab_tbl('sige_turmas');
        $tM = sige_ab_tbl('sige_matriculas');

        $plan = [
            'can_open' => true,
            'messages' => [],
            'turmas' => [],
            'target_groups' => [],
            'snapshots' => [],
            'totals' => ['turmas' => 0, 'total_alunos' => 0, 'progride' => 0, 'transita' => 0, 'reprova' => 0, 'sem_notas' => 0, 'concluintes' => 0],
            'source_closed' => (bool)get_option("sige_ano_lectivo_encerrado_{$eid}_{$ano_origem}", 0),
            'dest_turmas' => 0,
            'dest_matriculas' => 0,
            'snapshot_count' => 0,
            'active_matriculas' => 0,
            'hash' => '',
        ];

        if ($ano_origem <= 0 || $ano_destino <= 0) $plan['messages'][] = 'Seleccione anos lectivos válidos.';
        if ($ano_destino <= $ano_origem) $plan['messages'][] = 'O ano de destino deve ser posterior ao ano de origem.';
        if (!$plan['source_closed']) $plan['messages'][] = 'O ano de origem ainda não está encerrado oficialmente. A abertura oficial só pode avançar depois do encerramento seguro.';

        $plan['dest_turmas'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tT} WHERE ano_lectivo=%d AND escola_id=%d",
            $ano_destino, $eid
        ));
        $plan['dest_matriculas'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tM} WHERE ano_lectivo=%d AND escola_id=%d",
            $ano_destino, $eid
        ));
        if ($plan['dest_turmas'] > 0 || $plan['dest_matriculas'] > 0) {
            $plan['messages'][] = 'O ano de destino já tem turmas ou matrículas. Abertura bloqueada para evitar duplicação ou estado parcial.';
        }

        $op = sige_ab_existing_operation($ano_origem, $ano_destino, $eid);
        if ($op && ($op->status_operacao ?? '') === 'concluida') {
            $plan['messages'][] = 'Já existe uma abertura oficial concluída para este par de anos.';
        }

        $turmas = sige_ab_get_turmas_do_ano($ano_origem, $eid);
        if (empty($turmas)) $plan['messages'][] = 'Não existem turmas no ano de origem.';
        $turma_map = [];
        foreach ((array)$turmas as $t) {
            $turma_map[(int)$t->id] = $t;
        }

        $snapshots = sige_ab_get_active_snapshots($ano_origem, $eid);
        $plan['snapshot_count'] = count((array)$snapshots);
        $plan['active_matriculas'] = sige_ab_count_active_matriculas($ano_origem, $eid);
        if ($plan['snapshot_count'] <= 0) {
            $plan['messages'][] = 'Não existe snapshot activo do encerramento para este ano. Refaça/valide o encerramento antes da abertura.';
        }
        if ($plan['active_matriculas'] > 0 && $plan['snapshot_count'] !== $plan['active_matriculas']) {
            $plan['messages'][] = 'O número de snapshots não coincide com as matrículas activas do ano encerrado. Abertura bloqueada para evitar omissões.';
        }

        $by_turma = [];
        $disciplinas_cache = [];
        foreach ((array)$snapshots as $snap) {
            $tid = (int)($snap->turma_id ?? 0);
            $turma = $turma_map[$tid] ?? null;
            if (!$turma) {
                $plan['messages'][] = 'Snapshot encontrado sem turma de origem válida.';
                continue;
            }
            $situacao_raw = strtoupper(trim((string)($snap->situacao ?? 'SEM_NOTAS')));
            $bucket = sige_ab_bucket_situacao($situacao_raw);
            $classe_origem = (string)($snap->classe ?: ($turma->classe ?? ''));
            $next_info = sige_ab_next_classe_info($classe_origem);
            $classe_destino = null;
            $tipo_destino = '';
            if ($bucket === 'reprova') {
                $classe_destino = $classe_origem;
                $tipo_destino = 'retencao';
            } elseif ($bucket === 'progride' || $bucket === 'transita') {
                if (empty($next_info['known'])) {
                    $plan['messages'][] = 'Classe não reconhecida para progressão: ' . $classe_origem . '. Configure a transição académica antes de abrir o ano.';
                } elseif (!empty($next_info['terminal'])) {
                    $tipo_destino = 'concluinte';
                    $classe_destino = null;
                    $plan['totals']['concluintes']++;
                } else {
                    $classe_destino = (string)$next_info['next'];
                    $tipo_destino = 'progressao';
                }
            } else {
                $plan['messages'][] = 'Existe aluno sem situação final válida no snapshot: ' . (string)($snap->aluno_nome ?? ('Aluno #' . (int)$snap->aluno_id));
            }

            if (!isset($by_turma[$tid])) {
                $nome_t = !empty($turma->nome_turma) ? $turma->nome_turma : ($turma->nome ?? ('Turma #' . $tid));
                $by_turma[$tid] = [
                    'id' => $tid,
                    'nome' => $nome_t,
                    'classe' => (string)($turma->classe ?? ''),
                    'proxima_classe' => sige_ab_proxima_classe($turma->classe ?? ''),
                    'turno' => $turma->turno ?? '',
                    'pf_mode' => true,
                    'ct' => ['progride'=>0,'transita'=>0,'reprova'=>0,'sem_notas'=>0,'total'=>0],
                    'alunos' => [],
                ];
            }
            $by_turma[$tid]['ct']['total']++;
            $by_turma[$tid]['ct'][$bucket]++;
            $by_turma[$tid]['alunos'][] = ['nome' => (string)($snap->aluno_nome ?? ''), 'situacao' => $situacao_raw];

            $plan['totals']['total_alunos']++;
            if (isset($plan['totals'][$bucket])) $plan['totals'][$bucket]++;

            $snap->__classe_destino = $classe_destino;
            $snap->__tipo_destino = $tipo_destino;
            $snap->__bucket = $bucket;
            $plan['snapshots'][] = $snap;

            if ($classe_destino !== null && $classe_destino !== '') {
                $gkey = $tid . '::' . $classe_destino;
                if (!isset($plan['target_groups'][$gkey])) {
                    $plan['target_groups'][$gkey] = [
                        'source_turma' => $turma,
                        'source_turma_id' => $tid,
                        'classe_destino' => $classe_destino,
                        'tipo_destino' => $tipo_destino,
                        'situacoes' => [],
                        'alunos' => 0,
                    ];
                }
                $plan['target_groups'][$gkey]['situacoes'][$bucket] = true;
                $plan['target_groups'][$gkey]['alunos']++;
                if (!isset($disciplinas_cache[$classe_destino])) {
                    $disciplinas_cache[$classe_destino] = sige_ab_get_matriz_disciplinas($classe_destino, $eid);
                    if (empty($disciplinas_cache[$classe_destino])) {
                        $plan['messages'][] = 'A matriz curricular da classe destino "' . $classe_destino . '" não tem disciplinas configuradas.';
                    }
                }
            }
        }

        $plan['turmas'] = array_values($by_turma);
        $plan['totals']['turmas'] = count((array)$plan['target_groups']);
        $plan['can_open'] = empty($plan['messages']);
        $plan['hash'] = hash('sha256', wp_json_encode([
            'escola_id' => $eid,
            'ano_origem' => $ano_origem,
            'ano_destino' => $ano_destino,
            'snapshot_count' => $plan['snapshot_count'],
            'targets' => array_keys($plan['target_groups']),
            'totals' => $plan['totals'],
        ]));
        return $plan;
    }
}

if (!function_exists('sige_ab_execute_opening')) {
    function sige_ab_execute_opening($ano_origem, $ano_destino, $escola_id) {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_ab_execute_opening')) { return ['ok' => false, 'message' => 'Contexto de escola invalido.']; }
        global $wpdb;
        $eid = (int)$escola_id;
        $ano_origem = (int)$ano_origem;
        $ano_destino = (int)$ano_destino;
        $plan = sige_ab_build_opening_plan($ano_origem, $ano_destino, $eid);
        if (empty($plan['can_open'])) {
            return ['ok' => false, 'message' => 'Abertura bloqueada com segurança. ' . implode(' ', array_slice((array)$plan['messages'], 0, 4)), 'plan' => $plan];
        }

        $tT = sige_ab_tbl('sige_turmas');
        $tM = sige_ab_tbl('sige_matriculas');
        $tA = sige_ab_tbl('sige_alunos');
        $tTD = sige_ab_tbl('sige_turma_disciplinas');
        $opTable = sige_ab_ensure_abertura_table();
        sige_ab_ensure_matricula_cols();

        $uid = get_current_user_id();
        $now = current_time('mysql');
        $log = [];
        $turma_dest_by_group = [];
        $stats = ['turmas' => 0, 'matriculas' => 0, 'progride' => 0, 'transita' => 0, 'reprova' => 0, 'concluintes' => 0, 'bloqueados' => 0];
        $op_id = 0;

        try {
            $wpdb->query('START TRANSACTION');

            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$opTable} WHERE escola_id=%d AND ano_origem=%d AND ano_destino=%d LIMIT 1",
                $eid, $ano_origem, $ano_destino
            ));
            if ($existing) {
                $op_id = (int)$existing->id;
                $wpdb->update($opTable, [
                    'status_operacao' => 'em_execucao',
                    'erro' => null,
                    'criado_por' => $uid,
                    'criado_em' => $now,
                    'finalizado_em' => null,
                ], ['id' => $op_id, 'escola_id' => $eid]);
                sige_ab_wpdb_assert('Actualizar operação de abertura');
            } else {
                $wpdb->insert($opTable, [
                    'escola_id' => $eid,
                    'ano_origem' => $ano_origem,
                    'ano_destino' => $ano_destino,
                    'status_operacao' => 'em_execucao',
                    'criado_por' => $uid,
                    'criado_em' => $now,
                    'hash_integridade' => $plan['hash'],
                ], ['%d','%d','%d','%s','%d','%s','%s']);
                sige_ab_wpdb_assert('Criar operação de abertura');
                $op_id = (int)$wpdb->insert_id;
            }

            foreach ((array)$plan['target_groups'] as $gkey => $group) {
                $src = $group['source_turma'];
                $nome_t = !empty($src->nome_turma) ? $src->nome_turma : ($src->nome ?? 'Turma');
                $classe_destino = (string)$group['classe_destino'];
                $wpdb->insert($tT, [
                    'escola_id'      => $eid,
                    'nome_turma'     => $nome_t,
                    'nome'           => $nome_t,
                    'sala_fisica'    => $src->sala_fisica ?? null,
                    'nivel_ensino'   => $src->nivel_ensino ?? '',
                    'classe'         => $classe_destino,
                    'turno'          => $src->turno ?? '',
                    'sala'           => $src->sala ?? '',
                    'ano_lectivo'    => $ano_destino,
                    'capacidade_max' => $src->capacidade_max ?? null,
                    'capacidade'     => $src->capacidade ?? null,
                    'status_turma'   => 'activa',
                ]);
                sige_ab_wpdb_assert('Criar turma destino');
                $nova_turma_id = (int)$wpdb->insert_id;
                $turma_dest_by_group[$gkey] = $nova_turma_id;
                $stats['turmas']++;
                $log[] = "Turma criada: {$nome_t} ({$src->classe} → {$classe_destino}) [ID {$nova_turma_id}]";

                $disciplinas = sige_ab_get_matriz_disciplinas($classe_destino, $eid);
                foreach ((array)$disciplinas as $did) {
                    $wpdb->insert($tTD, [
                        'escola_id' => $eid,
                        'turma_id' => $nova_turma_id,
                        'disciplina_id' => (int)$did,
                        'professor_id' => null,
                    ], ['%d','%d','%d','%d']);
                    sige_ab_wpdb_assert('Aplicar matriz curricular da turma destino');
                }
            }

            foreach ((array)$plan['snapshots'] as $snap) {
                $bucket = (string)$snap->__bucket;
                $situacao = strtoupper((string)($snap->situacao ?? ''));
                $classe_origem = (string)($snap->classe ?? '');
                $classe_destino = $snap->__classe_destino;
                if ($classe_destino === null || $classe_destino === '') {
                    if ($bucket === 'progride' || $bucket === 'transita') {
                        $stats['concluintes']++;
                        $log[] = "  ↳ {$snap->aluno_nome} [{$situacao}] → concluinte/sem matrícula automática no ano destino";
                        continue;
                    }
                    $stats['bloqueados']++;
                    continue;
                }
                $gkey = (int)$snap->turma_id . '::' . (string)$classe_destino;
                $nova_turma_id = (int)($turma_dest_by_group[$gkey] ?? 0);
                if ($nova_turma_id <= 0) {
                    throw new Exception('Turma destino não encontrada para aluno ' . (string)$snap->aluno_nome);
                }
                $ja = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tM} WHERE aluno_id=%d AND ano_lectivo=%d AND escola_id=%d",
                    (int)$snap->aluno_id, $ano_destino, $eid
                ));
                if ($ja > 0) {
                    throw new Exception('Aluno já matriculado no ano destino: ' . (string)$snap->aluno_nome);
                }
                $insert = [
                    'escola_id' => $eid,
                    'aluno_id' => (int)$snap->aluno_id,
                    'turma_id' => $nova_turma_id,
                    'ano_lectivo' => $ano_destino,
                    'status_matricula' => 'activa',
                    'data_matricula' => $now,
                    'data_criacao' => $now,
                    'abertura_operacao_id' => $op_id,
                    'abertura_snapshot_id' => (int)$snap->id,
                    'turma_origem_id' => (int)$snap->turma_id,
                    'classe_origem' => $classe_origem,
                    'classe_destino' => (string)$classe_destino,
                    'situacao_origem' => $situacao,
                ];
                $wpdb->insert($tM, $insert);
                sige_ab_wpdb_assert('Criar matrícula destino');
                $stats['matriculas']++;
                if (isset($stats[$bucket])) $stats[$bucket]++;
                if (sige_ab_col_exists($tA, 'classe_atual')) {
                    $wpdb->update($tA, ['classe_atual' => (string)$classe_destino], ['id' => (int)$snap->aluno_id, 'escola_id' => $eid]);
                    sige_ab_wpdb_assert('Actualizar classe actual do aluno');
                }
                $log[] = "  ↳ {$snap->aluno_nome} [{$situacao}] → {$classe_destino} / Turma ID {$nova_turma_id}";
            }

            $relatorio = [
                'versao' => '12.10.54',
                'fonte' => 'snapshot_encerramento_academico_seguro',
                'escola_id' => $eid,
                'ano_origem' => $ano_origem,
                'ano_destino' => $ano_destino,
                'stats' => $stats,
                'plan_hash' => $plan['hash'],
                'log' => $log,
            ];
            $hash = hash('sha256', wp_json_encode($relatorio));
            $wpdb->update($opTable, [
                'status_operacao' => 'concluida',
                'total_turmas' => $stats['turmas'],
                'total_matriculas' => $stats['matriculas'],
                'total_progride' => $stats['progride'],
                'total_transita' => $stats['transita'],
                'total_reprova' => $stats['reprova'],
                'total_concluintes' => $stats['concluintes'],
                'total_bloqueados' => $stats['bloqueados'],
                'relatorio' => wp_json_encode($relatorio),
                'hash_integridade' => $hash,
                'finalizado_em' => current_time('mysql'),
                'erro' => null,
            ], ['id' => $op_id, 'escola_id' => $eid]);
            sige_ab_wpdb_assert('Concluir operação de abertura');

            $wpdb->query('COMMIT');

            update_option("sige_abertura_log_{$eid}_{$ano_destino}", implode("\n", $log), false);
            update_option("sige_abertura_data_{$eid}_{$ano_destino}", current_time('mysql'), false);
            $u = wp_get_current_user();
            update_option("sige_abertura_por_{$eid}_{$ano_destino}", $u->display_name ?: $u->user_login, false);
            update_option("sige_abertura_operacao_id_{$eid}_{$ano_destino}", $op_id, false);
            update_option("sige_abertura_relatorio_{$eid}_{$ano_destino}", $relatorio, false);
            sige_ab_audit('abertura_ano_lectivo_segura_concluida', ['ano_origem' => $ano_origem, 'ano_destino' => $ano_destino, 'escola_id' => $eid, 'stats' => $stats]);
            return ['ok' => true, 'message' => "Abertura oficial do ano lectivo {$ano_destino} concluída com segurança. {$stats['turmas']} turma(s) criada(s), {$stats['matriculas']} matrícula(s) activas, {$stats['reprova']} retenção(ões) correctamente mantidas na classe de origem.", 'stats' => $stats, 'log' => $log, 'operation_id' => $op_id];
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $erro = $e->getMessage();
            $opTable = sige_ab_ensure_abertura_table();
            $wpdb->replace($opTable, [
                'id' => $op_id ?: null,
                'escola_id' => $eid,
                'ano_origem' => $ano_origem,
                'ano_destino' => $ano_destino,
                'status_operacao' => 'falhou',
                'criado_por' => $uid,
                'criado_em' => $now,
                'finalizado_em' => current_time('mysql'),
                'erro' => $erro,
                'hash_integridade' => $plan['hash'],
            ]);
            sige_ab_audit('abertura_ano_lectivo_segura_falhou', ['ano_origem' => $ano_origem, 'ano_destino' => $ano_destino, 'escola_id' => $eid, 'erro' => $erro]);
            return ['ok' => false, 'message' => 'Abertura cancelada e revertida com segurança. Motivo: ' . $erro, 'plan' => $plan];
        }
    }
}

/* ─── ANO DE ORIGEM / DESTINO ─────────────────────────────────────── */
$ano_atual   = sige_ab_ano_atual();
$anos_todos  = range(2020, (int)wp_date('Y') + 1);
// Detectar anos com turmas DESTA escola
$anos_com_turmas = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT ano_lectivo FROM {$tTurmas} WHERE escola_id = %d ORDER BY ano_lectivo DESC", $_eid));
$ano_origem  = isset($_GET['ano_origem'])  ? sanitize_text_field($_GET['ano_origem'])  : (string)((int)$ano_atual);
$ano_destino = isset($_GET['ano_destino']) ? sanitize_text_field($_GET['ano_destino']) : (string)((int)$ano_atual + 1);
$passo = isset($_GET['passo']) ? intval($_GET['passo']) : 1;

/* ─── FEEDBACK ────────────────────────────────────────────────────── */
$msg_feedback = '';
$msg_tipo     = 'info';
if ( isset($_GET['feedback']) ) {
    $msg_feedback = sanitize_text_field(urldecode($_GET['feedback']));
    $msg_tipo     = sanitize_text_field($_GET['ftipo'] ?? 'info');
}

/* ─── PROCESSAR EXECUÇÃO (Passo 3) ───────────────────────────────── */
if ( isset($_POST['sige_ab_action']) && $_POST['sige_ab_action'] === 'executar_abertura'
     && check_admin_referer('sige_ab_nonce_executar') && $__sige_can_abrir_ano ) {
    $ano_orig = sanitize_text_field($_POST['ano_origem']);
    $ano_dest = sanitize_text_field($_POST['ano_destino']);
    $exec = sige_ab_execute_opening($ano_orig, $ano_dest, $_eid);
    $msg_feedback = (string)($exec['message'] ?? 'Operação concluída.');
    $msg_tipo = !empty($exec['ok']) ? 'success' : 'error';
    $dest_passo = !empty($exec['ok']) ? 4 : 2;
    wp_redirect( admin_url("admin.php?page=sige-app&view=abertura&passo={$dest_passo}&ano_origem={$ano_orig}&ano_destino={$ano_dest}&feedback=" . urlencode($msg_feedback) . "&ftipo={$msg_tipo}") );
    exit;
}

/* ─── PLANO SEGURO PARA PRÉ-VISUALIZAÇÃO/CONFIRMAÇÃO ─────────────── */
$abertura_plan = sige_ab_build_opening_plan($ano_origem, $ano_destino, $_eid);
$preview_turmas = (array)($abertura_plan['turmas'] ?? []);
$preview_totais = (array)($abertura_plan['totals'] ?? ['turmas' => 0, 'progride' => 0, 'transita' => 0, 'reprova' => 0, 'sem_notas' => 0, 'total_alunos' => 0, 'concluintes' => 0]);
$preview_totais = array_merge(['turmas' => 0, 'progride' => 0, 'transita' => 0, 'reprova' => 0, 'sem_notas' => 0, 'total_alunos' => 0, 'concluintes' => 0], $preview_totais);

/* ─── LOG DA ABERTURA (Passo 4) ─────────────────────────────────── */
$log_texto = '';
$log_data  = '';
$log_por   = '';
$log_relatorio = [];
if ( $passo === 4 ) {
    $log_texto = get_option("sige_abertura_log_{$_eid}_{$ano_destino}", '');
    $log_data  = get_option("sige_abertura_data_{$_eid}_{$ano_destino}", '');
    $log_por   = get_option("sige_abertura_por_{$_eid}_{$ano_destino}", '');
    $log_relatorio = get_option("sige_abertura_relatorio_{$_eid}_{$ano_destino}", []);
}

/* ─── URL BASE ───────────────────────────────────────────────────── */
$base_url = admin_url('admin.php?page=sige-app&view=abertura');
$sige_ab_icon = function($name) {
    return function_exists('sige_ui_icon') ? sige_ui_icon((string)$name) : '';
};
?>
<style id="sige-abertura-produto-pro-v121054">
/* ============================================================================
   SIGE SoftGenial - Abertura de Ano Lectivo: Produto PRO v12.10.54
   Escopo: harmonização visual com Painel Principal / Dashboard V2 MJS-grade.
   Abertura oficial segura: usa snapshot do encerramento, transacção, matriz curricular e auditoria.
   ============================================================================ */
#sige-ab-wrap.sige-ab-page{
    --sgv2-purple:var(--sg-theme-primary,var(--color-brand-500));
    --sgv2-purple-dark:var(--color-brand-700);
    --sgv2-purple-soft:var(--sg-theme-soft,var(--color-brand-100));
    --sgv2-ink:var(--color-black);
    --sgv2-muted:var(--color-slate-700);
    --sgv2-line:var(--color-ink-100);
    --sgv2-bg:var(--color-ink-50);
    --sgv2-green:var(--color-success-700);
    --sgv2-red:var(--color-danger-500);
    --sgv2-amber:var(--color-warning-500);
    --sgv2-blue:var(--color-info-400);
    --sgv2-shadow:0 18px 45px rgba(34,34,64,.075);
    --sgv2-shadow-lg:0 24px 70px rgba(45,36,96,.10);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
    color:var(--sgv2-ink);
    max-width:100%;
    padding-bottom:28px;
}
#sige-ab-wrap.sige-ab-page *{box-sizing:border-box;}
@keyframes sigeAbFadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* HERO - mesmo padrão do Painel Principal */
.sige-ab-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--sgv2-purple-soft) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-xs);
    padding:32px 34px;
    margin:0 0 22px;
    display:grid;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr);
    gap:22px;
    align-items:center;
    animation:sigeAbFadeInUp .45s ease-out both;
}
.sige-ab-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.sige-ab-hero:after{display:none!important;content:none!important;}
.sige-ab-title-wrap,.sige-ab-hero-panel{position:relative;z-index:1;}
.sige-ab-kicker{display:inline-flex;align-items:center;gap:var(--space-2);margin:0 0 10px;padding:0;border:0;background:transparent;color:var(--sgv2-purple);font-size:12px;line-height:1.2;font-weight:700;letter-spacing:.11em;text-transform:uppercase;box-shadow:none;}
.sige-ab-kicker:before,.sige-ab-kicker:after{display:none!important;content:none!important;}
.sige-ab-kicker svg{width:18px!important;height:18px!important;color:currentColor!important;stroke:currentColor!important;fill:none!important;flex:0 0 auto;}
.sige-ab-title-wrap h1{margin:0;max-width:900px;color:var(--sgv2-ink);font-size:clamp(30px,2.45vw,42px);line-height:1.08;font-weight:700;letter-spacing:-.04em;}
#sige-ab-wrap .sige-ab-subtitle{max-width:760px;margin:var(--space-3) 0 0;color:var(--sgv2-muted);font-size:15px;line-height:1.65;font-weight:500;}
.sige-ab-hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:var(--space-3);margin-top:24px;}
.sige-ab-hero-panel{min-height:148px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));padding:22px;overflow:hidden;display:flex;flex-direction:column;justify-content:center;gap:var(--space-3);}
.sige-ab-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);}
.sige-ab-hero-chips{display:grid;grid-template-columns:1fr;gap:10px;position:relative;z-index:1;}
.sige-ab-chip{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center;gap:10px;min-height:46px;padding:10px 12px;border-radius:var(--radius-lg);background:rgba(255,255,255,.76);border:1px solid rgba(30,34,60,.07);box-shadow:var(--shadow-sm);}
.sige-ab-chip-icon{width:34px;height:34px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--color-white);color:var(--sgv2-purple);box-shadow:var(--shadow-sm);}
.sige-ab-chip-icon svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-ab-chip strong{display:block;color:var(--color-ink-500);font-size:12px;font-weight:700;line-height:1.25;white-space:normal;}
.sige-ab-chip small{display:block;margin-top:2px;color:var(--color-ink-400);font-size:var(--fs-xs);font-weight:600;line-height:1.25;}

/* Feedback */
.sige-ab-alert{display:flex;align-items:flex-start;gap:10px;padding:14px 18px;border-radius:var(--radius-lg);margin-bottom:18px;font-weight:600;font-size:var(--fs-sm);line-height:1.45;animation:sigeAbFadeInUp .35s ease-out both;box-shadow:var(--shadow-xs);border:1px solid transparent;}
.sige-ab-alert.success{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);}
.sige-ab-alert.error{background:var(--color-danger-50);color:var(--color-danger-700);border-color:var(--color-danger-200);}
.sige-ab-alert.warning{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-300);}
.sige-ab-alert.info{background:var(--color-info-50);color:var(--color-info-600);border-color:var(--color-info-200);}

/* Stepper */
.sige-ab-stepper{display:flex;align-items:center;margin-bottom:22px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(30,34,60,.08);padding:18px 20px;box-shadow:var(--shadow-xs);animation:sigeAbFadeInUp .45s ease-out .05s both;overflow:auto;}
.sige-ab-step{display:flex;align-items:center;gap:9px;min-width:max-content;}
.sige-ab-step .step-num{width:32px;height:32px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:var(--fs-sm);border:1px solid transparent;}
.sige-ab-step.done .step-num{background:var(--color-success-100);color:var(--color-success-800);border-color:var(--color-success-200);}
.sige-ab-step.active .step-num{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));color:var(--color-white);box-shadow:var(--shadow-sm);}
.sige-ab-step.pending .step-num{background:var(--color-slate-50);color:var(--color-ink-400);border-color:var(--color-slate-100);}
.sige-ab-step .step-lbl{font-size:12px;font-weight:700;white-space:nowrap;}
.sige-ab-step.done .step-lbl{color:var(--color-success-800);}
.sige-ab-step.active .step-lbl{color:var(--sgv2-purple);}
.sige-ab-step.pending .step-lbl{color:var(--color-ink-400);}
.sige-ab-sep{flex:1;height:2px;background:var(--color-slate-100);margin:0 10px;min-width:42px;max-width:72px;}
.sige-ab-sep.done{background:var(--color-success-200);}

/* Painéis */
.sige-ab-panel{background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-xs);padding:22px 24px;margin-bottom:22px;border:1px solid rgba(30,34,60,.08);animation:sigeAbFadeInUp .45s ease-out .09s both;}
.sige-ab-panel-head{display:flex;align-items:flex-start;gap:var(--space-3);margin:0 0 18px;}
.sige-ab-panel-icon{width:38px;height:38px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--color-brand-50);color:var(--sgv2-purple);flex:0 0 auto;}
.sige-ab-panel-icon svg{width:20px!important;height:20px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-ab-panel h2{font-size:17px;font-weight:700;line-height:1.2;margin:0;color:var(--color-ink-500);letter-spacing:-.01em;}
.sige-ab-panel-head p{margin:var(--space-1) 0 0;color:var(--color-slate-500);font-size:12px;font-weight:600;line-height:1.45;}
.sige-ab-form-row{display:flex;align-items:flex-end;gap:var(--space-4);flex-wrap:wrap;max-width:760px;}
.sige-ab-field label{display:block;margin:0 0 7px;color:var(--color-slate-800);font-size:var(--fs-sm);font-weight:700;}
.sige-ab-field small{font-size:var(--fs-xs);font-weight:600;color:var(--color-ink-400);}
.sige-ab-field select{width:172px;height:44px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);background:var(--color-white);color:var(--color-ink-500);font-size:var(--fs-base);font-weight:700;padding:0 14px;font-family:inherit;box-shadow:var(--shadow-sm);}
.sige-ab-field select:focus{outline:none;border-color:rgba(90,63,214,.55);box-shadow:var(--shadow-xs);}
.sige-ab-flow-arrow{width:42px;height:42px;border-radius:var(--radius-md);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--sgv2-purple);display:flex;align-items:center;justify-content:center;font-weight:700;margin-bottom:1px;}
.sige-ab-help{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px;align-items:flex-start;margin-top:16px;max-width:920px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600;background:var(--color-slate-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);padding:13px 14px;}
.sige-ab-help .icon{width:30px;height:30px;border-radius:var(--radius-md);background:var(--color-white);color:var(--sgv2-purple);display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-sm);}
.sige-ab-help svg{width:16px!important;height:16px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-ab-years-list{display:flex;gap:var(--space-2);flex-wrap:wrap;margin:var(--space-4) 0 0;padding-top:16px;border-top:1px solid var(--color-slate-100);color:var(--color-slate-600);font-size:12px;font-weight:600;}
.sige-ab-year-pill{display:inline-flex;align-items:center;gap:7px;margin-right:0;padding:8px 10px;border-radius:var(--radius-pill);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-slate-800);}
.sige-ab-year-pill.is-closed{background:var(--color-danger-50);border-color:var(--color-danger-200);color:var(--color-danger-700);}
.sige-ab-year-pill.is-open{background:var(--color-success-50);border-color:var(--color-success-200);color:var(--color-success-900);}

/* Botões */
.btn-ab-primary,.btn-ab-success,.btn-ab-secondary,.btn-ab-print{min-height:44px;display:inline-flex;align-items:center;justify-content:center;gap:9px;border-radius:var(--radius-md);padding:0 var(--space-5);font-size:var(--fs-sm);font-weight:700;text-decoration:none;border:1px solid transparent;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;box-shadow:var(--shadow-sm);cursor:pointer;font-family:inherit;line-height:1;}
.btn-ab-primary svg,.btn-ab-success svg,.btn-ab-secondary svg,.btn-ab-print svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.btn-ab-primary{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));color:var(--color-white);box-shadow:var(--shadow-md);}
.btn-ab-success{background:var(--color-success-500);color:var(--color-white);box-shadow:var(--shadow-md);}
.btn-ab-secondary,.btn-ab-print{background:var(--color-white);color:var(--color-ink-900);border-color:var(--color-ink-100);}
.btn-ab-primary:hover,.btn-ab-success:hover,.btn-ab-secondary:hover,.btn-ab-print:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}
.sige-ab-nav{display:flex;gap:var(--space-3);flex-wrap:wrap;margin:0 0 22px;}

/* KPIs */
.sige-ab-cards{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:var(--space-4);margin-bottom:22px;animation:sigeAbFadeInUp .45s ease-out .08s both;}
.sige-ab-card{position:relative;overflow:hidden;min-height:104px;background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-xs);padding:18px 20px;text-align:left;border:1px solid rgba(28,32,54,.08);transition:transform .18s ease,box-shadow .18s ease;}
.sige-ab-card:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--card-soft,var(--color-brand-50));}
.sige-ab-card:hover{transform:translateY(-1px);box-shadow:var(--shadow-lg);}
.sige-ab-card .num{position:relative;z-index:1;font-size:27px;line-height:1;font-weight:700;letter-spacing:-.03em;color:var(--card-color,var(--sgv2-purple));}
.sige-ab-card .lbl{position:relative;z-index:1;font-size:12px;color:var(--color-slate-600);margin-top:8px;font-weight:700;line-height:1.25;}
.c-turmas{--card-color:var(--color-brand-500);--card-soft:var(--color-brand-50);}.c-alunos{--card-color:var(--color-brand-500);--card-soft:var(--color-brand-50);}.c-progride{--card-color:var(--color-success-500);--card-soft:var(--color-success-100);}.c-transita{--card-color:var(--color-info-500);--card-soft:var(--color-info-50);}.c-reprova{--card-color:var(--color-danger-500);--card-soft:var(--color-danger-50);}.c-sem{--card-color:var(--color-warning-500);--card-soft:var(--color-warning-50);}

/* Tabela */
.sige-ab-table-wrap{width:100%;overflow:auto;border-radius:var(--radius-lg);border:1px solid var(--color-slate-100);}
table.sige-ab-tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:var(--fs-sm);background:var(--color-white);min-width:980px;}
table.sige-ab-tbl th{background:var(--color-slate-50);padding:12px 14px;text-align:left;font-weight:700;color:var(--color-slate-800);border-bottom:1px solid var(--color-slate-100);white-space:nowrap;font-size:12px;text-transform:uppercase;letter-spacing:.04em;}
table.sige-ab-tbl td{padding:13px 14px;border-bottom:1px solid var(--color-info-50);vertical-align:middle;color:var(--color-ink-800);}
table.sige-ab-tbl tr:last-child td{border-bottom:none;}
table.sige-ab-tbl tr:hover td{background:var(--color-white);}
.sige-badge{display:inline-flex;align-items:center;justify-content:center;min-height:26px;padding:4px 10px;border-radius:var(--radius-pill);font-size:var(--fs-xs);font-weight:700;border:1px solid transparent;line-height:1;text-transform:uppercase;letter-spacing:.025em;}
.badge-progride{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);}.badge-transita{background:var(--color-info-50);color:var(--color-info-600);border-color:var(--color-info-200);}.badge-reprova{background:var(--color-danger-50);color:var(--color-danger-700);border-color:var(--color-danger-200);}.badge-sem{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-300);}.badge-retido{background:var(--color-brand-50);color:var(--color-brand-700);border-color:var(--color-info-100);}
.sige-ab-alunos-toggle{cursor:pointer;color:var(--sgv2-purple);font-size:12px;text-decoration:none;font-weight:700;}
.sige-ab-alunos-toggle:hover{text-decoration:underline;}
.sige-ab-alunos-list{display:none;margin:10px 0 0;padding:10px;background:var(--color-slate-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-md);font-size:12px;}
.sige-ab-alunos-list.open{display:grid;gap:7px;}
.sige-ab-alunos-list li{padding:0;list-style:none;display:flex;align-items:center;gap:var(--space-2);}
.classe-arrow{color:var(--sgv2-purple);font-weight:700;}
.sige-ab-empty{color:var(--color-ink-400);text-align:center;padding:34px;font-weight:600;}

/* Avisos, confirmação e log */
.sige-ab-warn-box{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-3);align-items:start;background:var(--color-warning-50);border:1px solid var(--color-warning-300);border-radius:var(--radius-lg);padding:16px 18px;margin-bottom:18px;font-size:var(--fs-sm);line-height:1.5;color:var(--color-warning-900);box-shadow:var(--shadow-xs);}
.sige-ab-warn-box .icon,.sige-ab-confirm-icon{width:36px;height:36px;border-radius:var(--radius-md);background:var(--color-white);color:var(--color-warning-700);display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-sm);}
.sige-ab-warn-box svg,.sige-ab-confirm-icon svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-ab-confirm-box{display:grid;grid-template-columns:auto minmax(0,1fr);gap:14px;background:var(--color-danger-50);border:1px solid var(--color-danger-200);border-radius:var(--radius-xl);padding:18px 20px;margin-top:18px;box-shadow:var(--shadow-xs);}
.sige-ab-confirm-box h3{color:var(--color-danger-700);margin:0 0 10px;font-size:15px;font-weight:700;}
.sige-ab-confirm-box ul{margin:0 0 14px;padding-left:20px;font-size:var(--fs-sm);color:var(--color-slate-700);line-height:1.5;}
.sige-ab-confirm-box ul li{margin-bottom:4px;}
.sige-ab-confirm-box p{font-size:var(--fs-sm);color:var(--color-slate-700);margin:0;line-height:1.55;}
.sige-ab-log-label{font-size:var(--fs-sm);color:var(--color-slate-800);margin:0 0 10px;font-weight:700;display:flex;align-items:center;gap:var(--space-2);}
.sige-ab-log-label svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:var(--sgv2-purple)!important;fill:none!important;}
.sige-ab-log{background:var(--color-black);color:var(--color-success-100);font-family:'JetBrains Mono','SFMono-Regular',Consolas,monospace;font-size:12px;line-height:1.55;padding:var(--space-4);border-radius:var(--radius-lg);max-height:420px;overflow:auto;white-space:pre-wrap;box-shadow:var(--shadow-xs);border:1px solid rgba(255,255,255,.05);}
.sige-ab-footer{margin-top:20px;font-size:12px;color:var(--color-slate-400);text-align:center;font-weight:600;}

@media (max-width:1100px){.sige-ab-hero{grid-template-columns:1fr;}.sige-ab-cards{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media (max-width:768px){.sige-ab-hero{padding:var(--space-6) var(--space-5);border-radius:var(--radius-xl);}.sige-ab-title-wrap h1{font-size:28px;}.sige-ab-cards{grid-template-columns:repeat(2,minmax(0,1fr));}.sige-ab-stepper{align-items:flex-start;}.sige-ab-sep{min-width:28px;}.sige-ab-form-row{align-items:stretch;}.sige-ab-field select{width:100%;}.sige-ab-flow-arrow{margin:0;}.btn-ab-primary,.btn-ab-success,.btn-ab-secondary,.btn-ab-print{width:100%;}}
@media print{#adminmenuwrap,#wpadminbar,#wpfooter,.no-print{display:none!important}.sige-ab-log{max-height:none}.sige-ab-hero,.sige-ab-stepper,.sige-ab-panel,.sige-ab-card{box-shadow:none!important}}
</style>
<div id="sige-ab-wrap" class="wrap sige-ab-page">
    <section class="sige-ab-hero" aria-label="Abertura de Ano Lectivo">
        <div class="sige-ab-title-wrap">
            <div class="sige-ab-kicker"><?php echo $sige_ab_icon('unlock'); ?><span>Ano Lectivo</span></div>
            <h1>Abertura de Ano Lectivo</h1>
            <p class="sige-ab-subtitle">Abra oficialmente o novo ano lectivo com base no encerramento seguro, usando snapshots finais, matriz curricular e auditoria da operação.</p>
        </div>
        <aside class="sige-ab-hero-panel" aria-label="Resumo da abertura">
            <div class="sige-ab-hero-chips">
                <div class="sige-ab-chip"><span class="sige-ab-chip-icon"><?php echo $sige_ab_icon('school'); ?></span><span><strong><?php echo esc_html($_escola_nome); ?></strong><small>Escola activa nesta instalação</small></span></div>
                <div class="sige-ab-chip"><span class="sige-ab-chip-icon"><?php echo $sige_ab_icon('calendar'); ?></span><span><strong><?php echo esc_html($ano_origem); ?> → <?php echo esc_html($ano_destino); ?></strong><small>Abertura oficial baseada no snapshot do encerramento</small></span></div>
                <div class="sige-ab-chip"><span class="sige-ab-chip-icon"><?php echo $sige_ab_icon('activity'); ?></span><span><strong><?php echo esc_html(wp_date('d/m/Y H:i')); ?></strong><small>Hora de referência - Moçambique</small></span></div>
            </div>
        </aside>
    </section>
    <?php if ($msg_feedback): ?>
    <div class="sige-ab-alert <?= esc_attr($msg_tipo) ?>"><?= esc_html($msg_feedback) ?></div>
    <?php endif; ?>
    <!-- ── STEPPER ── -->
    <div class="sige-ab-stepper">
        <?php
        $passos = ['Seleccionar Anos','Pré-visualizar','Confirmar','Concluído'];
        foreach ($passos as $i => $lbl):
            $n = $i + 1;
            $cls = $passo > $n ? 'done' : ($passo === $n ? 'active' : 'pending');
            $icon = $passo > $n ? '✓' : $n;
        ?>
            <div class="sige-ab-step <?= $cls ?>">
                <div class="step-num"><?= $icon ?></div>
                <div class="step-lbl"><?= $lbl ?></div>
            </div>
            <?php if ($n < count($passos)): ?>
                <div class="sige-ab-sep <?= $passo > $n ? 'done' : '' ?>"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php /* ════════════════════════════════════════════════
           PASSO 1 - Seleccionar anos
           ════════════════════════════════════════════════ */ ?>
    <?php if ($passo === 1): ?>
    <div class="sige-ab-panel">
        <div class="sige-ab-panel-head"><span class="sige-ab-panel-icon"><?php echo $sige_ab_icon('calendar'); ?></span><div><h2>Seleccione os anos de origem e destino</h2><p>Escolha o ano oficialmente encerrado e o novo ano que será aberto com segurança.</p></div></div>
        <?php
        $enc = $anos_com_turmas;
        // Avisos
        $origem_enc = (bool) get_option("sige_ano_lectivo_encerrado_{$_eid}_{$ano_origem}", 0);
        $dest_existe = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tTurmas} WHERE ano_lectivo = %d AND escola_id = %d", (int)$ano_destino, $_eid
        ));
        ?>
        <?php if (!$origem_enc && !empty($anos_com_turmas)): ?>
        <div class="sige-ab-alert warning">
            O ano de origem ainda não está encerrado oficialmente. A abertura oficial ficará bloqueada até existir encerramento seguro com snapshot final activo.
        </div>
        <?php endif; ?>
        <?php if ($dest_existe > 0): ?>
        <div class="sige-ab-alert warning">
            O ano destino <strong><?= esc_html($ano_destino) ?></strong> já tem <strong><?= $dest_existe ?></strong> turma(s) registada(s). Executar a abertura será bloqueado para evitar duplicação.
        </div>
        <?php endif; ?>
        <form method="get" action="">
            <input type="hidden" name="page" value="sige-app">
            <input type="hidden" name="view" value="abertura">
            <input type="hidden" name="passo" value="2">
            <div class="sige-ab-form-row">
                <div class="sige-ab-field">
                    <label for="ab_origem">Ano de Origem <small>(preferencialmente encerrado)</small></label>
                    <select name="ano_origem" id="ab_origem">
                        <?php foreach ( array_reverse($anos_com_turmas) as $a ): ?>
                            <option value="<?= esc_attr($a) ?>" <?= selected($a, $ano_origem, false) ?>>
                                <?= esc_html($a) ?><?= get_option("sige_ano_lectivo_encerrado_{$_eid}_{$a}") ? ' - encerrado' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sige-ab-flow-arrow">→</div>
                <div class="sige-ab-field">
                    <label for="ab_destino">Ano de Destino <small>(novo)</small></label>
                    <select name="ano_destino" id="ab_destino">
                        <?php foreach ( array_reverse($anos_todos) as $a ): ?>
                            <option value="<?= esc_attr($a) ?>" <?= selected($a, $ano_destino, false) ?>>
                                <?= esc_html($a) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-ab-primary"><?php echo $sige_ab_icon('file'); ?> Pré-visualizar</button>
                </div>
            </div>
            <div class="sige-ab-help"><span class="icon"><?php echo $sige_ab_icon('shield'); ?></span><span>O sistema só executa a abertura oficial se o ano de origem estiver encerrado, se houver snapshot final activo, se o destino estiver limpo e se a matriz curricular das classes destino estiver configurada.</span></div>
        </form>
        <?php if (!empty($anos_com_turmas)): ?>
        <div class="sige-ab-years-list">
            <strong>Anos com turmas registadas:</strong>
            <?php foreach ($anos_com_turmas as $a):
                $is_closed = (bool) get_option("sige_ano_lectivo_encerrado_{$_eid}_{$a}");
            ?>
                <span class="sige-ab-year-pill <?= $is_closed ? 'is-closed' : 'is-open' ?>"><?= esc_html($a) ?> · <?= $is_closed ? 'Encerrado' : 'Aberto' ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php /* ════════════════════════════════════════════════
           PASSO 2 - Pré-visualização
           ════════════════════════════════════════════════ */ ?>
    <?php elseif ($passo === 2): ?>
    <?php if (empty($abertura_plan['can_open'])): ?>
    <div class="sige-ab-alert error">
        <strong>Abertura oficial bloqueada:</strong>&nbsp;<?= esc_html(implode(' ', array_slice((array)$abertura_plan['messages'], 0, 5))) ?>
    </div>
    <?php else: ?>
    <div class="sige-ab-alert success">
        <strong>Checklist de segurança aprovado:</strong>&nbsp;ano encerrado, snapshot activo, destino limpo, matriz curricular configurada e operação pronta para confirmação.
    </div>
    <?php endif; ?>
    <div class="sige-ab-panel">
        <div class="sige-ab-panel-head"><span class="sige-ab-panel-icon"><?php echo $sige_ab_icon('shield'); ?></span><div><h2>Checklist de Abertura Académica Segura</h2><p>O sistema valida a abertura antes de permitir a criação oficial de turmas e matrículas.</p></div></div>
        <div class="sige-ab-help"><span class="icon"><?php echo $sige_ab_icon('check'); ?></span><span><strong>Ano de origem encerrado:</strong> <?= !empty($abertura_plan['source_closed']) ? 'Sim' : 'Não' ?> · <strong>Snapshots activos:</strong> <?= (int)($abertura_plan['snapshot_count'] ?? 0) ?> · <strong>Matrículas activas no ano origem:</strong> <?= (int)($abertura_plan['active_matriculas'] ?? 0) ?> · <strong>Turmas no destino:</strong> <?= (int)($abertura_plan['dest_turmas'] ?? 0) ?> · <strong>Matrículas no destino:</strong> <?= (int)($abertura_plan['dest_matriculas'] ?? 0) ?></span></div>
        <?php if (!empty($abertura_plan['messages'])): ?>
        <ul style="margin:14px 0 0 20px;color:var(--color-danger-800);font-size:13px;font-weight:600;line-height:1.65;">
            <?php foreach (array_slice((array)$abertura_plan['messages'], 0, 10) as $m): ?>
                <li><?= esc_html($m) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <!-- Cards resumo -->
    <div class="sige-ab-cards">
        <div class="sige-ab-card c-turmas">
            <div class="num"><?= $preview_totais['turmas'] ?></div>
            <div class="lbl">Turmas</div>
        </div>
        <div class="sige-ab-card c-alunos">
            <div class="num"><?= $preview_totais['total_alunos'] ?></div>
            <div class="lbl">Total Alunos</div>
        </div>
        <div class="sige-ab-card c-progride">
            <div class="num"><?= $preview_totais['progride'] ?></div>
            <div class="lbl">Progride</div>
        </div>
        <div class="sige-ab-card c-transita">
            <div class="num"><?= $preview_totais['transita'] ?></div>
            <div class="lbl">Transita</div>
        </div>
        <div class="sige-ab-card c-reprova">
            <div class="num"><?= $preview_totais['reprova'] ?></div>
            <div class="lbl">Reprova</div>
        </div>
        <div class="sige-ab-card c-sem">
            <div class="num"><?= $preview_totais['sem_notas'] ?></div>
            <div class="lbl">Sem Situação</div>
        </div>
    </div>
    <?php if ($preview_totais['sem_notas'] > 0): ?>
    <div class="sige-ab-warn-box">
        <span class="icon"><?php echo $sige_ab_icon('shield'); ?></span>
        <span><strong><?= $preview_totais['sem_notas'] ?> aluno(s) sem situação final</strong>. A abertura oficial fica bloqueada até o encerramento produzir situação final válida para todos os alunos.</span>
    </div>
    <?php endif; ?>
    <div class="sige-ab-panel">
        <div class="sige-ab-panel-head"><span class="sige-ab-panel-icon"><?php echo $sige_ab_icon('clipboard'); ?></span><div><h2>Pré-visualização: <?= esc_html($ano_origem) ?> <span class="classe-arrow">→</span> <?= esc_html($ano_destino) ?></h2><p>Confirme as turmas destino, situações congeladas pelo encerramento e matrículas que serão criadas.</p></div></div>
        <?php if (empty($preview_turmas)): ?>
            <p class="sige-ab-empty">
                Nenhuma turma encontrada para o ano de origem <strong><?= esc_html($ano_origem) ?></strong>.
            </p>
        <?php else: ?>
        <div class="sige-ab-table-wrap"><table class="sige-ab-tbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Turma</th>
                    <th>Classe Origem</th>
                    <th>Classe Destino</th>
                    <th>Turno</th>
                    <th class="sige-u-tac">Total</th>
                    <th class="sige-u-tac">Progride</th>
                    <th class="sige-u-tac">Transita</th>
                    <th class="sige-u-tac">Reprova</th>
                    <th class="sige-u-tac">Sem Sit.</th>
                    <th>Pauta Final</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($preview_turmas as $i => $pt): $ct = $pt['ct']; ?>
                <tr>
                    <td style="color:var(--color-slate-400);font-size:.8rem;"><?= $i+1 ?></td>
                    <td>
                        <strong><?= esc_html($pt['nome']) ?></strong>
                        <?php if (!empty($pt['alunos'])): ?>
                        <br>
                        <span class="sige-ab-alunos-toggle" data-sige-act="sigeAlternarClasseProximo">
                            ver <?= count($pt['alunos']) ?> aluno(s) ▾
                        </span>
                        <ul class="sige-ab-alunos-list">
                            <?php foreach ($pt['alunos'] as $al):
                                $bclass = strtolower($al['situacao']);
                                $bclass = $bclass === 'sem_notas' ? 'sem' : $bclass;
                            ?>
                            <li>
                                <span class="sige-badge badge-<?= $bclass ?>"><?= esc_html($al['situacao']) ?></span>
                                <?= esc_html($al['nome']) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </td>
                    <td><?= esc_html($pt['classe']) ?></td>
                    <td><strong class="classe-arrow"><?= esc_html($pt['proxima_classe']) ?></strong></td>
                    <td><?= esc_html($pt['turno'] ?: '-') ?></td>
                    <td class="sige-u-tac sige-u-fw7"><?= $ct['total'] ?></td>
                    <td class="sige-u-tac">
                        <?php if ($ct['progride']>0): ?><span class="sige-badge badge-progride"><?= $ct['progride'] ?></span>
                        <?php else: ?><span style="color:var(--color-slate-300);">0</span><?php endif; ?>
                    </td>
                    <td class="sige-u-tac">
                        <?php if ($ct['transita']>0): ?><span class="sige-badge badge-transita"><?= $ct['transita'] ?></span>
                        <?php else: ?><span style="color:var(--color-slate-300);">0</span><?php endif; ?>
                    </td>
                    <td class="sige-u-tac">
                        <?php if ($ct['reprova']>0): ?><span class="sige-badge badge-reprova"><?= $ct['reprova'] ?></span>
                        <?php else: ?><span style="color:var(--color-slate-300);">0</span><?php endif; ?>
                    </td>
                    <td class="sige-u-tac">
                        <?php if ($ct['sem_notas']>0): ?><span class="sige-badge badge-sem"><?= $ct['sem_notas'] ?></span>
                        <?php else: ?><span style="color:var(--color-slate-300);">0</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="sige-badge <?= $pt['pf_mode'] ? 'badge-progride' : 'badge-sem' ?>">
                            <?= $pt['pf_mode'] ? 'Activa' : 'Inactiva' ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <!-- Navegação -->
    <div class="sige-ab-nav no-print">
        <a href="<?= $base_url ?>&passo=1&ano_origem=<?= urlencode($ano_origem) ?>&ano_destino=<?= urlencode($ano_destino) ?>"
           class="btn-ab-secondary">Voltar</a>
        <?php if (!empty($abertura_plan['can_open'])): ?>
        <a href="<?= $base_url ?>&passo=3&ano_origem=<?= urlencode($ano_origem) ?>&ano_destino=<?= urlencode($ano_destino) ?>"
           class="btn-ab-primary">Confirmar e Prosseguir</a>
        <?php else: ?>
        <span class="btn-ab-secondary" style="opacity:.62;cursor:not-allowed;">Confirmar bloqueado</span>
        <?php endif; ?>
    </div>
    <?php /* ════════════════════════════════════════════════
           PASSO 3 - Confirmação
           ════════════════════════════════════════════════ */ ?>
    <?php elseif ($passo === 3): ?>
    <div class="sige-ab-panel">
        <div class="sige-ab-panel-head"><span class="sige-ab-panel-icon"><?php echo $sige_ab_icon('shield'); ?></span><div><h2>Confirmar Abertura do Ano Lectivo <?= esc_html($ano_destino) ?></h2><p>Revise a operação oficial antes da criação transaccional das turmas e matrículas do novo ano.</p></div></div>
        <p>Está prestes a executar a abertura oficial do ano lectivo. Esta operação irá:</p>
        <div class="sige-ab-confirm-box">
            <span class="sige-ab-confirm-icon"><?php echo $sige_ab_icon('shield'); ?></span>
            <div>
            <h3>Acção crítica de abertura</h3>
            <ul>
                <li>Criar <strong><?= $preview_totais['turmas'] ?> turma(s)</strong> de destino para <strong><?= esc_html($ano_destino) ?></strong>, respeitando progressão e retenção</li>
                <li>Aplicar a <strong>matriz curricular oficial</strong> de cada classe destino</li>
                <li>Matricular <strong><?= $preview_totais['progride'] ?> aluno(s) PROGRIDE</strong> na classe seguinte</li>
                <li>Matricular <strong><?= $preview_totais['transita'] ?> aluno(s) TRANSITA</strong> na classe seguinte quando aplicável</li>
                <li>Manter <strong><?= $preview_totais['reprova'] ?> aluno(s) REPROVA</strong> na mesma classe, com matrícula activa no novo ano</li>
                <?php if (!empty($preview_totais['concluintes'])): ?>
                <li>Registar <strong><?= $preview_totais['concluintes'] ?> concluinte(s)</strong> sem matrícula automática no ano destino</li>
                <?php endif; ?>
                <li>Registar operação estruturada, auditoria, hash de integridade, data e utilizador responsável</li>
            </ul>
            <p><strong>Não apaga</strong> dados anteriores. As notas, pautas, boletins e matrículas de <?= esc_html($ano_origem) ?> ficam intactas. Se qualquer validação falhar, a transacção é revertida automaticamente.</p>
            </div>
        </div>
        <?php if (empty($abertura_plan['can_open'])): ?>
        <div class="sige-ab-alert error" style="margin-top:18px;">
            <strong>Execução bloqueada:</strong>&nbsp;<?= esc_html(implode(' ', array_slice((array)$abertura_plan['messages'], 0, 5))) ?>
        </div>
        <?php endif; ?>
        <form method="post" style="margin-top:20px;">
            <?php wp_nonce_field('sige_ab_nonce_executar'); ?>
            <input type="hidden" name="sige_ab_action" value="executar_abertura">
            <input type="hidden" name="ano_origem"  value="<?= esc_attr($ano_origem) ?>">
            <input type="hidden" name="ano_destino" value="<?= esc_attr($ano_destino) ?>">
            <div class="sige-ab-nav">
                <a href="<?= $base_url ?>&passo=2&ano_origem=<?= urlencode($ano_origem) ?>&ano_destino=<?= urlencode($ano_destino) ?>"
                   class="btn-ab-secondary no-print">Voltar</a>
                <?php if (!empty($abertura_plan['can_open'])): ?>
                <button type="submit" class="btn-ab-success"
                    data-sige-titulo="Abertura oficial de <?= esc_attr($ano_destino) ?>" data-sige-confirm="O ano lectivo <?= esc_attr($ano_destino) ?> será aberto oficialmente, com snapshot do encerramento anterior, matriz curricular e transacção segura." data-sige-confirmar="Abrir o ano">
                    <?php echo $sige_ab_icon('rocket'); ?> Executar Abertura Oficial de <?= esc_html($ano_destino) ?>
                </button>
                <?php else: ?>
                <span class="btn-ab-secondary" style="opacity:.62;cursor:not-allowed;">Execução bloqueada</span>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php /* ════════════════════════════════════════════════
           PASSO 4 - Concluído
           ════════════════════════════════════════════════ */ ?>
    <?php elseif ($passo === 4): ?>
    <div class="sige-ab-panel">
        <div class="sige-ab-panel-head"><span class="sige-ab-panel-icon"><?php echo $sige_ab_icon('check'); ?></span><div><h2>Abertura Concluída</h2><p>O sistema registou a operação e guardou o histórico da abertura.</p></div></div>
        <?php if ($log_data): ?>
        <p style="font-size:13px;color:var(--color-slate-500);margin-bottom:20px;font-weight:600;">
            Executado em <strong><?= esc_html(wp_date('d/m/Y \à\s H:i', strtotime($log_data))) ?></strong>
            por <strong><?= esc_html($log_por) ?></strong>
        </p>
        <?php endif; ?>
        <div class="sige-ab-nav no-print" style="margin-bottom:24px;">
            <a href="<?= admin_url('admin.php?page=sige-app&view=encerramento&ano_enc='.urlencode($ano_destino)) ?>"
               class="btn-ab-primary">Ver Encerramento de <?= esc_html($ano_destino) ?></a>
            <a href="<?= $base_url ?>&passo=1" class="btn-ab-secondary">Nova Abertura</a>
            <button class="btn-ab-print no-print" data-sige-act="sigeImprimirPagina" data-sige-noargs><?php echo $sige_ab_icon('file'); ?> Imprimir Log</button>
        </div>
        <?php if ($log_texto): ?>
        <p class="sige-ab-log-label"><?php echo $sige_ab_icon('clipboard'); ?> Log de operações</p>
        <div class="sige-ab-log"><?= esc_html($log_texto) ?></div>
        <?php endif; ?>
        <div class="sige-ab-alert info" style="margin-top:20px;">
            <strong>Próximos passos recomendados:</strong> valide as turmas criadas, confirme a matriz curricular do novo ano, reveja a lista de alunos retidos/concluintes e só depois active o novo ano como ano corrente, se essa for a decisão da escola.
        </div>
    </div>
    <?php endif; /* fim switch passo */ ?>
    <div class="sige-ab-footer">
        SIGE SoftGenial · <?php echo esc_html($_escola_nome); ?> ·
        Módulo de Abertura de Ano Lectivo
    </div>
</div><!-- /#sige-ab-wrap -->
<?php
/*
 * ════════════════════════════════════════════════════════════════════
 * INSTALAÇÃO
 * ════════════════════════════════════════════════════════════════════
 *
 * 1. Copiar para: wp-content/plugins/sige-softgenial/admin/abertura-view.php
 *
 * 2. Em sige-softgenial.php, no routing (switch $view):
 *    case 'abertura':
 *        include SIGE_PATH . 'admin/abertura-view.php';
 *        break;
 *
 * 3. Aceder via: admin.php?page=sige-app&view=abertura
 * ════════════════════════════════════════════════════════════════════
 */
?>
