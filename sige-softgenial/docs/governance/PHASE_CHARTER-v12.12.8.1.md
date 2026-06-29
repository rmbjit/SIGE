# PHASE CHARTER - v12.12.8.1

Incremento: Tenant Write Isolation (remediacao do P1 da v12.12.8).
Versao base: 12.12.8.

## Objectivo
Fechar o P1 apurado no rediagnostico da v12.12.8 (enumeracao de escritas incompleta) tornando a escrita por tenant fail-closed na camada de SUMIDOURO, de modo que nenhuma escrita possa visar escola_id <= 0, independentemente do chamador. Alinhar com a Fase 3 ("validar escola_id em todas as operacoes criticas; tenant ausente, invalido ou ambiguo = operacao bloqueada"), sem tocar regras de calculo.

## Incluido
- Helper auditado `sige_tenant_write_guard` em multitenancy.php.
- Categoria A: guard fail-closed em 24 sumidouros que recebem escola_id/eid por parametro, com aborto tipado por contrato.
- Categoria B: guard fail-closed em 22 funcoes com escrita inline `=> sige_get_escola_id()` (AJAX com wp_send_json_error; biblioteca com retorno tipado).
- Fecho do handler delegado `acta-pdf-handler.php:1610`.
- Auditoria em todos os abortos (fecha a lacuna silenciosa da v12.12.8).
- Novo gate `check-tenant-write-sinks.php`, ligado ao corredor, que governa a superficie real e impede regressao.
- Smoke dedicado e correccao dos registos de tenant.

## Excluido
- 2 excecoes de logging resiliente: `sige_audit_log` e `sige_registar_log` (registam mesmo sem escola, auditando a anomalia).
- 137 call-sites de LEITURA (incremento posterior).
- Remocao do fallback final `SIGE_ESCOLA_MALISA` do resolvedor (precisa tratamento de contextos publicos/cron).
- `permissions-ui.php:70` (super-admin gated) e `permissions-layer.php:1014` (relaxado gated), P3 documentados.
- Qualquer alteracao a regras de calculo financeiro ou academico.

## Riscos
- Sumidouro com aborto de tipo incompativel quebraria o fluxo. Mitigacao: aborto tipado por contrato verificado (array ok/error, ok/message, bool, void, null, WP_Error), lint e revisao de chamadores no rediagnostico.
- Sumidouro legitimo chamado com escola 0 num fluxo desconhecido passaria a abortar. Mitigacao: em mono-escola o resolvedor devolve 1, guards dormentes; muitos sumidouros financeiros ja eram soft-fail-closed por SELECT filtrado por escola_id.
- Falso positivo no inventario (nome de leitura mas faz upsert). Mitigacao: cada sumidouro confirmado antes de tocar; detector com verificacao de corpo completo.

## Criterios de aceitacao
- 0 sumidouros de Categoria A sem guard; 0 funcoes de Categoria B sem guard fora das 2 excecoes.
- Novo gate verde e a bloquear regressao; todos os gates verdes.
- Helper testado (permite com escola, bloqueia e audita sem escola).
- Baseline ": 1" desce de 139 para 138; ratchet monotonico activo.
- Lint 0 falhas; rediagnostico adversarial Zero P0/P1.
- Mono-escola sem alteracao de comportamento; zero alteracao a regras de calculo.
