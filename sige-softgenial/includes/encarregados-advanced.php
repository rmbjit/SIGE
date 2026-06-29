<?php
/**
 * SIGE SoftGenial - Gestão Avançada de Encarregados
 *
 * v12.11.9.51
 * Camada auxiliar para normalização de contactos, consentimentos e leitura
 * minimizada dos campos avançados de encarregados do aluno.
 *
 * Princípios:
 * - Não altera regras financeiras/académicas.
 * - Não envia mensagens por si só.
 * - Apenas fornece helpers defensivos para UI, Ficha 360º e políticas de envio.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_guardian_adv_text')) {
    function sige_guardian_adv_text($value, int $max_len = 180): string {
        if (is_array($value) || is_object($value)) return '';
        $text = wp_strip_all_tags((string)$value);
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if (!is_string($text)) $text = '';
        $text = sanitize_text_field($text);
        if ($max_len > 0) {
            $text = function_exists('mb_substr') ? mb_substr($text, 0, $max_len, 'UTF-8') : substr($text, 0, $max_len);
        }
        return $text;
    }
}

if (!function_exists('sige_guardian_adv_email')) {
    function sige_guardian_adv_email($value): string {
        $email = strtolower(trim(sige_guardian_adv_text($value, 180)));
        return is_email($email) ? sanitize_email($email) : '';
    }
}

if (!function_exists('sige_guardian_adv_phone')) {
    function sige_guardian_adv_phone($value): string {
        $digits = preg_replace('/\D+/', '', (string)$value);
        if (strlen($digits) === 12 && substr($digits, 0, 3) === '258') {
            $digits = substr($digits, 3);
        }
        return $digits;
    }
}

if (!function_exists('sige_guardian_adv_is_valid_phone')) {
    function sige_guardian_adv_is_valid_phone($value): bool {
        return (bool)preg_match('/^(82|83|84|85|86|87)\d{7}$/', sige_guardian_adv_phone($value));
    }
}

if (!function_exists('sige_guardian_adv_bool')) {
    /**
     * Para bases antigas, ausência da coluna equivale a permitido para evitar regressão.
     */
    function sige_guardian_adv_bool($row, string $prop, bool $default = true): bool {
        if (!is_object($row) || !property_exists($row, $prop)) return $default;
        return (int)$row->{$prop} === 1;
    }
}

if (!function_exists('sige_guardian_adv_relationship_label')) {
    function sige_guardian_adv_relationship_label(string $key): string {
        $key = strtolower(trim($key));
        $map = [
            'pai' => 'Pai',
            'mae' => 'Mãe',
            'pai_mae' => 'Pai/Mãe',
            'mae_pai' => 'Pai/Mãe',
            'outro' => 'Outro encarregado',
            'tio' => 'Tio/Tia',
            'avo' => 'Avô/Avó',
            'irmao' => 'Irmão/Irmã',
            'guardiao' => 'Guardião legal',
        ];
        return $map[$key] ?? ($key !== '' ? ucfirst($key) : 'Não definido');
    }
}

if (!function_exists('sige_guardian_adv_payload')) {
    /**
     * Payload minimizado para a Ficha 360º e UI: não expõe campos técnicos.
     */
    function sige_guardian_adv_payload($aluno): array {
        if (!is_object($aluno)) return [];
        $principal_tipo = sige_guardian_adv_text($aluno->encarregado_principal_tipo ?? '', 40);
        $canal = sige_guardian_adv_text($aluno->canal_preferencial_comunicacao ?? '', 40);
        $cons_whatsapp = sige_guardian_adv_bool($aluno, 'consent_whatsapp', true);
        $cons_email    = sige_guardian_adv_bool($aluno, 'consent_email', true);
        $cons_sms      = sige_guardian_adv_bool($aluno, 'consent_sms', false);
        $cons_chamada  = sige_guardian_adv_bool($aluno, 'consent_chamada', true);

        return [
            'principal_tipo' => $principal_tipo ?: 'pai_mae',
            'principal_label' => sige_guardian_adv_relationship_label($principal_tipo ?: 'pai_mae'),
            'principal_nome' => sige_guardian_adv_text($aluno->encarregado_principal_nome ?? '', 140),
            'principal_parentesco' => sige_guardian_adv_text($aluno->encarregado_principal_parentesco ?? '', 80),
            'principal_telemovel' => sige_guardian_adv_phone($aluno->encarregado_principal_telemovel ?? ''),
            'principal_telemovel_valido' => sige_guardian_adv_is_valid_phone($aluno->encarregado_principal_telemovel ?? ''),
            'principal_email' => sige_guardian_adv_email($aluno->encarregado_principal_email ?? ''),
            'canal_preferencial' => $canal ?: 'whatsapp',
            'consentimentos' => [
                'whatsapp' => $cons_whatsapp,
                'email' => $cons_email,
                'sms' => $cons_sms,
                'chamada' => $cons_chamada,
            ],
            'contacto_alternativo' => [
                'nome' => sige_guardian_adv_text($aluno->contacto_alternativo_nome ?? '', 140),
                'parentesco' => sige_guardian_adv_text($aluno->contacto_alternativo_parentesco ?? '', 80),
                'telemovel' => sige_guardian_adv_phone($aluno->contacto_alternativo_telemovel ?? ''),
                'telemovel_valido' => sige_guardian_adv_is_valid_phone($aluno->contacto_alternativo_telemovel ?? ''),
            ],
            'autorizado_buscar' => [
                'nome' => sige_guardian_adv_text($aluno->autorizado_buscar_nome ?? '', 140),
                'parentesco' => sige_guardian_adv_text($aluno->autorizado_buscar_parentesco ?? '', 80),
                'telemovel' => sige_guardian_adv_phone($aluno->autorizado_buscar_telemovel ?? ''),
                'documento' => sige_guardian_adv_text($aluno->autorizado_buscar_documento ?? '', 80),
                'telemovel_valido' => sige_guardian_adv_is_valid_phone($aluno->autorizado_buscar_telemovel ?? ''),
            ],
            'observacoes' => sige_guardian_adv_text($aluno->encarregado_observacoes ?? '', 240),
        ];
    }
}

// ============================================================================
// v12.11.9.50 - Auditoria própria da Gestão Avançada de Encarregados
// ============================================================================
// Regista alterações sensíveis dos encarregados numa tabela própria, com valores
// normalizados e exposição minimizada para a Ficha 360º. Não bloqueia a gravação
// do aluno se a tabela ainda não existir; apenas não regista o histórico.

if (!function_exists('sige_guardian_adv_audit_fields')) {
    function sige_guardian_adv_audit_fields(): array {
        return [
            'nome_pai' => ['label' => 'Nome do pai', 'type' => 'text', 'default' => ''],
            'telemovel_pai' => ['label' => 'Telemóvel do pai', 'type' => 'phone', 'default' => ''],
            'telemovel_pai_2' => ['label' => 'Telemóvel alternativo do pai', 'type' => 'phone', 'default' => ''],
            'email_pai' => ['label' => 'E-mail do pai', 'type' => 'email', 'default' => ''],
            'nome_mae' => ['label' => 'Nome da mãe', 'type' => 'text', 'default' => ''],
            'telemovel_mae' => ['label' => 'Telemóvel da mãe', 'type' => 'phone', 'default' => ''],
            'telemovel_mae_2' => ['label' => 'Telemóvel alternativo da mãe', 'type' => 'phone', 'default' => ''],
            'email_mae' => ['label' => 'E-mail da mãe', 'type' => 'email', 'default' => ''],
            'whatsapp_notificacoes' => ['label' => 'WhatsApp principal', 'type' => 'phone', 'default' => ''],
            'email_encarregado' => ['label' => 'E-mail principal', 'type' => 'email', 'default' => ''],
            'contacto_encarregado' => ['label' => 'Contacto principal legado', 'type' => 'phone', 'default' => ''],
            'encarregado_principal_tipo' => ['label' => 'Encarregado principal', 'type' => 'principal_tipo', 'default' => 'pai_mae'],
            'encarregado_principal_nome' => ['label' => 'Nome do outro encarregado principal', 'type' => 'text', 'default' => ''],
            'encarregado_principal_parentesco' => ['label' => 'Parentesco do outro encarregado', 'type' => 'text', 'default' => ''],
            'encarregado_principal_telemovel' => ['label' => 'Telemóvel do outro encarregado', 'type' => 'phone', 'default' => ''],
            'encarregado_principal_email' => ['label' => 'E-mail do outro encarregado', 'type' => 'email', 'default' => ''],
            'canal_preferencial_comunicacao' => ['label' => 'Canal preferencial', 'type' => 'channel', 'default' => 'whatsapp'],
            'consent_whatsapp' => ['label' => 'Consentimento WhatsApp', 'type' => 'bool', 'default' => '1'],
            'consent_email' => ['label' => 'Consentimento e-mail', 'type' => 'bool', 'default' => '1'],
            'consent_sms' => ['label' => 'Consentimento SMS', 'type' => 'bool', 'default' => '0'],
            'consent_chamada' => ['label' => 'Consentimento chamada', 'type' => 'bool', 'default' => '1'],
            'contacto_alternativo_nome' => ['label' => 'Nome do contacto alternativo', 'type' => 'text', 'default' => ''],
            'contacto_alternativo_parentesco' => ['label' => 'Parentesco do contacto alternativo', 'type' => 'text', 'default' => ''],
            'contacto_alternativo_telemovel' => ['label' => 'Telemóvel do contacto alternativo', 'type' => 'phone', 'default' => ''],
            'autorizado_buscar_nome' => ['label' => 'Pessoa autorizada a buscar', 'type' => 'text', 'default' => ''],
            'autorizado_buscar_parentesco' => ['label' => 'Parentesco da pessoa autorizada', 'type' => 'text', 'default' => ''],
            'autorizado_buscar_telemovel' => ['label' => 'Telemóvel da pessoa autorizada', 'type' => 'phone', 'default' => ''],
            'autorizado_buscar_documento' => ['label' => 'Documento da pessoa autorizada', 'type' => 'document', 'default' => ''],
            'encarregado_observacoes' => ['label' => 'Observações de comunicação/autorização', 'type' => 'textarea', 'default' => ''],
        ];
    }
}

if (!function_exists('sige_guardian_adv_audit_value_from')) {
    function sige_guardian_adv_audit_value_from($row, string $field, array $def): string {
        if (is_array($row) && array_key_exists($field, $row)) {
            $value = $row[$field];
        } elseif (is_object($row) && property_exists($row, $field)) {
            $value = $row->{$field};
        } else {
            $value = $def['default'] ?? '';
        }
        $type = $def['type'] ?? 'text';
        if ($type === 'phone') {
            return sige_guardian_adv_phone($value);
        }
        if ($type === 'email') {
            return sige_guardian_adv_email($value);
        }
        if ($type === 'bool') {
            return ((string)$value === '1' || $value === 1 || $value === true) ? '1' : '0';
        }
        if ($type === 'principal_tipo') {
            $v = sanitize_key((string)$value);
            return in_array($v, ['pai_mae','pai','mae','outro'], true) ? $v : 'pai_mae';
        }
        if ($type === 'channel') {
            $v = sanitize_key((string)$value);
            return in_array($v, ['whatsapp','email','chamada','sms'], true) ? $v : 'whatsapp';
        }
        return sige_guardian_adv_text($value, $type === 'textarea' ? 500 : 180);
    }
}

if (!function_exists('sige_guardian_adv_mask_phone')) {
    function sige_guardian_adv_mask_phone(string $phone): string {
        $phone = sige_guardian_adv_phone($phone);
        if ($phone === '') return '-';
        if (strlen($phone) < 5) return str_repeat('•', strlen($phone));
        return substr($phone, 0, 2) . str_repeat('•', max(3, strlen($phone) - 4)) . substr($phone, -2);
    }
}

if (!function_exists('sige_guardian_adv_mask_email')) {
    function sige_guardian_adv_mask_email(string $email): string {
        $email = sige_guardian_adv_email($email);
        if ($email === '') return '-';
        $parts = explode('@', $email, 2);
        $name = $parts[0] ?? '';
        $domain = $parts[1] ?? '';
        $nameMasked = ($name === '') ? '•••' : substr($name, 0, 1) . str_repeat('•', max(2, strlen($name) - 1));
        return $nameMasked . ($domain ? '@' . $domain : '');
    }
}

if (!function_exists('sige_guardian_adv_mask_document')) {
    function sige_guardian_adv_mask_document(string $doc): string {
        $doc = sige_guardian_adv_text($doc, 80);
        if ($doc === '') return '-';
        $len = strlen($doc);
        if ($len <= 4) return str_repeat('•', $len);
        return substr($doc, 0, 2) . str_repeat('•', max(2, $len - 4)) . substr($doc, -2);
    }
}

if (!function_exists('sige_guardian_adv_audit_display_value')) {
    function sige_guardian_adv_audit_display_value(string $value, string $type): string {
        if ($type === 'phone') return sige_guardian_adv_mask_phone($value);
        if ($type === 'email') return sige_guardian_adv_mask_email($value);
        if ($type === 'document') return sige_guardian_adv_mask_document($value);
        if ($type === 'bool') return $value === '1' ? 'Sim' : 'Não';
        if ($type === 'principal_tipo') return sige_guardian_adv_relationship_label($value ?: 'pai_mae');
        if ($type === 'channel') {
            $map = ['whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'chamada' => 'Chamada', 'sms' => 'SMS'];
            return $map[$value] ?? ($value ?: '-');
        }
        $value = sige_guardian_adv_text($value, 180);
        return $value !== '' ? $value : '-';
    }
}

if (!function_exists('sige_guardian_adv_audit_hash')) {
    function sige_guardian_adv_audit_hash(string $value): string {
        if ($value === '') return '';
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : 'sige-softgenial');
        return hash_hmac('sha256', $value, $salt);
    }
}

if (!function_exists('sige_guardian_adv_audit_diff')) {
    function sige_guardian_adv_audit_diff($before, $after): array {
        $changes = [];
        foreach (sige_guardian_adv_audit_fields() as $field => $def) {
            $old = sige_guardian_adv_audit_value_from($before, $field, $def);
            $new = sige_guardian_adv_audit_value_from($after, $field, $def);
            if ($old === $new) continue;
            $type = $def['type'] ?? 'text';
            $changes[] = [
                'campo' => $field,
                'label' => sige_guardian_adv_text($def['label'] ?? $field, 120),
                'antes' => sige_guardian_adv_audit_display_value($old, $type),
                'depois' => sige_guardian_adv_audit_display_value($new, $type),
                'antes_hash' => sige_guardian_adv_audit_hash($old),
                'depois_hash' => sige_guardian_adv_audit_hash($new),
            ];
        }
        return $changes;
    }
}

if (!function_exists('sige_guardian_adv_table_exists')) {
    function sige_guardian_adv_table_exists(string $table): bool {
        global $wpdb;
        try {
            $show = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ((string)$show === $table) return true;
        } catch (Throwable $e) {
            // Fallback abaixo; não deve bloquear gravação do aluno.
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $table
        ));
        return (int)$exists > 0;
    }
}

if (!function_exists('sige_guardian_adv_privacy_hash')) {
    function sige_guardian_adv_privacy_hash(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        $salt = function_exists('wp_salt') ? wp_salt('secure_auth') : (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'sige-softgenial');
        return hash_hmac('sha256', $value, $salt);
    }
}

if (!function_exists('sige_guardian_adv_audit_log')) {
    function sige_guardian_adv_audit_log(int $aluno_id, $before, $after, array $context = []): bool {
        if ($aluno_id <= 0) return false;
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'sige_alunos_encarregados_historico';
            if (!sige_guardian_adv_table_exists($table)) return false;

            $changes = sige_guardian_adv_audit_diff($before, $after);
            if (empty($changes)) return false;

            $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
            if ($escola_id <= 0) { return false; }
            $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
            $user_id = function_exists('get_current_user_id') ? (int)get_current_user_id() : 0;
            $user_display = ($user && !empty($user->display_name)) ? sige_guardian_adv_text($user->display_name, 150) : '';
            $fields = array_map(static function($c) { return (string)($c['campo'] ?? ''); }, $changes);
            $labels = array_map(static function($c) { return (string)($c['label'] ?? ''); }, $changes);
            $labels = array_values(array_filter($labels));
            $evento = sanitize_key((string)($context['evento'] ?? 'encarregados_actualizados'));
            if ($evento === '') $evento = 'encarregados_actualizados';
            $origem = sanitize_key((string)($context['origem'] ?? 'cadastro_aluno'));
            if ($origem === '') $origem = 'cadastro_aluno';
            $resumo = sprintf(
                '%s campo(s) actualizado(s): %s',
                count($changes),
                implode(', ', array_slice($labels, 0, 5))
            );
            if (count($labels) > 5) $resumo .= '…';

            $ip_raw = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
            $ua_raw = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';

            $ok = $wpdb->insert($table, [
                'escola_id' => $escola_id,
                'aluno_id' => $aluno_id,
                'evento' => $evento,
                'origem' => $origem,
                'campos_alterados' => implode(',', array_filter($fields)),
                'total_alteracoes' => count($changes),
                'resumo' => sige_guardian_adv_text($resumo, 250),
                'alteracoes_json' => wp_json_encode($changes, JSON_UNESCAPED_UNICODE),
                'ip_hash' => sige_guardian_adv_privacy_hash($ip_raw),
                'user_agent_hash' => sige_guardian_adv_privacy_hash($ua_raw),
                'criado_em' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
                'criado_por' => $user_id,
                'user_display' => $user_display,
            ], ['%d','%d','%s','%s','%s','%d','%s','%s','%s','%s','%s','%d','%s']);

            if ($ok !== false && function_exists('sige_audit_log')) {
                sige_audit_log('aluno_encarregados_historico_registado', [
                    'aluno_id' => $aluno_id,
                    'evento' => $evento,
                    'total_alteracoes' => count($changes),
                    'campos' => implode(',', array_filter($fields)),
                ], 'alunos');
            }
            return $ok !== false;
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                error_log('[SIGE Encarregados Histórico] Falha ao registar auditoria: ' . $e->getMessage());
            }
            return false;
        }
    }
}
