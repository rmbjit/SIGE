# Phase Charter - v12.18.0 Operational UX & Workflow Hardening

## Objectivo
Melhorar a clareza operacional dos fluxos mais usados por perfil, reduzindo erro humano sem alterar lógica de negócio, permissões ou dados.

## Escopo incluído
- Orientação contextual curta por view crítica.
- Microcopy de passos seguros.
- Guardrails textuais antes de acções sensíveis.
- Asset leve e não bloqueante.
- Gates de contrato e regressão.

## Escopo excluído
- Fórmulas financeiras.
- Fórmulas académicas.
- Permissões críticas.
- Schema de base de dados.
- Migração de dados.
- Refactor de módulos críticos.
- Alteração do `admin-shell.php`.
- Alteração do núcleo da página de Alunos.
- Alteração da Portaria.

## Perfis afectados
- Director.
- Financeiro.
- Secretaria.
- Professor.
- Pedagógico.
- Guarda.
- Comunicação/secretaria.
- Administrador técnico, apenas para validação.

## Módulos afectados
- Include novo de orientação operacional.
- Assets novos de UX operacional.
- Bootstrap principal apenas para carregar o include.
- Documentação e gates.

## Riscos principais
- Excesso de informação no topo das páginas.
- Quebra visual mobile.
- Interferência com views que já possuem header próprio.
- Falsa sensação de alteração funcional.

## Mitigação
- Inserção não bloqueante por JavaScript.
- Sem manipulação de formulários.
- Sem alteração de queries.
- Sem novos endpoints.
- Sem gravação em base de dados.
- Fecho opcional por sessão.
- CSS escopado por classe própria.

## Critérios de aceitação
- PHP lint verde.
- Gates verdes.
- Package manifest alinhado.
- Orientação aparece apenas em views catalogadas.
- Dashboard e portal do aluno não recebem faixa operacional.
- Financeiro, académico, permissões, portaria, Alunos e shell permanecem intactos.
- Staging sem erro fatal, tela branca, loop ou quebra mobile.

## Definition of Done
- v12.18.0 sincronizada no header, constante e BUILD.json.
- Include carregado de forma segura.
- Assets presentes.
- Gates v12.18.0 verdes.
- ZIP final íntegro.
- Rollback documentado para v12.17.1.
