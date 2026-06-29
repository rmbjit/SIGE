# DEPLOY v12.19.4 - Painel Principal simplificado

Âmbito: só apresentação. Pressuposto: File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/admin/system/dashboard-view.php`
- `wp-content/plugins/sige-softgenial/includes/profile-dashboard-intelligence.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

(O ficheiro `tools/.design-tokens-baseline.json` é só para o gate de
desenvolvimento; não é preciso no servidor.)

## Ordem

1. Backup dos 4 ficheiros actuais.
2. Substituir os 4 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- O painel tem cache por transient (2 min). Aguardar 2 min ou recarregar.
- Purgar cache de página/objeto se existir. Ctrl+F5 no browser.

## Validar

- Em Plugins, confirmar **Versão 12.19.4**.
- Abrir o Painel Principal e validar pelo LIVE-TEST.

## Rollback

- Repor os 4 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
