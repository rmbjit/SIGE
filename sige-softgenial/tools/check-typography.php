<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate de Tipografia (hierarquia de pesos)
 *
 * ORIGEM (13 Jun 2026): auditoria de design encontrou 17 pesos de fonte
 * diferentes no sistema (650, 680, 720, 750, 760, 780, 850, 920, 950...).
 * A maioria NÃO existe nas fontes (renderizam arredondados a 400/500/
 * 600/700/800/900), e o uso de 850-950 em todo o lado destrói a
 * hierarquia visual (quando tudo é "extra-bold", nada se destaca).
 *
 * Regra profissional: escala tipográfica de poucos pesos REAIS. Os
 * ecrãs auditados usam 3 níveis (400 corpo / 600 ênfase / 700 título).
 *
 * Este gate:
 *  - conta pesos NÃO-canónicos (fora de 400/500/600/700/800/900) e FALHA
 *    se subirem acima da baseline (migração só reduz, como nos tokens);
 *  - garante que os ecrãs JÁ AUDITADOS mantêm a hierarquia (zero pesos
 *    não-canónicos neles).
 *
 * Uso:
 *   php tools/check-typography.php          verifica contra baseline
 *   php tools/check-typography.php --set     regista a baseline actual
 *   php tools/check-typography.php --report  piores ficheiros
 */

$raiz = dirname(__DIR__);
$baseline_file = $raiz . '/tools/.typography-baseline.json';

// Pesos REAIS das fontes (saltos de 100). Tudo o resto é ruído.
$canonicos = [400, 500, 600, 700, 800, 900];

// Ecrãs já auditados: DEVEM ter zero pesos não-canónicos.
$auditados = [
    'admin/system/config-center-view.php',
    'admin/academic/alunos_lista.php',
    'admin/finance/financeiro-extratos.php',
    'admin/academic/matriz-view.php',
    'admin/academic/aluno-portal-view.php',
    'admin/academic/disciplinas-view.php',
    'admin/academic/boletim-view.php',
    'admin/hr/equipe-view.php',
    'admin/academic/minhas_turmas-view.php',
    'admin/system/portaria-view.php',
    'admin/academic/encerramento-view.php',
    'admin/system/curriculum-engine-view.php',
    'admin/logistics/transporte-view.php',
    'admin/jardim/jardim_saude-view.php',
    'admin/jardim/jardim_diario-view.php',
    'admin/system/dashboard-view.php',
    'admin/academic/acta-view.php',
    'admin/academic/pautas-view.php',
    'admin/academic/estatisticas-demograficas-view.php',
    'admin/academic/abertura-view.php',
    'admin/finance/financeiro-pagamentos.php',
    'admin/system/permissions-ui.php',
    'admin/jardim/jardim_presencas-view.php',
    'admin/academic/pauta-final-view.php',
    'admin/whatsapp_central-view.php',
    'admin/academic/notas-view.php',
    'admin/jardim/jardim_relatorio-view.php',
    'admin/system/core-status-view.php',
    'admin/finance/financeiro-devedores-view.php',
    'admin/academic/turmas-view.php',
    'admin/academic/auditoria_notas-view.php',
    'admin/academic/dec-view.php',
    'admin/academic/aprovar_notas-view.php',
    'admin/jardim/jardim_boletim-view.php',
    'admin/finance/financeiro-lancamentos-view.php',
    'admin/finance/financeiro-config.php',
];

function pesos_de(string $ficheiro): array {
    $s = (string)file_get_contents($ficheiro);
    preg_match_all('/font-weight:\s*(\d+)/', $s, $m);
    return array_map('intval', $m[1]);
}

$alvos = glob($raiz . '/admin/*.php');
$alvos = array_merge($alvos, glob($raiz . '/admin/*/*.php'));
sort($alvos);

$total_nc = 0;
$por_ficheiro = [];
foreach ($alvos as $f) {
    $rel = str_replace($raiz . '/', '', $f);
    $pesos = pesos_de($f);
    $nc = 0;
    foreach ($pesos as $p) {
        if (!in_array($p, $canonicos, true)) $nc++;
    }
    if ($nc > 0) { $por_ficheiro[$rel] = $nc; $total_nc += $nc; }
}

$modo = $argv[1] ?? '';

if ($modo === '--report') {
    printf("Pesos não-canónicos: %d (em %d ficheiros)\n", $total_nc, count($por_ficheiro));
    echo "Canónicos aceites: " . implode('/', $canonicos) . "\n";
    echo str_repeat('-', 64) . "\n";
    arsort($por_ficheiro);
    $i = 0;
    foreach ($por_ficheiro as $rel => $n) {
        printf("%5d  %s\n", $n, $rel);
        if (++$i >= 20) break;
    }
    exit(0);
}

if ($modo === '--set') {
    file_put_contents($baseline_file, json_encode([
        'total_nao_canonicos' => $total_nc,
        'registado_em' => date('Y-m-d H:i'),
    ], JSON_PRETTY_PRINT) . "\n");
    printf("Baseline tipográfica registada: %d pesos não-canónicos.\n", $total_nc);
    exit(0);
}

// Verificação.
$falhou = false;

// 1. Ecrãs auditados não podem ter pesos não-canónicos.
foreach ($auditados as $rel) {
    $n = $por_ficheiro[$rel] ?? 0;
    if ($n > 0) {
        echo "FALHOU  Ecrã auditado {$rel} tem {$n} pesos não-canónicos (hierarquia quebrada)\n";
        $falhou = true;
    } else {
        echo "OK   Ecrã auditado mantém hierarquia de pesos: {$rel}\n";
    }
}

// 2. Baseline global não pode crescer.
if (!is_file($baseline_file)) {
    fwrite(STDERR, "Baseline tipográfica inexistente. Corra --set\n");
    exit(1);
}
$base = json_decode((string)file_get_contents($baseline_file), true);
$limite = (int)($base['total_nao_canonicos'] ?? 0);

printf("Pesos não-canónicos: %d (baseline: %d)\n", $total_nc, $limite);
if ($total_nc > $limite) {
    fwrite(STDERR, sprintf(
        "REGRESSÃO TIPOGRÁFICA: %d > baseline %d. Use só pesos reais (400/500/600/700/800/900).\n",
        $total_nc, $limite
    ));
    exit(1);
}
if ($total_nc < $limite) {
    printf("PROGRESSO: %d abaixo da baseline. Corra --set para registar o novo mínimo.\n", $limite - $total_nc);
}

if ($falhou) {
    fwrite(STDERR, "Ecrã auditado com hierarquia quebrada.\n");
    exit(1);
}
echo "TIPOGRAFIA OK - sem regressão; ecrãs auditados com hierarquia limpa.\n";
exit(0);
