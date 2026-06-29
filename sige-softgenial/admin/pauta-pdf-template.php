<!DOCTYPE html>

<html lang="pt">

<head>

<meta charset="UTF-8">

<title>Pauta - <?php echo esc_html($label_turma); ?> - <?php echo $ano_lectivo; ?></title>

<style <?php echo function_exists('sige_csp_style_attr') ? sige_csp_style_attr() : ''; ?>>

* { margin:0; padding:0; box-sizing:border-box; }

body { font-family:Arial,Helvetica,sans-serif; font-size:10px; background:#fff; color:#000; }

.no-print { padding:8px; background:#1a237e; color:#fff; display:flex; gap:10px; align-items:center; }

.btn-print { padding:8px 20px; background:#27ae60; color:#fff; border:none; border-radius:5px; cursor:pointer; font-weight:700; font-size:13px; }

.btn-close  { padding:8px 16px; background:#666; color:#fff; border:none; border-radius:5px; cursor:pointer; }

.inst-header { text-align:center; margin:6px 0 6px; border-bottom:2px solid #1a237e; padding-bottom:6px; }

.inst-header h2 { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#1a237e; }

.inst-header h3 { font-size:11px; color:#333; margin-top:2px; }

.pauta-meta { display:flex; justify-content:space-between; border:1px solid #aaa; padding:4px 8px; margin-bottom:6px; font-size:9px; }

table { border-collapse:collapse; width:100%; font-size:9px; }

th, td { border:1px solid #888; padding:2px 3px; text-align:center; }

th.th-nuc  { background:#155724; color:#fff; font-weight:700; font-size:8px; }

th.th-aux  { background:#4a5568; color:#fff; font-weight:700; font-size:8px; }

th.th-sub  { background:#2d5f8a; color:#fff; font-size:8px; }

th.th-sub2 { background:#3b7ab5; color:#fff; font-size:7px; }

th.th-mfd  { background:#7c4700; color:#fff; }

th.th-esc  { background:#4a5568; color:#fff; }

th.th-mg   { background:#1a237e; color:#fff; font-size:10px; }

th.th-neg  { background:#c0392b; color:#fff; }

th.th-sit  { background:#155724; color:#fff; font-size:10px; }

th.th-alu  { background:#1a237e; color:#fff; text-align:left; padding-left:4px; }

td.td-nome { text-align:left; padding-left:4px; font-size:9px; }

td.td-mt   { background:#e8f4ff; }

td.td-mfd  { background:#fff8cc; font-weight:700; }

td.td-esc  { background:#f3e5f5; font-size:8px; }

td.td-mg   { background:#e8f5e9; font-weight:800; font-size:11px; }

td.td-neg  { color:#c0392b; font-weight:700; }

.badge { padding:2px 6px; border-radius:10px; font-weight:800; font-size:8px; display:inline-block; white-space:nowrap; }

.bg-p { background:#16a34a; color:#fff; }

.bg-t { background:#f59e0b; color:#111; }

.bg-r { background:#dc2626; color:#fff; }

.bg-x { background:#64748b; color:#fff; }

tbody tr:nth-child(even) { background:#f8fafc; }

.legenda { margin-top:8px; font-size:8px; color:#555; }

.rodape { margin-top:20px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:30px; font-size:9px; }

.rodape div { border-top:1px solid #333; padding-top:4px; text-align:center; }

@page { size:A4 landscape; margin:10mm; }

@media print { .no-print { display:none !important; } body { font-size:9px; } }

</style>

</head>

<body>

<div class="no-print">

    <button class="btn-print" data-sige-print>🖨️ Imprimir / Guardar como PDF</button>

    <button class="btn-close" data-sige-close>✕ Fechar</button>

    <span style="font-size:12px;opacity:.8;">No diálogo de impressão escolha "Guardar como PDF" como destino.</span>

</div>

<div class="inst-header">

    <h2>República de Moçambique</h2>

    <h3><?php echo esc_html($titulo); ?></h3>

</div>

<div class="pauta-meta">

    <span>

        <strong>Escola:</strong> <?php echo esc_html($escola); ?> &nbsp;|&nbsp;

        <strong>Turma:</strong> <?php echo esc_html($label_turma); ?> &nbsp;|&nbsp;

        <strong>Classe:</strong> <?php echo $classe_num; ?>ª

    </span>

    <span>

        <strong>Ano Lectivo:</strong> <?php echo $ano_lectivo; ?> &nbsp;|&nbsp;

        <strong>Período:</strong> <?php echo esc_html($periodo_label); ?>

        <?php if ($eh_fim_ciclo && $pauta_final_m): ?>

        &nbsp;|&nbsp;<strong style="color:#c0392b;">🎓 FIM DE CICLO</strong>

        <?php endif; ?>

    </span>

</div>

<table>

<thead>

    <tr>

        <th rowspan="3" style="width:22px;background:#1a237e;color:#fff;">Nº</th>

        <th rowspan="3" class="th-alu" style="min-width:130px;">Nome do Aluno</th>

        <th rowspan="3" style="width:18px;background:#1a237e;color:#fff;">G</th>

        <?php foreach ($disciplinas as $d):

            $is_nuc = strtolower($d->categoria ?? '') === 'nuclear';

            $ncols  = ($trimestre === 0) ? 5 : 2;

        ?>

        <th colspan="<?php echo $ncols; ?>" class="<?php echo $is_nuc ? 'th-nuc' : 'th-aux'; ?>">

            <?php echo esc_html($d->sigla ?? $d->nome); ?> <?php echo $is_nuc ? '★' : '○'; ?>

        </th>

        <?php endforeach; ?>

        <?php if ($trimestre === 0): ?>

        <th rowspan="3" class="th-mg">MG</th>

        <th rowspan="3" class="th-neg">Neg.</th>

        <th rowspan="3" class="th-sit">Resultado</th>

        <?php endif; ?>

    </tr>

    <tr>

        <?php foreach ($disciplinas as $d): ?>

        <?php if ($trimestre === 0): ?>

            <th class="th-sub">MT1</th><th class="th-sub">MT2</th><th class="th-sub">MT3</th>

            <th class="th-mfd">MFD</th><th class="th-esc">ESC</th>

        <?php else: ?>

            <th class="th-sub">MT</th><th class="th-esc">ESC</th>

        <?php endif; ?>

        <?php endforeach; ?>

    </tr>

    <tr>

        <?php foreach ($disciplinas as $d):

            $sub = ($trimestre === 0) ? ['1ºT','2ºT','3ºT','Méd','Esc'] : ['MT','Esc'];

            foreach ($sub as $s): ?>

            <th class="th-sub2"><?php echo $s; ?></th>

        <?php endforeach; endforeach; ?>

    </tr>

</thead>

<tbody>

<?php

$num = 1;

foreach ($alunos as $aluno):

    $aid = (int)$aluno->id;

    $mfds_nuc = [];
    $nucleares_requeridas = 0;
    $nucleares_com_nota_final = 0;
    $tem_alguma_nota_final = false;

    $negativas = 0;

    $nota_min  = (float)($regra['nota_minima_aprovacao'] ?? 10);

?>

<tr>

    <td><?php echo $num++; ?></td>

    <td class="td-nome"><?php echo esc_html($aluno->nome_completo); ?></td>

    <td style="font-size:8px;"><?php echo esc_html($aluno->genero ?? ''); ?></td>

    <?php foreach ($disciplinas as $d):

        $did    = (int)$d->id;

        $is_nuc = strtolower($d->categoria ?? '') === 'nuclear';

        $mts    = [];

        for ($t = 1; $t <= 3; $t++) {

            $row    = $notas_map[$aid][$did][$t] ?? null;

            $mts[$t] = $fn_mt($row);

        }

        $mfd   = (int)round(((int)$mts[1] + (int)$mts[2] + (int)$mts[3]) / 3, 0);

        $nota_final = $mfd;

        if ($eh_fim_ciclo && $pauta_final_m && $mfd !== null) {

            $row3  = $notas_map[$aid][$did][3] ?? null;

            $exame = ($row3 && is_numeric($row3->nota_exame)) ? (float)$row3->nota_exame : 0.0;

            $pm = (float)($regra['peso_mfd'] ?? 60);

            $pe = (float)($regra['peso_exame'] ?? 40);

            $nota_final = (int)round(($mfd*$pm + $exame*$pe)/($pm+$pe), 0);

        }

        if ($is_nuc) {
            $nucleares_requeridas++;
        }
        if ($nota_final !== null) {
            $tem_alguma_nota_final = true;
        }

        if ($is_nuc && $nota_final !== null) {

            $mfds_nuc[] = $nota_final;
            $nucleares_com_nota_final++;

            if ($nota_final < $nota_min) $negativas++;

        }

        if ($trimestre === 0): ?>

        <td class="td-mt"><?php echo $mts[1] !== null ? $mts[1] : ''; ?></td>

        <td class="td-mt"><?php echo $mts[2] !== null ? $mts[2] : ''; ?></td>

        <td class="td-mt"><?php echo $mts[3] !== null ? $mts[3] : ''; ?></td>

        <td class="td-mfd"><?php echo $nota_final !== null ? $nota_final : ''; ?></td>

        <td class="td-esc"><?php echo $fn_esc($nota_final); ?></td>

    <?php else:

        $mt_t = $mts[$trimestre] ?? null; ?>

        <td class="td-mt"><?php echo $mt_t !== null ? $mt_t : ''; ?></td>

        <td class="td-esc"><?php echo $fn_esc($mt_t); ?></td>

    <?php endif; ?>

    <?php endforeach; ?>

    <?php

    $mg = null; $situacao = '-'; $bc = 'bg-x';

    if ($trimestre === 0) {

        $mg = !empty($mfds_nuc) ? (int)round(array_sum($mfds_nuc)/count($mfds_nuc), 0) : null;

        if (function_exists('sige_calcular_situacao_final')) {

            $sf = sige_calcular_situacao_final($aid, $turma_id, $ano_lectivo, (bool)$pauta_final_m);

            $situacao  = $sf['situacao']     ?? '-';

            $negativas = $sf['negativas']    ?? $negativas;

            $mg        = $sf['media_global'] ?? $mg;

        } else {

            $nota_min_r = (float)($regra['nota_minima_aprovacao'] ?? 10);

            $max_p = (int)($regra['max_negativas_progride'] ?? 0);

            $max_t = (int)($regra['max_negativas_transita'] ?? 2);

            if ($mg !== null && $mg < $nota_min_r) {

                $situacao = 'REPROVA';

            } elseif ($negativas <= $max_p) {

                $situacao = 'PROGRIDE';

            } elseif ($negativas <= $max_t) {

                $situacao = 'TRANSITA';

            } else {

                $situacao = 'REPROVA';

            }

            // Norma SNE: TRANSITA em fim de ciclo, PROGRIDE nas classes intermediárias

            if ($situacao !== 'REPROVA') {

                $eh_fim = !empty($regra['eh_fim_ciclo']) ? (int)$regra['eh_fim_ciclo'] : 0;

                $situacao = $eh_fim ? 'TRANSITA' : 'PROGRIDE';

            }

        }

        $sit_raw = strtoupper($situacao);
        $bc = ['PROGRIDE'=>'bg-p','TRANSITA'=>'bg-t','REPROVA'=>'bg-r','PENDENTE'=>'bg-x'][in_array($sit_raw, ['NÃO PROGRIDE','NAO PROGRIDE','NÃO TRANSITA','NAO TRANSITA'], true) ? 'REPROVA' : $sit_raw] ?? 'bg-x';
        $situacao_label = function_exists('sige_situacao_final_rotulo_publico') ? sige_situacao_final_rotulo_publico($situacao, !empty($regra['eh_fim_ciclo'])) : $situacao;

    }

    ?>

    <?php if ($trimestre === 0): ?>

    <td class="td-mg"><?php echo $mg !== null ? $mg : ''; ?></td>

    <td class="td-neg"><?php echo $negativas !== null ? $negativas : ''; ?></td>

    <td><span class="badge <?php echo $bc; ?>"><?php echo esc_html($situacao_label); ?></span></td>

    <?php endif; ?>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="legenda">

    ★ Nuclear (conta para MG) &nbsp;|&nbsp; ○ Auxiliar (só escala) &nbsp;|&nbsp;

    ESC: NS(&lt;10) | S(10-13) | B(14-16) | MB(17-18) | E(19-20) &nbsp;|&nbsp;

    MG = Média das disciplinas nucleares

</div>

<div class="rodape">

    <div>Director(a) Pedagógico(a)</div>

    <div>Director(a) da Escola</div>

    <div>Data: ___/___/______</div>

</div>

<script <?php echo sige_csp_script_attr(); ?>>

window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 800); });
document.querySelectorAll('[data-sige-print]').forEach(function(el){ el.addEventListener('click', function(){ window.print(); }); });
document.querySelectorAll('[data-sige-close]').forEach(function(el){ el.addEventListener('click', function(){ window.close(); }); });

</script>

</body>

</html>