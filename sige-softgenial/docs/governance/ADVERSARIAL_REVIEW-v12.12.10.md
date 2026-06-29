# ADVERSARIAL_REVIEW - v12.12.10 - MFA de Operacao (Step-up)

Rediagnostico adversarial do incremento 1 da Fase 4, por execucao real sobre a base v12.12.9.

## Metodo

Tentou-se quebrar o incremento por seis vias: injeccao de regressao nos pontos de integracao, alteracao silenciosa do calculo financeiro, introducao de em-dashes, regressao dos baselines de tenant, fuga ao guard (caminhos de bypass) e verificacao do comportamento fail-closed.

## Resultados

- Pontos de integracao: o gate check-mfa-stepup confirma os 10 contextos de operacao critica com o guard sige_mfa_require_step_up (6 servico, 2 credenciais, 2 caixa). Sem regressao.
- Calculo financeiro: as tres funcoes (sige_fin_saldo_lancamento, sige_fin_saldo_sql, sige_fin_total_bruto_sql) sao byte-identicas a v12.12.8.1 (md5 iguais). Nao foram tocadas.
- Em-dashes: varredura de todo o codigo (.php, .js, .css) com Python; zero ocorrencias de U+2014 ou U+2013.
- Baselines de tenant: baseline corrente 0; congelados 138 (v12.12.8.1) e 139 (v12.12.8) intactos; ratchet monotonico v12.12.10=0 <= v12.12.9=0.
- Bypass: as operacoes que movem dinheiro centralizam-se em SIGE_FinanceActionService (18 chamadas, todas depois do guard); nao ha UPDATE directo de estado de pagamento/lancamento fora do servico. A anulacao de recibo encaminha por estornarPagamento. Os usos de status cancelado fora do servico pertencem a mensagens (WhatsApp/comunicacoes), nao a financas.
- Fail-closed: o smoke confirma que o guard bloqueia (devolve false) quando ligado, aplicavel e sem verificacao recente, e permite nos casos legitimos (desligado, fora do perfil, recentemente verificado, falha de SMTP).
- Diff de ficheiros vs v12.12.9: exactamente o conjunto pretendido (4 ficheiros de codigo com guards, a regra de Kernel, os ficheiros de versao/governacao e 3 ficheiros novos). Sem alteracoes colaterais. Nenhum ficheiro removido.

## Achados

| Severidade | Achado | Decisao |
|---|---|---|
| P0 | Nenhum. | - |
| P1 | Nenhum. | - |
| P3 | Cada ponto de integracao envolve o guard em function_exists; se o modulo nao carregar, a operacao prossegue (fail-open ao nivel da integracao). | Aceite por design: a funcionalidade e aditiva e opt-in. O modulo e carregado incondicionalmente no arranque do plugin (a seguir ao escudo de login) e a sua presenca e coberta pelo gate. Em instalacoes sem o modulo, o comportamento e o de v12.12.9. |

## Decisao

O incremento fecha o objectivo da Fase 4 (incremento 1) sem regressao e sem tocar regras de calculo. Zero P0 e zero P1. Desligado por defeito, com desactivacao de emergencia e anti-lockout. Pode ser instalado e, quando o cliente o decidir, ligado em staging para validacao funcional do fluxo de confirmacao.
