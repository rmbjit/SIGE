<?php
/**
 * SIGE SoftGenial - Secret Vault (cofre de segredos)
 *
 * v12.12.14 (Fase 5, incremento 1). Camada unificada de cifra de segredos em
 * repouso, construida SOBRE a cifra forte ja existente
 * (sige_encrypt_token/sige_decrypt_token: sodium secretbox, com recuo para
 * AES-256-GCM; chave derivada dos salts do WordPress; formatos com prefixo
 * sige2: e gcm1:).
 *
 * Garantias:
 *  - selar nunca perde o segredo: se a cifra nao estiver disponivel ou nao
 *    produzir um formato selado reconhecido, devolve o valor original.
 *  - revelar passa o texto em claro intacto (instalacoes pre-cofre), e so
 *    decifra os nossos formatos com prefixo. Nunca expoe ciphertext.
 *  - idempotente: selar um valor ja selado devolve-o sem alteracao.
 *
 * Modulo de funcoes puras: nao regista hooks.
 */

if (!defined('ABSPATH') && !defined('SIGE_VAULT_TEST_MODE')) exit;

if (!function_exists('sige_vault_is_sealed')) {
    /** Verdadeiro se o valor guardado esta num dos nossos formatos cifrados. */
    function sige_vault_is_sealed(string $stored): bool {
        return strncmp($stored, 'sige2:', 6) === 0 || strncmp($stored, 'gcm1:', 5) === 0;
    }
}

if (!function_exists('sige_vault_seal')) {
    /** Cifra um segredo para repouso. Idempotente e seguro (nunca perde o valor). */
    function sige_vault_seal(string $plain): string {
        if ($plain === '') return '';
        if (sige_vault_is_sealed($plain)) return $plain; // ja selado
        if (function_exists('sige_encrypt_token')) {
            $sealed = (string) sige_encrypt_token($plain);
            // So aceita um resultado num formato selado reconhecido; caso contrario devolve o original.
            return sige_vault_is_sealed($sealed) ? $sealed : $plain;
        }
        return $plain; // sem cifra disponivel: degrada para texto em claro, sem quebrar
    }
}

if (!function_exists('sige_vault_reveal')) {
    /** Revela um segredo guardado. Texto em claro (pre-cofre) passa intacto. */
    function sige_vault_reveal(string $stored): string {
        if ($stored === '') return '';
        if (!sige_vault_is_sealed($stored)) return $stored; // texto em claro: passa intacto
        if (function_exists('sige_decrypt_token')) {
            return (string) sige_decrypt_token($stored);
        }
        return ''; // selado mas sem cifra disponivel: nao expoe ciphertext
    }
}

if (!function_exists('sige_vault_secret_registry')) {
    /**
     * Registo auditavel das chaves-segredo conhecidas, por area. Inclui as que
     * ja eram cifradas (SMTP, WhatsApp), para o registo ser completo, e as que
     * passam a ser cifradas nesta fase (gateways de pagamento).
     */
    function sige_vault_secret_registry(): array {
        return [
            'mpesa'           => ['api_key', 'public_key'],
            'emola'           => ['api_key', 'api_secret'],
            'payment_webhook' => ['webhook_token'],
            'smtp'            => ['password'],       // ja cifrado por sige_smtp_config
            'whatsapp'        => ['whatsapp_token'], // ja cifrado em db-handler
            'license'         => ['license_key'],    // chave de licenca (API do Hub)
        ];
    }
}

if (!function_exists('sige_vault_is_secret_key')) {
    /** Verdadeiro se (fornecedor, chave) e um segredo que deve ser cifrado em repouso. */
    function sige_vault_is_secret_key(string $provider, string $key): bool {
        $provider = strtolower(trim($provider));
        $key = strtolower(trim($key));
        if ($key === '') return false;
        // O token de webhook e segredo independentemente do fornecedor de pagamento.
        if ($key === 'webhook_token') return true;
        $reg = sige_vault_secret_registry();
        return isset($reg[$provider]) && in_array($key, $reg[$provider], true);
    }
}

if (!function_exists('sige_secret_mask')) {
    /**
     * Mascara um segredo para exibicao. Esconde tudo menos os ultimos $visible
     * caracteres; valores curtos ficam totalmente mascarados. Nunca revela o
     * comprimento real (parte escondida com largura fixa).
     */
    function sige_secret_mask(string $value, int $visible = 4): string {
        $len = strlen($value);
        if ($len === 0) return '';
        if ($len <= $visible + 2) return str_repeat('*', $len);
        return '********' . substr($value, -$visible);
    }
}

if (!function_exists('sige_secret_names')) {
    /** Fragmentos de nome que identificam um valor como segredo (para scrub e URL). */
    function sige_secret_names(): array {
        return [
            'senha', 'password', 'passwd', 'pwd',
            'secret', 'api_secret', 'client_secret',
            'token', 'api_key', 'apikey', 'webhook_token',
            'authorization', 'bearer',
        ];
    }
}

if (!function_exists('sige_secret_scrub')) {
    /**
     * Redige segredos de um texto antes de o registar ou de o colocar num URL.
     * Apaga (1) tokens selados pelo cofre (formatos sige2: e gcm1:) e (2) pares
     * chave=valor de segredos conhecidos. O resto do texto passa intacto.
     */
    function sige_secret_scrub(string $text): string {
        if ($text === '') return '';
        $text = (string) preg_replace('/\b(?:sige2|gcm1):[A-Za-z0-9+\/=._-]{8,}/', '[SEGREDO]', $text);
        $names = implode('|', array_map('preg_quote', sige_secret_names()));
        $text = (string) preg_replace('/((?:' . $names . ')\s*[:=]\s*)([^\s&"\'\\\\]+)/i', '$1[SEGREDO]', $text);
        return $text;
    }
}

/* -------------------------------------------------------------------------
 * Segredos por escola e rotacao (Fase 2, incremento 3)
 *
 * Camada de gestao sobre o cofre: guarda segredos isolados por escola, rastreia
 * a antiguidade de cada segredo, gera e roda segredos, e re-sela por higiene.
 * Aditivo: nao altera o armazenamento nem a leitura dos segredos ja existentes.
 * Nomes de opcao dinamicos (por escola); nunca expoe o segredo em registos.
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_secret_school_option')) {
    /** Nome canonico da opcao de um segredo isolado por escola. Vazio se invalido. */
    function sige_secret_school_option(string $name, int $school_id): string {
        $name = preg_replace('/[^a-z0-9_]/', '', strtolower($name));
        $school_id = (int) $school_id;
        if ($name === '' || $school_id <= 0) return '';
        return 'sige_secret_' . $name . '_esc' . $school_id;
    }
}

if (!function_exists('sige_secret_rotation_meta_option')) {
    /** Nome da opcao que guarda o instante da ultima rotacao de um segredo por escola. */
    function sige_secret_rotation_meta_option(string $name, int $school_id): string {
        $base = sige_secret_school_option($name, $school_id);
        return $base === '' ? '' : $base . '_rotacao';
    }
}

if (!function_exists('sige_secret_rotation_days')) {
    /** Idade maxima (dias) antes de um segredo ser considerado a precisar de rotacao. */
    function sige_secret_rotation_days(): int {
        $d = defined('SIGE_SECRET_ROTATION_DAYS') ? (int) SIGE_SECRET_ROTATION_DAYS : 180;
        return $d > 0 ? $d : 180;
    }
}

if (!function_exists('sige_secret_mark_rotated')) {
    /** Regista agora como o instante da ultima rotacao do segredo. */
    function sige_secret_mark_rotated(string $name, int $school_id): bool {
        $opt = sige_secret_rotation_meta_option($name, $school_id);
        if ($opt === '') return false;
        return (bool) update_option($opt, time(), false);
    }
}

if (!function_exists('sige_secret_rotated_at')) {
    /** Instante (unix) da ultima rotacao, ou 0 se nunca. */
    function sige_secret_rotated_at(string $name, int $school_id): int {
        $opt = sige_secret_rotation_meta_option($name, $school_id);
        if ($opt === '') return 0;
        return (int) get_option($opt, 0);
    }
}

if (!function_exists('sige_secret_rotation_due')) {
    /** Verdadeiro se o segredo nunca foi rodado ou ja excedeu a idade maxima. */
    function sige_secret_rotation_due(string $name, int $school_id, int $max_age_days = 0): bool {
        $days = $max_age_days > 0 ? $max_age_days : sige_secret_rotation_days();
        $at = sige_secret_rotated_at($name, $school_id);
        if ($at <= 0) return true;
        return (time() - $at) >= ($days * 86400);
    }
}

if (!function_exists('sige_secret_set_for_school')) {
    /** Guarda (cifrado) um segredo isolado por escola e marca a rotacao. Audita sem o valor. */
    function sige_secret_set_for_school(string $name, string $value, int $school_id): bool {
        $opt = sige_secret_school_option($name, $school_id);
        if ($opt === '') return false;
        $store = function_exists('sige_vault_seal') ? sige_vault_seal($value) : $value;
        $ok = (bool) update_option($opt, $store, false);
        if ($ok) {
            sige_secret_mark_rotated($name, $school_id);
            if (function_exists('sige_security_log')) sige_security_log('segredo_alterado', 'nome=' . $name . ';escola=' . (int) $school_id);
        }
        return $ok;
    }
}

if (!function_exists('sige_secret_get_for_school')) {
    /** Le (e revela) um segredo isolado por escola. Texto em claro passa intacto. */
    function sige_secret_get_for_school(string $name, int $school_id, string $default = ''): string {
        $opt = sige_secret_school_option($name, $school_id);
        if ($opt === '') return $default;
        $raw = get_option($opt, null);
        if ($raw === null || $raw === '') return $default;
        return function_exists('sige_vault_reveal') ? sige_vault_reveal((string) $raw) : (string) $raw;
    }
}

if (!function_exists('sige_secret_delete_for_school')) {
    /** Apaga um segredo por escola e a respectiva marca de rotacao. */
    function sige_secret_delete_for_school(string $name, int $school_id): bool {
        $opt = sige_secret_school_option($name, $school_id);
        if ($opt === '') return false;
        $meta = sige_secret_rotation_meta_option($name, $school_id);
        if ($meta !== '') delete_option($meta);
        return (bool) delete_option($opt);
    }
}

if (!function_exists('sige_secret_generate_token')) {
    /** Gera um segredo aleatorio forte, em texto url-safe (para tokens de webhook, etc.). */
    function sige_secret_generate_token(int $bytes = 32): string {
        $bytes = max(16, min(64, (int) $bytes));
        $raw = '';
        try { $raw = random_bytes($bytes); } catch (Throwable $e) { $raw = ''; }
        if ($raw === '') { $raw = hash('sha256', uniqid('', true) . microtime() . mt_rand(), true); }
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}

if (!function_exists('sige_secret_rotate_for_school')) {
    /**
     * Roda um segredo gerado por escola: gera um novo, guarda-o cifrado, marca a
     * rotacao e devolve o novo segredo em claro (para configurar no fornecedor).
     * Devolve '' se falhar.
     */
    function sige_secret_rotate_for_school(string $name, int $school_id, int $bytes = 32): string {
        $token = sige_secret_generate_token($bytes);
        if (!sige_secret_set_for_school($name, $token, $school_id)) return '';
        if (function_exists('sige_security_log')) sige_security_log('segredo_rodado', 'nome=' . $name . ';escola=' . (int) $school_id);
        return $token;
    }
}

if (!function_exists('sige_vault_reseal')) {
    /**
     * Re-sela um valor guardado por higiene de formato. Revela com a cifra actual e
     * sela de novo. Texto em claro fica selado; valor nao decifravel fica intacto
     * (nunca se perde o segredo). Nao serve para sobreviver a rotacao dos salts do
     * WordPress (ver guia de instalacao).
     */
    function sige_vault_reseal(string $stored): string {
        if ($stored === '') return '';
        if (!function_exists('sige_vault_reveal') || !function_exists('sige_vault_seal')) return $stored;
        $plain = sige_vault_reveal($stored);
        if ($plain === '') return $stored;
        return sige_vault_seal($plain);
    }
}
