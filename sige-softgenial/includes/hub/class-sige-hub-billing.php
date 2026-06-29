<?php
/**
 * SIGE_Hub_Billing - Avisos de cobrança da licença SoftGenial enviados pelo Hub.
 *
 * Objectivo:
 * - mostrar popup/lembrete dentro do SIGE APENAS aos perfis financeiros/decisores
 *   (Director, Admin TI/escola, Tesoureiro, Secretário) - nunca a professores, pais,
 *   alunos, pedagógico, recepção, motoristas, limpeza ou outros perfis operacionais;
 * - o e-mail da licença é enviado pelo Hub para evitar duplicidade;
 * - suspender automaticamente o acesso operacional após a data definida pelo Hub.
 *
 * Hardening 12.9.55 - confidencialidade da cobrança:
 *   A informação de cobrança (referência, valor, prazo de suspensão) é dado sensível
 *   da relação SoftGenial ↔ Direcção. Não pode ser exposta a utilizadores sem mandato
 *   financeiro/administrativo. Whitelist canónica de capabilities, filtrável via
 *   `sige_hub_billing_recipient_caps`. A suspensão automática mantém-se para todos
 *   (a escola está bloqueada para todos), mas com mensagem genérica para perfis não
 *   financeiros (sem ref/valor/prazo) - informação financeira só para quem deve.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Hub_Billing')) {
    final class SIGE_Hub_Billing {
        const OPTION = 'sige_hub_platform_charge_notice';

        public static function boot(): void {
            add_action('admin_init', [__CLASS__, 'enforce_auto_suspension'], 2);
            add_action('admin_notices', [__CLASS__, 'admin_notice']);
            add_action('admin_footer', [__CLASS__, 'render_popup']);
        }

        /**
         * Whitelist canónica de capabilities autorizadas a ver avisos de cobrança da licença.
         *
         * Inclui: Administrador WordPress (admin da escola), Director, Admin TI Escola,
         * Tesoureiro, Secretário e Chefe de Secretaria.
         *
         * Excluídos por desenho - NUNCA recebem o aviso, mesmo que naveguem em ?page=sige-app:
         * pedagógico, professores, educadores, gestor de RH, recepção, motoristas, limpeza,
         * assistentes, encarregados (pais), alunos.
         *
         * @return string[]
         */
        public static function recipient_caps(): array {
            $caps = [
                'manage_options',        // Administrador WordPress (admin da escola / super-admin)
                'sige_director',         // Director de Escola
                'sige_admin_ti',         // Admin TI Escola (admin da escola)
                'sige_financeiro',       // Tesoureiro
                'sige_secretario',       // Secretário
                'sige_secretaria_geral', // Chefe da Secretaria (secretária sénior)
            ];
            $filtered = apply_filters('sige_hub_billing_recipient_caps', $caps);
            if (!is_array($filtered) || !$filtered) return $caps;
            return array_values(array_unique(array_filter(array_map('strval', $filtered))));
        }

        /**
         * O utilizador actual tem mandato para ver avisos/popup de cobrança da licença?
         */
        public static function current_user_can_see_notice(): bool {
            if (!is_user_logged_in()) return false;
            foreach (self::recipient_caps() as $cap) {
                if ($cap !== '' && current_user_can($cap)) return true;
            }
            return false;
        }

        public static function issue(array $payload): array {
            $notice = self::sanitize_payload($payload);
            $notice['status'] = 'pending';
            $notice['received_at'] = current_time('mysql');
            $notice['last_email_at'] = '';
            update_option(self::OPTION, $notice, false);

            $email_result = ['attempted' => false, 'queued' => false, 'to' => ''];
            if (empty($notice['suppress_email']) && (($notice['channel'] ?? 'email') === 'email')) {
                $email_result = self::send_notice_email($notice);
                if (!empty($email_result['queued'])) {
                    $notice['last_email_at'] = current_time('mysql');
                    update_option(self::OPTION, $notice, false);
                }
            }

            return [
                'ok' => true,
                'message' => 'Cobrança da licença SoftGenial recebida pelo cliente.',
                'invoice_ref' => $notice['invoice_ref'],
                'due_date' => $notice['due_date'],
                'suspend_at' => $notice['suspend_at'],
                'email' => $email_result,
            ];
        }

        public static function clear(array $payload = []): array {
            $current = self::get_notice();
            $current['status'] = 'cleared';
            $current['cleared_at'] = current_time('mysql');
            $current['clear_message'] = sanitize_text_field((string)($payload['message'] ?? 'Cobrança regularizada.'));
            update_option(self::OPTION, $current, false);
            return ['ok' => true, 'message' => 'Cobrança da licença regularizada/removida no cliente.'];
        }

        public static function get_notice(): array {
            $notice = get_option(self::OPTION, []);
            return is_array($notice) ? $notice : [];
        }

        public static function has_active_notice(): bool {
            $n = self::get_notice();
            return !empty($n) && (($n['status'] ?? '') === 'pending');
        }

        public static function days_remaining(?array $notice = null): int {
            $notice = $notice ?: self::get_notice();
            return self::days_until((string)($notice['suspend_at'] ?? ''));
        }

        /** Dias até uma data YYYY-MM-DD (positivo = futuro, negativo = passado, 9999 = inválida). */
        public static function days_until(string $ymd): int {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) return 9999;
            try {
                $tz = wp_timezone();
                $today = new DateTimeImmutable(wp_date('Y-m-d'), $tz);
                $deadline = new DateTimeImmutable($ymd, $tz);
                return (int)$today->diff($deadline)->format('%r%a');
            } catch (Throwable $e) {
                return 9999;
            }
        }

        /**
         * Régua cordial faseada para a moldura do aviso (não lidera com a ameaça):
         *  - renew : antes do vencimento → convite à renovação, sem falar em pausa;
         *  - open  : vencido mas pausa ainda longe → lembrete cordial, sem contagem assustadora;
         *  - soon  : pausa a aproximar-se → menciona a data com pedido de regularização;
         *  - final : pausa iminente → pede regularização ou contacto, tom firme mas humano.
         */
        private static function notice_framing(array $n): array {
            $due = (string)($n['due_date'] ?? '');
            $suspend = (string)($n['suspend_at'] ?? '');
            $d_due = self::days_until($due);
            $d_susp = self::days_until($suspend);
            if ($d_due > 0)        $phase = 'renew';
            elseif ($d_susp > 5)   $phase = 'open';
            elseif ($d_susp > 2)   $phase = 'soon';
            else                   $phase = 'final';

            switch ($phase) {
                case 'renew':
                    return [
                        'phase' => 'renew',
                        'title' => 'Renovação da licença',
                        'banner_title' => 'Renovação da licença SoftGenial',
                        'show_suspension' => false,
                        'count_label' => 'Vence em',
                        'count_value' => max(0, $d_due),
                        'count_color' => 'ok',
                        'banner_lead' => 'Enquanto estiver em dia, o seu acesso ao SIGE mantém-se activo.',
                    ];
                case 'open':
                    return [
                        'phase' => 'open',
                        'title' => 'Renovação em aberto',
                        'banner_title' => 'Licença SoftGenial - renovação em aberto',
                        'show_suspension' => false,
                        'count_label' => 'Venceu há',
                        'count_value' => abs($d_due),
                        'count_color' => 'warn',
                        'banner_lead' => 'Para mantermos o acesso activo, agradecemos a regularização. Fale connosco para qualquer questão.',
                    ];
                case 'soon':
                    return [
                        'phase' => 'soon',
                        'title' => 'Renovação em aberto',
                        'banner_title' => 'Licença SoftGenial - renovação em aberto',
                        'show_suspension' => true,
                        'count_label' => 'Dias até à pausa',
                        'count_value' => max(0, $d_susp),
                        'count_color' => 'warn',
                        'banner_lead' => 'Para evitarmos a pausa do acesso, agradecemos a regularização até ' . $suspend . '.',
                    ];
                default:
                    return [
                        'phase' => 'final',
                        'title' => 'Renovação em aberto',
                        'banner_title' => 'Licença SoftGenial - renovação em aberto',
                        'show_suspension' => true,
                        'count_label' => 'Dias até à pausa',
                        'count_value' => max(0, $d_susp),
                        'count_color' => 'danger',
                        'banner_lead' => 'Para evitarmos a pausa do acesso, pedimos o favor de regularizar até ' . $suspend . ' ou de nos contactar para combinarmos um prazo.',
                    ];
            }
        }

        public static function enforce_auto_suspension(): void {
            if (!is_admin()) return;
            $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
            if ($page !== 'sige-app') return;
            if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && isset($_GET['sige_billing_bypass'])) return;
            $notice = self::get_notice();
            if (($notice['status'] ?? '') !== 'pending') return;
            if (self::days_remaining($notice) >= 0) return;

            // Perfis sem mandato financeiro recebem aviso genérico - sem expor ref/valor/prazo.
            // A escola está bloqueada para todos; mas a confidencialidade financeira é mantida.
            if (!self::current_user_can_see_notice()) {
                wp_die(
                    '<div style="max-width:680px;margin:36px auto;font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.6;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:28px;box-shadow:0 20px 60px rgba(15,23,42,.08)">'
                    . '<div style="display:inline-flex;padding:7px 12px;border-radius:999px;background:#f1f5f9;color:#334155;font-weight:800;font-size:12px;letter-spacing:.08em;text-transform:uppercase">SIGE SoftGenial</div>'
                    . '<h1 style="color:#0f172a;margin:16px 0 8px;font-size:28px">Acesso temporariamente indisponível</h1>'
                    . '<p style="font-size:16px;color:#334155">O acesso operacional ao SIGE está temporariamente restrito por motivos administrativos da escola.</p>'
                    . '<p style="color:#475569">Por favor contacte a Direcção, a Administração ou a Tesouraria da escola para mais informações.</p>'
                    . '<p style="color:#64748b">Os dados ficam preservados.</p>'
                    . '</div>',
                    'SIGE SoftGenial - acesso restrito',
                    ['response' => 503]
                );
            }

            $amount = self::format_money((float)($notice['amount'] ?? 0));
            $ref = esc_html((string)($notice['invoice_ref'] ?? ''));
            $suspend_at = esc_html((string)($notice['suspend_at'] ?? ''));
            wp_die(
                '<div style="max-width:760px;margin:36px auto;font-family:system-ui,-apple-system,Segoe UI,sans-serif;line-height:1.6;background:#fff;border:1px solid #fecaca;border-radius:18px;padding:28px;box-shadow:0 20px 60px rgba(15,23,42,.10)">'
                . '<div style="display:inline-flex;padding:7px 12px;border-radius:999px;background:#fff1f2;color:#9f1239;font-weight:800;font-size:12px;letter-spacing:.08em;text-transform:uppercase">SoftGenial Hub</div>'
                . '<h1 style="color:#991b1b;margin:16px 0 8px;font-size:30px">Acesso temporariamente em pausa</h1>'
                . '<p style="font-size:17px;color:#334155">A renovação da licença SoftGenial ficou por regularizar até <strong>'.$suspend_at.'</strong>. Assim que confirmarmos o pagamento, o acesso é reactivado de imediato.</p>'
                . '<p style="font-size:16px;color:#475569">Referência: <strong>'.$ref.'</strong><br>Valor: <strong>'.$amount.'</strong></p>'
                . '<p style="color:#475569">Para regularizar ou combinar um prazo, fale connosco: <strong>info@softgenial.edu.mz</strong>.</p>'
                . '<p style="color:#64748b">Os dados da escola permanecem preservados. Esta pausa apenas restringe o acesso operacional ao SIGE até à regularização.</p>'
                . '</div>',
                'SIGE SoftGenial - renovação pendente',
                ['response' => 402]
            );
        }

        public static function admin_notice(): void {
            if (!self::has_active_notice()) return;
            if (!self::current_user_can_see_notice()) return;
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (!$screen || strpos((string)$screen->id, 'sige') === false) return;
            $n = self::get_notice();
            $f = self::notice_framing($n);
            $amount = self::format_money((float)($n['amount'] ?? 0));
            $ref = (string)($n['invoice_ref'] ?? '');
            $suspend_at = (string)($n['suspend_at'] ?? '');
            $due_date = (string)($n['due_date'] ?? '');
            echo '<div class="sige-hub-bill-inline '.($f['phase'] === 'final' ? 'sige-danger' : '').'">';
            echo '<h3>'.esc_html($f['banner_title']).'</h3>';
            $line = 'Referência <code>'.esc_html($ref).'</code> · Valor <strong>'.esc_html($amount).'</strong>';
            if ($f['phase'] === 'renew') {
                $line .= ' · Vence a <strong>'.esc_html($due_date).'</strong>';
            } else {
                $line .= ' · Limite <strong>'.esc_html($due_date).'</strong>';
                if (!empty($f['show_suspension'])) {
                    $line .= ' · Pausa automática em <strong>'.esc_html($suspend_at).'</strong>';
                }
            }
            echo '<p>'.$line.'. '.esc_html($f['banner_lead']).'</p>';
            echo '</div>';
        }

        public static function render_popup(): void {
            if (!is_admin() || !self::has_active_notice()) return;
            $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
            if ($page !== 'sige-app') return;
            if (!self::current_user_can_see_notice()) return;
            $n = self::get_notice();
            $f = self::notice_framing($n);
            $amount = self::format_money((float)($n['amount'] ?? 0));
            $message = (string)($n['message'] ?? 'Renovação da licença SoftGenial. Enquanto estiver em dia, o seu acesso ao SIGE mantém-se activo.');
            $message = str_replace(['{due_date}','{suspend_at}','{amount}','{invoice_ref}'], [(string)($n['due_date'] ?? ''), (string)($n['suspend_at'] ?? ''), $amount, (string)($n['invoice_ref'] ?? '')], $message);
            ?>
            <style>
            .sige-hub-bill-float{position:fixed;right:22px;bottom:22px;z-index:99999;max-width:390px;background:#fff;border:1px solid #e5e7eb;border-radius:var(--radius-xl);box-shadow:0 24px 80px rgba(15,23,42,.18);font-family:system-ui,-apple-system,Segoe UI,sans-serif;overflow:hidden}.sige-hub-bill-head{padding:18px 20px;background:linear-gradient(135deg,#111827,#1e3a8a);color:#fff}.sige-hub-bill-kicker{font-size:var(--fs-xs);letter-spacing:.12em;text-transform:uppercase;opacity:.8;font-weight:800}.sige-hub-bill-title{font-size:var(--fs-lg);font-weight:900;margin-top:5px}.sige-hub-bill-body{padding:18px 20px;color:#334155}.sige-hub-bill-count{display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);background:#f8fafc;border:1px solid #e5e7eb;border-radius:var(--radius-lg);padding:12px 14px;margin:14px 0}.sige-hub-bill-days{font-size:34px;line-height:1;font-weight:950;color:#1d4ed8}.sige-hub-bill-days.warn{color:#b45309}.sige-hub-bill-days.danger{color:#b91c1c}.sige-hub-bill-meta{font-size:var(--fs-sm);color:#64748b}.sige-hub-bill-actions{display:flex;gap:10px;align-items:center;margin-top:15px}.sige-hub-bill-btn{border:0;border-radius:var(--radius-md);padding:10px 14px;font-weight:800;cursor:pointer}.sige-hub-bill-primary{background:#1d4ed8;color:#fff}.sige-hub-bill-muted{background:#f1f5f9;color:#334155}.sige-hub-bill-close{position:absolute;right:12px;top:10px;background:rgba(255,255,255,.16);border:0;color:#fff;border-radius:var(--radius-pill);width:28px;height:28px;cursor:pointer;font-weight:900}.sige-hub-bill-hidden{display:none!important}@media(max-width:680px){.sige-hub-bill-float{left:14px;right:14px;bottom:14px;max-width:none}}
            </style>
            <div id="sigeHubBillingNotice" class="sige-hub-bill-float">
                <button type="button" class="sige-hub-bill-close" data-sige-act="sigeHubBillSnooze" data-sige-noargs>×</button>
                <div class="sige-hub-bill-head"><div class="sige-hub-bill-kicker">SoftGenial Hub</div><div class="sige-hub-bill-title"><?php echo esc_html($f['title']); ?></div></div>
                <div class="sige-hub-bill-body">
                    <p style="margin:0 0 10px"><?php echo esc_html($message); ?></p>
                    <div class="sige-hub-bill-count">
                        <div><div class="sige-hub-bill-meta"><?php echo esc_html($f['count_label']); ?></div><div class="sige-hub-bill-days <?php echo esc_attr($f['count_color']); ?>"><?php echo esc_html((string)$f['count_value']); ?></div></div>
                        <div class="sige-hub-bill-meta"><strong>Ref.:</strong> <?php echo esc_html((string)($n['invoice_ref'] ?? '')); ?><br><strong>Valor:</strong> <?php echo esc_html($amount); ?><br><strong>Limite:</strong> <?php echo esc_html((string)($n['due_date'] ?? '')); ?><?php if (!empty($f['show_suspension'])): ?><br><strong>Pausa:</strong> <?php echo esc_html((string)($n['suspend_at'] ?? '')); ?><?php endif; ?></div>
                    </div>
                    <div class="sige-hub-bill-actions"><button type="button" class="sige-hub-bill-btn sige-hub-bill-primary" data-sige-act="sigeIrPara" data-sige-arg="<?php echo esc_attr('mailto:info@softgenial.edu.mz?subject=Regularização ' . rawurlencode((string)($n['invoice_ref'] ?? ''))); ?>">Contactar SoftGenial</button><button type="button" class="sige-hub-bill-btn sige-hub-bill-muted" data-sige-act="sigeHubBillSnooze" data-sige-noargs>Lembrar mais tarde</button></div>
                </div>
            </div>
            <script <?php echo sige_csp_script_attr(); ?>>
            (function(){var key='sige_hub_bill_snooze_<?php echo esc_js((string)($n['invoice_ref'] ?? '')); ?>';var until=parseInt(localStorage.getItem(key)||'0',10);if(until>Date.now()){var el=document.getElementById('sigeHubBillingNotice');if(el)el.classList.add('sige-hub-bill-hidden');}window.sigeHubBillSnooze=function(){localStorage.setItem(key,String(Date.now()+6*60*60*1000));var el=document.getElementById('sigeHubBillingNotice');if(el)el.classList.add('sige-hub-bill-hidden');};})();
            </script>
            <?php
        }

        private static function sanitize_payload(array $payload): array {
            $amount = max(0, (float)($payload['amount'] ?? 0));
            $due = sanitize_text_field((string)($payload['due_date'] ?? ''));
            $suspend = sanitize_text_field((string)($payload['suspend_at'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) $due = wp_date('Y-m-d', time() + 7*DAY_IN_SECONDS);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $suspend)) $suspend = wp_date('Y-m-d', time() + 10*DAY_IN_SECONDS);
            return [
                'invoice_ref' => sanitize_text_field((string)($payload['invoice_ref'] ?? ('SG-' . wp_date('Ym')))),
                'amount' => $amount,
                'currency' => sanitize_text_field((string)($payload['currency'] ?? 'MT')),
                'due_date' => $due,
                'suspend_at' => $suspend,
                'school_name' => sanitize_text_field((string)($payload['school_name'] ?? '')),
                'plan' => sanitize_key((string)($payload['plan'] ?? 'completo')),
                'message' => sanitize_textarea_field((string)($payload['message'] ?? '')),
                'channel' => in_array(sanitize_key((string)($payload['channel'] ?? 'internal')), ['email','internal','popup'], true) ? sanitize_key((string)($payload['channel'] ?? 'internal')) : 'internal',
                'suppress_email' => !empty($payload['suppress_email']),
                'contact_email' => sanitize_email((string)($payload['contact_email'] ?? '')),
                'issued_at' => sanitize_text_field((string)($payload['issued_at'] ?? current_time('mysql'))),
            ];
        }

        private static function send_notice_email(array $notice): array {
            $to = sanitize_email((string)($notice['contact_email'] ?? ''));
            if ($to === '') $to = sanitize_email((string)get_option('admin_email', ''));
            if ($to === '') return ['attempted'=>true,'queued'=>false,'to'=>'','error'=>'Email de contacto não definido.'];
            $amount = self::format_money((float)($notice['amount'] ?? 0));
            $subject = 'Renovação da licença SoftGenial - ' . (string)($notice['invoice_ref'] ?? '');
            $plain = "Olá,\n\nÉ tempo de renovar a licença SoftGenial desta escola.\n\nReferência: ".($notice['invoice_ref'] ?? '')."\nValor: ".$amount."\nData limite: ".($notice['due_date'] ?? '')."\nPausa automática (caso não seja regularizada): ".($notice['suspend_at'] ?? '')."\n\nEnquanto estiver em dia, o acesso ao SIGE mantém-se activo. Se precisar de mais prazo, fale connosco - encontramos uma solução em conjunto.\n\nSoftGenial";
            $html = function_exists('sige_email_wrap_html') ? sige_email_wrap_html(nl2br(esc_html($plain)), $subject) : nl2br(esc_html($plain));
            if (function_exists('sige_email_queue_enqueue')) {
                $id = sige_email_queue_enqueue($to, $subject, $html, ['context'=>'hub_platform_charge','priority'=>1]);
                if (function_exists('sige_email_queue_process')) sige_email_queue_process(3);
                return ['attempted'=>true,'queued'=>(bool)$id,'queue_id'=>$id,'to'=>$to];
            }
            $ok = wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
            return ['attempted'=>true,'queued'=>(bool)$ok,'direct'=>true,'to'=>$to];
        }

        private static function format_money(float $amount): string {
            return number_format($amount, 2, ',', '.') . ' MT';
        }
    }
    add_action('plugins_loaded', ['SIGE_Hub_Billing', 'boot'], 20);
}
