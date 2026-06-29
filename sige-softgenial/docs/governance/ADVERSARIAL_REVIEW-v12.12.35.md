# Revisao adversarial - v12.12.35 - Armazenamento privado dos documentos sensiveis

Rediagnostico adversarial do incremento 2 da fase de documentos, uploads e QR. O exercicio assume a postura de um atacante e de um revisor hostil e procura partir a entrega antes de a declarar pronta.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Aceder a um documento sensivel pela URL publica directa depois da migracao. O ficheiro ja nao esta no directorio publico (foi movido para sige-private), e a sua nova localizacao tem .htaccess de negacao total. A URL antiga devolve 404 e a nova devolve 403. O documento so abre pelo endpoint autenticado. Coberto pelo desenho e pelo teste de aceitacao.
2. Provocar perda de um ficheiro forcando uma falha a meio do movimento. O movimento copia, verifica o tamanho, constroi a URL privada e so remove o original no fim; se qualquer passo falhar, apaga a copia e devolve vazio, e o valor guardado mantem-se a apontar para o original intacto. Coberto pelo smoke (URL sem ficheiro degrada sem alterar).
3. Deixar uma copia publica de um documento que e imagem (miniatura). As variantes de tamanho e pre-visualizacoes ao lado do original sao movidas tambem para o directorio privado. Coberto pelo smoke (miniatura movida e removida do publico).
4. Reabrir o vector guardando de novo um documento ja privado (reenvio do formulario). O choke-point reconhece que o valor ja aponta para sige-private e devolve-o inalterado, sem mover nem duplicar. Coberto pelo smoke (valor ja privado fica inalterado).
5. Partir a foto, que e mostrada em linha. A foto (campo foto e foto_perfil) e explicitamente excluida da privatizacao; continua publica e a sua apresentacao em linha nao muda. Coberto pelo gate (foto nao privatizada e linha original intacta).
6. Sobrecarregar o site com o dreno em cada pedido. O dreno nao corre em AJAX nem em cron, so nas paginas do SIGE, processa um lote limitado e a consulta do que falta devolve vazio depois de concluida a migracao. Sem opcao de estado, e idempotente e resumivel.
7. Quebrar o endpoint ao mudar a localizacao do ficheiro. O endpoint (secure-document-download) nao foi alterado; o ficheiro privado continua sob o basedir de uploads, pelo que o resolvedor de caminho o encontra e serve por readfile, enquanto o .htaccess nega o acesso web directo. Nenhuma alteracao ao endpoint foi necessaria.
8. Atravessar fronteiras de escola na migracao. O dreno usa o escola_id de cada linha para o directorio privado e inclui escola_id na clausula WHERE das actualizacoes, preservando o isolamento por escola.
9. Inflar a superficie de ataque sem reparar. O extractor confirma 199 itens; o admin_init e contabilizado uma vez (deduplicado) e nao ha endpoint novo. Sem opcoes novas (132), sem dependencias novas (12).
10. Corromper documentos_urls da equipa (JSON). O dreno descodifica o JSON com tolerancia (tenta tambem stripslashes) e so actualiza quando algum campo muda; se o JSON for invalido, salta a linha sem a alterar.

## P0

Nenhum. Nao ha perda de ficheiros (movimento so consuma apos remover o original; em falha, aborta sem alterar) nem regressao de calculo. A entrega fecha um vector real de exposicao.

## P1

Nenhum. O endpoint autenticado mantem-se intacto e o acesso pelo botao seguro nao muda; a foto e preservada; o isolamento por escola e respeitado na migracao.

## P2 e P3 (observacoes, nao bloqueantes)

- P2: a negacao por .htaccess cobre Apache e LiteSpeed; Nginx exige a regra equivalente (negar /sige-private/), documentada no DEPLOY.
- P3: documentos ainda nao migrados permanecem acessiveis pela URL directa ate o lote os alcancar; o residual diminui a cada navegacao no SIGE e os documentos novos ja entram privados.

## Decisao

Aprovado. Zero P0 e zero P1. O incremento fecha o vector da URL publica directa dos documentos sensiveis, sem perder ficheiros, sem alterar o endpoint, sem nova superficie, sem opcoes novas, sem alteracao de esquema e com o calculo academico e financeiro byte-identico. A foto fica explicitamente de fora (mostrada em linha) e os itens de ciclo de vida (links com expiracao, limpeza de orfaos, QR) ficam atribuidos ao incremento 3. Corredor 82/82, smoke do armazenamento privado com 15 verificacoes verdes, release gate verde a partir de pasta limpa. Pronto para entrega.
