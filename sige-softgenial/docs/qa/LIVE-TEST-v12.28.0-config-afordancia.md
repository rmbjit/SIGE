# LIVE-TEST Config - afordância dos campos + Períodos colapsável (v12.28.0)

Mudança só visual/UX.

## Pré-condições
- Versão 12.28.0. Ctrl+F5 (CSS).

## Apresentação
| Verificação | Esperado |
|---|---|
| Campos (selects/inputs) | Borda nítida; selects com SETA de dropdown; hover realça (claramente clicáveis) |
| Inputs de data | Mantêm o seletor nativo (calendário) |
| Gestão de Períodos | Colapsável; começa fechada; cabeçalho clicável com seta |
| Modal Adicionar/Editar serviço | Formulário longo rola; cabeçalho do modal fixo no topo |

## Anti-regressão (é financeiro)
| Verificação | Esperado |
|---|---|
| Guardar Multa/Descontos/Creche/Transporte | Como antes |
| Adicionar/Editar serviço (selects funcionam) | Selecção dos valores funciona; gravar correcto |
| Gestão de Períodos (fechar/abrir meses) | Funciona como antes (expandir a secção primeiro) |

## Clientes
- Validar num cliente real: editar um serviço (confirmar setas nos selects) e
  guardar.
