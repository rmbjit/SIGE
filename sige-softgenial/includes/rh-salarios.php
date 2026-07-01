<?php
/**
 * SIGE SoftGenial - Recursos Humanos: Processamento de Salário (Fase 1).
 *
 * Calcula o vencimento mensal por colaborador: bruto (base + subsídios), menos
 * INSS, IRPS (retenção por escalões) e faltas injustificadas (da Assiduidade),
 * menos "outros". Gera recibo imprimível e guarda o processamento por mês.
 *
 * PRINCÍPIO DE SEGURANÇA FISCAL: nada de taxas "presas" no código. A taxa do
 * INSS e a TABELA do IRPS ficam numa CONFIGURAÇÃO por escola (option), editável
 * na aplicação e pré-preenchida com o padrão de Moçambique. Quando a lei muda,
 * edita-se a tabela — sem tocar em código. Os valores por omissão DEVEM ser
 * confirmados pela escola antes do uso real.
 *
 * Engenharia: cálculo em FUNÇÕES PURAS testáveis; tabela criada por código
 * (idempotente); tenant-scoped; AJAX gated por gestão, nonce e auditoria. Não
 * toca no módulo financeiro existente nem em ficheiros protegidos.
 */

if (!defined('ABSPATH')) exit;

/* ============================================================================
 * CONFIGURAÇÃO DE IMPOSTOS (editável por escola)
 * ========================================================================== */

if (!function_exists('sige_rh_salario_config_defaults')) {
    /**
     * Padrão de Moçambique — A CONFIRMAR/EDITAR pela escola.
     * IRPS: escalões mensais com taxa (%) e parcela a abater; 'ate' = limite
     * superior do escalão (0 = último, sem limite). IRPS incide, por omissão,
     * sobre (bruto − INSS) [irps_base='bruto_menos_inss'].
     */
    function sige_rh_salario_config_defaults(): array {
        return [
            'inss_trabalhador'        => 3.0,
            'inss_empregador'         => 4.0,
            'irps_base'               => 'bruto_menos_inss', // ou 'bruto'
            'irps_dependente_abater'  => 0.0,                 // MT a abater por dependente
            'irps_escaloes'           => [
                ['ate' => 20249.99, 'taxa' => 0,  'abater' => 0.00],
                ['ate' => 20749.99, 'taxa' => 10, 'abater' => 2025.00],
                ['ate' => 20999.99, 'taxa' => 15, 'abater' => 3062.50],
                ['ate' => 21249.99, 'taxa' => 20, 'abater' => 4112.50],
                ['ate' => 21749.99, 'taxa' => 25, 'abater' => 5175.00],
                ['ate' => 0,        'taxa' => 32, 'abater' => 6697.50],
            ],
            'confirmado' => 0, // a escola marca 1 quando validar a tabela em vigor
        ];
    }
}

if (!function_exists('sige_rh_salario_config_option')) {
    function sige_rh_salario_config_option(int $escola_id): string {
        return 'sige_rh_salario_config_' . (int) $escola_id;
    }
}

if (!function_exists('sige_rh_salario_config_normalizar')) {
    /** Sanitiza e completa a config (PURA sobre arrays). */
    function sige_rh_salario_config_normalizar($raw): array {
        $d = sige_rh_salario_config_defaults();
        if (!is_array($raw)) return $d;
        $out = $d;
        $out['inss_trabalhador'] = max(0.0, min(100.0, (float) ($raw['inss_trabalhador'] ?? $d['inss_trabalhador'])));
        $out['inss_empregador']  = max(0.0, min(100.0, (float) ($raw['inss_empregador'] ?? $d['inss_empregador'])));
        $out['irps_base']        = ($raw['irps_base'] ?? '') === 'bruto' ? 'bruto' : 'bruto_menos_inss';
        $out['irps_dependente_abater'] = max(0.0, (float) ($raw['irps_dependente_abater'] ?? $d['irps_dependente_abater']));
        $out['confirmado']       = !empty($raw['confirmado']) ? 1 : 0;
        if (!empty($raw['irps_escaloes']) && is_array($raw['irps_escaloes'])) {
            $esc = [];
            foreach ($raw['irps_escaloes'] as $e) {
                if (!is_array($e)) continue;
                $esc[] = [
                    'ate'    => max(0.0, (float) ($e['ate'] ?? 0)),
                    'taxa'   => max(0.0, min(100.0, (float) ($e['taxa'] ?? 0))),
                    'abater' => max(0.0, (float) ($e['abater'] ?? 0)),
                ];
            }
            if ($esc) $out['irps_escaloes'] = $esc;
        }
        return $out;
    }
}

if (!function_exists('sige_rh_salario_config_get')) {
    function sige_rh_salario_config_get(int $escola_id): array {
        $raw = get_option(sige_rh_salario_config_option($escola_id), null);
        return sige_rh_salario_config_normalizar($raw);
    }
}

if (!function_exists('sige_rh_salario_config_save')) {
    function sige_rh_salario_config_save(int $escola_id, $raw): array {
        $cfg = sige_rh_salario_config_normalizar($raw);
        update_option(sige_rh_salario_config_option($escola_id), $cfg, false);
        return $cfg;
    }
}

/* ============================================================================
 * CÁLCULO (FUNÇÕES PURAS)
 * ========================================================================== */

if (!function_exists('sige_rh_salario_inss')) {
    function sige_rh_salario_inss($bruto, $taxa): float {
        return round(max(0.0, (float) $bruto) * ((float) $taxa / 100), 2);
    }
}

if (!function_exists('sige_rh_salario_irps')) {
    /** IRPS por escalões: base × taxa − parcela a abater − (dependentes × abater/dependente). PURA. */
    function sige_rh_salario_irps($colectavel, array $escaloes, float $dependente_abater = 0.0, int $dependentes = 0): float {
        $colectavel = max(0.0, (float) $colectavel);
        $escolhido = null;
        foreach ($escaloes as $e) {
            $ate = (float) ($e['ate'] ?? 0);
            if ($ate <= 0 || $colectavel <= $ate) { $escolhido = $e; break; }
        }
        if ($escolhido === null) { $escolhido = end($escaloes) ?: ['taxa' => 0, 'abater' => 0]; }
        $irps = $colectavel * ((float) ($escolhido['taxa'] ?? 0) / 100)
              - (float) ($escolhido['abater'] ?? 0)
              - ($dependente_abater * max(0, $dependentes));
        return max(0.0, round($irps, 2));
    }
}

if (!function_exists('sige_rh_salario_desconto_faltas')) {
    function sige_rh_salario_desconto_faltas($bruto, int $dias_uteis, int $faltas): float {
        if ($dias_uteis <= 0 || $faltas <= 0) return 0.0;
        return round((float) $bruto / $dias_uteis * $faltas, 2);
    }
}

if (!function_exists('sige_rh_salario_calcular')) {
    /**
     * Cálculo completo de um vencimento (PURA). $in: salario_base, subsidio,
     * faltas_injustificadas, dias_uteis, outros, dependentes. $cfg: config.
     * @return array com bruto, inss, inss_empregador, colectavel, irps,
     *               desconto_faltas, outros, liquido e os inputs relevantes.
     */
    function sige_rh_salario_calcular(array $in, array $cfg): array {
        $cfg = sige_rh_salario_config_normalizar($cfg);
        $base = round((float) ($in['salario_base'] ?? 0), 2);
        $sub  = round((float) ($in['subsidio'] ?? 0), 2);
        $bruto = round($base + $sub, 2);

        $inss     = sige_rh_salario_inss($bruto, $cfg['inss_trabalhador']);
        $inss_emp = sige_rh_salario_inss($bruto, $cfg['inss_empregador']);

        $colectavel = ($cfg['irps_base'] === 'bruto') ? $bruto : round($bruto - $inss, 2);
        $irps = sige_rh_salario_irps($colectavel, $cfg['irps_escaloes'], (float) $cfg['irps_dependente_abater'], (int) ($in['dependentes'] ?? 0));

        $faltas = max(0, (int) ($in['faltas_injustificadas'] ?? 0));
        $du     = max(0, (int) ($in['dias_uteis'] ?? 0));
        $desc_faltas = sige_rh_salario_desconto_faltas($bruto, $du, $faltas);

        $outros  = round(max(0.0, (float) ($in['outros'] ?? 0)), 2);
        $liquido = round($bruto - $inss - $irps - $desc_faltas - $outros, 2);

        return [
            'salario_base'     => $base,
            'subsidio'         => $sub,
            'bruto'            => $bruto,
            'inss'             => $inss,
            'inss_empregador'  => $inss_emp,
            'colectavel'       => $colectavel,
            'irps'             => $irps,
            'faltas'           => $faltas,
            'dias_uteis'       => $du,
            'desconto_faltas'  => $desc_faltas,
            'outros'           => $outros,
            'liquido'          => $liquido,
        ];
    }
}

/* ============================================================================
 * MIGRAÇÃO (tabela de recibos)
 * ========================================================================== */

if (!function_exists('sige_rh_salarios_table')) {
    function sige_rh_salarios_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_rh_salarios';
    }
}

if (!function_exists('sige_rh_salarios_migrar')) {
    function sige_rh_salarios_migrar(): void {
        global $wpdb;
        $t = sige_rh_salarios_table();
        if (function_exists('sige_dbm_table_exists')) {
            if (sige_dbm_table_exists($t)) return;
        } else {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
            if ($found === $t) return;
        }
        if (!function_exists('dbDelta')) { require_once ABSPATH . 'wp-admin/includes/upgrade.php'; }
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$t} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id BIGINT UNSIGNED NOT NULL,
  professor_id BIGINT UNSIGNED NOT NULL,
  ano SMALLINT UNSIGNED NOT NULL,
  mes TINYINT UNSIGNED NOT NULL,
  bruto DECIMAL(12,2) NOT NULL DEFAULT 0,
  inss DECIMAL(12,2) NOT NULL DEFAULT 0,
  inss_empregador DECIMAL(12,2) NOT NULL DEFAULT 0,
  irps DECIMAL(12,2) NOT NULL DEFAULT 0,
  desconto_faltas DECIMAL(12,2) NOT NULL DEFAULT 0,
  outros DECIMAL(12,2) NOT NULL DEFAULT 0,
  liquido DECIMAL(12,2) NOT NULL DEFAULT 0,
  faltas INT NOT NULL DEFAULT 0,
  dias_uteis INT NOT NULL DEFAULT 0,
  estado VARCHAR(16) NOT NULL DEFAULT 'processado',
  criado_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL,
  atualizado_em DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_mes (escola_id, professor_id, ano, mes),
  KEY escola_periodo (escola_id, ano, mes)
) {$charset_collate};";
        dbDelta($sql);
        update_option('sige_rh_salarios_schema', '1', false);
    }
}
add_action('admin_init', 'sige_rh_salarios_migrar', 9);

/* ============================================================================
 * DADOS (tenant-scoped)
 * ========================================================================== */

if (!function_exists('sige_rh_salario_get')) {
    function sige_rh_salario_get(int $escola_id, int $professor_id, int $ano, int $mes) {
        global $wpdb;
        if ($escola_id <= 0 || $professor_id <= 0) return null;
        $t = sige_rh_salarios_table();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t} WHERE escola_id = %d AND professor_id = %d AND ano = %d AND mes = %d",
            $escola_id, $professor_id, $ano, $mes
        ));
    }
}

if (!function_exists('sige_rh_salario_preview')) {
    /**
     * Calcula (sem gravar) o vencimento de todos os colaboradores activos para
     * um mês, puxando faltas injustificadas da Assiduidade e 'outros' de um
     * eventual recibo já processado.
     */
    function sige_rh_salario_preview(int $escola_id, int $ano, int $mes): array {
        global $wpdb;
        $cfg = sige_rh_salario_config_get($escola_id);
        $du  = function_exists('sige_rh_dias_uteis_mes') ? sige_rh_dias_uteis_mes($ano, $mes) : 22;
        $out = ['ano' => $ano, 'mes' => $mes, 'dias_uteis' => $du, 'config' => $cfg, 'itens' => [], 'total_liquido' => 0.0];
        if ($escola_id <= 0) return $out;
        sige_rh_salarios_migrar();
        $tp = $wpdb->prefix . 'sige_professores';
        $profs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome_completo, nuit, salario_base, subsidio, email FROM {$tp}
              WHERE escola_id = %d AND (status_ativo IS NULL OR status_ativo = 1)
              ORDER BY nome_completo ASC",
            $escola_id
        ));
        $seen = [];
        foreach ((array) $profs as $p) {
            $pid = (int) $p->id;
            if ($pid <= 0 || isset($seen[$pid])) continue;
            $seen[$pid] = true;
            // Excluir o administrador WP real (utilizador de manutenção do sistema).
            if (function_exists('sige_rh_professor_e_admin_sistema') && sige_rh_professor_e_admin_sistema($p->email ?? '')) continue;

            $faltas = 0;
            if (function_exists('sige_rh_assiduidade_resumo_mes')) {
                $r = sige_rh_assiduidade_resumo_mes($escola_id, $pid, $ano, $mes);
                $faltas = (int) ($r['por_estado']['falta_injustificada'] ?? 0);
            }
            $saved = sige_rh_salario_get($escola_id, $pid, $ano, $mes);
            $outros = $saved ? (float) $saved->outros : 0.0;

            $calc = sige_rh_salario_calcular([
                'salario_base' => $p->salario_base, 'subsidio' => $p->subsidio,
                'faltas_injustificadas' => $faltas, 'dias_uteis' => $du, 'outros' => $outros, 'dependentes' => 0,
            ], $cfg);

            $out['itens'][] = array_merge([
                'professor_id' => $pid,
                'nome'         => (string) $p->nome_completo,
                'nuit'         => (string) ($p->nuit ?? ''),
                'processado'   => $saved ? 1 : 0,
            ], $calc);
            $out['total_liquido'] += (float) $calc['liquido'];
        }
        $out['total_liquido'] = round($out['total_liquido'], 2);
        return $out;
    }
}

if (!function_exists('sige_rh_salario_processar')) {
    /**
     * Grava (upsert) os recibos do mês. O servidor RECALCULA tudo (não confia
     * nos valores do cliente); do cliente só aceita 'outros' por colaborador.
     */
    function sige_rh_salario_processar(int $escola_id, int $ano, int $mes, array $outros_map, int $user_id): array {
        global $wpdb;
        if ($escola_id <= 0 || $ano < 2000 || $mes < 1 || $mes > 12) return ['ok' => false, 'erro' => 'Período inválido.', 'processados' => 0];
        sige_rh_salarios_migrar();
        $t = sige_rh_salarios_table();
        $prev = sige_rh_salario_preview($escola_id, $ano, $mes);
        $agora = current_time('mysql');
        $n = 0;
        foreach ($prev['itens'] as $it) {
            $pid = (int) $it['professor_id'];
            $outros = isset($outros_map[$pid]) ? round(max(0.0, (float) $outros_map[$pid]), 2) : (float) $it['outros'];
            // Recalcula com o 'outros' informado.
            $calc = sige_rh_salario_calcular([
                'salario_base' => $it['salario_base'], 'subsidio' => $it['subsidio'],
                'faltas_injustificadas' => $it['faltas'], 'dias_uteis' => $it['dias_uteis'], 'outros' => $outros, 'dependentes' => 0,
            ], $prev['config']);

            $fields = [
                'escola_id' => $escola_id, 'professor_id' => $pid, 'ano' => $ano, 'mes' => $mes,
                'bruto' => $calc['bruto'], 'inss' => $calc['inss'], 'inss_empregador' => $calc['inss_empregador'],
                'irps' => $calc['irps'], 'desconto_faltas' => $calc['desconto_faltas'], 'outros' => $calc['outros'],
                'liquido' => $calc['liquido'], 'faltas' => $calc['faltas'], 'dias_uteis' => $calc['dias_uteis'],
                'estado' => 'processado', 'atualizado_em' => $agora,
            ];
            $ex = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE escola_id = %d AND professor_id = %d AND ano = %d AND mes = %d", $escola_id, $pid, $ano, $mes));
            if ($ex > 0) { $wpdb->update($t, $fields, ['id' => $ex]); }
            else { $fields['criado_por'] = $user_id; $fields['criado_em'] = $agora; $wpdb->insert($t, $fields); }
            $n++;
        }
        return ['ok' => true, 'erro' => '', 'processados' => $n];
    }
}

if (!function_exists('sige_rh_salario_mapa_mes')) {
    /**
     * Mapa mensal para entrega (INSS/IRPS): lê os recibos JÁ PROCESSADOS do mês,
     * com nome e NUIT, e devolve linhas + totais. Só leitura.
     * @return array{ano:int,mes:int,itens:array,totais:array}
     */
    function sige_rh_salario_mapa_mes(int $escola_id, int $ano, int $mes): array {
        global $wpdb;
        $out = ['ano' => $ano, 'mes' => $mes, 'itens' => [], 'totais' => [
            'bruto' => 0.0, 'inss' => 0.0, 'inss_empregador' => 0.0, 'inss_total' => 0.0, 'irps' => 0.0, 'liquido' => 0.0,
        ]];
        if ($escola_id <= 0) return $out;
        sige_rh_salarios_migrar();
        $t  = sige_rh_salarios_table();
        $tp = $wpdb->prefix . 'sige_professores';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, p.nome_completo AS nome, p.nuit AS nuit, p.email AS email
               FROM {$t} s
               LEFT JOIN {$tp} p ON p.id = s.professor_id AND p.escola_id = s.escola_id
              WHERE s.escola_id = %d AND s.ano = %d AND s.mes = %d
              ORDER BY p.nome_completo ASC, s.id ASC",
            $escola_id, $ano, $mes
        ));
        foreach ((array) $rows as $r) {
            // Excluir o administrador WP real (utilizador de manutenção do sistema).
            if (function_exists('sige_rh_professor_e_admin_sistema') && sige_rh_professor_e_admin_sistema($r->email ?? '')) continue;
            $inss = (float) $r->inss; $inss_emp = (float) $r->inss_empregador;
            $out['itens'][] = [
                'professor_id'    => (int) $r->professor_id,
                'nome'            => (string) ($r->nome ?? ''),
                'nuit'            => (string) ($r->nuit ?? ''),
                'bruto'           => (float) $r->bruto,
                'inss'            => $inss,
                'inss_empregador' => $inss_emp,
                'inss_total'      => round($inss + $inss_emp, 2),
                'irps'            => (float) $r->irps,
                'liquido'         => (float) $r->liquido,
            ];
            $out['totais']['bruto']           += (float) $r->bruto;
            $out['totais']['inss']            += $inss;
            $out['totais']['inss_empregador'] += $inss_emp;
            $out['totais']['inss_total']      += ($inss + $inss_emp);
            $out['totais']['irps']            += (float) $r->irps;
            $out['totais']['liquido']         += (float) $r->liquido;
        }
        foreach ($out['totais'] as $k => $v) { $out['totais'][$k] = round($v, 2); }
        return $out;
    }
}

/* ============================================================================
 * AJAX (gated por gestão, nonce, tenant-scope, auditado)
 * ========================================================================== */

if (!function_exists('sige_rh_salario_ajax_guard')) {
    function sige_rh_salario_ajax_guard(): int {
        if (function_exists('sige_ajax_equipe_begin_buffer')) sige_ajax_equipe_begin_buffer();
        $nonce = isset($_POST['_sige_nonce']) ? sanitize_text_field(wp_unslash($_POST['_sige_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_equipe_action')) wp_send_json_error('Sessão expirada.');
        $pode = function_exists('sige_ajax_equipe_can_manage') ? sige_ajax_equipe_can_manage()
              : (function_exists('sige_can') ? sige_can('rh.equipe_gerir') : current_user_can('manage_options'));
        if (!$pode) wp_send_json_error('Sem permissão para processar salários.');
        $escola_id = function_exists('sige_ajax_equipe_escola_id') ? (int) sige_ajax_equipe_escola_id()
                   : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($escola_id <= 0) wp_send_json_error('Escola não identificada.');
        return $escola_id;
    }
}

if (!function_exists('sige_rh_salario_periodo_post')) {
    function sige_rh_salario_periodo_post(): array {
        $ano = isset($_POST['ano']) ? (int) $_POST['ano'] : 0;
        $mes = isset($_POST['mes']) ? (int) $_POST['mes'] : 0;
        $now_a = (int) (function_exists('wp_date') ? wp_date('Y') : date('Y'));
        $now_m = (int) (function_exists('wp_date') ? wp_date('n') : date('n'));
        if ($ano < 2000 || $ano > 2100) $ano = $now_a;
        if ($mes < 1 || $mes > 12) $mes = $now_m;
        return [$ano, $mes];
    }
}

add_action('wp_ajax_sige_rh_salario_preview', function () {
    $escola_id = sige_rh_salario_ajax_guard();
    list($ano, $mes) = sige_rh_salario_periodo_post();
    wp_send_json_success(sige_rh_salario_preview($escola_id, $ano, $mes));
});

add_action('wp_ajax_sige_rh_salario_processar', function () {
    $escola_id = sige_rh_salario_ajax_guard();
    list($ano, $mes) = sige_rh_salario_periodo_post();
    $raw = isset($_POST['outros']) ? wp_unslash($_POST['outros']) : '[]';
    $arr = json_decode((string) $raw, true);
    $map = [];
    if (is_array($arr)) {
        foreach ($arr as $row) {
            $pid = (int) ($row['professor_id'] ?? 0);
            if ($pid > 0) $map[$pid] = (float) ($row['outros'] ?? 0);
        }
    }
    $res = sige_rh_salario_processar($escola_id, $ano, $mes, $map, get_current_user_id());
    if (!$res['ok']) wp_send_json_error($res['erro']);
    if (function_exists('sige_ajax_equipe_audit')) {
        sige_ajax_equipe_audit('rh_salario_processado', ['ano' => $ano, 'mes' => $mes, 'processados' => $res['processados'], 'escola_id' => $escola_id, 'resultado' => 'Processamento de salário gravado.']);
    }
    wp_send_json_success(['processados' => $res['processados'], 'ano' => $ano, 'mes' => $mes]);
});

add_action('wp_ajax_sige_rh_salario_config_guardar', function () {
    $escola_id = sige_rh_salario_ajax_guard();
    $raw = isset($_POST['config']) ? wp_unslash($_POST['config']) : '';
    $cfg_in = json_decode((string) $raw, true);
    if (!is_array($cfg_in)) wp_send_json_error('Configuração inválida.');
    $cfg = sige_rh_salario_config_save($escola_id, $cfg_in);
    if (function_exists('sige_ajax_equipe_audit')) {
        sige_ajax_equipe_audit('rh_salario_config', ['escola_id' => $escola_id, 'resultado' => 'Configuração de impostos actualizada.']);
    }
    wp_send_json_success(['config' => $cfg]);
});

add_action('wp_ajax_sige_rh_salario_mapa', function () {
    $escola_id = sige_rh_salario_ajax_guard();
    list($ano, $mes) = sige_rh_salario_periodo_post();
    $mapa = sige_rh_salario_mapa_mes($escola_id, $ano, $mes);
    if (function_exists('sige_ajax_equipe_audit')) {
        sige_ajax_equipe_audit('rh_salario_mapa', ['ano' => $ano, 'mes' => $mes, 'escola_id' => $escola_id, 'resultado' => 'Mapa fiscal consultado.']);
    }
    wp_send_json_success($mapa);
});
