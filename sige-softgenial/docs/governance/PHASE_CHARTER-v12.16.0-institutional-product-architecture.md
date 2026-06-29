# Phase Charter - v12.16.0 - Institutional Product Architecture & Operational UX Hardening

## Nome da fase

v12.16.0 - Institutional Product Architecture & Operational UX Hardening.

## Objectivo

Reorganizar o produto para ficar mais alinhado com a rotina real da escola, reduzir complexidade operacional, melhorar clareza de navegação e preparar dashboards/fluxos por perfil, sem quebrar financeiro, académico, portaria, permissões, pesquisa global, tenant isolation ou mobile.

## Princípio central

A v12.16.0 não é uma fase cosmética. É uma fase de arquitectura de produto e UX operacional. Qualquer alteração deve reduzir risco, reduzir cliques, melhorar clareza, aumentar confiança institucional ou tornar a rotina da escola mais simples.

## Escopo incluído

1. Inventário técnico e mapa de risco da versão `12.15.25`.
2. Formalização da fase com DoD, matriz, QA e rollback.
3. Camada de orientação operacional por perfil, inicialmente sem alterar regras de negócio.
4. Preparação gradual de arquitectura de navegação orientada a rotina institucional.
5. Melhorias conservadoras de microcopy, estados vazios e orientação, quando não afectarem fluxo de negócio.
6. Contratos de segurança para impedir regressão em financeiro, académico, portaria, permissões e pesquisa global.
7. Gates dedicados para garantir que os artefactos mínimos da fase existem e continuam rastreáveis.

## Escopo excluído

1. Alteração de fórmulas financeiras.
2. Alteração de regras de cálculo académico.
3. Migração de base de dados ou alteração de schema.
4. Alteração de permissões com impacto operacional real.
5. Remoção de funcionalidades usadas.
6. Refactor global do `admin-shell.php`.
7. Refactor cego de `admin/academic/alunos_lista.php`.
8. Redesign visual agressivo global.
9. Alteração de pagamentos, recibos, dívida, multas, descontos, transporte ou fecho de caixa sem fase dedicada.
10. Alteração de pautas, boletins, notas ou encerramento académico sem fase dedicada.

## Ordem de intervenção

| RC | Bloco | Objectivo | Risco | Gate mínimo |
|---|---|---|---|---|
| RC0 | Governança da fase | Fechar documentos, inventário, DoD, QA e rollback | P3 | Gate de governança v12.16.0 |
| RC1 | Orientação operacional | Introduzir camada segura de arquitectura por rotina/perfil sem alterar permissões | P2 | Lint, gates, smoke dedicado |
| RC2 | Navegação institucional | Melhorar hierarquia da sidebar/topbar por grupos existentes | P1/P2 | Gate de menu sem alargar acesso |
| RC3 | Dashboards por perfil | Melhorar foco de cada perfil no que deve fazer agora | P1/P2 | Gate por perfil e QA manual |
| RC4 | Fluxos guiados sem regra nova | Orientar tarefas críticas com pré-visualização, checklist e mensagens | P1 | Gates específicos por fluxo |
| RC5 | QA final e ZIP | Consolidar versão, changelog, BUILD, testes e pacote final | P0/P1 zero | Corredor completo e lint total |

## Critérios de aceitação

1. Versão final sincronizada em `sige-softgenial.php`, `BUILD.json` e `CHANGELOG.md` apenas no fecho RC.
2. Nenhum P0/P1 aberto.
3. Financeiro protegido por gate de integridade sempre que houver alteração próxima.
4. Académico protegido por não alteração de fórmulas e por gates existentes.
5. Permissões não alargadas.
6. Portaria preservada como fluxo mínimo, rápido e directo.
7. Pesquisa global preservada como só leitura, escopada por escola e permissão.
8. Dashboards e menus devem responder melhor a rotina real de cada perfil.
9. QA incremental documentado por bloco.
10. ZIP final só gerado depois dos gates e lint.

## Riscos principais e mitigação

| Risco | Prioridade | Mitigação |
|---|---:|---|
| Financeiro regressivo | P0 | Não alterar fórmulas. Hashes e gates financeiros. |
| Académico regressivo | P0 | Não alterar fórmulas ou aprovação sem fase própria. |
| Permissão alargada acidentalmente | P1 | Usar matriz actual como fonte de verdade e gates negativos. |
| Navegação mais bonita, mas menos útil | P2 | Validar por tarefas reais, não por estética. |
| Performance pior em alunos/dashboard | P2 | Não carregar tudo de uma vez. Assets e queries por view. |
| Refactor grande sem teste | P1 | Proibido. Intervenções pequenas e reversíveis. |

## Plano de rollback

1. Manter ZIP de baseline `12.15.25` intacto.
2. Cada RC deve listar ficheiros alterados.
3. Alterações visuais ou de orientação devem ser reversíveis por remoção de asset/helper ou flag.
4. Alterações em shell devem ser pequenas e isoladas.
5. Nenhum schema novo nesta fase sem decisão explícita.
6. Se gate financeiro, académico, permissão ou portaria falhar, reverter o bloco inteiro.

## Decisão técnica inicial

A primeira implementação autorizada tecnicamente é RC0: formalizar a fase e criar gate de governança. A primeira alteração funcional futura deve ser uma camada de orientação operacional conservadora, sem alargar permissões, sem mudar rotas e sem tocar em regras críticas.
