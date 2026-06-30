# DEPLOY v12.27.0 - Config: editar serviço/preço como pop-up (sem reload)

Âmbito: ecrã Preços e Serviços (view=financeiro-config). UX da edição de serviços.
File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/admin/finance/financeiro-config.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5.

## Validar

- Em Plugins, confirmar Versão 12.27.0.
- Preços e Serviços -> na Lista de Serviços, clicar "Editar" num serviço:
  - O modal deve abrir INSTANTANEAMENTE (sem recarregar a página), já preenchido.
  - Fechar (X, clicar fora, ESC): fecha sem recarregar.
  - Gravar (Actualizar): guarda como antes (submissão normal).
- Confirmar que os valores guardados ficam CORRECTOS (nome, tipo, valor, classe,
  ciclo, centro, multa, e as regras: Multa/Desc. Irmãos/Pronto Pag./Funcionário/
  Activo). Nada se deve perder.
- "Adicionar Serviço" continua a abrir limpo (pop-up) e a gravar como antes.
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.27.0-config-editar-popup.md

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
