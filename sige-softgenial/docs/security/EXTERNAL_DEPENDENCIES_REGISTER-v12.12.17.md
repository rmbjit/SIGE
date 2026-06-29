# EXTERNAL DEPENDENCIES REGISTER - v12.12.17

## host
Nenhum host externo novo. 12 hosts, igual a v12.12.8.

## dependencia
Nenhuma dependencia nova. Incremento puramente interno (guards de escrita por tenant). Baseline em EXTERNAL_DEPENDENCIES_BASELINE-v12.12.11.json.

## risco
Sem risco externo novo. Reduz risco de escrita orfa/contaminada em modo multi-escola estrito; nao adiciona superficie de rede.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- A ancora externa e auto-contida (ficheiro no proprio servidor, em uploads). Nao introduz dependencia de rede nem de terceiros. A ancora em SigeHub (anel adicional) fica para um incremento posterior.
