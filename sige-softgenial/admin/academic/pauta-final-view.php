<?php
/**
 * SIGE SoftGenial - Pauta Final Oficial
 * v1.0 - Abril 2026
 * Vista: ?page=sige-app&view=pauta_final
 *
 * Layout adapta-se ao tipo de classe (conforme modelos SDEJT):
 *  - 1ª,2ª,4ª,5ª,6ª: MT1 | MT2 | MT3 | MFD
 *  - 3ª (nucleares POR/MAT): MT1 | MT2 | MT3 | MFD | AF | MF
 *  - 3ª (complementares): MT1 | MT2 | MT3 | MFD
 *  + Bloco de estatísticas por coluna (H/M/HM)
 *  + Impressão popup limpa + Excel
 *
 * Segurança: ABSPATH, capability, escola_id, prepare(), esc_*()
 * Zero alteração a ficheiros existentes.
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['academico.pauta_final_ver','academico.pauta_final_gerir'],
    ['sige_director','sige_pedagogico','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;
global $wpdb;
$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$_ep = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$_en = $_ep->nome_escola ?? get_bloginfo('name');
$p = $wpdb->prefix;
$tT=$p.'sige_turmas'; $tA=$p.'sige_alunos'; $tM=$p.'sige_matriculas'; $tN=$p.'sige_notas';
$tD=$p.'sige_disciplinas'; $tTD=$p.'sige_turma_disciplinas'; $tMC=$p.'sige_matriz_curricular';
$tR=$p.'sige_regras_academicas';

$ano_act = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (int)wp_date('Y');
$turma_id = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;
$ano_sel  = isset($_GET['ano_lectivo']) ? (int)$_GET['ano_lectivo'] : $ano_act;

// ─── Turmas ──────────────────────────────────────────────────────
$turmas = $wpdb->get_results($wpdb->prepare("SELECT id,nome,classe,ano_lectivo,turno FROM {$tT} WHERE escola_id=%d AND (status_turma IS NULL OR status_turma IN('activa','ativa')) ORDER BY ano_lectivo DESC,CAST(classe AS UNSIGNED) ASC,nome ASC", $eid));

// ─── Turma seleccionada ──────────────────────────────────────────
$ti=null; $cr=''; $cn=0;
if ($turma_id>0) {
    $ti=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$tT} WHERE id=%d AND escola_id=%d LIMIT 1",$turma_id,$eid));
    if($ti){$cr=(string)($ti->classe??'');$cn=function_exists('sige_parse_classe_num')?(int)sige_parse_classe_num($cr):(int)preg_replace('/\D+/','',$cr);}
}

// ─── Regras académicas ───────────────────────────────────────────
$regra = ['eh_fim_ciclo'=>0,'peso_mfd'=>60,'peso_exame'=>40,'exige_exame'=>0,'nota_minima_aprovacao'=>10,'max_negativas_transita'=>2,'max_negativas_progride'=>0,'usa_media_global'=>1,'media_minima_global'=>10];
if ($turma_id && $cn>0) {
    $rr=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$tR} WHERE ano_lectivo=%d AND classe_num=%d AND escola_id=%d AND ativo=1 ORDER BY id DESC LIMIT 1",$ano_sel,$cn,$eid),ARRAY_A);
    if($rr) $regra=array_merge($regra,$rr);
}
$efc=(int)($regra['eh_fim_ciclo']??0)===1;
$exige_exame=(int)($regra['exige_exame']??0)===1;
$peso_mfd=(float)($regra['peso_mfd']??60);
$peso_exame=(float)($regra['peso_exame']??40);
$nota_min=(int)($regra['nota_minima_aprovacao']??10);

// ─── TIPO DE PAUTA (auto-detectado) ─────────────────────────────
// tipo_pauta: 'standard' ou 'fim_ciclo_af' (interno: avaliação final de fim de ciclo).
// Regra visível: AF apenas na 3.ª classe; restantes classes usam Exame/NF.
$tipo_pauta = ($efc && $exige_exame) ? 'fim_ciclo_af' : 'standard';
$_af_lbl = function_exists('sige_label_avaliacao_final') ? sige_label_avaliacao_final($cn) : (($cn === 3) ? 'AF' : 'Exame');
$_nf_lbl = function_exists('sige_label_nota_final') ? sige_label_nota_final($cn) : (($cn === 3) ? 'MF' : 'NF');

// ─── Disciplinas (com CASE sigla oficial) ────────────────────────
$discs=[];
if ($turma_id>0 && $ti) {
    $cnorm='';
    if($cr!==''){if(is_numeric($cr))$cnorm=((int)$cr)."\xC2\xAA";elseif(preg_match('/^\s*(\d{1,2})\s*[\xC2\xAAa]?\s*([A-Za-z])?\s*$/u',$cr,$mm))$cnorm=((int)$mm[1])."\xC2\xAA";elseif(preg_match('/(\d{1,2})/u',$cr,$mm))$cnorm=((int)$mm[1])."\xC2\xAA";else $cnorm=$cr;}
    $discs=$wpdb->get_results($wpdb->prepare("SELECT d.id,d.nome,d.sigla,d.categoria,COALESCE(mc.ordem_pauta,CASE d.sigla WHEN 'POR' THEN 1 WHEN 'MAT' THEN 2 WHEN 'CN' THEN 3 WHEN 'CS' THEN 4 WHEN 'ING' THEN 5 WHEN 'ED.VISUAL' THEN 6 WHEN 'ED. V' THEN 6 WHEN 'OF' THEN 7 WHEN 'ED. FISICA' THEN 8 WHEN 'ED.FISICA' THEN 8 ELSE 99 END) AS ordem FROM {$tTD} td INNER JOIN {$tD} d ON d.id=td.disciplina_id LEFT JOIN {$tMC} mc ON mc.disciplina_id=d.id AND mc.classe=%s AND mc.escola_id=%d WHERE td.turma_id=%d AND td.escola_id=%d GROUP BY d.id ORDER BY ordem ASC,d.nome ASC",$cnorm,$eid,$turma_id,$eid));
    if(empty($discs)&&$cnorm!==''){$discs=$wpdb->get_results($wpdb->prepare("SELECT d.id,d.nome,d.sigla,d.categoria,COALESCE(mc.ordem_pauta,CASE d.sigla WHEN 'POR' THEN 1 WHEN 'MAT' THEN 2 WHEN 'CN' THEN 3 WHEN 'CS' THEN 4 WHEN 'ING' THEN 5 WHEN 'ED.VISUAL' THEN 6 WHEN 'ED. V' THEN 6 WHEN 'OF' THEN 7 WHEN 'ED. FISICA' THEN 8 WHEN 'ED.FISICA' THEN 8 ELSE 99 END) AS ordem FROM {$tMC} mc INNER JOIN {$tD} d ON d.id=mc.disciplina_id WHERE mc.classe=%s AND mc.escola_id=%d ORDER BY ordem ASC,d.nome ASC",$cnorm,$eid));}
    if(empty($discs)){$discs=$wpdb->get_results($wpdb->prepare("SELECT id,nome,sigla,categoria,COALESCE(ordem,CASE sigla WHEN 'POR' THEN 1 WHEN 'MAT' THEN 2 WHEN 'CN' THEN 3 WHEN 'CS' THEN 4 WHEN 'ING' THEN 5 WHEN 'ED.VISUAL' THEN 6 WHEN 'ED. V' THEN 6 WHEN 'OF' THEN 7 WHEN 'ED. FISICA' THEN 8 WHEN 'ED.FISICA' THEN 8 ELSE 99 END) AS ordem FROM {$tD} WHERE escola_id=%d AND activo=1 ORDER BY ordem ASC,nome ASC",$eid));}
    // [T5] Categoria vem da matriz_curricular (varia por classe), não de sige_disciplinas
    if ($cnorm !== '' && function_exists('sige_get_categoria_by_matriz')) { foreach ($discs as &$_d) { if (isset($_d->id)) $_d->categoria = sige_get_categoria_by_matriz($cnorm, $_d->id, $eid); } unset($_d); }
    // [MALISA-V3] Forçar ordem/categoria/label oficiais também na Pauta Final Oficial.
    if (function_exists('sige_apply_categoria_oficial_disciplinas')) { $discs = sige_apply_categoria_oficial_disciplinas($discs, $cnorm ?: $cr); }
    if (function_exists('sige_sort_disciplinas_oficial')) { $discs = sige_sort_disciplinas_oficial($discs); }
}

// ─── Alunos ──────────────────────────────────────────────────────
$alunos=[];
if($turma_id>0&&$ano_sel>0){
    $pop_ch = function_exists('sige_aluno_matricula_activa_sql') ? sige_aluno_matricula_activa_sql('a','m') : "(m.status_matricula IS NULL OR m.status_matricula='activa')";
    $ord_ch = function_exists('sige_turma_ordem_chamada_order_sql') ? sige_turma_ordem_chamada_order_sql('a') : "a.nome_completo ASC, a.id ASC";
    $alunos=$wpdb->get_results($wpdb->prepare("SELECT a.id,a.nome_completo,a.genero,a.numero_processo FROM {$tA} a INNER JOIN {$tM} m ON m.aluno_id=a.id WHERE m.turma_id=%d AND m.ano_lectivo=%d AND a.escola_id=%d AND {$pop_ch} ORDER BY {$ord_ch}",$turma_id,$ano_sel,$eid));
}

// ─── Notas (todos trimestres, status aprovado) ───────────────────
$nm=[]; // [aluno_id][disc_id][trimestre] => row
if($turma_id>0&&$ano_sel>0&&!empty($alunos)){
    $ids=array_map(function($o){return(int)$o->id;},$alunos);
    $ph=implode(',',array_fill(0,count($ids),'%d'));
    $prms=array_merge($ids,[$turma_id,$ano_sel,$eid]);
    $rows=$wpdb->get_results($wpdb->prepare("SELECT aluno_id,disciplina_id,trimestre,nota_ac,nota_acp,nota_exame,nota_at,nota_conselho FROM {$tN} WHERE aluno_id IN({$ph}) AND turma_id=%d AND ano_lectivo=%d AND escola_id=%d AND (status IS NULL OR status='aprovado')",$prms));
    foreach($rows as $r){$nm[(int)$r->aluno_id][(int)$r->disciplina_id][(int)$r->trimestre]=$r;}
}

// ─── Cálculos ────────────────────────────────────────────────────
$calc_mt=function($row){if(!$row)return null;if(isset($row->nota_conselho)&&$row->nota_conselho!==null&&$row->nota_conselho!==''&&is_numeric($row->nota_conselho))return(int)round((float)$row->nota_conselho,0);$ac=is_numeric($row->nota_ac)?(float)$row->nota_ac:null;$acp=is_numeric($row->nota_acp)?(float)$row->nota_acp:null;$at=is_numeric($row->nota_exame)?(float)$row->nota_exame:null;if($ac===null&&$acp===null)return null;$vs=array_filter([$ac,$acp],function($v){return $v!==null;});if($at===null)return(int)round(array_sum($vs)/count($vs),0);$med=array_sum($vs)/count($vs);return(int)round((2*$med+$at)/3,0);};

// ─── Normalização de género para estatística oficial ─────────────
$pf_genero_bucket = static function ($raw): string {
    // v12.11.9.18 - Fonte única de verdade partilhada (academic-logic.php).
    if (function_exists('sige_genero_bucket')) {
        $b = sige_genero_bucket($raw);
        return $b === 'M' ? 'H' : ($b === 'F' ? 'M' : '');
    }
    $g = trim((string)($raw ?? ''));
    if ($g === '') return '';
    $u = function_exists('remove_accents') ? remove_accents($g) : $g;
    $u = function_exists('mb_strtoupper') ? mb_strtoupper($u, 'UTF-8') : strtoupper($u);
    $u = preg_replace('/[^A-Z]/', '', $u);
    if (in_array($u, ['H','HOMEM','HOMENS','M','MASC','MASCULINO','MASCULINA','MACHO','MALE'], true)) return 'H';
    if (in_array($u, ['F','FEM','FEMININO','FEMININA','FEMEA','MULHER','MULHERES','FEMALE'], true)) return 'M';
    return '';
};

// ─── Montar dados ────────────────────────────────────────────────
// $pf_data[aid][did] = ['mt1'=>,'mt2'=>,'mt3'=>,'mfd'=>,'af'=>,'mf'=>,'is_nuclear'=>]
$pf_data=[];
$st=[]; // stats: [did][col_key] => ['all'=>[],'H'=>[],'M'=>[]]

// Determinar colunas de stats por disciplina
// Standard: mt1,mt2,mt3,mfd
// Fim ciclo nuclear: mt1,mt2,mt3,mfd,af,mf
foreach($discs as $d){
    $did=(int)$d->id;
    $is_nuc=strtolower($d->categoria??'')==='nuclear';
    $cols_s=['mt1','mt2','mt3','mfd'];
    if($tipo_pauta==='fim_ciclo_af'&&$is_nuc) $cols_s=array_merge($cols_s,['af','mf']);
    $st[$did]=['cols'=>$cols_s,'data'=>[]];
    foreach($cols_s as $ck) $st[$did]['data'][$ck]=['all'=>[],'H'=>[],'M'=>[]];
}

// Pré-calcular contagens género
$cnt_g=['H'=>0,'M'=>0,'HM'=>count($alunos)];
foreach($alunos as $a){
    $g=$pf_genero_bucket($a->genero??'');
    if($g==='H'||$g==='M') $cnt_g[$g]++;
}

foreach($alunos as $a){
    $aid=(int)$a->id;
    $g=$pf_genero_bucket($a->genero??'');

    foreach($discs as $d){
        $did=(int)$d->id;
        $is_nuc=strtolower($d->categoria??'')==='nuclear';
        $mts=[]; $mts_v=[];
        for($t=1;$t<=3;$t++){
            $row=$nm[$aid][$did][$t]??null;
            $mt=$calc_mt($row);
            $mts[$t]=$mt;
            if($mt!==null)$mts_v[]=$mt;
        }
        // MFD = (MT1+MT2+MT3)/3 - SEMPRE dividir por 3, MT ausente = 0
        $mt1=$mts[1]??0; $mt2=$mts[2]??0; $mt3=$mts[3]??0;
        $has_any=($mts[1]!==null||$mts[2]!==null||$mts[3]!==null);
        $mfd=$has_any?(int)round(((int)$mt1+(int)$mt2+(int)$mt3)/3,0):null;
        // AF e MF (só para fim_ciclo_af e nucleares)
        $af=null;$mf=null;
        if($tipo_pauta==='fim_ciclo_af'&&$is_nuc&&$mfd!==null){
            $row3=$nm[$aid][$did][3]??null;
            $af=($row3&&isset($row3->nota_at)&&is_numeric($row3->nota_at))?(float)$row3->nota_at:null;
            if($af!==null){
                $den=$peso_mfd+$peso_exame;
                $mf=($den>0)?(int)round(($mfd*$peso_mfd+$af*$peso_exame)/$den,0):$mfd;
            }
        }
        $pf_data[$aid][$did]=compact('mts','mfd','af','mf','is_nuc');

        // Stats
        $vm=['mt1'=>$mts[1]??null,'mt2'=>$mts[2]??null,'mt3'=>$mts[3]??null,'mfd'=>$mfd];
        if($tipo_pauta==='fim_ciclo_af'&&$is_nuc){$vm['af']=$af;$vm['mf']=$mf;}
        foreach($vm as $ck=>$val){
            if($val!==null&&isset($st[$did]['data'][$ck])){
                $st[$did]['data'][$ck]['all'][]=$val;
                if($g==='H'||$g==='M') $st[$did]['data'][$ck][$g][]=$val;
            }
        }
    }
}

// ─── Helpers ─────────────────────────────────────────────────────
$cfx=function(array $v,string $f):int{$c=0;foreach($v as $x){$xi=(int)round((float)$x,0);switch($f){case'NS':if($xi>=0&&$xi<=9)$c++;break;case'S':if($xi>=10&&$xi<=13)$c++;break;case'B':if($xi>=14&&$xi<=16)$c++;break;case'MB':if($xi>=17&&$xi<=18)$c++;break;case'E':if($xi>=19&&$xi<=20)$c++;break;}}return $c;};
$mav=function(array $v):?float{return empty($v)?null:round(array_sum($v)/count($v),1);};
$esc=function($n){if($n===null)return'';$v=(int)$n;if($v<10)return'NS';if($v<14)return'S';if($v<17)return'B';if($v<19)return'MB';return'E';};

$tl='';if($ti)$tl=($ti->classe??'')."\xC2\xAA Classe \xE2\x80\x94 Turma ".($ti->nome??'');
$tipo_label=$tipo_pauta==='fim_ciclo_af' ? ('Fim de Ciclo (com '.$_af_lbl.'/'.$_nf_lbl.' para nucleares)') : 'Standard';


$pf_icon = static function (string $name): string {
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
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
    ];
    $path = $map[$name] ?? $map['file'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

// ═══════════ UI ═══════════
?>
<style id="sige-pauta-final-produto-pro-v121074">
/* SIGE SoftGenial v12.10.75 - Pauta Final Oficial: Estatística por Género Correcta
   Escopo visual apenas: não altera cálculos, notas, progressão, transição, estatísticas, impressão ou exportação. */
.pf-wrap{
    --pf-blue:var(--color-info-700);
    --pf-blue-dark:var(--color-info-800);
    --pf-purple:var(--color-brand-500);
    --pf-purple-soft:var(--color-brand-50);
    --pf-ink:var(--color-black);
    --pf-muted:var(--color-slate-700);
    --pf-line:var(--color-ink-100);
    --pf-green:var(--color-success-500);
    --pf-red:var(--color-danger-500);
    --pf-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--pf-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.pf-wrap *{box-sizing:border-box}
.pf-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - espelho do Painel Principal / Dashboard V2 MJS-grade */
.pf-hero{
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
.pf-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.pf-hero-main,.pf-hero-panel{position:relative;z-index:1}
.pf-kicker{display:inline-flex;align-items:center;gap:var(--space-2);margin:0 0 10px;padding:0;border:0;background:transparent;color:var(--pf-blue);font-size:12px;line-height:1.2;font-weight:700;letter-spacing:.11em;text-transform:uppercase}
.pf-hero h2{margin:0;max-width:650px;color:var(--color-black);font-size:31px;line-height:1.08;font-weight:700;letter-spacing:-.04em;font-family:inherit}
.pf-hero p{max-width:650px;margin:var(--space-3) 0 0;color:var(--color-slate-700);font-size:15px;line-height:1.65;font-weight:500}
.pf-hero-pills{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.pf-pill{display:inline-flex;align-items:center;gap:var(--space-2);min-height:38px;padding:var(--space-2) var(--space-3);border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-100);color:var(--color-slate-700);font-size:12px;font-weight:700;box-shadow:var(--shadow-sm);margin:0}
.pf-pill svg{color:var(--pf-purple)}
.pf-hero-panel{min-height:148px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));padding:22px;overflow:hidden;display:flex;flex-direction:column;justify-content:center;gap:10px;border:1px solid rgba(92,64,187,.08)}
.pf-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.pf-hero-panel>*{position:relative;z-index:1}
.pf-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--pf-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.pf-hero-panel strong{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.pf-hero-panel small{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* Filtros */
.pf-flt{background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);padding:18px;display:grid;grid-template-columns:repeat(2,minmax(220px,1fr));gap:var(--space-3);align-items:end;box-shadow:var(--shadow-md);margin:0}
.pf-flt label{font-size:var(--fs-xs);font-weight:700;color:var(--color-slate-600);display:block;margin-bottom:7px;text-transform:uppercase;letter-spacing:.07em}
.pf-flt select{width:100%;min-height:44px;padding:0 13px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);font-size:var(--fs-sm);color:var(--color-ink-500);font-weight:600;background:var(--color-white);box-shadow:var(--shadow-sm);outline:none}
.pf-flt select:focus{border-color:rgba(90,63,214,.55);box-shadow:0 1px 2px rgba(15,23,42,.04)}

/* Contexto e acções */
.pf-ctx{margin:0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--space-3);padding:16px 18px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-md);color:var(--color-ink-500)}
.pf-ctx-m{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-800)}
.pf-meta-pill{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:var(--radius-pill);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-slate-700);font-size:12px;font-weight:700}
.pf-meta-pill svg{color:var(--pf-blue)}
.pf-actions{display:flex;gap:var(--space-2);flex-wrap:wrap}
.pf-btn{display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);min-height:42px;padding:0 var(--space-4);border-radius:var(--radius-md);font-size:12.5px;font-weight:700;cursor:pointer;border:1px solid transparent;transition:transform .18s ease,box-shadow .18s ease,background .18s ease;font-family:inherit;text-decoration:none}
.pf-btn:hover{transform:translateY(-1px)}
.pf-bp{background:linear-gradient(135deg,var(--pf-blue),var(--pf-blue-dark));color:var(--color-white);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.pf-bp:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}
.pf-be{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.pf-be:hover{background:var(--color-success-100)}

/* Tabelas */
.pf-sc,.pf-ss{overflow:auto;background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);box-shadow:0 4px 16px rgba(15,23,42,.08)}
table.pf-t{border-collapse:separate;border-spacing:0;width:100%;min-width:1180px;font-size:var(--fs-xs);background:var(--color-white)}
table.pf-t th,table.pf-t td{border-right:1px solid var(--color-ink-100);border-bottom:1px solid var(--color-ink-100);padding:6px 7px;text-align:center;white-space:nowrap}
table.pf-t th:last-child,table.pf-t td:last-child{border-right:none}
table.pf-t thead tr:first-child th{background:var(--color-info-700);color:var(--color-white);font-size:10px;padding:8px 6px}
table.pf-t thead tr:nth-child(2) th{background:var(--color-brand-500);color:var(--color-white);font-size:9px}
table.pf-t tbody tr:hover td{background:var(--color-white)}
table.pf-t tbody tr:nth-child(even) td{background:var(--color-white)}
td.pf-nm{text-align:left;padding-left:10px;min-width:180px;font-weight:600;color:var(--color-info-900);position:sticky;left:0;background:var(--color-white);z-index:1}
td.pf-n{font-weight:700;color:var(--color-info-700)}
.td-mt{font-weight:600;color:var(--color-ink-800)}.td-mfd{font-weight:700;color:var(--color-info-900);background:var(--color-info-50)}.td-af{font-weight:700;color:var(--color-danger-700);background:var(--color-danger-50)}
.td-mf{font-weight:700;color:var(--color-success-900);background:var(--color-success-50);font-size:12px}.td-mg{font-weight:700;color:var(--color-info-900);background:var(--color-info-100)}
.td-neg{font-weight:700;color:var(--color-danger-500)}.td-sit span{display:inline-block;padding:5px 11px;border-radius:var(--radius-pill);font-weight:700;font-size:10px;white-space:nowrap;letter-spacing:.02em}
.sit-progride{background:var(--color-success-500);color:var(--color-white)}.sit-transita{background:var(--color-warning-500);color:var(--color-black)}.sit-reprova{background:var(--color-danger-600);color:var(--color-white)}

.pf-ss{margin-top:0}
table.pf-st{border-collapse:separate;border-spacing:0;width:100%;min-width:980px;font-size:10px;background:var(--color-white)}
table.pf-st th{background:var(--color-info-700);color:var(--color-white);padding:6px 5px;border-right:1px solid rgba(255,255,255,.15);border-bottom:1px solid rgba(255,255,255,.1);font-size:9px}
table.pf-st td{padding:5px 5px;border-right:1px solid var(--color-ink-100);border-bottom:1px solid var(--color-ink-100);text-align:center;font-size:10px}
td.pf-sg{font-weight:700;writing-mode:vertical-rl;text-align:center;background:var(--color-brand-50);color:var(--color-brand-500);padding:6px 4px;font-size:9px}
td.pf-sl{text-align:left;padding-left:8px;font-weight:600;color:var(--color-slate-800);white-space:nowrap;font-size:9px;min-width:120px}
tr.pf-row-h td{background:var(--color-slate-50)}
tr.pf-row-h td.pf-sg{background:var(--color-info-100);color:var(--color-info-700)}
tr.pf-row-m td{background:var(--color-slate-50)}
tr.pf-row-m td.pf-sg{background:var(--color-danger-50);color:var(--color-danger-600)}
tr.pf-hm td{background:var(--color-warning-50);font-weight:700}tr.pf-hm td.pf-sg{background:var(--color-warning-500);color:var(--color-white)}

.pf-legend{margin-top:0;font-size:12px;color:var(--color-slate-600);display:flex;gap:10px;flex-wrap:wrap;padding:0 4px}
.pf-legend span{display:inline-flex;align-items:center;padding:8px 11px;border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.pf-signatures{margin-top:12px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:30px;font-size:var(--fs-xs);color:var(--color-ink-800)}
.pf-signatures div{border-top:1px solid var(--color-slate-900);padding-top:7px;text-align:center}
.pf-em{padding:38px 20px;text-align:center;background:var(--color-white);border-radius:var(--radius-xl);border:1px solid rgba(28,32,54,.08);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.pf-em h3{color:var(--color-black);margin:0 0 var(--space-2);font-size:18px;font-weight:700;letter-spacing:-.02em}.pf-em p{color:var(--color-slate-500);margin:0;font-size:var(--fs-sm);line-height:1.55}
.pf-ft{margin-top:0;text-align:center;font-size:var(--fs-xs);color:var(--color-slate-400);padding:8px 0}

@media(max-width:980px){.pf-hero{grid-template-columns:1fr;padding:26px 24px}.pf-flt{grid-template-columns:1fr}.pf-signatures{grid-template-columns:1fr;gap:22px}}
@media(max-width:680px){.pf-hero h2{font-size:24px}.pf-ctx{align-items:stretch}.pf-actions,.pf-btn{width:100%}.pf-actions{display:grid;grid-template-columns:1fr}}
@media print{.pf-hero,.pf-flt,.pf-ctx,.pf-ft{display:none!important}.pf-wrap{padding:0!important;display:block!important}}
</style>

<div class="pf-wrap">
<div class="pf-hero">
    <div class="pf-hero-main">
        <div class="pf-kicker"><?php echo $pf_icon('file'); ?><span>Académico</span></div>
        <h2>Pauta Final Oficial</h2>
        <p>Consulte, imprima e exporte a pauta final oficial conforme os modelos SDEJT, com adaptação automática ao tipo de classe.</p>
        <div class="pf-hero-pills">
            <span class="pf-pill"><?php echo $pf_icon('calendar'); ?> Ano Lectivo <?php echo esc_html((string)$ano_sel); ?></span>
            <?php if($ti): ?><span class="pf-pill"><?php echo $pf_icon('book'); ?> <?php echo esc_html($tl); ?></span><span class="pf-pill"><?php echo $pf_icon('shield'); ?> <?php echo esc_html($tipo_label); ?></span><?php endif; ?>
        </div>
    </div>
    <aside class="pf-hero-panel" aria-label="Estado da pauta final">
        <div class="pf-panel-label"><?php echo $pf_icon('chart'); ?><span>Estado</span></div>
        <strong><?php echo ($turma_id && !empty($alunos) && !empty($discs)) ? 'Pronta' : 'Por configurar'; ?></strong>
        <small><?php echo ($turma_id && !empty($alunos) && !empty($discs)) ? 'Turma seleccionada. Pode consultar, imprimir ou exportar a pauta final oficial.' : 'Seleccione uma turma para gerar a pauta final oficial.'; ?></small>
    </aside>
</div>

<?php $ps=isset($_GET['page'])?sanitize_text_field($_GET['page']):''; ?>
<form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>">
<input type="hidden" name="page" value="<?php echo esc_attr($ps); ?>"><input type="hidden" name="view" value="pauta_final">
<div class="pf-flt">
<div><label>Turma</label><select name="turma_id" onchange="this.form.submit()"><option value="">-- Seleccionar --</option>
<?php foreach($turmas as $t): ?><option value="<?php echo esc_attr($t->id); ?>" <?php selected($turma_id,(int)$t->id); ?>><?php $ccn=(int)preg_replace('/\D+/','',$t->classe??'');echo esc_html(($ccn?$ccn."\xC2\xAA Cl.":($t->classe??''))." \xE2\x80\x94 ".($t->nome??'').' ('.($t->ano_lectivo??'').')'); ?></option><?php endforeach; ?></select></div>
<div><label>Ano Lectivo</label><select name="ano_lectivo" onchange="this.form.submit()">
<?php for($y=(int)wp_date('Y');$y>=2020;$y--): ?><option value="<?php echo esc_attr($y); ?>" <?php selected($ano_sel,$y); ?>><?php echo esc_html((string)$y); ?></option><?php endfor; ?></select></div>
</div></form>

<?php if($turma_id && !empty($alunos) && !empty($discs)): ?>
<div class="pf-ctx">
    <div class="pf-ctx-m">
        <span class="pf-meta-pill"><?php echo $pf_icon('book'); ?> <?php echo esc_html($tl); ?></span>
        <span class="pf-meta-pill"><?php echo $pf_icon('users'); ?> <?php echo count($alunos); ?> alunos</span>
        <span class="pf-meta-pill"><?php echo $pf_icon('file'); ?> <?php echo count($discs); ?> disciplinas</span>
        <?php if($efc): ?><span class="pf-meta-pill" style="color:var(--color-danger-700);"><?php echo $pf_icon('shield'); ?> Fim de Ciclo<?php if($tipo_pauta==='fim_ciclo_af') echo ' (' . esc_html($_af_lbl . '/' . $_nf_lbl) . ')'; ?></span><?php endif; ?>
    </div>
    <div class="pf-actions">
        <button type="button" class="pf-btn pf-bp" data-sige-act="pfPrint" data-sige-noargs><?php echo $pf_icon('print'); ?> Imprimir / PDF</button>
        <button type="button" class="pf-btn pf-be" data-sige-act="pfXls" data-sige-noargs><?php echo $pf_icon('excel'); ?> Exportar Excel</button>
    </div>
</div>

<div class="pf-sc"><table class="pf-t" id="pf-t">
<thead>
<tr>
<th rowspan="2" style="min-width:30px">N&ordm;</th>
<th rowspan="2" style="min-width:150px;text-align:left;padding-left:8px">Nome do Aluno</th>
<th rowspan="2" style="width:28px">G</th>
<?php foreach($discs as $d):
    $is_nuc=strtolower($d->categoria??'')==='nuclear';
    $ncols=4; // MT1,MT2,MT3,MFD
    if($tipo_pauta==='fim_ciclo_af'&&$is_nuc) $ncols=6; // +AF+MF
    $badge=$is_nuc?' &#9733;':' &#9675;';
    $bg=$is_nuc?'background:var(--color-success-900)':'background:var(--color-slate-800)';
?>
<th colspan="<?php echo $ncols; ?>" style="<?php echo $bg; ?>"><?php echo esc_html($d->nome).$badge; ?></th>
<?php endforeach; ?>
<th rowspan="2" style="background:var(--color-info-900)">MG</th>
<th rowspan="2" style="background:var(--color-danger-700)">Neg.</th>
<th rowspan="2" style="background:var(--color-info-900)">Resultado</th>
</tr>
<tr>
<?php foreach($discs as $d):
    $is_nuc=strtolower($d->categoria??'')==='nuclear';
?>
<th>MT1</th><th>MT2</th><th>MT3</th><th style="background:var(--color-info-800)">MFD</th>
<?php if($tipo_pauta==='fim_ciclo_af'&&$is_nuc): ?><th style="background:var(--color-danger-700)"><?php echo $_af_lbl; ?></th><th style="background:var(--color-success-900)"><?php echo $_nf_lbl; ?></th><?php endif; ?>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php
$num=1;
foreach($alunos as $a):
    $aid=(int)$a->id;
    $mfds_all=[]; $negativas=0;
?>
<tr>
<td class="pf-n"><?php echo $num++; ?></td>
<td class="pf-nm"><?php echo esc_html($a->nome_completo); ?></td>
<td style="font-size:10px"><?php $gb=$pf_genero_bucket($a->genero??''); echo esc_html($gb!==''?$gb:'-'); ?></td>
<?php foreach($discs as $d):
    $did=(int)$d->id;
    $pd=$pf_data[$aid][$did]??['mts'=>[1=>null,2=>null,3=>null],'mfd'=>null,'af'=>null,'mf'=>null,'is_nuc'=>false];
    $is_nuc=$pd['is_nuc'];
    // Nota usada para situação: MF se existe, senão MFD; null = 0 para nucleares
    $nota_final=($pd['mf']!==null)?$pd['mf']:$pd['mfd'];
    if($is_nuc){$nf_val=($nota_final!==null)?(int)$nota_final:0;$mfds_all[]=$nf_val;if($nf_val<$nota_min)$negativas++;}
?>
<td class="td-mt"><?php echo $pd['mts'][1]??'&mdash;'; ?></td>
<td class="td-mt"><?php echo $pd['mts'][2]??'&mdash;'; ?></td>
<td class="td-mt"><?php echo $pd['mts'][3]??'&mdash;'; ?></td>
<td class="td-mfd"><?php echo $pd['mfd']!==null?(int)$pd['mfd']:'&mdash;'; ?></td>
<?php if($tipo_pauta==='fim_ciclo_af'&&$is_nuc): ?>
<td class="td-af"><?php echo $pd['af']!==null?number_format((float)$pd['af'],1,',',''):'&mdash;'; ?></td>
<td class="td-mf"><?php echo $pd['mf']!==null?(int)$pd['mf']:'&mdash;'; ?></td>
<?php endif; ?>
<?php endforeach; ?>
<?php
    // Situação baseada nos MFDs locais (fórmula /3, null=0 para nucleares)
    $mg=!empty($mfds_all)?(int)round(array_sum($mfds_all)/count($mfds_all),0):null;
    $sit='&mdash;';
    $mp=(int)($regra['max_negativas_progride']??0);$mxt=(int)($regra['max_negativas_transita']??2);
    if($mg!==null&&$mg<$nota_min)$sit='REPROVA';
    elseif($negativas>$mxt)$sit='REPROVA';
    elseif($negativas<=$mp)$sit=$efc?'TRANSITA':'PROGRIDE';
    elseif($negativas<=$mxt)$sit='TRANSITA';
    else $sit='REPROVA';
    $sit_cls=['PROGRIDE'=>'sit-progride','TRANSITA'=>'sit-transita','REPROVA'=>'sit-reprova'];
    $scls=$sit_cls[$sit]??'';
?>
<td class="td-mg"><?php echo $mg!==null?$mg:'&mdash;'; ?></td>
<td class="td-neg"><?php echo $negativas; ?></td>
<td class="td-sit"><span class="<?php echo $scls; ?>"><?php echo esc_html($sit); ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>

<!-- ESTATÍSTICAS -->
<div class="pf-ss"><table class="pf-st" id="pf-s">
<thead><tr><th colspan="2">Estat&iacute;stica &mdash; Pauta Final</th>
<?php foreach($discs as $d):
    $did=(int)$d->id;$cols=$st[$did]['cols'];
    foreach($cols as $ck):
        $lbl=['mt1'=>'MT1','mt2'=>'MT2','mt3'=>'MT3','mfd'=>'MFD','af'=>$_af_lbl,'mf'=>$_nf_lbl][$ck]??$ck;
        $bg='';if($ck==='mfd')$bg=' style="background:var(--color-info-800)"';if($ck==='af')$bg=' style="background:var(--color-danger-700)"';if($ck==='mf')$bg=' style="background:var(--color-success-900)"';
?><th<?php echo $bg; ?>><?php echo $lbl; ?></th>
<?php endforeach;endforeach; ?>
</tr>
<tr><th colspan="2"></th>
<?php foreach($discs as $d):$did=(int)$d->id;$nc=count($st[$did]['cols']); ?><th colspan="<?php echo $nc; ?>"><?php echo esc_html($d->sigla?:mb_substr($d->nome,0,8)); ?></th><?php endforeach; ?>
</tr></thead>
<tbody>
<?php
$sR=['ct'=>'N&ordm; avaliados','NS'=>'N. Satisf. (0-9)','S'=>'Satisfat. (10-13)','B'=>'Bom (14-16)','MB'=>'M. Bom (17-18)','E'=>'Excelente (19-20)','pc'=>'Percentagem','ma'=>'Nota M&eacute;dia'];
$gL=['H'=>'Homens','M'=>'Mulheres','HM'=>'Total (HM)'];
foreach(['H','M','HM'] as $gk):$fi=true;$rc=count($sR);$row_class=($gk==='H')?'pf-row-h':(($gk==='M')?'pf-row-m':'pf-hm pf-row-hm');
foreach($sR as $sk=>$sl): ?>
<tr class="<?php echo esc_attr($row_class); ?>">
<?php if($fi): ?><td class="pf-sg" rowspan="<?php echo $rc; ?>"><?php echo esc_html($gL[$gk]); ?></td><?php $fi=false;endif; ?>
<td class="pf-sl"><?php echo $sl; ?></td>
<?php foreach($discs as $d):$did=(int)$d->id;
foreach($st[$did]['cols'] as $ck):
    $gkey=($gk==='HM')?'all':$gk;$vs=$st[$did]['data'][$ck][$gkey]??[];
    if($sk==='ct')$vv=count($vs);
    elseif($sk==='ma'){$mv=$mav($vs);$vv=($mv!==null)?number_format($mv,1,',',''):'&mdash;';}
    elseif($sk==='pc'){$tg=$cnt_g[$gk]??0;$vv=$tg>0?round(count($vs)/$tg*100,0).'%':'0%';}
    else $vv=$cfx($vs,$sk);
    $sty='';if($sk==='NS'&&is_int($vv)&&$vv>0)$sty=' style="color:var(--color-danger-500);font-weight:700"';if($sk==='ma')$sty=' style="font-weight:700"';
?><td<?php echo $sty; ?>><?php echo $vv; ?></td>
<?php endforeach;endforeach; ?>
</tr>
<?php endforeach;endforeach; ?>
</tbody></table></div>

<div class="pf-legend">
<span>MT = ROUND((2&times;M&eacute;dACS + AT) / 3, 0)</span>
<span>MFD = ROUND((MT1+MT2+MT3) / 3, 0)</span>
<?php if($tipo_pauta==='fim_ciclo_af'): ?><span><?php echo esc_html($_nf_lbl); ?> = (MFD&times;<?php echo (int)$peso_mfd; ?>% + <?php echo esc_html($_af_lbl); ?>&times;<?php echo (int)$peso_exame; ?>%) / <?php echo (int)($peso_mfd+$peso_exame); ?>%</span><?php endif; ?>
<span>Disciplina Nuclear / Complementar</span>
</div>

<!-- Assinaturas -->
<div class="pf-signatures">
<div>Director(a) Pedagógico(a)</div>
<div>Director(a) da Escola</div>
<div>Data: ___/___/______</div>
</div>

<?php elseif($turma_id&&empty($alunos)): ?>
<div class="pf-em"><h3>Nenhum aluno matriculado</h3><p>Sem matr&iacute;culas activas nesta turma/ano.</p></div>
<?php else: ?>
<div class="pf-em"><h3>Seleccione uma turma</h3><p>Escolha uma turma para gerar a Pauta Final Oficial.</p></div>
<?php endif; ?>
<div class="pf-ft">SIGE SoftGenial &middot; <?php echo esc_html($_en); ?> &middot; Pauta Final Oficial</div>
</div>

<?php if($turma_id&&!empty($alunos)&&!empty($discs)): ?>
<?php echo function_exists('sige_cdn_script')?sige_cdn_script('exceljs'):''; ?>
<?php echo function_exists('sige_cdn_script')?sige_cdn_script('filesaver'):''; ?>
<script <?php echo sige_csp_script_attr(); ?>>
function pfPrint(){
var mt=document.getElementById('pf-t'),ms=document.getElementById('pf-s');if(!mt)return;
var h='<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Pauta Final</title><style>'
+'*{margin:0;padding:0;box-sizing:border-box}'
+'body{font-family:Arial,sans-serif;font-size:8pt;color:#000;background:#fff;padding:10mm}'
+'.hd{text-align:center;border-bottom:2pt solid #000;padding-bottom:6pt;margin-bottom:8pt}'
+'.hd h1{font-size:13pt}.hd h2{font-size:9pt;font-weight:normal}.hd p{font-size:8pt;color:#444;margin-top:2pt}'
+'table{width:100%;border-collapse:collapse;font-size:7pt;margin-bottom:8pt}'
+'th{background:#ddd;padding:3pt;border:.5pt solid #999;text-align:center;font-size:6.5pt}'
+'td{padding:2pt 3pt;border:.5pt solid #ccc;text-align:center}'
+'td.n{text-align:left;font-weight:600}.ng{color:#c00;font-weight:700}'
+'.pf-row-h td{background:#f8fbff}.pf-row-m td{background:#fff7fb}.pf-hm td{font-weight:700;background:#fff7ed}'
+'.assin{display:grid;grid-template-columns:1fr 1fr 1fr;gap:15mm;margin-top:15mm}'
+'.assin .a{text-align:center}.assin .a .ln{border-bottom:1pt solid #000;height:15pt;margin-bottom:2pt}'
+'.assin .a small{font-size:7pt;color:#555}'
+'@page{size:A4 landscape;margin:8mm}'
+'</style></head><body>'
+'<div class="hd"><h1>'+<?php echo wp_json_encode(mb_strtoupper($_en)); ?>+'</h1>'
+'<h2>Pauta Final \u2014 Ano Lectivo '+<?php echo wp_json_encode((string)$ano_sel); ?>+'</h2>'
+'<p>'+<?php echo wp_json_encode($tl); ?>+'</p></div>';
var ct=mt.cloneNode(true);ct.removeAttribute('id');ct.removeAttribute('class');h+=ct.outerHTML;
if(ms){var cs=ms.cloneNode(true);cs.removeAttribute('id');cs.removeAttribute('class');h+=cs.outerHTML;}
h+='<div class="assin"><div class="a"><div class="ln"></div><small>Director(a) Pedag\u00f3gico(a)</small></div><div class="a"><div class="ln"></div><small>Director(a) da Escola</small></div><div class="a"><div class="ln"></div><small>Data: ___/___/______</small></div></div>';
h+='</body></html>';
var w=window.open('','_blank','width=1200,height=800,scrollbars=yes');
if(!w){sigeUi.toast('O navegador bloqueou a janela de impressão. Permita popups para este site e tente novamente.', 'aviso');return;}
w.document.write(h);w.document.close();setTimeout(function(){w.focus();w.print();},400);
}
function pfXls(){
if(typeof ExcelJS==='undefined'){sigeUi.toast('A biblioteca de exportação ainda não carregou. Aguarde uns segundos e tente novamente.', 'erro');return;}
var wb=new ExcelJS.Workbook(),ws=wb.addWorksheet('Pauta Final');
ws.addRow([<?php echo wp_json_encode($_en); ?>]);
ws.addRow(['Pauta Final - Ano Lectivo '+<?php echo wp_json_encode((string)$ano_sel); ?>]);
ws.addRow([<?php echo wp_json_encode($tl); ?>]);ws.addRow([]);
var h1=['N\u00ba','Nome','G'],h2=['','',''];
<?php foreach($discs as $d):$is_nuc=strtolower($d->categoria??'')==='nuclear';$nc=4;if($tipo_pauta==='fim_ciclo_af'&&$is_nuc)$nc=6; ?>
h1.push(<?php echo wp_json_encode($d->nome); ?><?php for($i=1;$i<$nc;$i++)echo ",''" ?>);
h2.push('MT1','MT2','MT3','MFD'<?php if($tipo_pauta==='fim_ciclo_af'&&$is_nuc)echo ",'".$_af_lbl."','".$_nf_lbl."'"; ?>);
<?php endforeach; ?>
h1.push('MG','Neg','Resultado');h2.push('','','');
ws.addRow(h1);ws.addRow(h2);
<?php $n=1;foreach($alunos as $a):$aid=(int)$a->id;$r=[$n++,$a->nome_completo,($pf_genero_bucket($a->genero??'')?:'-')];
$mfds_xl=[];$neg_xl=0;
foreach($discs as $d){$did=(int)$d->id;$pd=$pf_data[$aid][$did]??['mts'=>[1=>null,2=>null,3=>null],'mfd'=>null,'af'=>null,'mf'=>null,'is_nuc'=>false];
$r[]=$pd['mts'][1];$r[]=$pd['mts'][2];$r[]=$pd['mts'][3];$r[]=$pd['mfd'];
if($tipo_pauta==='fim_ciclo_af'&&$pd['is_nuc']){$r[]=$pd['af'];$r[]=$pd['mf'];}
$nf=($pd['mf']!==null)?$pd['mf']:$pd['mfd'];
if($pd['is_nuc']){$nfv=($nf!==null)?(int)$nf:0;$mfds_xl[]=$nfv;if($nfv<$nota_min)$neg_xl++;}
}
$mg_xl=!empty($mfds_xl)?(int)round(array_sum($mfds_xl)/count($mfds_xl),0):null;
// Situação local (consistente com vista - fórmula /3, null=0)
$sit_xl='';
$mp_xl=(int)($regra['max_negativas_progride']??0);$mxt_xl=(int)($regra['max_negativas_transita']??2);
if($mg_xl!==null&&$mg_xl<$nota_min)$sit_xl='REPROVA';
elseif($neg_xl>$mxt_xl)$sit_xl='REPROVA';
elseif($neg_xl<=$mp_xl)$sit_xl=$efc?'TRANSITA':'PROGRIDE';
elseif($neg_xl<=$mxt_xl)$sit_xl='TRANSITA';
else $sit_xl='REPROVA';
$r[]=$mg_xl;$r[]=$neg_xl;$r[]=$sit_xl;
?>
ws.addRow(<?php echo wp_json_encode($r); ?>);
<?php endforeach; ?>
wb.xlsx.writeBuffer().then(function(b){saveAs(new Blob([b],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}),'PautaFinal_'+<?php echo wp_json_encode(sanitize_file_name($tl)); ?>+'_<?php echo $ano_sel; ?>.xlsx');});
}
</script>
<?php endif; ?>
