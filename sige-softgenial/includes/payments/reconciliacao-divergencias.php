<?php
/**
 * Reconciliacao e divergencias - Fase 7 incremento 1.
 *
 * Funcao de dominio (so leitura) que apura as divergencias entre o dinheiro
 * reportado pelos gateways moveis (M-Pesa e e-Mola, que partilham a tabela
 * sige_mpesa_transacoes atraves da coluna provider) e os pagamentos registados.
 *
 * Nao escreve nada. Nao altera fluxos. A interface (reconciliacao-view.php)
 * apenas apresenta o que esta funcao devolve. Esta separacao permite testar a
 * logica de forma isolada (ver tools/smoke-reconciliacao.php).
 */

if (!defined('ABSPATH') && !defined('SIGE_RECON_TEST_MODE')) { exit; }

if (!function_exists('sige_reconciliacao_divergencias')) {
    /**
     * Apura as divergencias de reconciliacao de uma escola.
     *
     * Devolve tres classes de divergencia e um bloco de totais:
     *  - nao_conciliadas: transacoes recebidas pelo gateway mas ainda nao
     *    aplicadas a um pagamento (dinheiro em limbo).
     *  - divergencias_montante: transacoes conciliadas cujo valor do gateway
     *    difere do valor do pagamento (possivel edicao posterior).
     *  - pagamentos_sem_gateway: pagamentos registados como moveis mas sem
     *    qualquer transacao de gateway que lhes corresponda.
     *
     * Fail-closed: sem escola valida, devolve a estrutura vazia.
     *
     * @param int $escola_id
     * @return array
     */
    function sige_reconciliacao_divergencias(int $escola_id): array {
        global $wpdb;

        $out = [
            'escola_id'              => $escola_id,
            'nao_conciliadas'        => [],
            'divergencias_montante'  => [],
            'pagamentos_sem_gateway' => [],
            'totais'                 => [],
        ];

        if ($escola_id <= 0) {
            return $out; // fail-closed
        }

        $tT = $wpdb->prefix . 'sige_mpesa_transacoes';
        $tP = $wpdb->prefix . 'sige_fin_pagamentos';

        // 1. Transacoes recebidas mas nao conciliadas (dinheiro em limbo).
        $out['nao_conciliadas'] = $wpdb->get_results($wpdb->prepare(
            "SELECT id, provider, referencia_mpesa, referencia_cliente, msisdn, valor, estado, criado_em
             FROM {$tT}
             WHERE escola_id = %d
               AND pagamento_id IS NULL
               AND estado IN ('recebida', 'pendente', 'pendente_manual')
             ORDER BY criado_em DESC",
            $escola_id
        )) ?: [];

        // 2. Divergencias de montante (conciliada cujo valor difere do pagamento).
        $out['divergencias_montante'] = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.provider, t.referencia_mpesa, t.valor AS valor_gateway,
                    t.pagamento_id, p.valor_pago AS valor_pagamento, t.conciliado_em
             FROM {$tT} t
             INNER JOIN {$tP} p ON p.id = t.pagamento_id
             WHERE t.escola_id = %d
               AND t.estado = 'conciliada'
               AND ABS(t.valor - p.valor_pago) > 0.01
             ORDER BY t.conciliado_em DESC",
            $escola_id
        )) ?: [];

        // 3. Pagamentos moveis sem transacao de gateway correspondente.
        $out['pagamentos_sem_gateway'] = $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.valor_pago, p.metodo_pagamento, p.referencia_externa, p.data_pagamento
             FROM {$tP} p
             WHERE p.escola_id = %d
               AND (LOWER(p.metodo_pagamento) LIKE %s OR LOWER(p.metodo_pagamento) LIKE %s)
               AND NOT EXISTS (
                   SELECT 1 FROM {$tT} t WHERE t.pagamento_id = p.id
               )
             ORDER BY p.data_pagamento DESC",
            $escola_id, '%pesa%', '%mola%'
        )) ?: [];

        // 4. Totais.
        $recebido = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM {$tT}
             WHERE escola_id = %d
               AND estado IN ('recebida', 'pendente', 'pendente_manual', 'conciliada')",
            $escola_id
        ));
        $conciliado = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM {$tT}
             WHERE escola_id = %d AND estado = 'conciliada'",
            $escola_id
        ));
        $em_limbo = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(valor), 0) FROM {$tT}
             WHERE escola_id = %d
               AND pagamento_id IS NULL
               AND estado IN ('recebida', 'pendente', 'pendente_manual')",
            $escola_id
        ));
        $pagamentos_moveis = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(valor_pago), 0) FROM {$tP}
             WHERE escola_id = %d
               AND (LOWER(metodo_pagamento) LIKE %s OR LOWER(metodo_pagamento) LIKE %s)",
            $escola_id, '%pesa%', '%mola%'
        ));

        $out['totais'] = [
            'recebido_gateway'                => round($recebido, 2),
            'conciliado'                      => round($conciliado, 2),
            'em_limbo'                        => round($em_limbo, 2),
            'pagamentos_moveis'               => round($pagamentos_moveis, 2),
            'diferenca_conciliado_vs_moveis'  => round($conciliado - $pagamentos_moveis, 2),
            'n_nao_conciliadas'               => count($out['nao_conciliadas']),
            'n_divergencias_montante'         => count($out['divergencias_montante']),
            'n_pagamentos_sem_gateway'        => count($out['pagamentos_sem_gateway']),
        ];

        return $out;
    }
}

if (!function_exists('sige_pagamentos_detectar_duplicados')) {
    /**
     * Nucleo puro (testavel) de deteccao de duplicados de pagamentos digitais.
     *
     * Recebe transacoes de gateway (cada uma com id, provider, referencia_mpesa,
     * msisdn, valor, estado, pagamento_id, criado_em) e devolve grupos de possiveis
     * duplicados, para revisao humana (so leitura, nunca apaga nem altera nada).
     * A idempotencia por referencia ja impede a mesma referencia entrar duas vezes;
     * isto apanha duplicados logicos que a idempotencia nao apanha.
     *
     * Tres classes:
     *  - referencia_repetida: mesma (provider, referencia) em duas ou mais transacoes.
     *  - pagamento_duplo: mesmo pagamento_id em duas ou mais transacoes conciliadas.
     *  - mesmo_pagador_valor: mesmo (msisdn, valor) em transacoes proximas no tempo
     *    (dentro da janela), provavel pagamento repetido por engano.
     *
     * @param array $txs            Lista de transacoes (objectos ou arrays).
     * @param int   $janela_horas   Janela para o criterio mesmo_pagador_valor.
     * @return array Lista de grupos: motivo, chave, valor, transacoes (ids), n.
     */
    function sige_pagamentos_detectar_duplicados(array $txs, int $janela_horas = 24): array {
        $norm = [];
        foreach ($txs as $t) {
            $t = (array) $t;
            $norm[] = [
                'id'           => (int) ($t['id'] ?? 0),
                'provider'     => (string) ($t['provider'] ?? ''),
                'referencia'   => (string) ($t['referencia_mpesa'] ?? ($t['referencia'] ?? '')),
                'msisdn'       => (string) ($t['msisdn'] ?? ''),
                'valor'        => round((float) ($t['valor'] ?? 0), 2),
                'estado'       => (string) ($t['estado'] ?? ''),
                'pagamento_id' => (int) ($t['pagamento_id'] ?? 0),
                'ts'           => isset($t['criado_em']) ? (int) strtotime((string) $t['criado_em']) : 0,
            ];
        }

        $grupos = [];

        // a. Mesma referencia (provider + referencia) em duas ou mais transacoes.
        $byref = [];
        foreach ($norm as $t) {
            if ($t['referencia'] === '') continue;
            $byref[$t['provider'] . '|' . $t['referencia']][] = $t['id'];
        }
        foreach ($byref as $chave => $ids) {
            if (count($ids) >= 2) {
                $grupos[] = ['motivo' => 'referencia_repetida', 'chave' => $chave, 'valor' => null, 'transacoes' => $ids, 'n' => count($ids)];
            }
        }

        // b. Mesmo pagamento_id em duas ou mais transacoes conciliadas (duplo credito).
        $bypag = [];
        foreach ($norm as $t) {
            if ($t['pagamento_id'] > 0 && $t['estado'] === 'conciliada') $bypag[$t['pagamento_id']][] = $t['id'];
        }
        foreach ($bypag as $pid => $ids) {
            if (count($ids) >= 2) {
                $grupos[] = ['motivo' => 'pagamento_duplo', 'chave' => (string) $pid, 'valor' => null, 'transacoes' => $ids, 'n' => count($ids)];
            }
        }

        // c. Mesmo pagador e valor, proximos no tempo (provavel pagamento repetido).
        $janela = max(1, $janela_horas) * 3600;
        $bypagador = [];
        foreach ($norm as $t) {
            if ($t['msisdn'] === '' || $t['valor'] <= 0) continue;
            $bypagador[$t['msisdn'] . '|' . number_format($t['valor'], 2, '.', '')][] = $t;
        }
        foreach ($bypagador as $chave => $lista) {
            if (count($lista) < 2) continue;
            usort($lista, static function ($a, $b) { return $a['ts'] <=> $b['ts']; });
            $n = count($lista);
            $i = 0;
            while ($i < $n) {
                $cluster = [$lista[$i]];
                $j = $i + 1;
                while ($j < $n) {
                    $prev = $cluster[count($cluster) - 1];
                    if ($prev['ts'] > 0 && $lista[$j]['ts'] > 0 && ($lista[$j]['ts'] - $prev['ts']) <= $janela) {
                        $cluster[] = $lista[$j];
                        $j++;
                    } else {
                        break;
                    }
                }
                if (count($cluster) >= 2) {
                    $ids = array_map(static function ($x) { return $x['id']; }, $cluster);
                    $grupos[] = ['motivo' => 'mesmo_pagador_valor', 'chave' => $chave, 'valor' => $cluster[0]['valor'], 'transacoes' => $ids, 'n' => count($cluster)];
                }
                $i = ($j > $i) ? $j : ($i + 1);
            }
        }

        return $grupos;
    }
}

if (!function_exists('sige_reconciliacao_duplicados')) {
    /**
     * Involucro de dominio (so leitura): carrega as transacoes de uma escola e
     * devolve os grupos de possiveis duplicados, com totais. Fail-closed.
     */
    function sige_reconciliacao_duplicados(int $escola_id, int $janela_horas = 24): array {
        global $wpdb;
        $out = ['escola_id' => $escola_id, 'grupos' => [], 'totais' => ['n_grupos' => 0, 'n_transacoes' => 0]];
        if ($escola_id <= 0) return $out;

        $tT = $wpdb->prefix . 'sige_mpesa_transacoes';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, provider, referencia_mpesa, msisdn, valor, estado, pagamento_id, criado_em
             FROM {$tT} WHERE escola_id = %d ORDER BY criado_em ASC",
            $escola_id
        )) ?: [];

        $grupos = sige_pagamentos_detectar_duplicados($rows, $janela_horas);
        $envolvidas = [];
        foreach ($grupos as $g) {
            foreach ($g['transacoes'] as $id) $envolvidas[(int) $id] = true;
        }
        $out['grupos'] = $grupos;
        $out['totais'] = ['n_grupos' => count($grupos), 'n_transacoes' => count($envolvidas)];
        return $out;
    }
}

if (!function_exists('sige_reconciliacao_duplicado_rotulo')) {
    /** Rotulo legivel do motivo de um grupo de duplicados. */
    function sige_reconciliacao_duplicado_rotulo(string $motivo): string {
        $map = [
            'referencia_repetida' => 'Referencia repetida',
            'pagamento_duplo'     => 'Mesmo pagamento conciliado mais de uma vez',
            'mesmo_pagador_valor' => 'Mesmo pagador e valor, proximos no tempo',
        ];
        return $map[$motivo] ?? $motivo;
    }
}
