<?php
/**
 * SIGE SoftGenial - Livro-razao financeiro (Financial Ledger)
 *
 * v12.12.15 (Fase 6, incremento 1). Livro-razao append-only e a prova de
 * adulteracao das operacoes financeiras criticas. Cada entrada e encadeada por
 * HMAC-SHA256 com chave derivada dos salts do WordPress: o hash de cada entrada
 * cobre o seu conteudo canonico mais o hash da entrada anterior. Qualquer
 * alteracao, remocao ou insercao posterior quebra a cadeia e e detectada pelo
 * verificador. Um agente com acesso apenas a base de dados nao tem a chave, logo
 * nao consegue forjar uma cadeia coerente.
 *
 * Disciplina: este modulo SO insere; nunca actualiza nem apaga entradas do ledger.
 */

if (!defined('ABSPATH') && !defined('SIGE_LEDGER_TEST_MODE')) exit;

if (!defined('SIGE_LEDGER_GENESIS')) {
    define('SIGE_LEDGER_GENESIS', str_repeat('0', 64));
}

if (!function_exists('sige_ledger_table_exists')) {
    /** Verifica se a tabela do ledger existe, sem emitir erro cru do WordPress. */
    function sige_ledger_table_exists(): bool {
        static $exists = false;
        if ($exists) return true; // uma vez encontrada, mantem-se
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) return false;
        $t = $wpdb->prefix . 'sige_fin_ledger';
        $prev = method_exists($wpdb, 'suppress_errors') ? $wpdb->suppress_errors(true) : false;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
        if (method_exists($wpdb, 'suppress_errors')) $wpdb->suppress_errors($prev);
        $exists = ($found === $t);
        return $exists;
    }
}

if (!function_exists('sige_ledger_hmac_key')) {
    /** Chave HMAC do ledger, derivada dos salts do WordPress. Nunca vazia. */
    function sige_ledger_hmac_key(): string {
        if (function_exists('wp_salt')) {
            return hash('sha256', wp_salt('auth') . '|' . wp_salt('secure_auth') . '|sige-ledger-v1', true);
        }
        return hash('sha256', 'sige-ledger-fallback', true);
    }
}

if (!function_exists('sige_ledger_canonical')) {
    /** Serializacao canonica e deterministica de uma entrada (conteudo + prev_hash). */
    function sige_ledger_canonical(array $f, string $prev_hash): string {
        $ordered = [
            'escola_id'     => (int) ($f['escola_id'] ?? 0),
            'seq'           => (int) ($f['seq'] ?? 0),
            'event_type'    => (string) ($f['event_type'] ?? ''),
            'entidade'      => (string) ($f['entidade'] ?? ''),
            'entidade_id'   => (int) ($f['entidade_id'] ?? 0),
            'montante'      => (!isset($f['montante']) || $f['montante'] === null) ? null : (string) $f['montante'],
            'actor_user_id' => (int) ($f['actor_user_id'] ?? 0),
            'ocorrido_em'   => (string) ($f['ocorrido_em'] ?? ''),
            'payload'       => (string) ($f['payload'] ?? ''),
            'prev_hash'     => (string) $prev_hash,
        ];
        return function_exists('wp_json_encode')
            ? wp_json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('sige_ledger_compute_hash')) {
    /** HMAC-SHA256 do conteudo canonico de uma entrada. */
    function sige_ledger_compute_hash(array $f, string $prev_hash): string {
        return hash_hmac('sha256', sige_ledger_canonical($f, $prev_hash), sige_ledger_hmac_key());
    }
}

if (!function_exists('sige_ledger_anchor_dir')) {
    /** Directoria da ancora externa (fora da base de dados), em wp-content/uploads. */
    function sige_ledger_anchor_dir(): string {
        $base = '';
        if (function_exists('wp_upload_dir')) {
            $u = wp_upload_dir();
            if (is_array($u) && !empty($u['basedir'])) $base = $u['basedir'];
        }
        if ($base === '') $base = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads' : sys_get_temp_dir();
        return rtrim($base, "/\\") . '/sige-private/ledger';
    }
}

if (!function_exists('sige_ledger_anchor_ensure_dir')) {
    /** Garante a directoria da ancora e os ficheiros de proteccao (negar acesso web). */
    function sige_ledger_anchor_ensure_dir(): bool {
        $dir = sige_ledger_anchor_dir();
        if (!is_dir($dir)) {
            if (function_exists('wp_mkdir_p')) { if (!wp_mkdir_p($dir)) return false; }
            elseif (!@mkdir($dir, 0755, true) && !is_dir($dir)) { return false; }
        }
        $idx = $dir . '/index.php';
        if (!file_exists($idx)) @file_put_contents($idx, "<?php\n// Silence is golden.\n");
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
        return is_dir($dir);
    }
}

if (!function_exists('sige_ledger_anchor_path')) {
    function sige_ledger_anchor_path(int $escola_id): string {
        return sige_ledger_anchor_dir() . '/anchor-' . $escola_id . '.json';
    }
}

if (!function_exists('sige_ledger_anchor_read')) {
    /** Le a ancora de uma escola. Devolve ['seq','hash','count'] ou null se ausente. */
    function sige_ledger_anchor_read(int $escola_id) {
        $path = sige_ledger_anchor_path($escola_id);
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') return null;
        $d = json_decode($raw, true);
        if (!is_array($d) || !isset($d['seq'], $d['hash'])) return null;
        return ['seq' => (int) $d['seq'], 'hash' => (string) $d['hash'], 'count' => (int) ($d['count'] ?? 0)];
    }
}

if (!function_exists('sige_ledger_anchor_write')) {
    /** Escreve a ancora de forma atomica (temp + rename). Best-effort. */
    function sige_ledger_anchor_write(int $escola_id, int $seq, string $hash, int $count): bool {
        if (!sige_ledger_anchor_ensure_dir()) return false;
        $path = sige_ledger_anchor_path($escola_id);
        $data = [
            'escola_id'  => $escola_id,
            'seq'        => $seq,
            'hash'       => $hash,
            'count'      => $count,
            'updated_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ];
        $json = function_exists('wp_json_encode') ? wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : json_encode($data);
        $tmp = $path . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        return true;
    }
}

if (!function_exists('sige_ledger_append')) {
    /**
     * Acrescenta um evento ao livro-razao (append-only). Serializa por escola com
     * bloqueio MySQL para manter o encadeamento consistente. Nunca lanca excepcao
     * para a operacao chamadora: se falhar, devolve false e a operacao financeira
     * (que ja sucedeu) mantem-se consistente.
     */
    function sige_ledger_append(string $event_type, string $entidade, int $entidade_id, $montante, array $payload = [], int $escola_id = 0) {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) return false;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($escola_id <= 0) return false; // fail-closed: nunca atribui a uma escola por omissao
        if (!sige_ledger_table_exists()) return false; // sem tabela, nao regista (e nao emite erro)
        $t = $wpdb->prefix . 'sige_fin_ledger';
        $lock = 'sige_ledger_' . $escola_id;
        $got = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock));
        $result = false;
        try {
            $last = $wpdb->get_row($wpdb->prepare("SELECT seq, hash FROM {$t} WHERE escola_id=%d ORDER BY seq DESC LIMIT 1", $escola_id));
            $seq = $last ? ((int) $last->seq + 1) : 1;
            $prev_hash = $last ? (string) $last->hash : SIGE_LEDGER_GENESIS;
            $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
            $u = ($uid && function_exists('get_userdata')) ? get_userdata($uid) : null;
            $actor_nome = $u ? ($u->display_name ?: $u->user_login) : 'Sistema';
            $ocorrido_em = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
            $montante_norm = ($montante === null) ? null : number_format((float) $montante, 2, '.', '');
            $payload_json = function_exists('wp_json_encode') ? wp_json_encode($payload, JSON_UNESCAPED_UNICODE) : json_encode($payload);
            $fields = [
                'escola_id'     => $escola_id,
                'seq'           => $seq,
                'event_type'    => $event_type,
                'entidade'      => $entidade,
                'entidade_id'   => $entidade_id,
                'montante'      => $montante_norm,
                'actor_user_id' => $uid,
                'ocorrido_em'   => $ocorrido_em,
                'payload'       => $payload_json,
            ];
            $hash = sige_ledger_compute_hash($fields, $prev_hash);
            $row = $fields;
            $row['actor_nome'] = $actor_nome;
            $row['prev_hash'] = $prev_hash;
            $row['hash'] = $hash;
            $ok = $wpdb->insert($t, $row);
            if ($ok) {
                $result = ['ok' => true, 'id' => (int) $wpdb->insert_id, 'seq' => $seq, 'hash' => $hash];
                // Ancora externa (fora da base de dados): regista a nova cabeca da cadeia.
                // Best-effort: uma falha de disco nunca quebra o append nem a operacao.
                sige_ledger_anchor_write($escola_id, $seq, $hash, $seq);
            }
        } catch (\Throwable $e) {
            $result = false;
        } finally {
            if ($got) $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock));
        }
        return $result;
    }
}

if (!function_exists('sige_ledger_append_many')) {
    /**
     * Escreve varios eventos de uma escola num so bloqueio (escrita em bloco).
     * Encadeia a cadeia HMAC em memoria e insere em serie, escrevendo a ancora uma
     * unica vez no fim. Best-effort: nunca lanca para a operacao chamadora. Devolve
     * o numero de eventos efectivamente gravados.
     */
    function sige_ledger_append_many(int $escola_id, array $events): int {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) return 0;
        if ($escola_id <= 0 || empty($events)) return 0;
        if (!sige_ledger_table_exists()) return 0;
        $t = $wpdb->prefix . 'sige_fin_ledger';
        $lock = 'sige_ledger_' . $escola_id;
        $got = $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock));
        $count = 0;
        try {
            $last = $wpdb->get_row($wpdb->prepare("SELECT seq, hash FROM {$t} WHERE escola_id=%d ORDER BY seq DESC LIMIT 1", $escola_id));
            $success_seq = $last ? (int) $last->seq : 0;
            $last_hash = $last ? (string) $last->hash : SIGE_LEDGER_GENESIS;
            $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
            $u = ($uid && function_exists('get_userdata')) ? get_userdata($uid) : null;
            $actor_nome = $u ? ($u->display_name ?: $u->user_login) : 'Sistema';
            foreach ($events as $e) {
                $try_seq = $success_seq + 1;
                $montante = $e['montante'] ?? null;
                $montante_norm = ($montante === null) ? null : number_format((float) $montante, 2, '.', '');
                $payload_json = function_exists('wp_json_encode') ? wp_json_encode($e['payload'] ?? [], JSON_UNESCAPED_UNICODE) : json_encode($e['payload'] ?? []);
                $ocorrido_em = !empty($e['ocorrido_em']) ? (string) $e['ocorrido_em'] : (function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'));
                $fields = [
                    'escola_id'     => $escola_id,
                    'seq'           => $try_seq,
                    'event_type'    => (string) ($e['event_type'] ?? ''),
                    'entidade'      => (string) ($e['entidade'] ?? ''),
                    'entidade_id'   => (int) ($e['entidade_id'] ?? 0),
                    'montante'      => $montante_norm,
                    'actor_user_id' => (int) ($e['actor_user_id'] ?? $uid),
                    'ocorrido_em'   => $ocorrido_em,
                    'payload'       => $payload_json,
                ];
                $hash = sige_ledger_compute_hash($fields, $last_hash);
                $row = $fields;
                $row['actor_nome'] = isset($e['actor_nome']) ? (string) $e['actor_nome'] : $actor_nome;
                $row['prev_hash'] = $last_hash;
                $row['hash'] = $hash;
                if ($wpdb->insert($t, $row)) {
                    $count++;
                    $success_seq = $try_seq;
                    $last_hash = $hash;
                } else {
                    break; // se um insert falhar, a cadeia ate aqui mantem-se valida
                }
            }
            if ($count > 0) sige_ledger_anchor_write($escola_id, $success_seq, $last_hash, $success_seq);
        } catch (\Throwable $ex) {
            // best-effort
        } finally {
            if ($got) $wpdb->get_var($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock));
        }
        return $count;
    }
}

if (!function_exists('sige_ledger_flush_charges')) {
    /** Grava os eventos de cobranca acumulados, agrupados por escola, num so bloqueio cada. */
    function sige_ledger_flush_charges(): void {
        if (empty($GLOBALS['sige_ledger_charge_buffer']) || !is_array($GLOBALS['sige_ledger_charge_buffer'])) return;
        $buf = $GLOBALS['sige_ledger_charge_buffer'];
        $GLOBALS['sige_ledger_charge_buffer'] = [];
        $by_school = [];
        foreach ($buf as $e) {
            $eid = (int) ($e['escola_id'] ?? 0);
            if ($eid > 0) $by_school[$eid][] = $e;
        }
        foreach ($by_school as $eid => $events) {
            sige_ledger_append_many((int) $eid, $events);
        }
    }
}

if (!function_exists('sige_ledger_record_charge')) {
    /**
     * Regista um evento de cobranca (criacao ou alteracao de valor) de forma diferida:
     * acumula em memoria e grava em bloco no fim do pedido (shutdown). Preserva a prova
     * de adulteracao por cobranca sem o custo de um bloqueio por cada uma na geracao em
     * massa. Best-effort.
     */
    function sige_ledger_record_charge(string $event_type, string $entidade, int $entidade_id, $montante, array $payload, int $escola_id): bool {
        if ($escola_id <= 0) return false; // fail-closed: nunca por omissao
        if (!isset($GLOBALS['sige_ledger_charge_buffer']) || !is_array($GLOBALS['sige_ledger_charge_buffer'])) {
            $GLOBALS['sige_ledger_charge_buffer'] = [];
            if (function_exists('add_action')) {
                add_action('shutdown', 'sige_ledger_flush_charges', 99);
            }
        }
        $GLOBALS['sige_ledger_charge_buffer'][] = [
            'event_type'    => $event_type,
            'entidade'      => $entidade,
            'entidade_id'   => $entidade_id,
            'montante'      => ($montante === null) ? null : (float) $montante,
            'payload'       => $payload,
            'escola_id'     => $escola_id,
            'actor_user_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
            'ocorrido_em'   => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ];
        return true;
    }
}

if (!function_exists('sige_ledger_verify')) {
    /**
     * Verifica a cadeia de uma escola. Deteta salto de sequencia (remocao ou
     * insercao), encadeamento quebrado e adulteracao de conteudo. Devolve o
     * estado e a primeira seq afectada.
     */
    function sige_ledger_verify(int $escola_id): array {
        global $wpdb;
        if (!sige_ledger_table_exists()) return ['ok' => true, 'total' => 0, 'broken_seq' => null, 'reason' => ''];
        $t = $wpdb->prefix . 'sige_fin_ledger';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE escola_id=%d ORDER BY seq ASC", $escola_id));
        $total = is_array($rows) ? count($rows) : 0;
        if ($total === 0) return ['ok' => true, 'total' => 0, 'broken_seq' => null, 'reason' => ''];
        $expected_seq = 1;
        $prev = SIGE_LEDGER_GENESIS;
        foreach ($rows as $r) {
            if ((int) $r->seq !== $expected_seq) {
                return ['ok' => false, 'total' => $total, 'broken_seq' => (int) $r->seq, 'reason' => 'salto de sequencia (esperado ' . $expected_seq . ')'];
            }
            if ((string) $r->prev_hash !== (string) $prev) {
                return ['ok' => false, 'total' => $total, 'broken_seq' => (int) $r->seq, 'reason' => 'encadeamento quebrado'];
            }
            $f = [
                'escola_id'     => $r->escola_id,
                'seq'           => $r->seq,
                'event_type'    => $r->event_type,
                'entidade'      => $r->entidade,
                'entidade_id'   => $r->entidade_id,
                'montante'      => $r->montante,
                'actor_user_id' => $r->actor_user_id,
                'ocorrido_em'   => $r->ocorrido_em,
                'payload'       => $r->payload,
            ];
            $calc = sige_ledger_compute_hash($f, (string) $r->prev_hash);
            if (!hash_equals($calc, (string) $r->hash)) {
                return ['ok' => false, 'total' => $total, 'broken_seq' => (int) $r->seq, 'reason' => 'hash nao corresponde (conteudo adulterado)'];
            }
            $prev = (string) $r->hash;
            $expected_seq++;
        }

        // Cadeia internamente integra. Confrontar com a ancora externa (fora da BD)
        // para detectar truncagem da cauda: apagar as entradas mais recentes nao
        // deixa salto de sequencia, mas a ancora regista a cabeca real.
        $db_last_seq  = (int) $rows[$total - 1]->seq;
        $db_last_hash = (string) $rows[$total - 1]->hash;
        $anchor = sige_ledger_anchor_read($escola_id);
        if ($anchor === null) {
            return ['ok' => true, 'total' => $total, 'broken_seq' => null, 'reason' => '', 'anchor' => 'ausente'];
        }
        if ($anchor['seq'] > $db_last_seq) {
            return ['ok' => false, 'total' => $total, 'broken_seq' => $db_last_seq + 1,
                    'reason' => 'truncagem da cauda: a ancora regista ate a seq ' . $anchor['seq'] . ' mas a base de dados so tem ate ' . $db_last_seq,
                    'anchor' => 'a frente'];
        }
        if ($anchor['seq'] === $db_last_seq && !hash_equals((string) $anchor['hash'], $db_last_hash)) {
            return ['ok' => false, 'total' => $total, 'broken_seq' => $db_last_seq,
                    'reason' => 'a ancora nao corresponde a cabeca da cadeia (cabeca adulterada)', 'anchor' => 'divergente'];
        }
        if ($anchor['seq'] < $db_last_seq) {
            // Ancora atrasada (falha de escrita pontual): confirmar o hash no ponto registado.
            foreach ($rows as $r) {
                if ((int) $r->seq === $anchor['seq']) {
                    if (!hash_equals((string) $anchor['hash'], (string) $r->hash)) {
                        return ['ok' => false, 'total' => $total, 'broken_seq' => $anchor['seq'],
                                'reason' => 'a ancora nao corresponde no ponto registado (seq ' . $anchor['seq'] . ')', 'anchor' => 'divergente'];
                    }
                    break;
                }
            }
        }
        return ['ok' => true, 'total' => $total, 'broken_seq' => null, 'reason' => '', 'anchor' => 'confirmada'];
    }
}

if (!function_exists('sige_ledger_schools')) {
    /** Escolas com entradas no ledger. */
    function sige_ledger_schools(): array {
        global $wpdb;
        if (!sige_ledger_table_exists()) return [];
        $t = $wpdb->prefix . 'sige_fin_ledger';
        $ids = $wpdb->get_col("SELECT DISTINCT escola_id FROM {$t} ORDER BY escola_id ASC");
        return array_map('intval', (array) $ids);
    }
}

if (!function_exists('sige_ledger_render_integrity_page')) {
    /** Ecra so de leitura: estado de integridade do ledger por escola. So super admin. */
    function sige_ledger_render_integrity_page(): void {
        if (!function_exists('sige_is_real_wp_admin_user') || !sige_is_real_wp_admin_user()) {
            wp_die('Acesso restrito ao administrador WordPress.', 'Negado', ['response' => 403]);
        }
        echo '<div class="wrap">';
        echo '<h1>Integridade do Ledger financeiro</h1>';
        echo '<p>Verificacao da cadeia imutavel de eventos financeiros criticos. Apenas leitura. '
           . 'A ancora externa (guardada fora da base de dados) deteta a truncagem das entradas mais recentes.</p>';
        if (!sige_ledger_table_exists()) {
            echo '<div class="notice notice-warning inline"><p>A tabela do ledger ainda nao existe nesta instalacao. '
               . 'Abra o painel de administracao como administrador para correr a actualizacao automatica da base de dados, '
               . 'ou reactive o plugin (desactivar e activar) para a criar. Nenhum dado e perdido.</p></div></div>';
            return;
        }
        $schools = sige_ledger_schools();
        if (empty($schools)) {
            echo '<p>Ainda nao ha eventos registados no ledger.</p></div>';
            return;
        }
        $anchor_labels = ['confirmada' => 'Confirmada', 'ausente' => 'Ausente', 'a frente' => 'A frente da BD', 'divergente' => 'Divergente'];
        echo '<table class="widefat striped"><thead><tr><th>Escola</th><th>Eventos</th><th>Estado da cadeia</th><th>Ancora externa</th></tr></thead><tbody>';
        foreach ($schools as $eid) {
            $v = sige_ledger_verify((int) $eid);
            $anchor = $v['anchor'] ?? '';
            if (!empty($v['ok'])) {
                $estado = ($anchor === 'ausente') ? 'Cadeia integra (ancora ainda nao gravada)' : 'Cadeia integra';
            } else {
                $estado = 'QUEBRA na seq ' . (int) $v['broken_seq'] . ' (' . $v['reason'] . ')';
            }
            $anchor_label = $anchor_labels[$anchor] ?? '-';
            echo '<tr><td>' . (int) $eid . '</td><td>' . (int) $v['total'] . '</td><td>' . esc_html($estado) . '</td><td>' . esc_html($anchor_label) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

if (!defined('SIGE_LEDGER_TEST_MODE')) {
    add_action('admin_menu', function () {
        // So o administrador WordPress real ve o ecra de integridade.
        if (!function_exists('sige_is_real_wp_admin_user') || !sige_is_real_wp_admin_user()) return;
        add_options_page(
            'Integridade do Ledger',
            'Integridade do Ledger',
            'manage_options',
            'sige-ledger-integridade',
            'sige_ledger_render_integrity_page'
        );
    });
}
