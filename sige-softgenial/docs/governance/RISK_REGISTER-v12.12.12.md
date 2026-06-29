# RISK_REGISTER - v12.12.12 - MFA de Operacao (correccao pos-rediagnostico)

## Bloqueadores

| Severidade | Risco | Estado |
|---|---|---|
| P0 | Nenhum. | - |
| P1 | Nenhum. | - |

## Achados do rediagnostico (todos fechados nesta versao)

| ID | Achado | Resolucao |
|---|---|---|
| A1 (P2) | Formulario do codigo nao uniforme entre operacoes. | Corrigido: formulario nas 10 operacoes (inline em POST, aviso em GET, sem duplicacao). |
| A2 (P3) | Bypass de MFA sob falha de SMTP. | Corrigido: modo estrito opt-in que bloqueia; defeito anti-lockout explicito. |
| A5 (P3) | Operacoes pela janela nao auditadas. | Corrigido: auditoria satisfied_window por operacao. |
| A7 (P3) | Em-dashes em documentos historicos do pacote. | Corrigido: pacote a zero em/en-dashes; gate passa a verificar documentos. |
| A3 (P3) | Fail-open por function_exists. | Documentado como design defensivo (fail-closed congelaria a financa se faltasse o ficheiro); coberto pelo gate. |
| A4 (P3) | Rate limit do endpoint em observe. | Documentado: tecto de 5 tentativas do OTP e o controlo activo; nao praticavel forcar 6 digitos. |
| A6 (P3) | Janela por utilizador, nao por escola. | Documentado: semantica sudo correcta; o tenant e garantido por guard separado. |

## Residuais nao bloqueadores (roteiro)

| Severidade | Risco residual | Fase futura |
|---|---|---|
| P2 | Reposicao automatica da operacao apos confirmacao (hoje o utilizador repete manualmente). | Incremento seguinte da Fase 4. |
| P2 | So ha entrega por email; sem TOTP nem aplicacao autenticadora. | Incremento seguinte da Fase 4. |
| P3 | Secret Vault universal e Ledger financeiro. | Fases 5 e 6. |

## Notas

A versao mantem-se desligada por defeito e sem alteracao de comportamento em instalacoes que nao a liguem. Nenhuma regra de calculo financeiro ou academico foi tocada.

## v12.12.11 (incremento TOTP)

- A2 (dependencia de email para autorizar a operacao): FECHADO para utilizadores inscritos em TOTP (confirmam com o codigo da aplicacao, sem email). Severidade residual P3 para quem nao inscrever (mitigado pelo modo estrito opt-in da v12.12.10.1).
- P2 (A-T6): codigo TOTP reutilizavel dentro da validade. Aceite; endurecimento futuro (contador de uso unico).
- P2 (A-T7): desactivar TOTP nao exige step-up. Aceite; endurecimento futuro (gating de definicoes de seguranca).
- P0: nenhum. P1: nenhum.

## v12.12.12 (reposicao automatica)

- Execucao dupla: MITIGADA (consumo atomico + guardas de estado das operacoes). P0 candidato fechado.
- Contorno de permissao/tenant pela reposicao: MITIGADO (re-validados dentro do metodo). P0 candidato fechado.
- P2 (A-R5): multiplas operacoes pendentes, vence a ultima. Aceite; repeticao manual para as anteriores.
- P2 (A-R6): descritor obsoleto. Mitigado por TTL curto e consumo na confirmacao.
- P0: nenhum. P1: nenhum.
