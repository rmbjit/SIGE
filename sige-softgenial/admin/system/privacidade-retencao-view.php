<?php
/**
 * Retencao e expurgo - Fase 8 incremento 4. So leitura.
 *
 * Mostra o calendario de retencao declarado e, por categoria, quantos registos
 * da escola ja excederam o prazo (candidatos a expurgo), de forma agregada.
 * Nao oferece qualquer eliminacao: o expurgo de um titular faz-se pela
 * anonimizacao (Apagamento). Sem POST, sem estilo inline, so tokens.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_pii_retencao_pode_ver')) {
    $sige_ret_lib = SIGE_PATH . 'includes/privacy/pii-retencao.php';
    if (file_exists($sige_ret_lib)) require_once $sige_ret_lib;
}

if (!function_exists('sige_pii_retencao_pode_ver') || !sige_pii_retencao_pode_ver()) {
    echo '<div class="notice notice-warning"><p>Esta área é reservada a quem pode ver a retenção de dados (Direcção e administração).</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$panorama = sige_pii_retencao_panorama($escola_id);
$apag_url = admin_url('admin.php?page=sige-app&view=privacidade-apagamento');

$modo_rotulo = [
    'anonimizacao'  => 'Anonimizar (Apagamento)',
    'retido'        => 'Retido',
    'nao_aplicavel' => 'Funcionário',
];
$modo_badge = [
    'anonimizacao'  => 'sige-priv-badge--normal',
    'retido'        => 'sige-priv-badge--ok',
    'nao_aplicavel' => 'sige-priv-badge--ausente',
];
?>
<div class="wrap">
    <h1 class="sige-priv-titulo">🗓️ Retenção e Expurgo</h1>
    <p class="sige-priv-hint">
        Política de retenção de dados pessoais: quanto tempo cada categoria deve ser guardada, com que
        fundamento, e quantos registos já excederam o prazo. As contagens são agregadas por escola; nenhum
        dado individual é mostrado aqui. Neste sistema o expurgo não é feito por eliminação em massa: para
        dados com dever de integridade ou retenção (financeiro, académico, presenças), o expurgo de um titular
        faz-se pela <a href="<?php echo esc_url($apag_url); ?>">anonimização (Apagamento)</a>, que preserva os
        registos. O calendário é uma classificação por omissão, a rever pela instituição enquanto responsável
        pelo tratamento.
    </p>

    <?php if (!$panorama['ok']): ?>
        <div class="notice notice-info"><p>Sem escola activa no contexto. Seleccione uma escola para ver a retenção.</p></div>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div class="sige-priv-cards">
        <div class="sige-priv-card">
            <div class="sige-priv-card__rotulo">CATEGORIAS NO CALENDÁRIO</div>
            <div class="sige-priv-card__valor"><?php echo count($panorama['linhas']); ?></div>
        </div>
        <div class="sige-priv-card">
            <div class="sige-priv-card__rotulo">REGISTOS ALÉM DO PRAZO</div>
            <div class="sige-priv-card__valor"><?php echo (int) $panorama['total_excedido']; ?></div>
        </div>
        <div class="sige-priv-card">
            <div class="sige-priv-card__rotulo">ANONIMIZÁVEIS (POR TITULAR)</div>
            <div class="sige-priv-card__valor"><?php echo (int) $panorama['total_anonimizavel']; ?></div>
        </div>
    </div>

    <h2 class="sige-priv-sec">Calendário de retenção</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Tabela</th>
                <th>Prazo</th>
                <th>Base legal</th>
                <th>Expurgo</th>
                <th>Além do prazo</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($panorama['linhas'] as $l): ?>
                <tr>
                    <td><?php echo esc_html((string) $l['categoria']); ?>
                        <?php if (!empty($l['nota'])): ?>
                            <span class="sige-priv-nota"><?php echo esc_html((string) $l['nota']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo esc_html((string) $l['tabela']); ?></code></td>
                    <td><?php echo esc_html((string) $l['prazo_legivel']); ?></td>
                    <td><?php echo esc_html((string) $l['base_legal']); ?></td>
                    <td>
                        <span class="sige-priv-badge <?php echo esc_attr($modo_badge[$l['modo']] ?? 'sige-priv-badge--normal'); ?>">
                            <?php echo esc_html($modo_rotulo[$l['modo']] ?? (string) $l['modo']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($l['mensuravel']): ?>
                            <strong><?php echo (int) $l['excedido']; ?></strong>
                        <?php else: ?>
                            <span class="sige-priv-nota">sem data</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="sige-priv-hint">
        Os registos retidos (auditoria e registo de acessos) não são expurgados, por dever legal ou por serem
        fonte de integridade. Para os campos anonimizáveis, use o
        <a href="<?php echo esc_url($apag_url); ?>">Apagamento</a> por titular: a anonimização redige os dados
        pessoais e preserva os valores financeiros e académicos.
    </p>
</div>
