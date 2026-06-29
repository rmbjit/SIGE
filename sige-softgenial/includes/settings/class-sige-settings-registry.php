<?php
/**
 * SIGE SoftGenial - Settings Registry
 *
 * Schema declarativo único. Adicionar configuração = adicionar 1 linha.
 * Chaves canónicas: <dominio>.<atributo>
 *
 * @since v12.10.0
 * @updated v12.10.0.1 - marcação informative_only para campos preservados
 *                       sem consumidor operacional actual + ui_help enxuto.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Settings_Registry')) {

    final class SIGE_Settings_Registry {

        private static $cache = null;

        public static function all(): array {
            if (self::$cache !== null) return self::$cache;
            $items = [];
            self::register_escola($items);
            self::register_documentos($items);
            self::register_aparencia($items);
            self::register_academico($items);
            self::register_operacao($items);
            self::register_comunicacao($items);
            self::register_smtp($items);
            self::register_modulos($items);
            self::register_diagnostico($items);
            self::register_financeiro_readonly($items);
            ksort($items);
            self::$cache = $items;
            return $items;
        }

        public static function get(string $key): ?array {
            $all = self::all();
            return $all[$key] ?? null;
        }

        public static function by_group(string $group): array {
            $out = [];
            foreach (self::all() as $key => $meta) {
                if (($meta['ui_group'] ?? '') === $group) $out[$key] = $meta;
            }
            uasort($out, function ($a, $b) {
                return (int)($a['ui_order'] ?? 999) <=> (int)($b['ui_order'] ?? 999);
            });
            return $out;
        }

        public static function groups(): array {
            return [
                'identidade'  => ['label' => 'Dados da escola',        'order' => 10, 'tech_only' => false, 'icon' => 'school',   'description' => 'Nome, código, NUIT e perfil institucional da escola.'],
                'localizacao' => ['label' => 'Contactos e localização','order' => 20, 'tech_only' => false, 'icon' => 'pin',      'description' => 'Endereço, província, distrito, telefone e email institucional.'],
                'direccao'    => ['label' => 'Direcção',               'order' => 30, 'tech_only' => false, 'icon' => 'users',    'description' => 'Dados da direcção que aparecem em documentos e comunicações.'],
                'documentos'  => ['label' => 'Marca e documentos',     'order' => 40, 'tech_only' => false, 'icon' => 'file',     'description' => 'Logotipos, cabeçalho oficial, cor dos documentos e rodapé.'],
                'aparencia'   => ['label' => 'Aparência da escola',    'order' => 45, 'tech_only' => false, 'icon' => 'palette',  'description' => 'Cores da aplicação e dos documentos, com pré-visualização segura.'],
                'academico'   => ['label' => 'Ano lectivo',            'order' => 50, 'tech_only' => false, 'icon' => 'calendar', 'description' => 'Ano lectivo activo usado na operação diária da escola.'],
                'operacao'    => ['label' => 'Preferências',           'order' => 60, 'tech_only' => false, 'icon' => 'settings', 'description' => 'Preferências gerais usadas pela escola no funcionamento do sistema.'],
                'comunicacao' => ['label' => 'Comunicação',            'order' => 70, 'tech_only' => true,  'icon' => 'message',  'description' => 'Canais de envio, templates e email institucional.'],
                'modulos'     => ['label' => 'Módulos activos',        'order' => 80, 'tech_only' => true,  'icon' => 'grid',     'description' => 'Áreas activas para esta escola, de acordo com o pacote contratado.'],
                'diagnostico' => ['label' => 'Estado do sistema',      'order' => 90, 'tech_only' => true,  'icon' => 'shield',   'description' => 'Informação de estado para acompanhamento seguro pelo suporte SoftGenial.'],
            ];
        }

        public static function reset_cache(): void { self::$cache = null; }

        // ─── Domínios ──────────────────────────────────────────────────────────

        private static function register_escola(array &$items): void {
            // Identidade - campos consumidos por motores reais
            self::add_sige_config($items, 'escola.nome',                  'Nome da escola',          'nome_escola',           'string', '', ['ui_group' => 'identidade', 'ui_order' => 10, 'required' => true]);
            self::add_sige_config($items, 'escola.codigo_minedh',         'Código MINEDH',           'codigo_escola',         'string', '', ['ui_group' => 'identidade', 'ui_order' => 20]);
            self::add_sige_config($items, 'escola.nuit',                  'NUIT',                    'nuit',                  'string', '', ['ui_group' => 'identidade', 'ui_order' => 30]);

            // Identidade - campos preservados, sem consumidor operacional actual (opção B)
            self::add_sige_config($items, 'escola.tipo_instituicao',      'Tipo de instituição',     'tipo_instituicao',      'select', '', ['ui_group' => 'identidade', 'ui_order' => 40, 'informative_only' => true, 'choices' => ['Privada' => 'Privada', 'Pública' => 'Pública', 'Comunitária' => 'Comunitária', 'Outra' => 'Outra']]);
            self::add_sige_config($items, 'escola.ensino_oferecido',      'Níveis de ensino',        'ensino_oferecido',      'csv',    '', ['ui_group' => 'identidade', 'ui_order' => 50, 'informative_only' => true, 'choices' => ['Jardim' => 'Pré-escolar', 'Primario' => 'Ensino primário', 'ESG1' => 'ESG1', 'ESG2' => 'ESG2']]);
            self::add_sige_config($items, 'escola.entidade_proprietaria', 'Entidade proprietária',   'entidade_proprietaria', 'string', '', ['ui_group' => 'identidade', 'ui_order' => 60, 'informative_only' => true]);
            self::add_sige_config($items, 'escola.ano_fundacao',          'Ano de fundação',         'ano_fundacao',          'string', '', ['ui_group' => 'identidade', 'ui_order' => 70, 'informative_only' => true]);

            // Localização - consumidos por documents-engine, passagem-docs
            self::add_sige_config($items, 'escola.pais',                  'País',                    'pais',                  'string', 'Moçambique', ['ui_group' => 'localizacao', 'ui_order' => 10]);
            self::add_sige_config($items, 'escola.provincia',             'Província',               'provincia',             'string', '', ['ui_group' => 'localizacao', 'ui_order' => 20]);
            self::add_sige_config($items, 'escola.distrito',              'Distrito',                'distrito',              'string', '', ['ui_group' => 'localizacao', 'ui_order' => 30]);
            self::add_sige_config($items, 'escola.cidade',                'Cidade',                  'cidade',                'string', '', ['ui_group' => 'localizacao', 'ui_order' => 40]);
            self::add_sige_config($items, 'escola.endereco_fisico',       'Endereço',                'endereco_escola',       'textarea', '', ['ui_group' => 'localizacao', 'ui_order' => 50, 'ui_rows' => 2]);
            self::add_sige_config($items, 'escola.telefone_oficial',      'Telefone',                'telefone_oficial',      'string', '', ['ui_group' => 'localizacao', 'ui_order' => 60]);
            self::add_sige_config($items, 'escola.email_institucional',   'Email institucional',     'email_institucional',   'email',  '', ['ui_group' => 'localizacao', 'ui_order' => 70]);

            // Direcção
            self::add_sige_config($items, 'escola.director_nome',         'Director(a)',             'director_nome',         'string', '', ['ui_group' => 'direccao', 'ui_order' => 10]);
            self::add_sige_config($items, 'escola.cargo_direccao',        'Cargo',                   'cargo_direcao',         'string', '', ['ui_group' => 'direccao', 'ui_order' => 20]);
        }

        private static function register_documentos(array &$items): void {
            self::add_sige_config($items, 'documentos.logo_sistema',      'Logotipo do sistema',     'logo_sistema_url',      'url',    '', ['ui_group' => 'documentos', 'ui_order' => 10, 'ui_widget' => 'upload']);
            self::add_sige_config($items, 'documentos.logo_documentos',   'Logotipo dos documentos', 'logo_documentos_url',   'url',    '', ['ui_group' => 'documentos', 'ui_order' => 20, 'ui_widget' => 'upload']);
            self::add_sige_config($items, 'documentos.cabecalho_oficial', 'Cabeçalho oficial',       'cabecalho_oficial_url', 'url',    '', ['ui_group' => 'documentos', 'ui_order' => 30, 'ui_widget' => 'upload']);
            self::add_sige_config($items, 'documentos.cor_primaria',      'Cor dos documentos',    'cor_primaria',          'color',  '#0d1259', [
                'ui_group' => 'documentos', 'ui_order' => 40,
                'ui_help'  => 'Usada em recibos, declarações, boletins e documentos impressos. Ao mudar a Aparência da escola, esta cor é sincronizada automaticamente; aqui pode ser ajustada manualmente quando necessário.'
            ]);
            self::add_sige_config($items, 'documentos.rodape',            'Rodapé dos documentos',   'rodape_documentos',     'textarea', '', ['ui_group' => 'documentos', 'ui_order' => 50, 'ui_rows' => 3]);
        }


        private static function register_aparencia(array &$items): void {
            self::add_wp_option($items, 'aparencia.tema', 'Tema visual', 'sige_theme_mode', 'select', 'softgenial', [
                'ui_group' => 'aparencia', 'ui_order' => 10,
                'choices' => ['softgenial' => 'Padrão SoftGenial', 'escola' => 'Usar cores da escola'],
                'ui_help' => 'Escolha Padrão SoftGenial para manter o roxo oficial. Ao escolher ou alterar cores da escola, a aplicação passa a usar essas cores com protecção de contraste.'
            ]);
            self::add_wp_option($items, 'aparencia.fonte', 'Fonte do sistema', 'sige_theme_font', 'select', 'softgenial', [
                'ui_group' => 'aparencia', 'ui_order' => 15,
                'choices' => [
                    'softgenial' => 'Padrão SoftGenial',
                    'inter'      => 'Inter / moderna',
                    'system'     => 'Sistema / leve',
                    'segoe'      => 'Segoe UI',
                    'arial'      => 'Arial',
                    'verdana'    => 'Verdana',
                    'serif'      => 'Serif institucional',
                ],
                'ui_help' => 'Aplica a tipografia em todo o sistema: menu, painéis, formulários, tabelas, pop-ups e documentos de interface. Use uma opção legível e profissional.'
            ]);
            self::add_wp_option($items, 'aparencia.primaria', 'Cor principal', 'sige_theme_primary', 'color', '#5a3fd6', [
                'ui_group' => 'aparencia', 'ui_order' => 20,
                'ui_help' => 'Usada no menu, botões principais, destaques, cabeçalhos da aplicação e, por padrão, nos recibos e documentos impressos. Ao alterar esta cor, o tema da escola é activado automaticamente.'
            ]);
            self::add_wp_option($items, 'aparencia.secundaria', 'Cor secundária', 'sige_theme_secondary', 'color', '#3f2c9f', [
                'ui_group' => 'aparencia', 'ui_order' => 30,
                'ui_help' => 'Ajuda a compor degradês, estados activos e áreas de maior destaque da aplicação.'
            ]);
            self::add_wp_option($items, 'aparencia.destaque', 'Cor de destaque', 'sige_theme_accent', 'color', '#34a853', [
                'ui_group' => 'aparencia', 'ui_order' => 40,
                'ui_help' => 'Usada em barras de progresso, indicadores positivos e pequenos acentos visuais da aplicação.'
            ]);
        }

        private static function register_academico(array &$items): void {
            self::add_sige_config($items, 'academico.ano_lectivo', 'Ano lectivo activo', 'ano_lectivo', 'integer', null, [
                'ui_group' => 'academico', 'ui_order' => 10,
                'ui_min' => 2000, 'ui_max' => 2100,
                'required' => true,
            ]);
        }

        private static function register_operacao(array &$items): void {
            self::add_sige_config($items, 'operacao.moeda', 'Símbolo da moeda', 'moeda', 'string', 'MZN', [
                'ui_group' => 'operacao', 'ui_order' => 10,
            ]);
        }

        private static function register_comunicacao(array &$items): void {
            self::add_sige_config($items, 'comunicacao.whatsapp_url', 'URL Z-API', 'whatsapp_url', 'url', '', [
                'ui_group' => 'comunicacao', 'ui_order' => 10,
                'sensitive' => true, 'masked' => true, 'tech_only' => true,
            ]);
            self::add_sige_config($items, 'comunicacao.whatsapp_token', 'Token Z-API', 'whatsapp_token', 'secret', '', [
                'ui_group' => 'comunicacao', 'ui_order' => 20,
                'sensitive' => true, 'masked' => true, 'encrypted' => true,
                'preserve_when_empty' => true, 'tech_only' => true,
                'ui_help' => 'Vazio preserva o actual.',
            ]);
            self::add_sige_config($items, 'comunicacao.whatsapp_destinatarios', 'Destinatários padrão', 'whatsapp_destinatarios_padrao', 'select', 'todos', [
                'ui_group' => 'comunicacao', 'ui_order' => 30,
                'tech_only' => true,
                'choices_callback' => 'sige_wpp_destinatarios_options',
                'normalize_callback' => 'sige_wpp_destinatarios_normalize',
            ]);
            self::add_sige_config($items, 'comunicacao.template_mensalidade', 'Template - Mensalidade', 'msg_nova_fatura', 'textarea', '', [
                'ui_group' => 'comunicacao', 'ui_order' => 40, 'ui_rows' => 3, 'tech_only' => true,
            ]);
            self::add_sige_config($items, 'comunicacao.template_recibo', 'Template - Recibo pago', 'msg_recibo_pago', 'textarea', '', [
                'ui_group' => 'comunicacao', 'ui_order' => 50, 'ui_rows' => 3, 'tech_only' => true,
            ]);
            self::add_sige_config($items, 'comunicacao.template_cobranca', 'Template - Cobrança', 'msg_cobranca', 'textarea', '', [
                'ui_group' => 'comunicacao', 'ui_order' => 60, 'ui_rows' => 3, 'tech_only' => true,
            ]);
        }

        private static function register_smtp(array &$items): void {
            self::add_wp_option($items, 'comunicacao.smtp_config', 'SMTP', 'sige_smtp_config', 'smtp', [], [
                'ui_group' => 'comunicacao', 'ui_order' => 70,
                'sensitive' => true, 'masked' => true, 'tech_only' => true,
                'preserve_when_empty' => true,
            ]);
        }

        private static function register_modulos(array &$items): void {
            self::add_sige_config($items, 'modulos.ativos_locais', 'Módulos activos', 'modulos_ativos', 'json_list', '[]', [
                'ui_group' => 'modulos', 'ui_order' => 10,
                'tech_only' => true, 'controlled_by_hub' => true,
                'choices' => [
                    'mod_academico'  => 'Académico base',
                    'mod_rh'         => 'RH/base',
                    'mod_financeiro' => 'Financeiro',
                    'mod_preescolar' => 'Pré-escolar',
                    'mod_portal'     => 'Portal dos encarregados',
                    'mod_transporte' => 'Transporte',
                ],
                'always_on' => ['mod_academico', 'mod_rh'],
            ]);
        }

        private static function register_diagnostico(array &$items): void {
            // Informação técnica de leitura (não é configuração editável).
            self::add_constant($items, 'tecnico.sige_version', 'Versão',         'SIGE_VERSION',  ['ui_group' => 'diagnostico', 'ui_order' => 10]);
            self::add_constant($items, 'tecnico.timezone',     'Fuso horário',   'SIGE_TIMEZONE', ['ui_group' => 'diagnostico', 'ui_order' => 20]);
            self::add_wp_option($items, 'tecnico.plugin_build_id',   'Build ID',     'sige_plugin_build_id',   'string', null, ['ui_group' => 'diagnostico', 'ui_order' => 30, 'readonly' => true]);
            self::add_wp_option($items, 'tecnico.plugin_build_time', 'Build time',   'sige_plugin_build_time', 'string', null, ['ui_group' => 'diagnostico', 'ui_order' => 40, 'readonly' => true]);
            self::add_wp_option($items, 'tecnico.license_status',    'Licença',      'sige_license_cache',     'json',   [],   ['ui_group' => 'diagnostico', 'ui_order' => 50, 'readonly' => true, 'sensitive' => true, 'masked' => true, 'controlled_by_hub' => true]);
        }

        /** Financeiro: só leitura no Centro. Escrita no módulo Financeiro. */
        private static function register_financeiro_readonly(array &$items): void {
            self::add_fin($items, 'financeiro.moeda_simbolo',              'Moeda',                      'moeda_simbolo',              'string',  'MT', ['ui_group' => 'diagnostico', 'ui_order' => 110]);
            self::add_fin($items, 'financeiro.dia_vencimento',             'Dia de vencimento',          'dia_vencimento_mensalidade', 'integer', 5,    ['ui_group' => 'diagnostico', 'ui_order' => 120]);
            self::add_fin($items, 'financeiro.permitir_pagamento_parcial', 'Pagamento parcial',          'permitir_pagamento_parcial', 'boolean', 1,    ['ui_group' => 'diagnostico', 'ui_order' => 130]);
            self::add_fin($items, 'financeiro.prazo_vencimento',           'Prazo (dias)',               'prazo_vencimento',           'integer', 10,   ['ui_group' => 'diagnostico', 'ui_order' => 140]);
            self::add_fin($items, 'financeiro.tipo_multa',                 'Tipo de multa',              'tipo_multa',                 'string',  'percentual', ['ui_group' => 'diagnostico', 'ui_order' => 150]);
        }

        // ─── Helpers de registo ────────────────────────────────────────────────

        private static function base(array $overrides): array {
            return array_merge([
                'label'              => '',
                'domain'             => 'tecnico',
                'source'             => 'unknown',
                'table'              => '',
                'column'             => '',
                'option'             => '',
                'constant'           => '',
                'type'               => 'string',
                'default'            => null,
                'ui_group'           => 'diagnostico',
                'ui_order'           => 999,
                'ui_widget'          => '',
                'ui_help'            => '',
                'ui_rows'            => 3,
                'ui_min'             => null,
                'ui_max'             => null,
                'choices'            => [],
                'choices_callback'   => '',
                'normalize_callback' => '',
                'required'           => false,
                'readonly'           => false,
                'sensitive'          => false,
                'masked'             => false,
                'encrypted'          => false,
                'preserve_when_empty' => false,
                'tech_only'          => false,
                'controlled_by_hub'  => false,
                'informative_only'   => false,
                'always_on'          => [],
                'caps_view'          => 'configuracoes.ver',
                'caps_edit'          => 'configuracoes.editar',
                'editable_by_school' => true,
            ], $overrides);
        }

        private static function add(array &$items, string $key, array $meta): void {
            $key = strtolower($key);
            $key = preg_replace('/[^a-z0-9_.\-]/', '', $key);
            $key = trim((string)$key, '.');
            if ($key === '') return;
            $domain = strtok($key, '.') ?: 'tecnico';
            $meta['domain'] = $meta['domain'] ?? $domain;
            $items[$key] = self::base($meta);
        }

        private static function add_sige_config(array &$items, string $key, string $label, string $column, string $type, $default, array $extra = []): void {
            self::add($items, $key, array_merge([
                'label' => $label, 'source' => 'sige_config', 'table' => 'sige_config',
                'column' => $column, 'type' => $type, 'default' => $default,
            ], $extra));
        }

        private static function add_fin(array &$items, string $key, string $label, string $column, string $type, $default, array $extra = []): void {
            self::add($items, $key, array_merge([
                'label' => $label, 'source' => 'sige_fin_configuracoes', 'table' => 'sige_fin_configuracoes',
                'column' => $column, 'type' => $type, 'default' => $default,
                'readonly' => true,
            ], $extra));
        }

        private static function add_wp_option(array &$items, string $key, string $label, string $option, string $type, $default, array $extra = []): void {
            self::add($items, $key, array_merge([
                'label' => $label, 'source' => 'wp_option', 'option' => $option,
                'type' => $type, 'default' => $default,
            ], $extra));
        }

        private static function add_constant(array &$items, string $key, string $label, string $constant, array $extra = []): void {
            self::add($items, $key, array_merge([
                'label' => $label, 'source' => 'constant', 'constant' => $constant,
                'type' => 'string', 'readonly' => true,
            ], $extra));
        }
    }
}
