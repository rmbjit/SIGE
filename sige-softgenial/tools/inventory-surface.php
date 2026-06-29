<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/governance-lib.php';

$write = in_array('--write', $argv, true);
$check = in_array('--check', $argv, true) || !$write;
$version = sige_gov_version();
$vslug = sige_gov_version_slug();

$surface = sige_gov_extract_action_surface();
$summary = sige_gov_summary();
$views = sige_gov_extract_views();
$options = sige_gov_option_names();
$hosts = sige_gov_external_hosts();
$auth = sige_gov_auth_baseline();
$tenant = sige_gov_tenant_fallbacks();

if ($write) {
    $public = array_values(array_filter($surface, static function ($it) { return !empty($it['public']); }));
    sige_gov_write_json("docs/security/ACTION_SURFACE_MANIFEST-v{$version}.json", [
        'version' => $version,
        'base_version' => '12.12.62',
        'purpose' => 'Fase 9 incremento 5 (sobreposicao puramente aditiva por capacidades). Elimina a substituicao do papel WordPress: o modulo deixa de chamar set_role na atribuicao de perfis de staff e na sincronizacao do init, e passa a conceder as capacidades em tempo de execucao por um filtro user_has_cap (sige_permissions_grant_caps_filter), a partir do papel sige_* mapeado ao perfil SIGE activo, sem nunca tocar no papel WordPress guardado. As capacidades sao lidas do conjunto completo do papel mapeado (sige_permissions_caps_for_user via get_role), incluindo a capacidade com o nome do papel (que e o que as verificacoes current_user_can(sige_*) usam) e as em cascata. Para replicar fielmente o antigo set_role (capacidades disponiveis em todo o lado, independentes do contexto de escola) sem regressao, caps_for_user prefere o perfil da escola actual e, sem contexto, usa o perfil activo mais recente em qualquer escola (sige_permissions_get_latest_active_role); a isolacao de dados por escola (escola_id) e separada e nao muda. O filtro ignora administradores WordPress reais e utilizadores sem perfil activo, tem cache por pedido e guarda anti-recursao. A sincronizacao no init passa a fazer so limpeza: repoe o papel WordPress original quando o utilizador esta num papel sige_* de staff legado (migracao preguicosa, uma vez por utilizador). A reposicao (restore_wp_roles) foi tornada segura para o fluxo aditivo: sem copia, so repoe o papel por omissao se o utilizador estiver num papel sige_* de staff, nunca mexendo num utilizador ja num papel real. As poucas verificacoes por slug de papel sige_* de staff que existiam (proteccao do Admin TI em ajax-handlers, rotulo do cracha em equipe-view) foram convertidas para verificacao por capacidade (user_can), que passa pelo filtro. Garantia central sem WordPress vivo: um invariante mecanico (gate) que falha se existir qualquer verificacao por slug de papel sige_* de staff no array de papeis; se nada depende do papel estar guardado, o filtro e suficiente por construcao. Os papeis de portal (sige_aluno, sige_encarregado) tem fluxo proprio (contas de aluno/encarregado) e ficam intocados, fora do ambito de staff. Sem nova superficie: o manifesto e as regras do Kernel mantem-se em 199 (enforce 33) e a allowlist de views em 60. Sem alteracao de esquema. Sem aumento de current_user_can (as conversoes usam user_can, funcao distinta). Regras de calculo financeiro intocadas. Conclui o conjunto previsto para a Fase 9. Gate e smoke dedicados: check-permissoes-aditiva e smoke-permissoes-aditiva.',
        'totals' => $summary['action_surface_by_type'],
        'items' => $surface,
    ]);
    sige_gov_write_json("docs/security/AUTHORIZATION_DEBT_BASELINE-v{$version}.json", array_merge(['version' => $version], $auth));
    sige_gov_write_json("docs/security/TENANT_FALLBACK_BASELINE-v{$version}.json", ['version' => $version, 'items' => $tenant]);
    sige_gov_write_json("docs/security/SECRETS_OPTIONS_BASELINE-v{$version}.json", ['version' => $version, 'items' => $options]);
    sige_gov_write_json("docs/security/EXTERNAL_DEPENDENCIES_BASELINE-v{$version}.json", ['version' => $version, 'items' => $hosts]);
    sige_gov_write_json("docs/governance/INVENTORY_SUMMARY-v{$version}.json", array_merge(['version' => $version], $summary, [
        'views' => $views,
        'public_surface_count' => count($public),
        'corrective_audit' => [
            'dynamic_hooks_detected' => true,
            'query_handlers_detected' => true,
            'independent_manifest_extractor' => true,
        ],
    ]));
}

printf("Inventario SIGE v%s\n", $version);
printf("Ficheiros totais: %d\n", $summary['total_files']);
printf("PHP runtime: %d\n", $summary['runtime_php_files']);
printf("Superficie de accao: %d\n", $summary['action_surface_total']);
foreach ($summary['action_surface_by_type'] as $type => $count) {
    printf(" - %s: %d\n", $type, $count);
}
printf("Views allowlist: %d\n", $summary['views_allowlist']);
printf("Views com permissao: %d\n", $summary['views_permission_map']);
printf("current_user_can: %d\n", $auth['current_user_can_total']);
printf("sige_can: %d\n", $auth['sige_can_total']);
printf("Fallbacks tenant candidatos: %d\n", count($tenant));
printf("Opcoes WordPress: %d\n", count($options));
printf("Hosts externos: %d\n", count($hosts));

if ($check) {
    $missing = array_values(array_diff($views['allowlist'], array_keys($views['permission_map'])));
    if ($missing) {
        fwrite(STDERR, 'Views sem permissao: ' . implode(', ', $missing) . "\n");
        exit(1);
    }
}

echo "INVENTARIO OK\n";
