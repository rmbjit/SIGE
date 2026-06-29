<?php



if (!defined('ABSPATH')) exit;



// [FIX D03] Removido date_default_timezone_set() - usa DateTimeZone explícito

$sige_tz_mz = new DateTimeZone('Africa/Maputo');

$sige_now_mz = new DateTime('now', $sige_tz_mz);



global $wpdb;



if (!sige_page_guard(
    ['academico.pautas_ver','academico.pautas_emitir'],
    ['sige_professor','sige_pedagogico','sige_secretario','sige_director']
)) return;



// ─── FUNÇÕES LOCAIS ───────────────────────────────────────────



if (!function_exists('sige_badge_situacao_final')) {



    function sige_badge_situacao_final($sit, $eh_fim_ciclo = false) {



        $sit_raw = strtoupper(trim((string)$sit));



        $label = function_exists('sige_situacao_final_rotulo_publico') ? sige_situacao_final_rotulo_publico($sit_raw, (bool)$eh_fim_ciclo) : $sit_raw;



        $map_key = in_array($sit_raw, ['REPROVA','REPROVADO','REPROVADA','NÃO PROGRIDE','NAO PROGRIDE','NÃO TRANSITA','NAO TRANSITA'], true) ? 'REPROVA' : $sit_raw;



        $map = [



            'PROGRIDE' => ['var(--color-success-500)','#fff'],



            'TRANSITA' => ['var(--color-warning-500)','#111'],



            'REPROVA'  => ['var(--color-danger-600)','#fff'],
            'PENDENTE' => ['var(--color-slate-500)','#fff'],



        ];



        $c = $map[$map_key] ?? ['var(--color-slate-500)','#fff'];



        return '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-weight:800;font-size:11px;background:'.$c[0].';color:'.$c[1].';white-space:nowrap;">'.esc_html($label).'</span>';



    }



}



if (!function_exists('sige_label_turma_pauta')) {



    function sige_label_turma_pauta($t) {



        $nome = !empty($t->nome_turma) ? $t->nome_turma : (!empty($t->nome) ? $t->nome : '');



        $cn = (int) preg_replace('/\D+/', '', (string)($t->classe ?? ''));



        $cl = $cn ? ($cn.'ª') : ($t->classe ?? '-');



        $turno = !empty($t->turno) ? (' • '.$t->turno) : '';



        return trim($cl.' - '.$nome.$turno);



    }



}



if (!function_exists('sige_sne_escala')) {



    function sige_sne_escala($nota) {



        if ($nota === null) return '';



        $n = (float)$nota;



        if ($n < 10)  return 'NS';



        if ($n < 14)  return 'S';



        if ($n < 17)  return 'B';



        if ($n < 19)  return 'MB';



        return 'E';



    }



}



// ─── PARÂMETROS ────────────────────────────────────────────────



$p = $wpdb->prefix;
// [MT-04] Multi-tenancy
$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;



$ano_default = function_exists('sige_get_ano_lectivo_atual') ? sige_get_ano_lectivo_atual() : (int)$sige_now_mz->format('Y');



$ano_lectivo = isset($_GET['ano_lectivo']) ? (int)$_GET['ano_lectivo'] : $ano_default;



$turma_id    = isset($_GET['turma_id'])    ? (int)$_GET['turma_id']    : 0;



$trimestre   = isset($_GET['trimestre'])   ? (int)$_GET['trimestre']   : 0; // 0 = pauta final (todos)
if ($trimestre < 0 || $trimestre > 3) {
    $trimestre = 0;
}



$pauta_final = !empty($_GET['pauta_final_mode']) ? 1 : 0;



// ─── TABELAS ──────────────────────────────────────────────────



$tTurmas     = $p.'sige_turmas';



$tMatriculas = $p.'sige_matriculas';



$tAlunos     = $p.'sige_alunos';



$tNotas      = $p.'sige_notas';



$tRegras     = $p.'sige_regras_academicas';



$tDisc       = $p.'sige_disciplinas';



$tMatriz     = $p.'sige_matriz_curricular';



$tTD         = $p.'sige_turma_disciplinas';



// ─── TURMAS DISPONÍVEIS ───────────────────────────────────────

// [FIX R-03] Professor só vê turmas atribuídas; supervisores vêm tudo

$_is_admin_p = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));

$_is_supervisor_p = $_is_admin_p || current_user_can('sige_pedagogico') || current_user_can('sige_secretario') || current_user_can('sige_director');
$_prof_id = 0;



if ($_is_supervisor_p) {

    $turmas = $wpdb->get_results($wpdb->prepare("

        SELECT id, nome_turma, nome, classe, turno, status_turma

        FROM {$tTurmas}

        WHERE escola_id = %d AND (status_turma IS NULL OR status_turma IN ('activa','ativa'))

        ORDER BY CAST(classe AS UNSIGNED) ASC, nome_turma ASC, nome ASC

    ", $eid));

} else {

    // Professor: buscar turmas via director_turma_id ou turma_disciplinas

    // [FIX D02] Identificação centralizada - usermeta sige_professor_id → fallback email
    $_prof_row = function_exists('sige_get_professor_atual') ? sige_get_professor_atual() : null;

    $_prof_id = $_prof_row ? (int)$_prof_row->id : 0;



    if ($_prof_id) {

        $turmas = $wpdb->get_results($wpdb->prepare("

            SELECT DISTINCT t.id, t.nome_turma, t.nome, t.classe, t.turno, t.status_turma

            FROM {$tTurmas} t

            WHERE t.escola_id = %d AND (t.status_turma IS NULL OR t.status_turma IN ('activa','ativa'))

              AND (t.director_turma_id = %d

                   OR t.id IN (SELECT turma_id FROM {$tTD} WHERE professor_id = %d))

            ORDER BY CAST(t.classe AS UNSIGNED) ASC, t.nome_turma ASC, t.nome ASC

        ", $eid, $_prof_id, $_prof_id));

    } else {

        $turmas = [];

    }

}



// ─── TURMA SELECCIONADA ───────────────────────────────────────



$turma = null;



$classe_num = 0;



if ($turma_id) {



    $turma = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tTurmas} WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id, $eid));



    if ($turma) $classe_num = (int) preg_replace('/\D+/', '', (string)($turma->classe ?? ''));



    // [V7.2.5] Professor não pode abrir pauta de turma não atribuída via URL manual.
    if ($turma && function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
        $_prof_id_scope = function_exists('sige_get_professor_atual_id') ? sige_get_professor_atual_id() : 0;
        if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, $_prof_id_scope, $eid)) {
            $turma = null;
            $turma_id = 0;
            $classe_num = 0;
            if (function_exists('sige_render_teacher_scope_denied')) {
                sige_render_teacher_scope_denied('Esta pauta pertence a uma turma que não está atribuída ao seu perfil de professor.');
            }
        }
    }



}



// ─── MODO PAUTA FINAL (persistente) ──────────────────────────



if ($turma_id > 0 && isset($_GET['pauta_final_mode']) && function_exists('sige_set_pauta_final_mode')) {



    sige_set_pauta_final_mode($ano_lectivo, $turma_id, (int)$_GET['pauta_final_mode'] === 1);



}



$pauta_final_mode = ($turma_id > 0 && function_exists('sige_is_pauta_final_mode'))



    ? (sige_is_pauta_final_mode($ano_lectivo, $turma_id) ? 1 : 0)



    : $pauta_final;



// ─── REGRAS ACADÉMICAS ────────────────────────────────────────



$regra = ['eh_fim_ciclo'=>0,'peso_mfd'=>60,'peso_exame'=>40,'exige_exame'=>0,



          'nota_minima_aprovacao'=>10,'max_negativas_transita'=>2,'max_negativas_progride'=>0];



if ($turma_id && $classe_num > 0) {



    $r = $wpdb->get_row($wpdb->prepare(



        "SELECT * FROM {$tRegras} WHERE ano_lectivo=%d AND classe_num=%d AND escola_id=%d ORDER BY id DESC LIMIT 1",



        $ano_lectivo, $classe_num, $eid



    ), ARRAY_A);



    if ($r) $regra = array_merge($regra, $r);



}



$eh_fim_ciclo = (int)($regra['eh_fim_ciclo'] ?? 0) === 1;



// ─── DISCIPLINAS DA TURMA ─────────────────────────────────────



$disciplinas = [];

// [V97] Inicialização defensiva: evita warning quando a página é aberta sem turma válida.
// A lógica existente continua igual; apenas garantimos que a variável existe antes dos filtros/sorters posteriores.
$classe_turma = '';



if ($turma_id && $turma) {



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



         FROM {$tDisc} d



         LEFT JOIN {$tMatriz} mc



             ON mc.disciplina_id = d.id AND mc.classe = %s



         WHERE d.id IN (



             SELECT disciplina_id FROM {$tTD} WHERE turma_id = %d AND escola_id = %d



             UNION



             SELECT disciplina_id FROM {$tMatriz} WHERE classe = %s AND escola_id = %d



         )



         ORDER BY ordem, d.nome",



        $classe_turma, $turma_id, $eid, $classe_turma, $eid



    ));



}

// [T5] Categoria vem da matriz_curricular (varia por classe), não de sige_disciplinas
if (!empty($classe_turma) && function_exists('sige_get_categoria_by_matriz')) { foreach ($disciplinas as &$_d) { if (isset($_d->id)) $_d->categoria = sige_get_categoria_by_matriz($classe_turma, $_d->id, $eid); } unset($_d); }
// [MALISA-V2 sort guard] Ordem/categoria oficiais Malisa para 1.ª-6.ª.
if (function_exists('sige_apply_categoria_oficial_disciplinas')) { $disciplinas = sige_apply_categoria_oficial_disciplinas($disciplinas, $classe_turma); }
if (function_exists('sige_sort_disciplinas_oficial')) { $disciplinas = sige_sort_disciplinas_oficial($disciplinas); }

// Separar nucleares das auxiliares (para cálculo da MG)



$disc_nucleares_ids = [];



foreach ($disciplinas as $d) {



    if (strtolower($d->categoria ?? '') === 'nuclear') {



        $disc_nucleares_ids[] = (int)$d->id;



    }



}



// ─── ALUNOS ───────────────────────────────────────────────────



$alunos = [];



$notas_map = []; // [aluno_id][disc_id][trimestre] => row



$gerar = ($turma_id > 0 && $turma && !empty($disciplinas));



if ($gerar) {



    $alunos = $wpdb->get_results($wpdb->prepare("



        SELECT a.id, a.nome_completo, a.genero, a.numero_processo



        FROM {$tMatriculas} m



        INNER JOIN {$tAlunos} a ON a.id = m.aluno_id



        WHERE m.turma_id = %d AND m.ano_lectivo = %d AND m.escola_id = %d



          AND (m.status_matricula IS NULL OR m.status_matricula IN ('activa','ativa'))



        ORDER BY a.nome_completo ASC



    ", $turma_id, $ano_lectivo, $eid));



    if (!empty($alunos)) {



        $ids = array_map(function($o){ return (int)$o->id; }, $alunos);



        $place = implode(',', array_fill(0, count($ids), '%d'));



        $params = array_merge($ids, [$turma_id, $ano_lectivo, $eid]);



        $rows = $wpdb->get_results($wpdb->prepare("



            SELECT aluno_id, disciplina_id, trimestre, nota_ac, nota_acp, nota_exame, nota_conselho



            FROM {$tNotas}



            WHERE aluno_id IN ({$place}) AND turma_id = %d AND ano_lectivo = %d AND escola_id = %d

              AND (status IS NULL OR status = 'aprovado')



        ", $params));



        foreach ($rows as $r) {



            $aid = (int)$r->aluno_id;



            $did = (int)$r->disciplina_id;



            $tri = (int)$r->trimestre;



            if ($tri < 1 || $tri > 3) continue;



            $notas_map[$aid][$did][$tri] = $r;



        }



    }



}



// ─── FUNÇÃO: calcular MT de uma row ──────────────────────────



function sige_pauta_mt($row) {



    // Nota Votada aprovada pelo Director Pedagógico prevalece como MT oficial.
    if ($row && isset($row->nota_conselho) && $row->nota_conselho !== null && $row->nota_conselho !== '' && is_numeric($row->nota_conselho)) {
        return (int) round((float)$row->nota_conselho, 0);
    }

    // Alinhado ao Aproveitamento do Aluno: valores ausentes contam como 0.
    $ac  = ($row && is_numeric($row->nota_ac))    ? (float)$row->nota_ac    : 0.0;



    $acp = ($row && is_numeric($row->nota_acp))   ? (float)$row->nota_acp   : 0.0;



    $at  = ($row && is_numeric($row->nota_exame)) ? (float)$row->nota_exame : 0.0;



    $med = ($ac + $acp) / 2;



    return (int) round((2 * $med + $at) / 3, 0);



}



?>



<div class="wrap sige-pautas-page">



<style>

/* ==========================================================================
   SIGE SoftGenial v12.10.67 - Pautas: Compliance Visual Integral
   Referência mandatória: Painel Principal / Dashboard V2 MJS-grade.
   Escopo: camada visual e saneamento técnico mínimo de query duplicada.
   Não altera fórmulas, médias, regras de transição/progressão, permissões,
   exportação PDF/Excel, dados académicos, pautas finais, boletins ou DEC.
   ========================================================================== */
body.sige-view-pautas .sg-product-page-head{display:none!important;}
body.sige-view-pautas .sg-app-page{padding-top:0!important;}
body.sige-view-pautas .sg-app-content{padding-left:30px!important;padding-right:30px!important;}
body.sige-view-pautas .sige-pautas-page{
    --pautas-blue:var(--color-info-700);
    --pautas-blue-dark:var(--color-info-800);
    --pautas-purple:var(--color-brand-500);
    --pautas-purple-soft:var(--color-brand-50);
    --pautas-ink:var(--color-black);
    --pautas-muted:var(--color-slate-700);
    --pautas-line:var(--color-ink-100);
    --pautas-green:var(--color-success-500);
    --pautas-red:var(--color-danger-500);
    --pautas-amber:var(--color-warning-500);
    width:100%!important;
    max-width:none!important;
    margin:0!important;
    padding:0 0 28px!important;
    color:var(--pautas-ink)!important;
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif)!important;
}
body.sige-view-pautas .sige-pautas-page *{box-sizing:border-box!important;}
body.sige-view-pautas .sige-pautas-page svg{stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;display:block!important;}

/* HERO - padrão claro do Painel Principal */
body.sige-view-pautas .sige-pautas-hero{
    position:relative!important;
    overflow:hidden!important;
    min-height:178px!important;
    border-radius:var(--radius-xl)!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%)!important;
    border:1px solid rgba(92,64,187,.12)!important;
    box-shadow:var(--shadow-lg);
    padding:32px 34px!important;
    margin:0 0 18px!important;
    display:grid!important;
    grid-template-columns:minmax(0,1.04fr) minmax(340px,.96fr)!important;
    gap:22px!important;
    align-items:center!important;
    color:var(--pautas-ink)!important;
}
body.sige-view-pautas .sige-pautas-hero:before{
    content:""!important;position:absolute!important;inset:auto -80px -130px auto!important;width:420px!important;height:300px!important;border-radius:var(--radius-pill)!important;background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%)!important;pointer-events:none!important;
}
body.sige-view-pautas .sige-pautas-hero:after{display:none!important;content:none!important;}
body.sige-view-pautas .sige-pautas-hero-main,body.sige-view-pautas .sige-pautas-hero-panel{position:relative!important;z-index:1!important;}
body.sige-view-pautas .sige-pautas-kicker{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;margin:0 0 10px!important;color:var(--pautas-blue)!important;font-size:12px!important;line-height:1.2!important;font-weight:700!important;letter-spacing:.11em!important;text-transform:uppercase!important;}
body.sige-view-pautas .sige-pautas-kicker svg{width:18px!important;height:18px!important;}
body.sige-view-pautas .sige-pautas-hero h1{margin:0!important;max-width:650px!important;color:var(--color-black)!important;font-size:31px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.04em!important;font-family:inherit!important;}
body.sige-view-pautas .sige-pautas-hero p{max-width:650px!important;margin:var(--space-3) 0 0!important;color:var(--color-slate-700)!important;font-size:15px!important;line-height:1.65!important;font-weight:500!important;}
body.sige-view-pautas .sige-pautas-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:var(--space-3)!important;margin-top:24px!important;}
body.sige-view-pautas .sige-pautas-hero-btn{min-height:46px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;border-radius:var(--radius-md)!important;padding:0 18px!important;font-size:var(--fs-sm)!important;font-weight:700!important;text-decoration:none!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important;color:var(--color-ink-800)!important;box-shadow:var(--shadow-sm);}
body.sige-view-pautas .sige-pautas-hero-btn svg{width:17px!important;height:17px!important;}
body.sige-view-pautas .sige-pautas-hero-btn-primary{background:linear-gradient(135deg,var(--pautas-blue),var(--pautas-blue-dark))!important;color:var(--color-white)!important;border-color:transparent!important;box-shadow:var(--shadow-md);}
body.sige-view-pautas .sige-pautas-hero-panel{padding:var(--space-5)!important;border-radius:var(--radius-xl)!important;background:rgba(255,255,255,.82)!important;border:1px solid rgba(92,64,187,.10)!important;box-shadow:var(--shadow-md);}
body.sige-view-pautas .sige-pautas-panel-label{display:flex!important;align-items:center!important;gap:var(--space-2)!important;color:var(--pautas-blue)!important;font-size:12px!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.09em!important;}
body.sige-view-pautas .sige-pautas-panel-label svg{width:18px!important;height:18px!important;}
body.sige-view-pautas .sige-pautas-panel-number{margin-top:13px!important;color:var(--color-black)!important;font-size:34px!important;line-height:1!important;font-weight:700!important;letter-spacing:-.05em!important;}
body.sige-view-pautas .sige-pautas-panel-text{margin:10px 0 0!important;color:var(--color-slate-700)!important;font-size:var(--fs-sm)!important;line-height:1.55!important;font-weight:600!important;}
body.sige-view-pautas .sige-pautas-panel-track{height:10px!important;margin-top:16px!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-100)!important;overflow:hidden!important;}
body.sige-view-pautas .sige-pautas-panel-track span{display:block!important;height:100%!important;border-radius:var(--radius-pill)!important;background:linear-gradient(135deg,var(--pautas-blue),var(--pautas-purple))!important;}

/* Filtros e acções */
body.sige-view-pautas .sige-filter-shell{background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;padding:18px 20px!important;border-radius:var(--radius-xl)!important;display:flex!important;gap:14px!important;align-items:flex-end!important;flex-wrap:wrap!important;margin-bottom:18px!important;box-shadow:var(--shadow-md);}
body.sige-view-pautas .sige-filter-shell label{display:block!important;margin-bottom:7px!important;color:var(--color-slate-700)!important;font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.09em!important;}
body.sige-view-pautas .sige-filter-shell input[type=number],body.sige-view-pautas .sige-filter-shell select{height:52px!important;padding:0 15px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;font-size:var(--fs-base)!important;font-weight:600!important;box-shadow:var(--shadow-sm);}
body.sige-view-pautas .sige-filter-shell input[type=number]:focus,body.sige-view-pautas .sige-filter-shell select:focus{outline:none!important;border-color:rgba(90,63,214,.55)!important;box-shadow:var(--shadow-xs);}
body.sige-view-pautas .sige-filter-chip{display:flex!important;align-items:center!important;gap:var(--space-2)!important;padding:0 14px!important;border:1px solid var(--color-slate-100)!important;border-radius:var(--radius-lg)!important;background:var(--color-slate-50)!important;min-height:52px!important;}
body.sige-view-pautas .sige-filter-chip label{margin:0!important;font-size:var(--fs-sm)!important;font-weight:700!important;text-transform:none!important;letter-spacing:0!important;color:var(--color-ink-800)!important;}
body.sige-view-pautas .sige-app-btn{min-height:52px!important;height:52px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;padding:0 var(--space-5)!important;border-radius:var(--radius-lg)!important;font-weight:700!important;box-shadow:var(--shadow-sm);border:1px solid transparent!important;text-decoration:none!important;line-height:1!important;}
body.sige-view-pautas .sige-app-btn svg{width:18px!important;height:18px!important;flex:0 0 auto!important;}
body.sige-view-pautas .sige-app-btn.alt-red{background:linear-gradient(135deg,var(--color-danger-500),var(--color-danger-700))!important;border-color:transparent!important;color:var(--color-white)!important;box-shadow:var(--shadow-sm);}
body.sige-view-pautas .sige-app-btn.alt-green{background:linear-gradient(135deg,var(--color-success-500),var(--color-success-900))!important;border-color:transparent!important;color:var(--color-white)!important;box-shadow:var(--shadow-sm);}

/* Alertas e contexto */
body.sige-view-pautas .sige-pautas-alert{display:flex!important;align-items:flex-start!important;gap:var(--space-3)!important;margin:var(--space-3) 0 var(--space-4)!important;padding:14px 16px!important;border-radius:var(--radius-lg)!important;background:var(--color-warning-50)!important;border:1px solid var(--color-warning-200)!important;color:var(--color-warning-800)!important;box-shadow:var(--shadow-sm);}
body.sige-view-pautas .sige-pautas-alert svg{width:18px!important;height:18px!important;flex:0 0 auto!important;margin-top:1px!important;}
body.sige-view-pautas .sige-pautas-alert strong{display:block!important;margin-bottom:4px!important;color:inherit!important;}
body.sige-view-pautas .sige-pautas-alert p{margin:0!important;color:inherit!important;font-size:var(--fs-sm)!important;line-height:1.5!important;font-weight:600!important;}
body.sige-view-pautas .sige-pautas-context{margin:0 0 var(--space-4)!important;display:flex!important;justify-content:space-between!important;align-items:center!important;flex-wrap:wrap!important;gap:var(--space-3)!important;padding:16px 18px!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;color:var(--color-ink-500)!important;box-shadow:var(--shadow-md);}
body.sige-view-pautas .sige-pautas-context-main{display:flex!important;align-items:center!important;gap:10px!important;flex-wrap:wrap!important;font-size:var(--fs-sm)!important;font-weight:600!important;}
body.sige-view-pautas .sige-pautas-context-item{display:inline-flex!important;align-items:center!important;gap:7px!important;padding:7px 10px!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-slate-800)!important;font-size:12px!important;font-weight:700!important;}
body.sige-view-pautas .sige-pautas-context-item svg{width:16px!important;height:16px!important;}
body.sige-view-pautas .sige-pautas-context-note{font-size:12px!important;color:var(--color-slate-600)!important;font-weight:600!important;}

/* Pauta/tabela - ecrã */
body.sige-view-pautas .sige-pauta-mono{background:var(--color-white)!important;padding:18px!important;border:1px solid rgba(28,32,54,.08)!important;border-radius:var(--radius-xl)!important;overflow-x:auto!important;box-shadow:var(--shadow-md);}
body.sige-view-pautas .pauta-inst-header{padding:var(--space-1) 0 var(--space-3)!important;text-align:center!important;margin-bottom:10px!important;border-bottom:1px solid var(--color-slate-100)!important;}
body.sige-view-pautas .pauta-inst-header h3{margin:2px 0!important;font-size:var(--fs-sm)!important;text-transform:uppercase!important;color:var(--color-info-700)!important;font-weight:700!important;letter-spacing:.04em!important;}
body.sige-view-pautas .pauta-inst-header h4{margin:var(--space-1) 0 0!important;font-size:var(--fs-sm)!important;color:var(--color-ink-800)!important;font-weight:700!important;}
body.sige-view-pautas .pauta-meta{display:flex!important;justify-content:space-between!important;gap:var(--space-3)!important;flex-wrap:wrap!important;border:1px solid var(--color-slate-100)!important;padding:12px 14px!important;border-radius:var(--radius-lg)!important;background:var(--color-slate-50)!important;font-size:12px!important;margin-bottom:12px!important;color:var(--color-ink-800)!important;}
body.sige-view-pautas table.mono{border-collapse:separate!important;border-spacing:0!important;width:100%!important;font-size:var(--fs-xs)!important;background:var(--color-white)!important;border-radius:var(--radius-lg)!important;overflow:hidden!important;}
body.sige-view-pautas table.mono th,body.sige-view-pautas table.mono td{border-right:1px solid var(--color-slate-100)!important;border-bottom:1px solid var(--color-slate-100)!important;padding:6px 7px!important;text-align:center!important;white-space:nowrap!important;}
body.sige-view-pautas table.mono thead tr.tr-disc th{background:var(--color-info-700)!important;color:var(--color-white)!important;font-size:10px!important;font-weight:700!important;padding:9px 7px!important;}
body.sige-view-pautas table.mono thead tr.tr-disc th.nuclear{background:var(--color-info-800)!important;color:var(--color-white)!important;}
body.sige-view-pautas table.mono thead tr.tr-disc th.auxiliar{background:var(--color-slate-800)!important;color:var(--color-white)!important;}
body.sige-view-pautas table.mono thead tr.tr-trim th{background:var(--color-brand-500)!important;color:var(--color-white)!important;font-size:10px!important;font-weight:700!important;}
body.sige-view-pautas table.mono thead tr.tr-sub th{background:var(--color-info-50)!important;color:var(--color-ink-800)!important;font-size:9px!important;font-weight:700!important;}
body.sige-view-pautas th.th-mfd{background:var(--color-warning-800)!important;color:var(--color-white)!important;}
body.sige-view-pautas th.th-esc{background:var(--color-slate-700)!important;color:var(--color-white)!important;}
body.sige-view-pautas th.th-mg{background:var(--color-info-700)!important;color:var(--color-white)!important;font-size:12px!important;}
body.sige-view-pautas th.th-sit{background:var(--color-success-900)!important;color:var(--color-white)!important;font-size:12px!important;}
body.sige-view-pautas th.th-neg{background:var(--color-danger-700)!important;color:var(--color-white)!important;}
body.sige-view-pautas td.td-nome{text-align:left!important;padding-left:10px!important;min-width:170px!important;font-weight:600!important;font-size:var(--fs-xs)!important;color:var(--color-ink-500)!important;}
body.sige-view-pautas td.td-num{font-weight:700!important;color:var(--color-info-700)!important;width:32px!important;}
body.sige-view-pautas td.td-g{width:26px!important;font-size:10px!important;}
body.sige-view-pautas td.td-mt{background:var(--color-info-50)!important;color:var(--color-info-600)!important;font-weight:700!important;}
body.sige-view-pautas td.td-mfd{background:var(--color-warning-50)!important;color:var(--color-warning-800)!important;font-weight:700!important;font-size:12px!important;}
body.sige-view-pautas td.td-esc{background:var(--color-slate-50)!important;color:var(--color-slate-700)!important;font-weight:600!important;font-size:10px!important;}
body.sige-view-pautas td.td-mg{background:var(--color-success-50)!important;color:var(--color-success-900)!important;font-weight:700!important;font-size:var(--fs-sm)!important;}
body.sige-view-pautas td.td-neg{color:var(--color-danger-700)!important;font-weight:700!important;}
body.sige-view-pautas table.mono tbody tr:nth-child(even){background:var(--color-white)!important;}
body.sige-view-pautas table.mono tbody tr:hover{background:var(--color-white)!important;}
body.sige-view-pautas .sige-pautas-legenda{margin-top:14px!important;font-size:var(--fs-xs)!important;color:var(--color-slate-600)!important;display:flex!important;gap:var(--space-3)!important;flex-wrap:wrap!important;padding:0 var(--space-1)!important;}
body.sige-view-pautas .sige-pautas-legenda span{display:inline-flex!important;align-items:center!important;gap:6px!important;padding:6px 9px!important;border-radius:var(--radius-pill)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;box-shadow:var(--shadow-sm);}
body.sige-view-pautas .sige-pautas-signatures{margin-top:30px!important;display:grid!important;grid-template-columns:1fr 1fr 1fr!important;gap:30px!important;font-size:var(--fs-xs)!important;}
body.sige-view-pautas .sige-pautas-signatures div{border-top:1px solid var(--color-slate-900)!important;padding-top:5px!important;text-align:center!important;}

@media(max-width:980px){body.sige-view-pautas .sg-app-content{padding-left:22px!important;padding-right:22px!important;}body.sige-view-pautas .sige-pautas-hero{grid-template-columns:1fr!important;padding:26px 24px!important;}body.sige-view-pautas .sige-filter-shell{display:grid!important;grid-template-columns:1fr!important;}body.sige-view-pautas .sige-filter-shell select,body.sige-view-pautas .sige-filter-shell input[type=number]{width:100%!important;min-width:100%!important;}body.sige-view-pautas .sige-app-btn{width:100%!important;}}
@media(max-width:680px){body.sige-view-pautas .sg-app-content{padding-left:16px!important;padding-right:16px!important;}body.sige-view-pautas .sige-pautas-hero h1{font-size:var(--fs-xl)!important;}body.sige-view-pautas .sige-pautas-hero-actions{display:grid!important;grid-template-columns:1fr!important;}body.sige-view-pautas .sige-pautas-hero-btn{width:100%!important;}body.sige-view-pautas .sige-pautas-signatures{grid-template-columns:1fr!important;}}


/* v12.10.68 - Pautas: Fluxo PRO, botões e estados do módulo */
body.sige-view-pautas .sige-pautas-hero-panel{
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18))!important;
    border:1px solid rgba(92,64,187,.08)!important;
    box-shadow:none!important;
    min-height:148px!important;
    display:flex!important;
    flex-direction:column!important;
    justify-content:center!important;
    overflow:hidden!important;
}
body.sige-view-pautas .sige-pautas-hero-panel:before{
    content:""!important;
    position:absolute!important;
    right:22px!important;
    bottom:16px!important;
    width:112px!important;
    height:92px!important;
    border-radius:22px 22px 12px 12px!important;
    background:rgba(109,93,252,.16)!important;
    box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)!important;
}
body.sige-view-pautas .sige-pautas-hero-panel>*{position:relative!important;z-index:1!important;}
body.sige-view-pautas .sige-pautas-panel-label{color:var(--color-brand-500)!important;}
body.sige-view-pautas .sige-pautas-panel-number{font-size:40px!important;font-weight:700!important;}
body.sige-view-pautas .sige-pautas-panel-track{background:rgba(255,255,255,.7)!important;}
body.sige-view-pautas .sige-pautas-panel-track span{background:linear-gradient(90deg,var(--color-brand-400),var(--color-brand-600))!important;}
body.sige-view-pautas .sige-filter-shell{
    display:grid!important;
    grid-template-columns:minmax(120px,.55fr) minmax(260px,1.35fr) minmax(190px,.8fr) minmax(190px,.8fr) auto auto auto!important;
    align-items:end!important;
}
body.sige-view-pautas .sige-filter-chip{justify-content:center!important;}
body.sige-view-pautas .sige-filter-chip input[type=checkbox]{width:18px!important;height:18px!important;accent-color:var(--color-info-700)!important;}
body.sige-view-pautas .sige-app-btn{transition:transform .18s ease,box-shadow .18s ease,opacity .18s ease!important;}
body.sige-view-pautas .sige-app-btn:hover{transform:translateY(-1px)!important;}
body.sige-view-pautas .sige-app-btn[disabled],body.sige-view-pautas .sige-app-btn.is-loading{opacity:.7!important;cursor:not-allowed!important;transform:none!important;}
body.sige-view-pautas .sige-pautas-empty{
    display:flex!important;
    align-items:flex-start!important;
    gap:14px!important;
    padding:20px 22px!important;
    border-radius:var(--radius-xl)!important;
    background:var(--color-white)!important;
    border:1px solid rgba(28,32,54,.08)!important;
    box-shadow:var(--shadow-md);
    color:var(--color-ink-500)!important;
}
body.sige-view-pautas .sige-pautas-empty-icon{
    width:46px!important;
    height:46px!important;
    border-radius:var(--radius-lg)!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    flex:0 0 auto!important;
    background:var(--color-brand-50)!important;
    color:var(--color-brand-500)!important;
}
body.sige-view-pautas .sige-pautas-empty-icon svg{width:22px!important;height:22px!important;}
body.sige-view-pautas .sige-pautas-empty strong{display:block!important;margin:0 0 5px!important;font-size:17px!important;font-weight:700!important;color:var(--color-ink-500)!important;}
body.sige-view-pautas .sige-pautas-empty p{margin:0!important;color:var(--color-slate-700)!important;font-size:var(--fs-sm)!important;line-height:1.55!important;font-weight:600!important;}
body.sige-view-pautas table.mono th:first-child,
body.sige-view-pautas table.mono td:first-child,
body.sige-view-pautas table.mono th:nth-child(2),
body.sige-view-pautas table.mono td:nth-child(2){position:sticky!important;left:0!important;z-index:4!important;}
body.sige-view-pautas table.mono th:nth-child(2),
body.sige-view-pautas table.mono td:nth-child(2){left:46px!important;}
body.sige-view-pautas table.mono tbody td:first-child,
body.sige-view-pautas table.mono tbody td:nth-child(2){background:var(--color-white)!important;}
body.sige-view-pautas table.mono thead th:first-child,
body.sige-view-pautas table.mono thead th:nth-child(2){z-index:8!important;}
@media(max-width:1320px){body.sige-view-pautas .sige-filter-shell{grid-template-columns:repeat(3,minmax(180px,1fr))!important;}body.sige-view-pautas .sige-app-btn{width:100%!important;}}
@media(max-width:980px){body.sige-view-pautas .sige-filter-shell{grid-template-columns:1fr!important;}body.sige-view-pautas .sige-filter-shell select,body.sige-view-pautas .sige-filter-shell input[type=number]{width:100%!important;min-width:100%!important;}body.sige-view-pautas .sige-app-btn{width:100%!important;}}
@media print{body.sige-view-pautas table.mono th,body.sige-view-pautas table.mono td{position:static!important;}}



/* v12.10.69 - Pautas: acções e exportações responsivas
   Ajuste de UX/responsividade apenas. Não altera geração, PDF, Excel, cálculos ou dados. */
body.sige-view-pautas .sige-filter-shell{
    grid-template-columns:minmax(110px,.55fr) minmax(240px,1.35fr) minmax(180px,.8fr) minmax(190px,.9fr)!important;
    align-items:end!important;
    overflow:visible!important;
}
body.sige-view-pautas .sige-pautas-actions{
    grid-column:1 / -1!important;
    display:flex!important;
    align-items:center!important;
    justify-content:flex-end!important;
    gap:var(--space-3)!important;
    flex-wrap:wrap!important;
    width:100%!important;
    min-width:0!important;
    padding-top:2px!important;
}
body.sige-view-pautas .sige-pautas-actions .sige-app-btn{
    width:auto!important;
    min-width:170px!important;
    max-width:100%!important;
    cursor:pointer!important;
    pointer-events:auto!important;
    user-select:none!important;
    white-space:nowrap!important;
    overflow:visible!important;
    position:relative!important;
    z-index:2!important;
}
body.sige-view-pautas .sige-pautas-actions .sige-app-btn:hover,
body.sige-view-pautas .sige-pautas-actions .sige-app-btn:focus{
    cursor:pointer!important;
    transform:translateY(-1px)!important;
    filter:saturate(1.02)!important;
}
body.sige-view-pautas .sige-pautas-actions .sige-app-btn span{
    display:inline-flex!important;
    align-items:center!important;
    line-height:1!important;
    white-space:nowrap!important;
}
body.sige-view-pautas .sige-pautas-actions .sige-app-btn svg{
    flex:0 0 auto!important;
}
body.sige-view-pautas .sige-pautas-actions .alt-red,
body.sige-view-pautas .sige-pautas-actions .alt-green{
    opacity:1!important;
    visibility:visible!important;
}
@media(max-width:1280px){
    body.sige-view-pautas .sige-filter-shell{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
    }
    body.sige-view-pautas .sige-pautas-actions{
        justify-content:flex-start!important;
    }
}
@media(max-width:760px){
    body.sige-view-pautas .sige-filter-shell{
        grid-template-columns:1fr!important;
    }
    body.sige-view-pautas .sige-pautas-actions{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:10px!important;
    }
    body.sige-view-pautas .sige-pautas-actions .sige-app-btn{
        width:100%!important;
        min-width:0!important;
    }
}

</style>



<section class="sige-pautas-hero" aria-label="Pautas">
    <div class="sige-pautas-hero-main">
        <div class="sige-pautas-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?><span>Académico</span></div>
        <h1>Pautas</h1>
        <p>Consulte, imprima e exporte pautas trimestrais ou anuais, mantendo as regras académicas, permissões e cálculos já definidos pela escola.</p>
        <div class="sige-pautas-hero-actions" aria-label="Resumo do módulo de pautas">
            <span class="sige-pautas-hero-btn sige-pautas-hero-btn-primary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> Ano Lectivo <?php echo esc_html((string) $ano_lectivo); ?></span>
            <span class="sige-pautas-hero-btn"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?> Regras Académicas Activas</span>
            <span class="sige-pautas-hero-btn"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> Moçambique · <?php echo esc_html($sige_now_mz->format('d/m/Y H:i')); ?></span>
        </div>
    </div>
    <aside class="sige-pautas-hero-panel" aria-label="Estado da pauta">
        <div class="sige-pautas-panel-label"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?><span>Estado</span></div>
        <div class="sige-pautas-panel-number"><?php echo esc_html($gerar ? 'Pronta' : 'Preparar'); ?></div>
        <p class="sige-pautas-panel-text"><?php echo esc_html($gerar ? 'Turma e período seleccionados. Pode consultar a pauta e usar os botões de exportação disponíveis.' : 'Seleccione o ano lectivo, a turma e o período para gerar a pauta.'); ?></p>
        <div class="sige-pautas-panel-track" aria-hidden="true"><span style="width:<?php echo esc_attr($gerar ? 100 : ($turma_id ? 55 : 25)); ?>%;"></span></div>
    </aside>
</section>



<!-- ─── FORMULÁRIO DE FILTROS ─── -->

<?php if (!$_is_supervisor_p && empty($_prof_id)): ?>
<div class="sige-pautas-alert">
    <span><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?></span>
    <div>
        <strong>Conta não vinculada</strong>
        <p>O seu utilizador WordPress não está vinculado a nenhum registo de professor no SIGE. Contacte a administração para que o seu perfil seja vinculado correctamente.</p>
    </div>
</div>
<?php endif; ?>



<form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="sige-filter-shell" id="sige-pautas-filtro">



    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? 'sige-app'); ?>">



    <input type="hidden" name="view" value="<?php echo esc_attr($_GET['view'] ?? 'pautas'); ?>">



    <div>



        <label>Ano Lectivo</label>



        <input type="number" name="ano_lectivo" value="<?php echo esc_attr($ano_lectivo); ?>"



               style="width:130px;">



    </div>



    <div>



        <label>Turma</label>



        <select name="turma_id" style="min-width:320px;">



            <option value="0">- Seleccione -</option>



            <?php foreach ($turmas as $t): ?>



                <option value="<?php echo esc_attr($t->id); ?>" <?php selected($turma_id, $t->id); ?>>



                    <?php echo esc_html(sige_label_turma_pauta($t)); ?>



                </option>



            <?php endforeach; ?>



        </select>



    </div>



    <div>



        <label>Período</label>



        <select name="trimestre" style="min-width:190px;">



            <option value="0" <?php selected($trimestre, 0); ?>>Pauta Final (todos)</option>



            <option value="1" <?php selected($trimestre, 1); ?>>1º Trimestre</option>



            <option value="2" <?php selected($trimestre, 2); ?>>2º Trimestre</option>



            <option value="3" <?php selected($trimestre, 3); ?>>3º Trimestre</option>



        </select>



    </div>



    <div class="sige-filter-chip">



        <input type="hidden" name="pauta_final_mode" value="0">



        <label style="display:flex;align-items:center;gap:6px;font-weight:600;cursor:pointer;">



            <input type="checkbox" name="pauta_final_mode" value="1" <?php checked($pauta_final_mode, 1); ?>>



            Modo Pauta Final



        </label>



    </div>



    <div class="sige-pautas-actions no-print" aria-label="Acções da pauta">
        <button type="submit" class="button button-primary sige-app-btn" id="sige-pautas-gerar-btn">
            <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?>
            <span>Gerar Pauta</span>
        </button>

        <?php if ($gerar && !empty($alunos)): ?>

        <button type="button" data-sige-act="sigePDF" data-sige-noargs class="sige-app-btn alt-red sige-pautas-export-btn" aria-label="Exportar pauta em PDF">
            <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?>
            <span>Exportar PDF</span>
        </button>

        <button type="button" data-sige-act="sigeExcel" data-sige-noargs class="sige-app-btn alt-green sige-pautas-export-btn" aria-label="Exportar pauta em Excel">
            <?php echo function_exists('sige_ui_icon') ? sige_ui_icon('grid') : ''; ?>
            <span>Exportar Excel</span>
        </button>

        <?php endif; ?>
    </div>



</form>



<?php if (!$turma_id): ?>

    <div class="sige-pautas-empty no-print">
        <div class="sige-pautas-empty-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?></div>
        <div>
            <strong>Prepare a pauta</strong>
            <p>Seleccione o ano lectivo, a turma e o período. Depois clique em <b>Gerar Pauta</b> para consultar, imprimir ou exportar.</p>
        </div>
    </div>

<?php elseif ($turma_id && !$turma): ?>



    <div class="sige-pautas-alert"><span><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ""; ?></span><div><strong>Turma não encontrada</strong><p>Confirme a turma seleccionada e tente novamente.</p></div></div>



<?php elseif ($gerar && empty($alunos)): ?>



    <div class="sige-pautas-alert"><span><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ""; ?></span><div><strong>Sem alunos activos</strong><p>Nenhum aluno matriculado activo nesta turma e neste ano lectivo.</p></div></div>



<?php elseif ($turma_id && $turma && empty($disciplinas)): ?>



    <div class="sige-pautas-alert"><span><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('book') : ""; ?></span><div><strong>Sem disciplinas configuradas</strong><p>Nenhuma disciplina foi encontrada para esta turma.</p></div></div>



<?php elseif ($gerar && !empty($alunos)): ?>



<?php



// ─── CABEÇALHO DA PAUTA ───────────────────────────────────────



$titulo_pauta = $trimestre > 0



    ? ['','PAUTA DE FREQUÊNCIA - 1º TRIMESTRE','PAUTA DE FREQUÊNCIA - 2º TRIMESTRE','PAUTA DE FREQUÊNCIA - 3º TRIMESTRE'][$trimestre]



    : 'PAUTA ANUAL DE FREQUÊNCIA';



$label_trim = $trimestre > 0 ? ['','Iº','IIº','IIIº'][$trimestre] : 'Anual';



$trims_mostrar = $trimestre > 0 ? [$trimestre] : [1,2,3];



?>



<div class="sige-pautas-context no-print">
    <div class="sige-pautas-context-main">
        <span class="sige-pautas-context-item"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('school') : ''; ?> <?php echo esc_html(sige_label_turma_pauta($turma)); ?></span>
        <span class="sige-pautas-context-item"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> <?php echo esc_html((string) $ano_lectivo); ?></span>
        <span class="sige-pautas-context-item"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> <?php echo esc_html($label_trim); ?></span>
    </div>
    <div class="sige-pautas-context-note"><?php echo esc_html(count($alunos)); ?> aluno(s) · <?php echo esc_html(count($disciplinas)); ?> disciplina(s)</div>
</div>

<style>



@media print {



    .sige-pautas-hero, .wrap > form, .wrap > h1, .no-print { display:none !important; }



    .sige-pauta-mono { margin:0 !important; border:none !important; }



    @page { size: A4 landscape; margin: 8mm; }



    body { font-size: 10px; }



}



.sige-pauta-mono { background:var(--color-white); padding:var(--space-3); border:1px solid var(--color-slate-200); overflow-x:auto; }



.pauta-inst-header { text-align:center; margin-bottom:8px; }



.pauta-inst-header h3 { margin:2px 0; font-size:var(--fs-sm); text-transform:uppercase; color:var(--color-info-800); }



.pauta-inst-header h4 { margin:2px 0; font-size:12px; color:var(--color-slate-900); }



.pauta-meta { display:flex; justify-content:space-between; border:1px solid var(--color-ink-200);



              padding:6px 12px; border-radius:var(--radius-xs); font-size:12px; margin-bottom:8px; }



table.mono { border-collapse:collapse; width:100%; font-size:var(--fs-xs); }



table.mono th, table.mono td {



    border:1px solid var(--color-slate-300); padding:3px 4px; text-align:center; white-space:nowrap;



}



/* Cabeçalho nível 1 - nome disciplina */



table.mono thead tr.tr-disc th {



    background:var(--color-info-800); color:var(--color-white); font-size:10px; font-weight:600; padding:5px 3px;



}



/* Nuclear vs Auxiliar */



table.mono thead tr.tr-disc th.nuclear { background:var(--color-success-900); }



table.mono thead tr.tr-disc th.auxiliar { background:var(--color-slate-700); }



/* Nível 2 - trimestres */



table.mono thead tr.tr-trim th { background:var(--color-ink-600); color:var(--color-white); font-size:10px; }



/* Nível 3 - sub-colunas */



table.mono thead tr.tr-sub th  { background:var(--color-slate-500); color:var(--color-white); font-size:9px; }



th.th-mfd  { background:var(--color-warning-800) !important; color:var(--color-white) !important; }



th.th-esc  { background:var(--color-slate-700) !important; color:var(--color-white) !important; }



th.th-mg   { background:var(--color-info-800) !important; color:var(--color-white) !important; font-size:12px !important; }



th.th-sit  { background:var(--color-success-900) !important; color:var(--color-white) !important; font-size:12px !important; }



th.th-neg  { background:var(--color-danger-500) !important; color:var(--color-white) !important; }



/* Aluno */



td.td-nome { text-align:left; padding-left:6px; min-width:140px; font-weight:500; font-size:var(--fs-xs); }



td.td-num  { font-weight:600; color:var(--color-info-800); width:26px; }



td.td-g    { width:22px; font-size:10px; }



/* Notas calculadas */



td.td-mt  { background:var(--color-info-100); color:var(--color-info-800); font-weight:600; }



td.td-mfd { background:var(--color-warning-200); color:var(--color-warning-800); font-weight:600; font-size:12px; }



td.td-esc { background:var(--color-brand-100); color:var(--color-brand-800); font-size:10px; }



td.td-mg  { background:var(--color-success-100); color:var(--color-success-900); font-weight:700; font-size:var(--fs-sm); }



td.td-neg { color:var(--color-danger-500); font-weight:600; }



/* Linhas alternadas */



table.mono tbody tr:nth-child(even) { background:var(--color-slate-50); }



table.mono tbody tr:hover { background:var(--color-info-50); }



/* Linha de resultado */



td.td-sit { padding:2px; }



</style>



<div class="sige-pauta-mono">



    <!-- Cabeçalho institucional -->



    <div class="pauta-inst-header">



        <h3>República de Moçambique</h3>



        <h4><?php echo esc_html($titulo_pauta); ?></h4>



    </div>



    <div class="pauta-meta">



        <span>



            <strong>Escola:</strong> <?php echo esc_html(get_bloginfo('name')); ?>



            &nbsp;&nbsp;|&nbsp;&nbsp;



            <strong>Turma:</strong> <?php echo esc_html(sige_label_turma_pauta($turma)); ?>



            &nbsp;&nbsp;|&nbsp;&nbsp;



            <strong>Classe:</strong> <?php echo $classe_num; ?>ª



        </span>



        <span>



            <strong>Ano Lectivo:</strong> <?php echo esc_attr($ano_lectivo); ?>



            &nbsp;&nbsp;|&nbsp;&nbsp;



            <strong>Período:</strong> <?php echo $label_trim; ?>



            <?php if ($eh_fim_ciclo && $pauta_final_mode): ?>



                &nbsp;&nbsp;|&nbsp;&nbsp;<strong style="color:var(--color-danger-700);">FIM DE CICLO - Pauta Final</strong>



            <?php endif; ?>



        </span>



    </div>



    <table class="mono">



        <thead>



            <!-- LINHA 1: Nomes das disciplinas -->



            <tr class="tr-disc">



                <th rowspan="3" class="td-num">Nº</th>



                <th rowspan="3" style="text-align:left;min-width:140px;background:var(--color-info-900);color:#fff;">Nome do Aluno</th>



                <th rowspan="3" class="td-g" style="background:var(--color-info-900);color:#fff;">G</th>



                <?php foreach ($disciplinas as $d):



                    $is_nuclear = strtolower($d->categoria ?? '') === 'nuclear';



                    $cat_class  = $is_nuclear ? 'nuclear' : 'auxiliar';



                    $cat_label  = '';



                    // Colunas por disciplina:



                    // Pauta final (todos os trimestres): MT1 MT2 MT3 MFD + ESC = 5 colunas



                    // Trimestre único: MT + ESC = 2 colunas



                    $ncols = ($trimestre === 0) ? 5 : 2;



                ?>



                <th colspan="<?php echo $ncols; ?>" class="<?php echo $cat_class; ?>">



                    <?php echo esc_html($d->sigla ?? $d->nome); ?>



                </th>



                <?php endforeach; ?>



                <?php if ($trimestre === 0): ?>



                <th rowspan="3" class="th-mg">MG</th>



                <th rowspan="3" class="th-neg">Neg.</th>



                <th rowspan="3" class="th-sit">Resultado</th>



                <?php endif; ?>



            </tr>



            <!-- LINHA 2: Subtítulos por disciplina -->



            <tr class="tr-trim">



                <?php foreach ($disciplinas as $d):



                    if ($trimestre === 0): // Pauta final



                ?>



                    <th>MT1</th><th>MT2</th><th>MT3</th>



                    <th class="th-mfd">MFD</th>



                    <th class="th-esc">ESC</th>



                <?php   else: // Trimestre único ?>



                    <th>MT</th>



                    <th class="th-esc">ESC</th>



                <?php   endif; ?>



                <?php endforeach; ?>



            </tr>



            <!-- LINHA 3: vazia (para espaço visual) -->



            <tr class="tr-sub">



                <?php foreach ($disciplinas as $d): ?>



                    <?php $ncols = ($trimestre === 0) ? 5 : 2; ?>



                    <?php for ($i = 0; $i < $ncols; $i++): ?>



                        <th style="background:var(--color-slate-700);font-size:8px;">



                            <?php



                            if ($trimestre === 0) {



                                echo ['1ºT','2ºT','3ºT','Méd','Esc'][$i];



                            } else {



                                echo ['MT','Esc'][$i];



                            }



                            ?>



                        </th>



                    <?php endfor; ?>



                <?php endforeach; ?>



            </tr>



        </thead>



        <tbody>



        <?php



        $num = 1;



        foreach ($alunos as $aluno):



            $aid = (int)$aluno->id;



            // ─── Calcular MTs e MFDs por disciplina ──────────────



            $mfds_nucleares = []; // para calcular MG
            $nucleares_requeridas = 0;
            $nucleares_com_nota_final = 0;
            $tem_alguma_nota_final = false;



            $negativas = 0;



            $nota_min = (float)($regra['nota_minima_aprovacao'] ?? 10);



        ?>



        <tr>



            <td class="td-num"><?php echo $num++; ?></td>



            <td class="td-nome"><?php echo esc_html($aluno->nome_completo); ?></td>



            <td class="td-g"><?php echo esc_html($aluno->genero ?? ''); ?></td>



            <?php foreach ($disciplinas as $d):



                $did = (int)$d->id;



                $is_nuclear = strtolower($d->categoria ?? '') === 'nuclear';



                // Calcular MTs por trimestre



                $mts = [];



                for ($t = 1; $t <= 3; $t++) {



                    $row = $notas_map[$aid][$did][$t] ?? null;



                    $mts[$t] = sige_pauta_mt($row);



                }



                // MFD = (MT1+MT2+MT3)/3 - mesma regra do Aproveitamento: ausentes = 0
                $_mt1 = $mts[1] ?? 0; $_mt2 = $mts[2] ?? 0; $_mt3 = $mts[3] ?? 0;
                $mfd = (int)round(((int)$_mt1 + (int)$_mt2 + (int)$_mt3) / 3, 0);



                // NF se fim de ciclo e pauta final



                $nota_final = $mfd;



                if ($eh_fim_ciclo && $pauta_final_mode && $mfd !== null) {



                    $row3 = $notas_map[$aid][$did][3] ?? null;



                    $exame = ($row3 && is_numeric($row3->nota_exame)) ? (float)$row3->nota_exame : 0.0;



                    $pm = (float)($regra['peso_mfd'] ?? 60);



                    $pe = (float)($regra['peso_exame'] ?? 40);



                    $nota_final = (int)round(($mfd * $pm + $exame * $pe) / ($pm + $pe), 0);



                }



                // Escala qualitativa



                $esc = sige_sne_escala($nota_final);



                // Acumular para MG (só nucleares)
                if ($is_nuclear) {
                    $nucleares_requeridas++;
                }
                if ($nota_final !== null) {
                    $tem_alguma_nota_final = true;
                }



                if ($is_nuclear && $nota_final !== null) {



                    $mfds_nucleares[] = $nota_final;
                    $nucleares_com_nota_final++;



                    if ($nota_final < $nota_min) $negativas++;



                }



                if ($trimestre === 0): // Pauta final: MT1 MT2 MT3 MFD ESC



            ?>



                <td class="td-mt"><?php echo $mts[1] !== null ? $mts[1] : '-'; ?></td>



                <td class="td-mt"><?php echo $mts[2] !== null ? $mts[2] : '-'; ?></td>



                <td class="td-mt"><?php echo $mts[3] !== null ? $mts[3] : '-'; ?></td>



                <td class="td-mfd"><?php echo $nota_final !== null ? $nota_final : '-'; ?></td>



                <td class="td-esc"><?php echo $esc; ?></td>



            <?php   else: // Trimestre único: MT ESC



                $mt_trim = $mts[$trimestre] ?? null;



                $esc_trim = sige_sne_escala($mt_trim);



            ?>



                <td class="td-mt"><?php echo $mt_trim !== null ? $mt_trim : '-'; ?></td>



                <td class="td-esc"><?php echo $esc_trim; ?></td>



            <?php   endif; ?>



            <?php endforeach; // disciplinas ?>



            <?php



            // ─── MG, Negativas, Situação (só Pauta Final) ─────



            $mg = null; $situacao = '-';



            if ($trimestre === 0) {



                $mg = !empty($mfds_nucleares) ? (int)round(array_sum($mfds_nucleares)/count($mfds_nucleares), 0) : null;



                // Mesma fonte de verdade do Aproveitamento do Aluno:
                // notas ausentes contam como 0; quando reprova, o rótulo público será NÃO PROGRIDE/NÃO TRANSITA.
                if (function_exists('sige_calcular_situacao_final')) {
                    $sf = sige_calcular_situacao_final($aid, $turma_id, $ano_lectivo, (bool)$pauta_final_mode);
                    $situacao  = $sf['situacao']     ?? '-';
                    $negativas = $sf['negativas']    ?? $negativas;
                    $mg        = $sf['media_global'] ?? $mg;
                } else {
                    $max_prog = (int)($regra['max_negativas_progride'] ?? 0);
                    $max_tran = (int)($regra['max_negativas_transita'] ?? 2);
                    $eh_fim = !empty($regra['eh_fim_ciclo']) ? (int)$regra['eh_fim_ciclo'] : 0;

                    if ($mg !== null && $mg < $nota_min) {
                        $situacao = 'REPROVA';
                    } elseif ($negativas > $max_tran) {
                        $situacao = 'REPROVA';
                    } elseif ($negativas <= $max_prog) {
                        $situacao = $eh_fim ? 'TRANSITA' : 'PROGRIDE';
                    } elseif ($negativas <= $max_tran) {
                        $situacao = $eh_fim ? 'TRANSITA' : 'PROGRIDE';
                    } else {
                        $situacao = 'REPROVA';
                    }
                }



            }



            ?>



            <?php if ($trimestre === 0): ?>



            <td class="td-mg"><?php echo $mg !== null ? $mg : '-'; ?></td>



            <td class="td-neg"><?php echo $negativas !== null ? $negativas : '-'; ?></td>



            <td class="td-sit"><?php echo sige_badge_situacao_final($situacao, !empty($regra['eh_fim_ciclo'])); ?></td>



            <?php endif; ?>



        </tr>



        <?php endforeach; // alunos ?>



        </tbody>



    </table>



    <!-- Legenda -->



    <div class="sige-pautas-legenda no-print">



        <span>Disciplina Nuclear</span>



        <span>Disciplina Auxiliar</span>



        <span>ESC: NS(&lt;10) | S(10-13) | B(14-16) | MB(17-18) | E(19-20)</span>



        <?php if ($trimestre === 0): ?>



        <span>MG = Média das disciplinas nucleares</span>



        <?php endif; ?>



    </div>



    <!-- Rodapé de assinaturas para impressão -->



    <div class="sige-pautas-signatures">



        <div>Director(a) Pedagógico(a)</div>



        <div>Director(a) da Escola</div>



        <div>Data: ___/___/______</div>



    </div>



</div><!-- /sige-pauta-mono -->



<?php endif; ?>



<script <?php echo sige_csp_script_attr(); ?>>



document.addEventListener('DOMContentLoaded', function(){
    var form = document.getElementById('sige-pautas-filtro');
    var btn = document.getElementById('sige-pautas-gerar-btn');
    if (form && btn) {
        form.addEventListener('submit', function(){
            btn.classList.add('is-loading');
            btn.setAttribute('disabled', 'disabled');
            var span = btn.querySelector('span');
            if (span) span.textContent = 'A gerar pauta...';
        });
    }
});

function sigePDF() {



    var nonce = '<?php echo wp_create_nonce("sige_pauta_pdf_nonce"); ?>';



    var turma = <?php echo (int)$turma_id; ?>;



    var ano   = <?php echo (int)$ano_lectivo; ?>;



    var trim  = <?php echo (int)$trimestre; ?>;



    var pf    = <?php echo (int)$pauta_final_mode; ?>;



    var base  = '<?php echo esc_js(admin_url("admin-post.php")); ?>';



    if (!turma) { return; }

    var params = new URLSearchParams({
        action: 'sige_pauta_pdf',
        turma_id: turma,
        ano_lectivo: ano,
        trimestre: trim,
        pauta_final_mode: pf,
        _wpnonce: nonce
    });

    window.open(base + '?' + params.toString(), '_blank', 'noopener');



}



function sigeExcel() {



    var nonce = '<?php echo wp_create_nonce("sige_pauta_excel_nonce"); ?>';



    var turma = <?php echo (int)$turma_id; ?>;



    var ano   = <?php echo (int)$ano_lectivo; ?>;



    var trim  = <?php echo (int)$trimestre; ?>;



    var pf    = <?php echo (int)$pauta_final_mode; ?>;



    var base  = '<?php echo esc_js(admin_url("admin-post.php")); ?>';



    if (!turma) { return; }

    var params = new URLSearchParams({
        action: 'sige_pauta_excel',
        turma_id: turma,
        ano_lectivo: ano,
        trimestre: trim,
        pauta_final_mode: pf,
        _wpnonce: nonce
    });

    var url = base + '?' + params.toString();
    var frame = document.getElementById('sige-pauta-download-frame');
    if (!frame) {
        frame = document.createElement('iframe');
        frame.id = 'sige-pauta-download-frame';
        frame.name = 'sige-pauta-download-frame';
        frame.style.display = 'none';
        document.body.appendChild(frame);
    }
    frame.src = url;

    // Exportação por iframe: descarrega o ficheiro sem deixar a página presa em processamento.
    setTimeout(function(){
        var gerar = document.getElementById('sige-pautas-gerar-btn');
        if (gerar) {
            gerar.classList.remove('is-loading');
            gerar.removeAttribute('disabled');
            var span = gerar.querySelector('span');
            if (span) span.textContent = 'Gerar Pauta';
        }
    }, 700);



}



</script>



</div><!-- /wrap -->
