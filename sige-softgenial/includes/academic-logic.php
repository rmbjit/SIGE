<?php

if (!defined('ABSPATH')) exit;

// ===============================

// ✅ Estado Persistente: Modo Pauta Final + Ano Lectivo Encerrado (WP Options)

// - Chave por ano+turma para "Modo Pauta Final"

// - Chave por ano para "Ano Lectivo Encerrado"

// ===============================

if (!function_exists('sige_truthy_lock_value')) {
    /** V7.2.6 - só valores explicitamente activos bloqueiam notas. */
    function sige_truthy_lock_value($v): bool {
        return ($v === 1 || $v === '1' || $v === true || $v === 'true');
    }
}

if (!function_exists('sige_resolve_turma_escola_id')) {
    function sige_resolve_turma_escola_id($turma_id, $fallback_eid = null): int {
        global $wpdb;
        $turma_id = (int)$turma_id;
        if ($turma_id > 0) {
            $tT = $wpdb->prefix . 'sige_turmas';
            $eid = $wpdb->get_var($wpdb->prepare("SELECT escola_id FROM {$tT} WHERE id=%d LIMIT 1", $turma_id));
            if ((int)$eid > 0) return (int)$eid;
        }
        if ($fallback_eid !== null && (int)$fallback_eid > 0) return (int)$fallback_eid;
        return function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    }
}

if (!function_exists('sige_pauta_final_option_key')) {
    function sige_pauta_final_option_key($ano, $turma_id, $escola_id = null) {
        $eid = ($escola_id !== null) ? (int)$escola_id : sige_resolve_turma_escola_id($turma_id, null);
        $ano = (int) $ano;
        $turma_id = (int) $turma_id;
        return "sige_pauta_final_mode_{$eid}_{$ano}_{$turma_id}";
    }
}

if (!function_exists('sige_is_pauta_final_mode')) {
    function sige_is_pauta_final_mode($ano, $turma_id, $escola_id = null) {
        $key = sige_pauta_final_option_key($ano, $turma_id, $escola_id);
        $v = get_option($key, 0);
        return sige_truthy_lock_value($v);
    }
}

if (!function_exists('sige_set_pauta_final_mode')) {
    function sige_set_pauta_final_mode($ano, $turma_id, $enabled, $escola_id = null) {
        $key = sige_pauta_final_option_key($ano, $turma_id, $escola_id);
        update_option($key, $enabled ? 1 : 0, false);
    }
}

if (!function_exists('sige_ano_encerrado_option_key')) {
    function sige_ano_encerrado_option_key($ano, $escola_id = null) {
        $eid = ($escola_id !== null && (int)$escola_id > 0) ? (int)$escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        $ano = (int) $ano;
        return "sige_ano_lectivo_encerrado_{$eid}_{$ano}";
    }
}

if (!function_exists('sige_is_ano_encerrado')) {
    function sige_is_ano_encerrado($ano, $escola_id = null) {
        $key = sige_ano_encerrado_option_key($ano, $escola_id);
        $v = get_option($key, 0);
        return sige_truthy_lock_value($v);
    }
}

if (!function_exists('sige_set_ano_encerrado')) {
    function sige_set_ano_encerrado($ano, $enabled, $escola_id = null) {
        $key = sige_ano_encerrado_option_key($ano, $escola_id);
        update_option($key, $enabled ? 1 : 0, false);
    }
}

if (!function_exists('sige_notas_lock_status')) {
    function sige_notas_lock_status($ano, $turma_id, $escola_id = null): array {
        $ano = (int)$ano;
        $turma_id = (int)$turma_id;
        $eid = sige_resolve_turma_escola_id($turma_id, $escola_id);
        $ano_locked = sige_is_ano_encerrado($ano, $eid);
        $pauta_locked = ($turma_id > 0) ? sige_is_pauta_final_mode($ano, $turma_id, $eid) : false;
        $reasons = [];
        if ($ano_locked) $reasons[] = 'ano_encerrado';
        if ($pauta_locked) $reasons[] = 'pauta_final';
        return [
            'locked' => !empty($reasons),
            'ano_encerrado' => $ano_locked,
            'pauta_final' => $pauta_locked,
            'reasons' => $reasons,
            'ano' => $ano,
            'turma_id' => $turma_id,
            'escola_id' => $eid,
            'ano_key' => sige_ano_encerrado_option_key($ano, $eid),
            'pauta_key' => ($turma_id > 0 ? sige_pauta_final_option_key($ano, $turma_id, $eid) : ''),
        ];
    }
}

if (!function_exists('sige_notas_lock_message')) {
    function sige_notas_lock_message(array $status): string {
        if (empty($status['locked'])) return '';
        if (!empty($status['ano_encerrado']) && !empty($status['pauta_final'])) return 'Edição bloqueada - Ano Lectivo encerrado e Pauta Final activa.';
        if (!empty($status['ano_encerrado'])) return 'Edição bloqueada - Ano Lectivo encerrado.';
        if (!empty($status['pauta_final'])) return 'Edição bloqueada - Pauta Final activa.';
        return 'Edição bloqueada.';
    }
}

if (!function_exists('sige_notas_locked')) {
    function sige_notas_locked($ano, $turma_id, $escola_id = null) {
        $st = sige_notas_lock_status($ano, $turma_id, $escola_id);
        return !empty($st['locked']);
    }
}

/**

 * SIGE SoftGenial - Módulo Académico (SNE - Moçambique)

 * Compatível com PHP 7.0+ (sem arrow functions, sem sintaxe 7.4+)

 *

 * AJAX:

 *  - sige_listar_alunos_notas

 *  - sige_salvar_notas_bulk

 * TEST:

 *  - sige_ping

 *

 * Requer:

 *  - {$wpdb->prefix}sige_matriculas (aluno_id, turma_id, ano_lectivo, status_matricula)

 *  - {$wpdb->prefix}sige_alunos (id, nome_completo, classe_atual)

 *  - {$wpdb->prefix}sige_notas (id, aluno_id, turma_id, disciplina_id, trimestre, nota_ac, nota_acp, nota_exame, ano_lectivo)

 */

if (!function_exists('sige_sne_sanitizar_nota')) {

    function sige_sne_sanitizar_nota($valor) {

        if ($valor === '' || $valor === null) return null;

        $v = str_replace(',', '.', trim((string)$valor));

        if ($v === '') return null;

        $n = floatval($v);

        if ($n < 0) $n = 0;

        if ($n > 20) $n = 20;

        return round($n, 2);

    }

}

if (!function_exists('sige_sne_get_ano_lectivo')) {

    function sige_sne_get_ano_lectivo() {

        if (function_exists('sige_fin_get_ano_letivo_master')) {

            $y = (int) sige_fin_get_ano_letivo_master();

            if ($y > 2000) return $y;

        }

        return (int) wp_date('Y');

    }

}

/**

 * PING para confirmar que este ficheiro está a ser carregado e que AJAX está registado.

 * Testar: /wp-admin/admin-ajax.php?action=sige_ping

 */

add_action('wp_ajax_sige_ping', function () {

    wp_send_json_success(array('ok' => true, 'where' => 'academic-logic', 'time' => time()));

});

if (!function_exists('sige_ajax_listar_alunos_notas')) {

    add_action('wp_ajax_sige_listar_alunos_notas', 'sige_ajax_listar_alunos_notas');

    function sige_ajax_listar_alunos_notas() {
    sige_check_nonce_global();

        global $wpdb;

        if (!current_user_can('read')) {

            wp_send_json_error('Acesso negado.');

        }

        // aceita GET/POST

        $disciplina_id = isset($_REQUEST['disciplina_id']) ? intval($_REQUEST['disciplina_id']) : 0;

        $trimestre     = isset($_REQUEST['trimestre']) ? intval($_REQUEST['trimestre']) : 0;

        $turma_id      = isset($_REQUEST['turma_id']) ? intval($_REQUEST['turma_id']) : 0;

        $ano_lectivo   = isset($_REQUEST['ano_lectivo']) ? intval($_REQUEST['ano_lectivo']) : sige_sne_get_ano_lectivo();

        if (!$disciplina_id || !$turma_id || $trimestre < 1 || $trimestre > 3 || $ano_lectivo < 2000) {

            wp_send_json_error('Parâmetros inválidos (turma/ano/disciplina/trimestre).');

        }

        $tAlunos     = $wpdb->prefix . 'sige_alunos';

        $tMatriculas = $wpdb->prefix . 'sige_matriculas';

        $tNotas      = $wpdb->prefix . 'sige_notas';

        $alunos = $wpdb->get_results($wpdb->prepare("

            SELECT a.id, a.nome_completo, a.classe_atual, t.classe AS turma_classe

            FROM {$tMatriculas} m

            INNER JOIN {$tAlunos} a ON a.id = m.aluno_id

            INNER JOIN {$wpdb->prefix}sige_turmas t ON t.id = m.turma_id

            WHERE m.turma_id = %d

              AND m.ano_lectivo = %d

              AND (m.status_matricula IS NULL OR m.status_matricula = 'activa')

            ORDER BY a.nome_completo ASC

        ", $turma_id, $ano_lectivo));

        if (empty($alunos)) {

            wp_send_json_error('Nenhum aluno matriculado nesta turma/ano (status activa).');

        }

        // IDs dos alunos para buscar notas existentes (1 query)

        $ids = array_map(function ($o) { return (int)$o->id; }, $alunos);

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        // Monta params: ids + disciplina + trimestre + ano + turma

        $params = $ids;

        $params[] = $disciplina_id;

        $params[] = $trimestre;

        $params[] = $ano_lectivo;

        $params[] = $turma_id;

        // NOTA: turma_id <=> %d é operador MySQL (não é sintaxe PHP)

        $notas = $wpdb->get_results($wpdb->prepare("

            SELECT aluno_id, nota_ac, nota_acp, nota_exame

            FROM {$tNotas}

            WHERE aluno_id IN ({$placeholders})

              AND disciplina_id = %d

              AND trimestre = %d

              AND ano_lectivo = %d

              AND turma_id <=> %d

        ", $params), OBJECT_K);

        $classes_exame = array(3, 6, 9, 12);

        $html = '';

        foreach ($alunos as $a) {

            $aid = (int)$a->id;

            // Classe: preferir turma.classe (porque a.classe_atual pode estar vazio/0)

$classe_raw = isset($a->turma_classe) ? $a->turma_classe : '';

if ($classe_raw === null) $classe_raw = '';

$classe_num = (int)preg_replace('/\D+/', '', (string)$classe_raw);

if ($classe_num <= 0) $classe_num = (int)$a->classe_atual;

$classe_label = $classe_raw !== '' ? $classe_raw : ($classe_num > 0 ? ($classe_num . 'ª') : '-');

            $pode_exame = ($trimestre === 3 && in_array($classe_num, $classes_exame, true));

            $readonly_exame = '';

            $n = isset($notas[$aid]) ? $notas[$aid] : null;

            $ac  = $n ? esc_attr($n->nota_ac) : '';

            $acp = $n ? esc_attr($n->nota_acp) : '';

            $ex  = $n ? esc_attr($n->nota_exame) : '';

            $html .= "<tr>

                <td style='padding:12px; border-bottom:1px solid #ddd;'>

                    <strong>" . esc_html($a->nome_completo) . "</strong><br>

                    <small>ID {$aid} • {$classe_label}</small>

                </td>

                <td style='padding:12px; border-bottom:1px solid #ddd;'>

                    <input type='text' class='nota-ac' data-aluno='{$aid}' value='{$ac}' style='width:90px; padding:8px;'>

                </td>

                <td style='padding:12px; border-bottom:1px solid #ddd;'>

                    <input type='text' class='nota-acp' data-aluno='{$aid}' value='{$acp}' style='width:90px; padding:8px;'>

                </td>

                <td style='padding:12px; border-bottom:1px solid #ddd;'>

                    <input type='text' class='nota-exame' data-aluno='{$aid}' value='{$ex}' {$readonly_exame} style='width:90px; padding:8px;'>

                </td>

            </tr>";

        }

        wp_send_json_success($html);

    }

}

if (!function_exists('sige_ajax_salvar_notas_bulk')) {

    add_action('wp_ajax_sige_salvar_notas_bulk', 'sige_ajax_salvar_notas_bulk');

    function sige_ajax_salvar_notas_bulk() {
    sige_check_nonce_global();

        global $wpdb;

        if (!current_user_can('read')) {

            wp_send_json_error('Acesso negado.');

        }

        $disciplina_id = isset($_POST['disciplina_id']) ? intval($_POST['disciplina_id']) : 0;

        $trimestre     = isset($_POST['trimestre']) ? intval($_POST['trimestre']) : 0;

        $turma_id      = isset($_POST['turma_id']) ? intval($_POST['turma_id']) : 0;

        $ano_lectivo   = isset($_POST['ano_lectivo']) ? intval($_POST['ano_lectivo']) : sige_sne_get_ano_lectivo();

        // === BLOQUEIO: Ano encerrado OU Pauta Final activa (estado persistente) ===

        if (function_exists('sige_notas_locked') && sige_notas_locked($ano_lectivo, $turma_id)) {

            $msg = (function_exists('sige_is_ano_encerrado') && sige_is_ano_encerrado($ano_lectivo))

                ? 'Edição bloqueada: Ano lectivo encerrado.'

                : 'Edição bloqueada: Modo Pauta Final activo.';

            wp_send_json_error($msg);

        }

        $dados_notas = isset($_POST['notas']) ? $_POST['notas'] : null;

        if (!$disciplina_id || !$turma_id || $trimestre < 1 || $trimestre > 3 || $ano_lectivo < 2000 || !is_array($dados_notas)) {

            wp_send_json_error('Dados inválidos para gravação.');

        }

        $tNotas = $wpdb->prefix . 'sige_notas';

        $tMatriculas = $wpdb->prefix . 'sige_matriculas';

        $gravadas = 0;

        $atualizadas = 0;

        $ignoradas = 0;

        foreach ($dados_notas as $linha) {

            $aluno_id = isset($linha['aluno_id']) ? intval($linha['aluno_id']) : 0;

            if (!$aluno_id) { $ignoradas++; continue; }

            // Segurança: só grava se aluno estiver matriculado nesta turma/ano

            $mat_ok = $wpdb->get_var($wpdb->prepare("

                SELECT id FROM {$tMatriculas}

                WHERE aluno_id=%d AND turma_id=%d AND ano_lectivo=%d

                  AND (status_matricula IS NULL OR status_matricula='activa')

                LIMIT 1

            ", $aluno_id, $turma_id, $ano_lectivo));

            if (!$mat_ok) { $ignoradas++; continue; }

            $nota_ac   = sige_sne_sanitizar_nota(isset($linha['ac']) ? $linha['ac'] : null);

            $nota_acp  = sige_sne_sanitizar_nota(isset($linha['acp']) ? $linha['acp'] : null);

            $nota_exam = sige_sne_sanitizar_nota(isset($linha['exame']) ? $linha['exame'] : null);

            // Procura existente (NULL-safe turma_id com <=> em MySQL)

            $id_existente = $wpdb->get_var($wpdb->prepare("

                SELECT id FROM {$tNotas}

                WHERE aluno_id=%d AND disciplina_id=%d AND trimestre=%d AND ano_lectivo=%d

                  AND turma_id <=> %d

                LIMIT 1

            ", $aluno_id, $disciplina_id, $trimestre, $ano_lectivo, $turma_id));

            // Se tudo vazio: apagar registo existente (limpar nota)

            if ($nota_ac === null && $nota_acp === null && $nota_exam === null) {

                if ($id_existente) { $wpdb->delete($tNotas, ['id' => (int)$id_existente]); }

                else { $ignoradas++; }

                continue;

            }

            $dados_db = array(

                'escola_id'     => sige_require_escola_id('salvar_notas'),

                'aluno_id'      => $aluno_id,

                'turma_id'      => $turma_id,

                'disciplina_id' => $disciplina_id,

                'trimestre'     => $trimestre,

                'nota_ac'       => $nota_ac,

                'nota_acp'      => $nota_acp,

                'nota_exame'    => $nota_exam,

                'ano_lectivo'   => $ano_lectivo,

            );

            // [T6] Fluxo de aprovação: professor→pendente, supervisor→aprovado
            $is_sup = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director')
                   || current_user_can('sige_pedagogico') || current_user_can('sige_secretario');
            $dados_db['status']        = $is_sup ? 'aprovado' : 'pendente';
            $dados_db['submetido_por'] = get_current_user_id();
            $dados_db['submetido_em']  = current_time('mysql');
            if ($is_sup) {
                $dados_db['aprovado_por'] = get_current_user_id();
                $dados_db['aprovado_em']  = current_time('mysql');
            }

            if ($id_existente) {

                $wpdb->update($tNotas, $dados_db, array('id' => (int)$id_existente));

                $atualizadas++;

            } else {

                $wpdb->insert($tNotas, $dados_db);

                $gravadas++;

            }

        }

        wp_send_json_success("Notas gravadas: {$gravadas} | Actualizadas: {$atualizadas} | Ignoradas: {$ignoradas}");

    }

}

/**

 * AJAX: listar disciplinas válidas para uma turma (turma_disciplinas -> fallback matriz_curricular por classe)

 * Retorna array {id, nome}

 */

add_action('wp_ajax_sige_get_disciplinas_turma', function() {

    if (!defined('ABSPATH')) exit;

    check_ajax_referer('sige_notas_nonce');

    global $wpdb;

    $turma_id = isset($_POST['turma_id']) ? (int) $_POST['turma_id'] : 0;

    if (!$turma_id) wp_send_json_error('Turma inválida.');

    $tTurmas  = $wpdb->prefix . 'sige_turmas';

    $tTD      = $wpdb->prefix . 'sige_turma_disciplinas';

    $tMatriz  = $wpdb->prefix . 'sige_matriz_curricular';

    $tDisc    = $wpdb->prefix . 'sige_disciplinas';

    $turma = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tTurmas} WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id, sige_require_escola_id('salvar_notas')));

    if (!$turma) wp_send_json_error('Turma não encontrada.');

    // Normalizar classe para bater com a matriz (ex.: 1 -> 1ª; 11 A -> 11ª A)

    $classe_raw = isset($turma->classe) ? (string)$turma->classe : '';

    $classe_raw = trim($classe_raw);

    $classe_norm = '';

    if ($classe_raw !== '') {

        if (is_numeric($classe_raw)) {

            $classe_norm = ((int)$classe_raw) . 'ª';

        } else if (preg_match('/^\s*(\d{1,2})\s*[ªa]?\s*([A-Za-z])?\s*$/u', $classe_raw, $m)) {

            $n = (int)$m[1];

            $suf = (!empty($m[2])) ? (' ' . strtoupper($m[2])) : '';

            $classe_norm = $n . 'ª' . $suf;

        } else if (preg_match('/(\d{1,2})/u', $classe_raw, $m)) {

            $classe_norm = ((int)$m[1]) . 'ª';

        } else {

            $classe_norm = $classe_raw;

        }

    }

    // 1) disciplinas vinculadas à turma

    if ($classe_norm) {

        $rows = $wpdb->get_results($wpdb->prepare("

            SELECT d.id, d.nome, d.sigla, d.categoria, d.ordem, mc.ordem_pauta

            FROM {$tTD} td

            INNER JOIN {$tDisc} d ON d.id = td.disciplina_id

            LEFT JOIN {$tMatriz} mc ON mc.disciplina_id = d.id AND mc.classe = %s

            WHERE td.turma_id = %d

            GROUP BY d.id

            ORDER BY COALESCE(mc.ordem_pauta, d.ordem, 9999) ASC, d.nome ASC

        ", $classe_norm, $turma_id));

    } else {

        $rows = $wpdb->get_results($wpdb->prepare("

            SELECT d.id, d.nome, d.sigla, d.categoria, d.ordem

            FROM {$tTD} td

            INNER JOIN {$tDisc} d ON d.id = td.disciplina_id

            WHERE td.turma_id = %d

            GROUP BY d.id

            ORDER BY COALESCE(d.ordem, 9999) ASC, d.nome ASC

        ", $turma_id));

    }

    // 2) fallback: matriz por classe

    if (empty($rows) && $classe_norm) {

        $eid = sige_require_escola_id('salvar_notas');

        $rows = $wpdb->get_results($wpdb->prepare("

            SELECT d.id, d.nome, d.sigla, d.categoria, d.ordem, mc.ordem_pauta

            FROM {$tMatriz} mc

            INNER JOIN {$tDisc} d ON d.id = mc.disciplina_id

            WHERE mc.classe = %s AND mc.escola_id = %d

            ORDER BY COALESCE(mc.ordem_pauta, d.ordem, 9999) ASC, d.nome ASC

        ", $classe_norm, $eid));

    }

    // 3) último recurso: disciplinas da escola

    if (empty($rows)) {

        $eid = sige_require_escola_id('salvar_notas');

        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, nome, sigla, categoria, ordem FROM {$tDisc} WHERE escola_id = %d ORDER BY ordem ASC, nome ASC", $eid));

    }

    $items = array();

    foreach ((array)$rows as $r) {

        $nome = (string)($r->nome ?? 'Disciplina');

        $cat = strtolower((string)($r->categoria ?? ''));

        if ($cat === 'nuclear') $nome .= ' (NUCLEAR)';

        else if ($cat === 'complementar') $nome .= ' (COMPLEMENTAR)';

        else if ($cat) $nome .= ' (' . strtoupper($cat) . ')';

        $items[] = array(

            'id' => (int)$r->id,

            'nome' => $nome,

        );

    }

    wp_send_json_success(array('items' => $items, 'classe' => $classe_norm));

});

/**

 * ==================================================

 * REGRAS ACADÉMICAS CONFIGURÁVEIS (SNE) - Helpers

 * ==================================================

 * Estas funções lêem a tabela {$wpdb->prefix}sige_regras_academicas (criada via db-handler upgrades)

 * e servem de base para:

 *  - identificar fim de ciclo (3ª, 6ª, 9ª, 12ª) de forma configurável

 *  - aplicar ponderações e regras de progressão no futuro

 */

if (!function_exists('sige_get_ano_lectivo_atual')) {

    function sige_get_ano_lectivo_atual() {

        // Multi-tenant: usa sige_ano_lectivo_atual() se disponível (cacheado, filtrado por escola)
        if (function_exists('sige_ano_lectivo_atual')) {
            $y = (int) sige_ano_lectivo_atual();
            if ($y > 2000) return $y;
        }

        global $wpdb;

        $tConfig = $wpdb->prefix . 'sige_config';

        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

        $ano = (int) $wpdb->get_var($wpdb->prepare("SELECT ano_lectivo FROM {$tConfig} WHERE escola_id = %d LIMIT 1", $eid));

        if ($ano < 2000) $ano = (int) wp_date('Y');

        return $ano;

    }

}

if (!function_exists('sige_parse_classe_num')) {

    /**

     * Extrai o número da classe a partir de strings como:

     *  - "6ª", "6", "6ª A", "12ª B", "3a", "9º"

     * Retorna int (0 se falhar).

     */

    function sige_parse_classe_num($classe_raw) {

        if ($classe_raw === null) return 0;

        $s = trim((string)$classe_raw);

        if ($s === '') return 0;

        if (preg_match('/(\d{1,2})/', $s, $m)) {

            $n = (int) $m[1];

            if ($n >= 0 && $n <= 99) return $n;

        }

        return 0;

    }

}

if (!function_exists('sige_get_regra_academica')) {

    /**

     * Obtém a regra académica para um ano e classe_num.

     * Retorna object (linha) ou null.

     */

    function sige_get_regra_academica($ano_lectivo, $classe_num) {

        global $wpdb;

        $tRegras = $wpdb->prefix . 'sige_regras_academicas';

        $ano = (int)$ano_lectivo;

        $classe = (int)$classe_num;

        if ($ano < 2000 || $classe <= 0) return null;

        // Se a tabela não existir ainda, falha silenciosamente (compatibilidade)

        $existe = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tRegras));

        if ($existe !== $tRegras) return null;

        return $wpdb->get_row($wpdb->prepare(

            "SELECT * FROM {$tRegras}

             WHERE escola_id = %d

               AND ano_lectivo = %d

               AND classe_num = %d

               AND ativo = 1

             LIMIT 1",

            function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0,

            $ano,

            $classe

        ));

    }

}

if (!function_exists('sige_is_fim_ciclo')) {

    /**

     * Verifica se uma classe (numérica) é fim de ciclo, baseado na tabela de regras.

     */

    function sige_is_fim_ciclo($ano_lectivo, $classe_num) {

        $regra = sige_get_regra_academica($ano_lectivo, $classe_num);

        if (!$regra) return false;

        return !empty($regra->eh_fim_ciclo);

    }

}

if (!function_exists('sige_is_fim_ciclo_by_turma')) {

    /**

     * Verifica fim de ciclo pela turma (usa sige_turmas.classe -> classe_num).

     */

    function sige_is_fim_ciclo_by_turma($turma_id, $ano_lectivo = null) {

        global $wpdb;

        $tid = (int)$turma_id;

        if ($tid <= 0) return false;

        $ano = $ano_lectivo !== null ? (int)$ano_lectivo : sige_get_ano_lectivo_atual();

        $tTurmas = $wpdb->prefix . 'sige_turmas';

        $classe_raw = $wpdb->get_var($wpdb->prepare("SELECT classe FROM {$tTurmas} WHERE id = %d LIMIT 1", $tid));

        $classe_num = sige_parse_classe_num($classe_raw);

        if ($classe_num <= 0) return false;

        return sige_is_fim_ciclo($ano, $classe_num);

    }

}


if (!function_exists('sige_classe_usa_af')) {

    /**
     * Regra canónica SNE/Produto PRO: a designação AF (Avaliação Final)
     * pertence exclusivamente à 3.ª classe. Outras classes de fim de ciclo
     * podem ter Exame/NF quando a regra académica assim exigir, mas nunca AF.
     */
    function sige_classe_usa_af($classe_num) {
        return (int) $classe_num === 3;
    }

}

if (!function_exists('sige_label_avaliacao_final')) {

    /**
     * Label visível da avaliação final por classe.
     * 3.ª classe: AF. Restantes classes: Exame.
     */
    function sige_label_avaliacao_final($classe_num) {
        return sige_classe_usa_af((int) $classe_num) ? 'AF' : 'Exame';
    }

}

if (!function_exists('sige_label_nota_final')) {

    /**
     * Label visível da nota final ponderada por classe.
     * 3.ª classe: MF. Restantes classes: NF.
     */
    function sige_label_nota_final($classe_num) {
        return sige_classe_usa_af((int) $classe_num) ? 'MF' : 'NF';
    }

}

if (!function_exists('sige_turma_classe_num')) {

    /**
     * Classe numérica a partir da turma, com fallback seguro.
     */
    function sige_turma_classe_num($turma_id) {
        global $wpdb;
        $tid = (int) $turma_id;
        if ($tid <= 0) return 0;
        $tTurmas = $wpdb->prefix . 'sige_turmas';
        $classe_raw = $wpdb->get_var($wpdb->prepare("SELECT classe FROM {$tTurmas} WHERE id=%d LIMIT 1", $tid));
        return function_exists('sige_parse_classe_num') ? (int) sige_parse_classe_num($classe_raw) : (int) preg_replace('/\D+/', '', (string) $classe_raw);
    }

}

if (!function_exists('sige_turma_label_avaliacao_final')) {

    /**
     * Label da avaliação final por turma, sem expor AF fora da 3.ª classe.
     */
    function sige_turma_label_avaliacao_final($turma_id) {
        $classe_num = sige_turma_classe_num((int) $turma_id);
        return sige_label_avaliacao_final($classe_num);
    }

}

if (!function_exists('sige_tipo_terceira_coluna_notas')) {

    /**

     * Determina o tipo da 3ª coluna no lançamento de notas:

     *  - Trimestre 1/2 -> AT

     *  - Trimestre 3 -> AT; a avaliação final fica em coluna própria quando aplicável

     *

     * Retorna: 'AT' ou 'EXAME'

     */

    function sige_tipo_terceira_coluna_notas($turma_id, $trimestre, $ano_lectivo = null) {

        $t = (int)$trimestre;

        if ($t !== 3) return 'AT';

        $ano = $ano_lectivo !== null ? (int)$ano_lectivo : sige_get_ano_lectivo_atual();

        $is_fim = sige_is_fim_ciclo_by_turma((int)$turma_id, $ano);

        return $is_fim ? 'EXAME' : 'AT';

    }

}

if (!function_exists('sige_label_terceira_coluna_notas')) {

    /**

     * Label amigável para UI (Português de Moçambique).

     */

    function sige_label_terceira_coluna_notas($turma_id, $trimestre, $ano_lectivo = null) {

        if (sige_tipo_terceira_coluna_notas($turma_id, $trimestre, $ano_lectivo) !== 'EXAME') {
            return 'AT';
        }
        return function_exists('sige_turma_label_avaliacao_final')
            ? sige_turma_label_avaliacao_final((int) $turma_id)
            : 'Exame';

    }

}

/**

 * ==================================================

 * MOTOR DE SITUAÇÃO FINAL (SNE) - CONFIGURÁVEL

 * ==================================================

 * Regras (padrão, via BD wpq1_sige_regras_academicas):

 * - 0 negativas  => PROGRIDE

 * - 1..N         => TRANSITA (até max_negativas_transita)

 * - acima disso  => REPROVA

 * - Se usa_media_global=1 e média_global < media_minima_global => REPROVA

 *

 * Modo "Pauta Final" (fecho do ano):

 * - Se for fim de ciclo e exige_exame=1, a nota usada por disciplina passa a ser NF/MF

 *   NF/MF = ponderação(MFD, avaliação final) com peso_mfd/peso_exame
 *   AF é designação exclusiva da 3.ª classe; restantes classes usam Exame.

 */

// [DRY-S12] sige_get_regra_academica() - canónica na linha 771 deste ficheiro

if (!function_exists('sige_get_disciplinas_da_turma')) {

    /**

     * Compatível com:

     *  A) Chamada antiga (pautas-view.php):

     *     sige_get_disciplinas_da_turma($turma_id, $turma_row, $tTD, $tDisc, $tMatriz)

     *  B) Chamada nova (academic-logic.php):

     *     sige_get_disciplinas_da_turma($turma_id, $classe_raw, $ano_lectivo = null)

     *

     * Retorna SEMPRE objetos de disciplinas com: id, nome, sigla, categoria, ordem, ordem_pauta

     */

    function sige_get_disciplinas_da_turma(...$args) {

        global $wpdb;

        // Helpers (cacheados) para evitar ruído e erros se tabelas/colunas não existirem

        static $cache = [

            'table_exists' => [],

            'col_exists'   => [],

        ];

        $table_exists = function($table) use ($wpdb, &$cache) {

            if (isset($cache['table_exists'][$table])) return $cache['table_exists'][$table];

            $exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

            $cache['table_exists'][$table] = $exists;

            return $exists;

        };

        $col_exists = function($table, $col) use ($wpdb, &$cache) {

            $k = $table . '::' . $col;

            if (isset($cache['col_exists'][$k])) return $cache['col_exists'][$k];

            // Nota: não dá para parametrizar nome de tabela com prepare; por isso validamos via SHOW COLUMNS e escapamos com backticks

            $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

            $exists = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$safe_table}` LIKE %s", $col));

            $cache['col_exists'][$k] = $exists;

            return $exists;

        };

        $parse_classe_num = function($classe_raw) {

            if (function_exists('sige_parse_classe_num')) {

                $n = (int) sige_parse_classe_num($classe_raw);

                if ($n > 0) return $n;

            }

            $n = (int) preg_replace('/\D+/', '', (string)$classe_raw);

            return $n > 0 ? $n : 0;

        };

        // ============================

        // MODO A (antigo): 5 args + turma_row objeto

        // ============================

        if (count($args) >= 5 && is_object($args[1])) {

            $turma_id  = (int) $args[0];

            $turma_row = $args[1];

            // Tabelas recebidas do pautas-view antigo

            $tTD     = (string) $args[2];

            $tDisc   = (string) $args[3];

            $tMatriz = (string) $args[4];

            // 1) Disciplinas definidas diretamente na turma

            $disc_ids = [];

            if (!empty($tTD) && $table_exists($tTD) && $col_exists($tTD, 'turma_id') && $col_exists($tTD, 'disciplina_id')) {

                $disc_ids = $wpdb->get_col($wpdb->prepare(

                    "SELECT disciplina_id FROM {$tTD} WHERE turma_id = %d",

                    $turma_id

                ));

            }

            // 2) Fallback pela matriz curricular (ciclo + classe)

            if (empty($disc_ids) && !empty($tMatriz) && $table_exists($tMatriz) && $turma_row) {

                $ciclo_id   = (int) ($turma_row->ciclo_id ?? 0);

                $classe_num = $parse_classe_num($turma_row->classe ?? '');

                if ($ciclo_id > 0 && $classe_num > 0 && $col_exists($tMatriz, 'ciclo_id') && $col_exists($tMatriz, 'classe_num') && $col_exists($tMatriz, 'disciplina_id')) {

                    $disc_ids = $wpdb->get_col($wpdb->prepare(

                        "SELECT disciplina_id FROM {$tMatriz} WHERE ciclo_id = %d AND classe_num = %d",

                        $ciclo_id,

                        $classe_num

                    ));

                }

            }

            if (empty($disc_ids) || empty($tDisc) || !$table_exists($tDisc)) return [];

            // Ordem preferida

            $order_by = 'd.nome ASC';

            if ($col_exists($tDisc, 'ordem')) $order_by = 'd.ordem ASC, d.nome ASC';

            // Carregar disciplinas

            $placeholders = implode(',', array_fill(0, count($disc_ids), '%d'));

            $sql = "SELECT d.*,

                           " . ($col_exists($tDisc, 'ordem') ? "d.ordem" : "0") . " AS ordem_pauta

                    FROM {$tDisc} d

                    WHERE d.id IN ($placeholders)

                    ORDER BY {$order_by}";

            $rows = $wpdb->get_results($wpdb->prepare($sql, ...array_map('intval', $disc_ids)));
            return function_exists('sige_sort_disciplinas_oficial') ? sige_sort_disciplinas_oficial($rows) : $rows;

        }

        // ============================

        // MODO B (novo): (turma_id, classe_raw, ano_lectivo?)

        // ============================

        $turma_id   = (int) ($args[0] ?? 0);

        $classe_raw = $args[1] ?? '';

        // $ano_lectivo não é obrigatório aqui, mas mantemos compatibilidade

        // $ano_lectivo = $args[2] ?? null;

        if ($turma_id <= 0) return [];

        // Tabelas padrão

        $tTurmas = $wpdb->prefix . 'sige_turmas';

        $tTD     = $wpdb->prefix . 'sige_turma_disciplinas';

        $tDisc   = $wpdb->prefix . 'sige_disciplinas';

        $tMatriz = $wpdb->prefix . 'sige_matriz_curricular';

        $turma_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tTurmas} WHERE id=%d", $turma_id));

        $classe_num = $parse_classe_num($classe_raw ?: ($turma_row->classe ?? ''));

        // 1) Primeiro: turma_disciplinas (melhor, pois é específico da turma)

        if ($table_exists($tTD) && $table_exists($tDisc) && $col_exists($tTD, 'turma_id') && $col_exists($tTD, 'disciplina_id')) {

            $ord_td = 'd.nome';

            if ($col_exists($tTD, 'ordem_pauta')) $ord_td = 'td.ordem_pauta';

            elseif ($col_exists($tTD, 'ordem'))    $ord_td = 'td.ordem';

            elseif ($col_exists($tDisc, 'ordem'))  $ord_td = 'd.ordem';

            $sql = "SELECT d.*,

                           " . ($ord_td === 'd.nome' ? "0" : "{$ord_td}") . " AS ordem_pauta

                    FROM {$tTD} td

                    INNER JOIN {$tDisc} d ON d.id = td.disciplina_id

                    WHERE td.turma_id = %d

                    ORDER BY {$ord_td} ASC, d.nome ASC";

            $rows = $wpdb->get_results($wpdb->prepare($sql, $turma_id));

            if (!empty($rows)) return function_exists('sige_sort_disciplinas_oficial') ? sige_sort_disciplinas_oficial($rows) : $rows;

        }

        // 2) Segundo: matriz curricular (ciclo + classe)

        if ($table_exists($tMatriz) && $table_exists($tDisc) && $turma_row) {

            $ciclo_id = (int) ($turma_row->ciclo_id ?? 0);

            // Detectar nomes de colunas possíveis (blindado)

            $col_ciclo = $col_exists($tMatriz, 'ciclo_id') ? 'ciclo_id' : ($col_exists($tMatriz, 'ciclo') ? 'ciclo' : '');

            $col_classe = $col_exists($tMatriz, 'classe_num') ? 'classe_num' : ($col_exists($tMatriz, 'classe') ? 'classe' : '');

            $col_disc = $col_exists($tMatriz, 'disciplina_id') ? 'disciplina_id' : '';

            if ($ciclo_id > 0 && $classe_num > 0 && $col_ciclo && $col_classe && $col_disc) {

                $ord_m = 'd.nome';

                if ($col_exists($tMatriz, 'ordem_pauta')) $ord_m = 'm.ordem_pauta';

                elseif ($col_exists($tMatriz, 'ordem'))   $ord_m = 'm.ordem';

                elseif ($col_exists($tDisc, 'ordem'))     $ord_m = 'd.ordem';

                $sql = "SELECT d.*,

                               " . ($ord_m === 'd.nome' ? "0" : "{$ord_m}") . " AS ordem_pauta

                        FROM {$tMatriz} m

                        INNER JOIN {$tDisc} d ON d.id = m.{$col_disc}

                        WHERE m.{$col_ciclo} = %d AND m.{$col_classe} = %d

                        ORDER BY {$ord_m} ASC, d.nome ASC";

                $rows = $wpdb->get_results($wpdb->prepare($sql, $ciclo_id, $classe_num));

                if (!empty($rows)) return function_exists('sige_sort_disciplinas_oficial') ? sige_sort_disciplinas_oficial($rows) : $rows;

            }

        }

        // 3) Último fallback: disciplinas da escola

        if ($table_exists($tDisc)) {

            $order = $col_exists($tDisc, 'ordem') ? 'ordem ASC, nome ASC' : 'nome ASC';

            $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

            $rows = $wpdb->get_results($wpdb->prepare("SELECT d.*, " . ($col_exists($tDisc, 'ordem') ? "d.ordem" : "0") . " AS ordem_pauta FROM {$tDisc} d WHERE d.escola_id = %d ORDER BY {$order}", $eid));
            return function_exists('sige_sort_disciplinas_oficial') ? sige_sort_disciplinas_oficial($rows) : $rows;

        }

        return [];

    }

}

if (!function_exists('sige_get_notas_row')) {

    function sige_get_notas_row($aluno_id, $turma_id, $disciplina_id, $trimestre, $ano_lectivo) {

        global $wpdb;

        $tN = $wpdb->prefix . 'sige_notas';

        // [T6] Filtrar por status aprovado - notas pendentes não devem entrar nos cálculos
        return $wpdb->get_row($wpdb->prepare(

            "SELECT * FROM {$tN}

             WHERE aluno_id=%d AND turma_id=%d AND disciplina_id=%d AND trimestre=%d AND ano_lectivo=%d

               AND (status IS NULL OR status = 'aprovado')

             LIMIT 1",

            (int)$aluno_id, (int)$turma_id, (int)$disciplina_id, (int)$trimestre, (int)$ano_lectivo

        ));

    }

}

if (!function_exists('sige_mt_from_notas')) {

    function sige_mt_from_notas($nota_ac, $nota_acp, $nota_at) {

        if ($nota_ac === null || $nota_acp === null || $nota_at === null) return null;

        $ac  = (float)$nota_ac;

        $acp = (float)$nota_acp;

        $at  = (float)$nota_at;

        // MT = ROUND((2 * MÉDIA(AC, ACP) + AT) / 3, 0)

        $media_acs = ($ac + $acp) / 2;

        $mt = ((2 * $media_acs) + $at) / 3;

        return (int) round($mt, 0);

    }

}

if (!function_exists('sige_avg_int')) {

    function sige_avg_int($vals) {

        $nums = [];

        foreach ((array)$vals as $v) {

            if ($v === null) continue;

            if ($v === '') continue;

            $nums[] = (float)$v;

        }

        if (count($nums) === 0) return null;

        return (int) round(array_sum($nums) / count($nums), 0);

    }

}

if (!function_exists('sige_nf_from_mfd_exame')) {

    function sige_nf_from_mfd_exame($mfd, $exame, $peso_mfd, $peso_exame) {

        if ($mfd === null || $exame === null) return null;

        $pm = max(0, (int)$peso_mfd);

        $pe = max(0, (int)$peso_exame);

        $den = $pm + $pe;

        if ($den <= 0) $den = 100; // fallback seguro

        $nf = (((float)$mfd * $pm) + ((float)$exame * $pe)) / $den;

        return (int) round($nf, 0);

    }

}


if (!function_exists('sige_situacao_final_rotulo_publico')) {

    /**
     * Converte a situação técnica em rótulo pedagógico público.
     * Regra visual usada no Aproveitamento do Aluno e nas Pautas:
     * - REPROVA em classe intermédia => NÃO PROGRIDE
     * - REPROVA em fim de ciclo       => NÃO TRANSITA
     */
    function sige_situacao_final_rotulo_publico($situacao, $eh_fim_ciclo = false) {
        $sit = strtoupper(trim((string)$situacao));
        if ($sit === 'REPROVA' || $sit === 'REPROVADO' || $sit === 'REPROVADA' || $sit === 'NAO PROGRIDE' || $sit === 'NÃO PROGRIDE' || $sit === 'NAO TRANSITA' || $sit === 'NÃO TRANSITA') {
            return $eh_fim_ciclo ? 'NÃO TRANSITA' : 'NÃO PROGRIDE';
        }
        if ($sit === 'TRANSITA') return 'TRANSITA';
        if ($sit === 'PROGRIDE') return 'PROGRIDE';
        if ($sit === 'PENDENTE') return 'PENDENTE';
        return $sit !== '' ? $sit : '-';
    }
}

if (!function_exists('sige_calcular_situacao_final')) {

    /**

     * @param bool $modo_final Se true, usa NF em fim de ciclo (quando exige_exame=1)

     */

    function sige_calcular_situacao_final($aluno_id, $turma_id, $ano_lectivo, $modo_final = false) {

        global $wpdb;

        $aid = (int)$aluno_id;

        $tid = (int)$turma_id;

        $ano = (int)$ano_lectivo;

        $modo_final = (bool)$modo_final;

        // Descobrir classe da turma

        $tT = $wpdb->prefix . 'sige_turmas';

        $classe_raw = $wpdb->get_var($wpdb->prepare("SELECT classe FROM {$tT} WHERE id=%d LIMIT 1", $tid));

        $classe_num = sige_parse_classe_num($classe_raw);

        // Regras

        $regra = sige_get_regra_academica($ano, $classe_num);

        $nota_min = $regra ? (int)$regra->nota_minima_aprovacao : 10;

        $media_min_global = $regra ? (int)$regra->media_minima_global : 10;

        $max_transita = $regra ? (int)$regra->max_negativas_transita : 2;

        $max_progride = $regra ? (int)$regra->max_negativas_progride : 0;

        $usa_media_global = $regra ? ((int)$regra->usa_media_global === 1) : true;

        $eh_fim_ciclo = $regra ? ((int)$regra->eh_fim_ciclo === 1) : false;

        $exige_exame  = $regra ? ((int)$regra->exige_exame === 1) : false;

        $peso_mfd   = $regra ? (int)$regra->peso_mfd : 50;

        $peso_exame = $regra ? (int)$regra->peso_exame : 50;

        // Disciplinas da turma

        $disciplinas_raw = sige_get_disciplinas_da_turma($tid);

        // Normalizar disciplinas (pode vir como lista de objetos ou IDs)

        $disc_ids = [];

        if (is_array($disciplinas_raw)) {

            foreach ($disciplinas_raw as $d) {

                $did = 0;

                if (is_object($d) && isset($d->id)) {

                    $did = (int) $d->id;

                } else {

                    $did = (int) $d;

                }

                if ($did > 0) $disc_ids[] = $did;

            }

        }

$negativas = 0;

        $final_grades = [];

        $nuclear_grades = []; // [T4] Apenas notas finais de disciplinas nucleares (para MG e negativas)
        $nucleares_requeridas = 0;
        $nucleares_com_nota_final = 0;

        $detalhes = [];

        foreach ($disc_ids as $did) {

            // Regra canónica do Aproveitamento do Aluno:
            // notas ausentes contam como 0 para efeito de MFD/NF/situação final.
            // Isto impede que a falta de notas seja interpretada como 0 negativas e vire PROGRIDE.
            $mt = [0, 0, 0];

            $exame_t3 = null;

            for ($tri = 1; $tri <= 3; $tri++) {

                $row = sige_get_notas_row($aid, $tid, $did, $tri, $ano);

                $ac  = ($row && is_numeric($row->nota_ac))    ? (float)$row->nota_ac    : 0.0;
                $acp = ($row && is_numeric($row->nota_acp))   ? (float)$row->nota_acp   : 0.0;
                $at  = ($row && is_numeric($row->nota_exame)) ? (float)$row->nota_exame : 0.0;

                $media_acs = ($ac + $acp) / 2;
                $mt[$tri-1] = (int) round(((2 * $media_acs) + $at) / 3, 0);

                if ($tri === 3) {

                    // No T3, nota_at guarda a avaliação final quando aplicável.
                    // AF é exclusiva da 3.ª classe; restantes classes usam Exame.
                    $exame_t3 = ($row && isset($row->nota_at) && is_numeric($row->nota_at))
                        ? (int) round((float) $row->nota_at, 0)
                        : null;

                }

            }

            $mfd = (int) round(((int)$mt[0] + (int)$mt[1] + (int)$mt[2]) / 3, 0);

            $nf = null;

            $nota_usada = $mfd;

            if ($modo_final && $eh_fim_ciclo && $exige_exame) {

                $nf = sige_nf_from_mfd_exame($mfd, $exame_t3, $peso_mfd, $peso_exame);

                $nota_usada = $nf;

            }

            // [T4] Determinar se disciplina é nuclear para esta classe
            // Negativas e Média Global contam APENAS para disciplinas nucleares (regra SNE)
            $eh_nuclear = sige_is_nuclear_by_matriz($classe_raw, $did);

            if ($eh_nuclear) {
                $nucleares_requeridas++;
            }

            if ($nota_usada !== null) {

                $final_grades[] = $nota_usada;

                if ($eh_nuclear) {

                    $nuclear_grades[] = $nota_usada;
                    $nucleares_com_nota_final++;

                    if ($nota_usada < $nota_min) $negativas++;

                }

            }

            $detalhes[(string)$did] = [

                'mt1' => $mt[0],

                'mt2' => $mt[1],

                'mt3' => $mt[2],

                'mfd' => $mfd,

                'exame' => $exame_t3,

                'nf' => $nf,

                'final' => $nota_usada,

                'categoria' => $eh_nuclear ? 'nuclear' : 'complementar'

            ];

        }

        // [T4] Média Global calculada apenas sobre disciplinas nucleares (regra SNE)
        $media_global = sige_avg_int($nuclear_grades);

        // Situação
        // Com a regra do Aproveitamento, ausência de nota já entra como 0.
        // PENDENTE fica reservado apenas para configuração académica incompleta
        // (ex.: turma sem disciplinas nucleares), não para aluno sem notas.
        $resultado_pendente = empty($disc_ids) || $nucleares_requeridas <= 0 || empty($nuclear_grades) || ($media_global === null);

        if ($resultado_pendente) {
            return [
                'situacao' => 'PENDENTE',
                'negativas' => null,
                'media_global' => null,
                'eh_fim_ciclo' => $eh_fim_ciclo,
                'exige_exame' => $exige_exame,
                'detalhes_disciplinas' => $detalhes,
                'classe_num' => $classe_num,
                'classe_raw' => $classe_raw,
                'pendente' => true,
                'motivo' => 'configuracao_academica_incompleta'
            ];
        }

        $situacao = 'PROGRIDE';

        if ($usa_media_global && $media_global !== null && $media_global < $media_min_global) {

            $situacao = 'REPROVA';

        } else {

            if ($negativas <= $max_progride) {

                $situacao = 'PROGRIDE';

            } elseif ($negativas <= $max_transita) {

                $situacao = 'TRANSITA';

            } else {

                $situacao = 'REPROVA';

            }

        }

        // Norma SNE: classes de fim de ciclo (3ª e 6ª EP, 9ª e 12ª ESG) usam TRANSITA;

        // classes intermédias (1ª, 2ª, 4ª, 5ª, 7ª, 8ª, 10ª, 11ª) usam PROGRIDE.

        // Aplica apenas quando o aluno não reprovaria.

        if ($situacao !== 'REPROVA') {

            if ($eh_fim_ciclo) {

                $situacao = 'TRANSITA'; // Fim de ciclo: sempre TRANSITA quando aprovado

            } else {

                $situacao = 'PROGRIDE'; // Classe intermédia: sempre PROGRIDE quando aprovado

            }

        }

        return [

            'situacao' => $situacao,

            'negativas' => $negativas,

            'media_global' => $media_global,

            'eh_fim_ciclo' => $eh_fim_ciclo,

            'exige_exame' => $exige_exame,

            'detalhes_disciplinas' => $detalhes,

            'classe_num' => $classe_num,

            'classe_raw' => $classe_raw

        ];

    }

}

// ═══════════════════════════════════════════════════════════════════════
// [T3] Helpers arquitecturais - categoria por (classe × disciplina) e
//      ordem canónica SNE para disciplinas
// ═══════════════════════════════════════════════════════════════════════
// Criados no Sprint 1 (Caminho A) do refactor arquitectural.
// Dependências: T1 (coluna `categoria` na `sige_matriz_curricular`)
//               T2 (disciplina Ed.V unificada em id=18)
//
// Estes helpers são ADITIVOS - nenhum código existente é modificado.
// São consumidos a partir de T4/T5.
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('sige_get_categoria_by_matriz')) {

    /**
     * Retorna a categoria ('nuclear' ou 'complementar') de uma disciplina
     * para uma classe específica, lendo da sige_matriz_curricular.
     *
     * A categoria VARIA POR CLASSE - ex: Ed. Visual é complementar nas
     * 1ª-5ª mas nuclear na 6ª (fim do 2º ciclo). Por isso não basta ler
     * de sige_disciplinas.categoria (que é estática por disciplina).
     *
     * Fallbacks (por esta ordem):
     *   1. sige_matriz_curricular.categoria WHERE classe = %s AND disciplina_id = %d
     *   2. sige_disciplinas.categoria WHERE id = %d
     *   3. 'complementar' (safe default)
     *
     * Performance: cache estático por request (1 query por par único).
     *
     * @param string   $classe_raw    Classe tal como está na BD ('1ª', '2ª', '3ª', '4ª', '5ª', '6ª', etc.)
     * @param int      $disciplina_id ID da disciplina
     * @param int|null $escola_id     ID da escola (default: sige_get_escola_id() ou 1)
     * @return string 'nuclear' ou 'complementar'
     */
    function sige_get_categoria_by_matriz($classe_raw, $disciplina_id, $escola_id = null) {

        global $wpdb;
        static $cache = [];

        $did = (int) $disciplina_id;
        $classe = trim((string) $classe_raw);
        $eid = $escola_id !== null
            ? (int) $escola_id
            : (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0);

        // Cache key: "1|3ª|18" → evita query repetida para o mesmo par
        $ck = "{$eid}|{$classe}|{$did}";
        if (isset($cache[$ck])) {
            return $cache[$ck];
        }

        // [MALISA-V2] Para 1.ª-6.ª classes, usar a regra oficial validada pelo cliente.
        $classe_num_oficial = function_exists('sige_parse_classe_num') ? (int)sige_parse_classe_num($classe) : (int)preg_replace('/\D+/', '', $classe);
        if ($classe_num_oficial >= 1 && $classe_num_oficial <= 6 && function_exists('sige_categoria_oficial_malisa')) {
            $tDiscOficial = $wpdb->prefix . 'sige_disciplinas';
            $disc_obj = $wpdb->get_row($wpdb->prepare("SELECT sigla,nome FROM `{$tDiscOficial}` WHERE id=%d LIMIT 1", $did));
            if ($disc_obj) {
                $cache[$ck] = sige_categoria_oficial_malisa($classe, $disc_obj);
                return $cache[$ck];
            }
        }

        // Fallback 1: sige_matriz_curricular (fonte autoritativa pós-T1)
        $tMatriz = $wpdb->prefix . 'sige_matriz_curricular';
        $cat = $wpdb->get_var($wpdb->prepare(
            "SELECT categoria FROM `{$tMatriz}`
             WHERE escola_id = %d AND classe = %s AND disciplina_id = %d
             LIMIT 1",
            $eid, $classe, $did
        ));

        if ($cat !== null && $cat !== '') {
            $cache[$ck] = $cat;
            return $cat;
        }

        // Fallback 2: sige_disciplinas (compatibilidade com escolas que ainda não
        // tenham a coluna `categoria` na matriz - ex: durante rollback)
        $tDisc = $wpdb->prefix . 'sige_disciplinas';
        $cat = $wpdb->get_var($wpdb->prepare(
            "SELECT categoria FROM `{$tDisc}` WHERE id = %d AND escola_id = %d LIMIT 1",
            $did, $eid
        ));

        if ($cat !== null && $cat !== '') {
            $cache[$ck] = $cat;
            return $cat;
        }

        // Fallback 3: safe default
        $cache[$ck] = 'complementar';
        return 'complementar';
    }

}

if (!function_exists('sige_ordem_canonica_sql')) {

    /**
     * Retorna um fragmento SQL CASE para ordenar disciplinas pela sequência
     * oficial do SNE (Sistema Nacional de Educação), usável em ORDER BY.
     *
     * Sequência oficial:
     *   1. Português
     *   2. Matemática
     *   3. Ciências Sociais
     *   4. Ciências Naturais
     *   5. Inglês
     *   6. Educação Visual
     *   7. Ofícios
     *   8. Educação Física
     *   99. Quaisquer outras (por d.nome)
     *
     * Uso típico:
     *   $ordem = sige_ordem_canonica_sql('d.sigla');
     *   $sql = "SELECT ... ORDER BY {$ordem}, d.nome ASC";
     *
     * O CASE funciona pela coluna de sigla passada como parâmetro para
     * permitir uso flexível (d.sigla, disc.sigla, s.sigla, etc.).
     *
     * @param string $sigla_col Nome da coluna de sigla na query (default: 'd.sigla')
     * @return string Fragmento SQL CASE pronto para ORDER BY
     */
    function sige_ordem_canonica_sql($sigla_col = 'd.sigla') {

        // Sanitizar: permitir apenas letras, underscore e ponto (ex: 'd.sigla')
        $col = preg_replace('/[^a-zA-Z0-9_.]/', '', $sigla_col);
        if (empty($col)) $col = 'd.sigla';

        return "CASE {$col}
            WHEN 'POR' THEN 1
            WHEN 'MAT' THEN 2
            WHEN 'MAT(4-6)' THEN 2
            WHEN 'CS' THEN 3
            WHEN 'CN' THEN 4
            WHEN 'ING' THEN 5
            WHEN 'ED.VISUAL' THEN 6
            WHEN 'ED. V' THEN 6
            WHEN 'OF' THEN 7
            WHEN 'ED. FISICA' THEN 8
            WHEN 'ED.FISICA' THEN 8
            WHEN 'EF' THEN 8
            ELSE 99
        END";
    }

}

if (!function_exists('sige_is_nuclear_by_matriz')) {

    /**
     * Atalho booleano: retorna true se a disciplina é nuclear para esta classe.
     *
     * @param string   $classe_raw    Classe ('1ª', '2ª', '3ª', '4ª', '5ª', '6ª', etc.)
     * @param int      $disciplina_id ID da disciplina
     * @param int|null $escola_id     ID da escola (default: auto-detect)
     * @return bool
     */
    function sige_is_nuclear_by_matriz($classe_raw, $disciplina_id, $escola_id = null) {

        return sige_get_categoria_by_matriz($classe_raw, $disciplina_id, $escola_id) === 'nuclear';
    }

}


// ═══════════════════════════════════════════════════════════════════════
// [MALISA-V2] Ordem e categoria oficiais - fonte única de verdade
// ═══════════════════════════════════════════════════════════════════════
// Objectivo: impedir que Pauta, DEC, Aproveitamento e PDFs usem ordens
// diferentes quando a BD tiver ordem_pauta antiga ou quando a sigla da
// disciplina variar ligeiramente entre instalações.

if (!function_exists('sige_sigla_disciplina_normalizada')) {
    function sige_sigla_disciplina_normalizada($disciplina): string {
        $sigla = '';
        if (is_object($disciplina)) {
            $sigla = (string)($disciplina->sigla ?? $disciplina->nome ?? '');
        } elseif (is_array($disciplina)) {
            $sigla = (string)($disciplina['sigla'] ?? $disciplina['nome'] ?? '');
        } else {
            $sigla = (string)$disciplina;
        }
        $s = strtoupper(trim($sigla));
        $s = str_replace(['Á','À','Â','Ã','É','Ê','Í','Ó','Ô','Õ','Ú','Ç'], ['A','A','A','A','E','E','I','O','O','O','U','C'], $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        if (strpos($s, 'PORT') !== false || $s === 'POR') return 'POR';
        if (strpos($s, 'MAT') !== false || $s === 'MAT(4-6)') return 'MAT';
        if ($s === 'CS' || strpos($s, 'SOCIA') !== false || strpos($s, 'C. SOC') !== false) return 'CS';
        if ($s === 'CN' || strpos($s, 'NATURA') !== false || strpos($s, 'C. NAT') !== false) return 'CN';
        if (strpos($s, 'ING') !== false) return 'ING';
        if (strpos($s, 'VISUAL') !== false || $s === 'ED.VISUAL' || $s === 'ED. V') return 'ED.VISUAL';
        if (strpos($s, 'OF') === 0 || strpos($s, 'OFIC') !== false) return 'OF';
        if (strpos($s, 'FISICA') !== false || $s === 'EF') return 'ED.FISICA';

        return $s;
    }
}



if (!function_exists('sige_sigla_disciplina_oficial')) {
    function sige_sigla_disciplina_oficial($disciplina): string {
        $sig = sige_sigla_disciplina_normalizada($disciplina);
        $labels = [
            'POR' => 'POR',
            'MAT' => 'MAT',
            'CS' => 'CS',
            'CN' => 'CN',
            'ING' => 'ING',
            'ED.VISUAL' => 'ED.VISUAL',
            'OF' => 'OF',
            'ED.FISICA' => 'ED. FISICA',
        ];
        return $labels[$sig] ?? $sig;
    }
}

if (!function_exists('sige_ordem_disciplina_oficial')) {
    function sige_ordem_disciplina_oficial($disciplina): int {
        $sig = sige_sigla_disciplina_normalizada($disciplina);
        $ordem = ['POR'=>1,'MAT'=>2,'CS'=>3,'CN'=>4,'ING'=>5,'ED.VISUAL'=>6,'OF'=>7,'ED.FISICA'=>8];
        return $ordem[$sig] ?? 99;
    }
}

if (!function_exists('sige_sort_disciplinas_oficial')) {
    function sige_sort_disciplinas_oficial($disciplinas): array {
        if (empty($disciplinas) || !is_array($disciplinas)) return is_array($disciplinas) ? $disciplinas : [];
        usort($disciplinas, function($a, $b) {
            $oa = sige_ordem_disciplina_oficial($a);
            $ob = sige_ordem_disciplina_oficial($b);
            if ($oa === $ob) {
                $na = is_object($a) ? (string)($a->nome ?? $a->sigla ?? '') : (string)($a['nome'] ?? $a['sigla'] ?? '');
                $nb = is_object($b) ? (string)($b->nome ?? $b->sigla ?? '') : (string)($b['nome'] ?? $b['sigla'] ?? '');
                return strcasecmp($na, $nb);
            }
            return $oa <=> $ob;
        });
        foreach ($disciplinas as $d) {
            $ord = sige_ordem_disciplina_oficial($d);
            $sig = function_exists('sige_sigla_disciplina_oficial') ? sige_sigla_disciplina_oficial($d) : sige_sigla_disciplina_normalizada($d);
            if (is_object($d)) { $d->ordem = $ord; $d->ordem_pauta = $ord; if ($ord < 99) $d->sigla = $sig; }
            elseif (is_array($d)) { $d['ordem'] = $ord; $d['ordem_pauta'] = $ord; if ($ord < 99) $d['sigla'] = $sig; }
        }
        return $disciplinas;
    }
}

if (!function_exists('sige_categoria_oficial_malisa')) {
    function sige_categoria_oficial_malisa($classe_raw, $disciplina): string {
        $classe_num = function_exists('sige_parse_classe_num') ? (int)sige_parse_classe_num($classe_raw) : (int)preg_replace('/\D+/', '', (string)$classe_raw);
        $sig = sige_sigla_disciplina_normalizada($disciplina);
        $nucleares = [
            1 => ['POR','MAT'],
            2 => ['POR','MAT'],
            3 => ['POR','MAT'],
            4 => ['POR','MAT','CS','CN'],
            5 => ['POR','MAT','CS','CN'],
            6 => ['POR','MAT','CS','CN','ED.VISUAL'],
        ];
        return in_array($sig, $nucleares[$classe_num] ?? [], true) ? 'nuclear' : 'complementar';
    }
}

if (!function_exists('sige_disciplina_field_value')) {
    /** [MALISA-V70] Leitura segura de campos em objectos/arrays de disciplina. */
    function sige_disciplina_field_value($disciplina, string $field, $default = '') {
        if (is_object($disciplina)) return $disciplina->{$field} ?? $default;
        if (is_array($disciplina)) return $disciplina[$field] ?? $default;
        return $default;
    }
}

if (!function_exists('sige_set_disciplina_field_value')) {
    /** [MALISA-V70] Escrita segura de campos em objectos/arrays de disciplina. */
    function sige_set_disciplina_field_value(&$disciplina, string $field, $value): void {
        if (is_object($disciplina)) $disciplina->{$field} = $value;
        elseif (is_array($disciplina)) $disciplina[$field] = $value;
    }
}

if (!function_exists('sige_deduplicate_disciplinas_oficial')) {
    /**
     * [MALISA-V70] Remove duplicações funcionais depois da normalização oficial.
     * Caso crítico: Educação Visual duplicada na 4.ª/5.ª deve manter a versão auxiliar.
     */
    function sige_deduplicate_disciplinas_oficial($disciplinas, $classe_raw = null): array {
        if (empty($disciplinas) || !is_array($disciplinas)) return is_array($disciplinas) ? $disciplinas : [];

        $classe_num = function_exists('sige_parse_classe_num')
            ? (int) sige_parse_classe_num($classe_raw)
            : (int) preg_replace('/\D+/', '', (string) $classe_raw);

        $by_sig = [];
        foreach ($disciplinas as $d) {
            $sig = sige_sigla_disciplina_normalizada($d);
            if ($sig === '') $sig = 'ID:' . (string) sige_disciplina_field_value($d, 'id', '');

            if (!isset($by_sig[$sig])) {
                $by_sig[$sig] = $d;
                continue;
            }

            $actual = $by_sig[$sig];
            $cat_actual = strtolower((string) sige_disciplina_field_value($actual, 'categoria', ''));
            $cat_novo   = strtolower((string) sige_disciplina_field_value($d, 'categoria', ''));
            $sig_actual = strtoupper((string) sige_disciplina_field_value($actual, 'sigla', ''));
            $sig_novo   = strtoupper((string) sige_disciplina_field_value($d, 'sigla', ''));
            $id_actual  = (int) sige_disciplina_field_value($actual, 'id', 0);
            $id_novo    = (int) sige_disciplina_field_value($d, 'id', 0);

            $prefer_new = false;
            if ($classe_num >= 1 && $classe_num <= 6) {
                $cat_oficial = sige_categoria_oficial_malisa($classe_raw, $d);
                if ($cat_novo === $cat_oficial && $cat_actual !== $cat_oficial) $prefer_new = true;
            }
            if (!$prefer_new && $sig === 'MAT' && $sig_novo === 'MAT' && $sig_actual !== 'MAT') $prefer_new = true;
            if (!$prefer_new && $id_actual > 0 && $id_novo > 0 && $id_novo < $id_actual && $cat_actual === $cat_novo) $prefer_new = true;
            if ($prefer_new) $by_sig[$sig] = $d;
        }
        return array_values($by_sig);
    }
}

if (!function_exists('sige_apply_categoria_oficial_disciplinas')) {
    function sige_apply_categoria_oficial_disciplinas($disciplinas, $classe_raw): array {
        if (empty($disciplinas) || !is_array($disciplinas)) return is_array($disciplinas) ? $disciplinas : [];
        foreach ($disciplinas as &$d) {
            $cat = sige_categoria_oficial_malisa($classe_raw, $d);
            sige_set_disciplina_field_value($d, 'categoria', $cat);
        }
        unset($d);
        return sige_deduplicate_disciplinas_oficial($disciplinas, $classe_raw);
    }
}


// ═══════════════════════════════════════════════════════════════════════
// [FIX D01] Identificação centralizada do professor actual
// ═══════════════════════════════════════════════════════════════════════
// Método 1: sige_professor_id do wp_usermeta (ligação directa, mais fiável)
// Método 2: Fallback por email (compatibilidade com registos antigos)
// Auto-correcção: grava o meta se encontrar por email para futuras sessões.
// Retorna: object {id, nome_completo, email, ...} ou null se não encontrado.
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('sige_get_professor_atual')) {

    function sige_get_professor_atual(): ?object {
        global $wpdb;

        $user = wp_get_current_user();
        if (!$user || !$user->ID) return null;

        $tP = $wpdb->prefix . 'sige_professores';
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

        // Método 1: usermeta sige_professor_id
        $stored_id = get_user_meta($user->ID, 'sige_professor_id', true);
        if (!empty($stored_id)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tP WHERE id = %d AND escola_id = %d AND status_ativo = 1 LIMIT 1",
                (int)$stored_id, $eid
            ));
            if ($row) return $row;
            // Meta inválido - limpar para não bloquear o fallback
            delete_user_meta($user->ID, 'sige_professor_id');
        }

        // Método 2: fallback por email
        if (!empty($user->user_email)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $tP WHERE email = %s AND escola_id = %d AND status_ativo = 1 LIMIT 1",
                $user->user_email, $eid
            ));
            if ($row) {
                // Auto-correcção: gravar meta para futuras sessões
                update_user_meta($user->ID, 'sige_professor_id', (int)$row->id);
                return $row;
            }
        }

        return null;
    }

}

// Helper curto: retorna só o ID (int) ou 0
if (!function_exists('sige_get_professor_atual_id')) {

    function sige_get_professor_atual_id(): int {
        $prof = sige_get_professor_atual();
        return $prof ? (int)$prof->id : 0;
    }

}


// ═══════════════════════════════════════════════════════════════════════
// [V7.2.5] Escopo Docente - professor vê apenas as suas turmas/disciplinas
// ═══════════════════════════════════════════════════════════════════════

if (!function_exists('sige_is_scoped_professor_user')) {
    /**
     * TRUE quando o utilizador deve operar em escopo docente.
     *
     * Nota de produto:
     * Mesmo que a matriz de permissões conceda acesso a várias páginas, o perfil
     * Professor não deve ver/operar dados académicos globais. Direcção,
     * Pedagógico, Secretaria e Admin mantêm visão ampla.
     */
    function sige_is_scoped_professor_user(?int $user_id = null): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) return false;

        if (function_exists('is_super_admin') && is_super_admin($user_id)) return false;
        if (user_can($user_id, 'manage_options')) return false;

        $role_slug = '';
        if (function_exists('sige_permissions_get_active_role')) {
            $role = sige_permissions_get_active_role($user_id);
            if ($role && !empty($role->slug)) {
                $role_slug = sanitize_key((string)$role->slug);
            }
        }

        $supervisor_slugs = [
            'admin_ti','admin_escola','director','direccao','pedagogico',
            'director_pedagogico','secretario','secretaria','secretaria_geral',
            'assistente','recepcao'
        ];
        if ($role_slug !== '' && in_array($role_slug, $supervisor_slugs, true)) {
            return false;
        }

        // Fonte preferencial: Perfil SIGE activo.
        if ($role_slug === 'professor' || $role_slug === 'docente') {
            return true;
        }

        // Fallback compatível para instalações ainda baseadas em WP roles/caps.
        $user = get_userdata($user_id);
        $roles = ($user && !empty($user->roles)) ? array_map('sanitize_key', (array)$user->roles) : [];
        if (array_intersect($roles, ['administrator','sige_director','sige_pedagogico','sige_secretario','sige_secretaria_geral','sige_assistente','sige_admin_ti'])) {
            return false;
        }

        return user_can($user_id, 'sige_professor') && !user_can($user_id, 'sige_pedagogico') && !user_can($user_id, 'sige_director') && !user_can($user_id, 'sige_secretario');
    }
}

if (!function_exists('sige_professor_turma_ids')) {
    /**
     * Lista de IDs de turmas atribuídas ao professor actual.
     * Inclui director_turma_id e vínculos em sige_turma_disciplinas.
     */
    function sige_professor_turma_ids(?int $professor_id = null, ?int $escola_id = null): array {
        global $wpdb;

        $professor_id = $professor_id ?: (function_exists('sige_get_professor_atual_id') ? sige_get_professor_atual_id() : 0);
        $escola_id = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($professor_id <= 0 || $escola_id <= 0) return [];

        $tT  = $wpdb->prefix . 'sige_turmas';
        $tTD = $wpdb->prefix . 'sige_turma_disciplinas';

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT x.id FROM (
                SELECT t.id
                  FROM {$tT} t
                 WHERE t.escola_id = %d
                   AND t.director_turma_id = %d
                UNION
                SELECT td.turma_id AS id
                  FROM {$tTD} td
                  INNER JOIN {$tT} t ON t.id = td.turma_id AND t.escola_id = %d
                 WHERE td.escola_id = %d
                   AND td.professor_id = %d
            ) x",
            $escola_id, $professor_id, $escola_id, $escola_id, $professor_id
        ));

        return array_values(array_unique(array_map('intval', (array)$ids)));
    }
}

if (!function_exists('sige_professor_can_access_turma')) {
    function sige_professor_can_access_turma(int $turma_id, ?int $professor_id = null, ?int $escola_id = null): bool {
        if ($turma_id <= 0) return false;
        $ids = sige_professor_turma_ids($professor_id, $escola_id);
        return in_array($turma_id, $ids, true);
    }
}

if (!function_exists('sige_professor_can_access_disciplina')) {
    function sige_professor_can_access_disciplina(int $turma_id, int $disciplina_id, ?int $professor_id = null, ?int $escola_id = null): bool {
        global $wpdb;

        $professor_id = $professor_id ?: (function_exists('sige_get_professor_atual_id') ? sige_get_professor_atual_id() : 0);
        $escola_id = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($professor_id <= 0 || $escola_id <= 0 || $turma_id <= 0 || $disciplina_id <= 0) return false;

        $tTD = $wpdb->prefix . 'sige_turma_disciplinas';
        $ok = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
               FROM {$tTD}
              WHERE escola_id = %d
                AND turma_id = %d
                AND disciplina_id = %d
                AND professor_id = %d",
            $escola_id, $turma_id, $disciplina_id, $professor_id
        ));

        return ((int)$ok) > 0;
    }
}

if (!function_exists('sige_render_teacher_scope_denied')) {
    function sige_render_teacher_scope_denied(string $msg = 'O seu perfil de professor só permite consultar turmas e disciplinas atribuídas a si.'): void {
        echo '<div class="notice notice-warning" style="padding:18px 22px;margin:22px 0;border-radius:16px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-family:Segoe UI,Tahoma,sans-serif;">';
        echo '<strong>🔒 Escopo docente activo</strong><br>';
        echo esc_html($msg);
        echo '</div>';
    }
}


// ═══════════════════════════════════════════════════════════════════════
// [v12.11.9.17] NORMALIZAÇÃO CANÓNICA DE GÉNERO - fonte única de verdade
// ═══════════════════════════════════════════════════════════════════════
// Objectivo: acabar de vez com divergências de estatística por género entre
// DEC, Pauta Final, ACTA, Estatísticas Demográficas e Dashboard. Todos os
// módulos passam a poder usar a MESMA regra, máxima cobertura de variantes.
//
// Convenção devolvida (interna SIGE): 'M' = Masculino, 'F' = Feminino,
// 'U' = desconhecido/não classificável. O 'U' NUNCA é forçado para um sexo -
// entra apenas no total HM, exactamente como o DEC faz count(all).
//
// Nota técnica: o campo sige_alunos.genero é CHAR(1). Valores escritos por
// extenso ("Masculino", "Feminino", "Mulher", "Menina", "Rapariga"...) são
// truncados pela base para o 1.º caractere no momento da gravação. Esta função
// reconhece tanto os códigos curtos (M/F/H) como as palavras completas (úteis
// em dados migrados/importados onde a coluna possa ter sido alargada).
if (!function_exists('sige_genero_bucket')) {
    function sige_genero_bucket($raw): string {
        $s = trim((string) ($raw ?? ''));
        if ($s === '') return 'U';
        $u = function_exists('remove_accents') ? remove_accents($s) : $s;
        $u = function_exists('mb_strtoupper') ? mb_strtoupper($u, 'UTF-8') : strtoupper($u);
        $u = preg_replace('/[^A-Z0-9]/', '', (string) $u);
        if ($u === '') return 'U';

        // 1) Tokens completos (mais específicos primeiro).
        $fem  = ['F','FEM','FEMININO','FEMININA','FEMEA','MULHER','MULHERES','FEMALE','MENINA','RAPARIGA','GAROTA','MOCA','SENHORA','2'];
        $masc = ['M','MASC','MASCULINO','MASCULINA','MACHO','MALE','HOMEM','HOMENS','H','MENINO','RAPAZ','GAROTO','MOCO','SENHOR','1'];
        if (in_array($u, $fem,  true)) return 'F';
        if (in_array($u, $masc, true)) return 'M';

        // 2) Heurística de 1.ª letra (apenas para strings NÃO reconhecidas acima,
        //    como dados livres em colunas alargadas). 'F'->Feminino, 'H'->Masculino.
        //    'M' isolado já foi resolvido como Masculino na lista canónica; aqui
        //    um inicio "M..." desconhecido não é adivinhado para evitar o caso
        //    ambíguo Menina/Masculino quando a origem é texto livre.
        $c = $u[0];
        if ($c === 'F') return 'F';
        if ($c === 'H') return 'M';

        return 'U';
    }
}

// Etiquetas e mapeamento auxiliar para quem precisa de Homens/Mulheres.
if (!function_exists('sige_genero_label')) {
    function sige_genero_label(string $bucket): string {
        switch ($bucket) {
            case 'M': return 'Masculino';
            case 'F': return 'Feminino';
            default:  return 'Por confirmar';
        }
    }
}
