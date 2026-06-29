<?php
/**
 * SIGE SoftGenial - Self-Hosted Update Checker
 *
 * Verifica actualizações a partir de softgenial.edu.mz/updates/info.json
 * Sem dependências externas - usa apenas a API nativa do WordPress.
 *
 * Endpoint JSON esperado:
 * {
 *   "version": "12.0",
 *   "download_url": "https://softgenial.edu.mz/updates/sige-softgenial-003.zip",
 *   "requires": "6.0",
 *   "requires_php": "7.4",
 *   "tested": "6.9",
 *   "changelog": "Correcções multi-tenant, DRY refactoring.",
 *   "last_updated": "2026-04-06"
 * }
 *
 * @since S12
 */

if (!defined('ABSPATH')) exit;

class SIGE_Update_Checker {

    /** URL do JSON de versão */
    private string $info_url;

    /** Slug do plugin (ex: sige-softgenial-003/sige-softgenial.php) */
    private string $plugin_slug;

    /** Versão actual instalada */
    private string $current_version;

    /** Cache key para transient */
    private string $cache_key = 'sige_update_check';

    /** Intervalo de verificação em segundos (12h) */
    private int $check_interval = 43200;

    public function __construct() {
        $this->info_url        = 'https://softgenial.edu.mz/updates/info.json';
        $this->plugin_slug     = plugin_basename(dirname(__FILE__, 2) . '/sige-softgenial.php');
        $this->current_version = defined('SIGE_VERSION') ? SIGE_VERSION : '0.0';

        // Hook: verificar updates
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);

        // Hook: mostrar info na modal de detalhes
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);

        // Hook: limpar cache ao activar/desactivar
        register_activation_hook(
            dirname(__FILE__, 2) . '/sige-softgenial.php',
            [$this, 'clear_cache']
        );
    }

    /**
     * Verifica se há versão mais recente.
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) return $transient;

        $remote = $this->get_remote_info();
        if (!$remote) return $transient;

        if (version_compare($this->current_version, $remote->version, '<')) {
            $update = (object) [
                'slug'        => dirname($this->plugin_slug),
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote->version,
                'url'         => $remote->homepage ?? 'https://softgenial.edu.mz',
                'package'     => $remote->download_url ?? '',
                'tested'      => $remote->tested ?? '',
                'requires'    => $remote->requires ?? '6.0',
                'requires_php'=> $remote->requires_php ?? '7.4',
            ];
            $transient->response[$this->plugin_slug] = $update;
        } else {
            // Informar WP que verificámos e está actualizado
            $transient->no_update[$this->plugin_slug] = (object) [
                'slug'        => dirname($this->plugin_slug),
                'plugin'      => $this->plugin_slug,
                'new_version' => $this->current_version,
                'url'         => 'https://softgenial.edu.mz',
            ];
        }

        return $transient;
    }

    /**
     * Modal de detalhes do plugin (clique em "Ver detalhes").
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) return $result;

        $remote = $this->get_remote_info();
        if (!$remote) return $result;

        return (object) [
            'name'          => 'SIGE SoftGenial',
            'slug'          => dirname($this->plugin_slug),
            'version'       => $remote->version,
            'author'        => '<a href="https://rmbjconsulting.com">RMBJ Consultoria</a>',
            'homepage'      => 'https://softgenial.edu.mz',
            'download_link' => $remote->download_url ?? '',
            'requires'      => $remote->requires ?? '6.0',
            'requires_php'  => $remote->requires_php ?? '7.4',
            'tested'        => $remote->tested ?? '',
            'last_updated'  => $remote->last_updated ?? '',
            'sections'      => [
                'description' => 'Software de Gestão Integrado para Escolas - Moçambique.',
                'changelog'   => $remote->changelog ?? 'Sem notas de versão.',
            ],
        ];
    }

    /**
     * Busca informação remota (com cache de 12h).
     */
    private function get_remote_info(): ?object {
        $cached = get_transient($this->cache_key);
        if ($cached instanceof \stdClass) return $cached;
        if ($cached === 'none') return null; // cache negativo

        $response = wp_remote_get($this->info_url, [
            'timeout'   => 10,
            'sslverify' => true,
            'headers'   => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Cache negativo (1h) para não bombardear o servidor
            set_transient($this->cache_key, 'none', 3600);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        if (!$body || empty($body->version)) return null;

        set_transient($this->cache_key, $body, $this->check_interval);
        return $body;
    }

    /**
     * Limpa cache de verificação.
     */
    public function clear_cache(): void {
        delete_transient($this->cache_key);
    }
}

// Instanciar apenas no admin.
// ADR-002: quando o Hub está configurado (licença presente), o canal de
// actualização é o Hub (prioridade 99); o checker clássico fica em silêncio
// para não haver dois anúncios de update a competir. Sem Hub, o clássico
// continua a funcionar como sempre (fallback completo).
if (is_admin()) {
    $sige_hub_configurado = (string) get_option('sige_license_key', '') !== ''
        && file_exists(SIGE_PATH . 'includes/hub/class-sige-hub-update-checker.php');
    if (!$sige_hub_configurado) {
        new SIGE_Update_Checker();
    }
}
