<?php
/**
 * SIGE SoftGenial - Alertas Cron
 * Ficheiro: includes/alertas-cron.php
 *
 * Cron diário do Sistema de Alertas v1.
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │  ESTRATÉGIA DUAL (defesa em profundidade contra pseudo-cron WP):  │
 * │                                                                    │
 * │  1. CANÓNICO - sige_alertas_cron_daily                             │
 * │     Agendado timezone-aware para 07h00 Maputo via wp_schedule_event.│
 * │     Se o pseudo-cron dispara na janela, corre em todas as escolas. │
 * │                                                                    │
 * │  2. FALLBACK - hook sige_evento_diario (já existente em cron-tasks)│
 * │     Se o canónico falhou (ex: site sem tráfego às 7h), na 1ª       │
 * │     visita admin do dia detecta que 'sige_alertas_ultima_execucao' │
 * │     ≠ hoje e corre os detectores. Idempotente no mesmo dia.        │
 * │                                                                    │
 * │  DEPLOY: para garantia total, adicionar CRON REAL no Hostinger     │
 * │  (cPanel → Cron Jobs) a bater em wp-cron.php?doing_wp_cron às 07h. │
 * │  Sem isso, o fallback cobre. Com isso, é relógio suíço.            │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * @since   13.5.0
 * @version 13.5.0-T1 (2026-04-19) - Turno 1: schema + detecção
 * @author  RMBJ Consultoria
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// CONSTANTES
// ============================================================================
// Timezone de referência - Casa Colorida opera em Maputo, fixo.
// Evita confusão quando o servidor Hostinger está em UTC.
if (!defined('SIGE_ALERTAS_TZ_OPERACIONAL')) {
    define('SIGE_ALERTAS_TZ_OPERACIONAL', 'Africa/Maputo');
}
if (!defined('SIGE_ALERTAS_HORA_CRON')) {
    define('SIGE_ALERTAS_HORA_CRON', 7); // 07h00 Maputo
}
// Option que guarda a data (YYYY-MM-DD) da última execução, para idempotência
// diária do fallback.
if (!defined('SIGE_ALERTAS_OPT_ULTIMA_EXECUCAO')) {
    define('SIGE_ALERTAS_OPT_ULTIMA_EXECUCAO', 'sige_alertas_ultima_execucao');
}

// ============================================================================
// HELPER - calcular próximo timestamp UTC para 07h00 Maputo
// ============================================================================
// PHP strtotime/mktime usam timezone do servidor. WP_TIMEZONE pode variar.
// Para garantir 07h00 LOCAL (Maputo), construímos DateTimeImmutable com TZ
// explícito e convertemos para timestamp UTC no fim.
// ============================================================================
if (!function_exists('sige_alertas_proximo_cron_ts')) {
    function sige_alertas_proximo_cron_ts(): int {
        try {
            $tz   = new DateTimeZone(SIGE_ALERTAS_TZ_OPERACIONAL);
            $now  = new DateTimeImmutable('now', $tz);
            $alvo = $now->setTime(SIGE_ALERTAS_HORA_CRON, 0, 0);
            if ($now >= $alvo) {
                $alvo = $alvo->modify('+1 day');
            }
            return $alvo->getTimestamp(); // UTC epoch - WP cron espera isto
        } catch (\Throwable $e) {
            // Fallback seguro: +24h
            return time() + DAY_IN_SECONDS;
        }
    }
}

// ============================================================================
// AGENDAMENTO - regista o hook no init
// ============================================================================
add_action('init', function () {
    if (!wp_next_scheduled('sige_alertas_cron_daily')) {
        wp_schedule_event(
            sige_alertas_proximo_cron_ts(),
            'daily',
            'sige_alertas_cron_daily'
        );
    }
});

// ============================================================================
// DESAGENDAR na desactivação do plugin (limpeza)
// ============================================================================
register_deactivation_hook(SIGE_PATH . 'sige-softgenial.php', function () {
    $ts = wp_next_scheduled('sige_alertas_cron_daily');
    if ($ts) {
        wp_unschedule_event($ts, 'sige_alertas_cron_daily');
    }
    wp_clear_scheduled_hook('sige_alertas_cron_daily');
});

// ============================================================================
// HANDLER CANÓNICO - dispara às 07h Maputo (ou quando o pseudo-cron alcança)
// ============================================================================
add_action('sige_alertas_cron_daily', 'sige_alertas_handler_diario');

if (!function_exists('sige_alertas_handler_diario')) {
    function sige_alertas_handler_diario(): void {
        // Garantir que a API de alertas está carregada
        if (!function_exists('sige_alertas_correr_deteccoes')) {
            return;
        }

        $hoje = wp_date('Y-m-d');

        // Se já correu hoje por qualquer caminho, não repete
        $ultima = (string)get_option(SIGE_ALERTAS_OPT_ULTIMA_EXECUCAO, '');
        if ($ultima === $hoje) {
            return;
        }

        // Lista de escolas activas - reutiliza helper do cron-tasks.php
        $escolas = function_exists('sige_cron_get_escolas')
            ? sige_cron_get_escolas()
            : [(object)['id' => 1]];

        $stats_global = [
            'data'               => $hoje,
            'escolas_processadas'=> 0,
            'total_novos'        => 0,
            'total_resolvidos'   => 0,
            'tempo_total_ms'     => 0,
        ];
        $t0 = microtime(true);

        foreach ($escolas as $e) {
            $eid = (int)($e->id ?? 0);
            if ($eid <= 0) continue;

            $stats = sige_alertas_correr_deteccoes($eid);
            $stats_global['escolas_processadas']++;
            $stats_global['total_novos']      += (int)($stats['total_novos']      ?? 0);
            $stats_global['total_resolvidos'] += (int)($stats['auto_resolvidos']  ?? 0);
        }

        $stats_global['tempo_total_ms'] = (int)round((microtime(true) - $t0) * 1000);

        // Marcar "correu hoje" - bloqueia fallback para o resto do dia
        update_option(SIGE_ALERTAS_OPT_ULTIMA_EXECUCAO, $hoje, false);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('alertas_cron_diario_concluido', $stats_global);
        }
    }
}

// ============================================================================
// FALLBACK - pendurar em sige_evento_diario (já existente em cron-tasks.php)
// ============================================================================
// Se o canónico não disparou (ex: site sem tráfego admin entre 07h e agora),
// a primeira vez que o sige_evento_diario correr hoje chama o mesmo handler.
// A guarda "já correu hoje" dentro do handler evita dupla execução.
// ============================================================================
add_action('sige_evento_diario', 'sige_alertas_handler_diario', 20);
// Prioridade 20 para correr depois dos handlers financeiros já existentes
// (recalcular multas, lembretes pré-vencimento). A ordem importa porque:
//   • Multas podem mudar saldo → detector de atraso quer ver saldo já actualizado
//   • Lembretes já filtram devedores de hoje → alertas ficam com dados frescos

// ============================================================================
// TRIGGER MANUAL (para o Turno 2 - botão "Correr cron agora" no admin)
// ============================================================================
// Função pública que um AJAX handler pode chamar. Ignora a guarda de data
// para permitir teste pelo Director sem esperar 24h. Devolve stats detalhadas.
// ============================================================================
if (!function_exists('sige_alertas_correr_agora_manual')) {
    function sige_alertas_correr_agora_manual(): array {
        if (!function_exists('sige_alertas_correr_deteccoes')) {
            return ['erro' => 'alertas-core.php não carregado'];
        }

        $escolas = function_exists('sige_cron_get_escolas')
            ? sige_cron_get_escolas()
            : [(object)['id' => 1]];

        $por_escola = [];
        $totais = ['novos' => 0, 'resolvidos' => 0];
        $t0 = microtime(true);

        foreach ($escolas as $e) {
            $eid = (int)($e->id ?? 0);
            if ($eid <= 0) continue;
            $stats = sige_alertas_correr_deteccoes($eid);
            $por_escola[$eid] = $stats;
            $totais['novos']      += (int)($stats['total_novos']      ?? 0);
            $totais['resolvidos'] += (int)($stats['auto_resolvidos']  ?? 0);
        }

        // Também marca "correu hoje" - útil se o Director usa o manual às 07h01
        update_option(SIGE_ALERTAS_OPT_ULTIMA_EXECUCAO, wp_date('Y-m-d'), false);

        return [
            'por_escola'      => $por_escola,
            'totais'          => $totais,
            'duracao_ms'      => (int)round((microtime(true) - $t0) * 1000),
            'timestamp'       => current_time('mysql'),
            'proximo_auto_em' => wp_date('Y-m-d H:i:s', sige_alertas_proximo_cron_ts()),
        ];
    }
}

// ============================================================================
// INTROSPECÇÃO - útil no admin para mostrar "último run foi X"
// ============================================================================
if (!function_exists('sige_alertas_info_cron')) {
    function sige_alertas_info_cron(): array {
        $ultima   = (string)get_option(SIGE_ALERTAS_OPT_ULTIMA_EXECUCAO, '');
        $proximo  = wp_next_scheduled('sige_alertas_cron_daily');
        return [
            'ultima_execucao_data' => $ultima,
            'correu_hoje'          => ($ultima === wp_date('Y-m-d')),
            'proximo_scheduled_ts' => $proximo ? (int)$proximo : 0,
            'proximo_scheduled_br' => $proximo ? wp_date('Y-m-d H:i:s', $proximo) : '-',
            'hora_alvo_maputo'     => sprintf('%02d:00', SIGE_ALERTAS_HORA_CRON),
        ];
    }
}
