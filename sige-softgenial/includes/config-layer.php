<?php
/**
 * SIGE SoftGenial - Config Layer v12.5.3
 *
 * Camada central de configuração por domínio funcional.
 * Fonte de verdade: WP Options com fallback interno seguro.
 *
 * Esta camada NÃO altera cálculo, schema, UI nem comportamento existente.
 * Apenas normaliza leitura de configuração para futuras regras por escola.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_config_defaults')) {
    function sige_config_defaults(): array {
        return [
            'financeiro' => [
                'regime_mensalidade'  => 'normal',
                'centros_custo_ativos' => false,
            ],
            'academico' => [
                'documentos_finais' => true,
            ],
            'core' => [],
        ];
    }
}

if (!function_exists('sige_config_normalize_scope')) {
    function sige_config_normalize_scope($scope): string {
        $scope = sanitize_key((string)$scope);
        return $scope !== '' ? $scope : 'core';
    }
}

if (!function_exists('sige_config_get')) {
    /**
     * Lê uma configuração do SIGE por escopo.
     *
     * Exemplo de option: sige_config_financeiro = [
     *   'regime_mensalidade' => 'normal',
     *   'centros_custo_ativos' => false,
     * ]
     *
     * @param string $scope   Ex.: financeiro, academico, core
     * @param string $key     Chave da configuração
     * @param mixed  $default Fallback externo quando não existe fallback interno
     * @return mixed
     */
    function sige_config_get($scope, $key, $default = null) {
        $scope = sige_config_normalize_scope($scope);
        $key = sanitize_key((string)$key);
        if ($key === '') return $default;

        $defaults = sige_config_defaults();
        $fallback = array_key_exists($scope, $defaults) && array_key_exists($key, $defaults[$scope])
            ? $defaults[$scope][$key]
            : $default;

        $option_name = 'sige_config_' . $scope;
        $option = get_option($option_name, []);
        if (is_string($option) && $option !== '') {
            $decoded = json_decode($option, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $option = $decoded;
            } else {
                if (function_exists('sige_log_event')) {
                    sige_log_event('config', 'invalid_json', [
                        'option' => $option_name,
                        'scope'  => $scope,
                        'key'    => $key,
                    ], 'warning');
                }
                $option = [];
            }
        }

        if (is_array($option) && array_key_exists($key, $option)) {
            if (function_exists('sige_log_event')) {
                sige_log_event('config', 'read', [
                    'scope'  => $scope,
                    'key'    => $key,
                    'source' => 'wp_option',
                ], 'debug');
            }
            return $option[$key];
        }

        if (function_exists('sige_log_event')) {
            sige_log_event('config', 'read', [
                'scope'  => $scope,
                'key'    => $key,
                'source' => 'fallback',
            ], 'debug');
        }
        return $fallback;
    }
}

if (!function_exists('sige_financeiro_config')) {
    function sige_financeiro_config($key, $default = null) {
        return sige_config_get('financeiro', $key, $default);
    }
}

if (!function_exists('sige_academico_config')) {
    function sige_academico_config($key, $default = null) {
        return sige_config_get('academico', $key, $default);
    }
}
