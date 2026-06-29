# Matriz de Rastreabilidade - v12.18.0 Operational UX & Workflow Hardening

| Problema | Causa | Solução | Ficheiro/módulo afectado | Teste obrigatório | Critério de aceitação |
|---|---|---|---|---|---|
| Utilizadores executam tarefas críticas sem sequência clara | Views operacionais têm complexidade e pressão de uso | Faixa contextual com 3 passos seguros por fluxo | includes/operational-workflow-hardening.php + assets novos | smoke-v12-18-0-operational-workflow | Cada view catalogada tem título, lead, 3 passos e guardrail |
| Risco de erro humano em pagamentos/cobrança | Conferência antes/depois pode ser esquecida | Microcopy financeiro específico | Catálogo v12.18.0 | check-v12-18-0-operational-workflow-contract | Fluxos financeiros têm guardrails de duplicado/conferência |
| Risco de alteração académica sem revisão | Lançamento/aprovação de notas é sensível | Orientação académica sem alterar fórmula | Catálogo v12.18.0 | check-v12-18-0-no-sensitive-regression | Fórmulas e ficheiros académicos sensíveis preservados |
| Risco de regressão global no shell | Alterações globais de header/sidebar são sensíveis | Inserção via asset novo, sem alterar admin-shell.php | assets/operational-workflow-v12-18-0.js | no-sensitive-regression | Hash do admin-shell preservado |
| Risco de persistência indevida | Preferência estética não deve gravar DB | Fecho apenas por sessionStorage | JS v12.18.0 | operational workflow smoke | Sem update_option, INSERT, ALTER ou AJAX novo |
