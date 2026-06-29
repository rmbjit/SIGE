# DEPLOY v12.19.2 - Fundação: kit alinhado à marca roxa

Âmbito: só camada de apresentação. Um ficheiro CSS + 3 fontes de versão.
Pressuposto: File Manager sem SSH (CloudPanel no VPS Hostinger; cPanel no
MochaHost para Casa Colorida).

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/assets/sige-ui.css`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Fazer backup dos 3 ficheiros actuais (descarregar cópia antes de substituir).
2. Substituir `assets/sige-ui.css` pela nova versão.
3. Substituir `sige-softgenial.php` e `BUILD.json`.
4. Não é precisa migração de base de dados. Não há activação/desactivação.

## Limpar cache

- Se houver cache de página/objeto (LiteSpeed, Redis, plugin de cache), purgar.
- O CSS tem cache-busting automático por `filemtime()`; ainda assim, forçar
  recarregamento sem cache no browser (Ctrl+F5).

## Validar a versão no ecrã

- Em Plugins, confirmar que o SIGE mostra **Versão 12.19.2**.
- Abrir um ecrã com componentes do kit (ver LIVE-TEST) e confirmar que os botões
  primários e o anel de foco estão **roxos**, não navy.

## Rollback

- Repor os 3 ficheiros do backup. Reverte instantaneamente; sem efeitos colaterais
  em dados, permissões ou lógica.
