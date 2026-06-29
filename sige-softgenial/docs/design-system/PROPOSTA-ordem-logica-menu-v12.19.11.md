# Proposta de ordenação lógica da barra lateral (fluxo do ano lectivo)

Versão de referência: 12.19.11
Data: 2026-06-29
Estado: proposta. Nada implementado.
Princípio: a barra lê-se de cima para baixo na sequência real de gerir a escola:
**preparar → matricular → cobrar → ensinar → avaliar → acompanhar → fechar**, e só
depois as áreas de apoio (comunicação, operação, configuração, sistema).

---

## 1. Estrutura proposta (ordem = fluxo)

| Ordem | Grupo | Itens | Fase do ciclo |
|---|---|---|---|
| 0 | **Painel Principal** | (visão geral) | Entrada |
| 1 | **ESTRUTURA ACADÉMICA** | Abertura de Ano · Turmas · Disciplinas · Matriz Curricular · Currículos `BETA` · Encerramento de Ano | Preparar o ano |
| 2 | **SECRETARIA** | Alunos · Contas de Alunos | Matricular |
| 3 | **FATURAÇÃO** | Lançar Mensalidades · Lançamentos · Inscrições e Renovações · Planos de Pagamento | Cobrar (gerar) |
| 4 | **TESOURARIA** | Registar Pagamento · Pagamentos Móveis `BETA` · Extractos e Caixa · Central de Cobranças · Despesas | Cobrar (receber) |
| 5 | **SALA DE AULA** | Minhas Turmas · Lançar Notas · Pautas · Presenças | Ensinar |
| 6 | **AVALIAÇÃO & DOCUMENTOS** | Aproveitamento · Aprovar Notas · Pauta Final · Acta · DEC | Avaliar |
| 7 | **RELATÓRIOS & ANÁLISE** | Painel Financeiro · Relatório Mensal · Pagamentos/Turma · Reconciliação `BETA` · Auditoria Financeira · Auditoria de Notas · Estatísticas da Escola | Acompanhar |
| 8 | **EQUIPA (RH)** | Equipa e Professores | Apoio |
| 9 | **JARDIM DE INFÂNCIA** | (itens do jardim) | Apoio (regime) |
| 10 | **COMUNICAÇÃO** | Central de Comunicações · Operação WhatsApp · Circulares `BETA?` | Apoio |
| 11 | **OPERAÇÃO ESCOLAR** | Portaria Digital · Transporte | Apoio |
| 12 | **CONFIGURAÇÃO** | Preços e Serviços · Centros de Custo · Saúde do Sistema · Perfis e Permissões · Centro de Configuração | Suporte |
| 13 | **PRIVACIDADE E DADOS** | Inventário · Acesso · Apagamento · Retenção | Suporte |

---

## 2. Lógica do fluxo (porquê esta ordem)

1. **Preparar**: antes de haver alunos, abre-se o ano e montam-se turmas,
   disciplinas e matriz. (Abertura no topo; Encerramento fica no mesmo grupo de
   gestão do ano, como par administrativo, em vez de um grupo isolado.)
2. **Matricular**: inscrever o aluno (Alunos) e abrir a sua conta.
3. **Cobrar**: primeiro gerar a cobrança (Faturação), depois receber (Tesouraria).
4. **Ensinar**: o dia-a-dia do professor (turmas, notas, pautas, presenças).
5. **Avaliar**: consolidar resultados e emitir documentos oficiais; aprovar notas.
6. **Acompanhar**: relatórios e análise (financeiros e académicos juntos, porque
   "acompanhar" é uma fase transversal do ciclo).
7. **Apoio/Suporte**: equipa, jardim, comunicação, operação, configuração,
   privacidade.

---

## 3. Decisões de bom senso (a confirmar)

| # | Tema | Opção tomada na proposta | Alternativa |
|---|---|---|---|
| O1 | Relatórios | **Um só grupo "Relatórios & Análise"** juntando financeiro e académico (visão de "acompanhar") | Manter relatórios financeiros dentro do bloco financeiro e análise académica à parte |
| O2 | Abertura/Encerramento | **Juntos em "Estrutura Académica"** (par de gestão do ano), evitando grupo de 1 item | Encerramento isolado no fim do fluxo |
| O3 | Domínios interligados | **Fluxo interligado** (Faturação/Tesouraria entram logo após Secretaria) | Manter tudo do financeiro contíguo e tudo do académico contíguo |

---

## 4. Nota de risco

Esta ordenação re-sequencia a barra inteira e mexe nas condições de gating por
papel (sobretudo no lado académico: cadastro com casa própria, escopo do
professor, exclusividade Secretaria/Académico/Consulta). Risco médio-alto; cada
item mantém a sua guarda igual à rota; exige teste por perfil (professor com
escopo, secretaria, tesouraria, director, pedagógico).

---

## 5. Decisão

(a) Aprova a **ordem lógica** da secção 1? (b) Confirmar O1, O2, O3 da secção 3.
Com isto fixo, implemento por fases (primeiro a reordenação + os 4 grupos
académicos do M4), com auto-revisão adversarial e LIVE-TEST por perfil.
