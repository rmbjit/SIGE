# Adversarial Review v12.12.21

## Rediagnostico adversarial - Fase 7 incremento 2 (regra de quatro-olhos)

Revisao adversarial da entrega v12.12.21 (dupla aprovacao de estornos e
reaberturas de caixa), conduzida sobre a versao empacotada, na perspectiva de um
atacante interno e de um revisor de seguranca. Objectivo: encontrar qualquer
forma de contornar a separacao de funcoes, executar sem aprovacao, ou partir o
fluxo financeiro existente.

### Superficie analisada

- Modulo includes/finance-aprovacoes.php (solicitar, decidir, executar, listar).
- Funcao de execucao da reabertura (sige_fin_reabertura_caixa_executar).
- Handlers interceptados em financeiro-extratos.php (estorno e reabertura).
- Ecra admin/finance/aprovacoes-view.php e endpoint de decisao.
- Tabela sige_fin_aprovacoes e migracao.
- Registo no manifesto e nas regras do Security Kernel.

### Vectores testados e resultado

- V1: auto-aprovacao (o solicitante aprova o seu proprio pedido). BLOQUEADO. O
  decisor nao pode ser o solicitante (comparacao de user_id); validado por smoke.
- V2: executar sem aprovacao, via o handler antigo. NEUTRALIZADO. O handler do
  estorno e o da reabertura ja nao executam; apenas criam pedido. Verificado:
  estornarPagamento ja nao e chamado em extratos.
- V3: dupla execucao de um pedido. BLOQUEADO. Um pedido ja decidido nao volta a
  ser decidido; estornarPagamento mantem FOR UPDATE e guarda de duplo estorno.
- V4: dois pedidos pendentes para o mesmo alvo. BLOQUEADO. Guarda anti-duplicado
  recusa o segundo pedido pendente.
- V5: aprovar sem a permissao da accao. BLOQUEADO. O endpoint exige
  financeiro.estornar OU financeiro.caixa_reabrir (regra enforce do Kernel) e o
  codigo aplica a permissao especifica por tipo.
- V6: aprovar de outra escola (cross-tenant). BLOQUEADO. Tenant guard e leitura
  do pedido por escola_id; fail-closed para escola <= 0.
- V7: saltar o MFA do aprovador. NEUTRALIZADO. A execucao do estorno e da
  reabertura exige step-up; quando nao satisfeito, devolve mfa_required e o
  pedido fica pendente (validado por smoke).
- V8: CSRF no endpoint de decisao. BLOQUEADO. Nonce dedicado verificado no
  handler.
- V9: regressao de calculo. NAO OCORRE. As tres funcoes de calculo permanecem
  byte-identicas a v12.12.20 (diff confirmado).
- V10: regressao de design (estilo inline). NAO OCORRE. A view e o CSS usam so
  tokens; o gate de consistencia visual nao sobe.

### P0

Nenhum achado P0. Nao existe caminho para executar um estorno ou reabertura sem
aprovacao de um segundo utilizador autorizado.

### P1

Nenhum achado P1. A separacao de funcoes, o nonce, a permissao, o tenant e o MFA
estao todos presentes e verificados.

### P2 / P3 (observacoes, fechadas por documentacao)

- P2-1: pedidos pendentes nao expiram automaticamente. Aceite e documentado: a
  expiracao por cron pertence a fase de infraestrutura; o pedido permanece
  visivel e decidivel. Nao e defeito.
- P3-1: o requerente faz MFA no momento do pedido de reabertura (mantido do
  fluxo anterior) e o aprovador faz MFA na execucao. Decisao: manter a dupla
  confirmacao de identidade como defesa em profundidade.

### Decisao

APROVADO. Zero P0 e zero P1. As observacoes P2/P3 fecham por documentacao nos
registos de governanca, sem adiamento. A Fase 7 fica completa: caixa imutavel,
reabertura com permissao, estorno com razao e movimento inverso, reconciliacao e
divergencias, idempotencia de webhooks, auditoria de operacoes sensiveis e
regra de quatro-olhos. Pode avancar para a Fase 8 (Dados, privacidade e
retencao).
