<?php
/**
 * SIGE SoftGenial - Saúde Operacional (cartão na página Saúde do Sistema)
 *
 * Mostra, escola a escola, o que o heartbeat já envia ao Hub: filas
 * WhatsApp/email, próximos crons, bloqueios do escudo de login, pendentes
 * M-Pesa e veredicto do piso PHP 8.1. E expõe os INTERRUPTORES das
 * funcionalidades dos Sprints 1-2 que até agora só se ligavam por CLI:
 * 2FA por email, relatório mensal automático (com destinatários) e a hora
 * de corte das presenças.
 *
 * Toque mínimo no monólito: a view core-status chama UMA linha
 * (sige_saude_operacional_card()); todo o resto vive aqui.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_saude_operacional_dados')) {
    function sige_saude_operacional_dados(): array {
        global $wpdb;
        $existe = static function (string $t) use ($wpdb): bool {
            return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
        };
        $conta = static function (string $t, string $where, array $args = []) use ($wpdb, $existe): ?int {
            if (!$existe($t)) return null;
            $sql = "SELECT COUNT(*) FROM {$t} WHERE {$where}";
            $v = $args ? $wpdb->get_var($wpdb->prepare($sql, $args)) : $wpdb->get_var($sql);
            return is_numeric($v) ? (int)$v : null;
        };
        $cron = static function (string $hook): string {
            $ts = wp_next_scheduled($hook);
            if (!$ts) return 'não agendado';
            $delta = $ts - time();
            if ($delta < 0) return 'EM ATRASO (' . human_time_diff($ts) . ')';
            return 'daqui a ' . human_time_diff(time(), $ts);
        };
        $corte24 = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        $tE = $wpdb->prefix . 'sige_email_queue';
        $tX = $wpdb->prefix . 'sige_mpesa_transacoes';
        $tA = $wpdb->prefix . 'sige_auditoria';

        $locks = null;
        if ($existe($tA)) {
            $col_data = null;
            foreach (['criado_em', 'data_hora'] as $c) {
                if ($wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$tA}` LIKE %s", $c))) { $col_data = $c; break; }
            }
            $col_det = null;
            foreach (['detalhes', 'contexto'] as $c) {
                if ($wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$tA}` LIKE %s", $c))) { $col_det = $c; break; }
            }
            if ($col_data && $col_det) {
                $locks = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tA} WHERE {$col_det} LIKE %s AND {$col_data} >= %s",
                    '%login_shield%lock%', $corte24
                ));
            }
        }

        return [
            'wpp_pendentes'   => $conta($tQ, "status = 'pendente'"),
            'wpp_falhas_24h'  => $conta($tQ, "status IN ('falhado','erro','falhada') AND criado_em >= %s", [$corte24]),
            'email_pendentes' => $conta($tE, "status = 'pendente'"),
            'mpesa_pendentes' => $conta($tX, "estado = 'pendente_manual'"),
            'cron_wpp'        => $cron('sige_processar_whatsapp_queue'),
            'cron_diario'     => $cron('sige_evento_diario'),
            'login_locks_24h' => $locks,
            'php_81_ok'       => version_compare(PHP_VERSION, '8.1.0', '>='),
        ];
    }
}

if (!function_exists('sige_saude_toggles_pode')) {
    function sige_saude_toggles_pode(): bool {
        if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) return true;
        return current_user_can('sige_director');
    }
}

if (!function_exists('sige_saude_operacional_card')) {
    /** Imprime o cartão completo. Chamar dentro da grelha da view core-status. */
    function sige_saude_operacional_card(): void {
        $d = sige_saude_operacional_dados();
        $fmt = static fn($v) => $v === null ? 'n/d' : (string)(int)$v;
        $pode = sige_saude_toggles_pode();
        $ok = isset($_GET['saude']) && $_GET['saude'] === 'ok';
        $rel_dest = (string) get_option('sige_relatorio_mensal_destinatarios', '');
        ?>
        <section class="sg-core-card">
            <h2>🩺 Saúde operacional</h2>
            <dl class="sg-core-dl">
                <dt>Fila WhatsApp</dt><dd><?php echo esc_html($fmt($d['wpp_pendentes'])); ?> pendente(s)</dd>
                <dt>Falhas WhatsApp 24h</dt><dd><?php $f = $d['wpp_falhas_24h']; echo $f === null ? 'n/d' : ($f > 0 ? '<span class="warn">' . (int)$f . '</span>' : '<span class="ok">0</span>'); ?></dd>
                <dt>Fila Email</dt><dd><?php echo esc_html($fmt($d['email_pendentes'])); ?> pendente(s)</dd>
                <dt>M-Pesa pendentes</dt><dd><?php $m = $d['mpesa_pendentes']; echo $m === null ? 'n/d' : ($m > 0 ? '<span class="warn">' . (int)$m . '</span>' : '<span class="ok">0</span>'); ?></dd>
                <dt>Cron WhatsApp</dt><dd><?php echo esc_html($d['cron_wpp']); ?></dd>
                <dt>Cron diário</dt><dd><?php echo esc_html($d['cron_diario']); ?></dd>
                <dt>Bloqueios login 24h</dt><dd><?php echo esc_html($fmt($d['login_locks_24h'])); ?></dd>
                <dt>Piso PHP 8.1</dt><dd><?php echo $d['php_81_ok'] ? '<span class="ok">PRONTO (' . esc_html(PHP_VERSION) . ')</span>' : '<span class="warn">subir PHP (' . esc_html(PHP_VERSION) . ')</span>'; ?></dd>
            </dl>

            <?php if ($ok): ?>
            <div class="sg-core-note" style="border-left:3px solid #16a34a;">✅ Interruptores guardados.</div>
            <?php endif; ?>

            <?php if ($pode): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:14px;display:grid;gap:10px;">
                <input type="hidden" name="action" value="sige_saude_toggles">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sige_saude_toggles')); ?>">
                <strong style="color:inherit;">Interruptores</strong>
                <label style="display:flex;gap:8px;align-items:center;">
                    <input type="checkbox" name="t_2fa" value="1" <?php checked(get_option('sige_2fa_email', 'off'), 'on'); ?>>
                    Verificação em dois passos por email (Director e Admin TI)
                </label>
                <label style="display:flex;gap:8px;align-items:center;">
                    <input type="checkbox" name="t_rel" value="1" <?php checked(get_option('sige_relatorio_mensal_email', 'off'), 'on'); ?>>
                    Relatório mensal automático à Direcção (dia 1)
                </label>
                <label style="display:grid;gap:4px;font-size:12.5px;">
                    Destinatários do relatório (separados por vírgula; vazio = email do administrador)
                    <input type="text" name="rel_dest" value="<?php echo esc_attr($rel_dest); ?>" placeholder="director@escola.mz, geral@escola.mz" style="padding:7px 9px;border-radius:7px;border:1px solid #cbd5e1;">
                </label>
                <label style="display:grid;gap:4px;font-size:12.5px;max-width:220px;">
                    Hora de corte do atraso (Presenças)
                    <input type="time" name="hora_corte" value="<?php echo esc_attr(function_exists('sige_presencas_hora_corte') ? sige_presencas_hora_corte() : '07:30'); ?>" style="padding:7px 9px;border-radius:7px;border:1px solid #cbd5e1;">
                </label>
                <button type="submit" class="sg-core-btn" style="justify-self:start;">Guardar interruptores</button>
            </form>
            <?php endif; ?>
        </section>
        <?php
    }
}

// ── Gravação dos interruptores ───────────────────────────────────────────────
add_action('admin_post_sige_saude_toggles', function () {
    if (!sige_saude_toggles_pode()) wp_die('Sem permissão para alterar os interruptores do sistema.');
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_saude_toggles')) {
        wp_die('A sessão expirou por segurança. Volte atrás, recarregue a página e tente novamente.');
    }
    update_option('sige_2fa_email', !empty($_POST['t_2fa']) ? 'on' : 'off', false);
    update_option('sige_relatorio_mensal_email', !empty($_POST['t_rel']) ? 'on' : 'off', false);

    $dest_raw = (string) wp_unslash($_POST['rel_dest'] ?? '');
    $dest = array_filter(array_map('sanitize_email', array_map('trim', explode(',', $dest_raw))));
    update_option('sige_relatorio_mensal_destinatarios', implode(', ', $dest), false);

    $hora = sanitize_text_field((string)($_POST['hora_corte'] ?? ''));
    if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
        update_option('sige_presencas_hora_corte', $hora, false);
    }

    if (function_exists('sige_security_log')) {
        sige_security_log('saude_toggles', sprintf(
            '2fa=%s rel=%s dest=%d corte=%s user=%d',
            !empty($_POST['t_2fa']) ? 'on' : 'off',
            !empty($_POST['t_rel']) ? 'on' : 'off',
            count($dest), $hora, get_current_user_id()
        ));
    }
    wp_safe_redirect(add_query_arg(['page' => 'sige-app', 'view' => 'sige_core_status', 'saude' => 'ok'], admin_url('admin.php')));
    exit;
});
