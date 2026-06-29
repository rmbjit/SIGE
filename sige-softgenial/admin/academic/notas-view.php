<?php



if (!defined('ABSPATH')) exit;



global $wpdb;



$sige_tz = new DateTimeZone('Africa/Maputo');

$sige_now_mz = new DateTime('now', $sige_tz);

$sige_current_year_mz = (int) $sige_now_mz->format('Y');

$sige_now_label_mz = $sige_now_mz->format('d/m/Y H:i');



if (!sige_page_guard(
    ['academico.lancar_notas','academico.editar_notas'],
    ['sige_professor']
)) return;



$p = $wpdb->prefix;
$eid = sige_require_escola_id('notas');



// ─── DROPDOWNS ────────────────────────────────────────────────



// --- PROFESSOR ACTUAL ------------------------------------------------


$is_admin    = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'));

$is_supervisor = $is_admin || current_user_can('sige_pedagogico') || current_user_can('sige_secretario') || current_user_can('sige_director');
$sige_scoped_professor = function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user();
if ($sige_scoped_professor) { $is_supervisor = false; }

// 12.9.0 - Enforcement Académico UI-first (sem wp_die por permissão).
// Admin TI/Super Admin mantém bypass; se a Permission Engine não estiver carregada, preserva compatibilidade.
$sige_can_lancar_notas = $is_admin || !function_exists('sige_can') || sige_can('academico.lancar_notas', ['view' => 'notas']);
$sige_can_editar_notas = $is_admin || !function_exists('sige_can') || sige_can('academico.editar_notas', ['view' => 'notas']);
$sige_notas_perm_msg = '';


// [FIX D01] Identificacao centralizada - usermeta sige_professor_id -> fallback email
$prof_row     = function_exists('sige_get_professor_atual') ? sige_get_professor_atual() : null;
$prof_sige_id = $prof_row ? (int)$prof_row->id : 0;

// Admins e supervisores veem tudo. Professores veem apenas as suas turmas.

if ($is_supervisor) {

    $turmas = $wpdb->get_results($wpdb->prepare("

        SELECT t.id, t.nome, t.classe, t.ano_lectivo

        FROM {$p}sige_turmas t

        WHERE t.escola_id = %d

        ORDER BY t.ano_lectivo DESC, t.classe ASC, t.nome ASC

    ", $eid));

} elseif ($prof_sige_id) {

    $turmas = $wpdb->get_results( $wpdb->prepare("

        SELECT DISTINCT t.id, t.nome, t.classe, t.ano_lectivo

        FROM {$p}sige_turmas t

        WHERE t.director_turma_id = %d AND t.escola_id = %d

        UNION

        SELECT DISTINCT t.id, t.nome, t.classe, t.ano_lectivo

        FROM {$p}sige_turmas t

        JOIN {$p}sige_turma_disciplinas td ON td.turma_id = t.id

        WHERE td.professor_id = %d AND t.escola_id = %d

        ORDER BY ano_lectivo DESC, classe ASC, nome ASC

    ", $prof_sige_id, $eid, $prof_sige_id, $eid));

} else {

    $turmas = [];

}



$turma_id      = isset($_GET['turma_id'])      ? intval($_GET['turma_id'])      : 0;



$disciplina_id = isset($_GET['disciplina_id']) ? intval($_GET['disciplina_id']) : 0;



$ano_lectivo   = isset($_GET['ano_lectivo'])   ? intval($_GET['ano_lectivo'])   : (function_exists('sige_get_ano_lectivo_atual') ? sige_get_ano_lectivo_atual() : $sige_current_year_mz);



// ─── TURMA SELECCIONADA ───────────────────────────────────────



// Buscar classe da turma para cruzar com matriz curricular



$classe_turma = $wpdb->get_var( $wpdb->prepare(



    "SELECT classe FROM {$p}sige_turmas WHERE id = %d AND escola_id = %d", $turma_id, $eid



));

// [FIX] Buscar info completa da turma para uso no formulário

$turma_info = $turma_id ? $wpdb->get_row($wpdb->prepare("SELECT id, nome, classe FROM {$p}sige_turmas WHERE id = %d AND escola_id = %d", $turma_id, $eid)) : null;

// [V7.2.5] Professor não pode abrir turma não atribuída via URL manual.
if ($turma_id && $sige_scoped_professor) {
    if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, (int)$prof_sige_id, (int)$eid)) {
        $turma_id = 0;
        $disciplina_id = 0;
        $classe_turma = '';
        $turma_info = null;
        if (function_exists('sige_render_teacher_scope_denied')) {
            sige_render_teacher_scope_denied('Esta turma não está atribuída ao seu perfil de professor.');
        }
    }
}



// Disciplinas filtradas por professor (admins vêsm todas, professor só as suas)



if ($is_admin) {



    // Admin: todas as disciplinas da turma (UNION turma_disciplinas + matriz)



    $disciplinas = $wpdb->get_results( $wpdb->prepare(



        "SELECT d.id, d.nome, d.sigla, d.categoria,



                COALESCE(



                    mc.ordem_pauta,



                    CASE d.sigla



                        WHEN 'POR'       THEN 1



                        WHEN 'MAT'       THEN 2



                        WHEN 'CN'        THEN 3



                        WHEN 'CS'        THEN 4



                        WHEN 'ING'       THEN 5



                        WHEN 'ED.VISUAL' THEN 6



                        WHEN 'ED. V'     THEN 6



                        WHEN 'OF'        THEN 7



                        WHEN 'ED. FISICA' THEN 8



                        WHEN 'ED.FISICA' THEN 8



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



} elseif ($prof_sige_id) {



    // Professor: só as disciplinas que lhe foram atribuídas nesta turma



    $disciplinas = $wpdb->get_results( $wpdb->prepare(



        "SELECT d.id, d.nome, d.sigla, d.categoria,



                COALESCE(mc.ordem_pauta, d.ordem, 99) AS ordem



         FROM {$p}sige_disciplinas d



         LEFT JOIN {$p}sige_matriz_curricular mc



             ON mc.disciplina_id = d.id AND mc.classe = %s



         JOIN {$p}sige_turma_disciplinas td



             ON td.disciplina_id = d.id AND td.turma_id = %d AND td.professor_id = %d AND td.escola_id = %d



         ORDER BY ordem, d.nome",



        $classe_turma, $turma_id, $prof_sige_id, $eid



    ));



} else {



    $disciplinas = [];



}

// [T5] Categoria vem da matriz_curricular (varia por classe), não de sige_disciplinas
if (!empty($classe_turma) && !empty($disciplinas) && function_exists('sige_get_categoria_by_matriz')) { foreach ($disciplinas as &$_d) { if (isset($_d->id)) $_d->categoria = sige_get_categoria_by_matriz($classe_turma, $_d->id, $eid); } unset($_d); }

// ─── ALUNOS VIA MATRÍCULAS (estrutura real do sistema) ────────



$alunos = [];



if ($turma_id) {



    $__sige_am_activo_notas = function_exists('sige_aluno_matricula_activa_sql')
        ? sige_aluno_matricula_activa_sql('a', 'm')
        : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa')) AND (m.status_matricula IS NULL OR LOWER(m.status_matricula) IN ('activa','ativa','activo','ativo'))";

    $alunos = $wpdb->get_results($wpdb->prepare("



        SELECT a.id, a.nome_completo, a.genero



        FROM {$p}sige_alunos a



        INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id



        WHERE m.turma_id = %d



          AND m.ano_lectivo = %d



          AND a.escola_id = %d



          AND {$__sige_am_activo_notas}



        ORDER BY a.nome_completo ASC



    ", $turma_id, $ano_lectivo, $eid));



}



// ─── NOTAS (mapa por aluno e trimestre) ───────────────────────



// NOTA: nota_exame serve como AT no T1/T2 e como Exame Final no T3 de fim de ciclo



$notas_mapa = [];



if ($turma_id && $disciplina_id) {



    $notas = $wpdb->get_results($wpdb->prepare("



        SELECT aluno_id, trimestre, nota_ac, nota_acp, nota_exame, nota_at



        FROM {$p}sige_notas



        WHERE turma_id = %d



          AND disciplina_id = %d



          AND ano_lectivo = %d



          AND escola_id = %d



    ", $turma_id, $disciplina_id, $ano_lectivo, $eid));



    foreach ($notas as $n) {



        $notas_mapa[$n->aluno_id][$n->trimestre] = $n;



    }



}



// ─── INFO DA DISCIPLINA ───────────────────────────────────────



$disciplina_info = null;



if ($disciplina_id) {



    $disciplina_info = $wpdb->get_row($wpdb->prepare(



        "SELECT * FROM {$p}sige_disciplinas WHERE id = %d AND escola_id = %d LIMIT 1", $disciplina_id, $eid



    ));



}




// Permissão contextual: o professor pode corrigir/actualizar notas apenas nas
// turmas e disciplinas que lhe foram atribuídas. A aprovação continua a cargo
// da Direcção Pedagógica; alterações relevantes voltam para estado pendente.
$sige_professor_disciplina_atribuida = false;
if ($sige_scoped_professor && $turma_id && $disciplina_id && $prof_sige_id) {
    $sige_professor_disciplina_atribuida = function_exists('sige_professor_can_access_disciplina')
        ? sige_professor_can_access_disciplina((int)$turma_id, (int)$disciplina_id, (int)$prof_sige_id, (int)$eid)
        : false;
}
$sige_can_editar_notas_no_contexto = $sige_can_editar_notas || (
    $sige_scoped_professor
    && $sige_can_lancar_notas
    && $sige_professor_disciplina_atribuida
);

// ─── É FIM DE CICLO? ─────────────────────────────────────────



$eh_fim_ciclo = false;



if ($turma_info && function_exists('sige_is_fim_ciclo_by_turma')) {



    $eh_fim_ciclo = sige_is_fim_ciclo_by_turma($turma_id, $ano_lectivo);



} elseif ($turma_info) {



    $classe_num = (int) preg_replace('/\D+/', '', (string)($turma_info->classe ?? ''));



    $eh_fim_ciclo = in_array($classe_num, [3, 6, 9, 12]);



}




// Regra canónica da avaliação final:
// AF é exclusiva da 3.ª classe; restantes classes de fim de ciclo usam Exame/NF quando configurado.
$classe_num_atual = ($turma_info && function_exists('sige_parse_classe_num'))
    ? (int) sige_parse_classe_num($turma_info->classe ?? '')
    : (int) preg_replace('/\D+/', '', (string)($turma_info->classe ?? ''));
$regra_academica_atual = ($classe_num_atual > 0 && function_exists('sige_get_regra_academica'))
    ? sige_get_regra_academica($ano_lectivo, $classe_num_atual)
    : null;
$exige_avaliacao_final = $regra_academica_atual ? ((int)($regra_academica_atual->exige_exame ?? 0) === 1) : $eh_fim_ciclo;
$label_avaliacao_final = function_exists('sige_label_avaliacao_final') ? sige_label_avaliacao_final($classe_num_atual) : ($classe_num_atual === 3 ? 'AF' : 'Exame');
$label_nota_final      = function_exists('sige_label_nota_final')      ? sige_label_nota_final($classe_num_atual)      : ($classe_num_atual === 3 ? 'MF' : 'NF');

// ─── NOTAS BLOQUEADAS? ────────────────────────────────────────

// Protegido contra tabela de anos lectivos inexistente e com mensagem clara.
$notas_locked = false;
$notas_lock_status = ['locked' => false];
$notas_lock_message = '';

if ($turma_id) {
    try {
        if (function_exists('sige_notas_lock_status')) {
            $notas_lock_status = sige_notas_lock_status((int)$ano_lectivo, (int)$turma_id, (int)$eid);
            $notas_locked = !empty($notas_lock_status['locked']);
            $notas_lock_message = function_exists('sige_notas_lock_message')
                ? sige_notas_lock_message($notas_lock_status)
                : ($notas_locked ? 'Edição bloqueada.' : '');
        } elseif (function_exists('sige_notas_locked')) {
            $notas_locked = (bool)sige_notas_locked($ano_lectivo, $turma_id);
            $notas_lock_message = $notas_locked ? 'Edição bloqueada.' : '';
        }
    } catch (Exception $e) {
        $notas_locked = false;
        $notas_lock_status = ['locked' => false];
        $notas_lock_message = '';
    }
}

// ─── GUARDAR NOTAS ────────────────────────────────────────────



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_save_notas']) && !$sige_can_lancar_notas) {
    $sige_notas_perm_msg = 'Sem permissão para lançar notas. A acção foi bloqueada pela Permission Engine.';
    if (function_exists('sige_obs_log')) {
        sige_obs_log('permission_denied', ['permission' => 'academico.lancar_notas', 'view' => 'notas']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_save_notas']) && $sige_can_lancar_notas) {

    check_admin_referer('sige_notas_nonce');



    $post_turma      = intval($_POST['turma_id']);
    $post_disciplina = intval($_POST['disciplina_id']);
    $post_ano        = isset($_POST['ano_lectivo']) ? intval($_POST['ano_lectivo']) : (int)$ano_lectivo;

    // V7.2.6 - Revalidar bloqueio com dados POST, não apenas com o contexto GET.
    if (function_exists('sige_notas_lock_status')) {
        $post_lock_status = sige_notas_lock_status($post_ano, $post_turma, (int)$eid);
        if (!empty($post_lock_status['locked'])) {
            $post_lock_msg = function_exists('sige_notas_lock_message') ? sige_notas_lock_message($post_lock_status) : 'Edição bloqueada.';
            wp_die(esc_html($post_lock_msg));
        }
    } elseif ($notas_locked) {
        wp_die('Edição bloqueada.');
    }

    // Guard: professor só pode guardar notas das suas turmas/disciplinas.
    // Director/Pedagógico/Secretaria/Admin continuam com visão ampla.
    if ($sige_scoped_professor) {
        $ok_turma = function_exists('sige_professor_can_access_turma') ? sige_professor_can_access_turma($post_turma, $prof_sige_id, $eid) : false;
        $ok_disciplina = function_exists('sige_professor_can_access_disciplina') ? sige_professor_can_access_disciplina($post_turma, $post_disciplina, $prof_sige_id, $eid) : false;
        if (!$ok_turma || !$ok_disciplina) {
            wp_die('🔒 Não tens permissão para lançar notas nesta turma/disciplina.');
        }
    } elseif (!$is_admin && !$is_supervisor && $prof_sige_id) {
        $autorizado = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$p}sige_turma_disciplinas
             WHERE turma_id = %d AND disciplina_id = %d AND professor_id = %d AND escola_id = %d",
            $post_turma, $post_disciplina, $prof_sige_id, $eid
        ));
        if (!$autorizado) {
            wp_die('🔒 Não tens permissão para lançar notas nesta disciplina.');
        }
    }

    $tNotas          = $wpdb->prefix . 'sige_notas';



    foreach ((array)$_POST['notas'] as $aluno_id => $trimestres) {



        foreach ((array)$trimestres as $trim => $campos) {



            // Usar sige_sne_sanitizar_nota se disponível



            $fn = function_exists('sige_sne_sanitizar_nota') ? 'sige_sne_sanitizar_nota' : function($v) {



                if ($v === '' || $v === null) return null;



                $n = floatval(str_replace(',', '.', $v));



                return ($n < 0 || $n > 20) ? null : round($n, 2);



            };



            $ac    = $fn(isset($campos['ac'])    ? $campos['ac']    : null);



            $acp   = $fn(isset($campos['acp'])   ? $campos['acp']   : null);



            // nota_exame = AT (T1/T2) ou Exame Final (T3 fim de ciclo)



            $exame = $fn(isset($campos['exame']) ? $campos['exame'] : null);



            $existe = $wpdb->get_var($wpdb->prepare("



                SELECT id FROM {$tNotas}



                WHERE aluno_id = %d AND disciplina_id = %d



                  AND turma_id <=> %d AND trimestre = %d AND ano_lectivo = %d



                  AND escola_id = %d



                LIMIT 1



            ", $aluno_id, $post_disciplina, $post_turma, $trim, $post_ano, $eid));



            // Se todos vazios: apagar registo existente (limpar nota)



            if ($ac === null && $acp === null && $exame === null) {



                if ($existe) {



                    $wpdb->delete($tNotas, ['id' => intval($existe), 'escola_id' => $eid]);



                }



                continue;



            }



            $dados = [



                'aluno_id'      => intval($aluno_id),



                'disciplina_id' => $post_disciplina,



                'turma_id'      => $post_turma,



                'trimestre'     => intval($trim),



                'ano_lectivo'   => $post_ano,



                'nota_ac'       => $ac,



                'nota_acp'      => $acp,



                'nota_exame'    => $exame,

                'escola_id'     => $eid,
                'nota_at'       => ((int)$trim === 3 && $eh_fim_ciclo && $exige_avaliacao_final && isset($_POST['notas'][$aluno_id]['af'])) ? $fn($_POST['notas'][$aluno_id]['af']) : null,



            ];



            // [FIX A-01] Adicionar campos de aprovação se colunas existem

            static $_has_approval_cols = null;

            if ($_has_approval_cols === null) {

                $_se = $wpdb->suppress_errors(true);

                $_has_approval_cols = !empty($wpdb->get_results("SHOW COLUMNS FROM {$tNotas} LIKE 'status'"));

                $wpdb->suppress_errors($_se);

            }

            // [v12.9.67] SMART UPDATE - preservar aprovação quando professor só adiciona
            // novos campos sem alterar os já aprovados.
            //
            // Regras:
            //  - Linha não existe → INSERT (pendente se professor, aprovado se supervisor).
            //  - Linha existe e estava 'aprovado' → comparar valores antigos vs novos:
            //      * Se algum campo PREVIAMENTE PREENCHIDO foi alterado ou apagado → status='pendente'.
            //      * Se só novos campos (que estavam NULL) foram adicionados → mantém 'aprovado'.
            //  - Linha existe e NÃO estava aprovado → continua 'pendente' (idem antes).
            //  - Supervisor (admin/director/pedagogico) → sempre 'aprovado' (consciente).
            //
            // Isto preserva o trabalho do director: ACS1 aprovado, professor lança ACS2 →
            // só status passa a ter ACS2 pendente; ACS1 permanece como aprovado.

            $row_anterior = null;
            if ($existe && $_has_approval_cols) {
                $row_anterior = $wpdb->get_row($wpdb->prepare(
                    "SELECT nota_ac, nota_acp, nota_at, nota_exame, status, aprovado_por, aprovado_em
                       FROM {$tNotas}
                      WHERE id = %d AND escola_id = %d
                      LIMIT 1",
                    intval($existe), $eid
                ));
            }

            if ($_has_approval_cols) {

                $novo_status = null;

                if (!$existe) {
                    // INSERT: professor → pendente, supervisor → aprovado
                    $novo_status = $is_supervisor ? 'aprovado' : 'pendente';
                } elseif ($is_supervisor) {
                    // Supervisor está consciente do que faz - mantém aprovação
                    $novo_status = 'aprovado';
                } else {
                    // Professor a editar linha existente: aplicar lógica smart
                    $st_ant = $row_anterior ? (string)$row_anterior->status : '';
                    if ($st_ant === 'aprovado' && $row_anterior) {
                        // Comparação campo-a-campo: campo "alterado" = (antigo NÃO era NULL) E (novo != antigo)
                        // Campo "adicionado" = (antigo era NULL) E (novo NÃO é NULL) - não invalida aprovação
                        $alterou_aprovado = false;
                        $campos_check = [
                            ['nota_ac',    $ac],
                            ['nota_acp',   $acp],
                            ['nota_exame', $exame],
                            ['nota_at',    $dados['nota_at']],
                        ];
                        foreach ($campos_check as $check) {
                            $col = $check[0];
                            $novo_val = $check[1];
                            $antigo_val = isset($row_anterior->$col) ? $row_anterior->$col : null;
                            // Comparar como strings com normalização decimal
                            $a_str = ($antigo_val === null) ? null : sprintf('%.2f', (float)$antigo_val);
                            $n_str = ($novo_val === null) ? null : sprintf('%.2f', (float)$novo_val);
                            if ($a_str !== null && $a_str !== $n_str) {
                                // Campo previamente preenchido foi alterado (ou apagado) → invalida aprovação
                                $alterou_aprovado = true;
                                break;
                            }
                        }
                        $novo_status = $alterou_aprovado ? 'pendente' : 'aprovado';
                    } else {
                        // Linha estava pendente/rejeitado → continua pendente
                        $novo_status = 'pendente';
                    }
                }

                $dados['status'] = $novo_status;

                // Preservar metadados de aprovação quando estamos a manter 'aprovado'
                if ($novo_status === 'aprovado' && $existe && $row_anterior && $row_anterior->aprovado_por) {
                    $dados['aprovado_por'] = (int) $row_anterior->aprovado_por;
                    $dados['aprovado_em']  = (string) $row_anterior->aprovado_em;
                }

                $dados['submetido_por'] = get_current_user_id();
                $dados['submetido_em']  = current_time('mysql');

            }



            if ($existe) {



                $wpdb->update($tNotas, $dados, ['id' => intval($existe), 'escola_id' => $eid]);



            } else {



                $wpdb->insert($tNotas, $dados);



            }



        }



    }



    // [FIX A-01] Notificar Dir. Pedagógico por email quando professor submete notas

    if (!$is_supervisor) {

        $_turma_info = $wpdb->get_row($wpdb->prepare(

            "SELECT nome, classe FROM {$p}sige_turmas WHERE id=%d AND escola_id=%d", $post_turma, $eid

        ));

        $_disc_info = $wpdb->get_row($wpdb->prepare(

            "SELECT nome FROM {$p}sige_disciplinas WHERE id=%d AND escola_id=%d", $post_disciplina, $eid

        ));

        $_prof_nome = wp_get_current_user()->display_name;

        $_turma_label = $_turma_info ? ($_turma_info->classe . ' - ' . $_turma_info->nome) : "Turma #{$post_turma}";

        $_disc_label = $_disc_info ? $_disc_info->nome : "Disciplina #{$post_disciplina}";



        $_dest_users = get_users(['role__in' => ['sige_pedagogico', 'sige_director'], 'fields' => ['user_email', 'display_name']]);

        if (!empty($_dest_users)) {

            $escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;

            $_escola_nome = $escola->nome_escola ?? get_bloginfo('name');

            $_link_aprovar = admin_url('admin.php?page=sige-app&view=aprovar_notas');



            $_assunto = "📝 Notas para aprovação - " . esc_html($_turma_label) . " | " . esc_html($_disc_label);

            $_corpo = "

            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>

                <div style='background:var(--sg-theme-primary,var(--color-brand-500));color:white;padding:20px;border-radius:8px 8px 0 0;text-align:center;'>

                    <h2 style='margin:0;'>📝 Notas Pendentes de Aprovação</h2>

                    <p style='margin:5px 0 0;opacity:0.9;'>" . esc_html($_escola_nome) . "</p>

                </div>

                <div style='background:var(--color-slate-50);padding:25px;border:1px solid var(--color-ink-100);'>

                    <p>Exmo(a) Director(a) Pedagógico(a),</p>

                    <p>O(a) professor(a) <strong>" . esc_html($_prof_nome) . "</strong> submeteu novas notas que aguardam a sua aprovação:</p>

                    <table style='width:100%;border-collapse:collapse;margin:15px 0;background:white;border-radius:6px;border:1px solid var(--color-ink-100);'>

                        <tr><td style='padding:8px 12px;border-bottom:1px solid var(--color-ink-50);color:var(--color-slate-500);'>Turma</td>

                            <td style='padding:8px 12px;border-bottom:1px solid var(--color-ink-50);font-weight:bold;'>" . esc_html($_turma_label) . "</td></tr>

                        <tr><td style='padding:8px 12px;border-bottom:1px solid var(--color-ink-50);color:var(--color-slate-500);'>Disciplina</td>

                            <td style='padding:8px 12px;border-bottom:1px solid var(--color-ink-50);font-weight:bold;'>" . esc_html($_disc_label) . "</td></tr>

                        <tr><td style='padding:8px 12px;color:var(--color-slate-500);'>Data de submissão</td>

                            <td style='padding:8px 12px;font-weight:bold;'>" . current_time('d/m/Y H:i') . "</td></tr>

                    </table>

                    <p style='margin-top:15px;'>

                        <a href='" . esc_url($_link_aprovar) . "' style='display:inline-block;background:var(--sg-theme-primary,var(--color-brand-500));color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;'>

                            ✅ Rever e Aprovar Notas

                        </a>

                    </p>

                </div>

                <div style='background:var(--color-ink-50);padding:15px;border-radius:0 0 8px 8px;text-align:center;font-size:12px;color:var(--color-slate-500);border:1px solid var(--color-ink-100);border-top:0;'>

                    <p style='margin:0;'>Documento emitido pela secretaria da escola</p>

                </div>

            </div>";



            $headers = ['Content-Type: text/html; charset=UTF-8'];

            foreach ($_dest_users as $_du) {

                wp_mail($_du->user_email, $_assunto, $_corpo, $headers);

            }

        }

    }



    $redirect = add_query_arg([



        'page'          => sanitize_text_field($_GET['page'] ?? ''),



        'view'          => 'notas',



        'turma_id'      => $post_turma,



        'disciplina_id' => $post_disciplina,



        'ano_lectivo'   => $post_ano,



        'saved'         => 1,



    ], admin_url('admin.php'));



    // wp_redirect falha aqui pois o HTML da sidebar ja foi enviado

    // [LOG-03] Audit log para gravação de notas
    if (function_exists('sige_audit_log')) sige_audit_log('gravar_notas', ['turma_id' => $post_turma, 'disciplina_id' => $post_disciplina, 'ano' => $post_ano], 'notas');

    echo '<script ' . sige_csp_script_attr() . '>window.location.replace(' . wp_json_encode($redirect) . ');</script>';



    exit;



}



?>



<style>

.sige-ficha-wrap{font-family:'Segoe UI',Arial,sans-serif;max-width:100%;padding:0 12px 18px;}

.sige-page-hero{background:linear-gradient(135deg,var(--color-ink-800) 0%,var(--color-info-700) 100%);color:var(--color-white);border-radius:var(--radius-xl);padding:22px 24px;box-shadow:var(--shadow-md);margin:var(--space-1) 0 var(--space-4);position:relative;overflow:hidden}

.sige-page-hero:before{content:"";position:absolute;right:-40px;top:-40px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.08)}

.sige-page-hero h2{margin:0 0 6px;font-size:var(--fs-xl);line-height:1.2;color:var(--color-white)}

.sige-page-hero p{margin:0;color:rgba(255,255,255,.9);font-size:var(--fs-base);max-width:780px}

.sige-page-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}

.sige-meta-pill{display:inline-flex;align-items:center;gap:var(--space-2);padding:var(--space-2) var(--space-3);border-radius:var(--radius-pill);background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.12);font-size:12px;font-weight:600;color:var(--color-white)}

.sige-filtros{background:var(--color-white);border:1px solid var(--color-info-100);border-radius:var(--radius-lg);padding:18px 20px;display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;box-shadow:var(--shadow-sm);margin-bottom:14px}

.sige-filtros label{font-size:12px;font-weight:600;color:var(--color-ink-700);display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.03em}

.sige-filtros select{padding:10px 12px;border:1px solid var(--color-ink-200);border-radius:var(--radius-md);font-size:var(--fs-sm);min-width:200px;background:var(--color-slate-50);box-shadow:none}

.sige-filtros select:focus{outline:none;border-color:var(--color-info-700);box-shadow:0 1px 2px rgba(15,23,42,.04)}

.sige-btn-save{background:linear-gradient(135deg,var(--color-success-500) 0%,var(--color-success-800) 100%);color:var(--color-white);font-size:var(--fs-base);padding:11px 28px;border:none;border-radius:var(--radius-md);cursor:pointer;font-weight:600;box-shadow:0 2px 8px rgba(15,23,42,.06)}

.sige-btn-save:hover{filter:brightness(1.03)}

.sige-btn-save:disabled{background:var(--color-ink-300);box-shadow:none;cursor:not-allowed}

.sige-notice-saved,.sige-notice-locked{padding:var(--space-3) var(--space-4);border-radius:var(--radius-md);margin:var(--space-3) 0;font-weight:600;border:1px solid transparent;box-shadow:0 2px 8px rgba(15,23,42,.06)}

.sige-notice-saved{background:var(--color-success-100);border-color:var(--color-success-200);color:var(--color-success-900)}

.sige-notice-locked{background:var(--color-danger-50);border-color:var(--color-danger-200);color:var(--color-danger-800)}

.sige-table-scroll{overflow-x:auto;margin-top:0;background:var(--color-white);border:1px solid var(--color-info-100);border-radius:var(--radius-lg);box-shadow:0 2px 8px rgba(15,23,42,.06)}

table.sige-notas{border-collapse:separate;border-spacing:0;width:100%;font-size:12px;background:var(--color-white);border:none;border-radius:var(--radius-lg);overflow:hidden}

table.sige-notas th,table.sige-notas td{border-right:1px solid var(--color-info-100);border-bottom:1px solid var(--color-info-100);padding:5px 6px;text-align:center;white-space:nowrap}

table.sige-notas th:last-child,table.sige-notas td:last-child{border-right:none}

table.sige-notas thead tr:first-child th{background:var(--color-info-800);color:var(--color-white);font-size:var(--fs-xs);padding:8px 6px}

table.sige-notas thead tr:nth-child(2) th{background:var(--color-info-700);color:var(--color-white);font-size:10px}

table.sige-notas thead tr:nth-child(3) th{background:var(--color-slate-500);color:var(--color-white);font-size:10px}

th.trim1{background:var(--color-success-900)!important} th.trim2{background:var(--color-warning-800)!important} th.trim3{background:var(--color-info-800)!important}

th.th-mfd{background:var(--color-warning-800)!important;color:var(--color-white)!important} th.th-exame{background:var(--color-danger-500)!important;color:var(--color-white)!important} th.th-nf{background:var(--color-danger-700)!important;color:var(--color-white)!important}

table.sige-notas tbody tr:hover{background:var(--color-slate-50)} table.sige-notas tbody tr:nth-child(even){background:var(--color-white)} table.sige-notas tbody tr:nth-child(even):hover{background:var(--color-info-50)}

td.aluno-nome{text-align:left;padding-left:10px;min-width:180px;font-weight:600;color:var(--color-info-900)} td.aluno-num{font-weight:600;color:var(--color-ink-800)}

table.sige-notas input.nota-input{width:56px;border:1px solid var(--color-slate-300);border-radius:var(--radius-sm);padding:6px 5px;text-align:center;font-size:12px;background:var(--color-warning-50);transition:.18s ease}

table.sige-notas input.nota-input:focus{outline:none;border-color:var(--color-info-700);box-shadow:var(--shadow-xs);background:var(--color-white)}

table.sige-notas input.nota-input.at-input{background:var(--color-success-50);border-color:var(--color-success-700)} table.sige-notas input.nota-input.exame-input{background:var(--color-warning-100);border-color:var(--color-warning-600);width:60px}

td.calc-med-acs{background:var(--color-success-50);font-weight:600;color:var(--color-success-900)} td.calc-mt{background:var(--color-info-50);font-weight:600;color:var(--color-info-800)} td.calc-mfd{background:var(--color-warning-100);font-weight:700;color:var(--color-warning-800);font-size:13px} td.calc-nf{background:var(--color-danger-100);font-weight:700;color:var(--color-danger-500);font-size:13px}

.sige-legenda{margin-top:12px;font-size:var(--fs-xs);color:var(--color-slate-600);display:flex;gap:var(--space-4);flex-wrap:wrap;padding:0 4px}

.leg-box{width:14px;height:14px;border-radius:var(--radius-xs);display:inline-block;vertical-align:middle}

.sige-context-bar{margin:12px 0 14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;padding:var(--space-3) var(--space-4);border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-info-100);color:var(--color-ink-800)}

.sige-context-bar__main{font-size:var(--fs-sm);font-weight:600}

.sige-context-bar__meta{font-size:12px;color:var(--color-slate-500)}

@media (max-width: 768px){.sige-page-hero{padding:18px}.sige-page-hero h2{font-size:20px}.sige-filtros{padding:16px}.sige-filtros select{min-width:100%}.sige-context-bar{align-items:flex-start}}



/* ==========================================================================
   SIGE SoftGenial v12.10.66 - Lançamento de Notas: Compliance Visual Integral
   Referência mandatória: Painel Principal / Dashboard V2 MJS-grade.
   Escopo: camada visual apenas. Não altera lançamento, cálculo, submissão,
   aprovação, pautas, permissões, queries ou regras académicas.
   ========================================================================== */
body.sige-view-notas .sg-product-page-head{display:none!important;}
body.sige-view-notas .sg-app-page{padding-top:0!important;}
.sige-notas-page{
    --notas-blue:var(--sg-theme-primary,var(--color-brand-500));
    --notas-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --notas-purple:var(--color-brand-500);
    --notas-purple-soft:var(--color-brand-50);
    --notas-ink:var(--color-black);
    --notas-muted:var(--color-slate-700);
    --notas-line:var(--color-ink-100);
    --notas-green:var(--color-success-500);
    --notas-red:var(--color-danger-500);
    --notas-amber:var(--color-warning-500);
    width:100%!important;
    max-width:none!important;
    margin:0!important;
    padding:0 0 28px!important;
    color:var(--notas-ink)!important;
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif)!important;
}
.sige-notas-page *{box-sizing:border-box!important;}
.sige-notas-page svg{stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;display:block!important;}

/* HERO - padrão claro do Painel Principal */
.sige-notas-page .sige-notas-hero{
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
    color:var(--notas-ink)!important;
}
.sige-notas-page .sige-notas-hero:before{
    content:""!important;
    position:absolute!important;
    inset:auto -80px -130px auto!important;
    width:420px!important;
    height:300px!important;
    border-radius:var(--radius-pill)!important;
    background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%)!important;
    pointer-events:none!important;
}
.sige-notas-page .sige-notas-hero:after{display:none!important;content:none!important;}
.sige-notas-page .sige-notas-hero-main,.sige-notas-page .sige-notas-hero-panel{position:relative!important;z-index:1!important;}
.sige-notas-page .sige-notas-kicker{
    display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;margin:0 0 10px!important;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important;color:var(--notas-blue)!important;font-size:12px!important;line-height:1.2!important;font-weight:700!important;letter-spacing:.11em!important;text-transform:uppercase!important;box-shadow:none!important;
}
.sige-notas-page .sige-notas-kicker:before,.sige-notas-page .sige-notas-kicker:after{display:none!important;content:none!important;}
.sige-notas-page .sige-notas-kicker svg{width:18px!important;height:18px!important;}
.sige-notas-page .sige-notas-hero h1{margin:0!important;max-width:650px!important;color:var(--color-black)!important;font-size:31px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.04em!important;font-family:inherit!important;}
.sige-notas-page .sige-notas-hero p{max-width:650px!important;margin:var(--space-3) 0 0!important;color:var(--color-slate-700)!important;font-size:15px!important;line-height:1.65!important;font-weight:500!important;}
.sige-notas-page .sige-notas-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:var(--space-3)!important;margin-top:24px!important;}
.sige-notas-page .sige-notas-hero-btn{min-height:46px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;border-radius:var(--radius-md)!important;padding:0 22px!important;font-size:var(--fs-base)!important;font-weight:700!important;text-decoration:none!important;border:1px solid transparent!important;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important;}
.sige-notas-page .sige-notas-hero-btn svg{width:18px!important;height:18px!important;}
.sige-notas-page .sige-notas-hero-btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sige-notas-page .sige-notas-hero-btn-secondary{background:var(--color-white)!important;color:var(--color-ink-900)!important;border-color:var(--color-ink-100)!important;box-shadow:var(--shadow-sm);}
.sige-notas-page .sige-notas-hero-panel{min-height:148px!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18))!important;padding:22px!important;overflow:hidden!important;display:flex!important;flex-direction:column!important;justify-content:center!important;gap:10px!important;border:1px solid rgba(92,64,187,.08)!important;}
.sige-notas-page .sige-notas-hero-panel:before{content:""!important;position:absolute!important;right:22px!important;bottom:16px!important;width:112px!important;height:92px!important;border-radius:22px 22px 12px 12px!important;background:rgba(109,93,252,.16)!important;box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)!important;}
.sige-notas-page .sige-notas-hero-panel>*{position:relative!important;z-index:1!important;}
.sige-notas-page .sige-notas-panel-label{display:flex!important;align-items:center!important;gap:var(--space-2)!important;color:var(--color-brand-500)!important;font-size:12px!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.11em!important;}
.sige-notas-page .sige-notas-panel-label svg{width:18px!important;height:18px!important;}
.sige-notas-page .sige-notas-panel-number{font-size:40px!important;line-height:1!important;font-weight:700!important;letter-spacing:-.045em!important;color:var(--color-ink-900)!important;}
.sige-notas-page .sige-notas-panel-text{font-size:var(--fs-sm)!important;color:var(--color-slate-600)!important;line-height:1.55!important;margin:0!important;max-width:320px!important;font-weight:600!important;}
.sige-notas-page .sige-notas-panel-track{height:10px!important;border-radius:var(--radius-pill)!important;background:rgba(255,255,255,.7)!important;overflow:hidden!important;margin-top:4px!important;}
.sige-notas-page .sige-notas-panel-track span{display:block!important;height:100%!important;border-radius:var(--radius-pill)!important;background:linear-gradient(90deg,var(--color-brand-400),var(--color-brand-600))!important;}

/* Filtros */
.sige-notas-page .sige-filtros{background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;border-radius:var(--radius-xl)!important;padding:18px 20px!important;display:flex!important;gap:14px!important;flex-wrap:wrap!important;align-items:flex-end!important;box-shadow:var(--shadow-md);margin-bottom:16px!important;}
.sige-notas-page .sige-filtros label{font-size:var(--fs-xs)!important;font-weight:700!important;color:var(--color-slate-600)!important;display:block!important;margin-bottom:7px!important;text-transform:uppercase!important;letter-spacing:.07em!important;}
.sige-notas-page .sige-filtros select{min-height:44px!important;padding:0 13px!important;border:1px solid var(--color-ink-100)!important;border-radius:var(--radius-md)!important;font-size:var(--fs-sm)!important;min-width:220px!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;font-weight:600!important;box-shadow:var(--shadow-sm);outline:none!important;}
.sige-notas-page .sige-filtros select:focus{border-color:rgba(90,63,214,.55)!important;box-shadow:var(--shadow-xs);}

/* Alertas */
.sige-notas-page .sige-notas-alert,.sige-notas-page .sige-notice-saved,.sige-notas-page .sige-notice-locked{display:flex!important;align-items:flex-start!important;gap:10px!important;padding:14px 16px!important;border-radius:var(--radius-lg)!important;margin:var(--space-3) 0!important;font-weight:600!important;border:1px solid transparent!important;box-shadow:var(--shadow-sm);}
.sige-notas-page .sige-notas-alert svg,.sige-notas-page .sige-notice-saved svg,.sige-notas-page .sige-notice-locked svg{width:18px!important;height:18px!important;flex:0 0 auto!important;margin-top:1px!important;}
.sige-notas-page .sige-notas-alert-warning{background:var(--color-warning-50)!important;border-color:var(--color-warning-200)!important;color:var(--color-warning-800)!important;}
.sige-notas-page .sige-notas-alert strong{display:block!important;margin-bottom:4px!important;color:inherit!important;}
.sige-notas-page .sige-notas-alert p{margin:0!important;color:inherit!important;font-size:var(--fs-sm)!important;line-height:1.5!important;font-weight:600!important;}
.sige-notas-page .sige-notice-saved{background:var(--color-success-50)!important;border-color:var(--color-success-200)!important;color:var(--color-success-900)!important;}
.sige-notas-page .sige-notice-locked{background:var(--color-danger-50)!important;border-color:var(--color-danger-200)!important;color:var(--color-danger-700)!important;}

/* Contexto e botões */
.sige-notas-page .sige-context-bar{margin:12px 0 14px!important;display:flex!important;justify-content:space-between!important;align-items:center!important;flex-wrap:wrap!important;gap:var(--space-3)!important;padding:16px 18px!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;color:var(--color-ink-500)!important;box-shadow:var(--shadow-md);}
.sige-notas-page .sige-context-bar__main{display:flex!important;align-items:center!important;gap:10px!important;flex-wrap:wrap!important;font-size:var(--fs-sm)!important;font-weight:600!important;}
.sige-notas-page .sige-context-item,.sige-notas-page .sige-context-cycle{display:inline-flex!important;align-items:center!important;gap:7px!important;padding:7px 10px!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-slate-800)!important;font-size:12px!important;font-weight:700!important;}
.sige-notas-page .sige-context-item svg,.sige-notas-page .sige-context-cycle svg{width:16px!important;height:16px!important;}
.sige-notas-page .sige-context-cycle{background:var(--color-warning-50)!important;border-color:var(--color-warning-200)!important;color:var(--color-warning-800)!important;}
.sige-notas-page .sige-btn-save{min-height:44px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)))!important;color:var(--color-white)!important;font-size:var(--fs-sm)!important;padding:0 var(--space-5)!important;border:none!important;border-radius:var(--radius-md)!important;cursor:pointer!important;font-weight:700!important;box-shadow:var(--shadow-sm);}
.sige-notas-page .sige-btn-save svg{width:18px!important;height:18px!important;}

/* Tabela */
.sige-notas-page .sige-table-scroll{overflow-x:auto!important;margin-top:0!important;background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);}
.sige-notas-page table.sige-notas{border-collapse:separate!important;border-spacing:0!important;width:100%!important;font-size:12px!important;background:var(--color-white)!important;border:none!important;border-radius:var(--radius-xl)!important;overflow:hidden!important;}
.sige-notas-page table.sige-notas th,.sige-notas-page table.sige-notas td{border-right:1px solid var(--color-slate-100)!important;border-bottom:1px solid var(--color-slate-100)!important;padding:7px 8px!important;text-align:center!important;white-space:nowrap!important;}
.sige-notas-page table.sige-notas thead tr:first-child th{background:var(--sg-theme-primary,var(--color-brand-500))!important;color:var(--color-white)!important;font-size:var(--fs-xs)!important;padding:10px 8px!important;font-weight:700!important;}
.sige-notas-page table.sige-notas thead tr:nth-child(2) th{background:var(--sg-theme-primary-800,var(--color-ink-700))!important;color:var(--color-white)!important;font-size:10px!important;font-weight:700!important;}
.sige-notas-page table.sige-notas thead tr:nth-child(3) th{background:var(--color-info-50)!important;color:var(--color-ink-800)!important;font-size:10px!important;font-weight:700!important;}
.sige-notas-page th.trim1{background:var(--color-success-900)!important;color:var(--color-white)!important;}
.sige-notas-page th.trim2{background:var(--color-warning-800)!important;color:var(--color-white)!important;}
.sige-notas-page th.trim3{background:var(--sg-theme-primary,var(--color-brand-500))!important;color:var(--color-white)!important;}
.sige-notas-page th.th-mfd{background:var(--color-brand-500)!important;color:var(--color-white)!important;}
.sige-notas-page th.th-exame{background:var(--color-danger-500)!important;color:var(--color-white)!important;}
.sige-notas-page th.th-nf{background:var(--color-danger-700)!important;color:var(--color-white)!important;}
.sige-notas-page table.sige-notas tbody tr:hover{background:var(--color-white)!important;}
.sige-notas-page table.sige-notas tbody tr:nth-child(even){background:var(--color-white)!important;}
.sige-notas-page table.sige-notas tbody tr:nth-child(even):hover{background:var(--color-white)!important;}
.sige-notas-page td.aluno-nome{text-align:left!important;padding-left:12px!important;min-width:190px!important;font-weight:600!important;color:var(--color-ink-500)!important;}
.sige-notas-page td.aluno-num{font-weight:700!important;color:var(--sg-theme-primary,var(--color-brand-500))!important;}
.sige-notas-page table.sige-notas input.nota-input{width:58px!important;border:1px solid var(--color-slate-200)!important;border-radius:var(--radius-md)!important;padding:7px 5px!important;text-align:center!important;font-size:12px!important;background:var(--color-white)!important;transition:.18s ease!important;font-weight:600!important;color:var(--color-ink-500)!important;}
.sige-notas-page table.sige-notas input.nota-input:focus{outline:none!important;border-color:rgba(90,63,214,.55)!important;box-shadow:var(--shadow-xs);background:var(--color-white)!important;}
.sige-notas-page td.calc-med-acs{background:var(--color-success-50)!important;font-weight:700!important;color:var(--color-success-900)!important;}
.sige-notas-page td.calc-mt{background:var(--sg-theme-soft,var(--color-brand-50))!important;font-weight:700!important;color:var(--color-info-600)!important;}
.sige-notas-page td.calc-mfd{background:var(--color-warning-50)!important;font-weight:700!important;color:var(--color-warning-800)!important;font-size:var(--fs-sm)!important;}
.sige-notas-page td.calc-nf{background:var(--color-danger-50)!important;font-weight:700!important;color:var(--color-danger-700)!important;font-size:var(--fs-sm)!important;}
.sige-notas-page .sige-legenda{margin-top:14px!important;font-size:var(--fs-xs)!important;color:var(--color-slate-600)!important;display:flex!important;gap:var(--space-3)!important;flex-wrap:wrap!important;padding:0 var(--space-1)!important;}
.sige-notas-page .sige-legenda span{display:inline-flex!important;align-items:center!important;gap:6px!important;padding:6px 9px!important;border-radius:var(--radius-pill)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;box-shadow:var(--shadow-sm);}
@media(max-width:980px){.sige-notas-page .sige-notas-hero{grid-template-columns:1fr!important;padding:26px 24px!important;}.sige-notas-page .sige-filtros{display:grid!important;grid-template-columns:1fr!important;}.sige-notas-page .sige-filtros select{min-width:100%!important;}.sige-notas-page .sige-context-bar{align-items:flex-start!important;}.sige-notas-page .sige-btn-save{width:100%!important;}}
@media(max-width:680px){.sige-notas-page .sige-notas-hero h1{font-size:var(--fs-xl)!important;}.sige-notas-page .sige-notas-hero-actions{display:grid!important;grid-template-columns:1fr!important;}.sige-notas-page .sige-notas-hero-btn{width:100%!important;}}

</style>



<div class="sige-ficha-wrap sige-notas-page">



<section class="sige-page-hero sige-notas-hero" aria-label="Lançamento de Notas">
    <div class="sige-notas-hero-main">
        <div class="sige-notas-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('edit') : ''; ?><span>Área Docente</span></div>
        <h1>Lançamento de Notas</h1>
        <p>Registe avaliações por turma, disciplina e ano lectivo, mantendo as regras académicas, permissões e validações já definidas pela escola.</p>
        <div class="sige-notas-hero-actions" aria-label="Atalhos do lançamento de notas">
            <span class="sige-notas-hero-btn sige-notas-hero-btn-primary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> Ano Lectivo <?php echo esc_html((string) $ano_lectivo); ?></span>
            <span class="sige-notas-hero-btn sige-notas-hero-btn-secondary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?> Regras Académicas Activas</span>
        </div>
    </div>
    <aside class="sige-notas-hero-panel" aria-label="Resumo do lançamento">
        <div class="sige-notas-panel-label"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('book') : ''; ?><span>Contexto</span></div>
        <div class="sige-notas-panel-number"><?php echo esc_html($turma_id && $disciplina_id ? 'Activo' : 'Preparar'); ?></div>
        <p class="sige-notas-panel-text"><?php echo esc_html($turma_id && $disciplina_id ? 'Turma e disciplina seleccionadas. Pode rever os alunos e lançar as notas conforme as permissões.' : 'Seleccione a turma, a disciplina e o ano lectivo para iniciar o lançamento de notas.'); ?></p>
        <div class="sige-notas-panel-track" aria-hidden="true"><span style="width:<?php echo esc_attr($turma_id && $disciplina_id ? 100 : ($turma_id ? 55 : 25)); ?>%;"></span></div>
    </aside>
</section>



<!-- FILTROS -->

<?php if (!$is_supervisor && !$prof_sige_id): ?>
<div class="sige-notas-alert sige-notas-alert-warning">
    <span class="sige-notas-alert-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?></span>
    <div>
        <strong>Conta não vinculada</strong>
        <p>O seu utilizador WordPress não está vinculado a nenhum registo de professor no SIGE. Contacte a administração para que o seu perfil seja vinculado correctamente.</p>
    </div>
</div>
<?php endif; ?>



<?php $sige_page_slug = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : ''; ?>



<form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>">



    <input type="hidden" name="page" value="<?php echo esc_attr($sige_page_slug); ?>">



    <input type="hidden" name="view" value="notas">



    <div class="sige-filtros">



        <div>



            <label>Turma</label>



            <select name="turma_id" onchange="this.form.submit()">



                <option value="">-- Seleccionar --</option>



                <?php foreach ($turmas as $t): ?>



                    <option value="<?php echo esc_attr($t->id); ?>" <?php selected($turma_id, $t->id); ?>>



                        <?php echo esc_html($t->classe . 'ª Classe - Turma ' . $t->nome . ' (' . $t->ano_lectivo . ')'); ?>



                    </option>



                <?php endforeach; ?>



            </select>



        </div>



        <?php if ($turma_id && !empty($disciplinas)): ?>



        <div>



            <label>Disciplina</label>



            <select name="disciplina_id" onchange="this.form.submit()">



                <option value="">-- Seleccionar --</option>



                <?php foreach ($disciplinas as $d):



                    $cat = strtolower($d->categoria ?? '');



                    $badge = ($cat === 'nuclear') ? ' ★' : ' ○';



                ?>



                    <option value="<?php echo esc_attr($d->id); ?>" <?php selected($disciplina_id, $d->id); ?>>



                        <?php echo esc_html($d->nome . $badge); ?>



                    </option>



                <?php endforeach; ?>



            </select>



        </div>



        <?php endif; ?>



        <div>



            <label>Ano Lectivo</label>



            <select name="ano_lectivo" onchange="this.form.submit()">



                <?php for ($y = $sige_current_year_mz; $y >= 2020; $y--): ?>



                    <option value="<?php echo esc_attr($y); ?>" <?php selected($ano_lectivo, $y); ?>><?php echo esc_attr($y); ?></option>



                <?php endfor; ?>



            </select>



        </div>



    </div>



</form>



<?php if (!empty($sige_notas_perm_msg)): ?>

    <div class="sige-notice-locked"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('lock') : ''; ?> <span><?php echo esc_html($sige_notas_perm_msg); ?></span></div>

<?php endif; ?>

<?php if (!empty($_GET['saved'])): ?>



    <div class="sige-notice-saved"><?php if ($is_supervisor): ?><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?> <span>Notas guardadas e aprovadas!</span><?php else: ?><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('edit') : ''; ?> <span>Notas submetidas - aguardam aprovação do Director Pedagógico.</span><?php endif; ?></div>



<?php endif; ?>



<?php if ($turma_id && $disciplina_id && $sige_can_lancar_notas && !$sige_can_editar_notas_no_contexto): ?>

    <div class="sige-notice-locked"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?> <span>Pode lançar notas em campos vazios, mas a correcção de notas já lançadas exige que a turma e a disciplina estejam atribuídas ao seu perfil.</span></div>

<?php endif; ?>

<?php if ($notas_locked): ?>



    <div class="sige-notice-locked"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('lock') : ''; ?> <span><?php echo esc_html($notas_lock_message ?: 'Edição bloqueada.'); ?></span></div>



<?php endif; ?>



<?php if ($turma_id && $disciplina_id && !empty($alunos)): ?>



<form method="POST" id="form-notas">



    <?php wp_nonce_field('sige_notas_nonce'); ?>



    <input type="hidden" name="sige_save_notas" value="1">



    <input type="hidden" name="turma_id"        value="<?php echo esc_attr($turma_id); ?>">



    <input type="hidden" name="disciplina_id"   value="<?php echo esc_attr($disciplina_id); ?>">



    <input type="hidden" name="ano_lectivo"     value="<?php echo esc_attr($ano_lectivo); ?>">



    <div class="sige-context-bar">



        <div class="sige-context-bar__main">



            <span class="sige-context-item"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('book') : ''; ?> <strong><?php echo esc_html($disciplina_info->nome ?? ''); ?></strong></span>



            <span class="sige-context-item"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('school') : ''; ?> <strong><?php echo esc_html(($turma_info->classe ?? '') . 'ª Classe - Turma ' . ($turma_info->nome ?? '')); ?></strong></span>



            <span class="sige-context-item"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> <strong><?php echo esc_attr($ano_lectivo); ?></strong></span>



            <?php if ($eh_fim_ciclo): ?>



                <span class="sige-context-cycle"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('award') : ''; ?> FIM DE CICLO<?php if ($exige_avaliacao_final): ?> - 3º Trim. tem <?php echo esc_html($label_avaliacao_final); ?><?php endif; ?></span>



            <?php endif; ?>



            <?php



            $cat_disc = strtolower($disciplina_info->categoria ?? '');
            $is_nuclear_disc = ($cat_disc === 'nuclear');
            // Avaliação final sistemática: AF só na 3.ª classe; demais classes usam Exame/NF.
            $_classe_n = $classe_num_atual;
            $show_af = ($eh_fim_ciclo && $exige_avaliacao_final && $is_nuclear_disc);
            $_af_label = $label_avaliacao_final;
            $_nf_label = $label_nota_final;



            if ($cat_disc === 'nuclear'): ?>



                &nbsp;<span style="background:var(--color-success-900); color:var(--color-white); padding:2px 8px; border-radius:10px; font-size:11px;">★ NUCLEAR</span>



            <?php elseif ($cat_disc): ?>



                &nbsp;<span style="background:var(--color-slate-700); color:var(--color-white); padding:2px 8px; border-radius:10px; font-size:11px;">○ AUXILIAR</span>



            <?php endif; ?>



        </div>



        <?php if (!$notas_locked && $sige_can_lancar_notas): ?>



            <button type="submit" class="sige-btn-save"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?> Guardar Notas</button>



        <?php endif; ?>



    </div>



    <div class="sige-table-scroll">



    <table class="sige-notas">



        <thead>



            <tr>



                <th rowspan="3" style="min-width:30px">Nº</th>



                <th rowspan="3" style="min-width:160px; text-align:left; padding-left:8px;">Nome Completo</th>



                <th rowspan="3" style="width:28px">G</th>



                <?php foreach ([1 => 'I', 2 => 'II', 3 => 'III'] as $trim => $rom): ?>



                <th colspan="5" class="trim<?php echo $trim; ?>">



                    <?php echo $rom; ?>º Trimestre



                    



                </th>



                <?php endforeach; ?>



                <th rowspan="3" class="th-mfd">MFD</th>

                <?php if ($show_af): ?>
                <th rowspan="3" class="th-exame" style="background:var(--color-danger-500)!important;color:var(--color-white)!important;"><?php echo esc_html($_af_label); ?></th>
                <?php endif; ?>

                <?php if ($show_af): ?>



                <th rowspan="3" class="th-nf"><?php echo isset($_nf_label) ? esc_html($_nf_label) : "NF"; ?></th>



                <?php endif; ?>



            </tr>



            <tr>



                <?php foreach ([1,2,3] as $trim): ?>



                <th colspan="3" class="trim<?php echo $trim; ?>" style="font-size:10px;">ACS</th>



                <th class="trim<?php echo $trim; ?>" style="font-size:10px;">



                    AT



                </th>



                <th class="trim<?php echo $trim; ?>" style="font-size:10px; font-style:italic;">MT</th>



                <?php endforeach; ?>



            </tr>



            <tr>



                <?php foreach ([1,2,3] as $trim): ?>



                <th class="trim<?php echo $trim; ?>" style="font-size:10px;">1ª</th>



                <th class="trim<?php echo $trim; ?>" style="font-size:10px;">2ª</th>



                <th style="background:var(--color-success-800) !important; color:var(--color-white); font-size:10px;">Méd</th>



                <th class="trim<?php echo $trim; ?>" style="font-size:10px;">



                    AT



                </th>



                <th style="background:var(--color-info-700) !important; color:var(--color-white); font-size:10px;">MT</th>



                <?php endforeach; ?>



            </tr>



        </thead>



        <tbody>



        <?php



        $num = 1;



        foreach ($alunos as $aluno):



            // Ler avaliação final (nota_at do T3, só para disciplina nuclear elegível)
            $af_val = '';
            if ($show_af) {
                $_n3_af = $notas_mapa[$aluno->id][3] ?? null;
                $af_val = ($_n3_af && isset($_n3_af->nota_at) && $_n3_af->nota_at !== null) ? $_n3_af->nota_at : '';
            }

            // Pré-calcular todos os MTs para MFD



            $mts = [];



            for ($trim = 1; $trim <= 3; $trim++) {



                $n = $notas_mapa[$aluno->id][$trim] ?? null;



                if ($n && $n->nota_ac !== null && $n->nota_acp !== null && $n->nota_exame !== null) {



                    $med_acs = ((float)$n->nota_ac + (float)$n->nota_acp) / 2;



                    $mts[$trim] = (int) round((2 * $med_acs + (float)$n->nota_exame) / 3, 0);



                }



            }



            $mfd = (count($mts) === 3) ? (int) round(array_sum($mts) / 3, 0) : null;



            // NF apenas se fim de ciclo e exame do T3 existir



            $nf = null;



            if ($show_af && $mfd !== null) {



                $regra = function_exists('sige_get_regra_academica') ? sige_get_regra_academica($ano_lectivo, (int)preg_replace('/\D+/', '', (string)($turma_info->classe ?? ''))) : null;



                $peso_mfd   = $regra ? (int)$regra->peso_mfd   : 60;



                $peso_exame = $regra ? (int)$regra->peso_exame : 40;



                $n3 = $notas_mapa[$aluno->id][3] ?? null;



                if ($n3 && isset($n3->nota_at) && $n3->nota_at !== null) {



                    $den = $peso_mfd + $peso_exame;



                    if ($den > 0) $nf = (int) round(($mfd * $peso_mfd + (float)$n3->nota_at * $peso_exame) / $den, 0);



                }



            }



        ?>



        <tr>



            <td class="aluno-num"><?php echo $num++; ?></td>



            <td class="aluno-nome"><?php echo esc_html($aluno->nome_completo); ?></td>



            <td style="font-size:11px;"><?php echo esc_html($aluno->genero ?? '-'); ?></td>



            <?php foreach ([1,2,3] as $trim):



                $n   = $notas_mapa[$aluno->id][$trim] ?? null;



                $ac  = ($n && $n->nota_ac    !== null) ? $n->nota_ac    : '';



                $acp = ($n && $n->nota_acp   !== null) ? $n->nota_acp   : '';



                $at  = ($n && $n->nota_exame !== null) ? $n->nota_exame : '';
                $af_val = ($n && isset($n->nota_at) && $n->nota_at !== null) ? $n->nota_at : '';



                $base = "notas[{$aluno->id}][{$trim}]";



                $med_acs_display = '';



                $mt_display = '';



                if ($ac !== '' && $acp !== '') {



                    $med_acs_display = round(((float)$ac + (float)$acp) / 2, 1);



                    if ($at !== '') {



                        $mt_display = (int) round((2 * $med_acs_display + (float)$at) / 3, 0);



                    }



                }



                $input_class  = 'at-input';



                $tem_valores_lancados = ($ac !== '' || $acp !== '' || $at !== '');
                $readonly     = ($notas_locked || !$sige_can_lancar_notas || ($tem_valores_lancados && !$sige_can_editar_notas_no_contexto)) ? 'readonly' : '';



            ?>



            <!-- ACS 1ª -->



            <td><input type="number" class="nota-input" name="<?php echo $base; ?>[ac]"



                value="<?php echo esc_attr($ac); ?>" min="0" max="20" step="0.01" <?php echo $readonly; ?>



                oninput="sigCalc(this)" placeholder="-"></td>



            <!-- ACS 2ª -->



            <td><input type="number" class="nota-input" name="<?php echo $base; ?>[acp]"



                value="<?php echo esc_attr($acp); ?>" min="0" max="20" step="0.01" <?php echo $readonly; ?>



                oninput="sigCalc(this)" placeholder="-"></td>



            <!-- Méd ACS (calculado) -->



            <td class="calc-med-acs"



                data-a="<?php echo $aluno->id; ?>" data-t="<?php echo $trim; ?>" data-c="med">



                <?php echo $med_acs_display !== '' ? number_format((float)$med_acs_display, 1) : '-'; ?>



            </td>



            <!-- AT ou Exame Final -->



            <td><input type="number" class="nota-input <?php echo $input_class; ?>"



                name="<?php echo $base; ?>[exame]"



                value="<?php echo esc_attr($at); ?>" min="0" max="20" step="0.01" <?php echo $readonly; ?>



                oninput="sigCalc(this)" placeholder="-"



                data-a="<?php echo $aluno->id; ?>" data-t="<?php echo $trim; ?>" data-c="at_input">



            </td>



            <!-- MT (calculado) -->



            <td class="calc-mt"



                data-a="<?php echo $aluno->id; ?>" data-t="<?php echo $trim; ?>" data-c="mt">



                <?php echo $mt_display !== '' ? $mt_display : '-'; ?>



            </td>



            <?php endforeach; // fim trimestres ?>



            <!-- MFD -->



            <td class="calc-mfd" data-a="<?php echo $aluno->id; ?>" data-c="mfd">



                <?php echo $mfd !== null ? $mfd : '-'; ?>



            </td>

            <!-- Avaliação final (AF apenas na 3.ª; Exame nas restantes classes elegíveis) -->
            <?php if ($show_af): ?>
            <td><input type="number" class="nota-input exame-input" 
                name="notas[<?php echo $aluno->id; ?>][af]"
                value="<?php echo esc_attr($af_val); ?>" min="0" max="20" step="0.01" <?php echo ($notas_locked || !$sige_can_lancar_notas || ($af_val !== '' && !$sige_can_editar_notas_no_contexto)) ? 'readonly' : ''; ?>
                oninput="sigCalc(this)" placeholder="-"
                data-a="<?php echo $aluno->id; ?>" data-c="af_input"
                style="width:60px;background:var(--color-danger-100);border-color:var(--color-danger-500);"></td>
            <?php endif; ?>

            <!-- Nota final ponderada (apenas quando há avaliação final configurada) -->



            <?php if ($show_af): ?>



            <td class="calc-nf" data-a="<?php echo $aluno->id; ?>" data-c="nf">



                <?php echo $nf !== null ? $nf : '-'; ?>



            </td>



            <?php endif; ?>



        </tr>



        <?php endforeach; ?>



        </tbody>



    </table>



    </div>



    <?php if (!$notas_locked && $sige_can_lancar_notas): ?>



    <div style="margin-top:14px; display:flex; justify-content:flex-end;">



        <button type="submit" class="sige-btn-save"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?> Guardar Notas</button>



    </div>



    <?php endif; ?>



</form>



<div class="sige-legenda">



    <span><span class="leg-box" style="background:var(--color-slate-100);border:1px solid var(--color-slate-400);"></span> Méd ACS = Média(ACS1, ACS2)</span>



    <span><span class="leg-box" style="background:var(--color-info-100);border:1px solid var(--color-slate-400);"></span> MT = ROUND((2×MédACS + AT) / 3, 0)</span>



    <span><span class="leg-box" style="background:var(--color-warning-200);border:1px solid var(--color-slate-400);"></span> MFD = ROUND((MT1+MT2+MT3) / 3, 0)</span>



    <?php if ($show_af):



        $regra_leg = function_exists('sige_get_regra_academica') ? sige_get_regra_academica($ano_lectivo, (int)preg_replace('/\D+/', '', (string)($turma_info->classe ?? ''))) : null;



        $pm = $regra_leg ? (int)$regra_leg->peso_mfd : 60;



        $pe = $regra_leg ? (int)$regra_leg->peso_exame : 40;



    ?>



    <span><span class="leg-box" style="background:var(--color-danger-100);border:1px solid var(--color-slate-400);"></span>



        <?php echo isset($_nf_label) ? esc_html($_nf_label) : "NF"; ?> = (MFD×<?php echo $pm; ?>% + <?php echo isset($_af_label) ? esc_html($_af_label) : "Exame"; ?>×<?php echo $pe; ?>%) / 100



    </span>



    <?php endif; ?>



    <span>★ = Disciplina Nuclear &nbsp; ○ = Auxiliar</span>



</div>



<?php elseif ($turma_id && $disciplina_id && empty($alunos)): ?>



    <div style="padding:20px; color:var(--color-slate-500); text-align:center;">Nenhum aluno matriculado activo nesta turma/ano.</div>



<?php elseif ($turma_id && !$disciplina_id): ?>



    <div style="padding:20px; color:var(--color-ink-800); text-align:center; font-size:14px;">← Selecciona uma disciplina para lançar notas.</div>



<?php else: ?>



    <div style="padding:20px; color:var(--color-ink-800); text-align:center; font-size:14px;">← Selecciona uma turma para começar.</div>



<?php endif; ?>



</div><!-- /wrap -->



<script <?php echo sige_csp_script_attr(); ?>>



/**



 * Cálculos em tempo real (igual ao Excel PROTOCOLO_2025)



 * MT = ROUND((2 × MédACS + AT) / 3, 0)



 * MFD = ROUND((MT1 + MT2 + MT3) / 3, 0)



 * NF = ROUND((MFD × pm + Exame_T3 × pe) / (pm+pe), 0)



 */



var ehFimCiclo = <?php echo $show_af ? 'true' : 'false'; ?>;



var pesoMfd    = <?php



    $r = function_exists('sige_get_regra_academica') ? sige_get_regra_academica($ano_lectivo, (int)preg_replace('/\D+/', '', (string)($turma_info->classe ?? ''))) : null;



    echo $r ? (int)$r->peso_mfd : 60;



?>;



var pesoExame  = <?php echo $r ? (int)$r->peso_exame : 40; ?>;



function sigCalc(input) {



    var tr = input.closest('tr');



    if (!tr) return;



    var m = input.name.match(/notas\[(\d+)\]/);



    if (!m) return;



    var aid = m[1];



    var mts = {};



    [1, 2, 3].forEach(function(t) {



        var ac  = parseFloat(tr.querySelector('input[name="notas['+aid+']['+t+'][ac]"]')?.value);



        var acp = parseFloat(tr.querySelector('input[name="notas['+aid+']['+t+'][acp]"]')?.value);



        var at  = parseFloat(tr.querySelector('input[name="notas['+aid+']['+t+'][exame]"]')?.value);



        var tdMed = tr.querySelector('td[data-a="'+aid+'"][data-t="'+t+'"][data-c="med"]');



        var tdMt  = tr.querySelector('td[data-a="'+aid+'"][data-t="'+t+'"][data-c="mt"]');



        if (!isNaN(ac) && !isNaN(acp)) {



            var med = (ac + acp) / 2;



            if (tdMed) tdMed.textContent = med.toFixed(1);



            if (!isNaN(at)) {



                var mt = Math.round((2 * med + at) / 3);



                if (tdMt) tdMt.textContent = mt;



                mts[t] = mt;



            } else {



                if (tdMt) tdMt.textContent = '-';



            }



        } else {



            if (tdMed) tdMed.textContent = '-';



            if (tdMt)  tdMt.textContent  = '-';



        }



    });



    var tdMfd = tr.querySelector('td[data-a="'+aid+'"][data-c="mfd"]');



    var tdNf  = tr.querySelector('td[data-a="'+aid+'"][data-c="nf"]');



    var _mt1 = (mts[1] !== undefined) ? mts[1] : 0;
    var _mt2 = (mts[2] !== undefined) ? mts[2] : 0;
    var _mt3 = (mts[3] !== undefined) ? mts[3] : 0;
    var _hasAny = (mts[1] !== undefined || mts[2] !== undefined || mts[3] !== undefined);

    if (_hasAny) {



        var mfd = Math.round((_mt1 + _mt2 + _mt3) / 3);



        if (tdMfd) tdMfd.textContent = mfd;



        if (ehFimCiclo && tdNf) {



            var afVal = parseFloat(tr.querySelector('input[name="notas['+aid+'][af]"]')?.value);



            if (!isNaN(afVal)) {



                var den = pesoMfd + pesoExame;



                var nf = Math.round((mfd * pesoMfd + afVal * pesoExame) / den);



                tdNf.textContent = nf;



            } else {



                tdNf.textContent = '-';



            }



        }



    } else {



        if (tdMfd) tdMfd.textContent = '-';



        if (tdNf)  tdNf.textContent  = '-';



    }



}



</script>
