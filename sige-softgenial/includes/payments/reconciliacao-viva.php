<?php
/**
 * SIGE SoftGenial - Reconciliacao viva (verificacao contra o gateway).
 * Fase 3, incremento 2.
 *
 * Confirma as transacoes locais contra o estado real no gateway (M-Pesa via
 * queryTransactionStatus; e-Mola via o seu endpoint de consulta). E so de leitura:
 * nunca apaga, cria nem altera pagamentos nem transacoes; apenas classifica e
 * regista divergencias para revisao humana. A accao (conciliar ou rejeitar) fica
 * para o fluxo manual ja existente ou para um incremento de escrita com quatro-olhos.
 *
 * Camadas:
 *  - Nucleo puro (sige_pagamentos_reconciliar_estado): recebe as transacoes locais
 *    e uma funcao de consulta ao gateway, e devolve confirmadas, divergentes e
 *    inconclusivas. Testavel em isolamento.
 *  - Adaptador (sige_pagamentos_consultar_gateway): liga a consulta aos clientes
 *    reais, de forma defensiva (nunca lanca; cliente indisponivel devolve erro).
 *  - Normalizador (sige_pagamentos_normalizar_estado_gateway): traduz a resposta do
 *    cliente para um estado simples. Conservador: so afirma 'confirmada' num sucesso
 *    claro e 'falhada' num codigo de falha conhecido; tudo o resto e 'desconhecida'.
 *  - Orquestracao (sige_reconciliacao_viva): por escola, fail-closed, so leitura.
 *  - Cron diario: corre a orquestracao de forma limitada, apenas se configurado.
 *
 * Nota: o ajuste fino dos codigos de falha do gateway depende do sandbox das
 * operadoras; por omissao a lista e vazia (conservador) e ajustavel por filtro.
 */

if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

// ============================================================================
// NUCLEO PURO (testavel)
// ============================================================================

if (!function_exists('sige_pagamentos_reconciliar_estado')) {
    /**
     * Classifica transacoes locais contra o gateway.
     *
     * @param array    $locais    Transacoes locais (id, provider, referencia_mpesa,
     *                            referencia_cliente, valor).
     * @param callable $consultar fn(provider, referencia, terceira_ref): array com
     *                            ['estado' => confirmada|falhada|nao_encontrada|
     *                            desconhecida|erro, 'valor' => float|null].
     * @return array confirmadas, divergentes, inconclusivas, totais.
     */
    function sige_pagamentos_reconciliar_estado(array $locais, callable $consultar): array {
        $out = ['confirmadas' => [], 'divergentes' => [], 'inconclusivas' => [], 'totais' => []];

        foreach ($locais as $t) {
            $t = (array) $t;
            $id    = (int) ($t['id'] ?? 0);
            $prov  = (string) ($t['provider'] ?? '');
            $ref   = (string) ($t['referencia_mpesa'] ?? ($t['referencia'] ?? ''));
            $refc  = (string) ($t['referencia_cliente'] ?? '');
            $valor = round((float) ($t['valor'] ?? 0), 2);

            if ($ref === '') {
                $out['inconclusivas'][] = ['id' => $id, 'motivo' => 'sem_referencia'];
                continue;
            }

            $g = (array) call_user_func($consultar, $prov, $ref, $refc);
            $estado = (string) ($g['estado'] ?? 'erro');
            $gvalor = (isset($g['valor']) && $g['valor'] !== null) ? round((float) $g['valor'], 2) : null;

            if ($estado === 'confirmada') {
                if ($gvalor !== null && $valor > 0 && abs($gvalor - $valor) > 0.01) {
                    $out['divergentes'][] = ['id' => $id, 'motivo' => 'valor_divergente', 'valor_local' => $valor, 'valor_gateway' => $gvalor];
                } else {
                    $out['confirmadas'][] = ['id' => $id];
                }
            } elseif ($estado === 'falhada' || $estado === 'nao_encontrada') {
                $out['divergentes'][] = ['id' => $id, 'motivo' => 'rejeitada_gateway', 'estado_gateway' => $estado];
            } else {
                $out['inconclusivas'][] = ['id' => $id, 'motivo' => $estado];
            }
        }

        $out['totais'] = [
            'n_confirmadas'   => count($out['confirmadas']),
            'n_divergentes'   => count($out['divergentes']),
            'n_inconclusivas' => count($out['inconclusivas']),
        ];
        return $out;
    }
}

if (!function_exists('sige_pagamentos_mapa_estados_gateway')) {
    /**
     * Mapa de estados e codigos de falha POR OPERADORA. Devolve as listas de estados
     * de sucesso e de falha, e os codigos de resposta de falha, para o provider dado.
     *
     * Defaults fundamentados: o M-Pesa (Vodacom, OpenAPI) reporta o estado da
     * transacao em ResponseTransactionStatus (ex.: Completed); um INS-0 confirma a
     * CONSULTA, nao a transacao, pelo que os codigos de resposta de falha ficam
     * vazios por omissao (a falha e expressa pelo estado). O e-Mola (Movitel) tem a
     * API de consulta por documentar, pelo que se mantem conservador com termos
     * genericos ate haver a especificacao oficial.
     *
     * Tudo sobreponivel por filtro, COM o provider como contexto, para afinacao por
     * operadora apos o sandbox:
     *   sige_mpesa_estados_sucesso(lista, provider)
     *   sige_mpesa_estados_falha(lista, provider)
     *   sige_mpesa_codigos_falha(lista, provider)
     */
    function sige_pagamentos_mapa_estados_gateway(string $provider = ''): array {
        $provider = strtolower(trim($provider));

        $sucesso = ['completed', 'complete', 'success', 'successful', 'concluida', 'concluido', 'sucesso'];
        $falha   = ['failed', 'failure', 'declined', 'rejected', 'cancelled', 'canceled', 'reversed', 'rollbacked', 'expired', 'error', 'falhada', 'falhado', 'rejeitada', 'recusada', 'cancelada', 'revertida', 'expirada'];
        $codigos = [];

        if ($provider === 'emola') {
            // e-Mola (Movitel): especificacao da consulta por confirmar. Por omissao,
            // sem codigos de falha (conservador); afinar por filtro apos o sandbox.
            $codigos = [];
        }

        if (function_exists('apply_filters')) {
            $sucesso = (array) apply_filters('sige_mpesa_estados_sucesso', $sucesso, $provider);
            $falha   = (array) apply_filters('sige_mpesa_estados_falha', $falha, $provider);
            $codigos = (array) apply_filters('sige_mpesa_codigos_falha', $codigos, $provider);
        }

        $minus = static function ($x) { return strtolower(trim((string) $x)); };
        $maius = static function ($x) { return strtoupper(trim((string) $x)); };
        return [
            'sucesso' => array_values(array_unique(array_map($minus, $sucesso))),
            'falha'   => array_values(array_unique(array_map($minus, $falha))),
            'codigos' => array_values(array_unique(array_map($maius, $codigos))),
        ];
    }
}

if (!function_exists('sige_pagamentos_normalizar_estado_gateway')) {
    /**
     * Traduz a resposta de um cliente de gateway para um estado simples, POR
     * OPERADORA. Conservador: da prioridade ao estado da transacao reportado pelo
     * gateway (campo de estado); na ausencia desse campo, sucesso claro -> confirmada
     * e codigo de falha conhecido -> falhada; tudo o resto -> desconhecida (nunca
     * acusa uma transacao real de falsa). As listas vem do mapa por operadora, que e
     * ajustavel por filtro.
     */
    function sige_pagamentos_normalizar_estado_gateway(array $r, string $provider = ''): array {
        if (!empty($r['erro'])) return ['estado' => 'erro', 'valor' => null];

        $dados = (isset($r['dados']) && is_array($r['dados'])) ? array_change_key_case($r['dados'], CASE_LOWER) : [];
        $valor = null;
        foreach (['output_transactionamount', 'output_amount', 'amount', 'valor', 'transamount'] as $k) {
            if (isset($dados[$k]) && $dados[$k] !== '') {
                $valor = (float) preg_replace('/[^0-9.\-]/', '', (string) $dados[$k]);
                break;
            }
        }

        $mapa = function_exists('sige_pagamentos_mapa_estados_gateway')
            ? sige_pagamentos_mapa_estados_gateway($provider)
            : ['sucesso' => ['completed', 'success'], 'falha' => ['failed', 'declined'], 'codigos' => []];

        // Estado da transacao reportado pelo gateway (o queryTransactionStatus do
        // M-Pesa devolve-o em output_ResponseTransactionStatus). Quando presente, e a
        // fonte mais fiavel: um INS-0 apenas diz que a CONSULTA correu bem, nao que a
        // transacao teve sucesso. Por isso o estado tem prioridade sobre o codigo.
        $status = '';
        foreach (['output_responsetransactionstatus', 'output_transactionstatus', 'responsetransactionstatus', 'transactionstatus', 'transaction_status', 'status'] as $k) {
            if (isset($dados[$k]) && $dados[$k] !== '') { $status = strtolower(trim((string) $dados[$k])); break; }
        }
        if ($status !== '') {
            if (in_array($status, $mapa['sucesso'], true)) return ['estado' => 'confirmada', 'valor' => $valor];
            if (in_array($status, $mapa['falha'], true)) return ['estado' => 'falhada', 'valor' => $valor];
            return ['estado' => 'desconhecida', 'valor' => $valor]; // estado presente mas nao mapeado -> conservador
        }

        // Sem campo de estado: recorrer a semantica do codigo de resposta.
        if (!empty($r['ok'])) return ['estado' => 'confirmada', 'valor' => $valor];

        if (!empty($r['http'])) {
            $rc = strtoupper(trim((string) ($r['response_code'] ?? '')));
            if ($rc !== '' && in_array($rc, $mapa['codigos'], true)) {
                return ['estado' => 'falhada', 'valor' => $valor];
            }
            return ['estado' => 'desconhecida', 'valor' => $valor];
        }

        return ['estado' => 'erro', 'valor' => null];
    }
}

// ============================================================================
// ADAPTADOR AOS CLIENTES REAIS (defensivo)
// ============================================================================

if (!function_exists('sige_pagamentos_consultar_gateway')) {
    /**
     * Consulta o estado de uma transacao no gateway real, de forma defensiva.
     * Cliente indisponivel ou erro inesperado devolvem sempre estado 'erro'.
     */
    function sige_pagamentos_consultar_gateway(string $provider, string $referencia, string $terceira_ref = ''): array {
        $provider = strtolower(trim($provider));
        if ($referencia === '') return ['estado' => 'erro', 'valor' => null];
        try {
            if ($provider === 'mpesa' && class_exists('SIGE_MPesa_Client')) {
                $r = SIGE_MPesa_Client::query_status($referencia, $terceira_ref !== '' ? $terceira_ref : $referencia);
                return sige_pagamentos_normalizar_estado_gateway(is_array($r) ? $r : [], 'mpesa');
            }
            if ($provider === 'emola' && class_exists('SIGE_EMola_Client')) {
                $r = SIGE_EMola_Client::query_status($referencia);
                return sige_pagamentos_normalizar_estado_gateway(is_array($r) ? $r : [], 'emola');
            }
        } catch (Throwable $e) {
            return ['estado' => 'erro', 'valor' => null];
        }
        return ['estado' => 'erro', 'valor' => null]; // cliente indisponivel
    }
}

// ============================================================================
// ORQUESTRACAO POR ESCOLA (so leitura, fail-closed)
// ============================================================================

if (!function_exists('sige_reconciliacao_viva')) {
    /**
     * Verifica as transacoes pendentes de uma escola contra o gateway e devolve o
     * relatorio. So leitura: regista divergencias no log de seguranca, nunca altera
     * pagamentos nem transacoes. O parametro $consultar permite injectar a consulta
     * (testes); por omissao usa o adaptador real.
     */
    function sige_reconciliacao_viva(int $escola_id, int $limite = 50, ?callable $consultar = null): array {
        global $wpdb;
        $out = ['escola_id' => $escola_id, 'confirmadas' => [], 'divergentes' => [], 'inconclusivas' => [], 'totais' => []];
        if ($escola_id <= 0) return $out; // fail-closed

        $limite = max(1, min(500, $limite));
        $tT = $wpdb->prefix . 'sige_mpesa_transacoes';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, provider, referencia_mpesa, referencia_cliente, valor, estado
             FROM {$tT}
             WHERE escola_id = %d
               AND estado IN ('recebida', 'pendente', 'pendente_manual')
             ORDER BY criado_em ASC
             LIMIT %d",
            $escola_id, $limite
        )) ?: [];

        $fn = $consultar ?? 'sige_pagamentos_consultar_gateway';
        $rel = sige_pagamentos_reconciliar_estado($rows, $fn);

        if (!empty($rel['divergentes']) && function_exists('sige_security_log')) {
            foreach ($rel['divergentes'] as $d) {
                sige_security_log('reconciliacao_viva_divergencia', 'escola=' . $escola_id . ';tx=' . (int) ($d['id'] ?? 0) . ';motivo=' . (string) ($d['motivo'] ?? ''));
            }
        }

        $out['confirmadas']   = $rel['confirmadas'];
        $out['divergentes']   = $rel['divergentes'];
        $out['inconclusivas'] = $rel['inconclusivas'];
        $out['totais']        = $rel['totais'];
        return $out;
    }
}

// ============================================================================
// CRON DIARIO (so fora do modo de teste)
// ============================================================================

if (!defined('SIGE_MPESA_TEST_MODE')) {

    add_action('sige_evento_diario', function () {
        // So corre se algum gateway estiver configurado (caso contrario, no-op barato).
        $mpesa_ok = function_exists('sige_mpesa_configurado') && sige_mpesa_configurado();
        $emola_ok = function_exists('sige_emola_configurado') && sige_emola_configurado();
        if (!$mpesa_ok && !$emola_ok) return;

        $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($escola_id <= 0) return;

        // Limite pequeno por passagem para nao prender o cron com chamadas ao gateway.
        sige_reconciliacao_viva($escola_id, 10);
    }, 20);
}
