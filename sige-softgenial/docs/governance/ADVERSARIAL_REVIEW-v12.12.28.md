# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.28

Fase 9 incremento 1: blindagem do modulo de permissoes.

## Rediagnostico adversarial

Revisao hostil do modulo de permissoes, procurando lockout de contas privilegiadas, escalada de privilegios, e contorno das guardas.

1. Lockout do administrador WordPress. Confirmado primeiro que o modelo actual ja nao trancava o administrador WP (a troca do WP role e saltada para administradores e o sige_can faz bypass). Mesmo assim, a protecca passa a ser explicita: o avaliador recusa qualquer atribuicao ou remocao cujo alvo seja um administrador WP real, removendo a dependencia de a conta manter para sempre o papel WordPress. Verificado por gate e smoke.

2. Lockout de um gestor nao-administrador. Era o risco mais serio (a atribuicao troca o WP role e estes gestores nao fazem bypass). Resolvido pela auto-proteccao (nao se despromove nem se remove a si proprio) e pela protecca do ultimo gestor (nao se deixa a escola sem gestor). Verificado pelo smoke (auto_despromocao, auto_remocao, ultimo_gestor).

3. Escalada de privilegios. Um gestor que nao seja administrador WP real nao pode atribuir um perfil que confira a gestao de permissoes, pelo que nao cria outros gestores nem escala alguem para o modulo. So administradores WP reais mintam gestores. Verificado pelo smoke (escalada_gestao) e pela deteccao do perfil de gestao via declaracao e base de dados (fail-safe para o lado mais restritivo).

4. Contorno pela interface. As contas protegidas nao tem formulario de accao (so leitura, com selo). O gate verifica que o ramo protegido nao contem sige_perm_action.

5. Contorno pelo POST forjado. O bloqueio esta no handler, que chama o avaliador antes de qualquer escrita, e nao apenas na interface; o avaliador e a fonte unica de verdade. Mesmo com nonce valido, um pedido sobre uma conta protegida ou de escalada e recusado e auditado.

6. Porta do menu incoerente. O link Perfis e Permissoes passa a ser mostrado pela mesma permissao da view (usuarios.gerir_permissoes), e nao por uma condicao diferente (core admin). Saude do Sistema e Centro de Configuracao continuam restritos ao core admin. A migracao de reconciliacao garante a concessao ao admin TI na base de dados real.

7. Deriva de superficie ou de calculo. Sem novo ecra nem endpoint: manifesto e Kernel mantem-se em 199 (enforce 33), alinhados; allowlist de views em 60. Regras de calculo byte-identicas. SCHEMA inalterada. Baselines de design sem regressao.

## P0

Nenhum. As guardas estao no handler (fonte unica de verdade), bloqueiam alvos protegidos, escalada, auto-bloqueio e ultimo gestor, e sao auditadas.

## P1

Nenhum. Durante o desenvolvimento foi corrigida na mesma sessao a remocao acidental da declaracao da funcao sige_can ao inserir os novos helpers (apanhada pelo lint antes de qualquer empacotamento).

## Decisao

Aprovado para entrega como v12.12.28. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: protegido = administrador WP real (criterio canonico); a protecca do ultimo gestor e da auto-proteccao aplicam-se a actores nao protegidos, pois um administrador WP real tem autoridade plena e nunca fica trancado; a deteccao de perfil de gestao e fail-safe para o lado restritivo. Fica declarado que o modelo actual ainda sincroniza o WP role de nao-administradores; a sobreposicao puramente aditiva e a hierarquia por nivel de perfil ficam para a Fase 9 Incr 2. Abre a Fase 9 (seguranca e governanca de acessos).
