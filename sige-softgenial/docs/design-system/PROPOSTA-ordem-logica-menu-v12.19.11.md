# Proposta de ordenação lógica da barra lateral (fluxo do ano lectivo) - v2

Versão de referência: 12.19.11
Data: 2026-06-29
Estado: proposta (revista). Nada implementado.

## Correcção face à v1 (lida a fundo o que cada módulo faz)

A v1 colocou em "apoio/suporte" áreas que são, na verdade, **operação e ensino**:

| Módulo | v1 (errado) | Realidade (cabeçalho) | v2 |
|---|---|---|---|
| Jardim de Infância | apoio/suporte | **Regime académico pré-escolar completo**: Diário + Avaliação por Critérios, Presenças, Nutrição e Saúde, Boletim, Análises (SNE) | Bloco próprio na fase de **ensinar+avaliar** (regime paralelo) |
| Portaria Digital | apoio/suporte | **Controlo de Acesso diário** (guarda valida entrada) | **Operação diária** |
| Transporte | apoio/suporte | Gestão de rotas e preços | **Operação diária** |

Verdadeiro suporte = apenas **Configuração, Privacidade e Sistema**.

---

## 1. Estrutura proposta (ordem = fluxo)

| # | Grupo | Itens | Fase |
|---|---|---|---|
| 0 | **Painel Principal** | visão geral | Entrada |
| 1 | **ESTRUTURA ACADÉMICA** | Abertura de Ano · Turmas · Disciplinas · Matriz Curricular · Currículos `BETA` · Encerramento de Ano | Preparar o ano |
| 2 | **EQUIPA (RH)** | Equipa e Professores | Preparar (pessoas) |
| 3 | **SECRETARIA** | Alunos · Contas de Alunos | Matricular |
| 4 | **FATURAÇÃO** | Lançar Mensalidades · Lançamentos · Inscrições e Renovações · Planos de Pagamento | Cobrar (gerar) |
| 5 | **TESOURARIA** | Registar Pagamento · Pagamentos Móveis `BETA` · Extractos e Caixa · Central de Cobranças · Despesas | Cobrar (receber) |
| 6 | **SALA DE AULA** | Minhas Turmas · Lançar Notas · Pautas · Presenças | Ensinar (regular) |
| 7 | **AVALIAÇÃO & DOCUMENTOS** | Aproveitamento · Aprovar Notas · Pauta Final · Acta · DEC | Avaliar (regular) |
| 8 | **JARDIM DE INFÂNCIA** | Diário de Actividades · Presenças · Nutrição e Saúde · Boletim Pré-Escolar · Diários & Análises | Ensinar+avaliar (pré-escolar) |
| 9 | **OPERAÇÃO ESCOLAR** | Portaria Digital · Transporte | Operação diária |
| 10 | **COMUNICAÇÃO** | Central de Comunicações · Operação WhatsApp · Circulares | Transversal |
| 11 | **RELATÓRIOS & ANÁLISE** | Painel Financeiro · Relatório Mensal · Pagamentos/Turma · Reconciliação `BETA` · Auditoria Financeira · Auditoria de Notas · Estatísticas da Escola | Acompanhar |
| 12 | **CONFIGURAÇÃO** | Preços e Serviços · Centros de Custo · Saúde do Sistema · Perfis e Permissões · Centro de Configuração | Suporte |
| 13 | **PRIVACIDADE E DADOS** | Inventário · Acesso · Apagamento · Retenção | Suporte |

---

## 2. Lógica do fluxo

Preparar o ano (estrutura + equipa) → matricular alunos → cobrar (gerar e receber)
→ ensinar e avaliar (regular e pré-escolar, regimes paralelos) → operar o dia-a-dia
(portaria, transporte, comunicação) → acompanhar (relatórios e análise) → e, por
fim, o suporte (configuração, privacidade, sistema). Abertura/Encerramento ficam
juntos no grupo de Estrutura, como par de gestão do ano.

---

## 3. Decisões de bom senso (a confirmar)

| # | Escolha na proposta | Alternativa |
|---|---|---|
| O1 | "Relatórios & Análise" único (financeiro + académico) | Separar relatórios financeiros e análise académica |
| O2 | Abertura + Encerramento juntos em Estrutura Académica | Encerramento isolado no fim |
| O3 | Fluxo interligado (Faturação/Tesouraria logo após Secretaria) | Tudo do financeiro contíguo e tudo do académico contíguo |
| O4 | Jardim como bloco próprio na fase de ensino (regime paralelo) | Jardim logo após Secretaria (para jardins puros) |
| O5 | Equipa (RH) na fase "preparar" | Equipa noutra posição |

---

## 4. Nota de risco

Re-sequencia a barra inteira e mexe no gating por papel (cadastro com casa
própria, escopo do professor, exclusividade Secretaria/Académico/Consulta, regime
de jardim). Risco médio-alto; cada item mantém a guarda igual à rota; teste por
perfil obrigatório (professor com escopo, educador/jardim, secretaria, tesouraria,
director, pedagógico, guarda).

---

## 5. Decisão

(a) Aprova a ordem da secção 1? (b) Confirmar O1-O5 da secção 3.
