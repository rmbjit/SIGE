<?php
/**
 * SIGE SoftGenial - MFA de Operacao (Step-up)
 *
 * Re-autenticacao por OTP antes de executar operacoes CRITICAS (que movem
 * dinheiro ou alteram credenciais de pagamento). O 2FA de login (escudo) protege
 * a porta de entrada; este modulo exige um segundo factor imediatamente antes da
 * operacao sensivel, reutilizando as primitivas OTP do escudo (sige_otp_issue,
 * sige_otp_verify, sige_otp_pending).
 *
 * Modelo: ao tentar uma operacao critica sem verificacao recente, a operacao e
 * BLOQUEADA (fail-closed), um codigo de 6 digitos e enviado por email, e o
 * utilizador confirma a identidade. Apos confirmar, abre-se uma janela curta
 * (SIGE_MFA_STEPUP_WINDOW) durante a qual as operacoes criticas prosseguem sem
 * novo codigo. Findo o prazo, exige-se novo codigo.
 *
 * Ligar: update_option('sige_mfa_stepup', 'on')
 * Desligar de emergencia: define('SIGE_MFA_STEPUP_OFF', true) no wp-config.php
 * Perfis abrangidos: option 'sige_mfa_stepup_roles' (defeito: sige_director, sige_admin_ti)
 * Janela de verificacao recente: SIGE_MFA_STEPUP_WINDOW (defeito 300s = 5 min)
 *
 * Anti-lockout: e opt-in e desligavel; uma falha so impede a operacao critica
 * (nao o login nem o resto do sistema). Falha de SMTP nunca tranca: se o email
 * nao sair, regista-se e a operacao e permitida nessa tentativa.
 */

if (!defined('ABSPATH') && !defined('SIGE_MFA_TEST_MODE')) exit;

if (!defined('SIGE_MFA_STEPUP_WINDOW')) define('SIGE_MFA_STEPUP_WINDOW', 300); // 5 min

if (!function_exists('sige_mfa_stepup_enabled')) {
    /** O step-up esta ligado? (opt-in por opcao; desligavel de emergencia). */
    function sige_mfa_stepup_enabled(): bool {
        if (defined('SIGE_MFA_STEPUP_OFF') && SIGE_MFA_STEPUP_OFF) return false;
        return get_option('sige_mfa_stepup', 'off') === 'on';
    }
}

if (!function_exists('sige_mfa_stepup_strict')) {
    /**
     * Modo estrito (opt-in, defeito desligado): se ligado, uma falha de envio do
     * codigo por email NAO permite a operacao critica (seguranca acima de
     * disponibilidade). Liga-se com update_option('sige_mfa_stepup_strict','on')
     * ou define('SIGE_MFA_STEPUP_STRICT', true). Por defeito mantem-se o
     * anti-lockout (falha de SMTP permite a operacao).
     */
    function sige_mfa_stepup_strict(): bool {
        if (defined('SIGE_MFA_STEPUP_STRICT') && SIGE_MFA_STEPUP_STRICT) return true;
        return get_option('sige_mfa_stepup_strict', 'off') === 'on';
    }
}

if (!function_exists('sige_mfa_stepup_roles')) {
    /** Perfis a quem o step-up se aplica. */
    function sige_mfa_stepup_roles(): array {
        $r = get_option('sige_mfa_stepup_roles', ['sige_director', 'sige_admin_ti']);
        return is_array($r) ? $r : ['sige_director', 'sige_admin_ti'];
    }
}

if (!function_exists('sige_mfa_applies_to_user')) {
    /** Aplica-se a este utilizador? (pelos perfis configurados). */
    function sige_mfa_applies_to_user(int $user_id): bool {
        if ($user_id <= 0) return false;
        $user = get_userdata($user_id);
        if (!$user) return false;
        return (bool) array_intersect(sige_mfa_stepup_roles(), (array) $user->roles);
    }
}

if (!function_exists('sige_mfa_window_key')) {
    function sige_mfa_window_key(int $user_id): string { return 'sige_mfa_ok_' . $user_id; }
}

if (!function_exists('sige_mfa_recently_verified')) {
    /** Ha verificacao de step-up dentro da janela? */
    function sige_mfa_recently_verified(int $user_id): bool {
        if ($user_id <= 0) return false;
        return get_transient(sige_mfa_window_key($user_id)) !== false;
    }
}

if (!function_exists('sige_mfa_mark_verified')) {
    /** Regista step-up bem sucedido: abre a janela curta. */
    function sige_mfa_mark_verified(int $user_id): void {
        if ($user_id <= 0) return;
        set_transient(sige_mfa_window_key($user_id), time(), SIGE_MFA_STEPUP_WINDOW);
        if (function_exists('sige_security_log')) {
            sige_security_log('mfa_stepup', "verified user_id={$user_id}");
        }
    }
}

if (!function_exists('sige_mfa_pending')) {
    /** Ha um desafio pendente? OTP por email emitido, ou desafio TOTP em curso. */
    function sige_mfa_pending(int $user_id): bool {
        if ($user_id <= 0) return false;
        if (function_exists('sige_mfa_totp_enrolled') && sige_mfa_totp_enrolled($user_id)
            && function_exists('get_transient') && get_transient('sige_mfa_totp_pending_' . $user_id)) {
            return true;
        }
        return function_exists('sige_otp_pending') && sige_otp_pending($user_id);
    }
}

if (!function_exists('sige_mfa_send_challenge_email')) {
    /** Envia o codigo de confirmacao de operacao critica. Devolve true se enviado. */
    function sige_mfa_send_challenge_email($user, string $code, string $contexto): bool {
        if (!is_object($user) || empty($user->user_email)) return false;
        $escola_nome = '';
        if (function_exists('sige_get_escola_perfil')) {
            $perfil = sige_get_escola_perfil();
            $escola_nome = $perfil && !empty($perfil->nome) ? (string) $perfil->nome : '';
        }
        $assunto = ($escola_nome !== '' ? $escola_nome . ' - ' : '') . 'Codigo de confirmacao de operacao critica';
        $corpo = "Ola {$user->display_name},\n\n"
               . "Foi pedida uma operacao critica no SIGE que exige confirmacao de identidade.\n"
               . "O seu codigo de confirmacao e: {$code}\n\n"
               . "Vale durante 10 minutos. Se nao foi voce a iniciar esta operacao, ignore este email e altere a sua palavra-passe.\n";
        return (bool) wp_mail((string) $user->user_email, $assunto, $corpo);
    }
}

if (!function_exists('sige_mfa_issue_challenge')) {
    /**
     * Emite um desafio OTP por email (idempotente dentro do TTL do OTP).
     * Devolve true se ja ha um desafio pendente ou se o email saiu; false se o
     * email falhou (o chamador decide permitir a operacao para nao trancar).
     */
    function sige_mfa_issue_challenge(int $user_id, string $contexto): bool {
        if ($user_id <= 0) return false;
        if (sige_mfa_pending($user_id)) return true; // ja emitido/em curso, ainda valido
        // Inscrito em TOTP: nao envia email, o codigo vem da aplicacao autenticadora.
        if (function_exists('sige_mfa_totp_enrolled') && sige_mfa_totp_enrolled($user_id)) {
            if (function_exists('set_transient')) {
                set_transient('sige_mfa_totp_pending_' . $user_id, 1, defined('SIGE_OTP_TTL') ? SIGE_OTP_TTL : 600);
            }
            if (function_exists('sige_security_log')) {
                sige_security_log('mfa_stepup', "challenge_totp_expected user_id={$user_id} ctx={$contexto}");
            }
            return true;
        }
        if (!function_exists('sige_otp_issue')) return false;
        $code = sige_otp_issue($user_id);
        $user = get_userdata($user_id);
        $enviado = sige_mfa_send_challenge_email($user, $code, $contexto);
        if (function_exists('sige_security_log')) {
            sige_security_log('mfa_stepup', ($enviado ? 'challenge_issued' : 'challenge_email_failed') . " user_id={$user_id} ctx={$contexto}");
        }
        return $enviado;
    }
}

if (!function_exists('sige_mfa_verify_challenge')) {
    /** Verifica o codigo. Devolve 'ok'|'errado'|'expirado'|'esgotado'|'ausente'. Em 'ok' abre a janela. */
    function sige_mfa_verify_challenge(int $user_id, string $code): string {
        if ($user_id <= 0) return 'ausente';
        // Inscrito em TOTP: verifica o codigo da aplicacao, com tecto de tentativas.
        if (function_exists('sige_mfa_totp_enrolled') && sige_mfa_totp_enrolled($user_id)) {
            $fkey = 'sige_mfa_totp_fails_' . $user_id;
            $fails = function_exists('get_transient') ? (int) get_transient($fkey) : 0;
            if ($fails >= 10) {
                if (function_exists('sige_security_log')) sige_security_log('mfa_stepup', "verify_esgotado_totp user_id={$user_id}");
                return 'esgotado';
            }
            if (function_exists('sige_mfa_totp_verify_user') && sige_mfa_totp_verify_user($user_id, $code)) {
                if (function_exists('delete_transient')) { delete_transient($fkey); delete_transient('sige_mfa_totp_pending_' . $user_id); }
                sige_mfa_mark_verified($user_id);
                if (function_exists('sige_security_log')) sige_security_log('mfa_stepup', "verify_ok_totp user_id={$user_id}");
                return 'ok';
            }
            if (function_exists('set_transient')) set_transient($fkey, $fails + 1, 300);
            if (function_exists('sige_security_log')) sige_security_log('mfa_stepup', "verify_errado_totp user_id={$user_id}");
            return 'errado';
        }
        // Caminho email (OTP do escudo de login).
        if (!function_exists('sige_otp_verify')) return 'ausente';
        $r = sige_otp_verify($user_id, $code);
        if ($r === 'ok') sige_mfa_mark_verified($user_id);
        else if (function_exists('sige_security_log')) sige_security_log('mfa_stepup', "verify_{$r} user_id={$user_id}");
        return $r;
    }
}

if (!function_exists('sige_mfa_require_step_up')) {
    /**
     * GUARD fail-closed para operacoes criticas. Invocar ANTES de executar.
     * Devolve true se a operacao pode prosseguir (step-up desligado, nao aplicavel,
     * verificacao recente, ou falha de email anti-lockout). Devolve false se e
     * preciso confirmar identidade primeiro: nesse caso emite o desafio e o
     * chamador deve abortar a operacao e mostrar o pedido de codigo.
     */
    function sige_mfa_require_step_up(string $contexto): bool {
        if (!sige_mfa_stepup_enabled()) return true;
        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        if ($uid <= 0) return true; // sem utilizador: outros guards (tenant/permissao) tratam
        if (!sige_mfa_applies_to_user($uid)) return true;
        if (sige_mfa_recently_verified($uid)) {
            // A5: auditar cada operacao critica autorizada pela janela, para rasto completo.
            if (function_exists('sige_security_log')) {
                sige_security_log('mfa_stepup', "satisfied_window user_id={$uid} ctx={$contexto}");
            }
            return true;
        }
        // Precisa de confirmar: emitir desafio.
        $enviado = sige_mfa_issue_challenge($uid, $contexto);
        if (!$enviado) {
            // A2: em modo estrito, falha de email NAO permite a operacao (seguranca > disponibilidade).
            if (sige_mfa_stepup_strict()) {
                if (function_exists('sige_security_log')) {
                    sige_security_log('mfa_stepup', "strict_block_email_failed user_id={$uid} ctx={$contexto}");
                }
                return false;
            }
            // Defeito (anti-lockout): falha de SMTP nunca tranca a operacao critica.
            return true;
        }
        return false; // bloquear e pedir codigo
    }
}

if (!function_exists('sige_mfa_render_challenge_form')) {
    /** HTML do formulario de confirmacao (codigo OTP). Reutilizado no aviso e inline. */
    function sige_mfa_render_challenge_form(): string {
        if (!function_exists('admin_url')) return '';
        $action = esc_url(admin_url('admin-post.php'));
        $nonce = wp_create_nonce('sige_mfa_confirm');
        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $is_totp = function_exists('sige_mfa_totp_enrolled') && sige_mfa_totp_enrolled($uid);
        if ($is_totp) {
            $intro = 'Introduza o codigo de 6 digitos da sua aplicacao autenticadora para autorizar a operacao.';
        } else {
            $intro = 'Enviamos um codigo de 6 digitos para o seu email. Introduza-o para autorizar a operacao.';
        }
        $cfg = '';
        if (function_exists('admin_url')) {
            $url = esc_url(admin_url('profile.php?page=sige-mfa-totp'));
            $cfg = $is_totp
                ? '<br><a href="' . $url . '">Gerir aplicacao autenticadora</a>'
                : '<br><a href="' . $url . '">Configurar aplicacao autenticadora (dispensa o email)</a>';
        }
        return '<div class="notice notice-warning"><p><strong>Confirmacao de operacao critica</strong><br>'
             . esc_html($intro) . $cfg . '</p>'
             . '<form method="post" action="' . $action . '">'
             . '<input type="hidden" name="action" value="sige_mfa_confirm">'
             . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
             . '<input type="text" name="sige_mfa_code" class="regular-text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" placeholder="6 digitos"> '
             . '<button type="submit" class="button button-primary">Confirmar identidade</button>'
             . '</form></div>';
    }
}

// ============================================================================
// INTEGRACAO WORDPRESS (so corre fora do modo de teste)
// ============================================================================
if (!defined('SIGE_MFA_TEST_MODE')) {

    // Endpoint de confirmacao: o utilizador submete o codigo recebido por email.
    add_action('admin_post_sige_mfa_confirm', function () {
        if (!is_user_logged_in()) wp_die('Sessao necessaria.', 403);
        $uid = (int) get_current_user_id();
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_mfa_confirm')) {
            set_transient('sige_mfa_result_' . $uid, 'nonce', 60);
            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        }
        $code = isset($_POST['sige_mfa_code']) ? sanitize_text_field(wp_unslash((string) $_POST['sige_mfa_code'])) : '';
        $r = sige_mfa_verify_challenge($uid, $code);
        set_transient('sige_mfa_result_' . $uid, $r, 60);
        // Reposicao automatica: se a identidade foi confirmada e ha uma operacao
        // de servico pendente, re-executa-a uma unica vez (consumo atomico).
        if ($r === 'ok' && function_exists('sige_mfa_replay_consume_and_run')) {
            $replay = sige_mfa_replay_consume_and_run($uid);
            if (is_array($replay)) set_transient('sige_mfa_replay_result_' . $uid, $replay, 60);
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    });

    // Aviso de UI: resultado da ultima confirmacao (via transient) e o formulario
    // do codigo quando ha desafio pendente. Nao le a query string (sem query_handler).
    add_action('admin_notices', function () {
        if (!is_user_logged_in()) return;
        $uid = (int) get_current_user_id();
        $replay = get_transient('sige_mfa_replay_result_' . $uid);
        $estado = get_transient('sige_mfa_result_' . $uid);
        if ($estado !== false) {
            delete_transient('sige_mfa_result_' . $uid);
            if ($estado === 'ok') {
                if (is_array($replay)) {
                    delete_transient('sige_mfa_replay_result_' . $uid);
                    if (!empty($replay['ok'])) {
                        echo '<div class="notice notice-success is-dismissible"><p>Identidade confirmada. A operacao critica foi concluida automaticamente.</p></div>';
                    } else {
                        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html('Identidade confirmada, mas a operacao nao pode ser concluida automaticamente: ' . (string) ($replay['error'] ?? 'motivo desconhecido') . '. Repita a operacao.') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>Identidade confirmada. Pode repetir a operacao critica.</p></div>';
                }
            } else {
                $msgs = [
                    'errado'   => 'Codigo de confirmacao incorrecto. Confirme o ultimo email recebido.',
                    'expirado' => 'O codigo expirou. Repita a operacao para receber um novo.',
                    'esgotado' => 'Demasiadas tentativas. Repita a operacao para receber um novo codigo.',
                    'ausente'  => 'Sessao de confirmacao nao encontrada. Repita a operacao.',
                    'nonce'    => 'Pedido invalido. Repita a operacao.',
                ];
                if (isset($msgs[(string) $estado])) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($msgs[(string) $estado]) . '</p></div>';
                }
            }
        }
        // O formulario inline e renderizado pelos handlers em pedidos POST.
        // Aqui cobrem-se os pedidos GET (recarregamento e redireccionamento de
        // config), evitando dois formularios no mesmo pedido.
        $is_post = isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST';
        if (!$is_post && sige_mfa_pending($uid) && !sige_mfa_recently_verified($uid)) {
            echo sige_mfa_render_challenge_form();
        }
    });
}
