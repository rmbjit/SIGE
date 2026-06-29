<?php
/**
 * SIGE SoftGenial - M-Pesa: conciliação automática
 *
 * REGRA DE OURO: este módulo NUNCA escreve nas tabelas financeiras.
 * Quando há match seguro, chama sige_fin_registar_pagamento() (a função
 * canónica, com todas as guardas: caixa fechada, em_plano, parciais).
 * O recibo WhatsApp sai pelo caminho normal, como num pagamento ao balcão.
 *
 * Política de matching v1 (conservadora de propósito):
 *   1. A referência do pagamento identifica o aluno pelo numero_processo
 *      (o mesmo número do crachá da Portaria);
 *   2. Escolhe-se o lançamento EM ABERTO mais antigo (data_vencimento)
 *      com saldo > 0;
 *   3. Se valor_pago <= saldo desse lançamento (+0,50 MT de tolerância de
 *      arredondamento): regista (parcial ou total) e concilia;
 *   4. Se valor_pago EXCEDE o saldo do mais antigo: pendente_manual
 *      (a secretaria decide a divisão; o sistema não inventa créditos);
 *   5. Caixa fechada: a transacção fica 'recebida' e o cron diário tenta
 *      de novo no dia seguinte. Dinheiro nunca se perde, só espera.
 *
 * O mapeador de payload e o algoritmo de escolha são puros e testáveis:
 * ver tools/smoke-mpesa.php.
 */
if (!defined('ABSPATH') && !defined('SIGE_MPESA_TEST_MODE')) exit;

// ============================================================================
// NÚCLEO PURO (testável em isolamento)
// ============================================================================

if (!function_exists('sige_pagamentos_parse_valor')) {
    /**
     * Converte um valor monetário em texto para float, tolerando os dois
     * mundos: '1500.00', '2,350.50' (anglo) e '2.350,50', '1,50' (europeu).
     * Regra: com os dois símbolos presentes, o ÚLTIMO é o separador
     * decimal; com um só, é decimal salvo quando o padrão é claramente de
     * milhares (exactamente 3 dígitos depois e pelo menos 1 antes).
     */
    function sige_pagamentos_parse_valor(string $raw): float {
        $s = preg_replace('/[^0-9.,]/', '', $raw);
        if ($s === '' || $s === null) return 0.0;
        $pv = strrpos($s, ','); $pp = strrpos($s, '.');
        if ($pv !== false && $pp !== false) {
            if ($pv > $pp) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
            else { $s = str_replace(',', '', $s); }
        } elseif ($pv !== false) {
            $dep = strlen($s) - $pv - 1;
            $s = ($dep === 3 && $pv > 0) ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif ($pp !== false) {
            $dep = strlen($s) - $pp - 1;
            if ($dep === 3 && $pp > 0) $s = str_replace('.', '', $s);
        }
        return (float)$s;
    }
}

if (!function_exists('sige_mpesa_mapear_payload')) {
    /**
     * Extrai os campos essenciais de um callback M-Pesa, tolerando as
     * variações de nomes da OpenAPI (input_/output_, maiúsculas).
     * Devolve ['referencia','referencia_cliente','msisdn','valor','moeda'].
     */
    function sige_mpesa_mapear_payload(array $p): array {
        $pega = static function (array $chaves) use ($p): string {
            foreach ($chaves as $k) {
                if (isset($p[$k]) && $p[$k] !== '') return (string)$p[$k];
            }
            // procura case-insensitive como último recurso
            $lower = array_change_key_case($p, CASE_LOWER);
            foreach ($chaves as $k) {
                $lk = strtolower($k);
                if (isset($lower[$lk]) && $lower[$lk] !== '') return (string)$lower[$lk];
            }
            return '';
        };
        $valor_raw = $pega(['input_Amount', 'output_Amount', 'amount', 'valor']);
        return [
            'referencia' => $pega(['input_TransactionID', 'output_TransactionID', 'input_TransactionReference', 'output_TransactionReference', 'transaction_id']),
            'referencia_cliente' => $pega(['input_ThirdPartyReference', 'output_ThirdPartyReference', 'input_CustomerReference', 'reference', 'referencia']),
            'msisdn' => $pega(['input_CustomerMSISDN', 'output_CustomerMSISDN', 'msisdn', 'telefone']),
            'valor' => sige_pagamentos_parse_valor($valor_raw),
            'moeda' => $pega(['input_Currency', 'currency']) ?: 'MZN',
        ];
    }
}

if (!function_exists('sige_mpesa_extrair_processo')) {
    /** A referência do cliente traz o numero_processo: só os dígitos contam. */
    function sige_mpesa_extrair_processo(string $referencia_cliente): string {
        $d = preg_replace('/\D+/', '', $referencia_cliente);
        return substr($d, 0, 20);
    }
}

if (!function_exists('sige_mpesa_escolher_lancamento')) {
    /**
     * Decide o destino de um valor dado os lançamentos em aberto do aluno.
     * @param array $abertos Lista de ['id'=>int,'saldo'=>float,'data_vencimento'=>'Y-m-d'], qualquer ordem
     * @param float $valor   Valor recebido
     * @return array ['accao'=>'registar','lancamento_id'=>int] |
     *               ['accao'=>'pendente_manual','motivo'=>string]
     */
    function sige_mpesa_escolher_lancamento(array $abertos, float $valor): array {
        $com_saldo = array_values(array_filter($abertos, static fn($l) => (float)$l['saldo'] > 0));
        if (empty($com_saldo)) {
            return ['accao' => 'pendente_manual', 'motivo' => 'Aluno sem lançamentos em aberto.'];
        }
        usort($com_saldo, static fn($a, $b) => strcmp((string)$a['data_vencimento'], (string)$b['data_vencimento']));
        $alvo = $com_saldo[0];
        if ($valor <= (float)$alvo['saldo'] + 0.5) {
            return ['accao' => 'registar', 'lancamento_id' => (int)$alvo['id']];
        }
        return [
            'accao' => 'pendente_manual',
            'motivo' => sprintf('Valor %.2f excede o saldo %.2f do lançamento mais antigo; dividir manualmente.', $valor, (float)$alvo['saldo']),
        ];
    }
}

if (!function_exists('sige_provider_metodo')) {
    /** Mapeia o provider da transacção para o método de pagamento canónico. */
    function sige_provider_metodo(string $provider): string {
        $mapa = ['mpesa' => 'mpesa', 'emola' => 'emola'];
        return $mapa[strtolower(trim($provider))] ?? 'mpesa';
    }
}

if (!function_exists('sige_provider_rotulo')) {
    /** Rótulo humano do provider para a referência externa e para o ecrã. */
    function sige_provider_rotulo(string $provider): string {
        $mapa = ['mpesa' => 'M-Pesa', 'emola' => 'e-Mola'];
        return $mapa[strtolower(trim($provider))] ?? 'M-Pesa';
    }
}

// ============================================================================
// INTEGRAÇÃO WORDPRESS
// ============================================================================
if (!defined('SIGE_MPESA_TEST_MODE')) {

    if (!function_exists('sige_mpesa_lancamentos_abertos')) {
        /** Lançamentos em aberto do aluno com saldo pela fórmula CANÓNICA. */
        function sige_mpesa_lancamentos_abertos(int $escola_id, int $aluno_id): array {
            global $wpdb;
            $tL = $wpdb->prefix . 'sige_fin_lancamentos';
            $saldo = function_exists('sige_fin_saldo_sql') ? sige_fin_saldo_sql('l') : '0';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT l.id, l.data_vencimento, {$saldo} AS saldo
                   FROM {$tL} l
                  WHERE l.escola_id = %d AND l.aluno_id = %d
                    AND l.status IN ('pendente','parcial')
                  ORDER BY l.data_vencimento ASC",
                $escola_id, $aluno_id
            ));
            $out = [];
            foreach ((array)$rows as $r) {
                $out[] = ['id' => (int)$r->id, 'saldo' => (float)$r->saldo, 'data_vencimento' => (string)$r->data_vencimento];
            }
            return $out;
        }
    }

    if (!function_exists('sige_mpesa_conciliar')) {
        /**
         * Tenta conciliar uma transacção pelo id da linha em
         * sige_mpesa_transacoes. Idempotente: só age sobre 'recebida'.
         */
        function sige_mpesa_conciliar(int $tx_id): void {
            global $wpdb;
            $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
            $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tX} WHERE id = %d", $tx_id));
            if (!$tx || $tx->estado !== 'recebida') return;

            $marcar = static function (array $campos) use ($wpdb, $tX, $tx_id): void {
                $wpdb->update($tX, $campos, ['id' => $tx_id]);
            };

            // 1) Identificar o aluno pelo numero_processo na referência
            $processo = sige_mpesa_extrair_processo((string)$tx->referencia_cliente);
            if ($processo === '') {
                $marcar(['estado' => 'pendente_manual', 'erro' => 'Referência sem número de processo.']);
                return;
            }
            $tA = $wpdb->prefix . 'sige_alunos';
            $aluno_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tA} WHERE escola_id = %d AND numero_processo = %s LIMIT 1",
                (int)$tx->escola_id, $processo
            ));
            if ($aluno_id <= 0) {
                $marcar(['estado' => 'pendente_manual', 'erro' => 'Processo ' . $processo . ' não encontrado.']);
                return;
            }
            $marcar(['aluno_id' => $aluno_id]);

            // 2) Escolher o lançamento (algoritmo puro, política conservadora)
            $abertos = sige_mpesa_lancamentos_abertos((int)$tx->escola_id, $aluno_id);
            $decisao = sige_mpesa_escolher_lancamento($abertos, (float)$tx->valor);
            if ($decisao['accao'] !== 'registar') {
                $marcar(['estado' => 'pendente_manual', 'erro' => (string)$decisao['motivo']]);
                return;
            }

            // 3) Registar pelo caminho CANÓNICO (todas as guardas incluídas)
            if (!function_exists('sige_fin_registar_pagamento')) {
                $marcar(['estado' => 'pendente_manual', 'erro' => 'Motor financeiro indisponível.']);
                return;
            }
            $ref_ext = sige_provider_rotulo((string)$tx->provider) . ' ' . (string)$tx->referencia_mpesa;
            $resultado = sige_fin_registar_pagamento(
                (int)$decisao['lancamento_id'],
                (float)$tx->valor,
                sige_provider_metodo((string)$tx->provider),
                $ref_ext
            );

            if (is_wp_error($resultado)) {
                if ($resultado->get_error_code() === 'caixa_fechado') {
                    // Fica 'recebida'; o cron diário volta a tentar amanhã.
                    $marcar(['erro' => 'Caixa fechada; nova tentativa automática no próximo dia útil.']);
                } else {
                    $marcar(['estado' => 'pendente_manual', 'erro' => $resultado->get_error_message()]);
                }
                return;
            }

            $marcar([
                'estado' => 'conciliada',
                'lancamento_id' => (int)$decisao['lancamento_id'],
                'pagamento_id' => (int)$resultado,
                'erro' => null,
                'conciliado_em' => current_time('mysql'),
                'conciliado_por' => 'sistema',
            ]);
            if (function_exists('sige_security_log')) {
                sige_security_log('mpesa_conciliada', "tx={$tx_id} aluno={$aluno_id} lanc={$decisao['lancamento_id']} valor={$tx->valor}");
            }
        }
    }

    // ── Retentativa diária das transacções presas (ex.: caixa fechada) ─────
    add_action('sige_evento_diario', function () {
        global $wpdb;
        $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
        $existe = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tX));
        if ($existe !== $tX) return;
        $ids = $wpdb->get_col("SELECT id FROM {$tX} WHERE estado = 'recebida' ORDER BY id ASC LIMIT 50");
        foreach ((array)$ids as $id) {
            sige_mpesa_conciliar((int)$id);
        }
    }, 30);

    // ── AJAX: conciliação manual (secretaria escolhe o lançamento) ─────────
    add_action('wp_ajax_sige_mpesa_conciliar_manual', function () {
        if (!function_exists('sige_mpesa_pode_gerir') || !sige_mpesa_pode_gerir()) {
            wp_send_json_error('Sem permissão.');
        }
        check_ajax_referer('sige_mpesa', '_wpnonce');
        global $wpdb;
        $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($escola_id <= 0) wp_send_json_error('Escola não identificada. Operação bloqueada.');
        $tx_id = (int)($_POST['tx_id'] ?? 0);
        $lanc_id = (int)($_POST['lancamento_id'] ?? 0);
        $tx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tX} WHERE id = %d AND escola_id = %d", $tx_id, $escola_id));
        if (!$tx || !in_array($tx->estado, ['pendente_manual', 'recebida'], true)) {
            wp_send_json_error('Transacção não está pendente para esta escola.');
        }
        if ($lanc_id <= 0) wp_send_json_error('Escolha o lançamento.');
        $lanc = $wpdb->get_row($wpdb->prepare(
            "SELECT id, aluno_id, escola_id FROM {$tL} WHERE id = %d AND escola_id = %d LIMIT 1",
            $lanc_id,
            $escola_id
        ));
        if (!$lanc) wp_send_json_error('Lançamento não pertence a esta escola.');
        if (!empty($tx->aluno_id) && (int)$tx->aluno_id > 0 && (int)$tx->aluno_id !== (int)$lanc->aluno_id) {
            wp_send_json_error('Lançamento não pertence ao aluno associado à transacção.');
        }

        $resultado = sige_fin_registar_pagamento($lanc_id, (float)$tx->valor, sige_provider_metodo((string)$tx->provider), sige_provider_rotulo((string)$tx->provider) . ' ' . (string)$tx->referencia_mpesa . ' (manual)');
        if (is_wp_error($resultado)) wp_send_json_error($resultado->get_error_message());

        $wpdb->update($tX, [
            'estado' => 'conciliada', 'lancamento_id' => $lanc_id, 'pagamento_id' => (int)$resultado,
            'aluno_id' => (int)$lanc->aluno_id,
            'erro' => null, 'conciliado_em' => current_time('mysql'),
            'conciliado_por' => 'user:' . get_current_user_id(),
        ], ['id' => $tx_id, 'escola_id' => $escola_id], ['%s','%d','%d','%s','%s','%s'], ['%d','%d']);
        if (function_exists('sige_security_log')) {
            sige_security_log('mpesa_conciliada_manual', "tx={$tx_id} lanc={$lanc_id} user=" . get_current_user_id());
        }
        wp_send_json_success('Conciliada.');
    });

    // ── AJAX: rejeitar transacção (não pertence à escola, devolvida, etc.) ─
    add_action('wp_ajax_sige_mpesa_rejeitar', function () {
        if (!function_exists('sige_mpesa_pode_gerir') || !sige_mpesa_pode_gerir()) {
            wp_send_json_error('Sem permissão.');
        }
        check_ajax_referer('sige_mpesa', '_wpnonce');
        global $wpdb;
        $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($escola_id <= 0) wp_send_json_error('Escola não identificada. Operação bloqueada.');
        $tx_id = (int)($_POST['tx_id'] ?? 0);
        $motivo = sanitize_text_field(wp_unslash((string)($_POST['motivo'] ?? '')));
        $tx = $wpdb->get_row($wpdb->prepare("SELECT estado FROM {$tX} WHERE id = %d AND escola_id = %d", $tx_id, $escola_id));
        if (!$tx || $tx->estado === 'conciliada') wp_send_json_error('Transacção inexistente nesta escola ou já conciliada.');
        $wpdb->update($tX, [
            'estado' => 'rejeitada', 'erro' => $motivo !== '' ? $motivo : 'Rejeitada pela secretaria.',
            'conciliado_em' => current_time('mysql'), 'conciliado_por' => 'user:' . get_current_user_id(),
        ], ['id' => $tx_id, 'escola_id' => $escola_id], ['%s','%s','%s'], ['%d','%d']);
        if (function_exists('sige_security_log')) {
            sige_security_log('mpesa_rejeitada', "tx={$tx_id} user=" . get_current_user_id());
        }
        wp_send_json_success('Rejeitada.');
    });

    // ── AJAX: lançamentos abertos de um aluno (para o modal de conciliação) ─
    add_action('wp_ajax_sige_mpesa_abertos', function () {
        if (!function_exists('sige_mpesa_pode_gerir') || !sige_mpesa_pode_gerir()) {
            wp_send_json_error('Sem permissão.');
        }
        check_ajax_referer('sige_mpesa', '_wpnonce');
        global $wpdb;
        $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($escola_id <= 0) wp_send_json_error('Escola não identificada. Operação bloqueada.');
        $processo = sanitize_text_field((string)($_POST['processo'] ?? ''));
        $aluno_id = (int)($_POST['aluno_id'] ?? 0);
        if ($aluno_id <= 0 && $processo !== '') {
            $tA = $wpdb->prefix . 'sige_alunos';
            $aluno_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tA} WHERE escola_id=%d AND numero_processo=%s LIMIT 1",
                $escola_id, sige_mpesa_extrair_processo($processo)
            ));
        }
        if ($aluno_id <= 0) wp_send_json_error('Aluno não encontrado. Confirme o número de processo.');
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $saldo = function_exists('sige_fin_saldo_sql') ? sige_fin_saldo_sql('l') : '0';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.id, l.descricao, l.mes_referencia, l.data_vencimento, {$saldo} AS saldo
               FROM {$tL} l
              WHERE l.escola_id=%d AND l.aluno_id=%d AND l.status IN ('pendente','parcial')
              ORDER BY l.data_vencimento ASC LIMIT 30",
            $escola_id, $aluno_id
        ));
        wp_send_json_success(['aluno_id' => $aluno_id, 'lancamentos' => array_map(static function ($r) {
            return [
                'id' => (int)$r->id,
                'descricao' => (string)($r->descricao ?? ''),
                'mes' => (string)($r->mes_referencia ?? ''),
                'vencimento' => (string)$r->data_vencimento,
                'saldo' => (float)$r->saldo,
            ];
        }, (array)$rows)]);
    });
}
