<?php
/**
 * Aprovacoes Pendentes - Fase 7 incremento 2 (regra de quatro-olhos).
 *
 * Lista os pedidos de estorno e de reabertura de caixa e permite que um
 * utilizador DIFERENTE do solicitante os aprove ou rejeite. A aprovacao executa
 * a accao subjacente (com MFA do aprovador); a rejeicao descarta-a. Um unico
 * endpoint de decisao (campo sige_fin_aprovacao_decidir). Estilo so com tokens.
 */

if (!defined('ABSPATH')) exit;
global $wpdb;

if (!function_exists('sige_fin_aprovacao_pode_aceder')) {
    $sige_aprov_lib = SIGE_PATH . 'includes/finance-aprovacoes.php';
    if (file_exists($sige_aprov_lib)) require_once $sige_aprov_lib;
}

if (!function_exists('sige_fin_aprovacao_pode_aceder') || !sige_fin_aprovacao_pode_aceder()) {
    echo '<div class="notice notice-warning"><p>Esta área é reservada a quem pode autorizar estornos ou reaberturas de caixa.</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

$fmt = function ($v) { return number_format((float) $v, 2, ',', ' ') . ' MT'; };

// ── Handler de decisao (POST) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fin_aprovacao_decidir'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sige_fin_aprovacao_decidir')) {
        echo '<div class="notice notice-error"><p>Pedido inválido.</p></div>';
    } else {
        $sg_aid     = (int) ($_POST['aprovacao_id'] ?? 0);
        $sg_decisao = isset($_POST['decisao']) ? sanitize_text_field((string) $_POST['decisao']) : '';
        $sg_motivo  = isset($_POST['decisao_motivo']) ? sanitize_text_field((string) $_POST['decisao_motivo']) : '';
        $sg_aprovar = ($sg_decisao === 'aprovar');

        if (!$sg_aprovar && $sg_decisao !== 'rejeitar') {
            echo '<div class="notice notice-error"><p>Decisão inválida.</p></div>';
        } elseif (!$sg_aprovar && trim($sg_motivo) === '') {
            echo '<div class="notice notice-error"><p>O motivo da rejeição é obrigatório.</p></div>';
        } else {
            $sg_res = sige_fin_aprovacao_decidir($sg_aid, $sg_aprovar, $sg_motivo, $escola_id);
            if (!empty($sg_res['mfa_required'])) {
                echo '<div class="notice notice-warning"><p>' . esc_html($sg_res['error'] ?? 'Confirmação de identidade necessária.') . '</p></div>';
                if (function_exists('sige_mfa_render_challenge_form')) echo sige_mfa_render_challenge_form();
            } elseif (!empty($sg_res['ok'])) {
                if (($sg_res['estado'] ?? '') === 'executada') {
                    echo '<div class="notice notice-success"><p>Pedido aprovado e executado.</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>Pedido rejeitado.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html($sg_res['error'] ?? 'Não foi possível concluir a decisão.') . '</p></div>';
            }
        }
    }
}

$pendentes = function_exists('sige_fin_aprovacoes_listar') ? sige_fin_aprovacoes_listar($escola_id, 'pendente') : [];
$historico = function_exists('sige_fin_aprovacoes_listar') ? sige_fin_aprovacoes_listar($escola_id, 'todos', 20) : [];
$historico = array_values(array_filter($historico, function ($r) { return $r->estado !== 'pendente'; }));

$nonce_dec = wp_create_nonce('sige_fin_aprovacao_decidir');
$uid_actual = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

$param = function ($r, $k, $d = '') {
    $p = json_decode((string) $r->parametros, true);
    return is_array($p) && isset($p[$k]) ? $p[$k] : $d;
};
$estado_classe = [
    'pendente'  => 'sige-aprov-badge sige-aprov-badge--aviso',
    'executada' => 'sige-aprov-badge sige-aprov-badge--ok',
    'rejeitada' => 'sige-aprov-badge sige-aprov-badge--neutro',
    'falhada'   => 'sige-aprov-badge sige-aprov-badge--erro',
];
?>
<div class="wrap">
    <h1 class="sige-aprov-titulo">✅ Aprovações Pendentes</h1>
    <p class="sige-aprov-hint">
        Estornos de pagamento e reaberturas de caixa exigem dupla aprovação. Cada pedido tem de ser
        aprovado por um utilizador <strong>diferente</strong> de quem o submeteu. Ninguém pode aprovar o seu próprio pedido.
    </p>

    <h2 class="sige-aprov-sec">A aguardar decisão
        <span class="sige-aprov-conta">(<?php echo (int) count($pendentes); ?>)</span>
    </h2>
    <?php if (empty($pendentes)): ?>
        <p class="sige-aprov-ok">Não há pedidos a aguardar decisão.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Operação</th><th>Alvo</th><th>Valor / Data</th><th>Motivo</th><th>Solicitado por</th><th>Decisão</th></tr></thead>
            <tbody>
            <?php foreach ($pendentes as $r):
                $tipo = (string) $r->tipo;
                $is_estorno = ($tipo === 'estorno_pagamento');
                $detalhe = $is_estorno ? esc_html($fmt($param($r, 'valor', 0))) : esc_html((string) $param($r, 'data_caixa', ''));
                $proprio = ($uid_actual > 0 && (int) $r->solicitante_user_id === $uid_actual);
            ?>
                <tr>
                    <td><?php echo esc_html(function_exists('sige_fin_aprovacao_tipo_rotulo') ? sige_fin_aprovacao_tipo_rotulo($tipo) : $tipo); ?></td>
                    <td><?php echo esc_html((string) ($r->alvo_ref ?: ('#' . (int) $r->alvo_id))); ?></td>
                    <td><?php echo $detalhe; ?></td>
                    <td><?php echo esc_html((string) $param($r, 'motivo', '')); ?></td>
                    <td><?php echo esc_html((string) $r->solicitante_nome); ?><br><span class="sige-aprov-conta"><?php echo esc_html((string) $r->solicitado_em); ?></span></td>
                    <td>
                        <?php if ($proprio): ?>
                            <span class="sige-aprov-conta">O seu pedido. Aguarda outro utilizador.</span>
                        <?php else: ?>
                            <form method="post" class="sige-aprov-form">
                                <input type="hidden" name="sige_fin_aprovacao_decidir" value="1">
                                <input type="hidden" name="aprovacao_id" value="<?php echo (int) $r->id; ?>">
                                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce_dec); ?>">
                                <input type="text" name="decisao_motivo" class="sige-aprov-motivo" placeholder="Motivo (obrigatório para rejeitar)">
                                <button type="submit" name="decisao" value="aprovar" class="button button-primary">Aprovar</button>
                                <button type="submit" name="decisao" value="rejeitar" class="button">Rejeitar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="sige-aprov-nota">Ao aprovar um estorno ou reabertura, poderá ser-lhe pedida a confirmação de identidade (MFA) antes de a operação ser executada.</p>
    <?php endif; ?>

    <h2 class="sige-aprov-sec">Decisões recentes
        <span class="sige-aprov-conta">(<?php echo (int) count($historico); ?>)</span>
    </h2>
    <?php if (empty($historico)): ?>
        <p class="sige-aprov-ok">Ainda não há decisões registadas.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead><tr><th>Operação</th><th>Alvo</th><th>Estado</th><th>Solicitado por</th><th>Decidido por</th><th>Quando</th></tr></thead>
            <tbody>
            <?php foreach ($historico as $r):
                $cls = $estado_classe[(string) $r->estado] ?? 'sige-aprov-badge';
            ?>
                <tr>
                    <td><?php echo esc_html(function_exists('sige_fin_aprovacao_tipo_rotulo') ? sige_fin_aprovacao_tipo_rotulo((string) $r->tipo) : (string) $r->tipo); ?></td>
                    <td><?php echo esc_html((string) ($r->alvo_ref ?: ('#' . (int) $r->alvo_id))); ?></td>
                    <td><span class="<?php echo esc_attr($cls); ?>"><?php echo esc_html((string) $r->estado); ?></span></td>
                    <td><?php echo esc_html((string) $r->solicitante_nome); ?></td>
                    <td><?php echo esc_html((string) ($r->aprovador_nome ?? '')); ?></td>
                    <td><?php echo esc_html((string) ($r->decidido_em ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
