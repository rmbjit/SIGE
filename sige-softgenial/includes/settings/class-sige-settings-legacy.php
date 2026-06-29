<?php
/**
 * SIGE SoftGenial - Settings Legacy Interceptor
 *
 * Captura as actions AJAX antigas que vivem fora de /includes/settings/ (em
 * db-handler.php, whatsapp-engine.php e email-engine.php) e redirecciona-as
 * para o novo Controller schema-driven.
 *
 * Isto permite que a refundação do módulo Configurações fique 100% contida
 * em /includes/settings/ e /admin/system/config*.php, sem tocar uma linha de
 * código fora deste módulo. Quando os outros módulos forem retrabalhados, as
 * suas funções AJAX antigas podem ser removidas porque já não correm.
 *
 * Estratégia:
 *   1. No hook 'init' com prioridade 99 (depois de todos os módulos terem
 *      registado as suas actions), localizamos e removemos os handlers antigos.
 *   2. Re-registamos a action apontando para o nosso interceptor.
 *   3. O interceptor mapeia $_POST do form antigo para chaves canónicas e
 *      chama SIGE_Settings_Controller::save_array().
 *   4. Devolve resposta JSON no formato esperado pelo JS legacy.
 *
 * @since v12.10.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Legacy')) {

    final class SIGE_Settings_Legacy {

        public static function init(): void {
            // Prioridade 99: depois de db-handler.php, whatsapp-engine.php,
            // email-engine.php, email-queue-templates.php terem registado.
            add_action('init', [__CLASS__, 'replace_legacy_handlers'], 99);
        }

        public static function replace_legacy_handlers(): void {
            // ── Handler legacy gigante de configuração geral ──
            self::replace('wp_ajax_sige_salvar_config_avancado', 'sige_ajax_salvar_config_avancado', [__CLASS__, 'intercept_config_avancado']);

            // ── Handler legacy de WhatsApp/Z-API isolado ──
            self::replace('wp_ajax_sige_salvar_whatsapp_isolado', 'sige_salvar_whatsapp_isolado',     [__CLASS__, 'intercept_whatsapp_isolado']);

            // ── Handler legacy de templates WhatsApp financeiro ──
            self::replace('wp_ajax_sige_salvar_templates_whatsapp_financeiro', 'sige_salvar_templates_whatsapp_financeiro', [__CLASS__, 'intercept_templates_whatsapp']);

            // ── Handler legacy de SMTP ──
            self::replace('wp_ajax_sige_salvar_smtp_config', 'sige_ajax_salvar_smtp_config', [__CLASS__, 'intercept_smtp']);
        }

        /**
         * Substitui um callback registado num hook por outro.
         *
         * O callback antigo pode ser uma string (nome de função) ou um array
         * [classe, método]. Tentamos ambos antes de desistir.
         */
        private static function replace(string $hook, $old_callback, callable $new_callback): void {
            // Tentar remover como função string.
            if (is_string($old_callback)) {
                remove_action($hook, $old_callback);
                // Algumas versões registam com prioridade != 10; varremos as comuns.
                foreach ([10, 0, 1, 5, 20, 50] as $p) remove_action($hook, $old_callback, $p);
            }
            // Tentar remover como método estático.
            if (is_string($old_callback) && function_exists($old_callback) === false) {
                // Nada - já foi tentado acima.
            }
            add_action($hook, $new_callback);
        }

        // ─── Interceptores ────────────────────────────────────────────────────

        /**
         * Substitui sige_ajax_salvar_config_avancado (db-handler.php).
         * Mapeia o $_POST do form antigo para chaves canónicas do novo schema.
         */
        public static function intercept_config_avancado(): void {
            if (function_exists('sige_check_nonce_global')) {
                // Mantém compatibilidade com o nonce que o form legacy envia.
                sige_check_nonce_global();
            }
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
                wp_send_json_error('Negado.');
            }

            $kv = self::map_config_avancado_post();

            $res = SIGE_Settings_Controller::save_array($kv, ['actor' => 'legacy_form_config_avancado']);

            if (!$res['ok']) {
                wp_send_json_error('Não foi possível guardar: ' . implode(' ', $res['errors']));
            }
            // Resposta no formato esperado pelo JS antigo (string genérica).
            wp_send_json_success('Configurações Gravadas com Sucesso!');
        }

        /** Substitui sige_salvar_whatsapp_isolado (whatsapp-engine.php). */
        public static function intercept_whatsapp_isolado(): void {
            if (function_exists('sige_check_nonce_global')) sige_check_nonce_global();
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

            $kv = [];
            if (isset($_POST['api_url']))   $kv['comunicacao.whatsapp_url']   = wp_unslash($_POST['api_url']);
            if (isset($_POST['api_token'])) $kv['comunicacao.whatsapp_token'] = wp_unslash($_POST['api_token']);
            if (isset($_POST['whatsapp_url']))   $kv['comunicacao.whatsapp_url']   = wp_unslash($_POST['whatsapp_url']);
            if (isset($_POST['whatsapp_token'])) $kv['comunicacao.whatsapp_token'] = wp_unslash($_POST['whatsapp_token']);

            $res = SIGE_Settings_Controller::save_array($kv, ['actor' => 'legacy_form_whatsapp_isolado']);
            if (!$res['ok']) wp_send_json_error('Não foi possível guardar: ' . implode(' ', $res['errors']));
            wp_send_json_success('WhatsApp guardado com sucesso.');
        }

        /** Substitui sige_salvar_templates_whatsapp_financeiro (whatsapp-engine.php). */
        public static function intercept_templates_whatsapp(): void {
            if (function_exists('sige_check_nonce_global')) sige_check_nonce_global();
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

            $kv = [];
            if (isset($_POST['msg_nova_fatura'])) $kv['comunicacao.template_mensalidade'] = wp_unslash($_POST['msg_nova_fatura']);
            if (isset($_POST['msg_recibo_pago'])) $kv['comunicacao.template_recibo']      = wp_unslash($_POST['msg_recibo_pago']);
            if (isset($_POST['msg_cobranca']))    $kv['comunicacao.template_cobranca']    = wp_unslash($_POST['msg_cobranca']);

            $res = SIGE_Settings_Controller::save_array($kv, ['actor' => 'legacy_form_templates_whatsapp']);
            if (!$res['ok']) wp_send_json_error('Não foi possível guardar: ' . implode(' ', $res['errors']));
            wp_send_json_success('Templates WhatsApp guardados com sucesso.');
        }

        /** Substitui sige_ajax_salvar_smtp_config (email-engine.php). */
        public static function intercept_smtp(): void {
            if (function_exists('sige_check_nonce_global')) sige_check_nonce_global();
            if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

            $raw = isset($_POST['smtp']) && is_array($_POST['smtp'])
                ? wp_unslash($_POST['smtp'])
                : self::collect_smtp_from_post();

            $kv = ['comunicacao.smtp_config' => $raw];
            $res = SIGE_Settings_Controller::save_array($kv, ['actor' => 'legacy_form_smtp']);
            if (!$res['ok']) wp_send_json_error('Não foi possível guardar: ' . implode(' ', $res['errors']));
            wp_send_json_success('SMTP guardado com sucesso.');
        }

        // ─── Mapas legacy → canónico ──────────────────────────────────────────

        /**
         * Mapa do form legacy (config-view.php antigo, db-handler.php legacy) para
         * chaves canónicas. Campos sem chave canónica equivalente são descartados
         * (são as 25 colunas mortas identificadas no mapeamento - figuras de estilo).
         */
        private static function map_config_avancado_post(): array {
            $p = $_POST;
            $kv = [];

            $assign = function (string $post_key, string $canon) use ($p, &$kv) {
                if (isset($p[$post_key])) $kv[$canon] = wp_unslash($p[$post_key]);
            };

            // Identidade
            $assign('nome_escola',            'escola.nome');
            $assign('codigo_escola',          'escola.codigo_minedh');
            $assign('tipo_instituicao',       'escola.tipo_instituicao');
            $assign('ano_fundacao',           'escola.ano_fundacao');
            $assign('entidade_proprietaria',  'escola.entidade_proprietaria');
            $assign('nuit',                   'escola.nuit');

            // ensino_oferecido pode vir como array ('ensino[]') ou string ('ensino_oferecido')
            if (isset($p['ensino']) && is_array($p['ensino'])) {
                $kv['escola.ensino_oferecido'] = implode(', ', array_map('sanitize_text_field', wp_unslash($p['ensino'])));
            } elseif (isset($p['ensino_oferecido'])) {
                $kv['escola.ensino_oferecido'] = wp_unslash($p['ensino_oferecido']);
            }

            // Localização
            $assign('pais',                'escola.pais');
            $assign('provincia',           'escola.provincia');
            $assign('distrito',            'escola.distrito');
            $assign('cidade',              'escola.cidade');
            $assign('endereco_escola',     'escola.endereco_fisico');
            $assign('telefone_oficial',    'escola.telefone_oficial');
            $assign('email_institucional', 'escola.email_institucional');

            // Direcção
            $assign('director_nome', 'escola.director_nome');
            $assign('cargo_direcao', 'escola.cargo_direccao');

            // Operacional
            $assign('ano_lectivo', 'academico.ano_lectivo');
            $assign('moeda',       'operacao.moeda');

            // Marca/documentos
            $assign('logo_sistema_url',      'documentos.logo_sistema');
            $assign('logo_documentos_url',   'documentos.logo_documentos');
            $assign('cabecalho_oficial_url', 'documentos.cabecalho_oficial');
            $assign('cor_primaria',          'documentos.cor_primaria');
            $assign('rodape_documentos',     'documentos.rodape');

            // WhatsApp
            $assign('whatsapp_url',                  'comunicacao.whatsapp_url');
            $assign('whatsapp_token',                'comunicacao.whatsapp_token');
            $assign('whatsapp_destinatarios_padrao', 'comunicacao.whatsapp_destinatarios');

            // Templates WhatsApp legacy
            $assign('msg_nova_fatura', 'comunicacao.template_mensalidade');
            $assign('msg_recibo_pago', 'comunicacao.template_recibo');
            $assign('msg_cobranca',    'comunicacao.template_cobranca');

            // Módulos: form antigo envia 'modulos[]'
            if (isset($p['modulos']) && is_array($p['modulos'])) {
                $kv['modulos.ativos_locais'] = wp_unslash($p['modulos']);
            }

            // Os restantes campos do form legacy (sistema_avaliacao, nota_minima,
            // nota_maxima, regra_arredondamento, escola_tem_areas, formato_recibo,
            // prefixo_matricula, dados_bancarios, assinatura_director_url,
            // turnos_config_json, feriados_json, escalas_json, tipos_avaliacao_json,
            // tabela_precos_json, data_*_t1/t2/t3, prazo_vencimento, taxa_inscricao,
            // tipo_multa, multa_atraso_percentual, desconto_irmaos, desconto_funcionario,
            // desconto_pronto_pagamento, dias_para_bloqueio, sms_api_key, sms_sender_id,
            // ativar_sms_automatico, template_boas_vindas/atraso/falta/nota) NÃO TÊM
            // chave canónica e são descartados - confirmado no mapeamento como
            // figuras de estilo sem efeito real no plugin.

            return $kv;
        }

        private static function collect_smtp_from_post(): array {
            $p = $_POST;
            return [
                'enabled'    => !empty($p['smtp_enabled'])    ? '1' : '0',
                'host'       => isset($p['smtp_host'])        ? wp_unslash($p['smtp_host'])        : '',
                'port'       => isset($p['smtp_port'])        ? (int) $p['smtp_port']              : 465,
                'encryption' => isset($p['smtp_encryption'])  ? wp_unslash($p['smtp_encryption'])  : 'ssl',
                'auth'       => !empty($p['smtp_auth'])       ? '1' : '0',
                'username'   => isset($p['smtp_username'])    ? wp_unslash($p['smtp_username'])    : '',
                'password'   => isset($p['smtp_password'])    ? (string) $p['smtp_password']       : '',
                'from_email' => isset($p['smtp_from_email'])  ? wp_unslash($p['smtp_from_email'])  : '',
                'from_name'  => isset($p['smtp_from_name'])   ? wp_unslash($p['smtp_from_name'])   : 'SoftGenial',
                'reply_to'   => isset($p['smtp_reply_to'])    ? wp_unslash($p['smtp_reply_to'])    : '',
            ];
        }
    }
}
