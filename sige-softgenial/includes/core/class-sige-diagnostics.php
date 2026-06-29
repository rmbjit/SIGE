<?php
/** SoftGenial Core v1.0 Foundation - Diagnóstico técnico. */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Diagnostics')) {
    final class SIGE_Diagnostics {
        public static function collect(): array {
            global $wpdb;
            $required_tables = [
                'sige_alunos', 'sige_turmas', 'sige_matriculas',
                'sige_fin_lancamentos', 'sige_fin_pagamentos', 'sige_fin_servicos',
                'sige_transport_rota', 'sige_logs_auditoria'
            ];
            $tables = [];
            foreach ($required_tables as $suffix) {
                $table = $wpdb->prefix . $suffix;
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
                $tables[$suffix] = $exists ? 'ok' : 'em falta';
            }

            return [
                'plugin_version' => defined('SIGE_VERSION') ? SIGE_VERSION : 'N/D',
                'core_version' => class_exists('SIGE_Core') ? SIGE_Core::CORE_VERSION : 'N/D',
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'mysql_version' => $wpdb->db_version(),
                'site_url' => home_url(),
                'timezone' => wp_timezone_string(),
                'license' => class_exists('SIGE_License') ? SIGE_License::get_state() : [],
                'queue' => class_exists('SIGE_Queue') ? SIGE_Queue::stats() : [],
                'tables' => $tables,
                'logs' => class_exists('SIGE_Logger') ? SIGE_Logger::recent(12) : [],
                'build' => function_exists('sige_version_status') ? sige_version_status() : [],
            ];
        }
    }
}
