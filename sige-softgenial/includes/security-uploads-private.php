<?php
/**
 * SIGE SoftGenial - Armazenamento privado de documentos sensiveis
 * Fase 1 (documentos, uploads e QR) - Incremento 2.
 *
 * Os documentos sensiveis do aluno (doc_bi_url, doc_cert_url, doc_vacina_url) e da
 * equipa (doc_bi, doc_cv, doc_cert dentro de documentos_urls) entravam pela
 * Biblioteca de Media e ficavam no directorio publico wp-content/uploads, com a URL
 * publica a responder a quem a tivesse. Este incremento move esses ficheiros para um
 * directorio privado (wp-content/uploads/sige-private/docs/<escola_id>), negado ao
 * acesso web directo, e passa a guardar a URL ja dentro desse directorio. O servico
 * autenticado de documentos (secure-document-download) continua a servir por readfile,
 * pelo que o caminho de acesso para o utilizador nao muda; apenas a URL directa deixa
 * de devolver o ficheiro.
 *
 * A foto (campo foto / foto_perfil) NAO e abrangida: e mostrada em linha como imagem e
 * e semi-publica por desenho. So os documentos ja servidos exclusivamente pelo endpoint
 * seguro sao tornados privados, pelo que a interface nao se altera.
 *
 * Estrategia, sem nova superficie de accao, sem opcoes novas e sem alteracao de esquema:
 *   1. Na gravacao (choke-point sige_uploads_privatize_doc_value), os documentos novos
 *      sao movidos para o directorio privado e a URL guardada aponta para la.
 *   2. Um dreno idempotente e resumivel, pendurado no admin_init das paginas do SIGE,
 *      trata os documentos ja existentes em lotes. Quando nada resta, fica inerte.
 *   O movimento so e consumado depois de o original ser removido com sucesso; em caso
 *   de falha, aborta sem perda e o valor guardado mantem-se (degrada para o de hoje).
 */
if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------
 * Directorio privado e proteccao (negar todo o acesso web)
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_private_marker')) {
    function sige_uploads_private_marker(): string { return 'SIGE-PRIVATE-DOCS v1'; }
}

if (!function_exists('sige_uploads_private_deny_htaccess')) {
    function sige_uploads_private_deny_htaccess(): string {
        return "# " . sige_uploads_private_marker() . "\n"
            . "# Directorio privado. Acesso web directo negado; servido apenas pelo endpoint autenticado.\n"
            . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
    }
}

if (!function_exists('sige_uploads_private_deny_webconfig')) {
    function sige_uploads_private_deny_webconfig(): string {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!-- " . sige_uploads_private_marker() . " -->\n"
            . "<configuration>\n  <system.webServer>\n    <authorization>\n"
            . "      <remove users=\"*\" roles=\"\" verbs=\"\" />\n"
            . "      <add accessType=\"Deny\" users=\"*\" />\n"
            . "    </authorization>\n  </system.webServer>\n</configuration>\n";
    }
}

if (!function_exists('sige_uploads_write_deny_all')) {
    /** Escreve proteccao de negacao total num directorio. Idempotente pelo marcador. */
    function sige_uploads_write_deny_all(string $dir): bool {
        $dir = rtrim($dir, "/\\");
        if ($dir === '' || !is_dir($dir) || !is_writable($dir)) return false;
        $marker = sige_uploads_private_marker();
        $targets = [
            '.htaccess'  => 'sige_uploads_private_deny_htaccess',
            'web.config' => 'sige_uploads_private_deny_webconfig',
        ];
        foreach ($targets as $file => $builder) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            $needs = true;
            if (is_file($path)) {
                $cur = (string) @file_get_contents($path);
                if ($cur !== '' && strpos($cur, $marker) !== false) $needs = false;
            }
            if ($needs) @file_put_contents($path, call_user_func($builder), LOCK_EX);
        }
        $idx = $dir . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($idx)) @file_put_contents($idx, "<?php\n// Silence is golden.\nhttp_response_code(403);\nexit;\n", LOCK_EX);
        return true;
    }
}

if (!function_exists('sige_uploads_private_root')) {
    /** Raiz privada dos documentos: wp-content/uploads/sige-private/docs (sem escola). */
    function sige_uploads_private_root(): string {
        if (!function_exists('wp_get_upload_dir')) return '';
        $u = wp_get_upload_dir();
        $base = is_array($u) && !empty($u['basedir']) ? rtrim((string)$u['basedir'], "/\\") : '';
        return $base === '' ? '' : $base . '/sige-private/docs';
    }
}

if (!function_exists('sige_uploads_ensure_private_dir')) {
    /** Garante o directorio privado da escola e a proteccao de negacao total. */
    function sige_uploads_ensure_private_dir(int $escola_id): string {
        $root = sige_uploads_private_root();
        if ($root === '') return '';
        // Proteger ao nivel de sige-private/docs (aplica-se recursivamente em Apache).
        if (!is_dir($root)) {
            if (function_exists('wp_mkdir_p')) { if (!wp_mkdir_p($root)) return ''; }
            elseif (!@mkdir($root, 0755, true) && !is_dir($root)) { return ''; }
        }
        sige_uploads_write_deny_all($root);
        $dir = $root . '/' . max(0, $escola_id);
        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) { if (!wp_mkdir_p($dir)) return ''; }
            elseif (!@mkdir($dir, 0755, true) && !is_dir($dir)) { return ''; }
        }
        // Indice de silencio tambem na subpasta da escola (defesa adicional).
        $idx = $dir . '/index.php';
        if (!is_file($idx)) @file_put_contents($idx, "<?php\n// Silence is golden.\nhttp_response_code(403);\nexit;\n", LOCK_EX);
        return is_dir($dir) ? $dir : '';
    }
}

/* -------------------------------------------------------------------------
 * Deteccao e movimento
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_value_is_private')) {
    /** Verdadeiro se o valor ja aponta para o directorio privado. */
    function sige_uploads_value_is_private(string $value): bool {
        return $value !== '' && stripos($value, '/sige-private/') !== false;
    }
}

if (!function_exists('sige_uploads_resolve_public_local_path')) {
    /** Resolve uma URL publica de uploads para o caminho local, ou '' se nao for local. */
    function sige_uploads_resolve_public_local_path(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        if (function_exists('sige_secure_document_resolve_local_path')) {
            return sige_secure_document_resolve_local_path($url);
        }
        return '';
    }
}

if (!function_exists('sige_uploads_move_siblings_to_private')) {
    /** Move (melhor esforco) as variantes de tamanho e pre-visualizacoes ao lado do original. */
    function sige_uploads_move_siblings_to_private(string $src, string $dest_dir): void {
        $src_dir = dirname($src);
        $basename = wp_basename($src);
        $name_no_ext = preg_replace('/\.[^.]+$/', '', $basename);
        if ($name_no_ext === '' || $name_no_ext === null) return;
        $ext = pathinfo($basename, PATHINFO_EXTENSION);
        $glob_name = str_replace(['*', '?', '['], ['\\*', '\\?', '\\['], $name_no_ext);
        $patterns = [
            $src_dir . '/' . $glob_name . '-*x*.' . $ext,
            $src_dir . '/' . $glob_name . '-scaled.' . $ext,
            $src_dir . '/' . $glob_name . '-*x*-scaled.' . $ext,
            $src_dir . '/' . $glob_name . '-pdf.jpg',
            $src_dir . '/' . $glob_name . '-*x*-pdf.jpg',
        ];
        foreach ($patterns as $pat) {
            foreach (glob($pat) ?: [] as $sib) {
                if (!is_file($sib) || $sib === $src) continue;
                $sib_target = rtrim($dest_dir, "/\\") . DIRECTORY_SEPARATOR . wp_basename($sib);
                if (@copy($sib, $sib_target)) @unlink($sib);
            }
        }
    }
}

if (!function_exists('sige_uploads_move_to_private')) {
    /**
     * Move um ficheiro de uma URL publica de uploads para o directorio privado da escola.
     * Devolve a nova URL privada, ou '' se nao for possivel (caso em que nada e alterado).
     * So consuma o movimento depois de remover o original; se a remocao falhar, aborta
     * apagando a copia, para nao deixar duplicado nem perder o ficheiro.
     */
    function sige_uploads_move_to_private(string $public_url, int $escola_id): string {
        $src = sige_uploads_resolve_public_local_path($public_url);
        if ($src === '' || !is_file($src) || !is_readable($src)) return '';

        $dir = sige_uploads_ensure_private_dir($escola_id);
        if ($dir === '') return '';

        $basename = wp_basename($src);
        $target_name = function_exists('wp_unique_filename') ? wp_unique_filename($dir, $basename) : (time() . '-' . $basename);
        $target = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . $target_name;

        $src_size = filesize($src);
        if (!@copy($src, $target)) return '';
        if (!is_file($target) || filesize($target) !== $src_size) { @unlink($target); return ''; }

        // Construir a URL privada antes de remover o original.
        $u = wp_get_upload_dir();
        $basedir = is_array($u) && !empty($u['basedir']) ? rtrim((string)$u['basedir'], "/\\") : '';
        $baseurl = is_array($u) && !empty($u['baseurl']) ? rtrim((string)$u['baseurl'], '/') : '';
        if ($basedir === '' || $baseurl === '') { @unlink($target); return ''; }
        $rel = ltrim(str_replace('\\', '/', substr($target, strlen($basedir))), '/');
        if ($rel === '' || stripos($rel, 'sige-private/') === false) { @unlink($target); return ''; }
        $private_url = $baseurl . '/' . $rel;

        // Consumar: remover o original. Se nao for possivel, abortar sem duplicado.
        if (!@unlink($src)) { @unlink($target); return ''; }

        // Original removido com sucesso: agora mover as variantes (melhor esforco).
        sige_uploads_move_siblings_to_private($src, $dir);

        if (function_exists('sige_security_log')) {
            sige_security_log('documento_privatizado', 'escola=' . $escola_id . '; ficheiro=' . $basename);
        }
        return $private_url;
    }
}

if (!function_exists('sige_uploads_privatize_doc_value')) {
    /**
     * Choke-point: dado o valor de um campo de documento, devolve-o ja privado.
     * Vazio ou ja privado: devolve inalterado. URL publica com ficheiro local: move e
     * devolve a URL privada. Qualquer falha: devolve o valor original (sem regressao).
     */
    function sige_uploads_privatize_doc_value(string $value, ?int $escola_id = null): string {
        $value = trim($value);
        if ($value === '' || sige_uploads_value_is_private($value)) return $value;
        $eid = $escola_id;
        if ($eid === null) $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($eid <= 0) return $value;
        $moved = sige_uploads_move_to_private($value, $eid);
        return $moved !== '' ? $moved : $value;
    }
}

/* -------------------------------------------------------------------------
 * Dreno de migracao (idempotente e resumivel, sem opcao de estado)
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_private_migration_drain')) {
    /**
     * Migra ate $limit documentos ainda publicos (alunos e equipa) para o directorio
     * privado. Devolve quantos foram migrados. Re-consulta o que resta a cada chamada,
     * pelo que e idempotente e resumivel sem qualquer marcador de estado.
     */
    function sige_uploads_private_migration_drain(int $limit = 150): int {
        global $wpdb;
        if (!isset($wpdb) || $limit <= 0) return 0;
        $done = 0;

        // 1) Alunos: doc_bi_url, doc_cert_url, doc_vacina_url.
        $ta = $wpdb->prefix . 'sige_alunos';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($ta))) === $ta) {
            $like = '%/uploads/%'; $notlike = '%/sige-private/%';
            $sql = $wpdb->prepare(
                "SELECT id, escola_id, doc_bi_url, doc_cert_url, doc_vacina_url FROM {$ta}
                 WHERE (doc_bi_url LIKE %s AND doc_bi_url NOT LIKE %s)
                    OR (doc_cert_url LIKE %s AND doc_cert_url NOT LIKE %s)
                    OR (doc_vacina_url LIKE %s AND doc_vacina_url NOT LIKE %s)
                 LIMIT %d",
                $like, $notlike, $like, $notlike, $like, $notlike, $limit
            );
            $rows = $wpdb->get_results($sql);
            foreach ($rows as $r) {
                $eid = (int) $r->escola_id;
                $new = [
                    'doc_bi_url'     => sige_uploads_privatize_doc_value((string)$r->doc_bi_url, $eid),
                    'doc_cert_url'   => sige_uploads_privatize_doc_value((string)$r->doc_cert_url, $eid),
                    'doc_vacina_url' => sige_uploads_privatize_doc_value((string)$r->doc_vacina_url, $eid),
                ];
                $changed = ($new['doc_bi_url'] !== (string)$r->doc_bi_url)
                    || ($new['doc_cert_url'] !== (string)$r->doc_cert_url)
                    || ($new['doc_vacina_url'] !== (string)$r->doc_vacina_url);
                if ($changed) {
                    $wpdb->update($ta, $new, ['id' => (int)$r->id, 'escola_id' => $eid]);
                    $done++;
                }
                if ($done >= $limit) return $done;
            }
        }

        // 2) Equipa: documentos_urls (JSON com doc_bi, doc_cv, doc_cert).
        $tp = $wpdb->prefix . 'sige_professores';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tp))) === $tp) {
            $like = '%/uploads/%'; $notlike_all = '%sige-private%';
            $sql = $wpdb->prepare(
                "SELECT id, escola_id, documentos_urls FROM {$tp}
                 WHERE documentos_urls LIKE %s AND documentos_urls NOT LIKE %s
                 LIMIT %d",
                $like, $notlike_all, $limit
            );
            $rows = $wpdb->get_results($sql);
            foreach ($rows as $r) {
                $eid = (int) $r->escola_id;
                $docs = json_decode((string)$r->documentos_urls, true);
                if (!is_array($docs)) $docs = json_decode(stripslashes((string)$r->documentos_urls), true);
                if (!is_array($docs)) continue;
                $changed = false;
                foreach (['doc_bi', 'doc_cv', 'doc_cert'] as $f) {
                    if (!empty($docs[$f]) && is_string($docs[$f])) {
                        $np = sige_uploads_privatize_doc_value($docs[$f], $eid);
                        if ($np !== $docs[$f]) { $docs[$f] = $np; $changed = true; }
                    }
                }
                if ($changed) {
                    $enc = function_exists('wp_json_encode') ? wp_json_encode($docs, JSON_UNESCAPED_UNICODE) : json_encode($docs);
                    $wpdb->update($tp, ['documentos_urls' => $enc], ['id' => (int)$r->id, 'escola_id' => $eid]);
                    $done++;
                }
                if ($done >= $limit) return $done;
            }
        }
        return $done;
    }
}

if (!function_exists('sige_uploads_maybe_run_private_migration')) {
    /**
     * Avanca a migracao nas paginas do SIGE. Liga-se ao admin_init (ja contabilizado como
     * superficie). Nao corre em AJAX nem em cron. Quando nada resta, o dreno e barato.
     */
    function sige_uploads_maybe_run_private_migration(): void {
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return;
        if (function_exists('wp_doing_cron') && wp_doing_cron()) return;
        if (function_exists('is_admin') && !is_admin()) return;
        $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
        if (strpos($page, 'sige') !== 0) return; // so nas paginas do SIGE
        sige_uploads_private_migration_drain(150);
        sige_uploads_private_orphan_sweep(100);
    }
}

/* -------------------------------------------------------------------------
 * Limpeza de orfaos (ficheiros privados sem referencia, com periodo de graca)
 * ---------------------------------------------------------------------- */

if (!function_exists('sige_uploads_private_ref_key')) {
    /**
     * Chave de referencia de um valor: o fragmento do caminho a partir de
     * sige-private/ (robusto a http/https e a diferencas de base). Devolve '' se o
     * valor nao apontar para o directorio privado.
     */
    function sige_uploads_private_ref_key(string $value): string {
        $value = str_replace('\\', '/', $value);
        $pos = stripos($value, '/sige-private/');
        if ($pos === false) {
            $pos2 = stripos($value, 'sige-private/');
            if ($pos2 !== 0) return '';
            return rtrim($value, '/');
        }
        return rtrim(substr($value, $pos + 1), '/');
    }
}

if (!function_exists('sige_uploads_private_referenced_keys')) {
    /**
     * Conjunto de chaves de referencia de todos os documentos privados referenciados
     * (alunos e equipa). Devolve null se alguma consulta falhar (nesse caso NAO se
     * deve apagar nada). A foto nao entra (nao e privada).
     */
    function sige_uploads_private_referenced_keys(): ?array {
        global $wpdb;
        if (!isset($wpdb)) return null;
        $keys = [];

        $ta = $wpdb->prefix . 'sige_alunos';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($ta))) === $ta) {
            $rows = $wpdb->get_results("SELECT doc_bi_url, doc_cert_url, doc_vacina_url FROM {$ta} WHERE doc_bi_url LIKE '%sige-private%' OR doc_cert_url LIKE '%sige-private%' OR doc_vacina_url LIKE '%sige-private%'");
            if ($wpdb->last_error) return null;
            foreach ($rows as $r) {
                foreach (['doc_bi_url', 'doc_cert_url', 'doc_vacina_url'] as $c) {
                    $k = sige_uploads_private_ref_key((string)($r->$c ?? ''));
                    if ($k !== '') $keys[$k] = true;
                }
            }
        }

        $tp = $wpdb->prefix . 'sige_professores';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tp))) === $tp) {
            $rows = $wpdb->get_results("SELECT documentos_urls FROM {$tp} WHERE documentos_urls LIKE '%sige-private%'");
            if ($wpdb->last_error) return null;
            foreach ($rows as $r) {
                $docs = json_decode((string)($r->documentos_urls ?? ''), true);
                if (!is_array($docs)) $docs = json_decode(stripslashes((string)($r->documentos_urls ?? '')), true);
                if (!is_array($docs)) continue;
                foreach ($docs as $v) {
                    if (is_string($v)) { $k = sige_uploads_private_ref_key($v); if ($k !== '') $keys[$k] = true; }
                }
            }
        }
        return $keys;
    }
}

if (!function_exists('sige_uploads_private_orphan_grace')) {
    /** Periodo de graca (segundos) antes de um ficheiro sem referencia ser removido. */
    function sige_uploads_private_orphan_grace(): int {
        $g = defined('SIGE_PRIVATE_ORPHAN_GRACE') ? (int) SIGE_PRIVATE_ORPHAN_GRACE : 86400;
        return $g > 0 ? $g : 86400;
    }
}

if (!function_exists('sige_uploads_private_orphan_sweep')) {
    /**
     * Remove ate $limit ficheiros privados sem referencia e mais antigos que o periodo
     * de graca. Idempotente e resumivel. Seguro: opera apenas dentro de sige-private/docs,
     * nunca toca nos ficheiros de proteccao, e aborta se nao conseguir construir o conjunto
     * de referencias (para nunca apagar com base em dados incompletos). Devolve quantos removeu.
     */
    function sige_uploads_private_orphan_sweep(int $limit = 100): int {
        $root = sige_uploads_private_root();
        if ($root === '' || !is_dir($root)) return 0;
        $referenced = sige_uploads_private_referenced_keys();
        if ($referenced === null) return 0; // consulta falhou: nao apagar nada

        $protect = ['.htaccess' => true, 'web.config' => true, 'index.php' => true, 'index.html' => true];
        $cutoff = time() - sige_uploads_private_orphan_grace();
        $removed = 0;

        $escola_dirs = glob($root . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($escola_dirs as $edir) {
            foreach (glob($edir . '/*') ?: [] as $file) {
                if (!is_file($file)) continue;
                $base = basename($file);
                if (isset($protect[$base])) continue;
                $key = sige_uploads_private_ref_key(str_replace('\\', '/', $file));
                if ($key === '' || isset($referenced[$key])) continue; // referenciado: manter
                $mt = @filemtime($file);
                if ($mt === false || $mt > $cutoff) continue; // dentro do periodo de graca
                if (@unlink($file)) {
                    $removed++;
                    if (function_exists('sige_security_log')) {
                        sige_security_log('documento_orfao_removido', 'ficheiro=' . $base);
                    }
                }
                if ($removed >= $limit) return $removed;
            }
        }
        return $removed;
    }
}

if (function_exists('add_action')) {
    add_action('admin_init', 'sige_uploads_maybe_run_private_migration');
}
