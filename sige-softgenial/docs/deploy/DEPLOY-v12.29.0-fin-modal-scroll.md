# DEPLOY v12.29.0 - Financeiro: scroll dos modais + empilhamento global + lembrar secções

Âmbito: transversal aos ecrãs financeiros. Só apresentação/UX (CSS + JS partilhado).
Nenhum PHP financeiro alterado. File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/assets/style.css`
- `wp-content/plugins/sige-softgenial/assets/views/financeiro-core-design-pro.js`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 4 ficheiros actuais.
2. Substituir os 4 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5 (é CSS/JS).

## Validar

- Em Plugins, confirmar Versão 12.29.0.
- SCROLL DOS MODAIS (o problema reportado): abrir um modal financeiro com muito
  conteúdo (ex.: Gerador, Despesas, Centros, Inscrições) e confirmar que o corpo
  do modal **rola** e o botão de acção no fundo é alcançável.
- EMPILHAMENTO: em qualquer ecrã financeiro, o modal aparece por cima da barra
  lateral (não atrás).
- LEMBRAR SECÇÕES: em Pagamentos/Config, expandir uma secção, gravar/recarregar e
  confirmar que essa secção continua expandida (não reabre tudo).
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.29.0-fin-modal-scroll.md

## Rollback

- Repor os 4 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
