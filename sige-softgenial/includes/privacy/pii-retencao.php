<?php
/**
 * Retencao e expurgo (Fase 8 incremento 4) - so leitura.
 *
 * Entrega a retencao como POLITICA e VISIBILIDADE. Um calendario de retencao
 * declarado mapeia cada categoria/tabela com dados pessoais a um prazo, a base
 * legal e a coluna de data usada para aferir a idade do registo. O painel conta,
 * por categoria, quantos registos ja excederam o prazo (candidatos a expurgo),
 * sempre de forma agregada e dentro da escola.
 *
 * Decisao de seguranca: neste sistema NAO ha expurgo por eliminacao em massa. As
 * presencas sao derivadas ao vivo do registo de acessos (apagar acessos antigos
 * apagaria o historico de assiduidade) e os registos financeiros, academicos e
 * de auditoria tem dever de retencao. Por isso o expurgo de um titular faz-se
 * pela anonimizacao ja existente (Incr 3), que preserva a integridade. Este
 * modulo nao elimina nada: so le e conta.
 *
 * O calendario e uma classificacao por omissao, a rever pela instituicao
 * enquanto responsavel pelo tratamento. Nao e parecer juridico.
 */

if (!defined('ABSPATH') && !defined('SIGE_PRIVACY_TEST_MODE')) {
    exit;
}

if (!function_exists('sige_pii_retencao_pode_ver')) {
    /** Pode ver o painel de retencao (so leitura). */
    function sige_pii_retencao_pode_ver(): bool {
        if (function_exists('sige_can') && sige_can('privacidade.retencao_ver')) {
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

if (!function_exists('sige_pii_retencao_modos')) {
    /** Modos de expurgo declarados (como se trata um registo apos o prazo). */
    function sige_pii_retencao_modos(): array {
        return [
            'anonimizacao'  => 'Expurgo por anonimizacao do titular (Apagamento), preservando a integridade financeira e academica.',
            'retido'        => 'Retido por dever legal ou por ser fonte de integridade; nao e expurgado.',
            'nao_aplicavel' => 'Fora do ambito do apagamento do aluno (dado de funcionario); tratado em incremento proprio.',
        ];
    }
}

if (!function_exists('sige_pii_retencao_calendario')) {
    /**
     * Calendario de retencao declarado. Cada entrada:
     *  tabela, categoria, prazo_meses, base_legal, coluna_data, modo, nota,
     *  so_inactivos (bool: contar apenas registos de alunos nao activos).
     */
    function sige_pii_retencao_calendario(): array {
        $e = static function (string $tabela, string $categoria, int $prazo_meses, string $base_legal, string $coluna_data, string $modo, string $nota = '', bool $so_inactivos = false): array {
            return [
                'tabela'       => $tabela,
                'categoria'    => $categoria,
                'prazo_meses'  => $prazo_meses,
                'base_legal'   => $base_legal,
                'coluna_data'  => $coluna_data,
                'modo'         => $modo,
                'nota'         => $nota,
                'so_inactivos' => $so_inactivos,
            ];
        };

        return [
            $e('sige_alunos', 'Ficha do aluno e do encarregado', 120, 'obrigacao_legal', 'data_registo', 'anonimizacao', 'Registo academico do aluno. Contagem apenas de alunos nao activos.', true),
            $e('sige_matriculas', 'Vinculo academico', 120, 'obrigacao_legal', 'data_matricula', 'anonimizacao', 'Matriculas por ano lectivo.'),
            $e('sige_jardim_saude', 'Dados de saude (jardim)', 60, 'interesse_legitimo', 'data_registo', 'anonimizacao', 'Dados sensiveis de saude da crianca.'),
            $e('sige_fin_pagamentos', 'Registo financeiro', 120, 'obrigacao_legal', 'data_pagamento', 'anonimizacao', 'Valores retidos por dever fiscal; apenas os campos pessoais sao anonimizaveis.'),
            $e('sige_fin_contactos_cobranca', 'Registo de cobranca', 60, 'interesse_legitimo', 'data_contacto', 'anonimizacao', 'As datas de cobranca sao preservadas; as notas sao anonimizaveis.'),
            $e('sige_agregados_familiares', 'Agregado familiar', 120, 'contrato_educativo', 'criado_em', 'anonimizacao', 'Partilhado entre irmaos; expurgo com cautela, por titular.'),
            $e('sige_mpesa_transacoes', 'Transacoes moveis', 120, 'obrigacao_legal', 'criado_em', 'anonimizacao', 'Retido por dever fiscal; campos pessoais anonimizaveis.'),
            $e('sige_alunos_encarregados_historico', 'Auditoria de alteracoes', 60, 'obrigacao_legal', 'criado_em', 'retido', 'Registo de auditoria; retido por dever legal.'),
            $e('sige_acessos', 'Registo de acessos (portaria)', 24, 'interesse_legitimo', 'data_hora', 'retido', 'Fonte das presencas (derivadas ao vivo); retido para preservar o historico de assiduidade.'),
            $e('sige_professores', 'Dados de funcionario', 120, 'obrigacao_legal', 'data_admissao', 'nao_aplicavel', 'Dado de funcionario; fora do ambito do apagamento do aluno.'),
        ];
    }
}

if (!function_exists('sige_pii_retencao_cutoff')) {
    /** Data de corte (agora menos o prazo em meses), no formato do MySQL. */
    function sige_pii_retencao_cutoff(int $prazo_meses): string {
        $agora = function_exists('current_time') ? current_time('timestamp') : time();
        $meses = max(0, $prazo_meses);
        return gmdate('Y-m-d H:i:s', strtotime("-{$meses} months", (int) $agora));
    }
}

if (!function_exists('sige_pii_retencao_contar_excedido')) {
    /**
     * Conta, dentro da escola, quantos registos de uma entrada ja excederam o
     * prazo de retencao. So leitura. Fail-closed por escola. Devolve null se nao
     * for possivel contar (tabela ou coluna em falta).
     */
    function sige_pii_retencao_contar_excedido(array $entrada, int $escola_id): ?int {
        global $wpdb;
        if ($escola_id <= 0) {
            return null;
        }
        $tabela = (string) ($entrada['tabela'] ?? '');
        $coluna = (string) ($entrada['coluna_data'] ?? '');
        if (!preg_match('/^[a-z0-9_]+$/', $tabela) || !preg_match('/^[a-z0-9_]+$/', $coluna)) {
            return null;
        }
        if (function_exists('sige_pii_tabela_existe') && !sige_pii_tabela_existe($wpdb->prefix . $tabela)) {
            return null;
        }
        $colunas = function_exists('sige_pii_schema_colunas') ? sige_pii_schema_colunas($tabela) : [];
        if (!in_array($coluna, $colunas, true)) {
            return null;
        }

        $tab = str_replace('`', '', $wpdb->prefix . $tabela);
        $cutoff = sige_pii_retencao_cutoff((int) ($entrada['prazo_meses'] ?? 0));

        $extra = '';
        if (!empty($entrada['so_inactivos']) && in_array('status', $colunas, true)) {
            $extra = " AND status <> 'activo'";
        }

        $sql = "SELECT COUNT(*) FROM `{$tab}` WHERE `{$coluna}` < %s AND escola_id = %d" . $extra;
        $total = $wpdb->get_var($wpdb->prepare($sql, $cutoff, $escola_id));
        return is_numeric($total) ? (int) $total : 0;
    }
}

if (!function_exists('sige_pii_retencao_panorama')) {
    /**
     * Panorama de retencao para a escola: para cada entrada do calendario, o
     * prazo, o modo de expurgo e a contagem de registos que ja o excederam.
     * So leitura, agregado, fail-closed por escola.
     */
    function sige_pii_retencao_panorama(int $escola_id): array {
        $linhas = [];
        $total_excedido = 0;
        $total_anonimizavel = 0;
        foreach (sige_pii_retencao_calendario() as $entrada) {
            $n = sige_pii_retencao_contar_excedido($entrada, $escola_id);
            $excedido = is_int($n) ? $n : 0;
            $total_excedido += $excedido;
            if (($entrada['modo'] ?? '') === 'anonimizacao') {
                $total_anonimizavel += $excedido;
            }
            $linhas[] = [
                'tabela'        => $entrada['tabela'],
                'categoria'     => $entrada['categoria'],
                'prazo_meses'   => $entrada['prazo_meses'],
                'prazo_legivel' => sige_pii_retencao_prazo_legivel((int) $entrada['prazo_meses']),
                'base_legal'    => $entrada['base_legal'],
                'coluna_data'   => $entrada['coluna_data'],
                'modo'          => $entrada['modo'],
                'nota'          => $entrada['nota'],
                'excedido'      => $excedido,
                'mensuravel'    => ($n !== null),
            ];
        }
        return [
            'ok'                 => ($escola_id > 0),
            'escola_id'          => $escola_id,
            'linhas'             => $linhas,
            'total_excedido'     => $total_excedido,
            'total_anonimizavel' => $total_anonimizavel,
        ];
    }
}

if (!function_exists('sige_pii_retencao_prazo_legivel')) {
    /** Apresenta o prazo em anos e meses. */
    function sige_pii_retencao_prazo_legivel(int $meses): string {
        $meses = max(0, $meses);
        $anos = intdiv($meses, 12);
        $resto = $meses % 12;
        $partes = [];
        if ($anos > 0) { $partes[] = $anos . ($anos === 1 ? ' ano' : ' anos'); }
        if ($resto > 0) { $partes[] = $resto . ($resto === 1 ? ' mes' : ' meses'); }
        if (empty($partes)) { return '0 meses'; }
        return implode(' e ', $partes);
    }
}
