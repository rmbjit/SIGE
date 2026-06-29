<?php
/**
 * SIGE SoftGenial - Escudo de Login
 *
 * 1) Rate limit de autenticação (ACTIVO por defeito):
 *    - 5 falhas por utilizador+IP em 10 minutos => bloqueio de 10 minutos;
 *    - 6 falhas por username em 30 minutos => bloqueio de 30 minutos (anti-spray distribuido);
 *    - 12 falhas por IP em 60 minutos => bloqueio de 60 minutos (anti-spray);
 *    - sucesso limpa os contadores do par utilizador+IP;
 *    - tudo registado via sige_security_log('login_shield', ...).
 *    Emergência: define('SIGE_LOGIN_SHIELD_OFF', true) no wp-config.php.
 *
 * 2) Verificação em dois passos por email (OFF por defeito):
 *    - liga-se com update_option('sige_2fa_email', 'on');
 *    - aplica-se aos perfis em sige_2fa_roles (por defeito: sige_director,
 *      sige_admin_ti);
 *    - administradores WP reais ficam ISENTOS salvo
 *      update_option('sige_2fa_incluir_wpadmin', 'on') - protecção contra
 *      lock-out se o SMTP falhar;
 *    - código de 6 dígitos, válido 10 minutos, máximo 5 tentativas;
 *    - emergência: define('SIGE_2FA_OFF', true) no wp-config.php.
 *
 * O fluxo usa apenas filtros padrão do WordPress (authenticate,
 * wp_login_failed, wp_login, login_form, login_message), por isso convive
 * com a página de login personalizada do SIGE sem a alterar.
 */

if (!defined('ABSPATH') && !defined('SIGE_SHIELD_TEST_MODE')) exit;

// ============================================================================
// CAMADA DE ARMAZENAMENTO (transients em produção; array em modo de teste)
// ============================================================================
if (!function_exists('sige_shield_store_get')) {
    function sige_shield_store_get(string $key) {
        if (defined('SIGE_SHIELD_TEST_MODE')) {
            return $GLOBALS['sige_shield_test_store'][$key] ?? false;
        }
        return get_transient($key);
    }
}
if (!function_exists('sige_shield_store_set')) {
    function sige_shield_store_set(string $key, $value, int $ttl): void {
        if (defined('SIGE_SHIELD_TEST_MODE')) {
            $GLOBALS['sige_shield_test_store'][$key] = $value;
            return;
        }
        set_transient($key, $value, $ttl);
    }
}
if (!function_exists('sige_shield_store_delete')) {
    function sige_shield_store_delete(string $key): void {
        if (defined('SIGE_SHIELD_TEST_MODE')) {
            unset($GLOBALS['sige_shield_test_store'][$key]);
            return;
        }
        delete_transient($key);
    }
}
if (!function_exists('sige_shield_now')) {
    function sige_shield_now(): int {
        if (defined('SIGE_SHIELD_TEST_MODE') && isset($GLOBALS['sige_shield_test_now'])) {
            return (int)$GLOBALS['sige_shield_test_now'];
        }
        return time();
    }
}
if (!function_exists('sige_shield_log')) {
    function sige_shield_log(string $detail): void {
        if (function_exists('sige_security_log')) {
            sige_security_log('login_shield', $detail);
        }
    }
}

// ============================================================================
// POLÍTICA (constantes centralizadas para afinação futura)
// ============================================================================
if (!defined('SIGE_SHIELD_USER_MAX'))   define('SIGE_SHIELD_USER_MAX', 5);     // falhas por utilizador+IP
if (!defined('SIGE_SHIELD_USER_WIN'))   define('SIGE_SHIELD_USER_WIN', 600);   // janela: 10 min
if (!defined('SIGE_SHIELD_USER_LOCK'))  define('SIGE_SHIELD_USER_LOCK', 600);  // bloqueio: 10 min
if (!defined('SIGE_SHIELD_USER_GLOBAL_MAX')) define('SIGE_SHIELD_USER_GLOBAL_MAX', 6);    // falhas por username, mesmo mudando IP
if (!defined('SIGE_SHIELD_USER_GLOBAL_WIN')) define('SIGE_SHIELD_USER_GLOBAL_WIN', 1800);  // janela: 30 min
if (!defined('SIGE_SHIELD_USER_GLOBAL_LOCK')) define('SIGE_SHIELD_USER_GLOBAL_LOCK', 1800); // bloqueio: 30 min
if (!defined('SIGE_SHIELD_IP_MAX'))     define('SIGE_SHIELD_IP_MAX', 12);      // falhas por IP
if (!defined('SIGE_SHIELD_IP_WIN'))     define('SIGE_SHIELD_IP_WIN', 3600);    // janela: 60 min
if (!defined('SIGE_SHIELD_IP_LOCK'))    define('SIGE_SHIELD_IP_LOCK', 3600);   // bloqueio: 60 min
if (!defined('SIGE_OTP_TTL'))           define('SIGE_OTP_TTL', 600);           // código válido 10 min
if (!defined('SIGE_OTP_MAX_TENTATIVAS')) define('SIGE_OTP_MAX_TENTATIVAS', 5);

// ============================================================================
// NÚCLEO DO RATE LIMIT (funções puras e testáveis)
// ============================================================================
if (!function_exists('sige_shield_ip')) {
    function sige_shield_ip(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        return preg_replace('/[^0-9a-fA-F:\.]/', '', $ip) ?: '0.0.0.0';
    }
}
if (!function_exists('sige_shield_keys')) {
    function sige_shield_keys(string $username, string $ip): array {
        $u = strtolower(trim($username));
        return [
            'user'        => 'sige_lsh_u_' . md5($u . '|' . $ip),
            'user_global' => 'sige_lsh_ug_' . md5($u),
            'ip'          => 'sige_lsh_i_' . md5($ip),
        ];
    }
}
if (!function_exists('sige_shield_bucket_hit')) {
    /** Regista uma falha no balde e devolve o estado actual [count, locked_until]. */
    function sige_shield_bucket_hit(string $key, int $max, int $window, int $lock): array {
        $now = sige_shield_now();
        $b = sige_shield_store_get($key);
        if (!is_array($b) || ($now - (int)($b['start'] ?? 0)) > $window) {
            $b = ['count' => 0, 'start' => $now, 'locked_until' => 0];
        }
        $b['count'] = (int)$b['count'] + 1;
        if ($b['count'] >= $max) {
            $b['locked_until'] = $now + $lock;
        }
        $ttl = max($window, ((int)$b['locked_until'] - $now) + 60);
        sige_shield_store_set($key, $b, $ttl);
        return $b;
    }
}
if (!function_exists('sige_shield_bucket_locked_until')) {
    function sige_shield_bucket_locked_until(string $key): int {
        $b = sige_shield_store_get($key);
        if (!is_array($b)) return 0;
        $lu = (int)($b['locked_until'] ?? 0);
        return ($lu > sige_shield_now()) ? $lu : 0;
    }
}
if (!function_exists('sige_shield_is_locked')) {
    /** Devolve segundos restantes de bloqueio (0 = livre). */
    function sige_shield_is_locked(string $username, string $ip): int {
        $k = sige_shield_keys($username, $ip);
        $until = max(
            sige_shield_bucket_locked_until($k['user']),
            sige_shield_bucket_locked_until($k['user_global']),
            sige_shield_bucket_locked_until($k['ip'])
        );
        return $until > 0 ? ($until - sige_shield_now()) : 0;
    }
}
if (!function_exists('sige_shield_register_failure')) {
    function sige_shield_register_failure(string $username, string $ip): void {
        $k = sige_shield_keys($username, $ip);
        $bu = sige_shield_bucket_hit($k['user'], SIGE_SHIELD_USER_MAX, SIGE_SHIELD_USER_WIN, SIGE_SHIELD_USER_LOCK);
        $bug = sige_shield_bucket_hit($k['user_global'], SIGE_SHIELD_USER_GLOBAL_MAX, SIGE_SHIELD_USER_GLOBAL_WIN, SIGE_SHIELD_USER_GLOBAL_LOCK);
        $bi = sige_shield_bucket_hit($k['ip'], SIGE_SHIELD_IP_MAX, SIGE_SHIELD_IP_WIN, SIGE_SHIELD_IP_LOCK);
        if (!empty($bu['locked_until']) || !empty($bug['locked_until']) || !empty($bi['locked_until'])) {
            sige_shield_log("lock user={$username} ip={$ip} u_count={$bu['count']} ug_count={$bug['count']} i_count={$bi['count']}");
        }
    }
}
if (!function_exists('sige_shield_register_success')) {
    function sige_shield_register_success(string $username, string $ip): void {
        $k = sige_shield_keys($username, $ip);
        sige_shield_store_delete($k['user']);
        sige_shield_store_delete($k['user_global']);
        // O balde por IP NÃO é limpo em sucesso: protege contra spray lento
        // num IP partilhado sem prejudicar quem acerta à primeira.
    }
}

// ============================================================================
// NÚCLEO DO OTP (funções puras e testáveis)
// ============================================================================
if (!function_exists('sige_otp_key')) {
    function sige_otp_key(int $user_id): string { return 'sige_otp_' . $user_id; }
}
if (!function_exists('sige_otp_issue')) {
    /** Gera, guarda (hash) e devolve o código em claro para envio. */
    function sige_otp_issue(int $user_id): string {
        try { $code = (string)random_int(100000, 999999); }
        catch (Throwable $e) { $code = (string)mt_rand(100000, 999999); }
        sige_shield_store_set(sige_otp_key($user_id), [
            'hash' => hash('sha256', $code . '|' . $user_id),
            'expira' => sige_shield_now() + SIGE_OTP_TTL,
            'tentativas' => 0,
        ], SIGE_OTP_TTL + 60);
        return $code;
    }
}
if (!function_exists('sige_otp_verify')) {
    /** Devolve 'ok' | 'errado' | 'expirado' | 'esgotado' | 'ausente'. */
    function sige_otp_verify(int $user_id, string $code): string {
        $k = sige_otp_key($user_id);
        $b = sige_shield_store_get($k);
        if (!is_array($b)) return 'ausente';
        if (sige_shield_now() > (int)$b['expira']) { sige_shield_store_delete($k); return 'expirado'; }
        $b['tentativas'] = (int)$b['tentativas'] + 1;
        if ($b['tentativas'] > SIGE_OTP_MAX_TENTATIVAS) { sige_shield_store_delete($k); return 'esgotado'; }
        sige_shield_store_set($k, $b, max(60, (int)$b['expira'] - sige_shield_now() + 60));
        $ok = hash_equals((string)$b['hash'], hash('sha256', trim($code) . '|' . $user_id));
        if ($ok) { sige_shield_store_delete($k); return 'ok'; }
        return 'errado';
    }
}
if (!function_exists('sige_otp_pending')) {
    function sige_otp_pending(int $user_id): bool {
        $b = sige_shield_store_get(sige_otp_key($user_id));
        return is_array($b) && sige_shield_now() <= (int)$b['expira'];
    }
}

// ============================================================================
// INTEGRAÇÃO WORDPRESS (só corre fora do modo de teste)
// ============================================================================
if (!defined('SIGE_SHIELD_TEST_MODE')) {

    if (!function_exists('sige_2fa_aplica_ao_user')) {
        function sige_2fa_aplica_ao_user($user): bool {
            if (defined('SIGE_2FA_OFF') && SIGE_2FA_OFF) return false;
            if (get_option('sige_2fa_email', 'off') !== 'on') return false;
            if (!($user instanceof WP_User)) return false;
            $wp_admin_real = function_exists('sige_is_real_wp_admin_user')
                ? sige_is_real_wp_admin_user($user->ID)
                : user_can($user, 'manage_options');
            if ($wp_admin_real && get_option('sige_2fa_incluir_wpadmin', 'off') !== 'on') return false;
            $roles_alvo = (array) get_option('sige_2fa_roles', ['sige_director', 'sige_admin_ti']);
            foreach ($roles_alvo as $cap) {
                if ($cap && user_can($user, (string)$cap)) return true;
            }
            return $wp_admin_real; // se incluiu wpadmin por opção, aplica
        }
    }

    // ── Falha de login -> contar ────────────────────────────────────────────
    add_action('wp_login_failed', function ($username) {
        if (defined('SIGE_LOGIN_SHIELD_OFF') && SIGE_LOGIN_SHIELD_OFF) return;
        sige_shield_register_failure((string)$username, sige_shield_ip());
    }, 10, 1);

    // ── Sucesso -> limpar contadores do utilizador ─────────────────────────
    add_action('wp_login', function ($user_login) {
        sige_shield_register_success((string)$user_login, sige_shield_ip());
    }, 10, 1);

    // ── Porta principal: bloqueio + passo 2FA ──────────────────────────────
    add_filter('authenticate', function ($user, $username, $password) {
        if (defined('SIGE_LOGIN_SHIELD_OFF') && SIGE_LOGIN_SHIELD_OFF) return $user;
        if ($username === '' && $password === '') return $user; // primeiro GET do form

        // 1) Bloqueio por força bruta (corre mesmo antes de validar password).
        $rest = sige_shield_is_locked((string)$username, sige_shield_ip());
        if ($rest > 0) {
            $min = (int)ceil($rest / 60);
            return new WP_Error('sige_locked', sprintf(
                'Por segurança, o acesso desta conta está temporariamente suspenso após várias tentativas falhadas. Tente novamente dentro de %d minuto(s).',
                max(1, $min)
            ));
        }

        // 2) Password ainda não validada ou já errada: deixa o WP seguir.
        if (!($user instanceof WP_User)) return $user;

        // 3) 2FA por email, se aplicável a este utilizador.
        if (!sige_2fa_aplica_ao_user($user)) return $user;

        $codigo = isset($_POST['sige_otp']) ? sanitize_text_field(wp_unslash((string)$_POST['sige_otp'])) : '';
        if ($codigo !== '') {
            $r = sige_otp_verify((int)$user->ID, $codigo);
            if ($r === 'ok') return $user;
            // Uma tentativa errada de MFA depois de password correcta e evento de risco alto.
            sige_shield_register_failure((string)$user->user_login, sige_shield_ip());
            $msgs = [
                'errado'   => 'Código de verificação incorrecto. Confirme o último email recebido.',
                'expirado' => 'O código expirou. Introduza a palavra-passe novamente para receber um novo.',
                'esgotado' => 'Demasiadas tentativas de código. Introduza a palavra-passe novamente para receber um novo.',
                'ausente'  => 'Sessão de verificação não encontrada. Introduza a palavra-passe novamente.',
            ];
            sige_shield_log("otp_{$r} user_id={$user->ID}");
            return new WP_Error('sige_otp_' . $r, $msgs[$r] ?? $msgs['ausente']);
        }

        // Sem código: emitir e pedir o passo 2.
        $code = sige_otp_issue((int)$user->ID);
        $escola_nome = '';
        if (function_exists('sige_get_escola_perfil')) {
            $perfil = sige_get_escola_perfil();
            $escola_nome = $perfil && !empty($perfil->nome) ? (string)$perfil->nome : '';
        }
        $assunto = ($escola_nome !== '' ? $escola_nome . ' - ' : '') . 'Código de acesso ao SIGE';
        $corpo = "Olá {$user->display_name},\n\n"
               . "O seu código de verificação é: {$code}\n\n"
               . "Vale durante 10 minutos. Se não tentou iniciar sessão agora, ignore este email e considere alterar a sua palavra-passe.\n";
        $enviado = wp_mail($user->user_email, $assunto, $corpo);
        if (!$enviado) {
            // Falha de SMTP nunca pode trancar a escola fora do sistema:
            // regista, avisa e NÃO exige o código nesta tentativa.
            sige_shield_log("otp_email_falhou user_id={$user->ID}");
            return $user;
        }
        sige_shield_log("otp_emitido user_id={$user->ID}");
        // Marca o ecrã de login para mostrar o campo do código no reenvio.
        setcookie('sige_otp_step', '1', time() + SIGE_OTP_TTL, COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
        $mask = preg_replace('/(^.).*(@)/', '$1***$2', (string)$user->user_email);
        return new WP_Error('sige_otp_required',
            "Enviámos um código de verificação para {$mask}. Introduza a palavra-passe novamente e o código no campo abaixo.");
    }, 30, 3);

    // ── Campo do código no formulário (só quando o passo 2 está activo) ────
    add_action('login_form', function () {
        $step = (isset($_COOKIE['sige_otp_step']) && $_COOKIE['sige_otp_step'] === '1')
             || (isset($_POST['sige_otp']));
        if (!$step) return;
        echo '<p class="sige-otp-field"><label for="sige_otp">Código de verificação<br>';
        echo '<input type="text" name="sige_otp" id="sige_otp" class="input" value="" size="20" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" placeholder="6 dígitos"></label></p>';
    });
}
