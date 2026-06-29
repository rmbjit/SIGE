# DEPLOY v12.19.7 - Comunicação: gating estrito (corrige R3)

Âmbito: navegação (gating de Comunicação). Seguimento do v12.19.6.
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

- Em Plugins, confirmar **Versão 12.19.7**.
- Grupo COMUNICAÇÃO aparece por `comunicacao.*` (LIVE-TEST v12.19.6 continua válido).
- Director/secretaria_geral continuam a ver "Circulares" (têm a permissão).

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora.
