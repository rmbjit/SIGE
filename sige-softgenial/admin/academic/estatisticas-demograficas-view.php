<?php
/**
 * SIGE SoftGenial - M3: Estatísticas Demográficas
 * v2.0 - Abril 2026
 * Vista: ?page=sige-app&view=estatisticas_demo
 *
 * Funcionalidades:
 *   • KPIs com comparação inter-anual (delta vs ano anterior)
 *   • 4 Gráficos interactivos (Chart.js): Género, Classe×Género, Idade, Tendência
 *   • Distribuição por classe/turma e género
 *   • Análise de sobreidade (referência SNE moçambicano)
 *   • Taxa de ocupação de turmas (capacidade vs efectivo)
 *   • Pirâmide etária desagregada
 *   • Tendência de matrículas mensais
 *   • Distribuição geográfica (bairro)
 *   • Exportação: impressão (popup A4) + Excel profissional 7 folhas estilizado
 *
 * Segurança: ABSPATH, capability, escola_id, prepare(), esc_*()
 * @since 12.0
 */
if (!defined('ABSPATH')) exit;
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['academico.estatisticas_ver'],
    ['sige_director','sige_pedagogico','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;
global $wpdb;
$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$_ep = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$_en = $_ep->nome_escola ?? get_bloginfo('name');
$tA  = $wpdb->prefix . 'sige_alunos';
$tM  = $wpdb->prefix . 'sige_matriculas';
$tT  = $wpdb->prefix . 'sige_turmas';
$ano_act = function_exists('sige_get_ano_lectivo_atual') ? (int) sige_get_ano_lectivo_atual() : (int) wp_date('Y');
$ano_sel = isset($_GET['ano_lectivo']) ? (int) $_GET['ano_lectivo'] : $ano_act;
$ano_ant = $ano_sel - 1;
$filtro_classe = isset($_GET['classe']) ? sanitize_text_field($_GET['classe']) : '';
$filtro_turno  = isset($_GET['turno'])  ? sanitize_text_field($_GET['turno'])  : '';
$hoje = wp_date('Y-m-d');

// [12.10.86] Normalização única de género para estatísticas demográficas.
// Regra: género indefinido/desconhecido NÃO pode ser forçado para Masculino.
// Isto evita estatísticas falsas quando a ficha do aluno não tem género preenchido.
if (!function_exists('sige_demo_normalizar_genero')) {
    function sige_demo_normalizar_genero($raw): string {
        // v12.11.9.18 - Fonte única de verdade partilhada (academic-logic.php).
        // Convenção desta vista: 'M'=Masculino, 'F'=Feminino, 'I'=Indefinido.
        if (function_exists('sige_genero_bucket')) {
            $b = sige_genero_bucket($raw);
            return $b === 'U' ? 'I' : $b;
        }
        $g = strtoupper(trim((string) $raw));
        $g = str_replace(['Á','À','Â','Ã','É','Ê','Í','Ó','Ô','Õ','Ú','Ç'], ['A','A','A','A','E','E','I','O','O','O','U','C'], $g);
        if (in_array($g, ['M', 'MASCULINO', 'H', 'HOMEM', 'HOMENS', 'MACHO', 'MALE'], true)) {
            return 'M';
        }
        if (in_array($g, ['F', 'FEMININO', 'MULHER', 'MULHERES', 'FEMEA', 'FEMALE'], true)) {
            return 'F';
        }
        return 'I';
    }
}


// ─── Idade de referência SNE por classe ─────────────────────────
$sne_idade_ref = [
    'Pré-primário'=>5,'Pré-Primário'=>5,'Pré'=>5,'Creche'=>3,'Jardim'=>4,
    '1ª'=>6,'2ª'=>7,'3ª'=>8,'4ª'=>9,'5ª'=>10,'6ª'=>11,
    '7ª'=>12,'8ª'=>13,'9ª'=>14,'10ª'=>15,'11ª'=>16,'12ª'=>17,
    '1º Ano'=>6,'2º Ano'=>7,'3º Ano'=>8,'4º Ano'=>9,'5º Ano'=>10,'6º Ano'=>11,
    '2º/3º Ano'=>7,'4º/5º Ano'=>9,
];

// ─── Filtros disponíveis ─────────────────────────────────────────
$classes_disp = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT t.classe FROM {$tT} t WHERE t.escola_id=%d AND t.ano_lectivo=%d AND t.status_turma='activa' ORDER BY t.classe ASC", $eid, $ano_sel));
$turnos_disp = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT t.turno FROM {$tT} t WHERE t.escola_id=%d AND t.ano_lectivo=%d AND t.status_turma='activa' AND t.turno IS NOT NULL AND t.turno<>'' ORDER BY t.turno ASC", $eid, $ano_sel));

// ─── QUERY PRINCIPAL ─────────────────────────────────────────────
$where_extra = '';
$params = [$eid, $ano_sel];
if ($filtro_classe !== '') { $where_extra .= ' AND t.classe=%s'; $params[] = $filtro_classe; }
if ($filtro_turno !== '')  { $where_extra .= ' AND t.turno=%s';  $params[] = $filtro_turno; }

$alunos = $wpdb->get_results($wpdb->prepare(
    "SELECT a.id, a.nome_completo, a.genero, a.data_nascimento, a.bairro,
            t.id AS turma_id, t.classe, t.turno, t.nome AS turma_nome, t.capacidade, t.capacidade_max,
            m.data_matricula
     FROM {$tA} a
     INNER JOIN {$tM} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
     INNER JOIN {$tT} t ON t.id = m.turma_id AND t.escola_id = a.escola_id
     WHERE a.escola_id = %d AND m.ano_lectivo = %d
       AND (m.status_matricula IS NULL OR m.status_matricula = 'activa') {$where_extra}
     ORDER BY t.classe ASC, t.nome ASC, a.nome_completo ASC", ...$params));
$total = count($alunos);

// ─── ANO ANTERIOR (comparação) ───────────────────────────────────
$params_ant = [$eid, $ano_ant];
if ($filtro_classe !== '') { $params_ant[] = $filtro_classe; }
if ($filtro_turno !== '')  { $params_ant[] = $filtro_turno; }
$total_ant = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT a.id) FROM {$tA} a
     INNER JOIN {$tM} m ON m.aluno_id=a.id AND m.escola_id=a.escola_id
     INNER JOIN {$tT} t ON t.id=m.turma_id AND t.escola_id=a.escola_id
     WHERE a.escola_id=%d AND m.ano_lectivo=%d AND (m.status_matricula IS NULL OR m.status_matricula='activa') {$where_extra}", ...$params_ant));
$cnt_genero_ant = ['M' => 0, 'F' => 0, 'I' => 0];
if ($total_ant > 0) {
    $gant = $wpdb->get_results($wpdb->prepare(
        "SELECT a.genero AS g, COUNT(*) AS c
         FROM {$tA} a INNER JOIN {$tM} m ON m.aluno_id=a.id AND m.escola_id=a.escola_id
         INNER JOIN {$tT} t ON t.id=m.turma_id AND t.escola_id=a.escola_id
         WHERE a.escola_id=%d AND m.ano_lectivo=%d AND (m.status_matricula IS NULL OR m.status_matricula='activa') {$where_extra} GROUP BY a.genero", ...$params_ant));
    foreach ($gant as $r) { $gg = sige_demo_normalizar_genero($r->g ?? ''); $cnt_genero_ant[$gg] = (int) (($cnt_genero_ant[$gg] ?? 0) + (int) $r->c); }
}

// ─── CÁLCULOS ────────────────────────────────────────────────────
$cnt_genero = ['M' => 0, 'F' => 0, 'I' => 0];
$cnt_classe = []; $cnt_turno = []; $cnt_bairro = []; $cnt_idade = [];
$cnt_mes_mat = []; $idades_all = [];
$turmas_ocup = []; $sobreidade = [];

foreach ($alunos as $a) {
    $g = sige_demo_normalizar_genero($a->genero ?? '');
    $cnt_genero[$g]++;
    $cl = $a->classe ?: 'N/D';

    // Classe
    if (!isset($cnt_classe[$cl])) $cnt_classe[$cl] = ['M'=>0,'F'=>0,'I'=>0,'total'=>0];
    $cnt_classe[$cl][$g]++; $cnt_classe[$cl]['total']++;

    // Turno
    $tn = $a->turno ?: 'N/D';
    if (!isset($cnt_turno[$tn])) $cnt_turno[$tn] = ['M'=>0,'F'=>0,'I'=>0,'total'=>0];
    $cnt_turno[$tn][$g]++; $cnt_turno[$tn]['total']++;

    // Idade + Sobreidade
    $idade = null;
    if ($a->data_nascimento && $a->data_nascimento !== '0000-00-00') {
        $dn = new DateTime($a->data_nascimento); $hj = new DateTime($hoje);
        $idade = (int) $hj->diff($dn)->y;
        if (!isset($cnt_idade[$idade])) $cnt_idade[$idade] = ['M'=>0,'F'=>0,'I'=>0];
        $cnt_idade[$idade][$g]++; $idades_all[] = $idade;
    }
    if (!isset($sobreidade[$cl])) $sobreidade[$cl] = ['total'=>0,'sobre'=>0,'sobre_M'=>0,'sobre_F'=>0,'sobre_I'=>0,'ref'=>0];
    $sobreidade[$cl]['total']++;
    $ref = $sne_idade_ref[$cl] ?? null;
    if ($ref === null) { $cn = (int) preg_replace('/\D+/', '', $cl); if ($cn >= 1 && $cn <= 12) $ref = $cn + 5; }
    $sobreidade[$cl]['ref'] = $ref ?: 0;
    if ($idade !== null && $ref !== null && $idade > ($ref + 1)) {
        $sobreidade[$cl]['sobre']++; if (isset($sobreidade[$cl]['sobre_'.$g])) { $sobreidade[$cl]['sobre_'.$g]++; }
    }

    // Bairro
    $br = trim($a->bairro ?? ''); if ($br === '') $br = 'N/D';
    $br = mb_convert_case($br, MB_CASE_TITLE, 'UTF-8');
    if (!isset($cnt_bairro[$br])) $cnt_bairro[$br] = 0; $cnt_bairro[$br]++;

    // Mês matrícula
    if ($a->data_matricula) {
        $mm = substr($a->data_matricula, 0, 7);
        if (!isset($cnt_mes_mat[$mm])) $cnt_mes_mat[$mm] = 0; $cnt_mes_mat[$mm]++;
    }

    // Ocupação turmas
    $tid = (int) $a->turma_id;
    if (!isset($turmas_ocup[$tid])) {
        $cap = max((int)($a->capacidade?:0), (int)($a->capacidade_max?:0));
        $turmas_ocup[$tid] = ['classe'=>$cl,'nome'=>($a->turma_nome?:''),'turno'=>$tn,'cap'=>$cap,'total'=>0,'M'=>0,'F'=>0];
    }
    $turmas_ocup[$tid]['total']++; $turmas_ocup[$tid][$g]++;
}

// Ordenações
uksort($cnt_classe, function($a,$b){ $na=(int)preg_replace('/\D+/','',$a); $nb=(int)preg_replace('/\D+/','',$b); return $na!==$nb?$na-$nb:strcmp($a,$b); });
uksort($sobreidade, function($a,$b){ $na=(int)preg_replace('/\D+/','',$a); $nb=(int)preg_replace('/\D+/','',$b); return $na!==$nb?$na-$nb:strcmp($a,$b); });
ksort($cnt_idade); ksort($cnt_mes_mat); arsort($cnt_bairro);
usort($turmas_ocup, function($a,$b){ $na=(int)preg_replace('/\D+/','',$a['classe']); $nb=(int)preg_replace('/\D+/','',$b['classe']); return $na!==$nb?$na-$nb:strcmp($a['nome'],$b['nome']); });

// Idade média/mediana
$idade_media = !empty($idades_all) ? round(array_sum($idades_all)/count($idades_all),1) : 0;
$idade_mediana = 0;
if (!empty($idades_all)) { sort($idades_all); $mid=(int)floor(count($idades_all)/2); $idade_mediana = count($idades_all)%2===0 ? ($idades_all[$mid-1]+$idades_all[$mid])/2 : $idades_all[$mid]; }

$anos_disp = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT ano_lectivo FROM {$tM} WHERE escola_id=%d ORDER BY ano_lectivo DESC", $eid));
if (empty($anos_disp)) $anos_disp = [$ano_act];

$sobre_total=0; $sobre_total_m=0; $sobre_total_f=0;
foreach ($sobreidade as $s) { $sobre_total+=$s['sobre']; $sobre_total_m+=$s['sobre_M']; $sobre_total_f+=$s['sobre_F']; }
$sobre_pct = $total>0 ? round($sobre_total/$total*100,1) : 0;
$cap_total = 0; foreach ($turmas_ocup as $to) $cap_total += $to['cap'];

$delta_total = $total - $total_ant;
$delta_pct = $total_ant>0 ? round(($delta_total/$total_ant)*100,1) : 0;
$delta_m = $cnt_genero['M'] - $cnt_genero_ant['M'];
$delta_f = $cnt_genero['F'] - $cnt_genero_ant['F'];

$meses_pt = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];

// JSON para Chart.js
$chart_classe_labels=[]; $chart_classe_m=[]; $chart_classe_f=[]; $chart_classe_i=[];
foreach ($cnt_classe as $cl=>$v) { $chart_classe_labels[]=$cl; $chart_classe_m[]=$v['M']; $chart_classe_f[]=$v['F']; $chart_classe_i[]=$v['I'] ?? 0; }
$chart_idade_labels=[]; $chart_idade_m=[]; $chart_idade_f=[]; $chart_idade_i=[];
foreach ($cnt_idade as $id=>$v) { $chart_idade_labels[]=$id.' anos'; $chart_idade_m[]=$v['M']; $chart_idade_f[]=$v['F']; $chart_idade_i[]=$v['I'] ?? 0; }
$chart_trend_labels=[]; $chart_trend_vals=[]; $chart_trend_acum=[]; $acum_t=0;
foreach ($cnt_mes_mat as $mm=>$n) { $parts=explode('-',$mm); $chart_trend_labels[]=($meses_pt[(int)$parts[1]]??$parts[1]); $chart_trend_vals[]=$n; $acum_t+=$n; $chart_trend_acum[]=$acum_t; }


$ed_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'chart' => '<path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'female' => '<circle cx="12" cy="8" r="5"/><path d="M12 13v8"/><path d="M9 18h6"/>',
        'male' => '<circle cx="10" cy="14" r="5"/><path d="M14 10l6-6"/><path d="M15 4h5v5"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'school' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/><path d="M9 10h.01"/><path d="M15 10h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
        'print' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
        'excel' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 6-6"/><path d="m9 9 6 6"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
        'map' => '<path d="M9 18l-6 3V6l6-3 6 3 6-3v15l-6 3-6-3Z"/><path d="M9 3v15"/><path d="M15 6v15"/>',
        'trend' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
    ];
    $path = $map[$name] ?? $map['chart'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

// ═══════════ UI ═══════════
?>
<style id="sige-estatisticas-demo-produto-pro-v121085">
/* SIGE SoftGenial v12.10.85 - Estatísticas Demográficas: Compliance Visual Integral
   Escopo visual apenas: não altera cálculos estatísticos, queries, exportações,
   gráficos, ACTA, DEC, pautas, notas ou regras académicas. */
.edw{
    --ed-blue:var(--color-info-700);
    --ed-blue-dark:var(--color-info-800);
    --ed-purple:var(--color-brand-500);
    --ed-purple-soft:var(--color-brand-50);
    --ed-ink:var(--color-black);
    --ed-muted:var(--color-slate-700);
    --ed-line:var(--color-ink-100);
    --ed-green:var(--color-success-500);
    --ed-red:var(--color-danger-500);
    --ed-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--ed-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.edw *{box-sizing:border-box}
.edw svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal / Dashboard V2 MJS-grade */
.edh{
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
    color:var(--ed-ink);
}
.edh:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.edh-main,.edh-panel{position:relative;z-index:1}
.ed-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    color:var(--ed-blue);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.edh h2{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
    font-family:inherit;
}
.edh p{
    max-width:650px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.edm{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.edp{
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
.edp svg{color:var(--ed-purple)}
.edh-panel{
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
.edh-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.edh-panel>*{position:relative;z-index:1}
.edh-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--ed-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.edh-panel-value{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.edh-panel-text{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* filtros */
.edf{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:18px;
    display:grid;
    grid-template-columns:repeat(3,minmax(180px,1fr));
    gap:var(--space-3);
    align-items:end;
    box-shadow:var(--shadow-md);
    margin:0;
}
.edf label{font-size:var(--fs-xs);font-weight:700;color:var(--color-slate-600);display:block;margin-bottom:7px;text-transform:uppercase;letter-spacing:.07em}
.edf select{
    width:100%;
    min-width:0;
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
.edf select:focus{border-color:rgba(90,63,214,.55);box-shadow:0 1px 2px rgba(15,23,42,.04)}

/* acções */
.ed-actions{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:var(--space-3);
    padding:16px 18px;
    border-radius:var(--radius-xl);
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    color:var(--color-ink-500);
    margin:0;
    box-shadow:var(--shadow-md);
}
.ed-am{font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-700);display:flex;align-items:center;gap:var(--space-2);flex-wrap:wrap}
.ed-actions-buttons{display:flex;gap:var(--space-2);flex-wrap:wrap}
.ed-btn{
    min-height:42px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    padding:0 var(--space-4);
    border-radius:var(--radius-md);
    font-size:12.5px;
    font-weight:700;
    cursor:pointer;
    border:1px solid transparent;
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease;
    font-family:inherit;
}
.ed-btn:hover{transform:translateY(-1px)}
.ed-bp{background:linear-gradient(135deg,var(--ed-blue),var(--ed-blue-dark));color:var(--color-white);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.ed-bp:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}
.ed-be{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.ed-be:hover{background:var(--color-success-100)}

/* KPIs */
.ed-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:var(--space-4);margin:0}
.ed-k{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    column-gap:14px;
    row-gap:var(--space-1);
    min-height:112px;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:18px 20px;
    box-shadow:var(--shadow-md);
}
.ed-k:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50))}
.ed-ki{
    grid-row:1 / span 4;
    width:52px;
    height:52px;
    border-radius:var(--radius-lg);
    display:flex;
    align-items:center;
    justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));
    color:var(--kpi-color,var(--color-brand-500));
    position:relative;
    z-index:1;
}
.ed-ki svg{width:24px;height:24px}
.ed-k > div{position:relative;z-index:1}
.ed-kl{font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--color-slate-600);margin:0}
.ed-kv{font-size:27px;font-weight:700;color:var(--color-black);line-height:1.06;letter-spacing:-.03em}
.ed-ks{font-size:12px;color:var(--color-slate-500);margin:0;font-weight:600}
.ed-kd{display:inline-flex;align-items:center;gap:var(--space-1);font-size:var(--fs-xs);font-weight:700;padding:var(--space-1) var(--space-2);border-radius:var(--radius-pill);margin-top:5px}
.ed-kd.up{background:var(--color-success-100);color:var(--color-success-500)}.ed-kd.dn{background:var(--color-danger-50);color:var(--color-danger-600)}.ed-kd.eq{background:var(--color-ink-50);color:var(--color-slate-500)}
.ed-k.k-total{--kpi-color:var(--color-info-700);--kpi-soft:var(--color-info-50)}
.ed-k.k-m{--kpi-color:var(--color-info-500);--kpi-soft:var(--color-info-50)}
.ed-k.k-f{--kpi-color:var(--color-danger-400);--kpi-soft:var(--color-danger-50)}
.ed-k.k-age{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50)}
.ed-k.k-cls{--kpi-color:var(--color-success-600);--kpi-soft:var(--color-success-50)}
.ed-k.k-sobre{--kpi-color:var(--color-danger-500);--kpi-soft:var(--color-danger-50)}

/* secções e gráficos */
.ed-sec{margin:0}
.ed-st{
    display:flex;
    align-items:center;
    gap:10px;
    font-size:17px;
    font-weight:700;
    color:var(--color-ink-500);
    margin:0 0 var(--space-3);
    letter-spacing:-.025em;
}
.ed-st .ed-sec-icon{
    width:38px;
    height:38px;
    border-radius:var(--radius-md);
    background:var(--color-brand-50);
    color:var(--ed-purple);
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 auto;
}
.ed-note{font-size:var(--fs-sm);color:var(--color-slate-500);margin:-4px 0 12px 48px;font-weight:600;line-height:1.55}
.ed-charts{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-4);margin:0}
.ed-chart-card{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:18px;
    box-shadow:var(--shadow-md);
}
.ed-chart-card h4{font-size:var(--fs-base);font-weight:700;color:var(--color-ink-500);margin:0 0 var(--space-3);text-align:left;letter-spacing:-.02em}
.ed-chart-card canvas{max-height:260px}
.ed-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}

/* tabelas */
.ed-tw{
    overflow:auto;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    box-shadow:var(--shadow-md);
}
table.ed-tb{border-collapse:separate;border-spacing:0;width:100%;min-width:780px;font-size:12px;background:var(--color-white)}
table.ed-tb th,table.ed-tb td{border-right:1px solid var(--color-slate-100);border-bottom:1px solid var(--color-slate-100);padding:9px 11px;text-align:center;white-space:nowrap}
table.ed-tb th:last-child,table.ed-tb td:last-child{border-right:none}
table.ed-tb thead th{background:var(--color-info-700);color:var(--color-white);font-size:var(--fs-xs);font-weight:700;padding:11px 11px;text-transform:uppercase;letter-spacing:.04em}
table.ed-tb tbody tr:hover td{background:var(--color-white)}
table.ed-tb tbody tr:nth-child(even) td{background:var(--color-white)}
table.ed-tb td.tl{text-align:left;font-weight:700;color:var(--color-info-900);padding-left:14px}
table.ed-tb tfoot td{background:var(--color-slate-50);font-weight:700;color:var(--color-ink-500);border-top:2px solid var(--color-info-100)}
.ed-bar{display:flex;height:18px;border-radius:var(--radius-pill);overflow:hidden;background:var(--color-ink-100);min-width:80px}
.ed-bar-m{background:linear-gradient(90deg,var(--color-info-500),var(--color-info-400));height:100%}
.ed-bar-f{background:linear-gradient(90deg,var(--color-danger-400),var(--color-danger-300));height:100%}
.ed-bar-g{height:100%;border-radius:999px}

.ed-empty{
    padding:42px 20px;
    text-align:center;
    background:var(--color-white);
    border-radius:var(--radius-xl);
    border:1px solid rgba(28,32,54,.08);
    box-shadow:var(--shadow-md);
}
.ed-empty h3{color:var(--color-black);margin:0 0 var(--space-2);font-size:var(--fs-lg);font-weight:700;letter-spacing:-.03em}
.ed-empty p{color:var(--color-slate-500);margin:0;font-size:var(--fs-sm);line-height:1.55}
.ed-ft{margin-top:0;text-align:center;font-size:var(--fs-xs);color:var(--color-slate-400);padding:8px 0}
.ed-legend{margin-top:0;font-size:12px;color:var(--color-slate-600);display:flex;gap:10px;flex-wrap:wrap;padding:0 4px}
.ed-legend span{display:inline-flex;align-items:center;padding:8px 11px;border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:0 2px 8px rgba(15,23,42,.06)}

@media(max-width:1100px){
    .edh{grid-template-columns:1fr;padding:26px 24px}
    .ed-charts,.ed-grid2{grid-template-columns:1fr}
}
@media(max-width:768px){
    .edh h2{font-size:24px}
    .edf{grid-template-columns:1fr}
    .ed-kpi{grid-template-columns:1fr}
    .ed-actions{align-items:stretch}
    .ed-actions-buttons,.ed-btn{width:100%}
    .ed-actions-buttons{display:grid;grid-template-columns:1fr}
    .ed-note{margin-left:0}
}

/* v12.10.86 - gráficos e tabelas em compliance visual e responsivo */
.ed-chart-card{
    min-height:340px;
    display:flex;
    flex-direction:column;
}
.ed-chart-card h4{
    display:flex;
    align-items:center;
    gap:10px;
}
.ed-chart-card h4:before{
    content:"";
    width:34px;
    height:34px;
    border-radius:var(--radius-md);
    background:var(--color-brand-50);
    box-shadow:inset 0 0 0 1px rgba(90,63,214,.06);
    flex:0 0 auto;
}
.ed-chart-card canvas{
    flex:1 1 auto;
    width:100%!important;
}
.ed-chart-card .chartjs-render-monitor{
    border-radius:var(--radius-lg);
}
.ed-tw{
    max-width:100%;
    -webkit-overflow-scrolling:touch;
}
.ed-tw:focus-within{
    box-shadow:var(--shadow-md);
}
.ed-responsive-note{
    display:none;
    font-size:var(--fs-xs);
    color:var(--color-slate-500);
    margin:6px 0 0;
    font-weight:600;
}
.ed-k.k-unknown{--kpi-color:var(--color-slate-500);--kpi-soft:var(--color-ink-50)}
.ed-gender-warning{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:12px 14px;
    border-radius:var(--radius-lg);
    background:var(--color-warning-50);
    border:1px solid var(--color-warning-200);
    color:var(--color-warning-800);
    font-size:12.5px;
    font-weight:600;
    line-height:1.5;
}
.ed-gender-warning svg{color:var(--color-warning-500);flex:0 0 auto;margin-top:1px}
@media(max-width:920px){
    .ed-chart-card{min-height:300px}
    table.ed-tb{min-width:640px}
    .ed-responsive-note{display:block}
}
@media(max-width:640px){
    .ed-chart-card{min-height:270px;padding:14px}
    .ed-chart-card canvas{max-height:220px}
    table.ed-tb{min-width:560px;font-size:11px}
    table.ed-tb th,table.ed-tb td{padding:8px 9px}
}


/* v12.10.87 - ajuste fino da secção Por Idade / Por Turno */
.ed-grid2--tables{
    grid-template-columns:minmax(0,1.28fr) minmax(0,.92fr);
    align-items:start;
}
.ed-grid2--tables > .ed-sec{
    min-width:0;
}
.ed-sec--idade .ed-tw,
.ed-sec--turno .ed-tw{
    max-width:100%;
    overflow-x:auto;
    overflow-y:hidden;
}
.ed-sec--idade table.ed-tb{
    min-width:0;
    width:100%;
}
.ed-sec--idade table.ed-tb th,
.ed-sec--idade table.ed-tb td{
    padding:8px 10px;
}
.ed-sec--idade .ed-responsive-note{
    display:block;
}
@media(max-width:1480px){
    .ed-grid2--tables{
        grid-template-columns:1fr;
    }
}
@media(max-width:920px){
    .ed-sec--idade table.ed-tb{
        min-width:560px;
    }
}

</style>

<div class="edw">
<div class="edh">
    <div class="edh-main">
        <div class="ed-kicker"><?php echo $ed_icon('chart'); ?><span>Gestão Escolar</span></div>
        <h2>Estatísticas Demográficas</h2>
        <p>Analise género, sobreidade SNE, ocupação das turmas, faixa etária, tendência de matrículas e distribuição geográfica da escola.</p>
        <div class="edm">
            <span class="edp"><?php echo $ed_icon('calendar'); ?> Ano Lectivo <?php echo esc_html((string)$ano_sel); ?></span>
            <span class="edp"><?php echo $ed_icon('users'); ?> <?php echo $total; ?> aluno(s)</span>
            <?php if($filtro_classe): ?><span class="edp"><?php echo $ed_icon('school'); ?> Classe: <?php echo esc_html($filtro_classe); ?></span><?php endif; ?>
            <?php if($filtro_turno): ?><span class="edp"><?php echo $ed_icon('clock'); ?> Turno: <?php echo esc_html($filtro_turno); ?></span><?php endif; ?>
        </div>
    </div>
    <aside class="edh-panel" aria-label="Estado das estatísticas">
        <div class="edh-panel-label"><?php echo $ed_icon('chart'); ?><span>Estado</span></div>
        <strong class="edh-panel-value"><?php echo $total > 0 ? esc_html((string)$total) : 'Sem dados'; ?></strong>
        <span class="edh-panel-text"><?php echo $total > 0 ? 'Aluno(s) activos considerados nos indicadores demográficos do ano seleccionado.' : 'Não há matrículas activas para os filtros seleccionados.'; ?></span>
    </aside>
</div>

<?php $ps=isset($_GET['page'])?sanitize_text_field($_GET['page']):''; ?>
<form method="GET" action="<?php echo esc_url(admin_url('admin.php')); ?>">
<input type="hidden" name="page" value="<?php echo esc_attr($ps); ?>"><input type="hidden" name="view" value="estatisticas_demo">
<div class="edf">
<div><label>Ano Lectivo</label><select name="ano_lectivo" onchange="this.form.submit()"><?php foreach($anos_disp as $y): ?><option value="<?php echo esc_attr($y); ?>" <?php selected($ano_sel,(int)$y); ?>><?php echo esc_html((string)$y); ?></option><?php endforeach; ?></select></div>
<div><label>Classe</label><select name="classe" onchange="this.form.submit()"><option value="">-- Todas --</option><?php foreach($classes_disp as $c): ?><option value="<?php echo esc_attr($c); ?>" <?php selected($filtro_classe,$c); ?>><?php echo esc_html($c); ?></option><?php endforeach; ?></select></div>
<div><label>Turno</label><select name="turno" onchange="this.form.submit()"><option value="">-- Todos --</option><?php foreach($turnos_disp as $tn): ?><option value="<?php echo esc_attr($tn); ?>" <?php selected($filtro_turno,$tn); ?>><?php echo esc_html($tn); ?></option><?php endforeach; ?></select></div>
</div></form>

<?php if($total > 0): ?>

<div class="ed-actions">
    <div class="ed-am"><?php echo $ed_icon('filter'); ?> <?php echo $total; ?> aluno(s) - <?php echo esc_html((string)$ano_sel); ?><?php if($filtro_classe): ?> - <?php echo esc_html($filtro_classe); ?><?php endif; ?></div>
    <div class="ed-actions-buttons">
        <button type="button" class="ed-btn ed-bp" data-sige-act="edPrint" data-sige-noargs><?php echo $ed_icon('print'); ?> Imprimir</button>
        <button type="button" class="ed-btn ed-be" data-sige-act="edXls" data-sige-noargs><?php echo $ed_icon('excel'); ?> Excel</button>
    </div>
</div>

<!-- KPIs -->
<div class="ed-kpi">
<div class="ed-k k-total"><span class="ed-ki"><?php echo $ed_icon('users'); ?></span><div class="ed-kl">Total Alunos</div><div class="ed-kv"><?php echo $total; ?></div><div class="ed-ks">Matr&iacute;culas activas</div><?php if($total_ant>0): ?><div class="ed-kd <?php echo $delta_total>0?'up':($delta_total<0?'dn':'eq'); ?>"><?php echo $delta_total>0?'&#9650;':($delta_total<0?'&#9660;':'&#9644;'); ?> <?php echo ($delta_total>=0?'+':'').$delta_total; ?> (<?php echo ($delta_pct>=0?'+':'').$delta_pct; ?>%) vs <?php echo $ano_ant; ?></div><?php endif; ?></div>
<div class="ed-k k-m"><span class="ed-ki"><?php echo $ed_icon('male'); ?></span><div class="ed-kl">Masculino</div><div class="ed-kv"><?php echo $cnt_genero['M']; ?></div><div class="ed-ks"><?php echo $total>0?round($cnt_genero['M']/$total*100,1):0; ?>% do total</div><?php if($total_ant>0): ?><div class="ed-kd <?php echo $delta_m>0?'up':($delta_m<0?'dn':'eq'); ?>"><?php echo ($delta_m>=0?'+':'').$delta_m; ?> vs <?php echo $ano_ant; ?></div><?php endif; ?></div>
<div class="ed-k k-f"><span class="ed-ki"><?php echo $ed_icon('female'); ?></span><div class="ed-kl">Feminino</div><div class="ed-kv"><?php echo $cnt_genero['F']; ?></div><div class="ed-ks"><?php echo $total>0?round($cnt_genero['F']/$total*100,1):0; ?>% do total</div><?php if($total_ant>0): ?><div class="ed-kd <?php echo $delta_f>0?'up':($delta_f<0?'dn':'eq'); ?>"><?php echo ($delta_f>=0?'+':'').$delta_f; ?> vs <?php echo $ano_ant; ?></div><?php endif; ?></div>
<div class="ed-k k-age"><span class="ed-ki"><?php echo $ed_icon('calendar'); ?></span><div class="ed-kl">Idade M&eacute;dia</div><div class="ed-kv"><?php echo number_format($idade_media,1,',',''); ?></div><div class="ed-ks">Mediana: <?php echo number_format($idade_mediana,1,',',''); ?> anos</div></div>
<div class="ed-k k-sobre"><span class="ed-ki"><?php echo $ed_icon('alert'); ?></span><div class="ed-kl">Sobreidade</div><div class="ed-kv"><?php echo $sobre_total; ?></div><div class="ed-ks"><?php echo $sobre_pct; ?>% acima ref. SNE</div></div>
<div class="ed-k k-cls"><span class="ed-ki"><?php echo $ed_icon('school'); ?></span><div class="ed-kl">Turmas Activas</div><div class="ed-kv"><?php echo count($turmas_ocup); ?></div><div class="ed-ks"><?php echo count($cnt_classe); ?> classe(s) &middot; <?php echo count($cnt_turno); ?> turno(s)</div></div>
</div>

<!-- GRÁFICOS -->
<div class="ed-charts">
<div class="ed-chart-card"><h4>Distribui&ccedil;&atilde;o por G&eacute;nero</h4><canvas id="chartGenero"></canvas></div>
<div class="ed-chart-card"><h4>Alunos por Classe e G&eacute;nero</h4><canvas id="chartClasse"></canvas></div>
<div class="ed-chart-card"><h4>Distribui&ccedil;&atilde;o por Idade</h4><canvas id="chartIdade"></canvas></div>
<div class="ed-chart-card"><h4>Tend&ecirc;ncia de Matr&iacute;culas (<?php echo esc_html((string)$ano_sel); ?>)</h4><canvas id="chartTrend"></canvas></div>
</div>

<!-- POR CLASSE -->
<div class="ed-sec"><h3 class="ed-st"><span class="ed-sec-icon"><?php echo $ed_icon('grid'); ?></span>Distribui&ccedil;&atilde;o por Classe e G&eacute;nero</h3>
<div class="ed-tw"><table class="ed-tb" id="ed-t-classe"><thead><tr><th style="text-align:left;padding-left:14px;">Classe</th><th>M</th><th>F</th><th>Por confirmar</th><th>Total</th><th>% M</th><th>% F</th><th style="min-width:120px">Propor&ccedil;&atilde;o</th></tr></thead><tbody>
<?php foreach($cnt_classe as $cl=>$v): $pM=$v['total']>0?round($v['M']/$v['total']*100,0):0; $pF=100-$pM; ?>
<tr><td class="tl"><?php echo esc_html($cl); ?></td><td><?php echo $v['M']; ?></td><td><?php echo $v['F']; ?></td><td><?php echo $v['I'] ?? 0; ?></td><td class="sige-u-fw7"><?php echo $v['total']; ?></td><td><?php echo $pM; ?>%</td><td><?php echo $pF; ?>%</td><td><div class="ed-bar"><div class="ed-bar-m" style="width:<?php echo $pM; ?>%"></div><div class="ed-bar-f" style="width:<?php echo $pF; ?>%"></div></div></td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr><td style="text-align:left;padding-left:14px;">TOTAL</td><td><?php echo $cnt_genero['M']; ?></td><td><?php echo $cnt_genero['F']; ?></td><td><?php echo $cnt_genero['I'] ?? 0; ?></td><td><?php echo $total; ?></td><td><?php echo $total>0?round($cnt_genero['M']/$total*100,0):0; ?>%</td><td><?php echo $total>0?round($cnt_genero['F']/$total*100,0):0; ?>%</td><td></td></tr></tfoot></table></div></div>

<!-- SOBREIDADE -->
<div class="ed-sec"><h3 class="ed-st"><span class="ed-sec-icon"><?php echo $ed_icon('alert'); ?></span>An&aacute;lise de Sobreidade (Refer&ecirc;ncia SNE)</h3>
<p class="ed-note">Alunos com mais de 1 ano acima da idade de refer&ecirc;ncia do SNE para a respectiva classe.</p>
<div class="ed-tw"><table class="ed-tb" id="ed-t-sobre"><thead><tr><th style="text-align:left;padding-left:14px;">Classe</th><th>Idade Ref.</th><th>Total</th><th>Sobreidade</th><th>M</th><th>F</th><th>Por confirmar</th><th>%</th><th style="min-width:100px">Indicador</th></tr></thead><tbody>
<?php foreach($sobreidade as $cl=>$s): $pct=$s['total']>0?round($s['sobre']/$s['total']*100,1):0; ?>
<tr><td class="tl"><?php echo esc_html($cl); ?></td><td><?php echo $s['ref']>0?$s['ref'].' anos':'&mdash;'; ?></td><td><?php echo $s['total']; ?></td><td class="sige-u-fw7"><?php echo $s['sobre']; ?></td><td><?php echo $s['sobre_M']; ?></td><td><?php echo $s['sobre_F']; ?></td><td><?php echo $s['sobre_I'] ?? 0; ?></td><td><?php echo $pct; ?>%</td>
<td><div class="ed-bar" style="background:var(--color-success-100)"><div class="ed-bar-g" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $pct>20?'var(--color-danger-500)':($pct>10?'var(--color-warning-500)':'var(--color-success-600)'); ?>"></div></div></td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr><td style="text-align:left;padding-left:14px;">TOTAL</td><td></td><td><?php echo $total; ?></td><td><?php echo $sobre_total; ?></td><td><?php echo $sobre_total_m; ?></td><td><?php echo $sobre_total_f; ?></td><td><?php echo $sobre_total_i ?? 0; ?></td><td><?php echo $sobre_pct; ?>%</td><td></td></tr></tfoot></table></div></div>

<!-- OCUPAÇÃO -->
<div class="ed-sec"><h3 class="ed-st"><span class="ed-sec-icon"><?php echo $ed_icon('school'); ?></span>Taxa de Ocupa&ccedil;&atilde;o das Turmas</h3>
<div class="ed-tw"><table class="ed-tb" id="ed-t-ocup"><thead><tr><th style="text-align:left;padding-left:14px;">Turma</th><th>Classe</th><th>Turno</th><th>M</th><th>F</th><th>Por confirmar</th><th>Efectivo</th><th>Capacidade</th><th>Ocupa&ccedil;&atilde;o</th><th style="min-width:110px">Indicador</th></tr></thead><tbody>
<?php foreach($turmas_ocup as $to): $pct=$to['cap']>0?round($to['total']/$to['cap']*100,0):0; $cor=$pct>95?'var(--color-danger-500)':($pct>80?'var(--color-warning-500)':'var(--color-success-600)'); ?>
<tr><td class="tl"><?php echo esc_html($to['classe'].' - '.$to['nome']); ?></td><td><?php echo esc_html($to['classe']); ?></td><td><?php echo esc_html($to['turno']); ?></td><td><?php echo $to['M']; ?></td><td><?php echo $to['F']; ?></td><td><?php echo $to['I'] ?? 0; ?></td><td class="sige-u-fw7"><?php echo $to['total']; ?></td><td><?php echo $to['cap']>0?$to['cap']:'&mdash;'; ?></td><td style="font-weight:700;color:<?php echo $cor; ?>"><?php echo $to['cap']>0?$pct.'%':'N/D'; ?></td>
<td><?php if($to['cap']>0): ?><div class="ed-bar" style="background:var(--color-ink-100)"><div class="ed-bar-g" style="width:<?php echo min($pct,100); ?>%;background:<?php echo $cor; ?>"></div></div><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr><td style="text-align:left;padding-left:14px;">TOTAL</td><td></td><td></td><td><?php echo $cnt_genero['M']; ?></td><td><?php echo $cnt_genero['F']; ?></td><td><?php echo $cnt_genero['I'] ?? 0; ?></td><td><?php echo $total; ?></td><td><?php echo $cap_total>0?$cap_total:'&mdash;'; ?></td><td class="sige-u-fw7"><?php echo $cap_total>0?round($total/$cap_total*100,0).'%':'N/D'; ?></td><td></td></tr></tfoot></table></div></div>

<div class="ed-grid2 ed-grid2--tables">
<!-- TURNO -->
<div class="ed-sec ed-sec--turno"><h3 class="ed-st"><span class="ed-sec-icon"><?php echo $ed_icon('clock'); ?></span>Por Turno</h3>
<div class="ed-tw"><table class="ed-tb" id="ed-t-turno"><thead><tr><th style="text-align:left;padding-left:14px;">Turno</th><th>M</th><th>F</th><th>Total</th><th>%</th></tr></thead><tbody>
<?php foreach($cnt_turno as $tn=>$v): ?>
<tr><td class="tl"><?php echo esc_html($tn); ?></td><td><?php echo $v['M']; ?></td><td><?php echo $v['F']; ?></td><td><?php echo $v['I'] ?? 0; ?></td><td class="sige-u-fw7"><?php echo $v['total']; ?></td><td><?php echo $total>0?round($v['total']/$total*100,1):0; ?>%</td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr><td style="text-align:left;padding-left:14px;">TOTAL</td><td><?php echo $cnt_genero['M']; ?></td><td><?php echo $cnt_genero['F']; ?></td><td><?php echo $cnt_genero['I'] ?? 0; ?></td><td><?php echo $total; ?></td><td>100%</td></tr></tfoot></table></div></div>
<!-- IDADE -->
<div class="ed-sec ed-sec--idade"><h3 class="ed-st"><span class="ed-sec-icon"><?php echo $ed_icon('calendar'); ?></span>Por Idade</h3>
<div class="ed-tw"><table class="ed-tb" id="ed-t-idade"><thead><tr><th>Idade</th><th>M</th><th>F</th><th>Por confirmar</th><th>Total</th><th>%</th></tr></thead><tbody>
<?php foreach($cnt_idade as $id=>$v): $t=$v['M']+$v['F']+($v['I'] ?? 0); ?>
<tr><td class="sige-u-fw7"><?php echo $id; ?> anos</td><td><?php echo $v['M']; ?></td><td><?php echo $v['F']; ?></td><td><?php echo $v['I'] ?? 0; ?></td><td class="sige-u-fw7"><?php echo $t; ?></td><td><?php echo $total>0?round($t/$total*100,1):0; ?>%</td></tr>
<?php endforeach; ?>
</tbody><tfoot><tr><td>TOTAL</td><td><?php echo $cnt_genero['M']; ?></td><td><?php echo $cnt_genero['F']; ?></td><td><?php echo $cnt_genero['I'] ?? 0; ?></td><td><?php echo $total; ?></td><td>100%</td></tr></tfoot></table></div><p class="ed-responsive-note">Deslize a tabela para ver todas as colunas.</p></div>
</div>

<?php if(!empty($cnt_mes_mat)): ?>
<div class="ed-sec"><h3 class="ed-st"><span class="ed-sec-icon"><?php echo $ed_icon('trend'); ?></span>Tend&ecirc;ncia de Matr&iacute;culas por M&ecirc;s</h3>
<div class="ed-tw"><table class="ed-tb" id="ed-t-trend"><thead><tr><th style="text-align:left;padding-left:14px;">M&ecirc;s</th><th>Matr&iacute;culas</th><th>Acumulado</th><th style="min-width:140px">Progresso</th></tr></thead><tbody>
<?php $acum=0; foreach($cnt_mes_mat as $mm=>$n): $acum+=$n; $parts=explode('-',$mm); $ml=($meses_pt[(int)$parts[1]]??$parts[1]).'/'.$parts[0]; $pct=$total>0?round($acum/$total*100,0):0; ?>
<tr><td class="tl"><?php echo esc_html($ml); ?></td><td class="sige-u-fw7"><?php echo $n; ?></td><td><?php echo $acum; ?></td><td><div class="ed-bar" style="background:var(--color-ink-100)"><div class="ed-bar-g" style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,var(--color-success-600),var(--color-success-400))"></div></div> <span style="font-size:10px;color:var(--color-slate-500)"><?php echo $pct; ?>%</span></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if(!empty($cnt_bairro)): ?>
<div class="ed-sec"><h3 class="ed-st"><span class="ed-sec-icon"><?php echo $ed_icon('map'); ?></span>Por Bairro / Localidade</h3>
<div class="ed-tw"><table class="ed-tb" id="ed-t-bairro"><thead><tr><th style="text-align:left;padding-left:14px;">Bairro</th><th>Alunos</th><th>%</th><th style="min-width:140px">Propor&ccedil;&atilde;o</th></tr></thead><tbody>
<?php $top=array_slice($cnt_bairro,0,20,true); foreach($top as $br=>$n): $pct=$total>0?round($n/$total*100,1):0; ?>
<tr><td class="tl"><?php echo esc_html($br); ?></td><td class="sige-u-fw7"><?php echo $n; ?></td><td><?php echo $pct; ?>%</td><td><div class="ed-bar" style="background:var(--color-ink-100)"><div class="ed-bar-g" style="width:<?php echo min($pct*3,100); ?>%;background:linear-gradient(90deg,var(--color-brand-400),var(--color-brand-300))"></div></div></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if(count($cnt_bairro)>20): ?><p style="font-size:11px;color:var(--color-slate-400);margin-top:6px;padding-left:4px">Top 20 de <?php echo count($cnt_bairro); ?> bairros.</p><?php endif; ?>
</div>
<?php endif; ?>

<div class="ed-legend">
<span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--color-info-500);vertical-align:middle;margin-right:4px"></span>Masculino</span>
<span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--color-danger-400);vertical-align:middle;margin-right:4px"></span>Feminino</span>
<span>Sobreidade: &gt;1 ano acima ref. SNE</span>
</div>

<?php else: ?>
<div class="ed-empty"><h3>Sem dados para <?php echo esc_html((string)$ano_sel); ?></h3><p>N&atilde;o foram encontradas matr&iacute;culas activas<?php if($filtro_classe): ?> para <strong><?php echo esc_html($filtro_classe); ?></strong><?php endif; ?>.</p></div>
<?php endif; ?>
<div class="ed-ft">SIGE SoftGenial &middot; <?php echo esc_html($_en); ?> &middot; Estat&iacute;sticas Demogr&aacute;ficas v2.0</div>
</div>

<?php if($total > 0): ?>
<?php echo function_exists('sige_cdn_script') ? sige_cdn_script('chartjs') : ''; ?>
<?php echo function_exists('sige_cdn_script') ? sige_cdn_script('exceljs') : ''; ?>
<?php echo function_exists('sige_cdn_script') ? sige_cdn_script('filesaver') : ''; ?>
<script <?php echo sige_csp_script_attr(); ?>>
/* ═══ CHART.JS ═══ */
document.addEventListener('DOMContentLoaded',function(){
if(typeof Chart==='undefined')return;
Chart.defaults.font.family="'Segoe UI',Arial,sans-serif";Chart.defaults.font.size=11;
var cM='#5a3fd6',cF='#16a34a',cI='#94a3b8',cMl='rgba(90,63,214,.55)',cFl='rgba(22,163,74,.55)',cIl='rgba(148,163,184,.55)';

new Chart(document.getElementById('chartGenero'),{type:'doughnut',
data:{labels:['Masculino','Feminino','Por confirmar'],datasets:[{data:[<?php echo $cnt_genero['M']; ?>,<?php echo $cnt_genero['F']; ?>,<?php echo $cnt_genero['I'] ?? 0; ?>],backgroundColor:[cM,cF,cI],borderWidth:4,borderColor:'#fff',hoverOffset:6}]},
options:{responsive:true,maintainAspectRatio:true,cutout:'66%',plugins:{legend:{position:'bottom',labels:{padding:14,usePointStyle:true,pointStyle:'circle'}}}}});

new Chart(document.getElementById('chartClasse'),{type:'bar',
data:{labels:<?php echo wp_json_encode($chart_classe_labels); ?>,datasets:[{label:'Masculino',data:<?php echo wp_json_encode($chart_classe_m); ?>,backgroundColor:cM,borderRadius:10},{label:'Feminino',data:<?php echo wp_json_encode($chart_classe_f); ?>,backgroundColor:cF,borderRadius:10},{label:'Por confirmar',data:<?php echo wp_json_encode($chart_classe_i); ?>,backgroundColor:cI,borderRadius:10}]},
options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'circle',padding:14}}},scales:{x:{stacked:true,grid:{display:false}},y:{stacked:true,beginAtZero:true,ticks:{stepSize:5}}}}});

new Chart(document.getElementById('chartIdade'),{type:'bar',
data:{labels:<?php echo wp_json_encode($chart_idade_labels); ?>,datasets:[{label:'Masculino',data:<?php echo wp_json_encode($chart_idade_m); ?>,backgroundColor:cMl,borderColor:cM,borderWidth:1,borderRadius:10},{label:'Feminino',data:<?php echo wp_json_encode($chart_idade_f); ?>,backgroundColor:cFl,borderColor:cF,borderWidth:1,borderRadius:10},{label:'Por confirmar',data:<?php echo wp_json_encode($chart_idade_i); ?>,backgroundColor:cIl,borderColor:cI,borderWidth:1,borderRadius:10}]},
options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'circle',padding:14}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true,ticks:{stepSize:2}}}}});

new Chart(document.getElementById('chartTrend'),{type:'line',
data:{labels:<?php echo wp_json_encode($chart_trend_labels); ?>,datasets:[{label:'Mensal',data:<?php echo wp_json_encode($chart_trend_vals); ?>,borderColor:'#f59e0b',backgroundColor:'rgba(245,158,11,.1)',fill:false,tension:.3,pointRadius:5,pointBackgroundColor:'#f59e0b'},{label:'Acumulado',data:<?php echo wp_json_encode($chart_trend_acum); ?>,borderColor:'#10b981',backgroundColor:'rgba(16,185,129,.08)',fill:true,tension:.3,pointRadius:4,pointBackgroundColor:'#10b981'}]},
options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'circle',padding:14}}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
});

/* ═══ IMPRESSÃO ═══ */
function edPrint(){
var ids=['ed-t-classe','ed-t-sobre','ed-t-ocup','ed-t-turno','ed-t-idade','ed-t-trend','ed-t-bairro'];
var titles=['Distribui\u00e7\u00e3o por Classe e G\u00e9nero','An\u00e1lise de Sobreidade (SNE)','Taxa de Ocupa\u00e7\u00e3o das Turmas','Distribui\u00e7\u00e3o por Turno','Distribui\u00e7\u00e3o por Idade','Tend\u00eancia de Matr\u00edculas','Distribui\u00e7\u00e3o por Bairro'];
var h='<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Estat\u00edsticas Demogr\u00e1ficas</title><style><?php echo sige_utilities_inline_css(); ?>'
+'*{margin:0;padding:0;box-sizing:border-box}'
+'body{font-family:Arial,sans-serif;font-size:9pt;color:#000;background:#fff;padding:10mm}'
+'.hd{text-align:center;border-bottom:2pt solid #0f2b47;padding-bottom:6pt;margin-bottom:10pt}'
+'.hd h1{font-size:14pt;color:#0f2b47}.hd h2{font-size:10pt;font-weight:normal;color:#333}'
+'.kpi{display:flex;gap:10pt;justify-content:center;margin:8pt 0 14pt;flex-wrap:wrap}'
+'.kpi-c{border:1.5pt solid #0f2b47;border-radius:6pt;padding:8pt 14pt;text-align:center;min-width:80pt}'
+'.kpi-v{font-size:16pt;font-weight:bold;color:#0f2b47}.kpi-l{font-size:7pt;color:#666;text-transform:uppercase}'
+'.kpi-d{font-size:7pt;margin-top:2pt}'
+'.stit{font-size:10pt;font-weight:bold;margin:12pt 0 4pt;border-left:3pt solid #0f2b47;padding-left:6pt;color:#0f2b47}'
+'table{width:100%;border-collapse:collapse;font-size:8pt;margin-bottom:10pt}'
+'th{background:#0f2b47;color:#fff;padding:5pt;border:.5pt solid #0f2b47;text-align:center;font-size:7.5pt}'
+'td{padding:3pt 5pt;border:.5pt solid #ccc;text-align:center}td.tl{text-align:left;font-weight:600}'
+'tfoot td{font-weight:bold;background:#e8edf2}tr:nth-child(even) td{background:#f8fafc}'
+'.lg{font-size:7pt;color:#666;margin-top:8pt;text-align:center}'
+'.ed-bar,.ed-bar-m,.ed-bar-f,.ed-bar-g{display:none}'
+'@page{size:A4 portrait;margin:10mm}'
+'</style></head><body><div class="hd">'
+'<h1>'+<?php echo wp_json_encode(mb_strtoupper($_en)); ?>+'</h1>'
+'<h2>Estat\u00edsticas Demogr\u00e1ficas \u2014 Ano Lectivo '+<?php echo wp_json_encode((string)$ano_sel); ?>+'</h2></div>'
+'<div class="kpi">'
+'<div class="kpi-c"><div class="kpi-v"><?php echo $total; ?></div><div class="kpi-l">Total</div><?php if($total_ant>0): ?><div class="kpi-d"><?php echo ($delta_total>=0?'+':'').$delta_total; ?> vs <?php echo $ano_ant; ?></div><?php endif; ?></div>'
+'<div class="kpi-c"><div class="kpi-v"><?php echo $cnt_genero['M']; ?></div><div class="kpi-l">Masculino</div></div>'
+'<div class="kpi-c"><div class="kpi-v"><?php echo $cnt_genero['F']; ?></div><div class="kpi-l">Feminino</div></div>'
+'<div class="kpi-c"><div class="kpi-v"><?php echo number_format($idade_media,1,',',''); ?></div><div class="kpi-l">Idade M\u00e9dia</div></div>'
+'<div class="kpi-c"><div class="kpi-v"><?php echo $sobre_total; ?></div><div class="kpi-l">Sobreidade</div></div>'
+'</div>';
ids.forEach(function(id,i){var el=document.getElementById(id);if(el){h+='<div class="stit">'+titles[i]+'</div>';var cl=el.cloneNode(true);cl.removeAttribute('id');cl.removeAttribute('class');cl.querySelectorAll('.ed-bar').forEach(function(b){b.parentNode.removeChild(b);});h+=cl.outerHTML;}});
h+='<p class="lg">SIGE SoftGenial \u2014 '+<?php echo wp_json_encode($_en); ?>+' \u2014 '+new Date().toLocaleDateString('pt-MZ')+'</p></body></html>';
var w=window.open('','_blank','width=900,height=700,scrollbars=yes');
if(!w){sigeUi.toast('O navegador bloqueou a janela de impressão. Permita popups para este site e tente novamente.', 'aviso');return;}w.document.write(h);w.document.close();setTimeout(function(){w.focus();w.print();},400);
}

/* ═══ EXCEL PROFISSIONAL ═══ */
function edXls(){
if(typeof ExcelJS==='undefined'){sigeUi.toast('A biblioteca de exportação ainda não carregou. Aguarde uns segundos e tente novamente.', 'erro');return;}
var wb=new ExcelJS.Workbook();wb.creator='SIGE SoftGenial';wb.created=new Date();
var navy='0F2B47',white='FFFFFF',ltGray='F1F5F9',medGray='CBD5E1',blue='2563EB',pink='EC4899',green='16A34A',red='EF4444',amber='F59E0B';
var bAll={top:{style:'thin',color:{argb:medGray}},left:{style:'thin',color:{argb:medGray}},bottom:{style:'thin',color:{argb:medGray}},right:{style:'thin',color:{argb:medGray}}};
var bBold={top:{style:'medium',color:{argb:navy}},left:{style:'thin',color:{argb:medGray}},bottom:{style:'medium',color:{argb:navy}},right:{style:'thin',color:{argb:medGray}}};
var hdrFill={type:'pattern',pattern:'solid',fgColor:{argb:navy}};
var hdrFont={bold:true,color:{argb:white},size:10,name:'Segoe UI'};
var altFill={type:'pattern',pattern:'solid',fgColor:{argb:ltGray}};
var totFill={type:'pattern',pattern:'solid',fgColor:{argb:'E8EDF2'}};
var totFont={bold:true,size:10,name:'Segoe UI',color:{argb:navy}};
var bodyFont={size:10,name:'Segoe UI'};

function addTitle(ws,title,cols){
    ws.mergeCells(1,1,1,cols);var c1=ws.getRow(1).getCell(1);
    c1.value=<?php echo wp_json_encode(mb_strtoupper($_en)); ?>;
    c1.font={bold:true,size:14,name:'Segoe UI',color:{argb:navy}};c1.alignment={horizontal:'center',vertical:'middle'};ws.getRow(1).height=28;
    ws.mergeCells(2,1,2,cols);var c2=ws.getRow(2).getCell(1);
    c2.value=title+' \u2014 Ano Lectivo '+<?php echo wp_json_encode((string)$ano_sel); ?>;
    c2.font={bold:true,size:11,name:'Segoe UI',color:{argb:'334155'}};c2.alignment={horizontal:'center',vertical:'middle'};ws.getRow(2).height=22;
    ws.mergeCells(3,1,3,cols);var c3=ws.getRow(3).getCell(1);
    c3.value='Gerado: '+new Date().toLocaleDateString('pt-MZ')+' | SIGE SoftGenial';
    c3.font={size:8,name:'Segoe UI',color:{argb:'94A3B8'},italic:true};c3.alignment={horizontal:'center'};ws.getRow(3).height=16;
    return 5;
}
function styleH(ws,r,n){ws.getRow(r).height=24;for(var i=1;i<=n;i++){var c=ws.getRow(r).getCell(i);c.fill=hdrFill;c.font=hdrFont;c.alignment={horizontal:'center',vertical:'middle',wrapText:true};c.border=bAll;}}
function styleB(ws,r,n,alt){ws.getRow(r).height=18;for(var i=1;i<=n;i++){var c=ws.getRow(r).getCell(i);c.font=bodyFont;c.border=bAll;c.alignment={horizontal:'center',vertical:'middle'};if(alt)c.fill=altFill;}}
function styleT(ws,r,n){ws.getRow(r).height=22;for(var i=1;i<=n;i++){var c=ws.getRow(r).getCell(i);c.fill=totFill;c.font=totFont;c.border=bBold;c.alignment={horizontal:'center',vertical:'middle'};}}

/* ═══ F1: RESUMO ═══ */
var w1=wb.addWorksheet('Resumo',{properties:{tabColor:{argb:navy}}});
w1.columns=[{width:24},{width:18},{width:20}];
var s=addTitle(w1,'Resumo Demogr\u00e1fico',3);
w1.mergeCells(s,1,s,3);w1.getRow(s).getCell(1).value='INDICADORES PRINCIPAIS';w1.getRow(s).getCell(1).font={bold:true,size:11,color:{argb:navy}};s++;
styleH(w1,s,3);w1.getRow(s).getCell(1).value='Indicador';w1.getRow(s).getCell(2).value='Valor';w1.getRow(s).getCell(3).value='Detalhe';s++;
var kpis=[['Total Alunos',<?php echo $total; ?>,'<?php echo ($delta_total>=0?"+":"").$delta_total; ?> vs <?php echo $ano_ant; ?>'],
['Masculino',<?php echo $cnt_genero['M']; ?>,'<?php echo $total>0?round($cnt_genero['M']/$total*100,1):0; ?>%'],
['Feminino',<?php echo $cnt_genero['F']; ?>,'<?php echo $total>0?round($cnt_genero['F']/$total*100,1):0; ?>%'],
['Idade M\u00e9dia',<?php echo wp_json_encode($idade_media); ?>,'Mediana: <?php echo number_format($idade_mediana,1,',',''); ?>'],
['Sobreidade',<?php echo $sobre_total; ?>,'<?php echo $sobre_pct; ?>% do total'],
['Turmas',<?php echo count($turmas_ocup); ?>,'<?php echo count($cnt_classe); ?> classe(s)']];
kpis.forEach(function(k,i){w1.getRow(s+i).getCell(1).value=k[0];w1.getRow(s+i).getCell(2).value=k[1];w1.getRow(s+i).getCell(3).value=k[2];styleB(w1,s+i,3,i%2===1);w1.getRow(s+i).getCell(1).alignment={horizontal:'left'};w1.getRow(s+i).getCell(1).font={bold:true,size:10,name:'Segoe UI'};});

/* ═══ F2: POR CLASSE ═══ */
var w2=wb.addWorksheet('Por Classe',{properties:{tabColor:{argb:blue}}});
w2.columns=[{width:18},{width:14},{width:14},{width:14},{width:12},{width:12}];
s=addTitle(w2,'Distribui\u00e7\u00e3o por Classe e G\u00e9nero',6);
styleH(w2,s,6);['Classe','Masculino','Feminino','Total','% M','% F'].forEach(function(h,i){w2.getRow(s).getCell(i+1).value=h;});s++;
var idx=0;
<?php foreach($cnt_classe as $cl=>$v): $pM=$v['total']>0?round($v['M']/$v['total']*100,1):0; $pF=$v['total']>0?round($v['F']/$v['total']*100,1):0; ?>
w2.getRow(s+idx).getCell(1).value=<?php echo wp_json_encode($cl); ?>;w2.getRow(s+idx).getCell(2).value=<?php echo $v['M']; ?>;w2.getRow(s+idx).getCell(3).value=<?php echo $v['F']; ?>;w2.getRow(s+idx).getCell(4).value=<?php echo $v['total']; ?>;w2.getRow(s+idx).getCell(5).value=<?php echo $pM; ?>+'%';w2.getRow(s+idx).getCell(6).value=<?php echo $pF; ?>+'%';styleB(w2,s+idx,6,idx%2===1);w2.getRow(s+idx).getCell(1).alignment={horizontal:'left'};w2.getRow(s+idx).getCell(4).font={bold:true,size:10,name:'Segoe UI'};idx++;
<?php endforeach; ?>
styleT(w2,s+idx,6);w2.getRow(s+idx).getCell(1).value='TOTAL';w2.getRow(s+idx).getCell(2).value=<?php echo $cnt_genero['M']; ?>;w2.getRow(s+idx).getCell(3).value=<?php echo $cnt_genero['F']; ?>;w2.getRow(s+idx).getCell(4).value=<?php echo $total; ?>;w2.getRow(s+idx).getCell(1).alignment={horizontal:'left'};

/* ═══ F3: SOBREIDADE ═══ */
var w3=wb.addWorksheet('Sobreidade',{properties:{tabColor:{argb:red}}});
w3.columns=[{width:18},{width:12},{width:12},{width:14},{width:10},{width:10},{width:12}];
s=addTitle(w3,'An\u00e1lise de Sobreidade (Ref. SNE)',7);
styleH(w3,s,7);['Classe','Idade Ref.','Total','Sobreidade','M','F','%'].forEach(function(h,i){w3.getRow(s).getCell(i+1).value=h;});s++;idx=0;
<?php foreach($sobreidade as $cl=>$sv): $pct=$sv['total']>0?round($sv['sobre']/$sv['total']*100,1):0; ?>
w3.getRow(s+idx).getCell(1).value=<?php echo wp_json_encode($cl); ?>;w3.getRow(s+idx).getCell(2).value=<?php echo $sv['ref']>0?$sv['ref']:"'N/D'"; ?>;w3.getRow(s+idx).getCell(3).value=<?php echo $sv['total']; ?>;w3.getRow(s+idx).getCell(4).value=<?php echo $sv['sobre']; ?>;w3.getRow(s+idx).getCell(5).value=<?php echo $sv['sobre_M']; ?>;w3.getRow(s+idx).getCell(6).value=<?php echo $sv['sobre_F']; ?>;w3.getRow(s+idx).getCell(7).value=<?php echo $pct; ?>+'%';styleB(w3,s+idx,7,idx%2===1);w3.getRow(s+idx).getCell(1).alignment={horizontal:'left'};
if(<?php echo $sv['sobre']; ?>>0){w3.getRow(s+idx).getCell(4).font={bold:true,size:10,name:'Segoe UI',color:{argb:red}};}idx++;
<?php endforeach; ?>
styleT(w3,s+idx,7);w3.getRow(s+idx).getCell(1).value='TOTAL';w3.getRow(s+idx).getCell(3).value=<?php echo $total; ?>;w3.getRow(s+idx).getCell(4).value=<?php echo $sobre_total; ?>;w3.getRow(s+idx).getCell(5).value=<?php echo $sobre_total_m; ?>;w3.getRow(s+idx).getCell(6).value=<?php echo $sobre_total_f; ?>;w3.getRow(s+idx).getCell(7).value=<?php echo $sobre_pct; ?>+'%';w3.getRow(s+idx).getCell(1).alignment={horizontal:'left'};

/* ═══ F4: OCUPAÇÃO ═══ */
var w4=wb.addWorksheet('Ocupa\u00e7\u00e3o',{properties:{tabColor:{argb:amber}}});
w4.columns=[{width:26},{width:14},{width:12},{width:10},{width:10},{width:12},{width:14},{width:12}];
s=addTitle(w4,'Taxa de Ocupa\u00e7\u00e3o das Turmas',8);
styleH(w4,s,8);['Turma','Classe','Turno','M','F','Efectivo','Capacidade','Ocupa\u00e7\u00e3o'].forEach(function(h,i){w4.getRow(s).getCell(i+1).value=h;});s++;idx=0;
<?php foreach($turmas_ocup as $to): $pct_o=$to['cap']>0?round($to['total']/$to['cap']*100,0):0; ?>
w4.getRow(s+idx).getCell(1).value=<?php echo wp_json_encode($to['classe'].' - '.$to['nome']); ?>;w4.getRow(s+idx).getCell(2).value=<?php echo wp_json_encode($to['classe']); ?>;w4.getRow(s+idx).getCell(3).value=<?php echo wp_json_encode($to['turno']); ?>;w4.getRow(s+idx).getCell(4).value=<?php echo $to['M']; ?>;w4.getRow(s+idx).getCell(5).value=<?php echo $to['F']; ?>;w4.getRow(s+idx).getCell(6).value=<?php echo $to['total']; ?>;w4.getRow(s+idx).getCell(7).value=<?php echo $to['cap']>0?$to['cap']:"'N/D'"; ?>;w4.getRow(s+idx).getCell(8).value=<?php echo $to['cap']>0?"$pct_o+'%'":"'N/D'"; ?>;styleB(w4,s+idx,8,idx%2===1);w4.getRow(s+idx).getCell(1).alignment={horizontal:'left'};w4.getRow(s+idx).getCell(6).font={bold:true,size:10,name:'Segoe UI'};
<?php if($to['cap']>0 && $pct_o>95): ?>w4.getRow(s+idx).getCell(8).font={bold:true,size:10,name:'Segoe UI',color:{argb:red}};<?php elseif($to['cap']>0 && $pct_o>80): ?>w4.getRow(s+idx).getCell(8).font={bold:true,size:10,name:'Segoe UI',color:{argb:'D97706'}};<?php endif; ?>
idx++;
<?php endforeach; ?>
styleT(w4,s+idx,8);w4.getRow(s+idx).getCell(1).value='TOTAL';w4.getRow(s+idx).getCell(4).value=<?php echo $cnt_genero['M']; ?>;w4.getRow(s+idx).getCell(5).value=<?php echo $cnt_genero['F']; ?>;w4.getRow(s+idx).getCell(6).value=<?php echo $total; ?>;w4.getRow(s+idx).getCell(7).value=<?php echo $cap_total>0?$cap_total:"'N/D'"; ?>;w4.getRow(s+idx).getCell(8).value=<?php echo $cap_total>0?round($total/$cap_total*100,0)."+'%'":"'N/D'"; ?>;w4.getRow(s+idx).getCell(1).alignment={horizontal:'left'};

/* ═══ F5: IDADE ═══ */
var w5=wb.addWorksheet('Por Idade',{properties:{tabColor:{argb:amber}}});
w5.columns=[{width:14},{width:14},{width:14},{width:14},{width:12}];
s=addTitle(w5,'Distribui\u00e7\u00e3o por Idade',5);
styleH(w5,s,5);['Idade','Masculino','Feminino','Total','%'].forEach(function(h,i){w5.getRow(s).getCell(i+1).value=h;});s++;idx=0;
<?php foreach($cnt_idade as $id=>$v): $t=$v['M']+$v['F']+($v['I'] ?? 0); ?>
w5.getRow(s+idx).getCell(1).value=<?php echo $id; ?>;w5.getRow(s+idx).getCell(2).value=<?php echo $v['M']; ?>;w5.getRow(s+idx).getCell(3).value=<?php echo $v['F']; ?>;w5.getRow(s+idx).getCell(4).value=<?php echo $t; ?>;w5.getRow(s+idx).getCell(5).value=<?php echo $total>0?round($t/$total*100,1):0; ?>+'%';styleB(w5,s+idx,5,idx%2===1);idx++;
<?php endforeach; ?>
styleT(w5,s+idx,5);w5.getRow(s+idx).getCell(1).value='TOTAL';w5.getRow(s+idx).getCell(2).value=<?php echo $cnt_genero['M']; ?>;w5.getRow(s+idx).getCell(3).value=<?php echo $cnt_genero['F']; ?>;w5.getRow(s+idx).getCell(4).value=<?php echo $total; ?>;

/* ═══ F6: MATRÍCULAS ═══ */
var w6=wb.addWorksheet('Matr\u00edculas',{properties:{tabColor:{argb:green}}});
w6.columns=[{width:16},{width:16},{width:16}];
s=addTitle(w6,'Tend\u00eancia de Matr\u00edculas por M\u00eas',3);
styleH(w6,s,3);['M\u00eas','Matr\u00edculas','Acumulado'].forEach(function(h,i){w6.getRow(s).getCell(i+1).value=h;});s++;idx=0;
<?php $ac=0; foreach($cnt_mes_mat as $mm=>$n): $ac+=$n; $parts=explode('-',$mm); $ml=($meses_pt[(int)$parts[1]]??$parts[1]).'/'.$parts[0]; ?>
w6.getRow(s+idx).getCell(1).value=<?php echo wp_json_encode($ml); ?>;w6.getRow(s+idx).getCell(2).value=<?php echo $n; ?>;w6.getRow(s+idx).getCell(3).value=<?php echo $ac; ?>;styleB(w6,s+idx,3,idx%2===1);w6.getRow(s+idx).getCell(1).alignment={horizontal:'left'};idx++;
<?php endforeach; ?>

/* ═══ F7: BAIRRO ═══ */
var w7=wb.addWorksheet('Por Bairro',{properties:{tabColor:{argb:'8B5CF6'}}});
w7.columns=[{width:24},{width:14},{width:12}];
s=addTitle(w7,'Distribui\u00e7\u00e3o por Bairro',3);
styleH(w7,s,3);['Bairro','Alunos','%'].forEach(function(h,i){w7.getRow(s).getCell(i+1).value=h;});s++;idx=0;
<?php foreach($cnt_bairro as $br=>$n): ?>
w7.getRow(s+idx).getCell(1).value=<?php echo wp_json_encode($br); ?>;w7.getRow(s+idx).getCell(2).value=<?php echo $n; ?>;w7.getRow(s+idx).getCell(3).value=<?php echo $total>0?round($n/$total*100,1):0; ?>+'%';styleB(w7,s+idx,3,idx%2===1);w7.getRow(s+idx).getCell(1).alignment={horizontal:'left'};idx++;
<?php endforeach; ?>

wb.xlsx.writeBuffer().then(function(b){saveAs(new Blob([b],{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}),'Estatisticas_Demograficas_'+<?php echo wp_json_encode(sanitize_file_name($_en)); ?>+'_<?php echo $ano_sel; ?>.xlsx');});
}
</script>
<?php endif; ?>
