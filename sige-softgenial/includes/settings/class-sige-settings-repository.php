<?php
/**
 * SIGE SoftGenial - Settings Repository
 *
 * Camada de leitura/escrita única para todas as configurações conhecidas.
 * Resolve a fonte (sige_config, sige_fin_configuracoes, wp_option, constant)
 * a partir do Registry, com cache transparente por request e invalidação
 * automática quando o valor muda.
 *
 * Suporta:
 *   - Encriptação automática de campos marcados encrypted=true
 *   - Multi-tenant via escola_id em todas as escritas
 *   - Preservação automática quando preserve_when_empty=true e valor vazio
 *   - Batch read (get_many) e batch write (set_many) atómico-best-effort
 *
 * @since v12.10.0
 * @updated v12.12.6 - fail-closed tenant resolution for table writes.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Repository')) {

    final class SIGE_Settings_Repository {

        /** @var array Cache estático por request, indexado por escola_id|key. */
        private static $request_cache = [];

        /** Hook para invalidação externa. */
        public static function init(): void {
            // Reservado para futuras integrações (cron, REST, etc.).
        }

        /** Devolve o valor sanitizado de uma chave, com cache. */
        public static function get(string $key, $default = null) {
            $meta = SIGE_Settings_Registry::get($key);
            if (!$meta) return $default;

            $cache_key = self::cache_key($key);
            if (array_key_exists($cache_key, self::$request_cache)) {
                return self::$request_cache[$cache_key];
            }

            $fallback = ($default !== null) ? $default : ($meta['default'] ?? null);
            $value = self::read_raw($meta, $fallback);

            // Desencriptar para apresentação se aplicável.
            if (!empty($meta['encrypted']) && is_string($value) && $value !== '' && function_exists('sige_decrypt_token')) {
                $decrypted = sige_decrypt_token($value);
                if ($decrypted !== false && $decrypted !== null) $value = $decrypted;
            }

            self::$request_cache[$cache_key] = $value;
            return $value;
        }

        /** Lê múltiplas chaves de uma só vez. */
        public static function get_many(array $keys, array $defaults = []): array {
            $out = [];
            foreach ($keys as $key) {
                $out[$key] = self::get($key, $defaults[$key] ?? null);
            }
            return $out;
        }

        /** Devolve representação mascarada para apresentação. */
        public static function get_masked(string $key, $default = null): string {
            $meta = SIGE_Settings_Registry::get($key);
            $value = self::get($key, $default);
            $sensitive = !empty($meta['sensitive']) || !empty($meta['masked']);
            return SIGE_Settings_Sanitizer::mask($value, $sensitive);
        }

        /**
         * Grava um valor. Aplica encriptação e preservação automáticas.
         * Devolve [ok, mensagem_erro_ou_vazio].
         */
        public static function set(string $key, $value, array $context = []): array {
            $meta = SIGE_Settings_Registry::get($key);
            if (!$meta) return [false, 'Chave desconhecida: ' . $key];
            if (!empty($meta['readonly'])) return [false, 'Chave só de leitura: ' . $key];

            // Preservação automática quando campo chega vazio e meta o permite.
            if (!empty($meta['preserve_when_empty']) && self::is_empty_value($value, (string)($meta['type'] ?? 'string'))) {
                return [true, '']; // No-op silencioso.
            }

            // Encriptação automática para campos secret.
            $storage_value = $value;
            if (!empty($meta['encrypted']) && function_exists('sige_encrypt_token') && is_string($storage_value) && $storage_value !== '') {
                $storage_value = sige_encrypt_token($storage_value);
            }

            // Preservação parcial para SMTP (password vazia mantém actual).
            // v12.11.9.35 - corrige gravação via Centro de Configuração:
            // a palavra-passe SMTP deve ficar encriptada, tal como no handler
            // nativo de email-engine.php. Sem isto, o Zoho pode recusar
            // autenticação porque o motor tenta desencriptar um valor em claro.
            if (($meta['type'] ?? '') === 'smtp' && is_array($storage_value)) {
                $current = (array) get_option((string)$meta['option'], []);

                if (empty($storage_value['host'])) {
                    $storage_value['host'] = 'smtp.zoho.com';
                }
                $storage_value['port'] = max(1, min(65535, (int)($storage_value['port'] ?? 465)));
                if (empty($storage_value['from_name'])) {
                    $storage_value['from_name'] = get_bloginfo('name') ?: 'SoftGenial';
                }
                if (empty($storage_value['from_email']) && !empty($storage_value['username']) && is_email((string)$storage_value['username'])) {
                    $storage_value['from_email'] = sanitize_email((string)$storage_value['username']);
                }

                $raw_password = array_key_exists('password', $storage_value) ? (string)$storage_value['password'] : '';
                if ($raw_password === '' && array_key_exists('password', $current)) {
                    $storage_value['password'] = $current['password'];
                } elseif ($raw_password !== '') {
                    $looks_encrypted = (strpos($raw_password, 'sige2:') === 0 || strpos($raw_password, 'gcm1:') === 0);
                    $storage_value['password'] = $looks_encrypted
                        ? $raw_password
                        : (function_exists('sige_encrypt_token') ? sige_encrypt_token($raw_password) : base64_encode($raw_password));
                }

                $storage_value['updated_at'] = current_time('mysql');
                $storage_value['updated_by'] = get_current_user_id();
            }

            $source = (string)($meta['source'] ?? '');
            $ok = false; $err = '';

            switch ($source) {
                case 'sige_config':
                    [$ok, $err] = self::write_sige_config($meta, $storage_value);
                    break;
                case 'wp_option':
                    [$ok, $err] = self::write_wp_option($meta, $storage_value);
                    break;
                case 'sige_fin_configuracoes':
                case 'constant':
                default:
                    return [false, 'Fonte não gravável: ' . $source];
            }

            if ($ok) {
                self::invalidate($key);
                // Auditoria de alteracao de segredo (apenas a chave, nunca o valor).
                if ((!empty($meta['encrypted']) || ($meta['type'] ?? '') === 'smtp') && function_exists('sige_security_log')) {
                    sige_security_log('segredo_alterado', 'chave=' . $key);
                }
            }
            return [$ok, $err];
        }

        /**
         * Grava múltiplas chaves. Atómico-best-effort: regista todas as mudanças
         * e devolve resumo.
         *
         * @return array{ok:bool, errors:string[], changed:array, count:int}
         */
        public static function set_many(array $kv, array $context = []): array {
            $errors  = [];
            $changed = [];
            $actor   = (string)($context['actor'] ?? 'unknown');

            foreach ($kv as $key => $raw) {
                $meta = SIGE_Settings_Registry::get($key);
                if (!$meta) continue;

                $sanitized = SIGE_Settings_Sanitizer::sanitize($raw, $meta);
                [$valid, $msg] = SIGE_Settings_Sanitizer::validate($sanitized, $meta);
                if (!$valid) { $errors[] = $msg; continue; }

                // Preservação total quando vazio.
                if (!empty($meta['preserve_when_empty']) && self::is_empty_value($sanitized, (string)($meta['type'] ?? 'string'))) {
                    continue;
                }

                $old = self::get($key, null);
                [$ok, $err] = self::set($key, $sanitized, $context);

                if ($ok) {
                    $new = self::get($key, null);
                    if (self::values_differ($old, $new)) {
                        $changed[] = ['key' => $key, 'label' => (string)($meta['label'] ?? $key)];
                        if (class_exists('SIGE_Settings_Audit')) {
                            SIGE_Settings_Audit::log_change($key, $meta, $old, $new, $actor);
                        }
                    }
                } else {
                    $errors[] = $err ?: ('Falha ao gravar ' . (string)($meta['label'] ?? $key));
                }
            }

            return [
                'ok'      => empty($errors),
                'errors'  => $errors,
                'changed' => $changed,
                'count'   => count($changed),
            ];
        }

        /** Limpa cache de uma chave (chamado após set bem-sucedido). */
        public static function invalidate(string $key): void {
            unset(self::$request_cache[self::cache_key($key)]);
            do_action('sige_settings_changed', $key);
        }

        public static function flush_cache(): void {
            self::$request_cache = [];
        }

        // ─── Leitura por fonte ────────────────────────────────────────────────

        private static function read_raw(array $meta, $fallback) {
            $source = (string)($meta['source'] ?? '');
            switch ($source) {
                case 'sige_config':
                case 'sige_fin_configuracoes':
                    return self::read_table($meta, $fallback);
                case 'wp_option':
                    return self::read_option($meta, $fallback);
                case 'constant':
                    $c = (string)($meta['constant'] ?? '');
                    return ($c !== '' && defined($c)) ? constant($c) : $fallback;
                default:
                    return $fallback;
            }
        }

        private static function read_table(array $meta, $fallback) {
            global $wpdb;
            if (!isset($wpdb) || !is_object($wpdb)) return $fallback;

            $table_slug = (string)($meta['table'] ?? '');
            $column     = (string)($meta['column'] ?? '');
            if ($table_slug === '' || $column === '') return $fallback;

            $table = $wpdb->prefix . $table_slug;
            if (!self::table_exists($table) || !self::column_exists($table, $column)) return $fallback;

            $eid = self::current_escola_id();

            if ($table_slug === 'sige_fin_configuracoes') {
                $ano = self::current_academic_year($eid);
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT `$column` AS v FROM `$table` WHERE escola_id=%d AND ano_letivo=%d LIMIT 1",
                    $eid, $ano
                ));
                if (!$row) {
                    $row = $wpdb->get_row($wpdb->prepare(
                        "SELECT `$column` AS v FROM `$table` WHERE escola_id=%d ORDER BY ano_letivo DESC LIMIT 1",
                        $eid
                    ));
                }
            } else {
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT `$column` AS v FROM `$table` WHERE escola_id=%d LIMIT 1",
                    $eid
                ));
            }

            return ($row && property_exists($row, 'v') && $row->v !== null) ? $row->v : $fallback;
        }

        private static function read_option(array $meta, $fallback) {
            $option = (string)($meta['option'] ?? '');
            if ($option === '') return $fallback;
            return get_option($option, $fallback);
        }

        // ─── Escrita por fonte ────────────────────────────────────────────────

        /** @return array{0:bool,1:string} */
        private static function write_sige_config(array $meta, $value): array {
            global $wpdb;
            if (!isset($wpdb) || !is_object($wpdb)) return [false, 'WPDB indisponível.'];

            $table_slug = (string)($meta['table'] ?? '');
            $column     = (string)($meta['column'] ?? '');
            if ($table_slug === '' || $column === '') return [false, 'Meta incompleto.'];

            $table = $wpdb->prefix . $table_slug;
            if (!self::table_exists($table))   return [false, 'Tabela inexistente: ' . $table_slug];
            if (!self::column_exists($table, $column)) return [false, 'Coluna inexistente: ' . $column];

            $eid = self::current_escola_id(true);
            if ($eid <= 0) {
                return [false, 'Escola não resolvida para gravação tenant-scoped.'];
            }
            $row_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `$table` WHERE escola_id=%d LIMIT 1",
                $eid
            ));

            if ($row_id > 0) {
                $res = $wpdb->update($table, [$column => $value], ['id' => $row_id]);
            } else {
                $res = $wpdb->insert($table, [$column => $value, 'escola_id' => $eid]);
            }
            return ($res !== false) ? [true, ''] : [false, 'Erro SQL: ' . $wpdb->last_error];
        }

        /** @return array{0:bool,1:string} */
        private static function write_wp_option(array $meta, $value): array {
            $option = (string)($meta['option'] ?? '');
            if ($option === '') return [false, 'Option não definido.'];
            $ok = update_option($option, $value, false);
            // update_option devolve false quando o valor é igual; consideramos sucesso silencioso.
            return [true, ''];
        }

        // ─── Helpers ──────────────────────────────────────────────────────────

        private static function cache_key(string $key): string {
            $eid = self::current_escola_id();
            return $eid . '|' . $key;
        }

        private static function current_escola_id(bool $strict = false): int {
            $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
            if ($eid > 0) return $eid;
            if ($strict) return 0;
            // Compatibilidade de leitura em instalações legadas mono-escola.
            // Escritas tenant-scoped usam strict=true e falham fechado.
            return 1;
        }

        private static function current_academic_year(int $eid): int {
            global $wpdb;
            $year = (int) wp_date('Y');
            if (!isset($wpdb) || !is_object($wpdb)) return $year;
            $table = $wpdb->prefix . 'sige_config';
            if (!self::table_exists($table) || !self::column_exists($table, 'ano_lectivo')) return $year;
            $v = $wpdb->get_var($wpdb->prepare("SELECT ano_lectivo FROM `$table` WHERE escola_id=%d LIMIT 1", $eid));
            return $v ? (int)$v : $year;
        }

        public static function table_exists(string $table): bool {
            global $wpdb;
            if (!isset($wpdb) || !is_object($wpdb) || $table === '') return false;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            return (string)$found === $table;
        }

        public static function column_exists(string $table, string $column): bool {
            global $wpdb;
            if (!isset($wpdb) || !is_object($wpdb) || $table === '' || $column === '') return false;
            $found = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $column));
            return !empty($found);
        }

        private static function is_empty_value($value, string $type): bool {
            if ($value === null) return true;
            if ($type === 'smtp' && is_array($value)) return empty($value['host']);
            if (is_string($value)) return trim($value) === '';
            if (is_array($value))  return empty($value);
            return false;
        }

        private static function values_differ($a, $b): bool {
            if (is_array($a) || is_array($b)) return wp_json_encode($a) !== wp_json_encode($b);
            return (string)$a !== (string)$b;
        }
    }
}

// ─── API pública: a partir de v12.10.0 ──────────────────────────────────────

if (!function_exists('sige_config')) {
    /**
     * Helper único de leitura. Substitui múltiplos padrões espalhados.
     *
     * @param string $key Chave canónica (ex: 'escola.nome', 'comunicacao.whatsapp_url').
     * @param mixed  $default
     * @return mixed
     */
    function sige_config(string $key, $default = null) {
        return SIGE_Settings_Repository::get($key, $default);
    }
}

if (!function_exists('sige_config_masked')) {
    function sige_config_masked(string $key, $default = null): string {
        return SIGE_Settings_Repository::get_masked($key, $default);
    }
}

// Compatibilidade retroactiva - helpers da v12.9.x continuam a funcionar.
if (!function_exists('sige_setting_get')) {
    function sige_setting_get(string $key, $default = null) {
        return SIGE_Settings_Repository::get($key, $default);
    }
}
if (!function_exists('sige_setting_effective')) {
    function sige_setting_effective(string $key, $default = null) {
        return SIGE_Settings_Repository::get($key, $default);
    }
}
if (!function_exists('sige_setting_masked')) {
    function sige_setting_masked(string $key, $default = null): string {
        return SIGE_Settings_Repository::get_masked($key, $default);
    }
}
