# Phase Charter - v12.16.2 Baseline Preservation & Operational Readiness

## Nome da fase
v12.16.2 - Baseline Preservation & Operational Readiness.

## Objectivo
Congelar a v12.16.1 aprovada como baseline operacional e preparar o sistema para proximas fases com menor risco de regressao.

## Escopo incluido
- Baseline aprovado v12.16.1 registado como fonte de verdade.
- Matriz de regressao obrigatoria para proximas releases.
- Checklist pos-instalacao por perfil real.
- Gates adicionais de preservacao, readiness e manifesto.
- Documentacao de rollback e criterios de aceite.
- Versionamento para 12.16.2.

## Escopo excluido
- Alteracao de formulas financeiras.
- Alteracao de regras academicas.
- Alteracao de permissoes reais ou capacidades de perfis.
- Alteracao de schema, migracoes, dados historicos ou metadados existentes.
- Refactor de alunos_lista.php.
- Refactor do shell global.
- Novas funcionalidades.

## Perfis afectados
Administrador tecnico, director, financeiro, secretaria, professor, guarda, encarregado e aluno. O impacto esperado e operacional/documental, nao funcional.

## Modulos afectados
BUILD.json, CHANGELOG.md, docs, tools e versionamento principal. Modulos financeiros, academicos, permissoes, tenant isolation e dados ficam protegidos por hashes.

## Riscos principais
- Regressao futura por avancar sem checklist.
- Validacao humana incompleta apos instalacao.
- Confusao entre baseline aprovado e fase nova.
- Alteracao inadvertida de ficheiros P0 em fases seguintes.

## Regras que nao podem ser alteradas
- Financeiro: pagamentos, recibos, dividas, multas, descontos, transporte, saldos e ledger.
- Academico: notas, pautas, boletins, DEC, actas e formulas.
- Seguranca: tenant isolation, permissoes reais, security kernel, endpoints sensiveis.
- Dados: schema, historico, migracoes, normalizacao ou apagamento.

## Solucoes previstas
- Gate BP-16-02-01: hashes protegidos dos ficheiros P0/P1.
- Gate OR-16-02-02: matriz operacional, checklist e staging validation presentes.
- Gate PM-16-02-03: manifesto e changelog sincronizados.
- QA local com lint, release gate e run-gates.

## Criterios de aceitacao
- Versao 12.16.2 sincronizada no header, SIGE_VERSION e BUILD.json.
- P0 = 0.
- P1 = 0.
- PHP lint sem erros.
- Release gate verde.
- Run gates verde.
- Ficheiros P0 protegidos com hashes iguais ao baseline v12.16.1.
- ZIP integro.

## Plano de rollback
Rollback imediato para v12.16.1 se houver erro fatal, loop, quebra mobile grave, permissao indevida, erro financeiro, erro academico ou quebra de login.

## Definition of Done
A fase so fecha com manifesto final, QA results, ZIP final, hash SHA256 e orientacao de validacao humana em staging.

## Marcadores de proteccao QA
- formula financeira protegida.
- fórmula academica protegida.
- migracao de dados excluida.
- schema excluido.
