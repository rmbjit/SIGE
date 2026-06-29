# Phase Charter - v12.14.4 - CSP Regression Closure: Documents & Buttons

## Objectivo
Corrigir regressões observadas após a Fase 10 de CSP enforcement/zero-inline, sem enfraquecer a política CSP e sem voltar a `unsafe-inline`.

## Escopo
- Documento financeiro oficial do aluno / histórico financeiro 360º.
- Botões documentais: imprimir/guardar PDF, fechar e voltar.
- Hidratador central de eventos legados usado no shell SIGE.
- Padrões de eventos simples afectados por CSP em módulos como Alunos, Turmas, Financeiro e RH.

## Não-escopo
- Instalação de motor PDF server-side nesta versão.
- Migração física de todos os modelos documentais históricos.
- Alterações em cálculos financeiros, académicos, permissões ou tenant isolation.

## Critérios de aceitação
- Histórico financeiro do aluno volta a apresentar layout oficial, não HTML cru do browser.
- Botões `Guardar PDF / Imprimir` e `Fechar` funcionam sem inline handlers.
- Botões declarativos/legados continuam funcionais no shell admin sob CSP enforcement.
- Não há `unsafe-inline`, `eval` ou `new Function` introduzidos.
- Gates, lint e smokes executados.

## Riscos
- Outros documentos antigos podem ainda precisar de migração de CSS/JS para assets externos.
- Motor PDF server-side exige fase própria devido a dependências, fontes, paginação e homologação.
