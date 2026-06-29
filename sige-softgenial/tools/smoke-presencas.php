<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke funcional - Motor de Presenças
 * Testa o núcleo puro (derivação, dias lectivos, totais) sem WordPress.
 *
 * Executar: php tools/smoke-presencas.php
 */
define('SIGE_PRESENCAS_TEST_MODE', true);
require __DIR__ . '/../includes/presencas-engine.php';

$fails = []; $oks = 0;
$check = function (bool $c, string $l) use (&$fails, &$oks) {
    if ($c) { $oks++; echo "OK   {$l}\n"; } else { $fails[] = $l; echo "FAIL {$l}\n"; }
};

// ── 1. Derivação básica ─────────────────────────────────────────────────────
$check(sige_presencas_derivar_estado(['07:05:12'], null, '07:30') === 'P', 'Entrada antes do corte = Presente');
$check(sige_presencas_derivar_estado(['07:30:00'], null, '07:30') === 'P', 'Entrada exactamente no corte = Presente');
$check(sige_presencas_derivar_estado(['07:30:01'], null, '07:30') === 'AT', 'Um segundo depois do corte = Atraso');
$check(sige_presencas_derivar_estado(['09:15:00'], null, '07:30') === 'AT', 'Entrada tardia = Atraso');
$check(sige_presencas_derivar_estado([], null, '07:30') === 'F', 'Sem entradas = Falta');

// ── 2. Múltiplas leituras: conta a PRIMEIRA entrada ─────────────────────────
$check(sige_presencas_derivar_estado(['08:00:00','07:10:00','12:30:00'], null, '07:30') === 'P', 'Primeira entrada decide (07:10 entre leituras)');
$check(sige_presencas_derivar_estado(['08:00:00','07:45:00'], null, '07:30') === 'AT', 'Primeira entrada 07:45 = Atraso mesmo com outras leituras');

// ── 3. Excepções vencem a derivação ──────────────────────────────────────────
$check(sige_presencas_derivar_estado([], ['estado' => 'falta_justificada'], '07:30') === 'J', 'Justificação vence a falta');
$check(sige_presencas_derivar_estado([], ['estado' => 'presente_manual'], '07:30') === 'PM', 'Presença manual vence a falta');
$check(sige_presencas_derivar_estado(['07:00:00'], ['estado' => 'dispensado'], '07:30') === 'D', 'Dispensa vence até a presença derivada');
$check(sige_presencas_derivar_estado(['09:00:00'], ['estado' => 'invalido_xyz'], '07:30') === 'AT', 'Excepção desconhecida é ignorada (cai na derivação)');

// ── 4. Hora de corte configurável ────────────────────────────────────────────
$check(sige_presencas_derivar_estado(['07:45:00'], null, '08:00') === 'P', 'Corte 08:00: entrada 07:45 = Presente');
$check(sige_presencas_derivar_estado(['08:10:00'], null, '08:00') === 'AT', 'Corte 08:00: entrada 08:10 = Atraso');

// ── 5. Dias lectivos (Junho de 2026: dia 1 é segunda-feira) ─────────────────
$dias = sige_presencas_dias_lectivos(2026, 6);
$check(count($dias) === 22, 'Junho/2026 tem 22 dias lectivos (seg-sex), obteve ' . count($dias));
$check($dias[0]['dia'] === 1 && $dias[0]['semana'] === 'Seg', 'Junho/2026 começa em segunda, dia 1');
$datas = array_column($dias, 'data');
$check(!in_array('2026-06-06', $datas, true) && !in_array('2026-06-07', $datas, true), 'Sábado 6 e domingo 7 excluídos');
$fev = sige_presencas_dias_lectivos(2024, 2); // ano bissexto
$check(end($fev)['dia'] === 29, 'Fevereiro/2024 (bissexto) inclui dia 29 (quinta-feira)');

// ── 6. Totais e percentagem ──────────────────────────────────────────────────
$t = sige_presencas_totais_linha(['P','P','AT','F','J','PM','D','P']);
$check($t['contagens']['P'] === 3 && $t['contagens']['AT'] === 1 && $t['contagens']['F'] === 1
    && $t['contagens']['J'] === 1 && $t['contagens']['PM'] === 1 && $t['contagens']['D'] === 1, 'Contagens por estado correctas');
// presentes = P3+AT1+PM1 = 5; consideradas = 8-1(D) = 7; 5/7 = 71.4%
$check(abs($t['pct_presenca'] - 71.4) < 0.05, 'Percentagem de presença 71.4 (dispensa fora do denominador), obteve ' . $t['pct_presenca']);
$t2 = sige_presencas_totais_linha(['D','D']);
$check($t2['pct_presenca'] === 0.0, 'Só dispensas: percentagem 0 sem divisão por zero');
$t3 = sige_presencas_totais_linha(['P','P','P','P']);
$check($t3['pct_presenca'] === 100.0, 'Mês perfeito: 100%');

// ── 7. Totais de presentes por dia (linha de rodapé do mapa oficial) ────────
$alunos_demo = [
    ['estados' => [1 => 'P',  2 => 'F', 3 => 'AT']],
    ['estados' => [1 => 'PM', 2 => 'J', 3 => 'F']],
    ['estados' => [1 => 'D',  2 => 'P', 3 => 'P']],
];
$dias_demo = [['dia' => 1], ['dia' => 2], ['dia' => 3]];
$td = sige_presencas_totais_por_dia($alunos_demo, $dias_demo);
$check($td[1] === 2 && $td[2] === 1 && $td[3] === 2, 'Totais por dia: P/AT/PM contam, F/J/D não (2,1,2)');
$check(sige_presencas_totais_por_dia([], $dias_demo) === [1 => 0, 2 => 0, 3 => 0], 'Turma vazia: totais a zero sem erro');

echo str_repeat('-', 60) . "\n";
if ($fails) {
    fwrite(STDERR, 'SMOKE PRESENÇAS FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "SMOKE PRESENÇAS OK - {$oks} verificações passaram.\n";
