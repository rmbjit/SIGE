<?php
/**
 * SIGE SoftGenial - Painel de controlo de seguranca (MFA)
 *
 * v12.12.13. Da ao administrador WordPress real um ecra no painel para gerir os
 * controlos de MFA (step-up, reposicao automatica, modo estrito) e os perfis
 * abrangidos, sem precisar de codigo, WP-CLI ou base de dados.
 *
 * Acesso restrito ao ADMIN Super (administrador WordPress real / super admin de
 * multisite), pela funcao canonica sige_is_real_wp_admin_user(). O Admin IT
 * (sige_admin_ti) e os outros perfis nativos do SIGE nunca veem nem alcancam o
 * ecra, mesmo que tenham manage_options (e-lhes retirado pelo filtro existente).
 *
 * Cada alteracao a um controlo de seguranca e registada na auditoria, com enfase
 * em desligar o step-up. Endpoint admin_post:sige_mfa_settings_save, governado
 * pelo Kernel.
 */

if (!defined('ABSPATH') && !defined('SIGE_MFA_SETTINGS_TEST_MODE')) exit;

if (!function_exists('sige_mfa_settings_can_manage')) {
    /**
     * Gate canonico do painel: so administrador WordPress real / super admin.
     * Nunca perfis nativos do SIGE (incluindo sige_admin_ti). Se o helper
     * canonico nao existir, recorre a um teste conservador pelo perfil
     * administrator (nunca apenas manage_options, que perfis SIGE podem herdar).
     */
    function sige_mfa_settings_can_manage(): bool {
        if (function_exists('sige_is_real_wp_admin_user')) return (bool) sige_is_real_wp_admin_user();
        if (!function_exists('wp_get_current_user')) return false;
        $u = wp_get_current_user();
        if (!$u || empty($u->ID)) return false;
        $roles = array_map('strval', (array) $u->roles);
        return in_array('administrator', $roles, true) || in_array('super_admin', $roles, true);
    }
}

if (!function_exists('sige_mfa_settings_role_choices')) {
    /** Perfis SIGE que podem ser abrangidos pelo step-up (alvos, nao acesso ao painel). */
    function sige_mfa_settings_role_choices(): array {
        return [
            'sige_director'         => 'Director',
            'sige_admin_ti'         => 'Admin IT',
            'sige_admin_escola'     => 'Admin da Escola',
            'sige_secretaria_geral' => 'Secretaria Geral',
            'sige_secretario'       => 'Secretario',
            'sige_financeiro'        => 'Financeiro',
            'sige_pedagogico'       => 'Pedagogico',
            'sige_gestor_rh'        => 'Gestor RH',
        ];
    }
}

if (!function_exists('sige_mfa_settings_compute')) {
    /**
     * Calcula os novos valores a partir de um conjunto tipo $_POST (puro e
     * testavel). Checkboxes ausentes valem 'off'. Perfis sao filtrados para o
     * conjunto permitido.
     */
    function sige_mfa_settings_compute(array $post): array {
        $choices = array_keys(sige_mfa_settings_role_choices());
        $roles = [];
        if (isset($post['sige_mfa_stepup_roles']) && is_array($post['sige_mfa_stepup_roles'])) {
            $roles = array_values(array_intersect($choices, array_map('strval', $post['sige_mfa_stepup_roles'])));
        }
        return [
            'sige_mfa_stepup'        => isset($post['sige_mfa_stepup']) ? 'on' : 'off',
            'sige_mfa_autoreplay'    => isset($post['sige_mfa_autoreplay']) ? 'on' : 'off',
            'sige_mfa_stepup_strict' => isset($post['sige_mfa_stepup_strict']) ? 'on' : 'off',
            'sige_mfa_stepup_roles'  => $roles,
        ];
    }
}

if (!function_exists('sige_mfa_settings_totp_enrolled_count')) {
    /** Quantos utilizadores tem a aplicacao autenticadora confirmada (so leitura). */
    function sige_mfa_settings_totp_enrolled_count(): int {
        if (!function_exists('get_users')) return 0;
        $ids = get_users(['meta_key' => '_sige_mfa_totp_confirmed', 'meta_value' => '1', 'fields' => 'ID']);
        return is_array($ids) ? count($ids) : 0;
    }
}

if (!function_exists('sige_mfa_settings_handle_save')) {
    /** Handler de gravacao (admin_post). Gate duro + nonce + sanitizacao + auditoria. */
    function sige_mfa_settings_handle_save(): void {
        if (!is_user_logged_in() || !sige_mfa_settings_can_manage()) {
            wp_die('Acesso restrito ao administrador WordPress.', 'Negado', ['response' => 403]);
        }
        check_admin_referer('sige_mfa_settings');

        $old = [
            'sige_mfa_stepup'        => (string) get_option('sige_mfa_stepup', 'off'),
            'sige_mfa_autoreplay'    => (string) get_option('sige_mfa_autoreplay', 'off'),
            'sige_mfa_stepup_strict' => (string) get_option('sige_mfa_stepup_strict', 'off'),
            'sige_mfa_stepup_roles'  => (array) get_option('sige_mfa_stepup_roles', ['sige_director', 'sige_admin_ti']),
        ];
        $new = sige_mfa_settings_compute(wp_unslash($_POST));

        update_option('sige_mfa_stepup', $new['sige_mfa_stepup']);
        update_option('sige_mfa_autoreplay', $new['sige_mfa_autoreplay']);
        update_option('sige_mfa_stepup_strict', $new['sige_mfa_stepup_strict']);
        update_option('sige_mfa_stepup_roles', $new['sige_mfa_stepup_roles']);

        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        foreach (['sige_mfa_stepup', 'sige_mfa_autoreplay', 'sige_mfa_stepup_strict'] as $k) {
            if ($old[$k] !== $new[$k]) {
                if (function_exists('sige_security_log')) sige_security_log('mfa_settings_change', $k . ': ' . $old[$k] . ' -> ' . $new[$k] . ' user_id=' . $uid);
            }
        }
        if ($old['sige_mfa_stepup_roles'] !== $new['sige_mfa_stepup_roles']) {
            if (function_exists('sige_security_log')) sige_security_log('mfa_settings_change', 'perfis abrangidos: [' . implode(',', $old['sige_mfa_stepup_roles']) . '] -> [' . implode(',', $new['sige_mfa_stepup_roles']) . '] user_id=' . $uid);
        }
        // Enfase: desligar o step-up e um evento distinto e auditavel.
        if ($old['sige_mfa_stepup'] === 'on' && $new['sige_mfa_stepup'] === 'off') {
            if (function_exists('sige_security_log')) sige_security_log('mfa_stepup_disabled', 'step-up DESLIGADO no painel por user_id=' . $uid);
        }

        if (function_exists('set_transient')) set_transient('sige_mfa_settings_saved_' . $uid, 'ok', 60);
        wp_safe_redirect(admin_url('options-general.php?page=sige-mfa-settings'));
        exit;
    }
}

if (!function_exists('sige_mfa_settings_render_page')) {
    /** Render do ecra de definicoes. Gate duro no topo. */
    function sige_mfa_settings_render_page(): void {
        if (!sige_mfa_settings_can_manage()) {
            wp_die('Acesso restrito ao administrador WordPress.', 'Negado', ['response' => 403]);
        }
        $stepup     = (string) get_option('sige_mfa_stepup', 'off');
        $autoreplay = (string) get_option('sige_mfa_autoreplay', 'off');
        $strict     = (string) get_option('sige_mfa_stepup_strict', 'off');
        $roles      = (array) get_option('sige_mfa_stepup_roles', ['sige_director', 'sige_admin_ti']);
        $enrolled   = sige_mfa_settings_totp_enrolled_count();
        $uid_now    = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $saved      = false;
        if ($uid_now && function_exists('get_transient') && get_transient('sige_mfa_settings_saved_' . $uid_now) === 'ok') {
            $saved = true;
            if (function_exists('delete_transient')) delete_transient('sige_mfa_settings_saved_' . $uid_now);
        }
        ?>
        <div class="wrap">
            <h1>Seguranca (MFA)</h1>
            <p>Controlos da confirmacao de identidade em operacoes criticas. Este ecra so esta disponivel ao administrador WordPress.</p>
            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p>Definicoes guardadas.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sige_mfa_settings_save">
                <?php wp_nonce_field('sige_mfa_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Step-up (confirmacao em operacoes criticas)</th>
                        <td>
                            <label><input type="checkbox" name="sige_mfa_stepup" value="on" <?php checked($stepup, 'on'); ?>> Ligado</label>
                            <p class="description">Exige confirmar a identidade (aplicacao autenticadora ou email) antes de operacoes financeiras criticas.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Reposicao automatica apos confirmacao</th>
                        <td>
                            <label><input type="checkbox" name="sige_mfa_autoreplay" value="on" <?php checked($autoreplay, 'on'); ?>> Ligado</label>
                            <p class="description">Depois de confirmar, re-executa automaticamente, uma unica vez, a operacao de servico que tinha sido bloqueada.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Modo estrito (fail-closed)</th>
                        <td>
                            <label><input type="checkbox" name="sige_mfa_stepup_strict" value="on" <?php checked($strict, 'on'); ?>> Ligado</label>
                            <p class="description">Se o canal de confirmacao falhar, bloqueia a operacao em vez de a deixar passar.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Perfis abrangidos pelo step-up</th>
                        <td>
                            <fieldset>
                            <?php foreach (sige_mfa_settings_role_choices() as $slug => $label) : ?>
                                <label>
                                    <input type="checkbox" name="sige_mfa_stepup_roles[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $roles, true), true); ?>>
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                            </fieldset>
                            <p class="description">Se nenhum perfil estiver marcado, o step-up nao abrange ninguem (equivale a desligado).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Aplicacao autenticadora (TOTP)</th>
                        <td>
                            <p class="description">Utilizadores com aplicacao autenticadora confirmada: <strong><?php echo (int) $enrolled; ?></strong>. A inscricao e por utilizador, em Utilizadores, Autenticador SIGE.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
        </div>
        <?php
    }
}

if (!defined('SIGE_MFA_SETTINGS_TEST_MODE')) {
    add_action('admin_menu', function () {
        // So regista o ecra para o administrador WordPress real; perfis SIGE nem o veem.
        if (!sige_mfa_settings_can_manage()) return;
        add_options_page(
            'Seguranca (MFA)',
            'Seguranca (MFA)',
            'manage_options',
            'sige-mfa-settings',
            'sige_mfa_settings_render_page'
        );
    });
    add_action('admin_post_sige_mfa_settings_save', 'sige_mfa_settings_handle_save');

    // Producao: nao EXIBIR avisos/deprecations do PHP no wp-admin (mantem-se no log).
    // Sem hook proprio para nao criar superficie nova. Opcional e desligavel.
    if (function_exists('is_admin') && is_admin()
        && !(defined('WP_DEBUG') && WP_DEBUG)
        && function_exists('get_option') && get_option('sige_admin_hide_php_notices', 'on') === 'on') {
        @ini_set('display_errors', '0');
    }
}
