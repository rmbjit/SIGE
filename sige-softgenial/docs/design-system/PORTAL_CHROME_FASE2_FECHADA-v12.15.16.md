# Fase 2 fechada: Portal Chrome ligado por defeito (v12.15.16)

## Estado

A Fase 2 (split chrome vs views do portal) esta FECHADA em producao. A prova
visual em browser foi confirmada pelo operador, como encarregado e como aluno,
em telemovel, tablet e desktop. A flag de chrome esta agora ligada por defeito.

## Trajectoria completa

| Versao | Entrega |
|---|---|
| 12.15.13 | Fase 1: portal enxuto. Evita wp_enqueue_media() e CSS financeiro de staff para o encarregado/aluno. |
| 12.15.14 | Fase 2 (encenada): folha de chrome `style-portal-chrome.css`, ligacao com flag DESLIGADA por defeito. |
| 12.15.15 | Productionizacao: folha GERADA do style.css, gate drift-proof. |
| 12.15.16 | Fase 2 fechada: flag LIGADA por defeito apos prova visual. |

## O que o portal carrega agora

No portal enxuto (encarregado/aluno em `aluno_portal`), por defeito:

| Asset | Estado |
|---|---|
| Uploader de media do WordPress | Nao carregado (Fase 1) |
| CSS financeiro de staff (devedores, reconciliacao, aprovacoes) | Nao carregado (Fase 1) |
| `style.css` completo (~302 KB) | Substituido pela folha de chrome (~109 KB) (Fase 2) |
| `style-portal-chrome.css` (chrome/core) | Carregado |
| `sige-tokens`, `sige-ui`, `sige-shell-stability`, mobile-tablet-ux | Carregados |

Staff e todas as outras views continuam com o `style.css` completo, intacto.

## Reversao

Se algo surgir em producao:

```
update_option('sige_portal_chrome_css_v121514_enabled', '0');
```

Repoe o `style.css` completo no portal, de imediato, sem reinstalar. Para
desligar tambem o resto do portal enxuto (media e CSS financeiro):

```
update_option('sige_portal_lean_assets_v121513_enabled', '0');
```

## Manutencao continua

A folha de chrome e gerada do `style.css`. Sempre que o chrome do `style.css`
mudar:

1. `php tools/gen-portal-chrome.php`
2. `php tools/run-gates.php` (o gate `Portal Chrome vs Views split (v12.15.16)`
   fica vermelho se houver drift).

## Proximo (fora do ambito do portal)

Do diagnostico de UX inicial, ficam por atacar o P2 (caminho rapido
inline/teclado/lote para a secretaria nas tarefas de alta frequencia) e o P3
(pesquisa global / saltar-para). Sao frentes proprias, cada uma com a sua fase.
