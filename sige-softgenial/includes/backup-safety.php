<?php
/**
 * SIGE SoftGenial - Backup Safety Layer
 * v12.11.9.25 - Fase 1/P1.2
 *
 * Snapshots defensivos antes de operações de restauro e self-test de escrita/leitura.
 * Esta camada não substitui backup externo do servidor, mas evita restauros cegos.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_backup_secure_dir')) {
    function sige_backup_secure_dir(): string {
        $uploads = wp_get_upload_dir();
        $base = isset($uploads['basedir']) ? rtrim((string)$uploads['basedir'], DIRECTORY_SEPARATOR) : '';
        if ($base === '') return '';
        $dir = $base . DIRECTORY_SEPARATOR . 'sige-secure-backups';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        if (is_dir($dir)) {
            $files = [
                '.htaccess' => "Order deny,allow\nDeny from all\n",
                'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><remove users=\"*\" roles=\"\" verbs=\"\" /><add accessType=\"Deny\" users=\"*\" /></authorization></system.webServer></configuration>\n",
                'index.html' => '',
                'index.php' => "<?php\n// Silence is golden.\nhttp_response_code(403);\nexit;\n",
            ];
            foreach ($files as $name => $content) {
                $path = $dir . DIRECTORY_SEPARATOR . $name;
                if (!file_exists($path)) {
                    @file_put_contents($path, $content, LOCK_EX);
                }
            }
        }
        return is_dir($dir) && is_writable($dir) ? $dir : '';
    }
}

if (!function_exists('sige_backup_cleanup_old_snapshots')) {
    function sige_backup_cleanup_old_snapshots(int $days = 30): int {
        $dir = sige_backup_secure_dir();
        if ($dir === '') return 0;
        $days = max(1, $days);
        $cutoff = time() - ($days * DAY_IN_SECONDS);
        $deleted = 0;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if (!is_file($file)) continue;
            if (filemtime($file) !== false && filemtime($file) < $cutoff) {
                if (@unlink($file)) $deleted++;
            }
        }
        return $deleted;
    }
}

if (!function_exists('sige_backup_write_json_snapshot')) {
    function sige_backup_write_json_snapshot(string $prefix, array $payload): string {
        $dir = sige_backup_secure_dir();
        if ($dir === '') return '';
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '-', $prefix) ?: 'snapshot';
        $file = $dir . DIRECTORY_SEPARATOR . $prefix . '-' . gmdate('Ymd-His') . '-' . wp_generate_password(8, false, false) . '.json';
        $payload['_manifest'] = [
            'plugin' => 'sige-softgenial',
            'version' => defined('SIGE_VERSION') ? SIGE_VERSION : '',
            'created_at' => current_time('mysql'),
            'site' => home_url('/'),
            'type' => $payload['type'] ?? $prefix,
            'schema' => 'phase1-backup-safety-v2',
        ];
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return '';
        $ok = @file_put_contents($file, $json, LOCK_EX);
        if ($ok === false) return '';
        @chmod($file, 0600);
        update_option('sige_last_backup_snapshot', [
            'file' => basename($file),
            'created_at' => current_time('mysql'),
            'prefix' => $prefix,
            'size' => filesize($file),
        ], false);
        sige_backup_cleanup_old_snapshots((int)apply_filters('sige_backup_retention_days', 30));
        return $file;
    }
}

if (!function_exists('sige_backup_config_snapshot')) {
    function sige_backup_config_snapshot(string $reason = 'manual'): string {
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($eid <= 0) return '';
        $table = $wpdb->prefix . 'sige_config';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return '';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE escola_id=%d LIMIT 1", $eid), ARRAY_A);
        if (!is_array($row) || empty($row)) return '';
        return sige_backup_write_json_snapshot('config-e' . $eid, [
            'type' => 'sige_config_snapshot',
            'reason' => sanitize_key($reason),
            'escola_id' => $eid,
            'created_at' => current_time('mysql'),
            'data' => $row,
        ]);
    }
}

if (!function_exists('sige_backup_school_light_snapshot')) {
    /**
     * Snapshot leve de metadados críticos da escola, útil antes de operações perigosas.
     * Não exporta todo o financeiro/notas por padrão para evitar ficheiros enormes.
     */
    function sige_backup_school_light_snapshot(string $reason = 'manual'): string {
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($eid <= 0) return '';
        $tables = [
            'config' => 'sige_config',
            'anos' => 'sige_anos_lectivos',
            'turmas' => 'sige_turmas',
            'disciplinas' => 'sige_disciplinas',
            'servicos' => 'sige_fin_servicos',
            'centros' => 'sige_fin_centros',
        ];
        $data = [];
        foreach ($tables as $label => $name) {
            $t = $wpdb->prefix . $name;
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) continue;
            if (function_exists('sige_table_has_column_secure') && !sige_table_has_column_secure($t, 'escola_id')) continue;
            $data[$label] = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE escola_id=%d LIMIT 500", $eid), ARRAY_A);
        }
        return sige_backup_write_json_snapshot('school-light-e' . $eid, [
            'type' => 'sige_school_light_snapshot',
            'reason' => sanitize_key($reason),
            'escola_id' => $eid,
            'created_at' => current_time('mysql'),
            'data' => $data,
        ]);
    }
}

if (!function_exists('sige_backup_selftest')) {
    function sige_backup_selftest(): array {
        $dir = sige_backup_secure_dir();
        $result = ['ok' => false, 'dir' => $dir, 'writable' => false, 'roundtrip' => false, 'protected_files' => false];
        if ($dir === '') return $result;
        $result['writable'] = is_writable($dir);
        $result['protected_files'] = file_exists($dir . DIRECTORY_SEPARATOR . '.htaccess') && file_exists($dir . DIRECTORY_SEPARATOR . 'index.php');
        $file = sige_backup_write_json_snapshot('selftest', ['type' => 'selftest', 'ts' => time()]);
        if ($file && is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            $result['roundtrip'] = is_array($decoded) && ($decoded['type'] ?? '') === 'selftest' && (($decoded['_manifest']['schema'] ?? '') === 'phase1-backup-safety-v2');
            @unlink($file);
        }
        $result['ok'] = $result['writable'] && $result['roundtrip'] && $result['protected_files'];
        return $result;
    }
}
