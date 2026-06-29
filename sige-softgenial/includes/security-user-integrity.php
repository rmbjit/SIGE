<?php
/**
 * SIGE SoftGenial - User Security & Role Integrity Guard
 *
 * v12.14.2: hardening de contas de utilizador contra enumeração, password spray,
 * alterações silenciosas de perfis privilegiados e downgrade acidental/malicioso
 * de papéis WordPress SIGE críticos.
 */

if (!defined('ABSPATH') && !defined('SIGE_USER_INTEGRITY_TEST_MODE')) exit;

if (!defined('SIGE_USER_GUARD_OFF')) define('SIGE_USER_GUARD_OFF', false);

if (!function_exists('sige_user_integrity_ip')) {
    function sige_user_integrity_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', (string) $_SERVER[$h])[0]);
                return preg_replace('/[^0-9a-fA-F:\.]/', '', $ip) ?: '';
            }
        }
        return '';
    }
}

if (!function_exists('sige_user_integrity_log')) {
    function sige_user_integrity_log(string $evento, array $contexto = [], int $objeto_id = 0): void {
        $contexto['ip'] = $contexto['ip'] ?? sige_user_integrity_ip();
        $contexto['actor_id'] = $contexto['actor_id'] ?? (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        $msg = wp_json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (function_exists('sige_security_log')) {
            sige_security_log('user_integrity_' . sanitize_key($evento), (string) $msg, $objeto_id);
            return;
        }
        if (function_exists('error_log')) {
            error_log('[SIGE USER INTEGRITY] ' . $evento . ' ' . (string) $msg);
        }
    }
}

if (!function_exists('sige_user_integrity_is_real_admin')) {
    function sige_user_integrity_is_real_admin(?int $user_id = null): bool {
        $user_id = $user_id ?: (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        if ($user_id <= 0) return false;
        if (function_exists('sige_is_real_wp_admin_user')) return (bool) sige_is_real_wp_admin_user($user_id);
        return function_exists('user_can') ? (bool) user_can($user_id, 'manage_options') : false;
    }
}

if (!function_exists('sige_user_integrity_privileged_sige_slugs')) {
    /** Perfis SIGE que alteram superfície de administração/segurança. */
    function sige_user_integrity_privileged_sige_slugs(): array {
        return (array) apply_filters('sige_user_integrity_privileged_sige_slugs', [
            'admin_ti',
            'admin_escola',
            'direccao_geral',
            'director',
        ]);
    }
}

if (!function_exists('sige_user_integrity_privileged_wp_roles')) {
    /** Papéis WordPress SIGE críticos, para instalações legadas ainda baseadas em WP roles. */
    function sige_user_integrity_privileged_wp_roles(): array {
        return (array) apply_filters('sige_user_integrity_privileged_wp_roles', [
            'sige_admin_ti',
            'sige_director',
        ]);
    }
}

if (!function_exists('sige_user_integrity_sige_role_is_privileged')) {
    function sige_user_integrity_sige_role_is_privileged(string $slug): bool {
        $slug = sanitize_key($slug);
        if ($slug === '') return false;
        if (in_array($slug, sige_user_integrity_privileged_sige_slugs(), true)) return true;
        if (function_exists('sige_permissions_role_concede_gestao')) {
            try { return (bool) sige_permissions_role_concede_gestao($slug); }
            catch (Throwable $e) { return true; } // fail-closed para perfis de gestão
        }
        return false;
    }
}

if (!function_exists('sige_user_integrity_target_active_sige_role')) {
    function sige_user_integrity_target_active_sige_role(int $user_id, ?int $escola_id = null): string {
        if ($user_id <= 0) return '';
        if (function_exists('sige_permissions_get_active_role')) {
            try {
                $role = sige_permissions_get_active_role($user_id, $escola_id);
                if ($role && !empty($role->slug)) return sanitize_key((string) $role->slug);
            } catch (Throwable $e) {}
        }
        if (function_exists('sige_permissions_get_latest_active_role')) {
            try {
                $role = sige_permissions_get_latest_active_role($user_id);
                if ($role && !empty($role->slug)) return sanitize_key((string) $role->slug);
            } catch (Throwable $e) {}
        }
        return '';
    }
}

if (!function_exists('sige_user_integrity_target_is_privileged_sige')) {
    function sige_user_integrity_target_is_privileged_sige(int $user_id, ?int $escola_id = null): bool {
        $slug = sige_user_integrity_target_active_sige_role($user_id, $escola_id);
        return $slug !== '' && sige_user_integrity_sige_role_is_privileged($slug);
    }
}

if (!function_exists('sige_user_integrity_can_change_sige_role')) {
    /**
     * Guarda central para alterações de perfil SIGE activo.
     * - Perfis de gestão só podem ser atribuídos por administrator/super_admin WP real.
     * - Perfis de gestão só podem ser despromovidos por administrator/super_admin WP real.
     * - Nenhum utilizador se pode despromover a si próprio de um perfil privilegiado.
     */
    function sige_user_integrity_can_change_sige_role(int $target_id, string $new_sige_slug, ?int $escola_id = null, ?int $actor_id = null): bool {
        if (defined('SIGE_USER_GUARD_OFF') && SIGE_USER_GUARD_OFF) return true;
        if ($target_id <= 0) return false;
        $actor_id = $actor_id ?: (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        $new_sige_slug = sanitize_key($new_sige_slug);
        $old_sige_slug = sige_user_integrity_target_active_sige_role($target_id, $escola_id);
        $old_priv = $old_sige_slug !== '' && sige_user_integrity_sige_role_is_privileged($old_sige_slug);
        $new_priv = $new_sige_slug !== '' && sige_user_integrity_sige_role_is_privileged($new_sige_slug);
        $actor_real_admin = $actor_id > 0 && sige_user_integrity_is_real_admin($actor_id);

        $deny_reason = '';
        if ($new_priv && !$actor_real_admin) {
            $deny_reason = 'atribuir_privilegiado_requer_wp_admin_real';
        } elseif ($old_priv && !$new_priv && !$actor_real_admin) {
            $deny_reason = 'despromover_privilegiado_requer_wp_admin_real';
        } elseif ($old_priv && $target_id === $actor_id && !$new_priv) {
            $deny_reason = 'auto_despromocao_privilegiada_bloqueada';
        }

        if ($deny_reason !== '') {
            sige_user_integrity_log('sige_role_change_blocked', [
                'reason' => $deny_reason,
                'target_id' => $target_id,
                'old_sige_role' => $old_sige_slug,
                'new_sige_role' => $new_sige_slug,
                'escola_id' => (int) $escola_id,
                'actor_is_real_wp_admin' => $actor_real_admin ? 1 : 0,
            ], $target_id);
            return false;
        }
        return true;
    }
}

if (!function_exists('sige_user_integrity_mark_sige_role_change')) {
    function sige_user_integrity_mark_sige_role_change(int $target_id, string $new_sige_slug, ?int $escola_id = null, string $source = 'sync'): void {
        if ($target_id <= 0) return;
        $new_sige_slug = sanitize_key($new_sige_slug);
        $escola_id_int = (int) $escola_id;
        update_user_meta($target_id, '_sige_last_authorized_role_change', [
            'at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'actor_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
            'role' => $new_sige_slug,
            'escola_id' => $escola_id_int,
            'source' => sanitize_key($source),
        ]);
        if ($new_sige_slug !== '' && function_exists('sige_user_integrity_sige_role_is_privileged') && sige_user_integrity_sige_role_is_privileged($new_sige_slug)) {
            if (function_exists('sige_user_integrity_update_snapshot')) {
                sige_user_integrity_update_snapshot($target_id, $new_sige_slug, $escola_id_int, $source);
            }
            if (function_exists('sige_user_integrity_mirror_wp_role')) {
                sige_user_integrity_mirror_wp_role($target_id, $new_sige_slug, $escola_id_int, $source);
            }
        } elseif (function_exists('sige_user_integrity_is_real_admin') && sige_user_integrity_is_real_admin()) {
            if (function_exists('sige_user_integrity_retire_privileged_snapshot')) {
                sige_user_integrity_retire_privileged_snapshot($target_id, $escola_id_int, $source);
            }
        }
        sige_user_integrity_log('sige_role_change_authorized', [
            'target_id' => $target_id,
            'new_sige_role' => $new_sige_slug,
            'escola_id' => $escola_id_int,
            'source' => sanitize_key($source),
        ], $target_id);
    }
}

if (!function_exists('sige_user_integrity_restore_wp_roles')) {
    function sige_user_integrity_restore_wp_roles(int $user_id, array $old_roles): void {
        static $restoring = false;
        if ($restoring || $user_id <= 0 || empty($old_roles)) return;
        $user = function_exists('get_user_by') ? get_user_by('ID', $user_id) : false;
        if (!($user instanceof WP_User)) return;
        $old_roles = array_values(array_unique(array_filter(array_map('sanitize_key', $old_roles))));
        if (empty($old_roles)) return;
        $restoring = true;
        try {
            $first = array_shift($old_roles);
            $user->set_role($first);
            foreach ($old_roles as $r) { $user->add_role($r); }
            clean_user_cache($user_id);
        } finally {
            $restoring = false;
        }
    }
}

if (!function_exists('sige_user_integrity_wp_role_guard')) {
    function sige_user_integrity_wp_role_guard(int $user_id, string $new_role, array $old_roles): void {
        static $inside = false;
        if ($inside || (defined('SIGE_USER_GUARD_OFF') && SIGE_USER_GUARD_OFF)) return;
        $new_role = sanitize_key($new_role);
        $old_roles = array_values(array_unique(array_filter(array_map('sanitize_key', $old_roles))));
        $critical_old = (bool) array_intersect($old_roles, sige_user_integrity_privileged_wp_roles());
        if (!$critical_old) return;
        if (in_array($new_role, sige_user_integrity_privileged_wp_roles(), true)) return;

        $actor_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        if ($actor_id > 0 && sige_user_integrity_is_real_admin($actor_id)) {
            sige_user_integrity_log('wp_role_downgrade_authorized', [
                'target_id' => $user_id,
                'old_roles' => $old_roles,
                'new_role' => $new_role,
            ], $user_id);
            return;
        }

        $inside = true;
        try {
            sige_user_integrity_restore_wp_roles($user_id, $old_roles);
            sige_user_integrity_log('wp_role_downgrade_reverted', [
                'target_id' => $user_id,
                'old_roles' => $old_roles,
                'attempted_role' => $new_role,
                'reason' => 'actor_not_real_wp_admin',
            ], $user_id);
        } finally {
            $inside = false;
        }
    }
}



if (!function_exists('sige_user_integrity_snapshot_option')) {
    function sige_user_integrity_snapshot_option(): string {
        return 'sige_user_integrity_privileged_snapshots';
    }
}

if (!function_exists('sige_user_integrity_snapshot_key')) {
    function sige_user_integrity_snapshot_key(int $user_id, int $escola_id): string {
        return max(0, $user_id) . ':' . max(0, $escola_id);
    }
}

if (!function_exists('sige_user_integrity_privileged_snapshots')) {
    /**
     * Cofre lógico dos últimos perfis privilegiados autorizados.
     * Não é fonte de permissões; é uma baseline de integridade para detectar e
     * reverter desaparecimentos/downgrades silenciosos de Admin TI/Direcção.
     */
    function sige_user_integrity_privileged_snapshots(): array {
        $raw = function_exists('get_option') ? get_option(sige_user_integrity_snapshot_option(), []) : [];
        return is_array($raw) ? $raw : [];
    }
}

if (!function_exists('sige_user_integrity_save_privileged_snapshots')) {
    function sige_user_integrity_save_privileged_snapshots(array $snapshots): void {
        $clean = [];
        foreach ($snapshots as $key => $row) {
            if (!is_array($row)) continue;
            $uid = (int)($row['user_id'] ?? 0);
            $eid = (int)($row['escola_id'] ?? 0);
            $slug = sanitize_key((string)($row['sige_role'] ?? ''));
            if ($uid <= 0 || $eid <= 0 || $slug === '') continue;
            $k = sige_user_integrity_snapshot_key($uid, $eid);
            $clean[$k] = [
                'user_id' => $uid,
                'escola_id' => $eid,
                'sige_role' => $slug,
                'wp_role' => sanitize_key((string)($row['wp_role'] ?? '')),
                'source' => sanitize_key((string)($row['source'] ?? 'unknown')),
                'actor_id' => (int)($row['actor_id'] ?? 0),
                'created_at' => sanitize_text_field((string)($row['created_at'] ?? '')),
                'updated_at' => sanitize_text_field((string)($row['updated_at'] ?? '')),
                'retired_at' => sanitize_text_field((string)($row['retired_at'] ?? '')),
                'retired_by' => (int)($row['retired_by'] ?? 0),
                'retired_source' => sanitize_key((string)($row['retired_source'] ?? '')),
            ];
        }
        if (function_exists('update_option')) update_option(sige_user_integrity_snapshot_option(), $clean, false);
    }
}

if (!function_exists('sige_user_integrity_update_snapshot')) {
    function sige_user_integrity_update_snapshot(int $user_id, string $sige_role_slug, int $escola_id, string $source = 'unknown'): void {
        if ($user_id <= 0 || $escola_id <= 0) return;
        $sige_role_slug = sanitize_key($sige_role_slug);
        if ($sige_role_slug === '' || !sige_user_integrity_sige_role_is_privileged($sige_role_slug)) return;
        $snapshots = sige_user_integrity_privileged_snapshots();
        $key = sige_user_integrity_snapshot_key($user_id, $escola_id);
        $now = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        $wp_role = function_exists('sige_permissions_role_to_wp_role') ? sige_permissions_role_to_wp_role($sige_role_slug) : '';
        $old = isset($snapshots[$key]) && is_array($snapshots[$key]) ? $snapshots[$key] : [];
        $snapshots[$key] = [
            'user_id' => $user_id,
            'escola_id' => $escola_id,
            'sige_role' => $sige_role_slug,
            'wp_role' => sanitize_key((string)$wp_role),
            'source' => sanitize_key($source),
            'actor_id' => function_exists('get_current_user_id') ? (int)get_current_user_id() : 0,
            'created_at' => (string)($old['created_at'] ?? $now),
            'updated_at' => $now,
            'retired_at' => '',
            'retired_by' => 0,
            'retired_source' => '',
        ];
        sige_user_integrity_save_privileged_snapshots($snapshots);
        sige_user_integrity_log('privileged_snapshot_saved', [
            'target_id' => $user_id,
            'sige_role' => $sige_role_slug,
            'wp_role' => sanitize_key((string)$wp_role),
            'escola_id' => $escola_id,
            'source' => sanitize_key($source),
        ], $user_id);
    }
}

if (!function_exists('sige_user_integrity_retire_privileged_snapshot')) {
    function sige_user_integrity_retire_privileged_snapshot(int $user_id, int $escola_id, string $source = 'authorized_change'): void {
        if ($user_id <= 0 || $escola_id <= 0) return;
        $snapshots = sige_user_integrity_privileged_snapshots();
        $key = sige_user_integrity_snapshot_key($user_id, $escola_id);
        if (empty($snapshots[$key]) || !is_array($snapshots[$key])) return;
        $snapshots[$key]['retired_at'] = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        $snapshots[$key]['retired_by'] = function_exists('get_current_user_id') ? (int)get_current_user_id() : 0;
        $snapshots[$key]['retired_source'] = sanitize_key($source);
        sige_user_integrity_save_privileged_snapshots($snapshots);
        sige_user_integrity_log('privileged_snapshot_retired', [
            'target_id' => $user_id,
            'escola_id' => $escola_id,
            'source' => sanitize_key($source),
        ], $user_id);
    }
}

if (!function_exists('sige_user_integrity_mirror_wp_role')) {
    /**
     * Espelho WordPress só para perfis privilegiados. Preserva roles existentes e
     * acrescenta o papel SIGE crítico quando falta, para evitar que um Admin TI
     * desapareça da gestão nativa do WordPress ou da UI de staff.
     */
    function sige_user_integrity_mirror_wp_role(int $user_id, string $sige_role_slug, int $escola_id = 0, string $source = 'mirror'): bool {
        if ($user_id <= 0) return false;
        $sige_role_slug = sanitize_key($sige_role_slug);
        if ($sige_role_slug === '' || !sige_user_integrity_sige_role_is_privileged($sige_role_slug)) return false;
        $wp_role = function_exists('sige_permissions_role_to_wp_role') ? sige_permissions_role_to_wp_role($sige_role_slug) : '';
        $wp_role = sanitize_key((string)$wp_role);
        if ($wp_role === '') return false;
        $user = function_exists('get_user_by') ? get_user_by('ID', $user_id) : false;
        if (!($user instanceof WP_User)) return false;
        $roles = array_values(array_map('sanitize_key', (array)$user->roles));
        if (!in_array($wp_role, $roles, true)) {
            $user->add_role($wp_role);
            clean_user_cache($user_id);
            sige_user_integrity_log('wp_role_mirror_added', [
                'target_id' => $user_id,
                'sige_role' => $sige_role_slug,
                'wp_role' => $wp_role,
                'escola_id' => $escola_id,
                'source' => sanitize_key($source),
            ], $user_id);
        }
        return true;
    }
}

if (!function_exists('sige_user_integrity_force_restore_sige_role')) {
    /** Restauração interna fail-closed a partir de snapshot privilegiado. */
    function sige_user_integrity_force_restore_sige_role(int $user_id, string $sige_role_slug, int $escola_id, string $source = 'snapshot_reconcile'): bool {
        global $wpdb;
        if ($user_id <= 0 || $escola_id <= 0 || !function_exists('sige_permissions_tables')) return false;
        $sige_role_slug = sanitize_key($sige_role_slug);
        if ($sige_role_slug === '' || !sige_user_integrity_sige_role_is_privileged($sige_role_slug)) return false;
        $t = sige_permissions_tables();
        $role = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['roles']} WHERE slug = %s AND ativo = 1 LIMIT 1", $sige_role_slug));
        if (!$role) return false;
        $wpdb->update($t['user_roles'], ['ativo' => 0, 'updated_at' => current_time('mysql')], ['user_id' => $user_id, 'escola_id' => $escola_id], ['%d','%s'], ['%d','%d']);
        $existing_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['user_roles']} WHERE user_id = %d AND role_id = %d AND escola_id = %d LIMIT 1",
            $user_id, (int)$role->id, $escola_id
        ));
        if ($existing_id > 0) {
            $ok = $wpdb->update($t['user_roles'], ['ativo' => 1, 'updated_at' => current_time('mysql')], ['id' => $existing_id], ['%d','%s'], ['%d']);
        } else {
            $ok = $wpdb->insert($t['user_roles'], [
                'user_id' => $user_id,
                'role_id' => (int)$role->id,
                'escola_id' => $escola_id,
                'ativo' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['%d','%d','%d','%d','%s','%s']);
        }
        if ($ok === false) return false;
        sige_user_integrity_mirror_wp_role($user_id, $sige_role_slug, $escola_id, $source);
        sige_user_integrity_update_snapshot($user_id, $sige_role_slug, $escola_id, $source);
        sige_user_integrity_log('sige_role_restored_from_snapshot', [
            'target_id' => $user_id,
            'sige_role' => $sige_role_slug,
            'escola_id' => $escola_id,
            'source' => sanitize_key($source),
        ], $user_id);
        return true;
    }
}

if (!function_exists('sige_user_integrity_collect_current_privileged_profiles')) {
    function sige_user_integrity_collect_current_privileged_profiles(int $limit = 200): array {
        global $wpdb;
        if (!function_exists('sige_permissions_tables')) return [];
        $t = sige_permissions_tables();
        $rows = $wpdb->get_results("SELECT ur.user_id, ur.escola_id, r.slug
            FROM {$t['user_roles']} ur
            INNER JOIN {$t['roles']} r ON r.id = ur.role_id
            WHERE ur.ativo = 1 AND r.ativo = 1
            ORDER BY ur.updated_at DESC, ur.id DESC
            LIMIT " . max(1, min(1000, (int)$limit)));
        $out = [];
        foreach ((array)$rows as $row) {
            $slug = sanitize_key((string)($row->slug ?? ''));
            if ($slug !== '' && sige_user_integrity_sige_role_is_privileged($slug)) {
                $out[] = ['user_id' => (int)$row->user_id, 'escola_id' => (int)$row->escola_id, 'sige_role' => $slug];
            }
        }
        return $out;
    }
}

if (!function_exists('sige_user_integrity_seed_current_privileged_snapshots')) {
    function sige_user_integrity_seed_current_privileged_snapshots(): void {
        if (function_exists('get_option') && get_option('sige_user_integrity_seeded_12143')) return;
        foreach (sige_user_integrity_collect_current_privileged_profiles(500) as $row) {
            sige_user_integrity_update_snapshot((int)$row['user_id'], (string)$row['sige_role'], (int)$row['escola_id'], 'seed_existing_privileged_profile');
            sige_user_integrity_mirror_wp_role((int)$row['user_id'], (string)$row['sige_role'], (int)$row['escola_id'], 'seed_existing_privileged_profile');
        }
        if (function_exists('update_option')) update_option('sige_user_integrity_seeded_12143', 1, false);
    }
}

if (!function_exists('sige_user_integrity_reconcile_privileged_snapshots')) {
    function sige_user_integrity_reconcile_privileged_snapshots(int $limit = 200): void {
        $snapshots = sige_user_integrity_privileged_snapshots();
        if (empty($snapshots)) return;
        $n = 0;
        foreach ($snapshots as $row) {
            if (!is_array($row) || $n >= $limit) break;
            if (!empty($row['retired_at'])) continue;
            $uid = (int)($row['user_id'] ?? 0);
            $eid = (int)($row['escola_id'] ?? 0);
            $slug = sanitize_key((string)($row['sige_role'] ?? ''));
            if ($uid <= 0 || $eid <= 0 || $slug === '') continue;
            $user = function_exists('get_user_by') ? get_user_by('ID', $uid) : false;
            if (!($user instanceof WP_User)) {
                sige_user_integrity_log('privileged_snapshot_user_missing', ['target_id' => $uid, 'escola_id' => $eid, 'sige_role' => $slug], $uid);
                continue;
            }
            $current = sige_user_integrity_target_active_sige_role($uid, $eid);
            if ($current !== $slug) {
                sige_user_integrity_force_restore_sige_role($uid, $slug, $eid, 'snapshot_reconcile');
            } else {
                sige_user_integrity_mirror_wp_role($uid, $slug, $eid, 'snapshot_reconcile');
            }
            $n++;
        }
    }
}

if (!defined('SIGE_USER_INTEGRITY_TEST_MODE')) {
    // Seed/reconcile de baseline privilegiada: cobre contas Admin TI/Direcção que
    // aparecem como subscriber no WordPress ou desaparecem da UI por inconsistência.
    add_action('admin_init', function () {
        if (defined('SIGE_USER_GUARD_OFF') && SIGE_USER_GUARD_OFF) return;
        if (function_exists('sige_user_integrity_seed_current_privileged_snapshots')) {
            sige_user_integrity_seed_current_privileged_snapshots();
        }
        if (function_exists('sige_user_integrity_reconcile_privileged_snapshots')) {
            sige_user_integrity_reconcile_privileged_snapshots(200);
        }
    }, 3);

    // Não revelar se o username existe ou não no formulário de login.
    add_filter('login_errors', function ($error) {
        $txt = is_string($error) ? wp_strip_all_tags($error) : '';
        if ($txt === '') return $error;
        $preserve = ['código', 'codigo', 'verificação', 'verificacao', 'temporariamente suspenso', 'suspensa'];
        foreach ($preserve as $needle) {
            if (stripos($txt, $needle) !== false) return $error;
        }
        return 'Credenciais inválidas ou acesso temporariamente bloqueado. Confirme os dados e tente novamente.';
    }, 50);

    // Bloquear enumeração clássica por ?author=1.
    add_action('template_redirect', function () {
        if (is_admin() || empty($_GET['author'])) return;
        sige_user_integrity_log('author_enumeration_blocked', [
            'author' => sanitize_text_field(wp_unslash((string) $_GET['author'])),
            'uri' => sanitize_text_field((string) ($_SERVER['REQUEST_URI'] ?? '')),
        ]);
        wp_safe_redirect(wp_login_url(), 302);
        exit;
    }, 0);

    // REST /wp/v2/users só fica disponível a administradores WordPress reais.
    add_filter('rest_endpoints', function ($endpoints) {
        $allow = function_exists('is_user_logged_in') && is_user_logged_in()
            && sige_user_integrity_is_real_admin(function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        if ($allow || !is_array($endpoints)) return $endpoints;
        foreach (array_keys($endpoints) as $route) {
            if (strpos((string) $route, '/wp/v2/users') === 0) unset($endpoints[$route]);
        }
        return $endpoints;
    }, 20);

    // O SIGE não precisa de XML-RPC; desactivar reduz brute-force remoto e pingback abuse.
    if (!defined('SIGE_XMLRPC_ALLOW') || !SIGE_XMLRPC_ALLOW) {
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('wp_headers', function ($headers) {
            if (is_array($headers) && isset($headers['X-Pingback'])) unset($headers['X-Pingback']);
            return $headers;
        }, 20);
    }

    // Auditoria + auto-reversão de downgrade de WP role crítico quando não vem de admin real.
    add_action('set_user_role', function ($user_id, $role, $old_roles) {
        $uid = (int) $user_id;
        $old = is_array($old_roles) ? $old_roles : [];
        sige_user_integrity_log('wp_role_set', [
            'target_id' => $uid,
            'old_roles' => array_values(array_map('strval', $old)),
            'new_role' => sanitize_key((string) $role),
        ], $uid);
        sige_user_integrity_wp_role_guard($uid, (string) $role, $old);
    }, 10, 3);

    add_action('added_user_role', function ($user_id, $role) {
        sige_user_integrity_log('wp_role_added', [
            'target_id' => (int) $user_id,
            'role' => sanitize_key((string) $role),
        ], (int) $user_id);
    }, 10, 2);

    add_action('removed_user_role', function ($user_id, $role) {
        sige_user_integrity_log('wp_role_removed', [
            'target_id' => (int) $user_id,
            'role' => sanitize_key((string) $role),
        ], (int) $user_id);
    }, 10, 2);
}
