# PHASE_CHARTER - SIGE SoftGenial v12.12.28

Fase 9 (Seguranca e governanca de acessos) - Incremento 1: Blindagem do modulo de permissoes.

## Objectivo

Tornar o modulo de Perfis e Permissoes seguro por desenho: nenhuma conta privilegiada pode ser trancada ou despromovida a partir dele, ninguem pode escalar privilegios atraves dele, e a porta de acesso fica coerente, com o admin TI a ver e a usar o modulo, mas sob guardas.

## Incluido

- Conta protegida na camada de dados: protegido = administrador WordPress real (sige_is_real_wp_admin_user). Os handlers de atribuir e remover recusam qualquer operacao cujo alvo seja uma conta protegida, com mensagem explicita e auditoria, mesmo com nonce valido.
- Interface: contas protegidas aparecem so de leitura, com selo Protegido, sem botoes de atribuir nem remover.
- Auto-proteccao e ultimo gestor: um gestor nao pode remover nem despromover o perfil que lhe da a si proprio a gestao de permissoes, nem deixar a escola sem nenhum gestor.
- Anti-escalada: um gestor que nao seja administrador WP real nao pode atribuir um perfil que confere usuarios.gerir_permissoes.
- Porta do menu coerente: Perfis e Permissoes passa a ser mostrado a quem tem usuarios.gerir_permissoes, mantendo Saude do Sistema e Centro de Configuracao restritos ao core admin. Migracao idempotente que garante a concessao dessa permissao ao admin TI na base de dados real.
- Avaliador unico de operacao (sige_permissions_avaliar_operacao) como fonte de verdade da decisao, chamado pelo handler. Gate e smoke dedicados.

## Excluido

- Hierarquia de perfis por nivel (a regra completa so atribui perfis iguais ou abaixo do seu nivel). Exige um campo de nivel nos perfis; fica para a Fase 9 Incr 2.
- Sobreposicao puramente aditiva (deixar de trocar o WP role com set_role). E a raiz do vector de lockout dos nao-super-admin e a direccao definitiva, mas e uma mudanca arquitectural maior; Fase 9 Incr 2/3. As guardas deste incremento tornam o modelo actual seguro entretanto.
- Migracao de esquema (a concessao e em dados, na tabela de permissoes de perfil) e alteracoes a regras de calculo.

## Riscos

- Tornar o modulo restritivo de mais (um admin legitimo bloqueado de uma accao legitima): mitigado por as guardas so bloquearem alvos protegidos, auto-bloqueio, ultimo gestor e escalada; todo o resto continua a funcionar.
- Falsos protegidos: protegido = administrador WP real, o mesmo criterio canonico ja usado no plugin.
- Regressao de acesso: a migracao e aditiva e idempotente, ninguem perde o que ja tinha.
- Decisao dispersa: mitigado por uma fonte unica de verdade (o avaliador), chamada pela interface, em vez de logica espalhada pelo handler.

## Criterios de aceitacao

- Atribuir ou remover perfil a um administrador WP real e recusado, com mensagem e auditoria, mesmo com nonce valido.
- Contas protegidas aparecem so de leitura, com selo, sem botoes.
- Um gestor nao-super-admin nao consegue despromover-se da gestao de permissoes, nem deixar a escola sem gestor, nem atribuir um perfil que confira a gestao de permissoes.
- O admin TI ve e acede a Perfis e Permissoes, com as guardas activas, e a concessao existe na base de dados real; Saude do Sistema e Centro de Configuracao continuam restritos ao core admin.
- Sem migracao de esquema, calculo byte-identico, baselines de design sem regressao, zero estilo inline novo, zero travessoes; gate e smoke verdes; corredor sobe de 68 para 70; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
