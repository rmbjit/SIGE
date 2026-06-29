<?php
/**
 * SIGE SoftGenial - Circulares WhatsApp
 *
 * Comunicados da escola aos encarregados (reunião de pais, interrupção
 * lectiva, eventos), por turma ou para a escola inteira, usando a MESMA
 * fila e os MESMOS guardrails do motor WhatsApp existente. Este módulo
 * não altera nada no financeiro nem nos templates de cobrança.
 *
 * Regras:
 *  - Uma circular por FAMÍLIA: deduplicação pelo telefone normalizado;
 *  - Apenas alunos activos com matrícula activa no ano corrente;
 *  - Respeita a política de destinatários da escola
 *    (sige_wpp_destinatario_permitido);
 *  - Envio sempre em dois tempos: pré-visualizar (contagem real) e
 *    confirmar com checkbox + nonce;
 *  - Tudo auditado.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_circular_pode_enviar')) {
    function sige_circular_pode_enviar(): bool {
        if (function_exists('sige_can') && sige_can('comunicacao.circulares_enviar')) return true;
        if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) return true;
        return current_user_can('sige_director') || current_user_can('sige_secretaria_geral');
    }
}

if (!function_exists('sige_circular_resolver_destinatarios')) {
    /**
     * Devolve ['total_alunos'=>int,'familias'=>[['telefone','nome','aluno_id','aluno_nome'],...]]
     * já deduplicado por telefone normalizado e filtrado pela política da escola.
     */
    function sige_circular_resolver_destinatarios(int $escola_id, string $escopo, int $turma_id = 0): array {
        global $wpdb;
        $tA = $wpdb->prefix . 'sige_alunos';
        $tM = $wpdb->prefix . 'sige_matriculas';
        $ano = (int) date('Y');

        $cond_aluno = function_exists('sige_aluno_activo_sql')
            ? sige_aluno_activo_sql('a')
            : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";
        $cond_mat = function_exists('sige_matricula_activa_sql')
            ? sige_matricula_activa_sql('m')
            : "(m.status_matricula IS NULL OR LOWER(m.status_matricula) IN ('activa','ativa','activo','ativo'))";

        $sql = "SELECT a.* FROM {$tA} a
                INNER JOIN {$tM} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id AND m.ano_letivo = %d
                WHERE a.escola_id = %d AND {$cond_aluno} AND {$cond_mat}";
        $args = [$ano, $escola_id];
        if ($escopo === 'turma' && $turma_id > 0) {
            $sql .= ' AND m.turma_id = %d';
            $args[] = $turma_id;
        }
        $sql .= ' ORDER BY a.nome_completo ASC';
        $alunos = $wpdb->get_results($wpdb->prepare($sql, $args));

        $familias = [];
        $vistos = [];
        $total_alunos = 0;
        foreach ((array)$alunos as $aluno) {
            $total_alunos++;
            $contactos = function_exists('sige_wpp_contactos_whatsapp_aluno_row')
                ? sige_wpp_contactos_whatsapp_aluno_row($aluno)
                : [];
            // Fallback mínimo se a política não estiver carregada
            if (empty($contactos) && !empty($aluno->contacto_encarregado)) {
                $contactos = [['telefone' => (string)$aluno->contacto_encarregado, 'nome' => 'Encarregado de Educação']];
            }
            foreach ($contactos as $c) {
                $raw = (string)($c['telefone'] ?? $c['raw'] ?? '');
                $tel = function_exists('sige_wpp_policy_normalize_phone')
                    ? sige_wpp_policy_normalize_phone($raw)
                    : preg_replace('/\D+/', '', $raw);
                if ($tel === '' ) continue;
                if (isset($vistos[$tel])) continue; // 1 circular por família
                if (function_exists('sige_wpp_destinatario_permitido')
                    && !sige_wpp_destinatario_permitido((int)$aluno->id, $tel)) continue;
                $vistos[$tel] = true;
                $familias[] = [
                    'telefone'   => $tel,
                    'nome'       => (string)($c['nome'] ?? 'Encarregado de Educação'),
                    'aluno_id'   => (int)$aluno->id,
                    'aluno_nome' => (string)($aluno->nome_completo ?? $aluno->nome ?? ''),
                ];
            }
        }
        return ['total_alunos' => $total_alunos, 'familias' => $familias];
    }
}

if (!function_exists('sige_circular_compor_mensagem')) {
    function sige_circular_compor_mensagem(string $texto, string $escola_nome): string {
        $texto = trim($texto);
        $cab = $escola_nome !== '' ? ('*' . $escola_nome . "*\n\n") : '';
        return $cab . $texto;
    }
}

// ── AJAX: pré-visualização (contagem real + amostra) ───────────────────────
add_action('wp_ajax_sige_circular_preview', function () {
    if (!sige_circular_pode_enviar()) wp_send_json_error('Sem permissão para enviar circulares.');
    check_ajax_referer('sige_circular', '_wpnonce');

    $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    $escopo = sanitize_key($_POST['escopo'] ?? 'escola');
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    if ($escopo === 'turma' && $turma_id <= 0) wp_send_json_error('Escolha a turma.');

    $r = sige_circular_resolver_destinatarios($escola_id, $escopo, $turma_id);
    $amostra = array_slice(array_map(static function ($f) {
        return $f['nome'] . ' (' . $f['aluno_nome'] . ')';
    }, $r['familias']), 0, 8);

    wp_send_json_success([
        'familias' => count($r['familias']),
        'alunos'   => (int)$r['total_alunos'],
        'amostra'  => $amostra,
    ]);
});

// ── POST: envio confirmado ─────────────────────────────────────────────────
add_action('admin_post_sige_circular_enviar', function () {
    if (!sige_circular_pode_enviar()) wp_die('Sem permissão para enviar circulares.');
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_circular')) {
        wp_die('A sessão expirou por segurança. Volte atrás, recarregue a página e tente novamente.');
    }
    if (empty($_POST['confirmo_envio'])) {
        wp_safe_redirect(add_query_arg(['page'=>'sige-app','view'=>'whatsapp_circulares','erro'=>'confirmacao'], admin_url('admin.php'))); exit;
    }

    $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    $escopo = sanitize_key($_POST['escopo'] ?? 'escola');
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $texto = sanitize_textarea_field(wp_unslash((string)($_POST['mensagem'] ?? '')));

    if (function_exists('mb_strlen') ? mb_strlen($texto) < 20 : strlen($texto) < 20) {
        wp_safe_redirect(add_query_arg(['page'=>'sige-app','view'=>'whatsapp_circulares','erro'=>'curta'], admin_url('admin.php'))); exit;
    }
    if (function_exists('mb_strlen') ? mb_strlen($texto) > 800 : strlen($texto) > 800) {
        wp_safe_redirect(add_query_arg(['page'=>'sige-app','view'=>'whatsapp_circulares','erro'=>'longa'], admin_url('admin.php'))); exit;
    }

    $escola_nome = '';
    if (function_exists('sige_get_escola_perfil')) {
        $perfil = sige_get_escola_perfil();
        $escola_nome = $perfil && !empty($perfil->nome) ? (string)$perfil->nome : '';
    }
    $mensagem = sige_circular_compor_mensagem($texto, $escola_nome);

    $r = sige_circular_resolver_destinatarios($escola_id, $escopo, $turma_id);
    if (empty($r['familias'])) {
        wp_safe_redirect(add_query_arg(['page'=>'sige-app','view'=>'whatsapp_circulares','erro'=>'sem_destinatarios'], admin_url('admin.php'))); exit;
    }

    global $wpdb;
    $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
    $agora = current_time('mysql');
    $enfileiradas = 0;
    foreach ($r['familias'] as $f) {
        $ok = $wpdb->insert($tQ, [
            'escola_id' => $escola_id,
            'telefone'  => $f['telefone'],
            'mensagem'  => $mensagem,
            'tipo'      => 'circular',
            'aluno_id'  => $f['aluno_id'],
            'status'    => 'pendente',
            'tentativas'=> 0,
            'criado_em' => $agora,
        ], ['%d','%s','%s','%s','%d','%s','%d','%s']);
        if ($ok) $enfileiradas++;
    }

    if (function_exists('sige_security_log')) {
        sige_security_log('circular_enviada', sprintf(
            'escopo=%s turma=%d familias=%d alunos=%d user=%d',
            $escopo, $turma_id, $enfileiradas, (int)$r['total_alunos'], get_current_user_id()
        ));
    }

    wp_safe_redirect(add_query_arg([
        'page'=>'sige-app','view'=>'whatsapp_circulares','ok'=>$enfileiradas,
    ], admin_url('admin.php')));
    exit;
});
