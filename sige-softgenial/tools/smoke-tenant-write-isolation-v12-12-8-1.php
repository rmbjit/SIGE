<?php
// Acesso restrito: smoke corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SMOKE - Tenant Write Isolation (v12.12.8.1)
 * Asserts estaticos sobre a presenca dos guards, do handler, dos baselines e da
 * integridade das funcoes de calculo. Nao requer bootstrap do WordPress.
 */
$root = dirname(__DIR__);
$fail = [];
$ok = 0;
$check = static function (string $label, bool $cond) use (&$fail, &$ok) {
    if ($cond) { $ok++; echo "OK   {$label}\n"; }
    else { $fail[] = $label; echo "FAIL {$label}\n"; }
};
$body = static function (string $file, string $fn) use ($root): string {
    $abs = $root . '/' . $file;
    if (!is_file($abs)) return '';
    $lines = file($abs, FILE_IGNORE_NEW_LINES); $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        if (preg_match('/function\s+' . preg_quote($fn, '/') . '\s*\(/', $lines[$i])) {
            $depth = 0; $started = false;
            for ($j = $i; $j < $n; $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if (strpos($lines[$j], '{') !== false) $started = true;
                if ($started && $depth <= 0) return implode("\n", array_slice($lines, $i, $j - $i + 1));
            }
        }
    }
    return '';
};
$guarded = static function (string $b): bool {
    return (bool) preg_match('/sige_tenant_write_guard\s*\(|sige_require_escola_id\s*\(/', $b);
};

// 1) Helper existe e e fail-closed e auditado
$mt = file_get_contents($root . '/includes/multitenancy.php');
$check('helper sige_tenant_write_guard existe', strpos($mt, 'function sige_tenant_write_guard') !== false);
$check('helper devolve false sem escola', (bool) preg_match('/sige_tenant_write_guard[^}]*?if\s*\(\s*\$escola_id\s*>\s*0\s*\)\s*\{\s*return true;/s', $mt));
$check('helper audita tenant_write_blocked', (bool) preg_match('/sige_tenant_write_guard.*?tenant_write_blocked/s', $mt));

// 2) Categoria A - amostra critica (financeiro, academico, whatsapp)
foreach ([
    ['includes/fin-action-service.php', 'estornarPagamento'],
    ['includes/fin-action-service.php', 'cancelLancamento'],
    ['includes/fin-fecho-turno.php', 'fecharTurno'],
    ['includes/fin-familia-service.php', 'atribuirFamilia'],
    ['includes/acta-pdf-handler.php', 'sige_acta_aplicar_nota_votada'],
    ['includes/whatsapp-recovery-mode.php', 'sige_wpp_contact_bump'],
    ['admin/academic/abertura-view.php', 'sige_ab_execute_opening'],
    ['admin/academic/encerramento-view.php', 'sige_enc_criar_snapshot_final'],
] as [$f, $fn]) {
    $check("Cat.A guard em {$fn}", $guarded($body($f, $fn)));
}

// 3) Categoria B - AJAX e biblioteca
foreach ([
    ['includes/db-handler.php', 'sige_ajax_salvar_aluno'],
    ['includes/db-handler.php', 'sige_ajax_remover_aluno'],
    ['includes/db-handler.php', 'sige_ajax_criar_usuario_staff'],
    ['includes/finance-core.php', 'sige_fin_criar_credito_pendente'],
    ['includes/finance-core.php', 'sige_fin_registar_pagamento_anual'],
] as [$f, $fn]) {
    $check("Cat.B guard em {$fn}", $guarded($body($f, $fn)));
}

// 4) Handler delegado acta:1610 com require
$acta = file_get_contents($root . '/includes/acta-pdf-handler.php');
$check('acta aprovar nota votada usa sige_require_escola_id', (bool) preg_match('/sige_require_escola_id\(.acta_aprovar_nota_votada.\)/', $acta));

// 5) Excecoes de logging NAO levam guard de bloqueio (logging resiliente)
$check('sige_audit_log sem guard de bloqueio (excecao)', !$guarded($body('includes/finance-core.php', 'sige_audit_log')));
$check('sige_registar_log sem guard de bloqueio (excecao)', !$guarded($body('includes/db-handler.php', 'sige_registar_log')));

// 6) Baselines
$b81 = json_decode(file_get_contents($root . '/docs/security/TENANT_FALLBACK_BASELINE-v12.12.8.1.json'), true);
$b80 = json_decode(file_get_contents($root . '/docs/security/TENANT_FALLBACK_BASELINE-v12.12.8.json'), true);
$check('baseline v12.12.8.1 = 138', count($b81['items']) === 138);
$check('baseline v12.12.8 congelado = 139', count($b80['items']) === 139);

// 7) Integridade das funcoes de calculo (nao tocadas)
$fc = file_get_contents($root . '/includes/finance-core.php');
$check('sige_fin_saldo_lancamento intacta (sem guard de tenant)', strpos($fc, 'function sige_fin_saldo_lancamento') !== false && !$guarded($body('includes/finance-core.php', 'sige_fin_saldo_lancamento')));
$check('sige_fin_saldo_sql intacta (sem guard de tenant)', strpos($fc, 'function sige_fin_saldo_sql') !== false && !$guarded($body('includes/finance-core.php', 'sige_fin_saldo_sql')));

echo "\n";
if ($fail) {
    fwrite(STDERR, "SMOKE TENANT WRITE ISOLATION FALHOU (" . count($fail) . "):\n - " . implode("\n - ", $fail) . "\n");
    exit(1);
}
echo "SMOKE TENANT WRITE ISOLATION OK - {$ok} verificacoes.\n";
