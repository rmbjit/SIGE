# EXTERNAL DEPENDENCIES REGISTER - v12.12.26

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

## Actualizacao v12.12.24 (Fase 8 Incr 1)

Sem alteracao de dependencias externas. Nenhum host novo e contactado (mantem-se 12). O inventario de dados pessoais opera inteiramente sobre o esquema local, sem qualquer chamada de rede; o risco de dependencia externa permanece inalterado.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- Nenhuma dependencia externa nova; nenhum host externo novo. O risco do incremento e interno (exportacao de dados pessoais), mitigado por permissao, nonce, rate limit, isolamento por escola e auditoria.


## Actualizacao v12.12.25 - Fase 8 incremento 3 (apagamento por anonimizacao)

Este incremento acrescenta a primeira operacao destrutiva do produto: o apagamento por anonimizacao (direito ao apagamento). Foi adicionado um endpoint admin_post governado em modo enforce e risco critico (admin_post:sige_privacidade_apagar), com confirmacao em dois passos por numero de processo, nonce, rate limit (5/300s), isolamento por escola e auditoria antes e depois. A superficie de accao passou de 198 para 199 e o enforce de 32 para 33. Nova permissao critica privacidade.apagamento_executar, semeada so a administracao e direccao e sempre auditada. Sem eliminacao fisica de linhas e sem migracao de esquema (SCHEMA_VERSION inalterada). Manifesto e Kernel mantem-se alinhados (199 == 199).


## Actualizacao v12.12.26 - Fase 8 incremento 3.2 (completar o catalogo de PII)

Este incremento classifica as 12 colunas com aspeto de dado pessoal que estavam fora do catalogo (lacunas detectadas pelo inventario da Incr 1), levando o catalogo de 76 para 88 campos e as lacunas de 12 para 0. As nove colunas identificaveis de sige_alunos (incluindo o documento de identidade digitalizado, a fotografia, os contactos de emergencia e os dados da pessoa autorizada a buscar o aluno) passam a ser tratadas pelo dossie de acesso/portabilidade e pelo motor de anonimizacao, fechando o buraco em que sobreviviam a um apagamento. As duas datas operacionais de cobranca sao classificadas mas preservadas na anonimizacao (lista de preservacao); a coluna de notas de funcionario e classificada mas fica fora do ambito do apagamento do aluno. Sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (SCHEMA_VERSION inalterada).
