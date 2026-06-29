# SECURITY KERNEL POLICY - v12.12.10

## Security Kernel
Contrato de regras do Kernel inalterado (193 regras, ids identicos a v12.12.8). O endurecimento deste incremento e na camada de sumidouro de escrita (guards fail-closed) e complementa o Kernel com defesa em profundidade.

## enforce
Regras em enforce mantidas. Para handlers de escrita, o Kernel continua a fechar acesso sem tenant em modo estrito; os guards de sumidouro reforcam essa garantia no proprio ponto de escrita.

## observe
observe so para acoes nao criticas. risk=critical com observe permanece proibido. Nenhuma acao critica em observe.

## rate limit
Sem alteracoes a limites neste incremento.

## auditoria
Todo bloqueio de escrita por falta de escola gera tenant_write_blocked, agora tambem nos sumidouros e funcoes de biblioteca (via sige_tenant_write_guard), fechando a lacuna de auditoria silenciosa da v12.12.8.

## Nota v12.12.10 (MFA de operacao)

A Fase 4 (incremento 1) acrescenta a accao admin_post:sige_mfa_confirm ao Security Kernel em modo observe (risco high, intent nonce, auditoria activa, rate limit sk_admin_post_sige_mfa_confirm 20/300). O enforcement mantem-se nas 30 operacoes da Fase 3; o endpoint de confirmacao MFA auto-protege-se por login e nonce e e observado pelo kernel. Total de regras: 194 (enforce 30, delegated 17, observe 147).
