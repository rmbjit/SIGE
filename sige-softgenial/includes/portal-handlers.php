<?php
/**
 * SIGE SoftGenial - Portal Handlers (AJAX endpoints)
 * Ficheiro: includes/portal-handlers.php
 *
 * AJAX handlers do portal do encarregado / aluno. Separados do
 * portal-logic.php (que faz rendering de HTML/JS) para que as
 * operações server-side tenham uma casa clara.
 *
 * HISTÓRICO:
 *   v12.2.1 - Primeira versão. Contém:
 *             • sige_alterar_senha_portal (movido de finance-core.php
 *               onde estava deslocado desde sempre)
 *
 * @since 12.2.1
 * @author RMBJ Consultoria
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────────────────────
// AJAX: Alterar senha do aluno / encarregado (portal público)
// ─────────────────────────────────────────────────────────────────────────────
//
// Endpoint:   admin-ajax.php?action=sige_alterar_senha_portal
// Exigido:    _nonce (sige_portal_senha), senha_atual, senha_nova, senha_confirmar
// Permissão:  qualquer utilizador autenticado (portal aceita aluno + encarregado)
// Rate limit: 5 tentativas por 10 minutos por user_id
// ─────────────────────────────────────────────────────────────────────────────
add_action('wp_ajax_sige_alterar_senha_portal', function() {

    if (!check_ajax_referer('sige_portal_senha', '_nonce', false)) {
        wp_send_json_error('Pedido inválido.');
        return;
    }

    $user = wp_get_current_user();
    if (!$user->ID) {
        wp_send_json_error('Sessão expirada.');
    }

    $atual = sanitize_text_field(wp_unslash($_POST['senha_atual'] ?? ''));
    $nova  = (string) wp_unslash($_POST['senha_nova'] ?? '');
    $conf  = (string) wp_unslash($_POST['senha_confirmar'] ?? '');

    if (!$atual || !$nova || !$conf) {
        wp_send_json_error('Preencha todos os campos.');
    }

    if (strlen($nova) < 8) {
        wp_send_json_error('A nova senha deve ter pelo menos 8 caracteres.');
    }

    if ($nova !== $conf) {
        wp_send_json_error('A nova senha e a confirmação não coincidem.');
    }

    if (function_exists('sige_rate_limit')) {
        sige_rate_limit('alterar_senha_' . $user->ID, 5, 600);
    }

    if (!wp_check_password($atual, $user->user_pass, $user->ID)) {
        wp_send_json_error('Senha actual incorrecta.');
    }

    wp_set_password($nova, $user->ID);
    if (function_exists('sige_aluno_portal_clear_first_access_flags_v129')) {
        sige_aluno_portal_clear_first_access_flags_v129((int)$user->ID);
    }

    // Re-autenticar para manter sessão (wp_set_password faz logout forçado)
    wp_set_auth_cookie($user->ID, true);
    wp_set_current_user($user->ID);

    // Audit log opcional
    if (function_exists('sige_audit_log')) {
        sige_audit_log('senha_alterada_portal', [
            'user_id' => (int) $user->ID,
            'login'   => (string) $user->user_login,
        ], 'acesso');
    }

    wp_send_json_success('Senha alterada com sucesso.');
});



if (!function_exists('sige_aluno_portal_clear_first_access_flags_v129')) {
    function sige_aluno_portal_clear_first_access_flags_v129(int $user_id): void {
        if ($user_id <= 0) return;
        foreach ([
            'sige_portal_force_password_change',
            'sige_password_must_change',
            'sige_primeiro_acesso_pendente',
            'sige_forcar_troca_senha',
            'sige_portal_force_password_reason',
            'sige_portal_force_password_at',
        ] as $meta_key) {
            delete_user_meta($user_id, $meta_key);
        }
        update_user_meta($user_id, 'sige_portal_primeiro_acesso_concluido', 1);
        update_user_meta($user_id, 'sige_portal_password_changed_at', current_time('mysql'));
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN-POST: Alterar senha na Página do Aluno integrada
// ─────────────────────────────────────────────────────────────────────────────
//
// Endpoint: wp-admin/admin-post.php?action=sige_aluno_portal_change_password
// Objectivo: processar antes do App Shell renderizar, evitando tela branca após submit.
// v12.10.127: depende de bypass seguro em security-roles.php para não ser redireccionado antes de executar.
// Segurança: só altera a senha do utilizador autenticado e redirecciona com mensagem.
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('sige_aluno_portal_password_policy_v126')) {
    function sige_aluno_portal_password_policy_v126($new_password, $user) {
        $errors = [];

        $new_password = (string)$new_password;
        if (strlen($new_password) < 8) {
            $errors[] = 'min_length';
        }
        if (strlen($new_password) > 128) {
            $errors[] = 'max_length';
        }
        if (!preg_match('/[A-Za-zÀ-ÿ]/u', $new_password) || !preg_match('/\d/', $new_password)) {
            $errors[] = 'letters_numbers';
        }

        $login = is_object($user) && isset($user->user_login) ? strtolower((string)$user->user_login) : '';
        $email = is_object($user) && isset($user->user_email) ? (string)$user->user_email : '';
        $email_prefix = strtolower((string)strtok($email, '@'));
        $lower = strtolower($new_password);

        if ($login !== '' && strlen($login) >= 4 && strpos($lower, $login) !== false) {
            $errors[] = 'contains_login';
        }
        if ($email_prefix !== '' && strlen($email_prefix) >= 4 && strpos($lower, $email_prefix) !== false) {
            $errors[] = 'contains_email';
        }

        return $errors;
    }
}

if (!function_exists('sige_aluno_portal_can_change_password_v126')) {
    function sige_aluno_portal_can_change_password_v126($user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0) return false;

        if (current_user_can('sige_encarregado') || current_user_can('sige_aluno')) {
            return true;
        }

        if (function_exists('sige_can') && sige_can('portal.ver')) {
            return true;
        }

        if (function_exists('sige_permissions_get_active_role')) {
            $role = sige_permissions_get_active_role($user_id);
            if ($role && !empty($role->slug)) {
                $slug = sanitize_key((string)$role->slug);
                if (in_array($slug, ['encarregado', 'aluno'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('sige_aluno_portal_password_redirect_v126')) {
    function sige_aluno_portal_password_redirect_v126($code = 'unknown', $ok = false) {
        $raw_redirect = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash((string)$_POST['redirect_to'])) : '';
        $fallback = admin_url('admin.php?page=sige-app&view=aluno_portal&secao=senha');

        if (!$raw_redirect) {
            $aluno_id = 0;
            if (function_exists('sige_aluno_portal_current_student_id_v117')) {
                $aluno_id = (int)sige_aluno_portal_current_student_id_v117(get_current_user_id());
            }
            $fallback = admin_url('admin.php?page=sige-app&view=aluno_portal' . ($aluno_id > 0 ? '&aluno_id=' . $aluno_id : '') . '&secao=senha');
        }

        $target = wp_validate_redirect($raw_redirect ?: $fallback, $fallback);
        $target = remove_query_arg(['pass_msg', 'pass_error'], $target);

        if ($ok) {
            $target = add_query_arg(['pass_msg' => 'ok'], $target);
        } else {
            $target = add_query_arg(['pass_msg' => 'error', 'pass_error' => sanitize_key((string)$code)], $target);
        }

        wp_safe_redirect($target);
        exit;
    }
}

add_action('admin_post_sige_aluno_portal_change_password', function() {
    if (!is_user_logged_in()) {
        sige_aluno_portal_password_redirect_v126('denied', false);
    }

    $user_id = (int)get_current_user_id();
    $user = get_user_by('id', $user_id);
    if (!$user || !is_object($user) || empty($user->ID)) {
        sige_aluno_portal_password_redirect_v126('denied', false);
    }

    if (!sige_aluno_portal_can_change_password_v126($user_id)) {
        sige_aluno_portal_password_redirect_v126('denied', false);
    }

    $nonce = isset($_POST['sige_aluno_portal_nonce']) ? sanitize_text_field(wp_unslash((string)$_POST['sige_aluno_portal_nonce'])) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'sige_aluno_portal_change_password')) {
        sige_aluno_portal_password_redirect_v126('nonce', false);
    }

    $rate_key = 'sige_aluno_pass_attempts_' . $user_id;
    $attempts = (int)get_transient($rate_key);
    if ($attempts >= 5) {
        sige_aluno_portal_password_redirect_v126('rate', false);
    }

    $current_password = isset($_POST['current_password']) ? (string)wp_unslash($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? (string)wp_unslash($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? (string)wp_unslash($_POST['confirm_password']) : '';

    $register_fail = function($code) use ($rate_key, $attempts) {
        set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
        sige_aluno_portal_password_redirect_v126($code, false);
    };

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $register_fail('empty');
    }

    if (!wp_check_password($current_password, (string)$user->user_pass, $user_id)) {
        $register_fail('current');
    }

    if ($new_password !== $confirm_password) {
        $register_fail('confirm');
    }

    if (function_exists('hash_equals') && hash_equals($current_password, $new_password)) {
        $register_fail('same');
    } elseif (!function_exists('hash_equals') && $current_password === $new_password) {
        $register_fail('same');
    }

    $policy_errors = sige_aluno_portal_password_policy_v126($new_password, $user);
    if (!empty($policy_errors)) {
        $first_policy_error = sanitize_key((string)reset($policy_errors));
        $allowed_policy_errors = ['min_length','max_length','letters_numbers','contains_login','contains_email'];
        if (!in_array($first_policy_error, $allowed_policy_errors, true)) {
            $first_policy_error = 'policy';
        }
        $register_fail($first_policy_error);
    }

    wp_set_password($new_password, $user_id);
    if (function_exists('sige_aluno_portal_clear_first_access_flags_v129')) {
        sige_aluno_portal_clear_first_access_flags_v129((int)$user_id);
    }

    // wp_set_password limpa a autenticação; recriamos a sessão para o utilizador continuar no portal.
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());

    delete_transient($rate_key);

    if (function_exists('sige_audit_log')) {
        sige_audit_log('senha_alterada_portal_integrado', [
            'user_id' => $user_id,
            'login' => (string)$user->user_login,
        ], 'acesso');
    }

    sige_aluno_portal_password_redirect_v126('ok', true);
});

