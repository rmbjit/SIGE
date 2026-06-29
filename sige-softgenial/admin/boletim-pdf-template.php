<!DOCTYPE html>

<html lang="pt">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Boletim - <?php echo esc_html($data['aluno']->nome_completo); ?> - <?php echo $data['ano']; ?></title>

<style <?php echo function_exists('sige_csp_style_attr') ? sige_csp_style_attr() : ''; ?>>
.bpdf-btn{padding:9px 18px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;color:#0f172a;font-weight:600;cursor:pointer;margin:4px;}
.bpdf-btn:hover{background:#eef2f7;}


/* ── Reset ── */

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {

    font-family: 'Segoe UI', Arial, sans-serif;

    font-size: 11pt;

    color: #1a1a1a;

    background: #fff;

}

/* ── Página A4 ── */

.page {

    width: 210mm;

    min-height: 297mm;

    margin: 0 auto;

    padding: 12mm 14mm 14mm;

    background: #fff;

}

/* ── Cabeçalho ── */

.header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom: 3px solid #1a5276;

    padding-bottom: 8px;

    margin-bottom: 10px;

}

.header-logo {

    width: 60px;

    height: 60px;

    object-fit: contain;

}

.header-info {

    text-align: center;

    flex: 1;

    padding: 0 10px;

}

.escola-nome {

    font-size: 14pt;

    font-weight: 700;

    color: #1a5276;

    text-transform: uppercase;

    letter-spacing: 0.5px;

}

.escola-sub {

    font-size: 9pt;

    color: #555;

    margin-top: 2px;

}

.escola-ano {

    font-size: 10pt;

    font-weight: 600;

    color: #1a5276;

    margin-top: 4px;

}

.header-right {

    text-align: right;

    font-size: 9pt;

    color: #555;

    min-width: 60px;

}

/* ── Título do documento ── */

.doc-title {

    text-align: center;

    font-size: 13pt;

    font-weight: 700;

    color: #1a5276;

    text-transform: uppercase;

    letter-spacing: 1px;

    border: 2px solid #1a5276;

    padding: 5px 0;

    margin: 8px 0;

    background: #eaf0fb;

}

/* ── Ficha do aluno ── */

.aluno-card {

    background: #f5f8fd;

    border: 1px solid #c5d8f0;

    border-radius: 4px;

    padding: 8px 12px;

    margin-bottom: 10px;

    display: flex;

    gap: 20px;

    flex-wrap: wrap;

}

.aluno-field {

    display: flex;

    flex-direction: column;

}

.aluno-label {

    font-size: 7.5pt;

    text-transform: uppercase;

    color: #777;

    font-weight: 600;

    letter-spacing: 0.4px;

}

.aluno-value {

    font-size: 10.5pt;

    font-weight: 600;

    color: #1a1a1a;

    margin-top: 1px;

}

.aluno-field-nome { flex: 1; min-width: 200px; }

/* ── Situação final badge ── */

.situacao-badge {

    display: inline-block;

    padding: 4px 14px;

    border-radius: 3px;

    font-size: 10pt;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.5px;

    color: #fff;

}

.sit-PROGRIDE { background: #1e8449; }

.sit-TRANSITA { background: #1a5276; }

.sit-REPROVA  { background: #b03a2e; }

.sit-PENDENTE { background: #7d6608; }

/* ── Tabela de notas ── */

.secao-titulo {

    font-size: 9pt;

    font-weight: 700;

    text-transform: uppercase;

    color: #1a5276;

    letter-spacing: 0.4px;

    border-left: 3px solid #1a5276;

    padding-left: 6px;

    margin: 10px 0 5px;

}

table.notas {

    width: 100%;

    border-collapse: collapse;

    font-size: 8.5pt;

}

table.notas th, table.notas td {

    border: 1px solid #c5d8f0;

    padding: 3px 4px;

    text-align: center;

    vertical-align: middle;

}

table.notas th {

    background: #1a5276;

    color: #fff;

    font-weight: 700;

    font-size: 7.5pt;

    text-transform: uppercase;

    letter-spacing: 0.3px;

}

/* Cabeçalhos de trimestre */

table.notas th.th-t1 { background: #2e86c1; }

table.notas th.th-t2 { background: #1a6ea0; }

table.notas th.th-t3 { background: #154f72; }

table.notas th.th-final { background: #0d3349; }

/* Coluna disciplina */

table.notas td.td-disc {

    text-align: left;

    font-weight: 600;

    white-space: nowrap;

    padding-left: 6px;

    background: #f5f8fd;

    max-width: 110px;

    overflow: hidden;

}

/* Linhas alternadas */

table.notas tbody tr:nth-child(even) td:not(.td-disc) {

    background: #fafdff;

}

table.notas tbody tr:nth-child(odd) td:not(.td-disc) {

    background: #fff;

}

/* Nota negativa */

table.notas td.neg {

    color: #b03a2e;

    font-weight: 700;

}

/* Escala colorida */

table.notas td.esc-E  { background: #1e8449; color: #fff; font-weight: 700; }

table.notas td.esc-MB { background: #148f77; color: #fff; font-weight: 700; }

table.notas td.esc-B  { background: #2e86c1; color: #fff; font-weight: 700; }

table.notas td.esc-S  { background: #7d6608; color: #fff; }

table.notas td.esc-NS { background: #b03a2e; color: #fff; font-weight: 700; }

/* Coluna MFD e NF destacadas */

table.notas td.td-mfd, table.notas td.td-nf {

    font-weight: 700;

    font-size: 9pt;

    background: #eaf0fb !important;

}

/* Rodapé tabela */

table.notas tfoot tr td {

    background: #1a5276 !important;

    color: #fff;

    font-weight: 700;

    font-size: 9pt;

}

table.notas tfoot tr td.td-disc {

    text-align: left;

    padding-left: 6px;

}

/* ── Legenda escala ── */

.legenda {

    display: flex;

    gap: 8px;

    margin-top: 6px;

    font-size: 8pt;

    flex-wrap: wrap;

}

.legenda-item {

    display: flex;

    align-items: center;

    gap: 3px;

}

.legenda-cor {

    display: inline-block;

    width: 14px;

    height: 14px;

    border-radius: 2px;

}

/* ── Info pesos ── */

.pesos-info {

    font-size: 8pt;

    color: #555;

    margin-top: 4px;

    font-style: italic;

}

/* ── Assinaturas ── */

.assinaturas {

    display: flex;

    justify-content: space-between;

    margin-top: 18mm;

    gap: 10mm;

}

.assinatura-bloco {

    flex: 1;

    text-align: center;

}

.assinatura-linha {

    border-top: 1.5px solid #1a1a1a;

    width: 100%;

    display: block;

    margin-bottom: 4px;

}

.assinatura-titulo {

    font-size: 8.5pt;

    font-weight: 600;

    text-transform: uppercase;

    letter-spacing: 0.3px;

    color: #1a5276;

}

.assinatura-label {

    font-size: 8pt;

    color: #555;

    margin-top: 2px;

}

/* ── Rodapé de página ── */

.footer {

    border-top: 1px solid #c5d8f0;

    margin-top: 10mm;

    padding-top: 4px;

    display: flex;

    justify-content: space-between;

    font-size: 7.5pt;

    color: #888;

}

/* ── Print ── */

@media print {

    html, body { background: #fff; }

    .page { margin: 0; padding: 10mm 12mm; }

    .no-print { display: none !important; }

    @page {

        size: A4 portrait;

        margin: 0;

    }

}

/* ── Botão de impressão (não aparece no PDF) ── */

.print-bar {

    background: #1a5276;

    color: #fff;

    text-align: center;

    padding: 10px;

    position: sticky;

    top: 0;

    z-index: 100;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 15px;

}

.print-bar button {

    background: #fff;

    color: #1a5276;

    border: none;

    padding: 6px 20px;

    font-size: 10pt;

    font-weight: 700;

    border-radius: 3px;

    cursor: pointer;

    text-transform: uppercase;

    letter-spacing: 0.5px;

}

.print-bar button:hover { background: #eaf0fb; }

</style>

</head>

<body>

<?php
// [STD] Perfil da escola
$_escola_perfil = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
$_escola_nome = $_escola_perfil->nome_escola ?? get_bloginfo('name');

// ── Extrair variáveis do array $data ──────────────────────────────────────────

$aluno        = $data['aluno'];

$matricula    = $data['matricula'];

$ano          = $data['ano'];

$classe       = $data['classe'];

$linhas       = $data['linhas'];

$media_global = $data['media_global'];

$total_neg    = $data['total_neg'];

$situacao     = $data['situacao'];

$peso_mfd     = $data['peso_mfd'];

$peso_exame   = $data['peso_exame'];

$fim_ciclo    = $data['fim_ciclo'];

$sit_label = [

    'PROGRIDE' => 'Aprovado(a) - Progride',

    'TRANSITA' => 'Aprovado(a) - Transita',

    'REPROVA'  => 'Reprovado(a)',

    'PENDENTE' => 'Situação Pendente',

];

function b_escala_class($esc) {

    $map = ['E'=>'esc-E','MB'=>'esc-MB','B'=>'esc-B','S'=>'esc-S','NS'=>'esc-NS'];

    return isset($map[$esc]) ? $map[$esc] : '';

}

function b_nota($v) {

    return ($v !== null && $v !== '') ? number_format($v, 0) : '-';

}

function b_nota_class($v) {

    return ($v !== null && $v < 10) ? ' neg' : '';

}

?>

<!-- Barra de impressão (não aparece no PDF) -->

<div class="print-bar no-print">

    <span>Boletim Individual - <?php echo esc_html($aluno->nome_completo); ?></span>

    <button data-sige-print class="bpdf-btn">🖨 Imprimir / Guardar PDF</button>

    <button data-sige-close class="bpdf-btn">✕ Fechar</button>

</div>

<!-- Página A4 -->

<div class="page">

    <!-- ── Cabeçalho ── -->

    <div class="header">

        <div class="header-info">

            <div class="escola-nome"><?php echo esc_html($_escola_nome); ?></div>

            <div class="escola-ano">Ano Lectivo <?php echo $ano; ?></div>

        </div>

        <div class="header-right">

            Emitido em<br>

            <strong><?php echo wp_date('d/m/Y'); ?></strong>

        </div>

    </div>

    <!-- ── Título ── -->

    <div class="doc-title">Boletim de Avaliação Individual</div>

    <!-- ── Ficha do aluno ── -->

    <div class="aluno-card">

        <div class="aluno-field aluno-field-nome">

            <span class="aluno-label">Nome Completo</span>

            <span class="aluno-value"><?php echo esc_html($aluno->nome_completo); ?></span>

        </div>

        <div class="aluno-field">

            <span class="aluno-label">Nº de Processo</span>

            <span class="aluno-value"><?php echo esc_html($aluno->numero_processo ?: '-'); ?></span>

        </div>

        <div class="aluno-field">

            <span class="aluno-label">Género</span>

            <span class="aluno-value"><?php echo esc_html($aluno->genero ?: '-'); ?></span>

        </div>

        <div class="aluno-field">

            <span class="aluno-label">Classe</span>

            <span class="aluno-value"><?php echo esc_html($classe); ?></span>

        </div>

        <div class="aluno-field">

            <span class="aluno-label">Turma</span>

            <span class="aluno-value"><?php echo esc_html($matricula->nome_turma); ?></span>

        </div>

        <div class="aluno-field">

            <span class="aluno-label">Turno</span>

            <span class="aluno-value"><?php echo esc_html($matricula->turno ?: '-'); ?></span>

        </div>

        <div class="aluno-field">

            <span class="aluno-label">Situação Final</span>

            <span class="aluno-value">

                <span class="situacao-badge sit-<?php echo esc_attr($situacao); ?>">

                    <?php echo esc_html($sit_label[$situacao] ?? $situacao); ?>

                </span>

            </span>

        </div>

    </div>

    <!-- ── Tabela de notas ── -->

    <div class="secao-titulo">Resultados por Disciplina</div>

    <table class="notas">

        <thead>

            <tr>

                <th rowspan="2" style="text-align:left; padding-left:6px; min-width:100px;">Disciplina</th>

                <th colspan="4" class="th-t1">1.º Trimestre</th>

                <th colspan="4" class="th-t2">2.º Trimestre</th>

                <th colspan="4" class="th-t3">

                    3.º Trimestre<?php echo $fim_ciclo ? ' (Fim de Ciclo)' : ''; ?>

                </th>

                <th rowspan="2" class="th-final" style="min-width:28px;">MFD</th>

                <?php if ($fim_ciclo): ?>

                <th rowspan="2" class="th-final" style="min-width:28px;">NF</th>

                <?php endif; ?>

                <th rowspan="2" class="th-final" style="min-width:28px;">Escala</th>

            </tr>

            <tr>

                <?php for ($t = 1; $t <= 3; $t++):

                    $cls = "th-t{$t}"; ?>

                    <th class="<?php echo $cls; ?>">AC1</th>

                    <th class="<?php echo $cls; ?>">AC2</th>

                    <th class="<?php echo $cls; ?>"><?php echo ($t < 3) ? 'AT' : ($fim_ciclo ? 'Exame' : 'AC3'); ?></th>

                    <th class="<?php echo $cls; ?>">MT</th>

                <?php endfor; ?>

            </tr>

        </thead>

        <tbody>

        <?php foreach ($linhas as $linha): ?>

            <tr>

                <td class="td-disc">

                    <?php echo esc_html($linha['nome']); ?>

                    <?php if ($linha['categoria'] === 'auxiliar'): ?>

                        <small style="color:#888;font-weight:400;"> (Aux)</small>

                    <?php endif; ?>

                </td>

                <?php for ($t = 1; $t <= 3; $t++):

                    $tr = isset($linha['trimestres'][$t]) ? $linha['trimestres'][$t] : ['ac1'=>null,'ac2'=>null,'exame'=>null,'mt'=>null]; ?>

                    <td><?php echo b_nota($tr['ac1']); ?></td>

                    <td><?php echo b_nota($tr['ac2']); ?></td>

                    <td><?php echo b_nota($tr['exame']); ?></td>

                    <td class="td-mfd<?php echo b_nota_class($tr['mt']); ?>"><?php echo b_nota($tr['mt']); ?></td>

                <?php endfor; ?>

                <td class="td-mfd<?php echo b_nota_class($linha['mfd']); ?>">

                    <?php echo b_nota($linha['mfd']); ?>

                </td>

                <?php if ($fim_ciclo): ?>

                <td class="td-nf<?php echo b_nota_class($linha['nf']); ?>">

                    <?php echo b_nota($linha['nf']); ?>

                </td>

                <?php endif; ?>

                <td class="<?php echo b_escala_class($linha['escala']); ?>">

                    <?php echo esc_html($linha['escala']); ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

        <tfoot>

            <tr>

                <td class="td-disc" colspan="<?php echo $fim_ciclo ? 14 : 13; ?>">

                    Média Global &nbsp;·&nbsp;

                    Disciplinas com nota negativa: <strong><?php echo $total_neg; ?></strong>

                </td>

                <td class="<?php echo b_escala_class( $media_global !== null ? (function($m){ if($m>=19)return'E'; if($m>=17)return'MB'; if($m>=14)return'B'; if($m>=10)return'S'; return'NS'; })($media_global) : '' ); ?>"

                    style="font-size:10pt;">

                    <?php echo $media_global !== null ? number_format($media_global, 1) : '-'; ?>

                </td>

            </tr>

        </tfoot>

    </table>

    <!-- ── Legenda + pesos ── -->

    <div class="legenda">

        <span style="font-size:8pt;font-weight:600;color:#555;margin-right:4px;">Escala SNE:</span>

        <span class="legenda-item">

            <span class="legenda-cor" style="background:#1e8449;"></span>

            <span>E (19-20)</span>

        </span>

        <span class="legenda-item">

            <span class="legenda-cor" style="background:#148f77;"></span>

            <span>MB (17-18)</span>

        </span>

        <span class="legenda-item">

            <span class="legenda-cor" style="background:#2e86c1;"></span>

            <span>B (14-16)</span>

        </span>

        <span class="legenda-item">

            <span class="legenda-cor" style="background:#7d6608;"></span>

            <span>S (10-13)</span>

        </span>

        <span class="legenda-item">

            <span class="legenda-cor" style="background:#b03a2e;"></span>

            <span>NS (&lt;10)</span>

        </span>

    </div>

    <?php if ($fim_ciclo): ?>

    <div class="pesos-info">

        * Ano de fim de ciclo · NF = (MFD × <?php echo $peso_mfd; ?>% + Exame Final × <?php echo $peso_exame; ?>%) / 100

    </div>

    <?php endif; ?>

    <!-- ── Assinaturas ── -->

    <div class="assinaturas">

        <div class="assinatura-bloco">

            <span class="assinatura-linha"></span>

            <div class="assinatura-titulo">Director(a) de Turma</div>

            <div class="assinatura-label">Data: ___ / ___ / ________</div>

        </div>

        <div class="assinatura-bloco">

            <span class="assinatura-linha"></span>

            <div class="assinatura-titulo">Director(a) Pedagógico(a)</div>

            <div class="assinatura-label">Data: ___ / ___ / ________</div>

        </div>

        <div class="assinatura-bloco">

            <span class="assinatura-linha"></span>

            <div class="assinatura-titulo">Encarregado de Educação</div>

            <div class="assinatura-label">Data: ___ / ___ / ________</div>

        </div>

    </div>

    <!-- ── Rodapé ── -->

    <div class="footer">

        <span><?php echo esc_html($_escola_nome); ?></span>

        <span>Boletim do Aluno · <?php echo esc_html($aluno->nome_completo); ?> · Ano <?php echo $ano; ?></span>

        <span>Emitido em <?php echo wp_date('d/m/Y H:i'); ?></span>

    </div>

</div><!-- .page -->

<script <?php echo sige_csp_script_attr(); ?>>

// Auto-abre o diálogo de impressão ao carregar a página

// (Remover esta linha se preferir que o utilizador clique manualmente)

window.addEventListener('load', function() {

    // Pequeno delay para garantir que o CSS carregou

    setTimeout(function() { window.print(); }, 600);
    document.querySelectorAll('[data-sige-print]').forEach(function(el){ el.addEventListener('click', function(){ window.print(); }); });
    document.querySelectorAll('[data-sige-close]').forEach(function(el){ el.addEventListener('click', function(){ window.close(); }); });

});

</script>

</body>

</html>