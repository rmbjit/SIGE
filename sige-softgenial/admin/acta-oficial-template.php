<?php
/**
 * SIGE SoftGenial - ACTA do Conselho de Notas (Template HTML/CSS)
 * Ficheiro: admin/acta-oficial-template.php
 *
 * Sprint 2 · M2 - Imprimível A4 estilo ACN/A25 (Colégio Malisa).
 *
 * Fonte dos dados: $data (definido em includes/acta-pdf-handler.php)
 *   [acta, turma, ano, trimestre, trimestre_ordinal, classe_num,
 *    turma_nome, presentes, ausentes, nota_votada, cumprimento,
 *    stats_demo, stats_disciplina, quadro_honra, escola_nome,
 *    logo_url, endereco, email_escola]
 *
 * @since 12.2.0
 */

if (!defined('ABSPATH')) exit;

// Helper de formatação inteira (idêntico ao MAP)
if (!function_exists('sige_acta_fmt')) {
    function sige_acta_fmt($v) {
        if ($v === null || $v === '') return '';
        return (string) (int) round((float) $v);
    }
}
if (!function_exists('sige_acta_fmt_nota')) {
    function sige_acta_fmt_nota($v) {
        if ($v === null || $v === '') return '';
        $f = (float) $v;
        return rtrim(rtrim(number_format($f, 1, ',', ''), '0'), ',');
    }
}

// Extracção segura de variáveis
$acta          = $data['acta']              ?? null;
$turma         = $data['turma']             ?? null;
$ano           = (int)   ($data['ano']               ?? 0);
$trimestre     = (int)   ($data['trimestre']         ?? 0);
$trim_ord      = (string)($data['trimestre_ordinal'] ?? '');
$classe_num    = (int)   ($data['classe_num']        ?? 0);
$turma_nome    = (string)($data['turma_nome']        ?? '');
$presentes     = (array) ($data['presentes']         ?? []);
$ausentes      = (array) ($data['ausentes']          ?? []);
$nota_votada   = (array) ($data['nota_votada']       ?? []);
$cumprimento   = (array) ($data['cumprimento']       ?? []);
$disciplinas_turma = (array) ($data['disciplinas_turma'] ?? []);
$stats_demo    = (array) ($data['stats_demo']        ?? []);
$stats_disc    = (array) ($data['stats_disciplina']  ?? []);
$quadro_honra  = (array) ($data['quadro_honra']      ?? []);
$escola_nome   = (string)($data['escola_nome']       ?? '');
$logo_url      = (string)($data['logo_url']          ?? '');
$endereco      = (string)($data['endereco']          ?? '');
$email_escola  = (string)($data['email_escola']      ?? '');
$ano_2d        = substr((string)$ano, -2);
$escola_upper  = mb_strtoupper($escola_nome);

// Data do conselho - mostra-se por extenso
$data_dia = $data_mes = $data_ano = '';
if (!empty($acta->data_conselho)) {
    $ts = strtotime((string) $acta->data_conselho);
    if ($ts) {
        $data_dia = (string) (int) wp_date('j', $ts);
        $meses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                  'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $data_mes = $meses[(int) wp_date('n', $ts)] ?? '';
        $data_ano = substr((string) wp_date('Y', $ts), -2);
    }
}
$sala      = (string) ($acta->sala ?? '');
$hora      = !empty($acta->hora_inicio) ? substr((string)$acta->hora_inicio, 0, 5) : '';
$presidido = (string) ($acta->presidido_por ?? '');
$como_dec  = (string) ($acta->como_decorreu ?? '');
$obs_dp    = (string) ($acta->observacao_dp ?? '');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>ACTA - <?php echo (int) $classe_num; ?>ª <?php echo esc_html($turma_nome); ?> - <?php echo esc_html($trim_ord); ?>T - <?php echo (int) $ano; ?></title>
<style>
/* ═══════════════════════════════════════════════════════════════════
   RESET + BASE (idêntico ao MAP para consistência visual)
   ═══════════════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 10.5pt;
    line-height: 1.35;
    color: #000;
    background: #e5e5e5;
}

.page {
    width: 210mm;
    min-height: 297mm;
    margin: 10mm auto;
    padding: 10mm 12mm;
    background: #fff;
    position: relative;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    page-break-after: always;
}
.page:last-child { page-break-after: auto; }

/* ═══════════════════════════════════════════════════════════════════
   CABEÇALHO (3 zonas - badge ACN/A25 | escola | assinatura director turma)
   ═══════════════════════════════════════════════════════════════════ */
.topbar {
    display: table;
    width: 100%;
    table-layout: fixed;
    margin-bottom: 3mm;
}
.topbar > div { display: table-cell; vertical-align: top; }
.topbar-left   { width: 30%; padding-right: 3mm; }
.topbar-center { width: 40%; text-align: center; padding: 0 2mm; }
.topbar-right  { width: 30%; padding-left: 3mm; }

.badge-acn {
    border: 1.2pt solid #000;
    padding: 4mm 3mm;
    text-align: left;
    min-height: 22mm;
}
.badge-acn .code {
    font-weight: 700;
    text-decoration: underline;
    font-size: 11pt;
    margin-bottom: 2mm;
}
.badge-acn .title {
    font-weight: 700;
    font-size: 11pt;
    line-height: 1.25;
}

.escola-logo {
    max-width: 16mm;
    max-height: 16mm;
    object-fit: contain;
    margin-bottom: 1mm;
}
.escola-nome {
    font-weight: 700;
    font-size: 11.5pt;
    letter-spacing: .3pt;
    margin-top: 1mm;
}
.escola-end {
    font-size: 8pt;
    line-height: 1.3;
    margin-top: 1mm;
}
.escola-email {
    font-size: 8pt;
    margin-top: .5mm;
}

.sig-box {
    border: 1.2pt dashed #000;
    padding: 4mm 3mm;
    min-height: 22mm;
}
.sig-slot { text-align: center; }
.sig-slot .role {
    font-size: 9pt;
    margin-bottom: 6mm;
}
.sig-line {
    border-top: .6pt solid #000;
    width: 85%;
    margin: 0 auto;
}

/* ═══════════════════════════════════════════════════════════════════
   DIVISOR PONTILHADO (idêntico ao MAP)
   ═══════════════════════════════════════════════════════════════════ */
.dotline {
    border: none;
    border-top: 1pt dotted #d93b3b;
    margin: 2.5mm 0;
}

/* ═══════════════════════════════════════════════════════════════════
   PARÁGRAFO INTRODUTÓRIO (Aos __ de __ ...)
   ═══════════════════════════════════════════════════════════════════ */
.intro {
    border: 1pt dashed #000;
    padding: 3mm 4mm;
    font-size: 10.5pt;
    line-height: 1.9;
    margin: 2mm 0 3mm 0;
}
.intro .val {
    display: inline-block;
    border-bottom: .6pt solid #000;
    min-width: 12mm;
    padding: 0 2mm;
    text-align: center;
    font-weight: 700;
}
.intro .val.wide  { min-width: 40mm; }
.intro .val.vwide { min-width: 80mm; }

/* ═══════════════════════════════════════════════════════════════════
   BARRA DE SECÇÃO (ACN/A25 - cinza com texto bold)
   ═══════════════════════════════════════════════════════════════════ */
.acta-bar {
    display: inline-block;
    background: #b4b4b4;
    color: #000;
    font-weight: 700;
    font-size: 11pt;
    padding: 1.5mm 6mm 1.5mm 4mm;
    margin: 3mm 0 2mm 0;
    min-width: 55mm;
    border: .4pt solid #999;
}
.acta-bar.right  { float: right; }
.acta-bar.left   { float: left; }
.bar-clear { clear: both; }

/* ═══════════════════════════════════════════════════════════════════
   TABELAS
   ═══════════════════════════════════════════════════════════════════ */
table.acta-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10pt;
    margin-bottom: 2mm;
    clear: both;
}
.acta-table th, .acta-table td {
    border: .8pt solid #000;
    padding: 1.5mm 1.5mm;
    text-align: center;
    vertical-align: middle;
    height: 6mm;
}
.acta-table th { font-weight: 700; background: #fff; }
.acta-table td.txt-left {
    text-align: left;
    padding-left: 2mm;
}
.acta-table tbody tr { height: 6mm; }
.acta-table.small { font-size: 9pt; }
.acta-table.small th, .acta-table.small td { padding: 1mm; }

/* Tabela de estatísticas por disciplina (mais estreita) */
.disc-table th.disc-h {
    font-size: 8.5pt;
    line-height: 1.1;
    padding: 1mm;
}

/* ═══════════════════════════════════════════════════════════════════
   BLOCO "Como decorreu"
   ═══════════════════════════════════════════════════════════════════ */
.como-bloco {
    border: .8pt solid #000;
    padding: 3mm 4mm;
    min-height: 25mm;
    margin-top: 2mm;
    font-size: 10pt;
    line-height: 1.8;
}
.como-bloco.with-lines {
    background: repeating-linear-gradient(
        to bottom,
        #fff 0,
        #fff 5.8mm,
        #000 5.8mm,
        #000 5.85mm
    );
    padding-top: 2mm;
}

.obs-dp-bloco {
    border: .8pt solid #000;
    padding: 2.5mm 3mm;
    min-height: 12mm;
    font-size: 9.5pt;
    margin-top: 2mm;
    background: #fafafa;
}
.obs-dp-bloco .lbl {
    font-weight: 700;
    display: block;
    margin-bottom: 1mm;
    font-size: 9pt;
}

/* ═══════════════════════════════════════════════════════════════════
   BARRA DE IMPRESSÃO
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

/* ═══════════════════════════════════════════════════════════════════
   PRINT
   ═══════════════════════════════════════════════════════════════════ */
@media print {
    html, body { background: #fff; }
    .page {
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

/* v12.10.80 - ACTA: paginação limpa e fontes de dados visíveis */
.page > div,
table.acta-table,
.como-bloco,
.obs-dp-bloco {
    break-inside: avoid;
    page-break-inside: avoid;
}
.page {
    overflow: visible;
}
.auto-note {
    font-size: 8.3pt;
    color: #444;
    line-height: 1.35;
    margin: 1.5mm 0 2mm 0;
    padding: 1.5mm 2mm;
    border: .6pt solid #ccc;
    background: #f7f7f7;
}
.auto-note strong { font-weight: 700; }
.muted-row td {
    color: #555;
    font-style: italic;
}
@media print {
    .page {
        break-after: page;
        page-break-after: always;
        min-height: auto;
        height: auto;
        overflow: visible;
    }
    .page:last-child {
        break-after: auto;
        page-break-after: auto;
    }
    .page > div,
    table.acta-table,
    .como-bloco,
    .obs-dp-bloco {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}

</style>
</head>
<body>

<button class="print-bar" data-sige-act="sigeImprimirPagina" data-sige-noargs type="button">🖨 Imprimir</button>

<?php
// Garantir número mínimo de linhas em cada tabela para manter o layout
$min_pres     = max(1, count($presentes));
$min_aus      = max(1, count($ausentes));
$min_nv       = max(1, count($nota_votada));
$min_qh       = max(1, count($quadro_honra));
?>

<div class="page">

    <!-- ═══ CABEÇALHO ═══ -->
    <div class="topbar">
        <div class="topbar-left">
            <div class="badge-acn">
                <div class="code">ACN/A<?php echo esc_html($ano_2d); ?></div>
                <div class="title">ACTA DO CONSELHO DE NOTAS<br>Ensino Básico / <?php echo esc_html($trim_ord); ?> Trimestre</div>
            </div>
        </div>
        <div class="topbar-center">
            <?php if (!empty($logo_url)): ?>
                <img src="<?php echo esc_url($logo_url); ?>" class="escola-logo" alt="Logo">
            <?php endif; ?>
            <div class="escola-nome"><?php echo esc_html($escola_upper); ?></div>
            <?php if (!empty($endereco)): ?>
                <div class="escola-end"><?php echo esc_html($endereco); ?></div>
            <?php endif; ?>
            <?php if (!empty($email_escola)): ?>
                <div class="escola-email">Email.: <?php echo esc_html($email_escola); ?></div>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <div class="sig-box">
                <div class="sig-slot">
                    <div class="role">O (A) Director (a) de Turma</div>
                    <div class="sig-line"></div>
                </div>
            </div>
        </div>
    </div>

    <hr class="dotline">

    <!-- ═══ PARÁGRAFO INTRODUTÓRIO ═══ -->
    <div class="intro">
        Aos <span class="val"><?php echo esc_html($data_dia); ?></span> de
        <span class="val wide"><?php echo esc_html($data_mes); ?></span> de
        20<span class="val"><?php echo esc_html($data_ano); ?></span>,
        na sala <span class="val"><?php echo esc_html($sala); ?></span>,
        pelas <span class="val"><?php echo esc_html($hora); ?></span> horas,
        realizou-se o Conselho de Notas da
        <span class="val"><?php echo $classe_num > 0 ? (int) $classe_num : ''; ?>ª</span> Classe,
        Turma <span class="val"><?php echo esc_html($turma_nome); ?></span>,
        presidido pelo(a) <span class="val vwide"><?php echo esc_html($presidido); ?></span>
    </div>

    <!-- ═══ ESTIVERAM PRESENTES ═══ -->
    <div>
        <div class="acta-bar right">Estiveram presentes</div>
        <div class="bar-clear"></div>
        <table class="acta-table small">
            <thead>
                <tr>
                    <th style="width: 38%;">Nome</th>
                    <th style="width: 20%;">Função</th>
                    <th style="width: 22%;">Disciplina</th>
                    <th style="width: 20%;">Assinatura</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < $min_pres; $i++):
                    $p = $presentes[$i] ?? null; ?>
                <tr>
                    <td class="txt-left"><?php echo $p ? esc_html($p->nome) : ''; ?></td>
                    <td class="txt-left"><?php echo $p ? esc_html($p->funcao) : ''; ?></td>
                    <td class="txt-left"><?php echo $p ? esc_html($p->disciplina_nome) : ''; ?></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- ═══ NOTA VOTADA ═══ -->
    <div>
        <div class="acta-bar left">Nota Votada</div>
        <div class="bar-clear"></div>
        <table class="acta-table small">
            <thead>
                <tr>
                    <th style="width: 7%;">N.°</th>
                    <th style="width: 33%;">Nome</th>
                    <th style="width: 12%;">Nota Inicial</th>
                    <th style="width: 12%;">Nota Final</th>
                    <th style="width: 36%;">Recomendações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($nota_votada)): ?>
                <tr class="muted-row">
                    <td class="txt-left" colspan="5">Sem notas votadas registadas para este conselho.</td>
                </tr>
                <?php else: for ($i = 0; $i < $min_nv; $i++):
                    $nv = $nota_votada[$i] ?? null; ?>
                <tr>
                    <td><?php echo $nv ? esc_html((string) ($nv->numero_chamada ?? '')) : ''; ?></td>
                    <td class="txt-left"><?php echo $nv ? esc_html((string) ($nv->nome_completo ?? '')) : ''; ?></td>
                    <td><?php echo $nv ? esc_html(sige_acta_fmt_nota($nv->nota_inicial)) : ''; ?></td>
                    <td><?php echo $nv ? esc_html(sige_acta_fmt_nota($nv->nota_final)) : ''; ?></td>
                    <td class="txt-left"><?php echo $nv ? esc_html((string) ($nv->recomendacoes ?? '')) : ''; ?></td>
                </tr>
                <?php endfor; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ═══ NÃO ESTIVERAM PRESENTES ═══ -->
    <div>
        <div class="acta-bar right">Não Estiveram Presentes</div>
        <div class="bar-clear"></div>
        <table class="acta-table small">
            <thead>
                <tr>
                    <th style="width: 38%;">Nome</th>
                    <th style="width: 20%;">Função</th>
                    <th style="width: 22%;">Disciplina</th>
                    <th style="width: 20%;">Assinatura</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ausentes)): ?>
                <tr class="muted-row">
                    <td class="txt-left" colspan="4">Sem ausências registadas nesta acta.</td>
                </tr>
                <?php else: for ($i = 0; $i < $min_aus; $i++):
                    $a = $ausentes[$i] ?? null; ?>
                <tr>
                    <td class="txt-left"><?php echo $a ? esc_html($a->nome) : ''; ?></td>
                    <td class="txt-left"><?php echo $a ? esc_html($a->funcao) : ''; ?></td>
                    <td class="txt-left"><?php echo $a ? esc_html($a->disciplina_nome) : ''; ?></td>
                    <td></td>
                </tr>
                <?php endfor; endif; ?>
            </tbody>
        </table>
    </div>



</div><!-- /page 1 -->

<div class="page">

    <!-- ═══ FIM DO TRIMESTRE - ESTATÍSTICA DEMOGRÁFICA (M/HM) ═══ -->
    <div>
        <div class="acta-bar left">Fim do Trimestre</div>
        <div class="bar-clear"></div>
        <table class="acta-table small">
            <thead>
                <tr>
                    <th style="width: 60%;">Estatística</th>
                    <th style="width: 20%;">M</th>
                    <th style="width: 20%;">HM</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $demo_rows = [
                    ['matriculados',              'Matriculados',              $stats_demo['matriculados']           ?? ['M'=>0,'HM'=>0]],
                    ['data_corte',                ($stats_demo['data_corte_label'] ?? '3 de Março'), $stats_demo['data_corte'] ?? ['M'=>0,'HM'=>0]],
                    ['desistencias',              'Desistências',              $stats_demo['desistencias']           ?? ['M'=>0,'HM'=>0]],
                    ['obitos',                    'Óbitos',                    $stats_demo['obitos']                 ?? ['M'=>0,'HM'=>0]],
                    ['transferidos_recebidos',    'Transferidos (Recebidos)',  $stats_demo['transferidos_recebidos'] ?? ['M'=>0,'HM'=>0]],
                    ['transferidos_enviados',     'Transferidos (Enviados)',   $stats_demo['transferidos_enviados']  ?? ['M'=>0,'HM'=>0]],
                    ['existentes',                'Existentes (Avaliados)',    $stats_demo['existentes']             ?? ['M'=>0,'HM'=>0]],
                    ['situacao_positiva',         'Situação Positiva (+)',     $stats_demo['situacao_positiva']      ?? ['M'=>0,'HM'=>0]],
                    ['percentagem',               'Percentagem (%)',           $stats_demo['percentagem']            ?? ['M'=>'','HM'=>'']],
                ];
                $unsupported_status = isset($stats_demo['status_ext_suportado']) && !$stats_demo['status_ext_suportado'];
                $status_keys = ['desistencias','obitos','transferidos_recebidos','transferidos_enviados'];
                foreach ($demo_rows as $row):
                    [$key, $lbl, $vals] = $row;
                    $is_status_extra = in_array($key, $status_keys, true);
                    $m_val  = ($unsupported_status && $is_status_extra) ? '-' : (string) ($vals['M']  ?? '');
                    $hm_val = ($unsupported_status && $is_status_extra) ? '-' : (string) ($vals['HM'] ?? '');
                ?>
                <tr>
                    <td class="txt-left"><?php echo esc_html($lbl); ?></td>
                    <td><?php echo esc_html($m_val); ?></td>
                    <td><?php echo esc_html($hm_val); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="auto-note">
            <strong>Fonte oficial:</strong> a linha <?php echo esc_html($stats_demo['data_corte_label'] ?? '3 de Março'); ?> representa o snapshot confirmado pela escola para o Censo Escolar oficial de Moçambique, não a data de registo no sistema.
            <?php if (empty($stats_demo['data_corte_confirmado'])): ?>
                Como ainda não existe snapshot confirmado, a ACTA mostra “-” nessa linha para evitar estatística falsa.
            <?php else: ?>
                Snapshot confirmado em <?php echo esc_html((string)($stats_demo['data_corte_confirmado_em'] ?? '')); ?><?php if (!empty($stats_demo['data_corte_observacao'])): ?> · <?php echo esc_html((string)$stats_demo['data_corte_observacao']); ?><?php endif; ?>.
            <?php endif; ?>
            <?php if (!empty($stats_demo['data_corte_stale'])): ?>
                <br><strong style="color:#b91c1c;">⚠ Atenção:</strong> este snapshot de Censo foi confirmado antes da correcção da estatística por género. Para o mesmo total HM, o cálculo corrigido indica <strong>M = <?php echo esc_html((string)($stats_demo['data_corte_sug_now']['M'] ?? '')); ?></strong> Mulheres (o snapshot tem M = <?php echo esc_html((string)($stats_demo['data_corte']['M'] ?? '')); ?>). Reconfirme o Censo desta turma no módulo ACTA para actualizar o valor oficial.
            <?php endif; ?>
            <?php if (isset($stats_demo['status_ext_suportado']) && !$stats_demo['status_ext_suportado']): ?>
                Desistências, óbitos e transferências aparecem como “-” porque a instalação ainda não tem estados de matrícula parametrizados para esses eventos.
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ FIM DO TRIMESTRE - ESTATÍSTICA POR DISCIPLINA ═══ -->
    <div>
        <div class="acta-bar left">Fim do Trimestre</div>
        <div class="bar-clear"></div>
        <?php
        $disc_labels = array_keys($stats_disc);
        $bin_rows = [
            'Avaliados' => 'avaliados',
            '+'         => 'positiva',
            '0 - 4'     => 'b04',
            '5 - 9'     => 'b59',
            '10 - 13'   => 'b1013',
            '14 - 17'   => 'b1417',
            '18 - 20'   => 'b1820',
        ];
        ?>
        <table class="acta-table disc-table small">
            <thead>
                <tr>
                    <th style="width: 14%;">Estatística</th>
                    <?php foreach ($disc_labels as $lbl): ?>
                        <th class="disc-h"><?php echo esc_html($lbl); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($disc_labels)): ?>
                <tr>
                    <td class="txt-left" colspan="2">Sem disciplinas configuradas para esta turma/classe.</td>
                </tr>
                <?php else: foreach ($bin_rows as $rlbl => $key): ?>
                <tr>
                    <td class="txt-left" style="font-weight:700;"><?php echo esc_html($rlbl); ?></td>
                    <?php foreach ($disc_labels as $lbl):
                        $v = $stats_disc[$lbl][$key] ?? 0; ?>
                        <td><?php echo (int) $v === 0 ? '' : (int) $v; ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <div class="auto-note">
            <strong>Fonte automática:</strong> estatística por disciplina calculada a partir das disciplinas reais da turma/classe e das notas oficiais do período, incluindo nota votada já aprovada pelo Director Pedagógico quando existir.
        </div>
    </div>

</div><!-- /page 2 -->

<div class="page">

    <!-- ═══ QUADRO DE HONRA ═══ -->
    <div>
        <div class="acta-bar left">Quadro de Honra</div>
        <div class="bar-clear"></div>
        <table class="acta-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Posição</th>
                    <th style="width: 65%;">Nome do Aluno (a)</th>
                    <th style="width: 20%;">Média</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quadro_honra)): ?>
                <tr class="muted-row">
                    <td class="txt-left" colspan="3">Sem alunos elegíveis para Quadro de Honra neste período, segundo a média calculada pelo sistema.</td>
                </tr>
                <?php else: for ($i = 0; $i < $min_qh; $i++):
                    $q = $quadro_honra[$i] ?? null; ?>
                <tr>
                    <td><?php echo $q ? (int) $q['posicao'] . 'º' : ''; ?></td>
                    <td class="txt-left"><?php echo $q ? esc_html((string) $q['nome']) : ''; ?></td>
                    <td><?php echo $q ? esc_html(number_format((float) $q['media'], 2, ',', '')) : ''; ?></td>
                </tr>
                <?php endfor; endif; ?>
            </tbody>
        </table>
        <div class="auto-note">
            <strong>Fonte automática:</strong> o Quadro de Honra é gerado pela média calculada pelo sistema; não é preenchido manualmente na acta digital.
        </div>
    </div>

    <!-- ═══ CUMPRIMENTO DOS PROGRAMAS ═══ -->
    <div>
        <div class="acta-bar right">Cumprimento dos Programas</div>
        <div class="bar-clear"></div>
        <?php
        // v12.10.78 - O impresso respeita as disciplinas reais vinculadas à turma.
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
        // v12.10.79 - a lista impressa vem exclusivamente da fonte canónica turma/classe.
        // Registos antigos de cumprimento não podem reintroduzir disciplinas hardcoded no PDF.
        $cp_lista = $disciplinas_turma;
        ?>
        <table class="acta-table small">
            <thead>
                <tr>
                    <th style="width: 20%;">Disciplina</th>
                    <th style="width: 35%;">Último Tema</th>
                    <th style="width: 15%;">N.° de Aulas em Atraso</th>
                    <th style="width: 30%;">Razões do Atraso</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cp_lista)): ?>
                <tr>
                    <td colspan="4" class="txt-left">Sem disciplinas vinculadas à turma.</td>
                </tr>
                <?php else: foreach ($cp_lista as $disc):
                    $disc_id = (int) ($disc->id ?? $disc->disciplina_id ?? 0);
                    $disc_nome = (string) ($disc->nome ?? $disc->disciplina_nome ?? '');
                    $c = $cp_by_id[$disc_id] ?? ($cp_by_name[$cp_key($disc_nome)] ?? null); ?>
                <tr>
                    <td class="txt-left" style="font-weight:600;"><?php echo esc_html($disc_nome); ?></td>
                    <td class="txt-left"><?php echo $c ? esc_html((string) $c->ultimo_tema) : ''; ?></td>
                    <td><?php echo $c && $c->aulas_em_atraso !== null ? (int) $c->aulas_em_atraso : ''; ?></td>
                    <td class="txt-left"><?php echo $c ? esc_html((string) $c->razoes_atraso) : ''; ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ═══ COMO DECORREU O CONSELHO DE NOTAS ═══ -->
    <div>
        <div class="acta-bar left">Como decorreu o Conselho de Notas</div>
        <div class="bar-clear"></div>
        <?php if (trim($como_dec) !== ''): ?>
            <div class="como-bloco"><?php echo nl2br(esc_html($como_dec)); ?></div>
        <?php else: ?>
            <div class="como-bloco with-lines">&nbsp;</div>
        <?php endif; ?>

        <?php if (trim($obs_dp) !== ''): ?>
            <div class="obs-dp-bloco">
                <span class="lbl">Observação do Director Pedagógico:</span>
                <?php echo nl2br(esc_html($obs_dp)); ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /page 3 -->

</body>
</html>
