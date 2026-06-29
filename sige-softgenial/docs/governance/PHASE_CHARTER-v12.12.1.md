# PHASE CHARTER - SIGE SoftGenial v12.12.1

## Objectivo

Executar a versao correctiva `v12.12.1 - Governance Baseline Corrective Audit` para fechar os P0/P1 encontrados no rediagnostico adversarial da v12.12.0. A meta e impedir falso verde em gates de governação e corrigir imediatamente o endpoint financeiro `sige_desp_print`.

## Incluido

1. Corrigir o P0 do print/relatorio de despesas com `escola_id` obrigatorio, nonce, permissao e auditoria.
2. Incluir hooks dinamicos como `wp_ajax_` concatenado com constantes, incluindo `wp_ajax:sige_settings_save`.
3. Incluir `template_redirect`, `admin_init`, `send_headers`, `parse_request` e query handlers `sige_*` no manifesto.
4. Adicionar extractor independente/adversarial ao gate do manifesto.
5. Adicionar gate especifico para query handlers criticos.
6. Adicionar gate especifico para queries sensiveis de despesas.
7. Adicionar testes negativos de governação para provar que os detectores apanham os casos que falharam.
8. Actualizar inventario, baselines, matriz de rastreabilidade, registo de riscos, QA e deploy.

## Excluido

1. MFA completo.
2. Security Kernel enforcement completo.
3. Financial Ledger.
4. Secret Vault completo.
5. Tenant isolation fail-closed global.
6. CSP enforcement.
7. Refactor modular.
8. Correcao de toda a divida `current_user_can`.

## Riscos

- P1: gate documental parecer forte sem validar comportamento real.
- P1: corrigir o print de despesas e quebrar links antigos sem nonce.
- P2: manifesto alargar superficie e aumentar tarefas futuras de enforcement.
- P2: scan de tenant ainda ser especifico para o P0 de despesas e nao cobrir todas as queries sensiveis do produto.

## Criterios de aceitacao

- P0 aberto = 0.
- P1 aberto = 0.
- `sige_desp_print` exige login, permissao, nonce, escola_id e auditoria.
- Manifesto contem `wp_ajax:sige_settings_save`.
- Manifesto contem `query_handler:sige_desp_print`, `query_handler:sige_recibo`, `query_handler:sige_portaria_camera`, `query_handler:sige_print` e `wp_hook:template_redirect`.
- Gates antigos continuam verdes.
- Gates novos ficam verdes.
- PHP lint passa em todos os ficheiros PHP.
- Rediagnostico adversarial final declara riscos residuais sem P0/P1.
