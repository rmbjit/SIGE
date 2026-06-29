# Phase Charter - v12.14.0 - Front-end Security & CSP Enforcement

## Fase
Fase 10 - Front-end Security e CSP.

## Objectivo
Eliminar dívida técnica que impedia segurança forte no navegador e permitir que o shell administrativo do SIGE opere com CSP em modo enforcement, sem quebrar a interface crítica.

## Escopo implementado
- Activação de `Content-Security-Policy` real no shell admin autenticado (`page=sige-app`), em vez de `Content-Security-Policy-Report-Only`.
- `script-src` e `script-src-elem` restritos a `'self'` e `nonce` por pedido.
- Nonce CSP aplicado também a scripts inline gerados pelo WordPress através de `wp_inline_script_attributes` e fallback defensivo em `wp_script_attributes`.
- Conversão de padrões críticos de `onclick` para acções declarativas `data-sige-act`.
- Remoção de Google Fonts externos no login e portal, usando stack de fontes de sistema.
- Gates actualizados para bloquear regressão: o shell admin já não pode voltar ao modo Report-Only como requisito da fase.

## Não-escopo declarado
- Eliminação total de todos os `style="..."` históricos: o volume ainda é alto e exige vaga própria de refactor CSS por módulo.
- Eliminação total de todos os atributos `on*` legados: a v12.14.0 removeu o bloqueador P0 para `script-src` com nonce, mas manteve `script-src-attr 'unsafe-inline'` como compatibilidade controlada.
- Alterações de schema, permissões, tenant isolation, regras financeiras ou cálculo académico.

## Critérios de aceitação
- CSP do shell admin em enforcement real.
- Scripts inline críticos com nonce.
- Gate `check-inline-frontend.php` a passar com baseline mais baixo de `onclick`.
- Login/portal sem dependência de Google Fonts externos.
- Sem novo fallback fail-open em acções críticas.
- Riscos residuais documentados.

## Classificação dos riscos residuais
- P0/P1: nenhum conhecido no escopo implementado.
- P2: `style-src 'unsafe-inline'` e `script-src-attr 'unsafe-inline'` mantidos por compatibilidade enquanto a migração zero-inline continua.
