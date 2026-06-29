<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

if (!function_exists('sige_mpesa_pode_gerir') || !sige_mpesa_pode_gerir()) {
    echo '<div class="notice notice-warning" style="margin:20px;border-radius:8px;"><p>Esta área é reservada à Direcção e à Secretaria Geral.</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$tX = $wpdb->prefix . 'sige_mpesa_transacoes';
$tabela_existe = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tX)) === $tX;
$ambiente = sige_mpesa_ambiente();
$configurado = sige_mpesa_configurado();
$emola_ambiente = function_exists('sige_emola_ambiente') ? sige_emola_ambiente() : 'off';
$emola_configurado = function_exists('sige_emola_configurado') && sige_emola_configurado();
$nonce = wp_create_nonce('sige_mpesa');
$ok_msg = isset($_GET['ok']) && $_GET['ok'] === 'config';

// Resolucao com quatro-olhos: o maker propoe; nao executa. A execucao so acontece
// quando um segundo utilizador autorizado aprova em Aprovacoes (sige_fin_aprovacao_*).
$sg_recon_aviso = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sige_recon_propor'])) {
    if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'sige_recon_propor')) {
        $sg_recon_aviso = 'Sessao expirada. Tente novamente.';
    } elseif ($escola_id <= 0) {
        $sg_recon_aviso = 'Escola nao identificada. Operacao bloqueada.';
    } else {
        $sg_acao = sanitize_text_field((string) $_POST['sige_recon_propor']);
        $sg_tx = (int) ($_POST['tx_id'] ?? 0);
        if ($sg_acao === 'conciliar' && function_exists('sige_recon_propor_conciliacao')) {
            $sg_lanc = (int) ($_POST['lancamento_id'] ?? 0);
            $sg_r = sige_recon_propor_conciliacao($sg_tx, $sg_lanc, $escola_id);
        } elseif ($sg_acao === 'rejeitar' && function_exists('sige_recon_propor_rejeicao')) {
            $sg_motivo = sanitize_text_field(wp_unslash((string) ($_POST['motivo'] ?? '')));
            $sg_r = sige_recon_propor_rejeicao($sg_tx, $sg_motivo, $escola_id);
        } else {
            $sg_r = ['ok' => false, 'error' => 'Accao desconhecida.'];
        }
        $sg_recon_aviso = !empty($sg_r['ok'])
            ? 'Pedido submetido para aprovacao de um segundo utilizador (quatro-olhos).'
            : ('Nao foi possivel submeter: ' . (string) ($sg_r['error'] ?? 'erro.'));
    }
}
$ok_emola = isset($_GET['ok']) && $_GET['ok'] === 'emola';

$txs = [];
$pendentes = 0;
if ($tabela_existe) {
    $txs = $wpdb->get_results($wpdb->prepare(
        "SELECT x.*, a.nome_completo FROM {$tX} x
           LEFT JOIN {$wpdb->prefix}sige_alunos a ON a.id = x.aluno_id
          WHERE x.escola_id = %d ORDER BY x.id DESC LIMIT 50", $escola_id
    ));
    $pendentes = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$tX} WHERE escola_id = %d AND estado = 'pendente_manual'", $escola_id
    ));
}
$badges = [
    'recebida' => ['Recebida', 'sgk-badge-aviso'],
    'conciliada' => ['Conciliada', 'sgk-badge-ok'],
    'pendente_manual' => ['Pendente manual', 'sgk-badge-erro'],
    'rejeitada' => ['Rejeitada', 'sgk-badge-neutro'],
];
?>
<div class="wrap" style="max-width:1100px;padding:18px;">
    <h1 style="display:flex;align-items:center;gap:12px;color:#0d1259;margin-bottom:4px;">
        <span style="font-size:28px;">📱</span> Pagamentos Móveis (M-Pesa / e-Mola)
    </h1>
    <p style="color:#475569;margin-top:0;max-width:760px;">
        O encarregado paga do telemóvel usando o <strong>número de processo do aluno</strong>
        como referência (o mesmo do crachá). A conciliação é automática: o pagamento
        liquida o lançamento em aberto mais antigo e o recibo segue por WhatsApp,
        como num pagamento ao balcão.
    </p>

    <?php if ($ok_msg): ?>
    <div class="sgk-banner sgk-banner-ok">✅ Configuração guardada.</div>
    <?php endif; ?>
    <?php if ($ok_emola): ?>
    <div class="sgk-banner sgk-banner-ok">✅ Configuração e-Mola guardada.</div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
        <!-- Estado -->
        <div class="sgk-card">
            <h2 style="margin:0 0 10px;color:#0d1259;font-size:16px;">Estado do canal</h2>
            <div style="display:grid;gap:8px;font-size:13.5px;color:#334155;">
                <div>Ambiente: <strong style="color:<?php echo $ambiente === 'producao' ? '#166534' : ($ambiente === 'sandbox' ? '#854d0e' : '#991b1b'); ?>;"><?php
                    echo $ambiente === 'off' ? 'DESLIGADO' : strtoupper($ambiente); ?></strong></div>
                <div>Credenciais: <strong><?php echo $configurado ? 'completas' : 'em falta'; ?></strong></div>
                <div>Pendentes de decisão manual: <strong><?php echo (int)$pendentes; ?></strong></div>
            </div>
            <h3 style="margin:16px 0 6px;color:#0d1259;font-size:13.5px;">Endereço do webhook (dar à Vodacom)</h3>
            <div style="display:flex;gap:6px;align-items:center;">
                <input type="text" readonly id="sgMpWebhook" value="<?php echo esc_attr(sige_mpesa_webhook_url()); ?>"
                       style="flex:1;padding:8px 10px;border-radius:8px;border:1px solid #cbd5e1;font-size:11.5px;color:#475569;">
                <button type="button" class="button" id="sgMpCopiar">Copiar</button>
            </div>
            <p style="color:#64748b;font-size:12px;margin:8px 0 0;">
                O token no fim do endereço autentica os callbacks. Tratar como uma palavra-passe.
            </p>
        </div>

        <!-- Credenciais -->
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              class="sgk-card">
            <input type="hidden" name="action" value="sige_mpesa_guardar_config">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sige_mpesa_config')); ?>">
            <h2 style="margin:0 0 10px;color:#0d1259;font-size:16px;">Credenciais (portal developer.mpesa.vm.co.mz)</h2>
            <div style="display:grid;gap:10px;">
                <label style="font-size:13px;color:#0f172a;">Ambiente
                    <select name="ambiente" style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                        <option value="off" <?php selected($ambiente, 'off'); ?>>Desligado</option>
                        <option value="sandbox" <?php selected($ambiente, 'sandbox'); ?>>Sandbox (testes)</option>
                        <option value="producao" <?php selected($ambiente, 'producao'); ?>>Produção</option>
                    </select>
                </label>
                <label style="font-size:13px;color:#0f172a;">Service Provider Code (código da carteira)
                    <input type="text" name="provider_code" value="<?php echo esc_attr(sige_mpesa_opt('provider_code')); ?>"
                           style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                </label>
                <label style="font-size:13px;color:#0f172a;">API Key <span style="color:#64748b;">(deixar vazio para manter a actual<?php echo sige_mpesa_opt('api_key') !== '' ? ': definida' : ''; ?>)</span>
                    <input type="password" name="api_key" autocomplete="new-password"
                           style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                </label>
                <label style="font-size:13px;color:#0f172a;">Public Key <span style="color:#64748b;">(deixar vazio para manter<?php echo sige_mpesa_opt('public_key') !== '' ? ': definida' : ''; ?>)</span>
                    <textarea name="public_key" rows="3" placeholder="PEM completo ou base64 do portal"
                              style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;font-size:11px;"></textarea>
                </label>
                <button type="submit" class="button button-primary" style="justify-self:start;">Guardar configuração</button>
            </div>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;margin-top:16px;">
        <!-- Estado e-Mola -->
        <div class="sgk-card" style="border-top:3px solid #f97316;">
            <h2 style="margin:0 0 10px;color:#0d1259;font-size:16px;">e-Mola (Movitel) <span style="font-size:11px;background:#ffedd5;color:#9a3412;border-radius:6px;padding:2px 8px;vertical-align:middle;">webhook-first</span></h2>
            <div style="display:grid;gap:8px;font-size:13.5px;color:#334155;">
                <div>Ambiente: <strong style="color:<?php echo $emola_ambiente === 'producao' ? '#166534' : ($emola_ambiente === 'sandbox' ? '#854d0e' : '#991b1b'); ?>;"><?php echo $emola_ambiente === 'off' ? 'DESLIGADO' : strtoupper($emola_ambiente); ?></strong></div>
                <div>Credenciais: <strong><?php echo $emola_configurado ? 'completas' : 'em falta'; ?></strong></div>
            </div>
            <h3 style="margin:16px 0 6px;color:#0d1259;font-size:13.5px;">Endereço do webhook (dar à Movitel)</h3>
            <div style="display:flex;gap:6px;align-items:center;">
                <input type="text" readonly id="sgEmWebhook" value="<?php echo esc_attr(function_exists('sige_emola_webhook_url') ? sige_emola_webhook_url() : ''); ?>"
                       style="flex:1;padding:8px 10px;border-radius:8px;border:1px solid #cbd5e1;font-size:11.5px;color:#475569;">
                <button type="button" class="button" id="sgEmCopiar">Copiar</button>
            </div>
            <p style="color:#64748b;font-size:12px;margin:8px 0 0;">
                A recepção de pagamentos por callback já está activa quando o ambiente
                deixar de estar Desligado. A cobrança push e-Mola fica pronta a ligar
                no dia em que a documentação oficial da Movitel chegar.
            </p>
        </div>

        <!-- Credenciais e-Mola -->
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              class="sgk-card" style="border-top:3px solid #f97316;">
            <input type="hidden" name="action" value="sige_emola_guardar_config">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sige_emola_config')); ?>">
            <h2 style="margin:0 0 10px;color:#0d1259;font-size:16px;">Credenciais e-Mola</h2>
            <div style="display:grid;gap:10px;">
                <label style="font-size:13px;color:#0f172a;">Ambiente
                    <select name="ambiente" style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                        <option value="off" <?php selected($emola_ambiente, 'off'); ?>>Desligado</option>
                        <option value="sandbox" <?php selected($emola_ambiente, 'sandbox'); ?>>Sandbox (testes)</option>
                        <option value="producao" <?php selected($emola_ambiente, 'producao'); ?>>Produção</option>
                    </select>
                </label>
                <label style="font-size:13px;color:#0f172a;">Host da API <span style="color:#64748b;">(da documentação Movitel)</span>
                    <input type="text" name="host" value="<?php echo esc_attr(function_exists('sige_emola_opt') ? sige_emola_opt('host') : ''); ?>" placeholder="api.emola.co.mz" style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                </label>
                <label style="font-size:13px;color:#0f172a;">Código de comerciante/carteira
                    <input type="text" name="merchant_code" value="<?php echo esc_attr(function_exists('sige_emola_opt') ? sige_emola_opt('merchant_code') : ''); ?>" style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                </label>
                <label style="font-size:13px;color:#0f172a;">API Key <span style="color:#64748b;">(vazio = manter<?php echo (function_exists('sige_emola_opt') && sige_emola_opt('api_key') !== '') ? ': definida' : ''; ?>)</span>
                    <input type="password" name="api_key" autocomplete="new-password" style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                </label>
                <label style="font-size:13px;color:#0f172a;">API Secret <span style="color:#64748b;">(vazio = manter<?php echo (function_exists('sige_emola_opt') && sige_emola_opt('api_secret') !== '') ? ': definido' : ''; ?>)</span>
                    <input type="password" name="api_secret" autocomplete="new-password" style="width:100%;padding:8px;border-radius:8px;border:1px solid #cbd5e1;margin-top:4px;">
                </label>
                <button type="submit" class="button button-primary" style="justify-self:start;">Guardar e-Mola</button>
            </div>
        </form>
    </div>

    <?php if ($configurado && $ambiente !== 'off'): ?>
    <div class="sgk-card" style="margin-top:16px;">
        <h2 style="margin:0 0 6px;color:#0d1259;font-size:16px;">Cobrar agora (push no telemóvel)</h2>
        <p style="color:#64748b;font-size:12.5px;margin:0 0 12px;">O encarregado recebe o pedido no M-Pesa e confirma com o PIN. Útil ao balcão e nas campanhas de cobrança.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <label style="font-size:12.5px;color:#0f172a;display:grid;gap:4px;">Nº de processo
                <input type="text" id="sgMpPushProc" inputmode="numeric" style="width:140px;padding:8px;border-radius:8px;border:1px solid #cbd5e1;">
            </label>
            <label style="font-size:12.5px;color:#0f172a;display:grid;gap:4px;">Telefone M-Pesa do encarregado
                <input type="text" id="sgMpPushTel" placeholder="84 XXX XXXX" style="width:170px;padding:8px;border-radius:8px;border:1px solid #cbd5e1;">
            </label>
            <label style="font-size:12.5px;color:#0f172a;display:grid;gap:4px;">Valor (MT)
                <input type="text" id="sgMpPushValor" inputmode="decimal" style="width:120px;padding:8px;border-radius:8px;border:1px solid #cbd5e1;">
            </label>
            <button type="button" class="button button-primary" id="sgMpPushBtn" style="height:38px;">Enviar pedido</button>
        </div>
        <div id="sgMpPushOut" style="display:none;margin-top:12px;padding:10px 12px;border-radius:9px;font-size:13px;"></div>
    </div>
    <?php endif; ?>

    <h2 style="color:#0d1259;font-size:16px;margin:22px 0 10px;">Últimas transacções</h2>
    <div class="sgk-table-wrap" style="background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);">
        <?php if (!$tabela_existe): ?>
        <div style="padding:24px;color:#64748b;">A tabela de transacções será criada automaticamente na próxima visita de um administrador (migração de schema). Recarregue dentro de instantes.</div>
        <?php elseif (empty($txs)): ?>
        <div style="padding:24px;color:#64748b;">Ainda sem transacções. Quando o canal estiver ligado e a Vodacom enviar o primeiro callback, aparece aqui.</div>
        <?php else: ?>
        <table style="border-collapse:collapse;width:100%;font-size:12.5px;">
            <thead><tr style="background:#0d1259;color:#fff;">
                <th style="padding:8px;text-align:left;">Data</th>
                <th style="padding:8px;text-align:left;">Canal</th>
                <th style="padding:8px;text-align:left;">Referência</th>
                <th style="padding:8px;text-align:left;">Telefone</th>
                <th style="padding:8px;text-align:left;">Aluno</th>
                <th style="padding:8px;text-align:right;">Valor</th>
                <th style="padding:8px;text-align:left;">Estado</th>
                <th style="padding:8px;text-align:left;">Detalhe</th>
                <th style="padding:8px;"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($txs as $t): $b = $badges[$t->estado] ?? [$t->estado, 'sgk-badge-neutro']; ?>
                <tr style="border-bottom:1px solid #eef2f7;">
                    <td style="padding:7px 8px;white-space:nowrap;"><?php echo esc_html(substr((string)$t->criado_em, 0, 16)); ?></td>
                    <td style="padding:7px 8px;"><?php $prov = strtolower((string)($t->provider ?? 'mpesa')); ?><span class="sgk-badge <?php echo $prov === 'emola' ? 'sgk-badge-aviso' : 'sgk-badge-erro'; ?>"><?php echo $prov === 'emola' ? 'e-Mola' : 'M-Pesa'; ?></span></td>
                    <td style="padding:7px 8px;font-family:monospace;"><?php echo esc_html($t->referencia_mpesa); ?></td>
                    <td style="padding:7px 8px;"><?php echo esc_html($t->msisdn ? substr((string)$t->msisdn, 0, 5) . '****' . substr((string)$t->msisdn, -3) : ''); ?></td>
                    <td style="padding:7px 8px;"><?php echo esc_html($t->nome_completo ?: ($t->referencia_cliente ? 'Ref.: ' . $t->referencia_cliente : '')); ?></td>
                    <td style="padding:7px 8px;text-align:right;font-weight:600;"><?php echo esc_html(number_format((float)$t->valor, 2, ',', '.')); ?> MT</td>
                    <td style="padding:7px 8px;"><span class="sgk-badge <?php echo esc_attr($b[1]); ?>"><?php echo esc_html($b[0]); ?></span></td>
                    <td style="padding:7px 8px;color:#64748b;max-width:240px;"><?php
                        if ($t->estado === 'conciliada' && $t->lancamento_id) {
                            echo 'Lançamento #' . (int)$t->lancamento_id . ' · ' . esc_html((string)$t->conciliado_por);
                        } else {
                            echo esc_html((string)($t->erro ?? ''));
                        }
                    ?></td>
                    <td style="padding:7px 8px;white-space:nowrap;">
                        <?php if (in_array($t->estado, ['pendente_manual', 'recebida'], true)): ?>
                        <button type="button" class="button button-small sg-mp-conciliar"
                                data-tx="<?php echo (int)$t->id; ?>"
                                data-aluno="<?php echo (int)$t->aluno_id; ?>"
                                data-ref="<?php echo esc_attr((string)$t->referencia_cliente); ?>"
                                data-valor="<?php echo esc_attr(number_format((float)$t->valor, 2, ',', '.')); ?>">Conciliar</button>
                        <button type="button" class="button button-small sg-mp-rejeitar" data-tx="<?php echo (int)$t->id; ?>" data-ref="<?php echo esc_attr($t->referencia_mpesa); ?>">Rejeitar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <p style="color:#64748b;font-size:12px;margin-top:10px;">
        Política de conciliação: o valor recebido liquida o lançamento em aberto mais antigo.
        Valores que excedem esse lançamento ficam pendentes para decisão humana: o sistema
        nunca divide dinheiro sozinho.
    </p>

    <?php $sg_pendentes = array_filter((array) $txs, static function ($t) { return in_array($t->estado, ['recebida', 'pendente_manual'], true); }); ?>
    <h2 style="color:#0d1259;font-size:16px;margin:26px 0 8px;">Resolução com quatro-olhos</h2>
    <p style="color:#64748b;font-size:12.5px;margin:0 0 10px;max-width:760px;">
        Aqui pode <strong>propor</strong> conciliar ou rejeitar uma transacção pendente. A proposta não é
        executada de imediato: fica a aguardar a aprovação de um <strong>segundo utilizador autorizado</strong>
        (não pode ser quem propõe). As aprovações pendentes são decididas em
        <a href="?page=sige-app&amp;view=financeiro-aprovacoes">Aprovações</a>.
        Na conciliação pode deixar a correspondência automática ou escolher um lançamento específico do aluno.
    </p>
    <?php if ($sg_recon_aviso !== ''): ?>
        <div class="notice notice-info" style="margin:0 0 12px;border-radius:8px;"><p><?php echo esc_html($sg_recon_aviso); ?></p></div>
    <?php endif; ?>
    <?php if (empty($sg_pendentes)): ?>
        <p style="color:#64748b;font-size:12.5px;">Não há transacções pendentes para resolver.</p>
    <?php else: ?>
        <div class="sgk-table-wrap" style="background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:6px 0;">
            <table style="border-collapse:collapse;width:100%;font-size:12.5px;">
                <thead><tr style="background:#f1f5f9;color:#0d1259;">
                    <th style="padding:8px;text-align:left;">Referência</th>
                    <th style="padding:8px;text-align:right;">Valor</th>
                    <th style="padding:8px;text-align:left;">Estado</th>
                    <th style="padding:8px;text-align:left;">Propor (quatro-olhos)</th>
                </tr></thead>
                <tbody>
                <?php foreach ($sg_pendentes as $t): ?>
                    <?php $sg_abertos = (function_exists('sige_mpesa_lancamentos_abertos') && (int) ($t->aluno_id ?? 0) > 0) ? sige_mpesa_lancamentos_abertos($escola_id, (int) $t->aluno_id) : []; ?>
                    <tr style="border-bottom:1px solid #eef2f7;">
                        <td style="padding:7px 8px;font-family:monospace;"><?php echo esc_html((string) $t->referencia_mpesa); ?></td>
                        <td style="padding:7px 8px;text-align:right;font-weight:600;"><?php echo esc_html(number_format((float) $t->valor, 2, ',', '.')); ?> MT</td>
                        <td style="padding:7px 8px;"><?php echo esc_html((string) $t->estado); ?></td>
                        <td style="padding:7px 8px;">
                            <form method="post" style="display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0;">
                                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sige_recon_propor')); ?>">
                                <input type="hidden" name="tx_id" value="<?php echo (int) $t->id; ?>">
                                <select name="lancamento_id" style="font-size:12px;padding:2px 6px;">
                                    <option value="0">Lançamento: automático</option>
                                    <?php foreach ($sg_abertos as $ab): ?>
                                        <option value="<?php echo (int) $ab['id']; ?>">#<?php echo (int) $ab['id']; ?> &middot; vence <?php echo esc_html(substr((string) $ab['data_vencimento'], 0, 10)); ?> &middot; <?php echo esc_html(number_format((float) $ab['saldo'], 2, ',', '.')); ?> MT</option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="sige_recon_propor" value="conciliar" class="button button-small">Propor conciliação</button>
                                <input type="text" name="motivo" placeholder="motivo (rejeição)" style="font-size:12px;padding:2px 6px;width:150px;">
                                <button type="submit" name="sige_recon_propor" value="rejeitar" class="button button-small">Propor rejeição</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de conciliação manual -->
<div id="sgMpModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:99999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;padding:20px;width:min(520px,94vw);box-shadow:0 20px 60px rgba(0,0,0,.3);">
        <h3 style="margin:0 0 4px;color:#0d1259;">Conciliar transacção</h3>
        <p style="margin:0 0 12px;color:#64748b;font-size:13px;" id="sgMpModalSub"></p>
        <div id="sgMpLista" style="display:grid;gap:6px;max-height:280px;overflow:auto;color:#0f172a;font-size:13px;">A carregar lançamentos...</div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
            <button type="button" class="button" id="sgMpCancelar">Cancelar</button>
            <button type="button" class="button button-primary" id="sgMpConfirmar" disabled>Registar pagamento</button>
        </div>
    </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
    var ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    var nonce = <?php echo wp_json_encode($nonce); ?>;
    var copiar = document.getElementById('sgMpCopiar');
    if (copiar) copiar.addEventListener('click', function(){
        var i = document.getElementById('sgMpWebhook'); i.select();
        try { document.execCommand('copy'); copiar.textContent = 'Copiado!'; setTimeout(function(){ copiar.textContent='Copiar'; }, 1500); } catch(e) {}
    });
    var copiarEm = document.getElementById('sgEmCopiar');
    if (copiarEm) copiarEm.addEventListener('click', function(){
        var i = document.getElementById('sgEmWebhook'); i.select();
        try { document.execCommand('copy'); copiarEm.textContent = 'Copiado!'; setTimeout(function(){ copiarEm.textContent='Copiar'; }, 1500); } catch(e) {}
    });

    var modal = document.getElementById('sgMpModal');
    var lista = document.getElementById('sgMpLista');
    var confirmar = document.getElementById('sgMpConfirmar');
    var txActiva = 0, lancEscolhido = 0;

    function fechar(){ modal.style.display='none'; txActiva=0; lancEscolhido=0; confirmar.disabled=true; }
    document.getElementById('sgMpCancelar').addEventListener('click', fechar);
    modal.addEventListener('click', function(ev){ if (ev.target === modal) fechar(); });

    document.querySelectorAll('.sg-mp-conciliar').forEach(function(btn){
        btn.addEventListener('click', function(){
            txActiva = parseInt(btn.dataset.tx, 10); lancEscolhido = 0; confirmar.disabled = true;
            document.getElementById('sgMpModalSub').textContent = 'Valor recebido: ' + btn.dataset.valor + ' MT · Ref.: ' + (btn.dataset.ref || '(sem referência)');
            lista.textContent = 'A carregar lançamentos...';
            modal.style.display = 'flex';
            var fd = new FormData();
            fd.append('action','sige_mpesa_abertos'); fd.append('_wpnonce',nonce);
            fd.append('aluno_id', btn.dataset.aluno || '0'); fd.append('processo', btn.dataset.ref || '');
            fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
              .then(function(r){return r.json();})
              .then(function(j){
                  if(!j || !j.success){ lista.textContent = (j&&j.data)||'Falha ao carregar.'; return; }
                  if(!j.data.lancamentos.length){ lista.textContent = 'Este aluno não tem lançamentos em aberto.'; return; }
                  lista.innerHTML = '';
                  j.data.lancamentos.forEach(function(l){
                      var lab = document.createElement('label');
                      lab.style.cssText = 'display:flex;gap:8px;align-items:center;border:1px solid #e2e8f0;border-radius:9px;padding:8px 10px;cursor:pointer;';
                      lab.innerHTML = '<input type="radio" name="sgMpLanc" value="'+l.id+'"> <span class="sige-u-flex-1">#'+l.id+' · '+(l.descricao||l.mes||'')+' · vence '+l.vencimento+'</span><strong>'+l.saldo.toFixed(2).replace('.',',')+' MT</strong>';
                      lab.querySelector('input').addEventListener('change', function(){ lancEscolhido = l.id; confirmar.disabled = false; });
                      lista.appendChild(lab);
                  });
              })
              .catch(function(){ lista.textContent='Falha de ligação.'; });
        });
    });

    confirmar.addEventListener('click', function(){
        if (!txActiva || !lancEscolhido) return;
        confirmar.disabled = true; confirmar.textContent = 'A registar...';
        var fd = new FormData();
        fd.append('action','sige_mpesa_conciliar_manual'); fd.append('_wpnonce',nonce);
        fd.append('tx_id',txActiva); fd.append('lancamento_id',lancEscolhido);
        fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
          .then(function(r){return r.json();})
          .then(function(j){
              if(!j || !j.success){ sigeUi.toast((j&&j.data)||'Não foi possível registar.', 'erro'); confirmar.disabled=false; confirmar.textContent='Registar pagamento'; return; }
              sigeUi.toast('Pagamento registado; o recibo segue na fila WhatsApp.', 'ok');
              setTimeout(function(){ location.reload(); }, 800);
          })
          .catch(function(){ sigeUi.toast('Falha de ligação. Verifique a internet e tente novamente.', 'erro'); confirmar.disabled=false; confirmar.textContent='Registar pagamento'; });
    });

    document.querySelectorAll('.sg-mp-rejeitar').forEach(function(btn){
        btn.addEventListener('click', async function(){
            var ref = btn.dataset.ref || ('#' + btn.dataset.tx);
            var motivo = await sigeUi.prompt({
                titulo: 'Rejeitar transacção',
                texto: 'A transacção ' + ref + ' será marcada como rejeitada e sai da fila de conciliação. Indique o motivo (fica no registo):',
                placeholder: 'Ex.: valor não corresponde a nenhuma dívida em aberto',
                confirmar: 'Rejeitar',
                perigo: true,
                obrigatorio: true
            });
            if (motivo === null) return;
            var fd = new FormData();
            fd.append('action','sige_mpesa_rejeitar'); fd.append('_wpnonce',nonce);
            fd.append('tx_id',btn.dataset.tx); fd.append('motivo',motivo);
            fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
              .then(function(r){return r.json();})
              .then(function(j){ if(!j||!j.success){ sigeUi.toast((j&&j.data)||'Não foi possível rejeitar.', 'erro'); return; } sigeUi.toast('Transacção rejeitada.', 'ok'); setTimeout(function(){ location.reload(); }, 800); })
              .catch(function(){ sigeUi.toast('Falha de ligação. Verifique a internet e tente novamente.', 'erro'); });
        });
    });

    var pushBtn = document.getElementById('sgMpPushBtn');
    if (pushBtn) pushBtn.addEventListener('click', function(){
        var out = document.getElementById('sgMpPushOut');
        var proc = document.getElementById('sgMpPushProc').value.trim();
        var tel = document.getElementById('sgMpPushTel').value.trim();
        var val = document.getElementById('sgMpPushValor').value.trim();
        if (!proc || !tel || !val){ sigeUi.toast('Preencha processo, telefone e valor.', 'aviso'); return; }
        pushBtn.disabled = true; pushBtn.textContent = 'A aguardar confirmação no telemóvel...';
        out.style.display = 'none';
        var fd = new FormData();
        fd.append('action','sige_mpesa_cobrar'); fd.append('_wpnonce',nonce);
        fd.append('processo',proc); fd.append('telefone',tel); fd.append('valor',val);
        fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd})
          .then(function(r){return r.json();})
          .then(function(j){
              pushBtn.disabled = false; pushBtn.textContent = 'Enviar pedido';
              out.style.display = '';
              if (j && j.success){
                  out.style.background = '#ecfdf5'; out.style.border = '1px solid #a7f3d0'; out.style.color = '#065f46';
                  out.textContent = '✅ ' + j.data.mensagem + ' Ref.: ' + j.data.referencia;
                  setTimeout(function(){ location.reload(); }, 2500);
              } else {
                  out.style.background = '#fef2f2'; out.style.border = '1px solid #fecaca'; out.style.color = '#991b1b';
                  out.textContent = '⚠ ' + ((j && j.data) || 'Falhou.');
              }
          })
          .catch(function(){ pushBtn.disabled=false; pushBtn.textContent='Enviar pedido'; out.style.display=''; out.style.background='#fef2f2'; out.style.color='#991b1b'; out.textContent='Falha de ligação.'; });
    });
})();
</script>
