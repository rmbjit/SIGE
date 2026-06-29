# DEPLOY v12.19.10 - Badges BETA no financeiro

Âmbito: barra lateral (só apresentação). File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/includes/admin-shell.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

(`tools/.design-tokens-baseline.json` muda mas é só para o gate de dev.)

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Validar

- Em Plugins, confirmar **Versão 12.19.10**.
- Na barra lateral, "Pagamentos Móveis", "Reconciliação" e "Aprovações" mostram o
  selo BETA (como "Currículos").

## Rollback

- Repor os 3 ficheiros do backup.
