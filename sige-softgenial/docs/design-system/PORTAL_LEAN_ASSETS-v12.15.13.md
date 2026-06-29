# Portal Enxuto: peso de entrega por papel (v12.15.13)

## Problema

A Pagina do Aluno (view `aluno_portal`) e a unica cara externa do produto para
o encarregado e o aluno. Estes papeis entram poucas vezes por trimestre,
tipicamente no telemovel e em rede fraca, apenas para LER (saldo, propinas,
boletim) e mudar a palavra-passe. No entanto, eram servidos exactamente com a
mesma shell administrativa completa de um director: `wp_enqueue_media()` (toda a
maquinaria de media do WordPress) e os CSS de views financeiras de staff que o
portal nunca renderiza.

Diagnostico anterior (lente de UX, ponto P1): o problema nao e o ecra do portal
(o seu CSS proprio esta bem feito), e o VEICULO de entrega.

## O que esta versao faz

Introduz uma politica dedicada, `includes/portal-lean-assets.php`, com a funcao
de decisao `sige_portal_lean_is_active()`. Quando activa, a resposta usa um
bundle mais leve para o portal.

Condicoes para activar (todas obrigatorias):

| Condicao | Razao |
|---|---|
| Flag `sige_portal_lean_assets_v121513_enabled` ligada | Reversibilidade imediata |
| View = `aluno_portal` | So a Pagina do Aluno e alvo |
| Utilizador exclusivo do portal (encarregado/aluno) | Staff pode navegar para modulos pesados |
| Nenhuma capacidade de staff | Director que inspecciona o portal mantem a shell completa |

## O que e suprimido (e o que NAO e)

| Asset | No portal enxuto | Porque |
|---|---|---|
| `wp_enqueue_media()` | Suprimido | Portal nao tem uploader nem AJAX. Ganho dominante. |
| `devedores.css`, `reconciliacao.css`, `aprovacoes.css` | Suprimidos | Views financeiras de staff que o portal nunca renderiza (6 251 bytes). |
| `style.css` (chrome) | Mantido | O portal usa classes de chrome `.sg-app-*`. |
| `sige-tokens`, `sige-ui-kit`, `sige-shell-stability` | Mantidos | O portal usa tokens e o kit `.sgk-`. |
| `mobile-tablet-ux` | Mantido | Chrome mobile do qual o portal depende no telemovel. |
| `privacidade.css` | Mantido | Conservador: o portal tem area de palavra-passe. |
| CSS proprio do portal (inline na view) | Mantido | E o ecra em si. |

## Contrato financeiro

A politica decide APENAS enfileiramento de assets. Nao referencia saldos,
lancamentos, recibos nem funcoes de calculo, e nao acede a BD. Garantido pelo
gate `check-portal-lean-assets-scope-v12-15-13` (verificacao de acoplamento de
codigo). O motor de devedores (`require_once fin-devedores-calendario.php`)
permanece sempre carregado, dentro e fora do portal enxuto. Nenhum ficheiro do
baseline financeiro foi tocado.

## Reversao

`update_option('sige_portal_lean_assets_v121513_enabled', '0')` repoe a shell
completa para todos os papeis, incluindo o portal. Degradacao segura: se a
politica nao estiver carregada, o sinal fica falso e tudo carrega como antes.

## Fase seguinte (nao incluida)

O ganho grande remanescente e separar `style.css` (4 668 linhas) em chrome vs
views, para o encarregado carregar apenas o chrome em vez do design system de
36 ecras. Exige extracao cuidada e prova visual antes/depois em browser, por
isso fica para vaga propria.
