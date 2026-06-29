<?php
if (!defined('ABSPATH')) exit;
/**
 * SIGE - Gestão de Lançamentos (Premium UI)
 * Versão: Premium + Detalhes + Paginação + Reativar
 *
 * REFATORIZADO: Abril 2026 - Design System v1.0
 * - Removido CSS inline (~115 linhas)
 * - Classes sg-* do Design System
 * - Lógica PHP 100% preservada
 *
 * - Lista lançamentos com filtros e paginação
 * - Ver detalhes (modal) incluindo campos de multa/desconto quando existirem
 * - Cancelar (soft) com motivo obrigatório, bloqueando se já houver pagamento
 * - Reativar (apenas se cancelado e sem pagamentos)
 *
 * Compatibilidade:
 * - Auto-detecção de nomes de colunas (valor, mês, vencimento, status, etc.)
 */

// [FIX R-02] Guard de acesso - Tesouraria (Lançamentos)
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.lancamentos_ver','financeiro.lancamentos_gerir'],
    ['sige_director','sige_secretario','sige_financeiro']
)) return;

if (!defined('ABSPATH')) exit;

global $wpdb;

// Permissões - verificação fina já garantida na guarda de entrada acima.
// [12.9.6] Mantida apenas como camada de defesa em profundidade.
if (!sige_page_guard_allows(
    ['financeiro.lancamentos_ver','financeiro.lancamentos_gerir'],
    ['sige_financeiro','sige_secretario']
)) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Sem permissão.</div></div>';
 return;
}

// =============================
// Helpers
// =============================
function sige_first_existing_col(array $cols, array $candidates) {
 $cols_l = array_map('strtolower', $cols);
 foreach ($candidates as $c) {
 $idx = array_search(strtolower($c), $cols_l, true);
 if ($idx !== false) return $cols[$idx];
 }
 return null;
}

function sige_table_exists($table) {
 global $wpdb;
 $like = $wpdb->esc_like($table);
 $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
 return !empty($found);
}

function sige_get_table_cols($table) {
 global $wpdb;
 $cols = [];
 try {
 $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table}");
 if ($rows) {
 foreach ($rows as $r) $cols[] = $r->Field;
 }
 } catch (Throwable $e) {}
 return $cols;
}

function sige_h($v) { return esc_html((string)$v); }

$sg_mz_tz = new DateTimeZone('Africa/Maputo');
$sg_mz_now = new DateTime('now', $sg_mz_tz);

// =============================
// Tables
// =============================
$tL = $wpdb->prefix . 'sige_fin_lancamentos';
$tP = $wpdb->prefix . 'sige_fin_pagamentos';
$tS = $wpdb->prefix . 'sige_fin_servicos';
$tA = $wpdb->prefix . 'sige_alunos';

if (!sige_table_exists($tL)) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Tabela de lançamentos não encontrada.</div></div>';
 return;
}

// =============================
// Column detection
// =============================
$lcols = sige_get_table_cols($tL);
$col_id = sige_first_existing_col($lcols, ['id','lancamento_id','id_lancamento']);
$col_aluno_id = sige_first_existing_col($lcols, ['aluno_id','id_aluno','student_id']);
$col_servico_id= sige_first_existing_col($lcols, ['servico_id','id_servico','service_id']);
$col_mes = sige_first_existing_col($lcols, ['mes_ref','mes','mes_ano','mes_referencia','referencia_mes']);
$col_venc = sige_first_existing_col($lcols, ['vencimento','data_vencimento','vencimento_data','due_date']);
$col_status = sige_first_existing_col($lcols, ['status','estado']);
$col_valor = sige_first_existing_col($lcols, ['valor_total','valor','valor_original','total','montante','valor_a_pagar']);
$col_pago = sige_first_existing_col($lcols, ['valor_pago','total_pago','pago','valor_pago_total']);
$col_multa = sige_first_existing_col($lcols, ['multa','valor_multa','multa_valor','multa_aplicada']);
$col_desconto = sige_first_existing_col($lcols, ['desconto','valor_desconto','desconto_valor','desconto_aplicado']);
$col_desc = sige_first_existing_col($lcols, ['descricao','observacao','obs','nota','detalhes']);
$col_criado_em = sige_first_existing_col($lcols, ['criado_em','created_at','data_registo','data_criacao']);
$col_cancel_em = sige_first_existing_col($lcols, ['cancelado_em','anulado_em']);
$col_cancel_por= sige_first_existing_col($lcols, ['cancelado_por','anulado_por']);
$col_cancel_mot= sige_first_existing_col($lcols, ['motivo_cancelamento','motivo_cancelado','motivo_anulacao']);

if (!$col_id || !$col_valor) {
 echo '<div class="sg-alert sg-alert-error sg-m-4"><span class="sg-alert-icon"></span><div class="sg-alert-content">Estrutura da tabela de lançamentos inesperada (faltam colunas essenciais).</div></div>';
 return;
}

// ── escola_id centralizado ────────────────────────────────────────────────
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
if ($escola_id <= 0) {
 echo '<div class="notice notice-error"><p>Escola não identificada. Lançamentos bloqueados para evitar acesso ao tenant errado.</p></div>';
 return;
}

// [v12.9.78] Metadados da escola para exportação Excel dos lançamentos.
$__sige_perfil_escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$__sige_nome_escola = $__sige_perfil_escola->nome_escola ?? get_bloginfo('name');
$__sige_contacto_escola = $__sige_perfil_escola->telefone_oficial ?? ($__sige_perfil_escola->contacto ?? '');
$__sige_email_escola = $__sige_perfil_escola->email_institucional ?? '';
$__sige_endereco_escola = $__sige_perfil_escola->endereco_escola ?? '';
$__sige_logo_escola = '';
if ($__sige_perfil_escola) {
 $__sige_logo_escola = $__sige_perfil_escola->logo_documentos_url ?? ($__sige_perfil_escola->logo_sistema_url ?? ($__sige_perfil_escola->logotipo ?? ''));
}

// FIX: detectar colunas do valor completo (transporte, extras, multa_cobrada, desc. especial)
$col_transporte = sige_first_existing_col($lcols, ['valor_transporte']);
$col_extras = sige_first_existing_col($lcols, ['valor_extras']);
$col_multa_cobrada = sige_first_existing_col($lcols, ['valor_multa_cobrada']);
$col_desc_especial = sige_first_existing_col($lcols, ['valor_desconto_especial']);

// [FIX FORMULA-09] Expressão completa do valor do lançamento
// Usa NULLIF para multa_cobrada com fallback para valor_multa (canónica)
// ANTES: COALESCE(multa_cobrada, 0) ignorava multas pendentes
$valor_expr = "COALESCE(l.{$col_valor},0)";
if ($col_transporte) $valor_expr .= " + COALESCE(l.{$col_transporte},0)";
if ($col_extras) $valor_expr .= " + COALESCE(l.{$col_extras},0)";
if ($col_multa_cobrada && $col_multa) {
 $valor_expr .= " + COALESCE(NULLIF(l.{$col_multa_cobrada},0), l.{$col_multa}, 0)";
} elseif ($col_multa_cobrada) {
 $valor_expr .= " + COALESCE(l.{$col_multa_cobrada},0)";
} elseif ($col_multa) {
 $valor_expr .= " + COALESCE(l.{$col_multa},0)";
}
if ($col_desconto) $valor_expr .= " - COALESCE(l.{$col_desconto},0)";
if ($col_desc_especial) $valor_expr .= " - COALESCE(l.{$col_desc_especial},0)";

// Pagamentos: detectar FK e valor
$pay_join_sql = '';
$pay_sum_alias = 'pago_calc';
$pay_fk_col = null;
$pay_val_col = null;
if (sige_table_exists($tP)) {
 $pcols = sige_get_table_cols($tP);
 $pay_fk_col = sige_first_existing_col($pcols, ['lancamento_id','id_lancamento']);
 $pay_val_col = sige_first_existing_col($pcols, ['valor','valor_pago','total','montante','valor_recebido']);
 if ($pay_fk_col && $pay_val_col) {
 $pay_join_sql = "LEFT JOIN (
 SELECT {$pay_fk_col} AS lanc_id, SUM(COALESCE({$pay_val_col},0)) AS {$pay_sum_alias}
 FROM {$tP}
 GROUP BY {$pay_fk_col}
 ) pay ON pay.lanc_id = l.{$col_id}";
 }
}

// Alunos: join opcional (para pesquisa e exibição)
$use_alunos = false;
$acol_nome = $acol_proc = null;
$join_aluno_sql = '';
if ($col_aluno_id && sige_table_exists($tA)) {
 $acols = sige_get_table_cols($tA);
 $acol_nome = sige_first_existing_col($acols, ['nome_completo','nome','aluno','nome_aluno']);
 $acol_proc = sige_first_existing_col($acols, ['numero_processo','processo','nr_processo','codigo','matricula']);
 if ($acol_nome) {
 $use_alunos = true;
 $join_aluno_sql = "LEFT JOIN {$tA} a ON a.id = l.{$col_aluno_id}";
 }
}

// Serviços: lista para filtro e nome
$servicos = [];
$scol_id = $scol_nome = null;
$join_servico_sql = '';
if (sige_table_exists($tS)) {
 $scols = sige_get_table_cols($tS);
 $scol_id = sige_first_existing_col($scols, ['id','servico_id']);
 $scol_nome = sige_first_existing_col($scols, ['nome','nome_servico','titulo','designacao']);
 if ($scol_id && $scol_nome) {
 $servicos = $wpdb->get_results("SELECT {$scol_id} AS id, {$scol_nome} AS nome FROM {$tS} ORDER BY {$scol_nome} ASC");
 if ($col_servico_id) {
 $join_servico_sql = "LEFT JOIN {$tS} s ON s.{$scol_id} = l.{$col_servico_id}";
 }
 }
}

// =============================
// Actions (Cancelar / Reativar / Isentar)
// =============================
$msg = '';
$err = ''; $mfa_required = false;

function sige_lancamento_has_pagamento($lanc_id, $tP, $pay_fk_col, $pay_val_col) {
 global $wpdb;
 if (!$tP || !$pay_fk_col || !$pay_val_col) return false;
 // [v12.12.7] Defesa multi-tenant fail-closed: sem escola, não assume tenant 1.
 $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
 if ($eid <= 0) return false;
 $sum = $wpdb->get_var($wpdb->prepare("SELECT SUM(COALESCE({$pay_val_col},0)) FROM {$tP} WHERE {$pay_fk_col}=%d AND escola_id=%d", $lanc_id, $eid));
 return ((float)$sum) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── [v15.2.0 - F1] Handlers consolidados via SIGE_FinanceActionService ──
    // Antes: 3 blocos de ~40 linhas cada (118 linhas total) com permissões,
    // validações, transições de status e audit repetidos com pequenas
    // divergências. Agora: adapters finos que validam nonce + delegam.
    // Benefício: uma matriz de permissões, uma FSM, um audit trail.

    $acao_post = sige_fin_post_param('acao');

    // Cancelar lançamento
    if ($acao_post === 'cancelar_lancamento') {
        if (!isset($_POST['sige_cancelar_nonce']) || !wp_verify_nonce($_POST['sige_cancelar_nonce'], 'sige_cancelar_lancamento')) {
            $err = 'Nonce inválido. Recarregue a página e tente novamente.';
        } else {
            try {
                $res = SIGE_FinanceActionService::cancelLancamento(
                    sige_fin_post_int('lancamento_id'),
                    sige_fin_post_param('motivo'),
                    $escola_id
                );
            } catch (Throwable $e) {
                $res = ['ok' => false, 'error' => 'Falha técnica ao cancelar lançamento: ' . $e->getMessage()];
                if (function_exists('sige_audit_log')) sige_audit_log('cancelar_lancamento_falha_tecnica', ['erro' => $e->getMessage()], 'financeiro');
            }
            if ($res['ok']) {
                $msg = "Lançamento #{$res['lancamento_id']} cancelado com sucesso.";
            } else {
                $err = $res['error'];
                if (!empty($res['mfa_required'])) $mfa_required = true;
            }
        }
    }

    // Isentar lançamento
    elseif ($acao_post === 'isentar_lancamento') {
        if (!isset($_POST['sige_isentar_nonce']) || !wp_verify_nonce($_POST['sige_isentar_nonce'], 'sige_isentar_lancamento')) {
            $err = 'Nonce inválido.';
        } else {
            try {
                $res = SIGE_FinanceActionService::isentarLancamento(
                    sige_fin_post_int('lancamento_id'),
                    sige_fin_post_param('motivo_isencao'),
                    $escola_id
                );
            } catch (Throwable $e) {
                $res = ['ok' => false, 'error' => 'Falha técnica ao isentar lançamento: ' . $e->getMessage()];
                if (function_exists('sige_audit_log')) sige_audit_log('isentar_lancamento_falha_tecnica', ['erro' => $e->getMessage()], 'financeiro');
            }
            if ($res['ok']) {
                $msg = "Lançamento #{$res['lancamento_id']} marcado como isento.";
            } else {
                $err = $res['error'];
                if (!empty($res['mfa_required'])) $mfa_required = true;
            }
        }
    }

    // Reactivar lançamento
    elseif ($acao_post === 'reativar_lancamento') {
        if (!isset($_POST['sige_reativar_nonce']) || !wp_verify_nonce($_POST['sige_reativar_nonce'], 'sige_reativar_lancamento')) {
            $err = 'Nonce inválido. Recarregue a página e tente novamente.';
        } else {
            try {
                $res = SIGE_FinanceActionService::reactivarLancamento(
                    sige_fin_post_int('lancamento_id'),
                    $escola_id
                );
            } catch (Throwable $e) {
                $res = ['ok' => false, 'error' => 'Falha técnica ao reactivar lançamento: ' . $e->getMessage()];
                if (function_exists('sige_audit_log')) sige_audit_log('reativar_lancamento_falha_tecnica', ['erro' => $e->getMessage()], 'financeiro');
            }
            if ($res['ok']) {
                $msg = "Lançamento #{$res['lancamento_id']} reactivado com sucesso.";
            } else {
                $err = $res['error'];
                if (!empty($res['mfa_required'])) $mfa_required = true;
            }
        }
    }
}

// =============================
// Filters + Pagination
// =============================
$view = sige_fin_get_param('view', 'financeiro-lancamentos');
$q = sige_fin_get_param('q');
$mes = sige_fin_get_param('mes');
$servico_id = sige_fin_get_int('servico_id');
$estado = sige_fin_get_param('estado');
// [v13.4.0 BLOCO 3] Filtro universal por centro
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;
$per_page = max(10, min(100, sige_fin_get_int('pp', 25)));
$page_num = max(1, sige_fin_get_int('p', 1));
$offset = ($page_num - 1) * $per_page;

$where = ["l.escola_id = %d"]; // FIX: escola_id sempre presente
$params = [$escola_id];

if ($mes !== '' && $col_mes) {
 $where[] = "l.{$col_mes} = %s";
 $params[] = $mes;
}

if ($servico_id && $col_servico_id) {
 $where[] = "l.{$col_servico_id} = %d";
 $params[] = $servico_id;
}

if ($estado !== '' && $col_status) {
 if ($estado === 'ativo') {
 $where[] = "LOWER(l.{$col_status}) IN ('pendente','parcial','em_plano')";
 } else {
 $where[] = "LOWER(l.{$col_status}) = %s";
 $params[] = strtolower($estado);
 }
}

if ($centro_id_filtro > 0) {
 $where[] = "l.centro_id = %d";
 $params[] = $centro_id_filtro;
}

if ($q !== '' && $use_alunos) {
 $or = [];
 $or[] = "a.{$acol_nome} LIKE %s";
 $params[] = '%' . $wpdb->esc_like($q) . '%';
 if ($acol_proc) {
 $or[] = "a.{$acol_proc} LIKE %s";
 $params[] = '%' . $wpdb->esc_like($q) . '%';
 }
 $where[] = '(' . implode(' OR ', $or) . ')';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// =============================
// KPIs (baseados nos filtros)
// =============================
$kpi_ativos = 0;
$kpi_cancelados = 0;
$kpi_aberto = 0.0;
$kpi_vencidos = 0.0;

try {
 $today = current_time('Y-m-d');
 $baseJoin = "{$join_aluno_sql} {$pay_join_sql}";
 $pago_expr = $col_pago ? "COALESCE(l.{$col_pago},0)" : "COALESCE(pay.{$pay_sum_alias},0)";
 // [v13.4.0 BLOCO 3] KPIs respeitam filtro de centro para coerência com a tabela
 $_centro_sql_kpi  = $centro_id_filtro > 0 ? ' AND centro_id = ' . (int)$centro_id_filtro : '';
 $_centro_sql_kpi_l = $centro_id_filtro > 0 ? ' AND l.centro_id = ' . (int)$centro_id_filtro : '';
 if ($col_status) {
 $kpi_ativos = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tL} WHERE escola_id = %d AND LOWER({$col_status}) IN ('pendente','parcial','em_plano'){$_centro_sql_kpi}", $escola_id));
 $kpi_cancelados = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tL} WHERE escola_id = %d AND LOWER({$col_status}) = 'cancelado'{$_centro_sql_kpi}", $escola_id));
 }
 // FIX: kpi_aberto usa $valor_expr completo (transporte+extras+multa-descontos) + escola_id
 $kpi_aberto = (float)$wpdb->get_var($wpdb->prepare(
 "SELECT COALESCE(SUM(GREATEST(({$valor_expr}) - {$pago_expr}, 0)), 0)
 FROM {$tL} l {$baseJoin}
 WHERE l.escola_id = %d" . ($col_status ? " AND LOWER(l.{$col_status}) IN ('pendente','parcial','em_plano')" : "") . $_centro_sql_kpi_l,
 $escola_id
 ));
 if ($col_venc) {
 // FIX: kpi_vencidos usa $valor_expr completo + escola_id
 $kpi_vencidos = (float)$wpdb->get_var($wpdb->prepare(
 "SELECT COALESCE(SUM(GREATEST(({$valor_expr}) - {$pago_expr}, 0)), 0)
 FROM {$tL} l {$baseJoin}
 WHERE l.escola_id = %d" . ($col_status ? " AND LOWER(l.{$col_status}) IN ('pendente','parcial','em_plano')" : "") . $_centro_sql_kpi_l . " AND l.{$col_venc} < %s",
 $escola_id, $today
 ));
 }
} catch (Throwable $e) {
 // silencioso para evitar erro crítico
}

// =============================
// Total rows (for pagination)
// =============================
$total_rows = 0;
try {
 $sqlCount = "SELECT COUNT(*) FROM {$tL} l {$join_aluno_sql} {$where_sql}";
 $total_rows = empty($params) ? (int)$wpdb->get_var($sqlCount) : (int)$wpdb->get_var($wpdb->prepare($sqlCount, $params));
} catch (Throwable $e) {}
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// =============================
// Fetch rows (paged)
// =============================
$select_fields = ["l.*"];
if ($use_alunos) {
 $select_fields[] = "a.{$acol_nome} AS aluno_nome";
 if ($acol_proc) $select_fields[] = "a.{$acol_proc} AS aluno_processo";
}
if ($pay_join_sql) {
 $select_fields[] = "COALESCE(pay.{$pay_sum_alias},0) AS pago_calc";
}
if ($join_servico_sql && $scol_nome) {
 $select_fields[] = "s.{$scol_nome} AS servico_nome";
}

$order_by = $col_criado_em ? "l.{$col_criado_em} DESC" : "l.{$col_id} DESC";
$sqlList = "SELECT " . implode(", ", $select_fields) . "
 FROM {$tL} l
 {$join_aluno_sql}
 {$join_servico_sql}
 {$pay_join_sql}
 {$where_sql}
 ORDER BY {$order_by}
 LIMIT %d OFFSET %d";

$list_params = array_merge($params, [$per_page, $offset]);
$rows = $wpdb->get_results($wpdb->prepare($sqlList, $list_params));

// [FIX M-02] Recalcular multas em tempo real ao exibir lançamentos
if (function_exists('sige_fin_recalcular_lancamento') && !empty($rows)) {
 foreach ($rows as $_rl) {
 $__id = (int)($_rl->{$col_id} ?? 0);
 $__st = strtolower((string)($_rl->{$col_status} ?? ''));
 if ($__id > 0 && in_array($__st, ['pendente','parcial','em_plano'], true)) {
 sige_fin_recalcular_e_sync($__id);
 }
 }
 // Re-fetch com valores actualizados
 $rows = $wpdb->get_results($wpdb->prepare($sqlList, $list_params));
}


// =============================
// [v12.9.78] Dados para exportação Excel dos lançamentos
// =============================
$export_rows_lancamentos = [];
$export_limit_lancamentos = 5000;
try {
 $export_select_fields = $select_fields;
 $sqlExport = "SELECT " . implode(", ", $export_select_fields) . "
 FROM {$tL} l
 {$join_aluno_sql}
 {$join_servico_sql}
 {$pay_join_sql}
 {$where_sql}
 ORDER BY {$order_by}
 LIMIT %d";
 $export_params = array_merge($params, [$export_limit_lancamentos]);
 $export_rows_lancamentos = $wpdb->get_results($wpdb->prepare($sqlExport, $export_params));
} catch (Throwable $e) {
 $export_rows_lancamentos = [];
}

$export_lancamentos_payload = [];
foreach ((array)$export_rows_lancamentos as $r) {
 $id = (int)($r->{$col_id} ?? 0);
 $aluno_nome = $use_alunos ? (string)($r->aluno_nome ?? '') : '';
 $aluno_proc = ($use_alunos && isset($r->aluno_processo)) ? (string)($r->aluno_processo ?? '') : '';
 $servico_nome = isset($r->servico_nome) && (string)$r->servico_nome !== '' ? (string)$r->servico_nome : ($col_servico_id ? ('Serviço #' . (int)($r->{$col_servico_id} ?? 0)) : '-');
 $valor = (float)($r->{$col_valor} ?? 0)
 + ($col_transporte ? (float)($r->{$col_transporte} ?? 0) : 0)
 + ($col_extras ? (float)($r->{$col_extras} ?? 0) : 0)
 + ($col_multa_cobrada ? (float)($r->{$col_multa_cobrada} ?? 0) : 0)
 - ($col_desconto ? (float)($r->{$col_desconto} ?? 0) : 0)
 - ($col_desc_especial ? (float)($r->{$col_desc_especial} ?? 0) : 0);
 $pago = 0.0;
 if ($col_pago) $pago = (float)($r->{$col_pago} ?? 0);
 elseif (isset($r->pago_calc)) $pago = (float)$r->pago_calc;
 $status = $col_status ? (string)($r->{$col_status} ?? 'pendente') : 'pendente';
 $export_lancamentos_payload[] = [
 'id' => $id,
 'aluno' => $aluno_nome !== '' ? $aluno_nome : ($col_aluno_id ? ('Aluno #' . (int)($r->{$col_aluno_id} ?? 0)) : '-'),
 'processo' => $aluno_proc,
 'servico' => $servico_nome,
 'mes' => $col_mes ? (string)($r->{$col_mes} ?? '') : '',
 'vencimento' => $col_venc ? (string)($r->{$col_venc} ?? '') : '',
 'valor' => $valor,
 'pago' => $pago,
 'saldo' => max($valor - $pago, 0),
 'estado' => $status,
 'descricao' => $col_desc ? (string)($r->{$col_desc} ?? '') : '',
 'criado_em' => $col_criado_em ? (string)($r->{$col_criado_em} ?? '') : '',
 ];
}

$export_lancamentos_meta = [
 'escola' => $__sige_nome_escola,
 'contacto' => $__sige_contacto_escola,
 'email' => $__sige_email_escola,
 'endereco' => $__sige_endereco_escola,
 'logo' => $__sige_logo_escola,
 'moeda' => function_exists('sige_moeda') ? sige_moeda() : 'MZN',
 'q' => $q,
 'mes' => $mes,
 'servico' => $servico_id ? ('#' . $servico_id) : 'Todos',
 'estado' => $estado !== '' ? $estado : 'Todos',
 'total_filtrado' => (int)$total_rows,
 'total_exportado' => count($export_lancamentos_payload),
 'limite' => $export_limit_lancamentos,
 'exportado_em' => $sg_mz_now->format('d/m/Y H:i') . ' (Moçambique)',
];

// =============================
// UI helpers
// =============================
function sige_badge_estado($status) {
 $s = strtolower((string)$status);
 if ($s === 'pago') return '<span class="sg-badge sg-badge-success">● Pago</span>';
 if ($s === 'cancelado') return '<span class="sg-badge sg-badge-error">● Cancelado</span>';
 if ($s === 'isento') return '<span class="sg-badge" style="background:var(--sg-purple-100);color:var(--sg-purple-700);">● Isento</span>';
 if ($s === 'parcial') return '<span class="sg-badge sg-badge-warning">● Parcial</span>';
 return '<span class="sg-badge sg-badge-warning">● Pendente</span>';
}

function sige_qs(array $extra = []) {
 $base = $_GET;
 foreach ($extra as $k=>$v) {
 if ($v === null) unset($base[$k]);
 else $base[$k] = $v;
 }
 return '?' . http_build_query($base);
}
?>
<?php echo function_exists('sige_cdn_script') ? sige_cdn_script('exceljs') : ''; ?>
<?php echo function_exists('sige_cdn_script') ? sige_cdn_script('filesaver') : ''; ?>

<!-- ══════════════════════════════════════════════════════════════════════════════
 UI - Design System v1.0
 ══════════════════════════════════════════════════════════════════════════════ -->

<div class="sg-finpro-wrap sg-lanc-wrap">

 <section class="sg-finpro-hero sg-lanc-hero" aria-label="Lançamentos financeiros">
 <div class="sg-finpro-hero-copy">
 <div class="sg-finpro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Financeiro</div>
 <h1>Lançamentos Financeiros</h1>
 <p>Consulte valores lançados, acompanhe saldos em aberto, filtre por aluno ou serviço e trate cancelamentos, isenções ou reactivações com uma experiência mais clara e segura.</p>
 <div class="sg-finpro-hero-actions">
 <a class="sg-finpro-btn sg-finpro-btn-light" href="?page=sige-app&view=financeiro-extratos"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Extractos e Caixa</a>
 <button type="button" class="sg-finpro-btn sg-finpro-btn-light" data-sige-act="sigeExportarLancamentosExcel"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Exportar Excel</button>
 <a class="sg-finpro-btn sg-finpro-btn-primary" href="?page=sige-app&view=financeiro-gerador"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('rocket') : ''; ?> Gerar Lançamentos</a>
 </div>
 </div>
 <div class="sg-finpro-hero-panel sg-lanc-hero-panel">
 <div class="sg-finpro-mini-label">Valor em aberto</div>
 <strong><?php echo esc_html(number_format((float)$kpi_aberto, 2, ',', '.')); ?> <?php echo esc_html(sige_moeda()); ?></strong>
 <span><?php echo (int)$kpi_ativos; ?> lançamentos activos nos filtros actuais</span>
 <div class="sg-finpro-progress"><i style="width:<?php echo esc_attr(min(100, $kpi_ativos > 0 ? 72 : 0)); ?>%"></i></div>
 <small>Actualizado em <?php echo esc_html($sg_mz_now->format('d/m/Y H:i')); ?> · Hora de Moçambique</small>
 </div>
 </section>

 <!-- Alerts -->
 <?php if ($msg): ?>
 <div class="sg-alert sg-alert-success sg-mb-4">
 <span class="sg-alert-icon"></span>
 <div class="sg-alert-content"><strong>Sucesso</strong> - <?php echo sige_h($msg); ?></div>
</div>
 <?php endif; ?>

 <?php if ($err): ?>
 <div class="sg-alert sg-alert-error sg-mb-4">
 <span class="sg-alert-icon"></span>
 <div class="sg-alert-content"><strong>Atenção</strong> - <?php echo sige_h($err); ?></div>
</div>
 <?php endif; ?>

 <?php if ($mfa_required && function_exists('sige_mfa_render_challenge_form')) echo sige_mfa_render_challenge_form(); ?>

 <!-- KPIs -->
 <div class="sg-finpro-kpi-grid sg-lanc-kpi-grid">
 <article class="sg-finpro-kpi sg-finpro-tone-green">
 <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></div>
 <div><span>Lançamentos activos</span><strong><?php echo (int)$kpi_ativos; ?></strong><small>Pendentes e parciais</small></div>
</article>
 <article class="sg-finpro-kpi sg-finpro-tone-amber">
 <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?></div>
 <div><span>Cancelados</span><strong><?php echo (int)$kpi_cancelados; ?></strong><small>Registos preservados</small></div>
</article>
 <article class="sg-finpro-kpi sg-finpro-tone-blue">
 <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('wallet') : ''; ?></div>
 <div><span>Valor em aberto</span><strong><?php echo esc_html(number_format((float)$kpi_aberto, 2, ',', '.')); ?></strong><small><?php echo esc_html(sige_moeda()); ?></small></div>
</article>
 <article class="sg-finpro-kpi sg-finpro-tone-coral">
 <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></div>
 <div><span>Vencidos</span><strong><?php echo esc_html(number_format((float)$kpi_vencidos, 2, ',', '.')); ?></strong><small><?php echo esc_html(sige_moeda()); ?></small></div>
</article>
</div>

 <!-- Filtros + Tabela -->
 <section class="sg-finpro-card sg-lanc-card">
 <div class="sg-finpro-card-head">
 <div>
 <div class="sg-finpro-section-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?></div>
 <div><h2>Pesquisa e filtros</h2><p>Encontre lançamentos por aluno, mês, serviço, estado ou centro.</p></div>
 </div>
 </div>

 <!-- Filtros -->
 <form method="get" class="sg-lanc-filterbar">
 <input type="hidden" name="page" value="sige-app">
 <input type="hidden" name="view" value="financeiro-lancamentos">
 <div class="sg-filters-row">
 <div class="sg-form-group sg-form-group-inline">
 <label class="sg-label">Aluno (nome ou processo)</label>
 <input class="sg-input" type="text" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Ex.: Junior / 20263074">
</div>
 <div class="sg-form-group sg-form-group-inline">
 <label class="sg-label">Mês (YYYY-MM)</label>
 <input class="sg-input" type="text" name="mes" value="<?php echo esc_attr($mes); ?>" placeholder="2026-09">
</div>
 <div class="sg-form-group sg-form-group-inline">
 <label class="sg-label">Serviço</label>
 <select class="sg-select" name="servico_id">
 <option value="">Todos</option>
 <?php foreach ($servicos as $s): ?>
 <option value="<?php echo (int)$s->id; ?>" <?php selected($servico_id, (int)$s->id); ?>>
 <?php echo esc_html($s->nome); ?>
</option>
 <?php endforeach; ?>
</select>
</div>
 <div class="sg-form-group sg-form-group-inline">
 <label class="sg-label">Estado</label>
 <select class="sg-select" name="estado">
 <option value="">Todos</option>
 <option value="ativo" <?php selected($estado, 'ativo'); ?>>Activo (Pendente + Parcial)</option>
 <option value="pendente" <?php selected($estado, 'pendente'); ?>>Pendente</option>
 <option value="parcial" <?php selected($estado, 'parcial'); ?>>Parcial</option>
 <option value="pago" <?php selected($estado, 'pago'); ?>>Pago</option>
 <option value="cancelado" <?php selected($estado, 'cancelado'); ?>>Cancelado</option>
 <option value="isento" <?php selected($estado, 'isento'); ?>>Isento</option>
</select>
</div>
 <?php
   // [v13.4.0 BLOCO 3] Filtro de centro (silencioso em tenants single-center)
   if (function_exists('sige_fin_render_filtro_centro')) {
     $__sel = sige_fin_render_filtro_centro([
       'selected'      => $centro_id_filtro,
       'include_label' => false,
       'class'         => 'sg-select',
     ]);
     if ($__sel !== '') {
       echo '<div class="sg-form-group sg-form-group-inline"><label class="sg-label">Centro</label>' . $__sel . '</div>';
     }
   }
 ?>
 <div class="sg-form-group sg-form-group-inline">
 <label class="sg-label">Por página</label>
 <select class="sg-select" name="pp">
 <?php foreach ([10,25,50,100] as $n): ?>
 <option value="<?php echo esc_attr($n); ?>" <?php selected($per_page, $n); ?>><?php echo esc_attr($n); ?></option>
 <?php endforeach; ?>
</select>
</div>
 <div class="sg-filters-actions">
 <button class="sg-finpro-btn sg-finpro-btn-primary" type="submit"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?> Filtrar</button>
 <button class="sg-finpro-btn sg-finpro-btn-light" type="button" data-sige-act="sigeExportarLancamentosExcel">Exportar Excel</button>
 <a class="sg-finpro-btn sg-finpro-btn-light" href="?page=sige-app&view=financeiro-lancamentos">Limpar</a>
</div>
</div>
</form>

 <!-- Tabela -->
 <div class="sg-table-wrapper sg-lanc-table-wrap">
 <table class="sg-table sg-lanc-table">
 <thead>
 <tr>
 <th>#</th>
 <th>Aluno</th>
 <th>Serviço</th>
 <th>Mês</th>
 <th>Venc.</th>
 <th class="sg-text-right">Valor</th>
 <th class="sg-text-right">Pago</th>
 <th>Estado</th>
 <th>Acções</th>
</tr>
</thead>
 <tbody>
 <?php if (empty($rows)): ?>
 <tr>
 <td colspan="9">
 <div class="sg-empty-state sg-py-6">
 <p class="sg-empty-title">Nenhum lançamento encontrado</p>
 <p class="sg-empty-desc">Tente ajustar os filtros de pesquisa.</p>
</div>
</td>
</tr>
 <?php else: ?>
 <?php foreach ($rows as $r): ?>
 <?php
 $id = (int)($r->{$col_id} ?? 0);
 $aluno_nome = $use_alunos ? ($r->aluno_nome ?? '') : '';
 $aluno_proc = ($use_alunos && isset($r->aluno_processo)) ? ($r->aluno_processo ?? '') : '';
 // [XSS-06] esc_html no nome antes de construir label com HTML
 $aluno_label = $aluno_nome ? esc_html($aluno_nome) : ('Aluno #' . (int)($col_aluno_id ? ($r->{$col_aluno_id} ?? 0) : 0));
 if ($aluno_proc) $aluno_label .= ' <span class="sg-badge sg-badge-muted sg-ml-1">Proc: ' . esc_html($aluno_proc) . '</span>';
 $mes_ref = $col_mes ? ($r->{$col_mes} ?? '') : '';
 $venc = $col_venc ? ($r->{$col_venc} ?? '') : '';
 // FIX: valor completo = original + transporte + extras + multa_cobrada - descontos
 $valor = (float)($r->{$col_valor} ?? 0)
 + ($col_transporte ? (float)($r->{$col_transporte} ?? 0) : 0)
 + ($col_extras ? (float)($r->{$col_extras} ?? 0) : 0)
 + ($col_multa_cobrada ? (float)($r->{$col_multa_cobrada} ?? 0) : 0)
 - ($col_desconto ? (float)($r->{$col_desconto} ?? 0) : 0)
 - ($col_desc_especial ? (float)($r->{$col_desc_especial} ?? 0) : 0);
 $pago = 0.0;
 if ($col_pago) $pago = (float)($r->{$col_pago} ?? 0);
 elseif (isset($r->pago_calc)) $pago = (float)$r->pago_calc;
 $status = $col_status ? ($r->{$col_status} ?? 'pendente') : 'pendente';
 $status_l = strtolower((string)$status);
 $can_cancel = ($status_l !== 'cancelado' && $status_l !== 'isento' && $status_l !== 'pago' && $pago <= 0.00001);
 $can_reactivate = (($status_l === 'cancelado' || $status_l === 'isento') && $pago <= 0.00001);
 $can_exempt = ($status_l !== 'cancelado' && $status_l !== 'isento' && $status_l !== 'pago' && $pago <= 0.00001);

 // Detalhes (mostrar só campos relevantes existentes)
 $det = [
 'N.º do lançamento' => $id,
 'Aluno' => wp_strip_all_tags($aluno_label),
 ];
 if ($col_aluno_id) $det['Código do aluno'] = (int)($r->{$col_aluno_id} ?? 0);
 if ($col_servico_id) $det['Código do serviço'] = (int)($r->{$col_servico_id} ?? 0);
 if ($mes_ref !== '') $det['Mês de referência'] = $mes_ref;
 if ($venc !== '') $det['Vencimento'] = $venc;
 $det['Valor'] = number_format($valor, 2, ',', '.') . ' ' . sige_moeda();
 $det['Pago'] = number_format($pago, 2, ',', '.') . ' ' . sige_moeda();
 $det['Saldo'] = number_format(max($valor - $pago, 0), 2, ',', '.') . ' ' . sige_moeda();
 if ($col_multa) $det['Multa'] = number_format((float)($r->{$col_multa} ?? 0), 2, ',', '.') . ' ' . sige_moeda();
 // [v15.2.0 - F7] Se o lançamento está pago e tem valor_multa_cobrada > 0,
 // mostrar explicitamente que é valor imutável (auditoria). Antes era
 // invisível: ambos valor_multa e valor_multa_cobrada apareciam juntos sem
 // indicação de qual é o "real" - a fórmula canónica usa o cobrada quando
 // pago, mas o utilizador não tinha forma de saber.
 if (strtolower((string)$status) === 'pago' && !empty($r->valor_multa_cobrada) && (float)$r->valor_multa_cobrada > 0) {
 $det['Multa cobrada no pagamento'] = number_format((float)$r->valor_multa_cobrada, 2, ',', '.') . ' ' . sige_moeda() . ' - valor preservado no histórico do pagamento';
 }
 if ($col_desconto) $det['Desconto'] = number_format((float)($r->{$col_desconto} ?? 0), 2, ',', '.') . ' ' . sige_moeda();
 if ($col_desc && !empty($r->{$col_desc})) $det['Observação'] = (string)$r->{$col_desc};
 if ($col_criado_em && !empty($r->{$col_criado_em})) $det['Criado em'] = (string)$r->{$col_criado_em};
 if ($col_cancel_em && !empty($r->{$col_cancel_em})) $det['Cancelado em'] = (string)$r->{$col_cancel_em};
 if ($col_cancel_por && !empty($r->{$col_cancel_por})) $det['Responsável pelo cancelamento'] = (string)$r->{$col_cancel_por};
 if ($col_cancel_mot && !empty($r->{$col_cancel_mot})) $det['Motivo do cancelamento'] = (string)$r->{$col_cancel_mot};
 $det['Estado'] = (string)$status;
 $det_json = esc_attr(wp_json_encode($det, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
 ?>
 <tr>
 <td class="sg-font-mono sg-text-sm">#<?php echo esc_attr($id); ?></td>
 <td><?php echo $aluno_label; ?></td>
 <td class="sg-text-muted"><?php echo esc_html(isset($r->servico_nome) && (string)$r->servico_nome !== '' ? (string)$r->servico_nome : ($col_servico_id ? ('Serviço ' . (int)($r->{$col_servico_id} ?? 0)) : '-')); ?></td>
 <td class="sg-font-mono sg-text-sm"><?php echo esc_html($mes_ref); ?></td>
 <td class="sg-font-mono sg-text-sm"><?php echo esc_html($venc); ?></td>
 <td class="sg-text-right sg-font-mono"><?php echo number_format($valor, 2, ',', '.'); ?></td>
 <td class="sg-text-right sg-font-mono"><?php echo number_format($pago, 2, ',', '.'); ?></td>
 <td><?php echo sige_badge_estado($status); ?></td>
 <td>
 <div class="sg-btn-group">
 <button type="button" class="sg-lanc-action sg-lanc-action-light" data-sige-lanc-action="details" data-details="<?php echo $det_json; ?>" data-sige-act="sigeOpenDetails">Ver</button>
 <?php if ($can_cancel): ?>
 <button type="button" class="sg-lanc-action sg-lanc-action-danger" data-sige-lanc-action="cancel" data-id="<?php echo esc_attr($id); ?>" data-sige-act="sigeOpenCancel" data-sige-arg="<?php echo esc_attr($id); ?>">Cancelar</button>
 <?php endif; ?>
 <?php if ($can_exempt): ?>
 <button type="button" class="sg-lanc-action sg-lanc-action-primary" data-sige-lanc-action="exempt" data-id="<?php echo esc_attr($id); ?>" data-sige-act="sigeOpenExempt" data-sige-arg="<?php echo esc_attr($id); ?>">Isentar</button>
 <?php endif; ?>
 <?php if ($can_reactivate): ?>
 <button type="button" class="sg-lanc-action sg-lanc-action-success" data-sige-lanc-action="reactivate" data-id="<?php echo esc_attr($id); ?>" data-sige-act="sigeOpenReactivate" data-sige-arg="<?php echo esc_attr($id); ?>">Reativar</button>
 <?php endif; ?>
</div>
</td>
</tr>
 <?php endforeach; ?>
 <?php endif; ?>
</tbody>
</table>
</div>

 <!-- Paginação -->
 <div class="sg-pagination sg-mt-4">
 <div class="sg-text-muted sg-text-sm">
 Total: <strong><?php echo (int)$total_rows; ?></strong> • Página <strong><?php echo (int)$page_num; ?></strong> de <strong><?php echo (int)$total_pages; ?></strong>
</div>
 <div class="sg-pagination-links">
 <?php
 $mk = function($label, $p, $active=false) {
 $cls = $active ? 'sg-pagination-link sg-pagination-link-active' : 'sg-pagination-link';
 echo '<a class="'.$cls.'" href="'.esc_url(sige_qs(['p'=>$p])).'">'.esc_html($label).'</a>';
 };
 $prev = max(1, $page_num - 1);
 $next = min($total_pages, $page_num + 1);
 if ($page_num > 1) $mk('« Anterior', $prev);
 $start = max(1, $page_num - 2);
 $end = min($total_pages, $page_num + 2);
 if ($start > 1) { $mk('1', 1, $page_num==1); if ($start > 2) echo '<span class="sg-text-muted sg-px-2">…</span>'; }
 for ($i=$start; $i<=$end; $i++) $mk((string)$i, $i, $i==$page_num);
 if ($end < $total_pages) { if ($end < $total_pages-1) echo '<span class="sg-text-muted sg-px-2">…</span>'; $mk((string)$total_pages, $total_pages, $page_num==$total_pages); }
 if ($page_num < $total_pages) $mk('Seguinte »', $next);
 ?>
</div>
</div>

</section>

</div>

<!-- ══════════════════════════════════════════════════════════════════════════════
 MODAIS
 ══════════════════════════════════════════════════════════════════════════════ -->

<!-- Modal: Cancelar -->
<div class="sg-modal-backdrop" id="sigeCancelModal" style="display:none;">
 <div class="sige-lanc-modal">
 <div class="sige-lanc-modal-header">
 <h3 class="sige-lanc-modal-title">Cancelar lançamento</h3>
</div>
 <div class="sige-lanc-modal-body">
 <p class="sg-text-muted sg-mb-3">Esta acção não apaga o registo. Apenas marca como cancelado.</p>
 <form method="post" id="sigeCancelForm">
 <?php wp_nonce_field('sige_cancelar_lancamento', 'sige_cancelar_nonce'); ?>
 <input type="hidden" name="acao" value="cancelar_lancamento">
 <input type="hidden" name="lancamento_id" id="sigeCancelId" value="0">
 <div class="sg-form-group">
 <label class="sg-label">Motivo do cancelamento (obrigatório)</label>
 <input class="sg-input" type="text" name="motivo" id="sigeCancelMotivo" required placeholder="Ex.: lançamento duplicado / erro no serviço">
</div>
</form>
</div>
 <div class="sige-lanc-modal-footer">
 <button type="button" class="sg-btn sg-btn-ghost" data-sige-act="sigeCloseCancel">Fechar</button>
 <button type="button" data-sige-lanc-submit-form="sigeCancelForm" class="sg-btn sg-btn-error">Confirmar Cancelamento</button>
</div>
</div>
</div>

<!-- Modal: Isentar -->
<div class="sg-modal-backdrop" id="sigeExemptModal" style="display:none;">
 <div class="sige-lanc-modal">
 <div class="sige-lanc-modal-header">
 <h3 class="sige-lanc-modal-title" style="color:var(--sg-purple-600);"> Isentar Lançamento</h3>
</div>
 <div class="sige-lanc-modal-body">
 <p class="sg-text-muted sg-mb-3">O lançamento será marcado como <strong>isento</strong> e não contará como dívida.</p>
 <form method="post" id="sigeExemptForm">
 <?php wp_nonce_field('sige_isentar_lancamento', 'sige_isentar_nonce'); ?>
 <input type="hidden" name="acao" value="isentar_lancamento">
 <input type="hidden" name="lancamento_id" id="sigeExemptId" value="0">
 <div class="sg-form-group sg-mb-3">
 <label class="sg-label">Motivo rápido</label>
 <select id="sigeExemptPreset" class="sg-select" onchange="if(this.value)document.getElementById('sigeExemptMotivo').value=this.value;">
 <option value="">- Seleccionar -</option>
 <option value="Aluno(a) ainda não tinha iniciado as actividades neste mês">Aluno ainda não iniciou</option>
 <option value="Aluno(a) ausente por motivo de doença">Ausência por doença</option>
 <option value="Transporte escolar suspenso neste período">Transporte suspenso</option>
 <option value="Aluno(a) transferido(a) / desistiu neste período">Transferência / Desistência</option>
</select>
</div>
 <div class="sg-form-group">
 <label class="sg-label">Motivo (obrigatório)</label>
 <input class="sg-input" type="text" name="motivo_isencao" id="sigeExemptMotivo" required placeholder="Descreva o motivo da isenção">
</div>
</form>
</div>
 <div class="sige-lanc-modal-footer">
 <button type="button" class="sg-btn sg-btn-ghost" data-sige-act="sigeCloseExempt">Fechar</button>
 <button type="button" data-sige-lanc-submit-form="sigeExemptForm" class="sg-btn" style="background:var(--sg-purple-600);color:white;">Confirmar Isenção</button>
</div>
</div>
</div>


<!-- Modal: Reativar -->
<div class="sg-modal-backdrop" id="sigeReactivateModal" style="display:none;">
 <div class="sige-lanc-modal">
 <div class="sige-lanc-modal-header">
 <h3 class="sige-lanc-modal-title">Reativar lançamento</h3>
</div>
 <div class="sige-lanc-modal-body">
 <p class="sg-text-muted sg-mb-3">Confirme apenas se este lançamento deve voltar a ficar disponível para acompanhamento financeiro.</p>
 <form method="post" id="sigeReactivateForm">
 <?php wp_nonce_field('sige_reativar_lancamento', 'sige_reativar_nonce'); ?>
 <input type="hidden" name="acao" value="reativar_lancamento">
 <input type="hidden" name="lancamento_id" id="sigeReactivateId" value="0">
 </form>
</div>
 <div class="sige-lanc-modal-footer">
 <button type="button" class="sg-btn sg-btn-ghost" data-sige-act="sigeCloseReactivate">Fechar</button>
 <button type="button" data-sige-lanc-submit-form="sigeReactivateForm" class="sg-btn sg-btn-primary">Confirmar Reactivação</button>
</div>
</div>
</div>

<!-- Modal: Detalhes -->
<div class="sg-modal-backdrop" id="sigeDetailsModal" style="display:none;">
 <div class="sige-lanc-modal sige-lanc-modal-lg">
 <div class="sige-lanc-modal-header">
 <h3 class="sige-lanc-modal-title"> Detalhes do lançamento</h3>
</div>
 <div class="sige-lanc-modal-body">
 <div id="sigeDetailsBody" class="sg-details-grid"></div>
</div>
 <div class="sige-lanc-modal-footer">
 <button type="button" class="sg-btn sg-btn-ghost" data-sige-act="sigeCloseDetails">Fechar</button>
</div>
</div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
// ── [v12.9.78] Exportação Excel profissional dos lançamentos ──
window.SIGE_LANCAMENTOS_EXPORT = {
 meta: <?php echo wp_json_encode($export_lancamentos_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
 rows: <?php echo wp_json_encode($export_lancamentos_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
};

function sigeLancamentosSafeFilePart(v) {
 return String(v || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'geral';
}
function sigeLancamentosMoney(v) {
 return Number(v || 0).toLocaleString('pt-MZ', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function sigeLancamentosDownloadCsv(meta, rows) {
 var headers = ['ID', 'Aluno', 'Processo', 'Serviço', 'Mês', 'Vencimento', 'Valor', 'Pago', 'Saldo', 'Estado', 'Descrição/Obs', 'Criado em'];
 var csv = [headers].concat(rows.map(function(r){
   return [r.id, r.aluno, r.processo, r.servico, r.mes, r.vencimento, r.valor, r.pago, r.saldo, r.estado, r.descricao, r.criado_em];
 })).map(function(line){
   return line.map(function(v){ return '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"'; }).join(';');
 }).join('\n');
 var blob = new Blob(['\ufeff' + csv], {type: 'text/csv;charset=utf-8;'});
 var a = document.createElement('a');
 a.href = URL.createObjectURL(blob);
 a.download = 'Lancamentos_' + sigeLancamentosSafeFilePart(meta.mes || meta.estado || 'geral') + '.csv';
 document.body.appendChild(a); a.click(); a.remove();
 setTimeout(function(){ URL.revokeObjectURL(a.href); }, 500);
}

window.sigeExportarLancamentosExcel = async function() {
 var payload = window.SIGE_LANCAMENTOS_EXPORT || {meta:{}, rows:[]};
 var meta = payload.meta || {};
 var rows = payload.rows || [];
 if (!rows.length) { sigeUi.toast('Não há lançamentos para exportar com os filtros actuais.', 'aviso'); return; }
 if (typeof ExcelJS === 'undefined') {
   sigeLancamentosDownloadCsv(meta, rows);
   return;
 }

 var wb = new ExcelJS.Workbook();
 wb.creator = 'SIGE SoftGenial';
 wb.created = new Date();
 wb.modified = new Date();
 var ws = wb.addWorksheet('Lançamentos', {
   views: [{ state: 'frozen', ySplit: 8 }],
   pageSetup: { paperSize: 9, orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 }
 });

 ws.mergeCells('A1:L1');
 ws.getCell('A1').value = meta.escola || 'SIGE SoftGenial';
 ws.getCell('A1').font = { bold:true, size:16, color:{argb:'FFFFFFFF'} };
 ws.getCell('A1').alignment = { horizontal:'center', vertical:'middle' };
 ws.getCell('A1').fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF0E4194'} };
 ws.getRow(1).height = 28;

 ws.mergeCells('A2:L2');
 ws.getCell('A2').value = [meta.endereco, meta.contacto ? 'Contacto: ' + meta.contacto : '', meta.email].filter(Boolean).join('  |  ');
 ws.getCell('A2').font = { size:10, color:{argb:'FF334155'} };
 ws.getCell('A2').alignment = { horizontal:'center' };

 ws.mergeCells('A4:L4');
 ws.getCell('A4').value = 'RELATÓRIO DE LANÇAMENTOS FINANCEIROS';
 ws.getCell('A4').font = { bold:true, size:14, color:{argb:'FF0E4194'} };
 ws.getCell('A4').alignment = { horizontal:'center' };

 ws.mergeCells('A5:L5');
 ws.getCell('A5').value = 'Filtros: Aluno/Processo: ' + (meta.q || 'Todos') + '  |  Mês: ' + (meta.mes || 'Todos') + '  |  Serviço: ' + (meta.servico || 'Todos') + '  |  Estado: ' + (meta.estado || 'Todos');
 ws.getCell('A5').font = { italic:true, size:10, color:{argb:'FF475569'} };
 ws.getCell('A5').alignment = { horizontal:'center' };

 ws.mergeCells('A6:L6');
 var limiteTxt = Number(meta.total_filtrado || 0) > Number(meta.total_exportado || 0) ? '  |  Nota: exportados ' + meta.total_exportado + ' de ' + meta.total_filtrado + ' registos filtrados.' : '';
 ws.getCell('A6').value = 'Exportado em: ' + (meta.exportado_em || new Date().toLocaleString('pt-MZ')) + limiteTxt;
 ws.getCell('A6').font = { size:9, color:{argb:'FF64748B'} };
 ws.getCell('A6').alignment = { horizontal:'center' };

 ws.addRow([]);
 var headers = ['ID', 'Aluno', 'Processo', 'Serviço', 'Mês', 'Vencimento', 'Valor (' + (meta.moeda || 'MZN') + ')', 'Pago (' + (meta.moeda || 'MZN') + ')', 'Saldo (' + (meta.moeda || 'MZN') + ')', 'Estado', 'Descrição/Obs', 'Criado em'];
 var headerRow = ws.addRow(headers);
 headerRow.height = 22;
 headerRow.eachCell(function(cell){
   cell.font = { bold:true, color:{argb:'FFFFFFFF'} };
   cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF1A237E'} };
   cell.alignment = { horizontal:'center', vertical:'middle', wrapText:true };
   cell.border = { top:{style:'thin',color:{argb:'FFCBD5E1'}}, left:{style:'thin',color:{argb:'FFCBD5E1'}}, bottom:{style:'thin',color:{argb:'FFCBD5E1'}}, right:{style:'thin',color:{argb:'FFCBD5E1'}} };
 });

 var totalValor = 0, totalPago = 0, totalSaldo = 0;
 var resumoServico = {}, resumoEstado = {};
 function addResumo(map, key, valor, pago, saldo) {
   key = key || 'Não identificado';
   if (!map[key]) map[key] = { qtd:0, valor:0, pago:0, saldo:0 };
   map[key].qtd += 1; map[key].valor += valor; map[key].pago += pago; map[key].saldo += saldo;
 }
 rows.forEach(function(r){
   var valor = Number(r.valor || 0), pago = Number(r.pago || 0), saldo = Number(r.saldo || 0);
   totalValor += valor; totalPago += pago; totalSaldo += saldo;
   addResumo(resumoServico, r.servico, valor, pago, saldo);
   addResumo(resumoEstado, r.estado, valor, pago, saldo);
   var rr = ws.addRow([r.id, r.aluno, r.processo, r.servico, r.mes, r.vencimento, valor, pago, saldo, r.estado, r.descricao, r.criado_em]);
   rr.eachCell(function(cell, col){
     cell.border = { bottom:{style:'thin',color:{argb:'FFE2E8F0'}} };
     cell.alignment = { vertical:'top', wrapText:true };
     if (col >= 7 && col <= 9) { cell.numFmt = '#,##0.00'; cell.alignment = { horizontal:'right' }; }
     if (col === 9 && saldo > 0) { cell.font = { bold:true, color:{argb:'FFB45309'} }; }
   });
 });
 var totalRow = ws.addRow(['', '', '', '', '', 'TOTAL', totalValor, totalPago, totalSaldo, '', '', '']);
 totalRow.eachCell(function(cell, col){
   cell.font = { bold:true, color:{argb:'FF0F172A'} };
   cell.fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FFEFF6FF'} };
   cell.border = { top:{style:'medium', color:{argb:'FF0E4194'}} };
   if (col >= 7 && col <= 9) { cell.numFmt = '#,##0.00'; cell.alignment = { horizontal:'right' }; }
 });

 function addResumoTitulo(titulo) {
   ws.addRow([]);
   var r = ws.addRow([titulo]);
   ws.mergeCells('A' + r.number + ':I' + r.number);
   r.getCell(1).font = { bold:true, size:12, color:{argb:'FFFFFFFF'} };
   r.getCell(1).alignment = { horizontal:'center' };
   r.getCell(1).fill = { type:'pattern', pattern:'solid', fgColor:{argb:'FF0E4194'} };
 }
 function addResumoTabela(map) {
   var h = ws.addRow(['Agrupamento', 'Registos', 'Valor', 'Pago', 'Saldo']);
   [1,2,3,4,5].forEach(function(i){
     var c = h.getCell(i); c.font = {bold:true, color:{argb:'FFFFFFFF'}}; c.fill = {type:'pattern', pattern:'solid', fgColor:{argb:'FF1A237E'}};
     c.alignment = { horizontal:i > 2 ? 'right' : 'left' };
   });
   Object.keys(map).sort().forEach(function(k){
     var x = map[k];
     var r = ws.addRow([k, x.qtd, x.valor, x.pago, x.saldo]);
     r.getCell(1).font = { bold:true };
     [3,4,5].forEach(function(i){ r.getCell(i).numFmt = '#,##0.00'; r.getCell(i).alignment = { horizontal:'right' }; });
     r.eachCell(function(cell){ cell.border = { bottom:{style:'thin',color:{argb:'FFE2E8F0'}} }; });
   });
 }
 addResumoTitulo('RESUMO POR SERVIÇO');
 addResumoTabela(resumoServico);
 addResumoTitulo('RESUMO POR ESTADO');
 addResumoTabela(resumoEstado);

 ws.columns = [
   {width:8}, {width:30}, {width:16}, {width:24}, {width:12}, {width:14},
   {width:16}, {width:16}, {width:16}, {width:14}, {width:36}, {width:20}
 ];
 ws.eachRow(function(row){ row.eachCell(function(cell){ cell.font = cell.font || { size:10 }; }); });

 var filename = 'Lancamentos_' + sigeLancamentosSafeFilePart(meta.mes || meta.estado || 'geral') + '_' + new Date().toISOString().slice(0,10) + '.xlsx';
 var buffer = await wb.xlsx.writeBuffer();
 var blob = new Blob([buffer], { type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
 if (typeof saveAs === 'function') saveAs(blob, filename);
 else {
   var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = filename; document.body.appendChild(a); a.click(); a.remove();
 }
};

// ── Modal helpers - usa classList para evitar conflito com !important do Design System ──
function sigeModalOpen(id) {
 const el = document.getElementById(id);
 if (!el) return;
 if (el.parentNode !== document.body) document.body.appendChild(el);
 el.style.display = 'flex';
 el.classList.add('is-open');
 document.body.style.overflow = 'hidden';
}
function sigeModalClose(id) {
 const el = document.getElementById(id);
 if (!el) return;
 el.style.display = 'none';
 el.classList.remove('is-open');
 document.body.style.overflow = '';
}

function sigeOpenExempt(lancId) {
 document.getElementById('sigeExemptId').value = lancId;
 document.getElementById('sigeExemptMotivo').value = '';
 document.getElementById('sigeExemptPreset').selectedIndex = 0;
 sigeModalOpen('sigeExemptModal');
}
function sigeCloseExempt() { sigeModalClose('sigeExemptModal'); }

function sigeOpenCancel(lancId) {
 document.getElementById('sigeCancelId').value = lancId;
 document.getElementById('sigeCancelMotivo').value = '';
 sigeModalOpen('sigeCancelModal');
 setTimeout(() => document.getElementById('sigeCancelMotivo').focus(), 80);
}
function sigeCloseCancel() { sigeModalClose('sigeCancelModal'); }

function sigeOpenReactivate(lancId) {
 document.getElementById('sigeReactivateId').value = lancId;
 sigeModalOpen('sigeReactivateModal');
}
function sigeCloseReactivate() { sigeModalClose('sigeReactivateModal'); }

function sigeOpenDetails(btn) {
 try {
 const raw = btn.getAttribute('data-details') || '{}';
 const data = JSON.parse(raw);
 const body = document.getElementById('sigeDetailsBody');
 body.innerHTML = '';
 Object.keys(data).forEach((k) => {
 const v = (data[k] === null || data[k] === undefined || data[k] === '') ? '-' : String(data[k]);
 const div = document.createElement('div');
 div.className = 'sg-detail-item';
 div.innerHTML = '<p class="sg-detail-label"></p><p class="sg-detail-value"></p>';
 div.querySelector('.sg-detail-label').textContent = k;
 div.querySelector('.sg-detail-value').textContent = v;
 body.appendChild(div);
 });
 sigeModalOpen('sigeDetailsModal');
 } catch(e) {
 console.error('sigeOpenDetails:', e);
 sigeUi.toast('Não foi possível abrir os detalhes. Tente novamente; se persistir, recarregue a página.', 'erro');
 }
}
function sigeCloseDetails() { sigeModalClose('sigeDetailsModal'); }

// v12.10.137 - Compatibilidade de clique: evita depender apenas de onclick inline/form=...
function sigeLancSubmitFormCompat(formId) {
 const form = document.getElementById(formId);
 if (!form) return false;
 if (typeof form.requestSubmit === 'function') form.requestSubmit();
 else {
  const tmp = document.createElement('button');
  tmp.type = 'submit'; tmp.style.display = 'none';
  form.appendChild(tmp); tmp.click(); form.removeChild(tmp);
 }
 return true;
}
document.addEventListener('click', function(e) {
 const actionBtn = e.target.closest ? e.target.closest('[data-sige-lanc-action]') : null;
 if (actionBtn) {
  const action = actionBtn.getAttribute('data-sige-lanc-action');
  const id = actionBtn.getAttribute('data-id');
  e.preventDefault();
  if (action === 'details') return sigeOpenDetails(actionBtn);
  if (action === 'cancel') return sigeOpenCancel(id);
  if (action === 'exempt') return sigeOpenExempt(id);
  if (action === 'reactivate') return sigeOpenReactivate(id);
 }
 const submitBtn = e.target.closest ? e.target.closest('[data-sige-lanc-submit-form]') : null;
 if (submitBtn) {
  e.preventDefault();
  return sigeLancSubmitFormCompat(submitBtn.getAttribute('data-sige-lanc-submit-form'));
 }
});

// Fechar ao clicar no backdrop
['sigeCancelModal','sigeExemptModal','sigeReactivateModal','sigeDetailsModal'].forEach(id => {
 const el = document.getElementById(id);
 if (el) el.addEventListener('click', function(e) {
 if (e.target === this) sigeModalClose(id);
 });
});

// Fechar com ESC
document.addEventListener('keydown', function(e) {
 if (e.key === 'Escape') {
 ['sigeCancelModal','sigeExemptModal','sigeReactivateModal','sigeDetailsModal'].forEach(id => sigeModalClose(id));
 }
});
</script>

<!-- CSS auxiliar para componentes específicos desta view -->
<style>
/* Lançamentos Financeiros - harmonia visual com o Painel Principal */
.sg-lanc-wrap{width:100%;max-width:none;margin:0;padding:0 0 28px;font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif;color:var(--color-ink-500);}
.sg-lanc-wrap *{box-sizing:border-box;}
.sg-lanc-hero{margin-bottom:18px;}
.sg-lanc-hero-panel strong{font-size:clamp(22px,2.1vw,34px);letter-spacing:-.055em;}
.sg-lanc-kpi-grid{margin-bottom:18px;}
.sg-lanc-card{padding:var(--space-5)!important;margin:0 0 18px!important;}
.sg-lanc-filterbar{margin:0 0 18px;}
.sg-lanc-filterbar .sg-filters-row{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:var(--space-3);align-items:end;}
.sg-lanc-filterbar .sg-form-group-inline{min-width:0;flex:initial;}
.sg-lanc-filterbar .sg-label{font-size:var(--fs-xs);font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--color-slate-600);margin-bottom:7px;}
.sg-lanc-filterbar .sg-input,.sg-lanc-filterbar .sg-select{width:100%;min-height:44px;border:1px solid var(--color-ink-100);background:var(--color-white);border-radius:var(--radius-md);padding:0 13px;color:var(--color-ink-500);font-weight:600;box-shadow:none;outline:0;}
.sg-lanc-filterbar .sg-input:focus,.sg-lanc-filterbar .sg-select:focus{border-color:rgba(109,93,252,.58);box-shadow:var(--shadow-xs);}
.sg-lanc-filterbar .sg-filters-actions{display:flex;align-items:end;gap:10px;margin-left:0;justify-content:flex-end;grid-column:span 2;}
.sg-lanc-table-wrap{overflow:auto;-webkit-overflow-scrolling:touch;border-radius:var(--radius-xl);border:1px solid var(--color-slate-100);background:var(--color-white);padding:0;}
.sg-lanc-table{width:100%;min-width:1040px;border-collapse:separate!important;border-spacing:0!important;}
.sg-lanc-table thead th{background:var(--color-white)!important;color:var(--color-slate-500)!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.06em!important;border-bottom:1px solid var(--color-slate-100)!important;padding:14px 14px!important;}
.sg-lanc-table tbody td{padding:15px 14px!important;border-bottom:1px solid var(--color-slate-100)!important;vertical-align:middle!important;color:var(--color-ink-800)!important;}
.sg-lanc-table tbody tr:hover td{background:var(--color-white)!important;}
.sg-lanc-table tbody tr:last-child td{border-bottom:0!important;}
.sg-lanc-table .sg-badge{border-radius:var(--radius-pill)!important;font-weight:700!important;}
.sg-lanc-table .sg-btn-group{display:flex;align-items:center;justify-content:flex-start;gap:7px;flex-wrap:wrap;}
.sg-lanc-action{min-height:32px;border-radius:var(--radius-sm);padding:0 11px;border:1px solid transparent;font-size:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none;white-space:nowrap;}
.sg-lanc-action,.sg-lanc-wrap button,.sg-lanc-wrap select,.sg-lanc-wrap input,.sg-modal-backdrop button,.sg-modal-backdrop select,.sg-modal-backdrop input{pointer-events:auto!important;}
.sg-lanc-action *,.sg-modal-backdrop button svg{pointer-events:none!important;}
.sg-modal-backdrop.is-open{pointer-events:auto!important;}
.sg-lanc-action-light{background:var(--color-white);color:var(--color-brand-500);border-color:var(--color-ink-100);}
.sg-lanc-action-primary{background:var(--color-brand-50);color:var(--color-brand-500);border-color:var(--color-info-100);}
.sg-lanc-action-success{background:var(--color-success-100);color:var(--color-success-700);border-color:var(--color-success-200);}
.sg-lanc-action-danger{background:var(--color-danger-50);color:var(--color-danger-500);border-color:var(--color-danger-100);}
.sg-lanc-action:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);}
.sg-lanc-wrap .sg-alert{border-radius:var(--radius-lg)!important;box-shadow:var(--shadow-sm);margin-bottom:16px!important;}
.sg-lanc-wrap .sg-pagination{padding-top:16px;}
@media (max-width:1320px){.sg-lanc-filterbar .sg-filters-row{grid-template-columns:repeat(3,minmax(0,1fr));}.sg-lanc-filterbar .sg-filters-actions{grid-column:1/-1;justify-content:flex-start;}}
@media (max-width:760px){.sg-lanc-filterbar .sg-filters-row{grid-template-columns:1fr;}.sg-lanc-filterbar .sg-filters-actions{flex-direction:column;align-items:stretch;}.sg-lanc-filterbar .sg-finpro-btn{width:100%;}.sg-lanc-card{padding:15px!important;border-radius:var(--radius-xl)!important;}.sg-lanc-table{min-width:960px;}.sige-lanc-modal-footer .sg-btn{width:100%;}}

/* Exportação */
.sige-finl-export-btn { border-color: rgba(14,65,148,.25) !important; color: var(--color-info-700) !important; }

/* Filtros em linha */
.sg-filters-row {
 display: flex;
 flex-wrap: wrap;
 gap: var(--sg-space-3);
 align-items: flex-end;
}
.sg-form-group-inline {
 min-width: 160px;
 flex: 1;
}
.sg-filters-actions {
 display: flex;
 gap: var(--sg-space-2);
 margin-left: auto;
}

/* Grid de detalhes */
.sg-details-grid {
 display: grid;
 grid-template-columns: repeat(2, 1fr);
 gap: var(--sg-space-3);
}
@media (max-width: 640px) {
 .sg-details-grid { grid-template-columns: 1fr; }
}
.sg-detail-item {
 background: var(--sg-slate-50);
 border: 1px solid var(--sg-slate-200);
 border-radius: var(--sg-radius-lg);
 padding: var(--sg-space-3);
}
.sg-detail-label {
 font-size: var(--sg-text-xs);
 color: var(--sg-slate-500);
 margin: 0 0 var(--sg-space-1) 0;
}
.sg-detail-value {
 font-size: var(--sg-text-sm);
 font-weight:600;
 color: var(--sg-slate-900);
 margin: 0;
 word-break: break-word;
}

/* Paginação */
.sg-pagination {
 display: flex;
 align-items: center;
 justify-content: space-between;
 flex-wrap: wrap;
 gap: var(--sg-space-3);
}
.sg-pagination-links {
 display: flex;
 gap: var(--sg-space-1);
 flex-wrap: wrap;
}
.sg-pagination-link {
 padding: var(--sg-space-2) var(--sg-space-3);
 border: 1px solid var(--sg-slate-300);
 border-radius: var(--sg-radius-lg);
 background: white;
 color: var(--sg-slate-700);
 text-decoration: none;
 font-size: var(--sg-text-sm);
 transition: all var(--sg-transition-fast);
}
.sg-pagination-link:hover {
 border-color: var(--sg-primary-500);
 color: var(--sg-primary-700);
}
.sg-pagination-link-active {
 background: var(--sg-primary-900);
 color: white;
 border-color: var(--sg-primary-900);
}

/* Grupo de botões em linha */
.sg-btn-group {
 display: flex;
 gap: var(--sg-space-1);
}

@media (max-width: 768px) {
 .sg-filters-row {
 flex-direction: column;
 align-items: stretch;
 }
 .sg-filters-actions {
 margin-left: 0;
 justify-content: flex-end;
 }
}


/* Cabeçalho refinado do módulo - seguro e auto-contido */
.sige-finl-header-refined {
 display: flex;
 flex-direction: column;
 gap: 18px;
 padding: 22px 24px;
 border: 1px solid rgba(15, 23, 42, 0.08);
 border-radius:var(--radius-xl);
 background: linear-gradient(135deg, var(--color-info-900) 0%, var(--color-info-800) 55%, var(--color-slate-700) 100%);
 box-shadow:var(--shadow-md);
}
.sige-finl-header-main {
 display: flex;
 align-items: center;
 justify-content: space-between;
 gap: 18px;
 flex-wrap: wrap;
}
.sige-finl-header-copy {
 display: flex;
 align-items: flex-start;
 gap: 14px;
 min-width: 280px;
 flex: 1 1 420px;
}
.sige-finl-header-icon {
 width: 52px;
 height: 52px;
 border-radius:var(--radius-lg);
 display: inline-flex;
 align-items: center;
 justify-content: center;
 background: rgba(255,255,255,0.14);
 color: var(--color-white);
 font-size:var(--fs-xl);
 box-shadow: inset 0 0 0 1px rgba(255,255,255,0.14);
}
.sige-finl-kicker {
 margin: 0 0 6px 0;
 font-size: 12px;
 font-weight:700;
 letter-spacing: .10em;
 text-transform: uppercase;
 color: rgba(255,255,255,0.76);
}
.sige-finl-title {
 margin: 0 !important;
 color: var(--color-white) !important;
}
.sige-finl-subtitle {
 margin:var(--space-2) 0 0 0;
 max-width: 820px;
 color: rgba(255,255,255,0.88);
 font-size: 15px;
 line-height: 1.65;
}
.sige-finl-actions {
 display: flex;
 gap: 10px;
 align-items: center;
 flex-wrap: wrap;
}
.sige-finl-actions .sg-btn.sg-btn-ghost {
 background: rgba(255,255,255,0.12);
 border-color: rgba(255,255,255,0.18);
 color: var(--color-white);
}
.sige-finl-actions .sg-btn.sg-btn-ghost:hover {
 background: rgba(255,255,255,0.18);
 color: var(--color-white);
}
.sige-finl-actions .sg-btn.sg-btn-primary {
 box-shadow:var(--shadow-sm);
}
.sige-finl-meta-row {
 display: flex;
 flex-wrap: wrap;
 gap: 10px;
}
.sige-finl-chip {
 display: inline-flex;
 align-items: center;
 gap:var(--space-2);
 min-height: 38px;
 padding: 0 14px;
 border-radius:var(--radius-pill);
 background: rgba(255,255,255,0.12);
 border: 1px solid rgba(255,255,255,0.16);
 color: var(--color-white);
 font-size:var(--fs-sm);
 font-weight:600;
 line-height: 1.2;
}
@media (max-width: 768px) {
 .sige-finl-header-refined {
 padding: 18px;
 border-radius:var(--radius-xl);
 }
 .sige-finl-header-main {
 align-items: flex-start;
 }
 .sige-finl-header-copy {
 min-width: 100%;
 }
 .sige-finl-actions {
 width: 100%;
 }
}

/* ── Modais - CSS local autónomo, independente do Design System global ── */
.sg-modal-backdrop {
 display: none !important;
 position: fixed !important;
 inset: 0 !important;
 background: rgba(15, 23, 42, 0.60) !important;
 z-index: 999999 !important;
 align-items: center !important;
 justify-content: center !important;
 padding:var(--space-5)!important;
}
.sg-modal-backdrop.is-open,
.sg-modal-backdrop[style*="flex"] {
 display: flex !important;
}
.sige-lanc-modal {
 background: var(--color-white) !important;
 border-radius:var(--radius-lg)!important;
 width: 100% !important;
 max-width: 520px !important;
 max-height: 88vh !important;
 overflow-y: auto !important;
 box-shadow:var(--shadow-lg);
 display: flex !important;
 flex-direction: column !important;
 position: relative !important;
 z-index: 1000000 !important;
 visibility: visible !important;
 opacity: 1 !important;
 pointer-events: auto !important;
 color: var(--color-ink-900) !important;
}
.sige-lanc-modal.sige-lanc-modal-lg { max-width: 720px !important; }
.sige-lanc-modal-header {
 padding: 20px 24px 14px !important;
 border-bottom: 1px solid var(--color-ink-100) !important;
 background: var(--color-slate-50) !important;
 border-radius:16px 16px 0 0 !important;
}
.sige-lanc-modal-title {
 margin: 0 !important;
 font-size: 17px !important;
 font-weight:700 !important;
 color: var(--color-ink-900) !important;
}
.sige-lanc-modal-body {
 padding:var(--space-5) var(--space-6)!important;
 flex: 1 !important;
 overflow-y: auto !important;
 color: var(--color-slate-800) !important;
}
.sige-lanc-modal-footer {
 padding: 14px 24px 18px !important;
 border-top: 1px solid var(--color-ink-50) !important;
 display: flex !important;
 justify-content: flex-end !important;
 gap: 10px !important;
 background: var(--color-slate-50) !important;
 border-radius: 0 0 16px 16px !important;
}
@media (max-width: 600px) {
 .sige-lanc-modal { max-width: 100% !important; border-radius:var(--radius-md)!important; }
 .sige-lanc-modal-footer { flex-direction: column-reverse !important; }
}


/* v12.10.96 - Filtros financeiros responsivos em laptops 17" */
@media (max-width:1600px){
    .sg-lanc-filterbar .sg-filters-row{
        grid-template-columns:repeat(4,minmax(0,1fr))!important;
    }
    .sg-lanc-filterbar .sg-filters-actions{
        grid-column:1/-1!important;
        justify-content:flex-start!important;
        flex-wrap:wrap!important;
    }
    .sg-lanc-filterbar .sg-filters-actions .sg-btn,
    .sg-lanc-filterbar .sg-filters-actions .sg-lanc-action{
        min-width:150px!important;
    }
}
@media (max-width:980px){
    .sg-lanc-filterbar .sg-filters-row{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
}
@media (max-width:680px){
    .sg-lanc-filterbar .sg-filters-row{grid-template-columns:1fr!important;}
    .sg-lanc-filterbar .sg-filters-actions,
    .sg-lanc-filterbar .sg-filters-actions .sg-btn,
    .sg-lanc-filterbar .sg-filters-actions .sg-lanc-action{width:100%!important;}
}

</style>