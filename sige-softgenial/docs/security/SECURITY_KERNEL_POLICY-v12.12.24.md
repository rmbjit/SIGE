# SECURITY KERNEL POLICY - v12.12.24

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

## Nota v12.12.11 (MFA de operacao)

A Fase 4 (incremento 1) acrescenta a accao admin_post:sige_mfa_confirm ao Security Kernel em modo observe (risco high, intent nonce, auditoria activa, rate limit sk_admin_post_sige_mfa_confirm 20/300). O enforcement mantem-se nas 30 operacoes da Fase 3; o endpoint de confirmacao MFA auto-protege-se por login e nonce e e observado pelo kernel. Total de regras: 194 (enforce 30, delegated 17, observe 147).

## v12.12.11 (incremento TOTP)

- Nova regra: admin_post:sige_mfa_totp_enroll, risco alto, em observe, com intent nonce (sige_mfa_totp_enroll), auditoria e rate limit. Espelha admin_post:sige_mfa_confirm.
- Total de regras: 195 (mais uma). Em enforce: 30 (inalterado). Em observe: 148.
- O endpoint trata inscricao, confirmacao e desactivacao da aplicacao autenticadora do proprio utilizador; cada utilizador so altera a sua conta.

## v12.12.12 (reposicao automatica)

- Regras de Kernel inalteradas. A reposicao reutiliza o endpoint existente admin_post:sige_mfa_confirm (ja governado, em observe) para disparar a re-execucao apos a confirmacao; nao adiciona endpoint nem regra.
- Total de regras: 195 (inalterado). Em enforce: 30. Em observe: 148.
- A re-execucao passa pelo mesmo metodo de servico, que mantem os guards de tenant e permissao; o kernel continua a observar o endpoint de confirmacao.

## v12.12.13 (painel de controlo de seguranca MFA)

- Regra nova admin_post:sige_mfa_settings_save em observe, modulo sistema, risco alto. legacy_caps apenas ['administrator'] (sem perfis SIGE); a enforcement real e o gate sige_is_real_wp_admin_user no handler.
- Total de regras: 196 (195 -> 196). Em enforce: 30 (inalterado). Em observe: 149.
- O painel so e acessivel ao super admin real; o kernel observa o endpoint de gravacao, que e auditado (mfa_settings_change e mfa_stepup_disabled).

## v12.12.14 (Secret Vault, incremento 1)

- Sem alteracao as regras do Kernel: nao ha endpoint novo. Total 196; enforce 30; observe 149.
- O cofre reforca a confidencialidade dos segredos em repouso (defesa em profundidade), complementar ao Kernel (que governa o acesso e as accoes).

## v12.12.15 (Ledger financeiro, incremento 1)

- Sem alteracao as regras do Kernel: nao ha endpoint novo (ecra so de leitura). Total 196; enforce 30; observe 149.
- O ledger complementa o Kernel: o Kernel governa o acesso e as accoes; o ledger torna o efeito das operacoes criticas imutavel e auditavel a prova de adulteracao.

## v12.12.16 (patch correctivo do Ledger)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Patch de migracao e resiliencia, sem superficie nova.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Instrumentacao interna e ancora em ficheiro, sem superficie nova.

## v12.12.18 (Ledger incr 3: lancamentos)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Instrumentacao interna e escrita diferida no shutdown, sem superficie nova.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Eventos internos imediatos, sem superficie nova.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

Security Kernel: nova regra em enforce para view_action:financeiro-aprovacoes:sige_fin_aprovacao_decidir (enforce passa de 30 para 31; observe 149; delegated 17; total 197). Intent por nonce, rate limit e auditoria activos. Manifesto igual a regras.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

O Security Kernel permanece inalterado: enforce 31, observe 149, delegated 17. A auditoria e o rate limit das regras criticas mantem-se. Esta release nao adiciona, remove nem altera regras; apenas repoe o roteamento de duas views ja governadas.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

O Security Kernel mantem-se inalterado: 197 regras, 31 em modo enforce, as restantes em observe. O novo ecra de inventario de dados pessoais nao acrescenta endpoint governado pelo Kernel (e so leitura, sem POST), pelo que nao ha nova regra, nem alteracao de rate limit nem de auditoria. Manifesto e Kernel permanecem alinhados (197 == 197).

## Actualizacao v12.12.24 - Fase 8 incremento 2

- O Security Kernel passa a 198 regras (sobe de 197), com enforce em 32 (sobe de 31). A regra nova, admin_post:sige_privacidade_exportar, esta em modo enforce.
- A regra aplica: permissao privacidade.acesso_exportar, intencao por nonce (_wpnonce, accao sige_privacidade_exportar), rate limit (10/300s) e auditoria de cada exportacao. Manifesto e Kernel alinhados (198 == 198). observe mantem as restantes regras de lockdown progressivo.
