# DEPLOY v12.19.3 - Painel Principal: correcções de apresentação

Âmbito: só apresentação. Um ficheiro de view + 3 fontes de versão.
Pressuposto: File Manager sem SSH (CloudPanel/Hostinger; cPanel/MochaHost).

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/admin/system/dashboard-view.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais (descarregar antes de substituir).
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir.
- O painel tem cache interna por transient (2 min); muda sozinha ao fim de 2
  minutos, ou force novo login/recarregar. Ctrl+F5 no browser.

## Validar a versão no ecrã

- Em Plugins, confirmar **Versão 12.19.3**.
- Abrir o Painel Principal e validar pelo LIVE-TEST.

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
