<?php

/**

 * SIGE SoftGenial - Handler PDF da Pauta

 * Registado via: add_action('admin_post_sige_pauta_pdf', ...)

 * URL: admin-post.php?action=sige_pauta_pdf&...

 */

if (!defined('ABSPATH')) exit;

add_action('admin_post_sige_pauta_pdf', 'sige_handle_pauta_pdf');

function sige_handle_pauta_pdf() {

    if (function_exists('sige_require_user_can_any_secure')) {
        sige_require_user_can_any_secure(
            ['academico.pautas_ver','academico.pautas_emitir','academico.lancar_notas'],
            ['sige_professor','sige_pedagogico','sige_secretario','sige_director'],
            'Acesso negado. Apenas docentes e staff autorizados podem exportar pautas.'
        );
    } elseif (!current_user_can('sige_professor') && !current_user_can('sige_pedagogico') && !current_user_can('sige_secretario') && !current_user_can('sige_director') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
        wp_die('Acesso negado. Apenas docentes e staff podem exportar pautas.', 403);
    }

    check_admin_referer('sige_pauta_pdf_nonce');

    global $wpdb;

    $p = $wpdb->prefix;
    $eid = sige_get_escola_id();

    $ano_lectivo   = isset($_GET['ano_lectivo'])     ? (int)$_GET['ano_lectivo']     : (int)wp_date('Y');

    $turma_id      = isset($_GET['turma_id'])         ? (int)$_GET['turma_id']         : 0;

    $trimestre     = isset($_GET['trimestre'])        ? (int)$_GET['trimestre']        : 0;
    if ($trimestre < 0 || $trimestre > 3) {
        $trimestre = 0;
    }

    $pauta_final_m = !empty($_GET['pauta_final_mode']) ? 1 : 0;

    if (!$turma_id) wp_die('Turma não especificada.');

    $turma = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sige_turmas WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id, $eid));

    if (!$turma) wp_die('Turma não encontrada.');

    // [v12.10.68] Exportação respeita o mesmo escopo docente da página de pautas.
    if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
        $_prof_id_scope = function_exists('sige_get_professor_atual_id') ? (int)sige_get_professor_atual_id() : 0;
        if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, $_prof_id_scope, (int)$eid)) {
            wp_die('Acesso negado. Esta pauta pertence a uma turma não atribuída ao seu perfil.', 403);
        }
    }

    $classe_num = (int) preg_replace('/\D+/', '', (string)($turma->classe ?? ''));

    // Regras

    $regra = ['eh_fim_ciclo'=>0,'peso_mfd'=>60,'peso_exame'=>40,

              'nota_minima_aprovacao'=>10,'max_negativas_transita'=>2,'max_negativas_progride'=>0];

    if ($classe_num > 0) {

        $r = $wpdb->get_row($wpdb->prepare(

            "SELECT * FROM {$p}sige_regras_academicas WHERE escola_id=%d AND ano_lectivo=%d AND classe_num=%d ORDER BY id DESC LIMIT 1",

            function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0, $ano_lectivo, $classe_num

        ), ARRAY_A);

        if ($r) $regra = array_merge($regra, $r);

    }

    $eh_fim_ciclo = (int)($regra['eh_fim_ciclo'] ?? 0) === 1;

    // Disciplinas - UNION fix: combina sige_turma_disciplinas + sige_matriz_curricular

    // Garante que todas as disciplinas da classe aparecem, mesmo que sige_turma_disciplinas esteja incompleto

    $classe_turma = $turma->classe ?? '';

    $disciplinas = $wpdb->get_results( $wpdb->prepare(

        "SELECT d.id, d.nome, d.sigla, d.categoria,

                COALESCE(

                    mc.ordem_pauta,

                    CASE d.sigla

                        WHEN 'POR'        THEN 1

                        WHEN 'MAT'        THEN 2

                        WHEN 'CS'         THEN 3

                        WHEN 'CN'         THEN 4

                        WHEN 'ING'        THEN 5

                        WHEN 'ED.VISUAL'  THEN 6

                        WHEN 'ED. V'      THEN 6

                        WHEN 'OF'         THEN 7

                        WHEN 'ED. FISICA' THEN 8

                        WHEN 'ED.FISICA'  THEN 8

                        ELSE 99

                    END

                ) AS ordem

         FROM {$p}sige_disciplinas d

         LEFT JOIN {$p}sige_matriz_curricular mc

             ON mc.disciplina_id = d.id AND mc.classe = %s

         WHERE d.id IN (

             SELECT disciplina_id FROM {$p}sige_turma_disciplinas WHERE turma_id = %d AND escola_id = %d

             UNION

             SELECT disciplina_id FROM {$p}sige_matriz_curricular WHERE classe = %s AND escola_id = %d

         )

         ORDER BY ordem, d.nome",

        $classe_turma, $turma_id, $eid, $classe_turma, $eid

    ));

    // [v12.10.68] Categoria por classe: mantém exportação alinhada à pauta no ecrã.
    if (!empty($classe_turma) && function_exists('sige_get_categoria_by_matriz')) {
        foreach ($disciplinas as &$_d) {
            if (isset($_d->id)) {
                $_d->categoria = sige_get_categoria_by_matriz($classe_turma, $_d->id, $eid);
            }
        }
        unset($_d);
    }

    // [MALISA-V2 sort guard] Ordem/categoria oficiais Malisa para 1.ª-6.ª.
    if (function_exists('sige_apply_categoria_oficial_disciplinas')) { $disciplinas = sige_apply_categoria_oficial_disciplinas($disciplinas, $classe_turma); }
    if (function_exists('sige_sort_disciplinas_oficial')) { $disciplinas = sige_sort_disciplinas_oficial($disciplinas); }

    // Alunos

    $pop_ch = function_exists('sige_aluno_matricula_activa_sql') ? sige_aluno_matricula_activa_sql('a','m') : "(m.status_matricula IS NULL OR m.status_matricula IN ('activa','ativa'))";
    $ord_ch = function_exists('sige_turma_ordem_chamada_order_sql') ? sige_turma_ordem_chamada_order_sql('a') : "a.nome_completo ASC, a.id ASC";
    $alunos = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.nome_completo, a.genero
         FROM {$p}sige_matriculas m
         INNER JOIN {$p}sige_alunos a ON a.id = m.aluno_id AND a.escola_id = m.escola_id
         WHERE m.turma_id=%d AND m.ano_lectivo=%d AND m.escola_id=%d
           AND {$pop_ch}
         ORDER BY {$ord_ch}",
        $turma_id, $ano_lectivo, $eid));

    // Notas

    $notas_map = [];

    if (!empty($alunos)) {

        $ids = array_map(function($o){ return (int)$o->id; }, $alunos);

        $place = implode(',', array_fill(0, count($ids), '%d'));

        $params = array_merge($ids, [$turma_id, $ano_lectivo, $eid]);

        $rows = $wpdb->get_results($wpdb->prepare(

            "SELECT aluno_id, disciplina_id, trimestre, nota_ac, nota_acp, nota_exame, nota_conselho

             FROM {$p}sige_notas WHERE aluno_id IN ({$place}) AND turma_id=%d AND ano_lectivo=%d AND escola_id=%d AND (status IS NULL OR status = 'aprovado')",

            $params

        ));

        foreach ($rows as $r) {

            $notas_map[(int)$r->aluno_id][(int)$r->disciplina_id][(int)$r->trimestre] = $r;

        }

    }

    // Labels

    $nome_turma  = !empty($turma->nome_turma) ? $turma->nome_turma : (!empty($turma->nome) ? $turma->nome : '');

    $label_turma = $classe_num.'ª - '.$nome_turma.(!empty($turma->turno) ? ' • '.$turma->turno : '');

    $trims_labels = ['','Iº Trimestre','IIº Trimestre','IIIº Trimestre'];

    $titulo      = $trimestre > 0 ? 'PAUTA DE FREQUÊNCIA - '.$trims_labels[$trimestre] : 'PAUTA ANUAL DE FREQUÊNCIA';

    $periodo_label = $trimestre > 0 ? $trims_labels[$trimestre] : 'Anual';

    $escola      = get_bloginfo('name');

    // Output HTML

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);

    // ─── Helpers inline ───────────────────────────────────────

    $fn_mt = function($row) {

        if ($row && isset($row->nota_conselho) && $row->nota_conselho !== null && $row->nota_conselho !== '' && is_numeric($row->nota_conselho)) {
            return (int)round((float)$row->nota_conselho, 0);
        }

        // Alinhado ao Aproveitamento do Aluno: valores ausentes contam como 0.
        $ac  = ($row && is_numeric($row->nota_ac))    ? (float)$row->nota_ac    : 0.0;

        $acp = ($row && is_numeric($row->nota_acp))   ? (float)$row->nota_acp   : 0.0;

        $at  = ($row && is_numeric($row->nota_exame)) ? (float)$row->nota_exame : 0.0;

        $med = ($ac + $acp) / 2;

        return (int)round((2*$med + $at)/3, 0);

    };

    $fn_esc = function($n) {

        if ($n === null) return '';

        $n = (float)$n;

        if ($n < 10) return 'NS'; if ($n < 14) return 'S';

        if ($n < 17) return 'B';  if ($n < 19) return 'MB'; return 'E';

    };

    ob_start();

    include SIGE_PATH . 'admin/pauta-pdf-template.php';

    $html = ob_get_clean();

    echo $html;

    exit;

}
