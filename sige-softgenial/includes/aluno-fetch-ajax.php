<?php
/**
 * SIGE SoftGenial - AJAX Handlers para gestão de alunos
 *
 * v12.9.8 - Suporte para paginação server-side da página alunos_lista.
 *
 * Endpoints:
 *   - sige_get_aluno_full       → devolve um aluno + dados expandidos para o modal de edição
 *   - sige_get_alunos_export    → devolve alunos filtrados com payload minimo por finalidade
 *
 * Padrão de segurança:
 *   - sige_check_nonce_global() verifica nonce sige_alunos_action via _sige_nonce
 *   - Capability via sige_can() / (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
 *   - escola_id validado por sige_get_escola_id()
 */

if (!defined('ABSPATH')) exit;

// ======================================================================
// HELPER: capability check para listagem/edição de alunos
// ======================================================================
if (!function_exists('sige_alunos_user_can_view')) {
    function sige_alunos_user_can_view(): bool {
        // Reusa o page_guard se disponível (matriz SIGE)
        if (function_exists('sige_page_guard_allows')) {
            return sige_page_guard_allows(
                ['alunos.ver'],
                ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda']
            );
        }
        return ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_assistente') || current_user_can('sige_recepcao') || current_user_can('sige_guarda'));
    }
}

if (!function_exists('sige_alunos_user_is_guarda')) {
    function sige_alunos_user_is_guarda(): bool {
        if (!is_user_logged_in()) return false;
        if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) return false;
        if (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user()) return false;
        $user_id = get_current_user_id();
        if ($user_id <= 0) return false;
        if (current_user_can('sige_guarda')) return true;
        if (function_exists('sige_permissions_get_active_role')) {
            $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
            $role = sige_permissions_get_active_role((int)$user_id, $escola_id);
            return $role && !empty($role->slug) && (string)$role->slug === 'guarda';
        }
        return false;
    }
}


if (!function_exists('sige_alunos_user_can_view_finance')) {
    /**
     * v12.11.9.68 - Ficha 360º alinhada ao escopo financeiro real.
     * Quem só pode consultar alunos não recebe resumo financeiro no AJAX.
     */
    function sige_alunos_user_can_view_finance(): bool {
        if (function_exists('sige_alunos_user_is_guarda') && sige_alunos_user_is_guarda()) return false;
        if (function_exists('sige_page_guard_allows')) {
            return (bool)sige_page_guard_allows(
                ['financeiro.ver','financeiro.dashboard_ver','financeiro.extractos_ver','financeiro.cobrancas_ver','financeiro.pagar'],
                ['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao']
            );
        }
        if (function_exists('sige_can')) {
            foreach (['financeiro.ver','financeiro.dashboard_ver','financeiro.extractos_ver','financeiro.cobrancas_ver','financeiro.pagar'] as $perm) {
                if (sige_can($perm)) return true;
            }
        }
        foreach (['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao'] as $cap) {
            if (current_user_can($cap)) return true;
        }
        return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
    }
}


// ======================================================================
// v12.11.9.69 - helpers granulares de acções sensíveis no módulo Alunos
// ======================================================================
if (!function_exists('sige_alunos_user_can_any_scope')) {
    function sige_alunos_user_can_any_scope(array $permissions, array $legacy_caps = []): bool {
        if (function_exists('sige_alunos_user_is_guarda') && sige_alunos_user_is_guarda()) return false;
        if ((function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) || (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user())) return true;
        if (function_exists('sige_page_guard_allows')) {
            try { return (bool)sige_page_guard_allows($permissions, $legacy_caps); } catch (Throwable $e) {}
        }
        if (function_exists('sige_can')) {
            foreach ($permissions as $permission) {
                $permission = (string)$permission;
                if ($permission !== '' && sige_can($permission)) return true;
            }
        }
        foreach ($legacy_caps as $cap) {
            $cap = (string)$cap;
            if ($cap !== '' && current_user_can($cap)) return true;
        }
        return current_user_can('manage_options');
    }
}

if (!function_exists('sige_alunos_user_can_view_documents')) {
    function sige_alunos_user_can_view_documents(): bool {
        return sige_alunos_user_can_any_scope(
            ['documentos.ver','documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.boletins_ver','academico.boletins_emitir'],
            ['sige_director','sige_secretario','sige_secretaria_geral','sige_pedagogico','sige_assistente','sige_recepcao']
        );
    }
}

if (!function_exists('sige_alunos_user_can_emit_documents')) {
    function sige_alunos_user_can_emit_documents(): bool {
        return sige_alunos_user_can_any_scope(
            ['documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.boletins_emitir'],
            ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_pedagogico']
        );
    }
}

if (!function_exists('sige_alunos_user_can_export_students')) {
    function sige_alunos_user_can_export_students(): bool {
        return sige_alunos_user_can_any_scope(
            ['documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.estatisticas_ver'],
            ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_pedagogico']
        );
    }
}

if (!function_exists('sige_alunos_user_can_view_academic_summary')) {
    function sige_alunos_user_can_view_academic_summary(): bool {
        return sige_alunos_user_can_any_scope(
            ['academico.ver','academico.turmas_ver','academico.boletins_ver','academico.pautas_ver','academico.lancar_notas','matriculas.ver'],
            ['sige_director','sige_secretario','sige_secretaria_geral','sige_pedagogico','sige_professor','sige_educador','sige_assistente','sige_recepcao']
        );
    }
}

if (!function_exists('sige_alunos_user_can_view_communications')) {
    function sige_alunos_user_can_view_communications(): bool {
        return sige_alunos_user_can_any_scope(
            ['comunicacao.ver','comunicacao.enviar','whatsapp.ver','whatsapp.enviar','financeiro.extractos_ver','financeiro.cobrancas_ver'],
            ['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao']
        );
    }
}

if (!function_exists('sige_alunos_user_can_view_sensitive_student_details')) {
    /**
     * Detalhes sensíveis = contactos familiares, morada, saúde, auditoria e documentos de identificação em texto.
     * Um perfil com apenas alunos.ver continua a consultar o aluno, mas não recebe esses blocos no payload AJAX.
     */
    function sige_alunos_user_can_view_sensitive_student_details(): bool {
        return sige_alunos_user_can_any_scope(
            ['alunos.editar','matriculas.ver','documentos.ver','financeiro.extractos_ver','comunicacao.ver','transporte.alunos_gerir'],
            ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_financeiro']
        );
    }
}

if (!function_exists('sige_alunos_ajax_table_exists')) {
    function sige_alunos_ajax_table_exists($table_name): bool {
        global $wpdb;
        $table_name = (string)$table_name;
        if ($table_name === '') return false;
        return (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));
    }
}

if (!function_exists('sige_alunos_ajax_column_exists')) {
    function sige_alunos_ajax_column_exists($table_name, $column_name): bool {
        global $wpdb;
        $table_name = (string)$table_name;
        $column_name = (string)$column_name;
        if ($table_name === '' || $column_name === '' || !sige_alunos_ajax_table_exists($table_name)) return false;
        return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $wpdb->esc_like($column_name)));
    }
}

if (!function_exists('sige_alunos_ajax_resolve_rotas_table')) {
    /**
     * v12.11.9.69 - resolve a tabela real de rotas de transporte.
     * A versão moderna usa sige_transporte_rotas; algumas bases antigas ainda podem ter sige_rotas.
     */
    function sige_alunos_ajax_resolve_rotas_table() {
        global $wpdb;
        $modern = $wpdb->prefix . 'sige_transporte_rotas';
        $legacy = $wpdb->prefix . 'sige_rotas';
        if (sige_alunos_ajax_table_exists($modern)) return $modern;
        if (sige_alunos_ajax_table_exists($legacy)) return $legacy;
        return '';
    }
}

if (!function_exists('sige_alunos_ajax_rota_join_parts')) {
    function sige_alunos_ajax_rota_join_parts($tbl_alunos, $alunos_alias = 'a'): array {
        $tbl_alunos = (string)$tbl_alunos;
        $alunos_alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$alunos_alias) ?: 'a';
        $tbl_rotas = sige_alunos_ajax_resolve_rotas_table();
        if ($tbl_alunos === '' || $tbl_rotas === '' || !sige_alunos_ajax_column_exists($tbl_alunos, 'rota_transporte_id') || !sige_alunos_ajax_column_exists($tbl_rotas, 'id')) {
            return ['join' => '', 'select' => ''];
        }
        $nome_expr = sige_alunos_ajax_column_exists($tbl_rotas, 'nome_rota') ? 'r.nome_rota' : (sige_alunos_ajax_column_exists($tbl_rotas, 'nome') ? 'r.nome' : "''");
        $preco_expr = sige_alunos_ajax_column_exists($tbl_rotas, 'preco_mensal') ? 'r.preco_mensal' : (sige_alunos_ajax_column_exists($tbl_rotas, 'valor_mensal') ? 'r.valor_mensal' : '0');
        $escola_guard = sige_alunos_ajax_column_exists($tbl_rotas, 'escola_id') ? " AND (r.escola_id = {$alunos_alias}.escola_id OR r.escola_id IS NULL)" : '';
        return [
            'join' => " LEFT JOIN {$tbl_rotas} r ON {$alunos_alias}.rota_transporte_id = r.id{$escola_guard} ",
            'select' => ", {$nome_expr} AS transporte_nome_rota, {$preco_expr} AS transporte_preco_mensal ",
        ];
    }
}

// ======================================================================
// AJAX: sige_get_aluno_full
// ======================================================================
add_action('wp_ajax_sige_get_aluno_full', 'sige_ajax_get_aluno_full');

function sige_ajax_get_aluno_full() {
    sige_check_nonce_global();
    if (!sige_alunos_user_can_manage()) {
        wp_send_json_error('Sem permissão para carregar dados de edição do aluno.');
    }

    $aluno_id = isset($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;
    if ($aluno_id <= 0) {
        wp_send_json_error('ID de aluno inválido.');
    }

    global $wpdb;
    $escola_id  = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $ano_lectivo = function_exists('sige_get_ano_lectivo') ? (int)sige_get_ano_lectivo() : (int)wp_date('Y');

    // v12.11.9.69 - rota de transporte defensiva: tabela moderna, fallback legado e colunas opcionais.
    $tbl_alunos = $wpdb->prefix . 'sige_alunos';
    $rota_parts = sige_alunos_ajax_rota_join_parts($tbl_alunos, 'a');
    $join_rota = $rota_parts['join'];
    $select_rota = $rota_parts['select'];

    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, t.nome AS turma_nome, t.classe, m.turma_id {$select_rota}
         FROM {$tbl_alunos} a
         LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id AND m.ano_lectivo = %d AND m.escola_id = a.escola_id)
         LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = a.escola_id
         {$join_rota}
         WHERE a.id = %d AND a.escola_id = %d
         LIMIT 1",
        $ano_lectivo, $aluno_id, $escola_id
    ));

    if (!$aluno) {
        wp_send_json_error('Aluno não encontrado ou sem acesso.');
    }

    // Enriquecer com actividades extras (se a tabela existir)
    $tbl_atv = $wpdb->prefix . 'sige_aluno_atividades_extras';
    $has_atv = (bool)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tbl_atv));
    if ($has_atv) {
        $rows_atv = $wpdb->get_results($wpdb->prepare(
            "SELECT servico_id FROM {$tbl_atv}
             WHERE escola_id = %d AND ativo = 1 AND aluno_id = %d",
            $escola_id, $aluno_id
        ));
        $aluno->atividades_extras_ids = array_map(function($r){ return (int)$r->servico_id; }, $rows_atv);
    } else {
        $aluno->atividades_extras_ids = [];
    }

    wp_send_json_success($aluno);
}

// ======================================================================
// AJAX: sige_get_alunos_export
// ======================================================================
// Devolve todos os alunos da escola (sem paginação) para uso em exportação
// Excel ou impressão de cartões em lote. É chamado on-demand pelo cliente
// quando o utilizador clica em "Excel" ou "Cartões (Lote)".
//
// Observação de performance: este endpoint TEM que carregar tudo (sem
// limit), por definição. Mas como só é executado quando o utilizador
// quer exportar/imprimir (não no boot da página), o custo é amortizado.
// O cliente cacheia o resultado em variável JS na sessão para evitar
// chamadas repetidas.
// ======================================================================
add_action('wp_ajax_sige_get_alunos_export', 'sige_ajax_get_alunos_export');

function sige_ajax_get_alunos_export() {
    sige_check_nonce_global();

    $purpose = isset($_POST['purpose']) ? sanitize_key(wp_unslash((string)$_POST['purpose'])) : 'export';
    if (!in_array($purpose, ['excel','export','cards'], true)) {
        $purpose = 'export';
    }

    if ($purpose === 'cards') {
        if (!function_exists('sige_alunos_user_can_emit_documents') || !sige_alunos_user_can_emit_documents()) {
            wp_send_json_error('Sem permissão para imprimir cartões em lote.');
        }
    } elseif (!function_exists('sige_alunos_user_can_export_students') || !sige_alunos_user_can_export_students()) {
        wp_send_json_error('Sem permissão para exportar listas de alunos.');
    }

    global $wpdb;
    $escola_id  = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $ano_lectivo = function_exists('sige_get_ano_lectivo') ? (int)sige_get_ano_lectivo() : (int)wp_date('Y');

    $tbl_alunos = $wpdb->prefix . 'sige_alunos';
    $rota_parts = sige_alunos_ajax_rota_join_parts($tbl_alunos, 'a');
    $join_rota = $rota_parts['join'];
    $select_rota = $rota_parts['select'];

    // v12.11.9.69 - aplica no servidor os mesmos filtros visíveis na página.
    $filtro_turma = isset($_POST['filtro_turma']) ? max(0, (int)$_POST['filtro_turma']) : 0;
    $filtro_status = isset($_POST['filtro_status']) ? sanitize_key(wp_unslash((string)$_POST['filtro_status'])) : '';
    $filtro_search = isset($_POST['filtro_search']) ? sanitize_text_field(wp_unslash((string)$_POST['filtro_search'])) : '';
    $where_parts = ['a.escola_id = %d'];
    $where_params = [$escola_id];
    if ($filtro_turma > 0) {
        $where_parts[] = 'm.turma_id = %d';
        $where_params[] = $filtro_turma;
    }
    $allowed_status = ['activo','suspenso','transferido','desistente'];
    if (in_array($filtro_status, $allowed_status, true)) {
        $where_parts[] = 'a.status = %s';
        $where_params[] = $filtro_status;
    }
    if ($filtro_search !== '') {
        $like = '%' . $wpdb->esc_like($filtro_search) . '%';
        $where_parts[] = '(a.nome_completo LIKE %s OR a.numero_processo LIKE %s OR a.contacto_encarregado LIKE %s OR a.telemovel_pai LIKE %s OR a.telemovel_mae LIKE %s OR t.nome LIKE %s OR t.classe LIKE %s)';
        array_push($where_params, $like, $like, $like, $like, $like, $like, $like);
    }
    $where_sql = implode(' AND ', $where_parts);
    $select_aluno = function_exists('sige_alunos_perf_export_select_sql')
        ? sige_alunos_perf_export_select_sql($tbl_alunos, 'a', $purpose)
        : (($purpose === 'cards') ? 'a.id, a.nome_completo, a.numero_processo, a.foto, a.status' : 'a.*');

    // Aumentar memory_limit temporariamente porque vamos carregar tudo
    $old_mem = ini_get('memory_limit');
    @ini_set('memory_limit', '512M');

    $alunos = $wpdb->get_results($wpdb->prepare(
        "SELECT {$select_aluno}, t.nome AS turma_nome, t.classe, m.turma_id {$select_rota}
         FROM {$tbl_alunos} a
         LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id AND m.ano_lectivo = %d AND m.escola_id = a.escola_id)
         LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = a.escola_id
         {$join_rota}
         WHERE {$where_sql}
         ORDER BY t.classe ASC, t.nome ASC, a.nome_completo ASC",
        array_merge([$ano_lectivo], $where_params)
    ));

    // v12.11.9.69 - minimização de dados para cartões: o browser não precisa receber dados familiares,
    // financeiros, documentos ou contactos quando a finalidade é apenas imprimir crachás/cartões.
    if ($purpose === 'cards') {
        $cards = array_map(static function($a) {
            return [
                'id' => (int)($a->id ?? 0),
                'nome_completo' => (string)($a->nome_completo ?? ''),
                'numero_processo' => (string)($a->numero_processo ?? ''),
                'foto' => (string)($a->foto ?? ''),
                'classe' => (string)($a->classe ?? ''),
                'turma_nome' => (string)($a->turma_nome ?? ''),
                'turma_id' => (int)($a->turma_id ?? 0),
                'status' => (string)($a->status ?? 'activo'),
            ];
        }, (array)$alunos);
        @ini_set('memory_limit', $old_mem);
        wp_send_json_success($cards);
    }

    // Enriquecer com actividades extras em batch (uma query única IN)
    $tbl_atv = $wpdb->prefix . 'sige_aluno_atividades_extras';
    $has_atv = (bool)$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tbl_atv));
    if ($has_atv && !empty($alunos)) {
        $ids = array_map(function($a){ return (int)$a->id; }, $alunos);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $rows_atv = $wpdb->get_results($wpdb->prepare(
            "SELECT aluno_id, servico_id FROM {$tbl_atv}
             WHERE escola_id = %d AND ativo = 1 AND aluno_id IN ({$placeholders})",
            array_merge([$escola_id], $ids)
        ));
        $map = [];
        foreach ($rows_atv as $r) $map[(int)$r->aluno_id][] = (int)$r->servico_id;
        foreach ($alunos as &$a) {
            $a->atividades_extras_ids = $map[(int)$a->id] ?? [];
        }
        unset($a);
    }

    @ini_set('memory_limit', $old_mem);

    wp_send_json_success($alunos);
}


// ======================================================================
// AJAX: sige_get_aluno_360
// ======================================================================
// Ficha 360º do aluno + Índice de Qualidade dos Dados.
// Princípios desta camada:
//   - Apenas leitura; não grava nem altera regras académicas/financeiras.
//   - Escopo obrigatório por escola_id e aluno_id.
//   - Reutiliza nonce/permissões do módulo Alunos.
//   - Consultas defensivas: só lê tabelas auxiliares quando existem.
//   - Índice de qualidade é operacional, calculado em memória, sem schema novo.
// ======================================================================
add_action('wp_ajax_sige_get_aluno_360', 'sige_ajax_get_aluno_360');

if (!function_exists('sige_aluno_360_table_exists')) {
    function sige_aluno_360_table_exists($table_name) {
        global $wpdb;
        $table_name = (string)$table_name;
        if ($table_name === '') return false;
        return (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));
    }
}

if (!function_exists('sige_aluno_360_column_exists')) {
    function sige_aluno_360_column_exists($table_name, $column_name) {
        global $wpdb;
        $table_name  = (string)$table_name;
        $column_name = (string)$column_name;
        if ($table_name === '' || $column_name === '' || !sige_aluno_360_table_exists($table_name)) return false;
        return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $wpdb->esc_like($column_name)));
    }
}

if (!function_exists('sige_aluno_360_pick')) {
    function sige_aluno_360_pick($source, $keys, $default = '') {
        $keys = is_array($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            if (is_object($source) && isset($source->{$key}) && $source->{$key} !== null && $source->{$key} !== '') {
                return $source->{$key};
            }
            if (is_array($source) && isset($source[$key]) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('sige_aluno_360_has_value')) {
    function sige_aluno_360_has_value($value) {
        return trim((string)$value) !== '';
    }
}

if (!function_exists('sige_aluno_360_normalize_mz_phone')) {
    function sige_aluno_360_normalize_mz_phone($phone) {
        $digits = preg_replace('/\D+/', '', (string)$phone);
        if (strlen($digits) === 12 && substr($digits, 0, 3) === '258') {
            $digits = substr($digits, 3);
        }
        return $digits;
    }
}

if (!function_exists('sige_aluno_360_is_valid_mz_phone')) {
    function sige_aluno_360_is_valid_mz_phone($phone) {
        $digits = sige_aluno_360_normalize_mz_phone($phone);
        return (bool)preg_match('/^(82|83|84|85|86|87)\d{7}$/', $digits);
    }
}

if (!function_exists('sige_aluno_360_age')) {
    function sige_aluno_360_age($date_value) {
        $raw = trim((string)$date_value);
        if ($raw === '') return null;
        try {
            $date = new DateTime(substr($raw, 0, 10));
            $now  = new DateTime(function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d'));
            if ($date > $now) return null;
            return (int)$date->diff($now)->y;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('sige_aluno_360_safe_datetime')) {
    function sige_aluno_360_safe_datetime($value) {
        $raw = trim((string)$value);
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') return '';
        return $raw;
    }
}

if (!function_exists('sige_aluno_360_text')) {
    /**
     * Normaliza texto devolvido pela Ficha 360º.
     * Mantém a camada AJAX em modo de minimização: sem HTML, sem payload longo
     * e sem conteúdo técnico desnecessário no navegador.
     */
    function sige_aluno_360_text($value, $max_len = 240) {
        if (is_array($value) || is_object($value)) return '';
        $text = (string)$value;
        $text = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($text) : strip_tags($text);
        $normalized = preg_replace('/\s+/u', ' ', trim($text));
        if ($normalized === null) {
            $normalized = preg_replace('/\s+/', ' ', trim($text));
        }
        $text = is_string($normalized) ? $normalized : '';
        if (function_exists('sanitize_text_field')) {
            $text = sanitize_text_field($text);
        }
        $max_len = (int)$max_len;
        if ($max_len > 0) {
            if (function_exists('mb_substr')) {
                $text = mb_substr($text, 0, $max_len, 'UTF-8');
            } else {
                $text = substr($text, 0, $max_len);
            }
        }
        return $text;
    }
}

if (!function_exists('sige_aluno_360_email')) {
    function sige_aluno_360_email($value) {
        $email = sige_aluno_360_text($value, 180);
        return function_exists('sanitize_email') ? sanitize_email($email) : $email;
    }
}

if (!function_exists('sige_aluno_360_date')) {
    function sige_aluno_360_date($value) {
        $date = substr(sige_aluno_360_text($value, 24), 0, 10);
        if ($date === '' || $date === '0000-00-00') return '';
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : sige_aluno_360_text($date, 10);
    }
}

if (!function_exists('sige_aluno_360_datetime')) {
    function sige_aluno_360_datetime($value) {
        $dt = sige_aluno_360_safe_datetime($value);
        return sige_aluno_360_text($dt, 24);
    }
}

if (!function_exists('sige_aluno_360_url')) {
    function sige_aluno_360_url($value) {
        $url = trim((string)$value);
        if ($url === '') return '';
        return function_exists('esc_url_raw') ? esc_url_raw($url) : filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sige_aluno_360_secure_document_link')) {
    /**
     * Devolve apenas links gerados pelo endpoint seguro de documentos.
     * Se a camada segura não estiver disponível, não expõe URL directo gravado na BD.
     */
    function sige_aluno_360_secure_document_link($aluno_id, $field, $raw_value) {
        $aluno_id = (int)$aluno_id;
        $field = function_exists('sanitize_key') ? sanitize_key((string)$field) : preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$field);
        if ($aluno_id <= 0 || $field === '' || !sige_aluno_360_has_value($raw_value)) return '';
        if (!function_exists('sige_secure_document_url')) return '';
        try {
            $url = sige_secure_document_url($aluno_id, $field);
        } catch (Throwable $e) {
            if (function_exists('error_log')) error_log('[SIGE Ficha 360] Falha ao gerar link documental seguro: ' . $e->getMessage());
            return '';
        }
        return sige_aluno_360_url($url);
    }
}

if (!function_exists('sige_aluno_360_bool')) {
    function sige_aluno_360_bool($value) {
        return (bool)$value;
    }
}

if (!function_exists('sige_aluno_360_quality_index')) {
    function sige_aluno_360_quality_index($aluno, $context = []) {
        $score = 0;
        $total = 0;
        $items = [];
        $pendencias = [];
        $forcas = [];

        $add = function($key, $label, $ok, $weight, $impact = 'medio', $hint = '') use (&$score, &$total, &$items, &$pendencias, &$forcas) {
            $weight = max(1, (int)$weight);
            $ok = (bool)$ok;
            $total += $weight;
            if ($ok) {
                $score += $weight;
                $forcas[] = $label;
            } else {
                $pendencias[] = [
                    'key' => (string)$key,
                    'label' => (string)$label,
                    'impacto' => (string)$impact,
                    'peso' => $weight,
                    'sugestao' => (string)$hint,
                ];
            }
            $items[] = [
                'key' => (string)$key,
                'label' => (string)$label,
                'ok' => $ok,
                'impacto' => (string)$impact,
                'peso' => $weight,
                'sugestao' => (string)$hint,
            ];
        };

        $nome       = sige_aluno_360_pick($aluno, 'nome_completo');
        $processo   = sige_aluno_360_pick($aluno, 'numero_processo');
        $nasc       = sige_aluno_360_pick($aluno, 'data_nascimento');
        $genero     = sige_aluno_360_pick($aluno, 'genero');
        $turma_id   = sige_aluno_360_pick($aluno, 'turma_id');
        $classe     = sige_aluno_360_pick($aluno, 'classe');
        $turma_nome = sige_aluno_360_pick($aluno, 'turma_nome');
        $status     = sige_aluno_360_pick($aluno, 'status');
        $pai        = sige_aluno_360_pick($aluno, 'nome_pai');
        $mae        = sige_aluno_360_pick($aluno, 'nome_mae');
        $tel_pai    = sige_aluno_360_pick($aluno, ['telemovel_pai', 'telefone_pai']);
        $tel_mae    = sige_aluno_360_pick($aluno, ['telemovel_mae', 'telefone_mae']);
        $whatsapp   = sige_aluno_360_pick($aluno, ['whatsapp_notificacoes', 'contacto_encarregado']);
        $principal_tipo = strtolower((string)sige_aluno_360_pick($aluno, 'encarregado_principal_tipo', 'pai_mae'));
        $principal_nome = sige_aluno_360_pick($aluno, 'encarregado_principal_nome');
        $principal_tel  = sige_aluno_360_pick($aluno, 'encarregado_principal_telemovel');
        $canal_pref = sige_aluno_360_pick($aluno, 'canal_preferencial_comunicacao');
        $aut_buscar_nome = sige_aluno_360_pick($aluno, 'autorizado_buscar_nome');
        $aut_buscar_doc  = sige_aluno_360_pick($aluno, 'autorizado_buscar_documento');
        $doc_tipo   = sige_aluno_360_pick($aluno, 'tipo_documento');
        $doc_nr     = sige_aluno_360_pick($aluno, ['documento_nr', 'documento_numero']);
        $doc_bi     = sige_aluno_360_pick($aluno, 'doc_bi_url');
        $doc_cert   = sige_aluno_360_pick($aluno, 'doc_cert_url');
        $foto       = sige_aluno_360_pick($aluno, 'foto');
        $bairro     = sige_aluno_360_pick($aluno, 'bairro');
        $nac        = sige_aluno_360_pick($aluno, 'nacionalidade');
        $emerg1     = sige_aluno_360_pick($aluno, 'contacto_emergencia_1');
        $emerg2     = sige_aluno_360_pick($aluno, 'contacto_emergencia_2');

        $valid_nascimento = false;
        if (sige_aluno_360_has_value($nasc)) {
            $ts = strtotime((string)$nasc);
            $valid_nascimento = ($ts !== false && $ts > 0 && $ts <= time());
        }
        $genero_ok = in_array(strtoupper(trim((string)$genero)), ['M','F'], true);
        $turma_ok = ((int)$turma_id > 0) || (sige_aluno_360_has_value($classe) && sige_aluno_360_has_value($turma_nome));
        if (!$turma_ok && !empty($context['turma_label']) && strtolower((string)$context['turma_label']) !== 'sem turma') {
            $turma_ok = true;
        }
        $status_ok = in_array(strtolower(trim((string)$status ?: 'activo')), ['activo','suspenso','transferido','desistente','concluido','arquivado'], true);
        $tel_pai_ok = sige_aluno_360_is_valid_mz_phone($tel_pai);
        $tel_mae_ok = sige_aluno_360_is_valid_mz_phone($tel_mae);
        $whatsapp_ok = sige_aluno_360_is_valid_mz_phone($whatsapp) || $tel_pai_ok || $tel_mae_ok || sige_aluno_360_is_valid_mz_phone($principal_tel);
        $principal_ok = in_array($principal_tipo, ['pai_mae','pai','mae'], true)
            || ($principal_tipo === 'outro' && sige_aluno_360_has_value($principal_nome) && sige_aluno_360_is_valid_mz_phone($principal_tel));
        $canal_ok = in_array(strtolower(trim((string)$canal_pref ?: 'whatsapp')), ['whatsapp','email','chamada','sms'], true);
        $autorizacao_busca_ok = sige_aluno_360_has_value($aut_buscar_nome) || sige_aluno_360_has_value($aut_buscar_doc);
        $doc_id_ok = sige_aluno_360_has_value($doc_tipo) && sige_aluno_360_has_value($doc_nr);
        $doc_digital_ok = sige_aluno_360_has_value($doc_bi) || sige_aluno_360_has_value($doc_cert);
        $morada_ok = sige_aluno_360_has_value($bairro) || sige_aluno_360_has_value($nac);
        $emergencia_ok = sige_aluno_360_has_value($emerg1) || sige_aluno_360_has_value($emerg2);

        $add('nome', 'Nome completo registado', sige_aluno_360_has_value($nome), 8, 'alto', 'Preencher o nome completo oficial do aluno.');
        $add('processo', 'Número de processo disponível', sige_aluno_360_has_value($processo), 7, 'alto', 'Gerar ou preencher o número de processo para identificação inequívoca.');
        $add('data_nascimento', 'Data de nascimento válida', $valid_nascimento, 8, 'alto', 'Corrigir a data de nascimento; ela deve ser real e não futura.');
        $add('genero', 'Género preenchido em formato válido', $genero_ok, 6, 'medio', 'Seleccionar Masculino ou Feminino conforme o cadastro escolar.');
        $add('turma', 'Turma/matrícula associada ao ano lectivo', $turma_ok, 12, 'alto', 'Associar o aluno a uma turma do ano lectivo activo.');
        $add('status', 'Situação do aluno definida', $status_ok, 5, 'medio', 'Definir se o aluno está activo, suspenso, transferido ou desistente.');
        $add('nome_pai', 'Nome do pai preenchido', sige_aluno_360_has_value($pai), 6, 'alto', 'Completar o nome do pai para comunicação e identificação familiar.');
        $add('telemovel_pai', 'Telemóvel do pai válido', $tel_pai_ok, 7, 'alto', 'Usar número moçambicano com 9 dígitos iniciado por 82, 83, 84, 85, 86 ou 87.');
        $add('nome_mae', 'Nome da mãe preenchido', sige_aluno_360_has_value($mae), 6, 'alto', 'Completar o nome da mãe para comunicação e identificação familiar.');
        $add('telemovel_mae', 'Telemóvel da mãe válido', $tel_mae_ok, 7, 'alto', 'Usar número moçambicano com 9 dígitos iniciado por 82, 83, 84, 85, 86 ou 87.');
        $add('whatsapp', 'Contacto principal/WhatsApp operacional', $whatsapp_ok, 9, 'alto', 'Definir um contacto de WhatsApp válido ou assegurar que pai/mãe têm telemóvel válido.');
        $add('encarregado_principal', 'Encarregado principal definido', $principal_ok, 5, 'medio', 'Definir se a comunicação principal é com pai, mãe ou outro encarregado identificado.');
        $add('canal_preferencial', 'Canal preferencial de comunicação definido', $canal_ok, 3, 'baixo', 'Seleccionar WhatsApp, e-mail, chamada ou SMS conforme a preferência do encarregado.');
        $add('autorizacao_busca', 'Pessoa autorizada a buscar identificada', $autorizacao_busca_ok, 3, 'medio', 'Registar pelo menos uma pessoa autorizada a buscar o aluno em caso de necessidade.');
        $add('documento_identificacao', 'Documento de identificação preenchido', $doc_id_ok, 6, 'medio', 'Preencher tipo e número do documento do aluno.');
        $add('documento_digital', 'Documento digital essencial carregado', $doc_digital_ok, 6, 'medio', 'Carregar BI/Cédula ou Certidão de Nascimento no arquivo digital.');
        $add('foto', 'Foto do aluno carregada', sige_aluno_360_has_value($foto), 5, 'baixo', 'Adicionar foto para cartões, portaria e identificação visual.');
        $add('morada_nacionalidade', 'Dados básicos de morada/nacionalidade', $morada_ok, 4, 'baixo', 'Preencher bairro e/ou nacionalidade para completar a ficha administrativa.');
        $add('emergencia', 'Contacto de emergência disponível', $emergencia_ok, 4, 'medio', 'Preencher contacto de emergência para gestão de incidentes escolares.');

        $percent = $total > 0 ? (int)round(($score / $total) * 100) : 0;
        if ($percent >= 85) {
            $class = 'completa';
            $label = 'Completa';
            $message = 'A ficha tem boa qualidade operacional. Manter actualizada ao longo do ano lectivo.';
        } elseif ($percent >= 65) {
            $class = 'pendente';
            $label = 'Com pendências';
            $message = 'A ficha pode ser usada, mas ainda tem dados que devem ser completados.';
        } else {
            $class = 'critica';
            $label = 'Crítica';
            $message = 'A ficha precisa de correcção prioritária antes de comunicações e processos sensíveis.';
        }

        usort($pendencias, function($a, $b) {
            $ord = ['alto' => 3, 'medio' => 2, 'baixo' => 1];
            $ia = $ord[$a['impacto']] ?? 0;
            $ib = $ord[$b['impacto']] ?? 0;
            if ($ia === $ib) return ($b['peso'] ?? 0) <=> ($a['peso'] ?? 0);
            return $ib <=> $ia;
        });

        return [
            'score' => $percent,
            'label' => $label,
            'class' => $class,
            'message' => $message,
            'pendencias' => array_slice($pendencias, 0, 12),
            'forcas' => array_slice($forcas, 0, 10),
            'items' => $items,
            'total_pendencias' => count($pendencias),
        ];
    }
}

function sige_ajax_get_aluno_360() {
    try {
        sige_ajax_get_aluno_360_core();
    } catch (Throwable $e) {
        if (function_exists('error_log')) {
            error_log('[SIGE Ficha 360] Erro ao carregar aluno: ' . $e->getMessage());
        }
        wp_send_json_error('Não foi possível carregar a ficha 360º neste momento. A ocorrência foi registada para verificação técnica.');
    }
}

function sige_ajax_get_aluno_360_core() {
    sige_check_nonce_global();
    if (!sige_alunos_user_can_view()) {
        wp_send_json_error('Sem permissão para consultar a ficha 360º do aluno.');
    }

    $aluno_id = isset($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;
    if ($aluno_id <= 0) {
        wp_send_json_error('ID de aluno inválido.');
    }

    global $wpdb;
    $escola_id   = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $ano_lectivo = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (function_exists('sige_get_ano_lectivo') ? (int)sige_get_ano_lectivo() : (int)wp_date('Y'));
    $is_guarda_360 = function_exists('sige_alunos_user_is_guarda') && sige_alunos_user_is_guarda();
    $can_view_finance_360 = function_exists('sige_alunos_user_can_view_finance') ? sige_alunos_user_can_view_finance() : !$is_guarda_360;
    $can_view_docs_360 = function_exists('sige_alunos_user_can_view_documents') ? sige_alunos_user_can_view_documents() : !$is_guarda_360;
    $can_emit_docs_360 = function_exists('sige_alunos_user_can_emit_documents') ? sige_alunos_user_can_emit_documents() : false;
    $can_view_academic_360 = function_exists('sige_alunos_user_can_view_academic_summary') ? sige_alunos_user_can_view_academic_summary() : !$is_guarda_360;
    $can_view_comunicacoes_360 = function_exists('sige_alunos_user_can_view_communications') ? sige_alunos_user_can_view_communications() : !$is_guarda_360;
    $can_view_sensitive_360 = function_exists('sige_alunos_user_can_view_sensitive_student_details') ? sige_alunos_user_can_view_sensitive_student_details() : !$is_guarda_360;

    $tbl_alunos     = $wpdb->prefix . 'sige_alunos';
    $tbl_matriculas = $wpdb->prefix . 'sige_matriculas';
    $tbl_turmas     = $wpdb->prefix . 'sige_turmas';
    $tbl_rotas      = function_exists('sige_alunos_ajax_resolve_rotas_table') ? sige_alunos_ajax_resolve_rotas_table() : ($wpdb->prefix . 'sige_transporte_rotas');
    if (!sige_aluno_360_table_exists($tbl_alunos)) {
        wp_send_json_error('Tabela de alunos não encontrada.');
    }

    $rota_parts = function_exists('sige_alunos_ajax_rota_join_parts') ? sige_alunos_ajax_rota_join_parts($tbl_alunos, 'a') : ['join' => '', 'select' => ''];
    $join_rota = $rota_parts['join'];
    $select_rota = $rota_parts['select'];

    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT a.*, t.nome AS turma_nome, t.classe, m.turma_id, m.id AS matricula_id {$select_rota}
         FROM {$tbl_alunos} a
         LEFT JOIN {$tbl_matriculas} m ON (a.id = m.aluno_id AND m.ano_lectivo = %d AND m.escola_id = a.escola_id)
         LEFT JOIN {$tbl_turmas} t ON m.turma_id = t.id AND t.escola_id = a.escola_id
         {$join_rota}
         WHERE a.id = %d AND a.escola_id = %d
         LIMIT 1",
        $ano_lectivo, $aluno_id, $escola_id
    ));

    if (!$aluno) {
        wp_send_json_error('Aluno não encontrado ou fora do escopo da escola activa.');
    }

    $foto = sige_aluno_360_pick($aluno, 'foto');
    if (!sige_aluno_360_has_value($foto) && defined('SIGE_URL')) {
        $foto = SIGE_URL . 'assets/img/avatar-default.svg';
    }

    $doc_bi_raw = sige_aluno_360_pick($aluno, 'doc_bi_url');
    $doc_cert_raw = sige_aluno_360_pick($aluno, 'doc_cert_url');
    $doc_vacina_raw = sige_aluno_360_pick($aluno, 'doc_vacina_url');
    $doc_bi_url = $can_view_docs_360 ? sige_aluno_360_secure_document_link((int)$aluno->id, 'doc_bi_url', $doc_bi_raw) : '';
    $doc_cert_url = $can_view_docs_360 ? sige_aluno_360_secure_document_link((int)$aluno->id, 'doc_cert_url', $doc_cert_raw) : '';
    $doc_vacina_url = $can_view_docs_360 ? sige_aluno_360_secure_document_link((int)$aluno->id, 'doc_vacina_url', $doc_vacina_raw) : '';

    $turma_label = 'Sem turma';
    if (sige_aluno_360_has_value(sige_aluno_360_pick($aluno, 'classe')) || sige_aluno_360_has_value(sige_aluno_360_pick($aluno, 'turma_nome'))) {
        $turma_label = trim((string)sige_aluno_360_pick($aluno, 'classe') . ' - ' . (string)sige_aluno_360_pick($aluno, 'turma_nome'), ' -');
    }

    $qualidade = sige_aluno_360_quality_index($aluno, [
        'turma_label' => $turma_label,
        'turma_id' => (int)sige_aluno_360_pick($aluno, 'turma_id', 0),
    ]);

    $matriculas = [];
    if (($can_view_sensitive_360 || $can_view_academic_360) && sige_aluno_360_table_exists($tbl_matriculas) && sige_aluno_360_table_exists($tbl_turmas)) {
        $mat_status_expr = sige_aluno_360_column_exists($tbl_matriculas, 'status') ? "COALESCE(m.status,'')" : "''";
        $matriculas_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.ano_lectivo, m.turma_id, {$mat_status_expr} AS status, t.nome AS turma_nome, t.classe
             FROM {$tbl_matriculas} m
             LEFT JOIN {$tbl_turmas} t ON m.turma_id = t.id AND t.escola_id = m.escola_id
             WHERE m.escola_id = %d AND m.aluno_id = %d
             ORDER BY m.ano_lectivo DESC, m.id DESC
             LIMIT 6",
            $escola_id, $aluno_id
        ));
        foreach ((array)$matriculas_rows as $m) {
            $matriculas[] = [
                'id' => (int)$m->id,
                'ano_lectivo' => (int)$m->ano_lectivo,
                'turma_id' => (int)$m->turma_id,
                'status' => sige_aluno_360_text($m->status, 40),
                'turma_nome' => sige_aluno_360_text($m->turma_nome, 120),
                'classe' => sige_aluno_360_text($m->classe, 80),
            ];
        }
    }

    $financeiro = [
        'disponivel' => false,
        'oculto_por_permissao' => !$can_view_finance_360,
        'divida_aberta' => 0.0,
        'em_plano' => 0.0,
        'credito' => 0.0,
        'saldo_vencido' => 0.0,
        'pagamentos_total' => 0,
        'status_operacional' => 'Indisponível',
        'lancamentos_abertos' => 0,
        'lancamentos_pagos' => 0,
        'total_pago' => 0.0,
        'total_estornado' => 0.0,
        'ultimo_pagamento' => null,
        'proximo_vencimento' => null,
        'pagamentos_recentes' => [],
        'historico_url' => '',
        'historico_csv_url' => '',
        'historico_xlsx_url' => '',
        'extracto_url' => '',
    ];
    $tbl_lanc = $wpdb->prefix . 'sige_fin_lancamentos';
    if ($can_view_finance_360 && sige_aluno_360_table_exists($tbl_lanc)) {
        $financeiro['disponivel'] = true;
        $saldo_expr = function_exists('sige_fin_saldo_sql')
            ? sige_fin_saldo_sql('l')
            : "GREATEST(COALESCE(l.valor_original,0)+COALESCE(l.valor_transporte,0)+COALESCE(l.valor_extras,0)+COALESCE(NULLIF(l.valor_multa_cobrada,0),l.valor_multa,0)-COALESCE(l.valor_desconto,0)-COALESCE(l.valor_desconto_especial,0)-COALESCE(l.valor_pago,0),0)";
        $fin = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(CASE WHEN LOWER(l.status) IN ('pendente','parcial') THEN {$saldo_expr} ELSE 0 END) AS divida_aberta,
                SUM(CASE WHEN LOWER(l.status) = 'em_plano' THEN {$saldo_expr} ELSE 0 END) AS em_plano,
                SUM(CASE WHEN LOWER(l.status) IN ('pendente','parcial','em_plano') THEN 1 ELSE 0 END) AS lancamentos_abertos,
                SUM(CASE WHEN LOWER(l.status) = 'pago' THEN 1 ELSE 0 END) AS lancamentos_pagos
             FROM {$tbl_lanc} l
             WHERE l.escola_id = %d AND l.aluno_id = %d",
            $escola_id, $aluno_id
        ));
        if ($fin) {
            $financeiro['divida_aberta'] = max(0, (float)$fin->divida_aberta);
            $financeiro['em_plano'] = max(0, (float)$fin->em_plano);
            $financeiro['lancamentos_abertos'] = (int)$fin->lancamentos_abertos;
            $financeiro['lancamentos_pagos'] = (int)$fin->lancamentos_pagos;
        }
        $tbl_creditos = $wpdb->prefix . 'sige_fin_creditos';
        if (sige_aluno_360_table_exists($tbl_creditos)) {
            $financeiro['credito'] = (float)$wpdb->get_var($wpdb->prepare(
                "SELECT SUM(GREATEST(COALESCE(valor_disponivel,0),0))
                 FROM {$tbl_creditos}
                 WHERE escola_id = %d AND aluno_id = %d
                   AND GREATEST(COALESCE(valor_disponivel,0),0) > 0
                   AND LOWER(COALESCE(status,'')) NOT IN ('cancelado','anulado','usado','esgotado')",
                $escola_id, $aluno_id
            ));
        }

        $tbl_pag = $wpdb->prefix . 'sige_fin_pagamentos';
        if (sige_aluno_360_table_exists($tbl_pag)) {
            $fin_pag = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    COUNT(*) AS pagamentos_total,
                    SUM(CASE WHEN COALESCE(valor_pago,0) > 0 AND LOWER(COALESCE(metodo_pagamento,'')) <> 'estorno' THEN COALESCE(valor_pago,0) ELSE 0 END) AS total_pago,
                    SUM(CASE WHEN COALESCE(valor_pago,0) < 0 OR LOWER(COALESCE(metodo_pagamento,'')) = 'estorno' THEN ABS(COALESCE(valor_pago,0)) ELSE 0 END) AS total_estornado
                 FROM {$tbl_pag}
                 WHERE escola_id = %d AND aluno_id = %d",
                $escola_id, $aluno_id
            ));
            if ($fin_pag) {
                $financeiro['pagamentos_total'] = (int)$fin_pag->pagamentos_total;
                $financeiro['total_pago'] = max(0, (float)$fin_pag->total_pago);
                $financeiro['total_estornado'] = max(0, (float)$fin_pag->total_estornado);
            }
            $ultimo = $wpdb->get_row($wpdb->prepare(
                "SELECT data_pagamento, valor_pago, recibo_numero, metodo_pagamento
                 FROM {$tbl_pag}
                 WHERE escola_id = %d AND aluno_id = %d
                   AND COALESCE(valor_pago,0) > 0
                   AND LOWER(COALESCE(metodo_pagamento,'')) <> 'estorno'
                 ORDER BY data_pagamento DESC, id DESC
                 LIMIT 1",
                $escola_id, $aluno_id
            ));
            if ($ultimo) {
                $financeiro['ultimo_pagamento'] = [
                    'data' => sige_aluno_360_datetime($ultimo->data_pagamento),
                    'valor' => (float)$ultimo->valor_pago,
                    'recibo' => sige_aluno_360_text($ultimo->recibo_numero, 80),
                    'metodo' => sige_aluno_360_text(function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($ultimo->metodo_pagamento) : $ultimo->metodo_pagamento, 80),
                ];
            }
        }

        $hoje_fin360 = function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d');
        $prox = $wpdb->get_row($wpdb->prepare(
            "SELECT l.data_vencimento, l.descricao, l.mes_referencia, {$saldo_expr} AS saldo
             FROM {$tbl_lanc} l
             WHERE l.escola_id = %d AND l.aluno_id = %d
               AND LOWER(l.status) IN ('pendente','parcial','em_plano')
               AND {$saldo_expr} > 0
               AND COALESCE(l.data_vencimento,'9999-12-31') >= %s
             ORDER BY COALESCE(l.data_vencimento,'9999-12-31') ASC, l.id ASC
             LIMIT 1",
            $escola_id, $aluno_id, $hoje_fin360
        ));
        if ($prox) {
            $financeiro['proximo_vencimento'] = [
                'data' => sige_aluno_360_date($prox->data_vencimento),
                'saldo' => (float)$prox->saldo,
                'descricao' => sige_aluno_360_text($prox->descricao, 120),
                'mes_referencia' => sige_aluno_360_text($prox->mes_referencia, 40),
            ];
        }

        $saldo_vencido_360 = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM({$saldo_expr})
             FROM {$tbl_lanc} l
             WHERE l.escola_id = %d AND l.aluno_id = %d
               AND LOWER(l.status) IN ('pendente','parcial','em_plano')
               AND {$saldo_expr} > 0
               AND COALESCE(l.data_vencimento,'9999-12-31') < %s",
            $escola_id, $aluno_id, $hoje_fin360
        ));
        $financeiro['saldo_vencido'] = max(0, (float)$saldo_vencido_360);
        $divida_operacional_360 = max(0, (float)$financeiro['divida_aberta'] + (float)$financeiro['em_plano']);
        if ((float)$financeiro['saldo_vencido'] > 0.005) {
            $financeiro['status_operacional'] = 'Com valores vencidos';
        } elseif ($divida_operacional_360 > 0.005) {
            $financeiro['status_operacional'] = 'Com valores em aberto';
        } else {
            $financeiro['status_operacional'] = 'Regularizado';
        }

        $financeiro['extracto_url'] = esc_url_raw(admin_url('admin.php?page=sige-app&view=financeiro-extratos&modo=aluno&aluno_id=' . (int)$aluno_id));
        if (function_exists('sige_fin_hist_aluno_build_url')) {
            $financeiro['historico_url'] = esc_url_raw(sige_fin_hist_aluno_build_url((int)$aluno_id, 'html'));
            $financeiro['historico_csv_url'] = '';
            $financeiro['historico_xlsx_url'] = esc_url_raw(sige_fin_hist_aluno_build_url((int)$aluno_id, 'xlsx'));
        } else {
            $hist_url = wp_nonce_url(admin_url('admin.php?sige_print=historico_financeiro_aluno&id=' . (int)$aluno_id), 'sige_hist_fin_aluno_' . (int)$aluno_id);
            $financeiro['historico_url'] = esc_url_raw($hist_url);
            $financeiro['historico_csv_url'] = '';
            $financeiro['historico_xlsx_url'] = esc_url_raw(add_query_arg('formato', 'xlsx', $hist_url));
        }
    }
    // Minimização de dados: a Ficha 360º mostra apenas o resumo financeiro.
    // Detalhes de pagamentos/recibos continuam disponíveis no módulo financeiro, com permissões próprias.

    $academico = [
        'disponivel' => false,
        'oculto_por_permissao' => !$can_view_academic_360,
        'total_notas' => 0,
        'disciplinas_com_notas' => 0,
        'por_trimestre' => [],
    ];
    $tbl_notas = $wpdb->prefix . 'sige_notas';
    if ($can_view_academic_360 && sige_aluno_360_table_exists($tbl_notas)) {
        $academico['disponivel'] = true;
        $nota_status_expr = sige_aluno_360_column_exists($tbl_notas, 'status') ? "LOWER(COALESCE(status,'')) = 'aprovado'" : "0=1";
        $academico['total_notas'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_notas} WHERE escola_id = %d AND aluno_id = %d AND ano_lectivo = %d",
            $escola_id, $aluno_id, $ano_lectivo
        ));
        $academico['disciplinas_com_notas'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT disciplina_id) FROM {$tbl_notas} WHERE escola_id = %d AND aluno_id = %d AND ano_lectivo = %d",
            $escola_id, $aluno_id, $ano_lectivo
        ));
        $trimestres_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT trimestre, COUNT(*) AS total, SUM(CASE WHEN {$nota_status_expr} THEN 1 ELSE 0 END) AS aprovadas
             FROM {$tbl_notas}
             WHERE escola_id = %d AND aluno_id = %d AND ano_lectivo = %d
             GROUP BY trimestre
             ORDER BY trimestre ASC",
            $escola_id, $aluno_id, $ano_lectivo
        ));
        foreach ((array)$trimestres_rows as $t) {
            $academico['por_trimestre'][] = [
                'trimestre' => (int)$t->trimestre,
                'total' => (int)$t->total,
                'aprovadas' => (int)$t->aprovadas,
            ];
        }
    }

    $portaria = [
        'disponivel' => false,
        'total_acessos' => 0,
        'ultimos' => [],
    ];
    $tbl_acessos = $wpdb->prefix . 'sige_acessos';
    if (sige_aluno_360_table_exists($tbl_acessos)) {
        $portaria['disponivel'] = true;
        $portaria['total_acessos'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_acessos} WHERE escola_id = %d AND aluno_id = %d",
            $escola_id, $aluno_id
        ));
        if (sige_aluno_360_column_exists($tbl_acessos, 'data_hora') && sige_aluno_360_column_exists($tbl_acessos, 'tipo') && sige_aluno_360_column_exists($tbl_acessos, 'status_no_momento')) {
            $acessos_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT data_hora, tipo, status_no_momento
                 FROM {$tbl_acessos}
                 WHERE escola_id = %d AND aluno_id = %d
                 ORDER BY data_hora DESC, id DESC
                 LIMIT 5",
                $escola_id, $aluno_id
            ));
            foreach ((array)$acessos_rows as $a) {
                $portaria['ultimos'][] = [
                    'data_hora' => sige_aluno_360_datetime(isset($a->data_hora) ? $a->data_hora : ''),
                    'tipo' => sige_aluno_360_text(isset($a->tipo) ? $a->tipo : '', 40),
                    'status_no_momento' => sige_aluno_360_text(isset($a->status_no_momento) ? $a->status_no_momento : '', 60),
                ];
            }
        }
    }

    $comunicacoes = [
        'disponivel' => false,
        'oculto_por_permissao' => !$can_view_comunicacoes_360,
        'total' => 0,
        'pendentes' => 0,
        'enviadas' => 0,
        'falhadas' => 0,
        'ultimas' => [],
    ];
    $tbl_wpp = $wpdb->prefix . 'sige_whatsapp_queue';
    if ($can_view_comunicacoes_360 && sige_aluno_360_table_exists($tbl_wpp) && sige_aluno_360_column_exists($tbl_wpp, 'aluno_id')) {
        $comunicacoes['disponivel'] = true;
        $wpp_status_expr = sige_aluno_360_column_exists($tbl_wpp, 'status') ? "LOWER(COALESCE(status,''))" : "''";
        $wpp = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN {$wpp_status_expr} = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
                    SUM(CASE WHEN {$wpp_status_expr} IN ('enviado','sent','sucesso') THEN 1 ELSE 0 END) AS enviadas,
                    SUM(CASE WHEN {$wpp_status_expr} IN ('erro','falhou','failed') THEN 1 ELSE 0 END) AS falhadas
             FROM {$tbl_wpp}
             WHERE escola_id = %d AND aluno_id = %d",
            $escola_id, $aluno_id
        ));
        if ($wpp) {
            $comunicacoes['total'] = (int)$wpp->total;
            $comunicacoes['pendentes'] = (int)$wpp->pendentes;
            $comunicacoes['enviadas'] = (int)$wpp->enviadas;
            $comunicacoes['falhadas'] = (int)$wpp->falhadas;
        }
        // Minimização de dados: não expõe no navegador telefone de destino nem erro técnico da fila WhatsApp.
        // O resumo operacional abaixo é suficiente para a Ficha 360º; detalhes ficam na Central WhatsApp.
        $comunicacoes['ultimas'] = [];
    }

    $encarregados_historico = [];
    $tbl_enc_hist = $wpdb->prefix . 'sige_alunos_encarregados_historico';
    if ($can_view_sensitive_360 && sige_aluno_360_table_exists($tbl_enc_hist)) {
        $hist_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, evento, campos_alterados, total_alteracoes, resumo, alteracoes_json, criado_em, criado_por, user_display
             FROM {$tbl_enc_hist}
             WHERE escola_id = %d AND aluno_id = %d
             ORDER BY criado_em DESC, id DESC
             LIMIT 8",
            $escola_id,
            $aluno_id
        ));
        foreach ((array)$hist_rows as $h) {
            $alteracoes_raw = json_decode((string)($h->alteracoes_json ?? ''), true);
            $alteracoes = [];
            if (is_array($alteracoes_raw)) {
                foreach (array_slice($alteracoes_raw, 0, 8) as $chg) {
                    if (!is_array($chg)) continue;
                    $alteracoes[] = [
                        'label' => sige_aluno_360_text($chg['label'] ?? 'Campo', 120),
                        'antes' => sige_aluno_360_text($chg['antes'] ?? '-', 120),
                        'depois' => sige_aluno_360_text($chg['depois'] ?? '-', 120),
                    ];
                }
            }
            $encarregados_historico[] = [
                'id' => (int)$h->id,
                'evento' => sige_aluno_360_text($h->evento ?? '', 80),
                'campos_alterados' => sige_aluno_360_text($h->campos_alterados ?? '', 240),
                'total_alteracoes' => (int)($h->total_alteracoes ?? count($alteracoes)),
                'resumo' => sige_aluno_360_text($h->resumo ?? '', 220),
                'alteracoes' => $alteracoes,
                'criado_em' => sige_aluno_360_datetime($h->criado_em ?? ''),
                'criado_por' => (int)($h->criado_por ?? 0),
                'user_display' => sige_aluno_360_text($h->user_display ?? '', 140),
            ];
        }
    }

    $documentos = [];
    if ($can_view_docs_360) {
        $documentos = [
            ['key' => 'foto', 'label' => 'Foto do aluno', 'ok' => sige_aluno_360_has_value(sige_aluno_360_pick($aluno, 'foto')), 'url' => ''],
            ['key' => 'doc_bi', 'label' => 'BI / Cédula', 'ok' => sige_aluno_360_has_value($doc_bi_raw), 'url' => $doc_bi_url],
            ['key' => 'doc_cert', 'label' => 'Certidão de nascimento', 'ok' => sige_aluno_360_has_value($doc_cert_raw), 'url' => $doc_cert_url],
            ['key' => 'doc_vacina', 'label' => 'Boletim de vacinação', 'ok' => sige_aluno_360_has_value($doc_vacina_raw), 'url' => $doc_vacina_url],
        ];
    }

    $payload = [
        'scope' => [
            'guard_readonly' => $is_guarda_360,
            'can_view_documents' => $can_view_docs_360,
            'can_emit_documents' => $can_emit_docs_360,
            'can_view_finance' => $can_view_finance_360,
            'can_view_academic' => $can_view_academic_360,
            'can_view_communications' => $can_view_comunicacoes_360,
            'can_view_sensitive' => $can_view_sensitive_360,
        ],
        'permissoes' => [
            'guarda' => $is_guarda_360,
            'documentos' => $can_view_docs_360,
            'documentos_emitir' => $can_emit_docs_360,
            'financeiro' => $can_view_finance_360,
            'academico' => $can_view_academic_360,
            'comunicacoes' => $can_view_comunicacoes_360,
            'sensivel' => $can_view_sensitive_360,
            'dados_sensiveis' => $can_view_sensitive_360,
        ],
        'aluno' => [
            'id' => (int)$aluno->id,
            'nome' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'nome_completo'), 140),
            'processo' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'numero_processo'), 60),
            'foto' => sige_aluno_360_url($foto),
            'genero' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'genero'), 20),
            'data_nascimento' => sige_aluno_360_date(sige_aluno_360_pick($aluno, 'data_nascimento')),
            'idade' => sige_aluno_360_age(sige_aluno_360_pick($aluno, 'data_nascimento')),
            'status' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'status', 'activo'), 40),
            'turma' => sige_aluno_360_text($turma_label, 160),
            'turma_id' => (int)sige_aluno_360_pick($aluno, 'turma_id', 0),
            'classe' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'classe'), 80),
            'nacionalidade' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'nacionalidade'), 80),
            'bairro' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'bairro'), 120),
            'tipo_documento' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'tipo_documento'), 80),
            'documento_nr' => sige_aluno_360_text(sige_aluno_360_pick($aluno, ['documento_nr','documento_numero']), 80),
            'nuit_encarregado' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'nuit_encarregado'), 80),
            'data_registo' => sige_aluno_360_datetime(sige_aluno_360_pick($aluno, ['data_registo','created_at'])),
            'importacao_lote_id' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'importacao_lote_id'), 80),
        ],
        'qualidade' => $qualidade,
        'encarregados' => [
            'pai' => [
                'nome' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'nome_pai'), 140),
                'profissao' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'profissao_pai'), 120),
                'telemovel' => sige_aluno_360_text(sige_aluno_360_pick($aluno, ['telemovel_pai','telefone_pai']), 40),
                'telemovel_valido' => sige_aluno_360_is_valid_mz_phone(sige_aluno_360_pick($aluno, ['telemovel_pai','telefone_pai'])),
                'email' => sige_aluno_360_email(sige_aluno_360_pick($aluno, 'email_pai')),
            ],
            'mae' => [
                'nome' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'nome_mae'), 140),
                'profissao' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'profissao_mae'), 120),
                'telemovel' => sige_aluno_360_text(sige_aluno_360_pick($aluno, ['telemovel_mae','telefone_mae']), 40),
                'telemovel_valido' => sige_aluno_360_is_valid_mz_phone(sige_aluno_360_pick($aluno, ['telemovel_mae','telefone_mae'])),
                'email' => sige_aluno_360_email(sige_aluno_360_pick($aluno, 'email_mae')),
            ],
            'principal' => [
                'nome' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'encarregado_nome'), 140),
                'contacto' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'contacto_encarregado'), 40),
                'whatsapp' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'whatsapp_notificacoes'), 40),
                'whatsapp_valido' => sige_aluno_360_is_valid_mz_phone(sige_aluno_360_pick($aluno, ['whatsapp_notificacoes','contacto_encarregado'])),
                'email' => sige_aluno_360_email(sige_aluno_360_pick($aluno, 'email_encarregado')),
            ],
            'avancado' => function_exists('sige_guardian_adv_payload') ? sige_guardian_adv_payload($aluno) : [],
        ],
        'saude' => [
            'grupo_sanguineo' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'grupo_sanguineo'), 40),
            'alergias' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'alergias'), 280),
            'condicoes_medicas' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'condicoes_medicas'), 280),
            'hospital_preferencia' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'hospital_preferencia'), 180),
            'contacto_emergencia_1' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'contacto_emergencia_1'), 80),
            'contacto_emergencia_2' => sige_aluno_360_text(sige_aluno_360_pick($aluno, 'contacto_emergencia_2'), 80),
        ],
        'documentos' => $documentos,
        'matriculas' => $matriculas,
        'financeiro' => $financeiro,
        'academico' => $academico,
        'portaria' => $portaria,
        'comunicacoes' => $comunicacoes,
        'encarregados_historico' => $encarregados_historico,
        'ano_lectivo' => (int)$ano_lectivo,
    ];

    if (!$can_view_docs_360) {
        // v12.11.9.69 - minimização documental para qualquer perfil sem escopo documental, não apenas Guarda.
        $payload['aluno']['tipo_documento'] = '';
        $payload['aluno']['documento_nr'] = '';
        $payload['aluno']['nuit_encarregado'] = '';
        $docs_limited = [];
        foreach ((array)$payload['documentos'] as $doc) {
            $docs_limited[] = [
                'key' => isset($doc['key']) ? $doc['key'] : '',
                'label' => isset($doc['label']) ? $doc['label'] : '',
                'ok' => false,
                'url' => '',
            ];
        }
        $payload['documentos'] = $docs_limited;
    }

    if (!$can_view_sensitive_360) {
        // v12.11.9.69 - minimização server-side: perfis de consulta simples não recebem dados familiares, morada, saúde ou auditoria.
        foreach (['genero','data_nascimento','idade','nacionalidade','bairro','tipo_documento','documento_nr','nuit_encarregado','data_registo','importacao_lote_id'] as $field) {
            if (array_key_exists($field, $payload['aluno'])) {
                $payload['aluno'][$field] = ($field === 'idade') ? null : '';
            }
        }
        $payload['qualidade'] = [
            'score' => 0,
            'label' => 'Modo consulta',
            'class' => 'pendente',
            'message' => 'Este perfil consulta apenas dados operacionais do aluno.',
            'pendencias' => [],
            'forcas' => [],
            'items' => [],
            'total_pendencias' => 0,
        ];
        $payload['encarregados'] = ['pai' => [], 'mae' => [], 'principal' => [], 'avancado' => []];
        $payload['saude'] = [
            'grupo_sanguineo' => '',
            'alergias' => '',
            'condicoes_medicas' => '',
            'hospital_preferencia' => '',
            'contacto_emergencia_1' => '',
            'contacto_emergencia_2' => '',
        ];
        $payload['encarregados_historico'] = [];
    }

    if ($is_guarda_360) {
        // v12.11.9.69 - Guarda/Portaria: payload estritamente operacional para crachá/identificação.
        $payload['scope'] = [
            'guard_readonly' => true,
            'can_view_documents' => false,
            'can_emit_documents' => false,
            'can_view_finance' => false,
            'can_view_academic' => false,
            'can_view_communications' => false,
            'can_view_sensitive' => false,
        ];
        $payload['permissoes'] = [
            'guarda' => true,
            'documentos' => false,
            'documentos_emitir' => false,
            'financeiro' => false,
            'academico' => false,
            'comunicacoes' => false,
            'sensivel' => false,
            'dados_sensiveis' => false,
        ];
        foreach (['genero','data_nascimento','idade','nacionalidade','bairro','tipo_documento','documento_nr','nuit_encarregado','data_registo','importacao_lote_id'] as $field) {
            if (array_key_exists($field, $payload['aluno'])) {
                $payload['aluno'][$field] = ($field === 'idade') ? null : '';
            }
        }
        $payload['qualidade'] = [
            'score' => 0,
            'label' => 'Modo portaria',
            'class' => 'pendente',
            'message' => 'O perfil Guarda valida crachás e confirma apenas identificação, turma, estado e histórico de portaria.',
            'pendencias' => [],
            'forcas' => [],
            'items' => [],
            'total_pendencias' => 0,
        ];
        $payload['encarregados'] = ['pai' => [], 'mae' => [], 'principal' => [], 'avancado' => []];
        $payload['saude'] = [
            'grupo_sanguineo' => '',
            'alergias' => '',
            'condicoes_medicas' => '',
            'hospital_preferencia' => '',
            'contacto_emergencia_1' => '',
            'contacto_emergencia_2' => '',
        ];
        $payload['documentos'] = [];
        $payload['matriculas'] = [];
        $payload['financeiro'] = [
            'disponivel' => false,
            'oculto_por_permissao' => true,
            'divida_aberta' => 0.0,
            'em_plano' => 0.0,
            'credito' => 0.0,
            'saldo_vencido' => 0.0,
            'pagamentos_total' => 0,
            'status_operacional' => 'Oculto',
            'lancamentos_abertos' => 0,
            'lancamentos_pagos' => 0,
            'total_pago' => 0.0,
            'total_estornado' => 0.0,
            'ultimo_pagamento' => null,
            'proximo_vencimento' => null,
            'pagamentos_recentes' => [],
            'historico_url' => '',
            'historico_csv_url' => '',
        'historico_xlsx_url' => '',
            'extracto_url' => '',
        ];
        $payload['academico'] = [
            'disponivel' => false,
            'oculto_por_permissao' => true,
            'total_notas' => 0,
            'disciplinas_com_notas' => 0,
            'por_trimestre' => [],
        ];
        $payload['comunicacoes'] = [
            'disponivel' => false,
            'oculto_por_permissao' => true,
            'total' => 0,
            'pendentes' => 0,
            'enviadas' => 0,
            'falhadas' => 0,
            'ultimas' => [],
        ];
        $payload['encarregados_historico'] = [];
    }


    if (function_exists('sige_audit_log')) {
        sige_audit_log('aluno_ficha_360_consultada', [
            'aluno_id' => (int)$aluno->id,
            'ano_lectivo' => $ano_lectivo,
            'qualidade' => (int)$qualidade['score'],
        ], 'alunos');
    }

    wp_send_json_success($payload);
}

// ======================================================================
// AJAX: sige_importar_alunos_csv
// ======================================================================
// Importação controlada de alunos por CSV, com validação rigorosa de
// dados mínimos, contactos dos pais/encarregados e matrícula no ano lectivo
// activo. Mantém o escopo por escola_id e não altera regras financeiras,
// académicas, pautas, boletins ou fórmulas.
// ======================================================================
if (!function_exists('sige_alunos_user_can_manage')) {
    function sige_alunos_user_can_manage() {
        if (function_exists('sige_page_guard_allows')) {
            return sige_page_guard_allows(
                ['alunos.criar','alunos.editar'],
                ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
            );
        }
        return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_assistente');
    }
}

add_action('wp_ajax_sige_importar_alunos_csv', 'sige_ajax_importar_alunos_csv');

add_action('admin_post_sige_download_modelo_importacao_alunos_xlsx', 'sige_admin_post_download_modelo_importacao_alunos_xlsx');

// ======================================================================
// ADMIN-POST: sige_download_modelo_importacao_alunos_xlsx
// ======================================================================
// Gera um modelo Excel (.xlsx) sem dependências externas, com listas
// controladas para género, classe, turma, tipo de documento e estado.
// A importação continua a respeitar a turma de destino escolhida no modal;
// as colunas classe/turma no Excel servem para conferência operacional.
// ======================================================================
function sige_admin_post_download_modelo_importacao_alunos_xlsx() {
    if (function_exists('check_admin_referer')) {
        check_admin_referer('sige_download_modelo_importacao_alunos_xlsx');
    }

    if (!sige_alunos_user_can_manage()) {
        wp_die('Sem permissão para baixar o modelo de importação de alunos.');
    }

    $escola_id   = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $ano_lectivo = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (int)wp_date('Y');
    $catalogos   = sige_import_alunos_template_catalogs($escola_id, $ano_lectivo);
    $xlsx        = sige_import_alunos_build_xlsx_template($catalogos, $ano_lectivo);

    if ($xlsx === '') {
        wp_die('Não foi possível gerar o modelo Excel neste servidor.');
    }

    if (function_exists('sige_audit_log')) {
        sige_audit_log('alunos_importacao_modelo_xlsx_baixado', [
            'ano_lectivo' => $ano_lectivo,
            'turmas'      => isset($catalogos['turmas']) ? count($catalogos['turmas']) : 0,
            'classes'     => isset($catalogos['classes']) ? count($catalogos['classes']) : 0,
        ], 'alunos');
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $filename = 'modelo-importacao-alunos-softgenial-' . (int)$ano_lectivo . '.xlsx';
    if (function_exists('nocache_headers')) {
        nocache_headers();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsx));
    header('X-Content-Type-Options: nosniff');
    echo $xlsx;
    exit;
}

function sige_import_alunos_template_catalogs($escola_id, $ano_lectivo) {
    global $wpdb;

    $classes = [];
    $turmas  = [];

    $tbl_turmas = $wpdb->prefix . 'sige_turmas';
    if (function_exists('sige_import_alunos_table_exists') && sige_import_alunos_table_exists($tbl_turmas)) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, classe, nome FROM {$tbl_turmas} WHERE escola_id = %d AND ano_lectivo = %d ORDER BY classe ASC, nome ASC",
            $escola_id,
            $ano_lectivo
        ));
        foreach ((array)$rows as $t) {
            $classe = trim((string)($t->classe ?? ''));
            $nome   = trim((string)($t->nome ?? ''));
            if ($classe !== '') {
                $classes[$classe] = $classe;
            }
            $label = trim($classe . ($classe !== '' && $nome !== '' ? ' - ' : '') . $nome);
            if ($label !== '') {
                $turmas[$label] = $label;
            }
        }
    }

    if (empty($classes)) {
        $fallback = ['Pré-Escolar','1ª Classe','2ª Classe','3ª Classe','4ª Classe','5ª Classe','6ª Classe','7ª Classe','8ª Classe','9ª Classe','10ª Classe','11ª Classe','12ª Classe'];
        foreach ($fallback as $c) $classes[$c] = $c;
    }
    if (empty($turmas)) {
        $turmas['Seleccione a turma no SoftGenial'] = 'Seleccione a turma no SoftGenial';
    }

    return [
        'generos'    => ['M', 'F'],
        'classes'    => array_values($classes),
        'turmas'     => array_values($turmas),
        'documentos' => ['Boletim de Nascimento', 'Cédula', 'BI', 'Passaporte', 'DIRE'],
        'status'     => ['activo', 'suspenso', 'transferido', 'desistente'],
    ];
}

function sige_import_alunos_build_xlsx_template(array $catalogos, int $ano_lectivo): string {
    $headers = [
        'numero_processo',
        'nome_completo *',
        'data_nascimento *',
        'genero *',
        'classe',
        'turma',
        'nome_pai *',
        'telemovel_pai *',
        'nome_mae *',
        'telemovel_mae *',
        'whatsapp_notificacoes',
        'contacto_encarregado',
        'email_pai',
        'email_mae',
        'email_encarregado',
        'bairro',
        'nacionalidade',
        'tipo_documento',
        'documento_nr',
        'status',
        'observacoes',
    ];
    $required = [2,3,4,7,8,9,10]; // colunas 1-based marcadas como obrigatórias

    $sheet_alunos = sige_import_alunos_xlsx_sheet_alunos($headers, $required);
    $sheet_listas = sige_import_alunos_xlsx_sheet_listas($catalogos);
    $sheet_instr  = sige_import_alunos_xlsx_sheet_instrucoes($ano_lectivo);

    $classes_count = max(1, count($catalogos['classes'] ?? []));
    $turmas_count  = max(1, count($catalogos['turmas'] ?? []));
    $docs_count    = max(1, count($catalogos['documentos'] ?? []));
    $status_count  = max(1, count($catalogos['status'] ?? []));

    $files = [
        '[Content_Types].xml'           => sige_import_alunos_xlsx_content_types(),
        '_rels/.rels'                   => sige_import_alunos_xlsx_root_rels(),
        'docProps/app.xml'              => sige_import_alunos_xlsx_app_xml(),
        'docProps/core.xml'             => sige_import_alunos_xlsx_core_xml(),
        'xl/workbook.xml'               => sige_import_alunos_xlsx_workbook_xml($classes_count, $turmas_count, $docs_count, $status_count),
        'xl/_rels/workbook.xml.rels'    => sige_import_alunos_xlsx_workbook_rels(),
        'xl/styles.xml'                 => sige_import_alunos_xlsx_styles_xml(),
        'xl/worksheets/sheet1.xml'      => $sheet_alunos,
        'xl/worksheets/sheet2.xml'      => $sheet_listas,
        'xl/worksheets/sheet3.xml'      => $sheet_instr,
    ];

    return sige_import_alunos_zip_store($files);
}

function sige_import_alunos_xlsx_sheet_alunos(array $headers, array $required_cols): string {
    $cells = [];
    foreach ($headers as $i => $label) {
        $col = $i + 1;
        $style = in_array($col, $required_cols, true) ? 2 : 1;
        $cells[] = sige_import_alunos_xlsx_cell(sige_import_alunos_xlsx_col($col) . '1', $label, $style);
    }
    $row1 = '<row r="1" ht="28" customHeight="1">' . implode('', $cells) . '</row>';

    $cols = '';
    $widths = [18,34,18,12,18,28,28,18,28,18,22,22,24,24,26,22,18,24,20,16,42];
    foreach ($widths as $i => $w) {
        $n = $i + 1;
        $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
    }

    $validations = [
        ['type' => 'list', 'sqref' => 'D2:D2001', 'formula1' => 'SIGE_GENEROS', 'errorTitle' => 'Género obrigatório', 'error' => 'Seleccione M ou F na lista.'],
        ['type' => 'list', 'sqref' => 'E2:E2001', 'formula1' => 'SIGE_CLASSES', 'errorTitle' => 'Classe inválida', 'error' => 'Seleccione uma classe da lista controlada.'],
        ['type' => 'list', 'sqref' => 'F2:F2001', 'formula1' => 'SIGE_TURMAS', 'errorTitle' => 'Turma inválida', 'error' => 'Seleccione uma turma da lista controlada.'],
        ['type' => 'list', 'sqref' => 'R2:R2001', 'formula1' => 'SIGE_DOCUMENTOS', 'errorTitle' => 'Tipo de documento inválido', 'error' => 'Seleccione um tipo de documento da lista.'],
        ['type' => 'list', 'sqref' => 'T2:T2001', 'formula1' => 'SIGE_STATUS', 'errorTitle' => 'Estado inválido', 'error' => 'Seleccione um estado da lista.'],
    ];

    $dv = '<dataValidations count="' . count($validations) . '">';
    foreach ($validations as $v) {
        $dv .= '<dataValidation type="' . sige_import_alunos_xlsx_escape($v['type']) . '" allowBlank="1" showErrorMessage="1" errorTitle="' . sige_import_alunos_xlsx_escape($v['errorTitle']) . '" error="' . sige_import_alunos_xlsx_escape($v['error']) . '" sqref="' . sige_import_alunos_xlsx_escape($v['sqref']) . '"><formula1>' . sige_import_alunos_xlsx_escape($v['formula1']) . '</formula1></dataValidation>';
    }
    $dv .= '</dataValidations>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="B2" sqref="B2"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . '<cols>' . $cols . '</cols>'
        . '<sheetData>' . $row1 . '</sheetData>'
        . '<autoFilter ref="A1:U1"/>'
        . $dv
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';
}

function sige_import_alunos_xlsx_sheet_listas(array $catalogos): string {
    $columns = [
        'A' => ['Género', $catalogos['generos'] ?? ['M','F']],
        'B' => ['Classes', $catalogos['classes'] ?? []],
        'C' => ['Turmas', $catalogos['turmas'] ?? []],
        'D' => ['Tipos de documento', $catalogos['documentos'] ?? []],
        'E' => ['Estado', $catalogos['status'] ?? []],
    ];

    $max = 1;
    foreach ($columns as $col) {
        $max = max($max, count($col[1]) + 1);
    }

    $rows = [];
    for ($r = 1; $r <= $max; $r++) {
        $cells = [];
        $idx = 1;
        foreach ($columns as $data) {
            $value = $r === 1 ? $data[0] : ($data[1][$r - 2] ?? '');
            if ($value !== '') {
                $cells[] = sige_import_alunos_xlsx_cell(sige_import_alunos_xlsx_col($idx) . $r, $value, $r === 1 ? 1 : 0);
            }
            $idx++;
        }
        if (!empty($cells)) {
            $rows[] = '<row r="' . $r . '">' . implode('', $cells) . '</row>';
        }
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<cols><col min="1" max="1" width="14" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/><col min="3" max="3" width="34" customWidth="1"/><col min="4" max="4" width="28" customWidth="1"/><col min="5" max="5" width="18" customWidth="1"/></cols>'
        . '<sheetData>' . implode('', $rows) . '</sheetData>'
        . '</worksheet>';
}

function sige_import_alunos_xlsx_sheet_instrucoes(int $ano_lectivo): string {
    $rows = [
        ['Modelo de importação de alunos - SoftGenial'],
        ['Ano lectivo', (string)$ano_lectivo],
        ['Como usar', 'Preencha a folha Alunos. Os campos com * são obrigatórios. Use as listas controladas para género, classe, turma, tipo de documento e estado.'],
        ['Importante', 'A matrícula final será feita na Turma de destino escolhida no popup do SoftGenial. As colunas classe e turma no Excel servem como conferência e padronização da lista.'],
        ['Datas', 'Use o formato AAAA-MM-DD, por exemplo 2015-03-12.'],
        ['Telemóveis', 'Use números móveis moçambicanos: 82, 83, 84, 85, 86 ou 87 + 7 dígitos. O sistema também aceita 258 no início.'],
        ['Pais/encarregados', 'Por padrão, nome_pai, telemovel_pai, nome_mae e telemovel_mae são obrigatórios para comunicação segura por WhatsApp.'],
        ['Fluxo seguro', 'Primeiro faça a pré-validação. O SoftGenial só grava depois da confirmação final.'],
    ];

    $xmlRows = [];
    foreach ($rows as $r => $values) {
        $cells = [];
        foreach ($values as $c => $v) {
            $style = ($r === 0) ? 3 : ($c === 0 ? 4 : 0);
            $cells[] = sige_import_alunos_xlsx_cell(sige_import_alunos_xlsx_col($c + 1) . ($r + 1), $v, $style);
        }
        $xmlRows[] = '<row r="' . ($r + 1) . '">' . implode('', $cells) . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<cols><col min="1" max="1" width="24" customWidth="1"/><col min="2" max="2" width="96" customWidth="1"/></cols>'
        . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
        . '<mergeCells count="1"><mergeCell ref="A1:B1"/></mergeCells>'
        . '</worksheet>';
}

function sige_import_alunos_xlsx_cell(string $ref, string $value, int $style = 0): string {
    $style_attr = $style > 0 ? ' s="' . $style . '"' : '';
    return '<c r="' . sige_import_alunos_xlsx_escape($ref) . '" t="inlineStr"' . $style_attr . '><is><t>' . sige_import_alunos_xlsx_escape($value) . '</t></is></c>';
}

function sige_import_alunos_xlsx_col(int $index): string {
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function sige_import_alunos_xlsx_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function sige_import_alunos_xlsx_workbook_xml(int $classes_count, int $turmas_count, int $docs_count, int $status_count): string {
    $classes_end = 1 + max(1, $classes_count);
    $turmas_end  = 1 + max(1, $turmas_count);
    $docs_end    = 1 + max(1, $docs_count);
    $status_end  = 1 + max(1, $status_count);

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>'
        . '<sheet name="Alunos" sheetId="1" r:id="rId1"/>'
        . '<sheet name="Listas" sheetId="2" r:id="rId2"/>'
        . '<sheet name="Instrucoes" sheetId="3" r:id="rId3"/>'
        . '</sheets>'
        . '<definedNames>'
        . '<definedName name="SIGE_GENEROS">\'Listas\'!$A$2:$A$3</definedName>'
        . '<definedName name="SIGE_CLASSES">\'Listas\'!$B$2:$B$' . $classes_end . '</definedName>'
        . '<definedName name="SIGE_TURMAS">\'Listas\'!$C$2:$C$' . $turmas_end . '</definedName>'
        . '<definedName name="SIGE_DOCUMENTOS">\'Listas\'!$D$2:$D$' . $docs_end . '</definedName>'
        . '<definedName name="SIGE_STATUS">\'Listas\'!$E$2:$E$' . $status_end . '</definedName>'
        . '</definedNames>'
        . '</workbook>';
}

function sige_import_alunos_xlsx_workbook_rels(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
        . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
}

function sige_import_alunos_xlsx_content_types(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>';
}

function sige_import_alunos_xlsx_root_rels(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';
}

function sige_import_alunos_xlsx_app_xml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>SoftGenial</Application><DocSecurity>0</DocSecurity><ScaleCrop>false</ScaleCrop><HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>3</vt:i4></vt:variant></vt:vector></HeadingPairs><TitlesOfParts><vt:vector size="3" baseType="lpstr"><vt:lpstr>Alunos</vt:lpstr><vt:lpstr>Listas</vt:lpstr><vt:lpstr>Instrucoes</vt:lpstr></vt:vector></TitlesOfParts><Company>RMBJ Consultoria</Company><LinksUpToDate>false</LinksUpToDate><SharedDoc>false</SharedDoc><HyperlinksChanged>false</HyperlinksChanged><AppVersion>12.11</AppVersion>'
        . '</Properties>';
}

function sige_import_alunos_xlsx_core_xml(): string {
    $now = gmdate('Y-m-d\TH:i:s\Z');
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Modelo de importação de alunos - SoftGenial</dc:title><dc:creator>SoftGenial</dc:creator><cp:lastModifiedBy>SoftGenial</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
        . '</cp:coreProperties>';
}

function sige_import_alunos_xlsx_styles_xml(): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="5"><font><sz val="11"/><color rgb="FF111827"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF10142D"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF475569"/><name val="Calibri"/></font></fonts>'
        . '<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF334155"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF059669"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="3" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="4" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>'
        . '</styleSheet>';
}

function sige_import_alunos_zip_store(array $files): string {
    $out = '';
    $central = '';
    $offset = 0;
    $now = getdate();
    $dos_time = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | ((int)floor($now['seconds'] / 2) & 0x1F);
    $dos_date = (((max(1980, (int)$now['year']) - 1980) & 0x7F) << 9) | (($now['mon'] & 0x0F) << 5) | ($now['mday'] & 0x1F);

    foreach ($files as $name => $data) {
        $name = ltrim(str_replace('\\', '/', (string)$name), '/');
        $data = (string)$data;
        $crc = sprintf('%u', crc32($data));
        $size = strlen($data);
        $name_len = strlen($name);

        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dos_time, $dos_date, $crc, $size, $size, $name_len, 0) . $name . $data;
        $out .= $local;

        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 0x0314, 20, 0, 0, $dos_time, $dos_date, $crc, $size, $size, $name_len, 0, 0, 0, 0, 32, $offset) . $name;
        $offset += strlen($local);
    }

    $central_offset = strlen($out);
    $central_size = strlen($central);
    $count = count($files);
    return $out . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $central_size, $central_offset, 0);
}

function sige_import_alunos_zip_entries(string $path) {
    if (!is_readable($path)) {
        return new WP_Error('sige_import_xlsx_unreadable', 'Não foi possível ler o ficheiro Excel carregado.');
    }
    $data = file_get_contents($path);
    if ($data === false || strlen($data) < 22) {
        return new WP_Error('sige_import_xlsx_empty', 'O ficheiro Excel está vazio ou corrompido.');
    }
    $eocd = strrpos($data, "PK\x05\x06");
    if ($eocd === false) {
        return new WP_Error('sige_import_xlsx_zip', 'O ficheiro Excel não tem uma estrutura .xlsx válida.');
    }
    $end = unpack('vdisk/vcdisk/ventriesDisk/ventries/Vsize/Voffset/vcommentLength', substr($data, $eocd + 4, 18));
    $ptr = (int)$end['offset'];
    $entries = [];
    for ($i = 0; $i < (int)$end['entries']; $i++) {
        if (substr($data, $ptr, 4) !== "PK\x01\x02") break;
        $h = unpack('vverMade/vverNeeded/vflags/vmethod/vmtime/vmdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttrs/VextAttrs/Voffset', substr($data, $ptr + 4, 42));
        $name = substr($data, $ptr + 46, (int)$h['nameLen']);
        $entries[$name] = [
            'name'   => $name,
            'method' => (int)$h['method'],
            'comp'   => (int)$h['comp'],
            'uncomp' => (int)$h['uncomp'],
            'offset' => (int)$h['offset'],
        ];
        $ptr += 46 + (int)$h['nameLen'] + (int)$h['extraLen'] + (int)$h['commentLen'];
    }
    return ['entries' => $entries, 'data' => $data];
}

function sige_import_alunos_zip_entry_data(string $path, string $entry_name) {
    $zip = sige_import_alunos_zip_entries($path);
    if (is_wp_error($zip)) return $zip;
    if (empty($zip['entries'][$entry_name])) {
        return new WP_Error('sige_import_xlsx_missing_entry', 'Entrada em falta no Excel: ' . $entry_name);
    }
    $e = $zip['entries'][$entry_name];
    $data = $zip['data'];
    $ptr = $e['offset'];
    if (substr($data, $ptr, 4) !== "PK\x03\x04") {
        return new WP_Error('sige_import_xlsx_local_header', 'Estrutura interna inválida no ficheiro Excel.');
    }
    $h = unpack('vver/vflags/vmethod/vmtime/vmdate/Vcrc/Vcomp/Vuncomp/vnameLen/vextraLen', substr($data, $ptr + 4, 26));
    $start = $ptr + 30 + (int)$h['nameLen'] + (int)$h['extraLen'];
    $raw = substr($data, $start, (int)$e['comp']);
    if ((int)$e['method'] === 0) {
        return $raw;
    }
    if ((int)$e['method'] === 8 && function_exists('gzinflate')) {
        $inflated = @gzinflate($raw);
        if ($inflated !== false) return $inflated;
    }
    return new WP_Error('sige_import_xlsx_compression', 'Não foi possível descompactar o Excel. Guarde novamente como .xlsx ou use CSV.');
}

function sige_import_alunos_parse_xlsx(string $path) {
    $entries = sige_import_alunos_zip_entries($path);
    if (is_wp_error($entries)) return $entries;

    $shared = [];
    if (!empty($entries['entries']['xl/sharedStrings.xml'])) {
        $shared_xml = sige_import_alunos_zip_entry_data($path, 'xl/sharedStrings.xml');
        if (!is_wp_error($shared_xml)) {
            $shared = sige_import_alunos_xlsx_shared_strings($shared_xml);
        }
    }

    $sheet_path = 'xl/worksheets/sheet1.xml';
    if (empty($entries['entries'][$sheet_path])) {
        foreach (array_keys($entries['entries']) as $name) {
            if (preg_match('#^xl/worksheets/sheet[0-9]+\.xml$#', $name)) {
                $sheet_path = $name;
                break;
            }
        }
    }

    $sheet_xml = sige_import_alunos_zip_entry_data($path, $sheet_path);
    if (is_wp_error($sheet_xml)) return $sheet_xml;

    $raw_rows = sige_import_alunos_xlsx_rows_from_xml($sheet_xml, $shared);
    if (empty($raw_rows)) {
        return new WP_Error('sige_import_xlsx_rows', 'O Excel não tem linhas válidas para importar.');
    }

    $header_index = null;
    $raw_headers = [];
    foreach ($raw_rows as $idx => $row) {
        $non_empty = array_values(array_filter($row, static function($v) { return trim((string)$v) !== ''; }));
        if (count($non_empty) >= 2) {
            $header_index = $idx;
            $raw_headers = $row;
            break;
        }
    }
    if ($header_index === null) {
        return new WP_Error('sige_import_xlsx_headers', 'O Excel precisa de cabeçalhos válidos na primeira folha.');
    }

    $headers = [];
    foreach ($raw_headers as $h) {
        $headers[] = sige_import_alunos_header_key($h);
    }

    $rows = [];
    $max_cols = count($headers);
    for ($i = $header_index + 1; $i < count($raw_rows); $i++) {
        $line = $raw_rows[$i];
        $row = [];
        for ($c = 0; $c < $max_cols; $c++) {
            $key = $headers[$c] ?? '';
            if ($key === '') continue;
            $row[$key] = isset($line[$c]) ? trim((string)$line[$c]) : '';
        }
        $rows[] = $row;
    }

    return [
        'headers' => array_values(array_unique(array_filter($headers))),
        'rows'    => $rows,
    ];
}

function sige_import_alunos_xlsx_shared_strings(string $xml): array {
    $strings = [];
    if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/si', $xml, $matches)) {
        foreach ($matches[1] as $si) {
            $text = '';
            if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $si, $tm)) {
                foreach ($tm[1] as $part) {
                    $text .= sige_import_alunos_xml_decode($part);
                }
            }
            $strings[] = $text;
        }
    }
    return $strings;
}

function sige_import_alunos_xlsx_rows_from_xml(string $xml, array $shared): array {
    $rows = [];
    if (!preg_match_all('/<row\b[^>]*>(.*?)<\/row>/si', $xml, $row_matches)) {
        return [];
    }
    foreach ($row_matches[1] as $row_xml) {
        $row = [];
        $seq = 0;
        if (preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/si', $row_xml, $cell_matches, PREG_SET_ORDER)) {
            foreach ($cell_matches as $cell) {
                $attrs = $cell[1];
                $body = $cell[2];
                $ref = sige_import_alunos_xml_attr($attrs, 'r');
                $type = sige_import_alunos_xml_attr($attrs, 't');
                $col = $ref !== '' ? sige_import_alunos_xlsx_col_to_index($ref) : $seq;
                $seq = max($seq + 1, $col + 1);
                $row[$col] = sige_import_alunos_xlsx_cell_value($body, $type, $shared);
            }
        }
        if (!empty($row)) {
            ksort($row);
            $max = max(array_keys($row));
            $dense = [];
            for ($i = 0; $i <= $max; $i++) {
                $dense[$i] = $row[$i] ?? '';
            }
            $rows[] = $dense;
        }
    }
    return $rows;
}

function sige_import_alunos_xlsx_cell_value(string $body, string $type, array $shared): string {
    if ($type === 'inlineStr') {
        if (preg_match_all('/<t\b[^>]*>(.*?)<\/t>/si', $body, $tm)) {
            $parts = [];
            foreach ($tm[1] as $part) $parts[] = sige_import_alunos_xml_decode($part);
            return implode('', $parts);
        }
        return '';
    }
    if (!preg_match('/<v\b[^>]*>(.*?)<\/v>/si', $body, $vm)) {
        return '';
    }
    $v = sige_import_alunos_xml_decode($vm[1]);
    if ($type === 's') {
        $idx = (int)$v;
        return isset($shared[$idx]) ? (string)$shared[$idx] : '';
    }
    return (string)$v;
}

function sige_import_alunos_xml_attr(string $attrs, string $name): string {
    if (preg_match('/\b' . preg_quote($name, '/') . '="([^"]*)"/i', $attrs, $m)) {
        return sige_import_alunos_xml_decode($m[1]);
    }
    return '';
}

function sige_import_alunos_xml_decode(string $value): string {
    return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function sige_import_alunos_xlsx_col_to_index(string $cell_ref): int {
    if (!preg_match('/^([A-Z]+)/i', $cell_ref, $m)) return 0;
    $letters = strtoupper($m[1]);
    $num = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $num = $num * 26 + (ord($letters[$i]) - 64);
    }
    return max(0, $num - 1);
}

function sige_ajax_importar_alunos_csv() {
    sige_check_nonce_global();

    if (!sige_alunos_user_can_manage()) {
        wp_send_json_error('Sem permissão para importar alunos.');
    }

    global $wpdb;

    $escola_id    = sige_require_escola_id('importar_alunos');
    $ano_lectivo  = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (int)wp_date('Y');
    $turma_id     = isset($_POST['turma_id']) ? (int)$_POST['turma_id'] : 0;
    $permitir_pendentes = isset($_POST['permitir_pendentes']) && (string)$_POST['permitir_pendentes'] === '1';
    $modo_importacao = isset($_POST['sige_import_mode']) ? sanitize_key(wp_unslash($_POST['sige_import_mode'])) : 'preview';
    if (!in_array($modo_importacao, ['preview', 'confirm'], true)) {
        $modo_importacao = 'preview';
    }

    if ($turma_id <= 0) {
        wp_send_json_error('Seleccione a turma onde os alunos serão matriculados.');
    }

    $tbl_alunos     = $wpdb->prefix . 'sige_alunos';
    $tbl_matriculas = $wpdb->prefix . 'sige_matriculas';
    $tbl_turmas     = $wpdb->prefix . 'sige_turmas';

    $turma = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nome, classe FROM {$tbl_turmas} WHERE id = %d AND escola_id = %d AND ano_lectivo = %d LIMIT 1",
        $turma_id, $escola_id, $ano_lectivo
    ));

    if (!$turma) {
        wp_send_json_error('Turma inválida ou não pertence ao ano lectivo activo.');
    }

    if (empty($_FILES['ficheiro']) || !isset($_FILES['ficheiro']['tmp_name'])) {
        wp_send_json_error('Carregue um ficheiro Excel (.xlsx) ou CSV antes de importar.');
    }

    if (!empty($_FILES['ficheiro']['error'])) {
        wp_send_json_error('Erro no carregamento do ficheiro. Código: ' . (int)$_FILES['ficheiro']['error']);
    }

    $nome_original = isset($_FILES['ficheiro']['name']) ? sanitize_file_name($_FILES['ficheiro']['name']) : 'lista.xlsx';
    $ext = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx','csv','txt'], true)) {
        wp_send_json_error('Formato não suportado. Use o modelo Excel (.xlsx) do SoftGenial ou CSV exportado do Excel.');
    }

    $size = isset($_FILES['ficheiro']['size']) ? (int)$_FILES['ficheiro']['size'] : 0;
    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        wp_send_json_error('O ficheiro deve ter conteúdo e não pode exceder 8 MB.');
    }

    // Validacao de conteudo real: recusa um executavel ou script disfarcado de lista.
    if (function_exists('sige_uploads_validate_import_file')) {
        $sige_imp_check = sige_uploads_validate_import_file((string)$_FILES['ficheiro']['tmp_name'], $nome_original, ['xlsx', 'csv', 'txt']);
        if (empty($sige_imp_check['ok'])) {
            wp_send_json_error(!empty($sige_imp_check['error']) ? $sige_imp_check['error'] : 'Ficheiro de importacao invalido.');
        }
    }

    $parsed = ($ext === 'xlsx')
        ? sige_import_alunos_parse_xlsx($_FILES['ficheiro']['tmp_name'])
        : sige_import_alunos_parse_csv($_FILES['ficheiro']['tmp_name']);
    if (is_wp_error($parsed)) {
        wp_send_json_error($parsed->get_error_message());
    }

    $headers = $parsed['headers'];
    $rows    = $parsed['rows'];

    if (empty($rows)) {
        wp_send_json_error('O ficheiro não tem linhas de alunos para importar.');
    }

    $required_headers = ['nome_completo', 'data_nascimento', 'genero'];
    foreach ($required_headers as $h) {
        if (!in_array($h, $headers, true)) {
            wp_send_json_error('Coluna obrigatória em falta no ficheiro: ' . $h . '. Baixe o modelo do SoftGenial e mantenha os cabeçalhos.');
        }
    }

    $max_rows = 2000;
    if (count($rows) > $max_rows) {
        wp_send_json_error('Por segurança, importe no máximo ' . $max_rows . ' alunos por ficheiro. Divida a lista em partes.');
    }

    $preview_hash = sige_import_alunos_preview_hash($headers, $rows, $turma_id, $escola_id, $ano_lectivo, $permitir_pendentes);
    if ($modo_importacao === 'confirm') {
        $submitted_hash = isset($_POST['sige_preview_hash']) ? sanitize_text_field(wp_unslash($_POST['sige_preview_hash'])) : '';
        if ($submitted_hash === '' || !hash_equals($preview_hash, $submitted_hash)) {
            wp_send_json_error('A lista, turma ou opção de contactos mudou depois da pré-validação. Faça a pré-validação novamente antes de gravar.');
        }
    }

    $stats = [
        'total_linhas' => count($rows),
        'importaveis'  => 0,
        'importados'   => 0,
        'duplicados'   => 0,
        'erros'        => 0,
        'pendentes'    => 0,
    ];
    $detalhes = [];
    $importados_ids = [];
    $lote_id = $modo_importacao === 'confirm' ? sige_import_alunos_generate_batch_id($escola_id) : '';

    foreach ($rows as $row_index => $row) {
        $linha = $row_index + 2; // header está na linha 1

        if (sige_import_alunos_row_is_empty($row)) {
            continue;
        }

        $nome = sanitize_text_field($row['nome_completo'] ?? '');
        $data_nascimento = sige_import_alunos_parse_date($row['data_nascimento'] ?? '');
        $genero = sige_import_alunos_parse_gender($row['genero'] ?? '');

        $row_errors = [];
        if ($nome === '') $row_errors[] = 'nome_completo vazio';
        if ($data_nascimento === '') $row_errors[] = 'data_nascimento inválida';
        if ($genero === '') $row_errors[] = 'genero inválido';

        $nome_pai = sanitize_text_field($row['nome_pai'] ?? '');
        $nome_mae = sanitize_text_field($row['nome_mae'] ?? '');
        $tel_pai_raw = sanitize_text_field($row['telemovel_pai'] ?? '');
        $tel_mae_raw = sanitize_text_field($row['telemovel_mae'] ?? '');
        $tel_pai = sige_import_alunos_normalize_phone($tel_pai_raw);
        $tel_mae = sige_import_alunos_normalize_phone($tel_mae_raw);

        $contact_errors = [];
        if ($nome_pai === '') $contact_errors[] = 'nome_pai em falta';
        if ($tel_pai_raw === '') $contact_errors[] = 'telemovel_pai em falta';
        elseif ($tel_pai === '') $contact_errors[] = 'telemovel_pai inválido';
        if ($nome_mae === '') $contact_errors[] = 'nome_mae em falta';
        if ($tel_mae_raw === '') $contact_errors[] = 'telemovel_mae em falta';
        elseif ($tel_mae === '') $contact_errors[] = 'telemovel_mae inválido';

        if (!empty($contact_errors) && !$permitir_pendentes) {
            $row_errors = array_merge($row_errors, $contact_errors);
        }

        if (!empty($row_errors)) {
            $stats['erros']++;
            $detalhes[] = [
                'linha' => $linha,
                'estado' => 'erro',
                'nome' => $nome,
                'mensagem' => implode('; ', $row_errors),
            ];
            continue;
        }

        $numero_processo = sanitize_text_field($row['numero_processo'] ?? '');
        if ($numero_processo !== '') {
            $exists_proc = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tbl_alunos} WHERE escola_id = %d AND numero_processo = %s LIMIT 1",
                $escola_id, $numero_processo
            ));
            if ($exists_proc > 0) {
                $stats['duplicados']++;
                $detalhes[] = [
                    'linha' => $linha,
                    'estado' => 'duplicado',
                    'nome' => $nome,
                    'mensagem' => 'número de processo já existe: ' . $numero_processo,
                ];
                continue;
            }
        }

        $exists_name_birth = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tbl_alunos} WHERE escola_id = %d AND nome_completo = %s AND data_nascimento = %s LIMIT 1",
            $escola_id, $nome, $data_nascimento
        ));
        if ($exists_name_birth > 0) {
            $stats['duplicados']++;
            $detalhes[] = [
                'linha' => $linha,
                'estado' => 'duplicado',
                'nome' => $nome,
                'mensagem' => 'aluno com o mesmo nome e data de nascimento já existe',
            ];
            continue;
        }

        $whatsapp = sige_import_alunos_normalize_phone($row['whatsapp_notificacoes'] ?? '');
        if ($whatsapp === '') {
            $whatsapp = $tel_pai !== '' ? $tel_pai : $tel_mae;
        }
        $contacto_encarregado = sige_import_alunos_normalize_phone($row['contacto_encarregado'] ?? '');
        if ($contacto_encarregado === '') {
            $contacto_encarregado = $whatsapp;
        }

        $obs = sanitize_textarea_field($row['observacoes'] ?? '');
        $has_pending_contacts = !empty($contact_errors);
        if ($has_pending_contacts) {
            $stats['pendentes']++;
            $pend_msg = 'IMPORTAÇÃO: contactos/nome dos pais pendentes de confirmação (' . implode('; ', $contact_errors) . '). Actualizar a ficha do aluno antes de activar comunicação por WhatsApp.';
            $obs = trim($obs . "\n" . $pend_msg);
        }

        $stats['importaveis']++;

        if ($modo_importacao === 'preview') {
            $detalhes[] = [
                'linha' => $linha,
                'estado' => $has_pending_contacts ? 'pendente' : 'validado',
                'nome' => $nome,
                'mensagem' => $has_pending_contacts
                    ? 'será importado com contactos dos pais pendentes de confirmação'
                    : ($numero_processo === '' ? 'pronto para gravar; número de processo será gerado automaticamente' : 'pronto para gravar'),
            ];
            continue;
        }

        if ($lote_id !== '') {
            $obs = trim($obs . "\n" . 'LOTE DE IMPORTAÇÃO: ' . $lote_id . ' | ' . current_time('mysql'));
        }

        if ($tel_pai === '') $tel_pai = '';
        if ($tel_mae === '') $tel_mae = '';

        $dados_brutos = [
            'escola_id'               => $escola_id,
            'numero_processo'         => $numero_processo,
            'nome_completo'           => $nome,
            'data_nascimento'         => $data_nascimento,
            'genero'                  => $genero,
            'nacionalidade'           => sanitize_text_field($row['nacionalidade'] ?? 'Moçambicana'),
            'bairro'                  => sanitize_text_field($row['bairro'] ?? ''),
            'documento_nr'            => sanitize_text_field($row['documento_nr'] ?? ($row['documento_numero'] ?? '')),
            'tipo_documento'          => sanitize_text_field($row['tipo_documento'] ?? ''),
            'nome_pai'                => $nome_pai,
            'telemovel_pai'           => $tel_pai,
            'email_pai'               => sanitize_email($row['email_pai'] ?? ''),
            'nome_mae'                => $nome_mae,
            'telemovel_mae'           => $tel_mae,
            'email_mae'               => sanitize_email($row['email_mae'] ?? ''),
            'contacto_encarregado'    => $contacto_encarregado,
            'whatsapp_notificacoes'   => $whatsapp,
            'email_encarregado'       => sanitize_email($row['email_encarregado'] ?? ''),
            'status'                  => in_array(strtolower((string)($row['status'] ?? 'activo')), ['activo','suspenso','transferido','desistente'], true) ? strtolower((string)($row['status'] ?? 'activo')) : 'activo',
            'observacoes'             => $obs,
            'data_registo'            => current_time('mysql'),
        ];

        // v12.11.9.39 - metadados estruturados do lote para permitir anulação segura.
        if ($lote_id !== '') {
            $dados_brutos['importacao_lote_id'] = $lote_id;
            $dados_brutos['importado_em']       = current_time('mysql');
            $dados_brutos['importado_por']      = get_current_user_id();
        }

        if ($dados_brutos['numero_processo'] === '') {
            $dados_brutos['numero_processo'] = sige_import_alunos_next_numero_processo($tbl_alunos, $escola_id);
        }

        $dados_db = [];
        foreach ($dados_brutos as $col => $val) {
            if ($col === 'escola_id' || (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_alunos, $col))) {
                $dados_db[$col] = $val;
            }
        }

        $insert_ok = $wpdb->insert($tbl_alunos, $dados_db);

        // Protecção contra corrida rara no número automático.
        if ($insert_ok === false && !empty($wpdb->last_error) && strpos(strtolower($wpdb->last_error), 'duplicate') !== false && empty($row['numero_processo'])) {
            $dados_db['numero_processo'] = sige_import_alunos_next_numero_processo($tbl_alunos, $escola_id);
            $insert_ok = $wpdb->insert($tbl_alunos, $dados_db);
        }

        if ($insert_ok === false) {
            $stats['erros']++;
            $detalhes[] = [
                'linha' => $linha,
                'estado' => 'erro',
                'nome' => $nome,
                'mensagem' => 'erro ao inserir aluno: ' . $wpdb->last_error,
            ];
            continue;
        }

        $aluno_id = (int)$wpdb->insert_id;
        $importados_ids[] = $aluno_id;

        $matricula_data = [
            'escola_id'    => $escola_id,
            'aluno_id'     => $aluno_id,
            'turma_id'     => $turma_id,
            'ano_lectivo'  => $ano_lectivo,
        ];
        if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_matriculas, 'status_matricula')) {
            // [v12.11.9.88.1] A matrícula importada deve reflectir a situação
            // operacional do aluno, e não ficar sempre activa quando a linha do
            // Excel indicar desistente/transferido/suspenso.
            $matricula_data['status_matricula'] = function_exists('sige_matricula_status_from_aluno_status')
                ? sige_matricula_status_from_aluno_status((string)($dados_brutos['status'] ?? 'activo'))
                : 'activa';
        }
        if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_matriculas, 'data_matricula')) {
            $matricula_data['data_matricula'] = current_time('mysql');
        }
        if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_matriculas, 'data_criacao')) {
            $matricula_data['data_criacao'] = current_time('mysql');
        }
        if ($lote_id !== '' && function_exists('sige_db_column_exists')) {
            if (sige_db_column_exists($tbl_matriculas, 'importacao_lote_id')) {
                $matricula_data['importacao_lote_id'] = $lote_id;
            }
            if (sige_db_column_exists($tbl_matriculas, 'importado_em')) {
                $matricula_data['importado_em'] = current_time('mysql');
            }
            if (sige_db_column_exists($tbl_matriculas, 'importado_por')) {
                $matricula_data['importado_por'] = get_current_user_id();
            }
        }

        $mat_ok = $wpdb->insert($tbl_matriculas, $matricula_data);
        if ($mat_ok === false) {
            $matricula_error = $wpdb->last_error;
            // Importação deve ser atómica por linha do ponto de vista operacional:
            // sem matrícula válida, o aluno recém-criado é removido para não deixar ficha órfã.
            $wpdb->delete($tbl_alunos, ['id' => $aluno_id, 'escola_id' => $escola_id], ['%d', '%d']);
            $stats['erros']++;
            $detalhes[] = [
                'linha' => $linha,
                'estado' => 'erro',
                'nome' => $nome,
                'mensagem' => 'não importado porque a matrícula falhou: ' . $matricula_error,
            ];
            continue;
        }

        $stats['importados']++;
        if ($has_pending_contacts) {
            $detalhes[] = [
                'linha' => $linha,
                'estado' => 'pendente',
                'nome' => $nome,
                'mensagem' => 'importado com contactos dos pais pendentes de confirmação',
            ];
        } else {
            $detalhes[] = [
                'linha' => $linha,
                'estado' => 'importado',
                'nome' => $nome,
                'mensagem' => 'importado e matriculado',
            ];
        }
    }

    if ($modo_importacao === 'confirm' && $lote_id !== '') {
        sige_import_alunos_save_batch_history([
            'escola_id'     => $escola_id,
            'lote_id'       => $lote_id,
            'ano_lectivo'   => $ano_lectivo,
            'turma_id'      => $turma_id,
            'turma_nome'    => trim(($turma->classe ?? '') . ' - ' . ($turma->nome ?? '')),
            'ficheiro'      => $nome_original,
            'total_linhas'  => (int)$stats['total_linhas'],
            'importaveis'   => (int)$stats['importaveis'],
            'importados'    => (int)$stats['importados'],
            'pendentes'     => (int)$stats['pendentes'],
            'erros'         => (int)$stats['erros'],
            'duplicados'    => (int)$stats['duplicados'],
            'estado'        => ((int)$stats['importados'] > 0 ? 'activo' : 'sem_registos'),
            'resumo_json'   => wp_json_encode(['stats' => $stats, 'ids' => array_slice($importados_ids, 0, 500)]),
            'detalhes_json' => wp_json_encode(array_slice($detalhes, 0, 200)),
            'criado_em'     => current_time('mysql'),
            'criado_por'    => get_current_user_id(),
            'actualizado_em'=> current_time('mysql'),
        ]);
    }

    if (function_exists('sige_audit_log')) {
        sige_audit_log($modo_importacao === 'preview' ? 'alunos_importacao_ficheiro_prevalidada' : 'alunos_importacao_ficheiro_confirmada', [
            'turma_id' => $turma_id,
            'ano_lectivo' => $ano_lectivo,
            'ficheiro' => $nome_original,
            'modo' => $modo_importacao,
            'lote_id' => $lote_id,
            'preview_hash' => $modo_importacao === 'preview' ? substr($preview_hash, 0, 12) : null,
            'stats' => $stats,
            'ids' => array_slice($importados_ids, 0, 100),
        ], 'alunos');
    }

    $response = [
        'mode' => $modo_importacao,
        'stats' => $stats,
        'turma' => trim(($turma->classe ?? '') . ' - ' . ($turma->nome ?? '')),
        'ano_lectivo' => $ano_lectivo,
        'detalhes' => array_slice($detalhes, 0, 100),
        'truncado' => count($detalhes) > 100,
    ];

    if ($modo_importacao === 'preview') {
        $response['preview_hash'] = $preview_hash;
    } else {
        $response['lote_id'] = $lote_id;
    }

    wp_send_json_success($response);
}

// ======================================================================
// AJAX: sige_anular_lote_importacao_alunos
// ======================================================================
// Anulação segura de um lote de importação. A operação é automática no
// sentido operacional: o utilizador informa/confirma o lote e o sistema
// verifica dependências, remove matrículas criadas pela importação e apaga
// os alunos apenas quando ainda não há uso académico, financeiro,
// documental, de portaria, transporte ou comunicação.
// ======================================================================
add_action('wp_ajax_sige_anular_lote_importacao_alunos', 'sige_ajax_anular_lote_importacao_alunos');

function sige_ajax_anular_lote_importacao_alunos() {
    sige_check_nonce_global();

    if (!sige_alunos_user_can_manage()) {
        wp_send_json_error('Sem permissão para anular lotes de importação.');
    }

    global $wpdb;

    $escola_id = sige_require_escola_id('anular_lote_importacao');
    $lote_id   = isset($_POST['lote_id']) ? sanitize_text_field(wp_unslash($_POST['lote_id'])) : '';

    if ($lote_id === '' || strlen($lote_id) > 100 || !preg_match('/^SGIMP-[0-9]+-[0-9]{8}-[0-9]{6}-[A-Z0-9]{4,16}$/', $lote_id)) {
        wp_send_json_error('Informe um ID de lote válido. Exemplo: SGIMP-1-20260605-103000-ABC123.');
    }

    $tbl_alunos     = $wpdb->prefix . 'sige_alunos';
    $tbl_matriculas = $wpdb->prefix . 'sige_matriculas';

    $alunos = sige_import_alunos_get_batch_students($lote_id, $escola_id);
    if (empty($alunos)) {
        wp_send_json_error('Lote não encontrado nesta escola. Confirme se copiou correctamente o ID do lote.');
    }

    if (count($alunos) > 2000) {
        wp_send_json_error('Este lote tem mais de 2000 alunos. Por segurança, a anulação automática foi bloqueada e deve ser analisada tecnicamente.');
    }

    $aluno_ids = array_map(function($a) { return (int)$a->id; }, $alunos);
    $blockers = sige_import_alunos_batch_blockers($aluno_ids, $escola_id, $lote_id);

    if (!empty($blockers)) {
        sige_import_alunos_mark_batch_blocked($lote_id, $escola_id, $blockers, $alunos);
        if (function_exists('sige_audit_log')) {
            sige_audit_log('alunos_importacao_lote_anulacao_bloqueada', [
                'lote_id' => $lote_id,
                'alunos_total' => count($aluno_ids),
                'bloqueios' => $blockers,
            ], 'alunos');
        }
        $human = [];
        foreach ($blockers as $b) {
            $human[] = $b['label'] . ' (' . (int)$b['count'] . ')';
        }
        wp_send_json_error('A anulação automática foi bloqueada porque o lote já tem utilização posterior: ' . implode('; ', $human) . '. Anule apenas lotes ainda limpos ou faça uma revisão técnica antes de remover dados ligados.');
    }

    sige_import_alunos_ensure_batch_history_from_students($lote_id, $escola_id, $alunos);

    $placeholders = implode(',', array_fill(0, count($aluno_ids), '%d'));
    $params = array_merge([$escola_id], $aluno_ids);

    $wpdb->query('START TRANSACTION');

    $sql_m = "DELETE FROM {$tbl_matriculas} WHERE escola_id = %d AND aluno_id IN ({$placeholders})";
    $mat_deleted = $wpdb->query($wpdb->prepare($sql_m, $params));
    if ($mat_deleted === false) {
        $err = $wpdb->last_error;
        $wpdb->query('ROLLBACK');
        wp_send_json_error('Não foi possível remover as matrículas do lote: ' . $err);
    }

    $sql_a = "DELETE FROM {$tbl_alunos} WHERE escola_id = %d AND id IN ({$placeholders})";
    $alunos_deleted = $wpdb->query($wpdb->prepare($sql_a, $params));
    if ($alunos_deleted === false || (int)$alunos_deleted !== count($aluno_ids)) {
        $err = $wpdb->last_error;
        $wpdb->query('ROLLBACK');
        wp_send_json_error('A anulação foi interrompida por divergência de integridade. Nenhum dado foi removido. ' . $err);
    }

    $wpdb->query('COMMIT');

    sige_import_alunos_mark_batch_annulled($lote_id, $escola_id, (int)$alunos_deleted, (int)$mat_deleted);

    if (function_exists('sige_clear_student_dashboard_caches')) {
        sige_clear_student_dashboard_caches();
    }

    if (function_exists('sige_audit_log')) {
        sige_audit_log('alunos_importacao_lote_anulado', [
            'lote_id' => $lote_id,
            'alunos_total' => (int)$alunos_deleted,
            'matriculas_total' => (int)$mat_deleted,
            'ids' => array_slice($aluno_ids, 0, 200),
        ], 'alunos');
    }

    $nomes = array_map(function($a) {
        return (string)$a->nome_completo;
    }, array_slice($alunos, 0, 10));

    wp_send_json_success([
        'lote_id' => $lote_id,
        'alunos_anulados' => (int)$alunos_deleted,
        'matriculas_removidas' => (int)$mat_deleted,
        'amostra' => $nomes,
        'truncado' => count($alunos) > 10,
        'message' => 'Lote anulado com segurança. Os alunos e as matrículas criadas por esta importação foram removidos.',
    ]);
}


// ======================================================================
// AJAX: sige_listar_lotes_importacao_alunos
// ======================================================================
// Histórico visual dos lotes de importação. A listagem usa a tabela
// persistente v12.11.9.40 e mantém fallback para lotes antigos ainda
// existentes em alunos/matrículas.
// ======================================================================
add_action('wp_ajax_sige_listar_lotes_importacao_alunos', 'sige_ajax_listar_lotes_importacao_alunos');

function sige_ajax_listar_lotes_importacao_alunos() {
    sige_check_nonce_global();

    if (!sige_alunos_user_can_manage()) {
        wp_send_json_error('Sem permissão para consultar o histórico de importações.');
    }

    global $wpdb;
    $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
    $limit = max(5, min(50, $limit));

    $lotes = [];
    $seen = [];
    $tbl_lotes = $wpdb->prefix . 'sige_alunos_importacao_lotes';

    if (sige_import_alunos_table_exists($tbl_lotes)) {
        $tbl_turmas = $wpdb->prefix . 'sige_turmas';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name AS criado_por_nome, ua.display_name AS anulado_por_nome,
                    t.nome AS turma_nome_real, t.classe AS turma_classe
             FROM {$tbl_lotes} l
             LEFT JOIN {$wpdb->users} u ON u.ID = l.criado_por
             LEFT JOIN {$wpdb->users} ua ON ua.ID = l.anulado_por
             LEFT JOIN {$tbl_turmas} t ON t.id = l.turma_id AND t.escola_id = l.escola_id
             WHERE l.escola_id = %d
             ORDER BY COALESCE(l.criado_em, '1970-01-01') DESC, l.id DESC
             LIMIT %d",
            $escola_id,
            $limit
        ));

        foreach ((array)$rows as $row) {
            $item = sige_import_alunos_history_row_to_payload($row);
            $lotes[] = $item;
            $seen[$item['lote_id']] = true;
        }
    }

    if (count($lotes) < $limit) {
        $legacy = sige_import_alunos_list_legacy_batches($escola_id, array_keys($seen), $limit - count($lotes));
        foreach ($legacy as $item) {
            $lotes[] = $item;
            $seen[$item['lote_id']] = true;
        }
    }

    usort($lotes, function($a, $b) {
        return strcmp((string)($b['criado_em'] ?? ''), (string)($a['criado_em'] ?? ''));
    });

    $lotes = array_slice($lotes, 0, $limit);

    wp_send_json_success([
        'lotes' => $lotes,
        'total' => count($lotes),
        'generated_at' => current_time('mysql'),
    ]);
}

function sige_import_alunos_save_batch_history(array $payload): void {
    global $wpdb;
    $tbl = $wpdb->prefix . 'sige_alunos_importacao_lotes';
    if (!sige_import_alunos_table_exists($tbl)) {
        return;
    }

    $escola_id = isset($payload['escola_id']) ? (int)$payload['escola_id'] : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
    if ($escola_id <= 0) { return; }
    $lote_id = isset($payload['lote_id']) ? sanitize_text_field((string)$payload['lote_id']) : '';
    if ($lote_id === '') {
        return;
    }

    $allowed = [
        'escola_id','lote_id','ano_lectivo','turma_id','turma_nome','ficheiro','total_linhas','importaveis','importados',
        'pendentes','erros','duplicados','estado','resumo_json','detalhes_json','criado_em','criado_por','anulado_em',
        'anulado_por','bloqueado_em','ultimo_bloqueio_json','actualizado_em'
    ];

    $data = [];
    foreach ($allowed as $col) {
        if (!array_key_exists($col, $payload)) {
            continue;
        }
        if (!sige_import_alunos_column_exists($tbl, $col)) {
            continue;
        }
        $val = $payload[$col];
        if (in_array($col, ['escola_id','ano_lectivo','turma_id','total_linhas','importaveis','importados','pendentes','erros','duplicados','criado_por','anulado_por'], true)) {
            $val = ($val === null || $val === '') ? null : (int)$val;
        } elseif ($col === 'estado') {
            $val = sanitize_key((string)$val);
            if ($val === '') $val = 'activo';
        } elseif (in_array($col, ['turma_nome','ficheiro','lote_id'], true)) {
            $val = sanitize_text_field((string)$val);
        } elseif (in_array($col, ['resumo_json','detalhes_json','ultimo_bloqueio_json'], true)) {
            $val = is_string($val) ? $val : wp_json_encode($val);
        } elseif ($val !== null) {
            $val = sanitize_text_field((string)$val);
        }
        $data[$col] = $val;
    }

    if (empty($data)) {
        return;
    }

    $existing_id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$tbl} WHERE escola_id = %d AND lote_id = %s LIMIT 1",
        $escola_id,
        $lote_id
    ));

    if ($existing_id > 0) {
        $wpdb->update($tbl, $data, ['id' => $existing_id, 'escola_id' => $escola_id]);
    } else {
        if (!isset($data['escola_id'])) $data['escola_id'] = $escola_id;
        if (!isset($data['lote_id'])) $data['lote_id'] = $lote_id;
        if (!isset($data['criado_em'])) $data['criado_em'] = current_time('mysql');
        if (!isset($data['actualizado_em'])) $data['actualizado_em'] = current_time('mysql');
        $wpdb->insert($tbl, $data);
    }
}

function sige_import_alunos_history_row_to_payload($row): array {
    $turma = '';
    if (!empty($row->turma_classe) || !empty($row->turma_nome_real)) {
        $turma = trim((string)$row->turma_classe . ' - ' . (string)$row->turma_nome_real, " -\t\n\r\0\x0B");
    }
    if ($turma === '' && !empty($row->turma_nome)) {
        $turma = (string)$row->turma_nome;
    }

    $estado = sanitize_key((string)($row->estado ?? 'activo'));
    if ($estado === '') $estado = 'activo';
    $pode_anular = ($estado === 'activo' && (int)($row->importados ?? 0) > 0);

    return [
        'lote_id' => (string)($row->lote_id ?? ''),
        'criado_em' => (string)($row->criado_em ?? ''),
        'ano_lectivo' => (int)($row->ano_lectivo ?? 0),
        'turma' => $turma,
        'turma_id' => (int)($row->turma_id ?? 0),
        'ficheiro' => (string)($row->ficheiro ?? ''),
        'total_linhas' => (int)($row->total_linhas ?? 0),
        'importaveis' => (int)($row->importaveis ?? 0),
        'importados' => (int)($row->importados ?? 0),
        'pendentes' => (int)($row->pendentes ?? 0),
        'erros' => (int)($row->erros ?? 0),
        'duplicados' => (int)($row->duplicados ?? 0),
        'estado' => $estado,
        'criado_por' => (string)($row->criado_por_nome ?? ''),
        'anulado_em' => (string)($row->anulado_em ?? ''),
        'anulado_por' => (string)($row->anulado_por_nome ?? ''),
        'bloqueado_em' => (string)($row->bloqueado_em ?? ''),
        'pode_anular' => $pode_anular,
        'origem' => 'historico',
    ];
}

function sige_import_alunos_list_legacy_batches(int $escola_id, array $exclude_lotes, int $limit): array {
    global $wpdb;
    $tbl_alunos = $wpdb->prefix . 'sige_alunos';
    if ($limit <= 0 || !sige_import_alunos_table_exists($tbl_alunos) || !sige_import_alunos_column_exists($tbl_alunos, 'importacao_lote_id')) {
        return [];
    }

    $where_exclude = '';
    $params = [$escola_id];
    $exclude_lotes = array_values(array_filter(array_map('strval', $exclude_lotes)));
    if (!empty($exclude_lotes)) {
        $ph_ex = implode(',', array_fill(0, count($exclude_lotes), '%s'));
        $where_exclude = " AND a.importacao_lote_id NOT IN ({$ph_ex})";
        $params = array_merge($params, $exclude_lotes);
    }

    $tbl_matriculas = $wpdb->prefix . 'sige_matriculas';
    $tbl_turmas = $wpdb->prefix . 'sige_turmas';
    $has_importado_em = sige_import_alunos_column_exists($tbl_alunos, 'importado_em');
    $has_importado_por = sige_import_alunos_column_exists($tbl_alunos, 'importado_por');
    $created_expr = $has_importado_em ? "MIN(COALESCE(a.importado_em, a.data_registo))" : "MIN(a.data_registo)";
    $user_expr = $has_importado_por ? "MAX(a.importado_por)" : "0";

    $join_m = sige_import_alunos_table_exists($tbl_matriculas) ? "LEFT JOIN {$tbl_matriculas} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id" : "";
    $join_t = sige_import_alunos_table_exists($tbl_turmas) ? "LEFT JOIN {$tbl_turmas} t ON t.id = m.turma_id AND t.escola_id = a.escola_id" : "";

    $params[] = $limit;
    $sql = "SELECT a.importacao_lote_id AS lote_id,
                   COUNT(DISTINCT a.id) AS importados,
                   SUM(CASE WHEN COALESCE(a.observacoes, '') LIKE '%contactos/nome dos pais pendentes%' THEN 1 ELSE 0 END) AS pendentes,
                   {$created_expr} AS criado_em,
                   {$user_expr} AS criado_por,
                   MAX(m.turma_id) AS turma_id,
                   MAX(m.ano_lectivo) AS ano_lectivo,
                   MAX(t.nome) AS turma_nome,
                   MAX(t.classe) AS turma_classe,
                   MAX(u.display_name) AS criado_por_nome
            FROM {$tbl_alunos} a
            {$join_m}
            {$join_t}
            LEFT JOIN {$wpdb->users} u ON u.ID = " . ($has_importado_por ? "a.importado_por" : "0") . "
            WHERE a.escola_id = %d
              AND a.importacao_lote_id IS NOT NULL
              AND a.importacao_lote_id <> ''
              {$where_exclude}
            GROUP BY a.importacao_lote_id
            ORDER BY criado_em DESC
            LIMIT %d";

    $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
    $out = [];
    foreach ((array)$rows as $row) {
        $turma = trim((string)($row->turma_classe ?? '') . ' - ' . (string)($row->turma_nome ?? ''), " -\t\n\r\0\x0B");
        $out[] = [
            'lote_id' => (string)$row->lote_id,
            'criado_em' => (string)$row->criado_em,
            'ano_lectivo' => (int)$row->ano_lectivo,
            'turma' => $turma,
            'turma_id' => (int)$row->turma_id,
            'ficheiro' => '',
            'total_linhas' => (int)$row->importados,
            'importaveis' => (int)$row->importados,
            'importados' => (int)$row->importados,
            'pendentes' => (int)$row->pendentes,
            'erros' => 0,
            'duplicados' => 0,
            'estado' => 'activo',
            'criado_por' => (string)($row->criado_por_nome ?? ''),
            'anulado_em' => '',
            'anulado_por' => '',
            'bloqueado_em' => '',
            'pode_anular' => ((int)$row->importados > 0),
            'origem' => 'reconstruido',
        ];
    }
    return $out;
}

function sige_import_alunos_ensure_batch_history_from_students(string $lote_id, int $escola_id, array $alunos): void {
    global $wpdb;
    $tbl_lotes = $wpdb->prefix . 'sige_alunos_importacao_lotes';
    if (!sige_import_alunos_table_exists($tbl_lotes) || $lote_id === '' || empty($alunos)) {
        return;
    }

    $exists = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$tbl_lotes} WHERE escola_id = %d AND lote_id = %s LIMIT 1",
        $escola_id,
        $lote_id
    ));
    if ($exists > 0) {
        return;
    }

    $ids = array_map(function($a) { return (int)$a->id; }, $alunos);
    $ids = array_values(array_filter($ids));
    if (empty($ids)) {
        return;
    }

    $tbl_matriculas = $wpdb->prefix . 'sige_matriculas';
    $tbl_turmas = $wpdb->prefix . 'sige_turmas';
    $ph = implode(',', array_fill(0, count($ids), '%d'));
    $mat = null;
    if (sige_import_alunos_table_exists($tbl_matriculas)) {
        $mat = $wpdb->get_row($wpdb->prepare(
            "SELECT MAX(m.turma_id) AS turma_id, MAX(m.ano_lectivo) AS ano_lectivo,
                    MAX(t.nome) AS turma_nome, MAX(t.classe) AS turma_classe
             FROM {$tbl_matriculas} m
             LEFT JOIN {$tbl_turmas} t ON t.id = m.turma_id AND t.escola_id = m.escola_id
             WHERE m.escola_id = %d AND m.aluno_id IN ({$ph})",
            array_merge([$escola_id], $ids)
        ));
    }

    $tbl_alunos = $wpdb->prefix . 'sige_alunos';
    $criado_em = current_time('mysql');
    $criado_por = 0;
    if (sige_import_alunos_table_exists($tbl_alunos) && sige_import_alunos_column_exists($tbl_alunos, 'importacao_lote_id')) {
        $has_importado_em = sige_import_alunos_column_exists($tbl_alunos, 'importado_em');
        $has_importado_por = sige_import_alunos_column_exists($tbl_alunos, 'importado_por');
        $expr_time = $has_importado_em ? 'MIN(COALESCE(importado_em, data_registo))' : 'MIN(data_registo)';
        $expr_user = $has_importado_por ? 'MAX(importado_por)' : '0';
        $meta = $wpdb->get_row($wpdb->prepare(
            "SELECT {$expr_time} AS criado_em, {$expr_user} AS criado_por FROM {$tbl_alunos} WHERE escola_id = %d AND importacao_lote_id = %s",
            $escola_id,
            $lote_id
        ));
        if ($meta && !empty($meta->criado_em)) $criado_em = (string)$meta->criado_em;
        if ($meta && !empty($meta->criado_por)) $criado_por = (int)$meta->criado_por;
    }

    $turma_nome = '';
    if ($mat) {
        $turma_nome = trim((string)($mat->turma_classe ?? '') . ' - ' . (string)($mat->turma_nome ?? ''), " -\t\n\r\0\x0B");
    }

    sige_import_alunos_save_batch_history([
        'escola_id' => $escola_id,
        'lote_id' => $lote_id,
        'ano_lectivo' => $mat ? (int)$mat->ano_lectivo : 0,
        'turma_id' => $mat ? (int)$mat->turma_id : 0,
        'turma_nome' => $turma_nome,
        'ficheiro' => 'Histórico reconstruído',
        'total_linhas' => count($ids),
        'importaveis' => count($ids),
        'importados' => count($ids),
        'pendentes' => 0,
        'erros' => 0,
        'duplicados' => 0,
        'estado' => 'activo',
        'resumo_json' => wp_json_encode(['reconstruido' => true, 'ids' => array_slice($ids, 0, 500)]),
        'criado_em' => $criado_em,
        'criado_por' => $criado_por,
        'actualizado_em' => current_time('mysql'),
    ]);
}

function sige_import_alunos_mark_batch_blocked(string $lote_id, int $escola_id, array $blockers, array $alunos): void {
    if ($lote_id === '') return;
    sige_import_alunos_ensure_batch_history_from_students($lote_id, $escola_id, $alunos);
    sige_import_alunos_save_batch_history([
        'escola_id' => $escola_id,
        'lote_id' => $lote_id,
        'bloqueado_em' => current_time('mysql'),
        'ultimo_bloqueio_json' => wp_json_encode(['blockers' => $blockers, 'checked_at' => current_time('mysql')]),
        'actualizado_em' => current_time('mysql'),
    ]);
}

function sige_import_alunos_mark_batch_annulled(string $lote_id, int $escola_id, int $alunos_deleted, int $mat_deleted): void {
    if ($lote_id === '') return;
    sige_import_alunos_save_batch_history([
        'escola_id' => $escola_id,
        'lote_id' => $lote_id,
        'estado' => 'anulado',
        'anulado_em' => current_time('mysql'),
        'anulado_por' => get_current_user_id(),
        'actualizado_em' => current_time('mysql'),
        'resumo_json' => wp_json_encode([
            'anulado' => true,
            'alunos_anulados' => $alunos_deleted,
            'matriculas_removidas' => $mat_deleted,
            'anulado_em' => current_time('mysql'),
        ]),
    ]);
}

function sige_import_alunos_column_exists($table, $column): bool {
    global $wpdb;
    if ($table === '' || $column === '') return false;
    if (function_exists('sige_db_column_exists')) {
        return (bool)sige_db_column_exists($table, $column);
    }
    return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
}

function sige_import_alunos_get_batch_students($lote_id, $escola_id) {
    global $wpdb;
    $tbl_alunos = $wpdb->prefix . 'sige_alunos';
    if (!sige_import_alunos_table_exists($tbl_alunos)) {
        return [];
    }

    $by_id = [];

    if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_alunos, 'importacao_lote_id')) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome_completo, numero_processo FROM {$tbl_alunos} WHERE escola_id = %d AND importacao_lote_id = %s ORDER BY id ASC",
            $escola_id, $lote_id
        ));
        foreach ((array)$rows as $r) {
            $by_id[(int)$r->id] = $r;
        }
    }

    // Compatibilidade com lotes criados na versão anterior, quando o ID ficava
    // apenas nas observações do aluno.
    if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_alunos, 'observacoes')) {
        $like = '%' . $wpdb->esc_like('LOTE DE IMPORTAÇÃO: ' . $lote_id) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome_completo, numero_processo FROM {$tbl_alunos} WHERE escola_id = %d AND observacoes LIKE %s ORDER BY id ASC",
            $escola_id, $like
        ));
        foreach ((array)$rows as $r) {
            $by_id[(int)$r->id] = $r;
        }
    }

    return array_values($by_id);
}

function sige_import_alunos_batch_blockers(array $aluno_ids, int $escola_id, string $lote_id): array {
    global $wpdb;

    $blockers = [];
    if (empty($aluno_ids)) {
        return $blockers;
    }

    $checks = [
        ['sige_turma_alunos', 'aluno_id', 'alocações adicionais de turma'],
        ['sige_notas', 'aluno_id', 'notas académicas'],
        ['sige_acessos', 'aluno_id', 'registos de portaria'],
        ['sige_jardim_criterios_respostas', 'aluno_id', 'avaliações do Jardim'],
        ['sige_jardim_avaliacoes', 'aluno_id', 'avaliações do Jardim'],
        ['sige_jardim_diario', 'aluno_id', 'diário do Jardim'],
        ['sige_jardim_saude', 'aluno_id', 'registos de saúde do Jardim'],
        ['sige_transporte_alunos', 'aluno_id', 'transporte escolar atribuído'],
        ['sige_fin_lancamentos', 'aluno_id', 'lançamentos financeiros'],
        ['sige_fin_pagamentos', 'aluno_id', 'pagamentos registados'],
        ['sige_fin_pagamentos_anuais', 'aluno_id', 'pagamentos anuais'],
        ['sige_fin_creditos', 'aluno_id', 'créditos financeiros'],
        ['sige_fin_contactos_cobranca', 'aluno_id', 'histórico de cobrança'],
        ['sige_fin_planos_pagamento', 'aluno_id', 'planos de pagamento'],
        ['sige_whatsapp_queue', 'aluno_id', 'mensagens WhatsApp na fila/histórico'],
        ['sige_financeiro', 'aluno_id', 'financeiro legado'],
    ];

    foreach ($checks as $check) {
        $table = $wpdb->prefix . $check[0];
        $count = sige_import_alunos_count_rows_for_ids($table, $check[1], $aluno_ids, $escola_id);
        if ($count > 0) {
            $blockers[] = ['key' => $check[0], 'label' => $check[2], 'count' => $count];
        }
    }

    // Matrículas: a matrícula gerada pela própria importação é removível.
    // Matrículas múltiplas/posteriores bloqueiam para evitar apagar histórico real.
    $tbl_matriculas = $wpdb->prefix . 'sige_matriculas';
    if (sige_import_alunos_table_exists($tbl_matriculas) && function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_matriculas, 'aluno_id')) {
        $ph = implode(',', array_fill(0, count($aluno_ids), '%d'));
        $params = array_merge([$escola_id], $aluno_ids);
        $school_clause = sige_db_column_exists($tbl_matriculas, 'escola_id') ? 'escola_id = %d AND ' : '';
        if ($school_clause === '') {
            $params = $aluno_ids;
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT aluno_id, COUNT(*) AS total FROM {$tbl_matriculas} WHERE {$school_clause} aluno_id IN ({$ph}) GROUP BY aluno_id HAVING COUNT(*) > 1",
            $params
        ));
        if (!empty($rows)) {
            $blockers[] = ['key' => 'sige_matriculas_multiplas', 'label' => 'múltiplas matrículas por aluno', 'count' => count($rows)];
        }

        if (sige_db_column_exists($tbl_matriculas, 'importacao_lote_id')) {
            $params2 = array_merge([$escola_id, $lote_id], $aluno_ids);
            $count_other = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tbl_matriculas} WHERE escola_id = %d AND importacao_lote_id IS NOT NULL AND importacao_lote_id <> %s AND aluno_id IN ({$ph})",
                $params2
            ));
            if ($count_other > 0) {
                $blockers[] = ['key' => 'sige_matriculas_outro_lote', 'label' => 'matrículas ligadas a outro lote', 'count' => $count_other];
            }
        }
    }

    $doc_count = sige_import_alunos_count_document_or_profile_data($aluno_ids, $escola_id);
    if ($doc_count > 0) {
        $blockers[] = ['key' => 'alunos_dados_posteriores', 'label' => 'documentos, foto, família ou serviços já actualizados na ficha', 'count' => $doc_count];
    }

    return $blockers;
}

function sige_import_alunos_table_exists($table): bool {
    global $wpdb;
    if ($table === '') return false;
    return (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
}

function sige_import_alunos_count_rows_for_ids($table, $column, array $ids, int $escola_id): int {
    global $wpdb;
    if (empty($ids) || !sige_import_alunos_table_exists($table)) return 0;
    if (!function_exists('sige_db_column_exists') || !sige_db_column_exists($table, $column)) return 0;

    $ph = implode(',', array_fill(0, count($ids), '%d'));
    $has_school = sige_db_column_exists($table, 'escola_id');
    if ($has_school) {
        $params = array_merge([$escola_id], $ids);
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE escola_id = %d AND {$column} IN ({$ph})",
            $params
        ));
    }

    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE {$column} IN ({$ph})",
        $ids
    ));
}

function sige_import_alunos_count_document_or_profile_data(array $ids, int $escola_id): int {
    global $wpdb;
    if (empty($ids)) return 0;

    $tbl = $wpdb->prefix . 'sige_alunos';
    if (!sige_import_alunos_table_exists($tbl)) return 0;

    $conditions = [];
    $text_cols = ['foto', 'doc_bi_url', 'doc_cert_url', 'doc_vacina_url'];
    foreach ($text_cols as $col) {
        if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl, $col)) {
            $conditions[] = "COALESCE({$col}, '') <> ''";
        }
    }
    $numeric_cols = ['familia_id', 'rota_transporte_id'];
    foreach ($numeric_cols as $col) {
        if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl, $col)) {
            $conditions[] = "COALESCE({$col}, 0) > 0";
        }
    }

    if (empty($conditions)) return 0;

    $ph = implode(',', array_fill(0, count($ids), '%d'));
    $params = array_merge([$escola_id], $ids);
    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$tbl} WHERE escola_id = %d AND id IN ({$ph}) AND (" . implode(' OR ', $conditions) . ")",
        $params
    ));
}

function sige_import_alunos_preview_hash($headers, $rows, $turma_id, $escola_id, $ano_lectivo, $permitir_pendentes) {
    $payload = wp_json_encode([
        'headers' => array_values($headers),
        'rows' => array_values($rows),
        'turma_id' => (int)$turma_id,
        'escola_id' => (int)$escola_id,
        'ano_lectivo' => (int)$ano_lectivo,
        'permitir_pendentes' => (bool)$permitir_pendentes,
    ]);
    return hash_hmac('sha256', (string)$payload, wp_salt('auth'));
}

function sige_import_alunos_generate_batch_id($escola_id) {
    $rand = function_exists('wp_generate_password') ? strtoupper(wp_generate_password(6, false, false)) : strtoupper(substr(md5(uniqid('', true)), 0, 6));
    return 'SGIMP-' . (int)$escola_id . '-' . wp_date('Ymd-His') . '-' . $rand;
}

function sige_import_alunos_parse_csv($path) {
    if (!is_readable($path)) {
        return new WP_Error('sige_import_csv_unreadable', 'Não foi possível ler o ficheiro carregado.');
    }

    $sample = file_get_contents($path, false, null, 0, 4096);
    if ($sample === false || trim($sample) === '') {
        return new WP_Error('sige_import_csv_empty', 'O ficheiro CSV está vazio.');
    }

    $delimiter = sige_import_alunos_detect_delimiter($sample);
    $handle = fopen($path, 'r');
    if (!$handle) {
        return new WP_Error('sige_import_csv_open', 'Não foi possível abrir o ficheiro CSV.');
    }

    $raw_headers = fgetcsv($handle, 0, $delimiter);
    if (!$raw_headers || count($raw_headers) < 2) {
        fclose($handle);
        return new WP_Error('sige_import_csv_headers', 'O CSV precisa de cabeçalhos válidos. Use o modelo de importação do SoftGenial.');
    }

    $headers = [];
    foreach ($raw_headers as $h) {
        $headers[] = sige_import_alunos_header_key($h);
    }

    $rows = [];
    while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($data === [null] || $data === false) continue;
        $row = [];
        foreach ($headers as $i => $key) {
            if ($key === '') continue;
            $row[$key] = isset($data[$i]) ? trim((string)$data[$i]) : '';
        }
        $rows[] = $row;
    }
    fclose($handle);

    return [
        'headers' => array_values(array_unique($headers)),
        'rows' => $rows,
    ];
}

function sige_import_alunos_detect_delimiter($sample) {
    $first_line = strtok($sample, "\r\n");
    $candidates = [";", ",", "\t"];
    $best = ';';
    $best_count = -1;
    foreach ($candidates as $candidate) {
        $count = substr_count($first_line, $candidate);
        if ($count > $best_count) {
            $best = $candidate;
            $best_count = $count;
        }
    }
    return $best;
}

function sige_import_alunos_header_key($header) {
    $h = trim((string)$header);
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
    if (function_exists('remove_accents')) {
        $h = remove_accents($h);
    }
    $h = strtolower($h);
    $h = preg_replace('/[^a-z0-9]+/', '_', $h);
    $h = trim($h, '_');

    $map = [
        'nome' => 'nome_completo',
        'nome_do_aluno' => 'nome_completo',
        'nome_aluno' => 'nome_completo',
        'aluno' => 'nome_completo',
        'estudante' => 'nome_completo',
        'nome_completo' => 'nome_completo',
        'data_nascimento' => 'data_nascimento',
        'data_de_nascimento' => 'data_nascimento',
        'nascimento' => 'data_nascimento',
        'dt_nascimento' => 'data_nascimento',
        'genero' => 'genero',
        'sexo' => 'genero',
        'classe' => 'classe',
        'classe_ref' => 'classe',
        'turma' => 'turma',
        'turma_ref' => 'turma',
        'numero_processo' => 'numero_processo',
        'n_processo' => 'numero_processo',
        'nr_processo' => 'numero_processo',
        'processo' => 'numero_processo',
        'codigo' => 'numero_processo',
        'nome_pai' => 'nome_pai',
        'pai' => 'nome_pai',
        'nome_do_pai' => 'nome_pai',
        'telemovel_pai' => 'telemovel_pai',
        'telefone_pai' => 'telemovel_pai',
        'contacto_pai' => 'telemovel_pai',
        'celular_pai' => 'telemovel_pai',
        'nr_pai' => 'telemovel_pai',
        'nome_mae' => 'nome_mae',
        'mae' => 'nome_mae',
        'nome_da_mae' => 'nome_mae',
        'telemovel_mae' => 'telemovel_mae',
        'telefone_mae' => 'telemovel_mae',
        'contacto_mae' => 'telemovel_mae',
        'celular_mae' => 'telemovel_mae',
        'nr_mae' => 'telemovel_mae',
        'contacto_encarregado' => 'contacto_encarregado',
        'telefone_encarregado' => 'contacto_encarregado',
        'telemovel_encarregado' => 'contacto_encarregado',
        'whatsapp' => 'whatsapp_notificacoes',
        'whatsapp_notificacoes' => 'whatsapp_notificacoes',
        'email_pai' => 'email_pai',
        'email_mae' => 'email_mae',
        'email_encarregado' => 'email_encarregado',
        'documento' => 'documento_nr',
        'documento_nr' => 'documento_nr',
        'documento_numero' => 'documento_nr',
        'numero_documento' => 'documento_nr',
        'tipo_documento' => 'tipo_documento',
        'bairro' => 'bairro',
        'nacionalidade' => 'nacionalidade',
        'estado' => 'status',
        'status' => 'status',
        'observacao' => 'observacoes',
        'observacoes' => 'observacoes',
    ];

    return $map[$h] ?? $h;
}

function sige_import_alunos_row_is_empty($row) {
    foreach ($row as $value) {
        if (trim((string)$value) !== '') return false;
    }
    return true;
}

function sige_import_alunos_parse_date($value) {
    $value = trim((string)$value);
    if ($value === '') return '';

    // Suporte a datas vindas de Excel .xlsx como número serial.
    if (preg_match('/^\d+(?:\.0+)?$/', $value)) {
        $serial = (int)round((float)$value);
        if ($serial > 20000 && $serial < 60000) {
            try {
                $base = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
                return $base->modify('+' . $serial . ' days')->format('Y-m-d');
            } catch (Exception $e) {
                return '';
            }
        }
    }

    $value = str_replace('\\', '/', $value);

    $y = $m = $d = 0;
    if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})$/', $value, $m1)) {
        $y = (int)$m1[1]; $m = (int)$m1[2]; $d = (int)$m1[3];
    } elseif (preg_match('/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})$/', $value, $m2)) {
        $d = (int)$m2[1]; $m = (int)$m2[2]; $y = (int)$m2[3];
    }

    if ($y >= 1900 && $y <= (int)wp_date('Y') && checkdate($m, $d, $y)) {
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    return '';
}

function sige_import_alunos_parse_gender($value) {
    $v = trim((string)$value);
    if (function_exists('remove_accents')) $v = remove_accents($v);
    $v = strtolower($v);
    if (in_array($v, ['m','masculino','male','homem'], true)) return 'M';
    if (in_array($v, ['f','feminino','female','mulher'], true)) return 'F';
    return '';
}

function sige_import_alunos_normalize_phone($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    $digits = preg_replace('/\D+/', '', $value);
    if (strpos($digits, '00258') === 0) {
        $digits = substr($digits, 5);
    } elseif (strpos($digits, '258') === 0 && strlen($digits) === 12) {
        $digits = substr($digits, 3);
    }
    if (preg_match('/^(82|83|84|85|86|87)\d{7}$/', $digits)) {
        return $digits;
    }
    return '';
}

function sige_import_alunos_next_numero_processo($tbl_alunos, $escola_id) {
    global $wpdb;
    $max_proc = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT MAX(CAST(numero_processo AS UNSIGNED)) FROM {$tbl_alunos} WHERE escola_id = %d",
        $escola_id
    ));
    return str_pad((string)($max_proc + 1), 5, '0', STR_PAD_LEFT);
}

