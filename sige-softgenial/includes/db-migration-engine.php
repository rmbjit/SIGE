<?php
/**
 * SIGE SoftGenial - DB Migration Engine v12.7.0
 *
 * Camada incremental e idempotente para sincronização segura de schema entre
 * instâncias antigas e versões novas do plugin. Não altera visual, cálculos nem
 * dados financeiros existentes; apenas cria a tabela de histórico e adiciona
 * colunas/índices em falta que o código actual já utiliza com fallback.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_dbm_table_exists')) {
    function sige_dbm_table_exists(string $table): bool {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $table
        ));
        return ((int)$exists) > 0;
    }
}

if (!function_exists('sige_dbm_column_exists')) {
    function sige_dbm_column_exists(string $table, string $column): bool {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            $table,
            $column
        ));
        return ((int)$exists) > 0;
    }
}

if (!function_exists('sige_dbm_index_exists')) {
    function sige_dbm_index_exists(string $table, string $index): bool {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s",
            $table,
            $index
        ));
        return ((int)$exists) > 0;
    }
}

if (!function_exists('sige_dbm_create_history_table')) {
    function sige_dbm_create_history_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'sige_migrations';
        $cc = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            migration_key VARCHAR(80) NOT NULL,
            schema_version VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            details LONGTEXT DEFAULT NULL,
            executed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY migration_key (migration_key),
            KEY schema_version (schema_version),
            KEY status (status),
            KEY executed_at (executed_at)
        ) {$cc};");
    }
}

if (!function_exists('sige_dbm_record')) {
    function sige_dbm_record(string $key, string $status, array $details = []): void {
        global $wpdb;
        $table = $wpdb->prefix . 'sige_migrations';
        if (!sige_dbm_table_exists($table)) {
            return;
        }
        $wpdb->replace($table, [
            'migration_key' => $key,
            'schema_version' => '12.7.0',
            'status' => $status,
            'details' => function_exists('wp_json_encode') ? wp_json_encode($details) : json_encode($details),
            'executed_by' => function_exists('get_current_user_id') ? (int)get_current_user_id() : null,
            'executed_at' => current_time('mysql'),
        ]);
    }
}

if (!function_exists('sige_dbm_already_done')) {
    function sige_dbm_already_done(string $key): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'sige_migrations';
        if (!sige_dbm_table_exists($table)) {
            return false;
        }
        $done = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM `{$table}` WHERE migration_key = %s AND status = 'success'",
            $key
        ));
        return ((int)$done) > 0;
    }
}

if (!function_exists('sige_dbm_run_once')) {
    function sige_dbm_run_once(string $key, callable $callback): void {
        if (sige_dbm_already_done($key)) {
            return;
        }
        $details = ['started_at' => current_time('mysql')];
        try {
            $result = $callback();
            if (is_array($result)) {
                $details = array_merge($details, $result);
            }
            $details['finished_at'] = current_time('mysql');
            sige_dbm_record($key, 'success', $details);
        } catch (Throwable $e) {
            $details['finished_at'] = current_time('mysql');
            $details['error'] = $e->getMessage();
            sige_dbm_record($key, 'error', $details);
            if (function_exists('sige_obs')) {
                sige_obs('migration.error', ['key' => $key, 'error' => $e->getMessage()]);
            } else {
                error_log('SIGE Migration error [' . $key . ']: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('sige_db_migration_engine_run')) {
    function sige_db_migration_engine_run(): void {
        global $wpdb;

        // Segurança: apenas administradores devem disparar alterações de schema.
        if (!is_admin() || !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
            return;
        }

        $installed = get_option('sige_db_migration_engine', '0');
        if (version_compare((string)$installed, '12.7.0', '>=')) {
            return;
        }

        sige_dbm_create_history_table();

        sige_dbm_run_once('m1270_finance_schema_columns', function () use ($wpdb) {
            $changes = [];

            $fechos = $wpdb->prefix . 'sige_fin_fechos_caixa';
            if (sige_dbm_table_exists($fechos) && !sige_dbm_column_exists($fechos, 'user_id_turno')) {
                $wpdb->query("ALTER TABLE `{$fechos}` ADD COLUMN `user_id_turno` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `fechado_por`");
                $changes[] = 'added user_id_turno to sige_fin_fechos_caixa';
            }
            if (sige_dbm_table_exists($fechos) && sige_dbm_column_exists($fechos, 'user_id_turno') && !sige_dbm_index_exists($fechos, 'idx_user_id_turno')) {
                $wpdb->query("ALTER TABLE `{$fechos}` ADD KEY `idx_user_id_turno` (`user_id_turno`)");
                $changes[] = 'added idx_user_id_turno';
            }

            $lanc = $wpdb->prefix . 'sige_fin_lancamentos';
            if (sige_dbm_table_exists($lanc) && !sige_dbm_column_exists($lanc, 'desconto_especial_por')) {
                $wpdb->query("ALTER TABLE `{$lanc}` ADD COLUMN `desconto_especial_por` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `motivo_desconto_especial`");
                $changes[] = 'added desconto_especial_por to sige_fin_lancamentos';
            }
            if (sige_dbm_table_exists($lanc) && sige_dbm_column_exists($lanc, 'desconto_especial_por') && !sige_dbm_index_exists($lanc, 'idx_desconto_especial_por')) {
                $wpdb->query("ALTER TABLE `{$lanc}` ADD KEY `idx_desconto_especial_por` (`desconto_especial_por`)");
                $changes[] = 'added idx_desconto_especial_por';
            }

            return ['changes' => $changes ?: ['no schema changes needed']];
        });

        update_option('sige_db_version', '12.7.0', false);
        update_option('sige_db_migration_engine', '12.7.0', false);
        update_option('sige_db_migrated_at', current_time('mysql'), false);
    }
}

add_action('admin_init', 'sige_db_migration_engine_run', 6);
