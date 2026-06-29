<?php
/**
 * SIGE SoftGenial - Feature Flags v12.8.3
 * Fase 2.1: controlo real de módulos por Hub / WP Options, sem alterar lógica interna.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_feature_known_defaults')) {
    function sige_feature_known_defaults(): array {
        return [
            'cadastro_base'=>true,'portal'=>true,'dica_do_dia'=>true,'academico'=>true,'financeiro'=>true,'documentos'=>true,'jardim'=>true,
            'transporte'=>true,'logistica'=>true,'rh'=>false,'portaria'=>true,
            'dashboard'=>true,'configuracoes'=>true,'core_status'=>true,'whatsapp'=>true,'email'=>true,
            'envio_real_whatsapp'=>true,'envio_real_email'=>true,'demo_mode'=>false,'demo_reset'=>false,
            'centros_custo'=>false,'regime_tempo_permanencia'=>false,'pauta_sne_malisa'=>false,
            'dec_malisa'=>false,'documentos_finais'=>true,
        ];
    }
}

if (!function_exists('sige_feature_aliases')) {
    function sige_feature_aliases(): array {
        return [
            'mod_academico'=>'academico','mod_financeiro'=>'financeiro','mod_preescolar'=>'jardim','mod_jardim'=>'jardim',
            'mod_transporte'=>'transporte','mod_logistica'=>'transporte','logistica'=>'transporte','logística'=>'transporte','mod_rh'=>'rh','mod_portaria'=>'portaria',
            'mod_whatsapp'=>'whatsapp','mod_email'=>'email','tesouraria'=>'financeiro',
            'secretaria'=>'academico','cadastro'=>'cadastro_base','cadastro_escolar'=>'cadastro_base',
            'preescolar'=>'jardim','jardim_infancia'=>'jardim','recursos_humanos'=>'rh',
            'portal_aluno'=>'portal','pagina_aluno'=>'portal','dica_dia'=>'dica_do_dia','mensagem_dia'=>'dica_do_dia',
        ];
    }
}

if (!function_exists('sige_feature_normalize_key')) {
    function sige_feature_normalize_key(string $key): string {
        $key = sanitize_key(trim($key));
        $aliases = sige_feature_aliases();
        return $aliases[$key] ?? $key;
    }
}

if (!function_exists('sige_feature_apply_active_list')) {
    function sige_feature_apply_active_list(array $active_list, bool $authoritative = true): array {
        $defaults = sige_feature_known_defaults();
        $map = $authoritative ? array_fill_keys(array_keys($defaults), false) : $defaults;
        foreach ($active_list as $raw) {
            $key = sige_feature_normalize_key((string)$raw);
            if ($key !== '') $map[$key] = true;
        }
        return sige_feature_apply_dependencies($map);
    }
}

if (!function_exists('sige_feature_apply_dependencies')) {
    /**
     * Aplica dependências mínimas entre funcionalidades.
     * Regra de produto: planos parciais não podem ficar inutilizáveis.
     * Ex.: Tesouraria precisa de cadastro de alunos/turmas/famílias, mas não de notas/pautas.
     */
    function sige_feature_apply_dependencies(array $map): array {
        $on = static function(array &$m, string $key): void {
            $key = sige_feature_normalize_key($key);
            if ($key !== '') $m[$key] = true;
        };

        if (!empty($map['financeiro'])) {
            $on($map, 'cadastro_base');
        }
        if (!empty($map['academico'])) {
            $on($map, 'cadastro_base');
        }
        if (!empty($map['jardim'])) {
            $on($map, 'cadastro_base');
        }
        if (!empty($map['transporte'])) {
            $on($map, 'cadastro_base');
        }

        return $map;
    }
}

if (!function_exists('sige_feature_resolve_all')) {
    function sige_feature_resolve_all(): array {
        $defaults = sige_feature_known_defaults();
        $hub_state = get_option('sige_hub_state', []);
        if (is_array($hub_state) && isset($hub_state["features_effective"]) && is_array($hub_state["features_effective"]) && count($hub_state["features_effective"]) > 0) {
            $map = sige_feature_apply_dependencies(sige_feature_apply_active_list($hub_state["features_effective"], true));
            if (function_exists("sige_feature_registry_apply_bridge")) {
                $map = sige_feature_registry_apply_bridge($map, $hub_state["features_effective"]);
                $map = sige_feature_apply_dependencies($map);
            }
            return $map;
        }
        $local = get_option('sige_features_local', null);
        if (is_array($local)) {
            $map = $defaults;
            foreach ($local as $raw_key => $raw_value) {
                $key = sige_feature_normalize_key((string)$raw_key);
                if ($key !== '') $map[$key] = (bool)$raw_value;
            }
            return sige_feature_apply_dependencies($map);
        }
        $cache = get_option('sige_license_cache', []);
        if (is_array($cache) && !empty($cache['modules']) && is_array($cache['modules'])) {
            return sige_feature_apply_dependencies(sige_feature_apply_active_list($cache['modules'], false));
        }
        if (class_exists('SIGE_License') && method_exists('SIGE_License', 'default_modules_for_plan')) {
            $plan = is_array($cache) ? (string)($cache['plan'] ?? 'completo') : 'completo';
            $mods = SIGE_License::default_modules_for_plan($plan);
            if (is_array($mods) && !empty($mods)) return sige_feature_apply_dependencies(sige_feature_apply_active_list($mods, false));
        }
        return sige_feature_apply_dependencies($defaults);
    }
}

if (!function_exists('sige_feature')) {
    function sige_feature(string $key, $default = null): bool {
        $key = sige_feature_normalize_key($key);
        if ($key === '') return (bool)($default ?? true);
        static $cache = null;
        if ($cache === null) $cache = sige_feature_resolve_all();
        if (array_key_exists($key, $cache)) return (bool)$cache[$key];
        return (bool)($default ?? true);
    }
}

if (!function_exists('sige_feature_source')) {
    function sige_feature_source(): string {
        $hub_state = get_option('sige_hub_state', []);
        if (is_array($hub_state) && !empty($hub_state['features_effective'])) return 'hub_client';
        $local = get_option('sige_features_local', null);
        if (is_array($local)) return 'wp_options_local';
        $cache = get_option('sige_license_cache', []);
        if (is_array($cache) && !empty($cache['modules'])) return 'license_cache';
        return 'compat_defaults';
    }
}

if (!function_exists('sige_feature_clear_cache')) {
    function sige_feature_clear_cache(): void {}
}

if (!function_exists('sige_view_feature_map')) {
    function sige_view_feature_map(): array {
        return [
            'financeiro-dashboard'=>'financeiro','financeiro-pagamentos'=>'financeiro','financeiro-extratos'=>'financeiro',
            'financeiro-gerador'=>'financeiro','financeiro-inscricoes'=>'financeiro','financeiro-config'=>'financeiro','financeiro-despesas'=>'financeiro',
            'financeiro-centros'=>'financeiro','financeiro-devedores'=>'financeiro','financeiro-lancamentos'=>'financeiro',
            'financeiro-relatorio-mensal'=>'financeiro','financeiro-auditoria'=>'financeiro',
            'pagamentos-turma'=>'financeiro',
            'alunos_lista'=>'cadastro_base','turmas'=>'cadastro_base','aluno_contas'=>'cadastro_base','aluno_portal'=>'portal',
            'disciplinas'=>'academico','matriz'=>'academico','curriculos'=>'academico','notas'=>'academico','pautas'=>'academico','boletim'=>'academico','minhas_turmas'=>'academico',
            'aprovar_notas'=>'academico','auditoria_notas'=>'academico','dec'=>'academico','pauta_final'=>'academico',
            'acta'=>'academico','encerramento'=>'academico','abertura'=>'academico',
            'vincular'=>'academico','alocacao'=>'academico','estatisticas_demo'=>'academico',
            'jardim_diario'=>'jardim','jardim_saude'=>'jardim','jardim_boletim'=>'jardim',
            'jardim_relatorio'=>'jardim','jardim_presencas'=>'jardim',
            'equipe'=>'rh','transporte'=>'transporte','portaria'=>'portaria',
        ];
    }
}

if (!function_exists('sige_view_feature_allowed')) {
    function sige_view_feature_allowed(string $view): bool {
        $view = sanitize_key($view);
        $map = sige_view_feature_map();
        if (!isset($map[$view])) return true;
        return sige_feature($map[$view]);
    }
}

if (!function_exists('sige_feature_denied_screen')) {
    function sige_feature_denied_screen(string $view): void {
        $map = sige_view_feature_map();
        wp_die(
            '<div style="max-width:720px;margin:20px auto;font-family:system-ui,-apple-system,Segoe UI,sans-serif;">'
            . '<h1 style="margin-bottom:10px;color:#0f172a;">Área não activa</h1>'
            . '<p style="font-size:15px;color:#475569;">Esta área não está activa para a escola neste momento. A operação normal do sistema permanece disponível nas restantes áreas.</p>'
            . '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=sige-app')) . '">Voltar ao painel</a></p>'
            . '</div>',
            'Área não activa',
            ['response' => 403]
        );
    }
}

if (!function_exists('sige_hub_get_domain')) {
    function sige_hub_get_domain(): string {
        $url = function_exists('home_url') ? home_url() : '';
        $domain = strtolower(trim((string)$url));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        return $domain;
    }
}
