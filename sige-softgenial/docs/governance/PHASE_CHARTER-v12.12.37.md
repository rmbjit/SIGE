# Carta da fase - v12.12.37 - Fase 1 Incremento 3 - Ciclo de vida dos documentos

Incremento que fecha a Fase 1 (seguranca de documentos e ficheiros). Constroi sobre o Incremento 1 (perimetro anti-execucao e bloqueio de tipos) e o Incremento 2 (armazenamento privado e migracao).

## Objectivo

Fechar a seguranca do ciclo de vida dos documentos: limitar no tempo e tornar infalsificaveis os links de acesso, registar quem descarrega e de onde, recuperar espaco removendo com seguranca os ficheiros privados sem referencia, e gerar o codigo QR localmente para que dados do aluno nao saiam para servicos externos.

## Incluido

Frente 1 - Links com expiracao. Os links do endpoint seguro de documentos (fluxo do aluno e fluxo da equipa) passam a transportar exp (instante de expiracao) e sig (HMAC-SHA256 de escopo, id, campo e expiracao), alem do nonce. O segredo e estavel por instalacao (wp_salt), sem opcao nova. Os handlers recusam (403) links expirados ou com assinatura invalida, com comparacao em tempo constante (hash_equals). A validade e ajustavel pela constante SIGE_SECURE_DOC_LINK_TTL (por omissao 3600s).

Frente 2 - Registo de download reforcado. O registo de auditoria de cada descarregamento passa a incluir o IP do cliente (saneado) e o utilizador, nos dois fluxos, alem do aluno ou professor, campo, rotulo e nome do ficheiro ja existentes.

Frente 3 - Limpeza de orfaos. Rotina segura, idempotente e resumivel que remove do armazenamento privado os ficheiros sem qualquer referencia em base de dados e mais antigos que um periodo de graca (constante SIGE_PRIVATE_ORPHAN_GRACE, por omissao 86400s), cobrindo os ficheiros que ficam para tras quando um documento e substituido. Corre na mesma passagem por admin_init que ja faz a migracao, em lotes.

Frente 4 - QR local. Os cartoes de estudante deixam de gerar o codigo QR num servico externo (api.qrserver.com) e passam a gera-lo no proprio navegador com a biblioteca qrious (ja no catalogo com SRI). A dependencia externa api.qrserver.com e removida.

## Excluido

- A foto do aluno continua publica (e mostrada em linha) e fora do armazenamento privado; nao e abrangida por esta fase.
- Nao se cria nenhum endpoint de verificacao publica do QR (evita aumentar a superficie); o QR encoda a mesma identificacao de antes, agora gerada localmente.
- Nenhuma regra de calculo academico ou financeiro e tocada. O backend de aprovacao e rejeicao nao e tocado.
- O cofre de segredos, observabilidade, CSP e restantes temas ficam para as fases seguintes.

## Riscos

- Bloqueio por expiracao do link. Mitigacao: validade generosa por omissao (1h) e regeneracao do link a cada abertura da ficha; a constante permite ajustar.
- Eliminacao indevida de ficheiros pela limpeza de orfaos. Mitigacao tripla: periodo de graca, conjunto de referencias obrigatorio (aborta sem apagar se nao puder ser lido) e lista de proteccao (.htaccess, web.config, index); a rotina opera apenas dentro de sige-private/docs.
- O qrious nao carregar. Mitigacao: dependencia ja no catalogo (cdnjs, com SRI) e pixel transparente de recurso para a imagem nao ficar partida; o numero de processo nunca sai do dispositivo.

## Criterios de aceitacao

- Um link de documento expira ao fim da validade (403) e nao pode ser forjado; reabrir a partir da ficha gera um link novo e funciona.
- O registo de auditoria de um descarregamento inclui o IP e o utilizador.
- Apos substituir um documento e passado o periodo de graca, o ficheiro antigo desaparece do armazenamento privado; ficheiros referenciados e de proteccao permanecem sempre.
- O QR dos cartoes e gerado localmente; o numero de processo nao e enviado para nenhum servico externo.
- Superficie de accao inalterada (199, enforce 33; views 60), sem opcoes novas (132), dependencias externas reduzidas para 11, versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 85/85 e release gate verde a partir de pasta limpa. Rediagnostico adversarial Zero P0/P1. Fase 1 completa.
