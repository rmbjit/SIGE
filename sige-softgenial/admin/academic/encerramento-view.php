<?php
/**
 * SIGE SoftGenial - Encerramento de Ano Lectivo
 *
 * v2.2 - Maio 2026
 * - BUG FIX: table name corrigido em queries de encerramento
 * - Multi-tenant: escola_id adicionado a queries de turmas/matrículas
 * - Removido DateTime wrapper → wp_date()
 * - Funções helper guardadas
 * - v12.10.50: harmonização visual Produto PRO com Painel Principal
 * - v12.10.52: encerramento académico seguro com checklist, snapshot final, auditoria e reabertura real
 *
 * Funcionalidades:
 *  - Cards de resumo: Total / Progride / Transita / Reprova / Sem Notas / Taxa
 *  - Tabela por turma com barra de progresso e toggle Pauta Final
 *  - Encerrar Ano (bloqueia todas as turmas + regista data/utilizador)
 *  - Reabrir Ano (apenas Administrador)
 *  - Selector de ano histórico + relatório imprimível
 */

if (!defined('ABSPATH')) exit;

// Guard de acesso - Encerramento (Director)
$__sige_is_admin = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
$__sige_can_ver_academico = $__sige_is_admin || !function_exists('sige_can') || sige_can('academico.ver', ['view' => 'encerramento']);
$__sige_can_fechar_ano = $__sige_is_admin || !function_exists('sige_can') || sige_can('academico.fechar_ano', ['view' => 'encerramento']);
// [12.9.6] Guarda centralizada - matriz SIGE manda; WP caps fallback.
// Mantém flags internas ($__sige_can_*) usadas pelo resto do ficheiro para
// gating fino de acções dentro da página.
if (!sige_page_guard(
    ['academico.fechar_ano','academico.ver'],
    ['sige_director']
)) return;

// Carregar perfil da escola
$_escola_perfil = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$_escola_nome = $_escola_perfil->nome_escola ?? get_bloginfo('name');
$_eid = sige_require_escola_id('encerramento');

global $wpdb;
/* ─── HELPERS ───────────────────────────────────────────────────── */
if (!function_exists('sige_enc_ano_atual')) {
    function sige_enc_ano_atual() {
        if ( function_exists('sige_get_ano_lectivo_atual') ) return sige_get_ano_lectivo_atual();
        if ( function_exists('sige_fin_get_ano_letivo_master') ) return sige_fin_get_ano_letivo_master();
        return (string) wp_date('Y');
    }
}
if (!function_exists('sige_enc_tbl')) {
    function sige_enc_tbl( $nome ) {
        global $wpdb;
        return $wpdb->prefix . $nome;
    }
}

if (!function_exists('sige_enc_icon')) {
    function sige_enc_icon( $name ) {
        return function_exists('sige_ui_icon') ? sige_ui_icon((string)$name) : '';
    }
}


if (!function_exists('sige_enc_table_exists')) {
    function sige_enc_table_exists($table) {
        global $wpdb;
        static $cache = [];
        $table = (string)$table;
        if (isset($cache[$table])) return (bool)$cache[$table];
        $cache[$table] = (bool)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return (bool)$cache[$table];
    }
}

if (!function_exists('sige_enc_col_exists')) {
    function sige_enc_col_exists($table, $column) {
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

if (!function_exists('sige_enc_set_pauta_final_turmas')) {
    function sige_enc_set_pauta_final_turmas($ano, $escola_id, $enabled) {
        global $wpdb;
        $ano = (int)$ano;
        $eid = (int)$escola_id;
        $tM = sige_enc_tbl('sige_matriculas');
        $tT = sige_enc_tbl('sige_turmas');
        $turmas = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT m.turma_id
             FROM {$tM} m
             INNER JOIN {$tT} t ON t.id = m.turma_id AND t.escola_id = %d
             WHERE m.ano_lectivo = %d
               AND (m.status_matricula IS NULL OR m.status_matricula != 'cancelada')",
            $eid, $ano
        ));
        foreach ((array)$turmas as $t) {
            $tid = (int)$t->turma_id;
            if ($tid <= 0) continue;
            if (function_exists('sige_set_pauta_final_mode')) {
                sige_set_pauta_final_mode($ano, $tid, (bool)$enabled, $eid);
            } else {
                update_option("sige_pauta_final_mode_{$eid}_{$ano}_{$tid}", $enabled ? 1 : 0, false);
            }
        }
        return count((array)$turmas);
    }
}

if (!function_exists('sige_enc_get_turmas_do_ano')) {
    function sige_enc_get_turmas_do_ano($ano, $escola_id) {
        global $wpdb;
        $tT = sige_enc_tbl('sige_turmas');
        $ano = (int)$ano;
        $eid = (int)$escola_id;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tT}
             WHERE ano_lectivo = %d AND escola_id = %d
             ORDER BY classe, nome_turma, nome",
            $ano, $eid
        ));
    }
}

if (!function_exists('sige_enc_get_alunos_turma')) {
    function sige_enc_get_alunos_turma($turma_id, $ano, $escola_id) {
        global $wpdb;
        $tA = sige_enc_tbl('sige_alunos');
        $tM = sige_enc_tbl('sige_matriculas');
        $turma_id = (int)$turma_id;
        $ano = (int)$ano;
        $eid = (int)$escola_id;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.nome_completo, a.genero, m.id AS matricula_id, m.status_matricula
             FROM {$tA} a
             INNER JOIN {$tM} m ON m.aluno_id = a.id
             WHERE m.turma_id = %d
               AND m.ano_lectivo = %d
               AND m.escola_id = %d
               AND (m.status_matricula IS NULL OR m.status_matricula != 'cancelada')
             ORDER BY a.nome_completo",
            $turma_id, $ano, $eid
        ));
    }
}

if (!function_exists('sige_enc_get_required_disciplines')) {
    function sige_enc_get_required_disciplines($turma, $ano, $escola_id) {
        $tid = (int)($turma->id ?? 0);
        $classe_raw = (string)($turma->classe ?? '');
        $disciplinas = [];
        if (function_exists('sige_get_disciplinas_da_turma')) {
            $disciplinas = sige_get_disciplinas_da_turma($tid, $classe_raw, (int)$ano);
        }
        if (!is_array($disciplinas)) $disciplinas = [];
        $nucleares = [];
        foreach ($disciplinas as $d) {
            $did = is_object($d) ? (int)($d->id ?? 0) : (int)$d;
            if ($did <= 0) continue;
            $is_nuclear = false;
            if (function_exists('sige_is_nuclear_by_matriz')) {
                $is_nuclear = (bool)sige_is_nuclear_by_matriz($classe_raw, $did, $escola_id);
            } elseif (is_object($d) && isset($d->categoria)) {
                $is_nuclear = (strtolower((string)$d->categoria) === 'nuclear');
            }
            if ($is_nuclear) $nucleares[] = $d;
        }
        return !empty($nucleares) ? $nucleares : $disciplinas;
    }
}

if (!function_exists('sige_enc_disciplina_nome')) {
    function sige_enc_disciplina_nome($disciplina) {
        if (is_object($disciplina)) {
            return (string)($disciplina->nome ?? $disciplina->nome_disciplina ?? ('Disciplina #' . (int)($disciplina->id ?? 0)));
        }
        return 'Disciplina #' . (int)$disciplina;
    }
}

if (!function_exists('sige_enc_aluno_pronto_para_encerramento')) {
    function sige_enc_aluno_pronto_para_encerramento($aluno_id, $turma, $ano, $escola_id) {
        global $wpdb;
        $aid = (int)$aluno_id;
        $tid = (int)($turma->id ?? 0);
        $ano = (int)$ano;
        $eid = (int)$escola_id;
        $tN = sige_enc_tbl('sige_notas');
        $required = sige_enc_get_required_disciplines($turma, $ano, $eid);
        $gaps = [];
        if (empty($required)) {
            $gaps[] = 'Turma sem disciplinas obrigatórias configuradas.';
            return ['ready' => false, 'gaps' => $gaps, 'required_count' => 0];
        }
        $has_status = sige_enc_col_exists($tN, 'status');
        $has_escola = sige_enc_col_exists($tN, 'escola_id');
        foreach ($required as $disc) {
            $did = is_object($disc) ? (int)($disc->id ?? 0) : (int)$disc;
            if ($did <= 0) continue;
            $nome_disc = sige_enc_disciplina_nome($disc);
            for ($tri = 1; $tri <= 3; $tri++) {
                $sql = "SELECT nota_ac, nota_acp, nota_exame FROM {$tN}
                        WHERE aluno_id=%d AND turma_id=%d AND disciplina_id=%d AND trimestre=%d AND ano_lectivo=%d";
                $params = [$aid, $tid, $did, $tri, $ano];
                if ($has_escola) { $sql .= " AND escola_id=%d"; $params[] = $eid; }
                if ($has_status) { $sql .= " AND (status IS NULL OR status='aprovado')"; }
                $sql .= " LIMIT 1";
                $row = $wpdb->get_row($wpdb->prepare($sql, $params));
                if (!$row) {
                    $gaps[] = $nome_disc . " - {$tri}.º trimestre sem nota aprovada";
                    continue;
                }
                if ($row->nota_ac === null || $row->nota_ac === '' || $row->nota_acp === null || $row->nota_acp === '' || $row->nota_exame === null || $row->nota_exame === '') {
                    $gaps[] = $nome_disc . " - {$tri}.º trimestre incompleto";
                }
            }
        }
        return ['ready' => empty($gaps), 'gaps' => $gaps, 'required_count' => count($required)];
    }
}

if (!function_exists('sige_enc_get_prereq_report')) {
    function sige_enc_get_prereq_report($ano, $escola_id) {
        global $wpdb;
        $ano = (int)$ano;
        $eid = (int)$escola_id;
        $tN = sige_enc_tbl('sige_notas');
        $turmas = sige_enc_get_turmas_do_ano($ano, $eid);
        $report = [
            'can_close' => true,
            'turmas' => count((array)$turmas),
            'alunos' => 0,
            'pendentes' => 0,
            'rejeitadas' => 0,
            'lacunas' => 0,
            'turmas_sem_disciplinas' => 0,
            'alunos_com_lacunas' => [],
            'messages' => [],
        ];
        if (empty($turmas)) {
            $report['can_close'] = false;
            $report['messages'][] = 'Não existem turmas registadas para este ano lectivo.';
            return $report;
        }
        if (sige_enc_table_exists($tN) && sige_enc_col_exists($tN, 'status')) {
            $has_escola = sige_enc_col_exists($tN, 'escola_id');
            if ($has_escola) {
                $report['pendentes'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tN} WHERE ano_lectivo=%d AND escola_id=%d AND status='pendente'", $ano, $eid));
                $report['rejeitadas'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tN} WHERE ano_lectivo=%d AND escola_id=%d AND status='rejeitado'", $ano, $eid));
            } else {
                $report['pendentes'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tN} WHERE ano_lectivo=%d AND status='pendente'", $ano));
                $report['rejeitadas'] = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tN} WHERE ano_lectivo=%d AND status='rejeitado'", $ano));
            }
        }
        foreach ((array)$turmas as $turma) {
            $required = sige_enc_get_required_disciplines($turma, $ano, $eid);
            if (empty($required)) $report['turmas_sem_disciplinas']++;
            $alunos = sige_enc_get_alunos_turma((int)$turma->id, $ano, $eid);
            $report['alunos'] += count((array)$alunos);
            foreach ((array)$alunos as $aluno) {
                $ready = sige_enc_aluno_pronto_para_encerramento((int)$aluno->id, $turma, $ano, $eid);
                if (empty($ready['ready'])) {
                    $report['lacunas'] += count((array)$ready['gaps']);
                    if (count($report['alunos_com_lacunas']) < 12) {
                        $nome_turma = !empty($turma->nome_turma) ? $turma->nome_turma : ($turma->nome ?? ('Turma #' . (int)$turma->id));
                        $report['alunos_com_lacunas'][] = [
                            'aluno' => $aluno->nome_completo,
                            'turma' => $nome_turma,
                            'amostra' => array_slice((array)$ready['gaps'], 0, 3),
                        ];
                    }
                }
            }
        }
        if ($report['alunos'] <= 0) $report['messages'][] = 'Não existem alunos activos/matriculados para encerrar.';
        if ($report['pendentes'] > 0) $report['messages'][] = 'Existem notas pendentes de aprovação.';
        if ($report['rejeitadas'] > 0) $report['messages'][] = 'Existem notas rejeitadas ainda não corrigidas/re-submetidas.';
        if ($report['turmas_sem_disciplinas'] > 0) $report['messages'][] = 'Existem turmas sem disciplinas obrigatórias configuradas.';
        if ($report['lacunas'] > 0) $report['messages'][] = 'Existem notas obrigatórias incompletas ou sem aprovação.';
        $report['can_close'] = ($report['turmas'] > 0 && $report['alunos'] > 0 && $report['pendentes'] === 0 && $report['rejeitadas'] === 0 && $report['lacunas'] === 0 && $report['turmas_sem_disciplinas'] === 0);
        return $report;
    }
}

if (!function_exists('sige_enc_ensure_snapshot_table')) {
    function sige_enc_ensure_snapshot_table() {
        global $wpdb;
        $table = sige_enc_tbl('sige_ano_lectivo_snapshots');
        if (sige_enc_table_exists($table)) return $table;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $cc = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            turma_id BIGINT(20) UNSIGNED NOT NULL,
            matricula_id BIGINT(20) UNSIGNED DEFAULT NULL,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            classe VARCHAR(50) DEFAULT NULL,
            turma_nome VARCHAR(191) DEFAULT NULL,
            aluno_nome VARCHAR(191) DEFAULT NULL,
            situacao VARCHAR(30) DEFAULT NULL,
            media_global DECIMAL(5,2) DEFAULT NULL,
            negativas INT(11) DEFAULT 0,
            modo_pauta_final TINYINT(1) NOT NULL DEFAULT 1,
            dados LONGTEXT DEFAULT NULL,
            hash_integridade VARCHAR(64) DEFAULT NULL,
            status_snapshot VARCHAR(20) NOT NULL DEFAULT 'activo',
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            criado_em DATETIME DEFAULT NULL,
            reaberto_por BIGINT(20) UNSIGNED DEFAULT NULL,
            reaberto_em DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sige_enc_aluno (escola_id, ano_lectivo, turma_id, aluno_id),
            KEY idx_sige_enc_ano (escola_id, ano_lectivo),
            KEY idx_sige_enc_status (status_snapshot)
        ) {$cc};";
        dbDelta($sql);
        return $table;
    }
}

if (!function_exists('sige_enc_ensure_matricula_final_cols')) {
    function sige_enc_ensure_matricula_final_cols() {
        global $wpdb;
        $tM = sige_enc_tbl('sige_matriculas');
        $cols = [
            'situacao_final' => "ALTER TABLE {$tM} ADD COLUMN situacao_final VARCHAR(30) DEFAULT NULL",
            'media_final' => "ALTER TABLE {$tM} ADD COLUMN media_final DECIMAL(5,2) DEFAULT NULL",
            'negativas_final' => "ALTER TABLE {$tM} ADD COLUMN negativas_final INT(11) DEFAULT NULL",
            'encerramento_status' => "ALTER TABLE {$tM} ADD COLUMN encerramento_status VARCHAR(20) DEFAULT NULL",
            'encerrado_em' => "ALTER TABLE {$tM} ADD COLUMN encerrado_em DATETIME DEFAULT NULL",
            'encerrado_por' => "ALTER TABLE {$tM} ADD COLUMN encerrado_por BIGINT(20) UNSIGNED DEFAULT NULL",
        ];
        foreach ($cols as $col => $sql) {
            if (!sige_enc_col_exists($tM, $col)) {
                $wpdb->query($sql);
            }
        }
    }
}

if (!function_exists('sige_enc_audit')) {
    function sige_enc_audit($action, $data = []) {
        $payload = array_merge(['action' => $action, 'time' => current_time('mysql')], (array)$data);
        if (function_exists('sige_audit_log')) {
            sige_audit_log($action, $payload, 'academico');
        } elseif (function_exists('sige_obs_log')) {
            sige_obs_log($action, $payload);
        }
    }
}

if (!function_exists('sige_enc_criar_snapshot_final')) {
    function sige_enc_criar_snapshot_final($ano, $escola_id) {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_enc_criar_snapshot_final')) { return ['ok' => false, 'snapshots' => 0, 'message' => 'Contexto de escola invalido.']; }
        global $wpdb;
        $ano = (int)$ano;
        $eid = (int)$escola_id;
        $table = sige_enc_ensure_snapshot_table();
        sige_enc_ensure_matricula_final_cols();
        $tM = sige_enc_tbl('sige_matriculas');
        $turmas = sige_enc_get_turmas_do_ano($ano, $eid);
        $uid = get_current_user_id();
        $now = current_time('mysql');
        $stats = ['snapshots' => 0, 'progride' => 0, 'transita' => 0, 'reprova' => 0, 'sem_notas' => 0];
        foreach ((array)$turmas as $turma) {
            $tid = (int)$turma->id;
            $nome_turma = !empty($turma->nome_turma) ? $turma->nome_turma : ($turma->nome ?? ('Turma #' . $tid));
            $alunos = sige_enc_get_alunos_turma($tid, $ano, $eid);
            foreach ((array)$alunos as $aluno) {
                $situacao = 'SEM_NOTAS';
                $media = null;
                $negativas = 0;
                $detalhes = [];
                if (function_exists('sige_calcular_situacao_final')) {
                    $sit = sige_calcular_situacao_final((int)$aluno->id, $tid, $ano, true);
                    $situacao = strtoupper((string)($sit['situacao'] ?? 'SEM_NOTAS'));
                    $media = isset($sit['media_global']) && $sit['media_global'] !== null ? (float)$sit['media_global'] : null;
                    $negativas = isset($sit['negativas']) ? (int)$sit['negativas'] : 0;
                    $detalhes = $sit;
                }
                if (!isset($stats[strtolower($situacao)])) $stats['sem_notas']++;
                else $stats[strtolower($situacao)]++;
                $dados = [
                    'classe' => (string)($turma->classe ?? ''),
                    'turno' => (string)($turma->turno ?? ''),
                    'situacao' => $situacao,
                    'media_global' => $media,
                    'negativas' => $negativas,
                    'detalhes' => $detalhes,
                    'fonte' => 'encerramento_academico_seguro_v12_10_52',
                ];
                $hash = hash('sha256', wp_json_encode([$eid, $ano, $tid, (int)$aluno->id, $dados]));
                $wpdb->replace($table, [
                    'escola_id' => $eid,
                    'ano_lectivo' => $ano,
                    'turma_id' => $tid,
                    'matricula_id' => (int)($aluno->matricula_id ?? 0),
                    'aluno_id' => (int)$aluno->id,
                    'classe' => (string)($turma->classe ?? ''),
                    'turma_nome' => $nome_turma,
                    'aluno_nome' => (string)$aluno->nome_completo,
                    'situacao' => $situacao,
                    'media_global' => $media,
                    'negativas' => $negativas,
                    'modo_pauta_final' => 1,
                    'dados' => wp_json_encode($dados),
                    'hash_integridade' => $hash,
                    'status_snapshot' => 'activo',
                    'criado_por' => $uid,
                    'criado_em' => $now,
                    'reaberto_por' => null,
                    'reaberto_em' => null,
                ], ['%d','%d','%d','%d','%d','%s','%s','%s','%s','%f','%d','%d','%s','%s','%s','%d','%s','%d','%s']);
                $wpdb->update($tM, [
                    'situacao_final' => $situacao,
                    'media_final' => $media,
                    'negativas_final' => $negativas,
                    'encerramento_status' => 'encerrado',
                    'encerrado_em' => $now,
                    'encerrado_por' => $uid,
                ], [
                    'id' => (int)($aluno->matricula_id ?? 0),
                    'escola_id' => $eid,
                ]);
                $stats['snapshots']++;
            }
        }
        sige_enc_audit('encerramento_snapshot_final_criado', ['ano' => $ano, 'escola_id' => $eid, 'stats' => $stats]);
        return $stats;
    }
}

if (!function_exists('sige_enc_marcar_reabertura')) {
    function sige_enc_marcar_reabertura($ano, $escola_id) {
        if (!sige_tenant_write_guard((int) $escola_id, 'sige_enc_marcar_reabertura')) { return; }
        global $wpdb;
        $ano = (int)$ano;
        $eid = (int)$escola_id;
        $uid = get_current_user_id();
        $now = current_time('mysql');
        $table = sige_enc_tbl('sige_ano_lectivo_snapshots');
        if (sige_enc_table_exists($table)) {
            $wpdb->update($table, [
                'status_snapshot' => 'reaberto',
                'reaberto_por' => $uid,
                'reaberto_em' => $now,
            ], [
                'escola_id' => $eid,
                'ano_lectivo' => $ano,
                'status_snapshot' => 'activo',
            ]);
        }
        $tM = sige_enc_tbl('sige_matriculas');
        if (sige_enc_col_exists($tM, 'encerramento_status')) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$tM}
                 SET encerramento_status='reaberto'
                 WHERE escola_id=%d AND ano_lectivo=%d AND encerramento_status='encerrado'",
                $eid, $ano
            ));
        }
        sige_enc_audit('ano_lectivo_reaberto', ['ano' => $ano, 'escola_id' => $eid]);
    }
}
/* ─── ANO SELECCIONADO ───────────────────────────────────────────── */
$ano_atual    = sige_enc_ano_atual();
$anos_opcoes  = range( 2020, (int)wp_date('Y') + 1 );
$ano_sel      = isset($_GET['ano_enc']) ? sanitize_text_field($_GET['ano_enc']) : $ano_atual;
/* ─── PROCESSAR ACÇÕES (Toggle Pauta Final, Encerrar, Reabrir) ────── */
$msg_feedback = '';
$msg_tipo     = 'info'; // info | success | error | warning
if ( isset($_POST['sige_enc_action']) && check_admin_referer('sige_enc_nonce_action') ) {
    $action   = sanitize_text_field($_POST['sige_enc_action']);
    $ano_post = sanitize_text_field($_POST['ano_lectivo_enc'] ?? $ano_sel);

    $__requires_fechar_ano = in_array($action, ['toggle_pauta_final','ativar_pauta_final_todas','encerrar_ano','reabrir_ano'], true);
    if ($__requires_fechar_ano && !$__sige_can_fechar_ano) {
        $msg_feedback = 'Sem permissão para alterar pauta final ou encerrar/reabrir o ano lectivo.';
        $msg_tipo = 'error';
        if (function_exists('sige_obs_log')) {
            sige_obs_log('permission_denied', ['permission' => 'academico.fechar_ano', 'view' => 'encerramento', 'action' => $action]);
        }
        $redirect_url = admin_url("admin.php?page=sige-app&view=encerramento&ano_enc={$ano_post}&feedback=" . urlencode($msg_feedback) . "&ftipo={$msg_tipo}");
        echo '<script ' . sige_csp_script_attr() . '>window.location.replace(' . wp_json_encode($redirect_url) . ');</script>';
        exit;
    }
    $ano_post_int = (int)$ano_post;
    $ano_post_encerrado = (bool) get_option("sige_ano_lectivo_encerrado_{$_eid}_{$ano_post_int}", 0);

    /* Toggle Pauta Final individual */
    if ( $action === 'toggle_pauta_final' ) {
        if ( $ano_post_encerrado ) {
            $msg_feedback = "Ano Lectivo {$ano_post_int} encerrado. A Pauta Final não pode ser alterada enquanto o ano estiver encerrado.";
            $msg_tipo     = 'error';
        } else {
            $turma_id = intval($_POST['turma_id'] ?? 0);
            if ( $turma_id > 0 ) {
                $actual = function_exists('sige_is_pauta_final_mode') ? sige_is_pauta_final_mode($ano_post_int, $turma_id, $_eid) : (bool) get_option("sige_pauta_final_mode_{$_eid}_{$ano_post_int}_{$turma_id}", 0);
                $novo   = ! $actual;
                if (function_exists('sige_set_pauta_final_mode')) {
                    sige_set_pauta_final_mode($ano_post_int, $turma_id, $novo, $_eid);
                } else {
                    update_option("sige_pauta_final_mode_{$_eid}_{$ano_post_int}_{$turma_id}", $novo ? 1 : 0, false);
                }
                $estado      = $novo ? 'activada' : 'desactivada';
                $msg_feedback = "Pauta Final {$estado} para a turma.";
                $msg_tipo     = 'success';
            }
        }
    }
    /* Activar Pauta Final em TODAS as turmas do ano */
    if ( $action === 'ativar_pauta_final_todas' ) {
        if ( $ano_post_encerrado ) {
            $msg_feedback = "Ano Lectivo {$ano_post_int} encerrado. Não é possível alterar a Pauta Final.";
            $msg_tipo     = 'error';
        } else {
            $qtd_pf = sige_enc_set_pauta_final_turmas($ano_post_int, $_eid, true);
            $msg_feedback = "Pauta Final activada em {$qtd_pf} turma(s) de {$ano_post_int}.";
            $msg_tipo     = 'success';
            sige_enc_audit('pauta_final_activada_todas', ['ano' => $ano_post_int, 'escola_id' => $_eid, 'turmas' => $qtd_pf]);
        }
    }
    /* Encerrar Ano - fluxo seguro, com pré-validação e snapshot formal */
    if ( $action === 'encerrar_ano' && $__sige_can_fechar_ano ) {
        $ja_encerrado = (bool) get_option("sige_ano_lectivo_encerrado_{$_eid}_{$ano_post_int}", 0);
        if ( $ja_encerrado ) {
            $msg_feedback = "O ano {$ano_post_int} já está encerrado.";
            $msg_tipo     = 'warning';
        } else {
            $check = sige_enc_get_prereq_report($ano_post_int, $_eid);
            if ( empty($check['can_close']) ) {
                $motivos = !empty($check['messages']) ? implode(' ', array_slice($check['messages'], 0, 4)) : 'Existem pendências académicas por resolver.';
                $msg_feedback = "Encerramento bloqueado com segurança. {$motivos}";
                $msg_tipo     = 'error';
                sige_enc_audit('encerramento_bloqueado', ['ano' => $ano_post_int, 'escola_id' => $_eid, 'check' => $check]);
            } else {
                /* A operação oficial activa Pauta Final, cria snapshot por aluno e só depois marca o ano como encerrado. */
                $qtd_pf = sige_enc_set_pauta_final_turmas($ano_post_int, $_eid, true);
                $snapshot = sige_enc_criar_snapshot_final($ano_post_int, $_eid);
                $current_user = wp_get_current_user();
                update_option("sige_ano_lectivo_encerrado_{$_eid}_{$ano_post_int}", 1, false);
                update_option("sige_ano_encerrado_data_{$_eid}_{$ano_post_int}", wp_date('Y-m-d H:i:s'), false);
                update_option("sige_ano_encerrado_por_{$_eid}_{$ano_post_int}", $current_user->display_name ?: $current_user->user_login, false);
                update_option("sige_ano_encerrado_snapshot_stats_{$_eid}_{$ano_post_int}", $snapshot, false);
                update_option("sige_ano_encerrado_checklist_{$_eid}_{$ano_post_int}", $check, false);
                sige_enc_audit('ano_lectivo_encerrado_seguro', ['ano' => $ano_post_int, 'escola_id' => $_eid, 'turmas_pf' => $qtd_pf, 'snapshot' => $snapshot]);
                $msg_feedback = "Ano Lectivo {$ano_post_int} encerrado com segurança. Snapshot final criado para {$snapshot['snapshots']} aluno(s), com Pauta Final bloqueada em {$qtd_pf} turma(s).";
                $msg_tipo     = 'success';
            }
        }
    }
    /* Reabrir Ano - reabertura real: desbloqueia ano, desactiva Pauta Final e marca snapshot como reaberto */
    if ( $action === 'reabrir_ano' && $__sige_can_fechar_ano ) {
        $qtd_pf = sige_enc_set_pauta_final_turmas($ano_post_int, $_eid, false);
        update_option("sige_ano_lectivo_encerrado_{$_eid}_{$ano_post_int}", 0, false);
        delete_option("sige_ano_encerrado_data_{$_eid}_{$ano_post_int}");
        delete_option("sige_ano_encerrado_por_{$_eid}_{$ano_post_int}");
        sige_enc_marcar_reabertura($ano_post_int, $_eid);
        $msg_feedback = "Ano Lectivo {$ano_post_int} reaberto com segurança. A Pauta Final foi desactivada em {$qtd_pf} turma(s) e as notas voltaram a ser editáveis.";
        $msg_tipo     = 'warning';
    }
    /* Redirigir para evitar re-POST */
    $redirect_url = admin_url("admin.php?page=sige-app&view=encerramento&ano_enc={$ano_post}&feedback=" . urlencode($msg_feedback) . "&ftipo={$msg_tipo}");
    // wp_redirect() falha aqui pois os headers do HTML ja foram enviados
    // pela sidebar do sige-softgenial.php. JS redirect funciona sempre.
    echo '<script ' . sige_csp_script_attr() . '>window.location.replace(' . wp_json_encode($redirect_url) . ');</script>';
    exit;
}
/* Feedback vindo de redirect */
if ( empty($msg_feedback) && isset($_GET['feedback']) ) {
    $msg_feedback = sanitize_text_field(urldecode($_GET['feedback']));
    $msg_tipo     = sanitize_text_field($_GET['ftipo'] ?? 'info');
}
/* ─── ESTADO DO ANO SELECCIONADO ─────────────────────────────────── */
$ano_encerrado    = (bool) get_option("sige_ano_lectivo_encerrado_{$_eid}_{$ano_sel}", 0);
$enc_data         = get_option("sige_ano_encerrado_data_{$_eid}_{$ano_sel}", '');
$enc_por          = get_option("sige_ano_encerrado_por_{$_eid}_{$ano_sel}", '');
/* ─── TURMAS DO ANO ──────────────────────────────────────────────── */
$tTurmas     = sige_enc_tbl('sige_turmas');
$tMatriculas = sige_enc_tbl('sige_matriculas');
$tAlunos     = sige_enc_tbl('sige_alunos');
$tNotas      = sige_enc_tbl('sige_notas');
$turmas = $wpdb->get_results( $wpdb->prepare(
    "SELECT t.*, COUNT(DISTINCT m.aluno_id) AS total_alunos
     FROM {$tTurmas} t
     LEFT JOIN {$tMatriculas} m ON m.turma_id = t.id AND m.ano_lectivo = %s AND m.status_matricula != 'cancelada'
     WHERE t.ano_lectivo = %d AND t.escola_id = %d
     GROUP BY t.id
     ORDER BY t.classe, t.nome_turma, t.nome",
    $ano_sel, (int)$ano_sel, $_eid
));
/* ─── CALCULAR SITUAÇÕES POR TURMA ──────────────────────────────── */
$resumo_global = [
    'total'       => 0,
    'progride'    => 0,
    'transita'    => 0,
    'reprova'     => 0,
    'sem_notas'   => 0,
];
$dados_turmas = [];
foreach ( $turmas as $turma ) {
    $tid       = (int) $turma->id;
    $pf_mode   = (bool) get_option("sige_pauta_final_mode_{$_eid}_{$ano_sel}_{$tid}", 0);
    $alunos_t = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.id, a.nome_completo, a.genero
         FROM {$tAlunos} a
         INNER JOIN {$tMatriculas} m ON m.aluno_id = a.id
         WHERE m.turma_id = %d AND m.ano_lectivo = %s AND m.status_matricula != 'cancelada'
         ORDER BY a.nome_completo",
        $tid, $ano_sel
    ));
    $ct = [
        'total'     => count($alunos_t),
        'progride'  => 0,
        'transita'  => 0,
        'reprova'   => 0,
        'sem_notas' => 0,
    ];
    foreach ( $alunos_t as $aluno ) {
        /* Encerramento seguro: só calcula situação final quando as notas obrigatórias estão completas e aprovadas. */
        $ready_final = sige_enc_aluno_pronto_para_encerramento((int)$aluno->id, $turma, (int)$ano_sel, $_eid);
        if ( empty($ready_final['ready']) ) {
            $ct['sem_notas']++;
            continue;
        }
        if ( function_exists('sige_calcular_situacao_final') ) {
            $sit = sige_calcular_situacao_final($aluno->id, $tid, $ano_sel, true);
            $situacao = strtoupper($sit['situacao'] ?? 'SEM_NOTAS');
        } else {
            $situacao = 'SEM_NOTAS';
        }
        if     ( $situacao === 'PROGRIDE' ) $ct['progride']++;
        elseif ( $situacao === 'TRANSITA' ) $ct['transita']++;
        elseif ( $situacao === 'REPROVA'  ) $ct['reprova']++;
        else                                $ct['sem_notas']++;
    }
    $aprovados   = $ct['progride'] + $ct['transita'];
    $taxa_apr    = $ct['total'] > 0 ? round($aprovados / $ct['total'] * 100, 1) : 0;
    $nome_turma  = !empty($turma->nome_turma) ? $turma->nome_turma : $turma->nome;
    $dados_turmas[] = [
        'id'         => $tid,
        'nome'       => $nome_turma,
        'classe'     => $turma->classe,
        'turno'      => $turma->turno ?? '',
        'pf_mode'    => $pf_mode,
        'ct'         => $ct,
        'taxa_apr'   => $taxa_apr,
    ];
    /* Acumular global */
    foreach ( ['total','progride','transita','reprova','sem_notas'] as $k ) {
        $resumo_global[$k] += $ct[$k];
    }
}
$aprovados_global = $resumo_global['progride'] + $resumo_global['transita'];
$taxa_global      = $resumo_global['total'] > 0
    ? round($aprovados_global / $resumo_global['total'] * 100, 1)
    : 0;

/* ─── CHECKLIST DE SEGURANÇA DO ENCERRAMENTO ─────────────────────── */
$sige_enc_checklist = sige_enc_get_prereq_report((int)$ano_sel, $_eid);
$sige_enc_snapshot_stats = get_option("sige_ano_encerrado_snapshot_stats_{$_eid}_{$ano_sel}", []);
/* ─── CSS ────────────────────────────────────────────────────────── */
?>

<style id="sige-encerramento-produto-pro-v121052">
/* ============================================================================
   SIGE SoftGenial - Encerramento do Ano: Produto PRO v12.10.52
   Escopo: harmonização visual com Painel Principal / Dashboard V2 MJS-grade.
   Não altera cálculos, permissões, notas, pautas, DEC, boletins ou BD.
   ============================================================================ */
#sige-enc-wrap.sige-enc-page{
    --sgv2-purple: var(--sg-theme-primary,var(--color-brand-500));
    --sgv2-purple-dark: var(--color-brand-700);
    --sgv2-purple-soft: var(--sg-theme-soft,var(--color-brand-100));
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
#sige-enc-wrap.sige-enc-page *{box-sizing:border-box;}
@keyframes sigeEncFadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes sigeEncSpin{to{transform:rotate(360deg)}}

/* HERO - rigorosamente alinhado ao Painel Principal */
.sige-enc-hero{
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
    animation:sigeEncFadeInUp .45s ease-out both;
}
.sige-enc-hero:before{
    content:"";
    position:absolute;
    inset:auto -80px -130px auto;
    width:420px;
    height:300px;
    border-radius:var(--radius-pill);
    background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);
    pointer-events:none;
}
.sige-enc-hero:after{display:none!important;content:none!important;}
.sige-enc-title-wrap,.sige-enc-hero-panel{position:relative;z-index:1;}
.sige-enc-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    padding:0;
    border:0;
    background:transparent;
    color:var(--sgv2-purple);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
    box-shadow:none;
}
.sige-enc-kicker:before,.sige-enc-kicker:after{display:none!important;content:none!important;}
.sige-enc-kicker svg{width:18px!important;height:18px!important;color:currentColor!important;stroke:currentColor!important;fill:none!important;flex:0 0 auto;}
.sige-enc-title-wrap h1{
    margin:0;
    max-width:900px;
    color:var(--sgv2-ink);
    font-size:clamp(30px,2.45vw,42px);
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
}
#sige-enc-wrap .sige-enc-subtitle{
    max-width:760px;
    margin:var(--space-3) 0 0;
    color:var(--sgv2-muted);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.sige-enc-hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:var(--space-3);margin-top:24px;}
.sige-enc-hero-panel{
    min-height:148px;
    border-radius:var(--radius-xl);
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:var(--space-3);
}
.sige-enc-hero-panel:before{
    content:"";
    position:absolute;
    right:22px;
    bottom:16px;
    width:112px;
    height:92px;
    border-radius:22px 22px 12px 12px;
    background:rgba(109,93,252,.16);
    box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);
}
.sige-enc-status{
    width:max-content;
    display:inline-flex;
    align-items:center;
    gap:9px;
    padding:10px 14px;
    border-radius:var(--radius-pill);
    background:var(--color-white);
    border:1px solid rgba(30,34,60,.08);
    box-shadow:var(--shadow-sm);
    font-weight:700;
    font-size:12px;
    line-height:1.2;
    text-transform:uppercase;
    letter-spacing:.05em;
    color:var(--sgv2-purple);
}
.sige-enc-status svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-enc-status.aberto{color:var(--sgv2-green);}
.sige-enc-status.encerrado{color:var(--sgv2-red);align-items:flex-start;border-radius:var(--radius-lg);}
.sige-enc-status small{display:block;margin-top:3px;font-size:var(--fs-xs);font-weight:600;text-transform:none;letter-spacing:0;color:var(--color-slate-500);}
.sige-enc-hero-chips{display:grid;grid-template-columns:1fr;gap:10px;position:relative;z-index:1;}
.sige-enc-chip{
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    gap:10px;
    min-height:46px;
    padding:10px 12px;
    border-radius:var(--radius-lg);
    background:rgba(255,255,255,.76);
    border:1px solid rgba(30,34,60,.07);
    box-shadow:var(--shadow-sm);
}
.sige-enc-chip-icon{width:34px;height:34px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--color-white);color:var(--sgv2-purple);box-shadow:var(--shadow-sm);}
.sige-enc-chip-icon svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-enc-chip strong{display:block;color:var(--color-ink-500);font-size:12px;font-weight:700;line-height:1.25;white-space:normal;}
.sige-enc-chip small{display:block;margin-top:2px;color:var(--color-ink-400);font-size:var(--fs-xs);font-weight:600;line-height:1.25;}

/* Feedback */
.sige-enc-alert{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:var(--radius-lg);margin-bottom:18px;font-weight:600;font-size:var(--fs-sm);animation:sigeEncFadeInUp .35s ease-out both;box-shadow:var(--shadow-xs);border:1px solid transparent;}
.sige-enc-alert.success{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);}
.sige-enc-alert.error{background:var(--color-danger-50);color:var(--color-danger-700);border-color:var(--color-danger-200);}
.sige-enc-alert.warning{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-300);}
.sige-enc-alert.info{background:var(--color-info-50);color:var(--color-info-600);border-color:var(--color-info-200);}

/* Toolbar */
.sige-enc-toolbar{
    display:flex;
    align-items:center;
    gap:var(--space-3);
    flex-wrap:wrap;
    margin-bottom:20px;
    padding:16px 18px;
    background:var(--color-white);
    border:1px solid rgba(30,34,60,.08);
    border-radius:var(--radius-xl);
    box-shadow:var(--shadow-xs);
    animation:sigeEncFadeInUp .45s ease-out .05s both;
}
.sige-enc-toolbar label{font-size:var(--fs-sm);color:var(--color-slate-800);}
.sige-enc-toolbar select,.sige-enc-toolbar button,.sige-enc-toolbar a.button{height:42px;border-radius:var(--radius-md);font-size:var(--fs-sm);font-family:inherit;}
.sige-enc-toolbar select{padding:0 14px;border:1px solid var(--color-ink-100);background:var(--color-white);color:var(--color-ink-500);font-weight:600;}
.sige-enc-toolbar .sige-enc-toolbar-total{font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-500);background:var(--color-slate-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-pill);padding:9px 12px;}
.sige-enc-toolbar-sep{width:1px;height:26px;background:var(--color-slate-100);}

/* KPIs */
.sige-enc-cards{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:var(--space-4);margin-bottom:22px;animation:sigeEncFadeInUp .45s ease-out .1s both;}
.sige-enc-card{position:relative;overflow:hidden;min-height:104px;background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-xs);padding:18px 20px;text-align:left;border:1px solid rgba(28,32,54,.08);transition:transform .18s ease,box-shadow .18s ease;}
.sige-enc-card:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--card-soft,var(--color-brand-50));}
.sige-enc-card:hover{transform:translateY(-1px);box-shadow:var(--shadow-lg);}
.sige-enc-card .card-num{position:relative;z-index:1;font-size:27px;line-height:1;font-weight:700;letter-spacing:-.03em;color:var(--card-color,var(--sgv2-purple));}
.sige-enc-card .card-lbl{position:relative;z-index:1;font-size:12px;color:var(--color-slate-600);margin-top:8px;font-weight:700;line-height:1.25;}
.sige-enc-card.c-total{--card-color:var(--color-brand-500);--card-soft:var(--color-brand-50);}
.sige-enc-card.c-progride{--card-color:var(--color-success-500);--card-soft:var(--color-success-100);}
.sige-enc-card.c-transita{--card-color:var(--color-info-500);--card-soft:var(--color-info-50);}
.sige-enc-card.c-reprova{--card-color:var(--color-danger-500);--card-soft:var(--color-danger-50);}
.sige-enc-card.c-sem{--card-color:var(--color-warning-500);--card-soft:var(--color-warning-50);}
.sige-enc-card.c-taxa{--card-color:var(--color-brand-500);--card-soft:var(--color-brand-50);}

/* Acções */
.sige-enc-actions{display:flex;gap:var(--space-3);flex-wrap:wrap;margin-bottom:22px;animation:sigeEncFadeInUp .45s ease-out .12s both;}
.sige-enc-actions form{margin:0;}
.btn-encerrar,.btn-reabrir,.btn-pf-todas,.btn-imprimir{
    min-height:46px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    border-radius:var(--radius-md);
    padding:0 var(--space-5);
    font-size:var(--fs-sm);
    font-weight:700;
    text-decoration:none;
    border:1px solid transparent;
    transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;
    box-shadow:var(--shadow-sm);
    cursor:pointer;
    font-family:inherit;
}
.btn-encerrar svg,.btn-reabrir svg,.btn-pf-todas svg,.btn-imprimir svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.btn-pf-todas{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));color:var(--color-white);box-shadow:var(--shadow-md);}
.btn-encerrar{background:var(--color-danger-500);color:var(--color-white);box-shadow:var(--shadow-md);}
.btn-reabrir{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-200);}
.btn-imprimir{background:var(--color-white);color:var(--color-ink-900);border-color:var(--color-ink-100);}
.btn-encerrar:hover,.btn-reabrir:hover,.btn-pf-todas:hover,.btn-imprimir:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}

/* Info */
.sige-enc-info-box{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-3);align-items:start;background:var(--color-warning-50);border:1px solid var(--color-warning-300);border-radius:var(--radius-lg);padding:16px 18px;margin-bottom:18px;font-size:var(--fs-sm);line-height:1.5;color:var(--color-warning-900);box-shadow:var(--shadow-xs);}
.sige-enc-info-icon{width:36px;height:36px;border-radius:var(--radius-md);background:var(--color-white);color:var(--color-warning-700);display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-sm);}
.sige-enc-info-icon svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-enc-info-box strong{color:var(--color-danger-900);}

/* Checklist de segurança */
.sige-enc-checklist{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-xs);padding:20px 22px;margin-bottom:20px;animation:sigeEncFadeInUp .45s ease-out .11s both;}
.sige-enc-checklist-head{display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-4);margin-bottom:16px;flex-wrap:wrap;}
.sige-enc-checklist-title{display:flex;align-items:center;gap:var(--space-3);}
.sige-enc-checklist-title .icon{width:38px;height:38px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;background:var(--color-brand-50);color:var(--sgv2-purple);}
.sige-enc-checklist-title svg{width:20px!important;height:20px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-enc-checklist-title strong{display:block;font-size:15px;font-weight:700;color:var(--color-ink-500);line-height:1.2;}
.sige-enc-checklist-title small{display:block;margin-top:3px;font-size:12px;font-weight:600;color:var(--color-slate-500);line-height:1.35;}
.sige-enc-readiness{display:inline-flex;align-items:center;gap:var(--space-2);border-radius:var(--radius-pill);padding:9px 12px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.sige-enc-readiness.ok{background:var(--color-success-50);color:var(--color-success-900);border:1px solid var(--color-success-200);}
.sige-enc-readiness.blocked{background:var(--color-danger-50);color:var(--color-danger-700);border:1px solid var(--color-danger-200);}
.sige-enc-check-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:var(--space-3);}
.sige-enc-check-item{border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);padding:13px 14px;background:var(--color-white);}
.sige-enc-check-item span{display:block;color:var(--color-slate-500);font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;line-height:1.15;}
.sige-enc-check-item strong{display:block;margin-top:6px;color:var(--color-ink-500);font-size:var(--fs-lg);font-weight:700;line-height:1;}
.sige-enc-check-messages{margin:14px 0 0;padding:12px 14px;border-radius:var(--radius-lg);background:var(--color-warning-50);border:1px solid var(--color-warning-300);color:var(--color-warning-900);font-size:12px;line-height:1.5;font-weight:600;}
.sige-enc-check-messages ul{margin:8px 0 0 18px;padding:0;}
.sige-enc-check-messages li{margin:var(--space-1) 0;}
.sige-enc-gap-list{margin-top:12px;display:grid;gap:var(--space-2);}
.sige-enc-gap-row{padding:10px 12px;border-radius:var(--radius-md);background:var(--color-white);border:1px solid var(--color-ink-50);font-size:12px;color:var(--color-slate-700);}
.sige-enc-gap-row strong{color:var(--color-ink-500);}
.btn-encerrar[disabled],.btn-encerrar.is-disabled{opacity:.55;filter:grayscale(.2);cursor:not-allowed;box-shadow:none;}

/* Secções e tabela */
.sige-enc-section{background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-xs);padding:0;overflow:hidden;margin-bottom:26px;border:1px solid rgba(30,34,60,.08);animation:sigeEncFadeInUp .45s ease-out .16s both;}
.sige-enc-section-header{padding:20px 22px 14px;background:var(--color-white);border-bottom:1px solid var(--color-slate-100);display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);flex-wrap:wrap;}
.sige-enc-section-header h2{display:flex;align-items:center;gap:var(--space-3);margin:0;font-size:17px;line-height:1.1;font-weight:700;letter-spacing:-.03em;color:var(--color-ink-500);}
.sige-enc-section-header h2 svg{width:20px!important;height:20px!important;stroke:currentColor!important;color:var(--sgv2-purple)!important;fill:none!important;}
.sige-enc-section-count{font-size:12px;font-weight:700;color:var(--sgv2-purple);background:var(--color-brand-50);border-radius:var(--radius-pill);padding:8px 11px;white-space:nowrap;}
.sige-enc-table-wrap{width:100%;overflow:auto;}
table.sige-enc-tbl{width:100%;min-width:940px;border-collapse:collapse;font-size:var(--fs-sm);}
table.sige-enc-tbl th{background:var(--color-white);padding:13px 14px;text-align:left;font-weight:700;color:var(--color-slate-600);border-bottom:1px solid var(--color-slate-100);white-space:nowrap;}
table.sige-enc-tbl td{padding:13px 14px;border-bottom:1px solid var(--color-slate-100);vertical-align:middle;color:var(--color-ink-800);}
table.sige-enc-tbl tr:last-child td{border-bottom:none;}
table.sige-enc-tbl tbody tr:hover td{background:var(--color-white);}
table.sige-enc-tbl tfoot tr{background:var(--color-white);font-weight:700;}
.sige-prog-wrap{min-width:120px;}
.sige-prog-bar{height:9px;border-radius:var(--radius-pill);background:var(--color-slate-100);overflow:hidden;margin-bottom:5px;}
.sige-prog-fill{height:100%;border-radius:var(--radius-pill);transition:width .4s;}
.sige-prog-txt{font-size:12px;color:var(--color-slate-500);font-weight:700;}
.sige-badge{display:inline-flex;align-items:center;justify-content:center;min-width:28px;padding:5px 10px;border-radius:var(--radius-pill);font-size:12px;font-weight:700;}
.badge-progride{background:var(--color-success-100);color:var(--color-success-900);}
.badge-transita{background:var(--color-info-50);color:var(--color-info-600);}
.badge-reprova{background:var(--color-danger-50);color:var(--color-danger-700);}
.badge-sem{background:var(--color-warning-50);color:var(--color-warning-800);}
.sige-toggle-wrap{display:flex;align-items:center;gap:var(--space-2);}
.sige-toggle{position:relative;display:inline-block;width:42px;height:22px;}
.sige-toggle input{opacity:0;width:0;height:0;}
.sige-toggle .slider{position:absolute;cursor:pointer;inset:0;background:var(--color-ink-200);border-radius:var(--radius-xl);transition:.3s;}
.sige-toggle .slider::before{position:absolute;content:"";height:16px;width:16px;left:3px;bottom:3px;background:var(--color-white);border-radius:50%;transition:.3s;box-shadow:var(--shadow-xs);}
.sige-toggle input:checked + .slider{background:var(--color-success-700);}
.sige-toggle input:checked + .slider::before{transform:translateX(20px);}
.sige-toggle-lbl{font-size:12px;color:var(--color-slate-500);font-weight:600;}
.sige-toggle-lbl.on{color:var(--color-success-900);font-weight:700;}
.sige-enc-empty{padding:42px 24px;text-align:center;color:var(--color-slate-500);font-size:var(--fs-base);}
.sige-enc-empty-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-3);background:var(--color-brand-50);color:var(--sgv2-purple);}
.sige-enc-empty-icon svg{width:24px!important;height:24px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
#sige-enc-print-footer{margin-top:30px;font-size:12px;color:var(--color-ink-400);text-align:center;font-weight:600;}

@media (max-width:1500px){.sige-enc-hero{grid-template-columns:1fr}.sige-enc-hero-panel{display:none}.sige-enc-cards{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media (max-width:900px){.sige-enc-cards{grid-template-columns:repeat(2,minmax(0,1fr));}.sige-enc-toolbar{display:grid;grid-template-columns:1fr}.sige-enc-toolbar form{display:grid!important;grid-template-columns:1fr;gap:8px}.sige-enc-toolbar-sep{display:none}.sige-enc-actions{display:grid;grid-template-columns:1fr}.sige-enc-actions form{display:block!important}.btn-encerrar,.btn-reabrir,.btn-pf-todas,.btn-imprimir{width:100%;}}
@media (max-width:720px){.sige-enc-hero{padding:var(--space-6) var(--space-5);border-radius:22px}.sige-enc-title-wrap h1{font-size:24px}.sige-enc-cards{grid-template-columns:1fr}.sige-enc-section{border-radius:22px}.sige-enc-section-header{align-items:flex-start}.sige-enc-table-wrap{margin:0 -1px}.sige-enc-info-box{grid-template-columns:1fr}.sige-enc-info-icon{display:none}}
@media print{#adminmenuwrap,#wpadminbar,#wpfooter,.sige-enc-toolbar,.sige-enc-actions,.sige-toggle-wrap,.no-print{display:none!important}#sige-enc-wrap{max-width:100%!important}.sige-enc-hero{box-shadow:none;border:1px solid var(--color-slate-200);display:block;min-height:auto}.sige-enc-hero-panel{display:none}.sige-enc-section{box-shadow:none;border:1px solid var(--color-ink-200)}body{font-size:11pt}}
</style>

<?php
/* ─── INÍCIO DO HTML ─────────────────────────────────────────────── */
$page_url = admin_url('admin.php?page=sige-app&view=encerramento');
?>

<div id="sige-enc-wrap" class="wrap sige-enc-page sg-dashboard-v2">
    <section class="sige-enc-hero" aria-label="Encerramento do ano lectivo">
        <div class="sige-enc-title-wrap">
            <div class="sige-enc-kicker"><?php echo sige_enc_icon('lock'); ?> Ano Lectivo</div>
            <h1>Encerramento do Ano</h1>
            <p class="sige-enc-subtitle">Acompanhe o estado do ano lectivo, confirme o desempenho por turma e controle o bloqueio final das pautas.</p>
        </div>
        <div class="sige-enc-hero-panel" aria-label="Resumo do encerramento">
            <?php if ( $ano_encerrado ): ?>
                <span class="sige-enc-status encerrado"><?php echo sige_enc_icon('lock'); ?><span>Encerrado
                    <?php if ($enc_data): ?>
                        <small>em <?= esc_html(date_i18n('d/m/Y H:i', strtotime($enc_data), false)) ?> por <?= esc_html($enc_por) ?></small>
                    <?php endif; ?>
                </span></span>
            <?php else: ?>
                <span class="sige-enc-status aberto"><?php echo sige_enc_icon('check'); ?><span>Em curso</span></span>
            <?php endif; ?>
            <div class="sige-enc-hero-chips">
                <div class="sige-enc-chip">
                    <span class="sige-enc-chip-icon"><?php echo sige_enc_icon('school'); ?></span>
                    <div>
                        <strong><?= esc_html($_escola_nome) ?></strong>
                        <small>Unidade escolar em referência</small>
                    </div>
                </div>
                <div class="sige-enc-chip">
                    <span class="sige-enc-chip-icon"><?php echo sige_enc_icon('calendar'); ?></span>
                    <div>
                        <strong>Hora de referência: <?= esc_html(wp_date('d/m/Y H:i')) ?> (Moçambique)</strong>
                        <small>Fuso horário: Africa/Maputo</small>
                    </div>
                </div>
                <div class="sige-enc-chip">
                    <span class="sige-enc-chip-icon"><?php echo sige_enc_icon('book'); ?></span>
                    <div>
                        <strong>Ano lectivo: <?= esc_html($ano_sel) ?></strong>
                        <small>Base do relatório e das acções</small>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php if ( $msg_feedback ): ?>
        <div class="sige-enc-alert <?= esc_attr($msg_tipo) ?>"><?= esc_html($msg_feedback) ?></div>
    <?php endif; ?>
    <!-- ── TOOLBAR ── -->

    <div class="sige-enc-toolbar">
        <form method="get" action="" style="display:contents;">
            <input type="hidden" name="page" value="sige-app">
            <input type="hidden" name="view" value="encerramento">
            <label for="ano_enc_sel"><strong>Ano Lectivo:</strong></label>
            <select name="ano_enc" id="ano_enc_sel" onchange="this.form.submit()">
                <?php foreach ( array_reverse($anos_opcoes) as $a ): ?>
                    <option value="<?= esc_attr($a) ?>" <?= selected($a, $ano_sel, false) ?>>
                        <?= esc_html($a) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="sgk-btn sgk-btn-sec">Filtrar</button></noscript>
        </form>
        <span class="sige-enc-toolbar-sep" aria-hidden="true"></span>
        <strong class="sige-enc-toolbar-total">Total de turmas: <?= count($turmas) ?></strong>
    </div>

    <!-- ── CHECKLIST DE SEGURANÇA ── -->
    <section class="sige-enc-checklist no-print" aria-label="Checklist de segurança do encerramento académico">
        <div class="sige-enc-checklist-head">
            <div class="sige-enc-checklist-title">
                <span class="icon"><?php echo sige_enc_icon('shield'); ?></span>
                <div>
                    <strong>Checklist de Segurança do Encerramento</strong>
                    <small>O sistema só permite encerrar quando não existem notas pendentes, rejeitadas ou lacunas obrigatórias.</small>
                </div>
            </div>
            <?php if (!empty($sige_enc_checklist['can_close'])): ?>
                <span class="sige-enc-readiness ok"><?php echo sige_enc_icon('check'); ?> Pronto para encerrar</span>
            <?php else: ?>
                <span class="sige-enc-readiness blocked"><?php echo sige_enc_icon('alert'); ?> Encerramento bloqueado</span>
            <?php endif; ?>
        </div>
        <div class="sige-enc-check-grid">
            <div class="sige-enc-check-item"><span>Turmas</span><strong><?= (int)$sige_enc_checklist['turmas'] ?></strong></div>
            <div class="sige-enc-check-item"><span>Alunos</span><strong><?= (int)$sige_enc_checklist['alunos'] ?></strong></div>
            <div class="sige-enc-check-item"><span>Notas pendentes</span><strong><?= (int)$sige_enc_checklist['pendentes'] ?></strong></div>
            <div class="sige-enc-check-item"><span>Notas rejeitadas</span><strong><?= (int)$sige_enc_checklist['rejeitadas'] ?></strong></div>
            <div class="sige-enc-check-item"><span>Lacunas</span><strong><?= (int)$sige_enc_checklist['lacunas'] ?></strong></div>
        </div>
        <?php if (!empty($sige_enc_checklist['messages'])): ?>
            <div class="sige-enc-check-messages">
                <strong>Antes de encerrar, resolva estes pontos:</strong>
                <ul>
                    <?php foreach ((array)$sige_enc_checklist['messages'] as $m): ?>
                        <li><?= esc_html($m) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($sige_enc_checklist['alunos_com_lacunas'])): ?>
                    <div class="sige-enc-gap-list">
                        <?php foreach ((array)$sige_enc_checklist['alunos_com_lacunas'] as $gap): ?>
                            <div class="sige-enc-gap-row">
                                <strong><?= esc_html($gap['aluno']) ?></strong> · <?= esc_html($gap['turma']) ?><br>
                                <?= esc_html(implode(' · ', (array)$gap['amostra'])) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($ano_encerrado && !empty($sige_enc_snapshot_stats)): ?>
            <div class="sige-enc-check-messages" style="background:var(--color-success-50);border-color:var(--color-success-200);color:var(--color-success-800);">
                <strong>Snapshot final activo:</strong>
                <?= (int)($sige_enc_snapshot_stats['snapshots'] ?? 0) ?> aluno(s) congelado(s),
                <?= (int)($sige_enc_snapshot_stats['progride'] ?? 0) ?> progride,
                <?= (int)($sige_enc_snapshot_stats['transita'] ?? 0) ?> transita,
                <?= (int)($sige_enc_snapshot_stats['reprova'] ?? 0) ?> reprova.
            </div>
        <?php endif; ?>
    </section>
    <!-- ── INFO DE ENCERRAMENTO ── -->
    <?php if ( $ano_encerrado && $enc_data ): ?>
    <div class="sige-enc-info-box">
        <span class="sige-enc-info-icon"><?php echo sige_enc_icon('lock'); ?></span>
        <div>
            <strong>Ano <?= esc_html($ano_sel) ?> encerrado</strong> em
            <strong><?= esc_html(date_i18n('d/m/Y \à\s H:i', strtotime($enc_data), false)) ?></strong>
            por <strong><?= esc_html($enc_por) ?></strong>.
            As pautas estão bloqueadas para edição.
            <?php if ( $__sige_can_fechar_ano ): ?>
                Pode reabrir o ano abaixo se necessário.
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <!-- ── CARDS DE RESUMO GLOBAL ── -->
    <div class="sige-enc-cards">
        <div class="sige-enc-card c-total">
            <div class="card-num"><?= $resumo_global['total'] ?></div>
            <div class="card-lbl">Total Alunos</div>
        </div>
        <div class="sige-enc-card c-progride">
            <div class="card-num"><?= $resumo_global['progride'] ?></div>
            <div class="card-lbl">Progride</div>
        </div>
        <div class="sige-enc-card c-transita">
            <div class="card-num"><?= $resumo_global['transita'] ?></div>
            <div class="card-lbl">Transita</div>
        </div>
        <div class="sige-enc-card c-reprova">
            <div class="card-num"><?= $resumo_global['reprova'] ?></div>
            <div class="card-lbl">Reprova</div>
        </div>
        <div class="sige-enc-card c-sem">
            <div class="card-num"><?= $resumo_global['sem_notas'] ?></div>
            <div class="card-lbl">Lacunas / Sem Notas</div>
        </div>
        <div class="sige-enc-card c-taxa">
            <div class="card-num"><?= $taxa_global ?>%</div>
            <div class="card-lbl">Taxa Aprovação</div>
        </div>
    </div>
    <!-- ── BOTÕES DE ACÇÃO PRINCIPAL ── -->
    <div class="sige-enc-actions no-print">
        <?php if ( ! $ano_encerrado ): ?>
            <?php if ( $__sige_can_fechar_ano ): ?>
            <!-- Activar Pauta Final em todas -->
            <form method="post" style="display:contents;">
                <?php wp_nonce_field('sige_enc_nonce_action'); ?>
                <input type="hidden" name="sige_enc_action" value="ativar_pauta_final_todas">
                <input type="hidden" name="ano_lectivo_enc" value="<?= esc_attr($ano_sel) ?>">
                <button type="submit" class="btn-pf-todas"
                    data-sige-titulo="Activar Pauta Final em todas as turmas" data-sige-confirm="A Pauta Final será activada em TODAS as turmas de <?= esc_attr($ano_sel) ?>: o cálculo da Nota Final (MFD + Exame) fica configurado em cada uma." data-sige-confirmar="Activar em todas">
                    <?php echo sige_enc_icon('clipboard'); ?> Activar Pauta Final em Todas as Turmas
                </button>
            </form>
            <?php endif; ?>
            <?php if ( $__sige_can_fechar_ano ): ?>
            <!-- Encerrar Ano -->
            <form method="post" style="display:contents;">
                <?php wp_nonce_field('sige_enc_nonce_action'); ?>
                <input type="hidden" name="sige_enc_action" value="encerrar_ano">
                <input type="hidden" name="ano_lectivo_enc" value="<?= esc_attr($ano_sel) ?>">
                <?php if (!empty($sige_enc_checklist['can_close'])): ?>
                    <button type="submit" class="btn-encerrar"
                        data-sige-titulo="Encerrar o Ano Lectivo <?= esc_attr($ano_sel) ?>" data-sige-confirm="Esta acção valida o checklist académico, activa a Pauta Final em todas as turmas, cria o snapshot final por aluno, bloqueia notas, aprovação e alterações de pauta, e regista a auditoria com data e utilizador. Depois de encerrado, só um utilizador autorizado poderá reabrir o ano." data-sige-confirmar="Encerrar o ano" data-sige-perigo="1">
                        <?php echo sige_enc_icon('lock'); ?> Encerrar Ano Lectivo <?= esc_html($ano_sel) ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-encerrar is-disabled" disabled title="Resolva primeiro as pendências do checklist de segurança.">
                        <?php echo sige_enc_icon('lock'); ?> Encerrar Ano Lectivo <?= esc_html($ano_sel) ?>
                    </button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        <?php elseif ( $__sige_can_fechar_ano ): ?>
            <!-- Reabrir Ano -->
            <form method="post" style="display:contents;">
                <?php wp_nonce_field('sige_enc_nonce_action'); ?>
                <input type="hidden" name="sige_enc_action" value="reabrir_ano">
                <input type="hidden" name="ano_lectivo_enc" value="<?= esc_attr($ano_sel) ?>">
                <button type="submit" class="btn-reabrir"
                    data-sige-titulo="Reabrir o Ano Lectivo <?= esc_attr($ano_sel) ?>" data-sige-confirm="A Pauta Final será desactivada e as notas voltam a ser editáveis. A reabertura fica registada na auditoria." data-sige-confirmar="Reabrir o ano">
                    <?php echo sige_enc_icon('unlock'); ?> Reabrir Ano Lectivo <?= esc_html($ano_sel) ?>
                </button>
            </form>
        <?php endif; ?>
        <!-- Imprimir -->
        <button type="button" class="btn-imprimir" data-sige-act="sigeImprimirPagina" data-sige-noargs>
            <?php echo sige_enc_icon('file'); ?> Imprimir Relatório
        </button>
    </div><!-- /.sige-enc-actions -->
    <!-- ── TABELA POR TURMA ── -->
    <div class="sige-enc-section">
        <div class="sige-enc-section-header">
            <h2><?php echo sige_enc_icon('chart'); ?> Situação por Turma - Ano Lectivo <?= esc_html($ano_sel) ?></h2>
            <span class="sige-enc-section-count"><?= count($dados_turmas) ?> turma(s)</span>
        </div>
        <?php if ( empty($dados_turmas) ): ?>
            <div class="sige-enc-empty">
                <div class="sige-enc-empty-icon"><?php echo sige_enc_icon('folder'); ?></div>
                <p>Nenhuma turma encontrada para o ano lectivo <strong><?= esc_html($ano_sel) ?></strong>.</p>
                <p style="font-size:.85rem;">Verifique se existem matrículas registadas para este ano.</p>
            </div>
        <?php else: ?>
        <div class="sige-enc-table-wrap">
        <table class="sige-enc-tbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Turma</th>
                    <th>Classe</th>
                    <th>Turno</th>
                    <th class="sige-u-tac">Total</th>
                    <th class="sige-u-tac">Progride</th>
                    <th class="sige-u-tac">Transita</th>
                    <th class="sige-u-tac">Reprova</th>
                    <th class="sige-u-tac">Lacunas / Sem Notas</th>
                    <th style="min-width:160px;">Taxa Aprovação</th>
                    <th class="no-print">Pauta Final</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $dados_turmas as $idx => $d ):
                $ct = $d['ct'];
                $cor_barra = $d['taxa_apr'] >= 80 ? 'var(--color-success-600)' : ( $d['taxa_apr'] >= 60 ? 'var(--color-warning-500)' : 'var(--color-danger-600)' );
            ?>
                <tr>
                    <td style="color:var(--color-slate-400);font-size:.8rem;"><?= $idx + 1 ?></td>
                    <td><strong><?= esc_html($d['nome']) ?></strong></td>
                    <td><?= esc_html($d['classe']) ?></td>
                    <td><?= esc_html($d['turno'] ?: '-') ?></td>
                    <td class="sige-u-tac sige-u-fw7"><?= $ct['total'] ?></td>
                    <td class="sige-u-tac">
                        <?php if ( $ct['progride'] > 0 ): ?>
                            <span class="sige-badge badge-progride"><?= $ct['progride'] ?></span>
                        <?php else: ?>
                            <span style="color:var(--color-slate-300);">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="sige-u-tac">
                        <?php if ( $ct['transita'] > 0 ): ?>
                            <span class="sige-badge badge-transita"><?= $ct['transita'] ?></span>
                        <?php else: ?>
                            <span style="color:var(--color-slate-300);">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="sige-u-tac">
                        <?php if ( $ct['reprova'] > 0 ): ?>
                            <span class="sige-badge badge-reprova"><?= $ct['reprova'] ?></span>
                        <?php else: ?>
                            <span style="color:var(--color-slate-300);">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="sige-u-tac">
                        <?php if ( $ct['sem_notas'] > 0 ): ?>
                            <span class="sige-badge badge-sem"><?= $ct['sem_notas'] ?></span>
                        <?php else: ?>
                            <span style="color:var(--color-slate-300);">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="sige-prog-wrap">
                            <div class="sige-prog-bar">
                                <div class="sige-prog-fill"
                                     style="width:<?= $d['taxa_apr'] ?>%;background:<?= $cor_barra ?>;"></div>
                            </div>
                            <div class="sige-prog-txt"><?= $d['taxa_apr'] ?>%</div>
                        </div>
                    </td>
                    <td class="no-print">
                        <?php if ( $ano_encerrado ): ?>
                            <span style="font-size:.8rem;color:var(--color-slate-400);">Bloqueado</span>
                        <?php elseif ( $__sige_can_fechar_ano ): ?>
                            <form method="post" class="sige-u-m0">
                                <?php wp_nonce_field('sige_enc_nonce_action'); ?>
                                <input type="hidden" name="sige_enc_action" value="toggle_pauta_final">
                                <input type="hidden" name="ano_lectivo_enc" value="<?= esc_attr($ano_sel) ?>">
                                <input type="hidden" name="turma_id" value="<?= $d['id'] ?>">
                                <div class="sige-toggle-wrap">
                                    <label class="sige-toggle" title="Clique para alternar">
                                        <input type="checkbox"
                                               <?= $d['pf_mode'] ? 'checked' : '' ?>
                                               onchange="this.closest('form').submit()">
                                        <span class="slider"></span>
                                    </label>
                                    <span class="sige-toggle-lbl <?= $d['pf_mode'] ? 'on' : '' ?>">
                                        <?= $d['pf_mode'] ? 'Activa' : 'Inactiva' ?>
                                    </span>
                                </div>
                            </form>
                        <?php else: ?>
                            <span class="sige-badge <?= $d['pf_mode'] ? 'badge-progride' : 'badge-sem' ?>">
                                <?= $d['pf_mode'] ? 'Activa' : 'Inactiva' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--color-slate-50);font-weight:700;">
                    <td colspan="4" style="text-align:right;padding:10px 14px;">TOTAIS GLOBAIS</td>
                    <td class="sige-u-tac"><?= $resumo_global['total'] ?></td>
                    <td style="text-align:center;color:var(--color-success-600);"><?= $resumo_global['progride'] ?></td>
                    <td style="text-align:center;color:var(--color-info-500);"><?= $resumo_global['transita'] ?></td>
                    <td style="text-align:center;color:var(--color-danger-600);"><?= $resumo_global['reprova'] ?></td>
                    <td style="text-align:center;color:var(--color-warning-800);"><?= $resumo_global['sem_notas'] ?></td>
                    <td><strong style="color:var(--color-brand-600);"><?= $taxa_global ?>% aprovação</strong></td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php endif; ?>
    </div><!-- /.sige-enc-section (tabela) -->
    <!-- ── RODAPÉ DO RELATÓRIO (visível na impressão) ── -->
    <div style="margin-top:30px;font-size:.8rem;color:var(--color-slate-400);text-align:center;" id="sige-enc-print-footer">
        Relatório gerado em <?= wp_date('d/m/Y H:i') ?> · <?php echo esc_html($_escola_nome); ?> ·
        Ano Lectivo <?= esc_html($ano_sel) ?> ·
        SIGE SoftGenial
    </div>
</div><!-- /#sige-enc-wrap -->
<?php
/*
 * ════════════════════════════════════════════════════════════════════
 * INSTRUÇÕES DE INSTALAÇÃO
 * ════════════════════════════════════════════════════════════════════
 *
 * 1. Copiar este ficheiro para: wp-content/plugins/sige-softgenial/admin/encerramento-view.php
 *
 * 2. Em sige-softgenial.php, adicionar nos require_once (se ainda não existirem):
 *    require_once SIGE_PATH . 'includes/pauta-pdf-handler.php';
 *    require_once SIGE_PATH . 'includes/pauta-excel-handler.php';
 *
 * 3. Na função de routing (switch/if por $view), adicionar:
 *    case 'encerramento':
 *        include SIGE_PATH . 'admin/encerramento-view.php';
 *        break;
 *
 * 4. No add_action('admin_menu', ...), adicionar no menu lateral (opcional):
 *    add_submenu_page(
 *        'sige-app',
 *        'Encerramento de Ano',
 *        '🔒 Encerramento',
 *        'manage_options',
 *        'sige-app',
 *        null    // usa o routing interno por ?view=encerramento
 *    );
 *    // URL: admin.php?page=sige-app&view=encerramento
 *
 * ════════════════════════════════════════════════════════════════════
 */
?>
