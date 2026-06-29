# Rediagnostico adversarial - v12.12.12 (reposicao automatica)

Base: v12.12.11. Alvo: a reposicao automatica da operacao de servico apos a
confirmacao de identidade, e a captura nos 6 pontos de servico. Metodo: leitura
adversarial do codigo novo e dos pontos de contacto, com verificacao por
execucao real (smoke com 26 verificacoes, todas verdes, incluindo a prova de
execucao unica e ausencia de execucao dupla).

## Resultado

Zero defeitos P0 e zero defeitos P1. O risco principal (execucao dupla) e a
preocupacao de contorno de permissoes ou tenant estao mitigados por desenho e
verificados. Achados residuais sao decisoes documentadas (regra inegociavel: o
que nao e defeito fecha-se documentando como tal).

## Modelo de ameaca e analise

### A-R1 Execucao dupla (P0 candidato) - MITIGADO E VERIFICADO
O consumo e atomico: o descritor e apagado ANTES de despachar, pelo que uma
confirmacao concorrente nao o encontra. Verificado no smoke: apos um consumo, a
operacao foi executada exactamente uma vez e um segundo consumo nao dispara
(devolve null, contador fica em 1). Em segunda linha, as proprias operacoes tem
guardas de estado internas (FSM, valor_pago, ja estornado), que tornam qualquer
re-execucao um nao-evento seguro. Sem defeito.

### A-R2 Contorno de permissoes pela reposicao (P0 candidato) - MITIGADO
Cada uma das 6 operacoes valida a permissao DENTRO do metodo (self::userCan ->
sige_can / current_user_can), nao apenas no call-site. Como a reposicao re-invoca
o mesmo metodo, a permissao e re-validada. Alem disso, para capturar o descritor
o utilizador ja tinha passado o call-site e chegado ao metodo, logo ja estava
autorizado momentos antes. Sem defeito.

### A-R3 Contorno de tenant pela reposicao (P0 candidato) - MITIGADO
sige_tenant_write_guard(escola_id, ...) e a primeira linha de cada metodo e e
re-executado na reposicao. O escola_id usado e o capturado da chamada original
(ja validada), nao vem do cliente na confirmacao. Se, no intervalo, o utilizador
deixar de ter acesso a essa escola, o guard devolve contexto invalido e a
operacao nao corre. Sem defeito.

### A-R4 Adulteracao de argumentos na reposicao (P1 candidato) - MITIGADO
O descritor e capturado do lado do servidor (func_get_args da chamada original) e
guardado num transient por utilizador. A confirmacao nao transporta argumentos da
operacao; a reposicao usa apenas o descritor. Verificado no smoke: os argumentos
sao despachados intactos e na ordem certa para cada operacao. Sem defeito.

### A-R5 Multiplas operacoes pendentes (P2) - DECISAO: ACEITE
Se o utilizador desencadear duas operacoes criticas antes de confirmar, o segundo
descritor sobrepoe o primeiro (vence a ultima). Apos a confirmacao, repoe-se a
ultima; a anterior repete-se a mao. Decisao de aceitar: e o comportamento mais
previsivel (uma confirmacao, uma reposicao), e a operacao anterior continua
disponivel pela via manual. Uma fila de reposicoes seria complexidade
desnecessaria neste incremento. Nao defeito.

### A-R6 Descritor obsoleto a disparar fora de tempo (P2) - MITIGADO
O descritor tem TTL curto (SIGE_MFA_REPLAY_TTL, 600s, igual a validade do
desafio) e e consumido na confirmacao. Se a confirmacao ocorrer depois do TTL, o
descritor expirou e a operacao repete-se a mao. Sem defeito.

### A-R7 Forja de descritor - MITIGADO
O descritor vive num transient do lado do servidor; forja-lo exigiria acesso de
escrita a base de dados (o atacante ja teria o site). A confirmacao que dispara a
reposicao e protegida por login e nonce; so o proprio utilizador dispara a sua
reposicao. Sem defeito.

### A-R8 Ambito (so as 6 operacoes de servico) - COERENTE
A captura so ocorre nas 6 operacoes de servico; config (e-Mola, M-Pesa) e caixa
(reabrir, fechar) nao capturam e mantem a repeticao manual, como na charter. O
registo de despacho cobre exactamente as 6; um contexto fora delas nao e
capturado nem despachado. Verificado no smoke (contexto de config nao sobrepoe o
descritor) e no gate (config nao tem captura).

### A-R9 Comportamento com a reposicao desligada - COERENTE
Desligada por defeito (opcao sige_mfa_autoreplay = off; kill-switch
SIGE_MFA_AUTOREPLAY_OFF). Com off, nao captura nem consome: o comportamento e o
da v12.12.11 (confirmar e repetir a mao). Verificado no smoke.

## Conclusao

O incremento esta a zero em defeitos (P0 e P1). Os achados P2 (A-R5, A-R6) sao
decisoes de desenho aceites e documentadas. A reposicao re-valida tenant,
permissoes e estado por passar pelo mesmo metodo, e nao executa duas vezes.
Pronto a entregar como v12.12.12, desligado por defeito e sem alteracao para quem
nao ligar.
