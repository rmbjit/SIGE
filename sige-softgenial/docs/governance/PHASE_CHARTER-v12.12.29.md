# PHASE_CHARTER - SIGE SoftGenial v12.12.29

Fase 9 (Seguranca e governanca de acessos) - Incremento 2: Hierarquia de perfis por nivel.

## Objectivo

Dar ao modulo de Perfis e Permissoes uma hierarquia: cada perfil tem um nivel, e um gestor so pode atribuir e mexer em perfis abaixo do seu proprio nivel. Generaliza a regra anti-escalada do Incr 1 (binaria) para uma regra granular, mantendo a garantia forte de que so o dono (administrador WordPress real) cria gestores.

## Niveis

Os tres gestores no topo (direccao_geral e admin_escola a 90, admin_ti a 80); os restantes por responsabilidade (director, dir_pedagogico, gestor_rh a 70; secretaria_geral a 60; secretaria, secretario, tesoureiro a 50; recepcao, assistente a 40; professor, educador a 30; guarda, motorista, limpeza a 10; encarregado a 0). Perfis desconhecidos ou personalizados ficam no nivel 0, o mais restritivo. Mapa em codigo (sige_permissions_role_niveis), a semelhanca do risco das permissoes; sem alteracao de esquema; filtravel.

## Incluido

- Mapa de niveis e funcoes de apoio (nivel de um perfil; nivel de um utilizador pelo seu perfil SIGE activo).
- Nova guarda no avaliador, para actores nao protegidos: so atribui um perfil cujo nivel seja estritamente inferior ao seu, e so mexe num utilizador cujo nivel actual seja estritamente inferior ao seu (codigo nivel_insuficiente). A regra do Incr 1 mantem-se por cima: nenhum nao-administrador atribui um perfil que confira gestao, qualquer que seja o nivel.
- Interface coerente: para actores nao protegidos, o selector de perfil lista so os perfis atribuiveis (abaixo do seu nivel e sem gestao); linhas de utilizadores ao seu nivel ou acima ficam so de leitura, com nota. Contas protegidas continuam so de leitura com selo (Incr 1). Administradores WordPress reais mantem autoridade plena e veem tudo.
- Gate e smoke dedicados para a regra de nivel.

## Excluido

- Sobreposicao puramente aditiva (deixar de trocar o WP role com set_role). Fase 9 Incr 3.
- Niveis editaveis na base de dados ou na interface (coluna por perfil, ecra de edicao). Este incremento usa um mapa em codigo.
- Sem alteracao de esquema e sem alteracao a regras de calculo.

## Riscos

- Perfis personalizados ficam no nivel 0: mitigado por ser o lado seguro e pela rede de seguranca da regra de gestao.
- Estritamente inferior pode parecer restritivo (um gestor nao mexe noutro do mesmo nivel): decisao de desenho que preserva e reforca as garantias do Incr 1; o administrador WordPress real resolve os casos de pares.

## Criterios de aceitacao

- Um actor nao protegido nao consegue atribuir um perfil de nivel igual ou superior ao seu, nem mexer num utilizador de nivel igual ou superior; o selector so oferece perfis atribuiveis.
- A garantia do Incr 1 mantem-se: nenhum nao-administrador cria gestores.
- O administrador WordPress real mantem autoridade plena; contas protegidas continuam so de leitura.
- Sem migracao de esquema, calculo byte-identico, baselines de design sem regressao, zero estilo inline novo, zero travessoes; gate e smoke verdes; corredor sobe de 70 para 72; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
