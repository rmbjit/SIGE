<?php
/**
 * SIGE SoftGenial - Acta do Conselho de Notas (CRUD)
 * Vista: ?page=sige-app&view=acta
 *
 * Sprint 2 · M2 - Interface para popular a ACTA antes de imprimir.
 *
 * Secções editáveis:
 *   1. Cabeçalho (data, sala, hora, presidido_por)
 *   2. Presenças (presentes + ausentes)
 *   3. Nota Votada (notas alteradas pelo conselho)
 *   4. Cumprimento dos Programas (último tema + atrasos)
 *   5. Observações (como decorreu + observação DP)
 *
 * Acesso:
 *   Director, Dir. Pedagógico, Secretaria Geral, TI (manage_options).
 *
 * POST:
 *   admin-post.php?action=sige_acta_guardar (via sige-acta-pdf-handler)
 *
 * Impressão:
 *   admin-post.php?action=sige_acta_pdf
 *
 * @since 12.2.0
 */
if (!defined('ABSPATH')) exit;

// [12.9.6] Guarda centralizada - matriz SIGE manda; WP roles ficam como fallback
// para utilizadores sem perfil SIGE atribuído.
if (!sige_page_guard(
    ['academico.actas_ver','academico.actas_emitir'],
    ['sige_professor','sige_director','sige_pedagogico','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;

global $wpdb;
$p   = $wpdb->prefix;
$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
if (function_exists('sige_acta_pro_ensure_schema')) sige_acta_pro_ensure_schema();

$tT   = $p . 'sige_turmas';
$tA   = $p . 'sige_alunos';
$tM   = $p . 'sige_matriculas';
$tD   = $p . 'sige_disciplinas';
$tTD  = $p . 'sige_turma_disciplinas';
$tAC  = $p . 'sige_acta_conselho_notas';

// ─── Filtros ─────────────────────────────────────────────────────────────
$ano_act   = function_exists('sige_get_ano_lectivo_atual') ? (int) sige_get_ano_lectivo_atual() : (int) wp_date('Y');
$turma_id  = isset($_GET['turma_id'])  ? (int) $_GET['turma_id']  : 0;
$ano_sel   = isset($_GET['ano'])       ? (int) $_GET['ano']       : $ano_act;
$trimestre = isset($_GET['trimestre']) ? (int) $_GET['trimestre'] : 1;
if ($trimestre < 1 || $trimestre > 3) $trimestre = 1;

$flash_saved = isset($_GET['saved']) && (int) $_GET['saved'] === 1;
$acta_apply_status = isset($_GET['acta_apply']) ? sanitize_text_field(wp_unslash($_GET['acta_apply'])) : '';
$acta_apply_msg = isset($_GET['acta_msg']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['acta_msg']))) : '';

// [12.11.9.15] Escopo docente: professor só vê ACTA das suas turmas.
$sige_acta_scoped_professor = function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user();
$sige_acta_professor_id = ($sige_acta_scoped_professor && function_exists('sige_get_professor_atual_id')) ? (int) sige_get_professor_atual_id() : 0;
$sige_acta_scope_msg = '';

// ─── Lista de turmas ─────────────────────────────────────────────────────
if ($sige_acta_scoped_professor) {
    if ($sige_acta_professor_id > 0) {
        $turmas = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT t.id, COALESCE(NULLIF(t.nome_turma,''), t.nome) AS nome_turma, t.classe, t.ano_lectivo, t.turno
             FROM {$tT} t
             WHERE t.escola_id = %d AND (t.status_turma IS NULL OR t.status_turma IN('activa','ativa'))
               AND (t.director_turma_id = %d OR t.id IN (SELECT turma_id FROM {$tTD} WHERE professor_id = %d AND escola_id = %d))
             ORDER BY t.ano_lectivo DESC, CAST(t.classe AS UNSIGNED) ASC, nome_turma ASC",
            $eid, $sige_acta_professor_id, $sige_acta_professor_id, $eid
        ));
        if (empty($turmas)) {
            $sige_acta_scope_msg = 'O seu perfil de professor ainda não tem turmas atribuídas para consultar a Acta do Conselho.';
        }
    } else {
        $turmas = [];
        $sige_acta_scope_msg = 'O seu utilizador ainda não está vinculado a um registo de professor no SIGE.';
    }
} else {
    $turmas = $wpdb->get_results($wpdb->prepare(
        "SELECT id, COALESCE(NULLIF(nome_turma,''), nome) AS nome_turma, classe, ano_lectivo, turno
         FROM {$tT}
         WHERE escola_id = %d AND (status_turma IS NULL OR status_turma IN('activa','ativa'))
         ORDER BY ano_lectivo DESC, CAST(classe AS UNSIGNED) ASC, nome_turma ASC",
        $eid
    ));
}

// ─── Carregar ACTA ───────────────────────────────────────────────────────
$turma = null;
$acta  = null;
$acta_id = 0;
$presentes = $ausentes = $nota_votada = $cumprimento = [];
$discs_turma = $alunos_turma = [];
$acta_ready = [
    'alunos' => 0,
    'disciplinas' => 0,
    'esperadas' => 0,
    'aprovadas' => 0,
    'pendentes' => 0,
    'em_falta' => 0,
    'percentual' => 0,
];
$pode_aprovar_nota_votada = function_exists('sige_acta_can_aprovar_nota_votada') ? sige_acta_can_aprovar_nota_votada() : false;
$acta_return_url = admin_url('admin.php?page=sige-app&view=acta');
$acta_censo_snapshot = null;
$acta_censo_sugestao = ['M' => 0, 'HM' => 0];
$acta_censo_stale = false;
$acta_genero_diag = [];
$acta_genero_diag_u = 0;
$acta_censo_confirmador_nome = '';
$acta_censo_info = function_exists('sige_acta_data_corte_info') ? sige_acta_data_corte_info($ano_sel) : ['data' => sprintf('%04d-03-03', $ano_sel), 'label' => '3 de Março'];

$acta_can_save = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
              || current_user_can('sige_director')
              || current_user_can('sige_pedagogico')
              || current_user_can('sige_secretaria_geral');
if (function_exists('sige_user_can_any_secure')) {
    $acta_can_save = sige_user_can_any_secure(['academico.actas_emitir','academico.aprovar_notas','academico.pautas_emitir'], ['sige_director','sige_pedagogico','sige_secretaria_geral']);
}
$pode_confirmar_censo = (bool) $acta_can_save;

if ($turma_id > 0) {
    $turma = $wpdb->get_row($wpdb->prepare(
        "SELECT id, COALESCE(NULLIF(nome_turma,''), nome) AS nome_turma, classe, turno
         FROM {$tT} WHERE id = %d AND escola_id = %d LIMIT 1",
        $turma_id, $eid
    ));

    if ($turma && $sige_acta_scoped_professor) {
        $can_turma = function_exists('sige_professor_can_access_turma') && sige_professor_can_access_turma((int)$turma_id, $sige_acta_professor_id, $eid);
        if (!$can_turma) {
            $turma = null;
            $turma_id = 0;
            $sige_acta_scope_msg = 'Esta Acta pertence a uma turma que não está atribuída ao seu perfil de professor.';
        }
    }

    if ($turma && function_exists('sige_acta_get_or_create')) {
        $acta_id = sige_acta_get_or_create($turma_id, $ano_sel, $trimestre);
        $acta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tAC} WHERE id = %d AND escola_id = %d LIMIT 1",
            $acta_id, $eid
        ));

        $acta_censo_info = function_exists('sige_acta_data_corte_info') ? sige_acta_data_corte_info($ano_sel) : ['data' => sprintf('%04d-03-03', $ano_sel), 'label' => '3 de Março'];
        $acta_censo_snapshot = function_exists('sige_acta_censo_get_snapshot') ? sige_acta_censo_get_snapshot($turma_id, $ano_sel, $eid) : null;
        $acta_censo_sugestao = function_exists('sige_acta_censo_sugestao_base') ? sige_acta_censo_sugestao_base($turma_id, $ano_sel, $eid) : ['M' => 0, 'HM' => 0];
        // v12.11.9.17 - Detecta snapshot de Censo confirmado antes da correcção de género.
        // Mesmo total HM mas Mulheres diferentes = bug antigo (caso 3 A / 4 A / 5 A).
        $acta_censo_stale = false;
        if ($acta_censo_snapshot) {
            $acta_censo_stale = ((int)$acta_censo_snapshot->hm_total === (int)($acta_censo_sugestao['HM'] ?? 0))
                && ((int)$acta_censo_snapshot->m_mulheres !== (int)($acta_censo_sugestao['M'] ?? 0));
        }
        // v12.11.9.18 - Diagnóstico de género da turma: mostra os valores REAIS
        // gravados em sige_alunos.genero e como cada um é classificado. Ajuda a
        // ver de imediato porque é que uma turma diverge (ex.: valores estranhos).
        $acta_genero_diag = [];
        $acta_genero_diag_u = 0;
        $diag_rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(a.genero,'') AS g, COUNT(*) AS c
             FROM {$wpdb->prefix}sige_alunos a
             INNER JOIN {$wpdb->prefix}sige_matriculas m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
             WHERE a.escola_id = %d AND m.turma_id = %d AND m.ano_lectivo = %d
               AND (m.status_matricula IS NULL OR m.status_matricula IN ('activa','ativa','activo','ativo'))
             GROUP BY g ORDER BY c DESC",
            $eid, $turma_id, $ano_sel
        ));
        foreach ($diag_rows as $dr) {
            $bucket = function_exists('sige_genero_bucket') ? sige_genero_bucket($dr->g) : 'U';
            $label  = function_exists('sige_genero_label') ? sige_genero_label($bucket) : $bucket;
            $raw    = ($dr->g === '' ? '(vazio)' : $dr->g);
            $acta_genero_diag[] = ['raw' => $raw, 'c' => (int)$dr->c, 'bucket' => $bucket, 'label' => $label];
            if ($bucket === 'U') $acta_genero_diag_u += (int)$dr->c;
        }
        if ($acta_censo_snapshot && !empty($acta_censo_snapshot->confirmado_por)) {
            $u_conf = get_userdata((int) $acta_censo_snapshot->confirmado_por);
            $acta_censo_confirmador_nome = $u_conf ? (string) $u_conf->display_name : ('Utilizador #' . (int) $acta_censo_snapshot->confirmado_por);
        }

        $presencas_all = function_exists('sige_acta_load_presencas')
            ? sige_acta_load_presencas($acta_id, $eid) : [];
        foreach ($presencas_all as $pr) {
            if ($pr->status === 'ausente') $ausentes[] = $pr; else $presentes[] = $pr;
        }

        $nota_votada = function_exists('sige_acta_load_nota_votada')
            ? sige_acta_load_nota_votada($acta_id, $eid) : [];
        $cumprimento = function_exists('sige_acta_load_cumprimento_programas')
            ? sige_acta_load_cumprimento_programas($acta_id, $eid) : [];

        // Disciplinas reais da turma - fonte da verdade para a ACTA.
        // A matriz curricular serve apenas para ordenar; a lista vem sempre do vínculo da turma.
        $discs_turma = function_exists('sige_acta_get_disciplinas_turma')
            ? sige_acta_get_disciplinas_turma($turma_id, $eid, (string)($turma->classe ?? ''))
            : $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT d.id, d.nome, d.sigla, COALESCE(mc.ordem_pauta, d.ordem, 999) AS ordem_pauta
                 FROM {$tTD} td
                 INNER JOIN {$tD} d ON d.id = td.disciplina_id AND d.escola_id = td.escola_id
                 LEFT JOIN {$p}sige_matriz_curricular mc
                        ON mc.disciplina_id = td.disciplina_id
                       AND mc.escola_id = td.escola_id
                       AND mc.classe = %s
                 WHERE td.turma_id = %d AND td.escola_id = %d
                   AND (d.activo IS NULL OR d.activo = 1)
                 ORDER BY COALESCE(mc.ordem_pauta, d.ordem, 999) ASC, d.nome ASC",
                (string)($turma->classe ?? ''), $turma_id, $eid
            ));

        // Alunos activos da turma
        $alunos_turma = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.nome_completo, a.numero_processo
             FROM {$tA} a
             INNER JOIN {$tM} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
             WHERE a.escola_id = %d AND m.turma_id = %d AND m.ano_lectivo = %d
               AND (m.status_matricula IS NULL OR m.status_matricula = 'activa')
             ORDER BY a.nome_completo ASC",
            $eid, $turma_id, $ano_sel
        ));

        // v12.10.77 - Prontidão académica da acta: visão mínima antes do Conselho.
        $acta_ready['alunos'] = count($alunos_turma);
        $acta_ready['disciplinas'] = count($discs_turma);
        $acta_ready['esperadas'] = $acta_ready['alunos'] * $acta_ready['disciplinas'];

        if ($acta_ready['esperadas'] > 0) {
            $ids_alunos = array_map(static function($a){ return (int)$a->id; }, $alunos_turma);
            $ids_disc = array_map(static function($d){ return (int)$d->id; }, $discs_turma);
            if (!empty($ids_alunos) && !empty($ids_disc)) {
                $ph_al = implode(',', array_fill(0, count($ids_alunos), '%d'));
                $ph_di = implode(',', array_fill(0, count($ids_disc), '%d'));
                $params = array_merge($ids_alunos, $ids_disc, [$turma_id, $ano_sel, $trimestre, $eid]);

                $acta_ready['aprovadas'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT CONCAT(aluno_id,'-',disciplina_id))
                     FROM {$p}sige_notas
                     WHERE aluno_id IN ({$ph_al}) AND disciplina_id IN ({$ph_di})
                       AND turma_id = %d AND ano_lectivo = %d AND trimestre = %d AND escola_id = %d
                       AND (status IS NULL OR status = 'aprovado')",
                    $params
                ));

                $acta_ready['pendentes'] = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT CONCAT(aluno_id,'-',disciplina_id))
                     FROM {$p}sige_notas
                     WHERE aluno_id IN ({$ph_al}) AND disciplina_id IN ({$ph_di})
                       AND turma_id = %d AND ano_lectivo = %d AND trimestre = %d AND escola_id = %d
                       AND status IS NOT NULL AND status <> 'aprovado'",
                    $params
                ));
            }
            $acta_ready['em_falta'] = max(0, (int)$acta_ready['esperadas'] - (int)$acta_ready['aprovadas']);
            $acta_ready['percentual'] = (int) round(((int)$acta_ready['aprovadas'] / max(1, (int)$acta_ready['esperadas'])) * 100);
        }
        $acta_return_url = admin_url('admin.php?page=sige-app&view=acta&turma_id=' . $turma_id . '&ano=' . $ano_sel . '&trimestre=' . $trimestre);
    }
}

// ─── Docentes alocados à turma (pré-popular Presentes) ──────────────────
$docentes_sug = [];
if ($turma_id > 0 && !empty($discs_turma)) {
    // Tenta carregar via tabela de alocações / professores (se existir)
    $tP  = $p . 'sige_professores';
    $tAl = $p . 'sige_alunos'; // placeholder; nem todas as escolas têm tabela própria de alocações
    // Estratégia: para cada disciplina, olhar user_id alocado como docente
    // (usa user_meta sige_docente_disciplinas se existir; senão, lista vazia)
    foreach ($discs_turma as $d) {
        $docentes_sug[] = [
            'nome'            => '',
            'funcao'          => 'Docente',
            'disciplina_id'   => (int) $d->id,
            'disciplina_nome' => (string) $d->nome,
        ];
    }
    $docentes_sug[] = ['nome' => '', 'funcao' => 'Director(a) de Turma', 'disciplina_id' => null, 'disciplina_nome' => ''];
    $docentes_sug[] = ['nome' => '', 'funcao' => 'Director(a) Pedagógico(a)', 'disciplina_id' => null, 'disciplina_nome' => ''];
}

$pode_editar_obs_dp = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
                   || current_user_can('sige_director')
                   || current_user_can('sige_pedagogico');

$admin_post_url = esc_url(admin_url('admin-post.php'));
$nonce_guardar  = wp_create_nonce('sige_acta_guardar');
$nonce_pdf      = wp_create_nonce('sige_acta_pdf');

$acta_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'clipboard' => '<path d="M9 2h6a2 2 0 0 1 2 2v1h1a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1V4a2 2 0 0 1 2-2z"/><path d="M9 5h6"/><path d="M8 11h8"/><path d="M8 15h8"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'print' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
    ];
    $path = $map[$name] ?? $map['clipboard'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

?>
<style id="sige-acta-produto-pro-v121076">
/* SIGE SoftGenial v12.10.76 - Acta do Conselho: Compliance Visual Integral
   Escopo visual apenas: não altera gravação, impressão, presenças, nota votada,
   cumprimento dos programas, observações, permissões ou regras académicas. */
.acta-wrap{
    --acta-blue:var(--color-info-700);
    --acta-blue-dark:var(--color-info-800);
    --acta-purple:var(--color-brand-500);
    --acta-purple-soft:var(--color-brand-50);
    --acta-ink:var(--color-black);
    --acta-muted:var(--color-slate-700);
    --acta-line:var(--color-ink-100);
    --acta-green:var(--color-success-500);
    --acta-red:var(--color-danger-500);
    --acta-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--acta-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.acta-wrap *{box-sizing:border-box}
.acta-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal / Dashboard V2 MJS-grade */
.acta-header{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-lg);
    padding:32px 34px;
    margin:0;
    display:grid;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr);
    gap:22px;
    align-items:center;
    color:var(--acta-ink);
}
.acta-header:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.acta-header-main,.acta-header-panel{position:relative;z-index:1}
.acta-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    padding:0;
    border:0;
    background:transparent;
    color:var(--acta-blue);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.acta-header h1{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
    font-family:inherit;
}
.acta-header p{
    max-width:650px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
    opacity:1;
}
.acta-hero-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.acta-hero-chip{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    min-height:38px;
    padding:var(--space-2) var(--space-3);
    border-radius:var(--radius-pill);
    background:var(--color-white);
    border:1px solid var(--color-slate-100);
    color:var(--color-slate-700);
    font-size:12px;
    font-weight:700;
    box-shadow:var(--shadow-sm);
}
.acta-hero-chip svg{color:var(--acta-purple)}
.acta-header-panel{
    min-height:148px;
    border-radius:var(--radius-xl);
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:10px;
    border:1px solid rgba(92,64,187,.08);
}
.acta-header-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.acta-header-panel>*{position:relative;z-index:1}
.acta-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--acta-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.acta-panel-status{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.acta-panel-text{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* Filtros */
.acta-filters{
    background:var(--color-white);
    padding:18px;
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    display:grid;
    grid-template-columns:minmax(260px,1.4fr) minmax(180px,.7fr) minmax(180px,.7fr) auto;
    gap:var(--space-3);
    align-items:end;
    margin:0;
    box-shadow:var(--shadow-md);
}
.acta-filters label{
    display:flex;
    flex-direction:column;
    gap:7px;
    font-size:var(--fs-xs);
    color:var(--color-slate-600);
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.07em;
}
.acta-filters select,
.acta-filters input[type="number"]{
    width:100%;
    min-height:44px;
    padding:0 13px;
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-md);
    font-size:var(--fs-sm);
    color:var(--color-ink-500);
    font-weight:600;
    background:var(--color-white);
    box-shadow:var(--shadow-sm);
    outline:none;
}
.acta-filters select:focus,
.acta-filters input[type="number"]:focus,
.grid input:focus,.grid textarea:focus,.grid select:focus,
textarea.fullwidth:focus,
.ed-table input:focus,.ed-table select:focus,.ed-table textarea:focus{
    border-color:rgba(90,63,214,.55)!important;
    box-shadow:var(--shadow-xs);
    background:var(--color-white)!important;
    outline:none!important;
}
.acta-filters button{
    min-height:44px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    background:linear-gradient(135deg,var(--acta-blue),var(--acta-blue-dark));
    color:var(--color-white);
    padding:0 18px;
    border:none;
    border-radius:var(--radius-md);
    font-weight:700;
    cursor:pointer;
    font-size:var(--fs-sm);
    font-family:inherit;
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease;
}
.acta-filters button:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}

.acta-flash{
    display:flex;
    align-items:center;
    gap:10px;
    background:var(--color-success-50);
    color:var(--color-success-900);
    padding:var(--space-3) var(--space-4);
    border-radius:var(--radius-lg);
    border:1px solid var(--color-success-200);
    margin:0;
    font-weight:700;
    box-shadow:var(--shadow-sm);
}
.acta-status-badge{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:var(--radius-pill);
    font-size:var(--fs-xs);
    font-weight:700;
    background:var(--color-warning-50);
    color:var(--color-warning-800);
    margin-left:8px;
    vertical-align:middle;
    border:1px solid var(--color-warning-200);
}
.acta-status-badge.finalizada{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200)}

/* Cards */
.acta-card{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:20px 22px;
    margin:0;
    box-shadow:var(--shadow-md);
    overflow:auto;
}
.acta-card h2{
    margin:0 0 14px;
    font-size:17px;
    color:var(--color-ink-500);
    border-bottom:1px solid var(--color-slate-100);
    padding-bottom:12px;
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:700;
    letter-spacing:-.03em;
}
.acta-card h2 .c-idx{
    background:var(--color-brand-50);
    color:var(--acta-purple);
    width:34px;
    height:34px;
    border-radius:var(--radius-md);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:var(--fs-sm);
    font-weight:700;
    flex:0 0 auto;
}
.acta-card p{
    font-size:var(--fs-sm)!important;
    color:var(--color-slate-600)!important;
    margin:0 0 var(--space-3)!important;
    line-height:1.55;
    font-weight:600;
}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}
.grid label{
    display:flex;
    flex-direction:column;
    gap:7px;
    font-size:var(--fs-xs);
    color:var(--color-slate-600);
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.07em;
}
.grid input,.grid textarea,.grid select,textarea.fullwidth{
    width:100%;
    min-height:44px;
    padding:10px 13px;
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-md);
    font-size:var(--fs-sm);
    background:var(--color-white);
    color:var(--color-ink-500);
    font-family:inherit;
    font-weight:600;
    box-shadow:var(--shadow-sm);
}
.grid textarea,textarea.fullwidth{min-height:86px;line-height:1.5;resize:vertical}

/* Tabelas editáveis */
table.ed-table{
    width:100%;
    min-width:900px;
    border-collapse:separate;
    border-spacing:0;
    font-size:var(--fs-sm);
    margin-top:8px;
    background:var(--color-white);
    overflow:hidden;
}
.ed-table th,.ed-table td{
    border-right:1px solid var(--color-slate-100);
    border-bottom:1px solid var(--color-slate-100);
    padding:8px 9px;
    text-align:left;
    vertical-align:middle;
}
.ed-table th{
    background:var(--color-slate-50);
    font-weight:700;
    font-size:11.5px;
    color:var(--color-slate-800);
    text-align:center;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.ed-table tr:hover td{background:var(--color-white)}
.ed-table input,.ed-table select,.ed-table textarea{
    width:100%;
    min-height:36px;
    padding:7px 9px;
    border:1px solid transparent;
    border-radius:var(--radius-sm);
    font-size:var(--fs-sm);
    background:transparent;
    color:var(--color-ink-500);
    font-family:inherit;
}
.ed-table textarea{resize:vertical;min-height:34px}
.ed-table .col-narrow{width:70px;text-align:center}
.ed-table .col-med{width:120px}
.ed-table .row-actions{width:46px;text-align:center}
.btn-sm{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:34px;
    min-height:34px;
    background:var(--color-slate-50);
    border:1px solid var(--color-slate-100);
    padding:0 10px;
    border-radius:var(--radius-md);
    cursor:pointer;
    font-size:12px;
    font-weight:700;
    color:var(--color-slate-700);
    transition:background .18s ease,color .18s ease,transform .18s ease;
}
.btn-sm:hover{background:var(--color-brand-50);color:var(--acta-purple);transform:translateY(-1px)}
.btn-sm.danger{color:var(--color-danger-700);background:var(--color-danger-50);border-color:var(--color-danger-200)}
.btn-sm.danger:hover{background:var(--color-danger-100);color:var(--color-danger-800)}
.btn-add{
    margin-top:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    min-height:40px;
    background:var(--color-white);
    border:1px dashed var(--acta-blue);
    color:var(--acta-blue);
    padding:0 14px;
    border-radius:var(--radius-md);
    cursor:pointer;
    font-size:12.5px;
    font-weight:700;
    font-family:inherit;
}
.btn-add:hover{background:var(--color-info-50)}

/* Acções */
.acta-actions{
    display:flex;
    gap:var(--space-3);
    justify-content:flex-end;
    flex-wrap:wrap;
    padding:18px 0 0;
    border-top:1px solid var(--color-slate-100);
    margin-top:20px;
}
.btn-primary,.btn-accent{
    min-height:44px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:9px;
    padding:0 var(--space-5);
    border:none;
    border-radius:var(--radius-md);
    font-weight:700;
    cursor:pointer;
    font-size:var(--fs-sm);
    text-decoration:none;
    font-family:inherit;
    transition:transform .18s ease,box-shadow .18s ease;
}
.btn-primary{
    background:linear-gradient(135deg,var(--acta-blue),var(--acta-blue-dark));
    color:var(--color-white);
    box-shadow:var(--shadow-sm);
}
.btn-accent{
    background:var(--color-white);
    color:var(--color-ink-900);
    border:1px solid var(--color-ink-100);
    box-shadow:var(--shadow-sm);
}
.btn-primary:hover,.btn-accent:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}

.empty-state{
    padding:38px 20px;
    text-align:center;
    color:var(--color-slate-500);
    background:var(--color-white);
    border-radius:var(--radius-xl);
    border:1px dashed var(--color-slate-200);
    box-shadow:var(--shadow-md);
}
.empty-state h3{color:var(--color-black);margin:0 0 var(--space-2);font-size:18px;font-weight:700;letter-spacing:-.02em}
.empty-state p{margin:0;font-size:var(--fs-sm);line-height:1.55}


/* v12.10.77 - fluxo PRO da Acta */
.acta-ready{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin:0;
}
.acta-ready-card{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    gap:14px;
    align-items:center;
    min-height:96px;
    padding:16px 18px;
    border-radius:var(--radius-xl);
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-md);
}
.acta-ready-card:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--ready-soft,var(--color-brand-50))}
.acta-ready-icon{
    width:46px;
    height:46px;
    border-radius:var(--radius-lg);
    display:flex;
    align-items:center;
    justify-content:center;
    background:var(--ready-soft,var(--color-brand-50));
    color:var(--ready-color,var(--color-brand-500));
    position:relative;
    z-index:1;
}
.acta-ready-card>div:last-child{position:relative;z-index:1}
.acta-ready-label{display:block;color:var(--color-slate-600);font-size:12px;font-weight:700;margin-bottom:6px}
.acta-ready-value{display:block;color:var(--color-black);font-size:25px;line-height:1;font-weight:700;letter-spacing:-.03em}
.acta-ready-note{display:block;margin-top:6px;color:var(--color-ink-400);font-size:11.5px;font-weight:600}
.acta-ready-ok{--ready-color:var(--color-success-500);--ready-soft:var(--color-success-100)}
.acta-ready-warn{--ready-color:var(--color-warning-500);--ready-soft:var(--color-warning-50)}
.acta-ready-bad{--ready-color:var(--color-danger-500);--ready-soft:var(--color-danger-50)}
.acta-ready-info{--ready-color:var(--color-info-700);--ready-soft:var(--color-info-50)}

.acta-status-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:30px;
    padding:6px 10px;
    border-radius:var(--radius-pill);
    font-size:var(--fs-xs);
    font-weight:700;
    white-space:nowrap;
}
.acta-status-pendente_dp{background:var(--color-warning-50);color:var(--color-warning-800);border:1px solid var(--color-warning-300)}
.acta-status-aprovada_aplicada{background:var(--color-success-50);color:var(--color-success-900);border:1px solid var(--color-success-200)}
.acta-status-rejeitada{background:var(--color-danger-50);color:var(--color-danger-700);border:1px solid var(--color-danger-200)}
.acta-apply-link{
    min-height:34px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    padding:0 11px;
    border-radius:var(--radius-md);
    background:var(--color-success-50);
    color:var(--color-success-900);
    border:1px solid var(--color-success-200);
    text-decoration:none;
    font-size:11.5px;
    font-weight:700;
    cursor:pointer;
}
.acta-apply-link:hover{background:var(--color-success-100);color:var(--color-success-900)}
.acta-apply-muted{
    display:block;
    color:var(--color-ink-400);
    font-size:11.5px;
    line-height:1.35;
    font-weight:600;
}
@media(max-width:1080px){.acta-ready{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:680px){.acta-ready{grid-template-columns:1fr;}}

@media(max-width:1080px){
    .acta-header{grid-template-columns:1fr;padding:26px 24px}
    .acta-filters{grid-template-columns:1fr 1fr}
}
@media(max-width:680px){
    .acta-header h1{font-size:24px}
    .acta-filters{grid-template-columns:1fr}
    .acta-filters button,.btn-primary,.btn-accent,.acta-actions{width:100%}
    .acta-actions{display:grid;grid-template-columns:1fr}
    .acta-card{padding:18px 16px}
}
@media print{
    .acta-header,.acta-filters,.acta-actions{display:none!important}
    .acta-wrap{padding:0!important;display:block!important}
}

.acta-auto-map ul{margin:8px 0 0 18px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}
.acta-auto-map strong{color:var(--color-ink-500)}


/* v12.10.81 - Censo 3 de Março */
.acta-censo-grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:var(--space-3);margin-top:12px;align-items:end}
.acta-censo-grid label{display:flex;flex-direction:column;gap:7px;font-size:var(--fs-xs);color:var(--color-slate-600);font-weight:700;text-transform:uppercase;letter-spacing:.07em}
.acta-censo-grid input,.acta-censo-grid textarea{width:100%;min-height:44px;padding:10px 13px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);font-size:var(--fs-sm);background:var(--color-white);color:var(--color-ink-500);font-family:inherit;font-weight:600;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.acta-censo-grid textarea{min-height:76px;resize:vertical;line-height:1.5}
.acta-censo-grid input:focus,.acta-censo-grid textarea:focus{border-color:rgba(90,63,214,.55)!important;box-shadow:var(--shadow-xs);outline:none!important}
.acta-censo-confirm{display:flex;align-items:flex-start;gap:10px;margin-top:14px;padding:12px 14px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-slate-700);font-size:var(--fs-sm);font-weight:600;line-height:1.5}
.acta-censo-confirm input{margin-top:3px;flex:0 0 auto}
.acta-censo-status{display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:10px}
.acta-censo-pill{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-100);color:var(--color-slate-700);font-size:12px;font-weight:700;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.acta-censo-pill.ok{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200)}.acta-censo-pill.warn{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-200)}
.acta-censo-help{margin-top:12px;padding:13px 15px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-ink-100);color:var(--color-slate-700);font-size:var(--fs-sm);line-height:1.55;font-weight:600}
.acta-censo-help strong{color:var(--color-ink-900)}.acta-readonly input:not([type=hidden]),.acta-readonly select,.acta-readonly textarea{pointer-events:none;background:var(--color-slate-50)!important;color:var(--color-slate-500)!important}.acta-readonly .btn-add,.acta-readonly .btn-sm.danger{display:none!important}
@media(max-width:680px){.acta-censo-grid{grid-template-columns:1fr}}

</style>

<div class="acta-wrap">
    <?php if (!empty($sige_acta_scope_msg)): ?>
        <?php if (function_exists('sige_render_teacher_scope_denied')) { sige_render_teacher_scope_denied($sige_acta_scope_msg); } else { echo '<div class="notice notice-warning"><p>' . esc_html($sige_acta_scope_msg) . '</p></div>'; } ?>
    <?php endif; ?>
    <div class="acta-header">
        <div class="acta-header-main">
            <div class="acta-kicker"><?php echo $acta_icon('clipboard'); ?><span>Académico</span></div>
            <h1>Acta do Conselho de Notas</h1>
            <p>Prepare, guarde e imprima a acta oficial do conselho de notas por turma, trimestre e ano lectivo.</p>
            <div class="acta-hero-chips">
                <span class="acta-hero-chip"><?php echo $acta_icon('calendar'); ?> Ano Lectivo <?php echo esc_html((string)$ano_sel); ?></span>
                <span class="acta-hero-chip"><?php echo $acta_icon('file'); ?> ACN/A<?php echo esc_html(substr((string)$ano_sel, -2)); ?></span>
                <span class="acta-hero-chip"><?php echo $acta_icon('shield'); ?> Regras académicas preservadas</span>
            </div>
        </div>
        <aside class="acta-header-panel" aria-label="Estado da acta">
            <div class="acta-panel-label"><?php echo $acta_icon('shield'); ?><span>Estado</span></div>
            <strong class="acta-panel-status"><?php echo $turma ? esc_html(ucfirst((string)($acta->status ?? 'rascunho'))) : 'Por configurar'; ?></strong>
            <span class="acta-panel-text"><?php echo $turma ? ($acta_can_save ? 'Turma seleccionada. Pode completar os campos, guardar e imprimir a acta.' : 'Modo de leitura docente: pode consultar e imprimir a acta desta turma.') : 'Seleccione a turma, ano lectivo e trimestre para iniciar a acta.'; ?></span>
        </aside>
    </div>

    <form method="get" class="acta-filters">
        <input type="hidden" name="page" value="sige-app">
        <input type="hidden" name="view" value="acta">

        <label>
            Turma
            <select name="turma_id" required onchange="this.form.submit()">
                <option value="0">- Selecciona -</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?php echo (int) $t->id; ?>" <?php selected($turma_id, (int) $t->id); ?>>
                        <?php echo esc_html(((int)$t->ano_lectivo) . ' · ' . $t->classe . 'ª · ' . $t->nome_turma . ($t->turno ? ' (' . $t->turno . ')' : '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Ano Lectivo
            <input type="number" name="ano" value="<?php echo (int) $ano_sel; ?>" min="2020" max="2099">
        </label>

        <label>
            Trimestre
            <select name="trimestre">
                <option value="1" <?php selected($trimestre, 1); ?>>I Trimestre</option>
                <option value="2" <?php selected($trimestre, 2); ?>>II Trimestre</option>
                <option value="3" <?php selected($trimestre, 3); ?>>III Trimestre</option>
            </select>
        </label>

        <button type="submit" class="sgk-btn sgk-btn-primario"><?php echo $acta_icon('grid'); ?> Carregar</button>
    </form>

    <?php if ($flash_saved): ?>
        <div class="acta-flash"><?php echo $acta_icon('check'); ?> Acta guardada com sucesso.</div>
    <?php endif; ?>

    <?php if ($turma): ?>
        <div class="acta-card acta-auto-map">
            <h2><span class="c-idx"><?php echo $acta_icon('shield'); ?></span> Dados automáticos da ACTA</h2>
            <p>Algumas secções do documento impresso não são preenchidas manualmente porque devem vir da fonte oficial do sistema.</p>
            <ul>
                <li><strong>Fim do Trimestre:</strong> usa matrículas, género, estado da matrícula e notas oficiais; a linha <strong>3 de Março</strong> vem do Censo oficial confirmado pela escola.</li>
                <li><strong>Estatística por disciplina:</strong> calculada a partir das disciplinas reais da turma/classe e notas do período.</li>
                <li><strong>Quadro de Honra:</strong> gerado automaticamente pela média do aluno; não é um campo manual.</li>
                <li><strong>Cumprimento dos Programas:</strong> é preenchido na acta digital e respeita as disciplinas vinculadas à turma.</li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($acta_apply_status): ?>
        <div class="acta-flash" style="<?php echo $acta_apply_status === 'ok' ? '' : 'background:var(--color-danger-50);color:var(--color-danger-700);border-color:var(--color-danger-200);'; ?>">
            <?php echo $acta_icon($acta_apply_status === 'ok' ? 'check' : 'shield'); ?>
            <?php echo esc_html($acta_apply_msg ?: ($acta_apply_status === 'ok' ? 'Nota votada aplicada à pauta.' : 'Não foi possível aplicar a nota votada.')); ?>
        </div>
    <?php endif; ?>

    <?php if (!$turma): ?>

        <div class="empty-state">
            <h3>Selecciona uma turma</h3>
            <p>Escolhe a turma, ano lectivo e trimestre para gerir a ACTA do Conselho de Notas.</p>
        </div>

    <?php else: ?>

        <section class="acta-ready" aria-label="Prontidão académica da acta">
            <article class="acta-ready-card acta-ready-info">
                <span class="acta-ready-icon"><?php echo $acta_icon('users'); ?></span>
                <div><span class="acta-ready-label">Alunos</span><strong class="acta-ready-value"><?php echo (int)$acta_ready['alunos']; ?></strong><span class="acta-ready-note">Na turma seleccionada</span></div>
            </article>
            <article class="acta-ready-card acta-ready-info">
                <span class="acta-ready-icon"><?php echo $acta_icon('file'); ?></span>
                <div><span class="acta-ready-label">Disciplinas</span><strong class="acta-ready-value"><?php echo (int)$acta_ready['disciplinas']; ?></strong><span class="acta-ready-note">Na matriz da turma</span></div>
            </article>
            <article class="acta-ready-card <?php echo ((int)$acta_ready['em_falta'] === 0) ? 'acta-ready-ok' : 'acta-ready-warn'; ?>">
                <span class="acta-ready-icon"><?php echo $acta_icon('check'); ?></span>
                <div><span class="acta-ready-label">Notas aprovadas</span><strong class="acta-ready-value"><?php echo (int)$acta_ready['aprovadas']; ?>/<?php echo (int)$acta_ready['esperadas']; ?></strong><span class="acta-ready-note"><?php echo (int)$acta_ready['percentual']; ?>% de prontidão</span></div>
            </article>
            <article class="acta-ready-card <?php echo ((int)$acta_ready['pendentes'] > 0 || (int)$acta_ready['em_falta'] > 0) ? 'acta-ready-bad' : 'acta-ready-ok'; ?>">
                <span class="acta-ready-icon"><?php echo $acta_icon('shield'); ?></span>
                <div><span class="acta-ready-label">Pendências</span><strong class="acta-ready-value"><?php echo (int)$acta_ready['em_falta']; ?></strong><span class="acta-ready-note"><?php echo (int)$acta_ready['pendentes']; ?> nota(s) pendentes de aprovação</span></div>
            </article>
        </section>

        <form method="post" action="<?php echo $admin_post_url; ?>" id="acta-form" class="<?php echo $acta_can_save ? '' : 'acta-readonly'; ?>">
            <input type="hidden" name="action" value="sige_acta_guardar">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_guardar); ?>">
            <input type="hidden" name="_redirect" value="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=acta&turma_id=' . $turma_id . '&ano=' . $ano_sel . '&trimestre=' . $trimestre)); ?>">
            <input type="hidden" name="acta_id"   value="<?php echo (int) $acta_id; ?>">
            <input type="hidden" name="turma_id"  value="<?php echo (int) $turma_id; ?>">
            <input type="hidden" name="ano"       value="<?php echo (int) $ano_sel; ?>">
            <input type="hidden" name="trimestre" value="<?php echo (int) $trimestre; ?>">

            <!-- ═══ CENSO 3 DE MARÇO ═══ -->
            <div class="acta-card acta-censo-card">
                <h2><span class="c-idx"><?php echo $acta_icon('calendar'); ?></span> Censo Escolar - <?php echo esc_html($acta_censo_info['label'] ?? '3 de Março'); ?>
                    <?php if ($acta_censo_snapshot): ?>
                        <span class="acta-status-badge finalizada">CONFIRMADO</span>
                    <?php else: ?>
                        <span class="acta-status-badge">POR CONFIRMAR</span>
                    <?php endif; ?>
                </h2>
                <p>Este valor representa a situação real da turma no dia oficial do levantamento estatístico escolar de Moçambique. Não é calculado pela data em que o aluno foi registado no sistema.</p>
                <div class="acta-censo-status">
                    <span class="acta-censo-pill <?php echo $acta_censo_snapshot ? 'ok' : 'warn'; ?>"><?php echo $acta_icon($acta_censo_snapshot ? 'check' : 'shield'); ?> <?php echo $acta_censo_snapshot ? 'Censo oficial guardado' : 'Censo oficial ainda não confirmado'; ?></span>
                    <span class="acta-censo-pill"><?php echo $acta_icon('users'); ?> Sugestão actual: M <?php echo esc_html((string)($acta_censo_sugestao['M'] ?? 0)); ?> · HM <?php echo esc_html((string)($acta_censo_sugestao['HM'] ?? 0)); ?></span>
                    <?php if ($acta_censo_snapshot && !empty($acta_censo_snapshot->confirmado_em)): ?><span class="acta-censo-pill"><?php echo $acta_icon('calendar'); ?> Confirmado em <?php echo esc_html((string)$acta_censo_snapshot->confirmado_em); ?></span><?php endif; ?>
                </div>
                <?php if ($acta_censo_stale): ?>
                <div class="acta-censo-stale" style="margin:12px 0;padding:14px 16px;border-radius:12px;background:var(--color-danger-50);border:1px solid var(--color-danger-200);color:var(--color-danger-700);font-size:13px;line-height:1.5;">
                    <strong>⚠ Censo desactualizado nesta turma.</strong>
                    O Censo oficial desta turma foi confirmado <em>antes</em> da actualização do cálculo da estatística por género.
                    Para o mesmo total <strong>HM = <?php echo esc_html((string)($acta_censo_sugestao['HM'] ?? 0)); ?></strong>,
                    o cálculo actual indica <strong>M = <?php echo esc_html((string)($acta_censo_sugestao['M'] ?? 0)); ?></strong> Mulheres,
                    mas o Censo guardado tem <strong>M = <?php echo esc_html((string)($acta_censo_snapshot->m_mulheres ?? 0)); ?></strong>.
                    <?php if ($pode_confirmar_censo): ?>
                        Clique em <strong>“Aplicar valores corrigidos”</strong>, confirme e Guarde para actualizar o Censo oficial desta turma.
                        <div style="margin-top:10px;">
                            <button type="button" id="acta-censo-fix-btn" class="button button-primary"
                                data-m="<?php echo esc_attr((string)($acta_censo_sugestao['M'] ?? 0)); ?>"
                                data-hm="<?php echo esc_attr((string)($acta_censo_sugestao['HM'] ?? 0)); ?>"
                                style="background:var(--color-danger-700);border-color:var(--color-danger-700);">Aplicar valores corrigidos</button>
                        </div>
                    <?php else: ?>
                        Peça à Direcção Pedagógica/Secretaria para reconfirmar o Censo desta turma.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="acta-censo-help">
                    <strong>Quem confirma?</strong> Direcção Pedagógica, Direcção, Secretaria Geral/Admin ou outro perfil autorizado a guardar a Acta. O professor em escopo docente apenas consulta as suas turmas, salvo se a escola lhe atribuir permissão real para editar/guardar a Acta.<br>
                    <strong>Como confirmar?</strong> conferir M e HM com o mapa oficial do Censo Escolar de <?php echo esc_html($acta_censo_info['label'] ?? '3 de Março'); ?>, ajustar os valores se necessário, indicar a fonte/observação, assinalar a confirmação e clicar em Guardar.
                </div>
                <div class="acta-censo-grid">
                    <label>
                        M - Mulheres no Censo
                        <input type="number" name="censo3_m" min="0" value="<?php echo esc_attr((string)($acta_censo_snapshot->m_mulheres ?? ($acta_censo_sugestao['M'] ?? 0))); ?>" <?php disabled(!$pode_confirmar_censo); ?>>
                    </label>
                    <label>
                        HM - Total da Turma no Censo
                        <input type="number" name="censo3_hm" min="0" value="<?php echo esc_attr((string)($acta_censo_snapshot->hm_total ?? ($acta_censo_sugestao['HM'] ?? 0))); ?>" <?php disabled(!$pode_confirmar_censo); ?>>
                    </label>
                    <label>
                        Observação / Fonte
                        <textarea name="censo3_observacao" rows="2" placeholder="Ex.: confirmado com base no mapa oficial 3 de Março da escola" <?php disabled(!$pode_confirmar_censo); ?>><?php echo esc_textarea((string)($acta_censo_snapshot->observacao ?? '')); ?></textarea>
                    </label>
                </div>
                <?php if ($pode_confirmar_censo): ?>
                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                    <button type="button" id="acta-censo-apply-btn" class="button button-primary"
                        data-m="<?php echo esc_attr((string)($acta_censo_sugestao['M'] ?? 0)); ?>"
                        data-hm="<?php echo esc_attr((string)($acta_censo_sugestao['HM'] ?? 0)); ?>">
                        Aplicar valores corrigidos (M <?php echo esc_html((string)($acta_censo_sugestao['M'] ?? 0)); ?> · HM <?php echo esc_html((string)($acta_censo_sugestao['HM'] ?? 0)); ?>)
                    </button>
                    <span style="font-size:12px;color:var(--color-slate-600);">Preenche M e HM com o cálculo corrigido. Reveja, assinale a confirmação e clique em Guardar.</span>
                </div>
                <?php endif; ?>
                <?php if (!empty($acta_genero_diag)): ?>
                <details class="acta-genero-diag" style="margin-top:14px;border:1px solid var(--color-ink-100);border-radius:14px;padding:12px 14px;background:var(--color-white);">
                    <summary style="cursor:pointer;font-weight:800;font-size:12px;color:var(--color-slate-800);text-transform:uppercase;letter-spacing:.05em;">
                        Diagnóstico de género desta turma (valores reais gravados)
                        <?php if ($acta_genero_diag_u > 0): ?>
                            <span style="color:var(--color-danger-700);">· <?php echo (int)$acta_genero_diag_u; ?> por confirmar</span>
                        <?php endif; ?>
                    </summary>
                    <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;"><table style="width:100%;border-collapse:collapse;margin-top:10px;font-size:13px;">
                        <thead>
                            <tr style="text-align:left;color:var(--color-slate-600);border-bottom:1px solid var(--color-ink-100);">
                                <th style="padding:6px 8px;">Valor gravado</th>
                                <th style="padding:6px 8px;">Nº alunos</th>
                                <th style="padding:6px 8px;">Classificado como</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($acta_genero_diag as $gd): ?>
                            <tr style="border-bottom:1px solid var(--color-ink-50);">
                                <td style="padding:6px 8px;font-family:monospace;"><?php echo esc_html((string)$gd['raw']); ?></td>
                                <td style="padding:6px 8px;"><?php echo (int)$gd['c']; ?></td>
                                <td style="padding:6px 8px;<?php echo $gd['bucket'] === 'U' ? 'color:var(--color-danger-700);font-weight:700;' : ''; ?>">
                                    <?php echo esc_html((string)$gd['label']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                    <?php if ($acta_genero_diag_u > 0): ?>
                    <p style="margin:10px 0 0;font-size:12px;color:var(--color-danger-700);line-height:1.5;">
                        Existem alunos cujo valor de género não é reconhecível (marcado a vermelho). Esses entram apenas no total <strong>HM</strong> e não em Mulheres. Corrija o campo género desses alunos na ficha (Alunos &amp; Matrículas) para que entrem na contagem correcta. Esta mesma regra é agora usada pelo DEC, Pauta Final e ACTA - os três passam a coincidir.
                    </p>
                    <?php endif; ?>
                </details>
                <?php endif; ?>
                <label class="acta-censo-confirm">
                    <input type="checkbox" name="censo3_confirmar" value="1" <?php disabled(!$pode_confirmar_censo); ?>>
                    <span>Confirmar/actualizar estes valores como registo oficial do Censo Escolar de <?php echo esc_html($acta_censo_info['label'] ?? '3 de Março'); ?> para esta turma e ano lectivo. Esta confirmação será auditada.</span>
                </label>
            </div>

            <!-- ═══ 1. CABEÇALHO ═══ -->
            <div class="acta-card">
                <h2><span class="c-idx">1</span> Cabeçalho
                    <span class="acta-status-badge <?php echo esc_attr(($acta->status ?? '') === 'finalizada' ? 'finalizada' : ''); ?>">
                        <?php echo esc_html(strtoupper((string)($acta->status ?? 'rascunho'))); ?>
                    </span>
                </h2>
                <div class="grid">
                    <label>
                        Data do Conselho
                        <input type="date" name="data_conselho" value="<?php echo esc_attr((string)($acta->data_conselho ?? '')); ?>">
                    </label>
                    <label>
                        Sala
                        <input type="text" name="sala" value="<?php echo esc_attr((string)($acta->sala ?? '')); ?>" placeholder="ex: Sala 3">
                    </label>
                    <label>
                        Hora de Início
                        <input type="time" name="hora_inicio" value="<?php echo esc_attr((string)($acta->hora_inicio ?? '')); ?>">
                    </label>
                    <label style="grid-column: span 2;">
                        Presidido pelo(a)
                        <input type="text" name="presidido_por" value="<?php echo esc_attr((string)($acta->presidido_por ?? '')); ?>" placeholder="Nome completo do presidente do conselho">
                    </label>
                </div>
            </div>

            <!-- ═══ 2. PRESENÇAS ═══ -->
            <div class="acta-card">
                <h2><span class="c-idx">2</span> Presenças no Conselho</h2>
                <p style="font-size:12px;color:var(--color-slate-700);margin:0 0 10px 0;">Estiveram Presentes e Não Estiveram Presentes. Preenche nome de cada docente/convidado. Arrasta o estado para alterar.</p>

                <table class="ed-table" id="tb-presencas">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Nome</th>
                            <th style="width: 18%;">Função</th>
                            <th style="width: 22%;">Disciplina</th>
                            <th style="width: 15%;">Estado</th>
                            <th class="row-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Mostrar todas presenças (primeiro presentes, depois ausentes) + sugestões se vazio
                        $todas = array_merge($presentes, $ausentes);
                        if (empty($todas)) $todas = $docentes_sug; // bootstrap

                        $idx = 0;
                        foreach ($todas as $row):
                            $is_obj = is_object($row);
                            $r_nome = $is_obj ? (string) $row->nome : (string) ($row['nome'] ?? '');
                            $r_func = $is_obj ? (string) $row->funcao : (string) ($row['funcao'] ?? '');
                            $r_did  = $is_obj ? ($row->disciplina_id ?? '') : ($row['disciplina_id'] ?? '');
                            $r_dnm  = $is_obj ? (string) $row->disciplina_nome : (string) ($row['disciplina_nome'] ?? '');
                            $r_sta  = $is_obj ? (string) $row->status : 'presente';
                        ?>
                        <tr>
                            <td><input type="text" name="presencas[<?php echo $idx; ?>][nome]" value="<?php echo esc_attr($r_nome); ?>" placeholder="Nome completo"></td>
                            <td><input type="text" name="presencas[<?php echo $idx; ?>][funcao]" value="<?php echo esc_attr($r_func); ?>" placeholder="Ex: Docente"></td>
                            <td>
                                <select name="presencas[<?php echo $idx; ?>][disciplina_id]" onchange="this.nextElementSibling.value=this.options[this.selectedIndex].text;">
                                    <option value="">-</option>
                                    <?php foreach ($discs_turma as $d): ?>
                                        <option value="<?php echo (int) $d->id; ?>" <?php selected((int) $r_did, (int) $d->id); ?>><?php echo esc_html((string) $d->nome); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="presencas[<?php echo $idx; ?>][disciplina_nome]" value="<?php echo esc_attr($r_dnm); ?>">
                            </td>
                            <td>
                                <select name="presencas[<?php echo $idx; ?>][status]">
                                    <option value="presente" <?php selected($r_sta, 'presente'); ?>>Presente</option>
                                    <option value="ausente"  <?php selected($r_sta, 'ausente');  ?>>Ausente</option>
                                </select>
                            </td>
                            <td class="row-actions"><button type="button" class="btn-sm danger" data-sige-act="removeRow"><?php echo $acta_icon('trash'); ?></button></td>
                        </tr>
                        <?php $idx++; endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="btn-add" data-sige-act="addPresencaRow" data-sige-noargs><?php echo $acta_icon('plus'); ?> Adicionar linha</button>
            </div>

            <!-- ═══ 3. NOTA VOTADA ═══ -->
            <div class="acta-card">
                <h2><span class="c-idx">3</span> Nota Votada</h2>
                <p style="font-size:12px;color:var(--color-slate-700);margin:0 0 10px 0;">Alunos cuja nota foi alterada por votação do conselho. Deixa em branco se não houve alterações.</p>

                <table class="ed-table" id="tb-nota-votada">
                    <thead>
                        <tr>
                            <th class="col-narrow">N.°</th>
                            <th>Aluno</th>
                            <th style="width: 22%;">Disciplina</th>
                            <th class="col-narrow">Nota Inicial</th>
                            <th class="col-narrow">Nota Final</th>
                            <th>Recomendações</th>
                            <th class="col-med">Estado DP</th>
                            <th class="col-med">Aprovação</th>
                            <th class="row-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $nv_idx = 0;
                        foreach ($nota_votada as $nv):
                            $nv_status = (string)($nv->status ?? 'pendente_dp');
                            $nv_status_label = $nv_status === 'aprovada_aplicada' ? 'Aplicada à pauta' : ($nv_status === 'rejeitada' ? 'Rejeitada' : 'Pendente DP');
                            $nv_can_apply = $pode_aprovar_nota_votada
                                && $nv_status !== 'aprovada_aplicada'
                                && !empty($nv->id)
                                && !empty($nv->disciplina_id)
                                && is_numeric($nv->nota_final)
                                && (float)$nv->nota_final >= 0
                                && (float)$nv->nota_final <= 20;
                            $nv_apply_url = '';
                            if ($nv_can_apply) {
                                $nv_apply_url = wp_nonce_url(add_query_arg([
                                    'action' => 'sige_acta_aprovar_nota_votada',
                                    'nota_votada_id' => (int)$nv->id,
                                    '_redirect' => $acta_return_url,
                                ], admin_url('admin-post.php')), 'sige_acta_aprovar_nota_votada_' . (int)$nv->id, '_wpnonce');
                            }
                        ?>
                        <tr>
                            <td class="col-narrow"><input type="hidden" name="nota_votada[<?php echo $nv_idx; ?>][id]" value="<?php echo (int)($nv->id ?? 0); ?>"><input type="number" name="nota_votada[<?php echo $nv_idx; ?>][numero_chamada]" value="<?php echo esc_attr((string)($nv->numero_chamada ?? '')); ?>" min="1" max="999"></td>
                            <td>
                                <select name="nota_votada[<?php echo $nv_idx; ?>][aluno_id]" required>
                                    <option value="">- Selecciona aluno -</option>
                                    <?php foreach ($alunos_turma as $al): ?>
                                        <option value="<?php echo (int) $al->id; ?>" <?php selected((int)$nv->aluno_id, (int)$al->id); ?>><?php echo esc_html((string)$al->nome_completo); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="nota_votada[<?php echo $nv_idx; ?>][disciplina_id]">
                                    <option value="">Média Global</option>
                                    <?php foreach ($discs_turma as $d): ?>
                                        <option value="<?php echo (int) $d->id; ?>" <?php selected((int)($nv->disciplina_id ?? 0), (int)$d->id); ?>><?php echo esc_html((string)$d->nome); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="col-narrow"><input type="number" step="0.01" min="0" max="20" name="nota_votada[<?php echo $nv_idx; ?>][nota_inicial]" value="<?php echo esc_attr((string)($nv->nota_inicial ?? '')); ?>"></td>
                            <td class="col-narrow"><input type="number" step="0.01" min="0" max="20" name="nota_votada[<?php echo $nv_idx; ?>][nota_final]"   value="<?php echo esc_attr((string)($nv->nota_final ?? '')); ?>"></td>
                            <td><textarea name="nota_votada[<?php echo $nv_idx; ?>][recomendacoes]" rows="1"><?php echo esc_textarea((string)($nv->recomendacoes ?? '')); ?></textarea></td>
                            <td><span class="acta-status-pill acta-status-<?php echo esc_attr($nv_status); ?>"><?php echo esc_html($nv_status_label); ?></span></td>
                            <td>
                                <?php if ($nv_can_apply): ?>
                                    <a class="acta-apply-link" href="<?php echo esc_url($nv_apply_url); ?>" data-sige-confirm="A nota votada será aplicada à pauta oficial com aprovação do Director Pedagógico. A acção fica registada na acta." data-sige-titulo="Aprovar e aplicar à pauta" data-sige-confirmar="Aprovar e aplicar"><?php echo $acta_icon('check'); ?> Aprovar e aplicar</a>
                                <?php elseif ($nv_status === 'aprovada_aplicada'): ?>
                                    <span class="acta-apply-muted">Já aplicada à pauta oficial.</span>
                                <?php elseif (empty($nv->disciplina_id)): ?>
                                    <span class="acta-apply-muted">Seleccione disciplina específica para aplicar.</span>
                                <?php elseif (!is_numeric($nv->nota_final)): ?>
                                    <span class="acta-apply-muted">Informe a nota final.</span>
                                <?php elseif (!$pode_aprovar_nota_votada): ?>
                                    <span class="acta-apply-muted">Aguarda Director Pedagógico.</span>
                                <?php else: ?>
                                    <span class="acta-apply-muted">Verifique a nota final.</span>
                                <?php endif; ?>
                            </td>
                            <td class="row-actions"><button type="button" class="btn-sm danger" data-sige-act="removeRow"><?php echo $acta_icon('trash'); ?></button></td>
                        </tr>
                        <?php $nv_idx++; endforeach; ?>
                        <?php if ($nv_idx === 0): /* linha vazia por defeito */ ?>
                        <tr>
                            <td class="col-narrow"><input type="number" name="nota_votada[0][numero_chamada]" min="1" max="999"></td>
                            <td>
                                <select name="nota_votada[0][aluno_id]">
                                    <option value="">- Selecciona aluno -</option>
                                    <?php foreach ($alunos_turma as $al): ?>
                                        <option value="<?php echo (int) $al->id; ?>"><?php echo esc_html((string)$al->nome_completo); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="nota_votada[0][disciplina_id]">
                                    <option value="">Média Global</option>
                                    <?php foreach ($discs_turma as $d): ?>
                                        <option value="<?php echo (int) $d->id; ?>"><?php echo esc_html((string)$d->nome); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="col-narrow"><input type="number" step="0.01" min="0" max="20" name="nota_votada[0][nota_inicial]"></td>
                            <td class="col-narrow"><input type="number" step="0.01" min="0" max="20" name="nota_votada[0][nota_final]"></td>
                            <td><textarea name="nota_votada[0][recomendacoes]" rows="1"></textarea></td>
                            <td><span class="acta-status-pill acta-status-pendente_dp">Por guardar</span></td>
                            <td><span class="acta-apply-muted">Guarde primeiro para submeter ao Director Pedagógico.</span></td>
                            <td class="row-actions"><button type="button" class="btn-sm danger" data-sige-act="removeRow"><?php echo $acta_icon('trash'); ?></button></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" class="btn-add" data-sige-act="addNotaVotadaRow" data-sige-noargs><?php echo $acta_icon('plus'); ?> Adicionar nota votada</button>
            </div>

            <!-- ═══ 4. CUMPRIMENTO DOS PROGRAMAS ═══ -->
            <div class="acta-card">
                <h2><span class="c-idx">4</span> Cumprimento dos Programas</h2>
                <p style="font-size:12px;color:var(--color-slate-700);margin:0 0 10px 0;">Último tema leccionado e aulas em atraso por disciplina. A lista abaixo vem directamente das disciplinas vinculadas à turma seleccionada.</p>

                <table class="ed-table">
                    <thead>
                        <tr>
                            <th style="width: 18%;">Disciplina</th>
                            <th style="width: 32%;">Último Tema</th>
                            <th class="col-narrow">N.° Aulas em Atraso</th>
                            <th>Razões do Atraso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // v12.10.78 - Cumprimento dos Programas usa apenas as disciplinas reais vinculadas à turma.
                        $cp_key = static function($s) {
                            $s = (string) $s;
                            if (function_exists('remove_accents')) $s = remove_accents($s);
                            return strtolower(trim($s));
                        };
                        $cp_by_id = [];
                        $cp_by_name = [];
                        foreach ($cumprimento as $c) {
                            $cid = isset($c->disciplina_id) ? (int) $c->disciplina_id : 0;
                            if ($cid > 0) $cp_by_id[$cid] = $c;
                            $cp_by_name[$cp_key($c->disciplina_nome ?? '')] = $c;
                        }
                        $cp_idx = 0;
                        if (empty($discs_turma)):
                        ?>
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--color-slate-500);font-weight:700;padding:18px;">
                                Esta turma ainda não tem disciplinas vinculadas. Configure a matriz/vínculo da turma antes de preencher o cumprimento dos programas.
                            </td>
                        </tr>
                        <?php
                        else:
                        foreach ($discs_turma as $disc):
                            $disc_id = (int) ($disc->id ?? 0);
                            $disc_nome = (string) ($disc->nome ?? '');
                            $c = $cp_by_id[$disc_id] ?? ($cp_by_name[$cp_key($disc_nome)] ?? null);
                        ?>
                        <tr>
                            <td class="sige-u-fw6">
                                <?php echo esc_html($disc_nome); ?>
                                <input type="hidden" name="cumprimento[<?php echo $cp_idx; ?>][disciplina_id]" value="<?php echo (int) $disc_id; ?>">
                                <input type="hidden" name="cumprimento[<?php echo $cp_idx; ?>][disciplina_nome]" value="<?php echo esc_attr($disc_nome); ?>">
                            </td>
                            <td><input type="text" name="cumprimento[<?php echo $cp_idx; ?>][ultimo_tema]" value="<?php echo esc_attr((string)($c->ultimo_tema ?? '')); ?>" placeholder="Último tema leccionado"></td>
                            <td class="col-narrow"><input type="number" name="cumprimento[<?php echo $cp_idx; ?>][aulas_em_atraso]" value="<?php echo esc_attr((string)($c->aulas_em_atraso ?? '')); ?>" min="0" max="999"></td>
                            <td><input type="text" name="cumprimento[<?php echo $cp_idx; ?>][razoes_atraso]" value="<?php echo esc_attr((string)($c->razoes_atraso ?? '')); ?>" placeholder="-"></td>
                        </tr>
                        <?php $cp_idx++; endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ═══ 5. OBSERVAÇÕES ═══ -->
            <div class="acta-card">
                <h2><span class="c-idx">5</span> Observações</h2>
                <div style="margin-bottom: 12px;">
                    <label style="font-size:12px;color:var(--color-slate-700);font-weight:600;display:block;margin-bottom:6px;">Como decorreu o Conselho de Notas</label>
                    <textarea class="fullwidth" name="como_decorreu" rows="5" placeholder="Descrição livre do conselho de notas..."><?php echo esc_textarea((string)($acta->como_decorreu ?? '')); ?></textarea>
                </div>

                <?php if ($pode_editar_obs_dp): ?>
                <div>
                    <label style="font-size:12px;color:var(--color-slate-700);font-weight:600;display:block;margin-bottom:6px;">
                        Observação do Director Pedagógico
                        <span style="font-weight:400;color:var(--color-slate-500);font-size:11px;">(só visível para DP e acima)</span>
                    </label>
                    <textarea class="fullwidth" name="observacao_dp" rows="3" placeholder="Observação do Director Pedagógico..."><?php echo esc_textarea((string)($acta->observacao_dp ?? '')); ?></textarea>
                </div>
                <?php else: ?>
                    <input type="hidden" name="observacao_dp" value="<?php echo esc_attr((string)($acta->observacao_dp ?? '')); ?>">
                <?php endif; ?>
            </div>

            <!-- ═══ ACÇÕES ═══ -->
            <div class="acta-actions">
                <?php if ($acta_can_save): ?><button type="submit" class="btn-primary"><?php echo $acta_icon('save'); ?> Guardar</button><?php endif; ?>
                <a class="btn-accent" target="_blank" href="<?php echo esc_url(add_query_arg([
                    'action'    => 'sige_acta_pdf',
                    'turma_id'  => $turma_id,
                    'ano'       => $ano_sel,
                    'trimestre' => $trimestre,
                    '_wpnonce'  => $nonce_pdf,
                ], admin_url('admin-post.php'))); ?>"><?php echo $acta_icon('print'); ?> Imprimir ACTA</a>
            </div>
        </form>

    <?php endif; ?>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function() {
    // v12.11.9.17 - Botão "Aplicar valores corrigidos" do Censo 3 de Março.
    var fixBtn = document.getElementById('acta-censo-fix-btn');
    var applyBtn = document.getElementById('acta-censo-apply-btn');
    function applyCorrected(btn) {
        var m  = btn.getAttribute('data-m');
        var hm = btn.getAttribute('data-hm');
        var inM  = document.querySelector('input[name="censo3_m"]');
        var inHM = document.querySelector('input[name="censo3_hm"]');
        var chk  = document.querySelector('input[name="censo3_confirmar"]');
        if (inM)  inM.value  = m;
        if (inHM) inHM.value = hm;
        if (chk && !chk.disabled) chk.checked = true;
        if (inM) { inM.focus(); }
        var card = document.querySelector('.acta-censo-stale');
        if (card) { card.style.opacity = '0.6'; }
    }
    if (fixBtn)   { fixBtn.addEventListener('click', function(){ applyCorrected(fixBtn); }); }
    if (applyBtn) { applyBtn.addEventListener('click', function(){ applyCorrected(applyBtn); }); }
})();
</script>
<script <?php echo sige_csp_script_attr(); ?>>
(function() {
    // Templates para novas rows de presença e nota votada
    const discsJson = <?php echo wp_json_encode(array_map(function($d){ return ['id'=>(int)$d->id, 'nome'=>(string)$d->nome]; }, $discs_turma)); ?>;
    const alunosJson = <?php echo wp_json_encode(array_map(function($a){ return ['id'=>(int)$a->id, 'nome'=>(string)$a->nome_completo]; }, $alunos_turma)); ?>;

    function optionsDiscs(selected) {
        let html = '<option value="">-</option>';
        discsJson.forEach(d => {
            html += `<option value="${d.id}" ${selected==d.id?'selected':''}>${escapeHtml(d.nome)}</option>`;
        });
        return html;
    }
    function optionsAlunos(selected) {
        let html = '<option value="">- Selecciona aluno -</option>';
        alunosJson.forEach(a => {
            html += `<option value="${a.id}" ${selected==a.id?'selected':''}>${escapeHtml(a.nome)}</option>`;
        });
        return html;
    }
    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
    }

    let presIdx = document.querySelectorAll('#tb-presencas tbody tr').length;
    let nvIdx   = document.querySelectorAll('#tb-nota-votada tbody tr').length;

    window.addPresencaRow = function() {
        const tr = document.createElement('tr');
        const i = presIdx++;
        tr.innerHTML = `
            <td><input type="text" name="presencas[${i}][nome]" placeholder="Nome completo"></td>
            <td><input type="text" name="presencas[${i}][funcao]" placeholder="Ex: Docente"></td>
            <td>
                <select name="presencas[${i}][disciplina_id]" onchange="this.nextElementSibling.value=this.options[this.selectedIndex].text;">${optionsDiscs('')}</select>
                <input type="hidden" name="presencas[${i}][disciplina_nome]" value="">
            </td>
            <td>
                <select name="presencas[${i}][status]">
                    <option value="presente">Presente</option>
                    <option value="ausente">Ausente</option>
                </select>
            </td>
            <td class="row-actions"><button type="button" class="btn-sm danger" data-sige-act="removeRow"><?php echo $acta_icon('trash'); ?></button></td>
        `;
        document.querySelector('#tb-presencas tbody').appendChild(tr);
    };

    window.addNotaVotadaRow = function() {
        const tr = document.createElement('tr');
        const i = nvIdx++;
        tr.innerHTML = `
            <td class="col-narrow"><input type="number" name="nota_votada[${i}][numero_chamada]" min="1" max="999"></td>
            <td><select name="nota_votada[${i}][aluno_id]">${optionsAlunos('')}</select></td>
            <td><select name="nota_votada[${i}][disciplina_id]"><option value="">Média Global</option>${discsJson.map(d=>`<option value="${d.id}">${escapeHtml(d.nome)}</option>`).join('')}</select></td>
            <td class="col-narrow"><input type="number" step="0.01" min="0" max="20" name="nota_votada[${i}][nota_inicial]"></td>
            <td class="col-narrow"><input type="number" step="0.01" min="0" max="20" name="nota_votada[${i}][nota_final]"></td>
            <td><textarea name="nota_votada[${i}][recomendacoes]" rows="1"></textarea></td>
            <td><span class="acta-status-pill acta-status-pendente_dp">Por guardar</span></td>
            <td><span class="acta-apply-muted">Guarde primeiro para submeter ao Director Pedagógico.</span></td>
            <td class="row-actions"><button type="button" class="btn-sm danger" data-sige-act="removeRow"><?php echo $acta_icon('trash'); ?></button></td>
        `;
        document.querySelector('#tb-nota-votada tbody').appendChild(tr);
    };

    window.removeRow = function(btn) {
        const tr = btn.closest('tr');
        if (tr) tr.remove();
    };
})();
</script>
