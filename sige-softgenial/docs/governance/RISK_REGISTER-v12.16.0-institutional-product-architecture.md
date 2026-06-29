# Registo de Riscos - v12.16.0 - Institutional Product Architecture & Operational UX Hardening

## Estado RC5

O RC5 fecha o hardening documental e contratual da fase. Nao gera ZIP final e nao altera runtime de negocio. A fase fica pronta para validacao humana em staging, com P0/P1 fechados localmente e riscos residuais classificados.

| Codigo | Risco | Prioridade | Estado RC5 | Mitigacao aplicada | Criterio de fecho |
|---|---|---:|---|---|---|
| R-16-01 | Alterar regras financeiras ao melhorar UX | P0 | Fechado localmente | RC1-RC5 nao tocaram `includes/finance-core.php`, `admin/finance/financeiro-pagamentos.php` nem `admin/finance/financeiro-extratos.php`; hashes financeiros iguais a baseline | Gates financeiros verdes, diff sem formula e staging financeiro manual |
| R-16-02 | Alterar regras academicas ao melhorar dashboards/fluxos | P0 | Fechado localmente | RC1-RC5 nao tocaram formulas, pautas, boletins nem `admin/academic/alunos_lista.php`; microcopy explicita que nao altera calculo | Gates academicos verdes e staging academico manual |
| R-16-03 | Menu reorganizado expoe area sem permissao | P1 | Mitigado em RC3 e fechado localmente | Faixa operacional usa `sige_page_guard_allows`; menu principal e allowlist continuam fonte de verdade | Gate de navegacao operacional verde e validacao por perfil em staging |
| R-16-04 | Portaria fica complexa para guarda | P1 | Fechado localmente, pendente staging | RC4 manteve um fluxo unico para portaria: validar entrada, autorizado/bloqueado e motivo | QA humano com perfil Guarda em staging |
| R-16-05 | Alunos monolitico causa regressao | P2 | Residual controlado | Nao houve refactor de `alunos_lista.php`; fase futura deve abrir contrato proprio para performance/segmentacao | Gate alunos verde e plano futuro especifico |
| R-16-06 | Shell concentra risco transversal | P2 | Mitigado em RC3 | Alteracao pequena em `includes/admin-shell.php`, sem substituir rotas, permissao ou menu completo | Lint e corredor completo verdes |
| R-16-07 | Dashboards por perfil ficam genericos | P2 | Mitigado em RC1-RC4 | Atalhos, checklists e fluxos guiados por permissao, com sinais operacionais do dashboard | QA manual por perfil |
| R-16-08 | Pesquisa global perde seguranca ao crescer | P1 | Fechado localmente | RC1-RC5 nao alteraram `includes/sige-pesquisa-global.php`; gate dedicado permanece verde | Gate pesquisa global verde |
| R-16-09 | Mobile degrada por novos componentes | P2 | Residual controlado | Componentes usam tokens e layout compacto; browser real nao foi executado neste ambiente | QA mobile em staging antes de ZIP final |
| R-16-10 | Documentacao diverge da implementacao | P3 | Fechado em RC5 | Sequencia RC, matriz, rollback, riscos e docs RC5 foram normalizados e protegidos por gate | `check-v12-16-0-rc-readiness.php` verde |
| R-16-11 | RC final confundido com ZIP final | P1 | Fechado em RC5 | Documentos declaram explicitamente que RC5 nao gera ZIP e exige validacao humana antes de Fase 6 | Gate RC readiness verifica marcadores de nao ZIP final |

## Riscos residuais declarados

| Codigo | Residual | Impacto | Accao obrigatoria antes do ZIP final |
|---|---|---|---|
| RR-16-01 | Validacao visual autenticada nao executada neste ambiente | P2 | Executar staging por perfil e registar evidencia |
| RR-16-02 | Mobile real nao executado neste ambiente | P2 | Testar dashboard, shell, portaria e menu em telemovel/tablet |
| RR-16-03 | Base de dados real da escola nao executada neste ambiente | P2 | Validar sinais operacionais com dados reais ou copia de staging |
| RR-16-04 | Aceitacao humana da escola ainda pendente | P1 | Direccao/operacao deve validar se as novas orientacoes reduzem friccao |

## Actualizacao RC6 - incidente de staging Professor

| Codigo | Risco | Prioridade | Estado RC6 | Mitigacao aplicada | Criterio de fecho |
|---|---|---:|---|---|---|
| R-16-12 | Redirect de Professor perde `view=` e cria loop de carregamento | P1 | Mitigado localmente em RC6 | `dashboard-view.php` usa `wp_json_encode()` em redirects JavaScript e gates bloqueiam `#038;view` | Professor abre Minhas Turmas em staging sem loop |
