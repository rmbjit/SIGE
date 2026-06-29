<?php
/**
 * SIGE SoftGenial - Data Efectiva de Pagamento (v12.9.66)
 * ==========================================================
 *
 * Permite à escola registar pagamentos com uma data efectiva diferente da
 * data actual (ex: pai pagou em 02/05 mas escola só registou em 05/05).
 * Esta data passa a ser a data lógica do pagamento para efeitos de extracto
 * diário, KPIs de caixa e relatórios.
 *
 * REGRAS CRÍTICAS:
 *   1. NUNCA permitir registo em data com caixa FECHADA. Bloqueio absoluto.
 *   2. Coluna `data_pagamento` (auditoria) continua intocada - sempre NOW().
 *   3. Coluna NOVA `data_efectiva` (DATE) guarda a data lógica.
 *   4. KPIs e extractos passam a usar COALESCE(data_efectiva, DATE(data_pagamento)).
 *   5. Função canónica sige_fin_registar_pagamento() NÃO é alterada.
 *      Em vez disso, fazemos UPDATE pós-inserção do recibo.
 *
 * FICHEIROS CANÓNICOS (NÃO TOCAR):
 *   - finance-core.php (registar_pagamento)
 *   - academic-logic.php
 *   - whatsapp-engine.php, whatsapp-recovery-mode.php, whatsapp-guardian.php
 *   - cron-tasks.php, class-sige-migration.php, db-handler.php
 *   - whatsapp-human-advanced.php
 *
 * @package SIGE\Finance
 * @since   12.9.66
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// 1) MIGRAÇÃO - adicionar coluna data_efectiva via auto-detect
// ----------------------------------------------------------------------------
// Corre 1× por instalação. Idempotente (verifica se coluna já existe).
// Não usa dbDelta - usa ALTER TABLE directo para evitar interferência com a
// migração canónica.
// ============================================================================
if (!function_exists('sige_fin_data_efectiva_migrate')) {
    function sige_fin_data_efectiva_migrate(): bool {
        global $wpdb;
        $tP = $wpdb->prefix . 'sige_fin_pagamentos';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$tP}'") !== $tP) return false;

        $col = $wpdb->get_results("SHOW COLUMNS FROM {$tP} LIKE 'data_efectiva'");
        if (!empty($col)) return true; // já existe

        // Adiciona coluna NULL após data_pagamento
        $r = $wpdb->query("ALTER TABLE {$tP} ADD COLUMN data_efectiva DATE NULL DEFAULT NULL AFTER data_pagamento");
        if ($r === false) return false;

        // Index para performance em queries de extracto/KPI
        @$wpdb->query("ALTER TABLE {$tP} ADD KEY idx_data_efectiva (escola_id, data_efectiva)");

        if (function_exists('sige_fin_log')) {
            sige_fin_log('migracao_data_efectiva', ['table' => $tP, 'success' => true]);
        }
        return true;
    }
}

// Auto-detect: corre na activação E em cada admin_init até estar OK (idempotente)
add_action('admin_init', function () {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    sige_fin_data_efectiva_migrate();
});

// ============================================================================
// 2) HELPER - verificar se caixa está aberta numa data específica
// ----------------------------------------------------------------------------
// Devolve:
//   ['aberta' => true/false, 'status' => 'aberta'|'fechada'|'sem_registo', 'data' => '2026-05-02']
//
// Regras:
//   - Se NÃO existe linha em sige_fin_fechos_caixa para a data → CAIXA ABERTA
//     (caixa nunca foi fechada nesse dia)
//   - Se existe linha com status='fechado' → CAIXA FECHADA (bloqueio)
//   - Se existe linha com status='reaberto' → CAIXA ABERTA
//   - Datas FUTURAS → bloqueia (não faz sentido pagar em data futura)
//   - Datas > 90 dias atrás → bloqueia (limite de retroactividade)
// ============================================================================
if (!function_exists('sige_fin_caixa_status_data')) {
    function sige_fin_caixa_status_data(string $data, ?int $escola_id = null): array {
        global $wpdb;
        $eid = $escola_id ?? (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        $out = ['aberta' => false, 'status' => 'erro', 'data' => $data, 'mensagem' => ''];

        // Validação formato
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $out['mensagem'] = 'Formato de data inválido.';
            return $out;
        }

        // Validação intervalo: não permitir futuro nem > 90 dias atrás
        $hoje = current_time('Y-m-d');
        if ($data > $hoje) {
            $out['status'] = 'futuro';
            $out['mensagem'] = 'Não é possível registar pagamento em data futura.';
            return $out;
        }
        $diff_dias = (strtotime($hoje) - strtotime($data)) / 86400;
        if ($diff_dias > 90) {
            $out['status'] = 'muito_antiga';
            $out['mensagem'] = 'Data demasiado antiga (limite: 90 dias). Contacte a direcção.';
            return $out;
        }

        $tF = $wpdb->prefix . 'sige_fin_fechos_caixa';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tF}'") !== $tF) {
            // Sem tabela de fechos → assume aberto (instalação inicial)
            $out['aberta'] = true;
            $out['status'] = 'sem_tabela';
            $out['mensagem'] = 'Caixa aberta (sem tabela de fechos).';
            return $out;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$tF}
              WHERE escola_id = %d AND data_caixa = %s
              ORDER BY id DESC LIMIT 1",
            $eid, $data
        ));

        if (!$row) {
            $out['aberta'] = true;
            $out['status'] = 'sem_registo';
            $out['mensagem'] = 'Caixa aberta (sem fecho registado para esta data).';
            return $out;
        }

        $st = (string) $row->status;
        if ($st === 'fechado') {
            $out['aberta'] = false;
            $out['status'] = 'fechado';
            $out['mensagem'] = 'Caixa do dia ' . wp_date('d/m/Y', strtotime($data)) . ' está FECHADA. Para registar nesta data, peça ao Director para reabrir.';
        } else { // reaberto
            $out['aberta'] = true;
            $out['status'] = 'reaberto';
            $out['mensagem'] = 'Caixa reaberta. Pode registar.';
        }
        return $out;
    }
}

// ============================================================================
// 3) AJAX endpoint - frontend valida antes de submeter
// ============================================================================
add_action('wp_ajax_sige_check_caixa_data', 'sige_ajax_check_caixa_data');
if (!function_exists('sige_ajax_check_caixa_data')) {
    function sige_ajax_check_caixa_data() {
        // Capability check
        $allowed = ['manage_options','sige_director','sige_admin','sige_admin_ti','sige_financeiro','sige_secretario','sige_secretaria_geral'];
        $can = false;
        foreach ($allowed as $cap) { if (current_user_can($cap)) { $can = true; break; } }
        if (!$can) wp_send_json_error(['message' => 'Sem permissão.'], 403);

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field((string)$_POST['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_check_caixa_data')) {
            wp_send_json_error(['message' => 'Nonce inválido. Recarregue a página.'], 403);
        }

        $data = isset($_POST['data']) ? sanitize_text_field((string)$_POST['data']) : '';
        $st = sige_fin_caixa_status_data($data);
        wp_send_json_success($st);
    }
}

// ============================================================================
// 4) UPDATE pós-inserção - preenche data_efectiva no(s) recibo(s) gerado(s)
// ----------------------------------------------------------------------------
// Chamado depois de sige_fin_registar_pagamento() ter sucesso. Como a função
// canónica não retorna o pagamento_id no contexto, identificamos pelo
// recibo_numero (único) e pela escola, actualizando todas as linhas que
// pertencem ao mesmo recibo.
//
// Validação: re-verifica caixa antes de aplicar (race condition: alguém
// fechou caixa entre o check e o submit).
// ============================================================================
if (!function_exists('sige_fin_aplicar_data_efectiva')) {
    function sige_fin_aplicar_data_efectiva(string $recibo_numero, string $data_efectiva, ?int $escola_id = null): array {
        global $wpdb;
        $eid = $escola_id ?? (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($eid <= 0) {
            return ['ok' => false, 'message' => 'Contexto de escola invalido.'];
        }

        // Re-validação da caixa (race condition)
        $st = sige_fin_caixa_status_data($data_efectiva, $eid);
        if (!$st['aberta']) {
            return ['ok' => false, 'message' => $st['mensagem']];
        }

        $tP = $wpdb->prefix . 'sige_fin_pagamentos';

        // Confirma que coluna existe (caso a migração não tenha corrido por algum motivo)
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$tP} LIKE 'data_efectiva'");
        if (empty($col)) {
            sige_fin_data_efectiva_migrate();
            $col = $wpdb->get_results("SHOW COLUMNS FROM {$tP} LIKE 'data_efectiva'");
            if (empty($col)) {
                return ['ok' => false, 'message' => 'Coluna data_efectiva ausente; migração falhou.'];
            }
        }

        $r = $wpdb->update(
            $tP,
            ['data_efectiva' => $data_efectiva],
            ['recibo_numero' => $recibo_numero, 'escola_id' => $eid]
        );

        if ($r === false) {
            return ['ok' => false, 'message' => 'Erro SQL: ' . $wpdb->last_error];
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('data_efectiva_aplicada', [
                'recibo' => $recibo_numero, 'data_efectiva' => $data_efectiva, 'rows_updated' => $r,
            ]);
        }

        return ['ok' => true, 'rows_updated' => (int)$r, 'message' => 'Data efectiva aplicada.'];
    }
}

// ============================================================================
// 5) HELPER SQL - devolve cláusula para queries usarem data_efectiva quando disponível
// ----------------------------------------------------------------------------
// Uso pelos extractos e KPIs:
//   $clause = sige_fin_data_pag_sql_clause();  // "COALESCE(data_efectiva, DATE(data_pagamento))"
//
// Detecta automaticamente se a coluna existe; se não, devolve apenas
// "DATE(data_pagamento)" para retrocompatibilidade.
// ============================================================================
if (!function_exists('sige_fin_data_pag_sql_clause')) {
    function sige_fin_data_pag_sql_clause(string $alias_table = ''): string {
        static $cache = null;
        if ($cache === null) {
            global $wpdb;
            $tP = $wpdb->prefix . 'sige_fin_pagamentos';
            $col = $wpdb->get_results("SHOW COLUMNS FROM {$tP} LIKE 'data_efectiva'");
            $cache = !empty($col);
        }
        $prefix = $alias_table !== '' ? rtrim($alias_table, '.') . '.' : '';
        if ($cache) {
            return "COALESCE({$prefix}data_efectiva, DATE({$prefix}data_pagamento))";
        }
        return "DATE({$prefix}data_pagamento)";
    }
}

// ============================================================================
// 6) HELPER de exibicao - data logica do pagamento para o RECIBO (v12.15.20)
// ----------------------------------------------------------------------------
// O recibo deve mostrar a data efectiva (a data em que o pagamento foi
// recebido) como "data do pagamento", igual ao extracto/KPI. A coluna
// data_pagamento (carimbo de registo/auditoria) continua intocada e e mostrada
// como nota quando difere. Esta funcao NAO altera valores, saldos nem formulas;
// e apenas de exibicao no documento.
//
// Devolve:
//   ['dia' => 'YYYY-MM-DD', 'difere' => bool]
//     dia    = data efectiva quando valida; senao o dia do registo.
//     difere = true quando ha data efectiva valida num dia diferente do registo.
// ============================================================================
if (!function_exists('sige_fin_recibo_data_efectiva_dia')) {
    function sige_fin_recibo_data_efectiva_dia($data_pagamento, $data_efectiva = null): array {
        $reg_dia = substr((string) $data_pagamento, 0, 10);
        $de = is_string($data_efectiva) ? trim($data_efectiva) : '';
        $de_ok = ($de !== '' && $de !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}/', $de));
        $ef_dia = $de_ok ? substr($de, 0, 10) : $reg_dia;
        return ['dia' => $ef_dia, 'difere' => ($de_ok && $ef_dia !== $reg_dia)];
    }
}
