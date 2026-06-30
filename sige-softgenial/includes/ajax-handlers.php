<?php
/**
 * SIGE SoftGenial - AJAX Handlers
 * Ficheiro: includes/ajax-handlers.php
 * 
 * Handlers AJAX para operações diversas: horários, alunos, staff.
 * 
 * @since 10.0
 */

if (!defined('ABSPATH')) exit;


// ============================================================================
// RH / EQUIPA - SECURITY HARDENING v12.11.9
// ============================================================================
if (!function_exists('sige_ajax_equipe_can_manage')) {
    /**
     * Autorização única para acções mutáveis do módulo Equipa.
     * A matriz SIGE é a fonte primária; capabilities antigas só servem como fallback
     * quando ainda não existe Perfil SIGE activo, tal como no page guard.
     */
    function sige_ajax_equipe_can_manage(): bool {
        if (function_exists('sige_page_guard_allows')) {
            return sige_page_guard_allows(['rh.equipe_gerir'], ['sige_director','sige_gestor_rh','sige_admin_ti']);
        }
        if (function_exists('sige_can') && sige_can('rh.equipe_gerir')) {
            return true;
        }
        // Fallback extremo: só administrador WordPress real, nunca role SIGE legada com manage_options herdado.
        return sige_ajax_equipe_is_real_wp_admin();
    }
}

if (!function_exists('sige_ajax_equipe_is_real_wp_admin')) {
    /** Apenas administradores WordPress reais podem atribuir perfil Admin TI. */
    function sige_ajax_equipe_is_real_wp_admin(): bool {
        $u = wp_get_current_user();
        return (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && in_array('administrator', (array)$u->roles, true);
    }
}

if (!function_exists('sige_ajax_equipe_escola_id')) {
    function sige_ajax_equipe_escola_id(): int {
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        return $eid > 0 ? $eid : 1;
    }
}

if (!function_exists('sige_ajax_equipe_professor_for_user')) {
    function sige_ajax_equipe_professor_for_user(int $user_id, int $escola_id = 0) {
        global $wpdb;
        $escola_id = $escola_id > 0 ? $escola_id : sige_ajax_equipe_escola_id();
        if ($user_id <= 0 || $escola_id <= 0) return null;

        $tabela = $wpdb->prefix . 'sige_professores';
        $prof_id = (int)get_user_meta($user_id, 'sige_professor_id', true);
        if ($prof_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$tabela} WHERE id = %d AND escola_id = %d LIMIT 1",
                $prof_id, $escola_id
            ));
            if ($row) return $row;
        }

        $user = get_user_by('ID', $user_id);
        if (!$user || empty($user->user_email)) return null;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabela} WHERE email = %s AND escola_id = %d ORDER BY id DESC LIMIT 1",
            $user->user_email, $escola_id
        ));
    }
}

if (!function_exists('sige_ajax_equipe_user_belongs_to_school')) {
    function sige_ajax_equipe_user_belongs_to_school(int $user_id, int $escola_id = 0): bool {
        $escola_id = $escola_id > 0 ? $escola_id : sige_ajax_equipe_escola_id();
        if ($user_id <= 0 || $escola_id <= 0) return false;

        // v12.11.9.8 - colaborador removido deixa de ser alvo válido de operações RH.
        // O histórico permanece preservado, mas a conta não deve voltar a ser editada/resetada
        // por fluxos normais do módulo.
        if ((string)get_user_meta($user_id, 'sige_staff_removed_at', true) !== '') {
            return false;
        }

        $meta_school = (int)get_user_meta($user_id, 'sige_escola_id', true);
        if ($meta_school > 0 && $meta_school === $escola_id) return true;

        return (bool)sige_ajax_equipe_professor_for_user($user_id, $escola_id);
    }
}

if (!function_exists('sige_ajax_equipe_allowed_roles')) {
    function sige_ajax_equipe_allowed_roles(): array {
        $roles = [
            'sige_director', 'sige_pedagogico',
            'sige_secretaria_geral', 'sige_secretario', 'sige_assistente',
            'sige_financeiro', 'sige_professor', 'sige_educador',
            'sige_gestor_rh', 'sige_motorista', 'sige_limpeza', 'sige_recepcao', 'sige_guarda'
        ];
        if (sige_ajax_equipe_is_real_wp_admin()) {
            $roles[] = 'sige_admin_ti';
        }
        return $roles;
    }
}


if (!function_exists('sige_ajax_equipe_apply_access_role')) {
    /**
     * v12.14.2 - atribuição de papel/perfil em ponto único, com guarda de
     * integridade antes de mexer no WordPress role. Evita downgrades silenciosos
     * e impede que o módulo RH contorne a política central de utilizadores.
     */
    function sige_ajax_equipe_apply_access_role(int $staff_id, string $wp_role, int $escola_id, string $old_wp_role = ''): void {
        $wp_role = sanitize_key($wp_role);
        if ($staff_id <= 0 || $wp_role === '') {
            sige_ajax_equipe_send_error('Perfil de acesso inválido.');
        }
        $sige_role_slug = '';
        if (function_exists('sige_permissions_wp_role_to_sige_role')) {
            $sige_role_slug = sige_permissions_wp_role_to_sige_role($wp_role);
        }
        if ($sige_role_slug !== ''
            && function_exists('sige_user_integrity_can_change_sige_role')
            && !sige_user_integrity_can_change_sige_role($staff_id, $sige_role_slug, $escola_id)) {
            sige_ajax_equipe_send_error('Alteração bloqueada pela guarda de integridade de utilizadores. Apenas administrador WordPress real pode atribuir ou despromover perfis privilegiados.');
        }

        $user = get_user_by('ID', $staff_id);
        if (!($user instanceof WP_User)) {
            sige_ajax_equipe_send_error('Utilizador não encontrado.');
        }
        $user->set_role($wp_role);

        if ($sige_role_slug !== '' && function_exists('sige_permissions_sync_user_role')) {
            $sync_ok = (bool) sige_permissions_sync_user_role($staff_id, $sige_role_slug, $escola_id, false);
            if (!$sync_ok) {
                if ($old_wp_role !== '') {
                    $rollback = get_user_by('ID', $staff_id);
                    if ($rollback instanceof WP_User) $rollback->set_role($old_wp_role);
                }
                sige_ajax_equipe_send_error('Alteração bloqueada pela guarda de integridade de utilizadores. Nenhum perfil foi aplicado.');
            }
        }
    }
}

if (!function_exists('sige_ajax_equipe_role_label')) {
    function sige_ajax_equipe_role_label(string $role_slug): string {
        $labels = [
            'sige_professor' => 'Professor',
            'sige_admin_ti' => 'Admin TI',
            'sige_director' => 'Direcção',
            'sige_financeiro' => 'Tesouraria',
            'sige_secretaria_geral' => 'Secretaria',
            'sige_assistente' => 'Assistente',
            'sige_educador' => 'Educador',
            'sige_motorista' => 'Motorista',
            'sige_limpeza' => 'Limpeza',
            'sige_secretario' => 'Secretário',
            'sige_gestor_rh' => 'Gestor RH',
            'sige_pedagogico' => 'Dir. Pedagógico',
            'sige_recepcao' => 'Recepção',
            'sige_guarda' => 'Guarda / Portaria',
        ];
        return $labels[$role_slug] ?? ucfirst(str_replace('sige_', '', $role_slug));
    }
}

if (!function_exists('sige_ajax_equipe_sanitize_document_url')) {
    function sige_ajax_equipe_sanitize_document_url($url): string {
        $url = esc_url_raw(wp_unslash((string)$url));
        if ($url === '') return '';

        $allowed_ext = ['pdf','jpg','jpeg','png','webp','doc','docx'];
        $path = (string)parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed_ext, true)) {
            return '';
        }

        // Preferir ficheiros da biblioteca WordPress/instalação actual.
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);
        if ($url_host && $site_host && strtolower($url_host) !== strtolower($site_host)) {
            return '';
        }

        $attachment_id = attachment_url_to_postid($url);
        // v12.11.9.8 - documentos RH devem ser ficheiros controlados da biblioteca/uploads
        // da própria instância. Isto evita associar URLs arbitrárias que podem expor ou
        // redireccionar documentos sensíveis fora do controlo da escola.
        if ($attachment_id <= 0 && strpos($path, '/wp-content/uploads/') === false) {
            return '';
        }
        if ($attachment_id > 0) {
            $mime = (string)get_post_mime_type($attachment_id);
            $allowed_mimes = [
                'application/pdf',
                'image/jpeg', 'image/png', 'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            if ($mime && !in_array($mime, $allowed_mimes, true)) {
                return '';
            }
        }

        return $url;
    }
}

if (!function_exists('sige_ajax_equipe_normalize_docs')) {
    function sige_ajax_equipe_normalize_docs(array $docs): array {
        return [
            'doc_bi'   => sige_ajax_equipe_sanitize_document_url($docs['doc_bi'] ?? ''),
            'doc_cv'   => sige_ajax_equipe_sanitize_document_url($docs['doc_cv'] ?? ''),
            'doc_cert' => sige_ajax_equipe_sanitize_document_url($docs['doc_cert'] ?? ''),
        ];
    }
}

if (!function_exists('sige_ajax_equipe_decode_json_array')) {
    function sige_ajax_equipe_decode_json_array($value, array $fallback = []): array {
        if (empty($value)) return $fallback;
        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(stripslashes((string)$value), true);
        }
        return is_array($decoded) ? array_merge($fallback, $decoded) : $fallback;
    }
}

if (!function_exists('sige_ajax_equipe_normalize_decimal')) {
    /**
     * Normaliza valores monetários vindos do formulário RH.
     * Aceita formatos comuns em Moçambique/pt: 40000, 40.000, 40.000,50, 40000.50.
     */
    function sige_ajax_equipe_normalize_decimal($value): float {
        $raw = trim((string)wp_unslash($value));
        if ($raw === '') return 0.0;
        $raw = preg_replace('/[\s\x{00A0}]+/u', '', $raw);
        $raw = preg_replace('/[^0-9,\.\-]/', '', $raw);
        if ($raw === '' || $raw === '-' || $raw === ',' || $raw === '.') return 0.0;

        $comma = strrpos($raw, ',');
        $dot   = strrpos($raw, '.');
        if ($comma !== false && $dot !== false) {
            // O último separador é tratado como decimal; o outro como milhar.
            if ($comma > $dot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($comma !== false) {
            $parts = explode(',', $raw);
            $last = end($parts);
            $raw = (strlen($last) <= 2) ? str_replace(',', '.', $raw) : str_replace(',', '', $raw);
        } elseif ($dot !== false) {
            $parts = explode('.', $raw);
            $last = end($parts);
            if (count($parts) > 1 && strlen($last) === 3) {
                $raw = str_replace('.', '', $raw);
            }
        }
        return max(0.0, (float)$raw);
    }
}

if (!function_exists('sige_ajax_equipe_sanitize_digits')) {
    function sige_ajax_equipe_sanitize_digits($value): string {
        return preg_replace('/\D+/', '', (string)wp_unslash($value));
    }
}

if (!function_exists('sige_ajax_equipe_date_ymd_or_empty')) {
    function sige_ajax_equipe_date_ymd_or_empty($value): string {
        $value = trim((string)wp_unslash($value));
        if ($value === '') return '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
    }
}


if (!function_exists('sige_ajax_equipe_limited_text')) {
    /**
     * Texto normalizado e limitado ao tamanho real das colunas RH.
     * Evita truncamentos silenciosos em MySQL e payloads excessivos vindos do browser.
     */
    function sige_ajax_equipe_limited_text($value, int $max = 255): string {
        $text = sanitize_text_field(wp_unslash($value));
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if ($max > 0 && function_exists('mb_substr')) {
            $text = mb_substr($text, 0, $max, 'UTF-8');
        } elseif ($max > 0) {
            $text = substr($text, 0, $max);
        }
        return $text;
    }
}

if (!function_exists('sige_ajax_equipe_sanitize_phone')) {
    function sige_ajax_equipe_sanitize_phone($value, int $max = 20): string {
        $raw = trim((string)wp_unslash($value));
        $raw = preg_replace('/[^0-9+ ]+/', '', $raw);
        $raw = preg_replace('/\s+/u', ' ', $raw);
        return sige_ajax_equipe_limited_text($raw, $max);
    }
}

if (!function_exists('sige_ajax_equipe_sanitize_bank_digits')) {
    function sige_ajax_equipe_sanitize_bank_digits($value, int $max = 34): string {
        $digits = sige_ajax_equipe_sanitize_digits($value);
        return substr($digits, 0, $max);
    }
}

if (!function_exists('sige_ajax_equipe_sanitize_image_url')) {
    function sige_ajax_equipe_sanitize_image_url($url): string {
        $url = esc_url_raw(wp_unslash((string)$url));
        if ($url === '') return '';
        $path = (string)parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','svg'], true)) {
            return '';
        }
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host  = wp_parse_url($url, PHP_URL_HOST);
        if ($url_host && $site_host && strtolower($url_host) !== strtolower($site_host)) {
            return '';
        }
        return $url;
    }
}

if (!function_exists('sige_ajax_equipe_soft_revoke_access')) {
    /**
     * Revoga o acesso sem apagar o WP user. Preserva histórico académico/RH/auditoria.
     * Usado por Remover colaborador; diferente de Desactivar, que mantém a linha visível.
     */
    function sige_ajax_equipe_soft_revoke_access(int $user_id, int $escola_id, string $reason = 'removed'): void {
        if ($user_id <= 0) return;
        $user = get_user_by('ID', $user_id);
        if ($user instanceof WP_User) {
            update_user_meta($user_id, 'sige_staff_previous_roles', array_values((array)$user->roles));
            update_user_meta($user_id, 'sige_staff_previous_caps', get_user_meta($user_id, $GLOBALS['wpdb']->prefix . 'capabilities', true));
            // Remove roles e capabilities operacionais para que o utilizador desapareça das listagens por role
            // e não consiga aceder por capabilities directas antigas.
            if (method_exists($user, 'remove_all_caps')) {
                $user->remove_all_caps();
            } else {
                $user->set_role('');
            }
        }
        update_user_meta($user_id, 'sige_staff_status_ativo', 0);
        update_user_meta($user_id, 'sige_status_ativo', 0);
        update_user_meta($user_id, 'sige_staff_removed_at', current_time('mysql'));
        update_user_meta($user_id, 'sige_staff_removed_by', get_current_user_id());
        update_user_meta($user_id, 'sige_staff_removed_reason', sanitize_key($reason));
        update_user_meta($user_id, 'sige_staff_removed_escola_id', $escola_id);
        clean_user_cache($user_id);
        if (class_exists('WP_Session_Tokens')) {
            WP_Session_Tokens::get_instance($user_id)->destroy_all();
        }
    }
}

if (!function_exists('sige_ajax_equipe_begin_buffer')) {
    /**
     * Inicia um buffer próprio para os endpoints RH. Em produção, qualquer
     * notice/warning ou espaço acidental antes do JSON quebra o jQuery e gera
     * erro de comunicação na óptica do utilizador.
     */
    function sige_ajax_equipe_begin_buffer(): void {
        if (!isset($GLOBALS['sige_ajax_equipe_ob_base_level'])) {
            $GLOBALS['sige_ajax_equipe_ob_base_level'] = ob_get_level();
        }
        ob_start();
    }
}

if (!function_exists('sige_ajax_equipe_clean_buffer')) {
    function sige_ajax_equipe_clean_buffer(): void {
        $base = isset($GLOBALS['sige_ajax_equipe_ob_base_level']) ? (int)$GLOBALS['sige_ajax_equipe_ob_base_level'] : ob_get_level();
        while (ob_get_level() > $base) {
            @ob_end_clean();
        }
    }
}

if (!function_exists('sige_ajax_equipe_send_error')) {
    function sige_ajax_equipe_send_error($data = null, $status_code = null, int $flags = 0): void {
        sige_ajax_equipe_clean_buffer();
        wp_send_json_error($data, $status_code, $flags);
    }
}

if (!function_exists('sige_ajax_equipe_send_success')) {
    function sige_ajax_equipe_send_success($data = null, $status_code = null, int $flags = 0): void {
        sige_ajax_equipe_clean_buffer();
        wp_send_json_success($data, $status_code, $flags);
    }
}


if (!function_exists('sige_ajax_equipe_audit')) {
    /**
     * Auditoria segura do módulo Equipa.
     * Nunca deve quebrar o fluxo do utilizador: a função global sige_audit_log()
     * recebe (acao, detalhes, modulo, user_id). Chamadas com ordem errada causavam
     * TypeError/HTTP 500 nos endpoints RH.
     */
    function sige_ajax_equipe_audit(string $acao, array $detalhes = []): void {
        if (!function_exists('sige_audit_log')) {
            return;
        }
        try {
            sige_audit_log($acao, $detalhes, 'professores', get_current_user_id());
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[SIGE Equipa Audit] ' . $acao . ' falhou: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('sige_ajax_equipe_deprecated_endpoint')) {
    function sige_ajax_equipe_deprecated_endpoint() {
        sige_ajax_equipe_begin_buffer();
        sige_ajax_equipe_send_error('Endpoint legado desactivado por segurança. Use o fluxo actualizado de Equipa e Professores.');
    }
}

// Neutralizar handlers legados que eram carregados em db-handler.php antes dos handlers PRO.
if (function_exists('sige_ajax_criar_usuario_staff')) {
    remove_action('wp_ajax_sige_criar_usuario_staff', 'sige_ajax_criar_usuario_staff');
}
if (function_exists('sige_ajax_editar_usuario_staff')) {
    remove_action('wp_ajax_sige_editar_usuario_staff', 'sige_ajax_editar_usuario_staff');
}
if (function_exists('sige_ajax_remover_usuario_staff')) {
    remove_action('wp_ajax_sige_remover_usuario_staff', 'sige_ajax_remover_usuario_staff');
}
if (function_exists('sige_ajax_resetar_senha')) {
    remove_action('wp_ajax_sige_resetar_senha', 'sige_ajax_resetar_senha');
}
add_action('wp_ajax_sige_criar_usuario_staff', 'sige_ajax_equipe_deprecated_endpoint');
add_action('wp_ajax_sige_editar_usuario_staff', 'sige_ajax_equipe_deprecated_endpoint');


// ============================================================================
// HORÁRIO DE TURMA: GUARDAR
// ============================================================================
add_action('wp_ajax_sige_salvar_horario_turma', 'sige_salvar_horario_turma_cb');
if (!function_exists('sige_salvar_horario_turma_cb')) {
    function sige_salvar_horario_turma_cb() {
        if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_salvar_horario_turma_cb')) { wp_send_json_error('Contexto de escola invalido.'); }
        if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_turmas_action')) {
            wp_send_json_error('Sessão expirada. Recarregue a página e tente novamente.');
        }
        
        if (!current_user_can('sige_director') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_assistente') 
            && !current_user_can('sige_secretario')) {
            wp_send_json_error('Sem permissão.');
        }
        
        $turma_id    = (int)($_POST['turma_id'] ?? 0);
        $horario_raw = wp_unslash($_POST['horario_json'] ?? '');
        
        if (!$turma_id) {
            wp_send_json_error('turma_id inválido.');
        }
        
        // Validar JSON
        $decoded = json_decode($horario_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('JSON inválido: ' . json_last_error_msg());
        }
        
        global $wpdb;
        $tT = $wpdb->prefix . 'sige_turmas';
        
        // Coluna horario_json - gerida por class-sige-migration.php
        
        $res = $wpdb->update(
            $tT,
            ['horario_json' => wp_json_encode((object)$decoded)],
            ['id' => $turma_id, 'escola_id' => sige_get_escola_id()],
            ['%s'],
            ['%d', '%d']
        );
        
        if ($res === false) {
            wp_send_json_error('Erro ao guardar: ' . $wpdb->last_error);
        }
        
        wp_send_json_success(['saved' => true, 'turma_id' => $turma_id, 'rows' => $res]);
    }
}

// ============================================================================
// HORÁRIO DE TURMA: CARREGAR
// ============================================================================
add_action('wp_ajax_sige_carregar_horario_turma', 'sige_carregar_horario_turma_cb');
if (!function_exists('sige_carregar_horario_turma_cb')) {
    function sige_carregar_horario_turma_cb() {
        if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_turmas_action')) {
            wp_send_json_error('Sessão expirada. Recarregue a página e tente novamente.');
        }
        
        if (!current_user_can('sige_director') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_assistente')
            && !current_user_can('sige_professor') && !current_user_can('sige_secretario')) {
            wp_send_json_error('Sem permissão.');
        }
        
        $turma_id = (int)($_POST['turma_id'] ?? 0);
        if (!$turma_id) {
            wp_send_json_error('turma_id inválido.');
        }
        
        global $wpdb;
        $tT = $wpdb->prefix . 'sige_turmas';
        
        $turma = $wpdb->get_row($wpdb->prepare(
            "SELECT horario_json FROM {$tT} WHERE id = %d AND escola_id = %d LIMIT 1",
            $turma_id, sige_get_escola_id()
        ));
        
        if (!$turma) {
            wp_send_json_error('Turma não encontrada.');
        }
        
        $horario = [];
        $periodos = 6;
        
        if (!empty($turma->horario_json)) {
            $decoded = json_decode($turma->horario_json, true);
            if (is_array($decoded)) {
                $horario  = $decoded;
                $periodos = isset($decoded['_periodos']) ? (int)$decoded['_periodos'] : 6;
            }
        }
        
        wp_send_json_success([
            'horario'  => $horario,
            'periodos' => $periodos,
        ]);
    }
}

// ============================================================================
// REMOVER/ARQUIVAR ALUNO
// ============================================================================
// v12.12.7: neutraliza callback legado em db-handler.php que fazia hard delete.
if (function_exists('sige_ajax_remover_aluno')) {
    remove_action('wp_ajax_sige_remover_aluno', 'sige_ajax_remover_aluno');
}
add_action('wp_ajax_sige_remover_aluno', function () {
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_alunos_action')) {
        wp_send_json_error('Sessão expirada. Recarregue a página e tente novamente.');
    }
    
    $sige_can_delete_aluno = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));
    if (!$sige_can_delete_aluno && function_exists('sige_can') && sige_can('alunos.apagar')) {
        $sige_can_delete_aluno = true;
    }
    if (!$sige_can_delete_aluno && (current_user_can('sige_assistente') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_director'))) {
        $sige_can_delete_aluno = true;
    }
    if (!$sige_can_delete_aluno) {
        wp_send_json_error('Sem permissão para remover alunos.');
    }
    
    global $wpdb;
    $id = (int)($_POST['id'] ?? 0);
    
    if (!$id) {
        wp_send_json_error('ID inválido.');
    }
    
    // v12.12.7: operação destrutiva convertida em arquivamento reversível.
    $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    if ($escola_id <= 0) {
        wp_send_json_error('Escola não identificada. Operação bloqueada.');
    }
    $motivo = isset($_POST['motivo']) ? sanitize_text_field(wp_unslash((string)$_POST['motivo'])) : '';
    if ($motivo === '') $motivo = 'Arquivado pela secretaria.';
    $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$wpdb->prefix}sige_alunos WHERE id = %d AND escola_id = %d LIMIT 1", $id, $escola_id));
    if ($exists <= 0) {
        wp_send_json_error('Aluno não encontrado nesta escola.');
    }
    $res = $wpdb->update(
        $wpdb->prefix . 'sige_alunos',
        [
            'status' => 'arquivado',
            'observacoes' => $wpdb->get_var($wpdb->prepare("SELECT CONCAT(COALESCE(observacoes,''), %s) FROM {$wpdb->prefix}sige_alunos WHERE id = %d AND escola_id = %d", "
[ARQUIVADO] " . current_time('mysql') . ' - ' . $motivo, $id, $escola_id)),
        ],
        ['id' => $id, 'escola_id' => $escola_id],
        ['%s','%s'],
        ['%d','%d']
    );
    if ($res === false) {
        wp_send_json_error('Erro ao arquivar aluno: ' . $wpdb->last_error);
    }
    if (function_exists('sige_audit_log')) {
        sige_audit_log('aluno_arquivado', [
            'aluno_id' => $id,
            'motivo' => $motivo,
            'resultado' => 'Arquivamento reversível; histórico financeiro/académico preservado.'
        ], 'alunos');
    }
    wp_send_json_success('Aluno arquivado com sucesso. O histórico foi preservado.');
});

// ============================================================================
// TOGGLE STATUS STAFF - PRO HARDENED v12.11.9
// ============================================================================
add_action('wp_ajax_sige_toggle_status_staff', function () {
    sige_ajax_equipe_begin_buffer();
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_equipe_action')) {
        sige_ajax_equipe_send_error('Sessão expirada. Recarregue a página e tente novamente.');
    }

    if (!sige_ajax_equipe_can_manage()) {
        sige_ajax_equipe_send_error('Sem permissão para gerir equipa.');
    }

    global $wpdb;
    $user_id      = absint($_POST['user_id'] ?? 0);
    $email        = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $status_ativo = (int)($_POST['status_ativo'] ?? 1) === 1 ? 1 : 0;
    $escola_id    = sige_ajax_equipe_escola_id();

    if (!$user_id || !$email) {
        sige_ajax_equipe_send_error('Dados inválidos.');
    }

    if ($user_id === get_current_user_id() && $status_ativo === 0) {
        sige_ajax_equipe_send_error('Não pode desactivar a sua própria conta.');
    }

    $user = get_user_by('ID', $user_id);
    if (!$user || strtolower($user->user_email) !== strtolower($email)) {
        sige_ajax_equipe_send_error('Utilizador inválido para esta operação.');
    }
    if ($status_ativo === 0 && (in_array('administrator', (array)$user->roles, true) || (function_exists('is_super_admin') && is_super_admin($user_id)))) {
        sige_ajax_equipe_send_error('Conta técnica WordPress real não deve ser desactivada pelo módulo RH.');
    }

    if ((string)get_user_meta($user_id, 'sige_staff_removed_at', true) !== '') {
        sige_ajax_equipe_send_error('Este colaborador foi removido da equipa. Crie uma nova ficha se precisar reintegrá-lo.');
    }
    if (!sige_ajax_equipe_user_belongs_to_school($user_id, $escola_id)) {
        sige_ajax_equipe_send_error('Operação bloqueada: o colaborador não pertence à escola actual.');
    }

    $tP = $wpdb->prefix . 'sige_professores';
    $prof = sige_ajax_equipe_professor_for_user($user_id, $escola_id);
    if (!$prof || empty($prof->id)) {
        sige_ajax_equipe_send_error('Ficha de equipa não encontrada para esta escola.');
    }

    $res = $wpdb->update(
        $tP,
        ['status_ativo' => $status_ativo],
        ['id' => (int)$prof->id, 'escola_id' => $escola_id],
        ['%d'],
        ['%d', '%d']
    );

    if ($res === false) {
        sige_ajax_equipe_send_error('Erro SQL: ' . $wpdb->last_error);
    }

    // v12.11.9.6 - efeito real no utilizador: estado persistente + invalidação de sessão.
    update_user_meta($user_id, 'sige_staff_status_ativo', $status_ativo);
    update_user_meta($user_id, 'sige_status_ativo', $status_ativo);
    update_user_meta($user_id, $status_ativo ? 'sige_staff_activated_at' : 'sige_staff_deactivated_at', current_time('mysql'));
    clean_user_cache($user_id);

    if ($status_ativo === 0 && class_exists('WP_Session_Tokens')) {
        WP_Session_Tokens::get_instance($user_id)->destroy_all();
    }

    if (function_exists('sige_rh_user_is_active_for_school')) {
        $effective_state = sige_rh_user_is_active_for_school($user_id, $escola_id) ? 1 : 0;
        if ($effective_state !== $status_ativo) {
            sige_ajax_equipe_send_error('O estado foi gravado, mas a validação efectiva ainda não reflecte a alteração. Recarregue e tente novamente.');
        }
    }

    $accao_label = $status_ativo ? 'staff_activado' : 'staff_desactivado';
    sige_ajax_equipe_audit($accao_label, [
        'staff_user_id' => $user_id,
        'email' => $email,
        'status_ativo' => $status_ativo,
    ]);

    sige_ajax_equipe_send_success(['status_ativo' => $status_ativo, 'user_id' => $user_id]);
});

// ============================================================================
// LISTAR ALUNOS DE TURMA
// ============================================================================
add_action('wp_ajax_sige_listar_alunos_turma', function () {
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_turmas_action')) {
        wp_send_json_error('Sessão expirada. Recarregue a página e tente novamente.');
    }
    
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')
        && !current_user_can('sige_secretaria_geral') && !current_user_can('sige_assistente')
        && !current_user_can('sige_professor') && !current_user_can('sige_secretario')) {
        wp_send_json_error('Sem permissao.');
    }
    
    global $wpdb;
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $ano      = (int)($_POST['ano_lectivo'] ?? wp_date('Y'));
    
    if (!$turma_id) {
        wp_send_json_error('Turma nao especificada.');
    }
    
    $tA = $wpdb->prefix . 'sige_alunos';
    $tM = $wpdb->prefix . 'sige_matriculas';
    
    $pop_ch = function_exists('sige_aluno_matricula_activa_sql') ? sige_aluno_matricula_activa_sql('a','m') : "(a.status IS NULL OR TRIM(LOWER(a.status)) IN ('activo','ativo','activa','ativa')) AND (m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo'))";
    $ord_ch = function_exists('sige_turma_ordem_chamada_order_sql') ? sige_turma_ordem_chamada_order_sql('a') : "a.nome_completo ASC, a.id ASC";
    $alunos = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.nome_completo, a.data_nascimento, a.genero, a.numero_processo,
                a.contacto_encarregado, a.nome_pai, a.nome_mae
         FROM {$tA} a
         INNER JOIN {$tM} m ON m.aluno_id = a.id AND m.turma_id = %d AND m.ano_lectivo = %d
         WHERE a.escola_id = %d AND {$pop_ch}
         ORDER BY {$ord_ch}",
        $turma_id, $ano, sige_get_escola_id()
    ));
    
    wp_send_json_success($alunos ?: []);
});

// ============================================================================
// SALVAR FUNCIONÁRIO - PRO HARDENED v12.11.9
// ============================================================================
add_action('wp_ajax_sige_salvar_funcionario', function () {
    sige_ajax_equipe_begin_buffer();
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_equipe_action')) {
        sige_ajax_equipe_send_error('Sessão expirada. Recarregue a página.');
    }

    if (!sige_ajax_equipe_can_manage()) {
        sige_ajax_equipe_send_error('Sem permissão para gerir equipa.');
    }

    global $wpdb;
    $tabela    = $wpdb->prefix . 'sige_professores';
    $escola_id = sige_ajax_equipe_escola_id();

    $staff_id = absint($_POST['staff_id'] ?? 0);
    $nome     = sige_ajax_equipe_limited_text($_POST['nome_staff'] ?? '', 255);
    $email    = sanitize_email(wp_unslash($_POST['email_staff'] ?? ''));
    $cargo    = sanitize_key(wp_unslash($_POST['cargo_staff'] ?? ''));

    $roles_permitidas = sige_ajax_equipe_allowed_roles();
    if (!empty($cargo) && !in_array($cargo, $roles_permitidas, true)) {
        sige_ajax_equipe_send_error('Perfil de acesso inválido ou reservado.');
    }
    if ($cargo === 'sige_admin_ti' && !sige_ajax_equipe_is_real_wp_admin()) {
        sige_ajax_equipe_send_error('Apenas administrador WordPress real pode atribuir o perfil Admin TI.');
    }

    $telemovel     = sige_ajax_equipe_sanitize_phone($_POST['telemovel'] ?? '', 20);
    $nuit          = sige_ajax_equipe_sanitize_digits($_POST['nuit'] ?? '');
    $formacao      = sige_ajax_equipe_limited_text($_POST['formacao'] ?? '', 255);
    $tipo_contrato = sanitize_key(wp_unslash($_POST['tipo_contrato'] ?? 'efectivo'));
    $fim_contrato_raw = wp_unslash($_POST['fim_contrato'] ?? '');
    $fim_contrato  = sige_ajax_equipe_date_ymd_or_empty($fim_contrato_raw);
    $salario_base  = sige_ajax_equipe_normalize_decimal($_POST['salario_base'] ?? 0);
    $subsidio      = sige_ajax_equipe_normalize_decimal($_POST['subsidio'] ?? 0);
    $foto_perfil   = sige_ajax_equipe_sanitize_image_url($_POST['foto_perfil'] ?? '');

    $dados_bancarios_raw = isset($_POST['dados_bancarios']) ? wp_unslash($_POST['dados_bancarios']) : '';
    $dados_bancarios_arr = json_decode($dados_bancarios_raw, true);
    if (!is_array($dados_bancarios_arr)) $dados_bancarios_arr = [];
    $dados_bancarios_arr = [
        'banco_nome' => sige_ajax_equipe_limited_text($dados_bancarios_arr['banco_nome'] ?? wp_unslash($_POST['banco_nome'] ?? ''), 80),
        'nib'        => sige_ajax_equipe_sanitize_bank_digits($dados_bancarios_arr['nib'] ?? wp_unslash($_POST['banco_nib'] ?? $_POST['nib'] ?? ''), 34),
        'mpesa'      => sige_ajax_equipe_sanitize_phone($dados_bancarios_arr['mpesa'] ?? wp_unslash($_POST['banco_mpesa'] ?? $_POST['mpesa'] ?? ''), 20),
    ];
    $dados_bancarios = wp_json_encode($dados_bancarios_arr, JSON_UNESCAPED_UNICODE);

    $documentos_urls_raw = isset($_POST['documentos_urls']) ? wp_unslash($_POST['documentos_urls']) : '';
    $documentos_urls_arr = json_decode($documentos_urls_raw, true);
    if (!is_array($documentos_urls_arr)) $documentos_urls_arr = [];
    $documentos_urls_arr = sige_ajax_equipe_normalize_docs([
        'doc_bi'   => $documentos_urls_arr['doc_bi'] ?? wp_unslash($_POST['doc_bi'] ?? ''),
        'doc_cv'   => $documentos_urls_arr['doc_cv'] ?? wp_unslash($_POST['doc_cv'] ?? ''),
        'doc_cert' => $documentos_urls_arr['doc_cert'] ?? wp_unslash($_POST['doc_cert'] ?? ''),
    ]);
    // Armazenamento privado: mover os documentos da equipa para fora do directorio publico.
    if (function_exists('sige_uploads_privatize_doc_value')) {
        foreach (['doc_bi', 'doc_cv', 'doc_cert'] as $sige_doc_field) {
            if (!empty($documentos_urls_arr[$sige_doc_field]) && is_string($documentos_urls_arr[$sige_doc_field])) {
                $documentos_urls_arr[$sige_doc_field] = sige_uploads_privatize_doc_value($documentos_urls_arr[$sige_doc_field]);
            }
        }
    }
    $documentos_urls = wp_json_encode($documentos_urls_arr, JSON_UNESCAPED_UNICODE);

    if ($nome === '' || $email === '' || $cargo === '') {
        sige_ajax_equipe_send_error('Nome, email e perfil de acesso são obrigatórios.');
    }
    if (function_exists('mb_strlen') && mb_strlen($nome, 'UTF-8') < 2) {
        sige_ajax_equipe_send_error('Nome do colaborador demasiado curto.');
    }
    if (!is_email($email) || strlen($email) > 100) {
        sige_ajax_equipe_send_error('Email inválido ou demasiado longo.');
    }
    if ($nuit !== '' && !preg_match('/^\d{9}$/', $nuit)) {
        sige_ajax_equipe_send_error('NUIT inválido. Use exactamente 9 dígitos.');
    }
    if ($telemovel !== '' && strlen(preg_replace('/\D+/', '', $telemovel)) < 8) {
        sige_ajax_equipe_send_error('Contacto telefónico inválido ou demasiado curto.');
    }
    if ($dados_bancarios_arr['nib'] !== '' && (strlen($dados_bancarios_arr['nib']) < 9 || strlen($dados_bancarios_arr['nib']) > 34)) {
        sige_ajax_equipe_send_error('NIB/IBAN inválido. Confirme os dígitos informados.');
    }
    if ($dados_bancarios_arr['mpesa'] !== '' && strlen(preg_replace('/\D+/', '', $dados_bancarios_arr['mpesa'])) < 8) {
        sige_ajax_equipe_send_error('Número M-Pesa inválido ou demasiado curto.');
    }
    $tipos_contrato_permitidos = ['efectivo', 'contrato', 'estagio'];
    if (!in_array($tipo_contrato, $tipos_contrato_permitidos, true)) {
        sige_ajax_equipe_send_error('Tipo de vínculo inválido.');
    }
    if (trim((string)$fim_contrato_raw) !== '' && $fim_contrato === '') {
        sige_ajax_equipe_send_error('Data de término inválida. Use o formato AAAA-MM-DD.');
    }

    $wp_user_old = null;
    $old_role = '';
    $created_user = false;

    if ($staff_id > 0) {
        $wp_user_old = get_user_by('ID', $staff_id);
        if (!$wp_user_old) {
            sige_ajax_equipe_send_error('Utilizador não encontrado.');
        }
        if ((string)get_user_meta($staff_id, 'sige_staff_removed_at', true) !== '') {
            sige_ajax_equipe_send_error('Este colaborador foi removido da equipa e não pode ser editado neste fluxo.');
        }
        if (!sige_ajax_equipe_user_belongs_to_school($staff_id, $escola_id)) {
            sige_ajax_equipe_send_error('Operação bloqueada: este utilizador não pertence à escola actual.');
        }
        if (user_can($wp_user_old->ID, 'sige_admin_ti') && !sige_ajax_equipe_is_real_wp_admin()) {
            sige_ajax_equipe_send_error('Apenas administrador WordPress real pode editar um Admin TI.');
        }
        $email_owner = email_exists($email);
        if ($email_owner && (int)$email_owner !== $staff_id) {
            sige_ajax_equipe_send_error('Este email já pertence a outro utilizador.');
        }
        $old_role = reset($wp_user_old->roles) ?: '';
    } else {
        if (email_exists($email)) {
            sige_ajax_equipe_send_error('Este email já pertence a outro utilizador.');
        }
    }

    $dados = [
        'nome_completo'       => $nome,
        'email'               => $email,
        'telemovel'           => $telemovel,
        'nuit'                => $nuit,
        'formacao_academica'  => $formacao,
        'tipo_contrato'       => $tipo_contrato,
        'fim_contrato'        => $fim_contrato ?: null,
        'salario_base'        => $salario_base,
        'subsidio'            => $subsidio,
        'foto_perfil'         => $foto_perfil,
        'dados_bancarios'     => $dados_bancarios,
        'documentos_urls'     => $documentos_urls,
        'status_ativo'        => 1,
    ];

    $old_snapshot = null;
    $professor_id = 0;

    if ($staff_id > 0) {
        $old_snapshot = [
            'display_name' => $wp_user_old->display_name,
            'user_email'   => $wp_user_old->user_email,
            'role'         => $old_role,
        ];

        // Capturar a ficha RH antes de alterar o email no WordPress. Isto evita
        // duplicar registos quando instalações antigas ainda não têm sige_professor_id.
        $prof_before = sige_ajax_equipe_professor_for_user($staff_id, $escola_id);
        $professor_id = ($prof_before && !empty($prof_before->id)) ? (int)$prof_before->id : 0;
        if ($prof_before && isset($prof_before->status_ativo)) {
            // Preserva o estado activo/inactivo durante edição normal; reactivar/desactivar usa o endpoint próprio.
            $dados['status_ativo'] = ((int)$prof_before->status_ativo) === 1 ? 1 : 0;
        }

        $updated = wp_update_user(['ID' => $staff_id, 'display_name' => $nome, 'user_email' => $email]);
        if (is_wp_error($updated)) {
            sige_ajax_equipe_send_error('Erro ao actualizar utilizador: ' . $updated->get_error_message());
        }

        if ($cargo) {
            sige_ajax_equipe_apply_access_role((int)$staff_id, $cargo, (int)$escola_id, (string)$old_role);
        }

    } else {
        $username_base = sanitize_user(strtolower(remove_accents(str_replace(' ', '.', $nome))));
        $username_base = $username_base ?: sanitize_user(current(explode('@', $email)));
        $username = $username_base;
        $i = 1;
        while (username_exists($username)) {
            $username = $username_base . '_' . $i;
            $i++;
        }
        $password = wp_generate_password(20, true);
        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            sige_ajax_equipe_send_error('Erro ao criar utilizador: ' . $user_id->get_error_message());
        }
        $created_user = true;
        $staff_id = (int)$user_id;
        wp_update_user(['ID' => $staff_id, 'display_name' => $nome]);
        if ($cargo) {
            sige_ajax_equipe_apply_access_role((int)$staff_id, $cargo, (int)$escola_id, '');
        }
    }

    $wpdb->query('START TRANSACTION');
    if ($professor_id > 0) {
        $ok = $wpdb->update($tabela, $dados, ['id' => $professor_id, 'escola_id' => $escola_id]);
    } else {
        $duplicate = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tabela} WHERE email = %s AND escola_id = %d LIMIT 1",
            $email, $escola_id
        ));
        if ($duplicate > 0) {
            $professor_id = $duplicate;
            $ok = $wpdb->update($tabela, $dados, ['id' => $professor_id, 'escola_id' => $escola_id]);
        } else {
            $dados['escola_id'] = $escola_id;
            $dados['data_admissao'] = wp_date('Y-m-d');
            $ok = $wpdb->insert($tabela, $dados);
            $professor_id = (int)$wpdb->insert_id;
        }
    }

    if ($ok === false || $professor_id <= 0) {
        $wpdb->query('ROLLBACK');
        if ($created_user) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($staff_id, 1);
        } elseif ($old_snapshot) {
            wp_update_user(['ID' => $staff_id, 'display_name' => $old_snapshot['display_name'], 'user_email' => $old_snapshot['user_email']]);
            if ($old_snapshot['role']) {
                $rollback_user = get_user_by('ID', $staff_id);
                if ($rollback_user) $rollback_user->set_role($old_snapshot['role']);
            }
        }
        sige_ajax_equipe_send_error('Não foi possível guardar os dados do colaborador. Tente novamente.');
    }

    $wpdb->query('COMMIT');

    update_user_meta($staff_id, 'sige_escola_id', $escola_id);
    update_user_meta($staff_id, 'billing_phone', $telemovel);
    update_user_meta($staff_id, 'sige_professor_id', $professor_id);
    update_user_meta($staff_id, 'sige_staff_status_ativo', (int)$dados['status_ativo']);
    update_user_meta($staff_id, 'sige_status_ativo', (int)$dados['status_ativo']);
    clean_user_cache($staff_id);

    sige_ajax_equipe_audit($created_user ? 'staff_criado' : 'staff_editado', [
        'staff_user_id' => $staff_id,
        'professor_id' => $professor_id,
        'email' => $email,
        'escola_id' => $escola_id,
        'resultado' => 'Colaborador guardado com validação de escola.'
    ]);

    sige_ajax_equipe_send_success('Colaborador guardado com sucesso!');
});

// ============================================================================
// DETALHE SEGURO DE FUNCIONÁRIO - só carrega dados sensíveis sob pedido autorizado
// ============================================================================
add_action('wp_ajax_sige_get_staff_secure', function () {
    sige_ajax_equipe_begin_buffer();
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_equipe_action')) {
        sige_ajax_equipe_send_error('Sessão expirada.');
    }
    if (!sige_ajax_equipe_can_manage()) {
        sige_ajax_equipe_send_error('Sem permissão para consultar dados sensíveis da equipa.');
    }

    $user_id = absint($_POST['id'] ?? 0);
    $escola_id = sige_ajax_equipe_escola_id();
    if ($user_id <= 0 || !sige_ajax_equipe_user_belongs_to_school($user_id, $escola_id)) {
        sige_ajax_equipe_send_error('Colaborador não encontrado nesta escola.');
    }

    $user = get_user_by('ID', $user_id);
    $rh = sige_ajax_equipe_professor_for_user($user_id, $escola_id);
    if (!$user || !$rh) {
        sige_ajax_equipe_send_error('Ficha não encontrada.');
    }

    $banco = sige_ajax_equipe_decode_json_array($rh->dados_bancarios ?? '', ['banco_nome'=>'','nib'=>'','mpesa'=>'']);
    $docs  = sige_ajax_equipe_decode_json_array($rh->documentos_urls ?? '', ['doc_bi'=>'','doc_cv'=>'','doc_cert'=>'']);
    $docs  = sige_ajax_equipe_normalize_docs($docs);
    $role  = reset($user->roles) ?: '';

    sige_ajax_equipe_audit('staff_detalhe_sensivel_consultado', [
        'staff_user_id' => $user_id,
        'professor_id' => isset($rh->id) ? (int)$rh->id : 0,
        'escola_id' => $escola_id,
        'resultado' => 'Detalhe RH carregado por utilizador autorizado.'
    ]);

    sige_ajax_equipe_send_success([
        'id'            => $user_id,
        'nome'          => $user->display_name,
        'email'         => $user->user_email,
        'role'          => $role,
        'tel'           => get_user_meta($user_id, 'billing_phone', true),
        'nuit'          => $rh->nuit ?? '',
        'formacao'      => $rh->formacao_academica ?? '',
        'tipo_contrato' => $rh->tipo_contrato ?? '',
        'fim_contrato'  => $rh->fim_contrato ?? '',
        // [v12.36.0] Campos da Ficha do Colaborador (não sensíveis, já existentes
        // na tabela): admissão/antiguidade, nível de carreira e regime de trabalho.
        'data_admissao'   => $rh->data_admissao ?? '',
        'nivel_carreira'  => $rh->nivel_carreira ?? '',
        'regime_trabalho' => $rh->regime_trabalho ?? '',
        'salario_base'  => (float)($rh->salario_base ?? 0),
        'subsidio'      => (float)($rh->subsidio ?? 0),
        'foto_perfil'   => esc_url_raw($rh->foto_perfil ?? ''),
        'banco'         => [
            'banco_nome' => sanitize_text_field($banco['banco_nome'] ?? ''),
            'nib'        => sanitize_text_field($banco['nib'] ?? ''),
            'mpesa'      => sanitize_text_field($banco['mpesa'] ?? ''),
        ],
        'docs'          => [
            'doc_bi'   => esc_url_raw($docs['doc_bi'] ?? ''),
            'doc_cv'   => esc_url_raw($docs['doc_cv'] ?? ''),
            'doc_cert' => esc_url_raw($docs['doc_cert'] ?? ''),
        ],
        'docs_secure'   => function_exists('sige_secure_staff_document_url') ? [
            'doc_bi'   => !empty($docs['doc_bi'])   ? sige_secure_staff_document_url($user_id, 'doc_bi')   : '',
            'doc_cv'   => !empty($docs['doc_cv'])   ? sige_secure_staff_document_url($user_id, 'doc_cv')   : '',
            'doc_cert' => !empty($docs['doc_cert']) ? sige_secure_staff_document_url($user_id, 'doc_cert') : '',
        ] : [],
        'status_ativo'  => (int)($rh->status_ativo ?? 1),
    ]);
});

// ============================================================================
// EXPORTAÇÃO SEGURA DA FOLHA SALARIAL - dados sensíveis não ficam no DOM
// ============================================================================
add_action('wp_ajax_sige_exportar_folha_staff', function () {
    sige_ajax_equipe_begin_buffer();
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_equipe_action')) {
        sige_ajax_equipe_send_error('Sessão expirada.');
    }
    if (!sige_ajax_equipe_can_manage()) {
        sige_ajax_equipe_send_error('Sem permissão para exportar folha salarial.');
    }

    global $wpdb;
    $escola_id = sige_ajax_equipe_escola_id();
    $role__in = [
        'sige_admin_ti', 'sige_director', 'sige_secretaria_geral',
        'sige_assistente', 'sige_financeiro', 'sige_professor',
        'sige_educador', 'sige_motorista', 'sige_limpeza',
        'sige_secretario', 'sige_gestor_rh', 'sige_pedagogico', 'sige_recepcao', 'sige_guarda'
    ];

    $staff = get_users([
        'role__in'   => $role__in,
        'meta_key'   => 'sige_escola_id',
        'meta_value' => $escola_id,
        'orderby'    => 'display_name',
        'order'      => 'ASC',
    ]);

    if (empty($staff)) {
        $emails_escola = $wpdb->get_col($wpdb->prepare(
            "SELECT email FROM {$wpdb->prefix}sige_professores WHERE escola_id = %d",
            $escola_id
        ));
        if (!empty($emails_escola)) {
            $all_staff = get_users(['role__in' => $role__in, 'orderby' => 'display_name', 'order' => 'ASC']);
            $emails_lc = array_map('strtolower', $emails_escola);
            $staff = array_values(array_filter($all_staff, function($u) use ($emails_lc) {
                return in_array(strtolower($u->user_email), $emails_lc, true);
            }));
        }
    }

    $dados = [];
    foreach ($staff as $s) {
        if (!sige_ajax_equipe_user_belongs_to_school((int)$s->ID, $escola_id)) continue;
        $rh = sige_ajax_equipe_professor_for_user((int)$s->ID, $escola_id);
        if (!$rh) continue;
        $banco = sige_ajax_equipe_decode_json_array($rh->dados_bancarios ?? '', ['banco_nome'=>'','nib'=>'','mpesa'=>'']);
        $role_slug = reset($s->roles) ?: '';
        $base = (float)($rh->salario_base ?? 0);
        $sub = (float)($rh->subsidio ?? 0);
        $dados[] = [
            'nome'  => $s->display_name,
            'email' => $s->user_email,
            'cargo' => sige_ajax_equipe_role_label($role_slug),
            'nuit'  => sanitize_text_field($rh->nuit ?? ''),
            'banco' => sanitize_text_field($banco['banco_nome'] ?? ''),
            'nib'   => sanitize_text_field($banco['nib'] ?? ''),
            'mpesa' => sanitize_text_field($banco['mpesa'] ?? ''),
            'base'  => $base,
            'sub'   => $sub,
            'total' => $base + $sub,
        ];
    }

    sige_ajax_equipe_audit('folha_salarial_exportada', [
        'registos' => count($dados),
        'escola_id' => $escola_id,
        'resultado' => 'Exportação solicitada no módulo Equipa.'
    ]);

    sige_ajax_equipe_send_success(['rows' => $dados]);
});

// ============================================================================
// REMOVER FUNCIONÁRIO - SOFT DELETE SEGURO v12.11.9.8
// ============================================================================
add_action('wp_ajax_sige_remover_usuario_staff', function () {
    sige_ajax_equipe_begin_buffer();
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_equipe_action')) {
        sige_ajax_equipe_send_error('Sessão expirada.');
    }

    if (!sige_ajax_equipe_can_manage()) {
        sige_ajax_equipe_send_error('Sem permissão para gerir equipa.');
    }

    global $wpdb;
    $tabela = $wpdb->prefix . 'sige_professores';
    $escola_id = sige_ajax_equipe_escola_id();
    $user_id = absint($_POST['id'] ?? 0);

    if ($user_id <= 0) {
        sige_ajax_equipe_send_error('ID inválido.');
    }
    if ($user_id === get_current_user_id()) {
        sige_ajax_equipe_send_error('Não pode remover a sua própria conta.');
    }

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        sige_ajax_equipe_send_error('Utilizador não encontrado.');
    }
    if ((string)get_user_meta($user_id, 'sige_staff_removed_at', true) !== '') {
        sige_ajax_equipe_send_success('Colaborador já estava removido.');
    }
    if (user_can($user->ID, 'sige_admin_ti') && !sige_ajax_equipe_is_real_wp_admin()) {
        sige_ajax_equipe_send_error('Apenas administrador WordPress real pode remover um Admin TI.');
    }
    if (in_array('administrator', (array)$user->roles, true) || (function_exists('is_super_admin') && is_super_admin($user_id))) {
        sige_ajax_equipe_send_error('Conta técnica WordPress real não deve ser removida pelo módulo RH.');
    }
    if (!sige_ajax_equipe_user_belongs_to_school($user_id, $escola_id)) {
        sige_ajax_equipe_send_error('Operação bloqueada: o colaborador não pertence à escola actual.');
    }

    $prof = sige_ajax_equipe_professor_for_user($user_id, $escola_id);
    if (!$prof || empty($prof->id)) {
        sige_ajax_equipe_send_error('Ficha de equipa não encontrada para remoção segura.');
    }

    $wpdb->query('START TRANSACTION');

    $rh_update = $wpdb->update(
        $tabela,
        ['status_ativo' => 0],
        ['id' => (int)$prof->id, 'escola_id' => $escola_id],
        ['%d'],
        ['%d','%d']
    );
    if ($rh_update === false) {
        $wpdb->query('ROLLBACK');
        sige_ajax_equipe_send_error('Erro ao actualizar estado RH antes da remoção segura.');
    }

    if (function_exists('sige_permissions_tables')) {
        $tables = sige_permissions_tables();
        if (!empty($tables['user_roles'])) {
            $ur_update = $wpdb->update(
                $tables['user_roles'],
                ['ativo' => 0, 'updated_at' => current_time('mysql')],
                ['user_id' => $user_id, 'escola_id' => $escola_id],
                ['%d','%s'],
                ['%d','%d']
            );
            if ($ur_update === false) {
                $wpdb->query('ROLLBACK');
                sige_ajax_equipe_send_error('Erro ao revogar perfil SIGE do colaborador.');
            }
        }
    }

    $wpdb->query('COMMIT');

    // v12.11.9.8 - não apagar wp_users. Preserva histórico de notas, auditoria, logs e vínculos.
    sige_ajax_equipe_soft_revoke_access($user_id, $escola_id, 'rh_removed');
    if (function_exists('sige_rh_user_is_active_for_school') && sige_rh_user_is_active_for_school($user_id, $escola_id)) {
        sige_ajax_equipe_send_error('Remoção gravada, mas o bloqueio efectivo não confirmou a revogação. Recarregue e valide o estado do colaborador.');
    }

    sige_ajax_equipe_audit('staff_removido_soft_delete', [
        'staff_user_id' => $user_id,
        'email' => $user->user_email,
        'escola_id' => $escola_id,
        'professor_id' => (int)$prof->id,
        'resultado' => 'Acesso removido sem apagar wp_users; histórico preservado.'
    ]);

    sige_ajax_equipe_send_success('Colaborador removido da equipa. O acesso foi revogado e o histórico foi preservado.');
});

// ============================================================================
// RESET SENHA FUNCIONÁRIO - PRO HARDENED v12.11.9
// ============================================================================
add_action('wp_ajax_sige_resetar_senha', function () {
    sige_ajax_equipe_begin_buffer();
    if (!isset($_POST['_sige_nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['_sige_nonce']), 'sige_equipe_action')) {
        sige_ajax_equipe_send_error('Sessão expirada.');
    }

    if (!sige_ajax_equipe_can_manage()) {
        sige_ajax_equipe_send_error('Sem permissão para gerir equipa.');
    }

    $user_id = absint($_POST['id'] ?? 0);
    $escola_id = sige_ajax_equipe_escola_id();
    if ($user_id <= 0) {
        sige_ajax_equipe_send_error('ID inválido.');
    }
    if (!sige_ajax_equipe_user_belongs_to_school($user_id, $escola_id)) {
        sige_ajax_equipe_send_error('Operação bloqueada: o colaborador não pertence à escola actual.');
    }

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        sige_ajax_equipe_send_error('Utilizador não encontrado.');
    }

    global $wpdb;
    $escola = $wpdb->get_row($wpdb->prepare("SELECT nome_escola, email_institucional FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $escola_id));
    $nome_escola = $escola->nome_escola ?? 'SIGE SoftGenial';
    $email_escola = $escola->email_institucional ?? get_option('admin_email');

    $email_enviado = function_exists('sige_sec_send_password_reset_email')
        ? sige_sec_send_password_reset_email($user, (string)$nome_escola, (string)$email_escola)
        : retrieve_password($user->user_login);

    sige_ajax_equipe_audit('reset_senha_link_seguro', [
        'staff_user_id' => $user_id,
        'email' => $user->user_email,
        'escola_id' => $escola_id,
        'resultado' => 'Link seguro enviado com validação de escola.'
    ]);

    if ($email_enviado) {
        sige_ajax_equipe_send_success('Link seguro de redefinição enviado para ' . $user->user_email . '. A senha não é mostrada nem enviada em texto claro.');
    }
    sige_ajax_equipe_send_error('Não foi possível enviar o link de redefinição. Verifique SMTP.');
});
