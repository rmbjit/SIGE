<?php
/**
 * SIGE - Fonte de verdade UNICA do "staff com perfil SIGE de uma escola".
 *
 * Porque existe este ficheiro
 * ---------------------------
 * O criterio de "quem tem perfil SIGE activo nesta escola" estava duplicado em
 * admin/hr/equipe-view.php (Equipa) e admin/system/permissions-ui.php (Permissoes
 * e Perfis). As duas copias divergiram (uma filtrava por r.ativo, a outra nao),
 * e foi exactamente essa divergencia que fez parte da equipa ficar invisivel na
 * Equipa apesar de aparecer em Permissoes. Centralizar o criterio numa unica
 * funcao elimina a classe inteira de bugs "as duas listas nao batem certo".
 *
 * CRITERIO CANONICO (definitivo)
 * ------------------------------
 * Um utilizador tem "perfil SIGE actual" numa escola quando tem uma ATRIBUICAO
 * activa (sige_user_roles.ativo = 1) a um papel que NAO e de portal
 * (aluno/encarregado). NAO se filtra pelo flag global do papel (sige_roles.ativo):
 * esse flag indica se o papel e oferecido, nao se este utilizador o detem. E o
 * mesmo criterio que ja alimentava a coluna "PERFIL SIGE ACTUAL" em Permissoes.
 *
 * Notas de seguranca/ambito
 * -------------------------
 * - Tenant-scoped: recebe sempre o escola_id e nunca cruza escolas.
 * - NAO decide politica de admin: nao exclui o administrador WordPress real. Cada
 *   pagina aplica a sua propria politica (a Equipa exclui o super admin; a pagina
 *   de Permissoes mostra-o de proposito). Esta camada so responde "quem tem
 *   perfil SIGE activo nesta escola".
 * - So leitura. Sem writes, sem schema, sem alteracao de permissoes reais.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_staff_portal_role_slugs')) {
    /**
     * Papeis de PORTAL (slugs da tabela sige_roles, sem prefixo). Tem fluxo
     * proprio (contas de aluno/encarregado) e NUNCA sao equipa/staff.
     *
     * @return string[]
     */
    function sige_staff_portal_role_slugs(): array {
        return ['aluno', 'encarregado'];
    }
}

if (!function_exists('sige_staff_active_profile_user_ids')) {
    /**
     * IDs dos utilizadores com perfil SIGE actual nesta escola (criterio canonico).
     *
     * @param int $escola_id
     * @return int[] Lista deduplicada de user IDs (pode ser vazia).
     */
    function sige_staff_active_profile_user_ids(int $escola_id): array {
        global $wpdb;
        if ($escola_id <= 0 || !function_exists('sige_permissions_tables')) {
            return [];
        }
        $t = sige_permissions_tables();
        if (empty($t['user_roles']) || empty($t['roles'])) {
            return [];
        }
        $portal = sige_staff_portal_role_slugs();
        $ph = implode(',', array_fill(0, count($portal), '%s'));
        $sql = "SELECT DISTINCT ur.user_id
                  FROM {$t['user_roles']} ur
                  INNER JOIN {$t['roles']} r ON r.id = ur.role_id
                 WHERE ur.escola_id = %d AND ur.ativo = 1
                   AND r.slug NOT IN ($ph)";
        $ids = $wpdb->get_col($wpdb->prepare($sql, array_merge([$escola_id], $portal)));
        return array_values(array_unique(array_map('intval', (array) $ids)));
    }
}

if (!function_exists('sige_staff_active_profile_map')) {
    /**
     * Mapa user_id => perfil SIGE actual nesta escola. Usa o MESMO criterio
     * canonico de sige_staff_active_profile_user_ids(). Pensado para rotular o
     * cargo e categorizar (o papel WordPress legado pode estar desactualizado
     * face ao perfil SIGE atribuido agora).
     *
     * Em duplicados por utilizador, a atribuicao mais recente vence (ORDER BY
     * ur.id ASC -> a ultima sobrescreve).
     *
     * @param int $escola_id
     * @return array<int, array{slug:string, nome:string, wp:string}>
     *         slug = papel sige_roles (sem prefixo); nome = rotulo do papel;
     *         wp = papel WordPress equivalente (sige_*) ou '' se nao mapeavel.
     */
    function sige_staff_active_profile_map(int $escola_id): array {
        global $wpdb;
        $map = [];
        if ($escola_id <= 0 || !function_exists('sige_permissions_tables')) {
            return $map;
        }
        $t = sige_permissions_tables();
        if (empty($t['user_roles']) || empty($t['roles'])) {
            return $map;
        }
        $portal = sige_staff_portal_role_slugs();
        $ph = implode(',', array_fill(0, count($portal), '%s'));
        $sql = "SELECT ur.user_id, r.slug, r.nome
                  FROM {$t['user_roles']} ur
                  INNER JOIN {$t['roles']} r ON r.id = ur.role_id
                 WHERE ur.escola_id = %d AND ur.ativo = 1
                   AND r.slug NOT IN ($ph)
                 ORDER BY ur.id ASC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge([$escola_id], $portal)));
        foreach ((array) $rows as $r) {
            $wp = function_exists('sige_permissions_role_to_wp_role')
                ? sige_permissions_role_to_wp_role((string) $r->slug)
                : '';
            $map[(int) $r->user_id] = [
                'slug' => (string) $r->slug,
                'nome' => (string) $r->nome,
                'wp'   => $wp,
            ];
        }
        return $map;
    }
}
