<?php
/**
 * SIGE SoftGenial - Provisionamento de Contas de Alunos
 * Ficheiro: includes/aluno-accounts.php
 * Carregado por sige-softgenial.php
 *
 * Lógica:
 *  - Username  = numero_processo (ex: ALG2024001)
 *  - Password  = 123456 (padrão, aluno pode alterar depois)
 *  - Role      = sige_aluno
 *  - Email     = processo@{escola}.internal (fictício, evita colisões)
 *  - User meta = sige_aluno_id → liga WP user ao registo do aluno
 */
if (!defined('ABSPATH')) exit;
// ── Garantir que a role existe mesmo antes de activação ──────────────────────
add_action('init', function () {
    if (!get_role('sige_aluno')) {
        add_role('sige_aluno', 'Aluno', [
            'read' => true,
            'sige_aluno' => true,
            'portal.ver' => true,
            'portal.ver_pagamentos' => true,
            'portal.ver_documentos' => true,
            'portal.alterar_senha' => true,
        ]);
    }

    // v12.10.129 - garantir capabilities de portal mesmo em roles antigas.
    foreach (['sige_aluno', 'sige_encarregado'] as $role_slug) {
        $role = get_role($role_slug);
        if ($role) {
            foreach (['read','portal.ver','portal.ver_pagamentos','portal.ver_documentos','portal.alterar_senha'] as $cap) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }
    }
});

if (!function_exists('sige_aluno_accounts_sync_portal_meta_v129')) {
    function sige_aluno_accounts_sync_portal_meta_v129(int $user_id, int $aluno_id, bool $force_password_change = false, string $reason = ''): void {
        if ($user_id <= 0 || $aluno_id <= 0) return;

        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

        // Associação canónica + aliases de compatibilidade usados em versões antigas.
        update_user_meta($user_id, 'sige_aluno_id', $aluno_id);
        update_user_meta($user_id, 'aluno_id', $aluno_id);
        update_user_meta($user_id, 'sige_portal_aluno_id', $aluno_id);

        if ($escola_id > 0) {
            update_user_meta($user_id, 'sige_escola_id', $escola_id);
            update_user_meta($user_id, 'escola_id', $escola_id);
            update_user_meta($user_id, 'sige_portal_escola_id', $escola_id);
        }

        $u = new WP_User($user_id);
        if (!in_array('sige_aluno', (array)$u->roles, true) && !in_array('sige_encarregado', (array)$u->roles, true)) {
            $u->set_role('sige_aluno');
        }

        foreach (['read','portal.ver','portal.ver_pagamentos','portal.ver_documentos','portal.alterar_senha'] as $cap) {
            $u->add_cap($cap);
        }

        update_user_meta($user_id, 'sige_portal_association_checked_at', current_time('mysql'));
        update_user_meta($user_id, 'sige_portal_association_version', '12.10.129');

        if ($force_password_change) {
            update_user_meta($user_id, 'sige_portal_force_password_change', 1);
            update_user_meta($user_id, 'sige_password_must_change', 1);
            update_user_meta($user_id, 'sige_primeiro_acesso_pendente', 1);
            update_user_meta($user_id, 'sige_portal_force_password_reason', $reason ?: 'senha_provisoria');
            update_user_meta($user_id, 'sige_portal_force_password_at', current_time('mysql'));
        }
    }
}
// ─────────────────────────────────────────────────────────────────────────────
// FUNÇÃO: Enviar credenciais de acesso ao encarregado (WhatsApp + Email)
// ─────────────────────────────────────────────────────────────────────────────
function sige_enviar_credenciais_encarregado(int $aluno_id, string $username, string $senha = ''): array {
    global $wpdb;
    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_alunos WHERE id = %d AND escola_id = %d LIMIT 1", $aluno_id, sige_get_escola_id()
    ));
    if (!$aluno) return ['wpp' => false, 'email' => false, 'motivo' => 'Aluno não encontrado.'];

    $uid = username_exists($username);
    $reset_link = wp_login_url();
    if ($uid) {
        if (function_exists('sige_aluno_accounts_sync_portal_meta_v129')) {
            sige_aluno_accounts_sync_portal_meta_v129((int)$uid, (int)$aluno_id, true, 'link_seguro_enviado');
        }
        $wp_user = get_userdata((int)$uid);
        if ($wp_user instanceof WP_User && function_exists('sige_sec_create_password_reset_link')) {
            $link = sige_sec_create_password_reset_link($wp_user);
            if (!is_wp_error($link)) $reset_link = (string)$link;
        }
    }

    // Cascata de telefone: whatsapp > contacto > pai > pai2 > mae > mae2
    $tel = '';
    foreach (['whatsapp_notificacoes','contacto_encarregado','telemovel_pai','telemovel_pai_2','telemovel_mae','telemovel_mae_2'] as $_f) {
        if (!empty($aluno->$_f)) { $tel = $aluno->$_f; break; }
    }
    // Cascata de email: encarregado > pai > mae
    $email_enc = '';
    foreach (['email_encarregado','email_pai','email_mae'] as $_f) {
        if (!empty($aluno->$_f) && strpos($aluno->$_f, '@') !== false) { $email_enc = $aluno->$_f; break; }
    }
    $nome_enc = !empty($aluno->nome_pai) ? $aluno->nome_pai
              : (!empty($aluno->nome_mae) ? $aluno->nome_mae : 'Encarregado(a)');
    $escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
    $escola_nome = $escola->nome_escola ?? get_bloginfo('name');
    $resultado = ['wpp' => false, 'email' => false, 'tel' => $tel, 'email_dest' => $email_enc];

    // WhatsApp - v12.10.140: não envia senha em texto claro; envia link único de definição.
    if (!empty($tel)) $resultado['wpp_encontrado'] = true;
    if (!empty($tel) && function_exists('sige_fin_queue_whatsapp')) {
        $msg = "📚 *{$escola_nome}*\n\n"
             . "Exmo(a) {$nome_enc},\n\n"
             . "Foi preparado o acesso ao Portal do Aluno para o(a) seu(a) educando(a):\n\n"
             . "👤 *Aluno(a):* {$aluno->nome_completo}\n"
             . "🔑 *Utilizador:* {$username}\n"
             . "🔐 *Definir senha:* {$reset_link}\n\n"
             . "Por segurança, a escola não envia senhas em texto claro. Use o link para definir uma senha pessoal.\n\n"
             . "Com os melhores cumprimentos,\n"
             . "{$escola_nome}";
        sige_fin_queue_whatsapp($aluno_id, $tel, 'conta_aluno', $msg);
        $resultado['wpp'] = true;
    }

    // Email - v12.10.140: link único, sem senha em texto claro.
    if (!empty($email_enc)) {
        $resultado['email_encontrado'] = true;
        $assunto = "Acesso ao Portal do Aluno - " . $aluno->nome_completo;
        $corpo = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:var(--sg-theme-primary-800,#3b2f8d);color:white;padding:20px;border-radius:8px 8px 0 0;text-align:center;'>
                <h2 style='margin:0;'>Acesso ao Portal do Aluno</h2>
                <p style='margin:5px 0 0;opacity:0.9;'>" . esc_html($escola_nome) . "</p>
            </div>
            <div style='background:#f8fafc;padding:25px;border:1px solid #e2e8f0;'>
                <p>Exmo(a) " . esc_html($nome_enc) . ",</p>
                <p>Foi preparado o acesso ao Portal do Aluno para o(a) seu(a) educando(a):</p>
                <div style='overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;'><table style='width:100%;border-collapse:collapse;margin:15px 0;background:white;border-radius:6px;border:1px solid #e2e8f0;'>
                    <tr><td style='padding:10px 15px;border-bottom:1px solid #f1f5f9;color:#64748b;width:35%;'>Aluno(a)</td>
                        <td style='padding:10px 15px;border-bottom:1px solid #f1f5f9;font-weight:bold;'>" . esc_html($aluno->nome_completo) . "</td></tr>
                    <tr><td style='padding:10px 15px;border-bottom:1px solid #f1f5f9;color:#64748b;'>Utilizador</td>
                        <td style='padding:10px 15px;border-bottom:1px solid #f1f5f9;font-weight:bold;font-family:monospace;font-size:16px;'>" . esc_html($username) . "</td></tr>
                    <tr><td style='padding:10px 15px;color:#64748b;'>Definir senha</td>
                        <td style='padding:10px 15px;'><a href='" . esc_url($reset_link) . "' style='color:var(--sg-theme-primary,#5a3fd6);font-weight:bold;'>Abrir link seguro</a></td></tr>
                </table></div>
                <div style='background:#e0f2fe;padding:12px 15px;border-radius:6px;margin-top:15px;border-left:4px solid #0284c7;'>
                    <strong>Segurança:</strong> por protecção da conta, a senha não é enviada por e-mail nem por WhatsApp.
                </div>
            </div>
            <div style='background:#f1f5f9;padding:15px;border-radius:0 0 8px 8px;text-align:center;font-size:12px;color:#64748b;border:1px solid #e2e8f0;border-top:0;'>
                <p style='margin:0;'>Comunicação enviada pela secretaria da escola</p>
            </div>
        </div>";
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($escola && !empty($escola->email_institucional)) {
            $nome_escola_from = $escola->nome_escola ?? get_bloginfo('name');
            $headers[] = 'From: ' . $nome_escola_from . ' <' . $escola->email_institucional . '>';
        }
        if (function_exists('sige_notify_humanize_outbound_email_html')) {
            $assunto = sige_notify_humanize_outbound_email_html($assunto);
            $corpo   = sige_notify_humanize_outbound_email_html($corpo);
        }
        $enviado = wp_mail($email_enc, $assunto, $corpo, $headers);
        $resultado['email'] = (bool)$enviado;
        if (!$enviado) $resultado['email_erro'] = 'wp_mail retornou false';
    }
    return $resultado;
}
// ─────────────────────────────────────────────────────────────────────────────
// FUNÇÃO CENTRAL: provisionar conta para um aluno
// Retorna: ['ok' => bool, 'user_id' => int|null, 'msg' => string, 'criado' => bool]
// ─────────────────────────────────────────────────────────────────────────────
function sige_provisionar_conta_aluno(int $aluno_id): array {
    global $wpdb;
    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_alunos WHERE id = %d AND escola_id = %d LIMIT 1",
        $aluno_id, sige_get_escola_id()
    ));
    if (!$aluno) {
        return ['ok' => false, 'user_id' => null, 'msg' => 'Aluno não encontrado.', 'criado' => false];
    }
    $username = sanitize_user(trim($aluno->numero_processo), true);
    if (empty($username)) {
        return ['ok' => false, 'user_id' => null, 'msg' => 'Nº de processo em falta.', 'criado' => false];
    }
    // ── Já tem conta ligada? ──────────────────────────────────────────────────
    // Verificar via user meta (ligação directa)
    $users_ligados = get_users([
        'meta_key'   => 'sige_aluno_id',
        'meta_value' => $aluno_id,
        'number'     => 1,
        'fields'     => 'ID',
    ]);
    if (!empty($users_ligados)) {
        $uid = (int)$users_ligados[0];
        // Garantir associação, role e permissões de portal.
        if (function_exists('sige_aluno_accounts_sync_portal_meta_v129')) {
            sige_aluno_accounts_sync_portal_meta_v129((int)$uid, (int)$aluno_id, false, 'conta_existente');
        } else {
            $u = new WP_User($uid);
            if (!in_array('sige_aluno', (array)$u->roles, true)) {
                $u->set_role('sige_aluno');
            }
        }
        return ['ok' => true, 'user_id' => $uid, 'msg' => 'Conta já existe e foi validada para o portal.', 'criado' => false];
    }
    // ── Username já existe mas sem ligação? ───────────────────────────────────
    $uid_existente = username_exists($username);
    if ($uid_existente) {
        // Ligar o utilizador existente a este aluno com aliases e permissões de portal.
        if (function_exists('sige_aluno_accounts_sync_portal_meta_v129')) {
            sige_aluno_accounts_sync_portal_meta_v129((int)$uid_existente, (int)$aluno_id, true, 'username_existente_ligado');
        } else {
            update_user_meta($uid_existente, 'sige_aluno_id', $aluno_id);
            $u = new WP_User($uid_existente);
            if (!in_array('sige_aluno', (array)$u->roles, true)) {
                $u->set_role('sige_aluno');
            }
        }
        return ['ok' => true, 'user_id' => $uid_existente, 'msg' => 'Username já existia - ligado e validado para o portal.', 'criado' => false];
    }
    // ── Criar nova conta ──────────────────────────────────────────────────────
    $_slug = sanitize_title(function_exists('sige_get_escola_perfil') ? (sige_get_escola_perfil()->nome_escola ?? 'escola') : 'escola');
    $email = strtolower($username) . '@aluno.' . $_slug . '.internal';
    // Garantir email único (adiciona ID se necessário)
    if (email_exists($email)) {
        $email = strtolower($username) . '.' . $aluno_id . '@aluno.' . $_slug . '.internal';
    }
    // [FIX PASS-01] Guardar a senha numa variável para poder devolvê-la e passá-la à notificação
    $senha_gerada = wp_generate_password(10, false);
    $user_id = wp_create_user($username, $senha_gerada, $email);
    if (is_wp_error($user_id)) {
        return ['ok' => false, 'user_id' => null, 'msg' => $user_id->get_error_message(), 'criado' => false];
    }
    // Definir role, display name e meta
    $u = new WP_User($user_id);
    $u->set_role('sige_aluno');
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $aluno->nome_completo,
        'first_name'   => explode(' ', trim($aluno->nome_completo))[0],
    ]);
    if (function_exists('sige_aluno_accounts_sync_portal_meta_v129')) {
        sige_aluno_accounts_sync_portal_meta_v129((int)$user_id, (int)$aluno_id, true, 'primeiro_acesso');
    } else {
        update_user_meta($user_id, 'sige_aluno_id', $aluno_id);
    }
        return ['ok' => true, 'user_id' => $user_id, 'msg' => 'Conta criada com sucesso e link seguro preparado para primeiro acesso.', 'criado' => true];
}
// ─────────────────────────────────────────────────────────────────────────────
// HOOK: criar conta automaticamente quando um aluno é guardado
// ─────────────────────────────────────────────────────────────────────────────
add_action('sige_aluno_guardado', function (int $aluno_id) {
    sige_provisionar_conta_aluno($aluno_id);
}, 10, 1);
// Também hookar directamente no AJAX de salvar aluno (fallback)
add_action('wp_ajax_sige_salvar_aluno', function () {
    // Corre DEPOIS do handler principal (priority 20 vs 10 do handler original)
    // Só actua se o aluno foi criado/actualizado com sucesso
    // O aluno_id vem do POST
    $id = isset($_POST['id_aluno']) ? (int)$_POST['id_aluno'] : 0;
    if ($id > 0) {
        sige_provisionar_conta_aluno($id);
    }
}, 20);
// ─────────────────────────────────────────────────────────────────────────────
// AJAX: repor senha de um aluno (admin)
// ─────────────────────────────────────────────────────────────────────────────
add_action('wp_ajax_sige_repor_senha_aluno', function () {
    check_ajax_referer('sige_aluno_accounts_nonce', 'nonce');
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_secretario')) {
        wp_send_json_error('Sem permissão.');
    }
    global $wpdb;
    $aluno_id = isset($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;
    if (!$aluno_id) wp_send_json_error('ID inválido.');
    $users = get_users([
        'meta_key' => 'sige_aluno_id', 'meta_value' => $aluno_id, 'number' => 1, 'fields' => 'ID',
    ]);
    if (empty($users)) wp_send_json_error('Conta não encontrada. Provisiona primeiro.');
    $uid = (int)$users[0];
    if (function_exists('sige_aluno_accounts_sync_portal_meta_v129')) {
        sige_aluno_accounts_sync_portal_meta_v129((int)$uid, (int)$aluno_id, true, 'link_seguro_reposicao');
    }
    $wp_user = get_userdata($uid);
    $uname = $wp_user ? $wp_user->user_login : '';
    sige_enviar_credenciais_encarregado($aluno_id, $uname, '');
    wp_send_json_success(['msg' => 'Link seguro de redefinição enviado/preparado. A senha não é mostrada no painel.']);
});
// ─────────────────────────────────────────────────────────────────────────────
// AJAX: provisionar UM aluno individual (com notificação)
// ─────────────────────────────────────────────────────────────────────────────
add_action('wp_ajax_sige_provisionar_individual', function () {
    check_ajax_referer('sige_aluno_accounts_nonce', 'nonce');
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_secretario') && !current_user_can('sige_director') && !current_user_can('sige_secretario') && !current_user_can('sige_assistente')) {
        wp_send_json_error('Sem permissão.');
    }
    $aluno_id = isset($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;
    if (!$aluno_id) wp_send_json_error('ID inválido.');
    
    $res = sige_provisionar_conta_aluno($aluno_id);
    if ($res['ok']) {
        // Buscar username da conta
        $wp_user = get_userdata($res['user_id']);
        $uname = $wp_user ? $wp_user->user_login : '';
        // [FIX PASS-02] Passar a senha gerada na criação - evita geração de senha diferente na notificação
        // Só passa senha quando a conta foi criada agora (criado:true); se já existia, a senha é desconhecida
        $notif = sige_enviar_credenciais_encarregado($aluno_id, $uname, $res['senha'] ?? '');
        wp_send_json_success([
            'msg'    => $res['msg'],
            'criado' => $res['criado'],
            'user_id'=> $res['user_id'],
            'notif_wpp'   => $notif['wpp'],
            'notif_email' => $notif['email'],
            'notif_tel'   => $notif['tel'] ?? '',
            'notif_email_dest' => $notif['email_dest'] ?? '',
        ]);
    } else {
        wp_send_json_error($res['msg']);
    }
});
// ─────────────────────────────────────────────────────────────────────────────
// AJAX: reenviar credenciais ao encarregado (sem alterar conta)
// ─────────────────────────────────────────────────────────────────────────────
add_action('wp_ajax_sige_enviar_credenciais', function () {
    check_ajax_referer('sige_aluno_accounts_nonce', 'nonce');
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_secretario') && !current_user_can('sige_director') && !current_user_can('sige_secretario') && !current_user_can('sige_assistente')) {
        wp_send_json_error('Sem permissão.');
    }
    $aluno_id = isset($_POST['aluno_id']) ? (int)$_POST['aluno_id'] : 0;
    if (!$aluno_id) wp_send_json_error('ID inválido.');
    // Buscar username
    $users = get_users(['meta_key' => 'sige_aluno_id', 'meta_value' => $aluno_id, 'number' => 1, 'fields' => 'ID']);
    if (empty($users)) wp_send_json_error('Conta não encontrada.');
    $wp_user = get_userdata((int)$users[0]);
    $uname = $wp_user ? $wp_user->user_login : '';
    $notif = sige_enviar_credenciais_encarregado($aluno_id, $uname, '');
    $notif['msg'] = 'Link seguro enviado/preparado. A senha não é mostrada nem enviada em texto claro.';
    wp_send_json_success($notif);
});
// ─────────────────────────────────────────────────────────────────────────────
// AJAX: provisionar todos os alunos em lote
// ─────────────────────────────────────────────────────────────────────────────
add_action('wp_ajax_sige_provisionar_lote', function () {
    check_ajax_referer('sige_aluno_accounts_nonce', 'nonce');
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_secretario')) {
        wp_send_json_error('Sem permissão.');
    }
    global $wpdb;
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    $lote   = 20; // processar 20 por vez para não fazer timeout
    $alunos = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nome_completo, numero_processo FROM {$wpdb->prefix}sige_alunos
         WHERE escola_id = %d AND status = 'activo' AND numero_processo != ''
         ORDER BY id LIMIT %d OFFSET %d",
        sige_get_escola_id(), $lote, $offset
    ));
    if (empty($alunos)) {
        wp_send_json_success(['done' => true, 'criados' => 0, 'msg' => 'Concluído.']);
        return;
    }
    $criados = 0;
    $erros   = [];
    foreach ($alunos as $a) {
        $res = sige_provisionar_conta_aluno((int)$a->id);
        if ($res['ok'] && $res['criado']) $criados++;
        if (!$res['ok']) $erros[] = $a->numero_processo . ': ' . $res['msg'];
    }
    wp_send_json_success([
        'done'      => false,
        'criados'   => $criados,
        'processados' => count($alunos),
        'proximo'   => $offset + $lote,
        'erros'     => $erros,
    ]);
});
// ─────────────────────────────────────────────────────────────────────────────
// VIEW ADMIN: Gestão de contas de alunos
// Rota: ?page=sige-app&view=aluno_contas
// ─────────────────────────────────────────────────────────────────────────────
function sige_render_aluno_contas_view(): void {
    global $wpdb;
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_secretario')) {
        echo '<div style="padding:30px;color:var(--sige-error,#c62828);">Acesso negado.</div>';
        return;
    }
    $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    // [FIX ACC-02] Contagens corrigidas + paginação
    $total_alunos = (int)$wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sige_alunos WHERE escola_id = %d AND status='activo'", $escola_id)
    );
    // Contar com conta: apenas alunos activos que têm meta sige_aluno_id
    $com_conta = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT a.id)
         FROM {$wpdb->prefix}sige_alunos a
         INNER JOIN {$wpdb->usermeta} um ON um.meta_key='sige_aluno_id' AND um.meta_value = a.id
         WHERE a.escola_id = %d AND a.status='activo'",
        $escola_id
    ));
    $sem_conta = max(0, $total_alunos - $com_conta);
    $nonce     = wp_create_nonce('sige_aluno_accounts_nonce');
    // Paginação
    $por_pagina = 50;
    $pag_atual  = max(1, isset($_GET['ac_pag']) ? (int)$_GET['ac_pag'] : 1);
    $offset     = ($pag_atual - 1) * $por_pagina;
    $total_pags = max(1, ceil($total_alunos / $por_pagina));
    // Filtro de pesquisa
    $busca = isset($_GET['ac_busca']) ? sanitize_text_field($_GET['ac_busca']) : '';
    $where_busca = '';
    if (!empty($busca)) {
        $like = '%' . $wpdb->esc_like($busca) . '%';
        $where_busca = $wpdb->prepare(" AND (a.nome_completo LIKE %s OR a.numero_processo LIKE %s)", $like, $like);
        // Recount - build full SQL to avoid nested prepare
        $total_alunos = (int)$wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sige_alunos a WHERE a.escola_id = %d AND a.status='activo'", $escola_id) . $where_busca
        );
        $total_pags = max(1, ceil($total_alunos / $por_pagina));
        $pag_atual  = min($pag_atual, $total_pags);
        $offset     = ($pag_atual - 1) * $por_pagina;
    }
    // Filtro por estado de conta
    $filtro_conta = isset($_GET['ac_filtro']) ? sanitize_text_field($_GET['ac_filtro']) : '';
    // [FIX SEC] Use $wpdb->prepare() instead of direct SQL concatenation
    $alunos = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT a.id, a.nome_completo, a.numero_processo, a.status,
                    um.user_id as wp_user_id
             FROM {$wpdb->prefix}sige_alunos a
             LEFT JOIN {$wpdb->usermeta} um ON um.meta_key='sige_aluno_id' AND um.meta_value=a.id
             WHERE a.escola_id = %d AND a.status='activo'",
            $escola_id
        ) . " {$where_busca} ORDER BY a.nome_completo LIMIT " . intval($por_pagina) . " OFFSET " . intval($offset)
    );
    // Aplicar filtro de estado pós-query
    if ($filtro_conta === 'com') {
        $alunos = array_filter($alunos, function($a) { return !empty($a->wp_user_id); });
    } elseif ($filtro_conta === 'sem') {
        $alunos = array_filter($alunos, function($a) { return empty($a->wp_user_id); });
    }
    $ac_percent = $total_alunos > 0 ? (int)round(($com_conta / max(1, $total_alunos)) * 100) : 0;
    $ac_listados = is_array($alunos) ? count($alunos) : 0;
    $ac_icon = static function(string $name): string {
        if (function_exists('sige_ui_icon')) {
            return sige_ui_icon($name);
        }
        return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/></svg>';
    };
    ?>
<style id="sige-aluno-contas-produto-pro-v121056">
/* SIGE SoftGenial - Contas dos Alunos: Compliance Visual Integral v12.10.56
   Referência inegociável: Painel Principal / Dashboard V2 MJS-grade. */
.sige-ac-page{
    --ac-accent:var(--sg-theme-primary,#5a3fd6);
    --ac-accent-dark:var(--sg-theme-primary-800,#3b2f8d);
    --ac-accent-soft:#e9f2ff;
    --ac-purple:#5a3fd6;
    --ac-purple-soft:#f3efff;
    --ac-green:#16a34a;
    --ac-green-soft:#eaf8ef;
    --ac-amber:#f59e0b;
    --ac-amber-soft:#fff7ed;
    --ac-ink:#15152c;
    --ac-muted:#61697c;
    --ac-border:#e9ebf4;
    --ac-bg:#f6f4fb;
    --ac-card:#ffffff;
    --ac-shadow:0 18px 45px rgba(34,34,64,.075);
    --ac-shadow-lg:0 24px 70px rgba(45,36,96,.10);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
    color:var(--ac-ink);
    display:flex;
    flex-direction:column;
    gap:18px;
    padding-bottom:26px;
}
.sige-ac-page *{box-sizing:border-box;}
.sige-ac-svg svg,.sige-ac-page svg{width:18px;height:18px;display:block;}

/* HERO - mesmo padrão claro do Painel Principal */
.sige-ac-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:24px;
    background:linear-gradient(110deg,#ffffff 0%,#ffffff 46%,#eff4ff 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--ac-shadow-lg);
    padding:32px 34px;
    margin:0;
    display:grid;
    grid-template-columns:minmax(0,1.05fr) minmax(300px,.95fr);
    gap:22px;
    align-items:center;
}
.sige-ac-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.sige-ac-hero:after{display:none!important;content:none!important;}
.sige-ac-hero-main,.sige-ac-hero-panel{position:relative;z-index:1;}
.sige-ac-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    padding:0;
    border:0;
    border-radius:0;
    background:transparent;
    color:var(--ac-accent);
    font-size:12px;
    line-height:1.2;
    font-weight:850;
    letter-spacing:.11em;
    text-transform:uppercase;
    box-shadow:none;
}
.sige-ac-kicker:before,.sige-ac-kicker:after{display:none!important;content:none!important;}
.sige-ac-kicker .sige-ac-svg{display:inline-flex;align-items:center;justify-content:center;width:auto;height:auto;border-radius:0;background:transparent;color:currentColor;}
.sige-ac-kicker svg{width:18px!important;height:18px!important;color:currentColor!important;stroke:currentColor!important;fill:none!important;flex:0 0 auto;}
.sige-ac-title{
    margin:0;
    max-width:900px;
    color:var(--ac-ink);
    font-size:clamp(30px,2.55vw,42px);
    line-height:1.08;
    font-weight:850;
    letter-spacing:-.04em;
}
.sige-ac-subtitle{
    max-width:760px;
    margin:var(--space-3) 0 0;
    color:var(--ac-muted);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.sige-ac-hero-panel{
    min-height:148px;
    border-radius:var(--radius-xl);
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:var(--space-3);
    border:1px solid rgba(92,64,187,.08);
}
.sige-ac-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);}
.sige-ac-panel-label{display:flex;align-items:center;gap:var(--space-2);font-size:12px;font-weight:850;text-transform:uppercase;letter-spacing:.11em;color:var(--ac-purple);}
.sige-ac-panel-label svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sige-ac-panel-number{font-size:40px;line-height:1;font-weight:850;letter-spacing:-.045em;color:#1f2140;}
.sige-ac-panel-text{font-size:var(--fs-sm);color:#676f82;line-height:1.55;margin:0;max-width:270px;}
.sige-ac-bar{height:10px;border-radius:var(--radius-pill);background:rgba(255,255,255,.7);overflow:hidden;position:relative;z-index:1;}
.sige-ac-bar span{display:block;height:100%;border-radius:var(--radius-pill);background:linear-gradient(90deg,#6d5dfc,#5038d4);width:0;transition:width .25s ease;}

/* KPIs - padrão Painel Principal */
.sige-ac-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--space-4);}
.sige-ac-stat,.sige-ac-card{
    background:#fff;
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    box-shadow:var(--ac-shadow);
}
.sige-ac-stat{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    gap:var(--space-4);
    align-items:center;
    padding:18px 20px;
    min-height:104px;
    transition:transform .18s ease,box-shadow .18s ease;
}
.sige-ac-stat:hover{transform:translateY(-1px);box-shadow:0 20px 50px rgba(34,34,64,.10);}
.sige-ac-stat:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--card-soft,#f3efff);}
.sige-ac-grid article:nth-child(1){--card-soft:#f4edff;}
.sige-ac-grid article:nth-child(2){--card-soft:#eaf8ef;}
.sige-ac-grid article:nth-child(3){--card-soft:#e9f2ff;}
.sige-ac-stat-icon,.sige-ac-card-icon,.sige-ac-btn-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;position:relative;z-index:1;}
.sige-ac-stat-icon{
    width:46px;height:46px;border-radius:var(--radius-lg);background:var(--ac-soft,#f4f1ff);color:var(--icon-color,var(--ac-purple));box-shadow:0 8px 18px rgba(28,32,54,.06);
}
.sige-ac-grid article:nth-child(1) .sige-ac-stat-icon{--ac-soft:#f4f1ff;--icon-color:var(--ac-purple);}
.sige-ac-grid article:nth-child(2) .sige-ac-stat-icon{--ac-soft:#eaf8ef;--icon-color:var(--ac-green);}
.sige-ac-grid article:nth-child(3) .sige-ac-stat-icon{--ac-soft:#e9f2ff;--icon-color:var(--ac-accent);}
.sige-ac-stat > div{position:relative;z-index:1;}
.sige-ac-stat strong{display:block;font-size:27px;line-height:1;font-weight:850;letter-spacing:-.03em;color:#1f2140;}
.sige-ac-stat span{display:block;margin-top:8px;font-size:12px;line-height:1.25;font-weight:800;color:#646b7b;text-transform:none;letter-spacing:0;}

/* Cards e secções */
.sige-ac-card{overflow:hidden;}
.sige-ac-card-head{
    display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:20px 22px;border-bottom:1px solid var(--ac-border);background:#fff;
}
.sige-ac-card-title{display:flex;align-items:flex-start;gap:var(--space-3);min-width:0;}
.sige-ac-card-icon{width:42px;height:42px;border-radius:14px;background:#f4f1ff;color:var(--ac-purple);}
.sige-ac-card-title h2,.sige-ac-card-title h3{margin:0;font-size:17px;font-weight:900;letter-spacing:-.02em;color:#202037;}
.sige-ac-card-title p{margin:var(--space-1) 0 0;color:#7a8091;font-size:12px;line-height:1.45;font-weight:650;}
.sige-ac-card-meta{font-size:12px;color:#646b7b;font-weight:800;white-space:nowrap;align-self:center;}
.sige-ac-card-body{padding:var(--space-5);}

/* Callout */
.sige-ac-callout{
    display:grid;grid-template-columns:1fr auto;gap:var(--space-4);align-items:center;padding:22px 24px;border-radius:var(--radius-xl);border:1px solid rgba(92,64,187,.12);background:#fff;box-shadow:var(--ac-shadow);
}
.sige-ac-callout h3{display:flex;align-items:center;gap:10px;margin:0 0 var(--space-2);font-size:17px;font-weight:900;color:#202037;}
.sige-ac-callout h3 .sige-ac-svg{color:var(--ac-purple);}
.sige-ac-callout p{margin:0;color:#646b7b;font-size:var(--fs-sm);line-height:1.6;font-weight:650;max-width:940px;}
.sige-ac-credentials{display:flex;gap:var(--space-2);flex-wrap:wrap;margin-top:14px;}
.sige-ac-credential{padding:8px 11px;border-radius:var(--radius-pill);background:#f7f8fc;border:1px solid #edf0f7;font-size:12px;color:#3f4356;font-weight:750;}
.sige-ac-toolbar{padding:16px 18px;border-bottom:1px solid var(--ac-border);background:#fff;}
.sige-ac-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
.sige-ac-search{position:relative;flex:1;min-width:240px;}
.sige-ac-search .sige-ac-svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94a3b8;}
.sige-ac-filter input[type="text"],.sige-ac-filter select{
    width:100%;min-height:44px;padding:10px 12px;border:1px solid #e5e7f0;border-radius:14px;background:#fff;color:#202037;font-size:var(--fs-sm);outline:none;transition:border-color .18s ease,box-shadow .18s ease;box-shadow:0 8px 18px rgba(28,32,54,.04);
}
.sige-ac-search input[type="text"]{padding-left:42px;}
.sige-ac-filter input[type="text"]:focus,.sige-ac-filter select:focus{border-color:rgba(90,63,214,.55);box-shadow:0 0 0 4px rgba(90,63,214,.10);}
.sige-ac-select{width:200px;}

/* Botões */
.sige-ac-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);min-height:44px;padding:0 18px;border:1px solid transparent;border-radius:14px;font-size:var(--fs-sm);font-weight:900;text-decoration:none;cursor:pointer;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;background .18s ease;color:#202037;white-space:nowrap;font-family:inherit;line-height:1;box-shadow:0 10px 26px rgba(31,32,55,.08);
}
.sige-ac-btn:hover{transform:translateY(-1px);}
.sige-ac-btn-primary{background:linear-gradient(135deg,var(--ac-accent),var(--ac-accent-dark));color:#fff;box-shadow:0 16px 32px rgba(11,74,143,.24);}
.sige-ac-btn-primary:hover{box-shadow:0 18px 34px rgba(11,74,143,.28);}
.sige-ac-btn-soft{background:#fff;color:#26263b;border-color:#e5e7f0;}
.sige-ac-btn-muted{background:#f7f8fc;color:#475569;border-color:#edf0f7;}
.sige-ac-btn-danger{background:#fff1f2;color:#991b1b;border-color:#fecdd3;}

/* Progress & logs */
.sige-ac-progress{background:#e6e9f2;border-radius:var(--radius-pill);height:12px;overflow:hidden;margin:var(--space-4) 0 0;display:none;}
.sige-ac-progress-fill{height:100%;background:linear-gradient(90deg,var(--ac-purple),#7b6ef6);border-radius:var(--radius-pill);transition:.3s;width:0;}
#sige-ac-log{font-size:12px;font-family:'JetBrains Mono',Consolas,monospace;max-height:210px;overflow-y:auto;background:#0f172a;color:#d8b4fe;border-radius:18px;padding:14px;margin-top:12px;display:none;line-height:1.6;}

/* Tabela */
.sige-ac-table-wrap{overflow-x:auto;background:#fff;}
.sige-ac-table{width:100%;border-collapse:separate;border-spacing:0;font-size:var(--fs-sm);min-width:780px;}
.sige-ac-table th{padding:13px 14px;text-align:left;border-bottom:1px solid #edf0f7;background:#f7f8fc;color:#3f4356;font-size:12px;font-weight:900;letter-spacing:.04em;text-transform:uppercase;}
.sige-ac-table td{padding:14px;border-bottom:1px solid #f0f2f8;vertical-align:middle;color:#303449;}
.sige-ac-table tr:hover td{background:#fbfcff;}
.sige-ac-student{display:flex;align-items:center;gap:10px;min-width:0;}
.sige-ac-avatar{width:34px;height:34px;border-radius:13px;background:#f4f1ff;color:var(--ac-purple);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;flex:0 0 auto;}
.sige-ac-student strong{display:block;font-size:13.5px;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;}
.sige-ac-code{font-family:'JetBrains Mono',Consolas,monospace;font-size:12px;background:#f8fafc;border:1px solid var(--ac-border);padding:5px 8px;border-radius:10px;color:#334155;}
.sige-ac-pill{display:inline-flex;align-items:center;gap:7px;padding:6px 10px;border-radius:var(--radius-pill);font-size:var(--fs-xs);font-weight:900;white-space:nowrap;}
.sige-ac-pill .dot{width:7px;height:7px;border-radius:var(--radius-pill);display:inline-block;}
.sige-ac-pill.ok{background:#ecfdf5;color:#166534;}
.sige-ac-pill.ok .dot{background:#16a34a;}
.sige-ac-pill.sem{background:#fffbeb;color:#92400e;}
.sige-ac-pill.sem .dot{background:#f59e0b;}
.sige-ac-actions{display:flex;gap:var(--space-2);flex-wrap:wrap;align-items:center;}
.sige-ac-created{display:inline-flex;align-items:center;gap:var(--space-2);color:#166534;font-weight:900;font-size:12px;}
.sige-ac-inline-note{display:block;margin-top:6px;color:#64748b;font-size:11.5px;line-height:1.4;}
.sige-ac-pagination{display:flex;justify-content:center;align-items:center;gap:7px;margin:var(--space-1) 0 0;flex-wrap:wrap;}
.sige-ac-page-link{display:inline-flex;align-items:center;justify-content:center;min-width:38px;min-height:38px;padding:9px 12px;border:1px solid var(--ac-border);border-radius:14px;text-decoration:none;color:#475569;background:#fff;font-size:var(--fs-sm);font-weight:800;}
.sige-ac-page-link.active{border-color:var(--ac-accent);background:var(--ac-accent);color:#fff;}
.sige-ac-page-link:hover{border-color:var(--sg-theme-soft,#f1edff);color:var(--ac-accent);}
.sige-ac-empty{padding:34px;text-align:center;color:var(--ac-muted);}
.sige-ac-empty .sige-ac-card-icon{margin:0 auto var(--space-3);display:inline-flex;}

@media(max-width:980px){
    .sige-ac-hero{grid-template-columns:1fr;padding:26px 24px;}
    .sige-ac-grid{grid-template-columns:1fr;}
    .sige-ac-callout{grid-template-columns:1fr;}
    .sige-ac-callout .sige-ac-btn{width:100%;}
    .sige-ac-card-head{align-items:flex-start;flex-direction:column;}
    .sige-ac-card-meta{white-space:normal;}
    .sige-ac-select{width:100%;}
    .sige-ac-filter .sige-ac-btn{width:100%;}
    .sige-ac-search{min-width:100%;}
}
@media(max-width:640px){
    .sige-ac-title{font-size:30px;}
    .sige-ac-card-body{padding:var(--space-4);}
    .sige-ac-stat{padding:var(--space-4);}
    .sige-ac-actions .sige-ac-btn{width:100%;}
}
</style>
<div class="sige-ac-page">
    <section class="sige-ac-hero" aria-label="Contas dos Alunos">
        <div class="sige-ac-hero-main">
            <div class="sige-ac-kicker"><span class="sige-ac-svg"><?php echo $ac_icon('key'); ?></span><span>Secretaria</span></div>
            <h1 class="sige-ac-title">Contas dos Alunos</h1>
            <p class="sige-ac-subtitle">Crie, acompanhe e envie credenciais de acesso dos alunos com uma experiência limpa, consistente e preparada para operação escolar real.</p>
        </div>
        <aside class="sige-ac-hero-panel" aria-label="Resumo de cobertura das contas">
            <div>
                <div class="sige-ac-panel-label"><span class="sige-ac-svg"><?php echo $ac_icon('shield'); ?></span><span>Cobertura de contas</span></div>
                <div class="sige-ac-panel-number"><?php echo esc_html($ac_percent); ?>%</div>
                <p class="sige-ac-panel-text"><?php echo esc_html($com_conta); ?> de <?php echo esc_html($total_alunos); ?> aluno(s) activo(s) já têm conta ligada ao portal.</p>
            </div>
            <div class="sige-ac-bar" aria-hidden="true"><span style="width:<?php echo esc_attr($ac_percent); ?>%;"></span></div>
        </aside>
    </section>

    <section class="sige-ac-grid" aria-label="Indicadores das contas de alunos">
        <article class="sige-ac-stat">
            <span class="sige-ac-stat-icon"><?php echo $ac_icon('users'); ?></span>
            <div><strong><?php echo esc_html($total_alunos); ?></strong><span>Alunos activos</span></div>
        </article>
        <article class="sige-ac-stat">
            <span class="sige-ac-stat-icon"><?php echo $ac_icon('check'); ?></span>
            <div><strong><?php echo esc_html($com_conta); ?></strong><span>Com conta criada</span></div>
        </article>
        <article class="sige-ac-stat">
            <span class="sige-ac-stat-icon"><?php echo $ac_icon('lock'); ?></span>
            <div><strong><?php echo esc_html($sem_conta); ?></strong><span>Sem conta</span></div>
        </article>
    </section>

    <?php if ($sem_conta > 0): ?>
    <section class="sige-ac-callout" aria-label="Criar contas em lote">
        <div>
            <h3><span class="sige-ac-svg"><?php echo $ac_icon('rocket'); ?></span><span><?php echo esc_html($sem_conta); ?> aluno(s) ainda sem conta</span></h3>
            <p>Crie as contas de acesso em lote para acelerar o trabalho da secretaria. Cada aluno usa o número de processo como utilizador e recebe credenciais que podem ser comunicadas ao encarregado.</p>
            <div class="sige-ac-credentials">
                <span class="sige-ac-credential">Utilizador: número de processo</span>
                <span class="sige-ac-credential">Senha gerada pelo sistema</span>
                <span class="sige-ac-credential">Notificação por email/WhatsApp quando disponível</span>
            </div>
            <div class="sige-ac-progress" id="ac-progress"><div class="sige-ac-progress-fill" id="ac-fill"></div></div>
            <div id="sige-ac-log"></div>
        </div>
        <button class="sige-ac-btn sige-ac-btn-primary" id="btn-prov-lote" type="button">
            <span class="sige-ac-btn-icon"><?php echo $ac_icon('plus'); ?></span><span>Criar contas em lote</span>
        </button>
    </section>
    <?php endif; ?>

    <section class="sige-ac-card" aria-label="Lista de contas de alunos">
        <div class="sige-ac-card-head">
            <div class="sige-ac-card-title">
                <span class="sige-ac-card-icon"><?php echo $ac_icon('users'); ?></span>
                <div>
                    <h2>Lista de Contas</h2>
                    <p>Pesquise alunos, confirme o estado da conta e execute acções de acesso sem sair deste módulo.</p>
                </div>
            </div>
            <div class="sige-ac-card-meta">Página <?php echo esc_html($pag_atual); ?> de <?php echo esc_html($total_pags); ?> · <?php echo esc_html($total_alunos); ?> aluno(s)</div>
        </div>
        <div class="sige-ac-toolbar">
            <form method="GET" class="sige-ac-filter">
                <input type="hidden" name="page" value="sige-app">
                <input type="hidden" name="view" value="aluno_contas">
                <label class="sige-ac-search">
                    <span class="sige-ac-svg"><?php echo $ac_icon('grid'); ?></span>
                    <input type="text" name="ac_busca" value="<?php echo esc_attr($busca); ?>" placeholder="Pesquisar por nome ou número de processo">
                </label>
                <select name="ac_filtro" class="sige-ac-select" aria-label="Filtrar por estado da conta">
                    <option value="">Todos os estados</option>
                    <option value="com" <?php echo $filtro_conta === 'com' ? 'selected' : ''; ?>>Com conta</option>
                    <option value="sem" <?php echo $filtro_conta === 'sem' ? 'selected' : ''; ?>>Sem conta</option>
                </select>
                <button type="submit" class="sige-ac-btn sige-ac-btn-primary"><span class="sige-ac-btn-icon"><?php echo $ac_icon('check'); ?></span><span>Filtrar</span></button>
                <?php if (!empty($busca) || !empty($filtro_conta)): ?>
                <a href="?page=sige-app&view=aluno_contas" class="sige-ac-btn sige-ac-btn-muted"><span>Limpar</span></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="sige-ac-table-wrap">
            <?php if (empty($alunos)): ?>
                <div class="sige-ac-empty">
                    <span class="sige-ac-card-icon"><?php echo $ac_icon('users'); ?></span>
                    <strong>Nenhum aluno encontrado</strong>
                    <p>Altere a pesquisa ou o filtro para consultar outros registos.</p>
                </div>
            <?php else: ?>
            <div class="sige-ac-table-wrap"><table class="sige-ac-table">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Número de processo</th>
                        <th>Estado da conta</th>
                        <th>ID da conta</th>
                        <th>Acções</th>
                    </tr>
                </thead>
                <tbody id="ac-tbody">
                <?php foreach ($alunos as $a):
                    $tem = !empty($a->wp_user_id);
                    $iniciais = '';
                    foreach (preg_split('/\s+/', trim((string)$a->nome_completo)) as $parte) {
                        if ($parte !== '') $iniciais .= mb_substr($parte, 0, 1);
                        if (mb_strlen($iniciais) >= 2) break;
                    }
                    $iniciais = $iniciais ?: 'A';
                ?>
                <tr id="row-<?php echo (int)$a->id; ?>">
                    <td>
                        <div class="sige-ac-student">
                            <span class="sige-ac-avatar"><?php echo esc_html(mb_strtoupper($iniciais)); ?></span>
                            <strong><?php echo esc_html($a->nome_completo); ?></strong>
                        </div>
                    </td>
                    <td><span class="sige-ac-code"><?php echo esc_html($a->numero_processo); ?></span></td>
                    <td>
                        <span class="sige-ac-pill <?php echo $tem ? 'ok' : 'sem'; ?>">
                            <span class="dot" aria-hidden="true"></span>
                            <span><?php echo $tem ? 'Com conta' : 'Sem conta'; ?></span>
                        </span>
                    </td>
                    <td><span class="sige-ac-code"><?php echo $tem ? esc_html($a->wp_user_id) : '-'; ?></span></td>
                    <td>
                        <div class="sige-ac-actions">
                            <?php if (!$tem): ?>
                            <button class="sige-ac-btn sige-ac-btn-primary ac-btn-prov" type="button" data-sige-act="provisionarAluno" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$a->id])); ?>" data-sige-self>
                                <span class="sige-ac-btn-icon"><?php echo $ac_icon('plus'); ?></span><span>Criar conta</span>
                            </button>
                            <?php else: ?>
                            <button class="sige-ac-btn sige-ac-btn-soft ac-btn-reset" type="button" data-sige-act="reporSenha" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$a->id])); ?>" data-sige-self>
                                <span class="sige-ac-btn-icon"><?php echo $ac_icon('key'); ?></span><span>Repor senha</span>
                            </button>
                            <button class="sige-ac-btn sige-ac-btn-primary ac-btn-send" type="button" data-sige-act="enviarCredenciais" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$a->id])); ?>" data-sige-self>
                                <span class="sige-ac-btn-icon"><?php echo $ac_icon('message'); ?></span><span>Reenviar</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($total_pags > 1): ?>
    <nav class="sige-ac-pagination" aria-label="Paginação das contas de alunos">
        <?php
        $base_url = add_query_arg(['page' => 'sige-app', 'view' => 'aluno_contas', 'ac_busca' => $busca, 'ac_filtro' => $filtro_conta], admin_url('admin.php'));
        if ($pag_atual > 1): ?>
            <a class="sige-ac-page-link" href="<?php echo esc_url(add_query_arg('ac_pag', $pag_atual - 1, $base_url)); ?>">Anterior</a>
        <?php endif;
        $inicio = max(1, $pag_atual - 2);
        $fim = min($total_pags, $pag_atual + 2);
        if ($inicio > 1) echo '<span class="sige-ac-page-link">...</span>';
        for ($pg = $inicio; $pg <= $fim; $pg++):
            $activa = $pg === $pag_atual;
        ?>
            <a class="sige-ac-page-link <?php echo $activa ? 'active' : ''; ?>" href="<?php echo esc_url(add_query_arg('ac_pag', $pg, $base_url)); ?>"><?php echo esc_html($pg); ?></a>
        <?php endfor;
        if ($fim < $total_pags) echo '<span class="sige-ac-page-link">...</span>';
        if ($pag_atual < $total_pags): ?>
            <a class="sige-ac-page-link" href="<?php echo esc_url(add_query_arg('ac_pag', $pag_atual + 1, $base_url)); ?>">Seguinte</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</div>
<script <?php echo sige_csp_script_attr(); ?>>
var NONCE = <?php echo wp_json_encode($nonce); ?>;
var TOTAL = <?php echo (int)$total_alunos; ?>;
function sigeAcIcon(name) {
    var icons = {
        check: '<span class="sige-ac-svg"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg></span>',
        key: '<span class="sige-ac-svg"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="15.5" r="5.5"/><path d="M12 11l8-8"/><path d="M17 3h4v4"/></svg></span>',
        message: '<span class="sige-ac-svg"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg></span>'
    };
    return icons[name] || icons.check;
}
function provisionarAluno(alunoId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span>A criar...</span>';
    jQuery.post(ajaxurl, {
        action: 'sige_provisionar_individual',
        nonce: NONCE,
        aluno_id: alunoId
    }, function(res) {
        if (res.success) {
            var row = document.getElementById('row-' + alunoId);
            if (row) {
                var pill = row.querySelector('.sige-ac-pill');
                if (pill) {
                    pill.className = 'sige-ac-pill ok';
                    pill.innerHTML = '<span class="dot" aria-hidden="true"></span><span>Com conta</span>';
                }
                var notifMsg = '';
                if (res.data.notif_email) notifMsg += 'Email enviado (' + res.data.notif_email_dest + ') ';
                if (res.data.notif_wpp) notifMsg += 'WhatsApp enviado (' + res.data.notif_tel + ')';
                if (!res.data.notif_email && !res.data.notif_wpp && !res.data.notif_email_dest && !res.data.notif_tel) notifMsg = 'Sem contacto do encarregado';
                else if (!res.data.notif_email && !res.data.notif_wpp) notifMsg = 'Envio falhou (email: ' + (res.data.notif_email_dest || 'N/A') + ', tel: ' + (res.data.notif_tel || 'N/A') + ')';
                btn.parentNode.innerHTML = '<span class="sige-ac-created">' + sigeAcIcon('check') + '<span>Criada</span></span> <button class="sige-ac-btn sige-ac-btn-soft ac-btn-reset" type="button" data-sige-act="reporSenha" data-sige-args="[' + alunoId + ']" data-sige-self>' + sigeAcIcon('key') + '<span>Repor senha</span></button> <button class="sige-ac-btn sige-ac-btn-primary ac-btn-send" type="button" data-sige-act="enviarCredenciais" data-sige-args="[' + alunoId + ']" data-sige-self>' + sigeAcIcon('message') + '<span>Reenviar</span></button><small class="sige-ac-inline-note">' + notifMsg + '</small>';
            }
        } else {
            btn.innerHTML = '<span>' + (res.data || 'Erro') + '</span>';
            btn.disabled = false;
        }
    }).fail(function() {
        btn.innerHTML = '<span>Erro de rede</span>';
        btn.disabled = false;
    });
}
function enviarCredenciais(alunoId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span>A enviar...</span>';
    jQuery.post(ajaxurl, {
        action: 'sige_enviar_credenciais',
        nonce: NONCE,
        aluno_id: alunoId
    }, function(res) {
        btn.disabled = false;
        if (res.success) {
            var d = res.data;
            var msg = '';
            if (d.email) {
                msg += 'Email enviado para ' + d.email_dest + '\n';
            } else if (d.email_dest) {
                msg += 'Email encontrado (' + d.email_dest + ') mas o envio falhou.\n';
            }
            if (d.wpp) {
                msg += 'WhatsApp enviado para ' + d.tel + '\n';
            } else if (d.tel) {
                msg += 'Telefone encontrado (' + d.tel + ') mas o envio falhou.\n';
            }
            if (!d.email_dest && !d.tel) {
                msg = 'O encarregado não tem email nem telefone preenchido na ficha do aluno.';
            } else if (!msg) {
                msg = 'Contactos encontrados, mas ambos os envios falharam. Verifique a configuração de email/WhatsApp.';
            } else {
                msg = 'Operação concluída.\n' + msg;
            }
            btn.innerHTML = sigeAcIcon('message') + '<span>Reenviar</span>';
            alert(msg);
        } else {
            alert((res.data || 'Erro.'));
            btn.innerHTML = sigeAcIcon('message') + '<span>Reenviar</span>';
        }
    });
}
function reporSenha(alunoId, btn) {
    if (!confirm('Repor a senha deste aluno? Uma nova senha será gerada e enviada ao encarregado.')) return;
    btn.disabled = true;
    btn.innerHTML = '<span>A repor...</span>';
    jQuery.post(ajaxurl, {
        action: 'sige_repor_senha_aluno',
        nonce: NONCE,
        aluno_id: alunoId
    }, function(res) {
        btn.disabled = false;
        btn.innerHTML = sigeAcIcon('key') + '<span>Repor senha</span>';
        alert(res.success ? 'Senha reposta para: ' + (res.data.senha || '(ver notificação enviada)') + '\nComunique ao encarregado.' : (res.data || 'Erro.'));
    });
}
document.getElementById('btn-prov-lote') && document.getElementById('btn-prov-lote').addEventListener('click', function() {
    var btn   = this;
    var prog  = document.getElementById('ac-progress');
    var fill  = document.getElementById('ac-fill');
    var log   = document.getElementById('sige-ac-log');
    var total = TOTAL;
    var criados = 0;
    var processados = 0;
    btn.disabled = true;
    btn.innerHTML = '<span>A processar...</span>';
    prog.style.display = 'block';
    log.style.display  = 'block';
    log.innerHTML      = '';
    function processarLote(offset) {
        jQuery.post(ajaxurl, {
            action: 'sige_provisionar_lote',
            nonce: NONCE,
            offset: offset
        }, function(res) {
            if (!res.success) {
                log.innerHTML += '<div>Erro: ' + (res.data || 'desconhecido') + '</div>';
                btn.innerHTML = '<span>Erro</span>';
                return;
            }
            var d = res.data;
            criados     += d.criados;
            processados += d.processados;
            var pct = total > 0 ? Math.min(100, Math.round(processados / total * 100)) : 100;
            fill.style.width = pct + '%';
            log.innerHTML += '<div>Lote +' + d.processados + ' processados · ' + d.criados + ' contas novas criadas</div>';
            if (d.erros && d.erros.length) {
                d.erros.forEach(function(e) { log.innerHTML += '<div>Alerta: ' + e + '</div>'; });
            }
            log.scrollTop = log.scrollHeight;
            if (d.done || processados >= total) {
                fill.style.width = '100%';
                btn.innerHTML = '<span>Concluído - ' + criados + ' conta(s) criada(s)</span>';
                log.innerHTML += '<div style="font-weight:700;margin-top:8px;">Provisionamento concluído. A página será recarregada para actualizar o estado.</div>';
                setTimeout(function() { location.reload(); }, 2500);
            } else {
                processarLote(d.proximo);
            }
        }).fail(function() {
            log.innerHTML += '<div>Erro de rede no lote ' + offset + '</div>';
            btn.innerHTML = '<span>Erro</span>';
        });
    }
    processarLote(0);
});
</script>
<?php
}