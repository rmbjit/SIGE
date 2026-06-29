# Auditoria UX - linha de base medida (v12.11.9.93, 12 Jun 2026)

## Método
Inconsistência não se discute, mede-se. O instrumento tools/ux-audit.php
varre as 53 views de admin/ e quantifica, por ficheiro: estilos inline,
alert()/confirm() nativos do browser, botões sem classe, tabelas sem
estado vazio, fugas AO90/BR nos rótulos e terminologia divergente. O
score heurístico pesa cada classe de problema pelo dano que causa ao
utilizador (diálogos nativos e terminologia trocada pesam mais; inline
de layout pesa pouco). Correr o auditor antes e depois de cada sprint
prova o progresso em números, não em opinião.

## Totais do sistema (linha de base)

| Métrica | Total | Leitura |
|---|---:|---|
| Score de inconsistência | 951,4 | A dívida UX inteira, em um número |
| Estilos inline | 1.804 | Componentes repetidos à mão em vez de classes |
| alert() nativos | 38 | Feedback com a cara do browser, não do SIGE |
| confirm() nativos | 18 | Confirmações destrutivas sem nome do objecto |
| Botões sem classe | 35 | Hierarquia visual indefinida |
| Fugas AO90/BR | 126 | "usuário/ativo/cadastr" em ecrãs PT-MZ |

## Decisões de cânone (a régua de todos os sprints)
1. Terminologia única: **Guardar** (nunca Salvar/Gravar), **Remover**
   (nunca Excluir/Deletar/Apagar/Eliminar), **Pesquisar** (nunca
   Buscar/Procurar), **Editar**, **Cancelar**. O sistema já pende para
   o cânone (Salvar=0 no sistema todo); falta fechar os desvios.
2. Diálogos nativos proibidos: alert() vira sigeUi.toast, confirm()
   vira sigeUi.confirm com o NOME do objecto em causa.
3. Todo o ecrã com tabela tem estado vazio com próxima acção (.sg-empty).
4. Toda a acção tem feedback visível (toast ou banner), com mensagem
   humana: o que aconteceu + o que fazer a seguir.
5. Um só botão primário por ecrã; o resto é secundário/ghost.
6. Pré-AO90 em todos os rótulos (activo, utilizador, registar).
7. Datas dd/mm/aaaa; valores 1.234,56 MT.
8. Inline de componente (botões, banners, badges, cartões) é dívida;
   inline de layout pontual tolera-se até à consolidação CSS.

## Ranking completo (ordena os sprints)

| Score | View | Linhas | Inline | alert | confirm | btn s/cl | AO90 |
|---:|---|---:|---:|---:|---:|---:|---:|
| 83.2 | admin/finance/financeiro-pagamentos.php | 3740 | 292 | 1 | 0 | 4 | 14 |
| 77.5 | admin/whatsapp_central-view.php | 1508 | 75 | 10 | 6 | 3 | 0 |
| 59.2 | admin/finance/financeiro-config.php | 1836 | 142 | 0 | 0 | 0 | 14 |
| 55.7 | admin/system/permissions-ui.php | 1295 | 7 | 0 | 1 | 0 | 17 |
| 44.8 | admin/system/dashboard-view.php | 883 | 28 | 0 | 0 | 0 | 14 |
| 40.7 | admin/finance/financeiro-gerador.php | 1473 | 7 | 0 | 0 | 0 | 12 |
| 33.6 | admin/academic/acta-view.php | 1449 | 36 | 0 | 1 | 1 | 4 |
| 31.8 | admin/finance/financeiro-devedores-view.php | 1836 | 38 | 3 | 0 | 1 | 4 |
| 30.4 | admin/finance/mpesa-view.php | 364 | 104 | 5 | 0 | 0 | 0 |
| 27.9 | admin/jardim/jardim_relatorio-view.php | 1244 | 139 | 0 | 2 | 3 | 0 |
| 26.9 | admin/academic/estatisticas-demograficas-view.php | 961 | 39 | 2 | 0 | 0 | 0 |
| 26.1 | admin/academic/alunos_lista.php | 9229 | 81 | 0 | 0 | 0 | 6 |
| 25.7 | admin/academic/aluno-portal-view.php | 1367 | 17 | 0 | 0 | 0 | 6 |
| 21.6 | admin/finance/financeiro-planos-view.php | 1075 | 96 | 0 | 0 | 5 | 0 |
| 21.5 | admin/academic/notas-view.php | 2546 | 25 | 0 | 0 | 0 | 4 |
| 20.8 | admin/jardim/jardim_diario-view.php | 1099 | 58 | 0 | 0 | 3 | 3 |
| 19.6 | admin/academic/boletim-view.php | 1342 | 26 | 2 | 0 | 0 | 2 |
| 18.7 | admin/academic/pauta-final-view.php | 579 | 17 | 2 | 0 | 0 | 2 |
| 17.8 | admin/finance/financeiro-lancamentos-view.php | 1511 | 8 | 2 | 0 | 0 | 3 |
| 17.4 | admin/academic/encerramento-view.php | 1341 | 34 | 0 | 3 | 1 | 0 |
| 17.1 | admin/academic/alocacao-view.php | 184 | 21 | 1 | 1 | 2 | 0 |
| 17.1 | admin/logistics/transporte-view.php | 547 | 11 | 0 | 1 | 0 | 3 |
| 16.3 | admin/finance/financeiro-dashboard.php | 862 | 3 | 0 | 0 | 2 | 1 |
| 16.3 | admin/whatsapp_diag-view.php | 343 | 73 | 0 | 0 | 0 | 0 |
| 15.2 | admin/academic/dec-view.php | 605 | 12 | 2 | 0 | 0 | 1 |
| 14.5 | admin/jardim/jardim_boletim-view.php | 870 | 15 | 1 | 0 | 0 | 3 |
| 14.5 | admin/whatsapp_circulares-view.php | 151 | 25 | 3 | 0 | 0 | 0 |
| 12.8 | admin/academic/turmas-view.php | 2365 | 98 | 0 | 0 | 0 | 0 |
| 12.8 | admin/finance/financeiro-extratos.php | 3761 | 8 | 3 | 0 | 0 | 0 |
| 12.4 | admin/finance/pagamentos-turma-view.php | 393 | 4 | 0 | 0 | 0 | 4 |
| 12.1 | admin/finance/financeiro-inscricoes-view.php | 457 | 1 | 0 | 0 | 0 | 4 |
| 11.7 | admin/academic/disciplinas-view.php | 2138 | 17 | 0 | 1 | 3 | 0 |
| 10.0 | admin/academic/pautas-view.php | 2375 | 10 | 0 | 0 | 0 | 3 |
| 8.9 | admin/jardim/jardim_saude-view.php | 819 | 49 | 0 | 0 | 2 | 0 |
| 8.6 | admin/academic/presencas-view.php | 200 | 26 | 0 | 0 | 0 | 0 |
| 8.2 | admin/academic/aprovar_notas-view.php | 618 | 12 | 0 | 1 | 0 | 0 |
| 6.9 | admin/hr/equipe-view.php | 3725 | 19 | 0 | 0 | 1 | 1 |
| 6.8 | admin/academic/vincular-view.php | 113 | 8 | 1 | 0 | 1 | 0 |
| 6.4 | admin/finance/financeiro-centros-view.php | 567 | 4 | 0 | 0 | 0 | 1 |
| 6.3 | admin/academic/abertura-view.php | 1241 | 23 | 0 | 1 | 0 | 0 |
| 2.7 | admin/finance/financeiro-relatorio-mensal-view.php | 837 | 7 | 0 | 0 | 1 | 0 |
| 2.5 | admin/system/config-center-view.php | 537 | 5 | 0 | 0 | 1 | 0 |
| 2.2 | admin/academic/auditoria_notas-view.php | 970 | 22 | 0 | 0 | 0 | 0 |
| 2.2 | admin/system/portaria-view.php | 1333 | 2 | 0 | 0 | 1 | 0 |
| 1.7 | admin/system/curriculum-engine-view.php | 450 | 17 | 0 | 0 | 0 | 0 |
| 1.6 | admin/academic/matriz-view.php | 1124 | 16 | 0 | 0 | 0 | 0 |
| 1.3 | admin/academic/minhas_turmas-view.php | 690 | 13 | 0 | 0 | 0 | 0 |
| 0.5 | admin/jardim/jardim_presencas-view.php | 579 | 5 | 0 | 0 | 0 | 0 |
| 0.4 | admin/system/config-view.php | 18 | 4 | 0 | 0 | 0 | 0 |
| 0.2 | admin/finance/financeiro-despesas-view.php | 545 | 2 | 0 | 0 | 0 | 0 |
| 0.1 | admin/academic/aluno_contas-view.php | 27 | 1 | 0 | 0 | 0 | 0 |
| 0.1 | admin/finance/financeiro-auditoria-view.php | 516 | 1 | 0 | 0 | 0 | 0 |
| 0.1 | admin/system/core-status-view.php | 661 | 1 | 0 | 0 | 0 | 0 |
| **951.4** | **TOTAL (53 views)** | | **1804** | **38** | **18** | **35** | **126** |

Gerado por: php tools/ux-audit.php (repetir após cada sprint UX).


## Afinação v2.1 do instrumento (12 Jun 2026, Sprint UX-2)
O recon do financeiro-pagamentos revelou que as 14 ocorrências "AO90"
eram falsos positivos: ativo=1 é coluna de schema financeiro, as
comparações 'activa','ativa' são tolerância deliberada de dados, e o
resto eram identificadores de funções/variáveis. O auditor passou a
contar AO90 apenas em contexto visível (linhas SQL/$wpdb/IN(...)
excluídas; identificadores ignorados). Recalibração: a linha de base do
sistema corrige de 951,4 (v1) para 678,4 (v2.1, somando de volta os
créditos já corrigidos). Correcção de instrumento não é progresso.

## Ranking v2.1 (estado actual, já com os módulos corrigidos)

| Score | View | Linhas | Inline | alert | confirm | btn s/cl | AO90 |
|---:|---|---:|---:|---:|---:|---:|---:|
| 44.2 | admin/finance/financeiro-config.php | 1836 | 142 | 0 | 0 | 0 | 9 |
| 30.4 | admin/finance/mpesa-view.php | 364 | 104 | 5 | 0 | 0 | 0 |
| 28.9 | admin/finance/financeiro-pagamentos.php | 3745 | 289 | 0 | 0 | 0 | 0 |
| 27.9 | admin/jardim/jardim_relatorio-view.php | 1244 | 139 | 0 | 2 | 3 | 0 |
| 26.9 | admin/academic/estatisticas-demograficas-view.php | 961 | 39 | 2 | 0 | 0 | 0 |
| 25.7 | admin/academic/aluno-portal-view.php | 1367 | 17 | 0 | 0 | 0 | 6 |
| 21.6 | admin/academic/acta-view.php | 1449 | 36 | 0 | 1 | 1 | 0 |
| 21.6 | admin/finance/financeiro-planos-view.php | 1075 | 96 | 0 | 0 | 5 | 0 |
| 19.8 | admin/finance/financeiro-devedores-view.php | 1836 | 38 | 3 | 0 | 1 | 0 |
| 17.8 | admin/finance/financeiro-lancamentos-view.php | 1511 | 8 | 2 | 0 | 0 | 3 |
| 17.8 | admin/jardim/jardim_diario-view.php | 1099 | 58 | 0 | 0 | 3 | 2 |
| 17.4 | admin/academic/encerramento-view.php | 1341 | 34 | 0 | 3 | 1 | 0 |
| 17.1 | admin/academic/alocacao-view.php | 184 | 21 | 1 | 1 | 2 | 0 |
| 17.1 | admin/academic/alunos_lista.php | 9229 | 81 | 0 | 0 | 0 | 3 |
| 16.3 | admin/whatsapp_diag-view.php | 343 | 73 | 0 | 0 | 0 | 0 |
| 14.8 | admin/system/dashboard-view.php | 883 | 28 | 0 | 0 | 0 | 4 |
| 14.5 | admin/whatsapp_circulares-view.php | 151 | 25 | 3 | 0 | 0 | 0 |
| 13.7 | admin/system/permissions-ui.php | 1295 | 7 | 0 | 1 | 0 | 3 |
| 13.6 | admin/academic/boletim-view.php | 1342 | 26 | 2 | 0 | 0 | 0 |
| 13.3 | admin/finance/financeiro-dashboard.php | 862 | 3 | 0 | 0 | 2 | 0 |
| 12.8 | admin/academic/turmas-view.php | 2365 | 98 | 0 | 0 | 0 | 0 |
| 12.8 | admin/finance/financeiro-extratos.php | 3761 | 8 | 3 | 0 | 0 | 0 |
| 12.7 | admin/academic/pauta-final-view.php | 579 | 17 | 2 | 0 | 0 | 0 |
| 12.2 | admin/academic/dec-view.php | 605 | 12 | 2 | 0 | 0 | 0 |
| 11.7 | admin/academic/disciplinas-view.php | 2138 | 17 | 0 | 1 | 3 | 0 |
| 11.5 | admin/jardim/jardim_boletim-view.php | 870 | 15 | 1 | 0 | 0 | 2 |
| 11.1 | admin/logistics/transporte-view.php | 547 | 11 | 0 | 1 | 0 | 1 |
| 10.7 | admin/finance/financeiro-gerador.php | 1473 | 7 | 0 | 0 | 0 | 2 |
| 9.5 | admin/academic/notas-view.php | 2546 | 25 | 0 | 0 | 0 | 0 |
| 8.9 | admin/jardim/jardim_saude-view.php | 819 | 49 | 0 | 0 | 2 | 0 |
| 8.6 | admin/academic/presencas-view.php | 200 | 26 | 0 | 0 | 0 | 0 |
| 8.2 | admin/academic/aprovar_notas-view.php | 618 | 12 | 0 | 1 | 0 | 0 |
| 7.3 | admin/whatsapp_central-view.php | 1508 | 73 | 0 | 0 | 0 | 0 |
| 6.8 | admin/academic/vincular-view.php | 113 | 8 | 1 | 0 | 1 | 0 |
| 6.3 | admin/academic/abertura-view.php | 1241 | 23 | 0 | 1 | 0 | 0 |
| 3.9 | admin/hr/equipe-view.php | 3725 | 19 | 0 | 0 | 1 | 0 |
| 3.4 | admin/finance/financeiro-centros-view.php | 567 | 4 | 0 | 0 | 0 | 0 |
| 2.7 | admin/finance/financeiro-relatorio-mensal-view.php | 837 | 7 | 0 | 0 | 1 | 0 |
| 2.5 | admin/system/config-center-view.php | 537 | 5 | 0 | 0 | 1 | 0 |
| 2.2 | admin/academic/auditoria_notas-view.php | 970 | 22 | 0 | 0 | 0 | 0 |
| 2.2 | admin/system/portaria-view.php | 1333 | 2 | 0 | 0 | 1 | 0 |
| 1.7 | admin/system/curriculum-engine-view.php | 450 | 17 | 0 | 0 | 0 | 0 |
| 1.6 | admin/academic/matriz-view.php | 1124 | 16 | 0 | 0 | 0 | 0 |
| 1.3 | admin/academic/minhas_turmas-view.php | 690 | 13 | 0 | 0 | 0 | 0 |
| 1.0 | admin/academic/pautas-view.php | 2375 | 10 | 0 | 0 | 0 | 0 |
| 0.5 | admin/jardim/jardim_presencas-view.php | 579 | 5 | 0 | 0 | 0 | 0 |
| 0.4 | admin/finance/pagamentos-turma-view.php | 393 | 4 | 0 | 0 | 0 | 0 |
| 0.4 | admin/system/config-view.php | 18 | 4 | 0 | 0 | 0 | 0 |
| 0.2 | admin/finance/financeiro-despesas-view.php | 545 | 2 | 0 | 0 | 0 | 0 |
| 0.1 | admin/academic/aluno_contas-view.php | 27 | 1 | 0 | 0 | 0 | 0 |
| 0.1 | admin/finance/financeiro-auditoria-view.php | 516 | 1 | 0 | 0 | 0 | 0 |
| 0.1 | admin/finance/financeiro-inscricoes-view.php | 457 | 1 | 0 | 0 | 0 | 0 |
| 0.1 | admin/system/core-status-view.php | 661 | 1 | 0 | 0 | 0 | 0 |
| **595.9** | **TOTAL (53 views)** | | **1799** | **27** | **12** | **28** | **35** |

Gerado por: php tools/ux-audit.php (v2.1).
