# DEPLOY v12.25.0 - Pagamentos: secções colapsáveis + modal acima da sidebar

Âmbito: ecrã Registar Pagamento (view=financeiro-pagamentos). Só apresentação/UX.
O ficheiro PHP do pagamento NÃO foi alterado; a melhoria está nos assets
partilhados do financeiro. File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/assets/views/financeiro-core-design-pro.js`
- `wp-content/plugins/sige-softgenial/assets/views/financeiro-core-design-pro.css`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

(Nota: NÃO é preciso substituir `admin/finance/financeiro-pagamentos.php` - ficou
intacto.)

## Ordem

1. Backup dos 4 ficheiros actuais.
2. Substituir os 4 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5 (é CSS/JS).

## Validar

- Em Plugins, confirmar Versão 12.25.0.
- Tesouraria -> Registar Pagamento -> abrir um aluno com dívidas.
- A página deve estar mais curta: "1. Dívidas Actuais" aberta; "2. Adiantar Meses
  Futuros" e "3. Outros Serviços" fechadas (clicar no cabeçalho expande/colapsa,
  com seta).
- Confirmar que processar um pagamento funciona EXACTAMENTE como antes (valores,
  recibo). O modal de sucesso deve aparecer por cima da barra lateral.
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.25.0-pagamentos.md

## Rollback

- Repor os 4 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
