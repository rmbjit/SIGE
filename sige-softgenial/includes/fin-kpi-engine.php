<?php
/**
 * SIGE SoftGenial - Motor Consolidado de KPIs Financeiros
 * Ficheiro: includes/fin-kpi-engine.php
 *
 * Camada única de cálculo para todos os KPIs financeiros do sistema.
 * Consolida lógica que estava duplicada entre dashboard, relatório
 * mensal, extratos e devedores - eliminando divergências entre ecrãs.
 *
 * Política de design (NÃO ALTERAR sem nova auditoria):
 *
 *   1. DELEGAÇÃO ESTRITA. Todas as funções delegam os cálculos a:
 *        - sige_fin_saldo_sql($alias)         - expressão SQL do saldo em dívida
 *        - sige_fin_saldo_lancamento($obj)    - versão PHP do saldo
 *        - sige_fin_total_bruto_sql($alias)   - total bruto (sem subtrair pago)
 *      Zero fórmulas novas. Se uma regra mudar, muda-se em finance-core.php
 *      e propaga-se automaticamente.
 *
 *   2. SEMÂNTICA FIXA dos estados (derivada do código em produção v13.2.1):
 *        - "pendente" = lançamentos com status IN ('pendente','parcial')
 *        - "atraso"   = pendente + data_vencimento < hoje
 *        - "em_plano" = status='em_plano' - SEM filtro de ano (planos
 *                       podem atravessar anos; contrato preservado)
 *        - "previsto" = status NOT IN ('cancelado','isento') no ano
 *        - despesa activa = LOWER(status) <> 'anulado' - consistente com
 *                           o resto do sistema desde BUG-CRIT-01 fix
 *
 *   3. JANELAS TEMPORAIS (derivadas de v13.2.1):
 *        - previsto / pendente / atraso → LEFT(mes_referencia,4) = ano
 *        - recebido                     → YEAR(data_pagamento)    = ano
 *        - despesa                      → YEAR(data_despesa)      = ano
 *      Um lançamento de Dez/2025 pago em Jan/2026 conta no "previsto 2025"
 *      e no "recebido 2026". Isto é por design.
 *
 *   4. ESTORNOS. Estornos são gravados com valor_pago = -abs(original),
 *      ou seja, NEGATIVO. SUM(valor_pago) já devolve receita LÍQUIDA;
 *      não há que somar estornos separadamente.
 *
 *   5. FILTRO POR CENTRO. Parâmetro $centro_id com semântica unificada:
 *        - 0 → todos os centros (ou equivalente a "não filtrar")
 *        - N → filtrar AND centro_id = N (na tabela-raiz da query)
 *
 *   6. BACKWARD COMPATIBILITY. Este engine é ADITIVO. Código antigo com
 *      cálculos inline continua a funcionar. O refactor dos consumidores
 *      é incremental - bloco a bloco.
 *
 *   7. mes_referencia = 'YYYY-00'. Este valor especial marca inscrições
 *      anuais (fora dos meses 01-12). O filtro por ano LEFT(...,4) apanha-o
 *      correctamente, mas o gráfico "por mês" (sige_kpi_por_mes) agrupa-o
 *      num bucket especial mês=0, deixando ao consumidor a decisão de
 *      exibir ou não. Ver contrato da função abaixo.
 *
 *   8. CACHE CURTO. Desde 12.9.9.2, KPIs pesados usam transients curtos
 *      para evitar SUM/COUNT/GROUP BY repetidos no dashboard financeiro.
 *
 * @since 13.3.0 (Abril 2026)
 * @author Rogério Benedito · RMBJ Consultoria
 * @see AUDITORIA_v13.1.0_SIGE.md §6 Bloco 2
 * @see HANDOFF_BLOCO1_v13.2.1.md
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// HELPERS INTERNOS (não são API pública)
// ============================================================================

if (!function_exists('_sige_kpi_centro_where')) {
    /**
     * Devolve fragmento SQL para filtrar por centro_id quando $centro_id > 0.
     * Seguro para concatenação (centro_id é inteiro validado).
     *
     * @param int    $centro_id  0 = todos os centros
     * @param string $alias      Alias da tabela na query (ex: 'l', 'p', 'd') ou '' para sem alias
     * @return string            Fragmento SQL começando com " AND " ou string vazia
     */
    function _sige_kpi_centro_where(int $centro_id, string $alias = ''): string {
        if ($centro_id <= 0) return '';
        $prefix = $alias !== '' ? $alias . '.' : '';
        // centro_id é int validado - concatenação segura, sem necessidade de prepare
        return " AND {$prefix}centro_id = " . (int)$centro_id;
    }
}

if (!function_exists('_sige_kpi_normalizar_ano')) {
    /**
     * Normaliza ano para intervalo válido. Defesa em profundidade contra
     * parâmetros malformados (já resolvido em sige_fin_get_ano_letivo_master()
     * pelo BUG-CRIT-06 mas mantido aqui como segunda linha).
     *
     * @param int $ano
     * @return int Ano válido (2000-2100) ou ano civil actual
     */
    function _sige_kpi_normalizar_ano(int $ano): int {
        if ($ano >= 2000 && $ano <= 2100) return $ano;
        return (int) wp_date('Y');
    }
}

if (!function_exists('_sige_kpi_cache_key')) {
    function _sige_kpi_cache_key(string $fn, array $args): string {
        $blog = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        return 'sige_kpi_' . md5($blog . '|' . $fn . '|' . wp_json_encode($args));
    }
}

if (!function_exists('_sige_kpi_cache_get')) {
    function _sige_kpi_cache_get(string $fn, array $args, &$hit = false) {
        $hit = false;
        if (defined('SIGE_DISABLE_KPI_CACHE') && SIGE_DISABLE_KPI_CACHE) return null;
        $val = get_transient(_sige_kpi_cache_key($fn, $args));
        if ($val === false) return null;
        $hit = true;
        return $val;
    }
}

if (!function_exists('_sige_kpi_cache_set')) {
    function _sige_kpi_cache_set(string $fn, array $args, $value) {
        if (defined('SIGE_DISABLE_KPI_CACHE') && SIGE_DISABLE_KPI_CACHE) return $value;
        $ttl = (int) apply_filters('sige_kpi_cache_ttl', 180);
        if ($ttl > 0) set_transient(_sige_kpi_cache_key($fn, $args), $value, $ttl);
        return $value;
    }
}

if (!function_exists('_sige_kpi_year_bounds')) {
    function _sige_kpi_year_bounds(int $ano): array {
        return [$ano . '-01-01 00:00:00', ($ano + 1) . '-01-01 00:00:00'];
    }
}

if (!function_exists('_sige_kpi_date_bounds')) {
    function _sige_kpi_date_bounds(string $data): array {
        return [$data . ' 00:00:00', date('Y-m-d H:i:s', strtotime($data . ' +1 day'))];
    }
}

if (!function_exists('_sige_kpi_mesref_bounds')) {
    function _sige_kpi_mesref_bounds(int $ano): array {
        return [(string)$ano . '-00', (string)($ano + 1) . '-00'];
    }
}

// ============================================================================
// 1. RECEITA DO ANO (recebido, já líquido de estornos)
// ============================================================================
if (!function_exists('sige_kpi_receita_ano')) {
    /**
     * Receita líquida recebida no ano (pagamentos, com estornos negativos).
     *
     * Equivalente ao $total_recebido do dashboard:
     *   SUM(valor_pago) WHERE escola_id=X AND YEAR(data_pagamento)=ano
     *
     * @param int $escola_id
     * @param int $ano        Ano civil (2000-2100); fora desta gama usa wp_date('Y')
     * @param int $centro_id  0 = todos os centros
     * @return float          Valor monetário (pode ser 0)
     */
    function sige_kpi_receita_ano(int $escola_id, int $ano, int $centro_id = 0): float {
        global $wpdb;
        if ($escola_id <= 0) return 0.0;
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit) return (float) $__cached;

        $tP  = $wpdb->prefix . 'sige_fin_pagamentos';
        $wcc = _sige_kpi_centro_where($centro_id);

        $sql = "SELECT COALESCE(SUM(valor_pago), 0)
                FROM {$tP}
                WHERE escola_id = %d
                  AND data_pagamento >= %s
                  AND data_pagamento < %s
                  {$wcc}";

        return (float) _sige_kpi_cache_set(__FUNCTION__, func_get_args(), (float) $wpdb->get_var($wpdb->prepare($sql, $escola_id, ..._sige_kpi_year_bounds($ano))));
    }
}

// ============================================================================
// 2. DESPESA DO ANO (excluindo anuladas)
// ============================================================================
if (!function_exists('sige_kpi_despesa_ano')) {
    /**
     * Total de despesas do ano, excluindo as anuladas.
     *
     * Convenção consistente com o resto do sistema: LOWER(status) <> 'anulado'
     * (conta registadas E aprovadas). Ver BUG-CRIT-01 fix v13.2.0.
     *
     * @param int $escola_id
     * @param int $ano
     * @param int $centro_id  0 = todos os centros
     * @return float
     */
    function sige_kpi_despesa_ano(int $escola_id, int $ano, int $centro_id = 0): float {
        global $wpdb;
        if ($escola_id <= 0) return 0.0;
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit) return (float) $__cached;

        $tD = $wpdb->prefix . 'sige_fin_despesas';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tD}'") !== $tD) return 0.0;

        $wcc = _sige_kpi_centro_where($centro_id);

        $sql = "SELECT COALESCE(SUM(valor), 0)
                FROM {$tD}
                WHERE escola_id = %d
                  AND data_despesa >= %s
                  AND data_despesa < %s
                  AND status <> 'anulado'
                  {$wcc}";

        return (float) _sige_kpi_cache_set(__FUNCTION__, func_get_args(), (float) $wpdb->get_var($wpdb->prepare($sql, $escola_id, ..._sige_kpi_year_bounds($ano))));
    }
}

// ============================================================================
// 3. SALDO PENDENTE DO ANO (status pendente|parcial, qualquer vencimento)
// ============================================================================
if (!function_exists('sige_kpi_pendente')) {
    /**
     * Total em dívida do ano (saldo de lançamentos pendentes/parciais).
     * NÃO inclui lançamentos em_plano (isolados em sige_kpi_em_plano).
     *
     * @param int $escola_id
     * @param int $ano
     * @param int $centro_id
     * @return float
     */
    function sige_kpi_pendente(int $escola_id, int $ano, int $centro_id = 0): float {
        global $wpdb;
        if ($escola_id <= 0) return 0.0;
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit) return (float) $__cached;

        $tL  = $wpdb->prefix . 'sige_fin_lancamentos';
        $tA  = $wpdb->prefix . 'sige_alunos';
        $saldo = sige_fin_saldo_sql('l');
        $wcc = _sige_kpi_centro_where($centro_id, 'l');
        $where_aluno_activo = function_exists('sige_aluno_activo_sql')
            ? (' AND ' . sige_aluno_activo_sql('a'))
            : " AND (a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";

        $sql = "SELECT COALESCE(SUM({$saldo}), 0)
                FROM {$tL} l
                LEFT JOIN {$tA} a ON a.id = l.aluno_id AND a.escola_id = l.escola_id
                WHERE l.escola_id = %d
                  {$where_aluno_activo}
                  AND l.mes_referencia >= %s
                  AND l.mes_referencia < %s
                  AND l.status IN ('pendente','parcial')
                  {$wcc}";

        return (float) _sige_kpi_cache_set(__FUNCTION__, func_get_args(), (float) $wpdb->get_var($wpdb->prepare($sql, $escola_id, ..._sige_kpi_mesref_bounds($ano))));
    }
}

// ============================================================================
// 4. SALDO EM ATRASO DO ANO (pendente + vencimento já passou)
// ============================================================================
if (!function_exists('sige_kpi_atraso')) {
    /**
     * Total em atraso do ano: lançamentos pendentes/parciais cujo
     * data_vencimento já passou (< hoje).
     *
     * @param int $escola_id
     * @param int $ano
     * @param int $centro_id
     * @return float
     */
    function sige_kpi_atraso(int $escola_id, int $ano, int $centro_id = 0): float {
        global $wpdb;
        if ($escola_id <= 0) return 0.0;
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit) return (float) $__cached;

        $tL    = $wpdb->prefix . 'sige_fin_lancamentos';
        $tA    = $wpdb->prefix . 'sige_alunos';
        $saldo = sige_fin_saldo_sql('l');
        $wcc   = _sige_kpi_centro_where($centro_id, 'l');
        $where_aluno_activo = function_exists('sige_aluno_activo_sql')
            ? (' AND ' . sige_aluno_activo_sql('a'))
            : " AND (a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";
        $hoje  = current_time('Y-m-d');

        $sql = "SELECT COALESCE(SUM({$saldo}), 0)
                FROM {$tL} l
                LEFT JOIN {$tA} a ON a.id = l.aluno_id AND a.escola_id = l.escola_id
                WHERE l.escola_id = %d
                  {$where_aluno_activo}
                  AND l.mes_referencia >= %s
                  AND l.mes_referencia < %s
                  AND l.status IN ('pendente','parcial')
                  AND l.data_vencimento < %s
                  {$wcc}";

        return (float) _sige_kpi_cache_set(__FUNCTION__, func_get_args(), (float) $wpdb->get_var(
            $wpdb->prepare($sql, array_merge([$escola_id], _sige_kpi_mesref_bounds($ano), [$hoje]))
        ));
    }
}

// ============================================================================
// 5. SALDO EM PLANO NEGOCIADO (status='em_plano', sem filtro de ano)
// ============================================================================
if (!function_exists('sige_kpi_em_plano')) {
    /**
     * Total em plano de pagamento negociado. Sem filtro de ano - planos
     * podem atravessar vários anos lectivos.
     *
     * @param int $escola_id
     * @param int $centro_id
     * @return float
     */
    function sige_kpi_em_plano(int $escola_id, int $centro_id = 0): float {
        global $wpdb;
        if ($escola_id <= 0) return 0.0;
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit) return (float) $__cached;

        $tL    = $wpdb->prefix . 'sige_fin_lancamentos';
        $saldo = sige_fin_saldo_sql('l');
        $wcc   = _sige_kpi_centro_where($centro_id, 'l');

        $sql = "SELECT COALESCE(SUM({$saldo}), 0)
                FROM {$tL} l
                WHERE l.escola_id = %d
                  AND l.status = 'em_plano'
                  {$wcc}";

        return (float) _sige_kpi_cache_set(__FUNCTION__, func_get_args(), (float) $wpdb->get_var($wpdb->prepare($sql, $escola_id)));
    }
}

// ============================================================================
// 6. TAXA DE COBRANÇA DO ANO (recebido / previsto, em percentagem)
// ============================================================================
if (!function_exists('sige_kpi_taxa_cobranca')) {
    /**
     * Taxa de cobrança do ano: recebido_ano / previsto_ano * 100.
     *
     * "Previsto" = total bruto de lançamentos do ano cujo status NÃO está
     * em ('cancelado','isento'). Coerente com o dashboard v13.2.1.
     *
     * @param int $escola_id
     * @param int $ano
     * @param int $centro_id
     * @return float Percentagem arredondada a 2 casas decimais (0.00 - 100.00+)
     */
    function sige_kpi_taxa_cobranca(int $escola_id, int $ano, int $centro_id = 0): float {
        global $wpdb;
        if ($escola_id <= 0) return 0.0;
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit) return (float) $__cached;

        $tL    = $wpdb->prefix . 'sige_fin_lancamentos';
        $bruto = sige_fin_total_bruto_sql('l');
        $wcc   = _sige_kpi_centro_where($centro_id, 'l');

        // Previsto: total bruto lançado no ano, excepto cancelado/isento
        $sql_prev = "SELECT COALESCE(SUM({$bruto}), 0)
                     FROM {$tL} l
                     WHERE l.escola_id = %d
                       AND l.mes_referencia >= %s
                  AND l.mes_referencia < %s
                       AND l.status NOT IN ('cancelado','isento')
                       {$wcc}";
        $previsto = (float) $wpdb->get_var(
            $wpdb->prepare($sql_prev, $escola_id, ..._sige_kpi_mesref_bounds($ano))
        );

        if ($previsto <= 0) return 0.0;

        $recebido = sige_kpi_receita_ano($escola_id, $ano, $centro_id);
        return (float) _sige_kpi_cache_set(__FUNCTION__, func_get_args(), round(($recebido / $previsto) * 100, 2));
    }
}


// ============================================================================
// 8. TOP DEVEDORES (alunos com maior dívida no ano)
// ============================================================================
if (!function_exists('sige_kpi_top_devedores')) {
    /**
     * Top N devedores do ano ordenados por saldo decrescente.
     *
     * [v14.1.0] Aceita $ano como parâmetro (consistente com as outras 11 funções
     * públicas do engine). Se $ano === 0, usa o ano lectivo master (comportamento
     * legado para callers que não passem o argumento).
     * [v14.1.0] JOIN sobre aluno passa a LEFT JOIN + COALESCE para evitar que
     * alunos apagados escondam silenciosamente a sua dívida (padrão BUG-CRIT-05).
     *
     * @param int $escola_id
     * @param int $limit      Máximo 100, mínimo 1
     * @param int $centro_id
     * @param int $ano        Ano civil; 0 (default) = ano lectivo master
     * @return array<int,object>  Cada item: {aluno_id, nome_completo, numero_processo,
     *                                        telemovel_pai, telemovel_mae, saldo}
     */
    function sige_kpi_top_devedores(int $escola_id, int $limit = 10, int $centro_id = 0, int $ano = 0): array {
        global $wpdb;
        if ($escola_id <= 0) return [];
        $limit = max(1, min(100, $limit));
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit && is_array($__cached)) return $__cached;

        $tL    = $wpdb->prefix . 'sige_fin_lancamentos';
        $tA    = $wpdb->prefix . 'sige_alunos';
        $saldo = sige_fin_saldo_sql('l');
        $wcc   = _sige_kpi_centro_where($centro_id, 'l');
        // [v12.10.96] Top devedores operacional: exclui alunos transferidos/desistentes/inactivos.
        $where_aluno_activo = function_exists('sige_aluno_activo_sql')
            ? (' AND ' . sige_aluno_activo_sql('a'))
            : " AND (a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";
        // kpi_top_devedores_activos_121096
        // [v14.1.0] Respeita $ano da UI; se 0, cai no master
        $ano_efectivo = $ano > 0
            ? _sige_kpi_normalizar_ano($ano)
            : _sige_kpi_normalizar_ano((int) sige_fin_get_ano_letivo_master());

        $sql = "SELECT l.aluno_id,
                       COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS nome_completo,
                       COALESCE(a.numero_processo, '-') AS numero_processo,
                       COALESCE(a.telemovel_pai, '') AS telemovel_pai,
                       COALESCE(a.telemovel_mae, '') AS telemovel_mae,
                       COALESCE(SUM({$saldo}), 0) AS saldo
                FROM {$tL} l
                LEFT JOIN {$tA} a ON a.id = l.aluno_id AND a.escola_id = l.escola_id
                WHERE l.escola_id = %d
                  {$where_aluno_activo}
                  AND l.mes_referencia >= %s
                  AND l.mes_referencia < %s
                  AND l.status IN ('pendente','parcial')
                  {$wcc}
                GROUP BY l.aluno_id
                HAVING saldo > 0
                ORDER BY saldo DESC
                LIMIT %d";

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, array_merge([$escola_id], _sige_kpi_mesref_bounds($ano_efectivo), [$limit]))
        );

        return _sige_kpi_cache_set(__FUNCTION__, func_get_args(), $rows ?: []);
    }
}

// ============================================================================
// 9. PENDENTE POR SERVIÇO (mix de serviços em dívida)
// ============================================================================
if (!function_exists('sige_kpi_por_servico')) {
    /**
     * Saldo pendente agrupado por serviço, ordenado por valor decrescente.
     *
     * Útil para gráfico "mix por serviço" no dashboard.
     *
     * @param int $escola_id
     * @param int $ano
     * @param int $centro_id
     * @return array<int,object>  Cada item: {servico_id, nome, pendente}
     */
    function sige_kpi_por_servico(int $escola_id, int $ano, int $centro_id = 0): array {
        global $wpdb;
        if ($escola_id <= 0) return [];
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit && is_array($__cached)) return $__cached;

        $tL    = $wpdb->prefix . 'sige_fin_lancamentos';
        $tS    = $wpdb->prefix . 'sige_fin_servicos';
        $saldo = sige_fin_saldo_sql('l');
        $wcc   = _sige_kpi_centro_where($centro_id, 'l');

        $sql = "SELECT l.servico_id, s.nome,
                       COALESCE(SUM({$saldo}), 0) AS pendente
                FROM {$tL} l
                LEFT JOIN {$tS} s ON s.id = l.servico_id
                WHERE l.escola_id = %d
                  AND l.mes_referencia >= %s
                  AND l.mes_referencia < %s
                  AND l.status IN ('pendente','parcial')
                  {$wcc}
                GROUP BY l.servico_id
                HAVING pendente > 0
                ORDER BY pendente DESC";

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, $escola_id, ..._sige_kpi_mesref_bounds($ano))
        );

        return _sige_kpi_cache_set(__FUNCTION__, func_get_args(), $rows ?: []);
    }
}

// ============================================================================
// 10. RECEITA POR MÉTODO DE PAGAMENTO
// ============================================================================
if (!function_exists('sige_kpi_por_metodo')) {
    /**
     * Total recebido no ano agrupado por método de pagamento.
     * Estornos aparecem como método 'estorno' com valor negativo
     * (NÃO são separados em linha própria) - coerência com o dashboard.
     *
     * @param int $escola_id
     * @param int $ano
     * @param int $centro_id
     * @return array<int,object>  Cada item: {metodo, total}
     */
    function sige_kpi_por_metodo(int $escola_id, int $ano, int $centro_id = 0): array {
        global $wpdb;
        if ($escola_id <= 0) return [];
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit && is_array($__cached)) return $__cached;

        $tP  = $wpdb->prefix . 'sige_fin_pagamentos';
        $wcc = _sige_kpi_centro_where($centro_id);

        $sql = "SELECT LOWER(metodo_pagamento) AS metodo,
                       COALESCE(SUM(valor_pago), 0) AS total
                FROM {$tP}
                WHERE escola_id = %d
                  AND data_pagamento >= %s
                  AND data_pagamento < %s
                  {$wcc}
                GROUP BY metodo
                ORDER BY total DESC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $escola_id, ..._sige_kpi_year_bounds($ano)));
        return _sige_kpi_cache_set(__FUNCTION__, func_get_args(), $rows ?: []);
    }
}

// ============================================================================
// 11. PREVISTO vs RECEBIDO POR MÊS (para gráficos mensais)
// ============================================================================
if (!function_exists('sige_kpi_por_mes')) {
    /**
     * Série mensal previsto vs recebido para gráficos.
     *
     * Devolve array com chaves 1-12 (meses calendário). Lançamentos com
     * mes_referencia = 'YYYY-00' (inscrições anuais) são agrupados em
     * chave especial 0 - cabe ao consumidor decidir mostrá-los.
     *
     * Estrutura:
     *   [
     *     'previsto' => [0 => 9500.00, 1 => 0.00, ..., 12 => 0.00],
     *     'recebido' => [0 => 0.00,    1 => 17850.0, ..., 12 => 0.00],
     *   ]
     *
     * Notas:
     *   - "previsto" usa SUBSTRING(mes_referencia,6,2) e exclui
     *     status IN ('cancelado','isento') - coerente com dashboard.
     *   - "recebido" usa MONTH(data_pagamento) - 1-12. Não há mês 0
     *     aqui (todo pagamento tem data de calendário real).
     *
     * @param int $escola_id
     * @param int $ano
     * @param int $centro_id
     * @return array{previsto:array<int,float>, recebido:array<int,float>}
     */
    function sige_kpi_por_mes(int $escola_id, int $ano, int $centro_id = 0): array {
        global $wpdb;
        $empty = [
            'previsto' => array_fill(0, 13, 0.0),
            'recebido' => array_fill(0, 13, 0.0),
        ];
        if ($escola_id <= 0) return $empty;
        $ano = _sige_kpi_normalizar_ano($ano);
        $__hit = false; $__cached = _sige_kpi_cache_get(__FUNCTION__, func_get_args(), $__hit);
        if ($__hit && is_array($__cached)) return $__cached;

        $tL    = $wpdb->prefix . 'sige_fin_lancamentos';
        $tP    = $wpdb->prefix . 'sige_fin_pagamentos';
        $bruto = sige_fin_total_bruto_sql('l');
        $wcc_l = _sige_kpi_centro_where($centro_id, 'l');
        $wcc_p = _sige_kpi_centro_where($centro_id);

        // Previsto por mês (inclui bucket 00 para inscrições anuais)
        $sql_prev = "SELECT CAST(SUBSTRING(l.mes_referencia, 6, 2) AS UNSIGNED) AS mes,
                            COALESCE(SUM({$bruto}), 0) AS total
                     FROM {$tL} l
                     WHERE l.escola_id = %d
                       AND l.mes_referencia >= %s
                  AND l.mes_referencia < %s
                       AND l.status NOT IN ('cancelado','isento')
                       {$wcc_l}
                     GROUP BY mes
                     ORDER BY mes ASC";
        $rows_prev = $wpdb->get_results(
            $wpdb->prepare($sql_prev, $escola_id, ..._sige_kpi_mesref_bounds($ano))
        );

        $previsto = array_fill(0, 13, 0.0); // chaves 0..12
        foreach ($rows_prev as $r) {
            $m = (int)$r->mes;
            if ($m >= 0 && $m <= 12) $previsto[$m] = (float)$r->total;
        }

        // Recebido por mês (1..12 apenas)
        $sql_rec = "SELECT MONTH(data_pagamento) AS mes,
                           COALESCE(SUM(valor_pago), 0) AS total
                    FROM {$tP}
                    WHERE escola_id = %d
                      AND data_pagamento >= %s
                  AND data_pagamento < %s
                      {$wcc_p}
                    GROUP BY MONTH(data_pagamento)
                    ORDER BY mes ASC";
        $rows_rec = $wpdb->get_results(
            $wpdb->prepare($sql_rec, $escola_id, ..._sige_kpi_year_bounds($ano))
        );

        $recebido = array_fill(0, 13, 0.0);
        foreach ($rows_rec as $r) {
            $m = (int)$r->mes;
            if ($m >= 1 && $m <= 12) $recebido[$m] = (float)$r->total;
        }

        return _sige_kpi_cache_set(__FUNCTION__, func_get_args(), ['previsto' => $previsto, 'recebido' => $recebido]);
    }
}

// ============================================================================
// 12. CAIXA DIÁRIO (resumo de um dia específico)
// ============================================================================
if (!function_exists('sige_kpi_caixa_dia')) {
    /**
     * Resumo de caixa de um dia específico.
     *
     * Estrutura devolvida:
     *   [
     *     'data'         => 'YYYY-MM-DD',
     *     'total_bruto'  => float,   // soma de valor_pago positivos (não-estornos)
     *     'total_estorno'=> float,   // valor absoluto dos estornos do dia
     *     'total_liquido'=> float,   // bruto - estorno
     *     'despesas'     => float,   // soma de despesas do dia (excl. anuladas)
     *     'saldo_dia'    => float,   // liquido - despesas
     *     'por_metodo'   => [metodo => total, ...]  // inclui estornos como valor negativo
     *   ]
     *
     * @param int    $escola_id
     * @param string $data       YYYY-MM-DD (validado)
     * @param int    $centro_id
     * @return array
     */
    function sige_kpi_caixa_dia(int $escola_id, string $data, int $centro_id = 0): array {
        global $wpdb;
        $empty = [
            'data'          => $data,
            'total_bruto'   => 0.0,
            'total_estorno' => 0.0,
            'total_liquido' => 0.0,
            'despesas'      => 0.0,
            'saldo_dia'     => 0.0,
            'por_metodo'    => [],
        ];
        if ($escola_id <= 0) return $empty;
        if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $data)) return $empty;

        $tP    = $wpdb->prefix . 'sige_fin_pagamentos';
        $tD    = $wpdb->prefix . 'sige_fin_despesas';
        $wcc_p = _sige_kpi_centro_where($centro_id);
        $wcc_d = _sige_kpi_centro_where($centro_id);

        // Pagamentos agrupados por método
        // [v12.9.66] Usa COALESCE(data_efectiva, DATE(data_pagamento)) quando
        // a coluna data_efectiva existe - permite à escola registar pagamento
        // com data lógica diferente da data de inserção (com caixa aberta).
        $data_pag_clause = function_exists('sige_fin_data_pag_sql_clause')
            ? sige_fin_data_pag_sql_clause()
            : 'DATE(data_pagamento)';
        $sql_p = "SELECT LOWER(metodo_pagamento) AS metodo,
                         COALESCE(SUM(valor_pago), 0) AS total
                  FROM {$tP}
                  WHERE escola_id = %d
                    AND {$data_pag_clause} = %s
                    {$wcc_p}
                  GROUP BY metodo";
        $rows_p = $wpdb->get_results($wpdb->prepare($sql_p, $escola_id, $data));

        $por_metodo    = [];
        $total_bruto   = 0.0;
        $total_estorno = 0.0;

        foreach ($rows_p as $r) {
            $m   = (string)$r->metodo;
            $val = (float)$r->total;
            $por_metodo[$m] = $val;
            // Estorno: método 'estorno' OU valor negativo
            if ($m === 'estorno' || $val < 0) {
                $total_estorno += abs($val);
            } else {
                $total_bruto += $val;
            }
        }

        $total_liquido = $total_bruto - $total_estorno;

        // Despesas do dia
        $despesas = 0.0;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tD}'") === $tD) {
            $sql_d = "SELECT COALESCE(SUM(valor), 0)
                      FROM {$tD}
                      WHERE escola_id = %d
                        AND data_despesa = %s
                        AND status <> 'anulado'
                        {$wcc_d}";
            $despesas = (float) $wpdb->get_var(
                $wpdb->prepare($sql_d, $escola_id, $data)
            );
        }

        return [
            'data'          => $data,
            'total_bruto'   => $total_bruto,
            'total_estorno' => $total_estorno,
            'total_liquido' => $total_liquido,
            'despesas'      => $despesas,
            'saldo_dia'     => $total_liquido - $despesas,
            'por_metodo'    => $por_metodo,
        ];
    }
}

// ============================================================================
// FIM do fin-kpi-engine.php
// ============================================================================
