# DEPLOY v12.19.6 - Navegação: grupo COMUNICAÇÃO

Âmbito: navegação da barra lateral (apresentação + gating de comunicação).
File Manager sem SSH.

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

- Em Plugins, confirmar **Versão 12.19.6**.
- Validar pelo LIVE-TEST (matriz por perfil): Comunicações aparecem por
  `comunicacao.*`, já não por permissão financeira.

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica
  financeira/académica.
