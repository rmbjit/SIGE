# Carta da fase - v12.12.34 - Blindagem de uploads e ficheiros (incremento 1)

Fase do roteiro restante: Seguranca de documentos e ficheiros (fecha a Fase 9 original: documentos, uploads e QR). Esta carta cobre o incremento 1 de tres.

## Objectivo

Garantir que nada perigoso entra no directorio de uploads e que, mesmo que entrasse, nao pode ser executado nem servido directamente pelo web. E a base de seguranca do sistema de ficheiros, antes de mover os documentos sensiveis para fora do directorio publico (incremento 2). Nao move ficheiros, nao altera onde a Biblioteca de Media os guarda, nao toca em regras de calculo nem no esquema da base de dados.

## Diagnostico que motiva o incremento

O lado dos downloads ja esta solido: includes/secure-document-download.php serve os documentos do aluno (doc_bi_url, doc_cert_url, doc_vacina_url) e de RH (doc_bi, doc_cv, doc_cert) por endpoints autenticados, com nonce por campo, controlo de acesso por permissao e por escola, proteccao de path traversal (realpath dentro de uploads), servico so de ficheiros locais, auditoria e cabecalhos nosniff. As views constroem os links por sige_secure_document_url.

Ficam, porem, duas dividas concretas no lado da entrada e do directorio:

1. Os ficheiros sensiveis entram pela Biblioteca de Media do WordPress e ficam no directorio publico wp-content/uploads, com a URL guardada na base de dados via esc_url_raw. O endpoint seguro e uma porta autenticada, mas a URL publica directa do ficheiro continua a responder a quem a tiver. (O fecho deste vector exige mover para armazenamento privado e e tratado no incremento 2.)
2. Nada filtra o que entra e o directorio de uploads nao tem blindagem anti-execucao. Nao existe wp_handle_upload_prefilter nem upload_mimes, e um ficheiro PHP carregado podia, em servidores mal configurados, ser executado pelo web. Ja existe, porem, um padrao limpo de blindagem reutilizavel (o Ledger e o backup escrevem .htaccess de negacao mais web.config mais index.php).

## Incluido

- Novo modulo includes/security-uploads.php, carregado em sige-softgenial.php logo apos backup-safety.
- Blindagem anti-execucao do directorio de uploads, idempotente no admin_init: escreve .htaccess e web.config que negam a execucao e o acesso web a ficheiros perigosos (PHP e scripts). A negacao por FilesMatch (Require all denied, com recuo para Apache 2.2 via Order deny) e a proteccao transversal; as directivas php_admin_flag ficam dentro de IfModule mod_php para nao quebrar em LiteSpeed ou PHP-FPM. Escreve tambem index.php de silencio. So escreve se faltar ou se o marcador de versao das regras nao estiver presente.
- Filtro wp_handle_upload_prefilter que recusa ficheiros perigosos no momento do upload: por extensao (incluindo duplas extensoes enganosas como factura.php.jpg), por bytes magicos reais (finfo), por inicio de ficheiro (assinaturas MZ, ELF, Mach-O, shebang e abertura de PHP, sem confundir com XML), por imagem declarada que nao e imagem real (getimagesize), e por SVG com script, eventos ou entidades. Bloqueia apenas o comprovadamente perigoso; tudo o resto segue a validacao normal do WordPress, para nao recusar tipos legitimos.
- Validacao do mesmo tipo no ponto de importacao de alunos (aluno-fetch-ajax.php): o .xlsx tem de ser um pacote ZIP valido (bytes PK); o .csv ou .txt tem de ser texto e nao pode comecar por codigo. Recusa um executavel ou script disfarcado de lista.
- Gate estatico (tools/check-uploads-hardening.php) e smoke de runtime (tools/smoke-uploads-hardening.php), registados no corredor, que passa para 80 verificacoes.

## Excluido (fica para os incrementos 2 e 3)

- Mover os documentos sensiveis para fora do directorio publico (sige-private) e a respectiva migracao dos ficheiros existentes.
- Bloquear o acesso web directo aos documentos sensiveis ja existentes.
- Links temporarios com expiracao e reforco do registo de download.
- Limpeza de ficheiros orfaos e confirmacao do QR local.
- Nenhuma regra de calculo academico ou financeiro e tocada. Sem novo ecra.

## Riscos

- Um filtro de upload demasiado severo podia recusar ficheiros legitimos. Mitigacao: o filtro bloqueia apenas o comprovadamente perigoso (executaveis, scripts, PHP, SVG com script, imagem falsa); os tipos legitimos seguem a validacao normal do WordPress, e a regeneracao de miniaturas pela Media nao passa pelo prefilter de upload original.
- A directiva de desactivacao do motor PHP, fora de um bloco de modulo, podia provocar erro 500 em LiteSpeed ou PHP-FPM. Mitigacao: as directivas php_admin_flag estao dentro de IfModule mod_php, mod_php7 e mod_php8; a proteccao efectiva e a negacao por FilesMatch, honrada por Apache e LiteSpeed.
- A blindagem por .htaccess nao se aplica a Nginx. Mitigacao: a regra equivalente (negar a execucao de PHP em uploads) fica documentada no DEPLOY.
- Residual conhecido: os documentos sensiveis ja existentes continuam acessiveis pela URL publica directa ate o incremento 2. Documentado no registo de risco.

## Criterios de aceitacao

- O directorio wp-content/uploads passa a ter .htaccess e web.config com as regras de negacao e o marcador de versao.
- Um ficheiro PHP renomeado para .jpg e recusado tanto no upload pela Biblioteca de Media como na importacao de alunos.
- Um .xlsx que nao seja um pacote ZIP valido e recusado na importacao.
- Ficheiros legitimos (imagens reais, PDF, .xlsx valido, CSV de texto) continuam a ser aceites.
- Os documentos do aluno e de RH continuam a abrir pelo botao seguro.
- Superficie de accao inalterada (manifesto e Kernel em 199, enforce 33; views 60), sem opcoes novas (132), sem dependencias novas (12), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 80/80 e release gate verde a partir de pasta limpa. Rediagnostico adversarial Zero P0/P1.
