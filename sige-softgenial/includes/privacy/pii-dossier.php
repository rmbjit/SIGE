<?php
/**
 * Dossie de dados pessoais do aluno (direito de acesso e portabilidade) - Fase 8 incr 2.
 *
 * So leitura. Dado um aluno da escola activa, reune os valores reais dos seus
 * dados pessoais a partir do catalogo declarado (pii-catalog.php), agrupados por
 * tabela e categoria. Fail-closed: aluno fora da escola activa, ou escola
 * invalida, devolve recusa sem qualquer dado.
 *
 * Nao escreve nada. A montagem do JSON de portabilidade esta separada para ser
 * testavel sem o WordPress.
 */

if (!defined('ABSPATH') && !defined('SIGE_PRIVACY_TEST_MODE')) {
    exit;
}

if (!function_exists('sige_pii_dossier_pode_exportar')) {
    /** Pode aceder ao dossie e exporta-lo. */
    function sige_pii_dossier_pode_exportar(): bool {
        if (function_exists('sige_can') && sige_can('privacidade.acesso_exportar')) {
            return true;
        }
        if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) {
            return true;
        }
        if (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user()) {
            return true;
        }
        return false;
    }
}

if (!function_exists('sige_pii_dossier_aluno_pertence')) {
    /** Confirma, so leitura, que o aluno pertence a escola activa. Fail-closed. */
    function sige_pii_dossier_aluno_pertence(int $aluno_id, int $escola_id): bool {
        global $wpdb;
        if ($aluno_id <= 0 || $escola_id <= 0) {
            return false;
        }
        $tab = $wpdb->prefix . 'sige_alunos';
        if (function_exists('sige_pii_tabela_existe') && !sige_pii_tabela_existe($tab)) {
            return false;
        }
        $found = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `" . str_replace('`', '', $tab) . "` WHERE id = %d AND escola_id = %d LIMIT 1",
            $aluno_id, $escola_id
        ));
        return $found === $aluno_id;
    }
}

if (!function_exists('sige_pii_dossier_identificacao')) {
    /**
     * Nome e numero de processo do aluno, para rotular o dossie. So leitura e
     * sempre dentro da escola. Devolve vazio se nao pertencer.
     */
    function sige_pii_dossier_identificacao(int $aluno_id, int $escola_id): array {
        global $wpdb;
        if (!sige_pii_dossier_aluno_pertence($aluno_id, $escola_id)) {
            return [];
        }
        $tab = $wpdb->prefix . 'sige_alunos';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT nome_completo, numero_processo FROM `" . str_replace('`', '', $tab) . "` WHERE id = %d AND escola_id = %d LIMIT 1",
            $aluno_id, $escola_id
        ), ARRAY_A);
        return is_array($row) ? $row : [];
    }
}

if (!function_exists('sige_pii_dossier_linhas')) {
    /**
     * Linhas de uma tabela ligadas ao aluno, apenas com as colunas PII catalogadas
     * que existem no esquema. So leitura, sempre filtrado por escola. Os nomes de
     * coluna sao validados contra uma lista branca estrita antes de entrar no SQL.
     *
     * @param array<int,string> $cols colunas catalogadas (ja intersectadas com o esquema)
     */
    function sige_pii_dossier_linhas(string $tabela, array $cols, string $where_col, int $aluno_id, int $escola_id, int $limite = 500): array {
        global $wpdb;
        if ($aluno_id <= 0 || $escola_id <= 0) {
            return [];
        }
        $safe_cols = array_values(array_filter($cols, static function ($c) { return (bool) preg_match('/^[a-z0-9_]+$/', $c); }));
        if (empty($safe_cols)) {
            return [];
        }
        $where_col_safe = preg_match('/^[a-z0-9_]+$/', $where_col) ? $where_col : 'id';
        $tab = str_replace('`', '', $wpdb->prefix . $tabela);
        $col_sql = implode(', ', array_map(static function ($c) { return '`' . $c . '`'; }, $safe_cols));
        $sql = "SELECT {$col_sql} FROM `{$tab}` WHERE `{$where_col_safe}` = %d AND escola_id = %d ORDER BY id DESC LIMIT %d";
        $res = $wpdb->get_results($wpdb->prepare($sql, $aluno_id, $escola_id, $limite), ARRAY_A);
        return is_array($res) ? $res : [];
    }
}

if (!function_exists('sige_pii_dossier')) {
    /**
     * Dossie completo do aluno. So leitura. Devolve estrutura com seccoes por
     * tabela, cada uma com as colunas PII e as suas linhas (valores reais).
     * Fail-closed: aluno fora da escola devolve ok=false sem dados.
     */
    function sige_pii_dossier(int $aluno_id, int $escola_id): array {
        if ($aluno_id <= 0 || $escola_id <= 0) {
            return ['ok' => false, 'motivo' => 'parametros invalidos', 'aluno_id' => $aluno_id, 'escola_id' => $escola_id, 'seccoes' => []];
        }
        if (!sige_pii_dossier_aluno_pertence($aluno_id, $escola_id)) {
            return ['ok' => false, 'motivo' => 'aluno fora da escola activa', 'aluno_id' => $aluno_id, 'escola_id' => $escola_id, 'seccoes' => []];
        }

        $por_tabela = sige_pii_catalogo_por_tabela();
        $seccoes = [];
        $total_registos = 0;

        foreach ($por_tabela as $tabela => $entradas) {
            $cols_existentes = function_exists('sige_pii_schema_colunas') ? sige_pii_schema_colunas($tabela) : [];
            if (empty($cols_existentes)) {
                continue;
            }
            $cols = [];
            $col_categoria = [];
            $col_sensibilidade = [];
            foreach ($entradas as $e) {
                if (in_array($e['coluna'], $cols_existentes, true)) {
                    $cols[] = $e['coluna'];
                    $col_categoria[$e['coluna']] = $e['categoria'];
                    $col_sensibilidade[$e['coluna']] = $e['sensibilidade'];
                }
            }
            if (empty($cols)) {
                continue;
            }

            // Coluna de ligacao ao aluno.
            if ($tabela === 'sige_alunos') {
                $where_col = 'id';
            } elseif (in_array('aluno_id', $cols_existentes, true)) {
                $where_col = 'aluno_id';
            } else {
                // Tabela sem ligacao directa ao aluno (ex.: agregado): fora do dossie individual.
                continue;
            }

            $linhas = sige_pii_dossier_linhas($tabela, $cols, $where_col, $aluno_id, $escola_id);
            if (empty($linhas)) {
                continue;
            }
            $total_registos += count($linhas);
            $seccoes[] = [
                'tabela'           => $tabela,
                'colunas'          => $cols,
                'col_categoria'    => $col_categoria,
                'col_sensibilidade' => $col_sensibilidade,
                'linhas'           => $linhas,
            ];
        }

        return [
            'ok'              => true,
            'aluno_id'        => $aluno_id,
            'escola_id'       => $escola_id,
            'identificacao'   => sige_pii_dossier_identificacao($aluno_id, $escola_id),
            'seccoes'         => $seccoes,
            'total_seccoes'   => count($seccoes),
            'total_registos'  => $total_registos,
            'gerado_em'       => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('sige_pii_dossier_json')) {
    /**
     * Serializa o dossie em JSON estruturado e legivel (portabilidade).
     * Funcao pura: nao acede a base nem ao WordPress.
     */
    function sige_pii_dossier_json(array $dossie): string {
        $doc = [
            'documento'   => 'Dossie de dados pessoais do titular',
            'sistema'     => 'SIGE SoftGenial',
            'gerado_em'   => $dossie['gerado_em'] ?? '',
            'aluno'       => [
                'id'              => $dossie['aluno_id'] ?? 0,
                'nome_completo'   => $dossie['identificacao']['nome_completo'] ?? '',
                'numero_processo' => $dossie['identificacao']['numero_processo'] ?? '',
            ],
            'aviso'       => 'Dados pessoais exportados ao abrigo do direito de acesso e portabilidade. Tratar com confidencialidade.',
            'seccoes'     => [],
        ];
        foreach (($dossie['seccoes'] ?? []) as $sec) {
            $doc['seccoes'][] = [
                'tabela'   => $sec['tabela'],
                'campos'   => $sec['colunas'],
                'registos' => $sec['linhas'],
            ];
        }
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (function_exists('wp_json_encode')) {
            return (string) wp_json_encode($doc, $flags);
        }
        return (string) json_encode($doc, $flags);
    }
}
