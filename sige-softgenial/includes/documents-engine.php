<?php
/**
 * SIGE SoftGenial - Documents Engine
 * Ficheiro: includes/documents-engine.php
 * 
 * Motor de geração de documentos: recibos, facturas, extractos.
 * Inclui templates HTML e interceptador de impressões.
 * 
 * @since 10.0
 */
if (!defined('ABSPATH')) exit;
// ============================================================================
// INTERCEPTADOR MESTRE DE IMPRESSÕES
// ============================================================================
function sige_processar_impressoes_universal() {
    if (!isset($_GET['sige_print'])) return;
    // 1. Utilizador tem de estar autenticado
    if (!is_user_logged_in()) {
        wp_die('Acesso negado. Por favor, inicie sessão.');
    }
    $tipo       = sanitize_key($_GET['sige_print']);

    // v12.11.9.25 - validação tenant explícita também em template_redirect,
    // porque links ?sige_print=... podem correr fora de admin_init.
    if (function_exists('sige_phase1_scope_guard_validate_id')) {
        if ($tipo === 'recibo' && isset($_GET['id'])) {
            sige_phase1_scope_guard_validate_id('id', 'sige_fin_pagamentos', (int)$_GET['id']);
        } elseif ($tipo === 'recibo_massa' && isset($_GET['ids'])) {
            $ids_array_guard = function_exists('sige_secure_int_list_from_request') ? sige_secure_int_list_from_request(sanitize_text_field(wp_unslash($_GET['ids'])), 50) : array_filter(array_map('intval', explode(',', sanitize_text_field(wp_unslash($_GET['ids'])))));
            foreach ($ids_array_guard as $pid_guard) {
                sige_phase1_scope_guard_validate_id('ids', 'sige_fin_pagamentos', (int)$pid_guard);
            }
        } elseif (in_array($tipo, ['factura','extracto','historico_financeiro_aluno'], true) && isset($_GET['id'])) {
            sige_phase1_scope_guard_validate_id('id', 'sige_alunos', (int)$_GET['id']);
        }
    }

    // Permissões documentais explícitas: evita que qualquer staff sem função financeira/documental imprima documentos sensíveis.
    $sige_doc_perms_ok = function_exists('sige_user_can_any_secure')
        ? sige_user_can_any_secure(['financeiro.ver','financeiro.dashboard_ver','financeiro.extractos_ver','financeiro.cobrancas_ver','financeiro.relatorio_mensal_ver','documentos.emitir','documentos.reemitir','documentos.emitir_finais'], ['sige_admin','sige_admin_ti','sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao'])
        : ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || sige_utilizador_e_staff());
    // [FIX PERM-01] WP administrators (manage_options) sempre têm acesso a documentos
    // sige_utilizador_e_staff() exclui 'administrator' por design (role WP pura),
    // mas Super Admins precisam ver facturas/extractos para gestão do sistema.
    $e_staff    = $sige_doc_perms_ok;
    $meu_aluno  = sige_get_aluno_id_do_utilizador(); // 0 se não for aluno/encarregado
    // 2. Verificação de propriedade para utilizadores não-staff
    if (!$e_staff) {
        // Apenas encarregados e alunos com sige_aluno_id associado podem continuar
        if (!$meu_aluno) {
            wp_die('Sem permissão para aceder a este documento.');
        }
        switch ($tipo) {
            case 'recibo':
                $id          = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                $dono_aluno  = sige_get_aluno_id_do_pagamento($id);
                if (!$id || $dono_aluno !== $meu_aluno) {
                    wp_die('Sem permissão para ver este recibo.');
                }
                break;
            case 'recibo_massa':
                $ids_raw    = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : '';
                $ids_array  = function_exists('sige_secure_int_list_from_request') ? sige_secure_int_list_from_request($ids_raw, 50) : array_filter(array_map('intval', explode(',', $ids_raw)));
                if (empty($ids_array)) {
                    wp_die('Nenhum pagamento seleccionado.');
                }
                foreach ($ids_array as $pid) {
                    if (sige_get_aluno_id_do_pagamento($pid) !== $meu_aluno) {
                        wp_die('Sem permissão para ver um ou mais recibos seleccionados.');
                    }
                }
                break;
            case 'factura':
            case 'extracto':
            case 'historico_financeiro_aluno':
                $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                if (!$id || $id !== $meu_aluno) {
                    wp_die('Sem permissão para ver este documento.');
                }
                break;
            default:
                wp_die('Tipo de documento desconhecido.');
        }
    }
    // 3. Autorizado - registar no audit log e gerar documento.
    // Alguns fluxos antigos limpam buffers antes de imprimir; rearmamos o
    // guard CSP zero-inline apenas para HTML, nunca para Excel/binários.
    while (ob_get_level()) { ob_end_clean(); }
    $sige_doc_formato = isset($_GET['formato']) ? sanitize_key((string) wp_unslash($_GET['formato'])) : 'html';
    $sige_doc_is_binary = in_array($sige_doc_formato, ['xlsx','excel','csv'], true);
    if (!$sige_doc_is_binary && function_exists('sige_csp_zero_inline_boot')) {
        sige_csp_zero_inline_boot();
    }
    if (!headers_sent()) {
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('X-Content-Type-Options: nosniff', true);
    }
    switch ($tipo) {
        // A) RECIBO DE MASSA (Vários IDs separados por vírgula)
        case 'recibo_massa':
            $ids_raw = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : '';
            $ids_array = function_exists('sige_secure_int_list_from_request') ? sige_secure_int_list_from_request($ids_raw, 50) : array_filter(array_map('intval', explode(',', $ids_raw)));
            if (empty($ids_array)) { wp_die('Nenhum pagamento seleccionado.'); }
            $ids = implode(',', $ids_array);
            sige_audit_log('doc_impresso', ['tipo' => 'recibo_massa', 'ids' => $ids], 'sistema');
            sige_gerar_html_recibo_massa($ids);
            break;
        // B) RECIBO INDIVIDUAL (ID Único -> Converte para Recibo Global Agrupado)
        case 'recibo':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            sige_audit_log('doc_impresso', ['tipo' => 'recibo', 'id' => $id], 'sistema');
            sige_resolver_e_gerar_recibo($id);
            break;
        // C) FACTURA (ID do Aluno)
        case 'factura':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            sige_audit_log('doc_impresso', ['tipo' => 'factura', 'id' => $id], 'sistema');
            sige_gerar_html_factura($id);
            break;
        // D) EXTRACTO (ID do Aluno)
        case 'extracto':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            sige_audit_log('doc_impresso', ['tipo' => 'extracto', 'id' => $id], 'sistema');
            sige_gerar_html_extracto($id);
            break;
        // E) HISTÓRICO FINANCEIRO DO ALUNO PRO
        case 'historico_financeiro_aluno':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (function_exists('sige_fin_hist_aluno_load') && function_exists('sige_fin_hist_aluno_render_html')) {
                if (!function_exists('sige_fin_hist_aluno_can_download') || !sige_fin_hist_aluno_can_download($id)) {
                    wp_die('Sem permissão para baixar o histórico financeiro deste aluno.');
                }
                $hist_escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
                if ($hist_escola_id <= 0) {
                    wp_die('Contexto de escola inválido para baixar o histórico financeiro.', 'SIGE - Segurança', ['response' => 403]);
                }
                $data = sige_fin_hist_aluno_load($id, $hist_escola_id);
                if (empty($data['ok'])) wp_die(esc_html($data['message'] ?? 'Não foi possível gerar o histórico financeiro.'));
                sige_audit_log('doc_impresso', ['tipo' => 'historico_financeiro_aluno', 'id' => $id], 'sistema');
                $formato = isset($_GET['formato']) ? sanitize_key((string)wp_unslash($_GET['formato'])) : 'html';
                if (in_array($formato, ['xlsx','excel','csv'], true) && function_exists('sige_fin_hist_aluno_output_xlsx')) {
                    sige_fin_hist_aluno_output_xlsx($data);
                } else {
                    sige_fin_hist_aluno_render_html($data);
                }
                break;
            }
            wp_die('Histórico financeiro indisponível.');
        default:
            wp_die('Tipo de documento desconhecido.');
    }
    exit;
}
// ==========================================
// 3.A) MOTOR: RESOLVER RECIBO POR ID (PONTE INTELIGENTE)
// ==========================================
function sige_resolver_e_gerar_recibo($pagamento_id) {
    global $wpdb;
    if (!$pagamento_id) wp_die("ID de pagamento inválido.");
    // Buscar recibo_numero E aluno_id em conjunto - evita mostrar aluno errado
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT recibo_numero, aluno_id, escola_id FROM {$wpdb->prefix}sige_fin_pagamentos WHERE id = %d AND escola_id = %d",
        $pagamento_id, sige_get_escola_id()
    ));
    if (!$row || !$row->recibo_numero) wp_die("Recibo não encontrado para o pagamento #$pagamento_id.");
    // Passar aluno_id ao renderer - filtra SQL directamente por este aluno
    sige_gerar_html_recibo_agrupado($row->recibo_numero, (int)$row->aluno_id, (int)$row->escola_id);
}
// ==========================================
// 3.B) MOTOR DE RECIBO AGRUPADO (COM DETALHES DE TRANSPORTE)
// SUBSTITUIR NO CÓDIGO ORIGINAL
// ==========================================
function sige_gerar_html_recibo_agrupado($recibo_numero, int $aluno_id_filtro = 0, int $escola_id_contexto = 0) {
    // $aluno_id_filtro > 0: link público - só mostrar itens deste aluno
    // $aluno_id_filtro = 0: admin/impressão interna - mostrar todos (FAM-, PLN-)
    // $escola_id_contexto > 0: recibo público/WhatsApp resolve escola sem fallback global.
    global $wpdb;
    $eid = absint($escola_id_contexto);
    if ($eid <= 0 && function_exists('sige_get_escola_id')) {
        $eid = (int)sige_get_escola_id();
    }
    if ($eid <= 0) {
        wp_die('Contexto de escola inválido para emissão do recibo.', 'SIGE - Segurança', ['response' => 403]);
    }
    $moeda_recibo = 'MT';
    $cfg_moeda_t = $wpdb->prefix . 'sige_fin_configuracoes';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $cfg_moeda_t)) === $cfg_moeda_t) {
        $ano_cfg = function_exists('sige_ano_lectivo_atual') ? (int)sige_ano_lectivo_atual() : (int)wp_date('Y');
        $moeda_bd = $wpdb->get_var($wpdb->prepare("SELECT moeda_simbolo FROM {$cfg_moeda_t} WHERE escola_id=%d AND ano_letivo=%d LIMIT 1", $eid, $ano_cfg));
        if (!empty($moeda_bd)) $moeda_recibo = (string)$moeda_bd;
    }
    $tabela_inscricoes = $wpdb->prefix . 'sige_transporte_inscricoes';
    $tabela_rotas      = $wpdb->prefix . 'sige_transporte_rotas';
    // Buscar pagamentos do recibo + dados do lançamento (incluindo extras/desconto/multa)
    if ($wpdb->get_var("SHOW TABLES LIKE '{$tabela_inscricoes}'") == $tabela_inscricoes) {
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.*,
                l.id as lancamento_id,
                l.descricao as conceito,
                l.mes_referencia,
                l.valor_original,
                l.valor_transporte,
                l.valor_extras,
                l.valor_desconto,
                l.valor_desconto_especial, l.motivo_desconto_especial,
                l.valor_multa,
                a.nome_completo, a.numero_processo, a.id as aluno_id,
                u.display_name as recebedor,
                t.nome as turma_nome, t.classe,
                r.nome_rota, r.preco_mensal as rota_preco
            FROM {$wpdb->prefix}sige_fin_pagamentos p
            LEFT JOIN {$wpdb->prefix}sige_alunos a ON p.aluno_id = a.id
            LEFT JOIN {$wpdb->prefix}sige_fin_lancamentos l ON p.lancamento_id = l.id
            LEFT JOIN {$wpdb->prefix}users u ON p.recebido_por = u.ID
            LEFT JOIN {$wpdb->prefix}sige_matriculas m ON a.id = m.aluno_id
            LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id
            LEFT JOIN {$tabela_inscricoes} ti ON (a.id = ti.aluno_id AND ti.activo = 1)
            LEFT JOIN {$tabela_rotas} r ON ti.rota_id = r.id
            WHERE p.recibo_numero = %s AND p.escola_id = %d
              AND ($aluno_id_filtro <= 0 OR p.aluno_id = $aluno_id_filtro)
            ORDER BY p.data_pagamento ASC
        ", $recibo_numero, $eid));
    } else {
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.*,
                l.id as lancamento_id,
                l.descricao as conceito,
                l.mes_referencia,
                l.valor_original,
                l.valor_transporte,
                l.valor_extras,
                l.valor_desconto,
                l.valor_desconto_especial, l.motivo_desconto_especial,
                l.valor_multa,
                a.nome_completo, a.numero_processo, a.id as aluno_id,
                u.display_name as recebedor,
                t.nome as turma_nome, t.classe,
                NULL as nome_rota, NULL as rota_preco
            FROM {$wpdb->prefix}sige_fin_pagamentos p
            LEFT JOIN {$wpdb->prefix}sige_alunos a ON p.aluno_id = a.id
            LEFT JOIN {$wpdb->prefix}sige_fin_lancamentos l ON p.lancamento_id = l.id
            LEFT JOIN {$wpdb->prefix}users u ON p.recebido_por = u.ID
            LEFT JOIN {$wpdb->prefix}sige_matriculas m ON a.id = m.aluno_id
            LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id
            WHERE p.recibo_numero = %s AND p.escola_id = %d
              AND ($aluno_id_filtro <= 0 OR p.aluno_id = $aluno_id_filtro)
            ORDER BY p.data_pagamento ASC
        ", $recibo_numero, $eid));
    }
    if (empty($rows) && $aluno_id_filtro > 0) {
        wp_die('Recibo não encontrado para este aluno.', 'Não Encontrado', ['response' => 404]);
    }
    if (empty($rows)) wp_die("Recibo #".esc_html($recibo_numero)." sem itens.");

    // [FIX RECIBO-02] Filtro por aluno_id já aplicado no SQL acima quando
    // $aluno_id_filtro > 0 (link público). Para admin/impressão interna
    // ($aluno_id_filtro = 0) mostra todos os itens do recibo (FAM-, PLN-).

    $head   = $rows[0];
    $_eid = $eid;
    $perfil = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $_eid));
    $logo   = !empty($perfil->logo_documentos_url) ? $perfil->logo_documentos_url : ($perfil->logo_sistema_url ?? '');
    $turma_lbl = (!empty($head->classe)) ? esc_html("{$head->classe} - {$head->turma_nome}") : "---";
    // [v12.15.20] Recibo respeita a data efectiva: mostra-a como data do pagamento
    // (igual ao extracto/KPI) e, quando difere, acrescenta a data de registo
    // (auditoria) em pequeno. Nao altera valores nem saldos; e so exibicao.
    $__sige_rec_de = function_exists('sige_fin_recibo_data_efectiva_dia')
        ? sige_fin_recibo_data_efectiva_dia($head->data_pagamento, $head->data_efectiva ?? null)
        : ['dia' => substr((string) $head->data_pagamento, 0, 10), 'difere' => false];
    if ($__sige_rec_de['difere']) {
        $data_fmt = esc_html(sige_doc_fin_date($__sige_rec_de['dia'], false))
            . '<br><small>(registado em ' . esc_html(sige_doc_fin_date($head->data_pagamento, true)) . ')</small>';
    } else {
        $data_fmt = esc_html(sige_doc_fin_date($head->data_pagamento, true));
    }
    // Agrupar por lançamento (para “itens pagos individualmente”, evitando duplicações)
    $itens = [];
    foreach ($rows as $r) {
        $key = !empty($r->lancamento_id) ? ('L' . (int)$r->lancamento_id) : ('P' . (int)$r->id);
        if (!isset($itens[$key])) {
            $itens[$key] = [
                'lancamento_id' => !empty($r->lancamento_id) ? (int)$r->lancamento_id : 0,
                'conceito'      => $r->conceito ?: ('Pagamento #' . (int)$r->id),
                'mes_referencia'=> $r->mes_referencia ?: '-',
                'valor_original'=> (float)($r->valor_original ?? 0),
                'valor_transporte'=> (float)($r->valor_transporte ?? 0),
                'valor_extras'  => (float)($r->valor_extras ?? 0),
                'valor_desconto'=> (float)($r->valor_desconto ?? 0),
                'valor_desconto_especial'=> (float)($r->valor_desconto_especial ?? 0),
                'motivo_desconto_especial'=> ($r->motivo_desconto_especial ?? ''),
                'valor_multa'   => (float)($r->valor_multa ?? 0),
                'pago_no_recibo'=> 0.0,
                'observacoes'   => $r->observacoes ?? '',
                'nome_rota'     => $r->nome_rota ?? '',
            ];
        }
        $itens[$key]['pago_no_recibo'] += (float)($r->valor_pago ?? 0);
        if (!empty($r->observacoes) && empty($itens[$key]['observacoes'])) {
            $itens[$key]['observacoes'] = $r->observacoes;
        }
    }
    ob_start();
    echo '<table class="items">
            <thead>
                <tr>
                    <th>DESCRIÇÃO</th>
                    <th style="text-align:center">MÊS REF.</th>
                    <th style="text-align:right">PAGO</th>
                    <th style="text-align:right">SALDO</th>
                </tr>
            </thead>
            <tbody>';
    $total_recibo = 0.00;
    $saldo_devedor_total = 0.00;
    $tem_divida_restante = false;
    foreach ($itens as $item) {
        $pago_no_recibo = (float)$item['pago_no_recibo'];
        $total_recibo  += $pago_no_recibo;
        $saldo_item = 0.0;
        $total_divida = 0.0;
        $total_pago_lanc = 0.0;
        if (!empty($item['lancamento_id'])) {
            // Total devido do lançamento (FÓRMULA CORRECTA, com EXTRAS)
            $total_divida = (
                (float)$item['valor_original']
                + (float)$item['valor_transporte']
                + (float)$item['valor_extras']
                + (float)$item['valor_multa']
            ) - (float)$item['valor_desconto'] - (float)($item['valor_desconto_especial'] ?? 0);
            // Total já pago no lançamento (histórico)
            $total_pago_lanc = (float)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(valor_pago),0) FROM {$wpdb->prefix}sige_fin_pagamentos WHERE lancamento_id=%d AND escola_id=%d",
                (int)$item['lancamento_id'], $eid
            ));
            $saldo_item = max(0.0, $total_divida - $total_pago_lanc);
            if ($saldo_item > 1.00) {
                $tem_divida_restante = true;
                $saldo_devedor_total += $saldo_item;
            }
        }
        echo '<tr>';
        echo '<td><strong>'.esc_html($item['conceito']).'</strong>';
        // Transporte
        if ((float)$item['valor_transporte'] > 0) {
            $info_transporte = 'Transporte: ' . number_format((float)$item['valor_transporte'], 2) . ' ' . $moeda_recibo;
            if (!empty($item['nome_rota'])) $info_transporte .= ' (Rota: ' . esc_html($item['nome_rota']) . ')';
            echo '<br><small style="color:#1976d2; font-weight:600;">🚌 ' . $info_transporte . '</small>';
        }
        // Extras
        if ((float)$item['valor_extras'] > 0) {
            echo '<br><small style="color:#0f766e; font-weight:600;">➕ Extras: ' . number_format((float)$item['valor_extras'], 2) . ' ' . $moeda_recibo . '</small>';
        }
        // Desconto / Multa
        if ((float)$item['valor_desconto'] > 0) {
            echo '<br><small style="color:#2e7d32; font-weight:700;">➖ Desconto: ' . number_format((float)$item['valor_desconto'], 2) . ' ' . $moeda_recibo . '</small>';
        }
        if ((float)($item['valor_desconto_especial'] ?? 0) > 0) {
            echo '<br><small style="color:var(--sg-doc-primary-700); font-weight:700;">⭐ Desconto especial: ' . number_format((float)$item['valor_desconto_especial'], 2) . ' ' . $moeda_recibo . '</small>';
            if (!empty($item['motivo_desconto_especial'])) {
                echo '<br><small style="color:var(--sg-doc-primary-700); font-style:italic;">Motivo: ' . esc_html($item['motivo_desconto_especial']) . '</small>';
            }
        }
        if ((float)$item['valor_multa'] > 0) {
            echo '<br><small style="color:#c62828; font-weight:700;">⚠️ Multa: ' . number_format((float)$item['valor_multa'], 2) . ' ' . $moeda_recibo . '</small>';
        }
        // Histórico rápido
        if (!empty($item['lancamento_id'])) {
            echo '<br><small style="color:#666;">Resumo: pago neste recibo '
                . number_format($pago_no_recibo, 2)
                . ' ' . $moeda_recibo . ' • pago acumulado no item '
                . number_format($total_pago_lanc, 2)
                . ' ' . $moeda_recibo . ' • valor total do item '
                . number_format($total_divida, 2)
                . ' ' . $moeda_recibo . '</small>';
        }
        if (!empty($item['observacoes'])) {
            echo '<br><small style="color:#666;">'.esc_html($item['observacoes']).'</small>';
        }
        echo '</td>';
        echo '<td style="text-align:center">'.esc_html($item['mes_referencia']).'</td>';
        echo '<td style="text-align:right; font-weight:bold;">'.number_format($pago_no_recibo, 2).' ' . $moeda_recibo . '</td>';
        if (!empty($item['lancamento_id'])) {
            echo '<td style="text-align:right; font-weight:bold; color:' . ($saldo_item > 1 ? '#c62828' : '#166534') . ';">'
                . number_format($saldo_item, 2) . ' ' . $moeda_recibo . '</td>';
        } else {
            echo '<td style="text-align:right; color:#64748b;">-</td>';
        }
        echo '</tr>';
    }
    if ($tem_divida_restante) {
        echo '<tr style="color:#c62828; background:#fffde7;">
                <td colspan="3"><strong>⚠️ SALDO POR REGULARIZAR</strong></td>
                <td style="text-align:right; font-weight:bold;">'.number_format($saldo_devedor_total, 2).' ' . $moeda_recibo . '</td>
              </tr>';
    }
    echo '<tr class="total">
            <td colspan="3">TOTAL RECEBIDO</td>
            <td style="text-align:right">'.number_format($total_recibo, 2).' ' . $moeda_recibo . '</td>
          </tr>';
    echo '</tbody></table>';
    echo '<div style="margin-top:15px; font-size:12px; color:#555; border-top:1px dashed #ccc; padding-top:12px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
            <div><strong>MÉTODO DE PAGAMENTO:</strong> '.strtoupper(function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($head->metodo_pagamento) : $head->metodo_pagamento).'</div>
            <div><strong>DATA DO PAGAMENTO:</strong> '.$data_fmt.'</div>
            <div><strong>ATENDIDO POR:</strong> '.esc_html($head->recebedor ?: 'Sistema').'</div>
          </div>';
    $conteudo_recibo = ob_get_clean();
    sige_print_recibo_duplo('RECIBO DE PAGAMENTO', $recibo_numero, $head->nome_completo, $head->numero_processo, $turma_lbl, $logo, $perfil, $conteudo_recibo, 'SIGE:'.$recibo_numero.'|'.$total_recibo, 'TESOURARIA');
}
// ==========================================
// 3.C) MOTOR DE RECIBO DE MASSA (CONSOLIDADO MULTI-RECIBOS)
// ==========================================
function sige_gerar_html_recibo_massa($ids_string) {
    global $wpdb;
    $ano_atual = wp_date('Y');
    $ids_array = array_map('intval', explode(',', $ids_string));
    $ids_array = array_filter($ids_array);
    if (empty($ids_array)) wp_die("Nenhum pagamento seleccionado.");
    $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    if ($escola_id <= 0) {
        wp_die('Contexto de escola inválido para emitir recibo consolidado.', 'SIGE - Segurança', ['response' => 403]);
    }
    // Construção segura de placeholders para IN() - um %d por ID
    $placeholders = implode(',', array_fill(0, count($ids_array), '%d'));
    $rows = $wpdb->get_results(
        $wpdb->prepare("
            SELECT
                p.*,
                l.id as lancamento_id,
                l.descricao as conceito,
                l.mes_referencia,
                l.valor_original,
                l.valor_transporte,
                l.valor_extras,
                l.valor_desconto,
                l.valor_desconto_especial, l.motivo_desconto_especial,
                l.valor_multa,
                a.nome_completo, a.numero_processo,
                u.display_name as recebedor,
                t.nome as turma_nome, t.classe
            FROM {$wpdb->prefix}sige_fin_pagamentos p
            LEFT JOIN {$wpdb->prefix}sige_alunos a ON p.aluno_id = a.id
            LEFT JOIN {$wpdb->prefix}sige_fin_lancamentos l ON p.lancamento_id = l.id
            LEFT JOIN {$wpdb->prefix}users u ON p.recebido_por = u.ID
            LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id AND m.ano_lectivo = %d)
            LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id
            WHERE p.id IN ($placeholders) AND p.escola_id = %d
            ORDER BY p.data_pagamento ASC
        ", array_merge([(int)$ano_atual], $ids_array, [$escola_id]))
    );
    if (empty($rows)) wp_die("Itens não encontrados.");
    $head   = $rows[0];
    $_eid = $escola_id;
    $perfil = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $_eid));
    $logo   = !empty($perfil->logo_documentos_url) ? $perfil->logo_documentos_url : ($perfil->logo_sistema_url ?? '');
    $turma_lbl = (!empty($head->classe)) ? esc_html("{$head->classe} - {$head->turma_nome}") : "---";
    $ref_consolidada = 'CONS-' . wp_date('ymd') . '-' . count($rows);
    ob_start();
    echo '<table class="items">
            <thead>
                <tr>
                    <th>DATA</th>
                    <th>RECIBO Nº</th>
                    <th>DESCRIÇÃO</th>
                    <th style="text-align:right">PAGO</th>
                    <th style="text-align:right">SALDO</th>
                </tr>
            </thead>
            <tbody>';
    $total_geral = 0.00;
    foreach ($rows as $r) {
        $pago = (float)($r->valor_pago ?? 0);
        $total_geral += $pago;
        $saldo_item = 0.0;
        if (!empty($r->lancamento_id)) {
            $total_divida = (
                (float)($r->valor_original ?? 0)
                + (float)($r->valor_transporte ?? 0)
                + (float)($r->valor_extras ?? 0)
                + (float)($r->valor_multa ?? 0)
            ) - (float)($r->valor_desconto ?? 0) - (float)($r->valor_desconto_especial ?? 0);
            $total_pago_lanc = (float)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(valor_pago),0) FROM {$wpdb->prefix}sige_fin_pagamentos WHERE lancamento_id=%d AND escola_id=%d",
                (int)$r->lancamento_id, sige_get_escola_id()
            ));
            $saldo_item = max(0.0, $total_divida - $total_pago_lanc);
        }
        echo '<tr>';
        // [v12.15.20] Consolidado tambem respeita a data efectiva por linha.
        $__r_de = function_exists('sige_fin_recibo_data_efectiva_dia')
            ? sige_fin_recibo_data_efectiva_dia($r->data_pagamento, $r->data_efectiva ?? null)
            : ['dia' => substr((string) $r->data_pagamento, 0, 10), 'difere' => false];
        echo '<td>' . esc_html(sige_doc_fin_date($__r_de['dia'], false))
            . ($__r_de['difere'] ? ' <sup title="Data efectiva. Registado em ' . esc_attr(sige_doc_fin_date($r->data_pagamento, true)) . '">ef.</sup>' : '')
            . '</td>';
        echo '<td>'.esc_html($r->recibo_numero).'</td>';
        echo '<td>'.esc_html($r->conceito ?: 'Pagamento').'</td>';
        echo '<td style="text-align:right; font-weight:bold;">'.number_format($pago, 2).' ' . sige_moeda() . '</td>';
        echo '<td style="text-align:right; font-weight:bold; color:' . ($saldo_item > 1 ? '#c62828' : '#166534') . ';">'
            . (!empty($r->lancamento_id) ? number_format($saldo_item, 2) . ' ' . sige_moeda() : '-') . '</td>';
        echo '</tr>';
    }
    echo '<tr class="total">
            <td colspan="4">TOTAL ACUMULADO</td>
            <td style="text-align:right">'.number_format($total_geral, 2).' ' . sige_moeda() . '</td>
          </tr>';
    echo '</tbody></table>';
    $conteudo_recibo = ob_get_clean();
    sige_print_recibo_duplo('RECIBO CONSOLIDADO', $ref_consolidada, $head->nome_completo, $head->numero_processo, $turma_lbl, $logo, $perfil, $conteudo_recibo, 'REF:'.$ref_consolidada.'|QTD:'.count($rows), 'TESOURARIA (CONSOLIDADO)');
}
// ==========================================
// 3.D) MOTOR DE FACTURA (DEVEDORES)
// ==========================================
if (!function_exists('sige_doc_fin_moeda')) {
    function sige_doc_fin_moeda(): string {
        return function_exists('sige_moeda') ? (string)sige_moeda() : 'MT';
    }
}

if (!function_exists('sige_doc_fin_fmt')) {
    function sige_doc_fin_fmt($valor): string {
        return number_format((float)$valor, 2, ',', '.') . ' ' . sige_doc_fin_moeda();
    }
}

if (!function_exists('sige_doc_fin_date')) {
    function sige_doc_fin_date($value, bool $with_time = false): string {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return '-';
        $ts = strtotime((string)$value);
        if (!$ts) return '-';
        return wp_date($with_time ? 'd/m/Y H:i' : 'd/m/Y', $ts);
    }
}

if (!function_exists('sige_doc_fin_status_label')) {
    function sige_doc_fin_status_label($status): string {
        $s = strtolower(trim((string)$status));
        $map = [
            'pendente' => 'Pendente',
            'parcial' => 'Parcial',
            'em_plano' => 'Em plano',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
            'anulado' => 'Anulado',
            'isento' => 'Isento',
        ];
        return $map[$s] ?? ($s !== '' ? ucfirst(str_replace('_', ' ', $s)) : 'Pendente');
    }
}

if (!function_exists('sige_doc_fin_lancamento_breakdown')) {
    /**
     * Decomposicao documental de um lancamento financeiro.
     *
     * A regra de saldo e total bruto deve vir da fonte canonica do financeiro
     * quando disponivel. O helper apenas organiza os componentes para leitura
     * do encarregado e nao altera lancamentos, pagamentos nem status.
     *
     * @param object|array $lancamento Linha de sige_fin_lancamentos.
     * @return array<string,float>
     */
    function sige_doc_fin_lancamento_breakdown($lancamento): array {
        $l = (object)$lancamento;
        $multa = (float)($l->valor_multa_cobrada ?? 0) > 0
            ? (float)$l->valor_multa_cobrada
            : (float)($l->valor_multa ?? 0);

        $base = (float)($l->valor_original ?? 0);
        $transporte = (float)($l->valor_transporte ?? 0);
        $extras = (float)($l->valor_extras ?? 0);
        $desconto = (float)($l->valor_desconto ?? 0);
        $desconto_especial = (float)($l->valor_desconto_especial ?? 0);
        $pago = (float)($l->valor_pago ?? 0);

        $total_lancado = function_exists('sige_fin_total_lancamento')
            ? (float)sige_fin_total_lancamento($l)
            : max(0.0, $base + $transporte + $extras + $multa - $desconto - $desconto_especial);
        $saldo = function_exists('sige_fin_saldo_lancamento')
            ? (float)sige_fin_saldo_lancamento($l)
            : max(0.0, $total_lancado - $pago);

        return [
            'base' => $base,
            'transporte' => $transporte,
            'extras' => $extras,
            'multa' => $multa,
            'desconto' => $desconto,
            'desconto_especial' => $desconto_especial,
            'descontos_total' => $desconto + $desconto_especial,
            'total_lancado' => max(0.0, $total_lancado),
            'pago' => max(0.0, $pago),
            'saldo' => max(0.0, $saldo),
        ];
    }
}

function sige_gerar_html_factura($aluno_id) {
    global $wpdb;

    $aluno_id = (int)$aluno_id;
    if ($aluno_id <= 0) wp_die('ID de aluno inválido.');

    $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    if ($escola_id <= 0) {
        wp_die('Contexto de escola inválido para emitir o extracto de dívida.', 'SIGE - Segurança', ['response' => 403]);
    }

    $ano_atual = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (int)wp_date('Y');

    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d",
        $aluno_id,
        $escola_id
    ));
    if (!$aluno) wp_die('Aluno não encontrado.');

    $turma = $wpdb->get_row($wpdb->prepare("
        SELECT t.nome as turma_nome, t.classe
        FROM {$wpdb->prefix}sige_matriculas m
        LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = m.escola_id
        WHERE m.aluno_id = %d AND m.ano_lectivo = %d AND m.escola_id = %d
        ORDER BY m.id DESC
        LIMIT 1
    ", $aluno_id, $ano_atual, $escola_id));
    $turma_label = $turma ? trim((string)($turma->classe ?? '') . ' - ' . (string)($turma->turma_nome ?? ''), ' -') : '---';
    if ($turma_label === '') $turma_label = '---';

    $dividas = $wpdb->get_results($wpdb->prepare("
        SELECT l.*, COALESCE(NULLIF(s.nome,''), NULLIF(l.descricao,''), 'Serviço não identificado') AS servico_nome
        FROM {$wpdb->prefix}sige_fin_lancamentos l
        LEFT JOIN {$wpdb->prefix}sige_fin_servicos s ON s.id = l.servico_id AND s.escola_id = l.escola_id
        WHERE l.aluno_id=%d AND l.escola_id=%d AND l.status IN ('pendente','parcial','em_plano')
        ORDER BY l.data_vencimento ASC, l.id ASC
    ", $aluno_id, $escola_id));

    $pagamentos_por_lancamento = [];
    $lancamento_ids = [];
    foreach ((array)$dividas as $d) {
        $id_lanc = (int)($d->id ?? 0);
        if ($id_lanc > 0) $lancamento_ids[$id_lanc] = $id_lanc;
    }
    if (!empty($lancamento_ids)) {
        $placeholders = implode(',', array_fill(0, count($lancamento_ids), '%d'));
        $rows_pag = $wpdb->get_results($wpdb->prepare("
            SELECT lancamento_id,
                   COALESCE(SUM(valor_pago),0) AS total_pago,
                   MAX(data_pagamento) AS ultimo_pagamento,
                   GROUP_CONCAT(DISTINCT NULLIF(recibo_numero,'') ORDER BY data_pagamento ASC SEPARATOR ', ') AS recibos
            FROM {$wpdb->prefix}sige_fin_pagamentos
            WHERE escola_id = %d
              AND aluno_id = %d
              AND lancamento_id IN ($placeholders)
            GROUP BY lancamento_id
        ", array_merge([$escola_id, $aluno_id], array_values($lancamento_ids))));
        foreach ((array)$rows_pag as $pg) {
            $pagamentos_por_lancamento[(int)$pg->lancamento_id] = $pg;
        }
    }

    $perfil = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $escola_id));
    if (!$perfil) {
        $perfil = (object)[
            'nome_escola' => get_bloginfo('name'),
            'endereco_escola' => '',
            'nuit' => '',
            'telefone_oficial' => '',
            'logo_documentos_url' => '',
            'logo_sistema_url' => '',
            'banco_nome' => '',
            'banco_conta' => '',
            'banco_nib' => '',
        ];
    }
    $logo = !empty($perfil->logo_documentos_url) ? $perfil->logo_documentos_url : ($perfil->logo_sistema_url ?? '');

    $itens = [];
    $total_lancado = 0.0;
    $total_pago = 0.0;
    $total_devido = 0.0;
    $total_vencido = 0.0;
    $total_em_plano = 0.0;
    $proximo_vencimento = null;
    $hoje_ts = strtotime(wp_date('Y-m-d')) ?: time();

    foreach ((array)$dividas as $d) {
        $bd = sige_doc_fin_lancamento_breakdown($d);
        if ($bd['saldo'] <= 0.005) continue;

        $id_lanc = (int)($d->id ?? 0);
        $pg = $pagamentos_por_lancamento[$id_lanc] ?? null;
        if ($pg) {
            $bd['pago'] = max($bd['pago'], max(0.0, (float)($pg->total_pago ?? 0)));
            $bd['saldo'] = max(0.0, $bd['total_lancado'] - $bd['pago']);
            if ($bd['saldo'] <= 0.005) continue;
        }

        $status = strtolower(trim((string)($d->status ?? '')));
        $venc_ts = strtotime((string)($d->data_vencimento ?? '')) ?: 0;
        $is_vencido = ($venc_ts > 0 && $venc_ts < $hoje_ts && $status !== 'em_plano');
        if ($is_vencido) $total_vencido += $bd['saldo'];
        if ($status === 'em_plano') $total_em_plano += $bd['saldo'];
        if ($venc_ts > 0 && ($proximo_vencimento === null || $venc_ts < (int)$proximo_vencimento['ts'])) {
            $proximo_vencimento = ['ts' => $venc_ts, 'label' => sige_doc_fin_date($d->data_vencimento)];
        }

        $total_lancado += $bd['total_lancado'];
        $total_pago += $bd['pago'];
        $total_devido += $bd['saldo'];
        $itens[] = ['row' => $d, 'breakdown' => $bd, 'pagamentos' => $pg, 'vencido' => $is_vencido];
    }

    $doc_ref = 'DIV-' . str_pad((string)$aluno_id, 5, '0', STR_PAD_LEFT) . '-' . wp_date('Ymd-His');
    sige_print_template_header('EXTRATO DE DÍVIDA DETALHADO', $doc_ref, $aluno->nome_completo, $aluno->numero_processo, $turma_label, $logo, $perfil);

    echo '<p><strong>NOTIFICAÇÃO DE PAGAMENTO PENDENTE:</strong> valores calculados até ao momento da emissão.</p>';

    echo '<table class="items"><thead><tr><th>Resumo</th><th>Valor</th><th>Resumo</th><th>Valor</th></tr></thead><tbody>';
    echo '<tr><td>Total em aberto</td><td><strong>'.esc_html(sige_doc_fin_fmt($total_devido)).'</strong></td><td>Total vencido</td><td>'.esc_html(sige_doc_fin_fmt($total_vencido)).'</td></tr>';
    echo '<tr><td>Em plano</td><td>'.esc_html(sige_doc_fin_fmt($total_em_plano)).'</td><td>Já pago nos itens</td><td>'.esc_html(sige_doc_fin_fmt($total_pago)).'</td></tr>';
    echo '<tr><td>Itens em aberto</td><td>'.esc_html((string)count($itens)).'</td><td>Próximo vencimento</td><td>'.esc_html($proximo_vencimento['label'] ?? '-').'</td></tr>';
    echo '</tbody></table>';

    echo '<p><strong>Como ler:</strong> cada item mostra valor lançado, abatimentos, pagamentos já registados e saldo final em aberto.</p>';

    if (empty($itens)) {
        echo '<p><strong>Não existem dívidas pendentes para este aluno no momento da emissão.</strong></p>';
    } else {
        echo '<table class="items">';
        echo '<thead><tr><th>Vencimento</th><th>Serviço / referência</th><th>Estado</th><th>Total lançado</th><th>Já pago</th><th>Saldo</th></tr></thead><tbody>';
        foreach ($itens as $item) {
            $d = $item['row'];
            $bd = $item['breakdown'];
            $pg = $item['pagamentos'];
            $servico = trim((string)($d->servico_nome ?? $d->descricao ?? 'Serviço não identificado')) ?: 'Serviço não identificado';
            $ref = trim((string)($d->mes_referencia ?? ''));
            $detalhes = [];
            $detalhes[] = 'Base/Propina: ' . sige_doc_fin_fmt($bd['base']);
            if ($bd['transporte'] > 0.005) $detalhes[] = 'Transporte: ' . sige_doc_fin_fmt($bd['transporte']);
            if ($bd['extras'] > 0.005) $detalhes[] = 'Extras: ' . sige_doc_fin_fmt($bd['extras']);
            if ($bd['multa'] > 0.005) $detalhes[] = 'Multa: ' . sige_doc_fin_fmt($bd['multa']);
            if ($bd['desconto'] > 0.005) $detalhes[] = 'Desconto: ' . sige_doc_fin_fmt($bd['desconto']);
            if ($bd['desconto_especial'] > 0.005) $detalhes[] = 'Desconto especial: ' . sige_doc_fin_fmt($bd['desconto_especial']);
            if (!empty($d->motivo_desconto_especial) && $bd['desconto_especial'] > 0.005) $detalhes[] = 'Motivo do desconto especial: ' . wp_strip_all_tags((string)$d->motivo_desconto_especial);
            if ($pg && !empty($pg->recibos)) $detalhes[] = 'Recibos abatidos: ' . wp_strip_all_tags((string)$pg->recibos);
            if ($pg && !empty($pg->ultimo_pagamento)) $detalhes[] = 'Último pagamento: ' . sige_doc_fin_date($pg->ultimo_pagamento, true);
            if (!empty($item['vencido'])) $detalhes[] = 'Situação: vencido';

            echo '<tr>';
            echo '<td>'.esc_html(sige_doc_fin_date($d->data_vencimento)).'</td>';
            echo '<td><strong>'.esc_html($servico).'</strong>'.($ref !== '' ? '<br><small>Ref: '.esc_html($ref).'</small>' : '').'</td>';
            echo '<td>'.esc_html(sige_doc_fin_status_label($d->status ?? '')).'</td>';
            echo '<td>'.esc_html(sige_doc_fin_fmt($bd['total_lancado'])).'</td>';
            echo '<td>'.esc_html(sige_doc_fin_fmt($bd['pago'])).'</td>';
            echo '<td><strong>'.esc_html(sige_doc_fin_fmt($bd['saldo'])).'</strong></td>';
            echo '</tr>';
            echo '<tr><td colspan="6"><small>'.esc_html(implode(' | ', $detalhes)).'</small></td></tr>';
        }
        echo '<tr class="total"><td colspan="3">TOTAL A PAGAR</td><td>'.esc_html(sige_doc_fin_fmt($total_lancado)).'</td><td>'.esc_html(sige_doc_fin_fmt($total_pago)).'</td><td>'.esc_html(sige_doc_fin_fmt($total_devido)).'</td></tr>';
        echo '</tbody></table>';
    }

    echo '<table class="items"><thead><tr><th>Dados bancários</th><th>Observações ao encarregado</th></tr></thead><tbody><tr>';
    echo '<td>Banco: '.esc_html($perfil->banco_nome ?? '---').'<br>Conta: '.esc_html($perfil->banco_conta ?? '---').'<br>NIB: '.esc_html($perfil->banco_nib ?? '---').'<br><em>Por favor, envie o comprovativo para a secretaria/tesouraria.</em></td>';
    echo '<td>Pagamentos feitos depois da emissão podem alterar o saldo. Se já pagou algum item aqui listado, apresente o recibo ou comprovativo para reconciliação. Valores em plano representam compromisso financeiro negociado e continuam visíveis até regularização.</td>';
    echo '</tr></tbody></table>';

    sige_print_template_footer('DIVDET:'.$aluno_id.'|'.number_format($total_devido, 2, '.', '').'|'.$doc_ref, 'TESOURARIA / SECRETARIA');
}
// ==========================================
// 3.E) MOTOR DE EXTRACTO (HISTÓRICO)
// ==========================================
function sige_gerar_html_extracto($aluno_id) {
    global $wpdb;

    $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    if ($escola_id <= 0) {
        wp_die('Contexto de escola inválido para emitir o histórico financeiro.', 'SIGE - Segurança', ['response' => 403]);
    }
    $aluno_id  = (int)$aluno_id;
    if ($aluno_id <= 0) wp_die('ID de aluno inválido.');

    $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
    $ano_atual = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (int)wp_date('Y');

    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d",
        $aluno_id,
        $escola_id
    ));
    if (!$aluno) wp_die('Aluno não encontrado.');

    // Blindagem multi-tenant: algumas escolas têm o estado da matrícula em 'status_matricula',
    // outras em 'status' e outras não têm a coluna. Detecta e adapta para evitar
    // "Unknown column 'm.status'" sem alterar qualquer regra de cálculo.
    $tbl_mat = $wpdb->prefix . 'sige_matriculas';
    $mat_col = '';
    if (function_exists('sige_db_column_exists')) {
        if (sige_db_column_exists($tbl_mat, 'status_matricula')) $mat_col = 'm.status_matricula';
        elseif (sige_db_column_exists($tbl_mat, 'status')) $mat_col = 'm.status';
    } elseif (function_exists('sige_fin_hist_aluno_column_exists')) {
        if (sige_fin_hist_aluno_column_exists($tbl_mat, 'status_matricula')) $mat_col = 'm.status_matricula';
        elseif (sige_fin_hist_aluno_column_exists($tbl_mat, 'status')) $mat_col = 'm.status';
    }
    $mat_status_sel = $mat_col !== '' ? $mat_col : "''";
    $mat_status_ord = $mat_col !== ''
        ? "CASE WHEN LOWER(COALESCE({$mat_col},'')) IN ('activa','ativo','activo') THEN 0 ELSE 1 END,"
        : "";

    $turma = $wpdb->get_row($wpdb->prepare(
        "SELECT t.nome AS turma_nome, t.classe, {$mat_status_sel} AS matricula_status, m.ano_lectivo
         FROM {$wpdb->prefix}sige_matriculas m
         LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = m.escola_id
         WHERE m.aluno_id = %d AND m.escola_id = %d
         ORDER BY CASE WHEN m.ano_lectivo = %d THEN 0 ELSE 1 END,
                  {$mat_status_ord}
                  m.ano_lectivo DESC, m.id DESC
         LIMIT 1",
        $aluno_id,
        $escola_id,
        $ano_atual
    ));
    $turma_label = $turma ? trim((string)($turma->classe ?? '') . ' - ' . (string)($turma->turma_nome ?? ''), ' -') : 'Sem turma';
    if ($turma_label === '') $turma_label = 'Sem turma';

    $perfil = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $escola_id));
    if (!$perfil) $perfil = (object)[];
    $nome_escola = $perfil->nome_escola ?? get_bloginfo('name');
    $logo = !empty($perfil->logo_documentos_url) ? $perfil->logo_documentos_url : (!empty($perfil->logo_sistema_url) ? $perfil->logo_sistema_url : '');
    $telefone = $perfil->telefone_oficial ?? ($perfil->contacto ?? '');
    $email = $perfil->email_institucional ?? '';
    $endereco = $perfil->endereco_escola ?? '';
    $nuit = $perfil->nuit ?? '';
    $rodape = $perfil->rodape_documentos ?? 'Documento emitido pelo SIGE SoftGenial.';
    $doc_vars = function_exists('sige_theme_document_css_vars') ? sige_theme_document_css_vars($perfil) : '--sg-doc-primary:#5a3fd6;--sg-doc-primary-rgb:90,63,214;--sg-doc-primary-50:#f7f4ff;--sg-doc-primary-100:#f1edff;--sg-doc-primary-700:#4b35b8;--sg-doc-primary-800:#3b2a8f;--sg-doc-primary-900:#2f2169;--sg-doc-accent:#34a853;--sg-doc-ink:#202037;--sg-doc-muted:#64748b;';

    $pagamentos = $wpdb->get_results($wpdb->prepare(
        "SELECT p.id, p.data_pagamento, p.recibo_numero, p.valor_pago, p.metodo_pagamento, p.referencia_externa, p.observacoes,
                COALESCE(NULLIF(s.nome,''), NULLIF(l.descricao,''), 'Serviço não identificado') AS servico_nome,
                COALESCE(l.mes_referencia,'') AS mes_referencia,
                COALESCE(l.status,'') AS lancamento_status,
                u.display_name AS recebido_por_nome
         FROM {$wpdb->prefix}sige_fin_pagamentos p
         LEFT JOIN {$wpdb->prefix}sige_fin_lancamentos l ON l.id = p.lancamento_id AND l.escola_id = p.escola_id
         LEFT JOIN {$wpdb->prefix}sige_fin_servicos s ON s.id = l.servico_id AND s.escola_id = p.escola_id
         LEFT JOIN {$wpdb->prefix}users u ON u.ID = p.recebido_por
         WHERE p.aluno_id = %d AND p.escola_id = %d
         ORDER BY p.data_pagamento DESC, p.id DESC",
        $aluno_id,
        $escola_id
    ));

    $lancamentos = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, COALESCE(NULLIF(s.nome,''), NULLIF(l.descricao,''), 'Serviço não identificado') AS servico_nome
         FROM {$wpdb->prefix}sige_fin_lancamentos l
         LEFT JOIN {$wpdb->prefix}sige_fin_servicos s ON s.id = l.servico_id AND s.escola_id = l.escola_id
         WHERE l.aluno_id = %d AND l.escola_id = %d
           AND LOWER(COALESCE(l.status,'')) NOT IN ('cancelado','anulado')
         ORDER BY l.data_vencimento ASC, l.id ASC",
        $aluno_id,
        $escola_id
    ));

    $fmt = function($valor) use ($moeda) {
        return number_format((float)$valor, 2, ',', '.') . ' ' . $moeda;
    };
    $safe_date = function($value, $with_time = false) {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return '-';
        $ts = strtotime((string)$value);
        if (!$ts) return '-';
        return wp_date($with_time ? 'd/m/Y H:i' : 'd/m/Y', $ts);
    };
    $method_label = function($metodo) {
        if (function_exists('sige_fin_metodo_pagamento_label')) return sige_fin_metodo_pagamento_label((string)$metodo);
        return strtoupper(str_replace('_', ' ', (string)$metodo));
    };
    $lanc_total = function($l) {
        $multa = isset($l->valor_multa_cobrada) && $l->valor_multa_cobrada !== null && (float)$l->valor_multa_cobrada > 0
            ? (float)$l->valor_multa_cobrada
            : (float)($l->valor_multa ?? 0);
        return max(0, (float)($l->valor_original ?? 0)
            + (float)($l->valor_transporte ?? 0)
            + (float)($l->valor_extras ?? 0)
            + $multa
            - (float)($l->valor_desconto ?? 0)
            - (float)($l->valor_desconto_especial ?? 0));
    };
    $status_label = function($status) {
        $s = strtolower(trim((string)$status));
        $map = [
            'pago' => 'Pago',
            'pendente' => 'Pendente',
            'parcial' => 'Parcial',
            'em_plano' => 'Em plano',
            'cancelado' => 'Cancelado',
            'anulado' => 'Anulado',
        ];
        return $map[$s] ?? ($s !== '' ? ucfirst(str_replace('_', ' ', $s)) : 'Por avaliar');
    };

    $total_lancado = 0.0;
    $total_pago_registado = 0.0;
    $total_estornado = 0.0;
    $saldo_aberto = 0.0;
    $saldo_vencido = 0.0;
    $saldo_proximo = 0.0;
    $qtd_abertos = 0;
    $qtd_pagos = 0;
    $qtd_vencidos = 0;
    $hoje_ts = strtotime(wp_date('Y-m-d'));
    $ate_30_ts = strtotime('+30 days', $hoje_ts ?: time());
    $proximo_vencimento = null;
    $resumo_servicos = [];
    foreach ((array)$lancamentos as $l) {
        $total = $lanc_total($l);
        $pago_lanc = (float)($l->valor_pago ?? 0);
        $saldo = max(0, $total - $pago_lanc);
        $status = strtolower(trim((string)($l->status ?? '')));
        $total_lancado += $total;
        if ($status === 'pago' || $saldo <= 0.005) {
            $qtd_pagos++;
        } elseif (in_array($status, ['pendente','parcial','em_plano',''], true) || $saldo > 0) {
            $qtd_abertos++;
            $saldo_aberto += $saldo;
            $venc_ts = strtotime((string)($l->data_vencimento ?? ''));
            if ($venc_ts && $venc_ts < $hoje_ts) {
                $saldo_vencido += $saldo;
                $qtd_vencidos++;
            } elseif ($venc_ts && $venc_ts <= $ate_30_ts) {
                $saldo_proximo += $saldo;
                if ($proximo_vencimento === null || $venc_ts < strtotime((string)$proximo_vencimento->data_vencimento)) {
                    $proximo_vencimento = $l;
                }
            }
        }
        $svc = trim((string)($l->servico_nome ?? $l->descricao ?? 'Serviço não identificado')) ?: 'Serviço não identificado';
        if (!isset($resumo_servicos[$svc])) $resumo_servicos[$svc] = ['lancado'=>0.0,'pago'=>0.0,'saldo'=>0.0,'qtd'=>0];
        $resumo_servicos[$svc]['lancado'] += $total;
        $resumo_servicos[$svc]['pago'] += min($pago_lanc, $total);
        $resumo_servicos[$svc]['saldo'] += $saldo;
        $resumo_servicos[$svc]['qtd']++;
    }

    $resumo_metodos = [];
    $ultimo_pagamento = null;
    foreach ((array)$pagamentos as $p) {
        $valor = (float)($p->valor_pago ?? 0);
        $met = trim((string)($p->metodo_pagamento ?? '')) ?: 'sem_metodo';
        $is_estorno = ($valor < 0 || strtolower($met) === 'estorno');
        if ($is_estorno) {
            $total_estornado += abs($valor);
        } else {
            $total_pago_registado += $valor;
            if ($ultimo_pagamento === null) $ultimo_pagamento = $p;
        }
        $lbl = $method_label($met);
        if (!isset($resumo_metodos[$lbl])) $resumo_metodos[$lbl] = ['total'=>0.0,'qtd'=>0];
        $resumo_metodos[$lbl]['total'] += $valor;
        $resumo_metodos[$lbl]['qtd']++;
    }
    uasort($resumo_servicos, function($a, $b){ return ($b['saldo'] <=> $a['saldo']) ?: ($b['lancado'] <=> $a['lancado']); });
    uasort($resumo_metodos, function($a, $b){ return ($b['total'] <=> $a['total']); });

    $saldo_total = max(0, $saldo_aberto);
    $situacao_doc = 'Regularizado';
    $situacao_class = 'ok';
    $situacao_text = 'Sem dívida aberta no momento da emissão.';
    if ($saldo_vencido > 0.005) {
        $situacao_doc = 'Com valores vencidos';
        $situacao_class = 'danger';
        $situacao_text = 'Há valores vencidos que exigem acompanhamento da Secretaria/Tesouraria.';
    } elseif ($saldo_total > 0.005) {
        $situacao_doc = 'Com valores em aberto';
        $situacao_class = 'warn';
        $situacao_text = 'Há valores em aberto, mas sem vencimento crítico detectado.';
    }

    $doc_ref = 'HFIN-' . str_pad((string)$aluno_id, 5, '0', STR_PAD_LEFT) . '-' . wp_date('Ymd-His');
    $audit_hash = strtoupper(substr(hash('sha256', $doc_ref . '|' . $escola_id . '|' . $aluno_id . '|' . $saldo_total . '|' . count($pagamentos)), 0, 12));
    $foto = !empty($aluno->foto) ? (string)$aluno->foto : (defined('SIGE_URL') ? SIGE_URL . 'assets/img/avatar-default.svg' : '');

    if (!headers_sent()) {
        header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);
        header('X-Content-Type-Options: nosniff', true);
        header('X-Frame-Options: DENY', true);
        header('Content-Disposition: inline; filename="historico-financeiro-aluno-' . (int)$aluno_id . '.html"', true);
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html('Histórico financeiro - ' . ($aluno->nome_completo ?? 'Aluno')); ?></title>
        <?php echo function_exists('sige_cdn_script') ? sige_cdn_script('qrious') : ''; ?>
        <style <?php echo function_exists('sige_csp_style_attr') ? sige_csp_style_attr() : ''; ?>>
            @page { size: A4 portrait; margin: 8mm; }
            * { box-sizing:border-box; }
            :root { <?php echo esc_html($doc_vars); ?> }
            html, body { margin:0; padding:0; }
            body { font-family: Inter, 'Segoe UI', Arial, sans-serif; background:#eef1f8; color:#111827; font-size:12px; }
            .controls{position:sticky;top:0;z-index:20;display:flex;justify-content:center;gap:10px;padding:12px;background:rgba(248,250,252,.94);backdrop-filter:blur(10px);border-bottom:1px solid #e6e9f2;}
            .btn{min-height:40px;border:0;border-radius:14px;padding:0 18px;cursor:pointer;font-weight:900;font-size:13px;letter-spacing:.01em;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;}
            .btn-primary{background:linear-gradient(135deg,var(--sg-doc-primary),var(--sg-doc-primary-800));color:#fff;box-shadow:0 12px 26px rgba(var(--sg-doc-primary-rgb),.18);}
            .btn-muted{background:#fff;color:var(--sg-doc-primary-900);border:1px solid rgba(var(--sg-doc-primary-rgb),.16);box-shadow:0 10px 22px rgba(15,23,42,.08);}
            .sheet{width:210mm;min-height:297mm;margin:16px auto 28px;background:#fff;border:1px solid rgba(var(--sg-doc-primary-rgb),.16);border-radius:22px;box-shadow:0 24px 60px rgba(23,15,73,.18);overflow:hidden;}
            .topbar{height:9px;background:linear-gradient(90deg,var(--sg-doc-primary),var(--sg-doc-primary-700),var(--sg-doc-accent));}
            .sheet-inner{padding:13mm;}
            .school{display:grid;grid-template-columns:70px 1fr auto;gap:16px;align-items:center;border-bottom:1px solid #e8ecf5;padding-bottom:14px;margin-bottom:16px;}
            .logo{width:62px;height:62px;border-radius:18px;background:var(--sg-doc-primary-50);display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid rgba(var(--sg-doc-primary-rgb),.16);}
            .logo img{max-width:100%;max-height:100%;object-fit:contain;}
            .school h1{margin:0;color:var(--sg-doc-primary-900);font-size:20px;line-height:1.08;text-transform:uppercase;letter-spacing:.01em;}
            .school p{margin:5px 0 0;color:#64748b;font-size:10.5px;line-height:1.45;font-weight:650;}
            .doc-id{text-align:right;color:#64748b;font-size:10px;line-height:1.45;font-weight:800;}
            .doc-id strong{display:block;color:var(--sg-doc-primary);font-size:13px;}
            .hero{display:grid;grid-template-columns:72px 1fr 185px;gap:16px;align-items:center;border:1px solid #e8ecf5;border-radius:20px;background:linear-gradient(135deg,#fff 0%,var(--sg-doc-primary-50) 100%);padding:16px;margin-bottom:14px;}
            .photo{width:72px;height:72px;border-radius:22px;object-fit:cover;background:#f1f5f9;border:4px solid #fff;box-shadow:0 12px 26px rgba(15,23,42,.12);}
            .hero small{display:block;color:var(--sg-doc-primary);font-size:10px;font-weight:950;letter-spacing:.1em;text-transform:uppercase;margin-bottom:4px;}
            .hero h2{margin:0;color:#111827;font-size:23px;line-height:1.08;letter-spacing:-.04em;}
            .chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px;}
            .chip{border-radius:999px;background:#fff;border:1px solid #e8ecf5;color:#475569;padding:6px 9px;font-size:10.5px;font-weight:850;}
            .status{border-radius:18px;padding:13px 14px;text-align:center;border:1px solid #dcfce7;background:#f0fdf4;color:#166534;}
            .status.warn{border-color:#fde68a;background:#fffbeb;color:#92400e;}.status.danger{border-color:#fecaca;background:#fef2f2;color:#991b1b;}
            .status span{display:block;font-size:9.5px;font-weight:950;letter-spacing:.12em;text-transform:uppercase;opacity:.8;}.status strong{display:block;font-size:16px;line-height:1.15;margin-top:3px;}
            .kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:12px 0 14px;}
            .kpi{border:1px solid #e8ecf5;border-radius:16px;padding:12px;background:#fff;min-height:72px;}
            .kpi span{display:block;color:#64748b;font-size:9.5px;text-transform:uppercase;letter-spacing:.08em;font-weight:950;margin-bottom:6px;}
            .kpi strong{display:block;color:#111827;font-size:15px;font-weight:950;line-height:1.18;}.kpi.good strong{color:#166534}.kpi.bad strong{color:#b91c1c}.kpi.warn strong{color:#a16207}
            .note{margin:0 0 14px;padding:11px 13px;border-radius:15px;border:1px solid #e8ecf5;background:#f8fafc;color:#475569;font-size:11px;line-height:1.5;font-weight:700;}
            .section{margin-top:15px;page-break-inside:avoid;}
            .section-title{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 8px;}
            .section-title h3{margin:0;color:var(--sg-doc-primary-900);font-size:13px;text-transform:uppercase;letter-spacing:.06em;}.section-title span{color:#64748b;font-size:10px;font-weight:850;}
            table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #e8ecf5;border-radius:14px;overflow:hidden;font-size:10.5px;}
            th{background:var(--sg-doc-primary-50);color:var(--sg-doc-primary-900);text-align:left;padding:8px 9px;text-transform:uppercase;letter-spacing:.05em;font-size:9.2px;font-weight:950;border-bottom:1px solid #e8ecf5;}
            td{padding:8px 9px;border-bottom:1px solid #edf2f7;vertical-align:top;line-height:1.35;color:#1f2937;}
            tr:last-child td{border-bottom:0;} .num{text-align:right;white-space:nowrap;font-weight:900;}.pos{color:#166534}.neg{color:#b91c1c}.muted{color:#64748b}.pill{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.04em;border:1px solid #e8ecf5;background:#fff;}
            .pill.pago{background:#ecfdf5;color:#166534;border-color:#bbf7d0}.pill.pendente,.pill.parcial,.pill.em_plano{background:#fffbeb;color:#92400e;border-color:#fde68a}.pill.vencido{background:#fef2f2;color:#991b1b;border-color:#fecaca}
            .summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
            .empty{border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-weight:750;padding:14px;text-align:center;}
            .footer{display:grid;grid-template-columns:90px 1fr 180px;gap:14px;align-items:end;margin-top:22px;padding-top:14px;border-top:1px solid #e8ecf5;color:#64748b;font-size:10px;line-height:1.45;}
            .qrbox{width:74px;height:74px;border:1px solid #e8ecf5;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#fff;}
            .signature{text-align:center;border-top:1px solid #111827;padding-top:6px;color:#111827;font-weight:800;}
            @media print{body{background:#fff}.controls{display:none}.sheet{width:210mm;min-height:297mm;margin:0;box-shadow:none;border:0;border-radius:0}.sheet-inner{padding:10mm}.kpis{gap:7px}.hero{grid-template-columns:60px 1fr 165px}.photo{width:60px;height:60px}.hero h2{font-size:20px}table{font-size:9.7px}th{font-size:8.6px}.section{break-inside:avoid}.no-print{display:none!important}}
            @media(max-width:760px){.sheet{width:100%;margin:0;border-radius:0}.sheet-inner{padding:18px}.school,.hero,.footer,.summary-grid{grid-template-columns:1fr}.doc-id{text-align:left}.kpis{grid-template-columns:1fr 1fr}.controls{flex-wrap:wrap}.btn{width:100%;}.status{text-align:left}}
        </style>
    </head>
    <body>
        <div class="controls no-print">
            <button class="btn btn-primary" data-sige-print>Baixar / Guardar PDF</button>
            <button class="btn btn-muted" data-sige-print>Imprimir</button>
            <button class="btn btn-muted" data-sige-close>Fechar</button>
        </div>
        <main class="sheet">
            <div class="topbar"></div>
            <div class="sheet-inner">
                <section class="school">
                    <div class="logo"><?php if ($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="Logotipo"><?php else: ?><strong>SIGE</strong><?php endif; ?></div>
                    <div>
                        <h1><?php echo esc_html($nome_escola); ?></h1>
                        <p><?php echo esc_html(implode(' · ', array_filter([$endereco, $telefone ? 'Tel: '.$telefone : '', $email, $nuit ? 'NUIT: '.$nuit : '']))); ?></p>
                    </div>
                    <div class="doc-id"><strong><?php echo esc_html($doc_ref); ?></strong>Emitido em<br><?php echo esc_html(wp_date('d/m/Y H:i')); ?><br>Código: <?php echo esc_html($audit_hash); ?></div>
                </section>

                <section class="hero">
                    <?php if ($foto): ?><img class="photo" src="<?php echo esc_url($foto); ?>" alt="Foto do aluno"><?php else: ?><div class="photo"></div><?php endif; ?>
                    <div>
                        <small>Histórico financeiro do aluno</small>
                        <h2><?php echo esc_html($aluno->nome_completo ?? 'Aluno'); ?></h2>
                        <div class="chips">
                            <span class="chip">Proc. <?php echo esc_html($aluno->numero_processo ?? '-'); ?></span>
                            <span class="chip"><?php echo esc_html($turma_label); ?></span>
                            <span class="chip">Estado: <?php echo esc_html($aluno->status ?? 'activo'); ?></span>
                            <span class="chip">Ano lectivo <?php echo esc_html((string)$ano_atual); ?></span>
                        </div>
                    </div>
                    <div class="status <?php echo esc_attr($situacao_class); ?>"><span>Situação financeira</span><strong><?php echo esc_html($situacao_doc); ?></strong></div>
                </section>

                <section class="kpis" aria-label="Resumo financeiro">
                    <div class="kpi"><span>Total lançado</span><strong><?php echo esc_html($fmt($total_lancado)); ?></strong></div>
                    <div class="kpi good"><span>Total recebido</span><strong><?php echo esc_html($fmt($total_pago_registado)); ?></strong></div>
                    <div class="kpi bad"><span>Saldo em aberto</span><strong><?php echo esc_html($fmt($saldo_total)); ?></strong></div>
                    <div class="kpi <?php echo $saldo_vencido > 0 ? 'bad' : 'good'; ?>"><span>Vencido</span><strong><?php echo esc_html($fmt($saldo_vencido)); ?></strong></div>
                    <div class="kpi"><span>Pagamentos</span><strong><?php echo esc_html((string)count($pagamentos)); ?> movimento(s)</strong></div>
                    <div class="kpi warn"><span>Estornos</span><strong><?php echo esc_html($fmt($total_estornado)); ?></strong></div>
                    <div class="kpi"><span>Lançamentos pagos</span><strong><?php echo esc_html((string)$qtd_pagos); ?></strong></div>
                    <div class="kpi <?php echo $qtd_vencidos > 0 ? 'bad' : ''; ?>"><span>Pendências</span><strong><?php echo esc_html((string)$qtd_abertos); ?> aberto(s)</strong></div>
                </section>

                <p class="note"><strong>Leitura rápida:</strong> <?php echo esc_html($situacao_text); ?> <?php if ($ultimo_pagamento): ?> Último pagamento registado em <?php echo esc_html($safe_date($ultimo_pagamento->data_pagamento, true)); ?> no valor de <?php echo esc_html($fmt($ultimo_pagamento->valor_pago)); ?>.<?php endif; ?> <?php if ($proximo_vencimento): ?> Próximo vencimento em aberto: <?php echo esc_html($safe_date($proximo_vencimento->data_vencimento)); ?> - <?php echo esc_html($proximo_vencimento->servico_nome ?? $proximo_vencimento->descricao ?? 'Serviço'); ?>.<?php endif; ?></p>

                <section class="section">
                    <div class="section-title"><h3>Valores em aberto e próximos vencimentos</h3><span><?php echo esc_html((string)$qtd_abertos); ?> lançamento(s)</span></div>
                    <?php if ($qtd_abertos <= 0): ?>
                        <div class="empty">Sem valores em aberto no momento da emissão.</div>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Vencimento</th><th>Serviço</th><th>Referência</th><th>Estado</th><th class="num">Lançado</th><th class="num">Pago</th><th class="num">Saldo</th></tr></thead>
                            <tbody>
                            <?php foreach ((array)$lancamentos as $l):
                                $total = $lanc_total($l);
                                $pago_lanc = (float)($l->valor_pago ?? 0);
                                $saldo = max(0, $total - $pago_lanc);
                                if ($saldo <= 0.005) continue;
                                $venc_ts = strtotime((string)($l->data_vencimento ?? ''));
                                $is_vencido = $venc_ts && $venc_ts < $hoje_ts;
                                $st = strtolower(trim((string)($l->status ?? 'pendente')));
                            ?>
                                <tr>
                                    <td><?php echo esc_html($safe_date($l->data_vencimento)); ?></td>
                                    <td><strong><?php echo esc_html($l->servico_nome ?? $l->descricao ?? 'Serviço'); ?></strong></td>
                                    <td><?php echo esc_html($l->mes_referencia ?? '-'); ?></td>
                                    <td><span class="pill <?php echo esc_attr($is_vencido ? 'vencido' : $st); ?>"><?php echo esc_html($is_vencido ? 'Vencido' : $status_label($st)); ?></span></td>
                                    <td class="num"><?php echo esc_html($fmt($total)); ?></td>
                                    <td class="num pos"><?php echo esc_html($fmt($pago_lanc)); ?></td>
                                    <td class="num neg"><?php echo esc_html($fmt($saldo)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="section summary-grid">
                    <div>
                        <div class="section-title"><h3>Resumo por serviço</h3><span>360º financeiro</span></div>
                        <?php if (empty($resumo_servicos)): ?>
                            <div class="empty">Sem lançamentos financeiros registados.</div>
                        <?php else: ?>
                            <table><thead><tr><th>Serviço</th><th class="num">Lançado</th><th class="num">Saldo</th></tr></thead><tbody>
                            <?php foreach (array_slice($resumo_servicos, 0, 8, true) as $svc => $row): ?>
                                <tr><td><?php echo esc_html($svc); ?><br><span class="muted"><?php echo (int)$row['qtd']; ?> lançamento(s)</span></td><td class="num"><?php echo esc_html($fmt($row['lancado'])); ?></td><td class="num <?php echo $row['saldo'] > 0 ? 'neg' : 'pos'; ?>"><?php echo esc_html($fmt($row['saldo'])); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="section-title"><h3>Resumo por método</h3><span>Recebimentos</span></div>
                        <?php if (empty($resumo_metodos)): ?>
                            <div class="empty">Sem pagamentos registados.</div>
                        <?php else: ?>
                            <table><thead><tr><th>Método</th><th>Mov.</th><th class="num">Total</th></tr></thead><tbody>
                            <?php foreach ($resumo_metodos as $met => $row): ?>
                                <tr><td><?php echo esc_html($met); ?></td><td><?php echo (int)$row['qtd']; ?></td><td class="num <?php echo $row['total'] < 0 ? 'neg' : 'pos'; ?>"><?php echo esc_html($fmt($row['total'])); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="section">
                    <div class="section-title"><h3>Histórico de pagamentos e estornos</h3><span><?php echo esc_html((string)count($pagamentos)); ?> movimento(s)</span></div>
                    <?php if (empty($pagamentos)): ?>
                        <div class="empty">Sem pagamentos registados para este aluno.</div>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Data</th><th>Recibo</th><th>Serviço</th><th>Método</th><th>Recebido por</th><th class="num">Valor</th></tr></thead>
                            <tbody>
                            <?php foreach ((array)$pagamentos as $p):
                                $valor = (float)($p->valor_pago ?? 0);
                                $is_estorno = ($valor < 0 || strtolower((string)$p->metodo_pagamento) === 'estorno');
                            ?>
                                <tr>
                                    <td><?php echo esc_html($safe_date($p->data_pagamento, true)); ?></td>
                                    <td><strong><?php echo esc_html($p->recibo_numero ?: ('#' . (int)$p->id)); ?></strong></td>
                                    <td><?php echo esc_html($p->servico_nome ?: 'Serviço não identificado'); ?><?php if (!empty($p->mes_referencia)): ?><br><span class="muted">Ref: <?php echo esc_html($p->mes_referencia); ?></span><?php endif; ?></td>
                                    <td><?php echo esc_html($method_label($p->metodo_pagamento)); ?></td>
                                    <td><?php echo esc_html($p->recebido_por_nome ?: 'Sistema'); ?></td>
                                    <td class="num <?php echo $is_estorno ? 'neg' : 'pos'; ?>"><?php echo esc_html($fmt($valor)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="footer">
                    <div class="qrbox"><canvas id="qr-historico" width="68" height="68" aria-label="Código de validação"></canvas></div>
                    <div>
                        <strong><?php echo esc_html($rodape); ?></strong><br>
                        Este documento apresenta valores calculados a partir dos lançamentos, pagamentos e estornos registados no sistema até ao momento da emissão. Alterações posteriores no caixa ou em lançamentos podem alterar o saldo.
                    </div>
                    <div class="signature">TESOURARIA / SECRETARIA<br><span class="muted">Processado por computador</span></div>
                </section>
            </div>
        </main>
        <script <?php echo sige_csp_script_attr(); ?>>
            (function(){
                if (typeof QRious !== 'undefined') {
                    new QRious({element: document.getElementById('qr-historico'), value: <?php echo wp_json_encode('SIGE:HISTFIN|' . $doc_ref . '|ALUNO:' . $aluno_id . '|HASH:' . $audit_hash); ?>, size: 68});
                }
            })();
            document.querySelectorAll('[data-sige-print]').forEach(function(el){ el.addEventListener('click', function(){ window.print(); }); });
            document.querySelectorAll('[data-sige-close]').forEach(function(el){ el.addEventListener('click', function(){ window.close(); }); });
        </script>
    </body>
    </html>
    <?php
}

/**
 * Template documental específico para recibos.
 *
 * Todos os recibos financeiros do SIGE são emitidos em A4 horizontal com
 * duas vias no mesmo papel: ORIGINAL e CÓPIA, separadas por linha de corte.
 * Esta função altera apenas a apresentação/impressão, sem tocar nas fórmulas.
 */
function sige_print_recibo_duplo($titulo, $doc_num, $nome, $processo, $turma, $logo, $perfil, $conteudo_html, $qr_data, $assinatura) {
    header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");

    $nome_escola = $perfil->nome_escola ?? get_bloginfo('name');
    $endereco    = $perfil->endereco_escola ?? '';
    $telefone    = $perfil->telefone_oficial ?? '';
    $email       = $perfil->email_institucional ?? '';
    $cidade      = $perfil->cidade ?? '';
    $distrito    = $perfil->distrito ?? '';
    $provincia   = $perfil->provincia ?? '';
    $pais        = $perfil->pais ?? 'Moçambique';
    $nuit        = $perfil->nuit ?? '';
    $codigo      = $perfil->codigo_escola ?? '';
    $rodape      = $perfil->rodape_documentos ?? 'Este recibo é válido como comprovativo de pagamento.';
    $doc_vars    = function_exists('sige_theme_document_css_vars') ? sige_theme_document_css_vars($perfil) : '--sg-doc-primary:#5a3fd6;--sg-doc-primary-rgb:90,63,214;--sg-doc-primary-50:#f7f4ff;--sg-doc-primary-100:#f1edff;--sg-doc-primary-700:#4b35b8;--sg-doc-primary-800:#3b2a8f;--sg-doc-primary-900:#2f2169;--sg-doc-accent:#34a853;--sg-doc-ink:#202037;--sg-doc-muted:#64748b;';
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($titulo . ' - ' . $doc_num); ?></title>
        <?php echo sige_cdn_script("qrious"); ?>
        <style <?php echo function_exists('sige_csp_style_attr') ? sige_csp_style_attr() : ''; ?>>
            @page { size: A4 landscape; margin: 6mm; }
            * { box-sizing: border-box; }
            :root { <?php echo esc_html($doc_vars); ?> }
            html, body { margin: 0; padding: 0; }
            body { font-family: Inter, 'Segoe UI', Arial, sans-serif; background:#eef1f8; color:#0f172a; }
            .controls { position:sticky; top:0; z-index:20; display:flex; justify-content:center; gap:10px; padding:12px; background:rgba(248,250,252,.92); backdrop-filter: blur(10px); border-bottom:1px solid #e6e9f2; }
            .btn { min-height:40px; border:0; border-radius:14px; padding:0 18px; cursor:pointer; font-weight:850; font-size:13px; letter-spacing:.01em; box-shadow:0 12px 26px rgba(var(--sg-doc-primary-rgb),.18); }
            .btn-primary { background:linear-gradient(135deg,var(--sg-doc-primary),var(--sg-doc-primary-800)); color:#fff; }
            .btn-muted { background:#fff; color:var(--sg-doc-primary-900); border:1px solid rgba(var(--sg-doc-primary-rgb),.16); box-shadow:0 10px 22px rgba(15,23,42,.08); }
            .sheet-recibo { background:#fff; width:285mm; min-height:198mm; margin:16px auto 24px; padding:4mm; box-shadow:0 24px 60px rgba(23,15,73,.18); display:grid; grid-template-columns: 1fr 7mm 1fr; gap:0; position:relative; border-radius:12px; }
            .linha-corte-vertical { position:relative; display:flex; align-items:center; justify-content:center; min-height:190mm; }
            .linha-corte-vertical::before { content:''; position:absolute; top:6mm; bottom:6mm; left:50%; border-left:1.6px dashed #b9b2d8; }
            .linha-corte-label { position:absolute; left:50%; transform:translateX(-50%); background:#fff; color:#7c729f; font-size:7.6px; font-weight:900; letter-spacing:.08em; white-space:nowrap; padding:2px 4px; z-index:2; text-transform:uppercase; }
            .linha-corte-label.top { top:0; }
            .linha-corte-label.bottom { bottom:0; }
            .linha-corte-label.vertical { transform:translateX(-50%) rotate(90deg); top:48%; }
            .recibo-via { border:1px solid rgba(var(--sg-doc-primary-rgb),.18); border-radius:14px; padding:4.2mm; position:relative; overflow:hidden; min-height:190mm; background:linear-gradient(180deg,#ffffff 0%,#fff 65%,#faf9ff 100%); }
            .recibo-via::before { content:''; position:absolute; inset:0 0 auto 0; height:3mm; background:linear-gradient(90deg,var(--sg-doc-primary),var(--sg-doc-primary-700),var(--sg-doc-accent)); }
            .marca-via { position:absolute; right:4.5mm; top:5.5mm; background:var(--sg-doc-primary-50); color:var(--sg-doc-primary); border:1px solid rgba(var(--sg-doc-primary-rgb),.16); border-radius:999px; padding:4px 12px; font-size:10px; font-weight:950; letter-spacing:.08em; box-shadow:0 8px 18px rgba(var(--sg-doc-primary-rgb),.13); z-index:3; text-transform:uppercase; }
            .escola-topo { display:grid; grid-template-columns: 28mm 1fr; gap:5mm; min-height:37mm; padding-right:35mm; align-items:start; padding-top:3mm; }
            .recibo-logo { text-align:center; }
            .recibo-logo img { max-width:25mm; max-height:30mm; object-fit:contain; }
            .recibo-logo.placeholder { width:25mm; height:25mm; border:1px solid rgba(var(--sg-doc-primary-rgb),.16); background:var(--sg-doc-primary-50); border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--sg-doc-primary); font-size:18px; font-weight:950; }
            .recibo-escola strong { display:block; color:var(--sg-doc-primary-900); font-size:16.2px; line-height:1.05; text-transform:uppercase; letter-spacing:.02em; margin-bottom:3px; }
            .recibo-escola .sub { color:#64748b; font-size:9.2px; line-height:1.34; margin-bottom:4px; }
            .recibo-escola .linha { color:#475569; font-size:8.7px; line-height:1.43; }
            .recibo-escola .fiscal { margin-top:3px; color:var(--sg-doc-primary-900); font-size:8.8px; font-weight:850; }
            .barra-titulo { margin:3mm 0 3mm; background:linear-gradient(135deg,var(--sg-doc-primary-800),var(--sg-doc-primary)); color:#fff; border-radius:12px; min-height:17mm; display:grid; grid-template-columns: 13mm 1fr 28mm; align-items:center; overflow:hidden; border:1px solid rgba(255,255,255,.42); box-shadow:0 14px 32px rgba(var(--sg-doc-primary-rgb),.22); }
            .barra-icon { font-size:22px; text-align:center; color:#fff; opacity:.96; }
            .barra-texto { padding:2mm 1mm; }
            .barra-texto .tit { font-size:16px; font-weight:950; letter-spacing:.02em; line-height:1.1; text-transform:uppercase; }
            .barra-texto .ref { font-size:9.3px; font-weight:800; margin-top:2px; opacity:.96; }
            .barra-data { background:rgba(255,255,255,.94); color:var(--sg-doc-primary-900); margin:2mm; border-radius:10px; padding:2mm; text-align:center; font-size:9.7px; font-weight:800; }
            .barra-data strong { display:block; color:var(--sg-doc-primary); font-size:8.4px; margin-bottom:2px; text-transform:uppercase; letter-spacing:.04em; }
            .recibo-meta { display:grid; grid-template-columns: 1.2fr .95fr 1fr; border:1px solid rgba(var(--sg-doc-primary-rgb),.18); border-radius:10px; overflow:hidden; margin-bottom:3mm; background:#fff; }
            .recibo-meta div { padding:2.1mm 2.6mm; min-height:12mm; border-right:1px solid rgba(var(--sg-doc-primary-rgb),.18); font-size:9.7px; line-height:1.35; }
            .recibo-meta div:last-child { border-right:0; }
            .recibo-meta strong { display:block; color:var(--sg-doc-primary); font-size:8.2px; letter-spacing:.05em; text-transform:uppercase; margin-bottom:2px; }
            .items { width:100%; border-collapse:separate; border-spacing:0; table-layout:fixed; font-size:9.3px; position:relative; z-index:1; overflow:hidden; border-radius:10px; border:1px solid rgba(var(--sg-doc-primary-rgb),.16); background:#fff; }
            .items th { text-align:left; background:var(--sg-doc-primary-50) !important; color:var(--sg-doc-primary-900) !important; padding:6px 7px; font-size:8.5px; font-weight:950; text-transform:uppercase; border-bottom:1px solid rgba(var(--sg-doc-primary-rgb),.18); }
            .items td { border-bottom:1px solid #edf0f7; padding:5.5px 7px; line-height:1.25; vertical-align:top; word-wrap:break-word; }
            .items tr:last-child td { border-bottom:0; }
            .items th:nth-child(1), .items td:nth-child(1) { width:50%; }
            .items th:nth-child(2), .items td:nth-child(2) { width:17%; text-align:center; }
            .items th:nth-child(3), .items td:nth-child(3) { width:16%; text-align:right; }
            .items th:nth-child(4), .items td:nth-child(4) { width:17%; text-align:right; }
            .items small { font-size:7.65px !important; line-height:1.18 !important; }
            .total td { font-size:10.3px; font-weight:950; border-top:2px solid var(--sg-doc-primary) !important; background:var(--sg-doc-primary-50) !important; color:var(--sg-doc-primary-900); }
            .items tr[style*="fffde7"] td { background:#fff7ed !important; color:#c2410c !important; font-weight:900; }
            .recibo-via > div[style*="grid-template-columns:1fr 1fr 1fr"] { margin-top:3mm !important; font-size:8.9px !important; border:1px solid rgba(var(--sg-doc-primary-rgb),.18) !important; border-top:1px solid rgba(var(--sg-doc-primary-rgb),.18) !important; border-radius:10px; padding:0 !important; display:grid !important; grid-template-columns:1fr 1fr 1fr !important; gap:0 !important; color:#0f172a !important; overflow:hidden; background:#fff; }
            .recibo-via > div[style*="grid-template-columns:1fr 1fr 1fr"] > div { padding:2.3mm !important; border-right:1px solid rgba(var(--sg-doc-primary-rgb),.18); min-height:11mm; }
            .recibo-via > div[style*="grid-template-columns:1fr 1fr 1fr"] > div:last-child { border-right:0; }
            .recibo-footer { position:absolute; left:4.2mm; right:4.2mm; bottom:4mm; display:grid; grid-template-columns: 22mm 1fr 34mm; gap:4mm; align-items:end; padding-top:3mm; border-top:1px solid rgba(var(--sg-doc-primary-rgb),.18); }
            .qr-wrap { text-align:center; font-size:7.2px; color:#64748b; }
            .qr-wrap canvas { width:18mm !important; height:18mm !important; border:1px solid rgba(var(--sg-doc-primary-rgb),.18); border-radius:6px; background:#fff; }
            .nota-arquivo { display:grid; grid-template-columns:10mm 1fr; gap:2.5mm; align-items:center; color:#475569; font-size:8px; line-height:1.3; }
            .nota-arquivo .escudo { width:9mm; height:9mm; border:1.5px solid var(--sg-doc-primary); background:var(--sg-doc-primary-50); border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--sg-doc-primary); font-weight:950; }
            .assinatura { text-align:center; color:#0f172a; font-size:8.2px; padding-top:3px; }
            .assinatura .linha { border-top:1.2px solid var(--sg-doc-primary-900); height:1px; margin-bottom:4px; }
            .obrigado { background:linear-gradient(135deg,var(--sg-doc-primary-800),var(--sg-doc-primary)); color:#fff; border-radius:9px; padding:4px 5px; text-align:center; font-size:7.8px; font-weight:850; line-height:1.25; margin-top:3mm; }
            @media print {
                body { background:#fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .controls { display:none; }
                .sheet-recibo { width:285mm; min-height:198mm; height:198mm; margin:0; padding:0; box-shadow:none; border-radius:0; }
                .recibo-via { min-height:198mm; border-radius:10px; page-break-inside:avoid; break-inside:avoid; }
            }
        </style>
    </head>
    <body>
        <div class="controls">
            <button class="btn btn-primary" data-sige-print>Imprimir / Guardar PDF</button>
            <button class="btn btn-muted" data-sige-close>Fechar</button>
        </div>
        <div class="sheet-recibo">
            <?php sige_print_recibo_via('ORIGINAL', $titulo, $doc_num, $nome, $processo, $turma, $logo, $nome_escola, $endereco, $nuit, $telefone, $email, $cidade, $distrito, $provincia, $pais, $codigo, $rodape, $conteudo_html, $qr_data, $assinatura, 'qr-original'); ?>
            <div class="linha-corte-vertical">
                <span class="linha-corte-label top">✂ CORTE AQUI ✂</span>
                <span class="linha-corte-label vertical">CORTE AQUI</span>
                <span class="linha-corte-label bottom">✂ CORTE AQUI ✂</span>
            </div>
            <?php sige_print_recibo_via('CÓPIA', $titulo, $doc_num, $nome, $processo, $turma, $logo, $nome_escola, $endereco, $nuit, $telefone, $email, $cidade, $distrito, $provincia, $pais, $codigo, $rodape, $conteudo_html, $qr_data, $assinatura, 'qr-copia'); ?>
        </div>
        <script <?php echo sige_csp_script_attr(); ?>>
            (function(){
                if (typeof QRious === 'undefined') return;
                var valor = <?php echo wp_json_encode((string)$qr_data); ?>;
                var original = document.getElementById('qr-original');
                var copia = document.getElementById('qr-copia');
                if (original) new QRious({element: original, value: valor, size: 82});
                if (copia) new QRious({element: copia, value: valor, size: 82});
            })();
            document.querySelectorAll('[data-sige-print]').forEach(function(el){ el.addEventListener('click', function(){ window.print(); }); });
            document.querySelectorAll('[data-sige-close]').forEach(function(el){ el.addEventListener('click', function(){ window.close(); }); });
        </script>
    </body>
    </html>
    <?php
}

function sige_print_recibo_via($via, $titulo, $doc_num, $nome, $processo, $turma, $logo, $nome_escola, $endereco, $nuit, $telefone, $email, $cidade, $distrito, $provincia, $pais, $codigo, $rodape, $conteudo_html, $qr_data, $assinatura, $qr_id) {
    $localizacao = trim(implode(' - ', array_filter([$cidade ?: $distrito, $provincia, $pais])));
    ?>
    <section class="recibo-via">
        <div class="marca-via"><?php echo esc_html($via); ?></div>

        <div class="escola-topo">
            <div class="recibo-logo">
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="Logotipo da escola">
                <?php else: ?>
                    <div class="recibo-logo placeholder">SG</div>
                <?php endif; ?>
            </div>
            <div class="recibo-escola">
                <strong><?php echo esc_html($nome_escola); ?></strong>
                <?php if (!empty($rodape)): ?><div class="sub"><?php echo esc_html(wp_strip_all_tags($rodape)); ?></div><?php endif; ?>
                <?php if ($endereco): ?><div class="linha">📍 <?php echo esc_html($endereco); ?></div><?php endif; ?>
                <?php if ($localizacao): ?><div class="linha">🌍 <?php echo esc_html($localizacao); ?></div><?php endif; ?>
                <?php if ($telefone): ?><div class="linha">☎ <?php echo esc_html($telefone); ?></div><?php endif; ?>
                <?php if ($email): ?><div class="linha">✉ <?php echo esc_html($email); ?></div><?php endif; ?>
                <div class="fiscal">
                    <?php if ($nuit): ?>NUIT: <?php echo esc_html($nuit); ?><?php endif; ?>
                    <?php if ($nuit && $codigo): ?> &nbsp; | &nbsp; <?php endif; ?>
                    <?php if ($codigo): ?>Código: <?php echo esc_html($codigo); ?><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="barra-titulo">
            <div class="barra-icon">✓</div>
            <div class="barra-texto">
                <div class="tit"><?php echo esc_html($titulo); ?></div>
                <div class="ref">Nº <?php echo esc_html($doc_num); ?></div>
            </div>
            <div class="barra-data"><strong>DATA:</strong><?php echo esc_html(wp_date('d/m/Y H:i')); ?></div>
        </div>

        <div class="recibo-meta">
            <div><strong>Aluno(a):</strong><?php echo esc_html($nome); ?></div>
            <div><strong>Processo:</strong><?php echo esc_html($processo); ?></div>
            <div><strong>Turma:</strong><?php echo esc_html($turma); ?></div>
        </div>

        <?php echo $conteudo_html; // Conteúdo financeiro produzido internamente pelo SIGE, campo a campo. ?>

        <div class="recibo-footer">
            <div class="qr-wrap"><canvas id="<?php echo esc_attr($qr_id); ?>"></canvas><br>Validação do documento</div>
            <div class="nota-arquivo">
                <div class="escudo">✓</div>
                <div>
                    <?php echo esc_html($via === 'ORIGINAL' ? 'Via entregue ao encarregado/aluno.' : 'Via destinada ao arquivo físico da escola.'); ?><br>
                    Referência do documento: <strong><?php echo esc_html($doc_num); ?></strong>
                </div>
            </div>
            <div>
                <div class="assinatura"><div class="linha"></div><strong><?php echo esc_html($assinatura); ?></strong><br>Assinatura e carimbo</div>
                <div class="obrigado">Obrigado!<br>Pagamento registado com sucesso.</div>
            </div>
        </div>
    </section>
    <?php
}

// 4. TEMPLATE GRÁFICO (REUTILIZÁVEL)
// ==========================================
function sige_print_template_header($titulo, $doc_num, $nome, $processo, $turma, $logo, $perfil) {
    header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    $doc_vars = function_exists('sige_theme_document_css_vars') ? sige_theme_document_css_vars($perfil) : '--sg-doc-primary:#5a3fd6;--sg-doc-primary-rgb:90,63,214;--sg-doc-primary-50:#f7f4ff;--sg-doc-primary-100:#f1edff;--sg-doc-primary-700:#4b35b8;--sg-doc-primary-800:#3b2a8f;--sg-doc-primary-900:#2f2169;--sg-doc-accent:#34a853;--sg-doc-ink:#202037;--sg-doc-muted:#64748b;';
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($titulo); ?></title>
        <?php echo sige_cdn_script("qrious"); ?>
        <style <?php echo function_exists('sige_csp_style_attr') ? sige_csp_style_attr() : ''; ?>>
            @page { size: A4 landscape; margin: 8mm; }
            * { box-sizing: border-box; }
            :root { <?php echo esc_html($doc_vars); ?> }
            body { font-family: Inter, 'Segoe UI', Arial, sans-serif; background:#eef1f8; margin:0; padding:18px; color:#0f172a; }
            .controls { position:sticky; top:0; z-index:20; display:flex; justify-content:center; gap:10px; padding:12px; margin:-18px -18px 18px; background:rgba(248,250,252,.92); backdrop-filter:blur(10px); border-bottom:1px solid #e6e9f2; }
            .btn { min-height:40px; border:0; border-radius:14px; padding:0 18px; cursor:pointer; font-weight:850; font-size:13px; letter-spacing:.01em; }
            .btn-primary { background:linear-gradient(135deg,var(--sg-doc-primary),var(--sg-doc-primary-800)); color:#fff; box-shadow:0 12px 26px rgba(var(--sg-doc-primary-rgb),.18); }
            .btn-muted { background:#fff; color:var(--sg-doc-primary-900); border:1px solid rgba(var(--sg-doc-primary-rgb),.16); box-shadow:0 10px 22px rgba(15,23,42,.08); }
            .sheet { background:#fff; width:277mm; min-height:190mm; margin:0 auto; padding:12mm 15mm; box-shadow:0 24px 60px rgba(23,15,73,.18); border-radius:18px; border:1px solid rgba(var(--sg-doc-primary-rgb),.16); }
            .header { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; border-bottom:1px solid #e7e2fb; padding-bottom:14px; margin-bottom:18px; }
            .logo img { height:58px; max-width:150px; object-fit:contain; }
            .info { text-align:right; font-size:11px; color:#475569; line-height:1.5; font-weight:600; }
            .info strong { color:var(--sg-doc-primary-900) !important; font-size:15px !important; }
            .title { text-align:left; font-weight:950; font-size:22px; margin:16px 0; color:var(--sg-doc-primary-900); letter-spacing:-.02em; padding:14px 16px; border-radius:16px; background:linear-gradient(135deg,var(--sg-doc-primary-50),#ffffff); border:1px solid #e9e4ff; }
            .title small { font-size:12px; display:block; margin-top:5px; color:var(--sg-doc-primary); font-weight:850; letter-spacing:.03em; }
            .box { background:#fff; padding:0; border-radius:14px; font-size:12px; margin-bottom:18px; display:grid; grid-template-columns:1.2fr .85fr .85fr .85fr; gap:0; line-height:1.45; border:1px solid #e9e4ff; overflow:hidden; }
            .box div { padding:12px; border-right:1px solid #e9e4ff; }
            .box div:last-child { border-right:0; }
            .box strong { display:block; color:var(--sg-doc-primary); font-size:10px; text-transform:uppercase; letter-spacing:.05em; font-weight:900; margin-bottom:4px; }
            .items { width:100%; border-collapse:separate; border-spacing:0; font-size:12px; border:1px solid rgba(var(--sg-doc-primary-rgb),.16); border-radius:14px; overflow:hidden; }
            .items th { text-align:left; background:var(--sg-doc-primary-50); color:var(--sg-doc-primary-900); padding:10px 12px; font-weight:900; font-size:11px; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid rgba(var(--sg-doc-primary-rgb),.16); }
            .items td { border-bottom:1px solid #edf0f7; padding:10px 12px; line-height:1.45; }
            .items tr:last-child td { border-bottom:0; }
            .total td { font-size:14px; font-weight:950; border-top:2px solid var(--sg-doc-primary); background:var(--sg-doc-primary-50); color:var(--sg-doc-primary-900); }
            @media print {
                html, body { width:297mm; height:210mm; margin:0; padding:0; background:#fff; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
                .controls { display:none; }
                .sheet { width:297mm; min-height:210mm; height:auto; margin:0; padding:8mm 12mm; box-shadow:none; border-radius:0; border:0; }
                .header { margin-bottom:12px; padding-bottom:10px; }
                .title { margin:12px 0; font-size:18px; }
                .title small { font-size:11px; }
                .box { margin-bottom:12px; font-size:11px; }
                .box div { padding:9px; }
                .items { font-size:11px; }
                .items th { padding:8px 10px; font-size:10px; }
                .items td { padding:8px 10px; }
                .total td { font-size:13px; }
            }
        </style>
    </head>
    <body>
        <div class="controls">
            <button class="btn btn-primary" data-sige-print>Imprimir / Guardar PDF</button>
            <button class="btn btn-muted" data-sige-close>Fechar</button>
        </div>
        <div class="sheet">
            <div class="header">
                <div class="logo"><?php if($logo) echo '<img src="'.esc_url($logo).'">'; ?></div>
                <div class="info">
                    <strong style="font-size:14px; color:var(--sg-doc-primary-900);"><?php echo esc_html($perfil->nome_escola); ?></strong><br>
                    <?php echo esc_html($perfil->endereco_escola); ?><br>
                    NUIT: <?php echo esc_html($perfil->nuit); ?> | Tel: <?php echo esc_html($perfil->telefone_oficial); ?>
                </div>
            </div>
            <div class="title"><?php echo esc_html($titulo); ?> <br> <small>Ref: <?php echo esc_html($doc_num); ?></small></div>
            <div class="box">
                <div><strong>ALUNO:</strong> <?php echo esc_html($nome); ?></div>
                <div><strong>PROCESSO:</strong> <?php echo esc_html($processo); ?></div>
                <div><strong>TURMA:</strong> <?php echo esc_html($turma); ?></div>
                <div><strong>DATA:</strong> <?php echo wp_date('d/m/Y H:i'); ?></div>
            </div>
    <?php
}
function sige_print_template_footer($qr_data, $assinatura) {
    ?>
            <div style="margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end;">
                <div style="text-align: center;">
                    <canvas id="qr"></canvas>
                </div>
                <div style="text-align: center; border-top: 1px solid #000; width: 40%; font-size: 10px; padding-top: 5px;">
                    <?php echo esc_html($assinatura); ?><br>Processado por computador
                </div>
            </div>
        </div>
        <script <?php echo sige_csp_script_attr(); ?>>
            new QRious({element: document.getElementById('qr'), value: "<?php echo esc_js($qr_data); ?>", size: 80});
            document.querySelectorAll('[data-sige-print]').forEach(function(el){ el.addEventListener('click', function(){ window.print(); }); });
            document.querySelectorAll('[data-sige-close]').forEach(function(el){ el.addEventListener('click', function(){ window.close(); }); });
        </script>
    </body>
    </html>
    <?php
}
