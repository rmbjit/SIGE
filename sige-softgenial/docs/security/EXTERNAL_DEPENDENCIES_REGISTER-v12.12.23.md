# EXTERNAL DEPENDENCIES REGISTER - v12.12.23

## host
Nenhum host externo novo. 12 hosts, igual a v12.12.8.

## dependencia
Nenhuma dependencia nova. Incremento puramente interno (guards de escrita por tenant). Baseline em EXTERNAL_DEPENDENCIES_BASELINE-v12.12.11.json.

## risco
Sem risco externo novo. Reduz risco de escrita orfa/contaminada em modo multi-escola estrito; nao adiciona superficie de rede.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- A ancora externa e auto-contida (ficheiro no proprio servidor, em uploads). Nao introduz dependencia de rede nem de terceiros. A ancora em SigeHub (anel adicional) fica para um incremento posterior.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

Sem novo host nem nova dependencia externa. O risco de dependencias externas mantem-se inalterado face a v12.12.20.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

Sem alteracao de dependencia externa nem de host; o nivel de risco de cada dependencia permanece o mesmo. A correccao e interna a shell de administracao.

## Actualizacao v12.12.23 (Fase 8 Incr 1)

Sem alteracao de dependencias externas. Nenhum host novo e contactado (mantem-se 12). O inventario de dados pessoais opera inteiramente sobre o esquema local, sem qualquer chamada de rede; o risco de dependencia externa permanece inalterado.
