<?php
/**
 * SIGE SoftGenial - Versionamento PRO
 *
 * Objectivo:
 * - Centralizar a leitura do manifesto de build;
 * - Detectar divergência entre Version do plugin, SIGE_VERSION e BUILD.json;
 * - Guardar histórico mínimo de instalação para o Hub/diagnóstico;
 * - Evitar repetição do erro “ZIP com nome novo, mas header antigo”.
 *
 * Nota: o WordPress lê a versão do plugin a partir do header do ficheiro
 * principal. Por isso, o controlo automático real acontece no processo de
 * build, através de tools/build-release.php, incluído neste pacote.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_build_manifest')) {
    function sige_build_manifest(): array {
        $path = defined('SIGE_PATH') ? SIGE_PATH . 'BUILD.json' : '';
        if (!$path || !file_exists($path)) {
            return [];
        }
        $json = file_get_contents($path);
        $data = json_decode((string)$json, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('sige_plugin_header_version')) {
    function sige_plugin_header_version(): string {
        if (!defined('SIGE_PATH')) return '';
        $main = SIGE_PATH . 'sige-softgenial.php';
        if (!file_exists($main)) return '';
        $src = (string) file_get_contents($main, false, null, 0, 4096);
        if (preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $src, $m)) {
            return trim((string)$m[1]);
        }
        return '';
    }
}

if (!function_exists('sige_version_status')) {
    function sige_version_status(): array {
        $manifest = sige_build_manifest();
        $constant = defined('SIGE_VERSION') ? (string)SIGE_VERSION : '';
        $header = sige_plugin_header_version();
        $manifest_version = isset($manifest['version']) ? (string)$manifest['version'] : '';

        $ok = true;
        $issues = [];
        if ($header && $constant && $header !== $constant) {
            $ok = false;
            $issues[] = 'Header do plugin diferente de SIGE_VERSION.';
        }
        if ($manifest_version && $constant && $manifest_version !== $constant) {
            $ok = false;
            $issues[] = 'BUILD.json diferente de SIGE_VERSION.';
        }
        if (!$manifest_version) {
            $issues[] = 'BUILD.json não encontrado ou sem versão.';
        }

        return [
            'ok' => $ok,
            'header_version' => $header ?: 'N/D',
            'constant_version' => $constant ?: 'N/D',
            'manifest_version' => $manifest_version ?: 'N/D',
            'build_id' => isset($manifest['build_id']) ? (string)$manifest['build_id'] : 'N/D',
            'build_time' => isset($manifest['build_time']) ? (string)$manifest['build_time'] : 'N/D',
            'base_version' => isset($manifest['base_version']) ? (string)$manifest['base_version'] : 'N/D',
            'issues' => $issues,
            'manifest' => $manifest,
        ];
    }
}

if (!function_exists('sige_version_record_runtime')) {
    function sige_version_record_runtime(): void {
        $status = sige_version_status();
        update_option('sige_plugin_version_installed', $status['constant_version'], false);
        update_option('sige_plugin_header_version', $status['header_version'], false);
        update_option('sige_plugin_build_id', $status['build_id'], false);
        update_option('sige_plugin_build_time', $status['build_time'], false);
        update_option('sige_plugin_version_status_ok', $status['ok'] ? '1' : '0', false);
        if (!empty($status['issues'])) {
            update_option('sige_plugin_version_issues', wp_json_encode($status['issues']), false);
        } else {
            delete_option('sige_plugin_version_issues');
        }
    }
}
add_action('admin_init', 'sige_version_record_runtime', 2);

if (!function_exists('sige_version_admin_notice')) {
    function sige_version_admin_notice(): void {
        if (!is_admin() || !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return;
        $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
        if ($page !== 'sige-app') return;
        $status = sige_version_status();
        if (!empty($status['ok'])) return;
        echo '<div class="notice notice-error"><p><strong>SIGE SoftGenial - aviso de versionamento:</strong> ' .
            esc_html(implode(' ', $status['issues'])) .
            ' Header: ' . esc_html($status['header_version']) .
            ' · SIGE_VERSION: ' . esc_html($status['constant_version']) .
            ' · BUILD.json: ' . esc_html($status['manifest_version']) .
            '</p></div>';
    }
}
add_action('admin_notices', 'sige_version_admin_notice');
