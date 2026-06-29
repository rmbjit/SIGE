# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.29

Fase 9 incremento 2: hierarquia de perfis por nivel.

## Rediagnostico adversarial

Revisao hostil da hierarquia, procurando escalada de privilegios pela atribuicao lateral ou ascendente, contorno da regra de nivel, e regressao das guardas do Incr 1.

1. Escalada ascendente. Um actor nao protegido nao pode atribuir um perfil de nivel igual ou superior ao seu. Verificado pelo smoke (atribuir gestor_rh ao mesmo nivel e recusado; atribuir um perfil de nivel inferior e permitido).

2. Atribuicao lateral (mintar pares). Como a regra e estritamente inferior, um actor nao mexe num utilizador do mesmo nivel nem lhe atribui um perfil do seu nivel. Verificado pelo smoke (admin_ti sobre outro admin_ti e recusado por nivel_insuficiente).

3. Minar gestores por nivel. Mesmo que um perfil de gestao tivesse um nivel mal configurado, a regra anti-escalada do Incr 1 mantem precedencia: nenhum nao-administrador atribui um perfil que confira gestao. Verificado pelo smoke (admin_ti a tentar atribuir admin_escola e recusado por escalada_gestao, nao por nivel).

4. Perfis personalizados. Ficam no nivel 0 (o mais restritivo), pelo que so niveis altos os podem atribuir, e se conferirem gestao a regra anti-escalada cobre-os. Sem brecha por perfil novo nao mapeado.

5. Contorno pela interface. O selector so lista perfis atribuiveis (abaixo do nivel do actor e sem gestao) e as linhas de utilizadores de nivel igual ou superior ficam so de leitura. Ainda assim, a decisao de seguranca esta no handler via avaliador, nao na interface: um POST forjado para um alvo de nivel superior ou para um perfil acima e recusado e auditado.

6. Regressao do Incr 1. Todas as guardas do Incr 1 (alvo protegido, anti-escalada, auto-proteccao, ultimo gestor) continuam a funcionar; o smoke do Incr 1 mantem-se verde apos a adicao da guarda de nivel. As guardas do Incr 1 que precedem a de nivel (alvo protegido, escalada, auto, ultimo gestor) tem a ordem correcta.

7. Deriva de superficie ou de calculo. Sem novo ecra nem endpoint: manifesto e Kernel mantem-se em 199 (enforce 33), alinhados; allowlist de views em 60. Regras de calculo byte-identicas. SCHEMA inalterada (mapa de niveis em codigo). Baselines de design sem regressao; reutiliza o selo neutro do Incr 1, sem estilo inline novo.

## P0

Nenhum. A guarda de nivel esta no avaliador (fonte unica de verdade), aplica-se nos dois ramos e e auditada quando bloqueia; a regra anti-escalada mantem precedencia.

## P1

Nenhum. Durante o desenvolvimento foi corrigida na mesma sessao a remocao acidental do inicio da consulta de perfis activos ao inserir o calculo do nivel do actor na interface (apanhada pelo lint antes de qualquer empacotamento); e foi actualizado o teste do Incr 1 para fornecer nivel ao actor, ja que a nova consulta de nivel nao existia no seu mock (o produto estava correcto; era o teste que estava desactualizado).

## Decisao

Aprovado para entrega como v12.12.29. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: niveis num mapa em codigo (sem alteracao de esquema); perfis desconhecidos ou personalizados no nivel 0 (lado seguro); regra estritamente inferior (preserva e reforca o Incr 1); regra anti-escalada com precedencia sobre a de nivel. Deferido para a Fase 9 Incr 3 (declarado): sobreposicao puramente aditiva (deixar de trocar o WP role) e niveis editaveis na base de dados ou na interface.
