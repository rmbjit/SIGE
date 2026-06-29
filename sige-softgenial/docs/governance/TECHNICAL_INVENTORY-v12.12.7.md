# TECHNICAL INVENTORY - v12.12.7

## Superficie
Manifesto v12.12.7: 192 superfícies. O Security Kernel cobre as mesmas 192 regras, com 29 em enforce, 17 delegated e 146 observe.

## Views
Foram adicionadas 18 superfícies `view_action` para mutações directas das views: financeiro-pagamentos, financeiro-despesas, financeiro-lancamentos, financeiro-extratos e sige_permissoes.

## Permissoes
A autorização por acção passa a ser explícita nas mutações críticas. Acesso à página `financeiro.ver` não autoriza pagamento. `sige_can` passa a consultar overrides tenant-scoped antes dos templates globais.

## Tenant
Foram corrigidos usos críticos de `escola_id`: M-Pesa manual, despesas, pagamentos, UI de permissões e arquivamento de alunos. Fallbacks globais remanescentes ficam para Fase 3.

## Segredos
Webhooks móveis passam por token scoped por escola, mantendo o storage tenant-scoped criado na v12.12.5. Secret Vault definitivo fica para fase própria.

## Dependencias
Não foram adicionados novos hosts externos. Dependências existentes permanecem registadas para análise futura.

## Security Kernel
O Kernel agora suporta `view_action`, `enforce`, `delegated`, `retired`, token/HMAC, object guards e despacho antecipado em `admin_init`.
