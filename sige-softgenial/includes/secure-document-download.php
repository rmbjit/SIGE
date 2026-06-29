<?php
/**
 * SIGE SoftGenial - Secure Document Download
 * Fase 1/P1.2: documentos do aluno e anexos RH passam por endpoints autenticados.
 *
 * Endpoint autenticado:
 *   /wp-admin/admin-post.php?action=sige_secure_document_download&aluno_id=...&field=...&_wpnonce=...
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_secure_document_allowed_fields')) {
    function sige_secure_document_allowed_fields(): array {
        return [
            'doc_bi_url'     => 'BI / Documento',
            'doc_cert_url'   => 'Certidão',
            'doc_vacina_url' => 'Boletim de Vacinas',
        ];
    }
}

if (!function_exists('sige_secure_document_link_ttl')) {
    /** Validade (em segundos) de um link de documento. Ajustavel por constante. */
    function sige_secure_document_link_ttl(): int {
        $ttl = defined('SIGE_SECURE_DOC_LINK_TTL') ? (int) SIGE_SECURE_DOC_LINK_TTL : 3600;
        return $ttl > 0 ? $ttl : 3600;
    }
}

if (!function_exists('sige_secure_document_link_secret')) {
    /** Segredo estavel da instalacao para assinar os links (sem opcao nova). */
    function sige_secure_document_link_secret(): string {
        if (function_exists('wp_salt')) { $s = (string) wp_salt('nonce'); if ($s !== '') return $s; }
        if (defined('NONCE_SALT') && NONCE_SALT) return (string) NONCE_SALT;
        if (defined('AUTH_SALT') && AUTH_SALT) return (string) AUTH_SALT;
        return 'sige-secure-doc-fallback';
    }
}

if (!function_exists('sige_secure_document_link_sig')) {
    /** Assinatura HMAC de um link (escopo|id|campo|expiracao). */
    function sige_secure_document_link_sig(string $scope, int $id, string $field, int $exp): string {
        return hash_hmac('sha256', $scope . '|' . $id . '|' . $field . '|' . $exp, sige_secure_document_link_secret());
    }
}

if (!function_exists('sige_secure_document_link_valid')) {
    /** Verdadeiro se o link nao expirou e a assinatura confere (comparacao constante). */
    function sige_secure_document_link_valid(string $scope, int $id, string $field, $exp, $sig): bool {
        $exp = (int) $exp;
        if ($exp <= 0 || $exp < time()) return false;
        if (!is_string($sig) || $sig === '') return false;
        $expected = sige_secure_document_link_sig($scope, $id, $field, $exp);
        return hash_equals($expected, $sig);
    }
}

if (!function_exists('sige_secure_document_client_ip')) {
    /** IP do cliente, saneado, para o registo de download (sem confiar em proxies). */
    function sige_secure_document_client_ip(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ip = preg_replace('/[^0-9a-fA-F:.]/', '', $ip);
        return substr((string) $ip, 0, 45);
    }
}

if (!function_exists('sige_secure_document_url')) {
    function sige_secure_document_url(int $aluno_id, string $field): string {
        $aluno_id = absint($aluno_id);
        $field = sanitize_key($field);
        if ($aluno_id <= 0 || !array_key_exists($field, sige_secure_document_allowed_fields())) return '';
        $exp = time() + sige_secure_document_link_ttl();
        return add_query_arg([
            'action'    => 'sige_secure_document_download',
            'aluno_id'  => $aluno_id,
            'field'     => $field,
            'exp'       => $exp,
            'sig'       => sige_secure_document_link_sig('aluno', $aluno_id, $field, $exp),
            '_wpnonce'  => wp_create_nonce('sige_secure_document_' . $aluno_id . '_' . $field),
        ], admin_url('admin-post.php'));
    }
}

if (!function_exists('sige_secure_document_user_can_access_student')) {
    function sige_secure_document_user_can_access_student(int $aluno_id): bool {
        if (!is_user_logged_in() || $aluno_id <= 0) return false;

        $staff_ok = function_exists('sige_user_can_any_secure')
            ? sige_user_can_any_secure(['documentos.ver','documentos.emitir','documentos.emitir_finais'], ['sige_admin_ti','sige_director','sige_secretario','sige_secretaria_geral','sige_pedagogico','sige_assistente','sige_recepcao'])
            : (current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_pedagogico'));
        if ($staff_ok) return true;

        if (function_exists('sige_get_aluno_id_do_utilizador') && sige_get_aluno_id_do_utilizador() === $aluno_id) {
            return function_exists('sige_can') ? (sige_can('portal.ver_documentos') || sige_can('portal.baixar_documentos') || sige_can('portal.ver')) : true;
        }

        return false;
    }
}

if (!function_exists('sige_secure_document_resolve_local_path')) {
    function sige_secure_document_resolve_local_path(string $url): string {
        $url = trim($url);
        if ($url === '') return '';

        // Aceita apenas ficheiros locais da instalação WordPress. URLs externas não são servidas por este endpoint.
        $uploads = wp_get_upload_dir();
        $baseurl = isset($uploads['baseurl']) ? rtrim((string)$uploads['baseurl'], '/') : '';
        $basedir = isset($uploads['basedir']) ? rtrim((string)$uploads['basedir'], DIRECTORY_SEPARATOR) : '';
        if ($baseurl === '' || $basedir === '') return '';

        $normalized_url = preg_replace('#^https?:#i', '', $url);
        $normalized_base = preg_replace('#^https?:#i', '', $baseurl);
        if (strpos($normalized_url, $normalized_base . '/') !== 0) return '';

        $relative = substr($normalized_url, strlen($normalized_base));
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], rawurldecode($relative)), '/');
        $path = $basedir . DIRECTORY_SEPARATOR . $relative;
        $real = realpath($path);
        $real_base = realpath($basedir);
        if (!$real || !$real_base || strpos($real, $real_base) !== 0 || !is_file($real) || !is_readable($real)) return '';
        return $real;
    }
}

if (!function_exists('sige_secure_document_download_handler')) {
    function sige_secure_document_download_handler(): void {
        if (!is_user_logged_in()) {
            wp_die('Inicie sessão para aceder a este documento.', 'SIGE - Documento protegido', ['response' => 401]);
        }

        $aluno_id = isset($_GET['aluno_id']) ? absint($_GET['aluno_id']) : 0;
        $field = isset($_GET['field']) ? sanitize_key((string)wp_unslash($_GET['field'])) : '';
        if ($aluno_id <= 0 || !array_key_exists($field, sige_secure_document_allowed_fields())) {
            wp_die('Documento inválido.', 'SIGE - Documento protegido', ['response' => 400]);
        }

        check_admin_referer('sige_secure_document_' . $aluno_id . '_' . $field);

        // Link com expiracao: alem do nonce, o link tem uma validade curta e assinada.
        $exp = isset($_GET['exp']) ? (int) $_GET['exp'] : 0;
        $sig = isset($_GET['sig']) ? (string) wp_unslash($_GET['sig']) : '';
        if (!sige_secure_document_link_valid('aluno', $aluno_id, $field, $exp, $sig)) {
            if (function_exists('sige_security_log')) sige_security_log('document_link_expirado', 'aluno_id=' . $aluno_id . '; field=' . $field . '; ip=' . sige_secure_document_client_ip(), $aluno_id);
            wp_die('Este link expirou ou é inválido. Reabra o documento a partir da ficha do aluno.', 'SIGE - Documento protegido', ['response' => 403]);
        }

        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($eid <= 0) {
            wp_die('Contexto de escola inválido.', 'SIGE - Segurança', ['response' => 403]);
        }

        if (!sige_secure_document_user_can_access_student($aluno_id)) {
            if (function_exists('sige_security_log')) sige_security_log('document_access_denied', 'aluno_id=' . $aluno_id . '; field=' . $field, $aluno_id);
            wp_die('Sem permissão para aceder a este documento.', 'SIGE - Documento protegido', ['response' => 403]);
        }

        $t = $wpdb->prefix . 'sige_alunos';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t)));
        if ($exists !== $t) wp_die('Tabela de alunos indisponível.', 'SIGE - Documento protegido', ['response' => 500]);

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, escola_id, {$field} AS doc_url FROM {$t} WHERE id=%d AND escola_id=%d LIMIT 1", $aluno_id, $eid));
        if (!$row || empty($row->doc_url)) {
            wp_die('Documento não encontrado.', 'SIGE - Documento protegido', ['response' => 404]);
        }

        $path = sige_secure_document_resolve_local_path((string)$row->doc_url);
        if ($path === '') {
            if (function_exists('sige_security_log')) sige_security_log('document_nonlocal_blocked', 'aluno_id=' . $aluno_id . '; field=' . $field, $aluno_id);
            wp_die('Documento não pode ser servido com segurança. Recarregue o ficheiro no arquivo da escola.', 'SIGE - Documento protegido', ['response' => 403]);
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('documento_baixado_seguro', [
                'aluno_id' => $aluno_id,
                'field' => $field,
                'label' => sige_secure_document_allowed_fields()[$field] ?? $field,
                'file' => basename($path),
                'user_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
                'ip' => sige_secure_document_client_ip(),
            ], 'documentos');
        }

        while (ob_get_level()) { ob_end_clean(); }
        $mime = function_exists('wp_check_filetype') ? (wp_check_filetype($path)['type'] ?? '') : '';
        if ($mime === '') $mime = 'application/octet-stream';
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Content-Type: ' . $mime, true);
        header('Content-Disposition: inline; filename="' . sanitize_file_name(basename($path)) . '"', true);
        header('Content-Length: ' . filesize($path), true);
        readfile($path);
        exit;
    }
}


if (!function_exists('sige_secure_staff_document_allowed_fields')) {
    function sige_secure_staff_document_allowed_fields(): array {
        return [
            'doc_bi'   => 'BI / Documento RH',
            'doc_cv'   => 'CV / Currículo',
            'doc_cert' => 'Certificados',
        ];
    }
}

if (!function_exists('sige_secure_staff_document_url')) {
    function sige_secure_staff_document_url(int $user_id, string $field): string {
        $user_id = absint($user_id);
        $field = sanitize_key($field);
        if ($user_id <= 0 || !array_key_exists($field, sige_secure_staff_document_allowed_fields())) return '';
        $exp = time() + sige_secure_document_link_ttl();
        return add_query_arg([
            'action'    => 'sige_secure_staff_document_download',
            'user_id'   => $user_id,
            'field'     => $field,
            'exp'       => $exp,
            'sig'       => sige_secure_document_link_sig('staff', $user_id, $field, $exp),
            '_wpnonce'  => wp_create_nonce('sige_secure_staff_document_' . $user_id . '_' . $field),
        ], admin_url('admin-post.php'));
    }
}

if (!function_exists('sige_secure_staff_user_belongs_to_current_school')) {
    function sige_secure_staff_user_belongs_to_current_school(int $user_id): bool {
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($user_id <= 0 || $eid <= 0) return false;
        if (function_exists('sige_phase1_scope_guard_wp_user_belongs_to_school')) {
            return sige_phase1_scope_guard_wp_user_belongs_to_school($user_id, $eid);
        }
        if (function_exists('sige_ajax_equipe_user_belongs_to_school')) {
            return sige_ajax_equipe_user_belongs_to_school($user_id, $eid);
        }
        $meta_school = (int)get_user_meta($user_id, 'sige_escola_id', true);
        return $meta_school > 0 && $meta_school === $eid;
    }
}

if (!function_exists('sige_secure_staff_document_user_can_access')) {
    function sige_secure_staff_document_user_can_access(int $target_user_id): bool {
        if (!is_user_logged_in() || $target_user_id <= 0) return false;
        if (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user()) return true;
        if (!sige_secure_staff_user_belongs_to_current_school($target_user_id)) return false;
        return function_exists('sige_user_can_any_secure')
            ? sige_user_can_any_secure(['rh.equipe_gerir','rh.equipe_ver','usuarios.ver'], ['sige_director','sige_gestor_rh','sige_admin_ti'])
            : (current_user_can('sige_director') || current_user_can('sige_gestor_rh') || current_user_can('sige_admin_ti'));
    }
}

if (!function_exists('sige_secure_staff_document_row')) {
    function sige_secure_staff_document_row(int $user_id) {
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($user_id <= 0 || $eid <= 0) return null;
        $t = $wpdb->prefix . 'sige_professores';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) return null;
        $prof_id = (int)get_user_meta($user_id, 'sige_professor_id', true);
        if ($prof_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d AND escola_id=%d LIMIT 1", $prof_id, $eid));
            if ($row) return $row;
        }
        $user = get_user_by('ID', $user_id);
        if (!$user || empty($user->user_email)) return null;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE email=%s AND escola_id=%d ORDER BY id DESC LIMIT 1", $user->user_email, $eid));
    }
}

if (!function_exists('sige_secure_staff_document_download_handler')) {
    function sige_secure_staff_document_download_handler(): void {
        if (!is_user_logged_in()) {
            wp_die('Inicie sessão para aceder a este documento.', 'SIGE - Documento RH protegido', ['response' => 401]);
        }

        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        $field = isset($_GET['field']) ? sanitize_key((string)wp_unslash($_GET['field'])) : '';
        if ($user_id <= 0 || !array_key_exists($field, sige_secure_staff_document_allowed_fields())) {
            wp_die('Documento inválido.', 'SIGE - Documento RH protegido', ['response' => 400]);
        }

        check_admin_referer('sige_secure_staff_document_' . $user_id . '_' . $field);

        // Link com expiracao: alem do nonce, o link tem uma validade curta e assinada.
        $exp = isset($_GET['exp']) ? (int) $_GET['exp'] : 0;
        $sig = isset($_GET['sig']) ? (string) wp_unslash($_GET['sig']) : '';
        if (!sige_secure_document_link_valid('staff', $user_id, $field, $exp, $sig)) {
            if (function_exists('sige_security_log')) sige_security_log('staff_document_link_expirado', 'user_id=' . $user_id . '; field=' . $field . '; ip=' . sige_secure_document_client_ip(), $user_id);
            wp_die('Este link expirou ou é inválido. Reabra o documento a partir da ficha.', 'SIGE - Documento protegido', ['response' => 403]);
        }

        if (!sige_secure_staff_document_user_can_access($user_id)) {
            if (function_exists('sige_security_log')) sige_security_log('staff_document_access_denied', 'user_id=' . $user_id . '; field=' . $field, $user_id);
            wp_die('Sem permissão para aceder a este documento RH.', 'SIGE - Documento RH protegido', ['response' => 403]);
        }

        $row = sige_secure_staff_document_row($user_id);
        if (!$row || empty($row->documentos_urls)) {
            wp_die('Documento não encontrado.', 'SIGE - Documento RH protegido', ['response' => 404]);
        }
        $docs = json_decode((string)$row->documentos_urls, true);
        if (!is_array($docs)) $docs = json_decode(stripslashes((string)$row->documentos_urls), true);
        if (!is_array($docs) || empty($docs[$field])) {
            wp_die('Documento não encontrado.', 'SIGE - Documento RH protegido', ['response' => 404]);
        }

        $path = sige_secure_document_resolve_local_path((string)$docs[$field]);
        if ($path === '') {
            if (function_exists('sige_security_log')) sige_security_log('staff_document_nonlocal_blocked', 'user_id=' . $user_id . '; field=' . $field, $user_id);
            wp_die('Documento RH não pode ser servido com segurança. Recarregue o ficheiro no arquivo da escola.', 'SIGE - Documento RH protegido', ['response' => 403]);
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('documento_rh_baixado_seguro', [
                'staff_user_id' => $user_id,
                'professor_id' => isset($row->id) ? (int)$row->id : 0,
                'field' => $field,
                'label' => sige_secure_staff_document_allowed_fields()[$field] ?? $field,
                'file' => basename($path),
                'actor_user_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
                'ip' => sige_secure_document_client_ip(),
            ], 'rh');
        }

        while (ob_get_level()) { ob_end_clean(); }
        $mime = function_exists('wp_check_filetype') ? (wp_check_filetype($path)['type'] ?? '') : '';
        if ($mime === '') $mime = 'application/octet-stream';
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Content-Type: ' . $mime, true);
        header('Content-Disposition: inline; filename="' . sanitize_file_name(basename($path)) . '"', true);
        header('Content-Length: ' . filesize($path), true);
        readfile($path);
        exit;
    }
}
add_action('admin_post_sige_secure_staff_document_download', 'sige_secure_staff_document_download_handler');

add_action('admin_post_sige_secure_document_download', 'sige_secure_document_download_handler');
