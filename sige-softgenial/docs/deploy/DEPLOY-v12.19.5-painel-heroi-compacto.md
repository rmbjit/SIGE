# DEPLOY v12.19.5 - Painel Principal: herói compacto e cartões coerentes

Âmbito: só apresentação (CSS do tema PRO scoped ao dashboard). File Manager sem SSH.

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
- O painel tem cache por transient (2 min); aguardar 2 min se necessário.

## Validar

- Em Plugins, confirmar **Versão 12.19.5**.
- Abrir o Painel Principal: herói compacto, sem brilhos decorativos, título mais
  contido, e cartões com sombra leve igual à dos KPIs.
- Confirmar que os ecrãs Financeiro/Alunos/Turmas/RH mantêm o herói grande de
  sempre (a mudança é só no dashboard).

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
