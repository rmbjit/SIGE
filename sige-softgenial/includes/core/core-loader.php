<?php
/** Carregador da fundação SoftGenial Core v1.0. */
if (!defined('ABSPATH')) exit;

require_once SIGE_PATH . 'includes/infra/class-sige-logger.php';
require_once SIGE_PATH . 'includes/infra/class-sige-queue.php';
require_once SIGE_PATH . 'includes/core/class-sige-core.php';
require_once SIGE_PATH . 'includes/licensing/class-sige-license.php';
require_once SIGE_PATH . 'includes/core/class-sige-diagnostics.php';

SIGE_Core::boot();

// [12.9.9.9] Limpeza de cron legado removido do financeiro.
add_action('init', function () {
    $hook = 'sige_processar_cobrancas_v90';
    $ts = wp_next_scheduled($hook);
    while ($ts) {
        wp_unschedule_event($ts, $hook);
        $ts = wp_next_scheduled($hook);
    }
}, 1);
