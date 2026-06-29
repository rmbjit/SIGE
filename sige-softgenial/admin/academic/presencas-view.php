<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

if (!function_exists('sige_presencas_pode_ver') || !sige_presencas_pode_ver()) {
    echo '<div class="notice notice-warning" style="margin:20px;border-radius:8px;"><p>Esta área é reservada à equipa pedagógica e à secretaria.</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

// ── Mapa Mensal de Assiduidade (formato oficial, imprimível) ───────────────
if (isset($_GET['relatorio']) && $_GET['relatorio'] === '1') {
    $rel_turma = (int)($_GET['turma_id'] ?? 0);
    $rel_ano = (int)($_GET['ano'] ?? current_time('Y'));
    $rel_mes = (int)($_GET['mes'] ?? current_time('n'));
    if ($rel_turma <= 0 || $rel_mes < 1 || $rel_mes > 12 || !function_exists('sige_presencas_relatorio_dados')) {
        echo '<div class="notice notice-warning" style="margin:20px;border-radius:8px;"><p>Parâmetros do relatório inválidos. Volte ao mapa e escolha a turma.</p></div>';
        return;
    }
    $R = sige_presencas_relatorio_dados($escola_id, $rel_turma, $rel_ano, $rel_mes);
    $meses_pt = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
    if (function_exists('sige_view_assets')) sige_view_assets('presencas');
    $voltar = add_query_arg(['page' => 'sige-app', 'view' => 'presencas'], admin_url('admin.php'));
    ?>
    <div class="sg-pres-rel">
        <div class="sg-pres-rel-toolbar">
            <a href="<?php echo esc_url($voltar); ?>" class="button">‹ Voltar ao mapa</a>
            <button type="button" class="button button-primary" id="sgPresRelPrint">Imprimir</button>
            <span style="color:#64748b;font-size:12px;">Formato genérico; será afinado ao modelo oficial MINEDH.</span>
        </div>
        <div class="sg-pres-rel-folha">
            <div class="sg-pres-rel-cab">
                <?php if (!empty($R['escola']['logo_url'])): ?>
                <img src="<?php echo esc_url($R['escola']['logo_url']); ?>" alt="" class="sg-pres-rel-logo">
                <?php endif; ?>
                <div class="sg-pres-rel-cab-txt">
                    <div class="sg-pres-rel-rep">República de Moçambique</div>
                    <div class="sg-pres-rel-min">Ministério da Educação e Desenvolvimento Humano</div>
                    <div class="sg-pres-rel-esc"><?php echo esc_html($R['escola']['nome']); ?></div>
                    <?php if ($R['escola']['endereco'] !== '' || $R['escola']['telefone'] !== ''): ?>
                    <div class="sg-pres-rel-end"><?php echo esc_html(trim($R['escola']['endereco'] . ($R['escola']['telefone'] !== '' ? ' · Tel: ' . $R['escola']['telefone'] : ''))); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <h2 class="sg-pres-rel-titulo">Mapa Mensal de Assiduidade</h2>
            <div class="sg-pres-rel-ident">
                <span><strong>Turma:</strong> <?php echo esc_html(trim(($R['turma']['classe'] ? $R['turma']['classe'] . ' - ' : '') . $R['turma']['nome'])); ?></span>
                <span><strong>Mês:</strong> <?php echo esc_html($meses_pt[$R['mes']] . ' de ' . $R['ano']); ?></span>
                <span><strong>Ano lectivo:</strong> <?php echo (int)$R['ano_lectivo']; ?></span>
                <span><strong>Hora de corte:</strong> <?php echo esc_html($R['hora_corte']); ?></span>
            </div>
            <table class="sg-pres-rel-tab">
                <thead>
                    <tr>
                        <th class="c-num">Nº</th>
                        <th class="c-nome">Nome do Aluno</th>
                        <th class="c-sexo">S</th>
                        <?php foreach ($R['dias'] as $d): ?><th class="c-dia"><?php echo (int)$d['dia']; ?></th><?php endforeach; ?>
                        <th class="c-tot">P</th><th class="c-tot">AT</th><th class="c-tot">F</th><th class="c-tot">J</th><th class="c-tot">%</th>
                    </tr>
                </thead>
                <tbody>
                <?php $ord = 0; foreach ($R['alunos'] as $a): $ord++; $c = $a['totais']['contagens']; ?>
                    <tr>
                        <td class="c-num"><?php echo $ord; ?></td>
                        <td class="c-nome"><?php echo esc_html($a['nome']); ?></td>
                        <td class="c-sexo"><?php echo esc_html($a['genero'] ?: ''); ?></td>
                        <?php foreach ($R['dias'] as $d): $e = $a['estados'][$d['dia']] ?? ''; ?>
                        <td class="c-dia c-e-<?php echo esc_attr($e ?: 'v'); ?>"><?php echo esc_html($e === 'PM' ? 'P' : ($e ?: '')); ?></td>
                        <?php endforeach; ?>
                        <td class="c-tot"><?php echo (int)($c['P'] + $c['PM']); ?></td>
                        <td class="c-tot"><?php echo (int)$c['AT']; ?></td>
                        <td class="c-tot"><?php echo (int)$c['F']; ?></td>
                        <td class="c-tot"><?php echo (int)$c['J']; ?></td>
                        <td class="c-tot"><?php echo esc_html($a['totais']['pct_presenca']); ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="c-rodape">Presentes no dia</td>
                        <?php foreach ($R['dias'] as $d): ?><td class="c-dia c-rodape"><?php echo (int)$R['totais_dia'][$d['dia']]; ?></td><?php endforeach; ?>
                        <td colspan="5" class="c-rodape"></td>
                    </tr>
                </tfoot>
            </table>
            <div class="sg-pres-rel-resumo">
                <span>Total de alunos: <strong><?php echo (int)$R['resumo']['total']; ?></strong></span>
                <span>Masculino: <strong><?php echo (int)$R['resumo']['masculino']; ?></strong></span>
                <span>Feminino: <strong><?php echo (int)$R['resumo']['feminino']; ?></strong></span>
                <span>Média de presença: <strong><?php echo esc_html($R['resumo']['media_presenca']); ?>%</strong></span>
                <span class="sg-pres-rel-leg">P presente · AT atraso · F falta · J justificada · D dispensado</span>
            </div>
            <div class="sg-pres-rel-assin">
                <div><div class="linha"></div>O/A Director(a) de Turma</div>
                <div><div class="linha"></div>O/A Director(a) da Escola</div>
                <div><div class="linha"></div>Data e carimbo</div>
            </div>
        </div>
    </div>
    <?php
    return;
}

$tT = $wpdb->prefix . 'sige_turmas';
$turmas = $wpdb->get_results($wpdb->prepare(
    "SELECT id, nome, classe FROM {$tT} WHERE escola_id = %d ORDER BY classe ASC, nome ASC", $escola_id
));
$nonce = wp_create_nonce('sige_presencas');
$pode_editar = function_exists('sige_presencas_pode_editar') && sige_presencas_pode_editar();
// Caminho rapido da secretaria (inline + teclado + lote). Atras de flag, default
// OFF: o modal classico continua a ser a experiencia viva ate prova em browser.
// Reversivel a qualquer momento pondo a option a '0'.
$fast_path = $pode_editar && get_option('sige_presencas_fast_path_v121517_enabled', '1') === '1';
$mes_actual = (int) current_time('n');
$ano_actual = (int) current_time('Y');
$meses_nomes = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>
<div class="wrap sg-presencas <?php echo $pode_editar ? 'sg-pres-editavel' : ''; ?>" style="max-width:1280px;padding:18px;">
    <h1 style="display:flex;align-items:center;gap:12px;color:#0d1259;margin-bottom:4px;">
        <span style="font-size:28px;">🗓️</span> Presenças por Turma
    </h1>
    <p style="color:#475569;margin-top:0;">
        Mapa de assiduidade derivado automaticamente das leituras da Portaria.
        Hora de corte para atraso: <strong><?php echo esc_html(function_exists('sige_presencas_hora_corte') ? sige_presencas_hora_corte() : '07:30'); ?></strong>.
        <?php if ($pode_editar): ?>Clique numa célula para justificar ou corrigir.<?php endif; ?>
    </p>

    <div class="sg-pres-toolbar" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 2px 10px rgba(0,0,0,.06);">
        <div>
            <label style="font-weight:600;color:#0f172a;display:block;margin-bottom:5px;">Turma</label>
            <select id="sgPresTurma" style="min-width:240px;padding:8px 10px;border-radius:8px;border:1px solid #cbd5e1;">
                <option value="0">Escolha a turma</option>
                <?php foreach ((array)$turmas as $t): ?>
                <option value="<?php echo (int)$t->id; ?>"><?php echo esc_html(trim(($t->classe ? $t->classe . ' - ' : '') . $t->nome)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-weight:600;color:#0f172a;display:block;margin-bottom:5px;">Mês</label>
            <div style="display:flex;align-items:center;gap:6px;">
                <button type="button" class="button" id="sgPresAnt" title="Mês anterior">‹</button>
                <strong id="sgPresMesLabel" style="min-width:160px;text-align:center;color:#0d1259;"></strong>
                <button type="button" class="button" id="sgPresSeg" title="Mês seguinte">›</button>
            </div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;">
            <a href="#" class="button" id="sgPresRel" aria-disabled="true" style="pointer-events:none;opacity:.5;">Relatório oficial</a>
            <button type="button" class="button" id="sgPresCsv" disabled>Exportar CSV</button>
            <button type="button" class="button" id="sgPresPrint" disabled>Imprimir</button>
        </div>
    </div>

    <div class="sg-pres-legenda" style="display:flex;gap:14px;flex-wrap:wrap;margin:12px 2px;color:#334155;font-size:12.5px;">
        <span><b class="sgp sgp-P">P</b> Presente</span>
        <span><b class="sgp sgp-AT">AT</b> Atraso</span>
        <span><b class="sgp sgp-F">F</b> Falta</span>
        <span><b class="sgp sgp-J">J</b> Justificada</span>
        <span><b class="sgp sgp-PM">PM</b> Presente (manual)</span>
        <span><b class="sgp sgp-D">D</b> Dispensado</span>
    </div>

    <div id="sgPresWrap" style="background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);overflow:auto;min-height:120px;">
        <div id="sgPresVazio" style="padding:34px;text-align:center;color:#64748b;">Escolha a turma para carregar o mapa do mês.</div>
        <table id="sgPresTabela" style="display:none;border-collapse:collapse;width:100%;font-size:12.5px;"></table>
    </div>
</div>

<?php if ($pode_editar): ?>
<div id="sgPresModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:20px;width:min(420px,92vw);box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <h3 style="margin:0 0 4px;color:#0d1259;" id="sgPresModalTitulo">Corrigir presença</h3>
        <p style="margin:0 0 12px;color:#64748b;font-size:13px;" id="sgPresModalSub"></p>
        <div style="display:grid;gap:8px;">
            <label class="sg-pres-op"><input type="radio" name="sgPresEstado" value="falta_justificada"> Falta justificada</label>
            <label class="sg-pres-op"><input type="radio" name="sgPresEstado" value="presente_manual"> Presente (marcação manual)</label>
            <label class="sg-pres-op"><input type="radio" name="sgPresEstado" value="dispensado"> Dispensado (fora do cálculo)</label>
            <label class="sg-pres-op"><input type="radio" name="sgPresEstado" value="auto"> Voltar ao automático (Portaria)</label>
        </div>
        <input type="text" id="sgPresMotivo" maxlength="255" placeholder="Motivo (opcional, fica no registo)"
               style="width:100%;margin-top:12px;padding:9px 10px;border-radius:8px;border:1px solid #cbd5e1;">
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
            <button type="button" class="button" id="sgPresCancelar">Cancelar</button>
            <button type="button" class="button button-primary" id="sgPresGuardar">Guardar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($pode_editar): ?>
<div id="sgPresStatus" class="sg-pres-status" aria-live="polite" role="status"></div>
<?php endif; ?>

<?php if ($fast_path): ?>
<div id="sgPresPop" class="sg-pres-pop" role="dialog" aria-label="Corrigir presença" hidden>
    <div class="sg-pres-pop-sub" id="sgPresPopSub"></div>
    <div class="sg-pres-pop-ops">
        <button type="button" class="sg-pres-pop-op" data-estado="presente_manual"><b class="sgp sgp-PM">PM</b> Presente <kbd>P</kbd></button>
        <button type="button" class="sg-pres-pop-op" data-estado="falta_justificada"><b class="sgp sgp-J">J</b> Justificada <kbd>J</kbd></button>
        <button type="button" class="sg-pres-pop-op" data-estado="dispensado"><b class="sgp sgp-D">D</b> Dispensado <kbd>D</kbd></button>
        <button type="button" class="sg-pres-pop-op" data-estado="auto"><b class="sgp sgp-A">A</b> Automático <kbd>A</kbd></button>
    </div>
    <input type="text" id="sgPresPopMotivo" maxlength="255" placeholder="Motivo (opcional, fica no registo)" class="sg-pres-pop-motivo">
</div>

<div id="sgPresBar" class="sg-pres-bar" role="region" aria-label="Acção em lote" hidden>
    <span class="sg-pres-bar-count" id="sgPresBarCount">0 células</span>
    <span class="sg-pres-bar-lbl">Aplicar:</span>
    <div class="sg-pres-bar-ops">
        <button type="button" class="sg-pres-bar-op" data-estado="presente_manual"><b class="sgp sgp-PM">PM</b></button>
        <button type="button" class="sg-pres-bar-op" data-estado="falta_justificada"><b class="sgp sgp-J">J</b></button>
        <button type="button" class="sg-pres-bar-op" data-estado="dispensado"><b class="sgp sgp-D">D</b></button>
        <button type="button" class="sg-pres-bar-op" data-estado="auto"><b class="sgp sgp-A">A</b></button>
    </div>
    <input type="text" id="sgPresBarMotivo" maxlength="255" placeholder="Motivo (opcional)" class="sg-pres-bar-motivo">
    <button type="button" class="sg-pres-bar-x" id="sgPresBarLimpar">Limpar selecção</button>
</div>
<?php endif; ?>

<script <?php echo sige_csp_script_attr(); ?>>
window.SIGE_PRESENCAS_CFG = {
    ajaxurl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
    nonce: <?php echo wp_json_encode($nonce); ?>,
    podeEditar: <?php echo $pode_editar ? 'true' : 'false'; ?>,
    fastPath: <?php echo $fast_path ? 'true' : 'false'; ?>,
    meses: <?php echo wp_json_encode($meses_nomes); ?>,
    ano: <?php echo (int)$ano_actual; ?>,
    mes: <?php echo (int)$mes_actual; ?>
};
</script>
<?php if (function_exists('sige_view_assets')) sige_view_assets('presencas'); ?>


