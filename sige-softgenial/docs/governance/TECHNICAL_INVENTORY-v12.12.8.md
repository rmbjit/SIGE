# TECHNICAL INVENTORY - v12.12.8

Versao: 12.12.8 (Tenant Isolation Hardening, escritas). Base: 12.12.7.1.

## Superficie
Superficie de accao inalterada face a v12.12.7.1: o manifesto continua com 193 itens (wp_ajax 113, view_action 18, cron_hook 9, query_handler 6, rest_route 5, wp_hook 4, shortcode 1). Este incremento nao adiciona nem remove accoes; altera apenas como a escola e resolvida dentro de caminhos de escrita ja existentes. Detalhe em `ACTION_SURFACE_MANIFEST-v12.12.8.json`.

## Views
54 views na allowlist, 54 com permissao mapeada (igual a v12.12.7.1). As views de escrita afectadas (abertura, disciplinas, encerramento, notas, financeiro-config, inscricoes, planos, transporte) passam a resolver a escola por `sige_require_escola_id` no topo, mantendo o mesmo mapa de permissoes.

## Permissoes
Sem alteracao: `sige_can` em 60, `current_user_can` em 475. O endurecimento e ortogonal a autorizacao e ocorre depois da verificacao de permissao.

## Tenant
Nucleo do incremento. Novo resolvedor fail-closed `sige_require_escola_id` em `includes/multitenancy.php`. 39 dos 41 call-sites de escrita migrados (25 request com `wp_die` 403; 14 biblioteca com aborto tipado). Baseline de fallbacks desce de 178 para 139. Detalhe em `TENANT_FALLBACK_BASELINE-v12.12.8.json` e em `TENANT_ISOLATION_REGISTER-v12.12.8.md`.

## Segredos
Sem segredos novos. Baseline em `SECRETS_OPTIONS_BASELINE-v12.12.8.json`, igual a v12.12.7.1.

## Dependencias
Sem dependencias nem hosts novos (12 hosts). Baseline em `EXTERNAL_DEPENDENCIES_BASELINE-v12.12.8.json`.

## Security Kernel
Contrato de regras inalterado: 193 regras, ids identicos a v12.12.7.1, em `SECURITY_KERNEL_RULES-v12.12.8.json`. O resolvedor de escrita complementa o Kernel com defesa em profundidade na resolucao de escola, sem alterar regras.
