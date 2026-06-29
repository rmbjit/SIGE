<?php
/**
 * SIGE SoftGenial - Calendário de Devedores por Mês
 *
 * Pedido de cliente (12 Jun 2026): "ver de forma rápida, num lance, o
 * número de devedores por mês". Faixa de 12 tiles no topo do ecrã de
 * Devedores; cada tile mostra a contagem de DEVEDORES DISTINTOS do mês
 * e o valor em aberto, com intensidade visual proporcional; clicar num
 * mês aplica o filtro 'mes' já existente no ecrã.
 *
 * REGRAS DE OURO RESPEITADAS:
 * - O valor em aberto usa SEMPRE sige_fin_saldo_sql() (fórmula
 *   canónica da tesouraria): nunca reinventar a matemática.
 * - O agregado reutiliza AS MESMAS condições da lista do ecrã
 *   (where_base + where_extra: turma, centro, situação do aluno),
 *   excepto o próprio mês: os números do calendário batem sempre
 *   com a lista quando se clica.
 * - Leitura pura: zero escritas na BD.
 *
 * Estrutura: consulta fina (sige_fin_devedores_por_mes) + montador
 * 100% PURO (sige_fin_calendario_devedores_montar), testado em
 * tools/smoke-devedores-calendario.php sem WordPress.
 */

if (!defined('ABSPATH') && PHP_SAPI !== 'cli') { exit; }

if (!function_exists('sige_fin_calendario_meses_pt')) {
    /** Rótulos pré-AO90 dos meses, Jan..Dez. */
    function sige_fin_calendario_meses_pt(): array {
        return ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    }
}

if (!function_exists('sige_fin_calendario_devedores_montar')) {
    /**
     * PURO. Recebe linhas agregadas [['ym'=>'2026-03','devedores'=>7,'aberto'=>12500.0], ...]
     * e devolve as 12 células do ano com zero-fill, nível de calor 0..4,
     * marcação do mês corrente e do mês actualmente filtrado.
     *
     * @param array       $linhas      Linhas vindas da BD (qualquer ordem; meses em falta = zero).
     * @param int         $ano         Ano lectivo/civil do calendário.
     * @param string      $ym_corrente 'YYYY-MM' de hoje (injectado para testabilidade).
     * @param string|null $mes_filtro  'YYYY-MM' actualmente filtrado no ecrã, ou null.
     */
    function sige_fin_calendario_devedores_montar(array $linhas, int $ano, string $ym_corrente, ?string $mes_filtro = null): array {
        $mapa = [];
        foreach ($linhas as $l) {
            $l = (array)$l;
            $ym = (string)($l['ym'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { continue; }
            if ((int)substr($ym, 0, 4) !== $ano) { continue; } // outro ano: fora da grelha E da escala
            $mapa[$ym] = [
                'devedores' => max(0, (int)($l['devedores'] ?? 0)),
                'aberto'    => max(0.0, (float)($l['aberto'] ?? 0)),
            ];
        }

        $rotulos = sige_fin_calendario_meses_pt();
        $max = 0;
        foreach ($mapa as $v) { $max = max($max, $v['devedores']); }

        $celulas = [];
        $total_devedores_meses = 0;
        $total_aberto = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $ym = sprintf('%04d-%02d', $ano, $m);
            $dev = $mapa[$ym]['devedores'] ?? 0;
            $abr = $mapa[$ym]['aberto'] ?? 0.0;
            // Nível de calor: 0 = sem devedores; 1..4 proporcional ao pior mês.
            $nivel = 0;
            if ($dev > 0 && $max > 0) {
                $nivel = (int)min(4, 1 + floor(3 * $dev / $max));
            }
            $celulas[] = [
                'mes'      => $m,
                'ym'       => $ym,
                'rotulo'   => $rotulos[$m - 1],
                'devedores'=> $dev,
                'aberto'   => $abr,
                'nivel'    => $nivel,
                'corrente' => ($ym === $ym_corrente),
                'activo'   => ($mes_filtro !== null && $mes_filtro === $ym),
            ];
            $total_devedores_meses += $dev;
            $total_aberto += $abr;
        }

        return [
            'ano'     => $ano,
            'max'     => $max,
            'celulas' => $celulas,
            'tem_filtro_mes' => ($mes_filtro !== null && preg_match('/^\d{4}-\d{2}$/', (string)$mes_filtro) === 1),
            'total_aberto' => $total_aberto,
        ];
    }
}

if (!function_exists('sige_fin_devedores_por_mes')) {
    /**
     * Consulta fina (leitura). Agrega devedores DISTINTOS e valor em
     * aberto por mês do ano, reutilizando as MESMAS condições da lista
     * do ecrã (where_base + where_extra SEM a condição de mês) e a
     * fórmula canónica de saldo.
     *
     * @param wpdb   $wpdb
     * @param int    $escola_id
     * @param int    $ano          Ano do calendário (LEFT(mes_referencia,4)).
     * @param string $where_base   Ex.: "l.status IN ('pendente','parcial')".
     * @param string $where_extra  Condições adicionais do ecrã (sem o mês).
     * @param array  $params       Parâmetros do where_extra, pela ordem.
     */
    function sige_fin_devedores_por_mes($wpdb, int $escola_id, int $ano, string $where_base, string $where_extra, array $params): array {
        $saldo = sige_fin_saldo_sql('l');
        $sql = "
            SELECT LEFT(l.mes_referencia, 7) AS ym,
                   COUNT(DISTINCT l.aluno_id) AS devedores,
                   SUM({$saldo}) AS aberto
            FROM {$wpdb->prefix}sige_fin_lancamentos l
            JOIN {$wpdb->prefix}sige_alunos a ON l.aluno_id = a.id AND a.escola_id = %d
            LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id AND m.escola_id = %d AND m.ano_lectivo = " . (int)sige_fin_get_ano_letivo_master() . ")
            LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = %d
            WHERE l.escola_id = %d AND {$where_base}{$where_extra}
              AND LEFT(l.mes_referencia, 4) = %s
              AND ({$saldo}) > 0
            GROUP BY LEFT(l.mes_referencia, 7)
        ";
        $todos = array_merge([$escola_id, $escola_id, $escola_id, $escola_id], $params, [(string)$ano]);
        $linhas = $wpdb->get_results($wpdb->prepare($sql, $todos), ARRAY_A);
        return is_array($linhas) ? $linhas : [];
    }
}
