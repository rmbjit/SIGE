# Rediagnostico adversarial v12.12.4

## Escopo

Versao: `12.12.4 - Security Kernel Foundation`.

Assumiu-se que a entrega poderia conter falhas escondidas, incluindo:

- kernel decorativo que nao intercepta superficies reais;
- superficie de execucao fora do kernel;
- regra `enforce` sem nonce/intencao;
- regra `enforce` critica sem auditoria;
- regra `enforce` critica sem rate limit;
- regra de tenant ausente em superficie sensivel;
- falsa cobertura por comparar extractor contra ele mesmo;
- endpoint publico fora da politica;
- quebra de webhooks ao aplicar nonce WordPress indevido;
- delegacao de `wp_ajax:sige_settings_save` quebrada;
- P0/P1 residual mascarado por gate fraco.

## Evidencia executada

- Superficies no manifesto v12.12.4: 174.
- Regras do Security Kernel: 174.
- Regras em `enforce`: 4.
- Regras em `observe`: 170.
- Gates oficiais: 33/33 verdes, executados integralmente com `php tools/run-gates.php`.
- PHP lint: 341 ficheiros PHP, 0 falhas.
- Testes negativos do Security Kernel: OK.

## Verificacoes adversariais feitas

- Remover uma superficie do kernel faz o gate falhar.
- Remover nonce de regra `enforce` faz o gate falhar.
- Remover auditoria de regra critica faz o gate falhar.
- Remover rate limit de regra critica faz o gate falhar.
- Usar `mode` invalido faz o gate falhar.
- O gate confirma cobertura entre manifesto, regras PHP e snapshot JSON.
- A politica de endpoints publicos foi corrigida para listar explicitamente os 8 endpoints publicos.
- `wp_ajax:sige_settings_save` preserva autorizacao fina delegada em `SIGE_Settings_Policy::can_edit`.
- REST webhooks permanecem em `observe`, evitando regressao por nonce WordPress indevido.

## Achados

### P0

Nenhum P0 aberto comprovado.

### P1

Nenhum P1 aberto comprovado.

### P2 residuais aceites para fases futuras

- 170 superficies permanecem em `observe`; isto e decisao deliberada da Fase 1 e nao lockdown completo.
- Autorizacao legada via `current_user_can` ainda existe e sera tratada em Critical Actions Lockdown.
- Tenant fail-closed global ainda nao foi imposto em todo o sistema; fica para Tenant Isolation Hardening.
- Webhooks publicos precisam de enforcement especifico por token/HMAC em fase propria.
- Validacao funcional com utilizadores/perfis reais em staging ainda e obrigatoria.

### P3

Nenhum P3 bloqueador. A evidencia final inclui execucao integral de `php tools/run-gates.php` e lint completo.

## Decisao

A versao e concluivel para a Fase 1 porque:

- Security Kernel runtime existe e e carregado no bootstrap.
- Regras operacionais cobrem o manifesto completo.
- Enforcement piloto esta activo nas 4 superficies aprovadas.
- Gates positivos e negativos estao verdes.
- Nao ha P0/P1 aberto.

Estado final: aprovado para staging.
