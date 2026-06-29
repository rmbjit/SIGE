<?php
/**
 * SIGE SoftGenial - Settings Bootstrap
 *
 * Carregador único do módulo Configurações. Substitui o antigo settings-loader.php
 * que carregava 24 ficheiros (12 deles documentais sem consumidor real).
 *
 * Ordem de carregamento é deliberada:
 *   1. Registry (schema declarativo - nenhuma dependência)
 *   2. Sanitizer (depende do Registry para tipos)
 *   3. Repository (depende do Registry + Sanitizer)
 *   4. Policy (depende do Registry + Technical_Mode)
 *   5. Audit (depende do Registry)
 *   6. Technical_Mode (modo técnico governado - mantido da v12.9.x)
 *   7. Controller (depende de todos os anteriores)
 *   8. Legacy (intercepta actions externas - corre no hook 'init' priority 99)
 *   9. View_Renderer (depende do Registry + Repository + Policy)
 *
 * @since v12.10.0
 */
if (!defined('ABSPATH')) exit;

$__sige_settings_files = [
    'class-sige-settings-registry.php',
    'class-sige-settings-sanitizer.php',
    'class-sige-settings-repository.php',
    'class-sige-settings-policy.php',
    'class-sige-settings-audit.php',
    'class-sige-settings-technical-mode.php',
    'class-sige-settings-controller.php',
    'class-sige-settings-legacy.php',
    'class-sige-settings-view-renderer.php',
];

foreach ($__sige_settings_files as $__sige_settings_file) {
    $__sige_settings_path = __DIR__ . '/' . $__sige_settings_file;
    if (file_exists($__sige_settings_path)) {
        require_once $__sige_settings_path;
    }
}
unset($__sige_settings_file, $__sige_settings_path, $__sige_settings_files);

// Inicialização do interceptor legacy.
if (class_exists('SIGE_Settings_Legacy')) {
    SIGE_Settings_Legacy::init();
}
