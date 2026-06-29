<?php
/**
 * SIGE SoftGenial - DEC (Documento Estatístico da Classe)
 * v1.1 - Abril 2026
 * Vista: ?page=sige-app&view=dec
 *
 * v1.1 fixes: Stats por CADA coluna (não apenas MT) + Impressão popup limpa
 *
 * Segurança: ABSPATH, capability, escola_id em todas queries, prepare(), esc_*()
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['academico.dec_ver','academico.dec_emitir'],
    ['sige_professor','sige_director','sige_pedagogico','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;
global $wpdb;
$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$_ep = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$_en = $_ep->nome_escola ?? get_bloginfo('name');
$tT = $wpdb->prefix.'sige_turmas'; $tA = $wpdb->prefix.'sige_alunos'; $tM = $wpdb->prefix.'sige_matriculas';
$tN = $wpdb->prefix.'sige_notas'; $tD = $wpdb->prefix.'sige_disciplinas'; $tTD = $wpdb->prefix.'sige_turma_disciplinas';
$tMC = $wpdb->prefix.'sige_matriz_curricular';
$ano_act = function_exists('sige_get_ano_lectivo_atual') ? (int) sige_get_ano_lectivo_atual() : (int) wp_date('Y');
$turma_id  = isset($_GET['turma_id'])    ? (int)$_GET['turma_id']    : 0;
$trimestre = isset($_GET['trimestre'])   ? (int)$_GET['trimestre']   : 0;
$ano_sel   = isset($_GET['ano_lectivo']) ? (int)$_GET['ano_lectivo'] : $ano_act;
if ($trimestre < 1 || $trimestre > 3) $trimestre = 0;

// [12.11.9.15] Escopo docente: DEC só mostra turmas atribuídas ao professor actual.
$sige_dec_scoped_professor = function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user();
$sige_dec_professor_id = ($sige_dec_scoped_professor && function_exists('sige_get_professor_atual_id')) ? (int) sige_get_professor_atual_id() : 0;
$sige_dec_scope_msg = '';

if ($sige_dec_scoped_professor) {
    if ($sige_dec_professor_id > 0) {
        $turmas = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT t.id,t.nome,t.classe,t.ano_lectivo,t.turno
             FROM {$tT} t
             WHERE t.escola_id=%d
               AND (t.status_turma IS NULL OR t.status_turma IN ('activa','ativa'))
               AND (t.director_turma_id=%d OR t.id IN (SELECT turma_id FROM {$tTD} WHERE professor_id=%d AND escola_id=%d))
             ORDER BY t.ano_lectivo DESC, CAST(t.classe AS UNSIGNED) ASC, t.nome ASC",
            $eid, $sige_dec_professor_id, $sige_dec_professor_id, $eid
        ));
        if (empty($turmas)) {
            $sige_dec_scope_msg = 'O seu perfil de professor ainda não tem turmas atribuídas para consultar o DEC.';
        }
    } else {
        $turmas = [];
        $sige_dec_scope_msg = 'O seu utilizador ainda não está vinculado a um registo de professor no SIGE.';
    }
} else {
    $turmas = $wpdb->get_results($wpdb->prepare("SELECT id,nome,classe,ano_lectivo,turno FROM {$tT} WHERE escola_id=%d ORDER BY ano_lectivo DESC,classe ASC,nome ASC", $eid));
}

$ti = null; $cr = ''; $cn = 0;
if ($turma_id > 0) {
    $ti = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tT} WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id, $eid));
    if ($ti && $sige_dec_scoped_professor) {
        $can_turma = function_exists('sige_professor_can_access_turma') && sige_professor_can_access_turma((int)$turma_id, $sige_dec_professor_id, $eid);
        if (!$can_turma) {
            $ti = null;
            $turma_id = 0;
            $trimestre = 0;
            $sige_dec_scope_msg = 'Este DEC pertence a uma turma que não está atribuída ao seu perfil de professor.';
        }
    }
    if ($ti) { $cr = (string)($ti->classe??''); $cn = function_exists('sige_parse_classe_num') ? (int)sige_parse_classe_num($cr) : (int)preg_replace('/\D+/','',$cr); }
}
$efc = false;
if ($ti && function_exists('sige_is_fim_ciclo_by_turma')) { $efc = sige_is_fim_ciclo_by_turma($turma_id, $ano_sel); }
elseif ($cn > 0) { $efc = in_array($cn, [3,6,9,12], true); }

// Disciplinas
$discs = [];
if ($turma_id > 0 && $ti) {
    $cnorm = '';
    if ($cr !== '') {
        if (is_numeric($cr)) $cnorm = ((int)$cr)."\xC2\xAA";
        elseif (preg_match('/^\s*(\d{1,2})\s*[\xC2\xAA a]?\s*([A-Za-z])?\s*$/u', $cr, $mm)) $cnorm = ((int)$mm[1])."\xC2\xAA";
        elseif (preg_match('/(\d{1,2})/u', $cr, $mm)) $cnorm = ((int)$mm[1])."\xC2\xAA";
        else $cnorm = $cr;
    }
    $discs = $wpdb->get_results($wpdb->prepare("SELECT d.id,d.nome,d.sigla,d.categoria,COALESCE(mc.ordem_pauta,CASE d.sigla WHEN 'POR' THEN 1 WHEN 'MAT' THEN 2 WHEN 'CS' THEN 3 WHEN 'CN' THEN 4 WHEN 'ING' THEN 5 WHEN 'ED.VISUAL' THEN 6 WHEN 'ED. V' THEN 6 WHEN 'OF' THEN 7 WHEN 'ED. FISICA' THEN 8 WHEN 'ED.FISICA' THEN 8 ELSE 99 END) AS ordem FROM {$tTD} td INNER JOIN {$tD} d ON d.id=td.disciplina_id LEFT JOIN {$tMC} mc ON mc.disciplina_id=d.id AND mc.classe=%s AND mc.escola_id=%d WHERE td.turma_id=%d AND td.escola_id=%d GROUP BY d.id ORDER BY ordem ASC,d.nome ASC", $cnorm, $eid, $turma_id, $eid));
    if (empty($discs) && $cnorm !== '') {
        $discs = $wpdb->get_results($wpdb->prepare("SELECT d.id,d.nome,d.sigla,d.categoria,COALESCE(mc.ordem_pauta,CASE d.sigla WHEN 'POR' THEN 1 WHEN 'MAT' THEN 2 WHEN 'CS' THEN 3 WHEN 'CN' THEN 4 WHEN 'ING' THEN 5 WHEN 'ED.VISUAL' THEN 6 WHEN 'ED. V' THEN 6 WHEN 'OF' THEN 7 WHEN 'ED. FISICA' THEN 8 WHEN 'ED.FISICA' THEN 8 ELSE 99 END) AS ordem FROM {$tMC} mc INNER JOIN {$tD} d ON d.id=mc.disciplina_id WHERE mc.classe=%s AND mc.escola_id=%d ORDER BY ordem ASC,d.nome ASC", $cnorm, $eid));
    }
    if (empty($discs)) {
        $discs = $wpdb->get_results($wpdb->prepare("SELECT id,nome,sigla,categoria,COALESCE(ordem,CASE sigla WHEN 'POR' THEN 1 WHEN 'MAT' THEN 2 WHEN 'CS' THEN 3 WHEN 'CN' THEN 4 WHEN 'ING' THEN 5 WHEN 'ED.VISUAL' THEN 6 WHEN 'ED. V' THEN 6 WHEN 'OF' THEN 7 WHEN 'ED. FISICA' THEN 8 WHEN 'ED.FISICA' THEN 8 ELSE 99 END) AS ordem FROM {$tD} WHERE escola_id=%d AND activo=1 ORDER BY ordem ASC,nome ASC", $eid));
    }
    // [T5] Categoria vem da matriz_curricular (varia por classe), não de sige_disciplinas
    if ($cnorm !== '' && function_exists('sige_get_categoria_by_matriz')) { foreach ($discs as &$_d) { if (isset($_d->id)) $_d->categoria = sige_get_categoria_by_matriz($cnorm, $_d->id, $eid); } unset($_d); }
    // [MALISA-V2 sort guard] Ordem/categoria oficiais Malisa para 1.ª-6.ª.
    if (function_exists('sige_apply_categoria_oficial_disciplinas')) { $discs = sige_apply_categoria_oficial_disciplinas($discs, $cnorm ?: $cr); }
    if (function_exists('sige_sort_disciplinas_oficial')) { $discs = sige_sort_disciplinas_oficial($discs); }
}

$alunos = [];
if ($turma_id > 0 && $ano_sel > 0) {
    $pop_ch = function_exists('sige_aluno_matricula_activa_sql') ? sige_aluno_matricula_activa_sql('a','m') : "(m.status_matricula IS NULL OR m.status_matricula='activa')";
    $ord_ch = function_exists('sige_turma_ordem_chamada_order_sql') ? sige_turma_ordem_chamada_order_sql('a') : "a.nome_completo ASC, a.id ASC";
    $alunos = $wpdb->get_results($wpdb->prepare("SELECT a.id,a.nome_completo,a.genero,a.data_nascimento FROM {$tA} a INNER JOIN {$tM} m ON m.aluno_id=a.id WHERE m.turma_id=%d AND m.ano_lectivo=%d AND a.escola_id=%d AND {$pop_ch} ORDER BY {$ord_ch}", $turma_id, $ano_sel, $eid));
}

$nm = [];
if ($turma_id > 0 && $trimestre > 0 && $ano_sel > 0 && !empty($alunos)) {
    // [T6] Apenas notas aprovadas - pendentes não aparecem no DEC
    $nr = $wpdb->get_results($wpdb->prepare("SELECT aluno_id,disciplina_id,nota_ac,nota_acp,nota_exame,nota_conselho FROM {$tN} WHERE turma_id=%d AND trimestre=%d AND ano_lectivo=%d AND escola_id=%d AND (status IS NULL OR status='aprovado')", $turma_id, $trimestre, $ano_sel, $eid));
    foreach ($nr as $n) $nm[(int)$n->aluno_id][(int)$n->disciplina_id] = $n;
}

// DEC é trimestral - SEMPRE mostra AT (AF/Exame é só na Pauta Final)
$lc3 = 'AT';
$cmed = function($a,$b){ return ($a===null||$b===null)?null:((float)$a+(float)$b)/2; };
$cmt  = function($a,$b,$c) use ($cmed){ $m=$cmed($a,$b); return ($m===null||$c===null)?null:(int)round((2*$m+(float)$c)/3,0); };


$dec_genero_bucket = static function ($raw): string {
    // v12.11.9.18 - Fonte única de verdade: delega na normalização canónica
    // partilhada (academic-logic.php), garantindo DEC ≡ ACTA ≡ Pauta Final.
    // Mapeia a convenção canónica (M=Masculino, F=Feminino, U=desconhecido)
    // para a convenção do DEC (H=Homens, M=Mulheres, ''=desconhecido).
    if (function_exists('sige_genero_bucket')) {
        $b = sige_genero_bucket($raw);
        return $b === 'M' ? 'H' : ($b === 'F' ? 'M' : '');
    }
    // Fallback robusto (caso a logic não esteja carregada).
    $g = trim((string)($raw ?? ''));
    if ($g === '') return '';
    $u = function_exists('remove_accents') ? remove_accents($g) : $g;
    $u = function_exists('mb_strtoupper') ? mb_strtoupper($u, 'UTF-8') : strtoupper($u);
    $u = preg_replace('/[^A-Z]/', '', $u);
    if (in_array($u, ['H','HOMEM','HOMENS','M','MASC','MASCULINO','MASCULINA','MACHO','MALE'], true)) return 'H';
    if (in_array($u, ['F','FEM','FEMININO','FEMININA','FEMEA','MULHER','MULHERES','FEMALE'], true)) return 'M';
    return '';
};

// ─── MONTAR DADOS + STATS POR COLUNA ─────────────────────────────
$dd = []; $ck = ['ac','acp','med','at','mt']; $st = [];
foreach ($discs as $d) { $did=(int)$d->id; $st[$did]=[]; foreach ($ck as $k) $st[$did][$k]=['all'=>[],'H'=>[],'M'=>[]]; }
foreach ($alunos as $a) {
    $aid=(int)$a->id; $g=$dec_genero_bucket($a->genero??'');
    foreach ($discs as $d) {
        $did=(int)$d->id; $n=$nm[$aid][$did]??null;
        $ac=($n&&$n->nota_ac!==null)?(float)$n->nota_ac:null;
        $acp=($n&&$n->nota_acp!==null)?(float)$n->nota_acp:null;
        $at=($n&&$n->nota_exame!==null)?(float)$n->nota_exame:null;
        $med=$cmed($ac,$acp); $mt=($n&&isset($n->nota_conselho)&&$n->nota_conselho!==null&&$n->nota_conselho!==''&&is_numeric($n->nota_conselho))?(int)round((float)$n->nota_conselho,0):$cmt($ac,$acp,$at);
        $dd[$aid][$did]=compact('ac','acp','med','at','mt');
        $vm=['ac'=>$ac,'acp'=>$acp,'med'=>$med,'at'=>$at,'mt'=>$mt];
        foreach ($vm as $kk=>$vv) {
            if ($vv!==null) {
                $st[$did][$kk]['all'][]=$vv;
                if ($g==='H' || $g==='M') $st[$did][$kk][$g][]=$vv;
            }
        }
    }
}

$cfx = function(array $v, string $f): int { $c=0; foreach($v as $x){ $xi=(int)round((float)$x,0); switch($f){ case'NS':if($xi>=0&&$xi<=9)$c++;break; case'S':if($xi>=10&&$xi<=13)$c++;break; case'B':if($xi>=14&&$xi<=16)$c++;break; case'MB':if($xi>=17&&$xi<=18)$c++;break; case'E':if($xi>=19&&$xi<=20)$c++;break; } } return $c; };
$mav = function(array $v):?float { return empty($v)?null:round(array_sum($v)/count($v),1); };

$tl = ''; if ($ti) $tl = ($ti->classe??'')."\xC2\xAA Classe \xE2\x80\x94 Turma ".($ti->nome??'');
$trL = [1=>'I Trimestre',2=>'II Trimestre',3=>'III Trimestre']; $trN=$trL[$trimestre]??'';


$dec_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'print' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'excel' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 6-6"/><path d="m9 9 6 6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
    ];
    $path = $map[$name] ?? $map['file'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

// ═══════════ UI ═══════════
?>
<style id="sige-dec-produto-pro-v121073">
/* SIGE SoftGenial v12.10.73 - DEC: Estatística por Género Correcta
   Escopo visual apenas: não altera cálculos, notas, estatísticas, queries ou exportação. */
.dw{
    --dec-blue:var(--color-info-700);
    --dec-blue-dark:var(--color-info-800);
    --dec-purple:var(--color-brand-500);
    --dec-purple-soft:var(--color-brand-50);
    --dec-ink:var(--color-black);
    --dec-muted:var(--color-slate-700);
    --dec-line:var(--color-ink-100);
    --dec-green:var(--color-success-500);
    --dec-red:var(--color-danger-500);
    --dec-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--dec-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.dw *{box-sizing:border-box}
.dw svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - mesmo ADN do Painel Principal */
.dh{
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
}
.dh:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.dh-main,.dh-panel{position:relative;z-index:1}
.dk{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    padding:0;
    border:0;
    background:transparent;
    color:var(--dec-blue);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.dh h2{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
    font-family:inherit;
}
.dh p{
    max-width:650px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.dm{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.dp{
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
.dp svg{color:var(--dec-purple)}
.dh-panel{
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
.dh-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.dh-panel>*{position:relative;z-index:1}
.dh-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--dec-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.dh-panel strong{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.dh-panel small{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* Filtros */
.df{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:18px;
    display:grid;
    grid-template-columns:repeat(3,minmax(220px,1fr));
    gap:var(--space-3);
    align-items:end;
    box-shadow:var(--shadow-md);
    margin:0;
}
.df label{font-size:var(--fs-xs);font-weight:700;color:var(--color-slate-600);display:block;margin-bottom:7px;text-transform:uppercase;letter-spacing:.07em}
.df select{
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
.df select:focus{border-color:rgba(90,63,214,.55);box-shadow:0 1px 2px rgba(15,23,42,.04)}

.dc{
    margin:0;
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:var(--space-3);
    padding:16px 18px;
    border-radius:var(--radius-xl);
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-md);
    color:var(--color-ink-500);
}
.dcm{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-800)}
.dec-meta-pill{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:var(--radius-pill);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-slate-700);font-size:12px;font-weight:700}
.dec-meta-pill svg{color:var(--dec-blue)}
.dec-actions{display:flex;gap:var(--space-2);flex-wrap:wrap}
.db{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    min-height:42px;
    padding:0 var(--space-4);
    border-radius:var(--radius-md);
    font-size:12.5px;
    font-weight:700;
    cursor:pointer;
    border:1px solid transparent;
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease;
    font-family:inherit;
    text-decoration:none;
}
.db:hover{transform:translateY(-1px)}
.dbp{background:linear-gradient(135deg,var(--dec-blue),var(--dec-blue-dark));color:var(--color-white);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.dbp:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}
.dbe{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.dbe:hover{background:var(--color-success-100)}

/* Tabelas */
.ds,.ss{
    overflow:auto;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    box-shadow:var(--shadow-md);
}
table.dt{border-collapse:separate;border-spacing:0;width:100%;min-width:980px;font-size:var(--fs-xs);background:var(--color-white)}
table.dt th,table.dt td{border-right:1px solid var(--color-ink-100);border-bottom:1px solid var(--color-ink-100);padding:6px 7px;text-align:center;white-space:nowrap}
table.dt th:last-child,table.dt td:last-child{border-right:none}
table.dt thead tr:first-child th{background:var(--color-info-700);color:var(--color-white);font-size:10px;padding:8px 6px}
table.dt thead tr:nth-child(2) th{background:var(--color-brand-500);color:var(--color-white);font-size:9px}
table.dt tbody tr:hover td{background:var(--color-white)}
table.dt tbody tr:nth-child(even) td{background:var(--color-white)}
td.dn{text-align:left;padding-left:10px;min-width:180px;font-weight:600;color:var(--color-info-900);position:sticky;left:0;background:var(--color-white);z-index:1}
td.dx{font-weight:700;color:var(--color-info-700)}
td.dm2{background:var(--color-success-50)!important;font-weight:700;color:var(--color-success-900)}
td.dmt{background:var(--color-info-50)!important;font-weight:700;color:var(--color-info-600)}
td.dng{color:var(--color-danger-500)!important;font-weight:700}

.ss{margin-top:0}
table.st{border-collapse:separate;border-spacing:0;width:100%;min-width:980px;font-size:10px;background:var(--color-white)}
table.st th{background:var(--color-info-700);color:var(--color-white);padding:6px 5px;border-right:1px solid rgba(255,255,255,.15);border-bottom:1px solid rgba(255,255,255,.1);font-size:9px}
table.st td{padding:5px 5px;border-right:1px solid var(--color-ink-100);border-bottom:1px solid var(--color-ink-100);text-align:center;font-size:10px}
td.sg{font-weight:700;writing-mode:vertical-rl;text-align:center;background:var(--color-brand-50);color:var(--color-brand-500);padding:6px 4px;font-size:9px}
td.sl{text-align:left;padding-left:8px;font-weight:600;color:var(--color-slate-800);white-space:nowrap;font-size:9px;min-width:120px}
tr.dec-row-h td{background:var(--color-slate-50)}
tr.dec-row-h td.sg{background:var(--color-info-100);color:var(--color-info-700)}
tr.dec-row-m td{background:var(--color-slate-50)}
tr.dec-row-m td.sg{background:var(--color-danger-50);color:var(--color-danger-600)}
tr.hm td{background:var(--color-warning-50);font-weight:700}
tr.hm td.sg{background:var(--color-warning-500);color:var(--color-white)}

.de{
    padding:38px 20px;
    text-align:center;
    background:var(--color-white);
    border-radius:var(--radius-xl);
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-md);
}
.de h3{color:var(--color-black);margin:0 0 var(--space-2);font-size:18px;font-weight:700;letter-spacing:-.02em}
.de p{color:var(--color-slate-500);margin:0;font-size:var(--fs-sm);line-height:1.55}
.dec-legend{margin-top:0;font-size:12px;color:var(--color-slate-600);display:flex;gap:10px;flex-wrap:wrap;padding:0 4px}
.dec-legend span{display:inline-flex;align-items:center;padding:8px 11px;border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.dft{margin-top:0;text-align:center;font-size:var(--fs-xs);color:var(--color-slate-400);padding:8px 0}

@media(max-width:980px){
    .dh{grid-template-columns:1fr;padding:26px 24px}
    .df{grid-template-columns:1fr}
}
@media(max-width:680px){
    .dh h2{font-size:24px}
    .dc{align-items:stretch}
    .dec-actions,.db{width:100%}
    .dec-actions{display:grid;grid-template-columns:1fr}
}
@media print{
    .dh,.df,.dc,.dft{display:none!important}
    .dw{padding:0!important;display:block!important}
}
</style>

<div class="dw">
<?php if (!empty($sige_dec_scope_msg)): ?>
    <?php if (function_exists('sige_render_teacher_scope_denied')) { sige_render_teacher_scope_denied($sige_dec_scope_msg); } else { echo '<div class="notice notice-warning"><p>' . esc_html($sige_dec_scope_msg) . '</p></div>'; } ?>
<?php endif; ?>
<div class="dh">
    <div class="dh-main">
        <div class="dk"><?php echo $dec_icon('file'); ?><span>Académico</span></div>
        <h2>Documento Estatístico da Classe</h2>
        <p>Consulte o DEC por turma e trimestre, com estatísticas por coluna e leitura desagregada por género.</p>
        <div class="dm">
            <span class="dp"><?php echo $dec_icon('calendar'); ?> Ano Lectivo <?php echo esc_html((string)$ano_sel); ?></span>
            <?php if ($trimestre): ?><span class="dp"><?php echo $dec_icon('chart'); ?> <?php echo esc_html($trN); ?></span><?php endif; ?>
            <span class="dp"><?php echo $dec_icon('shield'); ?> Regras académicas preservadas</span>
        </div>
    </div>
    <aside class="dh-panel" aria-label="Estado do DEC">
        <div class="dh-panel-label"><?php echo $dec_icon('chart'); ?><span>Estado</span></div>
        <strong><?php echo ($turma_id && $trimestre) ? 'Pronto' : 'Por configurar'; ?></strong>
        <small><?php echo ($turma_id && $trimestre) ? 'Turma e trimestre seleccionados. Pode consultar, imprimir ou exportar o DEC.' : 'Seleccione a turma e o trimestre para gerar o documento estatístico.'; ?></small>
    </aside>
</div>

<?php $ps=isset($_GET['page'])?sanitize_text_field($_GET['page']):''; ?>
<form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>">
<input type="hidden" name="page" value="<?php echo esc_attr($ps); ?>"><input type="hidden" name="view" value="dec">
<div class="df">
<div><label>Turma</label><select name="turma_id" onchange="this.form.submit()"><option value="">-- Seleccionar --</option>
<?php foreach($turmas as $t): ?><option value="<?php echo esc_attr($t->id); ?>" <?php selected($turma_id,(int)$t->id); ?>><?php echo esc_html(($t->classe??'')."\xC2\xAA Cl. \xE2\x80\x94 ".($t->nome??'').' ('.($t->ano_lectivo??'').')'); ?></option><?php endforeach; ?></select></div>
<?php if($turma_id): ?><div><label>Trimestre</label><select name="trimestre" onchange="this.form.submit()"><option value="">-- Seleccionar --</option>
<?php foreach($trL as $tv=>$tll): ?><option value="<?php echo esc_attr($tv); ?>" <?php selected($trimestre,$tv); ?>><?php echo esc_html($tll); ?></option><?php endforeach; ?></select></div><?php endif; ?>
<div><label>Ano Lectivo</label><select name="ano_lectivo" onchange="this.form.submit()">
<?php for($y=(int)wp_date('Y');$y>=2020;$y--): ?><option value="<?php echo esc_attr($y); ?>" <?php selected($ano_sel,$y); ?>><?php echo esc_html((string)$y); ?></option><?php endfor; ?></select></div>
</div></form>

<?php if($turma_id && $trimestre && !empty($alunos) && !empty($discs)): ?>
<div class="dc">
    <div class="dcm">
        <span class="dec-meta-pill"><?php echo $dec_icon('book'); ?> <?php echo esc_html($tl); ?></span>
        <span class="dec-meta-pill"><?php echo $dec_icon('calendar'); ?> <?php echo esc_html($trN); ?> - <?php echo esc_html((string)$ano_sel); ?></span>
        <span class="dec-meta-pill"><?php echo $dec_icon('users'); ?> <?php echo count($alunos); ?> aluno(s)</span>
        <span class="dec-meta-pill"><?php echo $dec_icon('file'); ?> <?php echo count($discs); ?> disciplina(s)</span>
        <?php if($efc): ?><span class="dec-meta-pill" style="color:var(--color-danger-700);"><?php echo $dec_icon('shield'); ?> Fim de Ciclo</span><?php endif; ?>
    </div>
    <div class="dec-actions">
        <button type="button" class="db dbp" data-sige-act="decPrint" data-sige-noargs><?php echo $dec_icon('print'); ?> Imprimir / PDF</button>
        <button type="button" class="db dbe" data-sige-act="decXls" data-sige-noargs><?php echo $dec_icon('excel'); ?> Exportar Excel</button>
    </div>
</div>

<div class="ds"><table class="dt" id="dec-t">
<thead><tr><th rowspan="2" style="min-width:30px">N&ordm;</th><th rowspan="2" style="min-width:150px;text-align:left;padding-left:8px;">Nome Completo</th><th rowspan="2" style="width:28px">G</th>
<?php foreach($discs as $d): $b=(strtolower($d->categoria??'')==='nuclear')?' &#9733;':' &#9675;'; ?><th colspan="5"><?php echo esc_html($d->nome).$b; ?></th><?php endforeach; ?></tr>
<tr><?php foreach($discs as $d): ?><th>1&ordf;</th><th>2&ordf;</th><th style="background:var(--color-success-800)!important">M&eacute;d</th><th><?php echo esc_html($lc3); ?></th><th style="background:var(--color-info-600)!important">MT</th><?php endforeach; ?></tr></thead>
<tbody>
<?php $num=1; foreach($alunos as $a): $aid=(int)$a->id; ?>
<tr><td class="dx"><?php echo $num++; ?></td><td class="dn"><?php echo esc_html($a->nome_completo); ?></td><td style="font-size:10px"><?php $gb=$dec_genero_bucket($a->genero??''); echo esc_html($gb!==''?$gb:'-'); ?></td>
<?php foreach($discs as $d): $v=$dd[$aid][(int)$d->id]??['ac'=>null,'acp'=>null,'med'=>null,'at'=>null,'mt'=>null]; $f=function($x){return $x!==null?number_format((float)$x,1,',',''):'&mdash;';}; ?>
<td><?php echo $f($v['ac']); ?></td><td><?php echo $f($v['acp']); ?></td><td class="dm2"><?php echo $f($v['med']); ?></td><td><?php echo $f($v['at']); ?></td><td class="dmt<?php echo($v['mt']!==null&&$v['mt']<10)?' dng':''; ?>"><?php echo $v['mt']!==null?(int)$v['mt']:'&mdash;'; ?></td>
<?php endforeach; ?></tr>
<?php endforeach; ?>
</tbody></table></div>

<!-- ESTATÍSTICAS POR COLUNA -->
<div class="ss"><table class="st" id="dec-s">
<thead><tr><th colspan="2">Estat&iacute;stica</th>
<?php foreach($discs as $d): ?><th>1&ordf;</th><th>2&ordf;</th><th style="background:var(--color-success-800)">M&eacute;d</th><th><?php echo esc_html($lc3); ?></th><th style="background:var(--color-info-600)">MT</th><?php endforeach; ?></tr>
<tr><th colspan="2"></th><?php foreach($discs as $d): ?><th colspan="5"><?php echo esc_html($d->sigla?:mb_substr($d->nome,0,8)); ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php
$sR=['ct'=>'N&ordm; avaliados','NS'=>'N. Satisf. (0-9)','S'=>'Satisfat. (10-13)','B'=>'Bom (14-16)','MB'=>'M. Bom (17-18)','E'=>'Excelente (19-20)','pc'=>'Percentagem','ma'=>'Nota M&eacute;dia'];
$gL=['H'=>'Homens','M'=>'Mulheres','HM'=>'Total (HM)'];
// Pré-calcular contagens por género
$cnt_g = ['H'=>0,'M'=>0];
foreach($alunos as $a){ $gg=$dec_genero_bucket($a->genero??''); if($gg==='H'||$gg==='M') $cnt_g[$gg]++; }
$cnt_g['HM'] = count($alunos);

foreach(['H','M','HM'] as $gk): $fi=true; $rc=count($sR); $row_class=($gk==='H')?'dec-row-h':(($gk==='M')?'dec-row-m':'hm dec-row-hm');
foreach($sR as $sk=>$sl): ?>
<tr class="<?php echo esc_attr($row_class); ?>">
<?php if($fi): ?><td class="sg" rowspan="<?php echo $rc; ?>"><?php echo esc_html($gL[$gk]); ?></td><?php $fi=false; endif; ?>
<td class="sl"><?php echo $sl; ?></td>
<?php foreach($discs as $d): $did=(int)$d->id;
foreach($ck as $kk): $gkey=($gk==='HM')?'all':$gk; $vs=$st[$did][$kk][$gkey]??[];
if($sk==='ct'){ $vv=count($vs); }
elseif($sk==='ma'){ $mv=$mav($vs); $vv=($mv!==null)?number_format($mv,1,',',''):'&mdash;'; }
elseif($sk==='pc'){ $tg=$cnt_g[$gk]; $vv=$tg>0?round(count($vs)/$tg*100,0).'%':'0%'; }
else{ $vv=$cfx($vs,$sk); }
$sty=''; if($sk==='NS'&&is_int($vv)&&$vv>0)$sty=' style="color:var(--color-danger-500);font-weight:700"'; if($sk==='ma')$sty=' style="font-weight:700"';
?><td<?php echo $sty; ?>><?php echo $vv; ?></td>
<?php endforeach; endforeach; ?></tr>
<?php endforeach; endforeach; ?>
</tbody></table></div>

<div style="margin-top:12px;font-size:11px;color:var(--color-slate-600);display:flex;gap:16px;flex-wrap:wrap;padding:0 4px">
<span>M&eacute;d ACS = (1&ordf; ACS + 2&ordf; ACS) / 2</span>
<span>MT = ROUND((2&times;M&eacute;d + <?php echo esc_html($lc3); ?>) / 3, 0)</span>
<span>Disciplina Nuclear</span></div>

<?php elseif($turma_id && $trimestre && empty($alunos)): ?>
<div class="de"><h3>Nenhum aluno matriculado</h3><p>Sem matr&iacute;culas activas nesta turma/ano.</p></div>
<?php elseif($turma_id && !$trimestre): ?>
<div class="de"><h3>Seleccione um trimestre</h3><p>Escolha o trimestre para visualizar o DEC.</p></div>
<?php else: ?>
<div class="de"><h3>Seleccione uma turma</h3><p>Escolha uma turma para começar.</p></div>
<?php endif; ?>
<div class="dft">SIGE SoftGenial &middot; <?php echo esc_html($_en); ?> &middot; DEC</div>
</div>

<?php if($turma_id && $trimestre && !empty($alunos) && !empty($discs)): ?>
<?php echo function_exists('sige_cdn_script') ? sige_cdn_script('exceljs') : ''; ?>
<?php echo function_exists('sige_cdn_script') ? sige_cdn_script('filesaver') : ''; ?>
<script <?php echo sige_csp_script_attr(); ?>>
function decPrint(){
var mt=document.getElementById('dec-t'),ms=document.getElementById('dec-s');
if(!mt)return;
var h='<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>DEC</title><style>'
+'*{margin:0;padding:0;box-sizing:border-box}'
+'body{font-family:Arial,sans-serif;font-size:8pt;color:#000;background:#fff;padding:10mm}'
+'.hd{text-align:center;border-bottom:2pt solid #000;padding-bottom:6pt;margin-bottom:8pt}'
+'.hd h1{font-size:13pt}.hd h2{font-size:9pt;font-weight:normal}.hd p{font-size:8pt;color:#444;margin-top:2pt}'
+'table{width:100%;border-collapse:collapse;font-size:7pt;margin-bottom:8pt}'
+'th{background:#ddd;padding:3pt;border:.5pt solid #999;text-align:center;font-size:6.5pt}'
+'td{padding:2pt 3pt;border:.5pt solid #ccc;text-align:center}'
+'td.n{text-align:left;font-weight:600}'
+'.ng{color:#c00;font-weight:700}'
+'.hm td{font-weight:700;background:#f0f0f0}'
+'.lg{font-size:6.5pt;color:#666;margin-top:6pt;text-align:center}'
+'@page{size:A4 landscape;margin:8mm}'
+'</style></head><body><div class="hd">'
+'<h1>'+<?php echo wp_json_encode(mb_strtoupper($_en)); ?>+'</h1>'
+'<h2>Documento Estat\u00edstico da Classe (DEC) \u2014 '+<?php echo wp_json_encode($trN); ?>+' \u2014 '+<?php echo wp_json_encode((string)$ano_sel); ?>+'</h2>'
+'<p>'+<?php echo wp_json_encode($tl); ?>+'</p></div>';
var ct=mt.cloneNode(true);ct.removeAttribute('id');ct.removeAttribute('class');h+=ct.outerHTML;
if(ms){var cs=ms.cloneNode(true);cs.removeAttribute('id');cs.removeAttribute('class');h+=cs.outerHTML;}
h+='<p class="lg">M\u00e9d=(1\u00aa+2\u00aa)/2 | MT=ROUND((2\u00d7M\u00e9d+'+<?php echo wp_json_encode($lc3); ?>+')/3,0) | \u2605=Nuclear</p></body></html>';
var w=window.open('','_blank','width=1200,height=800,scrollbars=yes');
if(!w){sigeUi.toast('O navegador bloqueou a janela de impressão. Permita popups para este site e tente novamente.', 'aviso');return;}
w.document.write(h);w.document.close();
setTimeout(function(){w.focus();w.print();},400);
}
function decXls(){
if(typeof ExcelJS==='undefined'){sigeUi.toast('A biblioteca de exportação ainda não carregou. Aguarde uns segundos e tente novamente.', 'erro');return;}
var wb=new ExcelJS.Workbook(),ws=wb.addWorksheet('DEC');
ws.addRow([<?php echo wp_json_encode($_en); ?>]);
ws.addRow(['DEC - '+<?php echo wp_json_encode($trN); ?>+' - '+<?php echo wp_json_encode((string)$ano_sel); ?>]);
ws.addRow([<?php echo wp_json_encode($tl); ?>]);ws.addRow([]);
var h1=['N\u00ba','Nome','G'],h2=['','',''];
<?php foreach($discs as $d): ?>h1.push(<?php echo wp_json_encode($d->nome); ?>,'','','','');h2.push('1\u00aa','2\u00aa','M\u00e9d',<?php echo wp_json_encode($lc3); ?>,'MT');<?php endforeach; ?>
ws.addRow(h1);ws.addRow(h2);
<?php $n=1;foreach($alunos as $a):$aid=(int)$a->id;$r=[$n++,$a->nome_completo,($dec_genero_bucket($a->genero??'')?:'-')];foreach($discs as $d){$v=$dd[$aid][(int)$d->id]??['ac'=>null,'acp'=>null,'med'=>null,'at'=>null,'mt'=>null];$r[]=$v['ac'];$r[]=$v['acp'];$r[]=$v['med']!==null?round($v['med'],1):null;$r[]=$v['at'];$r[]=$v['mt'];}?>
ws.addRow(<?php echo wp_json_encode($r); ?>);
<?php endforeach; ?>
ws.addRow([]);ws.addRow(['ESTAT\u00cdSTICA']);
<?php
$srK=['ct'=>'Nº avaliados','NS'=>'N.Satisf.(0-9)','S'=>'Satisf.(10-13)','B'=>'Bom(14-16)','MB'=>'M.Bom(17-18)','E'=>'Excelente(19-20)','pc'=>'Percentagem','ma'=>'Nota Média'];
foreach(['H','M','HM'] as $gk){
    foreach($srK as $sk=>$sll){
        $rx=[$gL[$gk],$sll,''];
        foreach($discs as $d){$did=(int)$d->id;
            foreach($ck as $kk){$gkey=($gk==='HM')?'all':$gk;$vs=$st[$did][$kk][$gkey]??[];
                if($sk==='ct')$rx[]=count($vs);
                elseif($sk==='ma'){$mv=$mav($vs);$rx[]=$mv;}
                elseif($sk==='pc'){$tg=$cnt_g[$gk];$rx[]=$tg>0?round(count($vs)/$tg*100,0).'%':'0%';}
                else $rx[]=$cfx($vs,$sk);
            }
        }
        echo 'ws.addRow('.wp_json_encode($rx).");\n";
    }
}
?>
wb.xlsx.writeBuffer().then(function(b){saveAs(new Blob([b],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}),'DEC_'+<?php echo wp_json_encode(sanitize_file_name($tl)); ?>+'_T<?php echo $trimestre; ?>_<?php echo $ano_sel; ?>.xlsx');});
}
</script>
<?php endif; ?>
