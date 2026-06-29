<?php
/**
 * SIGE_Hub_Admin - Página de diagnóstico e controlo do Hub Client.
 *
 * Acessível em: Ferramentas > SoftGenial Hub
 *
 * Responsabilidades:
 *   - Mostrar estado actual do Hub Client (cache, último refresh, último heartbeat)
 *   - Mostrar erros recentes
 *   - Permitir forçar refresh e heartbeat manualmente (sem dependência do WP-Cron)
 *   - Mostrar diagnóstico claro: domínio enviado, endpoints, configuração
 *
 * Sob menu "Ferramentas" (sempre presente em qualquer WP, sem conflito).
 *
 * @since 12.3.1
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Hub_Admin')) {
    final class SIGE_Hub_Admin {

        const SLUG = 'sige-hub-status';

        public static function boot(): void {
            add_action('admin_menu', [__CLASS__, 'menu']);
            add_action('admin_post_sige_hub_force_refresh', [__CLASS__, 'force_refresh']);
            add_action('admin_post_sige_hub_force_heartbeat', [__CLASS__, 'force_heartbeat']);
        }

        public static function menu(): void {
            add_submenu_page(
                'tools.php',
                'SoftGenial Hub - Estado',
                'SoftGenial Hub',
                'manage_options',
                self::SLUG,
                [__CLASS__, 'page']
            );
        }

        // ====================================================================
        // PÁGINA
        // ====================================================================

        public static function page(): void {
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return;

            $domain = function_exists('sige_hub_get_domain') ? sige_hub_get_domain() : '(função não disponível)';
            $validate_url = class_exists('SIGE_Hub_Client') ? SIGE_Hub_Client::resolve_validate_endpoint() : '';
            $heartbeat_url = class_exists('SIGE_Hub_Client') ? SIGE_Hub_Client::resolve_heartbeat_endpoint() : '';
            $updates_url = class_exists('SIGE_Hub_Client') ? SIGE_Hub_Client::resolve_updates_endpoint() : '';
            $api_key = (string) get_option('sige_license_key', '');
            $api_key_masked = $api_key ? mb_substr($api_key, 0, 4) . str_repeat('•', max(0, mb_strlen($api_key) - 8)) . mb_substr($api_key, -4) : '(não configurada)';

            $hub_state = get_option('sige_hub_state', []);
            if (!is_array($hub_state)) $hub_state = [];

            $last_refresh = (int) get_option('sige_hub_last_refresh_attempt', 0);
            $last_refresh_ok = (int) get_option('sige_hub_last_refresh_ok', 0);
            $last_hb_at = (int) get_option('sige_hub_last_heartbeat_at', 0);
            $last_hb_code = (int) get_option('sige_hub_last_heartbeat_code', 0);
            $last_hb_body = (string) get_option('sige_hub_last_heartbeat_body', '');
            $last_hb_error = (string) get_option('sige_hub_last_heartbeat_error', '');

            $notice = isset($_GET['hub_msg']) ? sanitize_text_field((string)$_GET['hub_msg']) : '';
            $error = isset($_GET['hub_err']) ? sanitize_text_field((string)$_GET['hub_err']) : '';

            // Status global
            $is_healthy = !empty($hub_state['features_effective']) && empty($hub_state['last_error']) && $last_hb_code >= 200 && $last_hb_code < 300;
            $status_label = $is_healthy ? 'Ligado' : (!empty($hub_state['last_error']) || $last_hb_code >= 400 ? 'Erro' : 'Aguarda');
            $status_color = $is_healthy ? '#0a6b2a' : (!empty($hub_state['last_error']) || $last_hb_code >= 400 ? '#8a1727' : '#7a5400');
            $status_bg = $is_healthy ? '#d1f5dd' : (!empty($hub_state['last_error']) || $last_hb_code >= 400 ? '#fce0e2' : '#fff4d0');

            ?>
            <div class="wrap">
                <h1>SoftGenial Hub - Estado <small style="font-size:13px;color:#777;font-weight:normal;">v<?php echo esc_html(defined('SIGE_HUB_VERSION') ? SIGE_HUB_VERSION : '1.0.1'); ?></small></h1>

                <?php if ($notice): ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
                <?php endif; ?>

                <div style="display:inline-block;padding:8px 16px;border-radius:999px;font-weight:600;background:<?php echo esc_attr($status_bg); ?>;color:<?php echo esc_attr($status_color); ?>;margin:10px 0 20px 0;">
                    Estado: <?php echo esc_html($status_label); ?>
                </div>

                <h2>Acções manuais</h2>
                <p>Use estes botões para forçar a comunicação com o Hub agora, sem esperar pelo WP-Cron.</p>
                <p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:10px;">
                        <?php wp_nonce_field('sige_hub_force_refresh'); ?>
                        <input type="hidden" name="action" value="sige_hub_force_refresh">
                        <button class="button button-primary">Forçar refresh agora</button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                        <?php wp_nonce_field('sige_hub_force_heartbeat'); ?>
                        <input type="hidden" name="action" value="sige_hub_force_heartbeat">
                        <button class="button">Forçar heartbeat agora</button>
                    </form>
                </p>

                <h2>Configuração detectada</h2>
                <table class="widefat striped" style="max-width:900px;">
                    <tbody>
                        <tr>
                            <th style="width:240px;">Domínio enviado ao Hub</th>
                            <td><code style="font-size:13px;"><?php echo esc_html($domain); ?></code><br><small style="color:#777;">Derivado automaticamente de <code>home_url()</code> - não depende de configuração manual.</small></td>
                        </tr>
                        <tr>
                            <th>Versão do plugin</th>
                            <td><code><?php echo esc_html(defined('SIGE_VERSION') ? SIGE_VERSION : '?'); ?></code></td>
                        </tr>
                        <tr>
                            <th>Endpoint validar</th>
                            <td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html($validate_url ?: '(não definido)'); ?></code></td>
                        </tr>
                        <tr>
                            <th>Endpoint heartbeat</th>
                            <td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html($heartbeat_url ?: '(não definido)'); ?></code></td>
                        </tr>
                        <tr>
                            <th>Endpoint updates</th>
                            <td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html($updates_url ?: '(não definido)'); ?></code></td>
                        </tr>
                        <tr>
                            <th>Chave API</th>
                            <td><code><?php echo esc_html($api_key_masked); ?></code></td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px;">Estado do Hub Client (refresh)</h2>
                <table class="widefat striped" style="max-width:900px;">
                    <tbody>
                        <tr>
                            <th style="width:240px;">Última tentativa</th>
                            <td><?php echo $last_refresh ? esc_html(wp_date('Y-m-d H:i:s', $last_refresh)) . ' (' . esc_html(human_time_diff($last_refresh, time())) . ' atrás)' : '<em>nunca</em>'; ?></td>
                        </tr>
                        <tr>
                            <th>Último sucesso</th>
                            <td><?php echo $last_refresh_ok ? esc_html(wp_date('Y-m-d H:i:s', $last_refresh_ok)) . ' (' . esc_html(human_time_diff($last_refresh_ok, time())) . ' atrás)' : '<em>nunca</em>'; ?></td>
                        </tr>
                        <tr>
                            <th>Último erro</th>
                            <td><?php echo !empty($hub_state['last_error']) ? '<code style="color:#8a1727;">' . esc_html((string)$hub_state['last_error']) . '</code>' : '<em>(nenhum)</em>'; ?></td>
                        </tr>
                        <tr>
                            <th>Features efectivas</th>
                            <td>
                                <?php if (!empty($hub_state['features_effective']) && is_array($hub_state['features_effective'])): ?>
                                    <?php foreach ($hub_state['features_effective'] as $f): ?>
                                        <code style="background:#d1f5dd;color:#0a6b2a;padding:2px 6px;border-radius:4px;margin:2px;display:inline-block;font-size:11px;"><?php echo esc_html((string)$f); ?></code>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <em>(ainda não recebidas)</em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Versão alvo (target)</th>
                            <td><code><?php echo esc_html((string)($hub_state['version_target'] ?? '-')); ?></code></td>
                        </tr>
                        <tr>
                            <th>Versão pinned</th>
                            <td><code><?php echo esc_html((string)($hub_state['version_pinned'] ?? '-')); ?></code></td>
                        </tr>
                        <tr>
                            <th>Canal de rollout</th>
                            <td><code><?php echo esc_html((string)($hub_state['rollout_channel'] ?? 'stable')); ?></code></td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px;">Estado do Heartbeat</h2>
                <table class="widefat striped" style="max-width:900px;">
                    <tbody>
                        <tr>
                            <th style="width:240px;">Último heartbeat</th>
                            <td><?php echo $last_hb_at ? esc_html(wp_date('Y-m-d H:i:s', $last_hb_at)) . ' (' . esc_html(human_time_diff($last_hb_at, time())) . ' atrás)' : '<em>nunca</em>'; ?></td>
                        </tr>
                        <tr>
                            <th>Código HTTP</th>
                            <td><code style="<?php echo $last_hb_code >= 200 && $last_hb_code < 300 ? 'color:#0a6b2a;' : ($last_hb_code >= 400 ? 'color:#8a1727;' : ''); ?>"><?php echo $last_hb_code ? (int)$last_hb_code : '-'; ?></code></td>
                        </tr>
                        <tr>
                            <th>Resposta do Hub</th>
                            <td><?php echo $last_hb_body ? '<pre style="background:#f6f7f7;padding:10px;border-radius:4px;font-size:11px;max-width:600px;overflow:auto;">' . esc_html(mb_substr($last_hb_body, 0, 500)) . '</pre>' : '<em>(sem resposta)</em>'; ?></td>
                        </tr>
                        <tr>
                            <th>Último erro de envio</th>
                            <td><?php echo $last_hb_error ? '<code style="color:#8a1727;">' . esc_html($last_hb_error) . '</code>' : '<em>(nenhum)</em>'; ?></td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top:30px;">Diagnóstico rápido</h2>
                <ul style="line-height:1.8;">
                    <?php
                    $checks = [
                        ['label' => 'Domínio configurado', 'ok' => $domain !== ''],
                        ['label' => 'Endpoint validar definido', 'ok' => $validate_url !== ''],
                        ['label' => 'Chave API configurada', 'ok' => $api_key !== ''],
                        ['label' => 'Hub respondeu pelo menos uma vez (refresh)', 'ok' => $last_refresh_ok > 0],
                        ['label' => 'Hub respondeu pelo menos uma vez (heartbeat)', 'ok' => $last_hb_code >= 200 && $last_hb_code < 300],
                        ['label' => 'Sem erro recente em refresh', 'ok' => empty($hub_state['last_error'])],
                        ['label' => 'Features efectivas recebidas', 'ok' => !empty($hub_state['features_effective'])],
                    ];
                    foreach ($checks as $c) {
                        $icon = $c['ok'] ? '<span style="color:#0a6b2a;">✓</span>' : '<span style="color:#8a1727;">✗</span>';
                        echo '<li>' . $icon . ' ' . esc_html($c['label']) . '</li>';
                    }
                    ?>
                </ul>
            </div>
            <?php
        }

        // ====================================================================
        // HANDLERS DE FORÇAR
        // ====================================================================

        public static function force_refresh(): void {
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_die('Sem permissão.');
            check_admin_referer('sige_hub_force_refresh');

            $msg = '';
            $err = '';
            if (!class_exists('SIGE_Hub_Client')) {
                $err = 'SIGE_Hub_Client não está carregado.';
            } else {
                $result = SIGE_Hub_Client::refresh();
                if (!empty($result['last_error'])) {
                    $err = 'Refresh falhou: ' . $result['last_error'];
                } else {
                    $count = isset($result['features_effective']) && is_array($result['features_effective']) ? count($result['features_effective']) : 0;
                    $msg = "Refresh concluído. {$count} features efectivas recebidas.";
                }
            }

            self::redirect_msg($msg, $err);
        }

        public static function force_heartbeat(): void {
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_die('Sem permissão.');
            check_admin_referer('sige_hub_force_heartbeat');

            $msg = '';
            $err = '';
            if (!class_exists('SIGE_Hub_Heartbeat')) {
                $err = 'SIGE_Hub_Heartbeat não está carregado.';
            } else {
                $result = SIGE_Hub_Heartbeat::send();
                if (empty($result['ok'])) {
                    $err = 'Heartbeat falhou: ' . (isset($result['reason']) ? $result['reason'] : ('HTTP ' . (int)($result['code'] ?? 0)));
                } else {
                    $msg = 'Heartbeat enviado com sucesso (HTTP ' . (int)($result['code'] ?? 200) . ').';
                }
            }

            self::redirect_msg($msg, $err);
        }

        private static function redirect_msg(string $msg, string $err): void {
            $url = admin_url('tools.php?page=' . self::SLUG);
            if ($msg) $url .= '&hub_msg=' . rawurlencode($msg);
            if ($err) $url .= '&hub_err=' . rawurlencode($err);
            wp_safe_redirect($url);
            exit;
        }
    }
}
