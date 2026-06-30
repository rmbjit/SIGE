# DEPLOY v12.22.0 - Alunos: modais acima da sidebar, herói compacto, form sem inline

Âmbito: ecrã Alunos e Matrículas (view=alunos_lista). Só apresentação.
File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/admin/academic/alunos_lista.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5.

## Validar

- Em Plugins, confirmar Versão 12.22.0.
- Abrir Alunos: o herói deve aparecer como faixa única compacta, sem a ilustração
  de escola.
- Clicar "Registar Aluno": o modal deve abrir centrado e completo, com a barra
  lateral esbatida por trás (já não tapada). Percorrer os separadores e confirmar
  o scroll.
- Abrir a Ficha 360º de um aluno e a "Importar Lista": ambos por cima da sidebar.
- Pesquisar na caixa de pesquisa (filtra em tempo real) e mudar os filtros de
  turma/estado (recarrega a lista).
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.22.0-alunos.md

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
