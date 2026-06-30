<?php
/**
 * SIGE SoftGenial - Configuração do modelo de crachá por escola.
 *
 * Camada de definicoes (so leitura/escrita de configuracao) que permite a cada
 * escola escolher o modelo do cracha do estudante e activar redes sociais.
 *
 * Fonte de verdade do DESENHO dos modelos: assets/cracha/sige-cracha-templates.js
 * (usado pela pre-visualizacao e pela impressao). Aqui vive apenas a LISTA valida
 * de modelos (para validar a escolha) e a configuracao escolhida pela escola.
 * Os ids TEM de coincidir com os do registo JS (garantido por smoke).
 *
 * Armazenamento: WP option por escola -> sige_cracha_config_{escola_id}. Sem
 * alteracao de esquema. Tenant-scoped. Sem tocar em calculo, permissoes reais,
 * nonces de outros fluxos nem contratos protegidos.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_cracha_template_ids')) {
    /** Ids validos de modelo (devem coincidir com o registo JS). @return string[] */
    function sige_cracha_template_ids(): array {
        return ['aurora', 'classic', 'vivid', 'minimal'];
    }
}

if (!function_exists('sige_cracha_templates_meta')) {
    /** Metadados dos modelos para o seletor (rotulo + descricao + cor sugerida). */
    function sige_cracha_templates_meta(): array {
        return [
            'aurora'  => ['nome' => 'Aurora',  'descricao' => 'Degradê moderno com foto circular e redes sociais.', 'accent' => '#7c3aed'],
            'classic' => ['nome' => 'Clássico', 'descricao' => 'Institucional sóbrio, estilo documento oficial.',     'accent' => '#0f172a'],
            'vivid'   => ['nome' => 'Vivid',   'descricao' => 'Faixa lateral vibrante e visual ousado.',             'accent' => '#e11d48'],
            'minimal' => ['nome' => 'Minimal', 'descricao' => 'Limpo e elegante, com linha de destaque fina.',       'accent' => '#0ea5e9'],
        ];
    }
}

if (!function_exists('sige_cracha_social_fields')) {
    /** Campos de redes sociais suportados no crachá. @return string[] */
    function sige_cracha_social_fields(): array {
        return ['instagram', 'facebook', 'website'];
    }
}

if (!function_exists('sige_cracha_config_defaults')) {
    /** Configuração por omissão (escola que nunca escolheu). */
    function sige_cracha_config_defaults(): array {
        return [
            'template'    => 'aurora',
            'accent'      => '#7c3aed',
            'show_social' => false,
            'social'      => ['instagram' => '', 'facebook' => '', 'website' => ''],
        ];
    }
}

if (!function_exists('sige_cracha_config_option_name')) {
    function sige_cracha_config_option_name(int $escola_id): string {
        return 'sige_cracha_config_' . max(0, $escola_id);
    }
}

if (!function_exists('sige_cracha_sanitize_hex')) {
    /** Devolve um hex #rrggbb valido ou o fallback dado. */
    function sige_cracha_sanitize_hex($value, string $fallback = '#7c3aed'): string {
        $value = is_string($value) ? trim($value) : '';
        if ($value !== '' && $value[0] !== '#') { $value = '#' . $value; }
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }
}

if (!function_exists('sige_cracha_normalize_generic')) {
    /**
     * Núcleo genérico de validação/normalização (partilhado por estudante e equipa).
     * Valida o modelo contra a lista permitida e sanitiza cor/redes. Nunca confia
     * no conteúdo bruto.
     *
     * @param mixed    $raw       Configuração crua (storage ou input).
     * @param string[] $valid_ids Ids de modelo permitidos.
     * @param array    $defaults  Defaults (template/accent/show_social/social).
     */
    function sige_cracha_normalize_generic($raw, array $valid_ids, array $defaults): array {
        $raw = is_array($raw) ? $raw : [];

        $template = isset($raw['template']) ? sanitize_key((string)$raw['template']) : '';
        if (!in_array($template, $valid_ids, true)) {
            $template = $defaults['template'];
        }

        $accent = sige_cracha_sanitize_hex($raw['accent'] ?? '', $defaults['accent']);

        $show_social = !empty($raw['show_social']) && $raw['show_social'] !== '0' && $raw['show_social'] !== 'false';

        $social_in = (isset($raw['social']) && is_array($raw['social'])) ? $raw['social'] : [];
        $social = [];
        foreach (sige_cracha_social_fields() as $f) {
            $val = isset($social_in[$f]) ? sanitize_text_field((string)$social_in[$f]) : '';
            // Limite defensivo de comprimento para o cartao nao rebentar visualmente.
            if (function_exists('mb_substr')) { $val = mb_substr($val, 0, 80); } else { $val = substr($val, 0, 80); }
            $social[$f] = trim($val);
        }

        return [
            'template'    => $template,
            'accent'      => $accent,
            'show_social' => $show_social,
            'social'      => $social,
        ];
    }
}

if (!function_exists('sige_cracha_normalize_config')) {
    /** Normaliza a configuração do crachá do ESTUDANTE. */
    function sige_cracha_normalize_config($raw): array {
        return sige_cracha_normalize_generic($raw, sige_cracha_template_ids(), sige_cracha_config_defaults());
    }
}

if (!function_exists('sige_cracha_config_get')) {
    /** Configuração normalizada de uma escola (com fallback aos defaults). */
    function sige_cracha_config_get(?int $escola_id = null): array {
        if ($escola_id === null) {
            $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        }
        $stored = get_option(sige_cracha_config_option_name((int)$escola_id), null);
        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            $stored = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
        }
        return sige_cracha_normalize_config($stored);
    }
}

if (!function_exists('sige_cracha_config_save')) {
    /** Valida e persiste a configuração de uma escola. Devolve a config normalizada. */
    function sige_cracha_config_save(int $escola_id, $input): array {
        $config = sige_cracha_normalize_config($input);
        update_option(sige_cracha_config_option_name($escola_id), wp_json_encode($config), false);
        if (function_exists('sige_log_event')) {
            sige_log_event('cracha', 'config_saved', ['escola_id' => $escola_id, 'template' => $config['template']], 'info');
        }
        return $config;
    }
}

if (!function_exists('sige_cracha_config_for_js')) {
    /**
     * Payload para o cliente: config actual + catalogo de modelos. Consumido pela
     * pagina Alunos (seletor + impressao).
     */
    function sige_cracha_config_for_js(?int $escola_id = null): array {
        return [
            'config'    => sige_cracha_config_get($escola_id),
            'templates' => sige_cracha_templates_meta(),
            'social'    => sige_cracha_social_fields(),
        ];
    }
}

// ── AJAX: gravar a escolha da escola ───────────────────────────────────────────
add_action('wp_ajax_sige_save_cracha_config', 'sige_ajax_save_cracha_config');

if (!function_exists('sige_ajax_save_cracha_config')) {
    function sige_ajax_save_cracha_config() {
        if (function_exists('sige_check_nonce_global')) { sige_check_nonce_global(); }

        $can = function_exists('sige_ajax_user_can_permissions_or_caps')
            ? sige_ajax_user_can_permissions_or_caps(
                ['configuracoes.editar'],
                ['sige_director', 'sige_gestor_rh', 'sige_admin_ti', 'sige_secretaria_geral']
            )
            : (function_exists('current_user_can') && current_user_can('manage_options'));

        if (!$can) {
            wp_send_json_error(['msg' => 'Sem permissão para alterar o modelo de crachá da escola.']);
        }

        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($escola_id <= 0) {
            wp_send_json_error(['msg' => 'Escola não identificada.']);
        }

        $input = [
            'template'    => isset($_POST['template']) ? wp_unslash((string)$_POST['template']) : '',
            'accent'      => isset($_POST['accent']) ? wp_unslash((string)$_POST['accent']) : '',
            'show_social' => isset($_POST['show_social']) ? wp_unslash((string)$_POST['show_social']) : '',
            'social'      => [],
        ];
        foreach (sige_cracha_social_fields() as $f) {
            $key = 'social_' . $f;
            $input['social'][$f] = isset($_POST[$key]) ? wp_unslash((string)$_POST[$key]) : '';
        }

        $config = sige_cracha_config_save($escola_id, $input);
        wp_send_json_success(['config' => $config, 'msg' => 'Modelo de crachá actualizado.']);
    }
}

// ============================================================================
// CRACHÁ DA EQUIPA (Professores/Funcionários) - conjunto de modelos próprio.
// Mesma engenharia do estudante, mas modelos e armazenamento separados.
// ============================================================================

if (!function_exists('sige_cracha_staff_template_ids')) {
    /** Ids válidos dos modelos de equipa (devem coincidir com o registo JS). @return string[] */
    function sige_cracha_staff_template_ids(): array {
        return ['corporate', 'lanyard', 'executive', 'slate'];
    }
}

if (!function_exists('sige_cracha_staff_templates_meta')) {
    function sige_cracha_staff_templates_meta(): array {
        return [
            'corporate' => ['nome' => 'Corporate', 'descricao' => 'Cabeçalho sólido e visual corporativo, foto destacada.', 'accent' => '#1e3a8a'],
            'lanyard'   => ['nome' => 'Lanyard',   'descricao' => 'Estilo crachá de fita/evento, com furo no topo.',       'accent' => '#0f766e'],
            'executive' => ['nome' => 'Executive', 'descricao' => 'Escuro e elegante, com linha de destaque fina.',        'accent' => '#b45309'],
            'slate'     => ['nome' => 'Slate',     'descricao' => 'Faixa lateral e visual técnico moderno.',               'accent' => '#475569'],
        ];
    }
}

if (!function_exists('sige_cracha_staff_config_defaults')) {
    function sige_cracha_staff_config_defaults(): array {
        return [
            'template'    => 'corporate',
            'accent'      => '#1e3a8a', // azul corporativo - distinto do roxo do estudante
            'show_social' => false,
            'social'      => ['instagram' => '', 'facebook' => '', 'website' => ''],
        ];
    }
}

if (!function_exists('sige_cracha_staff_config_get')) {
    function sige_cracha_staff_config_get(?int $escola_id = null): array {
        if ($escola_id === null) {
            $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        }
        $stored = get_option('sige_cracha_staff_config_' . max(0, (int)$escola_id), null);
        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            $stored = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
        }
        return sige_cracha_normalize_generic($stored, sige_cracha_staff_template_ids(), sige_cracha_staff_config_defaults());
    }
}

if (!function_exists('sige_cracha_staff_config_save')) {
    function sige_cracha_staff_config_save(int $escola_id, $input): array {
        $config = sige_cracha_normalize_generic($input, sige_cracha_staff_template_ids(), sige_cracha_staff_config_defaults());
        update_option('sige_cracha_staff_config_' . max(0, $escola_id), wp_json_encode($config), false);
        if (function_exists('sige_log_event')) {
            sige_log_event('cracha', 'staff_config_saved', ['escola_id' => $escola_id, 'template' => $config['template']], 'info');
        }
        return $config;
    }
}

if (!function_exists('sige_cracha_staff_config_for_js')) {
    function sige_cracha_staff_config_for_js(?int $escola_id = null): array {
        return [
            'config'    => sige_cracha_staff_config_get($escola_id),
            'templates' => sige_cracha_staff_templates_meta(),
            'social'    => sige_cracha_social_fields(),
        ];
    }
}

add_action('wp_ajax_sige_save_cracha_staff_config', 'sige_ajax_save_cracha_staff_config');

if (!function_exists('sige_ajax_save_cracha_staff_config')) {
    function sige_ajax_save_cracha_staff_config() {
        if (function_exists('sige_check_nonce_global')) { sige_check_nonce_global(); }

        $can = function_exists('sige_ajax_user_can_permissions_or_caps')
            ? sige_ajax_user_can_permissions_or_caps(
                ['rh.equipe_gerir', 'configuracoes.editar'],
                ['sige_director', 'sige_gestor_rh', 'sige_admin_ti']
            )
            : (function_exists('current_user_can') && current_user_can('manage_options'));

        if (!$can) {
            wp_send_json_error(['msg' => 'Sem permissão para alterar o modelo de crachá da equipa.']);
        }

        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($escola_id <= 0) {
            wp_send_json_error(['msg' => 'Escola não identificada.']);
        }

        $input = [
            'template'    => isset($_POST['template']) ? wp_unslash((string)$_POST['template']) : '',
            'accent'      => isset($_POST['accent']) ? wp_unslash((string)$_POST['accent']) : '',
            'show_social' => isset($_POST['show_social']) ? wp_unslash((string)$_POST['show_social']) : '',
            'social'      => [],
        ];
        foreach (sige_cracha_social_fields() as $f) {
            $key = 'social_' . $f;
            $input['social'][$f] = isset($_POST[$key]) ? wp_unslash((string)$_POST[$key]) : '';
        }

        $config = sige_cracha_staff_config_save($escola_id, $input);
        wp_send_json_success(['config' => $config, 'msg' => 'Modelo de crachá da equipa actualizado.']);
    }
}
