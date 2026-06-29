<?php

/**

 * SIGE SoftGenial - Export Pauta Excel (XLSX)

 * Sem dependências externas - XLSX puro (ZIP + XML)

 * Registado via: add_action('admin_post_sige_pauta_excel', ...)

 */

if (!defined('ABSPATH')) exit;

add_action('admin_post_sige_pauta_excel', 'sige_handle_pauta_excel');

function sige_handle_pauta_excel() {

    if (function_exists('sige_require_user_can_any_secure')) {
        sige_require_user_can_any_secure(
            ['academico.pautas_ver','academico.pautas_emitir','academico.lancar_notas'],
            ['sige_professor','sige_pedagogico','sige_secretario','sige_director'],
            'Acesso negado. Apenas docentes e staff autorizados podem exportar pautas.'
        );
    } elseif (!current_user_can('sige_professor') && !current_user_can('sige_pedagogico') && !current_user_can('sige_secretario') && !current_user_can('sige_director') && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
        wp_die('Acesso negado. Apenas docentes e staff podem exportar pautas.', 403);
    }

    check_admin_referer('sige_pauta_excel_nonce');

    global $wpdb;

    $p = $wpdb->prefix;
    $eid = sige_get_escola_id();

    $ano_lectivo   = isset($_GET['ano_lectivo'])      ? (int)$_GET['ano_lectivo']      : (int)wp_date('Y');

    $turma_id      = isset($_GET['turma_id'])          ? (int)$_GET['turma_id']          : 0;

    $trimestre     = isset($_GET['trimestre'])         ? (int)$_GET['trimestre']         : 0;
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

    $nome_turma = !empty($turma->nome_turma) ? $turma->nome_turma : (!empty($turma->nome) ? $turma->nome : '');

    $label_turma = $classe_num.'ª - '.$nome_turma.(!empty($turma->turno) ? ' • '.$turma->turno : '');

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
    if (function_exists('sige_apply_categoria_oficial_disciplinas')) { $disciplinas = sige_apply_categoria_oficial_disciplinas($disciplinas, $classe_turma); }
    if (function_exists('sige_sort_disciplinas_oficial')) { $disciplinas = sige_sort_disciplinas_oficial($disciplinas); }

    // Alunos

    $pop_ch = function_exists('sige_aluno_matricula_activa_sql') ? sige_aluno_matricula_activa_sql('a','m') : "(m.status_matricula IS NULL OR m.status_matricula IN ('activa','ativa'))";
    $ord_ch = function_exists('sige_turma_ordem_chamada_order_sql') ? sige_turma_ordem_chamada_order_sql('a') : "a.nome_completo ASC, a.id ASC";
    $alunos = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id,a.nome_completo,a.genero FROM {$p}sige_matriculas m
         INNER JOIN {$p}sige_alunos a ON a.id=m.aluno_id AND a.escola_id=m.escola_id
         WHERE m.turma_id=%d AND m.ano_lectivo=%d AND m.escola_id=%d
           AND {$pop_ch}
         ORDER BY {$ord_ch}", $turma_id, $ano_lectivo, $eid));

    // Notas

    $notas_map = [];

    if (!empty($alunos)) {

        $ids = array_map(function($o){ return (int)$o->id; }, $alunos);

        $pl  = implode(',', array_fill(0, count($ids), '%d'));

        $params = array_merge($ids, [$turma_id, $ano_lectivo, $eid]);

        $rows = $wpdb->get_results($wpdb->prepare(

            "SELECT aluno_id,disciplina_id,trimestre,nota_ac,nota_acp,nota_exame,nota_conselho

             FROM {$p}sige_notas WHERE aluno_id IN ($pl) AND turma_id=%d AND ano_lectivo=%d AND escola_id=%d AND (status IS NULL OR status = 'aprovado')", $params));

        foreach ($rows as $r) {

            $notas_map[(int)$r->aluno_id][(int)$r->disciplina_id][(int)$r->trimestre] = $r;

        }

    }

    // Helpers

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

        if ($n===null) return '';

        $n=(float)$n;

        if ($n<10) return 'NS'; if ($n<14) return 'S';

        if ($n<17) return 'B';  if ($n<19) return 'MB'; return 'E';

    };

    // ─── CONSTRUIR DADOS DA FOLHA ─────────────────────────────

    // Período

    $trims_labels = ['','Iº Trimestre','IIº Trimestre','IIIº Trimestre'];

    $titulo = $trimestre>0 ? 'PAUTA DE FREQUÊNCIA - '.$trims_labels[$trimestre] : 'PAUTA ANUAL DE FREQUÊNCIA';

    $periodo_label = $trimestre>0 ? $trims_labels[$trimestre] : 'Anual';

    $escola = get_bloginfo('name');

    $is_final = ($trimestre === 0);

    // Montar estrutura de colunas

    // Col fixas: Nº | Nome | G

    // Por disciplina: se final → MT1 MT2 MT3 MFD ESC ; se trimestral → MT ESC

    // Se final: + MG | Neg. | Resultado

    $cols_per_disc = $is_final ? 5 : 2;

    // Gerar dados - array de rows (cada row é array de células)

    $xlsx = new SigeXlsx();

    // ── Linha 1: título ──

    $total_disc_cols = count($disciplinas) * $cols_per_disc;

    $total_cols = 3 + $total_disc_cols + ($is_final ? 3 : 0);

    $xlsx->addMergedRow([$titulo], $total_cols, 'title');

    $xlsx->addMergedRow([$escola.' | Turma: '.$label_turma.' | Classe: '.$classe_num.'ª | Ano: '.$ano_lectivo.' | Período: '.$periodo_label], $total_cols, 'subtitle');

    $xlsx->addEmptyRow();

    // ── Linha cabeçalho nível 1: Nº | Nome | G | [DISC x ncols] | [MG Neg Resultado] ──

    $h1 = [

        ['v'=>'Nº',          'style'=>'hdr_dark', 'merge'=>1],

        ['v'=>'Nome do Aluno','style'=>'hdr_dark', 'merge'=>1],

        ['v'=>'G',            'style'=>'hdr_dark', 'merge'=>1],

    ];

    foreach ($disciplinas as $d) {

        $is_nuc = strtolower($d->categoria??'') === 'nuclear';

        $lbl = ($d->sigla ?? $d->nome).($is_nuc?' ★':' ○');

        $h1[] = ['v'=>$lbl, 'style'=>$is_nuc?'hdr_nuc':'hdr_aux', 'merge'=>$cols_per_disc];

    }

    if ($is_final) {

        $h1[] = ['v'=>'MG',       'style'=>'hdr_mg',  'merge'=>1];

        $h1[] = ['v'=>'Neg.',     'style'=>'hdr_neg', 'merge'=>1];

        $h1[] = ['v'=>'Resultado','style'=>'hdr_sit', 'merge'=>1];

    }

    $xlsx->addHeaderRow1($h1);

    // ── Linha cabeçalho nível 2: sub-colunas ──

    $h2 = [

        ['v'=>'', 'style'=>'hdr_dark'],

        ['v'=>'', 'style'=>'hdr_dark'],

        ['v'=>'', 'style'=>'hdr_dark'],

    ];

    foreach ($disciplinas as $d) {

        $is_nuc = strtolower($d->categoria??'') === 'nuclear';

        $st = $is_nuc ? 'hdr_nuc2' : 'hdr_aux2';

        if ($is_final) {

            $h2[] = ['v'=>'MT1', 'style'=>$st];

            $h2[] = ['v'=>'MT2', 'style'=>$st];

            $h2[] = ['v'=>'MT3', 'style'=>$st];

            $h2[] = ['v'=>'MFD', 'style'=>'hdr_mfd'];

            $h2[] = ['v'=>'ESC', 'style'=>'hdr_esc'];

        } else {

            $h2[] = ['v'=>'MT',  'style'=>$st];

            $h2[] = ['v'=>'ESC', 'style'=>'hdr_esc'];

        }

    }

    if ($is_final) {

        $h2[] = ['v'=>'', 'style'=>'hdr_mg'];

        $h2[] = ['v'=>'', 'style'=>'hdr_neg'];

        $h2[] = ['v'=>'', 'style'=>'hdr_sit'];

    }

    $xlsx->addRow($h2);

    // ── Linhas de dados ──

    $num = 1;

    foreach ($alunos as $aluno) {

        $aid = (int)$aluno->id;

        $mfds_nuc = []; $negativas = 0;
        $nucleares_requeridas = 0;
        $nucleares_com_nota_final = 0;
        $tem_alguma_nota_final = false;

        $nota_min = (float)($regra['nota_minima_aprovacao']??10);

        $row = [

            ['v'=>$num++,                          'style'=>'num',  't'=>'n'],

            ['v'=>$aluno->nome_completo,            'style'=>'nome', 't'=>'s'],

            ['v'=>($aluno->genero??''),             'style'=>'ctr',  't'=>'s'],

        ];

        foreach ($disciplinas as $d) {

            $did = (int)$d->id;

            $is_nuc = strtolower($d->categoria??'') === 'nuclear';

            $mts = [];

            for ($t=1;$t<=3;$t++) {

                $r2 = $notas_map[$aid][$did][$t] ?? null;

                $mts[$t] = $fn_mt($r2);

            }

            $mfd   = (int)round(((int)$mts[1] + (int)$mts[2] + (int)$mts[3]) / 3, 0);

            $nota_final = $mfd;

            if ($eh_fim_ciclo && $pauta_final_m && $mfd!==null) {

                $row3 = $notas_map[$aid][$did][3] ?? null;

                $exame = ($row3 && is_numeric($row3->nota_exame)) ? (float)$row3->nota_exame : 0.0;

                $pm=(float)($regra['peso_mfd']??60); $pe=(float)($regra['peso_exame']??40);

                $nota_final = (int)round(($mfd*$pm+$exame*$pe)/($pm+$pe),0);

            }

            if ($is_nuc) {
                $nucleares_requeridas++;
            }
            if ($nota_final !== null) {
                $tem_alguma_nota_final = true;
            }

            if ($is_nuc && $nota_final!==null) {

                $mfds_nuc[] = $nota_final;
                $nucleares_com_nota_final++;

                if ($nota_final < $nota_min) $negativas++;

            }

            if ($is_final) {

                $row[] = ['v'=>$mts[1]!==null?$mts[1]:'', 'style'=>'mt', 't'=>'n'];

                $row[] = ['v'=>$mts[2]!==null?$mts[2]:'', 'style'=>'mt', 't'=>'n'];

                $row[] = ['v'=>$mts[3]!==null?$mts[3]:'', 'style'=>'mt', 't'=>'n'];

                $row[] = ['v'=>$nota_final!==null?$nota_final:'', 'style'=>'mfd','t'=>'n'];

                $row[] = ['v'=>$fn_esc($nota_final), 'style'=>'esc','t'=>'s'];

            } else {

                $mt_t = $mts[$trimestre] ?? null;

                $row[] = ['v'=>$mt_t!==null?$mt_t:'', 'style'=>'mt','t'=>'n'];

                $row[] = ['v'=>$fn_esc($mt_t),        'style'=>'esc','t'=>'s'];

            }

        }

        if ($is_final) {

            $mg = !empty($mfds_nuc) ? (int)round(array_sum($mfds_nuc)/count($mfds_nuc),0) : null;

            $situacao = '-';

            if (function_exists('sige_calcular_situacao_final')) {

                $sf = sige_calcular_situacao_final($aid,$turma_id,$ano_lectivo,(bool)$pauta_final_m);

                $situacao=$sf['situacao']??'-'; $negativas=$sf['negativas']??$negativas; $mg=$sf['media_global']??$mg;

            } else {

                $max_p=(int)($regra['max_negativas_progride']??0);

                $max_t=(int)($regra['max_negativas_transita']??2);

                if ($mg!==null && $mg<$nota_min) $situacao='REPROVA';

                elseif ($negativas<=$max_p) $situacao='PROGRIDE';

                elseif ($negativas<=$max_t) $situacao='TRANSITA';

                else $situacao='REPROVA';

            }

            $sit_raw = strtoupper($situacao);
            $sit_style = ['PROGRIDE'=>'sit_p','TRANSITA'=>'sit_t','REPROVA'=>'sit_r','PENDENTE'=>'ctr'][in_array($sit_raw, ['NÃO PROGRIDE','NAO PROGRIDE','NÃO TRANSITA','NAO TRANSITA'], true) ? 'REPROVA' : $sit_raw]??'ctr';
            $situacao_label = function_exists('sige_situacao_final_rotulo_publico') ? sige_situacao_final_rotulo_publico($situacao, $eh_fim_ciclo) : $situacao;

            $row[] = ['v'=>$mg!==null?$mg:'', 'style'=>'mg',      't'=>'n'];

            $row[] = ['v'=>$negativas!==null?$negativas:'',         'style'=>'neg',     't'=>'n'];

            $row[] = ['v'=>$situacao_label,           'style'=>$sit_style,'t'=>'s'];

        }

        $xlsx->addRow($row);

    }

    // ── Legenda ──

    $xlsx->addEmptyRow();

    $xlsx->addMergedRow(['★ = Disciplina Nuclear (conta para MG)  |  ○ = Auxiliar  |  ESC: NS(<10) | S(10-13) | B(14-16) | MB(17-18) | E(19-20)'], $total_cols, 'legenda');

    // ── Larguras das colunas ──

    $col_widths = [6, 35, 5]; // Nº, Nome, G

    foreach ($disciplinas as $d) {

        for ($i=0; $i<$cols_per_disc; $i++) $col_widths[] = ($i===0||$i===1||$i===2) ? 7 : ($i===3?7:6);

    }

    if ($is_final) { $col_widths[] = 7; $col_widths[] = 6; $col_widths[] = 12; }

    $xlsx->setColWidths($col_widths);

    // ── Enviar ficheiro ──

    $filename = 'Pauta_'.$classe_num.'Classe_'.str_replace(' ','',$nome_turma).'_'.$periodo_label.'_'.$ano_lectivo.'.xlsx';

    $xlsx->download($filename);

    exit;

}

// ════════════════════════════════════════════════════════════

// CLASSE SigeXlsx - gerador XLSX puro em PHP (sem dependências)

// ════════════════════════════════════════════════════════════

class SigeXlsx {

    private $rows = [];

    private $merges = [];

    private $col_widths = [];

    private $styles = [];

    private $shared_strings = [];

    private $ss_index = [];

    private $current_row = 1;

    // Cores e estilos

    private $style_defs = [

        // fills: bgColor

        'title'    => ['bg'=>'1A237E','fg'=>'FFFFFF','bold'=>1,'sz'=>13,'ha'=>'center'],

        'subtitle' => ['bg'=>'E8EAF6','fg'=>'1A237E','bold'=>0,'sz'=>10,'ha'=>'center'],

        'hdr_dark' => ['bg'=>'1A237E','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'hdr_nuc'  => ['bg'=>'155724','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'hdr_aux'  => ['bg'=>'4A5568','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'hdr_nuc2' => ['bg'=>'2D5F8A','fg'=>'FFFFFF','bold'=>1,'sz'=>9, 'ha'=>'center'],

        'hdr_aux2' => ['bg'=>'4A5568','fg'=>'FFFFFF','bold'=>1,'sz'=>9, 'ha'=>'center'],

        'hdr_mfd'  => ['bg'=>'7C4700','fg'=>'FFFFFF','bold'=>1,'sz'=>9, 'ha'=>'center'],

        'hdr_esc'  => ['bg'=>'4A5568','fg'=>'FFFFFF','bold'=>1,'sz'=>9, 'ha'=>'center'],

        'hdr_mg'   => ['bg'=>'1A237E','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'hdr_neg'  => ['bg'=>'C0392B','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'hdr_sit'  => ['bg'=>'155724','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'num'      => ['bg'=>'FFFFFF','fg'=>'1A237E','bold'=>1,'sz'=>10,'ha'=>'center'],

        'nome'     => ['bg'=>'FFFFFF','fg'=>'000000','bold'=>0,'sz'=>10,'ha'=>'left'],

        'ctr'      => ['bg'=>'FFFFFF','fg'=>'000000','bold'=>0,'sz'=>9, 'ha'=>'center'],

        'mt'       => ['bg'=>'E8F4FF','fg'=>'0D3B6E','bold'=>0,'sz'=>10,'ha'=>'center'],

        'mfd'      => ['bg'=>'FFF8CC','fg'=>'7C4700','bold'=>1,'sz'=>11,'ha'=>'center'],

        'esc'      => ['bg'=>'F3E5F5','fg'=>'4A1982','bold'=>0,'sz'=>9, 'ha'=>'center'],

        'mg'       => ['bg'=>'E8F5E9','fg'=>'155724','bold'=>1,'sz'=>12,'ha'=>'center'],

        'neg'      => ['bg'=>'FFFFFF','fg'=>'C0392B','bold'=>1,'sz'=>10,'ha'=>'center'],

        'sit_p'    => ['bg'=>'16A34A','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'sit_t'    => ['bg'=>'F59E0B','fg'=>'111111','bold'=>1,'sz'=>10,'ha'=>'center'],

        'sit_r'    => ['bg'=>'DC2626','fg'=>'FFFFFF','bold'=>1,'sz'=>10,'ha'=>'center'],

        'legenda'  => ['bg'=>'F8F9FA','fg'=>'555555','bold'=>0,'sz'=>8, 'ha'=>'left'],

    ];

    public function addMergedRow($values, $total_cols, $style) {

        $row = [['v'=>$values[0],'style'=>$style,'merge'=>$total_cols,'t'=>'s']];

        // Células vazias para completar

        for ($i=1; $i<$total_cols; $i++) $row[] = ['v'=>'','style'=>$style,'t'=>'s'];

        $this->rows[] = $row;

        $this->merges[] = [$this->current_row, 1, $this->current_row, $total_cols];

        $this->current_row++;

    }

    public function addEmptyRow() {

        $this->rows[] = [];

        $this->current_row++;

    }

    public function addHeaderRow1($cells) {

        $this->rows[] = $cells;

        // Calcular merges para células com merge > 1

        $col = 1;

        foreach ($cells as $cell) {

            $m = $cell['merge'] ?? 1;

            if ($m > 1) {

                $this->merges[] = [$this->current_row, $col, $this->current_row, $col+$m-1];

            }

            $col += $m;

        }

        $this->current_row++;

    }

    public function addRow($cells) {

        $this->rows[] = $cells;

        $this->current_row++;

    }

    public function setColWidths($widths) {

        $this->col_widths = $widths;

    }

    private function getSharedString($s) {

        $s = (string)$s;

        if (!isset($this->ss_index[$s])) {

            $this->ss_index[$s] = count($this->shared_strings);

            $this->shared_strings[] = $s;

        }

        return $this->ss_index[$s];

    }

    private function colLetter($n) {

        $s = '';

        while ($n > 0) {

            $n--; $s = chr(65+($n%26)).$s; $n = (int)($n/26);

        }

        return $s;

    }

    private function buildStylesXml() {

        // Indexar estilos únicos

        $used = [];

        foreach ($this->rows as $row) {

            foreach ($row as $cell) {

                $s = $cell['style'] ?? 'ctr';

                $used[$s] = true;

            }

        }

        $fonts = []; $fills = []; $borders = []; $cellXfs = [];

        $font_idx = []; $fill_idx = []; $border_idx = []; $xf_idx = [];

        // Font padrão

        $fonts[] = '<font><sz val="10"/><name val="Arial"/><color rgb="FF000000"/></font>';

        $fills[] = '<fill><patternFill patternType="none"/></fill>';

        $fills[] = '<fill><patternFill patternType="gray125"/></fill>'; // obrigatório

        $borders[] = '<border><left/><right/><top/><bottom/><diagonal/></border>';

        $cellXfs[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>';

        $style_to_xf = [];

        $fi = 1; $fli = 2; $bi = 1; $xi = 1;

        foreach (array_keys($used) as $sname) {

            $def = $this->style_defs[$sname] ?? $this->style_defs['ctr'];

            $bold = !empty($def['bold']) ? '<b/>' : '';

            $sz   = $def['sz'] ?? 10;

            $fg   = 'FF'.strtoupper($def['fg']??'000000');

            $bg   = 'FF'.strtoupper($def['bg']??'FFFFFF');

            $ha   = $def['ha'] ?? 'general';

            $font_xml = "<font>{$bold}<sz val=\"{$sz}\"/><name val=\"Arial\"/><color rgb=\"{$fg}\"/></font>";

            if (!isset($font_idx[$font_xml])) { $font_idx[$font_xml]=$fi++; $fonts[]=$font_xml; }

            $fid = $font_idx[$font_xml];

            $fill_xml = "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"{$bg}\"/></patternFill></fill>";

            if (!isset($fill_idx[$fill_xml])) { $fill_idx[$fill_xml]=$fli++; $fills[]=$fill_xml; }

            $flid = $fill_idx[$fill_xml];

            $bdr = '<border><left style="thin"><color rgb="FF888888"/></left><right style="thin"><color rgb="FF888888"/></right><top style="thin"><color rgb="FF888888"/></top><bottom style="thin"><color rgb="FF888888"/></bottom><diagonal/></border>';

            if (!isset($border_idx[$bdr])) { $border_idx[$bdr]=$bi++; $borders[]=$bdr; }

            $bdid = $border_idx[$bdr];

            $xf = "<xf numFmtId=\"0\" fontId=\"{$fid}\" fillId=\"{$flid}\" borderId=\"{$bdid}\" applyFont=\"1\" applyFill=\"1\" applyBorder=\"1\" applyAlignment=\"1\"><alignment horizontal=\"{$ha}\" vertical=\"center\" wrapText=\"0\"/></xf>";

            $style_to_xf[$sname] = $xi;

            $cellXfs[] = $xf; $xi++;

        }

        $this->styles = $style_to_xf;

        $fontXml  = implode('', $fonts);

        $fillXml  = implode('', $fills);

        $bdrXml   = implode('', $borders);

        $xfXml    = implode('', $cellXfs);

        $nf = count($fonts); $nfl = count($fills); $nb = count($borders); $nx = count($cellXfs);

        return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>

<styleSheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\">

<fonts count=\"{$nf}\">{$fontXml}</fonts>

<fills count=\"{$nfl}\">{$fillXml}</fills>

<borders count=\"{$nb}\">{$bdrXml}</borders>

<cellStyleXfs count=\"1\"><xf numFmtId=\"0\" fontId=\"0\" fillId=\"0\" borderId=\"0\"/></cellStyleXfs>

<cellXfs count=\"{$nx}\">{$xfXml}</cellXfs>

</styleSheet>";

    }

    private function buildSheetXml() {

        $this->buildStylesXml(); // garante que $this->styles está preenchido

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";

        $xml .= "<worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\">\n";

        // Larguras

        if (!empty($this->col_widths)) {

            $xml .= "<cols>\n";

            foreach ($this->col_widths as $i => $w) {

                $c = $i+1;

                $xml .= "<col min=\"{$c}\" max=\"{$c}\" width=\"{$w}\" customWidth=\"1\"/>\n";

            }

            $xml .= "</cols>\n";

        }

        $xml .= "<sheetData>\n";

        foreach ($this->rows as $ri => $row) {

            $rn = $ri+1;

            if (empty($row)) { $xml .= "<row r=\"{$rn}\"/>\n"; continue; }

            $xml .= "<row r=\"{$rn}\" customHeight=\"1\" ht=\"18\">\n";

            $col = 1;

            foreach ($row as $cell) {

                $cn   = $this->colLetter($col).$rn;

                $sname= $cell['style'] ?? 'ctr';

                $xfid = $this->styles[$sname] ?? 0;

                $type = $cell['t'] ?? 's';

                $val  = $cell['v'] ?? '';

                $m    = $cell['merge'] ?? 1;

                if ($val === '' || $val === null) {

                    $xml .= "<c r=\"{$cn}\" s=\"{$xfid}\"/>";

                } elseif ($type === 'n' && is_numeric($val)) {

                    $xml .= "<c r=\"{$cn}\" t=\"n\" s=\"{$xfid}\"><v>".htmlspecialchars((string)$val,ENT_XML1)."</v></c>";

                } else {

                    $sid = $this->getSharedString((string)$val);

                    $xml .= "<c r=\"{$cn}\" t=\"s\" s=\"{$xfid}\"><v>{$sid}</v></c>";

                }

                $col += $m;

            }

            $xml .= "\n</row>\n";

        }

        $xml .= "</sheetData>\n";

        // Merges

        if (!empty($this->merges)) {

            $xml .= "<mergeCells count=\"".count($this->merges)."\">\n";

            foreach ($this->merges as $mg) {

                list($r1,$c1,$r2,$c2) = $mg;

                $from = $this->colLetter($c1).$r1;

                $to   = $this->colLetter($c2).$r2;

                $xml .= "<mergeCell ref=\"{$from}:{$to}\"/>\n";

            }

            $xml .= "</mergeCells>\n";

        }

        $xml .= "</worksheet>";

        return $xml;

    }

    private function buildSharedStringsXml() {

        $count = count($this->shared_strings);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n";

        $xml .= "<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$count}\" uniqueCount=\"{$count}\">\n";

        foreach ($this->shared_strings as $s) {

            $xml .= "<si><t xml:space=\"preserve\">".htmlspecialchars($s,ENT_XML1,'UTF-8')."</t></si>\n";

        }

        $xml .= "</sst>";

        return $xml;

    }

    public function download($filename) {

        $stylesXml        = $this->buildStylesXml();

        $sheetXml         = $this->buildSheetXml(); // deve vir depois de buildStylesXml

        $sharedStringsXml = $this->buildSharedStringsXml();

        $rels_wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>

<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">

<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>

<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>

<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>

</Relationships>';

        $rels_root = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>

<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">

<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>

</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>

<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"

          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">

<sheets><sheet name="Pauta" sheetId="1" r:id="rId1"/></sheets>

</workbook>';

        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>

<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">

<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>

<Default Extension="xml"  ContentType="application/xml"/>

<Override PartName="/xl/workbook.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>

<Override PartName="/xl/worksheets/sheet1.xml"     ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>

<Override PartName="/xl/styles.xml"                ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>

<Override PartName="/xl/sharedStrings.xml"         ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>

</Types>';

        // Montar ZIP em memória

        $tmp = tempnam(sys_get_temp_dir(), 'sige_xlsx_');

        $zip = new ZipArchive();

        $zip->open($tmp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml',            $content_types);

        $zip->addFromString('_rels/.rels',                    $rels_root);

        $zip->addFromString('xl/workbook.xml',                $workbook);

        $zip->addFromString('xl/_rels/workbook.xml.rels',     $rels_wb);

        $zip->addFromString('xl/worksheets/sheet1.xml',       $sheetXml);

        $zip->addFromString('xl/styles.xml',                  $stylesXml);

        $zip->addFromString('xl/sharedStrings.xml',           $sharedStringsXml);

        $zip->close();

        // Enviar

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        header('Content-Disposition: attachment; filename="'.rawurlencode($filename).'"');

        header('Content-Length: '.filesize($tmp));

        header('Cache-Control: max-age=0');

        readfile($tmp);

        unlink($tmp);

        exit;

    }

}
