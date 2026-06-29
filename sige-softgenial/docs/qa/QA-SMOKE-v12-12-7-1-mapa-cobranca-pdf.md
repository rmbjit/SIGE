# QA SMOKE - v12.12.7.1 Mapa de Cobranca em PDF

## Resultado
- `tools/smoke-devedores-pdf-v12-12-7-1.php`: 32 verificacoes OK.
- `tools/run-gates.php`: 39/39 gates verdes.
- PHP lint: 349 ficheiros, 0 falhas.
- Render funcional isolado do documento: 16 verificacoes OK.
- Teste de runtime do enforce do Kernel: 6 cenarios OK (permite com cobrancas_ver/gerir; bloqueia sem nonce, sem tenant, sem permissao, e com financeiro.ver sozinho).
- Teste focado da identidade da escola: 5 verificacoes OK (nome e logotipo pela fonte canonica).

## Cobertura principal
- Botao Imprimir lista (PDF) na Central de Cobrancas com URL nonce.
- Query handler sige_dev_print despachado em template_redirect.
- Login, nonce, permissao SIGE alinhada a pagina (cobrancas_ver/gerir, sem financeiro.ver) e tenant fail-closed.
- Identidade da escola pela fonte canonica sige_get_escola_perfil (nome_escola/logotipo).
- Dataset reutiliza a saldo canonica sige_fin_saldo_sql e os mesmos estados do ecra.
- Leitura pura: o handler nunca escreve em tabelas financeiras.
- Regra no Security Kernel em enforce com runtime antecipado multi-hook.
- Manifesto e Kernel alinhados (193 = 193); zero critical em observe.

## Rediagnostico adversarial
Encontrou e corrigiu um P1 (identidade da escola) e um P2 (permissao alargada). Detalhe em docs/governance/ADVERSARIAL_REVIEW-v12.12.7.1.md. Apos correccoes: P0=0, P1=0.

## Observacao
Testes automatizados nao substituem validacao humana em staging com perfis reais e duas escolas.
