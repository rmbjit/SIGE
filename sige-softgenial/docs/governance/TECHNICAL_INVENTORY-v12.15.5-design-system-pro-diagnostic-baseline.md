# Inventario Tecnico - v12.15.5 - Design System PRO Diagnostic Baseline

## Base analisada
- Base: v12.15.4 - Standalone UI/CSP Recovery.
- Natureza da versao: diagnostico e QA harness.
- Alteracao visual de producao: nenhuma.

## Contagens principais

| Indicador | Valor |
|---|---:|
| Ficheiros totais | 2743 |
| PHP | 445 |
| CSS em assets | 11 |
| JS em assets | 11 |
| `style=` em admin/includes/assets | 2177 |
| handlers `on*=` em admin/includes/assets | 168 |
| blocos `<style>` | 114 |
| blocos `<script>` | 106 |
| `!important` | 14212 |
| `overflow:hidden` | 388 |
| `position:fixed` | 60 |

## Diagnostico
A base tem multiplas camadas visuais acumuladas: `assets/style.css`, `assets/mobile-tablet-ux.css`, `assets/sige-ui.css`, CSS de views especificas, blocos inline historicos e regras com `!important`. Isto torna perigosa qualquer tentativa de normalizacao transversal.

## Pontos de maior risco
- Cards de alunos e menus de accoes.
- Scroll da area principal em tablet/mobile.
- Modais de pagamento e confirmacao.
- Paginas autonomas fora do admin shell.
- Popups de impressao.
- Tabelas financeiras extensas.
- Vistas antigas com CSS embebido.

## Artefacto de baseline
A baseline machine-readable foi criada em:

`docs/design-system/DESIGN_SYSTEM_BASELINE-v12.15.5.json`

## Conclusao
A Fase 11 deve prosseguir por modulo e nunca por camada global automatica. O primeiro passo visual recomendado apos esta baseline e v12.15.6: Shell, scroll, topbar e sidebar, sem tocar nos cards internos dos modulos.
