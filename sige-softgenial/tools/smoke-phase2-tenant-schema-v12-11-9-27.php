<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test estático - Fase 2/P2.1 Tenant-aware Schema v12.11.9.27
 * Executar: php tools/smoke-phase2-tenant-schema-v12-11-9-27.php
 */
$root = dirname(__DIR__);
$ok = 0; $fail = 0;
$check = function(bool $cond, string $msg) use (&$ok, &$fail) {
    if ($cond) { $ok++; echo "OK   - {$msg}\n"; }
    else { $fail++; echo "FAIL - {$msg}\n"; }
};
$read = function(string $rel) use ($root): string {
    $p = $root . DIRECTORY_SEPARATOR . $rel;
    return is_file($p) ? file_get_contents($p) : '';
};

$main = $read('sige-softgenial.php');
$build = $read('BUILD.json');
$migration = $read('includes/class-sige-migration.php');

$check(strpos($main, "define('SIGE_VERSION', '12.11.9.27')") !== false, 'SIGE_VERSION actualizado para 12.11.9.27');
$check(strpos($build, 'phase2-p2-1-tenant-schema') !== false, 'BUILD.json identifica Fase 2/P2.1 tenant schema');
$check(strpos($migration, "const SCHEMA_VERSION = '20260601.1'") !== false, 'SCHEMA_VERSION incrementado para executar migration');

$requiredUnique = [
    'UNIQUE KEY uniq_escola_ano_lectivo (escola_id, ano_lectivo)' => 'anos lectivos únicos por escola',
    'UNIQUE KEY uniq_pacote_escola_codigo_ano (escola_id, codigo, ano_letivo)' => 'pacotes únicos por escola/código/ano',
    'UNIQUE KEY uniq_pag_anual_escola_aluno_ano (escola_id, aluno_id, ano_letivo)' => 'pagamento anual único por escola/aluno/ano',
    'UNIQUE KEY uq_acta_disc_tenant (escola_id, acta_id, disciplina_id, disciplina_nome)' => 'acta cumprimento scoped por escola',
    'UNIQUE KEY uniq_curr_level_school_profile_code (escola_id, profile_id, code)' => 'curriculum levels scoped por escola',
    'UNIQUE KEY uniq_curr_grade_school_profile_code (escola_id, profile_id, code)' => 'curriculum grades scoped por escola',
    'UNIQUE KEY uniq_curr_subject_school_grade_disc (escola_id, profile_id, grade_id, disciplina_id)' => 'curriculum subjects scoped por escola',
    'UNIQUE KEY uniq_curr_period_school_profile_code (escola_id, profile_id, code)' => 'curriculum periods scoped por escola',
];
foreach ($requiredUnique as $needle => $label) {
    $check(strpos($migration, $needle) !== false, $label);
}

$forbiddenUnique = [
    'UNIQUE KEY ano_lectivo (ano_lectivo)',
    'UNIQUE KEY codigo_unico (codigo, ano_letivo)',
    'UNIQUE KEY aluno_ano_unico (aluno_id, ano_letivo)',
    'UNIQUE KEY uq_acta_disc (acta_id, disciplina_id, disciplina_nome)',
    'UNIQUE KEY uniq_curr_level_profile_code (profile_id, code)',
    'UNIQUE KEY uniq_curr_grade_profile_code (profile_id, code)',
    'UNIQUE KEY uniq_curr_subject_grade_disc (profile_id, grade_id, disciplina_id)',
    'UNIQUE KEY uniq_curr_period_profile_code (profile_id, code)',
];
foreach ($forbiddenUnique as $needle) {
    $check(strpos($migration, $needle) === false, "índice global removido do schema: {$needle}");
}

$check(strpos($migration, 'run_phase2_tenant_schema_migration') !== false, 'migration Fase 2 existe');
$check(strpos($migration, 'replace_unique_index_if_safe') !== false, 'substituição segura de unique indexes existe');
$check(strpos($migration, 'backfill_escola_id_from_parent') !== false, 'backfill de escola_id por relação canónica existe');
$check(strpos($migration, 'phase2_collect_tenant_schema_audit') !== false, 'auditoria tenant schema é gravada');
$check(strpos($migration, 'sige_phase2_tenant_schema_audit_v12_11_9_27') !== false, 'option de auditoria Fase 2 é criada');

$requiredBackfill = [
    "['sige_turma_alunos', 'aluno_id', 'sige_alunos']",
    "['sige_matriculas', 'aluno_id', 'sige_alunos']",
    "['sige_notas', 'aluno_id', 'sige_alunos']",
    "['sige_fin_lancamentos', 'aluno_id', 'sige_alunos']",
    "['sige_fin_pagamentos', 'lancamento_id', 'sige_fin_lancamentos']",
    "['sige_acta_presencas', 'acta_id', 'sige_acta_conselho_notas']",
];
foreach ($requiredBackfill as $needle) {
    $check(strpos($migration, $needle) !== false, "backfill previsto: {$needle}");
}

$requiredIndexes = [
    'idx_phase2_ano_escola_status',
    'idx_phase2_turmas_escola_ano_classe',
    'idx_phase2_notas_turma_periodo',
    'idx_phase2_pag_anual_school_aluno',
    'idx_phase2_wpp_school_status',
];
foreach ($requiredIndexes as $needle) {
    $check(strpos($migration, $needle) !== false, "índice tenant-aware de performance: {$needle}");
}

// Garantia estática: em CREATE TABLE, todas as UNIQUE KEYs de tabelas com escola_id devem conter escola_id.
preg_match_all('/CREATE TABLE \\{\\$p\\}(sige_[A-Za-z0-9_]+) \\((.*?)\\) \\{\\$cc\\};/s', $migration, $matches, PREG_SET_ORDER);
$bad = [];
foreach ($matches as $m) {
    $table = $m[1];
    $body = $m[2];
    if ($table === 'sige_escolas' || strpos($body, 'escola_id') === false) continue;
    foreach (preg_split('/\n/', $body) as $line) {
        $line = trim($line);
        if (strpos($line, 'UNIQUE KEY') === 0 && strpos($line, 'escola_id') === false) {
            $bad[] = $table . ': ' . rtrim($line, ',');
        }
    }
}
$check(empty($bad), 'nenhuma UNIQUE KEY global em tabelas tenant-scoped no schema novo');
if (!empty($bad)) {
    echo "\nUNIQUE KEYs problemáticas:\n" . implode("\n", $bad) . "\n";
}

if ($fail > 0) {
    echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
    exit(1);
}
echo "\nResultado: {$ok} OK, {$fail} FAIL\n";
exit(0);
