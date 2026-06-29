# Phase Charter - v12.15.3 - Design System Stability Recovery

## Objectivo
Recuperar estabilidade visual e operacional depois das regressões introduzidas pela camada global da Fase 11, sem regressão da CSP zero-inline nem das correcções de segurança anteriores.

## Escopo
- Remover da produção o carregamento global de `sige-design-system-pro.css` e `sige-design-system-pro.js`.
- Restaurar o comportamento visual aprovado da v12.14.4.
- Preservar a protecção do fluxo de confirmação de pagamento para impedir abertura sem dívida/serviço, método e total válido.
- Criar gate que impeça a reintrodução acidental de enhancer global de Design System.

## Não-escopo
- Remodelação visual ampla do SIGE.
- Novo motor PDF server-side.
- Alterações de regras de negócio, permissões, tenant isolation ou segurança CSP.

## Critérios de aceitação
- Área principal volta a depender da estrutura aprovada antes da camada Design System PRO global.
- Menus de acções dos alunos deixam de ser reformatados pela camada transversal.
- Não há enqueue dos assets `sige-design-system-pro.*`.
- Gates existentes passam.
- Smoke v12.15.3 passa.

## Decisão técnica
A abordagem profissional para estabilização é rollback selectivo para a última base aprovada, não mais uma sobreposição CSS. A Fase 11 continuará futuramente apenas com implementação opt-in por módulo e validação visual por viewport.
