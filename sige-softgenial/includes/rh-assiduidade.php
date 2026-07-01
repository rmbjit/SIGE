<?php
/**
 * SIGE SoftGenial - Recursos Humanos: Assiduidade / Ponto (Fase 1).
 *
 * Marcação diária de assiduidade da equipa (gerida pelo RH): escolhe-se um dia e
 * marca-se o estado de cada colaborador. Integra com as Férias & Ausências: um
 * dia coberto por uma ausência APROVADA aparece bloqueado ("em ausência") e não
 * é sobreposto nem conta como falta. Multi-tenant por escola.
 *
 * Engenharia (igual ao resto do módulo RH):
 *  - Tabela {prefix}sige_rh_assiduidade criada POR CÓDIGO (idempotente).
 *  - Funções PURAS testáveis (dias úteis do mês; rótulos).
 *  - Camada de dados tenant-scoped; AJAX gated por gestão, nonce e auditoria.
 *  - Não cria permissões novas; não toca em ficheiros protegidos.
 *  - O resumo mensal alimentará o Processamento de Salário (fase seguinte).
 */

if (!defined('ABSPATH')) exit;

/* ============================================================================
 * ROTULAGEM
 * ========================================================================== */

if (!function_exists('sige_rh_assiduidade_estados')) {
    /** Estados (slug => rótulo). */
    function sige_rh_assiduidade_estados(): array {
        return [
            'presente'            => 'Presente',
            'falta_justificada'   => 'Falta justificada',
            'falta_injustificada' => 'Falta injustificada',
            'atraso'              => 'Atraso',
            'meio_dia'            => 'Meio dia',
            'folga'               => 'Folga',
            'feriado'             => 'Feriado',
        ];
    }
}

if (!function_exists('sige_rh_assiduidade_estado_label')) {
    function sige_rh_assiduidade_estado_label(string $slug): string {
        $m = sige_rh_assiduidade_estados();
        $slug = trim($slug);
        return $m[$slug] ?? ($slug === '' ? '—' : ucfirst(str_replace('_', ' ', $slug)));
    }
}

/* ============================================================================
 * FUNÇÕES PURAS
 * ========================================================================== */

if (!function_exists('sige_rh_dias_uteis_mes')) {
    /** Nº de dias úteis (Seg–Sex) de um mês. PURA. Sem feriados (não há tabela). */
    function sige_rh_dias_uteis_mes(int $ano, int $mes): int {
        if ($mes < 1 || $mes > 12 || $ano < 1970) return 0;
        $dim = (int) date('t', mktime(0, 0, 0, $mes, 1, $ano));
        $n = 0;
        for ($d = 1; $d <= $dim; $d++) {
            $w = (int) date('N', mktime(0, 0, 0, $mes, $d, $ano));
            if ($w < 6) $n++;
        }
        return $n;
    }
}

/* ============================================================================
 * MIGRAÇÃO (criação idempotente da tabela)
 * ========================================================================== */

if (!function_exists('sige_rh_assiduidade_table')) {
    function sige_rh_assiduidade_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_rh_assiduidade';
    }
}

if (!function_exists('sige_rh_assiduidade_migrar')) {
    function sige_rh_assiduidade_migrar(): void {
        global $wpdb;
        $t = sige_rh_assiduidade_table();
        if (function_exists('sige_dbm_table_exists')) {
            if (sige_dbm_table_exists($t)) return;
        } else {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t));
            if ($found === $t) return;
        }
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$t} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id BIGINT UNSIGNED NOT NULL,
  professor_id BIGINT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  estado VARCHAR(24) NOT NULL DEFAULT 'presente',
  minutos_atraso INT NOT NULL DEFAULT 0,
  observacao VARCHAR(255) NULL,
  origem VARCHAR(16) NOT NULL DEFAULT 'manual',
  criado_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL,
  atualizado_em DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_dia (escola_id, professor_id, data),
  KEY escola_data (escola_id, data)
) {$charset_collate};";
        dbDelta($sql);
        update_option('sige_rh_assiduidade_schema', '1', false);
    }
}
add_action('admin_init', 'sige_rh_assiduidade_migrar', 8);

/* ============================================================================
 * CAMADA DE DADOS (tenant-scoped)
 * ========================================================================== */

if (!function_exists('sige_rh_professor_e_admin_sistema')) {
    /**
     * Verdadeiro se a linha de colaborador (sige_professores) pertence ao
     * administrador WordPress REAL — o "super admin" de manutenção do sistema,
     * que NÃO faz parte da escola. Serve para o excluir das listagens de
     * colaboradores (ausências, assiduidade, salários e mapas fiscais), tal como
     * já é excluído da própria aba Equipa.
     *
     * IMPORTANTE: usa EXACTAMENTE o mesmo critério da aba Equipa — resolve o
     * utilizador WP pelo email (a chave de ligação do roster RH, pois
     * sige_professores não tem user_id) e delega em sige_is_real_wp_admin_user().
     * Assim apanha também o super admin de MULTISITE (guardado numa opção do
     * site, muitas vezes SEM a role 'administrator'), que uma enumeração por role
     * deixaria passar. Cache por email durante o pedido.
     */
    function sige_rh_professor_e_admin_sistema($email_professor): bool {
        static $cache = [];
        $mail = strtolower(trim((string) $email_professor));
        if ($mail === '') return false;
        if (array_key_exists($mail, $cache)) return $cache[$mail];
        $is_admin = false;
        if (function_exists('get_user_by') && function_exists('sige_is_real_wp_admin_user')) {
            $u = get_user_by('email', $mail);
            if ($u && isset($u->ID)) $is_admin = sige_is_real_wp_admin_user((int) $u->ID);
        }
        $cache[$mail] = $is_admin;
        return $is_admin;
    }
}

if (!function_exists('sige_rh_assiduidade_ausencias_do_dia')) {
    /** Mapa professor_id => tipo de ausência APROVADA que cobre o dia. */
    function sige_rh_assiduidade_ausencias_do_dia(int $escola_id, string $data): array {
        global $wpdb;
        $map = [];
        $tau = $wpdb->prefix . 'sige_rh_ausencias';
        // A tabela de ausências pode ainda não existir (módulo carregado depois).
        if (function_exists('sige_dbm_table_exists') && !sige_dbm_table_exists($tau)) return $map;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT professor_id, tipo FROM {$tau}
              WHERE escola_id = %d AND estado = 'aprovada' AND data_inicio <= %s AND data_fim >= %s",
            $escola_id, $data, $data
        ));
        foreach ((array) $rows as $r) { $map[(int) $r->professor_id] = (string) $r->tipo; }
        return $map;
    }
}

if (!function_exists('sige_rh_assiduidade_grelha')) {
    /**
     * Grelha de marcação de um dia: um registo por colaborador activo, com o
     * estado já marcado (se houver) e a ausência que cobre o dia (se houver).
     */
    function sige_rh_assiduidade_grelha(int $escola_id, string $data): array {
        global $wpdb;
        if ($escola_id <= 0 || $data === '' || strtotime($data) === false) return [];
        sige_rh_assiduidade_migrar();
        $ta = sige_rh_assiduidade_table();
        $tp = $wpdb->prefix . 'sige_professores';

        $profs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome_completo, email FROM {$tp}
              WHERE escola_id = %d AND (status_ativo IS NULL OR status_ativo = 1)
              ORDER BY nome_completo ASC",
            $escola_id
        ));

        $recs = $wpdb->get_results($wpdb->prepare(
            "SELECT professor_id, estado, minutos_atraso FROM {$ta} WHERE escola_id = %d AND data = %s",
            $escola_id, $data
        ));
        $recmap = [];
        foreach ((array) $recs as $r) { $recmap[(int) $r->professor_id] = $r; }

        $ausmap = sige_rh_assiduidade_ausencias_do_dia($escola_id, $data);

        $out = [];
        $seen = [];
        foreach ((array) $profs as $p) {
            $pid = (int) $p->id;
            if ($pid <= 0 || isset($seen[$pid])) continue;
            $seen[$pid] = true;
            // Excluir o administrador WP real (utilizador de manutenção do sistema).
            if (sige_rh_professor_e_admin_sistema($p->email ?? '')) continue;
            $nome = trim((string) $p->nome_completo);
            if ($nome === '') continue;
            $rec = $recmap[$pid] ?? null;
            $cov = $ausmap[$pid] ?? '';
            $out[] = [
                'professor_id'   => $pid,
                'nome'           => $nome,
                'estado'         => $rec ? (string) $rec->estado : '',
                'minutos_atraso' => $rec ? (int) $rec->minutos_atraso : 0,
                'ausencia'       => $cov !== '' ? ['tipo' => $cov, 'label' => function_exists('sige_rh_ausencia_tipo_label') ? sige_rh_ausencia_tipo_label($cov) : $cov] : null,
            ];
        }
        return $out;
    }
}

if (!function_exists('sige_rh_assiduidade_professor_valido')) {
    function sige_rh_assiduidade_professor_valido(int $professor_id, int $escola_id): bool {
        if (function_exists('sige_rh_ausencia_professor_valido')) {
            return sige_rh_ausencia_professor_valido($professor_id, $escola_id);
        }
        global $wpdb;
        if ($professor_id <= 0 || $escola_id <= 0) return false;
        $tp = $wpdb->prefix . 'sige_professores';
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tp} WHERE id = %d AND escola_id = %d", $professor_id, $escola_id)) > 0;
    }
}

if (!function_exists('sige_rh_assiduidade_guardar_lote')) {
    /**
     * Grava a marcação de um dia em lote. Não sobrepõe dias cobertos por
     * ausência aprovada. Estado vazio remove a marcação existente.
     * @return array{ok:bool,erro:string,guardados:int}
     */
    function sige_rh_assiduidade_guardar_lote(int $escola_id, string $data, array $itens, int $user_id): array {
        global $wpdb;
        if ($escola_id <= 0 || $data === '' || strtotime($data) === false) {
            return ['ok' => false, 'erro' => 'Data inválida.', 'guardados' => 0];
        }
        sige_rh_assiduidade_migrar();
        $ta = sige_rh_assiduidade_table();
        $estados = sige_rh_assiduidade_estados();
        $covered = sige_rh_assiduidade_ausencias_do_dia($escola_id, $data);
        $agora = current_time('mysql');
        $n = 0;

        foreach ($itens as $it) {
            $pid = (int) ($it['professor_id'] ?? 0);
            if ($pid <= 0 || isset($covered[$pid])) continue; // não sobrepor ausência
            if (!sige_rh_assiduidade_professor_valido($pid, $escola_id)) continue;

            $estado = trim((string) ($it['estado'] ?? ''));
            $min = (int) ($it['minutos_atraso'] ?? 0);
            if ($min < 0) $min = 0;

            if ($estado === '') {
                $wpdb->delete($ta, ['escola_id' => $escola_id, 'professor_id' => $pid, 'data' => $data]);
                $n++;
                continue;
            }
            if (!isset($estados[$estado])) continue;
            if ($estado !== 'atraso') $min = 0;

            $ex = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$ta} WHERE escola_id = %d AND professor_id = %d AND data = %s",
                $escola_id, $pid, $data
            ));
            if ($ex > 0) {
                $wpdb->update($ta, ['estado' => $estado, 'minutos_atraso' => $min, 'origem' => 'manual', 'atualizado_em' => $agora], ['id' => $ex]);
            } else {
                $wpdb->insert($ta, ['escola_id' => $escola_id, 'professor_id' => $pid, 'data' => $data, 'estado' => $estado, 'minutos_atraso' => $min, 'origem' => 'manual', 'criado_por' => $user_id, 'criado_em' => $agora]);
            }
            $n++;
        }
        return ['ok' => true, 'erro' => '', 'guardados' => $n];
    }
}

if (!function_exists('sige_rh_assiduidade_resumo_mes')) {
    /**
     * Resumo mensal de assiduidade de UM colaborador (alimenta o salário).
     * @return array{ano:int,mes:int,dias_uteis:int,por_estado:array,total_marcado:int}
     */
    function sige_rh_assiduidade_resumo_mes(int $escola_id, int $professor_id, int $ano, int $mes): array {
        global $wpdb;
        $out = ['ano' => $ano, 'mes' => $mes, 'dias_uteis' => sige_rh_dias_uteis_mes($ano, $mes), 'por_estado' => [], 'total_marcado' => 0];
        if ($escola_id <= 0 || $professor_id <= 0) return $out;
        sige_rh_assiduidade_migrar();
        $ta = sige_rh_assiduidade_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT estado, COUNT(*) AS n FROM {$ta}
              WHERE escola_id = %d AND professor_id = %d AND YEAR(data) = %d AND MONTH(data) = %d
              GROUP BY estado",
            $escola_id, $professor_id, $ano, $mes
        ));
        foreach ((array) $rows as $r) {
            $out['por_estado'][(string) $r->estado] = (int) $r->n;
            $out['total_marcado'] += (int) $r->n;
        }
        return $out;
    }
}

/* ============================================================================
 * AJAX (gated por gestão, nonce, tenant-scope, auditado)
 * ========================================================================== */

if (!function_exists('sige_rh_assiduidade_ajax_guard')) {
    function sige_rh_assiduidade_ajax_guard(): int {
        if (function_exists('sige_ajax_equipe_begin_buffer')) sige_ajax_equipe_begin_buffer();
        $nonce = isset($_POST['_sige_nonce']) ? sanitize_text_field(wp_unslash($_POST['_sige_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_equipe_action')) wp_send_json_error('Sessão expirada.');
        $pode = function_exists('sige_ajax_equipe_can_manage') ? sige_ajax_equipe_can_manage()
              : (function_exists('sige_can') ? sige_can('rh.equipe_gerir') : current_user_can('manage_options'));
        if (!$pode) wp_send_json_error('Sem permissão para gerir assiduidade.');
        $escola_id = function_exists('sige_ajax_equipe_escola_id') ? (int) sige_ajax_equipe_escola_id()
                   : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($escola_id <= 0) wp_send_json_error('Escola não identificada.');
        return $escola_id;
    }
}

add_action('wp_ajax_sige_rh_assiduidade_grelha', function () {
    $escola_id = sige_rh_assiduidade_ajax_guard();
    $data = isset($_POST['data']) ? sanitize_text_field(wp_unslash($_POST['data'])) : '';
    if ($data === '' || strtotime($data) === false) $data = function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d');
    $itens = sige_rh_assiduidade_grelha($escola_id, $data);
    $out = [];
    foreach ($itens as $r) {
        $out[] = [
            'professor_id'   => (int) $r['professor_id'],
            'nome'           => (string) $r['nome'],
            'estado'         => (string) $r['estado'],
            'estado_label'   => $r['estado'] !== '' ? sige_rh_assiduidade_estado_label((string) $r['estado']) : '',
            'minutos_atraso' => (int) $r['minutos_atraso'],
            'ausencia'       => $r['ausencia'],
        ];
    }
    wp_send_json_success(['data' => $data, 'itens' => $out]);
});

add_action('wp_ajax_sige_rh_assiduidade_guardar', function () {
    $escola_id = sige_rh_assiduidade_ajax_guard();
    $data = isset($_POST['data']) ? sanitize_text_field(wp_unslash($_POST['data'])) : '';
    $raw = isset($_POST['itens']) ? wp_unslash($_POST['itens']) : '[]';
    $itens = json_decode((string) $raw, true);
    if (!is_array($itens)) $itens = [];
    // Sanitização mínima de cada item.
    $clean = [];
    foreach ($itens as $it) {
        $clean[] = [
            'professor_id'   => (int) ($it['professor_id'] ?? 0),
            'estado'         => sanitize_key((string) ($it['estado'] ?? '')),
            'minutos_atraso' => (int) ($it['minutos_atraso'] ?? 0),
        ];
    }
    $res = sige_rh_assiduidade_guardar_lote($escola_id, $data, $clean, get_current_user_id());
    if (!$res['ok']) wp_send_json_error($res['erro']);
    if (function_exists('sige_ajax_equipe_audit')) {
        sige_ajax_equipe_audit('rh_assiduidade_guardada', ['data' => $data, 'guardados' => $res['guardados'], 'escola_id' => $escola_id, 'resultado' => 'Marcação de assiduidade guardada.']);
    }
    wp_send_json_success(['guardados' => $res['guardados'], 'data' => $data]);
});
