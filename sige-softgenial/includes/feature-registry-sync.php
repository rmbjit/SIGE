<?php
/**
 * SIGE SoftGenial - Feature Registry Sync v12.8.3
 *
 * Objectivo: alinhar o catálogo local de permissões/módulos com o Hub Central,
 * sem alterar cálculo, UI dos outros módulos ou base de dados.
 *
 * Nota de compatibilidade:
 * - Algumas versões do Hub podem ainda não conhecer certas funcionalidades do
 *   plugin (ex.: rh). Quando o Hub antigo envia uma lista autoritativa sem uma
 *   feature local instalada, essa feature pode desaparecer do menu.
 * - Esta camada expõe um manifesto técnico para heartbeat/diagnóstico e aplica
 *   uma ponte conservadora apenas para features locais comprovadamente instaladas
 *   e ainda não reconhecidas pelo Hub antigo.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_feature_registry_module_to_feature_map')) {
    function sige_feature_registry_module_to_feature_map(): array {
        return [
            'financeiro'      => 'financeiro',
            'academico'       => 'academico',
            'secretaria'      => 'cadastro_base',
            'alunos'          => 'cadastro_base',
            'matriculas'      => 'cadastro_base',
            'documentos'      => 'documentos',
            'configuracoes'   => 'configuracoes',
            'usuarios'        => 'configuracoes',
            'portal'          => 'portal',
            'transporte'      => 'transporte',
            'jardim'          => 'jardim',
            'creche'          => 'jardim',
            'rh'              => 'rh',
            'recursos_humanos'=> 'rh',
            'comunicacao'     => 'comunicacao',
            'sistema'         => 'core_status',
            'tecnico'         => 'core_status',
        ];
    }
}

if (!function_exists('sige_feature_registry_manifest')) {
    function sige_feature_registry_manifest(): array {
        $features = [];

        $add = static function(string $key, string $source, bool $default_active = true) use (&$features): void {
            $key = function_exists('sige_feature_normalize_key') ? sige_feature_normalize_key($key) : sanitize_key($key);
            if ($key === '') return;
            if (!isset($features[$key])) {
                $features[$key] = [
                    'feature_key' => $key,
                    'sources' => [],
                    'default_active' => $default_active,
                ];
            }
            $features[$key]['sources'][] = $source;
            $features[$key]['sources'] = array_values(array_unique($features[$key]['sources']));
            $features[$key]['default_active'] = $features[$key]['default_active'] || $default_active;
        };

        if (function_exists('sige_feature_known_defaults')) {
            foreach (sige_feature_known_defaults() as $key => $active) {
                $add((string)$key, 'feature_defaults', (bool)$active);
            }
        }

        if (function_exists('sige_view_feature_map')) {
            foreach (sige_view_feature_map() as $view => $feature) {
                $add((string)$feature, 'view:' . sanitize_key((string)$view), true);
            }
        }

        if (function_exists('sige_permissions_registry')) {
            $map = sige_feature_registry_module_to_feature_map();
            foreach (sige_permissions_registry() as $perm_key => $meta) {
                $modulo = sanitize_key((string)($meta['modulo'] ?? ''));
                if ($modulo === '') continue;
                $feature = $map[$modulo] ?? $modulo;
                $add($feature, 'permission:' . sanitize_key((string)$perm_key), true);
            }
        }

        ksort($features);
        return array_values($features);
    }
}

if (!function_exists('sige_feature_registry_keys')) {
    function sige_feature_registry_keys(): array {
        $keys = [];
        foreach (sige_feature_registry_manifest() as $row) {
            if (!empty($row['feature_key'])) $keys[] = (string)$row['feature_key'];
        }
        $keys = array_values(array_unique(array_map('sanitize_key', $keys)));
        sort($keys);
        return $keys;
    }
}

if (!function_exists('sige_feature_registry_bridge_keys')) {
    /**
     * Ponte conservadora para features locais instaladas, mas ausentes em Hub antigo.
     * Actualmente limitada a RH porque o módulo existe no plugin/menu e não estava
     * listado no catálogo de funcionalidades de algumas versões do Hub.
     */
    function sige_feature_registry_bridge_keys(): array {
        $configured = get_option('sige_feature_registry_bridge_keys', null);
        if (is_array($configured)) {
            return array_values(array_unique(array_map('sanitize_key', $configured)));
        }
        // A partir do Hub v3.0.4, 'rh' existe oficialmente no catálogo central.
        // Portanto, a ponte automática deixa de ligar RH por defeito.
        // Se for necessário em emergência, definir a option sige_feature_registry_bridge_keys = ['rh'].
        return [];
    }
}

if (!function_exists('sige_feature_registry_apply_bridge')) {
    function sige_feature_registry_apply_bridge(array $map, array $hub_effective): array {
        $hub_effective = array_values(array_unique(array_map('sanitize_key', $hub_effective)));
        $registry_keys = sige_feature_registry_keys();

        foreach (sige_feature_registry_bridge_keys() as $feature_key) {
            $feature_key = function_exists('sige_feature_normalize_key') ? sige_feature_normalize_key($feature_key) : sanitize_key($feature_key);
            if ($feature_key === '') continue;

            // Só actua se a feature existir no plugin local e estiver ausente da lista do Hub antigo.
            if (!in_array($feature_key, $registry_keys, true)) continue;
            if (in_array($feature_key, $hub_effective, true)) continue;

            // Permite desactivar localmente a ponte em emergência: option sige_feature_registry_bridge_disabled = ['rh'].
            $disabled = get_option('sige_feature_registry_bridge_disabled', []);
            if (is_array($disabled) && in_array($feature_key, array_map('sanitize_key', $disabled), true)) continue;

            $map[$feature_key] = true;
        }

        return $map;
    }
}

if (!function_exists('sige_feature_registry_sync_snapshot')) {
    function sige_feature_registry_sync_snapshot(): void {
        update_option('sige_feature_registry_manifest', sige_feature_registry_manifest(), false);
        update_option('sige_feature_registry_keys', sige_feature_registry_keys(), false);
    }
}

add_action('admin_init', 'sige_feature_registry_sync_snapshot', 20);
