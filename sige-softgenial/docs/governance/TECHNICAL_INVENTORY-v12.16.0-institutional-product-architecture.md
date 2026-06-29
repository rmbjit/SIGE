# Inventário Técnico - v12.16.0 - Institutional Product Architecture & Operational UX Hardening

## Baseline congelada

- Versão de partida: `12.15.25`.
- ZIP de origem: `sige-softgenial-v12_15_25-fecho-4-gates.zip`.
- SHA-256 da baseline: `e9354b459a5bfef5ab126f76fa92e149aec9e8172070111ba73e7ab947305f27`.
- Corredor oficial de baseline: `121/121` gates verdes.
- PHP lint de baseline: `474/474` ficheiros PHP sem erro de sintaxe.
- Estado: baseline tecnicamente íntegra e apta para planeamento controlado.

## Métricas da superfície técnica

| Métrica | Valor |
|---|---:|
| Total de ficheiros | 2855 |
| PHP total | 474 |
| Admin views PHP | 71 |
| Includes PHP | 155 |
| Tools PHP | 232 |
| CSS em assets | 16 |
| JS em assets | 15 |
| Views na allowlist | 60 |
| Rotas no map da shell | 60 |
| Views na matriz de permissões | 60 |

## Maiores ficheiros PHP

| Ficheiro | Linhas |
|---|---:|
| `admin/academic/alunos_lista.php` | 9264 |
| `includes/security-kernel-rules.php` | 8906 |
| `includes/finance-core.php` | 4243 |
| `admin/finance/financeiro-pagamentos.php` | 3875 |
| `admin/finance/financeiro-extratos.php` | 3761 |
| `admin/hr/equipe-view.php` | 3740 |
| `includes/aluno-fetch-ajax.php` | 3242 |
| `includes/db-handler.php` | 3038 |
| `includes/class-sige-migration.php` | 2819 |
| `includes/admin-shell.php` | 2739 |
| `admin/academic/notas-view.php` | 2546 |
| `admin/academic/turmas-view.php` | 2377 |
| `admin/academic/pautas-view.php` | 2375 |
| `includes/academic-logic.php` | 2309 |
| `includes/portal-logic.php` | 2300 |

## Ficheiros críticos com hash de referência

| Ficheiro | Linhas | SHA-256 baseline |
|---|---:|---|
| `includes/finance-core.php` | 4243 | `bcb51dcfaf93f8c45f378b825d734df2a3dea8dfe23e26b5364fc12fb5ad7266` |
| `admin/finance/financeiro-pagamentos.php` | 3875 | `3afab797c29cd261d25c53410ac482f267d043232b98f31c02c323caebd9a823` |
| `admin/finance/financeiro-extratos.php` | 3761 | `e251917d82564372c5a779152ef6a9640754a394e95298396ac48bf53eb293d1` |
| `includes/admin-shell.php` | 2739 | `8b818279c9a11cbb9010ee6cbbe0498222420a3b7c85fe8d6ab919f74319270f` |
| `admin/academic/alunos_lista.php` | 9264 | `4f491428dab48d4a12157ad645c84ecd8c0e1db93cdb5f81eab6793746137f47` |
| `includes/permissions-layer.php` | 1944 | `3661ef0d427790f413b2148092bd2f340c4c064a9913d8eb1264301cd5728c47` |
| `includes/security-kernel-rules.php` | 8906 | `774436262ffab70665dcb61cca1f3c14494e7adde846d6b269665cae028d6869` |

## Mapa funcional por domínio

| Área | Ficheiros principais | Função operacional | Risco | Pode mexer nesta fase? | Observações |
|---|---|---|---|---|---|
| Financeiro core | `includes/finance-core.php`, `admin/finance/financeiro-pagamentos.php`, `admin/finance/financeiro-extratos.php` | Valores, dívida, recibos, pagamentos, saldos e data efectiva | P0 protegido | Não em RC1 | Só leitura, microcopy ou UX superficial com gate de integridade. Nenhuma fórmula pode mudar. |
| Alunos | `admin/academic/alunos_lista.php`, `includes/aluno-fetch-ajax.php` | Cadastro, matrícula, ficha 360, pesquisa e base operacional | P2 alto | Sim, mas só depois de contrato dedicado | Monólito com 9264 linhas. Refactor cego proibido. |
| Shell e navegação | `includes/admin-shell.php`, `includes/ui-kit.php` | Topbar, sidebar, routing, assets e pesquisa global | P1/P2 | Sim, em bloco pequeno | Intervenção deve ser reversível e protegida por permissões. |
| Permissões | `includes/permissions-layer.php`, `includes/security-kernel-rules.php` | Capacidades, perfis, autorização e segurança | P1 protegido | Não em RC1 | Não alargar acesso. Mudanças só com gate e cenários negativos. |
| Académico | `admin/academic/notas-view.php`, `admin/academic/pautas-view.php`, `admin/academic/aprovar_notas-view.php` | Notas, pautas, boletins, aprovação e encerramento | P0 protegido | Não em RC1 | Fórmulas e regras académicas permanecem intactas. |
| Portaria | `admin/system/portaria-view.php`, `includes/portaria-*` | Validação de entrada, bloqueios, QR/câmara e histórico | P1 | Sim, apenas UX/clareza | Não introduzir menus administrativos no perfil guarda. |
| Pesquisa global | `includes/sige-pesquisa-global.php`, `assets/views/pesquisa-global.*`, `includes/admin-shell.php` | Pesquisa por aluno, turma, recibo, planos e despesas | P1 | Sim, só extensão de leitura | Já tem gate dedicado e queries escopadas por escola/permissão. |
| Assets/UI | `assets/*.css`, `assets/views/*.css`, `assets/views/*.js` | Design system, responsividade, modais, mobile e shell | P2 | Sim, se escopado por view | Não introduzir inline agressivo, `onclick`, `alert`, `confirm`, `prompt` ou drift de tokens. |
| Configuração e saúde | `admin/system/config-center-view.php`, `admin/system/core-status-view.php` | Administração técnica e saúde do sistema | P2 | Só diagnóstico | Deve ficar fora da rotina operacional comum. |

## Diagnóstico de produto

O sistema tem cobertura funcional forte, mas a experiência ainda está organizada principalmente por módulos e permissões. A v12.16.0 deve evoluir para uma arquitectura mais próxima da rotina real da escola, mantendo os contratos técnicos existentes.

Constatações principais:

1. A navegação principal existe, mas ainda mistura categorias técnicas, operacionais e institucionais.
2. Há dashboards e redireccionamentos por papel, mas ainda falta uma camada formal de tarefas do dia por perfil.
3. Financeiro e académico estão protegidos, mas qualquer melhoria visual ou de fluxo nessas áreas precisa de blindagem antes/depois.
4. A página de alunos continua a ser o maior ponto de risco por tamanho e centralidade operacional.
5. A pesquisa global já é uma boa base para redução de cliques, mas precisa continuar só leitura e escopada por permissão.
6. A portaria deve manter experiência mínima e directa, sem ruído administrativo.

## Riscos classificados

| Código | Descrição | Prioridade | Estado | Tratamento |
|---|---|---:|---|---|
| R-16-01 | Alteração acidental de fórmulas financeiras durante melhoria de UX | P0 | Aberto protegido | Hashes, gates financeiros, não tocar em `finance-core.php` sem autorização e teste dedicado. |
| R-16-02 | Regressão académica por mudança visual em notas/pautas | P0 | Aberto protegido | Não tocar em cálculo, aprovação, pauta ou boletim em RC1. |
| R-16-03 | Regressão de permissão por reorganização de menu | P1 | Aberto controlado | Menu só pode ocultar/organizar entradas já permitidas. Nunca conceder acesso novo. |
| R-16-04 | Alunos monolítico dificulta performance e QA | P2 | Aberto | Planeamento de divisão gradual, primeiro por funções puras e assets escopados. |
| R-16-05 | Shell concentra UI, routing e assets | P2 | Aberto | Intervenções pequenas e verificáveis. Nada de refactor global. |
| R-16-06 | A melhoria visual pode aumentar peso ou quebrar mobile | P2 | Aberto | Assets por view, gates de tokens, JS sem handlers inline. |
| R-16-07 | Auditoria ainda pode ser técnica demais para direcção | P2 | Aberto | Planeamento de camada humana sem alterar ledger ou writes críticos. |
| R-16-08 | Falta de artefactos formais da v12.16.0 | P3 | Tratado nesta fase | Criados Charter, DoD, QA, rollback, matriz e gate de governança. |

## Conclusão técnica

A primeira implementação segura da v12.16.0 não deve alterar regras financeiras, fórmulas académicas, schema, permissões profundas ou fluxos de escrita. O caminho correcto é iniciar por contrato de arquitectura operacional, documentação formal, gate de governança e, em seguida, uma intervenção pequena na camada de orientação do utilizador, mantendo comportamento externo intacto ou reversível.
