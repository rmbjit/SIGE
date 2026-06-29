# QA Smoke Gate - SIGE SoftGenial v12.11.9.88

**Build:** Financeiro Cash Reconciliation PRO  
**Módulo:** `admin/finance/financeiro-extratos.php`  
**Data:** 2026-06-10

## Decisão do gate

**APROVADO para instalação em ambiente de teste/validação real.**

A Fase 2 foi aplicada como evolução incremental sobre a `v12.11.9.87.1`, mantendo as aprendizagens da Fase 1: compatibilidade, estados seguros, validação server-side, prevenção de duplo envio e preservação dos cálculos financeiros existentes.

## Validações executadas

| Verificação | Resultado |
|---|---:|
| `php -l admin/finance/financeiro-extratos.php` | OK |
| `php -l sige-softgenial.php` | OK |
| PHP lint completo em 243 ficheiros PHP | OK |
| Smoke test da Fase 2 | OK |
| Preservação dos hardenings da Fase 1 | OK |
| Preservação do hotfix Deep Smoke v12.11.9.87.1 | OK |
| Validação básica de CSS inline do módulo | OK |
| Validação básica de `assets/style.css` | OK |
| `node --check` dos scripts inline extraídos | OK |
| Ausência de migração `ALTER TABLE` no módulo | OK |

## Pontos críticos cobertos

- Assistente de fecho de caixa renderizado no modo diário quando o caixa está aberto.
- Valores contados por método de pagamento.
- Diferença automática entre sistema e valor contado.
- Validação server-side da checklist de reconciliação.
- Observação obrigatória quando existe divergência.
- Snapshot de reconciliação gravado em `observacoes` com marcador compatível `[SIGE_RECON_V1]`.
- Reabertura preserva observações/snapshots anteriores.
- Resumo PRO do fecho apresenta sistema, contado, diferença e saldo estimado.
- Histórico de fechos da data disponível no módulo.
- Termo de fecho imprimível.
- Não foram alterados cálculos canónicos, recibos, estornos, exportação existente, permissões existentes ou schema da base de dados.

## Observações técnicas

A decisão técnica foi **não introduzir migração de base de dados nesta fase**. A reconciliação é armazenada dentro de `observacoes` de forma estruturada e reversível, preservando compatibilidade com instalações existentes. Uma tabela própria de auditoria/fechos pode ser criada numa fase posterior, quando avançarmos para permissões/aprovação/auditoria profunda.

## Teste recomendado em ambiente real

1. Abrir `Financeiro > Extractos/Caixa` no modo diário.
2. Confirmar que o assistente de reconciliação aparece quando o caixa está aberto.
3. Fechar caixa com valores contados iguais aos do sistema.
4. Testar divergência sem observação e confirmar bloqueio.
5. Testar divergência com observação válida e confirmar gravação.
6. Reabrir caixa e confirmar que a observação anterior fica preservada.
7. Voltar a fechar e verificar histórico de fechos.
8. Imprimir termo de fecho.
9. Confirmar que recibos, estornos e Excel continuam a funcionar como antes.
