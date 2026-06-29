<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Smoke do Calendário de Devedores (motor puro)
 * Valida sige_fin_calendario_devedores_montar() sem WordPress.
 */

require __DIR__ . '/../includes/fin-devedores-calendario.php';

$total = 0;
$ok = 0;
function checa(bool $cond, string $msg): void {
    global $total, $ok;
    $total++;
    if ($cond) { $ok++; echo "OK   {$msg}\n"; }
    else { echo "FALHOU  {$msg}\n"; }
}

// Cenário base: ano 2026, hoje em Junho, filtro em Março.
$linhas = [
    ['ym' => '2026-01', 'devedores' => 1, 'aberto' => 100.0],
    ['ym' => '2026-03', 'devedores' => 7, 'aberto' => 1250.5],
    (object)['ym' => '2026-04', 'devedores' => 4, 'aberto' => 400.0],
    ['ym' => '2026-08', 'devedores' => 8, 'aberto' => 8000.0],
    ['ym' => '2025-12', 'devedores' => 99, 'aberto' => 9.0],   // outro ano: fora da grelha
    ['ym' => 'lixo',    'devedores' => 5, 'aberto' => 5.0],     // inválido: ignorado
    ['ym' => '2026-05', 'devedores' => -3, 'aberto' => -10.0],  // clamp a zero
];
$r = sige_fin_calendario_devedores_montar($linhas, 2026, '2026-06', '2026-03');

checa(count($r['celulas']) === 12, 'Grelha tem sempre 12 células');
$meses = array_map(fn($c) => $c['mes'], $r['celulas']);
checa($meses === range(1, 12), 'Células ordenadas Jan..Dez');
checa($r['celulas'][0]['rotulo'] === 'Jan' && $r['celulas'][11]['rotulo'] === 'Dez', 'Rótulos PT nas pontas');
checa($r['celulas'][10]['ym'] === '2026-11', 'ym no formato YYYY-MM');
checa($r['celulas'][2]['devedores'] === 7 && abs($r['celulas'][2]['aberto'] - 1250.5) < 0.001, 'Mapeamento do mês com dados (Mar)');
checa($r['celulas'][3]['devedores'] === 4, 'Linhas em objecto também são aceites (Abr)');
checa($r['celulas'][1]['devedores'] === 0 && $r['celulas'][1]['nivel'] === 0, 'Zero-fill em mês sem dados (Fev)');
checa($r['max'] === 8, 'Máximo do ano detectado (8)');
checa($r['celulas'][7]['nivel'] === 4, 'Pior mês recebe nível 4 (Ago)');
checa($r['celulas'][0]['nivel'] === 1, 'Mês mínimo com devedores recebe nível 1 (Jan)');
checa($r['celulas'][2]['nivel'] === 3, 'Nível proporcional: 7 de 8 dá nível 3 (Mar)');
checa($r['celulas'][5]['corrente'] === true && $r['celulas'][4]['corrente'] === false, 'Mês corrente marcado (Jun)');
checa($r['celulas'][2]['activo'] === true && $r['celulas'][0]['activo'] === false, 'Mês filtrado marcado (Mar)');
checa($r['tem_filtro_mes'] === true, 'Flag de filtro activo');
checa($r['celulas'][11]['devedores'] === 0, 'Linha de outro ano fica fora da grelha (Dez=0)');
checa($r['celulas'][4]['devedores'] === 0 && $r['celulas'][4]['aberto'] === 0.0, 'Valores negativos clampados a zero (Mai)');
checa(abs($r['total_aberto'] - (100.0 + 1250.5 + 400.0 + 8000.0)) < 0.001, 'Total em aberto soma só o ano da grelha');

// Cenário vazio + sem filtro.
$r2 = sige_fin_calendario_devedores_montar([], 2026, '2026-06', null);
checa($r2['max'] === 0 && $r2['tem_filtro_mes'] === false, 'Cenário vazio: max=0 e sem filtro');
checa(array_sum(array_map(fn($c) => $c['nivel'], $r2['celulas'])) === 0, 'Cenário vazio: todos os níveis a zero');

echo str_repeat('-', 60) . "\n";
if ($ok === $total) {
    echo "SMOKE CALENDÁRIO DEVEDORES OK - {$total} verificações passaram.\n";
    exit(0);
}
fwrite(STDERR, 'FALHARAM ' . ($total - $ok) . " de {$total} verificações.\n");
exit(1);
