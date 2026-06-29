<?php
/**
 * SIGE SoftGenial - Security & Roles
 * Ficheiro: includes/security-roles.php
 * 
 * Gestão de roles, permissões e redirecionamentos de segurança.
 * 
 * @since 10.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// PORTAL DO ALUNO - URL CANÓNICA INTEGRADA v12.10.117
// ============================================================================
// A partir desta versão, a página correcta do aluno é nativa do SIGE/App Shell:
// admin.php?page=sige-app&view=aluno_portal&aluno_id=...
// O endpoint antigo /portal-do-aluno/ fica apenas como compatibilidade e redirecciona.
if (!function_exists('sige_aluno_portal_current_student_id_v117')) {
    function sige_aluno_portal_current_student_id_v117(?int $user_id = null): int {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) return 0;

        $keys = ['sige_aluno_id', 'aluno_id', 'sige_portal_aluno_id'];
        $candidate = 0;
        foreach ($keys as $key) {
            $id = (int)get_user_meta($user_id, $key, true);
            if ($id > 0) {
                $candidate = $id;
                break;
            }
        }
        if ($candidate <= 0) return 0;

        // v12.10.129 - validação segura da associação dentro da escola actual.
        global $wpdb;
        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $table = $wpdb->prefix . 'sige_alunos';

        if ($escola_id > 0 && $wpdb && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) {
            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE id=%d AND escola_id=%d LIMIT 1",
                $candidate,
                $escola_id
            ));
            if ($exists <= 0) {
                return 0;
            }
        }

        // Backfill dos aliases para evitar regressões entre versões antigas e novas.
        update_user_meta($user_id, 'sige_aluno_id', $candidate);
        update_user_meta($user_id, 'aluno_id', $candidate);
        update_user_meta($user_id, 'sige_portal_aluno_id', $candidate);
        if ($escola_id > 0) {
            update_user_meta($user_id, 'sige_escola_id', $escola_id);
            update_user_meta($user_id, 'sige_portal_escola_id', $escola_id);
        }

        return $candidate;
    }
}

if (!function_exists('sige_aluno_portal_url_v117')) {
    function sige_aluno_portal_url_v117(?int $user_id = null): string {
        $aluno_id = sige_aluno_portal_current_student_id_v117($user_id);
        $args = [
            'page' => 'sige-app',
            'view' => 'aluno_portal',
        ];
        if ($aluno_id > 0) {
            $args['aluno_id'] = $aluno_id;
        }
        return add_query_arg($args, admin_url('admin.php'));
    }
}

if (!function_exists('sige_aluno_portal_is_legacy_request_v117')) {
    function sige_aluno_portal_is_legacy_request_v117(): bool {
        $path = trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $home_path = trim((string)parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home_path !== '' && strpos($path, $home_path . '/') === 0) {
            $path = substr($path, strlen($home_path) + 1);
        }
        $path = trim($path, '/');
        return in_array($path, ['portal-do-aluno', 'portal-do-aluno/', 'portal-aluno', 'portal-aluno/'], true);
    }
}

// Compatibilidade: qualquer tentativa de abrir o endpoint antigo vai para a nova página integrada.
if (!function_exists('sige_redirect_legacy_portal_to_app_v117')) {
    function sige_redirect_legacy_portal_to_app_v117() {
        if (is_admin()) return;
        if (!sige_aluno_portal_is_legacy_request_v117()) return;

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(sige_aluno_portal_url_v117()), 302);
            exit;
        }

        wp_safe_redirect(sige_aluno_portal_url_v117(get_current_user_id()), 302);
        exit;
    }
}
add_action('template_redirect', 'sige_redirect_legacy_portal_to_app_v117', 0);

// ============================================================================
// REDIRECIONAMENTO APÓS LOGIN
// ============================================================================
if (!function_exists('sige_login_redirect_rules')) {
    function sige_login_redirect_rules($redirect_to, $request, $user) {
        if (!($user instanceof WP_User)) return $redirect_to;

        // Admin/Super Admin real mantém acesso normal ao WP Admin.
        if (function_exists('sige_page_guard_is_real_admin') ? sige_page_guard_is_real_admin((int)$user->ID) : user_can($user, 'manage_options')) return $redirect_to;

        // Perfil SIGE é a fonte principal; WP role fica apenas como fallback.
        $active_role = function_exists('sige_permissions_get_active_role') ? sige_permissions_get_active_role((int)$user->ID) : null;
        if ($active_role && !empty($active_role->slug)) {
            return ((string)$active_role->slug === 'encarregado') ? sige_aluno_portal_url_v117((int)$user->ID) : admin_url('admin.php?page=sige-app');
        }

        if (!empty($user->roles) && sige_is_portal_role($user)) return sige_aluno_portal_url_v117((int)$user->ID);
        if (!empty($user->roles) && sige_is_staff_role($user))  return admin_url('admin.php?page=sige-app');
        return $redirect_to;
    }
}
add_filter('login_redirect', 'sige_login_redirect_rules', 10, 3);

// ============================================================================
// FORÇAR LINK PRINCIPAL DA ESCOLA PARA LOGIN/SIGE
// ============================================================================
if (!function_exists('sige_force_school_home_to_login')) {
    /**
     * Garante que o endereço principal da escola nunca expõe a home pública do WordPress.
     *
     * Escopo intencionalmente restrito ao URL principal da instalação (sem query string),
     * para não interferir com recibos públicos, REST API, cron, admin-ajax, portal do aluno,
     * ficheiros, impressões ou páginas internas.
     *
     * Para casos excepcionais, pode ser desactivado no wp-config.php com:
     * define('SIGE_DISABLE_HOME_LOGIN_REDIRECT', true);
     *
     * @since 12.9.82
     */
    function sige_force_school_home_to_login() {
        if (defined('SIGE_DISABLE_HOME_LOGIN_REDIRECT') && SIGE_DISABLE_HOME_LOGIN_REDIRECT) return;
        if (is_admin()) return;
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (defined('WP_CLI') && WP_CLI) return;

        // Não tocar em recibos públicos, documentos, cron, webhooks ou qualquer endpoint com query string.
        if (!empty($_GET)) return;

        $request_path = trim((string) parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $home_path    = trim((string) parse_url(home_url('/'), PHP_URL_PATH), '/');

        // Só o link principal exacto da instalação é protegido.
        if ($request_path !== $home_path) return;

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $target = admin_url('admin.php?page=sige-app');

            $active_role = function_exists('sige_permissions_get_active_role') ? sige_permissions_get_active_role((int)$user->ID) : null;
            if ($active_role && !empty($active_role->slug) && (string)$active_role->slug === 'encarregado') {
                $target = sige_aluno_portal_url_v117((int)$user->ID);
            } elseif (function_exists('sige_is_portal_role') && sige_is_portal_role($user)) {
                $target = sige_aluno_portal_url_v117((int)$user->ID);
            }

            wp_safe_redirect($target, 302);
            exit;
        }

        wp_safe_redirect(wp_login_url(admin_url('admin.php?page=sige-app')), 302);
        exit;
    }
}
add_action('template_redirect', 'sige_force_school_home_to_login', 0);

// ============================================================================
// BLOQUEAR ACESSO DIRECTO AO BACKEND WP
// ============================================================================
if (!function_exists('sige_block_dashboard_access')) {
    function sige_block_dashboard_access() {
        if (!is_admin() || defined('DOING_AJAX') || defined('DOING_CRON')) return;
        if (isset($_GET['sige_print'])) return;

        $user = wp_get_current_user();
        if (!$user->ID) return;

        // Regra de produto: apenas Admin/Super Admin real acede ao dashboard nativo do WordPress.
        if (function_exists('sige_page_guard_is_real_admin') ? sige_page_guard_is_real_admin((int)$user->ID) : user_can($user, 'manage_options')) return;

        $pg = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $scripts_permitidos = ['admin-post.php', 'upload.php', 'async-upload.php', 'media-upload.php', 'media-new.php'];

        // v12.10.127 - permitir que o handler oficial da alteração de senha execute.
        // Sem esta excepção, o admin_init redireccionava aluno/encarregado antes do
        // admin-post.php disparar admin_post_sige_aluno_portal_change_password.
        $sige_admin_post_action = isset($_REQUEST['action']) ? sanitize_key((string)wp_unslash($_REQUEST['action'])) : '';
        if ($script === 'admin-post.php' && in_array($sige_admin_post_action, ['sige_aluno_portal_change_password','sige_secure_document_download'], true)) {
            return;
        }

        $active_role = function_exists('sige_permissions_get_active_role') ? sige_permissions_get_active_role((int)$user->ID) : null;
        if ($active_role && !empty($active_role->slug)) {
            if ((string)$active_role->slug === 'encarregado') {
                $view = isset($_GET['view']) ? sanitize_key((string)$_GET['view']) : '';
                if ($pg === 'sige-app' && $view === 'aluno_portal') {
                    return;
                }
                wp_safe_redirect(sige_aluno_portal_url_v117((int)$user->ID));
                exit;
            }
            if (strpos($pg, 'sige') === false && !in_array($script, $scripts_permitidos, true)) {
                wp_redirect(admin_url('admin.php?page=sige-app'));
                exit;
            }
            return;
        }

        // Fallback legado.
        if (sige_is_portal_role($user)) {
            $view = isset($_GET['view']) ? sanitize_key((string)$_GET['view']) : '';
            if ($pg === 'sige-app' && $view === 'aluno_portal') {
                return;
            }
            wp_safe_redirect(sige_aluno_portal_url_v117((int)$user->ID));
            exit;
        }
        if (sige_is_staff_role($user)) {
            if (strpos($pg, 'sige') === false && !in_array($script, $scripts_permitidos, true)) {
                wp_redirect(admin_url('admin.php?page=sige-app'));
                exit;
            }
        }
    }
}
add_action('admin_init', 'sige_block_dashboard_access', 1);

// ============================================================================
// LIMPAR INTERFACE FRONTEND
// ============================================================================
if (!function_exists('sige_limpar_interface_frontend')) {
    function sige_limpar_interface_frontend() {
        if (is_admin()) return;
        
        // Remover admin bar para roles SIGE
        $user = wp_get_current_user();
        if ($user->ID && (sige_is_portal_role($user) || sige_is_staff_role($user))) {
            show_admin_bar(false);
        }
    }
}
add_action('after_setup_theme', 'sige_limpar_interface_frontend');

// ============================================================================
// CRIAÇÃO DE PERFIS (ROLES)
// ============================================================================
if (!function_exists('sige_criar_roles_acesso')) {
    function sige_criar_roles_acesso() {
        // Direcção
        add_role('sige_director', 'Director de Escola', [
            'read' => true,
            // Fase 1/P0: Director é autoridade funcional do SIGE, não administrador técnico do WordPress.
            'sige_director' => true
        ]);
        add_role('sige_pedagogico', 'Director Pedagógico', [
            'read' => true, 
            'sige_pedagogico' => true
        ]);
        
        // Administração & TI
        add_role('sige_admin_ti', 'Admin TI Escola', [
            'read' => true, 
            'upload_files' => true,
            'sige_admin_ti' => true,
            'sige_director' => true,
            'sige_secretaria_geral' => true,
            'sige_secretario' => true,
            'sige_assistente' => true,
            'sige_financeiro' => true,
            'sige_fin_ver' => true,
            'sige_fin_lancar' => true,
            'sige_fin_caixa' => true,
            'sige_fin_config' => true,
            'sige_professor' => true,
            'sige_pedagogico' => true,
            'sige_educador' => true,
            'sige_gestor_rh' => true,
        ]);
        add_role('sige_secretaria_geral', 'Chefe Secretaria', [
            'read' => true, 
            'sige_secretaria_geral' => true
        ]);
        add_role('sige_secretario', 'Secretário', [
            'read' => true, 
            'sige_secretario' => true
        ]);
        add_role('sige_assistente', 'Assistente Admin', [
            'read' => true, 
            'sige_assistente' => true
        ]);
        add_role('sige_recepcao', 'Recepção', [
            'read' => true, 
            'sige_recepcao' => true
        ]);
        add_role('sige_guarda', 'Guarda / Portaria', [
            'read' => true,
            'sige_guarda' => true,
            'portaria.ver' => true,
            'portaria.validar_acesso' => true,
            'alunos.ver' => true
        ]);
        if ($role_guarda = get_role('sige_guarda')) {
            $role_guarda->add_cap('read');
            $role_guarda->add_cap('sige_guarda');
            $role_guarda->add_cap('portaria.ver');
            $role_guarda->add_cap('portaria.validar_acesso');
            $role_guarda->add_cap('alunos.ver');
        }
        
        // Financeiro
        add_role('sige_financeiro', 'Tesoureiro', [
            'read' => true, 
            'sige_financeiro' => true
        ]);
        
        // Académico
        add_role('sige_professor', 'Professor', [
            'read' => true, 
            'sige_professor' => true
        ]);
        add_role('sige_educador', 'Educador', [
            'read' => true, 
            'sige_educador' => true
        ]);
        
        // RH e Operações
        add_role('sige_gestor_rh', 'Gestor de RH', [
            'read' => true, 
            'sige_gestor_rh' => true
        ]);
        add_role('sige_motorista', 'Motorista', [
            'read' => true, 
            'sige_motorista' => true
        ]);
        add_role('sige_limpeza', 'Limpeza', [
            'read' => true, 
            'sige_limpeza' => true
        ]);
        
        // Portal
        add_role('sige_encarregado', 'Encarregado', [
            'read' => true,
            'sige_encarregado' => true,
            'portal.ver' => true,
            'portal.ver_pagamentos' => true,
            'portal.ver_documentos' => true,
            'portal.alterar_senha' => true
        ]);
        add_role('sige_aluno', 'Aluno', [
            'read' => true,
            'sige_aluno' => true,
            'portal.ver' => true,
            'portal.ver_pagamentos' => true,
            'portal.ver_documentos' => true,
            'portal.alterar_senha' => true
        ]);
    }
}

// Auto-registar roles em falta (hook init garante que WP está pronto)
add_action('init', function () {
    if (!get_role('sige_admin_ti') || !get_role('sige_recepcao') || !get_role('sige_guarda')) {
        if (function_exists('sige_criar_roles_acesso')) {
            sige_criar_roles_acesso();
        }
    }
    // [MALISA-V70] Garantir que o Director Pedagógico existente mantém acesso
    // aos módulos académicos solicitados pelo cliente.
    $ped = get_role("sige_pedagogico");
    if ($ped) {
        foreach (["read","sige_pedagogico"] as $cap) {
            if (!$ped->has_cap($cap)) $ped->add_cap($cap);
        }
    }

    // [FIX S12] Garantir que Admin TI tem todas as capabilities SIGE (sem manage_options)
    $ti = get_role('sige_admin_ti');
    if ($ti && !$ti->has_cap('sige_director')) {
        foreach (['sige_director','sige_secretaria_geral','sige_secretario',
                   'sige_assistente','sige_financeiro','sige_fin_ver','sige_fin_lancar',
                   'sige_fin_caixa','sige_fin_config','sige_professor','sige_pedagogico',
                   'sige_educador','sige_gestor_rh'] as $cap) {
            $ti->add_cap($cap);
        }
    }
    // v12.11.9.65 - garantir role Guarda/Portaria em instalações já existentes.
    $guarda = get_role('sige_guarda');
    if ($guarda) {
        foreach (['read','sige_guarda','portaria.ver','portaria.validar_acesso','alunos.ver'] as $cap) {
            if (!$guarda->has_cap($cap)) $guarda->add_cap($cap);
        }
    }

    // v12.10.118 - garantir que roles de portal já existentes conseguem abrir a Página do Aluno integrada.
    foreach (['sige_encarregado','sige_aluno'] as $portal_role_slug) {
        $portal_role = get_role($portal_role_slug);
        if ($portal_role) {
            foreach (['read','portal.ver','portal.ver_pagamentos','portal.ver_documentos','portal.alterar_senha'] as $cap) {
                if (!$portal_role->has_cap($cap)) $portal_role->add_cap($cap);
            }
        }
    }

    // Fase 1/P0 - Remover manage_options de todas as roles SIGE não-nativas.
    // A autoridade escolar deve vir das capabilities/permissões SIGE, não do poder técnico do WordPress.
    $sige_roles_sem_manage_options = [
        'sige_director','sige_pedagogico','sige_admin_ti','sige_secretaria_geral','sige_secretario',
        'sige_assistente','sige_recepcao','sige_guarda','sige_financeiro','sige_professor','sige_educador',
        'sige_gestor_rh','sige_motorista','sige_limpeza','sige_encarregado','sige_aluno'
    ];
    foreach ($sige_roles_sem_manage_options as $role_slug) {
        $role_obj = get_role($role_slug);
        if ($role_obj && $role_obj->has_cap('manage_options')) {
            $role_obj->remove_cap('manage_options');
        }
    }

    // Migração defensiva única: remover capability directa manage_options de utilizadores com role SIGE,
    // excepto quando também são administradores WordPress reais.
    if (!get_option('sige_phase1_removed_manage_options_from_sige_users')) {
        $users = get_users([
            'fields' => ['ID'],
            'role__in' => $sige_roles_sem_manage_options,
            'number' => 5000,
        ]);
        foreach ($users as $urow) {
            $u = get_userdata((int)$urow->ID);
            if (!($u instanceof WP_User)) continue;
            $roles = array_map('strval', (array)$u->roles);
            if (in_array('administrator', $roles, true) || in_array('super_admin', $roles, true)) continue;
            if ($u->has_cap('manage_options')) {
                $u->remove_cap('manage_options');
            }
        }
        update_option('sige_phase1_removed_manage_options_from_sige_users', current_time('mysql'), false);
    }
}, 5);
