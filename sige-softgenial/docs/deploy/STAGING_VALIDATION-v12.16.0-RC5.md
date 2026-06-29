# Checklist de Validacao em Staging - v12.16.0 RC5

## Objectivo

Validar a Release Candidate em ambiente WordPress autenticado antes de qualquer ZIP final.

## Regras

1. Usar uma copia segura de staging.
2. Nao validar directamente em producao.
3. Usar perfis reais ou equivalentes.
4. Registar resultado por perfil.
5. Se houver P0/P1, parar e voltar para fase de correcao.

## Perfis obrigatorios

| Perfil | Validacao minima | Resultado |
|---|---|---|
| Direccao | Dashboard, Comece aqui, indicadores, Gestao, relatorios | Pendente |
| Tesouraria | Pagamentos, devedores, Checklist Operacional, Fluxos Guiados | Pendente |
| Secretaria | Alunos, matriculas, documentos, ficha 360 | Pendente |
| Professor | Minhas turmas, lancar notas, orientacao academica | Pendente |
| Direccao Pedagogica | Aprovar notas, pautas, boletins, pendencias | Pendente |
| Guarda | Portaria simples, autorizado/bloqueado/motivo/nova leitura | Pendente |
| Encarregado/Aluno | Portal, situacao financeira, boletim, documentos | Pendente |
| Administrador tecnico | Saude do sistema, permissoes, logs, configuracoes | Pendente |

## Fluxos criticos de smoke humano

| Fluxo | Deve confirmar |
|---|---|
| Registar pagamento | O sistema orienta sem alterar saldo ou regra automaticamente |
| Lancar mensalidades | O sistema continua a exigir pre-visualizacao e evita duplicacao conforme regra existente |
| Lancar notas | A orientacao nao muda formula nem pesos |
| Aprovar pauta | A orientacao nao aprova nada sem acao humana |
| Portaria | O guarda nao recebe menus administrativos desnecessarios |
| Pesquisa global | Resultados continuam filtrados por permissao |
| Mobile | Componentes cabem no ecra sem bloquear tarefas |

## Criterio de aceite

A validacao humana deve concluir que a v12.16.0 melhora clareza operacional sem regressao funcional, financeira, academica, de portaria, permissao ou mobile.

## Adenda RC6 - validacao obrigatoria Professor

Este item foi adicionado apos o staging detectar loop no perfil Professor.

| Validacao | Resultado esperado | Estado |
|---|---|---|
| Abrir Professor sem dashboard executivo | Deve carregar Minhas Turmas sem `#038;view` | Pendente |
| URL final | Deve ser `admin.php?page=sige-app&view=minhas_turmas` | Pendente |
| Loader | Deve desaparecer apos a view carregar | Pendente |
| Professor sem permissao de Minhas Turmas | Deve mostrar acesso restrito, nao loop | Pendente |
| Menu lateral | Deve continuar filtrado pela matriz de permissoes | Pendente |
