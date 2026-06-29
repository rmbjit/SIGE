<?php
/**
 * SIGE SoftGenial - Histórico Financeiro do Aluno PRO
 * v12.11.9.82
 *
 * Documento financeiro 360º do aluno, com ambiente oficial, impressão/guardar PDF e Excel moderno.
 * Não altera regras de cálculo; usa helpers canónicos do finance-core sempre que disponíveis.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_fin_hist_aluno_table_exists')) {
    function sige_fin_hist_aluno_table_exists(string $table_name): bool {
        global $wpdb;
        $table_name = trim($table_name);
        if ($table_name === '') return false;
        return (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));
    }
}

if (!function_exists('sige_fin_hist_aluno_column_exists')) {
    function sige_fin_hist_aluno_column_exists(string $table_name, string $column_name): bool {
        global $wpdb;
        if ($table_name === '' || $column_name === '' || !sige_fin_hist_aluno_table_exists($table_name)) return false;
        return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $wpdb->esc_like($column_name)));
    }
}

if (!function_exists('sige_fin_hist_aluno_money')) {
    function sige_fin_hist_aluno_money($valor, bool $with_currency = true): string {
        $valor = (float)$valor;
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        return number_format($valor, 2, ',', '.') . ($with_currency ? ' ' . $moeda : '');
    }
}

if (!function_exists('sige_fin_hist_aluno_date')) {
    function sige_fin_hist_aluno_date($value, bool $with_time = false): string {
        $raw = trim((string)$value);
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') return '-';
        $ts = strtotime($raw);
        if (!$ts) return '-';
        return wp_date($with_time ? 'd/m/Y H:i' : 'd/m/Y', $ts);
    }
}

if (!function_exists('sige_fin_hist_aluno_status_label')) {
    function sige_fin_hist_aluno_status_label($status): string {
        $status = strtolower(trim((string)$status));
        $map = [
            'pago' => 'Pago',
            'pendente' => 'Pendente',
            'parcial' => 'Parcial',
            'em_plano' => 'Em plano',
            'cancelado' => 'Cancelado',
            'anulado' => 'Anulado',
            'isento' => 'Isento',
            'activo' => 'Activo',
            'ativa' => 'Activa',
            'activa' => 'Activa',
        ];
        return $map[$status] ?? ($status !== '' ? ucfirst(str_replace('_', ' ', $status)) : '-');
    }
}

if (!function_exists('sige_fin_hist_aluno_status_class')) {
    function sige_fin_hist_aluno_status_class($status): string {
        $status = strtolower(trim((string)$status));
        if ($status === 'pago' || $status === 'isento') return 'ok';
        if ($status === 'parcial' || $status === 'em_plano') return 'warn';
        if ($status === 'pendente') return 'danger';
        if ($status === 'cancelado' || $status === 'anulado') return 'muted';
        return 'neutral';
    }
}

if (!function_exists('sige_fin_hist_aluno_can_download')) {
    function sige_fin_hist_aluno_can_download(int $aluno_id = 0): bool {
        if (!is_user_logged_in()) return false;

        // Guarda/Portaria nunca visualiza documentos financeiros.
        if (current_user_can('sige_guarda')) return false;
        if (function_exists('sige_permissions_get_active_role')) {
            $role = sige_permissions_get_active_role((int)get_current_user_id(), function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
            if ($role && !empty($role->slug) && (string)$role->slug === 'guarda') return false;
        }

        // Dono do portal pode baixar apenas o próprio histórico.
        $meu_aluno = function_exists('sige_get_aluno_id_do_utilizador') ? (int)sige_get_aluno_id_do_utilizador() : 0;
        if ($meu_aluno > 0 && $aluno_id > 0 && $meu_aluno === $aluno_id) return true;

        if ((function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) || (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user())) return true;

        if (function_exists('sige_page_guard_allows')) {
            try {
                return (bool)sige_page_guard_allows(
                    ['financeiro.extractos_ver','financeiro.ver','financeiro.cobrancas_ver','financeiro.relatorio_mensal_ver','documentos.emitir','documentos.reemitir'],
                    ['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao']
                );
            } catch (Throwable $e) {}
        }
        if (function_exists('sige_can')) {
            foreach (['financeiro.extractos_ver','financeiro.ver','financeiro.cobrancas_ver','financeiro.relatorio_mensal_ver','documentos.emitir','documentos.reemitir'] as $perm) {
                if (sige_can($perm)) return true;
            }
        }
        foreach (['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao'] as $cap) {
            if (current_user_can($cap)) return true;
        }
        return current_user_can('manage_options');
    }
}

if (!function_exists('sige_fin_hist_aluno_build_url')) {
    function sige_fin_hist_aluno_build_url(int $aluno_id, string $formato = 'html'): string {
        $args = [
            'sige_print' => 'historico_financeiro_aluno',
            'id' => max(0, (int)$aluno_id),
        ];
        $formato = sanitize_key($formato);
        if ($formato !== '' && $formato !== 'html') {
            $args['formato'] = $formato;
        }
        $url = add_query_arg($args, admin_url('admin.php'));
        return wp_nonce_url($url, 'sige_hist_fin_aluno_' . max(0, (int)$aluno_id));
    }
}

if (!function_exists('sige_fin_hist_aluno_load')) {
    function sige_fin_hist_aluno_load(int $aluno_id, int $escola_id = 0): array {
        global $wpdb;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        $aluno_id = max(0, (int)$aluno_id);
        if ($aluno_id <= 0) {
            return ['ok' => false, 'message' => 'Aluno inválido.'];
        }

        $tA = $wpdb->prefix . 'sige_alunos';
        $tM = $wpdb->prefix . 'sige_matriculas';
        $tT = $wpdb->prefix . 'sige_turmas';
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tP = $wpdb->prefix . 'sige_fin_pagamentos';
        $tS = $wpdb->prefix . 'sige_fin_servicos';
        $tC = $wpdb->prefix . 'sige_config';
        $tCred = $wpdb->prefix . 'sige_fin_creditos';

        if (!sige_fin_hist_aluno_table_exists($tA)) {
            return ['ok' => false, 'message' => 'Tabela de alunos indisponível.'];
        }

        $aluno = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tA} WHERE id=%d AND escola_id=%d LIMIT 1", $aluno_id, $escola_id));
        if (!$aluno) {
            return ['ok' => false, 'message' => 'Aluno não encontrado na escola activa.'];
        }

        $perfil = sige_fin_hist_aluno_table_exists($tC)
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tC} WHERE escola_id=%d LIMIT 1", $escola_id))
            : null;
        if (!$perfil) {
            $perfil = (object)[
                'nome_escola' => get_bloginfo('name'),
                'endereco_escola' => '',
                'nuit' => '',
                'telefone_oficial' => '',
                'email_institucional' => '',
                'logo_documentos_url' => '',
                'logo_sistema_url' => '',
                'rodape_documentos' => '',
            ];
        }

        $ano_actual = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (function_exists('sige_get_ano_lectivo') ? (int)sige_get_ano_lectivo() : (int)wp_date('Y'));
        $turma = null;
        if (sige_fin_hist_aluno_table_exists($tM) && sige_fin_hist_aluno_table_exists($tT)) {
            $turma = $wpdb->get_row($wpdb->prepare(
                "SELECT m.id AS matricula_id, m.ano_lectivo, " .
                (sige_fin_hist_aluno_column_exists($tM, 'status_matricula') ? "m.status_matricula" : (sige_fin_hist_aluno_column_exists($tM, 'status') ? "m.status" : "''")) . " AS status_matricula,
                        t.nome AS turma_nome, t.classe
                 FROM {$tM} m
                 LEFT JOIN {$tT} t ON t.id = m.turma_id AND t.escola_id = m.escola_id
                 WHERE m.aluno_id=%d AND m.escola_id=%d
                 ORDER BY CASE WHEN m.ano_lectivo=%d THEN 0 ELSE 1 END,
                          CASE WHEN LOWER(COALESCE(" . (sige_fin_hist_aluno_column_exists($tM, 'status_matricula') ? "m.status_matricula" : (sige_fin_hist_aluno_column_exists($tM, 'status') ? "m.status" : "''")) . ",'')) IN ('activa','ativo','activo') THEN 0 ELSE 1 END,
                          m.ano_lectivo DESC, m.id DESC
                 LIMIT 1",
                $aluno_id, $escola_id, $ano_actual
            ));
        }
        $turma_label = 'Sem turma';
        if ($turma && (trim((string)$turma->classe) !== '' || trim((string)$turma->turma_nome) !== '')) {
            $turma_label = trim((string)$turma->classe . ' - ' . (string)$turma->turma_nome, " -\t\n\r\0\x0B");
        }

        $saldo_expr = function_exists('sige_fin_saldo_sql') ? sige_fin_saldo_sql('l') : "GREATEST(COALESCE(l.valor_original,0)+COALESCE(l.valor_transporte,0)+COALESCE(l.valor_extras,0)+COALESCE(NULLIF(l.valor_multa_cobrada,0),l.valor_multa,0)-COALESCE(l.valor_desconto,0)-COALESCE(l.valor_desconto_especial,0)-COALESCE(l.valor_pago,0),0)";
        $total_expr = function_exists('sige_fin_total_bruto_sql') ? sige_fin_total_bruto_sql('l') : "GREATEST(COALESCE(l.valor_original,0)+COALESCE(l.valor_transporte,0)+COALESCE(l.valor_extras,0)+COALESCE(NULLIF(l.valor_multa_cobrada,0),l.valor_multa,0)-COALESCE(l.valor_desconto,0)-COALESCE(l.valor_desconto_especial,0),0)";

        $lancamentos = [];
        if (sige_fin_hist_aluno_table_exists($tL)) {
            $join_serv = sige_fin_hist_aluno_table_exists($tS) ? "LEFT JOIN {$tS} s ON s.id = l.servico_id AND s.escola_id = l.escola_id" : "";
            $select_serv = sige_fin_hist_aluno_table_exists($tS) ? "COALESCE(NULLIF(s.nome,''), NULLIF(l.descricao,''), 'Serviço não identificado') AS servico_nome, COALESCE(s.ciclo,'') AS servico_ciclo, COALESCE(s.classe,'') AS servico_classe," : "COALESCE(NULLIF(l.descricao,''), 'Serviço não identificado') AS servico_nome, '' AS servico_ciclo, '' AS servico_classe,";
            $lancamentos = $wpdb->get_results($wpdb->prepare(
                "SELECT l.*, {$select_serv} {$total_expr} AS total_previsto_calc, {$saldo_expr} AS saldo_calc
                 FROM {$tL} l
                 {$join_serv}
                 WHERE l.escola_id=%d AND l.aluno_id=%d
                 ORDER BY COALESCE(l.data_vencimento, '9999-12-31') ASC, l.id ASC",
                $escola_id, $aluno_id
            ));
            $lancamentos = is_array($lancamentos) ? $lancamentos : [];
        }

        $pagamentos = [];
        if (sige_fin_hist_aluno_table_exists($tP)) {
            $join_l = sige_fin_hist_aluno_table_exists($tL) ? "LEFT JOIN {$tL} l ON l.id = p.lancamento_id AND l.escola_id = p.escola_id" : "";
            $join_s = (sige_fin_hist_aluno_table_exists($tS) && sige_fin_hist_aluno_table_exists($tL)) ? "LEFT JOIN {$tS} s ON s.id = l.servico_id AND s.escola_id = p.escola_id" : "";
            $pagamentos = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, COALESCE(NULLIF(p.recibo_numero,''), CONCAT('#', p.id)) AS recibo_ref,
                        COALESCE(NULLIF(l.descricao,''), NULLIF(s.nome,''), 'Pagamento') AS descricao_lancamento,
                        COALESCE(s.nome,'') AS servico_nome, COALESCE(l.mes_referencia,'') AS mes_referencia_lancamento
                 FROM {$tP} p
                 {$join_l}
                 {$join_s}
                 WHERE p.escola_id=%d AND p.aluno_id=%d
                 ORDER BY p.data_pagamento DESC, p.id DESC",
                $escola_id, $aluno_id
            ));
            $pagamentos = is_array($pagamentos) ? $pagamentos : [];
        }

        $creditos = [];
        if (sige_fin_hist_aluno_table_exists($tCred)) {
            $creditos = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$tCred}
                 WHERE escola_id=%d AND aluno_id=%d
                 ORDER BY id DESC
                 LIMIT 20",
                $escola_id, $aluno_id
            ));
            $creditos = is_array($creditos) ? $creditos : [];
        }

        $total_lancado = 0.0;
        $saldo_aberto = 0.0;
        $saldo_em_plano = 0.0;
        $lanc_pagos = 0;
        $lanc_abertos = 0;
        $proximos = [];
        $hoje = function_exists('sige_mz_date') ? sige_mz_date('Y-m-d') : wp_date('Y-m-d');
        foreach ($lancamentos as $l) {
            $total_item = isset($l->total_previsto_calc) ? (float)$l->total_previsto_calc : (function_exists('sige_fin_total_lancamento') ? (float)sige_fin_total_lancamento($l) : 0.0);
            $saldo_item = isset($l->saldo_calc) ? (float)$l->saldo_calc : (function_exists('sige_fin_saldo_lancamento') ? (float)sige_fin_saldo_lancamento($l) : 0.0);
            $status = strtolower((string)($l->status ?? ''));
            $l->total_previsto_pro = $total_item;
            $l->saldo_pro = $saldo_item;
            $total_lancado += $total_item;
            if ($status === 'pago') $lanc_pagos++;
            if (in_array($status, ['pendente','parcial','em_plano'], true) && $saldo_item > 0.009) {
                $lanc_abertos++;
                if ($status === 'em_plano') $saldo_em_plano += $saldo_item;
                else $saldo_aberto += $saldo_item;
                $venc = (string)($l->data_vencimento ?? '');
                if ($venc !== '' && $venc >= $hoje) $proximos[] = $l;
            }
        }

        $total_entradas = 0.0;
        $total_estornos = 0.0;
        $total_liquido = 0.0;
        $por_metodo = [];
        $por_mes = [];
        $ultimo_pagamento = null;
        foreach ($pagamentos as $p) {
            $valor = (float)($p->valor_pago ?? 0);
            $metodo = strtolower(trim((string)($p->metodo_pagamento ?? '')));
            if ($metodo === '') $metodo = 'outro';
            $label_metodo = function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($metodo) : ucfirst($metodo);
            if (!isset($por_metodo[$label_metodo])) $por_metodo[$label_metodo] = 0.0;
            if ($valor < 0 || $metodo === 'estorno') {
                $total_estornos += abs($valor);
                $por_metodo[$label_metodo] += $valor;
            } else {
                $total_entradas += $valor;
                $por_metodo[$label_metodo] += $valor;
            }
            $total_liquido += $valor;
            $mes_key = '';
            $data_pag = (string)($p->data_pagamento ?? '');
            if (preg_match('/^(\d{4}-\d{2})/', $data_pag, $m)) $mes_key = $m[1];
            if ($mes_key !== '') {
                if (!isset($por_mes[$mes_key])) $por_mes[$mes_key] = 0.0;
                $por_mes[$mes_key] += $valor;
            }
            if ($ultimo_pagamento === null && $valor > 0) $ultimo_pagamento = $p;
        }
        arsort($por_metodo);
        ksort($por_mes);
        $por_mes = array_slice($por_mes, -12, 12, true);

        $credito_disponivel = 0.0;
        foreach ($creditos as $c) {
            $status = strtolower((string)($c->status ?? ''));
            $valor_disp = isset($c->valor_disponivel) ? (float)$c->valor_disponivel : (float)($c->valor ?? 0);
            if ($valor_disp > 0 && !in_array($status, ['cancelado','anulado','usado','esgotado'], true)) {
                $credito_disponivel += $valor_disp;
            }
        }

        $divida_total = max(0.0, $saldo_aberto + $saldo_em_plano);
        $regularidade = $total_lancado > 0 ? max(0, min(100, (int)round(($total_liquido / $total_lancado) * 100))) : ($divida_total > 0 ? 0 : 100);
        $estado_financeiro = 'Regular';
        $estado_classe = 'ok';
        $recomendacao = 'Aluno sem dívida aberta no momento. Manter acompanhamento normal.';
        if ($saldo_aberto > 0.009) {
            $estado_financeiro = 'Com dívida';
            $estado_classe = 'danger';
            $recomendacao = 'Existe valor em aberto. Confirmar pagamento ou encaminhar para regularização.';
        } elseif ($saldo_em_plano > 0.009) {
            $estado_financeiro = 'Em plano';
            $estado_classe = 'warn';
            $recomendacao = 'Existe plano de pagamento activo. Acompanhar o cumprimento do acordo.';
        } elseif ($credito_disponivel > 0.009) {
            $estado_financeiro = 'Com crédito';
            $estado_classe = 'credit';
            $recomendacao = 'Aluno tem crédito disponível. Validar aplicação em próximos lançamentos.';
        }

        return [
            'ok' => true,
            'escola_id' => $escola_id,
            'aluno_id' => $aluno_id,
            'aluno' => $aluno,
            'perfil' => $perfil,
            'turma' => $turma,
            'turma_label' => $turma_label,
            'ano_lectivo' => $ano_actual,
            'lancamentos' => $lancamentos,
            'pagamentos' => $pagamentos,
            'creditos' => $creditos,
            'por_metodo' => $por_metodo,
            'por_mes' => $por_mes,
            'proximos' => $proximos,
            'ultimo_pagamento' => $ultimo_pagamento,
            'totais' => [
                'total_lancado' => $total_lancado,
                'total_entradas' => $total_entradas,
                'total_estornos' => $total_estornos,
                'total_liquido' => $total_liquido,
                'saldo_aberto' => $saldo_aberto,
                'saldo_em_plano' => $saldo_em_plano,
                'divida_total' => $divida_total,
                'credito_disponivel' => $credito_disponivel,
                'lancamentos_pagos' => $lanc_pagos,
                'lancamentos_abertos' => $lanc_abertos,
                'pagamentos_count' => count($pagamentos),
                'regularidade' => $regularidade,
                'estado_financeiro' => $estado_financeiro,
                'estado_classe' => $estado_classe,
                'recomendacao' => $recomendacao,
            ],
        ];
    }
}

if (!function_exists('sige_fin_hist_aluno_doc_ref')) {
    function sige_fin_hist_aluno_doc_ref(array $data): string {
        return 'HFA-' . (int)($data['aluno_id'] ?? 0) . '-' . wp_date('YmdHis');
    }
}

if (!function_exists('sige_fin_hist_aluno_safe_filename_part')) {
    function sige_fin_hist_aluno_safe_filename_part($value): string {
        $value = sanitize_file_name((string)$value);
        $value = trim($value, '-_ .');
        return $value !== '' ? $value : 'aluno';
    }
}

if (!function_exists('sige_fin_hist_aluno_xlsx_xml')) {
    function sige_fin_hist_aluno_xlsx_xml($value): string {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sige_fin_hist_aluno_xlsx_col')) {
    function sige_fin_hist_aluno_xlsx_col(int $index): string {
        $index = max(1, $index);
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = intdiv($index, 26);
        }
        return $letters;
    }
}

if (!function_exists('sige_fin_hist_aluno_xlsx_cell')) {
    function sige_fin_hist_aluno_xlsx_cell($cell, int $row, int $col): string {
        $value = $cell;
        $type = 's';
        $style = 0;
        if (is_array($cell)) {
            $value = $cell[0] ?? '';
            $type = $cell[1] ?? 's';
            $style = (int)($cell[2] ?? 0);
        }
        $ref = sige_fin_hist_aluno_xlsx_col($col) . $row;
        $s = $style > 0 ? ' s="' . $style . '"' : '';
        if ($type === 'n' || $type === 'money' || $type === 'percent') {
            $num = is_numeric($value) ? (float)$value : 0.0;
            return '<c r="' . $ref . '"' . $s . '><v>' . rtrim(rtrim(number_format($num, 6, '.', ''), '0'), '.') . '</v></c>';
        }
        return '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t>' . sige_fin_hist_aluno_xlsx_xml($value) . '</t></is></c>';
    }
}

if (!function_exists('sige_fin_hist_aluno_xlsx_sheet_xml')) {
    function sige_fin_hist_aluno_xlsx_sheet_xml(array $rows, array $widths = [], string $auto_filter = '', string $freeze = '', array $merges = [], array $row_heights = []): string {
        $max_col = 1;
        foreach ($rows as $row) {
            $max_col = max($max_col, count($row));
        }
        $max_row = max(1, count($rows));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $xml .= '<dimension ref="A1:' . sige_fin_hist_aluno_xlsx_col($max_col) . $max_row . '"/>';
        // Grelha sempre oculta para que apenas as celulas com contorno definam o documento (aspecto institucional sobrio).
        $xml .= '<sheetViews><sheetView showGridLines="0" workbookViewId="0">';
        if ($freeze !== '') {
            $xml .= '<pane ' . $freeze . ' state="frozen"/>';
        }
        $xml .= '</sheetView></sheetViews>';
        $xml .= '<sheetFormatPr defaultRowHeight="16.5"/>';
        if (!empty($widths)) {
            $xml .= '<cols>';
            foreach ($widths as $idx => $width) {
                $col = (int)$idx + 1;
                $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . number_format((float)$width, 2, '.', '') . '" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        foreach ($rows as $r => $row) {
            $row_num = $r + 1;
            $h = '';
            if (isset($row_heights[$row_num])) {
                $h = ' ht="' . number_format((float)$row_heights[$row_num], 2, '.', '') . '" customHeight="1"';
            }
            $xml .= '<row r="' . $row_num . '"' . $h . '>';
            foreach ($row as $c => $cell) {
                $xml .= sige_fin_hist_aluno_xlsx_cell($cell, $row_num, $c + 1);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($auto_filter !== '') {
            $xml .= '<autoFilter ref="' . sige_fin_hist_aluno_xlsx_xml($auto_filter) . '"/>';
        }
        if (!empty($merges)) {
            $xml .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $merge) {
                $xml .= '<mergeCell ref="' . sige_fin_hist_aluno_xlsx_xml($merge) . '"/>';
            }
            $xml .= '</mergeCells>';
        }
        $xml .= '<pageMargins left="0.3" right="0.3" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
        $xml .= '</worksheet>';
        return $xml;
    }
}

if (!function_exists('sige_fin_hist_aluno_xlsx_styles_xml')) {
    function sige_fin_hist_aluno_xlsx_styles_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00 &quot;MT&quot;"/><numFmt numFmtId="165" formatCode="0%"/></numFmts>
<fonts count="7"><font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF166534"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF991B1B"/><name val="Calibri"/></font><font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/></font></fonts>
<fills count="8"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F2747"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EDF4"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFECFDF3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEF2F2"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF7E6"/><bgColor indexed="64"/></patternFill></fill></fills>
<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E1EC"/></left><right style="thin"><color rgb="FFD9E1EC"/></right><top style="thin"><color rgb="FFD9E1EC"/></top><bottom style="thin"><color rgb="FFD9E1EC"/></bottom><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="12"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="164" fontId="6" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="164" fontId="4" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="164" fontId="5" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf><xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="5" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="165" fontId="6" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf></cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/></styleSheet>';
    }
}

if (!function_exists('sige_fin_hist_aluno_zip_binary')) {
    function sige_fin_hist_aluno_zip_binary(array $entries): string {
        $local = '';
        $central = '';
        $offset = 0;
        $dos_time = 0;
        $dos_date = (1 << 5) | 1; // 1980-01-01
        foreach ($entries as $name => $data) {
            $name = str_replace('\\', '/', (string)$name);
            $data = (string)$data;
            $crc = crc32($data);
            if ($crc < 0) $crc += 4294967296;
            $size = strlen($data);
            $local_header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dos_time, $dos_date, $crc, $size, $size, strlen($name), 0) . $name;
            $local .= $local_header . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dos_time, $dos_date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($local_header) + $size;
        }
        return $local . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $offset, 0);
    }
}

if (!function_exists('sige_fin_hist_aluno_output_xlsx')) {
    function sige_fin_hist_aluno_output_xlsx(array $data): void {
        $aluno = $data['aluno'];
        $perfil = $data['perfil'];
        $tot = $data['totais'];
        $doc_ref = sige_fin_hist_aluno_doc_ref($data);
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        $estado_style = $tot['estado_classe'] === 'danger' ? 9 : ($tot['estado_classe'] === 'warn' ? 10 : 8);
        $money_alert = ((float)$tot['divida_total'] > 0.009) ? 7 : 6;
        $school = trim((string)($perfil->nome_escola ?? get_bloginfo('name')));
        $student = trim((string)($aluno->nome_completo ?? 'Aluno'));
        $proc = trim((string)($aluno->numero_processo ?? $aluno->id));
        $filename = 'historico-financeiro-oficial-' . sige_fin_hist_aluno_safe_filename_part($proc . '-' . $student) . '-' . wp_date('Ymd-His') . '.xlsx';

        $summary = [
            [['HISTÓRICO FINANCEIRO OFICIAL DO ALUNO', 's', 1]],
            [['Documento administrativo · Visão financeira 360º · ' . $doc_ref, 's', 2]],
            [],
            [['Escola', 's', 3], [$school, 's', 0], ['Emitido em', 's', 3], [wp_date('d/m/Y H:i'), 's', 0]],
            [['Aluno', 's', 3], [$student, 's', 0], ['Processo', 's', 3], [$proc, 's', 0]],
            [['Turma actual', 's', 3], [$data['turma_label'] ?? '-', 's', 0], ['Situação do aluno', 's', 3], [sige_fin_hist_aluno_status_label($aluno->status ?? 'activo'), 's', 0]],
            [],
            [['Indicador 360º', 's', 4], ['Valor', 's', 4], ['Nota', 's', 4]],
            [['Dívida actual', 's', 3], [$tot['divida_total'], 'money', $money_alert], ['Aberta + plano', 's', 0]],
            [['Entradas líquidas', 's', 3], [$tot['total_liquido'], 'money', 6], ['Entradas menos estornos', 's', 0]],
            [['Total lançado', 's', 3], [$tot['total_lancado'], 'money', 5], ['Obrigações registadas', 's', 0]],
            [['Regularidade', 's', 3], [($tot['regularidade'] / 100), 'percent', 11], [(string)$tot['lancamentos_pagos'] . ' pagos · ' . (string)$tot['lancamentos_abertos'] . ' abertos', 's', 0]],
            [['Estado financeiro', 's', 3], [$tot['estado_financeiro'], 's', $estado_style], [$tot['recomendacao'], 's', 0]],
            [['Entradas', 's', 3], [$tot['total_entradas'], 'money', 5], ['Pagamentos positivos', 's', 0]],
            [['Estornos', 's', 3], [$tot['total_estornos'], 'money', 7], ['Movimentos de devolução/anulação', 's', 0]],
            [['Crédito disponível', 's', 3], [$tot['credito_disponivel'], 'money', 5], ['Valor disponível para aplicação', 's', 0]],
        ];

        $payRows = [
            [['PAGAMENTOS REGISTADOS', 's', 1]],
            [['Aluno', 's', 3], [$student, 's', 0], ['Processo', 's', 3], [$proc, 's', 0]],
            [],
            [['Data', 's', 4], ['Recibo', 's', 4], ['Serviço / descrição', 's', 4], ['Referência', 's', 4], ['Método', 's', 4], ['Valor', 's', 4], ['Observação', 's', 4]],
        ];
        foreach ($data['pagamentos'] as $p) {
            $metodo = function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($p->metodo_pagamento ?? '') : ($p->metodo_pagamento ?? '');
            $valor = (float)($p->valor_pago ?? 0);
            $payRows[] = [
                [sige_fin_hist_aluno_date($p->data_pagamento ?? '', true), 's', 0],
                [$p->recibo_ref ?? ($p->recibo_numero ?? '-'), 's', 0],
                [$p->descricao_lancamento ?? ($p->servico_nome ?? 'Pagamento'), 's', 0],
                [$p->mes_referencia_lancamento ?? '', 's', 0],
                [$metodo, 's', 0],
                [$valor, 'money', $valor < 0 ? 7 : 6],
                [$p->observacoes ?? '', 's', 0],
            ];
        }
        if (count($payRows) === 4) $payRows[] = [['Sem pagamentos registados', 's', 0]];

        $oblRows = [
            [['LANÇAMENTOS E OBRIGAÇÕES', 's', 1]],
            [['Base de cálculo preservada · ' . $doc_ref, 's', 2]],
            [],
            [['Vencimento', 's', 4], ['Referência', 's', 4], ['Serviço / descrição', 's', 4], ['Estado', 's', 4], ['Total', 's', 4], ['Pago', 's', 4], ['Saldo', 's', 4]],
        ];
        foreach ($data['lancamentos'] as $l) {
            $status = strtolower((string)($l->status ?? ''));
            $statusStyle = in_array($status, ['pago','isento'], true) ? 8 : (in_array($status, ['pendente','parcial','em_plano'], true) ? 10 : 0);
            $saldo = (float)($l->saldo_pro ?? 0);
            $oblRows[] = [
                [sige_fin_hist_aluno_date($l->data_vencimento ?? ''), 's', 0],
                [$l->mes_referencia ?? '-', 's', 0],
                [$l->servico_nome ?? ($l->descricao ?? 'Obrigação financeira'), 's', 0],
                [sige_fin_hist_aluno_status_label($l->status ?? ''), 's', $statusStyle],
                [(float)($l->total_previsto_pro ?? 0), 'money', 5],
                [(float)($l->valor_pago ?? 0), 'money', 5],
                [$saldo, 'money', $saldo > 0.009 ? 7 : 6],
            ];
        }
        if (count($oblRows) === 4) $oblRows[] = [['Sem lançamentos localizados', 's', 0]];

        $metRows = [
            [['MÉTODOS E ACOMPANHAMENTO 360º', 's', 1]],
            [['Aluno', 's', 3], [$student, 's', 0], ['Documento', 's', 3], [$doc_ref, 's', 0]],
            [],
            [['Método', 's', 4], ['Total', 's', 4]],
        ];
        foreach ($data['por_metodo'] as $metodo => $valor) {
            $metRows[] = [[$metodo, 's', 0], [(float)$valor, 'money', $valor < 0 ? 7 : 6]];
        }
        $metRows[] = [];
        $metRows[] = [['Próximos acompanhamentos', 's', 4], ['Valor em aberto', 's', 4], ['Estado', 's', 4]];
        if (!empty($data['proximos'])) {
            foreach ($data['proximos'] as $l) {
                $metRows[] = [[sige_fin_hist_aluno_date($l->data_vencimento ?? '') . ' · ' . ($l->servico_nome ?? ($l->descricao ?? 'Obrigação financeira')), 's', 0], [(float)($l->saldo_pro ?? 0), 'money', 7], [sige_fin_hist_aluno_status_label($l->status ?? ''), 's', 10]];
            }
        } else {
            $metRows[] = [['Sem próximos vencimentos em aberto', 's', 0], [0, 'money', 6], ['Regular', 's', 8]];
        }
        $metRows[] = [];
        $metRows[] = [['Observação', 's', 3], ['Valores reflectem os registos existentes no momento da emissão. Documento gerado pelo SIGE SoftGenial para uso administrativo controlado.', 's', 0]];

        $sheets = [
            'Resumo 360' => sige_fin_hist_aluno_xlsx_sheet_xml($summary, [28, 24, 26, 28, 18, 18, 18], 'A8:C16', 'ySplit="8" topLeftCell="A9" activePane="bottomLeft"', ['A1:G1','A2:G2'], [1 => 30, 2 => 20, 8 => 18]),
            'Pagamentos' => sige_fin_hist_aluno_xlsx_sheet_xml($payRows, [18, 22, 46, 18, 20, 18, 36], 'A4:G' . max(4, count($payRows)), 'ySplit="4" topLeftCell="A5" activePane="bottomLeft"', ['A1:G1'], [1 => 30, 4 => 18]),
            'Obrigacoes' => sige_fin_hist_aluno_xlsx_sheet_xml($oblRows, [18, 18, 46, 18, 18, 18, 18], 'A4:G' . max(4, count($oblRows)), 'ySplit="4" topLeftCell="A5" activePane="bottomLeft"', ['A1:G1','A2:G2'], [1 => 30, 2 => 20, 4 => 18]),
            'Metodos 360' => sige_fin_hist_aluno_xlsx_sheet_xml($metRows, [42, 20, 18, 28], 'A4:B' . max(4, count($metRows)), 'ySplit="4" topLeftCell="A5" activePane="bottomLeft"', ['A1:D1'], [1 => 30, 2 => 20, 4 => 18]),
        ];

        $workbookSheets = '';
        $rels = '';
        $i = 1;
        foreach (array_keys($sheets) as $name) {
            $workbookSheets .= '<sheet name="' . sige_fin_hist_aluno_xlsx_xml($name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
            $i++;
        }
        $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($n = 1; $n <= count($sheets); $n++) $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $contentTypes .= '</Types>';
        $entries = [
            '[Content_Types].xml' => $contentTypes,
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="false"/><sheets>' . $workbookSheets . '</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>',
            'xl/styles.xml' => sige_fin_hist_aluno_xlsx_styles_xml(),
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Histórico Financeiro Oficial do Aluno</dc:title><dc:creator>SIGE SoftGenial</dc:creator></cp:coreProperties>',
        ];
        $n = 1;
        foreach ($sheets as $xml) {
            $entries['xl/worksheets/sheet' . $n . '.xml'] = $xml;
            $n++;
        }
        while (ob_get_level()) { ob_end_clean(); }
        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo sige_fin_hist_aluno_zip_binary($entries);
    }
}

if (!function_exists('sige_fin_hist_aluno_output_csv')) {
    function sige_fin_hist_aluno_output_csv(array $data): void {
        // Compatibilidade com URLs antigas: o formato CSV foi substituído por Excel .xlsx.
        sige_fin_hist_aluno_output_xlsx($data);
    }
}

if (!function_exists('sige_fin_hist_aluno_escape_attr_class')) {
    function sige_fin_hist_aluno_escape_attr_class($class): string {
        return sanitize_html_class((string)$class, 'neutral');
    }
}

if (!function_exists('sige_fin_hist_aluno_render_html')) {
    function sige_fin_hist_aluno_render_html(array $data): void {
        $aluno = $data['aluno'];
        $perfil = $data['perfil'];
        $tot = $data['totais'];
        $logo = !empty($perfil->logo_documentos_url) ? $perfil->logo_documentos_url : (!empty($perfil->logo_sistema_url) ? $perfil->logo_sistema_url : '');
        $doc_ref = sige_fin_hist_aluno_doc_ref($data);
        $xlsx_url = sige_fin_hist_aluno_build_url((int)$data['aluno_id'], 'xlsx');
        $estado_class = sige_fin_hist_aluno_escape_attr_class($tot['estado_classe']);
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        while (ob_get_level()) { ob_end_clean(); }
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);
        ?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Histórico financeiro oficial - <?php echo esc_html($aluno->nome_completo ?? 'Aluno'); ?></title>
<link rel="stylesheet" href="<?php echo esc_url(defined('SIGE_URL') ? SIGE_URL . 'assets/documents/financeiro-historico-aluno.css' : ''); ?>?ver=<?php echo esc_attr(defined('SIGE_VERSION') ? SIGE_VERSION : '12.14.4'); ?>">
<script defer src="<?php echo esc_url(defined('SIGE_URL') ? SIGE_URL . 'assets/sige-document-actions.js' : ''); ?>?ver=<?php echo esc_attr(defined('SIGE_VERSION') ? SIGE_VERSION : '12.14.4'); ?>"></script>
</head>
<body>
<div class="screen-bar">
  <div class="env"><span class="env-badge">Histórico financeiro</span><div><strong>Ambiente Histórico Financeiro do Aluno</strong><span>Está a consultar o histórico oficial de <?php echo esc_html($aluno->nome_completo ?? 'Aluno'); ?>.</span></div></div>
  <div class="actions"><button class="btn btn-primary" type="button" data-sige-print>Guardar PDF / Imprimir</button><a class="btn" href="<?php echo esc_url($xlsx_url); ?>">Baixar Excel</a><button class="btn" type="button" data-sige-close-back>Fechar</button></div>
</div>

<main class="sheet">
 <div class="sheet-inner">

  <header class="letterhead">
    <div class="brand">
      <div class="logo"><?php if ($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="Logotipo"><?php else: $__ini = trim((string)($perfil->nome_escola ?? get_bloginfo('name'))); $__ini = function_exists('mb_substr') ? mb_substr($__ini, 0, 1) : substr($__ini, 0, 1); ?><span class="logo-ph"><?php echo esc_html(strtoupper($__ini)); ?></span><?php endif; ?></div>
      <div class="school">
        <h1><?php echo esc_html($perfil->nome_escola ?? get_bloginfo('name')); ?></h1>
        <?php if (trim((string)($perfil->endereco_escola ?? '')) !== ''): ?><p><?php echo esc_html(trim((string)$perfil->endereco_escola)); ?></p><?php endif; ?>
        <?php
          $__contactos = [];
          if (!empty($perfil->telefone_oficial)) $__contactos[] = 'Tel.: ' . $perfil->telefone_oficial;
          if (!empty($perfil->email_institucional)) $__contactos[] = (string)$perfil->email_institucional;
          if (!empty($perfil->nuit)) $__contactos[] = 'NUIT: ' . $perfil->nuit;
          if (!empty($__contactos)): ?><p><?php echo esc_html(implode('   ·   ', $__contactos)); ?></p><?php endif;
        ?>
      </div>
    </div>
    <aside class="doc-control">
      <div class="dc-h">Documento administrativo oficial</div>
      <div class="dc-b">
        <div class="dc-row"><span>Referência</span><strong><?php echo esc_html($doc_ref); ?></strong></div>
        <div class="dc-row"><span>Emitido em</span><strong><?php echo esc_html(wp_date('d/m/Y H:i')); ?></strong></div>
        <div class="dc-row"><span>Ano lectivo</span><strong><?php echo esc_html((string)($data['ano_lectivo'] ?? wp_date('Y'))); ?></strong></div>
        <div class="dc-row"><span>Uso</span><strong>Interno</strong></div>
      </div>
    </aside>
  </header>
  <div class="rule-strong"></div>
  <div class="rule-thin"></div>

  <section class="doc-title">
    <div class="eyebrow">Visão financeira 360º</div>
    <h2>Histórico financeiro do aluno</h2>
    <p>Documento para tesouraria, secretaria e direcção: situação financeira, pagamentos, obrigações, dívida e crédito.</p>
  </section>

  <section class="block">
    <h3>Identificação do aluno</h3>
    <div class="block-body">
      <div class="ficha">
        <div class="f-row"><span>Nome</span><strong><?php echo esc_html($aluno->nome_completo ?? '-'); ?></strong></div>
        <div class="f-row"><span>Nº de processo</span><strong><?php echo esc_html($aluno->numero_processo ?? '-'); ?></strong></div>
        <div class="f-row"><span>Turma actual</span><strong><?php echo esc_html($data['turma_label']); ?></strong></div>
        <div class="f-row"><span>Situação</span><strong><?php echo esc_html(sige_fin_hist_aluno_status_label($aluno->status ?? 'activo')); ?></strong></div>
        <div class="f-row"><span>Estado financeiro</span><strong class="state <?php echo esc_attr($estado_class); ?>"><?php echo esc_html($tot['estado_financeiro']); ?></strong></div>
        <div class="f-row"><span>Regularidade</span><strong><?php echo esc_html((string)$tot['regularidade']); ?>%   ·   <?php echo esc_html((string)$tot['lancamentos_pagos']); ?> pago(s), <?php echo esc_html((string)$tot['lancamentos_abertos']); ?> aberto(s)</strong></div>
      </div>
    </div>
  </section>

  <section class="block">
    <h3>Quadro da situação financeira <small><?php echo esc_html($moeda); ?></small></h3>
    <div class="block-body">
      <table class="grid">
        <thead><tr><th>Indicador</th><th class="num">Valor</th><th>Nota</th></tr></thead>
        <tbody>
          <tr><td class="lbl">Total lançado</td><td class="num"><?php echo esc_html(sige_fin_hist_aluno_money($tot['total_lancado'])); ?></td><td>Obrigações registadas</td></tr>
          <tr><td class="lbl">Entradas</td><td class="num pos"><?php echo esc_html(sige_fin_hist_aluno_money($tot['total_entradas'])); ?></td><td>Pagamentos positivos</td></tr>
          <tr><td class="lbl">Estornos</td><td class="num neg"><?php echo esc_html(sige_fin_hist_aluno_money($tot['total_estornos'])); ?></td><td>Devoluções / anulações</td></tr>
          <tr><td class="lbl">Entradas líquidas</td><td class="num"><?php echo esc_html(sige_fin_hist_aluno_money($tot['total_liquido'])); ?></td><td>Entradas menos estornos</td></tr>
          <tr><td class="lbl">Dívida em aberto</td><td class="num <?php echo ((float)$tot['saldo_aberto'] > 0.009) ? 'neg' : ''; ?>"><?php echo esc_html(sige_fin_hist_aluno_money($tot['saldo_aberto'])); ?></td><td>Pendente / parcial</td></tr>
          <tr><td class="lbl">Dívida em plano</td><td class="num"><?php echo esc_html(sige_fin_hist_aluno_money($tot['saldo_em_plano'])); ?></td><td>Acordo de pagamento</td></tr>
          <tr><td class="lbl">Dívida total</td><td class="num <?php echo ((float)$tot['divida_total'] > 0.009) ? 'neg' : 'pos'; ?>"><?php echo esc_html(sige_fin_hist_aluno_money($tot['divida_total'])); ?></td><td>Aberta + plano</td></tr>
          <tr><td class="lbl">Crédito disponível</td><td class="num <?php echo ((float)$tot['credito_disponivel'] > 0.009) ? 'pos' : ''; ?>"><?php echo esc_html(sige_fin_hist_aluno_money($tot['credito_disponivel'])); ?></td><td>Para aplicação futura</td></tr>
          <tr><td class="lbl">Último pagamento</td><td class="num"><?php echo $data['ultimo_pagamento'] ? esc_html(sige_fin_hist_aluno_money($data['ultimo_pagamento']->valor_pago ?? 0)) : '-'; ?></td><td><?php echo $data['ultimo_pagamento'] ? esc_html(sige_fin_hist_aluno_date($data['ultimo_pagamento']->data_pagamento ?? '', true)) : 'Sem registo'; ?></td></tr>
        </tbody>
      </table>
      <div class="parecer <?php echo esc_attr($estado_class); ?>"><b>Parecer:</b> <?php echo esc_html($tot['recomendacao']); ?></div>
    </div>
  </section>

  <section class="block">
    <h3>Pagamentos registados <small><?php echo esc_html((string)$tot['pagamentos_count']); ?> movimento(s)</small></h3>
    <div class="block-body">
      <table class="grid">
        <thead><tr><th>Data</th><th>Recibo</th><th>Serviço / descrição</th><th>Método</th><th class="num">Valor</th></tr></thead>
        <tbody>
        <?php if (!empty($data['pagamentos'])): foreach ($data['pagamentos'] as $p): $valor = (float)($p->valor_pago ?? 0); $metodo = function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($p->metodo_pagamento ?? '') : ($p->metodo_pagamento ?? ''); ?>
          <tr>
            <td><?php echo esc_html(sige_fin_hist_aluno_date($p->data_pagamento ?? '', true)); ?></td>
            <td><?php echo esc_html($p->recibo_ref ?? ($p->recibo_numero ?? '-')); ?></td>
            <td><?php echo esc_html($p->descricao_lancamento ?? ($p->servico_nome ?? 'Pagamento')); ?><?php if (!empty($p->mes_referencia_lancamento)): ?> <span style="color:var(--muted)">· <?php echo esc_html($p->mes_referencia_lancamento); ?></span><?php endif; ?></td>
            <td><?php echo esc_html($metodo); ?></td>
            <td class="num <?php echo $valor < 0 ? 'neg' : 'pos'; ?>"><?php echo esc_html(sige_fin_hist_aluno_money($valor)); ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="5"><div class="empty">Ainda não existem pagamentos registados para este aluno.</div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      <div class="scroll-hint">Em telemóvel, deslize a tabela para ver todos os campos.</div>
    </div>
  </section>

  <section class="block">
    <h3>Lançamentos e obrigações <small>Base de cálculo preservada</small></h3>
    <div class="block-body">
      <table class="grid">
        <thead><tr><th>Vencimento</th><th>Referência</th><th>Serviço / descrição</th><th>Estado</th><th class="num">Total</th><th class="num">Pago</th><th class="num">Saldo</th></tr></thead>
        <tbody>
        <?php if (!empty($data['lancamentos'])): foreach ($data['lancamentos'] as $l): $sld = (float)($l->saldo_pro ?? 0); ?>
          <tr>
            <td><?php echo esc_html(sige_fin_hist_aluno_date($l->data_vencimento ?? '')); ?></td>
            <td><?php echo esc_html($l->mes_referencia ?? '-'); ?></td>
            <td><?php echo esc_html($l->servico_nome ?? ($l->descricao ?? 'Obrigação financeira')); ?></td>
            <td><span class="tag <?php echo esc_attr(sige_fin_hist_aluno_status_class($l->status ?? '')); ?>"><?php echo esc_html(sige_fin_hist_aluno_status_label($l->status ?? '')); ?></span></td>
            <td class="num"><?php echo esc_html(sige_fin_hist_aluno_money($l->total_previsto_pro ?? 0)); ?></td>
            <td class="num"><?php echo esc_html(sige_fin_hist_aluno_money($l->valor_pago ?? 0)); ?></td>
            <td class="num <?php echo $sld > 0.009 ? 'neg' : 'pos'; ?>"><?php echo esc_html(sige_fin_hist_aluno_money($sld)); ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="7"><div class="empty">Sem lançamentos financeiros localizados para este aluno.</div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      <div class="scroll-hint">Em telemóvel, deslize a tabela para ver total, pago e saldo.</div>
    </div>
  </section>

  <div class="two-col">
    <section class="block" style="margin-top:0">
      <h3>Entradas por método</h3>
      <div class="block-body">
        <table class="grid">
          <thead><tr><th>Método</th><th class="num">Valor</th></tr></thead>
          <tbody>
          <?php if (!empty($data['por_metodo'])): foreach ($data['por_metodo'] as $metodo => $valor): ?>
            <tr><td><?php echo esc_html($metodo); ?></td><td class="num <?php echo ((float)$valor < 0) ? 'neg' : ''; ?>"><?php echo esc_html(sige_fin_hist_aluno_money($valor)); ?></td></tr>
          <?php endforeach; else: ?>
            <tr><td colspan="2"><div class="empty">Sem métodos de pagamento registados.</div></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <section class="block" style="margin-top:0">
      <h3>Próximos acompanhamentos</h3>
      <div class="block-body">
        <table class="grid">
          <thead><tr><th>Vencimento</th><th>Serviço</th><th class="num">Em aberto</th></tr></thead>
          <tbody>
          <?php if (!empty($data['proximos'])): foreach (array_slice($data['proximos'], 0, 8) as $l): ?>
            <tr>
              <td><?php echo esc_html(sige_fin_hist_aluno_date($l->data_vencimento ?? '')); ?></td>
              <td><?php echo esc_html($l->servico_nome ?? ($l->descricao ?? 'Obrigação financeira')); ?></td>
              <td class="num neg"><?php echo esc_html(sige_fin_hist_aluno_money($l->saldo_pro ?? 0)); ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="3"><div class="empty">Sem próximos vencimentos em aberto.</div></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="signs">
    <div class="sign">Tesouraria<small>Nome e assinatura</small></div>
    <div class="sign">Secretaria<small>Nome e assinatura</small></div>
    <div class="sign">Direcção / Carimbo<small>Validação institucional</small></div>
  </div>

  <footer class="foot">
    <div><b>Observação:</b> documento gerado pelo SIGE SoftGenial para consulta interna e entrega controlada. Os valores reflectem os registos existentes no momento da emissão.<?php if (!empty($perfil->rodape_documentos)): ?><br><?php echo esc_html($perfil->rodape_documentos); ?><?php endif; ?></div>
    <div><?php echo esc_html($doc_ref); ?></div>
  </footer>

 </div>
</main>
</body>
</html>
        <?php
    }
}

if (!function_exists('sige_fin_hist_aluno_maybe_handle_print')) {
    function sige_fin_hist_aluno_maybe_handle_print(): void {
        $tipo = isset($_GET['sige_print']) ? sanitize_key((string)wp_unslash($_GET['sige_print'])) : '';
        if ($tipo !== 'historico_financeiro_aluno') return;
        if (!is_user_logged_in()) {
            wp_die('Acesso negado. Por favor, inicie sessão.');
        }
        $aluno_id = isset($_GET['id']) ? absint($_GET['id']) : (isset($_GET['aluno_id']) ? absint($_GET['aluno_id']) : 0);
        if ($aluno_id <= 0) {
            wp_die('Aluno inválido.');
        }
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string)wp_unslash($_GET['_wpnonce'])) : '';
        $is_owner = function_exists('sige_get_aluno_id_do_utilizador') && (int)sige_get_aluno_id_do_utilizador() === $aluno_id;
        $is_real_admin = (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) || (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user());
        // Documento apenas de leitura: a autorização real é por sessão, perfil e escola.
        // Se a ligação trouxer nonce, validamos; se não trouxer, mantemos compatibilidade
        // com os documentos financeiros legados do SIGE que usam GET autenticado.
        if ($nonce !== '' && !$is_owner && !$is_real_admin && !wp_verify_nonce($nonce, 'sige_hist_fin_aluno_' . $aluno_id)) {
            wp_die('Ligação expirada. Volte ao SIGE e tente novamente.');
        }
        if (!sige_fin_hist_aluno_can_download($aluno_id)) {
            wp_die('Sem permissão para baixar o histórico financeiro deste aluno.');
        }
        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $data = sige_fin_hist_aluno_load($aluno_id, $escola_id);
        if (empty($data['ok'])) {
            wp_die(esc_html($data['message'] ?? 'Não foi possível gerar o histórico financeiro.'));
        }
        if (function_exists('sige_audit_log')) {
            sige_audit_log('historico_financeiro_aluno_baixado', [
                'aluno_id' => $aluno_id,
                'formato' => sanitize_key((string)($_GET['formato'] ?? 'html')),
            ], 'financeiro');
        }
        $formato = isset($_GET['formato']) ? sanitize_key((string)wp_unslash($_GET['formato'])) : 'html';
        if (in_array($formato, ['xlsx','excel','csv'], true)) {
            sige_fin_hist_aluno_output_xlsx($data);
            exit;
        }
        sige_fin_hist_aluno_render_html($data);
        exit;
    }
}
add_action('admin_init', 'sige_fin_hist_aluno_maybe_handle_print', 0);
