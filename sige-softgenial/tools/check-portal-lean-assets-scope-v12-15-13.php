<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.13 - Gate de escopo do Portal enxuto.
 *
 * Garante, por inspeccao estatica, que a camada de peso de entrega por papel:
 *   1. Existe como ficheiro de politica dedicado e e carregada cedo.
 *   2. Esta atras de feature flag (reversivel).
 *   3. So actua na view do portal e exclui staff.
 *   4. Guarda mesmo wp_enqueue_media() e os CSS financeiros de staff.
 *   5. NUNCA toca em PHP financeiro/academico (nao referencia formulas, saldos,
 *      lancamentos nem funcoes de calculo). So decide enfileiramento.
 */
$root = dirname(__DIR__);
$errors = [];

$policy_path = $root . '/includes/portal-lean-assets.php';
if (!is_file($policy_path)) {
    fwrite(STDERR, "check-portal-lean-assets-scope-v12-15-13: FALHOU\n- Ficheiro de politica ausente: includes/portal-lean-assets.php\n");
    exit(1);
}
$policy = (string) file_get_contents($policy_path);
$boot   = (string) file_get_contents($root . '/sige-softgenial.php');
$shell  = (string) file_get_contents($root . '/includes/admin-shell.php');
$ui     = (string) file_get_contents($root . '/includes/ui-kit.php');

// 1. Politica carregada cedo no bootstrap.
if (strpos($boot, "includes/portal-lean-assets.php") === false) {
    $errors[] = 'A politica de portal enxuto nao e carregada em sige-softgenial.php.';
}

// 2. Feature flag presente (reversibilidade).
$flag = 'sige_portal_lean_assets_v121513_enabled';
if (strpos($policy, $flag) === false) $errors[] = "Feature flag ausente na politica: {$flag}.";

// 3. Funcao de decisao publica e exclusao de staff/escopo de view.
if (strpos($policy, 'function sige_portal_lean_is_active') === false) {
    $errors[] = 'Funcao de decisao sige_portal_lean_is_active ausente.';
}
if (strpos($policy, "'aluno_portal'") === false) {
    $errors[] = 'Politica nao esta limitada a view aluno_portal.';
}
foreach (['manage_options', 'sige_director', 'sige_secretario', 'sige_financeiro', 'sige_professor'] as $staff_cap) {
    if (strpos($policy, $staff_cap) === false) {
        $errors[] = "Capacidade de staff em falta na exclusao: {$staff_cap}.";
    }
}
if (strpos($policy, 'sige_encarregado') === false || strpos($policy, 'sige_aluno') === false) {
    $errors[] = 'Politica nao identifica os papeis exclusivos do portal (encarregado/aluno).';
}

// 4a. admin-shell: wp_enqueue_media() guardado pelo sinal de portal enxuto.
if (strpos($shell, "function_exists('sige_portal_lean_is_active')") === false) {
    $errors[] = 'admin-shell nao consulta sige_portal_lean_is_active.';
}
$guard_pos = strpos($shell, 'if (!$sige_portal_lean) {');
$media_pos = strpos($shell, 'wp_enqueue_media();');
if ($guard_pos === false) {
    $errors[] = 'admin-shell sem bloco de guarda if (!$sige_portal_lean).';
} elseif ($media_pos === false) {
    $errors[] = 'admin-shell sem chamada wp_enqueue_media().';
} elseif ($media_pos < $guard_pos) {
    $errors[] = 'wp_enqueue_media() aparece antes da guarda: media nao esta protegida.';
} else {
    // A guarda tem de preceder imediatamente a media (mesma janela curta de texto).
    $janela = substr($shell, $guard_pos, ($media_pos - $guard_pos) + strlen('wp_enqueue_media();'));
    if (strpos($janela, 'wp_enqueue_media();') === false) {
        $errors[] = 'wp_enqueue_media() nao esta dentro do bloco de guarda.';
    }
}

// 4b. ui-kit: CSS financeiros de staff guardados pelo sinal de portal enxuto.
$ui_guard = strpos($ui, 'if (!$sige_portal_lean) {');
if (strpos($ui, '$sige_portal_lean = function_exists') === false) {
    $errors[] = 'ui-kit nao define o sinal $sige_portal_lean.';
}
if ($ui_guard === false) {
    $errors[] = 'ui-kit sem bloco de guarda if (!$sige_portal_lean).';
} else {
    foreach (['sige-devedores-css', 'sige-reconciliacao-css', 'sige-aprovacoes-css'] as $fin_handle) {
        $hpos = strpos($ui, $fin_handle);
        if ($hpos === false) {
            $errors[] = "Handle financeiro ausente do ui-kit: {$fin_handle}.";
        } elseif ($hpos < $ui_guard) {
            $errors[] = "Handle financeiro {$fin_handle} fora do bloco de portal enxuto.";
        }
    }
}
// O motor de devedores tem de permanecer carregado sempre (fora da guarda).
if (strpos($ui, "require_once SIGE_PATH . 'includes/fin-devedores-calendario.php';") === false) {
    $errors[] = 'O require do motor de devedores foi removido (regressao funcional).';
}

// 5. A politica NAO pode acoplar-se a financeiro/BD. So decide assets. Verifica-se
// acoplamento de CODIGO (chamadas, includes, acesso a BD), nao palavras em prosa:
// os comentarios da politica mencionam legitimamente saldo/propina/media ao explicar
// o que o portal mostra, e isso e aceitavel.
$forbidden_finance = [
    'sige_fin_saldo_sql',
    'finance-core.php',
    'fin-kpi-engine',
    'fin-action-service',
    'financeiro-historico',
    'number_format(',
    '$wpdb',
    '->prepare(',
    '->get_var(',
    '->get_results(',
];
foreach ($forbidden_finance as $tok) {
    if (strpos($policy, $tok) !== false) {
        $errors[] = "A politica acopla-se a financeiro/BD (deve so decidir assets): {$tok}.";
    }
}

if ($errors) {
    fwrite(STDERR, "check-portal-lean-assets-scope-v12-15-13: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
echo "check-portal-lean-assets-scope-v12-15-13: OK - Portal enxuto ligado, guardado, reversivel e sem qualquer toque em financeiro.\n";
