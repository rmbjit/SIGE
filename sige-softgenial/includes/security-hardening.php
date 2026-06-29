<?php
/**
 * SIGE SoftGenial - Security Hardening
 * Versão 12.9.9.3
 *
 * Camada leve de segurança para produção.
 * Não altera fluxos, menus ou regras financeiras/académicas.
 */
if (!defined('ABSPATH')) exit;


if (!function_exists('sige_is_real_wp_admin_user_raw')) {
    /**
     * Fase 1/P0 hotfix: identifica administrador WordPress real sem chamar
     * is_super_admin(), current_user_can() ou user_can().
     *
     * Motivo: esta função é usada por filtros de capacidade. Chamar
     * is_super_admin()/current_user_can() dentro de user_has_cap reentra no
     * próprio filtro e causa recursão infinita no WordPress.
     *
     * @param int|WP_User|null $user_or_id ID do utilizador, WP_User ou null.
     */
    function sige_is_real_wp_admin_user_raw($user_or_id = null): bool {
        $user = null;
        $user_id = 0;

        if (is_object($user_or_id) && isset($user_or_id->ID)) {
            $user = $user_or_id;
            $user_id = (int)$user_or_id->ID;
        } else {
            $user_id = $user_or_id ? (int)$user_or_id : (function_exists('get_current_user_id') ? (int)get_current_user_id() : 0);
            if ($user_id > 0 && function_exists('get_userdata')) {
                $user = get_userdata($user_id);
            }
        }

        if ($user_id <= 0 || !$user) return false;

        $roles = !empty($user->roles) ? array_map('strval', (array)$user->roles) : [];
        if (in_array('administrator', $roles, true) || in_array('super_admin', $roles, true)) return true;

        // Multisite: verificar super admins pelo login, sem chamar is_super_admin().
        if (function_exists('is_multisite') && is_multisite() && function_exists('get_super_admins')) {
            $login = isset($user->user_login) ? (string)$user->user_login : '';
            if ($login !== '' && in_array($login, array_map('strval', (array)get_super_admins()), true)) return true;
        }

        return false;
    }
}

if (!function_exists('sige_is_real_wp_admin_user')) {
    /**
     * Fase 1/P0: identifica administrador WordPress real por role nativa/super-admin,
     * sem confiar cegamente em manage_options herdado por roles SIGE legadas.
     *
     * Importante: não chama is_super_admin() nem current_user_can(), para poder
     * ser usada em segurança dentro de filtros de capability do WordPress.
     */
    function sige_is_real_wp_admin_user(?int $user_id = null): bool {
        return sige_is_real_wp_admin_user_raw($user_id);
    }
}


if (!function_exists('sige_security_sige_role_slugs')) {
    /** Roles SIGE operacionais que nunca devem herdar autoridade técnica WP. */
    function sige_security_sige_role_slugs(): array {
        return [
            'sige_director','sige_pedagogico','sige_admin_ti','sige_admin_escola','sige_secretaria_geral',
            'sige_secretario','sige_assistente','sige_recepcao','sige_guarda','sige_financeiro','sige_professor','sige_educador',
            'sige_gestor_rh','sige_motorista','sige_limpeza','sige_encarregado','sige_aluno','sige_admin'
        ];
    }
}

if (!function_exists('sige_security_strip_technical_caps_from_sige_users')) {
    /**
     * Defesa em profundidade: se uma role/utilizador SIGE legado ainda tiver
     * manage_options ou capacidades técnicas gravadas, current_user_can() passa
     * a devolver falso para quem não for administrator/super_admin WordPress real.
     */
    function sige_security_strip_technical_caps_from_sige_users(array $allcaps, array $caps, array $args, WP_User $user): array {
        static $inside_filter = false;

        // Guarda anti-recursão: este filtro roda dentro de WP_User::has_cap().
        // Se qualquer código externo voltar a consultar capabilities durante a
        // execução, devolvemos o estado actual para evitar loop fatal.
        if ($inside_filter) return $allcaps;

        $inside_filter = true;
        try {
            $uid = isset($user->ID) ? (int)$user->ID : 0;
            if ($uid <= 0) return $allcaps;

            // Usar a variante raw baseada no objecto recebido pelo próprio WP,
            // sem is_super_admin()/current_user_can().
            if (function_exists('sige_is_real_wp_admin_user_raw') && sige_is_real_wp_admin_user_raw($user)) return $allcaps;

            $roles = array_map('strval', (array)$user->roles);
            if (!array_intersect($roles, sige_security_sige_role_slugs())) return $allcaps;

            foreach (['manage_options','activate_plugins','delete_plugins','edit_plugins','install_plugins','update_plugins','switch_themes','edit_themes','delete_themes','install_themes','update_themes','edit_users','delete_users','create_users','promote_users','manage_network'] as $cap) {
                if (isset($allcaps[$cap])) $allcaps[$cap] = false;
            }
            return $allcaps;
        } finally {
            $inside_filter = false;
        }
    }
}
add_filter('user_has_cap', 'sige_security_strip_technical_caps_from_sige_users', 1, 4);

if (!function_exists('sige_security_log')) {
    function sige_security_log(string $evento, string $contexto = '', int $objeto_id = 0): void {
        // Proibicao de segredos em registos: redige segredos antes de registar.
        if (function_exists('sige_secret_scrub')) {
            $evento = sige_secret_scrub($evento);
            $contexto = sige_secret_scrub($contexto);
        }
        $msg = '[SIGE SECURITY] ' . $evento . ($contexto !== '' ? ' - ' . $contexto : '');
        if (function_exists('sige_audit_log')) {
            try {
                sige_audit_log('seguranca', [
                    'evento' => $evento,
                    'contexto' => $contexto,
                    'objeto_id' => $objeto_id,
                ], 'sistema');
                return;
            } catch (Throwable $e) {
                error_log($msg . ' | audit_error=' . $e->getMessage());
                return;
            }
        }
        error_log($msg);
    }
}

if (!function_exists('sige_user_can_any_secure')) {
    /**
     * Verifica permissões SIGE e capacidades legadas sem abrir o backend WP.
     * manage_options continua a funcionar para superadmin técnico.
     */
    function sige_user_can_any_secure(array $permissoes = [], array $caps = []): bool {
        if (!is_user_logged_in()) return false;
        if (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) return true;

        if (function_exists('sige_can')) {
            foreach ($permissoes as $perm) {
                if ($perm && sige_can((string)$perm)) return true;
            }
        }

        // v12.11.4 - Matriz SIGE soberana em handlers e AJAX.
        // Se o utilizador já tem perfil SIGE activo, as permissões do perfil
        // devem mandar também em acções POST/AJAX/admin-post. Antes, alguns
        // handlers ainda caiam para current_user_can('sige_xxx'), o que fazia
        // certas permissões parecerem cosméticas: a UI dizia uma coisa e a
        // acção real seguia outra. O fallback legado só deve valer para contas
        // ainda não migradas para a matriz SIGE.
        $has_active_sige_role = false;
        if (function_exists('sige_page_guard_has_active_sige_role')) {
            $has_active_sige_role = (bool) sige_page_guard_has_active_sige_role();
        } elseif (function_exists('sige_permissions_user_has_active_role')) {
            $has_active_sige_role = (bool) sige_permissions_user_has_active_role();
        }
        if ($has_active_sige_role && !empty($permissoes)) {
            return false;
        }

        foreach ($caps as $cap) {
            if ($cap && current_user_can((string)$cap)) return true;
        }

        return false;
    }
}

if (!function_exists('sige_require_user_can_any_secure')) {
    function sige_require_user_can_any_secure(array $permissoes = [], array $caps = [], string $mensagem = 'Acesso negado.'): void {
        if (!sige_user_can_any_secure($permissoes, $caps)) {
            sige_security_log('acesso_negado', $mensagem . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
            wp_die(esc_html($mensagem), 'SIGE - Segurança', ['response' => 403]);
        }
    }
}

if (!function_exists('sige_secure_int_list_from_request')) {
    /**
     * Normaliza listas de IDs vindas de GET/POST e limita tamanho para evitar abuso.
     */
    function sige_secure_int_list_from_request($raw, int $max = 50): array {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = explode(',', (string)$raw);
        }
        $ids = [];
        foreach ($parts as $p) {
            $id = absint($p);
            if ($id > 0) $ids[$id] = $id;
            if (count($ids) >= $max) break;
        }
        return array_values($ids);
    }
}

if (!function_exists('sige_rate_limit_action')) {
    /**
     * Rate limit leve por utilizador/IP/ação. Não é aplicado automaticamente.
     * IMPORTANTE: rotinas de lançamento em massa devem chamar com $bypass=true.
     */
    function sige_rate_limit_action(string $action, int $max = 10, int $window = 60, bool $bypass = false): bool {
        if ($bypass || (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return true;
        $uid = get_current_user_id();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/[^0-9a-fA-F:\.]/', '', (string)$_SERVER['REMOTE_ADDR']) : '0.0.0.0';
        $key = 'sige_rl_' . md5($action . '|' . $uid . '|' . $ip);
        $bucket = get_transient($key);
        if (!is_array($bucket)) {
            set_transient($key, ['count' => 1, 'start' => time()], $window);
            return true;
        }
        $count = (int)($bucket['count'] ?? 0) + 1;
        $bucket['count'] = $count;
        set_transient($key, $bucket, $window);
        if ($count > $max) {
            sige_security_log('rate_limit', "action={$action}; count={$count}; max={$max}; window={$window}");
            return false;
        }
        return true;
    }
}


if (!function_exists('sige_table_has_column_secure')) {
    function sige_table_has_column_secure(string $table, string $column): bool {
        global $wpdb;
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $column = preg_replace('/[^A-Za-z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }
}

if (!function_exists('sige_object_belongs_to_current_school')) {
    /**
     * Fase 1: helper central para handlers que recebem IDs por GET/POST.
     * Retorna true apenas quando a linha existe e pertence à escola corrente.
     */
    function sige_object_belongs_to_current_school(string $table, int $id, string $pk = 'id', string $school_col = 'escola_id'): bool {
        global $wpdb;
        $id = absint($id);
        if ($id <= 0) return false;
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table);
        $pk = preg_replace('/[^A-Za-z0-9_]/', '', $pk) ?: 'id';
        $school_col = preg_replace('/[^A-Za-z0-9_]/', '', $school_col) ?: 'escola_id';
        if ($table === '') return false;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($eid <= 0) return false;
        if (!sige_table_has_column_secure($table, $pk) || !sige_table_has_column_secure($table, $school_col)) return false;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE {$pk}=%d AND {$school_col}=%d LIMIT 1",
            $id,
            $eid
        )) > 0;
    }
}

if (!function_exists('sige_require_object_belongs_to_current_school')) {
    function sige_require_object_belongs_to_current_school(string $table, int $id, string $msg = 'Operação bloqueada: o registo não pertence à escola actual.', string $pk = 'id', string $school_col = 'escola_id'): void {
        if (!sige_object_belongs_to_current_school($table, $id, $pk, $school_col)) {
            if (function_exists('sige_security_log')) {
                sige_security_log('tenant_object_scope_denied', $msg . ' table=' . $table . '; id=' . $id, $id);
            }
            wp_die(esc_html($msg), 'SIGE - Segurança', ['response' => 403]);
        }
    }
}
