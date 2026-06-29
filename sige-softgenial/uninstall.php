<?php
/**
 * SIGE SoftGenial - Desinstalação
 *
 * POLÍTICA DELIBERADA: a desinstalação NUNCA apaga dados da escola.
 * Tabelas de alunos, matrículas, lançamentos financeiros, notas, recibos,
 * presenças e auditoria são património da instituição e permanecem na base
 * de dados. Uma reinstalação do plugin reencontra tudo no estado em que ficou.
 *
 * O que este ficheiro limpa:
 * - Eventos agendados (cron) registados pelo SIGE;
 * - Transients temporários com prefixo do plugin.
 *
 * Qualquer remoção definitiva de dados deve ser uma decisão explícita do
 * administrador, feita por backup + operação manual, nunca um efeito
 * colateral de remover o plugin.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1) Remover eventos agendados do SIGE.
$sige_cron_hooks = array(
    'sige_processar_whatsapp_queue',
    'sige_processar_email_queue',
    'sige_evento_diario',
    'sige_semanal',
    'sige_conciliacao_semanal',
    'sige_alertas_cron_daily',
    'sige_hub_heartbeat_send',
);
foreach ($sige_cron_hooks as $sige_hook) {
    $sige_ts = wp_next_scheduled($sige_hook);
    while ($sige_ts) {
        wp_unschedule_event($sige_ts, $sige_hook);
        $sige_ts = wp_next_scheduled($sige_hook);
    }
}

// 2) Limpar transients temporários do plugin (prefixo sige_).
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\\_transient\\_sige\\_%'
        OR option_name LIKE '\\_transient\\_timeout\\_sige\\_%'"
);
