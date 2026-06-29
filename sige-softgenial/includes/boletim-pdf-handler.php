<?php

/**

 * SIGE SoftGenial - Boletim Individual PDF Handler

 * Ficheiro: includes/boletim-pdf-handler.php

 *

 * Registar em sige-softgenial.php:

 *   add_action('admin_post_sige_boletim_pdf', 'sige_boletim_pdf_handler');

 *

 * Chamada via:

 *   admin-post.php?action=sige_boletim_pdf&aluno_id=X&ano=Y&_wpnonce=Z

 */

if ( ! defined('ABSPATH') ) exit;

function sige_boletim_pdf_handler() {

    // ── 1. Segurança ──────────────────────────────────────────────────────────

    if (function_exists('sige_require_user_can_any_secure')) {
        sige_require_user_can_any_secure(
            ['academico.boletins_emitir','documentos.emitir','documentos.emitir_finais'],
            ['sige_secretario','sige_secretaria_geral','sige_director','sige_pedagogico'],
            'Acesso negado.'
        );
    } elseif ( ! (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && ! current_user_can('sige_secretario') && ! current_user_can('sige_director') ) {

        wp_die('Acesso negado.', 403);

    }

    if ( ! isset($_GET['_wpnonce']) || ! wp_verify_nonce( sanitize_text_field($_GET['_wpnonce']), 'sige_boletim_pdf' ) ) {

        wp_die('Nonce inválido.', 403);

    }

    // ── 2. Parâmetros ─────────────────────────────────────────────────────────

    $aluno_id = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;

    $ano      = isset($_GET['ano'])      ? intval($_GET['ano'])      : 0;

    if ( ! $aluno_id || ! $ano ) {

        wp_die('Parâmetros em falta: aluno_id e ano são obrigatórios.');

    }

    global $wpdb;

    $p = $wpdb->prefix; // wpq1_
    $eid = sige_get_escola_id();

    // ── 3. Dados do aluno ─────────────────────────────────────────────────────

    $aluno = $wpdb->get_row( $wpdb->prepare(

        "SELECT * FROM {$p}sige_alunos WHERE id = %d AND escola_id = %d LIMIT 1",

        $aluno_id, $eid

    ));

    if ( ! $aluno ) {

        wp_die('Aluno não encontrado (id=' . esc_html($aluno_id) . ').');

    }

    // ── 4. Matrícula e turma para o ano pedido ────────────────────────────────

    $matricula = $wpdb->get_row( $wpdb->prepare(

        "SELECT m.*, t.classe, t.nivel_ensino,

                COALESCE(t.nome_turma, t.nome) AS nome_turma,

                t.turno

         FROM   {$p}sige_matriculas m

         JOIN   {$p}sige_turmas t ON t.id = m.turma_id

         WHERE  m.aluno_id   = %d

           AND  m.ano_lectivo = %d

           AND  m.escola_id = %d

         ORDER  BY m.id DESC

         LIMIT  1",

        $aluno_id, $ano, $eid

    ));

    if ( ! $matricula ) {

        wp_die("Sem matrícula para o aluno no ano $ano.");

    }

    $turma_id = intval($matricula->turma_id);

    $classe   = $matricula->classe;

    // ── 5. Disciplinas (fix UNION obrigatório) ────────────────────────────────

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

         FROM   {$p}sige_disciplinas d

         LEFT JOIN {$p}sige_matriz_curricular mc

                ON mc.disciplina_id = d.id AND mc.classe = %s

         WHERE  d.id IN (

             SELECT disciplina_id FROM {$p}sige_turma_disciplinas WHERE turma_id = %d AND escola_id = %d

             UNION

             SELECT disciplina_id FROM {$p}sige_matriz_curricular WHERE classe = %s AND escola_id = %d

         )

         ORDER BY ordem, d.nome",

        $classe, $turma_id, $eid, $classe, $eid

    ));

    // [MALISA-V2 sort guard] Ordem/categoria oficiais Malisa para 1.ª-6.ª.
    if (function_exists('sige_apply_categoria_oficial_disciplinas')) { $disciplinas = sige_apply_categoria_oficial_disciplinas($disciplinas, $classe); }
    if (function_exists('sige_sort_disciplinas_oficial')) { $disciplinas = sige_sort_disciplinas_oficial($disciplinas); }

    // ── 6. Notas por disciplina e trimestre ───────────────────────────────────

    $notas_raw = $wpdb->get_results( $wpdb->prepare(

        "SELECT disciplina_id, trimestre,

                nota_ac, nota_acp, nota_exame

         FROM   {$p}sige_notas

         WHERE  aluno_id    = %d

           AND  turma_id    = %d

           AND  escola_id   = %d

           AND  ano_lectivo = %d

           AND  (status IS NULL OR status = 'aprovado')",

        $aluno_id, $turma_id, $eid, $ano

    ));

    // Indexar: $notas[$disc_id][$trimestre] = objeto

    $notas = [];

    foreach ( $notas_raw as $n ) {

        $notas[ $n->disciplina_id ][ $n->trimestre ] = $n;

    }

    // ── 7. Regras académicas para cálculo de NF ───────────────────────────────

    $classe_num = intval( filter_var($classe, FILTER_SANITIZE_NUMBER_INT) );

    $regra      = null;

    if ( function_exists('sige_get_regra_academica') ) {

        $regra = sige_get_regra_academica($ano, $classe_num);

    }

    $peso_mfd   = ( $regra && isset($regra->peso_mfd)   ) ? floatval($regra->peso_mfd)   : 60;

    $peso_exame = ( $regra && isset($regra->peso_exame) ) ? floatval($regra->peso_exame) : 40;

    $fim_ciclo  = ( function_exists('sige_is_fim_ciclo_by_turma') ) ? sige_is_fim_ciclo_by_turma($turma_id) : false;

    // ── 8. Calcular notas por disciplina ──────────────────────────────────────

    // [MALISA-V4] Funções locais de cálculo alinhadas ao Aproveitamento em tela.
    // Campos sem nota contam como 0 para manter coerência com a regra já validada no boletim.
    $calc_med_acs = function($ac, $acp) {
        $a = ($ac === null || $ac === '') ? 0.0 : (float)$ac;
        $b = ($acp === null || $acp === '') ? 0.0 : (float)$acp;
        return round(($a + $b) / 2, 1);
    };
    $calc_mt = function($ac, $acp, $at) use ($calc_med_acs) {
        $c = ($at === null || $at === '') ? 0.0 : (float)$at;
        $med = $calc_med_acs($ac, $acp);
        return (int) round((2 * $med + $c) / 3, 0);
    };
    $calc_mfd = function($mt1, $mt2, $mt3) {
        return (int) round((((float)$mt1) + ((float)$mt2) + ((float)$mt3)) / 3, 0);
    };
    $calc_nf = function($mfd, $exame, $peso_mfd, $peso_exame) {
        $m = ($mfd === null || $mfd === '') ? 0.0 : (float)$mfd;
        $e = ($exame === null || $exame === '') ? 0.0 : (float)$exame;
        $pm = max(0, (float)$peso_mfd);
        $pe = max(0, (float)$peso_exame);
        $den = ($pm + $pe) > 0 ? ($pm + $pe) : 100;
        return (int) round(($m * $pm + $e * $pe) / $den, 0);
    };

    $linhas       = [];

    $total_nf     = 0;

    $count_nf     = 0;

    $total_neg    = 0;

    foreach ( $disciplinas as $disc ) {

        $linha = [

            'nome'      => $disc->nome,

            'sigla'     => $disc->sigla,

            'categoria' => $disc->categoria,

            'trimestres'=> [],

            'mfd'       => null,

            'nf'        => null,

            'escala'    => '',

        ];

        $mts     = [];

        $exame_t3 = null;

        for ( $t = 1; $t <= 3; $t++ ) {

            $n = isset($notas[$disc->id][$t]) ? $notas[$disc->id][$t] : null;

            $ac1      = ( $n && $n->nota_ac    !== null && $n->nota_ac    !== '' ) ? floatval($n->nota_ac)    : null;

            $ac2      = ( $n && $n->nota_acp   !== null && $n->nota_acp   !== '' ) ? floatval($n->nota_acp)   : null;

            $exame    = ( $n && $n->nota_exame !== null && $n->nota_exame !== '' ) ? floatval($n->nota_exame) : null;

            $med_acs  = $calc_med_acs($ac1, $ac2);

            $mt       = $calc_mt($ac1, $ac2, $exame);

            if ( $t === 3 ) $exame_t3 = $exame;

            $mts[$t] = $mt;

            $linha['trimestres'][$t] = [

                'ac1'   => $ac1,

                'ac2'   => $ac2,

                'exame' => $exame,

                'mt'    => $mt,

            ];

        }

        // MFD - [MALISA-V4] regra null=0 alinhada ao Aproveitamento em tela

        $mfd = $calc_mfd($mts[1] ?? 0, $mts[2] ?? 0, $mts[3] ?? 0);

        $linha['mfd'] = $mfd;

        // NF

        if ( $fim_ciclo ) {

            $nf = $calc_nf($mfd, $exame_t3, $peso_mfd, $peso_exame);

        } else {

            $nf = $mfd;

        }

        $linha['nf']     = $nf;

        $linha['escala'] = sige_boletim_pdf_escala($nf);

        // [MALISA-V4] Média Global e negativas contam apenas disciplinas nucleares.
        $is_nuclear_pdf = (strtolower((string)($linha['categoria'] ?? '')) === 'nuclear');
        if ( $is_nuclear_pdf ) {

            $total_nf += $nf;

            $count_nf++;

            if ( $nf < 10 ) $total_neg++;

        }

        $linhas[] = $linha;

    }

    // [MALISA-V4] Média Global do PDF calculada apenas com disciplinas nucleares.
    $media_global = ( $count_nf > 0 ) ? round($total_nf / $count_nf, 1) : null;

    // ── 9. Situação final ─────────────────────────────────────────────────────

    $situacao_data = null;

    if ( function_exists('sige_calcular_situacao_final') ) {

        $pauta_final = function_exists('sige_is_pauta_final_mode')

            ? sige_is_pauta_final_mode($ano, $turma_id)

            : false;

        $situacao_data = sige_calcular_situacao_final($aluno_id, $turma_id, $ano, $pauta_final);

    }

    $situacao = ( $situacao_data && isset($situacao_data['situacao']) )

                ? $situacao_data['situacao']

                : 'PENDENTE';

    // ── 10. Renderizar template ───────────────────────────────────────────────

    $data = [

        'aluno'        => $aluno,

        'matricula'    => $matricula,

        'ano'          => $ano,

        'classe'       => $classe,

        'linhas'       => $linhas,

        'media_global' => $media_global,

        'total_neg'    => $total_neg,

        'situacao'     => $situacao,

        'peso_mfd'     => $peso_mfd,

        'peso_exame'   => $peso_exame,

        'fim_ciclo'    => $fim_ciclo,

    ];

    // Header para forçar download como HTML limpo (o browser imprime/guarda como PDF)

    // Para gerar PDF real no servidor, substituir por mPDF/TCPDF/Dompdf aqui

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Security-Policy: ' . (function_exists('sige_csp_zero_inline_policy') ? sige_csp_zero_inline_policy() : "default-src 'self'; object-src 'none';"), true);

    // Sem Content-Disposition para que o browser abra normalmente e permita "Imprimir > Guardar como PDF"

    include SIGE_PATH . 'admin/boletim-pdf-template.php';

    exit;

}

// ── Helper local de escala (caso academic-logic.php não esteja carregado) ────

if ( ! function_exists('sige_boletim_pdf_escala') ) {

    function sige_boletim_pdf_escala( $nota ) {

        if ( $nota === null ) return '';

        if ( $nota >= 19 ) return 'E';

        if ( $nota >= 17 ) return 'MB';

        if ( $nota >= 14 ) return 'B';

        if ( $nota >= 10 ) return 'S';

        return 'NS';

    }

}
