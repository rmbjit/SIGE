# Rediagnóstico dos grupos académicos (barra lateral) - M4

Versão de referência: 12.19.11
Data: 2026-06-29
Ficheiro: `includes/admin-shell.php`
Estado: diagnóstico. Nada implementado.

---

## 1. Grupos académicos actuais e respectivo gating

| Grupo | Condição (resumo) | Itens |
|---|---|---|
| SECRETARIA | `!scoped_prof && !$ff_academico && $ff_cadastro && can(alunos/turmas/contas)` | Alunos, Turmas, Contas de Alunos |
| ACADÉMICO | `!scoped_prof && $ff_academico && can(...)` | Disciplinas, Matriz, Currículos[BETA], Turmas, Alunos, Abertura, Encerramento, Contas de Alunos |
| CONSULTA | `can(alunos.ver) && !can(académico/contas/config)` | Alunos |
| DOCENTES | `$ff_academico && can(turmas/notas/pautas/boletins/dec/pauta/actas/aprovar)` | Minhas Turmas, Lançar Notas, Pautas, Presenças, Aproveitamento, DEC, Pauta Final, Acta, Aprovar Notas |
| GESTÃO ESCOLAR | `$ff_academico && can(auditoria_notas/estatisticas)` | Auditoria, Estatísticas da Escola |

Notas de gating: SECRETARIA e ACADÉMICO são **mutuamente exclusivos** (`!$ff_academico`
vs `$ff_academico`); por isso o cadastro "salta" para dentro de ACADÉMICO quando o
módulo académico está ligado. CONSULTA é fallback do visualizador mínimo.

---

## 2. O que cada ecrã faz (lido nos cabeçalhos)

| Ecrã | Natureza |
|---|---|
| Alunos, Turmas, Contas de Alunos | **Cadastro** (secretaria) |
| Disciplinas, Matriz Curricular, Currículos[BETA] | **Estrutura curricular** |
| Abertura / Encerramento de Ano | **Gestão do ano lectivo** |
| Minhas Turmas, Lançar Notas, Pautas, Presenças | **Sala de aula (docente)** |
| Aproveitamento, DEC, Pauta Final, Acta | **Avaliação / documentos oficiais** |
| Aprovar Notas | **Controlo (validação de notas)** |
| Auditoria (de notas) | **Controlo / auditoria** |
| Estatísticas da Escola | **Análise** |

---

## 3. Problemas reais (M4)

| # | Gravidade | Problema |
|---|---|---|
| A1 | Médio | **ACADÉMICO é um saco**: mistura 3 domínios (cadastro + estrutura curricular + gestão de ano) sob um rótulo |
| A2 | Médio | **Director vê ACADÉMICO + DOCENTES + GESTÃO ESCOLAR** ao mesmo tempo: navegação académica muito longa, rótulos a sobrepor-se conceptualmente |
| A3 | Baixo | **"GESTÃO ESCOLAR"** é fino (2 itens) e mal nomeado (parece gerir a escola toda; é só auditoria+estatística académica) |
| A4 | Baixo | **DOCENTES** mistura trabalho diário (Minhas Turmas, Notas, Pautas, Presenças) com documentos oficiais (DEC, Pauta Final, Acta) e controlo (Aprovar Notas) |
| A5 | Baixo | **Cadastro sem casa estável**: vive em SECRETARIA (se `!$ff_academico`) ou dentro de ACADÉMICO (se `$ff_academico`) |

---

## 4. Proposta (4 grupos por domínio)

| Grupo | Itens |
|---|---|
| **SECRETARIA** | Alunos · Turmas · Contas de Alunos |
| **ESTRUTURA ACADÉMICA** | Disciplinas · Matriz Curricular · Currículos[BETA] · Abertura · Encerramento |
| **SALA DE AULA** | Minhas Turmas · Lançar Notas · Pautas · Presenças |
| **AVALIAÇÃO & CONTROLO** | Aproveitamento · DEC · Pauta Final · Acta · Aprovar Notas · Auditoria · Estatísticas da Escola |

(Alternativa: separar "ANÁLISE ACADÉMICA" = Auditoria + Estatísticas num 5.º grupo,
se preferir distinguir documentos de análise.)

---

## 5. Mudanças de gating necessárias (a parte delicada)

Ao contrário da Tesouraria (tudo dentro de um só `$ff_financeiro`), aqui mexe-se
em condições por papel:

1. **SECRETARIA passa a mostrar cadastro sempre** que houver permissões de
   cadastro (remover o `!$ff_academico` da condição), e **retira-se o cadastro de
   ACADÉMICO**. Dá casa estável ao cadastro e resolve A1/A5.
2. **CONSULTA** torna-se redundante (o visualizador mínimo de alunos passa a cair
   na SECRETARIA com só "Alunos"); avaliar remover.
3. **ESTRUTURA ACADÉMICA** = o antigo ACADÉMICO sem cadastro (Disciplinas, Matriz,
   Currículos, Abertura, Encerramento), mantendo `!scoped_prof && $ff_academico`.
4. **DOCENTES** divide-se em SALA DE AULA (diário) e AVALIAÇÃO & CONTROLO
   (documentos + aprovação), preservando o escopo do professor.
5. **GESTÃO ESCOLAR** funde-se em AVALIAÇÃO & CONTROLO (ou vira ANÁLISE ACADÉMICA).

Risco: médio-alto. Toca visibilidade por perfil (professor com escopo, secretaria,
director, pedagógico) e a exclusividade SECRETARIA/ACADÉMICO/CONSULTA. Cada item
mantém a sua guarda de permissão (igual ao mapa de rota). Exige teste por perfil.

---

## 6. Decisão pendente

(a) Aprova a proposta de **4 grupos** (ou a alternativa com 5, separando Análise)?
(b) Confirmar que cadastro (Alunos/Turmas/Contas) deve ter **casa própria
(SECRETARIA) sempre**, independentemente do módulo académico estar ligado.
