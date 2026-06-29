# Phase Charter - v12.19.0 Dashboard Executivo & Inteligencia Operacional por Perfil

## Objectivo
Criar uma camada leve de leitura executiva no Painel Principal para orientar cada perfil real sobre prioridade, risco, regra segura, atalhos e areas visiveis.

## Baseline
- Versao origem: v12.18.0 aprovada em staging.
- Rollback seguro: v12.18.0.

## Escopo incluido
- Include read-only de inteligencia operacional por perfil.
- CSS e JS dedicados ao bloco do dashboard.
- Filtro por permissoes e mapa institucional existente.
- Documentacao de QA, rollback e rastreabilidade.
- Gates de contrato, smoke, manifesto e regressao sensivel.

## Escopo excluido
- Alteracao de formulas financeiras.
- Alteracao de formulas academicas.
- Alteracao de permissoes reais.
- Alteracao de schema ou dados historicos.
- Refactor de admin-shell.
- Refactor do dashboard PHP core.
- Refactor de Alunos, Portaria, Financeiro ou Academico.

## Perfis afectados
- Director.
- Financeiro.
- Secretaria.
- Academico.
- Comunicacao.
- Portaria.
- Administrador tecnico.

## Riscos principais
- Exibir atalhos sem permissao.
- Aumentar peso do dashboard.
- Quebrar mobile.
- Sobrepor a camada de fluxo seguro v12.18.0.
- Confundir utilizadores com excesso de informacao.

## Decisao tecnica tomada
A implementacao fica fora do dashboard core, via include e assets proprios, para reduzir risco de regressao.

## Definition of Done
- Versao sincronizada em header, SIGE_VERSION e BUILD.json.
- Include e assets presentes.
- Contexto so aparece no dashboard.
- Sem escrita na base de dados.
- Sem fetch, AJAX ou cookies no JS.
- Atalhos derivados apenas de accoes ja filtradas por permissao.
- Gates verdes.
- ZIP integro.
