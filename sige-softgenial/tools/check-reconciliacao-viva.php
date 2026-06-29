<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: reconciliacao viva (verificacao contra o gateway)
 * (Fase 3, incremento 2).
 * Tranca: nucleo puro de classificacao, normalizador conservador, adaptador
 * defensivo aos clientes, orquestracao por escola fail-closed e so leitura, e
 * passagem diaria no cron guardada. A camada nunca altera pagamentos nem transacoes.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

$viva = $read('includes/payments/reconciliacao-viva.php');
if ($viva === '') {
    $fails[] = 'includes/payments/reconciliacao-viva.php ausente';
} else {
    foreach (['sige_pagamentos_reconciliar_estado', 'sige_pagamentos_normalizar_estado_gateway', 'sige_pagamentos_consultar_gateway', 'sige_reconciliacao_viva'] as $fn) {
        if (strpos($viva, "function {$fn}(") === false) $fails[] = "falta a funcao {$fn}";
    }
    // Nucleo puro nao depende de $wpdb.
    $ini = strpos($viva, 'function sige_pagamentos_reconciliar_estado(');
    $fim = strpos($viva, 'function sige_pagamentos_normalizar_estado_gateway(');
    if ($ini !== false && $fim !== false && $fim > $ini) {
        if (strpos(substr($viva, $ini, $fim - $ini), '$wpdb') !== false) {
            $fails[] = 'o nucleo puro nao deve depender de $wpdb';
        }
    }
    // Normalizador conservador: falha so por codigo conhecido; resto desconhecida.
    if (strpos($viva, "'sige_mpesa_codigos_falha'") === false && strpos($viva, 'sige_mpesa_codigos_falha') === false) {
        $fails[] = 'normalizador nao limita a falha a codigos conhecidos (conservador)';
    }
    if (strpos($viva, "'estado' => 'desconhecida'") === false) {
        $fails[] = 'normalizador nao tem o ramo conservador desconhecida';
    }
    // Afinacao: o estado da transacao tem prioridade, com listas ajustaveis por filtro.
    if (strpos($viva, 'output_responsetransactionstatus') === false) {
        $fails[] = 'normalizador nao inspecciona o estado da transacao do gateway';
    }
    if (strpos($viva, "'sige_mpesa_estados_sucesso'") === false || strpos($viva, "'sige_mpesa_estados_falha'") === false) {
        $fails[] = 'normalizador nao expoe as listas de estados de sucesso e falha por filtro';
    }
    // Afinacao por operadora: mapa por provider e normalizador consciente do provider.
    if (strpos($viva, 'function sige_pagamentos_mapa_estados_gateway(') === false) {
        $fails[] = 'falta o mapa de estados e codigos por operadora';
    }
    if (strpos($viva, 'sige_pagamentos_normalizar_estado_gateway(array $r, string $provider') === false) {
        $fails[] = 'o normalizador nao e por operadora (sem parametro provider)';
    }
    if (strpos($viva, "apply_filters('sige_mpesa_estados_sucesso', \$sucesso, \$provider)") === false) {
        $fails[] = 'os filtros de estados nao recebem o provider como contexto';
    }
    // Adaptador defensivo: try/catch e guardas class_exists.
    if (strpos($viva, 'class_exists(\'SIGE_MPesa_Client\')') === false || strpos($viva, 'class_exists(\'SIGE_EMola_Client\')') === false) {
        $fails[] = 'adaptador nao protege a ausencia dos clientes (class_exists)';
    }
    if (!preg_match('/function sige_pagamentos_consultar_gateway\(.*?catch \(Throwable/s', $viva)) {
        $fails[] = 'adaptador nao e defensivo (sem try/catch)';
    }
    // Orquestracao fail-closed.
    if (!preg_match('/function sige_reconciliacao_viva\([^)]*\)\s*:\s*array\s*\{.*?if \(\$escola_id <= 0\) return \$out;/s', $viva)) {
        $fails[] = 'orquestracao nao e fail-closed para escola invalida';
    }
    // So leitura: nada de escrita em pagamentos/transacoes.
    foreach (['->insert(', '->update(', '->delete(', '->query('] as $w) {
        if (strpos($viva, $w) !== false) $fails[] = "reconciliacao viva deixou de ser so leitura ({$w})";
    }
    // Cron guardado (nao corre em modo de teste) e ligado ao evento diario.
    if (strpos($viva, "add_action('sige_evento_diario'") === false) {
        $fails[] = 'reconciliacao viva nao corre no cron diario';
    }
    if (!preg_match('/if \(!defined\(.SIGE_MPESA_TEST_MODE.\)\) \{.*?add_action\(.sige_evento_diario./s', $viva)) {
        $fails[] = 'o cron nao esta guardado pelo modo de teste';
    }
    // Regista divergencias (rasto), sem afirmar accao.
    if (strpos($viva, "'reconciliacao_viva_divergencia'") === false) {
        $fails[] = 'orquestracao nao regista as divergencias';
    }
}

// Carregada pelo bootstrap.
$boot = $read('sige-softgenial.php');
if (strpos($boot, 'reconciliacao-viva.php') === false) {
    $fails[] = 'reconciliacao-viva.php nao e carregada pelo bootstrap';
}

if ($fails) {
    fwrite(STDERR, "RECONCILIACAO VIVA FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'RECONCILIACAO VIVA OK - nucleo puro, normalizador conservador, adaptador defensivo, orquestracao fail-closed e so leitura, cron guardado.' . "\n";
