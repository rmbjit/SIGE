<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: reconciliacao de pagamentos digitais - deteccao
 * de duplicados (Fase 3, incremento 1).
 * Tranca: nucleo puro de deteccao com as tres classes, involucro por escola
 * fail-closed e so leitura, e integracao na vista de reconciliacao. A camada nunca
 * escreve nem altera pagamentos (so assinala para revisao).
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

$dom = $read('includes/payments/reconciliacao-divergencias.php');
if ($dom === '') {
    $fails[] = 'includes/payments/reconciliacao-divergencias.php ausente';
} else {
    foreach (['sige_pagamentos_detectar_duplicados', 'sige_reconciliacao_duplicados', 'sige_reconciliacao_duplicado_rotulo'] as $fn) {
        if (strpos($dom, "function {$fn}(") === false) $fails[] = "falta a funcao {$fn}";
    }
    foreach (['referencia_repetida', 'pagamento_duplo', 'mesmo_pagador_valor'] as $motivo) {
        if (strpos($dom, "'{$motivo}'") === false) $fails[] = "falta a classe de duplicado {$motivo}";
    }
    // Fail-closed no involucro por escola.
    if (!preg_match('/function sige_reconciliacao_duplicados\([^)]*\)\s*:\s*array\s*\{.*?if \(\$escola_id <= 0\) return \$out;/s', $dom)) {
        $fails[] = 'involucro de duplicados nao e fail-closed para escola invalida';
    }
    // So leitura: o ficheiro de dominio nunca escreve.
    foreach (['->insert(', '->update(', '->delete(', '->query('] as $w) {
        if (strpos($dom, $w) !== false) $fails[] = "camada de duplicados deixou de ser so leitura ({$w})";
    }
    // O nucleo puro nao depende de $wpdb (testavel em isolamento).
    $ini = strpos($dom, 'function sige_pagamentos_detectar_duplicados(');
    $fim = strpos($dom, "if (!function_exists('sige_reconciliacao_duplicados'))");
    if ($ini !== false && $fim !== false && $fim > $ini) {
        $corpo_puro = substr($dom, $ini, $fim - $ini);
        if (strpos($corpo_puro, '$wpdb') !== false) {
            $fails[] = 'o nucleo puro de deteccao nao deve depender de $wpdb';
        }
    }
}

// Integracao na vista (so leitura: a vista nao tem POST).
$view = $read('admin/finance/reconciliacao-view.php');
if ($view === '') {
    $fails[] = 'admin/finance/reconciliacao-view.php ausente';
} else {
    if (strpos($view, 'sige_reconciliacao_duplicados(') === false) {
        $fails[] = 'a vista nao apresenta os possiveis duplicados';
    }
    if (strpos($view, 'Possíveis duplicados') === false && strpos($view, 'Possiveis duplicados') === false) {
        $fails[] = 'a vista nao tem a seccao de possiveis duplicados';
    }
    if (preg_match('/\$_POST|->insert\(|->update\(/', $view)) {
        $fails[] = 'a vista de reconciliacao deixou de ser so leitura';
    }
}

if ($fails) {
    fwrite(STDERR, "RECONCILIACAO - DETECCAO DE DUPLICADOS FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'RECONCILIACAO - DETECCAO DE DUPLICADOS OK - tres classes, involucro fail-closed e so leitura, integrado na vista.' . "\n";
