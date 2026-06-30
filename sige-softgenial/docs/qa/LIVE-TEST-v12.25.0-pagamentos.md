# LIVE-TEST Registar Pagamento (v12.25.0)

Mudança só visual/UX: secções colapsáveis (página mais curta) + modal de sucesso
por cima da barra lateral. O PHP do pagamento não foi tocado.

## Pré-condições
- Versão 12.25.0. Ctrl+F5 (CSS/JS). Perfil de Tesouraria.

## Apresentação / navegação
| Verificação | Esperado |
|---|---|
| Abrir um aluno | A página está mais curta: "1. Dívidas Actuais" aberta; "2. Adiantar Meses Futuros" e "3. Outros Serviços" fechadas |
| Cabeçalho de secção | Tem seta; clicar expande/colapsa; passar o rato realça; teclado (Enter/Espaço) também |
| Secção com algo já marcado | Abre automaticamente (não esconde escolhas) |

## Anti-regressão (CRÍTICO - é pagamento)
| Verificação | Esperado |
|---|---|
| Seleccionar dívidas e processar | Funciona EXACTAMENTE como antes; total/recibo corar correctos |
| Adiantar meses futuros | Expandir, seleccionar e pagar funciona como antes |
| Outros serviços (avulsos) | Expandir, seleccionar e pagar funciona como antes |
| Total / multa / desconto / parcial | Cálculos inalterados (não se tocou nas fórmulas) |
| Modal de sucesso | Aparece por cima da barra lateral; "Continuar" fecha e a página volta ao normal |
| Notificações (WhatsApp/E-mail) | Checkboxes quadradas (v12.24.2), funcionam como antes |

## Nota técnica
- O ficheiro `financeiro-pagamentos.php` ficou intacto; a melhoria está nos assets
  partilhados do financeiro (CSS/JS). Se algo parecer fora do sítio, basta repor
  os assets.

## Clientes
- Validar em pelo menos um cliente real, processando um pagamento de teste.
