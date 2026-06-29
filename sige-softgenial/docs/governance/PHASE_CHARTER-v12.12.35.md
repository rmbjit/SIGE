# Carta da fase - v12.12.35 - Armazenamento privado dos documentos sensiveis (incremento 2)

Fase do roteiro restante: Seguranca de documentos e ficheiros (fecha a Fase 9 original: documentos, uploads e QR). Esta carta cobre o incremento 2 de tres.

## Objectivo

Fechar o vector que ficou em aberto no incremento 1: os documentos sensiveis viviam no directorio publico wp-content/uploads e a sua URL publica directa respondia a quem a tivesse. Este incremento move esses documentos para um directorio privado, negado ao acesso web directo, e passa a servi-los apenas pelo endpoint autenticado. O caminho de acesso para o utilizador nao muda; apenas a URL directa deixa de devolver o ficheiro.

## Diagnostico que motiva o incremento

O endpoint de download ja era seguro (autenticado, com nonce por campo, controlo de acesso por permissao e por escola, proteccao de path traversal, servico so de ficheiros locais, auditoria e nosniff). Mas os ficheiros entravam pela Biblioteca de Media e ficavam no directorio publico, com a URL guardada na base de dados. Quem conhecesse ou adivinhasse essa URL obtinha o ficheiro sem autenticacao. As views ja acedem a estes documentos exclusivamente pelo endpoint (sige_secure_document_url e a variante da equipa), pelo que torna-los privados e transparente para a interface.

## Incluido

- Novo modulo includes/security-uploads-private.php, carregado em sige-softgenial.php logo apos o modulo de blindagem de uploads.
- Directorio privado wp-content/uploads/sige-private/docs/<escola_id>, com .htaccess e web.config de negacao total do acesso web e index.php de silencio, escritos de forma idempotente.
- Choke-point sige_uploads_privatize_doc_value, ligado aos pontos de gravacao dos documentos do aluno (db-handler: doc_bi_url, doc_cert_url, doc_vacina_url) e da equipa (caminho AJAX: doc_bi, doc_cv, doc_cert). Na gravacao, o documento novo e movido para o directorio privado e a URL guardada aponta para la.
- Dreno de migracao idempotente e resumivel (sige_uploads_private_migration_drain), pendurado no admin_init das paginas do SIGE, que trata os documentos ja existentes em lotes (ate 150 por carregamento) e fica inerte quando nada resta. Re-consulta o que falta a cada chamada, pelo que e seguro interromper e retomar sem qualquer marcador de estado (logo, sem opcao nova).
- Movimento seguro contra perda: copia o ficheiro, verifica o tamanho, constroi a URL privada e so consuma o movimento depois de remover o original com sucesso; em qualquer falha aborta apagando a copia e o valor guardado mantem-se. As variantes de imagem e pre-visualizacoes ao lado do original (miniaturas) sao movidas tambem, para nao deixar copias publicas.
- Gate estatico (tools/check-uploads-private.php) e smoke de runtime (tools/smoke-uploads-private.php), registados no corredor, que passa para 82 verificacoes.

## Excluido (fica para o incremento 3 ou para fora da fase)

- A foto (campo foto e foto_perfil) NAO e tornada privada: e mostrada em linha como imagem e tornar-la privada partiria a sua apresentacao. Permanece publica por desenho.
- Links com expiracao e reforco do registo de download.
- Limpeza de ficheiros orfaos (ficheiros publicos que tenham ficado sem referencia).
- Confirmacao do QR local.
- Nenhuma regra de calculo academico ou financeiro e tocada. Sem novo ecra. Sem alteracao de esquema.

## Riscos

- Mover ficheiros pode, em teoria, perder um ficheiro. Mitigacao: o movimento so consuma depois de remover o original com sucesso; se a copia, a verificacao do tamanho, a construcao da URL ou a remocao do original falharem, aborta apagando a copia e nao altera o valor guardado (degrada para o comportamento de hoje, sem regressao).
- O dreno corre em cada carregamento de pagina do SIGE. Mitigacao: nao corre em AJAX nem em cron, processa um lote limitado e a consulta do que falta e barata e devolve vazio depois de concluida a migracao.
- A negacao por .htaccess nao se aplica a Nginx. Mitigacao: a regra equivalente (negar /sige-private/) fica documentada no DEPLOY.
- Documentos ainda nao migrados permanecem acessiveis pela URL directa ate o lote os alcancar. Mitigacao: a migracao corre por lotes a cada navegacao no SIGE e os documentos novos ja entram privados; o residual diminui a cada carregamento.

## Criterios de aceitacao

- Passa a existir wp-content/uploads/sige-private/docs com .htaccess de negacao total e marcador.
- A URL publica directa de um documento ja migrado devolve 403, enquanto o documento continua a abrir pelo botao seguro.
- Um documento novo (aluno ou equipa) entra directamente no directorio privado.
- A foto do aluno continua a aparecer em linha normalmente.
- Nenhum ficheiro e perdido em caso de falha de movimento (o valor guardado mantem-se).
- Superficie de accao inalterada (manifesto e Kernel em 199, enforce 33; views 60), sem opcoes novas (132), sem dependencias novas (12), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 82/82 e release gate verde a partir de pasta limpa. Rediagnostico adversarial Zero P0/P1.
