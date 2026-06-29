<?php
/**
 * SIGE SoftGenial - Alunos Performance & Modularization Contract.
 *
 * v12.17.0 cria uma camada pequena e partilhada para o modulo Alunos:
 * - SELECT leve para a lista principal.
 * - SELECT minimizado para exportacao Excel/cartoes.
 * - Payload minimo para accoes de impressao no card.
 *
 * Nao altera schema, dados historicos, formulas financeiras, formulas academicas
 * nem permissões reais. A edicao continua a usar sige_get_aluno_full.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_alunos_perf_table_exists')) {
    function sige_alunos_perf_table_exists(string $table_name): bool {
        global $wpdb;
        $table_name = trim($table_name);
        if ($table_name === '') return false;
        return (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));
    }
}

if (!function_exists('sige_alunos_perf_column_exists')) {
    function sige_alunos_perf_column_exists(string $table_name, string $column_name): bool {
        global $wpdb;
        static $cache = [];
        $table_name = trim($table_name);
        $column_name = trim($column_name);
        if ($table_name === '' || $column_name === '') return false;
        $key = $table_name . '::' . $column_name;
        if (array_key_exists($key, $cache)) return (bool)$cache[$key];
        if (!sige_alunos_perf_table_exists($table_name)) {
            $cache[$key] = false;
            return false;
        }
        $cache[$key] = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $wpdb->esc_like($column_name)));
        return (bool)$cache[$key];
    }
}

if (!function_exists('sige_alunos_perf_safe_identifier')) {
    function sige_alunos_perf_safe_identifier(string $identifier, string $fallback = 'campo'): string {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
        return $clean !== '' ? $clean : $fallback;
    }
}

if (!function_exists('sige_alunos_perf_sql_default')) {
    function sige_alunos_perf_sql_default($default): string {
        if (is_int($default) || is_float($default)) return (string)$default;
        if (is_bool($default)) return $default ? '1' : '0';
        $default = (string)$default;
        if ($default === 'CURRENT_DATE') return 'CURRENT_DATE';
        return "'" . str_replace("'", "''", $default) . "'";
    }
}

if (!function_exists('sige_alunos_perf_select_sql')) {
    /**
     * Constroi uma lista SELECT defensiva.
     * Se uma coluna opcional nao existir, devolve default AS alias para evitar fatal SQL.
     *
     * @param string $table_name tabela real, ex.: wp_sige_alunos.
     * @param string $table_alias alias SQL, ex.: a.
     * @param array<string,array{column?:string,default?:mixed}> $fields
     */
    function sige_alunos_perf_select_sql(string $table_name, string $table_alias, array $fields): string {
        $table_alias = sige_alunos_perf_safe_identifier($table_alias, 'a');
        $parts = [];
        foreach ($fields as $alias => $meta) {
            $alias = sige_alunos_perf_safe_identifier((string)$alias, 'campo');
            $column = sige_alunos_perf_safe_identifier((string)($meta['column'] ?? $alias), $alias);
            $default = $meta['default'] ?? '';
            if (sige_alunos_perf_column_exists($table_name, $column)) {
                $parts[] = "{$table_alias}.{$column} AS {$alias}";
            } else {
                $parts[] = sige_alunos_perf_sql_default($default) . " AS {$alias}";
            }
        }
        return implode(', ', $parts);
    }
}

if (!function_exists('sige_alunos_perf_list_fields')) {
    function sige_alunos_perf_list_fields(): array {
        return [
            'id' => ['default' => 0],
            'nome_completo' => ['default' => ''],
            'numero_processo' => ['default' => ''],
            'foto' => ['default' => ''],
            'status' => ['default' => 'activo'],
            'data_nascimento' => ['default' => ''],
            'genero' => ['default' => ''],
            'nome_pai' => ['default' => ''],
            'profissao_pai' => ['default' => ''],
            'telemovel_pai' => ['default' => ''],
            'telemovel_pai_2' => ['default' => ''],
            'email_pai' => ['default' => ''],
            'nome_mae' => ['default' => ''],
            'profissao_mae' => ['default' => ''],
            'telemovel_mae' => ['default' => ''],
            'telemovel_mae_2' => ['default' => ''],
            'email_mae' => ['default' => ''],
            'contacto_encarregado' => ['default' => ''],
            'whatsapp_notificacoes' => ['default' => ''],
            'encarregado_principal_tipo' => ['default' => 'pai_mae'],
            'encarregado_principal_nome' => ['default' => ''],
            'encarregado_principal_telemovel' => ['default' => ''],
            'canal_preferencial_comunicacao' => ['default' => 'whatsapp'],
            'autorizado_buscar_nome' => ['default' => ''],
            'autorizado_buscar_documento' => ['default' => ''],
            'tipo_documento' => ['default' => ''],
            'documento_nr' => ['default' => ''],
            'documento_numero' => ['default' => ''],
            'doc_bi_url' => ['default' => ''],
            'doc_cert_url' => ['default' => ''],
            'bairro' => ['default' => ''],
            'nacionalidade' => ['default' => ''],
            'contacto_emergencia_1' => ['default' => ''],
            'contacto_emergencia_2' => ['default' => ''],
            'tem_desconto_irmao' => ['default' => 0],
            'tem_desconto_funcionario' => ['default' => 0],
            'alergias' => ['default' => ''],
            'condicoes_medicas' => ['default' => ''],
            'grupo_sanguineo' => ['default' => ''],
            'rota_transporte_id' => ['default' => 0],
        ];
    }
}

if (!function_exists('sige_alunos_perf_excel_fields')) {
    function sige_alunos_perf_excel_fields(): array {
        return [
            'id' => ['default' => 0],
            'numero_processo' => ['default' => ''],
            'nome_completo' => ['default' => ''],
            'genero' => ['default' => ''],
            'data_nascimento' => ['default' => ''],
            'nome_pai' => ['default' => ''],
            'telemovel_pai' => ['default' => ''],
            'nome_mae' => ['default' => ''],
            'telemovel_mae' => ['default' => ''],
            'bairro' => ['default' => ''],
            'status' => ['default' => 'activo'],
        ];
    }
}

if (!function_exists('sige_alunos_perf_cards_fields')) {
    function sige_alunos_perf_cards_fields(): array {
        return [
            'id' => ['default' => 0],
            'nome_completo' => ['default' => ''],
            'numero_processo' => ['default' => ''],
            'foto' => ['default' => ''],
            'status' => ['default' => 'activo'],
        ];
    }
}

if (!function_exists('sige_alunos_perf_list_select_sql')) {
    function sige_alunos_perf_list_select_sql(string $table_name, string $table_alias = 'a'): string {
        return sige_alunos_perf_select_sql($table_name, $table_alias, sige_alunos_perf_list_fields());
    }
}

if (!function_exists('sige_alunos_perf_export_select_sql')) {
    function sige_alunos_perf_export_select_sql(string $table_name, string $table_alias = 'a', string $purpose = 'excel'): string {
        $purpose = sanitize_key($purpose);
        $fields = ($purpose === 'cards') ? sige_alunos_perf_cards_fields() : sige_alunos_perf_excel_fields();
        return sige_alunos_perf_select_sql($table_name, $table_alias, $fields);
    }
}

if (!function_exists('sige_alunos_perf_object_value')) {
    function sige_alunos_perf_object_value($source, string $key, $default = '') {
        if (is_object($source) && isset($source->{$key})) return $source->{$key};
        if (is_array($source) && array_key_exists($key, $source)) return $source[$key];
        return $default;
    }
}

if (!function_exists('sige_alunos_perf_card_document_payload')) {
    /**
     * Payload seguro para botoes de documento/cartao no card.
     * Evita embutir a linha inteira do aluno no HTML da lista.
     */
    function sige_alunos_perf_card_document_payload($aluno): array {
        $keys = [
            'id', 'nome_completo', 'numero_processo', 'foto', 'status', 'data_nascimento', 'genero',
            'classe', 'turma_nome', 'turma_id', 'nacionalidade', 'documento_nr', 'documento_numero',
            'bairro', 'nome_pai', 'profissao_pai', 'telemovel_pai', 'telemovel_pai_2', 'email_pai',
            'nome_mae', 'profissao_mae', 'telemovel_mae', 'telemovel_mae_2', 'email_mae'
        ];
        $out = [];
        foreach ($keys as $key) {
            $value = sige_alunos_perf_object_value($aluno, $key, '');
            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }
        if (empty($out['documento_nr']) && !empty($out['documento_numero'])) {
            $out['documento_nr'] = $out['documento_numero'];
        }
        return $out;
    }
}

if (!function_exists('sige_alunos_perf_defer_script_tag')) {
    function sige_alunos_perf_defer_script_tag(string $key): string {
        if (!function_exists('sige_cdn_script')) return '';
        $tag = sige_cdn_script($key);
        if (stripos($tag, '<script ') === false || stripos($tag, ' defer') !== false) return $tag;
        return preg_replace('/<script\s+/i', '<script defer ', $tag, 1) ?: $tag;
    }
}
