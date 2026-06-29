<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Corredor de Gates (run-gates)
 *
 * ORIGEM (12 Jun 2026): durante o fecho da v12.11.9.102, um gate
 * vermelho passou despercebido porque `php gate | tail -1` devolve o
 * exit do tail, não do gate. O stderr denunciou; a lição ficou: gates
 * nunca mais se invocam à mão em cadeias com pipes. Este corredor
 * executa TODOS os gates em sequência, mostra uma linha por gate, e
 * sai com código 1 se QUALQUER um falhar. Um comando, zero máscaras.
 *
 * Uso: php tools/run-gates.php
 */

$raiz = dirname(__DIR__);

$gates = [
    'Release gate (versões/CLI/canónicas)' => 'tools/smoke-release-gate.php',
    'Regressão (invariantes)'              => 'tools/smoke-regression-pack.php',
    'Presenças (motor puro)'               => 'tools/smoke-presencas.php',
    'M-Pesa/e-Mola (funil)'                => 'tools/smoke-mpesa.php',
    'Escudo de login'                      => 'tools/smoke-login-shield.php',
    'JS embebido nas views'                => 'tools/check-js-views.php',
    'Colisões CSS'                         => 'tools/check-css-collisions.php',
    'Calendário de devedores (motor puro)' => 'tools/smoke-devedores-calendario.php',
    'Extracto de dívida detalhado (documento devedores)' => 'tools/smoke-devedores-extracto-detalhado-v12-12-2.php',
    'Views beta (notas operacionais)' => 'tools/smoke-beta-views-v12-12-3.php',
    'Design System (fonte da verdade + ponte)' => 'tools/smoke-design-system.php',
    'Design System Stability Recovery (v12.15.3)' => 'tools/smoke-design-system-stability-v12-15-3.php',
    'Ícones (consistência visual)' => 'tools/smoke-icones.php',
    'Design tokens (sem regressão de mágicos)' => 'tools/check-design-tokens.php',
    'Tipografia (hierarquia de pesos)' => 'tools/check-typography.php',
    'Consistencia visual (primitivos sem token)' => 'tools/check-consistencia-visual.php',
    'Tokens fora de contexto (login/portal/standalone)' => 'tools/check-tokens-fora-de-contexto.php',
    'Responsivo (mobile/tablet sem quebras)' => 'tools/diag-responsivo.php',
    'Governação (inventário técnico)' => 'tools/inventory-surface.php',
    'Governação (documentos e baselines)' => 'tools/check-governance-docs.php',
    'Governação (views com permissões)' => 'tools/check-view-permission-map.php',
    'Governação (manifesto de acções)' => 'tools/check-action-surface-manifest.php',
    'Governação (query handlers críticos)' => 'tools/check-query-handler-surface.php',
    'Governação (tenant em queries sensíveis)' => 'tools/check-tenant-sensitive-queries.php',
    'Governação (testes negativos)' => 'tools/check-governance-negative-tests.php',
    'Governação (endpoints públicos)' => 'tools/check-public-endpoints-policy.php',
    'Governação (baseline de autorização)' => 'tools/check-authorization-baseline.php',
    'Governação (fallbacks tenant)' => 'tools/check-tenant-fallbacks.php',
    'Governação (sumidouros de escrita tenant)' => 'tools/check-tenant-write-sinks.php',
    'Governação (leitura/resolvedor tenant)' => 'tools/check-tenant-read-resolver.php',
    'Governação (segredos e opções)' => 'tools/check-secrets-options-register.php',
    'Governação (dependências externas)' => 'tools/check-external-dependencies-register.php',
    'Security Kernel (runtime e bootstrap)' => 'tools/check-security-kernel.php',
    'Security Kernel (regras enforce/observe)' => 'tools/check-security-kernel-rules.php',
    'Security Kernel (smoke v12.12.4)' => 'tools/smoke-security-kernel-v12-12-4.php',
    'Security Kernel (runtime audit v12.12.5)' => 'tools/smoke-security-kernel-v12-12-5.php',
    'Security Kernel (query runtime closure v12.12.6)' => 'tools/smoke-security-kernel-v12-12-6.php',
    'Settings tenant guard closure v12.12.6' => 'tools/smoke-settings-tenant-guard-v12-12-6.php',
    'Pagamentos moveis (tenant-scoped options v12.12.5)' => 'tools/smoke-mobile-tenant-options-v12-12-5.php',
    'Security Kernel (testes negativos)' => 'tools/check-security-kernel-negative-tests.php',
    'Critical Actions Lockdown (v12.12.7)' => 'tools/smoke-critical-actions-lockdown-v12-12-7.php',
    'Mapa de Cobranca PDF (v12.12.7.1)' => 'tools/smoke-devedores-pdf-v12-12-7-1.php',
    'Tenant Isolation Hardening (v12.12.8)' => 'tools/smoke-tenant-hardening-v12-12-8.php',
    'Tenant Write Isolation (v12.12.8.1)' => 'tools/smoke-tenant-write-isolation-v12-12-8-1.php',
    'Tenant Read Isolation (v12.12.9)' => 'tools/smoke-tenant-read-resolver-v12-12-9.php',
    'MFA de Operacao (step-up) (v12.12.10)' => 'tools/check-mfa-stepup.php',
    'MFA de Operacao (smoke v12.12.10)' => 'tools/smoke-mfa-stepup-v12-12-10.php',
    'MFA de Operacao TOTP (v12.12.11)' => 'tools/check-mfa-totp.php',
    'MFA de Operacao TOTP (smoke v12.12.11)' => 'tools/smoke-mfa-totp-v12-12-11.php',
    'MFA de Operacao Reposicao (v12.12.12)' => 'tools/check-mfa-autoreplay.php',
    'MFA de Operacao Reposicao (smoke v12.12.12)' => 'tools/smoke-mfa-autoreplay-v12-12-12.php',
    'Painel de Seguranca MFA (v12.12.13)' => 'tools/check-mfa-settings.php',
    'Painel de Seguranca MFA (smoke v12.12.13)' => 'tools/smoke-mfa-settings-v12-12-13.php',
    'Secret Vault (v12.12.14)' => 'tools/check-vault.php',
    'Secret Vault (smoke v12.12.14)' => 'tools/smoke-vault-v12-12-14.php',
    'Ledger Financeiro (v12.12.15)' => 'tools/check-ledger.php',
    'Ledger Financeiro (smoke v12.12.15)' => 'tools/smoke-ledger-v12-12-15.php',
    'Reconciliacao e divergencias (v12.12.20)' => 'tools/check-reconciliacao.php',
    'Reconciliacao e divergencias (smoke v12.12.20)' => 'tools/smoke-reconciliacao.php',
    'Regra de quatro-olhos (v12.12.21)' => 'tools/check-aprovacoes.php',
    'Regra de quatro-olhos (smoke v12.12.21)' => 'tools/smoke-aprovacoes.php',
    'Inventario de dados pessoais (v12.12.23)' => 'tools/check-privacidade.php',
    'Inventario de dados pessoais (smoke v12.12.23)' => 'tools/smoke-privacidade.php',
    'Direito de acesso e portabilidade (v12.12.24)' => 'tools/check-acesso.php',
    'Direito de acesso e portabilidade (smoke v12.12.24)' => 'tools/smoke-acesso.php',
    'Apagamento por anonimizacao (v12.12.25)' => 'tools/check-apagamento.php',
    'Apagamento por anonimizacao (smoke v12.12.25)' => 'tools/smoke-apagamento.php',
    'Retencao e expurgo (v12.12.27)' => 'tools/check-retencao.php',
    'Retencao e expurgo (smoke v12.12.27)' => 'tools/smoke-retencao.php',
    'Blindagem do modulo de permissoes (v12.12.28)' => 'tools/check-permissoes-blindagem.php',
    'Blindagem do modulo de permissoes (smoke v12.12.28)' => 'tools/smoke-permissoes-blindagem.php',
    'Hierarquia de perfis por nivel (v12.12.29)' => 'tools/check-permissoes-hierarquia.php',
    'Hierarquia de perfis por nivel (smoke v12.12.29)' => 'tools/smoke-permissoes-hierarquia.php',
    'Sobreposicao reversivel do papel WP (v12.12.30)' => 'tools/check-permissoes-sobreposicao.php',
    'Sobreposicao reversivel do papel WP (smoke v12.12.30)' => 'tools/smoke-permissoes-sobreposicao.php',
    'Niveis de perfil editaveis (v12.12.31)' => 'tools/check-permissoes-niveis-editaveis.php',
    'Niveis de perfil editaveis (smoke v12.12.31)' => 'tools/smoke-permissoes-niveis-editaveis.php',
    'Sobreposicao aditiva por capacidades (v12.12.32)' => 'tools/check-permissoes-aditiva.php',
    'Sobreposicao aditiva por capacidades (smoke v12.12.32)' => 'tools/smoke-permissoes-aditiva.php',
    'Blindagem de uploads e ficheiros (v12.12.34)' => 'tools/check-uploads-hardening.php',
    'Blindagem de uploads e ficheiros (smoke v12.12.34)' => 'tools/smoke-uploads-hardening.php',
    'Armazenamento privado de documentos (v12.12.35)' => 'tools/check-uploads-private.php',
    'Armazenamento privado de documentos (smoke v12.12.35)' => 'tools/smoke-uploads-private.php',
    'Despacho do dialogo de confirmacao (v12.12.36)' => 'tools/check-confirm-dispatch.php',
    'Ciclo de vida dos documentos (v12.12.37)' => 'tools/check-doc-lifecycle.php',
    'Ciclo de vida dos documentos (smoke v12.12.37)' => 'tools/smoke-doc-lifecycle.php',
    'Cofre - cobertura e nao-vazamento (v12.12.38)' => 'tools/check-vault-coverage.php',
    'Cofre - cobertura e nao-vazamento (smoke v12.12.38)' => 'tools/smoke-vault-coverage.php',
    'Cofre - rotacao e segredos por escola (v12.12.39)' => 'tools/check-secret-rotation.php',
    'Cofre - rotacao e segredos por escola (smoke v12.12.39)' => 'tools/smoke-secret-rotation.php',
    'Reconciliacao - deteccao de duplicados (v12.12.40)' => 'tools/check-payments-dedup.php',
    'Reconciliacao - deteccao de duplicados (smoke v12.12.40)' => 'tools/smoke-payments-dedup.php',
    'Reconciliacao viva (v12.12.41)' => 'tools/check-reconciliacao-viva.php',
    'Reconciliacao viva (smoke v12.12.41)' => 'tools/smoke-reconciliacao-viva.php',
    'Resolucao quatro-olhos (v12.12.42)' => 'tools/check-recon-resolucao.php',
    'Resolucao quatro-olhos (smoke v12.12.42)' => 'tools/smoke-recon-resolucao.php',
    'Fase 4 self-host e enqueue (v12.12.45)' => 'tools/check-fase4-assets.php',
    'Fase 4 self-host e enqueue (smoke v12.12.45)' => 'tools/smoke-fase4-assets.php',
    'Fase 4 inline ratchet (v12.12.46)' => 'tools/check-inline-frontend.php',
    'Fase 4 inline ratchet (smoke v12.12.47)' => 'tools/smoke-inline-frontend.php',
    'Standalone UI/CSP Recovery (v12.15.4)' => 'tools/smoke-standalone-csp-ui-v12-15-4.php',
    'Design System Inventory Baseline (v12.15.5)' => 'tools/inventory-design-system-pro-v12-15-5.php',
    'Design System Safety Contract (v12.15.5)' => 'tools/check-design-system-safety-contract-v12-15-5.php',
    'Design System PRO Diagnostic Baseline (v12.15.5)' => 'tools/smoke-design-system-diagnostic-v12-15-5.php',
    'Shell Scope Guard (v12.15.6)' => 'tools/check-shell-scope-v12-15-6.php',
    'Shell Scroll Contract (v12.15.6)' => 'tools/smoke-shell-scroll-v12-15-6.php',
    'Alunos Design Scope Guard (v12.15.8)' => 'tools/check-alunos-design-scope-v12-15-8.php',
    'Alunos Action Menu Layering (v12.15.8)' => 'tools/smoke-alunos-action-menu-layering-v12-15-8.php',
    'Alunos Stability Scope Guard (v12.15.11)' => 'tools/check-alunos-stability-scope-v12-15-11.php',
    'Alunos Stability Recovery (v12.15.11)' => 'tools/smoke-alunos-stability-recovery-v12-15-11.php',
    'Alunos Modal Tabs Contract (v12.15.19)' => 'tools/check-alunos-modal-tabs-v12-15-19.php',
    'Financeiro Core Design Scope Guard (v12.15.12)' => 'tools/check-financeiro-core-design-scope-v12-15-12.php',
    'Financeiro Formula Integrity (v12.15.12)' => 'tools/check-financeiro-formula-integrity-v12-15-12.php',
    'Recibo respeita data efectiva (v12.15.20)' => 'tools/check-recibo-data-efectiva-v12-15-20.php',
    'Pesquisa Global (v12.15.21)' => 'tools/check-pesquisa-global-v12-15-21.php',
    'Financeiro Core Design PRO (v12.15.12)' => 'tools/smoke-financeiro-core-design-pro-v12-15-12.php',
    'Portal Enxuto Scope Guard (v12.15.13)' => 'tools/check-portal-lean-assets-scope-v12-15-13.php',
    'Portal Enxuto decisao por papel (smoke v12.15.13)' => 'tools/smoke-portal-lean-assets-v12-15-13.php',
    'Portal Chrome vs Views split (v12.15.16)' => 'tools/check-portal-chrome-split-v12-15-16.php',
    'Presenças caminho rapido (gate v12.15.18)' => 'tools/check-presencas-fastpath-v12-15-18.php',
    'Presenças caminho rapido (smoke v12.15.17)' => 'tools/smoke-presencas-fastpath-v12-15-17.php',
    'v12.16.0 Governance Contract' => 'tools/check-v12-16-0-governance.php',
    'v12.16.0 Operational Map Contract' => 'tools/check-v12-16-0-operational-map.php',
    'v12.16.0 Operational Map Smoke' => 'tools/smoke-v12-16-0-operational-map.php',
    'v12.16.0 Operational Checklist Contract' => 'tools/check-v12-16-0-operational-checklists.php',
    'v12.16.0 Operational Checklist Smoke' => 'tools/smoke-v12-16-0-operational-checklists.php',
    'v12.16.0 Operational Navigation Contract' => 'tools/check-v12-16-0-operational-navigation.php',
    'v12.16.0 Operational Navigation Smoke' => 'tools/smoke-v12-16-0-operational-navigation.php',
    'v12.16.0 Flow Guidance Contract' => 'tools/check-v12-16-0-flow-guidance.php',
    'v12.16.0 Flow Guidance Smoke' => 'tools/smoke-v12-16-0-flow-guidance.php',
    'v12.16.0 RC Readiness Contract' => 'tools/check-v12-16-0-rc-readiness.php',
    'v12.16.0 RC Readiness Smoke' => 'tools/smoke-v12-16-0-rc-readiness.php',
    'v12.16.0 Professor Route Hotfix Contract' => 'tools/check-v12-16-0-professor-route-hotfix.php',
    'v12.16.0 Professor Route Hotfix Smoke' => 'tools/smoke-v12-16-0-professor-route-hotfix.php',
    'v12.16.0 Mobile Header Hotfix Contract' => 'tools/check-v12-16-0-mobile-header-hotfix.php',
    'v12.16.0 Mobile Header Hotfix Smoke' => 'tools/smoke-v12-16-0-mobile-header-hotfix.php',
    'v12.16.1 Runtime Evidence Contract' => 'tools/check-v12-16-1-runtime-evidence.php',
    'v12.16.1 Runtime Evidence Smoke' => 'tools/smoke-v12-16-1-runtime-evidence.php',
    'v12.16.1 Shell Contract' => 'tools/check-v12-16-1-shell-contract.php',
    'v12.16.1 Package Manifest' => 'tools/check-v12-16-1-package-manifest.php',
    'v12.16.2 Baseline Preservation Contract' => 'tools/check-v12-16-2-baseline-preservation.php',
    'v12.16.2 Operational Readiness Smoke' => 'tools/smoke-v12-16-2-operational-readiness.php',
    'v12.16.2 Package Manifest' => 'tools/check-v12-16-2-package-manifest.php',
    'v12.16.2 Regression Matrix' => 'tools/check-v12-16-2-regression-matrix.php',
    'v12.17.0 Alunos Performance Contract' => 'tools/check-v12-17-0-alunos-performance-contract.php',
    'v12.17.0 Alunos Performance Smoke' => 'tools/smoke-v12-17-0-alunos-performance.php',
    'v12.17.0 No Sensitive Regression' => 'tools/check-v12-17-0-no-sensitive-regression.php',
    'v12.17.0 Package Manifest' => 'tools/check-v12-17-0-package-manifest.php',
    'v12.17.1 Alunos Mobile Header Contract' => 'tools/check-v12-17-1-alunos-mobile-header-contract.php',
    'v12.17.1 Alunos Mobile Header Smoke' => 'tools/smoke-v12-17-1-alunos-mobile-header.php',
    'v12.17.1 No Sensitive Regression' => 'tools/check-v12-17-1-no-sensitive-regression.php',
    'v12.17.1 Package Manifest' => 'tools/check-v12-17-1-package-manifest.php',
    'v12.18.0 Operational Workflow Contract' => 'tools/check-v12-18-0-operational-workflow-contract.php',
    'v12.18.0 Operational Workflow Smoke' => 'tools/smoke-v12-18-0-operational-workflow.php',
    'v12.18.0 No Sensitive Regression' => 'tools/check-v12-18-0-no-sensitive-regression.php',
    'v12.18.0 Package Manifest' => 'tools/check-v12-18-0-package-manifest.php',
    'v12.19.0 Dashboard Intelligence Contract' => 'tools/check-v12-19-0-dashboard-intelligence-contract.php',
    'v12.19.0 Dashboard Intelligence Smoke' => 'tools/smoke-v12-19-0-dashboard-intelligence.php',
    'v12.19.0 No Sensitive Regression' => 'tools/check-v12-19-0-no-sensitive-regression.php',
    'v12.19.0 Package Manifest' => 'tools/check-v12-19-0-package-manifest.php',
    'v12.19.1 Dashboard Copy Contract' => 'tools/check-v12-19-1-dashboard-copy-contract.php',
    'v12.19.1 Dashboard Copy Smoke' => 'tools/smoke-v12-19-1-dashboard-copy.php',
    'v12.19.1 No Sensitive Regression' => 'tools/check-v12-19-1-no-sensitive-regression.php',
    'v12.19.1 Package Manifest' => 'tools/check-v12-19-1-package-manifest.php',
];


/**
 * Gate JS embebido com heap Node limitada para ambientes CI restritivos.
 * Mantem o verificador oficial tools/check-js-views.php como fonte unica.
 *
 * @return array{0:int,1:array<int,string>}
 */
function sige_run_js_views_gate_inline(string $raiz): array {
    $php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $script = $raiz . '/tools/check-js-views.php';
    $cmd = 'env NODE_OPTIONS=' . escapeshellarg('--max-old-space-size=8') . ' '
         . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' 2>&1';
    $out = [];
    $rc = 0;
    exec($cmd, $out, $rc);
    return [$rc, $out];
}

$total_gates = count($gates);
$from = 1;
$to = $total_gates;
foreach ($argv as $arg) {
    if (preg_match('/^--from=(\d+)$/', $arg, $m)) $from = max(1, (int)$m[1]);
    if (preg_match('/^--to=(\d+)$/', $arg, $m)) $to = min($total_gates, (int)$m[1]);
}
if ($from > $to) {
    fwrite(STDERR, "Intervalo de gates invalido: --from={$from} --to={$to}\n");
    exit(1);
}
$gates = array_slice($gates, $from - 1, $to - $from + 1, true);

$falhas = [];
$larg = max(array_map('strlen', array_keys($gates)));
$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';

echo "Intervalo de gates: {$from}-{$to} de {$total_gates}\n";

foreach ($gates as $nome => $script) {
    $caminho = $raiz . '/' . $script;
    echo str_repeat('-', 64) . "\n";
    echo "GATE: {$nome}\n";
    if (!is_file($caminho)) {
        echo "FALHOU: ficheiro em falta: {$script}\n";
        $falhas[] = $nome;
        continue;
    }

    $rc = 1;
    if ($script === 'tools/check-js-views.php') {
        [$rc, $linhas] = sige_run_js_views_gate_inline($raiz);
        foreach ($linhas as $linha) { echo $linha . "\n"; }
    } else {
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($caminho);
        passthru($cmd, $rc);
    }

    if ($rc === 0) {
        printf("OK      %-{$larg}s\n", $nome);
    } else {
        printf("FALHOU  %-{$larg}s  (exit %d)\n", $nome, $rc);
        $falhas[] = $nome;
    }
    if (function_exists('gc_collect_cycles')) { gc_collect_cycles(); }
}

echo str_repeat('=', 64) . "\n";
if ($falhas) {
    fwrite(STDERR, 'GATES VERMELHOS: ' . count($falhas) . ' de ' . count($gates) . ' (' . implode('; ', $falhas) . ")\n");
    exit(1);
}
echo 'TODOS OS GATES VERDES - ' . count($gates) . ' de ' . count($gates) . ' neste intervalo; cobertura total declarada: ' . $total_gates . " gates.\n";
exit(0);
