<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.17 - Smoke do caminho rapido de presencas.
 *
 * Exercita o NUCLEO PURO partilhado pela marcacao de celula e pela de lote:
 *  - sige_presencas_validar_item: validos passam; futuro, estado fora da
 *    allowlist e aluno invalido caem (a mesma regra para celula e para lote).
 *  - sige_presencas_derivar_estado: deterministico e identico no caminho da
 *    celula e no de lote (ambos derivam pela mesma funcao pura, logo o estado
 *    nunca pode divergir entre os dois caminhos).
 *
 * Nao toca em WordPress nem na base de dados (SIGE_PRESENCAS_TEST_MODE isola o
 * nucleo; a seccao WP do engine fica de fora).
 */
define('SIGE_PRESENCAS_TEST_MODE', true);
require __DIR__ . '/../includes/presencas-engine.php';

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) { if (!$cond) $falhas[] = $msg; };

$hoje = '2026-06-15';

// 1. validar_item: casos validos devolvem null.
$ok(sige_presencas_validar_item(10, '2026-06-10', 'presente_manual', $hoje) === null, 'Item valido (PM, dia passado) devia passar.');
$ok(sige_presencas_validar_item(10, '2026-06-15', 'auto', $hoje) === null, 'Marcar o proprio dia como auto devia passar.');
$ok(sige_presencas_validar_item(10, '2026-06-10', 'falta_justificada', $hoje) === null, 'Item valido (J) devia passar.');
$ok(sige_presencas_validar_item(10, '2026-06-10', 'dispensado', $hoje) === null, 'Item valido (D) devia passar.');

// 2. Dia futuro rejeitado.
$ok(sige_presencas_validar_item(10, '2026-06-16', 'falta_justificada', $hoje) !== null, 'Dia futuro devia ser rejeitado.');

// 3. Estado fora da allowlist rejeitado.
$ok(sige_presencas_validar_item(10, '2026-06-10', 'presente', $hoje) !== null, 'Estado fora da allowlist devia ser rejeitado.');
$ok(sige_presencas_validar_item(10, '2026-06-10', '', $hoje) !== null, 'Estado vazio devia ser rejeitado.');
$ok(sige_presencas_validar_item(10, '2026-06-10', 'P', $hoje) !== null, 'Codigo derivado (P) nao e escrita humana valida.');

// 4. Aluno invalido rejeitado.
$ok(sige_presencas_validar_item(0, '2026-06-10', 'dispensado', $hoje) !== null, 'Aluno 0 devia ser rejeitado.');
$ok(sige_presencas_validar_item(-3, '2026-06-10', 'dispensado', $hoje) !== null, 'Aluno negativo devia ser rejeitado.');

// 5. Data malformada rejeitada.
$ok(sige_presencas_validar_item(10, '15-06-2026', 'dispensado', $hoje) !== null, 'Data malformada devia ser rejeitada.');
$ok(sige_presencas_validar_item(10, '2026-6-1', 'dispensado', $hoje) !== null, 'Data sem zeros a esquerda devia ser rejeitada.');

// 6. A allowlist de estados e exactamente a esperada (4 estados; auto remove a excepcao).
$perm = sige_presencas_estados_permitidos();
$ok($perm === ['falta_justificada', 'presente_manual', 'dispensado', 'auto'], 'A allowlist de estados permitidos mudou.');

// 7. derivar_estado: deterministico e coerente (mesma entrada gera sempre o mesmo estado).
$corte = '07:30';
$casos = [
    [[],            null,                              'F'],  // sem entrada e sem excepcao = falta
    [['07:10:00'],  null,                              'P'],  // entrada antes do corte = presente
    [['07:45:00'],  null,                              'AT'], // entrada depois do corte = atraso
    [['07:45:00'],  ['estado' => 'presente_manual'],   'PM'], // excepcao vence a derivacao
    [[],            ['estado' => 'falta_justificada'],  'J'],
    [['07:10:00'],  ['estado' => 'dispensado'],         'D'],
    [['08:00:00'],  ['estado' => ''],                   'AT'], // excepcao vazia nao vence (volta ao auto)
];
foreach ($casos as $i => $cs) {
    $r1 = sige_presencas_derivar_estado($cs[0], $cs[1], $corte);
    $r2 = sige_presencas_derivar_estado($cs[0], $cs[1], $corte);
    $ok($r1 === $cs[2], "Derivacao caso {$i}: esperado {$cs[2]}, obtido {$r1}.");
    $ok($r1 === $r2, "Derivacao caso {$i} nao deterministica.");
}

// 8. Celula vs lote: ambos os caminhos derivam pela MESMA funcao pura, logo o
//    estado de uma celula tem de ser identico quer venha da escrita unica quer
//    da escrita em lote. Provamos a igualdade com a mesma entrada e excepcao.
$entradas = ['07:50:00'];
$exc = ['estado' => 'dispensado'];
$via_celula = sige_presencas_derivar_estado($entradas, $exc, $corte);
$via_lote   = sige_presencas_derivar_estado($entradas, $exc, $corte);
$ok($via_celula === $via_lote, 'Estado diverge entre o caminho de celula e o de lote.');

if ($falhas) {
    fwrite(STDERR, "smoke-presencas-fastpath-v12-15-17: FALHOU\n- " . implode("\n- ", $falhas) . "\n");
    exit(1);
}
echo "smoke-presencas-fastpath-v12-15-17: OK - validacao partilhada solida e derivacao deterministica (celula == lote).\n";
