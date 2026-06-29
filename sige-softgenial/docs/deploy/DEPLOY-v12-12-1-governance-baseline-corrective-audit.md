# Deploy - SIGE SoftGenial v12.12.1

## Tipo de versao

Corrective audit da Fase 0 - Governação de Engenharia.

## Alteracoes criticas

1. `sige_desp_print` agora exige login, permissao, nonce, contexto de escola valido e queries com `escola_id`.
2. Links de comprovativo/relatorio de despesas usam `wp_nonce_url`.
3. Manifesto cobre hooks dinamicos e query handlers.
4. Gates negativos foram adicionados para impedir falso verde.

## Schema

Nao ha alteracao de schema.

## Validacao recomendada em staging

- Abrir Despesas e imprimir comprovativo por link gerado pela UI.
- Tentar abrir comprovativo sem nonce: deve bloquear.
- Tentar abrir despesa inexistente/de outra escola: deve devolver nao encontrado/bloqueio.
- Validar relatorio de despesas com filtros.
- Validar `php tools/run-gates.php` em ambiente de desenvolvimento.

## Rollback

Reverter para v12.12.0 apenas se houver regressao operacional bloqueadora. O rollback reabre riscos P0/P1 conhecidos no endpoint de despesas e no manifesto incompleto; portanto, deve ser temporario e controlado.
