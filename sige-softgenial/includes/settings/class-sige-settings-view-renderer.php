<?php
/**
 * SIGE SoftGenial - Settings View Renderer
 *
 * @since v12.10.0
 * @updated v12.10.4 - Centro de Configuração Produto PRO.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_View_Renderer')) {

    final class SIGE_Settings_View_Renderer {

        public static function render_all(): void {
            $groups = SIGE_Settings_Registry::groups();
            $tech_active = SIGE_Settings_Policy::is_technical_mode_active();

            $visible_groups = [];
            foreach ($groups as $gid => $g) {
                if (!empty($g['tech_only']) && !$tech_active) continue;
                if (!self::group_has_visible_keys($gid)) continue;
                $visible_groups[$gid] = $g;
            }

            echo '<div class="sgcc-tabs" role="tablist" aria-label="Áreas de configuração">';
            $first = true;
            foreach ($visible_groups as $gid => $g) {
                $icon = function_exists('sige_ui_icon') ? sige_ui_icon((string)($g['icon'] ?? 'settings')) : '';
                printf(
                    '<button type="button" class="sgcc-tab%s" data-tab="%s" role="tab" aria-selected="%s"><span class="sgcc-tab-icon">%s</span><span class="sgcc-tab-text"><strong>%s</strong><small>%s</small></span></button>',
                    $first ? ' active' : '',
                    esc_attr($gid),
                    $first ? 'true' : 'false',
                    $icon,
                    esc_html((string)($g['label'] ?? $gid)),
                    esc_html((string)($g['description'] ?? ''))
                );
                $first = false;
            }
            echo '</div>';

            $first = true;
            foreach ($visible_groups as $gid => $g) {
                printf('<div id="sgcc-panel-%s" class="sgcc-panel%s" role="tabpanel">', esc_attr($gid), $first ? ' active' : '');
                self::render_group($gid, $g);
                echo '</div>';
                $first = false;
            }
        }

        public static function render_group(string $gid, array $g): void {
            $keys = SIGE_Settings_Registry::by_group($gid);
            $visible_keys = array_filter($keys, function ($meta, $key) {
                return SIGE_Settings_Policy::can_view($key);
            }, ARRAY_FILTER_USE_BOTH);

            if (empty($visible_keys)) return;

            $is_writable_group = self::group_has_writable_keys($gid);
            $icon = function_exists('sige_ui_icon') ? sige_ui_icon((string)($g['icon'] ?? 'settings')) : '';

            echo '<form class="sgcc-form" data-action="sige_settings_save">';
            echo '<input type="hidden" name="nonce" value="' . esc_attr(SIGE_Settings_Controller::nonce_value()) . '">';
            echo '<div class="sgcc-section-head">';
            echo '<span class="sgcc-section-icon">' . $icon . '</span>';
            echo '<span><strong>' . esc_html((string)($g['label'] ?? $gid)) . '</strong><small>' . esc_html((string)($g['description'] ?? '')) . '</small></span>';
            echo '</div>';

            if ($gid === 'aparencia') { self::render_theme_preview(); }

            echo '<div class="sgcc-grid">';
            foreach ($visible_keys as $key => $meta) {
                self::render_field($key, $meta);
                if ($gid === 'comunicacao' && $key === 'comunicacao.whatsapp_token') {
                    self::render_whatsapp_send_test_block();
                }
            }
            echo '</div>';

            if ($is_writable_group) {
                echo '<div class="sgcc-actions">';
                echo '<button type="submit" class="sgcc-btn-primary">Guardar alterações</button>';
                echo '<span class="sgcc-msg" aria-live="polite"></span>';
                echo '</div>';
            } else {
                echo '<div class="sgcc-actions sgcc-actions-readonly">';
                echo '<span class="sgcc-msg ok">Área de consulta.</span>';
                echo '</div>';
            }

            echo '</form>';
        }

        public static function render_field(string $key, array $meta): void {
            $type     = (string)($meta['type'] ?? 'string');
            $label    = (string)($meta['label'] ?? $key);
            $help     = (string)($meta['ui_help'] ?? '');
            $widget   = (string)($meta['ui_widget'] ?? '');
            $required = !empty($meta['required']);
            $readonly = !empty($meta['readonly']);
            $informative = !empty($meta['informative_only']);
            $name     = 'settings[' . esc_attr($key) . ']';
            $can_edit = SIGE_Settings_Policy::can_edit($key);
            $disabled = (!$can_edit || $readonly) ? ' disabled' : '';
            $req      = $required ? ' required' : '';

            $value = SIGE_Settings_Repository::get($key, $meta['default'] ?? '');
            if (is_array($value) || is_object($value)) $value = wp_json_encode($value, JSON_UNESCAPED_UNICODE);
            $value = (string) $value;

            $field_classes = 'sgcc-field';
            if ($type === 'textarea' || $type === 'smtp' || $type === 'json_list') $field_classes .= ' sgcc-field-full';
            if (!$can_edit || $readonly) $field_classes .= ' sgcc-field-readonly';

            echo '<div class="' . esc_attr($field_classes) . '">';
            echo '<label>';
            echo '<span>' . esc_html($label) . '</span>';
            if ($required) echo ' <span class="sgcc-req">*</span>';
            if ($informative) echo ' <span class="sgcc-pill sgcc-pill-info" title="Campo de referência.">perfil</span>';
            if (!empty($meta['controlled_by_hub'])) echo ' <span class="sgcc-pill sgcc-pill-hub" title="Gerido pela central SoftGenial">central</span>';
            if ($readonly && !$informative) echo ' <span class="sgcc-pill sgcc-pill-ro">consulta</span>';
            echo '</label>';

            switch ($type) {
                case 'textarea':
                    $rows = (int)($meta['ui_rows'] ?? 3);
                    echo '<textarea name="' . esc_attr($name) . '" rows="' . $rows . '"' . $disabled . $req . '>'
                        . esc_textarea($value) . '</textarea>';
                    break;

                case 'select':
                    $choices = self::resolve_choices($meta);
                    echo '<select name="' . esc_attr($name) . '"' . $disabled . $req . '>';
                    if (!$required && !isset($choices[''])) {
                        echo '<option value="">-</option>';
                    }
                    foreach ($choices as $k => $lab) {
                        printf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr((string)$k),
                            selected((string)$value, (string)$k, false),
                            esc_html((string)$lab)
                        );
                    }
                    echo '</select>';
                    break;

                case 'csv':
                    $current = array_filter(array_map('trim', explode(',', $value)));
                    $choices = (array)($meta['choices'] ?? []);
                    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" data-csv-target="' . esc_attr($key) . '">';
                    echo '<div class="sgcc-checks" data-csv-for="' . esc_attr($key) . '">';
                    foreach ($choices as $k => $lab) {
                        $checked = in_array((string)$k, $current, true) ? ' checked' : '';
                        echo '<label class="sgcc-check"><input type="checkbox" class="sgcc-csv-check" value="' . esc_attr((string)$k) . '"' . $checked . ($can_edit ? '' : ' disabled') . '><span>' . esc_html((string)$lab) . '</span></label>';
                    }
                    echo '</div>';
                    break;

                case 'json_list':
                    $current = json_decode($value, true);
                    if (!is_array($current)) $current = [];
                    $choices = (array)($meta['choices'] ?? []);
                    $always_on = (array)($meta['always_on'] ?? []);
                    echo '<div class="sgcc-checks sgcc-checks-large">';
                    foreach ($choices as $k => $lab) {
                        $is_always = in_array($k, $always_on, true);
                        $checked = ($is_always || in_array((string)$k, array_map('strval', $current), true)) ? ' checked' : '';
                        $dis = ($is_always || !$can_edit) ? ' disabled' : '';
                        echo '<label class="sgcc-check"><input type="checkbox" name="' . esc_attr($name) . '[]" value="' . esc_attr((string)$k) . '"' . $checked . $dis . '><span>' . esc_html((string)$lab) . '</span></label>';
                        if ($is_always) echo '<input type="hidden" name="' . esc_attr($name) . '[]" value="' . esc_attr((string)$k) . '">';
                    }
                    echo '</div>';
                    break;

                case 'secret':
                    $masked = SIGE_Settings_Repository::get_masked($key, '');
                    echo '<input type="password" name="' . esc_attr($name) . '" value="" autocomplete="new-password" placeholder="' . esc_attr($masked !== '-' ? $masked : '') . '"' . $disabled . '>';
                    if (!$help) $help = 'Deixe vazio para manter o valor actual.';
                    break;

                case 'smtp':
                    $smtp = json_decode($value, true);
                    if (!is_array($smtp)) $smtp = (array) get_option('sige_smtp_config', []);
                    $smtp = array_merge([
                        'enabled' => '0', 'host' => 'smtp.zoho.com', 'port' => 465, 'encryption' => 'ssl',
                        'auth' => '1', 'username' => '', 'from_email' => '', 'from_name' => 'SoftGenial', 'reply_to' => '',
                    ], $smtp);
                    self::render_smtp_block($name, $smtp, $can_edit);
                    self::render_smtp_test_block($smtp, $can_edit);
                    break;

                case 'integer':
                case 'float':
                case 'money':
                    $min = isset($meta['ui_min']) ? ' min="' . esc_attr((string)$meta['ui_min']) . '"' : '';
                    $max = isset($meta['ui_max']) ? ' max="' . esc_attr((string)$meta['ui_max']) . '"' : '';
                    $step = ($type === 'integer') ? ' step="1"' : ' step="0.01"';
                    echo '<input type="number" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $disabled . $req . $min . $max . $step . '>';
                    break;

                case 'color':
                    echo '<div class="sgcc-color-row">';
                    echo '<input type="color" name="' . esc_attr($name) . '" value="' . esc_attr($value !== '' ? $value : '#5b3df5') . '"' . $disabled . $req . '>';
                    echo '<code>' . esc_html($value !== '' ? $value : '#5b3df5') . '</code>';
                    echo '</div>';
                    break;

                case 'date':
                    echo '<input type="date" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $disabled . $req . '>';
                    break;

                case 'boolean':
                    $checked = (string)$value === '1' ? ' checked' : '';
                    echo '<label class="sgcc-bool"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . $checked . $disabled . '><span>Activo</span></label>';
                    break;

                case 'email':
                    echo '<input type="email" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $disabled . $req . '>';
                    break;

                case 'url':
                    if ($widget === 'upload') {
                        $id = 'sgcc-up-' . sanitize_key($key);
                        echo '<div class="sgcc-upload">';
                        echo '<input id="' . esc_attr($id) . '" type="url" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $disabled . $req . '>';
                        echo '<button type="button" class="sgcc-btn-light sgcc-upload-btn" data-target="' . esc_attr($id) . '"' . ($can_edit ? '' : ' disabled') . '>Carregar</button>';
                        echo '</div>';
                    } else {
                        echo '<input type="url" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $disabled . $req . '>';
                    }
                    break;

                case 'string':
                default:
                    if ($readonly && $value === '') $value = '-';
                    echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . $disabled . $req . '>';
                    break;
            }

            if ($help !== '') echo '<small>' . esc_html($help) . '</small>';

            echo '</div>';
        }


        private static function render_smtp_test_block(array $smtp, bool $can_edit): void {
            if (!function_exists('wp_create_nonce')) return;

            $nonce = wp_create_nonce('sige_cfg_global');
            $current_user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
            $default_to = is_object($current_user) && !empty($current_user->user_email) ? (string)$current_user->user_email : (string)get_option('admin_email');
            $last_test = get_option('sige_smtp_last_test', []);
            if (!is_array($last_test) || empty($last_test)) {
                $last_test = get_option('sige_smtp_last_success', []);
            }

            $cfg_plain = function_exists('sige_smtp_get_config') ? sige_smtp_get_config(true) : [];
            $password_needs_reentry = is_array($cfg_plain) && !empty($cfg_plain['password_needs_reentry']);

            echo '<div class="sgcc-smtp-test-card" data-sgcc-smtp-test data-nonce="' . esc_attr($nonce) . '">';
            echo '<div class="sgcc-smtp-test-head">';
            echo '<span class="sgcc-smtp-test-icon">' . (function_exists('sige_ui_icon') ? sige_ui_icon('send') : '') . '</span>';
            echo '<span><strong>Teste de envio SMTP</strong><small>Use este teste para confirmar se as mensagens automáticas do SoftGenial, incluindo senhas e recuperação de acesso, estão a sair pelo SMTP configurado.</small></span>';
            echo '</div>';

            echo '<div class="sgcc-smtp-provider">';
            echo '<span><strong>Fornecedor configurado: Zoho Mail</strong><span>Recomendado para Zoho: <code>smtp.zoho.com</code> com porta <code>465/SSL</code> ou <code>587/TLS</code>, autenticação activa e utilizador igual ao e-mail completo.</span></span>';
            echo '<span class="sgcc-smtp-presets">';
            echo '<button type="button" class="sgcc-smtp-preset" data-sgcc-smtp-preset="zoho_ssl"' . ($can_edit ? '' : ' disabled') . '>Aplicar Zoho 465 SSL</button>';
            echo '<button type="button" class="sgcc-smtp-preset" data-sgcc-smtp-preset="zoho_tls"' . ($can_edit ? '' : ' disabled') . '>Aplicar Zoho 587 TLS</button>';
            echo '</span>';
            echo '</div>';

            if ($password_needs_reentry) {
                echo '<div class="sgcc-smtp-test-last warn"><strong>Atenção:</strong> a palavra-passe SMTP gravada não está legível. Reintroduza a palavra-passe do Zoho no campo “Nova palavra-passe” e clique em “Enviar teste agora”.</div>';
            }

            if (is_array($last_test) && !empty($last_test['time'])) {
                $ok = !empty($last_test['ok']) || (!isset($last_test['ok']) && !empty($last_test['to']));
                echo '<div class="sgcc-smtp-test-last ' . ($ok ? 'ok' : 'err') . '">';
                echo '<strong>Último teste:</strong> ' . esc_html($ok ? 'sucesso' : 'falhou') . ' · ';
                echo esc_html((string)($last_test['time'] ?? '')) . ' · Para ' . esc_html((string)($last_test['to'] ?? ''));
                if (!empty($last_test['host'])) echo ' · ' . esc_html((string)$last_test['host']) . ':' . esc_html((string)($last_test['port'] ?? ''));
                if (!empty($last_test['message'])) echo ' · ' . esc_html((string)$last_test['message']);
                echo '</div>';
            }

            echo '<div class="sgcc-smtp-test-grid">';
            echo '<label><span>E-mail para teste</span><input type="email" class="sgcc-smtp-test-to" value="' . esc_attr($default_to) . '" placeholder="exemplo@dominio.com" autocomplete="email"></label>';
            echo '<button type="button" class="sgcc-btn-primary sgcc-smtp-test-btn"' . ($can_edit ? '' : ' disabled') . '>Enviar teste agora</button>';
            echo '</div>';
            echo '<div class="sgcc-smtp-test-result" aria-live="polite"></div>';
            echo '<div class="sgcc-smtp-deliverability"><b>Sobre SPAM</b>Este teste confirma se o servidor aceitou o envio. A entrega na caixa principal depende também da reputação do domínio, SPF, DKIM, DMARC, alinhamento do remetente e conteúdo da mensagem. Para Zoho, mantenha o e-mail remetente no mesmo domínio autenticado.</div>';
            echo '</div>';
        }



        private static function render_whatsapp_send_test_block(): void {
            if (!function_exists('wp_create_nonce')) return;

            $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
            $last_test = get_option('sige_wpp_last_send_test_' . $eid, []);
            $nonce = wp_create_nonce('sige_wpp_diag');

            echo '<div class="sgcc-field sgcc-field-full sgcc-wpp-test-card" data-sgcc-whatsapp-test data-nonce="' . esc_attr($nonce) . '">';
            echo '<div class="sgcc-wpp-test-head">';
            echo '<span class="sgcc-wpp-test-icon">' . (function_exists('sige_ui_icon') ? sige_ui_icon('send') : '') . '</span>';
            echo '<span><strong>Teste de envio WhatsApp</strong><small>Use depois de guardar a URL Z-API e o Token. O teste envia uma mensagem controlada para confirmar se a configuração activa está funcional.</small></span>';
            echo '</div>';

            if (is_array($last_test) && !empty($last_test['sent_at'])) {
                $ok = !empty($last_test['ok']);
                echo '<div class="sgcc-wpp-test-last ' . ($ok ? 'ok' : 'err') . '">';
                echo '<strong>Último teste:</strong> ' . esc_html($ok ? 'sucesso' : 'falhou') . ' · ';
                echo esc_html((string)($last_test['sent_at'] ?? '')) . ' · Nº ' . esc_html((string)($last_test['phone_mask'] ?? '')) . ' · HTTP ' . esc_html((string)($last_test['http'] ?? ''));
                if (!empty($last_test['message_id'])) echo ' · ID ' . esc_html((string)$last_test['message_id']);
                echo '</div>';
            }

            echo '<div class="sgcc-wpp-test-grid">';
            echo '<label><span>Número WhatsApp de teste</span><input type="text" class="sgcc-wpp-test-number" placeholder="Ex.: 25884xxxxxxx" autocomplete="off"></label>';
            echo '<label><span>Mensagem opcional</span><textarea class="sgcc-wpp-test-message" rows="2" placeholder="Deixe vazio para usar a mensagem padrão de teste."></textarea></label>';
            echo '<button type="button" class="sgcc-btn-primary sgcc-wpp-test-btn">Enviar teste agora</button>';
            echo '</div>';
            echo '<div class="sgcc-wpp-test-result" aria-live="polite"></div>';
            echo '<small>Este teste não cria cobrança, não cria recibo, não altera mensalidades e não mexe na fila normal. A confirmação final é verificar se a mensagem chegou ao telefone informado.</small>';
            echo '</div>';
        }

        private static function render_theme_preview(): void {
            $palette = function_exists('sige_theme_current_palette') ? sige_theme_current_palette() : [
                'mode' => 'softgenial', 'primary' => '#5a3fd6', 'secondary' => '#3f2c9f', 'accent' => '#34a853', 'soft' => '#f1edff'
            ];
            $primary = esc_attr((string)($palette['primary'] ?? '#5a3fd6'));
            $secondary = esc_attr((string)($palette['secondary'] ?? '#3f2c9f'));
            $accent = esc_attr((string)($palette['accent'] ?? '#34a853'));
            $soft = esc_attr((string)($palette['soft'] ?? '#f1edff'));
            echo '<div class="sgcc-theme-preview" style="--preview-primary:' . $primary . ';--preview-secondary:' . $secondary . ';--preview-accent:' . $accent . ';--preview-soft:' . $soft . ';">';
            echo '<div class="sgcc-theme-preview-side"><span></span><b>SoftGenial</b><small>Painel Principal</small><i></i><i></i><i></i></div>';
            echo '<div class="sgcc-theme-preview-main">';
            echo '<div class="sgcc-theme-preview-hero"><span>Pré-visualização</span><strong>Visual da escola</strong><small>Veja como as cores ficam no sistema e nos documentos antes de guardar.</small><button type="button">Acção principal</button></div>';
            echo '<div class="sgcc-theme-preview-cards"><span></span><span></span><span></span></div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="sgcc-theme-tools"><button type="button" class="sgcc-btn-light" id="sgcc-theme-reset">Restaurar padrão SoftGenial</button><span>As alterações só são aplicadas definitivamente depois de guardar.</span></div>';
            echo '<div class="sgcc-theme-note"><strong>Cores da aplicação</strong><span>Esta área altera menu, botões, cartões, destaques e também sincroniza a cor dos recibos e documentos. Em Marca e documentos pode ajustar logótipos, cabeçalho, rodapé e, se necessário, afinar a cor documental.</span></div>';
        }

        private static function render_smtp_block(string $name_prefix, array $smtp, bool $can_edit): void {
            $disabled = $can_edit ? '' : ' disabled';
            $base = rtrim($name_prefix, ']');
            $n = function ($field) use ($base) { return $base . '][' . $field . ']'; };

            echo '<div class="sgcc-smtp">';
            echo '<div class="sgcc-grid">';

            echo '<div class="sgcc-field"><label><span>Estado do envio</span></label><select name="' . esc_attr($n('enabled')) . '"' . $disabled . '>';
            foreach (['0' => 'Inactivo', '1' => 'Activo'] as $k => $v) printf('<option value="%s" %s>%s</option>', esc_attr($k), selected((string)$smtp['enabled'], $k, false), esc_html($v));
            echo '</select></div>';

            echo '<div class="sgcc-field"><label><span>Servidor SMTP</span></label><input type="text" name="' . esc_attr($n('host')) . '" value="' . esc_attr((string)$smtp['host']) . '"' . $disabled . '></div>';
            echo '<div class="sgcc-field"><label><span>Porta</span></label><input type="number" min="1" max="65535" name="' . esc_attr($n('port')) . '" value="' . esc_attr((string)$smtp['port']) . '"' . $disabled . '></div>';

            echo '<div class="sgcc-field"><label><span>Segurança</span></label><select name="' . esc_attr($n('encryption')) . '"' . $disabled . '>';
            foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'Sem encriptação'] as $k => $v) printf('<option value="%s" %s>%s</option>', esc_attr($k), selected((string)$smtp['encryption'], $k, false), esc_html($v));
            echo '</select></div>';

            echo '<div class="sgcc-field"><label><span>Autenticação</span></label><select name="' . esc_attr($n('auth')) . '"' . $disabled . '>';
            foreach (['1' => 'Sim', '0' => 'Não'] as $k => $v) printf('<option value="%s" %s>%s</option>', esc_attr($k), selected((string)$smtp['auth'], $k, false), esc_html($v));
            echo '</select></div>';

            echo '<div class="sgcc-field"><label><span>Utilizador</span></label><input type="text" name="' . esc_attr($n('username')) . '" value="' . esc_attr((string)$smtp['username']) . '"' . $disabled . '></div>';

            echo '<div class="sgcc-field"><label><span>Nova palavra-passe</span> <span class="sgcc-pill sgcc-pill-info">opcional</span></label><input type="password" name="' . esc_attr($n('password')) . '" value="" autocomplete="new-password"' . $disabled . '></div>';

            echo '<div class="sgcc-field"><label><span>Email remetente</span></label><input type="email" name="' . esc_attr($n('from_email')) . '" value="' . esc_attr((string)$smtp['from_email']) . '"' . $disabled . '></div>';
            echo '<div class="sgcc-field"><label><span>Nome remetente</span></label><input type="text" name="' . esc_attr($n('from_name')) . '" value="' . esc_attr((string)$smtp['from_name']) . '"' . $disabled . '></div>';
            echo '<div class="sgcc-field"><label><span>Responder para</span></label><input type="email" name="' . esc_attr($n('reply_to')) . '" value="' . esc_attr((string)$smtp['reply_to']) . '"' . $disabled . '></div>';

            echo '</div></div>';
        }

        private static function resolve_choices(array $meta): array {
            $choices = (array)($meta['choices'] ?? []);
            $cb = (string)($meta['choices_callback'] ?? '');
            if ($cb !== '' && function_exists($cb)) {
                $dyn = call_user_func($cb);
                if (is_array($dyn)) return $dyn;
            }
            return $choices;
        }

        private static function group_has_visible_keys(string $gid): bool {
            foreach (SIGE_Settings_Registry::by_group($gid) as $key => $meta) {
                if (SIGE_Settings_Policy::can_view($key)) return true;
            }
            return false;
        }

        private static function group_has_writable_keys(string $gid): bool {
            foreach (SIGE_Settings_Registry::by_group($gid) as $key => $meta) {
                if (SIGE_Settings_Policy::can_edit($key)) return true;
            }
            return false;
        }
    }
}
