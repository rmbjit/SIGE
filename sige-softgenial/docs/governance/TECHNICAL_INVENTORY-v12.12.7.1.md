# TECHNICAL INVENTORY - v12.12.7.1

## Superficie
Manifesto v12.12.7.1: 193 superficies (era 192). A unica superficie nova e `query_handler:sige_dev_print`. O Security Kernel cobre as mesmas 193 regras, com 30 em enforce, 17 delegated e 146 observe.

## Views
Nenhuma view nova. A view `financeiro-devedores` recebe apenas um botao adicional (Imprimir lista PDF) que aponta para o query handler seguro. As 18 superficies `view_action` da v12.12.7 permanecem intactas.

## Permissoes
O documento de cobranca exige `financeiro.cobrancas_ver` ou `financeiro.cobrancas_gerir` via `sige_can`, com fallback compativel para os papeis sige_director, sige_secretario e sige_financeiro. E exactamente a mesma matriz de acesso da pagina Central de Cobrancas, sem alargar o acesso.

## Tenant
O handler resolve `escola_id` por `sige_get_escola_id()` e e fail-closed: sem escola resolvida nao ha documento. A query do dataset filtra `escola_id` em todas as juncoes (alunos, matriculas, turmas, lancamentos). Os fallbacks globais remanescentes ficam para a Fase 3.

## Segredos
Nenhum segredo novo. Nenhuma opcao sensivel adicionada. O Secret Vault definitivo continua para fase propria.

## Dependencias
Nenhum host externo novo. Nenhuma biblioteca nova: o documento e HTML pronto a imprimir, sem motor de PDF no servidor.

## Security Kernel
O Kernel passa a cobrir `query_handler:sige_dev_print` em enforce, com nonce (_wpnonce/sige_dev_print), permissoes, tenant_required, rate limit (sk_query_handler_sige_dev_print, 30/300s), auditoria e dispatch antecipado multi-hook em admin_init, parse_request e template_redirect a prioridade -1000.
