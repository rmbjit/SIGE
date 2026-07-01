<?php
/**
 * SIGE SoftGenial - Seguranca de uploads e ficheiros
 * Fase 1 (documentos, uploads e QR) - Incremento 1: blindagem do directorio de
 * uploads e bloqueio de tipos perigosos a entrada.
 *
 * Esta camada nao move ficheiros nem altera onde a Biblioteca de Media os guarda
 * (isso fica para o incremento 2). Faz duas coisas, ambas defensivas:
 *   1. Garante, de forma idempotente, ficheiros de proteccao no directorio de
 *      uploads (.htaccess e web.config) que impedem a execucao e o acesso web a
 *      ficheiros perigosos (PHP e scripts). O servico autenticado de documentos
 *      continua a funcionar porque le por readfile, do lado do servidor.
 *   2. Recusa, no momento do upload (wp_handle_upload_prefilter) e nos pontos de
 *      importacao do plugin, ficheiros perigosos, por extensao, por bytes magicos
 *      e por inicio de ficheiro. Bloqueia apenas o que e comprovadamente perigoso,
 *      para nao recusar uploads legitimos.
 *
 * Sem nova superficie de accao (admin_init ja e superficie contabilizada; o
 * prefilter e um filtro, nao um endpoint), sem opcoes novas, sem alteracao de
 * esquema e sem tocar em regras de calculo.
 */
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
 * Listas canonicas (puras, sem dependencia do WordPress)
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_dangerous_extensions')) {
    /** Extensoes que nunca devem ser aceites num upload nem servidas pelo web. */
    function sige_uploads_dangerous_extensions(): array {
        return [
            'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'phps',
            'phar', 'pht', 'pgif', 'inc',
            'cgi', 'pl', 'py', 'rb', 'jsp', 'jspx', 'asp', 'aspx', 'asa', 'cer',
            'sh', 'bash', 'ksh', 'csh', 'bin',
            'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'so', 'jar', 'scr', 'vbs',
            'wsf', 'ps1', 'app', 'deb', 'rpm',
            'htaccess', 'htpasswd', 'user.ini',
        ];
    }
}

if (!function_exists('sige_uploads_dangerous_mimes')) {
    /** Tipos MIME reais (por bytes magicos) que devem ser sempre recusados. */
    function sige_uploads_dangerous_mimes(): array {
        return [
            'text/x-php', 'application/x-php', 'application/x-httpd-php',
            'application/x-httpd-php-source', 'application/php',
            'text/x-shellscript', 'application/x-sh', 'application/x-shellscript',
            'text/x-perl', 'application/x-perl',
            'text/x-python', 'application/x-python-code', 'application/x-python',
            'application/x-executable', 'application/x-pie-executable',
            'application/x-dosexec', 'application/x-msdownload', 'application/x-msdos-program',
            'application/x-mach-binary', 'application/x-elf', 'application/x-sharedlib',
            'application/java-archive', 'application/x-java-archive',
            'application/x-msi', 'application/vnd.microsoft.portable-executable',
        ];
    }
}

if (!function_exists('sige_uploads_filename_has_dangerous_ext')) {
    /**
     * Verdadeiro se QUALQUER segmento de extensao do nome for perigoso.
     * Apanha duplas extensoes enganosas (por exemplo, factura.php.jpg), porque
     * alguns servidores executam pelo primeiro segmento conhecido.
     */
    function sige_uploads_filename_has_dangerous_ext(string $name): bool {
        $name = strtolower(trim($name));
        if ($name === '') return false;
        $danger = array_fill_keys(sige_uploads_dangerous_extensions(), true);
        $parts = explode('.', $name);
        array_shift($parts); // primeiro elemento e o nome base, nao uma extensao
        foreach ($parts as $seg) {
            $seg = trim($seg);
            if ($seg !== '' && isset($danger[$seg])) return true;
        }
        return false;
    }
}

if (!function_exists('sige_uploads_head_is_executable_or_script')) {
    /**
     * Verdadeiro se o INICIO do ficheiro denunciar codigo executavel ou script,
     * mesmo que a extensao e o MIME pareçam inofensivos. Verificacao de alta
     * precisao (so o inicio), para nao recusar dados legitimos que mencionem
     * estes tokens mais a frente.
     */
    function sige_uploads_head_is_executable_or_script(string $path): bool {
        if (!is_file($path) || !is_readable($path)) return false;
        $fh = @fopen($path, 'rb');
        if (!$fh) return false;
        $head = (string) fread($fh, 512);
        fclose($fh);
        if ($head === '') return false;

        // Assinaturas binarias de executavel.
        if (strncmp($head, "MZ", 2) === 0) return true;          // PE / DOS
        if (strncmp($head, "\x7fELF", 4) === 0) return true;     // ELF
        if (strncmp($head, "\xca\xfe\xba\xbe", 4) === 0) return true; // Mach-O / class
        if (strncmp($head, "\xfe\xed\xfa", 3) === 0) return true; // Mach-O
        if (strncmp($head, "#!", 2) === 0) return true;           // shebang

        // Abertura de PHP ou de template de servidor logo no inicio (ignorando
        // espacos e um eventual BOM UTF-8).
        $trim = ltrim($head, "\xEF\xBB\xBF \t\r\n");
        $low = strtolower($trim);
        foreach (['<?php', '<?=', '<%', '<script language="php"'] as $needle) {
            if (strncmp($low, $needle, strlen($needle)) === 0) return true;
        }
        // <? curto seguido de letra (php curto), evitando apanhar XML <?xml.
        if (strncmp($low, '<?', 2) === 0 && strncmp($low, '<?xml', 5) !== 0) return true;

        return false;
    }
}

if (!function_exists('sige_uploads_svg_is_malicious')) {
    /** Verdadeiro se um conteudo SVG trouxer script, eventos ou entidades perigosas. */
    function sige_uploads_svg_is_malicious(string $content): bool {
        if ($content === '') return false;
        $low = strtolower($content);
        $bad = [
            '<script', 'javascript:', '<foreignobject', '<iframe', '<embed',
            '<!entity', '<!doctype', 'xlink:href="javascript', 'data:text/html',
            'onload=', 'onerror=', 'onclick=', 'onmouseover=', 'onbegin=', 'onactivate=',
            'set attributename', '<use xlink:href="http', '<handler',
        ];
        foreach ($bad as $needle) {
            if (strpos($low, $needle) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('sige_uploads_detect_real_mime')) {
    /** MIME real por bytes magicos (finfo, com recuo para mime_content_type). */
    function sige_uploads_detect_real_mime(string $path): string {
        if (!is_file($path) || !is_readable($path)) return '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($path);
            if (is_string($mime) && $mime !== '') return strtolower($mime);
        }
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if (is_string($mime) && $mime !== '') return strtolower($mime);
        }
        return '';
    }
}

/* -------------------------------------------------------------------------
 * Avaliador central: este ficheiro e perigoso?
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_assess_file')) {
    /**
     * Avalia um ficheiro carregado. Devolve ['dangerous' => bool, 'reason' => string].
     * Perigoso se: extensao perigosa (qualquer segmento), inicio executavel/script,
     * MIME real perigoso, imagem declarada que nao e imagem, ou SVG malicioso.
     */
    function sige_uploads_assess_file(string $path, string $name): array {
        $name = (string) $name;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (sige_uploads_filename_has_dangerous_ext($name)) {
            return ['dangerous' => true, 'reason' => 'extensao_perigosa'];
        }
        if (sige_uploads_head_is_executable_or_script($path)) {
            return ['dangerous' => true, 'reason' => 'inicio_executavel_ou_script'];
        }

        $mime = sige_uploads_detect_real_mime($path);
        if ($mime !== '' && in_array($mime, sige_uploads_dangerous_mimes(), true)) {
            return ['dangerous' => true, 'reason' => 'mime_real_perigoso'];
        }

        // Imagem declarada tem de ser mesmo uma imagem (apanha PHP renomeado p/ .jpg).
        $img_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'];
        if (in_array($ext, $img_ext, true)) {
            $is_image = false;
            if (function_exists('getimagesize')) {
                $info = @getimagesize($path);
                $is_image = is_array($info) && !empty($info[0]) && !empty($info[1]);
            } elseif ($mime !== '') {
                $is_image = strpos($mime, 'image/') === 0;
            } else {
                $is_image = true; // sem ferramentas, nao bloqueia (evita falso positivo)
            }
            if (!$is_image) {
                return ['dangerous' => true, 'reason' => 'imagem_invalida'];
            }
        }

        // SVG: examinar o conteudo (script/eventos/entidades).
        if ($ext === 'svg' || $mime === 'image/svg+xml' || $mime === 'image/svg') {
            $content = (string) @file_get_contents($path, false, null, 0, 256 * 1024);
            if (sige_uploads_svg_is_malicious($content)) {
                return ['dangerous' => true, 'reason' => 'svg_malicioso'];
            }
        }

        return ['dangerous' => false, 'reason' => ''];
    }
}

/* -------------------------------------------------------------------------
 * Filtro da Biblioteca de Media (wp_handle_upload_prefilter)
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_prefilter')) {
    /**
     * Recusa ficheiros perigosos no momento do upload. So bloqueia o que e
     * comprovadamente perigoso; tudo o resto segue para a validacao normal do
     * WordPress (papeis, upload_mimes), para nao recusar tipos legitimos.
     */
    function sige_uploads_prefilter(array $file): array {
        if (!empty($file['error'])) return $file; // ja vem com erro
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        $name = isset($file['name']) ? (string) $file['name'] : '';
        if ($tmp === '' || !is_file($tmp)) return $file;

        $verdict = sige_uploads_assess_file($tmp, $name);
        if (!empty($verdict['dangerous'])) {
            if (function_exists('sige_security_log')) {
                sige_security_log('upload_bloqueado', 'nome=' . substr($name, 0, 120) . '; motivo=' . $verdict['reason']);
            }
            $file['error'] = 'Este tipo de ficheiro nao e permitido por seguranca.';
        }
        return $file;
    }
}

/* -------------------------------------------------------------------------
 * Validacao nos pontos de importacao do proprio plugin
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_validate_import_file')) {
    /**
     * Valida um ficheiro de importacao do plugin (por exemplo, lista de alunos).
     * Confirma a extensao contra a lista permitida e que o conteudo corresponde ao
     * tipo declarado: .xlsx tem de ser um pacote ZIP (bytes PK); .csv/.txt tem de ser
     * texto e nao pode comecar por codigo. Devolve ['ok'=>bool, 'error'=>string].
     */
    function sige_uploads_validate_import_file(string $tmp, string $name, array $allowed_ext): array {
        if ($tmp === '' || !is_file($tmp) || !is_readable($tmp)) {
            return ['ok' => false, 'error' => 'Ficheiro de importacao invalido.'];
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed_ext = array_map('strtolower', $allowed_ext);
        if (!in_array($ext, $allowed_ext, true)) {
            return ['ok' => false, 'error' => 'Formato nao suportado.'];
        }
        if (sige_uploads_filename_has_dangerous_ext($name) || sige_uploads_head_is_executable_or_script($tmp)) {
            return ['ok' => false, 'error' => 'O ficheiro carregado nao e uma lista valida.'];
        }

        $fh = @fopen($tmp, 'rb');
        $head = $fh ? (string) fread($fh, 8) : '';
        if ($fh) fclose($fh);

        if ($ext === 'xlsx') {
            // OOXML e um ZIP: tem de comecar por PK\x03\x04 (ou PK vazio/spanned).
            if (strncmp($head, "PK\x03\x04", 4) !== 0 && strncmp($head, "PK\x05\x06", 4) !== 0 && strncmp($head, "PK\x07\x08", 4) !== 0) {
                return ['ok' => false, 'error' => 'O ficheiro .xlsx nao e um pacote Excel valido.'];
            }
        } else {
            // csv/txt: tem de ser texto, nao um binario disfarcado.
            $mime = sige_uploads_detect_real_mime($tmp);
            $texto_ok = ($mime === '' || strpos($mime, 'text/') === 0 || $mime === 'application/csv' || $mime === 'application/vnd.ms-excel' || $mime === 'inode/x-empty');
            if (!$texto_ok && in_array($mime, sige_uploads_dangerous_mimes(), true)) {
                return ['ok' => false, 'error' => 'O ficheiro de importacao tem um conteudo nao permitido.'];
            }
        }
        return ['ok' => true, 'error' => ''];
    }
}

/* -------------------------------------------------------------------------
 * Blindagem do directorio de uploads (.htaccess, web.config, index.php)
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_hardening_marker')) {
    /** Marcador de versao das regras, para permitir refrescar sem opcoes novas. */
    function sige_uploads_hardening_marker(): string {
        return 'SIGE-UPLOADS-HARDENING v1';
    }
}

if (!function_exists('sige_uploads_htaccess_contents')) {
    function sige_uploads_htaccess_contents(): string {
        $marker = sige_uploads_hardening_marker();
        return "# {$marker}\n"
            . "# Impede a execucao e o acesso web a ficheiros perigosos no directorio de uploads.\n"
            . "# O servico autenticado de documentos do SIGE le por readfile (lado do servidor) e nao e afectado.\n"
            . "<FilesMatch \"(?i)\\.(php|php[0-9]?|phtml|phps|phar|pht|inc|cgi|pl|py|rb|jsp|jspx|asp|aspx|sh|bash|exe|com|bat|cmd|msi|dll|so|jar|scr|vbs|wsf|ps1|htaccess|htpasswd)$\">\n"
            . "    <IfModule mod_authz_core.c>\n"
            . "        Require all denied\n"
            . "    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n"
            . "        Order allow,deny\n"
            . "        Deny from all\n"
            . "    </IfModule>\n"
            . "</FilesMatch>\n"
            . "<IfModule mod_php.c>\n"
            . "    php_admin_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php7.c>\n"
            . "    php_admin_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php8.c>\n"
            . "    php_admin_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_mime.c>\n"
            . "    RemoveHandler .php .phtml .phps .phar .pht .php3 .php4 .php5 .php6 .php7 .php8\n"
            . "    RemoveType .php .phtml .phps .phar .pht\n"
            . "</IfModule>\n";
    }
}

if (!function_exists('sige_uploads_webconfig_contents')) {
    function sige_uploads_webconfig_contents(): string {
        $exts = ['.php', '.php3', '.php4', '.php5', '.php7', '.php8', '.phtml', '.phps', '.phar', '.pht', '.cgi', '.pl', '.py', '.jsp', '.asp', '.aspx', '.sh', '.exe', '.com', '.bat', '.cmd', '.msi', '.dll', '.jar', '.vbs', '.ps1'];
        $lines = '';
        foreach ($exts as $e) {
            $lines .= '          <add fileExtension="' . $e . '" allowed="false" />' . "\n";
        }
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!-- " . sige_uploads_hardening_marker() . " -->\n"
            . "<configuration>\n"
            . "  <system.webServer>\n"
            . "    <security>\n"
            . "      <requestFiltering>\n"
            . "        <fileExtensions allowUnlisted=\"true\">\n"
            . $lines
            . "        </fileExtensions>\n"
            . "      </requestFiltering>\n"
            . "    </security>\n"
            . "  </system.webServer>\n"
            . "</configuration>\n";
    }
}

if (!function_exists('sige_uploads_write_protection_files')) {
    /**
     * Escreve/refresca os ficheiros de proteccao num directorio. Idempotente:
     * so escreve se faltar ou se o marcador de versao nao estiver presente.
     * Recebe o directorio para ser testavel sem o WordPress.
     */
    function sige_uploads_write_protection_files(string $dir): bool {
        $dir = rtrim($dir, "/\\");
        if ($dir === '' || !is_dir($dir) || !is_writable($dir)) return false;
        $marker = sige_uploads_hardening_marker();

        $targets = [
            '.htaccess'  => 'sige_uploads_htaccess_contents',
            'web.config' => 'sige_uploads_webconfig_contents',
        ];
        foreach ($targets as $file => $builder) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            $needs = true;
            if (is_file($path)) {
                $cur = (string) @file_get_contents($path);
                if ($cur !== '' && strpos($cur, $marker) !== false) {
                    $needs = false; // ja na versao corrente
                }
            }
            if ($needs) {
                @file_put_contents($path, call_user_func($builder), LOCK_EX);
            }
        }
        $idx = $dir . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($idx)) {
            @file_put_contents($idx, "<?php\n// Silence is golden.\n", LOCK_EX);
        }
        return true;
    }
}

if (!function_exists('sige_uploads_protection_present')) {
    /** Verdadeiro se .htaccess e web.config existirem com o marcador de versao. */
    function sige_uploads_protection_present(string $dir): bool {
        $dir = rtrim($dir, "/\\");
        $marker = sige_uploads_hardening_marker();
        foreach (['.htaccess', 'web.config'] as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) return false;
            $cur = (string) @file_get_contents($path);
            if (strpos($cur, $marker) === false) return false;
        }
        return true;
    }
}

if (!function_exists('sige_uploads_harden_dir')) {
    /** Resolve o directorio de uploads e garante a proteccao. Hook de admin_init. */
    function sige_uploads_harden_dir(): void {
        if (!function_exists('wp_get_upload_dir')) return;
        $u = wp_get_upload_dir();
        $base = is_array($u) && !empty($u['basedir']) ? (string) $u['basedir'] : '';
        if ($base === '' || !is_dir($base)) return;
        sige_uploads_write_protection_files($base);
    }
}

/* -------------------------------------------------------------------------
 * Registo dos hooks (admin_init ja e superficie; o prefilter e filtro)
 * ---------------------------------------------------------------------- */

if (function_exists('add_action')) {
    add_action('admin_init', 'sige_uploads_harden_dir');
}
if (function_exists('add_filter')) {
    add_filter('wp_handle_upload_prefilter', 'sige_uploads_prefilter');

    // [v12.37.2] Permitir imagens AVIF na Biblioteca de Média. O WordPress só
    // passou a aceitar AVIF por omissão na versão 6.5; em versões anteriores (ou
    // consoante a configuração) o upload era recusado com "tipo não permitido".
    // Adicionamos o mime de forma idempotente, sem remover nada do que já existe.
    // O prefilter de segurança acima continua a validar o conteúdo real.
    add_filter('upload_mimes', function ($mimes) {
        if (is_array($mimes) && empty($mimes['avif'])) {
            $mimes['avif'] = 'image/avif';
        }
        return $mimes;
    });
}
