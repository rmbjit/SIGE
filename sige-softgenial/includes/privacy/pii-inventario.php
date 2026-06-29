<?php
/**
 * Inventario de dados pessoais (PII) - Fase 8 incremento 1.
 *
 * So leitura. Confronta o catalogo declarado (pii-catalog.php) com o esquema vivo
 * da base de dados e produz:
 *  - cobertura: por tabela e campo, se o campo catalogado existe no esquema;
 *  - desvios: campos no catalogo que NAO existem no esquema (catalogo desactualizado);
 *  - lacunas: colunas com aspeto de PII que existem no esquema mas NAO estao no
 *    catalogo (classificacao em falta);
 *  - agregados por escola: apenas CONTAGENS por categoria (nunca linhas nem PII
 *    individual), fail-closed por escola.
 *
 * Nao escreve nada. Nao expoe valores de campos. As contagens sao agregados
 * estatisticos de governanca.
 */

if (!defined('ABSPATH') && !defined('SIGE_PRIVACY_TEST_MODE')) {
    exit;
}

if (!function_exists('sige_privacidade_pode_aceder')) {
    /** Pode aceder ao inventario de dados pessoais. */
    function sige_privacidade_pode_aceder(): bool {
        if (function_exists('sige_can') && sige_can('privacidade.inventario_ver')) {
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

if (!function_exists('sige_pii_tabela_existe')) {
    /** Verifica, so leitura, se a tabela (ja com prefixo) existe. */
    function sige_pii_tabela_existe(string $tabela_prefixada): bool {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabela_prefixada));
        return $found === $tabela_prefixada;
    }
}

if (!function_exists('sige_pii_schema_colunas')) {
    /**
     * Colunas existentes de uma tabela (sem prefixo). So leitura.
     * @return array<int,string> nomes de coluna; vazio se a tabela nao existir.
     */
    function sige_pii_schema_colunas(string $tabela_sem_prefixo): array {
        global $wpdb;
        $tab = $wpdb->prefix . $tabela_sem_prefixo;
        if (!sige_pii_tabela_existe($tab)) {
            return [];
        }
        $rows = $wpdb->get_results('SHOW COLUMNS FROM `' . str_replace('`', '', $tab) . '`');
        $cols = [];
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (isset($r->Field)) {
                    $cols[] = (string) $r->Field;
                }
            }
        }
        return $cols;
    }
}

if (!function_exists('sige_pii_coluna_parece_pii')) {
    /**
     * Heuristica: o nome da coluna sugere dado pessoal? Usado para detectar
     * lacunas (colunas PII fora do catalogo). Conservador: exclui colunas
     * tecnicas obvias (ids, datas de sistema, flags).
     */
    function sige_pii_coluna_parece_pii(string $coluna): bool {
        $c = strtolower($coluna);
        $tecnicas = [
            'id', 'escola_id', 'aluno_id', 'turma_id', 'user_id', 'criado_em', 'created_at',
            'updated_at', 'actualizado_em', 'data_criacao', 'data_registo', 'status', 'ativo',
            'activo', 'ordem', 'versao', 'lancamento_id', 'pagamento_id', 'porteiro_id',
        ];
        if (in_array($c, $tecnicas, true)) {
            return false;
        }
        $pistas = [
            'nome', 'apelido', 'email', 'telefone', 'telemovel', 'msisdn', 'contacto',
            'nuit', 'documento', 'bi_', 'morada', 'endereco', 'bairro', 'naturalidade',
            'nacionalidade', 'profissao', 'foto', 'fotografia', 'observ', 'nota', 'notas',
            'whatsapp', 'parentesco', 'encarregado', 'agregado', 'bancarios', 'salario',
            'subsidio', 'ip_', 'user_agent', 'user_display', 'autorizado', 'desc_ocorrencia',
            'medicamento', 'obs_', 'recado', 'payload',
        ];
        foreach ($pistas as $p) {
            if (strpos($c, $p) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sige_pii_cobertura')) {
    /**
     * Cobertura do catalogo vs esquema vivo: por tabela, marca cada campo
     * catalogado como presente ou ausente, e lista desvios e lacunas.
     * Esta parte e metadados de esquema (nao depende de escola).
     */
    function sige_pii_cobertura(): array {
        $por_tabela = sige_pii_catalogo_por_tabela();
        $cobertura = [];
        $desvios = [];
        $lacunas = [];

        foreach ($por_tabela as $tabela => $entradas) {
            $cols_existentes = sige_pii_schema_colunas($tabela);
            $tabela_existe = !empty($cols_existentes);
            $campos = [];
            foreach ($entradas as $entry) {
                $presente = $tabela_existe && in_array($entry['coluna'], $cols_existentes, true);
                $campos[] = [
                    'coluna'        => $entry['coluna'],
                    'categoria'     => $entry['categoria'],
                    'sensibilidade' => $entry['sensibilidade'],
                    'finalidade'    => $entry['finalidade'],
                    'base_legal'    => $entry['base_legal'],
                    'nota'          => $entry['nota'],
                    'presente'      => $presente,
                ];
                if ($tabela_existe && !$presente) {
                    $desvios[] = ['tabela' => $tabela, 'coluna' => $entry['coluna']];
                }
            }
            // Lacunas: colunas com aspeto de PII no esquema, fora do catalogo.
            if ($tabela_existe) {
                $catalogadas = array_map(static function ($e) { return $e['coluna']; }, $entradas);
                foreach ($cols_existentes as $col) {
                    if (!in_array($col, $catalogadas, true) && sige_pii_coluna_parece_pii($col)) {
                        $lacunas[] = ['tabela' => $tabela, 'coluna' => $col];
                    }
                }
            }
            $cobertura[] = [
                'tabela'        => $tabela,
                'tabela_existe' => $tabela_existe,
                'campos'        => $campos,
            ];
        }

        return ['cobertura' => $cobertura, 'desvios' => $desvios, 'lacunas' => $lacunas];
    }
}

if (!function_exists('sige_pii_contar')) {
    /**
     * Contagem segura por escola. Devolve 0 se a tabela nao existir ou a escola
     * for invalida. Apenas COUNT/SUM; nunca devolve linhas.
     *
     * @param string $tabela_sem_prefixo tabela
     * @param string $expr expressao de contagem (ex.: 'COUNT(*)', 'COUNT(DISTINCT aluno_id)', 'SUM(consent_whatsapp)')
     * @param string $where_extra condicao adicional segura (sem dados do utilizador), opcional
     */
    function sige_pii_contar(string $tabela_sem_prefixo, string $expr, int $escola_id, string $where_extra = ''): int {
        global $wpdb;
        if ($escola_id <= 0) {
            return 0;
        }
        $tab = $wpdb->prefix . $tabela_sem_prefixo;
        if (!sige_pii_tabela_existe($tab)) {
            return 0;
        }
        $sql = "SELECT {$expr} FROM `" . str_replace('`', '', $tab) . "` WHERE escola_id = %d";
        if ($where_extra !== '') {
            $sql .= ' AND ' . $where_extra;
        }
        $val = $wpdb->get_var($wpdb->prepare($sql, $escola_id));
        return (int) $val;
    }
}

if (!function_exists('sige_pii_agregados')) {
    /**
     * Agregados estatisticos por escola, por categoria. Apenas contagens.
     * Fail-closed: escola invalida devolve estrutura vazia.
     */
    function sige_pii_agregados(int $escola_id): array {
        if ($escola_id <= 0) {
            return [];
        }
        return [
            'titulares_alunos'        => sige_pii_contar('sige_alunos', 'COUNT(*)', $escola_id),
            'alunos_com_contacto'     => sige_pii_contar('sige_alunos', 'COUNT(*)', $escola_id, "(contacto_encarregado <> '' OR encarregado_principal_telemovel <> '')"),
            'titulares_funcionarios'  => sige_pii_contar('sige_professores', 'COUNT(*)', $escola_id),
            'alunos_com_saude'        => sige_pii_contar('sige_jardim_saude', 'COUNT(DISTINCT aluno_id)', $escola_id),
            'pagamentos'              => sige_pii_contar('sige_fin_pagamentos', 'COUNT(*)', $escola_id),
            'transacoes_moveis'       => sige_pii_contar('sige_mpesa_transacoes', 'COUNT(*)', $escola_id),
            'registos_acesso'         => sige_pii_contar('sige_acessos', 'COUNT(*)', $escola_id),
            'consent_whatsapp'        => sige_pii_contar('sige_alunos', 'SUM(consent_whatsapp)', $escola_id),
            'consent_email'           => sige_pii_contar('sige_alunos', 'SUM(consent_email)', $escola_id),
            'consent_sms'             => sige_pii_contar('sige_alunos', 'SUM(consent_sms)', $escola_id),
            'consent_chamada'         => sige_pii_contar('sige_alunos', 'SUM(consent_chamada)', $escola_id),
        ];
    }
}

if (!function_exists('sige_pii_inventario')) {
    /**
     * Inventario completo de PII para uma escola. So leitura.
     * Junta cobertura/desvios/lacunas (metadados de esquema) e agregados por
     * escola (contagens). Fail-closed: escola invalida zera os agregados.
     */
    function sige_pii_inventario(int $escola_id): array {
        $cob = sige_pii_cobertura();
        $catalogo = sige_pii_catalogo();
        $categorias = sige_pii_categorias();

        $total_campos = count($catalogo);
        $campos_sensiveis = 0;
        $tabelas = [];
        foreach ($catalogo as $entry) {
            if ($entry['sensibilidade'] === 'sensivel') {
                $campos_sensiveis++;
            }
            $tabelas[$entry['tabela']] = true;
        }
        $campos_presentes = 0;
        $tabelas_presentes = [];
        foreach ($cob['cobertura'] as $t) {
            if (!empty($t['tabela_existe'])) {
                $tabelas_presentes[$t['tabela']] = true;
            }
            foreach ($t['campos'] as $c) {
                if (!empty($c['presente'])) {
                    $campos_presentes++;
                }
            }
        }

        return [
            'escola_id'   => $escola_id,
            'escola_valida' => $escola_id > 0,
            'categorias'  => $categorias,
            'cobertura'   => $cob['cobertura'],
            'desvios'     => $cob['desvios'],
            'lacunas'     => $cob['lacunas'],
            'agregados'   => sige_pii_agregados($escola_id),
            'resumo'      => [
                'total_campos'       => $total_campos,
                'campos_presentes'   => $campos_presentes,
                'campos_sensiveis'   => $campos_sensiveis,
                'tabelas_catalogadas' => count($tabelas),
                'tabelas_presentes'  => count($tabelas_presentes),
                'desvios'            => count($cob['desvios']),
                'lacunas'            => count($cob['lacunas']),
            ],
        ];
    }
}
