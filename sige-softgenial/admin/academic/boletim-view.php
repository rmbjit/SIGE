<?php
/**
 * SIGE SoftGenial - Aproveitamento do Aluno
 *
 * v3.0 - Abril 2026
 * - Vistas separadas: 1º Trimestre / 2º Trimestre / 3º Trimestre / Anual
 * - Situação (PROGRIDE/TRANSITA/NÃO PROGRIDE/NÃO TRANSITA) APENAS na vista Anual
 * - Detalhes completos: todas as avaliações (AC, ACP, AT/Exame) por disciplina
 * - Média trimestral, média por disciplina e média final visíveis
 * - Impressão popup (consistente com DEC/Pauta Final)
 * - Fórmulas consistentes com academic-logic.php:
 *     MT = ROUND((2 × (AC+ACP)/2 + AT) / 3, 0)
 *     MFD = média dos MT (T1, T2, T3)
 *     NF = (MFD × peso_mfd + Exame × peso_exame) / (peso_mfd + peso_exame)
 *
 * Segurança: ABSPATH, capability, escola_id, prepare(), esc_*()
 *
 * @since 12.1
 */

if (!defined('ABSPATH')) exit;

// [T7] Dir. Pedagógico pode consultar aproveitamento dos alunos
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['academico.boletins_ver','academico.boletins_emitir'],
    ['sige_professor','sige_director','sige_pedagogico','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;

$_escola_perfil = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$_escola_nome = $_escola_perfil->nome_escola ?? get_bloginfo('name');
$_escola_nome_upper = mb_strtoupper($_escola_nome);

global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

/* ─── TABELAS ──────────────────────────────────────────────────── */
$tTurmas      = $wpdb->prefix . 'sige_turmas';
$tAlunos      = $wpdb->prefix . 'sige_alunos';
$tMatriculas  = $wpdb->prefix . 'sige_matriculas';
$tNotas       = $wpdb->prefix . 'sige_notas';
$tDisciplinas = $wpdb->prefix . 'sige_disciplinas';
$tTurmaDisc   = $wpdb->prefix . 'sige_turma_disciplinas';
$tMatriz      = $wpdb->prefix . 'sige_matriz_curricular';

/* ─── HELPERS ──────────────────────────────────────────────────── */
if (!function_exists('sige_bol_ano_atual')) {
    function sige_bol_ano_atual() {
        if (function_exists('sige_get_ano_lectivo_atual'))     return sige_get_ano_lectivo_atual();
        if (function_exists('sige_fin_get_ano_letivo_master')) return sige_fin_get_ano_letivo_master();
        return (string) wp_date('Y');
    }
}

if (!function_exists('sige_bol_escala')) {
    function sige_bol_escala($nota) {
        if ($nota === null || $nota === '') return '-';
        $n = (int) $nota;
        if ($n < 10)  return 'NS';
        if ($n <= 13) return 'S';
        if ($n <= 16) return 'B';
        if ($n <= 18) return 'MB';
        return 'E';
    }
}

if (!function_exists('sige_bol_escala_label')) {
    /**
     * [MALISA-V5] Escala qualitativa oficial do Colégio Malisa.
     * 19-20 Excelente · 17-18 Muito Bom · 14-16 Bom · 10-13 Satisfatório · 0-9 Não Satisfatório.
     */
    function sige_bol_escala_label($nota) {
        if ($nota === null || $nota === '') return '-';
        $n = (int) $nota;
        if ($n < 10)  return 'Não Satisfatório';
        if ($n <= 13) return 'Satisfatório';
        if ($n <= 16) return 'Bom';
        if ($n <= 18) return 'Muito Bom';
        return 'Excelente';
    }
}

if (!function_exists('sige_bol_cor')) {
    function sige_bol_cor($nota) {
        if ($nota === null || $nota === '') return '#999';
        $n = (int) $nota;
        if ($n < 10)  return '#dc3545';
        if ($n <= 13) return '#fd7e14';
        if ($n <= 16) return '#007bff';
        if ($n <= 18) return '#28a745';
        return '#6f42c1';
    }
}

if (!function_exists('sige_bol_fmt')) {
    function sige_bol_fmt($v) {
        // Regra null=0 em vigor - espaços vazios contam como 0
        if ($v === null || $v === '') return '0';
        $f = (float) $v;
        return ($f == (int) $f) ? (int) $f : number_format($f, 1, ',', '');
    }
}

/** Média de AC+ACP com regra null=0 (sempre calculada) */
if (!function_exists('sige_ap_med_acs')) {
    function sige_ap_med_acs($ac, $acp) {
        $a = ($ac === null || $ac === '') ? 0.0 : (float) $ac;
        $b = ($acp === null || $acp === '') ? 0.0 : (float) $acp;
        return round(($a + $b) / 2, 1);
    }
}

/** MT com regra null=0: MT = ROUND((2 × Méd + AT) / 3, 0) - sempre calculada */
if (!function_exists('sige_ap_mt_zerofill')) {
    function sige_ap_mt_zerofill($ac, $acp, $at) {
        $a = ($ac  === null || $ac  === '') ? 0.0 : (float) $ac;
        $b = ($acp === null || $acp === '') ? 0.0 : (float) $acp;
        $c = ($at  === null || $at  === '') ? 0.0 : (float) $at;
        $med = ($a + $b) / 2;
        return (int) round((2 * $med + $c) / 3, 0);
    }
}

/** MFD com regra null=0: MFD = (MT1 + MT2 + MT3) / 3 - sempre calculada */
if (!function_exists('sige_ap_mfd_zerofill')) {
    function sige_ap_mfd_zerofill($mt1, $mt2, $mt3) {
        $a = ($mt1 === null || $mt1 === '') ? 0.0 : (float) $mt1;
        $b = ($mt2 === null || $mt2 === '') ? 0.0 : (float) $mt2;
        $c = ($mt3 === null || $mt3 === '') ? 0.0 : (float) $mt3;
        return (int) round(($a + $b + $c) / 3, 0);
    }
}

/** NF com regra null=0: NF = (MFD × peso_mfd + Exame × peso_exame) / (peso_mfd + peso_exame) */
if (!function_exists('sige_ap_nf_zerofill')) {
    function sige_ap_nf_zerofill($mfd, $exame, $peso_mfd, $peso_exame) {
        $m = ($mfd   === null || $mfd   === '') ? 0.0 : (float) $mfd;
        $e = ($exame === null || $exame === '') ? 0.0 : (float) $exame;
        $pm = max(0, (int) $peso_mfd);
        $pe = max(0, (int) $peso_exame);
        $den = $pm + $pe;
        if ($den <= 0) $den = 100;
        return (int) round(($m * $pm + $e * $pe) / $den, 0);
    }
}

/* ─── PARÂMETROS ───────────────────────────────────────────────── */
$ano_sel        = isset($_GET['ano_bol'])        ? sanitize_text_field($_GET['ano_bol'])        : sige_bol_ano_atual();
$aluno_id       = isset($_GET['aluno_id'])       ? intval($_GET['aluno_id'])                    : 0;
$turma_id       = isset($_GET['turma_id'])       ? intval($_GET['turma_id'])                    : 0;
$turma_busca_id = isset($_GET['turma_busca_id']) ? intval($_GET['turma_busca_id'])              : 0;
$search_q       = isset($_GET['search_q'])       ? sanitize_text_field($_GET['search_q'])       : '';
$modo           = isset($_GET['modo'])           ? sanitize_text_field($_GET['modo'])           : 'normal';
$vista_ap       = isset($_GET['vista_ap'])       ? sanitize_text_field($_GET['vista_ap'])       : 'anual';
// Validar vista
if (!in_array($vista_ap, ['t1', 't2', 't3', 'anual'], true)) $vista_ap = 'anual';

// [12.11.9.15] Escopo docente: Aproveitamento só pesquisa/abre alunos das turmas atribuídas ao professor actual.
$sige_bol_scoped_professor = function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user();
$sige_bol_professor_id = ($sige_bol_scoped_professor && function_exists('sige_get_professor_atual_id')) ? (int) sige_get_professor_atual_id() : 0;
$sige_bol_turma_ids = [];
$sige_bol_scope_msg = '';
if ($sige_bol_scoped_professor) {
    if ($sige_bol_professor_id > 0 && function_exists('sige_professor_turma_ids')) {
        $sige_bol_turma_ids = array_values(array_unique(array_filter(array_map('intval', (array) sige_professor_turma_ids($sige_bol_professor_id, $escola_id)))));
        if (empty($sige_bol_turma_ids)) {
            $sige_bol_scope_msg = 'O seu perfil de professor ainda não tem turmas atribuídas para consultar o Aproveitamento.';
        }
    } else {
        $sige_bol_scope_msg = 'O seu utilizador ainda não está vinculado a um registo de professor no SIGE.';
    }
}
if ($sige_bol_scoped_professor && $turma_busca_id > 0 && !in_array($turma_busca_id, $sige_bol_turma_ids, true)) {
    $turma_busca_id = 0;
    $sige_bol_scope_msg = 'A turma solicitada não está atribuída ao seu perfil de professor.';
}
if ($sige_bol_scoped_professor && $turma_id > 0 && !in_array($turma_id, $sige_bol_turma_ids, true)) {
    $turma_id = 0;
    $aluno_id = 0;
    $sige_bol_scope_msg = 'O aluno/turma solicitados não pertencem ao seu escopo docente.';
}
$sige_bol_scope_sql = '';
$sige_bol_scope_params = [];
if ($sige_bol_scoped_professor) {
    if (!empty($sige_bol_turma_ids)) {
        $sige_bol_scope_sql = ' AND id IN (' . implode(',', array_fill(0, count($sige_bol_turma_ids), '%d')) . ') ';
        $sige_bol_scope_params = $sige_bol_turma_ids;
    } else {
        $sige_bol_scope_sql = ' AND 1=0 ';
    }
}

$base_url  = admin_url('admin.php?page=sige-app&view=boletim');
$anos_opts = range(2020, (int) wp_date('Y') + 1);

$vistas_label = [
    't1'    => '1º Trimestre',
    't2'    => '2º Trimestre',
    't3'    => '3º Trimestre',
    'anual' => 'Anual (Consolidado)',
];

/* ─── PESQUISA ─────────────────────────────────────────────────── */
$resultados = [];
$turmas_pesquisa = [];
$turma_filtro = null;
$turma_alunos = [];

$turma_tem_nome_turma = function_exists('sige_db_column_exists') && sige_db_column_exists($tTurmas, 'nome_turma');
$turma_nome_expr = $turma_tem_nome_turma ? "COALESCE(NULLIF(nome_turma,''), nome)" : "nome";
$turma_nome_join_expr = $turma_tem_nome_turma ? "COALESCE(NULLIF(t.nome_turma,''), t.nome)" : "t.nome";

$turmas_pesquisa_params = array_merge([$escola_id, $ano_sel], $sige_bol_scope_params);
$turmas_pesquisa = $wpdb->get_results($wpdb->prepare(
    "SELECT id, classe, {$turma_nome_expr} AS nome_turma
     FROM {$tTurmas}
     WHERE escola_id = %d AND ano_lectivo = %s {$sige_bol_scope_sql}
     ORDER BY classe ASC, nome ASC",
    $turmas_pesquisa_params
));

if ($turma_busca_id > 0) {
    $turma_filtro = $wpdb->get_row($wpdb->prepare(
        "SELECT id, classe, {$turma_nome_expr} AS nome_turma
         FROM {$tTurmas}
         WHERE id = %d AND escola_id = %d AND ano_lectivo = %s
         LIMIT 1",
        $turma_busca_id, $escola_id, $ano_sel
    ));

    if (!$turma_filtro) {
        $turma_busca_id = 0;
    }
}

if ($search_q && strlen($search_q) >= 2) {
    $like = '%' . $wpdb->esc_like($search_q) . '%';
    $where_turma_pesquisa = '';
    $params_pesquisa = [$ano_sel, $escola_id, $like, $like];
    if ($sige_bol_scoped_professor) {
        if (!empty($sige_bol_turma_ids)) {
            $where_turma_pesquisa .= ' AND m.turma_id IN (' . implode(',', array_fill(0, count($sige_bol_turma_ids), '%d')) . ') ';
            $params_pesquisa = array_merge($params_pesquisa, $sige_bol_turma_ids);
        } else {
            $where_turma_pesquisa .= ' AND 1=0 ';
        }
    }
    if ($turma_busca_id > 0) {
        $where_turma_pesquisa .= ' AND m.turma_id = %d ';
        $params_pesquisa[] = $turma_busca_id;
    }

    $resultados = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.nome_completo, a.numero_processo, a.genero, a.classe_atual,
                m.turma_id, m.ano_lectivo, m.status_matricula,
                {$turma_nome_join_expr} AS nome_turma, t.classe
         FROM {$tAlunos} a
         LEFT JOIN {$tMatriculas} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
             AND m.ano_lectivo = %s AND m.status_matricula != 'cancelada'
         LEFT JOIN {$tTurmas} t ON t.id = m.turma_id AND t.escola_id = a.escola_id
         WHERE a.escola_id = %d AND (a.nome_completo LIKE %s OR a.numero_processo LIKE %s)
         {$where_turma_pesquisa}
         ORDER BY a.nome_completo
         LIMIT 30",
        $params_pesquisa
    ));
}

if ($turma_busca_id > 0 && $turma_filtro && $aluno_id <= 0) {
    $activo_sql = function_exists('sige_aluno_matricula_activa_sql')
        ? sige_aluno_matricula_activa_sql('a', 'm')
        : "(m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo'))";

    $turma_alunos = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.nome_completo, a.numero_processo, a.genero,
                m.turma_id, m.status_matricula
         FROM {$tMatriculas} m
         JOIN {$tAlunos} a ON a.id = m.aluno_id AND a.escola_id = m.escola_id
         WHERE m.turma_id = %d
           AND m.ano_lectivo = %s
           AND m.escola_id = %d
           AND {$activo_sql}
         ORDER BY a.nome_completo ASC",
        $turma_busca_id, $ano_sel, $escola_id
    ));
}

/* ─── CARREGAR ALUNO E TURMA ───────────────────────────────────── */
$aluno = null;
$turma = null;
if ($aluno_id > 0) {
    $aluno = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tAlunos} WHERE id=%d AND escola_id=%d", $aluno_id, $escola_id));
    if ($aluno && $turma_id === 0) {
        $mat = $wpdb->get_row($wpdb->prepare(
            "SELECT turma_id FROM {$tMatriculas}
             WHERE aluno_id=%d AND escola_id=%d AND ano_lectivo=%s AND status_matricula != 'cancelada'
             ORDER BY id DESC LIMIT 1",
            $aluno_id, $escola_id, $ano_sel
        ));
        if ($mat) $turma_id = (int) $mat->turma_id;
    }
    if ($turma_id > 0) {
        $turma = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tTurmas} WHERE id=%d AND escola_id=%d", $turma_id, $escola_id));
    }
    if ($aluno && $turma_id > 0 && $sige_bol_scoped_professor) {
        $can_turma = function_exists('sige_professor_can_access_turma') && sige_professor_can_access_turma((int)$turma_id, $sige_bol_professor_id, $escola_id);
        if (!$can_turma) {
            $aluno = null;
            $turma = null;
            $turma_id = 0;
            $aluno_id = 0;
            $sige_bol_scope_msg = 'Este aproveitamento pertence a uma turma que não está atribuída ao seu perfil de professor.';
        }
    }
}

/* ─── CALCULAR DADOS ───────────────────────────────────────────── */
$boletim   = [];   // Por disciplina: [T1,T2,T3 completo], MFD, NF
$sit_final = null;
$pf_mode   = false;
$media_trimestre = [1 => null, 2 => null, 3 => null]; // média geral do trimestre (apenas disciplinas nucleares)
$media_final     = null; // média geral anual (MFD apenas das disciplinas nucleares)
$peso_mfd = 60; $peso_ex = 40; $eh_fim_ciclo = false; $exige_exame = false;

if ($aluno && $turma_id > 0) {
    $pf_mode = (bool) (function_exists("sige_is_pauta_final_mode") ? sige_is_pauta_final_mode($ano_sel, $turma_id) : get_option("sige_pauta_final_mode_{$escola_id}_{$ano_sel}_{$turma_id}", 0));

    /* Disciplinas da turma */
    $classe_turma = $turma->classe ?? '';
    $discs = $wpdb->get_results($wpdb->prepare(
        "SELECT d.id, d.nome, d.sigla, d.categoria,
                COALESCE(mc.ordem_pauta,
                    CASE d.sigla
                        WHEN 'POR' THEN 1 WHEN 'MAT' THEN 2 WHEN 'CS' THEN 3
                        WHEN 'CN'  THEN 4 WHEN 'ING' THEN 5
                        WHEN 'ED.VISUAL' THEN 6 WHEN 'ED. V' THEN 6 WHEN 'OF' THEN 7
                        WHEN 'ED. FISICA' THEN 8 WHEN 'ED.FISICA' THEN 8
                        ELSE 99
                    END) AS ordem
         FROM {$tDisciplinas} d
         LEFT JOIN {$tMatriz} mc ON mc.disciplina_id = d.id AND mc.classe = %s AND mc.escola_id = %d
         WHERE d.escola_id = %d AND d.id IN (
             SELECT disciplina_id FROM {$tTurmaDisc} WHERE turma_id = %d AND escola_id = %d
             UNION
             SELECT disciplina_id FROM {$tMatriz} WHERE classe = %s AND escola_id = %d
         )
         ORDER BY ordem, d.nome",
        $classe_turma, $escola_id, $escola_id, $turma_id, $escola_id, $classe_turma, $escola_id
    ));

    if (empty($discs)) {
        $discs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome, sigla, categoria, ordem FROM {$tDisciplinas} WHERE escola_id=%d AND activo=1 ORDER BY ordem, nome",
            $escola_id
        ));
    }
    // [T5] Categoria vem da matriz_curricular (varia por classe), não de sige_disciplinas
    if (!empty($classe_turma) && function_exists('sige_get_categoria_by_matriz')) { foreach ($discs as &$_d) { if (isset($_d->id)) $_d->categoria = sige_get_categoria_by_matriz($classe_turma, $_d->id, $escola_id); } unset($_d); }
    // [MALISA-V2 sort guard] Ordem/categoria oficiais Malisa para 1.ª-6.ª.
    if (function_exists('sige_apply_categoria_oficial_disciplinas')) { $discs = sige_apply_categoria_oficial_disciplinas($discs, $classe_turma); }
    if (function_exists('sige_sort_disciplinas_oficial')) { $discs = sige_sort_disciplinas_oficial($discs); }

    /* Todas as notas do aluno nesta turma/ano */
    // [T6] Apenas notas aprovadas (ou legacy sem status) - pendentes não aparecem no boletim
    $notas_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT disciplina_id, trimestre, nota_ac, nota_acp, nota_exame, nota_at, nota_conselho
         FROM {$tNotas}
         WHERE aluno_id=%d AND turma_id=%d AND ano_lectivo=%s AND escola_id=%d
           AND (status IS NULL OR status = 'aprovado')",
        $aluno_id, $turma_id, $ano_sel, $escola_id
    ));
    $ni = [];
    foreach ($notas_raw as $n) $ni[(int) $n->disciplina_id][(int) $n->trimestre] = $n;

    /* Regras académicas para pesos e fim de ciclo */
    $classe_num = function_exists('sige_parse_classe_num') ? sige_parse_classe_num($turma->classe ?? '1') : (int) filter_var($turma->classe ?? '1', FILTER_SANITIZE_NUMBER_INT);
    $regra = function_exists('sige_get_regra_academica') ? sige_get_regra_academica($ano_sel, $classe_num) : null;
    $peso_mfd     = $regra ? (int) $regra->peso_mfd   : 60;
    $peso_ex      = $regra ? (int) $regra->peso_exame : 40;
    $eh_fim_ciclo = $regra ? ((int) $regra->eh_fim_ciclo === 1) : in_array($classe_num, [3, 6, 9, 12], true);
    $exige_exame  = $regra ? ((int) $regra->exige_exame === 1) : false;
    $label_avaliacao_final = function_exists('sige_label_avaliacao_final') ? sige_label_avaliacao_final($classe_num) : ($classe_num === 3 ? 'AF' : 'Exame');
    $label_nota_final      = function_exists('sige_label_nota_final')      ? sige_label_nota_final($classe_num)      : ($classe_num === 3 ? 'MF' : 'NF');

    /* Acumuladores para médias gerais por trimestre
       [MALISA-V4] A média do aproveitamento deve contar APENAS disciplinas nucleares.
       As disciplinas auxiliares continuam visíveis, mas não entram em MT média, MG nem situação. */
    $mt_acum = [1 => [], 2 => [], 3 => []];
    $mfd_acum = [];
    $mg_acum  = []; // Para média global (situação) com regra null=0

    foreach ($discs as $d) {
        $did = (int) $d->id;
        $row = [
            'id'        => $did,
            'nome'      => $d->nome,
            'sigla'     => $d->sigla,
            'categoria' => $d->categoria,
            'ordem'     => $d->ordem,
        ];
        $is_nuclear_boletim = (strtolower((string)($row['categoria'] ?? '')) === 'nuclear');

        // Calcular para cada trimestre - regra null=0, MT sempre calculado
        for ($t = 1; $t <= 3; $t++) {
            $n = $ni[$did][$t] ?? null;
            // Valores brutos (preservam null apenas para display diferenciado)
            $ac  = ($n && $n->nota_ac    !== null) ? (float) $n->nota_ac    : null;
            $acp = ($n && $n->nota_acp   !== null) ? (float) $n->nota_acp   : null;
            $at  = ($n && $n->nota_exame !== null) ? (float) $n->nota_exame : null;
            // Computados (regra null=0 - sempre definidos)
            $med = sige_ap_med_acs($ac, $acp);              // (AC + ACP) / 2, nulls = 0
            $mt  = ($n && isset($n->nota_conselho) && $n->nota_conselho !== null && $n->nota_conselho !== '' && is_numeric($n->nota_conselho))
                 ? (int) round((float)$n->nota_conselho, 0)
                 : sige_ap_mt_zerofill($ac, $acp, $at);     // ROUND((2×Méd + AT)/3, 0)
            $row["T{$t}"] = compact('ac', 'acp', 'at', 'med', 'mt');
            if ($is_nuclear_boletim) {
                $mt_acum[$t][] = $mt; // [MALISA-V4] só nucleares entram na média trimestral
            }
        }

        // MFD = (MT1 + MT2 + MT3) / 3 - regra null=0, sempre calculado
        $mt1 = $row['T1']['mt'];
        $mt2 = $row['T2']['mt'];
        $mt3 = $row['T3']['mt'];
        $mfd = sige_ap_mfd_zerofill($mt1, $mt2, $mt3);
        $row['MFD'] = $mfd;
        if ($is_nuclear_boletim) {
            $mfd_acum[] = $mfd; // [MALISA-V4] só nucleares entram na média anual/global
        }

        // Avaliação final do T3: AF apenas na 3.ª classe; restantes classes usam Exame.
        // A avaliação final fica em nota_at; nota_exame continua a representar AT na grelha de notas.
        $ex3 = $ni[$did][3]->nota_at ?? null;
        $mostra_exame_aqui = $pf_mode && $eh_fim_ciclo && $exige_exame && $is_nuclear_boletim;
        $row['EX_FINAL'] = $mostra_exame_aqui ? $ex3 : null;

        // NF/MF = ponderação (apenas em modo pauta final + fim ciclo + avaliação final configurada)
        // Regra null=0: se avaliação final não registada, trata como 0 e calcula sempre.
        if ($mostra_exame_aqui) {
            $row['NF'] = sige_ap_nf_zerofill($mfd, $ex3, $peso_mfd, $peso_ex);
        } else {
            $row['NF'] = null;
        }

        // Nota usada para situação final
        $nota_final_disc = $mostra_exame_aqui ? $row['NF'] : $mfd;
        $mg_acum[] = ['nota' => $nota_final_disc, 'disc_id' => $did];

        $boletim[$did] = $row;
    }

    // Médias gerais por trimestre - [MALISA-V4] calculadas apenas com disciplinas nucleares
    for ($t = 1; $t <= 3; $t++) {
        $media_trimestre[$t] = !empty($mt_acum[$t]) ? round(array_sum($mt_acum[$t]) / count($mt_acum[$t]), 1) : null;
    }
    $media_final = !empty($mfd_acum) ? round(array_sum($mfd_acum) / count($mfd_acum), 1) : null;

    // Situação final - computada localmente com regra null=0 (consistente com MFD/NF mostrados)
    // APENAS usada na vista anual
    $nota_min_aprov    = $regra ? (int) $regra->nota_minima_aprovacao : 10;
    $media_min_global  = $regra ? (int) $regra->media_minima_global   : 10;
    $max_progride      = $regra ? (int) $regra->max_negativas_progride : 0;
    $max_transita      = $regra ? (int) $regra->max_negativas_transita : 2;
    $usa_media_global  = $regra ? ((int) $regra->usa_media_global === 1) : true;

    // [T5] Negativas e Média Global contam APENAS disciplinas nucleares (regra SNE)
    $negativas = 0;
    $notas_finais = [];
    foreach ($mg_acum as $item) {
        $cat = $boletim[$item['disc_id']]['categoria'] ?? 'complementar';
        if (strtolower($cat) === 'nuclear') {
            $notas_finais[] = (int) $item['nota'];
            if ((int) $item['nota'] < $nota_min_aprov) $negativas++;
        }
    }
    $media_global_zf = count($notas_finais) > 0 ? (int) round(array_sum($notas_finais) / count($notas_finais), 0) : 0;

    // Aplicar regras de situação (null=0 em vigor)
    $sit_txt = 'PROGRIDE';
    if ($usa_media_global && $media_global_zf < $media_min_global) {
        $sit_txt = 'REPROVA';
    } else {
        if ($negativas <= $max_progride)       $sit_txt = 'PROGRIDE';
        elseif ($negativas <= $max_transita)   $sit_txt = 'TRANSITA';
        else                                    $sit_txt = 'REPROVA';
    }
    // Norma SNE: fim de ciclo usa TRANSITA quando aprovado; classes intermédias usam PROGRIDE
    if ($sit_txt !== 'REPROVA') {
        $sit_txt = $eh_fim_ciclo ? 'TRANSITA' : 'PROGRIDE';
    }
    $sit_final = [
        'situacao'     => $sit_txt,
        'negativas'    => $negativas,
        'media_global' => $media_global_zf,
    ];
}

/* ─── HISTÓRICO ────────────────────────────────────────────────── */
$historico = [];
if ($aluno && $modo === 'historico') {
    $historico = $wpdb->get_results($wpdb->prepare(
        "SELECT m.ano_lectivo, m.turma_id, m.status_matricula,
                {$turma_nome_join_expr} AS nome_turma, t.classe
         FROM {$tMatriculas} m
         LEFT JOIN {$tTurmas} t ON t.id = m.turma_id AND t.escola_id = m.escola_id
         WHERE m.aluno_id = %d AND m.escola_id = %d AND m.status_matricula != 'cancelada'
         ORDER BY m.ano_lectivo ASC",
        $aluno_id, $escola_id
    ));
}

/* ══════════════════════════════════════════════════════════════════
   UI
   ══════════════════════════════════════════════════════════════════ */
$ap_doc_palette = function_exists('sige_theme_document_palette') ? sige_theme_document_palette($_escola_perfil) : ['primary' => '#5a3fd6', 'primary_700' => '#4c34bd'];
$ap_doc_primary = isset($ap_doc_palette['primary']) && preg_match('/^#[0-9a-f]{6}$/i', (string) $ap_doc_palette['primary']) ? strtolower((string) $ap_doc_palette['primary']) : '#5a3fd6';
$ap_doc_primary_700 = isset($ap_doc_palette['primary_700']) && preg_match('/^#[0-9a-f]{6}$/i', (string) $ap_doc_palette['primary_700']) ? strtolower((string) $ap_doc_palette['primary_700']) : '#4c34bd';
?>
<style>
/* =============================================================================
   SoftGenial - Aproveitamento / Boletim no padrão visual aprovado
   Camada visual apenas. Não altera fórmulas, regras académicas ou dados.
   ============================================================================= */
body.sige-view-boletim .sg-product-page-head{display:none!important;}
body.sige-view-boletim .sg-app-page{padding-top:0;}
.sige-ap-page{
    --ap-primary:var(--sg-theme-primary,var(--color-brand-500));
    --ap-primary-50:var(--sg-theme-primary-50,var(--color-brand-50));
    --ap-primary-100:var(--sg-theme-primary-100,var(--color-brand-100));
    --ap-primary-700:var(--sg-theme-primary-700,var(--color-brand-700));
    --ap-primary-900:var(--sg-theme-primary-900,var(--color-brand-900));
    --ap-accent:var(--sg-theme-accent,var(--color-success-700));
    --ap-ink:var(--color-black);
    --ap-muted:var(--color-slate-600);
    --ap-soft:var(--color-ink-50);
    --ap-line:var(--color-ink-100);
    width:100%;max-width:none;margin:0;padding:0 0 34px;
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,sans-serif);color:var(--ap-ink);
}
.sige-ap-page *,.sige-ap-page *::before,.sige-ap-page *::after{box-sizing:border-box;}
.sige-ap-page svg{width:18px;height:18px;stroke:currentColor;fill:none;}
.sige-ap-page a{text-decoration:none;}

/* Hero claro, igual à lógica do Painel Principal */
.sige-hero{
    position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr);gap:18px;align-items:center;
    margin:0 0 18px;padding:34px 38px;border-radius:var(--radius-xl);
    background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 52%,var(--ap-primary-50) 100%);
    border:1px solid rgba(31,32,55,.06);box-shadow:var(--shadow-lg);color:var(--ap-ink);
}
.sige-hero:after{content:"";position:absolute;right:-64px;top:-88px;width:390px;height:270px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(var(--sg-theme-primary-rgb,90,63,214),.20),transparent 68%);pointer-events:none;}
.sige-hero:before{content:"";position:absolute;right:100px;bottom:-90px;width:280px;height:220px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(52,168,83,.13),transparent 70%);pointer-events:none;}
.sige-hero-main{position:relative;z-index:1;min-width:0;max-width:860px;}
.sige-hero-kicker{display:inline-flex;align-items:center;gap:9px;margin:0 0 14px;padding:8px 14px;border-radius:var(--radius-pill);background:var(--ap-primary-50);color:var(--ap-primary);font-size:12px;line-height:1;font-weight:700;letter-spacing:.16em;text-transform:uppercase;box-shadow:inset 0 0 0 1px rgba(var(--sg-theme-primary-rgb,90,63,214),.08);}
.sige-hero-kicker svg{width:18px;height:18px;}
.sige-hero h1{position:relative;z-index:1;margin:0;color:var(--ap-ink);font-size:clamp(31px,3.15vw,48px);line-height:1.05;letter-spacing:-.055em;font-weight:700;display:block;}
.sige-hero p{position:relative;z-index:1;max-width:820px;margin:13px 0 0;color:var(--color-slate-700);font-size:15.5px;line-height:1.72;font-weight:600;}
.sige-hero-meta{position:relative;z-index:1;display:flex;flex-wrap:wrap;gap:10px;margin-top:22px;}
.sige-hero-chip{display:inline-flex;align-items:center;gap:var(--space-2);min-height:36px;padding:0 14px;border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-ink-100);color:var(--ap-ink);box-shadow:var(--shadow-sm);font-size:12px;font-weight:700;}
.sige-hero .sige-hero-meta:after{content:"";display:block;position:absolute;right:8px;top:-62px;width:300px;height:170px;opacity:.92;background:linear-gradient(135deg,rgba(var(--sg-theme-primary-rgb,90,63,214),.08),rgba(var(--sg-theme-primary-rgb,90,63,214),.20));border-radius:var(--radius-xl);z-index:-1;}

/* Cartões e KPIs */
.sige-card{background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);border:1px solid rgba(31,32,55,.065);padding:22px;margin:0 0 18px;}
.sige-ap-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-4);margin:0 0 18px;}
.sige-ap-kpi{position:relative;overflow:hidden;display:flex;align-items:center;gap:var(--space-4);min-height:118px;padding:var(--space-5);border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(31,32,55,.06);box-shadow:var(--shadow-md);}
.sige-ap-kpi:after{content:"";position:absolute;right:-30px;top:-36px;width:108px;height:108px;border-radius:var(--radius-pill);background:var(--kpi-soft,var(--ap-primary-50));pointer-events:none;}
.sige-ap-kpi-ic{position:relative;z-index:1;width:56px;height:56px;flex:0 0 56px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--kpi-soft,var(--ap-primary-50));color:var(--kpi-strong,var(--ap-primary));}
.sige-ap-kpi-ic svg{width:24px;height:24px;}
.sige-ap-kpi-body{position:relative;z-index:1;min-width:0;}
.sige-ap-kpi span{display:block;color:var(--color-slate-600);font-size:var(--fs-sm);font-weight:600;margin-bottom:6px;}
.sige-ap-kpi strong{display:block;color:var(--ap-ink);font-size:25px;line-height:1.05;font-weight:700;letter-spacing:-.045em;word-break:break-word;}
.sige-ap-kpi small{display:block;margin-top:6px;color:var(--color-ink-400);font-size:12px;font-weight:600;}
.sige-ap-kpi.is-green{--kpi-soft:var(--color-success-100);--kpi-strong:var(--color-success-700);}
.sige-ap-kpi.is-blue{--kpi-soft:var(--color-info-50);--kpi-strong:var(--color-info-400);}
.sige-ap-kpi.is-amber{--kpi-soft:var(--color-warning-100);--kpi-strong:var(--color-warning-600);}
.sige-ap-kpi.is-red{--kpi-soft:var(--color-danger-50);--kpi-strong:var(--color-danger-400);}

/* Pesquisa */
.sige-search-form{display:grid;grid-template-columns:minmax(260px,1fr) minmax(240px,.75fr) 180px auto;gap:14px;align-items:end;}
.sige-search-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.sige-fg{min-width:0;}
.sige-fg label{display:block;font-size:var(--fs-xs);font-weight:700;color:var(--color-slate-600);text-transform:uppercase;letter-spacing:.08em;margin:0 0 7px;}
.sige-fg input,.sige-fg select{width:100%;min-height:46px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);background:var(--color-white);color:var(--ap-ink);padding:0 15px;font-size:var(--fs-base);font-weight:600;outline:0;box-shadow:var(--shadow-sm);transition:border .18s,box-shadow .18s;}
.sige-fg input:focus,.sige-fg select:focus{border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.36);box-shadow:var(--shadow-xs);}

/* Botões */
.sige-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:46px;padding:0 18px;border-radius:var(--radius-md);font-size:var(--fs-sm);font-weight:700;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,background .18s ease;color:inherit;font-family:inherit;}
.sige-btn:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm);}
.sige-btn svg{width:18px;height:18px;}
.sige-btn-primary,.sige-btn-print{background:var(--ap-primary);color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-btn-primary:hover,.sige-btn-print:hover{background:var(--ap-primary-700);color:var(--color-white)!important;}
.sige-btn-excel{background:var(--color-success-500);color:var(--color-white)!important;}
.sige-btn-histo{background:var(--color-white);color:var(--ap-primary)!important;border-color:var(--color-brand-100);box-shadow:var(--shadow-sm);}
.sige-btn-back{background:var(--color-white);color:var(--color-slate-800)!important;border-color:var(--color-slate-100);box-shadow:var(--shadow-sm);} .sige-btn-doc{background:var(--color-white);color:var(--ap-primary)!important;border-color:var(--color-brand-100);box-shadow:var(--shadow-sm);} .sige-btn-doc:hover{background:var(--ap-primary-50);color:var(--ap-primary-700)!important;}
.sige-actions{display:flex;gap:10px;justify-content:flex-end;align-items:center;padding:14px;margin:16px 0 18px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid var(--color-slate-100);flex-wrap:wrap;}

/* Resultados de pesquisa */
.sige-res-item{display:flex;align-items:center;gap:15px;padding:15px 16px;border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);margin-bottom:10px;background:var(--color-white);cursor:pointer;transition:all .18s;text-decoration:none;color:inherit;box-shadow:var(--shadow-sm);}
.sige-res-item:hover{background:var(--ap-primary-50);border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.20);transform:translateX(4px);}
.sige-res-avatar{width:48px;height:48px;border-radius:var(--radius-lg);background:var(--ap-primary);color:var(--color-white);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;flex-shrink:0;}
.sige-res-info{flex:1;min-width:0;}
.sige-res-info strong{display:block;font-size:var(--fs-base);color:var(--ap-ink);margin-bottom:3px;font-weight:700;}
.sige-res-info small{display:block;font-size:12px;color:var(--color-slate-600);font-weight:600;}
.sige-res-action{color:var(--ap-primary);font-size:12px;font-weight:700;}

/* Ficha do aluno */
.sige-ficha{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:var(--space-3);margin-bottom:18px;border:0;background:transparent;overflow:visible;}
.sige-ficha-item{position:relative;overflow:hidden;min-height:82px;padding:15px 16px;border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);background:var(--color-white);box-shadow:var(--shadow-sm);}
.sige-ficha-item:after{content:"";position:absolute;right:-22px;top:-26px;width:82px;height:82px;border-radius:var(--radius-pill);background:var(--ap-primary-50);}
.sige-ficha-label{position:relative;z-index:1;font-size:var(--fs-xs);font-weight:700;color:var(--color-ink-400);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;}
.sige-ficha-value{position:relative;z-index:1;font-size:15px;font-weight:700;color:var(--ap-ink);}
.sige-ficha-value.warning{color:var(--color-warning-600)}.sige-ficha-value.success{color:var(--color-success-700)}

/* Separadores */
.sige-vistas{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-2);padding:7px;background:var(--color-ink-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);margin-bottom:16px;}
.sige-vista-btn{min-height:44px;padding:0 14px;border:none;background:transparent;border-radius:var(--radius-md);font-weight:700;font-size:var(--fs-sm);color:var(--color-slate-600);cursor:pointer;transition:all .18s;text-decoration:none;text-align:center;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:9px;}
.sige-vista-btn:hover{background:var(--color-white);color:var(--ap-ink);}
.sige-vista-btn.active{background:var(--color-white);color:var(--ap-primary);box-shadow:0 10px 22px rgba(45,36,96,.07),inset 0 0 0 1px rgba(var(--sg-theme-primary-rgb,90,63,214),.14);}
.sige-vista-btn svg{width:18px;height:18px;}

/* Nota explicativa */
.sige-info-note{display:flex;gap:var(--space-3);align-items:flex-start;font-size:var(--fs-sm);line-height:1.55;color:var(--color-slate-600);padding:14px 16px;background:var(--ap-primary-50);border:1px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.08);border-left:4px solid var(--ap-primary);border-radius:var(--radius-lg);margin-bottom:14px;font-weight:600;}
.sige-info-note strong{color:var(--ap-ink);}

/* Tabelas */
.sige-tw{overflow-x:auto;background:var(--color-white);border:1px solid var(--color-slate-100);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);}
table.sige-nt{border-collapse:separate;border-spacing:0;width:100%;font-size:var(--fs-sm);min-width:860px;}
table.sige-nt th,table.sige-nt td{padding:12px 10px;text-align:center;border-right:1px solid var(--color-slate-100);border-bottom:1px solid var(--color-slate-100);white-space:nowrap;}
table.sige-nt th:last-child,table.sige-nt td:last-child{border-right:none;}
table.sige-nt thead tr:first-child th{background:var(--ap-primary);color:var(--color-white);font-weight:700;font-size:12px;letter-spacing:.02em;padding:14px 10px;}
table.sige-nt thead tr:nth-child(2) th{background:var(--ap-primary-700);color:var(--color-white);font-weight:700;font-size:var(--fs-xs);padding:9px 10px;}
table.sige-nt thead tr:first-child th:first-child{border-top-left-radius:22px;}
table.sige-nt thead tr:first-child th:last-child{border-top-right-radius:22px;}
table.sige-nt tbody tr:hover{background:var(--color-white);}
table.sige-nt tbody tr:nth-child(even){background:var(--color-white);}
table.sige-nt .td-disc{text-align:left;padding-left:16px;min-width:220px;font-weight:700;color:var(--ap-ink);}
table.sige-nt .td-sig{font-size:var(--fs-xs);color:var(--color-slate-600);font-weight:700;}
table.sige-nt tfoot td{background:var(--color-slate-50);font-weight:700;color:var(--ap-primary-900);border-top:2px solid var(--color-ink-100);padding:14px 10px;}
.sige-nt-med{background:var(--color-success-100)!important;font-weight:700;color:var(--color-success-800);}
.sige-nt-mt{background:var(--ap-primary-50)!important;font-weight:700;color:var(--ap-primary-900);}
.sige-nt-mfd{background:var(--color-warning-100)!important;font-weight:700;color:var(--color-warning-700);}
.sige-nt-nf{background:var(--color-danger-50)!important;font-weight:700;color:var(--color-danger-700);}
.sige-nt-neg{color:var(--color-danger-400)!important;font-weight:700;}
.sige-nt-vazio{color:var(--color-slate-400);}
.sige-nt-escala{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:22px;padding:0 7px;border-radius:var(--radius-pill);font-size:10px;font-weight:700;margin-left:6px;vertical-align:middle;}
.sige-escala-NS{background:var(--color-danger-50);color:var(--color-danger-600)}.sige-escala-S{background:var(--color-warning-100);color:var(--color-danger-500)}.sige-escala-B{background:var(--color-info-50);color:var(--color-info-600)}.sige-escala-MB{background:var(--color-success-100);color:var(--color-success-800)}.sige-escala-E{background:var(--color-brand-100);color:var(--color-brand-700)}
.sige-tr-nuclear .td-disc::before{content:"★";display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;margin-right:8px;border-radius:var(--radius-sm);background:var(--color-warning-100);color:var(--color-warning-600);font-size:12px;}
.sige-tr-auxiliar .td-disc::before{content:"○";display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;margin-right:8px;border-radius:var(--radius-sm);background:var(--color-ink-50);color:var(--color-slate-400);font-size:var(--fs-sm);}

/* Situação final */
.sige-sit{display:flex;align-items:center;gap:14px;padding:18px 20px;border-radius:var(--radius-xl);margin-top:18px;flex-wrap:wrap;border:1px solid transparent;box-shadow:var(--shadow-md);}
.sige-sit.progride{background:var(--color-success-100);border-color:var(--color-success-200)}.sige-sit.transita{background:var(--color-info-50);border-color:var(--color-info-100)}.sige-sit.reprova{background:var(--color-danger-50);border-color:var(--color-danger-100)}
.sige-sit-badge{font-size:15px;font-weight:700;padding:10px 16px;border-radius:var(--radius-md);letter-spacing:.01em;color:var(--color-white);}
.sige-sit.progride .sige-sit-badge{background:var(--color-success-700)}.sige-sit.transita .sige-sit-badge{background:var(--color-info-400)}.sige-sit.reprova .sige-sit-badge{background:var(--color-danger-400)}
.sige-sit-meta{font-size:var(--fs-sm);color:var(--color-slate-700);font-weight:700}.sige-sit-meta strong{color:var(--ap-ink)}

/* Filtro por turma / MAP em massa */
.sige-turma-panel{margin-top:20px;padding-top:20px;border-top:1px solid var(--color-ink-50);}
.sige-turma-head{display:flex;align-items:flex-start;justify-content:space-between;gap:var(--space-4);margin-bottom:14px;flex-wrap:wrap;}
.sige-turma-title{display:flex;align-items:center;gap:var(--space-3);min-width:0;}
.sige-turma-title .ic{width:46px;height:46px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--ap-primary-50);color:var(--ap-primary);flex:0 0 46px;}
.sige-turma-title h3{margin:0;font-size:18px;font-weight:700;color:var(--ap-ink);letter-spacing:-.025em;}
.sige-turma-title p{margin:var(--space-1) 0 0;color:var(--color-slate-600);font-size:var(--fs-sm);font-weight:600;line-height:1.45;}
.sige-turma-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.sige-turma-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 0 14px;}
.sige-turma-meta .box{padding:13px 14px;border-radius:var(--radius-lg);background:var(--color-white);border:1px solid var(--color-slate-100);}
.sige-turma-meta .box span{display:block;font-size:10.5px;font-weight:700;color:var(--color-slate-600);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;}
.sige-turma-meta .box strong{display:block;font-size:var(--fs-md);font-weight:700;color:var(--ap-ink);}
.sige-turma-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;}
.sige-turma-aluno{display:flex;align-items:center;gap:var(--space-3);padding:13px;border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);background:var(--color-white);transition:all .18s ease;color:inherit;}
.sige-turma-aluno:hover{border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.22);background:var(--color-white);box-shadow:var(--shadow-sm);transform:translateY(-1px);}
.sige-turma-aluno .av{width:42px;height:42px;border-radius:var(--radius-md);background:var(--ap-primary-50);color:var(--ap-primary);display:flex;align-items:center;justify-content:center;font-weight:700;flex:0 0 42px;}
.sige-turma-aluno .info{min-width:0;flex:1;}
.sige-turma-aluno .info strong{display:block;font-size:13.5px;font-weight:700;color:var(--ap-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sige-turma-aluno .info small{display:block;margin-top:2px;font-size:11.5px;font-weight:600;color:var(--color-slate-600);}
.sige-turma-aluno .go{font-size:12px;font-weight:700;color:var(--ap-primary);white-space:nowrap;}
@media(max-width:980px){.sige-turma-meta{grid-template-columns:1fr}.sige-turma-list{grid-template-columns:1fr}}

/* Estados vazios e histórico */
.sige-empty{text-align:center;padding:64px 30px;background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);border:1px solid rgba(31,32,55,.06);}
.sige-empty-ic{width:82px;height:82px;margin:0 auto var(--space-5);background:var(--ap-primary-50);color:var(--ap-primary);border-radius:var(--radius-xl);display:flex;align-items:center;justify-content:center;}
.sige-empty-ic svg{width:38px;height:38px;}
.sige-empty h3{font-size:22px;line-height:1.2;font-weight:700;color:var(--ap-ink);margin:0 0 var(--space-2);letter-spacing:-.03em;}
.sige-empty p{color:var(--color-slate-600);font-size:var(--fs-base);margin:var(--space-1) 0;font-weight:600;}
.sige-hist-panel{background:var(--color-white);border-radius:var(--radius-xl);overflow:hidden;margin-bottom:20px;border:1px solid rgba(31,32,55,.06);box-shadow:var(--shadow-md);}
.sige-hist-header{padding:18px 22px;background:var(--color-white);color:var(--ap-ink);font-weight:700;display:flex;align-items:center;gap:var(--space-3);border-bottom:1px solid var(--color-slate-100);}
.sige-hist-header svg{width:20px;height:20px;color:var(--ap-primary);}
table.sige-hist{width:100%;border-collapse:collapse;font-size:var(--fs-sm);min-width:680px;}
table.sige-hist th{background:var(--color-slate-50);padding:13px 16px;text-align:left;font-weight:700;color:var(--color-slate-600);border-bottom:1px solid var(--color-slate-100);text-transform:uppercase;letter-spacing:.06em;font-size:var(--fs-xs);}
table.sige-hist td{padding:14px 16px;border-bottom:1px solid var(--color-ink-50);font-weight:600;}
.sige-badge-mini{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border-radius:var(--radius-pill);font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;}
.sige-badge-mini.progride{background:var(--color-success-100);color:var(--color-success-800)}.sige-badge-mini.transita{background:var(--color-info-50);color:var(--color-info-600)}.sige-badge-mini.reprova{background:var(--color-danger-50);color:var(--color-danger-700)}

.sige-ft{text-align:center;padding:14px;font-size:12px;color:var(--color-slate-400);font-weight:600;}
.sige-guide{margin-top:14px;font-size:12px;color:var(--color-slate-600);padding:13px 15px;background:var(--color-white);border:1px solid var(--color-slate-100);border-radius:var(--radius-lg);display:flex;gap:14px;flex-wrap:wrap;line-height:1.5;font-weight:600;}
.sige-guide strong{color:var(--ap-ink);}

@media(max-width:1180px){.sige-ap-kpis{grid-template-columns:repeat(2,minmax(0,1fr));}.sige-ficha{grid-template-columns:repeat(3,minmax(0,1fr));}.sige-hero{grid-template-columns:1fr;}.sige-hero .sige-hero-meta:after{display:none;}}
@media(max-width:860px){.sige-ap-page{padding-bottom:20px}.sige-hero{padding:var(--space-6);border-radius:22px}.sige-hero h1{font-size:28px}.sige-search-form{grid-template-columns:1fr}.sige-ap-kpis{grid-template-columns:1fr}.sige-ficha{grid-template-columns:1fr}.sige-vistas{grid-template-columns:1fr}.sige-card{border-radius:var(--radius-xl);padding:16px}.sige-actions{justify-content:stretch}.sige-actions .sige-btn{flex:1;min-width:160px}.sige-tw{border-radius:16px}}
</style>

<div class="wrap sige-ap-page">

    <!-- HERO -->
    <div class="sige-hero">
        <div class="sige-hero-main">
            <div class="sige-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Aproveitamento</div>
            <h1>Aproveitamento do Aluno</h1>
            <p>Acompanhe o desempenho do aluno por trimestre, disciplina e resultado anual, com leitura clara para a secretaria e direcção pedagógica.</p>
            <div class="sige-hero-meta">
                <span class="sige-hero-chip">Ano Lectivo <?= esc_html($ano_sel) ?></span>
                <?php if ($aluno): ?><span class="sige-hero-chip">👤 <?= esc_html($aluno->nome_completo) ?></span><?php endif; ?>
                <?php if ($turma): ?><span class="sige-hero-chip">📚 <?= esc_html(($turma->classe ?? '')) ?> - <?= esc_html($turma->nome ?? $turma->nome_turma ?? '-') ?></span><?php endif; ?>
                <?php if (!$aluno && $turma_filtro): ?><span class="sige-hero-chip">📚 Turma filtrada: <?= esc_html(($turma_filtro->classe ?? '')) ?> - <?= esc_html($turma_filtro->nome_turma ?? '-') ?></span><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($sige_bol_scope_msg)): ?>
        <?php if (function_exists('sige_render_teacher_scope_denied')) { sige_render_teacher_scope_denied($sige_bol_scope_msg); } else { echo '<div class="notice notice-warning"><p>' . esc_html($sige_bol_scope_msg) . '</p></div>'; } ?>
    <?php endif; ?>

    <?php if ($aluno):
        $ap_total_disciplinas = is_array($boletim) ? count($boletim) : 0;
        $ap_situacao_txt = 'Em acompanhamento';
        $ap_situacao_class = 'is-blue';
        if ($sit_final && !empty($sit_final['situacao'])) {
            $tmp = strtolower((string) $sit_final['situacao']);
            if ($tmp === 'reprova') {
                $ap_situacao_txt = $eh_fim_ciclo ? 'Não transita' : 'Não progride';
                $ap_situacao_class = 'is-red';
            } elseif ($tmp === 'transita') {
                $ap_situacao_txt = 'Transita';
                $ap_situacao_class = 'is-blue';
            } else {
                $ap_situacao_txt = 'Progride';
                $ap_situacao_class = 'is-green';
            }
        }
    ?>
    <div class="sige-ap-kpis">
        <div class="sige-ap-kpi">
            <div class="sige-ap-kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
            <div class="sige-ap-kpi-body"><span>Disciplinas</span><strong><?= esc_html($ap_total_disciplinas) ?></strong><small>Registadas para a turma</small></div>
        </div>
        <div class="sige-ap-kpi is-green">
            <div class="sige-ap-kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
            <div class="sige-ap-kpi-body"><span>Média anual</span><strong><?= $media_final !== null ? esc_html(sige_bol_fmt($media_final)) : '-' ?></strong><small>Disciplinas nucleares</small></div>
        </div>
        <div class="sige-ap-kpi is-amber">
            <div class="sige-ap-kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-6"/></svg></div>
            <div class="sige-ap-kpi-body"><span>Vista actual</span><strong><?= esc_html($vistas_label[$vista_ap] ?? 'Anual') ?></strong><small>Ano lectivo <?= esc_html($ano_sel) ?></small></div>
        </div>
        <div class="sige-ap-kpi <?= esc_attr($ap_situacao_class) ?>">
            <div class="sige-ap-kpi-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            <div class="sige-ap-kpi-body"><span>Situação</span><strong><?= esc_html($ap_situacao_txt) ?></strong><small>Consolidado pedagógico</small></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SEARCH / FILTROS - sempre inicia nova pesquisa (sem preservar aluno carregado) -->
    <div class="sige-card">
        <form method="GET" action="<?= esc_url(admin_url('admin.php')) ?>" class="sige-search-form">
            <input type="hidden" name="page" value="sige-app">
            <input type="hidden" name="view" value="boletim">
            <div class="sige-fg">
                <label>Pesquisar aluno</label>
                <input type="text" name="search_q" value="<?= esc_attr($search_q) ?>" placeholder="Nome ou nº de processo">
            </div>
            <div class="sige-fg">
                <label>Filtrar por turma</label>
                <select name="turma_busca_id">
                    <option value="0">Todas as turmas</option>
                    <?php foreach ($turmas_pesquisa as $tp): ?>
                    <option value="<?= esc_attr((int) $tp->id) ?>" <?= selected((int) $tp->id, (int) $turma_busca_id, false) ?>>
                        <?= esc_html(trim(($tp->classe ? $tp->classe . ' - ' : '') . ($tp->nome_turma ?? 'Turma'))) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sige-fg" style="max-width:180px">
                <label>Ano Lectivo</label>
                <select name="ano_bol">
                    <?php foreach ($anos_opts as $a): ?>
                    <option value="<?= esc_attr($a) ?>" <?= selected($a, (int) $ano_sel, false) ?>><?= esc_html($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sige-search-actions">
                <button type="submit" class="sige-btn sige-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Pesquisar
                </button>
                <?php if ($search_q || $turma_busca_id > 0): ?>
                <a href="<?= esc_url($base_url . '&ano_bol=' . urlencode($ano_sel)) ?>" class="sige-btn sige-btn-back" title="Limpar pesquisa e filtro de turma">Limpar</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($turma_filtro && !$aluno):
            $url_map_turma_filtro = add_query_arg([
                'action'   => 'sige_map_turma_pdf',
                'turma_id' => (int) $turma_busca_id,
                'ano'      => $ano_sel,
                '_wpnonce' => wp_create_nonce('sige_map_turma_pdf'),
            ], admin_url('admin-post.php'));
            $total_turma_alunos = is_array($turma_alunos) ? count($turma_alunos) : 0;
        ?>
        <div class="sige-turma-panel">
            <div class="sige-turma-head">
                <div class="sige-turma-title">
                    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                    <div>
                        <h3><?= esc_html(trim(($turma_filtro->classe ? $turma_filtro->classe . ' - ' : '') . ($turma_filtro->nome_turma ?? 'Turma'))) ?></h3>
                        <p>Filtro pedagógico por turma activo. Pode abrir o aproveitamento individual ou imprimir o MAP oficial de toda a turma.</p>
                    </div>
                </div>
                <div class="sige-turma-actions">
                    <?php if ($total_turma_alunos > 0): ?>
                    <a href="<?= esc_url($url_map_turma_filtro) ?>" target="_blank" class="sige-btn sige-btn-doc" title="Imprimir mapa oficial em massa para esta turma">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M3 16v5h5"/><path d="M21 16v5h-5"/><rect x="7" y="7" width="10" height="10" rx="2"/></svg>
                        Mapa oficial da turma
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sige-turma-meta">
                <div class="box"><span>Ano lectivo</span><strong><?= esc_html($ano_sel) ?></strong></div>
                <div class="box"><span>Alunos activos</span><strong><?= esc_html($total_turma_alunos) ?></strong></div>
                <div class="box"><span>Documento</span><strong>MAP oficial</strong></div>
            </div>
            <?php if ($total_turma_alunos > 0): ?>
            <div class="sige-turma-list">
                <?php foreach ($turma_alunos as $ta):
                    $iniciais_ta = mb_substr($ta->nome_completo, 0, 1) . (strpos($ta->nome_completo, ' ') !== false ? mb_substr(strrchr($ta->nome_completo, ' '), 1, 1) : '');
                    $url_ta = $base_url . '&aluno_id=' . (int) $ta->id . '&turma_id=' . (int) $turma_busca_id . '&ano_bol=' . urlencode($ano_sel) . '&vista_ap=anual&turma_busca_id=' . (int) $turma_busca_id;
                ?>
                <a href="<?= esc_url($url_ta) ?>" class="sige-turma-aluno">
                    <div class="av"><?= esc_html(mb_strtoupper($iniciais_ta)) ?></div>
                    <div class="info">
                        <strong><?= esc_html($ta->nome_completo) ?></strong>
                        <small>Nº <?= esc_html($ta->numero_processo ?: '-') ?> · <?= esc_html($ta->genero ?: '-') ?></small>
                    </div>
                    <span class="go">Ver →</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="sige-empty" style="box-shadow:none;border-radius:18px;padding:28px;margin-top:12px">
                <h3>Nenhum aluno activo nesta turma</h3>
                <p>Confirme as matrículas activas da turma no ano lectivo seleccionado.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- Resultados -->
        <?php if ($search_q && !empty($resultados) && !$aluno): ?>
        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--color-info-50)">
            <p style="font-size:.8rem;color:var(--color-slate-500);margin:0 0 12px"><?= count($resultados) ?> resultado(s) para "<strong><?= esc_html($search_q) ?></strong>"<?= $turma_filtro ? ' na turma ' . esc_html($turma_filtro->nome_turma ?? '') : '' ?></p>
            <?php foreach ($resultados as $r):
                $iniciais = mb_substr($r->nome_completo, 0, 1) . (strpos($r->nome_completo, ' ') !== false ? mb_substr(strrchr($r->nome_completo, ' '), 1, 1) : '');
                $url_sel = $base_url . '&aluno_id=' . (int) $r->id . '&turma_id=' . (int) ($r->turma_id ?? 0) . '&ano_bol=' . urlencode($ano_sel) . ($turma_busca_id > 0 ? '&turma_busca_id=' . (int) $turma_busca_id : '');
            ?>
            <a href="<?= esc_url($url_sel) ?>" class="sige-res-item">
                <div class="sige-res-avatar"><?= esc_html(mb_strtoupper($iniciais)) ?></div>
                <div class="sige-res-info">
                    <strong><?= esc_html($r->nome_completo) ?></strong>
                    <small>Nº <?= esc_html($r->numero_processo ?: '-') ?> · <?= esc_html($r->classe ?? '-') ?> · <?= esc_html($r->nome_turma ?? '-') ?></small>
                </div>
                <span class="sige-res-action">Ver →</span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php elseif ($search_q && empty($resultados) && !$aluno): ?>
        <div style="margin-top:20px;padding:16px;text-align:center;color:var(--color-slate-400);font-size:.88rem">Sem resultados para "<?= esc_html($search_q) ?>".</div>
        <?php endif; ?>
    </div>

    <?php if ($aluno): ?>

    <!-- FICHA DO ALUNO + VISTAS -->
    <div class="sige-card">
        <!-- Ficha -->
        <div class="sige-ficha">
            <div class="sige-ficha-item"><div class="sige-ficha-label">Nº Processo</div><div class="sige-ficha-value"><?= esc_html($aluno->numero_processo ?: '-') ?></div></div>
            <div class="sige-ficha-item"><div class="sige-ficha-label">Género</div><div class="sige-ficha-value"><?= esc_html($aluno->genero ?: '-') ?></div></div>
            <div class="sige-ficha-item"><div class="sige-ficha-label">Classe</div><div class="sige-ficha-value"><?= esc_html($turma->classe ?? '-') ?></div></div>
            <div class="sige-ficha-item"><div class="sige-ficha-label">Turma</div><div class="sige-ficha-value"><?= esc_html($turma ? ($turma->nome ?: $turma->nome_turma) : '-') ?></div></div>
            <div class="sige-ficha-item"><div class="sige-ficha-label">Turno</div><div class="sige-ficha-value"><?= esc_html($turma->turno ?? '-') ?></div></div>
            <div class="sige-ficha-item"><div class="sige-ficha-label">Pauta Final</div><div class="sige-ficha-value <?= $pf_mode ? 'success' : 'warning' ?>"><?= $pf_mode ? '✅ Activa' : '⏸ Inactiva' ?></div></div>
        </div>

        <?php if (empty($boletim)): ?>
        <div class="sige-empty" style="border-radius:0;box-shadow:none;border:none;border-top:1px solid var(--color-info-50)">
            <div class="sige-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <h3>Sem notas registadas</h3>
            <p>Não existem notas para este aluno no ano <?= esc_html($ano_sel) ?>.</p>
            <?php if (!$turma): ?><p style="font-size:.82rem;color:var(--color-slate-200)">Aluno sem matrícula activa neste ano lectivo.</p><?php endif; ?>
        </div>
        <?php else: ?>

        <!-- VISTA SELECTOR -->
        <div class="sige-vistas" role="tablist">
            <?php
            $url_base_vista = $base_url . '&aluno_id=' . (int) $aluno_id . '&turma_id=' . (int) $turma_id . '&ano_bol=' . urlencode($ano_sel);
            $vista_icons = [
                't1'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="currentColor" stroke="none">1</text></svg>',
                't2'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="currentColor" stroke="none">2</text></svg>',
                't3'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><text x="12" y="16" text-anchor="middle" font-size="10" font-weight="bold" fill="currentColor" stroke="none">3</text></svg>',
                'anual' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            ];
            foreach ($vistas_label as $k => $label):
                $url_v = $url_base_vista . '&vista_ap=' . $k;
            ?>
            <a href="<?= esc_url($url_v) ?>" class="sige-vista-btn <?= $vista_ap === $k ? 'active' : '' ?>">
                <?= $vista_icons[$k] ?>
                <?= esc_html($label) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- INFO NOTE -->
        <?php if ($vista_ap !== 'anual'): ?>
        <div class="sige-info-note">
            <strong>Vista trimestral:</strong> mostra as avaliações do período, a média de acompanhamento e o resultado trimestral por disciplina. A situação final aparece apenas no consolidado anual.
        </div>
        <?php else: ?>
        <div class="sige-info-note">
            <strong>Vista anual:</strong> apresenta o consolidado do ano lectivo, a média final e a situação do aluno. A média geral considera apenas as disciplinas nucleares. <?= $pf_mode && $eh_fim_ciclo && $exige_exame ? 'Turma de fim de ciclo com exame final incluído.' : '' ?>
        </div>
        <?php endif; ?>

        <!-- ACTION BAR -->
        <div class="sige-actions">
            <?php
            $url_p   = $base_url . '&aluno_id=' . $aluno_id . '&turma_id=' . $turma_id . '&ano_bol=' . urlencode($ano_sel) . '&vista_ap=' . $vista_ap . '&modo=print';
            $url_h   = $base_url . '&aluno_id=' . $aluno_id . '&turma_id=' . $turma_id . '&ano_bol=' . urlencode($ano_sel) . '&vista_ap=' . $vista_ap . '&modo=historico&search_q=' . urlencode($search_q);
            $url_b   = $base_url . '&search_q=' . urlencode($search_q) . '&ano_bol=' . urlencode($ano_sel) . ($turma_busca_id > 0 ? '&turma_busca_id=' . (int) $turma_busca_id : '');
            // [Sprint 2 · M1] Mapa oficial - imprimível institucional estilo MAPED/A25
            $url_map = add_query_arg(['action' => 'sige_map_pdf', 'aluno_id' => $aluno_id, 'ano' => $ano_sel, '_wpnonce' => wp_create_nonce('sige_map_pdf')], admin_url('admin-post.php'));
            $url_map_turma = add_query_arg(['action' => 'sige_map_turma_pdf', 'turma_id' => $turma_id, 'ano' => $ano_sel, '_wpnonce' => wp_create_nonce('sige_map_turma_pdf')], admin_url('admin-post.php'));
            // [MALISA-V6] Documentos finais - só fazem sentido na vista anual/consolidada.
            $url_declaracao = add_query_arg(['action' => 'sige_declaracao_passagem_pdf', 'aluno_id' => $aluno_id, 'ano' => $ano_sel, '_wpnonce' => wp_create_nonce('sige_declaracao_passagem_pdf')], admin_url('admin-post.php'));
            $url_boletim_passagem = add_query_arg(['action' => 'sige_boletim_passagem_pdf', 'aluno_id' => $aluno_id, 'ano' => $ano_sel, '_wpnonce' => wp_create_nonce('sige_boletim_passagem_pdf')], admin_url('admin-post.php'));
            ?>
            <button type="button" class="sige-btn sige-btn-print" data-sige-act="apPrint" data-sige-noargs>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir / PDF
            </button>
            <?php if ($vista_ap === 'anual'): ?>
            <a href="<?= esc_url($url_map) ?>" target="_blank" class="sige-btn sige-btn-doc" title="Mapa de Aproveitamento Pedagógico (MAPED/A<?= esc_attr(substr((string)$ano_sel, -2)) ?>) - formato institucional oficial">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
                Mapa oficial
            </a>
            <a href="<?= esc_url($url_map_turma) ?>" target="_blank" class="sige-btn sige-btn-doc" title="Imprimir mapa oficial em massa para todos os alunos activos desta turma">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5"/><path d="M8 3H3v5"/><path d="M3 16v5h5"/><path d="M21 16v5h-5"/><rect x="7" y="7" width="10" height="10" rx="2"/></svg>
                Mapa oficial da turma
            </a>
            <a href="<?= esc_url($url_declaracao) ?>" target="_blank" class="sige-btn sige-btn-doc" title="Declaração de Passagem - documento final anual">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="16" y2="16"/></svg>
                Declaração
            </a>
            <a href="<?= esc_url($url_boletim_passagem) ?>" target="_blank" class="sige-btn sige-btn-doc" title="Boletim de Passagem - documento final anual">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Boletim de passagem
            </a>
            <?php endif; ?>
            <a href="<?= esc_url($url_h) ?>" class="sige-btn sige-btn-histo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Histórico
            </a>
            <a href="<?= esc_url($url_b) ?>" class="sige-btn sige-btn-back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Nova pesquisa
            </a>
        </div>

        <!-- TABELAS POR VISTA -->
        <?php if (in_array($vista_ap, ['t1', 't2', 't3'], true)):
            $tr = (int) substr($vista_ap, 1);
            $lbl_at = 'AT';
        ?>

        <!-- VISTA TRIMESTRAL -->
        <div class="sige-tw">
            <table class="sige-nt" id="ap-t-trim">
                <thead>
                    <tr>
                        <th rowspan="2" style="min-width:180px;text-align:left;padding-left:14px">Disciplina</th>
                        <th rowspan="2" style="width:40px">Sigla</th>
                        <th colspan="5"><?= esc_html($vistas_label[$vista_ap]) ?></th>
                    </tr>
                    <tr>
                        <th>1ª AC</th>
                        <th>2ª ACP</th>
                        <th style="background:var(--color-success-800) !important">Méd (AC+ACP)/2</th>
                        <th><?= esc_html($lbl_at) ?></th>
                        <th style="background:var(--color-info-500) !important">MT</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($boletim as $r):
                    $trc = ($r['categoria'] ?? '') === 'nuclear' ? 'sige-tr-nuclear' : 'sige-tr-auxiliar';
                    $td  = $r["T{$tr}"] ?? null;
                    $ac  = $td['ac']  ?? null;
                    $acp = $td['acp'] ?? null;
                    $at  = $td['at']  ?? null;
                    $med = $td['med'] ?? null;
                    $mt  = $td['mt']  ?? null;
                    $esc = sige_bol_escala($mt);
                    $cor = sige_bol_cor($mt);
                ?>
                <tr class="<?= $trc ?>">
                    <td class="td-disc"><?= esc_html($r['nome']) ?></td>
                    <td class="td-sig"><?= esc_html($r['sigla']) ?></td>
                    <td class="<?= $ac === null ? 'sige-nt-vazio' : '' ?>"><?= sige_bol_fmt($ac) ?></td>
                    <td class="<?= $acp === null ? 'sige-nt-vazio' : '' ?>"><?= sige_bol_fmt($acp) ?></td>
                    <td class="sige-nt-med"><?= sige_bol_fmt($med) ?></td>
                    <td class="<?= $at === null ? 'sige-nt-vazio' : '' ?>"><?= sige_bol_fmt($at) ?></td>
                    <td class="sige-nt-mt <?= ($mt !== null && $mt < 10) ? 'sige-nt-neg' : '' ?>">
                        <?php if ($mt !== null): ?>
                            <span style="color:<?= $cor ?>"><?= (int) $mt ?></span>
                            <span class="sige-nt-escala sige-escala-<?= $esc ?>"><?= $esc ?></span>
                        <?php else: ?>
                            <span class="sige-nt-vazio">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="sige-u-tar">Média Geral do Trimestre:</td>
                        <td style="background:var(--color-warning-100);color:var(--color-danger-500)"><?= $media_trimestre[$tr] !== null ? sige_bol_fmt($media_trimestre[$tr]) : '-' ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php else:
            // VISTA ANUAL
            $tem_nf = array_filter($boletim, fn($r) => $r['NF'] !== null);
            $mostra_exame = $pf_mode && $eh_fim_ciclo && $exige_exame;
        ?>

        <!-- VISTA ANUAL -->
        <div class="sige-tw">
            <table class="sige-nt" id="ap-t-anual">
                <thead>
                    <tr>
                        <th rowspan="2" style="min-width:180px;text-align:left;padding-left:14px">Disciplina</th>
                        <th rowspan="2" style="width:40px">Sigla</th>
                        <th>1º Trim.</th>
                        <th>2º Trim.</th>
                        <th>3º Trim.</th>
                        <th style="background:var(--color-danger-500) !important">MFD</th>
                        <?php if ($mostra_exame): ?>
                        <th style="background:var(--color-brand-700) !important"><?= esc_html($label_avaliacao_final) ?></th>
                        <?php endif; ?>
                        <?php if ($tem_nf): ?>
                        <th style="background:var(--color-danger-800) !important"><?= esc_html($label_nota_final) ?></th>
                        <?php endif; ?>
                    </tr>
                    <tr>
                        <th>MT1</th>
                        <th>MT2</th>
                        <th>MT3</th>
                        <th></th>
                        <?php if ($mostra_exame): ?><th></th><?php endif; ?>
                        <?php if ($tem_nf): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($boletim as $r):
                    $trc = ($r['categoria'] ?? '') === 'nuclear' ? 'sige-tr-nuclear' : 'sige-tr-auxiliar';
                    $mt1 = $r['T1']['mt'] ?? null;
                    $mt2 = $r['T2']['mt'] ?? null;
                    $mt3 = $r['T3']['mt'] ?? null;
                    $mfd = $r['MFD'];
                    $ex_final = $r['EX_FINAL'] ?? null;
                    $nf  = $r['NF'];
                ?>
                <tr class="<?= $trc ?>">
                    <td class="td-disc"><?= esc_html($r['nome']) ?></td>
                    <td class="td-sig"><?= esc_html($r['sigla']) ?></td>
                    <?php foreach ([$mt1, $mt2, $mt3] as $mt): $esc = sige_bol_escala($mt); $cor = sige_bol_cor($mt); ?>
                    <td class="<?= ($mt !== null && $mt < 10) ? 'sige-nt-neg' : '' ?>">
                        <?php if ($mt !== null): ?>
                            <span style="color:<?= $cor ?>;font-weight:700"><?= (int) $mt ?></span>
                        <?php else: ?>
                            <span class="sige-nt-vazio">-</span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="sige-nt-mfd <?= ($mfd !== null && $mfd < 10) ? 'sige-nt-neg' : '' ?>">
                        <?php if ($mfd !== null): $esc = sige_bol_escala($mfd); ?>
                            <?= (int) $mfd ?><span class="sige-nt-escala sige-escala-<?= $esc ?>"><?= $esc ?></span>
                        <?php else: ?>
                            <span class="sige-nt-vazio">-</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($mostra_exame): ?>
                    <td class="<?= $ex_final === null ? 'sige-nt-vazio' : '' ?>" style="<?= $ex_final !== null ? 'background:var(--color-brand-100);font-weight:700;color:var(--color-brand-700)' : '' ?>">
                        <?= sige_bol_fmt($ex_final) ?>
                    </td>
                    <?php endif; ?>
                    <?php if ($tem_nf): ?>
                    <td class="sige-nt-nf <?= ($nf !== null && $nf < 10) ? 'sige-nt-neg' : '' ?>">
                        <?php if ($nf !== null): $esc = sige_bol_escala($nf); ?>
                            <?= (int) $nf ?><span class="sige-nt-escala sige-escala-<?= $esc ?>"><?= $esc ?></span>
                        <?php else: ?>
                            <span class="sige-nt-vazio">-</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right;padding-right:12px">Média Geral:</td>
                        <td><?= $media_trimestre[1] !== null ? sige_bol_fmt($media_trimestre[1]) : '-' ?></td>
                        <td><?= $media_trimestre[2] !== null ? sige_bol_fmt($media_trimestre[2]) : '-' ?></td>
                        <td><?= $media_trimestre[3] !== null ? sige_bol_fmt($media_trimestre[3]) : '-' ?></td>
                        <td style="background:var(--color-warning-100);color:var(--color-danger-500)"><?= $media_final !== null ? sige_bol_fmt($media_final) : '-' ?></td>
                        <?php if ($mostra_exame): ?><td>-</td><?php endif; ?>
                        <?php if ($tem_nf): ?><td>-</td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- SITUAÇÃO FINAL (APENAS ANUAL) -->
        <?php if ($sit_final): $s = strtolower($sit_final['situacao'] ?? ''); ?>
        <div class="sige-sit <?= $s ?>">
            <?php
            // Label REPROVA depende da classe:
            //  - Classes de fim de ciclo (3ª, 6ª, 9ª, 12ª) → "NÃO TRANSITA"
            //  - Classes intermédias (1ª, 2ª, 4ª, 5ª, etc.) → "NÃO PROGRIDE"
            $ls = [
                'progride' => '✅ PROGRIDE',
                'transita' => '🔄 TRANSITA',
                'reprova'  => $eh_fim_ciclo ? '❌ NÃO TRANSITA' : '❌ NÃO PROGRIDE',
            ];
            ?>
            <span class="sige-sit-badge"><?= esc_html($ls[$s] ?? strtoupper($s)) ?></span>
            <?php if (isset($sit_final['media_global']) && $sit_final['media_global'] !== null): ?>
                <span class="sige-sit-meta">Média Global: <strong><?= (int) $sit_final['media_global'] ?></strong></span>
            <?php endif; ?>
            <?php if (isset($sit_final['negativas'])): ?>
                <span class="sige-sit-meta">Disciplinas com negativa: <strong><?= (int) $sit_final['negativas'] ?></strong></span>
            <?php endif; ?>
            <?php if ($eh_fim_ciclo): ?>
                <span class="sige-sit-meta">📌 Fim de Ciclo</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; // vista ?>

        <!-- GUIA DE LEITURA -->
        <div class="sige-guide">
            <span><strong>AC/ACP</strong> avaliações contínuas</span>
            <span><strong>AT</strong> avaliação trimestral</span>
            <span><strong>MT</strong> média do trimestre</span>
            <span><strong>MFD</strong> média final da disciplina</span>
            <?php if ($pf_mode && $eh_fim_ciclo && $exige_exame): ?>
            <span><strong><?= esc_html($label_nota_final) ?></strong> nota final ponderada com <?= esc_html($label_avaliacao_final) ?></span>
            <?php endif; ?>
            <span><strong>★</strong> disciplina nuclear &nbsp; <strong>○</strong> disciplina auxiliar</span>
        </div>

        <?php endif; // boletim não vazio ?>
    </div>

    <!-- HISTÓRICO -->
    <?php if ($modo === 'historico' && !empty($historico)): ?>
    <div class="sige-hist-panel">
        <div class="sige-hist-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Histórico Escolar - <?= esc_html($aluno->nome_completo) ?>
        </div>
        <div class="sige-u-oxa">
        <table class="sige-hist">
            <thead><tr><th>Ano</th><th>Turma</th><th>Classe</th><th>Estado</th><th>Situação</th><th>Acções</th></tr></thead>
            <tbody>
            <?php foreach ($historico as $h):
                $pf_h = (bool) (function_exists("sige_is_pauta_final_mode") ? sige_is_pauta_final_mode($h->ano_lectivo, $h->turma_id) : get_option("sige_pauta_final_mode_{$escola_id}_{$h->ano_lectivo}_{$h->turma_id}", 0));
                $sit_h = null;
                if (function_exists('sige_calcular_situacao_final') && $h->turma_id) {
                    $sit_h = sige_calcular_situacao_final($aluno_id, $h->turma_id, $h->ano_lectivo, $pf_h);
                }
                $sl = strtolower($sit_h['situacao'] ?? '');
                $url_hn = $base_url . '&aluno_id=' . $aluno_id . '&turma_id=' . $h->turma_id . '&ano_bol=' . urlencode($h->ano_lectivo) . '&vista_ap=anual';
            ?>
            <tr>
                <td><strong><?= esc_html($h->ano_lectivo) ?></strong></td>
                <td><?= esc_html($h->nome_turma ?? '-') ?></td>
                <td><?= esc_html($h->classe ?? '-') ?></td>
                <td><?= esc_html(ucfirst($h->status_matricula ?? '-')) ?></td>
                <td><?php if ($sl): ?><span class="sige-badge-mini <?= $sl ?>"><?= strtoupper($sl) ?></span><?php else: ?>-<?php endif; ?></td>
                <td><a href="<?= esc_url($url_hn) ?>" style="color:var(--color-info-700);font-weight:600;text-decoration:none">Ver notas →</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif (!$search_q): ?>
    <!-- EMPTY STATE -->
    <div class="sige-empty">
        <div class="sige-empty-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
        <h3>Pesquise um aluno</h3>
        <p>Use o campo acima para localizar o aluno pelo nome ou número de processo.</p>
        <p style="font-size:.82rem;margin-top:10px">O aproveitamento mostra notas por trimestre, média por disciplina e situação final.</p>
    </div>
    <?php endif; ?>

    <div class="sige-ft"><?= esc_html($_escola_nome) ?> · Aproveitamento do aluno</div>
</div>

<?php if ($aluno && !empty($boletim)): ?>
<script <?php echo sige_csp_script_attr(); ?>>
/* ═══ IMPRESSÃO POPUP (consistente com DEC/Pauta Final) ═══ */
function apPrint(){
    var apDocPrimary = <?= wp_json_encode($ap_doc_primary) ?>;
    var apDocPrimaryDark = <?= wp_json_encode($ap_doc_primary_700) ?>;
    var tbl = document.getElementById('<?= $vista_ap === 'anual' ? 'ap-t-anual' : 'ap-t-trim' ?>');
    if(!tbl){sigeUi.toast('Não foi possível preparar a impressão (tabela não encontrada). Recarregue a página e tente novamente.', 'erro');return;}

    var h = '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Aproveitamento - <?= esc_js($aluno->nome_completo) ?></title><style><?php echo sige_utilities_inline_css(); ?>'
        + '*{margin:0;padding:0;box-sizing:border-box}'
        + 'body{font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#000;background:#fff;padding:10mm}'
        + '.hd{text-align:center;border-bottom:2pt solid '+apDocPrimary+';padding-bottom:8pt;margin-bottom:12pt}'
        + '.hd h1{font-size:14pt;color:'+apDocPrimary+';letter-spacing:.5pt}'
        + '.hd h2{font-size:10.5pt;font-weight:normal;color:#333;margin-top:2pt}'
        + '.hd h3{font-size:9.5pt;font-weight:600;color:'+apDocPrimaryDark+';margin-top:4pt}'
        + '.ficha{display:grid;grid-template-columns:1fr 1fr 1fr;gap:3pt;border:.8pt solid #999;padding:8pt;border-radius:3pt;margin-bottom:10pt;font-size:8pt}'
        + '.ficha-item strong{color:'+apDocPrimary+';display:inline-block;min-width:68pt}'
        + 'table{width:100%;border-collapse:collapse;font-size:8pt;margin-bottom:8pt}'
        + 'th{background:'+apDocPrimary+';color:#fff;padding:5pt;border:.5pt solid '+apDocPrimary+';text-align:center;font-size:7.5pt}'
        + 'thead tr:nth-child(2) th{background:'+apDocPrimaryDark+';font-size:7pt}'
        + 'td{padding:3.5pt 4pt;border:.5pt solid #bbb;text-align:center}'
        + 'td.tl{text-align:left;font-weight:700;padding-left:8pt}'
        + 'td.sig{font-size:7pt;color:#555}'
        + '.med{background:#e8f5e9;font-weight:700;color:#17633a}'
        + '.mt{background:#e8eaf6;font-weight:700;color:#184b7c}'
        + '.mfd{background:#fff3e0;font-weight:700;color:#e65100}'
        + '.nf{background:#fce4ec;font-weight:700;color:#880e4f}'
        + '.neg{color:#c00;font-weight:800}'
        + 'tfoot td{background:#f1f5f9;font-weight:700;color:'+apDocPrimary+';border-top:1.5pt solid '+apDocPrimary+'}'
        + 'tr:nth-child(even) td{background:#fafbfc}'
        + '.sit{margin-top:10pt;padding:8pt 12pt;border:1.5pt solid '+apDocPrimary+';border-radius:4pt;text-align:center;font-size:10pt;font-weight:700}'
        + '.sit.progride{background:#e8f5e9;border-color:#2e7d32;color:#1b5e20}'
        + '.sit.transita{background:#e3f2fd;border-color:#1565c0;color:#0d47a1}'
        + '.sit.reprova{background:#ffebee;border-color:#c62828;color:#b71c1c}'
        + '.frm{font-size:7pt;color:#666;margin-top:6pt;padding:4pt 8pt;background:#f8f8f8;border-left:2pt solid '+apDocPrimary+'}'
        + '.inst-block{margin-top:12pt;border:1pt solid #999;padding:8pt;border-radius:3pt;page-break-inside:avoid}'
        + '.inst-title{font-size:9pt;font-weight:800;text-transform:uppercase;color:'+apDocPrimary+';margin-bottom:5pt;border-bottom:.5pt solid #bbb;padding-bottom:3pt}'
        + '.obs-line{border-bottom:.5pt solid #555;height:16pt;margin-top:4pt}'
        + '.result-grid{display:grid;grid-template-columns:1fr 1fr;gap:8pt;margin-top:5pt;font-size:8pt}'
        + '.blank{display:inline-block;border-bottom:.5pt solid #444;min-width:110pt;height:12pt;vertical-align:bottom}'
        + '.sign-row{display:grid;grid-template-columns:1fr 1fr;gap:18pt;margin-top:18pt;text-align:center;font-size:8pt;page-break-inside:avoid}'
        + '.sign-line{border-top:.7pt solid #333;padding-top:3pt}'
        + '.scale{margin-top:8pt;font-size:7pt;color:#444;background:#f8fafc;border:.5pt solid #ddd;padding:5pt;border-radius:3pt}'
        + '.fg{margin-top:10pt;text-align:center;font-size:7pt;color:#666;border-top:.5pt solid #ccc;padding-top:5pt}'
        + '@page{size:A4 portrait;margin:10mm}'
        + '</style></head><body>'
        + '<div class="hd">'
        + '<h1>' + <?= wp_json_encode(mb_strtoupper($_escola_nome)) ?> + '</h1>'
        + '<h2>' + <?= wp_json_encode($vista_ap === 'anual' ? 'Aproveitamento do Aluno - Ano Lectivo ' . (string)$ano_sel : 'Aproveitamento Trimestral - Ano Lectivo ' . (string)$ano_sel) ?> + '</h2>'
        + '<h3>' + <?= wp_json_encode($vistas_label[$vista_ap]) ?> + '</h3>'
        + '</div>'
        + '<div class="ficha">'
        + '<div class="ficha-item"><strong>Nome:</strong> ' + <?= wp_json_encode($aluno->nome_completo) ?> + '</div>'
        + '<div class="ficha-item"><strong>Nº Processo:</strong> ' + <?= wp_json_encode($aluno->numero_processo ?: '-') ?> + '</div>'
        + '<div class="ficha-item"><strong>Género:</strong> ' + <?= wp_json_encode($aluno->genero ?: '-') ?> + '</div>'
        + '<div class="ficha-item"><strong>Classe:</strong> ' + <?= wp_json_encode($turma->classe ?? '-') ?> + '</div>'
        + '<div class="ficha-item"><strong>Turma:</strong> ' + <?= wp_json_encode($turma ? ($turma->nome ?: ($turma->nome_turma ?? '-')) : '-') ?> + '</div>'
        + '<div class="ficha-item"><strong>Turno:</strong> ' + <?= wp_json_encode($turma->turno ?? '-') ?> + '</div>'
        + '</div>';

    // Clone da tabela
    var ct = tbl.cloneNode(true);
    ct.removeAttribute('id');
    ct.removeAttribute('class');
    // Remover escalas (NS/S/B/MB/E spans) para reduzir visual no print
    ct.querySelectorAll('.sige-nt-escala').forEach(function(e){e.remove();});
    // Mapear classes CSS de cor para equivalentes compactos
    ct.querySelectorAll('.sige-nt-med').forEach(function(e){e.className='med';});
    ct.querySelectorAll('.sige-nt-mt').forEach(function(e){e.className='mt';});
    ct.querySelectorAll('.sige-nt-mfd').forEach(function(e){e.className='mfd';});
    ct.querySelectorAll('.sige-nt-nf').forEach(function(e){e.className='nf';});
    ct.querySelectorAll('.sige-nt-neg').forEach(function(e){if(!e.classList.contains('mt')&&!e.classList.contains('mfd')&&!e.classList.contains('nf')){e.className='neg';}else{e.className+=' neg';}});
    ct.querySelectorAll('.td-disc').forEach(function(e){e.className='tl';});
    ct.querySelectorAll('.td-sig').forEach(function(e){e.className='sig';});
    // Regra null=0: preserva "0" em células brutas vazias (não converte para traço)
    ct.querySelectorAll('.sige-nt-vazio').forEach(function(e){e.textContent='0';});
    h += ct.outerHTML;

    <?php if ($vista_ap === 'anual' && $sit_final):
        $s = strtolower($sit_final['situacao'] ?? '');
        // Label REPROVA depende da classe (fim de ciclo vs intermédia)
        $ls = [
            'progride' => 'PROGRIDE',
            'transita' => 'TRANSITA',
            'reprova'  => $eh_fim_ciclo ? 'NÃO TRANSITA' : 'NÃO PROGRIDE',
        ];
        $sit_txt = $ls[$s] ?? strtoupper($s);
    ?>
    h += '<div class="sit <?= esc_js($s) ?>">SITUAÇÃO FINAL: <?= esc_js($sit_txt) ?>'
        + <?= isset($sit_final['media_global']) ? wp_json_encode(' - Média Global: ' . (int) $sit_final['media_global']) : "''" ?>
        + <?= isset($sit_final['negativas']) ? wp_json_encode(' - Negativas: ' . (int) $sit_final['negativas']) : "''" ?>
        + '</div>';
    <?php endif; ?>

    h += '<div class="inst-block">'
        + '<div class="inst-title">Resultado Final / Observações</div>'
        + '<div class="obs-line">Observação: </div><div class="obs-line"></div>'
        + '<div class="result-grid"><div>Média Final: <span class="blank"></span> Valores</div><div>Situação Final: <span class="blank"></span></div></div>'
        + '</div>'
        + '<div class="sign-row"><div class="sign-line">O (A) Encarregado (a) de Educação</div><div class="sign-line">O (A) Director (a) de Turma</div></div>'
        + '<div class="scale"><strong>Escala qualitativa:</strong> 19-20 Excelente (E) · 17-18 Muito Bom (MB) · 14-16 Bom (B) · 10-13 Satisfatório (S) · 0-9 Não Satisfatório (NS).</div>'
        + '<div class="frm">Critérios de leitura: AC e ACP são avaliações contínuas; AT representa a avaliação trimestral; MFD representa a média final da disciplina.'
        + <?= ($pf_mode && $eh_fim_ciclo && $exige_exame) ? wp_json_encode(' · ' . $label_nota_final . '=(MFD×' . $peso_mfd . '% + ' . $label_avaliacao_final . '×' . $peso_ex . '%)') : "''" ?>
        + '</div>'
        + '<div class="fg">SIGE SoftGenial · ' + <?= wp_json_encode($_escola_nome) ?> + ' · Gerado em ' + new Date().toLocaleDateString('pt-MZ') + '</div>'
        + '</body></html>';

    var w = window.open('', '_blank', 'width=1000,height=700,scrollbars=yes');
    if(!w){sigeUi.toast('O navegador bloqueou a janela de impressão. Permita popups para este site e tente novamente.', 'aviso');return;}
    w.document.write(h);
    w.document.close();
    setTimeout(function(){w.focus();w.print();}, 400);
}
</script>
<?php endif; ?>
