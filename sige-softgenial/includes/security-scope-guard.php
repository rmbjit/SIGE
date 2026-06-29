<?php
/**
 * SIGE SoftGenial - Security Scope Guard
 * v12.11.9.25 - Fase 1/P1.2
 *
 * Validação central de IDs por escola em AJAX/admin-post/admin views.
 * Objectivo: impedir que um utilizador autenticado envie manualmente IDs de
 * outra escola em acções críticas antes do handler funcional executar.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_phase1_scope_guard_param_ids')) {
    function sige_phase1_scope_guard_param_ids($raw, int $max = 100): array {
        if (function_exists('sige_secure_int_list_from_request')) {
            return sige_secure_int_list_from_request($raw, $max);
        }
        $parts = is_array($raw) ? $raw : explode(',', (string)$raw);
        $ids = [];
        foreach ($parts as $p) {
            $id = absint($p);
            if ($id > 0) $ids[$id] = $id;
            if (count($ids) >= $max) break;
        }
        return array_values($ids);
    }
}

if (!function_exists('sige_phase1_scope_guard_fail')) {
    function sige_phase1_scope_guard_fail(string $message, array $context = []): void {
        if (function_exists('sige_security_log')) {
            sige_security_log('tenant_scope_guard_denied', $message . ' ' . wp_json_encode($context));
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            wp_send_json_error($message, 403);
        }
        wp_die(esc_html($message), 'SIGE - Segurança', ['response' => 403]);
    }
}

if (!function_exists('sige_phase1_scope_guard_table_exists')) {
    function sige_phase1_scope_guard_table_exists(string $table): bool {
        global $wpdb;
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if ($table === '') return false;
        $full = $wpdb->prefix . $table;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full;
    }
}

if (!function_exists('sige_phase1_scope_guard_wp_user_belongs_to_school')) {
    /**
     * Verifica se um utilizador WP operacional pertence à escola actual.
     * Cobre staff via meta sige_escola_id / sige_professor_id / email e portal
     * via meta sige_aluno_id. Administradores WordPress reais não são alvo de
     * operações RH/SIGE comuns e são bloqueados para mutações por utilizadores
     * não técnicos dentro dos handlers funcionais.
     */
    function sige_phase1_scope_guard_wp_user_belongs_to_school(int $user_id, int $escola_id = 0): bool {
        global $wpdb;
        $user_id = absint($user_id);
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($user_id <= 0 || $escola_id <= 0) return false;

        $meta_school = (int)get_user_meta($user_id, 'sige_escola_id', true);
        if ($meta_school > 0) return $meta_school === $escola_id;

        if (function_exists('sige_permissions_tables')) {
            $pt = sige_permissions_tables();
            $ur = $pt['user_roles'] ?? '';
            if ($ur && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ur)) === $ur) {
                $ok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$ur} WHERE user_id=%d AND escola_id=%d AND ativo=1 LIMIT 1", $user_id, $escola_id));
                if ($ok > 0) return true;
            }
        }

        $prof_id = (int)get_user_meta($user_id, 'sige_professor_id', true);
        $tProf = $wpdb->prefix . 'sige_professores';
        if ($prof_id > 0 && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tProf)) === $tProf) {
            $ok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tProf} WHERE id=%d AND escola_id=%d LIMIT 1", $prof_id, $escola_id));
            if ($ok > 0) return true;
        }

        $user = get_user_by('ID', $user_id);
        if ($user && !empty($user->user_email) && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tProf)) === $tProf) {
            $ok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tProf} WHERE email=%s AND escola_id=%d LIMIT 1", $user->user_email, $escola_id));
            if ($ok > 0) return true;
        }

        $aluno_id = (int)get_user_meta($user_id, 'sige_aluno_id', true);
        $tAluno = $wpdb->prefix . 'sige_alunos';
        if ($aluno_id > 0 && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tAluno)) === $tAluno) {
            $ok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tAluno} WHERE id=%d AND escola_id=%d LIMIT 1", $aluno_id, $escola_id));
            if ($ok > 0) return true;
        }

        return false;
    }
}


if (!function_exists('sige_phase1_scope_guard_wp_user_has_any_school')) {
    function sige_phase1_scope_guard_wp_user_has_any_school(int $user_id): bool {
        global $wpdb;
        $user_id = absint($user_id);
        if ($user_id <= 0) return false;
        if ((int)get_user_meta($user_id, 'sige_escola_id', true) > 0) return true;
        if ((int)get_user_meta($user_id, 'sige_professor_id', true) > 0) return true;
        if ((int)get_user_meta($user_id, 'sige_aluno_id', true) > 0) return true;
        if (function_exists('sige_permissions_tables')) {
            $pt = sige_permissions_tables();
            $ur = $pt['user_roles'] ?? '';
            if ($ur && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ur)) === $ur) {
                $ok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$ur} WHERE user_id=%d AND ativo=1 LIMIT 1", $user_id));
                if ($ok > 0) return true;
            }
        }
        $user = get_user_by('ID', $user_id);
        if ($user && !empty($user->user_email)) {
            $tProf = $wpdb->prefix . 'sige_professores';
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tProf)) === $tProf) {
                $ok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tProf} WHERE email=%s LIMIT 1", $user->user_email));
                if ($ok > 0) return true;
            }
        }
        return false;
    }
}

if (!function_exists('sige_phase1_scope_guard_validate_wp_user')) {
    function sige_phase1_scope_guard_validate_wp_user(string $param, int $user_id): void {
        $user_id = absint($user_id);
        if ($user_id <= 0) return;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($eid <= 0) {
            sige_phase1_scope_guard_fail('Operação bloqueada: contexto de escola inválido.', [
                'param' => $param,
                'user_id' => $user_id,
                'action' => sanitize_key($_REQUEST['action'] ?? ''),
            ]);
        }
        if (!sige_phase1_scope_guard_wp_user_belongs_to_school($user_id, $eid)) {
            $action = sanitize_key($_REQUEST['action'] ?? '');
            // Atribuição inicial: administrador WordPress real pode vincular um utilizador ainda sem escola.
            // Utilizadores já vinculados a outra escola continuam bloqueados.
            if ($action === 'assign_user_role'
                && function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user()
                && function_exists('sige_phase1_scope_guard_wp_user_has_any_school')
                && !sige_phase1_scope_guard_wp_user_has_any_school($user_id)) {
                return;
            }
            sige_phase1_scope_guard_fail('Operação bloqueada: o utilizador solicitado não pertence à escola actual.', [
                'param' => $param,
                'user_id' => $user_id,
                'action'=> sanitize_key($_REQUEST['action'] ?? ''),
                'page'  => sanitize_key($_REQUEST['page'] ?? ''),
                'view'  => sanitize_key($_REQUEST['view'] ?? ''),
            ]);
        }
    }
}

if (!function_exists('sige_phase1_scope_guard_validate_id')) {
    function sige_phase1_scope_guard_validate_id(string $param, string $table, int $id): void {
        $id = absint($id);
        if ($id <= 0) return;
        if ($table === 'wp_user') {
            sige_phase1_scope_guard_validate_wp_user($param, $id);
            return;
        }
        if (!sige_phase1_scope_guard_table_exists($table)) {
            // Não bloquear instalações antigas em que uma tabela opcional ainda não existe.
            return;
        }
        if (!function_exists('sige_object_belongs_to_current_school')) return;
        $full_table = $GLOBALS['wpdb']->prefix . preg_replace('/[^A-Za-z0-9_]/', '', $table);
        if (!sige_object_belongs_to_current_school($full_table, $id)) {
            sige_phase1_scope_guard_fail('Operação bloqueada: o registo solicitado não pertence à escola actual.', [
                'param' => $param,
                'table' => $table,
                'id'    => $id,
                'action'=> sanitize_key($_REQUEST['action'] ?? ''),
                'sige_print' => sanitize_key($_REQUEST['sige_print'] ?? ''),
                'page'  => sanitize_key($_REQUEST['page'] ?? ''),
                'view'  => sanitize_key($_REQUEST['view'] ?? ''),
            ]);
        }
    }
}

if (!function_exists('sige_phase1_scope_guard_validate_request')) {
    function sige_phase1_scope_guard_validate_request(): void {
        if (!is_user_logged_in()) return;

        $action     = sanitize_key($_REQUEST['action'] ?? '');
        $page       = sanitize_key($_REQUEST['page'] ?? '');
        $view       = sanitize_key($_REQUEST['view'] ?? '');
        $sige_print = sanitize_key($_REQUEST['sige_print'] ?? '');

        // Só actuar em superfícies SIGE. Evita interferir com admin WordPress geral.
        $is_sige_surface = ($action !== '' && (strpos($action, 'sige_') === 0 || in_array($action, ['assign_user_role','unassign_user_role'], true)))
            || $page === 'sige-app'
            || $page === 'sige_core'
            || $page === 'sige_hub'
            || $sige_print !== '';
        if (!$is_sige_surface) return;

        // Parâmetros canónicos que indicam objectos tenant-scoped.
        $generic = [
            'aluno_id'       => 'sige_alunos',
            'aluno_ids'      => 'sige_alunos',
            'turma_id'       => 'sige_turmas',
            'turma_ids'      => 'sige_turmas',
            'disciplina_id'  => 'sige_disciplinas',
            'disciplina_ids' => 'sige_disciplinas',
            'professor_id'   => 'sige_professores',
            'matricula_id'   => 'sige_matriculas',
            'pagamento_id'   => 'sige_fin_pagamentos',
            'pagamento_ids'  => 'sige_fin_pagamentos',
            'lancamento_id'  => 'sige_fin_lancamentos',
            'lancamento_ids' => 'sige_fin_lancamentos',
            'servico_id'     => 'sige_fin_servicos',
            'pacote_id'      => 'sige_fin_pacotes',
            'plano_id'       => 'sige_fin_planos_pagamento',
            'prestacao_id'   => 'sige_fin_planos_prestacoes',
            'despesa_id'     => 'sige_fin_despesas',
            'rota_id'        => 'sige_transporte_rotas',
            'criterio_id'    => 'sige_jardim_criterios',
            'centro_id'      => 'sige_fin_centros',
            'acta_id'        => 'sige_acta_conselho_notas',
            'nota_id'        => 'sige_notas',
            'queue_id'       => 'sige_whatsapp_queue',
            'id_vinculo'     => 'sige_turma_disciplinas',
            'turma_disciplina_id' => 'sige_turma_disciplinas',
        ];

        // Acções onde o campo genérico "id" / "ids" / "user_id" tem significado tenant-scoped conhecido.
        $id_maps = [
            'sige_get_aluno_full'       => ['id' => 'sige_alunos'],
            'sige_remover_aluno'        => ['id' => 'sige_alunos'],
            'sige_get_professor_detalhes'=> ['id' => 'sige_professores'],
            'sige_atualizar_professor'  => ['id' => 'sige_professores'],
            'sige_remover_professor'    => ['id' => 'sige_professores'],
            'sige_excluir_turma'        => ['id' => 'sige_turmas'],
            'sige_remover_turma'        => ['id' => 'sige_turmas'],
            'sige_remover_criterio'     => ['id' => 'sige_jardim_criterios'],
            'sige_get_matriz'           => ['id' => 'sige_matriz_curricular'],
            'sige_remover_matriz'       => ['id' => 'sige_matriz_curricular'],
            'sige_update_ordem_matriz'  => ['id' => 'sige_matriz_curricular'],
            'sige_centro_eliminar'      => ['id' => 'sige_fin_centros'],
            'sige_wppc_get_full'        => ['id' => 'sige_whatsapp_queue'],
            'sige_wppc_cancel'          => ['id' => 'sige_whatsapp_queue'],
            'sige_wppc_retry'           => ['id' => 'sige_whatsapp_queue'],
            'sige_wppc_delete'          => ['id' => 'sige_whatsapp_queue'],
            'sige_wppc_send_link_now'   => ['id' => 'sige_whatsapp_queue', 'queue_id' => 'sige_whatsapp_queue'],
            'sige_get_staff_secure'     => ['id' => 'wp_user'],
            'sige_remover_usuario_staff'=> ['id' => 'wp_user'],
            'sige_resetar_senha'        => ['id' => 'wp_user'],
            'sige_editar_usuario_staff' => ['user_id' => 'wp_user', 'staff_id' => 'wp_user'],
            'sige_toggle_status_staff'  => ['user_id' => 'wp_user'],
            'assign_user_role'          => ['user_id' => 'wp_user'],
            'unassign_user_role'        => ['user_id' => 'wp_user'],
        ];

        // Impressões internas autenticadas com campo genérico id/ids.
        if ($sige_print !== '') {
            if ($sige_print === 'recibo') {
                $id_maps['_sige_print'] = ['id' => 'sige_fin_pagamentos'];
            } elseif ($sige_print === 'recibo_massa') {
                $id_maps['_sige_print'] = ['ids' => 'sige_fin_pagamentos'];
            } elseif (in_array($sige_print, ['factura','extracto','historico_financeiro_aluno'], true)) {
                $id_maps['_sige_print'] = ['id' => 'sige_alunos'];
            }
        }

        foreach ($generic as $param => $table) {
            if (!isset($_REQUEST[$param])) continue;
            foreach (sige_phase1_scope_guard_param_ids(wp_unslash($_REQUEST[$param])) as $id) {
                sige_phase1_scope_guard_validate_id($param, $table, $id);
            }
        }

        $map_key = ($action !== '' && isset($id_maps[$action])) ? $action : (($sige_print !== '' && isset($id_maps['_sige_print'])) ? '_sige_print' : '');
        if ($map_key !== '') {
            foreach ($id_maps[$map_key] as $param => $table) {
                if (!isset($_REQUEST[$param])) continue;
                foreach (sige_phase1_scope_guard_param_ids(wp_unslash($_REQUEST[$param])) as $id) {
                    sige_phase1_scope_guard_validate_id($param, $table, $id);
                }
            }
        }
    }
}

// Corre cedo nas superfícies autenticadas. Handlers funcionais mantêm as suas próprias validações.
add_action('admin_init', 'sige_phase1_scope_guard_validate_request', 1);
