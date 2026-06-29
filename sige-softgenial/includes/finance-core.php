<?php

if (!defined('ABSPATH')) exit;

// ============================================================================
// [12.9.9.2 V7.1] Performance Financeiro - cache leve de leitura
// ----------------------------------------------------------------------------
// Objectivo: evitar consultas repetidas aos mesmos serviços/configurações durante
// uma única navegação no financeiro. Não altera regras, não persiste decisões de
// negócio e pode ser desactivado por constante em caso de diagnóstico.
// ============================================================================
if (!function_exists('sige_fin_get_servicos_ativos_cached')) {
    function sige_fin_get_servicos_ativos_cached(int $escola_id = 0, bool $forcar_refresh = false): array {
        global $wpdb;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($escola_id <= 0) return [];
        static $cache = [];
        $key = (string) $escola_id;
        if (!$forcar_refresh && isset($cache[$key])) return $cache[$key];
        $tS = $wpdb->prefix . 'sige_fin_servicos';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tS} WHERE ativo=1 AND escola_id=%d ORDER BY id ASC", $escola_id));
        $cache[$key] = is_array($rows) ? $rows : [];
        return $cache[$key];
    }
}

if (!function_exists('sige_fin_get_servico_cached')) {
    function sige_fin_get_servico_cached(int $servico_id, int $escola_id = 0) {
        if ($servico_id <= 0) return null;
        foreach (sige_fin_get_servicos_ativos_cached($escola_id) as $srv) {
            if ((int)($srv->id ?? 0) === $servico_id) return $srv;
        }
        return null;
    }
}


/**
 * ==================================================
 * SIGE SoftGenial - FINANCE CORE (FUSÃO: ORIGINAL + TRANSPORTES + EXTRAS)
 * ==================================================

 * Fonte única de regras financeiras.

 * Mantém integridade com o sistema legado e adiciona suporte a rotas e extras.

 */

// ── Helpers de data/hora (fuso horário de Moçambique) ──────────────────────
//
// Estas funções eram definidas em financeiro-pagamentos.php, tornando-as
// indisponíveis para qualquer módulo carregado antes desse ficheiro.
// Movidas para o finance-core para garantir disponibilidade global.
// Todas usam guards (!function_exists) para compatibilidade com código legado.
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('sige_mz_tz')) {
    function sige_mz_tz(): DateTimeZone {
        static $tz = null;
        if ($tz === null) {
            $zone = 'Africa/Maputo'; // default
            $cfg = function_exists('sige_fin_cfg') ? sige_fin_cfg() : null;
            if ($cfg && !empty($cfg->timezone)) {
                $zone = $cfg->timezone;
            }
            $tz = new DateTimeZone($zone);
        }
        return $tz;
    }
}

if (!function_exists('sige_mz_date')) {
    function sige_mz_date(string $format, $timestamp = null): string {
        $dt = new DateTime('now', sige_mz_tz());
        if ($timestamp !== null) {
            if ($timestamp instanceof DateTimeInterface) {
                $dt = (new DateTime('@' . $timestamp->getTimestamp()))->setTimezone(sige_mz_tz());
            } else {
                $dt = (new DateTime('@' . (int)$timestamp))->setTimezone(sige_mz_tz());
            }
        }
        return $dt->format($format);
    }
}

if (!function_exists('sige_mz_mysql_now')) {
    function sige_mz_mysql_now(): string {
        return sige_mz_date('Y-m-d H:i:s');
    }
}

// ── Sanitização de inputs ────────────────────────────────────────────────
// [P07] Wrappers para $_GET/$_POST - substituem acesso directo.
// Cada wrapper aplica sanitize_text_field() e tipo adequado.
// ─────────────────────────────────────────────────────────────────────────

if (!function_exists('sige_fin_get_param')) {
    /** GET string (sanitized). */
    function sige_fin_get_param(string $key, string $default = ''): string {
        return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : $default;
    }
}
if (!function_exists('sige_fin_post_param')) {
    /** POST string (sanitized). */
    function sige_fin_post_param(string $key, string $default = ''): string {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $default;
    }
}
if (!function_exists('sige_fin_get_int')) {
    /** GET integer. */
    function sige_fin_get_int(string $key, int $default = 0): int {
        return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
    }
}
if (!function_exists('sige_fin_post_int')) {
    /** POST integer. */
    function sige_fin_post_int(string $key, int $default = 0): int {
        return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
    }
}
if (!function_exists('sige_fin_post_float')) {
    /** POST float (para valores monetários). */
    function sige_fin_post_float(string $key, float $default = 0.0): float {
        return isset($_POST[$key]) ? (float) str_replace(',', '.', sanitize_text_field(wp_unslash($_POST[$key]))) : $default;
    }
}
if (!function_exists('sige_fin_post_array')) {
    /** POST array of integers (para selecções múltiplas). */
    function sige_fin_post_array(string $key): array {
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) return [];
        return array_map('intval', $_POST[$key]);
    }
}

// ════════════════════════════════════════════════════════════════════════
// FUNÇÕES CANÓNICAS DE SALDO - FONTE ÚNICA DE VERDADE
// ════════════════════════════════════════════════════════════════════════
//
// REGRA: nenhum ficheiro do módulo financeiro deve definir a fórmula de
// saldo inline. Todos usam estas funções. Mudar aqui actualiza o sistema
// inteiro automaticamente.
//
// Fórmula:
//   (original + transporte + extras + multa_efectiva - descontos) - pago
//   multa_efectiva = multa_cobrada (se foi paga) ou multa_calculada
//
// ════════════════════════════════════════════════════════════════════════

// ── Constantes de status ─────────────────────────────────────────────────
// Usar estas constantes em vez de repetir as strings em cada ficheiro.
// 'em_plano' = lançamento congelado por acordo negociado (não é dívida livre)

if (!defined('SIGE_STATUS_DEVEDOR')) {
    // Lançamentos que representam dívida activa (aparecem em devedores, relatórios, etc.)
    define('SIGE_STATUS_DEVEDOR',    "'pendente','parcial','em_plano'");
    // Lançamentos que NÃO devem aparecer como dívida livre
    define('SIGE_STATUS_LIQUIDADO',  "'pago','cancelado','isento'");
    // Apenas dívida livre (sem planos negociados) - para KPIs de cobrança
    define('SIGE_STATUS_LIVRE',      "'pendente','parcial'");
}

// ── Migração automática: mes_referencia VARCHAR(7) → VARCHAR(20) ────────
// (v11.0) Migrado para class-sige-migration.php (M1)
// Função mantida como no-op para compatibilidade.
if (!function_exists('sige_fin_migration_mes_ref')) {
    function sige_fin_migration_mes_ref(): void { return; }
}

// ── sige_fin_saldo_sql() ─────────────────────────────────────────────────
// Expressão SQL para calcular o saldo em dívida de um lançamento.
// Usar em queries SELECT para somas e agrupamentos.
//
// @param string $alias  Alias da tabela de lançamentos na query (default 'l')
// @return string        Expressão SQL com GREATEST(... , 0)

if (!function_exists('sige_fin_saldo_sql')) {
    function sige_fin_saldo_sql(string $alias = 'l'): string {
        $a = $alias;
        return "GREATEST(
            COALESCE({$a}.valor_original, 0)
            + COALESCE({$a}.valor_transporte, 0)
            + COALESCE({$a}.valor_extras, 0)
            + COALESCE(NULLIF({$a}.valor_multa_cobrada, 0), {$a}.valor_multa, 0)
            - COALESCE({$a}.valor_desconto, 0)
            - COALESCE({$a}.valor_desconto_especial, 0)
            - COALESCE({$a}.valor_pago, 0)
        , 0)";
    }
}

// ── sige_fin_total_bruto_sql() ───────────────────────────────────────────
// Expressão SQL para calcular o total bruto (SEM subtrair valor_pago).
// Usar em relatórios de "emitido" e "previsto" (dashboard, relatório mensal).
// [NOVA R-04] Adicionada na auditoria de 5/Abr/2026 para eliminar fórmulas inline.
//
// @param string $alias  Alias da tabela de lançamentos na query (default 'l')
// @return string        Expressão SQL com GREATEST(... , 0)

if (!function_exists('sige_fin_total_bruto_sql')) {
    function sige_fin_total_bruto_sql(string $alias = 'l'): string {
        $a = $alias;
        return "GREATEST(
            COALESCE({$a}.valor_original, 0)
            + COALESCE({$a}.valor_transporte, 0)
            + COALESCE({$a}.valor_extras, 0)
            + COALESCE(NULLIF({$a}.valor_multa_cobrada, 0), {$a}.valor_multa, 0)
            - COALESCE({$a}.valor_desconto, 0)
            - COALESCE({$a}.valor_desconto_especial, 0)
        , 0)";
    }
}

// ── sige_fin_saldo_lancamento() ──────────────────────────────────────────
// Versão PHP da mesma fórmula - para cálculos com objectos já carregados.
// Idêntica à lógica de sige_fin_atualizar_status_lancamento().
//
// @param object|array $l  Linha do lançamento (stdClass ou array)
// @return float           Valor em dívida (nunca negativo)

if (!function_exists('sige_fin_saldo_lancamento')) {
    function sige_fin_saldo_lancamento($l): float {
        $l = (object)$l;
        // Multa efectiva: usa multa_cobrada (auditoria imutável) se disponível
        $multa = (float)($l->valor_multa_cobrada ?? 0) > 0
            ? (float)$l->valor_multa_cobrada
            : (float)($l->valor_multa ?? 0);

        $total = (float)($l->valor_original  ?? 0)
               + (float)($l->valor_transporte ?? 0)
               + (float)($l->valor_extras     ?? 0)
               + $multa
               - (float)($l->valor_desconto         ?? 0)
               - (float)($l->valor_desconto_especial ?? 0);

        return max(0.0, $total - (float)($l->valor_pago ?? 0));
    }
}

// Alias legado - compatibilidade com chamadas existentes em financeiro-pagamentos
// e financeiro-extratos que usam estes nomes
if (!function_exists('sige_fin_total_restante')) {
    function sige_fin_total_restante($l): float {
        return sige_fin_saldo_lancamento($l);
    }
}
if (!function_exists('sige_fin_total_lancamento')) {
    function sige_fin_total_lancamento($l): float {
        // Sem subtrair valor_pago - retorna o total bruto do lançamento
        $l = (object)$l;
        $multa = (float)($l->valor_multa_cobrada ?? 0) > 0
            ? (float)$l->valor_multa_cobrada
            : (float)($l->valor_multa ?? 0);
        return max(0.0,
            (float)($l->valor_original  ?? 0)
          + (float)($l->valor_transporte ?? 0)
          + (float)($l->valor_extras     ?? 0)
          + $multa
          - (float)($l->valor_desconto         ?? 0)
          - (float)($l->valor_desconto_especial ?? 0)
        );
    }
}

// ── sige_fin_mes_base() ─────────────────────────────────────────────────
// Extrai o mês base (YYYY-MM) de um mes_referencia que pode ter sufixo de
// avulso (ex: '2026-04-2' → '2026-04', '2026-04' → '2026-04').
// Usar em relatórios e agrupamentos por mês.
if (!function_exists('sige_fin_mes_base')) {
    function sige_fin_mes_base(string $mes_ref): string {
        // Formato esperado: YYYY-MM ou YYYY-MM-sufixo
        if (preg_match('/^(\d{4}-\d{2})/', $mes_ref, $m)) {
            return $m[1];
        }
        return $mes_ref;
    }
}

// ── sige_fin_mes_base_sql() ─────────────────────────────────────────────
// Expressão SQL equivalente: LEFT(coluna, 7) para extrair YYYY-MM.
// Usar em GROUP BY e WHERE de relatórios.
if (!function_exists('sige_fin_mes_base_sql')) {
    function sige_fin_mes_base_sql(string $col = 'l.mes_referencia'): string {
        return "LEFT({$col}, 7)";
    }
}

// (v11.0) sige_moeda() movido para core-helpers.php



// (v11.0) sige_get_escola_id() é fornecido por multitenancy.php
// Fallback mantido caso multitenancy.php não esteja carregado.
if (!function_exists('sige_get_escola_id')) {
    function sige_get_escola_id(): int { return 1; }
}

// [FIX BLOQ-01] Helper: verificar se mês está bloqueado para um aluno
// Usado para impedir envio de notificações em meses sem cobrança
function sige_fin_mes_bloqueado(int $aluno_id, string $mes_referencia): bool {
    global $wpdb;
    $tL = $wpdb->prefix . 'sige_fin_lancamentos';

    // [B09] Primeiro verificar fecho global do período (escola inteira)
    if (!sige_fin_periodo_aberto($mes_referencia)) {
        return true;
    }

    // Bloqueio individual por aluno (mecanismo original)
    $bloqueio = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM $tL
         WHERE escola_id = %d
           AND aluno_id = %d
           AND mes_referencia = %s
           AND servico_id = 0
           AND valor_original = 0
           AND status = 'cancelado'
         LIMIT 1",
        sige_get_escola_id(), $aluno_id, $mes_referencia
    ));

    return (int)$bloqueio > 0;
}

// ── [B09] Gestão de Períodos - funções core ───────────────────────────────
// Controlo escola-wide: quais meses do ano lectivo estão abertos/fechados
// para geração de lançamentos, pagamentos e cobranças.
// Armazenado como WP option JSON: {"2026-01":"aberto","2026-02":"fechado",...}

if (!function_exists('sige_fin_periodos_key')) {
    function sige_fin_periodos_key(?int $ano = null): string {
        if ($ano === null) $ano = function_exists('sige_fin_get_ano_letivo_master') ? sige_fin_get_ano_letivo_master() : (int) wp_date('Y');
        return 'sige_fin_periodos_' . sige_get_escola_id() . '_' . $ano;
    }
}

if (!function_exists('sige_fin_periodos_get')) {
    /** Retorna array associativo mes_ref => 'aberto'|'fechado'. Meses sem entrada = aberto. */
    function sige_fin_periodos_get(?int $ano = null): array {
        $data = get_option(sige_fin_periodos_key($ano), '{}');
        $arr = json_decode((string) $data, true);
        return is_array($arr) ? $arr : [];
    }
}

if (!function_exists('sige_fin_periodos_save')) {
    function sige_fin_periodos_save(array $periodos, ?int $ano = null): bool {
        return update_option(sige_fin_periodos_key($ano), wp_json_encode($periodos, JSON_UNESCAPED_UNICODE));
    }
}

if (!function_exists('sige_fin_periodo_aberto')) {
    /** Verifica se um mês está aberto para operações financeiras. */
    function sige_fin_periodo_aberto(string $mes_referencia): bool {
        $ano = (int) substr($mes_referencia, 0, 4);
        $periodos = sige_fin_periodos_get($ano);
        $estado = $periodos[$mes_referencia] ?? 'aberto';
        return $estado === 'aberto';
    }
}



function sige_fin_get_ano_letivo_master(): int {



    global $wpdb;



    $cfg = $wpdb->get_row($wpdb->prepare(
        "SELECT ano_lectivo FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
        sige_get_escola_id()
    ));



    return (int)($cfg->ano_lectivo ?? (int)wp_date('Y'));



}



function sige_fin_get_config(?int $ano_letivo = null) {



    global $wpdb;



    $ano = $ano_letivo ?: sige_fin_get_ano_letivo_master();



    $t = $wpdb->prefix . 'sige_fin_configuracoes';

    // [v12.5.2] Config financeira sempre isolada por escola + ano lectivo.
    // Evita herdar acidentalmente permissões financeiras de outra escola/linha histórica.
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $t WHERE escola_id = %d AND ano_letivo = %d ORDER BY id DESC LIMIT 1",
        sige_get_escola_id(),
        $ano
    ));

    // Fallback seguro: mesma escola, configuração mais recente.
    if (!$row) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $t WHERE escola_id = %d ORDER BY id DESC LIMIT 1",
            sige_get_escola_id()
        ));
    }

    // Fallback final de compatibilidade para instalações legadas sem escola_id.
    if (!$row) {
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $t WHERE ano_letivo = %d ORDER BY id DESC LIMIT 1",
            $ano
        ));
    }

    if (!$row) {
        $row = $wpdb->get_row("SELECT * FROM $t ORDER BY id DESC LIMIT 1");
    }




    return $row;



}



// ── Função central de auditoria ──────────────────────────────────────────────



// Módulos válidos: 'financeiro' | 'alunos' | 'notas' | 'acesso' | 'sistema'



function sige_audit_log(string $acao, $detalhes = null, string $modulo = 'financeiro', ?int $user_id = null): void {



    global $wpdb;



    $t = $wpdb->prefix . 'sige_logs_auditoria';



    // (v11.0) Colunas modulo e user_display garantidas por class-sige-migration.php (M2)

    $uid = $user_id ?? get_current_user_id();



    $u   = $uid ? get_userdata($uid) : null;



    $wpdb->insert($t, [



        'escola_id'    => sige_get_escola_id(),



        'user_id'      => $uid,



        'user_display' => $u ? ($u->display_name ?: $u->user_login) : 'Sistema',



        'modulo'       => sanitize_text_field($modulo),



        'acao'         => sanitize_text_field($acao),



        'detalhes'     => is_string($detalhes) ? $detalhes : wp_json_encode($detalhes, JSON_UNESCAPED_UNICODE),



        'ip_address'   => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '',



        'data_hora'    => current_time('mysql'),



    ]);



}



// Alias legado - mantém compatibilidade com chamadas existentes



function sige_fin_log(string $acao, $detalhes = null): void {



    sige_audit_log($acao, $detalhes, 'financeiro');



}


// ── [v12.11.9.31] Métodos de pagamento canónicos ────────────────────────
// Mantém compatibilidade com métodos antigos e passa a expor POS por banco:
// POS BCI, POS BIM, POS STBANK, POS MOZA, POS NEDBANK e POS FNB.
if (!function_exists('sige_fin_metodos_pagamento_map')) {
    function sige_fin_metodos_pagamento_map(string $context = 'all'): array {
        $all = [
            'numerario'         => 'Numerário',
            'mpesa'             => 'M-Pesa',
            'emola'             => 'E-Mola',
            'emola_comerciante' => 'E-Mola Comerciante',
            // Métodos bancários existentes, preservados para histórico e operação actual.
            'bim'               => 'Millennium BIM',
            'bci'               => 'BCI',
            'pagafacil'         => 'Paga Fácil',
            'nib'               => 'Transferência (NIB)',
            'transferencia'     => 'Transferência Bancária',
            // POS por banco.
            'pos_bci'           => 'POS BCI',
            'pos_bim'           => 'POS BIM',
            'pos_stbank'        => 'POS STBANK',
            'pos_moza'          => 'POS MOZA',
            'pos_nedbank'       => 'POS NEDBANK',
            'pos_fnb'           => 'POS FNB',
            // Valor legado para pagamentos antigos registados apenas como "pos".
            'pos'               => 'POS (Banco não especificado)',
            'cheque'            => 'Cheque',
            'cartao'            => 'Cartão',
            'banco'             => 'Banco',
            'estorno'           => 'Estorno',
        ];

        $context = strtolower(trim($context));
        if (in_array($context, ['pagamento', 'plano', 'familia'], true)) {
            $keys = ['numerario','mpesa','emola','emola_comerciante','bim','bci','pagafacil','pos_bci','pos_bim','pos_stbank','pos_moza','pos_nedbank','pos_fnb','nib','transferencia'];
            return array_intersect_key($all, array_flip($keys));
        }
        if ($context === 'despesa') {
            $keys = ['numerario','mpesa','emola','emola_comerciante','bim','bci','pagafacil','pos_bci','pos_bim','pos_stbank','pos_moza','pos_nedbank','pos_fnb','nib','transferencia','cheque','cartao'];
            return array_intersect_key($all, array_flip($keys));
        }
        if ($context === 'pos') {
            $keys = ['pos_bci','pos_bim','pos_stbank','pos_moza','pos_nedbank','pos_fnb'];
            return array_intersect_key($all, array_flip($keys));
        }
        return $all;
    }
}

if (!function_exists('sige_fin_metodos_pagamento_labels')) {
    function sige_fin_metodos_pagamento_labels(): array {
        return sige_fin_metodos_pagamento_map('all');
    }
}

if (!function_exists('sige_fin_metodos_pagamento_recebimento')) {
    function sige_fin_metodos_pagamento_recebimento(): array {
        return sige_fin_metodos_pagamento_map('pagamento');
    }
}

if (!function_exists('sige_fin_metodo_pagamento_key')) {
    function sige_fin_metodo_pagamento_key($metodo): string {
        $key = strtolower(trim((string)$metodo));
        $key = str_replace([' ', '-'], '_', $key);
        $key = preg_replace('/_+/', '_', $key);
        $aliases = [
            'posbci' => 'pos_bci',
            'posbim' => 'pos_bim',
            'posstbank' => 'pos_stbank',
            'pos_st_bank' => 'pos_stbank',
            'pos_standard_bank' => 'pos_stbank',
            'pos_standardbank' => 'pos_stbank',
            'pos_moza_banco' => 'pos_moza',
            'pos_tpa' => 'pos',
            'tpa' => 'pos',
            'm_pesa' => 'mpesa',
            'e_mola' => 'emola',
        ];
        return $aliases[$key] ?? $key;
    }
}

if (!function_exists('sige_fin_metodo_pagamento_label')) {
    function sige_fin_metodo_pagamento_label($metodo): string {
        $key = function_exists('sige_fin_metodo_pagamento_key') ? sige_fin_metodo_pagamento_key($metodo) : strtolower(trim((string)$metodo));
        if ($key === '') return '-';
        $labels = function_exists('sige_fin_metodos_pagamento_map') ? sige_fin_metodos_pagamento_map('all') : [];
        return $labels[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
    }
}

if (!function_exists('sige_fin_metodo_pagamento_icon')) {
    function sige_fin_metodo_pagamento_icon($metodo): string {
        $key = function_exists('sige_fin_metodo_pagamento_key') ? sige_fin_metodo_pagamento_key($metodo) : strtolower(trim((string)$metodo));
        if ($key === 'numerario') return '💵';
        if (in_array($key, ['mpesa','emola','emola_comerciante'], true)) return '📱';
        if (strpos($key, 'pos') === 0 || in_array($key, ['pagafacil','cartao'], true)) return '💳';
        if (in_array($key, ['bim','bci','nib','transferencia','banco'], true)) return '🏦';
        if ($key === 'cheque') return '📄';
        if ($key === 'estorno') return '↩️';
        return '💳';
    }
}

if (!function_exists('sige_fin_metodos_pos_keys')) {
    function sige_fin_metodos_pos_keys(): array {
        return ['pos', 'pos_bci', 'pos_bim', 'pos_stbank', 'pos_moza', 'pos_nedbank', 'pos_fnb'];
    }
}

if (!function_exists('sige_fin_metodos_pagamento_options_html')) {
    function sige_fin_metodos_pagamento_options_html(string $selected = '', array $args = []): string {
        $context  = isset($args['context']) ? (string)$args['context'] : 'pagamento';
        $icons    = !empty($args['icons']);
        $selected = function_exists('sige_fin_metodo_pagamento_key') ? sige_fin_metodo_pagamento_key($selected) : strtolower(trim($selected));
        $out = '';
        foreach (sige_fin_metodos_pagamento_map($context) as $value => $label) {
            $text = ($icons ? sige_fin_metodo_pagamento_icon($value) . ' ' : '') . $label;
            $out .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($selected, $value, false),
                esc_html($text)
            );
        }
        return $out;
    }
}

// ── [B05] Resumo de métodos de pagamento (texto legível) ──────────────────
// Converte array de totais por método num texto compacto para a coluna
// resumo_metodos da tabela de fechos de caixa.
// Exemplo: "Numerário: 6.700,00 | M-Pesa: 31.450,00 | POS BCI: 3.750,00"
if (!function_exists('sige_fin_resumo_metodos_texto')) {
    function sige_fin_resumo_metodos_texto(array $resumo): string {
        $partes = [];
        foreach ($resumo as $met => $val) {
            $v = (float) $val;
            if ($v <= 0 && $met !== 'estorno') continue;
            if ($met === 'estorno' && $v <= 0) continue;
            $label = ($met === 'estorno') ? 'Estornos' : sige_fin_metodo_pagamento_label($met);
            $partes[] = $label . ': ' . number_format($v, 2, ',', '.') . ' MT';
        }
        return implode(' | ', $partes) ?: 'Sem movimentos';
    }
}



/**
 * V7.2.4 - Controlo manual de notificações financeiras.
 * Permite que formulários financeiros decidam, por acção, se devem enviar
 * WhatsApp e/ou E-mail aos encarregados. O padrão continua a ser enviar quando
 * formulários antigos não trouxerem estes campos.
 */
if (!function_exists('sige_fin_notificacao_canal_ativo')) {
    function sige_fin_notificacao_canal_ativo(string $canal, string $prefixo = 'notificar'): bool {
        $canal = sanitize_key($canal);
        $prefixo = sanitize_key($prefixo ?: 'notificar');
        $campo = $prefixo . '_' . $canal;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
        if (array_key_exists($campo, $_POST)) {
            $valor = is_array($_POST[$campo]) ? reset($_POST[$campo]) : $_POST[$campo];
            return (string)$valor === '1';
        }
        return true;
    }
}

if (!function_exists('sige_fin_notificacao_canal_label')) {
    function sige_fin_notificacao_canal_label(bool $whatsapp, bool $email): string {
        if ($whatsapp && $email) return 'WhatsApp + E-mail';
        if ($whatsapp) return 'Apenas WhatsApp';
        if ($email) return 'Apenas E-mail';
        return 'Sem notificação';
    }
}


function sige_fin_queue_whatsapp(int $aluno_id, string $telefone, string $tipo, string $mensagem): bool {

    global $wpdb;

    // v12.9.53 - Guard anti-cron: tipos financeiros só com origem humana.
    // Bloqueia tentativas de enfileiramento durante DOING_CRON para tipos
    // protegidos (fatura/recibo/cobranca/lembrete). A acção da escola
    // (lançamento, pagamento, cobrança manual) ocorre fora de cron e não
    // é afectada. Defesa em profundidade - sem alteração de fórmulas.
    if (function_exists('sige_wpp_tpl_block_cron_origin')
        && sige_wpp_tpl_block_cron_origin($tipo, $aluno_id, $telefone)) {
        return false;
    }

    $telefone = function_exists('sige_telefone_normalizar') ? sige_telefone_normalizar($telefone) : preg_replace('/[^0-9]/', '', $telefone);
    if (!$telefone || !$mensagem) return false;

    // [v12.9.69] Guard transversal de destinatários WhatsApp.
    // Mesmo que algum módulo antigo tente enfileirar para Pai/Mãe/Nº de Notificações
    // directamente, a fila só aceita o número permitido pela política da escola.
    if (function_exists('sige_wpp_destinatario_permitido')
        && !sige_wpp_destinatario_permitido($aluno_id, $telefone)) {
        if (function_exists('sige_fin_log')) {
            sige_fin_log('wpp_destinatario_bloqueado_politica', [
                'aluno_id' => $aluno_id,
                'tipo'     => $tipo,
                'telefone' => $telefone,
                'politica' => function_exists('sige_wpp_destinatarios_get') ? sige_wpp_destinatarios_get() : 'n/a',
            ]);
        }
        return false;
    }

    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    if ($eid <= 0) { return false; }
    $t = $wpdb->prefix . 'sige_whatsapp_queue';

    // v12.9.19 - Guardian/Recovery Mode:
    // 1) nunca envia directo neste ponto; 2) humaniza; 3) separa links;
    // 4) agenda links com atraso; 5) respeita opt-out/contactos.
    $items = function_exists('sige_wpp_prepare_queue_messages')
        ? sige_wpp_prepare_queue_messages($eid, $telefone, $tipo, $mensagem, $aluno_id)
        : [[ 'message' => $mensagem, 'delay' => 0, 'priority' => 5, 'flags' => '' ]];

    $ok_all = true;
    $inserted_count = 0;
    $skipped_safety = 0;
    foreach ($items as $item) {
        $msg = trim((string)($item['message'] ?? ''));
        if ($msg === '') continue;

        // v12.11.2 - Notification Humanization PRO: último filtro antes da fila.
        // Remove sinais visíveis de automação/SIGE e aproxima o texto de uma conversa real da secretaria.
        if (function_exists('sige_notify_humanize_outbound_whatsapp')) {
            $msg = sige_notify_humanize_outbound_whatsapp($msg, $tipo, [
                'escola_id' => $eid,
                'aluno_id'  => $aluno_id,
                'telefone'  => $telefone,
            ]);
        }
        if ($msg === '') continue;

        // v12.10.136 - Guardrails PRO: bloqueia duplicados/cooldown antes de inserir.
        // Bloqueios silenciosos contam como sucesso seguro para não incentivar cliques repetidos.
        if (function_exists('sige_wpp_guardrails_pro_can_queue')) {
            $decision = sige_wpp_guardrails_pro_can_queue($eid, $aluno_id, $telefone, $tipo, $msg);
            if (empty($decision['ok'])) {
                if (!empty($decision['silent'])) {
                    $skipped_safety++;
                    if (function_exists('sige_fin_log')) {
                        sige_fin_log('whatsapp_guardrails_pro_skip_seguro', [
                            'aluno_id' => $aluno_id,
                            'tipo'     => $tipo,
                            'telefone' => $telefone,
                            'motivo'   => (string)($decision['reason'] ?? 'skip_seguro'),
                        ]);
                    }
                    continue;
                }
                if (function_exists('sige_fin_log')) {
                    sige_fin_log('whatsapp_guardrails_pro_bloqueado', [
                        'aluno_id' => $aluno_id,
                        'tipo'     => $tipo,
                        'telefone' => $telefone,
                        'motivo'   => (string)($decision['reason'] ?? 'bloqueado'),
                    ]);
                }
                $ok_all = false;
                continue;
            }
        }

        $delay = max(0, (int)($item['delay'] ?? 0));
        $priority = max(1, min(9, (int)($item['priority'] ?? 5)));
        $flags = sanitize_text_field((string)($item['flags'] ?? ''));

        if (function_exists('sige_wpp_guardrails_pro_initial_schedule_ts')) {
            $schedule_ts = sige_wpp_guardrails_pro_initial_schedule_ts($eid, $telefone, $tipo, $priority, $delay, $aluno_id, $msg);
            $scheduled = wp_date('Y-m-d H:i:s', $schedule_ts, new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
        } else {
            // Fallback seguro: nunca fica pronto no próprio clique.
            $scheduled = wp_date('Y-m-d H:i:s', current_time('timestamp') + max(120, $delay), new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo'));
        }

        $data = [
            'escola_id'  => $eid,
            'telefone'   => $telefone,
            'mensagem'   => $msg,
            'tipo'       => sanitize_text_field($tipo),
            'aluno_id'   => $aluno_id,
            'status'     => 'pendente',
            'tentativas' => 0,
            'criado_em'  => current_time('mysql'),
            'enviado_em' => null,
            'erro'       => null,
        ];

        // Colunas Guardian existem após migração, mas fazemos fallback defensivo.
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at')) $data['scheduled_at'] = $scheduled;
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('priority')) $data['priority'] = $priority;
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('risk_flags')) $data['risk_flags'] = trim($flags . ' guardrails_pro_v1210136');
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('guardian_meta')) $data['guardian_meta'] = wp_json_encode([
            'recovery_version' => defined('SIGE_WPP_RECOVERY_VERSION') ? SIGE_WPP_RECOVERY_VERSION : 'n/a',
            'guardrails_pro' => defined('SIGE_WPP_GUARDRAILS_PRO_VERSION') ? SIGE_WPP_GUARDRAILS_PRO_VERSION : '12.10.136',
            'scheduled_at' => $scheduled,
            'min_global_seconds' => defined('SIGE_WPP_PRO_MIN_GLOBAL_SECONDS') ? SIGE_WPP_PRO_MIN_GLOBAL_SECONDS : 120,
            'flags' => $flags,
            'click_action' => 'queued_only_no_immediate_send',
        ], JSON_UNESCAPED_UNICODE);

        $inserted = $wpdb->insert($t, $data);
        if (!$inserted) {
            $ok_all = false;
        } else {
            $inserted_count++;
        }
    }

    if (($inserted_count + $skipped_safety) > 0 && function_exists('sige_fin_log')) {
        sige_fin_log('whatsapp_enfileirado_guardrails_pro', [
            'aluno_id' => $aluno_id,
            'tipo'     => $tipo,
            'telefone' => $telefone,
            'itens'    => count($items),
            'inseridos'=> $inserted_count,
            'skipped_seguro' => $skipped_safety,
        ]);
    }

    return (bool)($ok_all && ($inserted_count > 0 || $skipped_safety > 0));

}


// ==========================================



// RECIBO SEQUENCIAL



// ==========================================



if (!function_exists('sige_fin_gerar_recibo_numero')) {



    function sige_fin_gerar_recibo_numero($prefixo = 'REC') {



        global $wpdb;



        $ano = function_exists('sige_fin_get_ano_letivo_master')



            ? (int)sige_fin_get_ano_letivo_master()



            : (int)wp_date('Y');



        $prefixo = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$prefixo));



        if ($prefixo === '') $prefixo = 'REC';



        // [FIX M-05] Sequência atómica via opção WP com UPDATE condicional.



        // Antes: get_option + update_option sem lock → dois pagamentos simultâneos



        // podiam gerar o mesmo número de recibo.



        // Agora: UPDATE com WHERE garante atomicidade a nível de linha MySQL.



        $key = 'sige_recibo_seq_' . $prefixo . '_' . $ano . '_e' . sige_get_escola_id();



        // Garantir que a opção existe (INSERT IGNORE semantics via add_option)



        add_option($key, 0, '', 'no'); // 'no' = não autoload; não faz nada se já existir



        // Incremento atómico: UPDATE só avança se o valor ainda for o que lemos



        $max_tentativas = 10;



        $seq = null;



        for ($i = 0; $i < $max_tentativas; $i++) {



            // [FIX M-05b] Ler sempre da BD - get_option() usa alloptions cache
            // que wp_cache_delete($key, 'options') NÃO invalida. Query directa
            // garante que cada iteração vê o valor mais recente no MySQL.
            $atual = (int)$wpdb->get_var(
                $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key)
            );
            if ($atual === null) $atual = 0; // opção ainda não existe



            $novo  = $atual + 1;



            // UPDATE condicional: só actualiza se option_value ainda for $atual



            $updated = $wpdb->query($wpdb->prepare(



                "UPDATE {$wpdb->options}



                 SET option_value = %d



                 WHERE option_name = %s AND option_value = %d",



                $novo, $key, $atual



            ));



            if ($updated > 0) {



                // Limpar cache de objectos do WP para a próxima leitura



                // Invalidar AMBAS as caches: individual e alloptions
                wp_cache_delete($key, 'options');
                wp_cache_delete('alloptions', 'options'); // resolve o bug de colisão de recibos



                $seq = $novo;



                break;



            }



            // Outra transacção ganhou; tentar novamente (micro-pausa)



            usleep(5000); // 5ms



        }



        // Fallback de segurança: se as 10 tentativas falharem,
        // usar MAX do DB para garantir unicidade real (nunca reutilizar).
        if ($seq === null) {
            $seq_db = (int)$wpdb->get_var(
                $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key)
            );
            // Incrementar pelo menos 1 acima do valor actual
            $seq = $seq_db > 0 ? $seq_db + 1 : 1;
            // Tentar gravar o novo valor (best-effort, sem loop)
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %d WHERE option_name = %s AND option_value = %d",
                $seq, $key, $seq_db
            ));
            wp_cache_delete($key, 'options');
            wp_cache_delete('alloptions', 'options');
        }



        return $prefixo . '-' . $ano . '-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);



    }



}

// ── [B06] Referência fiscal completa do recibo ────────────────────────────
// Gera a referência fiscal para impressão, incluindo NIF da escola.
// Formato MZ: "REC-2026-000001 · NIF: XXXXXXXXX · Colégio Malisa"
// O número de recibo (acima) mantém-se inalterado para compatibilidade.

if (!function_exists('sige_fin_recibo_ref_fiscal')) {
    function sige_fin_recibo_ref_fiscal(string $recibo_numero): string {
        global $wpdb;
        static $escola_cache = null;
        if ($escola_cache === null) {
            $tE = $wpdb->prefix . 'sige_escolas';
            $escola_cache = $wpdb->get_row($wpdb->prepare(
                "SELECT nome, nif FROM $tE WHERE id = %d LIMIT 1",
                sige_get_escola_id()
            ));
            if (!$escola_cache) $escola_cache = (object)['nome' => '', 'nif' => ''];
        }
        $partes = [$recibo_numero];
        if (!empty($escola_cache->nif)) {
            $partes[] = 'NUIT: ' . $escola_cache->nif;
        }
        if (!empty($escola_cache->nome)) {
            $partes[] = $escola_cache->nome;
        }
        return implode(' · ', $partes);
    }
}

// ── [B06] Dados fiscais da escola (para templates de recibo) ──────────────

if (!function_exists('sige_fin_dados_escola')) {
    function sige_fin_dados_escola(): object {
        global $wpdb;
        static $cache = null;
        if ($cache !== null) return $cache;
        $tE = $wpdb->prefix . 'sige_escolas';
        $cache = $wpdb->get_row($wpdb->prepare(
            "SELECT nome, nif, email, telefone, endereco, logo_url FROM $tE WHERE id = %d LIMIT 1",
            sige_get_escola_id()
        ));
        if (!$cache) $cache = (object)['nome' => '', 'nif' => '', 'email' => '', 'telefone' => '', 'endereco' => '', 'logo_url' => ''];
        return $cache;
    }
}



// ==========================================

// [FIX F-02] VALIDAÇÃO DE CLASSE (global)

// Movido de financeiro-pagamentos.php para estar disponível em todos os módulos

// ==========================================



if (!function_exists('sige_fin_norm_classe')) {

    function sige_fin_norm_classe($v) {

        $v = strtolower(trim((string)$v));

        $v = str_replace(['ª','º'], '', $v);

        $v = preg_replace('/\s+/', '', $v);

        return $v;

    }

}



if (!function_exists('sige_fin_servico_permitido_para_classe')) {

    /**

     * Verifica se um serviço é permitido para a classe do aluno.

     * - serviço.classe = 'todas' ou vazio => geral (permite tudo)

     * - serviço.classe = '2' ou '2ª' => só para essa classe

     */

    function sige_fin_servico_permitido_para_classe($servico_row, $classe_aluno): bool {

        if (!$servico_row) return false;

        // [v12.11.9.88.2] Usar a normalização canónica sempre que a camada
        // avançada estiver carregada. Isto corrige casos importados por Excel
        // e catálogos com classes compostas, por exemplo "2º/3º Ano", "1º Ano",
        // "Pré-primário", sem deixar o pagamento gerar "Nenhuma dívida processada".
        if (class_exists('SIGE_FinanceClasseHelper') && method_exists('SIGE_FinanceClasseHelper', 'servicoAplicavelAoAluno')) {
            return SIGE_FinanceClasseHelper::servicoAplicavelAoAluno($servico_row, (string)$classe_aluno);
        }

        $classe_aluno = trim((string)$classe_aluno);

        $classe_srv   = trim((string)($servico_row->classe ?? ''));

        $classe_srv_lower = function_exists('mb_strtolower') ? mb_strtolower($classe_srv, 'UTF-8') : strtolower($classe_srv);
        if ($classe_srv === '' || $classe_srv === '0' || $classe_srv_lower === 'todas') return true;

        return sige_fin_norm_classe($classe_srv) === sige_fin_norm_classe($classe_aluno);

    }

}


if (!function_exists('sige_fin_tipo_servico_canonico')) {
    /**
     * Normaliza tipos financeiros que representam a mesma obrigação recorrente.
     *
     * Regra crítica: mensalidade é uma obrigação do aluno+mês, não do serviço_id
     * da classe actual. Se o aluno mudou de turma/classe, os meses já lançados
     * ou pagos com o serviço antigo continuam a liquidar esse mês.
     */
    function sige_fin_tipo_servico_canonico($tipo): string {
        $tipo = strtolower(trim((string)$tipo));
        if (in_array($tipo, ['mensalidade', 'mensal'], true)) return 'mensalidade';
        if ($tipo === 'transporte') return 'transporte';
        return $tipo;
    }
}

if (!function_exists('sige_fin_tipos_equivalentes')) {
    /**
     * Lista de tipos equivalentes para pesquisas SQL semânticas.
     * Ex.: catálogos legados usam "mensal" e catálogos novos usam "mensalidade".
     */
    function sige_fin_tipos_equivalentes($tipo): array {
        $canon = function_exists('sige_fin_tipo_servico_canonico') ? sige_fin_tipo_servico_canonico($tipo) : strtolower(trim((string)$tipo));
        if ($canon === 'mensalidade') return ['mensalidade', 'mensal'];
        if ($canon === 'transporte') return ['transporte'];
        return $canon !== '' ? [$canon] : [];
    }
}

if (!function_exists('sige_fin_lancamento_recorrente_existente_por_tipo_mes')) {
    /**
     * Procura qualquer lançamento recorrente do mesmo tipo canónico para aluno+mês.
     *
     * Esta função é deliberadamente independente da classe actual. Ela protege
     * histórico financeiro quando um aluno muda de classe/turma a meio do ano:
     * mensalidades pagas com o serviço da classe anterior não podem voltar a
     * aparecer como dívida apenas porque o serviço_id actual mudou.
     *
     * @param array<int,string> $statuses Lista opcional de statuses a limitar.
     * @return object|null
     */
    function sige_fin_lancamento_recorrente_existente_por_tipo_mes(int $aluno_id, string $tipo, string $mes_ref, int $escola_id = 0, array $statuses = []) {
        global $wpdb;
        if ($aluno_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $mes_ref)) return null;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        $tipos = function_exists('sige_fin_tipos_equivalentes') ? sige_fin_tipos_equivalentes($tipo) : [strtolower(trim($tipo))];
        $tipos = array_values(array_unique(array_filter(array_map('strval', $tipos))));
        if (empty($tipos)) return null;

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tS = $wpdb->prefix . 'sige_fin_servicos';
        $tipo_ph = implode(',', array_fill(0, count($tipos), '%s'));
        $params = array_merge([$escola_id, $aluno_id, $mes_ref], $tipos);
        $status_sql = '';
        if (!empty($statuses)) {
            $statuses = array_values(array_unique(array_filter(array_map('strval', $statuses))));
            if (!empty($statuses)) {
                $status_sql = ' AND l.status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')';
                $params = array_merge($params, $statuses);
            }
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT l.*
             FROM {$tL} l
             JOIN {$tS} s ON s.id = l.servico_id AND s.escola_id = l.escola_id
             WHERE l.escola_id = %d
               AND l.aluno_id = %d
               AND l.mes_referencia = %s
               AND LOWER(TRIM(COALESCE(s.tipo,''))) IN ({$tipo_ph})
               {$status_sql}
             ORDER BY
               CASE l.status
                 WHEN 'pago' THEN 1
                 WHEN 'isento' THEN 2
                 WHEN 'parcial' THEN 3
                 WHEN 'pendente' THEN 4
                 WHEN 'em_plano' THEN 5
                 WHEN 'cancelado' THEN 6
                 ELSE 9
               END,
               l.data_vencimento ASC,
               l.id ASC
             LIMIT 1",
            $params
        ));
    }
}

if (!function_exists('sige_fin_find_lancamento_aberto_por_tipo_mes')) {
    /**
     * Localiza lançamento aberto por tipo canónico de serviço para aluno+mês.
     * Útil para pagar cartões virtuais (MENS_01/TRAN_01) mesmo quando o ID do
     * serviço mudou, a turma veio do Excel, ou a UI ainda não marcou o item como
     * "pendente" por divergência de classe/serviço.
     *
     * @return object|null
     */
    function sige_fin_find_lancamento_aberto_por_tipo_mes(int $aluno_id, string $tipo, string $mes_ref, int $escola_id = 0) {
        global $wpdb;
        if ($aluno_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $mes_ref)) return null;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        $tipo = strtolower(trim($tipo));
        $tipos = function_exists('sige_fin_tipos_equivalentes') ? sige_fin_tipos_equivalentes($tipo) : [$tipo];
        $tipos = array_values(array_unique(array_filter($tipos)));
        if (empty($tipos)) return null;
        $placeholders = implode(',', array_fill(0, count($tipos), '%s'));
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tS = $wpdb->prefix . 'sige_fin_servicos';
        $params = array_merge([$escola_id, $aluno_id, $mes_ref], $tipos);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT l.*
             FROM {$tL} l
             JOIN {$tS} s ON s.id = l.servico_id AND s.escola_id = l.escola_id
             WHERE l.escola_id = %d
               AND l.aluno_id = %d
               AND l.mes_referencia = %s
               AND l.status IN ('pendente','parcial')
               AND LOWER(TRIM(COALESCE(s.tipo,''))) IN ({$placeholders})
             ORDER BY l.data_vencimento ASC, l.id ASC
             LIMIT 1",
            $params
        ));
    }
}

if (!function_exists('sige_fin_upsert_acao_label')) {
    function sige_fin_upsert_acao_label(array $res): string {
        $acao = strtolower(trim((string)($res['acao'] ?? '')));
        $map = [
            'bloqueado_aluno_inactivo' => 'aluno não está activo operacionalmente',
            'bloqueado_mes' => 'mês bloqueado',
            'bloqueado_classe' => 'serviço incompatível com a classe/turma do aluno',
            'bloqueado_creche' => 'serviço de creche incompatível com o aluno',
            'bloqueado_creche_inverso' => 'serviço de mensalidade normal incompatível com aluno de creche',
            'erro_insert' => 'erro ao gravar lançamento',
            'erro_servico' => 'serviço não encontrado/inactivo',
            'em_plano' => 'lançamento em plano negociado',
            'ignorado_existente' => 'lançamento existente não accionável',
            'ignorado_pago' => 'mensalidade já paga/isenta',
        ];
        $label = $map[$acao] ?? ($acao !== '' ? $acao : 'sem detalhe');
        if (!empty($res['db_error'])) $label .= ' - DB: ' . (string)$res['db_error'];
        return $label;
    }
}

// ==========================================
// [FIX CRECHE-01] Helpers creche - fonte única de verdade
// Movidos do gerador/pagamentos para finance-core (Abril 2026)
// ==========================================

if (!function_exists('sige_fin_servico_eh_mensalidade')) {
    /**
     * Verifica se um serviço é do tipo mensalidade.
     */
    function sige_fin_servico_eh_mensalidade($servico) {
        if (!$servico) return false;
        if (isset($servico->tipo) && is_string($servico->tipo)) {
            $v = strtolower(trim($servico->tipo));
            if (in_array($v, ['mensalidade', 'mensal'], true)) return true;
        }
        $nome = strtolower(trim((string)($servico->nome ?? '')));
        return (strpos($nome, 'mensal') !== false);
    }
}

if (!function_exists('sige_fin_get_servico_creche')) {
    /**
     * Retorna o serviço de mensalidade de creche adequado ao regime e ano do aluno.
     *
     * [FIX CRECHE-01] Corrigido: 'semi-integral' já não faz match para regime 'integral'.
     * [FIX CRECHE-02] Novo: distingue serviços '5o ano' vs '2o a 4o ano' via $classe_aluno.
     *
     * @param string       $regime_creche   'semi_integral' ou 'integral'
     * @param array        $servicos_ativos Lista de serviços activos (objectos com ->nome, ->tipo)
     * @param string       $classe_aluno    Classe da turma do aluno (ex: '2º/3º Ano', 'Pré-primário')
     *                                      Opcional - se vazio, ignora filtro de ano.
     * @return object|null Serviço correspondente ou null.
     */
    function sige_fin_get_servico_creche($regime_creche, $servicos_ativos, $classe_aluno = '') {
        if (!$regime_creche || !is_array($servicos_ativos)) return null;

        $is_semi = (strtolower(trim((string)$regime_creche)) === 'semi_integral');

        // Detectar se o aluno é do 5º ano (pré-primário)
        $cl = strtolower(trim((string)$classe_aluno));
        $has_classe = ($cl !== '');
        $is_5o = false;
        if ($has_classe) {
            $is_5o = (
                strpos($cl, 'pré') !== false
                || strpos($cl, 'pre-prim') !== false
                || strpos($cl, 'preprim') !== false
                || $cl === '5'
                || strpos($cl, '5º') !== false
                || strpos($cl, '5o') !== false
            );
        }

        foreach ($servicos_ativos as $s) {
            if (!sige_fin_servico_eh_mensalidade($s)) continue;
            $nome = strtolower(trim((string)($s->nome ?? '')));
            if (strpos($nome, 'creche') === false) continue;

            // [FIX CRECHE-01] Match exacto de regime
            $nome_has_semi = (strpos($nome, 'semi') !== false);
            if ($is_semi && !$nome_has_semi) continue;   // quer semi, serviço não é semi
            if (!$is_semi && $nome_has_semi) continue;    // quer integral, serviço é semi(-integral)

            // [FIX CRECHE-02] Filtro de ano (só se classe disponível)
            if ($has_classe) {
                $nome_has_5 = (strpos($nome, '5o') !== false || strpos($nome, '5º') !== false);
                if ($is_5o && !$nome_has_5) continue;     // aluno 5º ano, serviço é 2-4
                if (!$is_5o && $nome_has_5) continue;     // aluno 2-4, serviço é 5º ano
            }

            return $s;
        }
        return null;
    }
}



function sige_fin_obter_servico(int $servico_id) {



    global $wpdb;



    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_fin_servicos WHERE id=%d AND escola_id=%d", $servico_id, sige_get_escola_id()));



}



// ==========================================



// [A-04] UPSERT DE LANÇAMENTO - assinatura unificada



// ==========================================



/**



 * Cria ou actualiza um lançamento financeiro para um aluno/serviço/mês.



 *



 * @param int        $aluno_id         ID do aluno.



 * @param int        $servico_id       ID do serviço (usado na query e no INSERT).



 * @param string     $mes_ref          Mês de referência no formato 'YYYY-MM'.



 * @param string     $descricao        Texto descritivo do lançamento.



 * @param float      $valor_original   Valor bruto antes de descontos/multas.



 * @param string     $data_vencimento  Data de vencimento ('YYYY-MM-DD').



 * @param mixed      $config_fin       Configuração financeira (array ou objecto).



 * @param object|null $servico_obj     Objecto do serviço. Se null, é carregado



 *                                     automaticamente via sige_fin_obter_servico().



 *



 * @return array{acao: string, lanc_id: int}



 *         acao ∈ { 'criado', 'actualizado', 'ignorado_pago',



 *                  'ignorado_existente', 'erro_insert', 'erro_servico' }



 */



function sige_fin_upsert_lancamento(



    int    $aluno_id,



    int    $servico_id,



    string $mes_ref,



    string $descricao,



    float  $valor_original,



    string $data_vencimento,



           $config_fin,



           $servico_obj = null



): array {



    global $wpdb;



    // --- Resolução do objecto de serviço (unificação A-04) ---



    if ($servico_obj === null) {



        $servico_obj = sige_fin_obter_servico($servico_id);



        if (!$servico_obj) {



            sige_fin_log('upsert_lancamento_erro', [



                'motivo'     => 'servico_nao_encontrado',



                'servico_id' => $servico_id,



                'aluno_id'   => $aluno_id,



            ]);



            return ['acao' => 'erro_servico', 'lanc_id' => 0];



        }



    }

    // [v12.12.64 HIST-CLASSE-01] Idempotência financeira antes da elegibilidade de classe.
    // Se já existe mensalidade/transporte histórico pago, isento ou em plano para
    // o mesmo aluno+mês, ele liquida esse mês mesmo que o aluno tenha mudado de
    // classe depois. A classe actual nunca deve transformar mês já liquidado em dívida.
    if (function_exists('sige_fin_lancamento_recorrente_existente_por_tipo_mes')) {
        $__tipo_hist = function_exists('sige_fin_tipo_servico_canonico')
            ? sige_fin_tipo_servico_canonico($servico_obj->tipo ?? '')
            : strtolower(trim((string)($servico_obj->tipo ?? '')));
        if (in_array($__tipo_hist, ['mensalidade', 'transporte'], true)) {
            $__hist = sige_fin_lancamento_recorrente_existente_por_tipo_mes(
                (int)$aluno_id,
                (string)$__tipo_hist,
                (string)$mes_ref,
                function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0,
                ['pago', 'isento', 'em_plano']
            );
            if ($__hist && !empty($__hist->id)) {
                $__st_hist = strtolower(trim((string)($__hist->status ?? '')));
                if (in_array($__st_hist, ['pago', 'isento'], true)) {
                    return ['acao' => 'ignorado_pago', 'lanc_id' => (int)$__hist->id];
                }
                if ($__st_hist === 'em_plano') {
                    return ['acao' => 'em_plano', 'lanc_id' => (int)$__hist->id];
                }
            }
        }
    }

    // [v12.10.94 PRO] Transferidos/desistentes/inactivos não recebem novos lançamentos recorrentes.
    // Histórico, dívidas anteriores e pagamentos continuam preservados.
    if (function_exists('sige_aluno_financeiramente_inactivo')
        && function_exists('sige_fin_servico_recorrente_bloqueavel')
        && sige_fin_servico_recorrente_bloqueavel($servico_obj)
        && sige_aluno_financeiramente_inactivo($aluno_id, sige_get_escola_id())) {
        if (function_exists('sige_fin_log')) {
            sige_fin_log('upsert_bloqueado_aluno_inactivo', [
                'aluno_id'   => $aluno_id,
                'servico_id' => $servico_id,
                'mes_ref'    => $mes_ref,
                'motivo'     => 'Aluno transferido/desistente/inactivo não recebe cobrança recorrente nova.',
            ]);
        }
        return ['acao' => 'bloqueado_aluno_inactivo', 'lanc_id' => 0];
    }



    // [FIX BLOQ-04] Mês bloqueado não aceita lançamentos

    if (function_exists('sige_fin_mes_bloqueado') && sige_fin_mes_bloqueado($aluno_id, $mes_ref)) {

        return ['acao' => 'bloqueado_mes', 'lanc_id' => 0];

    }



    // [FIX F-02] Safety net: validação de classe no core

    if (function_exists('sige_fin_servico_permitido_para_classe') && $servico_obj) {

        $classe_srv = trim((string)($servico_obj->classe ?? ''));

        // Só valida se o serviço tem restrição de classe (não é "todas")

        if ($classe_srv !== '' && $classe_srv !== '0' && strtolower($classe_srv) !== 'todas') {

            $_tM = $wpdb->prefix . 'sige_matriculas';

            $_tT = $wpdb->prefix . 'sige_turmas';

            if (function_exists('sige_fin_obter_classe_actual_aluno')) {
                $classe_aluno = (string)sige_fin_obter_classe_actual_aluno((int)$aluno_id, 0, function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
            } else {
                $classe_aluno = (string)$wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(NULLIF(TRIM(t.classe),''), NULLIF(TRIM(t.nivel_ensino),''), '')
                     FROM $_tM m
                     JOIN $_tT t ON t.id = m.turma_id
                     WHERE m.aluno_id = %d
                       AND (m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo'))
                     ORDER BY m.id DESC LIMIT 1",
                    $aluno_id
                ));
            }

            if ($classe_aluno && !sige_fin_servico_permitido_para_classe($servico_obj, $classe_aluno)) {

                return ['acao' => 'bloqueado_classe', 'lanc_id' => 0];

            }

        }

    }



    // [FIX F-03] Safety net: isolamento creche ↔ primário

    // Regra A: Serviço creche NÃO pode ser cobrado a aluno sem regime_creche

    // Regra B: Serviço mensalidade com classe específica NÃO pode ser cobrado a aluno de creche

    if ($servico_obj && strtolower((string)($servico_obj->tipo ?? '')) === 'mensalidade') {

        $nome_srv_lower = strtolower((string)($servico_obj->nome ?? ''));

        $is_srv_creche = (strpos($nome_srv_lower, 'creche') !== false);



        // Buscar regime_creche do aluno (single query, cached se possível)

        $regime_aluno = (string)$wpdb->get_var($wpdb->prepare(

            "SELECT COALESCE(regime_creche,'') FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d",

            $aluno_id, sige_get_escola_id()

        ));

        $aluno_e_creche = ($regime_aluno !== '' && $regime_aluno !== '0');



        // Regra A: serviço é creche mas aluno NÃO é creche → bloquear

        if ($is_srv_creche && !$aluno_e_creche) {

            return ['acao' => 'bloqueado_creche', 'lanc_id' => 0];

        }



        // Regra B: aluno É creche mas serviço é mensalidade normal (não-creche) → bloquear

        if ($aluno_e_creche && !$is_srv_creche) {

            return ['acao' => 'bloqueado_creche_inverso', 'lanc_id' => 0];

        }

    }



    // [FIX F-04 REMOVIDO] Avulsos agora podem ser cobrados múltiplas vezes

    // (ex: pai compra uniforme em Março e outro em Outubro)

    // O bloqueio anterior impedia isto - removido a pedido da escola.

    $tL = $wpdb->prefix . 'sige_fin_lancamentos';
    $tS = $wpdb->prefix . 'sige_fin_servicos';

    // [FASE1-B01] Detecção de duplicatas melhorada:
    // 1) Match exacto por servico_id (comportamento actual)
    $existe = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tL WHERE escola_id=%d AND aluno_id=%d AND servico_id=%d AND mes_referencia=%s LIMIT 1",
        sige_get_escola_id(), $aluno_id, $servico_id, $mes_ref
    ));

    // 2) Se não encontrou E o serviço é recorrente (mensalidade/transporte),
    //    procurar por QUALQUER serviço do mesmo tipo para o mesmo aluno+mês.
    //    Isto previne duplicatas quando o servico_id muda (ex: correcção creche 21→22).
    if (!$existe && $servico_obj) {
        $tipo_srv = function_exists('sige_fin_tipo_servico_canonico')
            ? sige_fin_tipo_servico_canonico($servico_obj->tipo ?? '')
            : strtolower(trim((string)($servico_obj->tipo ?? '')));
        $is_recorrente_tipo = in_array($tipo_srv, ['mensalidade', 'transporte'], true);

        if ($is_recorrente_tipo) {
            $tipos_equiv = function_exists('sige_fin_tipos_equivalentes') ? sige_fin_tipos_equivalentes($tipo_srv) : [$tipo_srv];
            $tipos_equiv = array_values(array_unique(array_filter($tipos_equiv)));
            $tipo_placeholders = implode(',', array_fill(0, count($tipos_equiv), '%s'));
            $params_dup = array_merge([sige_get_escola_id(), $aluno_id, $mes_ref], $tipos_equiv);
            $existe = $wpdb->get_row($wpdb->prepare(
                "SELECT l.* FROM $tL l
                 JOIN $tS s ON s.id = l.servico_id AND s.escola_id = l.escola_id
                 WHERE l.escola_id=%d AND l.aluno_id=%d AND l.mes_referencia=%s
                   AND LOWER(TRIM(COALESCE(s.tipo,''))) IN ({$tipo_placeholders})
                 LIMIT 1",
                $params_dup
            ));

            // [FIX V71/B03] Se encontrou com servico_id diferente, só sincronizar lançamentos abertos.
            // Lançamentos pagos/isentos/cancelados/em_plano são histórico e NÃO podem ter serviço/descrição alterados.
            if ($existe && (int)$existe->servico_id !== $servico_id) {
                $status_existente = strtolower(trim((string)($existe->status ?? '')));

                if (in_array($status_existente, ['pendente', 'parcial'], true)) {
                    $wpdb->update($tL, [
                        'servico_id' => $servico_id,
                        'descricao'  => $descricao,
                    ], ['id' => (int)$existe->id]);

                    if (function_exists('sige_fin_log')) {
                        sige_fin_log('upsert_servico_corrigido', [
                            'lancamento_id' => (int)$existe->id,
                            'servico_antigo' => (int)$existe->servico_id,
                            'servico_novo'   => $servico_id,
                            'aluno_id'       => $aluno_id,
                            'mes_ref'        => $mes_ref,
                        ]);
                    }
                } else {
                    if (function_exists('sige_fin_log')) {
                        sige_fin_log('upsert_servico_nao_corrigido_historico', [
                            'lancamento_id' => (int)$existe->id,
                            'status'        => $status_existente,
                            'servico_antigo' => (int)$existe->servico_id,
                            'servico_novo'   => $servico_id,
                            'aluno_id'       => $aluno_id,
                            'mes_ref'        => $mes_ref,
                            'motivo'         => 'Protecção B03: lançamento histórico não pode ser alterado.',
                        ]);
                    }
                }
            }
        }
    }



    // [FIX UPSERT-01] Tratamento diferenciado por status

    if ($existe) {

        // Pago ou Isento → para mensalidades/transporte, não recriar.
        // Para avulsos (uniformes, material, etc.), permitir nova compra com mes_ref único.

        if (in_array($existe->status, ['pago', 'isento'], true)) {

            $is_recorrente = sige_servico_eh_recorrente($servico_obj);

            if ($is_recorrente) {
                return ['acao' => 'ignorado_pago', 'lanc_id' => (int)$existe->id];
            }

            // [FIX #7-CORE] Avulso já pago → gerar mes_ref único e criar novo lançamento.
            // Conta quantos lançamentos este serviço já tem para este aluno e usa como sufixo.
            $_n = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $tL WHERE aluno_id=%d AND servico_id=%d AND escola_id=%d",
                $aluno_id, $servico_id, sige_get_escola_id()
            ));
            $mes_ref = $mes_ref . '-' . ($_n + 1);
            $existe = null; // forçar INSERT com novo mes_ref

        }

        // Em Plano → lançamento congelado pelo tesoureiro, não pode ser substituído
        if ($existe->status === 'em_plano') {
            return ['acao' => 'em_plano', 'lanc_id' => (int)$existe->id];
        }

        

        // [FIX UPSERT-02] Cancelado → REACTIVAR o lançamento (a escola quer relançar este mês)

        if ($existe->status === 'cancelado') {

            $desconto = 0.0;

            if (function_exists('sige_fin_calcular_desconto')) {

                $desconto = (float)sige_fin_calcular_desconto($aluno_id, $config_fin, $servico_obj, [

                    'valor_original' => $valor_original,

                ]);

            }

            

            // [FIX DESC-ESP-01] Desconto especial é atributo da transacção, não do aluno-mês.
            // Se o lançamento foi cancelado, o desconto morre com ele - precisa ser reaplicado manualmente.
            $dados_reabrir_lancamento = [

                'descricao'                => $descricao,

                'valor_original'           => $valor_original,

                'valor_desconto'           => $desconto,

                'valor_pago'               => 0.00,

                'valor_multa'              => 0.00,

                'valor_desconto_especial'  => 0.00,

                'motivo_desconto_especial' => null,

                'desconto_especial_por'    => null,

                'data_vencimento'          => $data_vencimento,

                'status'                   => 'pendente',

                'cancelado_em'             => null,

                'cancelado_por'            => null,

                'motivo_cancelamento'      => null,

            ];

            // [12.6.1] Compatibilidade de schema: remover campos que não existem antes do UPDATE.
            foreach (array_keys($dados_reabrir_lancamento) as $_sige_col) {
                $col_ok = function_exists('sige_db_column_exists')
                    ? sige_db_column_exists($tL, $_sige_col)
                    : (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tL} LIKE %s", $_sige_col));
                if (!$col_ok) {
                    unset($dados_reabrir_lancamento[$_sige_col]);
                }
            }

            $wpdb->update($tL, $dados_reabrir_lancamento, ['id' => (int)$existe->id]);

            

            sige_fin_recalcular_e_sync((int)$existe->id);

            return ['acao' => 'reactivado', 'lanc_id' => (int)$existe->id];

        }

    }



    // Desconto



    $desconto = 0.0;



    if (function_exists('sige_fin_calcular_desconto')) {



        $desconto = (float)sige_fin_calcular_desconto($aluno_id, $config_fin, $servico_obj, [



            'valor_original' => $valor_original,



        ]);



    }



    if (!$existe) {



        $ok = $wpdb->insert($tL, [

            'escola_id'        => sige_get_escola_id(),

            'aluno_id'         => $aluno_id,

            'servico_id'       => $servico_id,



            'descricao'        => $descricao,



            'mes_referencia'   => $mes_ref,



            'valor_original'   => $valor_original,



            'valor_transporte' => 0.00,   // itens de transporte são lançamentos separados



            'valor_extras'     => 0.00,   // idem para extras



            'valor_multa'      => 0.00,



            'valor_desconto'   => $desconto,



            'valor_pago'       => 0.00,



            'data_vencimento'  => $data_vencimento,



            'status'           => 'pendente',



            'data_criacao'     => current_time('mysql'),



        ]);



        if (!$ok) return ['acao' => 'erro_insert', 'lanc_id' => 0, 'db_error' => $wpdb->last_error, 'mes_ref_tentado' => $mes_ref];



        $lanc_id = (int)$wpdb->insert_id;



        sige_fin_recalcular_e_sync($lanc_id);

        if (function_exists('sige_ledger_record_charge')) {
            sige_ledger_record_charge('fin_criar_lancamento', 'lancamento', $lanc_id, (float)$valor_original, ['aluno_id' => $aluno_id, 'servico_id' => $servico_id, 'mes' => $mes_ref], (int)sige_get_escola_id());
        }
        return ['acao' => 'criado', 'lanc_id' => $lanc_id];



    }



    if (in_array($existe->status, ['pendente', 'parcial'], true)) {



        // [FASE1-B01] Inclui servico_id no UPDATE para corrigir serviço quando muda
        $wpdb->update($tL, [
            'servico_id'      => $servico_id,
            'descricao'       => $descricao,
            'valor_original'  => $valor_original,
            'valor_desconto'  => $desconto,
            'data_vencimento' => $data_vencimento,
        ], ['id' => (int)$existe->id]);

        sige_fin_recalcular_e_sync((int)$existe->id);



        if (function_exists('sige_ledger_record_charge')) {
            sige_ledger_record_charge('fin_actualizar_lancamento', 'lancamento', (int)$existe->id, (float)$valor_original, ['aluno_id' => $aluno_id, 'servico_id' => $servico_id, 'mes' => $mes_ref, 'tipo' => 'valor'], (int)sige_get_escola_id());
        }
        return ['acao' => 'actualizado', 'lanc_id' => (int)$existe->id];



    }



    return ['acao' => 'ignorado_existente', 'lanc_id' => (int)$existe->id];



}



// ==========================================



// [INOVAÇÃO] HELPERS DE TRANSPORTE



// ==========================================



function sige_transporte_get_rota_aluno(int $aluno_id) {



    global $wpdb;



    $tA = $wpdb->prefix . 'sige_alunos';



    $tR = $wpdb->prefix . 'sige_transporte_rotas';



    $rota_id = (int)$wpdb->get_var($wpdb->prepare("SELECT rota_transporte_id FROM $tA WHERE id=%d", $aluno_id));



    if ($rota_id <= 0) return null;



    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $tR WHERE id=%d AND ativo=1", $rota_id));



}



function sige_transporte_get_preco_mensal(int $aluno_id): float {



    $rota = sige_transporte_get_rota_aluno($aluno_id);



    return ($rota && isset($rota->preco_mensal)) ? (float)$rota->preco_mensal : 0.00;



}



// ==========================================
// SERVIÇO TRANSPORTE - Get or Create
// ==========================================

if (!function_exists('sige_fin_get_ou_criar_servico_transporte')) {
 function sige_fin_get_ou_criar_servico_transporte($eid = 0) {
 global $wpdb;
 $tS = $wpdb->prefix . 'sige_fin_servicos';
 if (!$eid) $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
 if ($eid <= 0) { return null; }
 $srv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tS WHERE ativo=1 AND escola_id=%d AND LOWER(tipo)='transporte' LIMIT 1", $eid));
 if ($srv) {
 // v12.9.81 - migração leve e única: em instalações antigas, o serviço
 // de transporte podia existir com multa desligada por defeito. Activamos
 // apenas uma vez para garantir o comportamento esperado; depois disso,
 // a escola mantém liberdade para desligar/ajustar em Configuração de Preços.
 $flag_opt = 'sige_fin_transporte_regras_v12981_e' . (int)$eid;
 if (function_exists('get_option') && function_exists('update_option') && (int)get_option($flag_opt, 0) !== 1) {
 $upd = [];
 if (isset($srv->aplica_multa) && (int)$srv->aplica_multa === 0) $upd['aplica_multa'] = 1;
 if (function_exists('sige_db_column_exists')) {
 if (sige_db_column_exists($tS, 'tipo_multa') && empty($srv->tipo_multa)) $upd['tipo_multa'] = 'percentual';
 if (sige_db_column_exists($tS, 'valor_multa') && ($srv->valor_multa === null || $srv->valor_multa === '')) $upd['valor_multa'] = 0.00;
 if (sige_db_column_exists($tS, 'dia_vencimento') && empty($srv->dia_vencimento)) $upd['dia_vencimento'] = 10;
 }
 if (!empty($upd)) {
 $wpdb->update($tS, $upd, ['id' => (int)$srv->id]);
 $srv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tS WHERE id=%d LIMIT 1", (int)$srv->id));
 }
 update_option($flag_opt, 1, false);
 }
 return $srv;
 }

 // v12.9.81 - o valor do transporte vem da rota do aluno, mas as regras
 // financeiras (multa/descontos) pertencem ao serviço "Transporte Escolar".
 // Por defeito, transporte deve poder receber multa global; a escola pode
 // desligar/ajustar isto em Configuração de Preços.
 $dados = [
 'nome' => 'Transporte Escolar',
 'tipo' => 'transporte',
 'valor' => 0.00,
 'classe' => 'todas',
 'categoria' => 'fixo',
 'aplica_multa' => 1,
 'aplica_desconto' => 0,
 'ativo' => 1,
 'escola_id' => $eid,
 ];

 if (function_exists('sige_db_column_exists')) {
 if (sige_db_column_exists($tS, 'aplica_desc_pronto')) $dados['aplica_desc_pronto'] = 0;
 if (sige_db_column_exists($tS, 'aplica_desc_func')) $dados['aplica_desc_func'] = 0;
 if (sige_db_column_exists($tS, 'tipo_multa')) $dados['tipo_multa'] = 'percentual';
 if (sige_db_column_exists($tS, 'valor_multa')) $dados['valor_multa'] = 0.00;
 if (sige_db_column_exists($tS, 'dia_vencimento')) $dados['dia_vencimento'] = 10;
 if (sige_db_column_exists($tS, 'centro_id')) {
 $dados['centro_id'] = function_exists('sige_fin_get_centro_default_id') ? (int)sige_fin_get_centro_default_id() : 0;
 }
 } else {
 $dados['aplica_desc_pronto'] = 0;
 $dados['aplica_desc_func'] = 0;
 $dados['tipo_multa'] = 'percentual';
 $dados['valor_multa'] = 0.00;
 $dados['dia_vencimento'] = 10;
 if (function_exists('sige_fin_get_centro_default_id')) $dados['centro_id'] = (int)sige_fin_get_centro_default_id();
 }

 $wpdb->insert($tS, $dados);
 return $wpdb->get_row($wpdb->prepare("SELECT * FROM $tS WHERE id=%d LIMIT 1", $wpdb->insert_id));
 }
}

// ==========================================



// CÁLCULOS FINANCEIROS



// ==========================================



function sige_fin_calcular_multa(array $lanc, $config, $servico): float {



    if (isset($servico->aplica_multa) && (int)$servico->aplica_multa === 0) return 0.0;



    $hoje = current_time('Y-m-d');



    $venc = $lanc['data_vencimento'] ?? null;



    if (!$venc) return 0.0;



    $dias = (int)floor((strtotime($hoje) - strtotime($venc)) / 86400);



    if ($dias <= 0) return 0.0;



    $valor_base = (float)($lanc['valor_original'] ?? 0);



    // ── Override por serviço (se definido e > 0, prevalece sobre a config global) ──



    $tipo_srv = strtolower((string)($servico->tipo_multa ?? 'percentual'));



    $val_srv  = (float)($servico->valor_multa ?? 0);



    if ($val_srv > 0) {



        // Respeita o dias_para_multa do tier 1 como tolerância mínima



        $tolerancia = (int)($config->multa_tier1_dias ?? $config->dias_para_multa ?? 10);



        if ($dias < $tolerancia) return 0.0; // [FIX M-01] >= ao invés de >



        if ($tipo_srv === 'fixa') return max(0.0, $val_srv);



        return max(0.0, $valor_base * ($val_srv / 100.0)); // percentual



    }



    // ── Tiers globais escalados (configurados na página de Config Financeiro) ──



    // Lê da tabela sige_fin_configuracoes. Defaults seguros caso não estejam definidos.



    $t1_dias = (int)($config->multa_tier1_dias ?? 10);



    $t1_pct  = (float)($config->multa_tier1_pct  ?? 0);



    $t2_dias = (int)($config->multa_tier2_dias ?? 30);



    $t2_pct  = (float)($config->multa_tier2_pct  ?? 0);



    $t3_dias = (int)($config->multa_tier3_dias ?? 60);



    $t3_pct  = (float)($config->multa_tier3_pct  ?? 0);



    // Aplica o tier mais alto que o atraso justifique



    if ($t3_pct > 0 && $dias >= $t3_dias) {



        return max(0.0, $valor_base * ($t3_pct / 100.0));



    }



    if ($t2_pct > 0 && $dias >= $t2_dias) {



        return max(0.0, $valor_base * ($t2_pct / 100.0));



    }



    if ($t1_pct > 0 && $dias >= $t1_dias) {



        return max(0.0, $valor_base * ($t1_pct / 100.0));



    }



    return 0.0;



}



function sige_fin_calcular_desconto(int $aluno_id, $config, $servico, array $contexto = []): float {

    global $wpdb;

    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT tem_desconto_irmao, tem_desconto_funcionario
           FROM {$wpdb->prefix}sige_alunos
          WHERE id = %d AND escola_id = %d",
        $aluno_id, sige_get_escola_id()
    ));

    if (!$aluno) return 0.0;

    $valor_original = (float)($contexto['valor_original'] ?? ($servico->valor ?? 0));
    if ($valor_original <= 0) return 0.0;

    // Regra: um desconto por lançamento. Se irmão e funcionário forem elegíveis,
    // aplica-se apenas o maior benefício, mantendo a cadeia financeira sem acumulação indevida.
    $cfg_get = static function ($obj, string $prop, $default = null) {
        if (is_object($obj) && property_exists($obj, $prop)) return $obj->{$prop};
        if (is_array($obj) && array_key_exists($prop, $obj)) return $obj[$prop];
        return $default;
    };

    $normalizar_tipo = static function ($tipo): string {
        $tipo = strtolower(trim((string)$tipo));
        return in_array($tipo, ['percentual', 'percentagem', 'pct', '%'], true) ? 'percentual' : 'mt';
    };

    $calcular_valor = static function (float $valor_config, string $tipo) use ($valor_original): float {
        if ($valor_config <= 0) return 0.0;
        if ($tipo === 'percentual') {
            $pct = min(100.0, max(0.0, $valor_config));
            return max(0.0, min($valor_original, $valor_original * ($pct / 100.0)));
        }
        return max(0.0, min($valor_original, $valor_config));
    };

    $elegiveis = [];

    if (
        (int)($servico->aplica_desc_func ?? 0) === 1 &&
        (int)($aluno->tem_desconto_funcionario ?? 0) === 1
    ) {
        $tipo = $normalizar_tipo($cfg_get($config, 'desconto_funcionario_tipo', 'mt'));
        $base = ($tipo === 'percentual')
            ? (float)$cfg_get($config, 'desconto_funcionario_percentual', 0)
            : (float)$cfg_get($config, 'desconto_funcionario_mt', $cfg_get($config, 'desconto_funcionario_percentual', 0));
        $val = $calcular_valor($base, $tipo);
        if ($val > 0) $elegiveis['funcionario'] = $val;
    }

    if (
        (int)($servico->aplica_desconto ?? 0) === 1 &&
        (int)($aluno->tem_desconto_irmao ?? 0) === 1
    ) {
        $tipo = $normalizar_tipo($cfg_get($config, 'desconto_irmaos_tipo', 'mt'));
        $base = ($tipo === 'percentual')
            ? (float)$cfg_get($config, 'desconto_irmaos_pct', 0)
            : (float)$cfg_get($config, 'desconto_irmaos_mt', $cfg_get($config, 'desconto_irmaos_pct', 0));
        $val = $calcular_valor($base, $tipo);
        if ($val > 0) $elegiveis['irmao'] = $val;
    }

    if (empty($elegiveis)) return 0.0;

    return max(0.0, max($elegiveis));

}



// ==========================================



// CRÉDITOS (ADIANTAMENTOS) - PENDENTE DE CONFIRMAÇÃO



// ==========================================



function sige_fin_creditos_table(): string {



    global $wpdb;



    return $wpdb->prefix . 'sige_fin_creditos';



}



function sige_fin_creditos_ensure_table(): void {



    global $wpdb;



    $t = sige_fin_creditos_table();



    $existe = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));



    if (!$existe) return;



}



function sige_fin_criar_credito_pendente(int $aluno_id, float $valor, array $meta = []): ?int {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_fin_criar_credito_pendente')) { return null; }



    global $wpdb;



    sige_fin_creditos_ensure_table();



    $t = sige_fin_creditos_table();



    $existe = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));



    if (!$existe) return null;



    $valor = (float)$valor;



    if ($valor <= 0) return null;



    $ok = $wpdb->insert($t, [
        'escola_id' => sige_get_escola_id(),



        'aluno_id'         => $aluno_id,



        'valor_total'      => $valor,



        'valor_disponivel' => $valor,



        'status'           => 'pendente',



        'meta'             => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),



        'criado_por'       => get_current_user_id(),



        'criado_em'        => current_time('mysql'),



        'aprovado_por'     => null,



        'aprovado_em'      => null,



    ]);



    if (!$ok) return null;



    $sige_credito_id = (int)$wpdb->insert_id;
    if (function_exists('sige_ledger_append')) {
        sige_ledger_append('fin_criar_credito', 'credito', $sige_credito_id, (float)$valor, ['aluno_id' => $aluno_id], (int)sige_get_escola_id());
    }
    return $sige_credito_id;



}



// ==========================================



// [INOVAÇÃO] RECÁLCULO (MENSALIDADE + TRANSPORTE + EXTRAS)



// ==========================================



function sige_fin_recalcular_lancamento(int $lancamento_id, bool $forcar_recalculo = false): bool {



    global $wpdb;



    $tL = $wpdb->prefix . 'sige_fin_lancamentos';



    // (v11.0) Colunas valor_transporte e valor_extras garantidas por class-sige-migration.php (M3)



    $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tL WHERE id=%d", $lancamento_id), ARRAY_A);



    if (!$l) return false;



    // [FIX ESTORNO-04] Só congelar lançamentos pagos se não for forçado recálculo

    // Isto permite que estornos forcem actualização de multas/descontos

    if ($l['status'] === 'pago' && !$forcar_recalculo) return true;



    $config  = sige_fin_get_config(sige_fin_get_ano_letivo_master());



    $servico = sige_fin_obter_servico((int)$l['servico_id']);



    if (!$servico) return false;



    $multa    = sige_fin_calcular_multa($l, $config, $servico);



    $desconto = sige_fin_calcular_desconto((int)$l['aluno_id'], $config, $servico, ['valor_original' => (float)$l['valor_original']]);



    // valor_transporte e valor_extras são sempre 0.00 nesta função.



    // O modelo actual cria lançamentos SEPARADOS para transporte e cada extra



    // (via Modo 2 do gerador ou módulo de pagamentos).



    // O bloco antigo que embutia estes valores na mensalidade foi removido



    // para evitar dupla cobrança com os lançamentos separados.



    $wpdb->update($tL, [



        'valor_multa'      => (float)$multa,



        'valor_desconto'   => (float)$desconto,



        'valor_transporte' => 0.00,



        'valor_extras'     => 0.00,



    ], ['id' => $lancamento_id]);



    return true;



}



// ==========================================
// [PERFORMANCE BASE v2] RECÁLCULO COM THROTTLE
// Evita recalcular o mesmo lançamento várias vezes durante navegação normal.
// O recálculo forçado continua disponível para pagamento, estorno e acções críticas.
// ==========================================
if (!function_exists('sige_fin_recalcular_lancamento_throttled')) {
    function sige_fin_recalcular_lancamento_throttled(int $lancamento_id, bool $forcar_recalculo = false, int $ttl = 1800): bool {
        if ($lancamento_id <= 0) return false;
        if ($forcar_recalculo || (defined('SIGE_DISABLE_RECALC_THROTTLE') && SIGE_DISABLE_RECALC_THROTTLE)) {
            return sige_fin_recalcular_lancamento($lancamento_id, $forcar_recalculo);
        }

        $key = 'sige_fin_recalc_' . (int)$lancamento_id;
        if (get_transient($key)) {
            return true;
        }

        $ok = sige_fin_recalcular_lancamento($lancamento_id, false);
        if ($ok && $ttl > 0) {
            set_transient($key, 1, $ttl);
        }
        return $ok;
    }
}

// ==========================================



// [INOVAÇÃO] STATUS COM EXTRAS E TOLERÂNCIA




// ==========================================



function sige_fin_atualizar_status_lancamento(int $lancamento_id): void {

    global $wpdb;

    $tL = $wpdb->prefix . 'sige_fin_lancamentos';

    $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tL WHERE id = %d", $lancamento_id));

    if (!$l) return;

    // Não alterar status de lançamentos cancelados ou isentos -
    // estes são definidos manualmente e têm precedência sobre recálculo automático.
    if (in_array($l->status, ['cancelado', 'isento'], true)) {
        return;
    }

    // Fórmula: (Original + Transporte + Extras + Multa) − Desconto − Desconto Especial
    $original   = (float)$l->valor_original;
    $transporte = (float)($l->valor_transporte ?? 0);
    $extras     = (float)($l->valor_extras ?? 0);
    $multa      = (float)($l->valor_multa ?? 0);
    $desconto   = (float)($l->valor_desconto ?? 0);
    $desc_esp   = (float)($l->valor_desconto_especial ?? 0);

    $total_devido = ($original + $transporte + $extras + $multa) - $desconto - $desc_esp;
    $pago         = (float)$l->valor_pago;

    // Determinação do novo status
    if ($pago <= 0.0) {
        $novo = 'pendente';
    } elseif ($pago + 0.5 < $total_devido) {
        $novo = 'parcial'; // tolerância de 0.50 MT para arredondamentos
    } else {
        $novo = 'pago';
    }

    // em_plano: lançamento congelado por acordo negociado.
    // $novo já calculado - só transita para 'pago', nunca volta a pendente/parcial.
    if ($l->status === 'em_plano') {
        if ($novo === 'pago') {
            $wpdb->update($tL, ['status' => 'pago'], ['id' => $lancamento_id]);
            if ((float)$l->valor_multa > 0 && (float)($l->valor_multa_cobrada ?? 0) <= 0) {
                $wpdb->update($tL, ['valor_multa_cobrada' => (float)$l->valor_multa], ['id' => $lancamento_id]);
            }
        }
        return; // qualquer outro estado → manter em_plano (não deixa voltar a pendente/parcial)
    }

    if ($novo !== $l->status) {
        $wpdb->update($tL, ['status' => $novo], ['id' => $lancamento_id]);
    }

    // ── Preservação do histórico de multa ───────────────────────────────────
    //
    // VERSÃO ANTERIOR (BUG INTEG-03):
    //   Zerávamos valor_multa = 0.00 ao pagar para limpar a UI.
    //   Consequência: histórico de multas cobradas destruído permanentemente.
    //
    // VERSÃO CORRIGIDA:
    //   Gravamos valor_multa_cobrada (coluna de auditoria imutável).
    //   valor_multa mantém-se intacto para relatórios.
    //   A UI verifica status='pago' para decidir o que mostrar ao operador.
    // ────────────────────────────────────────────────────────────────────────

    if ($novo === 'pago' && (float)$l->valor_multa > 0) {
        $ja_gravado = (float)($l->valor_multa_cobrada ?? 0);
        if ($ja_gravado <= 0) {
            $wpdb->update(
                $tL,
                ['valor_multa_cobrada' => (float)$l->valor_multa],
                ['id' => $lancamento_id]
            );
        }
        // valor_multa NÃO é zerado - preservado para relatórios financeiros.
    }

}



// ==========================================
// [FASE1-P11] WRAPPER RECALC + SYNC
// Garante que recalcular e atualizar_status são SEMPRE chamados juntos.
// Usar esta função em vez de chamar recalcular sozinho.
// ==========================================

function sige_fin_recalcular_e_sync(int $lancamento_id, bool $forcar = false): void {
    if (function_exists('sige_fin_recalcular_lancamento')) {
        sige_fin_recalcular_lancamento($lancamento_id, $forcar);
    }
    if (function_exists('sige_fin_atualizar_status_lancamento')) {
        sige_fin_atualizar_status_lancamento($lancamento_id);
    }
}

// ==========================================
// [FASE1-B02] MÁQUINA DE ESTADOS (FSM)
// Valida transições de status e loga todas as mudanças.
// ==========================================

/**
 * Transita o status de um lançamento com validação.
 *
 * @param int    $lancamento_id  ID do lançamento.
 * @param string $novo_status    Status destino.
 * @param string $motivo         Motivo da transição (para audit trail).
 * @return bool  true se transitou, false se bloqueado.
 */
function sige_fin_transitar_status(int $lancamento_id, string $novo_status, string $motivo = ''): bool {
    global $wpdb;

    static $transicoes = [
        'pendente'  => ['parcial', 'pago', 'cancelado', 'em_plano', 'isento'],
        'parcial'   => ['pago', 'cancelado', 'em_plano', 'pendente'],
        'pago'      => ['parcial', 'pendente'],  // apenas via estorno
        'cancelado' => ['pendente'],              // reactivação
        'em_plano'  => ['pendente', 'parcial', 'pago', 'cancelado'],
        'isento'    => ['cancelado', 'pendente'],
    ];

    $tL = $wpdb->prefix . 'sige_fin_lancamentos';
    $l = $wpdb->get_row($wpdb->prepare("SELECT status FROM $tL WHERE id=%d", $lancamento_id));
    if (!$l) return false;

    $actual = $l->status;
    $permitidos = $transicoes[$actual] ?? [];

    if (!in_array($novo_status, $permitidos, true)) {
        if (function_exists('sige_fin_log')) {
            sige_fin_log('transicao_bloqueada', [
                'lancamento_id' => $lancamento_id,
                'de'            => $actual,
                'para'          => $novo_status,
                'motivo'        => $motivo,
            ]);
        }
        return false;
    }

    $wpdb->update($tL, ['status' => $novo_status], ['id' => $lancamento_id]);

    if (function_exists('sige_fin_log')) {
        sige_fin_log('transicao_status', [
            'lancamento_id' => $lancamento_id,
            'de'            => $actual,
            'para'          => $novo_status,
            'motivo'        => $motivo,
            'user_id'       => get_current_user_id(),
        ]);
    }
    return true;
}

// ── [B03] Guard: impedir modificação de lançamentos pagos ─────────────────
// Verifica se um lançamento pode ser modificado (valor, desconto, etc.).
// Retorna true se modificação é permitida, WP_Error se bloqueada.
// Uso: chamar antes de qualquer UPDATE em campos financeiros de lançamentos.
// Excepção: estorno (que muda valor_pago para reverter) e transitar_status.

if (!function_exists('sige_fin_assert_modificavel')) {
    function sige_fin_assert_modificavel(int $lancamento_id, string $operacao = ''): bool|WP_Error {
        global $wpdb;
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $l = $wpdb->get_row($wpdb->prepare("SELECT status, valor_pago FROM $tL WHERE id=%d", $lancamento_id));
        if (!$l) {
            return new WP_Error('nao_encontrado', "Lançamento #{$lancamento_id} não encontrado.");
        }
        $status = strtolower((string) $l->status);
        $pago = (float) $l->valor_pago;

        // Lançamentos pagos só podem ser alterados via estorno
        if ($status === 'pago') {
            sige_fin_log('modificacao_bloqueada', [
                'lancamento_id' => $lancamento_id,
                'status'        => $status,
                'operacao'      => $operacao,
                'user_id'       => get_current_user_id(),
            ]);
            return new WP_Error('lancamento_pago',
                'Lançamento #' . $lancamento_id . ' está pago e não pode ser alterado. Use o estorno para reverter o pagamento primeiro.'
            );
        }

        // Lançamentos com pagamento parcial: permitir com aviso no log
        if ($pago > 0 && $status === 'parcial') {
            sige_fin_log('modificacao_parcial_aviso', [
                'lancamento_id' => $lancamento_id,
                'valor_pago'    => $pago,
                'operacao'      => $operacao,
            ]);
        }

        return true;
    }
}

// ==========================================

// REGISTAR PAGAMENTO

// ==========================================



function sige_fin_registar_pagamento(
    int     $lancamento_id,
    float   $valor_pago,
    string  $metodo,
    ?string $referencia_externa = null,
    bool    $multa_isenta       = false,
    ?string $motivo_isencao     = null,
    ?string $recibo_numero      = null
) {
    global $wpdb;

    $tL = $wpdb->prefix . 'sige_fin_lancamentos';
    $tP = $wpdb->prefix . 'sige_fin_pagamentos';
    $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';

    // ── Validações pré-transação ─────────────────────────────────────────────

    if ($valor_pago <= 0) {
        return new WP_Error('valor_invalido', 'Valor inválido: deve ser superior a zero.');
    }

    // Verificar Fecho de Caixa
    $data_caixa = current_time('Y-m-d');
    $fechado = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM $tF WHERE escola_id = %d AND data_caixa = %s AND status = 'fechado'",
        sige_get_escola_id(), $data_caixa
    ));
    if ($fechado > 0) {
        return new WP_Error(
            'caixa_fechado',
            'A caixa do dia já foi encerrada. Contacte a direcção para reabertura.'
        );
    }

    // Recalcular multas/descontos com dados actuais antes de processar
    sige_fin_recalcular_lancamento($lancamento_id);

    $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tL WHERE id = %d", $lancamento_id));
    if (!$l) {
        return new WP_Error('nao_encontrado', 'Lançamento #' . $lancamento_id . ' não encontrado.');
    }
    // em_plano: só o módulo de planos pode processar pagamentos neste lançamento.
    // Pagamento directo via tesouraria bloqueado para evitar dupla cobrança.
    // Excepção: se chamado com recibo PLN- (vem do próprio módulo de planos).
    if ($l->status === 'em_plano' && !str_starts_with((string)($recibo_numero ?? ''), 'PLN')) {
        return new WP_Error(
            'em_plano',
            'Este lançamento está integrado num plano de pagamento negociado. ' .
            'Processe o pagamento em Planos de Pagamento.'
        );
    }

    // Cálculo do valor em dívida
    $transporte = (float)($l->valor_transporte ?? 0);
    $extras     = (float)($l->valor_extras ?? 0);
    $multa      = $multa_isenta ? 0.0 : (float)$l->valor_multa;
    $total      = ((float)$l->valor_original + $transporte + $extras + $multa)
                - (float)$l->valor_desconto
                - (float)($l->valor_desconto_especial ?? 0);
    $restante   = max(0.0, $total - (float)$l->valor_pago);

    // Verificar autorização para pagamento parcial
    $config          = sige_fin_get_config(sige_fin_get_ano_letivo_master());
    $permite_parcial = (int)($config->permitir_pagamento_parcial ?? 1) === 1;

    if (!$permite_parcial && $valor_pago + 0.5 < $restante) {
        return new WP_Error(
            'parcial_nao_permitido',
            sprintf(
                'Pagamento parcial não permitido. Valor total em dívida: %s MT.',
                number_format($restante, 2, ',', '.')
            )
        );
    }

    $aplicar   = min($valor_pago, $restante);
    $excedente = max(0.0, $valor_pago - $aplicar);
    $recibo    = $recibo_numero ?: sige_fin_gerar_recibo_numero('REC');

    // Observação automática
    $obs_partes = [];
    if ($transporte > 0) $obs_partes[] = 'Transp: ' . number_format($transporte, 0) . ' ' . sige_moeda();
    if ($extras > 0)     $obs_partes[] = 'Extras: ' . number_format($extras, 0) . ' ' . sige_moeda();
    $obs_str = implode(', ', $obs_partes);

    // ── TRANSAÇÃO SQL ────────────────────────────────────────────────────────
    //
    // As 3 escritas abaixo são atómicas: se qualquer uma falhar,
    // todas são revertidas (ROLLBACK). Sem transação, um crash entre
    // escritas deixaria o sistema em estado financeiro inconsistente.
    // ────────────────────────────────────────────────────────────────────────

    $wpdb->query('START TRANSACTION');

    // Escrita 1 - Registar pagamento
    $ok = $wpdb->insert($tP, [
        'escola_id'          => (int)($l->escola_id ?: sige_get_escola_id()),
        'lancamento_id'      => $lancamento_id,
        'aluno_id'           => (int)$l->aluno_id,
        'recibo_numero'      => $recibo,
        'valor_pago'         => (float)$aplicar,
        'metodo_pagamento'   => sanitize_text_field($metodo),
        'referencia_externa' => $referencia_externa ? sanitize_text_field($referencia_externa) : null,
        'recebido_por'       => get_current_user_id(),
        'data_pagamento'     => current_time('mysql'),
        'multa_isenta'       => $multa_isenta ? 1 : 0,
        'motivo_isencao'     => $motivo_isencao ? sanitize_text_field($motivo_isencao) : null,
        'observacoes'        => $obs_str,
    ]);

    if (!$ok) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('sql_pagamento', 'Erro ao registar pagamento: ' . $wpdb->last_error);
    }

    $pagamento_id = (int)$wpdb->insert_id;

    // Escrita 2 - Acumular valor pago no lançamento
    $upd = $wpdb->query($wpdb->prepare(
        "UPDATE $tL SET valor_pago = valor_pago + %f WHERE id = %d",
        $aplicar,
        $lancamento_id
    ));

    if ($upd === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('sql_lancamento', 'Erro ao actualizar lançamento: ' . $wpdb->last_error);
    }

    // Escrita 3 - Isenção de multa (se solicitada pelo operador)
    if ($multa_isenta) {
        $upd3 = $wpdb->update($tL, ['valor_multa' => 0.0], ['id' => $lancamento_id]);
        if ($upd3 === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('sql_isencao', 'Erro ao aplicar isenção de multa: ' . $wpdb->last_error);
        }
    }

    $wpdb->query('COMMIT');

    // ── Pós-transação ────────────────────────────────────────────────────────

    // Actualizar status (leitura + escrita independente - fora da transação)
    sige_fin_atualizar_status_lancamento($lancamento_id);

    // Crédito por excedente (auxiliar - falha é logada mas não reverte o pagamento)
    $credito_id = null;
    if ($excedente > 0.0) {
        $credito_id = sige_fin_criar_credito_pendente((int)$l->aluno_id, $excedente, [
            'origem'        => 'pagamento_excedente',
            'lancamento_id' => $lancamento_id,
            'valor_pago'    => (float)$valor_pago,
            'aplicado'      => (float)$aplicar,
            'excedente'     => (float)$excedente,
            'criado_em'     => current_time('mysql'),
        ]);

        if (!$credito_id) {
            error_log(sprintf(
                '[SIGE FIN] AVISO: Excedente de %.2f ' . sige_moeda() . ' do aluno %d (lançamento %d) não foi creditado. Verificar manualmente.',
                $excedente, (int)$l->aluno_id, $lancamento_id
            ));
        }
    }

    // Log de auditoria
    sige_fin_log('pagamento_registado', [
        'pagamento_id'   => $pagamento_id,
        'lancamento_id'  => $lancamento_id,
        'aluno_id'       => (int)$l->aluno_id,
        'valor_aplicado' => (float)$aplicar,
        'valor_enviado'  => (float)$valor_pago,
        'excedente'      => (float)$excedente,
        'recibo'         => $recibo,
        'metodo'         => $metodo,
        'multa_isenta'   => $multa_isenta,
        'credito_id'     => $credito_id,
    ]);

    // Auditoria - cada pagamento registado deixa rasto
    if ($pagamento_id && function_exists('sige_audit_log')) {
        sige_audit_log('registar_pagamento', [
            'lancamento_id' => $lancamento_id,
            'valor'         => $valor_pago,
            'recibo'        => $recibo,
            'metodo'        => $metodo,
        ], 'financeiro');
    }

    // Ledger imutavel (Fase 6 incr 2): regista o movimento de dinheiro. Cobre
    // tambem o pagamento anual, que delega nesta funcao. pagamento_id ja capturado.
    if ($pagamento_id && function_exists('sige_ledger_append')) {
        sige_ledger_append('fin_registar_pagamento', 'pagamento', $pagamento_id, (float)$aplicar, ['lancamento_id' => $lancamento_id, 'aluno_id' => (int)$l->aluno_id, 'metodo' => $metodo, 'referencia' => $referencia_externa], (int)($l->escola_id ?: sige_get_escola_id()));
    }

    return $pagamento_id;

}



// ==========================================



// PAGAMENTO ANUAL



// ==========================================



function sige_fin_registar_pagamento_anual($aluno_id, $ano_letivo, $tipo, $pacote_id, $servicos, $meses, $v_orig, $desc, $v_final, $metodo, $ref, $recibo = null) {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_fin_registar_pagamento_anual')) { return new WP_Error('escola_invalida', 'Contexto de escola invalido.'); }



    // [FIX I-02] Esta função estava vazia (retornava 0 silenciosamente).



    // Implementação: gera um lançamento único de pagamento anual e regista o pagamento.



    // Se precisar de lançamentos mensais separados, usar sige_fin_upsert_lancamento em loop.



    global $wpdb;



    $tL = $wpdb->prefix . 'sige_fin_lancamentos';



    $tP = $wpdb->prefix . 'sige_fin_pagamentos';



    if ((float)$v_final <= 0 || !$aluno_id) {



        return new WP_Error('dados_invalidos', 'Valor ou aluno inválido para pagamento anual.');



    }



    // Verificar fecho de caixa



    $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';



    $data_caixa = current_time('Y-m-d');



    $fechado = (int)$wpdb->get_var($wpdb->prepare(

        "SELECT COUNT(1) FROM $tF WHERE escola_id = %d AND data_caixa = %s AND status = 'fechado'",

        sige_get_escola_id(), $data_caixa

    ));

    if ($fechado > 0) {

        return new WP_Error('caixa_fechado', 'Caixa do dia já foi fechado.');

    }



    $config     = sige_fin_get_config((int)$ano_letivo);



    $servico_id = is_array($servicos) ? (int)($servicos[0] ?? 0) : (int)$servicos;



    $servico    = $servico_id > 0 ? sige_fin_obter_servico($servico_id) : null;



    $desconto  = $servico ? (float)sige_fin_calcular_desconto((int)$aluno_id, $config, $servico, ['valor_original' => (float)$v_orig]) : 0.0;



    $mes_ref   = $ano_letivo . '-01'; // referência ao ano lectivo completo



    $data_venc = $ano_letivo . '-01-31';



    $descricao = $desc ?: ('Pagamento Anual ' . $tipo . ' ' . $ano_letivo);



    // Criar/actualizar lançamento anual



    $existe = $wpdb->get_row($wpdb->prepare(



        "SELECT * FROM $tL WHERE aluno_id=%d AND servico_id=%d AND mes_referencia=%s AND descricao LIKE %s LIMIT 1",



        (int)$aluno_id, $servico_id, $mes_ref, '%Anual%'



    ));



    if ($existe && in_array($existe->status, ['pago', 'cancelado', 'isento'], true)) {



        return new WP_Error('ja_pago', 'Pagamento anual já registado para este aluno/ano.');



    }



    if (!$existe) {



        $wpdb->insert($tL, [

            'escola_id'       => sige_get_escola_id(),

            'aluno_id'        => (int)$aluno_id,

            'servico_id'      => $servico_id,

            'descricao'       => sanitize_text_field($descricao),

            'mes_referencia'  => $mes_ref,

            'valor_original'  => (float)$v_orig,



            'valor_desconto'  => (float)$desconto,



            'valor_multa'     => 0.0,



            'valor_transporte'=> 0.0,



            'valor_extras'    => 0.0,



            'valor_pago'      => 0.0,



            'data_vencimento' => $data_venc,



            'status'          => 'pendente',



            'data_criacao'    => current_time('mysql'),



        ]);



        $lanc_id = (int)$wpdb->insert_id;



    } else {



        $lanc_id = (int)$existe->id;



    }



    if (!$lanc_id) {



        return new WP_Error('sql', 'Erro ao criar lançamento anual: ' . $wpdb->last_error);



    }



    // Registar pagamento (reutiliza a função principal)



    return sige_fin_registar_pagamento(



        $lanc_id,



        (float)$v_final,



        sanitize_text_field((string)$metodo),



        $ref ? sanitize_text_field((string)$ref) : null,



        false,  // multa_isenta



        null,



        $recibo ?: null



    );



}

// ─────────────────────────────────────────────────────────────────────────────

// AJAX: Carregar lançamentos pendentes de um aluno (formulário de Planos de Pagamento)

// ─────────────────────────────────────────────────────────────────────────────

add_action('wp_ajax_sige_planos_lancs_aluno', function() {

    if (!check_ajax_referer('sige_planos_nonce', '_nonce', false)) {
        wp_send_json_error(['msg' => 'Nonce inválido.']); return;
    }

    if (!((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') ||
          current_user_can('sige_financeiro') || current_user_can('sige_secretario'))) {
        wp_send_json_error(['msg' => 'Sem permissão.']); return;
    }

    global $wpdb;

    $escola_id = sige_get_escola_id();
    $aluno_id  = (int)($_POST['aluno_id'] ?? 0);

    if (!$aluno_id) {
        wp_send_json_error(['msg' => 'Aluno inválido.']); return;
    }

    $tL = $wpdb->prefix . 'sige_fin_lancamentos';
    $tS = $wpdb->prefix . 'sige_fin_servicos';

    // [FIX FORMULA-11] Usar expressão canónica sige_fin_saldo_sql()
    // ANTES: omitia valor_transporte, valor_extras e usava valor_multa directo
    $_saldo = sige_fin_saldo_sql('l');
    $lancs = $wpdb->get_results($wpdb->prepare(
        "SELECT l.id, l.descricao, l.mes_referencia, l.data_vencimento,
                l.valor_original, l.valor_multa, l.valor_desconto,
                COALESCE(l.valor_transporte, 0) AS valor_transporte,
                COALESCE(l.valor_extras, 0) AS valor_extras,
                COALESCE(l.valor_desconto_especial, 0) AS valor_desconto_especial,
                l.valor_pago, l.status, COALESCE(s.nome, l.descricao, 'Serviço eliminado') AS servico_nome,
                $_saldo AS restante
           FROM $tL l
           LEFT JOIN $tS s ON s.id = l.servico_id
          WHERE l.aluno_id = %d
            AND l.escola_id = %d
            AND l.status IN ('pendente', 'parcial')
          ORDER BY l.data_vencimento ASC",
        $aluno_id,
        $escola_id
    ));

    $total = 0.0;
    foreach ((array)$lancs as $l) {
        $total += (float)($l->restante ?? 0);
    }

    wp_send_json_success(['lancamentos' => $lancs, 'total' => $total]);

});

// ─────────────────────────────────────────────────────────────────────────────

// AJAX: Isentar mês inteiro de um aluno (mensalidade + transporte)

// ─────────────────────────────────────────────────────────────────────────────

add_action('wp_ajax_sige_isentar_mes', function() {

    sige_check_nonce_global();

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario')) {

        wp_send_json_error('Sem permissão.');

    }

    $aluno_id = (int)($_POST['aluno_id'] ?? 0);

    $mes_num  = (int)($_POST['mes_num'] ?? 0);

    $motivo   = sanitize_text_field($_POST['motivo'] ?? '');

    if (!$aluno_id || $mes_num < 1 || $mes_num > 12 || !$motivo) {

        wp_send_json_error('Dados inválidos.');

    }

    global $wpdb;

    $tL = $wpdb->prefix . 'sige_fin_lancamentos';

    $ano = function_exists('sige_fin_get_ano_letivo_master') ? sige_fin_get_ano_letivo_master() : wp_date('Y');

    $mes_ref = $ano . '-' . str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT);



    // Buscar colunas de cancelamento disponíveis

    $cols = array_column((array)$wpdb->get_results("SHOW COLUMNS FROM {$tL}"), 'Field');

    $has_cancel_em  = in_array('cancelado_em', $cols);

    $has_cancel_por = in_array('cancelado_por', $cols);

    $has_cancel_mot = in_array('motivo_cancelamento', $cols);



    // Buscar lançamentos pendentes deste aluno/mês

    $lancs = $wpdb->get_results($wpdb->prepare(

        "SELECT id FROM {$tL} WHERE escola_id=%d AND aluno_id=%d AND mes_referencia=%s AND status IN ('pendente','parcial','em_plano')",

        sige_get_escola_id(), $aluno_id, $mes_ref

    ));

    $count = 0;

    foreach ($lancs as $l) {

        $data = ['status' => 'isento'];

        if ($has_cancel_em)  $data['cancelado_em']  = current_time('mysql');

        if ($has_cancel_por) $data['cancelado_por'] = get_current_user_id();

        if ($has_cancel_mot) $data['motivo_cancelamento'] = 'ISENÇÃO: ' . $motivo;

        $wpdb->update($tL, $data, ['id' => (int)$l->id]);

        $count++;

    }



    // Se não existiam lançamentos, criar como isento

    if ($count === 0) {

        $classe = $wpdb->get_var($wpdb->prepare(

            "SELECT t.classe FROM {$wpdb->prefix}sige_matriculas m 

             JOIN {$wpdb->prefix}sige_turmas t ON t.id = m.turma_id

             WHERE m.aluno_id=%d AND m.ano_lectivo=%d AND m.escola_id=%d LIMIT 1", $aluno_id, (int)$ano, sige_get_escola_id()

        ));

        $srv_mens = function_exists('sige_fin_get_servico_por_tipo_e_classe')

            ? sige_fin_get_servico_por_tipo_e_classe('mensalidade', $classe) : null;

        if ($srv_mens) {

            $config_fin = function_exists('sige_fin_get_config') ? sige_fin_get_config($ano) : null;

            $dia_venc = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);

            $ts_venc = strtotime($mes_ref . '-01');

            $data_venc = wp_date('Y-m', $ts_venc) . '-' . str_pad((string)$dia_venc, 2, '0', STR_PAD_LEFT);

            $mes_nome = function_exists('sige_fin_obter_nome_mes') ? sige_fin_obter_nome_mes($mes_num) : $mes_num;

            $insert = [

                'escola_id'       => sige_get_escola_id(),

                'aluno_id'        => $aluno_id,

                'servico_id'      => (int)$srv_mens->id,

                'descricao'       => $srv_mens->nome . ' (' . $mes_nome . '/' . $ano . ') - ISENTO',

                'mes_referencia'  => $mes_ref,

                'valor_original'  => (float)$srv_mens->valor,

                'valor_pago'      => 0,

                'data_vencimento' => $data_venc,

                'status'          => 'isento',

            ];

            if ($has_cancel_em)  $insert['cancelado_em']  = current_time('mysql');

            if ($has_cancel_por) $insert['cancelado_por'] = get_current_user_id();

            if ($has_cancel_mot) $insert['motivo_cancelamento'] = 'ISENÇÃO: ' . $motivo;

            $wpdb->insert($tL, $insert);

            $count = 1;

        }

    }



    wp_send_json_success(['count' => $count, 'mes' => $mes_ref]);

});



// ─────────────────────────────────────────────────────────────────────────────

// [MOVIDO v12.2.1] O handler sige_alterar_senha_portal foi movido para

// includes/portal-handlers.php (separação de módulos).

// ─────────────────────────────────────────────────────────────────────────────



// ─────────────────────────────────────────────────────────────────────────────

// PRINT: Comprovativo de despesa individual ou relatório de despesas

// ─────────────────────────────────────────────────────────────────────────────

add_action('template_redirect', function() {

    if (!isset($_GET['sige_desp_print'])) return;

    if (!is_user_logged_in()) wp_die('Acesso negado.', 'SIGE - Segurança', ['response' => 403]);

    $tipo = sanitize_key(wp_unslash($_GET['sige_desp_print']));

    if (!in_array($tipo, ['comprovativo', 'relatorio'], true)) {

        wp_die('Tipo de impressão inválido.', 'SIGE - Segurança', ['response' => 400]);

    }

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (!wp_verify_nonce($nonce, 'sige_desp_print')) {

        wp_die('Pedido inválido. Reabra o comprovativo a partir do módulo de Despesas.', 'SIGE - Segurança', ['response' => 403]);

    }

    $sige_desp_print_can = false;

    if (function_exists('sige_can')) {

        $sige_desp_print_can = sige_can('financeiro.despesas_ver', ['surface' => 'sige_desp_print'])

            || sige_can('financeiro.despesas_gerir', ['surface' => 'sige_desp_print'])

            || sige_can('financeiro.ver', ['surface' => 'sige_desp_print']);

    }

    if (!$sige_desp_print_can) {

        $sige_desp_print_can = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))

            || current_user_can('sige_director')

            || current_user_can('sige_secretario')

            || current_user_can('sige_financeiro');

    }

    if (!$sige_desp_print_can) {

        wp_die('Sem permissão.', 'SIGE - Segurança', ['response' => 403]);

    }



    global $wpdb;

    $tD = $wpdb->prefix . 'sige_fin_despesas';

    $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

    if ($escola_id <= 0) {

        if (function_exists('sige_audit_log')) {

            sige_audit_log('despesa_print_tenant_invalido', ['tipo' => $tipo], 'financeiro');

        }

        wp_die('Operação bloqueada: contexto de escola inválido.', 'SIGE - Segurança', ['response' => 403]);

    }

    $escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;

    $escola_nome = $escola->nome_escola ?? get_bloginfo('name');

    $logo = '';

    if ($escola && !empty($escola->logotipo)) {

        $logo = $escola->logotipo;

    }



    // ── Individual ──

    if ($tipo === 'comprovativo') {

        $id = (int)($_GET['id'] ?? 0);

        if (!$id) wp_die('ID inválido.');

        $d = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tD WHERE id=%d AND escola_id=%d", $id, $escola_id));

        if (!$d) wp_die('Despesa não encontrada.', 'Não encontrado', ['response' => 404]);

        if (function_exists('sige_audit_log')) {

            sige_audit_log('despesa_comprovativo_impresso', ['despesa_id' => $id], 'financeiro');

        }



        $registado = $d->registado_por ? get_userdata((int)$d->registado_por) : null;

        $aprovado  = $d->aprovado_por ? get_userdata((int)$d->aprovado_por) : null;



        $cat_labels = [

            'material_escolar'=>'Material Escolar','salários'=>'Salários','manutenção'=>'Manutenção',

            'transporte'=>'Transporte','alimentação'=>'Alimentação','utilidades'=>'Utilidades',

            'equipamento'=>'Equipamento','serviços'=>'Serviços Externos','geral'=>'Geral','outro'=>'Outro',

        ];

        $cat_label = $cat_labels[$d->categoria] ?? ucfirst(str_replace('_',' ',$d->categoria));



        $metodo_label = function_exists('sige_fin_metodo_pagamento_label')
            ? sige_fin_metodo_pagamento_label($d->metodo_pagamento ?? '')
            : ucfirst($d->metodo_pagamento ?? '');



        sige_desp_print_page('COMPROVATIVO DE DESPESA', $escola_nome, $logo, function() use ($d, $cat_label, $metodo_label, $registado, $aprovado) {

            ?>

            <div style="text-align:center;margin-bottom:20px;">

                <div style="font-size:28px;font-weight:900;color:#dc2626;"><?php echo number_format((float)$d->valor, 2, '.', ','); ?> <?php echo esc_html(sige_moeda()); ?></div>

                <div style="font-size:12px;color:#64748b;margin-top:4px;">DESP-<?php echo str_pad((string)$d->id, 6, '0', STR_PAD_LEFT); ?></div>

            </div>

            <table style="width:100%;border-collapse:collapse;margin:0 auto;max-width:500px;">

                <?php

                $rows = [

                    'Data'        => wp_date('d/m/Y', strtotime($d->data_despesa)),

                    'Descrição'   => $d->descricao,

                    'Categoria'   => $cat_label,

                    'Fornecedor'  => $d->fornecedor ?: '-',

                    'Método'      => $metodo_label,

                    'Referência'  => $d->referencia ?: '-',

                    'Estado'      => ucfirst($d->status),

                    'Registado por' => $registado ? $registado->display_name : '-',

                    'Aprovado por'  => $aprovado ? $aprovado->display_name : '-',

                ];

                foreach ($rows as $label => $val):

                ?>

                <tr>

                    <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;color:#64748b;font-size:12px;width:40%;font-weight:600;"><?php echo $label; ?></td>

                    <td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;font-size:13px;font-weight:500;"><?php echo esc_html($val); ?></td>

                </tr>

                <?php endforeach; ?>

            </table>

            <?php if ($d->observacoes): ?>

            <div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;">

                <div style="font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;">OBSERVAÇÕES</div>

                <div style="font-size:12px;color:#334155;"><?php echo nl2br(esc_html($d->observacoes)); ?></div>

            </div>

            <?php endif; ?>

            <div style="margin-top:40px;display:flex;justify-content:space-between;">

                <div style="text-align:center;width:45%;">

                    <div style="border-top:1px solid #333;padding-top:6px;font-size:11px;color:#64748b;">Registado por</div>

                    <div style="font-size:12px;font-weight:600;"><?php echo $registado ? esc_html($registado->display_name) : '________________'; ?></div>

                </div>

                <div style="text-align:center;width:45%;">

                    <div style="border-top:1px solid #333;padding-top:6px;font-size:11px;color:#64748b;">Aprovado por</div>

                    <div style="font-size:12px;font-weight:600;"><?php echo $aprovado ? esc_html($aprovado->display_name) : '________________'; ?></div>

                </div>

            </div>

            <?php

        });

        exit;

    }



    // ── Relatório (lista filtrada) ──

    if ($tipo === 'relatorio') {

        $data_de  = sanitize_text_field($_GET['data_de'] ?? '');

        $data_ate = sanitize_text_field($_GET['data_ate'] ?? '');

        $cat      = sanitize_text_field($_GET['cat'] ?? '');

        $st       = sanitize_text_field($_GET['st'] ?? '');

        $centro_id = isset($_GET['centro_id']) ? absint($_GET['centro_id']) : 0;

        if ($centro_id > 0 && function_exists('sige_fin_centro_pertence_escola') && !sige_fin_centro_pertence_escola($centro_id, $escola_id)) {

            $centro_id = 0;

        }



        $where = ["escola_id = %d"];

        $params = [$escola_id];

        if ($data_de)  { $where[] = "data_despesa >= %s"; $params[] = $data_de; }

        if ($data_ate) { $where[] = "data_despesa <= %s"; $params[] = $data_ate; }

        if ($cat)      { $where[] = "categoria = %s"; $params[] = $cat; }

        if ($st)       { $where[] = "LOWER(status) = %s"; $params[] = strtolower($st); }

        $where[] = "LOWER(status) != 'anulado'";

        $where_sql = implode(' AND ', $where);

        $centro_sql = ($centro_id > 0 && function_exists('sige_fin_centro_where_clause'))

            ? sige_fin_centro_where_clause($centro_id)

            : '';



        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $tD WHERE $where_sql $centro_sql ORDER BY data_despesa DESC", $params));



        if (function_exists('sige_audit_log')) {

            sige_audit_log('despesas_relatorio_impresso', [

                'data_de' => $data_de,

                'data_ate' => $data_ate,

                'categoria' => $cat,

                'status' => $st,

                'centro_id' => $centro_id,

                'registos' => is_array($rows) ? count($rows) : 0,

            ], 'financeiro');

        }



        $total = 0;

        foreach ($rows as $r) $total += (float)$r->valor;



        $periodo = '';

        if ($data_de && $data_ate) $periodo = wp_date('d/m/Y', strtotime($data_de)) . ' - ' . wp_date('d/m/Y', strtotime($data_ate));

        elseif ($data_de) $periodo = 'Desde ' . wp_date('d/m/Y', strtotime($data_de));

        elseif ($data_ate) $periodo = 'Até ' . wp_date('d/m/Y', strtotime($data_ate));

        else $periodo = 'Todos os períodos';



        $cat_labels = [

            'material_escolar'=>'Material Escolar','salários'=>'Salários','manutenção'=>'Manutenção',

            'transporte'=>'Transporte','alimentação'=>'Alimentação','utilidades'=>'Utilidades',

            'equipamento'=>'Equipamento','serviços'=>'Serviços Externos','geral'=>'Geral','outro'=>'Outro',

        ];



        sige_desp_print_page('RELATÓRIO DE DESPESAS', $escola_nome, $logo, function() use ($rows, $total, $periodo, $cat, $cat_labels) {

            ?>

            <div style="display:flex;justify-content:space-between;margin-bottom:16px;font-size:12px;color:#64748b;">

                <div><strong>Período:</strong> <?php echo esc_html($periodo); ?></div>

                <?php if ($cat): ?><div><strong>Categoria:</strong> <?php echo esc_html($cat_labels[$cat] ?? $cat); ?></div><?php endif; ?>

                <div><strong>Registos:</strong> <?php echo count($rows); ?></div>

            </div>

            <table style="width:100%;border-collapse:collapse;">

                <thead>

                    <tr style="background:#f8fafc;">

                        <th style="padding:8px;text-align:left;font-size:11px;border-bottom:2px solid #e2e8f0;color:#64748b;">#</th>

                        <th style="padding:8px;text-align:left;font-size:11px;border-bottom:2px solid #e2e8f0;color:#64748b;">Data</th>

                        <th style="padding:8px;text-align:left;font-size:11px;border-bottom:2px solid #e2e8f0;color:#64748b;">Descrição</th>

                        <th style="padding:8px;text-align:left;font-size:11px;border-bottom:2px solid #e2e8f0;color:#64748b;">Categoria</th>

                        <th style="padding:8px;text-align:left;font-size:11px;border-bottom:2px solid #e2e8f0;color:#64748b;">Fornecedor</th>

                        <th style="padding:8px;text-align:right;font-size:11px;border-bottom:2px solid #e2e8f0;color:#64748b;">Valor (MT)</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($rows as $i => $r):

                    $cl = $cat_labels[$r->categoria] ?? ucfirst(str_replace('_',' ',$r->categoria));

                ?>

                    <tr style="<?php echo ($i % 2) ? 'background:#fafbfc;' : ''; ?>">

                        <td style="padding:6px 8px;font-size:11px;border-bottom:1px solid #f1f5f9;color:#94a3b8;"><?php echo (int)$r->id; ?></td>

                        <td style="padding:6px 8px;font-size:12px;border-bottom:1px solid #f1f5f9;"><?php echo esc_html(wp_date('d/m/Y', strtotime($r->data_despesa))); ?></td>

                        <td style="padding:6px 8px;font-size:12px;border-bottom:1px solid #f1f5f9;font-weight:600;"><?php echo esc_html($r->descricao); ?></td>

                        <td style="padding:6px 8px;font-size:11px;border-bottom:1px solid #f1f5f9;"><?php echo esc_html($cl); ?></td>

                        <td style="padding:6px 8px;font-size:11px;border-bottom:1px solid #f1f5f9;"><?php echo esc_html($r->fornecedor ?: '-'); ?></td>

                        <td style="padding:6px 8px;font-size:13px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;color:#dc2626;"><?php echo number_format((float)$r->valor, 2, '.', ','); ?></td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

                <tfoot>

                    <tr style="background:#1e293b;">

                        <td colspan="5" style="padding:10px;color:white;font-weight:800;font-size:13px;">TOTAL</td>

                        <td style="padding:10px;color:white;font-weight:900;font-size:16px;text-align:right;"><?php echo number_format($total, 2, '.', ','); ?> <?php echo esc_html(sige_moeda()); ?></td>

                    </tr>

                </tfoot>

            </table>

            <?php

        });

        exit;

    }

});



/**

 * Helper: Renderiza página de impressão de despesas

 */

function sige_desp_print_page($titulo, $escola_nome, $logo, $content_callback) {

    ?><!DOCTYPE html>

<html lang="pt">

<head>

    <meta charset="UTF-8">

    <title><?php echo esc_html($titulo . ' - ' . $escola_nome); ?></title>

    <style>

        *{margin:0;padding:0;box-sizing:border-box}

        body{font-family:'Segoe UI',system-ui,sans-serif;color:#1e293b;background:white;padding:30px;max-width:800px;margin:0 auto}

        .header{display:flex;align-items:center;gap:16px;padding-bottom:16px;border-bottom:3px solid #1e293b;margin-bottom:20px}

        .header img{width:60px;height:60px;object-fit:contain}

        .header .info{flex:1}

        .header .escola{font-size:18px;font-weight:800;color:#1e293b}

        .header .doc-tipo{font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:2px}

        .header .data{font-size:11px;color:#94a3b8;text-align:right}

        .content{min-height:400px}

        .footer{margin-top:30px;padding-top:12px;border-top:1px solid #e2e8f0;text-align:center;font-size:10px;color:#94a3b8}

        @media print{

            body{padding:15px}

            .no-print{display:none!important}

            @page{margin:10mm 15mm}

        }

    </style>

</head>

<body>

    <div class="no-print" style="text-align:center;margin-bottom:20px;">

        <button onclick="window.print()" style="background:#1e40af;color:white;border:none;padding:10px 30px;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;">Imprimir</button>

        <button onclick="window.close()" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;padding:10px 20px;border-radius:6px;font-size:14px;cursor:pointer;margin-left:8px;">Fechar</button>

    </div>

    <div class="header">

        <?php if ($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="Logo"><?php endif; ?>

        <div class="info">

            <div class="escola"><?php echo esc_html($escola_nome); ?></div>

            <div class="doc-tipo"><?php echo esc_html($titulo); ?></div>

        </div>

        <div class="data">

            Impresso em:<br>

            <strong><?php echo wp_date('d/m/Y H:i'); ?></strong>

        </div>

    </div>

    <div class="content">

        <?php $content_callback(); ?>

    </div>

    <div class="footer">

        Documento emitido pela secretaria da escola - <?php echo esc_html($escola_nome); ?>

    </div>

    <script>window.onafterprint = function() { /* keep open */ };</script>

</body>

</html><?php

}



// ==============================================================================
// v98 - Casa Colorida hotfix: helpers batch usados por financeiro-pagamentos.php
// ------------------------------------------------------------------------------
// A view de pagamentos importada da Casa Colorida usa estes helpers para evitar
// N+1 queries ao abrir a ficha do aluno. Em algumas bases consolidadas, a view
// já estava presente mas os helpers não tinham sido trazidos para o finance-core,
// causando tela branca ao abrir ?view=financeiro-pagamentos&aluno_id=...
// Funções idempotentes: só são declaradas se ainda não existirem.
// ==============================================================================

if (!function_exists('sige_fin_batch_fetch_lancamentos')) {
    /**
     * Busca N lançamentos por ID numa única query, retorna array indexado por ID.
     *
     * @param array  $ids     IDs dos lançamentos.
     * @param string $columns Colunas internas a seleccionar. Usar apenas strings internas controladas pelo sistema.
     * @param bool   $scoped  Se true, filtra por escola_id corrente quando disponível.
     * @return array<int, object>
     */
    function sige_fin_batch_fetch_lancamentos(array $ids, string $columns = '*', bool $scoped = true): array {
        global $wpdb;

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) return [];

        $columns = trim($columns) !== '' ? trim($columns) : '*';
        if ($columns !== '*' && !preg_match('/^[a-zA-Z0-9_`,.\s]+$/', $columns)) {
            $columns = '*';
        }

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params = $ids;

        $where_escola = '';
        if ($scoped && function_exists('sige_get_escola_id')) {
            $where_escola = ' AND escola_id = %d';
            $params[] = (int) sige_get_escola_id();
        }

        $sql = "SELECT {$columns} FROM {$tL} WHERE id IN ({$placeholders}){$where_escola}";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        $map = [];
        foreach ((array) $rows as $r) {
            if (isset($r->id)) $map[(int) $r->id] = $r;
        }
        return $map;
    }
}

if (!function_exists('sige_fin_batch_fetch_alunos_nomes')) {
    /**
     * Pré-fetch de nomes de alunos por ID numa única query.
     *
     * @param array $aluno_ids IDs dos alunos.
     * @return array<int, string>
     */
    function sige_fin_batch_fetch_alunos_nomes(array $aluno_ids): array {
        global $wpdb;

        $aluno_ids = array_values(array_unique(array_filter(array_map('intval', $aluno_ids))));
        if (empty($aluno_ids)) return [];

        $tA = $wpdb->prefix . 'sige_alunos';
        $placeholders = implode(',', array_fill(0, count($aluno_ids), '%d'));
        $params = $aluno_ids;

        $where_escola = '';
        if (function_exists('sige_get_escola_id')) {
            $where_escola = ' AND escola_id = %d';
            $params[] = (int) sige_get_escola_id();
        }

        $sql = "SELECT id, nome_completo FROM {$tA} WHERE id IN ({$placeholders}){$where_escola}";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));

        $map = [];
        foreach ((array) $rows as $r) {
            if (isset($r->id)) $map[(int) $r->id] = (string) ($r->nome_completo ?? '');
        }
        return $map;
    }
}
