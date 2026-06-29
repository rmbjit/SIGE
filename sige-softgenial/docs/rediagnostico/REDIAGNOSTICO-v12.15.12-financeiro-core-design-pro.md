# Rediagnóstico Adversarial - v12.15.12 - Financeiro Core

## Pergunta adversarial 1: a versão mexeu em fórmulas?
Não. A entrega não altera `admin/finance/*.php`, `includes/finance-core.php` nem motores financeiros protegidos. O gate de integridade compara hashes SHA-256.

## Pergunta adversarial 2: a versão pode tornar pagamento lento ou mudo?
O JS é passivo e não intercepta cliques, submits, modais ou selecções. Não usa `MutationObserver`, wrappers, `appendChild` ou dispatcher. O risco funcional é reduzido.

## Pergunta adversarial 3: há risco visual?
Sim, baixo e reversível. Há CSS novo para views financeiras. A mitigação é escopo por view e feature flag `sige_design_financeiro_core_v121512_enabled`.

## Pergunta adversarial 4: há risco de regressão em Alunos, Portaria ou PDFs?
Baixo. A whitelist de views exclui esses módulos e os assets novos não carregam fora do Financeiro Core.

## Pergunta adversarial 5: a versão está suficientemente testada?
Foi testada por lint, gates CLI, CSP checks e integridade de hashes. Não foi testada em browser autenticado neste ambiente. Staging visual continua obrigatório antes de produção.

## Riscos residuais
- Diferença visual pontual em submódulo financeiro com CSS legado.
- Necessidade de ajustar posteriormente Pagamentos mobile em vaga própria, caso o cliente reporte problema de fluxo.
