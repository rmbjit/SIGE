# DEPLOY v12.24.0 - Financeiro Gerador: modal acima da barra lateral

Âmbito: ecrã Lançar Mensalidades (view=financeiro-gerador). Só apresentação
(uma regra de z-index, scoped à view). File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/assets/style.css`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais.
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5 (importante: é CSS).

## Validar

- Em Plugins, confirmar Versão 12.24.0.
- Abrir Tesouraria -> Lançar Mensalidades.
- Preencher e clicar em gerar: o modal de confirmação deve aparecer centrado e
  por cima da barra lateral (antes ficava parcialmente atrás).
- Confirmar: o modal de sucesso também por cima da barra lateral.
- ESC / clicar fora fecham e a página volta ao normal.
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.24.0-financeiro-gerador.md

## Nota

- A regra é scoped a esta view; não altera os outros ecrãs financeiros. O mesmo
  ajuste pode ser aplicado a Centros/Auditoria/Despesas/Lançamentos quando forem
  revistos.

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
