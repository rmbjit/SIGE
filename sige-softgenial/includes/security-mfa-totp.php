<?php
/**
 * SIGE SoftGenial - MFA de Operacao: TOTP (aplicacao autenticadora)
 *
 * Fase 4, incremento 2 (v12.12.11). Segundo factor alternativo ao OTP por
 * email, conforme RFC 6238 (TOTP) sobre RFC 4226 (HOTP), HMAC-SHA1, 6 digitos,
 * periodo de 30 segundos: o que Google Authenticator, Authy e Microsoft
 * Authenticator esperam.
 *
 * O nucleo (base32, codigo, verificacao) e validado contra os vectores de
 * referencia da RFC 6238. O segredo e guardado cifrado (sige_encrypt_token,
 * sodium secretbox autenticado) em user meta, com flag de confirmacao separada:
 * so se considera inscrito depois de o utilizador confirmar um codigo de teste.
 *
 * Sem dependencia de JS ou CDN: o QR e gerado no servidor como SVG e o segredo
 * e tambem mostrado em texto para introducao manual (caminho garantido).
 *
 * Desligado por defeito; nao altera o comportamento de quem nao inscrever.
 */

if (!defined('ABSPATH') && !defined('SIGE_MFA_TOTP_TEST_MODE')) exit;

if (!defined('SIGE_MFA_TOTP_PERIOD'))  define('SIGE_MFA_TOTP_PERIOD', 30);
if (!defined('SIGE_MFA_TOTP_DIGITS'))  define('SIGE_MFA_TOTP_DIGITS', 6);
if (!defined('SIGE_MFA_TOTP_WINDOW'))  define('SIGE_MFA_TOTP_WINDOW', 1); // +/- 1 passo (desvio de relogio)
if (!defined('SIGE_MFA_TOTP_ISSUER'))  define('SIGE_MFA_TOTP_ISSUER', 'SIGE SoftGenial');

/* ------------------------------------------------------------------ */
/* Base32 (RFC 4648, alfabeto sem padding no segredo)                  */
/* ------------------------------------------------------------------ */

if (!function_exists('sige_mfa_totp_base32_encode')) {
    function sige_mfa_totp_base32_encode(string $bytes): string {
        if ($bytes === '') return '';
        $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buffer = 0; $bits = 0;
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= $alpha[($buffer >> $bits) & 0x1f];
            }
        }
        if ($bits > 0) {
            $out .= $alpha[($buffer << (5 - $bits)) & 0x1f];
        }
        return $out;
    }
}

if (!function_exists('sige_mfa_totp_base32_decode')) {
    function sige_mfa_totp_base32_decode(string $b32): string {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if ($b32 === '') return '';
        $map = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
        $out = '';
        $buffer = 0; $bits = 0;
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $buffer = ($buffer << 5) | $map[$b32[$i]];
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xff);
            }
        }
        return $out;
    }
}

/* ------------------------------------------------------------------ */
/* HOTP / TOTP (RFC 4226 / RFC 6238)                                   */
/* ------------------------------------------------------------------ */

if (!function_exists('sige_mfa_totp_hotp')) {
    /** HOTP de um contador (RFC 4226), com truncagem dinamica. */
    function sige_mfa_totp_hotp(string $secretBytes, int $counter, int $digits = 6): string {
        // contador em 8 bytes big-endian
        $bin = '';
        for ($i = 7; $i >= 0; $i--) { $bin .= chr(($counter >> ($i * 8)) & 0xff); }
        $hash = hash_hmac('sha1', $bin, $secretBytes, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $part = (ord($hash[$offset]) & 0x7f) << 24
              | (ord($hash[$offset + 1]) & 0xff) << 16
              | (ord($hash[$offset + 2]) & 0xff) << 8
              | (ord($hash[$offset + 3]) & 0xff);
        $mod = $part % (10 ** $digits);
        return str_pad((string) $mod, $digits, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('sige_mfa_totp_code')) {
    /** Codigo TOTP de um segredo base32 num instante (RFC 6238). */
    function sige_mfa_totp_code(string $secretB32, ?int $timestamp = null, ?int $period = null, ?int $digits = null): string {
        $period = $period ?: SIGE_MFA_TOTP_PERIOD;
        $digits = $digits ?: SIGE_MFA_TOTP_DIGITS;
        $timestamp = $timestamp ?? time();
        $secret = sige_mfa_totp_base32_decode($secretB32);
        if ($secret === '') return '';
        $counter = (int) floor($timestamp / $period);
        return sige_mfa_totp_hotp($secret, $counter, $digits);
    }
}

if (!function_exists('sige_mfa_totp_verify')) {
    /** Verifica um codigo TOTP com tolerancia de +/- window passos. Comparacao em tempo constante. */
    function sige_mfa_totp_verify(string $secretB32, string $code, ?int $timestamp = null, ?int $window = null, ?int $period = null, ?int $digits = null): bool {
        $code = preg_replace('/\D/', '', (string) $code);
        $digits = $digits ?: SIGE_MFA_TOTP_DIGITS;
        if (strlen($code) !== $digits) return false;
        $window = $window ?? SIGE_MFA_TOTP_WINDOW;
        $period = $period ?: SIGE_MFA_TOTP_PERIOD;
        $timestamp = $timestamp ?? time();
        $secret = sige_mfa_totp_base32_decode($secretB32);
        if ($secret === '') return false;
        $base = (int) floor($timestamp / $period);
        for ($i = -$window; $i <= $window; $i++) {
            $candidate = sige_mfa_totp_hotp($secret, $base + $i, $digits);
            if (hash_equals($candidate, $code)) return true;
        }
        return false;
    }
}

if (!function_exists('sige_mfa_totp_generate_secret')) {
    /** Segredo aleatorio (160 bits por defeito) em base32. */
    function sige_mfa_totp_generate_secret(int $bytes = 20): string {
        return sige_mfa_totp_base32_encode(random_bytes($bytes));
    }
}

if (!function_exists('sige_mfa_totp_uri')) {
    /** URI otpauth para inscricao (QR ou introducao manual). */
    function sige_mfa_totp_uri(string $secretB32, string $label, string $issuer = ''): string {
        $issuer = $issuer !== '' ? $issuer : SIGE_MFA_TOTP_ISSUER;
        $labelEnc  = rawurlencode($issuer) . ':' . rawurlencode($label);
        $params = 'secret=' . rawurlencode($secretB32)
                . '&issuer=' . rawurlencode($issuer)
                . '&algorithm=SHA1'
                . '&digits=' . SIGE_MFA_TOTP_DIGITS
                . '&period=' . SIGE_MFA_TOTP_PERIOD;
        return 'otpauth://totp/' . $labelEnc . '?' . $params;
    }
}

/* ------------------------------------------------------------------ */
/* Armazenamento por utilizador (segredo cifrado + flag confirmado)    */
/* ------------------------------------------------------------------ */

if (!function_exists('sige_mfa_totp_secret_meta_key'))    { function sige_mfa_totp_secret_meta_key(): string { return '_sige_mfa_totp_secret'; } }
if (!function_exists('sige_mfa_totp_confirmed_meta_key')) { function sige_mfa_totp_confirmed_meta_key(): string { return '_sige_mfa_totp_confirmed'; } }

if (!function_exists('sige_mfa_totp_store_secret')) {
    /** Guarda o segredo cifrado (estado NAO confirmado). */
    function sige_mfa_totp_store_secret(int $user_id, string $secretB32): bool {
        if ($user_id <= 0 || $secretB32 === '' || !function_exists('sige_encrypt_token') || !function_exists('update_user_meta')) return false;
        $enc = sige_encrypt_token($secretB32);
        if ($enc === '') return false;
        update_user_meta($user_id, sige_mfa_totp_secret_meta_key(), $enc);
        update_user_meta($user_id, sige_mfa_totp_confirmed_meta_key(), '0');
        return true;
    }
}

if (!function_exists('sige_mfa_totp_get_secret')) {
    /** Devolve o segredo decifrado (ou '' se ausente). */
    function sige_mfa_totp_get_secret(int $user_id): string {
        if ($user_id <= 0 || !function_exists('get_user_meta') || !function_exists('sige_decrypt_token')) return '';
        $enc = (string) get_user_meta($user_id, sige_mfa_totp_secret_meta_key(), true);
        if ($enc === '') return '';
        return (string) sige_decrypt_token($enc);
    }
}

if (!function_exists('sige_mfa_totp_enrolled')) {
    /** Inscrito = tem segredo E confirmou-o. */
    function sige_mfa_totp_enrolled(int $user_id): bool {
        if ($user_id <= 0 || !function_exists('get_user_meta')) return false;
        return (string) get_user_meta($user_id, sige_mfa_totp_confirmed_meta_key(), true) === '1'
            && sige_mfa_totp_get_secret($user_id) !== '';
    }
}

if (!function_exists('sige_mfa_totp_confirm_enrollment')) {
    /** Confirma a inscricao verificando um codigo de teste contra o segredo guardado. */
    function sige_mfa_totp_confirm_enrollment(int $user_id, string $code): bool {
        $secret = sige_mfa_totp_get_secret($user_id);
        if ($secret === '') return false;
        if (!sige_mfa_totp_verify($secret, $code)) return false;
        if (function_exists('update_user_meta')) update_user_meta($user_id, sige_mfa_totp_confirmed_meta_key(), '1');
        if (function_exists('sige_security_log')) sige_security_log('mfa_totp', "enrolled user_id={$user_id}");
        return true;
    }
}

if (!function_exists('sige_mfa_totp_disable')) {
    /** Desactiva o TOTP do utilizador (apaga segredo e flag). */
    function sige_mfa_totp_disable(int $user_id): void {
        if ($user_id <= 0 || !function_exists('delete_user_meta')) return;
        delete_user_meta($user_id, sige_mfa_totp_secret_meta_key());
        delete_user_meta($user_id, sige_mfa_totp_confirmed_meta_key());
        if (function_exists('sige_security_log')) sige_security_log('mfa_totp', "disabled user_id={$user_id}");
    }
}

if (!function_exists('sige_mfa_totp_verify_user')) {
    /** Verifica um codigo contra o segredo confirmado do utilizador. */
    function sige_mfa_totp_verify_user(int $user_id, string $code): bool {
        if (!sige_mfa_totp_enrolled($user_id)) return false;
        return sige_mfa_totp_verify(sige_mfa_totp_get_secret($user_id), $code);
    }
}

/* ------------------------------------------------------------------ */
/* Codificador QR (modo byte, EC nivel L, versoes 1-10, mascara 0)     */
/* Sem JS nem CDN: matriz calculada no servidor, saida em SVG.         */
/* ------------------------------------------------------------------ */

if (!function_exists('sige_mfa_qr_gf')) {
    /** Tabelas exp/log de GF(256) com primitivo 0x11d (memoizadas). */
    function sige_mfa_qr_gf(): array {
        static $t = null;
        if ($t !== null) return $t;
        $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11d; }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        $t = ['exp' => $exp, 'log' => $log];
        return $t;
    }
}
if (!function_exists('sige_mfa_qr_mul')) {
    function sige_mfa_qr_mul(int $a, int $b): int {
        if ($a === 0 || $b === 0) return 0;
        $g = sige_mfa_qr_gf();
        return $g['exp'][$g['log'][$a] + $g['log'][$b]];
    }
}
if (!function_exists('sige_mfa_qr_rs_divisor')) {
    function sige_mfa_qr_rs_divisor(int $degree): array {
        $result = array_fill(0, $degree, 0); $result[$degree - 1] = 1; $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) { $result[$j] = sige_mfa_qr_mul($result[$j], $root); if ($j + 1 < $degree) $result[$j] ^= $result[$j + 1]; }
            $root = sige_mfa_qr_mul($root, 2);
        }
        return $result;
    }
}
if (!function_exists('sige_mfa_qr_rs_remainder')) {
    function sige_mfa_qr_rs_remainder(array $data, array $divisor): array {
        $degree = count($divisor); $result = array_fill(0, $degree, 0);
        foreach ($data as $b) {
            $factor = $b ^ $result[0]; array_shift($result); $result[] = 0;
            for ($i = 0; $i < $degree; $i++) $result[$i] ^= sige_mfa_qr_mul($divisor[$i], $factor);
        }
        return $result;
    }
}
if (!function_exists('sige_mfa_qr_params')) {
    /** [rawCodewords, numBlocks, eccPerBlock] para EC nivel L, versoes 1-10. */
    function sige_mfa_qr_params(int $v): array {
        $raw  = [1=>26,2=>44,3=>70,4=>100,5=>134,6=>172,7=>196,8=>242,9=>292,10=>346];
        $blk  = [1=>1,2=>1,3=>1,4=>1,5=>1,6=>2,7=>2,8=>2,9=>2,10=>4];
        $ecc  = [1=>7,2=>10,3=>15,4=>20,5=>26,6=>18,7=>20,8=>24,9=>30,10=>18];
        return [$raw[$v], $blk[$v], $ecc[$v]];
    }
}
if (!function_exists('sige_mfa_qr_align')) {
    /** Centros dos padroes de alinhamento por versao (1-10). */
    function sige_mfa_qr_align(int $v): array {
        $a = [1=>[],2=>[6,18],3=>[6,22],4=>[6,26],5=>[6,30],6=>[6,34],7=>[6,22,38],8=>[6,24,42],9=>[6,26,46],10=>[6,28,50]];
        return $a[$v];
    }
}
if (!function_exists('sige_mfa_qr_pick_version')) {
    /** Menor versao 1-10 que comporta $len bytes em modo byte (nivel L). */
    function sige_mfa_qr_pick_version(int $len): int {
        for ($v = 1; $v <= 10; $v++) {
            [$raw, $blk, $ecc] = sige_mfa_qr_params($v);
            $dataCw = $raw - $blk * $ecc;
            $countBits = ($v <= 9) ? 8 : 16;
            $need = 4 + $countBits + $len * 8;
            if ($need <= $dataCw * 8) return $v;
        }
        return 0; // nao cabe ate v10
    }
}
if (!function_exists('sige_mfa_qr_codewords')) {
    /** Constroi os codewords finais (dados + EC interleaved) para a versao. */
    function sige_mfa_qr_codewords(string $data, int $version): array {
        [$raw, $numBlocks, $eccLen] = sige_mfa_qr_params($version);
        $dataCw = $raw - $numBlocks * $eccLen;
        $countBits = ($version <= 9) ? 8 : 16;
        // fluxo de bits
        $bits = '0100'; // modo byte
        $bits .= str_pad(decbin(strlen($data)), $countBits, '0', STR_PAD_LEFT);
        for ($i = 0, $n = strlen($data); $i < $n; $i++) $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        $cap = $dataCw * 8;
        $term = min(4, $cap - strlen($bits));
        $bits .= str_repeat('0', max(0, $term));
        if (strlen($bits) % 8 !== 0) $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        $pads = ['11101100', '00010001']; $pi = 0;
        while (strlen($bits) < $cap) { $bits .= $pads[$pi % 2]; $pi++; }
        // bytes de dados
        $bytes = [];
        for ($i = 0; $i < $cap; $i += 8) $bytes[] = bindec(substr($bits, $i, 8));
        // blocos: curtos e longos diferem em 1 codeword
        $shortLen = intdiv($raw, $numBlocks);
        $numShort = $numBlocks - ($raw % $numBlocks);
        $divisor = sige_mfa_qr_rs_divisor($eccLen);
        $blocks = []; $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $dlen = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat = array_slice($bytes, $k, $dlen); $k += $dlen;
            $ec = sige_mfa_qr_rs_remainder($dat, $divisor);
            if ($i < $numShort) $dat[] = 0; // placeholder p/ interleaving
            $blocks[] = array_merge($dat, $ec);
        }
        // interleave
        $result = [];
        $maxLen = count($blocks[0]);
        for ($i = 0; $i < $maxLen; $i++) {
            for ($j = 0; $j < $numBlocks; $j++) {
                if ($i !== ($shortLen - $eccLen) || $j >= $numShort) $result[] = $blocks[$j][$i];
            }
        }
        return $result;
    }
}
if (!function_exists('sige_mfa_qr_matrix')) {
    /** Matriz de modulos (0/1) com padroes, dados e mascara 0 aplicada. */
    function sige_mfa_qr_matrix(string $uri): ?array {
        $len = strlen($uri);
        $version = sige_mfa_qr_pick_version($len);
        if ($version === 0) return null;
        $codewords = sige_mfa_qr_codewords($uri, $version);
        $size = $version * 4 + 17;
        $mod = []; $fn = [];
        for ($y = 0; $y < $size; $y++) { $mod[$y] = array_fill(0, $size, 0); $fn[$y] = array_fill(0, $size, false); }
        $set = function ($x, $y, $dark) use (&$mod, &$fn, $size) { if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) { $mod[$y][$x] = $dark ? 1 : 0; $fn[$y][$x] = true; } };
        $getbit = function ($v, $i) { return ($v >> $i) & 1; };
        // timing
        for ($i = 0; $i < $size; $i++) { $set(6, $i, $i % 2 == 0); $set($i, 6, $i % 2 == 0); }
        // finders
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as $c) {
            for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) { $d = max(abs($dx), abs($dy)); $set($c[0] + $dx, $c[1] + $dy, ($d != 2 && $d != 4)); }
        }
        // alignment
        $pos = sige_mfa_qr_align($version); $np = count($pos);
        for ($i = 0; $i < $np; $i++) for ($j = 0; $j < $np; $j++) {
            if (($i == 0 && $j == 0) || ($i == 0 && $j == $np - 1) || ($i == $np - 1 && $j == 0)) continue;
            $cx = $pos[$i]; $cy = $pos[$j];
            for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) != 1);
        }
        // dark module
        $set(8, $size - 8, true);
        // format bits (L, mascara 0 = 0x77C4)
        $fmt = 0x77C4;
        for ($i = 0; $i <= 5; $i++) $set(8, $i, $getbit($fmt, $i));
        $set(8, 7, $getbit($fmt, 6)); $set(8, 8, $getbit($fmt, 7)); $set(7, 8, $getbit($fmt, 8));
        for ($i = 9; $i < 15; $i++) $set(14 - $i, 8, $getbit($fmt, $i));
        for ($i = 0; $i < 8; $i++) $set($size - 1 - $i, 8, $getbit($fmt, $i));
        for ($i = 8; $i < 15; $i++) $set(8, $size - 15 + $i, $getbit($fmt, $i));
        $set(8, $size - 8, true);
        // version info (versoes >= 7)
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            $vbits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) { $b = $getbit($vbits, $i); $a = $size - 11 + $i % 3; $bb = intdiv($i, 3); $set($a, $bb, $b); $set($bb, $a, $b); }
        }
        // codewords em zigzag
        $bitIdx = 0; $total = count($codewords) * 8;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right == 6) $right = 5;
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = ((($right + 1) & 2) == 0);
                    $y = $upward ? ($size - 1 - $vert) : $vert;
                    if (!$fn[$y][$x] && $bitIdx < $total) {
                        $mod[$y][$x] = $getbit($codewords[$bitIdx >> 3], 7 - ($bitIdx & 7));
                        $bitIdx++;
                    }
                }
            }
        }
        // mascara 0: (x+y)%2==0
        for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) if (!$fn[$y][$x] && (($x + $y) % 2 == 0)) $mod[$y][$x] ^= 1;
        return $mod;
    }
}
if (!function_exists('sige_mfa_totp_qr_svg')) {
    /** QR do URI otpauth como SVG (quiet zone 4). '' se nao gerar. */
    function sige_mfa_totp_qr_svg(string $uri, int $scale = 6): string {
        $m = sige_mfa_qr_matrix($uri);
        if ($m === null) return '';
        $size = count($m); $q = 4; $dim = ($size + 2 * $q) * $scale;
        $rects = '';
        for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) if ($m[$y][$x]) {
            $px = ($x + $q) * $scale; $py = ($y + $q) * $scale;
            $rects .= '<rect x="' . $px . '" y="' . $py . '" width="' . $scale . '" height="' . $scale . '"/>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" role="img" aria-label="Codigo QR de inscricao TOTP">'
             . '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/>'
             . '<g fill="#000000">' . $rects . '</g></svg>';
    }
}

/* ------------------------------------------------------------------ */
/* Integracao WordPress: pagina de gestao e endpoint de inscricao      */
/* (cada utilizador gere apenas a sua propria aplicacao autenticadora) */
/* ------------------------------------------------------------------ */

if (!function_exists('sige_mfa_totp_has_pending_secret')) {
    /** Ha um segredo guardado ainda nao confirmado? */
    function sige_mfa_totp_has_pending_secret(int $user_id): bool {
        if ($user_id <= 0 || !function_exists('get_user_meta')) return false;
        return sige_mfa_totp_get_secret($user_id) !== ''
            && (string) get_user_meta($user_id, sige_mfa_totp_confirmed_meta_key(), true) !== '1';
    }
}

if (!function_exists('sige_mfa_totp_user_label')) {
    /** Rotulo para o URI otpauth (login do utilizador, com recurso ao email). */
    function sige_mfa_totp_user_label(int $user_id): string {
        if (!function_exists('get_userdata')) return 'utilizador';
        $u = get_userdata($user_id);
        if (!$u) return 'utilizador';
        $label = (string) ($u->user_login ?: $u->user_email ?: ('id' . $user_id));
        return $label;
    }
}

if (!function_exists('sige_mfa_totp_render_page')) {
    /** Pagina de gestao do TOTP do proprio utilizador. */
    function sige_mfa_totp_render_page(): void {
        if (!function_exists('get_current_user_id')) return;
        $uid = (int) get_current_user_id();
        $nonce = wp_create_nonce('sige_mfa_totp_enroll');
        $action = esc_url(admin_url('admin-post.php'));

        echo '<div class="wrap"><h1>Aplicacao autenticadora (MFA de operacao)</h1>';

        $res = get_transient('sige_mfa_totp_result_' . $uid);
        if ($res !== false) {
            delete_transient('sige_mfa_totp_result_' . $uid);
            $map = [
                'inscrito'   => ['success', 'Aplicacao autenticadora activada. As operacoes criticas passam a confirmar-se com o codigo da aplicacao, sem email.'],
                'desactivado'=> ['success', 'Aplicacao autenticadora desactivada. As operacoes criticas voltam a confirmar-se por email.'],
                'codigo'     => ['error', 'Codigo de confirmacao incorrecto. Verifique a hora do telemovel e tente o codigo actual.'],
                'nonce'      => ['error', 'Pedido invalido. Repita a operacao.'],
                'iniciado'   => ['info', 'Leia o codigo QR na sua aplicacao autenticadora (ou introduza a chave manualmente) e confirme com um codigo de 6 digitos.'],
            ];
            if (isset($map[(string) $res])) {
                echo '<div class="notice notice-' . esc_attr($map[(string) $res][0]) . ' is-dismissible"><p>' . esc_html($map[(string) $res][1]) . '</p></div>';
            }
        }

        if (sige_mfa_totp_enrolled($uid)) {
            echo '<p><strong>Estado:</strong> activa. As operacoes criticas sao confirmadas com o codigo da sua aplicacao autenticadora.</p>';
            echo '<form method="post" action="' . $action . '" onsubmit="return confirm(\'Desactivar a aplicacao autenticadora? Passara a confirmar por email.\');">'
               . '<input type="hidden" name="action" value="sige_mfa_totp_enroll">'
               . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
               . '<input type="hidden" name="op" value="disable">'
               . '<button type="submit" class="button">Desactivar aplicacao autenticadora</button>'
               . '</form>';
        } elseif (sige_mfa_totp_has_pending_secret($uid)) {
            $secret = sige_mfa_totp_get_secret($uid);
            $uri = sige_mfa_totp_uri($secret, sige_mfa_totp_user_label($uid));
            $svg = sige_mfa_totp_qr_svg($uri);
            echo '<p><strong>Estado:</strong> por confirmar. Conclua a inscricao.</p>';
            echo '<ol style="max-width:640px">';
            echo '<li>Abra a aplicacao autenticadora (Google Authenticator, Microsoft Authenticator, Authy, FreeOTP).</li>';
            if ($svg !== '') {
                echo '<li>Leia este codigo QR:<br>' . $svg . '</li>';
            }
            echo '<li>Ou introduza a chave manualmente: <code style="font-size:14px;letter-spacing:2px">' . esc_html(chunk_split($secret, 4, ' ')) . '</code> (tipo: baseado no tempo, 6 digitos, 30 segundos).</li>';
            echo '<li>Introduza o codigo de 6 digitos para confirmar:</li>';
            echo '</ol>';
            echo '<form method="post" action="' . $action . '">'
               . '<input type="hidden" name="action" value="sige_mfa_totp_enroll">'
               . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
               . '<input type="hidden" name="op" value="confirm">'
               . '<input type="text" name="sige_mfa_totp_code" class="regular-text" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" placeholder="6 digitos"> '
               . '<button type="submit" class="button button-primary">Confirmar e activar</button>'
               . '</form>';
            echo '<form method="post" action="' . $action . '" style="margin-top:8px">'
               . '<input type="hidden" name="action" value="sige_mfa_totp_enroll">'
               . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
               . '<input type="hidden" name="op" value="start">'
               . '<button type="submit" class="button">Gerar nova chave</button>'
               . '</form>';
        } else {
            echo '<p>Active uma aplicacao autenticadora para confirmar operacoes criticas sem depender do email.</p>';
            echo '<form method="post" action="' . $action . '">'
               . '<input type="hidden" name="action" value="sige_mfa_totp_enroll">'
               . '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '">'
               . '<input type="hidden" name="op" value="start">'
               . '<button type="submit" class="button button-primary">Iniciar inscricao</button>'
               . '</form>';
        }
        echo '</div>';
    }
}

if (!function_exists('sige_mfa_totp_handle_enroll')) {
    /** Trata o endpoint admin-post de inscricao/confirmacao/desactivacao. */
    function sige_mfa_totp_handle_enroll(): void {
        if (!is_user_logged_in()) wp_die('Sessao necessaria.', 403);
        $uid = (int) get_current_user_id();
        $back = admin_url('profile.php?page=sige-mfa-totp');
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_mfa_totp_enroll')) {
            set_transient('sige_mfa_totp_result_' . $uid, 'nonce', 60);
            wp_safe_redirect($back); exit;
        }
        $op = isset($_POST['op']) ? sanitize_text_field(wp_unslash((string) $_POST['op'])) : '';
        if ($op === 'start') {
            $secret = sige_mfa_totp_generate_secret();
            sige_mfa_totp_store_secret($uid, $secret);
            set_transient('sige_mfa_totp_result_' . $uid, 'iniciado', 60);
        } elseif ($op === 'confirm') {
            $code = isset($_POST['sige_mfa_totp_code']) ? sanitize_text_field(wp_unslash((string) $_POST['sige_mfa_totp_code'])) : '';
            $ok = sige_mfa_totp_confirm_enrollment($uid, $code);
            set_transient('sige_mfa_totp_result_' . $uid, $ok ? 'inscrito' : 'codigo', 60);
        } elseif ($op === 'disable') {
            sige_mfa_totp_disable($uid);
            set_transient('sige_mfa_totp_result_' . $uid, 'desactivado', 60);
        }
        wp_safe_redirect($back); exit;
    }
}

if (!defined('SIGE_MFA_TOTP_TEST_MODE')) {
    add_action('admin_menu', function () {
        add_submenu_page(
            'profile.php',
            'Autenticador SIGE (MFA)',
            'Autenticador SIGE',
            'read',
            'sige-mfa-totp',
            'sige_mfa_totp_render_page'
        );
    });
    add_action('admin_post_sige_mfa_totp_enroll', 'sige_mfa_totp_handle_enroll');
}
