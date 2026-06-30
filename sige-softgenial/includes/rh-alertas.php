<?php
/**
 * SIGE SoftGenial - Alertas de RH (contratos a expirar).
 *
 * Primeiro tijolo do módulo de Recursos Humanos: avisos de fim de contrato.
 * Só LEITURA de campos que já existem (sige_professores.fim_contrato /
 * tipo_contrato / status_ativo). Sem schema, sem writes, sem fórmulas
 * financeiras, sem alteração de permissões. Tenant-scoped por escola.
 *
 * A lógica de avaliação é uma função PURA (sige_rh_evaluate_contract_alerts)
 * para ser testável sem base de dados; a função com query apenas a alimenta.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_rh_contract_alert_thresholds')) {
    /**
     * Limiares (em dias) para classificar um contrato:
     *  - 'critico': falta este nº de dias ou menos -> vermelho;
     *  - 'aviso'  : falta este nº de dias ou menos -> âmbar (acima disto: sem alerta).
     * Expirado (data já passada) é sempre crítico/vermelho.
     *
     * @return array{critico:int,aviso:int}
     */
    function sige_rh_contract_alert_thresholds(): array {
        $defaults = ['critico' => 30, 'aviso' => 90];
        $out = function_exists('apply_filters') ? apply_filters('sige_rh_contract_alert_thresholds', $defaults) : $defaults;
        return [
            'critico' => max(1, (int)($out['critico'] ?? $defaults['critico'])),
            'aviso'   => max(1, (int)($out['aviso'] ?? $defaults['aviso'])),
        ];
    }
}

if (!function_exists('sige_rh_contract_label')) {
    /** Rótulo humano do tipo de vínculo (alinhado com o formulário). */
    function sige_rh_contract_label(string $slug): string {
        $map = [
            'efectivo' => 'Efectivo (Quadro)',
            'contrato' => 'Contrato a Prazo',
            'estagio'  => 'Estagiário',
        ];
        $slug = trim($slug);
        if ($slug === '') return 'Vínculo não definido';
        return $map[$slug] ?? ucfirst(str_replace('_', ' ', $slug));
    }
}

if (!function_exists('sige_rh_evaluate_contract_alerts')) {
    /**
     * Avalia alertas de contrato a partir de linhas já carregadas (função PURA).
     *
     * @param array  $rows  Linhas de sige_professores (objecto ou array) com
     *                      nome_completo, tipo_contrato, fim_contrato, status_ativo.
     * @param string $today Data de referência 'Y-m-d' (normalmente wp_date('Y-m-d')).
     * @param array  $opts  ['critico'=>int,'aviso'=>int] para sobrepor limiares.
     * @return array{items:array,counts:array,thresholds:array}
     */
    function sige_rh_evaluate_contract_alerts(array $rows, string $today, array $opts = []): array {
        $th = sige_rh_contract_alert_thresholds();
        $critico = max(1, (int)($opts['critico'] ?? $th['critico']));
        $aviso   = max($critico, (int)($opts['aviso'] ?? $th['aviso']));

        $today_ts = strtotime($today . ' 00:00:00');
        if ($today_ts === false) { $today_ts = strtotime('today'); }

        $items = [];
        foreach ($rows as $row) {
            $r = (array)$row;
            $status = array_key_exists('status_ativo', $r) ? (int)$r['status_ativo'] : 1;
            if ($status === 0) continue; // só colaboradores activos

            $fim = isset($r['fim_contrato']) ? trim((string)$r['fim_contrato']) : '';
            if ($fim === '' || $fim === '0000-00-00') continue; // sem data de término -> sem alerta
            $fim_ts = strtotime($fim . ' 00:00:00');
            if ($fim_ts === false) continue;

            $dias = (int)floor(($fim_ts - $today_ts) / 86400);
            if ($dias > $aviso) continue; // ainda longe -> sem alerta

            $estado = $dias < 0 ? 'expirado' : ($dias <= $critico ? 'critico' : 'aviso');
            $items[] = [
                'nome'          => isset($r['nome_completo']) ? (string)$r['nome_completo'] : '',
                'tipo_contrato' => isset($r['tipo_contrato']) ? (string)$r['tipo_contrato'] : '',
                'fim_contrato'  => $fim,
                'dias'          => $dias,
                'estado'        => $estado,
            ];
        }

        // Mais urgente primeiro: expirados (dias negativos) e depois os que faltam menos dias.
        usort($items, static function ($a, $b) { return $a['dias'] <=> $b['dias']; });

        $counts = ['expirado' => 0, 'critico' => 0, 'aviso' => 0];
        foreach ($items as $it) { $counts[$it['estado']]++; }
        $counts['total'] = count($items);

        return ['items' => $items, 'counts' => $counts, 'thresholds' => ['critico' => $critico, 'aviso' => $aviso]];
    }
}

if (!function_exists('sige_rh_contract_alerts')) {
    /**
     * Alertas de contrato de uma escola (consulta + avaliação).
     * Reutilize sige_rh_evaluate_contract_alerts() quando já tiver as linhas
     * carregadas (evita 2.ª query no ecrã da Equipa).
     *
     * @return array{items:array,counts:array,thresholds:array}
     */
    function sige_rh_contract_alerts(int $escola_id, array $opts = []): array {
        global $wpdb;
        if ($escola_id <= 0) {
            return ['items' => [], 'counts' => ['expirado' => 0, 'critico' => 0, 'aviso' => 0, 'total' => 0], 'thresholds' => sige_rh_contract_alert_thresholds()];
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT nome_completo, tipo_contrato, fim_contrato, status_ativo
               FROM {$wpdb->prefix}sige_professores
              WHERE escola_id = %d",
            $escola_id
        ));
        $today = function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d');
        return sige_rh_evaluate_contract_alerts((array)$rows, $today, $opts);
    }
}

if (!function_exists('sige_rh_contract_alert_phrase')) {
    /** Frase curta e humana para os dias restantes/decorridos. */
    function sige_rh_contract_alert_phrase(int $dias): string {
        if ($dias < 0) {
            $d = abs($dias);
            return $d === 1 ? 'expirou ontem' : 'expirou há ' . $d . ' dias';
        }
        if ($dias === 0) return 'expira hoje';
        if ($dias === 1) return 'expira amanhã';
        return 'faltam ' . $dias . ' dias';
    }
}
