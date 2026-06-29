# DEPLOY v12.19.8 - Navegação mais limpa (M2 + M5)

Âmbito: barra lateral (só apresentação). File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/includes/admin-shell.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5.

## Validar

- Em Plugins, confirmar **Versão 12.19.8**.
- "Aproveitamento" aparece uma só vez na barra lateral (LIVE-TEST).
- O bloco "Comece aqui" deixou de aparecer no topo da barra lateral.

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora.
