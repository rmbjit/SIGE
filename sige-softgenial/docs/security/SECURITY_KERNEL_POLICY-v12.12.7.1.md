# SECURITY KERNEL POLICY - v12.12.7.1

## Security Kernel
O Security Kernel continua a camada central para classificar e bloquear acoes antes dos handlers funcionais. A v12.12.7.1 acrescenta a cobertura do documento de cobranca.

## enforce
`enforce` bloqueia sem autenticacao, nonce, tenant, permissao ou rate limit quando exigidos. O `query_handler:sige_dev_print` esta em enforce.

## observe
`observe` continua permitido apenas para acoes nao criticas. `risk=critical` com `mode=observe` permanece proibido. Nenhuma acao critica esta em observe.

## rate limit
Acoes em enforce com risco alto tem rate limit. O documento de cobranca usa rate limit proprio no Kernel.

## auditoria
O acesso ao documento de cobranca gera evento `security_kernel.allowed` no Kernel e auditoria de dominio `devedores_lista_impressa` no handler, alem de `devedores_lista_tenant_invalido` quando a escola nao e resolvida.
