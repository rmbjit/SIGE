# DEPLOY v12.19.9 - Tesouraria em 3 grupos + gating por item (M3/M6)

Âmbito: barra lateral (apresentação + gating financeiro). File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/includes/admin-shell.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

(`tools/.design-tokens-baseline.json` muda mas é só para o gate de
desenvolvimento; não é preciso no servidor.)

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5.

## Validar

- Em Plugins, confirmar **Versão 12.19.9**.
- A Tesouraria aparece agora em 3 grupos: TESOURARIA, FINANÇAS · RELATÓRIOS,
  FINANÇAS · CONFIGURAÇÃO.
- Portaria e Transporte aparecem juntos sob OPERAÇÃO ESCOLAR.
- Validar por perfil (LIVE-TEST): o menu só mostra o que a rota permite.

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
