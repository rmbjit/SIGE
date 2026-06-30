# DEPLOY v12.26.0 - Config (Preços e Serviços): secções colapsáveis + modal acima da sidebar

Âmbito: ecrã Preços e Serviços (view=financeiro-config). Só apresentação/UX.
O ficheiro PHP da config NÃO foi alterado; a melhoria está nos assets partilhados
do financeiro. File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/assets/views/financeiro-core-design-pro.js`
- `wp-content/plugins/sige-softgenial/assets/views/financeiro-core-design-pro.css`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

(Nota: NÃO é preciso substituir `admin/finance/financeiro-config.php` - ficou intacto.)

## Ordem

1. Backup dos 4 ficheiros actuais.
2. Substituir os 4 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5 (é CSS/JS).

## Validar

- Em Plugins, confirmar Versão 12.26.0.
- Tesouraria -> Preços e Serviços (Configuração).
- A página deve estar mais curta: as secções de regras (Multa, Descontos, Creche,
  Localização, Transporte) começam fechadas (clicar no cabeçalho expande/colapsa,
  com seta). A "Lista de Serviços" fica aberta.
- Adicionar/Editar um serviço: o modal deve aparecer por cima da barra lateral.
- Confirmar que guardar preços/regras/serviços funciona EXACTAMENTE como antes.
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.26.0-config.md

## Nota

- A secção "Gestão de Períodos" fica aberta (não tem cabeçalho separado para
  colapsar). Pode ser colapsada numa versão futura com um pequeno ajuste de
  marcação, se desejado.

## Rollback

- Repor os 4 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
