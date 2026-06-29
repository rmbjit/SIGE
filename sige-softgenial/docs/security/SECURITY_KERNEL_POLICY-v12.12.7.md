# SECURITY KERNEL POLICY - v12.12.7

## Security Kernel
O Security Kernel é a camada central para classificar e bloquear acções críticas antes dos handlers funcionais.

## enforce
`enforce` bloqueia sem autenticação, nonce/token, tenant, permissão, object guard ou rate limit quando exigidos.

## observe
`observe` é permitido apenas para acções não críticas nesta fase. `risk=critical` com `mode=observe` é proibido.

## delegated
`delegated` indica que o Kernel garante o contrato estrutural e mantém autorização fina numa policy de domínio já existente.

## rate limit
Acções críticas em enforce/delegated têm rate limit quando aplicável.

## auditoria
Acções críticas geram eventos `security_kernel.allowed` ou `security_kernel.denied`, além de auditoria de domínio quando existente.
