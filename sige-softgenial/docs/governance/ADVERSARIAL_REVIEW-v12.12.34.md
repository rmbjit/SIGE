# Revisao adversarial - v12.12.34 - Blindagem de uploads e ficheiros

Rediagnostico adversarial do incremento 1 da fase de documentos, uploads e QR. O exercicio assume a postura de um atacante e de um revisor hostil e procura partir a entrega antes de a declarar pronta.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Carregar um PHP renomeado para imagem (factura.jpg com conteudo PHP). O avaliador le o inicio do ficheiro e reconhece a abertura de PHP, recusando antes de olhar para a extensao. Coberto pelo smoke.
2. Esconder o PHP numa dupla extensao (factura.php.jpg). A verificacao percorre todos os segmentos de extensao e nao apenas o ultimo, apanhando o .php intermedio. Coberto pelo smoke.
3. Enviar um executavel (MZ ou ELF) com extensao inofensiva. A leitura das assinaturas binarias no inicio do ficheiro recusa-o. Coberto pelo smoke.
4. Enviar um SVG com script embebido ou eventos onload. O conteudo e examinado e recusado. Coberto pelo smoke.
5. Enviar um ficheiro de texto renomeado para .jpg. O ficheiro nao passa em getimagesize e e recusado como imagem invalida. Coberto pelo smoke.
6. Importar uma lista de alunos que e na verdade um script. A validacao da importacao exige que o .xlsx seja um pacote ZIP e que o .csv ou .txt nao comece por codigo. Coberto pelo smoke.
7. Confundir o detector com XML legitimo (que tambem comeca por menor-que-ponto-interrogacao). A verificacao distingue a abertura curta de PHP da declaracao XML, deixando o XML passar. Coberto pelo smoke.
8. Tentar que a blindagem do directorio derrube o site. As directivas de PHP estao dentro de IfModule mod_php; em LiteSpeed ou PHP-FPM sao ignoradas sem erro 500, e a proteccao efectiva (negacao por FilesMatch) e honrada. A escrita e idempotente e nunca reescreve quando o marcador ja esta presente. Coberto pelo smoke (idempotencia) e pela nota de DEPLOY.
9. Contornar o endpoint seguro de documentos. Nao foi alterado; continua a ler por readfile do lado do servidor, pelo que a blindagem do directorio nao o afecta. A blindagem nega o acesso web directo a tipos de codigo, nao aos documentos servidos pelo endpoint.
10. Inflar a superficie de ataque sem reparar. O extractor confirma 199 itens; o admin_init e contabilizado uma vez (deduplicado) e o prefilter, por ser filtro, nao e endpoint. Sem opcoes novas (132), sem dependencias novas (12).

## P0

Nenhum. Nao ha caminho de execucao remota introduzido nem regressao de calculo. A entrega so acrescenta defesa.

## P1

Nenhum. O endpoint autenticado de documentos mantem-se intacto; o filtro de upload bloqueia apenas o comprovadamente perigoso, sem recusar tipos legitimos; a blindagem do directorio nao quebra em LiteSpeed nem em FPM.

## P2 e P3 (observacoes, nao bloqueantes)

- P2: a blindagem por .htaccess cobre Apache e LiteSpeed; Nginx exige a regra equivalente, documentada no DEPLOY.
- P3: os documentos sensiveis ja existentes continuam acessiveis pela URL publica directa ate o incremento 2 os mover para armazenamento privado. Documentado no registo de risco como residual conhecido e proximo passo da fase.

## Decisao

Aprovado. Zero P0 e zero P1. O incremento fecha o vector de execucao no directorio de uploads e o vector de entrada de tipos perigosos, sem nova superficie, sem opcoes novas, sem alteracao de esquema e com o calculo academico e financeiro byte-identico. O residual (acesso directo aos documentos existentes) fica explicitamente atribuido ao incremento 2. Corredor 80/80, smoke da blindagem com 26 verificacoes verdes, release gate verde a partir de pasta limpa. Pronto para entrega.
