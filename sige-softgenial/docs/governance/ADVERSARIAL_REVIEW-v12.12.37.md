# Revisao adversarial - v12.12.37 - Fase 1 Incremento 3 - Ciclo de vida dos documentos

Rediagnostico adversarial das quatro frentes que fecham a Fase 1. O exercicio assume a postura de um revisor hostil e procura partir cada frente antes de a declarar pronta.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Reutilizar um link de documento que fugou. O link tem exp e sig (HMAC com segredo da instalacao). Passada a validade, o handler recusa com 403. O smoke confirma: link fresco aceite; expirado, campo adulterado, escopo adulterado, assinatura vazia e assinatura alterada todos recusados.
2. Forjar um link sem conhecer o segredo. A assinatura e HMAC-SHA256 de escopo, id, campo e expiracao; sem o segredo (wp_salt) nao e possivel produzir um sig valido, e a comparacao e em tempo constante (hash_equals).
3. Esticar a validade adulterando exp. Alterar exp invalida o sig (faz parte do material assinado), pelo que o link e recusado. Reduzir o campo de expiracao a um instante passado tambem e recusado pela verificacao de tempo.
4. Apagar um documento valido pela limpeza de orfaos. A rotina so apaga ficheiros sem qualquer referencia em base de dados e mais antigos que o periodo de graca; aborta sem apagar nada se nao conseguir construir o conjunto de referencias; nunca toca nos ficheiros de proteccao; opera apenas dentro de sige-private/docs. O smoke confirma: remove o orfao antigo, mantem o referenciado, o recente e os ficheiros de proteccao, e aborta sem apagar quando as referencias falham.
5. Apanhar um ficheiro recem-criado numa corrida de gravacao. O periodo de graca (24h por omissao) protege os ficheiros recentes; um documento acabado de gravar nunca e considerado orfao.
6. Fazer fugar o numero de processo do aluno pelo QR. O QR passa a ser gerado no proprio navegador (qrious, do catalogo com SRI); o numero de processo nunca e enviado para um servico externo. api.qrserver.com foi removido de todo o codigo e da baseline de dependencias (de 12 para 11).
7. Inflar a superficie ou as opcoes. As alteracoes nao adicionam add_action novos (os handlers ja existiam; a limpeza corre na passagem por admin_init que ja existe) nem opcoes (usa wp_salt e constantes). O extractor confirma 199 itens de superficie e 132 opcoes.
8. Bloquear o utilizador com expiracao curta demais. A validade por omissao e de 1h e o link e regenerado a cada abertura da ficha; a constante permite ajustar sem alterar codigo.

## P0

Nenhum. Links infalsificaveis e com expiracao; limpeza de orfaos sem risco de perda de ficheiros referenciados ou de proteccao.

## P1

Nenhum. Privacidade reforcada (QR local) e rasto de download mais completo (IP e utilizador).

## P2 e P3

Nenhum novo. Riscos de bloqueio por expiracao e de indisponibilidade do qrious mitigados como descrito na carta da fase.

## Decisao

Aprovado. Zero P0 e zero P1. As quatro frentes estao completas e provadas por gate e smoke dedicados (corredor 85/85): links com expiracao assinados e recusados em tempo constante, registo de download reforcado com IP e utilizador, limpeza de orfaos segura (graca, abortar se referencias nulas, proteccao) e QR gerado localmente com remocao da dependencia externa api.qrserver.com (deps 12 para 11). Sem nova superficie, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Release gate verde a partir de pasta limpa. A Fase 1 fica completa. Pronto para entrega.
