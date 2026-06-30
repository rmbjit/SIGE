# LIVE-TEST Financeiro - scroll dos modais + empilhamento global + lembrar secções (v12.29.0)

## Pré-condições
- Versão 12.29.0. Ctrl+F5 (CSS/JS). Desktop/portátil (barra lateral visível).

## Scroll dos modais (o problema reportado)
| Ecrã / modal | Esperado |
|---|---|
| Gerador (confirmação/sucesso) | O corpo do modal rola; o botão no fundo é alcançável |
| Despesas / Centros / Planos (modais .sige-lanc-modal) | Corpo rola; cabeçalho/rodapé fixos |
| Inscrições (modal .sg-inspro) | Corpo rola |
| Modais genéricos (.sige-modal) | Corpo rola; cabeçalho/rodapé fixos |

## Empilhamento (global)
| Verificação | Esperado |
|---|---|
| Qualquer modal financeiro | Aparece por cima da barra lateral (não atrás) |

## Lembrar secções (Pagamentos / Config)
| Fluxo | Esperado |
|---|---|
| Expandir uma secção, recarregar/gravar | A secção continua expandida (não reabre tudo) |
| Modo privado do navegador | Funciona na mesma (só não memoriza) |

## Anti-regressão (é financeiro)
- Processar pagamento, gerar mensalidades, gravar serviços/regras: tudo como antes.
- Sem erros de consola novos; sem violações CSP.

## Clientes
- Validar num cliente real: abrir um modal financeiro alto e confirmar o scroll.
