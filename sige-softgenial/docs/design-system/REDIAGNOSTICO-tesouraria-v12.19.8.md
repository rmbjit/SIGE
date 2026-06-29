# Rediagnóstico do grupo TESOURARIA (barra lateral)

Versão de referência: 12.19.8
Data: 2026-06-29
Ficheiro: `includes/admin-shell.php` (linhas 2091-2118)
Estado: diagnóstico. Nada implementado.

---

## 1. Estrutura real

Um único rótulo "TESOURARIA" cobre **17 itens**, em dois blocos de permissão:

| Bloco | Condição (`can_any`) | Itens |
|---|---|---|
| 1 (rotulado TESOURARIA) | `financeiro.pagar, dashboard_ver, ver, cobrancas_ver, auditoria_ver, pagamentos_turma_ver, relatorio_mensal_ver, extractos_ver` | Registar Pagamento, Pagamentos Móveis*, Reconciliação*, Aprovações**, Painel Financeiro, Central de Cobranças, Auditoria Financeira, Pagamentos/Turma, Relatório Mensal, Extractos e Caixa |
| 2 (sem rótulo, continua TESOURARIA) | `lancamentos_ver, lancamentos_gerir, despesas_ver, centros_custo_ver, lancar_mensalidades, servicos_ver, configurar_precos` | Lançamentos, Despesas, Centros de Custo, Lançar Mensalidades, Inscrições e Renovações, Planos de Pagamento, Preços e Serviços |

\* M-Pesa e Reconciliação estão sob `sige_mpesa_pode_gerir()`.
\*\* Aprovações sob `sige_fin_aprovacao_pode_aceder()`.

---

## 2. Achados

| # | Gravidade | Achado |
|---|---|---|
| **T1** | **Alto (bug de acesso, classe M1b)** | A maioria dos itens **não tem guarda de permissão própria**: aparecem se QUALQUER permissão do bloco existir. Ex.: um utilizador só com `financeiro.extractos_ver` vê Registar Pagamento, Painel Financeiro, Cobranças, Auditoria, Pagamentos/Turma e Relatório — e a **rota bloqueia** todos esses (menu ≠ rota) |
| T2 | Médio | 17 itens sob um só rótulo = parede difícil de varrer (o M3 original) |
| T3 | Baixo | O bloco 2 (lançamentos/configuração) **não tem rótulo**: visualmente cola-se à Tesouraria |
| Ok | - | Sub-guardas de M-Pesa/Reconciliação e Aprovações estão correctas; manter |

---

## 3. Mapa item → permissão de rota (fonte: `$sige_view_permission_map`)

Para cada item, a guarda de menu DEVE igualar a permissão da rota:

| Item | view | Permissão da rota |
|---|---|---|
| Registar Pagamento | financeiro-pagamentos | `financeiro.pagar` |
| Pagamentos Móveis | financeiro-mpesa | `sige_mpesa_pode_gerir()` (mantém) |
| Reconciliação | financeiro-reconciliacao | `sige_mpesa_pode_gerir()` (mantém) |
| Aprovações | financeiro-aprovacoes | `sige_fin_aprovacao_pode_aceder()` (mantém) |
| Painel Financeiro | financeiro-dashboard | `financeiro.dashboard_ver, financeiro.ver` |
| Central de Cobranças | financeiro-devedores | `financeiro.cobrancas_ver` |
| Auditoria Financeira | financeiro-auditoria | `financeiro.auditoria_ver` |
| Pagamentos / Turma | pagamentos-turma | `financeiro.pagamentos_turma_ver` |
| Relatório Mensal | financeiro-relatorio-mensal | `financeiro.relatorio_mensal_ver` |
| Extractos e Caixa | financeiro-extratos | `financeiro.extractos_ver` |
| Lançamentos | financeiro-lancamentos | `financeiro.lancamentos_ver` |
| Despesas | financeiro-despesas | `financeiro.despesas_ver` |
| Centros de Custo | financeiro-centros | `financeiro.centros_custo_ver` |
| Lançar Mensalidades | financeiro-gerador | `financeiro.lancar_mensalidades` |
| Inscrições e Renovações | financeiro-inscricoes | `financeiro.lancamentos_gerir, financeiro.lancar_mensalidades` |
| Planos de Pagamento | financeiro-planos | `financeiro.planos_ver, financeiro.planos_gerir` |
| Preços e Serviços | financeiro-config | `financeiro.servicos_ver, financeiro.configurar_precos` |

---

## 4. Proposta

### 4.1 Corrigir T1 (prioridade) - guarda por item

Cada item passa a ter `if ($sige_menu_can_any([<perm da rota>]))`, igual ao mapa.
Elimina o menu ≠ rota (mesma correcção que fizemos para Comunicação).

### 4.2 Dividir o grupo (T2/T3)

Opção base (mínima, segura): dois rótulos, mapeando os dois blocos existentes:

| Grupo | Itens |
|---|---|
| **TESOURARIA** (operação + relatórios) | bloco 1 |
| **FINANÇAS · CONFIGURAÇÃO** | bloco 2 |

Opção alternativa (mais arrumada, 3 grupos): TESOURARIA (operação diária:
Registar Pagamento, Móveis, Reconciliação, Aprovações, Cobranças, Painel) ·
**FINANÇAS · RELATÓRIOS** (Auditoria, Pagamentos/Turma, Relatório Mensal,
Extractos) · **FINANÇAS · CONFIGURAÇÃO** (bloco 2).

### 4.3 M6 (à parte)

Fundir Portaria + Transporte sob um único rótulo "OPERAÇÃO ESCOLAR".

---

## 5. Risco e teste

- 4.1 toca gating de muitos itens. Para papéis-padrão com o conjunto financeiro
  completo (ex.: `sige_financeiro`), nada muda. Para papéis de privilégio mínimo,
  o menu passa a ser exacto (deixa de mostrar o que a rota bloqueia).
- Sem hex novo (rótulos sem `--ml-color`, como em COMUNICAÇÃO).
- Teste por perfil obrigatório (matriz no LIVE-TEST).

## 6. Decisão pendente

(a) Aplicar 4.1 (guardas por item) + 4.2 **opção base (2 grupos)** ou **alternativa
(3 grupos)**? (b) Incluir M6 no mesmo incremento?
