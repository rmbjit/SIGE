<?php
/**
 * Apagamento por anonimizacao (direito ao apagamento) - Fase 8 incremento 3.
 *
 * Operacao destrutiva e irreversivel. Em vez de eliminar linhas (o que partiria a
 * integridade financeira, academica e de auditoria, e o dever de retencao), redige
 * os campos pessoais identificaveis de um aluno, substituindo-os por marcadores
 * (texto) ou por nulos (datas e numeros anulaveis), e preserva intactos os
 * identificadores pseudonimos (numero de processo, aluno_id), os carimbos
 * estruturais e todos os valores financeiros e academicos.
 *
 * Regras de seguranca:
 *  - so toca colunas PII catalogadas (Incr 1), nunca estruturais ou financeiras;
 *  - nunca toca a lista de preservacao (numero_processo, aluno_id, data_hora);
 *  - a redaccao e segura quanto ao tipo, lido em runtime via SHOW COLUMNS;
 *  - fail-closed por escola: aluno de outra escola e recusado;
 *  - idempotente: reanonimizar e inocuo.
 *
 * A montagem do plano (pre-visualizacao) e a deteccao de "ja anonimizado" sao
 * separadas para serem testaveis sem o WordPress.
 */

if (!defined('ABSPATH') && !defined('SIGE_PRIVACY_TEST_MODE')) {
    exit;
}

if (!function_exists('sige_pii_apagamento_marcador')) {
    /** Marcador de redaccao para campos de texto. */
    function sige_pii_apagamento_marcador(): string {
        return '[apagado]';
    }
}

if (!function_exists('sige_pii_apagamento_preservar')) {
    /** Colunas catalogadas como PII que NUNCA sao redigidas (pseudonimos, ligacao, estrutura, registo operacional). */
    function sige_pii_apagamento_preservar(): array {
        return ['numero_processo', 'aluno_id', 'data_hora', 'data_contacto', 'proximo_contacto'];
    }
}

if (!function_exists('sige_pii_apagamento_pode_executar')) {
    /** Pode executar o apagamento por anonimizacao. */
    function sige_pii_apagamento_pode_executar(): bool {
        if (function_exists('sige_can') && sige_can('privacidade.apagamento_executar')) {
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

if (!function_exists('sige_pii_apagamento_classificar')) {
    /** Classe de redaccao a partir do tipo SQL. */
    function sige_pii_apagamento_classificar(string $type): string {
        $t = strtolower(trim($type));
        if (preg_match('/^(enum|set)\b/', $t)) return 'enum';
        if (preg_match('/^(char|varchar|tinytext|text|mediumtext|longtext)\b/', $t)) return 'texto';
        if (preg_match('/^(date|datetime|timestamp|time|year)\b/', $t)) return 'data';
        if (preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|bit)\b/', $t)) return 'numero';
        return 'texto'; // conservador: trata o desconhecido como texto (marcador)
    }
}

if (!function_exists('sige_pii_apagamento_maxlen')) {
    /** Comprimento maximo de um char/varchar, ou null se nao aplicavel. */
    function sige_pii_apagamento_maxlen(string $type): ?int {
        if (preg_match('/^(?:var)?char\s*\(\s*(\d+)\s*\)/i', trim($type), $m)) {
            return (int) $m[1];
        }
        return null;
    }
}

if (!function_exists('sige_pii_apagamento_sentinela_data')) {
    /** Valor neutro para datas nao anulaveis, por subtipo. */
    function sige_pii_apagamento_sentinela_data(string $type): string {
        $t = strtolower(trim($type));
        if (strpos($t, 'datetime') === 0 || strpos($t, 'timestamp') === 0) return '1900-01-01 00:00:00';
        if (strpos($t, 'time') === 0) return '00:00:00';
        if (strpos($t, 'year') === 0) return '1900';
        return '1900-01-01';
    }
}

if (!function_exists('sige_pii_apagamento_resolver')) {
    /**
     * Decide a redaccao de uma coluna a partir do seu meta de esquema.
     * @param array $meta ['coluna'=>, 'type'=>, 'nullable'=>bool, 'maxlen'=>?int]
     * @return array ['accao'=>'marcador|anular|sentinela|zero|manter', 'valor'=>mixed, 'fmt'=>'%s|%d|null', 'rotulo'=>string]
     */
    function sige_pii_apagamento_resolver(array $meta): array {
        $classe = sige_pii_apagamento_classificar((string) $meta['type']);
        if ($classe === 'enum') {
            return ['accao' => 'manter', 'valor' => null, 'fmt' => null, 'rotulo' => 'mantido (tipo enumerado)'];
        }
        if ($classe === 'texto') {
            $val = sige_pii_apagamento_marcador();
            $maxlen = $meta['maxlen'] ?? null;
            if ($maxlen !== null && $maxlen < strlen($val)) {
                $val = substr($val, 0, max(1, (int) $maxlen));
            }
            return ['accao' => 'marcador', 'valor' => $val, 'fmt' => '%s', 'rotulo' => 'marcador'];
        }
        if ($classe === 'data') {
            if (!empty($meta['nullable'])) {
                return ['accao' => 'anular', 'valor' => null, 'fmt' => null, 'rotulo' => 'anulado'];
            }
            return ['accao' => 'sentinela', 'valor' => sige_pii_apagamento_sentinela_data((string) $meta['type']), 'fmt' => '%s', 'rotulo' => 'data neutra'];
        }
        // numero
        if (!empty($meta['nullable'])) {
            return ['accao' => 'anular', 'valor' => null, 'fmt' => null, 'rotulo' => 'anulado'];
        }
        return ['accao' => 'zero', 'valor' => 0, 'fmt' => '%d', 'rotulo' => 'zero'];
    }
}

if (!function_exists('sige_pii_apagamento_colunas_meta')) {
    /** Meta de esquema (tipo, anulabilidade, comprimento) das colunas de uma tabela. So leitura. */
    function sige_pii_apagamento_colunas_meta(string $tabela): array {
        global $wpdb;
        $tab = str_replace('`', '', $wpdb->prefix . $tabela);
        if (function_exists('sige_pii_tabela_existe') && !sige_pii_tabela_existe($wpdb->prefix . $tabela)) {
            return [];
        }
        $rows = $wpdb->get_results('SHOW COLUMNS FROM `' . $tab . '`');
        $meta = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (!isset($r->Field)) continue;
                $meta[(string) $r->Field] = [
                    'coluna'   => (string) $r->Field,
                    'type'     => (string) ($r->Type ?? ''),
                    'nullable' => isset($r->Null) ? (strtoupper((string) $r->Null) === 'YES') : true,
                    'maxlen'   => sige_pii_apagamento_maxlen((string) ($r->Type ?? '')),
                ];
            }
        }
        return $meta;
    }
}

if (!function_exists('sige_pii_apagamento_alvos_tabela')) {
    /**
     * Colunas PII a redigir numa tabela (catalogadas e existentes, menos a lista de
     * preservacao), com o seu meta e a redaccao resolvida. Tambem devolve a coluna
     * de ligacao ao aluno, ou null se a tabela nao se liga ao aluno individual.
     */
    function sige_pii_apagamento_alvos_tabela(string $tabela, array $entradas_catalogo): array {
        $meta = sige_pii_apagamento_colunas_meta($tabela);
        if (empty($meta)) {
            return ['where_col' => null, 'alvos' => [], 'preservadas' => []];
        }
        $preservar = sige_pii_apagamento_preservar();

        // Ligacao ao aluno.
        if ($tabela === 'sige_alunos') {
            $where_col = 'id';
        } elseif (isset($meta['aluno_id'])) {
            $where_col = 'aluno_id';
        } else {
            return ['where_col' => null, 'alvos' => [], 'preservadas' => []];
        }

        $alvos = [];
        $preservadas = [];
        foreach ($entradas_catalogo as $e) {
            $col = $e['coluna'];
            if (!isset($meta[$col])) continue;          // nao existe no esquema vivo
            if (in_array($col, $preservar, true)) {       // pseudonimo/ligacao/estrutura
                $preservadas[] = $col;
                continue;
            }
            $resolucao = sige_pii_apagamento_resolver($meta[$col]);
            $alvos[$col] = [
                'coluna'    => $col,
                'categoria' => $e['categoria'],
                'sensibilidade' => $e['sensibilidade'],
                'tipo'      => $meta[$col]['type'],
                'accao'     => $resolucao['accao'],
                'valor'     => $resolucao['valor'],
                'fmt'       => $resolucao['fmt'],
                'rotulo'    => $resolucao['rotulo'],
            ];
        }
        return ['where_col' => $where_col, 'alvos' => $alvos, 'preservadas' => $preservadas];
    }
}

if (!function_exists('sige_pii_apagamento_plano')) {
    /**
     * Pre-visualizacao de impacto. So leitura. Lista, por tabela ligada ao aluno,
     * as colunas a redigir (accao e valor previsto) e as preservadas. Fail-closed.
     */
    function sige_pii_apagamento_plano(int $aluno_id, int $escola_id): array {
        if ($aluno_id <= 0 || $escola_id <= 0) {
            return ['ok' => false, 'motivo' => 'parametros invalidos', 'aluno_id' => $aluno_id, 'escola_id' => $escola_id, 'seccoes' => []];
        }
        if (!function_exists('sige_pii_dossier_aluno_pertence') || !sige_pii_dossier_aluno_pertence($aluno_id, $escola_id)) {
            return ['ok' => false, 'motivo' => 'aluno fora da escola activa', 'aluno_id' => $aluno_id, 'escola_id' => $escola_id, 'seccoes' => []];
        }

        $por_tabela = sige_pii_catalogo_por_tabela();
        $seccoes = [];
        $total_colunas = 0;
        foreach ($por_tabela as $tabela => $entradas) {
            $info = sige_pii_apagamento_alvos_tabela($tabela, $entradas);
            if ($info['where_col'] === null || empty($info['alvos'])) {
                continue;
            }
            $total_colunas += count($info['alvos']);
            $seccoes[] = [
                'tabela'      => $tabela,
                'where_col'   => $info['where_col'],
                'alvos'       => array_values($info['alvos']),
                'preservadas' => $info['preservadas'],
            ];
        }

        return [
            'ok'             => true,
            'aluno_id'       => $aluno_id,
            'escola_id'      => $escola_id,
            'identificacao'  => function_exists('sige_pii_dossier_identificacao') ? sige_pii_dossier_identificacao($aluno_id, $escola_id) : [],
            'ja_anonimizado' => sige_pii_apagamento_esta_anonimizado($aluno_id, $escola_id),
            'seccoes'        => $seccoes,
            'total_seccoes'  => count($seccoes),
            'total_colunas'  => $total_colunas,
            'preservado'     => 'Numero de processo, aluno_id, carimbos estruturais e todos os valores financeiros e academicos sao preservados.',
        ];
    }
}

if (!function_exists('sige_pii_apagamento_esta_anonimizado')) {
    /** Deteta um aluno ja anonimizado pelo marcador no nome. So leitura. */
    function sige_pii_apagamento_esta_anonimizado(int $aluno_id, int $escola_id): bool {
        global $wpdb;
        if ($aluno_id <= 0 || $escola_id <= 0) return false;
        $tab = str_replace('`', '', $wpdb->prefix . 'sige_alunos');
        $nome = $wpdb->get_var($wpdb->prepare(
            "SELECT nome_completo FROM `{$tab}` WHERE id = %d AND escola_id = %d LIMIT 1",
            $aluno_id, $escola_id
        ));
        return $nome !== null && (string) $nome === sige_pii_apagamento_marcador();
    }
}

if (!function_exists('sige_pii_apagamento_executar')) {
    /**
     * Executa a anonimizacao. Operacao destrutiva e irreversivel. Idempotente.
     * Fail-closed por escola. Devolve detalhe por tabela (colunas redigidas e
     * linhas afectadas). NAO regista auditoria (cabe ao handler, com o utilizador).
     */
    function sige_pii_apagamento_executar(int $aluno_id, int $escola_id): array {
        global $wpdb;
        if ($aluno_id <= 0 || $escola_id <= 0) {
            return ['ok' => false, 'motivo' => 'parametros invalidos', 'tabelas' => []];
        }
        if (!function_exists('sige_pii_dossier_aluno_pertence') || !sige_pii_dossier_aluno_pertence($aluno_id, $escola_id)) {
            return ['ok' => false, 'motivo' => 'aluno fora da escola activa', 'tabelas' => []];
        }

        $por_tabela = sige_pii_catalogo_por_tabela();
        $resultado = [];
        $total_colunas = 0;
        $total_linhas = 0;

        foreach ($por_tabela as $tabela => $entradas) {
            $info = sige_pii_apagamento_alvos_tabela($tabela, $entradas);
            if ($info['where_col'] === null || empty($info['alvos'])) {
                continue;
            }
            $sets = [];
            $args = [];
            $redigidas = [];
            foreach ($info['alvos'] as $col => $alvo) {
                if (!preg_match('/^[a-z0-9_]+$/', $col)) continue;
                if ($alvo['accao'] === 'manter') continue;
                if ($alvo['accao'] === 'anular') {
                    $sets[] = "`{$col}` = NULL";
                    $redigidas[] = $col;
                    continue;
                }
                $sets[] = "`{$col}` = {$alvo['fmt']}";
                $args[] = $alvo['valor'];
                $redigidas[] = $col;
            }
            if (empty($sets)) {
                continue;
            }
            $where_col = preg_match('/^[a-z0-9_]+$/', $info['where_col']) ? $info['where_col'] : 'id';
            $tab = str_replace('`', '', $wpdb->prefix . $tabela);
            $sql = "UPDATE `{$tab}` SET " . implode(', ', $sets) . " WHERE `{$where_col}` = %d AND escola_id = %d";
            $args[] = $aluno_id;
            $args[] = $escola_id;
            $linhas = $wpdb->query($wpdb->prepare($sql, $args));
            $linhas = is_numeric($linhas) ? (int) $linhas : 0;
            $total_colunas += count($redigidas);
            $total_linhas += $linhas;
            $resultado[] = [
                'tabela'           => $tabela,
                'colunas_redigidas' => $redigidas,
                'linhas_afectadas' => $linhas,
            ];
        }

        return [
            'ok'            => true,
            'aluno_id'      => $aluno_id,
            'escola_id'     => $escola_id,
            'tabelas'       => $resultado,
            'total_tabelas' => count($resultado),
            'total_colunas' => $total_colunas,
            'total_linhas'  => $total_linhas,
            'executado_em'  => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ];
    }
}
