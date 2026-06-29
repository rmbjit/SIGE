<?php
/**
 * SIGE SoftGenial - Page Guard Helper v12.9.6
 *
 * Centraliza a verificação de acesso à entrada de cada página/view.
 *
 * Objectivo único: tornar a matriz de permissões SIGE *efectiva*.
 * Quando um administrador atribui um perfil SIGE a um utilizador e ajusta as
 * permissões desse perfil, esses ajustes têm de mandar de verdade - em vez de
 * serem silenciosamente sobrepostos por verificações `current_user_can('sige_xxx')`
 * espalhadas pelos ficheiros de view.
 *
 * Ordem de decisão (idêntica em todas as páginas):
 *   1. Admin WP real (`manage_options` ou `is_super_admin`) → entrada permitida.
 *   2. Utilizador com Perfil SIGE activo → ENTRADA APENAS via matriz (`sige_can`).
 *      Este é o ponto crítico: a matriz manda. Não há fallback para WP roles
 *      legadas quando há perfil SIGE atribuído.
 *   3. Utilizador SEM Perfil SIGE activo → fallback compatível para as WP caps
 *      legadas indicadas pela própria página. Preserva o comportamento de
 *      ambientes ainda não migrados.
 *   4. Caso contrário → acesso negado, mensagem padrão renderizada.
 *
 * Sem mexer em fórmulas financeiras, académicas, ou em lógica de negócio
 * dentro das páginas.
 *
 * @since 12.9.6
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_page_guard_is_real_admin')) {
    /**
     * Bypass *estrito*: apenas administradores WordPress reais.
     *
     * Diferente de `sige_permissions_is_super_admin`, que historicamente
     * incluía utilizadores com a WP role `sige_admin_ti`. Aqui só passa
     * `manage_options` ou `is_super_admin` (multisite). Isto garante que a
     * matriz SIGE - quando atribuída - passa a mandar mesmo para perfis
     * técnicos (`admin_ti` / `admin_escola`).
     *
     * Justificação: as roles `admin_ti` / `admin_escola` recebem `$all_permissions`
     * no seed, portanto continuam a ter acesso total via matriz. A diferença
     * é que, se o administrador remover uma permissão dessa role, a remoção
     * passa a ser efectiva em vez de ser silenciosamente ignorada.
     *
     * Rede de segurança: utilizadores `manage_options` permanecem com bypass
     * absoluto e podem sempre recuperar uma matriz mal configurada.
     *
     * @param int|null $user_id ID do utilizador (defaults to current user).
     * @return bool TRUE se o utilizador é admin WP real.
     */
    function sige_page_guard_is_real_admin(?int $user_id = null): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) return false;
        if (function_exists('sige_is_real_wp_admin_user')) return sige_is_real_wp_admin_user($user_id);
        $user = function_exists('get_userdata') ? get_userdata($user_id) : null;
        $roles = ($user && !empty($user->roles)) ? array_map('strval', (array)$user->roles) : [];
        return in_array('administrator', $roles, true) || in_array('super_admin', $roles, true);
    }
}

if (!function_exists('sige_page_guard_has_active_sige_role')) {
    /**
     * Indica se o utilizador tem um perfil SIGE activo na escola corrente.
     * Usado para decidir se o fallback legado (WP caps) ainda se aplica.
     */
    function sige_page_guard_has_active_sige_role(?int $user_id = null): bool {
        if (!function_exists('sige_permissions_get_active_role')) return false;
        $role = sige_permissions_get_active_role($user_id);
        return ($role && !empty($role->id));
    }
}

if (!function_exists('sige_page_guard_allows')) {
    /**
     * Verifica se o utilizador corrente pode entrar numa página.
     *
     * @param array       $sige_permissions Permissões SIGE aceitáveis (any-of).
     *                                      Ex.: ['academico.lancar_notas']
     * @param array       $legacy_caps      WP caps legadas como fallback,
     *                                      usadas APENAS quando o utilizador não
     *                                      tem perfil SIGE activo. Ex.:
     *                                      ['sige_director','sige_professor']
     * @return bool
     */
    function sige_page_guard_allows(array $sige_permissions, array $legacy_caps = []): bool {
        // v12.11.9.6 - colaborador RH desactivado não deve entrar por matriz nem fallback legado.
        // Esta verificação vem antes do bypass legado porque algumas roles SIGE antigas ainda têm manage_options.
        if (function_exists('sige_rh_user_is_active_for_school') && !sige_rh_user_is_active_for_school(get_current_user_id())) {
            return false;
        }

        // 1. Admin WP real → bypass absoluto.
        if (sige_page_guard_is_real_admin()) return true;

        // 2. Matriz SIGE - fonte primária de verdade.
        if (function_exists('sige_can') && !empty($sige_permissions)) {
            foreach ($sige_permissions as $perm) {
                $perm = (string)$perm;
                if ($perm === '') continue;
                if (sige_can($perm)) return true;
            }
        }

        // 3. Fallback legado - só vale se o utilizador AINDA não tem perfil SIGE.
        // Quando há perfil SIGE atribuído, a matriz é soberana.
        if (!sige_page_guard_has_active_sige_role()) {
            foreach ($legacy_caps as $cap) {
                $cap = (string)$cap;
                if ($cap === '') continue;
                if (current_user_can($cap)) return true;
            }
        }

        return false;
    }
}

if (!function_exists('sige_page_guard_render_denied')) {
    /**
     * Renderiza a mensagem padrão de acesso restrito.
     * Estilo neutro, alinhado com o resto do app shell.
     */
    function sige_page_guard_render_denied(string $title = 'Acesso restrito', string $msg = 'O seu perfil SIGE não tem permissão para aceder a esta secção.'): void {
        echo '<div class="sige-page-guard-denied notice notice-error" style="padding:22px 26px;margin:24px;border-radius:18px;background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12;box-shadow:0 10px 28px rgba(124,45,18,.06);font-family:Segoe UI,Tahoma,sans-serif;">';
        echo '<p style="margin:0 0 6px;font-weight:800;font-size:14px;letter-spacing:.02em;">🔒 ' . esc_html($title) . '</p>';
        echo '<p style="margin:0;font-size:13.5px;line-height:1.6;color:#9a3412;">' . esc_html($msg) . '</p>';
        echo '</div>';
    }
}

if (!function_exists('sige_page_guard')) {
    /**
     * Guarda completa de página: verifica e renderiza mensagem se negar.
     *
     * Uso típico no topo de uma view:
     *   if (!sige_page_guard(['academico.lancar_notas'], ['sige_professor','sige_director'])) return;
     *
     * @param array|string $sige_permissions Permissão(ões) SIGE necessária(s).
     * @param array        $legacy_caps      WP caps de fallback (sem perfil SIGE).
     * @param array        $opts             Opções: title, message.
     * @return bool TRUE se a página pode prosseguir, FALSE se negou (e renderizou).
     */
    function sige_page_guard($sige_permissions, array $legacy_caps = [], array $opts = []): bool {
        $perms = is_array($sige_permissions) ? $sige_permissions : [(string)$sige_permissions];
        if (sige_page_guard_allows($perms, $legacy_caps)) return true;

        $title = isset($opts['title']) ? (string)$opts['title'] : 'Acesso restrito';
        $msg = isset($opts['message']) ? (string)$opts['message'] : 'O seu perfil SIGE não tem permissão para aceder a esta secção. Contacte o administrador da escola.';
        sige_page_guard_render_denied($title, $msg);
        return false;
    }
}
