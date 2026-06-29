<?php
/**
 * SIGE SoftGenial - Central de Mensagens WhatsApp
 * Ficheiro: includes/whatsapp-central.php
 *
 * Handlers AJAX que servem a página admin/whatsapp-central-view.php:
 *
 *   - sige_wppc_get_full        → devolve texto integral de uma mensagem da fila
 *   - sige_wppc_cancel          → marca uma pendente como 'cancelado'
 *   - sige_wppc_retry           → reabre uma falhada/cancelada (status → 'pendente', tentativas=0)
 *   - sige_wppc_delete          → elimina uma linha (apenas falhadas/canceladas/enviadas)
 *   - sige_wppc_force_cron      → força execução imediata do processador da queue
 *
 * Princípios:
 *   - NÃO toca em fórmulas financeiras/académicas.
 *   - Só lê/altera estado da própria fila WhatsApp.
 *   - Multi-tenant: todas as queries filtram por escola_id (sige_get_escola_id()).
 *   - Capability gate idêntico ao da Central (financeiro/secretário/director/admin).
 *   - Nonce em todos os endpoints (sige_wppc_nonce).
 *
 * @since 12.9.56
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// CAPABILITY GATE - quem pode operar a Central de Mensagens
// ============================================================================
if (!function_exists('sige_wppc_user_can')) {
    function sige_wppc_user_can(): bool {
        if (function_exists('sige_can') && sige_can('comunicacao.whatsapp_ver')) return true;
        return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            || current_user_can('sige_director')
            || current_user_can('sige_admin')
            || current_user_can('sige_admin_ti')
            || current_user_can('sige_financeiro')
            || current_user_can('sige_secretario')
            || current_user_can('sige_secretaria_geral');
    }
}

// ============================================================================
// HELPERS - normalização defensiva
// ============================================================================
if (!function_exists('sige_wppc_send_json_error')) {
    function sige_wppc_send_json_error(string $msg, int $code = 400): void {
        wp_send_json_error(['message' => $msg], $code);
    }
}

if (!function_exists('sige_wppc_check_nonce_or_die')) {
    function sige_wppc_check_nonce_or_die(): void {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field((string)$_POST['_wpnonce']) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_wppc_nonce')) {
            sige_wppc_send_json_error('Nonce inválido ou expirado. Recarregue a página.', 403);
        }
        if (!sige_wppc_user_can()) {
            sige_wppc_send_json_error('Sem permissão para esta operação.', 403);
        }
    }
}

if (!function_exists('sige_wppc_eid')) {
    function sige_wppc_eid(): int {
        return function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    }
}

if (!function_exists('sige_wppc_table')) {
    function sige_wppc_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_whatsapp_queue';
    }
}

// ----------------------------------------------------------------------------
// Carrega 1 linha da fila (validando escola_id) ou null se não existe.
// ----------------------------------------------------------------------------
if (!function_exists('sige_wppc_load_row')) {
    function sige_wppc_load_row(int $id): ?object {
        global $wpdb;
        $eid = sige_wppc_eid();
        $t   = sige_wppc_table();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t} WHERE id = %d AND escola_id = %d LIMIT 1",
            $id, $eid
        ));
        return $row ?: null;
    }
}

// ============================================================================
// AJAX: sige_wppc_get_full - texto integral de uma mensagem
// ============================================================================
add_action('wp_ajax_sige_wppc_get_full', 'sige_wppc_ajax_get_full');
if (!function_exists('sige_wppc_ajax_get_full')) {
    function sige_wppc_ajax_get_full() {
        sige_wppc_check_nonce_or_die();
        global $wpdb;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) {
            sige_wppc_send_json_error('ID inválido.');
        }

        $row = sige_wppc_load_row($id);
        if (!$row) {
            sige_wppc_send_json_error('Mensagem não encontrada nesta escola.', 404);
        }

        // Dados do aluno (nome) - só leitura, sem efeito colateral
        $aluno_nome = '';
        if (!empty($row->aluno_id)) {
            $aluno_nome = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT nome_completo FROM {$wpdb->prefix}sige_alunos
                 WHERE id = %d AND escola_id = %d LIMIT 1",
                (int)$row->aluno_id, sige_wppc_eid()
            ));
        }

        // Tenta decodificar guardian_meta (pode não existir como coluna)
        $guardian_meta = null;
        if (isset($row->guardian_meta) && !empty($row->guardian_meta)) {
            $decoded = json_decode((string)$row->guardian_meta, true);
            if (is_array($decoded)) $guardian_meta = $decoded;
        }

        wp_send_json_success([
            'id'             => (int) $row->id,
            'aluno_id'       => (int) ($row->aluno_id ?? 0),
            'aluno_nome'     => $aluno_nome,
            'tipo'           => (string) ($row->tipo ?? ''),
            'telefone'       => (string) ($row->telefone ?? ''),
            'status'         => (string) ($row->status ?? ''),
            'tentativas'     => (int) ($row->tentativas ?? 0),
            'criado_em'      => (string) ($row->criado_em ?? ''),
            'scheduled_at'   => isset($row->scheduled_at)   ? (string)$row->scheduled_at   : null,
            'enviado_em'     => (string) ($row->enviado_em ?? ''),
            'erro'           => (string) ($row->erro ?? ''),
            'priority'       => isset($row->priority)       ? (int)$row->priority          : null,
            'risk_flags'     => isset($row->risk_flags)     ? (string)$row->risk_flags     : null,
            'mensagem'       => (string) ($row->mensagem ?? ''),
            'guardian_meta'  => $guardian_meta,
        ]);
    }
}

// ============================================================================
// AJAX: sige_wppc_cancel - cancela uma mensagem pendente
// ----------------------------------------------------------------------------
// Regra: só cancela se status='pendente'. Não re-envia, não apaga.
// Não toca em valor / aluno / lançamento - apenas no estado da fila.
// ============================================================================
add_action('wp_ajax_sige_wppc_cancel', 'sige_wppc_ajax_cancel');
if (!function_exists('sige_wppc_ajax_cancel')) {
    function sige_wppc_ajax_cancel() {
        sige_wppc_check_nonce_or_die();
        global $wpdb;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_wppc_send_json_error('ID inválido.');

        $row = sige_wppc_load_row($id);
        if (!$row) sige_wppc_send_json_error('Mensagem não encontrada.', 404);

        if (!in_array((string)$row->status, ['pendente', 'forcar_envio'], true)) {
            sige_wppc_send_json_error('Só é possível cancelar mensagens pendentes (status actual: ' . $row->status . ').');
        }

        $t = sige_wppc_table();
        $ok = $wpdb->update(
            $t,
            [
                'status' => 'cancelado',
                'erro'   => 'Cancelada manualmente por ' . wp_get_current_user()->user_login . ' em ' . current_time('mysql'),
            ],
            ['id' => $id, 'escola_id' => sige_wppc_eid()],
            ['%s', '%s'],
            ['%d', '%d']
        );

        if ($ok === false) sige_wppc_send_json_error('Falha ao cancelar (BD).', 500);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wppc_cancel', ['id' => $id, 'tipo' => $row->tipo, 'aluno_id' => $row->aluno_id]);
        }

        wp_send_json_success(['id' => $id, 'novo_status' => 'cancelado']);
    }
}

// ============================================================================
// AJAX: sige_wppc_retry - reenvia uma falhada/cancelada
// ----------------------------------------------------------------------------
// Regra: só permite vinda de 'falhou' ou 'cancelado'.
// Reabre como 'pendente', tentativas=0, scheduled_at=now (sai no próximo ciclo).
// ============================================================================
add_action('wp_ajax_sige_wppc_retry', 'sige_wppc_ajax_retry');
if (!function_exists('sige_wppc_ajax_retry')) {
    function sige_wppc_ajax_retry() {
        sige_wppc_check_nonce_or_die();
        global $wpdb;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_wppc_send_json_error('ID inválido.');

        $row = sige_wppc_load_row($id);
        if (!$row) sige_wppc_send_json_error('Mensagem não encontrada.', 404);

        if (!in_array((string)$row->status, ['falhou', 'cancelado'], true)) {
            sige_wppc_send_json_error('Só é possível re-enviar mensagens falhadas ou canceladas.');
        }

        $t = sige_wppc_table();

        // Construímos $data dinâmico para suportar instalações sem colunas Guardian.
        $data    = ['status' => 'pendente', 'tentativas' => 0, 'erro' => null];
        $formats = ['%s', '%d', '%s'];

        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at')) {
            $data['scheduled_at'] = current_time('mysql');
            $formats[]            = '%s';
        }
        if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('next_retry_at')) {
            $data['next_retry_at'] = null;
            $formats[]             = '%s';
        }

        $ok = $wpdb->update(
            $t,
            $data,
            ['id' => $id, 'escola_id' => sige_wppc_eid()],
            $formats,
            ['%d', '%d']
        );

        if ($ok === false) sige_wppc_send_json_error('Falha ao re-enviar (BD).', 500);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wppc_retry', ['id' => $id, 'tipo' => $row->tipo, 'aluno_id' => $row->aluno_id]);
        }

        wp_send_json_success(['id' => $id, 'novo_status' => 'pendente']);
    }
}

// ============================================================================
// AJAX: sige_wppc_delete - apaga linha já resolvida
// ----------------------------------------------------------------------------
// Regra: só apaga 'enviado', 'falhou' ou 'cancelado'. Nunca pendentes.
// ============================================================================
add_action('wp_ajax_sige_wppc_delete', 'sige_wppc_ajax_delete');
if (!function_exists('sige_wppc_ajax_delete')) {
    function sige_wppc_ajax_delete() {
        sige_wppc_check_nonce_or_die();
        global $wpdb;

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_wppc_send_json_error('ID inválido.');

        $row = sige_wppc_load_row($id);
        if (!$row) sige_wppc_send_json_error('Mensagem não encontrada.', 404);

        if (!in_array((string)$row->status, ['enviado', 'falhou', 'cancelado'], true)) {
            sige_wppc_send_json_error('Não é possível eliminar mensagens em curso. Cancele primeiro.');
        }

        $t  = sige_wppc_table();
        $ok = $wpdb->delete($t, ['id' => $id, 'escola_id' => sige_wppc_eid()], ['%d', '%d']);

        if ($ok === false) sige_wppc_send_json_error('Falha ao eliminar (BD).', 500);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wppc_delete', ['id' => $id, 'tipo' => $row->tipo]);
        }

        wp_send_json_success(['id' => $id, 'eliminado' => true]);
    }
}

// ============================================================================
// AJAX: sige_wppc_pending_receipts_list - listar todos os pending receipts
// ----------------------------------------------------------------------------
// [v12.9.64] Ajuda a escola a tratar manualmente os encarregados que pediram
// o link mas não foram apanhados pelo auto-responder (frases ambíguas, webhook
// Z-API mal configurado, race conditions, etc.).
//
// Estratégia: percorre as mensagens de recibo enviadas/pendentes nas últimas
// 48h, para cada telefone único pergunta ao transient store se há pending,
// devolve a lista enriquecida com nome do aluno, telefone, número de recibo,
// data, e - se possível - última resposta detectada do encarregado.
// ============================================================================
add_action('wp_ajax_sige_wppc_pending_receipts_list', 'sige_wppc_ajax_pending_list');
if (!function_exists('sige_wppc_ajax_pending_list')) {
    function sige_wppc_ajax_pending_list() {
        sige_wppc_check_nonce_or_die();
        global $wpdb;

        if (!function_exists('sige_wpp_get_pending_receipt')) {
            sige_wppc_send_json_error('Sistema de pending_receipts indisponível.', 500);
        }

        $eid = sige_wppc_eid();
        $t   = sige_wppc_table();

        // Procura mensagens de recibo nas últimas 48h, agrupadas por telefone+aluno
        // (para não duplicar quando pai e mãe têm pending separados)
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT q.id, q.telefone, q.aluno_id, q.criado_em, q.enviado_em, q.status, q.mensagem,
                    a.nome_completo AS aluno_nome
               FROM {$t} q
               LEFT JOIN {$wpdb->prefix}sige_alunos a ON a.id = q.aluno_id
              WHERE q.escola_id = %d
                AND q.tipo = 'recibo'
                AND q.criado_em >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
              ORDER BY q.criado_em DESC",
            $eid
        ));

        $pendentes = [];
        $seen = []; // dedup por telefone

        foreach ($rows as $r) {
            $tel = (string)$r->telefone;
            if ($tel === '' || isset($seen[$tel])) continue;
            $seen[$tel] = true;

            $pending = sige_wpp_get_pending_receipt($eid, $tel);
            if (!$pending || empty($pending['links'])) continue;

            // Extrai número de recibo do texto da mensagem para mostrar à secretária
            $recibo_num = '';
            if (preg_match('/\b([A-Z]{2,6}-\d{4}-\d{6})\b/', (string)$r->mensagem, $m)) {
                $recibo_num = $m[1];
            }

            // Última resposta do encarregado (procura por "última resposta" no contacto)
            $last_reply = '';
            $last_reply_at = '';
            // [v12.9.64.1] Tabela canónica é 'sige_whatsapp_contacts' (não wpp_contacts).
            // [v12.9.64.3] Coluna é 'telefone' (português), não 'phone'. Schema confirmado
            // em whatsapp-recovery-mode.php linha 39: "telefone VARCHAR(50) NOT NULL".
            // Verifica se existe antes de consultar (algumas instalações podem não a ter).
            $contacts_table = $wpdb->prefix . 'sige_whatsapp_contacts';
            $contacts_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$contacts_table}'") === $contacts_table);
            if ($contacts_exists) {
                $contact = $wpdb->get_row($wpdb->prepare(
                    "SELECT last_reply_at, consent_status
                       FROM {$contacts_table}
                      WHERE escola_id = %d AND telefone = %s LIMIT 1",
                    $eid, preg_replace('/[^0-9]/', '', $tel)
                ));
                if ($contact) {
                    $last_reply_at = (string)($contact->last_reply_at ?? '');
                }
            }

            // Nome do encarregado (pai/mãe) - derivado da tabela alunos.
            // [v12.9.64.1] Defensivo: nem todas as instalações têm contacto_pai/mae.
            // Algumas só têm contacto_encarregado. Faz SELECT * e usa o que existir.
            $enc_nome = '';
            if ($r->aluno_id) {
                $aluno_full = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sige_alunos
                      WHERE id = %d AND escola_id = %d LIMIT 1",
                    (int)$r->aluno_id, $eid
                ));
                if ($aluno_full) {
                    $tel_normalizado = preg_replace('/[^0-9]/', '', $tel);
                    $cand_pai = preg_replace('/[^0-9]/', '', (string)($aluno_full->contacto_pai ?? ''));
                    $cand_mae = preg_replace('/[^0-9]/', '', (string)($aluno_full->contacto_mae ?? ''));
                    $cand_enc = preg_replace('/[^0-9]/', '', (string)($aluno_full->contacto_encarregado ?? ''));

                    if ($cand_pai !== '' && strpos($tel_normalizado, $cand_pai) !== false) {
                        $enc_nome = trim((string)($aluno_full->nome_pai ?? ''));
                    } elseif ($cand_mae !== '' && strpos($tel_normalizado, $cand_mae) !== false) {
                        $enc_nome = trim((string)($aluno_full->nome_mae ?? ''));
                    } elseif ($cand_enc !== '' && strpos($tel_normalizado, $cand_enc) !== false) {
                        // Encarregado registado - preferimos pai, depois mãe
                        $enc_nome = trim((string)($aluno_full->nome_pai ?? ($aluno_full->nome_mae ?? '')));
                    }
                    if ($enc_nome === '') $enc_nome = 'Encarregado(a)';
                }
            }

            $pendentes[] = [
                'queue_id'     => (int)$r->id,
                'aluno_id'     => (int)$r->aluno_id,
                'aluno_nome'   => (string)($r->aluno_nome ?? '-'),
                'encarregado'  => $enc_nome,
                'telefone'     => $tel,
                'recibo'       => $recibo_num,
                'criado_em'    => (string)$r->criado_em,
                'enviado_em'   => (string)($r->enviado_em ?? ''),
                'status_msg'   => (string)$r->status,
                'last_reply_at' => $last_reply_at,
                'links'        => $pending['links'],
            ];
        }

        wp_send_json_success([
            'count'      => count($pendentes),
            'pendentes'  => $pendentes,
        ]);
    }
}

// ============================================================================
// AJAX: sige_wppc_send_link_now - envia link do recibo manualmente, mantendo
// humanidade (priority=1 + scheduled_at=NOW + delay 0-30s do cron tick)
// ----------------------------------------------------------------------------
// O link sai com formato conversacional ("Claro 😊 segue o link..."), tal como
// o auto-responder, mas sob comando explícito da secretária.
// ============================================================================
add_action('wp_ajax_sige_wppc_send_link_now', 'sige_wppc_ajax_send_link_now');
if (!function_exists('sige_wppc_ajax_send_link_now')) {
    function sige_wppc_ajax_send_link_now() {
        sige_wppc_check_nonce_or_die();

        $tel = isset($_POST['telefone']) ? sanitize_text_field((string)$_POST['telefone']) : '';
        $tel = preg_replace('/[^0-9]/', '', $tel);
        if ($tel === '') sige_wppc_send_json_error('Telefone inválido.');

        $eid = sige_wppc_eid();
        if (!function_exists('sige_wpp_get_pending_receipt') || !function_exists('sige_wpp_queue_receipt_link_after_yes')) {
            sige_wppc_send_json_error('Sistema de pending receipts indisponível.', 500);
        }

        $pending = sige_wpp_get_pending_receipt($eid, $tel);
        if (!$pending || empty($pending['links'])) {
            sige_wppc_send_json_error('Não há pending receipt para este telefone.');
        }

        // Reaproveita exactamente o mesmo flow do auto-responder
        // (delay 0-30s + abertura conversacional + priority=1)
        $r = sige_wpp_queue_receipt_link_after_yes($eid, $tel, $pending);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wppc_send_link_manual', [
                'telefone' => $tel,
                'aluno_id' => (int)($pending['aluno_id'] ?? 0),
                'links'    => $pending['links'],
            ]);
        }

        wp_send_json_success([
            'sent'    => true,
            'message' => 'Link enfileirado. Vai sair em 0-30 segundos.',
        ]);
    }
}

// ============================================================================
// AJAX: sige_wppc_clear_pending - marca como tratado sem enviar
// ----------------------------------------------------------------------------
// Usado quando a secretária já enviou link manualmente fora do sistema, ou
// quando o pai disse não/parar e queremos remover da lista visualmente.
// ============================================================================
add_action('wp_ajax_sige_wppc_clear_pending', 'sige_wppc_ajax_clear_pending');
if (!function_exists('sige_wppc_ajax_clear_pending')) {
    function sige_wppc_ajax_clear_pending() {
        sige_wppc_check_nonce_or_die();

        $tel = isset($_POST['telefone']) ? sanitize_text_field((string)$_POST['telefone']) : '';
        $tel = preg_replace('/[^0-9]/', '', $tel);
        if ($tel === '') sige_wppc_send_json_error('Telefone inválido.');

        $eid = sige_wppc_eid();
        if (function_exists('sige_wpp_clear_pending_receipt')) {
            sige_wpp_clear_pending_receipt($eid, $tel);
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wppc_clear_pending_manual', ['telefone' => $tel]);
        }

        wp_send_json_success(['cleared' => true, 'message' => 'Pendente removido.']);
    }
}

// ============================================================================
// AJAX: sige_wppc_pull_forward - distribuir mensagens reagendadas para o dia
// corrente respeitando spread-load (v12.9.62)
// ----------------------------------------------------------------------------
// Pega em todas as mensagens com scheduled_at futuro (agendadas para amanhã,
// depois de amanhã, etc.) e recalcula via sige_notify_spread_reschedule.
// Resultado: as que cabem hoje passam para hoje, as que não cabem ficam para
// amanhã (mas distribuídas, não empilhadas).
// ============================================================================
add_action('wp_ajax_sige_wppc_pull_forward', 'sige_wppc_ajax_pull_forward');
if (!function_exists('sige_wppc_ajax_pull_forward')) {
    function sige_wppc_ajax_pull_forward() {
        sige_wppc_check_nonce_or_die();

        if (!function_exists('sige_notify_pull_forward_pending')) {
            sige_wppc_send_json_error('Função de redistribuição indisponível.', 500);
        }

        $eid = sige_wppc_eid();
        $stats = sige_notify_pull_forward_pending($eid);

        $msg_parts = [];
        $msg_parts[] = "{$stats['scanned']} avaliada(s)";
        if (!empty($stats['pulled_today']))  $msg_parts[] = "{$stats['pulled_today']} trazida(s) para hoje";
        if (!empty($stats['kept_future']))   $msg_parts[] = "{$stats['kept_future']} mantida(s) para próximos dias (capacidade hoje esgotada)";

        wp_send_json_success([
            'stats'   => $stats,
            'message' => implode(' • ', $msg_parts),
        ]);
    }
}

// ============================================================================
// AJAX: sige_wppc_force_cron - corre o processador da queue
// ----------------------------------------------------------------------------
// Reaproveita a função canónica sige_wpp_process_queue_engine() do cron-tasks.
// ============================================================================
add_action('wp_ajax_sige_wppc_force_cron', 'sige_wppc_ajax_force_cron');
if (!function_exists('sige_wppc_ajax_force_cron')) {
    function sige_wppc_ajax_force_cron() {
        sige_wppc_check_nonce_or_die();

        if (!function_exists('sige_wpp_process_queue_engine')) {
            sige_wppc_send_json_error('Engine de processamento indisponível.', 500);
        }

        $t0 = microtime(true);
        $result = @sige_wpp_process_queue_engine([
            'manual' => true,
            'limit'  => (int) apply_filters('sige_wpp_queue_batch_limit', 5),
            'rescue' => true,
        ]);
        $dt = round((microtime(true) - $t0) * 1000);

        $sent = (int)($result['enviadas'] ?? 0);
        $deferred = (int)($result['adiadas'] ?? 0);
        $rescued = (int)($result['rescue_receipts']['rescued'] ?? 0);
        $msg = 'Processador da fila executado.';
        if ($rescued > 0) $msg .= ' Recibos reprogramados: ' . $rescued . '.';
        if ($sent > 0) $msg .= ' Enviadas agora: ' . $sent . '.';
        if ($deferred > 0) $msg .= ' Adiadas por segurança: ' . $deferred . '.';

        wp_send_json_success([
            'forced'      => true,
            'duration_ms' => $dt,
            'message'     => $msg,
            'result'      => $result,
        ]);
    }
}

// ============================================================================
// AJAX: sige_wppc_backfill_pending - registar pending_receipt para mensagens
// de recibo já na fila (criadas antes da v12.9.61)
// ----------------------------------------------------------------------------
// [v12.9.61] One-shot fixer. Mensagens de recibo criadas com a v12.9.58/59/60
// (entre o bug que removeu link inline e a v12.9.61 que ressuscitou
// pending_receipt) NÃO têm transient registado. Quando o pai responder "Sim"
// o auto-responder não tem o que enviar.
//
// Este endpoint percorre todas as mensagens de recibo na fila com status
// pendente/forcar_envio, extrai o número do recibo do texto via regex
// "REC-YYYY-NNNNNN", reconstrói o URL público via sige_recibo_url_publica,
// e regista pending_receipt (transient 48h) para cada uma.
//
// IDEMPOTENTE: se o pending já existe (transient activo), salta. Pode ser
// corrido as vezes que quiserem sem efeito acumulado.
//
// Multi-tenant: filtra por escola_id. Cada escola corre o backfill na sua.
// ============================================================================
add_action('wp_ajax_sige_wppc_backfill_pending', 'sige_wppc_ajax_backfill_pending');
if (!function_exists('sige_wppc_ajax_backfill_pending')) {
    function sige_wppc_ajax_backfill_pending() {
        sige_wppc_check_nonce_or_die();
        global $wpdb;

        $eid = sige_wppc_eid();
        $t   = sige_wppc_table();

        // Pré-condições: funções precisam estar disponíveis
        if (!function_exists('sige_wpp_store_pending_receipt')) {
            sige_wppc_send_json_error('sige_wpp_store_pending_receipt indisponível.', 500);
        }
        if (!function_exists('sige_recibo_url_publica')) {
            sige_wppc_send_json_error('sige_recibo_url_publica indisponível.', 500);
        }

        // Carrega mensagens elegíveis
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, telefone, aluno_id, mensagem
               FROM {$t}
              WHERE escola_id = %d
                AND tipo = 'recibo'
                AND status IN ('pendente', 'forcar_envio')",
            $eid
        ));

        $stats = [
            'scanned'         => 0,
            'already_set'     => 0, // pending já existia
            'set'             => 0, // pending registado agora
            'no_recibo_match' => 0, // regex não apanhou número
            'no_url_built'    => 0, // sige_recibo_url_publica devolveu vazio
        ];

        $detalhes = []; // amostra dos primeiros 5 sucessos para audit

        foreach ($rows as $r) {
            $stats['scanned']++;

            $tel = (string)$r->telefone;
            if ($tel === '') continue;

            // Idempotência: já tem pending? salta.
            if (function_exists('sige_wpp_get_pending_receipt') && sige_wpp_get_pending_receipt($eid, $tel)) {
                $stats['already_set']++;
                continue;
            }

            // Extrair número de recibo do texto (formato REC-YYYY-NNNNNN, prefixo
            // pode variar mas mantemos restrito ao padrão actual do plugin).
            // Regex: prefixo 2-6 letras maiúsculas + ano 4 dígitos + sequência 6 dígitos
            if (!preg_match('/\b([A-Z]{2,6})-(\d{4})-(\d{6})\b/', (string)$r->mensagem, $m)) {
                $stats['no_recibo_match']++;
                continue;
            }
            $recibo_num = $m[0];

            $url = sige_recibo_url_publica($recibo_num, (int)$r->aluno_id);
            if (!$url) {
                $stats['no_url_built']++;
                continue;
            }

            sige_wpp_store_pending_receipt(
                $eid,
                $tel,
                (int)$r->aluno_id,
                [$url],
                (string)$r->mensagem
            );
            $stats['set']++;

            if (count($detalhes) < 5) {
                $detalhes[] = [
                    'queue_id'  => (int)$r->id,
                    'telefone'  => $tel,
                    'aluno_id'  => (int)$r->aluno_id,
                    'recibo'    => $recibo_num,
                ];
            }
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wppc_backfill_pending_receipts', $stats);
        }

        $msg_parts = [];
        if ($stats['set'] > 0)             $msg_parts[] = "{$stats['set']} pendente(s) registado(s)";
        if ($stats['already_set'] > 0)     $msg_parts[] = "{$stats['already_set']} já tinha(m) pending";
        if ($stats['no_recibo_match'] > 0) $msg_parts[] = "{$stats['no_recibo_match']} sem número de recibo legível";
        if ($stats['no_url_built'] > 0)    $msg_parts[] = "{$stats['no_url_built']} URL não construída";
        if ($stats['scanned'] === 0)       $msg_parts[] = 'Nenhuma mensagem de recibo na fila';
        $message = implode(' • ', $msg_parts) ?: 'Sem alterações.';

        wp_send_json_success([
            'stats'    => $stats,
            'detalhes' => $detalhes,
            'message'  => $message,
        ]);
    }
}

// ============================================================================
// Helper: contar quantas mensagens de recibo precisam de backfill
// ----------------------------------------------------------------------------
// Usado na view para decidir se mostra o botão amarelo.
// ============================================================================
if (!function_exists('sige_wppc_count_recibos_sem_pending')) {
    function sige_wppc_count_recibos_sem_pending(): int {
        global $wpdb;
        $eid = sige_wppc_eid();
        $t   = sige_wppc_table();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT telefone FROM {$t}
              WHERE escola_id = %d
                AND tipo = 'recibo'
                AND status IN ('pendente', 'forcar_envio')",
            $eid
        ));

        if (!function_exists('sige_wpp_get_pending_receipt')) return count($rows);

        $sem_pending = 0;
        $seen = [];
        foreach ($rows as $r) {
            $tel = (string)$r->telefone;
            if ($tel === '' || isset($seen[$tel])) continue;
            $seen[$tel] = true;
            if (!sige_wpp_get_pending_receipt($eid, $tel)) {
                $sem_pending++;
            }
        }
        return $sem_pending;
    }
}
// ----------------------------------------------------------------------------
// [v12.9.59] Permite seleccionar várias linhas na Central e aplicar uma
// acção única a todas. Em vez de N HTTP calls, faz 1 só.
//
// Validação por linha (preserva regras das acções individuais):
//   - cancel : só status='pendente' ou 'forcar_envio'
//   - retry  : só status='falhou' ou 'cancelado'
//   - delete : só status='enviado','falhou','cancelado'
//
// Linhas que não cumprem o filtro são SKIP (não FAIL) - não são erro, apenas
// não aplicáveis. Resposta inclui sumário {ok, skip, fail}.
//
// Multi-tenant: todas as queries filtram por escola_id. IDs de outras
// escolas que cheguem por mistake são silenciosamente ignorados (skip).
//
// Limite: max 500 IDs por chamada (proteção contra timeouts e DoS).
// ============================================================================
add_action('wp_ajax_sige_wppc_bulk', 'sige_wppc_ajax_bulk');
if (!function_exists('sige_wppc_ajax_bulk')) {
    function sige_wppc_ajax_bulk() {
        sige_wppc_check_nonce_or_die();
        global $wpdb;

        $action_op = isset($_POST['op']) ? sanitize_key((string)$_POST['op']) : '';
        if (!in_array($action_op, ['cancel', 'retry', 'delete'], true)) {
            sige_wppc_send_json_error('Operação inválida.');
        }

        // IDs vêm como CSV ou array
        $ids_raw = isset($_POST['ids']) ? (array) $_POST['ids'] : [];
        if (count($ids_raw) === 1 && is_string($ids_raw[0]) && strpos($ids_raw[0], ',') !== false) {
            $ids_raw = explode(',', $ids_raw[0]);
        }
        $ids = [];
        foreach ($ids_raw as $v) {
            $i = (int) $v;
            if ($i > 0) $ids[] = $i;
        }
        $ids = array_values(array_unique($ids));

        if (empty($ids)) {
            sige_wppc_send_json_error('Nenhum ID seleccionado.');
        }
        if (count($ids) > 500) {
            sige_wppc_send_json_error('Limite de 500 mensagens por operação. Reduza a selecção.');
        }

        $eid = sige_wppc_eid();
        $t   = sige_wppc_table();

        // Carrega as linhas alvo numa só query (filtro por escola_id incluído)
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $params       = array_merge([$eid], $ids);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status, tipo, aluno_id FROM {$t}
              WHERE escola_id = %d AND id IN ({$placeholders})",
            $params
        ));

        // Mapas de validação por operação
        $allowed_status = [
            'cancel' => ['pendente', 'forcar_envio'],
            'retry'  => ['falhou', 'cancelado'],
            'delete' => ['enviado', 'falhou', 'cancelado'],
        ][$action_op];

        // Particiona IDs por aplicabilidade
        $apply_ids = [];
        $skip_ids  = [];
        $found_ids = [];
        foreach ($rows as $r) {
            $found_ids[] = (int) $r->id;
            if (in_array((string)$r->status, $allowed_status, true)) {
                $apply_ids[] = (int) $r->id;
            } else {
                $skip_ids[] = (int) $r->id;
            }
        }
        // IDs que vieram do front mas não existem na BD desta escola → skip
        $skip_ids = array_merge($skip_ids, array_diff($ids, $found_ids));
        $skip_count = count($skip_ids);

        if (empty($apply_ids)) {
            wp_send_json_success([
                'op'    => $action_op,
                'total' => count($ids),
                'ok'    => 0,
                'skip'  => $skip_count,
                'fail'  => 0,
                'message' => 'Nenhuma das mensagens seleccionadas é elegível para esta acção.',
            ]);
        }

        // ── EXECUTA EM LOTE ────────────────────────────────────────────────
        $apply_placeholders = implode(',', array_fill(0, count($apply_ids), '%d'));
        $apply_params       = array_merge([$eid], $apply_ids);
        $now_user           = wp_get_current_user()->user_login ?: 'system';
        $now_mysql          = current_time('mysql');
        $ok_count           = 0;
        $fail_count         = 0;

        if ($action_op === 'cancel') {
            $note = 'Cancelada em lote por ' . $now_user . ' em ' . $now_mysql;
            $ok = $wpdb->query($wpdb->prepare(
                "UPDATE {$t}
                    SET status = 'cancelado', erro = %s
                  WHERE escola_id = %d AND id IN ({$apply_placeholders})",
                array_merge([$note], $apply_params)
            ));
            $ok_count = $ok === false ? 0 : (int) $ok;
            $fail_count = $ok === false ? count($apply_ids) : 0;

        } elseif ($action_op === 'retry') {
            // Build UPDATE com colunas Guardian opcionais
            $sets   = ["status = 'pendente'", "tentativas = 0", "erro = NULL"];
            $extra_params = [];

            if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at')) {
                $sets[]         = 'scheduled_at = %s';
                $extra_params[] = $now_mysql;
            }
            if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('next_retry_at')) {
                $sets[] = 'next_retry_at = NULL';
            }

            $set_sql = implode(', ', $sets);
            $sql_params = array_merge($extra_params, $apply_params);

            $ok = $wpdb->query($wpdb->prepare(
                "UPDATE {$t}
                    SET {$set_sql}
                  WHERE escola_id = %d AND id IN ({$apply_placeholders})",
                $sql_params
            ));
            $ok_count = $ok === false ? 0 : (int) $ok;
            $fail_count = $ok === false ? count($apply_ids) : 0;

        } elseif ($action_op === 'delete') {
            $ok = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$t}
                  WHERE escola_id = %d AND id IN ({$apply_placeholders})",
                $apply_params
            ));
            $ok_count = $ok === false ? 0 : (int) $ok;
            $fail_count = $ok === false ? count($apply_ids) : 0;
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wppc_bulk_' . $action_op, [
                'total' => count($ids),
                'ok'    => $ok_count,
                'skip'  => $skip_count,
                'fail'  => $fail_count,
            ]);
        }

        $msg_parts = [];
        if ($ok_count > 0)   $msg_parts[] = "{$ok_count} aplicada" . ($ok_count > 1 ? 's' : '');
        if ($skip_count > 0) $msg_parts[] = "{$skip_count} ignorada" . ($skip_count > 1 ? 's' : '') . " (estado incompatível)";
        if ($fail_count > 0) $msg_parts[] = "{$fail_count} falhou" . ($fail_count > 1 ? 'aram' : '');
        $message = implode(' • ', $msg_parts) ?: 'Sem alterações.';

        wp_send_json_success([
            'op'      => $action_op,
            'total'   => count($ids),
            'ok'      => $ok_count,
            'skip'    => $skip_count,
            'fail'    => $fail_count,
            'message' => $message,
        ]);
    }
}
