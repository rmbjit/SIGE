# PHASE CHARTER v12.12.3 - Notas Beta nos Modulos em Validacao

## Objectivo
Adicionar comunicacao visual clara de estado Beta nos views definidos pelo cliente, sem alterar comportamento funcional, permissoes, tenant, financeiro, comunicacao, presencas, endpoints ou schema.

## Incluido
- Marcar `financeiro-mpesa`, `whatsapp_circulares`, `comunicacoes_central` e `presencas` como Beta no catalogo central `includes/ui-components.php`.
- Adicionar `status=beta` e `beta_note` especifico por view.
- Actualizar o cabecalho central para renderizar selo `BETA` e nota operacional quando o catalogo declarar `status=beta`.
- Adicionar CSS canonico em `assets/style.css` para linha de titulo, selo Beta e nota Beta.
- Criar smoke test dedicado `tools/smoke-beta-views-v12-12-3.php`.
- Integrar o smoke em `tools/run-gates.php`.
- Actualizar metadados de release, changelog, deploy e QA.

## Excluido
- Bloqueio de acesso por estado Beta.
- Alteracao de permissoes, roles ou capabilities.
- Alteracao de regras financeiras, M-Pesa/e-Mola, envio WhatsApp/e-mail ou presencas.
- Alteracao de queries, tenant guards, nonces ou auditoria.
- Alteracao de schema, migracoes ou dados persistidos.
- Refactor visual amplo, CSP enforcement ou Design System PRO completo.

## Riscos
- P1: um dos views declarados nao renderizar a nota por usar cabecalho proprio.
- P1: alteracao acidental de regra funcional em modulo Beta.
- P2: nota Beta visualmente insuficiente em algum ecran, exigindo ajuste em staging.
- P3: operador interpretar Beta como bloqueio funcional, apesar de ser apenas aviso.

## Criterios de aceitacao
- Os quatro views possuem `status=beta` e `beta_note` no catalogo UI.
- O cabecalho central renderiza selo `BETA` e nota Beta apenas quando o view estiver marcado como Beta.
- Os quatro views continuam mapeados no router.
- Os quatro views usam cabecalho central e nao cabecalho proprio, para a nota ser exibida.
- Smoke Beta verde.
- PHP lint verde.
- `php tools/run-gates.php` verde.
- Rediagnostico adversarial sem P0/P1 aberto.
