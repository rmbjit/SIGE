# LIVE-TEST Financeiro - 4 grupos por domínio (v12.19.11)

Mudança de IA (apresentação). O gating por item não muda face ao v12.19.9/10;
só a organização. Validar em staging.

## Pré-condições

- Versão 12.19.11. Ctrl+F5.

## Estrutura esperada (perfil financeiro completo)

| Grupo | Itens |
|---|---|
| TESOURARIA | Registar Pagamento · Pagamentos Móveis `BETA` · Extractos e Caixa · Central de Cobranças · Despesas |
| FATURAÇÃO | Lançar Mensalidades · Lançamentos · Inscrições e Renovações · Planos de Pagamento |
| RELATÓRIOS & CONTROLO | Painel Financeiro · Relatório Mensal · Pagamentos / Turma · Auditoria Financeira · Reconciliação `BETA` · Aprovações `BETA` |
| FINANÇAS · CONFIGURAÇÃO | Preços e Serviços · Centros de Custo |

## Confirmar

- "Painel Financeiro" está agora em RELATÓRIOS & CONTROLO (não em TESOURARIA).
- "Extractos e Caixa" e "Despesas" estão em TESOURARIA.
- Lançamentos / Lançar Mensalidades / Inscrições / Planos estão em FATURAÇÃO.
- Os 3 selos BETA continuam (Pagamentos Móveis, Reconciliação, Aprovações).

## Anti-regressão

- Cada item abre (menu concorda com a rota); perfil de privilégio mínimo só vê o seu.
- Nenhum item financeiro desapareceu (17 itens, agora em 4 grupos).
- Sem erros de consola; sem violações CSP.

## Clientes

Validar em pelo menos dois, incluindo um perfil financeiro de privilégio mínimo.
