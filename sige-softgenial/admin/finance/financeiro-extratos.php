<?php
if (!defined('ABSPATH')) exit;

/**

 * SIGE - Fecho de Caixa, Extratos & Recibos

 * Fusão: Caixa Diário + Histórico de Aluno + Recibo Unificado

 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.extractos_ver'],
    ['sige_financeiro','sige_secretario','sige_director']
)) return;

if (!function_exists('sige_fin_get_config')) {

 echo '<div class="notice notice-error"><p>Finance Core não carregado. Confirme o require de <code>includes/finance-core.php</code>.</p></div>';

 return;

}

// ─────────────────────────────────────────────

// TABELAS

// ─────────────────────────────────────────────

$tP = $wpdb->prefix . 'sige_fin_pagamentos';

$tL = $wpdb->prefix . 'sige_fin_lancamentos';

$tS = $wpdb->prefix . 'sige_fin_servicos';

$tF = $wpdb->prefix . 'sige_fin_fechos_caixa';

// [12.9.21] Helpers locais para enriquecer o Excel do extracto diário com
// totais por classe e por ciclo, sem alterar a grelha visual existente.
if (!function_exists('sige_extracto_classe_num')) {
    function sige_extracto_classe_num($classe) {
        if ($classe === null) return 0;
        if (preg_match('/\d+/', (string)$classe, $m)) {
            return (int)$m[0];
        }
        return 0;
    }
}


if (!function_exists('sige_extracto_safe_lower')) {
    function sige_extracto_safe_lower($value) {
        $value = trim((string)$value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('sige_extracto_ciclo_label')) {
    function sige_extracto_ciclo_label($classe, $servico_ciclo = '', $servico_nome = '') {
        // [12.9.77] Ciclo mais robusto: usa turma/classe, mas também respeita
        // o ciclo/nome do serviço financeiro. Isto corrige casos de Creche em
        // que a turma aparece como sala/berçário/jardim ou vem vazia na matrícula.
        $classe_txt = sige_extracto_safe_lower($classe);
        $servico_ciclo_txt = sige_extracto_safe_lower($servico_ciclo);
        $servico_nome_txt = sige_extracto_safe_lower($servico_nome);
        $texto = trim($classe_txt . ' ' . $servico_ciclo_txt . ' ' . $servico_nome_txt);

        if ($servico_ciclo_txt !== '') {
            if (preg_match('/cre[s]?che|ber[cç]a|pr[eé][ -]?escolar|jardim|infantil|inicia[cç][aã]o|baby|maternal|sala dos? [0-5]/iu', $servico_ciclo_txt)) return 'Creche';
            if (preg_match('/prim[aá]rio|1[ªa]?|2[ªa]?|3[ªa]?|4[ªa]?|5[ªa]?|6[ªa]?/iu', $servico_ciclo_txt)) return 'Primário';
            if (preg_match('/secund[aá]rio|7[ªa]?|8[ªa]?|9[ªa]?|10[ªa]?|11[ªa]?|12[ªa]?/iu', $servico_ciclo_txt)) return 'Secundário';
        }
        if ($texto !== '' && preg_match('/cre[s]?che|ber[cç]a|pr[eé][ -]?escolar|jardim|infantil|inicia[cç][aã]o|baby|maternal|sala dos? [0-5]/iu', $texto)) {
            return 'Creche';
        }
        $n = sige_extracto_classe_num($classe);
        if ($n >= 1 && $n <= 6) return 'Primário';
        if ($n >= 7 && $n <= 12) return 'Secundário';
        return 'Outro';
    }
}

// [12.9.24] Filtros de download/visualização do extracto por período e ciclo.
// Mantém a lógica financeira intacta: só muda o intervalo de consulta e a
// segmentação visual/exportável do extracto; não recalcula valores.
if (!function_exists('sige_extracto_periodo_datas')) {
    function sige_extracto_periodo_datas($periodo, $data_ref, $mes_ref = '', $ano_ref = 0) {
        $periodo = in_array($periodo, ['diario','mensal','anual'], true) ? $periodo : 'diario';
        $data_ref = function_exists('sige_fin_normalize_date_ymd') ? sige_fin_normalize_date_ymd($data_ref) : (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data_ref) ? (string)$data_ref : wp_date('Y-m-d'));
        $mes_ref = preg_match('/^\d{4}-\d{2}$/', (string)$mes_ref) ? (string)$mes_ref : substr($data_ref, 0, 7);
        $ano_ref = (int)$ano_ref;
        if ($ano_ref < 2000 || $ano_ref > 2100) $ano_ref = (int)substr($data_ref, 0, 4);
        if ($periodo === 'mensal') {
            [$ini, $fim] = sige_fin_month_bounds($mes_ref);
            return [$ini, $fim];
        }
        if ($periodo === 'anual') return [sprintf('%04d-01-01', $ano_ref), sprintf('%04d-12-31', $ano_ref)];
        return [$data_ref, $data_ref];
    }
}

if (!function_exists('sige_extracto_periodo_label')) {
    function sige_extracto_periodo_label($periodo, $data_ref, $mes_ref = '', $ano_ref = 0) {
        $periodo = in_array($periodo, ['diario','mensal','anual'], true) ? $periodo : 'diario';
        if ($periodo === 'mensal') {
            $mes_ref = preg_match('/^\d{4}-\d{2}$/', (string)$mes_ref) ? (string)$mes_ref : substr((string)$data_ref, 0, 7);
            $ts = strtotime($mes_ref . '-01');
            if (!$ts) $ts = current_time('timestamp');
            return 'Mensal · ' . wp_date('F Y', $ts);
        }
        if ($periodo === 'anual') {
            $ano_ref = (int)$ano_ref;
            if ($ano_ref < 2000 || $ano_ref > 2100) $ano_ref = (int)wp_date('Y');
            return 'Anual · ' . $ano_ref;
        }
        $ts = strtotime($data_ref ?: wp_date('Y-m-d'));
        if (!$ts) $ts = current_time('timestamp');
        return 'Diário · ' . wp_date('d \d\e F \d\e Y', $ts);
    }
}

if (!function_exists('sige_extracto_ciclo_get_param')) {
    function sige_extracto_ciclo_get_param($valor) {
        $valor = sanitize_key((string)$valor);
        return in_array($valor, ['todos','creche','primario','secundario'], true) ? $valor : 'todos';
    }
}


// [v12.11.9.88] Helpers PRO de reconciliação de caixa.
// Mantêm compatibilidade com schemas antigos: a fotografia canónica continua
// em total_por_metodo; a reconciliação operacional é guardada em observacoes.
if (!function_exists('sige_extracto_money_from_raw')) {
    function sige_extracto_money_from_raw($value): float {
        if (is_array($value) || is_object($value)) return 0.0;
        $value = trim((string)$value);
        if ($value === '') return 0.0;
        $value = preg_replace('/[^0-9,\.\-]/', '', $value);
        if ($value === '' || $value === '-' || $value === null) return 0.0;
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastComma !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        return round((float)$value, 2);
    }
}

if (!function_exists('sige_extracto_table_has_column')) {
    function sige_extracto_table_has_column(string $table, string $column): bool {
        global $wpdb;
        static $cache = [];
        $key = $table . '::' . $column;
        if (array_key_exists($key, $cache)) return (bool)$cache[$key];
        $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        $cache[$key] = !empty($found);
        return (bool)$cache[$key];
    }
}

if (!function_exists('sige_extracto_filter_existing_columns')) {
    function sige_extracto_filter_existing_columns(string $table, array $data): array {
        foreach (array_keys($data) as $col) {
            if (!sige_extracto_table_has_column($table, (string)$col)) unset($data[$col]);
        }
        return $data;
    }
}

if (!function_exists('sige_extracto_recon_marker_encode')) {
    function sige_extracto_recon_marker_encode(array $payload): string {
        return "\n\n[SIGE_RECON_V1]" . wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "[/SIGE_RECON_V1]";
    }
}

if (!function_exists('sige_extracto_recon_parse')) {
    function sige_extracto_recon_parse($observacoes): array {
        $observacoes = (string)$observacoes;
        if ($observacoes === '') return [];
        if (!preg_match('/\[SIGE_RECON_V1\](.*?)\[\/SIGE_RECON_V1\]/s', $observacoes, $m)) return [];
        $json = html_entity_decode((string)$m[1], ENT_QUOTES, 'UTF-8');
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('sige_extracto_recon_clean_note')) {
    function sige_extracto_recon_clean_note($observacoes): string {
        $txt = preg_replace('/\s*\[SIGE_RECON_V1\].*?\[\/SIGE_RECON_V1\]\s*/s', '', (string)$observacoes);
        return trim((string)$txt);
    }
}

if (!function_exists('sige_extracto_recon_methods')) {
    function sige_extracto_recon_methods(array $resumo, array $labels): array {
        $methods = [];
        foreach ($labels as $key => $label) {
            if ($key === 'estorno') continue;
            $expected = round((float)($resumo[$key] ?? 0), 2);
            if ($expected > 0.0001) {
                $methods[$key] = ['label' => (string)$label, 'expected' => $expected];
            }
        }
        // Num dia sem movimentos, mantém pelo menos numerário visível para confirmar zero.
        if (empty($methods)) {
            $methods['numerario'] = ['label' => $labels['numerario'] ?? 'Numerário', 'expected' => 0.0];
        }
        return $methods;
    }
}

if (!function_exists('sige_extracto_recon_build_payload')) {
    function sige_extracto_recon_build_payload(array $methods, array $posted, float $total_bruto, float $total_estorno, float $total_liquido, float $total_despesas, string $motivo = ''): array {
        $rows = [];
        $total_contado = 0.0;
        foreach ($methods as $key => $meta) {
            $expected = round((float)($meta['expected'] ?? 0), 2);
            $counted = array_key_exists($key, $posted) ? sige_extracto_money_from_raw($posted[$key]) : $expected;
            $counted = round(max(0.0, $counted), 2);
            $diff = round($counted - $expected, 2);
            $total_contado += $counted;
            $rows[$key] = [
                'label'     => (string)($meta['label'] ?? $key),
                'system'    => $expected,
                'counted'   => $counted,
                'difference'=> $diff,
            ];
        }
        $total_contado = round($total_contado, 2);
        $divergencia = round($total_contado - $total_bruto, 2);
        $saldo_estimado = round($total_contado - $total_estorno - $total_despesas, 2);
        return [
            'version'        => '12.11.9.88',
            'kind'           => 'cash_reconciliation_pro',
            'created_at'     => current_time('mysql'),
            'created_by'     => get_current_user_id(),
            'currency'       => function_exists('sige_moeda') ? sige_moeda() : 'MT',
            'system'         => [
                'gross'      => round($total_bruto, 2),
                'refunds'    => round($total_estorno, 2),
                'net'        => round($total_liquido, 2),
                'expenses'   => round($total_despesas, 2),
                'balance'    => round($total_liquido - $total_despesas, 2),
            ],
            'counted'        => [
                'gross'      => $total_contado,
                'estimated_balance_after_adjustments' => $saldo_estimado,
            ],
            'difference'     => $divergencia,
            'methods'        => $rows,
            'divergence_note'=> sanitize_textarea_field($motivo),
            'checklist'      => [
                'period_confirmed' => true,
                'methods_checked'  => true,
                'expenses_checked' => true,
            ],
        ];
    }
}


// [12.9.46] Normalização PRO de datas para filtros financeiros.
// Evita divergências entre instâncias por timezone/locale e garante que o SQL
// recebe sempre datas no formato Y-m-d, mesmo quando o navegador envia valores
// localizados ou vazios.
if (!function_exists('sige_fin_normalize_date_ymd')) {
    function sige_fin_normalize_date_ymd($value, $fallback = '') {
        $value = trim((string)$value);
        $fallback = $fallback !== '' ? (string)$fallback : (function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d'));
        if ($value === '') return $fallback;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        if (preg_match('/^(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{4})$/', $value, $m)) {
            $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : $fallback;
        }
        $ts = strtotime($value);
        return $ts ? gmdate('Y-m-d', $ts) : $fallback;
    }
}

if (!function_exists('sige_fin_month_bounds')) {
    function sige_fin_month_bounds($ym) {
        $ym = preg_match('/^\d{4}-\d{2}$/', (string)$ym) ? (string)$ym : (function_exists('sige_mz_date') ? sige_mz_date('Y-m') : wp_date('Y-m'));
        $start = $ym . '-01';
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        if (!$dt) {
            $ym = function_exists('sige_mz_date') ? sige_mz_date('Y-m') : wp_date('Y-m');
            $start = $ym . '-01';
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        }
        return [$start, $dt->modify('last day of this month')->format('Y-m-d')];
    }
}

// ── escola_id via helper ──────────────────────────────────────────────────
$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;
$__sige_perfil_escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$__sige_nome_escola = $__sige_perfil_escola->nome_escola ?? get_bloginfo('name');
$__sige_contacto_escola = $__sige_perfil_escola->telefone_oficial ?? ($__sige_perfil_escola->contacto ?? '');
$__sige_email_escola = $__sige_perfil_escola->email_institucional ?? '';
$__sige_endereco_escola = $__sige_perfil_escola->endereco_escola ?? '';

// [DRY-S12] sige_fin_total_lancamento() - canónica em finance-core.php

// ─────────────────────────────────────────────

// MODO: DIÁRIO vs HISTÓRICO DE ALUNO

// ─────────────────────────────────────────────

$aluno_id = sige_fin_get_int('aluno_id');

$data_filtro = sige_fin_normalize_date_ymd(sige_fin_get_param('data_relatorio'), function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d'));
$mes_relatorio = preg_match('/^\d{4}-\d{2}$/', (string)sige_fin_get_param('mes_relatorio')) ? sige_fin_get_param('mes_relatorio') : substr($data_filtro, 0, 7);
$ano_relatorio = sige_fin_get_int('ano_relatorio', (int)substr($data_filtro, 0, 4));
if ($ano_relatorio < 2000 || $ano_relatorio > 2100) { $ano_relatorio = (int)substr($data_filtro, 0, 4); }

$rel_periodo = sanitize_key((string)(sige_fin_get_param('periodo_relatorio') ?: 'diario'));
if (!in_array($rel_periodo, ['diario','mensal','anual'], true)) { $rel_periodo = 'diario'; }
$rel_ciclo = sige_extracto_ciclo_get_param(sige_fin_get_param('ciclo_ensino') ?: 'todos');
[$data_inicio_relatorio, $data_fim_relatorio] = sige_extracto_periodo_datas($rel_periodo, $data_filtro, $mes_relatorio, $ano_relatorio);
$rel_periodo_label = sige_extracto_periodo_label($rel_periodo, $data_filtro, $mes_relatorio, $ano_relatorio);
$rel_ciclo_label_map = ['todos'=>'Todos os ciclos', 'creche'=>'Creche', 'primario'=>'Ensino Primário', 'secundario'=>'Ensino Secundário'];
$rel_ciclo_label = $rel_ciclo_label_map[$rel_ciclo] ?? 'Todos os ciclos';
$sg_period_range = ($data_inicio_relatorio === $data_fim_relatorio)
    ? wp_date('d/m/Y', strtotime($data_inicio_relatorio))
    : wp_date('d/m/Y', strtotime($data_inicio_relatorio)) . ' - ' . wp_date('d/m/Y', strtotime($data_fim_relatorio));

// Defaults seguros para o estado "Histórico de aluno" sem aluno seleccionado.
// Evita warnings e impede que o ecrã de aluno mostre movimentos do caixa por engano.
$extrato = [];
$aluno_dados = null;
$hist_total = 0.0;
$hist_entradas = 0.0;
$hist_estornos = 0.0;

$modo_aluno = ($aluno_id > 0);

// Toggle de modo via GET (checkbox de UI)

$modo_get = sige_fin_get_param('modo') ?: ($modo_aluno ? 'aluno' : 'diario');

if ($modo_get === 'diario') { $aluno_id = 0; $modo_aluno = false; }

// [v12.11.9.83] Estado de navegação do ecrã.
// PROBLEMA CORRIGIDO: o separador "Histórico de aluno" dependia apenas de
// existir um aluno seleccionado ($modo_aluno = aluno_id > 0). Ao clicar no
// separador sem aluno escolhido, o botão nunca ficava activo e parecia que
// "nada acontecia". Separamos agora "modo pretendido pelo utilizador" de
// "aluno efectivamente carregado" sem mexer na lógica financeira.
$sg_em_modo_aluno   = ($modo_get === 'aluno') || $modo_aluno; // o utilizador quer o histórico de aluno
$sg_aluno_carregado = ($modo_aluno && $aluno_id > 0);          // existe aluno efectivamente seleccionado

// [v13.4.0 BLOCO 3] Filtro universal por centro. Aplicado a caixa do dia,
// despesas do dia, e ao extrato (quer em modo diário quer em modo histórico
// do aluno). Tabela sige_fin_fechos_caixa é excepção documentada - o fecho
// de caixa é por natureza consolidado (não tem coluna centro_id).
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;
$_centro_sql_p = $centro_id_filtro > 0 ? ' AND p.centro_id = ' . (int)$centro_id_filtro : '';
$_centro_sql_d = $centro_id_filtro > 0 ? ' AND centro_id = ' . (int)$centro_id_filtro : '';

// [v12.11.9.87] Preferências leves por utilizador: densidade visual e última
// leitura usada. Não altera regras financeiras; só melhora customização da UI.
$sg_density_options = ['compacta', 'normal', 'confortavel'];
$sg_density_labels = [
    'compacta'    => 'Compacta',
    'normal'      => 'Normal',
    'confortavel' => 'Confortável',
];
$sg_density_param = sanitize_key((string)sige_fin_get_param('sg_density'));
$sg_density_saved = is_user_logged_in() ? sanitize_key((string)get_user_meta(get_current_user_id(), 'sige_fin_extratos_density', true)) : '';
$sg_density = in_array($sg_density_param, $sg_density_options, true)
    ? $sg_density_param
    : (in_array($sg_density_saved, $sg_density_options, true) ? $sg_density_saved : 'normal');
if (is_user_logged_in() && in_array($sg_density_param, $sg_density_options, true) && $sg_density_param !== $sg_density_saved) {
    update_user_meta(get_current_user_id(), 'sige_fin_extratos_density', $sg_density_param);
}
$sg_density_class = 'sg-density-' . $sg_density;

$sg_today = function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d');
$sg_yesterday = wp_date('Y-m-d', strtotime($sg_today . ' -1 day'));
$sg_this_month = substr($sg_today, 0, 7);
$sg_prev_month = wp_date('Y-m', strtotime($sg_today . ' -1 month'));
$sg_this_year = (int)substr($sg_today, 0, 4);
$sg_preset_base = [
    'page'       => 'sige-app',
    'view'       => 'financeiro-extratos',
    'modo'       => 'diario',
    'sg_density' => $sg_density,
];
if ($centro_id_filtro > 0) { $sg_preset_base['centro_id'] = $centro_id_filtro; }
$sg_filter_presets = [
    [
        'key'    => 'hoje',
        'label'  => 'Hoje',
        'hint'   => 'Caixa do dia',
        'active' => (!$sg_em_modo_aluno && $rel_periodo === 'diario' && $data_filtro === $sg_today && $rel_ciclo === 'todos'),
        'args'   => array_merge($sg_preset_base, ['periodo_relatorio' => 'diario', 'data_relatorio' => $sg_today, 'ciclo_ensino' => 'todos']),
    ],
    [
        'key'    => 'ontem',
        'label'  => 'Ontem',
        'hint'   => 'Conferência rápida',
        'active' => (!$sg_em_modo_aluno && $rel_periodo === 'diario' && $data_filtro === $sg_yesterday && $rel_ciclo === 'todos'),
        'args'   => array_merge($sg_preset_base, ['periodo_relatorio' => 'diario', 'data_relatorio' => $sg_yesterday, 'ciclo_ensino' => 'todos']),
    ],
    [
        'key'    => 'mes',
        'label'  => 'Este mês',
        'hint'   => 'Movimentos mensais',
        'active' => (!$sg_em_modo_aluno && $rel_periodo === 'mensal' && $mes_relatorio === $sg_this_month && $rel_ciclo === 'todos'),
        'args'   => array_merge($sg_preset_base, ['periodo_relatorio' => 'mensal', 'mes_relatorio' => $sg_this_month, 'ciclo_ensino' => 'todos']),
    ],
    [
        'key'    => 'mes_anterior',
        'label'  => 'Mês anterior',
        'hint'   => 'Auditoria mensal',
        'active' => (!$sg_em_modo_aluno && $rel_periodo === 'mensal' && $mes_relatorio === $sg_prev_month && $rel_ciclo === 'todos'),
        'args'   => array_merge($sg_preset_base, ['periodo_relatorio' => 'mensal', 'mes_relatorio' => $sg_prev_month, 'ciclo_ensino' => 'todos']),
    ],
    [
        'key'    => 'ano',
        'label'  => 'Este ano',
        'hint'   => 'Visão anual',
        'active' => (!$sg_em_modo_aluno && $rel_periodo === 'anual' && (int)$ano_relatorio === $sg_this_year && $rel_ciclo === 'todos'),
        'args'   => array_merge($sg_preset_base, ['periodo_relatorio' => 'anual', 'ano_relatorio' => $sg_this_year, 'ciclo_ensino' => 'todos']),
    ],
];
if (is_user_logged_in() && !$sg_em_modo_aluno) {
    update_user_meta(get_current_user_id(), 'sige_fin_extratos_last_filters', [
        'periodo_relatorio' => $rel_periodo,
        'data_relatorio'    => $data_filtro,
        'mes_relatorio'     => $mes_relatorio,
        'ano_relatorio'     => $ano_relatorio,
        'ciclo_ensino'      => $rel_ciclo,
        'centro_id'         => $centro_id_filtro,
        'density'           => $sg_density,
        'updated_at'        => current_time('mysql'),
    ]);
}

// ─────────────────────────────────────────────

// PERMISSÕES

// ─────────────────────────────────────────────

$pode_direccao = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_secretario');

// ─────────────────────────────────────────────

// FECHO DE CAIXA - estado (só relevante no modo diário)

// ─────────────────────────────────────────────

$fecho_row = null;

$dia_fechado = false;

if (!$sg_em_modo_aluno) {

 $fecho_row = $wpdb->get_row($wpdb->prepare(

 "SELECT * FROM $tF WHERE escola_id = %d AND data_caixa = %s ORDER BY id DESC LIMIT 1",

 $escola_id, $data_filtro

 ));

 $dia_fechado = ($fecho_row && $fecho_row->status === 'fechado');

}

// ─────────────────────────────────────────────

// POST: REABRIR CAIXA

// ─────────────────────────────────────────────

if (!$sg_em_modo_aluno && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_reabrir_caixa'])) {

 $is_director_reopen = ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director'));

 if (!$is_director_reopen) {

 echo '<div class="sige-alert sige-alert-error"><p>Apenas o Director pode reabrir o caixa.</p></div>';

 } elseif (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_reabrir_caixa')) {

 echo '<div class="sige-alert sige-alert-error"><p>Pedido inválido.</p></div>';

 } elseif (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('caixa_reabrir')) {
  echo '<div class="sige-alert sige-alert-error"><p>Confirmacao de identidade necessaria para reabrir o caixa.</p></div>';
  echo function_exists('sige_mfa_render_challenge_form') ? sige_mfa_render_challenge_form() : '';
 } else {

 $dc = sige_fin_post_param('data_caixa') ?: $data_filtro;
 $motivo_reabrir = sige_fin_post_param('motivo_reabertura');

 if ($motivo_reabrir === '') {
 echo '<div class="sige-alert sige-alert-error"><p>O motivo da reabertura é obrigatório.</p></div>';
 } else {

 $fecho_alvo = $wpdb->get_row($wpdb->prepare("SELECT id FROM $tF WHERE escola_id=%d AND data_caixa=%s AND status='fechado' ORDER BY id DESC LIMIT 1", $escola_id, $dc));
 $sg_req = sige_fin_aprovacao_solicitar('reabertura_caixa', $fecho_alvo ? (int)$fecho_alvo->id : 0, ['data_caixa' => $dc, 'motivo' => $motivo_reabrir], $escola_id, 'caixa ' . $dc);
 if (!empty($sg_req['ok'])) {
 echo '<div class="sige-alert sige-alert-success"><p>Pedido de reabertura do caixa de <strong>' . esc_html($dc) . '</strong> submetido para aprovação. A reabertura só será efectuada depois de aprovada por outro utilizador autorizado, na página de <strong>Aprovações</strong>.</p></div>';
 } else {
 echo '<div class="sige-alert sige-alert-error"><p>' . esc_html($sg_req['error'] ?? 'Não foi possível submeter o pedido.') . '</p></div>';
 }

 }
 }

}

// ─────────────────────────────────────────────

// TOTAIS DO DIA (modo diário)

// ─────────────────────────────────────────────

// [v13.3.0 Bloco 2] Delega ao motor consolidado sige_kpi_caixa_dia(). O $resumo
// continua a ser calculado para preservar a ordem visual dos cartões por método
// (ex: numerário primeiro mesmo quando zero), mas os totais agregados vêm todos
// do engine. Zero duplicação de lógica bruto/estorno/líquido.
$resumo = ['numerario' => 0, 'mpesa' => 0, 'emola' => 0, 'emola_comerciante' => 0, 'bim' => 0, 'bci' => 0, 'pagafacil' => 0, 'nib' => 0, 'transferencia' => 0, 'pos_bci' => 0, 'pos_bim' => 0, 'pos_stbank' => 0, 'pos_moza' => 0, 'pos_nedbank' => 0, 'pos_fnb' => 0, 'pos' => 0, 'estorno' => 0];

$total_bruto = 0.0;
$total_estorno = 0.0;
$total_liquido = 0.0;
$total_despesas_dia = 0.0;

if (!$sg_em_modo_aluno && function_exists('sige_kpi_caixa_dia')) {
	$__cx = sige_kpi_caixa_dia($escola_id, $data_filtro, $centro_id_filtro);

	$total_bruto        = (float) $__cx['total_bruto'];
	$total_estorno      = (float) $__cx['total_estorno'];
	$total_liquido      = (float) $__cx['total_liquido'];
	$total_despesas_dia = (float) $__cx['despesas'];

	// Preencher $resumo preservando ordem visual (métodos ausentes ficam a 0)
	foreach ($__cx['por_metodo'] as $__m => $__v) {
		$resumo[$__m] = (float) $__v;
	}
}

// Lista detalhada de despesas do dia (para render na UI) - não agregada
// [v13.3.0] Fix multi-tenant: filtro por escola_id que estava em falta na v13.2.1.
$tDesp = $wpdb->prefix . 'sige_fin_despesas';
$despesas_dia = [];
if (!$sg_em_modo_aluno && $wpdb->get_var("SHOW TABLES LIKE '$tDesp'") === $tDesp) {
	$despesas_dia = $wpdb->get_results($wpdb->prepare(
		"SELECT * FROM $tDesp
		 WHERE escola_id = %d
		   AND data_despesa = %s
		   AND LOWER(status) != 'anulado'
		   {$_centro_sql_d}
		 ORDER BY id ASC",
		$escola_id, $data_filtro
	));
}
$saldo_dia = $total_liquido - $total_despesas_dia;


// [v12.11.9.88] Snapshot operacional para o assistente de reconciliação.
$sg_recon_method_labels = function_exists('sige_fin_metodos_pagamento_labels')
    ? sige_fin_metodos_pagamento_labels()
    : ['numerario'=>'Numerário','mpesa'=>'M-Pesa','emola'=>'E-Mola','emola_comerciante'=>'E-Mola Comerciante','bim'=>'Millennium BIM','bci'=>'BCI','pagafacil'=>'Paga Fácil','nib'=>'Transferência (NIB)','transferencia'=>'Transferência Bancária','pos_bci'=>'POS BCI','pos_bim'=>'POS BIM','pos_stbank'=>'POS STBANK','pos_moza'=>'POS MOZA','pos_nedbank'=>'POS NEDBANK','pos_fnb'=>'POS FNB','pos'=>'POS (Banco não especificado)','estorno'=>'Estornos'];
$sg_recon_methods = sige_extracto_recon_methods($resumo, $sg_recon_method_labels);
$sg_recon_expected_gross = round((float)$total_bruto, 2);
$sg_recon_expected_net = round((float)$total_liquido, 2);
$sg_recon_expected_balance = round((float)$saldo_dia, 2);
$sg_recon_expenses_total = round((float)$total_despesas_dia, 2);
$sg_recon_refunds_total = round((float)$total_estorno, 2);
$sg_recon_post_payload = [];
$sg_recon_post_difference = 0.0;

// ─────────────────────────────────────────────

// POST: FECHAR CAIXA - Fase 2 PRO: reconciliação assistida

// ─────────────────────────────────────────────

if (!$sg_em_modo_aluno && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fechar_caixa'])) {

 if (!$pode_direccao && !current_user_can('sige_financeiro')) {

 echo '<div class="sige-alert sige-alert-error"><p>Sem permissão para fechar caixa.</p></div>';

 } elseif (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_fechar_caixa')) {

 echo '<div class="sige-alert sige-alert-error"><p>Pedido inválido.</p></div>';

 } elseif (function_exists('sige_mfa_require_step_up') && !sige_mfa_require_step_up('caixa_fechar')) {
  echo '<div class="sige-alert sige-alert-error"><p>Confirmacao de identidade necessaria para fechar o caixa.</p></div>';
  echo function_exists('sige_mfa_render_challenge_form') ? sige_mfa_render_challenge_form() : '';
 } else {

 $dc = sige_fin_normalize_date_ymd(sige_fin_post_param('data_caixa') ?: $data_filtro, $data_filtro);
 $recon_confirmado = isset($_POST['sg_recon_confirmado']) && (string)$_POST['sg_recon_confirmado'] === '1';
 $motivo_divergencia = sanitize_textarea_field((string)sige_fin_post_param('motivo_divergencia'));
 $posted_contado = (isset($_POST['sg_contado']) && is_array($_POST['sg_contado'])) ? wp_unslash($_POST['sg_contado']) : [];
 $sg_recon_post_payload = sige_extracto_recon_build_payload($sg_recon_methods, $posted_contado, $total_bruto, $total_estorno, $total_liquido, $total_despesas_dia, $motivo_divergencia);
 $sg_recon_post_difference = round((float)($sg_recon_post_payload['difference'] ?? 0), 2);

 if (!$recon_confirmado) {
     echo '<div class="sige-alert sige-alert-error"><p>Confirme a checklist de reconciliação antes de fechar o caixa.</p></div>';
 } elseif (abs($sg_recon_post_difference) >= 0.01 && strlen(trim($motivo_divergencia)) < 6) {
     echo '<div class="sige-alert sige-alert-error"><p>Existe divergência entre o sistema e o valor contado. Descreva o motivo antes de fechar.</p></div>';
 } else {

 $existente = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tF WHERE escola_id = %d AND data_caixa = %s LIMIT 1", $escola_id, $dc));

 $recon_human_note = sprintf(
     "FECHO DE CAIXA COM RECONCILIAÇÃO\nSistema bruto: %s %s | Contado: %s %s | Diferença: %s %s | Estornos: %s %s | Despesas: %s %s | Saldo estimado: %s %s%s",
     number_format((float)$total_bruto, 2, ',', '.'), sige_moeda(),
     number_format((float)$sg_recon_post_payload['counted']['gross'], 2, ',', '.'), sige_moeda(),
     number_format((float)$sg_recon_post_difference, 2, ',', '.'), sige_moeda(),
     number_format((float)$total_estorno, 2, ',', '.'), sige_moeda(),
     number_format((float)$total_despesas_dia, 2, ',', '.'), sige_moeda(),
     number_format((float)$sg_recon_post_payload['counted']['estimated_balance_after_adjustments'], 2, ',', '.'), sige_moeda(),
     $motivo_divergencia !== '' ? "\nObservação: " . $motivo_divergencia : ''
 );
 $observacoes_payload = $recon_human_note . sige_extracto_recon_marker_encode($sg_recon_post_payload);
 if ($existente && !empty($existente->observacoes)) {
     $obs_historico_anterior = sige_extracto_recon_clean_note($existente->observacoes);
     if ($obs_historico_anterior !== '') {
         $observacoes_payload .= "

HISTÓRICO ANTERIOR:
" . $obs_historico_anterior;
     }
 }

 $payload = [

 'escola_id' => $escola_id,

 'total_bruto' => $total_bruto,

 'total_estornos' => $total_estorno,

 'total_liquido' => $total_liquido,

 'total_por_metodo' => wp_json_encode($resumo, JSON_UNESCAPED_UNICODE),

 'fechado_por' => get_current_user_id(),

 'data_fecho' => current_time('mysql'),

 'status' => 'fechado',

 'fechado_em' => current_time('mysql'),

 'resumo_metodos' => sige_fin_resumo_metodos_texto($resumo),

 'observacoes' => $observacoes_payload,

 ];

 $payload = sige_extracto_filter_existing_columns($tF, $payload);

 if ($existente) {

 $ok = $wpdb->update($tF, $payload, ['id' => (int)$existente->id]);

 } else {

 $payload['data_caixa'] = $dc;

 $ok = $wpdb->insert($tF, $payload);

 }

 if ($ok === false) {

 echo '<div class="sige-alert sige-alert-error"><p>Erro ao fechar caixa: ' . esc_html($wpdb->last_error) . '</p></div>';

 } else {

 if (function_exists('sige_fin_log')) sige_fin_log('caixa_fechado_reconciliado', ['dc' => $dc, 'total_bruto' => $total_bruto, 'total_estorno' => $total_estorno, 'total_liquido' => $total_liquido, 'despesas' => $total_despesas_dia, 'divergencia' => $sg_recon_post_difference, 'resumo' => $resumo]);
 if (function_exists('sige_audit_log')) sige_audit_log('caixa_fechado_reconciliado', ['data_caixa' => $dc, 'divergencia' => $sg_recon_post_difference, 'total_contado' => (float)$sg_recon_post_payload['counted']['gross']], 'financeiro');

 echo '<div class="sige-alert sige-alert-success"><p>Caixa fechado para <strong>' . esc_html($dc) . '</strong> com reconciliação. Diferença registada: <strong>' . esc_html(number_format($sg_recon_post_difference, 2, ',', '.')) . ' ' . esc_html(sige_moeda()) . '</strong>.</p></div>';

 $fecho_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tF WHERE escola_id=%d AND data_caixa=%s ORDER BY id DESC LIMIT 1", $escola_id, $data_filtro));

 $dia_fechado = ($fecho_row && $fecho_row->status === 'fechado');

 }

 }
 }

}

// ─────────────────────────────────────────────

// POST: ESTORNO (modo diário, caixa aberto)

// ─────────────────────────────────────────────

if (!$sg_em_modo_aluno && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_anular_recibo'])) {

    // [Fase 7 incr 2] Regra de quatro-olhos: este handler ja nao executa o
    // estorno. Cria um pedido pendente (sige_fin_aprovacao_solicitar) que tem de
    // ser aprovado por OUTRO utilizador autorizado na pagina de Aprovacoes. So na
    // aprovacao corre o servico de estorno (com MFA, FOR UPDATE, FSM e ledger).
    //
    // [F5] O check de fecho de caixa passa a ser POR TURNO (user+data)
    // em vez de pelo dia inteiro. Uma secretária pode estornar o seu
    // recibo mesmo que outra já tenha fechado o dela.

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_anular_recibo')) {
        echo '<div class="sige-alert sige-alert-error"><p>Pedido inválido.</p></div>';
    } else {
        // [Fase 7 incr 2] Regra de quatro-olhos: o estorno deixa de executar de
        // imediato. Cria-se um pedido pendente que tem de ser aprovado por OUTRO
        // utilizador autorizado (na pagina de Aprovacoes). A execucao real, com
        // MFA, FOR UPDATE e ledger, acontece na aprovacao via estornarPagamento.
        $sg_pag_estorno   = sige_fin_post_int('recibo_id_anular');
        $sg_motivo_estorno = sige_fin_post_param('motivo_anulacao');
        $sg_valor_estorno  = (float) str_replace(',', '.', (string) sige_fin_post_param('valor_estorno'));
        if (trim($sg_motivo_estorno) === '') {
            echo '<div class="sige-alert sige-alert-error"><p>O motivo do estorno é obrigatório.</p></div>';
        } elseif (!function_exists('sige_fin_aprovacao_solicitar')) {
            echo '<div class="sige-alert sige-alert-error"><p>Serviço de aprovações indisponível.</p></div>';
        } else {
            $sg_req = sige_fin_aprovacao_solicitar('estorno_pagamento', $sg_pag_estorno, [
                'motivo' => $sg_motivo_estorno,
                'valor'  => $sg_valor_estorno,
            ], $escola_id, 'pagamento #' . $sg_pag_estorno);
            if (!empty($sg_req['ok'])) {
                echo '<div class="sige-alert sige-alert-success"><p>Pedido de estorno do pagamento <strong>#'
                    . esc_html((string) $sg_pag_estorno)
                    . '</strong> submetido para aprovação. O estorno só será efectuado depois de aprovado por outro utilizador autorizado, na página de <strong>Aprovações</strong>.</p></div>';
            } else {
                echo '<div class="sige-alert sige-alert-error"><p>' . esc_html($sg_req['error'] ?? 'Não foi possível submeter o pedido.') . '</p></div>';
            }
        }
    }
}

// ─────────────────────────────────────────────

// QUERY PRINCIPAL: EXTRATO

// ─────────────────────────────────────────────

if ($modo_aluno) {

 $__sige_data_pag_sql_aluno = function_exists('sige_fin_data_pag_sql_clause') ? sige_fin_data_pag_sql_clause('p') : 'DATE(p.data_pagamento)';

 $extrato = $wpdb->get_results($wpdb->prepare(

 "SELECT p.*, a.nome_completo, a.numero_processo, l.descricao, l.mes_referencia,
        COALESCE(NULLIF(s.nome, ''), NULLIF(l.descricao, ''), 'Serviço não identificado') AS servico_nome,
        COALESCE(s.ciclo, '') AS servico_ciclo,
        COALESCE(s.classe, '') AS servico_classe,
        NULL as recepcionista,
        (
          SELECT t.classe
          FROM {$wpdb->prefix}sige_matriculas m
          LEFT JOIN {$wpdb->prefix}sige_turmas t ON t.id = m.turma_id AND t.escola_id = m.escola_id
          WHERE m.aluno_id = p.aluno_id AND m.escola_id = p.escola_id
          ORDER BY CASE WHEN m.status_matricula = 'activa' THEN 0 ELSE 1 END, m.ano_lectivo DESC, m.id DESC
          LIMIT 1
        ) AS classe_atual

 FROM $tP p

 JOIN {$wpdb->prefix}sige_alunos a ON p.aluno_id = a.id

 LEFT JOIN $tL l ON p.lancamento_id = l.id

 LEFT JOIN $tS s ON s.id = l.servico_id AND s.escola_id = p.escola_id

 WHERE p.aluno_id = %d AND p.escola_id = %d
       {$_centro_sql_p}

 ORDER BY {$__sige_data_pag_sql_aluno} DESC, p.data_pagamento DESC",

 $aluno_id, $escola_id

 ));

 $aluno_dados = $wpdb->get_row($wpdb->prepare(

 "SELECT nome_completo, numero_processo FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d", $aluno_id, $escola_id

 ));

 // Totais do histórico do aluno

 $hist_total = array_sum(array_map(fn($r) => (float)$r->valor_pago, $extrato));

 $hist_entradas = array_sum(array_filter(array_map(fn($r) => (float)$r->valor_pago > 0 ? (float)$r->valor_pago : 0, $extrato)));

 $hist_estornos = array_sum(array_filter(array_map(fn($r) => (float)$r->valor_pago < 0 ? abs((float)$r->valor_pago) : 0, $extrato)));

} elseif (!$sg_em_modo_aluno) {

 $__sige_data_pag_sql = function_exists('sige_fin_data_pag_sql_clause') ? sige_fin_data_pag_sql_clause('p') : 'DATE(p.data_pagamento)';
 $extrato = $wpdb->get_results($wpdb->prepare(

 "SELECT p.*, a.nome_completo, a.numero_processo, l.descricao, l.mes_referencia,
        COALESCE(NULLIF(s.nome, ''), NULLIF(l.descricao, ''), 'Serviço não identificado') AS servico_nome,
        COALESCE(s.ciclo, '') AS servico_ciclo,
        COALESCE(s.classe, '') AS servico_classe,
        u.user_login as recepcionista,
        (
          SELECT t.classe
          FROM {$wpdb->prefix}sige_matriculas m
          LEFT JOIN {$wpdb->prefix}sige_turmas t ON t.id = m.turma_id AND t.escola_id = m.escola_id
          WHERE m.aluno_id = p.aluno_id AND m.escola_id = p.escola_id
          ORDER BY CASE WHEN m.status_matricula = 'activa' THEN 0 ELSE 1 END, m.ano_lectivo DESC, m.id DESC
          LIMIT 1
        ) AS classe_atual

 FROM $tP p

 JOIN {$wpdb->prefix}sige_alunos a ON p.aluno_id = a.id

 LEFT JOIN $tL l ON p.lancamento_id = l.id

 LEFT JOIN $tS s ON s.id = l.servico_id AND s.escola_id = p.escola_id

 LEFT JOIN {$wpdb->prefix}users u ON p.recebido_por = u.ID

 WHERE p.escola_id = %d AND {$__sige_data_pag_sql} BETWEEN %s AND %s
       {$_centro_sql_p}

 ORDER BY p.data_pagamento DESC",

 $escola_id, $data_inicio_relatorio, $data_fim_relatorio

 ));

 // [12.9.24] Filtro por ciclo aplicado depois da consulta para evitar mexer na
 // matemática financeira e em joins críticos. Apenas remove da visualização/exportação
 // os movimentos fora do segmento seleccionado.
 if ($rel_ciclo !== 'todos' && !empty($extrato)) {
     $map_ciclos = ['creche' => 'Creche', 'primario' => 'Primário', 'secundario' => 'Secundário'];
     $ciclo_alvo = $map_ciclos[$rel_ciclo] ?? '';
     $extrato = array_values(array_filter($extrato, function($row) use ($ciclo_alvo) {
         return sige_extracto_ciclo_label($row->classe_atual ?? '', $row->servico_ciclo ?? '', $row->servico_nome ?? '') === $ciclo_alvo;
     }));
 }

 // Quando o relatório deixa de ser o caixa diário puro, os cartões passam a
 // reflectir somente os movimentos exibidos/exportados. Não altera pagamentos;
 // apenas evita misturar KPIs do dia com um extracto mensal/anual/segmentado.
 if ($rel_periodo !== 'diario' || $rel_ciclo !== 'todos') {
     $resumo = ['numerario' => 0, 'mpesa' => 0, 'emola' => 0, 'emola_comerciante' => 0, 'bim' => 0, 'bci' => 0, 'pagafacil' => 0, 'nib' => 0, 'transferencia' => 0, 'pos_bci' => 0, 'pos_bim' => 0, 'pos_stbank' => 0, 'pos_moza' => 0, 'pos_nedbank' => 0, 'pos_fnb' => 0, 'pos' => 0, 'estorno' => 0];
     $total_bruto = 0.0;
     $total_estorno = 0.0;
     $total_liquido = 0.0;
     foreach ($extrato as $__mov) {
         $__valor = (float)($__mov->valor_pago ?? 0);
         $__metodo = (string)($__mov->metodo_pagamento ?? '');
         if (!array_key_exists($__metodo, $resumo)) { $resumo[$__metodo] = 0; }
         if ($__valor < 0 || $__metodo === 'estorno') {
             $total_estorno += abs($__valor);
             $resumo['estorno'] += abs($__valor);
         } else {
             $total_bruto += $__valor;
             $resumo[$__metodo] += $__valor;
         }
         $total_liquido += $__valor;
     }
     $total_despesas_dia = 0.0;
     $despesas_dia = [];
     $saldo_dia = $total_liquido;
 }

}

// ─────────────────────────────────────────────

// PESQUISA DE ALUNOS (autocomplete via GET ?q=)

// ─────────────────────────────────────────────

$alunos_search = [];

$_q_raw = sige_fin_get_param('q');
 if ($_q_raw !== '' && strlen(trim($_q_raw)) >= 2) {

 $q = '%' . $wpdb->esc_like(sige_fin_get_param('q')) . '%';

 $alunos_search = $wpdb->get_results($wpdb->prepare(

 "SELECT id, nome_completo, numero_processo FROM {$wpdb->prefix}sige_alunos WHERE escola_id=%d AND (nome_completo LIKE %s OR numero_processo LIKE %s) ORDER BY nome_completo LIMIT 10", $escola_id, $q, $q

 ));

}

// ─────────────────────────────────────────────

// UI HELPERS

// ─────────────────────────────────────────────

$estado = 'aberto';

$pill_class = 'pill-aberto';

$pill_txt = 'Caixa aberta';

if (!$sg_em_modo_aluno && $fecho_row) {

 if ($fecho_row->status === 'fechado') { $estado = 'fechado'; $pill_class = 'pill-fechado'; $pill_txt = 'Caixa fechada'; }

 if ($fecho_row->status === 'reaberto') { $estado = 'reaberto'; $pill_class = 'pill-reaberto'; $pill_txt = 'Caixa reaberta'; }

}

// [v12.11.9.88] Histórico de fechos para a data seleccionada. Compatível com
// instalações antigas, com ou sem user_id_turno.
$sg_close_history = [];
$sg_close_recon = $fecho_row ? sige_extracto_recon_parse($fecho_row->observacoes ?? '') : [];
if (!$sg_em_modo_aluno && $rel_periodo === 'diario') {
    $sg_hist_user_col = sige_extracto_table_has_column($tF, 'user_id_turno') ? 'COALESCE(f.user_id_turno, f.fechado_por)' : 'f.fechado_por';
    $sg_close_history = $wpdb->get_results($wpdb->prepare(
        "SELECT f.*, u.display_name AS user_nome
           FROM $tF f
           LEFT JOIN {$wpdb->users} u ON u.ID = {$sg_hist_user_col}
          WHERE f.escola_id = %d AND f.data_caixa = %s
          ORDER BY COALESCE(f.fechado_em, f.data_fecho) DESC, f.id DESC
          LIMIT 12",
        $escola_id, $data_filtro
    )) ?: [];
}

?>

<!-- ═══════════════════════════════════════════

 SCRIPTS & STYLES

═══════════════════════════════════════════ -->

<?php echo sige_cdn_script("xlsx"); ?>
<?php echo sige_cdn_script("exceljs"); ?>
<?php echo sige_cdn_script("filesaver"); ?>

<style>


/* ── Variables ── */

:root {

 --navy: var(--sg-theme-primary-800,var(--color-ink-700));

 --navy-dark: var(--color-info-900);

 --navy-light: var(--color-ink-100);

 --accent: var(--color-info-500);

 --surface: var(--color-white);

 --surface-2: var(--color-slate-50);

 --border: var(--color-ink-100);

 --text: var(--color-ink-500);

 --muted: var(--color-slate-500);

 --success: var(--color-success-500);

 --success-bg: var(--color-success-100);

 --warning: var(--color-warning-600);

 --warning-bg: var(--color-warning-100);

 --danger: var(--color-danger-600);

 --danger-bg: var(--color-danger-50);

 --info: var(--sg-theme-primary,var(--color-brand-500));

 --info-bg: var(--sg-theme-soft,var(--color-brand-50));

 --mpesa: var(--color-danger-600);

 --emola: var(--color-danger-500);

 --cash: var(--color-success-800);

 --pos: var(--sg-theme-primary,var(--color-brand-500));

 --radius: 14px;

 --radius-sm: 8px;

 --shadow: 0 2px 12px rgba(26,35,126,0.08);

 --shadow-md: 0 4px 24px rgba(26,35,126,0.13);

 --font: 'DM Sans', sans-serif;

 --mono: 'DM Mono', monospace;

 --transition: .18s ease;

}

/* ── Reset & Base ── */

.sige-wrap { font-family: var(--font); color: var(--text); max-width: 1400px; padding:0 var(--space-1); }

.sige-wrap *, .sige-wrap *::before, .sige-wrap *::after { box-sizing: border-box; }

/* ── Alerts ── */

.sige-alert {

 display: flex; align-items: flex-start; gap: 10px;

 padding: 13px 16px; border-radius: var(--radius-sm);

 margin-bottom: 16px; font-size:var(--fs-base);

 animation: sige-slideDown .3s ease;

}

.sige-alert p { margin: 0; }

.sige-alert-success { background: var(--success-bg); border-left: 4px solid var(--success); color: var(--color-success-900); }

.sige-alert-error { background: var(--danger-bg); border-left: 4px solid var(--danger); color: var(--color-danger-800); }

.sige-alert-warning { background: var(--warning-bg); border-left: 4px solid var(--warning); color: var(--color-warning-800); }

/* ── Page Header ── */

.sige-header {

 display: flex; align-items: center; justify-content: space-between;

 flex-wrap: wrap; gap:var(--space-3);

 margin-bottom: 22px; padding-bottom: 18px;

 border-bottom: 2px solid var(--border);

}

.sige-header h1 { font-size: 22px; font-weight:600; color: var(--navy); margin: 0 0 3px; letter-spacing: -.4px; }

.sige-header p { font-size:var(--fs-sm); color: var(--muted); margin: 0; }

/* ── Status Pill ── */

.sige-status-pill {

 display: inline-flex; align-items: center; gap: 6px;

 padding: 5px 13px; border-radius:var(--radius-pill);

 font-size:var(--fs-xs); font-weight:600; letter-spacing: .3px; text-transform: uppercase;

}

.pill-fechado { background: var(--color-danger-100); color: var(--color-danger-700); border: 1px solid var(--color-danger-200); }

.pill-aberto { background: var(--color-success-100); color: var(--color-success-900); border: 1px solid var(--color-success-300); }

.pill-reaberto { background: var(--color-warning-200); color: var(--color-warning-800); border: 1px solid var(--color-warning-400); }

.pill-aluno { background: var(--info-bg); color: var(--info); border: 1px solid var(--color-info-200); }

.pill-dot { width: 7px; height: 7px; border-radius: 50%; }

.pill-fechado .pill-dot { background: var(--color-danger-700); }

.pill-aberto .pill-dot { background: var(--color-success-500); animation: sige-pulse 2s infinite; }

.pill-reaberto .pill-dot { background: var(--color-warning-700); animation: sige-pulse 2s infinite; }

.pill-aluno .pill-dot { background: var(--info); }

/* ── Mode Toggle ── */

.sige-mode-toggle {

 background: var(--surface);

 border: 1px solid var(--border);

 border-radius: var(--radius);

 padding:var(--space-4) var(--space-5);

 margin-bottom: 18px;

 box-shadow:var(--shadow-xs);

}

.sige-mode-toggle-title {

 font-size:var(--fs-xs); font-weight:600; text-transform: uppercase;

 letter-spacing: .5px; color: var(--muted); margin-bottom: 12px;

}

.sige-mode-options { display: flex; gap: 10px; flex-wrap: wrap; }

.sige-mode-btn {

 display: inline-flex; align-items: center; gap:var(--space-2);

 padding: 10px 18px; border-radius:var(--radius-sm);

 font-family: var(--font); font-size:var(--fs-sm); font-weight:600;

 cursor: pointer; transition: all var(--transition);

 border: 2px solid var(--border); background: var(--surface-2);

 color: var(--muted); text-decoration: none;

}

.sige-mode-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--navy-light); }

.sige-mode-btn:focus-visible { outline: 3px solid rgba(90,63,214,.35); outline-offset: 2px; }

.sige-mode-btn.active { border-color: var(--navy); background: var(--navy-light); color: var(--navy); box-shadow: inset 0 0 0 1px var(--navy), 0 8px 20px rgba(15,39,71,.12); }

.sige-mode-btn.active small { color: var(--navy); opacity: .85; }

.sige-mode-btn .mode-icon { font-size:var(--fs-md); }

/* ── Search Section (modo aluno) ── */

.sige-search-box {

 background: var(--surface);

 border: 1px solid var(--border);

 border-radius: var(--radius);

 padding: 18px 20px;

 margin-bottom: 18px;

 box-shadow:var(--shadow-xs);

}

.sige-search-box-title {

 font-size: 12px; font-weight:600; color: var(--muted);

 text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px;

}

.sige-search-row {

 display: flex; gap: 10px; align-items: center; flex-wrap: wrap;

}

.sige-input {

 flex: 1; min-width: 200px;

 padding: 9px 14px;

 border: 1px solid var(--border); border-radius: var(--radius-sm);

 font-family: var(--font); font-size:var(--fs-base); color: var(--text);

 background: var(--surface-2);

 transition: border-color var(--transition), background var(--transition);

 outline: none;

}

.sige-input:focus { border-color: var(--accent); background: var(--color-white); box-shadow:var(--shadow-xs); }

.sige-results-list {

 display: flex; gap:var(--space-2); flex-wrap: wrap; margin-top: 12px;

 padding-top: 12px; border-top: 1px solid var(--border);

}

.sige-result-item {

 display: inline-flex; align-items: center; gap: 7px;

 padding: 7px 13px; border-radius:var(--radius-pill);

 background: var(--info-bg); color: var(--info);

 border: 1px solid var(--color-info-200);

 font-size: 12px; font-weight:600;

 text-decoration: none; transition: all var(--transition);

}

.sige-result-item:hover { background: var(--info); color: var(--color-white); border-color: var(--info); }

.sige-result-proc { font-size: 10px; opacity: .7; font-family: var(--mono); }

/* ── Aluno Banner ── */

.sige-aluno-banner {

 background: linear-gradient(135deg, var(--info-bg), var(--sg-theme-soft,var(--color-brand-50)));

 border: 1px solid var(--color-info-200);

 border-radius: var(--radius);

 padding:var(--space-4) var(--space-5);

 margin-bottom: 18px;

 display: flex; align-items: center; justify-content: space-between;

 flex-wrap: wrap; gap:var(--space-3);

}

.sige-aluno-banner-info h3 { font-size:var(--fs-md); font-weight:600; color: var(--info); margin: 0 0 3px; }

.sige-aluno-banner-info p { font-size: 12px; color: var(--sg-theme-primary,var(--color-brand-500)); margin: 0; }

.sige-aluno-stats { display: flex; gap:var(--space-4); flex-wrap: wrap; }

.sige-aluno-stat { text-align: center; }

.sige-aluno-stat-val { font-size: 18px; font-weight:600; font-family: var(--mono); color: var(--info); }

.sige-aluno-stat-lbl { font-size: 10px; text-transform: uppercase; font-weight:600; color: var(--color-info-300); letter-spacing: .4px; }
.sg-hist-pro-download{margin:-6px 0 18px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:18px 20px;border-radius:var(--radius-lg);background:linear-gradient(135deg,var(--color-white),var(--color-brand-50));border:1px solid rgba(90,63,214,.18);box-shadow:var(--shadow-md);position:relative}
.sg-hist-pro-download::before{content:"";position:absolute;left:0;top:14px;bottom:14px;width:4px;border-radius:var(--radius-xs);background:linear-gradient(180deg,var(--sg-theme-primary,var(--color-brand-500)),var(--navy,var(--color-info-900)))}
.sg-hist-pro-download .sg-hist-pro-head{display:flex;align-items:center;gap:11px;margin-bottom:4px}
.sg-hist-pro-download .sg-hist-pro-ic{flex:0 0 auto;width:34px;height:34px;border-radius:var(--radius-sm);display:inline-flex;align-items:center;justify-content:center;background:rgba(90,63,214,.12);color:var(--sg-theme-primary,var(--color-brand-500));font-size:18px;line-height:1}
.sg-hist-pro-download strong{display:block;font-size:15px;color:var(--color-black)}
.sg-hist-pro-download span{display:block;font-size:12px;color:var(--color-slate-500);font-weight:600;line-height:1.45}
.sg-hist-pro-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;align-items:center}
.sg-hist-pro-btn{min-height:44px;border-radius:var(--radius-md);padding:0 17px;display:inline-flex;align-items:center;gap:var(--space-2);text-decoration:none!important;font-size:12.5px;font-weight:700;border:1px solid var(--color-info-100);background:var(--color-white);color:var(--color-slate-800)!important;cursor:pointer;transition:transform .12s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease;-webkit-tap-highlight-color:transparent}
.sg-hist-pro-btn .sg-hist-ic{font-size:15px;line-height:1}
.sg-hist-pro-btn.primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)));color:var(--color-white)!important;border-color:transparent;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sg-hist-pro-btn:hover{transform:translateY(-1px);border-color:var(--sg-theme-primary,var(--color-brand-500));box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sg-hist-pro-btn.primary:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sg-hist-pro-btn:active{transform:translateY(1px) scale(.99);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sg-hist-pro-btn:focus-visible{outline:3px solid rgba(90,63,214,.40);outline-offset:2px}
.sg-hist-pro-btn.is-loading{pointer-events:none;opacity:.85}
.sg-hist-pro-btn.is-loading .sg-hist-ic{animation:sgHistSpin .8s linear infinite}
@keyframes sgHistSpin{to{transform:rotate(360deg)}}
.sg-hist-pro-foot{grid-column:1 / -1;margin-top:2px;font-size:var(--fs-xs);color:var(--color-slate-400);font-weight:600;display:flex;align-items:center;gap:6px}
@media(max-width:760px){.sg-hist-pro-download{grid-template-columns:1fr}.sg-hist-pro-actions{justify-content:stretch}.sg-hist-pro-btn{flex:1;justify-content:center}.sg-hist-pro-foot{justify-content:center;text-align:center}}

/* ── Toolbar ── */

.sige-toolbar {

 background: var(--surface);

 border: 1px solid var(--border);

 border-radius: var(--radius);

 padding: 13px 18px;

 margin-bottom: 18px;

 display: flex; align-items: center; justify-content: space-between;

 gap:var(--space-3); flex-wrap: wrap;

 box-shadow:var(--shadow-xs);

}

.sige-toolbar-left,

.sige-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.sige-date-group {

 display: flex; align-items: center; gap:var(--space-2);

 background: var(--surface-2); border: 1px solid var(--border);

 border-radius: var(--radius-sm); padding: 6px 12px;

}

.sige-date-group label { font-size:var(--fs-xs); font-weight:600; color: var(--muted); white-space: nowrap; }

.sige-date-group input[type="date"],
.sige-date-group select {

 border: none; background: transparent;

 font-family: var(--font); font-size:var(--fs-base); color: var(--text);

 outline: none; cursor: pointer;

}

/* ── Buttons ── */

.sige-btn {

 display: inline-flex; align-items: center; gap: 6px;

 padding:var(--space-2) var(--space-4); border-radius: var(--radius-sm);

 font-family: var(--font); font-size:var(--fs-sm); font-weight:600;

 cursor: pointer; transition: all var(--transition);

 border: 1px solid transparent; text-decoration: none;

 line-height: 1; white-space: nowrap;

}

.sige-btn-primary { background: var(--navy); color: var(--color-white); border-color: var(--navy); }

.sige-btn-primary:hover { background: var(--navy-dark); border-color: var(--navy-dark); color: var(--color-white); }

.sige-btn-success { background: var(--success); color: var(--color-white); border-color: var(--success); }

.sige-btn-success:hover { background: var(--color-success-800); color: var(--color-white); }

.sige-btn-danger { background: var(--danger); color: var(--color-white); border-color: var(--danger); }

.sige-btn-danger:hover { background: var(--color-danger-700); }

.sige-btn-outline { background: var(--color-white); color: var(--navy); border-color: var(--border); }

.sige-btn-outline:hover { background: var(--navy-light); border-color: var(--accent); }

.sige-btn-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--color-warning-300); }

.sige-btn-warning:hover { background: var(--color-warning-200); }

.sige-btn-ghost { background: transparent; color: var(--muted); border-color: transparent; }

.sige-btn-ghost:hover { background: var(--surface-2); color: var(--text); }

.sige-btn-sm { padding: 5px 11px; font-size: 12px; }

/* ── KPI Cards ── */

.sige-kpi-grid {

 display: grid; grid-template-columns: 1fr; gap: 14px; margin-bottom: 20px;

}

@media (min-width: 480px) { .sige-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

@media (min-width: 768px) { .sige-kpi-grid { grid-template-columns: repeat(3, 1fr); } }

@media (min-width: 1100px) { .sige-kpi-grid { grid-template-columns: repeat(5, 1fr); } }

.kpi-card {

 background: var(--surface); border: 1px solid var(--border);

 border-radius: var(--radius); padding: 18px 20px;

 box-shadow:var(--shadow-xs); position: relative; overflow: hidden;

 transition: transform .2s ease, box-shadow .2s ease;

}

.kpi-card:hover { transform: translateY(-2px); box-shadow:var(--shadow-xs); }

.kpi-card::before {

 content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;

}

.kpi-card.total::before { background: linear-gradient(90deg, var(--navy), var(--accent)); }

.kpi-card.mpesa::before { background: var(--mpesa); }

.kpi-card.emola::before { background: var(--emola); }

.kpi-card.cash::before { background: var(--cash); }

.kpi-card.pos::before { background: var(--pos); }

.kpi-card.bim::before { background: var(--color-danger-800); }

.kpi-card.bci::before { background: var(--color-slate-800); }

.kpi-card.pagafacil::before { background: var(--sg-theme-primary-800,var(--color-ink-700)); }

.kpi-icon {

 width: 36px; height: 36px; border-radius:var(--radius-sm);

 display: flex; align-items: center; justify-content: center;

 font-size:var(--fs-md); margin-bottom: 12px;

}

.kpi-card.total .kpi-icon { background: var(--navy-light); }

.kpi-card.mpesa .kpi-icon { background: var(--color-danger-50); }

.kpi-card.emola .kpi-icon { background: var(--color-warning-100); }

.kpi-card.cash .kpi-icon { background: var(--color-success-100); }

.kpi-card.pos .kpi-icon { background: var(--info-bg); }

.kpi-label { font-size: 10px; font-weight:600; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); margin-bottom: 6px; }

.kpi-value { font-size: 21px; font-weight:600; font-family: var(--mono); letter-spacing: -.5px; line-height: 1; color: var(--text); }

.kpi-card.total .kpi-value { color: var(--navy); }

.kpi-unit { font-size:var(--fs-sm); font-weight:500; color: var(--muted); margin-left: 2px; }

.kpi-sub { margin-top: 8px; font-size:var(--fs-xs); color: var(--muted); line-height: 1.5; }

/* ── Closed Banner ── */

.sige-closed-banner {

 background: linear-gradient(135deg, var(--color-danger-50), var(--color-danger-100));

 border: 1px solid var(--color-danger-200); border-radius: var(--radius);

 padding: 13px 18px; display: flex; align-items: center; gap:var(--space-3);

 margin-bottom: 18px; font-size:var(--fs-base); color: var(--color-danger-800);

}

/* ── Table Card ── */

.sige-table-card {

 background: var(--surface); border: 1px solid var(--border);

 border-radius: var(--radius); box-shadow:var(--shadow-xs); overflow: hidden;

}

.sige-table-head {

 padding: 15px 20px; border-bottom: 1px solid var(--border);

 display: flex; align-items: center; justify-content: space-between;

 flex-wrap: wrap; gap: 10px;

}

.sige-table-head h3 { font-size: 15px; font-weight:600; color: var(--navy); margin: 0; }

.sige-table-head-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.sige-count-badge {

 background: var(--navy-light); color: var(--navy);

 padding: 3px 10px; border-radius:var(--radius-pill); font-size: 12px; font-weight:600;

}

.sige-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ── Table ── */

.sige-table { width: 100%; border-collapse: collapse; font-size:var(--fs-sm); }

.sige-table thead th {

 background: var(--surface-2); padding: 11px 14px;

 text-align: left; font-size: 10px; font-weight:600;

 text-transform: uppercase; letter-spacing: .5px;

 color: var(--muted); border-bottom: 1px solid var(--border);

 white-space: nowrap;

}

.sige-table thead th.th-check { width: 42px; padding-left: 16px; }

.sige-table thead th.th-right { text-align: right; }

.sige-table thead th.th-center { text-align: center; }

.sige-table tbody td {

 padding: 12px 14px; border-bottom: 1px solid var(--color-info-50);

 vertical-align: middle; color: var(--text);

}

.sige-table tbody tr:last-child td { border-bottom: none; }

.sige-table tbody tr:hover td { background: var(--color-slate-50); }

.sige-table tbody tr.row-negativo td { background: var(--color-danger-50); }

.sige-table tbody tr.row-negativo:hover td { background: var(--color-danger-100); }

.sige-table tbody tr.row-selected td { background: var(--navy-light) !important; }

.sige-table tfoot td {

 padding: 13px 14px; background: var(--surface-2);

 border-top: 2px solid var(--border); font-weight:600;

}

/* ── Checkbox ── */

.sige-cb {

 width: 16px; height: 16px; cursor: pointer; accent-color: var(--navy);

}

/* ── Bulk Action Bar ── */

.sige-bulk-bar {

 position: sticky; bottom: 0; z-index: 10;

 background: var(--navy); color: var(--color-white);

 padding:var(--space-3) var(--space-5);

 display: flex; align-items: center; justify-content: space-between;

 flex-wrap: wrap; gap:var(--space-3);

 border-top: 1px solid rgba(255,255,255,.1);

 border-radius: 0 0 var(--radius) var(--radius);

 transition: opacity .2s ease;

}

.sige-bulk-bar.hidden { opacity: 0; pointer-events: none; }

.sige-bulk-info { font-size:var(--fs-sm); font-weight:600; opacity: .9; }

.sige-bulk-info strong { color: var(--color-white); }

.sige-bulk-actions { display: flex; gap:var(--space-2); flex-wrap: wrap; }

.sige-btn-white {

 background: var(--color-white); color: var(--navy); border-color: var(--color-white);

 display: inline-flex; align-items: center; gap: 6px;

 padding: 7px 15px; border-radius: var(--radius-sm);

 font-family: var(--font); font-size:var(--fs-sm); font-weight:600;

 cursor: pointer; transition: all var(--transition); border: 1px solid;

 text-decoration: none; line-height: 1;

}

.sige-btn-white:hover { background: var(--navy-light); color: var(--navy); }

/* ── Badges ── */

.sige-badge {

 display: inline-flex; align-items: center; gap:var(--space-1);

 padding: 3px 9px; border-radius:var(--radius-pill);

 font-size: 10px; font-weight:700; text-transform: uppercase; letter-spacing: .3px;

 white-space: nowrap;

}

.badge-mpesa { background: var(--color-danger-50); color: var(--mpesa); border: 1px solid var(--color-danger-200); }

.badge-emola { background: var(--color-warning-100); color: var(--emola); border: 1px solid var(--color-warning-300); }

.badge-numerario { background: var(--color-success-100); color: var(--cash); border: 1px solid var(--color-success-300); }

.badge-pos { background: var(--info-bg); color: var(--pos); border: 1px solid var(--color-info-200); }

.badge-banco { background: var(--info-bg); color: var(--pos); border: 1px solid var(--color-info-200); }

.badge-bim { background: var(--color-danger-100); color: var(--color-danger-800); border: 1px solid var(--color-danger-200); }

.badge-bci { background: var(--color-slate-100); color: var(--color-slate-800); border: 1px solid var(--color-slate-200); }

.badge-pagafacil { background: var(--sg-theme-soft,var(--color-brand-50)); color: var(--sg-theme-primary-800,var(--color-ink-700)); border: 1px solid var(--color-info-200); }

.badge-nib { background: var(--color-brand-100); color: var(--color-brand-800); border: 1px solid var(--color-brand-200); }

.badge-estorno { background: var(--color-danger-50); color: var(--color-danger-700); border: 1px solid var(--color-danger-200); }

/* ── Cell helpers ── */

.cell-student-name { font-weight:600; color: var(--text); line-height: 1.3; }

.cell-student-proc { font-size:var(--fs-xs); color: var(--muted); font-family: var(--mono); }

.cell-id { font-family: var(--mono); font-size: 12px; font-weight:600; color: var(--muted); background: var(--surface-2); padding: 2px 7px; border-radius:var(--radius-xs); border: 1px solid var(--border); }

.cell-time { font-family: var(--mono); font-size: 12px; color: var(--muted); white-space: nowrap; }

.cell-date { font-family: var(--mono); font-size: 12px; color: var(--text); white-space: nowrap; }

.cell-val-pos { font-weight:600; font-family: var(--mono); color: var(--text); text-align: right; white-space: nowrap; }

.cell-val-neg { font-weight:600; font-family: var(--mono); color: var(--color-danger-700); text-align: right; white-space: nowrap; }

.cell-val-total { font-weight:700; font-family: var(--mono); color: var(--navy); font-size: 15px; text-align: right; }

.cell-desc { color: var(--text); font-weight:500; }

.cell-desc-note { font-size:var(--fs-xs); color: var(--color-danger-700); font-style: italic; margin-top: 2px; }

.cell-mes { font-size:var(--fs-xs); color: var(--muted); font-family: var(--mono); margin-top: 1px; }

/* ── Action buttons in table ── */

.action-cell { display: flex; gap: 6px; white-space: nowrap; }

.btn-recibo, .btn-estorno {

 display: inline-flex; align-items: center; gap:var(--space-1);

 padding: 5px 10px; border-radius:var(--radius-sm);

 font-size:var(--fs-xs); font-weight:600;

 cursor: pointer; transition: all var(--transition);

 text-decoration: none; border: 1px solid;

}

.btn-recibo { color: var(--navy); border-color: var(--border); background: var(--color-white); }

.btn-recibo:hover { background: var(--navy-light); border-color: var(--accent); color: var(--accent); }

.btn-estorno { color: var(--danger); border-color: var(--color-danger-200); background: var(--color-white); }

.btn-estorno:hover { background: var(--danger-bg); border-color: var(--danger); }

/* ── Empty state ── */

.sige-empty { text-align: center; padding: 60px 24px; }

.sige-empty-icon { font-size: 46px; opacity: .35; margin-bottom: 14px; }

.sige-empty p { font-size: 15px; color: var(--muted); margin: 0; }

/* ── Modal ── */

.sige-modal-overlay {

 display: none; position: fixed; inset: 0;

 background: rgba(15,23,42,.55); backdrop-filter: blur(4px);

 z-index: 99999; justify-content: center; align-items: center; padding:var(--space-5);

}

.sige-modal-box {

 background: var(--color-white); width: 100%; max-width: 460px;

 border-radius:var(--radius-lg); overflow: hidden;

 box-shadow:var(--shadow-lg);

 animation: sige-modalIn .25s cubic-bezier(.34,1.56,.64,1);

}

.sige-modal-hdr {

 padding: 18px 22px 15px; border-bottom: 1px solid var(--border);

 display: flex; align-items: center; gap: 10px;

}

.sige-modal-hdr h2 { margin: 0; font-size: 17px; }

.sige-modal-hdr.danger h2 { color: var(--danger); }

.sige-modal-body { padding: 18px 22px; }

.sige-modal-body p { font-size:var(--fs-base); color: var(--muted); margin: 0 0 5px; }

.sige-modal-highlight {

 background: var(--danger-bg); border: 1px solid var(--color-danger-200); border-radius:var(--radius-sm);

 padding: 10px 14px; margin:var(--space-3) 0 var(--space-4);

 font-size:var(--fs-base); color: var(--danger); font-weight:600; font-family: var(--mono);

}

.sige-modal-ftr {

 padding: 12px 22px 18px; display: flex; gap: 10px; justify-content: flex-end;

}

.sige-label { display: block; font-size:var(--fs-xs); font-weight:600; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }

.sige-textarea {

 width: 100%; border: 1px solid var(--border); border-radius:var(--radius-sm);

 padding: 10px 12px; font-family: var(--font); font-size:var(--fs-sm);

 color: var(--text); background: var(--surface-2); resize: vertical;

 transition: border-color var(--transition); outline: none;

}

.sige-textarea:focus { border-color: var(--accent); background: var(--color-white); }

/* ── Responsive ── */

@media (max-width: 900px) {

 .sige-table .col-recepcionista { display: none; }

}

@media (max-width: 660px) {

 .sige-table .col-hora { display: none; }

 .sige-table .col-recibo-num { display: none; }

 .sige-toolbar { flex-direction: column; align-items: stretch; }

 .sige-toolbar-right { justify-content: flex-start; }

 .sige-header { flex-direction: column; align-items: flex-start; }

}

@media (max-width: 480px) {

 .sige-mode-options { flex-direction: column; }

 .sige-mode-btn { justify-content: center; }

 .sige-aluno-stats { gap: 10px; }

}

/* ── Animations ── */

@keyframes sige-slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

@keyframes sige-modalIn { from { opacity: 0; transform: scale(.93) translateY(12px); } to { opacity: 1; transform: scale(1) translateY(0); } }

@keyframes sige-pulse { 0%,100% { opacity: 1; } 50% { opacity: .35; } }


/* ────────────────────────────────────────────────────────────────────────────
   v12.11.9.85 - Extractos UX/UI customization-first
   Mobile/tablet/laptop: separa tarefas, melhora leitura, tactilidade e estados.
   ──────────────────────────────────────────────────────────────────────────── */
.sg-extracts-wrap{--sgx-ink:var(--color-black);--sgx-muted:var(--color-slate-600);--sgx-line:var(--color-ink-100);--sgx-soft:var(--color-slate-50);--sgx-primary:var(--sg-theme-primary,var(--color-brand-500));--sgx-primary-dark:var(--sg-theme-primary-800,var(--color-ink-700));--sgx-green:var(--color-success-800);--sgx-red:var(--color-danger-500);--sgx-amber:var(--color-warning-700);--sgx-card:var(--color-white);--sgx-radius:22px;--sgx-shadow:0 18px 44px rgba(45,36,96,.075);--sgx-safe-bottom:env(safe-area-inset-bottom,0px)}
.sg-sr-only{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}.sg-help-text{margin:10px 0 0!important;color:var(--sgx-muted)!important;font-size:12px!important;font-weight:600!important;line-height:1.45!important}
.sg-card-eyebrow{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;color:var(--sgx-primary)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.12em!important;text-transform:uppercase!important}.sg-card-title{margin:3px 0 0!important;color:var(--sgx-ink)!important;font-size:18px!important;line-height:1.15!important;font-weight:700!important;letter-spacing:-.035em!important}.sg-card-subtitle{margin:6px 0 0!important;color:var(--sgx-muted)!important;font-size:var(--fs-sm)!important;font-weight:600!important;line-height:1.5!important}
.sg-extracts-filter-card,.sg-extracts-balance-card,.sg-extracts-cash-card,.sg-extracts-close-summary{background:var(--sgx-card)!important;border:1px solid rgba(31,32,55,.07)!important;border-radius:var(--sgx-radius)!important;box-shadow:var(--shadow-xs);margin:0 0 18px!important}.sg-extracts-filter-card{padding:18px!important}.sg-extracts-filter-head{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:14px!important;margin-bottom:14px!important}.sg-extracts-filter-grid{display:grid!important;grid-template-columns:repeat(5,minmax(132px,1fr)) auto!important;gap:10px!important;align-items:end!important;width:100%!important}.sg-extracts-filter-grid .sige-date-group{min-width:0!important}.sg-extracts-filter-grid .sige-btn{width:100%!important}.sg-extracts-filter-summary{display:flex!important;flex-wrap:wrap!important;gap:var(--space-2)!important;margin-top:14px!important;padding-top:14px!important;border-top:1px solid var(--sgx-line)!important}.sg-extracts-filter-chip{display:inline-flex!important;align-items:center!important;gap:6px!important;min-height:34px!important;padding:0 var(--space-3)!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-slate-700)!important;font-size:12px!important;font-weight:700!important}.sg-extracts-filter-chip strong{color:var(--sgx-ink)!important;font-weight:700!important}.sg-extracts-filter-chip.status-open{background:var(--color-success-100)!important;color:var(--color-success-800)!important;border-color:var(--color-success-200)!important}.sg-extracts-filter-chip.status-closed{background:var(--color-danger-50)!important;color:var(--color-danger-700)!important;border-color:var(--color-danger-100)!important}.sg-extracts-filter-chip.status-reopened{background:var(--color-warning-50)!important;color:var(--color-warning-800)!important;border-color:var(--color-warning-200)!important}
.sg-extracts-ops-grid{display:grid!important;grid-template-columns:minmax(0,1fr)!important;gap:18px!important;margin-bottom:18px!important}.sg-extracts-ops-grid.has-cash-actions{grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr)!important}.sg-extracts-balance-card,.sg-extracts-cash-card{padding:18px!important;margin:0!important}.sg-extracts-balance-head,.sg-extracts-cash-head{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:var(--space-3)!important;margin-bottom:14px!important}.sg-extracts-balance-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:10px!important}.sg-balance-mini{position:relative!important;overflow:hidden!important;padding:15px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important}.sg-balance-mini:after{content:""!important;position:absolute!important;right:-24px!important;top:-28px!important;width:82px!important;height:82px!important;border-radius:var(--radius-pill)!important;background:var(--color-brand-50)!important}.sg-balance-mini.revenue:after{background:var(--color-success-100)!important}.sg-balance-mini.expense:after{background:var(--color-danger-50)!important}.sg-balance-mini.balance.negative:after{background:var(--color-danger-50)!important}.sg-balance-mini span{position:relative!important;z-index:1!important;display:block!important;color:var(--color-slate-500)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.08em!important;text-transform:uppercase!important}.sg-balance-mini strong{position:relative!important;z-index:1!important;display:block!important;margin-top:7px!important;color:var(--sgx-ink)!important;font-size:22px!important;line-height:1.05!important;font-weight:700!important;letter-spacing:-.045em!important}.sg-balance-mini.revenue strong{color:var(--sgx-green)!important}.sg-balance-mini.expense strong,.sg-balance-mini.balance.negative strong{color:var(--sgx-red)!important}.sg-balance-mini small{position:relative!important;z-index:1!important;display:block!important;margin-top:7px!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:600!important;line-height:1.35!important}.sg-expense-details{margin-top:14px!important;border:1px solid var(--color-danger-100)!important;border-radius:var(--radius-lg)!important;background:var(--color-warning-50)!important;overflow:hidden!important}.sg-expense-details summary{cursor:pointer!important;list-style:none!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:10px!important;padding:13px 15px!important;color:var(--color-danger-700)!important;font-size:var(--fs-sm)!important;font-weight:700!important}.sg-expense-details summary::-webkit-details-marker{display:none!important}.sg-expense-details summary:after{content:"+";font-size:18px;font-weight:700}.sg-expense-details[open] summary:after{content:"-"}.sg-expense-list{padding:0 15px 14px!important}.sg-expense-row{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:10px!important;padding:10px 0!important;border-top:1px solid var(--color-danger-100)!important}.sg-expense-row strong{display:block!important;color:var(--color-slate-900)!important;font-size:12px!important;font-weight:700!important}.sg-expense-row small{display:block!important;margin-top:2px!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:600!important}.sg-expense-row em{font-style:normal!important;color:var(--color-danger-500)!important;font-size:var(--fs-sm)!important;font-weight:700!important;white-space:nowrap!important}.sg-expense-row.total{background:var(--color-danger-50)!important;margin:8px -15px -14px!important;padding:12px 15px!important}.sg-expense-row.total strong,.sg-expense-row.total em{color:var(--color-danger-700)!important}.sg-extracts-cash-card{display:flex!important;flex-direction:column!important;gap:12px!important}.sg-extracts-cash-card.is-closed{background:linear-gradient(135deg,var(--color-white),var(--color-warning-50))!important;border-color:var(--color-warning-200)!important}.sg-cash-inline-form{display:flex!important;gap:10px!important;align-items:end!important;flex-wrap:wrap!important;margin:0!important}.sg-cash-inline-form .sg-cash-field{flex:1 1 220px!important}.sg-cash-field label{display:block!important;margin-bottom:6px!important;color:var(--color-slate-600)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.07em!important;text-transform:uppercase!important}.sg-cash-note{margin:0!important;color:var(--color-slate-500)!important;font-size:12px!important;font-weight:600!important;line-height:1.45!important}.sg-cash-state-pill{display:inline-flex!important;align-items:center!important;gap:7px!important;min-height:32px!important;padding:0 11px!important;border-radius:var(--radius-pill)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.06em!important;text-transform:uppercase!important}.sg-cash-state-pill.open{background:var(--color-success-100)!important;color:var(--color-success-800)!important}.sg-cash-state-pill.closed{background:var(--color-danger-50)!important;color:var(--color-danger-700)!important}.sg-cash-state-pill.reopened{background:var(--color-warning-50)!important;color:var(--color-warning-800)!important}.sg-extracts-close-summary{overflow:hidden!important;background:linear-gradient(135deg,var(--color-info-900) 0%,var(--color-info-800) 100%)!important;color:var(--color-white)!important;border:0!important}.sg-close-summary-head{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:14px!important;padding:20px 22px 14px!important}.sg-close-summary-head span{display:block!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.12em!important;text-transform:uppercase!important;opacity:.72!important}.sg-close-summary-head strong{display:block!important;margin-top:4px!important;font-size:21px!important;line-height:1.1!important;font-weight:700!important;letter-spacing:-.035em!important}.sg-close-summary-meta{text-align:right!important;font-size:12px!important;font-weight:600!important;line-height:1.5!important;opacity:.86!important}.sg-close-method-grid{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(128px,1fr))!important;gap:10px!important;padding:0 22px 16px!important}.sg-close-method{border-radius:var(--radius-lg)!important;padding:var(--space-3)!important;background:rgba(255,255,255,.10)!important;border:1px solid rgba(255,255,255,.10)!important;text-align:center!important}.sg-close-method span{display:block!important;font-size:10px!important;letter-spacing:.08em!important;text-transform:uppercase!important;opacity:.68!important;font-weight:700!important}.sg-close-method strong{display:block!important;margin-top:5px!important;font-size:15px!important;font-weight:700!important}.sg-close-total-bar{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:var(--space-3)!important;padding:15px 22px 20px!important;border-top:1px solid rgba(255,255,255,.14)!important;flex-wrap:wrap!important}.sg-close-total-bar small{display:inline-block!important;margin-right:8px!important;opacity:.62!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important}.sg-close-total-bar strong{font-size:22px!important;font-weight:700!important;color:var(--color-warning-300)!important}
.sg-student-search-card{padding:18px!important}.sg-student-search-head{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:14px!important;margin-bottom:14px!important}.sg-student-search-form{grid-template-columns:minmax(260px,1fr) auto auto!important}.sg-results-grid{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))!important;gap:10px!important;border-top:1px solid var(--sgx-line)!important;padding-top:14px!important}.sg-results-grid .sige-result-item{display:grid!important;grid-template-columns:38px minmax(0,1fr) auto!important;gap:10px!important;align-items:center!important;border-radius:var(--radius-lg)!important;padding:11px 12px!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-ink-500)!important;box-shadow:var(--shadow-sm);text-align:left!important}.sg-results-grid .sige-result-item:hover{background:var(--color-brand-50)!important;color:var(--color-ink-500)!important;border-color:var(--color-brand-200)!important;transform:translateY(-1px)!important}.sg-result-avatar{width:38px!important;height:38px!important;border-radius:var(--radius-md)!important;display:flex!important;align-items:center!important;justify-content:center!important;background:var(--color-brand-50)!important;color:var(--sgx-primary)!important;font-weight:700!important}.sg-result-title{display:block!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important;font-size:var(--fs-sm)!important;font-weight:700!important}.sg-result-meta{display:block!important;margin-top:2px!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:600!important}.sg-result-open{color:var(--sgx-primary)!important;font-size:12px!important;font-weight:700!important}.sg-no-results{margin-top:14px!important;padding:14px!important;border-radius:var(--radius-lg)!important;background:var(--color-warning-50)!important;border:1px solid var(--color-warning-200)!important;color:var(--color-warning-800)!important;font-size:var(--fs-sm)!important;font-weight:600!important;line-height:1.45!important}.sg-student-profile-card{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:var(--space-4)!important;align-items:center!important}.sg-student-main{display:flex!important;align-items:center!important;gap:14px!important;min-width:0!important}.sg-student-avatar{width:54px!important;height:54px!important;border-radius:var(--radius-xl)!important;display:flex!important;align-items:center!important;justify-content:center!important;background:var(--color-brand-50)!important;color:var(--sgx-primary)!important;font-size:var(--fs-lg)!important;font-weight:700!important;box-shadow:inset 0 0 0 1px var(--color-brand-100)!important;flex:0 0 auto!important}.sg-student-profile-card .sige-aluno-banner-info h3{margin:0 0 5px!important}.sg-student-profile-card .sige-aluno-banner-info p{line-height:1.55!important}.sg-student-profile-card .sige-aluno-stat.is-refund .sige-aluno-stat-val{color:var(--color-danger-500)!important}.sg-extracts-center-note{margin:8px 0 18px!important;padding:12px 14px!important;background:var(--color-warning-50)!important;border:1px solid var(--color-warning-200)!important;border-radius:var(--radius-lg)!important;display:flex!important;align-items:center!important;gap:10px!important;font-size:var(--fs-sm)!important;color:var(--color-warning-800)!important;font-weight:600!important;flex-wrap:wrap!important}.sg-extracts-center-note a{margin-left:auto!important;color:var(--color-warning-800)!important;text-decoration:underline!important;font-weight:700!important}
.sg-table-title-block{min-width:0!important}.sg-table-kicker{display:block!important;margin-bottom:5px!important;color:var(--sgx-primary)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.11em!important;text-transform:uppercase!important}.sg-table-subtitle{margin:6px 0 0!important;color:var(--color-slate-500)!important;font-size:12px!important;font-weight:600!important;line-height:1.45!important}.sige-table caption{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important}.sige-table .th-check{text-align:center!important}.sige-table tbody td[data-label="Valor"]{font-variant-numeric:tabular-nums!important}.sg-mobile-table-hint{display:none!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:600!important}.sige-empty .sg-empty-actions{display:flex!important;justify-content:center!important;gap:10px!important;flex-wrap:wrap!important;margin-top:18px!important}.sige-bulk-bar{box-shadow:0 4px 16px rgba(15,23,42,.08)}.sige-bulk-bar.hidden{display:none!important}.sige-bulk-info{display:flex!important;align-items:center!important;gap:8px!important}.sige-bulk-info:before{content:"✓"!important;width:24px!important;height:24px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;border-radius:var(--radius-pill)!important;background:rgba(255,255,255,.16)!important;color:var(--color-white)!important;font-weight:700!important}
@media (max-width:1320px){.sg-extracts-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}.sg-extracts-ops-grid.has-cash-actions{grid-template-columns:1fr!important}.sg-extracts-cash-card{order:2!important}.sg-extracts-balance-card{order:1!important}}
@media (min-width:761px) and (max-width:1100px){.sg-extracts-filter-card,.sg-extracts-balance-card,.sg-extracts-cash-card,.sg-extracts-close-summary{border-radius:22px!important}.sg-extracts-balance-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}.sg-student-profile-card{grid-template-columns:1fr!important}.sg-student-profile-card .sige-aluno-stats{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important}.sg-extracts-filter-summary{overflow:auto!important;flex-wrap:nowrap!important;padding-bottom:2px!important}.sg-extracts-filter-chip{flex:0 0 auto!important}}
@media (max-width:760px){.sg-extracts-wrap{padding-bottom:calc(24px + var(--sgx-safe-bottom))!important}.sg-extracts-filter-head,.sg-extracts-balance-head,.sg-extracts-cash-head,.sg-close-summary-head,.sg-student-search-head{display:block!important}.sg-extracts-filter-grid,.sg-student-search-form{grid-template-columns:1fr!important}.sg-extracts-filter-card,.sg-extracts-balance-card,.sg-extracts-cash-card,.sg-extracts-close-summary,.sg-student-search-card{padding:15px!important;border-radius:var(--radius-xl)!important;margin-bottom:14px!important}.sg-extracts-filter-summary{display:grid!important;grid-template-columns:1fr!important}.sg-extracts-filter-chip{width:100%!important;justify-content:space-between!important}.sg-extracts-balance-grid{grid-template-columns:1fr!important}.sg-balance-mini strong{font-size:21px!important}.sg-cash-inline-form{display:grid!important;grid-template-columns:1fr!important}.sg-cash-inline-form .sige-btn,.sg-cash-inline-form .sige-input{width:100%!important}.sg-close-summary-meta{text-align:left!important;margin-top:8px!important}.sg-close-method-grid{grid-template-columns:1fr!important;padding:0 15px 14px!important}.sg-close-total-bar{display:grid!important;grid-template-columns:1fr!important;padding:14px 15px 16px!important}.sg-results-grid{grid-template-columns:1fr!important}.sg-results-grid .sige-result-item{grid-template-columns:38px minmax(0,1fr)!important}.sg-result-open{grid-column:2!important}.sg-student-profile-card{grid-template-columns:1fr!important;align-items:start!important}.sg-student-main{align-items:flex-start!important}.sg-student-profile-card .sige-aluno-stats{display:grid!important;grid-template-columns:1fr!important}.sg-extracts-center-note{display:block!important}.sg-extracts-center-note a{display:block!important;margin:8px 0 0!important}.sg-mobile-table-hint{display:block!important;margin:10px 14px 0!important}.sige-table-wrap{overflow:visible!important;padding:0 12px 12px!important}.sige-table{min-width:0!important;width:100%!important;border-collapse:separate!important;border-spacing:0!important}.sige-table thead{display:none!important}.sige-table tbody{display:grid!important;grid-template-columns:1fr!important;gap:12px!important}.sige-table tbody tr{display:block!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;box-shadow:var(--shadow-sm);overflow:hidden!important}.sige-table tbody tr.row-negativo{border-color:var(--color-danger-100)!important;background:var(--color-warning-50)!important}.sige-table tbody td{display:grid!important;grid-template-columns:minmax(96px,36%) minmax(0,1fr)!important;gap:10px!important;align-items:center!important;width:100%!important;border:0!important;border-bottom:1px solid var(--color-slate-100)!important;border-radius:0!important;background:transparent!important;padding:11px 12px!important;font-size:var(--fs-sm)!important;text-align:left!important}.sige-table tbody td:last-child{border-bottom:0!important}.sige-table tbody td::before{content:attr(data-label)!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.08em!important;text-transform:uppercase!important}.sige-table tbody td.th-check{display:flex!important;align-items:center!important;justify-content:space-between!important;background:var(--color-white)!important}.sige-table tbody td.th-check::before{content:"Selecionar"!important}.sige-table tbody td .cell-val-pos,.sige-table tbody td .cell-val-neg{justify-self:end!important}.sige-table tfoot{display:block!important;margin-top:12px!important}.sige-table tfoot tr,.sige-table tfoot td{display:block!important;width:100%!important;border:0!important;background:transparent!important;text-align:right!important}.sige-table tfoot td:not(.cell-val-total){display:none!important}.sige-table tfoot .cell-val-total{padding:14px!important;border-radius:var(--radius-lg)!important;background:var(--color-brand-50)!important;color:var(--sgx-primary)!important}.action-cell{display:grid!important;grid-template-columns:1fr!important;gap:8px!important}.btn-recibo,.btn-estorno{width:100%!important;justify-content:center!important;min-height:40px!important}.sige-bulk-bar{position:fixed!important;left:12px!important;right:12px!important;bottom:calc(12px + var(--sgx-safe-bottom))!important;border-radius:var(--radius-xl)!important;z-index:10080!important;display:grid!important;grid-template-columns:1fr!important;padding:14px!important}.sige-bulk-actions{display:grid!important;grid-template-columns:1fr 1fr!important;width:100%!important}.sige-bulk-actions .sige-btn-white{width:100%!important;min-height:44px!important}.sige-empty{padding:42px 18px!important}.sige-empty-icon{font-size:38px!important}}
@media (max-width:420px){.sg-extracts-hero-panel strong{font-size:24px!important}.sg-student-avatar{width:46px!important;height:46px!important;border-radius:16px!important}.sige-table tbody td{grid-template-columns:1fr!important;gap:4px!important}.sige-table tbody td .cell-val-pos,.sige-table tbody td .cell-val-neg{justify-self:start!important}.sige-bulk-actions{grid-template-columns:1fr!important}}
@media (prefers-reduced-motion:reduce){.sg-extracts-wrap *{transition:none!important;animation:none!important}.sg-extracts-btn:hover,.sige-btn:hover,.sige-result-item:hover{transform:none!important}}

/* v12.11.9.85 - overrides finais para vencer estilos globais com maior especificidade no mobile */
@media (max-width:760px){
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table-card{overflow:hidden!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table-wrap{overflow:visible!important;-webkit-overflow-scrolling:auto!important;padding:0 12px 12px!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table-wrap:after{content:''!important;display:none!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table{min-width:0!important;width:100%!important;border-collapse:separate!important;border-spacing:0!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table caption{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table thead{display:none!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody{display:grid!important;grid-template-columns:1fr!important;gap:12px!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody tr{display:block!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;box-shadow:var(--shadow-sm);overflow:hidden!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td,
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td:first-child,
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td:last-child{display:grid!important;grid-template-columns:minmax(96px,36%) minmax(0,1fr)!important;gap:10px!important;align-items:center!important;width:100%!important;border:0!important;border-bottom:1px solid var(--color-slate-100)!important;border-radius:0!important;background:transparent!important;padding:11px 12px!important;font-size:var(--fs-sm)!important;text-align:left!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td:last-child{border-bottom:0!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td::before{content:attr(data-label)!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.08em!important;text-transform:uppercase!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td.th-check{display:flex!important;align-items:center!important;justify-content:space-between!important;background:var(--color-white)!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td.th-check::before{content:'Seleccionar'!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td .cell-val-pos,
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td .cell-val-neg{justify-self:end!important}
}
@media (max-width:420px){
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td,
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td:first-child,
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td:last-child{grid-template-columns:1fr!important;gap:4px!important}
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td .cell-val-pos,
 body.sige-view-financeiro-extratos .sg-extracts-wrap .sige-table tbody td .cell-val-neg{justify-self:start!important}
}


/* ────────────────────────────────────────────────────────────────────────────
   v12.11.9.87 - Financeiro Extractos PRO UX Hardening
   Customization-first: presets, densidade, mobile action bar e prevenção visual
   de erro sem alterar cálculos nem regras financeiras.
   ──────────────────────────────────────────────────────────────────────────── */
.sg-filter-head-actions{display:flex!important;align-items:flex-end!important;justify-content:flex-end!important;gap:10px!important;flex-wrap:wrap!important}.sg-density-toggle{display:inline-flex!important;align-items:center!important;gap:var(--space-1)!important;padding:var(--space-1)!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;box-shadow:inset 0 0 0 1px rgba(255,255,255,.62)!important}.sg-density-toggle a{min-height:34px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;padding:0 11px!important;border-radius:var(--radius-pill)!important;color:var(--color-slate-600)!important;text-decoration:none!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.02em!important;white-space:nowrap!important}.sg-density-toggle a.active{background:var(--color-white)!important;color:var(--sgx-primary)!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}.sg-density-toggle a:focus-visible,.sg-filter-preset:focus-visible,.sg-mobile-action:focus-visible{outline:3px solid rgba(95,69,220,.35)!important;outline-offset:2px!important}.sg-filter-presets{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:9px!important;margin:0 0 14px!important}.sg-filter-preset{position:relative!important;min-height:58px!important;padding:11px 12px!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;text-decoration:none!important;color:var(--color-slate-900)!important;display:flex!important;flex-direction:column!important;justify-content:center!important;overflow:hidden!important}.sg-filter-preset:before{content:""!important;position:absolute!important;left:0!important;top:10px!important;bottom:10px!important;width:3px!important;border-radius:0 6px 6px 0!important;background:var(--color-brand-100)!important}.sg-filter-preset strong{display:block!important;color:var(--color-ink-500)!important;font-size:var(--fs-sm)!important;font-weight:700!important;line-height:1.1!important}.sg-filter-preset span{display:block!important;margin-top:4px!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:600!important;line-height:1.25!important}.sg-filter-preset.active{background:var(--color-brand-50)!important;border-color:var(--color-brand-200)!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}.sg-filter-preset.active:before{background:var(--sgx-primary)!important}.sg-filter-preset.active strong{color:var(--sgx-primary-dark)!important}.sg-critical-checklist{display:grid!important;grid-template-columns:1fr!important;gap:7px!important;margin:0!important;padding:var(--space-3)!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;list-style:none!important}.sg-critical-checklist li{position:relative!important;margin:0!important;padding-left:24px!important;color:var(--color-slate-700)!important;font-size:12px!important;font-weight:600!important;line-height:1.35!important}.sg-critical-checklist li:before{content:"✓"!important;position:absolute!important;left:0!important;top:-1px!important;width:17px!important;height:17px!important;border-radius:var(--radius-pill)!important;background:var(--color-success-100)!important;color:var(--color-success-800)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;font-size:10px!important;font-weight:700!important}.sg-extracts-wrap :is(a,button,input,select,textarea):focus-visible{outline:3px solid rgba(95,69,220,.34)!important;outline-offset:2px!important}.sg-extracts-wrap button.is-loading,.sg-extracts-wrap a.is-loading,.sg-mobile-action.is-loading{position:relative!important;pointer-events:none!important;opacity:.82!important}.sg-extracts-wrap button.is-loading:after,.sg-extracts-wrap a.is-loading:after,.sg-mobile-action.is-loading:after{content:""!important;width:13px!important;height:13px!important;border-radius:var(--radius-pill)!important;border:2px solid currentColor!important;border-right-color:transparent!important;display:inline-block!important;margin-left:7px!important;animation:sgSpin87 .75s linear infinite!important}@keyframes sgSpin87{to{transform:rotate(360deg)}}.sg-scroll-highlight{animation:sgPulseTarget87 1.25s ease!important;scroll-margin-top:90px!important}@keyframes sgPulseTarget87{0%{box-shadow:0 1px 2px rgba(15,23,42,.04)}35%{box-shadow:0 1px 2px rgba(15,23,42,.04)}100%{box-shadow:0 1px 2px rgba(15,23,42,.04)}}.sg-density-compacta .sg-extracts-filter-card,.sg-density-compacta .sg-extracts-balance-card,.sg-density-compacta .sg-extracts-cash-card,.sg-density-compacta .sige-search-box,.sg-density-compacta .sige-aluno-banner{padding:14px!important;border-radius:16px!important}.sg-density-compacta .sg-card-title{font-size:16px!important}.sg-density-compacta .sg-card-subtitle,.sg-density-compacta .sg-help-text{font-size:12px!important}.sg-density-compacta .sige-kpi-grid{gap:10px!important}.sg-density-compacta .kpi-card{min-height:94px!important;padding:15px!important;border-radius:16px!important}.sg-density-compacta .kpi-value{font-size:20px!important}.sg-density-compacta .sige-table tbody td{padding:10px 11px!important}.sg-density-compacta .sg-filter-preset{min-height:50px!important;padding:9px 10px!important}.sg-density-confortavel .sg-extracts-filter-card,.sg-density-confortavel .sg-extracts-balance-card,.sg-density-confortavel .sg-extracts-cash-card,.sg-density-confortavel .sige-search-box,.sg-density-confortavel .sige-aluno-banner{padding:22px!important;border-radius:22px!important}.sg-density-confortavel .sg-card-title{font-size:20px!important}.sg-density-confortavel .sg-card-subtitle{font-size:14px!important}.sg-density-confortavel .kpi-card{min-height:134px!important;padding:23px!important}.sg-density-confortavel .sige-table tbody td{padding:16px 14px!important}.sg-density-confortavel .sg-filter-preset{min-height:68px!important}.sg-mobile-actionbar{display:none!important}
@media (max-width:1180px){.sg-filter-presets{grid-template-columns:repeat(3,minmax(0,1fr))!important}.sg-filter-head-actions{justify-content:flex-start!important}}
@media (max-width:760px){.sg-filter-head-actions{display:grid!important;grid-template-columns:1fr!important;width:100%!important;margin-top:12px!important}.sg-density-toggle{width:100%!important;justify-content:space-between!important;border-radius:16px!important}.sg-density-toggle a{flex:1 1 0!important}.sg-filter-presets{grid-template-columns:1fr 1fr!important;gap:var(--space-2)!important;overflow:visible!important}.sg-filter-preset{min-height:56px!important}.sg-filter-preset:last-child{grid-column:1 / -1!important}.sg-extracts-wrap{padding-bottom:calc(92px + var(--sgx-safe-bottom))!important}.sg-mobile-actionbar{position:fixed!important;left:10px!important;right:10px!important;bottom:calc(10px + var(--sgx-safe-bottom))!important;z-index:10070!important;display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:6px!important;padding:var(--space-2)!important;border-radius:var(--radius-xl)!important;background:rgba(255,255,255,.94)!important;-webkit-backdrop-filter:blur(16px)!important;backdrop-filter:blur(16px)!important;border:1px solid rgba(31,32,55,.08)!important;box-shadow:0 4px 16px rgba(15,23,42,.08)}.sg-mobile-action{min-width:0!important;min-height:52px!important;border:0!important;border-radius:var(--radius-lg)!important;background:var(--color-slate-50)!important;color:var(--color-slate-700)!important;text-decoration:none!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:3px!important;font-size:15px!important;font-weight:700!important;padding:5px 2px!important;line-height:1!important;cursor:pointer!important}.sg-mobile-action strong{display:block!important;font-size:10px!important;font-weight:700!important;letter-spacing:.01em!important;line-height:1.1!important}.sg-mobile-action-primary{background:linear-gradient(135deg,var(--sgx-primary),var(--sgx-primary-dark))!important;color:var(--color-white)!important;box-shadow:0 2px 8px rgba(15,23,42,.06)}.sg-mobile-action:disabled,.sg-mobile-action[aria-disabled="true"]{opacity:.44!important;cursor:not-allowed!important}.sg-critical-checklist{gap:6px!important}.sg-density-confortavel .sg-extracts-filter-card,.sg-density-confortavel .sg-extracts-balance-card,.sg-density-confortavel .sg-extracts-cash-card,.sg-density-confortavel .sige-search-box,.sg-density-confortavel .sige-aluno-banner{padding:18px!important;border-radius:22px!important}.sg-density-compacta .sige-table tbody td,.sg-density-confortavel .sige-table tbody td{padding:11px 12px!important}}
@media (max-width:390px){.sg-mobile-actionbar{left:7px!important;right:7px!important;gap:var(--space-1)!important;padding:6px!important}.sg-mobile-action{min-height:48px!important;border-radius:12px!important}.sg-mobile-action strong{font-size:9.5px!important}.sg-filter-presets{grid-template-columns:1fr!important}.sg-filter-preset:last-child{grid-column:auto!important}}



/* ────────────────────────────────────────────────────────────────────────────
   v12.11.9.88 - Cash Reconciliation PRO
   Assistente de fecho, reconciliação, histórico e termo imprimível.
   ──────────────────────────────────────────────────────────────────────────── */
.sg-cash-recon-form{display:flex!important;flex-direction:column!important;gap:var(--space-3)!important;margin:0!important}.sg-recon-steps{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important}.sg-recon-step{display:flex!important;gap:9px!important;align-items:flex-start!important;padding:10px!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important}.sg-recon-step>span{width:24px!important;height:24px!important;border-radius:var(--radius-pill)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;background:var(--sgx-primary)!important;color:var(--color-white)!important;font-size:12px!important;font-weight:700!important;flex:0 0 auto!important}.sg-recon-step strong{display:block!important;color:var(--color-slate-900)!important;font-size:12px!important;font-weight:700!important;line-height:1.15!important}.sg-recon-step small{display:block!important;margin-top:3px!important;color:var(--color-ink-400)!important;font-size:10.5px!important;font-weight:600!important;line-height:1.25!important}.sg-recon-methods{overflow:hidden!important;border:1px solid var(--color-ink-100)!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important}.sg-recon-method-head,.sg-recon-method-row{display:grid!important;grid-template-columns:minmax(92px,1fr) 86px 96px 76px!important;gap:var(--space-2)!important;align-items:center!important}.sg-recon-method-head{padding:10px 12px!important;background:var(--color-slate-50)!important;color:var(--color-slate-600)!important;font-size:10px!important;font-weight:700!important;letter-spacing:.07em!important;text-transform:uppercase!important}.sg-recon-method-row{padding:10px 12px!important;border-top:1px solid var(--color-slate-100)!important}.sg-recon-method-row label{margin:0!important;color:var(--color-slate-900)!important;font-size:12px!important;font-weight:700!important;line-height:1.25!important}.sg-recon-method-row strong{color:var(--color-slate-700)!important;font-size:12px!important;font-weight:700!important;text-align:right!important}.sg-recon-method-row input{min-height:36px!important;text-align:right!important;padding:7px 9px!important;font-size:13px!important}.sg-recon-diff{font-style:normal!important;text-align:right!important;font-size:12px!important;font-weight:700!important;color:var(--color-success-800)!important}.sg-recon-diff.has-diff,.sg-recon-totals strong.has-diff,.sg-close-recon-grid .has-diff strong,.sg-close-recon-methods-row em.has-diff,.sg-close-history-item em.has-diff{color:var(--color-danger-700)!important}.sg-recon-diff.is-zero,.sg-recon-totals strong.is-zero,.sg-close-recon-grid .is-ok strong,.sg-close-recon-methods-row em.is-ok,.sg-close-history-item em.is-ok{color:var(--color-success-800)!important}.sg-recon-totals,.sg-recon-adjustments{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:8px!important}.sg-recon-totals div,.sg-recon-adjustments div{padding:11px 12px!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important}.sg-recon-totals span,.sg-recon-adjustments span{display:block!important;color:var(--color-slate-500)!important;font-size:10px!important;font-weight:700!important;letter-spacing:.07em!important;text-transform:uppercase!important}.sg-recon-totals strong,.sg-recon-adjustments strong{display:block!important;margin-top:5px!important;color:var(--color-ink-500)!important;font-size:var(--fs-base)!important;font-weight:700!important;letter-spacing:-.02em!important}.sg-recon-note-field textarea{width:100%!important;min-height:76px!important}.sg-recon-note-field.is-required textarea{border-color:var(--color-danger-300)!important;background:var(--color-white)!important}.sg-recon-note-field.is-required [data-sg-recon-note-hint]{color:var(--color-danger-700)!important}.sg-recon-confirm{display:flex!important;align-items:flex-start!important;gap:9px!important;padding:11px 12px!important;border-radius:var(--radius-lg)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-info-100)!important;color:var(--color-slate-800)!important;font-size:12px!important;font-weight:700!important;line-height:1.35!important;cursor:pointer!important}.sg-recon-confirm input{margin-top:2px!important}.sg-recon-submit{width:100%!important;min-height:44px!important}.sg-close-print-btn{margin-top:8px!important;border:1px solid rgba(255,255,255,.32)!important;background:rgba(255,255,255,.13)!important;color:var(--color-white)!important;border-radius:var(--radius-pill)!important;min-height:30px!important;padding:0 var(--space-3)!important;font-size:var(--fs-xs)!important;font-weight:700!important;cursor:pointer!important}.sg-close-recon-grid{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:10px!important;padding:0 22px 16px!important}.sg-close-recon-grid div{border-radius:var(--radius-lg)!important;padding:var(--space-3)!important;background:rgba(255,255,255,.10)!important;border:1px solid rgba(255,255,255,.12)!important}.sg-close-recon-grid span{display:block!important;font-size:10px!important;letter-spacing:.08em!important;text-transform:uppercase!important;opacity:.72!important;font-weight:700!important}.sg-close-recon-grid strong{display:block!important;margin-top:5px!important;font-size:15px!important;font-weight:700!important;color:var(--color-white)!important}.sg-close-recon-methods{margin:0 22px 16px!important;border:1px solid rgba(255,255,255,.14)!important;border-radius:var(--radius-lg)!important;overflow:hidden!important;background:rgba(255,255,255,.08)!important}.sg-close-recon-methods-head,.sg-close-recon-methods-row{display:grid!important;grid-template-columns:minmax(110px,1fr) 90px 90px 70px!important;gap:var(--space-2)!important;align-items:center!important}.sg-close-recon-methods-head{padding:9px 11px!important;background:rgba(255,255,255,.10)!important;font-size:10px!important;font-weight:700!important;letter-spacing:.08em!important;text-transform:uppercase!important;opacity:.82!important}.sg-close-recon-methods-row{padding:9px 11px!important;border-top:1px solid rgba(255,255,255,.10)!important}.sg-close-recon-methods-row span{font-size:12px!important;font-weight:700!important}.sg-close-recon-methods-row strong,.sg-close-recon-methods-row em{font-style:normal!important;text-align:right!important;font-size:12px!important;font-weight:700!important}.sg-close-note{margin:0 22px 16px!important;padding:13px 14px!important;border-radius:var(--radius-lg)!important;background:rgba(255,255,255,.10)!important;border:1px solid rgba(255,255,255,.12)!important}.sg-close-note span{display:block!important;font-size:10px!important;letter-spacing:.08em!important;text-transform:uppercase!important;opacity:.72!important;font-weight:700!important}.sg-close-note p{margin:6px 0 0!important;font-size:12px!important;line-height:1.45!important;opacity:.92!important}.sg-close-history{background:var(--color-white)!important;border:1px solid rgba(31,32,55,.07)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-xs);padding:18px!important;margin:0 0 18px!important}.sg-close-history-head{display:flex!important;justify-content:space-between!important;gap:14px!important;align-items:flex-start!important;margin-bottom:12px!important}.sg-close-history-list{display:grid!important;gap:8px!important}.sg-close-history-item{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:var(--space-3)!important;align-items:center!important;padding:var(--space-3)!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-lg)!important;background:var(--color-white)!important}.sg-close-history-item strong{display:block!important;color:var(--color-slate-900)!important;font-size:var(--fs-sm)!important;font-weight:700!important}.sg-close-history-item small{display:block!important;margin-top:3px!important;color:var(--color-ink-400)!important;font-size:var(--fs-xs)!important;font-weight:600!important}.sg-close-history-item>div:last-child{text-align:right!important}.sg-close-history-item span{display:block!important;color:var(--color-ink-500)!important;font-size:var(--fs-sm)!important;font-weight:700!important}.sg-close-history-item em{display:block!important;margin-top:3px!important;font-style:normal!important;font-size:var(--fs-xs)!important;font-weight:700!important}.sg-print-only{display:none!important}
@media (max-width:980px){.sg-recon-steps{grid-template-columns:1fr!important}.sg-recon-method-head{display:none!important}.sg-recon-method-row{grid-template-columns:1fr 1fr!important;gap:8px 10px!important}.sg-recon-method-row label{grid-column:1/-1!important}.sg-recon-method-row strong{text-align:left!important}.sg-recon-method-row strong:before{content:"Sistema: ";color:var(--color-ink-400)!important;font-weight:700!important}.sg-recon-method-row input{width:100%!important}.sg-recon-diff{text-align:left!important}.sg-recon-diff:before{content:"Dif.: ";color:var(--color-ink-400)!important;font-weight:700!important}.sg-recon-totals,.sg-recon-adjustments,.sg-close-recon-grid{grid-template-columns:1fr!important}.sg-close-recon-methods-head{display:none!important}.sg-close-recon-methods-row{grid-template-columns:1fr 1fr!important}.sg-close-recon-methods-row span{grid-column:1/-1!important}.sg-close-history-head{display:grid!important}.sg-close-history-item{grid-template-columns:1fr!important}.sg-close-history-item>div:last-child{text-align:left!important}}
@media print{body *{visibility:hidden!important}#sg-termo-fecho,#sg-termo-fecho *{visibility:visible!important}#sg-termo-fecho{position:absolute!important;left:0!important;top:0!important;width:100%!important;background:var(--color-white)!important;color:var(--color-black)!important;box-shadow:none!important;border:0!important}.sg-close-print-btn{display:none!important}.sg-extracts-close-summary{color:var(--color-black)!important}.sg-close-summary-head,.sg-close-method,.sg-close-recon-grid div,.sg-close-recon-methods,.sg-close-note,.sg-close-total-bar{background:var(--color-white)!important;color:var(--color-black)!important;border-color:var(--color-slate-200)!important}.sg-close-recon-grid strong,.sg-close-total-bar strong,.sg-close-total-bar b{color:var(--color-black)!important}}

</style>

<!-- ═══════════════════════════════════════════

 HTML

═══════════════════════════════════════════ -->

<div class="wrap sige-wrap sg-extracts-wrap <?php echo esc_attr($sg_density_class); ?>" data-sg-density="<?php echo esc_attr($sg_density); ?>">

 <!-- PAGE HEADER -->

 <?php
 $url_lancamentos = '?page=sige-app&view=financeiro-lancamentos' . (($modo_aluno && $aluno_id) ? '&aluno_id='.(int)$aluno_id : '');
 $sg_receita_lbl = ($rel_periodo === 'diario' && $rel_ciclo === 'todos') ? 'Receita líquida do caixa' : 'Receita líquida do extracto';
 // [v12.11.9.83] Reavalia com os dados já carregados nesta fase do ecrã.
 $sg_aluno_carregado = ($modo_aluno && !empty($aluno_dados));
 $sg_hero_total = $sg_aluno_carregado ? ($hist_total ?? 0) : ($total_liquido ?? 0);
 $sg_hero_sub = $sg_aluno_carregado
     ? 'Histórico financeiro de ' . $aluno_dados->nome_completo
     : ($sg_em_modo_aluno
         ? 'Seleccione um aluno para abrir o histórico financeiro oficial'
         : $rel_periodo_label . ' · ' . $rel_ciclo_label);
 ?>

 <section class="sg-extracts-hero" aria-label="Extractos e Caixa">
   <div class="sg-extracts-hero-copy">
     <div class="sg-extracts-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Tesouraria</div>
     <h1>Extractos e Caixa</h1>
     <p>Consulte movimentos, emita recibos, acompanhe entradas por método de pagamento e faça o fecho do caixa com mais clareza.</p>
     <div class="sg-extracts-actions">
       <a href="?page=sige-app&view=financeiro-pagamentos" class="sg-extracts-btn sg-extracts-btn-primary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?> Registar pagamento</a>
       <a href="<?php echo esc_url($url_lancamentos); ?>" class="sg-extracts-btn sg-extracts-btn-light"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('pin') : ''; ?> Ver lançamentos</a>
     </div>
   </div>
   <div class="sg-extracts-hero-panel">
     <span><?php echo esc_html($sg_aluno_carregado ? 'Saldo do histórico' : ($sg_em_modo_aluno ? 'Histórico de aluno' : $sg_receita_lbl)); ?></span>
     <strong><?php
       if ($sg_em_modo_aluno && !$sg_aluno_carregado) {
         echo '-';
       } else {
         echo number_format((float)$sg_hero_total, 2) . ' ' . esc_html(sige_moeda());
       }
     ?></strong>
     <small><?php echo esc_html($sg_hero_sub); ?></small>
     <div class="sg-extracts-hero-status">
       <?php if ($sg_em_modo_aluno): ?>
         <span class="sige-status-pill pill-aluno"><span class="pill-dot"></span> <?php echo esc_html($sg_aluno_carregado ? 'Histórico de aluno' : 'A escolher aluno'); ?></span>
       <?php else: ?>
         <span class="sige-status-pill <?php echo esc_attr($pill_class); ?>"><span class="pill-dot"></span> <?php echo esc_html($pill_txt); ?></span>
       <?php endif; ?>
     </div>
   </div>
 </section>

 <!-- MODE TOGGLE -->

 <div class="sg-extracts-mode-card">
   <div class="sg-extracts-mode-title">
     <strong>Escolha a leitura financeira</strong>
     <span>Alterne entre caixa do período e histórico individual do aluno.</span>
   </div>
   <div class="sige-mode-options">
     <a href="?page=sige-app&view=financeiro-extratos&modo=diario&data_relatorio=<?php echo esc_attr($data_filtro); ?>&mes_relatorio=<?php echo esc_attr($mes_relatorio); ?>&ano_relatorio=<?php echo esc_attr($ano_relatorio); ?>&periodo_relatorio=<?php echo esc_attr($rel_periodo); ?>&ciclo_ensino=<?php echo esc_attr($rel_ciclo); ?>&sg_density=<?php echo esc_attr($sg_density); ?>"
        class="sige-mode-btn <?php echo !$sg_em_modo_aluno ? 'active' : ''; ?>" <?php echo !$sg_em_modo_aluno ? 'aria-current="true"' : ''; ?>>
       <span class="mode-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?></span>
       <span><strong>Caixa do período</strong><small>Entradas, estornos e fecho</small></span>
     </a>
     <a href="?page=sige-app&view=financeiro-extratos&modo=aluno<?php echo $aluno_id ? '&aluno_id='.$aluno_id : ''; ?>&sg_density=<?php echo esc_attr($sg_density); ?>"
        class="sige-mode-btn <?php echo $sg_em_modo_aluno ? 'active' : ''; ?>" <?php echo $sg_em_modo_aluno ? 'aria-current="true"' : ''; ?>>
       <span class="mode-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?></span>
       <span><strong>Histórico de aluno</strong><small>Todos os pagamentos do aluno</small></span>
     </a>
   </div>
 </div>

 <!-- ══════════════════════════════════════

 MODO: HISTÓRICO DE ALUNO

 ══════════════════════════════════════ -->

 <?php if ($modo_aluno || $modo_get === 'aluno'): ?>

 <!-- Pesquisa de aluno -->

 <div class="sige-search-box sg-student-search-card">

   <div class="sg-student-search-head">
     <div>
       <span class="sg-card-eyebrow">Aluno</span>
       <h2 class="sg-card-title">Pesquisar histórico financeiro</h2>
       <p class="sg-card-subtitle">Digite pelo menos 2 letras do nome ou o número de processo. Depois escolha o aluno para abrir o histórico completo.</p>
     </div>
   </div>

   <form method="get" class="sige-search-row sg-student-search-form" role="search" aria-describedby="sg-aluno-search-help" data-sg-lock-submit>
     <input type="hidden" name="page" value="sige-app">
     <input type="hidden" name="view" value="financeiro-extratos">
     <input type="hidden" name="modo" value="aluno">
     <input type="hidden" name="sg_density" value="<?php echo esc_attr($sg_density); ?>">
     <label class="sg-sr-only" for="sg_aluno_search">Nome ou número de processo do aluno</label>
     <input id="sg_aluno_search" type="search" name="q" class="sige-input"
       placeholder="Nome do aluno ou nº de processo"
       value="<?php echo esc_attr(sige_fin_get_param('q')); ?>"
       autocomplete="off" inputmode="search">
     <button type="submit" class="sige-btn sige-btn-primary">Pesquisar aluno</button>
     <?php if ($aluno_id || sige_fin_get_param('q') !== ''): ?>
       <a href="?page=sige-app&view=financeiro-extratos&modo=aluno&sg_density=<?php echo esc_attr($sg_density); ?>" class="sige-btn sige-btn-ghost">Limpar</a>
     <?php endif; ?>
   </form>
   <p id="sg-aluno-search-help" class="sg-help-text">Sugestão: use o número de processo quando houver alunos com nomes semelhantes.</p>

   <?php if (!empty($alunos_search)): ?>
     <div class="sige-results-list sg-results-grid" aria-label="Resultados da pesquisa de alunos">
       <?php foreach ($alunos_search as $as):
         $sg_nome_partes = preg_split('/\s+/', trim((string)$as->nome_completo));
         $sg_iniciais = '';
         foreach (array_slice($sg_nome_partes ?: [], 0, 2) as $__parte) { $sg_iniciais .= function_exists('mb_substr') ? mb_substr($__parte, 0, 1, 'UTF-8') : substr($__parte, 0, 1); }
         $sg_iniciais = $sg_iniciais !== '' ? (function_exists('mb_strtoupper') ? mb_strtoupper($sg_iniciais, 'UTF-8') : strtoupper($sg_iniciais)) : 'AL';
       ?>
         <a href="<?php echo esc_url(add_query_arg(['page'=>'sige-app','view'=>'financeiro-extratos','modo'=>'aluno','aluno_id'=>(int)$as->id,'centro_id'=>$centro_id_filtro ?: null,'sg_density'=>$sg_density], admin_url('admin.php'))); ?>"
           class="sige-result-item">
           <span class="sg-result-avatar" aria-hidden="true"><?php echo esc_html($sg_iniciais); ?></span>
           <span>
             <span class="sg-result-title"><?php echo esc_html($as->nome_completo); ?></span>
             <span class="sg-result-meta">Processo <?php echo esc_html($as->numero_processo); ?></span>
           </span>
           <span class="sg-result-open">Abrir</span>
         </a>
       <?php endforeach; ?>
     </div>
   <?php elseif (strlen(trim((string)sige_fin_get_param('q'))) >= 2): ?>
     <div class="sg-no-results">Nenhum aluno encontrado para esta pesquisa. Confirme o nome, o número de processo ou remova filtros de centro.</div>
   <?php endif; ?>

 </div>

 <?php if ($modo_aluno && !empty($aluno_dados)): ?>

 <!-- Aluno banner + mini stats -->

 <div class="sige-aluno-banner sg-student-profile-card">
   <div class="sg-student-main">
     <?php
       $__sg_aluno_partes = preg_split('/\s+/', trim((string)$aluno_dados->nome_completo));
       $__sg_aluno_iniciais = '';
       foreach (array_slice($__sg_aluno_partes ?: [], 0, 2) as $__parte) { $__sg_aluno_iniciais .= function_exists('mb_substr') ? mb_substr($__parte, 0, 1, 'UTF-8') : substr($__parte, 0, 1); }
       $__sg_aluno_iniciais = $__sg_aluno_iniciais !== '' ? (function_exists('mb_strtoupper') ? mb_strtoupper($__sg_aluno_iniciais, 'UTF-8') : strtoupper($__sg_aluno_iniciais)) : 'AL';
     ?>
     <div class="sg-student-avatar" aria-hidden="true"><?php echo esc_html($__sg_aluno_iniciais); ?></div>
     <div class="sige-aluno-banner-info">
       <h3><?php echo esc_html($aluno_dados->nome_completo); ?></h3>
       <p>Nº Processo: <strong style="font-family:var(--mono);"><?php echo esc_html($aluno_dados->numero_processo); ?></strong>
       · <?php echo count($extrato); ?> pagamento(s) registado(s)
       · seleccione linhas para emitir um <strong>recibo unificado</strong>.</p>
     </div>
   </div>

   <div class="sige-aluno-stats">
     <div class="sige-aluno-stat">
       <div class="sige-aluno-stat-val"><?php echo number_format($hist_entradas, 2); ?></div>
       <div class="sige-aluno-stat-lbl">Entradas <?php echo esc_html(sige_moeda()); ?></div>
     </div>
     <div class="sige-aluno-stat is-refund">
       <div class="sige-aluno-stat-val"><?php echo number_format($hist_estornos, 2); ?></div>
       <div class="sige-aluno-stat-lbl">Estornos <?php echo esc_html(sige_moeda()); ?></div>
     </div>
     <div class="sige-aluno-stat">
       <div class="sige-aluno-stat-val"><?php echo number_format($hist_total, 2); ?></div>
       <div class="sige-aluno-stat-lbl">Saldo <?php echo esc_html(sige_moeda()); ?></div>
     </div>
   </div>
 </div>

 <?php
   $sg_hist_pro_url = function_exists('sige_fin_hist_aluno_build_url')
       ? sige_fin_hist_aluno_build_url((int)$aluno_id, 'html')
       : wp_nonce_url(admin_url('admin.php?sige_print=historico_financeiro_aluno&id=' . (int)$aluno_id), 'sige_hist_fin_aluno_' . (int)$aluno_id);
   $sg_hist_pro_xlsx_url = function_exists('sige_fin_hist_aluno_build_url')
       ? sige_fin_hist_aluno_build_url((int)$aluno_id, 'xlsx')
       : add_query_arg('formato', 'xlsx', $sg_hist_pro_url);
 ?>
 <div class="sg-hist-pro-download">
   <div>
     <div class="sg-hist-pro-head">
       <span class="sg-hist-pro-ic" aria-hidden="true">🧾</span>
       <strong>Histórico financeiro oficial do aluno</strong>
     </div>
     <span>Folha administrativa do aluno: cabeçalho institucional, situação financeira, pagamentos, obrigações, dívida, crédito e Excel para análise.</span>
   </div>
   <div class="sg-hist-pro-actions">
     <a class="sg-hist-pro-btn primary" data-sg-hist href="<?php echo esc_url($sg_hist_pro_url); ?>" target="_blank" rel="noopener"><span class="sg-hist-ic" aria-hidden="true">📄</span><span class="sg-hist-label">Abrir documento / PDF</span></a>
     <a class="sg-hist-pro-btn" data-sg-hist href="<?php echo esc_url($sg_hist_pro_xlsx_url); ?>" target="_blank" rel="noopener"><span class="sg-hist-ic" aria-hidden="true">⬇</span><span class="sg-hist-label">Baixar Excel</span></a>
   </div>
   <div class="sg-hist-pro-foot"><span aria-hidden="true">↗</span> Abre numa nova aba. Use "Guardar PDF / Imprimir" dentro do documento.</div>
 </div>
 <script <?php echo sige_csp_script_attr(); ?>>
 (function(){
   var botoes = document.querySelectorAll('.sg-hist-pro-btn[data-sg-hist]');
   if (!botoes.length) return;
   botoes.forEach(function(b){
     b.addEventListener('click', function(){
       var label = b.querySelector('.sg-hist-label');
       var ic = b.querySelector('.sg-hist-ic');
       var textoOriginal = label ? label.textContent : '';
       var icOriginal = ic ? ic.textContent : '';
       b.classList.add('is-loading');
       if (label) { label.textContent = 'A abrir…'; }
       if (ic) { ic.textContent = '⏳'; }
       window.setTimeout(function(){
         b.classList.remove('is-loading');
         if (label) { label.textContent = textoOriginal; }
         if (ic) { ic.textContent = icOriginal; }
       }, 2600);
     });
   });
 })();
 </script>

 <?php
   // [v13.4.0 BLOCO 3] Pill informativo quando o filtro de centro está activo no
   // histórico do aluno. Dá ao utilizador uma saída clara para ver o histórico
   // completo sem forçar voltar ao dashboard.
   if ($centro_id_filtro > 0 && function_exists('sige_fin_chip_centro')):
     $__limpar_url = add_query_arg([
       'page' => 'sige-app', 'view' => 'financeiro-extratos',
       'modo' => 'aluno', 'aluno_id' => $aluno_id,
       'centro_id' => null,
     ]);
   ?>
   <div class="sg-extracts-center-note">
     <span>Extrato filtrado por centro:</span>
     <?php echo sige_fin_chip_centro($centro_id_filtro); ?>
     <a href="<?php echo esc_url($__limpar_url); ?>">Ver histórico completo</a>
   </div>
   <?php endif; ?>

 <?php endif; ?>

 <?php else: // MODO DIÁRIO ?>

 <!-- ══════════════════════════════════════

 MODO: RELATÓRIO DO DIA

 ══════════════════════════════════════ -->

 <!-- TOOLBAR DIÁRIO REDESENHADA -->

 <?php
 $sg_mov_count = count($extrato);
 $sg_mov_label = $sg_mov_count === 1 ? '1 movimento' : $sg_mov_count . ' movimentos';
 $sg_cash_is_reopen_user = ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director'));
 $sg_can_fechar_caixa = ($rel_periodo === 'diario' && !$dia_fechado && $pode_direccao);
 $sg_can_reabrir_caixa = ($rel_periodo === 'diario' && $dia_fechado && $sg_cash_is_reopen_user);
 $sg_has_cash_actions = ($sg_can_fechar_caixa || $sg_can_reabrir_caixa || ($rel_periodo === 'diario' && $dia_fechado));
 $sg_cash_status_class = $dia_fechado ? 'closed' : (($estado === 'reaberto') ? 'reopened' : 'open');
 $sg_cash_status_txt = $dia_fechado ? 'Caixa fechado' : (($estado === 'reaberto') ? 'Caixa reaberto' : 'Caixa aberto');
 ?>

 <section id="sg-filter-card" class="sg-extracts-filter-card" aria-labelledby="sg-extracts-filter-title">
   <div class="sg-extracts-filter-head">
     <div>
       <span class="sg-card-eyebrow">Filtros</span>
       <h2 class="sg-card-title" id="sg-extracts-filter-title">Ajuste a leitura do extracto</h2>
       <p class="sg-card-subtitle">Escolha período, ciclo e centro. Os cartões, a tabela e o Excel passam a reflectir exactamente estes filtros.</p>
     </div>
     <div class="sg-filter-head-actions">
       <div class="sg-density-toggle" role="group" aria-label="Densidade de visualização">
         <?php foreach ($sg_density_labels as $sg_density_key => $sg_density_label): ?>
           <a href="<?php echo esc_url(add_query_arg('sg_density', $sg_density_key)); ?>"
              class="<?php echo $sg_density === $sg_density_key ? 'active' : ''; ?>"
              <?php echo $sg_density === $sg_density_key ? 'aria-current="true"' : ''; ?>><?php echo esc_html($sg_density_label); ?></a>
         <?php endforeach; ?>
       </div>
       <button type="button" data-sige-act="sigeExportarExcel" data-sige-noargs class="sige-btn sige-btn-outline sg-export-btn-main" data-sg-export>⬇ Exportar Excel</button>
     </div>
   </div>

   <nav class="sg-filter-presets" aria-label="Atalhos rápidos do extracto">
     <?php foreach ($sg_filter_presets as $sg_preset): ?>
       <a href="<?php echo esc_url(add_query_arg($sg_preset['args'], admin_url('admin.php'))); ?>"
          class="sg-filter-preset <?php echo !empty($sg_preset['active']) ? 'active' : ''; ?>"
          <?php echo !empty($sg_preset['active']) ? 'aria-current="true"' : ''; ?>>
         <strong><?php echo esc_html($sg_preset['label']); ?></strong>
         <span><?php echo esc_html($sg_preset['hint']); ?></span>
       </a>
     <?php endforeach; ?>
   </nav>

   <form method="get" class="sg-extracts-filter-grid" data-sg-lock-submit>
     <input type="hidden" name="page" value="sige-app">
     <input type="hidden" name="view" value="financeiro-extratos">
     <input type="hidden" name="modo" value="diario">
     <input type="hidden" name="sg_density" value="<?php echo esc_attr($sg_density); ?>">

     <div class="sige-date-group">
       <label for="sige_periodo_relatorio">Período</label>
       <select name="periodo_relatorio" id="sige_periodo_relatorio" aria-label="Tipo de período do extracto">
         <option value="diario" <?php selected($rel_periodo, 'diario'); ?>>Diário</option>
         <option value="mensal" <?php selected($rel_periodo, 'mensal'); ?>>Mensal</option>
         <option value="anual" <?php selected($rel_periodo, 'anual'); ?>>Anual</option>
       </select>
     </div>

     <div class="sige-date-group sige-period-field" data-period="diario">
       <label for="sg_data_relatorio">Dia</label>
       <input id="sg_data_relatorio" type="date" name="data_relatorio" value="<?php echo esc_attr($data_filtro); ?>">
     </div>

     <div class="sige-date-group sige-period-field" data-period="mensal">
       <label for="sg_mes_relatorio">Mês</label>
       <input id="sg_mes_relatorio" type="month" name="mes_relatorio" value="<?php echo esc_attr($mes_relatorio); ?>">
     </div>

     <div class="sige-date-group sige-period-field" data-period="anual">
       <label for="sg_ano_relatorio">Ano</label>
       <input id="sg_ano_relatorio" type="number" name="ano_relatorio" min="2000" max="2100" value="<?php echo esc_attr($ano_relatorio); ?>">
     </div>

     <div class="sige-date-group">
       <label for="sg_ciclo_ensino">Ciclo</label>
       <select id="sg_ciclo_ensino" name="ciclo_ensino" aria-label="Ciclo de ensino">
         <option value="todos" <?php selected($rel_ciclo, 'todos'); ?>>Todos</option>
         <option value="creche" <?php selected($rel_ciclo, 'creche'); ?>>Creche</option>
         <option value="primario" <?php selected($rel_ciclo, 'primario'); ?>>Ensino Primário</option>
         <option value="secundario" <?php selected($rel_ciclo, 'secundario'); ?>>Ensino Secundário</option>
       </select>
     </div>

     <?php
       if (function_exists('sige_fin_render_filtro_centro')) {
         $__sel = sige_fin_render_filtro_centro([
           'selected'      => $centro_id_filtro,
           'include_label' => false,
         ]);
         if ($__sel !== '') {
           echo '<div class="sige-date-group"><label>Centro</label>' . $__sel . '</div>';
         }
       }
     ?>

     <button type="submit" class="sige-btn sige-btn-primary">Aplicar filtros</button>
   </form>

   <div class="sg-extracts-filter-summary" aria-label="Filtros activos">
     <span class="sg-extracts-filter-chip"><span>Período</span><strong><?php echo esc_html($rel_periodo_label); ?></strong></span>
     <span class="sg-extracts-filter-chip"><span>Intervalo</span><strong><?php echo esc_html($sg_period_range); ?></strong></span>
     <span class="sg-extracts-filter-chip"><span>Ciclo</span><strong><?php echo esc_html($rel_ciclo_label); ?></strong></span>
     <span class="sg-extracts-filter-chip"><span>Resultado</span><strong><?php echo esc_html($sg_mov_label); ?></strong></span>
     <?php if ($rel_periodo === 'diario'): ?>
       <span class="sg-extracts-filter-chip status-<?php echo esc_attr($sg_cash_status_class); ?>"><span>Estado</span><strong><?php echo esc_html($sg_cash_status_txt); ?></strong></span>
     <?php endif; ?>
   </div>
 </section>

 <?php if ($rel_periodo === 'diario'): ?>
 <section id="sg-operational-summary" class="sg-extracts-ops-grid <?php echo $sg_has_cash_actions ? 'has-cash-actions' : ''; ?>" aria-label="Resumo operacional do caixa">
   <div class="sg-extracts-balance-card">
     <div class="sg-extracts-balance-head">
       <div>
         <span class="sg-card-eyebrow">Resumo do caixa</span>
         <h2 class="sg-card-title">Receitas, despesas e saldo</h2>
         <p class="sg-card-subtitle">Visão rápida para conferência antes de fechar ou reabrir o caixa.</p>
       </div>
     </div>
     <div class="sg-extracts-balance-grid">
       <div class="sg-balance-mini revenue">
         <span><?php echo ($rel_periodo === 'diario' && $rel_ciclo === 'todos') ? 'Receitas' : 'Receitas do extracto'; ?></span>
         <strong><?php echo number_format($total_liquido, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong>
         <small>Entradas líquidas após estornos.</small>
       </div>
       <div class="sg-balance-mini expense">
         <span>Despesas</span>
         <strong><?php echo number_format($total_despesas_dia, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong>
         <small>Saídas registadas no dia.</small>
       </div>
       <div class="sg-balance-mini balance <?php echo $saldo_dia < 0 ? 'negative' : ''; ?>">
         <span><?php echo ($rel_periodo === 'diario' && $rel_ciclo === 'todos') ? 'Saldo do dia' : 'Saldo do extracto'; ?></span>
         <strong><?php echo number_format($saldo_dia, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong>
         <small><?php echo $saldo_dia >= 0 ? 'Resultado positivo para conferência.' : 'Atenção: despesas acima das receitas.'; ?></small>
       </div>
     </div>

     <?php if (!$modo_aluno && !empty($despesas_dia)): ?>
       <details class="sg-expense-details">
         <summary>Ver despesas do dia</summary>
         <div class="sg-expense-list">
           <?php foreach ($despesas_dia as $dd): ?>
             <div class="sg-expense-row">
               <div>
                 <strong><?php echo esc_html($dd->descricao ?? 'Despesa'); ?></strong>
                 <?php $dd_categoria = trim((string)($dd->categoria ?? '')); ?>
                 <small><?php echo esc_html($dd_categoria !== '' ? ucfirst(str_replace('_',' ',$dd_categoria)) : 'Despesa'); ?></small>
               </div>
               <em>-<?php echo number_format((float)$dd->valor, 2); ?> <?php echo esc_html(sige_moeda()); ?></em>
             </div>
           <?php endforeach; ?>
           <div class="sg-expense-row total">
             <strong>Total de despesas</strong>
             <em>-<?php echo number_format($total_despesas_dia, 2); ?> <?php echo esc_html(sige_moeda()); ?></em>
           </div>
         </div>
       </details>
     <?php endif; ?>
   </div>

   <?php if ($sg_has_cash_actions): ?>
   <aside id="sg-cash-card" class="sg-extracts-cash-card <?php echo $dia_fechado ? 'is-closed' : ''; ?>" aria-labelledby="sg-cash-actions-title">
     <div class="sg-extracts-cash-head">
       <div>
         <span class="sg-card-eyebrow">Acção crítica</span>
         <h2 class="sg-card-title" id="sg-cash-actions-title"><?php echo $dia_fechado ? 'Caixa encerrado' : 'Fecho de caixa'; ?></h2>
         <p class="sg-card-subtitle"><?php echo $dia_fechado ? 'O caixa desta data está bloqueado para pagamentos e estornos.' : 'Confirme valores antes de encerrar o caixa do dia.'; ?></p>
       </div>
       <span class="sg-cash-state-pill <?php echo esc_attr($sg_cash_status_class); ?>"><?php echo esc_html($sg_cash_status_txt); ?></span>
     </div>

     <ul class="sg-critical-checklist" aria-label="Checklist antes de alterar o estado do caixa">
       <li>Conferir valores por método de pagamento.</li>
       <li>Validar despesas e estornos do dia.</li>
       <li>Confirmar que a data seleccionada está correcta.</li>
     </ul>

     <?php if ($sg_can_fechar_caixa): ?>
       <form method="post" class="sg-cash-recon-form" data-sg-lock-submit data-sg-recon-form>
         <?php wp_nonce_field('sige_fechar_caixa'); ?>
         <input type="hidden" name="sige_fechar_caixa" value="1">
         <input type="hidden" name="data_caixa" value="<?php echo esc_attr($data_filtro); ?>">

         <div class="sg-recon-steps" aria-label="Assistente de reconciliação de caixa">
           <div class="sg-recon-step">
             <span>1</span>
             <div><strong>Conferir métodos</strong><small>Compare sistema vs contado.</small></div>
           </div>
           <div class="sg-recon-step">
             <span>2</span>
             <div><strong>Validar ajustes</strong><small>Estornos e despesas.</small></div>
           </div>
           <div class="sg-recon-step">
             <span>3</span>
             <div><strong>Fechar com evidência</strong><small>Guardar snapshot auditável.</small></div>
           </div>
         </div>

         <div class="sg-recon-methods" role="group" aria-label="Valores contados por método de pagamento">
           <div class="sg-recon-method-head">
             <span>Método</span><span>Sistema</span><span>Contado</span><span>Diferença</span>
           </div>
           <?php foreach ($sg_recon_methods as $sg_method_key => $sg_method): ?>
             <div class="sg-recon-method-row" data-sg-recon-row>
               <label for="sg_contado_<?php echo esc_attr($sg_method_key); ?>"><?php echo esc_html($sg_method['label']); ?></label>
               <strong data-sg-system="<?php echo esc_attr(number_format((float)$sg_method['expected'], 2, '.', '')); ?>">
                 <?php echo number_format((float)$sg_method['expected'], 2, ',', '.'); ?>
               </strong>
               <input id="sg_contado_<?php echo esc_attr($sg_method_key); ?>"
                      type="number" step="0.01" min="0" inputmode="decimal"
                      name="sg_contado[<?php echo esc_attr($sg_method_key); ?>]"
                      value="<?php echo esc_attr(number_format((float)$sg_method['expected'], 2, '.', '')); ?>"
                      class="sige-input sg-recon-counted"
                      aria-label="Valor contado em <?php echo esc_attr($sg_method['label']); ?>">
               <em class="sg-recon-diff" data-sg-row-diff>0,00</em>
             </div>
           <?php endforeach; ?>
         </div>

         <div class="sg-recon-totals" aria-live="polite">
           <div><span>Sistema bruto</span><strong data-sg-expected-gross="<?php echo esc_attr(number_format($sg_recon_expected_gross, 2, '.', '')); ?>"><?php echo number_format($sg_recon_expected_gross, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
           <div><span>Total contado</span><strong data-sg-counted-total><?php echo number_format($sg_recon_expected_gross, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
           <div><span>Diferença</span><strong data-sg-recon-difference class="is-zero">0,00 <?php echo esc_html(sige_moeda()); ?></strong></div>
         </div>

         <div class="sg-recon-adjustments">
           <div><span>Estornos</span><strong>-<?php echo number_format($sg_recon_refunds_total, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
           <div><span>Despesas</span><strong>-<?php echo number_format($sg_recon_expenses_total, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
           <div><span>Saldo esperado</span><strong><?php echo number_format($sg_recon_expected_balance, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
         </div>

         <div class="sg-cash-field sg-recon-note-field">
           <label for="sg_motivo_divergencia">Observação de divergência</label>
           <textarea id="sg_motivo_divergencia" name="motivo_divergencia" rows="3" class="sige-textarea" placeholder="Obrigatório se o total contado não bater com o sistema. Ex: diferença de troco, comprovativo pendente, erro de contagem..."></textarea>
           <p class="sg-cash-note" data-sg-recon-note-hint>Se a diferença for 0,00, esta observação é opcional.</p>
         </div>

         <label class="sg-recon-confirm">
           <input type="checkbox" name="sg_recon_confirmado" value="1" required>
           <span>Confirmo que conferi os métodos, estornos, despesas e a data do caixa antes de fechar.</span>
         </label>

         <button type="submit" class="sige-btn sige-btn-primary sg-recon-submit"
           data-sige-act="sigeConfirmacaoCaixaSubmit" data-sige-prevent data-sige-confirm-title="Fechar caixa com reconciliação" data-sige-confirm-text="O sistema vai guardar a fotografia financeira e a reconciliação sistema vs contado. Se houver divergência, o motivo ficará auditável.">
           Fechar caixa com reconciliação
         </button>
       </form>
       <p class="sg-cash-note">O fecho guarda a fotografia canónica por método, o total contado, a divergência e o termo operacional para auditoria.</p>
     <?php endif; ?>

     <?php if ($sg_can_reabrir_caixa): ?>
       <form method="post" class="sg-cash-inline-form" data-sg-lock-submit>
         <?php wp_nonce_field('sige_reabrir_caixa'); ?>
         <input type="hidden" name="sige_reabrir_caixa" value="1">
         <input type="hidden" name="data_caixa" value="<?php echo esc_attr($data_filtro); ?>">
         <div class="sg-cash-field">
           <label for="sg_motivo_reabertura">Motivo da reabertura *</label>
           <input id="sg_motivo_reabertura" type="text" name="motivo_reabertura" required placeholder="Ex: Corrigir pagamento duplicado" class="sige-input">
         </div>
         <button type="submit" class="sige-btn sige-btn-warning"
           data-sige-act="sigeConfirmacaoCaixaSubmit" data-sige-prevent data-sige-confirm-title="Reabrir caixa" data-sige-confirm-text="A reabertura permite novos pagamentos ou estornos nesta data. Confirme apenas se o motivo estiver correctamente preenchido.">
           Reabrir caixa
         </button>
       </form>
     <?php elseif ($rel_periodo === 'diario' && $dia_fechado): ?>
       <p class="sg-cash-note">Para reabrir, é necessária permissão de Direcção/Administração e um motivo auditável.</p>
     <?php endif; ?>
   </aside>
   <?php endif; ?>
 </section>
 <?php endif; ?>

 <?php if ($rel_periodo === 'diario' && $dia_fechado && $fecho_row): ?>
   <?php
     $metodos_json = json_decode($fecho_row->total_por_metodo ?? '{}', true) ?: [];
     $metodo_labels = $sg_recon_method_labels;
     $fechado_user = $fecho_row->fechado_por ? get_userdata((int)$fecho_row->fechado_por) : null;
     $fechado_nome = $fechado_user ? $fechado_user->display_name : 'ID #' . ($fecho_row->fechado_por ?? '?');
     $sg_close_recon = !empty($sg_close_recon) ? $sg_close_recon : sige_extracto_recon_parse($fecho_row->observacoes ?? '');
     $sg_close_counted = (float)($sg_close_recon['counted']['gross'] ?? $fecho_row->total_bruto);
     $sg_close_diff = (float)($sg_close_recon['difference'] ?? 0);
     $sg_close_balance = (float)($sg_close_recon['counted']['estimated_balance_after_adjustments'] ?? ((float)$fecho_row->total_liquido - $total_despesas_dia));
     $sg_close_note = sige_extracto_recon_clean_note($fecho_row->observacoes ?? '');
   ?>
   <section id="sg-termo-fecho" class="sg-extracts-close-summary" aria-label="Resumo do fecho de caixa">
     <div class="sg-close-summary-head">
       <div>
         <span>Caixa encerrado · Reconciliação concluída</span>
         <strong><?php echo esc_html(wp_date('d \d\e F \d\e Y', strtotime($fecho_row->data_caixa))); ?></strong>
       </div>
       <div class="sg-close-summary-meta">
         Fechado por <strong><?php echo esc_html($fechado_nome); ?></strong><br>
         em <?php echo esc_html(wp_date('d/m/Y H:i', strtotime($fecho_row->fechado_em ?? $fecho_row->data_fecho))); ?><br>
         <button type="button" class="sg-close-print-btn" data-sige-act="sigeImprimirTermoFecho" data-sige-noargs>Imprimir termo</button>
       </div>
     </div>
     <div class="sg-close-method-grid">
       <?php foreach ($metodos_json as $met => $val):
         if ((float)$val <= 0 && $met !== 'estorno') continue;
         if ($met === 'estorno' && (float)$val <= 0) continue;
         $label = $metodo_labels[$met] ?? ucfirst($met);
       ?>
         <div class="sg-close-method">
           <span><?php echo esc_html($label); ?></span>
           <strong><?php echo number_format((float)$val, 2, ',', '.'); ?></strong>
         </div>
       <?php endforeach; ?>
     </div>
     <div class="sg-close-recon-grid">
       <div><span>Sistema bruto</span><strong><?php echo number_format((float)$fecho_row->total_bruto, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
       <div><span>Total contado</span><strong><?php echo number_format($sg_close_counted, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
       <div class="<?php echo abs($sg_close_diff) >= 0.01 ? 'has-diff' : 'is-ok'; ?>"><span>Diferença</span><strong><?php echo number_format($sg_close_diff, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
       <div><span>Saldo estimado</span><strong><?php echo number_format($sg_close_balance, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong></div>
     </div>
     <?php if (!empty($sg_close_recon['methods']) && is_array($sg_close_recon['methods'])): ?>
       <div class="sg-close-recon-methods">
         <div class="sg-close-recon-methods-head"><span>Método</span><span>Sistema</span><span>Contado</span><span>Dif.</span></div>
         <?php foreach ($sg_close_recon['methods'] as $sgm): ?>
           <div class="sg-close-recon-methods-row">
             <span><?php echo esc_html($sgm['label'] ?? 'Método'); ?></span>
             <strong><?php echo number_format((float)($sgm['system'] ?? 0), 2, ',', '.'); ?></strong>
             <strong><?php echo number_format((float)($sgm['counted'] ?? 0), 2, ',', '.'); ?></strong>
             <em class="<?php echo abs((float)($sgm['difference'] ?? 0)) >= 0.01 ? 'has-diff' : 'is-ok'; ?>"><?php echo number_format((float)($sgm['difference'] ?? 0), 2, ',', '.'); ?></em>
           </div>
         <?php endforeach; ?>
       </div>
     <?php endif; ?>
     <?php if ($sg_close_note !== ''): ?>
       <div class="sg-close-note"><span>Observações auditáveis</span><p><?php echo nl2br(esc_html($sg_close_note)); ?></p></div>
     <?php endif; ?>
     <div class="sg-close-total-bar">
       <div>
         <small>Bruto</small><b><?php echo number_format((float)$fecho_row->total_bruto, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></b>
         <small>Estornos</small><b>-<?php echo number_format((float)$fecho_row->total_estornos, 2, ',', '.'); ?></b>
       </div>
       <strong><?php echo number_format((float)$fecho_row->total_liquido, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
     </div>
   </section>
 <?php endif; ?>

 <?php if (!$sg_em_modo_aluno && $rel_periodo === 'diario' && !empty($sg_close_history)): ?>
   <section class="sg-close-history" aria-label="Histórico de fechos desta data">
     <div class="sg-close-history-head">
       <div>
         <span class="sg-card-eyebrow">Histórico de fechos</span>
         <h2 class="sg-card-title">Registos do dia seleccionado</h2>
         <p class="sg-card-subtitle">Mostra os fechos/reaberturas conhecidos para esta data, preservando compatibilidade com fechos antigos.</p>
       </div>
       <span class="sige-count-badge"><?php echo count($sg_close_history); ?> registo(s)</span>
     </div>
     <div class="sg-close-history-list">
       <?php foreach ($sg_close_history as $sg_hist):
         $sg_hist_recon = sige_extracto_recon_parse($sg_hist->observacoes ?? '');
         $sg_hist_diff = (float)($sg_hist_recon['difference'] ?? 0);
         $sg_hist_status = (string)($sg_hist->status ?? '');
       ?>
         <article class="sg-close-history-item">
           <div>
             <strong><?php echo esc_html($sg_hist->user_nome ?: ('User #' . ($sg_hist->fechado_por ?? '-'))); ?></strong>
             <small><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($sg_hist->fechado_em ?? $sg_hist->data_fecho ?? $sg_hist->data_caixa))); ?> · <?php echo esc_html(ucfirst($sg_hist_status)); ?></small>
           </div>
           <div>
             <span><?php echo number_format((float)$sg_hist->total_liquido, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></span>
             <em class="<?php echo abs($sg_hist_diff) >= 0.01 ? 'has-diff' : 'is-ok'; ?>">Dif. <?php echo number_format($sg_hist_diff, 2, ',', '.'); ?></em>
           </div>
         </article>
       <?php endforeach; ?>
     </div>
   </section>
 <?php endif; ?>

 <!-- KPI CARDS -->

 <?php
 $sige_pos_total = 0.0;
 foreach ((function_exists('sige_fin_metodos_pos_keys') ? sige_fin_metodos_pos_keys() : ['pos']) as $__pos_metodo) {
     $sige_pos_total += (float)($resumo[$__pos_metodo] ?? 0);
 }
 ?>

 <div class="sige-kpi-grid">

 <div class="kpi-card total">

 <div class="kpi-icon"></div>

 <div class="kpi-label"><?php echo ($rel_periodo === 'diario' && $rel_ciclo === 'todos') ? 'Receita Líquida' : 'Receita Líquida do Extracto'; ?></div>

 <div class="kpi-value"><?php echo number_format($total_liquido, 2); ?><span class="kpi-unit">MT</span></div>

 <div class="kpi-sub">

 Bruto: <strong><?php echo number_format($total_bruto, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong><br>

 Estornos: <strong style="color:var(--danger);">−<?php echo number_format($total_estorno, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong>

</div>

</div>

 <div class="kpi-card cash">

 <div class="kpi-icon"></div>

 <div class="kpi-label">Numerário</div>

 <div class="kpi-value"><?php echo number_format((float)$resumo['numerario'], 2); ?><span class="kpi-unit">MT</span></div>

</div>

 <div class="kpi-card mpesa">

 <div class="kpi-icon"></div>

 <div class="kpi-label">M-Pesa / E-Mola</div>

 <div class="kpi-value"><?php echo number_format((float)$resumo['mpesa'] + (float)$resumo['emola'] + (float)$resumo['emola_comerciante'], 2); ?><span class="kpi-unit">MT</span></div>

</div>

 <div class="kpi-card bim">

 <div class="kpi-icon"></div>

 <div class="kpi-label">POS bancário</div>

 <div class="kpi-value"><?php echo number_format((float)$sige_pos_total, 2); ?><span class="kpi-unit">MT</span></div>

</div>

 <div class="kpi-card pagafacil">

 <div class="kpi-icon"></div>

 <div class="kpi-label">Paga Fácil / Transferência</div>

 <div class="kpi-value"><?php echo number_format((float)$resumo['pagafacil'] + (float)($resumo['nib'] ?? 0) + (float)($resumo['transferencia'] ?? 0), 2); ?><span class="kpi-unit">MT</span></div>

</div>

</div>

 <?php endif; // fim modo diário ?>

 <!-- ══════════════════════════════════════

 TABELA (partilhada pelos dois modos)

 ══════════════════════════════════════ -->

 <form id="form-massa" onsubmit="sigeImprimirMassa(event)">

 <div class="sige-table-card">

 <div class="sige-table-head">
   <div class="sg-table-title-block">
     <span class="sg-table-kicker"><?php echo $sg_em_modo_aluno ? 'Histórico individual' : 'Movimentos financeiros'; ?></span>
     <h3>
       <?php if ($sg_em_modo_aluno): ?>
         Histórico de pagamentos
       <?php else: ?>
         <?php echo $rel_periodo === 'diario' ? 'Movimentos do caixa' : 'Movimentos do extracto'; ?>
       <?php endif; ?>
     </h3>
     <p class="sg-table-subtitle">
       <?php if ($sg_em_modo_aluno): ?>
         <?php echo $aluno_id ? 'Seleccione pagamentos para recibo unificado ou abra recibos individuais.' : 'Pesquise um aluno para carregar o histórico.'; ?>
       <?php else: ?>
         <?php echo esc_html($rel_periodo_label . ' · ' . $rel_ciclo_label . ' · ' . $sg_period_range); ?>
       <?php endif; ?>
     </p>
   </div>

   <div class="sige-table-head-right">
     <?php if (!empty($extrato)): ?>
       <span class="sige-count-badge"><?php echo count($extrato); ?> registos</span>
     <?php endif; ?>
     <?php if (!$sg_em_modo_aluno && !empty($extrato)): ?>
       <button type="button" data-sige-act="sigeExportarExcel" data-sige-noargs class="sige-btn sige-btn-outline sige-btn-sm" data-sg-export>⬇ Excel</button>
     <?php endif; ?>
   </div>
 </div>

 <?php if (!empty($extrato)): ?>
   <div class="sg-mobile-table-hint">Em telemóvel, cada movimento aparece como cartão. Use “Seleccionar” para recibo unificado.</div>
 <?php endif; ?>

 <?php if (empty($extrato)): ?>

 <div class="sige-empty">
   <div class="sige-empty-icon">🧾</div>
   <p>
     <?php if ($sg_em_modo_aluno && !$aluno_id): ?>
       Pesquise um aluno acima para ver o seu histórico de pagamentos.
     <?php elseif ($sg_em_modo_aluno): ?>
       Este aluno ainda não tem pagamentos registados no filtro actual.
     <?php else: ?>
       Nenhum movimento registado no período/ciclo seleccionado.
     <?php endif; ?>
   </p>
   <div class="sg-empty-actions">
     <?php if ($sg_em_modo_aluno && !$aluno_id): ?>
       <a href="#sg_aluno_search" class="sige-btn sige-btn-primary">Pesquisar aluno</a>
     <?php else: ?>
       <a href="?page=sige-app&view=financeiro-extratos&modo=diario&periodo_relatorio=diario&data_relatorio=<?php echo esc_attr(function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d')); ?>&ciclo_ensino=todos" class="sige-btn sige-btn-outline">Voltar para hoje</a>
       <a href="?page=sige-app&view=financeiro-pagamentos" class="sige-btn sige-btn-primary">Registar pagamento</a>
     <?php endif; ?>
   </div>
 </div>

 <?php else: ?>

 <div class="sige-table-wrap">

 <table class="sige-table" id="tabela-extrato">

 <caption><?php echo $modo_aluno ? 'Histórico de pagamentos do aluno' : 'Movimentos financeiros do período filtrado'; ?></caption>

 <thead>

 <tr>

 <th class="th-check"><input type="checkbox" class="sige-cb" id="cb-select-all" title="Seleccionar tudo"></th>

 <?php if ($modo_aluno): ?>

 <th class="col-hora">Data</th>

 <?php else: ?>

 <th class="col-hora"><?php echo $rel_periodo === 'diario' ? 'Hora' : 'Data/Hora'; ?></th>

 <?php endif; ?>

 <th class="col-recibo-num">Recibo</th>

 <?php if (!$modo_aluno): ?><th>Aluno</th><?php endif; ?>

 <th>Descrição</th>

 <th>Método</th>

 <?php if (!$modo_aluno): ?><th class="col-recepcionista">Recebido por</th><?php endif; ?>

 <th class="th-right">Valor (<?php echo esc_html(sige_moeda()); ?>)</th>

 <th class="th-center">Ações</th>

</tr>

</thead>

 <tbody>

 <?php foreach ($extrato as $e):

 $is_neg = ((float)$e->valor_pago < 0);

 $id_fmt = str_pad((string)$e->id, 5, '0', STR_PAD_LEFT);

 $badge_map = [

 'mpesa' => 'badge-mpesa',

 'emola' => 'badge-emola',
 'emola_comerciante' => 'badge-emola',

 'numerario' => 'badge-numerario',

 'pos_bci' => 'badge-pos',

 'pos_bim' => 'badge-pos',

 'pos_stbank' => 'badge-pos',

 'pos_moza' => 'badge-pos',

 'pos_nedbank' => 'badge-pos',

 'pos_fnb' => 'badge-pos',

 'pos' => 'badge-pos',

 'banco' => 'badge-banco',

 'bim' => 'badge-bim',

 'bci' => 'badge-bci',

 'pagafacil' => 'badge-pagafacil',

 'nib' => 'badge-nib',

 'estorno' => 'badge-estorno',

 ];

 $badge_class = $badge_map[$e->metodo_pagamento] ?? 'badge-pos';

 // Label formatada

 $label_metodo = function_exists('sige_fin_metodo_pagamento_label')
     ? strtoupper(sige_fin_metodo_pagamento_label($e->metodo_pagamento))
     : strtoupper((string)$e->metodo_pagamento);

 $pode_estornar_ui = (!$modo_aluno && $rel_periodo === 'diario' && !$dia_fechado && !$is_neg && $e->metodo_pagamento !== 'estorno');

 $classe_atual = trim((string)($e->classe_atual ?? ''));
 $servico_export = trim((string)($e->servico_nome ?? ($e->descricao ?? 'Serviço não identificado')));
 $ciclo_atual = sige_extracto_ciclo_label($classe_atual, $e->servico_ciclo ?? '', $servico_export);

 ?>

 <tr class="<?php echo $is_neg ? 'row-negativo' : ''; ?>" data-classe="<?php echo esc_attr($classe_atual); ?>" data-ciclo="<?php echo esc_attr($ciclo_atual); ?>" data-servico="<?php echo esc_attr($servico_export); ?>">

 <td class="th-check" data-label="Seleccionar">

 <?php if (!$is_neg && $e->metodo_pagamento !== 'estorno'): ?>

 <input type="checkbox" name="pag_ids[]" value="<?php echo (int)$e->id; ?>" class="sige-cb row-cb" aria-label="Seleccionar pagamento <?php echo esc_attr(($e->recibo_numero ?? '') !== '' ? $e->recibo_numero : ('#' . $id_fmt)); ?> para recibo unificado">

 <?php endif; ?>

</td>

 <td class="col-hora" data-label="<?php echo $modo_aluno ? 'Data' : ($rel_periodo === 'diario' ? 'Hora' : 'Data/Hora'); ?>">

 <?php if ($modo_aluno): ?>

 <span class="cell-date"><?php echo esc_html(wp_date('d/m/Y', strtotime($e->data_pagamento))); ?></span>

 <?php else: ?>

 <span class="cell-time"><?php echo esc_html($rel_periodo === 'diario' ? wp_date('H:i', strtotime($e->data_pagamento)) : wp_date('d/m/Y H:i', strtotime($e->data_pagamento))); ?></span>

 <?php endif; ?>

</td>

 <td class="col-recibo-num" data-label="Recibo">

 <span class="cell-id"><?php echo esc_html(($e->recibo_numero ?? '') !== '' ? $e->recibo_numero : '-'); ?></span>

</td>

 <?php if (!$modo_aluno): ?>

 <td data-label="Aluno">

 <div class="cell-student-name"><?php echo esc_html($e->nome_completo); ?></div>

 <div class="cell-student-proc"><?php echo esc_html($e->numero_processo); ?></div>

</td>

 <?php endif; ?>

 <td data-label="Descrição">

 <div class="cell-desc"><?php echo esc_html($e->descricao); ?></div>

 <?php if (!empty($e->mes_referencia)): ?>

 <div class="cell-mes">Ref: <?php echo esc_html($e->mes_referencia); ?></div>

 <?php endif; ?>

 <?php if ($is_neg && !empty($e->observacoes)): ?>

 <div class="cell-desc-note"><?php echo esc_html($e->observacoes); ?></div>

 <?php endif; ?>

</td>

 <td data-label="Método">

 <span class="sige-badge <?php echo esc_attr($badge_class); ?>">

 <?php echo esc_html($label_metodo); ?>

</span>

</td>

 <?php if (!$modo_aluno): ?>

 <td class="col-recepcionista" data-label="Recebido por" style="font-size:12px;color:var(--muted);">

 <?php echo esc_html($e->recepcionista ?: 'Sistema'); ?>

</td>

 <?php endif; ?>

 <td data-label="Valor">

 <span class="<?php echo $is_neg ? 'cell-val-neg' : 'cell-val-pos'; ?>">

 <?php echo number_format((float)$e->valor_pago, 2); ?>

</span>

</td>

 <td data-label="Acções">

 <div class="action-cell">

 <a href="<?php echo admin_url('admin.php?sige_print=recibo&id=' . (int)$e->id); ?>"

 data-sige-act="sigeAbrirJanela" data-sige-prevent data-sige-window-name="ReciboSIGE" data-sige-window-features="width=900,height=800,scrollbars=yes,resizable=yes"

 class="btn-recibo" title="Imprimir Recibo">

 Abrir recibo

</a>

 <?php if ($pode_estornar_ui): ?>

 <button type="button" class="btn-estorno"

 data-sige-act="sigeAbrirAnular" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$e->id, number_format((float)$e->valor_pago, 2, '.', '')])); ?>">

 Estornar

</button>

 <?php endif; ?>

</div>

</td>

</tr>

 <?php endforeach; ?>

</tbody>

 <tfoot>

 <tr>

 <td colspan="<?php echo $modo_aluno ? 5 : 7; ?>" style="text-align:right;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;">

 Total

</td>

 <td class="cell-val-total">

 <?php

 $total_tabela = array_sum(array_map(fn($r) => (float)$r->valor_pago, $extrato));

 echo number_format($total_tabela, 2);

 ?>

</td>

 <td></td>

</tr>

</tfoot>

</table>

</div>

 <!-- BULK ACTION BAR -->

 <div class="sige-bulk-bar hidden" id="sige-bulk-bar" aria-live="polite" aria-hidden="true">

 <span class="sige-bulk-info"><strong id="sige-sel-count">0</strong> pagamento(s) seleccionado(s)</span>

 <div class="sige-bulk-actions">

 <button type="submit" class="sige-btn-white">

 Emitir recibo

</button>

 <button type="button" data-sige-act="sigeClearAll" data-sige-noargs class="sige-btn-white" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.3);">

 Desmarcar

</button>

</div>

</div>

 <?php endif; ?>

</div><!-- /.sige-table-card -->

</form>

<?php
  $sg_mobile_fourth_target = $sg_em_modo_aluno ? '#sg_aluno_search' : (($rel_periodo === 'diario') ? '#sg-operational-summary' : '#tabela-extrato');
  $sg_mobile_fourth_label = $sg_em_modo_aluno ? 'Aluno' : (($rel_periodo === 'diario') ? 'Caixa' : 'Tabela');
?>
<nav class="sg-mobile-actionbar" aria-label="Acções rápidas do financeiro">
  <a href="?page=sige-app&view=financeiro-pagamentos" class="sg-mobile-action sg-mobile-action-primary">
    <span aria-hidden="true">＋</span><strong>Pagar</strong>
  </a>
  <button type="button" class="sg-mobile-action" data-sg-scroll="#sg-filter-card">
    <span aria-hidden="true">☰</span><strong>Filtros</strong>
  </button>
  <button type="button" class="sg-mobile-action" data-sg-export data-sige-act="sigeExportarExcel" data-sige-noargs <?php echo empty($extrato) ? 'disabled aria-disabled="true"' : ''; ?>>
    <span aria-hidden="true">⬇</span><strong>Excel</strong>
  </button>
  <button type="button" class="sg-mobile-action" data-sg-submit-bulk <?php echo empty($extrato) ? 'disabled aria-disabled="true"' : ''; ?>>
    <span aria-hidden="true">🧾</span><strong>Recibo</strong>
  </button>
  <button type="button" class="sg-mobile-action" data-sg-scroll="<?php echo esc_attr($sg_mobile_fourth_target); ?>">
    <span aria-hidden="true">↕</span><strong><?php echo esc_html($sg_mobile_fourth_label); ?></strong>
  </button>
</nav>

</div><!-- /.sige-wrap -->

<!-- ═══════════════════════════════════════════

 MODAL: ESTORNO

═══════════════════════════════════════════ -->

<div id="modal-anular" class="sige-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="sg-estorno-title" aria-describedby="sg-estorno-desc">

 <div class="sige-modal-box">

 <div class="sige-modal-hdr danger">

 <span style="font-size:22px;"></span>

 <h2 id="sg-estorno-title">Estornar Pagamento</h2>

</div>

 <div class="sige-modal-body">

 <p id="sg-estorno-desc">Está prestes a estornar o pagamento <strong id="lbl-recibo">#00000</strong>.</p>

 <p>Esta acção reverte o valor no caixa e repõe o débito no lançamento do aluno.</p>

 <div class="sige-modal-highlight">Valor a retirar: <span id="lbl-valor">0.00</span> <?php echo esc_html(sige_moeda()); ?></div>

 <form method="post" id="form-estorno" data-sg-lock-submit>

 <?php wp_nonce_field('sige_anular_recibo'); ?>

 <input type="hidden" name="sige_anular_recibo" value="1">

 <input type="hidden" name="recibo_id_anular" id="inp-recibo-id">

 <label class="sige-label">Valor do estorno</label>
<input type="number" step="0.01" min="0.01" name="valor_estorno" id="valor_estorno" class="sige-input" placeholder="Deixe vazio para estorno total">
<small style="color:#64748b;display:block;margin:6px 0 12px;">Pode fazer estorno total ou parcial.</small>
<label class="sige-label">Motivo do estorno <span style="color:var(--danger);">*</span></label>

 <textarea name="motivo_anulacao" required rows="3" class="sige-textarea"

 placeholder="Ex: Erro de lançamento, reembolso, pagamento duplicado..."></textarea>

</form>

</div>

 <div class="sige-modal-ftr">

 <button type="button" data-sige-act="sigeFecharModal" data-sige-noargs class="sige-btn sige-btn-outline">Cancelar</button>

 <button type="submit" form="form-estorno" class="sige-btn sige-btn-danger">Confirmar Estorno</button>

</div>

</div>

</div>



<!-- MODAL: CONFIRMAÇÃO DO CAIXA -->
<div id="modal-caixa-confirm" class="sige-modal-overlay sg-extracts-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="sg-caixa-confirm-title" aria-describedby="sg-caixa-confirm-text">
 <div class="sige-modal-box sg-extracts-modal-box">
   <div class="sige-modal-hdr sg-extracts-modal-hdr">
     <span class="sg-extracts-modal-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?></span>
     <div>
       <span>Confirmação</span>
       <h2 id="sg-caixa-confirm-title">Confirmar acção</h2>
     </div>
   </div>
   <div class="sige-modal-body sg-extracts-modal-body">
     <p id="sg-caixa-confirm-text">Confirme a operação antes de avançar.</p>
     <div class="sg-extracts-modal-warning">Esta confirmação ajuda a evitar alterações acidentais no caixa da escola.</div>
   </div>
   <div class="sige-modal-ftr sg-extracts-modal-ftr">
     <button type="button" data-sige-act="sigeFecharConfirmacaoCaixa" data-sige-noargs class="sige-btn sige-btn-outline">Voltar</button>
     <button type="button" data-sige-act="sigeConfirmarAcaoCaixa" data-sige-noargs class="sige-btn sige-btn-primary">Confirmar</button>
   </div>
 </div>
</div>

<!-- MODAL: RECIBO UNIFICADO -->
<div id="modal-recibo-unificado" class="sige-modal-overlay sg-extracts-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="sg-recibo-modal-title" aria-describedby="sg-recibo-modal-text">
 <div class="sige-modal-box sg-extracts-modal-box">
   <div class="sige-modal-hdr sg-extracts-modal-hdr">
     <span class="sg-extracts-modal-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('receipt') : ''; ?></span>
     <div>
       <span>Recibos</span>
       <h2 id="sg-recibo-modal-title">Emitir recibo unificado</h2>
     </div>
   </div>
   <div class="sige-modal-body sg-extracts-modal-body">
     <p id="sg-recibo-modal-text">Confirme os pagamentos seleccionados antes de abrir o recibo.</p>
     <div id="sg-recibo-modal-summary" class="sg-extracts-modal-warning">O documento será aberto numa nova janela para impressão ou gravação em PDF.</div>
   </div>
   <div class="sige-modal-ftr sg-extracts-modal-ftr">
     <button type="button" data-sige-act="sigeFecharModalRecibo" data-sige-noargs class="sige-btn sige-btn-outline">Voltar</button>
     <button type="button" id="sg-recibo-modal-confirm" data-sige-act="sigeConfirmarReciboUnificado" data-sige-noargs class="sige-btn sige-btn-primary">Abrir recibo</button>
   </div>
 </div>
</div>

<!-- ═══════════════════════════════════════════

 JAVASCRIPT

═══════════════════════════════════════════ -->

<script <?php echo sige_csp_script_attr(); ?>>

(function () {

 'use strict';

 var sgCaixaFormPendente = null;
 var sgReciboUnificadoUrl = '';
 var sgLastFocus = null;

 function sgVisibleModal() {
   return Array.prototype.slice.call(document.querySelectorAll('.sige-modal-overlay')).filter(function(m){
     return m && m.style.display !== 'none' && window.getComputedStyle(m).display !== 'none';
   }).pop() || null;
 }
 function sgFocusable(modal) {
   if (!modal) return [];
   return Array.prototype.slice.call(modal.querySelectorAll('a[href],button:not([disabled]),textarea,input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])'))
     .filter(function(el){ return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length); });
 }
 function sgOpenModal(modal, preferredFocus) {
   if (!modal) return;
   sgLastFocus = document.activeElement;
   modal.style.display = 'flex';
   modal.setAttribute('aria-modal', 'true');
   modal.setAttribute('role', 'dialog');
   document.documentElement.classList.add('sg-extracts-modal-open');
   document.body.classList.add('sg-extracts-modal-open');
   window.setTimeout(function(){
     var focusTarget = preferredFocus || sgFocusable(modal)[0];
     if (focusTarget && typeof focusTarget.focus === 'function') focusTarget.focus();
   }, 30);
 }
 function sgCloseModal(modal) {
   if (modal) modal.style.display = 'none';
   if (!sgVisibleModal()) {
     document.documentElement.classList.remove('sg-extracts-modal-open');
     document.body.classList.remove('sg-extracts-modal-open');
   }
   if (sgLastFocus && typeof sgLastFocus.focus === 'function') {
     try { sgLastFocus.focus(); } catch (err) {}
   }
 }
 function sgSetBusy(btn, label) {
   if (!btn) return;
   if (!btn.dataset.sgOriginalHtml) btn.dataset.sgOriginalHtml = btn.innerHTML;
   btn.classList.add('is-loading');
   btn.setAttribute('aria-busy', 'true');
   if (label) btn.innerHTML = label;
   window.setTimeout(function(){
     btn.classList.remove('is-loading');
     btn.removeAttribute('aria-busy');
     if (btn.dataset.sgOriginalHtml) btn.innerHTML = btn.dataset.sgOriginalHtml;
   }, 2600);
 }

 function sgNumber(value) {
   value = String(value == null ? '' : value).replace(/[^0-9,.-]/g, '').trim();
   if (!value) return 0;
   if (value.indexOf(',') !== -1 && value.indexOf('.') !== -1) {
     if (value.lastIndexOf(',') > value.lastIndexOf('.')) value = value.replace(/\./g, '').replace(',', '.');
     else value = value.replace(/,/g, '');
   } else if (value.indexOf(',') !== -1) {
     value = value.replace(/\./g, '').replace(',', '.');
   }
   var n = parseFloat(value);
   return isNaN(n) ? 0 : Math.round(n * 100) / 100;
 }
 function sgMoney(n) {
   try { return new Intl.NumberFormat('pt-MZ', {minimumFractionDigits:2, maximumFractionDigits:2}).format(n || 0); }
   catch (err) { return (Math.round((n || 0) * 100) / 100).toFixed(2).replace('.', ','); }
 }
 function sgReconDifference(form) {
   if (!form) return 0;
   var expectedGrossEl = form.querySelector('[data-sg-expected-gross]');
   var expectedGross = sgNumber(expectedGrossEl ? expectedGrossEl.getAttribute('data-sg-expected-gross') : 0);
   var counted = 0;
   form.querySelectorAll('[data-sg-recon-row]').forEach(function(row){
     var systemEl = row.querySelector('[data-sg-system]');
     var input = row.querySelector('.sg-recon-counted');
     var diffEl = row.querySelector('[data-sg-row-diff]');
     var system = sgNumber(systemEl ? systemEl.getAttribute('data-sg-system') : 0);
     var val = sgNumber(input ? input.value : 0);
     counted += val;
     var diff = Math.round((val - system) * 100) / 100;
     if (diffEl) {
       diffEl.textContent = sgMoney(diff);
       diffEl.classList.toggle('has-diff', Math.abs(diff) >= 0.01);
       diffEl.classList.toggle('is-zero', Math.abs(diff) < 0.01);
     }
   });
   var totalEl = form.querySelector('[data-sg-counted-total]');
   if (totalEl) totalEl.textContent = sgMoney(counted) + ' <?php echo esc_js(sige_moeda()); ?>';
   var totalDiff = Math.round((counted - expectedGross) * 100) / 100;
   var diffTotalEl = form.querySelector('[data-sg-recon-difference]');
   if (diffTotalEl) {
     diffTotalEl.textContent = sgMoney(totalDiff) + ' <?php echo esc_js(sige_moeda()); ?>';
     diffTotalEl.classList.toggle('has-diff', Math.abs(totalDiff) >= 0.01);
     diffTotalEl.classList.toggle('is-zero', Math.abs(totalDiff) < 0.01);
   }
   var noteField = form.querySelector('.sg-recon-note-field');
   var noteHint = form.querySelector('[data-sg-recon-note-hint]');
   var note = form.querySelector('[name="motivo_divergencia"]');
   var requiresNote = Math.abs(totalDiff) >= 0.01;
   if (note) note.required = requiresNote;
   if (noteField) noteField.classList.toggle('is-required', requiresNote);
   if (noteHint) noteHint.textContent = requiresNote ? 'Obrigatório: existe diferença entre o sistema e o total contado.' : 'Se a diferença for 0,00, esta observação é opcional.';
   return totalDiff;
 }
 function sgValidateReconForm(form, showMessages) {
   if (!form || !form.hasAttribute('data-sg-recon-form')) return true;
   var diff = sgReconDifference(form);
   var note = form.querySelector('[name="motivo_divergencia"]');
   var confirm = form.querySelector('[name="sg_recon_confirmado"]');
   if (Math.abs(diff) >= 0.01 && note && note.value.trim().length < 6) {
     if (showMessages) sigeUi.toast('Existe divergência entre o sistema e o valor contado. Escreva uma observação antes de fechar.', 'aviso');
     note.focus();
     return false;
   }
   if (confirm && !confirm.checked) {
     if (showMessages) sigeUi.toast('Confirme a checklist de reconciliação antes de fechar o caixa.', 'aviso');
     confirm.focus();
     return false;
   }
   return true;
 }
 function sgInitReconForms() {
   document.querySelectorAll('[data-sg-recon-form]').forEach(function(form){
     form.querySelectorAll('.sg-recon-counted').forEach(function(input){
       input.addEventListener('input', function(){ sgReconDifference(form); });
       input.addEventListener('blur', function(){ input.value = (sgNumber(input.value)).toFixed(2); sgReconDifference(form); });
     });
     var note = form.querySelector('[name="motivo_divergencia"]');
     if (note) note.addEventListener('input', function(){ sgReconDifference(form); });
     sgReconDifference(form);
   });
 }
 window.sigeImprimirTermoFecho = function() {
   var source = document.getElementById('sg-termo-fecho');
   if (!source) { window.print(); return; }
   var win = window.open('', 'TermoFechoCaixa', 'width=980,height=860,scrollbars=yes,resizable=yes');
   if (!win) { window.print(); return; }
   win.document.open();
   win.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Termo de Fecho de Caixa</title><style>body{font-family:Arial,sans-serif;margin:28px;color:#111}.sg-extracts-close-summary{background:#fff!important;color:#111!important}.sg-close-summary-head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #111;padding-bottom:14px;margin-bottom:16px}.sg-close-summary-head span,.sg-close-method span,.sg-close-recon-grid span,.sg-close-note span{display:block;font-size:10px;text-transform:uppercase;color:#555;font-weight:700}.sg-close-summary-head strong{font-size:22px}.sg-close-method-grid,.sg-close-recon-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:12px 0}.sg-close-method,.sg-close-recon-grid div,.sg-close-note,.sg-close-total-bar{border:1px solid #ddd;border-radius:10px;padding:10px}.sg-close-recon-methods-head,.sg-close-recon-methods-row{display:grid;grid-template-columns:1fr 100px 100px 90px;gap:8px;border-bottom:1px solid #eee;padding:8px}.sg-close-total-bar{display:flex;justify-content:space-between;align-items:center;margin-top:14px}.sg-close-print-btn{display:none}.has-diff{color:#b42318}.is-ok{color:#128754}@media print{button{display:none}}</style></head><body>' + source.outerHTML + '<script>window.onload=function(){window.focus();window.print();};<\/script></body></html>');
   win.document.close();
 };

 window.sigeAbrirModalRecibo = function(titulo, texto, resumo, url, podeConfirmar) {
   sgReciboUnificadoUrl = url || '';
   var modal = document.getElementById('modal-recibo-unificado');
   var title = document.getElementById('sg-recibo-modal-title');
   var body = document.getElementById('sg-recibo-modal-text');
   var summary = document.getElementById('sg-recibo-modal-summary');
   var btn = document.getElementById('sg-recibo-modal-confirm');
   if (title) title.textContent = titulo || 'Emitir recibo unificado';
   if (body) body.textContent = texto || 'Confirme os pagamentos seleccionados antes de abrir o recibo.';
   if (summary) summary.textContent = resumo || 'O documento será aberto numa nova janela para impressão ou gravação em PDF.';
   if (btn) btn.style.display = podeConfirmar ? '' : 'none';
   if (modal) { sgOpenModal(modal, btn && podeConfirmar ? btn : null); }
 };
 window.sigeFecharModalRecibo = function() {
   var modal = document.getElementById('modal-recibo-unificado');
   sgCloseModal(modal);
   sgReciboUnificadoUrl = '';
 };
 window.sigeConfirmarReciboUnificado = function() {
   var url = sgReciboUnificadoUrl;
   window.sigeFecharModalRecibo();
   if (url) window.open(url, 'ReciboUnificado', 'width=1040,height=820,scrollbars=yes,resizable=yes');
 };

 window.sigeAbrirConfirmacaoCaixa = function(form, titulo, texto) {
   if (form && form.hasAttribute && form.hasAttribute('data-sg-recon-form') && !sgValidateReconForm(form, true)) return false;
   sgCaixaFormPendente = form || null;
   var modal = document.getElementById('modal-caixa-confirm');
   var title = document.getElementById('sg-caixa-confirm-title');
   var body = document.getElementById('sg-caixa-confirm-text');
   if (title) title.textContent = titulo || 'Confirmar acção';
   if (body) body.textContent = texto || 'Confirme a operação antes de avançar.';
   if (modal) {
     var confirmBtn = modal.querySelector('.sige-btn-primary');
     sgOpenModal(modal, confirmBtn);
   }
   return false;
 };
 window.sigeFecharConfirmacaoCaixa = function() {
   var modal = document.getElementById('modal-caixa-confirm');
   sgCloseModal(modal);
   sgCaixaFormPendente = null;
 };
 window.sigeConfirmarAcaoCaixa = function() {
   if (sgCaixaFormPendente) {
     var f = sgCaixaFormPendente;
     if (f.dataset.sgSubmitted === '1') return;
     f.dataset.sgSubmitted = '1';
     var modal = document.getElementById('modal-caixa-confirm');
     var btn = modal ? modal.querySelector('.sige-btn-primary') : null;
     if (btn) { btn.disabled = true; btn.textContent = 'A processar…'; }
     sgCaixaFormPendente = null;
     f.submit();
   }
 };


 // [12.9.77] Alternância inteligente dos campos de período no extracto.
 // Diário mostra dia; Mensal mostra mês; Anual mostra ano.
 var periodoSelect = document.getElementById('sige_periodo_relatorio');
 var periodoFields = Array.prototype.slice.call(document.querySelectorAll('.sige-period-field'));
 function sigeSyncPeriodoFields() {
   var val = periodoSelect ? periodoSelect.value : 'diario';
   periodoFields.forEach(function(field){ field.style.display = field.getAttribute('data-period') === val ? '' : 'none'; });
 }
 if (periodoSelect) { periodoSelect.addEventListener('change', sigeSyncPeriodoFields); sigeSyncPeriodoFields(); }
 sgInitReconForms();

 // [v12.11.9.87] Micro-interacções PRO: submit idempotente, scroll assistido,
 // botões com feedback e preferências de densidade persistidas localmente.
 var sgRoot = document.querySelector('.sg-extracts-wrap');
 if (sgRoot && sgRoot.dataset.sgDensity) {
   try { window.localStorage.setItem('sige_fin_extratos_density', sgRoot.dataset.sgDensity); } catch (err) {}
 }
 document.querySelectorAll('form[data-sg-lock-submit]').forEach(function(form){
   form.addEventListener('submit', function(e){
     if (form.hasAttribute('data-sg-recon-form') && !sgValidateReconForm(form, true)) { e.preventDefault(); return false; }
     if (form.dataset.sgSubmitted === '1') { e.preventDefault(); return false; }
     form.dataset.sgSubmitted = '1';
     var submitter = form.querySelector('button[type="submit"],input[type="submit"]');
     if (submitter) {
       submitter.classList.add('is-loading');
       submitter.setAttribute('aria-busy', 'true');
       if (submitter.tagName === 'BUTTON') submitter.dataset.sgOriginalText = submitter.textContent;
       if (submitter.tagName === 'BUTTON') submitter.textContent = 'A processar…';
     }
   });
 });
 document.addEventListener('click', function(e){
   var scrollBtn = e.target.closest('[data-sg-scroll]');
   if (scrollBtn) {
     var target = document.querySelector(scrollBtn.getAttribute('data-sg-scroll'));
     if (target) {
       e.preventDefault();
       target.scrollIntoView({behavior:'smooth', block:'start'});
       target.classList.add('sg-scroll-highlight');
       window.setTimeout(function(){ target.classList.remove('sg-scroll-highlight'); }, 1400);
       if (typeof target.focus === 'function') { target.setAttribute('tabindex','-1'); window.setTimeout(function(){ target.focus({preventScroll:true}); }, 420); }
     }
   }
   var exportBtn = e.target.closest('[data-sg-export]');
   if (exportBtn && !exportBtn.disabled) { sgSetBusy(exportBtn, 'A gerar…'); }
   var bulkBtn = e.target.closest('[data-sg-submit-bulk]');
   if (bulkBtn && !bulkBtn.disabled) {
     e.preventDefault();
     var form = document.getElementById('form-massa');
     if (form) {
       var evt = new Event('submit', {cancelable:true});
       form.dispatchEvent(evt);
     }
   }
 });

 // ── Modal de Estorno ──────────────────────

 window.sigeAbrirAnular = function(id, valor) {

 document.getElementById('inp-recibo-id').value = id;

 document.getElementById('lbl-recibo').textContent = '#' + String(id).padStart(5, '0');

 document.getElementById('lbl-valor').textContent = valor;

 const overlay = document.getElementById('modal-anular');

 sgOpenModal(overlay, document.getElementById('valor_estorno'));

 overlay.addEventListener('click', function backdropClick(e) {

 if (e.target === overlay) { sigeFecharModal(); overlay.removeEventListener('click', backdropClick); }

 });

 };

 window.sigeFecharModal = function() {

 var modal = document.getElementById('modal-anular');
 sgCloseModal(modal);

 };

 document.addEventListener('keydown', function(e) {
   var modal = sgVisibleModal();
   if (!modal) return;
   if (e.key === 'Escape') {
     if (modal.id === 'modal-caixa-confirm') window.sigeFecharConfirmacaoCaixa();
     else if (modal.id === 'modal-recibo-unificado') window.sigeFecharModalRecibo();
     else window.sigeFecharModal();
     return;
   }
   if (e.key !== 'Tab') return;
   var items = sgFocusable(modal);
   if (!items.length) return;
   var first = items[0];
   var last = items[items.length - 1];
   if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
   else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
 });

 // ── Select All / Bulk Bar ─────────────────

 var selectAll = document.getElementById('cb-select-all');

 var bulkBar = document.getElementById('sige-bulk-bar');

 var selCount = document.getElementById('sige-sel-count');

 function updateBulkBar() {

 var checked = document.querySelectorAll('input.row-cb:checked').length;

 if (!bulkBar || !selCount) { return; }

 if (checked > 0) {

 bulkBar.classList.remove('hidden');
 bulkBar.setAttribute('aria-hidden', 'false');

 selCount.textContent = checked;

 } else {

 bulkBar.classList.add('hidden');
 bulkBar.setAttribute('aria-hidden', 'true');

 }

 if (selectAll) {

 var total = document.querySelectorAll('input.row-cb').length;

 selectAll.indeterminate = checked > 0 && checked < total;

 selectAll.checked = total > 0 && checked === total;

 }

 }

 if (selectAll) {

 selectAll.addEventListener('change', function() {

 document.querySelectorAll('input.row-cb').forEach(function(cb) {

 cb.checked = selectAll.checked;

 cb.closest('tr').classList.toggle('row-selected', selectAll.checked);

 });

 updateBulkBar();

 });

 }

 document.querySelectorAll('input.row-cb').forEach(function(cb) {

 cb.addEventListener('change', function() {

 cb.closest('tr').classList.toggle('row-selected', cb.checked);

 updateBulkBar();

 });

 });

 window.sigeClearAll = function() {

 document.querySelectorAll('input.row-cb').forEach(function(cb) {

 cb.checked = false;

 cb.closest('tr').classList.remove('row-selected');

 });

 if (selectAll) selectAll.checked = false;

 updateBulkBar();

 };

 // ── Recibo Unificado (massa) ──────────────

 window.sigeImprimirMassa = function(e) {

 e.preventDefault();

 var ids = Array.from(document.querySelectorAll('input.row-cb:checked')).map(function(cb) { return cb.value; });

 if (ids.length === 0) {

 sigeAbrirModalRecibo('Seleccione pagamentos', 'Seleccione pelo menos um pagamento antes de emitir o recibo unificado.', 'Marque os pagamentos pretendidos na tabela e tente novamente.', '', false);

 return;

 }

 var url = '<?php echo esc_js(admin_url('admin.php?sige_print=recibo_massa&ids=')); ?>' + ids.join(',');

 sigeAbrirModalRecibo('Emitir recibo unificado', 'Vai abrir um recibo consolidado com ' + ids.length + ' pagamento(s) seleccionado(s).', 'Confirme se os pagamentos seleccionados pertencem ao recibo que pretende imprimir ou guardar em PDF.', url, true);

 };

 // ── Exportar Excel Profissional ────────────────────────

 window.sigeExportarExcel = async function() {

 var tabela = document.getElementById('tabela-extrato');
 if (!tabela) { sigeUi.toast('Não há dados para exportar com os filtros actuais.', 'aviso'); return; }

 if (typeof ExcelJS === 'undefined') {
   // Fallback seguro: mantém exportação funcional mesmo sem CDN ExcelJS.
   var wbFallback = XLSX.utils.book_new();
   var wsFallback = XLSX.utils.table_to_sheet(tabela);
   wsFallback['!cols'] = [{wch:8},{wch:14},{wch:18},{wch:28},{wch:42},{wch:18},{wch:22},{wch:16},{wch:12}];
   XLSX.utils.book_append_sheet(wbFallback, wsFallback, 'Extracto');
   XLSX.writeFile(wbFallback, <?php echo wp_json_encode($modo_aluno && $aluno_id ? ('Historico_Financeiro_Aluno_' . (int)$aluno_id . '.xlsx') : ('Extracto_' . $rel_periodo . '_' . $rel_ciclo . '_' . $data_inicio_relatorio . '_a_' . $data_fim_relatorio . '.xlsx')); ?>);
   return;
 }

 var meta = {
   escola: <?php echo wp_json_encode($__sige_nome_escola); ?>,
   contacto: <?php echo wp_json_encode($__sige_contacto_escola); ?>,
   email: <?php echo wp_json_encode($__sige_email_escola); ?>,
   endereco: <?php echo wp_json_encode($__sige_endereco_escola); ?>,
   data: <?php echo wp_json_encode($data_filtro); ?>,
   periodo: <?php echo wp_json_encode($rel_periodo_label); ?>,
   ciclo: <?php echo wp_json_encode($rel_ciclo_label); ?>,
   dataInicio: <?php echo wp_json_encode($data_inicio_relatorio); ?>,
   dataFim: <?php echo wp_json_encode($data_fim_relatorio); ?>,
   moeda: <?php echo wp_json_encode(sige_moeda()); ?>,
   modoAluno: <?php echo $modo_aluno ? 'true' : 'false'; ?>,
   alunoNome: <?php echo wp_json_encode(($modo_aluno && !empty($aluno_dados)) ? (string)$aluno_dados->nome_completo : ''); ?>,
   alunoProcesso: <?php echo wp_json_encode(($modo_aluno && !empty($aluno_dados)) ? (string)$aluno_dados->numero_processo : ''); ?>
 };

 var wb = new ExcelJS.Workbook();
 wb.creator = 'SIGE SoftGenial';
 wb.created = new Date();
 wb.modified = new Date();
 var ws = wb.addWorksheet('Extracto', {
   views: [{ state: 'frozen', ySplit: 7 }],
   pageSetup: { paperSize: 9, orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 }
 });

 var totalCols = meta.modoAluno ? 7 : 9;
 var lastCol = meta.modoAluno ? 'G' : 'I';

 ws.mergeCells('A1:' + lastCol + '1');
 ws.getCell('A1').value = meta.escola || 'SIGE SoftGenial';
 ws.getCell('A1').font = { bold:true, size:16, color:{argb:'FFFFFFFF'} };
 ws.getCell('A1').alignment = { horizontal:'center', vertical:'middle' };
 ws.getCell('A1').fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF0E4194'} };
 ws.getRow(1).height = 26;

 ws.mergeCells('A2:' + lastCol + '2');
 ws.getCell('A2').value = [meta.endereco, meta.contacto ? 'Contacto: ' + meta.contacto : '', meta.email].filter(Boolean).join('  |  ');
 ws.getCell('A2').font = { size:10, color:{argb:'FF334155'} };
 ws.getCell('A2').alignment = { horizontal:'center' };

 ws.mergeCells('A4:' + lastCol + '4');
 ws.getCell('A4').value = meta.modoAluno ? 'HISTÓRICO FINANCEIRO DO ALUNO' : 'EXTRACTO FINANCEIRO';
 ws.getCell('A4').font = { bold:true, size:14, color:{argb:'FF0E4194'} };
 ws.getCell('A4').alignment = { horizontal:'center' };

 ws.mergeCells('A5:' + lastCol + '5');
 ws.getCell('A5').value = meta.modoAluno ? ('Aluno: ' + (meta.alunoNome || '-') + '  |  Processo: ' + (meta.alunoProcesso || '-') + '  |  Exportado em: ' + new Date().toLocaleString('pt-MZ')) : (meta.periodo + '  |  Ciclo: ' + meta.ciclo + '  |  Intervalo: ' + meta.dataInicio + ' a ' + meta.dataFim + '  |  Exportado em: ' + new Date().toLocaleString('pt-MZ'));
 ws.getCell('A5').font = { italic:true, size:10, color:{argb:'FF475569'} };
 ws.getCell('A5').alignment = { horizontal:'center' };

 var headers = meta.modoAluno
   ? ['Data', 'Recibo', 'Descrição', 'Método', 'Valor (' + meta.moeda + ')', 'Estado', 'Observações']
   : ['Hora', 'Recibo', 'Aluno', 'Processo', 'Descrição', 'Método', 'Recebido por', 'Valor (' + meta.moeda + ')', 'Estado'];
 ws.addRow([]);
 var headerRow = ws.addRow(headers);
 headerRow.height = 22;
 headerRow.eachCell(function(cell){
   cell.font = { bold:true, color:{argb:'FFFFFFFF'} };
   cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF1A237E'} };
   cell.alignment = { horizontal:'center', vertical:'middle' };
   cell.border = { top:{style:'thin',color:{argb:'FFCBD5E1'}}, left:{style:'thin',color:{argb:'FFCBD5E1'}}, bottom:{style:'thin',color:{argb:'FFCBD5E1'}}, right:{style:'thin',color:{argb:'FFCBD5E1'}} };
 });

 var total = 0;
 var totaisPorClasse = {};
 var totaisPorCiclo = {};
 var totaisPorMetodo = {};
 var totaisPorServico = {};
 var movimentosPorClasse = {};
 var movimentosPorCiclo = {};
 var movimentosPorMetodo = {};
 var movimentosPorServico = {};

 function classeNumero(label) {
   var m = String(label || '').match(/\d+/);
   return m ? parseInt(m[0], 10) : 999;
 }
 function normalizarClasse(label) {
   label = String(label || '').replace(/\s+/g, ' ').trim();
   if (!label) return 'Sem classe';
   var n = classeNumero(label);
   if (n !== 999) return n + 'ª Classe';
   return label;
 }
 function cicloDaClasse(label, fallback) {
   var n = classeNumero(label);
   if (n >= 1 && n <= 6) return 'Primário';
   if (n >= 7 && n <= 12) return 'Secundário';
   return fallback || 'Outro';
 }
 function addTotal(map, countMap, key, valor) {
   key = key || 'Outro';
   map[key] = (map[key] || 0) + valor;
   countMap[key] = (countMap[key] || 0) + 1;
 }

 var rows = Array.prototype.slice.call(tabela.querySelectorAll('tbody tr'));
 rows.forEach(function(tr){
   var tds = Array.prototype.slice.call(tr.querySelectorAll('td'));
   if (!tds.length) return;
   var isNeg = tr.classList.contains('row-negativo');
   var clean = function(el){ return (el ? (el.innerText || el.textContent || '') : '').replace(/\s+/g,' ').trim(); };
   var valorTxt = clean(tds[meta.modoAluno ? 5 : 7] || tds[tds.length-2]);
   var valor = parseFloat(valorTxt.replace(/[^0-9,.-]/g,'').replace(/,/g,'')) || 0;
   if (isNeg && valor > 0) valor = -valor;
   total += valor;

   if (!meta.modoAluno) {
     var classeLabel = normalizarClasse(tr.getAttribute('data-classe') || '');
     var cicloLabel = cicloDaClasse(classeLabel, tr.getAttribute('data-ciclo') || 'Outro');
     addTotal(totaisPorClasse, movimentosPorClasse, classeLabel, valor);
     addTotal(totaisPorCiclo, movimentosPorCiclo, cicloLabel, valor);
   }

   var data = meta.modoAluno ? clean(tds[1]) : clean(tds[1]);
   var recibo = meta.modoAluno ? clean(tds[2]) : clean(tds[2]);
   var descricao = meta.modoAluno ? clean(tds[3]) : clean(tds[4]);
   var metodo = meta.modoAluno ? clean(tds[4]) : clean(tds[5]);
   var servico = tr.getAttribute('data-servico') || descricao || 'Serviço não identificado';
   // [v12.9.77] Agregação por serviço no Excel, respeitando o período/ciclo filtrado.
   addTotal(totaisPorServico, movimentosPorServico, servico, valor);
   // [v12.9.60] Agregação por método de pagamento (em ambos os modos)
   addTotal(totaisPorMetodo, movimentosPorMetodo, metodo || 'SEM MÉTODO', valor);
   var rowValues = meta.modoAluno
     ? [data, recibo, descricao, metodo, valor, isNeg ? 'Estorno' : 'Recebimento', '']
     : [data, recibo, clean(tds[3]).split(' ')[0] ? clean(tds[3]).replace(/(\d{4,})$/, '').trim() : clean(tds[3]), (clean(tds[3]).match(/(\d{4,})$/)||['',''])[1], descricao, metodo, clean(tds[6]), valor, isNeg ? 'Estorno' : 'Recebimento'];
   var r = ws.addRow(rowValues);
   r.eachCell(function(cell, col){
     cell.border = { bottom:{style:'thin',color:{argb:'FFE2E8F0'}} };
     cell.alignment = { vertical:'top', wrapText:true };
     if (col === (meta.modoAluno ? 5 : 8)) {
       cell.numFmt = '#,##0.00';
       cell.font = { bold:true, color:{argb: isNeg ? 'FFDC2626' : 'FF166534'} };
       cell.alignment = { horizontal:'right' };
     }
   });
 });

 var totalRow = ws.addRow([]);
 var totalLabelCol = meta.modoAluno ? 4 : 7;
 totalRow.getCell(totalLabelCol).value = 'TOTAL LÍQUIDO';
 totalRow.getCell(totalLabelCol).font = { bold:true, color:{argb:'FF0F172A'} };
 totalRow.getCell(totalLabelCol + 1).value = total;
 totalRow.getCell(totalLabelCol + 1).numFmt = '#,##0.00';
 totalRow.getCell(totalLabelCol + 1).font = { bold:true, color:{argb:'FF0E4194'} };
 totalRow.getCell(totalLabelCol + 1).alignment = { horizontal:'right' };
 totalRow.eachCell(function(cell){
   cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FFEFF6FF'} };
   cell.border = { top:{style:'medium', color:{argb:'FF0E4194'}} };
 });

 // [v12.9.60] Helpers de resumo movidos para fora do `if (!meta.modoAluno)`
 // para permitir que a secção POR MÉTODO funcione em AMBOS os modos
 // (extracto geral E extracto individual de aluno).
 function addResumoTitulo(titulo) {
   ws.addRow([]);
   var r = ws.addRow([titulo]);
   ws.mergeCells('A' + r.number + ':' + lastCol + r.number);
   r.getCell(1).font = { bold:true, size:12, color:{argb:'FFFFFFFF'} };
   r.getCell(1).alignment = { horizontal:'center' };
   r.getCell(1).fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF0E4194'} };
 }
 function addResumoHeader() {
   var r = ws.addRow(['Agrupamento', 'Movimentos', 'Total (' + meta.moeda + ')']);
   for (var i = 1; i <= 3; i++) {
     var c = r.getCell(i);
     c.font = { bold:true, color:{argb:'FFFFFFFF'} };
     c.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF1A237E'} };
     c.alignment = { horizontal:i === 3 ? 'right' : 'left' };
     c.border = { top:{style:'thin',color:{argb:'FFCBD5E1'}}, left:{style:'thin',color:{argb:'FFCBD5E1'}}, bottom:{style:'thin',color:{argb:'FFCBD5E1'}}, right:{style:'thin',color:{argb:'FFCBD5E1'}} };
   }
 }
 function addResumoRows(map, countMap, sortFn) {
   Object.keys(map).sort(sortFn).forEach(function(k){
     var r = ws.addRow([k, countMap[k] || 0, map[k] || 0]);
     r.getCell(1).font = { bold:true, color:{argb:'FF0F172A'} };
     r.getCell(2).alignment = { horizontal:'center' };
     r.getCell(3).numFmt = '#,##0.00';
     r.getCell(3).font = { bold:true, color:{argb:(map[k] || 0) < 0 ? 'FFDC2626' : 'FF166534'} };
     r.getCell(3).alignment = { horizontal:'right' };
     for (var i = 1; i <= 3; i++) {
       r.getCell(i).border = { bottom:{style:'thin',color:{argb:'FFE2E8F0'}} };
     }
   });
 }
 // Linha de TOTAL no fim de uma tabela de resumo
 function addResumoTotal(map) {
   var soma = 0, qtd = 0;
   Object.keys(map).forEach(function(k){ soma += (map[k] || 0); qtd += 1; });
   var r = ws.addRow(['TOTAL GERAL', '', soma]);
   r.getCell(1).font = { bold:true, color:{argb:'FFFFFFFF'} };
   r.getCell(1).fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF0E4194'} };
   r.getCell(2).fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF0E4194'} };
   r.getCell(3).numFmt = '#,##0.00';
   r.getCell(3).font = { bold:true, color:{argb:'FFFFFFFF'} };
   r.getCell(3).fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF0E4194'} };
   r.getCell(3).alignment = { horizontal:'right' };
 }

 if (!meta.modoAluno) {
   addResumoTitulo('PAGAMENTOS CUMULATIVOS POR CLASSE');
   addResumoHeader();
   addResumoRows(totaisPorClasse, movimentosPorClasse, function(a, b){ return classeNumero(a) - classeNumero(b) || a.localeCompare(b); });

   addResumoTitulo('PAGAMENTOS CUMULATIVOS POR CICLO');
   addResumoHeader();
   addResumoRows(totaisPorCiclo, movimentosPorCiclo, function(a, b){
     var ordem = {'Primário':1, 'Secundário':2, 'Outro':3};
     return (ordem[a] || 99) - (ordem[b] || 99) || a.localeCompare(b);
   });
 }

 // [v12.9.77] TOTAIS RECEBIDOS POR SERVIÇO
 // Aparece em ambos os modos e respeita exactamente as linhas filtradas/exportadas.
 if (Object.keys(totaisPorServico).length > 0) {
   addResumoTitulo('VALORES RECEBIDOS POR SERVIÇO');
   addResumoHeader();
   addResumoRows(totaisPorServico, movimentosPorServico, function(a, b){
     var diff = (totaisPorServico[b] || 0) - (totaisPorServico[a] || 0);
     return diff !== 0 ? diff : a.localeCompare(b);
   });
   addResumoTotal(totaisPorServico);
 }

 // [v12.9.60] TOTAIS RECEBIDOS POR MÉTODO DE PAGAMENTO
 // Aparece em ambos os modos (geral e aluno individual).
 // Sort DESC pelo total - método com maior recebimento aparece primeiro.
 if (Object.keys(totaisPorMetodo).length > 0) {
   addResumoTitulo('TOTAIS RECEBIDOS POR MÉTODO DE PAGAMENTO');
   addResumoHeader();
   addResumoRows(totaisPorMetodo, movimentosPorMetodo, function(a, b){
     var diff = (totaisPorMetodo[b] || 0) - (totaisPorMetodo[a] || 0);
     return diff !== 0 ? diff : a.localeCompare(b);
   });
   addResumoTotal(totaisPorMetodo);
 }

 ws.columns = meta.modoAluno
   ? [{width:14},{width:18},{width:44},{width:22},{width:16},{width:16},{width:24}]
   : [{width:12},{width:18},{width:30},{width:14},{width:42},{width:22},{width:22},{width:16},{width:16}];
 ws.autoFilter = { from: 'A7', to: lastCol + '7' };

 var buffer = await wb.xlsx.writeBuffer();
 var blob = new Blob([buffer], {type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
 if (typeof saveAs !== 'undefined') {
   saveAs(blob, <?php echo wp_json_encode($modo_aluno && $aluno_id ? ('Historico_Financeiro_Aluno_' . (int)$aluno_id . '.xlsx') : ('Extracto_' . $rel_periodo . '_' . $rel_ciclo . '_' . $data_inicio_relatorio . '_a_' . $data_fim_relatorio . '.xlsx')); ?>);
 } else {
   var a = document.createElement('a');
   a.href = URL.createObjectURL(blob);
   a.download = <?php echo wp_json_encode($modo_aluno && $aluno_id ? ('Historico_Financeiro_Aluno_' . (int)$aluno_id . '.xlsx') : ('Extracto_' . $rel_periodo . '_' . $rel_ciclo . '_' . $data_inicio_relatorio . '_a_' . $data_fim_relatorio . '.xlsx')); ?>;
   a.click();
   URL.revokeObjectURL(a.href);
 }

 };

})();

</script>