<?php
/**
 * Reconciliacao e Divergencias - Fase 7 incremento 1.
 *
 * Ecra so de leitura. Apresenta as divergencias apuradas por
 * sige_reconciliacao_divergencias(). Nao tem formularios nem POST: nao cria
 * qualquer endpoint de escrita. As accoes correctivas continuam a fazer-se
 * pelos fluxos existentes (Pagamentos Moveis). O estilo vive em
 * assets/views/reconciliacao.css, so com tokens (sem primitivos magicos).
 */

if (!defined('ABSPATH')) exit;
global $wpdb;

if (!function_exists('sige_mpesa_pode_gerir') || !sige_mpesa_pode_gerir()) {
    echo '<div class="notice notice-warning"><p>Esta área é reservada à Direcção e à Secretaria Geral.</p></div>';
    return;
}

if (!function_exists('sige_reconciliacao_divergencias')) {
    $sige_recon_lib = SIGE_PATH . 'includes/payments/reconciliacao-divergencias.php';
    if (file_exists($sige_recon_lib)) {
        require_once $sige_recon_lib;
    }
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

$tX = $wpdb->prefix . 'sige_mpesa_transacoes';
$tabela_existe = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tX)) === $tX;

$div = (function_exists('sige_reconciliacao_divergencias') && $tabela_existe)
    ? sige_reconciliacao_divergencias($escola_id)
    : ['nao_conciliadas' => [], 'divergencias_montante' => [], 'pagamentos_sem_gateway' => [], 'totais' => []];

$tot = $div['totais'] ?: [];

$dup = (function_exists('sige_reconciliacao_duplicados') && $tabela_existe)
    ? sige_reconciliacao_duplicados($escola_id)
    : ['grupos' => [], 'totais' => ['n_grupos' => 0, 'n_transacoes' => 0]];
$dup_grupos = $dup['grupos'] ?? [];

$provider_rotulo = function ($p) {
    $p = strtolower(trim((string) $p));
    if ($p === 'emola') return 'e-Mola';
    if ($p === 'mpesa') return 'M-Pesa';
    return $p !== '' ? $p : 'Movel';
};
$fmt = function ($v) {
    return number_format((float) $v, 2, ',', ' ') . ' MT';
};

$sem_divergencias = empty($div['nao_conciliadas'])
    && empty($div['divergencias_montante'])
    && empty($div['pagamentos_sem_gateway']);
?>
<div class="wrap">
    <h1 class="sige-recon-titulo">🧾 Reconciliação e Divergências</h1>
    <p class="sige-recon-hint">
        Esta página compara o dinheiro reportado pelos gateways móveis (M-Pesa e e-Mola)
        com os pagamentos registados, e mostra as divergências que precisam de atenção.
        É só de leitura: para resolver uma divergência, use a página de Pagamentos Móveis.
    </p>

    <?php if (!$tabela_existe): ?>
        <div class="notice notice-info"><p>Ainda não há registos de pagamentos móveis nesta escola.</p></div>
    <?php else: ?>

    <div class="sige-recon-totais">
        <div class="sige-recon-card">
            <div class="sige-recon-card__rotulo">Recebido pelo gateway</div>
            <div class="sige-recon-card__valor"><?php echo esc_html($fmt($tot['recebido_gateway'] ?? 0)); ?></div>
        </div>
        <div class="sige-recon-card sige-recon-card--ok">
            <div class="sige-recon-card__rotulo">Conciliado</div>
            <div class="sige-recon-card__valor"><?php echo esc_html($fmt($tot['conciliado'] ?? 0)); ?></div>
        </div>
        <div class="sige-recon-card sige-recon-card--aviso">
            <div class="sige-recon-card__rotulo">Recebido por aplicar</div>
            <div class="sige-recon-card__valor"><?php echo esc_html($fmt($tot['em_limbo'] ?? 0)); ?></div>
        </div>
        <div class="sige-recon-card">
            <div class="sige-recon-card__rotulo">Pagamentos móveis registados</div>
            <div class="sige-recon-card__valor"><?php echo esc_html($fmt($tot['pagamentos_moveis'] ?? 0)); ?></div>
        </div>
    </div>

    <?php if ($sem_divergencias): ?>
        <div class="notice notice-success"><p><strong>Sem divergências.</strong> Todo o dinheiro recebido está conciliado e os valores coincidem.</p></div>
    <?php endif; ?>

    <h2 class="sige-recon-sec sige-recon-sec--aviso">Recebido mas não conciliado
        <span class="sige-recon-conta">(<?php echo (int) ($tot['n_nao_conciliadas'] ?? 0); ?>)</span>
    </h2>
    <p class="sige-recon-hint">Dinheiro que o gateway confirmou ter recebido, mas que ainda não foi aplicado a nenhum aluno.</p>
    <?php if (empty($div['nao_conciliadas'])): ?>
        <p class="sige-recon-ok">Nada por conciliar.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Gateway</th><th>Referência</th><th>Telemóvel</th><th>Valor</th><th>Estado</th><th>Recebido em</th></tr></thead>
            <tbody>
            <?php foreach ($div['nao_conciliadas'] as $r): ?>
                <tr>
                    <td><?php echo esc_html($provider_rotulo($r->provider)); ?></td>
                    <td><code><?php echo esc_html((string) $r->referencia_mpesa); ?></code></td>
                    <td><?php echo esc_html((string) ($r->msisdn ?? '')); ?></td>
                    <td><?php echo esc_html($fmt($r->valor)); ?></td>
                    <td><?php echo esc_html((string) $r->estado); ?></td>
                    <td><?php echo esc_html((string) ($r->criado_em ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 class="sige-recon-sec sige-recon-sec--erro">Divergência de montante
        <span class="sige-recon-conta">(<?php echo (int) ($tot['n_divergencias_montante'] ?? 0); ?>)</span>
    </h2>
    <p class="sige-recon-hint">Transacções conciliadas cujo valor do gateway não coincide com o valor do pagamento registado.</p>
    <?php if (empty($div['divergencias_montante'])): ?>
        <p class="sige-recon-ok">Nenhuma divergência de montante.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Gateway</th><th>Referência</th><th>Valor do gateway</th><th>Valor do pagamento</th><th>Diferença</th><th>Pagamento</th></tr></thead>
            <tbody>
            <?php foreach ($div['divergencias_montante'] as $r):
                $difer = (float) $r->valor_gateway - (float) $r->valor_pagamento; ?>
                <tr>
                    <td><?php echo esc_html($provider_rotulo($r->provider)); ?></td>
                    <td><code><?php echo esc_html((string) $r->referencia_mpesa); ?></code></td>
                    <td><?php echo esc_html($fmt($r->valor_gateway)); ?></td>
                    <td><?php echo esc_html($fmt($r->valor_pagamento)); ?></td>
                    <td class="sige-recon-difer"><?php echo esc_html($fmt($difer)); ?></td>
                    <td>#<?php echo (int) $r->pagamento_id; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 class="sige-recon-sec sige-recon-sec--aviso">Pagamento móvel sem transacção de gateway
        <span class="sige-recon-conta">(<?php echo (int) ($tot['n_pagamentos_sem_gateway'] ?? 0); ?>)</span>
    </h2>
    <p class="sige-recon-hint">Pagamentos registados como M-Pesa ou e-Mola que não têm nenhuma transacção do gateway a corresponder-lhes (por exemplo, lançamento manual ou webhook em falta).</p>
    <?php if (empty($div['pagamentos_sem_gateway'])): ?>
        <p class="sige-recon-ok">Todos os pagamentos móveis têm transacção de gateway.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Pagamento</th><th>Método</th><th>Referência externa</th><th>Valor</th><th>Data</th></tr></thead>
            <tbody>
            <?php foreach ($div['pagamentos_sem_gateway'] as $r): ?>
                <tr>
                    <td>#<?php echo (int) $r->id; ?></td>
                    <td><?php echo esc_html((string) $r->metodo_pagamento); ?></td>
                    <td><?php echo esc_html((string) ($r->referencia_externa ?? '')); ?></td>
                    <td><?php echo esc_html($fmt($r->valor_pago)); ?></td>
                    <td><?php echo esc_html((string) ($r->data_pagamento ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="sige-recon-nota">
        Nota: a idempotência de webhooks (M-Pesa e e-Mola) é garantida pela chave única por
        escola e referência; transacções repetidas não são contadas duas vezes.
    </p>

    <?php endif; ?>

    <h2 class="sige-recon-sec sige-recon-sec--aviso">Possíveis duplicados
        <span class="sige-recon-conta">(<?php echo (int) ($dup['totais']['n_grupos'] ?? 0); ?>)</span>
    </h2>
    <p class="sige-recon-hint">Grupos de transacções que podem ser o mesmo pagamento feito mais do que uma vez: mesma referência, o mesmo pagamento conciliado mais de uma vez, ou o mesmo pagador e valor próximos no tempo. É uma ajuda à revisão; confirme antes de agir.</p>
    <?php if (empty($dup_grupos)): ?>
        <p class="sige-recon-ok">Não foram encontrados possíveis duplicados.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Motivo</th><th>Chave</th><th>Transacções (n.&ordm;)</th></tr></thead>
            <tbody>
            <?php foreach ($dup_grupos as $g): ?>
                <tr>
                    <td><?php echo esc_html(function_exists('sige_reconciliacao_duplicado_rotulo') ? sige_reconciliacao_duplicado_rotulo((string) $g['motivo']) : (string) $g['motivo']); ?></td>
                    <td><code><?php echo esc_html((string) $g['chave']); ?></code></td>
                    <td><?php echo esc_html(implode(', ', array_map('intval', (array) $g['transacoes']))); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
