# Inventario Tecnico - v12.15.6 - Shell, Scroll, Topbar e Sidebar

## Base
- Base: v12.15.5 - Design System PRO Diagnostic Baseline.
- Tipo de alteracao: visual estrutural limitada ao shell.
- Areas tocadas: assets, ui-kit, admin-shell scroll restore, gates e documentacao.

## Ficheiros alterados

| Ficheiro | Tipo | Motivo |
|---|---|---|
| `assets/sige-shell-stability.css` | Novo CSS externo | Contrato de scroll, topbar e sidebar por breakpoint |
| `assets/sige-shell-stability.js` | Novo JS externo | Sincronizacao da sidebar, overlay e estados ARIA |
| `includes/ui-kit.php` | Enqueue | Carregar assets do shell por `wp_enqueue_style` e `wp_enqueue_script` |
| `includes/admin-shell.php` | Ajuste pontual | Scroll independente passa a restaurar apenas em desktop real, 1101px ou mais |
| `sige-softgenial.php` | Versao | 12.15.6 |
| `BUILD.json` | Build | 12.15.6 |
| `CHANGELOG.md` | Release | Entrada da versao |
| `tools/check-shell-scope-v12-15-6.php` | Novo gate | Bloqueia selectores globais perigosos |
| `tools/smoke-shell-scroll-v12-15-6.php` | Novo smoke | Valida activos, versao, enqueue e contrato de scroll |
| `tools/run-gates.php` | Gate runner | Integra os novos gates |

## Contrato por viewport

| Faixa | Contrato |
|---|---|
| 1101px ou mais | Shell com grid, sidebar fixa no fluxo, conteudo com scroll independente |
| 861px a 1100px | Pagina com scroll natural, topbar sticky e sidebar off-canvas |
| ate 860px | Pagina com scroll natural, topbar sticky compacta e sidebar off-canvas |

## Riscos evitados
- Regra desktop `overflow:hidden` a bloquear tablet.
- Conteudo principal sem scroll em tablets.
- Sidebar aberta sem overlay sincronizado.
- Body bloqueado mesmo com sidebar fechada.
- Alteracao acidental de cards ou accoes internas.

## Risco residual
A validacao visual real continua obrigatoria porque os gates CLI nao conseguem capturar todos os efeitos de CSS ja existente com `!important`.
