# QA Smoke v12.12.5

## Gates
`php tools/run-gates.php` passou com 35/35 gates verdes.

## Security Kernel runtime
- REST routes mapeiam por namespace + route.
- Shortcode `sige_portal` tem dispatch via `pre_do_shortcode_tag`.
- `wp_hook` tem dispatch runtime via `add_action` dinamico.
- Quatro superficies enforcement piloto continuam em enforce.

## Pagamentos moveis
- M-Pesa e e-Mola escrevem options scoped por escola em multi-escola estrito.
- Tokens de webhook resolvem escola por token scoped.

## Decisao
A versao esta pronta para validacao em staging. P0/P1 aberto: 0.
