<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: resolucao de divergencias com escrita
 * (quatro-olhos) (Fase 3, incremento 3).
 * Tranca: execucao que delega no caminho canonico e re-valida o estado, propostas
 * que so criam pedidos pendentes, tipos e despacho ligados ao framework de
 * aprovacoes (sem nova permissao), e UI do maker so server-side na vista de gestao.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

$acc = $read('includes/payments/reconciliacao-accoes.php');
if ($acc === '') {
    $fails[] = 'includes/payments/reconciliacao-accoes.php ausente';
} else {
    foreach (['sige_recon_executar_conciliar', 'sige_recon_executar_rejeitar', 'sige_recon_propor_conciliacao', 'sige_recon_propor_rejeicao'] as $fn) {
        if (strpos($acc, "function {$fn}(") === false) $fails[] = "falta a funcao {$fn}";
    }
    // Conciliacao passa pelo caminho canonico (nunca calcula valores).
    if (strpos($acc, 'sige_fin_registar_pagamento(') === false) {
        $fails[] = 'a conciliacao nao delega no caminho canonico sige_fin_registar_pagamento';
    }
    // Nunca escreve directamente em lancamentos (so na tabela de transacoes).
    if (strpos($acc, 'sige_fin_lancamentos') !== false && preg_match('/sige_fin_lancamentos[^;]*->(insert|update|delete)\(/', $acc)) {
        $fails[] = 'a resolucao escreve directamente em lancamentos (proibido)';
    }
    // Re-valida o estado no momento da execucao.
    if (strpos($acc, "in_array(\$tx->estado, ['recebida', 'pendente_manual'], true)") === false) {
        $fails[] = 'a conciliacao nao re-valida o estado pendente';
    }
    if (strpos($acc, "\$tx->estado === 'conciliada'") === false) {
        $fails[] = 'a rejeicao nao protege transacoes ja conciliadas';
    }
    // Fail-closed e tenant guard.
    if (substr_count($acc, 'if ($escola_id <= 0) return') < 2) $fails[] = 'execucao nao e fail-closed em ambas as accoes';
    if (strpos($acc, 'sige_tenant_write_guard') === false) $fails[] = 'execucao nao respeita o tenant guard';
    // Propostas criam pedido pendente (nao executam).
    if (strpos($acc, "sige_fin_aprovacao_solicitar('recon_conciliar'") === false || strpos($acc, "sige_fin_aprovacao_solicitar('recon_rejeitar'") === false) {
        $fails[] = 'as propostas nao usam o framework de aprovacoes (solicitar)';
    }
}

// Framework: tipos e despacho.
$apr = $read('includes/finance-aprovacoes.php');
if ($apr === '') {
    $fails[] = 'includes/finance-aprovacoes.php ausente';
} else {
    foreach (['recon_conciliar', 'recon_rejeitar'] as $tipo) {
        if (strpos($apr, "'{$tipo}'") === false) $fails[] = "tipo de aprovacao {$tipo} nao registado";
    }
    // Reutiliza a permissao existente (sem nova permissao).
    if (substr_count($apr, 'financeiro.mobile_payments_gerir') < 2) {
        $fails[] = 'os tipos de reconciliacao nao reutilizam a permissao financeiro.mobile_payments_gerir';
    }
    if (strpos($apr, 'sige_recon_executar_conciliar(') === false || strpos($apr, 'sige_recon_executar_rejeitar(') === false) {
        $fails[] = 'o despacho de aprovacoes nao encaminha para a execucao da reconciliacao';
    }
}

// UI do maker: so server-side (sem add_action), na vista de gestao.
$view = $read('admin/finance/mpesa-view.php');
if ($view === '') {
    $fails[] = 'admin/finance/mpesa-view.php ausente';
} else {
    if (strpos($view, "wp_verify_nonce") === false || strpos($view, "'sige_recon_propor'") === false) {
        $fails[] = 'a vista nao tem o handler server-side do maker com nonce';
    }
    if (strpos($view, 'sige_recon_propor_conciliacao(') === false || strpos($view, 'sige_recon_propor_rejeicao(') === false) {
        $fails[] = 'a vista nao liga aos helpers de proposta';
    }
    if (strpos($view, 'financeiro-aprovacoes') === false) {
        $fails[] = 'a vista nao remete para a fila de aprovacoes (checker)';
    }
    // Escolha de lancamento especifico na proposta de conciliacao.
    if (strpos($view, 'sige_mpesa_lancamentos_abertos(') === false || strpos($view, 'name="lancamento_id"') === false) {
        $fails[] = 'a vista nao oferece a escolha de lancamento na proposta de conciliacao';
    }
    if (strpos($view, "(int) (\$_POST['lancamento_id']") === false && strpos($view, "(int)(\$_POST['lancamento_id']") === false) {
        $fails[] = 'o handler do maker nao le o lancamento escolhido';
    }
}

// Carregada pelo bootstrap.
if (strpos($read('sige-softgenial.php'), 'reconciliacao-accoes.php') === false) {
    $fails[] = 'reconciliacao-accoes.php nao e carregada pelo bootstrap';
}

if ($fails) {
    fwrite(STDERR, "RESOLUCAO QUATRO-OLHOS FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'RESOLUCAO QUATRO-OLHOS OK - execucao canonica e re-validada, propostas via aprovacoes, tipos e despacho ligados (sem nova permissao), UI do maker so server-side.' . "\n";
