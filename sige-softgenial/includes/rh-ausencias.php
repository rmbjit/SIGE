<?php
/**
 * SIGE SoftGenial - Recursos Humanos: Férias & Ausências (Fase 1).
 *
 * Registo de férias e ausências da equipa, gerido pelo RH ("RH regista e decide":
 * o gestor regista e marca o estado). Só contagem no ano (sem regras de saldo/
 * acumulação, juridicamente sensíveis). Multi-tenant por escola.
 *
 * Engenharia:
 *  - Tabela nova {prefix}sige_rh_ausencias criada POR CÓDIGO (idempotente), para
 *    o pipeline do CloudPanel não precisar de correr SQL manual.
 *  - Lógica de contagem/validação em funções PURAS (testáveis sem base de dados).
 *  - AJAX gated por gestão (sige_ajax_equipe_can_manage), com nonce, tenant-scope
 *    e auditoria. Não cria permissões novas (não toca no ficheiro protegido da
 *    matriz): reutiliza a permissão de gerir equipa.
 *  - Não altera fórmulas financeiras/académicas nem ficheiros protegidos.
 */

if (!defined('ABSPATH')) exit;

/* ============================================================================
 * ROTULAGEM
 * ========================================================================== */

if (!function_exists('sige_rh_ausencia_tipos')) {
    /** Tipos disponíveis (slug => rótulo). */
    function sige_rh_ausencia_tipos(): array {
        return [
            'ferias'              => 'Férias',
            'doenca'              => 'Doença / baixa médica',
            'licenca'             => 'Licença',
            'falta_justificada'   => 'Falta justificada',
            'falta_injustificada' => 'Falta injustificada',
        ];
    }
}

if (!function_exists('sige_rh_ausencia_tipo_label')) {
    function sige_rh_ausencia_tipo_label(string $slug): string {
        $m = sige_rh_ausencia_tipos();
        $slug = trim($slug);
        return $m[$slug] ?? ($slug === '' ? '—' : ucfirst(str_replace('_', ' ', $slug)));
    }
}

if (!function_exists('sige_rh_ausencia_estados')) {
    /** Estados possíveis (slug => rótulo). */
    function sige_rh_ausencia_estados(): array {
        return [
            'pendente'  => 'Pendente',
            'aprovada'  => 'Aprovada',
            'rejeitada' => 'Rejeitada',
            'cancelada' => 'Cancelada',
        ];
    }
}

if (!function_exists('sige_rh_ausencia_estado_label')) {
    function sige_rh_ausencia_estado_label(string $slug): string {
        $m = sige_rh_ausencia_estados();
        $slug = trim($slug);
        return $m[$slug] ?? ($slug === '' ? '—' : ucfirst($slug));
    }
}

/* ============================================================================
 * FUNÇÕES PURAS (contagem e validação) — testáveis sem base de dados
 * ========================================================================== */

if (!function_exists('sige_rh_ausencia_contar_dias')) {
    /**
     * Conta dias entre duas datas (inclusivas). PURA.
     * @return array{corridos:int,uteis:int} 'uteis' exclui Sábado e Domingo.
     *         Sem feriados (não há tabela de feriados no sistema).
     */
    function sige_rh_ausencia_contar_dias(string $inicio, string $fim): array {
        $a = strtotime($inicio . ' 00:00:00');
        $b = strtotime($fim . ' 00:00:00');
        if ($a === false || $b === false || $b < $a) {
            return ['corridos' => 0, 'uteis' => 0];
        }
        $corridos = (int) floor(($b - $a) / 86400) + 1;
        $uteis = 0;
        for ($t = $a; $t <= $b; $t += 86400) {
            $w = (int) date('N', $t); // 1=Seg ... 7=Dom
            if ($w < 6) { $uteis++; }
        }
        return ['corridos' => $corridos, 'uteis' => $uteis];
    }
}

if (!function_exists('sige_rh_ausencia_dias_efectivos')) {
    /** Dias efectivos para contagem: meio dia (só válido num único dia) ou dias úteis. PURA. */
    function sige_rh_ausencia_dias_efectivos(string $inicio, string $fim, bool $meio_dia): float {
        $c = sige_rh_ausencia_contar_dias($inicio, $fim);
        if ($meio_dia && $inicio === $fim) { return 0.5; }
        return (float) $c['uteis'];
    }
}

if (!function_exists('sige_rh_ausencia_validar')) {
    /**
     * Valida e normaliza a entrada (PURA). Não toca na base de dados.
     * @return array{ok:bool,erro:string,dados:array}
     */
    function sige_rh_ausencia_validar(array $in): array {
        $professor_id = (int) ($in['professor_id'] ?? 0);
        $tipo   = trim((string) ($in['tipo'] ?? ''));
        $inicio = trim((string) ($in['data_inicio'] ?? ''));
        $fim    = trim((string) ($in['data_fim'] ?? ''));
        $estado = trim((string) ($in['estado'] ?? 'aprovada'));
        $meio   = !empty($in['meio_dia']) ? 1 : 0;
        $motivo = trim((string) ($in['motivo'] ?? ''));

        if ($professor_id <= 0)                          return ['ok' => false, 'erro' => 'Seleccione o colaborador.', 'dados' => []];
        if (!array_key_exists($tipo, sige_rh_ausencia_tipos()))   return ['ok' => false, 'erro' => 'Tipo de ausência inválido.', 'dados' => []];
        if (!array_key_exists($estado, sige_rh_ausencia_estados())) $estado = 'aprovada';

        $ra = strtotime($inicio); $rb = strtotime($fim);
        if ($inicio === '' || $ra === false)             return ['ok' => false, 'erro' => 'Data de início inválida.', 'dados' => []];
        if ($fim === '' || $rb === false)                return ['ok' => false, 'erro' => 'Data de fim inválida.', 'dados' => []];
        if (strtotime($fim . ' 00:00:00') < strtotime($inicio . ' 00:00:00'))
            return ['ok' => false, 'erro' => 'A data de fim não pode ser anterior à de início.', 'dados' => []];

        if ($meio && $inicio !== $fim) { $meio = 0; } // meio dia só faz sentido num único dia

        $dias = sige_rh_ausencia_dias_efectivos($inicio, $fim, (bool) $meio);

        return ['ok' => true, 'erro' => '', 'dados' => [
            'professor_id' => $professor_id,
            'tipo'         => $tipo,
            'data_inicio'  => date('Y-m-d', $ra),
            'data_fim'     => date('Y-m-d', $rb),
            'estado'       => $estado,
            'meio_dia'     => $meio,
            'dias'         => $dias,
            'motivo'       => $motivo,
        ]];
    }
}

/* ============================================================================
 * MIGRAÇÃO (criação idempotente da tabela)
 * ========================================================================== */

if (!function_exists('sige_rh_ausencias_table')) {
    function sige_rh_ausencias_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'sige_rh_ausencias';
    }
}

if (!function_exists('sige_rh_ausencias_migrar')) {
    /** Cria a tabela se ainda não existir. Idempotente e barato (corre no admin_init). */
    function sige_rh_ausencias_migrar(): void {
        global $wpdb;
        $t = sige_rh_ausencias_table();

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
  user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tipo VARCHAR(32) NOT NULL DEFAULT 'ferias',
  data_inicio DATE NOT NULL,
  data_fim DATE NOT NULL,
  dias DECIMAL(5,1) NOT NULL DEFAULT 0,
  meio_dia TINYINT(1) NOT NULL DEFAULT 0,
  estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
  motivo TEXT NULL,
  aprovado_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
  aprovado_em DATETIME NULL,
  criado_por BIGINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL,
  atualizado_em DATETIME NULL,
  PRIMARY KEY  (id),
  KEY escola_prof (escola_id, professor_id),
  KEY escola_inicio (escola_id, data_inicio),
  KEY escola_estado (escola_id, estado)
) {$charset_collate};";
        dbDelta($sql);
        update_option('sige_rh_ausencias_schema', '1', false);
    }
}
add_action('admin_init', 'sige_rh_ausencias_migrar', 7);

/* ============================================================================
 * CAMADA DE DADOS (tenant-scoped)
 * ========================================================================== */

if (!function_exists('sige_rh_ausencia_professor_valido')) {
    /** Confirma que o professor_id pertence à escola (evita cross-tenant). */
    function sige_rh_ausencia_professor_valido(int $professor_id, int $escola_id): bool {
        global $wpdb;
        if ($professor_id <= 0 || $escola_id <= 0) return false;
        $tp = $wpdb->prefix . 'sige_professores';
        $ok = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$tp} WHERE id = %d AND escola_id = %d",
            $professor_id, $escola_id
        ));
        return $ok > 0;
    }
}

if (!function_exists('sige_rh_ausencias_listar')) {
    /**
     * Lista ausências da escola com filtros opcionais.
     * @param array $f ['ano'=>int,'tipo'=>string,'estado'=>string,'professor_id'=>int]
     */
    function sige_rh_ausencias_listar(int $escola_id, array $f = []): array {
        global $wpdb;
        if ($escola_id <= 0) return [];
        sige_rh_ausencias_migrar();
        $t  = sige_rh_ausencias_table();
        $tp = $wpdb->prefix . 'sige_professores';

        $where = ['a.escola_id = %d'];
        $args  = [$escola_id];

        if (!empty($f['ano'])) {
            $where[] = 'YEAR(a.data_inicio) = %d';
            $args[]  = (int) $f['ano'];
        }
        if (!empty($f['tipo']) && array_key_exists((string) $f['tipo'], sige_rh_ausencia_tipos())) {
            $where[] = 'a.tipo = %s';
            $args[]  = (string) $f['tipo'];
        }
        if (!empty($f['estado']) && array_key_exists((string) $f['estado'], sige_rh_ausencia_estados())) {
            $where[] = 'a.estado = %s';
            $args[]  = (string) $f['estado'];
        }
        if (!empty($f['professor_id'])) {
            $where[] = 'a.professor_id = %d';
            $args[]  = (int) $f['professor_id'];
        }

        $sql = "SELECT a.*, p.nome_completo AS colaborador
                  FROM {$t} a
                  LEFT JOIN {$tp} p ON p.id = a.professor_id AND p.escola_id = a.escola_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY a.data_inicio DESC, a.id DESC
                 LIMIT 500";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args));
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('sige_rh_ausencia_get')) {
    function sige_rh_ausencia_get(int $escola_id, int $id) {
        global $wpdb;
        if ($escola_id <= 0 || $id <= 0) return null;
        $t = sige_rh_ausencias_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d AND escola_id = %d", $id, $escola_id));
    }
}

if (!function_exists('sige_rh_ausencia_guardar')) {
    /**
     * Insere ou actualiza (se vier 'id'). Devolve ['ok'=>bool,'erro'=>string,'id'=>int].
     */
    function sige_rh_ausencia_guardar(int $escola_id, array $in, int $user_id): array {
        global $wpdb;
        $v = sige_rh_ausencia_validar($in);
        if (!$v['ok']) return ['ok' => false, 'erro' => $v['erro'], 'id' => 0];
        $d = $v['dados'];

        if (!sige_rh_ausencia_professor_valido((int) $d['professor_id'], $escola_id)) {
            return ['ok' => false, 'erro' => 'Colaborador não encontrado nesta escola.', 'id' => 0];
        }

        sige_rh_ausencias_migrar();
        $t = sige_rh_ausencias_table();
        $agora = current_time('mysql');
        $id = (int) ($in['id'] ?? 0);

        $fields = [
            'escola_id'    => $escola_id,
            'professor_id' => (int) $d['professor_id'],
            'tipo'         => $d['tipo'],
            'data_inicio'  => $d['data_inicio'],
            'data_fim'     => $d['data_fim'],
            'dias'         => $d['dias'],
            'meio_dia'     => (int) $d['meio_dia'],
            'estado'       => $d['estado'],
            'motivo'       => $d['motivo'],
            'atualizado_em' => $agora,
        ];
        // Carimbo de aprovação quando o estado fica aprovado/rejeitado.
        if (in_array($d['estado'], ['aprovada', 'rejeitada'], true)) {
            $fields['aprovado_por'] = $user_id;
            $fields['aprovado_em']  = $agora;
        }

        if ($id > 0) {
            $existe = sige_rh_ausencia_get($escola_id, $id);
            if (!$existe) return ['ok' => false, 'erro' => 'Registo não encontrado.', 'id' => 0];
            $ok = $wpdb->update($t, $fields, ['id' => $id, 'escola_id' => $escola_id]);
            if ($ok === false) return ['ok' => false, 'erro' => 'Não foi possível actualizar.', 'id' => 0];
            return ['ok' => true, 'erro' => '', 'id' => $id];
        }

        $fields['criado_por'] = $user_id;
        $fields['criado_em']  = $agora;
        $ok = $wpdb->insert($t, $fields);
        if ($ok === false) return ['ok' => false, 'erro' => 'Não foi possível registar.', 'id' => 0];
        return ['ok' => true, 'erro' => '', 'id' => (int) $wpdb->insert_id];
    }
}

if (!function_exists('sige_rh_ausencia_definir_estado')) {
    /** Muda apenas o estado (aprovar/rejeitar/cancelar). */
    function sige_rh_ausencia_definir_estado(int $escola_id, int $id, string $estado, int $user_id): array {
        global $wpdb;
        if (!array_key_exists($estado, sige_rh_ausencia_estados())) return ['ok' => false, 'erro' => 'Estado inválido.'];
        $reg = sige_rh_ausencia_get($escola_id, $id);
        if (!$reg) return ['ok' => false, 'erro' => 'Registo não encontrado.'];
        $t = sige_rh_ausencias_table();
        $agora = current_time('mysql');
        $fields = ['estado' => $estado, 'atualizado_em' => $agora];
        if (in_array($estado, ['aprovada', 'rejeitada'], true)) {
            $fields['aprovado_por'] = $user_id;
            $fields['aprovado_em']  = $agora;
        }
        $ok = $wpdb->update($t, $fields, ['id' => $id, 'escola_id' => $escola_id]);
        return $ok === false ? ['ok' => false, 'erro' => 'Não foi possível actualizar o estado.'] : ['ok' => true, 'erro' => ''];
    }
}

if (!function_exists('sige_rh_ausencia_eliminar')) {
    function sige_rh_ausencia_eliminar(int $escola_id, int $id): array {
        global $wpdb;
        $reg = sige_rh_ausencia_get($escola_id, $id);
        if (!$reg) return ['ok' => false, 'erro' => 'Registo não encontrado.'];
        $t = sige_rh_ausencias_table();
        $ok = $wpdb->delete($t, ['id' => $id, 'escola_id' => $escola_id]);
        return $ok === false ? ['ok' => false, 'erro' => 'Não foi possível eliminar.'] : ['ok' => true, 'erro' => ''];
    }
}

/* ============================================================================
 * RESUMOS (Fase 2) — leitura/agregação para a Ficha e os Relatórios
 * ========================================================================== */

if (!function_exists('sige_rh_ausencia_hoje')) {
    function sige_rh_ausencia_hoje(): string {
        return function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d');
    }
}

if (!function_exists('sige_rh_ausencia_resumo_professor')) {
    /**
     * Resumo anual de ausências de UM colaborador (só aprovadas).
     * @return array{ano:int,total_dias:float,por_tipo:array,ausente_hoje:?array}
     */
    function sige_rh_ausencia_resumo_professor(int $escola_id, int $professor_id, int $ano, string $hoje = ''): array {
        global $wpdb;
        $out = ['ano' => $ano, 'total_dias' => 0.0, 'por_tipo' => [], 'ausente_hoje' => null];
        if ($escola_id <= 0 || $professor_id <= 0) return $out;
        sige_rh_ausencias_migrar();
        $t = sige_rh_ausencias_table();
        $hoje = $hoje !== '' ? $hoje : sige_rh_ausencia_hoje();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tipo, SUM(dias) AS dias FROM {$t}
              WHERE escola_id = %d AND professor_id = %d AND estado = 'aprovada' AND YEAR(data_inicio) = %d
              GROUP BY tipo",
            $escola_id, $professor_id, $ano
        ));
        foreach ((array) $rows as $r) {
            $out['por_tipo'][(string) $r->tipo] = (float) $r->dias;
            $out['total_dias'] += (float) $r->dias;
        }

        $now = $wpdb->get_row($wpdb->prepare(
            "SELECT tipo, data_fim FROM {$t}
              WHERE escola_id = %d AND professor_id = %d AND estado = 'aprovada' AND data_inicio <= %s AND data_fim >= %s
              ORDER BY data_fim DESC LIMIT 1",
            $escola_id, $professor_id, $hoje, $hoje
        ));
        if ($now) {
            $out['ausente_hoje'] = ['tipo' => (string) $now->tipo, 'data_fim' => (string) $now->data_fim];
        }
        return $out;
    }
}

if (!function_exists('sige_rh_ausencia_resumo_escola')) {
    /**
     * Resumo anual de ausências da ESCOLA (só aprovadas) + quem está ausente hoje.
     * @return array{ano:int,total_dias:float,por_tipo:array,ausentes_hoje:array}
     */
    function sige_rh_ausencia_resumo_escola(int $escola_id, int $ano, string $hoje = ''): array {
        global $wpdb;
        $out = ['ano' => $ano, 'total_dias' => 0.0, 'por_tipo' => [], 'ausentes_hoje' => []];
        if ($escola_id <= 0) return $out;
        sige_rh_ausencias_migrar();
        $t  = sige_rh_ausencias_table();
        $tp = $wpdb->prefix . 'sige_professores';
        $hoje = $hoje !== '' ? $hoje : sige_rh_ausencia_hoje();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tipo, SUM(dias) AS dias, COUNT(*) AS n FROM {$t}
              WHERE escola_id = %d AND estado = 'aprovada' AND YEAR(data_inicio) = %d
              GROUP BY tipo",
            $escola_id, $ano
        ));
        foreach ((array) $rows as $r) {
            $out['por_tipo'][(string) $r->tipo] = ['dias' => (float) $r->dias, 'n' => (int) $r->n];
            $out['total_dias'] += (float) $r->dias;
        }

        $aus = $wpdb->get_results($wpdb->prepare(
            "SELECT a.professor_id, a.tipo, a.data_fim, p.nome_completo AS nome FROM {$t} a
              LEFT JOIN {$tp} p ON p.id = a.professor_id AND p.escola_id = a.escola_id
              WHERE a.escola_id = %d AND a.estado = 'aprovada' AND a.data_inicio <= %s AND a.data_fim >= %s
              ORDER BY p.nome_completo ASC",
            $escola_id, $hoje, $hoje
        ));
        foreach ((array) $aus as $r) {
            $out['ausentes_hoje'][] = [
                'professor_id' => (int) $r->professor_id,
                'nome'         => (string) ($r->nome ?? ''),
                'tipo'         => (string) $r->tipo,
                'data_fim'     => (string) $r->data_fim,
            ];
        }
        return $out;
    }
}

/* ============================================================================
 * AJAX (gated por gestão, nonce, tenant-scope, auditado)
 * ========================================================================== */

if (!function_exists('sige_rh_ausencia_ajax_guard')) {
    /** Valida nonce + permissão de gestão da equipa. Devolve escola_id (>0) ou termina. */
    function sige_rh_ausencia_ajax_guard(): int {
        if (function_exists('sige_ajax_equipe_begin_buffer')) sige_ajax_equipe_begin_buffer();
        $nonce = isset($_POST['_sige_nonce']) ? sanitize_text_field(wp_unslash($_POST['_sige_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'sige_equipe_action')) {
            wp_send_json_error('Sessão expirada.');
        }
        $pode = function_exists('sige_ajax_equipe_can_manage') ? sige_ajax_equipe_can_manage()
              : (function_exists('sige_can') ? sige_can('rh.equipe_gerir') : current_user_can('manage_options'));
        if (!$pode) {
            wp_send_json_error('Sem permissão para gerir ausências.');
        }
        $escola_id = function_exists('sige_ajax_equipe_escola_id') ? (int) sige_ajax_equipe_escola_id()
                   : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);
        if ($escola_id <= 0) wp_send_json_error('Escola não identificada.');
        return $escola_id;
    }
}

if (!function_exists('sige_rh_ausencia_ajax_audit')) {
    function sige_rh_ausencia_ajax_audit(string $evento, array $dados): void {
        if (function_exists('sige_ajax_equipe_audit')) {
            sige_ajax_equipe_audit($evento, $dados);
        }
    }
}

add_action('wp_ajax_sige_rh_ausencia_listar', function () {
    $escola_id = sige_rh_ausencia_ajax_guard();
    $f = [
        'ano'          => isset($_POST['ano']) ? (int) $_POST['ano'] : 0,
        'tipo'         => isset($_POST['tipo']) ? sanitize_text_field(wp_unslash($_POST['tipo'])) : '',
        'estado'       => isset($_POST['estado']) ? sanitize_text_field(wp_unslash($_POST['estado'])) : '',
        'professor_id' => isset($_POST['professor_id']) ? (int) $_POST['professor_id'] : 0,
    ];
    $rows = sige_rh_ausencias_listar($escola_id, $f);
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'           => (int) $r->id,
            'professor_id' => (int) $r->professor_id,
            'colaborador'  => (string) ($r->colaborador ?? ''),
            'tipo'         => (string) $r->tipo,
            'tipo_label'   => sige_rh_ausencia_tipo_label((string) $r->tipo),
            'data_inicio'  => (string) $r->data_inicio,
            'data_fim'     => (string) $r->data_fim,
            'dias'         => (float) $r->dias,
            'meio_dia'     => (int) $r->meio_dia,
            'estado'       => (string) $r->estado,
            'estado_label' => sige_rh_ausencia_estado_label((string) $r->estado),
            'motivo'       => (string) ($r->motivo ?? ''),
        ];
    }
    wp_send_json_success(['itens' => $out]);
});

add_action('wp_ajax_sige_rh_ausencia_guardar', function () {
    $escola_id = sige_rh_ausencia_ajax_guard();
    $in = [
        'id'           => isset($_POST['id']) ? (int) $_POST['id'] : 0,
        'professor_id' => isset($_POST['professor_id']) ? (int) $_POST['professor_id'] : 0,
        'tipo'         => isset($_POST['tipo']) ? sanitize_text_field(wp_unslash($_POST['tipo'])) : '',
        'data_inicio'  => isset($_POST['data_inicio']) ? sanitize_text_field(wp_unslash($_POST['data_inicio'])) : '',
        'data_fim'     => isset($_POST['data_fim']) ? sanitize_text_field(wp_unslash($_POST['data_fim'])) : '',
        'estado'       => isset($_POST['estado']) ? sanitize_text_field(wp_unslash($_POST['estado'])) : 'aprovada',
        'meio_dia'     => !empty($_POST['meio_dia']) ? 1 : 0,
        'motivo'       => isset($_POST['motivo']) ? sanitize_textarea_field(wp_unslash($_POST['motivo'])) : '',
    ];
    $res = sige_rh_ausencia_guardar($escola_id, $in, get_current_user_id());
    if (!$res['ok']) wp_send_json_error($res['erro']);
    sige_rh_ausencia_ajax_audit('rh_ausencia_guardada', ['ausencia_id' => $res['id'], 'escola_id' => $escola_id, 'resultado' => 'Registo de ausência guardado.']);
    wp_send_json_success(['id' => $res['id']]);
});

add_action('wp_ajax_sige_rh_ausencia_estado', function () {
    $escola_id = sige_rh_ausencia_ajax_guard();
    $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $estado = isset($_POST['estado']) ? sanitize_text_field(wp_unslash($_POST['estado'])) : '';
    $res = sige_rh_ausencia_definir_estado($escola_id, $id, $estado, get_current_user_id());
    if (!$res['ok']) wp_send_json_error($res['erro']);
    sige_rh_ausencia_ajax_audit('rh_ausencia_estado', ['ausencia_id' => $id, 'estado' => $estado, 'escola_id' => $escola_id, 'resultado' => 'Estado da ausência alterado.']);
    wp_send_json_success(['id' => $id, 'estado' => $estado]);
});

add_action('wp_ajax_sige_rh_ausencia_eliminar', function () {
    $escola_id = sige_rh_ausencia_ajax_guard();
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $res = sige_rh_ausencia_eliminar($escola_id, $id);
    if (!$res['ok']) wp_send_json_error($res['erro']);
    sige_rh_ausencia_ajax_audit('rh_ausencia_eliminada', ['ausencia_id' => $id, 'escola_id' => $escola_id, 'resultado' => 'Registo de ausência eliminado.']);
    wp_send_json_success(['id' => $id]);
});
