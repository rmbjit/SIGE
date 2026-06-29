# SECURITY KERNEL POLICY - v12.12.8

## Security Kernel
O Security Kernel continua a camada central para classificar e bloquear acoes antes dos handlers funcionais. A v12.12.8 nao altera o contrato de regras do Kernel (193 regras, ids identicos a v12.12.7.1). O endurecimento deste incremento ocorre nos call-sites de escrita e no resolvedor de tenant, complementando o Kernel com defesa em profundidade ao nivel da resolucao de escola.

## enforce
`enforce` bloqueia sem autenticacao, nonce, tenant, permissao ou rate limit quando exigidos. As regras em enforce mantem-se. Para handlers de escrita com `tenant_required`, o Kernel ja fecha o acesso quando a escola nao resolve em modo estrito; o resolvedor `sige_require_escola_id` reforca essa garantia dentro do proprio handler.

## observe
`observe` continua permitido apenas para acoes nao criticas. `risk=critical` com `mode=observe` permanece proibido. Nenhuma acao critica esta em observe.

## rate limit
Acoes em enforce com risco alto tem rate limit. Sem alteracoes a limites neste incremento.

## auditoria
O resolvedor de escrita regista o evento `tenant_write_blocked` quando uma escrita e bloqueada por falta de escola valida. O resolvedor de leitura ja regista `tenant_context_missing` em modo estrito. Ambos alimentam a auditoria de isolamento por escola.
