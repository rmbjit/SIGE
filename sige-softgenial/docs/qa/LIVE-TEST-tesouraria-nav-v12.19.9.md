# LIVE-TEST Tesouraria - 3 grupos + gating por item (v12.19.9)

Mudança que TOCA GATING. Validar por perfil.

## Pré-condições

- Versão 12.19.9. Ctrl+F5.

## Estrutura esperada (quem tem o conjunto financeiro completo)

| Grupo | Itens |
|---|---|
| TESOURARIA | Registar Pagamento, Pagamentos Móveis, Reconciliação, Aprovações, Central de Cobranças, Painel Financeiro |
| FINANÇAS · RELATÓRIOS | Auditoria Financeira, Pagamentos / Turma, Relatório Mensal, Extractos e Caixa |
| FINANÇAS · CONFIGURAÇÃO | Lançamentos, Despesas, Centros de Custo, Lançar Mensalidades, Inscrições e Renovações, Planos de Pagamento, Preços e Serviços |

## Gating por item (matriz a confirmar)

| Cenário | Esperado |
|---|---|
| Só `financeiro.extractos_ver` | Vê **apenas** "Extractos e Caixa" (grupo RELATÓRIOS). Antes via também Registar Pagamento, Painel, Cobranças, Auditoria, etc. (que a rota bloqueava) |
| Só `financeiro.pagar` | Vê apenas "Registar Pagamento" (grupo TESOURARIA) |
| Só `financeiro.despesas_ver` | Vê apenas "Despesas" (grupo CONFIGURAÇÃO) |
| Cada item visível, ao clicar | **Abre** (não dá "acesso restrito"): menu concorda com a rota |

## M6 - Operação Escolar

| Cenário | Esperado |
|---|---|
| Tem portaria | "Portaria Digital" sob OPERAÇÃO ESCOLAR (já não num grupo PORTARIA isolado) |
| Tem transporte | "Transporte" no mesmo grupo |
| Tem ambos | Os dois itens no mesmo grupo |

## Anti-regressão

- Papel financeiro completo: vê todos os itens de sempre (agora em 3 grupos).
- Nenhum número/saldo muda. Sem erros de consola; sem violações CSP.
- Restantes grupos (Comunicação, Académico, etc.) inalterados.

## Clientes

Validar em pelo menos dois, idealmente com um perfil financeiro de privilégio
mínimo (ex.: só extractos ou só cobranças) para confirmar o gating por item.
