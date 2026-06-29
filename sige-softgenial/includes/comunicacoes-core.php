<?php
/**
 * SIGE SoftGenial - Central de Comunicacoes: nucleo de normalizacao (cross-canal)
 * Ficheiro: includes/comunicacoes-core.php
 *
 * Camada de normalizacao que unifica as filas de E-MAIL (sige_email_queue) e
 * WhatsApp (sige_whatsapp_queue) num MODELO CANONICO comum. A vista consome
 * apenas este modelo, por isso fica simples e estavel mesmo que entre um terceiro
 * canal no futuro (ex. SMS).
 *
 * Principios:
 *   - NAO altera o subsistema de WhatsApp (apenas le e aplica as MESMAS regras de
 *     estado que o Central de WhatsApp ja usa). A camada fica isolada aqui.
 *   - Multi-tenant: escola_id em TODAS as queries.
 *   - Reutiliza a seguranca do email-central (nonce sige_emc_nonce, capability,
 *     prepared statements, auditoria). Requer email-central.php carregado antes.
 *
 * Estados canonicos: pendente | a_enviar | enviado | falhou | cancelado
 */

if (!defined('ABSPATH')) exit;

/* ============================================================================
 * Mapas de estado (raw por canal <-> canonico)
 * ========================================================================== */

if (!function_exists('sige_comm_estados_canonicos')) {
    function sige_comm_estados_canonicos(): array {
        return [
            'pendente'  => 'Pendente',
            'a_enviar'  => 'A enviar',
            'enviado'   => 'Enviado',
            'falhou'    => 'Falhou',
            'cancelado' => 'Cancelado',
        ];
    }
}

// raw (por canal) que correspondem a cada estado canonico (para filtrar).
if (!function_exists('sige_comm_raw_por_estado')) {
    function sige_comm_raw_por_estado(string $canal, string $estado): array {
        $email = [
            'pendente'  => ['pending'],
            'a_enviar'  => ['sending'],
            'enviado'   => ['sent'],
            'falhou'    => ['failed'],
            'cancelado' => ['cancelled'],
        ];
        $wpp = [
            'pendente'  => ['pendente', 'agendada', 'forcar_envio'],
            'a_enviar'  => [],
            'enviado'   => ['enviado'],
            'falhou'    => ['falhou'],
            'cancelado' => ['cancelado'],
        ];
        $m = $canal === 'whatsapp' ? $wpp : $email;
        return $m[$estado] ?? [];
    }
}

// raw -> canonico (para mostrar o estado de cada linha).
if (!function_exists('sige_comm_estado_canonico')) {
    function sige_comm_estado_canonico(string $canal, string $raw): string {
        if ($canal === 'whatsapp') {
            switch ($raw) {
                case 'enviado':   return 'enviado';
                case 'falhou':    return 'falhou';
                case 'cancelado': return 'cancelado';
                default:          return 'pendente'; // pendente, agendada, forcar_envio
            }
        }
        switch ($raw) {
            case 'sending':   return 'a_enviar';
            case 'sent':      return 'enviado';
            case 'failed':    return 'falhou';
            case 'cancelled': return 'cancelado';
            default:          return 'pendente';
        }
    }
}

if (!function_exists('sige_comm_email_table')) {
    function sige_comm_email_table(): string {
        return function_exists('sige_email_queue_table') ? sige_email_queue_table() : $GLOBALS['wpdb']->prefix . 'sige_email_queue';
    }
}
if (!function_exists('sige_comm_wpp_table')) {
    function sige_comm_wpp_table(): string {
        return $GLOBALS['wpdb']->prefix . 'sige_whatsapp_queue';
    }
}

/* ============================================================================
 * Estatisticas unificadas (por estado canonico, ambos os canais)
 * ========================================================================== */
if (!function_exists('sige_comm_stats')) {
    function sige_comm_stats(): array {
        global $wpdb;
        $eid = sige_emc_eid();
        $out = ['total' => 0, 'pendente' => 0, 'a_enviar' => 0, 'enviado' => 0, 'falhou' => 0, 'cancelado' => 0,
                'email' => 0, 'whatsapp' => 0];

        $te = sige_comm_email_table();
        $rowsE = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) n FROM {$te} WHERE escola_id=%d GROUP BY status", $eid));
        foreach ((array) $rowsE as $r) {
            $c = sige_comm_estado_canonico('email', (string) $r->status);
            $out[$c] = ($out[$c] ?? 0) + (int) $r->n;
            $out['email'] += (int) $r->n;
            $out['total'] += (int) $r->n;
        }
        $tw = sige_comm_wpp_table();
        $rowsW = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) n FROM {$tw} WHERE escola_id=%d GROUP BY status", $eid));
        foreach ((array) $rowsW as $r) {
            $c = sige_comm_estado_canonico('whatsapp', (string) $r->status);
            $out[$c] = ($out[$c] ?? 0) + (int) $r->n;
            $out['whatsapp'] += (int) $r->n;
            $out['total'] += (int) $r->n;
        }
        return $out;
    }
}

/* ============================================================================
 * Query unificada (UNION ALL, paginada em SQL, escola-scoped)
 *   $filtros = ['canal' => todos|email|whatsapp, 'estado' => all|<canonico>,
 *               'pagina' => int, 'por_pagina' => int]
 *   devolve ['rows' => [modelo canonico...], 'total' => int]
 * ========================================================================== */
if (!function_exists('sige_comm_query')) {
    function sige_comm_query(array $filtros): array {
        global $wpdb;
        $eid   = sige_emc_eid();
        $canal = in_array($filtros['canal'] ?? 'todos', ['todos', 'email', 'whatsapp'], true) ? ($filtros['canal'] ?? 'todos') : 'todos';
        $estado = (string) ($filtros['estado'] ?? 'all');
        $por_pagina = max(1, min(100, (int) ($filtros['por_pagina'] ?? 20)));
        $pagina = max(1, (int) ($filtros['pagina'] ?? 1));

        $te = sige_comm_email_table();
        $tw = sige_comm_wpp_table();

        // Constroi cada metade como (sql, params, count_sql, count_params).
        $partes = [];   // selects para o UNION
        $params = [];   // params do UNION (ordem)
        $total  = 0;

        // --- Email ---
        if ($canal === 'todos' || $canal === 'email') {
            $w = 'escola_id = %d'; $p = [$eid];
            if ($estado !== 'all') {
                $raw = sige_comm_raw_por_estado('email', $estado);
                if (empty($raw)) { $w .= ' AND 1=0'; }
                else {
                    $w .= ' AND status IN (' . implode(',', array_fill(0, count($raw), '%s')) . ')';
                    $p = array_merge($p, $raw);
                }
            }
            $partes[] = "SELECT 'email' AS canal, id, recipient_name AS nome, recipient AS sub,
                                LEFT(subject, 180) AS resumo, context AS contexto, status AS estado_raw,
                                COALESCE(scheduled_at, created_at) AS data_ord, last_error AS erro,
                                attempts AS tentativas, 0 AS aluno_id
                           FROM {$te} WHERE {$w}";
            $params = array_merge($params, $p);

            $cntE = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$te} WHERE {$w}", $p));
            $total += $cntE;
        }

        // --- WhatsApp ---
        if ($canal === 'todos' || $canal === 'whatsapp') {
            $w = 'escola_id = %d'; $p = [$eid];
            if ($estado !== 'all') {
                $raw = sige_comm_raw_por_estado('whatsapp', $estado);
                if (empty($raw)) { $w .= ' AND 1=0'; }
                else {
                    $w .= ' AND status IN (' . implode(',', array_fill(0, count($raw), '%s')) . ')';
                    $p = array_merge($p, $raw);
                }
            }
            $partes[] = "SELECT 'whatsapp' AS canal, id, NULL AS nome, telefone AS sub,
                                LEFT(mensagem, 180) AS resumo, tipo AS contexto, status AS estado_raw,
                                criado_em AS data_ord, erro AS erro,
                                tentativas AS tentativas, aluno_id AS aluno_id
                           FROM {$tw} WHERE {$w}";
            $params = array_merge($params, $p);

            $cntW = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tw} WHERE {$w}", $p));
            $total += $cntW;
        }

        if (empty($partes)) return ['rows' => [], 'total' => 0];

        $offset = ($pagina - 1) * $por_pagina;
        $sql = '(' . implode(') UNION ALL (', $partes) . ') ORDER BY data_ord DESC LIMIT %d OFFSET %d';
        $params[] = $por_pagina;
        $params[] = $offset;

        $raw_rows = $wpdb->get_results($wpdb->prepare($sql, $params));
        $rows = [];
        foreach ((array) $raw_rows as $r) {
            $rows[] = [
                'canal'      => (string) $r->canal,
                'id'         => (int) $r->id,
                'nome'       => trim((string) ($r->nome ?? '')),
                'sub'        => (string) ($r->sub ?? ''),
                'resumo'     => (string) ($r->resumo ?? ''),
                'contexto'   => (string) ($r->contexto ?? ''),
                'estado'     => sige_comm_estado_canonico((string) $r->canal, (string) $r->estado_raw),
                'estado_raw' => (string) $r->estado_raw,
                'data'       => (string) ($r->data_ord ?? ''),
                'erro'       => (string) ($r->erro ?? ''),
                'tentativas' => (int) $r->tentativas,
                'aluno_id'   => (int) $r->aluno_id,
            ];
        }
        return ['rows' => $rows, 'total' => $total];
    }
}

/* ============================================================================
 * Aplicar uma accao a UMA mensagem (por canal, escola-scoped, regras de estado)
 *   $accao = cancel | retry | delete
 *   devolve ['ok' => bool, 'msg' => string, 'novo_estado' => string|null]
 * ========================================================================== */
if (!function_exists('sige_comm_email_apply')) {
    function sige_comm_email_apply(string $accao, int $id): array {
        global $wpdb;
        $t = sige_comm_email_table();
        $eid = sige_emc_eid();
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$t} WHERE id=%d AND escola_id=%d", $id, $eid));
        if (!$row) return ['ok' => false, 'msg' => 'E-mail não encontrado.'];
        $st = (string) $row->status;
        $now = current_time('mysql');

        if ($accao === 'cancel') {
            if ($st !== 'pending') return ['ok' => false, 'msg' => 'Só pendentes.'];
            $wpdb->update($t, ['status' => 'cancelled', 'last_error' => 'Cancelado em ' . $now, 'updated_at' => $now],
                ['id' => $id, 'escola_id' => $eid], ['%s', '%s', '%s'], ['%d', '%d']);
            return ['ok' => true, 'msg' => 'Cancelado.', 'novo_estado' => 'cancelado'];
        }
        if ($accao === 'retry') {
            if (!in_array($st, ['failed', 'cancelled'], true)) return ['ok' => false, 'msg' => 'Só falhados/cancelados.'];
            $wpdb->update($t, ['status' => 'pending', 'attempts' => 0, 'last_error' => null, 'locked_at' => null, 'scheduled_at' => $now, 'updated_at' => $now],
                ['id' => $id, 'escola_id' => $eid], ['%s', '%d', '%s', '%s', '%s', '%s'], ['%d', '%d']);
            return ['ok' => true, 'msg' => 'Recolocado na fila.', 'novo_estado' => 'pendente'];
        }
        if ($accao === 'delete') {
            if (!in_array($st, ['sent', 'failed', 'cancelled'], true)) return ['ok' => false, 'msg' => 'Em curso; cancele primeiro.'];
            $wpdb->delete($t, ['id' => $id, 'escola_id' => $eid], ['%d', '%d']);
            return ['ok' => true, 'msg' => 'Eliminado.', 'novo_estado' => null];
        }
        return ['ok' => false, 'msg' => 'Acção inválida.'];
    }
}

if (!function_exists('sige_comm_wpp_apply')) {
    function sige_comm_wpp_apply(string $accao, int $id): array {
        global $wpdb;
        $t = sige_comm_wpp_table();
        $eid = sige_emc_eid();
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM {$t} WHERE id=%d AND escola_id=%d", $id, $eid));
        if (!$row) return ['ok' => false, 'msg' => 'Mensagem não encontrada.'];
        $st = (string) $row->status;
        $now = current_time('mysql');
        $login = wp_get_current_user()->user_login;

        if ($accao === 'cancel') {
            if (!in_array($st, ['pendente', 'forcar_envio', 'agendada'], true)) return ['ok' => false, 'msg' => 'Só pendentes.'];
            $wpdb->update($t, ['status' => 'cancelado', 'erro' => 'Cancelada por ' . $login . ' em ' . $now],
                ['id' => $id, 'escola_id' => $eid], ['%s', '%s'], ['%d', '%d']);
            return ['ok' => true, 'msg' => 'Cancelada.', 'novo_estado' => 'cancelado'];
        }
        if ($accao === 'retry') {
            if (!in_array($st, ['falhou', 'cancelado'], true)) return ['ok' => false, 'msg' => 'Só falhadas/canceladas.'];
            $data = ['status' => 'pendente', 'tentativas' => 0, 'erro' => null];
            $fmt  = ['%s', '%d', '%s'];
            if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at')) { $data['scheduled_at'] = $now; $fmt[] = '%s'; }
            if (function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('next_retry_at')) { $data['next_retry_at'] = null; $fmt[] = '%s'; }
            $wpdb->update($t, $data, ['id' => $id, 'escola_id' => $eid], $fmt, ['%d', '%d']);
            return ['ok' => true, 'msg' => 'Recolocada na fila.', 'novo_estado' => 'pendente'];
        }
        if ($accao === 'delete') {
            if (!in_array($st, ['enviado', 'falhou', 'cancelado'], true)) return ['ok' => false, 'msg' => 'Em curso; cancele primeiro.'];
            $wpdb->delete($t, ['id' => $id, 'escola_id' => $eid], ['%d', '%d']);
            return ['ok' => true, 'msg' => 'Eliminada.', 'novo_estado' => null];
        }
        return ['ok' => false, 'msg' => 'Acção inválida.'];
    }
}

if (!function_exists('sige_comm_apply')) {
    function sige_comm_apply(string $canal, string $accao, int $id): array {
        return $canal === 'whatsapp' ? sige_comm_wpp_apply($accao, $id) : sige_comm_email_apply($accao, $id);
    }
}

/* ============================================================================
 * AJAX: accao unificada (1 ou varios itens) - cancelar/reenviar/eliminar
 *   POST: accao=cancel|retry|delete, itens=JSON [{"c":"email|whatsapp","id":N}, ...]
 * ========================================================================== */
add_action('wp_ajax_sige_comm_action', 'sige_comm_ajax_action');
if (!function_exists('sige_comm_ajax_action')) {
    function sige_comm_ajax_action() {
        sige_emc_check_nonce_or_die();

        $accao = isset($_POST['accao']) ? sanitize_key((string) wp_unslash($_POST['accao'])) : '';
        if (!in_array($accao, ['cancel', 'retry', 'delete'], true)) sige_emc_send_json_error('Acção inválida.');

        $itens_raw = isset($_POST['itens']) ? (string) wp_unslash($_POST['itens']) : '[]';
        $itens = json_decode($itens_raw, true);
        if (!is_array($itens) || empty($itens)) sige_emc_send_json_error('Nenhum item indicado.');
        if (count($itens) > 200) sige_emc_send_json_error('Demasiados itens de uma vez (máx. 200).');

        $ok = 0; $falhou = 0; $detalhes = [];
        foreach ($itens as $it) {
            $canal = isset($it['c']) && $it['c'] === 'whatsapp' ? 'whatsapp' : 'email';
            $id = isset($it['id']) ? absint($it['id']) : 0;
            if ($id <= 0) { $falhou++; continue; }
            $res = sige_comm_apply($canal, $accao, $id);
            if (!empty($res['ok'])) { $ok++; } else { $falhou++; $detalhes[] = $canal . '#' . $id . ': ' . ($res['msg'] ?? 'erro'); }
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('comm_action', ['accao' => $accao, 'ok' => $ok, 'falhou' => $falhou, 'n' => count($itens)]);
        }
        wp_send_json_success(['ok' => $ok, 'falhou' => $falhou, 'detalhes' => $detalhes, 'stats' => sige_comm_stats()]);
    }
}

/* ============================================================================
 * AJAX: pre-visualizar (cross-canal)
 * ========================================================================== */
add_action('wp_ajax_sige_comm_get_full', 'sige_comm_ajax_get_full');
if (!function_exists('sige_comm_ajax_get_full')) {
    function sige_comm_ajax_get_full() {
        sige_emc_check_nonce_or_die();
        global $wpdb;

        $canal = isset($_POST['canal']) && $_POST['canal'] === 'whatsapp' ? 'whatsapp' : 'email';
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if ($id <= 0) sige_emc_send_json_error('ID inválido.');
        $eid = sige_emc_eid();

        if ($canal === 'whatsapp') {
            $t = sige_comm_wpp_table();
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d AND escola_id=%d", $id, $eid));
            if (!$row) sige_emc_send_json_error('Mensagem não encontrada.', 404);
            wp_send_json_success([
                'canal' => 'whatsapp',
                'titulo' => 'Mensagem WhatsApp',
                'destinatario' => (string) $row->telefone,
                'estado' => sige_comm_estado_canonico('whatsapp', (string) $row->status),
                'corpo_texto' => (string) $row->mensagem,
                'erro' => (string) ($row->erro ?? ''),
            ]);
        }
        $t = sige_comm_email_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d AND escola_id=%d", $id, $eid));
        if (!$row) sige_emc_send_json_error('E-mail não encontrado.', 404);
        wp_send_json_success([
            'canal' => 'email',
            'titulo' => (string) $row->subject,
            'destinatario' => trim((string) ($row->recipient_name ?? '')) !== '' ? $row->recipient_name . ' <' . $row->recipient . '>' : (string) $row->recipient,
            'estado' => sige_comm_estado_canonico('email', (string) $row->status),
            'corpo_html' => wp_kses_post((string) $row->body),
            'erro' => (string) ($row->last_error ?? ''),
        ]);
    }
}

/* ============================================================================
 * NIVEL 3 - Historico por encarregado/aluno (agnostico ao canal)
 * ----------------------------------------------------------------------------
 * Correlaciona as comunicacoes de um aluno entre canais:
 *   - WhatsApp: por aluno_id (ligacao directa na fila).
 *   - E-mail: por recipient IN (emails dos encarregados na ficha do aluno).
 * Devolve uma linha do tempo unica, do mais recente para o mais antigo.
 * ========================================================================== */

if (!function_exists('sige_comm_alunos_table')) {
    function sige_comm_alunos_table(): string {
        return $GLOBALS['wpdb']->prefix . 'sige_alunos';
    }
}

// Ficha de contacto do aluno (emails, telefones, canal preferido, consentimentos).
if (!function_exists('sige_comm_aluno_ficha')) {
    function sige_comm_aluno_ficha(int $aluno_id): ?array {
        global $wpdb;
        $t = sige_comm_alunos_table();
        $a = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nome_completo, numero_processo,
                    email_encarregado, email_pai, email_mae, encarregado_principal_email,
                    contacto_encarregado, whatsapp_notificacoes, telemovel_pai, telemovel_mae,
                    encarregado_principal_nome, encarregado_principal_parentesco, encarregado_principal_telemovel,
                    canal_preferencial_comunicacao, consent_whatsapp, consent_email
               FROM {$t} WHERE id=%d AND escola_id=%d",
            $aluno_id, sige_emc_eid()
        ));
        if (!$a) return null;

        $emails = [];
        foreach (['email_encarregado', 'email_pai', 'email_mae', 'encarregado_principal_email'] as $c) {
            $v = trim((string) ($a->$c ?? ''));
            if ($v !== '' && is_email($v)) $emails[strtolower($v)] = $v;
        }
        $tels = [];
        foreach (['contacto_encarregado', 'whatsapp_notificacoes', 'encarregado_principal_telemovel', 'telemovel_pai', 'telemovel_mae'] as $c) {
            $v = trim((string) ($a->$c ?? ''));
            if ($v !== '') $tels[$v] = $v;
        }
        return [
            'id'            => (int) $a->id,
            'nome'          => (string) $a->nome_completo,
            'processo'      => (string) ($a->numero_processo ?? ''),
            'encarregado'   => trim((string) ($a->encarregado_principal_nome ?? '')),
            'parentesco'    => trim((string) ($a->encarregado_principal_parentesco ?? '')),
            'emails'        => array_values($emails),
            'telefones'     => array_values($tels),
            'canal_pref'    => (string) ($a->canal_preferencial_comunicacao ?? ''),
            'consent_wpp'   => (int) ($a->consent_whatsapp ?? 1),
            'consent_email' => (int) ($a->consent_email ?? 1),
        ];
    }
}

// Procurar alunos por nome ou numero de processo (para o selector).
if (!function_exists('sige_comm_buscar_alunos')) {
    function sige_comm_buscar_alunos(string $q): array {
        global $wpdb;
        $q = trim($q);
        if (strlen($q) < 2) return [];
        $t = sige_comm_alunos_table();
        $like = '%' . $wpdb->esc_like($q) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome_completo, numero_processo, encarregado_principal_nome
               FROM {$t}
              WHERE escola_id=%d AND (nome_completo LIKE %s OR numero_processo LIKE %s)
              ORDER BY nome_completo ASC LIMIT 25",
            sige_emc_eid(), $like, $like
        ));
        $out = [];
        foreach ((array) $rows as $r) {
            $out[] = [
                'id'          => (int) $r->id,
                'nome'        => (string) $r->nome_completo,
                'processo'    => (string) ($r->numero_processo ?? ''),
                'encarregado' => trim((string) ($r->encarregado_principal_nome ?? '')),
            ];
        }
        return $out;
    }
}

// Linha do tempo unificada de um aluno (e-mail por emails + WhatsApp por aluno_id).
if (!function_exists('sige_comm_historico_aluno')) {
    function sige_comm_historico_aluno(int $aluno_id, int $limite = 100): array {
        global $wpdb;
        $eid = sige_emc_eid();
        $ficha = sige_comm_aluno_ficha($aluno_id);
        if (!$ficha) return ['ficha' => null, 'rows' => []];

        $te = sige_comm_email_table();
        $tw = sige_comm_wpp_table();
        $rows = [];

        // E-mail: por emails do encarregado.
        if (!empty($ficha['emails'])) {
            $ph = implode(',', array_fill(0, count($ficha['emails']), '%s'));
            $params = array_merge([$eid], $ficha['emails'], [$limite]);
            $re = $wpdb->get_results($wpdb->prepare(
                "SELECT id, recipient_name AS nome, recipient AS sub, LEFT(subject,180) AS resumo,
                        context AS contexto, status AS estado_raw, COALESCE(scheduled_at, created_at) AS data_ord,
                        last_error AS erro
                   FROM {$te}
                  WHERE escola_id=%d AND recipient IN ({$ph})
                  ORDER BY id DESC LIMIT %d",
                $params
            ));
            foreach ((array) $re as $r) {
                $rows[] = [
                    'canal' => 'email', 'id' => (int) $r->id,
                    'nome' => trim((string) ($r->nome ?? '')), 'sub' => (string) $r->sub,
                    'resumo' => (string) $r->resumo, 'contexto' => (string) $r->contexto,
                    'estado' => sige_comm_estado_canonico('email', (string) $r->estado_raw),
                    'estado_raw' => (string) $r->estado_raw, 'data' => (string) $r->data_ord,
                    'erro' => (string) ($r->erro ?? ''),
                ];
            }
        }

        // WhatsApp: por aluno_id.
        $rw = $wpdb->get_results($wpdb->prepare(
            "SELECT id, telefone AS sub, LEFT(mensagem,180) AS resumo, tipo AS contexto,
                    status AS estado_raw, criado_em AS data_ord, erro AS erro
               FROM {$tw}
              WHERE escola_id=%d AND aluno_id=%d
              ORDER BY id DESC LIMIT %d",
            $eid, $aluno_id, $limite
        ));
        foreach ((array) $rw as $r) {
            $rows[] = [
                'canal' => 'whatsapp', 'id' => (int) $r->id,
                'nome' => '', 'sub' => (string) $r->sub,
                'resumo' => (string) $r->resumo, 'contexto' => (string) $r->contexto,
                'estado' => sige_comm_estado_canonico('whatsapp', (string) $r->estado_raw),
                'estado_raw' => (string) $r->estado_raw, 'data' => (string) $r->data_ord,
                'erro' => (string) ($r->erro ?? ''),
            ];
        }

        // Ordenar por data desc (merge dos dois canais).
        usort($rows, static function ($a, $b) {
            return strcmp((string) $b['data'], (string) $a['data']);
        });
        if (count($rows) > $limite) $rows = array_slice($rows, 0, $limite);

        return ['ficha' => $ficha, 'rows' => $rows];
    }
}

/* AJAX: procurar alunos */
add_action('wp_ajax_sige_comm_buscar', 'sige_comm_ajax_buscar');
if (!function_exists('sige_comm_ajax_buscar')) {
    function sige_comm_ajax_buscar() {
        sige_emc_check_nonce_or_die();
        $q = isset($_POST['q']) ? sanitize_text_field((string) wp_unslash($_POST['q'])) : '';
        wp_send_json_success(['alunos' => sige_comm_buscar_alunos($q)]);
    }
}

/* AJAX: historico de um aluno */
add_action('wp_ajax_sige_comm_historico', 'sige_comm_ajax_historico');
if (!function_exists('sige_comm_ajax_historico')) {
    function sige_comm_ajax_historico() {
        sige_emc_check_nonce_or_die();
        $aluno_id = isset($_POST['aluno_id']) ? absint($_POST['aluno_id']) : 0;
        if ($aluno_id <= 0) sige_emc_send_json_error('Aluno inválido.');
        $h = sige_comm_historico_aluno($aluno_id);
        if (!$h['ficha']) sige_emc_send_json_error('Aluno não encontrado nesta escola.', 404);
        wp_send_json_success($h);
    }
}
