# QA - SIGE SoftGenial v12.12.12 (MFA de Operacao: reposicao automatica)

Execucao real, sem bootstrap do WordPress (servico-stub que conta chamadas).
Base: v12.12.11. Regra: zero em tudo antes de avancar.

## 1. Captura

- Descritor de uso unico capturado quando uma das 6 operacoes de servico e bloqueada pelo step-up (func_get_args). Guardado em transient por utilizador, TTL curto.
- Contexto fora das 6 (ex.: config) nao e capturado nem sobrepoe o descritor existente.
- Com a reposicao desligada (off), nao captura.

## 2. Consumo de uso unico (sem execucao dupla)

- O consumo apaga o descritor ANTES de despachar (atomico). A operacao executa exactamente uma vez (contador do servico-stub = 1).
- Um segundo consumo nao dispara (devolve null; contador continua em 1). Prova directa de ausencia de execucao dupla.
- Em segunda linha, as proprias operacoes tem guardas de estado (FSM, valor_pago, ja estornado) que tornam qualquer re-execucao um nao-evento seguro.

## 3. Argumentos e despacho

- Os argumentos sao despachados intactos e na ordem certa para cada uma das 6 operacoes (incluindo o valor opcional do estorno).
- O despacho usa call_user_func_array sobre o mesmo metodo estatico (SIGE_FinanceActionService::...).

## 4. Re-validacao de tenant e permissao

- A reposicao re-invoca o mesmo metodo, cuja primeira linha e sige_tenant_write_guard(escola_id) e que corre self::userCan (sige_can / current_user_can) e os guards de estado. Tenant e permissao re-validados; a reposicao nao contorna verificacoes.
- O escola_id usado e o capturado da chamada original ja validada; a confirmacao nao transporta argumentos.

## 5. Gating

- Desligada por defeito (opcao sige_mfa_autoreplay = off). Ligada com a opcao on. Kill-switch SIGE_MFA_AUTOREPLAY_OFF.
- Com off, o consumo nao executa nada (verificado).

## 6. Integridade e governanca

- Sem novo endpoint: manifesto mantem-se em 195 itens. Regras de Kernel inalteradas (enforce 30; manifestIds == ruleIds; phpIds === jsonIds).
- Regras de calculo intactas: md5 de sige_fin_saldo_lancamento (f6d229...), sige_fin_saldo_sql (9c7117...), sige_fin_total_bruto_sql (d105bc...) inalterados.
- 12 documentos de governanca e 7 baselines presentes para v12.12.12 (gate verde).
- Versao sincronizada nas 3 fontes (cabecalho, SIGE_VERSION, BUILD.json) em 12.12.12; SIGE_GOV_VERSION em 12.12.12.
- Zero travessoes em codigo e documentos. Raiz com os 7 ficheiros canonicos. Lint PHP limpo.

## 7. Gates

- check-mfa-autoreplay.php: OK. smoke-mfa-autoreplay-v12-12-12.php: 26 verificacoes, todas verdes.
- Corredor completo tools/run-gates.php: verde (ver registo da entrega).

## 8. Rediagnostico adversarial

- Zero P0, zero P1. Riscos de execucao dupla e de contorno de permissao/tenant mitigados e verificados. Dois achados P2 aceites e documentados (A-R5 multiplas operacoes pendentes; A-R6 descritor obsoleto). Ver ADVERSARIAL_REVIEW-v12.12.12.md.
