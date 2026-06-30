# DEPLOY v12.21.0 - Equipa: herói compacto, tokens limpos, form sem inline

Âmbito: ecrã Equipa e Professores (RH). Só apresentação. File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/admin/hr/equipe-view.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5.

## Validar

- Em Plugins, confirmar Versão 12.21.0.
- Abrir Equipa e Professores: o herói deve aparecer como faixa única e compacta
  (sem a ilustração de escola), igual ao Painel Principal.
- Tabela de colaboradores intacta; abrir o modal Novo colaborador e Editar.
- Gravar um colaborador (botão Guardar): deve funcionar e recarregar a lista.
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.21.0-equipa.md

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
