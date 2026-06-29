<?php
/**
 * SoftGenial v96 - Regime de Mensalidade por Ciclo
 * Restaura a regra da Casa Colorida: Tempo Inteiro / Meio Dia.
 * Compatível/idempotente: se a escola não usa esta regra, nada muda.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_regime_mensalidade_ensure_schema')) {
    function sige_regime_mensalidade_ensure_schema(): void {
        global $wpdb;
        $tbl_alunos = $wpdb->prefix . 'sige_alunos';
        $tbl_serv   = $wpdb->prefix . 'sige_fin_servicos';

        $table_exists = static function(string $table) use ($wpdb): bool {
            return (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        };
        $col_exists = static function(string $table, string $col) use ($wpdb): bool {
            return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $col));
        };

        if ($table_exists($tbl_alunos) && !$col_exists($tbl_alunos, 'regime_mensalidade')) {
            $after = $col_exists($tbl_alunos, 'regime_creche') ? ' AFTER regime_creche' : '';
            $wpdb->query("ALTER TABLE {$tbl_alunos} ADD COLUMN regime_mensalidade VARCHAR(20) NULL DEFAULT NULL{$after}");
        }
        if ($table_exists($tbl_serv) && !$col_exists($tbl_serv, 'ciclo')) {
            $after = $col_exists($tbl_serv, 'categoria') ? ' AFTER categoria' : '';
            $wpdb->query("ALTER TABLE {$tbl_serv} ADD COLUMN ciclo VARCHAR(50) NOT NULL DEFAULT 'todos'{$after}");
        }
    }
}
add_action('admin_init', 'sige_regime_mensalidade_ensure_schema', 5);
add_action('wp_ajax_sige_salvar_aluno', 'sige_regime_mensalidade_ensure_schema', 1);

if (!function_exists('sige_fin_get_servico_regime_mensalidade')) {
    function sige_fin_get_servico_regime_mensalidade($regime_mensalidade, $servicos_ativos) {
        if (!$regime_mensalidade || !is_array($servicos_ativos)) return null;
        $regime = strtolower(trim((string)$regime_mensalidade));
        if (!in_array($regime, ['tempo_inteiro', 'meio_dia'], true)) return null;

        foreach ($servicos_ativos as $s) {
            $tipo = strtolower(trim((string)($s->tipo ?? '')));
            $nome = strtolower(trim((string)($s->nome ?? '')));
            $is_mensalidade = function_exists('sige_fin_servico_eh_mensalidade')
                ? sige_fin_servico_eh_mensalidade($s)
                : ($tipo === 'mensalidade' || strpos($nome, 'mensalidade') !== false || strpos($nome, 'propina') !== false);
            if (!$is_mensalidade) continue;
            if (strtolower(trim((string)($s->ciclo ?? ''))) === $regime) return $s;
        }

        $kw_map = [
            'tempo_inteiro' => ['tempo inteiro', 'tempo_inteiro', 'integral', 'full'],
            'meio_dia'      => ['meio dia', 'meio-dia', 'meio_dia', 'half'],
        ];
        foreach ($servicos_ativos as $s) {
            $tipo = strtolower(trim((string)($s->tipo ?? '')));
            $nome = strtolower(trim((string)($s->nome ?? '')));
            $is_mensalidade = function_exists('sige_fin_servico_eh_mensalidade')
                ? sige_fin_servico_eh_mensalidade($s)
                : ($tipo === 'mensalidade' || strpos($nome, 'mensalidade') !== false || strpos($nome, 'propina') !== false);
            if (!$is_mensalidade) continue;
            foreach (($kw_map[$regime] ?? []) as $kw) {
                if ($kw !== '' && strpos($nome, $kw) !== false) return $s;
            }
        }
        return null;
    }
}

if (!function_exists('sige_casa_colorida_extras_ensure_schema')) {
    function sige_casa_colorida_extras_ensure_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $tbl = $wpdb->prefix . 'sige_aluno_atividades_extras';
        $sql = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT UNSIGNED NOT NULL,
            servico_id BIGINT UNSIGNED NOT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            data_inicio DATE NULL,
            data_fim DATE NULL,
            criado_em DATETIME NULL,
            criado_por BIGINT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_aluno_servico (escola_id, aluno_id, servico_id),
            KEY idx_aluno (escola_id, aluno_id),
            KEY idx_servico (escola_id, servico_id),
            KEY idx_ativo (ativo)
        ) {$charset};";
        dbDelta($sql);
    }
}
add_action('admin_init', 'sige_casa_colorida_extras_ensure_schema', 6);
add_action('wp_ajax_sige_salvar_aluno', 'sige_casa_colorida_extras_ensure_schema', 1);

if (!function_exists('sige_fin_get_atividades_extras_aluno')) {
    function sige_fin_get_atividades_extras_aluno($aluno_id, $escola_id = null) {
        global $wpdb;
        $aluno_id = (int)$aluno_id;
        $escola_id = $escola_id !== null ? (int)$escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($aluno_id <= 0) return [];
        $tbl = $wpdb->prefix . 'sige_aluno_atividades_extras';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tbl)) !== $tbl) return [];
        $tS = $wpdb->prefix . 'sige_fin_servicos';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT s.*
             FROM {$tbl} ax
             INNER JOIN {$tS} s ON s.id = ax.servico_id AND s.escola_id = ax.escola_id
             WHERE ax.escola_id=%d AND ax.aluno_id=%d AND ax.ativo=1 AND s.ativo=1
             ORDER BY s.nome ASC",
            $escola_id, $aluno_id
        ));
    }
}
