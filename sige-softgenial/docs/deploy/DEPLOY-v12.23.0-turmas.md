# DEPLOY v12.23.0 - Turmas: modais acima da sidebar, ESC, herói compacto

Âmbito: ecrã Turmas (view=turmas). Só apresentação/UI. File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/admin/academic/turmas-view.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5.

## Validar

- Em Plugins, confirmar Versão 12.23.0.
- Herói: faixa única compacta, sem a ilustração de escola; os 2 botões numa linha
  no portátil (sem 2+1).
- Abrir Nova Turma / Editar / Docentes / Horário / Alunos: cada modal deve
  aparecer por cima da barra lateral (sidebar esbatida atrás).
- Premir ESC para fechar: o modal fecha E a página volta a fazer scroll
  normalmente (antes ficava bloqueada).
- Fechar por clique fora e pelo X: idem.
- Pesquisar turma e mudar o filtro de turno (continuam a filtrar).
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.23.0-turmas.md

## Nota (achado a confirmar, NÃO alterado nesta versão)

- Os editores de Horário e de Docentes podem não responder por causa da CSP
  (handlers inline injectados). É pré-existente; será tratado numa correcção
  dedicada se confirmado.

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
