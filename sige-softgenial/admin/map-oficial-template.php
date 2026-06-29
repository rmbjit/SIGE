<?php
/**
 * SIGE SoftGenial - MAP Oficial (Template HTML/CSS)
 * Ficheiro: admin/map-oficial-template.php
 *
 * Sprint 2 · M1 - Imprimível A4 estilo MAPED/A25 (Colégio Malisa).
 *
 * Fonte dos dados: $data (definido em includes/map-pdf-handler.php)
 *   [aluno, matricula, ano, classe, turma_nome, turno, numero_chamada,
 *    linhas, situacao, media_final, media_final_ext, negativas,
 *    eh_fim_ciclo, exige_exame, escola_nome, logo_url, endereco, email_escola]
 *
 * Cada $data['linhas'][$i] contém:
 *   [id, nome, sigla, categoria, mt1, mt2, mt3, mfd, exame, af, escala]
 *
 * @since 12.1
 */

if (!defined('ABSPATH')) exit;

// Helper local de formatação (exibe inteiro ou vazio)
if (!function_exists('sige_map_fmt')) {
    function sige_map_fmt($v) {
        if ($v === null || $v === '') return '';
        return (string) (int) round((float) $v);
    }
}

// Ordem SNE das linhas a mostrar na tabela (sigla → label institucional Malisa)
// Reordenadas/renomeadas para coincidirem com o documento oficial.
$label_map = [
    'POR'        => 'Português',
    'MAT'        => 'Matemática',
    'MAT(4-6)'   => 'Matemática',
    'CS'         => 'C. Sociais',
    'CN'         => 'C. Naturais',
    'ING'        => 'Inglês',
    'ED.VISUAL'  => 'Ed. Visual',
    'ED. V'      => 'Ed. Visual',
    'OF'         => 'Ofícios',
    'ED. FISICA' => 'Ed. Física',
    'ED.FISICA'  => 'Ed. Física',
];


// Dados em modo individual ou em massa.
$data_pages = (isset($data_pages) && is_array($data_pages) && !empty($data_pages)) ? array_values($data_pages) : [$data];
$first_data = $data_pages[0];
$map_batch = !empty($map_batch) || count($data_pages) > 1;
$map_title = isset($map_batch_title) && $map_batch_title !== ''
    ? (string) $map_batch_title
    : ('MAP - ' . (string) ($first_data['aluno']->nome_completo ?? '') . ' - ' . (int) ($first_data['ano'] ?? 0));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html($map_title); ?></title>
<style>
/* ═══════════════════════════════════════════════════════════════════
   RESET + BASE
   ═══════════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 10.5pt;
    line-height: 1.35;
    color: #000;
    background: #e5e5e5;
}

/* Página A4 */
.page {
    page-break-after: always;
    width: 210mm;
    min-height: 297mm;
    margin: 10mm auto;
    padding: 10mm 12mm;
    background: #fff;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}

/* ═══════════════════════════════════════════════════════════════════
   CABEÇALHO (3 zonas: badge esquerda | escola centro | assinaturas dir.)
   ═══════════════════════════════════════════════════════════════════ */
.topbar {
    display: table;
    width: 100%;
    table-layout: fixed;
    margin-bottom: 4mm;
}
.topbar > div { display: table-cell; vertical-align: top; }
.topbar-left   { width: 30%; padding-right: 3mm; }
.topbar-center { width: 40%; text-align: center; padding: 0 2mm; }
.topbar-right  { width: 30%; padding-left: 3mm; }

/* Caixa MAPED/A25 (esquerda) */
.badge-maped {
    border: 1.2pt solid #000;
    padding: 4mm 3mm;
    text-align: left;
    min-height: 28mm;
}
.badge-maped .code {
    font-weight: 700;
    text-decoration: underline;
    font-size: 11pt;
    margin-bottom: 2mm;
}
.badge-maped .title {
    font-weight: 700;
    font-size: 11pt;
    line-height: 1.25;
}

/* Escola (centro) */
.escola-logo {
    max-width: 18mm;
    max-height: 18mm;
    object-fit: contain;
    margin-bottom: 1mm;
}
.escola-nome {
    font-weight: 700;
    font-size: 12pt;
    letter-spacing: .3pt;
    margin-top: 1mm;
}
.escola-end {
    font-size: 8.5pt;
    line-height: 1.3;
    margin-top: 1mm;
}
.escola-email {
    font-size: 8.5pt;
    margin-top: .5mm;
}

/* Caixa assinaturas (direita) */
.sig-box {
    border: 1.2pt dashed #000;
    padding: 4mm 3mm;
    min-height: 35mm;
}
.sig-slot { margin-bottom: 7mm; text-align: left; }
.sig-slot:last-child { margin-bottom: 0; }
.sig-slot .role {
    font-size: 9pt;
    margin-bottom: 5mm;
}
.sig-line {
    border-top: .6pt solid #000;
    width: 85%;
    margin: 0 auto 0 0;
}

/* ═══════════════════════════════════════════════════════════════════
   DIVISOR PONTILHADO VERMELHO
   ═══════════════════════════════════════════════════════════════════ */
.dotline {
    border: none;
    border-top: 1pt dotted #d93b3b;
    margin: 3mm 0;
}

/* ═══════════════════════════════════════════════════════════════════
   CABEÇALHO DO ALUNO
   ═══════════════════════════════════════════════════════════════════ */
.aluno-head {
    display: table;
    width: 100%;
    table-layout: fixed;
    font-size: 10.5pt;
    margin: 2mm 0;
}
.aluno-head > div { display: table-cell; padding: 1mm 0; vertical-align: bottom; white-space: nowrap; }
.field-lbl { font-weight: 400; }
.field-val {
    display: inline-block;
    border-bottom: .6pt solid #000;
    min-width: 20mm;
    padding: 0 2mm;
    text-align: center;
    font-weight: 700;
}
.field-val.small   { min-width: 12mm; }
.field-val.narrow  { min-width: 16mm; }
.aluno-nome-row {
    margin-top: 3mm;
    font-size: 11pt;
    white-space: nowrap;
}
.aluno-nome-row .field-val {
    min-width: 150mm;
    text-align: left;
    padding-left: 2mm;
}

/* ═══════════════════════════════════════════════════════════════════
   SECÇÃO "APROVEITAMENTO ANUAL"
   ═══════════════════════════════════════════════════════════════════ */
.section-title {
    font-weight: 700;
    font-size: 12pt;
    margin: 4mm 0 2mm 0;
    padding-left: 5mm;
    position: relative;
}
.section-title::before {
    content: '';
    position: absolute;
    left: 0;
    top: 55%;
    transform: translateY(-50%);
    width: 3mm;
    height: 3mm;
    background: #000;
}

/* Layout 2 colunas: tabela (esq) + caixas trimestre (dir) */
.anual-wrap {
    display: table;
    width: 100%;
    table-layout: fixed;
}
.anual-wrap > div { display: table-cell; vertical-align: top; }
.anual-left  { width: 70%; padding-right: 4mm; }
.anual-right { width: 30%; }

/* Tabela de notas */
table.ap-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10pt;
}
.ap-table th, .ap-table td {
    border: .8pt solid #000;
    padding: 1.8mm 1.5mm;
    text-align: center;
    vertical-align: middle;
    height: 6.5mm;
}
.ap-table th {
    font-weight: 700;
    background: #fff;
}
.ap-table td.td-disc {
    text-align: left;
    padding-left: 2.5mm;
    font-weight: 400;
}
.ap-table .group-hdr { font-weight: 700; }

/* Caixas trimestre laterais */
.tri-box {
    border: 1pt dashed #000;
    padding: 3mm;
    margin-bottom: 3mm;
    min-height: 22mm;
}
.tri-box .tri-t {
    font-weight: 700;
    text-align: center;
    margin-bottom: 3mm;
    font-size: 11pt;
}
.tri-box .tri-role {
    font-size: 9pt;
    margin-bottom: 5mm;
    text-align: center;
}
.tri-box .tri-line {
    border-top: .6pt solid #000;
    width: 90%;
    margin: 0 auto;
}

/* ═══════════════════════════════════════════════════════════════════
   RESULTADO FINAL
   ═══════════════════════════════════════════════════════════════════ */
.resultado-block {
    margin-top: 4mm;
}
.line-row {
    margin: 4mm 0;
    font-size: 11pt;
}
.line-row .lbl { font-weight: 700; }
.line-inline {
    display: inline-block;
    border-bottom: .6pt solid #000;
    padding: 0 2mm;
    min-height: 5mm;
    font-weight: 700;
}
.line-inline.small { min-width: 20mm; text-align: center; }
.line-inline.ext   { min-width: 105mm; }
.line-inline.sit   { min-width: 100mm; }
.line-inline.obs   {
    display: block;
    border-bottom: .6pt solid #000;
    min-height: 5mm;
    margin-bottom: 2.5mm;
    font-weight: 400;
}

/* ═══════════════════════════════════════════════════════════════════
   BARRA DE IMPRESSÃO (oculta em print)
   ═══════════════════════════════════════════════════════════════════ */
.print-bar {
    position: fixed;
    top: 10px; right: 10px;
    z-index: 9999;
    background: #1a5276;
    color: #fff;
    padding: 8px 14px;
    border-radius: 6px;
    font-family: Arial, sans-serif;
    font-size: 11pt;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,.3);
}
.print-bar:hover { background: #154563; }
.page:last-child { page-break-after: auto; }

/* ═══════════════════════════════════════════════════════════════════
   PRINT
   ═══════════════════════════════════════════════════════════════════ */
@media print {
    html, body { background: #fff; }
    .page {
        page-break-after: always;
        margin: 0;
        box-shadow: none;
        padding: 8mm 10mm;
    }
    .print-bar { display: none !important; }
    @page {
        size: A4 portrait;
        margin: 0;
    }
}
</style>
</head>
<body>

<button class="print-bar" data-sige-act="sigeImprimirPagina" data-sige-noargs type="button">🖨 <?php echo $map_batch ? 'Imprimir todos' : 'Imprimir'; ?></button>

<?php foreach ($data_pages as $data):
    $linhas_disc = [];
    foreach ((array) ($data['linhas'] ?? []) as $l) {
        $sig = (string) ($l['sigla'] ?? '');
        $l['display_nome'] = $label_map[$sig] ?? (string) ($l['nome'] ?? '');
        $linhas_disc[] = $l;
    }
    $linhas_disc[] = [
        'id' => 0,
        'display_nome' => 'Comportamento',
        'mt1' => null, 'mt2' => null, 'mt3' => null,
        'mfd' => null, 'af' => null, 'nota_final_mapa' => null, 'escala' => '',
        'sintetica' => true,
    ];

    $aluno_nome   = (string) ($data['aluno']->nome_completo ?? '');
    $classe_num   = preg_match('/\d+/', (string) ($data['classe'] ?? ''), $mm) ? $mm[0] : (string) ($data['classe'] ?? '');
    $turma_lbl    = (string) ($data['turma_nome'] ?? '');
    $ano_2d       = substr((string) ($data['ano'] ?? ''), -2);
    $nr_chamada   = $data['numero_chamada'] ?? '';
    $med_num      = $data['media_final'] ?? null;
    $med_ext      = (string) ($data['media_final_ext'] ?? '');
    $situacao_txt = (string) ($data['situacao'] ?? '');
    $escola_upper = mb_strtoupper((string) ($data['escola_nome'] ?? ''));
    $map_final_col_label = (string) ($data['label_mapa_final'] ?? ((int)$classe_num === 3 ? 'AF' : 'MFD'));
?>
<div class="page">

    <!-- ═══ CABEÇALHO ═══ -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="badge-maped">
                <div class="code">MAPED/A<?php echo esc_html($ano_2d); ?></div>
                <div class="title">MAPA DE APROVEITAMENTO PEDAGÓGICO</div>
            </div>
        </div>
        <div class="topbar-center">
            <?php if (!empty($data['logo_url'])): ?>
                <img src="<?php echo esc_url($data['logo_url']); ?>" class="escola-logo" alt="Logo">
            <?php endif; ?>
            <div class="escola-nome"><?php echo esc_html($escola_upper); ?></div>
            <?php if (!empty($data['endereco'])): ?>
                <div class="escola-end"><?php echo esc_html($data['endereco']); ?></div>
            <?php endif; ?>
            <?php if (!empty($data['email_escola'])): ?>
                <div class="escola-email">Email.: <?php echo esc_html($data['email_escola']); ?></div>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <div class="sig-box">
                <div class="sig-slot">
                    <div class="role">O (A) Director (a) Adjunto (a)</div>
                    <div class="sig-line"></div>
                </div>
                <div class="sig-slot">
                    <div class="role">O (A) Director (a) de Turma</div>
                    <div class="sig-line"></div>
                </div>
            </div>
        </div>
    </div>

    <hr class="dotline">

    <!-- ═══ CABEÇALHO DO ALUNO ═══ -->
    <div class="aluno-head">
        <div>
            Ano Lectivo 20<span class="field-val small"><?php echo esc_html($ano_2d); ?></span>
        </div>
        <div>
            <span class="field-val small"><?php echo esc_html($classe_num); ?></span><sup>ª</sup> Classe
        </div>
        <div>
            Turma <span class="field-val narrow"><?php echo esc_html($turma_lbl); ?></span>
        </div>
        <div>
            N° <span class="field-val small"><?php echo esc_html($nr_chamada); ?></span>
        </div>
    </div>

    <div class="aluno-nome-row">
        Nome do (a) Aluno (a) <span class="field-val"><?php echo esc_html($aluno_nome); ?></span>
    </div>

    <hr class="dotline">

    <!-- ═══ APROVEITAMENTO ANUAL ═══ -->
    <div class="section-title">Aproveitamento Anual</div>

    <div class="anual-wrap">
        <div class="anual-left">
            <table class="ap-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 32%;">Disciplina</th>
                        <th colspan="3" class="group-hdr">Média</th>
                        <th rowspan="2" style="width: 10%;"><?php echo esc_html($map_final_col_label); ?></th>
                        <th rowspan="2" style="width: 16%;">Frequência</th>
                    </tr>
                    <tr>
                        <th style="width: 14%;">I Trimestre</th>
                        <th style="width: 14%;">II Trimestre</th>
                        <th style="width: 14%;">III Trimestre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($linhas_disc as $l): ?>
                    <tr>
                        <td class="td-disc"><?php echo esc_html($l['display_nome']); ?></td>
                        <td><?php echo esc_html(sige_map_fmt($l['mt1'] ?? null)); ?></td>
                        <td><?php echo esc_html(sige_map_fmt($l['mt2'] ?? null)); ?></td>
                        <td><?php echo esc_html(sige_map_fmt($l['mt3'] ?? null)); ?></td>
                        <td><?php echo esc_html(sige_map_fmt($l['nota_final_mapa'] ?? ($l['af'] ?? null))); ?></td>
                        <td></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="anual-right">
            <div class="tri-box">
                <div class="tri-t">I Trimestre</div>
                <div class="tri-role">O (A) Encarregado (a) de Educação</div>
                <div class="tri-line"></div>
            </div>
            <div class="tri-box">
                <div class="tri-t">II Trimestre</div>
                <div class="tri-role">O (A) Encarregado (a) de Educação</div>
                <div class="tri-line"></div>
            </div>
            <div class="tri-box">
                <div class="tri-t">III Trimestre</div>
                <div class="tri-role">O (A) Encarregado (a) de Educação</div>
                <div class="tri-line"></div>
            </div>
        </div>
    </div>

    <hr class="dotline">

    <!-- ═══ RESULTADO FINAL ═══ -->
    <div class="section-title">Resultado Final</div>

    <div class="resultado-block">
        <div class="line-row">
            <span class="lbl">Média Final:</span>
            <span class="line-inline small"><?php echo esc_html(sige_map_fmt($med_num)); ?></span>
            &nbsp;(<span class="line-inline ext"><?php echo esc_html($med_ext); ?></span>)
            <em>Valores</em>
        </div>

        <div class="line-row">
            <span class="lbl">Situação Final:</span>
            <span class="line-inline sit"><?php echo esc_html($situacao_txt); ?></span>
        </div>

        <div class="line-row">
            <div class="lbl" style="margin-bottom: 2mm;">Observação:</div>
            <div class="line-inline obs">&nbsp;</div>
            <div class="line-inline obs">&nbsp;</div>
            <div class="line-inline obs">&nbsp;</div>
        </div>
    </div>

</div>
<?php endforeach; ?>
</body>
</html>
