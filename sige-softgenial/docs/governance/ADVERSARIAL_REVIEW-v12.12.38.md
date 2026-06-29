# Revisao adversarial - v12.12.38 - Fase 2 - Cofre de segredos - cobertura, mascaramento e nao-vazamento

Rediagnostico adversarial do segundo incremento do cofre. O exercicio assume a postura de um revisor hostil e procura partir cada frente antes de a declarar pronta.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Ler o comprimento de um segredo pela mascara. A mascara esconde a parte oculta com largura fixa e revela apenas os ultimos caracteres, pelo que nao revela o comprimento real. O smoke confirma que o valor original nao aparece na mascara.
2. Fazer um segredo chegar aos logs. Pelo canal de seguranca (sige_security_log), o evento e o contexto sao redigidos antes de qualquer registo: tokens selados (sige2:, gcm1:) e pares chave=valor de segredos viram [SEGREDO]. O smoke confirma a redaccao e que o texto normal passa intacto.
3. Esconder um segredo num URL. Um gate estatico garante que nenhuma chave-credencial e colocada num URL via add_query_arg; se um URL com segredo for registado, a redaccao trata o par chave=valor. O smoke confirma a redaccao de um URL com webhook_token.
4. Sobre-redigir texto legitimo. A redaccao usa padroes especificos (formatos selados e nomes de chave de segredo); mensagens normais como aluno_id=7 ou field=doc_bi passam intactas, comprovado por smoke.
5. Alterar um segredo sem deixar rasto. A gravacao de um segredo regista segredo_alterado com a chave (e o fornecedor, nos pagamentos), nunca o valor, no repositorio de definicoes e na gravacao de pagamentos. O gate exige a presenca nos dois pontos.
6. Partir a cifra de pagamentos ao alargar o registo. A classificacao de segredos so e usada para selar no caminho de pagamentos; adicionar a chave de licenca e declarativo e nao altera esse caminho. Os gates de cofre anteriores (check-vault, smoke-vault com 27 verificacoes) continuam verdes.
7. Tocar num ficheiro canonico. Nenhum ficheiro canonico foi alterado; o registo de auditoria financeiro (finance-core) nao foi tocado. A redaccao liga-se ao canal de seguranca.
8. Inflar a superficie ou as opcoes. As alteracoes sao funcoes puras no cofre e ligacoes em funcoes ja existentes; o extractor confirma 199 itens de superficie e 132 opcoes, e 11 dependencias externas.

## P0

Nenhum. O armazenamento e a leitura dos segredos vivos nao sao alterados.

## P1

Nenhum. Os segredos deixam de poder chegar aos logs em claro pelo canal de seguranca, e a alteracao de um segredo passa a ser auditavel sem expor o valor.

## P2 e P3

Nenhum novo. Risco de sobre-redaccao mitigado e comprovado por smoke.

## Decisao

Aprovado. Zero P0 e zero P1. As quatro frentes estao completas e provadas por gate e smoke dedicados (corredor 87/87): mascaramento central, redaccao de segredos no canal de seguranca, proibicao de segredos em URL e auditoria de alteracao, com o registo de segredos alargado a licenca. Ficheiros canonicos intocados, sem nova superficie, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Os gates de cofre anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. Rotacao e segredos por escola seguem no proximo incremento da Fase 2.
