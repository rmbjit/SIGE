# DEPLOY v12.19.11 - Financeiro reagrupado por domínio (4 grupos)

Âmbito: barra lateral (só apresentação/IA). File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/includes/admin-shell.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Validar

- Em Plugins, confirmar **Versão 12.19.11**.
- O financeiro aparece em 4 grupos: TESOURARIA, FATURAÇÃO, RELATÓRIOS &
  CONTROLO, FINANÇAS · CONFIGURAÇÃO.
- Validar por perfil (LIVE-TEST): cada item só aparece a quem a rota permite.

## Rollback

- Repor os 3 ficheiros do backup.
